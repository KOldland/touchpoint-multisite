<?php
/**
 * Membership Checkout Handler
 *
 * Handles AJAX requests for the membership checkout modal.
 * Creates Stripe Checkout Sessions for membership subscriptions.
 */

namespace KHM\Frontend;

use KHM\Services\LevelRepository;

class MembershipCheckoutHandler {

    private static bool $booted = false;
    private ?LevelRepository $levels = null;

    public function __construct() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks(): void {
        // Register AJAX handler (accessible to logged-in and logged-out users)
        add_action('wp_ajax_khm_create_membership_checkout', [$this, 'ajax_create_checkout_session']);
        add_action('wp_ajax_nopriv_khm_create_membership_checkout', [$this, 'ajax_create_checkout_session']);
    }

    /**
     * AJAX handler to create Stripe Checkout Session for membership.
     *
     * Expected POST params:
     * - membership_level_id: The tier ID
     * - nonce: Security nonce
     *
     * Returns:
     * - checkout_url: The Stripe Checkout URL to redirect to
     */
    public function ajax_create_checkout_session(): void {
        // Verify nonce
        check_ajax_referer('khm_membership_checkout_nonce', 'nonce');

        // Get and validate membership level ID
        $level_id = intval($_POST['membership_level_id'] ?? 0);
        if (empty($level_id)) {
            wp_send_json_error([
                'message' => __('Invalid membership tier.', 'khm-membership')
            ], 400);
        }

        // Get current user (or prepare for guest checkout)
        $user_id = get_current_user_id();
        $user_email = '';
        $user = null;

        if ($user_id) {
            $user = wp_get_current_user();
            $user_email = $user->user_email;
        }

        // Validate membership tier exists
        global $wpdb;
        $table = $wpdb->prefix . 'khm_membership_levels';
        $level = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $level_id
        ));

        if (!$level) {
            wp_send_json_error([
                'message' => __('Membership tier not found.', 'khm-membership')
            ], 404);
        }

        // Check if user already has an active membership (if logged in)
        if ($user_id) {
            $membership_table = $wpdb->prefix . 'khm_memberships';
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$membership_table} WHERE user_id = %d AND status IN ('active', 'trialing') LIMIT 1",
                $user_id
            ));

            if ($existing) {
                wp_send_json_error([
                    'message' => __('You already have an active membership. Please cancel your current membership before subscribing to a new tier.', 'khm-membership')
                ], 409);
            }
        }

        // Get Stripe configuration
        $stripe_secret = get_option('khm_stripe_secret_key', '');
        $stripe_publishable = get_option('khm_stripe_publishable_key', '');

        if (empty($stripe_secret) || empty($stripe_publishable)) {
            error_log('KHM Membership Checkout: Stripe not configured');
            wp_send_json_error([
                'message' => __('Payment system is not configured. Please contact support.', 'khm-membership')
            ], 500);
        }

        // Resolve Stripe Price ID for this membership level
        $price_id = $this->resolve_stripe_price_id($level_id);
        if (empty($price_id)) {
            error_log("KHM Membership Checkout: No Stripe price ID found for level {$level_id}");
            wp_send_json_error([
                'message' => __('This membership tier is not available for purchase. Please contact support.', 'khm-membership')
            ], 400);
        }

        // Prepare success and cancel URLs
        $success_url = apply_filters(
            'khm_membership_checkout_success_url',
            home_url('/account/?membership=success'),
            $level_id,
            $user_id
        );

        $cancel_url = apply_filters(
            'khm_membership_checkout_cancel_url',
            home_url('/?membership=cancelled'),
            $level_id,
            $user_id
        );

        // Prepare metadata for Stripe (will be sent back via webhook)
        $metadata = [
            'purchase_type' => 'subscription',
            'membership_level_id' => (string) $level_id,
            'membership_level_name' => $level->name ?? 'Membership',
        ];

        if ($user_id) {
            $metadata['user_id'] = (string) $user_id;
        }

        $create_account = !empty($_POST['create_account']) ? '1' : '0';
        $profile = $this->sanitize_profile_payload($_POST['profile'] ?? null);
        $guest_email = sanitize_email((string) ($_POST['guest_email'] ?? ''));

        if ($create_account === '1') {
            if ($profile['first_name'] === '' || $profile['last_name'] === '') {
                wp_send_json_error([
                    'message' => __('First name and last name are required to create an account.', 'khm-membership')
                ], 400);
            }
            if ($profile['mobile'] !== '' && strlen($profile['mobile']) < 7) {
                wp_send_json_error([
                    'message' => __('Please provide a valid mobile number.', 'khm-membership')
                ], 400);
            }
        }

        $metadata['create_account'] = $create_account;
        if (!empty($profile['first_name'])) {
            $metadata['profile_first_name'] = $profile['first_name'];
        }
        if (!empty($profile['last_name'])) {
            $metadata['profile_last_name'] = $profile['last_name'];
        }
        if (!empty($profile['mobile'])) {
            $metadata['profile_mobile'] = $profile['mobile'];
        }
        if (!empty($profile['job_title'])) {
            $metadata['profile_job_title'] = $profile['job_title'];
        }
        if (!empty($profile['company'])) {
            $metadata['profile_company'] = $profile['company'];
        }
        if (!empty($profile['marketing_opt_in'])) {
            $metadata['profile_marketing_optin'] = '1';
        }
        if ($guest_email && is_email($guest_email)) {
            $metadata['guest_email'] = $guest_email;
        }

        // Create Stripe Checkout Session
        try {
            \Stripe\Stripe::setApiKey($stripe_secret);

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
                'metadata' => $metadata,
                'allow_promotion_codes' => $this->resolve_allow_promotion_codes( $level_id ),
            ];

            // If user is logged in, pre-fill email
            if ($user_email) {
                $session_params['customer_email'] = $user_email;
            } elseif ($guest_email && is_email($guest_email)) {
                $session_params['customer_email'] = $guest_email;
            }

            // Optional: Set subscription data
            $session_params['subscription_data'] = [
                'metadata' => $metadata
            ];

            $session_params = apply_filters(
                'khm_membership_checkout_session_params',
                $session_params,
                $level_id,
                $user_id ?: null,
                $user_email
            );

            $session = \Stripe\Checkout\Session::create($session_params);

            if (empty($session->url)) {
                throw new \Exception('Checkout session created but missing URL');
            }

            // Log successful session creation
            error_log(sprintf(
                'KHM Membership Checkout: Session created for level %d (user %d): %s',
                $level_id,
                $user_id,
                $session->id
            ));

            wp_send_json_success([
                'checkout_url' => $session->url,
                'session_id' => $session->id
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('KHM Membership Checkout Stripe Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('Unable to create checkout session. Please try again later.', 'khm-membership')
            ], 500);

        } catch (\Throwable $e) {
            error_log('KHM Membership Checkout Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An unexpected error occurred. Please try again.', 'khm-membership')
            ], 500);
        }
    }

    /**
     * Resolve Stripe Price ID for a membership level.
     *
     * This method checks multiple sources to find the Stripe Price ID:
     * 1. Filter hook (allows custom mapping)
     * 2. Level meta (stored in database)
     * 3. Options table (global mapping array)
     *
     * @param int $level_id Membership level ID
     * @return string|null Stripe Price ID or null if not found
     */
    private function resolve_stripe_price_id(int $level_id): ?string {
        if (function_exists('khm_get_level_price_id')) {
            return khm_get_level_price_id($level_id);
        }

        // Fallback to legacy behavior if helper is missing.
        $filtered = apply_filters('khm_stripe_membership_price_map', null, $level_id);
        if (is_string($filtered) && !empty($filtered)) {
            return $filtered;
        }
        if (is_array($filtered) && isset($filtered[$level_id])) {
            return $filtered[$level_id];
        }

        global $wpdb;
        $meta_table = $wpdb->prefix . 'khm_membership_levelmeta';
        $price_id = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$meta_table} WHERE level_id = %d AND meta_key = 'stripe_price_id' LIMIT 1",
            $level_id
        ));

        if (!empty($price_id)) {
            return maybe_unserialize($price_id);
        }

        $price_map = get_option('khm_stripe_price_map', []);
        if (is_array($price_map) && isset($price_map[$level_id])) {
            return $price_map[$level_id];
        }

        return null;
    }

    private function resolve_allow_promotion_codes( int $level_id ): bool {
        if ( ! $this->levels ) {
            $this->levels = class_exists( LevelRepository::class ) ? new LevelRepository() : null;
        }

        if ( ! $this->levels ) {
            return true;
        }

        $meta = $this->levels->getMeta( $level_id, 'khm_level_meta', [] );
        if ( is_string( $meta ) ) {
            $decoded = json_decode( $meta, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $meta = $decoded;
            }
        }

        if ( is_array( $meta ) ) {
            $commerce = $meta['commerce'] ?? null;
            if ( is_array( $commerce ) && array_key_exists( 'allow_promotion_codes', $commerce ) ) {
                return (bool) $commerce['allow_promotion_codes'];
            }
        }

        return true;
    }

    /**
     * Sanitize optional guest profile payload passed from checkout modal.
     *
     * @param mixed $profile
     * @return array{first_name:string,last_name:string,mobile:string,job_title:string,company:string,marketing_opt_in:int}
     */
    private function sanitize_profile_payload($profile): array {
        if (!is_array($profile)) {
            return [
                'first_name' => '',
                'last_name' => '',
                'mobile' => '',
                'job_title' => '',
                'company' => '',
                'marketing_opt_in' => 0,
            ];
        }

        return [
            'first_name' => sanitize_text_field((string) ($profile['first_name'] ?? '')),
            'last_name' => sanitize_text_field((string) ($profile['last_name'] ?? '')),
            'mobile' => sanitize_text_field((string) ($profile['mobile'] ?? '')),
            'job_title' => sanitize_text_field((string) ($profile['job_title'] ?? '')),
            'company' => sanitize_text_field((string) ($profile['company'] ?? '')),
            'marketing_opt_in' => !empty($profile['marketing_opt_in']) ? 1 : 0,
        ];
    }
}
