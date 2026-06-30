# Search API Contracts

## Overview

Two search endpoints are available:

| Endpoint | Path | Auth | Rate Limit |
|----------|------|------|------------|
| Internal | `POST /kh-editorial/v1/search` | Requires `edit_posts` capability | None |
| Public   | `POST /kh-editorial/v1/public/search` | Public (no auth) | 10 req/min per IP |

---

## 1. Internal Search Endpoint

### `POST /kh-editorial/v1/search`

Searches across RAG (semantic), Registry (fulltext), and SERP (external) sources.

### Request Body

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `query` | string | **Yes** | — | The search query (must not be empty). |
| `sources` | array | No | `["rag","registry","serp"]` | Sources to query. Allowed: `rag`, `registry`, `serp`. |
| `blog_id` | int | No | `null` | Filter results by blog/site ID (multisite). |
| `limit` | int | No | `10` | Results per page (max: 100). |
| `page` | int | No | `1` | Page number for pagination. |
| `filters` | object | No | `{}` | Additional JSON filters passed to sources. |
| `content_types` | array | No | `[]` | Filter by content type (e.g. `article`, `atomic_article`). |

### Response (200 OK)

```json
{
  "source": "unified",
  "total": 42,
  "page": 1,
  "per_page": 10,
  "results": [
    {
      "id": "rag:42",
      "source": "rag",
      "content_type": "article",
      "title": "Trade Finance Trends 2026",
      "excerpt": "Global trade finance is evolving with digitalisation...",
      "url": "https://example.com/trade-finance-2026",
      "score": 0.89,
      "content_type_metadata": {},
      "source_metadata": {
        "similarity": 0.89,
        "model": "text-embedding-ada-002"
      }
    }
  ],
  "aggregations": {
    "source_summary": {
      "rag": { "status": "success", "total": 15 },
      "registry": { "status": "success", "total": 27 },
      "serp": { "status": "success", "total": 8 }
    }
  }
}
```

### Error Responses

| Status | Code | Description |
|--------|------|-------------|
| 400 | `empty_query` | Search query is empty or whitespace-only. |
| 401 | `rest_not_logged_in` | User is not authenticated. |
| 403 | `rest_forbidden` | User lacks `edit_posts` capability. |
| 500 | `search_failed` | Internal search error (check error log). |

### Example

```bash
curl -X POST https://example.com/wp-json/kh-editorial/v1/search \
  -H 'Content-Type: application/json' \
  -H 'X-WP-Nonce: 12345abc' \
  -d '{"query":"trade finance trends","sources":["rag","registry"],"limit":5}'
```

---

## 2. Public Search Endpoint

### `POST /kh-editorial/v1/public/search`

Public-facing search with IP-based rate limiting. Queries RAG + Registry only (no SERP).

### Request Body

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `query` | string | **Yes** | — | The search query (max 500 chars). |

### Response (200 OK)

Same structure as internal endpoint, plus:

```json
{
  "answer": "Trade finance is evolving with digitalisation...",
  "source": "unified",
  "total": 15,
  "page": 1,
  "per_page": 10,
  "results": [],
  "aggregations": {}
}
```

The `answer` field contains either:
- A synthesised LLM-generated answer (when AI Answer Synthesis is enabled in Settings)
- The best-matching excerpt (when AI Answer Synthesis is disabled)

### Rate Limiting

- **Limit:** 10 requests per IP per 60-second window.
- **Headers:** Standard WP REST headers.
- **429 Response:**
  ```json
  {
    "code": "rate_limited",
    "message": "Too many search requests. Please wait a moment.",
    "data": { "status": 429 }
  }
  ```

### Error Responses

| Status | Code | Description |
|--------|------|-------------|
| 400 | `empty_query` | Search query is empty or whitespace-only. |
| 429 | `rate_limited` | IP has exceeded the rate limit. |
| 500 | `search_failed` | Internal search error. |

### Example

```bash
curl -X POST https://example.com/wp-json/kh-editorial/v1/public/search \
  -H 'Content-Type: application/json' \
  -d '{"query":"What is trade finance?"}'
```

---

## Search Sources

| Source | Name | Description | Used By |
|--------|------|-------------|---------|
| RAG | `rag` | Semantic search over atomic article embeddings (cosine similarity). | Internal + Public |
| Registry | `registry` | FULLTEXT search on content_registry table. Falls back to LIKE if no FULLTEXT index. | Internal + Public |
| SERP | `serp` | External web search via DataForSEO / SerpAPI. | Internal only |

---

## Caching

- Search results are cached using WP Transients with a 5-minute TTL.
- Cache key: `khm_search_{md5(query + blog_id + sources + filters)}`
- Cache can be flushed via the **Search Analytics** admin page.
- When using Redis/memcached, cache invalidation uses a version key (`khm_search_cache_version`).

---

## Monitoring

All search queries are logged to the `kh_search_log` table. Fields:

| Field | Type | Description |
|-------|------|-------------|
| `query` | varchar(500) | The search query text |
| `source` | varchar(50) | `public`, `internal` |
| `blog_id` | bigint | Site ID (multisite) |
| `result_count` | int | Number of results returned |
| `latency_ms` | int | Query execution time |
| `answer_synthesized` | tinyint | Whether LLM answer was attempted |
| `answer_success` | tinyint | Whether LLM answer succeeded (response !== excerpt) |
| `rate_limited` | tinyint | Whether request was rate-limited |
| `user_ip` | varchar(45) | Client IP (public endpoint only) |

View logs and aggregate stats at **Intelligence Admin > Search Analytics**.