# Changelog - Promotion Planner (SMMA) Implementation

## [Unreleased] - 2025-02-03

### Production Readiness Improvements

#### Testing & Quality Assurance
- **Unit Tests for ComplianceValidator** (`tests/ComplianceValidatorTest.php`)
  - 25 comprehensive test cases covering:
    - Rule-based validation (blacklist, length limits, allowed claims)
    - Exact match and fuzzy match claim validation
    - Case-insensitive matching
    - Batch validation
    - Unicode/multibyte character handling
    - Edge cases (empty text, whitespace, regex-safe claims)
  - All tests documented with expected behavior

- **Golden Stub Infrastructure** for LLM calls
  - MockLLMClient (`tests/MockLLMClient.php`) for deterministic testing
  - Golden fixtures in `tests/fixtures/golden/`:
    - `generate_response.json` - Variant generation responses
    - `compliance_pass_response.json` - Passing compliance checks
    - `compliance_warn_response.json` - Warning/failing compliance checks
  - Automatic fixture selection based on prompt content
  - Telemetry tracking with prompt_hash and response_hash

- **CI Safety Checks** (`tests/ci-safety-check.php`)
  - Detects live LLM API keys in CI environment
  - Fails fast if real API keys found (prevents accidental live calls)
  - Verifies test mode configuration
  - Validates presence of required golden fixtures
  - Exit codes for CI integration (0=safe, 1=unsafe, 2=config error)

#### Security & Authorization
- **Enhanced Authorization** for approve/reject endpoints
  - New `approve_sponsor_posts` capability registered in CapabilityManager
  - Capability granted to administrators and editors by default
  - Server-side capability checks on all approval operations
  - Proper 403 responses for unauthorized attempts

- **Idempotency** for approve/reject endpoints
  - Approve endpoint returns 200 with metadata if already approved (no duplicate operations)
  - Reject endpoint returns 200 with metadata if already rejected
  - Prevents duplicate audit log entries
  - Proper tracking of who/when for idempotent requests

#### Timezone & Datetime Handling
- **ISO 8601 Datetime Support** in schedule endpoint
  - Accepts ISO 8601 datetime strings (e.g., "2025-02-20T09:00:00-05:00")
  - Converts to UTC for storage consistency
  - Preserves original timezone in metadata (`_kh_smma_original_timezone`)
  - Backwards compatible with unix timestamps
  - Helper methods: `parse_datetime_to_utc()`, `extract_timezone()`

#### Telemetry & Observability
- **Enhanced Telemetry** for all operations
  - Generate: prompt_hash, model_version, response_hash
  - Variant-edit: editor_id, unified_diff, compliance_result
  - Approve: approver_id, notes, timestamp
  - Reject: rejected_by, rejection_reason, timestamp
  - Schedule: original_timezone, scheduled_at_input

- **Preview Changes Tracking** (`_kh_smma_preview_changes` meta)
  - Stores full edited text
  - Unified diff for change visualization
  - Editor ID and timestamp
  - Compliance check results
  - Enables edit history and audit trails

- **Unified Diff Calculation**
  - Line-by-line diff generation for variant edits
  - Compact storage format (unified diff)
  - Shows additions (+), deletions (-), and unchanged lines
  - Used for telemetry and audit logging

#### Data Integrity
- **Sponsor Metadata Validation**
  - Verified `kh_ad_manager_get_sponsor_meta()` function integration
  - Returns `allowed_claims` array as expected
  - Supports both kh_sponsor CPT and ad-campaign terms
  - Proper fallback handling when function unavailable

### Changed

#### REST API Endpoints
- **POST `/kh-smma/v1/approve`** - Enhanced with:
  - Authorization: Requires `approve_sponsor_posts` or `manage_options` capability
  - Idempotency: Returns existing approval if already approved
  - Enhanced telemetry: approver_id, notes
  - Proper audit logging
  - Location: `RestController.php:294-364`

- **POST `/kh-smma/v1/reject`** - Enhanced with:
  - Authorization: Requires `approve_sponsor_posts` or `manage_options` capability
  - Idempotency: Returns existing rejection if already rejected
  - Enhanced telemetry: rejected_by, rejection_reason
  - Location: `RestController.php:466-557`

