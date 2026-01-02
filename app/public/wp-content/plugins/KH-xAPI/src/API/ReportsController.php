<?php
namespace KH\XAPI\API;

use KH\XAPI\Services\LearningDataService;
use WP_REST_Request;
use WP_REST_Response;

class ReportsController {
    private LearningDataService $data_service;

    public function __construct( LearningDataService $data_service ) {
        $this->data_service = $data_service;
    }

    public function hooks(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route(
            'kh-xapi/v1',
            '/reports',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_report' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => $this->request_args(),
            ]
        );

        register_rest_route(
            'kh-xapi/v1',
            '/reports/export',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_export' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => $this->request_args(),
            ]
        );

        register_rest_route(
            'kh-xapi/v1',
            '/reports/aggregate',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_aggregate' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => array_merge(
                    $this->request_args(),
                    [
                        'dimension' => [
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ]
                ),
            ]
        );
    }

    public function permissions_check(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );
    }

    private function request_args(): array {
        return [
            'user_id'      => [ 'validate_callback' => 'absint' ],
            'content_id'   => [ 'validate_callback' => 'absint' ],
            'status'       => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'registration' => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'date_from'    => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'date_to'      => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'summary'      => [ 'sanitize_callback' => 'rest_sanitize_boolean' ],
            'dimension'    => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'limit'        => [ 'sanitize_callback' => 'absint' ],
            'offset'       => [ 'sanitize_callback' => 'absint' ],
        ];
    }

    public function handle_report( WP_REST_Request $request ): WP_REST_Response {
        $args = $this->build_query_args( $request );

        $cache_key = $this->build_cache_key( 'report', $args );
        $cached    = wp_cache_get( $cache_key, 'kh_xapi_reports' );
        if ( false !== $cached ) {
            return new WP_REST_Response( $cached );
        }

        $rows = $this->data_service->query_completions( $args );
        $data = [ 'rows' => array_map( [ $this, 'format_row' ], $rows ) ];

        if ( rest_sanitize_boolean( $request->get_param( 'summary' ) ) ) {
            $data['summary'] = $this->data_service->get_summary( $args );
        }

        wp_cache_set( $cache_key, $data, 'kh_xapi_reports', MINUTE_IN_SECONDS );

        return new WP_REST_Response( $data );
    }

    public function handle_export( WP_REST_Request $request ) {
        $args      = $this->build_query_args( $request );
        $dimension = $request->get_param( 'dimension' );

        if ( $dimension ) {
            $aggregate = $this->data_service->aggregate_by( $dimension, $args );
            $headers   = $aggregate['headers'];
            $rows      = $aggregate['rows'];
            $csv_rows  = [ $headers ];

            foreach ( $rows as $row ) {
                $csv_rows[] = array_map(
                    static function ( $header ) use ( $row ) {
                        return $row[ $header ] ?? '';
                    },
                    $headers
                );
            }

            return $this->csv_response( $csv_rows, 'kh-xapi-aggregate.csv' );
        }

        $rows = $this->data_service->query_completions( array_merge( $args, [ 'limit' => null, 'offset' => null ] ) );

        $csv_rows   = [];
        $csv_rows[] = [ 'content_id', 'user_id', 'status', 'percentage', 'score', 'timespent', 'registration', 'recorded_at' ];
        foreach ( $rows as $row ) {
            $csv_rows[] = [ $row->content_id, $row->user_id, $row->status, $row->percentage, $row->score, $row->timespent, $row->registration, $row->recorded_at ];
        }

        return $this->csv_response( $csv_rows, 'kh-xapi-report.csv' );
    }

    private function csv_response( array $rows, string $filename ): WP_REST_Response {
        $fh = fopen( 'php://temp', 'w+' );
        foreach ( $rows as $csv_row ) {
            fputcsv( $fh, $csv_row );
        }
        rewind( $fh );
        $csv = stream_get_contents( $fh );
        fclose( $fh );

        $response = new WP_REST_Response( $csv );
        $response->header( 'Content-Type', 'text/csv' );
        $response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );

        return $response;
    }

    public function handle_aggregate( WP_REST_Request $request ): WP_REST_Response {
        $args      = $this->build_query_args( $request );
        $dimension = $request->get_param( 'dimension' ) ?: 'content';

        $cache_key = $this->build_cache_key( 'aggregate_' . $dimension, $args );
        $cached    = wp_cache_get( $cache_key, 'kh_xapi_reports' );
        if ( false !== $cached ) {
            return new WP_REST_Response( $cached );
        }

        $result = $this->data_service->aggregate_by( $dimension, $args );
        wp_cache_set( $cache_key, $result, 'kh_xapi_reports', MINUTE_IN_SECONDS );

        return new WP_REST_Response( $result );
    }

    private function build_query_args( WP_REST_Request $request ): array {
        $args = [];
        foreach ( [ 'user_id', 'content_id', 'status', 'registration', 'date_from', 'date_to', 'dimension' ] as $field ) {
            if ( null !== $request->get_param( $field ) ) {
                $args[ $field ] = $request->get_param( $field );
            }
        }

        if ( null !== $request->get_param( 'limit' ) ) {
            $args['limit'] = absint( $request->get_param( 'limit' ) );
        }

        if ( null !== $request->get_param( 'offset' ) ) {
            $args['offset'] = absint( $request->get_param( 'offset' ) );
        }

        return $args;
    }

    private function format_row( $row ): array {
        return [
            'content_id'   => (int) $row->content_id,
            'user_id'      => (int) $row->user_id,
            'status'       => $row->status,
            'percentage'   => (float) $row->percentage,
            'score'        => (float) $row->score,
            'timespent'    => (int) $row->timespent,
            'registration' => $row->registration,
            'recorded_at'  => $row->recorded_at,
        ];
    }

    private function build_cache_key( string $prefix, array $args ): string {
        ksort( $args );
        return $prefix . '_' . md5( wp_json_encode( $args ) );
    }
}
