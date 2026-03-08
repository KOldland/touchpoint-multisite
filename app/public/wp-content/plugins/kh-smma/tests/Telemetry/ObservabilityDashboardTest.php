<?php
/**
 * OBS-03: ObservabilityDashboardPage unit + integration tests.
 *
 * Tests cover:
 *  - get_dashboard_data() assembles all sections from repository/audit correctly.
 *  - Compliance percentages sum to 100 for valid inputs.
 *  - P95 latency estimate is 1.65× average (heuristic).
 *  - Scheduling backlog = max(0, created - dispatched).
 *  - Empty snapshot returns zeroed-out metrics without errors.
 *  - TelemetryTracePage::extract_key_fields() returns correct summaries per event type.
 *  - Fixture-driven: dashboard_metrics.json expected values match computed output.
 */

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/MetricsSnapshotRepository.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Admin/ObservabilityDashboardPage.php';
require_once dirname( __DIR__, 2 ) . '/src/Admin/TelemetryTracePage.php';

use KH_SMMA\Admin\ObservabilityDashboardPage;
use KH_SMMA\Admin\TelemetryTracePage;
use KH_SMMA\Telemetry\MetricsSnapshotRepository;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

// Stub admin_url — only needed for HTML rendering, not unit tests.
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) {
        return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $args, $url = '' ) {
        return $url . '?' . http_build_query( $args );
    }
}

class ObservabilityDashboardTest extends TestCase {

    /** @var MetricsSnapshotRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $snapshots;

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $audit;

    /** @var ObservabilityDashboardPage */
    private $page;

    /** @var array Fixture data */
    private array $fixture;

    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['kh_test_options']    = array();
        $GLOBALS['kh_test_filters']    = array();
        $GLOBALS['kh_test_db_inserts'] = array();

        $db             = new wpdb();
        $this->snapshots = $this->getMockBuilder( MetricsSnapshotRepository::class )
                                ->setConstructorArgs( array( $db ) )
                                ->onlyMethods( array( 'get_latest', 'get_recent' ) )
                                ->getMock();

        $this->audit = $this->getMockBuilder( AuditLogger::class )
                            ->setConstructorArgs( array( $db ) )
                            ->onlyMethods( array( 'get_recent_telemetry_events', 'get_events_by_trace' ) )
                            ->getMock();

