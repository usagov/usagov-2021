# WAF Canary Test Plan — Dev Verification (USAGOV-2834)

Companion to PR #2838, branch `USAGOV-2834-waf-modsecurity-work`.

Verifying the OWASP CRS rollout on dev **without sending a single attack
payload** — so the test proves the detection pipeline works and doesn't
provoke an incident response.

| | |
|---|---|
| **Target** | `beta-dev.usa.gov` (WAF route `waf-route-dev-usagov.app.cloud.gov`) |
| **Mode** | detection-only (CRS scores and logs, never blocks) |
| **Script** | `scripts/waf-verify-dev.sh` |

---

## Why this is safe to run

The probe that exercises the WAF omits its `User-Agent` header. That trips CRS
rule **920330 "Empty User Agent Header"** — a real detection that travels the
identical path an attack would: rule match → anomaly score → nginx error
log → New Relic.

No attack signature is ever put on the wire. A missing header appears in no
threat feed and matches no IDS rule, so it will not page anyone. Rule 920330
scores **2**, below the inbound threshold of **5**, so it never trips 949110 —
meaning the canary stays safe to run even after this environment moves to
blocking.

Attack-payload testing already happened locally, in a container that never
touched the network. SQLi, XSS, path traversal and RCE detection were all
confirmed there. Nothing is gained by repeating it against a `.gov` host.

---

## 00. Before you start

### Send the heads-up first, even though this is benign

It costs nothing and is the difference between a non-event and someone opening
an investigation. Adapt and send before running anything:

```text
Subject: Planned WAF verification on dev - USAGOV-2834

We are deploying an OWASP CRS ruleset change to the USAGov *dev*
environment and will run a short verification against beta-dev.usa.gov.

  When:        <date/time window>
  Source IP:   <your egress IP>
  Volume:      ~10 requests, one pass
  Marker:      every request carries the header
               X-USAGov-WAF-Test: USAGOV-2834
  Payloads:    none. No attack strings are sent. The only non-standard
               request omits the User-Agent header, which is what the
               WAF is expected to notice.
  Contact:     <name / email / phone>

Ticket: USAGOV-2834   PR: #2838
```

### 00.1 — Confirm the new ruleset is actually live

- [ ] Done

The single most important precheck. If the rule count hasn't changed, nothing
else in this plan is meaningful.

```sh
cf target -s dev
cf logs $ROUTE_SERVICE_APP_NAME --recent | grep "rules loaded"
```

**Expect:** `ModSecurity-nginx v1.0.3 (rules loaded inline/local/remote: 0/807/0)`

If it still reads `0/7/0`, the old image is running. **Stop here.**

### 00.2 — Set your contact details in the script

- [ ] Done

The script ships with a placeholder. Whoever reads these logs later should be
able to tell instantly what the traffic was and who to ask.

```sh
# edit scripts/waf-verify-dev.sh
TICKET="USAGOV-2834"
CONTACT="you@gsa.gov"   # <- change this
```

### 00.3 — Record your source IP and confirm allowlisting

- [ ] Done

Dev sits behind an IP allowlist (`IPS_ALLOWED_WWW` then `deny all`). If you
aren't on it you'll get 403s from the allowlist, not from the WAF, and every
result below will be misleading.

```sh
curl -s https://checkip.amazonaws.com
```

> **Note:** ModSecurity runs on the port-80 server block, *before* the allowlist
> on port 8881. A non-allowlisted request is still WAF-evaluated — which is why
> a 403 here is ambiguous and worth ruling out up front.

---

## 01. Run the checks

One pass of the script covers all four. They're listed individually so you can
run them by hand and so a failure points at something specific.

```sh
./scripts/waf-verify-dev.sh beta-dev.usa.gov
```

### 01 — Normal traffic still serves correctly

- [ ] Pass

The regression that actually matters. A WAF change that breaks real users is a
far bigger risk than anything this rollout is defending against — check it first.

```sh
for p in / /es /benefit-finder; do
  curl -s -o /dev/null -w "$p -> %{http_code}\n" \
    -H "X-USAGov-WAF-Test: USAGOV-2834" \
    -H "User-Agent: USAGov-WAF-Verification/1.0" \
    "https://beta-dev.usa.gov$p"
done
```

**Expect:** `200`, `301`, `302` or `304` for all three. Any `403` means CRS is
blocking rather than detecting — that is a stop condition.

