<?php
namespace KH_SMMA\Tests;

use KH_SMMA\Compliance\ComplianceRulesStore;
use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Sponsor\ApprovalSafetyService;
use PHPUnit\Framework\TestCase;

class ComplianceAdminWorkflowTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['kh_test_options'] = array();
        $GLOBALS['kh_test_post_meta'] = array();
        $GLOBALS['kh_test_wp_query_posts'] = array();
    }

    public function test_rule_update_increments_corpus_version(): void {
        $store = new ComplianceRulesStore();

        $store->add_or_update_phrase( 'risk-free returns', 'FAIL', 'marketing_claim', 3 );
        $meta = $store->increment_corpus_version( 3 );

        $this->assertSame( 2, $meta['corpus_version'] );
        $this->assertSame( 3, $meta['updated_by'] );
    }

    public function test_corpus_change_flags_approved_schedules_for_rereview(): void {
        $GLOBALS['kh_test_wp_query_posts'] = array( 101, 102, 103 );
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] = 'approved';
        $GLOBALS['kh_test_post_meta'][102]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][103]['_kh_smma_approval_status'] = 'approved';

        $service = new ApprovalSafetyService( new ScheduleRepository() );
        $count = $service->trigger_rereview_for_corpus_version( 9, 42 );

        $this->assertSame( 2, $count );
        $this->assertSame( 1, $GLOBALS['kh_test_post_meta'][101]['_kh_smma_requires_rereview'] );
        $this->assertSame( 'pending', $GLOBALS['kh_test_post_meta'][103]['_kh_smma_approval_status'] );
        $this->assertSame( 'awaiting_approval', $GLOBALS['kh_test_post_meta'][103]['_kh_smma_schedule_status'] );
        $this->assertArrayNotHasKey( '_kh_smma_requires_rereview', $GLOBALS['kh_test_post_meta'][102] );
    }
}
