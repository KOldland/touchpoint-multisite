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
            'permission_callback' => '__return_true', // Or a permission check for logged in users
            'args' => [
                'user_id' => [
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
    }

    public function handle_request( \WP_REST_Request $req ) {
        $user_id = $req->get_param('user_id');

        if ( empty($user_id) ) {
            $user_id = get_current_user_id();
        }

        if ( empty($user_id) ) {
            return new \WP_REST_Response(['error'=>'user_id is required'], 400);
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $membership_tier_table = $wpdb->prefix . 'membership_tier';

        $query = $wpdb->prepare(
            "SELECT um.status, um.trial_ends_at, mt.name as tier_name, mt.benefits
             FROM $user_membership_table um
             LEFT JOIN $membership_tier_table mt ON um.tier_id = mt.id
             WHERE um.user_id = %d",
            $user_id
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if ( !$result ) {
            return new \WP_REST_Response(['status' => 'none'], 200);
        }

        $result['benefits'] = json_decode($result['benefits'] ?? '[]', true);

        return rest_ensure_response($result);
    }
}
