# Session: Answer Card OpenRouter Routing Discovery

## Previously Completed (see `LLM_final_mapping.md` for full details)
- Post-crash recovery & API Settings Page redesign
- Model Profiles audit & fix (Phases 1–6), Profile UX upgrade, Model viability audit
- SEO Agent UI/backend bug fixes (nonce, preview, job_id, polling, apply endpoint, empty value guard)
- Excerpt function UI & backend fixes
- Schema Expansion: 4 new JSON-LD types (TechArticle, QAPage, VideoObject, AudioObject)
- Schema Tools Frontend Wiring & UI Debugging
- Social Media Tools Migration: khm-seo → kh-smma
- LinkedIn Social Publishing Pipeline (active API publishing)
- Answer Card Frontend Bug Fixes
- Social Strip Icon Fix
- Abstract Block AI Agent Workflow

## Investigation — Answer Card Suggest Flow Bypasses OpenRouter *(2026-06-14)*

### Problem
User reported rejection when using Answer Card suggestions. Error: "OpenAI API not configured" — but all other agentic workflows route through OpenRouter via `LLMService` in `kh-editorial-intelligence`.

### Root Cause Discovery
The Answer Card suggest flow in the post editor (Gutenberg Inspector) lives in `kh-editorial-author/src/Blocks/answer-card/` — a separate code path from the `SuggestAnswerCardsEndpoint` in `kh-editorial-intelligence` (which was already migrated in Gap 2).

Flow traced:
```
JS: suggest-plugin.js → POST /khm-geo/v1/suggest-answercards
PHP: rest.php → generate_answercard_draft() → run_answercard_generation_job()
```

The `generate_answercard_draft()` callback in `rest.php` calls the legacy OpenAI API directly instead of routing through `LLMService::post_completion()`. The legacy API key isn't configured, causing rejection.

### What Needs Fixing
1. Read `run_answercard_generation_job()` fully to confirm the exact OpenAI call
2. Migrate the suggest flow to use `LLMService::post_completion()` from `kh-editorial-intelligence`
3. Register an `answercard` agent in `LLMService::AGENTS`, `DEFAULT_AGENT_MODELS`, and all 4 `PRESET_PROFILES`
4. Ensure the `answercard` agent appears in the Editorial Settings page alongside other agent models
5. Wire the new LLM call into `generate_answercard_draft()` or `run_answercard_generation_job()`

## Investigation — Publish Redirect Bug *(2026-06-14)*

### Problem
Clicking "Publish" in the post editor redirects to `post-new.php?post_type=post` instead of staying on the published post's edit screen. This started after wiring `AtomicArticlePostType` to `save_post`.

### Root Cause Hypothesis
Likely a PHP fatal error during `save_post`/`wp_insert_post` in `AtomicArticleGenerator::on_save_post()` or `AtomicMetaBox::save_meta()`. When WordPress encounters a fatal error during save, it falls back to redirecting to `post-new.php` as a safety measure.

### Status
PENDING — requires checking `debug.log` or PHP error log at time of reproduction.

### Related Files
- `khm-plugin/src/Atomic/AtomicArticleGenerator.php`
- `khm-plugin/src/Atomic/AtomicMetaBox.php`

---

## Next Immediate Task
**Answer Card OpenRouter Routing** — Migrate the `khm-geo/v1/suggest-answercards` flow from legacy OpenAI direct to `LLMService::post_completion()` with a new `answercard` agent.