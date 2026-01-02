<?php
/**
 * Membership Repository Interface
 *
 * Defines the contract for membership persistence and access control.
 * Handles user-level assignments and membership lifecycle.
 *
 * @package KHM\Contracts
 */

namespace KHM\Contracts;

interface MembershipRepositoryInterface {

    /**
     * Assign a membership level to a user.
     *
     * @param int $userId User ID
     * @param int $levelId Membership level ID
     * @param array $options Optional settings (start_date, end_date, status, etc.)
     * @return object Created membership object
     */
    public function assign( int $userId, int $levelId, array $options = [] ): object;

    /**
     * Cancel a user's membership.
     *
     * Sets status to 'cancelled' and optionally sets end_date.
     *
     * @param int $userId User ID
     * @param int $levelId Membership level ID
     * @param string $reason Optional cancellation reason
     * @return bool True if cancelled successfully
     */
    public function cancel( int $userId, int $levelId, string $reason = '' ): bool;

    /**
     * Expire a user's membership.
     *
     * Sets status to 'expired' and records expiration timestamp.
     *
     * @param int $userId User ID
     * @param int $levelId Membership level ID
     * @return bool True if expired successfully
     */
    public function expire( int $userId, int $levelId ): bool;

    /**
     * Find all active memberships for a user.
     *
     * @param int $userId User ID
     * @return array Array of membership objects
     */
    public function findActive( int $userId ): array;

    /**
     * Find all users with a specific membership level.
     *
     * @param int $levelId Membership level ID
     * @param array $filters Optional filters (status, etc.)
     * @return array Array of membership objects
     */
    public function findByLevel( int $levelId, array $filters = [] ): array;

    /**
     * Find memberships expiring within N days.
     *
     * Used for sending expiration warnings.
     *
     * @param int $days Number of days until expiration
     * @return array Array of membership objects
     */
    public function findExpiring( int $days = 7 ): array;

    /**
     * Check if a user has access to a specific membership level.
     *
     * @param int $userId User ID
     * @param int $levelId Membership level ID
     * @return bool True if user has active access
     */
    public function hasAccess( int $userId, int $levelId ): bool;

    /**
     * Update the end date for a membership.
     *
     * @param int $userId User ID
     * @param int $levelId Membership level ID
     * @param \DateTime|null $endDate New end date (null = no expiration)
     * @return bool True if updated successfully
     */
    public function updateEndDate( int $userId, int $levelId, ?\DateTime $endDate ): bool;

    /**
     * Get membership details for a user and level.
     *
     * @param int $userId User ID
     * @param int $levelId Membership level ID
     * @return object|null Membership object or null if not found
     */
    public function find( int $userId, int $levelId ): ?object;

    /**
     * Mark a membership as past due after a failed payment.
     *
     * @param int    $userId User ID.
     * @param int    $levelId Membership level ID.
     * @param string $reason Optional reason for auditing/logging.
     * @return bool True if status updated.
     */
    public function markPastDue( int $userId, int $levelId, string $reason = '' ): bool;

    /**
     * Update billing profile data on an existing membership.
     *
     * @param int   $userId User ID.
     * @param int   $levelId Membership level ID.
     * @param array $attributes Associative array of billing fields.
     * @return bool True if the membership was updated.
     */
    public function updateBillingProfile( int $userId, int $levelId, array $attributes = [] ): bool;

    /**
     * Set a specific status on a membership.
     *
     * @param int    $userId User ID.
     * @param int    $levelId Membership level ID.
     * @param string $status Status slug (e.g. active, past_due, cancelled).
     * @param string $reason Optional reason for auditing/logging.
     * @return bool True if status updated.
     */
    public function setStatus( int $userId, int $levelId, string $status, string $reason = '' ): bool;
}
