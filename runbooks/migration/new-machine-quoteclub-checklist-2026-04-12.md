# New Machine Migration Checklist (Quote Club + Dual GPT)

## Source Of Truth
- Repository root: /Users/kris/Local Sites/touchpoint-template-final-build
- Runtime tree to launch from this repo: app/public
- Integrity manifest: artifacts/migration_2026-04-12/quoteclub_manifest.sha1

## Pre-Move Export (Current Machine)
1. Archive source tree:
   - tar -czf touchpoint-template-final-build-2026-04-12.tgz touchpoint-template-final-build
2. Export database from app/public if needed:
   - cd app/public
   - wp db export ../../artifacts/migration_2026-04-12/pre-move.sql
3. Save uploads/media backup if you need full content parity.

## New Machine Setup
1. Install prerequisites:
   - PHP 8.5.x CLI
   - Composer 2.9+
   - WP-CLI 2.12+
   - MySQL/MariaDB
2. Extract project:
   - tar -xzf touchpoint-template-final-build-2026-04-12.tgz
3. Configure runtime in your local web stack to point to:
   - touchpoint-template-final-build/app/public
4. Import DB and set site URLs if required.

## Post-Move Validation
1. Verify checksums:
   - ./scripts/migration/verify_quoteclub_manifest.sh
2. Run smoke checks:
   - ./scripts/migration/quoteclub_smoke_check.sh
3. Manual UI checks:
   - Quote Club toolbar shows multi-select categories, topics input, AND/OR helper text, Saved Searches placeholder.
   - Search with all categories selected + no topics/keywords returns results.
   - Topic suggestions return category names.

## Quick Recovery Steps
1. If checksum fails, re-copy the mismatched file(s) from template source.
2. If smoke checks fail, run:
   - php -l on the file listed by the script
3. If UI is stale, flush cache:
   - cd app/public
   - wp cache flush
