# Changelog - Promotion Planner (SMMA) Implementation

## [Unreleased] - 2025-02-02

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
