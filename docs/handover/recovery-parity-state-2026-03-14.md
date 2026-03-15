# Recovery Parity State - 2026-03-14

## Scope

This note captures the current parity state between:

- recovery repo: `/Users/krisoldland/Local Sites/touchpoint-template-remote`
- runnable rebuild: `/Users/krisoldland/Local Sites/touchpoint-template-final-build`

It is intended as a quick checkpoint before live Editorial Assistant testing.

## Code Parity

The key Editorial Assistant and image-generation files reviewed from the last session are now aligned between recovery and rebuild, including:

- `khm-plugin/assets/js/editorial-planner.js`
- `khm-plugin/assets/js/editorial-new-session.js`
- `khm-plugin/assets/js/editorial-sessions.js`
- `khm-plugin/assets/js/editorial-frameworks.js`
- `khm-plugin/assets/js/editorial-exports.js`
- `khm-plugin/assets/js/editorial-calendar.js`
- `khm-plugin/assets/js/editorial-top-line-categories.js`
- `khm-plugin/khm-plugin.php`
- `editorial-planner/editorial-planner.php`
- `dual-gpt-wordpress-plugin/includes/class-dual-gpt-plugin.php`
- `dual-gpt-wordpress-plugin/includes/providers/class-google-image-provider.php`
- `dual-gpt-wordpress-plugin/includes/class-image-provider-registry.php`
- `dual-gpt-wordpress-plugin/includes/class-image-settings.php`

## Specifically Restored

- New Session redirects directly into the created planner session using `session_id`.
- Past Sessions is decoupled from the hidden planner bootstrap path.
- New Session supports legacy preset roles while displaying the recovered Generic terminology.
- Specialist Profile was removed from the initial New Session form.
- Planner image actions are present in code: Recommend Image, Generate Image, Open Image.
- Runtime framework model mapping was updated in final-build DB to `gpt-5`.

## Runtime State Confirmed

- `wp_ai_jobs` exists in final-build DB.
- `wp_planner_task_queue` exists in final-build DB.
- Queue/job history exists in final-build DB.
- Preset data exists in `wp_ai_presets`.
- Final-build DB can be queried with WP-CLI when DB host overrides are cleared.

Recommended WP-CLI pattern in this environment:

```bash
env -u DB_HOST -u TEMP_DB_HOST wp ...
```

## Remaining Non-Code Gaps

These are the known remaining losses from the recovery incident:

1. Recent Editorial Assistant DB content is not recovered.
   - Current final-build DB only has January-era planner session content.
   - This is a data gap, not a code gap.

2. `dual_gpt_top_line_categories` has no saved DB option in final-build.
   - Category behavior currently falls back to code defaults.
   - Any later admin-edited category state was not recovered.

3. Runtime environment is slightly brittle.
   - If `DB_HOST` or `TEMP_DB_HOST` are set externally, WP-CLI may bypass the correct Local socket path.

## Live Test Focus

Use live testing to verify behavior rather than code presence for these flows:

1. New Session
   - Create session
   - Confirm direct redirect to `admin.php?page=editorial_planner&session_id=...`

2. Past Sessions
   - Load from sidebar
   - Confirm standalone load without planner overlap/blank state

3. Planner queue flow
   - Queue item visibility
   - Stop/Retry actions
   - Status progression and completion refresh

4. Opinion / Dive Deeper flow
   - Opinion piece path
   - Lite framework pre-step behavior
   - Deep dive completion reflected in synopsis/article state

5. Image flow
   - Recommend Image
   - Generate Image
   - Open Image

## Current Position

Code parity is effectively restored for the tracked last-session scope.

What remains to validate is runtime behavior and UX under live interaction.