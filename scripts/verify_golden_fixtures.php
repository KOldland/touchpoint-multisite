<?php
declare(strict_types=1);

const GOLDEN_DIR = __DIR__ . '/../app/public/wp-content/plugins/kh-smma/tests/fixtures/golden';

$required = array(
	'generate_awareness_ok.json',
	'generate_sponsor_warn.json',
	'generate_sponsor_fail.json',
	'google_ad_draft.json',
	'compliance_ok.json',
	'compliance_warn.json',
	'compliance_fail.json',
	'checkout_session_completed.json',
	'invoice_paid.json',
	'checkout_session_no_consent.json',
	'paid_adapter_dry_run_manifest.json',
	'paid_adapter_execute_response.json',
);

$errors = array();

foreach ($required as $fixture) {
	$fixture_path = GOLDEN_DIR . '/' . $fixture;
	$meta_path = GOLDEN_DIR . '/' . substr($fixture, 0, -5) . '.meta.json';

	if (!is_file($fixture_path)) {
		$errors[] = "Missing fixture: {$fixture}";
		continue;
	}
	if (!is_file($meta_path)) {
		$errors[] = "Missing metadata sidecar: " . basename($meta_path);
		continue;
	}

	$content = file_get_contents($fixture_path);
	if ($content === false) {
		$errors[] = "Unable to read fixture: {$fixture}";
		continue;
	}

	if (json_decode($content, true) === null && json_last_error() !== JSON_ERROR_NONE) {
		$errors[] = "Invalid JSON fixture: {$fixture}";
		continue;
	}

	if (has_secret_pattern($content)) {
		$errors[] = "Secret-like pattern detected in fixture: {$fixture}";
	}

	$meta_raw = file_get_contents($meta_path);
	if ($meta_raw === false) {
		$errors[] = "Unable to read metadata: " . basename($meta_path);
		continue;
	}
	$meta = json_decode($meta_raw, true);
	if (!is_array($meta)) {
		$errors[] = "Invalid metadata JSON: " . basename($meta_path);
		continue;
	}

	$required_meta_fields = array('version', 'prompt_hash', 'prompt_version', 'created_at', 'author', 'checksum');
	foreach ($required_meta_fields as $field) {
		if (!array_key_exists($field, $meta) || $meta[$field] === '') {
			$errors[] = basename($meta_path) . " missing field: {$field}";
		}
	}

	if (isset($meta['author']) && is_string($meta['author']) && strpos($meta['author'], '@') !== 0) {
		$errors[] = basename($meta_path) . ' author must be a GitHub handle starting with @';
	}

	$actual_checksum = hash_file('sha256', $fixture_path);
	if ($actual_checksum === false) {
		$errors[] = "Could not hash fixture: {$fixture}";
		continue;
	}

	if (($meta['checksum'] ?? '') !== $actual_checksum) {
		$errors[] = basename($meta_path) . ' checksum mismatch'
			. " (expected {$meta['checksum']}, got {$actual_checksum})";
	}
}

if (!empty($errors)) {
	fwrite(STDERR, "Golden fixture verification failed:\n");
	foreach ($errors as $error) {
		fwrite(STDERR, " - {$error}\n");
	}
	exit(1);
}

fwrite(STDOUT, "Golden fixture verification passed.\n");

function has_secret_pattern(string $content): bool {
	$patterns = array(
		'/sk_(live|test)_[A-Za-z0-9]+/',
		'/whsec_[A-Za-z0-9]+/',
		'/-----BEGIN (RSA|EC|OPENSSH|PRIVATE) KEY-----/',
		'/\bAKIA[0-9A-Z]{16}\b/',
		'/\bBearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
	);
	foreach ($patterns as $pattern) {
		if (preg_match($pattern, $content) === 1) {
			return true;
		}
	}
	return false;
}
