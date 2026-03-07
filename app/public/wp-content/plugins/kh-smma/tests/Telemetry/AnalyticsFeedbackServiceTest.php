<?php
/**
 * OBS-CARD-02: AnalyticsFeedbackService unit tests.
 *
 * Verifies that:
 *  - Each telemetry event increments the correct accumulator counter.
 *  - Compliance outcome distribution is correctly bucketed (OK/WARN/FAIL).
 *  - Average latency is computed from generate.response events.
 *  - flush_snapshot() calls MetricsSnapshotRepository::write_snapshot()
 *    with the correct metrics and resets the accumulator.
 *  - Privacy: snapshot metrics contain only counts / distributions, no PII.
 *  - compute_metrics() is deterministic from a given accumulator state.
 */

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/MetricsSnapshotRepository.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/AnalyticsFeedbackService.php';

use KH_SMMA\Telemetry\AnalyticsFeedbackService;
use KH_SMMA\Telemetry\MetricsSnapshotRepository;
use PHPUnit\Framework\TestCase;

class AnalyticsFeedbackServiceTest extends TestCase {

	/** @var array Captured write_snapshot calls */
	private array $written_snapshots = array();

	/** @var MetricsSnapshotRepository&\PHPUnit\Framework\MockObject\MockObject */
	private $repo;

	/** @var AnalyticsFeedbackService */
	private $service;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['kh_test_options']    = array();
		$GLOBALS['kh_test_filters']    = array();
		$GLOBALS['kh_test_db_inserts'] = array();

		$this->written_snapshots = array();

		$db         = new wpdb();
		$this->repo = $this->getMockBuilder( MetricsSnapshotRepository::class )
		                   ->setConstructorArgs( array( $db ) )
		                   ->onlyMethods( array( 'write_snapshot' ) )
		                   ->getMock();

		$written = &$this->written_snapshots;
		$this->repo->method( 'write_snapshot' )
		           ->willReturnCallback( function ( $metrics, $window_start ) use ( &$written ) {
		               $written[] = array( 'metrics' => $metrics, 'window_start' => $window_start );
		               return 1;
		           } );

