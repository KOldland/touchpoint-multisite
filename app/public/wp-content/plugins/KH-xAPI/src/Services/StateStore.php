<?php
namespace KH\XAPI\Services;

class StateStore {
    private string $endpoint;
    private string $auth_header;
    private string $version;

    public function __construct() {
        $settings          = get_option( 'kh_xapi_lrs', [] );
        $this->endpoint    = trailingslashit( $settings['endpoint'] ?? '' );
        $this->version     = $settings['version'] ?? '1.0.3';
        $user              = $settings['username'] ?? '';
        $pass              = $settings['password'] ?? '';
        $this->auth_header = 'Basic ' . base64_encode( $user . ':' . $pass );
    }

    public function send_state( string $activity_id, array $agent, string $state_id, string $data, ?string $registration = null ) {
        if ( empty( $this->endpoint ) ) {
            return new \WP_Error( 'kh_xapi_state_endpoint_missing', 'No xAPI endpoint configured.' );
        }

        $url = $this->build_url( $activity_id, $agent, $state_id, $registration );

        $response = wp_remote_request(
            $url,
            [
                'method'  => 'PUT',
                'timeout' => 15,
                'headers' => $this->headers(),
                'body'    => $data,
            ]
        );

        return $response;
    }

    public function get_state( string $activity_id, array $agent, string $state_id, ?string $registration = null ) {
        if ( empty( $this->endpoint ) ) {
            return new \WP_Error( 'kh_xapi_state_endpoint_missing', 'No xAPI endpoint configured.' );
        }

        $url = $this->build_url( $activity_id, $agent, $state_id, $registration );

        return wp_remote_get(
            $url,
            [
                'timeout' => 15,
                'headers' => $this->headers(),
            ]
        );
    }

    private function build_url( string $activity_id, array $agent, string $state_id, ?string $registration ): string {
        $query = [
            'stateId'    => $state_id,
            'activityId' => $activity_id,
            'agent'      => wp_json_encode( $agent ),
        ];

        if ( ! empty( $registration ) ) {
            $query['registration'] = $registration;
        }

        return $this->endpoint . 'activities/state?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
    }

    private function headers(): array {
        return [
            'Authorization'            => $this->auth_header,
            'Content-Type'             => 'application/json',
            'Accept'                   => 'application/json, */*; q=0.01',
            'X-Experience-API-Version' => $this->version,
        ];
    }
}
