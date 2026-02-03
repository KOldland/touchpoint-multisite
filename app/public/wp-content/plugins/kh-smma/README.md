# KH Social Media Management & Automation (KH-SMMA)

**Version:** 0.1.0
**Status:** ✅ Backend Complete | ✅ Frontend Complete

## Overview

KH-SMMA is a comprehensive WordPress plugin that provides AI-powered social media promotion planning, variant generation, and approval workflows. It integrates with the 4A Phase Model (Attention → Antagonistic → Anxiety → Acceptance) to create phase-aware promotional content optimized for LinkedIn and other social platforms.

### Key Features

- **Phase-Aware Variant Generation**: Automatically generates promotional variants optimized for user's current buyer journey phase
- **AI-Powered Content Creation**: Uses LLM (via Dual_GPT) to create LinkedIn-native promotional text
- **Compliance Validation**: Two-tier validation (rule-based + AI) ensures content meets sponsor policies and regulatory guidelines
- **Approval Workflows**: Automated approval for organic content, manual approval for sponsored content
- **GEO Targeting**: Geographic-specific scheduling and sponsor policy enforcement
- **Batch Scheduling**: Schedule multiple variants with per-variant timing and targeting
- **Inline Editing**: Edit variant text with live compliance re-checking
- **Audit Logging**: Complete audit trail of all operations

## Architecture

### Backend Components

#### 1. REST API Endpoints ([RestController.php](src/API/RestController.php))

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/kh-smma/v1/generate` | POST | Generate promotional variants |
| `/kh-smma/v1/schedule` | POST | Schedule variants for publishing |
| `/kh-smma/v1/variant-edit` | POST | Edit variant text with compliance check |
| `/kh-smma/v1/approve` | POST | Approve variant for publishing |
| `/kh-smma/v1/reject` | POST | Reject variant with reason |
| `/kh-smma/v1/sponsor/{id}` | GET | Get sponsor metadata |
| `/kh-smma/v1/seo-table` | GET | Get SEO data for posts |

#### 2. Services

**SmmaGenerator** ([SmmaGenerator.php](src/Services/SmmaGenerator.php))
- Orchestrates variant generation flow
- Hydrates input data (blocks, keywords, sponsor context, phase)
- Calls LLM API for AI-generated content
- Applies compliance validation
- Generates fallback variants when AI unavailable

**ComplianceValidator** ([ComplianceValidator.php](src/Services/ComplianceValidator.php))
- Two-tier validation: rule-based (fast) + AI-powered (nuanced)
- Blacklist checking (8 prohibited phrases)
- Channel-specific length limits
- Sponsor claim validation
- Confidence scoring (0.0-1.0)
- Batch validation support

**PhaseEngine** ([PhaseEngine.php](src/Services/PhaseEngine.php))
- Tracks user events and behavior
- Assigns 4A phase based on signal aggregation
- Provides top 3 phase signals for transparency
- REST endpoint: `GET /kh-smma/v1/user-phase`

**AuditLogger** ([AuditLogger.php](src/Services/AuditLogger.php))
- Logs all operations to custom database table
- Tracks user actions, object changes, details
- Used for compliance reporting and debugging

#### 3. Data Model

**Custom Post Type: `kh_smma_schedule`**

Post meta keys:
- `_kh_smma_payload` - Variant data (text, channel, geo, etc.)
- `_kh_smma_scheduled_at` - Unix timestamp for publishing
- `_kh_smma_schedule_status` - Status: pending, awaiting_approval, rejected
- `_kh_smma_approval_status` - Approval: pending, auto_approved, approved, rejected
- `_kh_smma_approval_required` - Boolean flag
- `_kh_smma_sponsor_id` - Sponsor post ID (if applicable)
- `_kh_smma_sponsor_mode` - Policy: co-brand, ghost, etc.
- `_kh_smma_compliance_notes` - Validation results
- `_kh_smma_boost_mode` - Boost channel: none, linkedin, google_ads
- `_kh_smma_approved_by` - User ID or 'system'
- `_kh_smma_approved_at` - Unix timestamp
- `_kh_smma_rejected_by` - User ID
- `_kh_smma_rejected_at` - Unix timestamp
- `_kh_smma_rejection_reason` - Text reason

### Frontend Components

#### 1. Boost Visibility Table ([AdminManager.php](../khm-seo/src/Admin/AdminManager.php))

**Enhanced Columns:**
- **Phase**: Colored badge (Attention=blue, Antagonistic=orange, Anxiety=red, Acceptance=green)
  - Tooltip shows top 3 phase signals
- **Actions**: Promote, Boost, Pending buttons with data attributes for modals

**Pending Approvals Panel:**
- Variant text preview (truncated)
- Phase badge
- Compliance status (OK/WARN/FAIL)
- Scheduled time
- Approve/Edit/Reject actions

#### 2. JavaScript UI ([smma-admin.js](assets/js/smma-admin.js))

**API Client:**
```javascript
SMMA_API.generate(postId, options)      // Generate variants
SMMA_API.schedule(postId, items, options) // Schedule variants
SMMA_API.editVariant(scheduleId, text)   // Edit with compliance
SMMA_API.approve(scheduleId)             // Approve
SMMA_API.reject(scheduleId, reason)      // Reject
```

**Modal Components:**

**PromoteModal**
- Form: num_variants, tone, geo_targets, series
- Variant grid with phase/compliance badges
- Expandable details (rationale, time window, GEO)
- Checkbox selection for scheduling

**CalendarModal**
- Per-variant datetime inputs (staggered by default)
- GEO target overrides
- LinkedIn boost toggle
- Batch scheduling confirmation

**EditVariantModal**
- Textarea editor for variant text
- Live compliance validation on save
- Visual feedback (success/failure)

**RejectModal**
- Textarea for rejection reason
- Confirmation with audit logging

#### 3. Styling ([smma-admin.css](assets/css/smma-admin.css))

- Modal system with fadeIn/slideDown animations
- Phase badges with hover states
- Compliance badges (color-coded)
- Variant cards with shadows
- Responsive design (mobile-friendly)
- Loading states and spinners

## User Workflows

### 1. Generate & Schedule Workflow

```
User clicks "Promote" button on post
    ↓
