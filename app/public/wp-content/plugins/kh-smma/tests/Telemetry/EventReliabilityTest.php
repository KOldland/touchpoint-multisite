<?php
namespace KH_SMMA\Tests\Telemetry;

use KH_SMMA\Telemetry\TelemetryRetryService;
use KH_SMMA\Telemetry\EventQueue;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\TraceContext;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * OBS-06: Unit tests for TelemetryRetryService and EventQueue reliability guarantees.
 *
 * Covers:
 *  - Retry attempts and backoff delay sequencing
 *  - Fallback buffer persistence when all retries exhausted
 *  - Audit completeness regardless of queue/publisher failures
 *  - Debug field sampling at sample_rate=0.0 and sample_rate=1.0
 *  - NEVER_SAMPLE_PREFIXES bypass sampling entirely
 *  - EventQueue bounded FIFO overflow eviction
 */
class EventReliabilityTest extends TestCase {

    /** @var \wpdb */
    private $db;

    /** @var array  DB inserts captured during test */
    private array $db_inserts = [];

    /** @var array  Sleep calls recorded by injectable sleep_fn */
    private array $sleep_calls = [];

    /** @var array  AuditLogger::record_event calls */
    private array $audit_calls = [];

    /** @var array  Publisher invocations */
    private array $publisher_calls = [];

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['kh_test_filters']   = [];
        $GLOBALS['kh_test_db_inserts'] = [];
        $this->db_inserts    = [];
        $this->sleep_calls   = [];
        $this->audit_calls   = [];
        $this->publisher_calls = [];
        TraceContext::reset();
    }

    // -------------------------------------------------------------------------
    // TelemetryRetryService — publish_with_retry
    // -------------------------------------------------------------------------

    public function test_publish_succeeds_on_first_attempt(): void {
        $retry = $this->make_retry_service();

        $called = 0;
        $publisher = function ( array $event ) use ( &$called ): void {
            $called++;
        };

        $result = $retry->publish_with_retry( $this->make_event(), $publisher );

        $this->assertTrue( $result );
        $this->assertSame( 1, $called );
        $this->assertEmpty( $this->sleep_calls );
        $this->assertEmpty( $GLOBALS['kh_test_db_inserts'] );
    }

    public function test_publish_retries_on_first_failure_succeeds_second(): void {
        $retry = $this->make_retry_service();

        $attempt = 0;
        $publisher = function ( array $event ) use ( &$attempt ): void {
            $attempt++;
            if ( $attempt === 1 ) {
                throw new \RuntimeException( 'sink unavailable' );
            }
        };

        $result = $retry->publish_with_retry( $this->make_event(), $publisher );

        $this->assertTrue( $result );
        $this->assertSame( 2, $attempt );
        // First attempt has 0µs delay; second attempt records the delay before attempt 2
        // backoff_us[1] = 250000
        $this->assertCount( 1, $this->sleep_calls );
        $this->assertSame( 250000, $this->sleep_calls[0] );
    }

    public function test_all_attempts_fail_buffers_event(): void {
        $retry = $this->make_retry_service();

        $publisher = function ( array $event ): void {
            throw new \RuntimeException( 'permanent failure' );
        };

        $event  = $this->make_event( 'variant.edit', 'trace-buf-001' );
        $result = $retry->publish_with_retry( $event, $publisher );

        $this->assertFalse( $result );

        // Buffer insert should have been recorded
        $inserts = $GLOBALS['kh_test_db_inserts'] ?? [];
        $this->assertNotEmpty( $inserts );

        $insert = $inserts[0];
        $this->assertStringContainsString( 'kh_smma_telemetry_buffer', $insert['table'] );
        $this->assertSame( 'trace-buf-001', $insert['data']['trace_id'] );
        $this->assertSame( 'variant.edit', $insert['data']['event_name'] );
        $payload = json_decode( $insert['data']['payload'], true );
        $this->assertSame( 'trace-buf-001', $payload['trace_id'] );
    }

    public function test_backoff_delays_applied_in_order(): void {
        $retry = $this->make_retry_service( [ 0, 111111, 222222 ] );

        $publisher = function ( array $event ): void {
            throw new \RuntimeException( 'fail' );
        };

        $retry->publish_with_retry( $this->make_event(), $publisher );

        // attempt 0 → delay 0 (not recorded by sleep_fn)
        // attempt 1 → delay 111111
        // attempt 2 → delay 222222
        $this->assertSame( [ 111111, 222222 ], $this->sleep_calls );
    }

    public function test_zero_delay_attempts_produce_no_sleep_calls(): void {
        $retry = $this->make_retry_service( [ 0, 0, 0 ] );

        $publisher = function ( array $event ): void {
            throw new \RuntimeException( 'fail' );
        };

        $retry->publish_with_retry( $this->make_event(), $publisher );

        $this->assertEmpty( $this->sleep_calls );
    }

    public function test_buffer_event_stores_correct_fields(): void {
        $retry = $this->make_retry_service();
        $event = $this->make_event( 'schedule.create', 'trace-sch-99' );

        $retry->buffer_event( $event );

        $inserts = $GLOBALS['kh_test_db_inserts'] ?? [];
        $this->assertNotEmpty( $inserts );
        $data = $inserts[0]['data'];
        $this->assertSame( 'trace-sch-99', $data['trace_id'] );
        $this->assertSame( 'schedule.create', $data['event_name'] );
        $this->assertSame( 0, $data['attempts'] );
        $this->assertStringStartsWith( '2026', $data['created_at'] );
    }

    // -------------------------------------------------------------------------
    // TelemetryRetryService — replay_buffered_events
    // -------------------------------------------------------------------------

    public function test_replay_returns_zero_when_no_rows(): void {
        $db_mock = $this->createMock( \wpdb::class );
        $db_mock->prefix = 'wp_';
        $db_mock->method( 'get_results' )->willReturn( [] );
        $db_mock->method( 'prepare' )->willReturnArgument( 0 );

        $retry = new TelemetryRetryService( $db_mock, [ 0, 0, 0 ], $this->noop_sleep() );

        $replayed = $retry->replay_buffered_events( function ( array $e ): void {} );
        $this->assertSame( 0, $replayed );
    }

    public function test_replay_deletes_successful_rows(): void {
        $row = [
            'id'         => 7,
            'trace_id'   => 'trace-rpl-007',
            'event_name' => 'generate.request',
            'payload'    => json_encode( [ 'event_name' => 'generate.request', 'trace_id' => 'trace-rpl-007' ] ),
            'attempts'   => 0,
        ];

        $deleted_ids = [];
        $db_mock = $this->createMock( \wpdb::class );
        $db_mock->prefix = 'wp_';
        $db_mock->method( 'get_results' )->willReturn( [ $row ] );
        $db_mock->method( 'prepare' )->willReturnArgument( 0 );
        $db_mock->method( 'query' )->willReturnCallback( function ( $q ) use ( &$deleted_ids ) {
            if ( str_contains( $q, 'DELETE' ) ) {
                $deleted_ids[] = $q;
            }
            return true;
        } );

        $retry    = new TelemetryRetryService( $db_mock, [ 0, 0, 0 ], $this->noop_sleep() );
        $replayed = $retry->replay_buffered_events( function ( array $e ): void {} );

        $this->assertSame( 1, $replayed );
        $this->assertNotEmpty( $deleted_ids );
    }

    public function test_replay_increments_attempt_on_failure(): void {
        $row = [
            'id'         => 3,
            'trace_id'   => 'trace-fail',
            'event_name' => 'alert.triggered',
            'payload'    => json_encode( [ 'event_name' => 'alert.triggered' ] ),
            'attempts'   => 1,
        ];

        $update_queries = [];
        $db_mock = $this->createMock( \wpdb::class );
        $db_mock->prefix = 'wp_';
        $db_mock->method( 'get_results' )->willReturn( [ $row ] );
        $db_mock->method( 'prepare' )->willReturnArgument( 0 );
        $db_mock->method( 'query' )->willReturnCallback( function ( $q ) use ( &$update_queries ) {
            if ( str_contains( $q, 'UPDATE' ) ) {
                $update_queries[] = $q;
            }
            return true;
        } );

        $retry    = new TelemetryRetryService( $db_mock, [ 0, 0, 0 ], $this->noop_sleep() );
        $replayed = $retry->replay_buffered_events( function ( array $e ): void {
            throw new \RuntimeException( 'still failing' );
        } );

        $this->assertSame( 0, $replayed );
        $this->assertNotEmpty( $update_queries );
    }

    // -------------------------------------------------------------------------
    // EventQueue — enqueue / flush / sampling
    // -------------------------------------------------------------------------

    public function test_enqueue_increases_size(): void {
        $queue = new EventQueue( null, 100, 1.0 );
        $this->assertSame( 0, $queue->size() );
        $queue->enqueue( $this->make_event() );
        $this->assertSame( 1, $queue->size() );
    }

    public function test_flush_drains_queue(): void {
        $queue = new EventQueue( null, 100, 1.0 );
        $queue->enqueue( $this->make_event( 'generate.request' ) );
        $queue->enqueue( $this->make_event( 'schedule.create' ) );

        $dispatched = [];
        add_action( 'kh_telemetry_event', function ( $_val, $e ) use ( &$dispatched ) {
            $dispatched[] = $e;
        }, 10, 2 );

        $queue->flush();

        $this->assertSame( 0, $queue->size() );
        $this->assertCount( 2, $dispatched );
    }

    public function test_flush_is_idempotent_when_empty(): void {
        $queue = new EventQueue( null, 100, 1.0 );
        $queue->flush();
        $queue->flush();
        $this->assertSame( 0, $queue->size() );
    }

    public function test_overflow_evicts_oldest_event(): void {
        $queue = new EventQueue( null, 3, 1.0 );
        $queue->enqueue( $this->make_event( 'ev-1' ) );
        $queue->enqueue( $this->make_event( 'ev-2' ) );
        $queue->enqueue( $this->make_event( 'ev-3' ) );
        $queue->enqueue( $this->make_event( 'ev-4' ) ); // should evict ev-1

        $dispatched = [];
        add_action( 'kh_telemetry_event', function ( $_val, $e ) use ( &$dispatched ) {
            $dispatched[] = $e['event_name'];
        }, 10, 2 );

        $queue->flush();

        $this->assertSame( [ 'ev-2', 'ev-3', 'ev-4' ], $dispatched );
    }

    public function test_max_size_enforced_exactly(): void {
        $max   = 5;
        $queue = new EventQueue( null, $max, 1.0 );
        for ( $i = 0; $i < 10; $i++ ) {
            $queue->enqueue( $this->make_event( "ev-{$i}" ) );
        }
        $this->assertSame( $max, $queue->size() );
    }

    // -------------------------------------------------------------------------
    // EventQueue — sampling
    // -------------------------------------------------------------------------

    public function test_sample_rate_zero_strips_debug_fields(): void {
        $queue = new EventQueue( null, 100, 0.0 );
        $event = array_merge( $this->make_event( 'generate.request' ), [
            'prompt_hash'        => 'abc123',
            'asset_hint_details' => 'some hints',
            'debug_metadata'     => [ 'foo' => 'bar' ],
        ] );

        $sampled = $queue->apply_sampling( $event );

        $this->assertArrayNotHasKey( 'prompt_hash', $sampled );
        $this->assertArrayNotHasKey( 'asset_hint_details', $sampled );
        $this->assertArrayNotHasKey( 'debug_metadata', $sampled );
    }

    public function test_sample_rate_one_retains_debug_fields(): void {
        $queue = new EventQueue( null, 100, 1.0 );
        $event = array_merge( $this->make_event( 'generate.request' ), [
            'prompt_hash'    => 'abc123',
            'debug_metadata' => [ 'foo' => 'bar' ],
        ] );

        $sampled = $queue->apply_sampling( $event );

        $this->assertArrayHasKey( 'prompt_hash', $sampled );
        $this->assertArrayHasKey( 'debug_metadata', $sampled );
    }

    public function test_never_sampled_prefixes_bypass_sampling(): void {
        $queue = new EventQueue( null, 100, 0.0 ); // would strip at 0.0 for normal events

        $never_sample_names = [
            'schedule.create',
            'variant.edit.approved',
            'sponsor.approval.granted',
            'membership.signup',
            'alert.triggered',
        ];

        foreach ( $never_sample_names as $name ) {
            $event = array_merge( $this->make_event( $name ), [
                'prompt_hash'    => 'preserved',
                'debug_metadata' => [ 'keep' => true ],
            ] );
            $sampled = $queue->apply_sampling( $event );
            $this->assertArrayHasKey( 'prompt_hash', $sampled, "prompt_hash stripped for {$name}" );
            $this->assertArrayHasKey( 'debug_metadata', $sampled, "debug_metadata stripped for {$name}" );
        }
    }

    public function test_normal_events_subject_to_sampling(): void {
        $queue = new EventQueue( null, 100, 0.0 );
        $event = array_merge( $this->make_event( 'generate.request' ), [
            'prompt_hash' => 'should-be-removed',
        ] );

        $sampled = $queue->apply_sampling( $event );
        $this->assertArrayNotHasKey( 'prompt_hash', $sampled );
    }

    // -------------------------------------------------------------------------
    // Audit completeness guarantee
    // -------------------------------------------------------------------------

    public function test_audit_fires_even_when_queue_enqueue_throws(): void {
        $db = new \wpdb();
        $audit = $this->getMockBuilder( AuditLogger::class )
                      ->setConstructorArgs( [ $db ] )
                      ->onlyMethods( [ 'record_event' ] )
                      ->getMock();

        $audit_calls = 0;
        $audit->method( 'record_event' )->willReturnCallback( function () use ( &$audit_calls ) {
            $audit_calls++;
        } );

        // Queue that always throws on enqueue
        $bad_queue = new class( null, 100, 1.0 ) extends EventQueue {
            public function enqueue( array $event ): void {
                throw new \RuntimeException( 'queue exploded' );
            }
        };

        TraceContext::init( 'trace-audit-guarantee' );
        $emitter = new EventEmitter( $audit, $bad_queue );
        $emitter->emit( 'generate.request', [ 'user_id' => 1 ] );

        $this->assertSame( 1, $audit_calls, 'Audit must fire even when queue throws' );
    }

    public function test_audit_fires_even_when_publisher_throws(): void {
        $db = new \wpdb();
        $audit = $this->getMockBuilder( AuditLogger::class )
                      ->setConstructorArgs( [ $db ] )
                      ->onlyMethods( [ 'record_event' ] )
                      ->getMock();

        $audit_calls = 0;
        $audit->method( 'record_event' )->willReturnCallback( function () use ( &$audit_calls ) {
            $audit_calls++;
        } );

        TraceContext::init( 'trace-audit-publisher-fail' );
        // No queue — direct dispatch path; hook throws
        add_action( 'kh_telemetry_event', function () {
            throw new \RuntimeException( 'hook exploded' );
        }, 10, 1 );

        $emitter = new EventEmitter( $audit );
        $emitter->emit( 'generate.request', [ 'user_id' => 1 ] );

        $this->assertSame( 1, $audit_calls, 'Audit must fire even when hook throws' );
    }

    // -------------------------------------------------------------------------
    // Fixture parity
    // -------------------------------------------------------------------------

    public function test_retry_cases_fixture_is_valid(): void {
        $path = dirname( __DIR__ ) . '/fixtures/telemetry/retry_cases.json';
        $this->assertFileExists( $path );

        $data = json_decode( file_get_contents( $path ), true );
        $this->assertNotNull( $data, 'retry_cases.json must be valid JSON' );
        $this->assertArrayHasKey( 'cases', $data );
        $this->assertArrayHasKey( 'retry_success_case', $data['cases'] );
        $this->assertArrayHasKey( 'retry_failure_case', $data['cases'] );
        $this->assertArrayHasKey( 'fallback_buffer_case', $data['cases'] );
    }

    public function test_fixture_retry_success_case_matches_service_behavior(): void {
        $path     = dirname( __DIR__ ) . '/fixtures/telemetry/retry_cases.json';
        $fixture  = json_decode( file_get_contents( $path ), true );
        $case     = $fixture['cases']['retry_success_case'];
        $expected = $case['expected'];

        $retry  = $this->make_retry_service( $case['input']['backoff_us'] );
        $called = 0;
        $result = $retry->publish_with_retry(
            $case['input']['event'],
            function ( array $e ) use ( &$called ): void { $called++; }
        );

        $this->assertSame( $expected['published'], $result );
        $this->assertSame( $expected['attempts_made'], $called );
        $this->assertSame( $expected['sleep_calls'], count( $this->sleep_calls ) );
        $this->assertSame( $expected['buffered'], ! empty( $GLOBALS['kh_test_db_inserts'] ) );
    }

    public function test_fixture_fallback_buffer_case_matches_service_behavior(): void {
        $path    = dirname( __DIR__ ) . '/fixtures/telemetry/retry_cases.json';
        $fixture = json_decode( file_get_contents( $path ), true );
        $case    = $fixture['cases']['fallback_buffer_case'];
        $expected = $case['expected'];

        $retry  = $this->make_retry_service( $case['input']['backoff_us'] );
        $result = $retry->publish_with_retry(
            $case['input']['event'],
            function ( array $e ): void { throw new \RuntimeException( 'always fails' ); }
        );

        $this->assertSame( $expected['published'], $result );
        $this->assertTrue( $expected['buffered'] );

        $inserts = $GLOBALS['kh_test_db_inserts'] ?? [];
        $this->assertNotEmpty( $inserts );
        $this->assertSame( $expected['buffered_event_name'], $inserts[0]['data']['event_name'] );
        $this->assertSame( $expected['buffered_trace_id'],   $inserts[0]['data']['trace_id'] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function make_retry_service( array $backoff_us = [ 0, 250000, 1000000 ] ): TelemetryRetryService {
        $db = new \wpdb();

        $sleep_calls = &$this->sleep_calls;
        $sleep_fn    = function ( int $us ) use ( &$sleep_calls ): void {
            if ( $us > 0 ) {
                $sleep_calls[] = $us;
            }
        };

        return new TelemetryRetryService( $db, $backoff_us, $sleep_fn );
    }

    private function make_event( string $event_name = 'generate.request', string $trace_id = 'trace-test-001' ): array {
        return [
            'event_name' => $event_name,
            'trace_id'   => $trace_id,
            'timestamp'  => 1741219200,
            'service'    => 'smma',
        ];
    }

    private function noop_sleep(): callable {
        return function ( int $us ): void {};
    }
}
