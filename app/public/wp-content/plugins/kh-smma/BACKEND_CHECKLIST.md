# Backend Sign-Off Checklist

Complete these backend-only checks before handing off to UI/QA team.

**Estimated Time:** 1-3 hours
**Target:** All checks passing, evidence attached

---

## A. Backend-Only Checks (No UI Required)

### ☑ 1. CI Safety Check

**Command:**
```bash
cd /Users/krisoldland/Local\ Sites/touchpoint-template/app/public/wp-content/plugins/kh-smma
php tests/ci-safety-check.php
```

**Acceptance Criteria:**
- Exit code: 0
- No live LLM keys detected
- All golden fixtures present
- MockLLMClient exists

**Evidence:** Attach terminal output

**Result:** ⬜ Pass / ⬜ Fail

---

### ☑ 2. Unit Tests with Golden Stubs

**Setup (first time only):**
```bash
cd /Users/krisoldland/Local\ Sites/touchpoint-template/app/public/wp-content/plugins/kh-smma
composer init --no-interaction
composer require --dev phpunit/phpunit:^9.5
```

**Command:**
```bash
export KH_SMMA_TEST_MODE=ci
vendor/bin/phpunit tests/ComplianceValidatorTest.php --verbose
```

**Acceptance Criteria:**
- 25/25 tests pass
- No network calls to external LLMs
- All test output shows "OK"
- No warnings or errors

**Evidence:** Attach phpunit output

**Result:** ⬜ Pass / ⬜ Fail

---

### ☑ 3. Plugin Activation (Register New Capability)

**Command:**
```bash
# Deactivate
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  plugin deactivate kh-smma --allow-root

# Activate
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  plugin activate kh-smma --allow-root

# Verify capability registered
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  role list --format=json --allow-root | \
  jq '.[] | select(.name=="editor" or .name=="administrator") | {name, capabilities}'
```

**Acceptance Criteria:**
- Plugin activates without errors
- Editor role has `approve_sponsor_posts` capability
- Administrator role has `approve_sponsor_posts` capability

**Evidence:** Attach role capabilities JSON

**Result:** ⬜ Pass / ⬜ Fail

---

### ☑ 4. Variant-Edit Backend Validation

#### Test 4a: Safe Edit (Should Pass)

**Setup:**
```bash
# Create test schedule first
SCHEDULE_ID=$(wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post create --post_type=kh_smma_schedule --post_title="Test Schedule" \
  --post_status=publish --porcelain --allow-root)

# Add payload meta
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post meta update $SCHEDULE_ID _kh_smma_payload \
  '{"text":"Original test text","channel":"linkedin","variant_id":"v-test-001"}' \
  --format=json --allow-root

echo "Created schedule ID: $SCHEDULE_ID"
```

**API Test:**
```bash
# Get WordPress nonce
WP_NONCE=$(wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  eval 'echo wp_create_nonce("wp_rest");' --allow-root)

# Test safe edit
curl -X POST "http://touchpoint-template.local/wp-json/kh-smma/v1/variant-edit" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  -H "Cookie: wordpress_logged_in=..." \
  -d "{
    \"schedule_id\": ${SCHEDULE_ID},
    \"updated_text\": \"Updated text with safe content\"
  }" | jq .
```

**Acceptance Criteria:**
- Response: `{"status":"updated","compliance":{"passed":true,...}}`
- Meta `_kh_smma_preview_changes` exists with `unified_diff`, `editor_id`, `timestamp`
- Meta `_kh_smma_payload` updated with new text

**Verify:**
```bash
# Check preview changes
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post meta get $SCHEDULE_ID _kh_smma_preview_changes --allow-root --format=json | jq .

# Expected fields: variant_id, editor_id, full_text, unified_diff, timestamp, compliance_result
```

**Evidence:** Attach API response + meta output

**Result:** ⬜ Pass / ⬜ Fail

#### Test 4b: Disallowed Phrase Edit (Should Fail)

**API Test:**
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-smma/v1/variant-edit" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  -H "Cookie: wordpress_logged_in=..." \
  -d "{
    \"schedule_id\": ${SCHEDULE_ID},
    \"updated_text\": \"This delivers guaranteed results in 30 days!\"
  }" | jq .
