# KH-SMMA API Endpoint Tests

Test documentation for newly implemented REST API endpoints.

## Setup

All requests require:
- Authentication: WordPress logged-in user with `edit_posts` capability
- Headers:
  - `Content-Type: application/json`
  - `X-WP-Nonce: <wp_rest_nonce>`
- Base URL: `/wp-json/kh-smma/v1`

---

## 1. POST /variant-edit

Edit a scheduled variant's text with compliance validation.

### Request

```json
POST /wp-json/kh-smma/v1/variant-edit
{
  "schedule_id": 123,
  "updated_text": "Discover how leading businesses optimize their LinkedIn strategy. Learn proven techniques that drive engagement. #Marketing #LinkedIn"
}
```

### Success Response (200 OK)

```json
{
  "status": "updated",
  "compliance": {
    "passed": true,
    "message": "",
    "notes": "OK: All compliance checks passed",
    "confidence_score": 0.95,
    "details": {
      "rule_check": {
        "passed": true,
        "notes": "OK: rule-based checks passed"
      },
      "ai_check": {
        "passed": true,
        "notes": "OK: AI compliance passed"
      }
    }
  }
}
```

### Error Response - Compliance Failed (422 Unprocessable Entity)

```json
{
  "code": "kh_smma_compliance_failed",
  "message": "Blocked phrase detected: guaranteed results",
  "data": {
    "status": 422
  }
}
```

### Error Response - Missing Parameters (400 Bad Request)

```json
{
  "code": "kh_smma_invalid_edit",
  "message": "schedule_id and updated_text are required.",
  "data": {
    "status": 400
  }
}
```

### Error Response - Schedule Not Found (404 Not Found)

```json
{
  "code": "kh_smma_schedule_not_found",
  "message": "Schedule not found.",
  "data": {
    "status": 404
  }
}
```

### Test Cases

1. **Valid Edit - No Sponsor**
   - schedule_id: existing schedule without sponsor
   - updated_text: "Check out our latest insights on digital marketing trends"
   - Expected: 200 OK, compliance passed

2. **Valid Edit - With Sponsor**
   - schedule_id: existing schedule with sponsor_id and allowed_claims
   - updated_text: Must include at least one allowed claim
   - Expected: 200 OK, compliance passed

3. **Invalid - Blacklist Violation**
   - updated_text: "Get guaranteed results with no risk!"
   - Expected: 422 Error, blacklist violation

4. **Invalid - Missing Sponsor Claim**
   - schedule_id: schedule with sponsor requiring specific claims
   - updated_text: Generic text without allowed claim
   - Expected: 422 Error, missing sponsor claim

5. **Invalid - Text Too Long**
   - updated_text: String longer than 3000 characters
   - Expected: 422 Error, length violation

---

## 2. POST /reject

Reject a scheduled variant with optional reason.

### Request

```json
POST /wp-json/kh-smma/v1/reject
{
  "schedule_id": 123,
  "reason": "Content does not align with brand voice"
}
```

### Success Response (200 OK)

```json
{
  "status": "rejected",
  "schedule_id": 123
}
```

### Error Response - Missing Schedule ID (400 Bad Request)

```json
{
  "code": "kh_smma_missing_schedule",
  "message": "schedule_id is required.",
  "data": {
    "status": 400
  }
}
```

### Error Response - Already Rejected (400 Bad Request)

```json
{
  "code": "kh_smma_already_rejected",
  "message": "This schedule is already rejected.",
  "data": {
    "status": 400
  }
}
```

### Test Cases

1. **Valid Rejection - With Reason**
   - schedule_id: existing pending schedule
   - reason: "Brand voice mismatch"
   - Expected: 200 OK, status rejected

2. **Valid Rejection - No Reason**
   - schedule_id: existing pending schedule
   - reason: ""
   - Expected: 200 OK, status rejected

3. **Invalid - Already Rejected**
   - schedule_id: schedule with approval_status = 'rejected'
   - Expected: 400 Error, already rejected

4. **Invalid - Missing Schedule ID**
   - Request without schedule_id
   - Expected: 400 Error, schedule_id required

### Post-Rejection Verification

