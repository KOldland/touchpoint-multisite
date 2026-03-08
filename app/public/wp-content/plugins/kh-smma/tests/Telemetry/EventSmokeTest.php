<?php
/**
 * OBS-05: EventSmokeTest — full 6-event workflow smoke test.
 *
 * Exercises the canonical event sequence for a complete SMMA workflow:
 *   generate.request → generate.response → compliance.check →
 *   variant.edit → schedule.create → schedule.dispatch
 *
 * Verifies:
 *  1. All six events are emitted in order with correct event_name values.
 *  2. Every envelope carries the same trace_id (single-request correlation).
 *  3. All base envelope fields (event_name, trace_id, timestamp, service) present.
 *  4. After processing all events, analytics accumulator holds expected counts.
 *  5. Flushed metrics snapshot matches expected aggregate values.
 *  6. No event is silently dropped — count of audit entries = 6.
 */

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/TraceContext.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/EventEmitter.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/MetricsSnapshotRepository.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/AnalyticsFeedbackService.php';

use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Telemetry\AnalyticsFeedbackService;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\MetricsSnapshotRepository;
use KH_SMMA\Telemetry\TraceContext;
use PHPUnit\Framework\TestCase;

class EventSmokeTest extends TestCase {

    private const TRACE_ID = 'cccccccc-0005-4000-8000-000000000005';

    /** @var array Full sequence of captured envelopes */
    private array $sequence = array();

    /** @var array Captured flush_snapshot metrics */
    private array $flushed = array();

    /** @var EventEmitter */
    private $emitter;

    /** @var AnalyticsFeedbackService */
    private $feedback;

    // Full canonical 6-event smoke workflow definition.
    private const WORKFLOW = array(
        array(
            'name'    => 'generate.request',
            'payload' => array(
                'service'                 => 'smma',
                'session_id'              => 'smoke-sess-001',
                'prompt_hash'             => 'deadbeefdeadbeef',
                'variant_count_requested' => 2,
            ),
        ),
        array(
            'name'    => 'generate.response',
            'payload' => array(
                'service'                  => 'smma',
                'variant_count_generated'  => 2,
                'latency_ms'               => 180,
            ),
        ),
        array(
            'name'    => 'compliance.check',
            'payload' => array(
                'service'       => 'smma',
                'variant_id'    => 'smoke-v-001',
                'outcome'       => 'OK',
                'rules_matched' => array(),
            ),
        ),
        array(
            'name'    => 'variant.edit',
            'payload' => array(
                'service'     => 'smma',
                'variant_id'  => 'smoke-v-001',
                'editor_id'   => '1',
                'revision_id' => 'smoke-rev-001',
            ),
        ),
        array(
            'name'    => 'schedule.create',
            'payload' => array(
                'service'          => 'smma',
                'schedule_id'      => 'smoke-sched-001',
                'sponsor_id'       => 'smoke-sp-001',
                'approval_required' => false,
            ),
        ),
        array(
            'name'    => 'schedule.dispatch',
            'payload' => array(
                'service'     => 'smma',
                'schedule_id' => 'smoke-sched-001',
                'adapter'     => 'linkedin',
                'result'      => 'dispatched',
            ),
        ),
    );

    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['kh_test_filters']    = array();
        $GLOBALS['kh_test_db_inserts'] = array();
        $GLOBALS['kh_test_options']    = array();
        $this->sequence                = array();
        $this->flushed                 = array();

        TraceContext::reset();
        TraceContext::init( self::TRACE_ID );

        $sequence = &$this->sequence;
        $db       = new wpdb();
        $audit    = $this->getMockBuilder( AuditLogger::class )
                         ->setConstructorArgs( array( $db ) )
                         ->onlyMethods( array( 'record_event' ) )
                         ->getMock();
        $audit->method( 'record_event' )
              ->willReturnCallback( function ( $trace_id, $event_name, $timestamp, $payload ) use ( &$sequence ) {
                  $sequence[] = $payload;
              } );

        $flushed         = &$this->flushed;
        $repo_mock       = $this->getMockBuilder( MetricsSnapshotRepository::class )
                               ->setConstructorArgs( array( $db ) )
                               ->onlyMethods( array( 'write_snapshot' ) )
                               ->getMock();
        $repo_mock->method( 'write_snapshot' )
                  ->willReturnCallback( function ( array $metrics ) use ( &$flushed ) {
                      $flushed[] = $metrics;
                      return 1;
                  } );

        $this->emitter  = new EventEmitter( $audit );
        $this->feedback = new AnalyticsFeedbackService( $repo_mock );

