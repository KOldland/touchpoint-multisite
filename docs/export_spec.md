# Membership Attribution Export Spec (MEM-04)

## Endpoint / Trigger
- Admin action: `admin_post_khm_membership_reports_export`
- Source page: `admin.php?page=khm-membership-reports`

## Filters Propagated to Export
- `schedule_id`
- `sponsor_id`
- `user_id`
- `conversion_type`
- `date_from`
- `date_to`
- `q`

## Output
- Format: CSV
- Filename: `membership-attribution-YYYYMMDD-HHMMSS.csv`
- Response headers:
  - `Content-Type: text/csv; charset=utf-8`
  - `Content-Disposition: attachment; filename=...`
  - `X-KHM-Export-Checksum: <sha256>`

## CSV Columns
1. `id`
2. `schedule_id`
3. `schedule_title`
4. `sponsor_id`
5. `sponsor_name`
6. `user_id`
7. `user_email`
8. `utm_source`
9. `utm_medium`
10. `utm_campaign`
11. `phase_at_click`
12. `conversion_type`
13. `created_at`

## Consent-aware Redaction Rules
If consent is not present (`conversion_type` includes `no_consent`, or metadata consent is false), export blanks:
- `user_id`
- `user_email`
- `utm_source`
- `utm_medium`
- `utm_campaign`

## File Lifecycle (TTL)
- Export files are created in uploads dir: `wp-content/uploads/khm-membership-exports/`
- Existing CSV exports older than 24 hours are deleted on export generation.