After rejection, verify meta keys are set:
- `_kh_smma_approval_status`: 'rejected'
- `_kh_smma_rejected_by`: Current user ID
- `_kh_smma_rejected_at`: Unix timestamp
- `_kh_smma_rejection_reason`: Provided reason
- `_kh_smma_schedule_status`: 'rejected'

---

## 3. POST /schedule (Enhanced)

Create scheduled variants with automatic approval handling.

### Request - No Approval Required

```json
POST /wp-json/kh-smma/v1/schedule
{
  "post_id": 456,
  "schedule": [
    {
      "variant_id": "v-abc-123",
      "scheduled_at": 1675209600,
      "geo": "US-East",
      "text": "Latest insights on digital transformation"
    }
  ],
  "boost": false,
  "sponsor_context": {}
}
```

### Response - Auto-Approved (200 OK)

```json
{
  "created": [
    {
      "schedule_id": 789,
      "schedule_status": "pending",
      "approval_status": "auto_approved",
      "approval_required": false
    }
  ]
}
```

### Request - Approval Required (Sponsor)

```json
POST /wp-json/kh-smma/v1/schedule
{
  "post_id": 456,
  "schedule": [
    {
      "variant_id": "v-xyz-456",
      "scheduled_at": 1675296000,
      "geo": "UK",
      "text": "Sponsored content with approved claims"
    }
  ],
  "boost": false,
  "sponsor_context": {
    "sponsor_id": 12,
    "policy": "co-brand",
    "approval_required": true,
    "allowed_claims": ["leading solution", "trusted partner"],
    "sponsor_assets": []
  }
}
```

### Response - Awaiting Approval (200 OK)

```json
{
  "created": [
    {
      "schedule_id": 790,
      "schedule_status": "awaiting_approval",
      "approval_status": "pending",
      "approval_required": true
    }
  ]
}
```

### Test Cases

1. **Auto-Approved - No Sponsor**
   - sponsor_context: empty
   - boost: false
   - Expected: approval_status = 'auto_approved', schedule_status = 'pending'

2. **Requires Approval - Sponsor**
   - sponsor_context.approval_required: true
   - Expected: approval_status = 'pending', schedule_status = 'awaiting_approval'

3. **Requires Approval - Boost**
   - boost: true
   - boost_settings.requires_approval: true
   - Expected: approval_status = 'pending', schedule_status = 'awaiting_approval'

4. **Multiple Schedules**
   - schedule array with 3 items
   - Expected: 3 schedule posts created with correct approval statuses

### Post-Creation Verification

For auto-approved schedules, verify:
- `_kh_smma_approval_status`: 'auto_approved'
- `_kh_smma_approved_by`: 'system'
- `_kh_smma_approved_at`: Unix timestamp
- `_kh_smma_approval_required`: false

For approval-required schedules, verify:
- `_kh_smma_approval_status`: 'pending'
- `_kh_smma_schedule_status`: 'awaiting_approval'
- `_kh_smma_approval_required`: true
- `_kh_smma_approved_by`: NOT set
- `_kh_smma_approved_at`: NOT set

---

## ComplianceValidator Service Tests

Test the ComplianceValidator service independently.

### PHP Unit Test Example

