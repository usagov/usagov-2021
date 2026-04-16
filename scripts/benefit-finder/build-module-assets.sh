#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SOURCE_DIR="$ROOT_DIR/sources/benefit-finder"

if [ ! -d "$SOURCE_DIR" ]; then
  echo "Benefit Finder source directory not found: $SOURCE_DIR" >&2
  exit 1
fi

echo "Installing Benefit Finder dependencies..."
npm install --prefix "$SOURCE_DIR"

npm run build --prefix "$SOURCE_DIR"