```

**Acceptance Criteria:**
- Response: `{"code":"kh_smma_compliance_failed","message":"Blocked phrase detected: guaranteed results",...}`
- Status code: 422
- No changes saved to meta

**Evidence:** Attach API error response

**Result:** ⬜ Pass / ⬜ Fail

---

### ☑ 5. Approve Endpoint - Authorization & Idempotency

#### Test 5a: Authorized Approval

**API Test:**
```bash
# First approval
curl -X POST "http://touchpoint-template.local/wp-json/kh-smma/v1/approve" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  -H "Cookie: wordpress_logged_in=..." \
  -d "{
    \"schedule_id\": ${SCHEDULE_ID},
    \"approver_id\": 1,
    \"notes\": \"Approved for testing\"
  }" | jq .
```

**Acceptance Criteria:**
- Response: `{"status":"approved","schedule_id":...,"approved_by":1,"approved_at":...}`
- Meta `_kh_smma_approval_status` = "approved"
- Telemetry logged with approver_id

**Evidence:** Attach API response

**Result:** ⬜ Pass / ⬜ Fail

#### Test 5b: Idempotency (Second Approval)

**API Test:**
```bash
# Second approval (should be idempotent)
curl -X POST "http://touchpoint-template.local/wp-json/kh-smma/v1/approve" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  -H "Cookie: wordpress_logged_in=..." \
  -d "{
    \"schedule_id\": ${SCHEDULE_ID},
    \"approver_id\": 1,
    \"notes\": \"Trying again\"
  }" | jq .
```

**Acceptance Criteria:**
- Response includes: `"idempotent": true`
- Original approval metadata returned
- No duplicate telemetry/audit entries

**Evidence:** Attach API response + audit log count

**Result:** ⬜ Pass / ⬜ Fail

#### Test 5c: Unauthorized User (403)

**Setup:**
```bash
# Create author-level user
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  user create testauthor test@example.com --role=author --user_pass=testpass --allow-root

# Get nonce for author (manual: log in as testauthor in browser)
```

**API Test:**
```bash
# Try to approve with author credentials
curl -X POST "http://touchpoint-template.local/wp-json/kh-smma/v1/approve" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${AUTHOR_NONCE}" \
  -H "Cookie: wordpress_logged_in_author=..." \
  -d "{\"schedule_id\": ${SCHEDULE_ID}}" | jq .
```

**Acceptance Criteria:**
- Status code: 403
- Response: `{"code":"kh_smma_insufficient_permissions",...}`

**Evidence:** Attach 403 response

**Result:** ⬜ Pass / ⬜ Fail

---

### ☑ 6. Reject Endpoint - Authorization & Idempotency

#### Test 6a: Authorized Rejection

**Setup:**
```bash
# Create new schedule for rejection test
REJECT_SCHEDULE_ID=$(wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post create --post_type=kh_smma_schedule --post_title="Test Reject" \
  --post_status=publish --porcelain --allow-root)

wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post meta update $REJECT_SCHEDULE_ID _kh_smma_payload \
  '{"text":"Test reject","channel":"linkedin"}' --format=json --allow-root
```

**API Test:**
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-smma/v1/reject" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  -H "Cookie: wordpress_logged_in=..." \
  -d "{
    \"schedule_id\": ${REJECT_SCHEDULE_ID},
    \"reason\": \"Does not meet editorial standards\"
  }" | jq .
```

**Acceptance Criteria:**
- Response: `{"status":"rejected","rejected_by":1,"rejected_at":...}`
- Meta `_kh_smma_approval_status` = "rejected"
- Meta `_kh_smma_rejection_reason` = reason text

**Evidence:** Attach API response

**Result:** ⬜ Pass / ⬜ Fail

#### Test 6b: Idempotency (Second Rejection)

**API Test:**
```bash
# Second rejection
curl -X POST "http://touchpoint-template.local/wp-json/kh-smma/v1/reject" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  -H "Cookie: wordpress_logged_in=..." \
  -d "{
    \"schedule_id\": ${REJECT_SCHEDULE_ID},
    \"reason\": \"Still rejected\"
  }" | jq .
```

**Acceptance Criteria:**
- Response includes: `"idempotent": true`
- Original rejection reason returned (not new one)

**Evidence:** Attach API response

**Result:** ⬜ Pass / ⬜ Fail

---

### ☑ 7. Schedule Endpoint - Timezone Handling

**API Test:**
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-smma/v1/schedule" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  -H "Cookie: wordpress_logged_in=..." \
  -d '{
    "post_id": 123,
    "schedule": [{
      "variant_id": "v-tz-test-001",
      "scheduled_at": "2025-02-20T09:00:00-05:00",
      "geo": "US-East",
      "text": "Timezone test variant"
    }],
    "boost": false,
    "sponsor_context": {"approval_required": false}
  }' | jq .
```

**Get schedule_id from response, then verify:**

```bash
TZ_SCHEDULE_ID=<from_response>

