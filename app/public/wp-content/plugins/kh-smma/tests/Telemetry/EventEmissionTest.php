<?php
/**
 * OBS-CARD-01: Event emission unit tests.
 *
 * Verifies that EventEmitter:
 *  - emits the correct event_name
 *  - attaches trace_id and timestamp to every event
 *  - persists each event via AuditLogger::record_event()
 *  - fires the kh_telemetry_event WordPress action
 *  - never throws (non-blocking contract)
 */

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/TraceContext.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/EventEmitter.php';

use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\TraceContext;
use PHPUnit\Framework\TestCase;

class EventEmissionTest extends TestCase {

	/** @var array Captured calls to AuditLogger::record_event */
	private array $recorded_events = array();

	/** @var array Captured kh_telemetry_event action payloads */
	private array $action_events = array();

	/** @var AuditLogger */
	private $audit;

	/** @var EventEmitter */
	private $emitter;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['kh_test_filters']  = array();
		$GLOBALS['kh_test_db_inserts'] = array();

		$this->recorded_events = array();
		$this->action_events   = array();

		// Build a stub AuditLogger that captures record_event calls.
		$db          = new wpdb();
		$this->audit = $this->getMockBuilder( AuditLogger::class )
		                    ->setConstructorArgs( array( $db ) )
		                    ->onlyMethods( array( 'record_event' ) )
		                    ->getMock();

		$captured = &$this->recorded_events;
		$this->audit->method( 'record_event' )
		            ->willReturnCallback( function ( $trace_id, $event_name, $timestamp, $payload ) use ( &$captured ) {
		                $captured[] = compact( 'trace_id', 'event_name', 'timestamp', 'payload' );
		            } );

		$this->emitter = new EventEmitter( $this->audit );

		// Capture kh_telemetry_event action.
		$actions = &$this->action_events;
		add_action( 'kh_telemetry_event', function ( $event ) use ( &$actions ) {
			$actions[] = $event;
		} );

