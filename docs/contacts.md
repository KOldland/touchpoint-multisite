# Contacts and Escalation

Update names/handles before production incidents.

## Area owners

- Build (membership frontend/backend): `@mem-build-owner`
- QA (staging E2E + accessibility): `@mem-qa-owner`
- Ops (webhook/email/runtime): `@mem-ops-owner`
- Legal/compliance (DSAR/privacy): `@legal-owner`
- PM sign-off: `@mem-pm`

## Escalation ladder

1. On-call engineer acknowledges alert within SLA.
2. Area owner engaged if unresolved after initial triage.
3. PM + Ops lead engaged for customer-impacting incidents.
4. Legal/compliance engaged immediately for DSAR/privacy risk.

## Sample alert messages

### Webhook incident

`[SEV2][membership] webhook.invalid_signature spike on staging/prod. Event ingest failing; investigating secret/config drift. Incident ID: INC-####.`

### Email incident

`[SEV2][membership] membership.email.failed above threshold; transactional emails paused via toggle pending provider recovery. Incident ID: INC-####.`

### Attribution/reconciliation incident

`[SEV2][membership-paid] membership.attribution.missing or paid_reconciliation.discrepancy_alert triggered; validating idempotency and finance deltas. Incident ID: INC-####.`

### Legal/compliance escalation

`[SEV1][privacy] DSAR/delete/anonymize flow issue detected. Legal review requested immediately. Ticket: COMP-#### / Incident: INC-####.`
