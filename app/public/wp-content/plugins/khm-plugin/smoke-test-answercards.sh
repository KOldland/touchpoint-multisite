#!/usr/bin/env bash
# Smoke test for AnswerCard REST endpoints and redirect handler
# Tests: public endpoint, full endpoint, redirect lookup
set -euo pipefail

# === CONFIG - EDIT THESE BEFORE RUNNING ===
SITE_URL="http://kh-staging.test"           # <-- replace with your site URL (no trailing slash)
USERNAME="admin"                            # <-- WP admin username
APP_PASSWORD="YOUR_APP_PASSWORD_HERE"       # <-- Application password for that user
TEST_POST_ID="1"                            # <-- A post ID that has AnswerCard blocks
# ==========================================

AUTH="${USERNAME}:${APP_PASSWORD}"

echo "=========================================="
echo "  AnswerCard REST API Smoke Tests"
echo "=========================================="
echo ""

# --- Test 1: Public endpoint (no auth) ---
echo "=== Test 1: Public AnswerCards endpoint (no auth) ==="
PUBLIC_ENDPOINT="${SITE_URL}/wp-json/khm-geo/v1/posts/${TEST_POST_ID}/answercards"
echo "GET ${PUBLIC_ENDPOINT}"
HTTP_RESPONSE=$(curl -s -w "\n%{http_code}" "${PUBLIC_ENDPOINT}")
HTTP_BODY=$(echo "$HTTP_RESPONSE" | sed '$d')
HTTP_CODE=$(echo "$HTTP_RESPONSE" | tail -n1)

echo "HTTP status: $HTTP_CODE"
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Public endpoint accessible"
    # Check that sensitive fields are stripped
    if echo "$HTTP_BODY" | jq -e '.[0].evidence.confidence // empty' > /dev/null 2>&1; then
        echo "⚠️  WARNING: confidence field present in public response (should be stripped)"
    else
        echo "✅ confidence field properly stripped"
    fi
    if echo "$HTTP_BODY" | jq -e '.[0].evidence.source_passage // empty' > /dev/null 2>&1; then
        echo "⚠️  WARNING: source_passage field present in public response (should be stripped)"
    else
        echo "✅ source_passage field properly stripped"
    fi
    if echo "$HTTP_BODY" | jq -e '.[0].citations[0].tracked_url // empty' > /dev/null 2>&1; then
        echo "⚠️  WARNING: tracked_url field present in public response (should be stripped)"
    else
        echo "✅ tracked_url field properly stripped"
    fi
    echo "Cards returned: $(echo "$HTTP_BODY" | jq 'length')"
else
    echo "❌ Public endpoint failed with status $HTTP_CODE"
    echo "$HTTP_BODY"
fi
echo ""

# --- Test 2: Full endpoint (with auth) ---
echo "=== Test 2: Full AnswerCards endpoint (authenticated) ==="
FULL_ENDPOINT="${SITE_URL}/wp-json/khm-geo/v1/tracker/posts/${TEST_POST_ID}/answercards"
echo "GET ${FULL_ENDPOINT}"
HTTP_RESPONSE=$(curl -s -w "\n%{http_code}" -u "$AUTH" "${FULL_ENDPOINT}")
HTTP_BODY=$(echo "$HTTP_RESPONSE" | sed '$d')
HTTP_CODE=$(echo "$HTTP_RESPONSE" | tail -n1)

echo "HTTP status: $HTTP_CODE"
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Full endpoint accessible with auth"
    # Check for meta envelope
    if echo "$HTTP_BODY" | jq -e '.meta' > /dev/null 2>&1; then
        echo "✅ Response has meta envelope"
        echo "Generated at: $(echo "$HTTP_BODY" | jq -r '.meta.generated_at')"
    else
        echo "⚠️  WARNING: Response missing meta envelope"
    fi
    # Check for cards array
    if echo "$HTTP_BODY" | jq -e '.cards' > /dev/null 2>&1; then
        CARD_COUNT=$(echo "$HTTP_BODY" | jq '.cards | length')
        echo "✅ Cards array present: ${CARD_COUNT} cards"
        # Check first card for new fields
        if echo "$HTTP_BODY" | jq -e '.cards[0].answer_card_id' > /dev/null 2>&1; then
            ANSWER_CARD_ID=$(echo "$HTTP_BODY" | jq -r '.cards[0].answer_card_id')
            echo "✅ answer_card_id present: ${ANSWER_CARD_ID}"
        else
            echo "⚠️  answer_card_id missing from first card"
        fi
        if echo "$HTTP_BODY" | jq -e '.cards[0].requires_review' > /dev/null 2>&1; then
            echo "✅ requires_review field present"
        fi
    else
        echo "❌ Cards array missing from response"
    fi