		TraceContext::reset();
	}

	protected function tearDown(): void {
		TraceContext::reset();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// generate.request
	// -------------------------------------------------------------------------

	public function test_generate_request_event_is_emitted(): void {
		TraceContext::init( 'trace-gen-001' );
		$this->emitter->emit( 'generate.request', array(
			'session_id'              => 'req_abc',
			'prompt_hash'             => 'sha256hash',
			'variant_count_requested' => 2,
			'service'                 => 'smma',
		) );

		$this->assertCount( 1, $this->recorded_events );
		$event = $this->recorded_events[0];
		$this->assertSame( 'generate.request', $event['event_name'] );
	}

	public function test_generate_request_payload_has_required_keys(): void {
		TraceContext::init( 'trace-gen-001' );
		$this->emitter->emit( 'generate.request', array(
			'session_id'              => 'req_abc',
			'prompt_hash'             => 'sha256hash',
			'variant_count_requested' => 2,
			'service'                 => 'smma',
		) );

		$payload = $this->recorded_events[0]['payload'];
		foreach ( array( 'event_name', 'trace_id', 'timestamp', 'service', 'session_id', 'prompt_hash', 'variant_count_requested' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, "Missing key: {$key}" );
		}
	}

	// -------------------------------------------------------------------------
	// generate.response
	// -------------------------------------------------------------------------

	public function test_generate_response_event_is_emitted(): void {
		TraceContext::init( 'trace-gen-001' );
		$this->emitter->emit( 'generate.response', array(
			'session_id'              => 'req_abc',
			'variant_count_generated' => 3,
			'latency_ms'              => 412,
			'service'                 => 'smma',
		) );

		$this->assertSame( 'generate.response', $this->recorded_events[0]['event_name'] );
		$this->assertArrayHasKey( 'latency_ms', $this->recorded_events[0]['payload'] );
		$this->assertArrayHasKey( 'variant_count_generated', $this->recorded_events[0]['payload'] );
	}

	// -------------------------------------------------------------------------
	// compliance.check
	// -------------------------------------------------------------------------

	public function test_compliance_check_event_is_emitted(): void {
		TraceContext::init( 'trace-compliance-001' );
		$this->emitter->emit( 'compliance.check', array(
			'variant_id'   => 'v-123',
			'outcome'      => 'OK',
			'rules_matched' => array(),
			'service'      => 'smma',
		) );

		$this->assertSame( 'compliance.check', $this->recorded_events[0]['event_name'] );
	}

	public function test_compliance_check_payload_has_required_keys(): void {
		TraceContext::init( 'trace-compliance-001' );
		$this->emitter->emit( 'compliance.check', array(
			'variant_id'   => 'v-123',
			'outcome'      => 'WARN',
			'rules_matched' => array( 'rule-1' ),
			'service'      => 'smma',
		) );

		$payload = $this->recorded_events[0]['payload'];
		foreach ( array( 'event_name', 'trace_id', 'timestamp', 'variant_id', 'outcome', 'rules_matched' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, "Missing key: {$key}" );
		}
	}

	// -------------------------------------------------------------------------
	// variant.edit
	// -------------------------------------------------------------------------

	public function test_variant_edit_event_is_emitted(): void {
		TraceContext::init( 'trace-edit-001' );
		$this->emitter->emit( 'variant.edit', array(
			'variant_id'  => 'v-456',
			'editor_id'   => '7',
			'revision_id' => 'rev-001',
			'deltas'      => array( 'edit_reason' => 'typo fix' ),
			'service'     => 'smma',
		) );

		$this->assertSame( 'variant.edit', $this->recorded_events[0]['event_name'] );
	}

	public function test_variant_edit_payload_has_required_keys(): void {
		TraceContext::init( 'trace-edit-001' );
		$this->emitter->emit( 'variant.edit', array(
			'variant_id'  => 'v-456',
			'editor_id'   => '7',
			'revision_id' => 'rev-001',
			'deltas'      => array( 'edit_reason' => 'typo fix' ),
			'service'     => 'smma',
		) );

		$payload = $this->recorded_events[0]['payload'];
		foreach ( array( 'event_name', 'trace_id', 'timestamp', 'variant_id', 'editor_id', 'revision_id', 'deltas' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, "Missing key: {$key}" );
		}
	}

	// -------------------------------------------------------------------------
	// schedule.create
	// -------------------------------------------------------------------------

	public function test_schedule_create_event_is_emitted(): void {
		TraceContext::init( 'trace-sched-001' );
		$this->emitter->emit( 'schedule.create', array(
			'schedule_id'      => 'sched-99',
			'sponsor_id'       => 'sp-1',
			'approval_required' => false,
			'service'          => 'smma',
		) );

		$this->assertSame( 'schedule.create', $this->recorded_events[0]['event_name'] );
	}

	public function test_schedule_create_payload_has_required_keys(): void {
		TraceContext::init( 'trace-sched-001' );
		$this->emitter->emit( 'schedule.create', array(
			'schedule_id'      => 'sched-99',
			'sponsor_id'       => 'sp-1',
			'approval_required' => true,
			'service'          => 'smma',
		) );

		$payload = $this->recorded_events[0]['payload'];
		foreach ( array( 'event_name', 'trace_id', 'timestamp', 'schedule_id', 'sponsor_id', 'approval_required' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, "Missing key: {$key}" );
		}
	}

	// -------------------------------------------------------------------------
	// schedule.dispatch
	// -------------------------------------------------------------------------

	public function test_schedule_dispatch_event_is_emitted(): void {
		TraceContext::init( 'trace-dispatch-001' );
		$this->emitter->emit( 'schedule.dispatch', array(
			'schedule_id' => 'sched-99',
			'adapter'     => 'manual',
			'result'      => 'dispatched',
			'service'     => 'smma',
		) );

		$this->assertSame( 'schedule.dispatch', $this->recorded_events[0]['event_name'] );
	}

	public function test_schedule_dispatch_payload_has_required_keys(): void {
		TraceContext::init( 'trace-dispatch-001' );
		$this->emitter->emit( 'schedule.dispatch', array(
			'schedule_id' => 'sched-99',
			'adapter'     => 'linkedin',
			'result'      => 'dispatched',
			'service'     => 'smma',
		) );

		$payload = $this->recorded_events[0]['payload'];
		foreach ( array( 'event_name', 'trace_id', 'timestamp', 'schedule_id', 'adapter', 'result' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, "Missing key: {$key}" );
		}
	}

	// -------------------------------------------------------------------------
	// membership.signup
	// -------------------------------------------------------------------------

	public function test_membership_signup_event_is_emitted(): void {
		TraceContext::init( 'trace-mem-001' );
		$this->emitter->emit( 'membership.signup', array(
			'user_id'        => 42,
			'tier'           => 'premium',
			'payment_status' => 'paid',
			'attribution_id' => 'promo-001',
			'service'        => 'mem',
		) );

		$this->assertSame( 'membership.signup', $this->recorded_events[0]['event_name'] );
		$this->assertSame( 'mem', $this->recorded_events[0]['payload']['service'] );
	}

	// -------------------------------------------------------------------------
	// promotion_attribution
	// -------------------------------------------------------------------------

	public function test_promotion_attribution_event_is_emitted(): void {
		TraceContext::init( 'trace-attr-001' );
		$this->emitter->emit( 'promotion_attribution', array(
			'schedule_id'      => 'sched-99',
			'sponsor_id'       => 'sp-1',
			'utm_source'       => 'linkedin',
			'utm_campaign'     => 'q1-promo',
			'confidence_score' => 0.92,
			'service'          => 'mem',
		) );

		$this->assertSame( 'promotion_attribution', $this->recorded_events[0]['event_name'] );
	}

	public function test_promotion_attribution_payload_has_required_keys(): void {
		TraceContext::init( 'trace-attr-001' );
		$this->emitter->emit( 'promotion_attribution', array(
			'schedule_id'      => 'sched-99',
			'sponsor_id'       => 'sp-1',
			'utm_source'       => 'google',
			'utm_campaign'     => 'q1-promo',
			'confidence_score' => 0.85,
			'service'          => 'mem',
		) );

		$payload = $this->recorded_events[0]['payload'];
		foreach ( array( 'event_name', 'trace_id', 'timestamp', 'schedule_id', 'sponsor_id', 'utm_source', 'utm_campaign', 'confidence_score' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, "Missing key: {$key}" );
		}
	}

	// -------------------------------------------------------------------------
	// Audit persistence + WP action
	// -------------------------------------------------------------------------

	public function test_audit_record_event_is_called(): void {
		TraceContext::init( 'trace-audit-001' );
		$this->audit->expects( $this->once() )
		            ->method( 'record_event' )
		            ->with(
		                $this->identicalTo( 'trace-audit-001' ),
		                $this->identicalTo( 'generate.request' ),
		                $this->isType( 'integer' ),
		                $this->isType( 'array' )
		            );

		$this->emitter->emit( 'generate.request', array(
			'session_id'              => 'req_x',
			'prompt_hash'             => 'abc',
			'variant_count_requested' => 1,
			'service'                 => 'smma',
		) );
	}

	public function test_kh_telemetry_event_action_fires(): void {
		TraceContext::init( 'trace-action-001' );
		$this->emitter->emit( 'compliance.check', array(
			'variant_id'   => 'v-789',
			'outcome'      => 'OK',
			'rules_matched' => array(),
			'service'      => 'smma',
		) );

		$this->assertCount( 1, $this->action_events );
		$this->assertSame( 'compliance.check', $this->action_events[0]['event_name'] );
	}

	public function test_emit_does_not_throw_when_audit_throws(): void {
		TraceContext::init( 'trace-safe-001' );
		$db     = new wpdb();
		$audit  = $this->getMockBuilder( AuditLogger::class )
		               ->setConstructorArgs( array( $db ) )
		               ->onlyMethods( array( 'record_event' ) )
		               ->getMock();
		$audit->method( 'record_event' )->willThrowException( new \RuntimeException( 'DB down' ) );

		$emitter = new EventEmitter( $audit );

		// Must not propagate the exception.
		$emitter->emit( 'generate.request', array( 'service' => 'smma' ) );
		$this->assertTrue( true ); // Reached without throwing.
	}
}
