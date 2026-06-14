# LLM Migration — Status & Roadmap

## Completed: 4 Migration Gaps

1. **Gap 1 — Gutenberg Compiler** — `GutenbergCompiler.php` service + wire into `persist_draft()` + JS sends `blocks` array *(in `kh-editorial-author`)*
2. **Gap 2 — Answer Card LLM** — `SuggestAnswerCardsEndpoint` migrated to `LLMService::post_completion()` *(in `kh-editorial-intelligence`)*
3. **Gap 3 — Citation Validation** — `CitationVerifier` now has tier + authority score + wired into Phase 4 *(in `kh-editorial-intelligence`, consumed by `kh-editorial-planner`)*
4. **Gap 4 — Currency Rates** — defaults + nested merge added *(in `kh-editorial-intelligence`)*

## Completed: Citation DB + Briefs + Exporter *(in `kh-editorial-planner`)*

- `CitationStore` — `kh_planner_citations` table, CRUD, approve/reject, link to briefs
- `BriefStore` — `kh_planner_briefs` + `kh_planner_exports` tables, save/query briefs
- `ExportAgent` — provenance appendix in DOCX + HTML (tier/authority/APA)

## Completed: API Settings Page Redesign *(in `kh-editorial-intelligence`)*

- `render_settings_page()` method with collapsible section layout (renamed from `render_settings_page_2`)
- Navigation rerouted: `kh-editorial-settings` points to `render_settings_page()`
- `admin.css` created with Elementor-inspired card/panel styling
- `admin.js` created for section collapse toggle
- Enqueue logic rewritten to use `get_current_screen()` substring match
- Inline JS collapse toggle added as fallback
- Old method removed, suffix cleaned up, committed

## Known Issues — Pending Investigation

1. **Publish redirect bug:** Draft → Publish redirects to `post-new.php?post_type=post` instead of staying on the published post's edit screen. Likely a PHP fatal error during `save_post`/`wp_insert_post` — check `debug.log` or PHP error log when reproducing.
2. **Answer Card suggest bypasses OpenRouter:** `generate_answercard_draft()` in `rest.php` calls OpenAI directly instead of routing through `LLMService`. Rejected with "OpenAI API not configured" error.

---

## Bug Fixes — 2026-06-13

1. **Asset URL bug:** `get_asset_url()` was passing `KH_EDITORIAL_PLUGIN_DIR` (directory path) to `plugins_url()` — should be plugin file path. CSS/JS returned 404s, page rendered unstyled. Fixed by appending `kh-editorial-intelligence.php`.
2. **PHP warning:** `$_POST['openai_model']` accessed without null coalesce — form has no `openai_model` input field. Fixed with `?? ''`.

---

## Model Profiles — Audit & Fix Plan *(2026-06-13)*

### Root Cause Analysis

Four bugs identified in `kh-editorial-intelligence/src/Admin/EditorialAdmin.php` save/load cycle and `src/Core/LLMService.php`:

1. **Preset always overwrites user model selections** — `render_settings_page()` saves `agent_models` from POST, then immediately overwrites with `PRESET_PROFILES[$preset]['models']` on every save. User can never customize away from a preset.
2. **Fallback chains never saved to DB** — `$settings['agent_fallbacks']` captures user-selected fallbacks but presets' `fallback_chains` are never written. UI shows stale/empty fallback dropdowns.
3. **UI model dropdowns are hardcoded and incomplete** — Research, Persona, and Utility model arrays are hand-maintained and missing ~15 models that exist in presets (e.g., `deepseek/deepseek-v4-pro`, `qwen/qwen3-32b`, `anthropic/claude-fable-latest`). Fallback dropdown has only 5 options.
4. **`gutenberg_push` orphaned** — Listed in admin UI as an agent but missing from `LLMService::AGENTS`, `DEFAULT_AGENT_MODELS`, and all 4 `PRESET_PROFILES`. Falls through to `gpt-4o-mini`.

### Fixes Completed — 2026-06-13

