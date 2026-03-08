<?php
declare(strict_types=1);

/**
 * Build a Stripe-Signature header for tests.
 *
 * @param string   $payload   Raw request body.
 * @param string   $secret    Webhook secret.
 * @param int|null $timestamp Optional timestamp override.
 * @return string
 */
function khm_test_build_stripe_signature_header(string $payload, string $secret, ?int $timestamp = null): string {
	$timestamp = $timestamp ?? time();
	$signed_payload = $timestamp . '.' . $payload;
	$signature = hash_hmac('sha256', $signed_payload, $secret);

	return 't=' . $timestamp . ',v1=' . $signature;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	$options = getopt('', ['secret:', 'payload:', 'timestamp::']);
	$secret = isset($options['secret']) ? (string) $options['secret'] : '';
	$payloadRef = isset($options['payload']) ? (string) $options['payload'] : '';
	$timestamp = isset($options['timestamp']) ? (int) $options['timestamp'] : null;

	if ($secret === '' || $payloadRef === '') {
		fwrite(STDERR, "Usage: php tests/helpers/stripe_signature.php --secret=<webhook_secret> --payload=<json file|raw json> [--timestamp=<unix>]\n");
		exit(1);
	}

	$payload = is_file($payloadRef) ? (string) file_get_contents($payloadRef) : $payloadRef;
	if ($payload === '') {
		fwrite(STDERR, "Payload is empty.\n");
		exit(1);
	}

	echo khm_test_build_stripe_signature_header($payload, $secret, $timestamp) . PHP_EOL;
}
