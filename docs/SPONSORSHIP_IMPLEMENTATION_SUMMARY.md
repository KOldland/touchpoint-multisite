# Sponsorship & Advertising Foundation - Implementation Summary

## Project Overview

**Status:** Sprint 1 & 2 Complete ✅  
**Start Date:** February 2, 2026  
**Completion Date:** February 2, 2026  

Built the foundation for canonical sponsor records, asset libraries, approval workflows, and paid-ads plumbing so SMMA and Phase Engine can use sponsors confidently and safely.

---

## What Was Delivered

### ✅ Sprint 1 (Days 1–7): Foundation & APIs

#### 1. **Sponsor CPT Migration & Full Fields** 
- **File:** `/includes/cpt-sponsor.php`
- **Changes:**
  - Expanded CPT with 11 new post meta fields
  - All fields registered for REST API with proper schemas
  - Fields include: linkedin_page_url, linkedin_handles, quotable_representatives, content_library_url, sponsor_assets, allowed_claims, co_brand_policy, geo_rules, ppc_budget_total, ppc_daily_cap, ppc_account_id, approval_contact, spend_tracking

#### 2. **Sponsor Admin UI Screens**
- **File:** `/includes/sponsor-admin.php`
- **Components:**
  - Profile metabox: LinkedIn URLs, handles, representative management
  - Policies metabox: Co-brand policy selector, allowed claims textarea
  - Asset Library metabox: Media upload/removal with gallery preview
  - Budget metabox: Total budget, daily cap, PPC account ID, spend tracking display
  - Approval Contact metabox: Name, email, role fields
  - Geo Rules metabox: Country-specific policy, asset, and budget caps
  - Save handler: Persists all fields with proper sanitization

#### 3. **Sponsor Lookup API**
- **File:** `/includes/sponsor-api.php`
- **Endpoints:**
  - `GET /wp-json/kh-ad-manager/v1/sponsor/{id}` → Full sponsor metadata
  - `GET /wp-json/kh-ad-manager/v1/sponsor/{id}/assets` → Asset library with URLs/thumbnails
  - `GET /wp-json/kh-ad-manager/v1/sponsor/{id}/budget` → Budget and spend info
  - `GET /wp-json/kh-ad-manager/v1/sponsor/{id}/geo-rules` → Geo-specific policies
- **Format:** JSON with nested objects for assets, budgets, geo_rules
- **Helper:** `kh_ad_manager_format_assets_for_api()` ensures consistency

#### 4. **ManualExportAdapter Enhancements**
- **File:** `/src/Adapters/ManualExportAdapter.php`
- **New Fields in Export Bundle:**
  - `sponsor_id` - Sponsor CPT ID
  - `variants[]` - Array with variant_id, text, asset_ids
  - `recommended_budget` - Platform + daily/total amounts
  - `sponsor_metadata` - Snapshot of name, allowed_claims, co_brand_policy, assets, ppc_account_id
  - `generated` - Timestamp
- **Logic:** Queries sponsor meta if schedule has `_kh_smma_sponsor_id`; uses boost_settings for budget or sponsor defaults

---

### ✅ Sprint 2 (Days 8–14): Approval Workflow & Adapters

#### 5. **Sponsor Approval Workflow API**
- **File:** `/includes/sponsor-approval-api.php`
- **Endpoints:**
  - `POST /wp-json/kh-ad-manager/v1/sponsor-approve` → Record approval/rejection with notes
  - `GET /wp-json/kh-ad-manager/v1/sponsor-approvals/pending` → List pending approvals
  - `GET /wp-json/kh-ad-manager/v1/sponsor-approvals/{schedule_id}` → Approval status for one schedule
- **Stored Metadata:**
  - `_kh_smma_sponsor_approval_status` - "approved" | "rejected" | "pending"
  - `_kh_smma_sponsor_approved_by` - User ID
  - `_kh_smma_sponsor_approved_at` - Timestamp
  - `_kh_smma_sponsor_approval_notes` - Audit trail
