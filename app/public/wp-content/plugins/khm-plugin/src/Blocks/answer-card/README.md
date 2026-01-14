# AnswerCard Gutenberg Block

A structured answer card block for GEO (Generative Engine Optimization) that outputs semantic content with JSON-LD schema markup.

## Overview

The AnswerCard block allows content editors to create structured Q&A content that:
- Outputs semantic HTML for accessibility
- Generates JSON-LD schema (FAQPage + WebPage hasPart) for search engines
- Calculates GEO scores for content optimization
- Stores canonical data for reporting via the Tracker

## Files Structure

```
src/Blocks/answer-card/
├── answer-card.php       # Block registration, save handler, JSON-LD output
├── block.json            # Block metadata (attributes, supports, etc.)
├── rest.php              # REST API endpoints
├── package.json          # Build configuration
├── README.md             # This file
├── src/
│   ├── index.js          # Gutenberg block source
│   ├── editor.scss       # Editor styles
│   └── style.scss        # Frontend styles
└── build/                # Compiled assets (after npm run build)
    ├── index.js
    ├── index.css         # Compiled editor styles
    └── style-index.css   # Compiled frontend styles
```

## Installation

### 1. Install Dependencies

```bash
cd wp-content/plugins/khm-plugin/src/Blocks/answer-card
npm install
```

### 2. Build Assets

```bash
# Production build
npm run build

# Development (watch mode)
npm run start
```

### 3. Create Database Tables (Optional)

The block stores data in postmeta by default. For enhanced reporting, create the dedicated tables:

**Via WP-CLI:**
```bash
wp eval "KHM\Migrations\GeoAnswerCardMigration::create_tables();"
```

**Via Admin Action:**
Visit: `/wp-admin/admin-post.php?action=khm_geo_migrate_tables&_wpnonce=YOUR_NONCE`

## Usage

1. In the Gutenberg editor, add a new block and search for "AnswerCard"
2. Fill in the fields:
   - **Question**: The query this content answers
   - **Concise Answer**: 40-80 words recommended for featured snippets
   - **Key Points**: Scannable bullet points
   - **Citations**: Authoritative source URLs
   - **Entities**: Key topics and concepts
3. Toggle "Include in JSON-LD schema" in the sidebar to control schema output

## Block Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `question` | string | `""` | The question being answered |
| `conciseAnswer` | string | `""` | Direct answer (40-80 words ideal) |
| `keyPoints` | array | `[]` | Array of key point strings |
| `citations` | array | `[]` | Array of `{title, url}` objects |
| `entities` | array | `[]` | Array of `{name, sameAs}` objects |
| `exposeInSchema` | boolean | `true` | Include in JSON-LD output |
| `position` | number | `0` | Order position on page |

## REST API Endpoints

### GET `/wp-json/khm-geo/v1/entities`

Entity autocomplete search.

**Parameters:**
- `q` (string): Search query
- `limit` (integer): Max results (default: 10)

**Response:**
```json
[
  { "name": "SEO", "sameAs": "" },
  { "name": "Search Engine", "sameAs": "" }
]
```

### POST `/wp-json/khm-geo/v1/score`

Calculate GEO score for an answer card on demand.

**Request Body:**
```json
{
  "question": "What is GEO?",
  "concise_answer": "GEO is...",
  "key_points": ["Point 1", "Point 2"],
  "citations": [{"title": "Source", "url": "https://..."}],
  "entities": [{"name": "SEO", "sameAs": ""}]
}
```

**Response:**
```json
{
  "total_score": 75.5,
  "breakdown": {
    "question": { "score": 10, "max": 10, "status": "good" },
    "answer": { "score": 25, "max": 25, "status": "optimal", "words": 65 },
    ...
  },
  "grade": "C",
  "recommendations": ["Add 2+ authoritative citations..."]
}
```

### GET `/wp-json/khm-geo/v1/posts/{post_id}/answercards`

Get answer cards for a specific post.

### GET `/wp-json/khm-geo/v1/reports/scores`

Get GEO scores report for all posts (requires `edit_others_posts` capability).

**Parameters:**
- `per_page` (integer): Results per page
- `page` (integer): Page number
- `orderby` (string): `score`, `title`, or `date`
- `order` (string): `ASC` or `DESC`
- `min_score` (number): Minimum score filter

## Post Meta

The block saves the following post meta:

| Meta Key | Description |
|----------|-------------|
| `_geo_answercards` | Canonical array of answer cards |
| `_geo_score` | Composite GEO score (0-100) |
| `_geo_score_details` | Per-card scoring breakdown |

## JSON-LD Output

On singular posts/pages, the block outputs:

1. **FAQPage Schema** - For featured snippet eligibility
2. **WebPage hasPart Schema** - For semantic content structure

Example output:
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [{
    "@type": "Question",
    "name": "What is GEO?",
    "acceptedAnswer": {
      "@type": "Answer",
      "text": "GEO is..."
    }
  }]
}
</script>
```

## Hooks & Filters

### Filters

**`khm_geo_answercard_post_types`**
Modify which post types support answer cards.
```php
add_filter( 'khm_geo_answercard_post_types', function( $types ) {
    $types[] = 'product';
    return $types;
} );
```

## Admin Features

- **GEO Score Column**: Posts/Pages list shows GEO score with color coding
- **Sortable**: Click column header to sort by score
- **Color Coding**: Green (70+), Yellow (40-69), Red (<40)

## Scoring Engine

If `\KHM_SEO\GEO\Scoring\ScoringEngine` class is available, it will be used for advanced scoring. Otherwise, a basic completeness-based scoring fallback is provided:

| Component | Max Points | Criteria |
|-----------|------------|----------|
| Question | 10 | Must be present |
| Answer | 25 | 40-80 words optimal |
| Key Points | 20 | 3+ points recommended |
| Citations | 25 | 2+ sources recommended |
| Entities | 20 | 3+ entities recommended |

## Development

### Lint & Format
```bash
npm run lint:js
npm run lint:css
npm run format
```

### Update Dependencies
```bash
npm run packages-update
```

## Troubleshooting

### Block not appearing in editor
1. Ensure build files exist in `build/` folder
2. Check browser console for JavaScript errors
3. Verify PHP files are loading (check for PHP errors in debug.log)

### Score always shows 0
1. ScoringEngine class may not be available (fallback is used)
2. Check that `_geo_score` meta is being saved (use Query Monitor plugin)

### JSON-LD not appearing
1. Ensure `exposeInSchema` is enabled for at least one card
2. Check that viewing a singular post/page (not archive)
3. Use Google Rich Results Test to validate

## Acceptance Criteria

- [ ] Block available in Gutenberg inserter
- [ ] Can save question, answer, key points, citations, entities
- [ ] `_geo_answercards` postmeta persists on save
- [ ] `_geo_score` calculated and stored on save
- [ ] JSON-LD outputs in `<head>` for published posts
- [ ] GEO Score column appears in admin posts list
- [ ] REST endpoints return expected data
