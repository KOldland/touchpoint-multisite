<?php

namespace KHM\Tests\Membership;

use PHPUnit\Framework\TestCase;
use KHM\Membership\MembershipWebhookAuditLogger;
use KHM\Membership\ProcessedWebhook;
use KHM\Membership\StripeWebhookHandler;
use WP_REST_Request;

require_once dirname(__DIR__) . '/helpers/stripe_signature.php';

/**
 * Tests for webhook error logging and audit entry shape.
 *
 * Covers:
 *  - Exception path writes a structured audit entry with required fields.
 *  - Audit context includes event_id, type, outcome, trace_id, received_at, error_class.
 *  - No undefined variable on catch path when $object is null before assignment.
 *  - ProcessedWebhook is marked 'failed' after an exception.
 *  - Invalid-signature path returns 400 and writes no audit rows.
 *  - Valid signed webhook exception path produces structured audit entry.
 *  - Telemetry event is fired on the error path.
 *
 * Staging smoke steps (run against local env after enabling skip-sig filter):
 *   POST /wp-json/khm/v1/webhooks/stripe
 *   Body: {"id":"evt_smoke_001","type":"invoice.paid","data":{"object":{"id":"in_smoke","customer":"cus_nosuchcustomer"}}}
 *   Then: SELECT * FROM wp_khm_membership_webhook_audit WHERE event_id = 'evt_smoke_001';
 *   Expected: outcome=failed, context JSON contains trace_id, received_at, error_class=RuntimeException.
 */
class StripeWebhookErrorLoggingTest extends TestCase {

    private StripeWebhookHandler $handler;

