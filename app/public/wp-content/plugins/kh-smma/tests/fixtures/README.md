# SMMA Test Fixtures

Golden stub fixtures for deterministic LLM testing. These fixtures ensure tests run consistently without live API calls.

## Directory Structure

```
fixtures/
├── golden/                           # LLM response fixtures
│   ├── generate_response.json        # LinkedIn variants (OK)
│   ├── generate_warn_response.json   # LinkedIn variants (WARN)
│   ├── generate_fail_response.json   # LinkedIn variants (FAIL)
│   ├── google_ad_draft_response.json # Google Ads draft
│   ├── compliance_pass_response.json # Compliance check (PASS)
│   ├── compliance_warn_response.json # Compliance check (WARN)
│   └── compliance_fail_response.json # Compliance check (FAIL)
└── README.md                         # This file
```

## Golden Fixtures (6 Canonical Scenarios)

### 1. `generate_response.json` - LinkedIn Generation (OK)
**Purpose**: Clean LinkedIn variants that pass all compliance checks.

**Usage**:
```php
putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );
$generator->generate( $input );
```

**Contains**:
- 2 LinkedIn variants
- Clean text (no compliance issues)
- Complete variant schema with all fields
- Attention and Anxiety phase examples

---

### 2. `generate_warn_response.json` - LinkedIn Generation (WARN)
**Purpose**: LinkedIn variants with sponsor claims needing verification.

**Triggered by**: User prompts containing "satisfied customers" or "test_warn"

**Contains**:
- LinkedIn variant with vague success claims
- `compliance_notes: "WARN: contains vague success claims"`
- Sponsor flag enabled
- Requires sponsor verification

---

### 3. `generate_fail_response.json` - LinkedIn Generation (FAIL)
**Purpose**: LinkedIn variants with critical compliance violations.

**Triggered by**: User prompts containing multiple blacklisted phrases

**Contains**:
- Text with "guaranteed results", "risk-free", "100% guaranteed"
- `compliance_notes: "FAIL: multiple blacklisted phrases detected"`
- Should block paid scheduling

---

### 4. `google_ad_draft_response.json` - Google Ads Draft
**Purpose**: Valid Google Ads draft with multiple ad groups.

**Triggered by**: System prompt containing "Google Ads Draft Generator"

**Contains**:
- 2 ad groups with keyword clusters
- Headlines (3 per group, max 30 chars)
- Descriptions (2 per group, max 90 chars)
- CPC suggestions
- UTM-tracked URLs

---

### 5. `compliance_pass_response.json` - Compliance Check (PASS)
**Purpose**: Compliance validation that passes all rules.

**Triggered by**: Compliance validator with clean text

**Contains**:
```json
{
  "passed": true,
  "message": "",
  "confidence_score": 0.95,
  "flags": []
}
```

---

### 6. `compliance_warn_response.json` - Compliance Check (WARN)
**Purpose**: Compliance validation with warnings.

**Triggered by**: Text containing single blacklisted phrase

**Contains**:
```json
{
  "passed": false,
  "message": "Text contains potentially misleading claim...",
  "confidence_score": 0.85,
  "flags": ["unverified_claim", "sponsor_approval_required"]
}
```

---

### 7. `compliance_fail_response.json` - Compliance Check (FAIL)
**Purpose**: Compliance validation with critical failures.

**Triggered by**: Text containing multiple blacklisted phrases (e.g., "guaranteed" + "risk-free")

**Contains**:
```json
{
  "passed": false,
  "message": "Text contains multiple prohibited phrases...",
  "confidence_score": 1.0,
  "flags": ["blacklist_violation", "guaranteed_claims", "medical_claims"]
}
```

## Usage in Tests

### Explicit Fixture Selection

```php
use KH_SMMA\Tests\inject_mock_llm_client;

// Inject mock LLM client
inject_mock_llm_client();

// Select specific fixture
putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_warn_response.json' );

// Run test
$generator = new SmmaGenerator();
$result = $generator->generate( $input );
```

### Automatic Fixture Selection

The `MockLLMClient` automatically selects fixtures based on prompt content:

```php
// This will use google_ad_draft_response.json
$google_service = new GoogleAdDraftService();
$draft = $google_service->generate( $input );

// This will use compliance_fail_response.json
$validator = new ComplianceValidator();
$result = $validator->validate( 'guaranteed risk-free results', $context );
```

### CI Safety

In CI mode (`KH_SMMA_TEST_MODE=ci` or `CI=true`), the mock client will:
1. Prevent any live API calls
2. Fail tests if real API keys are detected
3. Ensure deterministic test behavior

