<?php
namespace KH_SMMA\Tests;

use KH_SMMA\Compliance\ComplianceRulesStore;
use KH_SMMA\Sponsor\ApprovalPermissionService;
use PHPUnit\Framework\TestCase;

class ComplianceRulesAdminTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['kh_test_options'] = array();
        $GLOBALS['kh_test_user_caps'] = array();
        $GLOBALS['kh_test_user_meta'] = array();
    }

    public function test_phrase_add_edit_remove_and_duplicate_validation(): void {
        $store = new ComplianceRulesStore();

        $added = $store->add_or_update_phrase( 'guaranteed results', 'FAIL', 'marketing_claim', 7 );
        $this->assertTrue( $added['ok'] );

        $second = $store->add_or_update_phrase( 'risk-free returns', 'WARN', 'marketing_claim', 7 );
        $this->assertTrue( $second['ok'] );

        $dup = $store->add_or_update_phrase( 'guaranteed results', 'WARN', 'marketing_claim', 7, $second['phrase_id'] );
        $this->assertFalse( $dup['ok'] );

        $edited = $store->add_or_update_phrase( 'guaranteed results', 'WARN', 'marketing_claim', 7, $added['phrase_id'] );
        $this->assertTrue( $edited['ok'] );
        $this->assertSame( 'WARN', $edited['record']['severity'] );

        $removed = $store->remove_phrase( $added['phrase_id'] );
        $this->assertTrue( $removed['ok'] );
    }

    public function test_allowed_claims_update_persists_with_audit_fields(): void {
        $store = new ComplianceRulesStore();

        $updated = $store->update_sponsor_claims( 22, array( 'eco friendly', 'ISO certified' ), 8 );
        $this->assertTrue( $updated['ok'] );
        $this->assertSame( 22, $updated['current']['sponsor_id'] );
        $this->assertSame( 8, $updated['current']['updated_by'] );
        $this->assertCount( 2, $updated['current']['allowed_claims'] );
    }

    public function test_permission_service_enforces_scope_for_sponsor_manager(): void {
        $permissions = new ApprovalPermissionService();

        $GLOBALS['kh_test_user_caps'][15]['kh_smma_manage_sponsor_claims'] = true;
        $GLOBALS['kh_test_user_meta'][15]['kh_smma_sponsor_ids'] = array( '22' );

        $this->assertTrue( $permissions->can_manage_sponsor_claims( 22, 15 ) );
        $this->assertFalse( $permissions->can_manage_sponsor_claims( 77, 15 ) );

        $GLOBALS['kh_test_user_caps'][1]['manage_options'] = true;
        $this->assertTrue( $permissions->can_manage_banned_phrases( 1 ) );
    }
}