else
    echo "❌ Full endpoint failed with status $HTTP_CODE"
    echo "$HTTP_BODY"
fi
echo ""

# --- Test 3: Full endpoint without auth (should fail) ---
echo "=== Test 3: Full endpoint without auth (should fail) ==="
HTTP_RESPONSE=$(curl -s -w "\n%{http_code}" "${FULL_ENDPOINT}")
HTTP_CODE=$(echo "$HTTP_RESPONSE" | tail -n1)
echo "HTTP status: $HTTP_CODE"
if [ "$HTTP_CODE" = "401" ] || [ "$HTTP_CODE" = "403" ]; then
    echo "✅ Full endpoint correctly requires authentication"
else
    echo "❌ Expected 401/403, got $HTTP_CODE"
fi
echo ""

# --- Test 4: Redirect handler (if any redirects exist) ---
echo "=== Test 4: Redirect handler ==="
echo "Testing /r/<code> redirect endpoint..."
# First, try to get a redirect code from the database via the full endpoint
REDIRECT_CODE=$(echo "$HTTP_BODY" | jq -r '.cards[0].citations[0].tracked_url // empty' 2>/dev/null | grep -oE '/r/[a-zA-Z0-9]+' | sed 's|/r/||' || true)
if [ -n "$REDIRECT_CODE" ]; then
    REDIRECT_URL="${SITE_URL}/r/${REDIRECT_CODE}"
    echo "Testing redirect: ${REDIRECT_URL}"
    HTTP_RESPONSE=$(curl -s -w "\n%{http_code}" -L -o /dev/null "${REDIRECT_URL}")
    HTTP_CODE=$(echo "$HTTP_RESPONSE" | tail -n1)
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "301" ] || [ "$HTTP_CODE" = "302" ]; then
        echo "✅ Redirect handler working"
    else
        echo "⚠️  Redirect returned status $HTTP_CODE"
    fi
else
    echo "ℹ️  No tracked_url found to test redirect handler"
    # Test with a fake code to check 404 handling
    FAKE_URL="${SITE_URL}/r/FAKECODE123"
    echo "Testing 404 handling: ${FAKE_URL}"
    HTTP_RESPONSE=$(curl -s -w "\n%{http_code}" "${FAKE_URL}")
    HTTP_CODE=$(echo "$HTTP_RESPONSE" | tail -n1)
    echo "HTTP status for fake code: $HTTP_CODE"
    if [ "$HTTP_CODE" = "404" ]; then
        echo "✅ 404 handling works for invalid codes"
    fi
fi
echo ""

# --- Test 5: Compare public vs full response ---
echo "=== Test 5: Public vs Full field comparison ==="
PUBLIC_RESPONSE=$(curl -s -u "$AUTH" "${PUBLIC_ENDPOINT}")
FULL_RESPONSE=$(curl -s -u "$AUTH" "${FULL_ENDPOINT}")

PUBLIC_FIELDS=$(echo "$PUBLIC_RESPONSE" | jq -r '.[0] // {} | keys[]' 2>/dev/null | sort | tr '\n' ',' || echo "")
FULL_CARD_FIELDS=$(echo "$FULL_RESPONSE" | jq -r '.cards[0] // {} | keys[]' 2>/dev/null | sort | tr '\n' ',' || echo "")

echo "Public response fields: ${PUBLIC_FIELDS:-none}"
echo "Full response card fields: ${FULL_CARD_FIELDS:-none}"
echo ""

# --- Summary ---
echo "=========================================="
echo "  Smoke Test Complete"
echo "=========================================="
echo ""
echo "Manual checks still needed:"
echo "  1. Edit a post with AnswerCard, verify new sidebar panels appear"
echo "  2. Check JSON-LD output on frontend has @id anchors"
echo "  3. Test low-confidence card (< 0.6) is hidden from schema"
echo "  4. Verify tracked URLs generate /r/<code> links"
echo ""
echo "Done."
