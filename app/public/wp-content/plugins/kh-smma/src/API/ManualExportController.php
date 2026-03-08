<?php
declare( strict_types=1 );

namespace KH_SMMA\API;

use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Security\CapabilityManager;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\Card1StateStore;
use KH_SMMA\Services\ExportBundleService;
use WP_REST_Request;
use WP_REST_Response;

use function add_action;
use function current_user_can;
use function do_action;
use function get_current_user_id;
use function get_option;
use function gmdate;
use function register_rest_route;
use function rest_ensure_response;
use function rest_url;
use function sanitize_text_field;
use function wp_generate_uuid4;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PAID — REST controller for secure manual export bundle downloads.
 *
 * ManualExportAdapter stores manifest bundles in WP options at
 * `kh_paid_bundle_{manifest_id}`. This controller exposes a capability-gated
 * GET endpoint so finance staff can retrieve bundles without direct DB access.
 *
 * Route (under kh-smma/v1):
 *   GET /manual-export/{manifest_id} → download_bundle()
 *
 * Requires: manage_paid_adapters || manage_options
 */
class ManualExportController {

    private const NAMESPACE = 'kh-smma/v1';

    private AuditLogger $logger;
    private ?Card1StateStore $state_store;
    private ?ExportBundleService $bundle_service;
    private ?ManualExportAdapter $manual_adapter;

