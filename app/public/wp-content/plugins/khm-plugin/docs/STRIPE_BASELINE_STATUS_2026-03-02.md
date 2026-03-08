# Stripe Baseline Status (2026-03-02)

This file is the working source of truth for current Stripe integration status in this repo checkout.

## Current local branch

- `copilot/sub-pr-20`
- HEAD commit: `aba6f75`

## What is included in this branch

- Stripe level mirror mapping/import scaffold and related docs
- Membership/checkout modal promotion-code alignment
- Legacy checkout migration guardrails
- Stripe webhook verifier header normalization + HMAC fallback
- Stripe dependency preflight + lockfile workflow

## What is not yet included in this branch

- PR `#22` webhook queue/idempotency hardening package
- PR `#23` checkout security follow-up
- PR `#24` webhook/audit/security follow-up

## Local hardening applied in this workspace

- `src/Rest/CheckoutController.php`
  - Removed side-effect-only `StripeGateway` construction
  - Explicitly sets Stripe API key before session creation
- `src/Membership/StripeWebhookHandler.php`
  - Added explicit signed-webhook requirement by default
  - Added filter gate for local bypass: `khm_membership_webhook_skip_signature_verification`
  - Hardened signature header extraction
  - Added safe event object validation
  - Normalized membership status mapping (`trialing` -> `trial`, etc.)
  - Normalized canceled spelling to `canceled`
  - Centralized webhook event table creation
  - Switched event persistence to `INSERT IGNORE` for idempotency races

## Recommended next merge order

1. Apply/merge PR `#22`
2. Apply/merge PR `#23`
3. Apply/merge PR `#24`
4. Re-run Stripe integration tests and webhook smoke tests

## Minimum verification checklist after merge

1. `checkout.session.completed` creates/updates membership with expected status
2. `invoice.paid` updates membership active state
3. `invoice.payment_failed` marks membership `past_due`
4. Signature verification fails with invalid signature (`400`)
5. Duplicate webhook event IDs are idempotent
6. Promo path only accepts server-validated Stripe promotion codes
