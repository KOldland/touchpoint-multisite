Test notes (2026-02-02)

- PHPUnit currently fails before running tests due to a syntax error:
  - File: src/Adapters/GoogleAdsAdapter.php
  - Error: Unmatched '}' around line 260
- PaidAdapterContract is not in the load path for tests; a stub is provided in tests/TestHelpers.php.
  Replace the stub once the real contract is available.
