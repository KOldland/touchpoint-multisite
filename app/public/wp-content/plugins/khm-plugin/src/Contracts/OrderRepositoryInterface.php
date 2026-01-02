<?php
/**
 * Order Repository Interface
 *
 * Defines the contract for order persistence and retrieval.
 * Handles CRUD operations for membership orders.
 *
 * @package KHM\Contracts
 */

namespace KHM\Contracts;

interface OrderRepositoryInterface {

    /**
     * Create a new order.
     *
     * @param array $data Order data (user_id, membership_id, total, gateway, etc.)
     * @return object Created order object with ID
     */
    public function create( array $data ): object;

    /**
     * Update an existing order.
     *
     * @param int $orderId Order ID
     * @param array $data Fields to update
     * @return object Updated order object
     */
    public function update( int $orderId, array $data ): object;

    /**
     * Find an order by ID.
     *
     * @param int $orderId Order ID
     * @return object|null Order object or null if not found
     */
    public function find( int $orderId ): ?object;

    /**
     * Find an order by public code.
     *
     * @param string $code Order code (public identifier)
     * @return object|null Order object or null if not found
     */
    public function findByCode( string $code ): ?object;

    /**
     * Find an order by payment transaction ID.
     *
     * @param string $txnId Payment transaction ID from gateway
     * @return object|null Order object or null if not found
     */
    public function findByPaymentTransactionId( string $txnId ): ?object;

    /**
     * Find the most recent order for a subscription.
     *
     * @param string $subscriptionId Subscription transaction ID from gateway
     * @return object|null Order object or null if not found
     */
	public function findLastBySubscriptionId( string $subscriptionId ): ?object;

	/**
	 * Find orders for a user.
	 *
     * @param int $userId User ID
	 * @param array $filters Optional filters (status, gateway, membership_id, etc.)
	 * @return array Array of order objects
	 */
	public function findByUser( int $userId, array $filters = [] ): array;

	/**
	 * Retrieve an order with related user and membership details.
	 *
	 * @param int $orderId Order ID.
	 * @return array<string,mixed>|null
	 */
	public function getWithRelations( int $orderId ): ?array;

	/**
	 * Retrieve multiple orders with related user and membership details.
	 *
	 * @param array<int> $ids Order IDs.
	 * @return array<array<string,mixed>>
	 */
	public function getManyWithRelations( array $ids ): array;

	/**
	 * Paginate orders for admin listings.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items: array<array<string,mixed>>, total: int}
	 */
	public function paginate( array $args = [] ): array;

	/**
	 * Update order status.
	 *
	 * @param int $orderId Order ID
	 * @param string $status New status (success, pending, cancelled, refunded, etc.)
	 * @param string $notes Optional notes for the status change
	 * @return bool True if updated successfully
	 */
	public function updateStatus( int $orderId, string $status, string $notes = '' ): bool;

	/**
	 * Update order notes content.
	 *
	 * @param int    $orderId Order ID.
	 * @param string $notes   Notes content.
	 * @return bool True if updated.
	 */
	public function updateNotes( int $orderId, string $notes ): bool;

	/**
	 * Record refund metadata (amount, reason) for an order.
	 *
	 * @param int         $orderId    Order ID.
	 * @param float       $amount     Refund amount.
	 * @param string      $reason     Refund reason.
	 * @param string|null $refundedAt Optional timestamp override.
	 * @return bool True if recorded successfully.
	 */
	public function recordRefund( int $orderId, float $amount, string $reason = '', ?string $refundedAt = null ): bool;

	/**
	 * Delete an order.
	 *
	 * Soft delete recommended (set status to 'deleted' instead of removing row).
	 *
     * @param int $orderId Order ID
     * @return bool True if deleted successfully
     */
    public function delete( int $orderId ): bool;

    /**
     * Generate a unique order code.
     *
     * @return string Random alphanumeric code
     */
    public function generateCode(): string;

    /**
     * Calculate tax for an order.
     *
     * @param object $order Order object with subtotal and billing details
     * @return float Tax amount
     */
    public function calculateTax( object $order ): float;
}
