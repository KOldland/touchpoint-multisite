# Stripe Secret Rotation

## Scope

Rotate:

- `KH_STRIPE_SECRET_KEY`
- `KH_STRIPE_WEBHOOK_SECRET`
- any canary-only or staging Stripe keys

## Steps

1. Create new Stripe restricted API key and webhook secret.
2. Update secret manager entries; do not commit values to the repo.
3. Update GitHub Actions secrets and deployment platform env vars.
4. Run `scripts/secret-scan.sh` and `phase4-security` workflow.
5. Run webhook smoke on staging using the new secret.
6. Disable the previous secret only after staging validation succeeds.

## Validation

- `phase4-security` workflow green
- `khm-plugin-webhooks-ci` green
- manual webhook signature test passes on staging

## Human approval

Production secret rotation must be announced to release manager and payments owner before cutover.
