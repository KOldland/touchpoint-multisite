<?php

use KH_SMMA\Services\Card1StateStore;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/Card1StateStore.php';

class VariantRevisionTest extends TestCase {
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

	public function test_editing_variant_creates_revision_and_preserves_previous(): void {
		$store = new Card1StateStore();
		$request_id = $store->create_generate_request(
			array(
				'request_id' => 'req_rev_1',
				'post_id' => '100',
				'user_id' => 1,
				'prompt_hash' => 'hash',
				'model' => 'mock',
				'status' => 'success',
			)
		);
		$variant_id = $store->upsert_variant(
			$request_id,
			array(
				'variant_id' => 'var_rev_1',
				'text' => 'Original text',
				'rationale' => 'Why this works',
				'asset_hints' => array(
					array(
						'type' => 'image',
						'description' => 'Hero shot',
					),
				),
				'platform' => 'linkedin',
				'compliance_status' => 'PASS',
				'compliance_reason' => '',
			)
		);

		$first = $store->apply_variant_edit(
			$variant_id,
			'idem-rev-1',
			array(
				'editor_user_id' => '42',
				'text' => 'Updated text',
				'metadata' => array( 'edit_reason' => 'tone tweak' ),
			),
			array(
				'status' => 'PASS',
				'reasons' => array(),
			)
		);
		$second = $store->apply_variant_edit(
			$variant_id,
			'idem-rev-2',
			array(
				'editor_user_id' => '42',
				'text' => 'Second update',
				'metadata' => array( 'edit_reason' => 'final polish' ),
			),
			array(
				'status' => 'WARN',
				'reasons' => array( 'Contains superlative claim' ),
			)
		);

		$this->assertFalse( $first['idempotent'] );
		$this->assertFalse( $second['idempotent'] );
		$this->assertNotSame( $first['revision']['revision_id'], $second['revision']['revision_id'] );
		$this->assertSame( 'Original text', $first['revision']['diff']['previous_text'] );
		$this->assertSame( 'Updated text', $first['revision']['diff']['updated_text'] );
		$this->assertSame( 'tone tweak', $first['revision']['diff']['edit_reason'] );
		$this->assertSame( 'Updated text', $second['revision']['diff']['previous_text'] );
		$this->assertSame( 'Second update', $second['revision']['diff']['updated_text'] );
	}
}
