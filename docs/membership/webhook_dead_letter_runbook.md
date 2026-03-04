# Membership Webhook Dead-Letter Runbook

## Purpose

Operate and recover failed membership Stripe webhook events captured in `khm_webhook_dead_letter`.

## Commands

- List open dead letters:

```bash
wp khm membership-webhook-dead-letters --last=50
```

- Replay one dead-letter event by row ID:

```bash
wp khm membership-webhook-dead-letters-replay --id=123
```

- Replay a batch of open dead letters:

```bash
wp khm membership-webhook-dead-letters-replay --all-open --limit=20
```

## Operational Notes

- Replays enqueue back onto `khm_process_membership_stripe_webhook_event` and preserve existing event/operation idempotency.
- Successful replay marks rows `resolved` with `resolved_at` timestamp.
- Invalid payload rows remain open and must be manually triaged.
- Review plugin logs for `webhook.failed` and `webhook.queue_failed` telemetry during incidents.
