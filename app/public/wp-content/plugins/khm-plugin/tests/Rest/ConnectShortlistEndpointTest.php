<?php

namespace KHM\Tests\Rest;

use KHM\Connect\ConnectProviderRepository;
use KHM\Connect\ConnectShortlistEndpoint;
use KHM\Connect\ConnectShortlistService;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class ConnectShortlistEndpointTest extends TestCase {

	public function test_returns_ranked_shortlist_response(): void {
		$repository = new class extends ConnectProviderRepository {
			public function list_active( string $title_context = '' ): array {
				return array(
					array(
						'id' => 7,
						'name' => 'ForgeFlow',
						'slug' => 'forgeflow',
						'description' => 'ERP for discrete manufacturing',
						'website_url' => 'https://example.com',
						'commentary_enabled' => true,
						'ad_targeting_enabled' => true,
						'titles' => array( 'the-engineer' ),
						'match_rules' => array(
							'industries' => array( 'manufacturing' ),
							'regions' => array( 'uk' ),
							'title_weights' => array( 'the-engineer' => 0.4 ),
						),
						'comparison_fields' => array( 'deployment' => 'Cloud' ),
					),
				);
			}
		};

		$endpoint = new ConnectShortlistEndpoint( $repository, new ConnectShortlistService() );

		$request = new WP_REST_Request( 'POST', '/khm/v1/connect/shortlist' );
		$request->set_body(
			wp_json_encode(
				array(
					'title_context' => 'the-engineer',
					'criteria' => array(
						'industries' => array( 'manufacturing' ),
						'regions' => array( 'uk' ),
					),
				)
			)
		);

		$response = $endpoint->handle( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 1, $data['site_id'] );
		$this->assertSame( 1, $data['count'] );
		$this->assertSame( 'forgeflow', $data['providers'][0]['slug'] );
		$this->assertTrue( $data['providers'][0]['commentary_enabled'] );
	}

	public function test_rejects_empty_criteria(): void {
		$endpoint = new ConnectShortlistEndpoint( new ConnectProviderRepository(), new ConnectShortlistService() );

		$request = new WP_REST_Request( 'POST', '/khm/v1/connect/shortlist' );
		$request->set_body( wp_json_encode( array( 'criteria' => array() ) ) );

		$response = $endpoint->handle( $request );

		$this->assertSame( 'connect_missing_criteria', $response->get_error_code() );
	}

	public function test_rejects_site_context_mismatch(): void {
		$endpoint = new ConnectShortlistEndpoint( new ConnectProviderRepository(), new ConnectShortlistService() );

		$request = new WP_REST_Request( 'POST', '/khm/v1/connect/shortlist' );
		$request->set_body(
			wp_json_encode(
				array(
					'site_id' => 99,
					'criteria' => array( 'industries' => array( 'manufacturing' ) ),
				)
			)
		);

		$response = $endpoint->handle( $request );

		$this->assertSame( 'connect_site_context_mismatch', $response->get_error_code() );
	}
}