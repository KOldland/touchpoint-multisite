<?php
/**
 * Settlement ACK REST Controller
 *
 * Handles external acknowledgement callbacks sent by accounting systems
 * after they have received and verified a settlement ledger delivery.
 *
 *   POST /wp-json/kh-smma/v1/settlement-ack
 *
 * Requires kh_paid_finance capability or manage_options.
 * Logs unauthorized_admin_access on 403 (same pattern as ReconciliationController).
 *
 * @package KH_SMMA\Api
 * @see     docs/paid/accounting_integration_runbook.md
 */

namespace KH_SMMA\Api;

use KH_SMMA\Reconciliation\SettlementDeliveryService;
use KH_SMMA\Services\AuditLogger;

use function add_action;
use function current_user_can;
use function get_current_user_id;
use function register_rest_route;
use function rest_ensure_response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettlementAckController {

    private const NAMESPACE = 'kh-smma/v1';

    /** @var SettlementDeliveryService */
    private $delivery_service;

    /** @var AuditLogger */
    private $logger;

    public function __construct(
        SettlementDeliveryService $delivery_service,
        AuditLogger $logger
    ) {
        $this->delivery_service = $delivery_service;
        $this->logger           = $logger;
    }

    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/settlement-ack', [
            [
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_ack' ),
                'permission_callback' => array( $this, 'require_finance_capability' ),
                'args'                => [
                    'delivery_id' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                    'checksum'    => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                    'notes'       => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                ],
            ],
        ] );
    }

    /**
     * POST /settlement-ack
     *
     * Records an external acknowledgement for a delivery.
     * Optionally verifies the checksum sent by the accounting system
     * against the stored checksum; logs a warning on mismatch but
     * does not block the ACK (the external system is authoritative).
     */
    public function handle_ack( \WP_REST_Request $request ) {
        $delivery_id      = (string) $request->get_param( 'delivery_id' );
        $provided_checksum = (string) $request->get_param( 'checksum' );
        $notes            = (string) $request->get_param( 'notes' );

        if ( '' === $delivery_id ) {
            return new \WP_Error(
                'invalid_params',
                'delivery_id is required.',
                [ 'status' => 400 ]
            );
        }

        try {
            $delivery_row = $this->delivery_service->record_ack( $delivery_id );
        } catch ( \RuntimeException $e ) {
            return new \WP_Error( 'not_found', $e->getMessage(), [ 'status' => 404 ] );
        }

        // ── Optional checksum verification ───────────────────────────────────
        if ( '' !== $provided_checksum
            && ! empty( $delivery_row['checksum'] )
            && $provided_checksum !== $delivery_row['checksum']
        ) {
            $this->logger->log( 'paid_delivery.checksum_mismatch', [
                'object_type' => 'settlement',
                'details'     => [
                    'delivery_id'       => $delivery_id,
                    'stored_checksum'   => $delivery_row['checksum'],
                    'provided_checksum' => $provided_checksum,
                ],
            ] );
        }

        // ── Append notes if provided ─────────────────────────────────────────
        if ( '' !== $notes ) {
            $delivery_row['notes'] = $notes;
        }

        return rest_ensure_response( $delivery_row );
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
                'endpoint' => 'kh-smma/v1/settlement-ack',
                'user_id'  => get_current_user_id(),
            ],
        ] );

        return false;
    }
}
