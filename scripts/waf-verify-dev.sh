#!/usr/bin/env bash
#
# WAF / OWASP CRS verification against a deployed environment (USAGOV-2834).
#
# DELIBERATELY SENDS NO ATTACK PAYLOADS.
#
# Everything here is either an ordinary request or a merely non-conforming one.
# The canary probe omits the User-Agent header, which trips CRS rule 920330
# ("Empty User Agent Header"). That exercises the identical code path an attack
# would -- rule match -> anomaly score -> audit log -> stdout -> New Relic --
# without putting a single attack signature on the wire, so it will not show up
# in anyone's threat feed and will not page the network security team.
#
# Attack-payload testing belongs in the local container instead, where it never
# touches the network at all:
#   docker build -f .docker/Dockerfile-waf .docker -t usagov-waf:test
#   docker run -d --name waf-test usagov-waf:test
#
# Rule 920330 scores 2 (NOTICE), below the inbound threshold of 5, so it does
# not trip 949110 and will NOT be blocked even after this environment is moved
# from detection-only to blocking. The canary stays safe to run post-go-live.
#
# Usage:  ./scripts/waf-verify-dev.sh [host]
#         ./scripts/waf-verify-dev.sh beta-dev.usa.gov

set -uo pipefail

HOST="${1:-beta-dev.usa.gov}"
BASE="https://${HOST}"

# Attribution marker. Carried on EVERY request, including the one with no
# User-Agent, so anyone reading the logs can immediately tell what this was
# and who to ask. Change the contact before running.
TICKET="USAGOV-2834"
CONTACT="webops@gsa.gov"
MARK="X-USAGov-WAF-Test: ${TICKET} verification, contact ${CONTACT}"
UA="User-Agent: USAGov-WAF-Verification/1.0 (+${TICKET}; ${CONTACT})"

pass=0; fail=0
say() { printf '%s\n' "$*"; }
chk() { # chk <label> <expected-regex> <actual>
  if [[ "$3" =~ $2 ]]; then say "  PASS  $1 (got $3)"; pass=$((pass+1))
  else say "  FAIL  $1 (expected $2, got $3)"; fail=$((fail+1)); fi
}

say "WAF verification against ${BASE}"
say "Ticket ${TICKET} | contact ${CONTACT}"
say "No attack payloads are sent by this script."
say ""

# ---------------------------------------------------------------------------
say "1. Normal traffic still serves correctly (regression -- the thing that"
say "   actually matters; a WAF change breaking real users is the real risk)"
for path in "/" "/es" "/benefit-finder"; do
  codeout=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 \
       -H "$UA" -H "$MARK" -H 'Accept: text/html' "${BASE}${path}")
  chk "GET ${path}" '^(200|301|302|304)$' "$codeout"
done
say ""

# ---------------------------------------------------------------------------
say "2. Benign canary -- omits User-Agent, trips CRS 920330."
say "   Expect a NORMAL response code: in detection-only the request is"
say "   logged but NOT blocked. A 403 here means CRS is already blocking."
codeout=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 \
     -H 'User-Agent;' -H "$MARK" -H 'Accept: text/html' "${BASE}/")
chk "canary not blocked" '^(200|301|302|304)$' "$codeout"
say ""
say "   -> Now confirm in New Relic that this produced an audit record:"
say "      look for ruleId 920330 / \"Empty User Agent Header\" in the last"
say "      few minutes from the WAF app for this space."
say "      If normal traffic above logged NOTHING and this logged ONE record,"
say "      the detection pipeline is working end to end."
say ""

# ---------------------------------------------------------------------------
say "3. Malformed request body still rejected (pre-existing rule 200002,"
say "   must be UNCHANGED by this work -- expect 400)"
codeout=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 \
     -X POST -H "$UA" -H "$MARK" -H 'Content-Type: text/xml' \
     --data 'not-valid-xml' "${BASE}/")
chk "malformed XML body rejected" '^400$' "$codeout"
say ""

# ---------------------------------------------------------------------------
say "4. Retired test rule is gone (used to return 403 for ?testparam=test)"
codeout=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 \
     -H "$UA" -H "$MARK" -H 'Accept: text/html' "${BASE}/?testparam=test")
chk "testparam no longer 403" '^(200|301|302|304)$' "$codeout"
say ""

say "-----------------------------------------------------------------"
say "passed: ${pass}   failed: ${fail}"
say ""
say "Still to check by hand in New Relic / cf logs:"
say "  a. Audit-log VOLUME. Compare records/min before and after deploy."
say "     Normal 200 traffic should add none. Confirm 404s are not flooding"
say "     the audit log -- if they are, uncomment SecAuditLogRelevantStatus"
say "     in modsecurity-override.conf to exclude them."
say "  b. FALSE POSITIVES. Exercise CMS login, benefit-finder submission and"
say "     site search, then look for any 9xxxxx ruleId against those paths."
say "     Anything that appears is an exclusion to add BEFORE blocking."
say "  c. Startup line should read 'rules loaded inline/local/remote: 0/807/0'."
[ "$fail" -eq 0 ]
