# Phase Engine Handover

## Canonical event IDs
- CSV: `app/public/wp-content/plugins/kh-smma/data/event_catalog.csv`

## Aggregator
- Trigger: cron/worker (hourly)
- Dry-run verification SQL (Postgres):

```
WITH decayed AS (
  SELECT
    ue.user_id,
    ec.phase_tag,
    SUM(ue.event_points * pow(2, - extract(epoch from (now() - ue.created_at)) / (ec.default_decay_days * 86400))) AS decayed_points
  FROM user_event ue
  JOIN event_catalog ec ON ue.event_type = ec.event_id
  GROUP BY ue.user_id, ec.phase_tag
),
pivot AS (
  SELECT
    user_id,
    COALESCE(MAX(case when phase_tag='Attention' then decayed_points end),0) as attention_pts,
    COALESCE(MAX(case when phase_tag='Antagonistic' then decayed_points end),0) as antagonistic_pts,
    COALESCE(MAX(case when phase_tag='Anxiety' then decayed_points end),0) as anxiety_pts,
    COALESCE(MAX(case when phase_tag='Acceptance' then decayed_points end),0) as acceptance_pts
  FROM decayed
  GROUP BY user_id
)
SELECT
  user_id,
  attention_pts, antagonistic_pts, anxiety_pts, acceptance_pts,
  attention_pts/72.0 AS norm_attention,
  antagonistic_pts/90.0 AS norm_antagonistic,
  anxiety_pts/108.0 AS norm_anxiety,
  acceptance_pts/90.0 AS norm_acceptance,
  GREATEST(attention_pts/72.0, antagonistic_pts/90.0, anxiety_pts/108.0, acceptance_pts/90.0) AS top_norm,
  CASE
    WHEN GREATEST(attention_pts/72.0, antagonistic_pts/90.0, anxiety_pts/108.0, acceptance_pts/90.0) >= 0.45
      THEN
        CASE
          WHEN attention_pts/72.0 = GREATEST(attention_pts/72.0, antagonistic_pts/90.0, anxiety_pts/108.0, acceptance_pts/90.0) THEN 'Attention'
          WHEN antagonistic_pts/90.0 = GREATEST(attention_pts/72.0, antagonistic_pts/90.0, anxiety_pts/108.0, acceptance_pts/90.0) THEN 'Antagonistic'
          WHEN anxiety_pts/108.0 = GREATEST(attention_pts/72.0, antagonistic_pts/90.0, anxiety_pts/108.0, acceptance_pts/90.0) THEN 'Anxiety'
          WHEN acceptance_pts/90.0 = GREATEST(attention_pts/72.0, antagonistic_pts/90.0, anxiety_pts/108.0, acceptance_pts/90.0) THEN 'Acceptance'
        END
    ELSE NULL
  END AS assigned_phase
FROM pivot;
```

## Smoke tests
- Script: `devops/smoke.sh`
- Required env: `HOST`, `NONCE`, `PGHOST`, `PGUSER`, `PGDATABASE`, optional `PGPORT`, `TEST_USER_ID`
- Run: `HOST=... NONCE=... PGHOST=... PGUSER=... PGDATABASE=... devops/smoke.sh`

## Rollback
- Script: `devops/revert.sh`

## REST endpoints
- `POST /wp-json/kh-smma/v1/record-event`
- `GET /wp-json/kh-smma/v1/user-phase?user_id=<id>`