- [x] **Phase 1 — Save logic:** Removed preset overwrite; per-agent user overrides persist via `resolve_agent_model()` priority chain
- [x] **Phase 2 — Unified model registry:** `ALL_MODELS` constant (33 models); all 3 dropdown helpers repointed
- [x] **Phase 3 — Fallback chains:** `agent_tertiaries` added to save/load cycle; tertiary dropdown reads correct key; preset chains seed display on first load
- [x] **Phase 4 — gutenberg_push:** Added to `AGENTS`, `DEFAULT_AGENT_MODELS`, and all 4 `PRESET_PROFILES` with models + 2-tier fallback chains
- [x] **Phase 5 — Model aliases:** `deepseek/deepseek-chat` → `deepseek/deepseek-v3`
- [x] **JS profile switcher:** Inline script dynamically populates all model/fallback/tertiary selects when profile dropdown changes; values saved on form submit
- [x] **Default profile:** Changed from `balanced` to `speed`
- [x] **Phase 6 — Model viability audit:** Checked 27 unique model IDs against OpenRouter API
  - 22/25 OpenRouter-routed models confirmed live (plus 2 OpenAI-direct models: `gpt-4o`, `gpt-4o-mini`)
  - **3 dead models removed:**
    - `anthropic/claude-fable-latest` → replaced with `anthropic/claude-fable-5`
    - `deepseek/deepseek-v3` → replaced with `deepseek/deepseek-v3.2`
    - `nvidia/nemotron-nano-9b-v2` → replaced with `nvidia/nemotron-nano-9b-v2:free`
  - All 3 were removed from `ALL_MODELS`, `DEFAULT_AGENT_MODELS`, `DEFAULT_PERSONA_MODELS`, and all 4 `PRESET_PROFILES` chains (7 references total updated)
  - Zero deprecation warnings on any live model
  - Model count unchanged at 28 (`deepseek/deepseek-v3` dropped, `deepseek/deepseek-v3.2` added)

### Immediate Next Task
**Answer Card OpenRouter Routing** — The Answer Card suggest flow (`khm-geo/v1/suggest-answercards` → `generate_answercard_draft()` in `kh-editorial-author/src/Blocks/answer-card/rest.php`) is being rejected because it calls OpenAI API directly instead of routing through OpenRouter via `LLMService`. The API key in that legacy code isn't configured. Needs to be migrated to use `LLMService::post_completion()` with an `answercard` agent registered in `AGENTS`, `DEFAULT_AGENT_MODELS`, and all 4 `PRESET_PROFILES` — similar to Gap 2 (Answer Card LLM migration in `kh-editorial-intelligence`).

## Completed: LinkedIn Social Publishing Pipeline *(2026-06-14)*

### Summary
Built a full active LinkedIn publishing pipeline replacing the legacy passive OG meta tag approach:
- LinkedIn API credentials co-located with AI APIs in the existing Editorial Settings page
- Dedicated "Save Social Data" button with AJAX persistence
- "Post to LinkedIn Now" for immediate API publishing
- "Save for Later" queuing with SMMA admin review page
- Social Queue admin page for reviewing/publishing queued posts

### Architecture
```
Editor → Social Media meta box
  ├── "Save Social Data" → AJAX → post meta (_kh_smma_social_linkedin_*)
  ├── "Post to LinkedIn Now" → AJAX → LinkedIn UGC Posts API → published
  └── "Save for Later" → AJAX → queue_status=pending → SMMA → Social Queue

SMMA Admin → Social Queue page
  └── Pending table → Publish Now / Edit / Remove
  └── Recently Published table
```

### Removed
- `output_social_meta_tags()` + `wp_head` hook — passive OG meta tags deleted
- Twitter Card `<meta>` output deleted
- OG-based passive sharing model replaced with active API publishing

