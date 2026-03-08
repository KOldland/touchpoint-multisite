<?php

declare( strict_types=1 );

use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Sponsor\ApprovalSafetyService;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Scheduling/ScheduleRepository.php';
require_once dirname( __DIR__, 2 ) . '/src/Sponsor/ApprovalTelemetryService.php';
require_once dirname( __DIR__, 2 ) . '/src/Sponsor/ApprovalSafetyService.php';

class SafetyFixtureRepository extends ScheduleRepository {
    public array $marked = array();
    private array $rows;

    public function __construct( array $rows = array() ) {
        $this->rows = $rows;
    }

    public function markScheduleForReReview( string $schedule_id, string $reason ): bool {
        $this->marked[] = array(
            'schedule_id' => $schedule_id,
            'reason'      => $reason,
        );
        return true;
    }

    public function findSchedulesImpactedByClaimChange( string $sponsor_id, array $removed_claims, ?array $fixture_rows = null ): array {
        return parent::findSchedulesImpactedByClaimChange( $sponsor_id, $removed_claims, $fixture_rows ?? $this->rows );
    }
}

final class ApprovalSafetyServiceTest extends TestCase {
    private array $fixture;

    protected function setUp(): void {
        $GLOBALS['kh_test_filters'] = array();
        $GLOBALS['kh_test_caps'] = array();
        $GLOBALS['kh_test_current_user_id'] = 1;

        $path = dirname( __DIR__ ) . '/fixtures/sponsor/re_review_cases.json';
        $this->fixture = json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    private function logger(): AuditLogger {
        return $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();
    }

    public function test_approval_blocked_on_compliance_fail(): void {
        $repo = new SafetyFixtureRepository();
        $svc = new ApprovalSafetyService( $repo, $this->logger() );

        $error = $svc->ensure_approvable( $this->fixture['fail_approval_case']['schedule'], 1 );

        $this->assertInstanceOf( WP_Error::class, $error );
        $this->assertArrayHasKey( 'COMPLIANCE_FAIL_APPROVAL_BLOCKED', $error->errors );
    }

    public function test_compliance_change_triggers_re_review(): void {
        $repo = new SafetyFixtureRepository();
        $svc = new ApprovalSafetyService( $repo, $this->logger() );

        $updated = $svc->apply_re_review_if_needed( $this->fixture['compliance_change_case']['schedule'], 1 );

        $this->assertSame( 'pending', $updated['approval_status'] );
        $this->assertTrue( $updated['approval_required'] );
        $this->assertSame( 'compliance_changed', $updated['approval_reason'] );
        $this->assertSame( 'compliance_changed', $repo->marked[0]['reason'] );
    }

    public function test_claim_change_triggers_re_review(): void {
        $case = $this->fixture['claim_change_case'];
        $repo = new SafetyFixtureRepository( $case['schedules'] );
        $svc = new ApprovalSafetyService( $repo, $this->logger() );

        $impacted = $svc->trigger_claim_change_re_review(
            $case['sponsor_id'],
            $case['previous_allowed_claims'],
            $case['current_allowed_claims'],
            1,
            $case['schedules']
        );

        $this->assertCount( 1, $impacted );
        $this->assertSame( 'pending', $impacted[0]['approval_status'] );
        $this->assertSame( 'sponsor_claim_change', $impacted[0]['approval_reason'] );
        $this->assertSame( 'sponsor_claim_change', $repo->marked[0]['reason'] );
    }
}
