#!/usr/bin/env bash
set -euo pipefail

# CONFIG - replace these values for your environment
SITE_URL="https://your-site.example"          # base URL (no trailing slash)
USERNAME="admin"                              # WP username with publish/edit_posts
APP_PASSWORD="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"  # Application password for USERNAME
API_ENDPOINT="${SITE_URL}/wp-json/khm-geo/v1/suggest-answercards"
TEST_TITLE="Smoke test: GEO Suggest"
TEST_CONTENT="Spare-parts lead time depends on supplier location, stock levels, and order batching. To reduce lead time, consolidate suppliers locally where possible and increase minimum order quantities for high-turn spares. Implement a Kanban system for on-site replenishment and monitor supplier OTIF (on-time-in-full)."
MAX_CARDS=3

# Helper for auth
AUTH="${USERNAME}:${APP_PASSWORD}"

echo "=== GEO Suggest: basic call ==="
HTTP_RESPONSE=$(curl -s -w "\n%{http_code}" -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"${TEST_TITLE}\",\"content\":\"${TEST_CONTENT}\",\"max_cards\":${MAX_CARDS}}" \
  "${API_ENDPOINT}")

# split body and code
HTTP_BODY=$(echo "$HTTP_RESPONSE" | sed '$d')
HTTP_CODE=$(echo "$HTTP_RESPONSE" | tail -n1)

echo "HTTP status: $HTTP_CODE"
echo "Response snippet:"
echo "$HTTP_BODY" | jq '.cards | length, .cards[0].question' || echo "$HTTP_BODY"

# Check cache header (first call should be MISS - cannot easily fetch header from -s)
echo
echo "=== Cache hit test (repeat same request) ==="
HTTP_RESPONSE2=$(curl -s -w "\n%{http_code}" -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"${TEST_TITLE}\",\"content\":\"${TEST_CONTENT}\",\"max_cards\":${MAX_CARDS}}" \
  "${API_ENDPOINT}" -D -)
# Print X-KHM-GEO-Cache header
echo "$HTTP_RESPONSE2" | grep -i "X-KHM-GEO-Cache" || echo "No X-KHM-GEO-Cache header found (inspect logs)."

echo
echo "=== Rate limit test (3 quick calls) ==="
for i in 1 2 3 4; do
  echo "Call #$i"
  HTTP_RESPONSE_LOOP=$(curl -s -w "\n%{http_code}" -u "$AUTH" -H "Content-Type: application/json" \
    -d "{\"title\":\"${TEST_TITLE}\",\"content\":\"${TEST_CONTENT}\",\"max_cards\":${MAX_CARDS}}" \
    "${API_ENDPOINT}")
  HTTP_BODY_LOOP=$(echo "$HTTP_RESPONSE_LOOP" | sed '$d')
  HTTP_CODE_LOOP=$(echo "$HTTP_RESPONSE_LOOP" | tail -n1)
  echo "Status $HTTP_CODE_LOOP"
  if [ "$HTTP_CODE_LOOP" != "200" ]; then
    echo "Non-200 response: $HTTP_BODY_LOOP"
  else
    echo "OK"
  fi
done

echo
echo "=== Test: missing API key handling (manual step) ==="
echo "To test: temporarily remove the API key (or set get_api_key() to return null) and call the endpoint — it should return an error indicating no API key."

echo
echo "Done."