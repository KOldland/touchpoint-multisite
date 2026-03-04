<?php

namespace KHM\Membership;

use KHM\Gateways\StripeGateway;
use KHM\Services\MembershipRepository;
use KHM\Services\DiscountCodeService;

class SignupEndpoint {
    private const TEMP_ATTRIBUTION_TTL_SECONDS = 86400;
    private const RATE_LIMIT_WINDOW_SECONDS = 60;
    private const RATE_LIMIT_MAX_REQUESTS = 30;

    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route('kh-membership/v1', '/signup', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('kh-membership/v1', '/signup-init', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_signup_init' ],
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

    public function handle_signup_init( \WP_REST_Request $req ) {
        if ( $this->is_signup_init_rate_limited() ) {
            return $this->contract_error_response( 'MBR_ERR_INVALID_ATTR', 'Rate limit exceeded. Please retry shortly.', 429, true );
        }

        $params = $req->get_json_params();
        $params = is_array( $params ) ? $params : [];
        $payload = $this->normalize_canonical_attribution_payload( $params );

        // Server-side promo validation (HIGH-PRIORITY FIX #1)
        if ( ! empty( $payload['promo_code'] ) || ! empty( $payload['stripe_promotion_code'] ) ) {
            $promoValidation = $this->validate_promo_code( $payload );
            if ( is_wp_error( $promoValidation ) ) {
                return $this->contract_error_response(
                    'MBR_ERR_INVALID_PROMO',
                    $promoValidation->get_error_message(),
                    400,
                    false
                );
            }
            $payload['validated_promo'] = $promoValidation;
        }

        if ( '' === $payload['schedule_id'] || ! $this->is_valid_uuid( $payload['idempotency_key'] ) ) {
            return $this->contract_error_response( 'MBR_ERR_INVALID_ATTR', 'Invalid attribution payload.', 400, false );
        }

        if ( ! empty( $payload['sponsor_id'] ) ) {
            $sponsorValidation = $this->validate_sponsor_for_schedule( $payload['schedule_id'], $payload['sponsor_id'] );
            if ( is_wp_error( $sponsorValidation ) ) {
                return $this->contract_error_response( 'MBR_ERR_INVALID_SPONSOR', $sponsorValidation->get_error_message(), 422, false );
            }
        }

        $repo = new MembershipRepository();
        $existing = $repo->getSignupInitByIdempotency( $payload['idempotency_key'] );
        if ( is_array( $existing ) && ! empty( $existing['session_id'] ) && ! empty( $existing['checkout_url'] ) ) {
            return new \WP_REST_Response([
                'checkout_url' => $this->sanitize_url_value( (string) $existing['checkout_url'] ),
                'session_id' => sanitize_text_field( (string) $existing['session_id'] ),
                'message' => 'checkout_created',
                'temp_store_ttl_seconds' => self::TEMP_ATTRIBUTION_TTL_SECONDS,
            ], 201);
        }

        $checkout = $this->create_signup_init_checkout_session( $payload );
        if ( is_wp_error( $checkout ) ) {
            $message = $checkout->get_error_message();
            $errorData = $checkout->get_error_data();
            $retryable = is_array( $errorData ) ? ! empty( $errorData['retryable'] ) : false;
            return $this->contract_error_response( 'MBR_ERR_INVALID_ATTR', $message, 400, $retryable );
        }

        $session_id = sanitize_text_field( (string) ( $checkout['session_id'] ?? '' ) );
        $checkout_url = $this->sanitize_url_value( (string) ( $checkout['checkout_url'] ?? '' ) );
        if ( '' === $session_id || '' === $checkout_url ) {
            return $this->contract_error_response( 'MBR_ERR_INVALID_ATTR', 'Could not initialize checkout session.', 400, true );
        }

        $storePayload = $payload;
        if ( ! $payload['consent'] ) {
            $storePayload['sponsor_id'] = null;
            $storePayload['utm_source'] = null;
            $storePayload['utm_medium'] = null;
            $storePayload['utm_campaign'] = null;
            $storePayload['phase_at_click'] = null;
            $storePayload['client_reference'] = null;
        }
        $storePayload['checkout_url'] = $checkout_url;

        $repo->storeTempAttribution( $session_id, $storePayload, self::TEMP_ATTRIBUTION_TTL_SECONDS );
        $repo->storeSignupInitIdempotency( $payload['idempotency_key'], $session_id, $checkout_url, self::TEMP_ATTRIBUTION_TTL_SECONDS );

        $this->emit_landing_telemetry( 'landing.submit', [
            'session_id' => $session_id,
            'schedule_id' => $payload['schedule_id'],
            'sponsor_id' => (string) ( $payload['sponsor_id'] ?? '' ),
            'consent' => $payload['consent'] ? 1 : 0,
            'source' => 'landing',
        ] );

        return new \WP_REST_Response([
            'checkout_url' => $checkout_url,
            'session_id' => $session_id,
            'message' => 'checkout_created',
            'temp_store_ttl_seconds' => self::TEMP_ATTRIBUTION_TTL_SECONDS,
        ], 201);
    }

    public function handle_landing_success( \WP_REST_Request $req ) {
        $endpoint = new LandingSuccessEndpoint();
        return $endpoint->handle_request( $req );
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
        $secret = function_exists('khm_get_stripe_secret')
            ? (string) (khm_get_stripe_secret('KH_STRIPE_SECRET_KEY') ?? '')
            : '';
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
        if ( empty( $attribution['schedule_id'] ) ) {
            return;
        }

        $consent = ! empty( $attribution['consent'] );
        $conversionType = $consent ? 'signup' : 'signup_no_consent';

        $attribution_data = [
            'conversion_type' => $conversionType,
            'schedule_id' => intval($attribution['schedule_id']),
            'sponsor_id' => ! empty( $attribution['sponsor_id'] ) ? intval($attribution['sponsor_id']) : null,
            'user_id' => $consent ? $user_id : null,
            'user_email' => $consent ? $email : null,
            'utm_source' => $consent ? sanitize_text_field($attribution['utm_source'] ?? '') : null,
            'utm_medium' => $consent ? sanitize_text_field($attribution['utm_medium'] ?? '') : null,
            'utm_campaign' => $consent ? sanitize_text_field($attribution['utm_campaign'] ?? '') : null,
            'utm_term' => $consent ? sanitize_text_field($attribution['utm_term'] ?? '') : null,
            'utm_content' => $consent ? sanitize_text_field($attribution['utm_content'] ?? '') : null,
            'phase_at_click' => $consent ? sanitize_text_field($attribution['phase_at_click'] ?? '') : null,
            'plan_id' => $plan_id,
            'reference_metadata' => wp_json_encode( [
                'source' => 'signup',
                'consent' => $consent,
                'idempotency_key' => isset( $attribution['idempotency_key'] ) ? (string) $attribution['idempotency_key'] : '',
            ] ),
            'consent' => $consent,
                'consent_source' => sanitize_key( (string) ( $attribution['consent_source'] ?? 'landing' ) ),
            'consent_given_at' => $consent ? current_time( 'mysql', 1 ) : null,
                'reference' => isset( $attribution['idempotency_key'] ) ? sanitize_text_field( (string) $attribution['idempotency_key'] ) : null,
        ];

        if (class_exists('KHM\Membership\AttributionEndpoint')) {
            $req = new \WP_REST_Request('POST');
            $req->set_body(wp_json_encode($attribution_data));
            $req->set_route('/kh-membership/v1/attribution');
            $attribution_endpoint = new AttributionEndpoint();
            $attribution_endpoint->handle_request($req);
        }
    }

    private function normalize_canonical_attribution_payload( array $params ): array {
        $consent = ! empty( $params['consent'] ) && in_array( strtolower( (string) $params['consent'] ), [ '1', 'true', 'yes', 'on' ], true );

        $schedule_id = sanitize_text_field( (string) ( $params['schedule_id'] ?? '' ) );
        $sponsor_id = isset( $params['sponsor_id'] ) ? sanitize_text_field( (string) $params['sponsor_id'] ) : null;
        if ( '' === (string) $sponsor_id ) {
            $sponsor_id = null;
        }

        $payload = [
            'schedule_id' => $this->sanitize_contract_identifier( $schedule_id ),
            'sponsor_id' => $sponsor_id ? $this->sanitize_contract_identifier( $sponsor_id ) : null,
            'utm_source' => $this->nullable_text( $params['utm_source'] ?? null, 128 ),
            'utm_medium' => $this->nullable_text( $params['utm_medium'] ?? null, 128 ),
            'utm_campaign' => $this->nullable_text( $params['utm_campaign'] ?? null, 256 ),
            'phase_at_click' => $this->nullable_text( $params['phase_at_click'] ?? null, 64 ),
            'idempotency_key' => sanitize_text_field( (string) ( $params['idempotency_key'] ?? wp_generate_uuid4() ) ),
            'consent' => $consent,
            'consent_source' => 'landing',
            'consent_given_at' => $consent ? current_time( 'mysql', 1 ) : null,
            'client_reference' => $this->nullable_text( $params['client_reference'] ?? null, 255 ),
            'plan_id' => $this->nullable_text( $params['plan_id'] ?? null, 128 ),
            'promo_code' => $this->nullable_text( $params['promo_code'] ?? null, 128 ),
            'stripe_promotion_code' => $this->nullable_text( $params['stripe_promotion_code'] ?? null, 255 ),
        ];

        if ( ! $consent ) {
            $payload['utm_source'] = null;
            $payload['utm_medium'] = null;
            $payload['utm_campaign'] = null;
            $payload['phase_at_click'] = null;
        }

        return $payload;
    }

    private function sanitize_contract_identifier( string $value ): string {
        $value = preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
        return substr( (string) $value, 0, 128 );
    }

    private function nullable_text( $value, int $maxLength ): ?string {
        if ( null === $value ) {
            return null;
        }
        $clean = sanitize_text_field( (string) $value );
        $clean = substr( $clean, 0, max( 1, $maxLength ) );
        return '' === $clean ? null : $clean;
    }

    private function sanitize_url_value( string $value ): string {
        if ( function_exists( 'esc_url_raw' ) ) {
            return esc_url_raw( $value );
        }

        return filter_var( $value, FILTER_SANITIZE_URL ) ?: '';
    }

    private function validate_sponsor_for_schedule( string $scheduleId, string $sponsorId ) {
        $sponsorNumericId = $this->extract_numeric_identifier( $sponsorId );
        if ( $sponsorNumericId <= 0 ) {
            return new \WP_Error( 'invalid_sponsor', 'Sponsor identifier is invalid.' );
        }

        global $wpdb;
        $sponsorTable = $wpdb->prefix . 'khm_sponsors';
        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$sponsorTable} WHERE id = %d LIMIT 1", $sponsorNumericId )
        );
        if ( ! $exists ) {
            return new \WP_Error( 'invalid_sponsor', 'Sponsor does not exist.' );
        }

        $scheduleNumericId = $this->extract_numeric_identifier( $scheduleId );
        if ( $scheduleNumericId > 0 && function_exists( 'get_post_meta' ) ) {
            $scheduleSponsor = get_post_meta( $scheduleNumericId, 'sponsor_id', true );
            if ( '' === (string) $scheduleSponsor ) {
                $scheduleSponsor = get_post_meta( $scheduleNumericId, 'khm_sponsor_id', true );
            }
            $scheduleSponsorNumeric = absint( $scheduleSponsor );
            if ( $scheduleSponsorNumeric > 0 && $scheduleSponsorNumeric !== $sponsorNumericId ) {
                return new \WP_Error( 'invalid_sponsor', 'Sponsor does not match this schedule.' );
            }
        }

        return true;
    }

