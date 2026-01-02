#!/usr/bin/env bash
set -euo pipefail
PLUGIN_DIR=$(cd "$(dirname "$0")/.." && pwd)
if ! command -v wp >/dev/null 2>&1; then
  echo "wp cli not found. Install WP-CLI before running make-pot." >&2
  exit 1
fi
wp i18n make-pot "$PLUGIN_DIR" "$PLUGIN_DIR/languages/kh-bounce.pot" --exclude=node_modules,vendor,tests
