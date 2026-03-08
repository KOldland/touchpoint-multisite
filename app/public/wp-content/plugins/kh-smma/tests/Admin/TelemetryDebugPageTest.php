<?php
namespace KH_SMMA\Tests\Admin;

use KH_SMMA\Admin\TelemetryDebugPage;
use KH_SMMA\Security\CapabilityManager;
use KH_SMMA\Telemetry\TelemetryTraceService;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Security/CapabilityManager.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/TelemetryTraceService.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/TelemetryPayloadSanitizer.php';
require_once dirname( __DIR__, 2 ) . '/src/Admin/TelemetryDebugPage.php';

/**
 * OBS-08: Unit tests for TelemetryDebugPage.
 *
 * Covers:
 *  - Page slug constant is correct
 *  - add_menu() registers submenu with manage_observability capability
 *  - render_page() calls wp_die for unauthorized users
 *  - render_page() calls get_trace_timeline() for trace_id lookup
 *  - render_page() calls find_by_schedule_id() for schedule_id lookup
 *  - render_page() calls find_by_variant_id() for variant_id lookup
 *  - Empty lookup value skips service call
 *  - Invalid nonce skips service call
 *  - Rendered HTML contains timeline rows
 *  - "No events found" message shown for unknown trace
 *  - Privacy notice appears in rendered output
 */
class TelemetryDebugPageTest extends TestCase {

    /** @var TelemetryTraceService|\PHPUnit\Framework\MockObject\MockObject */
    private $trace_service;

    /** @var TelemetryDebugPage */
    private $page;

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['kh_test_filters']  = [];
        $GLOBALS['kh_test_submenus'] = [];
        // Default: user has all capabilities.
        $GLOBALS['kh_test_caps'] = [];
        // Clear GET params.
        $_GET = [];

        $this->trace_service = $this->getMockBuilder( TelemetryTraceService::class )
                                    ->setConstructorArgs( [ $this->make_audit_mock() ] )
                                    ->onlyMethods( [ 'get_trace_timeline', 'find_by_schedule_id', 'find_by_variant_id', 'extract_key_fields' ] )
                                    ->getMock();

        $this->trace_service->method( 'extract_key_fields' )->willReturn( [] );

