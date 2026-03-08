# Phase 4 Sign-off

## Status

- IN PROGRESS

## Platform exception

- Direct staging DB verification is not possible on WP Engine from the exposed SFTP/SSH node.
- Confirmed behavior:
  - SSH authentication: works
  - SSH tunnel creation: works
  - remote MySQL endpoint via SFTP host loopback: unavailable / not routable
- Accepted compensating controls for this phase:
  - local mirror schema verification using `migrations/verify.sql`
  - CI migration verification (`phase4-migrate-verify`)
  - staging HTTP smoke / load validation once staging base URL is confirmed

## Required artifacts

- release RC zip + signature
- phpunit_membership_final.log
- ci/golden-summary.json
- k6_load_report.html or text summary
- db_snapshot.sql (sanitized)
- canary_report.md
- runbooks bundle

## Human approvals pending

- production migration approval or explicit acceptance of the WP Engine DB access exception
- real Stripe production validation approval
- canary kickoff approval

## Current execution state

- local mirror schema verification: complete
- local secret scan: complete
- local unsigned RC build: complete
- local full `khm-plugin` suite: complete
- staging DB verification: blocked by WP Engine topology
- staging HTTP load/canary smoke: pending staging base URL and runnable `k6` environment
- signed RC: pending release signing key

## Known blockers

1. WP Engine does not expose a usable staging MySQL path from the SFTP/SSH node.
2. `k6` is not installed on this workstation, so load/canary smoke must run in CI or on an ops runner.
3. No release signing key is configured on this workstation, so local RC output is unsigned.
