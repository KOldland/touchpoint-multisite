# Phase 3 Image Upload / Layout Preview Contract

Owner: AI-2

Status: Draft for frontend implementation handoff

Purpose:
Define the authoritative API contract for image upload, layout recommendation, composition preview, and optional optimization so the Phase 3 image UI can be implemented without inventing backend behavior.

## 1. Shared Rules

Base namespace:
- `/wp-json/kh-images/v1`

Auth:
- `upload`: authenticated editor or admin
- `layouts`: authenticated editor or admin
- `compose`: authenticated editor or admin
- `optimize`: authenticated editor or admin

Headers:
- `Content-Type: application/json` for JSON endpoints
- `X-WP-Nonce: <nonce>` for WordPress authenticated browser requests
- `X-Request-Id: <uuid>` recommended for traceability

Telemetry events:
- `images.uploaded`
- `images.layout.recommended`
- `images.composed`
- `images.optimized`

Error shape:

```json
{
  "code": "IMG_ERR_INVALID_FILE",
  "message": "Only JPEG, PNG, and WebP uploads are supported.",
  "retryable": false,
  "request_id": "req_img_001"
}
```

## 2. Field Shape Rules

- All uploaded images must return `width`, `height`, `orientation`, and `aspect_ratio`.
- `orientation` must be one of `portrait`, `landscape`, or `square`.
- `aspect_ratio` must be returned as a decimal string rounded to 4 decimal places.
- Frontend should prefer layouts whose slot ratios best fit the uploaded set.
- Compose previews must normalize output to a sqrt(2) canvas ratio where practical.
- No slot may extend beyond the declared canvas.
- Layout responses must use consistent spacing and slot ordering.
- Uploads must reject files over the backend-defined size cap and any unsupported MIME type.

## 3. POST /wp-json/kh-images/v1/upload

Purpose:
Upload an image and return normalized metadata for layout selection.

Request:
- `multipart/form-data`

Fields:
- `file` required, binary
- `source` required, string
- `reference_id` optional, string

Example `curl`:

```bash
curl -X POST "$HOST/wp-json/kh-images/v1/upload" \
  -H "X-WP-Nonce: $WP_NONCE" \
  -F "file=@sample-hero.jpg" \
  -F "source=phase3-demo" \
  -F "reference_id=post_123"
```

Success response:

```json
{
  "image_id": "img_hero_001",
  "width": 1600,
  "height": 900,
  "orientation": "landscape",
  "aspect_ratio": "1.7778",
  "thumbnail_url": "https://example.test/uploads/smma/thumbs/img_hero_001.jpg",
  "request_id": "req_img_upload_001"
}
```

Notes:
- `source` should identify the caller flow such as `phase3-demo` or `schedule-admin`.
- `reference_id` may map the upload to a post, schedule, or draft composition.

## 4. GET /wp-json/kh-images/v1/layouts?count=4&intent=5

Purpose:
Return recommended layouts for the current image selection intent.

Query params:
- `count` optional, integer, default `4`, max `12`
- `intent` optional, string or integer identifier for layout family

Example `curl`:

```bash
curl "$HOST/wp-json/kh-images/v1/layouts?count=4&intent=5" \
  -H "X-WP-Nonce: $WP_NONCE"
```

Success response:

```json
[
  {
    "layout_id": "layout_grid_2x2",
    "thumbnail": "https://example.test/uploads/smma/layouts/layout_grid_2x2.png",
    "intent": "5",
    "score": 0.96,
    "canvas": {
      "width": 1080,
      "height": 1527
    },
    "slots": [
      { "slot_index": 0, "x": 48, "y": 48, "w": 468, "h": 680 },
      { "slot_index": 1, "x": 564, "y": 48, "w": 468, "h": 680 },
      { "slot_index": 2, "x": 48, "y": 799, "w": 468, "h": 680 },
      { "slot_index": 3, "x": 564, "y": 799, "w": 468, "h": 680 }
    ]
  }
]
```

Notes:
- `score` is a recommendation value in the `0..1` range.
- `intent` should remain stable for deterministic fixture-based demos.

## 5. POST /wp-json/kh-images/v1/compose

Purpose:
Map uploaded images to a chosen layout and generate a preview image.

Request body:

```json
{
  "layout_id": "layout_grid_2x2",
  "images": [
    { "image_id": "img_hero_001", "slot_index": 0 },
    { "image_id": "img_quote_002", "slot_index": 1 },
    { "image_id": "img_team_003", "slot_index": 2 },
    { "image_id": "img_logo_004", "slot_index": 3 }
  ],
  "meta": {
    "title": "Autumn campaign preview"
  }
}
```

Example `curl`:

```bash
curl -X POST "$HOST/wp-json/kh-images/v1/compose" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $WP_NONCE" \
  -d @compose-request.json
```

Success response:

```json
{
  "preview_url": "https://example.test/uploads/smma/previews/compose_001.jpg",
  "composed_image_id": "cmp_001",
  "request_id": "req_img_compose_001"
}
```

Security:
- Compose must reject unauthenticated callers.
- Compose must validate that every `image_id` belongs to an accessible asset record.

## 6. POST /wp-json/kh-images/v1/optimize

Purpose:
Request backend-generated optimized outputs for a composed image or source upload.

Request body:

```json
{
  "image_id": "cmp_001",
  "variants": [
    { "name": "feed", "width": 1080, "height": 1527, "format": "jpg" },
    { "name": "thumb", "width": 540, "height": 764, "format": "webp" }
  ]
}
```

Success response:

```json
{
  "image_id": "cmp_001",
  "optimized": [
    {
      "name": "feed",
      "url": "https://example.test/uploads/smma/optimized/cmp_001-feed.jpg",
      "width": 1080,
      "height": 1527,
      "format": "jpg"
    },
    {
      "name": "thumb",
      "url": "https://example.test/uploads/smma/optimized/cmp_001-thumb.webp",
      "width": 540,
      "height": 764,
      "format": "webp"
    }
  ],
  "request_id": "req_img_optimize_001"
}
```

Notes:
- `optimize` is optional for the initial UI slice.
- Frontend may defer this call until export or approval workflows require it.

## 7. Friendly Error Examples

Upload too large:

```json
{
  "code": "IMG_ERR_FILE_TOO_LARGE",
  "message": "Upload exceeds the 10MB limit.",
  "retryable": false,
  "request_id": "req_img_err_001"
}
```

Compose permission denied:

```json
{
  "code": "IMG_ERR_FORBIDDEN",
  "message": "You do not have permission to compose layouts.",
  "retryable": false,
  "request_id": "req_img_err_002"
}
```

Unknown layout:

```json
{
  "code": "IMG_ERR_LAYOUT_NOT_FOUND",
  "message": "The requested layout is no longer available.",
  "retryable": true,
  "request_id": "req_img_err_003"
}
```

## 8. Mock Fixture Mapping

Deterministic frontend demos should use these fixture files:
- `tests/fixtures/images/upload_response.json`
- `tests/fixtures/images/layouts_response.json`
- `tests/fixtures/images/compose_response.json`
- `tests/fixtures/images/optimize_response.json`

## 9. Follow-up Implementation Notes

- UI implementation belongs in `ai2/phase3-images-ui`.
- Backend implementation must honor this contract or explicitly revise this document before UI work is merged.
- `docs/PHASE3_API_CONTRACT.md` should continue to reference this document instead of inventing image endpoints inline.
