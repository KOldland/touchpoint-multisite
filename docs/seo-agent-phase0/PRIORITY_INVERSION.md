# SEO Agent Phase 0 — Priority Inversion Review

Date: 2026-01-22

## Value-first plan (fastest editor value)
1. Editor Audit Modal
   - Read-only audit from KHM-SEO analysis + Dual-GPT enrichment.
   - Single summary line + issue list.
2. Suggestion selection + Preview
   - Preview-only diff and block preview.
   - No writes without explicit confirmation.
3. Apply (single meta write)
   - Allow only _khm_seo_title / _khm_seo_description / _khm_seo_focus_keyword.
   - Dual-GPT audit log for every apply.
4. Handoff modal
   - Create Dual-GPT session and pass prompt to upstream agent (Framework Builder / Author Agent).

## Risk-first plan (prevent platform damage)
1. Guardrails before any tool writes
   - Capability checks, idempotency keys, audit logging, and rollback data.
2. Enforce JSON output contract + schema validation
   - UI blocks if invalid JSON.
3. KHM-SEO as single source of truth
   - No new tables for SEO state; only postmeta via allowed keys.
4. Sponsor-safe mode toggle
   - Enforce in presets + tool logic.
5. Rate-limit + budget checks
   - Use Dual_GPT_DB_Handler::check_user_budget and rate limit prior to tool calls.

## Reconciled plan
### Must ship (Phase 1)
- Dual-GPT preset + JSON validation with UI error handling.
- Read-only audit + suggestions modal.
- Preview-only diff flow for selected suggestions.
- Apply actions limited to safe meta keys with audit logging and rollback data.
- Admin toggle for sponsor_safe mode.
- Feature flag and role gating (internal only).

### Phase 2+
- Full admin dashboard insights and scoring.
- Multi-step Apply wizard with block insertion previews.
- Schema write support (validated), GEO-aware improvements, and internal link automation.
- Broader rollout / role expansion.

Rationale:
- Phase 1 focuses on safe, auditable editor value with minimal write scope.
- Phase 2+ expands functionality after validation of audit/preview/apply flow and sponsor-safe guardrails.