```bash
export KH_SMMA_TEST_MODE=ci
vendor/bin/phpunit tests/
```

## Adding New Fixtures

### Step 1: Create Fixture File

```json
{
  "choices": [
    {
      "message": {
        "content": "{\"your\":\"json\",\"response\":\"here\"}"
      }
    }
  ],
  "model": "gpt-4-turbo",
  "usage": {
    "prompt_tokens": 250,
    "completion_tokens": 120,
    "total_tokens": 370
  }
}
```

**Important**: The `content` field must be a JSON-encoded string (not a raw object).

### Step 2: Update MockLLMClient

Add detection logic in `determine_fixture()`:

```php
private function determine_fixture( string $system, string $user ): string {
    // Add your condition
    if ( stripos( $system, 'Your Prompt Marker' ) !== false ) {
        return 'your_new_fixture.json';
    }

    // Existing logic...
}
```

### Step 3: Write Tests

```php
public function test_your_new_scenario() {
    inject_mock_llm_client();
    putenv( 'KH_SMMA_GOLDEN_FIXTURE=your_new_fixture.json' );

    $generator = new SmmaGenerator();
    $result = $generator->generate( $input );

    $this->assertArrayHasKey( 'your_field', $result );
}
```

## Fixture Schema Validation

All fixtures should match these schemas:

### LLM Response Envelope
```json
{
  "choices": [
    {
      "message": {
        "content": "<JSON_STRING>"
      }
    }
  ],
  "model": "string",
  "usage": {
    "prompt_tokens": int,
    "completion_tokens": int,
    "total_tokens": int
  }
}
```

### LinkedIn Variant Content (inside `content` field)
```json
{
  "variants": [
    {
      "variant_id": "string",
      "channel": "linkedin",
      "text": "string",
      "phase_tag": "Attention|Anxiety|Action|Aftercare",
      "tone": "string",
      "recommended_post_time_gmt": int,
      "geo_recommendations": [],
      "sponsor_flag": bool,
      "compliance_notes": "string",
      "explainability": "string"
    }
  ]
}
```

### Google Ads Draft Content (inside `content` field)
```json
{
  "ad_groups": [
    {
      "keyword_cluster": "string",
      "headlines": ["30 char max", "30 char", "30 char"],
      "descriptions": ["90 char max", "90 char"],
      "final_url": "https://...",
      "final_url_with_utm": "https://...",
      "cpc_suggestion": 0.00
    }
  ]
}
```

### Compliance Response Content (inside `content` field)
```json
{
  "passed": bool,
  "message": "string",
  "confidence_score": 0.0-1.0,
  "flags": ["string"]
}
```

## Maintenance Guidelines

1. **Never modify fixtures in-place** - Create new versions if schema changes
2. **Keep fixtures minimal** - Only include data required for tests
3. **Validate JSON** - Use `jq` or similar to ensure valid JSON
4. **Document triggers** - Update this README when adding detection logic
5. **Test coverage** - Ensure each fixture has at least one test

## Troubleshooting

### Test fails with "Failed to parse golden fixture"
- Check JSON syntax with `jq < fixture.json`
- Ensure `content` field is a JSON-encoded string (double-encoded)

### MockLLMClient uses wrong fixture
- Check `determine_fixture()` logic in `MockLLMClient.php`
- Use explicit fixture selection: `putenv('KH_SMMA_GOLDEN_FIXTURE=...')`

### CI fails with "real API key detected"
- Remove API keys from CI environment variables
- Set `KH_SMMA_TEST_MODE=ci` to enforce mock mode

---

## Quick Reference

| Scenario | Fixture | Trigger |
|----------|---------|---------|
| LinkedIn OK | `generate_response.json` | Default for SMMA-AI prompts |
| LinkedIn WARN | `generate_warn_response.json` | "satisfied customers" or "test_warn" |
| LinkedIn FAIL | `generate_fail_response.json` | Multiple blacklisted phrases |
| Google Ads | `google_ad_draft_response.json` | "Google Ads Draft Generator" in system |
| Compliance OK | `compliance_pass_response.json` | Clean compliance text |
| Compliance WARN | `compliance_warn_response.json` | Single blacklisted phrase |
| Compliance FAIL | `compliance_fail_response.json` | Multiple blacklisted phrases |

---

**Last Updated**: 2026-02-04
**Version**: 1.0.0
**Maintainer**: SMMA Backend Team