    private function extract_numeric_identifier( string $value ): int {
        if ( preg_match( '/(\d+)/', $value, $matches ) ) {
            return absint( $matches[1] );
        }
        return 0;
    }

    private function is_valid_uuid( string $value ): bool {
        return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
    }

    /**
     * Validate promo code server-side using DiscountCodeService.
     *
     * @param array $payload Attribution payload with promo_code or stripe_promotion_code.
     * @return array|\WP_Error Validated promo data or error.
     */
    private function validate_promo_code( array $payload ) {
        $promoCode = sanitize_text_field( (string) ( $payload['promo_code'] ?? '' ) );
        $stripePromoCode = sanitize_text_field( (string) ( $payload['stripe_promotion_code'] ?? '' ) );

        $override = apply_filters( 'khm_membership_signup_init_validate_promo_override', null, $promoCode, $payload );
        if ( is_wp_error( $override ) ) {
            return $override;
        }
        if ( is_array( $override ) ) {
            return $override;
        }

        // Reject raw stripe_promotion_code without server validation
        if ( '' !== $stripePromoCode && '' === $promoCode ) {
            return new \WP_Error(
                'invalid_promo',
                'stripe_promotion_code must be validated via promo_code parameter.',
                [ 'retryable' => false ]
            );
        }

        if ( '' === $promoCode ) {
            return [];
        }

        // Extract plan_id from payload for validation (if available)
        $planId = 0;
        $planIdStr = (string) ( $payload['plan_id'] ?? '' );
        if ( '' !== $planIdStr ) {
            $planId = $this->extract_numeric_identifier( $planIdStr );
        }

        // If no plan_id, use default membership level (filter-based)
        if ( $planId <= 0 ) {
            $planId = (int) apply_filters( 'khm_membership_default_plan_id', 1 );
        }

        $userId = get_current_user_id();
        if ( ! $userId ) {
            $userId = 0; // Guest user
        }

        $discountService = new DiscountCodeService();
        $validation = $discountService->validate_code( $promoCode, $planId, $userId );

        if ( empty( $validation['valid'] ) ) {
            return new \WP_Error(
                'invalid_promo',
                $validation['message'] ?? 'Invalid discount code.',
                [ 'retryable' => false ]
            );
        }

        return $validation;
    }

