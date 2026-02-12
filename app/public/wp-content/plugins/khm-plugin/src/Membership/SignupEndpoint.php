<?php

namespace KHM\Membership;

use KHM\Gateways\StripeGateway;

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
            return new \WP_REST_Response(['error' => 'invalid email'], 400);
        }

        $plan_id = isset($p['plan_id']) ? intval($p['plan_id']) : 0;
        if ( empty($plan_id) ) {
            return new \WP_REST_Response(['error' => 'invalid plan_id'], 400);
        }

        // Validate plan exists
        global $wpdb;
        $membership_tier_table = $wpdb->prefix . 'membership_tier';
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $membership_tier_table WHERE id = %d AND is_active = 1",
            $plan_id
        ));

        if (!$plan) {
            return new \WP_REST_Response(['error' => 'plan_id does not exist'], 400);
        }

        // --- User Creation or Retrieval ---
        $provided_user_id = isset($p['user_id']) ? intval($p['user_id']) : 0;
        $user_id = email_exists($email);

        if ($user_id && $provided_user_id && $user_id !== $provided_user_id) {
            return new \WP_REST_Response([
                'error' => 'email already exists for a different user'
            ], 409);
        }

        if ( !$user_id ) {
            // Create a new user
            $password = wp_generate_password();
            $user_id = wp_create_user($email, $password, $email);
            if ( is_wp_error($user_id) ) {
                return new \WP_REST_Response([
                    'error' => 'could not create user',
                    'details' => $user_id->get_error_message()
                ], 500);
            }
        }

        // Check for existing active subscription
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $existing_membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $user_membership_table WHERE user_id = %d AND status IN ('active', 'trialing')",
            $user_id
        ));

        if ($existing_membership) {
            return new \WP_REST_Response([
                'error' => 'user already has an active subscription'
            ], 409);
        }

        // --- Promotion Attribution ---
        $this->create_attribution($p, $user_id, $email, $plan_id);

        // --- Determine if plan requires payment ---
        $requires_payment = !empty($plan->price_cents) && $plan->price_cents > 0;
        $trial_days = isset($plan->trial_days) ? intval($plan->trial_days) : 0;

        // If plan is free or has a trial period without immediate payment, start trial
        if (!$requires_payment || $trial_days > 0) {
            return $this->start_trial($user_id, $plan_id, $plan, $trial_days);
        }

        // Otherwise, create Stripe Checkout Session
        return $this->create_checkout_session($user_id, $email, $plan_id, $plan, $p);
    }

    private function start_trial($user_id, $plan_id, $plan, $trial_days) {
        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        $trial_ends_at = null;
        if ($trial_days > 0) {
            $trial_ends_at = gmdate('Y-m-d H:i:s', time() + ($trial_days * 86400));
        }

        $wpdb->replace($user_membership_table, [
            'user_id' => $user_id,
            'tier_id' => $plan_id,
            'status' => 'trialing',
            'trial_ends_at' => $trial_ends_at,
            'started_at' => current_time('mysql', 1),
        ]);

        return rest_ensure_response([
            'success' => true,
            'status' => 'trialing',
            'user_id' => $user_id,
            'membership' => [
                'tier_id' => $plan_id,
                'status' => 'trialing',
                'trial_ends_at' => $trial_ends_at ? gmdate('c', strtotime($trial_ends_at)) : null
            ]
        ]);
    }

    private function create_checkout_session($user_id, $email, $plan_id, $plan, $params) {
        $secret = get_option('khm_stripe_secret_key', '');
        if ( empty($secret) ) {
            return new \WP_REST_Response([
                'error' => 'Stripe is not configured'
            ], 500);
        }

        // Resolve Stripe price ID
        $price_id = $this->resolve_price_id($plan_id);
        if ( empty($price_id) ) {
            return new \WP_REST_Response([
                'error' => 'Stripe price mapping not configured for this plan'
            ], 400);
        }

        $success_url = apply_filters('khm_membership_checkout_success_url', home_url('/account/'), $plan_id, $user_id);
        $cancel_url  = apply_filters('khm_membership_checkout_cancel_url', home_url('/checkout/'), $plan_id, $user_id);

        $metadata = [
            'purchase_type' => 'subscription',
            'membership_level_id' => (string) $plan_id,
            'user_id' => (string) $user_id,
            'schedule_id' => isset($params['schedule_id']) ? (string) $params['schedule_id'] : '',
        ];

        try {
            \Stripe\Stripe::setApiKey($secret);

            $session_params = [
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price' => $price_id,
                        'quantity' => 1
                    ]
                ],
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'customer_email' => $email,
                'allow_promotion_codes' => true,
                'metadata' => $metadata,
            ];

            $session = \Stripe\Checkout\Session::create($session_params);

            if ( empty($session->url) ) {
                return new \WP_REST_Response([
                    'error' => 'Checkout session missing URL'
                ], 500);
            }

            return rest_ensure_response([
                'success' => true,
                'status' => 'requires_payment_method',
                'redirect_url' => $session->url
            ]);

        } catch ( \Throwable $e ) {
            error_log('Stripe checkout session error in /signup: ' . $e->getMessage());
            return new \WP_REST_Response([
                'error' => 'Unable to create checkout session'
            ], 500);
        }
    }

    private function resolve_price_id($plan_id) {
        // Try filter first
        $filtered = apply_filters('khm_stripe_membership_price_map', null, $plan_id);
        if ( is_string($filtered) && $filtered !== '' ) {
            return $filtered;
        } elseif ( is_array($filtered) && isset($filtered[$plan_id]) ) {
            return $filtered[$plan_id];
        }

        // Try option
        $map = get_option('khm_stripe_membership_price_map', []);
        if ( is_array($map) && isset($map[$plan_id]) ) {
            return $map[$plan_id];
        }

        // Try plan meta
        global $wpdb;
        $membership_tier_table = $wpdb->prefix . 'membership_tier';
        $stripe_price_id = $wpdb->get_var($wpdb->prepare(
            "SELECT stripe_price_id FROM $membership_tier_table WHERE id = %d",
            $plan_id
        ));

        return $stripe_price_id ? sanitize_text_field($stripe_price_id) : null;
    }

    private function create_attribution($params, $user_id, $email, $plan_id) {
        if (!isset($params['schedule_id'])) {
            return;
        }

        $attribution_data = [
            'conversion_type' => 'signup',
            'schedule_id' => isset($params['schedule_id']) ? intval($params['schedule_id']) : null,
            'sponsor_id' => isset($params['sponsor_id']) ? intval($params['sponsor_id']) : null,
            'user_id' => $user_id,
            'user_email' => $email,
            'utm_source' => sanitize_text_field($params['utm_source'] ?? ''),
            'utm_medium' => sanitize_text_field($params['utm_medium'] ?? ''),
            'utm_campaign' => sanitize_text_field($params['utm_campaign'] ?? ''),
            'utm_term' => sanitize_text_field($params['utm_term'] ?? ''),
            'utm_content' => sanitize_text_field($params['utm_content'] ?? ''),
            'phase_at_click' => sanitize_text_field($params['phase_at_click'] ?? ''),
            'plan_id' => $plan_id,
            'reference_metadata' => wp_json_encode($params)
        ];

        if (class_exists('KHM\Membership\AttributionEndpoint')) {
            $req = new \WP_REST_Request('POST');
            $req->set_body(wp_json_encode($attribution_data));
            $req->set_route('/kh-membership/v1/attribution');
            $attribution_endpoint = new AttributionEndpoint();
            $attribution_endpoint->handle_request($req);
        }
    }
}
