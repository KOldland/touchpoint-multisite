<?php

namespace KHM\Membership;

use KHM\Services\MembershipRepository;

class LandingSuccessEndpoint {
    private const TELEMETRY_ROUTE = '/landing-telemetry';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'kh-membership/v1', '/landing-success', [
            'methods' => 'GET',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'kh-membership/v1', self::TELEMETRY_ROUTE, [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_telemetry' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_request( \WP_REST_Request $req ) {
        $session_id = sanitize_text_field( (string) $req->get_param( 'session_id' ) );
        if ( '' === $session_id ) {
            return new \WP_REST_Response( [ 'error' => 'session_id is required.' ], 400 );
        }

        $repo = new MembershipRepository();
        $record = $repo->getTempAttribution( $session_id );
        $payload = is_array( $record ) && isset( $record['payload'] ) && is_array( $record['payload'] )
            ? $record['payload']
            : [];

        $response = $this->build_response_payload( $session_id, $payload, $repo );

        $this->emit_telemetry( 'landing.success', [
            'session_id' => $response['session_id'],
            'schedule_id' => isset( $response['schedule']['id'] ) ? (string) $response['schedule']['id'] : '',
            'sponsor_id' => isset( $response['sponsor']['id'] ) ? (string) $response['sponsor']['id'] : '',
            'consent' => ! empty( $response['consent'] ) ? 1 : 0,
            'membership_status' => (string) ( $response['membership_status'] ?? 'none' ),
        ] );

        return new \WP_REST_Response( $response, 200 );
    }

    public function handle_telemetry( \WP_REST_Request $req ) {
        $params = $req->get_json_params();
        $params = is_array( $params ) ? $params : [];

        $metric = isset( $params['metric'] ) ? sanitize_key( (string) $params['metric'] ) : '';
        if ( '' === $metric ) {
            return new \WP_REST_Response( [ 'error' => 'metric is required.' ], 400 );
        }

        $context = [
            'session_id' => isset( $params['session_id'] ) ? sanitize_text_field( (string) $params['session_id'] ) : '',
            'cta_name' => isset( $params['cta_name'] ) ? sanitize_text_field( (string) $params['cta_name'] ) : '',
            'cta_action' => isset( $params['cta_action'] ) ? sanitize_key( (string) $params['cta_action'] ) : '',
        ];

        $this->emit_telemetry( $metric, $context );
        return new \WP_REST_Response( [ 'ok' => true ], 200 );
    }

    private function build_response_payload( string $session_id, array $payload, MembershipRepository $repo ): array {
        $status = $this->normalize_status( isset( $payload['status'] ) ? (string) $payload['status'] : 'pending' );
        $membership_status = $this->normalize_membership_status( isset( $payload['membership_status'] ) ? (string) $payload['membership_status'] : 'pending' );

        $consent = ! empty( $payload['consent'] );
        $schedule = $repo->resolveLandingSchedule( isset( $payload['schedule_id'] ) ? (string) $payload['schedule_id'] : '' );

        if ( $this->must_hide_sensitive_data( $payload ) ) {
            $consent = false;
            $sponsor = null;
            $attribution = null;
        } else {
            $sponsor = $consent
                ? $repo->resolveLandingSponsor( isset( $payload['sponsor_id'] ) ? (string) $payload['sponsor_id'] : null )
                : null;
            $attribution = $consent
                ? [
                    'schedule_id' => (string) ( $schedule['id'] ?? '' ),
                    'sponsor_id' => isset( $payload['sponsor_id'] ) ? (string) $payload['sponsor_id'] : null,
                    'utm_source' => isset( $payload['utm_source'] ) ? sanitize_text_field( (string) $payload['utm_source'] ) : null,
                    'utm_medium' => isset( $payload['utm_medium'] ) ? sanitize_text_field( (string) $payload['utm_medium'] ) : null,
                    'utm_campaign' => isset( $payload['utm_campaign'] ) ? sanitize_text_field( (string) $payload['utm_campaign'] ) : null,
                    'phase_at_click' => isset( $payload['phase_at_click'] ) ? sanitize_text_field( (string) $payload['phase_at_click'] ) : null,
                ]
                : null;
        }

        if ( empty( $payload ) ) {
            $status = 'failed';
            $membership_status = 'none';
        }

        return [
            'session_id' => $session_id,
            'status' => $status,
            'membership_status' => $membership_status,
            'schedule' => $schedule,
            'sponsor' => $sponsor,
            'consent' => (bool) $consent,
            'attribution' => $attribution,
            'ctas' => $repo->buildLandingSuccessCtas(),
            'message' => $this->message_for_status( $status, $membership_status ),
            'reference' => 'LS-' . substr( md5( $session_id ), 0, 8 ),
        ];
    }

    private function must_hide_sensitive_data( array $payload ): bool {
        $current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
        $payload_user_id = isset( $payload['user_id'] ) ? (int) $payload['user_id'] : 0;
        if ( $payload_user_id <= 0 ) {
            return false;
        }

        if ( $current_user_id <= 0 ) {
            return true;
        }

        return $current_user_id !== $payload_user_id;
    }

    private function normalize_status( string $status ): string {
        $status = strtolower( trim( $status ) );
        if ( ! in_array( $status, [ 'pending', 'complete', 'failed' ], true ) ) {
            return 'pending';
        }

        return $status;
    }

    private function normalize_membership_status( string $status ): string {
        $status = strtolower( trim( $status ) );
        if ( ! in_array( $status, [ 'active', 'trialing', 'pending', 'none' ], true ) ) {
            return 'pending';
        }

        return $status;
    }

    private function message_for_status( string $status, string $membership_status ): string {
        if ( 'failed' === $status ) {
            return 'We could not confirm your membership yet. Please contact support with your reference code.';
        }

        if ( 'pending' === $status || 'pending' === $membership_status ) {
            return 'Your payment is still processing. We will update this page automatically.';
        }

        if ( 'trialing' === $membership_status ) {
            return 'Your trial membership is active.';
        }

        return 'Your membership is active. Welcome aboard!';
    }

    private function emit_telemetry( string $metric, array $context = [] ): void {
        do_action( 'khm_membership_landing_telemetry', $metric, $context );
        error_log( 'KHM landing ' . $metric . ' ' . wp_json_encode( $context ) );
    }
}
