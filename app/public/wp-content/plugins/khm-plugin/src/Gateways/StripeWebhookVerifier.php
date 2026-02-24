<?php
/**
 * Stripe Webhook Verifier
 *
 * Verifies Stripe webhook signatures and parses events.
 *
 * @package KHM\Gateways
 */

namespace KHM\Gateways;

use KHM\Contracts\WebhookVerifierInterface;

class StripeWebhookVerifier implements WebhookVerifierInterface {

    /**
     * Verify the webhook signature.
     */
    public function verify( string $payload, array $headers, string $secret ) {
        $secret = trim( $secret );
        if ( $secret === '' ) {
            error_log( 'Stripe webhook: Empty signing secret' );
            return false;
        }

        try {
            // Load Stripe library
            if ( ! class_exists('\Stripe\Stripe') ) {
                require_once dirname(__DIR__, 2) . '/vendor/stripe/stripe-php/init.php';
            }

            // Get signature from headers (normalize to handle different server keys)
            $signature = $this->extractSignature($headers);

            if ( empty($signature) ) {
                error_log('Stripe webhook: No signature header found');
                return false;
            }

            // Verify signature and return parsed event.
            return \Stripe\Webhook::constructEvent($payload, $signature, $secret);

        } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
            error_log('Stripe webhook signature verification failed: ' . $e->getMessage());
            return $this->verifySignatureManually( $payload, $headers, $secret );
        } catch ( \Exception $e ) {
            error_log('Stripe webhook verification error: ' . $e->getMessage());
            return $this->verifySignatureManually( $payload, $headers, $secret );
        }
    }

    /**
     * Parse the webhook payload into a normalized Event object.
     *
     * @throws \InvalidArgumentException When the payload JSON is invalid or required fields are missing.
     */
    public function parseEvent( $payload ): object {
        if ( is_object( $payload ) ) {
            return $payload;
        }

        $data = json_decode($payload);

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new \InvalidArgumentException('Invalid JSON payload');
        }

        if ( ! isset($data->id) || ! isset($data->type) ) {
            throw new \InvalidArgumentException('Missing required event fields (id, type)');
        }

        return $data;
    }

    /**
     * Get the event ID from the parsed event.
     */
    public function getEventId( object $event ): string {
        return $event->id ?? '';
    }

    /**
     * Get the event type from the parsed event.
     */
    public function getEventType( object $event ): string {
        return $event->type ?? '';
    }

    /**
     * Normalize and extract Stripe signature header.
     *
     * @param array $headers
     * @return string
     */
    private function extractSignature(array $headers): string {
        // Lowercase keys for case-insensitive lookup.
        $lower = [];
        foreach ($headers as $key => $value) {
            $lower[strtolower($key)] = $value;
        }

        // Common keys.
        if (!empty($lower['stripe-signature'])) {
            return $this->normalizeHeaderValue($lower['stripe-signature']);
        }
        if (!empty($lower['stripe_signature'])) {
            return $this->normalizeHeaderValue($lower['stripe_signature']);
        }
        if (!empty($lower['http_stripe_signature'])) {
            return $this->normalizeHeaderValue($lower['http_stripe_signature']);
        }
        if (!empty($lower['http-stripe-signature'])) {
            return $this->normalizeHeaderValue($lower['http-stripe-signature']);
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private function normalizeHeaderValue($value): string {
        // WP_REST_Request headers may be arrays. Stripe expects a raw string.
        if (is_array($value)) {
            foreach ($value as $candidate) {
                if (is_string($candidate) && $candidate !== '') {
                    return trim($candidate);
                }
            }
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        return '';
    }

    /**
     * Manual Stripe signature verification fallback.
     *
     * @return object|false
     */
    private function verifySignatureManually( string $payload, array $headers, string $secret ) {
        $signature = $this->extractSignature( $headers );
        if ( $signature === '' ) {
            return false;
        }

        $parts = array();
        foreach ( explode( ',', $signature ) as $segment ) {
            $pair = explode( '=', trim( $segment ), 2 );
            if ( count( $pair ) === 2 ) {
                $parts[ $pair[0] ][] = $pair[1];
            }
        }

        $timestamp = isset( $parts['t'][0] ) ? trim( (string) $parts['t'][0] ) : '';
        $v1Sigs    = isset( $parts['v1'] ) && is_array( $parts['v1'] ) ? $parts['v1'] : array();
        if ( $timestamp === '' || empty( $v1Sigs ) ) {
            return false;
        }

        // Keep Stripe's default 5-minute tolerance.
        if ( abs( time() - (int) $timestamp ) > 300 ) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected      = hash_hmac( 'sha256', $signedPayload, $secret );
        foreach ( $v1Sigs as $candidate ) {
            if ( is_string( $candidate ) && hash_equals( $expected, trim( $candidate ) ) ) {
                return $this->parseEvent( $payload );
            }
        }

        return false;
    }
}
