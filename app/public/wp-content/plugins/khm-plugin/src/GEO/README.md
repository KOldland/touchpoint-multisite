# GEO AnswerCard Suggestion Service

## Overview

The GEO (Generative Engine Optimization) AnswerCard Suggestion Service provides AI-powered content analysis and structured Q&A generation for WordPress. It integrates with the Gutenberg editor to suggest AnswerCards optimized for AI citation and featured snippets.

## Features

- **AI-Powered Suggestions**: Analyzes article content and generates structured AnswerCards
- **Schema Validation**: Strict JSON schema validation with automatic retry
- **Intelligent Caching**: Content-hash based caching (24h TTL default)
- **Rate Limiting**: Per-user throttling to control API costs
- **Audit Logging**: Complete request/response logging for debugging and cost tracking
- **Gutenberg Integration**: Seamless sidebar plugin with preview/insert workflow

## Architecture

```
src/GEO/
├── SuggestAnswerCardsEndpoint.php   # REST API endpoint
├── LLMClient.php                     # OpenAI API wrapper with retry
├── AnswerCardSchemaValidator.php     # JSON schema validation
├── SuggestionCacheManager.php        # Transient/Redis caching
├── RateLimiter.php                   # Per-user rate limiting
└── SuggestionAuditLogger.php         # Audit trail logging

src/Blocks/answer-card/src/
├── index.js                          # Block editor component
├── suggest-plugin.js                 # Suggestion sidebar plugin
└── suggest-modal.scss               # Modal styling
```

## Installation

### 1. Environment Variables

Set the OpenAI API key using one of these methods:

**Option A: Environment Variable (Recommended for production)**
```bash
export OPENAI_API_KEY="sk-..."
```

**Option B: WordPress Constant in wp-config.php**
```php
define( 'OPENAI_API_KEY', 'sk-...' );
```

**Option C: WordPress Option (Admin UI - coming soon)**
```php
update_option( 'khm_geo_openai_api_key', 'sk-...' );
```

### 2. Build Assets

Navigate to the block directory and build:

```bash
cd wp-content/plugins/khm-plugin/src/Blocks/answer-card
npm install
npm run build
```

### 3. Database Tables

The audit log table is created automatically on first request. To manually create:

```php
$logger = new \KHM\GEO\SuggestionAuditLogger();
$logger->create_table();
```

## Configuration

### Environment Variables / Constants

| Variable | Description | Default |
|----------|-------------|---------|
| `OPENAI_API_KEY` | OpenAI API key (required) | - |
| `KHM_GEO_LLM_MODEL` | LLM model to use | `gpt-4o-mini` |
| `KHM_GEO_CACHE_TTL` | Cache TTL in seconds | `86400` (24h) |
| `KHM_GEO_RATE_LIMIT_MINUTE` | Requests per minute per user | `3` |
| `KHM_GEO_RATE_LIMIT_DAY` | Requests per day per user | `50` |
| `KHM_GEO_DISABLE_RATE_LIMIT` | Disable rate limiting | `false` |

### WordPress Options

```php
// Model selection
update_option( 'khm_geo_llm_model', 'gpt-4o' );

// Cache TTL (seconds)
update_option( 'khm_geo_cache_ttl', 86400 );

// Rate limits
update_option( 'khm_geo_rate_limit_minute', 3 );
update_option( 'khm_geo_rate_limit_day', 50 );

// Exempt admins from rate limits
update_option( 'khm_geo_rate_limit_exempt_admins', true );

// Audit log retention (days)
update_option( 'khm_geo_audit_retention_days', 90 );
```

## API Reference

### POST /wp-json/khm-geo/v1/suggest-answercards

Generate AnswerCard suggestions for an article.

**Authentication**: Requires `edit_posts` capability

**Request Body**:
```json
{
    "post_id": 123,
    "title": "Article title",
    "url": "https://example.com/article",
    "content": "Full plain-text article content...",
    "max_cards": 4
}
```

**Success Response (200)**:
```json
{
    "cards": [
        {
            "question": "What is GEO?",
            "concise_answer": "GEO (Generative Engine Optimization) is...",
            "key_points": ["Point 1", "Point 2", "Point 3"],
            "citations": [{"title": "Source", "url": "https://..."}],
            "entities": [{"name": "SEO", "sameAs": "https://..."}],
            "confidence": 0.85,
            "notes": "Optional reviewer notes"
        }
    ],
    "model": "gpt-4o-mini",
    "generated_at": "2026-01-14 10:30:00"
}
```

**Response Headers**:
- `X-KHM-GEO-Cache: HIT|MISS` - Indicates cache status

**Error Responses**:
- `401` - Unauthorized (insufficient permissions)
- `429` - Rate limit exceeded
- `422` - JSON validation failure after retry
- `500` - Server error

## Caching

### Cache Key Format
```
khm_geo_suggest:{sha256(normalized_content)}:{max_cards}:{model_version}
```

### Cache Backends

**WordPress Transients (Default)**
- Suitable for low-traffic sites
- Data stored in `wp_options` table
- Automatically cleaned up

**Redis Object Cache (Recommended)**
- Install Redis object cache plugin
- Detected automatically when available
- Better performance for high traffic

### Cache Invalidation

Cache naturally invalidates when content changes (due to content hashing). No explicit invalidation required.

To manually clear all caches:
```php
$cache = new \KHM\GEO\SuggestionCacheManager();
$cache->clear_all();
```

## Rate Limiting

