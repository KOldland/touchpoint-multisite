<?php
/**
 * Webhook Verifier Interface
 *
 * Defines the contract for webhook signature verification and event parsing.
 * Each gateway (Stripe, PayPal, Braintree) implements its own verification logic.
 *
 * @package KHM\Contracts
 */

namespace KHM\Contracts;

interface WebhookVerifierInterface {

    /**
     * Verify the webhook signature.
     *
     * Checks HMAC-SHA256, MD5, or other signature schemes depending on gateway.
     *
     * @param string $payload Raw request body
     * @param array $headers HTTP headers (e.g., Stripe-Signature, X-Braintree-Signature)
     * @param string $secret Webhook signing secret from gateway
     * @return object|false Parsed event object when valid, false when invalid
     */
    public function verify( string $payload, array $headers, string $secret );

    /**
     * Parse the webhook payload into a normalized Event object.
     *
     * @param string $payload Raw request body (JSON, form-encoded, etc.)
     * @return object Event object with type, id, and data properties
     * @throws \InvalidArgumentException If payload is malformed
     */
    public function parseEvent( string $payload ): object;

    /**
     * Get the event ID from the parsed event.
     *
     * Used for idempotency checks.
     *
     * @param object $event Parsed event object
     * @return string Event ID
     */
    public function getEventId( object $event ): string;

    /**
     * Get the event type from the parsed event.
     *
     * Examples: 'charge.succeeded', 'subscription.cancelled', 'dispute.created'
     *
     * @param object $event Parsed event object
     * @return string Event type
     */
    public function getEventType( object $event ): string;
}
