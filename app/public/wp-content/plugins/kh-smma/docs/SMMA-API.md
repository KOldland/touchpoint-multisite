# SMMA REST API Documentation

**Version**: 1.0.0
**Base Path**: `/wp-json/kh-smma/v1`
**Authentication**: WordPress nonce verification + capability checks

---

## Table of Contents

1. [Authentication](#authentication)
2. [Generate Endpoint](#post-generate)
3. [Compliance Check Endpoint](#post-compliancecheck)
4. [Schedule Endpoint](#post-schedule)
5. [Approve/Reject Endpoints](#post-variantsapprove--post-variantsreject)
6. [Record Event Endpoint](#post-events)
7. [Error Handling](#error-handling)
8. [Rate Limiting](#rate-limiting)
9. [Telemetry & Monitoring](#telemetry--monitoring)

---

## Authentication

All endpoints require:
1. **WordPress Authentication**: Valid logged-in session
2. **Nonce Verification**: `X-WP-Nonce` header with valid nonce
3. **Capability Check**: User must have `edit_posts` capability

### Getting a Nonce

```javascript
const nonce = wpApiSettings.nonce; // WordPress REST API nonce
```

### Example Request Headers

```http
POST /wp-json/kh-smma/v1/generate
Content-Type: application/json
X-WP-Nonce: abc123def456...
```

---

## POST /generate

Generate LinkedIn promotional variants and optional Google Ads draft for a post.

### Request Body

```json
{
  "post_id": 123,
  "num_variants": 2,
  "phase_tag": "Attention",
  "tone": "Authority",
  "geo_targets": ["US", "GB"],
  "sponsor_context": {
    "sponsor_id": 456,
    "allowed_claims": [
      "award-winning design",
      "trusted by professionals"
    ]
  },
  "generate_google_ads": true,
  "num_ad_groups": 2,
  "keywords": ["business strategy", "leadership", "growth"]
}
```

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `post_id` | integer | Yes | - | WordPress post ID |
| `num_variants` | integer | No | 2 | Number of LinkedIn variants (1-5) |
| `phase_tag` | string | No | "Attention" | SMMA phase: Attention, Anxiety, Action, Aftercare |
| `tone` | string | No | "Authority" | Tone: Authority, Empathy, Urgency, Curiosity |
| `geo_targets` | array | No | [] | ISO country codes for targeting |
| `sponsor_context` | object | No | null | Sponsor metadata for compliance |
| `sponsor_context.sponsor_id` | integer | No | null | Sponsor ID for allowed claims |
| `sponsor_context.allowed_claims` | array | No | [] | Pre-approved marketing claims |
| `generate_google_ads` | boolean | No | true | Whether to generate Google Ads draft |
| `num_ad_groups` | integer | No | 2 | Number of Google Ads ad groups (1-5) |
| `keywords` | array | No | [] | Keywords for Google Ads targeting |
| `title` | string | No | Post title | Override post title for ads |
| `canonical_url` | string | No | Post URL | Override canonical URL |

### Response

```json
{
  "variants": [
    {
      "variant_id": "v-att-auth-1",
      "channel": "linkedin",
      "text": "Are you ready to transform your business strategy? Discover proven frameworks...",
      "phase_tag": "Attention",
      "tone": "Authority",
      "recommended_post_time_gmt": 1707148800,
      "geo_recommendations": ["US", "GB"],
      "sponsor_flag": false,
      "compliance_notes": "OK: No compliance issues detected",
      "explainability": "Attention-grabbing question hooks the audience...",
      "asset_hints": {
        "image_style": "professional",
        "suggested_colors": ["#0077B5", "#FFFFFF"]
      }
    }
  ],
  "linkedin_variants": [ /* same as variants */ ],
  "google_ad_draft": {
    "ad_groups": [
      {
        "keyword_cluster": "business strategy, leadership",
        "headlines": [
          "Transform Your Strategy",
          "Expert Business Insights",
          "Proven Leadership Framework"
        ],
        "descriptions": [
          "Discover actionable strategies from industry experts. Click to learn more.",
          "Get the latest business insights and professional guidance today."
        ],
        "final_url": "https://example.com/post/123",
        "final_url_with_utm": "https://example.com/post/123?utm_source=google_ads&utm_medium=cpc&utm_campaign=ad_group_1",
        "cpc_suggestion": 2.15
      }
    ],
    "metadata": {
      "generated_by": "smma-google-ads-v1",
      "model": "gpt-4-turbo",
      "created_at": 1707148800
    }
  },
  "google_ad_compliance": {
    "passed": true,
    "message": "",
    "ad_group_results": []
  },
  "model": "gpt-4-turbo"
}
```

### Compliance Levels

Variants include a `compliance_notes` field indicating compliance status:

- **OK**: `"OK: No compliance issues detected"` - Safe for all channels
- **WARN**: `"WARN: contains vague success claims"` - Requires sponsor verification before paid promotion
- **FAIL**: `"FAIL: multiple blacklisted phrases detected"` - Blocks paid scheduling

### Error Responses

```json
{
  "code": "kh_smma_missing_post_id",
  "message": "post_id is required.",
  "data": {
    "status": 400
  }
}
```

---

## POST /compliance/check

Validate text content against compliance rules (blacklist phrases, length limits, sponsor claims).

### Request Body

```json
{
  "text": "Experience our award-winning design and see the difference.",
  "channel": "linkedin",
  "phase_tag": "Attention",
  "sponsor_id": 123,
  "allowed_claims": [
    "award-winning design"
  ]
}
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `text` | string | Yes | Text content to validate |
| `channel` | string | No | Channel: linkedin, twitter, google_ads |
| `phase_tag` | string | No | SMMA phase for context |
| `sponsor_id` | integer | No | Sponsor ID for claim enforcement |
| `allowed_claims` | array | No | Pre-approved claims for sponsor |

### Response

```json
{
  "pass": true,
  "level": "OK",
  "flags": [],
  "suggested_edits": [],
  "confidence": 0.95,
  "details": {
    "violation_type": null,
    "message": ""
  }
}
```

### Compliance Levels

| Level | Description | Behavior |
|-------|-------------|----------|
| `OK` | No issues detected | Approved for all channels |
| `WARN` | Requires sponsor verification | Blocks paid scheduling until approved |
| `FAIL` | Critical violations (blacklist) | Blocks all paid scheduling |

### Common Flags

- `blacklist_violation` - Contains prohibited phrases
- `length_exceeded` - Exceeds channel character limit
- `unverified_claim` - Marketing claim needs substantiation
- `sponsor_approval_required` - Requires sponsor pre-approval
- `missing_allowed_claim` - Sponsored content missing required claim

### Blacklisted Phrases

The following phrases trigger FAIL:
- "guaranteed results", "guarantee results"
- "risk-free", "no risk"
- "100% guaranteed"
- "get rich quick"
- "miracle cure"
- "instant results"

### Channel Length Limits

| Channel | Max Characters |
|---------|----------------|
| LinkedIn | 3000 |
| Twitter | 280 |
| Google Ads Headlines | 30 |
| Google Ads Descriptions | 90 |

---

## POST /schedule

Schedule approved variants to social media channels.

### Request Body

```json
{
  "post_id": 123,
  "selected_variants": ["v-att-auth-1", "v-anx-emp-2"],
  "schedule": [
    {
      "variant_id": "v-att-auth-1",
      "scheduled_at": 1707148800,
      "geo": "US",
      "channel": "linkedin"
    },
    {
      "variant_id": "v-anx-emp-2",
      "scheduled_at": 1707235200,
      "geo": "GB",
      "channel": "linkedin"
    }
  ],
  "boost": false,
  "dry_run": false
}
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `post_id` | integer | Yes | WordPress post ID |
| `selected_variants` | array | Yes | Variant IDs to schedule |
| `schedule` | array | Yes | Schedule entries with timing/geo |
| `schedule[].variant_id` | string | Yes | Variant ID |
| `schedule[].scheduled_at` | integer | Yes | Unix timestamp (GMT) |
| `schedule[].geo` | string | Yes | ISO country code |
| `schedule[].channel` | string | Yes | Channel: linkedin, twitter |
| `boost` | boolean | No | Whether to boost posts (paid promotion) |
| `dry_run` | boolean | No | Preview mode (no actual scheduling) |

### Response

```json
{
  "created": [
    {
      "queue_id": 789,
      "variant_id": "v-att-auth-1",
      "scheduled_at": 1707148800,
      "status": "queued",
      "adapter": "linkedin_organic",
      "dry_run": false
    }
  ],
  "errors": []
}
```

### Scheduling States

1. **queued** - Initial state, awaiting processing
2. **processing** - Adapter actively posting
3. **posted** - Successfully posted to channel
4. **failed** - Posting failed (see error message)
5. **dry_run** - Preview mode (no actual posting)

### Compliance Gates

- **FAIL compliance**: Blocks `boost: true` (paid promotion)
- **WARN compliance**: Blocks `boost: true` unless `sponsor_approved: true`
- **OK compliance**: No restrictions

---

## POST /variants/approve

Approve a variant for paid promotion (bypasses WARN gates).

### Request Body

```json
{
  "variant_id": "v-att-auth-1",
  "post_id": 123
}
```

### Response

```json
{
  "success": true,
  "variant_id": "v-att-auth-1",
  "approved_at": 1707148800,
  "approved_by": 1
}
```

### Idempotency

Approving an already-approved variant is safe and returns success.

---

## POST /variants/reject

Reject a variant and optionally provide a reason.

### Request Body

```json
{
  "variant_id": "v-att-auth-1",
  "post_id": 123,
  "reason": "Not aligned with brand voice"
}
```

### Response

```json
{
  "success": true,
  "variant_id": "v-att-auth-1",
  "rejected_at": 1707148800,
  "rejection_reason": "Not aligned with brand voice"
}
```

---

## POST /events

Record behavioral events for the Phase Engine.

### Request Body

```json
{
  "event_id": "user_clicked_linkedin_post",
  "metadata": {
    "post_id": 123,
    "variant_id": "v-att-auth-1",
    "timestamp": 1707148800
  },
  "user_id": 456
}
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `event_id` | string | Yes | Event identifier |
| `metadata` | object | No | Event metadata |
| `user_id` | integer | No | User ID (auto-detected if logged in) |

### Response

```json
{
  "success": true,
  "phase_transition": {
    "from": "Attention",
    "to": "Anxiety",
    "confidence": 0.87
  }
}
```

---

## Error Handling

### Standard Error Format

```json
{
  "code": "error_code",
  "message": "Human-readable error message",
  "data": {
    "status": 400
  }
}
```

### Common Error Codes

| Code | Status | Description |
|------|--------|-------------|
| `kh_smma_missing_post_id` | 400 | post_id parameter required |
| `kh_smma_invalid_phase` | 400 | Invalid phase_tag value |
| `kh_smma_compliance_blocked` | 403 | Content failed compliance check |
| `kh_smma_unauthorized` | 403 | Missing permissions |
| `kh_smma_feature_disabled` | 503 | Feature flag disabled |
| `kh_smma_llm_error` | 500 | LLM generation failed |

### Retry Logic

- **5xx errors**: Retry with exponential backoff (1s, 2s, 4s)
- **429 rate limit**: Wait for `Retry-After` header duration
- **4xx client errors**: Do not retry (fix request)

---

## Rate Limiting

### Limits

- **Generate**: 10 requests/minute per user
- **Compliance Check**: 30 requests/minute per user
- **Schedule**: 20 requests/minute per user

### Headers

```http
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 7
X-RateLimit-Reset: 1707148860
```

### 429 Response

```json
{
  "code": "rest_too_many_requests",
  "message": "Rate limit exceeded. Try again in 45 seconds.",
  "data": {
    "status": 429,
    "retry_after": 45
  }
}
```

---

## Telemetry & Monitoring

### Telemetry Data Logged

All endpoints log:
1. **Prompt/Response Hashes**: SHA256 hashes for deterministic tracking
2. **Model Usage**: Which LLM model was used
3. **Compliance Results**: OK/WARN/FAIL levels
4. **Performance Metrics**: Response time, token usage
5. **User Actions**: Approve/reject decisions

### Audit Trail

All generation and scheduling actions are logged to:
- WordPress custom table: `wp_kh_smma_audit_log`
- Fields: `user_id`, `action`, `object_type`, `object_id`, `details`, `created_at`

### Example Query

```php
global $wpdb;
$logs = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}kh_smma_audit_log
     WHERE object_type = %s AND object_id = %d
     ORDER BY created_at DESC LIMIT 10",
    'post',
    123
) );
```

---

## Examples

### Complete Generation Workflow

```javascript
// 1. Generate variants
const generateResponse = await fetch('/wp-json/kh-smma/v1/generate', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    post_id: 123,
    num_variants: 2,
    phase_tag: 'Attention',
    generate_google_ads: true
  })
});

const { variants, google_ad_draft } = await generateResponse.json();

// 2. Validate edited text (inline editing)
const editedText = variants[0].text + ' Learn more today!';
const complianceResponse = await fetch('/wp-json/kh-smma/v1/compliance/check', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    text: editedText,
    channel: 'linkedin'
  })
});

