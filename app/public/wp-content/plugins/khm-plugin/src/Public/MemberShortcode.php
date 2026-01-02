<?php

namespace KHM\PublicFrontend;

use KHM\Services\AccessControlService;

/**
 * MemberShortcode
 *
 * Provides [khm_member] shortcode for conditional content display.
 * Shows content only to users with active memberships or specific levels.
 */
class MemberShortcode {

    private AccessControlService $access_control;

    public function __construct( AccessControlService $access_control ) {
        $this->access_control = $access_control;
    }

    /**
     * Register shortcode and hooks
     */
    public function register(): void {
        add_shortcode('khm_member', [ $this, 'render' ]);
        add_shortcode('khm_nonmember', [ $this, 'render_nonmember' ]);
    }

    /**
     * Render member-only content
     *
     * Usage:
     *   [khm_member]This content is for all members[/khm_member]
     *   [khm_member levels="1,2,3"]This is for specific levels[/khm_member]
     *   [khm_member levels="1" display="hide"]Hide from level 1[/khm_member]
     *
     * @param array $atts Shortcode attributes
     * @param string|null $content Enclosed content
     * @return string
     */
    public function render( array $atts = [], ?string $content = null ): string {
        if ( $content === null ) {
            return '';
        }

        $atts = shortcode_atts([
            'levels' => '',        // Comma-separated level IDs
            'display' => 'show',   // 'show' or 'hide'
            'delay' => '',         // Days delay after signup (e.g., "7")
        ], $atts, 'khm_member');

        $user_id = get_current_user_id();

        // Parse required levels
        $required_levels = [];
        if ( ! empty($atts['levels']) ) {
            $required_levels = array_map('intval', array_filter(explode(',', $atts['levels'])));
        }

        // Check access
        $has_access = false;

        if ( empty($required_levels) ) {
            // No specific levels - just check if user has any active membership
            $has_access = $this->user_has_any_membership($user_id);
        } else {
            // Check specific levels
            $has_access = $this->access_control->has_access($user_id, null, [
                'required_levels' => $required_levels,
            ]);
        }

        // Apply delay logic if specified
        if ( $has_access && ! empty($atts['delay']) ) {
            $delay_days = intval($atts['delay']);
            $has_access = $this->check_delay($user_id, $required_levels, $delay_days);
        }

        // Invert logic if display="hide"
        if ( $atts['display'] === 'hide' ) {
            $has_access = ! $has_access;
        }

        // Allow filter to override
        $has_access = apply_filters('khm_member_shortcode_access', $has_access, $atts, $user_id);

        if ( ! $has_access ) {
            return '';
        }

        return do_shortcode($content);
    }

    /**
     * Render non-member content (inverse of khm_member)
     *
     * Usage:
     *   [khm_nonmember]Sign up to see premium content![/khm_nonmember]
     *
     * @param array $atts
     * @param string|null $content
     * @return string
     */
    public function render_nonmember( array $atts = [], ?string $content = null ): string {
        if ( $content === null ) {
            return '';
        }

        $user_id = get_current_user_id();
        $has_membership = $this->user_has_any_membership($user_id);

        // Allow filter to override
        $show_content = apply_filters('khm_nonmember_shortcode_show', ! $has_membership, $atts, $user_id);

        if ( ! $show_content ) {
            return '';
        }

        return do_shortcode($content);
    }

    /**
     * Check if user has any active membership
     *
     * @param int $user_id
     * @return bool
     */
    private function user_has_any_membership( int $user_id ): bool {
        if ( $user_id === 0 ) {
            return false;
        }

        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}khm_memberships_users 
             WHERE user_id = %d 
             AND status = 'active' 
             AND (end_date IS NULL OR end_date > NOW())",
            $user_id
        ));

        return $count > 0;
    }

    /**
     * Check if user has had membership for minimum delay period
     *
     * @param int $user_id
     * @param array $level_ids
     * @param int $delay_days
     * @return bool
     */
    private function check_delay( int $user_id, array $level_ids, int $delay_days ): bool {
        if ( $delay_days <= 0 ) {
            return true;
        }

        global $wpdb;

		$threshold_date = gmdate('Y-m-d H:i:s', strtotime("-{$delay_days} days"));

        if ( empty($level_ids) ) {
            // Check any membership
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}khm_memberships_users 
                 WHERE user_id = %d 
                 AND status = 'active'
                 AND start_date <= %s",
                $user_id,
                $threshold_date
            ));
        } else {
            // Check specific levels
            $placeholders = implode(',', array_fill(0, count($level_ids), '%d'));
            /* phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare */
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}khm_memberships_users 
                 WHERE user_id = %d 
                 AND membership_id IN ($placeholders)
                 AND status = 'active'
                 AND start_date <= %s",
                array_merge([ $user_id ], $level_ids, [ $threshold_date ])
            );
            /* phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare */

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared above before execution.
            $count = $wpdb->get_var($query);
        }

        return $count > 0;
    }
}
