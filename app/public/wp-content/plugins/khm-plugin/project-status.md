# Project Status: Editorial Suite Decomposition

## 1. Overview & Architecture
The project objective is the systematic decomposition of the legacy WordPress "God Plugin" (`khm-plugin.php`) and the associated `dual-gpt-wordpress-plugin` into a modular, service-oriented architecture.

### Functional Suites:
1. **Membership Suite**: Identity, Billing, and Permissions.
2. **Editorial Suite**: Content Strategy, Research, and AI Agents.
3. **Sponsorship Suite**: Partner Relations and Distribution.

### Architectural Tiers (Editorial Suite):
*   **Intelligence Tier (`kh-editorial-intelligence`)**: The "Plumbing." Handles API credentials, Global Rate Limits, AI Job Queueing, Budgeting, and generic Research Wrappers (SerpAPI, DataForSEO).
*   **Workspace Tier (`kh-editorial-planner`)**: The "Strategy." Handles the specific 4-phase research workflow, agentic prompts, and the generation of article ideas and dossiers.
*   **Author Tier (`kh-editorial-author`)**: The "Production." Handles article drafting, abstracting, and citation enrichment.

---

## 2. Achievements (Current Session)

### Modular Infrastructure Established:
*   **Unified API Settings**: Created a central switchboard in the Intelligence plugin for OpenAI, DataForSEO, and Search Provider keys.
*   **AI Job Queue**: Implemented the `wp_ai_jobs` pattern for asynchronous processing, decoupled from specific workspace tools.
*   **Standardized REST API**: Established `editorial/v1` as the canonical namespace for all suite interactions.

### The Research Engine (Planner) Rewritten:
*   **Agent-Based Architecture**: Transitioned the 1,400-line legacy orchestrator into a decoupled suite of specialized agents.
*   **Session Persistence**: Migrated storage from legacy custom tables to the `planner_session` Custom Post Type (CPT) using Namespaced Meta.

### The Author Engine (Writing) Fully Modularized:
*   **New Plugin**: `kh-editorial-author` established with full PSR-4 agentic architecture.
*   **Drafting Engine**: `DraftAgent` migrated with full parity of editorial constraints (sentence variation, em-dash limits, "KH Voice" logic).
*   **Enrichment Engine**: `EnrichmentAgent` implemented to handle automated pull-quote extraction from numeric claims and Reference section building.
*   **Abstracting Engine**: `AbstractAgent` ported for extractive metadata (summaries, key points, meta-descriptions).
*   **Infrastructure Bridges**: Established clean interfaces (`IntelligenceBridge`, `PlannerBridge`) to decouple the Author from the specific LLM and Session implementations.

---

## 3. Suite Mapping & Migration Status

| Functional Area | Legacy Path (Source) | Modern Path (Target) | Status |
| :--- | :--- | :--- | :--- |
| **LLM Interface** | `dual-gpt-wordpress-plugin/includes/class-llm-client.php` | `kh-editorial-intelligence/src/Core/LLMService.php` | **Migrated** |
| **Strategy (Planner)** | `editorial-planner/` (and parts of `dual-gpt`) | `kh-editorial-planner/` | **Migrated** |
| **Writing Engine** | `dual-gpt-wordpress-plugin/includes/class-author-agent.php` | `kh-editorial-author/src/Agents/DraftAgent.php` | **Migrated** |
| **Post-Processing** | `dual-gpt-wordpress-plugin` (Enrichment methods) | `kh-editorial-author/src/Agents/EnrichmentAgent.php` | **Migrated** |
| **Metadata/Abstract**| `dual-gpt-wordpress-plugin` (Abstract methods) | `kh-editorial-author/src/Agents/AbstractAgent.php` | **Migrated** |
| **Data Storage** | SQL Tables (`ep_briefs`, `fg_briefs`, etc.) | CPT Meta (`planner_session`) | **Migrated** |
| **REST Namespace** | `dual-gpt/v1` | `editorial/v1` | **Migrated** |

---

## 4. Current State of Plugins

### `kh-editorial-author` (NEW)
*   **Status**: CORE LOGIC COMPLETE.
*   **Components**: `DraftAgent`, `EnrichmentAgent`, `AbstractAgent`, `PromptFactory`, `PolicyValidator`, `AuthorOrchestrator`.
*   **Refinement**: Includes refined numeric claim detection and robust citation normalization.

### `dual-gpt-wordpress-plugin` (LEGACY)
*   **Hollowed Out**: Research, Search, and Writing logic moved.
*   **Remaining**: 
    *   **Image Service**: Midjourney/DALL-E generation.
    *   **Framework Tools**: Citation verification logic (to be moved to Planner Agents).

### `editorial-planner` (LEGACY)
*   **Status**: DEPRECATED.
*   **Note**: All logic has been superseded by `kh-editorial-planner`. **Do not edit.**

### `khm-plugin` (The God Plugin - LEGACY)
*   **Hollowed Out**: Back-end Planner logic moved.
*   **Remaining**:
    *   **Primary Assets**: Host for the React builds (JS/CSS).
    *   **SEO/GEO Engines**: Answer card logic.
    *   **Membership**: Core user and billing logic.

---

## 5. Remaining Work (Roadmap)

### Phase 2: Finalization
*   Integrate the Author Agents with the Intelligence Job Queue for async processing.
*   Verify end-to-to workflow via REST endpoints.

### Phase 3: Asset Migration
*   Move React source code and build tools from `khm-plugin/assets` into the respective suite plugins.
*   Decouple the UI completely from the legacy God Plugin.

### Phase 4: Suite Finalization
*   Migrate Image Generation to a shared utility.
*   Migrate Framework citation-verifier to the Planner agents.
*   Consolidate the Admin UI into a single "KH Suite" top-level menu.

---

**Last Sync:** 2024-05-21  
**Status**: Phase 2 Logic Migration Complete; High-parity Author suite verified.
