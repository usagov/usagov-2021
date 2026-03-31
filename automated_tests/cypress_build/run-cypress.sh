#!/bin/sh

set -eu

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <open|run> [cypress args...]" >&2
  exit 1
fi

command="$1"
shift

project_dir="${CYPRESS_PROJECT_DIR:-../e2e-cypress}"
browser="${CYPRESS_BROWSER:-chromium}"

cd "$project_dir"

case "$command" in
  open)
    exec cypress open --project . --browser "$browser" "$@"
    ;;
  run)
    exec cypress run --project . --browser "$browser" "$@"
    ;;
  *)
    echo "Unknown Cypress command: $command" >&2
    exit 1
    ;;
esac
