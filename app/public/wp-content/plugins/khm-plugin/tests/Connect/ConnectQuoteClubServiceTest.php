<?php

namespace KHM\Tests\Connect;

use KHM\Connect\ConnectProviderRepository;
use KHM\Connect\ConnectQuoteClubService;
use KHM\Connect\ConnectShortlistService;
use PHPUnit\Framework\TestCase;

class ConnectQuoteClubServiceTest extends TestCase {

	public function test_matches_only_commentary_enabled_providers(): void {
		$repository = new class extends ConnectProviderRepository {
			public function list_active( string $title_context = '' ): array {
				return array(
					array(
						'id' => 1,
						'name' => 'ForgeFlow',
						'slug' => 'forgeflow',
						'commentary_enabled' => true,
						'match_rules' => array(
							'industries' => array( 'manufacturing' ),
							'keywords' => array( 'erp' ),
						),
					),
					array(
						'id' => 2,
						'name' => 'HiddenVendor',
						'slug' => 'hiddenvendor',
						'commentary_enabled' => false,
						'match_rules' => array(
							'industries' => array( 'manufacturing' ),
						),
					),
				);
			}
		};

		$service = new ConnectQuoteClubService( $repository, new ConnectShortlistService() );

		$matches = $service->match_for_session(
			array(
				'title' => 'ERP roundtable',
				'topics' => array( 'manufacturing' ),
				'key_messages' => 'ERP transformation',
			)
		);

		$this->assertCount( 1, $matches );
		$this->assertSame( 'forgeflow', $matches[0]['slug'] );
	}

	public function test_provider_snapshot_respects_title_context(): void {
		$repository = new class extends ConnectProviderRepository {
			public function get_by_id( int $provider_id ): ?array {
				return array(
					'id' => $provider_id,
					'name' => 'ForgeFlow',
					'slug' => 'forgeflow',
					'commentary_enabled' => true,
					'titles' => array( 'the-engineer' ),
					'description' => 'ERP',
					'website_url' => 'https://example.com',
					'sponsor_id' => 5,
				);
			}
		};

		$service = new ConnectQuoteClubService( $repository, new ConnectShortlistService() );

		$this->assertNotNull( $service->get_provider_snapshot( 7, 'the-engineer' ) );
		$this->assertNull( $service->get_provider_snapshot( 7, 'other-title' ) );
	}
}