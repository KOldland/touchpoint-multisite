# Editorial Planner Technical Specification

This document provides the technical specification for the Editorial Planner, including the REST API, job lifecycle, and data schemas.

## 1. Database Migrations

The following SQL files are located in `sql/migrations/`:

- `01_create_ep_citations.sql`
- `02_create_ep_trends.sql`
- `03_create_ep_article_ideas.sql`
- `04_create_ep_briefs.sql`

**Note:** The table creation scripts use `wp_` as a placeholder prefix. This should be replaced with the actual WordPress database prefix during deployment.

## 2. Job Lifecycle

The Editorial Planner uses a job-based system to manage the research pipeline. The lifecycle of a job is as follows:

- **`queued`**: The initial state of a job when it is created but has not yet started processing.
- **`running`**: The job is actively being processed by a worker.
- **`waiting_for_human`**: The job has completed a phase that requires human input (e.g., Citation QA) and is paused until the required action is taken.
- **`completed`**: The job has finished successfully.
- **`failed`**: The job has encountered an unrecoverable error.

## 3. REST API Specification

All endpoints are prefixed with `/wp-json/ep/v1`.

### 3.1. Start a New Session

- **Method**: `POST`
- **Path**: `/start`
- **Authentication**: `edit_posts` capability required.
- **Purpose**: Creates a new `ai_session`, enqueues the Phase 1 job(s), and returns the `session_id`.

#### Request Body

```json
{
  "type": "object",
  "properties": {
    "broad_focus": {
      "type": "array",
      "items": { "type": "string" },
      "description": "An array of broad topics to start the research from."
    },
    "granular_focus": {
      "type": "array",
      "items": { "type": "string" },
      "description": "An array of more specific topics to refine the search."
    },
    "exclusions": {
      "type": "array",
      "items": { "type": "string" },
      "description": "A list of keywords or phrases to exclude from the search."
    },
    "preferred_sources": {
      "type": "array",
      "items": { "type": "string" },
      "description": "A list of preferred sources (e.g., Gartner, IEEE)."
    },
    "sponsor_mode": {
      "type": "boolean",
      "default": false,
      "description": "When true, the agent will include sponsor materials in its research."
    },
    "idempotency_key": {
      "type": "string",
      "description": "A unique key to prevent duplicate session creation."
    }
  },
  "required": ["broad_focus", "idempotency_key"]
}
```

#### Response (200 OK)

```json
{
  "type": "object",
  "properties": {
    "session_id": {
      "type": "string",
      "format": "uuid"
    },
    "job_ids": {
      "type": "array",
      "items": {
        "type": "string",
        "format": "uuid"
      }
    },
    "status": {
      "type": "string",
      "enum": ["queued"]
    },
    "message": {
      "type": "string"
    }
  }
}
```

### 3.2. Get Session Status

- **Method**: `GET`
- **Path**: `/session/{session_id}`
- **Authentication**: `edit_posts` capability required.
- **Purpose**: Retrieves the status of a session and its associated jobs.

#### Path Parameters

- `session_id` (string, required): The UUID of the session.

#### Response (200 OK)

```json
{
  "type": "object",
  "properties": {
    "session": {
      "type": "object",
      "description": "The full session object from the database."
    },
    "jobs": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "job_id": { "type": "string", "format": "uuid" },
          "phase": { "type": "string", "enum": ["phase_1", "phase_2", "phase_3"] },
          "status": { "type": "string", "enum": ["queued", "running", "waiting_for_human", "completed", "failed"] },
          "progress": { "type": "integer", "minimum": 0, "maximum": 100 },
          "tokens_used": { "type": "integer" },
          "estimated_cost": { "type": "number" },
          "cache_hit": { "type": "boolean" }
        }
      }
    },
    "results": {
      "type": "object",
      "properties": {
        "phase_1": { "type": "object" },
        "phase_2": { "type": "object" },
        "phase_3": { "type": "object" }
      }
    }
  }
}
```

### 3.3. Submit Citation QA

- **Method**: `POST`
- **Path**: `/citation-qa/{session_id}`
- **Authentication**: `edit_posts` capability required.
- **Purpose**: Submits editor decisions on citations from Phase 2.

#### Path Parameters

