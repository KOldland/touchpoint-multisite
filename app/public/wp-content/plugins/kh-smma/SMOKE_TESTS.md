# API Smoke Tests

Run these tests against your staging environment to verify all critical functionality before merge.

## Setup

### 1. Get Authentication Credentials

```bash
# Get WordPress nonce for REST API
export WP_NONCE=$(curl -s --cookie-jar cookies.txt \
  -d "log=YOUR_USERNAME&pwd=YOUR_PASSWORD" \
  "http://localhost/wp-login.php" | \
  grep "wp_rest" | sed -n 's/.*"wp_rest":"\\([^"]*\\)".*/\1/p')

# Or manually: Log into WordPress, open browser console, run:
# wp.apiFetch.nonceMiddleware.nonce

export STAGING_URL="http://localhost"
```

### 2. Set Test Mode (for golden stubs)

```bash
export KH_SMMA_TEST_MODE=ci
```

## Test 1: Generate Variants (with golden stubs)

**Endpoint:** `POST /kh-smma/v1/generate`

```bash
curl -X POST "${STAGING_URL}/wp-json/kh-smma/v1/generate" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  --cookie cookies.txt \
  -d '{
    "post_id": 123,
    "blocks_json": {
      "summary": "Testing variant generation with golden stubs",
      "key_points": ["Point 1", "Point 2"]
    },
    "num_variants": 2,
    "tone": "Authority",
    "geo_targets": ["GB", "US-East"],
    "phase_tag": "Anxiety",
    "user_controls": {
      "boost": false
    }
  }' | jq .
```

**Expected Response:**
```json
{
  "variants": [
    {
      "variant_id": "v-test-001",
      "channel": "linkedin",
      "text": "...",
      "phase_tag": "Anxiety",
      "tone": "Authority",
      "compliance_notes": "OK: All compliance checks passed",
      "recommended_post_time_gmt": 1675209600,
      "geo_recommendations": [...],
      "sponsor_flag": false,
      "explainability": "..."
    }
  ]
}
```

**Verify:**
- [ ] Response contains 2 variants
- [ ] Each variant has `variant_id`, `text`, `phase_tag`, `compliance_notes`
- [ ] `phase_tag` matches request ("Anxiety")
- [ ] Response time < 2 seconds (golden stubs should be fast)

## Test 2: Schedule Variants (with timezone handling)

**Endpoint:** `POST /kh-smma/v1/schedule`

```bash
# Create schedule with ISO 8601 datetime
curl -X POST "${STAGING_URL}/wp-json/kh-smma/v1/schedule" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  --cookie cookies.txt \
  -d '{
    "post_id": 123,
    "schedule": [
      {
        "variant_id": "v-test-001",
        "scheduled_at": "2025-02-20T09:00:00-05:00",
        "geo": "US-East",
        "text": "Test variant text for timezone handling"
      }
    ],
    "boost": false,
    "sponsor_context": {
      "sponsor_id": 0,
      "approval_required": false
    }
  }' | jq .
```

**Expected Response:**
```json
{
  "created": [
    {
      "schedule_id": 456,
      "schedule_status": "pending",
      "approval_status": "auto_approved",
      "approval_required": false
    }
  ]
}
```

**Verify Timezone Handling:**
```bash
# Get schedule_id from response above
SCHEDULE_ID=456

# Check stored UTC timestamp
wp post meta get ${SCHEDULE_ID} _kh_smma_scheduled_at --allow-root
# Expected: 1708441200 (UTC equivalent of 2025-02-20T14:00:00Z)

# Check original timezone preserved
wp post meta get ${SCHEDULE_ID} _kh_smma_original_timezone --allow-root
# Expected: "-05:00" or "America/New_York"

# Check payload metadata
wp post meta get ${SCHEDULE_ID} _kh_smma_payload --allow-root --format=json | jq '.meta'
# Expected: contains "original_timezone" and "scheduled_at_input"
```

