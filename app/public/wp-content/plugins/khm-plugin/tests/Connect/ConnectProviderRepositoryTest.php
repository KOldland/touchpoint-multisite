<?php

namespace KHM\Tests\Connect;

use KHM\Connect\ConnectProviderRepository;
use KHM\Migrations\ConnectProvidersMigration;
use PHPUnit\Framework\TestCase;

class ConnectProviderRepositoryTest extends TestCase {

	private ConnectProviderRepository $repository;

	private string $table_name;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$GLOBALS['khm_test_filters'] = [];

		ConnectProvidersMigration::run();
		$this->repository = new ConnectProviderRepository();
		$this->table_name = $wpdb->prefix . 'connect_providers';
		$wpdb->query( "DELETE FROM {$this->table_name}" );
	}

	protected function tearDown(): void {
		$GLOBALS['khm_test_filters'] = [];
		parent::tearDown();
	}

	public function test_save_creates_provider_with_normalized_fields(): void {
		$provider_id = $this->repository->save(
			array(
				'name' => 'Forge Flow ERP',
				'titles' => 'The Engineer, Manufacturing Management',
				'comparison_fields' => '{"deployment":"Cloud","support_model":"Dedicated"}',
				'match_rules' => '{"industries":["Manufacturing"],"budget_min":1000}',
				'commentary_enabled' => true,
			)
		);

		$this->assertGreaterThan( 0, $provider_id );
		$provider = $this->repository->get_by_id( $provider_id );

		$this->assertNotNull( $provider );
		$this->assertSame( 'forge-flow-erp', $provider['slug'] );
		$this->assertSame( array( 'the-engineer', 'manufacturing-management' ), $provider['titles'] );
		$this->assertTrue( $provider['commentary_enabled'] );
		$this->assertSame( 'Cloud', $provider['comparison_fields']['deployment'] );
	}

	public function test_save_updates_existing_provider(): void {
		$provider_id = $this->repository->save(
			array(
				'name' => 'Original Name',
				'status' => 'active',
			)
		);

		$this->repository->save(
			array(
				'id' => $provider_id,
				'name' => 'Updated Name',
				'status' => 'inactive',
				'website_url' => 'https://example.com/provider',
			)
		);

		$provider = $this->repository->get_by_id( $provider_id );

		$this->assertNotNull( $provider );
		$this->assertSame( 'Updated Name', $provider['name'] );
		$this->assertSame( 'inactive', $provider['status'] );
		$this->assertSame( 'https://example.com/provider', $provider['website_url'] );
	}

	public function test_delete_removes_provider(): void {
		$provider_id = $this->repository->save(
			array(
				'name' => 'Delete Me',
			)
		);

		$this->assertTrue( $this->repository->delete( $provider_id ) );
		$this->assertNull( $this->repository->get_by_id( $provider_id ) );
	}

	public function test_repository_enforces_site_context_for_get_and_delete(): void {
		$provider_id = $this->repository->save(
			array(
				'name' => 'Site Two Provider',
				'blog_id' => 2,
			)
		);

		add_filter(
			'khm_connect_current_blog_id',
			static function ( $current ): int {
				return 3;
			}
		);

		$this->assertNull( $this->repository->get_by_id( $provider_id ) );
		$this->assertFalse( $this->repository->delete( $provider_id ) );
	}
}