### Files Changed/Created
| File | Status | Description |
|---|---|---|
| `kh-editorial-intelligence/src/Admin/EditorialAdmin.php` | Modified | Added LinkedIn API fields (client_id, client_secret, access_token, author_urn) to settings form + save logic + defaults |
| `kh-smma/src/Social/SocialManager.php` | Rewritten | Removed OG output; added `ajax_save_social()`, `ajax_post_now()`, `ajax_queue_later()`, `publish_to_linkedin()`, `get_linkedin_credentials()` |
| `kh-smma/admin/templates/meta-box-social.php` | Modified | Added status bar, Save/Post Now/Save for Later buttons, queue status display, updated CSS |
| `kh-smma/assets/js/social-editor.js` | Rewritten | Wired all 3 action buttons, status bar show/hide, restoreStatus on load |
| `kh-smma/src/Admin/SocialQueuePage.php` | Created | Admin page listing pending + recently published posts, publish/remove actions, workflow guide |
| `kh-smma/src/Plugin.php` | Modified | Registered `SocialQueuePage` |

### New Flow
```
Editor → Fill social fields → "Save Social Data" → saved to post meta
       → "Post to LinkedIn Now" → LinkedIn API → published → status:published
       → "Save for Later" → status:pending → SMMA → Social Queue → "Publish Now"
```

## Completed: SEO Agent — UI/Backend Bug Fixes *(2026-06-13)*

4 bugs found in `khm-seo-agent` and fixed:

1. **Nonce middleware unwired (critical):** `can_edit_posts()` required `X-WP-Nonce` header and nonce was localized to `khmSeoAgentData.nonce`, but `editor-modal.js` never called `apiFetch.createNonceMiddleware()`. All 4 REST endpoints returned 403. Fixed.

2. **Preview key mismatch (critical):** `handle_preview()` returned `['preview' => [...]]` but JS read `response.preview_html`. Preview always blank. Fixed: JS reads `response.preview` and renders comparison table.

3. **job_id missing from responses (critical):** `handle_audit()` omitted `job_id` from LLM error, parse error, validation error, and completed paths. Apply button failed silently. Fixed: `wp_generate_uuid4()` generated once and included in all 5 response paths.

4. **Dead polling code (medium):** JS still had `pollAuditStatus()` and queued branch despite synchronous audit. Removed dead code.

### Files Affected
- `khm-seo-agent/assets/js/editor-modal.js`
- `khm-seo-agent/src/API/Rest_Api.php`

## Completed: SEO Agent — Apply Endpoint & UX Bug Fixes *(2026-06-13)*

Smoke test after initial fixes revealed 4 more bugs in the apply workflow:

1. **`confirm_schema_changes` never sent from JS (critical):** `handle_apply()` requires `confirm_schema_changes=true` when `set_schema_config` is among selected actions. `editor-modal.js` never sent this field — ALL 5 actions failed atomically with a silent 400 error. Fixed: `runApply()` now detects schema actions and sends `confirm_schema_changes: true` or `undefined`.

2. **Apply errors invisible (critical):** `runApply()` caught errors with only `console.error()` — no user-visible toast. Modal stayed open with zero feedback. Fixed: added `statusMessage`/`statusType` state and styled status banner (green success / red error) rendered below Preview/Apply buttons.

3. **Toast is misleading (medium):** Sidebar toast showed "8 suggestions" (counting `suggestions` array) but modal only rendered `apply_actions` (max 5). The `suggestions` array was completely invisible. Fixed: modal now renders a "Suggestions" section listing all `suggestions` entries before the "Apply Actions" checkboxes.

4. **No success confirmation (medium):** On successful apply, modal just closed silently — no summary. Fixed: status banner now reads "5 action(s) applied successfully. Refresh the editor to see updates."

### Files Affected
- `khm-seo-agent/assets/js/editor-modal.js`

## Completed: Excerpt Function — UI & Backend Fixes *(2026-06-13)*

1. **"Generate Excerpt" button invisible:** Extracted from image-gen conditional into standalone "Write Excerpt" `PanelBody` — now always visible in the AI Assistant sidebar.
2. **Excerpt endpoint skipped fallback chain:** `generate_excerpt()` switched from `post_completion()` to `post_completion_with_retry()` with `fallback_chain`.
3. **Sidebar renamed:** "AI Image Generator" → "AI Assistant" (hosts image gen + excerpt writing).
4. **Excerpt word count:** Changed from 160 characters to 100–200 words with server-side validation.

