# Why New Relic Started Mangling ModSecurity Logs

Findings and recommended fixes for the log-parsing regression introduced by
PR #2838 (branch `USAGOV-2834-waf-modsecurity-work`), which loaded the OWASP CRS
into the WAF and enabled a JSON audit log.

The custom log parser at
`/srv/usagov-logshipper/project_conf/scripts/parse_modsecurity_keys.lua`
is **out of scope and must not be modified**. Every fix below is confined to
`/srv/usagov-2021/`.

---

## Findings

**The diagnosis is confirmed, and the mechanism is specific.**

`parse_modsecurity_keys.lua` is built for exactly one input shape: ModSecurity's
**nginx error-log alert line** — `ModSecurity: …` followed by `[key "value"]`
pairs and a trailing `, client: …, server: …`. The commented-out `dummy_modsec1`
input in `/srv/usagov-logshipper/project_conf/fluentbit.conf` documents that
contract verbatim, and it maps 1:1 onto the July CSV columns
(`Modsecurity.id`, `Modsecurity.msg`, `Modsecurity.level`, …).

The shipping pipeline is: cloud.gov log drain → TCP 8888 → `post-with-syslog`
parser → the whole app log line lands in `record["message"]` →
`parse_modsecurity_keys`.

PR #2838 added a **second, structurally different** log stream —
`SecAuditEngine RelevantOnly` + `SecAuditLog /dev/stdout` +
`SecAuditLogFormat JSON`. Three things then go wrong:

1. **It still triggers.** The guard is
   `string.find(string.lower(record["message"]), "modsecurity")`. Audit records
   contain `modsecurity` in CRS rule paths
   (`/etc/modsecurity.d/modsecurity-crs/rules/…`) and in `producer.modsecurity`.
   Every audit record fires the filter.

2. **`%[(.-)%]` matches JSON arrays, not alert brackets.** It hits
   `"messages":[…`, `"tags":[…`, and `[` characters inside regex text in the
   `match` field (e.g. `` `(?:^([\d.]+|\[[\da-f:]+\]…` ``).

3. **Keys are emitted with raw unescaped quotes.** The first space-delimited
   token becomes the key, producing keys like `{"message":"SQL`. Values are
   sanitised with `:gsub('"','')` — **keys never are.** The result is malformed
   JSON in `record["modsecurity"]`, which is what New Relic mangles.

### The more useful finding: the audit log was never necessary

From the ModSecurity-nginx v1.0.3 source:

- `ngx_http_modsecurity_log()` — the `msc_set_log_cb` callback that emits **every
  matched rule message** — logs at **`NGX_LOG_INFO`**
  (`src/ngx_http_modsecurity_log.c`).
- Interventions (blocks) log at **`NGX_LOG_ERR`** in
  `ngx_http_modsecurity_process_intervention()`.

And `.docker/src-waf/etc/nginx/nginx.conf.tmpl` line 9 hardcodes
`error_log /dev/stderr warn;`, which suppresses INFO.

That is the whole reason detection-only testing showed nothing in the error log,
and why audit logging appeared to be mandatory. It is not — the messages were
being emitted all along at a suppressed log level.

Note also that the `LOGLEVEL` and `ERRORLOG` environment variables in
`Dockerfile-waf` are inert: `error_log` is hardcoded in the template and never
substituted. This is the same class of bug as the `MODSEC_REQ_BODY_*` /
`MODSEC_RULE_ENGINE` variables already documented in that file.

---

## Recommended fix

Drop the audit log; lower the error-log level instead. Detection-only survives,
and the Lua parser needs no change because the messages arrive in the format it
already parses.

| File | Change |
|---|---|
| `.docker/src-waf/etc/nginx/nginx.conf.tmpl` (line 9) | `warn` → `info` — the entire visibility fix |
| `.docker/src-waf/opt/owasp-crs/modsecurity-override.conf` | Revert to `SecAuditEngine Off`, re-comment the `SecAuditLog*` lines |
| `.docker/src-waf/etc/nginx/snippets/owasp-modsecurity-main.conf` | Its "detection-only is silent without audit logging" warning is now wrong — rewrite it |
| `.docker/Dockerfile-waf` | Optional: define `MODSEC_TAG`, currently emitted literally as `${MODSEC_TAG}` in every alert |

With `error_log … info`, non-blocking CRS matches emit:

```
ModSecurity: Warning. Matched … [file "…"] [line "46"] [id "942100"]
  [msg "SQL Injection Attack Detected via libinjection"] [data "…"]
  [severity "2"] … [hostname "…"] [uri "/"] [unique_id "…"] [ref "…"]
```

— the same shape as the existing `ModSecurity: Access denied …` lines the parser
already handles correctly.

> nginx itself emits little at `info`; debug output requires both a
> `--with-debug` build *and* `error_log … debug`. The real volume increase comes
> from ModSecurity — see caveats.

---

## Caveats that survive the fix

Mostly inherent to the Lua parser and not addressable without modifying it, so
they should be documented rather than worked around.

- **`Modsecurity.level` changes meaning — check your dashboards.** Previously
  every ModSecurity record arrived at `error`, because only blocks were logged.
  Now **detections arrive at `info` and blocks stay at `error`**. That is a
  useful distinction, but any New Relic dashboard or alert that counts
  "ModSecurity errors" will see a different mix, and anything filtering
  `Modsecurity.level = error` will silently exclude all CRS detections.

- **Duplicate `tag` keys — last one wins.** CRS rules carry many `[tag "…"]`
  entries and the parser emits one `"tag":"…"` per occurrence, producing
  duplicate keys. Verified against the real parser: a single 942100 SQLi alert
  emitted **8** `tag` keys, of which only the final one (`PCI/6.5.2`) survived
  JSON parsing. Note this also means `MODSEC_TAG` is overwritten on any
  multi-tag CRS rule; it only survives on single-tag rules such as 200002. The
  old 7-rule config never hit this because it had at most one tag per alert.

- **`[data "…"]` containing `]`** — likely with XSS payloads — terminates the
  non-greedy `%[(.-)%]` match early, truncating the value and potentially
  emitting spurious keys from the remainder.

- **Volume rises.** One error-log line per *matched rule*, not per transaction.
  A single SQLi probe produced roughly six lines in local testing, versus one
  audit record. Measure on dev before promoting to prod.

---

## Verification

1. Build and run locally; confirm startup still reads
   `rules loaded inline/local/remote: 0/807/0`.
2. Send the benign canary (`curl -H 'User-Agent;'`) and assert a line matching
   `ModSecurity: Warning.*\[id "920330"\]` appears. This previously produced
   nothing — it is the direct regression test.
3. Confirm no JSON audit records remain: `grep '"transaction"'` must return
   nothing.
4. Confirm blocking is unchanged: a malformed XML body still returns 400 and
   still logs `[id "200002"]`.
5. Replay a captured error-log line through the real Lua offline and assert the
   output parses as valid JSON. Tests the reported symptom without touching the
   logshipper.
