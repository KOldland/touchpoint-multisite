<?php

namespace KHM\Membership;

class StatusEndpoint {
    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route('kh-membership/v1', '/status', [
            'methods' => 'GET',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
    }

    public function check_permission( \WP_REST_Request $req ) {
        $requested_user_id = $req->get_param('user_id');
        $current_user_id = get_current_user_id();

        // User must be authenticated
        if ( empty($current_user_id) ) {
            return new \WP_Error(
                'rest_forbidden',
                __('Authentication required.', 'khm'),
                [ 'status' => 401 ]
            );
        }

        // User can only access their own membership status
        // (unless they're an admin)
        if ( $requested_user_id != $current_user_id && !current_user_can('manage_options') ) {
            return new \WP_Error(
                'rest_forbidden',
                __('You can only access your own membership status.', 'khm'),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    public function handle_request( \WP_REST_Request $req ) {
        $user_id = $req->get_param('user_id');

        if ( empty($user_id) ) {
            return new \WP_REST_Response(['error' => 'user_id is required'], 400);
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $membership_tier_table = $wpdb->prefix . 'membership_tier';

        $query = $wpdb->prepare(
            "SELECT um.user_id, um.tier_id, um.status, um.trial_ends_at, um.started_at,
                    um.cancelled_at, mt.slug as tier_slug, mt.name as tier_name
             FROM $user_membership_table um
             LEFT JOIN $membership_tier_table mt ON um.tier_id = mt.id
             WHERE um.user_id = %d",
            $user_id
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if ( !$result ) {
            return rest_ensure_response([
                'user_id' => (int) $user_id,
                'tier' => null,
                'status' => 'none',
                'trial_ends_at' => null,
                'started_at' => null,
                'cancelled_at' => null,
                'renews_at' => null
            ]);
        }

        // Calculate renews_at (approximation - would need Stripe subscription data for exact value)
        $renews_at = null;
        if ($result['status'] === 'active' || $result['status'] === 'trialing') {
            // If trial, renews_at is trial_ends_at
            if ($result['status'] === 'trialing' && !empty($result['trial_ends_at'])) {
                $renews_at = $result['trial_ends_at'];
            } elseif (!empty($result['started_at'])) {
                // Approximate monthly renewal from started_at
                // In production, this should come from Stripe subscription
                $renews_at = gmdate('Y-m-d\TH:i:s\Z', strtotime($result['started_at'] . ' +1 month'));
            }
        }

        $response = [
            'user_id' => (int) $result['user_id'],
            'tier' => [
                'id' => (int) $result['tier_id'],
                'slug' => $result['tier_slug'],
                'name' => $result['tier_name']
            ],
            'status' => $result['status'],
            'trial_ends_at' => $result['trial_ends_at'] ? gmdate('c', strtotime($result['trial_ends_at'])) : null,
            'started_at' => $result['started_at'] ? gmdate('c', strtotime($result['started_at'])) : null,
            'cancelled_at' => $result['cancelled_at'] ? gmdate('c', strtotime($result['cancelled_at'])) : null,
            'renews_at' => $renews_at ? gmdate('c', strtotime($renews_at)) : null
        ];

        return rest_ensure_response($response);
    }
}
