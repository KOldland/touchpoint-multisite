# Membership Disaster Recovery Runbook (MEM-09)

## Scope

Recovery procedures for membership data domains:

- `promotion_attribution`
- processed webhook stores
- webhook DLQ/audit
- email logs/queue
- paid reconciliations

## 1) Backup procedure

Create logical backup:

```bash
wp db export artifacts/mem09-backup-$(date -u +%Y%m%d-%H%M%S).sql
```

Recommended table-focused snapshot for faster restore drills:

```bash
mysqldump --single-transaction --skip-lock-tables <db_name> \
  wp_promotion_attribution \
  wp_khm_processed_webhooks \
  wp_khm_processed_webhook_events \
  wp_khm_webhook_dead_letter \
  wp_khm_email_logs \
  wp_khm_email_queue \
  wp_kh_paid_reconciliations \
  > artifacts/mem09-membership-core.sql
```

## 2) Restore drill

Restore into isolated test database:

```bash
mysql <restored_db> < artifacts/mem09-membership-core.sql
```

Point staging clone to restored DB and run smoke checks.

## 3) Integrity validation queries

```sql
SELECT COUNT(*) FROM wp_promotion_attribution;
SELECT COUNT(*) FROM wp_khm_processed_webhook_events;
SELECT COUNT(*) FROM wp_khm_webhook_dead_letter WHERE status='open';
SELECT COUNT(*) FROM wp_khm_email_queue WHERE status IN ('pending','processing','failed');
SELECT COUNT(*) FROM wp_kh_paid_reconciliations;
```

Reference hash sanity:

```sql
SELECT id, reference_hash, anonymized_at
FROM wp_promotion_attribution
WHERE anonymized_at IS NOT NULL
ORDER BY id DESC
LIMIT 20;
```

## 4) Functional smoke on restored env

- Run release gate:

```bash
php scripts/mem_release_gate_check.php --environment=restore-drill
```

- Run membership smoke tests:

```bash
cd app/public/wp-content/plugins/khm-plugin
php vendor/bin/phpunit --colors=never tests/Membership/SignupInitMatrixTest.php
php vendor/bin/phpunit --colors=never tests/Membership/StripeWebhookFixtureIntegrationTest.php
```

## 5) Failover checklist

- [ ] Backup artifact checksum recorded.
- [ ] Restore completed without SQL errors.
- [ ] Core table counts match expected variance.
- [ ] Membership smoke tests pass on restored environment.
- [ ] Ops confirms webhook and queue workers healthy.
- [ ] PM/ops sign-off recorded.

## 6) RTO/RPO tracking template

- Incident ID:
- Backup timestamp (UTC):
- Restore start/end (UTC):
- RTO achieved:
- RPO achieved:
- Data deltas (if any):
- Follow-up remediations:
