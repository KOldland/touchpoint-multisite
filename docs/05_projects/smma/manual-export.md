# SMMA Manual Export

## Overview

Card 05 adds a manual-only export path for scheduled campaigns. It generates a
zip bundle for ops review or external publishing and does not dispatch ads.

## Bundle Format

Generated filename:

- `schedule_export_{schedule_id}.zip`

Archive contents:

- `manifest.json`
- `variant_text.txt`
- `assets/` (optional)

## Manifest Schema

`manifest.json` includes:

- `schedule_id`
- `variant_id`
- `platform` (`manual`)
- `post_text`
- `estimated_spend`
- `estimated_ops`
- `compliance_status`
- `approval_status`
- `created_at`

No PII should be included.

## Scheduling Gate

Manual export is allowed only when:

- `approval_status = approved`
- `compliance_status != FAIL`

Otherwise the API returns:

- `error: EXPORT_NOT_ALLOWED`

## API Workflow

1. Generate bundle:
   - `POST /wp-json/kh-smma/v1/manual-export/schedule/{schedule_id}/bundle`
2. Download bundle metadata (zip response contract):
   - `GET /wp-json/kh-smma/v1/manual-export/schedule/{schedule_id}/download`

## Telemetry & Audit

Emitted events:

- `export.bundle.created`
- `export.bundle.downloaded`

Audit actions:

- `export.bundle.created`
- `export.bundle.downloaded`