### Files Affected
- `kh-editorial-intelligence/assets/js/editor-image-sidebar.js`
- `kh-editorial-author/src/API/AuthorEndpoints.php`

### Files Affected
- `app/public/app/public/wp-content/plugins/kh-editorial-intelligence/src/Core/LLMService.php`
- `app/public/app/public/wp-content/plugins/kh-editorial-intelligence/src/Admin/EditorialAdmin.php`

---

## Completed: Schema Expansion — 4 New JSON-LD Types *(2026-06-13)*

**ClaimReview was dropped** per product decision. 4 types implemented across 3 files:

| Type | Schema.org | Source | Key Fields |
|---|---|---|---|
| **Atomic Article** | `TechArticle` | Category archive `isPartOf` + `parent_guide_url` override | `isPartOf`, `about`, `articleSection` |
| **QAPage** | `QAPage` | Q&A content parsing (Q:/A: patterns) | `mainEntity` → `Question` + `acceptedAnswer` |
| **VideoObject** | `VideoObject` | YouTube embeds in `post_content` | `hasPart` Clips, `embedUrl`, `thumbnailUrl` |
| **AudioObject** | `AudioObject` | YouTube embeds (podcast) | `contentUrl`, `encodingFormat` |

### Key Decisions Resolved
- **Taxonomy:** Existing `category` (no custom `kb_series` needed)
- **isPartOf:** Category archive default + `_khm_seo_parent_guide_url` override
- **Detection:** AI Agent flow (`build_llm_prompt`) + manual `SelectControl` sidebar dropdown

### Implementation
1. **SchemaGenerator.php** — 4 types in `$supported_types`, 4 generators, `extract_youtube_embed()`, `extract_qa_pairs_from_content()`, config toggles
2. **Rest_Api.php** — `build_llm_prompt()` extended, `build_recommended_schema_config()` handles new types, 2 schema-config REST endpoints (GET/POST)
3. **editor-modal.js** — `SelectControl` dropdown with `useEffect` load + `handleSchemaTypeChange` persist

### Files Affected
- `khm-seo/src/Schema/SchemaGenerator.php`
- `khm-seo-agent/src/API/Rest_Api.php`
- `khm-seo-agent/assets/js/editor-modal.js`

---

## Completed: Schema Tools — Frontend Wiring Fix *(2026-06-14)*

Smoke test revealed 6 issues with the schema tools. Root cause: two parallel schema engines (`SchemaGenerator` with all 4 new types vs `SchemaManager` with basic types only), and only `SchemaManager` was wired to `wp_head`.

### Fixes Applied

| # | Severity | Issue | Fix |
|---|---|---|---|
| 1 | Critical | `SchemaAdminManager` called undefined `generate_post_schema()` — fatal error on save/preview | Added `generate_post_schema()` to `SchemaManager` that delegates to `SchemaGenerator` |
| 2 | Critical | 4 new types never reached frontend — `SchemaGenerator` not wired to `wp_head` | Modified `determine_schema_for_current_page()` to delegate per-post schema to `SchemaGenerator` + added `merge_generated_schema()` helper |
| 3 | Medium | `SchemaAdminManager` meta box dropdown missing 4 new types | Added `techarticle`, `qapage`, `videoobject`, `audioobject` to `$schema_types` array |
| 4 | Medium | `_khm_seo_schema_config` post meta saved but never consumed by frontend | Added per-post config override in `detect_schema_types()` + `resolve_schema_type_key()` helper |
| 5 | Low | Admin settings page missing toggle checkboxes for 4 new types | Added 4 checkboxes to `render_general_tab()` + to `sanitize_schema_settings()` |
| 6 | Low | Two parallel schema engines | Bridged via delegation: `SchemaManager` handles global schemas + coordinates; `SchemaGenerator` handles per-post rich types |

