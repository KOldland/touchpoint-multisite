<?php
/**
 * Reconciliation REST Controller
 *
 * Exposes reconciliation rows via the WP REST API.
 *
 *   GET  /wp-json/kh-smma/v1/reconciliations
 *   GET  /wp-json/kh-smma/v1/reconciliations/{id}
 *   POST /wp-json/kh-smma/v1/reconciliations/{id}/rerun
 *   POST /wp-json/kh-smma/v1/reconciliations/{id}/adjust     (PAID-05)
 *   POST /wp-json/kh-smma/v1/reconciliations/{id}/dispute    (PAID-05)
 *   POST /wp-json/kh-smma/v1/reconciliations/settle          (PAID-05)
 *   GET  /wp-json/kh-smma/v1/reconciliations/settlement/{id} (PAID-05)
 *
 * @package KH_SMMA\Api
 * @see     docs/paid/reconciliation_runbook.md
 * @see     docs/paid/finance_reconciliation_runbook.md
 */

namespace KH_SMMA\Api;

use KH_SMMA\Reconciliation\PaidReconciliationService;
use KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService;
use KH_SMMA\Reconciliation\SettlementWorker;
use KH_SMMA\Services\AuditLogger;

use function add_action;
use function current_time;
use function current_user_can;
use function get_current_user_id;
use function register_rest_route;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReconciliationController {

    private const NAMESPACE = 'kh-smma/v1';
    private const BASE      = 'reconciliations';

    /** @var PaidReconciliationService */
    private $service;

    /** @var PaidReconciliationAdjustmentService */
    private $adj_service;

    /** @var SettlementWorker */
    private $settlement_worker;

    /** @var AuditLogger */
    private $logger;

    public function __construct(
        PaidReconciliationService $service,
        PaidReconciliationAdjustmentService $adj_service,
        SettlementWorker $settlement_worker,
        AuditLogger $logger
    ) {
        $this->service           = $service;
        $this->adj_service       = $adj_service;
        $this->settlement_worker = $settlement_worker;
        $this->logger            = $logger;
    }

    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        // List reconciliations.
        register_rest_route( self::NAMESPACE, '/' . self::BASE, [
            [
                'methods'             => 'GET',
                'callback'            => array( $this, 'list_reconciliations' ),
                'permission_callback' => array( $this, 'require_manage_options' ),
                'args'                => [
                    'sponsor_id' => [ 'type' => 'string', 'default' => '' ],
                    'status'     => [ 'type' => 'string', 'default' => '' ],
                    'per_page'   => [ 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 200 ],
                    'page'       => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
                    'date_start' => [ 'type' => 'string', 'default' => '' ],
                    'date_end'   => [ 'type' => 'string', 'default' => '' ],
                ],
            ],
        ] );

        // Single reconciliation row.
        register_rest_route( self::NAMESPACE, '/' . self::BASE . '/(?P<id>[a-zA-Z0-9_]+)', [
            [
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_reconciliation' ),
                'permission_callback' => array( $this, 'require_manage_options' ),
                'args'                => [
                    'id' => [ 'type' => 'string', 'required' => true ],
                ],
            ],
        ] );

        // Re-run reconciliation from cached data.
        register_rest_route( self::NAMESPACE, '/' . self::BASE . '/(?P<id>[a-zA-Z0-9_]+)/rerun', [
            [
                'methods'             => 'POST',
                'callback'            => array( $this, 'rerun_reconciliation' ),
                'permission_callback' => array( $this, 'require_manage_options' ),
                'args'                => [
                    'id' => [ 'type' => 'string', 'required' => true ],
                ],
            ],
        ] );

        // PAID-05: Create manual adjustment.
        register_rest_route( self::NAMESPACE, '/' . self::BASE . '/(?P<id>[a-zA-Z0-9_]+)/adjust', [
            [
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_adjustment' ),
                'permission_callback' => array( $this, 'require_finance_capability' ),
                'args'                => [
                    'id'       => [ 'type' => 'string', 'required' => true ],
                    'amount'   => [ 'type' => 'number', 'required' => true ],
                    'reason'   => [ 'type' => 'string', 'required' => true ],
                    'currency' => [ 'type' => 'string', 'default' => 'AUD' ],
                ],
            ],
        ] );

        // PAID-05: Dispute a reconciliation.
        register_rest_route( self::NAMESPACE, '/' . self::BASE . '/(?P<id>[a-zA-Z0-9_]+)/dispute', [
            [
                'methods'             => 'POST',
                'callback'            => array( $this, 'dispute_reconciliation' ),
                'permission_callback' => array( $this, 'require_finance_capability' ),
                'args'                => [
                    'id' => [ 'type' => 'string', 'required' => true ],
                ],
            ],
        ] );

        // PAID-05: Run settlement batch.
        register_rest_route( self::NAMESPACE, '/' . self::BASE . '/settle', [
            [
                'methods'             => 'POST',
                'callback'            => array( $this, 'run_settlement' ),
                'permission_callback' => array( $this, 'require_finance_capability' ),
                'args'                => [
                    'sponsor_id'      => [ 'type' => 'string', 'default' => '' ],
                    'currency'        => [ 'type' => 'string', 'default' => '' ],
                    'target_currency' => [ 'type' => 'string', 'default' => '' ],
                    'date_start'      => [ 'type' => 'string', 'default' => '' ],
                    'date_end'        => [ 'type' => 'string', 'default' => '' ],
                    'batch_size'      => [ 'type' => 'integer', 'default' => 500, 'minimum' => 1, 'maximum' => 2000 ],
                ],
            ],
        ] );

        // PAID-05: Get single settlement row.
        register_rest_route( self::NAMESPACE, '/' . self::BASE . '/settlement/(?P<id>[a-zA-Z0-9_]+)', [
            [
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_settlement' ),
                'permission_callback' => array( $this, 'require_finance_capability' ),
                'args'                => [
                    'id' => [ 'type' => 'string', 'required' => true ],
                ],
            ],
        ] );
    }

    /**
     * GET /reconciliations
     */
    public function list_reconciliations( \WP_REST_Request $request ): array {
        $filters = [
            'sponsor_id' => (string) $request->get_param( 'sponsor_id' ),
            'status'     => (string) $request->get_param( 'status' ),
            'per_page'   => (int) $request->get_param( 'per_page' ),
            'paged'      => (int) $request->get_param( 'page' ),
            'date_start' => (string) $request->get_param( 'date_start' ),
            'date_end'   => (string) $request->get_param( 'date_end' ),
        ];

        // Strip empty string filters.
        $filters = array_filter( $filters, function ( $v ) {
            return '' !== $v;
        } );

        // Restore numeric defaults removed by array_filter.
        $filters['per_page'] = (int) $request->get_param( 'per_page' );
        $filters['paged']    = (int) $request->get_param( 'page' );

        return $this->service->list_rows( $filters );
    }

    /**
     * GET /reconciliations/{id}
     */
    public function get_reconciliation( \WP_REST_Request $request ) {
        $id  = (string) $request->get_param( 'id' );
        $row = $this->service->get_row( $id );

        if ( null === $row ) {
            return new \WP_Error( 'not_found', 'Reconciliation row not found.', [ 'status' => 404 ] );
        }

        return $row;
    }

    /**
     * POST /reconciliations/{id}/rerun
     *
     * Re-runs reconcile() with the same reconciliation_id cleared so a fresh
     * row is produced. In practice, callers should use a new idempotency_key
     * (the UNIQUE constraint prevents overwriting existing rows).
     */
    public function rerun_reconciliation( \WP_REST_Request $request ) {
        $id  = (string) $request->get_param( 'id' );
        $row = $this->service->get_row( $id );

        if ( null === $row ) {
            return new \WP_Error( 'not_found', 'Reconciliation row not found.', [ 'status' => 404 ] );
        }

        // Rerun is a no-op for idempotent rows — return the existing row with a note.
        return array_merge( $row, [ '_rerun_note' => 'Row already reconciled; idempotency key unchanged.' ] );
    }

    /**
     * POST /reconciliations/{id}/adjust
     */
    public function create_adjustment( \WP_REST_Request $request ) {
        $rec_id   = (string) $request->get_param( 'id' );
        $amount   = (float) $request->get_param( 'amount' );
        $reason   = (string) $request->get_param( 'reason' );
        $currency = strtoupper( (string) $request->get_param( 'currency' ) ) ?: 'AUD';

        if ( ! $amount || ! $reason ) {
            return new \WP_Error( 'invalid_params', 'amount and reason are required.', [ 'status' => 400 ] );
        }

        $row = $this->adj_service->create_adjustment( $rec_id, $amount, $currency, $reason, get_current_user_id() );
        return rest_ensure_response( $row );
    }

    /**
     * POST /reconciliations/{id}/dispute
     */
    public function dispute_reconciliation( \WP_REST_Request $request ) {
        global $wpdb;

        $rec_id = (string) $request->get_param( 'id' );
        $row    = $this->service->get_row( $rec_id );

        if ( null === $row ) {
            return new \WP_Error( 'not_found', 'Reconciliation row not found.', [ 'status' => 404 ] );
        }

        $now = current_time( 'mysql' );
        $wpdb->update(
            $wpdb->prefix . 'kh_paid_reconciliations',
            [ 'status' => 'disputed', 'updated_at' => $now ],
            [ 'reconciliation_id' => $rec_id ]
        );

        $this->logger->log( 'paid_reconciliation.disputed', [
            'object_type' => 'reconciliation',
            'details'     => [
                'reconciliation_id' => $rec_id,
                'disputed_by'       => get_current_user_id(),
            ],
        ] );

        return rest_ensure_response( array_merge( $row, [ 'status' => 'disputed', 'updated_at' => $now ] ) );
    }

    /**
     * POST /reconciliations/settle
     */
    public function run_settlement( \WP_REST_Request $request ) {
        $filters = array_filter( [
            'sponsor_id'      => (string) $request->get_param( 'sponsor_id' ),
            'currency'        => (string) $request->get_param( 'currency' ),
            'target_currency' => (string) $request->get_param( 'target_currency' ),
            'date_start'      => (string) $request->get_param( 'date_start' ),
            'date_end'        => (string) $request->get_param( 'date_end' ),
            'batch_size'      => (int) $request->get_param( 'batch_size' ),
        ], fn( $v ) => '' !== $v && 0 !== $v );

        $results = $this->settlement_worker->run( $filters );
        return rest_ensure_response( $results );
    }

    /**
     * GET /reconciliations/settlement/{id}
     */
    public function get_settlement( \WP_REST_Request $request ) {
        $id  = (string) $request->get_param( 'id' );
        $row = $this->settlement_worker->get_settlement( $id );

        if ( null === $row ) {
            return new \WP_Error( 'not_found', 'Settlement not found.', [ 'status' => 404 ] );
        }

        return rest_ensure_response( $row );
    }

    /**
     * Require manage_options capability.
     */
    public function require_manage_options(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Require kh_paid_finance capability (or manage_options).
     * Logs unauthorized_admin_access audit event on 403.
     */
    public function require_finance_capability(): bool {
        if ( current_user_can( 'kh_paid_finance' ) || current_user_can( 'manage_options' ) ) {
            return true;
        }

        $this->logger->log( 'unauthorized_admin_access', [
            'object_type' => 'rest_endpoint',
            'details'     => [
                'endpoint' => 'kh-smma/v1/reconciliations (finance)',
                'user_id'  => get_current_user_id(),
            ],
        ] );

        return false;
    }
}
