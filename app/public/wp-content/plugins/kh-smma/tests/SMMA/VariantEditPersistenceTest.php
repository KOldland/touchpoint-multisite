<?php

use KH_SMMA\Services\Card1StateStore;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/Card1StateStore.php';

final class VariantEditPersistenceTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		putenv( 'KH_SMMA_TEST_MODE=ci' );
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = array(
			'generate_requests' => array(),
			'variants' => array(),
			'variant_revisions' => array(),
			'schedules' => array(),
			'schedule_queue' => array(),
			'idempotency' => array(),
		);
	}

	public function test_variant_edit_creates_revision_with_metadata(): void {
		$fixture = json_decode(
			(string) file_get_contents( dirname( __DIR__ ) . '/fixtures/smma/variant_edit_case.json' ),
			true
		);
		$store = new Card1StateStore();

		$store->create_generate_request( array( 'request_id' => 'req_variant_edit_1', 'post_id' => '1', 'status' => 'success' ) );
		$store->upsert_variant(
			'req_variant_edit_1',
			array(
				'variant_id' => $fixture['variant_id'],
				'text' => $fixture['previous_text'],
				'rationale' => 'Fixture rationale',
				'asset_hints' => array(),
				'platform' => 'linkedin',
				'compliance_status' => 'OK',
				'compliance_reason' => '',
				'compliance' => array( 'status' => 'OK', 'reasons' => array() ),
			),
			array()
		);

		$result = $store->apply_variant_edit(
			(string) $fixture['variant_id'],
			(string) $fixture['idempotency_key'],
			array(
				'editor_user_id' => (string) $fixture['editor_user_id'],
				'text' => (string) $fixture['updated_text'],
				'metadata' => array( 'edit_reason' => (string) $fixture['edit_reason'] ),
				'asset_hints' => array(),
			),
			array(
				'status' => 'WARN',
				'reasons' => array( 'review required' ),
			)
		);

		$this->assertFalse( $result['idempotent'] );
		$this->assertArrayHasKey( 'revision', $result );
		$this->assertSame( (string) $fixture['variant_id'], $result['revision']['variant_id'] );
		$this->assertSame( (string) $fixture['editor_user_id'], $result['revision']['editor_user_id'] );
		$this->assertSame( (string) $fixture['previous_text'], $result['revision']['diff']['previous_text'] );
		$this->assertSame( (string) $fixture['updated_text'], $result['revision']['diff']['updated_text'] );
		$this->assertNotEmpty( $result['revision']['created_at'] );
	}
}