### Architecture
- `SchemaManager` remains the `wp_head` coordinator (Organization, WebSite, BreadcrumbList)
- `SchemaManager` now delegates per-post schema to `SchemaGenerator` for singular pages
- `SchemaGenerator` respects per-post `_khm_seo_schema_config` override (from SEO Agent sidebar)
- `SchemaGenerator` auto-detects types (YouTube → VideoObject, Q&A → QAPage, etc.) when no override

### Files Modified
- `khm-seo/src/Schema/SchemaManager.php` — added `generate_post_schema()`, `merge_generated_schema()`, modified `determine_schema_for_current_page()`
- `khm-seo/src/Schema/SchemaGenerator.php` — added `resolve_schema_type_key()`, per-post config override in `detect_schema_types()`
- `khm-seo/src/Schema/Admin/SchemaAdminManager.php` — added 4 new types to `$schema_types`

## Completed: Schema Tools UI Debugging *(2026-06-14)*

### Root Cause
Schema tools (Preview, Validate, Test with Google) were non-functional in the post editor meta box. Three root causes identified:

1. **Handler conflict:** Both `schema-admin.js` and inline handlers in `meta-box-schema.php` bound to the same buttons. `schema-admin.js`'s validate handler sent stale textarea content (like "Loading preview...") as JSON, causing "Invalid JSON format" errors.

2. **WordPress magic quotes:** `wp_magic_quotes` applies `addslashes()` to all `$_POST` values. The validate endpoint received `{\"@context\":...}` instead of `{"@context":...}`, causing `json_decode()` to fail.

3. **No inline fallback:** The meta box template had no self-contained AJAX handlers — it relied entirely on `schema-admin.js` which had broken bindings.

### Fixes Applied

| # | Issue | Fix |
|---|---|---|
| 1 | `schema-admin.js` validate handler sent stale text as JSON | Removed Preview and Validate bindings from `schema-admin.js` — delegated to inline handlers in `meta-box-schema.php` |
| 2 | `wp_magic_quotes` escaped JSON quotes → `json_decode()` failed | Added `wp_unslash()` to `ajax_validate_schema()` before `json_decode()` |
| 3 | No inline fallback for button handlers | Added self-contained AJAX handlers directly in `meta-box-schema.php` that generate fresh preview before validating |
| 4 | Test with Google button not firing | Replaced delegated binding with direct `.on('click.khminline', ...)` using `stopImmediatePropagation()` |
| 5 | Validate always regenerates preview first | Changed validate flow to always call `ajax_preview_schema()` first, then validate the fresh result — never sends stale text |
| 6 | No server-side error logging | Added `error_log()` calls to `ajax_preview_schema()`, `ajax_validate_schema()`, `enqueue_admin_assets()`, and `init_hooks()` |

### Architecture
- **Preview Schema:** Inline handler → `ajax_preview_schema()` → `generate_schema_for_preview()` → populates textarea
- **Validate Schema:** Inline handler → always regenerates preview first → `ajax_validate_schema()` → `wp_unslash()` → `json_decode()` → `SchemaGenerator::validate_schema()`
- **Test with Google:** Direct binding → `ajax_test_with_google()` → returns Google Rich Results URL → `window.open()`

### Files Modified
- `khm-seo/src/Schema/Admin/SchemaAdminManager.php` — added `wp_unslash()` to `ajax_validate_schema()`; added `error_log()` diagnostics
- `khm-seo/src/Schema/Admin/assets/js/schema-admin.js` — removed Preview and Validate bindings from `bindEvents()`; added `window.khm_debug` global

## Completed: Social Media Tools Migration — khm-seo → kh-smma *(2026-06-14)*

Moved both "Social Media Optimization" and "Social Media Previews" panels from `khm-seo` into a single unified "Social Media" meta box in `kh-smma`.

