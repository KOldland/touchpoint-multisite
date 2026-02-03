# Sponsor & Ad Manager API Documentation

## Overview

The Sponsorship & Advertising Foundation provides canonical sponsor records, asset libraries, approval workflows, and paid-ads plumbing for SMMA and Phase Engine.

**Key Features:**
- Sponsor CPT with full metadata (LinkedIn, assets, allowed claims, geo rules, budgets)
- REST API for sponsor lookup and asset retrieval
- Sponsor approval workflow with audit trail
- Manual export with sponsor metadata and recommended budgets
- Paid adapter contract with dry-run preview mode
- Budget and spend placeholder fields

---

## API Versioning & Stability

**API Version:** `v1` (Stable)

The `/wp-json/kh-ad-manager/v1/*` API is now **frozen as a stable contract** for SMMA and Phase Engine integration.

**Versioning Policy:**
- ✅ **Additive changes allowed**: New fields, new optional parameters, new endpoints
- ❌ **Breaking changes prohibited**: Removing fields, changing field types, renaming endpoints
- 🔄 **Breaking changes require `/v2`**: Any non-additive changes must be released under a new version namespace

**Stability Guarantees:**
- Response schemas will remain backward-compatible
- Field names, types, and structures are locked
- Deprecation warnings will precede any field removal (minimum 6 months notice)
- Critical bug fixes may adjust validation logic but not response format

**Consumer Expectations:**
- SMMA and Phase Engine can safely cache API response structures
- Integration tests will remain valid across minor releases
- Schema changes will be documented in CHANGELOG.md

---

## Sponsor Lookup API

### `GET /wp-json/kh-ad-manager/v1/sponsor/{id}`

Returns full sponsor metadata including allowed_claims, co_brand_policy, assets, budgets, and contact info.

**Request:**
```http
GET /wp-json/kh-ad-manager/v1/sponsor/123
```

**Response:**
```json
{
  "sponsor_id": 123,
  "name": "Acme Corp",
  "allowed_claims": [
    {
      "claim": "Does X 95% faster",
      "version": 1,
      "approved_at": 1706820000,
      "approved_by": 456
    },
    {
      "claim": "Reduces Y costs by 50%",
      "version": 1,
      "approved_at": 1706820000,
      "approved_by": 456
    }
  ],
  "co_brand_policy": "co-brand",
  "assets": [
    {
      "id": 456,
      "url": "https://example.com/acme-logo.png",
      "thumb": "https://example.com/acme-logo-thumb.png",
      "alt": "Acme Corp Logo",
      "type": "image/png",
      "metadata": {
        "width": 800,
        "height": 600
      }
    }
  ],
  "ppc_budget": {
    "total": 10000,
    "daily_cap": 100
  },
  "geo_rules": {
    "GB": {
      "policy": "co-brand",
      "budget_cap": 500
    },
    "US": {
      "policy": "sponsor-only",
      "budget_cap": 2000
    }
  },
  "approval_contact": {
    "name": "Jane Doe",
    "email": "jane@acme.com",
    "role": "Marketing Manager"
  },
  "linkedin_page": "https://www.linkedin.com/company/acme-corp",
  "linkedin_handles": ["acme", "acme-corp"],
  "content_library": "https://assets.acme.com",
  "ppc_account_id": "linkedin-ads-account-789"
}
```

**Status Codes:**
- `200 OK` - Sponsor found
- `400 Bad Request` - Missing sponsor ID
- `404 Not Found` - Sponsor not found

---

### `GET /wp-json/kh-ad-manager/v1/sponsor/{id}/assets`

Returns sponsor asset library with URLs, thumbnails, alt text, metadata.

**Request:**
```http
GET /wp-json/kh-ad-manager/v1/sponsor/123/assets
```

**Response:**
```json
{
  "sponsor_id": 123,
  "assets": [
    {
      "id": 456,
      "url": "https://example.com/acme-logo.png",
      "thumb": "https://example.com/acme-logo-thumb.png",
      "alt": "Acme Corp Logo",
      "type": "image/png",
      "metadata": { "width": 800, "height": 600 }
    },
    {
      "id": 457,
      "url": "https://example.com/acme-video.mp4",
      "thumb": "https://example.com/acme-video-thumb.png",
      "alt": "Acme Product Demo",
      "type": "video/mp4",
      "metadata": { "duration": 30 }
    }
  ],
  "count": 2
}
```

---

