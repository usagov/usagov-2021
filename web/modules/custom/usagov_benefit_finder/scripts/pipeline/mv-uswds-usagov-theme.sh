#!/bin/bash
# Copies usagov USWDS theme assets from web/themes/custom/usagov/ into
# app-source/themes/ for local Storybook development.
#
# Must be invoked from the app-source/ directory (the npm mv:uswds:usagov
# script handles this automatically).

set -e

SRC="../../../../themes/custom/usagov"
DEST="./themes/custom/usagov"

if [ ! -d "$SRC" ]; then
  echo "Error: source theme not found at $SRC" >&2
  exit 1
fi

mkdir -p "$DEST"
cp -r "$SRC/." "$DEST/"

echo "✓ usagov theme assets copied to $DEST"