        $this->page    = new ObservabilityDashboardPage( $this->snapshots, $this->audit );
        $this->fixture = json_decode(
            file_get_contents( __DIR__ . '/../fixtures/telemetry/dashboard_metrics.json' ),
            true
        );
    }

    // -------------------------------------------------------------------------
    // Empty / no snapshot

    public function test_empty_snapshot_returns_zero_metrics(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( null );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();

        $this->assertNull( $data['latest'] );
        $this->assertSame( 0, $data['throughput']['generate_requests'] );
        $this->assertSame( 0, $data['throughput']['variants_created'] );
        $this->assertSame( 0, $data['compliance']['ok'] );
        $this->assertSame( 0.0, $data['latency']['avg_ms'] );
        $this->assertSame( 0.0, $data['latency']['p95_ms'] );
        $this->assertSame( 0, $data['scheduling']['backlog'] );
        $this->assertSame( 0, $data['membership']['signups'] );
    }

    // -------------------------------------------------------------------------
    // Throughput section

    public function test_throughput_from_latest_snapshot(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( $this->build_snapshot( array(
            'generate_requests'   => 42,
            'variants_created'    => 38,
            'variant_edits'       => 12,
            'schedule_created'    => 18,
            'schedule_dispatched' => 17,
        ) ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        $t    = $data['throughput'];

        $this->assertSame( 42, $t['generate_requests'] );
        $this->assertSame( 38, $t['variants_created'] );
        $this->assertSame( 12, $t['variant_edits'] );
        $this->assertSame( 18, $t['schedule_created'] );
        $this->assertSame( 17, $t['schedule_dispatched'] );
    }

    // -------------------------------------------------------------------------
    // Latency section

    public function test_latency_avg_and_p95_estimate(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( $this->build_snapshot( array(
            'avg_generate_latency_ms' => 312.4,
        ) ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        $l    = $data['latency'];

        $this->assertSame( 312.4, $l['avg_ms'] );
        // P95 = round(312.4 * 1.65, 1) = 515.5
        $this->assertSame( 515.5, $l['p95_ms'] );
    }

    public function test_zero_avg_latency_produces_zero_p95(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( $this->build_snapshot( array() ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        $this->assertSame( 0.0, $data['latency']['p95_ms'] );
    }

    // -------------------------------------------------------------------------
    // Compliance section

    public function test_compliance_counts_and_percentages(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( $this->build_snapshot( array(
            'compliance_ok'   => 30,
            'compliance_warn' => 6,
            'compliance_fail' => 2,
        ) ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        $c    = $data['compliance'];

        $this->assertSame( 30, $c['ok'] );
        $this->assertSame( 6,  $c['warn'] );
        $this->assertSame( 2,  $c['fail'] );
        // Total = 38 → ok_pct = round(30/38*100,1) = 78.9
        $this->assertSame( 78.9, $c['ok_pct'] );
        $this->assertSame( 15.8, $c['warn_pct'] );
        $this->assertSame( 5.3,  $c['fail_pct'] );
    }

    public function test_compliance_percentages_sum_to_100(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( $this->build_snapshot( array(
            'compliance_ok'   => 10,
            'compliance_warn' => 3,
            'compliance_fail' => 7,
        ) ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        $c    = $data['compliance'];

        $sum = $c['ok_pct'] + $c['warn_pct'] + $c['fail_pct'];
        // Allow floating-point rounding tolerance of 0.1
        $this->assertEqualsWithDelta( 100.0, $sum, 0.1, 'Compliance percentages should sum to 100' );
    }

    public function test_compliance_all_zero_does_not_divide_by_zero(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( $this->build_snapshot( array(
            'compliance_ok'   => 0,
            'compliance_warn' => 0,
            'compliance_fail' => 0,
        ) ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        $c    = $data['compliance'];

        // max(1, 0+0+0) = 1 used as denominator → 0%
        $this->assertSame( 0.0, $c['ok_pct'] );
        $this->assertSame( 0.0, $c['warn_pct'] );
        $this->assertSame( 0.0, $c['fail_pct'] );
    }

    // -------------------------------------------------------------------------
    // Scheduling section

    public function test_scheduling_backlog_is_created_minus_dispatched(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( $this->build_snapshot( array(
            'schedule_created'    => 18,
            'schedule_dispatched' => 17,
        ) ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        $s    = $data['scheduling'];

        $this->assertSame( 18, $s['created'] );
        $this->assertSame( 17, $s['dispatched'] );
        $this->assertSame( 1,  $s['backlog'] );
    }

    public function test_scheduling_backlog_never_negative(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( $this->build_snapshot( array(
            'schedule_created'    => 5,
            'schedule_dispatched' => 10,
        ) ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        $this->assertSame( 0, $data['scheduling']['backlog'] );
    }

    // -------------------------------------------------------------------------
    // Business / membership section

    public function test_membership_signups_and_attributions(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( $this->build_snapshot( array(
            'membership_signups'     => 5,
            'promotion_attributions' => 8,
        ) ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        $m    = $data['membership'];

        $this->assertSame( 5, $m['signups'] );
        $this->assertSame( 8, $m['attributions'] );
    }

    // -------------------------------------------------------------------------
    // Recent traces

    public function test_recent_traces_propagated_from_audit(): void {
        $traces = $this->build_audit_rows( array(
            array( 'trace_id' => 'aaaaaaaa-0001-4000-8000-000000000001', 'event_name' => 'generate.request' ),
            array( 'trace_id' => 'aaaaaaaa-0002-4000-8000-000000000002', 'event_name' => 'compliance.check' ),
        ) );

        $this->snapshots->method( 'get_latest' )->willReturn( null );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( $traces );

        $data = $this->page->get_dashboard_data();
        $this->assertCount( 2, $data['recent_traces'] );
    }

    // -------------------------------------------------------------------------
    // Fixture-driven validation

    public function test_fixture_expected_values_match_computed_dashboard(): void {
        $expected = $this->fixture['expected_dashboard'];

        $raw_metrics = json_decode( $this->fixture['snapshot']['metrics_json'], true );
        $this->snapshots->method( 'get_latest' )->willReturn( array(
            'metrics'      => $raw_metrics,
            'created_at'   => $this->fixture['snapshot']['created_at'],
            'window_start' => $this->fixture['snapshot']['window_start'],
        ) );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();

        $this->assertSame( $expected['throughput']['generate_requests'],   $data['throughput']['generate_requests'] );
        $this->assertSame( $expected['throughput']['variants_created'],     $data['throughput']['variants_created'] );
        $this->assertSame( $expected['latency']['avg_ms'],                  $data['latency']['avg_ms'] );
        $this->assertSame( $expected['latency']['p95_ms'],                  $data['latency']['p95_ms'] );
        $this->assertSame( $expected['compliance']['ok_pct'],               $data['compliance']['ok_pct'] );
        $this->assertSame( $expected['compliance']['warn_pct'],             $data['compliance']['warn_pct'] );
        $this->assertSame( $expected['compliance']['fail_pct'],             $data['compliance']['fail_pct'] );
        $this->assertSame( $expected['scheduling']['backlog'],              $data['scheduling']['backlog'] );
        $this->assertSame( $expected['membership']['signups'],              $data['membership']['signups'] );
        $this->assertSame( $expected['membership']['attributions'],         $data['membership']['attributions'] );
    }

    // -------------------------------------------------------------------------
    // TelemetryTracePage::extract_key_fields()

    public function test_extract_key_fields_generate_request(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'generate.request', array(
            'session_id'               => 'sess_abc',
            'variant_count_requested'  => 3,
        ) );
        $this->assertStringContainsString( 'session=sess_abc', $result );
        $this->assertStringContainsString( 'requested=3', $result );
    }

    public function test_extract_key_fields_generate_response(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'generate.response', array(
            'variant_count_generated' => 3,
            'latency_ms'              => 312,
        ) );
        $this->assertStringContainsString( 'generated=3', $result );
        $this->assertStringContainsString( 'latency=312ms', $result );
    }

    public function test_extract_key_fields_compliance_check(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'compliance.check', array(
            'variant_id' => 'var_001',
            'outcome'    => 'OK',
        ) );
        $this->assertStringContainsString( 'variant=var_001', $result );
        $this->assertStringContainsString( 'outcome=OK', $result );
    }

    public function test_extract_key_fields_variant_edit(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'variant.edit', array(
            'variant_id'  => 'var_002',
            'revision_id' => 'rev_007',
        ) );
        $this->assertStringContainsString( 'variant=var_002', $result );
        $this->assertStringContainsString( 'revision=rev_007', $result );
    }

    public function test_extract_key_fields_schedule_create(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'schedule.create', array(
            'schedule_id' => 'sched_123',
            'result'      => 'queued',
        ) );
        $this->assertStringContainsString( 'schedule=sched_123', $result );
        $this->assertStringContainsString( 'result=queued', $result );
    }

    public function test_extract_key_fields_schedule_dispatch(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'schedule.dispatch', array(
            'schedule_id' => 'sched_456',
            'result'      => 'dispatched',
        ) );
        $this->assertStringContainsString( 'schedule=sched_456', $result );
        $this->assertStringContainsString( 'result=dispatched', $result );
    }

    public function test_extract_key_fields_membership_signup(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'membership.signup', array(
            'tier'           => 'pro',
            'payment_status' => 'completed',
        ) );
        $this->assertStringContainsString( 'tier=pro', $result );
        $this->assertStringContainsString( 'payment=completed', $result );
    }

    public function test_extract_key_fields_promotion_attribution(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'promotion_attribution', array(
            'utm_source'       => 'google',
            'confidence_score' => 0.92,
        ) );
        $this->assertStringContainsString( 'utm_source=google', $result );
        $this->assertStringContainsString( 'confidence=0.92', $result );
    }

    public function test_extract_key_fields_unknown_event_returns_dash(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'unknown.event', array( 'foo' => 'bar' ) );
        $this->assertSame( '—', $result );
    }

    public function test_extract_key_fields_empty_payload_returns_dash(): void {
        $page   = new TelemetryTracePage( $this->audit );
        $result = $page->extract_key_fields( 'generate.request', array() );
        $this->assertSame( '—', $result );
    }

    // -------------------------------------------------------------------------
    // Data structure invariants

    public function test_get_dashboard_data_returns_all_required_keys(): void {
        $this->snapshots->method( 'get_latest' )->willReturn( null );
        $this->snapshots->method( 'get_recent' )->willReturn( array() );
        $this->audit->method( 'get_recent_telemetry_events' )->willReturn( array() );

        $data = $this->page->get_dashboard_data();
        foreach ( array( 'latest', 'recent', 'throughput', 'latency', 'compliance', 'scheduling', 'membership', 'recent_traces' ) as $key ) {
            $this->assertArrayHasKey( $key, $data, "Dashboard data must contain key: $key" );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers

    private function build_snapshot( array $metrics ): array {
        return array(
            'metrics'      => $metrics,
            'created_at'   => '2026-03-06 12:05:00',
            'window_start' => '2026-03-06 12:00:00',
        );
    }

    /**
     * Build fake decoded audit rows for get_recent_telemetry_events().
     */
    private function build_audit_rows( array $events ): array {
        $rows = array();
        foreach ( $events as $i => $e ) {
            $row                  = new \stdClass();
            $row->id              = 100 + $i;
            $row->action          = 'telemetry_event';
            $row->created_at      = '2026-03-06 12:0' . $i . ':00';
            $row->decoded_details = array(
                'trace_id'   => $e['trace_id'],
                'event_name' => $e['event_name'],
                'timestamp'  => time(),
                'payload'    => array( 'service' => 'smma' ),
            );
            $rows[]               = $row;
        }
        return $rows;
    }
}
