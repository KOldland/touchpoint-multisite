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
        $p = is_array( $p ) ? $p : [];
        $attribution = $this->normalize_attribution_payload( $p );

        // --- Validation ---
        $email = sanitize_email($p['email'] ?? '');
        if ( !is_email($email) ) {
            return $this->error_response( 'MBR_ERR_100', 'invalid_email', 400, false, [ 'field' => 'email' ] );
        }

        $tier_slug = isset( $p['tier'] ) ? sanitize_key( (string) $p['tier'] ) : '';
        $plan_id = isset($p['plan_id']) ? intval($p['plan_id']) : 0;
        if ( $plan_id <= 0 && $tier_slug === '' ) {
            return $this->error_response( 'MBR_ERR_101', 'invalid_plan', 400, false, [ 'field' => 'plan_id' ] );
        }

        $plan = $this->resolve_plan( $plan_id, $tier_slug );
        if ( ! $plan ) {
            if ( $tier_slug !== '' ) {
                return $this->error_response( 'MBR_ERR_103', 'unknown_tier', 400, false, [ 'tier' => $tier_slug ] );
            }
            return $this->error_response( 'MBR_ERR_104', 'unknown_plan', 400, false, [ 'plan_id' => $plan_id ] );
        }
        $plan_id = (int) $plan->id;
        $tier_slug = sanitize_key( (string) ( $plan->slug ?? $tier_slug ) );

        // --- User Creation or Retrieval ---
        $provided_user_id = isset($p['user_id']) ? intval($p['user_id']) : 0;
        $user_id = email_exists($email);

        if ($user_id && $provided_user_id && $user_id !== $provided_user_id) {
            return $this->error_response( 'MBR_ERR_105', 'email_conflict', 409, false );
        }

        if ( !$user_id ) {
            // Create a new user
            $password = wp_generate_password();
            $user_id = wp_create_user($email, $password, $email);
            if ( is_wp_error($user_id) ) {
                return $this->error_response(
                    'MBR_ERR_106',
                    'user_create_failed',
                    500,
                    true,
                    [ 'wp_error' => $user_id->get_error_message() ]
                );
            }
        }

        // Check for existing active subscription
        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $existing_membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $user_membership_table WHERE user_id = %d AND status IN ('active', 'trial')",
            $user_id
        ));

        if ($existing_membership) {
            return $this->error_response( 'MBR_ERR_107', 'already_subscribed', 409, false );
        }

        // --- Promotion Attribution ---
        $this->create_attribution($attribution, $user_id, $email, $plan_id);

        // --- Determine if plan requires payment ---
        $requires_payment = !empty($plan->price_cents) && $plan->price_cents > 0;
        $registry_entry = TierRegistry::get_tier( $tier_slug );
        $trial_days = $registry_entry && ! empty( $registry_entry['trial_eligible'] )
            ? (int) ( $registry_entry['trial_days'] ?? 0 )
            : ( isset($plan->trial_days) ? intval($plan->trial_days) : 0 );

        // If plan is free or has a trial period without immediate payment, start trial
        if (!$requires_payment) {
            return $this->start_trial($user_id, $plan_id, $tier_slug, $trial_days);
        }

        // Otherwise, create Stripe Checkout Session
        return $this->create_checkout_session($user_id, $email, $plan_id, $tier_slug, $trial_days, $attribution);
    }

    private function start_trial($user_id, $plan_id, string $tier_slug, $trial_days) {
        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        $trial_ends_at = null;
        if ($trial_days > 0) {
            $trial_ends_at = gmdate('Y-m-d H:i:s', time() + ($trial_days * 86400));
        }

        $wpdb->replace($user_membership_table, [
            'user_id' => $user_id,
            'tier_id' => $plan_id,
            'tier_slug' => $tier_slug,
            'status' => 'trial',
            'trial_ends_at' => $trial_ends_at,
            'trial_end_date' => $trial_ends_at,
            'started_at' => current_time('mysql', 1),
        ]);

        return rest_ensure_response([
            'success' => true,
            'status' => 'trial',
            'user_id' => $user_id,
            'membership' => [
                'tier_id' => $plan_id,
                'tier_slug' => $tier_slug,
                'status' => 'trial',
                'trial_ends_at' => $trial_ends_at ? gmdate('c', strtotime($trial_ends_at)) : null
            ]
        ]);
    }

    private function create_checkout_session($user_id, $email, $plan_id, string $tier_slug, int $trial_days, array $attribution) {
        $secret = get_option('khm_stripe_secret_key', '');
        if ( empty($secret) ) {
            return $this->error_response( 'MBR_ERR_200', 'stripe_not_configured', 500, false );
        }

        // Resolve Stripe price ID
        $price_id = $this->resolve_price_id($plan_id, $tier_slug);
        if ( empty($price_id) ) {
            return $this->error_response( 'MBR_ERR_201', 'stripe_price_missing', 400, false );
        }

        $success_url = apply_filters('khm_membership_checkout_success_url', home_url('/account/'), $plan_id, $user_id);
        $cancel_url  = apply_filters('khm_membership_checkout_cancel_url', home_url('/checkout/'), $plan_id, $user_id);

        $metadata = [
            'purchase_type' => 'subscription',
            'membership_level_id' => (string) $plan_id,
            'tier_slug' => (string) $tier_slug,
            'stripe_price_id' => (string) $price_id,
            'user_id' => (string) $user_id,
            'schedule_id' => (string) ( $attribution['schedule_id'] ?? '' ),
            'sponsor_id' => (string) ( $attribution['sponsor_id'] ?? '' ),
            'utm_source' => (string) ( $attribution['utm_source'] ?? '' ),
            'utm_medium' => (string) ( $attribution['utm_medium'] ?? '' ),
            'utm_campaign' => (string) ( $attribution['utm_campaign'] ?? '' ),
            'phase_at_click' => (string) ( $attribution['phase_at_click'] ?? '' ),
            'idempotency_key' => (string) ( $attribution['idempotency_key'] ?? '' ),
            'consent' => ! empty( $attribution['consent'] ) ? '1' : '0',
            'trial_days' => (string) max( 0, $trial_days ),
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
            if ( $trial_days > 0 ) {
                $session_params['subscription_data'] = [
                    'trial_period_days' => $trial_days,
                    'metadata' => $metadata,
                ];
            } else {
                $session_params['subscription_data'] = [
                    'metadata' => $metadata,
                ];
            }

            $session = \Stripe\Checkout\Session::create($session_params);

            if ( empty($session->url) ) {
                return $this->error_response( 'MBR_ERR_202', 'checkout_session_missing_url', 500, true );
            }

            return rest_ensure_response([
                'success' => true,
                'status' => 'requires_payment_method',
                'redirect_url' => $session->url
            ]);

        } catch ( \Throwable $e ) {
            error_log('Stripe checkout session error in /signup: ' . $e->getMessage());
            return $this->error_response(
                'MBR_ERR_203',
                'checkout_create_failed',
                500,
                true,
                [ 'exception' => $e->getMessage() ]
            );
        }
    }

    private function resolve_price_id($plan_id, string $tier_slug = '') {
        if ( $tier_slug !== '' ) {
            $tier = TierRegistry::get_tier( $tier_slug );
            if ( $tier && ! empty( $tier['price_id'] ) ) {
                return sanitize_text_field( (string) $tier['price_id'] );
            }
        }

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

    private function resolve_plan( int $plan_id, string $tier_slug = '' ) {
        global $wpdb;
        $membership_tier_table = $wpdb->prefix . 'membership_tier';
        if ( $plan_id > 0 ) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $membership_tier_table WHERE id = %d AND is_active = 1",
                $plan_id
            ));
        }
        if ( $tier_slug !== '' ) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $membership_tier_table WHERE slug = %s AND is_active = 1",
                $tier_slug
            ));
        }
        return null;
    }

    private function create_attribution(array $attribution, $user_id, $email, $plan_id) {
        if ( empty( $attribution['consent'] ) ) {
            return;
        }

        if ( empty( $attribution['schedule_id'] ) ) {
            return;
        }

        $attribution_data = [
            'conversion_type' => 'signup',
            'schedule_id' => intval($attribution['schedule_id']),
            'sponsor_id' => ! empty( $attribution['sponsor_id'] ) ? intval($attribution['sponsor_id']) : null,
            'user_id' => $user_id,
            'user_email' => $email,
            'utm_source' => sanitize_text_field($attribution['utm_source'] ?? ''),
            'utm_medium' => sanitize_text_field($attribution['utm_medium'] ?? ''),
            'utm_campaign' => sanitize_text_field($attribution['utm_campaign'] ?? ''),
            'utm_term' => sanitize_text_field($attribution['utm_term'] ?? ''),
            'utm_content' => sanitize_text_field($attribution['utm_content'] ?? ''),
            'phase_at_click' => sanitize_text_field($attribution['phase_at_click'] ?? ''),
            'plan_id' => $plan_id,
            'reference_metadata' => wp_json_encode($attribution)
        ];

        if (class_exists('KHM\Membership\AttributionEndpoint')) {
            $req = new \WP_REST_Request('POST');
            $req->set_body(wp_json_encode($attribution_data));
            $req->set_route('/kh-membership/v1/attribution');
            $attribution_endpoint = new AttributionEndpoint();
            $attribution_endpoint->handle_request($req);
        }
    }

    private function normalize_attribution_payload( array $params ): array {
        $consent = ! empty( $params['consent'] ) && in_array( strtolower( (string) $params['consent'] ), [ '1', 'true', 'yes', 'on' ], true );

        $payload = [
            'schedule_id' => isset( $params['schedule_id'] ) ? absint( $params['schedule_id'] ) : 0,
            'sponsor_id' => isset( $params['sponsor_id'] ) ? absint( $params['sponsor_id'] ) : 0,
            'utm_source' => sanitize_text_field( (string) ( $params['utm_source'] ?? '' ) ),
            'utm_medium' => sanitize_text_field( (string) ( $params['utm_medium'] ?? '' ) ),
            'utm_campaign' => sanitize_text_field( (string) ( $params['utm_campaign'] ?? '' ) ),
            'utm_term' => sanitize_text_field( (string) ( $params['utm_term'] ?? '' ) ),
            'utm_content' => sanitize_text_field( (string) ( $params['utm_content'] ?? '' ) ),
            'phase_at_click' => sanitize_text_field( (string) ( $params['phase_at_click'] ?? '' ) ),
            'idempotency_key' => sanitize_text_field( (string) ( $params['idempotency_key'] ?? wp_generate_uuid4() ) ),
            'consent' => $consent,
        ];

        if ( ! $consent ) {
            $payload['sponsor_id'] = 0;
            $payload['utm_source'] = '';
            $payload['utm_medium'] = '';
            $payload['utm_campaign'] = '';
            $payload['utm_term'] = '';
            $payload['utm_content'] = '';
            $payload['phase_at_click'] = '';
        }

        return $payload;
    }

    private function error_response( string $code, string $message, int $status = 400, bool $retryable = false, array $details = [] ): \WP_REST_Response {
        $support_code = $code . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 6 ) );
        $help_url = apply_filters( 'khm_membership_help_url', home_url( '/support/' ) );
        $legacy_error_map = [
            'invalid_plan' => 'invalid plan_id',
            'unknown_plan' => 'plan_id does not exist',
            'already_subscribed' => 'user already has an active subscription',
        ];
        $legacy_error = isset( $legacy_error_map[ $message ] ) ? $legacy_error_map[ $message ] : str_replace( '_', ' ', $message );

        return new \WP_REST_Response(
            [
                'error' => $legacy_error,
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'help_url' => $help_url,
                'retryable' => $retryable,
                'support_code' => $support_code,
            ],
            $status
        );
    }
}
