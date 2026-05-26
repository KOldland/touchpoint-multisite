# SMMA Generator Output

## Variant Schema

Generator variants must be structured as:

```json
{
  "variant_id": "uuid",
  "text": "string",
  "rationale": "string",
  "asset_hints": [
    {
      "type": "image|video|graphic",
      "description": "string"
    }
  ],
  "platform": "linkedin|google",
  "compliance_status": "PASS|WARN|FAIL",
  "compliance_reason": "string|null"
}
```

## Asset Hints

Asset hints are editor-facing creative guidance and are persisted with the variant payload:

- `type`: `image`, `video`, or `graphic`
- `description`: short implementation hint for creative production

## LLM Validation Rules

`LLMResponseParser` enforces:

- JSON payload must parse successfully.
- Top-level response must contain `variants`.
- Every variant must include `text`, `rationale`, and valid `asset_hints`.

Malformed output is rejected with:

```json
{
  "error": "Invalid generator response",
  "reason": "LLM returned malformed JSON",
  "error_type": "invalid_generator_response",
  "generator_request_id": "req_xxx"
}
```

No auto-correction is attempted for malformed LLM output.

## Variant Revision History

Every edit creates an immutable revision record in `variant_revisions`:

- `variant_id`
- `revision_id`
- `editor_user_id`
- `edited_at`
- `previous_text`
- `updated_text`
- `edit_reason` (optional)

Existing revisions are never overwritten.
