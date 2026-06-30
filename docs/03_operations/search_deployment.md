# Search Deployment Runbook

## Prerequisites

- **Plugin version:** `kh-editorial-intelligence` v0.1.0+
- **Node.js:** 18+ (for asset build)
- **PHP:** 8.0+
- **MySQL:** 8.0+ (for FULLTEXT index)
- **Required tables:** `content_registry` (kh-content-registry plugin), `atomic_embeddings` (GEO)

---

## Step 1: Build Assets

```bash
cd wp-content/plugins/kh-editorial-intelligence
npm install
npm run build
```

This produces minified files in `assets/dist/`:
- `site-search.min.js`
- `site-search-styles.min.css`

The build also creates `site-search-styles.min.js` (empty, expected by webpack's CSS extraction).

**Cache busting:** The plugin automatically uses `filemtime()` of the dist files as the version string when enqueuing. No manual version bumping needed.

---

## Step 2: Run Database Migrations

### Via Admin UI (recommended)

1. Navigate to **Editorial Studio > Settings > Database Init**
2. Click **"Run Pending Migrations"**
3. Verify migration status shows both migrations as ✅ Success

### Via WP-CLI (headless deployments)

```bash
wp eval-file wp-content/plugins/kh-editorial-intelligence/migrations/run_migrations.php
```

Or run each individually:

```bash
wp eval-file app/public/migrations/20260704_add_fulltext_indexes.php
wp eval-file app/public/migrations/20260705_add_blog_id_to_embeddings.php
```

### What Migrations Do

| Migration | Table | Change |
|-----------|-------|--------|
| `20260704_add_fulltext_indexes` | `content_registry` | Adds `idx_fulltext_search` FULLTEXT index on `(title, content_body, excerpt)` |
| `20260705_add_blog_id_to_embeddings` | `atomic_embeddings` | Adds `blog_id` column + index for multisite RAG scope |

### Idempotency

Both migrations are idempotent:
- FULLTEXT index: Checks `SHOW INDEX` before creating
- blog_id column: Checks `SHOW COLUMNS` before adding
- The MigrationRunner logs each run to `kh_migration_log` and skips already-executed migrations

**Fresh database:** All migrations will execute on first "Run Pending Migrations" click.

**Existing database:** Only unexecuted migrations will run. Safe to click repeatedly.

---

## Step 3: Post-Deploy Verification

### 3.1 Verify FULLTEXT Index

```sql
SHOW INDEX FROM wp_content_registry WHERE Key_name = 'idx_fulltext_search';
```

Expected: One row with `Index_type = FULLTEXT`.

### 3.2 Verify blog_id Column

```sql
SHOW COLUMNS FROM wp_atomic_embeddings LIKE 'blog_id';
```

Expected: One row with `Field = blog_id`, `Type = bigint(20) unsigned`, `Null = NO`, `Default = 0`.

### 3.3 Verify Minified Assets Load

Check the frontend page source where `[khm_site_search]` is used:

```html
<link rel='stylesheet' id='khm-site-search-css' href='/wp-content/plugins/kh-editorial-intelligence/assets/dist/site-search-styles.min.css?ver=1234567890' />
<script src='/wp-content/plugins/kh-editorial-intelligence/assets/dist/site-search.min.js?ver=1234567890'></script>
```

The `ver` parameter should be a Unix timestamp (file mtime), not the plugin version.

### 3.4 Verify Caching

Search for the same query twice within 5 minutes:

```bash
# First request (cache miss — slower)
curl -s -X POST https://example.com/wp-json/kh-editorial/v1/public/search \
  -H 'Content-Type: application/json' \
  -d '{"query":"trade finance"}' -w '\nTime: %{time_total}s\n'

# Second request (cache hit — faster)
curl -s -X POST https://example.com/wp-json/kh-editorial/v1/public/search \
  -H 'Content-Type: application/json' \
  -d '{"query":"trade finance"}' -w '\nTime: %{time_total}s\n'
```

Second request should be significantly faster (typically <50ms vs 200-500ms).

### 3.5 Verify Logging

Check **Editorial Studio > Settings > Search Analytics** for recent query entries after running a few searches.

---

## Step 4: Cache Warm-Up (Optional)

If you expect high traffic after deployment, pre-cache popular queries:

```php
// wp-cli or custom script
$cache = new \KH\Editorial\Search\Services\SearchCache();
$popular_queries = ['trade finance', 'supply chain', 'ESG'];

foreach ($popular_queries as $q) {
    $orchestrator = new \KH\Editorial\Search\SearchOrchestrator();
    $query = new \KH\Editorial\Search\Models\SearchQuery(query: $q, sources: ['rag', 'registry']);
    $result = $orchestrator->search($query);
    echo "Warmed: {$q}\n";
}
```

---

## Step 5: Monitoring (First Hour)

### Watch for:

1. **Answer synthesis failure rate** — Check Search Analytics page. Rate should be <20%.
   - If >20%, check the LLM API keys and model availability in Settings.
2. **Rate-limit hits** — If many 429 responses, consider increasing the limit or adding additional caching.
3. **Latency spikes** — Avg latency >1000ms for public endpoint may indicate embedding generation issues.

### Alert Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| Synthesis failure rate (1h) | >10% | >20% |
| Rate-limit hits/day | >100 | >500 |
| Avg latency public search | >500ms | >2000ms |

---

## Rollback Procedure

### 1. Revert Asset Changes

```bash
cd wp-content/plugins/kh-editorial-intelligence
git checkout -- assets/dist/ package.json webpack.config.js
```

Or restore from backup if not using git.

### 2. Revert Code Changes

```bash
cd wp-content/plugins/kh-editorial-intelligence
git checkout -- src/Database/MigrationRunner.php
git checkout -- src/Database/SearchLogSchema.php
git checkout -- src/Search/Services/SearchCache.php
git checkout -- src/Search/Services/SearchLogger.php
git checkout -- src/Admin/SearchAnalyticsPage.php
git checkout -- src/Admin/EditorialAdmin.php  # careful — may overwrite other admin changes
git checkout -- src/Search/SearchOrchestrator.php
git checkout -- src/Search/Frontend/SearchShortcode.php
git checkout -- src/Search/API/PublicSearchEndpoint.php
git checkout -- kh-editorial-intelligence.php
```

### 3. Roll Back Migrations (if needed)

```sql
-- Drop FULLTEXT index
ALTER TABLE wp_content_registry DROP INDEX idx_fulltext_search;

-- Drop blog_id column
ALTER TABLE wp_atomic_embeddings DROP COLUMN blog_id, DROP INDEX idx_blog_id;

-- Drop tracking tables
DROP TABLE IF EXISTS wp_kh_migration_log;
DROP TABLE IF EXISTS wp_kh_search_log;
```

**Note:** Search functionality degrades gracefully without these:
- Without FULLTEXT index, RegistrySource falls back to `LIKE` queries (slower but works)
- Without `blog_id` column, RAG returns results from all sites (no multisite scoping)

---

## Architecture Notes

### Data Flow

```
[Site Search Widget] → REST API → SearchOrchestrator
                                    ├─ Cache (check → store)
                                    ├─ RAGSource (semantic)
                                    ├─ RegistrySource (fulltext)
                                    └─ SERPSource (external, internal only)
                                    
SearchOrchestrator → Merge → Deduplicate → Sort → Paginate → Response
                         ↘ Log to kh_search_log
```

### Dependencies

| Component | Requires | Notes |
|-----------|----------|-------|
| RAGSource | `atomic_embeddings` table, embedding model | Falls back if no embeddings |
| RegistrySource | `content_registry` table | Uses FULLTEXT or LIKE fallback |
| SERPSource | DataForSEO API key | Internal endpoint only |
| SearchCache | WP Transients | Redis supported via `wp_using_ext_object_cache()` |

---

## Troubleshooting

### "Search failed" errors

1. Check `wp-content/debug.log` for `[KH Search]` or `[KH Public Search]` entries.
2. Verify required tables exist:
   ```sql
   SHOW TABLES LIKE 'wp_content_registry';
   SHOW TABLES LIKE 'wp_atomic_embeddings';
   ```
3. Check migration status in Database Init admin page.

### Minified assets not loading

1. Verify `assets/dist/site-search.min.js` exists.
2. Check browser DevTools Network tab for 404s.
3. Confirm `npm run build` completed without errors.
4. Verify `KH_EDITORIAL_PLUGIN_URL` constant resolves correctly.

### Cache not working

1. Check `wp_options` table for `_transient_khm_search_*` entries.
2. If using Redis, check `wp_using_ext_object_cache()` returns true.
3. Flush cache from Search Analytics page and try again.

### Rate limiting too aggressive

Modify `RATE_LIMIT_MAX` and `RATE_LIMIT_WINDOW` constants in `src/Search/API/PublicSearchEndpoint.php`.