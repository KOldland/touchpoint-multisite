<?php
/**
 * Idempotency Store Interface
 *
 * Tracks processed webhook events to prevent duplicate processing.
 * Implementations can use database, Redis, or other storage backends.
 *
 * @package KHM\Contracts
 */

namespace KHM\Contracts;

interface IdempotencyStoreInterface {

    /**
     * Check if an event has already been processed.
     *
     * @param string $eventId Unique event identifier from gateway
     * @return bool True if event was already processed
     */
    public function hasProcessed( string $eventId ): bool;

    /**
     * Mark an event as processed.
     *
     * Stores event ID, timestamp, gateway name, and optional metadata.
     *
     * @param string $eventId Unique event identifier
     * @param string $gateway Gateway name (stripe, paypal, etc.)
     * @param array $metadata Optional metadata (event type, order ID, etc.)
     * @return void
     */
    public function markProcessed( string $eventId, string $gateway, array $metadata = [] ): void;

    /**
     * Retrieve details of a processed event.
     *
     * Returns event record with timestamp and metadata, or null if not found.
     *
     * @param string $eventId Event identifier
     * @return array|null Event record or null
     */
    public function getProcessedEvent( string $eventId ): ?array;

    /**
     * Clean up old processed event records.
     *
     * Deletes records older than the specified number of days.
     * Recommended to run this periodically (e.g., daily cron job).
     *
     * @param int $daysOld Delete records older than this many days
     * @return int Number of records deleted
     */
    public function cleanup( int $daysOld = 90 ): int;
}