		$this->service = new AnalyticsFeedbackService( $this->repo );
	}

	// -------------------------------------------------------------------------
	// generate.request / generate.response
	// -------------------------------------------------------------------------

	public function test_generate_request_increments_counter(): void {
		$this->service->handle_event( array( 'event_name' => 'generate.request', 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 1, $acc['generate_requests'] );
	}

	public function test_generate_response_increments_variants_created(): void {
		$this->service->handle_event( array(
			'event_name'              => 'generate.response',
			'variant_count_generated' => 3,
			'latency_ms'              => 300,
			'service'                 => 'smma',
		) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 3, $acc['variants_created'] );
	}

	public function test_generate_response_accumulates_latency(): void {
		$this->service->handle_event( array( 'event_name' => 'generate.response', 'variant_count_generated' => 1, 'latency_ms' => 200, 'service' => 'smma' ) );
		$this->service->handle_event( array( 'event_name' => 'generate.response', 'variant_count_generated' => 1, 'latency_ms' => 400, 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 600, $acc['total_latency_ms'] );
		$this->assertSame( 2, $acc['latency_count'] );
	}

	// -------------------------------------------------------------------------
	// variant.edit
	// -------------------------------------------------------------------------

	public function test_variant_edit_increments_counter(): void {
		$this->service->handle_event( array( 'event_name' => 'variant.edit', 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 1, $acc['variant_edits'] );
	}

	// -------------------------------------------------------------------------
	// compliance.check — outcome distribution
	// -------------------------------------------------------------------------

	public function test_compliance_ok_increments_ok_counter(): void {
		$this->service->handle_event( array( 'event_name' => 'compliance.check', 'outcome' => 'OK', 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 1, $acc['compliance_ok'] );
		$this->assertSame( 0, $acc['compliance_warn'] );
		$this->assertSame( 0, $acc['compliance_fail'] );
	}

	public function test_compliance_warn_increments_warn_counter(): void {
		$this->service->handle_event( array( 'event_name' => 'compliance.check', 'outcome' => 'WARN', 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 0, $acc['compliance_ok'] );
		$this->assertSame( 1, $acc['compliance_warn'] );
		$this->assertSame( 0, $acc['compliance_fail'] );
	}

	public function test_compliance_fail_increments_fail_counter(): void {
		$this->service->handle_event( array( 'event_name' => 'compliance.check', 'outcome' => 'FAIL', 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 0, $acc['compliance_ok'] );
		$this->assertSame( 0, $acc['compliance_warn'] );
		$this->assertSame( 1, $acc['compliance_fail'] );
	}

	public function test_compliance_distribution_across_mixed_outcomes(): void {
		foreach ( array( 'OK', 'OK', 'WARN', 'FAIL', 'OK' ) as $outcome ) {
			$this->service->handle_event( array( 'event_name' => 'compliance.check', 'outcome' => $outcome, 'service' => 'smma' ) );
		}
		$acc = $this->service->get_accumulator();
		$this->assertSame( 3, $acc['compliance_ok'] );
		$this->assertSame( 1, $acc['compliance_warn'] );
		$this->assertSame( 1, $acc['compliance_fail'] );
	}

	// -------------------------------------------------------------------------
	// schedule.create / schedule.dispatch
	// -------------------------------------------------------------------------

	public function test_schedule_create_increments_counter(): void {
		$this->service->handle_event( array( 'event_name' => 'schedule.create', 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 1, $acc['schedule_created'] );
	}

	public function test_schedule_dispatch_increments_counter(): void {
		$this->service->handle_event( array( 'event_name' => 'schedule.dispatch', 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 1, $acc['schedule_dispatched'] );
	}

	// -------------------------------------------------------------------------
	// MEM events
	// -------------------------------------------------------------------------

	public function test_membership_signup_increments_counter(): void {
		$this->service->handle_event( array( 'event_name' => 'membership.signup', 'service' => 'mem' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 1, $acc['membership_signups'] );
	}

	public function test_promotion_attribution_increments_counter(): void {
		$this->service->handle_event( array( 'event_name' => 'promotion_attribution', 'service' => 'mem' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 1, $acc['promotion_attributions'] );
	}

	// -------------------------------------------------------------------------
	// compute_metrics
	// -------------------------------------------------------------------------

	public function test_compute_metrics_calculates_avg_latency(): void {
		$acc = array(
			'window_start'           => time(),
			'generate_requests'      => 5,
			'variants_created'       => 10,
			'variant_edits'          => 2,
			'compliance_ok'          => 8,
			'compliance_warn'        => 1,
			'compliance_fail'        => 1,
			'schedule_created'       => 3,
			'schedule_dispatched'    => 3,
			'membership_signups'     => 4,
			'promotion_attributions' => 2,
			'total_latency_ms'       => 1500,
			'latency_count'          => 5,
		);

		$metrics = $this->service->compute_metrics( $acc );

		$this->assertSame( 5, $metrics['generate_requests'] );
		$this->assertSame( 10, $metrics['variants_created'] );
		$this->assertSame( 2, $metrics['variant_edits'] );
		$this->assertSame( 8, $metrics['compliance_ok'] );
		$this->assertSame( 1, $metrics['compliance_warn'] );
		$this->assertSame( 1, $metrics['compliance_fail'] );
		$this->assertSame( 3, $metrics['schedule_created'] );
		$this->assertSame( 300.0, $metrics['avg_generate_latency_ms'] );
	}

	public function test_compute_metrics_returns_zero_latency_when_no_data(): void {
		$metrics = $this->service->compute_metrics( array() );
		$this->assertSame( 0.0, $metrics['avg_generate_latency_ms'] );
	}

	public function test_compute_metrics_has_no_pii_fields(): void {
		$metrics = $this->service->compute_metrics( array() );
		$forbidden = array( 'email', 'user_email', 'name', 'token', 'password', 'prompt', 'text' );
		foreach ( $forbidden as $field ) {
			$this->assertArrayNotHasKey( $field, $metrics, "PII field '{$field}' found in metrics" );
		}
	}

	// -------------------------------------------------------------------------
	// flush_snapshot
	// -------------------------------------------------------------------------

	public function test_flush_snapshot_calls_write_snapshot(): void {
		$this->service->handle_event( array( 'event_name' => 'generate.request', 'service' => 'smma' ) );
		$this->service->handle_event( array( 'event_name' => 'generate.request', 'service' => 'smma' ) );

		$this->repo->expects( $this->once() )->method( 'write_snapshot' );
		$this->service->flush_snapshot();
	}

	public function test_flush_snapshot_passes_correct_metrics(): void {
		$this->service->handle_event( array( 'event_name' => 'generate.request', 'service' => 'smma' ) );
		$this->service->handle_event( array( 'event_name' => 'compliance.check', 'outcome' => 'OK', 'service' => 'smma' ) );
		$this->service->handle_event( array( 'event_name' => 'schedule.create', 'service' => 'smma' ) );

		$this->service->flush_snapshot();

		$this->assertCount( 1, $this->written_snapshots );
		$metrics = $this->written_snapshots[0]['metrics'];
		$this->assertSame( 1, $metrics['generate_requests'] );
		$this->assertSame( 1, $metrics['compliance_ok'] );
		$this->assertSame( 1, $metrics['schedule_created'] );
	}

	public function test_flush_snapshot_resets_accumulator(): void {
		$this->service->handle_event( array( 'event_name' => 'generate.request', 'service' => 'smma' ) );
		$this->service->flush_snapshot();

		// Accumulator should be reset to zero counts after flush.
		$acc = $this->service->get_accumulator();
		$this->assertSame( 0, $acc['generate_requests'] );
	}

	public function test_flush_snapshot_fires_action(): void {
		$fired = array();
		add_action( 'kh_smma_analytics_snapshot_flushed', function ( $m ) use ( &$fired ) {
			$fired[] = $m;
		} );

		$this->service->flush_snapshot();

		$this->assertCount( 1, $fired );
		$this->assertArrayHasKey( 'generate_requests', $fired[0] );
	}

	// -------------------------------------------------------------------------
	// Unknown / unrecognised events are silently ignored
	// -------------------------------------------------------------------------

	public function test_unknown_event_name_does_not_throw(): void {
		$this->service->handle_event( array( 'event_name' => 'unknown.future.event', 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		// All counts remain zero — no crash.
		$this->assertSame( 0, $acc['generate_requests'] );
	}

	public function test_missing_event_name_is_ignored(): void {
		$this->service->handle_event( array( 'service' => 'smma' ) );
		$acc = $this->service->get_accumulator();
		$this->assertSame( 0, $acc['generate_requests'] );
	}
}
