# Phase 5: Search API Implementation — Session Handover

**Date:** 30 June 2026  
**Status:** Complete ✅  
**Next:** Phase 6 — Deployment Strategy ❌

---

## What Was Built

### 1. Unified Search System (13 PHP files)

All under `kh-editorial-intelligence/src/Search/`:

| File | Purpose |
|---|---|
| `Interfaces/SearchSourceInterface.php` | Contract: `search(SearchQuery): ResultSet`, `supports(SearchQuery): bool`, `name(): string` |
| `Models/SearchQuery.php` | Query value object with `from_rest_request()` factory |
| `Models/SearchResult.php` | Standardised result with `get_id()`, `to_array()`, `content_type` for future extensibility |
| `Models/ResultSet.php` | Collection with `merge()`, `deduplicate()`, `sort()`, `slice()`, `to_array()` |
| `Sources/RAGSource.php` | Semantic search via `AtomicEmbeddingService` — pagination, `blog_id` filter, query embedding caching |
| `Sources/RegistrySource.php` | Fulltext search via `ContentRegistryService::search_articles_with_filters()` — JSON_CONTAINS for `geo_flags`, `seo_metadata`, `smma_flags` |
| `Sources/SERPSource.php` | External search via `SearchProvider` |
| `SearchOrchestrator.php` | Routes queries to registered sources, merges, deduplicates, sorts by score, paginates |
| `GapAnalyzer.php` | Cross-references SERP topics vs registry to identify content gaps |
| `Services/AnswerSynthesizer.php` | LLM-based answer synthesis with graceful fallback to best excerpt |
| `API/SearchEndpoint.php` | Internal endpoint (`POST /kh-editorial/v1/search`, requires `edit_posts`) |
| `API/PublicSearchEndpoint.php` | Public endpoint (`POST /kh-editorial/v1/public/search`, IP rate-limited 10/min) |
| `Frontend/SearchShortcode.php` | `[khm_site_search]` shortcode with theme/placeholder/title attributes |

### 2. Public-Facing Search UI (3 files)

| File | Purpose |
|---|---|
| `assets/js/site-search.js` | Debounced input (400ms), form submission, spinner, error/empty states, renders "Best Match" + "Related Content" with source badges |
| `assets/css/site-search.css` | Clean responsive design, light/dark theme support via `.khm-site-search-theme-dark` |
| `Frontend/SearchShortcode.php` | Backend: enqueues JS/CSS, localizes WP REST URL + nonce, renders widget markup |

### 3. Answer Synthesis (LLM integration)

- **`AnswerSynthesizer.php`** — Sends top 5 results + query to LLM for a 2–4 sentence answer
- **Fallback chain:** `openrouter/free` → `deepseek/deepseek-v4-flash` → `gpt-4o-mini` (all via OpenRouter)
- **Graceful degradation:** If no LLM configured, answers disabled, or all models fail → returns best excerpt
- **Frontend:** JS converts markdown bold `**text**` to HTML `<strong>` tags, `\n` to `<br>`

### 4. Admin Settings (API Settings → Models)

**"AI Answer Synthesis" subsection** (global, not per-profile):

| Setting | Key | Default |
|---|---|---|
| Enable checkbox | `enable_ai_answers` | `1` (ON) |
| Primary model | `ai_answer_model` | `openrouter/free` |
| Secondary model | `ai_answer_fallback` | `deepseek/deepseek-v4-flash` |
| Tertiary model | `ai_answer_tertiary` | `gpt-4o-mini` |

### 5. Schema Migrations (2 files)

| File | Purpose |
|---|---|
| `migrations/20260704_add_fulltext_indexes.php` | FULLTEXT index on `content_registry(title, content_body, excerpt)` |
| `migrations/20260705_add_blog_id_to_embeddings.php` | `blog_id` column on `atomic_embeddings` for multisite scoping |

### 6. Modified Plugin Files (3)

| File | Changes |
|---|---|
| `kh-editorial-intelligence.php` | Registers `SearchEndpoint`, `PublicSearchEndpoint`, and `SearchShortcode` via `register_activation_hook` / `init` |
| `ContentRegistryService.php` | Added `search_articles_with_filters()` with JSON_CONTAINS filtering; `CHECK FULLTEXT` + LIKE fallback in `search_articles()` |
| `AtomicEmbeddingService.php` | Added `get_embeddings_paginated()`, `store_embedding_with_blog()` |
| `LLMService.php` | Added `openrouter/free` → `'OpenRouter Free (auto-routed)'` to `ALL_MODELS` |

---

## Key Design Decisions

1. **Not a standalone plugin** — unified search lives in `kh-editorial-intelligence` (already the orchestrator for AI/LLM services)
2. **Provider/adapter pattern** — `SearchSourceInterface` means new sources (podcasts, video, white papers) just implement the interface and register with `SearchOrchestrator`
3. **Quote Club stays independent** — its sponsor-specific search (`kh-quote-club`) is untouched; it can consume the unified endpoint if needed but that's a future refactoring opportunity
4. **Free-tier first** — `openrouter/free` is the default answer synthesis model; no API key required for basic functionality
5. **No AI API calls required for search** — RAG uses cosine similarity over local embeddings, Registry uses MySQL FULLTEXT, only the optional answer synthesis calls an LLM

---

## Verification Steps (for the next session)

1. Open `https://touchpoint-multisite.local/wp-admin/admin.php?page=kh-editorial-settings` → Models section → "AI Answer Synthesis" subsection
2. Toggle the checkbox, verify it saves and the dropdown defaults are `openrouter/free`, `deepseek/deepseek-v4-flash`, `gpt-4o-mini`
3. Add `[khm_site_search]` to any page and test the public search widget
4. Test `POST /kh-editorial/v1/public/search` with `{ "query": "test" }` — should return results without auth
5. Test `POST /kh-editorial/v1/search` with `{ "query": "test", "sources": ["rag", "registry", "serp"] }` — requires `edit_posts` capability

---

## Phase 6 (Next): Deployment Strategy

The next phase should plan for:
- CI/CD pipeline for the search assets (JS/CSS minification, versioning)
- Migration orchestration (running the 2 new migrations on staging/prod)
- Caching strategy for public search endpoint (e.g. Redis or WP Transients for popular queries)
- Performance benchmarks (RAG query against 10K+ embeddings)
- Monitoring: track search query volume, answer synthesis success rate, rate-limit hits