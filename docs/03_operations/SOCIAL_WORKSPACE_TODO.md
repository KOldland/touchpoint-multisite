# Social Workspace Unification TODO

## Goal
Create one cohesive social workflow by combining:
- `kh-smma` campaign generation/edit/scheduling
- `khm-seo` sharing metadata and preview controls

## Phase 1 - Unified Editor Surface (In Progress)
- [x] Add a unified editor meta box shell in `kh-smma` with tabs:
  - `Campaigns`
  - `Sharing Metadata`
- [x] Keep existing `Boost Post` workflow trigger inside `Campaigns`.
- [x] Add `Sharing Metadata` fields for:
  - Default social title/description
  - Twitter card type
  - Platform title/description overrides (Facebook, Twitter, LinkedIn, Pinterest)
- [x] Save metadata from the unified panel on post save.
- [ ] Add image selector support in unified panel (attachment picker + preview).
- [ ] Add per-platform quick preview links from the unified panel.

## Phase 2 - Metadata Completeness + Validation
- [ ] Add inline validation (length guidance and required fields):
  - OG title/description
  - Twitter title/description
- [ ] Add readiness badges:
  - `Metadata Ready`
  - `Campaign Ready`
  - `Scheduled`
- [ ] Add warning copy when campaign exists but sharing metadata is missing.

## Phase 3 - Navigation and IA Cleanup
- [ ] Consolidate menu labels to avoid overlap/confusion:
  - distinguish `Campaign Ops` vs `Sharing Metadata`
- [ ] Add links between admin pages and editor panel for quick context switching.
- [ ] Deprecate duplicate legacy entry points only after parity is confirmed.

## Phase 4 - Deep Integration (Optional Refactor)
- [ ] Create shared service contract for social post context consumed by both engines.
- [ ] Allow SMMA generation to optionally use social metadata defaults as prompt inputs.
- [ ] Add analytics correlation between metadata quality and campaign output performance.

## Release 1.02 Backlog
- [ ] Schema by function routing (default wired model):
  - Posts locked to `Article` schema
  - Author/profile surfaces emit `Person` schema
  - Organization/company surfaces emit `Organization` schema
  - Product/toolkit surfaces emit `Product` schema where applicable
  - Breadcrumb schema handled globally rather than manually per post
- [ ] Schema management surfaces (v1.02):
  - Reintroduce advanced schema controls in a dedicated management page
  - Restore working preview/validation/test tooling with reliable handlers
  - Keep post editor schema in auto mode unless explicitly overridden by admin policy

## QA Checklist
- [ ] Existing SMMA generate/edit/schedule still works.
- [ ] Social metadata from unified panel persists across saves.
- [ ] Front-end OG/Twitter tags still render correctly.
- [ ] No regressions in approvals/queue/compliance flow.
- [ ] Classic editor and block editor both render panel cleanly.
