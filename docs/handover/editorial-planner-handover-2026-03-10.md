# Editorial Planner handover — 2026-03-10

## Scope of this session

Work focused on the Editorial Planner admin flow in the KHM plugin, especially:

- planner session detail UX
- framework generation UX
- author generation UX
- session URL persistence
- modal behavior/styling
- framework and article output visibility

## Main files changed

- [app/public/wp-content/plugins/khm-plugin/assets/js/editorial-planner.js](app/public/wp-content/plugins/khm-plugin/assets/js/editorial-planner.js)
- [app/public/wp-content/plugins/khm-plugin/assets/js/editorial-new-session.js](app/public/wp-content/plugins/khm-plugin/assets/js/editorial-new-session.js)
- [app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php](app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php)

## Completed changes

### Session/detail behavior

- Fixed session detail opening from URL `?session_id=...`
- Fixed hard refresh preserving planner session detail state
- Fixed URL updates to preserve `page=editorial_planner`
- Fixed `navigateBack()` to only remove session query params

### New session form cleanup

- Removed `Author Profile` from the new session form
- Removed `allowed_domains` from planner/new session policy payloads
- New session success redirect now opens planner with `session_id`

### Thinking/loading UX

- Added visible full-screen thinking overlay with blur
- Overlay now reacts to:
	- research phase runs
	- framework generation
	- author generation
	- synopsis generation
- Added rotating thinking phrases to synopsis generation state

### Framework UX

- Changed button label from `Generate` to `Generate Framework`
- Changed completed label from `Regenerate` to `Regenerate Framework`
- Generated frameworks now render from `meta.frameworks` if present, otherwise from `meta.articles[].framework.output`
- Frameworks section is now actually rendered below article synopses

### Article table / prioritization

- `Volume` column renamed to `Search Volume`
- Articles are now ordered by a blended priority score using:
	- Search Volume: 45%
	- Citations: 35%
	- Keyword ranking / priority signal: 20%
- Rows now show priority ordering and a visible priority score

### Citation threshold guard

- Added minimum citation threshold before framework generation and author generation
- Current threshold: `2`
- Below threshold:
	- warning is shown in the row
	- framework generation is disabled/blocked
	- author generation is disabled/blocked

### Author profile flow

- Added per-article author profile selector in the planner table
- Supported profiles:
	- `balanced`
	- `journalistic`
	- `analytical`
	- `executive`
- Added recommended profile logic derived from article/framework content
- Selected profile is passed to backend `run-author`
- Backend now stores selected profile in article author metadata
- Backend prompt builder now injects profile-specific writing guidance

### Author modal

- `Open in Editor` issue fixed by syncing modal preview state from live session article data
- Author-complete modal restyled to better match planner modal/card structure
- Author modal now shows selected profile and recommended profile

## Backend notes

### `run-author`

Backend route now accepts `author_profile` and falls back to recommended profile if invalid/missing.

Relevant method:

- [app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php](app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php)

Key additions:

- `recommend_author_profile()`
- `get_author_profile_guidance()`
- updated `build_author_prompt()` signature to accept profile

## Validation completed

- JS syntax check passed for [app/public/wp-content/plugins/khm-plugin/assets/js/editorial-planner.js](app/public/wp-content/plugins/khm-plugin/assets/js/editorial-planner.js)
- PHP lint passed for [app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php](app/public/wp-content/plugins/dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php)

## Known UX notes still worth testing next cycle

1. Run another full framework + author cycle and verify the thinking overlay appears immediately for both actions.
2. Verify the article priority ordering feels sensible in real data.
3. Verify frameworks appear consistently in the generated frameworks section after refresh.
4. Verify selected author profile persists visually after refresh/reopen.
5. Check whether the author modal styling now feels aligned enough with the rest of the planner.
6. Re-check synopsis completion state to ensure the loading modal closes correctly in live use.

## Recommended next improvements

If continuing in a new chat, likely next tasks are:

1. Tune priority weighting or expose it in UI
2. Improve recommended author profile heuristics
3. Add citation-based re-research workflow instead of only blocking generation
4. Consolidate duplicated modal render blocks in planner JS
5. Finish broader planner modal styling tidy-up pass

## Suggested prompt to resume in a new chat

Use this as the starting point:

> Continue from [docs/handover/editorial-planner-handover-2026-03-10.md](docs/handover/editorial-planner-handover-2026-03-10.md). We were working on the Editorial Planner UI in `editorial-planner.js` and backend support in `class-dual-gpt-plugin.php`. Please first review that handover, then validate current behavior for framework generation, author generation, citation thresholds, author profile selection/recommendation, and modal consistency before making the next changes.