### `GET /wp-json/kh-ad-manager/v1/sponsor/{id}/budget`

Returns current and historical budget info including spend tracking.

**Request:**
```http
GET /wp-json/kh-ad-manager/v1/sponsor/123/budget
```

**Response:**
```json
{
  "sponsor_id": 123,
  "budget_total": 10000,
  "budget_daily": 100,
  "spend": {
    "total": 2500,
    "today": 45,
    "last_updated": 1706820000
  },
  "remaining": {
    "total": 7500,
    "daily": 55
  }
}
```

---

### `GET /wp-json/kh-ad-manager/v1/sponsor/{id}/geo-rules`

Returns geo-specific sponsor rules keyed by country code.

**Request:**
```http
GET /wp-json/kh-ad-manager/v1/sponsor/123/geo-rules
```

**Response:**
```json
{
  "sponsor_id": 123,
  "geo_rules": {
    "GB": {
      "policy": "co-brand",
      "asset_id": 456,
      "budget_cap": 500
    },
    "US": {
      "policy": "sponsor-only",
      "budget_cap": 2000
    },
    "DE": {
      "policy": "white-label"
    }
  }
}
```

---

## Sponsor Approval API

**Approval Semantics:**

Sponsor approval is **distinct from editorial approval** and operates as an independent workflow:

- **Sponsor Approval** → Enables paid amplification and verifies brand policy compliance
- **Editorial Approval** → Validates content quality, accuracy, and editorial policy compliance

⚠️ **Critical:** Sponsor approval **does not override editorial policy**. Both approvals are required before dispatch to paid adapters. A schedule must pass editorial review first, then receive sponsor approval for budget authorization.

**Dual Approval Flow:**
1. Editorial team approves variant (SMMA approval workflow)
2. Sponsor approves variant for paid amplification (Sponsor approval workflow)
3. Only when both are "approved" → Schedule dispatches to paid adapters

---

### `POST /wp-json/kh-ad-manager/v1/sponsor-approve`

Record sponsor approval or rejection for ad variants.

**Request:**
```http
POST /wp-json/kh-ad-manager/v1/sponsor-approve
Content-Type: application/json

{
  "schedule_id": 987,
  "sponsor_id": 123,
  "approver_id": 456,
  "decision": "approved",
  "notes": "Approved as-is. Complies with brand guidelines."
}
```

**Request Fields:**
- `schedule_id` (int, required) - SMMA schedule ID
- `sponsor_id` (int, optional) - Sponsor ID (will lookup from schedule if omitted)
- `approver_id` (int, optional) - User ID of approver (defaults to current user)
- `decision` (string, required) - "approved" or "rejected"
- `notes` (string, optional) - Approval notes for audit trail

**Response:**
```json
{
  "success": true,
  "schedule_id": 987,
  "sponsor_id": 123,
  "decision": "approved",
  "approved_at": 1706820000,
  "approver_id": 456
}
```

**Stored Metadata:**
- `_kh_smma_sponsor_approval_status` - "approved" or "rejected"
- `_kh_smma_sponsor_approved_by` - Approver user ID
- `_kh_smma_sponsor_approved_at` - Timestamp
- `_kh_smma_sponsor_approval_notes` - Notes

---

### `GET /wp-json/kh-ad-manager/v1/sponsor-approvals/pending`

Returns list of schedules pending sponsor approval.

**Request:**
```http
GET /wp-json/kh-ad-manager/v1/sponsor-approvals/pending?paged=1
```

**Response:**
```json
{
  "pending": [
    {
      "schedule_id": 987,
      "title": "SMMA Schedule – 654",
      "sponsor_id": 123,
      "approval_status": "pending",
      "variant_text": "Check out our new product launch...",
      "scheduled_at": 1706820000,
      "created_at": "2024-02-02T10:00:00Z"
    }
  ],
  "total": 1,
  "total_pages": 1
}
```

---

### `GET /wp-json/kh-ad-manager/v1/sponsor-approvals/{schedule_id}`

Returns approval status and metadata for a specific schedule.

**Request:**
```http
GET /wp-json/kh-ad-manager/v1/sponsor-approvals/987
```

**Response:**
```json
{
  "schedule_id": 987,
  "sponsor_id": 123,
  "approval_status": "approved",
  "approved_by": 456,
  "approved_at": 1706820000,
  "approval_notes": "Approved as-is. Complies with brand guidelines.",
  "smma_approval_status": "pending",
  "variant_text": "Check out our new product launch..."
}
```

