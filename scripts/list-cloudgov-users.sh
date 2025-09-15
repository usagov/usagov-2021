#!/usr/bin/env bash
set -euo pipefail

ORG="gsa-tts-usagov"
OUT="usagov_user_report.csv"

# org GUID and pull spaces
ORG_GUID=$(cf org "$ORG" --guid)
cf curl "/v3/spaces?organization_guids=${ORG_GUID}&per_page=5000" > /tmp/spaces.json

# all roles in the org
cf curl "/v3/roles?organization_guids=${ORG_GUID}&per_page=5000" > /tmp/roles.json

# list of user GUIDs from roles
jq -r '.resources[].relationships.user.data.guid' /tmp/roles.json | sort -u > /tmp/user_guids.txt

# user objects for the GUIDs
echo "[" > /tmp/users.json
first=1
while read -r UG; do
  RESP=$(cf curl "/v3/users/${UG}")
  if [ $first -eq 1 ]; then first=0; echo "$RESP" >> /tmp/users.json
  else echo ",$RESP" >> /tmp/users.json
  fi
done < /tmp/user_guids.txt
echo "]" >> /tmp/users.json

# 5) For each user - find the most recent audit for "last_used" - I dont think this works though
> /tmp/user_last_event.tsv
while read -r UG; do
  LAST=$(cf curl "/v3/audit_events?actor_guids=${UG}&order_by=-created_at&per_page=1" | jq -r '.resources[0].created_at // ""')
  printf "%s\t%s\n" "$UG" "$LAST" >> /tmp/user_last_event.tsv
done < /tmp/user_guids.txt

# compile CSV rows
#    username, space, role, date_created, last_used, is_service_account, created_by
> /tmp/created_by.tsv
while read -r UG; do
  # Try to find a user.create audit event targeting this user and grab the actor name/guid
  EV=$(cf curl "/v3/audit_events?order_by=-created_at&per_page=50&target_guids=${UG}")
  CREATOR_GUID=$(echo "$EV" | jq -r '.resources[] | select(.type|startswith("audit.user.create")) | .actor.guid' | head -n1)
  CREATOR_NAME=$(echo "$EV" | jq -r '.resources[] | select(.type|startswith("audit.user.create")) | .actor.name' | head -n1)
  if [ -n "${CREATOR_GUID:-}" ]; then
    printf "%s\t%s (%s)\n" "$UG" "$CREATOR_NAME" "$CREATOR_GUID" >> /tmp/created_by.tsv
  else
    printf "%s\t\n" "$UG" >> /tmp/created_by.tsv
  fi
done < /tmp/user_guids.txt

# Join everything into final CSV
jq -r \
  --slurpfile spaces /tmp/spaces.json \
  --slurpfile users  /tmp/users.json \
  --arg lastmap "$(awk -F'\t' '{print $1":"$2}' /tmp/user_last_event.tsv | paste -sd',' -)" \
  --arg createdmap "$(awk -F'\t' '{print $1":"$2}' /tmp/created_by.tsv | paste -sd',' -)" '
  # Helpers to map GUIDs -> names/values
  def space_name(g): ($spaces[0].resources[]? | select(.guid==g) | .name) // "";
  def user_obj(ug): ($users[0][]? | select(.guid==ug)) // {};
  def username(ug): (user_obj(ug).username // ug);
  def created_at(ug): (user_obj(ug).created_at // "");

  # Treat as a FILTER: takes piped string input and returns true/false
  # Add a UUID-ish check to better flag service accounts
  def is_service:
    ( . | test("service-account")
        or test("^space-(auditor|deployer)")
        or test("^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$") );

  # Also a FILTER: takes piped "guid:value,guid:value" and returns an object map
  def kvmap:
    if (.|length) == 0 then {} else
      reduce ( (split(",")[]) ) as $e ({}; 
        ($e|split(":")) as $p | . + { ($p[0]) : ($p[1] // "") })
    end;

  # Bind maps from the provided args via filters
  ($lastmap    | kvmap) as $last |
  ($createdmap | kvmap) as $created_by |

  # Header
  ["username","space","role","date_created","last_used","service_account","created_by"],

  # Emit a row per role (stdin is /tmp/roles.json)
  ( .resources[] |
    .relationships.user.data.guid  as $ug |
    (.relationships.space.data.guid // null) as $sg |
    [
      username($ug),
      (if $sg then space_name($sg) else "" end),
      .type,
      created_at($ug),
      ($last[$ug] // ""),
      (username($ug) | is_service),
      ($created_by[$ug] // "")
    ]
  )
  | @csv
' /tmp/roles.json > "$OUT"


echo "Wrote $OUT"
