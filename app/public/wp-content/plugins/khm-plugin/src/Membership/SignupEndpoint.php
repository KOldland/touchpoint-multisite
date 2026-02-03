<?php

namespace KHM\Membership;

class SignupEndpoint {
    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route('kh-membership/v1', '/signup', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => '__return_true'
        ]);
    }

    public function handle_request( \WP_REST_Request $req ) {
        $p = $req->get_json_params();

        // --- Validation ---
        $email = sanitize_email($p['email'] ?? '');
        if ( !is_email($email) ) {
            return new \WP_REST_Response(['error'=>'invalid email'], 400);
        }

        $plan_id = isset($p['plan_id']) ? intval($p['plan_id']) : 0;
        if ( empty($plan_id) ) {
            return new \WP_REST_Response(['error'=>'invalid plan_id'], 400);
        }

        // --- User Creation or Retrieval ---
        $user_id = email_exists($email);
        if ( !$user_id ) {
            // Create a new user
            $password = wp_generate_password();
            $user_id = wp_create_user($email, $password, $email);
            if ( is_wp_error($user_id) ) {
                return new \WP_REST_Response(['error'=>'could not create user', 'details' => $user_id->get_error_message()], 500);
            }
            // Optional: send new user notification
            // wp_send_new_user_notifications($user_id, 'user');
        }

        // --- Mock Stripe Integration ---
        $stripe_customer_id = 'cus_' . uniqid();
        $stripe_subscription_id = 'sub_' . uniqid();

        // --- Create User Membership ---
        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        $wpdb->replace($user_membership_table, [
            'user_id' => $user_id,
            'tier_id' => $plan_id,
            'stripe_customer_id' => $stripe_customer_id,
            'stripe_subscription_id' => $stripe_subscription_id,
            'status' => 'trialing', // or active, depending on plan
            'started_at' => current_time('mysql', 1),
        ]);


        // --- Promotion Attribution ---
        $attribution_data = [
            'conversion_type' => 'signup',
            'schedule_id' => isset($p['schedule_id']) ? intval($p['schedule_id']) : null,
            'sponsor_id' => isset($p['sponsor_id']) ? intval($p['sponsor_id']) : null,
            'user_id' => $user_id,
            'user_email' => $email,
            'utm_source' => sanitize_text_field($p['utm_source'] ?? ''),
            'utm_medium' => sanitize_text_field($p['utm_medium'] ?? ''),
            'utm_campaign' => sanitize_text_field($p['utm_campaign'] ?? ''),
            'utm_term' => sanitize_text_field($p['utm_term'] ?? ''),
            'utm_content' => sanitize_text_field($p['utm_content'] ?? ''),
            'phase_at_click' => sanitize_text_field($p['phase_at_click'] ?? ''),
            'plan_id' => $plan_id,
            'reference_metadata' => wp_json_encode($p)
        ];

        if (class_exists('KHM\Membership\AttributionEndpoint')) {
            $req = new \WP_REST_Request('POST');
            $req->set_body(wp_json_encode($attribution_data));
            $req->set_route('/kh-membership/v1/attribution');
            $attribution_endpoint = new AttributionEndpoint();
            $attribution_endpoint->handle_request($req);
        }
        
        // --- Return Status ---
        $status_response = [
            'user_id' => $user_id,
            'status' => 'trialing',
            'plan_id' => $plan_id
        ];

        return rest_ensure_response($status_response);
    }
}