        $this->page = new TelemetryDebugPage( $this->trace_service );
    }

    protected function tearDown(): void {
        $_GET = [];
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function test_page_slug_constant(): void {
        $this->assertSame( 'kh-telemetry-debug', TelemetryDebugPage::PAGE_SLUG );
    }

    public function test_nonce_key_constant(): void {
        $this->assertNotEmpty( TelemetryDebugPage::NONCE_KEY );
    }

    // -------------------------------------------------------------------------
    // register() / add_menu()
    // -------------------------------------------------------------------------

    public function test_register_hooks_admin_menu(): void {
        $this->page->register();
        $hooks = $GLOBALS['kh_test_filters']['admin_menu'] ?? [];
        $this->assertNotEmpty( $hooks, 'admin_menu hook must be registered' );
    }

    public function test_add_menu_registers_submenu_with_manage_observability_cap(): void {
        $this->page->add_menu();

        $this->assertNotEmpty( $GLOBALS['kh_test_submenus'] );
        $menu = $GLOBALS['kh_test_submenus'][0];

        $this->assertSame( 'kh-smma-dashboard',                       $menu['parent'] );
        $this->assertSame( TelemetryDebugPage::PAGE_SLUG,             $menu['slug'] );
        $this->assertSame( CapabilityManager::CAP_MANAGE_OBSERVABILITY, $menu['capability'] );
    }

    public function test_add_menu_parent_is_smma_dashboard(): void {
        $this->page->add_menu();
        $this->assertSame( 'kh-smma-dashboard', $GLOBALS['kh_test_submenus'][0]['parent'] );
    }

    // -------------------------------------------------------------------------
    // RBAC — unauthorized access triggers wp_die
    // -------------------------------------------------------------------------

    public function test_render_page_dies_when_unauthorized(): void {
        // Deny all caps.
        $GLOBALS['kh_test_caps'] = [
            CapabilityManager::CAP_MANAGE_OBSERVABILITY => false,
            'manage_options'                             => false,
        ];

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessageMatches( '/wp_die/' );

        $this->page->render_page();
    }

    public function test_render_page_proceeds_when_authorized(): void {
        $GLOBALS['kh_test_caps'] = [ CapabilityManager::CAP_MANAGE_OBSERVABILITY => true ];
        $_GET = [ 'lookup_value' => '', '_wpnonce' => 'valid' ];

        ob_start();
        $this->page->render_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'Telemetry Debug', $html );
    }

    // -------------------------------------------------------------------------
    // Trace ID lookup
    // -------------------------------------------------------------------------

    public function test_render_page_calls_get_trace_timeline_for_trace_id_lookup(): void {
        $_GET = [
            'lookup_type'  => 'trace_id',
            'lookup_value' => 'tr-test-001',
            '_wpnonce'     => 'valid',
        ];

        $this->trace_service->expects( $this->once() )
                            ->method( 'get_trace_timeline' )
                            ->with( 'tr-test-001' )
                            ->willReturn( [] );

        ob_start();
        $this->page->render_page();
        ob_get_clean();
    }

    public function test_render_page_renders_timeline_events(): void {
        $_GET = [
            'lookup_type'  => 'trace_id',
            'lookup_value' => 'tr-abc',
            '_wpnonce'     => 'valid',
        ];

        $timeline = [
            [ 'event_name' => 'generate.request',  'trace_id' => 'tr-abc', 'timestamp' => 1000, 'created_at' => '2026-03-06 10:00:01', 'payload' => [] ],
            [ 'event_name' => 'compliance.check',  'trace_id' => 'tr-abc', 'timestamp' => 1001, 'created_at' => '2026-03-06 10:00:02', 'payload' => [] ],
            [ 'event_name' => 'schedule.dispatch', 'trace_id' => 'tr-abc', 'timestamp' => 1010, 'created_at' => '2026-03-06 10:00:10', 'payload' => [] ],
        ];

        $this->trace_service->method( 'get_trace_timeline' )->willReturn( $timeline );

        ob_start();
        $this->page->render_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'generate.request',  $html );
        $this->assertStringContainsString( 'compliance.check',  $html );
        $this->assertStringContainsString( 'schedule.dispatch', $html );
        $this->assertStringContainsString( '3 event(s)',        $html );
    }

    // -------------------------------------------------------------------------
    // Schedule ID lookup
    // -------------------------------------------------------------------------

    public function test_render_page_calls_find_by_schedule_id(): void {
        $_GET = [
            'lookup_type'  => 'schedule_id',
            'lookup_value' => 'sch-77',
            '_wpnonce'     => 'valid',
        ];

        $this->trace_service->expects( $this->once() )
                            ->method( 'find_by_schedule_id' )
                            ->with( 'sch-77' )
                            ->willReturn( [] );

        ob_start();
        $this->page->render_page();
        ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Variant ID lookup
    // -------------------------------------------------------------------------

    public function test_render_page_calls_find_by_variant_id(): void {
        $_GET = [
            'lookup_type'  => 'variant_id',
            'lookup_value' => 'v-abc',
            '_wpnonce'     => 'valid',
        ];

        $this->trace_service->expects( $this->once() )
                            ->method( 'find_by_variant_id' )
                            ->with( 'v-abc' )
                            ->willReturn( [] );

        ob_start();
        $this->page->render_page();
        ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Empty lookup / no results
    // -------------------------------------------------------------------------

    public function test_render_page_skips_lookup_when_value_empty(): void {
        $_GET = [ 'lookup_value' => '', '_wpnonce' => 'valid' ];

        $this->trace_service->expects( $this->never() )->method( 'get_trace_timeline' );
        $this->trace_service->expects( $this->never() )->method( 'find_by_schedule_id' );
        $this->trace_service->expects( $this->never() )->method( 'find_by_variant_id' );

        ob_start();
        $this->page->render_page();
        ob_get_clean();
    }

    public function test_render_page_shows_no_events_message_on_empty_result(): void {
        $_GET = [
            'lookup_type'  => 'trace_id',
            'lookup_value' => 'trace-nonexistent',
            '_wpnonce'     => 'valid',
        ];

        $this->trace_service->method( 'get_trace_timeline' )->willReturn( [] );

        ob_start();
        $this->page->render_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'No events found', $html );
    }

    // -------------------------------------------------------------------------
    // Nonce validation
    // -------------------------------------------------------------------------

    public function test_render_page_skips_lookup_when_nonce_invalid(): void {
        // wp_verify_nonce returns false for 'bad-nonce'.
        // But TestHelpers wp_verify_nonce always returns true — so we override via GLOBALS trick.
        // Instead, test that with no _wpnonce in GET, lookup is skipped.
        $_GET = [
            'lookup_type'  => 'trace_id',
            'lookup_value' => 'tr-123',
            // No _wpnonce key — nonce check will fail ($nonce_valid = false).
        ];

        $this->trace_service->expects( $this->never() )->method( 'get_trace_timeline' );

        ob_start();
        $this->page->render_page();
        ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Privacy notice
    // -------------------------------------------------------------------------

    public function test_rendered_timeline_includes_privacy_notice(): void {
        $_GET = [
            'lookup_type'  => 'trace_id',
            'lookup_value' => 'tr-priv',
            '_wpnonce'     => 'valid',
        ];

        $this->trace_service->method( 'get_trace_timeline' )->willReturn( [
            [ 'event_name' => 'generate.request', 'trace_id' => 'tr-priv', 'timestamp' => 1000, 'created_at' => '2026-03-06 10:00:01', 'payload' => [] ],
        ] );

        ob_start();
        $this->page->render_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'PII-sanitized', $html );
    }

    // -------------------------------------------------------------------------
    // HTML form structure
    // -------------------------------------------------------------------------

    public function test_lookup_form_renders_all_lookup_type_options(): void {
        $_GET = [];

        ob_start();
        $this->page->render_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'trace_id',    $html );
        $this->assertStringContainsString( 'schedule_id', $html );
        $this->assertStringContainsString( 'variant_id',  $html );
    }

    public function test_lookup_form_shows_page_slug(): void {
        $_GET = [];

        ob_start();
        $this->page->render_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( TelemetryDebugPage::PAGE_SLUG, $html );
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function make_audit_mock(): AuditLogger {
        return $this->getMockBuilder( AuditLogger::class )
                    ->setConstructorArgs( [ new \wpdb() ] )
                    ->onlyMethods( [ 'get_events_by_trace', 'get_recent_telemetry_events' ] )
                    ->getMock();
    }
}
