# Project Status: Editorial Suite Decomposition

## 1. Overview & Architecture
The project objective is the systematic decomposition of the legacy WordPress "God Plugin" (`khm-plugin.php`) and the associated `dual-gpt-wordpress-plugin` into a modular, service-oriented architecture.

### Functional Suites:
1. **Membership Suite**: Identity, Billing, and Permissions.
2. **Editorial Suite**: Content Strategy, Research, and AI Agents.
3. **Sponsorship Suite**: Partner Relations and Distribution.

---

## 2. Achievements (Current Session)

### Modular Infrastructure Established:
*   **Intelligence Tier (`kh-editorial-intelligence`)**: Central switchboard for API keys and centralized LLM access verified.
*   **Workspace Tier (`kh-editorial-planner`)**: Foundation repaired (namespaces standardized) and logic fully modularized.
*   **Author Tier (`kh-editorial-author`)**: PSR-4 agentic architecture established with high-parity drafting, enrichment, and abstracting logic.

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
*   **Async Integration**: Tie Author agents into the `wp_ai_jobs` queue for background processing.
*   **Consolidation**: Move the remaining Framework tools and Image Generation into the modular agents.
*   **Unified Menu**: Merge all suite tools into a single "KH Suite" top-level admin menu.

---

**Last Sync:** 2024-05-21  
**Status**: Asset Migration Complete; Editorial Suite now independent and modularized.
