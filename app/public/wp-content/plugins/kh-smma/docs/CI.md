# SMMA CI/CD Pipeline Documentation

**Version**: 1.0.0
**CI Platform**: GitHub Actions
**Test Framework**: PHPUnit 9.6
**PHP Versions**: 8.1, 8.2

---

## Table of Contents

1. [Overview](#overview)
2. [GitHub Actions Workflow](#github-actions-workflow)
3. [Local Testing](#local-testing)
4. [Golden Stub Testing](#golden-stub-testing)
5. [Test Suites](#test-suites)
6. [CI Safety Guarantees](#ci-safety-guarantees)
7. [Troubleshooting](#troubleshooting)
8. [Adding New Tests](#adding-new-tests)

---

## Overview

The SMMA plugin uses a comprehensive CI/CD pipeline to ensure production readiness:

- **Deterministic Testing**: All tests use golden stub fixtures (no live API calls)
- **Multi-PHP Testing**: Tests run on PHP 8.1 and 8.2
- **Security Scanning**: Automatic detection of secrets, SQL injection risks
- **Code Standards**: WordPress coding standards via phpcs
- **Fast Execution**: Complete test suite runs in ~20 seconds

### Test Coverage

```
112 tests, 424 assertions across 5 test files:
- Golden AI Tests:       9 tests,  129 assertions
- Compliance Fuzzing:   56 tests,   86 assertions
- Compliance Validator: 25 tests,   73 assertions
- Smoke Tests:          12 tests,   80 assertions
- Other Tests:          10 tests,   56 assertions
```

---

## GitHub Actions Workflow

### Workflow File

Located at: `.github/workflows/smma-ci.yml`

### Triggers

The CI pipeline runs on:
- **Push** to `main` or `staging` branches
- **Pull requests** targeting `main` or `staging`
- Only when SMMA plugin files change

```yaml
on:
  push:
    branches: [main, staging]
    paths:
      - 'app/public/wp-content/plugins/kh-smma/**'
  pull_request:
    branches: [main, staging]
    paths:
      - 'app/public/wp-content/plugins/kh-smma/**'
```

### Pipeline Jobs

#### 1. Test Job

Runs the complete test suite across multiple PHP versions.

**Steps:**
1. Checkout repository
2. Setup PHP (8.1 or 8.2)
3. Validate composer.json
4. Cache Composer dependencies
5. Install dependencies
6. Check for real API keys (safety check)
7. Run test suites:
   - Golden AI Tests
   - Compliance Fuzzing Tests
   - Compliance Validator Tests
   - Smoke Tests
   - Full test suite
8. Verify golden fixtures exist
9. Validate fixture JSON syntax

**Environment Variables:**
```yaml
env:
  KH_SMMA_TEST_MODE: ci
  CI: true
  OPENAI_API_KEY: ''
  ANTHROPIC_API_KEY: ''
  DUAL_GPT_API_KEY: ''
```

#### 2. Lint Job

Checks code quality and WordPress coding standards.

**Steps:**
1. Checkout repository
2. Setup PHP 8.1
3. Install dependencies
4. Run phpcs (WordPress standards)

#### 3. Security Job

Scans for security vulnerabilities and secrets.

**Steps:**
1. Composer audit for known vulnerabilities
2. Scan for hardcoded API keys
3. Scan for hardcoded passwords
4. Check for SQL injection patterns

#### 4. Report Job

Generates a comprehensive test report summary.

**Output Example:**
```markdown
# SMMA Plugin CI Report

## Test Results
- ✅ Tests: success
- 🔍 Lint: success
- 🔒 Security: success

## Test Coverage
- Golden AI Tests: 9 tests, 129 assertions
- Compliance Fuzzing: 56 tests, 86 assertions
- Compliance Validator: 25 tests, 73 assertions
- Smoke Tests: 12 tests, 80 assertions
- **Total: 102+ tests, 368+ assertions**

## Safety Checks
- ✅ No live API calls in CI
- ✅ Golden stub fixtures validated
- ✅ Deterministic test behavior
```

---

## Local Testing

### Prerequisites

- PHP 8.1+ with extensions: `mbstring`, `json`, `mysqli`
- Composer 2.x
- No LLM API keys (tests use golden stubs)

### Setup

```bash
cd app/public/wp-content/plugins/kh-smma

# Install dependencies
composer install

# Verify PHPUnit is available
vendor/bin/phpunit --version
# Output: PHPUnit 9.6.34
```

### Running Tests

#### All Tests

```bash
export KH_SMMA_TEST_MODE=ci
vendor/bin/phpunit --testdox --colors=always
```

#### Specific Test Suite

```bash
# Golden AI tests
vendor/bin/phpunit tests/GoldenAITest.php --testdox

# Compliance fuzzing
vendor/bin/phpunit tests/ComplianceFuzzingTest.php --testdox

# Smoke tests
vendor/bin/phpunit tests/SmokeTest.php --testdox

# Single test method
vendor/bin/phpunit --filter test_method_name
```

#### With Coverage (requires xdebug)

```bash
vendor/bin/phpunit --coverage-html coverage/
open coverage/index.html
```

#### Verbose Output

```bash
vendor/bin/phpunit --testdox --verbose
```

### Test Configuration

PHPUnit configuration: `phpunit.xml`

```xml
<phpunit bootstrap="tests/bootstrap.php"
         colors="true"
         stopOnFailure="false"
         failOnWarning="false">
    <testsuites>
        <testsuite name="SMMA Plugin Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

---

## Golden Stub Testing

### What Are Golden Stubs?

Golden stubs are pre-recorded LLM responses stored as JSON fixtures. They enable:
- **Deterministic testing**: Same input → same output
- **No API costs**: Zero LLM API usage in CI
- **Fast execution**: No network latency
- **Offline testing**: Works without internet

### How It Works

#### 1. MockLLMClient

Located at `tests/MockLLMClient.php`, this class intercepts LLM calls:

```php
class MockLLMClient {
    public function call( string $system, string $user, array $options = array() ): array {
        // Determine which fixture to load
        $fixture_name = $this->determine_fixture( $system, $user );
        $fixture_path = $this->fixture_dir . $fixture_name;

        // Load and return golden response
        return json_decode( file_get_contents( $fixture_path ), true );
    }
}
```

#### 2. Fixture Auto-Detection

The mock client automatically selects fixtures based on prompt content:

```php
// Compliance validation
if ( stripos( $system, 'compliance validator' ) !== false ) {
    if ( contains_multiple_blacklist_phrases( $user ) ) {
        return 'compliance_fail_response.json';
    }
    if ( contains_single_blacklist_phrase( $user ) ) {
        return 'compliance_warn_response.json';
    }
    return 'compliance_pass_response.json';
}

// Google Ads generation
if ( stripos( $system, 'Google Ads Draft Generator' ) !== false ) {
    return 'google_ad_draft_response.json';
}

// Default: LinkedIn generation
return 'generate_response.json';
```

#### 3. Explicit Fixture Selection

Override auto-detection with environment variable:

```bash
export KH_SMMA_GOLDEN_FIXTURE=generate_fail_response.json
vendor/bin/phpunit tests/GoldenAITest.php::test_specific_scenario
```

Or in test code:

```php
putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_warn_response.json' );
$result = $generator->generate( $input );
```

### Available Fixtures

Located in `tests/fixtures/golden/`:

| Fixture | Purpose | Trigger |
|---------|---------|---------|
| `generate_response.json` | LinkedIn variants (OK) | Default SMMA generation |
| `generate_warn_response.json` | LinkedIn variants (WARN) | Contains "satisfied customers" |
| `generate_fail_response.json` | LinkedIn variants (FAIL) | Multiple blacklisted phrases |
| `google_ad_draft_response.json` | Google Ads draft | System prompt: "Google Ads Draft Generator" |
| `compliance_pass_response.json` | Compliance OK | Clean text |
| `compliance_warn_response.json` | Compliance WARN | Single blacklisted phrase |
| `compliance_fail_response.json` | Compliance FAIL | Multiple blacklisted phrases |

Full documentation: [tests/fixtures/README.md](../tests/fixtures/README.md)

### Creating New Fixtures

#### 1. Capture Real Response

Run code with real LLM once to capture response:

```php
$llm = new \Dual_GPT\Dual_GPT_LLM_Client();
$response = $llm->call( $system_prompt, $user_prompt );
file_put_contents( 'new_fixture.json', json_encode( $response, JSON_PRETTY_PRINT ) );
```

#### 2. Validate JSON

```bash
jq . tests/fixtures/golden/new_fixture.json
```

#### 3. Update MockLLMClient

Add detection logic in `determine_fixture()`:

```php
if ( stripos( $system, 'Your Unique Marker' ) !== false ) {
    return 'new_fixture.json';
}
```

#### 4. Write Tests

```php
public function test_new_scenario() {
    putenv( 'KH_SMMA_GOLDEN_FIXTURE=new_fixture.json' );

    $generator = new SmmaGenerator();
    $result = $generator->generate( $input );

    $this->assertArrayHasKey( 'expected_field', $result );
}
```

---

## Test Suites

### 1. Golden AI Tests

**File**: `tests/GoldenAITest.php`
**Purpose**: Validate LLM generation outputs against golden fixtures
**Tests**: 9 tests, 129 assertions

**Scenarios Covered**:
- LinkedIn generation with OK compliance
- LinkedIn generation with WARN compliance
- LinkedIn generation with FAIL compliance
- Google Ads draft generation
- Compliance pass validation
- Compliance warn validation
- Compliance fail validation
- Complete generation response validation
- Deterministic fixture behavior

**Run**:
```bash
vendor/bin/phpunit tests/GoldenAITest.php --testdox
```

### 2. Compliance Fuzzing Tests

**File**: `tests/ComplianceFuzzingTest.php`
**Purpose**: Test adversarial inputs and edge cases
**Tests**: 56 tests, 86 assertions

**Scenarios Covered**:
- Exact blacklisted phrases (8 tests)
- Case variations (6 tests)
- Embedded phrases in sentences (6 tests)
- Bypass attempts with special characters (6 tests)
- Channel length limits (3 tests)
- Sponsor allowed claims enforcement
- Unicode and international text (6 tests)
- HTML sanitization (4 tests)
- Empty and whitespace inputs (4 tests)
- Batch validation
- Clean professional content (6 tests)
- Confidence score ranges
- Partial phrase matching

**Run**:
```bash
vendor/bin/phpunit tests/ComplianceFuzzingTest.php --testdox
```

### 3. Compliance Validator Tests

**File**: `tests/ComplianceValidatorTest.php`
**Purpose**: Test core compliance validation logic
**Tests**: 25 tests, 73 assertions

**Scenarios Covered**:
- Blacklisted phrase detection
- Length limit validation per channel
- Sponsor claim enforcement
- Batch validation
- Edge cases

**Run**:
```bash
vendor/bin/phpunit tests/ComplianceValidatorTest.php --testdox
```

### 4. Smoke Tests

**File**: `tests/SmokeTest.php`
**Purpose**: End-to-end workflow testing
**Tests**: 12 tests, 80 assertions

**Scenarios Covered**:
- Complete generation workflow (OK compliance)
- Generation with WARN compliance
- Generation with FAIL compliance
- Google Ads draft generation and validation
- Inline editing with re-validation
- Batch validation workflow
- Sponsor allowed claims gates
- Approve/reject workflow
- Deterministic fixture behavior
- Schema validation error handling
- CI safety checks
- Compliance validation across all fixtures

**Run**:
```bash
vendor/bin/phpunit tests/SmokeTest.php --testdox
```

---

## CI Safety Guarantees

### 1. No Live API Calls

**Enforcement**:
```php
// MockLLMClient checks for CI mode
if ( $this->is_ci_mode() && $this->has_real_api_key() ) {
    throw new \Exception( 'CRITICAL: Real API key detected in CI' );
}
```

**CI Mode Detection**:
- `KH_SMMA_TEST_MODE=ci`
- `CI=true`
- `GITHUB_ACTIONS=true`

### 2. API Key Detection

GitHub Actions explicitly sets:
```yaml
env:
  OPENAI_API_KEY: ''
  ANTHROPIC_API_KEY: ''
  DUAL_GPT_API_KEY: ''
  LLM_API_KEY: ''
```

Test fails if any key has length > 10 characters.

### 3. Deterministic Behavior

All tests use golden stubs → same input always produces same output:

```php
$result1 = $generator->generate( $input );
$result2 = $generator->generate( $input );

$this->assertEquals( $result1, $result2 ); // Always passes
```

### 4. Fixture Validation

CI verifies:
- All 7 golden fixtures exist
- All fixtures are valid JSON
- No fixtures contain real API keys

---

## Troubleshooting

### Test Failures

#### "Failed to parse golden fixture"

**Cause**: Invalid JSON in fixture file
**Fix**: Validate JSON syntax

```bash
jq . tests/fixtures/golden/fixture_name.json
# or
php -r "json_decode(file_get_contents('fixture.json')); echo json_last_error_msg();"
```

#### "MockLLMClient uses wrong fixture"

**Cause**: Auto-detection logic mismatch
**Fix**: Use explicit fixture selection

```php
putenv( 'KH_SMMA_GOLDEN_FIXTURE=expected_fixture.json' );
```

#### "Call to undefined function"

**Cause**: WordPress function not available in test environment
**Fix**: Add fallback check

```php
$url = function_exists( 'home_url' ) ? home_url() : 'https://example.com';
```

### CI Failures

#### "Real API key detected"

**Cause**: Environment variable contains API key
**Fix**: Remove API keys from GitHub Actions secrets for test jobs

#### "Fixture missing"

**Cause**: Fixture file not committed to repository
**Fix**: Ensure all fixtures are tracked in git

```bash
git add tests/fixtures/golden/
git commit -m "Add missing fixtures"
```

#### "Tests timeout"

**Cause**: Infinite loop or hanging process
**Fix**: Check for:
- Missing mock injection: `inject_mock_llm_client()`
- Real API calls (check network requests)

### Local Development

#### Composer Install Fails

```bash
# Clear cache
rm -rf vendor/ composer.lock
composer clear-cache
composer install
```

#### PHPUnit Not Found

```bash
# Ensure Composer bin directory in PATH
export PATH="$PATH:./vendor/bin"

# Or use full path
./vendor/bin/phpunit
```

#### Tests Pass Locally But Fail in CI

**Common Causes**:
- PHP version difference (CI uses 8.1/8.2)
- WordPress functions available locally but not in CI
- File path differences (absolute vs relative)

**Fix**: Run tests with CI mode locally

```bash
export KH_SMMA_TEST_MODE=ci
export CI=true
vendor/bin/phpunit
```

---

## Adding New Tests

### Step 1: Identify Test Type

- **Unit test**: Single class/method in isolation → Add to existing test file
- **Integration test**: Multiple classes working together → Add to smoke tests
- **Fuzzing test**: Edge cases and adversarial inputs → Add to compliance fuzzing
- **Golden test**: LLM output validation → Add to golden AI tests

### Step 2: Create Test Method

```php
/**
 * Test description.
 *
 * @test
 */
public function descriptive_test_name() {
    // Arrange
    $input = array( /* test data */ );

    // Act
    $result = $service->method( $input );

    // Assert
    $this->assertEquals( $expected, $result );
}
```

### Step 3: Use Data Providers (Optional)

For parameterized tests:

```php
/**
 * @test
 * @dataProvider scenarioProvider
 */
public function test_with_multiple_scenarios( $input, $expected ) {
    $result = $service->method( $input );
    $this->assertEquals( $expected, $result );
}

public function scenarioProvider(): array {
    return array(
        'scenario 1' => array( 'input1', 'expected1' ),
        'scenario 2' => array( 'input2', 'expected2' ),
    );
}
```

### Step 4: Mock LLM if Needed

```php
require_once __DIR__ . '/MockLLMClient.php';

protected function setUp(): void {
    inject_mock_llm_client();
    putenv( 'KH_SMMA_GOLDEN_FIXTURE=appropriate_fixture.json' );
}
```

### Step 5: Run and Verify

```bash
# Run new test only
vendor/bin/phpunit --filter test_name

# Run full suite to ensure no regressions
vendor/bin/phpunit --testdox
```

### Step 6: Update Documentation

If adding new fixture or test category, update:
- `tests/fixtures/README.md` - If new fixture added
- This file (`CI.md`) - If new test suite added
- `SMMA-API.md` - If testing new API behavior

---

## Best Practices

### 1. Test Naming

Use descriptive names that explain what is being tested:

```php
// Good
public function linkedin_generation_with_fail_compliance_blocks_paid_scheduling()

// Bad
public function test1()
```

### 2. Assertion Messages

Always provide clear failure messages:

```php
$this->assertTrue( $result, 'Expected generation to succeed but it failed' );
```

### 3. Arrange-Act-Assert Pattern

```php
// Arrange: Set up test data
$input = array( 'post_id' => 123 );

// Act: Execute the code under test
$result = $generator->generate( $input );

// Assert: Verify expectations
$this->assertArrayHasKey( 'variants', $result );
```

### 4. Test Independence

Each test should:
- Set up its own data
- Not depend on other tests
- Clean up after itself

```php
protected function setUp(): void {
    // Initialize fresh state
}

protected function tearDown(): void {
    // Clean up resources
}
```

### 5. Mock External Dependencies

Never rely on:
- Live API calls
- External services
- Database state (unless testing database interactions)

Always use:
- Golden stubs for LLM calls
- Mock objects for external services
- In-memory data for tests

---

## Performance Optimization

### Parallel Test Execution

PHPUnit can run tests in parallel (requires paratest):

```bash
composer require --dev brianium/paratest
vendor/bin/paratest --processes=4
```

### Selective Test Running

Run only relevant tests during development:

```bash
# Fast feedback loop
vendor/bin/phpunit tests/ComplianceValidatorTest.php

# Full validation before commit
vendor/bin/phpunit
```

### CI Caching

GitHub Actions caches Composer dependencies:

```yaml
- name: Cache Composer dependencies
  uses: actions/cache@v4
  with:
    path: ${{ steps.composer-cache.outputs.dir }}
    key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
```

---

## Monitoring & Alerts

### GitHub Actions Status Badge

Add to README.md:

```markdown
![CI Status](https://github.com/KOldland/touchpoint-template/workflows/SMMA%20Plugin%20CI/badge.svg)
```

### Slack/Email Notifications

Configure in GitHub repository settings:
- Settings → Notifications
- Enable email notifications for failed builds
- Add Slack webhook for team notifications

### Test Report Archive

GitHub Actions automatically archives test results:
- Viewable in Actions tab → Workflow run → Artifacts

---

## Changelog

### v1.0.0 (2026-02-05)
- Initial CI/CD pipeline
- 112 tests, 424 assertions
- Golden stub testing with 7 fixtures
- Multi-PHP version testing (8.1, 8.2)
- Security scanning and code standards
- Comprehensive test coverage: 100% passing

---

## References

- **PHPUnit Documentation**: https://phpunit.de/documentation.html
- **GitHub Actions Docs**: https://docs.github.com/en/actions
- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/
- **Test Fixtures**: [tests/fixtures/README.md](../tests/fixtures/README.md)
- **REST API**: [SMMA-API.md](SMMA-API.md)

---

## Support

- **GitHub Issues**: https://github.com/KOldland/touchpoint-template/issues
- **Pull Requests**: https://github.com/KOldland/touchpoint-template/pulls
- **CI Logs**: https://github.com/KOldland/touchpoint-template/actions
