#!/usr/bin/env bash
set -euo pipefail

HOST="${HOST:-}"
NONCE="${NONCE:-}"
PGHOST="${PGHOST:-}"
PGUSER="${PGUSER:-}"
PGDATABASE="${PGDATABASE:-}"
PGPORT="${PGPORT:-5432}"
TEST_USER_ID="${TEST_USER_ID:-}"

if ! command -v jq >/dev/null 2>&1; then
  echo "jq is required but not installed."
  exit 1
fi

if [[ -z "$HOST" || -z "$NONCE" ]]; then
  echo "Missing HOST or NONCE. Example: HOST=https://staging.example.com NONCE=... $0"
  exit 1
fi

if [[ -z "$PGHOST" || -z "$PGUSER" || -z "$PGDATABASE" ]]; then
  echo "Missing PGHOST/PGUSER/PGDATABASE for SQL checks."
  exit 1
fi

REST_PAYLOAD='{"event_id":"article_read_75_plus_marketing","metadata":{"url":"'"$HOST"'/sample-article","percent":80}}'

echo "[1/4] REST smoke"
RESP=$(curl -s -X POST "$HOST/wp-json/kh-smma/v1/record-event" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $NONCE" \
  -d "$REST_PAYLOAD" | jq -e '.success == true' >/dev/null && echo ok || echo fail)

if [[ "$RESP" != "ok" ]]; then
  echo "REST smoke failed"
  exit 1
fi

echo "[2/4] DB checks"
PSQL_BASE=(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$PGDATABASE" -At)

EVENT_ROW=$(${PSQL_BASE[@]} -c "SELECT event_id FROM event_catalog WHERE event_id='pricing_page_view' LIMIT 1;")
if [[ "$EVENT_ROW" != "pricing_page_view" ]]; then
  echo "event_catalog missing pricing_page_view"
  exit 1
fi

INGEST_ROW=$(${PSQL_BASE[@]} -c "SELECT event_type FROM user_event WHERE event_type='article_read_75_plus_marketing' ORDER BY created_at DESC LIMIT 1;")
if [[ "$INGEST_ROW" != "article_read_75_plus_marketing" ]]; then
  echo "user_event missing recent article_read_75_plus_marketing"
  exit 1
fi

if [[ -n "$TEST_USER_ID" ]]; then
  SCORE_ROW=$(${PSQL_BASE[@]} -c "SELECT user_id FROM user_phase_score WHERE user_id = $TEST_USER_ID LIMIT 1;")
  if [[ "$SCORE_ROW" != "$TEST_USER_ID" ]]; then
    echo "user_phase_score missing row for user_id=$TEST_USER_ID"
    exit 1
  fi
fi

echo "[3/4] Front-end checks (manual)"
cat <<'EOF'
- Article: scroll 25/50/75/100% and confirm /kh-smma/v1/record-event calls.
- Pricing: open pricing page, click CTA, confirm pricing_page_view and pricing_cta_click.
- Rep-sent: open link with ?kh_rep_sent=1 and confirm rep-sent tracking.
EOF

echo "[4/4] Aggregator dry-run (manual)"
cat <<'EOF'
Run synthetic inserts + aggregator dry-run and verify user_phase_score assigned_phase.
See docs/phase_engine_handover.md for SQL.
EOF

echo "OK"