**Verify:**
- [ ] Schedule created successfully
- [ ] UTC timestamp stored correctly
- [ ] Original timezone preserved in metadata
- [ ] `_kh_smma_original_timezone` meta exists

## Test 3: Variant Edit (with diff calculation)

**Endpoint:** `POST /kh-smma/v1/variant-edit`

```bash
# First, get the current variant text
ORIGINAL_TEXT="Test variant text for timezone handling"

# Edit the variant
curl -X POST "${STAGING_URL}/wp-json/kh-smma/v1/variant-edit" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  --cookie cookies.txt \
  -d '{
    "schedule_id": 456,
    "updated_text": "Updated test variant with improved messaging and compliance"
  }' | jq .
```

**Expected Response:**
```json
{
  "status": "updated",
  "compliance": {
    "passed": true,
    "message": "",
    "notes": "OK: All compliance checks passed",
    "confidence_score": 0.95,
    "details": {
      "rule_check": {"passed": true, "notes": "OK: rule-based checks passed"},
      "ai_check": {"passed": true, "notes": "OK: AI compliance passed"}
    }
  }
}
```

**Verify Diff & Preview Changes:**
```bash
# Check preview changes metadata
wp post meta get 456 _kh_smma_preview_changes --allow-root --format=json | jq .

# Expected structure:
# {
#   "variant_id": "v-test-001",
#   "editor_id": 1,
#   "full_text": "Updated test variant...",
#   "unified_diff": "--- Original\n+++ Updated\n...",
#   "timestamp": 1675209600,
#   "compliance_result": {...}
# }
```

**Verify:**
- [ ] Variant updated successfully
- [ ] Compliance check passed
- [ ] `_kh_smma_preview_changes` meta exists
- [ ] `unified_diff` shows changes
- [ ] `editor_id` matches current user

## Test 4: Approve (idempotency test)

**Endpoint:** `POST /kh-smma/v1/approve`

```bash
# First approval
curl -X POST "${STAGING_URL}/wp-json/kh-smma/v1/approve" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  --cookie cookies.txt \
  -d '{
    "schedule_id": 456,
    "approver_id": 1,
    "notes": "Looks good, approved for publishing"
  }' | jq .

# Expected: {"status":"approved","schedule_id":456,"approved_by":1,"approved_at":...}

# Second approval (should be idempotent)
curl -X POST "${STAGING_URL}/wp-json/kh-smma/v1/approve" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  --cookie cookies.txt \
  -d '{
    "schedule_id": 456,
    "approver_id": 1,
    "notes": "Trying to approve again"
  }' | jq .

# Expected: {"status":"approved","message":"This schedule was already approved.","approved_by":1,"approved_at":...,"idempotent":true}
```

**Verify:**
- [ ] First call returns success with `approved_by` and `approved_at`
- [ ] Second call returns 200 (not 400/500)
- [ ] Second call includes `"idempotent": true`
- [ ] Second call returns original approval metadata
- [ ] Only ONE approval audit log entry created

## Test 5: Reject (idempotency test)

**Endpoint:** `POST /kh-smma/v1/reject`

```bash
# Create a new schedule first (to avoid conflict with approved one)
# ... (use schedule endpoint from Test 2)
NEW_SCHEDULE_ID=457

# First rejection
curl -X POST "${STAGING_URL}/wp-json/kh-smma/v1/reject" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  --cookie cookies.txt \
  -d '{
    "schedule_id": 457,
    "reason": "Content does not meet editorial standards"
  }' | jq .

# Expected: {"status":"rejected","schedule_id":457,"rejected_by":1,"rejected_at":...}

# Second rejection (should be idempotent)
curl -X POST "${STAGING_URL}/wp-json/kh-smma/v1/reject" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  --cookie cookies.txt \
  -d '{
    "schedule_id": 457,
    "reason": "Still rejected"
  }' | jq .

# Expected: {"status":"rejected","message":"This schedule was already rejected.","rejected_by":1,"rejected_at":...,"reason":"Content does not meet editorial standards","idempotent":true}
```

