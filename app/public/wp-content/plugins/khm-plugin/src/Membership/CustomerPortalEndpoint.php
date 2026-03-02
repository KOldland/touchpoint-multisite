<?php

namespace KHM\Membership;

class CustomerPortalEndpoint {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'kh-membership/v1', '/portal', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
    }

    public function check_permission() {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Authentication required.', 'khm' ),
                [ 'status' => 401 ]
            );
        }
        return true;
    }

    public function handle_request( \WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return new \WP_REST_Response( [ 'error' => 'authentication_required' ], 401 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'user_membership';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT stripe_customer_id FROM {$table} WHERE user_id = %d LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );
        $stripe_customer_id = isset( $row['stripe_customer_id'] ) ? sanitize_text_field( (string) $row['stripe_customer_id'] ) : '';
        if ( '' === $stripe_customer_id ) {
            return new \WP_REST_Response( [ 'error' => 'stripe_customer_not_found' ], 400 );
        }

        $secret = (string) get_option( 'khm_stripe_secret_key', '' );
        if ( '' === $secret || ! class_exists( '\Stripe\Stripe' ) || ! class_exists( '\Stripe\BillingPortal\Session' ) ) {
            return new \WP_REST_Response( [ 'error' => 'stripe_not_configured' ], 500 );
        }

        $return_url = esc_url_raw( (string) $request->get_param( 'return_url' ) );
        if ( '' === $return_url ) {
            $return_url = home_url( '/membership/manage' );
        }

        try {
            \Stripe\Stripe::setApiKey( $secret );
            $session = \Stripe\BillingPortal\Session::create( [
                'customer' => $stripe_customer_id,
                'return_url' => $return_url,
            ] );
            if ( empty( $session->url ) ) {
                return new \WP_REST_Response( [ 'error' => 'portal_session_failed' ], 500 );
            }

            return rest_ensure_response( [
                'success' => true,
                'url' => esc_url_raw( (string) $session->url ),
            ] );
        } catch ( \Throwable $e ) {
            error_log( 'Stripe customer portal session error: ' . $e->getMessage() );
            return new \WP_REST_Response( [ 'error' => 'portal_session_failed' ], 500 );
        }
    }
}

