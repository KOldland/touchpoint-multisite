<?php
namespace KH_SMMA\Tests\Telemetry;

use KH_SMMA\Telemetry\TelemetryTraceService;
use KH_SMMA\Telemetry\TelemetryPayloadSanitizer;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * OBS-08: Unit tests for TelemetryTraceService.
 *
 * Covers:
 *  - get_trace_timeline() returns events in chronological order
 *  - Empty / blank trace_id returns empty array
 *  - Unknown trace_id returns empty array
 *  - find_by_schedule_id() filters events correctly
 *  - find_by_variant_id() filters events correctly
 *  - PII masking applied when sanitizer is present
 *  - Without sanitizer, raw payload passes through
 *  - extract_key_fields() returns expected diagnostic keys
 *  - Fixture case parity: all three trace_debug_cases cases
 */
class TelemetryTraceServiceTest extends TestCase {

    /** @var AuditLogger|\PHPUnit\Framework\MockObject\MockObject */
    private $audit;

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['kh_test_filters']    = [];
        $GLOBALS['kh_test_db_inserts'] = [];

        $this->audit = $this->getMockBuilder( AuditLogger::class )
                            ->setConstructorArgs( [ new \wpdb() ] )
                            ->onlyMethods( [ 'get_events_by_trace', 'get_recent_telemetry_events' ] )
                            ->getMock();
    }

    // -------------------------------------------------------------------------
    // get_trace_timeline() — basic lookup
    // -------------------------------------------------------------------------

    public function test_get_trace_timeline_returns_ordered_events(): void {
        $rows = $this->make_rows( [
            [ 'trace_id' => 'tr-001', 'event_name' => 'generate.request',  'timestamp' => 1000, 'created_at' => '2026-03-06 10:00:01', 'payload' => [] ],
            [ 'trace_id' => 'tr-001', 'event_name' => 'compliance.check',  'timestamp' => 1001, 'created_at' => '2026-03-06 10:00:02', 'payload' => [] ],
            [ 'trace_id' => 'tr-001', 'event_name' => 'schedule.dispatch', 'timestamp' => 1010, 'created_at' => '2026-03-06 10:00:10', 'payload' => [] ],
        ] );

        $this->audit->method( 'get_events_by_trace' )
                    ->with( 'tr-001' )
                    ->willReturn( $rows );

        $service  = new TelemetryTraceService( $this->audit );
        $timeline = $service->get_trace_timeline( 'tr-001' );

        $this->assertCount( 3, $timeline );
        $this->assertSame( 'generate.request',  $timeline[0]['event_name'] );
        $this->assertSame( 'compliance.check',  $timeline[1]['event_name'] );
        $this->assertSame( 'schedule.dispatch', $timeline[2]['event_name'] );
    }

    public function test_get_trace_timeline_returns_correct_field_map(): void {
        $rows = $this->make_rows( [
            [ 'trace_id' => 'tr-abc', 'event_name' => 'variant.edit', 'timestamp' => 2000, 'created_at' => '2026-03-06 11:00:00', 'payload' => [ 'variant_id' => 'v-1', 'editor_id' => 3 ] ],
        ] );

        $this->audit->method( 'get_events_by_trace' )->willReturn( $rows );

        $service  = new TelemetryTraceService( $this->audit );
        $timeline = $service->get_trace_timeline( 'tr-abc' );

        $this->assertSame( 'tr-abc',       $timeline[0]['trace_id'] );
        $this->assertSame( 'variant.edit', $timeline[0]['event_name'] );
        $this->assertSame( 2000,           $timeline[0]['timestamp'] );
        $this->assertSame( '2026-03-06 11:00:00', $timeline[0]['created_at'] );
        $this->assertSame( 'v-1',          $timeline[0]['payload']['variant_id'] );
    }

    public function test_get_trace_timeline_empty_trace_id_returns_empty(): void {
        $service = new TelemetryTraceService( $this->audit );
        $this->assertSame( [], $service->get_trace_timeline( '' ) );
        $this->assertSame( [], $service->get_trace_timeline( '   ' ) );
    }

    public function test_get_trace_timeline_unknown_trace_returns_empty(): void {
        $this->audit->method( 'get_events_by_trace' )->willReturn( [] );

        $service = new TelemetryTraceService( $this->audit );
        $this->assertSame( [], $service->get_trace_timeline( 'nonexistent-trace' ) );
    }

    public function test_get_trace_timeline_single_event(): void {
        $rows = $this->make_rows( [
            [ 'trace_id' => 'tr-solo', 'event_name' => 'generate.request', 'timestamp' => 5000, 'created_at' => '2026-03-06 12:00:00', 'payload' => [] ],
        ] );

        $this->audit->method( 'get_events_by_trace' )->willReturn( $rows );

        $service  = new TelemetryTraceService( $this->audit );
        $timeline = $service->get_trace_timeline( 'tr-solo' );

        $this->assertCount( 1, $timeline );
    }

    // -------------------------------------------------------------------------
    // find_by_schedule_id()
    // -------------------------------------------------------------------------

    public function test_find_by_schedule_id_returns_matching_events(): void {
        $rows = $this->make_recent_rows( [
            [ 'trace_id' => 'tr-001', 'event_name' => 'schedule.create',   'payload' => [ 'schedule_id' => 'sch-1' ], 'created_at' => '2026-03-06 10:00:01' ],
            [ 'trace_id' => 'tr-001', 'event_name' => 'schedule.dispatch', 'payload' => [ 'schedule_id' => 'sch-1' ], 'created_at' => '2026-03-06 10:00:05' ],
            [ 'trace_id' => 'tr-002', 'event_name' => 'schedule.create',   'payload' => [ 'schedule_id' => 'sch-9' ], 'created_at' => '2026-03-06 10:00:02' ],
        ] );

        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( $rows );

        $service = new TelemetryTraceService( $this->audit );
        $events  = $service->find_by_schedule_id( 'sch-1' );

        $this->assertCount( 2, $events );
        foreach ( $events as $e ) {
            $this->assertSame( 'sch-1', $e['payload']['schedule_id'] );
        }
    }

    public function test_find_by_schedule_id_empty_value_returns_empty(): void {
        $service = new TelemetryTraceService( $this->audit );
        $this->assertSame( [], $service->find_by_schedule_id( '' ) );
    }

    public function test_find_by_schedule_id_no_match_returns_empty(): void {
        $rows = $this->make_recent_rows( [
            [ 'trace_id' => 'tr-x', 'event_name' => 'schedule.create', 'payload' => [ 'schedule_id' => 'sch-9' ], 'created_at' => '2026-03-06 10:00:01' ],
        ] );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( $rows );

        $service = new TelemetryTraceService( $this->audit );
        $this->assertSame( [], $service->find_by_schedule_id( 'sch-999' ) );
    }

    // -------------------------------------------------------------------------
    // find_by_variant_id()
    // -------------------------------------------------------------------------

    public function test_find_by_variant_id_returns_matching_events(): void {
        $rows = $this->make_recent_rows( [
            [ 'trace_id' => 'tr-v1', 'event_name' => 'compliance.check', 'payload' => [ 'variant_id' => 'v-abc' ], 'created_at' => '2026-03-06 09:00:01' ],
            [ 'trace_id' => 'tr-v1', 'event_name' => 'variant.edit',     'payload' => [ 'variant_id' => 'v-abc', 'editor_id' => 5 ], 'created_at' => '2026-03-06 09:00:02' ],
            [ 'trace_id' => 'tr-v2', 'event_name' => 'compliance.check', 'payload' => [ 'variant_id' => 'v-xyz' ], 'created_at' => '2026-03-06 09:00:03' ],
        ] );

        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( $rows );

        $service = new TelemetryTraceService( $this->audit );
        $events  = $service->find_by_variant_id( 'v-abc' );

        $this->assertCount( 2, $events );
        foreach ( $events as $e ) {
            $this->assertSame( 'v-abc', $e['payload']['variant_id'] );
        }
    }

    public function test_find_by_variant_id_empty_value_returns_empty(): void {
        $service = new TelemetryTraceService( $this->audit );
        $this->assertSame( [], $service->find_by_variant_id( '' ) );
    }

    // -------------------------------------------------------------------------
    // PII masking
    // -------------------------------------------------------------------------

    public function test_sanitizer_masks_pii_in_timeline(): void {
        $rows = $this->make_rows( [
            [
                'trace_id'   => 'tr-pii',
                'event_name' => 'membership.signup',
                'timestamp'  => 3000,
                'created_at' => '2026-03-06 13:00:00',
                'payload'    => [
                    'user_id'   => 7,
                    'email'     => 'sensitive@example.com',
                    'trace_id'  => 'tr-pii',
                    'event_name' => 'membership.signup',
                ],
            ],
        ] );

        $this->audit->method( 'get_events_by_trace' )->willReturn( $rows );

        $service  = new TelemetryTraceService( $this->audit, new TelemetryPayloadSanitizer() );
        $timeline = $service->get_trace_timeline( 'tr-pii' );

        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $timeline[0]['payload']['email'] );
        $this->assertSame( 7, $timeline[0]['payload']['user_id'] );
    }

    public function test_without_sanitizer_payload_passes_through(): void {
        $rows = $this->make_rows( [
            [
                'trace_id'   => 'tr-raw',
                'event_name' => 'generate.request',
                'timestamp'  => 4000,
                'created_at' => '2026-03-06 14:00:00',
                'payload'    => [ 'user_id' => 9, 'email' => 'raw@example.com' ],
            ],
        ] );

        $this->audit->method( 'get_events_by_trace' )->willReturn( $rows );

        // No sanitizer — caller's responsibility.
        $service  = new TelemetryTraceService( $this->audit );
        $timeline = $service->get_trace_timeline( 'tr-raw' );

        // Without sanitizer PII passes through unchanged.
        $this->assertSame( 'raw@example.com', $timeline[0]['payload']['email'] );
    }

    public function test_sanitizer_applied_in_find_by_schedule_id(): void {
        $rows = $this->make_recent_rows( [
            [
                'trace_id'   => 'tr-s',
                'event_name' => 'schedule.create',
                'payload'    => [ 'schedule_id' => 'sch-pii', 'email' => 'pii@x.com', 'event_name' => 'schedule.create', 'trace_id' => 'tr-s' ],
                'created_at' => '2026-03-06 10:00:01',
            ],
        ] );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( $rows );

        $service = new TelemetryTraceService( $this->audit, new TelemetryPayloadSanitizer() );
        $events  = $service->find_by_schedule_id( 'sch-pii' );

        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $events[0]['payload']['email'] );
    }

    // -------------------------------------------------------------------------
    // extract_key_fields()
    // -------------------------------------------------------------------------

    public function test_extract_key_fields_returns_diagnostic_keys(): void {
        $event = [
            'event_name' => 'schedule.dispatch',
            'trace_id'   => 'tr-1',
            'timestamp'  => 5000,
            'created_at' => '2026-03-06 15:00:00',
            'payload'    => [
                'schedule_id' => 'sch-77',
                'adapter'     => 'linkedin',
                'result'      => 'delivered',
                'latency_ms'  => 300,
                'confidence_score' => 0.95,
            ],
        ];

        $service = new TelemetryTraceService( $this->audit );
        $fields  = $service->extract_key_fields( $event );

        $this->assertSame( 'sch-77',    $fields['schedule_id'] );
        $this->assertSame( 'linkedin',  $fields['adapter'] );
        $this->assertSame( 'delivered', $fields['result'] );
        $this->assertSame( 300,         $fields['latency_ms'] );
        $this->assertSame( 0.95,        $fields['confidence_score'] );
    }

    public function test_extract_key_fields_skips_absent_keys(): void {
        $event   = [ 'payload' => [ 'outcome' => 'ok' ] ];
        $service = new TelemetryTraceService( $this->audit );
        $fields  = $service->extract_key_fields( $event );

        $this->assertArrayHasKey( 'outcome', $fields );
        $this->assertArrayNotHasKey( 'schedule_id', $fields );
        $this->assertArrayNotHasKey( 'adapter', $fields );
    }

    public function test_extract_key_fields_empty_payload_returns_empty(): void {
        $service = new TelemetryTraceService( $this->audit );
        $this->assertSame( [], $service->extract_key_fields( [] ) );
        $this->assertSame( [], $service->extract_key_fields( [ 'payload' => [] ] ) );
    }

    // -------------------------------------------------------------------------
    // Fixture parity
    // -------------------------------------------------------------------------

    public function test_fixture_file_is_valid_json(): void {
        $path = dirname( __DIR__ ) . '/fixtures/telemetry/trace_debug_cases.json';
        $this->assertFileExists( $path );
        $data = json_decode( file_get_contents( $path ), true );
        $this->assertNotNull( $data );
        $this->assertArrayHasKey( 'cases', $data );
        $this->assertCount( 3, $data['cases'] );
    }

    public function test_fixture_complete_trace_timeline_order(): void {
        $data = $this->load_fixture();
        $case = $data['cases']['complete_trace_case'];

        $rows     = $this->make_rows_from_fixture( $case['audit_rows'] );
        $expected = $case['expected_timeline'];

        $this->audit->method( 'get_events_by_trace' )->willReturn( $rows );

        $service  = new TelemetryTraceService( $this->audit );
        $timeline = $service->get_trace_timeline( $case['trace_id'] );

        $this->assertCount( count( $expected ), $timeline );
        foreach ( $expected as $i => $exp ) {
            $this->assertSame( $exp['event_name'], $timeline[ $i ]['event_name'], "Position {$i} event_name mismatch" );
            $this->assertSame( $exp['trace_id'],   $timeline[ $i ]['trace_id'],   "Position {$i} trace_id mismatch" );
        }
    }

    public function test_fixture_complete_trace_key_fields_on_dispatch(): void {
        $data = $this->load_fixture();
        $case = $data['cases']['complete_trace_case'];

        $rows = $this->make_rows_from_fixture( $case['audit_rows'] );
        $this->audit->method( 'get_events_by_trace' )->willReturn( $rows );

        $service  = new TelemetryTraceService( $this->audit );
        $timeline = $service->get_trace_timeline( $case['trace_id'] );

        // Last event is schedule.dispatch.
        $dispatch = end( $timeline );
        $fields   = $service->extract_key_fields( $dispatch );
        $expected = $case['expected_key_fields_on_dispatch'];

        foreach ( $expected as $key => $value ) {
            $this->assertSame( $value, $fields[ $key ], "Key {$key} mismatch in dispatch key fields" );
        }
    }

    public function test_fixture_partial_trace_event_count(): void {
        $data = $this->load_fixture();
        $case = $data['cases']['missing_event_case'];

        $rows = $this->make_rows_from_fixture( $case['audit_rows'] );
        $this->audit->method( 'get_events_by_trace' )->willReturn( $rows );

        $service  = new TelemetryTraceService( $this->audit );
        $timeline = $service->get_trace_timeline( $case['trace_id'] );

        $this->assertSame( $case['expected_event_count'], count( $timeline ) );
    }

    public function test_fixture_dispatch_failure_result_field(): void {
        $data = $this->load_fixture();
        $case = $data['cases']['dispatch_failure_case'];

        $rows = $this->make_rows_from_fixture( $case['audit_rows'] );
        $this->audit->method( 'get_events_by_trace' )->willReturn( $rows );

        $service  = new TelemetryTraceService( $this->audit );
        $timeline = $service->get_trace_timeline( $case['trace_id'] );

        $dispatch = end( $timeline );
        $fields   = $service->extract_key_fields( $dispatch );

        $this->assertSame( 'failed', $fields['result'] );
        $this->assertSame( 'google', $fields['adapter'] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build decoded audit rows for get_events_by_trace() mock.
     *
     * @param  array $events Array of event definition maps.
     * @return array  Array of stdClass rows with decoded_details.
     */
    private function make_rows( array $events ): array {
        $rows = [];
        foreach ( $events as $i => $e ) {
            $row                   = new \stdClass();
            $row->id               = $i + 1;
            $row->created_at       = $e['created_at'] ?? '2026-03-06 00:00:00';
            $row->decoded_details  = [
                'trace_id'   => $e['trace_id'],
                'event_name' => $e['event_name'],
                'timestamp'  => $e['timestamp'] ?? 0,
                'payload'    => $e['payload'] ?? [],
            ];
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Build decoded audit rows for get_recent_telemetry_events() mock.
     * Returns in DESC order (as the real method does).
     */
    private function make_recent_rows( array $events ): array {
        $rows = $this->make_rows( $events );
        return array_reverse( $rows );
    }

    /**
     * Build decoded rows from fixture audit_rows array.
     */
    private function make_rows_from_fixture( array $fixture_rows ): array {
        $rows = [];
        foreach ( $fixture_rows as $fr ) {
            $row                  = new \stdClass();
            $row->id              = $fr['id'];
            $row->created_at      = $fr['created_at'];
            $row->decoded_details = $fr['details'];
            $rows[]               = $row;
        }
        return $rows;
    }

    private function load_fixture(): array {
        $path = dirname( __DIR__ ) . '/fixtures/telemetry/trace_debug_cases.json';
        return json_decode( file_get_contents( $path ), true );
    }
}
