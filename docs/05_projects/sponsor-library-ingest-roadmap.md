# Sponsor Library Ingest Roadmap

## Sprint 1 (Implemented)

### Outcome
Bulk sponsor library ingestion is now queue-based and asynchronous.

### Delivered
- Bulk URL import from multiline text input.
- CSV URL import (`url,title,authors,publisher,pub_date`) from Sponsor Library admin page.
- Ingest jobs queue table with processing status and counters.
- Background processing via scheduled event (`khm_sponsor_process_ingest_job`).
- Duplicate URL skip per sponsor during ingest.
- New Sponsor Library admin sections:
  - **Bulk Import Sponsor Documents** form
  - **Bulk Import Jobs** status table
- REST API support:
  - `POST /wp-json/khm-geo/v1/sponsor-docs/bulk-import`
  - `GET /wp-json/khm-geo/v1/sponsor-docs/ingest-jobs`

### Notes
- Imported docs are created as `approved = 0` (manual approval retained).
- Ingest source metadata is written to doc `meta` (`source=bulk_ingest`, `job_id`, timestamp).

---

## Sprint 2 (To Do)

### Goal
Support a single sponsor “library URL” that can be crawled and synced into sponsor docs.

### Backlog Items
1. **Source Registry**
   - Add `sponsor_sources` table for URL source definitions (root URL, allowlist rules, depth, limits, schedule).

2. **Crawler Worker**
   - Build background crawler pipeline:
     - fetch HTML
     - extract canonical URL/title/date/body summary
     - normalize and hash content
     - upsert into sponsor docs

3. **Safety & Compliance Guardrails**
   - Respect `robots.txt` and noindex directives.
   - Domain allowlist enforcement per sponsor source.
   - Max pages, max depth, max response size, content-type filtering.

4. **Incremental Re-crawl**
   - Track `last_crawled_at`, `etag`, `last_modified`, and content hash.
   - Skip unchanged pages.

5. **Approval Workflow Integration**
   - Keep imported pages in pending state by default.
   - Bulk approve/reject actions for crawler imports.

6. **Admin UX**
   - Add “Library Sources” section:
     - create/edit source
     - run now
     - pause/resume
     - last run health + error logs

7. **Observability**
   - Job-level metrics (queued/processing/completed/failed).
   - Error buckets (DNS, timeout, parse, blocked by rules).
   - Audit log links from source run to imported docs.

### Suggested Acceptance Criteria
- Admin can add one root URL and run ingest without manual per-document entry.
- Crawl obeys configured limits and allowlist rules.
- Duplicate pages are not reinserted.
- Imported pages appear in Sponsor Documents and require approval before use.
- Job status and failures are visible in admin.

---

## Sprint 1.5 (Demo-Critical) - Implemented

### Delivered in this branch
- **Source Registry + UI**
   - Add source URL per sponsor, with allowlist and crawl limits.
   - Edit source limits/URL and pause/activate source.
   - Run source crawl immediately from Sponsor Library admin.
- **Crawler MVP**
   - Same-domain crawl with configurable max pages/depth/response size.
   - Queued background processing through ingest jobs.
   - Crawled pages are inserted/updated as sponsor docs in pending approval state.
- **Guardrails**
   - Domain allowlist enforcement.
   - `robots.txt` disallow parsing for wildcard user-agent rules.
   - `noindex` detection from meta robots and `X-Robots-Tag`.
- **Job Visibility**
   - Status/progress/success/fail counts displayed in Sponsor Library jobs table.
   - Error messages surfaced in the jobs table.
- **Incremental Sync**
   - Skip unchanged pages using `etag`, `last_modified`, and `content_hash` metadata.
   - Existing docs are updated and reset to pending approval when content changes.
- **Bulk Approve Imported**
   - One-click bulk approval for pending docs imported via `bulk_ingest` or `crawl`.

### Demo operator flow
1. Add sponsor in Sponsor Library.
2. Add Library Source URL and allowlist.
3. Click **Run Now** on the source.
4. Watch progress/errors in **Bulk Import Jobs**.
5. Approve imported docs individually or use **Bulk Approve Imported Docs**.
6. Use sponsor-linked docs in session planning and content generation.