---

## Manual Export Manifest

When SMMA requests a manual export, the manifest includes sponsor metadata:

**Manifest Structure:**
```json
{
  "schedule_id": 987,
  "sponsor_id": 123,
  "account_id": 456,
  "variants": [
    {
      "variant_id": "v-1",
      "text": "Check out our new product launch...",
      "asset_ids": [456, 457]
    }
  ],
  "recommended_budget": {
    "platform": "LinkedIn",
    "daily": 100,
    "total": 1000
  },
  "sponsor_metadata": {
    "name": "Acme Corp",
    "allowed_claims": ["Does X 95% faster"],
    "co_brand_policy": "co-brand",
    "assets": [
      {
        "id": 456,
        "url": "https://example.com/logo.png",
        "type": "image/png"
      }
    ],
    "ppc_account_id": "linkedin-ads-account-789"
  },
  "generated": 1706820000
}
```

---

## Paid Adapter Contract

All paid adapters (LinkedIn Ads, Google Ads, Meta Ads) must implement `PaidAdapterContract`.

### Contract Interface

```php
namespace KH_SMMA\Adapters;

abstract class PaidAdapterContract {
    
    /**
     * Dry-run mode: returns operation sequence without executing.
     * 
     * @param array $schedule_payload Schedule payload with variants, assets, targeting
     * @return array Array of operations with op_type, payload_preview, estimated_spend
     */
    abstract public function dry_run( array $schedule_payload );

    /**
     * Execute mode: performs the actual operations.
     * 
     * @param array $schedule_payload Schedule payload
     * @return array Result with operation_id, status, response
     */
    abstract public function execute( array $schedule_payload );

    /**
     * Get adapter metadata: name, version, capabilities.
     * 
     * @return array
     */
    abstract public function get_metadata();
}
```

### Dry-Run Response Format

**Operation Structure:**

Each operation returned by `dry_run()` contains:
- `op_type` - The operation identifier (e.g., "create_campaign", "create_creative")
- `payload_preview` - Summary of what would be sent to the API (redacted sensitive data)
- `estimated_spend` - Calculated expected spend in USD
- `policy_warnings` - Array of policy/budget warnings (optional)
- `requires_review` - Boolean indicating if manual review is needed (optional)

**Example: LinkedIn Ads Dry-Run Response**

```json
{
  "success": true,
  "adapter": "LinkedIn Ads",
  "operations": [
    {
      "op_type": "create_campaign",
      "payload_preview": {
        "name": "SMMA-Schedule-12345-LinkedIn",
        "account_id": "urn:li:sponsoredAccount:987654321",
        "campaign_type": "SPONSORED_UPDATES",
        "budget_daily": 100.00,
        "start_date": "2026-02-03T00:00:00Z",
        "end_date": "2026-02-10T23:59:59Z",
        "duration_days": 7
      },
      "estimated_spend": 700.00,
      "policy_warnings": [],
      "requires_review": false
    },
    {
      "op_type": "create_creative",
      "payload_preview": {
        "text": "Discover how AI-powered analytics can transform your business. 📊 Learn more about our enterprise solution.",
        "media_count": 2,
        "media_types": ["image", "video"],
        "asset_ids": [456, 789],
        "call_to_action": "LEARN_MORE",
        "landing_page": "https://example.com/landing"
      },
      "estimated_spend": 0.00,
      "policy_warnings": [],
      "requires_review": false
    },
    {
      "op_type": "associate_audience",
      "payload_preview": {
        "targeting_type": "AUDIENCE",
        "audience_id": "urn:li:audienceSegment:12345678",
        "audience_size_estimate": "50,000-100,000",
        "targeting_criteria": {
          "job_functions": ["Engineering", "IT"],
          "seniority": ["Director", "VP"],
          "geo": ["US", "GB", "CA"]
        }
      },
      "estimated_spend": 0.00,
      "policy_warnings": [],
      "requires_review": false
    }
  ],
  "total_estimated_spend": 700.00,
  "schedule_id": 12345,
  "dry_run": true,
  "timestamp": 1706889600
}
```

**Example: Google Ads Dry-Run with Policy Warnings**

