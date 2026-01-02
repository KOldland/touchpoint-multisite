<?php
/**
 * Access Control Interface
 *
 * Defines the contract for content protection and membership access checks.
 * Determines whether users can view posts, pages, and custom content.
 *
 * @package KHM\Contracts
 */

namespace KHM\Contracts;

interface AccessControlInterface {

    /**
     * Check if a user has access to a post.
     *
     * @param int $userId User ID
     * @param int $postId Post ID
     * @return bool True if user has access
     */
    public function hasAccess( int $userId, int $postId ): bool;

    /**
     * Get the membership levels required for a post.
     *
     * @param int $postId Post ID
     * @return array Array of level IDs
     */
    public function getRequiredLevels( int $postId ): array;

    /**
     * Filter post content based on access rules.
     *
     * Returns full content if user has access, otherwise returns excerpt or message.
     *
     * @param string $content Post content
     * @param int $postId Post ID
     * @param int $userId User ID
     * @return string Filtered content
     */
    public function filterContent( string $content, int $postId, int $userId ): string;

    /**
     * Get the access denied message for a post.
     *
     * @param int $postId Post ID
     * @param int $userId User ID
     * @return string Message to display to non-members
     */
    public function getAccessDeniedMessage( int $postId, int $userId ): string;

    /**
     * Set access rules for a post.
     *
     * @param int $postId Post ID
     * @param array $levelIds Array of level IDs that can access this post
     * @return bool True if rules saved successfully
     */
    public function setAccessRules( int $postId, array $levelIds ): bool;

    /**
     * Remove all access rules for a post.
     *
     * Makes the post publicly accessible.
     *
     * @param int $postId Post ID
     * @return bool True if rules removed successfully
     */
    public function removeAccessRules( int $postId ): bool;
}
