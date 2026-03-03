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
