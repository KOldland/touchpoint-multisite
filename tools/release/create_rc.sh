#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
DATE_TAG="${1:-$(date +%Y%m%d-%H%M%S)}"
OUT_DIR="${ROOT_DIR}/artifacts/release-rc/${DATE_TAG}"
ARTIFACT="release-rc-${DATE_TAG}.zip"

mkdir -p "$OUT_DIR"
cd "$ROOT_DIR"

echo "[release-rc] preparing RC ${DATE_TAG}"
git rev-parse --short HEAD | tee "$OUT_DIR/commit.txt"
php scripts/verify_golden_fixtures.php | tee "$OUT_DIR/golden-check.log"

zip -rq "$OUT_DIR/$ARTIFACT" \
  app/public/wp-content/plugins/kh-smma \
  app/public/wp-content/plugins/khm-plugin \
  docs \
  runbooks \
  migrations \
  .github/workflows \
  composer.lock 2>/dev/null || zip -rq "$OUT_DIR/$ARTIFACT" \
  app/public/wp-content/plugins/kh-smma \
  app/public/wp-content/plugins/khm-plugin \
  docs \
  runbooks \
  migrations \
  .github/workflows

shasum -a 256 "$OUT_DIR/$ARTIFACT" | tee "$OUT_DIR/${ARTIFACT}.sha256"

if [[ -n "${RELEASE_GPG_KEY_ID:-}" ]]; then
  GPG_ARGS=(--batch --yes --armor --detach-sign --local-user "$RELEASE_GPG_KEY_ID" -o "$OUT_DIR/${ARTIFACT}.asc" "$OUT_DIR/$ARTIFACT")
  if [[ -n "${RELEASE_GPG_PASSPHRASE:-}" ]]; then
    GPG_ARGS=(--pinentry-mode loopback --passphrase "$RELEASE_GPG_PASSPHRASE" "${GPG_ARGS[@]}")
  fi
  gpg "${GPG_ARGS[@]}"
  echo "[release-rc] gpg signature created"
else
  echo "[release-rc] RELEASE_GPG_KEY_ID not set; signature skipped" | tee "$OUT_DIR/signature-warning.log"
fi
