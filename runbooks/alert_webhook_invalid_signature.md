# Alert Runbook: webhook.invalid_signature

## Trigger

- invalid signature rate > 1% for 5m
- or repeated spikes from a single source IP

## Triage

1. Check latest `webhook.invalid_signature` telemetry and confirm source IPs.
2. Verify current `KH_STRIPE_WEBHOOK_SECRET` version in secret manager.
3. Confirm no recent deploy changed signature parsing.
4. Check `phase4-canary-smoke` and `khm-plugin-webhooks-ci` outputs.

## Mitigation

- block abusive IPs at edge/WAF when the source is malicious
- rotate webhook secret if mismatch is confirmed
- pause canary expansion if the alert fires during rollout

## Escalate when

- signatures fail across all traffic after a secret rotation
- DLQ growth rises alongside invalid signature alerts
