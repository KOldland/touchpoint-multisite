# Secret Rotation Runbook

Use [docs/STRIPE_SECRET_ROTATION.md](../docs/STRIPE_SECRET_ROTATION.md) for Stripe-specific rotation. This runbook covers the generic pattern.

## Rotation checklist

1. Create replacement secret.
2. Store in vault / secret manager.
3. Update CI and deploy environment references.
4. Run `scripts/secret-scan.sh`.
5. Run smoke tests in staging.
6. Disable old secret after verification.

## Never do

- commit secrets to the repo
- paste secrets into PRs or issue comments
- reuse staging secrets in production
