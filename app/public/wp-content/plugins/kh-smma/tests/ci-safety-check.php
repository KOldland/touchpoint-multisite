<?php
/**
 * CI Safety Check Script
 *
 * Run this before running tests in CI to ensure:
 * 1. No live LLM API keys are present
 * 2. Test mode is properly configured
 * 3. Golden stubs are available
 *
 * Usage:
 *   php tests/ci-safety-check.php
 *
 * Exit codes:
 *   0 = Safe to run tests
 *   1 = Unsafe (live API keys detected)
 *   2 = Configuration error
 */

namespace KH_SMMA\Tests;

// Detect if running in CI
$is_ci = getenv( 'CI' ) === 'true' ||
         getenv( 'GITHUB_ACTIONS' ) === 'true' ||
         getenv( 'TRAVIS' ) === 'true' ||
         getenv( 'CIRCLECI' ) === 'true';

echo "CI Safety Check\n";
echo "===============\n\n";

// Check 1: Detect live API keys
echo "[1/4] Checking for live API keys...\n";
$api_key_vars = array(
	'OPENAI_API_KEY',
	'ANTHROPIC_API_KEY',
	'DUAL_GPT_API_KEY',
	'LLM_API_KEY',
	'CLAUDE_API_KEY',
);

$found_keys = array();
foreach ( $api_key_vars as $var ) {
	$value = getenv( $var );
	if ( ! empty( $value ) && strlen( $value ) > 10 ) {
		$found_keys[] = $var;
	}
}

if ( ! empty( $found_keys ) ) {
	if ( $is_ci ) {
		echo "   ❌ CRITICAL: Live API keys detected in CI environment!\n";
		echo "   Found keys: " . implode( ', ', $found_keys ) . "\n";
		echo "   Tests MUST use golden stubs only.\n";
		echo "   Remove these keys from CI environment variables.\n\n";
		exit( 1 );
	} else {
		echo "   ⚠️  Warning: Live API keys detected in local environment.\n";
		echo "   Found keys: " . implode( ', ', $found_keys ) . "\n";
		echo "   Local tests will use live LLM calls unless KH_SMMA_TEST_MODE=ci is set.\n\n";
	}
} else {
	echo "   ✓ No live API keys detected\n\n";
}

// Check 2: Verify test mode configuration
echo "[2/4] Checking test mode configuration...\n";
$test_mode = getenv( 'KH_SMMA_TEST_MODE' );
if ( $is_ci && $test_mode !== 'ci' ) {
	echo "   ⚠️  Warning: CI detected but KH_SMMA_TEST_MODE not set to 'ci'\n";
	echo "   Recommend: export KH_SMMA_TEST_MODE=ci\n\n";
} elseif ( $is_ci ) {
	echo "   ✓ Test mode properly configured (KH_SMMA_TEST_MODE=ci)\n\n";
} else {
	echo "   ℹ️  Local environment detected\n";
	if ( $test_mode === 'ci' ) {
		echo "   ✓ KH_SMMA_TEST_MODE=ci (will use golden stubs)\n\n";
	} else {
		echo "   ⚠️  KH_SMMA_TEST_MODE not set (may use live LLM calls)\n\n";
	}
}

// Check 3: Verify golden fixtures exist
echo "[3/4] Checking golden fixtures...\n";
$fixture_dir = __DIR__ . '/fixtures/golden/';
$required_fixtures = array(
	'generate_awareness_ok.json',
	'generate_sponsor_warn.json',
	'generate_sponsor_fail.json',
	'google_ad_draft.json',
	'compliance_ok.json',
	'compliance_warn.json',
	'compliance_fail.json',
	'generate_response.json',
	'compliance_pass_response.json',
	'compliance_warn_response.json',
);

$missing_fixtures = array();
foreach ( $required_fixtures as $fixture ) {
	$path = $fixture_dir . $fixture;
	if ( ! file_exists( $path ) ) {
		$missing_fixtures[] = $fixture;
	}
}

if ( ! empty( $missing_fixtures ) ) {
	echo "   ❌ Missing golden fixtures:\n";
	foreach ( $missing_fixtures as $fixture ) {
		echo "      - {$fixture}\n";
	}
	echo "   Create these fixtures before running tests.\n\n";
	exit( 2 );
} else {
	echo "   ✓ All required golden fixtures present\n\n";
}

// Check 4: Verify MockLLMClient exists
echo "[4/4] Checking MockLLMClient...\n";
$mock_client_path = __DIR__ . '/MockLLMClient.php';
if ( ! file_exists( $mock_client_path ) ) {
	echo "   ❌ MockLLMClient.php not found\n";
	echo "   Expected: {$mock_client_path}\n\n";
	exit( 2 );
} else {
	echo "   ✓ MockLLMClient.php exists\n\n";
}

// Summary
echo "===============\n";
if ( $is_ci ) {
	if ( empty( $found_keys ) ) {
		echo "✓ CI environment is SAFE to run tests\n";
		echo "  All tests will use golden stubs.\n";
		exit( 0 );
	} else {
		echo "❌ CI environment is UNSAFE\n";
		echo "  Remove live API keys before running tests.\n";
		exit( 1 );
	}
} else {
	echo "ℹ️  Local environment detected\n";
	if ( $test_mode === 'ci' ) {
		echo "  Tests will use golden stubs (KH_SMMA_TEST_MODE=ci)\n";
	} else {
		echo "  Tests may use live LLM calls.\n";
		echo "  Set KH_SMMA_TEST_MODE=ci to use golden stubs.\n";
	}
	exit( 0 );
}
