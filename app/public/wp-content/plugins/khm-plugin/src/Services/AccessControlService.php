<?php

namespace KHM\Services;

use KHM\Contracts\AccessControlInterface;
use KHM\Contracts\MembershipRepositoryInterface;

/**
 * AccessControlService
 *
 * Manages content protection and access control based on membership levels.
 * Provides has_access API for checking user permissions on posts, pages, and custom resources.
 */
class AccessControlService implements AccessControlInterface {

    private MembershipRepositoryInterface $membership_repo;
    private LevelRepository $level_repo;

    public function __construct( MembershipRepositoryInterface $membership_repo, ?LevelRepository $level_repo = null ) {
        $this->membership_repo = $membership_repo;
        $this->level_repo = $level_repo ?: new LevelRepository();
    }

    // Interface compatibility wrappers (camelCase contract → snake_case internals)
    public function hasAccess( int $userId, int $postId ): bool {
        return $this->has_access($userId, $postId);
    }

    public function getRequiredLevels( int $postId ): array {
        return $this->get_required_levels($postId, []);
    }

    public function filterContent( string $content, int $postId, int $userId ): string {
        return $this->filter_content($content, $postId, $userId);
    }

    public function getAccessDeniedMessage( int $postId, int $userId ): string {
        $levels = $this->get_required_levels($postId, []);
        return $this->get_access_denied_message($postId, $levels);
    }

    public function setAccessRules( int $postId, array $levelIds ): bool {
        return $this->protect_post($postId, $levelIds);
    }

    public function removeAccessRules( int $postId ): bool {
        return $this->protect_post($postId, []);
    }

    /**
     * Check if a user has access to a resource
     *
     * @param int $user_id User ID (0 for current user)
     * @param int|string $resource Post/page ID or custom resource identifier
     * @param array $options Additional context (post_type, required_levels, etc.)
     * @return bool
     */
    public function has_access( int $user_id = 0, $resource = null, array $options = [] ): bool {
        // Default to current user
        if ( $user_id === 0 ) {
            $user_id = get_current_user_id();
        }

        // Allow filter to override access check
        $pre_check = apply_filters('khm_pre_has_access', null, $user_id, $resource, $options);
        if ( $pre_check !== null ) {
            return (bool) $pre_check;
        }

        // If no resource specified and no required levels in options, allow access
        if ( $resource === null && empty($options['required_levels']) ) {
            return true;
        }

        // Get required levels for this resource
        $required_levels = $this->get_required_levels($resource, $options);

        // If no levels required, allow access
        if ( empty($required_levels) ) {
            $has_access = true;
        } else {
            // Check if user has any of the required levels
            $has_access = $this->user_has_any_level($user_id, $required_levels);
        }

        // Allow filter to modify final access decision
        return apply_filters('khm_has_access', $has_access, $user_id, $resource, $options, $required_levels);
    }

    /**
     * Get required membership levels for a resource
     *
     * @param int|string $resource
     * @param array $options
     * @return array Array of level IDs
     */
    private function get_required_levels( $resource, array $options ): array {
        // If required_levels explicitly passed in options, use those
        if ( ! empty($options['required_levels']) ) {
            return (array) $options['required_levels'];
        }

        // If resource is numeric, treat as post/page ID
        if ( is_numeric($resource) ) {
            $post_id = (int) $resource;
            $levels = get_post_meta($post_id, '_khm_required_levels', true);

            if ( ! empty($levels) && is_array($levels) ) {
                return array_map('intval', $levels);
            }
        }

        // Allow filter to define required levels for custom resources
        $levels = apply_filters('khm_get_required_levels', [], $resource, $options);

        return is_array($levels) ? $levels : [];
    }

    /**
     * Check if user has any of the specified membership levels
     *
     * @param int $user_id
     * @param array $level_ids
     * @return bool
     */
    private function user_has_any_level( int $user_id, array $level_ids ): bool {
        if ( empty($level_ids) ) {
            return false;
        }

        foreach ( $level_ids as $level_id ) {
            if ( $this->membership_repo->hasAccess($user_id, (int) $level_id) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Protect a post/page by setting required membership levels
     *
     * @param int $post_id
     * @param array $level_ids Array of membership level IDs
     * @return bool Success
     */
    public function protect_post( int $post_id, array $level_ids ): bool {
        if ( empty($level_ids) ) {
            // Remove protection
            return delete_post_meta($post_id, '_khm_required_levels');
        }

        $level_ids = array_map('intval', $level_ids);
        return update_post_meta($post_id, '_khm_required_levels', $level_ids) !== false;
    }

    /**
     * Get all protected posts
     *
     * @param array $args WP_Query args to customize the query
     * @return array Array of post IDs
     */
    public function get_protected_posts( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];

        $args = array_merge($defaults, $args, [
            'meta_key' => '_khm_required_levels',
            'fields' => 'ids',
        ]);

        $query = new \WP_Query($args);
        return $query->posts;
    }

    /**
     * Filter content based on user access
     *
     * @param string $content Post content
     * @param int $post_id
     * @param int $user_id
     * @return string Filtered content or access denied message
     */
    public function filter_content( string $content, int $post_id, int $user_id = 0 ): string {
        if ( $user_id === 0 ) {
            $user_id = get_current_user_id();
        }

        // Check if post is protected
        $required_levels = $this->get_required_levels($post_id, []);

        if ( empty($required_levels) ) {
            // Not protected
            return $content;
        }

        // Check access
        if ( $this->has_access($user_id, $post_id) ) {
            return $content;
        }

        // No access - return denial message
        return $this->get_access_denied_message($post_id, $required_levels);
    }

    /**
     * Get access denied message with available membership levels
     *
     * @param int $post_id
     * @param array $required_levels
     * @return string HTML message
     */
    private function get_access_denied_message( int $post_id, array $required_levels ): string {
        $message = '<div class="khm-access-denied">';
        $message .= '<p>' . esc_html__('This content is restricted to members.', 'khm-membership') . '</p>';

        if ( ! is_user_logged_in() ) {
            $message .= '<p><a href="' . esc_url(wp_login_url(get_permalink($post_id))) . '">'
                    . esc_html__('Please log in to view this content.', 'khm-membership') . '</a></p>';
        } else {
            $message .= '<p>' . esc_html__('You need an active membership to view this content.', 'khm-membership') . '</p>';

            // Show available membership levels
            if ( ! empty($required_levels) ) {
                $message .= '<ul class="khm-required-levels">';
                foreach ( $required_levels as $level_id ) {
                    $level = $this->get_level_info($level_id);
                    if ( $level ) {
                        $checkout_url = add_query_arg('level_id', $level_id, home_url('/checkout/'));
                        $message .= '<li><a href="' . esc_url($checkout_url) . '">'
                                . esc_html($level->name) . '</a></li>';
                    }
                }
                $message .= '</ul>';
            }
        }

        $message .= '</div>';

        return apply_filters('khm_access_denied_message', $message, $post_id, $required_levels);
    }

    /**
     * Get membership level info
     *
     * @param int $level_id
     * @return object|null
     */
    private function get_level_info( int $level_id ) {
        return $this->level_repo->get($level_id, true);
    }

    /**
     * Check if current query should be filtered
     * Used by WordPress the_content filter
     *
     * @return bool
     */
    public function should_filter_content(): bool {
        // Don't filter in admin, feeds, or search results
        if ( is_admin() || is_feed() || is_search() ) {
            return false;
        }

        // Don't filter if user can manage KHM settings (admin override)
        if ( current_user_can('manage_khm') ) {
            return false;
        }

        return apply_filters('khm_should_filter_content', true);
    }
}