```php
<?php
use KH_SMMA\Services\ComplianceValidator;

// Initialize validator
$validator = new ComplianceValidator();

// Test 1: Valid text, no sponsor
$result = $validator->validate(
    'Discover proven strategies for digital marketing success.',
    array( 'channel' => 'linkedin' )
);
assert( $result['passed'] === true );

// Test 2: Blacklist violation
$result = $validator->validate(
    'Get guaranteed results with our risk-free solution!',
    array( 'channel' => 'linkedin' )
);
assert( $result['passed'] === false );
assert( $result['violation_type'] === 'blacklist' );

// Test 3: Sponsor with allowed claims - valid
$result = $validator->validate(
    'Partner with the leading solution for your business.',
    array(
        'channel' => 'linkedin',
        'sponsor_id' => 12,
        'allowed_claims' => array( 'leading solution', 'trusted partner' ),
    )
);
assert( $result['passed'] === true );

// Test 4: Sponsor with allowed claims - missing claim
$result = $validator->validate(
    'Check out this amazing product for your needs.',
    array(
        'channel' => 'linkedin',
        'sponsor_id' => 12,
        'allowed_claims' => array( 'leading solution', 'trusted partner' ),
    )
);
assert( $result['passed'] === false );
assert( $result['violation_type'] === 'missing_claim' );

// Test 5: Length violation
$result = $validator->validate(
    str_repeat( 'a', 3001 ),
    array( 'channel' => 'linkedin' )
);
assert( $result['passed'] === false );
assert( $result['violation_type'] === 'length' );

// Test 6: Batch validation
$variants = array(
    array( 'variant_id' => 'v1', 'text' => 'Valid content here', 'channel' => 'linkedin' ),
    array( 'variant_id' => 'v2', 'text' => 'Guaranteed results!', 'channel' => 'linkedin' ),
);
$results = $validator->validate_batch( $variants, array() );
assert( $results['v1']['passed'] === true );
assert( $results['v2']['passed'] === false );
```

---

## Integration Test Workflow

Complete end-to-end test for variant creation, editing, and rejection:

1. **Generate Variants**
   ```
   POST /generate
   { "post_id": 456, "num_variants": 2, "phase_tag": "Attention" }
   ```
   Save variant IDs from response

2. **Schedule Variants (Approval Required)**
   ```
   POST /schedule
   {
     "post_id": 456,
     "schedule": [{ "variant_id": "v-1", "scheduled_at": ..., "text": "..." }],
     "sponsor_context": { "approval_required": true }
   }
   ```
   Save schedule_id from response

3. **Edit Variant**
   ```
   POST /variant-edit
   { "schedule_id": <from_step_2>, "updated_text": "Improved content..." }
   ```
   Verify compliance passed

4. **Approve Variant**
   ```
   POST /approve
   { "schedule_id": <from_step_2> }
   ```
   Verify status = 'approved'

5. **Reject Another Variant** (schedule a second one first)
   ```
   POST /reject
   { "schedule_id": <schedule_id_2>, "reason": "Not aligned" }
   ```
   Verify status = 'rejected'

---

## Automated Testing Commands

### Using WP-CLI

```bash
# Test variant-edit endpoint
wp eval 'echo json_encode((new WP_REST_Request("POST", "/kh-smma/v1/variant-edit"))->set_body_params(["schedule_id" => 123, "updated_text" => "Test content"]));'

# Test reject endpoint
wp eval 'echo json_encode((new WP_REST_Request("POST", "/kh-smma/v1/reject"))->set_body_params(["schedule_id" => 123, "reason" => "Test rejection"]));'
```

### Using cURL

```bash
# Get nonce first
NONCE=$(curl -s --cookie-jar cookies.txt \
  -d "log=admin&pwd=password" \
  http://localhost/wp-login.php | \
  grep -oP 'wpApiSettings.*nonce["\s:]+\K[^"]+')

# Test variant-edit
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $NONCE" \
  --cookie cookies.txt \
  -d '{"schedule_id":123,"updated_text":"New content"}' \
  http://localhost/wp-json/kh-smma/v1/variant-edit

# Test reject
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $NONCE" \
  --cookie cookies.txt \
  -d '{"schedule_id":123,"reason":"Test rejection"}' \
  http://localhost/wp-json/kh-smma/v1/reject
```

---

## Expected Audit Log Entries

After successful operations, verify audit log entries:

### variant-edit
- Action: `smma_variant_edit`
- Object type: `schedule`
- Object ID: schedule_id
- Details: `{ "compliance_passed": true, "edited_by": <user_id> }`

### reject
- Action: `smma_schedule_reject`
- Object type: `schedule`
- Object ID: schedule_id
- Details: `{ "reason": "...", "rejected_by": <user_id> }`

---

## Notes

- All endpoints require valid WordPress authentication
- Feature flag 'smma' must be enabled via FeatureFlags service
- ComplianceValidator can fallback to rule-based validation if AI is unavailable
- Telemetry is logged via ScheduleQueueProcessor for all operations
- Approval workflow supports both manual approval (sponsors) and auto-approval (no restrictions)
