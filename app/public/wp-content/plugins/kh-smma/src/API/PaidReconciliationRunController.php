<?php
declare( strict_types=1 );

namespace KH_SMMA\Api;

use KH_SMMA\Adapters\ReconciliationService;
use KH_SMMA\Security\CapabilityManager;
use KH_SMMA\Services\AuditLogger;
use WP_REST_Request;
use WP_REST_Response;

use function add_action;
use function current_user_can;
use function get_current_user_id;
use function rest_ensure_response;
use function sanitize_text_field;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PAID-08 — REST controller for reconciliation runs.
 *
 * Routes (all under kh-smma/v1):
 *   POST   /recon/runs                           → start_run() + execute_run()
 *   GET    /recon/runs                           → list_runs()
 *   GET    /recon/runs/{run_id}                  → get_run()
 *   GET    /recon/runs/{run_id}/rows             → get_run_rows()
 *   POST   /recon/runs/{run_id}/rows/{row_id}/resolve → resolve_row()
 *   GET    /recon/runs/{run_id}/export           → export_run() as CSV
 */
class PaidReconciliationRunController {

    private const NAMESPACE = 'kh-smma/v1';

    private ReconciliationService $service;
    private AuditLogger $logger;

    public function __construct( ReconciliationService $service, AuditLogger $logger ) {
        $this->service = $service;
        $this->logger  = $logger;
    }

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/recon/runs', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_runs' ],
                'permission_callback' => [ $this, 'require_manage_paid_adapters' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_and_execute_run' ],
                'permission_callback' => [ $this, 'require_manage_paid_adapters' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/recon/runs/(?P<run_id>[a-z0-9_]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_run' ],
                'permission_callback' => [ $this, 'require_manage_paid_adapters' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/recon/runs/(?P<run_id>[a-z0-9_]+)/rows', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_run_rows' ],
                'permission_callback' => [ $this, 'require_manage_paid_adapters' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/recon/runs/(?P<run_id>[a-z0-9_]+)/rows/(?P<row_id>[a-z0-9_]+)/resolve', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'resolve_row' ],
                'permission_callback' => [ $this, 'require_manage_paid_adapters' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/recon/runs/(?P<run_id>[a-z0-9_]+)/export', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'export_run' ],
                'permission_callback' => [ $this, 'require_manage_paid_adapters' ],
            ],
        ] );
    }

    // ── Route handlers ────────────────────────────────────────────────────────

    public function list_runs( WP_REST_Request $request ): WP_REST_Response {
        $filters = [
            'status'     => sanitize_text_field( $request->get_param( 'status' ) ?? '' ),
            'initiator'  => sanitize_text_field( $request->get_param( 'initiator' ) ?? '' ),
            'date_start' => sanitize_text_field( $request->get_param( 'date_start' ) ?? '' ),
            'date_end'   => sanitize_text_field( $request->get_param( 'date_end' ) ?? '' ),
            'per_page'   => max( 1, (int) ( $request->get_param( 'per_page' ) ?? 25 ) ),
            'paged'      => max( 1, (int) ( $request->get_param( 'paged' ) ?? 1 ) ),
        ];

        $runs = $this->service->list_runs( $filters );
        return rest_ensure_response( [ 'runs' => $runs ] );
    }

    public function create_and_execute_run( WP_REST_Request $request ): WP_REST_Response {
        $sponsor_id = sanitize_text_field( $request->get_param( 'sponsor_id' ) ?? '' );
        $adapters   = (array) ( $request->get_param( 'adapters' ) ?? [] );
        $date_start = sanitize_text_field( $request->get_param( 'date_start' ) ?? '' );
        $date_end   = sanitize_text_field( $request->get_param( 'date_end' ) ?? '' );
        $dry_run    = (bool) ( $request->get_param( 'dry_run' ) ?? false );

        $current_user = wp_get_current_user();
        $initiator    = $current_user->user_login ?: 'api';

        $run = $this->service->start_run( [
            'sponsor_id' => $sponsor_id,
            'adapters'   => $adapters,
            'date_start' => $date_start,
            'date_end'   => $date_end,
            'initiator'  => $initiator,
        ] );

        if ( ! $dry_run ) {
            $run = $this->service->execute_run( $run['run_id'] );
        }

        return rest_ensure_response( [ 'run' => $run ] );
    }

    public function get_run( WP_REST_Request $request ): WP_REST_Response {
        $run_id = sanitize_text_field( $request->get_param( 'run_id' ) ?? '' );
        $run    = $this->service->get_run( $run_id );

        if ( $run === null ) {
            return new WP_REST_Response( [ 'error' => 'Run not found.' ], 404 );
        }

        return rest_ensure_response( [ 'run' => $run ] );
    }

    public function get_run_rows( WP_REST_Request $request ): WP_REST_Response {
        $run_id  = sanitize_text_field( $request->get_param( 'run_id' ) ?? '' );
        $filters = [
            'status'     => sanitize_text_field( $request->get_param( 'status' ) ?? '' ),
            'sponsor_id' => sanitize_text_field( $request->get_param( 'sponsor_id' ) ?? '' ),
            'adapter'    => sanitize_text_field( $request->get_param( 'adapter' ) ?? '' ),
            'per_page'   => max( 1, (int) ( $request->get_param( 'per_page' ) ?? 50 ) ),
            'paged'      => max( 1, (int) ( $request->get_param( 'paged' ) ?? 1 ) ),
        ];

        $rows = $this->service->get_run_rows( $run_id, $filters );
        return rest_ensure_response( [ 'rows' => $rows ] );
    }

    public function resolve_row( WP_REST_Request $request ): WP_REST_Response {
        $run_id = sanitize_text_field( $request->get_param( 'run_id' ) ?? '' );
        $row_id = sanitize_text_field( $request->get_param( 'row_id' ) ?? '' );
        $note   = sanitize_text_field( $request->get_param( 'note' ) ?? '' );

        try {
            $row = $this->service->resolve_row( $row_id, 'resolved', $note, get_current_user_id() );
            return rest_ensure_response( [ 'row' => $row ] );
        } catch ( \InvalidArgumentException $e ) {
            return new WP_REST_Response( [ 'error' => $e->getMessage() ], 400 );
        }
    }

    public function export_run( WP_REST_Request $request ): WP_REST_Response {
        $run_id = sanitize_text_field( $request->get_param( 'run_id' ) ?? '' );
        $export = $this->service->export_run( $run_id, get_current_user_id() );

        // Return CSV inline with appropriate headers.
        $response = new WP_REST_Response( $export['csv'], 200 );
        $response->header( 'Content-Type', 'text/csv' );
        $response->header( 'Content-Disposition', 'attachment; filename="recon_run_' . $run_id . '.csv"' );

        return $response;
    }

    // ── Permission callback ───────────────────────────────────────────────────

    public function require_manage_paid_adapters(): bool {
        if ( current_user_can( CapabilityManager::CAP_MANAGE_PAID_ADAPTERS )
            || current_user_can( 'manage_options' ) ) {
            return true;
        }

        $this->logger->log( 'unauthorized_admin_access', [
            'route'   => '/recon/runs',
            'user_id' => get_current_user_id(),
        ] );

        return false;
    }
}