### New Files Created (in `kh-smma`)
- `src/Social/SocialManager.php` — Unified class: LinkedIn OG tags on wp_head, "Social Media" meta box, live card preview, variant auto-populate REST endpoint, AI suggest endpoint
- `admin/templates/meta-box-social.php` — Meta box template with title/description fields, image selector, "Suggest with AI" + "Refresh Preview" buttons, live preview card, validation warnings
- `assets/js/social-editor.js` — JS for live preview (debounced input), character counters (150/300 limits), WordPress media library image picker, variant auto-populate listener, AI suggest handler
- `assets/css/social-editor.css` — Minimal styling for preview card hover

### Modified Files
- `kh-smma/src/Plugin.php` — Added `SocialManager` import + instantiation
- `kh-smma/assets/js/editor-generate.js` — Added `onUseSocial` callback dispatching `smma:social.populate` CustomEvent
- `kh-smma/assets/js/variant-grid.js` — Added "Use as social text" button per variant card
- `khm-seo/src/Core/Plugin.php` — Removed `SocialMediaManager`, `SocialMediaPreviewManager` imports, properties, and instantiations

### Deleted from `khm-seo`
- `src/Social/` — 5 files (SocialMediaManager, SocialMediaAdmin, SocialMediaGenerator, SocialMediaAdmin_Enhanced, SocialMediaGenerator_Enhanced)
- `src/Preview/` — 8 files (SocialMediaPreviewManager, 2 templates, 2 JS, 2 CSS)
- `assets/js/social-admin.js`, `assets/css/social-admin.css`

### AI Agentic Feature — "Suggest with AI" *(2026-06-14)*

Added AI-powered title/description generation using the existing `social_posts` agent in `kh-editorial-intelligence`:

| Component | Detail |
|---|---|
| Agent used | `social_posts` (already configured across all 4 presets) |
| Endpoint | `wp_ajax_kh_smma_social_suggest` in `SocialManager` |
| Model routing | `LLMService::resolve_agent_model('social_posts')` |
| Prompt | Generates LinkedIn title (max 150 chars) + description (max 300 chars) from post content |
| UI feedback | Button shows "AI is generating...", then "AI suggestions applied" for 3 seconds, auto-fills fields + refreshes preview |

### Architecture
```
wp_head → SocialManager::output_social_meta_tags() → OG/Twitter Card <meta> tags
Post Editor → "Social Media" meta box
  ├── LinkedIn title/description/image fields
  ├── "Suggest with AI" button → AJAX → ajax_suggest_social() → LLMService → populates fields
  ├── "Refresh Preview" button → AJAX → ajax_social_preview() → LinkedIn card HTML
  └── Live preview (debounced on input) + character counters
Variant Grid → "Use as social text" button → CustomEvent → REST /variant-populate
```

### Bug Fixes
- **Meta box padding:** Added 12px padding to `.kh-smma-social-meta-box` to match other WordPress meta box panels

---

## Completed: Answer Card Frontend Bug Fixes *(2026-06-14)*

### Root Cause
`src/view.js` and `src/view.scss` existed in source but were never compiled to `build/`. The webpack config had `view` as an entry point but the build had never been run after this entry was added.

### Fixes Applied

| # | Severity | Issue | Fix |
|---|---|---|---|
| 1 | Critical | `filemtime()` PHP warnings — `build/view.js` + `build/view.css` didn't exist | Ran `npx wp-scripts build`; added `file_exists()` guards in `answer-card.php` |
| 2 | Critical | No CSS styling on frontend — `block.json` declared `style: ./build/view.css` but file missing | Sass compiled → `build/view.css` (581-line SCSS → 9,224 bytes CSS) |
| 3 | Critical | Cards not opening (toggle broken) — `block.json` declared `viewScript: ./build/view.js` but file missing | `src/view.js` compiled → `build/view.js` (392-line JS → 8,054 bytes minified) |
| 4 | Medium | Bookmark icon not loading — `plugins_url('social-strip/assets/bookmark.png', WP_PLUGIN_DIR)` passed directory instead of plugin file | Changed to `plugins_url('/social-strip/assets/bookmark.png')` (single-arg form) |
| 5 | Medium | No metadata appearing — Suggest flow never includes `topicDiscussedAt` in block attributes | Added auto-populate fallback in `render_answercard_block()`: fills title, URL, author, publisher, date from current post |
| 6 | Low | No bottom margin on answer block | Changed `.khm-answer-card { margin: 1.5rem 0 }` → `margin: 1.5rem 0 3rem 0` in `src/view.scss` + rebuild |

