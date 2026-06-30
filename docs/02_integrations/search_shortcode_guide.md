# Site Search Shortcode Guide

## Quick Start

Place the search widget on any page or post using the shortcode:

```
[khm_site_search]
```

This renders a full search widget with input field, search button, and results area.

---

## Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `placeholder` | string | `"Ask a question or search the knowledge base…"` | Input placeholder text |
| `title` | string | `""` (hidden) | Optional heading above the search form |
| `theme` | string | `"light"` | Visual theme: `light` or `dark` |

### Examples

**With placeholder and title:**
```
[khm_site_search placeholder="Search our knowledge base…" title="Site Search"]
```

**Dark theme for dark-mode pages:**
```
[khm_site_search theme="dark" placeholder="Ask a question…"]
```

**Minimal (no title):**
```
[khm_site_search]
```

---

## Output Structure

The shortcode renders:

```
┌─────────────────────────────────────────────┐
│  [Search input field]              [Search] │
├─────────────────────────────────────────────┤
│  🔍 Searching your content…                 │
├─────────────────────────────────────────────┤
│  Best Match                                 │
│  ┌─────────────────────────────────────────┐│
│  │ AI-synthesised answer or best excerpt   ││
│  │ …with "Read the full article →" link    ││
│  └─────────────────────────────────────────┘│
│                                             │
│  Related Content (3 results)                │
│  ┌─────────────────────────────────────────┐│
│  │ Article title 1 — source badge          ││
│  │ Article title 2 — source badge          ││
│  │ Article title 3 — source badge          ││
│  └─────────────────────────────────────────┘│
└─────────────────────────────────────────────┘
```

---

## Behaviour

| Feature | Details |
|---------|---------|
| **Search triggers** | Form submission (Enter key or button click) — not live/debounced |
| **API endpoint** | `POST /kh-editorial/v1/public/search` (public, no auth) |
| **Sources** | RAG (semantic) + Registry (fulltext) — no external SERP |
| **Rate limit** | 10 requests per IP per 60 seconds |
| **Answer synthesis** | When enabled in Settings, an LLM generates a concise answer above results |
| **Excerpt fallback** | When synthesis is disabled, the best-matching result excerpt is shown |
| **Error state** | Red error message shown below the search form |
| **Empty state** | "No results found" message shown when no results match |
| **Loading state** | Spinner with "Searching your content…" text while request is in flight |

---

## Theme Customisation

### Dark Theme

```html
[khm_site_search theme="dark"]
```

The dark theme uses these CSS custom properties (overridable in your theme's stylesheet):

```css
.khm-site-search-theme-dark {
  --khm-bg: #1d2327;
  --khm-text: #f0f0f1;
  --khm-border: #3c434a;
  --khm-input-bg: #2c3338;
  --khm-accent: #2271b1;
}
```

### Light Theme (default)

```css
.khm-site-search-theme-light {
  --khm-bg: #ffffff;
  --khm-text: #3c434a;
  --khm-border: #c3c4c7;
  --khm-input-bg: #f6f7f7;
  --khm-accent: #2271b1;
}
```

### Custom Styling

Target the widget by container ID or class:

```css
/* Target a specific widget instance */
#khm-site-search-1234 .khm-site-search-input {
  border-radius: 8px;
}

/* Override all widgets */
.khm-site-search-widget {
  max-width: 800px;
  margin: 2rem auto;
}

/* Style the "Best Match" answer section */
.khm-site-search-answer-content {
  font-size: 1.1rem;
  line-height: 1.6;
}
```

---

## AI Answer Synthesis

When enabled in **Settings > Models > AI Answer Synthesis**:

1. The search widget sends the query to the public search endpoint
2. The endpoint runs RAG + Registry search
3. If results are found and AI Answers are enabled, the top results + query are sent to an LLM
4. The LLM synthesises a concise, readable answer
5. The answer appears in the "Best Match" section above the results list

### Configure Models

The answer synthesis model chain is configured in Settings:
- **Primary:** Default `openrouter/free`
- **Secondary:** Default `deepseek/deepseek-v4-flash`
- **Tertiary:** Default `gpt-4o-mini`

If the primary model is unavailable, the synthesizer falls through the chain automatically.

### Disable Synthesis

To disable LLM answer generation and show only the best-matching excerpt:

1. Go to **Editorial Studio > Settings > API Settings**
2. Expand the **Models** section
3. Find **AI Answer Synthesis**
4. Uncheck **"Enable"**
5. Save

---

## Accessibility

The search widget is built with accessibility in mind:

- `aria-live="polite"` on results container for screen reader announcements
- `role="search"` on form element
- `screen-reader-text` label on input
- Focus management: input retains focus after search
- Keyboard navigation: Results are focusable via Tab
- Error messages use `role="alert"`

---

## Performance Notes

- **JS size:** ~3 KB minified
- **CSS size:** ~6 KB minified
- **No external dependencies** — vanilla JS, no jQuery
- **Lazy loaded:** Assets only enqueue on pages containing the `[khm_site_search]` shortcode
- **Cached:** Results are cached for 5 minutes via WP Transients
- **No blocking:** Script is loaded in the footer (`true` for `$in_footer`)

---

## Troubleshooting

### Widget doesn't appear on the page

1. Check the page source for the shortcode text — it must not be commented out.
2. Verify the plugin is active.
3. Check browser console for JS errors (404 on assets, REST API errors).

### "No results found" for known content

1. Ensure content_registry table has been populated (content must be indexed first).
2. Check atomic_embeddings table has entries for semantic search.
3. Try the internal endpoint to verify data exists:
   ```bash
   curl -X POST https://example.com/wp-json/kh-editorial/v1/search \
     -H 'X-WP-Nonce: YOUR_NONCE' \
     -H 'Content-Type: application/json' \
     -d '{"query":"your query"}'
   ```

### Rate-limited (429 error)

Wait 60 seconds before searching again. Rate limit is 10 requests per minute per IP.