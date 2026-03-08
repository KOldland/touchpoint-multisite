<?php
/**
 * OBS-CARD-01: Trace ID propagation tests.
 *
 * Verifies that TraceContext:
 *  - generates a UUID v4 when no trace_id is supplied
 *  - preserves a caller-supplied trace_id unchanged
 *  - shares the same trace_id across multiple emit() calls in one workflow
 *  - resets cleanly between requests
 *
 * Uses fixture: tests/fixtures/telemetry/trace_propagation_fixture.json
 */

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/TraceContext.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/EventEmitter.php';

use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\TraceContext;
use PHPUnit\Framework\TestCase;

class TracePropagationTest extends TestCase {

	/** @var array */
	private array $captured = array();

	/** @var EventEmitter */
	private $emitter;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['kh_test_filters']    = array();
		$GLOBALS['kh_test_db_inserts'] = array();
		$this->captured                = array();

		$db    = new wpdb();
		$audit = $this->getMockBuilder( AuditLogger::class )
		              ->setConstructorArgs( array( $db ) )
		              ->onlyMethods( array( 'record_event' ) )
		              ->getMock();

		$captured = &$this->captured;
		$audit->method( 'record_event' )
		      ->willReturnCallback( function ( $trace_id, $event_name, $timestamp, $payload ) use ( &$captured ) {
		          $captured[] = $payload;
		      } );

		$this->emitter = new EventEmitter( $audit );

		TraceContext::reset();
	}

	protected function tearDown(): void {
		TraceContext::reset();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// TraceContext unit tests
	// -------------------------------------------------------------------------

	public function test_init_generates_uuid_when_no_id_supplied(): void {
		$trace_id = TraceContext::init();
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$trace_id,
			'Generated trace_id is not a valid UUID v4 — or uses fallback format which is also acceptable'
		);
	}

	public function test_init_preserves_caller_supplied_trace_id(): void {
		$supplied = 'my-custom-trace-id-00001';
		$result   = TraceContext::init( $supplied );
		$this->assertSame( $supplied, $result );
		$this->assertSame( $supplied, TraceContext::current() );
	}

	public function test_current_returns_null_before_init(): void {
		TraceContext::reset();
		$this->assertNull( TraceContext::current() );
	}

	public function test_reset_clears_trace_id(): void {
		TraceContext::init( 'should-be-cleared' );
		TraceContext::reset();
		$this->assertNull( TraceContext::current() );
	}

	public function test_require_current_auto_generates_when_unset(): void {
		TraceContext::reset();
		$id = TraceContext::require_current();
		$this->assertNotEmpty( $id );
		$this->assertSame( $id, TraceContext::current() );
	}

	// -------------------------------------------------------------------------
	// Propagation across multiple emit() calls (same workflow)
	// -------------------------------------------------------------------------

	public function test_trace_id_is_constant_across_generate_workflow(): void {
		TraceContext::init( 'workflow-trace-ABC' );

		$this->emitter->emit( 'generate.request', array(
			'session_id'              => 'req_001',
			'prompt_hash'             => 'ph1',
			'variant_count_requested' => 1,
			'service'                 => 'smma',
		) );
		$this->emitter->emit( 'generate.response', array(
			'session_id'              => 'req_001',
			'variant_count_generated' => 1,
			'latency_ms'              => 200,
			'service'                 => 'smma',
		) );
		$this->emitter->emit( 'compliance.check', array(
			'variant_id'   => 'v-001',
			'outcome'      => 'OK',
			'rules_matched' => array(),
			'service'      => 'smma',
		) );

		$this->assertCount( 3, $this->captured );
		foreach ( $this->captured as $event ) {
			$this->assertSame( 'workflow-trace-ABC', $event['trace_id'], "trace_id mismatch in event: {$event['event_name']}" );
		}
	}

	public function test_trace_id_is_constant_across_schedule_workflow(): void {
		TraceContext::init( 'schedule-trace-XYZ' );

		$this->emitter->emit( 'schedule.create', array(
			'schedule_id'      => 'sched-1',
			'sponsor_id'       => 'sp-1',
			'approval_required' => false,
			'service'          => 'smma',
		) );
		$this->emitter->emit( 'schedule.dispatch', array(
			'schedule_id' => 'sched-1',
			'adapter'     => 'manual',
			'result'      => 'dispatched',
			'service'     => 'smma',
		) );

		$this->assertCount( 2, $this->captured );
		foreach ( $this->captured as $event ) {
			$this->assertSame( 'schedule-trace-XYZ', $event['trace_id'] );
		}
	}

	public function test_full_workflow_trace_propagation_matches_fixture(): void {
		$fixture_path = dirname( __DIR__ ) . '/fixtures/telemetry/trace_propagation_fixture.json';
		$fixture      = json_decode( file_get_contents( $fixture_path ), true );

		$expected_trace = $fixture['trace_id'];
		TraceContext::init( $expected_trace );

		foreach ( $fixture['events'] as $spec ) {
			$this->emitter->emit( $spec['event_name'], array_merge( $spec['payload'], array( 'service' => $spec['service'] ) ) );
		}

		$this->assertCount( count( $fixture['events'] ), $this->captured );
		foreach ( $this->captured as $i => $event ) {
			$this->assertSame( $expected_trace, $event['trace_id'], "Event #{$i} has wrong trace_id" );
			$this->assertSame( $fixture['events'][ $i ]['event_name'], $event['event_name'] );
		}
	}

	public function test_trace_id_isolated_between_requests(): void {
		// First request.
		TraceContext::init( 'request-one' );
		$this->emitter->emit( 'generate.request', array( 'service' => 'smma' ) );
		$this->assertSame( 'request-one', $this->captured[0]['trace_id'] );

		// Simulate next request: reset and reinit.
		TraceContext::reset();
		TraceContext::init( 'request-two' );
		$this->emitter->emit( 'generate.request', array( 'service' => 'smma' ) );
		$this->assertSame( 'request-two', $this->captured[1]['trace_id'] );

		$this->assertNotSame( $this->captured[0]['trace_id'], $this->captured[1]['trace_id'] );
	}
}