# Check UTC timestamp
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post meta get $TZ_SCHEDULE_ID _kh_smma_scheduled_at --allow-root

# Expected: 1708441200 (UTC equivalent of 2025-02-20T14:00:00Z)

# Check original timezone
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post meta get $TZ_SCHEDULE_ID _kh_smma_original_timezone --allow-root

# Expected: "-05:00" or similar

# Check payload metadata
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post meta get $TZ_SCHEDULE_ID _kh_smma_payload --allow-root --format=json | \
  jq '.meta'
```

**Acceptance Criteria:**
- UTC timestamp stored correctly (14:00 UTC = 09:00 EST)
- Original timezone preserved in `_kh_smma_original_timezone`
- Payload meta contains `original_timezone` and `scheduled_at_input`

**Evidence:** Attach timestamps + meta output

**Result:** ⬜ Pass / ⬜ Fail

---

### ☑ 8. Telemetry & Audit Verification

**Commands:**
```bash
# List recent schedules
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post list --post_type=kh_smma_schedule --posts_per_page=10 \
  --format=table --allow-root

# Pick a schedule ID and check telemetry
TELEMETRY_ID=<schedule_id>

# Check last telemetry
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post meta get $TELEMETRY_ID _kh_smma_last_telemetry --allow-root --format=json | jq .

# Check preview changes
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post meta get $TELEMETRY_ID _kh_smma_preview_changes --allow-root --format=json | jq .

# Check audit log (requires direct DB access)
wp db query "SELECT * FROM wp_kh_smma_audit_log WHERE object_id=${TELEMETRY_ID} ORDER BY created_at DESC LIMIT 10;" \
  --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" --allow-root
```

**Acceptance Criteria:**

**Telemetry (`_kh_smma_last_telemetry`) must include:**
- `timestamp`
- `mode` (generate/approve/reject/variant_edit)
- `provider` ("smma")
- For approve: `approver_id`, `notes`
- For reject: `rejected_by`, `rejection_reason`
- For variant_edit: `editor_id`, `diff`, `compliance_result`

**Preview Changes (`_kh_smma_preview_changes`) must include:**
- `variant_id`
- `editor_id`
- `full_text`
- `unified_diff`
- `timestamp`
- `compliance_result`

**Evidence:** Attach JSON outputs for both meta keys

**Result:** ⬜ Pass / ⬜ Fail

---

### ☑ 9. Sponsor Metadata Integration

**Command:**
```bash
# Check if function exists
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  eval 'echo function_exists("kh_ad_manager_get_sponsor_meta") ? "exists" : "missing";' --allow-root

# Test with sample sponsor ID (replace with real ID)
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  eval 'print_r(kh_ad_manager_get_sponsor_meta(123));' --allow-root
```

**Acceptance Criteria:**
- Function exists
- Returns array with `allowed_claims` key
- `allowed_claims` is an array (even if empty)

**Evidence:** Attach function output

**Result:** ⬜ Pass / ⬜ Fail

---

### ☑ 10. Database Schema & CPT Verification

**Commands:**
```bash
# Verify CPT registered
wp --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" \
  post-type list --format=table --allow-root | grep kh_smma

# Check tables exist
wp db query "SHOW TABLES LIKE 'wp_kh_smma%';" \
  --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" --allow-root

# Check phase events table structure
wp db query "DESCRIBE wp_kh_smma_phase_events;" \
  --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" --allow-root

# Check audit log table structure
wp db query "DESCRIBE wp_kh_smma_audit_log;" \
  --path="/Users/krisoldland/Local Sites/touchpoint-template/app/public" --allow-root
```

**Acceptance Criteria:**
- CPT `kh_smma_schedule` registered
- Tables exist: `wp_kh_smma_phase_events`, `wp_kh_smma_audit_log`
- Tables have correct schema with primary keys

**Evidence:** Attach table structures

**Result:** ⬜ Pass / ⬜ Fail

---

## Summary & Sign-Off

**Checklist Progress:** __/10 checks passed

**Blockers Found:** (list any failing tests)

**Evidence Package:** (attach all outputs in a zip file)

**Developer Sign-Off:**
- Name: ________________
- Date: ________________
- Signature: ________________

**Status:** ⬜ Ready for UI/QA Testing / ⬜ Blocked (requires fixes)

---

## Next Steps

Once all backend checks pass:

1. **Attach evidence package** to PR #12
2. **Hand off to UI/QA team** with this checklist completed
3. **Move to UI testing phase** (see VERIFICATION.md section B)

