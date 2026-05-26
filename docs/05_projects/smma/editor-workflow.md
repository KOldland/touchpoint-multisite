# SMMA Editor Workflow

## Overview

Card 06 adds the editor UI flow for:

1. Generate variants from a post
2. Edit variants inline
3. Schedule approved variants

The UI orchestrates existing SMMA APIs only.

## Entry Point

On post edit screens (`post` and `page`), a **Boost Post** button appears in the
`SMMA Boost Workflow` meta box.

## Generate Workflow

- Open **Generate Variants** modal
- Set:
  - number of variants
  - platform target
- Submit to:
  - `POST /wp-json/kh-smma/v1/generate`

Payload includes:

- `post_id`
- `blocks_summary`
- `num_variants`

## Variant Grid

Each card displays:

- variant text
- rationale
- asset hints
- compliance badge

Compliance badge states:

- `OK` (green)
- `WARN` (yellow)
- `FAIL` (red)

Tooltips display `compliance_reason` and `ai_review_summary`.

## Variant Editing

From each card, **Edit** opens the inline editor modal.

Save calls:

- `POST /wp-json/kh-smma/v1/variant/{variant_id}/edit`

Headers include `Idempotency-Key`.

UI feedback:

- "Variant updated successfully"
- compliance badge refresh after response

## Scheduling Flow

From each non-FAIL card, **Schedule** opens a scheduling modal.

Submit calls:

- `POST /wp-json/kh-smma/v1/schedule`

Headers include `Idempotency-Key`.

Displayed result includes:

- `schedule_id`
- `approval_status`

FAIL variants are blocked from scheduling in UI.
