<?php

namespace KHM\Membership;

use KHM\Services\MembershipRepository;

class DsarController {
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = 3600;

    public function register_routes(): void {
        register_rest_route( 'kh-membership/v1', '/dsar/request', [
            'methods' => 'POST',
            'callback' => [ $this, 'request' ],
            'permission_callback' => [ $this, 'can_request' ],
        ] );

        register_rest_route( 'kh-membership/v1', '/dsar/approve', [
            'methods' => 'POST',
            'callback' => [ $this, 'approve' ],
            'permission_callback' => [ $this, 'can_approve' ],
        ] );
    }

    public function can_request(): bool {
        return (int) get_current_user_id() > 0;
    }

    public function can_approve(): bool {
        return current_user_can( 'manage_options' );
    }

    public function request( \WP_REST_Request $request ): \WP_REST_Response {
        $userId = (int) get_current_user_id();
        if ( $userId <= 0 ) {
            return new \WP_REST_Response( [ 'error' => 'authentication_required' ], 401 );
        }

        if ( $this->is_rate_limited( $userId ) ) {
            return new \WP_REST_Response( [ 'error' => 'rate_limited' ], 429 );
        }

        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : [];

        $type = isset( $payload['type'] ) ? sanitize_key( (string) $payload['type'] ) : 'export';
        if ( ! in_array( $type, [ 'export', 'delete', 'anonymize' ], true ) ) {
            return new \WP_REST_Response( [ 'error' => 'invalid_type' ], 400 );
        }

        $ticket = isset( $payload['ticket_id'] ) ? sanitize_text_field( (string) $payload['ticket_id'] ) : '';
        $requestId = 'dsar_' . substr( md5( wp_generate_uuid4() . '|' . $userId . '|' . microtime( true ) ), 0, 24 );

        update_option( 'khm_dsar_request_' . $requestId, [
            'request_id' => $requestId,
            'user_id' => $userId,
            'type' => $type,
            'status' => 'pending',
            'ticket_id' => $ticket,
            'created_at' => gmdate( 'c' ),
        ], false );

        do_action( 'khm_membership_reporting_telemetry', 'dsar.requested', [
            'request_id' => $requestId,
            'user_id' => $userId,
            'type' => $type,
            'ticket_id' => $ticket,
        ] );

        return new \WP_REST_Response( [
            'request_id' => $requestId,
            'status' => 'pending',
            'message' => 'Request queued for admin approval.',
        ], 202 );
    }

    public function approve( \WP_REST_Request $request ): \WP_REST_Response {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : [];

        $requestId = isset( $params['request_id'] ) ? sanitize_text_field( (string) $params['request_id'] ) : '';
        $ticketId = isset( $params['ticket_id'] ) ? sanitize_text_field( (string) $params['ticket_id'] ) : '';
        if ( '' === $requestId ) {
            return new \WP_REST_Response( [ 'error' => 'request_id_required' ], 400 );
        }

        $record = get_option( 'khm_dsar_request_' . $requestId, null );
        if ( ! is_array( $record ) ) {
            return new \WP_REST_Response( [ 'error' => 'request_not_found' ], 404 );
        }

        $repository = new MembershipRepository();
        $userId = isset( $record['user_id'] ) ? (int) $record['user_id'] : 0;
        $type = isset( $record['type'] ) ? sanitize_key( (string) $record['type'] ) : 'export';

        if ( $userId <= 0 ) {
            return new \WP_REST_Response( [ 'error' => 'invalid_request_user' ], 400 );
        }

        if ( 'export' === $type ) {
            $result = $this->build_export_bundle( $repository, $userId, $requestId );
            if ( is_wp_error( $result ) ) {
                return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
            }

            $record['status'] = 'completed';
            $record['approved_by'] = (int) get_current_user_id();
            $record['ticket_id'] = $ticketId;
            $record['completed_at'] = gmdate( 'c' );
            $record['download_file'] = $result['file'];
            $record['rows'] = $result['rows'];
            update_option( 'khm_dsar_request_' . $requestId, $record, false );

            do_action( 'khm_membership_reporting_telemetry', 'dsar.completed', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'type' => $type,
                'rows' => $result['rows'],
                'ticket_id' => $ticketId,
            ] );

            return new \WP_REST_Response( [
                'request_id' => $requestId,
                'status' => 'completed',
                'rows' => $result['rows'],
                'file' => $result['file'],
            ], 200 );
        }