    public function __construct(
        AuditLogger $logger,
        ?Card1StateStore $state_store = null,
        ?ExportBundleService $bundle_service = null,
        ?ManualExportAdapter $manual_adapter = null
    ) {
        $this->logger = $logger;
        $this->state_store = $state_store ?? ( class_exists( Card1StateStore::class ) ? new Card1StateStore() : null );
        $this->bundle_service = $bundle_service ?? ( class_exists( ExportBundleService::class ) ? new ExportBundleService() : null );
        $this->manual_adapter = $manual_adapter;
        if ( null === $this->manual_adapter && null !== $this->bundle_service && class_exists( ManualExportAdapter::class ) ) {
            $this->manual_adapter = new ManualExportAdapter( $logger, null, $this->bundle_service );
        }
    }

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/manual-export/(?P<manifest_id>[a-zA-Z0-9_\-]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'download_bundle' ],
                'permission_callback' => [ $this, 'require_manage_paid_adapters' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/manual-export/schedule/(?P<schedule_id>[A-Za-z0-9_\-]+)/bundle', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_schedule_bundle' ],
                'permission_callback' => [ $this, 'require_export_access' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/manual-export/schedule/(?P<schedule_id>[A-Za-z0-9_\-]+)/download', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'download_schedule_bundle' ],
                'permission_callback' => [ $this, 'require_export_access' ],
            ],
        ] );
    }

    // ── Route handler ─────────────────────────────────────────────────────────

    /**
     * GET /manual-export/{manifest_id}
     *
     * Returns the stored bundle JSON for the given manifest_id.
     * Returns 404 if the bundle has not been produced yet (execute() not called).
     */
    public function download_bundle( WP_REST_Request $request ) {
        $manifest_id = sanitize_text_field( $request->get_param( 'manifest_id' ) ?? '' );

        if ( '' === $manifest_id ) {
            return new WP_REST_Response( [ 'error' => 'manifest_id is required.' ], 400 );
        }

        $bundle = get_option( 'kh_paid_bundle_' . $manifest_id );

        if ( ! $bundle ) {
            return new WP_REST_Response( [ 'error' => 'Bundle not found for manifest_id: ' . $manifest_id ], 404 );
        }

        $this->logger->log( 'paid_manual_export.downloaded', [
            'object_type' => 'manifest',
            'details'     => [
                'manifest_id' => $manifest_id,
                'user_id'     => get_current_user_id(),
            ],
        ] );

        return rest_ensure_response( $bundle );
    }

    public function create_schedule_bundle( WP_REST_Request $request ) {
        $schedule_id = sanitize_text_field( (string) $request->get_param( 'schedule_id' ) );
        if ( null === $this->state_store || null === $this->manual_adapter ) {
            return new WP_REST_Response( array( 'error' => 'manual export service unavailable' ), 500 );
        }
        $schedule = $this->state_store->get_schedule( $schedule_id );
        if ( empty( $schedule ) ) {
            return new WP_REST_Response( array( 'error' => 'schedule not found' ), 404 );
        }

        $status_check = $this->validate_export_allowed( $schedule );
        if ( $status_check instanceof WP_REST_Response ) {
            return $status_check;
        }

        $variant = $this->state_store->get_variant( (string) ( $schedule['variant_id'] ?? '' ) );
        $bundle = $this->manual_adapter->create_schedule_export_bundle( $schedule, $variant, array() );
        $trace_id = $this->resolve_trace_id( $request );

        $this->logger->log( 'export.bundle.created', array(
            'object_type' => 'schedule',
            'object_id' => 0,
            'details' => array(
                'schedule_id' => $schedule_id,
                'variant_id' => (string) ( $schedule['variant_id'] ?? '' ),
                'bundle_size' => (int) ( $bundle['bundle_size'] ?? 0 ),
            ),
            'user_id' => get_current_user_id(),
        ) );
        do_action( 'kh_smma_telemetry_event', 'export.bundle.created', array(
            'trace_id' => $trace_id,
            'schedule_id' => $schedule_id,
            'variant_id' => (string) ( $schedule['variant_id'] ?? '' ),
            'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'bundle_size' => (int) ( $bundle['bundle_size'] ?? 0 ),
        ) );

        return rest_ensure_response( array(
            'status' => 'created',
            'schedule_id' => $schedule_id,
            'variant_id' => (string) ( $schedule['variant_id'] ?? '' ),
            'estimated_spend' => (float) ( $bundle['manifest']['estimated_spend'] ?? 0.0 ),
            'estimated_ops' => (int) ( $bundle['manifest']['estimated_ops'] ?? 1 ),
            'download_endpoint' => rest_url( self::NAMESPACE . '/manual-export/schedule/' . $schedule_id . '/download' ),
        ) );
    }

    public function download_schedule_bundle( WP_REST_Request $request ) {
        $schedule_id = sanitize_text_field( (string) $request->get_param( 'schedule_id' ) );
        if ( null === $this->state_store || null === $this->bundle_service ) {
            return new WP_REST_Response( array( 'error' => 'manual export service unavailable' ), 500 );
        }
        $schedule = $this->state_store->get_schedule( $schedule_id );
        if ( empty( $schedule ) ) {
            return new WP_REST_Response( array( 'error' => 'schedule not found' ), 404 );
        }

        $status_check = $this->validate_export_allowed( $schedule );
        if ( $status_check instanceof WP_REST_Response ) {
            return $status_check;
        }

        $bundle = $this->bundle_service->get_bundle( $schedule_id );
        if ( empty( $bundle ) ) {
            return new WP_REST_Response( array( 'error' => 'bundle not found' ), 404 );
        }

        $trace_id = $this->resolve_trace_id( $request );
        $this->logger->log( 'export.bundle.downloaded', array(
            'object_type' => 'schedule',
            'object_id' => 0,
            'details' => array(
                'schedule_id' => $schedule_id,
                'variant_id' => (string) ( $bundle['variant_id'] ?? '' ),
                'bundle_size' => (int) ( $bundle['bundle_size'] ?? 0 ),
            ),
            'user_id' => get_current_user_id(),
        ) );
        do_action( 'kh_smma_telemetry_event', 'export.bundle.downloaded', array(
            'trace_id' => $trace_id,
            'schedule_id' => $schedule_id,
            'variant_id' => (string) ( $bundle['variant_id'] ?? '' ),
            'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'bundle_size' => (int) ( $bundle['bundle_size'] ?? 0 ),
        ) );

        $response = new WP_REST_Response( array(
            'schedule_id' => $schedule_id,
            'variant_id' => (string) ( $bundle['variant_id'] ?? '' ),
            'filename' => (string) ( $bundle['file_name'] ?? '' ),
            'content_type' => 'application/zip',
            'bundle_size' => (int) ( $bundle['bundle_size'] ?? 0 ),
            'manifest' => $bundle['manifest'] ?? array(),
        ), 200 );
        if ( method_exists( $response, 'header' ) ) {
            $response->header( 'Content-Type', 'application/zip' );
            $response->header( 'Content-Disposition', 'attachment; filename="' . (string) ( $bundle['file_name'] ?? 'schedule_export.zip' ) . '"' );
        }
        return $response;
    }

    // ── Permission callback ───────────────────────────────────────────────────

    public function require_manage_paid_adapters(): bool {
        if ( current_user_can( CapabilityManager::CAP_MANAGE_PAID_ADAPTERS )
            || current_user_can( 'manage_options' ) ) {
            return true;
        }

        $this->logger->log( 'unauthorized_admin_access', [
            'object_type' => 'rest_endpoint',
            'details'     => [
                'endpoint' => 'kh-smma/v1/manual-export',
                'user_id'  => get_current_user_id(),
            ],
        ] );

        return false;
    }

    public function require_export_access(): bool {
        return current_user_can( 'edit_posts' )
            || current_user_can( CapabilityManager::CAP_MANAGE_PAID_ADAPTERS )
            || current_user_can( 'manage_options' );
    }

    private function validate_export_allowed( array $schedule ): ?WP_REST_Response {
        $approval = strtolower( (string) ( $schedule['approval_status'] ?? '' ) );
        $compliance = strtoupper( (string) ( $schedule['compliance_status'] ?? 'OK' ) );
        if ( 'auto_approved' === $approval ) {
            $approval = 'approved';
        }
        if ( 'denied' === $approval ) {
            $approval = 'rejected';
        }

        if ( 'approved' !== $approval || 'FAIL' === $compliance ) {
            return new WP_REST_Response( array(
                'status' => 'blocked',
                'reason' => 'approval_required',
                'approval_status' => '' !== $approval ? $approval : 'pending',
                'error' => 'APPROVAL_REQUIRED',
                'message' => 'Schedule requires sponsor approval before dispatch.',
            ), 409 );
        }

        return null;
    }

    private function resolve_trace_id( WP_REST_Request $request ): string {
        $header = sanitize_text_field( (string) $request->get_header( 'X-Trace-Id' ) );
        return '' !== $header ? $header : wp_generate_uuid4();
    }
}
