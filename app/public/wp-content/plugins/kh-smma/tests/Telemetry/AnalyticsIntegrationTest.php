<?php
/**
 * OBS-CARD-02: Analytics pipeline integration test.
 *
 * Replays the event stream from tests/fixtures/telemetry/analytics_events.json
 * through AnalyticsFeedbackService and asserts that:
 *  - every event type increments the correct accumulator counter
 *  - flush_snapshot() produces a snapshot whose metrics exactly match
 *    the expected_metrics declared in the fixture
 *  - the snapshot is persisted via MetricsSnapshotRepository
 *  - audit persistence is called for each event (via EventEmitter integration)
 *
 * This test covers the full pipeline:
 *   EventEmitter → handle_event() accumulate → flush_snapshot() → write_snapshot()
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

class AnalyticsIntegrationTest extends TestCase {

	/** @var array */
	private array $written_snapshots = array();

	/** @var array */
	private array $audit_records = array();

	/** @var AnalyticsFeedbackService */
	private $analytics;

	/** @var EventEmitter */
	private $emitter;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['kh_test_options']    = array();
		$GLOBALS['kh_test_filters']    = array();
		$GLOBALS['kh_test_db_inserts'] = array();

		$this->written_snapshots = array();
		$this->audit_records     = array();

		TraceContext::reset();

		// Stub AuditLogger.
		$db    = new wpdb();
		$audit = $this->getMockBuilder( AuditLogger::class )
		              ->setConstructorArgs( array( $db ) )
		              ->onlyMethods( array( 'record_event' ) )
		              ->getMock();
		$ar    = &$this->audit_records;
		$audit->method( 'record_event' )
		      ->willReturnCallback( function ( $trace_id, $event_name, $ts, $payload ) use ( &$ar ) {
		          $ar[] = compact( 'trace_id', 'event_name' );
		      } );

		// Stub MetricsSnapshotRepository.
		$repo = $this->getMockBuilder( MetricsSnapshotRepository::class )
		             ->setConstructorArgs( array( $db ) )
		             ->onlyMethods( array( 'write_snapshot' ) )
		             ->getMock();
		$ws   = &$this->written_snapshots;
		$repo->method( 'write_snapshot' )
		     ->willReturnCallback( function ( $metrics, $window_start ) use ( &$ws ) {
		         $ws[] = array( 'metrics' => $metrics, 'window_start' => $window_start );
		         return 1;
		     } );

		$this->analytics = new AnalyticsFeedbackService( $repo );
		$this->emitter   = new EventEmitter( $audit );

		// Wire kh_telemetry_event → AnalyticsFeedbackService::handle_event.
		$svc = $this->analytics;
		add_action( 'kh_telemetry_event', array( $svc, 'handle_event' ) );
	}

	protected function tearDown(): void {
		TraceContext::reset();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Fixture-driven pipeline test
	// -------------------------------------------------------------------------

	public function test_full_pipeline_matches_fixture(): void {
		$fixture = json_decode(
			file_get_contents( dirname( __DIR__ ) . '/fixtures/telemetry/analytics_events.json' ),
			true
		);

		TraceContext::init( $fixture['trace_id'] );

		// Replay all events through EventEmitter (which fires kh_telemetry_event).
		foreach ( $fixture['events'] as $spec ) {
			$this->emitter->emit(
				$spec['event_name'],
				array_merge( $spec['payload'], array( 'service' => $spec['service'] ) )
			);
		}

		// Flush snapshot.
		$this->analytics->flush_snapshot();

		// Assert snapshot was written.
		$this->assertCount( 1, $this->written_snapshots, 'Expected exactly one snapshot to be written' );

		$actual   = $this->written_snapshots[0]['metrics'];
		$expected = $fixture['expected_metrics'];

		foreach ( $expected as $key => $value ) {
			$this->assertArrayHasKey( $key, $actual, "Metrics missing key: {$key}" );
			$this->assertEquals( $value, $actual[ $key ], "Metrics mismatch for key '{$key}': expected {$value}, got {$actual[$key]}" );
		}
	}

	// -------------------------------------------------------------------------
	// Audit persistence is called for every event
	// -------------------------------------------------------------------------

	public function test_audit_records_created_for_all_events(): void {
		$fixture = json_decode(
			file_get_contents( dirname( __DIR__ ) . '/fixtures/telemetry/analytics_events.json' ),
			true
		);

		TraceContext::init( $fixture['trace_id'] );

		foreach ( $fixture['events'] as $spec ) {
			$this->emitter->emit(
				$spec['event_name'],
				array_merge( $spec['payload'], array( 'service' => $spec['service'] ) )
			);
		}

		// One audit record per event.
		$this->assertCount( count( $fixture['events'] ), $this->audit_records );
	}

	// -------------------------------------------------------------------------
	// Compliance audit detail fields are present in emitted events
	// -------------------------------------------------------------------------

	public function test_compliance_check_events_carry_ai_review_summary(): void {
		$captured = array();
		add_action( 'kh_telemetry_event', function ( $event ) use ( &$captured ) {
			if ( 'compliance.check' === $event['event_name'] ) {
				$captured[] = $event;
			}
		} );

		TraceContext::init( 'compliance-detail-trace' );
		$this->emitter->emit( 'compliance.check', array(
			'variant_id'        => 'v-test',
			'outcome'           => 'WARN',
			'rules_matched'     => array( 'soft_claim' ),
			'ai_review_summary' => 'Claim may require sponsor verification',
			'service'           => 'smma',
		) );

		$this->assertCount( 1, $captured );
		$event = $captured[0];
		$this->assertArrayHasKey( 'variant_id', $event );
		$this->assertArrayHasKey( 'outcome', $event );
		$this->assertArrayHasKey( 'rules_matched', $event );
		$this->assertArrayHasKey( 'ai_review_summary', $event );
		$this->assertSame( 'Claim may require sponsor verification', $event['ai_review_summary'] );
	}

	// -------------------------------------------------------------------------
	// Privacy: snapshot metrics contain no PII
	// -------------------------------------------------------------------------

	public function test_snapshot_metrics_contain_no_pii(): void {
		$this->emitter->emit( 'generate.request', array( 'service' => 'smma' ) );
		$this->analytics->flush_snapshot();

		$this->assertCount( 1, $this->written_snapshots );
		$metrics_json = json_encode( $this->written_snapshots[0]['metrics'] );

		// Ensure none of these PII-indicative strings appear in any key or value.
		$pii_signals = array( 'email', 'password', 'token', 'raw_text', 'prompt', 'name' );
		foreach ( $pii_signals as $signal ) {
			$this->assertStringNotContainsStringIgnoringCase(
				$signal,
				$metrics_json,
				"Snapshot metrics appear to contain PII signal: '{$signal}'"
			);
		}
	}

	// -------------------------------------------------------------------------
	// Accumulator resets after flush
	// -------------------------------------------------------------------------

	public function test_accumulator_resets_after_flush(): void {
		$this->emitter->emit( 'generate.request', array( 'service' => 'smma' ) );
		$this->emitter->emit( 'schedule.create', array( 'service' => 'smma' ) );
		$this->analytics->flush_snapshot();

		$acc = $this->analytics->get_accumulator();
		$this->assertSame( 0, $acc['generate_requests'] );
		$this->assertSame( 0, $acc['schedule_created'] );
	}
}
