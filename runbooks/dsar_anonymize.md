# DSAR / Anonymize Runbook

## Export DSAR

```bash
wp --path=app/public khm dsar request --user_id=<id> --type=export --ticket_id=<ticket>
wp --path=app/public khm dsar approve --request_id=<request-id> --ticket_id=<ticket>
```

## Anonymize attribution

```bash
wp --path=app/public khm anonymize_attribution --id=<attribution-id> --dry-run
wp --path=app/public khm anonymize_attribution --id=<attribution-id> --reason="phase4-ops"
```

## Verify

- audit entry recorded
- telemetry `membership.anonymize.*` recorded
- PII removed from attribution rows

## Human approval

Deletion beyond anonymization requires legal/compliance sign-off.
