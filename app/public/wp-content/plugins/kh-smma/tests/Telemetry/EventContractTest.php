<?php
/**
 * OBS-05: EventContractTest — telemetry event payload contract validation.
 *
 * For every canonical event type this test:
 *  1. Emits the event via EventEmitter with a representative payload.
 *  2. Captures the envelope published to kh_telemetry_event.
 *  3. Validates that all required keys defined in event_contracts.json are
 *     present in the emitted envelope.
 *  4. Validates that base-envelope fields (event_name, trace_id, timestamp,
 *     service) are always present and have the correct types.
 *
 * This test is driven by tests/fixtures/telemetry/event_contracts.json so that
 * contract changes can be made in a single place and are automatically picked
 * up by both CI and documentation tooling.
 *
 * This test does NOT modify event definitions — it only validates them.
 */

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/TraceContext.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/EventEmitter.php';

use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\TraceContext;
use PHPUnit\Framework\TestCase;

class EventContractTest extends TestCase {

    private const TRACE_ID = 'aaaaaaaa-0005-4000-8000-000000000005';

    /** @var array Captured kh_telemetry_event envelopes */
    private array $captured = array();

    /** @var EventEmitter */
    private $emitter;

    /** @var array Full contract fixture */
    private array $contracts;

    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['kh_test_filters']    = array();
        $GLOBALS['kh_test_db_inserts'] = array();
        $this->captured                = array();

        TraceContext::reset();
        TraceContext::init( self::TRACE_ID );

        $db = new wpdb();

        // Capture via record_event mock — the reliable path.
        // TestHelpers do_action passes event as 2nd arg (null is first), so
        // add_action with accepted_args=1 receives null.  record_event receives
        // the full envelope as its 4th parameter every time.
        $captured = &$this->captured;
        $audit    = $this->getMockBuilder( AuditLogger::class )
                         ->setConstructorArgs( array( $db ) )
                         ->onlyMethods( array( 'record_event' ) )
                         ->getMock();
        $audit->method( 'record_event' )
              ->willReturnCallback( function ( $trace_id, $event_name, $timestamp, $payload ) use ( &$captured ) {
                  $captured[] = $payload;
              } );

        $this->emitter = new EventEmitter( $audit );

