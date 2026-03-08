<?php
/**
 * OBS-04: AlertEvaluator unit + integration tests.
 *
 * Covers:
 *  - compliance_fail_rate: warning (2-snapshot sustained), critical (single),
 *    cleared when normal, edge cases (empty/single snapshot).
 *  - queue_backlog: warning when backlog > 20, cleared when normal.
 *  - dispatch_errors: warning when > 5 failures, cleared when ≤ 5.
 *  - Alert events emitted via EventEmitter on trigger.
 *  - No alert emitted when metrics are normal.
 *  - Active alert state stored in WP option after evaluate().
 *  - Active alert cleared from WP option when condition resolves.
 *  - get_alert_history() returns only alert.triggered rows.
 *  - Fixture-driven: alert_snapshots.json validates all expected outcomes.
 */

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/MetricsSnapshotRepository.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/EventEmitter.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/TraceContext.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/AlertEvaluator.php';

use KH_SMMA\Telemetry\AlertEvaluator;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\MetricsSnapshotRepository;
use KH_SMMA\Telemetry\TraceContext;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

class AlertEvaluatorTest extends TestCase {

    /** @var MetricsSnapshotRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $snapshots;

    /** @var EventEmitter&\PHPUnit\Framework\MockObject\MockObject */
    private $emitter;

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $audit;

    /** @var AlertEvaluator */
    private $evaluator;

    /** @var array Captured emit() calls */
    private array $emitted = array();

    /** @var array Fixture data */
    private array $fixture;

    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['kh_test_options']    = array();
        $GLOBALS['kh_test_filters']    = array();
        $GLOBALS['kh_test_db_inserts'] = array();
        $this->emitted                 = array();

        TraceContext::reset();
        TraceContext::init( 'aaaaaaaa-0000-4000-8000-000000000099' );

        $db              = new wpdb();
        $this->snapshots = $this->getMockBuilder( MetricsSnapshotRepository::class )
                                ->setConstructorArgs( array( $db ) )
                                ->onlyMethods( array( 'get_recent' ) )
                                ->getMock();

        $this->audit = $this->getMockBuilder( AuditLogger::class )
                            ->setConstructorArgs( array( $db ) )
                            ->onlyMethods( array( 'record_event', 'get_recent_telemetry_events' ) )
                            ->getMock();

        $captured = &$this->emitted;
        $this->emitter = $this->getMockBuilder( EventEmitter::class )
                              ->setConstructorArgs( array( $this->audit ) )
                              ->onlyMethods( array( 'emit' ) )
                              ->getMock();
        $this->emitter->method( 'emit' )
                      ->willReturnCallback( function ( $event_name, $payload ) use ( &$captured ) {
                          $captured[] = array( 'event_name' => $event_name, 'payload' => $payload );
                      } );

        $this->evaluator = new AlertEvaluator( $this->snapshots, $this->emitter, $this->audit );