const { pass, level } = await complianceResponse.json();

if (level === 'FAIL') {
  alert('Content contains prohibited phrases');
  return;
}

// 3. Approve variant (if WARN)
if (level === 'WARN') {
  await fetch('/wp-json/kh-smma/v1/variants/approve', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
      variant_id: variants[0].variant_id,
      post_id: 123
    })
  });
}

// 4. Schedule for posting
const scheduleResponse = await fetch('/wp-json/kh-smma/v1/schedule', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    post_id: 123,
    selected_variants: [variants[0].variant_id],
    schedule: [
      {
        variant_id: variants[0].variant_id,
        scheduled_at: Math.floor(Date.now() / 1000) + 3600, // 1 hour from now
        geo: 'US',
        channel: 'linkedin'
      }
    ],
    boost: true
  })
});

const { created } = await scheduleResponse.json();
console.log('Scheduled:', created);
```

---

## Changelog

### v1.0.0 (2026-02-05)
- Initial release
- Added `/generate` endpoint with Google Ads support
- Added `/compliance/check` endpoint with OK/WARN/FAIL levels
- Added `/schedule` endpoint with compliance gates
- Added `/variants/approve` and `/variants/reject` endpoints
- Added Phase Engine `/events` endpoint
- Full telemetry and audit logging

---

## Support

- **Documentation**: `/docs/`
- **GitHub Issues**: https://github.com/KOldland/touchpoint-template/issues
- **Testing**: See [CI.md](CI.md) for test suite documentation