    private function create_signup_init_checkout_session( array $payload ) {
        $mockMode = (bool) apply_filters( 'khm_membership_signup_init_use_mock_session', getenv( 'KH_SMMA_TEST_MODE' ) === 'ci' );
        if ( $mockMode ) {
            $sessionId = 'cs_test_' . substr( md5( $payload['idempotency_key'] ), 0, 16 );
            return [
                'session_id' => $sessionId,
                'checkout_url' => 'https://checkout.stripe.com/c/pay/' . $sessionId,
            ];
        }

        $secret = function_exists( 'khm_get_stripe_secret' )
            ? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
            : '';
        if ( '' === trim( $secret ) || ! class_exists( '\\Stripe\\Checkout\\Session' ) ) {
            return new \WP_Error( 'signup_init_not_configured', 'Stripe Checkout is not configured.', [ 'retryable' => false ] );
        }

        $priceId = (string) apply_filters( 'khm_membership_signup_init_price_id', '', $payload );
        if ( '' === $priceId ) {
            return new \WP_Error( 'signup_init_missing_price', 'No Stripe price configured for signup-init.', [ 'retryable' => false ] );
        }

        \Stripe\Stripe::setApiKey( $secret );

        $params = [
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'allow_promotion_codes' => true,
            'success_url' => home_url( '/membership-success?session_id={CHECKOUT_SESSION_ID}' ),
            'cancel_url' => home_url( '/membership-landing' ),
            'metadata' => [
                'schedule_id' => (string) ( $payload['schedule_id'] ?? '' ),
                'sponsor_id' => (string) ( $payload['sponsor_id'] ?? '' ),
                'utm_source' => (string) ( $payload['utm_source'] ?? '' ),
                'utm_medium' => (string) ( $payload['utm_medium'] ?? '' ),
                'utm_campaign' => (string) ( $payload['utm_campaign'] ?? '' ),
                'phase_at_click' => (string) ( $payload['phase_at_click'] ?? '' ),
                'idempotency_key' => (string) ( $payload['idempotency_key'] ?? '' ),
                'consent' => ! empty( $payload['consent'] ) ? '1' : '0',
                'client_reference' => (string) ( $payload['client_reference'] ?? '' ),
            ],
        ];

        try {
            $session = \Stripe\Checkout\Session::create( $params, [
                'idempotency_key' => (string) $payload['idempotency_key'],
            ]);
        } catch ( \Throwable $throwable ) {
            return new \WP_Error( 'signup_init_checkout_failed', 'Failed to create checkout session.', [ 'retryable' => true ] );
        }

        if ( empty( $session->id ) || empty( $session->url ) ) {
            return new \WP_Error( 'signup_init_checkout_missing', 'Checkout session was created without URL.', [ 'retryable' => true ] );
        }

        return [
            'session_id' => (string) $session->id,
            'checkout_url' => $this->sanitize_url_value( (string) $session->url ),
        ];
    }

