#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${1:-$(cd "$(dirname "$0")/../.." && pwd)}"
MANIFEST_REL="artifacts/migration_2026-04-12/quoteclub_manifest.sha1"
MANIFEST_PATH="$ROOT_DIR/$MANIFEST_REL"

if [[ ! -f "$MANIFEST_PATH" ]]; then
  echo "manifest_not_found=$MANIFEST_PATH"
  exit 1
fi

cd "$ROOT_DIR"
echo "verifying_manifest=$MANIFEST_REL"
# Filter comments/blank lines because shasum -c expects only checksum lines.
grep -E '^[0-9a-f]{40}[[:space:]]+' "$MANIFEST_PATH" | shasum -c -

echo "manifest_status=ok"
