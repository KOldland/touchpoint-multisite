# Contributing Golden Fixtures

Golden fixtures are deterministic contracts used by CIC to unblock parallel work across SMMA, Compliance, Membership, and Paid Adapter buckets.

## Rules

1. Contract-first: if fixture shape changes, update the related contract JSON in `docs/contracts/` in the same PR.
2. Owner approval required: any fixture change PR must include label `golden-owner-approved`.
3. No secrets/PII in fixtures: CI secret scan blocks merge on violations.
4. Metadata required: every core fixture must have `<fixture>.meta.json` with checksum, prompt hash, version, author, and created date.

## Regenerate workflow

1. Capture a recorded response payload to a local JSON file.
2. Run:

```bash
php scripts/regenerate_fixture.php \
  --input /path/to/recorded.json \
  --fixture-name generate_awareness_ok.json \
  --author @your-handle \
  --prompt-version cic-01
```

3. Review the generated fixture and sidecar metadata.
4. Open PR with reason/impact summary and request owner review.
5. Apply label `golden-owner-approved`.

## CI checks

- `golden-check`: validates required fixtures, metadata, checksums, and label policy.
- `secret-scan`: rejects committed secrets.

These checks are merge-blocking once branch protection is configured to require them.
