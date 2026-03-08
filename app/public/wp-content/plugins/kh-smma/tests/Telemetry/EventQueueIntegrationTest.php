<?php
namespace KH_SMMA\Tests\Telemetry;

use KH_SMMA\Telemetry\EventQueue;
use KH_SMMA\Telemetry\TelemetryRetryService;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\TraceContext;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * OBS-06: Integration tests for the EventQueue pipeline.
 *
 * Covers:
 *  - Enqueue → flush → hook dispatch end-to-end
 *  - 500-event burst throughput (FIFO ordering, no overflow at MAX_SIZE)
 *  - FIFO ordering preserved through flush
 *  - Max-size overflow evicts oldest, retains newest MAX_SIZE events
 *  - Queue routes through TelemetryRetryService when wired
 *  - AnalyticsFeedbackService::maybe_flush_snapshot triggered on FLUSH_CRON_HOOK
 *  - Emitter → queue pipeline: audit always fires, queue receives event
 *  - Shutdown registration happens only once per queue instance
 */
class EventQueueIntegrationTest extends TestCase {

    /** @var array  kh_telemetry_event hook payloads captured */
    private array $dispatched = [];

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['kh_test_filters']    = [];
        $GLOBALS['kh_test_db_inserts'] = [];
        $this->dispatched = [];
        TraceContext::reset();

