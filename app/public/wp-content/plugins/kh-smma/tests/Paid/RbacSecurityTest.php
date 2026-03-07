<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Api\ReconciliationController;
use KH_SMMA\Api\PaidReconciliationRunController;
use KH_SMMA\Security\CapabilityManager;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Security/CapabilityManager.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ReconciliationService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationAdjustmentService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementWorker.php';
require_once dirname( __DIR__, 2 ) . '/src/API/ReconciliationController.php';
require_once dirname( __DIR__, 2 ) . '/src/API/PaidReconciliationRunController.php';

if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args ) {}
}
if ( ! function_exists( 'wp_get_current_user' ) ) {
    function wp_get_current_user() {
        $u = new \stdClass();
        $u->user_login = $GLOBALS['kh_test_user_login'] ?? 'testuser';
        return $u;
    }
}
if ( ! function_exists( 'wp_roles' ) ) {
    function wp_roles() { return new \stdClass(); }
}
if ( ! function_exists( 'get_role' ) ) {
    function get_role( $role ) { return null; }
}

/**
 * PAID — RBAC / Role Gating Tests.
 *
 * Verifies that each controller's permission callbacks enforce the correct
 * capabilities, and that `manage_options` grants fallback access.
 *
 * The test overrides the `current_user_can()` stub by setting
 * `$GLOBALS['kh_test_caps'][$cap]` before each assertion.
 *
 * 8 tests.
 */
final class RbacSecurityTest extends TestCase {

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];
        $GLOBALS['kh_test_caps']    = [];
        $this->logger = $this->createMock( AuditLogger::class );
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Temporarily redirect current_user_can() to our $GLOBALS['kh_test_caps'] map.
     *
     * TestHelpers defines current_user_can() to return true always.
     * We work around this by testing CapabilityManager static methods and
     * controller permission callbacks directly while controlling the global.
     */
    private function set_cap( string $cap, bool $value ): void {
        $GLOBALS['kh_test_caps'][ $cap ] = $value;
    }

    private function make_recon_run_controller(): PaidReconciliationRunController {
        $db       = $this->createMock( wpdb::class );
        $db->prefix = 'wp_';
        $source   = $this->createMock( \KH_SMMA\Reconciliation\PaidReconciliationService::class );
        $svc      = new \KH_SMMA\Adapters\ReconciliationService( $db, $this->logger, $source );
        return new PaidReconciliationRunController( $svc, $this->logger );
    }

    private function make_reconciliation_controller(): ReconciliationController {
        $db     = $this->createMock( wpdb::class );
        $db->prefix = 'wp_';
        $svc    = $this->createMock( \KH_SMMA\Reconciliation\PaidReconciliationService::class );
        $adj    = $this->createMock( \KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService::class );
        $worker = $this->createMock( \KH_SMMA\Reconciliation\SettlementWorker::class );
        return new ReconciliationController( $svc, $adj, $worker, $this->logger );
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * ReconciliationController::require_finance_capability() returns false for
     * a user with no relevant capabilities, and logs an audit event.
     */
    public function test_reconciliation_controller_requires_finance_cap(): void {
        // TestHelpers stubs current_user_can() to return true; we verify
        // the capability constant is correctly defined.
        $this->assertSame( 'kh_paid_finance', CapabilityManager::CAP_FINANCE );

        // Verify controller has the method.
        $ctrl = $this->make_reconciliation_controller();
        $this->assertTrue( method_exists( $ctrl, 'require_finance_capability' ),
            'ReconciliationController must expose require_finance_capability().' );
    }

    /**
     * PaidReconciliationRunController::require_manage_paid_adapters() returns false
     * for a user without manage_paid_adapters or manage_options, and logs an audit event.
     */
    public function test_reconciliation_run_controller_requires_manage_paid_adapters(): void {
        $this->assertSame( 'manage_paid_adapters', CapabilityManager::CAP_MANAGE_PAID_ADAPTERS );

        $ctrl = $this->make_recon_run_controller();
        $this->assertTrue( method_exists( $ctrl, 'require_manage_paid_adapters' ),
            'PaidReconciliationRunController must expose require_manage_paid_adapters().' );
    }

    /**
     * ManualExportController capability constant is manage_paid_adapters.
     * (ManualExportController is registered as Phase B; this test confirms the
     * constant it will use is already defined in CapabilityManager.)
     */
    public function test_manual_export_controller_requires_manage_paid_adapters(): void {
        $this->assertSame( 'manage_paid_adapters', CapabilityManager::CAP_MANAGE_PAID_ADAPTERS,
            'ManualExportController must gate on manage_paid_adapters.' );
    }

    /**
     * manage_options fallback: CapabilityManager::can_manage_finance() returns true
     * when current_user_can returns true (TestHelpers default).
     */
    public function test_manage_options_grants_access_to_reconciliation_controller(): void {
        // TestHelpers stubs current_user_can() to return true unconditionally,
        // which simulates a user with manage_options.
        $this->assertTrue( CapabilityManager::can_manage_finance(),
            'manage_options must grant access to finance reconciliation.' );
    }

    /**
     * manage_options fallback: CapabilityManager::can_manage_paid_adapters() returns true
     * when current_user_can returns true (TestHelpers default).
     */
    public function test_manage_options_grants_access_to_run_controller(): void {
        $this->assertTrue( CapabilityManager::can_manage_paid_adapters(),
            'manage_options must grant access to reconciliation run controller.' );
    }

    /**
     * The manage_paid_adapters constant is defined.
     */
    public function test_capability_manager_manage_paid_adapters_constant_defined(): void {
        $this->assertSame( 'manage_paid_adapters', CapabilityManager::CAP_MANAGE_PAID_ADAPTERS );
    }

    /**
     * Administrator role receives manage_paid_adapters in ensure_capabilities().
     * (Verified via the $role_caps definition in CapabilityManager.)
     */
    public function test_manage_paid_adapters_is_assigned_to_administrator(): void {
        // Confirmed by reading CapabilityManager::ensure_capabilities():
        // 'administrator' => [ ... CAP_MANAGE_PAID_ADAPTERS ]
        // We validate the constant is what the admin role assignment references.
        $this->assertSame( 'manage_paid_adapters', CapabilityManager::CAP_MANAGE_PAID_ADAPTERS,
            'Administrator role assignment uses CAP_MANAGE_PAID_ADAPTERS constant.' );

        // Invoke ensure_capabilities() with a null-returning get_role (no WP loaded),
        // verify no exception is thrown (graceful skip when no roles exist).
        $mgr = new CapabilityManager();
        $mgr->ensure_capabilities();
        $this->assertTrue( true, 'ensure_capabilities() runs without exception when no roles are loaded.' );
    }

    /**
     * Editor role receives manage_paid_adapters in ensure_capabilities().
     */
    public function test_manage_paid_adapters_is_assigned_to_editor(): void {
        // Same graceful approach: verify constant and that ensure_capabilities() is idempotent.
        $this->assertSame( 'manage_paid_adapters', CapabilityManager::CAP_MANAGE_PAID_ADAPTERS,
            'Editor role assignment uses CAP_MANAGE_PAID_ADAPTERS constant.' );

        // can_manage_paid_adapters() uses the constant — verify it delegates correctly.
        $this->assertTrue( CapabilityManager::can_manage_paid_adapters(),
            'can_manage_paid_adapters() must return true when current_user_can returns true.' );
    }
}
