<?php
/**
 * OBS-05: EventPipelineIntegrationTest — end-to-end pipeline integration.
 *
 * Verifies that:
 *  1. EventEmitter builds the canonical envelope and calls AuditLogger::record_event.
 *  2. The envelope produced by EventEmitter is accepted by AnalyticsFeedbackService
 *     and correctly increments accumulator counters.
 *  3. flush_snapshot() computes correct aggregate metrics and writes them to the
 *     MetricsSnapshotRepository.
 *  4. Trace context propagates correctly across the full pipeline.
 *
 * Note on do_action stub: TestHelpers do_action passes null as the first argument
 * to apply_filters, so add_action callbacks with accepted_args=1 receive null
 * instead of the event.  These tests wire the pipeline directly (calling
 * handle_event() with the captured envelope) to isolate the contract between
 * EventEmitter output and AnalyticsFeedbackService input.
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

class EventPipelineIntegrationTest extends TestCase {

    private const TRACE_ID = 'bbbbbbbb-0005-4000-8000-000000000005';

    /** @var array Captured event envelopes from AuditLogger::record_event */
    private array $captured_envelopes = array();

    /** @var array Captured write_snapshot calls */
    private array $captured_snapshots = array();

    /** @var EventEmitter */
    private $emitter;

    /** @var AnalyticsFeedbackService */
    private $feedback;

    /** @var MetricsSnapshotRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $repo_mock;

    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['kh_test_filters']    = array();
        $GLOBALS['kh_test_db_inserts'] = array();
        $GLOBALS['kh_test_options']    = array();
        $this->captured_envelopes      = array();
        $this->captured_snapshots      = array();

        TraceContext::reset();
        TraceContext::init( self::TRACE_ID );

        // AuditLogger mock — capture record_event calls.
        $envelopes = &$this->captured_envelopes;
        $db        = new wpdb();
        $audit     = $this->getMockBuilder( AuditLogger::class )
                          ->setConstructorArgs( array( $db ) )
                          ->onlyMethods( array( 'record_event' ) )
                          ->getMock();
        $audit->method( 'record_event' )
              ->willReturnCallback( function ( $trace_id, $event_name, $timestamp, $payload ) use ( &$envelopes ) {
                  $envelopes[] = $payload;
              } );

        // MetricsSnapshotRepository mock — capture write_snapshot calls.
        $snapshots  = &$this->captured_snapshots;
        $this->repo_mock = $this->getMockBuilder( MetricsSnapshotRepository::class )
                               ->setConstructorArgs( array( $db ) )
                               ->onlyMethods( array( 'write_snapshot' ) )
                               ->getMock();
        $this->repo_mock->method( 'write_snapshot' )
                        ->willReturnCallback( function ( array $metrics, int $window_start ) use ( &$snapshots ) {
                            $snapshots[] = $metrics;
                            return 1;
                        } );

        $this->emitter  = new EventEmitter( $audit );
        $this->feedback = new AnalyticsFeedbackService( $this->repo_mock );
    }

    protected function tearDown(): void {
        TraceContext::reset();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Audit persistence
    // -------------------------------------------------------------------------

    public function test_emit_calls_audit_record_event(): void {
        $this->emitter->emit( 'generate.request', array(
            'service'                  => 'smma',
            'session_id'               => 'sess-1',
            'prompt_hash'              => 'abc123',
            'variant_count_requested'  => 2,
        ) );

        $this->assertCount( 1, $this->captured_envelopes, 'record_event must be called once' );
    }

    public function test_emit_produces_canonical_envelope(): void {
        $this->emitter->emit( 'generate.request', array(
            'service'                  => 'smma',
            'session_id'               => 'sess-1',
            'prompt_hash'              => 'abc123',
            'variant_count_requested'  => 2,
        ) );

        $env = $this->captured_envelopes[0];
        $this->assertSame( 'generate.request', $env['event_name'] );
        $this->assertSame( self::TRACE_ID, $env['trace_id'] );
        $this->assertIsInt( $env['timestamp'] );
        $this->assertGreaterThan( 0, $env['timestamp'] );
        $this->assertSame( 'smma', $env['service'] );
        $this->assertSame( 'sess-1', $env['session_id'] );
    }

    public function test_multiple_emits_produce_multiple_audit_entries(): void {
        $this->emitter->emit( 'generate.request',  array( 'service' => 'smma', 'session_id' => 's', 'prompt_hash' => 'h', 'variant_count_requested' => 1 ) );
        $this->emitter->emit( 'generate.response', array( 'service' => 'smma', 'variant_count_generated' => 1, 'latency_ms' => 50 ) );
        $this->emitter->emit( 'compliance.check',  array( 'service' => 'smma', 'variant_id' => 'v-1', 'outcome' => 'OK', 'rules_matched' => array() ) );

        $this->assertCount( 3, $this->captured_envelopes );
        $this->assertSame( 'generate.request',  $this->captured_envelopes[0]['event_name'] );
        $this->assertSame( 'generate.response', $this->captured_envelopes[1]['event_name'] );
        $this->assertSame( 'compliance.check',  $this->captured_envelopes[2]['event_name'] );
    }

    // -------------------------------------------------------------------------
    // Accumulator — single events
    // -------------------------------------------------------------------------

    public function test_handle_generate_request_increments_counter(): void {
        $this->emitter->emit( 'generate.request', array( 'service' => 'smma', 'session_id' => 's', 'prompt_hash' => 'h', 'variant_count_requested' => 1 ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['generate_requests'] );
    }

    public function test_handle_generate_response_increments_variants_and_latency(): void {
        $this->emitter->emit( 'generate.response', array( 'service' => 'smma', 'variant_count_generated' => 3, 'latency_ms' => 120 ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 3,   $acc['variants_created'] );
        $this->assertSame( 120, $acc['total_latency_ms'] );
        $this->assertSame( 1,   $acc['latency_count'] );
    }

    public function test_handle_compliance_check_ok_increments_ok_counter(): void {
        $this->emitter->emit( 'compliance.check', array( 'service' => 'smma', 'variant_id' => 'v-1', 'outcome' => 'OK', 'rules_matched' => array() ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['compliance_ok'] );
        $this->assertSame( 0, $acc['compliance_fail'] );
    }

    public function test_handle_compliance_check_fail_increments_fail_counter(): void {
        $this->emitter->emit( 'compliance.check', array( 'service' => 'smma', 'variant_id' => 'v-1', 'outcome' => 'FAIL', 'rules_matched' => array( 'prohibited_claim' ) ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 0, $acc['compliance_ok'] );
        $this->assertSame( 1, $acc['compliance_fail'] );
    }

    public function test_handle_variant_edit_increments_counter(): void {
        $this->emitter->emit( 'variant.edit', array( 'service' => 'smma', 'variant_id' => 'v-1', 'editor_id' => '1', 'revision_id' => 'rev-1' ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['variant_edits'] );
    }

    public function test_handle_schedule_create_increments_counter(): void {
        $this->emitter->emit( 'schedule.create', array( 'service' => 'smma', 'schedule_id' => 's-1', 'sponsor_id' => 'sp-1', 'approval_required' => false ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['schedule_created'] );
    }

    public function test_handle_schedule_dispatch_increments_counter(): void {
        $this->emitter->emit( 'schedule.dispatch', array( 'service' => 'smma', 'schedule_id' => 's-1', 'adapter' => 'manual', 'result' => 'dispatched' ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['schedule_dispatched'] );
    }

    public function test_handle_membership_signup_increments_counter(): void {
        $this->emitter->emit( 'membership.signup', array( 'service' => 'mem', 'user_id' => 99, 'tier' => 'standard', 'payment_status' => 'paid', 'attribution_id' => 'p-1' ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['membership_signups'] );
    }

    public function test_handle_promotion_attribution_increments_counter(): void {
        $this->emitter->emit( 'promotion_attribution', array( 'service' => 'mem', 'schedule_id' => 's-1', 'sponsor_id' => 'sp-1', 'utm_source' => 'linkedin', 'confidence_score' => 0.88 ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['promotion_attributions'] );
    }

    // -------------------------------------------------------------------------
    // Full generate→edit→schedule pipeline
    // -------------------------------------------------------------------------

    public function test_full_pipeline_generate_to_schedule(): void {
        // Emit the full pipeline sequence.
        $events = array(
            array( 'generate.request',  array( 'service' => 'smma', 'session_id' => 'p-sess', 'prompt_hash' => 'abc', 'variant_count_requested' => 2 ) ),
            array( 'generate.response', array( 'service' => 'smma', 'variant_count_generated' => 2, 'latency_ms' => 200 ) ),
            array( 'compliance.check',  array( 'service' => 'smma', 'variant_id' => 'v-a', 'outcome' => 'OK',   'rules_matched' => array() ) ),
            array( 'compliance.check',  array( 'service' => 'smma', 'variant_id' => 'v-b', 'outcome' => 'WARN', 'rules_matched' => array( 'cta_length' ) ) ),
            array( 'variant.edit',      array( 'service' => 'smma', 'variant_id' => 'v-b', 'editor_id' => '1', 'revision_id' => 'r1' ) ),
            array( 'schedule.create',   array( 'service' => 'smma', 'schedule_id' => 's-1', 'sponsor_id' => 'sp-1', 'approval_required' => true ) ),
            array( 'schedule.dispatch', array( 'service' => 'smma', 'schedule_id' => 's-1', 'adapter' => 'linkedin', 'result' => 'dispatched' ) ),
        );

        foreach ( $events as $idx => list( $name, $payload ) ) {
            $this->emitter->emit( $name, $payload );
            $this->feedback->handle_event( $this->captured_envelopes[ $idx ] );
        }

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 1, $acc['generate_requests'],   'generate_requests' );
        $this->assertSame( 2, $acc['variants_created'],    'variants_created' );
        $this->assertSame( 1, $acc['compliance_ok'],       'compliance_ok' );
        $this->assertSame( 1, $acc['compliance_warn'],     'compliance_warn' );
        $this->assertSame( 0, $acc['compliance_fail'],     'compliance_fail' );
        $this->assertSame( 1, $acc['variant_edits'],       'variant_edits' );
        $this->assertSame( 1, $acc['schedule_created'],    'schedule_created' );
        $this->assertSame( 1, $acc['schedule_dispatched'], 'schedule_dispatched' );
    }

    public function test_full_pipeline_all_envelopes_share_trace_id(): void {
        $events = array(
            array( 'generate.request',  array( 'service' => 'smma', 'session_id' => 's', 'prompt_hash' => 'h', 'variant_count_requested' => 1 ) ),
            array( 'generate.response', array( 'service' => 'smma', 'variant_count_generated' => 1, 'latency_ms' => 80 ) ),
            array( 'schedule.create',   array( 'service' => 'smma', 'schedule_id' => 's-1', 'sponsor_id' => 'sp-1', 'approval_required' => false ) ),
        );

        foreach ( $events as list( $name, $payload ) ) {
            $this->emitter->emit( $name, $payload );
        }

        foreach ( $this->captured_envelopes as $env ) {
            $this->assertSame( self::TRACE_ID, $env['trace_id'], "trace_id mismatch in {$env['event_name']}" );
        }
    }

    // -------------------------------------------------------------------------
    // flush_snapshot — aggregate metrics
    // -------------------------------------------------------------------------

    public function test_flush_snapshot_writes_correct_aggregate_metrics(): void {
        // Simulate a 5-minute window.
        $pipeline = array(
            array( 'generate.request',  array( 'service' => 'smma', 'session_id' => 's1', 'prompt_hash' => 'h1', 'variant_count_requested' => 2 ) ),
            array( 'generate.request',  array( 'service' => 'smma', 'session_id' => 's2', 'prompt_hash' => 'h2', 'variant_count_requested' => 1 ) ),
            array( 'generate.response', array( 'service' => 'smma', 'variant_count_generated' => 3, 'latency_ms' => 300 ) ),
            array( 'compliance.check',  array( 'service' => 'smma', 'variant_id' => 'v-1', 'outcome' => 'OK',   'rules_matched' => array() ) ),
            array( 'compliance.check',  array( 'service' => 'smma', 'variant_id' => 'v-2', 'outcome' => 'FAIL', 'rules_matched' => array( 'r' ) ) ),
            array( 'schedule.create',   array( 'service' => 'smma', 'schedule_id' => 's-1', 'sponsor_id' => 'sp-1', 'approval_required' => false ) ),
            array( 'schedule.dispatch', array( 'service' => 'smma', 'schedule_id' => 's-1', 'adapter' => 'manual', 'result' => 'dispatched' ) ),
        );

        foreach ( $pipeline as $idx => list( $name, $payload ) ) {
            $this->emitter->emit( $name, $payload );
            $this->feedback->handle_event( $this->captured_envelopes[ $idx ] );
        }

        $this->feedback->flush_snapshot();

        $this->assertCount( 1, $this->captured_snapshots, 'flush_snapshot must call write_snapshot once' );

        $metrics = $this->captured_snapshots[0];
        $this->assertSame( 2, $metrics['generate_requests'],   'generate_requests' );
        $this->assertSame( 3, $metrics['variants_created'],    'variants_created' );
        $this->assertSame( 1, $metrics['compliance_ok'],       'compliance_ok' );
        $this->assertSame( 0, $metrics['compliance_warn'],     'compliance_warn' );
        $this->assertSame( 1, $metrics['compliance_fail'],     'compliance_fail' );
        $this->assertSame( 1, $metrics['schedule_created'],    'schedule_created' );
        $this->assertSame( 1, $metrics['schedule_dispatched'], 'schedule_dispatched' );
        $this->assertSame( 300.0, $metrics['avg_generate_latency_ms'], 'avg latency' );
    }

    public function test_flush_snapshot_resets_accumulator(): void {
        $this->emitter->emit( 'generate.request', array( 'service' => 'smma', 'session_id' => 's', 'prompt_hash' => 'h', 'variant_count_requested' => 1 ) );
        $this->feedback->handle_event( $this->captured_envelopes[0] );

        $this->feedback->flush_snapshot();

        $acc = $this->feedback->get_accumulator();
        $this->assertSame( 0, $acc['generate_requests'], 'accumulator must reset after flush' );
    }

    // -------------------------------------------------------------------------
    // Envelope field passthrough
    // -------------------------------------------------------------------------

    public function test_event_specific_fields_pass_through_to_envelope(): void {
        $this->emitter->emit( 'compliance.check', array(
            'service'       => 'smma',
            'variant_id'    => 'v-abc',
            'outcome'       => 'WARN',
            'rules_matched' => array( 'cta_too_long', 'excessive_caps' ),
        ) );

        $env = $this->captured_envelopes[0];
        $this->assertSame( 'v-abc', $env['variant_id'] );
        $this->assertSame( 'WARN',  $env['outcome'] );
        $this->assertCount( 2,      $env['rules_matched'] );
    }

    public function test_mem_service_events_carry_mem_service_tag(): void {
        $this->emitter->emit( 'membership.signup', array(
            'service'        => 'mem',
            'user_id'        => 42,
            'tier'           => 'premium',
            'payment_status' => 'paid',
            'attribution_id' => 'promo-x',
        ) );

        $env = $this->captured_envelopes[0];
        $this->assertSame( 'mem', $env['service'] );
        $this->assertSame( 42,    $env['user_id'] );
    }
}