        // Execute the full workflow.
        foreach ( self::WORKFLOW as $step ) {
            $this->emitter->emit( $step['name'], $step['payload'] );
        }
        // Wire analytics: feed captured envelopes directly into AnalyticsFeedbackService.
        foreach ( $this->sequence as $envelope ) {
            $this->feedback->handle_event( $envelope );
        }
    }

    protected function tearDown(): void {
        TraceContext::reset();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Emission completeness
    // -------------------------------------------------------------------------

    public function test_all_six_events_are_emitted(): void {
        $this->assertCount( 6, $this->sequence, 'All 6 workflow events must be emitted' );
    }

    public function test_event_order_matches_workflow_sequence(): void {
        $expected_names = array_column( self::WORKFLOW, 'name' );
        $actual_names   = array_column( $this->sequence, 'event_name' );
        $this->assertSame( $expected_names, $actual_names, 'Events must be emitted in workflow order' );
    }

    // -------------------------------------------------------------------------
    // Trace correlation
    // -------------------------------------------------------------------------

    public function test_all_events_share_the_same_trace_id(): void {
        foreach ( $this->sequence as $env ) {
            $this->assertSame(
                self::TRACE_ID,
                $env['trace_id'],
                "trace_id mismatch on event '{$env['event_name']}'"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Base envelope integrity
    // -------------------------------------------------------------------------

    /**
     * @dataProvider base_envelope_field_provider
     */
    public function test_every_event_has_base_envelope_field( string $field ): void {
        foreach ( $this->sequence as $env ) {
            $this->assertArrayHasKey( $field, $env, "Event '{$env['event_name']}' missing base field '$field'" );
        }
    }

    public static function base_envelope_field_provider(): array {
        return array(
            'event_name' => array( 'event_name' ),
            'trace_id'   => array( 'trace_id' ),
            'timestamp'  => array( 'timestamp' ),
            'service'    => array( 'service' ),
        );
    }

    public function test_timestamps_are_positive_integers(): void {
        foreach ( $this->sequence as $env ) {
            $this->assertIsInt( $env['timestamp'],  "Event '{$env['event_name']}' timestamp must be int" );
            $this->assertGreaterThan( 0, $env['timestamp'], "Event '{$env['event_name']}' timestamp must be positive" );
        }
    }

    public function test_event_name_field_matches_emitted_name(): void {
        $expected_names = array_column( self::WORKFLOW, 'name' );
        foreach ( $this->sequence as $idx => $env ) {
            $this->assertSame( $expected_names[ $idx ], $env['event_name'] );
        }
    }

    // -------------------------------------------------------------------------
    // Analytics accumulator — smoke counts
    // -------------------------------------------------------------------------

    public function test_smoke_accumulator_generate_requests(): void {
        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['generate_requests'] );
    }

    public function test_smoke_accumulator_variants_created(): void {
        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 2, $acc['variants_created'] );
    }

    public function test_smoke_accumulator_compliance_ok(): void {
        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['compliance_ok'] );
        $this->assertSame( 0, $acc['compliance_fail'] );
    }

    public function test_smoke_accumulator_variant_edits(): void {
        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['variant_edits'] );
    }

    public function test_smoke_accumulator_schedule_created(): void {
        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['schedule_created'] );
    }

    public function test_smoke_accumulator_schedule_dispatched(): void {
        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['schedule_dispatched'] );
    }

    // -------------------------------------------------------------------------
    // Flush snapshot — aggregate
    // -------------------------------------------------------------------------

    public function test_flush_produces_one_snapshot(): void {
        $this->feedback->flush_snapshot();
        $this->assertCount( 1, $this->flushed );
    }

    public function test_flushed_snapshot_matches_smoke_counts(): void {
        $this->feedback->flush_snapshot();
        $m = $this->flushed[0];

        $this->assertSame( 1, $m['generate_requests'],   'generate_requests' );
        $this->assertSame( 2, $m['variants_created'],    'variants_created' );
        $this->assertSame( 1, $m['compliance_ok'],       'compliance_ok' );
        $this->assertSame( 0, $m['compliance_fail'],     'compliance_fail' );
        $this->assertSame( 1, $m['variant_edits'],       'variant_edits' );
        $this->assertSame( 1, $m['schedule_created'],    'schedule_created' );
        $this->assertSame( 1, $m['schedule_dispatched'], 'schedule_dispatched' );
    }

    public function test_flushed_snapshot_avg_latency_is_correct(): void {
        $this->feedback->flush_snapshot();
        $m = $this->flushed[0];
        // Only one generate.response with latency_ms=180.
        $this->assertSame( 180.0, $m['avg_generate_latency_ms'] );
    }

    // -------------------------------------------------------------------------
    // Event-specific payload fields
    // -------------------------------------------------------------------------

    public function test_generate_request_has_session_id(): void {
        $env = $this->get_event( 'generate.request' );
        $this->assertSame( 'smoke-sess-001', $env['session_id'] );
    }

    public function test_compliance_check_has_variant_id_and_outcome(): void {
        $env = $this->get_event( 'compliance.check' );
        $this->assertSame( 'smoke-v-001', $env['variant_id'] );
        $this->assertSame( 'OK',          $env['outcome'] );
    }

    public function test_schedule_dispatch_has_adapter_and_result(): void {
        $env = $this->get_event( 'schedule.dispatch' );
        $this->assertSame( 'linkedin',   $env['adapter'] );
        $this->assertSame( 'dispatched', $env['result'] );
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function get_event( string $event_name ): array {
        foreach ( $this->sequence as $env ) {
            if ( $env['event_name'] === $event_name ) {
                return $env;
            }
        }
        $this->fail( "Event '$event_name' not found in emitted sequence" );
    }
}
