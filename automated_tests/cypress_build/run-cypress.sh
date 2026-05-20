#!/bin/sh

set -eu

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <open|run> [cypress args...]" >&2
  exit 1
fi

command="$1"
shift

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_dir="${CYPRESS_PROJECT_DIR:-../e2e-cypress}"
browser="${CYPRESS_BROWSER:-chromium}"
cypress_bin="$script_dir/node_modules/.bin/cypress"

if [ ! -x "$cypress_bin" ]; then
  echo "Cypress binary not found at $cypress_bin. Run npm ci in $script_dir first." >&2
  exit 1
fi

cd "$project_dir"

case "$command" in
  open)
    exec "$cypress_bin" open --project . --browser "$browser" "$@"
    ;;
  run)
    exec "$cypress_bin" run --project . --browser "$browser" "$@"
    ;;
  *)
    echo "Unknown Cypress command: $command" >&2
    exit 1
    ;;
esac
