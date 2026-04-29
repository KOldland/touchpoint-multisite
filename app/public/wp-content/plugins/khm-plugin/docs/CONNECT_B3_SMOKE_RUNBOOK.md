# Connect B3 Smoke Runbook

## Purpose

This smoke test validates the buyer-side Connect B3 flow end-to-end:

1. public shortlist endpoint responds and returns providers
2. intro thread creation succeeds
3. buyer status endpoint works using token alias
4. buyer handover request updates handover state

Primary script:

- `wp-content/plugins/khm-plugin/artifacts/smoke_connect_b3.php`

Convenience wrapper:

- `wp-content/plugins/khm-plugin/smoke-test-connect-b3.sh`

## Local execution

From WordPress root (`app/public`):

```bash
wp --skip-plugins=pojo-accessibility eval-file wp-content/plugins/khm-plugin/artifacts/smoke_connect_b3.php
```

Or:

```bash
wp-content/plugins/khm-plugin/smoke-test-connect-b3.sh
```

A successful run prints:

- `Step 1 OK: shortlist returned ...`
- `Step 2 OK: created thread ...`
- `Step 3 OK: status loaded ...`
- `Step 4 OK: handover_status=...`
- `PASS: Connect B3 smoke flow completed successfully.`

## CI execution

Workflow:

- `.github/workflows/connect-b3-smoke.yml`

Trigger:

- pull requests touching `wp-content/plugins/khm-plugin/**`
- manual `workflow_dispatch`

The CI job provisions MySQL, creates a temporary WordPress config, installs WordPress, activates `khm-plugin`, and runs the smoke script.

CI debugging aid:

- On every run (pass or fail), the workflow uploads `connect-b3-smoke-log` containing `ci-logs/connect-b3-smoke.log`.

## Expected noise in local environment

These are known non-blocking local warnings and should not be treated as smoke failures:

- WP-CLI deprecation warnings from bundled WP-CLI dependencies
- session header warnings from `wp-content/mu-plugins/000-session-config.php`

The source of truth is the script exit code and the `PASS:` line.

## Failure signatures to treat as blockers

- `FAIL: shortlist request did not return 200`
- `FAIL: shortlist returned no providers` (after seed retry)
- `FAIL: intro create did not return top-level id`
- `FAIL: status request did not return 200`
- `FAIL: handover_status was not updated after request`

## Troubleshooting checklist

1. Confirm plugin is active: `wp plugin is-active khm-plugin`
2. Confirm REST root resolves: `wp eval 'echo home_url("/wp-json/khm/v1/connect/");'`
3. Re-run smoke directly (not wrapper) to inspect exact fail line
4. If shortlist is empty locally, ensure seed fixtures are available in:
   - `wp-content/plugins/khm-plugin/artifacts/seed_connect_validation.php`