- `session_id` (string, required): The UUID of the session.

#### Request Body

```json
{
  "type": "object",
  "properties": {
    "approved_citation_ids": {
      "type": "array",
      "items": { "type": "string" }
    },
    "rejected_citation_ids": {
      "type": "array",
      "items": { "type": "string" }
    },
    "additional_keywords": {
      "type": "array",
      "items": { "type": "string" }
    }
  },
  "required": ["approved_citation_ids", "rejected_citation_ids"]
}
```

#### Response (200 OK)

```json
{
  "type": "object",
  "properties": {
    "job_id": {
      "type": "string",
      "format": "uuid"
    },
    "status": {
      "type": "string",
      "enum": ["queued"]
    }
  }
}
```

### 3.4. Regenerate Citations

- **Method**: `POST`
- **Path**: `/regenerate-citations/{session_id}`
- **Authentication**: `edit_posts` capability required.
- **Purpose**: Enqueues a new job to regenerate the Phase 2 citations.

#### Path Parameters

- `session_id` (string, required): The UUID of the session.

#### Request Body

```json
{
  "type": "object",
  "properties": {
    "idempotency_key": {
      "type": "string"
    }
  },
  "required": ["idempotency_key"]
}
```

#### Response (202 Accepted)

```json
{
  "type": "object",
  "properties": {
    "job_id": {
      "type": "string",
      "format": "uuid"
    },
    "status": {
      "type": "string",
      "enum": ["queued"]
    },
    "message": {
      "type": "string"
    }
  }
}
```

### 3.5. Generate Article Ideas

- **Method**: `POST`
- **Path**: `/generate-article-ideas/{session_id}`
- **Authentication**: `edit_posts` capability required.
- **Purpose**: Enqueues the Phase 3 job to generate the final topics and article ideas.

#### Path Parameters

- `session_id` (string, required): The UUID of the session.

#### Response (202 Accepted)

```json
{
  "type": "object",
  "properties": {
    "job_id": {
      "type": "string",
      "format": "uuid"
    },
    "status": {
      "type": "string",
      "enum": ["queued"]
    },
    "message": {
      "type": "string"
    }
  }
}
```

### 3.6. Get Final Brief

- **Method**: `GET`
- **Path**: `/brief/{session_id}`
- **Authentication**: `edit_posts` capability required.
- **Purpose**: Retrieves the final research brief.

#### Path Parameters

- `session_id` (string, required): The UUID of the session.

#### Response (200 OK)

```json
{
  "type": "object",
  "properties": {
    "id": { "type": "string", "format": "uuid" },
    "session_id": { "type": "string", "format": "uuid" },
    "executive_summary": { "type": "string" },
    "context": { "type": "string" },
    "application": { "type": "object" },
    "observations": { "type": "object" },
    "key_themes": {
      "type": "array",
      "items": { "$ref": "#/definitions/trend" }
    },
    "citations": {
      "type": "array",
      "items": { "$ref": "#/definitions/citation" }
    },
    "writer_guidance": { "type": "string" },
    "produced_at": { "type": "string", "format": "date-time" }
  },
  "definitions": {
    "trend": {
      "type": "object",
      "properties": {
        "id": { "type": "string", "format": "uuid" },
        "title": { "type": "string" },
        "summary": { "type": "string" },
        "prevalence_score": { "type": "number" },
        "article_ideas": {
          "type": "array",
          "items": { "$ref": "#/definitions/article_idea" }
        }
      }
    },
    "article_idea": {
      "type": "object",
      "properties": {
        "id": { "type": "string", "format": "uuid" },
        "title": { "type": "string" },
        "three_sentence_summary": { "type": "string" },
        "key_points": { "type": "array", "items": { "type": "string" } },
        "recommended_length": { "type": "string", "enum": ["short", "medium", "long"] },
        "rating": { "type": "string", "enum": ["highly_requested", "emerging", "undercovered"] }
      }
    },
    "citation": {
      "type": "object",
      "properties": {
        "id": { "type": "string", "format": "uuid" },
        "title": { "type": "string" },
        "url": { "type": "string", "format": "uri" },
        "publication": { "type": "string" },
        "apa_string": { "type": "string" }
      }
    }
  }
}
```
