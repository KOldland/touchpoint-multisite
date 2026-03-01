#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

COMPOSER_CMD=""
if command -v composer >/dev/null 2>&1; then
  COMPOSER_CMD="composer"
elif [ -f "$SCRIPT_DIR/composer.phar" ]; then
  COMPOSER_CMD="php composer.phar"
else
  echo "Composer is required but was not found."
  echo "Install Composer globally, or download local composer.phar into wp-content/plugins/khm-plugin."
  exit 1
fi

$COMPOSER_CMD install \
  --no-dev \
  --prefer-dist \
  --no-interaction \
  --optimize-autoloader

echo "KHM plugin dependencies installed."
