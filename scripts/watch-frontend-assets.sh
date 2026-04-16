#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PIDS=()

cleanup() {
  for pid in "${PIDS[@]:-}"; do
    kill "$pid" 2>/dev/null || true
  done

  for pid in "${PIDS[@]:-}"; do
    wait "$pid" 2>/dev/null || true
  done
}

trap cleanup EXIT INT TERM

npm run watch --prefix "$ROOT_DIR/web/themes/custom/usagov" &
PIDS+=($!)

"$ROOT_DIR/scripts/benefit-finder/watch-module-assets.sh" &
PIDS+=($!)

wait -n "${PIDS[@]}"
