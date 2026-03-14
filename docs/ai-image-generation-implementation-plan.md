# AI Image Generation Implementation Plan

## Objective

Add article-aware AI image generation to the existing Dual-GPT platform without mixing provider logic into the Editorial Assistant plugin shell.

## Product shape

Two primary users:

- Editor/Webmaster configures providers, defaults, house style, permissions, and moderation rules.
- Content Manager/Contributor generates and inserts images from the WordPress editor using article context.

## System placement

Primary backend home:

- `app/public/wp-content/plugins/dual-gpt-wordpress-plugin`

Why:

- already owns AI settings, REST routes, sessions, jobs, audit patterns, and editor-side integration patterns
- already provides provider configuration UI patterns
- keeps `khm-plugin` focused on editorial workflow rather than AI orchestration

## Architecture

### 1. Settings and policy

Store image configuration in WordPress options:

- provider enablement
- provider credentials
- default text recommendation provider
- default image generation provider
- provider fallback chain
- house style profile
- workflow permissions

### 2. Provider split

Treat recommendation and rendering as separate concerns:

- recommendation providers: generate visual direction and prompts from article context
- rendering providers: generate binary image output or hosted image results

Initial target providers:

- OpenAI
- Anthropic
- Google

Notes:

- Anthropic is expected to be more useful for prompt generation than direct rendering
- OpenAI and Google are stronger candidates for direct image output

### 3. Request pipeline

1. Extract article context from post or supplied payload
2. Merge with house style defaults
3. Produce recommendation bundle:
   - prompt
   - negative prompt
   - alt text
   - caption
   - rationale
4. Generate one or more images from selected provider
5. Persist provenance and optionally save into media library

### 4. UX surfaces

Admin:

- Dual-GPT `AI Images` submenu
- provider and house style settings page

Editor:

- recommend image
- generate image
- generate variants
- insert inline
- set featured image

Editorial Assistant:

- later integration after editor flow is stable
- show article-level recommendation and generation status

## Delivery phases

### Phase 1: foundation

- image settings model
- provider registry
- prompt builder
- REST routes
- admin settings page

### Phase 2: first working provider

- OpenAI prompt recommendation
- OpenAI image generation
- dry-run support for safe validation

### Phase 3: media workflow

- attachment creation
- featured image support
- provenance metadata

### Phase 4: editor UI

- Gutenberg sidebar or plugin panel
- contributor-safe controls

### Phase 5: editorial integration

- connect image generation to Editorial Assistant article workflow

## Initial file map

- `includes/class-image-settings.php`
- `includes/class-image-provider-registry.php`
- `includes/class-image-generation-service.php`
- `includes/class-image-prompt-builder.php`
- `includes/providers/class-openai-image-provider.php`
- admin updates in `admin/class-dual-gpt-admin.php`
- route/controller updates in `includes/class-dual-gpt-plugin.php`

## Initial REST surface

- `GET /dual-gpt/v1/images/config`
- `POST /dual-gpt/v1/images/recommend`
- `POST /dual-gpt/v1/images/generate`

## Immediate implementation goal

Land the backend foundation and admin settings first. Keep editor UI and media insertion for the next pass so the API contract is stable before front-end work begins.