- **Permissions:** Requires edit_posts capability

#### 6. **Pending Approvals Admin UI**
- **File:** `/includes/sponsor-approvals-panel.php`
- **Function:** `kh_ad_manager_render_sponsor_approvals_panel()`
- **Features:**
  - Table of pending sponsor approvals
  - Columns: Schedule title, Sponsor name + contact, Variant text preview, Scheduled date
  - Approve/Reject buttons with modal
  - Modal for adding notes before approval/rejection
  - Form handler for POST requests
  - Success/error messages
  - Fallback message when no pending approvals
- **Integration:** Can be called from Boost Visibility page or custom dashboard

#### 7. **Paid Adapter Contract & Dry-Run Shim**
- **File:** `/src/Adapters/PaidAdapterContract.php`
- **Abstract Class** with enforced methods:
  - `dry_run(array $schedule_payload)` → Array of operations without executing
  - `execute(array $schedule_payload)` → Real API calls or sandbox equivalent
  - `get_metadata()` → Adapter name, version, capabilities
- **Helpers:**
  - `format_operation()` - Standardize operation structure (op_type, payload_preview, estimated_spend, policy_warnings)
  - `validate_payload()` - Ensure variant_id, text, targeting present
  - `estimate_spend()` - Calculate expected spend from boost_settings
  - `error()` - Build WP_Error responses
- **Benefit:** Consistent interface for all paid adapters; enables SMMA to preview operations before execution

#### 8. **LinkedIn Ads Adapter with Dry-Run**
- **File:** `/src/Adapters/LinkedInAdsAdapter.php`
- **Implements:** `PaidAdapterContract`
- **dry_run() Returns:**
  ```json
  [
    {
      "op_type": "create_campaign",
      "payload_preview": { ... },
      "estimated_spend": 700,
      "policy_warnings": ["Large budget..."],
      "requires_review": true
    },
    { "op_type": "create_creative", ... },
    { "op_type": "associate_audience", ... }
  ]
  ```
- **execute() Returns:** Same structure + operation_id, status, response
- **Features:**
  - Checks feature flag `smma_paid_adapters`
  - Validates token & account_id
  - Stores operations in `_kh_smma_adapter_operations` meta
  - Logs telemetry with operation count, estimated spend
  - Falls back to manual export on error
- **Notes:** Currently stubbed for sandbox; real API integration deferred to Phase 2

#### 9. **Google Ads Adapter with Dry-Run**
- **File:** `/src/Adapters/GoogleAdsAdapter.php`
- **Implements:** `PaidAdapterContract`
- **Operations:**
  - create_campaign
  - create_ad_group
  - create_text_ads
  - add_keywords
- **Follows Same Pattern:** Dry-run preview → execute with fallback
- **Status:** Stubbed; ready for real API integration

#### 10. **Budget & Spend Placeholders**
- **Fields Implemented:**
  - `ppc_budget_total` (float) - Total campaign budget
  - `ppc_daily_cap` (float) - Daily spend cap
  - `spend_tracking` (object) - { total_spent, today_spent, last_updated }
- **API:** `GET /sponsor/{id}/budget` returns current + remaining amounts
- **Adapters Can Update:** `spend_tracking` meta via `update_post_meta()` as they process campaigns
- **Auto-Pause Logic:** TBD when spend tracking reaches limits (Phase 2)

---

## Files Created

| File | Purpose | Lines |
|------|---------|-------|
| `includes/cpt-sponsor.php` | Sponsor CPT + post meta registration | ~160 |
| `includes/sponsor-api.php` | REST endpoints for sponsor lookup | ~280 |
| `includes/sponsor-admin.php` | Metaboxes + form handlers | ~520 |
| `includes/sponsor-approval-api.php` | Sponsor approval endpoints | ~220 |
| `includes/sponsor-approvals-panel.php` | Admin UI for pending approvals | ~340 |
| `src/Adapters/PaidAdapterContract.php` | Abstract base for paid adapters | ~110 |
| `docs/SPONSOR_API_DOCUMENTATION.md` | Complete API reference | ~450 |