```json
{
  "success": true,
  "adapter": "Google Ads",
  "operations": [
    {
      "op_type": "create_campaign",
      "payload_preview": {
        "name": "SMMA-Schedule-12346-GoogleAds",
        "customer_id": "123-456-7890",
        "campaign_type": "SEARCH",
        "budget_daily": 250.00,
        "start_date": "2026-02-03",
        "end_date": "2026-02-17",
        "duration_days": 14
      },
      "estimated_spend": 3500.00,
      "policy_warnings": [
        "Large budget: $3,500 exceeds recommended monthly limit of $2,000.",
        "Long duration: 14 days may require additional sponsor approval."
      ],
      "requires_review": true
    },
    {
      "op_type": "create_ad_group",
      "payload_preview": {
        "name": "Ad Group - Enterprise Analytics",
        "cpc_bid": 2.50,
        "targeting": {
          "keywords_count": 15,
          "negative_keywords_count": 8,
          "geo_targets": ["US", "GB"]
        }
      },
      "estimated_spend": 0.00,
      "policy_warnings": [],
      "requires_review": false
    },
    {
      "op_type": "create_text_ads",
      "payload_preview": {
        "ad_count": 3,
        "headlines": [
          "AI Analytics Platform",
          "Transform Your Data",
          "Enterprise BI Solution"
        ],
        "descriptions": [
          "Powerful insights in real-time. Try free for 30 days.",
          "Join 10,000+ companies using our platform."
        ]
      },
      "estimated_spend": 0.00,
      "policy_warnings": [],
      "requires_review": false
    },
    {
      "op_type": "add_keywords",
      "payload_preview": {
        "keywords": [
          "enterprise analytics platform",
          "business intelligence software",
          "data visualization tool"
        ],
        "match_types": ["BROAD", "PHRASE", "EXACT"]
      },
      "estimated_spend": 0.00,
      "policy_warnings": [],
      "requires_review": false
    }
  ],
  "total_estimated_spend": 3500.00,
  "schedule_id": 12346,
  "dry_run": true,
  "timestamp": 1706889600
}
```

**Policy Warning Examples:**
- Budget warnings: "Large budget: $X exceeds recommended limit."
- Duration warnings: "Long duration: N days may require additional approval."
- Compliance warnings: "Claim 'Does X' not found in sponsor allowed_claims."
- Asset warnings: "Asset ID 123 not found in sponsor approved assets."

### Adapter Dispatch Flow

1. **SMMA calls** `kh_smma_dispatch_schedule` filter
2. **Adapter checks** dry-run flag in `_kh_smma_boost_settings`
3. **If dry-run**: Call `dry_run()` → return operation previews
4. **If execute**: Call `execute()` → return operation results or WP_Error
5. **Store** `_kh_smma_adapter_operations` meta
6. **Log** telemetry with operations, estimated spend, warnings

---

## Sponsor CPT Fields

### Profile Fields

| Field | Type | Description |
|-------|------|-------------|
| `linkedin_page_url` | URL | Official LinkedIn company page |
| `linkedin_handles` | Array | Company handles for mention matching |
| `quotable_representatives` | Array | Name + title of approved speakers |
| `content_library_url` | URL | Link to sponsor's asset repository |

### Policy Fields

| Field | Type | Description |
|-------|------|-------------|
| `allowed_claims` | Array | **Structured** approved marketing claims (see schema below) |
| `co_brand_policy` | String | "co-brand" \| "sponsor-only" \| "white-label" |
| `sponsor_assets` | Array | Approved logos, creatives, captions |

#### `allowed_claims` Schema (Locked)

⚠️ **Schema-Validated**: This field is **locked to prevent free-text additions**. All claims must follow this structure:

```json
[
  {
    "claim": "Does X 95% faster",
    "version": 1,
    "approved_at": 1706820000,
    "approved_by": 456
  }
]
```

**Required Fields:**
- `claim` (string, required) - The exact marketing claim text
- `version` (integer, required) - Claim version number (starts at 1)
- `approved_at` (integer, auto-set) - Unix timestamp when claim was approved
- `approved_by` (integer, auto-set) - User ID of approver

**Validation Rules:**
- ❌ Free-text strings are rejected
- ❌ Claims without `claim` or `version` fields are rejected  
- ✅ Version must be >= 1
- ✅ Claim text must be non-empty after sanitization
- ✅ `approved_at` defaults to current time if not provided
- ✅ `approved_by` defaults to current user if not provided

**Versioning:** To update a claim, increment `version` and update `approved_at`/`approved_by`. This maintains an audit trail.

### Budget Fields