- **POST `/kh-smma/v1/variant-edit`** - Enhanced with:
  - Diff calculation: Stores unified diff of changes
  - Preview changes: Full metadata in `_kh_smma_preview_changes`
  - Enhanced telemetry: includes diff and compliance result
  - Location: `RestController.php:409-476`

- **POST `/kh-smma/v1/schedule`** - Enhanced with:
  - Timezone handling: ISO 8601 datetime support
  - UTC normalization: All timestamps stored in UTC
  - Original timezone preservation
  - Location: `RestController.php:179-267`

#### Security
- **CapabilityManager** (`src/Security/CapabilityManager.php`)
  - Added `CAP_APPROVE_SPONSOR` constant
  - Registered `approve_sponsor_posts` capability for admins and editors
  - New helper method: `can_approve_sponsor_content()`

### Testing

#### Running Tests
```bash
# Run CI safety check first
php tests/ci-safety-check.php

# Run unit tests with golden stubs (recommended for CI)
export KH_SMMA_TEST_MODE=ci
phpunit tests/ComplianceValidatorTest.php

# Run unit tests with live LLM (local development only)
unset KH_SMMA_TEST_MODE
phpunit tests/ComplianceValidatorTest.php
```

#### CI Integration
```yaml
# GitHub Actions example
- name: Safety Check
  run: php app/public/wp-content/plugins/kh-smma/tests/ci-safety-check.php

- name: Run Tests
  env:
    KH_SMMA_TEST_MODE: ci
  run: phpunit app/public/wp-content/plugins/kh-smma/tests/
```

### Migration Notes
- **Capability Update**: Run plugin activation to register new `approve_sponsor_posts` capability
- **Timezone Data**: Existing schedules without timezone metadata will default to UTC
- **Telemetry Schema**: New telemetry fields automatically populated for new operations

### Breaking Changes
None. All changes are backwards compatible.

---

## [0.1.0] - 2025-02-02

### Added - Backend (Option 1)

#### REST API Endpoints
- **POST `/kh-smma/v1/variant-edit`** - Edit scheduled variant text with compliance validation
  - Validates against blacklist phrases
  - Checks sponsor allowed claims
  - Re-validates with AI (if available)
  - Returns compliance results with confidence score
  - Updates variant payload with edit metadata (edited_at, edited_by)
  - Location: `RestController.php:277-332`

- **POST `/kh-smma/v1/reject`** - Reject scheduled variant with optional reason
  - Prevents double-rejection
  - Tracks rejection metadata (rejected_by, rejected_at, reason)
  - Updates schedule_status to 'rejected'
  - Logs to audit trail and telemetry
  - Location: `RestController.php:334-374`

#### Services

- **ComplianceValidator** service (`src/Services/ComplianceValidator.php`)
  - Two-tier validation: rule-based (fast) + AI-powered (nuanced)
  - Blacklist checking (8 prohibited phrases)
  - Channel-specific length limits
  - Sponsor claim validation
  - Confidence scoring (0.0-1.0)
  - Batch validation support

- **Enhanced Schedule Endpoint** - Added approval workflow logic
  - Auto-approval for non-sponsored content
  - Manual approval required for sponsored content
  - Tracks approval metadata

### Added - Frontend (Option 2)

#### Boost Visibility Table
- Phase column with colored badges
- Enhanced Actions column (Promote, Boost, Pending)
- Pending count display

#### Pending Approvals Panel
- Variant preview with full text
- Phase and compliance badges
- JavaScript-based action buttons

#### JavaScript Components
- SMMA_API client
- PromoteModal (generation UI)
- CalendarModal (scheduling UI)
- EditVariantModal (inline editing)
- RejectModal (rejection workflow)

#### Assets
- smma-admin.js (683 lines)
- smma-admin.css (204 lines)
- AssetsManager for enqueuing

### Documentation
- README.md (comprehensive plugin documentation)
- CHANGELOG.md (this file)
- API test documentation

---

See README.md for complete feature documentation.