PromoteModal opens
    ↓
User configures: num_variants, tone, geo_targets, series
    ↓
Click "Generate Variants"
    ↓
POST /generate → SmmaGenerator → LLM → Variants
    ↓
Variant Grid displays with phase/compliance badges
    ↓
User selects desired variants
    ↓
Click "Schedule Selected Variants"
    ↓
CalendarModal opens
    ↓
User sets times, GEO targets, boost toggle
    ↓
Click "Confirm & Schedule"
    ↓
POST /schedule → Creates kh_smma_schedule posts
    ↓
Auto-approval (no sponsor) OR awaits approval (sponsor)
    ↓
Pending Approvals panel shows awaiting items
```

### 2. Approval Workflow

```
Pending Approvals panel displays variants
    ↓
User reviews: text, phase, compliance, sponsor
    ↓
Three options:
    ├─ Approve: POST /approve → approved status
    ├─ Edit: Opens EditVariantModal
    │    ↓
    │   Edit text → POST /variant-edit
    │    ↓
    │   Compliance re-check → Success/Failure
    │    ↓
    │   If passed: Updated, reload page
    └─ Reject: Opens RejectModal
         ↓
        Enter reason → POST /reject
         ↓
        Rejected status, logged
```

### 3. Phase-Aware Generation

```
User at Phase: Anxiety
    ↓
User clicks "Promote" on Post #123
    ↓
PhaseEngine.get_user_phase(user_id)
    ↓
Returns: { assigned_phase: 'Anxiety', top_signals: [...] }
    ↓
SmmaGenerator receives phase_tag='Anxiety'
    ↓
LLM system prompt: "Optimize for Anxiety phase..."
    ↓
Generated variants emphasize solutions, risk mitigation
    ↓
Variants include phase_tag='Anxiety' in response
    ↓
UI displays red Anxiety badge
```

## Configuration

### Feature Flags

Enable/disable SMMA via FeatureFlags service:
```php
$flags = new \KH_SMMA\Services\FeatureFlags();
$flags->is_enabled('smma'); // Check if enabled
```

### Blacklist Configuration

Edit blacklist in [ComplianceValidator.php](src/Services/ComplianceValidator.php):
```php
private $blacklist = array(
    'guaranteed results',
    'guarantee results',
    'risk-free',
    '100% guaranteed',
    'no risk',
    'get rich quick',
    'miracle cure',
    'instant results',
);
```

### Channel Length Limits

Configured in ComplianceValidator::get_channel_max_length():
- LinkedIn: 3000 characters
- Twitter: 280 characters
- Facebook: 63,206 characters
- Google Ads: 90 characters (description)
- Instagram: 2200 characters

### Sponsor Integration

Requires `kh-ad-manager` plugin with function:
```php
kh_ad_manager_get_sponsor_meta( int $sponsor_id ): array
```

Returns:
```php
array(
    'name' => 'Sponsor Name',
    'allowed_claims' => array( 'leading solution', 'trusted partner' ),
    'approval_contact' => 'email@example.com',
    'sponsor_assets' => array( ... ),
)
```

## API Examples

### Generate Variants

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${NONCE}" \
  --cookie cookies.txt \
  -d '{
    "post_id": 123,
    "num_variants": 3,
    "phase_tag": "Attention",
    "tone": "Authority",
    "geo_targets": ["US-East", "UK"],
    "series": false
  }' \
  http://localhost/wp-json/kh-smma/v1/generate
```

Response:
```json
{
  "variants": [
    {
      "variant_id": "v-abc-123",
      "channel": "linkedin",
      "text": "Discover proven strategies...",
      "phase_tag": "Attention",
      "tone": "Authority",
      "compliance_notes": "OK: All compliance checks passed",
      "recommended_post_time_gmt": 1675209600,
      "geo_recommendations": [
        { "geo": "US-East", "time_window": "08:30-10:00 GMT" }
      ],
      "sponsor_flag": false,
      "explainability": "Aligned with phase and tone requirements."
    }
  ]
}
```

