<?php

namespace KHM\Tests\Rest;

use KHM\Connect\ConnectComparisonEndpoint;
use KHM\Connect\ConnectComparisonService;
use KHM\Connect\ConnectProviderRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class ConnectComparisonEndpointTest extends TestCase {

	public function test_returns_comparison_matrix_for_requested_providers(): void {
		$repository = new class extends ConnectProviderRepository {
			public function list_active( string $title_context = '' ): array {
				return array(
					array(
						'id' => 21,
						'name' => 'ForgeFlow',
						'slug' => 'forgeflow',
						'comparison_fields' => array(
							'deployment' => 'Cloud',
							'pricing_model' => 'Subscription',
						),
					),
					array(
						'id' => 22,
						'name' => 'PlantPilot',
						'slug' => 'plantpilot',
						'comparison_fields' => array(
							'deployment' => 'Hybrid',
							'pricing_model' => 'Quote-based',
						),
					),
				);
			}
		};

		$endpoint = new ConnectComparisonEndpoint( $repository, new ConnectComparisonService() );

		$request = new WP_REST_Request( 'POST', '/khm/v1/connect/compare' );
		$request->set_body(
			wp_json_encode(
				array(
					'provider_ids' => array( 21, 22 ),
					'fields' => array( 'deployment', 'pricing_model' ),
				)
			)
		);

		$response = $endpoint->handle( $request );
		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $data['site_id'] );
		$this->assertSame( 2, $data['comparison']['provider_count'] );
		$this->assertSame( 'deployment', $data['comparison']['rows'][0]['key'] );
		$this->assertSame( 'Hybrid', $data['comparison']['rows'][0]['values'][1]['value'] );
	}

	public function test_rejects_invalid_provider_count(): void {
		$endpoint = new ConnectComparisonEndpoint( new ConnectProviderRepository(), new ConnectComparisonService() );

		$request = new WP_REST_Request( 'POST', '/khm/v1/connect/compare' );
		$request->set_body( wp_json_encode( array( 'provider_ids' => array( 21 ) ) ) );

		$response = $endpoint->handle( $request );

		$this->assertSame( 'connect_invalid_provider_count', $response->get_error_code() );
	}

	public function test_rejects_site_context_mismatch(): void {
		$endpoint = new ConnectComparisonEndpoint( new ConnectProviderRepository(), new ConnectComparisonService() );

		$request = new WP_REST_Request( 'POST', '/khm/v1/connect/compare' );
		$request->set_body(
			wp_json_encode(
				array(
					'site_id' => 99,
					'provider_ids' => array( 21, 22 ),
				)
			)
		);

		$response = $endpoint->handle( $request );

		$this->assertSame( 'connect_site_context_mismatch', $response->get_error_code() );
	}
}