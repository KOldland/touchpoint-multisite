<?php

namespace KHM\Tests\Connect;

use KHM\Connect\ConnectOpportunityEndpoint;
use KHM\Connect\ConnectOpportunityRepository;
use KHM\Connect\ConnectProviderRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class ConnectOpportunityEndpointTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['khm_test_current_user_id'] = 1;
		$GLOBALS['khm_test_current_user_caps'] = array( 'manage_options' => true );
		$_GET['sponsor_id'] = 44;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['khm_test_current_user_id'], $GLOBALS['khm_test_current_user_caps'], $_GET['sponsor_id'] );
		parent::tearDown();
	}

	public function test_list_mine_returns_claimed_and_open_inbox_rows(): void {
		$opportunities = new class extends ConnectOpportunityRepository {
			public function __construct() {}

			public function list_inbox_for_sponsor( int $sponsor_id ): array {
				return array(
					array(
						'id' => 11,
						'commercial_tier' => 'exploring',
						'internal_stage' => 'attention',
						'person_score' => 55.1,
						'opportunity_status' => 'detected',
						'pricing_model' => 'cpl',
						'unit_price_cents' => 5000,
						'commission_eligible' => 0,
						'provider_id' => 0,
						'created_at' => '2026-04-28 00:00:00',
					),
					array(
						'id' => 12,
						'commercial_tier' => 'assessing',
						'internal_stage' => 'diagnosis',
						'person_score' => 72.4,
						'opportunity_status' => 'sponsor_accepted',
						'pricing_model' => 'cpl',
						'unit_price_cents' => 15000,
						'commission_eligible' => 0,
						'provider_id' => 9,
						'created_at' => '2026-04-28 00:00:00',
					),
				);
			}
		};

		$endpoint = new ConnectOpportunityEndpoint( $opportunities, new ConnectProviderRepository() );
		$request  = new WP_REST_Request( 'GET', '/khm/v1/connect/opportunities/mine' );

		$response = $endpoint->list_mine( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertCount( 2, $data['opportunities'] );
		$this->assertSame( 11, $data['opportunities'][0]['id'] );
		$this->assertSame( 'detected', $data['opportunities'][0]['opportunity_status'] );
		$this->assertSame( 12, $data['opportunities'][1]['id'] );
		$this->assertSame( 'sponsor_accepted', $data['opportunities'][1]['opportunity_status'] );
	}

	public function test_get_mine_returns_open_unclaimed_opportunity_when_visible_to_sponsor(): void {
		$opportunities = new class extends ConnectOpportunityRepository {
			public function __construct() {}

			public function get_inbox_for_sponsor( int $opportunity_id, int $sponsor_id ): ?array {
				return array(
					'id' => $opportunity_id,
					'commercial_tier' => 'assessing',
					'internal_stage' => 'diagnosis',
					'person_score' => 64.3,
					'opportunity_status' => 'offered',
					'pricing_model' => 'cpl',
					'unit_price_cents' => 12000,
					'commission_eligible' => 1,
					'provider_id' => 0,
					'created_at' => '2026-04-28 00:00:00',
				);
			}
		};

		$endpoint = new ConnectOpportunityEndpoint( $opportunities, new ConnectProviderRepository() );
		$request  = new WP_REST_Request( 'GET', '/khm/v1/connect/opportunities/mine/55' );
		$request->set_param( 'id', 55 );

		$response = $endpoint->get_mine( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertSame( 55, $data['opportunity']['id'] );
		$this->assertSame( 'offered', $data['opportunity']['opportunity_status'] );
	}

	public function test_accept_mine_assigns_provider_and_returns_updated_opportunity(): void {
		$opportunities = new class extends ConnectOpportunityRepository {
			public int $last_id = 0;
			public int $last_sponsor_id = 0;
			public int $last_provider_id = 0;
			public bool $accepted = false;

			public function __construct() {}

			public function get_inbox_for_sponsor( int $opportunity_id, int $sponsor_id ): ?array {
				if ( ! $this->accepted ) {
					return array(
						'id' => $opportunity_id,
						'opportunity_status' => 'offered',
					);
				}

				return array(
					'id' => $opportunity_id,
					'commercial_tier' => 'assessing',
					'internal_stage' => 'diagnosis',
					'person_score' => 72.4,
					'opportunity_status' => 'sponsor_accepted',
					'pricing_model' => 'cpl',
					'unit_price_cents' => 15000,
					'commission_eligible' => 0,
					'provider_id' => 9,
					'sponsor_id' => $sponsor_id,
					'created_at' => '2026-04-28 00:00:00',
				);
			}

			public function mark_sponsor_acceptance( int $opportunity_id, int $sponsor_id, int $provider_id ): bool {
				$this->last_id = $opportunity_id;
				$this->last_sponsor_id = $sponsor_id;
				$this->last_provider_id = $provider_id;
				$this->accepted = true;
				return true;
			}
		};

		$providers = new class extends ConnectProviderRepository {
			public function __construct() {}

			public function get_by_id( int $provider_id ): ?array {
				return array(
					'id' => $provider_id,
					'sponsor_id' => 44,
					'name' => 'Assigned Provider',
				);
			}
		};

		$endpoint = new ConnectOpportunityEndpoint( $opportunities, $providers );
		$request = new WP_REST_Request( 'POST', '/khm/v1/connect/opportunities/mine/77/accept' );
		$request->set_param( 'id', 77 );
		$request->set_body( wp_json_encode( array( 'provider_id' => 9 ) ) );

		$response = $endpoint->accept_mine( $request );
		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertSame( 77, $opportunities->last_id );
		$this->assertSame( 44, $opportunities->last_sponsor_id );
		$this->assertSame( 9, $opportunities->last_provider_id );
		$this->assertSame( 'sponsor_accepted', $data['opportunity']['opportunity_status'] );
		$this->assertSame( 9, $data['opportunity']['provider_id'] );
	}
}