### Schedule Variants

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${NONCE}" \
  --cookie cookies.txt \
  -d '{
    "post_id": 123,
    "schedule": [
      {
        "variant_id": "v-abc-123",
        "scheduled_at": 1675209600,
        "geo": "US-East",
        "text": "Variant text here..."
      }
    ],
    "boost": false,
    "sponsor_context": {
      "sponsor_id": 0,
      "policy": "",
      "approval_required": false
    }
  }' \
  http://localhost/wp-json/kh-smma/v1/schedule
```

Response:
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

### Edit Variant

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ${NONCE}" \
  --cookie cookies.txt \
  -d '{
    "schedule_id": 456,
    "updated_text": "Updated variant text with improvements..."
  }' \
  http://localhost/wp-json/kh-smma/v1/variant-edit
```

Response:
```json
{
  "status": "updated",
  "compliance": {
    "passed": true,
    "message": "",
    "notes": "OK: All compliance checks passed",
    "confidence_score": 0.95,
    "details": {
      "rule_check": { "passed": true, "notes": "OK: rule-based checks passed" },
      "ai_check": { "passed": true, "notes": "OK: AI compliance passed" }
    }
  }
}
```

## Testing

### Manual Testing

See [tests/api-endpoint-tests.md](tests/api-endpoint-tests.md) for:
- Endpoint test cases
- cURL examples
- Expected responses
- Integration test workflows

### Unit Testing (Future)

Recommended test coverage:
- ComplianceValidator::validate() with various inputs
- SmmaGenerator::generate() with fallback scenarios
- PhaseEngine::get_user_phase() with mock events
- RestController endpoints with invalid inputs

### Testing Checklist

- [ ] Generate variants for post with phase assignment
- [ ] Schedule variants with/without approval required
- [ ] Edit variant text (pass compliance)
- [ ] Edit variant text (fail compliance - blacklist)
- [ ] Edit variant text (fail compliance - missing claim)
- [ ] Approve pending variant
- [ ] Reject pending variant with reason
- [ ] Verify audit log entries for all operations
- [ ] Test responsive UI on mobile devices
- [ ] Test modal interactions (open, close, submit)
- [ ] Test with LLM API unavailable (fallback variants)

## Database Schema

### Custom Tables

**kh_smma_phase_events** (via PhaseEngine)
- event_id (bigint, primary key)
- user_id (bigint)
- event_type (varchar)
- event_data (text, JSON)
- created_at (datetime)

**kh_smma_audit_log** (via AuditLogger)
- id (bigint, primary key)
- action (varchar)
- object_type (varchar)
- object_id (bigint)
- user_id (bigint)
- details (text, JSON)
- created_at (datetime)

## Dependencies

### Required
- WordPress 5.8+
- PHP 7.4+
- jQuery (WordPress core)

### Optional
- `Dual_GPT\Dual_GPT_LLM_Client` - For AI-powered variant generation (fallback if unavailable)
- `kh-ad-manager` - For sponsor metadata and policies
- `khm-seo` - For Boost Visibility UI integration

## Installation

1. Upload plugin to `/wp-content/plugins/kh-smma/`
2. Activate plugin via WordPress admin
3. Ensure `smma` feature flag is enabled
4. Configure Dual_GPT API key (if using AI generation)
5. Navigate to **SEO → Boost Visibility** to access UI

## Troubleshooting

### Variants not generating
- Check Dual_GPT API key configuration
- Verify feature flag `smma` is enabled
- Check PHP error logs for exceptions
- Fallback variants should generate even without API

### Compliance validation failing
- Review blacklist phrases in ComplianceValidator
- Check sponsor allowed_claims configuration
- Verify text length within channel limits
- Check browser console for JavaScript errors

### Modals not opening
- Verify JavaScript is enqueued on Boost Visibility page
- Check browser console for JavaScript errors
- Ensure jQuery is loaded
- Verify REST API nonce is valid

### Pending approvals not showing
- Verify `kh_smma_schedule` custom post type registered
- Check meta query for `_kh_smma_approval_status=pending`
- Ensure scheduled variants have approval_required=true

## Roadmap

### Short Term (v0.2.0)
- [ ] Export variants to CSV/JSON
- [ ] Multi-channel support (Twitter, Facebook, Instagram)
- [ ] Template library for common promotional patterns
- [ ] A/B testing for variant performance

### Medium Term (v0.3.0)
- [ ] Automated publishing (OAuth integration)
- [ ] Analytics integration (track engagement metrics)
- [ ] Variant performance scoring
- [ ] Automated re-generation based on performance

### Long Term (v1.0.0)
- [ ] Machine learning for optimal posting times
- [ ] Sentiment analysis for tone optimization
- [ ] Competitive analysis integration
- [ ] Multi-language support

## Support

For issues, questions, or feature requests:
- GitHub: [Create an issue](https://github.com/kh-marketing-suite/kh-smma/issues)
- Documentation: See `/docs` directory
- Email: support@kh-marketing.com

## License

Proprietary - KH Marketing Suite
Copyright © 2025 KH Marketing Suite. All rights reserved.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and release notes.