        $this->fixture = json_decode(
            file_get_contents( __DIR__ . '/../fixtures/telemetry/alert_snapshots.json' ),
            true
        );
    }

    protected function tearDown(): void {
        TraceContext::reset();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // check_compliance_fail_rate — no alert

    public function test_compliance_no_alert_when_below_threshold(): void {
        $snapshots = $this->fixture_snapshots( 'no_alert_snapshots' );
        $result    = $this->evaluator->check_compliance_fail_rate( $snapshots );
        // 5 fail / (90+5+5) = 5% < 10%
        $this->assertNull( $result );
    }

    public function test_compliance_no_alert_with_empty_snapshots(): void {
        $result = $this->evaluator->check_compliance_fail_rate( array() );
        $this->assertNull( $result );
    }

    public function test_compliance_no_alert_single_snapshot_above_warn_only_once(): void {
        // Only 1 snapshot (not 2 consecutive) at exactly 12% — should be null (not sustained)
        $snapshots = array( array( 'metrics' => array(
            'compliance_ok'   => 26,
            'compliance_warn' => 10,
            'compliance_fail'  => 5, // 5/41 ≈ 12.2% > 10%, but only 1 snapshot
        ), 'created_at' => '2026-03-06 12:05:00' ) );
        $result = $this->evaluator->check_compliance_fail_rate( $snapshots );
        $this->assertNull( $result );
    }

    // -------------------------------------------------------------------------
    // check_compliance_fail_rate — warning

    public function test_compliance_warning_when_two_consecutive_snapshots_exceed_warn_rate(): void {
        $snapshots = $this->fixture_snapshots( 'compliance_warn_snapshots' );
        $result    = $this->evaluator->check_compliance_fail_rate( $snapshots );

        $this->assertNotNull( $result );
        $this->assertSame( AlertEvaluator::TYPE_COMPLIANCE_FAIL, $result['alert_type'] );
        $this->assertSame( AlertEvaluator::SEV_WARNING, $result['severity'] );
        $this->assertGreaterThan( AlertEvaluator::COMPLIANCE_WARN_RATE, $result['fail_rate'] );
    }

    public function test_compliance_warning_payload_contains_required_fields(): void {
        $snapshots = $this->fixture_snapshots( 'compliance_warn_snapshots' );
        $result    = $this->evaluator->check_compliance_fail_rate( $snapshots );

        foreach ( array( 'alert_type', 'severity', 'fail_rate', 'snapshot_time', 'metrics_context' ) as $key ) {
            $this->assertArrayHasKey( $key, $result, "Alert payload must contain $key" );
        }
        $this->assertIsArray( $result['metrics_context'] );
    }

    // -------------------------------------------------------------------------
    // check_compliance_fail_rate — critical

    public function test_compliance_critical_when_single_snapshot_exceeds_crit_rate(): void {
        $snapshots = $this->fixture_snapshots( 'compliance_crit_snapshots' );
        $result    = $this->evaluator->check_compliance_fail_rate( $snapshots );

        $this->assertNotNull( $result );
        $this->assertSame( AlertEvaluator::SEV_CRITICAL, $result['severity'] );
        $this->assertGreaterThan( AlertEvaluator::COMPLIANCE_CRIT_RATE, $result['fail_rate'] );
    }

    public function test_compliance_critical_does_not_require_two_snapshots(): void {
        // Only 1 snapshot but fail_rate > 25%
        $snapshots = $this->fixture_snapshots( 'compliance_crit_snapshots' );
        $this->assertCount( 1, $snapshots );
        $result = $this->evaluator->check_compliance_fail_rate( $snapshots );
        $this->assertSame( AlertEvaluator::SEV_CRITICAL, $result['severity'] );
    }

    // -------------------------------------------------------------------------
    // check_queue_backlog

    public function test_queue_backlog_no_alert_when_within_threshold(): void {
        $snapshots = $this->fixture_snapshots( 'normal_queue_snapshots' );
        $result    = $this->evaluator->check_queue_backlog( $snapshots );
        $this->assertNull( $result );
    }

    public function test_queue_backlog_no_alert_when_empty(): void {
        $result = $this->evaluator->check_queue_backlog( array() );
        $this->assertNull( $result );
    }

    public function test_queue_backlog_warning_when_backlog_exceeds_threshold(): void {
        $snapshots = $this->fixture_snapshots( 'queue_backlog_snapshots' );
        $result    = $this->evaluator->check_queue_backlog( $snapshots );

        $this->assertNotNull( $result );
        $this->assertSame( AlertEvaluator::TYPE_QUEUE_BACKLOG, $result['alert_type'] );
        $this->assertSame( AlertEvaluator::SEV_WARNING, $result['severity'] );
        $this->assertSame( 25, $result['backlog_size'] ); // 45-20=25
    }

    public function test_queue_backlog_payload_contains_required_fields(): void {
        $snapshots = $this->fixture_snapshots( 'queue_backlog_snapshots' );
        $result    = $this->evaluator->check_queue_backlog( $snapshots );
        foreach ( array( 'alert_type', 'severity', 'backlog_size', 'snapshot_time', 'metrics_context' ) as $key ) {
            $this->assertArrayHasKey( $key, $result );
        }
    }

    public function test_queue_backlog_never_negative(): void {
        $snapshots = array( array( 'metrics' => array(
            'schedule_created'    => 5,
            'schedule_dispatched' => 100,
        ), 'created_at' => '2026-03-06 12:00:00' ) );
        $result = $this->evaluator->check_queue_backlog( $snapshots );
        $this->assertNull( $result ); // backlog = max(0, 5-100) = 0, no alert
    }

    // -------------------------------------------------------------------------
    // check_dispatch_errors

    public function test_dispatch_errors_no_alert_when_below_threshold(): void {
        $rows   = $this->fixture_telemetry_rows( 'mixed_dispatch_events' );
        $result = $this->evaluator->check_dispatch_errors( $rows );
        $this->assertNull( $result ); // only 1 failure ≤ 5
    }

    public function test_dispatch_errors_no_alert_with_empty_events(): void {
        $result = $this->evaluator->check_dispatch_errors( array() );
        $this->assertNull( $result );
    }

    public function test_dispatch_errors_warning_when_failures_exceed_threshold(): void {
        $rows   = $this->fixture_telemetry_rows( 'dispatch_error_events' );
        $result = $this->evaluator->check_dispatch_errors( $rows );

        $this->assertNotNull( $result );
        $this->assertSame( AlertEvaluator::TYPE_DISPATCH_ERRORS, $result['alert_type'] );
        $this->assertSame( AlertEvaluator::SEV_WARNING, $result['severity'] );
        $this->assertSame( 6, $result['failure_count'] );
    }

    public function test_dispatch_errors_ignores_non_failed_results(): void {
        $rows = $this->fixture_telemetry_rows( 'mixed_dispatch_events' );
        // mixed has 1 dispatched + 1 failed + 1 generate.request
        $result = $this->evaluator->check_dispatch_errors( $rows );
        $this->assertNull( $result );
    }

    public function test_dispatch_errors_ignores_non_dispatch_events(): void {
        // Only generate.request events — zero dispatch failures
        $rows = $this->build_telemetry_rows( array(
            array( 'event_name' => 'generate.request', 'result' => '' ),
            array( 'event_name' => 'compliance.check', 'result' => '' ),
        ) );
        $result = $this->evaluator->check_dispatch_errors( $rows );
        $this->assertNull( $result );
    }

    public function test_dispatch_errors_payload_contains_required_fields(): void {
        $rows   = $this->fixture_telemetry_rows( 'dispatch_error_events' );
        $result = $this->evaluator->check_dispatch_errors( $rows );
        foreach ( array( 'alert_type', 'severity', 'failure_count', 'adapter', 'snapshot_time' ) as $key ) {
            $this->assertArrayHasKey( $key, $result );
        }
    }

    // -------------------------------------------------------------------------
    // evaluate() — integration tests

    public function test_evaluate_emits_alert_event_when_threshold_exceeded(): void {
        $this->snapshots->method( 'get_recent' )
                        ->willReturn( $this->fixture_snapshots( 'compliance_crit_snapshots' ) );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $this->evaluator->evaluate();

        $events = array_column( $this->emitted, 'event_name' );
        $this->assertContains( 'alert.triggered', $events );
    }

    public function test_evaluate_does_not_emit_when_all_metrics_normal(): void {
        $this->snapshots->method( 'get_recent' )
                        ->willReturn( $this->fixture_snapshots( 'no_alert_snapshots' ) );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $this->evaluator->evaluate();

        $events = array_column( $this->emitted, 'event_name' );
        $this->assertNotContains( 'alert.triggered', $events );
    }

    public function test_evaluate_stores_active_alert_in_wp_option(): void {
        $this->snapshots->method( 'get_recent' )
                        ->willReturn( $this->fixture_snapshots( 'queue_backlog_snapshots' ) );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $this->evaluator->evaluate();

        $active = $this->evaluator->get_active_alerts();
        $this->assertArrayHasKey( AlertEvaluator::TYPE_QUEUE_BACKLOG, $active );
    }

    public function test_evaluate_clears_active_alert_when_condition_resolves(): void {
        // First evaluation — alert fires.
        $this->snapshots->method( 'get_recent' )
                        ->willReturnOnConsecutiveCalls(
                            $this->fixture_snapshots( 'queue_backlog_snapshots' ),
                            $this->fixture_snapshots( 'normal_queue_snapshots' )
                        );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $this->evaluator->evaluate();
        $this->assertArrayHasKey( AlertEvaluator::TYPE_QUEUE_BACKLOG, $this->evaluator->get_active_alerts() );

        // Second evaluation — condition resolved.
        $this->evaluator->evaluate();
        $this->assertArrayNotHasKey( AlertEvaluator::TYPE_QUEUE_BACKLOG, $this->evaluator->get_active_alerts() );
    }

    // -------------------------------------------------------------------------
    // get_active_alerts() / get_alert_history()

    public function test_get_active_alerts_returns_empty_array_initially(): void {
        $result = $this->evaluator->get_active_alerts();
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_get_alert_history_returns_only_alert_triggered_rows(): void {
        $rows = array(
            $this->build_audit_row( 'alert.triggered',  array( 'alert_type' => 'compliance_fail_rate', 'severity' => 'warning' ) ),
            $this->build_audit_row( 'generate.request', array( 'session_id' => 'sess_abc' ) ),
            $this->build_audit_row( 'alert.triggered',  array( 'alert_type' => 'queue_backlog', 'severity' => 'warning' ) ),
        );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( $rows );

        $history = $this->evaluator->get_alert_history( 10 );
        $this->assertCount( 2, $history );
    }

    public function test_get_alert_history_respects_limit(): void {
        $rows = array();
        for ( $i = 0; $i < 20; $i++ ) {
            $rows[] = $this->build_audit_row( 'alert.triggered', array( 'alert_type' => 'queue_backlog', 'severity' => 'warning' ) );
        }
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( $rows );

        $history = $this->evaluator->get_alert_history( 5 );
        $this->assertCount( 5, $history );
    }

    // -------------------------------------------------------------------------
    // Alert event payload structure

    public function test_emitted_alert_event_contains_service_obs(): void {
        $this->snapshots->method( 'get_recent' )
                        ->willReturn( $this->fixture_snapshots( 'compliance_crit_snapshots' ) );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $this->evaluator->evaluate();

        $alert_events = array_filter( $this->emitted, fn( $e ) => $e['event_name'] === 'alert.triggered' );
        $this->assertNotEmpty( $alert_events );

        $first = array_values( $alert_events )[0];
        $this->assertSame( 'obs', $first['payload']['service'] );
        $this->assertSame( AlertEvaluator::SEV_CRITICAL, $first['payload']['severity'] );
    }

    // -------------------------------------------------------------------------
    // Helpers

    /**
     * Convert fixture snapshot array into the format get_recent() returns.
     */
    private function fixture_snapshots( string $key ): array {
        return $this->fixture[ $key ] ?? array();
    }

    /**
     * Convert fixture telemetry event array into decoded audit row objects.
     */
    private function fixture_telemetry_rows( string $key ): array {
        $items = $this->fixture[ $key ] ?? array();
        $rows  = array();
        foreach ( $items as $item ) {
            $row                  = new \stdClass();
            $row->id              = $item['id'];
            $row->action          = $item['action'];
            $row->created_at      = $item['created_at'];
            $row->decoded_details = $item['decoded_details'];
            $rows[]               = $row;
        }
        return $rows;
    }

    /**
     * Build simple telemetry rows from lightweight descriptors.
     */
    private function build_telemetry_rows( array $events ): array {
        $rows = array();
        foreach ( $events as $i => $e ) {
            $row                  = new \stdClass();
            $row->id              = 300 + $i;
            $row->action          = 'telemetry_event';
            $row->created_at      = '2026-03-06 12:00:00';
            $row->decoded_details = array(
                'event_name' => $e['event_name'],
                'payload'    => array( 'service' => 'smma', 'result' => $e['result'] ),
            );
            $rows[]               = $row;
        }
        return $rows;
    }

    private function build_audit_row( string $event_name, array $payload_extra = array() ): \stdClass {
        $row                  = new \stdClass();
        $row->id              = rand( 1000, 9999 );
        $row->action          = 'telemetry_event';
        $row->created_at      = '2026-03-06 12:00:00';
        $row->decoded_details = array(
            'event_name' => $event_name,
            'payload'    => array_merge( array( 'service' => 'smma' ), $payload_extra ),
        );
        return $row;
    }
}
