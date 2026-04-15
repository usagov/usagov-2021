#!/bin/bash
# Copies the Drupal theme assets used by local Benefit Finder development into
# the source workspace for Vite and Storybook.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SOURCE_DIR="$ROOT_DIR/sources/benefit-finder"
SRC="$ROOT_DIR/web/themes/custom/usagov"
DEST="$SOURCE_DIR/themes/custom/usagov"

if [ ! -d "$SOURCE_DIR" ]; then
  echo "Benefit Finder source directory not found: $SOURCE_DIR" >&2
  exit 1
fi

if [ ! -d "$SRC" ]; then
  echo "USAGov theme not found at $SRC" >&2
  exit 1
fi

mkdir -p "$DEST"
cp -r "$SRC/." "$DEST/"

echo "Synced USAGov theme assets to $DEST"
