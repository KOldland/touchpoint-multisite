# Membership Ops Safety Thresholds

Date: `2026-03-03`

## Scope

This document defines the operational alert thresholds requested for the membership + Stripe webhook workstream.

## Alert thresholds

- `membership.email.failed`: alert if `> 0` in `15m`
- `membership.email.sent.failed`: alert if `> 0` in `15m`
- `membership.attribution.missing`: alert if `> 0` in `15m`
- `webhook.invalid_signature`: alert if `> 5%` of webhook requests in `15m`
- `webhook.rate_limit.exceeded`: alert if `> 20` in `5m`
- `webhook.rate_limit.blocked`: alert if `> 5` in `15m`
- `membership.email.skipped`: alert if `> 0` in `15m`

## Current implementation status

- `webhook.invalid_signature`: emitted in webhook handlers and included in runbook monitoring guidance.
- `webhook.rate_limit.exceeded`: emitted in canonical webhook controller.
- `webhook.rate_limit.blocked`: emitted in canonical webhook controller.
- `membership.email.failed`: not emitted as a first-class metric key in current plugin code.
- `membership.email.sent.failed`: not emitted as a first-class metric key in current plugin code.
- `membership.attribution.missing`: not emitted as a first-class metric key in current plugin code.
- `membership.email.skipped`: not emitted as a first-class metric key in current plugin code.

## Notes

- Canonical staging webhook path is `khm/v1/webhooks/stripe` with idempotency table `wp_khm_webhook_events`.
- Legacy membership webhook handler has separate telemetry/events and tables.
- Until the missing metric keys are added, alerting for those items must be approximated with existing hooks/logs.
- `khm_membership_transactional_emails_enabled` should default to `off` in production/staging toggle policy.
