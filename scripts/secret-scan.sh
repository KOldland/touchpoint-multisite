#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
EXCLUDES=(
  --glob=!.git
  --glob=!vendor
  --glob=!node_modules
  --glob=!.idea
  --glob=!.vscode
)
PATTERNS=(
  'sk_(live|test)_[A-Za-z0-9]{16,}'
  'whsec_[A-Za-z0-9]{16,}'
  '-----BEGIN (RSA|EC|OPENSSH|PRIVATE) KEY-----'
  'AKIA[0-9A-Z]{16}'
  'ghp_[A-Za-z0-9]{30,}'
  'Bearer[[:space:]]+[A-Za-z0-9._~+/-]{20,}'
)

echo "[secret-scan] scanning repository for high-risk secret patterns"
failures=0
for pattern in "${PATTERNS[@]}"; do
  if rg --pcre2 --line-number --hidden "${EXCLUDES[@]}" "${pattern}" "$ROOT_DIR" >/tmp/phase4_secret_scan_hits.txt 2>/dev/null; then
    echo "[secret-scan] pattern matched: ${pattern}"
    cat /tmp/phase4_secret_scan_hits.txt
    failures=1
  fi
done

if [[ ${failures} -ne 0 ]]; then
  echo "[secret-scan] FAIL"
  exit 1
fi

echo "[secret-scan] PASS"