### 02 — The benign canary: detected, not blocked

- [ ] Pass

The one request that deliberately trips a CRS rule. `-H 'User-Agent;'` is
curl's syntax for sending the header *empty* rather than omitting it.

```sh
curl -s -o /dev/null -w "canary -> %{http_code}\n" \
  -H "X-USAGov-WAF-Test: USAGOV-2834" \
  -H "User-Agent;" \
  https://beta-dev.usa.gov/
```

**Expect:** a **normal** response code — `200`/`301`/`302`. In detection-only
the request is logged but not blocked. A `403` means CRS is already blocking.

### 03 — Malformed body still rejected (unchanged behaviour)

- [ ] Pass

Rule 200002 predates this work and must behave exactly as it does in production
today. This is the check that proves the rollout changed *what is detected*
without changing *what is rejected*.

```sh
curl -s -o /dev/null -w "malformed body -> %{http_code}\n" \
  -X POST -H "X-USAGov-WAF-Test: USAGOV-2834" \
  -H "Content-Type: text/xml" --data "not-valid-xml" \
  https://beta-dev.usa.gov/
```

**Expect:** exactly `400`. Anything else — especially `200` or `502` — means the
engine mode is wrong and 200002 has stopped blocking. **Stop condition.**

### 04 — The retired test rule is gone

- [ ] Pass

A live `testparam` rule was returning 403 for any request carrying
`?testparam=test`. It was removed in this PR.

```sh
curl -s -o /dev/null -w "testparam -> %{http_code}\n" \
  -H "X-USAGov-WAF-Test: USAGOV-2834" \
  -H "User-Agent: USAGov-WAF-Verification/1.0" \
  "https://beta-dev.usa.gov/?testparam=test"
```

**Expect:** a normal response code, **not** `403`.

---

## 02. Verify the logging pipeline by hand

The script can only see HTTP status codes. The whole point of the rollout is
what lands in the log — that part has to be checked in New Relic.

> ### How detections reach New Relic
>
> Detections arrive as ordinary ModSecurity **nginx error-log** lines, the same
> bracketed format the logshipper's `parse_modsecurity_keys.lua` already parses
> into `Modsecurity.id` / `Modsecurity.msg` / `Modsecurity.level`. No new log
> shape is involved and no parser change is needed.
>
> This works only because `nginx.conf.tmpl` sets `error_log /dev/stderr info`.
> ModSecurity-nginx emits matched rule messages at `NGX_LOG_INFO` and logs
> blocks at `NGX_LOG_ERR`, so at `warn` every CRS detection is silently
> discarded. If step 2.1 finds nothing, check the log level first.
>
> **`Modsecurity.level` now carries meaning:** detections are `info`, blocks are
> `error`. Any existing dashboard or alert filtering on `level = error` will
> exclude every CRS detection — check those before relying on them.

### 2.1 — The canary produced exactly one detection line

- [ ] Pass

```sh
# raw stream - authoritative
cf logs $ROUTE_SERVICE_APP_NAME --recent | grep '\[id "920330"\]'
```

```sql
-- New Relic (NRQL)
SELECT * FROM Log WHERE `Modsecurity.id` = '920330' SINCE 15 minutes ago
```

**Expect:** one line reading
`ModSecurity: Warning. Matched … [id "920330"] [msg "Empty User Agent Header"] …`,
at `[info]` level, parsing into `Modsecurity.id = 920330` and
`Modsecurity.msg = "Empty User Agent Header"`.

### 2.2 — Normal traffic produced no detection lines

- [ ] Pass

A request that matches no rule emits no ModSecurity line at all. That is what
makes the canary attributable — if normal traffic logged too, you could not tell
them apart.

**Expect:** zero `ModSecurity:` lines for the step 01 requests. Note that nginx
itself now emits some unrelated `[info]` chatter; only `ModSecurity:` lines
matter here.

### 2.3 — Measure error-log volume

- [ ] Pass

The genuinely open question, and the main reason to run this on dev before prod.
ModSecurity logs **one line per matched rule**, not one per request — a single
SQLi probe produced roughly six lines in local testing. Raising the level from
`warn` to `info` also lets through nginx's own info-level messages.

**Judge:** compare error-log lines/min for a few hours before and after the
deploy. If the volume is unacceptable, the lever is CRS tuning — adding
exclusions so fewer rules match — not lowering the log level, which would make
detection-only silent again. Record the numbers on the PR.