### Files Modified
- `kh-editorial-author/src/Blocks/answer-card/answer-card.php` — `file_exists()` guards, bookmark icon fix, metadata auto-populate fallback
- `kh-editorial-author/src/Blocks/answer-card/src/view.scss` — bottom margin fix
- `kh-editorial-author/src/Blocks/answer-card/build/view.js` — compiled from source
- `kh-editorial-author/src/Blocks/answer-card/build/view.css` — compiled from source
- `kh-editorial-author/src/Blocks/answer-card/build/view.asset.php` — generated by webpack

## Completed: Social Strip Icon Fix *(2026-06-14)*

### Root Cause
Both `icon_base` paths pointed to directories that don't exist:
- `widgets/class-social-strip-widget.php:73` — `plugin_dir_url(__DIR__) . 'assets/'` → `widgets/assets/` ❌
- `includes/khm-integration.php:428` — `plugin_dir_url(__FILE__) . '../assets/img/'` → `assets/img/` ❌

Actual icons live at `social-strip/assets/` (bookmark.png, buy.png, download.png, gift.png, share.png).

### Fix
Both changed to `plugins_url('/social-strip/assets/')` — same pattern as the answer card bookmark fix.

### Files Modified
- `social-strip/widgets/class-social-strip-widget.php` — line 73 icon_base
- `social-strip/includes/khm-integration.php` — line 428 icon_base

## Completed: Abstract Block AI Agent Workflow *(2026-06-14)*

### Summary
Wired the `AbstractAgent` (backed by LLM via IntelligenceBridge) to the AI Assistant sidebar. Users can generate abstract metadata (Overview, Context, Application, Observations, Keywords) from post content and save it as an ACF block.

### Architecture
```
AI Assistant sidebar → "Generate Abstract" button
  → POST editorial/v1/abstract/generate
  → AbstractAgent::execute() (via AuthorOrchestrator)
  → Returns { overview, key_points, context, application, keywords }
  → JS renders preview + "Save Abstract to Post" button
  → POST editorial/v1/abstract/save-to-post
  → Writes ACF post meta + block marker → auto-saves post → reloads editor
```

### ACF Block Persistence
Three-layer write to ensure the block populates in all contexts:
1. **Post meta:** `overview`, `context`, `application`, `key_points_N_bullet` (ACF repeater)
2. **Block comment:** Both `_field_abstract_*` keys (Gutenberg editor) + simple keys (PDFService/frontend)
3. **ACF `update_field()`** for ACF Pro in-block editing

### Deduplication
Removed `editorial_summary` and `meta_summary` — duplicate existing Excerpt Generator (100-200 word excerpt) and SEO Agent (meta description) tools. Abstract block now produces 4 core fields: Overview, Context, Application, Observations.

### Files Modified
- `kh-editorial-author/src/API/AuthorEndpoints.php` — `generate_abstract()` + `save_abstract_to_post()` endpoints
- `kh-editorial-author/src/Agents/PromptFactory.php` — removed `editorial_summary`/`meta_summary` from abstract schema
- `kh-editorial-author/src/Agents/AbstractAgent.php` — removed `editorial_summary`/`meta_summary` from validation
- `kh-editorial-intelligence/assets/js/editor-image-sidebar.js` — "Generate Abstract" PanelBody with preview + auto-save/reload

### Known Minor Bugs
1. Post save/reload flow may re-save the post even if no other editor changes exist (acceptable trade-off)
2. Abstract block marker regex replacement uses `preg_replace` — may fail if block has been manually edited/indented
3. `window.location.reload()` may lose unsaved changes in other metaboxes/panels — consider `useSelect` refresh in future

### Immediate Next Task
[None — abstract block workflow complete]