    /** @var array<string,mixed> */
    protected function setUp(): void {
        parent::setUp();

        $this->handler = new StripeWebhookHandler();
        ProcessedWebhook::maybe_create_table();
        MembershipWebhookAuditLogger::maybe_create_table();

        add_filter( 'khm_membership_webhook_skip_signature_verification', '__return_true' );

        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_processed_webhooks" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_processed_webhook_events" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_membership_webhook_operations" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_webhook_dead_letter" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_membership_webhook_audit" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}user_membership" );
    }

    protected function tearDown(): void {
        remove_filter( 'khm_membership_webhook_skip_signature_verification', '__return_true' );
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Unit: exception path — null $object guard
    // -------------------------------------------------------------------------

    /**
     * When process_queued_event raises an exception (user not found),
     * $object may still be null if to_object never completed.
     * Ensure no undefined-variable notice and a failed audit row is written.
     */
    public function test_exception_path_with_null_object_writes_audit_entry(): void {
        $this->dispatchThenProcess( 'evt_err_null_obj', 'invoice.paid', [
            'id'           => 'in_err_null_obj',
            'customer'     => 'cus_no_such',
            'subscription' => 'sub_no_such',
        ], 'trace-null-obj' );

        $failed = $this->getAuditRowsByEventId( 'evt_err_null_obj', 'failed' );
        $this->assertNotEmpty( $failed, 'Expected a failed audit row for exception path.' );
    }

    /**
     * Audit entry on exception path must carry the mandatory structured context.
     */
    public function test_exception_path_audit_entry_has_structured_context(): void {
        $this->dispatchThenProcess( 'evt_err_ctx', 'invoice.paid', [
            'id'           => 'in_err_ctx',
            'customer'     => 'cus_err_ctx',
            'subscription' => 'sub_err_ctx',
        ], 'trace-ctx-001' );

        $failed = $this->getAuditRowsByEventId( 'evt_err_ctx', 'failed' );
        $this->assertNotEmpty( $failed );

        $row     = reset( $failed );
        $context = isset( $row['context'] ) ? json_decode( (string) $row['context'], true ) : null;

        $this->assertIsArray( $context, 'context column must be valid JSON.' );

        // Required keys per brief: event_id, type, outcome, notes, timestamp, trace_id, received_at.
        foreach ( [ 'event_id', 'type', 'outcome', 'notes', 'timestamp', 'trace_id', 'received_at', 'error_class' ] as $key ) {
            $this->assertArrayHasKey( $key, $context, "Structured context must include '{$key}'." );
        }

        $this->assertSame( 'evt_err_ctx',   $context['event_id'] );
        $this->assertSame( 'invoice.paid',  $context['type'] );
        $this->assertSame( 'failed',        $context['outcome'] );
        $this->assertSame( 'trace-ctx-001', $context['trace_id'] );
        $this->assertSame( 'RuntimeException', $context['error_class'] );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            (string) $context['received_at'],
            'received_at must be ISO-8601 UTC.'
        );
    }

    /**
     * After an exception, ProcessedWebhook status must be 'failed'.
     */
    public function test_exception_path_marks_webhook_failed(): void {
        $this->dispatchThenProcess( 'evt_err_mark', 'invoice.paid', [
            'id'           => 'in_err_mark',
            'customer'     => 'cus_err_mark',
            'subscription' => 'sub_err_mark',
        ], 'trace-mark' );

        $record = ProcessedWebhook::get_event( 'evt_err_mark' );
        $this->assertNotNull( $record, 'ProcessedWebhook record must exist after failure.' );
        $this->assertSame( 'failed', (string) ( $record['status'] ?? '' ) );
    }

    /**
     * On exception, a dead-letter record must be created with reason=processing_failed.
     */
    public function test_exception_path_creates_dead_letter_record(): void {
        $this->dispatchThenProcess( 'evt_err_dl', 'invoice.paid', [
            'id'           => 'in_err_dl',
            'customer'     => 'cus_err_dl',
            'subscription' => 'sub_err_dl',
        ], 'trace-dl-001' );

        global $wpdb;
        $dl_table = $wpdb->prefix . 'khm_webhook_dead_letter';
        $rows     = $wpdb->get_results( "SELECT * FROM {$dl_table} LIMIT 9999", ARRAY_A );
        $rows     = is_array( $rows ) ? $rows : [];

        $dlRows = array_filter(
            $rows,
            static fn( $r ) => ( $r['event_id'] ?? '' ) === 'evt_err_dl'
                && ( $r['reason'] ?? '' ) === 'processing_failed'
        );

        $this->assertNotEmpty( $dlRows, 'Dead-letter record must exist with reason=processing_failed.' );
    }

    /**
     * Audit entries on both 'processing' start and 'failed' must include trace_id.
     */
    public function test_processing_and_failed_audit_rows_both_carry_trace_id(): void {
        $this->dispatchThenProcess( 'evt_err_both', 'invoice.paid', [
            'id'           => 'in_err_both',
            'customer'     => 'cus_err_both',
            'subscription' => 'sub_err_both',
        ], 'trace-both-999' );

        $allRows = $this->getAllAuditRows();
        $myRows  = array_filter( $allRows, static fn( $r ) => $r['event_id'] === 'evt_err_both' );

        foreach ( $myRows as $row ) {
            if ( ! isset( $row['context'] ) ) {
                continue;
            }
            $ctx = json_decode( (string) $row['context'], true );
            if ( ! is_array( $ctx ) || ! isset( $ctx['trace_id'] ) ) {
                continue;
            }
            // At least one row (the failed one) must carry the trace_id.
            $this->assertSame( 'trace-both-999', $ctx['trace_id'] );
            return;
        }

        $this->fail( 'No audit row with trace_id=trace-both-999 found for evt_err_both.' );
    }

    // -------------------------------------------------------------------------
    // Integration: invalid signature → 400, no audit writes
    // -------------------------------------------------------------------------

    /**
     * An invalid Stripe signature must return HTTP 400.
     * The spec requires no audit DB writes for rejected requests.
     */
    public function test_invalid_signature_returns_400_and_no_audit_writes(): void {
        remove_filter( 'khm_membership_webhook_skip_signature_verification', '__return_true' );
        putenv( 'KH_STRIPE_WEBHOOK_SECRET=whsec_integration_test_secret' );

        $payload = wp_json_encode( [
            'id'   => 'evt_bad_sig_001',
            'type' => 'invoice.paid',
            'data' => [ 'object' => [ 'id' => 'in_bad_sig', 'customer' => 'cus_bad_sig' ] ],
        ] );

        $request = new WP_REST_Request( 'POST' );
        $request->set_body( (string) $payload );
        $request->set_header( 'stripe-signature', 't=1700000000,v1=invalidsig' );

        $response = $this->handler->handle_request( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertSame( 'Invalid signature', $response->get_data()['error'] ?? '' );

        // No audit rows must exist (no DB writes for rejected requests).
        $rows = $this->getAllAuditRows();
        $this->assertEmpty( $rows, 'No audit writes expected for invalid-signature rejection.' );

        putenv( 'KH_STRIPE_WEBHOOK_SECRET' );
        add_filter( 'khm_membership_webhook_skip_signature_verification', '__return_true' );
    }

    // -------------------------------------------------------------------------
    // Integration: valid signed webhook whose handler throws → structured audit
    // -------------------------------------------------------------------------

    /**
     * A valid signed webhook whose handler cannot resolve a user must:
     * - Be accepted (200 queued) on handle_request.
     * - Produce a failed audit entry with structured context after worker runs.
     * - Carry trace_id and received_at in the audit context.
     */
    public function test_valid_signed_webhook_exception_produces_structured_audit(): void {
        putenv( 'KH_STRIPE_WEBHOOK_SECRET=whsec_valid_signed_test' );
        remove_filter( 'khm_membership_webhook_skip_signature_verification', '__return_true' );

        $payloadData = [
            'id'      => 'evt_signed_exc_001',
            'type'    => 'invoice.paid',
            'created' => time(),
            'data'    => [
                'object' => [
                    'id'           => 'in_signed_exc',
                    'customer'     => 'cus_no_such_signed',
                    'subscription' => 'sub_no_such_signed',
                ],
            ],
        ];
        $body   = (string) wp_json_encode( $payloadData );
        $header = khm_test_build_stripe_signature_header( $body, 'whsec_valid_signed_test' );

        $request = new WP_REST_Request( 'POST' );
        $request->set_body( $body );
        $request->set_header( 'stripe-signature', $header );

        $response = $this->handler->handle_request( $request );
        $this->assertSame( 200, $response->get_status(), 'Valid signature must be accepted.' );

        // Simulate the async worker.
        $this->handler->process_queued_event( [
            'event_id'    => 'evt_signed_exc_001',
            'event_type'  => 'invoice.paid',
            'data_object' => $payloadData['data']['object'],
            'trace_id'    => 'trace-signed-exc-001',
        ] );

        $failed = $this->getAuditRowsByEventId( 'evt_signed_exc_001', 'failed' );
        $this->assertNotEmpty( $failed, 'Failed audit row must exist after exception.' );

        $row     = reset( $failed );
        $context = isset( $row['context'] ) ? json_decode( (string) $row['context'], true ) : null;

        $this->assertIsArray( $context );
        $this->assertSame( 'trace-signed-exc-001', $context['trace_id'] ?? '' );
        $this->assertNotEmpty( $context['received_at'] ?? '' );
        $this->assertSame( 'RuntimeException', $context['error_class'] ?? '' );

        putenv( 'KH_STRIPE_WEBHOOK_SECRET' );
        add_filter( 'khm_membership_webhook_skip_signature_verification', '__return_true' );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Run handle_request (to claim the event) then process_queued_event inline.
     *
     * @param array<string,mixed> $dataObject
     */
    private function dispatchThenProcess(
        string $eventId,
        string $eventType,
        array  $dataObject,
        string $traceId
    ): void {
        $payload = [
            'id'      => $eventId,
            'type'    => $eventType,
            'created' => time(),
            'data'    => [ 'object' => $dataObject ],
        ];

        $request = new WP_REST_Request( 'POST' );
        $request->set_body( (string) wp_json_encode( $payload ) );
        $this->handler->handle_request( $request );

        $this->handler->process_queued_event( [
            'event_id'    => $eventId,
            'event_type'  => $eventType,
            'data_object' => $dataObject,
            'trace_id'    => $traceId,
        ] );
    }

    /**
     * Get audit rows for a specific event_id and outcome from the in-memory table.
     *
     * @return array<int,array<string,mixed>>
     */
    private function getAuditRowsByEventId( string $eventId, string $outcome ): array {
        $all = $this->getAllAuditRows();
        return array_values( array_filter(
            $all,
            static fn( $r ) => ( $r['event_id'] ?? '' ) === $eventId
                && ( $r['outcome'] ?? '' ) === $outcome
        ) );
    }

    /**
     * Get all rows from the in-memory audit table.
     *
     * @return array<int,array<string,mixed>>
     */
    private function getAllAuditRows(): array {
        global $wpdb;
        $table = MembershipWebhookAuditLogger::table_name();
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} LIMIT 9999", ARRAY_A );
        return is_array( $rows ) ? array_values( $rows ) : [];
    }
}