        $this->contracts = json_decode(
            file_get_contents( __DIR__ . '/../fixtures/telemetry/event_contracts.json' ),
            true
        );
    }

    protected function tearDown(): void {
        TraceContext::reset();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Base envelope — all events
    // -------------------------------------------------------------------------

    /**
     * @dataProvider all_event_provider
     */
    public function test_base_envelope_fields_always_present( string $event_name, array $payload ): void {
        $this->emitter->emit( $event_name, $payload );
        $envelope = $this->last_envelope();

        $this->assertArrayHasKey( 'event_name', $envelope, "$event_name: missing event_name" );
        $this->assertArrayHasKey( 'trace_id',   $envelope, "$event_name: missing trace_id" );
        $this->assertArrayHasKey( 'timestamp',  $envelope, "$event_name: missing timestamp" );
        $this->assertArrayHasKey( 'service',    $envelope, "$event_name: missing service" );
    }

    /**
     * @dataProvider all_event_provider
     */
    public function test_base_envelope_types( string $event_name, array $payload ): void {
        $this->emitter->emit( $event_name, $payload );
        $envelope = $this->last_envelope();

        $this->assertIsString( $envelope['event_name'], "$event_name: event_name must be string" );
        $this->assertIsString( $envelope['trace_id'],   "$event_name: trace_id must be string" );
        $this->assertIsInt(    $envelope['timestamp'],  "$event_name: timestamp must be int" );
        $this->assertIsString( $envelope['service'],    "$event_name: service must be string" );
    }

    /**
     * @dataProvider all_event_provider
     */
    public function test_event_name_matches_emitted_name( string $event_name, array $payload ): void {
        $this->emitter->emit( $event_name, $payload );
        $envelope = $this->last_envelope();
        $this->assertSame( $event_name, $envelope['event_name'] );
    }

    /**
     * @dataProvider all_event_provider
     */
    public function test_trace_id_is_propagated( string $event_name, array $payload ): void {
        $this->emitter->emit( $event_name, $payload );
        $envelope = $this->last_envelope();
        $this->assertSame( self::TRACE_ID, $envelope['trace_id'], "$event_name: trace_id must match TraceContext" );
    }

    /**
     * @dataProvider all_event_provider
     */
    public function test_timestamp_is_positive_integer( string $event_name, array $payload ): void {
        $this->emitter->emit( $event_name, $payload );
        $envelope = $this->last_envelope();
        $this->assertGreaterThan( 0, $envelope['timestamp'], "$event_name: timestamp must be positive" );
    }

    // -------------------------------------------------------------------------
    // Per-event required-key validation (fixture-driven)
    // -------------------------------------------------------------------------

    /**
     * @dataProvider contract_event_provider
     */
    public function test_required_keys_present( string $event_name, array $payload, array $required_keys ): void {
        $this->emitter->emit( $event_name, $payload );
        $envelope = $this->last_envelope();

        foreach ( $required_keys as $key ) {
            $this->assertArrayHasKey(
                $key,
                $envelope,
                "Event '$event_name': required key '$key' missing from envelope"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Per-event explicit tests (for readable --testdox output)
    // -------------------------------------------------------------------------

    public function test_generate_request_required_keys(): void {
        $this->emitter->emit( 'generate.request', $this->payload_for( 'generate.request' ) );
        $this->assert_required_keys( 'generate.request' );
    }

    public function test_generate_response_required_keys(): void {
        $this->emitter->emit( 'generate.response', $this->payload_for( 'generate.response' ) );
        $this->assert_required_keys( 'generate.response' );
    }

    public function test_compliance_check_required_keys(): void {
        $this->emitter->emit( 'compliance.check', $this->payload_for( 'compliance.check' ) );
        $this->assert_required_keys( 'compliance.check' );
    }

    public function test_compliance_check_outcome_is_valid_enum(): void {
        foreach ( array( 'OK', 'WARN', 'FAIL' ) as $outcome ) {
            $this->captured = array();
            $payload        = $this->payload_for( 'compliance.check' );
            $payload['outcome'] = $outcome;
            $this->emitter->emit( 'compliance.check', $payload );
            $env = $this->last_envelope();
            $this->assertContains( $env['outcome'], array( 'OK', 'WARN', 'FAIL' ) );
        }
    }

    public function test_variant_edit_required_keys(): void {
        $this->emitter->emit( 'variant.edit', $this->payload_for( 'variant.edit' ) );
        $this->assert_required_keys( 'variant.edit' );
    }

    public function test_schedule_create_required_keys(): void {
        $this->emitter->emit( 'schedule.create', $this->payload_for( 'schedule.create' ) );
        $this->assert_required_keys( 'schedule.create' );
    }

    public function test_schedule_dispatch_required_keys(): void {
        $this->emitter->emit( 'schedule.dispatch', $this->payload_for( 'schedule.dispatch' ) );
        $this->assert_required_keys( 'schedule.dispatch' );
    }

    public function test_schedule_dispatch_result_is_valid_enum(): void {
        foreach ( array( 'dispatched', 'exported', 'failed' ) as $result ) {
            $this->captured = array();
            $payload        = $this->payload_for( 'schedule.dispatch' );
            $payload['result'] = $result;
            $this->emitter->emit( 'schedule.dispatch', $payload );
            $env = $this->last_envelope();
            $this->assertContains( $env['result'], array( 'dispatched', 'exported', 'failed' ) );
        }
    }

    public function test_membership_signup_required_keys(): void {
        $this->emitter->emit( 'membership.signup', $this->payload_for( 'membership.signup' ) );
        $this->assert_required_keys( 'membership.signup' );
    }

    public function test_membership_signup_user_id_is_integer(): void {
        $this->emitter->emit( 'membership.signup', $this->payload_for( 'membership.signup' ) );
        $env = $this->last_envelope();
        $this->assertIsInt( $env['user_id'], 'user_id must be integer (no PII leak)' );
    }

    public function test_promotion_attribution_required_keys(): void {
        $this->emitter->emit( 'promotion_attribution', $this->payload_for( 'promotion_attribution' ) );
        $this->assert_required_keys( 'promotion_attribution' );
    }

    public function test_contract_fixture_is_valid_json(): void {
        $this->assertIsArray( $this->contracts );
        $this->assertArrayHasKey( 'events', $this->contracts );
        $this->assertArrayHasKey( 'base_envelope', $this->contracts );
    }

    public function test_all_registry_events_covered_by_fixture(): void {
        $registry_events = array(
            'generate.request', 'generate.response', 'compliance.check',
            'variant.edit', 'schedule.create', 'schedule.dispatch',
            'membership.signup', 'promotion_attribution',
        );
        $fixture_events = array_keys( $this->contracts['events'] ?? array() );
        foreach ( $registry_events as $event ) {
            $this->assertContains( $event, $fixture_events, "Registry event '$event' missing from event_contracts.json" );
        }
    }

    public function test_schema_file_exists_and_valid_json(): void {
        $schema_path = dirname( __DIR__, 7 ) . '/docs/contracts/telemetry_events_schema.json';
        $this->assertFileExists( $schema_path, 'telemetry_events_schema.json must exist' );
        $decoded = json_decode( file_get_contents( $schema_path ), true );
        $this->assertIsArray( $decoded );
        $this->assertArrayHasKey( '$defs', $decoded );
    }

    // -------------------------------------------------------------------------
    // Data providers
    // -------------------------------------------------------------------------

    public static function all_event_provider(): array {
        return array(
            'generate.request'      => array( 'generate.request',      array( 'service' => 'smma', 'session_id' => 'req_1', 'prompt_hash' => 'abc', 'variant_count_requested' => 2 ) ),
            'generate.response'     => array( 'generate.response',     array( 'service' => 'smma', 'variant_count_generated' => 2, 'latency_ms' => 100 ) ),
            'compliance.check'      => array( 'compliance.check',      array( 'service' => 'smma', 'variant_id' => 'v-1', 'outcome' => 'OK', 'rules_matched' => array() ) ),
            'variant.edit'          => array( 'variant.edit',          array( 'service' => 'smma', 'variant_id' => 'v-1', 'editor_id' => '1', 'revision_id' => 'rev-1' ) ),
            'schedule.create'       => array( 'schedule.create',       array( 'service' => 'smma', 'schedule_id' => 's-1', 'sponsor_id' => 'sp-1', 'approval_required' => false ) ),
            'schedule.dispatch'     => array( 'schedule.dispatch',     array( 'service' => 'smma', 'schedule_id' => 's-1', 'adapter' => 'manual', 'result' => 'dispatched' ) ),
            'membership.signup'     => array( 'membership.signup',     array( 'service' => 'mem',  'user_id' => 99, 'tier' => 'standard', 'payment_status' => 'paid', 'attribution_id' => 'promo-1' ) ),
            'promotion_attribution' => array( 'promotion_attribution', array( 'service' => 'mem',  'schedule_id' => 's-1', 'sponsor_id' => 'sp-1', 'utm_source' => 'linkedin', 'confidence_score' => 0.88 ) ),
        );
    }

    public static function contract_event_provider(): array {
        $contracts = json_decode(
            file_get_contents( __DIR__ . '/../fixtures/telemetry/event_contracts.json' ),
            true
        );

        $provider = array();
        $payloads = self::all_event_provider();

        foreach ( $contracts['events'] as $event_name => $contract ) {
            if ( ! isset( $payloads[ $event_name ] ) ) {
                continue;
            }
            $provider[ $event_name ] = array(
                $event_name,
                $payloads[ $event_name ][1],
                $contract['required'],
            );
        }

        return $provider;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function last_envelope(): array {
        $this->assertNotEmpty( $this->captured, 'No event was captured by kh_telemetry_event listener' );
        return end( $this->captured );
    }

    private function payload_for( string $event_name ): array {
        $payloads = self::all_event_provider();
        return $payloads[ $event_name ][1] ?? array( 'service' => 'smma' );
    }

    private function assert_required_keys( string $event_name ): void {
        $required = $this->contracts['events'][ $event_name ]['required'] ?? array();
        $envelope = $this->last_envelope();
        foreach ( $required as $key ) {
            $this->assertArrayHasKey( $key, $envelope, "Event '$event_name': required key '$key' missing" );
        }
    }
}