Default limits:
- **3 requests per minute** per user
- **50 requests per day** per user

When exceeded, returns `429 Too Many Requests` with:
```json
{
    "code": "rate_limit_exceeded",
    "message": "Rate limit exceeded. Maximum 3 requests per minute...",
    "data": {
        "status": 429,
        "retry_after": 60,
        "limit_type": "minute",
        "current": 3,
        "limit": 3
    }
}
```

### Check User Limits
```php
$limiter = new \KHM\GEO\RateLimiter();
$usage = $limiter->get_usage( get_current_user_id() );
// Returns: ['minute' => ['current' => 2, 'limit' => 3], 'day' => [...]]
```

### Reset User Limits
```php
$limiter = new \KHM\GEO\RateLimiter();
$limiter->reset( $user_id, 'all' ); // or 'minute' or 'day'
```

## Audit Logging

All requests are logged to `{prefix}_geo_requests` table.

### Table Schema
```sql
CREATE TABLE wp_geo_requests (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    post_id bigint(20) unsigned NOT NULL DEFAULT 0,
    action varchar(50) NOT NULL DEFAULT '',
    model varchar(100) NOT NULL DEFAULT '',
    prompt_id varchar(50) NOT NULL DEFAULT '',
    cached tinyint(1) NOT NULL DEFAULT 0,
    response_size int(11) unsigned NOT NULL DEFAULT 0,
    prompt_tokens int(11) unsigned NOT NULL DEFAULT 0,
    completion_tokens int(11) unsigned NOT NULL DEFAULT 0,
    estimated_cost decimal(10,6) NOT NULL DEFAULT 0,
    error_message varchar(500) DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY post_id (post_id),
    KEY action (action),
    KEY created_at (created_at)
);
```

### View Statistics
```php
$logger = new \KHM\GEO\SuggestionAuditLogger();

// Get today's stats
$stats = $logger->get_stats( 'today' );
// Returns: total_requests, cache_hits, cache_misses, cache_hit_rate, 
//          total_tokens, total_cost, errors, unique_users, unique_posts

// Get recent logs
$logs = $logger->get_recent_logs( 100 );

// Logs for specific post
$post_logs = $logger->get_logs_by_post( $post_id );
```

### Cleanup Old Logs
Logs older than 90 days are automatically cleaned up daily. Configure retention:
```php
update_option( 'khm_geo_audit_retention_days', 30 );
```

## Gutenberg Integration

### Accessing the Suggestion Panel

1. Open any post/page in Gutenberg
2. Click the "gear" icon in top-right toolbar
3. Click "GEO AnswerCards" in the sidebar
4. Click "Suggest AnswerCards" button

### Workflow

1. **Generate**: Click "Generate Suggestions" to analyze content
2. **Review**: View suggested AnswerCards with confidence scores
3. **Select**: Check/uncheck cards to include
4. **Insert**: Click "Insert Selected" to add blocks to post
5. **Edit**: Optionally edit cards before insertion

### Minimum Content Requirement

The suggestion feature requires at least 100 characters of content.

## Testing

### Run Unit Tests
```bash
cd wp-content/plugins/khm-plugin
./vendor/bin/phpunit --testsuite=geo
```

### Manual Testing

1. **Test Endpoint**:
```bash
curl -X POST \
  'https://your-site.com/wp-json/khm-geo/v1/suggest-answercards' \
  -H 'Content-Type: application/json' \
  -H 'X-WP-Nonce: YOUR_NONCE' \
  -d '{
    "post_id": 123,
    "title": "Test Article",
    "url": "https://your-site.com/test",
    "content": "Your article content here...",
    "max_cards": 3
  }'
```

2. **Test Caching**: Make the same request twice; second should have `X-KHM-GEO-Cache: HIT`

3. **Test Rate Limiting**: Make 4+ requests within a minute; should get 429 on 4th

## Cost Estimation

Approximate costs per request (GPT-4o-mini):

| Content Length | Prompt Tokens | Output Tokens | Cost |
|---------------|---------------|---------------|------|
| Short (~500 words) | ~800 | ~400 | ~$0.0004 |
| Medium (~1500 words) | ~2000 | ~500 | ~$0.0006 |
| Long (~3000 words) | ~4000 | ~600 | ~$0.001 |

With 50 requests/day limit and 24h cache:
- **Maximum daily cost per user**: ~$0.05
- **Typical cost with caching**: ~$0.01-0.02/day

## Troubleshooting

### "OpenAI API key not configured"
Set the `OPENAI_API_KEY` environment variable or define it in wp-config.php.

### "Rate limit exceeded"
Wait for the cooldown period or increase limits in configuration.

### "JSON validation failed after retry"
The LLM output didn't match the schema. Try with different/simpler content.

### Suggestions not appearing in editor
1. Ensure the block assets are built: `npm run build`
2. Check browser console for JavaScript errors
3. Verify the plugin is not disabled on editor pages

### Cache not working
1. Check if transients are being stored in `wp_options`
2. For Redis, verify object cache is working
3. Check cache TTL configuration

## Security Considerations

- API keys are never exposed in frontend code
- Endpoint requires `edit_posts` capability
- Rate limiting prevents API abuse
- All inputs are sanitized
- Content hashes don't reveal original content

## Changelog

### 1.0.0 (2026-01-14)
- Initial release
- REST endpoint with auth
- OpenAI integration with retry
- JSON schema validation
- Caching layer
- Rate limiting
- Audit logging
- Gutenberg modal UI
