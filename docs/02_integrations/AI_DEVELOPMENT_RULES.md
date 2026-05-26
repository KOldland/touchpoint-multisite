# AI Development Rules

This repository is developed by multiple AI teams in parallel. These rules are mandatory for all AI-delivered cards and pull requests.

## 1) Bucket Boundaries

- Every card is assigned to a bucket (for example: `MEM`, `SMMA`, `PAID`, `CIC`).
- Work must stay within the paths declared by `.dev-scope.yml`.
- Changes outside declared `allowed_paths` are blocked by `scripts/scope_guard.php` via `.github/workflows/scope-guard.yml`.

## 2) Contract-First Development

- Shared contracts are authoritative and must be treated as stable interfaces.
- Protected contract locations include:
  - `docs/contracts/`
  - `golden-fixtures/`
- Contract changes require explicit approval (`contract-change-approved` label) before CI can pass.

## 3) No Cross-Bucket Edits

- Do not modify implementation code owned by other buckets.
- If upstream/downstream changes appear necessary, report blockers in the PR summary instead of implementing cross-bucket changes.
- Do not refactor unrelated modules, naming, or architecture as part of a bucket card.

## 4) Migration Safety

- Existing migrations are immutable.
- New migrations must be appended in sequence.
- Migration rules are enforced by `scripts/migration_guard.php` in `.github/workflows/migration-guard.yml`.

## 5) Deterministic Replay Requirement

- Repository-level integration replay must remain deterministic and reproducible.
- Replay entrypoint: `scripts/replay_system_test.php`.
- CI runs replay on every PR via `.github/workflows/replay-test.yml`.
- Golden fixtures must not contain PII.

## 6) PR Evidence Block (Required)

Every card PR must include a structured evidence block covering at minimum:

- `CARD`
- `BUCKET`
- `FILES CREATED`
- `FILES MODIFIED`
- `DATABASE CHANGES`
- `TELEMETRY EVENTS`
- `TESTS ADDED`
- `CI STATUS`
- `RUNBOOK UPDATED`

## 7) Stop After Card Completion

- Implement only the requested card scope.
- When acceptance criteria are met:
  1. Open/update PR with evidence block.
  2. Report completion.
  3. Stop development.
  4. Wait for the next card brief.

## 8) Brief Reference Requirement

- New AI development briefs should reference this document directly (`docs/AI_DEVELOPMENT_RULES.md`) so guardrails are consistently applied across buckets.