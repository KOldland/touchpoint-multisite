# SEO Agent Phase 0 — Integration Map

Date: 2026-01-22

## Scope
This map focuses on Dual-GPT and KHM-SEO integration points that the SEO Agent will depend on (REST endpoints, public APIs, DB tables, meta keys/options, editor and GEO hooks, and existing agent flows).

---

## Dual-GPT (dual-gpt-wordpress-plugin)

### REST API routes
- Sessions
  - POST /wp-json/dual-gpt/v1/sessions
  - Handler: Dual_GPT_Plugin::create_session()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php
- Jobs
  - POST /wp-json/dual-gpt/v1/jobs
  - Handler: Dual_GPT_Plugin::create_job()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php
- Job stream (SSE)
  - GET /wp-json/dual-gpt/v1/jobs/{id}
  - Handler: Dual_GPT_Plugin::stream_job()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php
- Presets
  - GET/POST /wp-json/dual-gpt/v1/presets
  - PUT/DELETE /wp-json/dual-gpt/v1/presets/{id}
  - Handlers: get_presets(), create_preset(), update_preset(), delete_preset()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php
- Audit
  - GET /wp-json/dual-gpt/v1/audit
  - Handler: get_audit_logs()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php
- Budgets
  - GET/POST /wp-json/dual-gpt/v1/budgets
  - Handler: get_budgets(), update_budget()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php
- Blocks import
  - POST /wp-json/dual-gpt/v1/blocks/import
  - Handler: import_blocks()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php
- Author Agent
  - POST /wp-json/dual-gpt/v1/author/run
  - Handler: Dual_GPT_Author_Agent_API::run_author_agent()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-author-agent.php
- Framework Generator (FG)
  - POST /wp-json/fg/v1/start
  - GET /wp-json/fg/v1/session/{session_id}
  - POST /wp-json/fg/v1/citation-qa/{session_id}
  - GET /wp-json/fg/v1/brief/{fg_brief_id}
  - POST /wp-json/fg/v1/export/{fg_brief_id}
  - POST /wp-json/fg/v1/pass-to-author/{fg_brief_id}
  - POST /wp-json/fg/v1/citation-qa/{session_id} (legacy)
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-framework-generator-api.php

### Key classes and functions
- Core plugin: Dual_GPT_Plugin
  - REST registration: register_rest_routes()
  - Job processing code path:
    - create_job() → process_job_async() → process_job()
    - process_job() → OpenAI call → process_tool_calls() → execute_tool()
    - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php
- DB handler: Dual_GPT_DB_Handler
  - Sessions: insert_session(), get_session(), get_session_by_idempotency()
  - Jobs: insert_job(), get_job(), get_job_by_idempotency(), update_job_status()
  - Presets: insert_preset(), update_preset(), delete_preset(), get_presets(), get_preset()
  - Budget: check_user_budget(), update_token_usage()
  - Audit: insert_audit_log()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-db-handler.php
- OpenAI connector: Dual_GPT_OpenAI_Connector
  - create_chat_completion(), validate_api_key(), calculate_cost()
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-openai-connector.php
- Tools
  - Research tools: Dual_GPT_Research_Tools
    - get_tool_definitions(), execute_tool()
    - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/tools/class-research-tools.php
  - Author tools: Dual_GPT_Author_Tools
    - get_tool_definitions(), execute_tool()
    - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/tools/class-author-tools.php
- Existing agents
  - Author Agent (draft/abstract/enrichment): Dual_GPT_Author_Agent
  - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-author-agent.php
  - Framework Generator pipeline
    - API: Framework_Generator_API
    - Workers: Framework_Generator_Workers
    - File: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-framework-generator-api.php

### Dual-GPT DB tables
Defined in Dual_GPT_Plugin::create_tables():
- wp_ai_sessions
- wp_ai_jobs
- wp_ai_presets
- wp_ai_audit
- wp_ai_budgets
- wp_fg_validated_citations
- wp_fg_briefs
- wp_fg_exports
- wp_fg_raw_articles
- wp_fg_session_exclusions
- wp_fg_session_keywords

### Sidebar/editor UI patterns
- Dual-GPT Gutenberg sidebar modal UI patterns
  - JS: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/assets/js/sidebar.js
  - CSS: app/public/wp-content/plugins/dual-gpt-wordpress-plugin/assets/css/sidebar.css
  - Localized data: dualGptData (nonce, restUrl, coreSettings)

---

## KHM-SEO (khm-seo)

### Public APIs
- Global accessor
  - khm_seo() → KHM_SEO\Core\Plugin::instance()
  - File: app/public/wp-content/plugins/khm-seo/khm-seo.php
- Public methods used by agent
  - Plugin::analyze_content($content, $keyword = '')
  - Plugin::get_meta_manager()
  - Plugin::get_schema_manager()
  - Plugin::get_geo_manager()
  - File: app/public/wp-content/plugins/khm-seo/src/Core/Plugin.php

### SEO meta write surfaces (postmeta / termmeta)
- Post meta keys (AdminManager & MetaManager)
  - _khm_seo_title
  - _khm_seo_description
  - _khm_seo_keywords
  - _khm_seo_robots
  - _khm_seo_canonical
  - _khm_seo_focus_keyword
  - Files:
    - app/public/wp-content/plugins/khm-seo/src/Admin/AdminManager.php
    - app/public/wp-content/plugins/khm-seo/src/Meta/MetaManager.php
- Term meta keys (MetaManager)
  - khm_seo_title
  - khm_seo_description
  - khm_seo_keywords
  - File: app/public/wp-content/plugins/khm-seo/src/Meta/MetaManager.php