| Field | Type | Description |
|-------|------|-------------|
| `ppc_budget_total` | Float | Total PPC budget cap |
| `ppc_daily_cap` | Float | Daily spend cap |
| `ppc_account_id` | String | LinkedIn/Google Ads account ID |
| `spend_tracking` | Object | {total_spent, today_spent, last_updated} |

### Geo Rules

```php
'geo_rules' => [
    'GB' => [
        'policy' => 'co-brand',
        'asset_id' => 456,
        'budget_cap' => 500
    ],
    'US' => [
        'policy' => 'sponsor-only'
    ]
]
```

### Approval Contact

```php
'approval_contact' => [
    'name' => 'Jane Doe',
    'email' => 'jane@acme.com',
    'role' => 'Marketing Manager'
]
```

---

## Integration Points

### SMMA Schedule Metadata

When a schedule has sponsor approval requirements, SMMA stores:

- `_kh_smma_sponsor_id` - Sponsor CPT ID
- `_kh_smma_sponsor_approval_status` - "pending" \| "approved" \| "rejected"
- `_kh_smma_sponsor_approved_by` - User ID of sponsor approver
- `_kh_smma_sponsor_approved_at` - Timestamp
- `_kh_smma_sponsor_approval_notes` - Approval notes

### Phase Engine Integration

Phase Engine can use `GET /sponsor/{id}/geo-rules` to:
- Determine sponsor policy for a given geo
- Look up geo-specific assets
- Apply budget caps per region

### Editorial Planner Integration

Boost Visibility Pending Approvals panel shows:
- Schedules with `_kh_smma_approval_status = 'pending'`
- Sponsor info (name, approval contact)
- Variant text preview
- Approve/Reject buttons → sponsor-approve API

---

## Error Handling

### Standard Error Response

```json
{
  "code": "kh_ad_manager_missing_schedule",
  "message": "schedule_id is required.",
  "data": {
    "status": 400
  }
}
```

### Common Errors

| Error | Cause | Resolution |
|-------|-------|-----------|
| `missing_schedule` | No schedule_id in request | Include schedule_id |
| `schedule_not_found` | Invalid schedule ID | Verify schedule exists |
| `sponsor_not_found` | Invalid sponsor ID | Verify sponsor exists |
| `invalid_decision` | Decision not "approved"/"rejected" | Use valid decision value |

---

## Testing Guide

### Unit Tests: Sponsor CRUD

```bash
# Create sponsor
POST /wp-json/wp/v2/sponsors
{
  "title": "Acme Corp",
  "meta": {
    "linkedin_page_url": "https://...",
    "allowed_claims": ["Claim 1"],
    "ppc_budget_total": 10000
  }
}

# Read sponsor
GET /wp-json/kh-ad-manager/v1/sponsor/123

# Update sponsor
POST /wp-json/wp/v2/sponsors/123
{
  "meta": {
    "ppc_budget_total": 15000
  }
}

# Delete sponsor
DELETE /wp-json/wp/v2/sponsors/123
```

### Integration Test: Generate + Approve + Export

1. **Create sponsor** with allowed_claims + assets
2. **Create schedule** with sponsor_id
3. **Call sponsor-approve** → sets approval status
4. **Request manual export** → manifest includes sponsor metadata
5. **Verify** export bundle contains recommended_budget + sponsor assets

### Adapter Test: Dry-Run

```php
$adapter = new LinkedInAdsAdapter($tokens, $flags);

$payload = [
    'variant_id' => 'v-1',
    'text' => 'Check out our product...',
    'targeting' => ['audience_id' => 123],
    'budget_daily' => 100
];

$operations = $adapter->dry_run($payload);

// Assert: operations array with create_campaign, create_creative, associate_audience
// Assert: operations have op_type, payload_preview, estimated_spend
// Assert: no API calls made
```

---

## FAQ

**Q: Can sponsors approve variants before editorial approval?**
A: Yes, sponsor approval is independent. SMMA has separate editorial approval. Both must pass before dispatch.

**Q: What happens if sponsor budget is exceeded?**
A: Adapters will auto-pause campaigns or reject new dispatches. Spend placeholder API returns remaining budget.

**Q: Can geo rules override global policy?**
A: Yes, geo-specific rules take precedence if defined.

**Q: Are dry-run operations logged?**
A: Yes, dry-run results are logged in telemetry but don't update spend or create real campaigns.

---

**Version:** 1.0.0  
**Last Updated:** February 2, 2026
