# Editorial Planner API Test Fixtures

This document provides a suite of cURL commands and expected responses to test the Editorial Planner API.

**Note:** Replace `http://localhost` with your actual development URL and `YOUR_NONCE` with a valid WordPress REST nonce.

## 1. Start a New Session

### cURL Command

```bash
curl -X POST http://localhost/wp-json/ep/v1/start \
-H "Content-Type: application/json" \
-H "X-WP-Nonce: YOUR_NONCE" \
-d '{
  "broad_focus": ["field service transformation", "asset analytics"],
  "granular_focus": ["predictive maintenance", "dynamic scheduling"],
  "exclusions": ["vendor PR", "marketing content"],
  "preferred_sources": ["Gartner", "IEEE", "Field Service News"],
  "sponsor_mode": false,
  "idempotency_key": "user-123-plan-2026-02-10"
}'
```

### Expected Response (200 OK)

```json
{
  "session_id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
  "job_ids": ["j1b2c3d4-e5f6-7890-1234-567890abcdef"],
  "status": "queued",
  "message": "Session created; Phase 1 queued."
}
```

---

## 2. Get Session Status

### cURL Command

```bash
curl -X GET http://localhost/wp-json/ep/v1/session/a1b2c3d4-e5f6-7890-1234-567890abcdef \
-H "X-WP-Nonce: YOUR_NONCE"
```

### Expected Response (200 OK)

```json
{
  "session": {
    "id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
    "status": "running"
  },
  "jobs": [
    {
      "job_id": "j1b2c3d4-e5f6-7890-1234-567890abcdef",
      "phase": "phase_1",
      "status": "running",
      "progress": 72,
      "tokens_used": 1200,
      "estimated_cost": 0.05,
      "cache_hit": false
    },
    {
      "job_id": "j2b2c3d4-e5f6-7890-1234-567890abcdef",
      "phase": "phase_2",
      "status": "queued",
      "progress": 0,
      "tokens_used": 0,
      "estimated_cost": 0,
      "cache_hit": false
    }
  ],
  "results": {
    "phase_1": {
        "message": "Phase 1 is still in progress."
    }
  }
}
```
---

## 3. Submit Citation QA

### cURL Command

```bash
curl -X POST http://localhost/wp-json/ep/v1/citation-qa/a1b2c3d4-e5f6-7890-1234-567890abcdef \
-H "Content-Type: application/json" \
-H "X-WP-Nonce: YOUR_NONCE" \
-d '{
  "approved_citation_ids": ["cid1-abc", "cid2-def"],
  "rejected_citation_ids": ["cid3-ghi"],
  "additional_keywords": ["NHS", "cancer laser treatment"]
}'
```

### Expected Response (200 OK)

```json
{
  "job_id": "j3b2c3d4-e5f6-7890-1234-567890abcdef",
  "status": "queued",
  "message": "Phase 3 generation job has been queued."
}
```
---

## 4. Regenerate Citations

### cURL Command

```bash
curl -X POST http://localhost/wp-json/ep/v1/regenerate-citations/a1b2c3d4-e5f6-7890-1234-567890abcdef \
-H "Content-Type: application/json" \
-H "X-WP-Nonce: YOUR_NONCE" \
-d '{
  "idempotency_key": "regenerate-2026-02-10-1"
}'
```

### Expected Response (202 Accepted)

```json
{
  "job_id": "j4b2c3d4-e5f6-7890-1234-567890abcdef",
  "status": "queued",
  "message": "Citation regeneration job accepted."
}
```
---

## 5. Generate Article Ideas

### cURL Command

```bash
curl -X POST http://localhost/wp-json/ep/v1/generate-article-ideas/a1b2c3d4-e5f6-7890-1234-567890abcdef \
-H "Content-Type: application/json" \
-H "X-WP-Nonce: YOUR_NONCE"
```

### Expected Response (202 Accepted)

```json
{
  "job_id": "j5b2c3d4-e5f6-7890-1234-567890abcdef",
  "status": "queued",
  "message": "Phase 3 article idea generation job has been queued."
}
```
---

## 6. Get Final Brief

### cURL Command

```bash
curl -X GET http://localhost/wp-json/ep/v1/brief/a1b2c3d4-e5f6-7890-1234-567890abcdef \
-H "X-WP-Nonce: YOUR_NONCE"
```

### Expected Response (200 OK)

```json
{
  "id": "b1b2c3d4-e5f6-7890-1234-567890abcdef",
  "session_id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
  "executive_summary": "...",
  "context": "...",
  "application": {},
  "observations": {},
  "key_themes": [
    {
      "id": "t1b2c3d4-e5f6-7890-1234-567890abcdef",
      "title": "Trend 1: Predictive Maintenance in Manufacturing",
      "summary": "...",
      "prevalence_score": 0.85,
      "article_ideas": [
        {
          "id": "idea1-abc",
          "title": "How Predictive Maintenance is Revolutionizing the Factory Floor",
          "three_sentence_summary": "...",
          "key_points": ["...", "...", "..."],
          "recommended_length": "medium",
          "rating": "highly_requested"
        }
      ]
    }
  ],
  "citations": [
    {
      "id": "cid1-abc",
      "title": "The Rise of Predictive Maintenance",
      "url": "http://example.com/article1",
      "publication": "TechCrunch",
      "apa_string": "..."
    }
  ],
  "writer_guidance": "...",
  "produced_at": "2026-02-12T10:00:00Z"
}
```