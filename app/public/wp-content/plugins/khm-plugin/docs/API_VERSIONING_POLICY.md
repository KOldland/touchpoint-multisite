# API Versioning Policy (v1/v2)

## Summary
- **v1 endpoints are stable** and should remain backward compatible.
- **Breaking changes require a new v2 namespace** (or higher).
- Additive changes are allowed in v1.

## Examples
- ✅ Add a new optional field in a response (v1).
- ✅ Add a new query parameter with a default (v1).
- ❌ Change response shape or remove a field (requires v2).

## Notes
This policy applies to public REST endpoints, including `/wp-json/khm/v1/*`.
