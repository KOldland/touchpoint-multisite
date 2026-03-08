<?php
declare(strict_types=1);

/**
 * Regenerate a canonical golden fixture + metadata sidecar.
 *
 * Usage:
 *   php scripts/regenerate_fixture.php \
 *     --input /path/to/recorded.json \
 *     --fixture-name generate_awareness_ok.json \
 *     --author @ci-qa-team \
 *     --prompt-version cic-01
 */

const GOLDEN_DIR = __DIR__ . '/../app/public/wp-content/plugins/kh-smma/tests/fixtures/golden';

main($argv);

function main(array $argv): void {
	$options = parse_options($argv);
	$input = $options['input'] ?? '';
	$fixture_name = $options['fixture-name'] ?? '';
	$author = $options['author'] ?? '@ci-qa-team';
	$prompt_version = $options['prompt-version'] ?? 'cic-01';
	$notes = $options['notes'] ?? 'Regenerated via scripts/regenerate_fixture.php';

	if ($input === '' || $fixture_name === '') {
		fail("Missing required args. Use --input and --fixture-name.");
	}

	if (!preg_match('/^[a-z0-9][a-z0-9_.-]*\.json$/', $fixture_name)) {
		fail('Fixture name must be lowercase and end with .json');
	}

	if (!is_file($input)) {
		fail("Input file not found: {$input}");
	}

	$raw = file_get_contents($input);
	if ($raw === false) {
		fail("Unable to read input file: {$input}");
	}

	assert_no_secrets($raw);

	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		fail('Input JSON must decode to an object/array.');
	}

	$normalized = normalize_payload($decoded);
	$json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		fail('Failed to encode normalized fixture JSON.');
	}
	$json .= PHP_EOL;

	$fixture_path = GOLDEN_DIR . '/' . $fixture_name;
	$meta_path = GOLDEN_DIR . '/' . substr($fixture_name, 0, -5) . '.meta.json';

	if (!is_dir(GOLDEN_DIR) && !mkdir(GOLDEN_DIR, 0775, true) && !is_dir(GOLDEN_DIR)) {
		fail('Failed to create golden fixture directory.');
	}

	if (file_put_contents($fixture_path, $json) === false) {
		fail("Failed to write fixture file: {$fixture_path}");
	}

	$checksum = hash_file('sha256', $fixture_path);
	if ($checksum === false) {
		fail("Failed to compute checksum for fixture: {$fixture_path}");
	}

	$meta = array(
		'version' => '1.0.0',
		'prompt_hash' => hash('sha256', $prompt_version . '|' . $fixture_name),
		'prompt_version' => $prompt_version,
		'created_at' => gmdate('c'),
		'author' => $author,
		'checksum' => $checksum,
		'notes' => $notes,
	);

	$meta_json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if ($meta_json === false) {
		fail('Failed to encode metadata JSON.');
	}
	$meta_json .= PHP_EOL;

	if (file_put_contents($meta_path, $meta_json) === false) {
		fail("Failed to write metadata file: {$meta_path}");
	}

	echo "Fixture regenerated:\n";
	echo "  - {$fixture_path}\n";
	echo "  - {$meta_path}\n\n";
	echo "Next steps:\n";
	echo "1. Review fixture + metadata for placeholders and compliance notes.\n";
	echo "2. Open PR and include reason/impact of the fixture change.\n";
	echo "3. Add label: golden-owner-approved\n";
	echo "4. Commit manually (this script never commits).\n";
}

/**
 * @return array<string,string>
 */
function parse_options(array $argv): array {
	$options = array();
	for ($i = 1; $i < count($argv); $i++) {
		$arg = (string) $argv[$i];
		if (strpos($arg, '--') !== 0) {
			continue;
		}
		$parts = explode('=', substr($arg, 2), 2);
		$key = $parts[0];
		if (count($parts) === 2) {
			$options[$key] = $parts[1];
			continue;
		}

		$next = $argv[$i + 1] ?? '';
		if (is_string($next) && strpos($next, '--') !== 0) {
			$options[$key] = $next;
			$i++;
		} else {
			$options[$key] = '1';
		}
	}
	return $options;
}

/**
 * @param mixed $value
 * @return mixed
 */
function normalize_payload($value) {
	if (is_array($value)) {
		$out = array();
		foreach ($value as $key => $item) {
			$key_str = is_string($key) ? $key : (string) $key;
			if (preg_match('/(created|updated|timestamp|time)$/i', $key_str)) {
				$out[$key] = '{{UNIX_TS}}';
				continue;
			}
			if (preg_match('/(^id$|_id$)/i', $key_str) && is_scalar($item)) {
				$out[$key] = '{{' . strtoupper(str_replace(array('-', ' '), '_', $key_str)) . '}}';
				continue;
			}
			$out[$key] = normalize_payload($item);
		}
		return $out;
	}

	if (is_string($value)) {
		if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $value)) {
			return '{{ISO8601}}';
		}
		$value = preg_replace('/\b(cs|evt|in|sub|cus|price|pi|pm|tok|adset|cmp|asset|ad)_[A-Za-z0-9_]+\b/', '{{ID_TOKEN}}', $value);
	}

	return $value;
}

function assert_no_secrets(string $content): void {
	$patterns = array(
		'/sk_(live|test)_[A-Za-z0-9]+/',
		'/whsec_[A-Za-z0-9]+/',
		'/-----BEGIN (RSA|EC|OPENSSH|PRIVATE) KEY-----/',
		'/\bAKIA[0-9A-Z]{16}\b/',
		'/\bBearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
	);

	foreach ($patterns as $pattern) {
		if (preg_match($pattern, $content) === 1) {
			fail('Input appears to contain secrets. Scrub the payload before regenerating fixture.');
		}
	}
}

function fail(string $message): void {
	fwrite(STDERR, "ERROR: {$message}\n");
	exit(1);
}