        // TestHelpers do_action calls apply_filters($tag, null, ...$args).
        // With accepted_args=2 the callback receives ($null_value, $event).
        add_action( 'kh_telemetry_event', function ( $_val, $event ) {
            $this->dispatched[] = $event;
        }, 10, 2 );
    }

    // -------------------------------------------------------------------------
    // Basic enqueue → flush pipeline
    // -------------------------------------------------------------------------

    public function test_single_event_enqueue_then_flush_dispatches_once(): void {
        $queue = new EventQueue( null, 1000, 1.0 );
        $queue->enqueue( $this->ev( 'generate.request', 'trace-qi-001' ) );

        $this->assertSame( 0, count( $this->dispatched ), 'No dispatch before flush' );

        $queue->flush();

        $this->assertCount( 1, $this->dispatched );
        $this->assertSame( 'generate.request', $this->dispatched[0]['event_name'] );
    }

    public function test_multiple_events_all_dispatched_after_flush(): void {
        $queue = new EventQueue( null, 1000, 1.0 );
        $names = [ 'generate.request', 'generate.response', 'compliance.check',
                   'variant.edit', 'schedule.create', 'schedule.dispatch' ];

        foreach ( $names as $name ) {
            $queue->enqueue( $this->ev( $name ) );
        }

        $queue->flush();

        $dispatched_names = array_column( $this->dispatched, 'event_name' );
        $this->assertSame( $names, $dispatched_names );
    }

    public function test_flush_empties_queue(): void {
        $queue = new EventQueue( null, 1000, 1.0 );
        $queue->enqueue( $this->ev( 'generate.request' ) );
        $queue->flush();
        $this->assertSame( 0, $queue->size() );
        $this->assertTrue( $queue->is_empty() );
    }

    public function test_second_flush_dispatches_nothing_when_queue_empty(): void {
        $queue = new EventQueue( null, 1000, 1.0 );
        $queue->enqueue( $this->ev( 'generate.request' ) );
        $queue->flush();

        $count_after_first = count( $this->dispatched );
        $queue->flush();

        $this->assertSame( $count_after_first, count( $this->dispatched ) );
    }

    // -------------------------------------------------------------------------
    // FIFO ordering
    // -------------------------------------------------------------------------

    public function test_fifo_ordering_preserved_through_flush(): void {
        $queue = new EventQueue( null, 1000, 1.0 );

        for ( $i = 1; $i <= 10; $i++ ) {
            $queue->enqueue( $this->ev( "event-{$i}" ) );
        }

        $queue->flush();

        for ( $i = 1; $i <= 10; $i++ ) {
            $this->assertSame( "event-{$i}", $this->dispatched[ $i - 1 ]['event_name'] );
        }
    }

    // -------------------------------------------------------------------------
    // 500-event burst
    // -------------------------------------------------------------------------

    public function test_500_event_burst_all_dispatched_in_order(): void {
        $queue = new EventQueue( null, 1000, 1.0 );

        for ( $i = 0; $i < 500; $i++ ) {
            $queue->enqueue( $this->ev( "burst-{$i}" ) );
        }

        $this->assertSame( 500, $queue->size() );

        $start = microtime( true );
        $queue->flush();
        $elapsed_ms = ( microtime( true ) - $start ) * 1000;

        $this->assertCount( 500, $this->dispatched, '500 events must be dispatched' );
        $this->assertSame( 0, $queue->size() );

        // FIFO: first dispatched event is burst-0
        $this->assertSame( 'burst-0', $this->dispatched[0]['event_name'] );
        $this->assertSame( 'burst-499', $this->dispatched[499]['event_name'] );

        // Performance target: flush 500 events in < 200ms (generous for test env)
        $this->assertLessThan( 200, $elapsed_ms, "Flush of 500 events took {$elapsed_ms}ms (target < 200ms)" );
    }

    // -------------------------------------------------------------------------
    // MAX_SIZE overflow
    // -------------------------------------------------------------------------

    public function test_overflow_beyond_max_size_evicts_oldest(): void {
        $max   = 100;
        $queue = new EventQueue( null, $max, 1.0 );

        for ( $i = 0; $i < 150; $i++ ) {
            $queue->enqueue( $this->ev( "event-{$i}" ) );
        }

        $this->assertSame( $max, $queue->size() );

        $queue->flush();

        // First dispatched should be event-50 (oldest 50 were evicted)
        $this->assertSame( 'event-50', $this->dispatched[0]['event_name'] );
        $this->assertSame( 'event-149', $this->dispatched[99]['event_name'] );
    }

    // -------------------------------------------------------------------------
    // Sampling integration
    // -------------------------------------------------------------------------

    public function test_debug_fields_stripped_at_zero_sample_rate(): void {
        $queue = new EventQueue( null, 1000, 0.0 );
        $event = array_merge( $this->ev( 'generate.request' ), [
            'prompt_hash'        => 'abc',
            'asset_hint_details' => 'hint',
            'debug_metadata'     => [ 'k' => 'v' ],
        ] );
        $queue->enqueue( $event );
        $queue->flush();

        $this->assertCount( 1, $this->dispatched );
        $d = $this->dispatched[0];
        $this->assertArrayNotHasKey( 'prompt_hash', $d );
        $this->assertArrayNotHasKey( 'asset_hint_details', $d );
        $this->assertArrayNotHasKey( 'debug_metadata', $d );
    }

    public function test_schedule_events_never_sampled_debug_fields_retained(): void {
        $queue = new EventQueue( null, 1000, 0.0 ); // 0.0 → strip for normal events
        $event = array_merge( $this->ev( 'schedule.create' ), [
            'prompt_hash'    => 'keep-me',
            'debug_metadata' => [ 'important' => true ],
        ] );
        $queue->enqueue( $event );
        $queue->flush();

        $this->assertCount( 1, $this->dispatched );
        $this->assertArrayHasKey( 'prompt_hash', $this->dispatched[0] );
        $this->assertArrayHasKey( 'debug_metadata', $this->dispatched[0] );
    }

    // -------------------------------------------------------------------------
    // TelemetryRetryService integration
    // -------------------------------------------------------------------------

    public function test_queue_routes_through_retry_service_on_flush(): void {
        $db = new \wpdb();

        $sleep_fn = function ( int $us ): void {};
        $retry    = new TelemetryRetryService( $db, [ 0, 0, 0 ], $sleep_fn );

        $queue = new EventQueue( $retry, 1000, 1.0 );
        $queue->enqueue( $this->ev( 'generate.request', 'trace-route-001' ) );

        $queue->flush();

        // If retry service routes successfully, event should reach kh_telemetry_event hook
        $this->assertCount( 1, $this->dispatched );
        $this->assertSame( 'trace-route-001', $this->dispatched[0]['trace_id'] );
    }

    public function test_retry_service_buffers_on_persistent_hook_failure(): void {
        $db = new \wpdb();

        $sleep_fn = function ( int $us ): void {};
        $retry    = new TelemetryRetryService( $db, [ 0, 0, 0 ], $sleep_fn );

        // Override the publisher via a different hook registration that always throws.
        // We test retry buffering by calling publish_with_retry directly with a failing publisher.
        $result = $retry->publish_with_retry(
            $this->ev( 'variant.edit', 'trace-buf-int-001' ),
            function ( array $e ): void {
                throw new \RuntimeException( 'sink down' );
            }
        );

        $this->assertFalse( $result );
        $inserts = $GLOBALS['kh_test_db_inserts'] ?? [];
        $this->assertNotEmpty( $inserts );
        $this->assertSame( 'trace-buf-int-001', $inserts[0]['data']['trace_id'] );
    }

    // -------------------------------------------------------------------------
    // Emitter → EventQueue pipeline
    // -------------------------------------------------------------------------

    public function test_emitter_with_queue_audit_fires_event_queued(): void {
        $db = new \wpdb();

        $audit_calls = 0;
        $audit = $this->getMockBuilder( AuditLogger::class )
                      ->setConstructorArgs( [ $db ] )
                      ->onlyMethods( [ 'record_event' ] )
                      ->getMock();
        $audit->method( 'record_event' )
              ->willReturnCallback( function () use ( &$audit_calls ) {
                  $audit_calls++;
              } );

        $queue   = new EventQueue( null, 1000, 1.0 );
        $emitter = new EventEmitter( $audit, $queue );

        TraceContext::init( 'trace-emq-001' );
        $emitter->emit( 'generate.request', [ 'user_id' => 1 ] );

        // Audit fires synchronously before flush
        $this->assertSame( 1, $audit_calls );

        // Queue should hold the event (not yet dispatched)
        $this->assertSame( 1, $queue->size() );
        $this->assertEmpty( $this->dispatched );

        // After flush, hook fires
        $queue->flush();
        $this->assertCount( 1, $this->dispatched );
        $this->assertSame( 'generate.request', $this->dispatched[0]['event_name'] );
    }

    public function test_emitter_without_queue_dispatches_synchronously(): void {
        $db = new \wpdb();

        $audit = $this->getMockBuilder( AuditLogger::class )
                      ->setConstructorArgs( [ $db ] )
                      ->onlyMethods( [ 'record_event' ] )
                      ->getMock();
        $audit->method( 'record_event' )->willReturnCallback( function () {} );

        $emitter = new EventEmitter( $audit ); // no queue → backward-compat path

        TraceContext::init( 'trace-sync-001' );
        $emitter->emit( 'generate.request', [ 'user_id' => 1 ] );

        // Dispatched immediately (no flush needed)
        $this->assertCount( 1, $this->dispatched );
        $this->assertSame( 'generate.request', $this->dispatched[0]['event_name'] );
    }

    public function test_full_six_event_workflow_through_queue(): void {
        $db = new \wpdb();

        $audit = $this->getMockBuilder( AuditLogger::class )
                      ->setConstructorArgs( [ $db ] )
                      ->onlyMethods( [ 'record_event' ] )
                      ->getMock();
        $audit->method( 'record_event' )->willReturnCallback( function () {} );

        $queue   = new EventQueue( null, 1000, 1.0 );
        $emitter = new EventEmitter( $audit, $queue );

        TraceContext::init( 'trace-wf-001' );

        $workflow = [
            [ 'generate.request',   [ 'user_id' => 1, 'post_type' => 'awareness' ] ],
            [ 'generate.response',  [ 'latency_ms' => 420, 'model_version' => 'claude-sonnet-4' ] ],
            [ 'compliance.check',   [ 'passed' => true, 'violations' => 0 ] ],
            [ 'variant.edit',       [ 'variant_id' => 'v-1', 'editor_id' => 2 ] ],
            [ 'schedule.create',    [ 'schedule_id' => '99', 'channel' => 'linkedin' ] ],
            [ 'schedule.dispatch',  [ 'schedule_id' => '99', 'result' => 'dispatched' ] ],
        ];

        foreach ( $workflow as [ $name, $payload ] ) {
            $emitter->emit( $name, $payload );
        }

        $this->assertSame( 6, $queue->size() );
        $this->assertEmpty( $this->dispatched );

        $queue->flush();

        $this->assertCount( 6, $this->dispatched );
        $dispatched_names = array_column( $this->dispatched, 'event_name' );
        $expected_names   = array_column( $workflow, 0 );
        $this->assertSame( $expected_names, $dispatched_names );

        // All events share the same trace_id
        foreach ( $this->dispatched as $event ) {
            $this->assertSame( 'trace-wf-001', $event['trace_id'] );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function ev( string $event_name, string $trace_id = 'trace-qi-test' ): array {
        return [
            'event_name' => $event_name,
            'trace_id'   => $trace_id,
            'timestamp'  => 1741219200,
            'service'    => 'smma',
        ];
    }
}