- Schema postmeta keys (SchemaAdminManager)
  - _khm_seo_schema_config
  - _khm_seo_schema_cache
  - _khm_seo_schema_cache_updated
  - File: app/public/wp-content/plugins/khm-seo/src/Schema/Admin/SchemaAdminManager.php

### KHM-SEO options (site-level)
- khm_seo_general
- khm_seo_titles
- khm_seo_meta
- khm_seo_sitemap
- khm_seo_schema
- khm_seo_tools
- khm_seo_performance
- khm_seo_analysis
- khm_seo_schema_admin
- khm_seo_db_version
- khm_seo_geo_entities_db_version
- khm_seo_activated_time
- khm_seo_version

### KHM-SEO DB tables
- Core tables (Activator/DatabaseManager)
  - wp_khm_seo_posts
  - wp_khm_seo_terms
  - Files:
    - app/public/wp-content/plugins/khm-seo/src/Core/Activator.php
    - app/public/wp-content/plugins/khm-seo/src/Utils/DatabaseManager.php
- GEO entity registry tables (EntityTables)
  - wp_geo_entities
  - wp_geo_entity_aliases
  - wp_geo_entity_link_rules
  - wp_geo_entity_scopes
  - wp_geo_page_entities
  - File: app/public/wp-content/plugins/khm-seo/src/GEO/Database/EntityTables.php
- Analytics tables (for dashboards/insights)
  - wp_khm_seo_scores
  - wp_khm_seo_metrics
  - wp_khm_seo_reports
  - wp_khm_seo_recommendations
  - wp_khm_seo_audit_log
  - wp_khm_seo_competitive_data
  - Files:
    - app/public/wp-content/plugins/khm-seo/src/Analytics/AnalyticsDatabase.php
    - app/public/wp-content/plugins/khm-seo/src/Analytics/AdvancedAnalyticsEngine.php

### Editor + GEO hooks
- Editor hooks (live analysis + meta preview)
  - wp_ajax_khm_seo_live_analysis
  - wp_ajax_khm_seo_meta_preview
  - Meta box: khm-seo-meta
  - Files:
    - app/public/wp-content/plugins/khm-seo/src/Editor/EditorManager.php
    - app/public/wp-content/plugins/khm-seo/src/Admin/AdminManager.php
- Admin analysis AJAX
  - wp_ajax_khm_seo_analyze_content
  - File: app/public/wp-content/plugins/khm-seo/src/Admin/AdminManager.php
- GEO REST endpoints
  - Namespace: geo/v1
  - Routes: /entities, /entities/{id}, /entities/{id}/aliases, /entities/import, /entities/export, /entities/refactor, /content/validate
  - File: app/public/wp-content/plugins/khm-seo/src/GEO/API/EntityAPI.php
- GEO admin/edit hooks
  - Entity edit, aliases, bulk actions via AJAX and admin pages
  - File: app/public/wp-content/plugins/khm-seo/src/GEO/GEOManager.php

### Elementor integration
- ElementorIntegration (hooks, widgets, controls, editor scripts)
  - Widget category: khm-seo
  - Widgets: AnswerCard, ClientBadge
  - Control: EntityAutocomplete
  - AJAX: wp_ajax_khm_geo_entity_search
  - Files:
    - app/public/wp-content/plugins/khm-seo/src/Elementor/ElementorIntegration.php
    - app/public/wp-content/plugins/khm-seo/src/Elementor/controls/EntityAutocomplete.php
    - app/public/wp-content/plugins/khm-seo/src/Elementor/widgets/AnswerCard.php

---

## Integration gaps to confirm (write surfaces)
- KHM-SEO meta write surface is primarily postmeta with _khm_seo_* keys (AdminManager + MetaManager). Confirm whether any writes must be routed through KHM_SEO\Meta\MetaManager or DatabaseManager instead of direct postmeta updates.
  - Files to confirm:
    - app/public/wp-content/plugins/khm-seo/src/Meta/MetaManager.php
    - app/public/wp-content/plugins/khm-seo/src/Admin/AdminManager.php
    - app/public/wp-content/plugins/khm-seo/src/Utils/DatabaseManager.php
- Schema writes: SchemaAdminManager saves _khm_seo_schema_config and caches schema JSON in _khm_seo_schema_cache. Confirm if any validation hooks or schema validators must run before writes.
  - Files to confirm:
    - app/public/wp-content/plugins/khm-seo/src/Schema/Admin/SchemaAdminManager.php
    - app/public/wp-content/plugins/khm-seo/src/Schema/SchemaManager.php
    - app/public/wp-content/plugins/khm-seo/src/Validation/SchemaValidator.php
- GEO entity data and schema references: ensure any SEO Agent suggestions avoid direct writes to GEO tables unless explicitly approved.
  - Files to confirm:
    - app/public/wp-content/plugins/khm-seo/src/GEO/Database/EntityTables.php
    - app/public/wp-content/plugins/khm-seo/src/GEO/Entity/EntityManager.php

Safe defaults (until confirmed):
- Use post meta keys only (_khm_seo_title, _khm_seo_description, _khm_seo_focus_keyword, _khm_seo_keywords, _khm_seo_robots, _khm_seo_canonical).
- Do not write schema config or cache from the SEO Agent without explicit schema validation API or human confirmation path.
- Do not write to GEO tables from the SEO Agent in Phase 1.

---

## CI / tests
- No repo-level PHPUnit config or vendor/bin/phpunit found.
- No root composer.json found.
- Next step (Phase 1): define standard test command and add CI workflow.

