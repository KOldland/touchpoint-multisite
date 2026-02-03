#!/bin/bash

# Backend Verification Runner
# Automates as much of the backend checklist as possible
# Usage: ./tests/run-backend-checks.sh

set -e

PLUGIN_DIR="/Users/krisoldland/Local Sites/touchpoint-template/app/public/wp-content/plugins/kh-smma"
WP_PATH="/Users/krisoldland/Local Sites/touchpoint-template/app/public"
SITE_URL="http://touchpoint-template.local"

echo "========================================"
echo "Backend Verification Runner"
echo "========================================"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

# Helper functions
pass() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
    ((PASSED++))
}

fail() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    ((FAILED++))
}

warn() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1"
}

# Check 1: CI Safety
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "CHECK 1: CI Safety"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cd "$PLUGIN_DIR"
if php tests/ci-safety-check.php > /tmp/ci-safety.log 2>&1; then
    pass "CI safety check passed"
    cat /tmp/ci-safety.log
else
    fail "CI safety check failed"
    cat /tmp/ci-safety.log
fi
echo ""

# Check 2: PHPUnit Tests
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "CHECK 2: Unit Tests"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if vendor/bin/phpunit exists
if [ ! -f "$PLUGIN_DIR/vendor/bin/phpunit" ]; then
    warn "PHPUnit not installed. Installing..."
    cd "$PLUGIN_DIR"
    composer init --no-interaction 2>/dev/null || true
    composer require --dev phpunit/phpunit:^9.5
fi

# Run tests
export KH_SMMA_TEST_MODE=ci
if cd "$PLUGIN_DIR" && vendor/bin/phpunit tests/ComplianceValidatorTest.php --verbose > /tmp/phpunit.log 2>&1; then
    pass "All unit tests passed"
    echo "Summary:"
    grep -E "OK \(" /tmp/phpunit.log || grep "Tests:" /tmp/phpunit.log
else
    fail "Unit tests failed"
    cat /tmp/phpunit.log
fi
echo ""

# Check 3: Plugin Activation
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "CHECK 3: Plugin Activation"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Deactivate and reactivate
wp --path="$WP_PATH" plugin deactivate kh-smma --allow-root --quiet
if wp --path="$WP_PATH" plugin activate kh-smma --allow-root 2>/tmp/activate.log; then
    pass "Plugin activated successfully"
else
    fail "Plugin activation failed"
    cat /tmp/activate.log
fi

# Check capabilities
EDITOR_CAPS=$(wp --path="$WP_PATH" role list --format=json --allow-root | \
  jq '.[] | select(.name=="editor") | .capabilities | keys | .[]' | grep approve_sponsor_posts || echo "")

if [ -n "$EDITOR_CAPS" ]; then
    pass "Editor has approve_sponsor_posts capability"
else
    fail "Editor missing approve_sponsor_posts capability"
fi
echo ""

# Check 4: Database Schema
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "CHECK 4: Database Schema"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check CPT registered
CPT_EXISTS=$(wp --path="$WP_PATH" post-type list --format=json --allow-root | \
  jq '.[] | select(.name=="kh_smma_schedule") | .name' || echo "")

if [ -n "$CPT_EXISTS" ]; then
    pass "CPT kh_smma_schedule registered"
else
    fail "CPT kh_smma_schedule not registered"
fi

# Check tables exist
PHASE_TABLE=$(wp db query "SHOW TABLES LIKE 'wp_kh_smma_phase_events';" \
  --path="$WP_PATH" --allow-root 2>/dev/null | grep phase_events || echo "")

if [ -n "$PHASE_TABLE" ]; then
    pass "Table wp_kh_smma_phase_events exists"
else
    fail "Table wp_kh_smma_phase_events missing"
fi

AUDIT_TABLE=$(wp db query "SHOW TABLES LIKE 'wp_kh_smma_audit_log';" \
  --path="$WP_PATH" --allow-root 2>/dev/null | grep audit_log || echo "")

if [ -n "$AUDIT_TABLE" ]; then
    pass "Table wp_kh_smma_audit_log exists"
else
    fail "Table wp_kh_smma_audit_log missing"
fi
echo ""

# Check 5: Sponsor Metadata Function
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "CHECK 5: Sponsor Metadata"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

FUNC_EXISTS=$(wp --path="$WP_PATH" eval \
  'echo function_exists("kh_ad_manager_get_sponsor_meta") ? "yes" : "no";' \
  --allow-root 2>/dev/null)

if [ "$FUNC_EXISTS" = "yes" ]; then
    pass "Sponsor metadata function exists"
else
    warn "Sponsor metadata function not found (may be in separate plugin)"
fi
echo ""

# Check 6: Create Test Schedule
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "CHECK 6: Test Schedule Creation"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

TEST_SCHEDULE_ID=$(wp --path="$WP_PATH" post create \
  --post_type=kh_smma_schedule \
  --post_title="Automated Test Schedule" \
  --post_status=publish \
  --porcelain \
  --allow-root 2>/dev/null)

if [ -n "$TEST_SCHEDULE_ID" ]; then
    pass "Created test schedule ID: $TEST_SCHEDULE_ID"

    # Add payload meta
    wp --path="$WP_PATH" post meta update $TEST_SCHEDULE_ID _kh_smma_payload \
      '{"text":"Test variant text","channel":"linkedin","variant_id":"v-auto-test-001"}' \
      --format=json --allow-root

    pass "Added payload meta to schedule"

    # Store for later tests
    echo "$TEST_SCHEDULE_ID" > /tmp/test_schedule_id.txt
else
    fail "Could not create test schedule"
fi
echo ""

# Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All automated checks passed!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Run manual API tests (see BACKEND_CHECKLIST.md)"
    echo "2. Verify telemetry in database"
    echo "3. Test approve/reject idempotency"
    echo "4. Complete sign-off checklist"
    echo ""
    exit 0
else
    echo -e "${RED}✗ Some checks failed. Review output above.${NC}"
    echo ""
    exit 1
fi