**Verify:**
- [ ] First call returns success with `rejected_by`, `rejected_at`
- [ ] Second call returns 200 (not 400/500)
- [ ] Second call includes `"idempotent": true`
- [ ] Second call returns original rejection reason (not new one)
- [ ] Only ONE rejection audit log entry created

## Test 6: Authorization (403 tests)

Test that endpoints properly reject unauthorized users.

```bash
# Create a non-privileged user or revoke capability
wp user create testauthor testauthor@example.com --role=author --allow-root

# Get nonce for this user
# ... (log in as testauthor, get nonce)

# Try to approve (should fail)
curl -X POST "${STAGING_URL}/wp-json/kh-smma/v1/approve" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${TESTAUTHOR_NONCE}" \
  --cookie testauthor_cookies.txt \
  -d '{"schedule_id": 456}' | jq .

# Expected: {"code":"kh_smma_insufficient_permissions","message":"You do not have permission to approve sponsored content.","data":{"status":403}}
```

**Verify:**
- [ ] Author (without `approve_sponsor_posts`) gets 403
- [ ] Editor (with `approve_sponsor_posts`) can approve
- [ ] Admin can always approve

## Test 7: Compliance Validation

Test blacklist and sponsor claim validation.

```bash
# Test blacklist detection
curl -X POST "${STAGING_URL}/wp-json/kh-smma/v1/variant-edit" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${WP_NONCE}" \
  --cookie cookies.txt \
  -d '{
    "schedule_id": 456,
    "updated_text": "This product delivers guaranteed results in just 30 days!"
  }' | jq .

# Expected: {"code":"kh_smma_compliance_failed","message":"Blocked phrase detected: guaranteed results","data":{"status":422}}
```

**Verify:**
- [ ] Blacklist phrase detected and rejected
- [ ] Error message identifies specific phrase
- [ ] Status code is 422 (Unprocessable Entity)

## Test 8: Telemetry Verification

Verify telemetry is being logged for all operations.

```bash
# Check telemetry for generate
wp post meta get 456 _kh_smma_last_telemetry --allow-root --format=json | jq .

# Expected fields:
# {
#   "timestamp": 1675209600,
#   "mode": "approve",
#   "provider": "smma",
#   "approver_id": 1,
#   "notes": "..."
# }
```

**Verify:**
- [ ] Telemetry logged for: generate, schedule, variant_edit, approve, reject
- [ ] Each telemetry entry has: timestamp, mode, provider
- [ ] Variant_edit telemetry includes: editor_id, diff, compliance_result
- [ ] Approve/reject telemetry includes: approver_id/rejected_by, notes/reason

## Summary Checklist

### Critical Path
- [ ] Generate (golden stubs work)
- [ ] Schedule (timezone handling)
- [ ] Approve (idempotency)
- [ ] Reject (idempotency)
- [ ] Authorization (403 for non-privileged)

### Data Integrity
- [ ] Timezone conversion correct
- [ ] Diff calculation works
- [ ] Preview changes stored
- [ ] Telemetry complete

### Security
- [ ] Nonce validation enforced
- [ ] Capability checks enforced
- [ ] Blacklist enforcement works

### Performance
- [ ] Golden stubs fast (< 2 sec)
- [ ] No live LLM calls in CI

## Troubleshooting

### "Invalid nonce" Error
```bash
# Re-generate nonce
wp eval 'echo wp_create_nonce("wp_rest");' --allow-root
```

### "Insufficient permissions" Error
```bash
# Grant capability manually
wp role add-cap editor approve_sponsor_posts --allow-root
```

### Timezone Mismatch
```bash
# Check PHP timezone
php -i | grep timezone

# Set in WordPress
wp option update timezone_string 'UTC' --allow-root
```

---

**Last Updated:** 2025-02-03
**Test Coverage:** 8 critical scenarios
**Estimated Runtime:** 15-20 minutes
