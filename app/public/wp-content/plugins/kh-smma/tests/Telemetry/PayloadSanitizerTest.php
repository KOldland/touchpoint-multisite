<?php
namespace KH_SMMA\Tests\Telemetry;

use KH_SMMA\Telemetry\TelemetryPayloadSanitizer;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\EventQueue;
use KH_SMMA\Telemetry\TraceContext;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * OBS-07: Unit tests for TelemetryPayloadSanitizer.
 *
 * Covers:
 *  - PII fields are masked with [REDACTED]
 *  - Unknown fields are stripped from output
 *  - Valid canonical fields are preserved unchanged
 *  - event_name is never treated as a name-PII field
 *  - alert_name is preserved (not a personal name)
 *  - All six PII fixture cases match expected sanitizer output
 *  - EventEmitter passes sanitized payload to audit and dispatch
 */
class PayloadSanitizerTest extends TestCase {

    /** @var TelemetryPayloadSanitizer */
    private $sanitizer;

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['kh_test_filters'] = [];
        TraceContext::reset();
        $this->sanitizer = new TelemetryPayloadSanitizer();
    }

    // -------------------------------------------------------------------------
    // PII detection — is_pii_field()
    // -------------------------------------------------------------------------

    public function test_email_fields_detected_as_pii(): void {
        $this->assertTrue( $this->sanitizer->is_pii_field( 'email' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'user_email' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'email_address' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'EMAIL' ) );
    }

    public function test_phone_fields_detected_as_pii(): void {
        $this->assertTrue( $this->sanitizer->is_pii_field( 'phone' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'phone_number' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'mobile_phone' ) );
    }

    public function test_address_fields_detected_as_pii(): void {
        $this->assertTrue( $this->sanitizer->is_pii_field( 'address' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'billing_address' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'shipping_address' ) );
    }

    public function test_name_fields_detected_as_pii(): void {
        $this->assertTrue( $this->sanitizer->is_pii_field( 'first_name' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'last_name' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'full_name' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'display_name' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'username' ) );
    }

    public function test_token_and_secret_fields_detected_as_pii(): void {
        $this->assertTrue( $this->sanitizer->is_pii_field( 'api_key' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'access_token' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'refresh_token' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'secret' ) );
        $this->assertTrue( $this->sanitizer->is_pii_field( 'password' ) );
    }

    public function test_event_name_not_detected_as_pii(): void {
        // 'event_name' contains no PII despite having 'name' as suffix
        $this->assertFalse( $this->sanitizer->is_pii_field( 'event_name' ) );
    }

    public function test_alert_name_not_detected_as_pii(): void {
        // alert_name is a system field, not a personal name
        $this->assertFalse( $this->sanitizer->is_pii_field( 'alert_name' ) );
    }

    public function test_allowed_canonical_fields_not_pii(): void {
        $safe_fields = [
            'trace_id', 'schedule_id', 'variant_id', 'sponsor_id', 'user_id',
            'timestamp', 'service', 'latency_ms', 'outcome', 'status',
            'channel', 'adapter', 'result', 'tier', 'confidence_score',
        ];
        foreach ( $safe_fields as $field ) {
            $this->assertFalse(
                $this->sanitizer->is_pii_field( $field ),
                "Field '{$field}' should not be flagged as PII"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Allow-list — is_allowed_field()
    // -------------------------------------------------------------------------

    public function test_canonical_fields_are_allowed(): void {
        $canonical = [
            'event_name', 'trace_id', 'timestamp', 'service',
            'variant_id', 'schedule_id', 'user_id', 'latency_ms',
            'outcome', 'rules_matched', 'editor_id', 'alert_name',
        ];
        foreach ( $canonical as $field ) {
            $this->assertTrue(
                $this->sanitizer->is_allowed_field( $field ),
                "'{$field}' should be in the allow-list"
            );
        }
    }

    public function test_unknown_fields_not_allowed(): void {
        $unknown = [ 'raw_text', 'internal_notes', 'debug_dump', 'foo_bar' ];
        foreach ( $unknown as $field ) {
            $this->assertFalse(
                $this->sanitizer->is_allowed_field( $field ),
                "'{$field}' should not be in the allow-list"
            );
        }
    }

    // -------------------------------------------------------------------------
    // sanitize() — PII masking
    // -------------------------------------------------------------------------

    public function test_pii_fields_replaced_with_redacted_marker(): void {
        $payload   = [ 'user_email' => 'user@example.com', 'user_id' => 42, 'event_name' => 'generate.request', 'trace_id' => 'tr-1' ];
        $sanitized = $this->sanitizer->sanitize( $payload );

        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $sanitized['user_email'] );
        $this->assertSame( 42, $sanitized['user_id'] );
    }

    public function test_pii_key_retained_with_redacted_value(): void {
        $sanitized = $this->sanitizer->sanitize( [ 'email' => 'x@y.com', 'trace_id' => 'tr-1', 'event_name' => 'generate.request' ] );

        // Key is retained so a PII violation is visible in audit records.
        $this->assertArrayHasKey( 'email', $sanitized );
        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $sanitized['email'] );
    }

    public function test_multiple_pii_fields_all_redacted(): void {
        $payload = [
            'first_name'  => 'Alice',
            'last_name'   => 'Smith',
            'email'       => 'a@b.com',
            'phone'       => '555-0100',
            'user_id'     => 7,
            'trace_id'    => 'tr-2',
            'event_name'  => 'membership.signup',
        ];

        $sanitized = $this->sanitizer->sanitize( $payload );

        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $sanitized['first_name'] );
        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $sanitized['last_name'] );
        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $sanitized['email'] );
        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $sanitized['phone'] );
        $this->assertSame( 7, $sanitized['user_id'] );
    }

    // -------------------------------------------------------------------------
    // sanitize() — unknown field stripping
    // -------------------------------------------------------------------------

    public function test_unknown_fields_stripped(): void {
        $payload   = [ 'event_name' => 'generate.request', 'trace_id' => 'tr-3', 'unknown_field' => 'data', 'raw_text' => 'draft' ];
        $sanitized = $this->sanitizer->sanitize( $payload );

        $this->assertArrayNotHasKey( 'unknown_field', $sanitized );
        $this->assertArrayNotHasKey( 'raw_text', $sanitized );
    }

    public function test_mixed_pii_unknown_allowed_payload(): void {
        $payload = [
            'event_name'   => 'schedule.create',
            'trace_id'     => 'tr-4',
            'schedule_id'  => 'sch-1',
            'email'        => 'x@y.com',       // PII — redacted
            'raw_notes'    => 'some text',      // unknown — stripped
            'channel'      => 'linkedin',       // allowed
        ];

        $sanitized = $this->sanitizer->sanitize( $payload );

        $this->assertSame( 'sch-1', $sanitized['schedule_id'] );
        $this->assertSame( 'linkedin', $sanitized['channel'] );
        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $sanitized['email'] );
        $this->assertArrayNotHasKey( 'raw_notes', $sanitized );
    }

    // -------------------------------------------------------------------------
    // sanitize() — valid fields preserved
    // -------------------------------------------------------------------------

    public function test_all_allowed_fields_preserved(): void {
        $payload = [
            'event_name'   => 'compliance.check',
            'trace_id'     => 'tr-5',
            'timestamp'    => 1741219200,
            'service'      => 'smma',
            'variant_id'   => 'v-abc',
            'outcome'      => 'OK',
            'violations'   => 0,
            'passed'       => true,
        ];

        $sanitized = $this->sanitizer->sanitize( $payload );
        $this->assertSame( $payload, $sanitized );
    }

    public function test_empty_payload_returns_empty_array(): void {
        $this->assertSame( [], $this->sanitizer->sanitize( [] ) );
    }

    public function test_numeric_keys_are_stripped(): void {
        $payload   = [ 0 => 'zero', 'trace_id' => 'tr-6', 'event_name' => 'generate.request' ];
        $sanitized = $this->sanitizer->sanitize( $payload );
        $this->assertArrayNotHasKey( 0, $sanitized );
        $this->assertSame( 'tr-6', $sanitized['trace_id'] );
    }

    // -------------------------------------------------------------------------
    // Fixture parity
    // -------------------------------------------------------------------------

    public function test_fixture_file_is_valid_json(): void {
        $path = dirname( __DIR__ ) . '/fixtures/telemetry/pii_payload_cases.json';
        $this->assertFileExists( $path );
        $data = json_decode( file_get_contents( $path ), true );
        $this->assertNotNull( $data );
        $this->assertArrayHasKey( 'cases', $data );
    }

    /**
     * @dataProvider fixture_cases_provider
     */
    public function test_fixture_case_matches_sanitizer( string $case_name, array $input, array $expected_fields, array $stripped_fields ): void {
        $sanitized = $this->sanitizer->sanitize( $input );

        foreach ( $expected_fields as $key => $value ) {
            $this->assertArrayHasKey( $key, $sanitized, "Case {$case_name}: key '{$key}' missing" );
            $this->assertSame( $value, $sanitized[ $key ], "Case {$case_name}: key '{$key}' value mismatch" );
        }

        foreach ( $stripped_fields as $key ) {
            $this->assertArrayNotHasKey( $key, $sanitized, "Case {$case_name}: key '{$key}' should have been stripped" );
        }
    }

    public function fixture_cases_provider(): array {
        $path   = dirname( __DIR__ ) . '/fixtures/telemetry/pii_payload_cases.json';
        $data   = json_decode( file_get_contents( $path ), true );
        $cases  = [];

        foreach ( $data['cases'] as $name => $case ) {
            $cases[ $name ] = [
                $name,
                $case['input'],
                $case['expected'],
                $case['stripped_fields'],
            ];
        }

        return $cases;
    }

    // -------------------------------------------------------------------------
    // EventEmitter integration — sanitizer wired into emit()
    // -------------------------------------------------------------------------

    public function test_emitter_sanitizes_pii_before_audit(): void {
        $db = new \wpdb();

        $captured_payload = null;
        $audit = $this->getMockBuilder( AuditLogger::class )
                      ->setConstructorArgs( [ $db ] )
                      ->onlyMethods( [ 'record_event' ] )
                      ->getMock();
        $audit->method( 'record_event' )
              ->willReturnCallback( function ( $trace_id, $event_name, $timestamp, $payload ) use ( &$captured_payload ) {
                  $captured_payload = $payload;
              } );

        TraceContext::init( 'trace-san-001' );
        $emitter = new EventEmitter( $audit, null, new TelemetryPayloadSanitizer() );
        $emitter->emit( 'generate.request', [
            'user_id'    => 1,
            'user_email' => 'pii@example.com',
            'latency_ms' => 200,
        ] );

        $this->assertNotNull( $captured_payload );
        $this->assertSame( TelemetryPayloadSanitizer::REDACTED_MARKER, $captured_payload['user_email'] );
        $this->assertSame( 1, $captured_payload['user_id'] );
        $this->assertSame( 200, $captured_payload['latency_ms'] );
    }

    public function test_emitter_without_sanitizer_passes_payload_through(): void {
        $db = new \wpdb();

        $captured_payload = null;
        $audit = $this->getMockBuilder( AuditLogger::class )
                      ->setConstructorArgs( [ $db ] )
                      ->onlyMethods( [ 'record_event' ] )
                      ->getMock();
        $audit->method( 'record_event' )
              ->willReturnCallback( function ( $trace_id, $event_name, $timestamp, $payload ) use ( &$captured_payload ) {
                  $captured_payload = $payload;
              } );

        TraceContext::init( 'trace-san-002' );
        // No sanitizer — backward-compat mode.
        $emitter = new EventEmitter( $audit );
        $emitter->emit( 'generate.request', [
            'user_id'    => 2,
            'user_email' => 'raw@example.com',
        ] );

        $this->assertNotNull( $captured_payload );
        // Without sanitizer, PII passes through (caller's responsibility).
        $this->assertSame( 'raw@example.com', $captured_payload['user_email'] );
    }

    public function test_emitter_sanitizer_strips_unknown_fields_before_queue(): void {
        $db = new \wpdb();

        $audit = $this->getMockBuilder( AuditLogger::class )
                      ->setConstructorArgs( [ $db ] )
                      ->onlyMethods( [ 'record_event' ] )
                      ->getMock();
        $audit->method( 'record_event' )->willReturnCallback( function () {} );

        $dispatched = [];
        add_action( 'kh_telemetry_event', function ( $_val, $e ) use ( &$dispatched ) {
            $dispatched[] = $e;
        }, 10, 2 );

        $queue   = new EventQueue( null, 1000, 1.0 );
        TraceContext::init( 'trace-san-003' );
        $emitter = new EventEmitter( $audit, $queue, new TelemetryPayloadSanitizer() );
        $emitter->emit( 'schedule.create', [
            'schedule_id' => 'sch-1',
            'raw_content' => 'should-be-stripped',
            'channel'     => 'linkedin',
        ] );

        $queue->flush();

        $this->assertCount( 1, $dispatched );
        $event = $dispatched[0];
        $this->assertSame( 'sch-1', $event['schedule_id'] );
        $this->assertSame( 'linkedin', $event['channel'] );
        $this->assertArrayNotHasKey( 'raw_content', $event );
    }
}