### 2.4 — False-positive sweep (the real work)

- [ ] Pass

Everything above proves the plumbing. This step is what the detection-only stage
*exists for*: finding the legitimate traffic CRS mistakes for an attack, before
it can block anyone. Do it in a browser, as a real user would.

- **CMS login** and an authenticated content edit — rich-text bodies are the
  classic CRS false-positive source
- **Benefit-finder**, submitting the full questionnaire
- **Site search**, including a query with an apostrophe (`who's eligible`) and
  one with angle brackets
- A page with query parameters — UTM tags, pagination

Then query for any `9xxxxx` rule ID against those paths.

**Judge:** every CRS rule that fires on legitimate traffic is an exclusion to add
to `REQUEST-900-EXCLUSION-RULES-BEFORE-CRS.conf` **before** anything is switched
to blocking. Record them on the PR rather than fixing them ad hoc.

---

## 03. Stop conditions

Any of these means stop and roll back rather than push forward.

| Symptom | What it means | Action |
|---|---|---|
| Normal pages return `403` | CRS is blocking, not detecting. The `SecRuleUpdateActionById` lines aren't taking effect. | **Roll back** |
| Malformed body no longer returns `400` | Engine mode is wrong; rule 200002 has stopped blocking. Production behaviour has changed. | **Roll back** |
| Startup line still reads `0/7/0` | Old image. Nothing was actually tested. | Redeploy |
| No canary record after 5 min | Most likely `error_log` is back at `warn`, which discards the INFO-level detections. Check the raw `cf logs` stream before blaming the WAF. | Investigate |
| Error-log volume rises sharply | One line per matched rule; tune CRS exclusions rather than lowering the log level. | Tune, don't roll back |
| CMS or benefit-finder flags CRS rules | Expected — this is the finding, not a failure. Record and build exclusions. | Continue |

### Rollback

Redeploy the previously running WAF image tag. The change is confined to the WAF
container — no database, no CMS, no content implications, and nothing to unwind
beyond the deploy itself.

---

## 04. Record the run

Paste the completed table into PR #2838 so the reviewer can see dev evidence
rather than take it on trust.

| # | Check | Expected | Actual | Result |
|---|---|---|---|---|
| 00.1 | Ruleset live | `0/807/0` | | |
| 01 | Normal traffic serves | 200/301/302 | | |
| 02 | Canary not blocked | 200/301/302 | | |
| 03 | Malformed body rejected | 400 | | |
| 04 | testparam retired | not 403 | | |
| 2.1 | Canary logged | 1 record, 920330 | | |
| 2.2 | Normal traffic silent | 0 records | | |
| 2.3 | Error-log volume acceptable | judgement | | |
| 2.4 | False positives found | list them | | |

Run by ______________________  Date ______________  Source IP ________________

---

## 05. Rule reference

| ID | Name | Role in this plan |
|---|---|---|
| `920330` | Empty User Agent Header | The canary. Score 2, below the threshold of 5 — detected, never blocked, safe post-go-live. |
| `200002` | Failed to parse request body | Pre-existing. Returns 400. Must be **unchanged** — the control in this experiment. |
| `200003` | Multipart strict validation | Pre-existing. The bulk of the July log noise; benign LF line endings. |
| `949110` | Inbound Anomaly Score Exceeded | Forced to `pass`. Still logs, never blocks. Commenting this out is the switch to blocking. |
| `959100` | Outbound Anomaly Score Exceeded | Same, on the response side. |
| `942100` | SQL Injection via libinjection | Verified locally only. **Do not send SQLi at dev.** |

### What not to do

- **Don't send attack payloads at any deployed environment.** It's already
  covered locally, and it is what turns a routine test into an incident.
- **Don't run a scanner** — ZAP, Nikto, sqlmap — against dev for this. Volume and
  signature are exactly what triggers escalation.
- **Don't test from a shared or unattributable IP.** One known source, recorded
  in advance.
- **Don't add exclusions ad hoc** while testing. Record findings, change config
  through the PR.

---

Rule counts, scores and status codes here were verified in a locally built WAF
container from branch `USAGOV-2834-waf-modsecurity-work`. The New Relic query
shapes and the `cf` app name are starting points to confirm against your
environment.