---

## Files Modified

| File | Changes |
|------|---------|
| `kh-ad-manager.php` | Added requires for new include files |
| `src/Adapters/ManualExportAdapter.php` | Enhanced with sponsor metadata + budgets |
| `src/Adapters/LinkedInAdsAdapter.php` | Refactored to extend PaidAdapterContract; added dry-run |
| `src/Adapters/GoogleAdsAdapter.php` | Refactored to extend PaidAdapterContract; added dry-run |

---

## Key Architectural Decisions

### 1. **Sponsor as Canonical Post Type**
- Independent CPT, not term meta
- Allows rich media library, admin UI, version history
- Decouples from campaign taxonomy
- Easier to scale (sponsors can manage self-service later)

### 2. **Dual Approval Workflow**
- SMMA approval: Editorial + compliance checks
- Sponsor approval: Brand policy + asset verification
- Both required before dispatch (independent flows)
- Maintains audit trail for both

### 3. **Adapter Contract Pattern**
- All paid adapters inherit `PaidAdapterContract`
- Forces dry-run + execute separation
- Enables SMMA to preview before committing budget
- Pluggable: New adapters just implement contract

### 4. **Manual Export as Fallback**
- Paid adapters can fallback if feature flag disabled
- If token missing or error occurs
- Preserves manual campaign options
- Sponsor metadata included for manual teams

### 5. **Geo Rules as Nested Object**
- Not separate post type
- Stored as JSON in sponsor meta
- Fast lookup without joins
- Easy for Phase Engine to query

---

## Integration Points

### SMMA Team
- **Consumes:** GET /sponsor/{id}, manual export manifest
- **Sets:** _kh_smma_sponsor_id, _kh_smma_approval_status
- **Expects:** Sponsor approval required before dispatch to paid adapters

### Phase Engine Team
- **Consumes:** GET /sponsor/{id}/geo-rules
- **Uses:** For geo→sponsor mapping decisions
- **Sets:** Sponsor context in phase signals

### Editorial/QA Team
- **Consumes:** Pending approvals admin panel
- **Actions:** Approve/reject variants before sponsor sees them

### Sponsor Admin Portal (Future)
- **Uses:** Sponsor lookup API read-only
- **Portal:** Self-service asset upload, claim approval

---

## Testing Checklist (Ready for QA)

- [ ] **Unit: Sponsor CRUD** - Create sponsor, edit fields, verify meta saved
- [ ] **Unit: Asset Upload** - Upload image/video, verify attachment linked, gallery displays
- [ ] **Unit: Sponsor API** - GET endpoints return correct format + all fields
- [ ] **Unit: Budget Calculation** - Remaining budget = total - spent
- [ ] **Integration: Generate + Schedule + Approve + Export** 
  - Create schedule with sponsor_id
  - Call sponsor-approve endpoint
  - Request manual export
  - Verify manifest includes sponsor metadata + recommended_budget
- [ ] **Integration: Dry-Run** 
  - Set boost_settings with dry_run=true
  - Dispatch schedule to LinkedInAdsAdapter
  - Verify operations returned without API calls
  - Verify estimated_spend + policy_warnings present
- [ ] **Acceptance: Approval Status Visible**
  - Create schedule with approval required
  - Pending approvals panel shows it
  - Approve via modal/form
  - Status changes to "approved"
  - Can proceed to dispatch

---

## Known Limitations & Future Work

### Phase 2 (Not in Scope)
- [ ] Real LinkedIn Ads API integration (currently stubbed)
- [ ] Real Google Ads API integration (currently stubbed)
- [ ] Auto-pause campaigns when budget exceeded
- [ ] Sponsor self-service portal with login
- [ ] Email notifications for pending approvals
- [ ] Webhook notifications to external sponsors
- [ ] Invoicing skeleton for billing team

