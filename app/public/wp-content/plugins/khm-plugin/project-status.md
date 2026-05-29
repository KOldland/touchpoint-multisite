# Project Status: Editorial Suite Decomposition

## 1. Overview & Architecture
The project objective is the systematic decomposition of the legacy WordPress "God Plugin" (`khm-plugin.php`) and the associated `dual-gpt-wordpress-plugin` into a modular, service-oriented architecture.

### Functional Suites:
1. **Membership Suite**: Identity, Billing, and Permissions.
2. **Editorial Suite**: Content Strategy, Research, and AI Agents.
3. **Sponsorship Suite**: Partner Relations and Distribution.

---

## 2. Achievements (Recent Waves)

### Phase 4: Suite Finalization (WAVE 1 COMPLETE):
*   **Async Authoring**: `AuthorOrchestrator` refactored to utilize the `wp_ai_jobs` table. Drafting is now non-blocking, returning a `job_id` for frontend polling.
*   **Intelligence Services**: Established `CitationVerifier` in the Intelligence Tier with full parity to legacy logic (CrossRef, OpenAlex, JSON-LD, APA).
*   **Agent Integration**: `ResearchAgent` (Planner) and `AuthorOrchestrator` (Author) successfully integrated with the Intelligence Tier via the `IntelligenceBridge`.
*   **API Evolution**: Added `/author/job/{id}` REST endpoint for tracking background job status.
*   **Asset Correction**: Resolved script pathing issues in `AuthorWorkspace`.

### Phase 4: Suite Finalization (WAVE 2 COMPLETE):
*   **Job Execution (Worker)**: Implemented `AIWorker` in the Intelligence Tier. It now successfully consumes async jobs from the `wp_ai_jobs` table and dispatches them to the appropriate Agent.
*   **Image Service Migration**: 100% parity migration of DALL-E and Google Imagen logic from the legacy plugin to the modern `ImageService`. Supports house style presets and automated media library persistence.
*   **Unified "Editorial Studio" Menu**: Consistently re-parented Planner and Author workspaces under a new top-level "Editorial Studio" menu.
*   **Budget Resilience**: Hardened `AIStorage` with an UPSERT pattern to ensure token usage is tracked even for new users.
*   **Enrichment Automation**: Connected `EnrichmentAgent` to the new `ImageService` for automated editorial image injection.

### Phase 3: Asset Migration & UI Decoupling (COMPLETE):
*   **Asset Migration**: React dashboards and sub-components moved from the God Plugin into their respective modular plugins.
*   **Frontend Refactoring**: All JavaScript components updated to use the standardized `editorial/v1` REST namespace and localized data objects (`editorialData`, `authorData`).
*   **UI Mounting**: Modernized the admin workspace controllers (`PlannerWorkspace`, `AuthorWorkspace`) with correct DOM container IDs and context-aware script enqueuing.
*   **God Plugin Hollowing**: Redundant editorial menus and asset enqueues in `khm-plugin.php` commented out. Legacy `khm-plugin.full.php` archived as `.bak`.

---

## 3. Suite Mapping & Migration Status

| Functional Area | Legacy Path (Source) | Modern Path (Target) | Status |
| :--- | :--- | :--- | :--- |
| **LLM Interface** | `dual-gpt-...\/includes/class-llm-client.php` | `kh-editorial-intelligence/.../LLMService.php` | **Migrated** |
| **Strategy (Planner)** | `editorial-planner/` | `kh-editorial-planner/` | **Migrated** |
| **Writing Engine** | `dual-gpt-...\/includes/class-author-agent.php` | `kh-editorial-author/.../DraftAgent.php` | **Migrated** |
| **Frontend UI** | `khm-plugin/assets/js/` | `kh-editorial-planner/assets/js/` | **Migrated** |
| **Data Storage** | SQL Tables (`ep_briefs`, etc.) | CPT Meta (`planner_session`) | **Migrated** |
| **REST Namespace** | `dual-gpt/v1` | `editorial/v1` | **Migrated** |

---

## 4. Current State of Plugins

### `kh-editorial-author` (NEW)
*   **Status**: CORE LOGIC & UI COMPLETE.
*   **Components**: `AuthorOrchestrator`, `DraftAgent`, `EnrichmentAgent`, `AbstractAgent`, `AuthorWorkspace`.

### `kh-editorial-planner` (MODERNIZED)
*   **Status**: CORE LOGIC & UI COMPLETE.
*   **Refinement**: Namespaces standardized to `KH\Planner`. UI restored via `PlannerWorkspace`.

### `dual-gpt-wordpress-plugin` (LEGACY)
*   **Hollowed Out**: Research and Writing logic moved.
*   **Remaining**: Image Service, Framework citation-verifier logic.

### `khm-plugin` (The God Plugin - LEGACY)
*   **Hollowed Out**: Back-end Planner and Editorial UI logic moved.
*   **Status**: Host for Membership and SEO/GEO Answer card logic only. `full.php` archived.

---

## 5. Remaining Work (Roadmap)

### Phase 4: Suite Finalization
*   **UI Polling (Wave 3)**: Update the React `Authoring` frontend to handle async job responses, polling, and real-time status updates.
*   **Legacy Hollowing**: Remove or disable redundant logic in `dual-gpt-wordpress-plugin` and `khm-plugin` once Wave 3 testing is confirmed.

---

**Last Sync:** 2024-05-28  
**Status**: Phase 4 Wave 2 Complete; Infrastructure modernized & Image Service migrated.