        if ( 'delete' === $type ) {
            $deleted = $repository->deleteAttributionForUser( $userId );
            $record['status'] = 'completed';
            $record['approved_by'] = (int) get_current_user_id();
            $record['ticket_id'] = $ticketId;
            $record['completed_at'] = gmdate( 'c' );
            $record['rows'] = $deleted;
            update_option( 'khm_dsar_request_' . $requestId, $record, false );

            do_action( 'khm_membership_reporting_telemetry', 'dsar.deleted', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'rows' => $deleted,
                'ticket_id' => $ticketId,
            ] );

            return new \WP_REST_Response( [
                'request_id' => $requestId,
                'status' => 'completed',
                'rows' => $deleted,
            ], 200 );
        }

        $anonymized = $repository->anonymizeAttributionForUser( $userId );
        $record['status'] = 'completed';
        $record['approved_by'] = (int) get_current_user_id();
        $record['ticket_id'] = $ticketId;
        $record['completed_at'] = gmdate( 'c' );
        $record['rows'] = $anonymized;
        update_option( 'khm_dsar_request_' . $requestId, $record, false );

        do_action( 'khm_membership_reporting_telemetry', 'dsar.deleted', [
            'request_id' => $requestId,
            'user_id' => $userId,
            'rows' => $anonymized,
            'ticket_id' => $ticketId,
            'mode' => 'anonymize',
        ] );

        return new \WP_REST_Response( [
            'request_id' => $requestId,
            'status' => 'completed',
            'rows' => $anonymized,
        ], 200 );
    }

    private function is_rate_limited( int $userId ): bool {
        $key = 'khm_dsar_rl_' . md5( (string) $userId . '|' . gmdate( 'YmdH' ) );
        $count = (int) get_transient( $key );
        $count++;
        set_transient( $key, $count, self::RATE_LIMIT_WINDOW );
        return $count > self::RATE_LIMIT_MAX;
    }

    private function build_export_bundle( MembershipRepository $repository, int $userId, string $requestId ) {
        if ( ! class_exists( '\\ZipArchive' ) ) {
            return new \WP_Error( 'zip_missing', 'ZipArchive not available.' );
        }

        $rows = $repository->getAttributionHistoryForUser( $userId, 5000 );
        $uploads = wp_upload_dir();
        $base = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : sys_get_temp_dir();
        $dir = trailingslashit( $base ) . 'khm-dsar-private';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        @file_put_contents( trailingslashit( $dir ) . '.htaccess', "Deny from all\n" );
        @file_put_contents( trailingslashit( $dir ) . 'index.html', '' );

        $zipFile = trailingslashit( $dir ) . 'dsar-' . sanitize_file_name( $requestId ) . '.zip';
        $jsonFile = trailingslashit( $dir ) . 'dsar-' . sanitize_file_name( $requestId ) . '.json';
        file_put_contents( $jsonFile, wp_json_encode( [ 'request_id' => $requestId, 'rows' => $rows ], JSON_PRETTY_PRINT ) );

        $zip = new \ZipArchive();
        if ( true !== $zip->open( $zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
            return new \WP_Error( 'zip_create_failed', 'Unable to create DSAR zip.' );
        }

        $zip->addFile( $jsonFile, 'attribution.json' );
        $zip->close();

        @unlink( $jsonFile );

        return [
            'file' => $zipFile,
            'rows' => count( $rows ),
        ];
    }
}