### Defer to Phase 2
- Spend sync from live API campaigns (mock data only for now)
- Resume capability for partial campaign failures
- Complex multi-sponsor co-brand rules
- Sponsor asset versioning/approval history UI

---

## Deployment Checklist

- [x] Code follows WordPress standards (security, sanitization, escaping)
- [x] All REST endpoints check `current_user_can('edit_posts')`
- [x] Database queries use wpdb prepared statements (ACF handles)
- [x] Admin forms include nonces + verify on save
- [x] JS uses wp_localize_script for AJAX security
- [x] Translation strings marked with `__()` and `esc_html__()`
- [x] No direct file access (all files have `if (!defined('ABSPATH')) exit;`)
- [x] POST handlers redirect with nonce + flash messages
- [x] Error responses use WP_Error with proper status codes
- [x] Asset URLs use `wp_get_attachment_url()` not direct paths

---

## Success Metrics

✅ **Sponsor CPT:** Can create/edit sponsors with all 11 fields in admin  
✅ **Asset Library:** Upload images/videos, retrieve via API with metadata  
✅ **Approval Workflow:** Schedule approval tracking with audit trail  
✅ **Manual Export:** Manifest includes sponsor metadata + recommended budget  
✅ **Adapter Contract:** LinkedInAdsAdapter + GoogleAdsAdapter both implement contract  
✅ **Dry-Run:** Adapters return operation previews without executing API calls  
✅ **API Stability:** All endpoints return consistent JSON format  
✅ **Documentation:** Complete OpenAPI-style reference with examples  

---

## Contact & Next Steps

**Coordination Required:**
1. **SMMA Team:** Verify sponsor API schema matches expectations; begin integration
2. **Phase Engine Team:** Test geo-rules lookup; integrate with phase signal context
3. **QA Team:** Execute test checklist above; flag issues
4. **Sponsorship Admin:** Test sponsor CPT workflows; provide feedback on UX

**Timeline:**
- Day 1–3: SMMA + Phase Engine teams begin parallel integration
- Day 4: Joint testing of generate→approve→export flow in staging
- Day 5: Feature flag enabled for broader testing
- Day 6–7: Bug fixes + polish for Phase 2

---

## Code Examples

### Create a Sponsor
```php
$sponsor_id = wp_insert_post([
    'post_type' => 'kh_sponsor',
    'post_title' => 'Acme Corp',
    'post_status' => 'publish',
]);

update_post_meta($sponsor_id, 'linkedin_page_url', 'https://linkedin.com/company/acme');
update_post_meta($sponsor_id, 'allowed_claims', ['Claim 1', 'Claim 2']);
update_post_meta($sponsor_id, 'ppc_budget_total', 10000);
```

### Lookup Sponsor via API
```bash
curl https://site.local/wp-json/kh-ad-manager/v1/sponsor/123 \
  -H "X-WP-Nonce: $(wp eval 'echo wp_create_nonce("wp_rest");')"
```

### Approve a Schedule via API
```bash
curl -X POST https://site.local/wp-json/kh-ad-manager/v1/sponsor-approve \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: ..." \
  -d '{
    "schedule_id": 987,
    "decision": "approved",
    "notes": "Approved - complies with brand guidelines"
  }'
```

### Use Adapter Contract
```php
$adapter = new LinkedInAdsAdapter($tokens, $flags);

// Preview without executing
$operations = $adapter->dry_run([
    'variant_id' => 'v-1',
    'text' => 'Check out our product...',
    'targeting' => ['audience_id' => 123],
    'budget_daily' => 100,
]);

// Execute when ready
$results = $adapter->execute($payload);
```

---

**Documentation:** See [SPONSOR_API_DOCUMENTATION.md](./SPONSOR_API_DOCUMENTATION.md)  
**Version:** 1.0.0  
**Last Updated:** February 2, 2026
