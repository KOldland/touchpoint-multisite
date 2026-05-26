# SMMA Testing

## Deterministic Test Model

SMMA test execution in CI uses deterministic fixtures and MockLLM behavior.

Environment:

- `KH_SMMA_TEST_MODE=ci`
- no real LLM API keys allowed

## Fixtures

Primary deterministic fixture directories:

- `tests/fixtures/smma/generation/`
- `tests/fixtures/smma/`
- `tests/fixtures/golden/`

Examples:

- `generation/generate_awareness_ok.json`
- `variant_edit_case.json`
- `export_manifest.json`
- `workflow_smoke_case.json`

## MockLLM Provider

`src/Testing/MockLLMProvider.php` loads generation fixtures and returns a stable
LLM envelope (`choices[0].message.content`) for parser tests.

## Core SMMA Test Files

- `tests/SMMA/GeneratorParsingTest.php`
- `tests/SMMA/VariantEditPersistenceTest.php`
- `tests/SMMA/ExportValidationTest.php`
- `tests/SMMA/WorkflowSmokeTest.php`

## CI Workflow

CI runs:

- `vendor/bin/phpunit tests/SMMA`
- fixture verification scripts
- deterministic golden checks

Coverage output is printed in CI when coverage extension support is available.

## Smoke Harness

Run local workflow smoke harness:

- `vendor/bin/phpunit tests/SMMA/WorkflowSmokeTest.php`

Expected result:

- `PASS`
- deterministic generate -> edit -> schedule -> export chain verified

## Local Development Setup

Required tools:

- PHP (8.1+ recommended)
- Composer
- PHPUnit (`vendor/bin/phpunit`)

Commands:

- full SMMA suite:
  - `vendor/bin/phpunit tests/SMMA`
- smoke only:
  - `vendor/bin/phpunit tests/SMMA/WorkflowSmokeTest.php`

## Troubleshooting

| Issue | Cause | Symptoms | Resolution |
|---|---|---|---|
| Non-JSON generator response | malformed LLM fixture/response | parser errors (`SMMA_ERR_INVALID_LLM`) | check `MockLLMProvider` fixture content and validate generator JSON schema |
| Compliance FAIL | banned phrase or restricted claim | `COMPLIANCE_FAIL`, scheduling blocked | edit variant text, rerun compliance via variant edit flow |
| Queue backlog | blocked dispatch or slow downstream processing | backlog alerts / delayed schedule progress | inspect observability dashboard, check approval status and adapter execution path |
| Export failure | bundle/manifest generation issue | missing zip or missing manifest entries | validate `ExportBundleService`, confirm `export_manifest.json` fixture and writable exports directory |

## CI Behavior

Workflow file:

- `.github/workflows/smma-ci.yml`

CI validates:

- SMMA PHPUnit suite (`tests/SMMA`)
- fixture checks / golden verification
- deterministic parser + smoke coverage
- coverage text output (when `xdebug`/`pcov` available)

CI fails on:

- fixture mismatch
- parser/test failures
- smoke test failures

## Telemetry Verification in Tests/Demo

During smoke/demo runs confirm these events appear in observability/audit views:

- `generate.request`
- `generate.response`
- `variant.edit`
- `schedule.create`
- `schedule.dispatch`