    private function is_signup_init_rate_limited(): bool {
        $windowSeconds = (int) apply_filters( 'khm_membership_signup_init_rate_limit_window', self::RATE_LIMIT_WINDOW_SECONDS );
        $maxRequests = (int) apply_filters( 'khm_membership_signup_init_rate_limit_max_requests', self::RATE_LIMIT_MAX_REQUESTS );
        $windowSeconds = max( 5, $windowSeconds );
        $maxRequests = max( 1, $maxRequests );

        $ip = 'unknown';
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $headerKey ) {
            if ( ! empty( $_SERVER[ $headerKey ] ) ) {
                $ip = sanitize_text_field( (string) $_SERVER[ $headerKey ] );
                break;
            }
        }

        $key = 'khm_signup_init_rl_' . md5( $ip . '|' . gmdate( 'YmdHi' ) );
        $count = (int) get_transient( $key );
        $count++;
        set_transient( $key, $count, $windowSeconds );

        return $count > $maxRequests;
    }

    private function resolve_schedule_payload( string $scheduleId ): array {
        $scheduleId = $this->sanitize_contract_identifier( $scheduleId );
        $numericId = $this->extract_numeric_identifier( $scheduleId );
        $title = '';
        $recommended = '';
        $boostCopy = '';

        if ( $numericId > 0 && function_exists( 'get_post' ) ) {
            $post = get_post( $numericId );
            if ( is_object( $post ) && isset( $post->post_title ) ) {
                $title = sanitize_text_field( (string) $post->post_title );
            }
            if ( function_exists( 'get_post_meta' ) ) {
                $recommended = sanitize_text_field( (string) get_post_meta( $numericId, 'recommended_post_time', true ) );
                $boostCopy = sanitize_text_field( (string) get_post_meta( $numericId, 'boost_copy', true ) );
            }
        }

        return [
            'id' => $scheduleId,
            'title' => $title,
            'recommended_post_time' => $recommended,
            'boost_copy' => $boostCopy,
        ];
    }

    private function resolve_sponsor_payload( string $sponsorId ): array {
        $sponsorId = $this->sanitize_contract_identifier( $sponsorId );
        if ( '' === $sponsorId ) {
            return [
                'id' => null,
                'name' => '',
                'logo_url' => '',
                'accent_color' => '',
                'blurb' => '',
            ];
        }

        $numericId = $this->extract_numeric_identifier( $sponsorId );
        $name = '';
        if ( $numericId > 0 ) {
            global $wpdb;
            $table = $wpdb->prefix . 'khm_sponsors';
            $name = (string) $wpdb->get_var(
                $wpdb->prepare( "SELECT name FROM {$table} WHERE id = %d LIMIT 1", $numericId )
            );
        }

        $logo = (string) get_option( 'khm_sponsor_logo_' . $sponsorId, '' );
        $accent = sanitize_hex_color( (string) get_option( 'khm_sponsor_accent_' . $sponsorId, '' ) ) ?: '';
        $blurb = (string) get_option( 'khm_sponsor_blurb_' . $sponsorId, '' );

        $allowedBlurb = [
            'a' => [ 'href' => [], 'target' => [], 'rel' => [] ],
            'strong' => [],
            'em' => [],
            'br' => [],
            'p' => [],
            'span' => [ 'class' => [] ],
        ];

        return [
            'id' => $sponsorId,
            'name' => sanitize_text_field( $name ),
            'logo_url' => $this->sanitize_url_value( $logo ),
            'accent_color' => $accent,
            'blurb' => wp_kses( $blurb, $allowedBlurb ),
        ];
    }

    private function emit_landing_telemetry( string $metric, array $context = [] ): void {
        do_action( 'khm_membership_landing_telemetry', $metric, $context );
        error_log( 'KHM landing ' . $metric . ' ' . wp_json_encode( $context ) );
    }

    private function contract_error_response( string $code, string $message, int $status, bool $retryable ): \WP_REST_Response {
        $helpUrl = apply_filters( 'khm_membership_help_url', home_url( '/support/' ) );

        return new \WP_REST_Response([
            'error' => [
                'code' => $code,
                'message' => $message,
                'retryable' => $retryable,
                'help_url' => $helpUrl,
            ],
        ], $status);
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
