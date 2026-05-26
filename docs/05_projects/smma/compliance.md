# SMMA Compliance

Detailed operator guidance is documented in:

- `docs/smma/compliance-runbook.md`

## Overview

Compliance runs in two stages:

1. Deterministic rule scan (`ComplianceRuleEngine`)
2. AI-assisted contextual review (`ComplianceService` via `ComplianceValidator`)

Decision priority:

- Deterministic `FAIL` always remains `FAIL`
- AI may classify borderline content as `WARN`
- Otherwise content is `OK`

## Banned Phrase Rules

Configured in:

- `src/Compliance/BannedPhraseRules.php`

Default blocked phrases:

- guaranteed results
- risk-free returns
- unlimited growth
- guaranteed leads

## Compliance Metadata

Stored with each variant payload:

- `compliance_status`
- `compliance_reason`
- `matched_rules`
- `ai_review_summary`
- `checked_at`

## Scheduling Gates

`ScheduleController` enforces:

- `OK`: schedule is created with `approval_required=false` and `approval_status=approved`
- `WARN`: schedule is created with `status=pending_approval`, `approval_required=true`, and `approval_status=pending`
- `FAIL`: scheduling is hard blocked with `COMPLIANCE_FAIL` and no schedule row is created

For approval workflow integration, schedule metadata persists:

- `approval_required`
- `approval_status`
- `compliance_status`
- `compliance_reason`

Sponsor/admin approval workflow UI is out of scope for SMMA; SMMA only sets the statuses consumed by that workflow.
