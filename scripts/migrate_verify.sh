#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VERIFY_SQL="${ROOT_DIR}/migrations/verify.sql"
MODE="execute"
DB_HOST="${DB_HOST:-}"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"
OUT_DIR="${ROOT_DIR}/artifacts/migrations"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) MODE="dry-run" ;;
    --host) DB_HOST="$2"; shift ;;
    --db) DB_NAME="$2"; shift ;;
    --user) DB_USER="$2"; shift ;;
    --pass) DB_PASS="$2"; shift ;;
    --out) OUT_DIR="$2"; shift ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
  esac
  shift
done

mkdir -p "$OUT_DIR"

echo "[migrate-verify] mode=${MODE}" | tee "$OUT_DIR/staging_dryrun.log"
echo "[migrate-verify] verify_sql=${VERIFY_SQL}" | tee -a "$OUT_DIR/staging_dryrun.log"

echo "[migrate-verify] migration files:" | tee -a "$OUT_DIR/staging_dryrun.log"
find "${ROOT_DIR}/migrations" -maxdepth 1 -type f | sort | tee -a "$OUT_DIR/staging_dryrun.log"

if [[ "$MODE" == "dry-run" ]]; then
  echo "[migrate-verify] dry-run complete; no database changes applied" | tee -a "$OUT_DIR/staging_dryrun.log"
  exit 0
fi

if [[ -z "$DB_HOST" || -z "$DB_NAME" || -z "$DB_USER" ]]; then
  echo "[migrate-verify] DB_HOST, DB_NAME and DB_USER must be set for execute mode" >&2
  exit 2
fi

MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < "$VERIFY_SQL" > "$OUT_DIR/verify_output.txt"
echo "[migrate-verify] verify.sql executed successfully" | tee -a "$OUT_DIR/staging_dryrun.log"
