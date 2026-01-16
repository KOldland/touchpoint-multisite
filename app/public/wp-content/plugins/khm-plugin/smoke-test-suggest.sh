# (edit top 3 values)
# Save: Ctrl-O then Enter
# Exit: Ctrl-X
#!/usr/bin/env bash
set -euo pipefail

# === CONFIG - EDIT THESE BEFORE RUNNING ===
SITE_URL="http://kh-staging.test"           # <-- replace with your site URL (no trailing slash)
USERNAME="admin"                                  # <-- WP admin username
APP_PASSWORD="gCGa XVjq 9Cmd bYfb L3UF b1vZ"                 # <-- Application password for that user
# ==========================================

API_ENDPOINT="${SITE_URL}/wp-json/khm-geo/v1/suggest-answercards"
TEST_TITLE="Smoke test: GEO Suggest"
TEST_CONTENT="Spare-parts lead time depends on supplier location, stock levels, and order batching. To reduce lead time, consolidate suppliers locally where possible and increase minimum order quantities for high-turn spares. Implement a Kanban system for on-site replenishment and monitor supplier OTIF (on-time-in-full)."
MAX_CARDS=3

AUTH="${USERNAME}:${APP_PASSWORD}"

echo "=== GEO Suggest: basic call ==="
HTTP_RESPONSE=$(curl -s -w "\n%{http_code}" -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"${TEST_TITLE}\",\"content\":\"${TEST_CONTENT}\",\"max_cards\":${MAX_CARDS}}" \
  "${API_ENDPOINT}")

HTTP_BODY=$(echo "$HTTP_RESPONSE" | sed '$d')
HTTP_CODE=$(echo "$HTTP_RESPONSE" | tail -n1)

echo "HTTP status: $HTTP_CODE"
echo "Response snippet:"
echo "$HTTP_BODY" | jq 'if type=="object" and has("cards") then (.cards | length, .cards[0].question) elif type=="array" then (length, .[0].question) else . end' || echo "$HTTP_BODY"

echo
echo "=== Cache hit test (repeat same request) ==="
HTTP_RESPONSE2=$(curl -s -w "\n%{http_code}" -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"${TEST_TITLE}\",\"content\":\"${TEST_CONTENT}\",\"max_cards\":${MAX_CARDS}}" \
  "${API_ENDPOINT}" -D -)
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
echo "=== Optional: Stress test (50 rapid requests, stops on first 429) ==="
echo "To run: uncomment the stress_test function call below"
# stress_test  # Uncomment this line to run the stress test

echo
echo "Done."

# Optional stress test function
stress_test() {
    echo "Starting stress test: 50 rapid requests..."
    for i in {1..50}; do
        HTTP_RESPONSE_STRESS=$(curl -s -w "\n%{http_code}" -u "$AUTH" -H "Content-Type: application/json" \
            -d "{\"title\":\"${TEST_TITLE}\",\"content\":\"${TEST_CONTENT}\",\"max_cards\":${MAX_CARDS}}" \
            "${API_ENDPOINT}")
        HTTP_CODE_STRESS=$(echo "$HTTP_RESPONSE_STRESS" | tail -n1)
        echo "Request $i: Status $HTTP_CODE_STRESS"
        if [ "$HTTP_CODE_STRESS" = "429" ]; then
            echo "Rate limit hit at request $i (HTTP $HTTP_CODE_STRESS)"
            break
        fi
    done
    echo "Stress test completed."
}
