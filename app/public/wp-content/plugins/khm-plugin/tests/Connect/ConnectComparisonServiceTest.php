<?php

namespace KHM\Tests\Connect;

use KHM\Connect\ConnectComparisonService;
use PHPUnit\Framework\TestCase;

class ConnectComparisonServiceTest extends TestCase {

	public function test_builds_ordered_comparison_rows(): void {
		$service = new ConnectComparisonService();

		$matrix = $service->build_matrix(
			array(
				array(
					'id' => 11,
					'name' => 'ForgeFlow',
					'slug' => 'forgeflow',
					'comparison_fields' => array(
						'deployment' => 'Cloud',
						'pricing_model' => 'Subscription',
						'integrations' => array( 'ERP', 'CRM' ),
					),
				),
				array(
					'id' => 12,
					'name' => 'PlantPilot',
					'slug' => 'plantpilot',
					'comparison_fields' => array(
						'deployment' => 'Hybrid',
						'pricing_model' => 'Quote-based',
					),
				),
			)
		);

		$this->assertSame( 2, $matrix['provider_count'] );
		$this->assertSame( 'deployment', $matrix['rows'][0]['key'] );
		$this->assertSame( 'Cloud', $matrix['rows'][0]['values'][0]['value'] );
		$this->assertSame( 'ERP, CRM', $matrix['rows'][2]['values'][0]['value'] );
	}

	public function test_validate_provider_count_enforces_bounds(): void {
		$service = new ConnectComparisonService();

		$this->assertFalse( $service->validate_provider_count( array( 1 ) ) );
		$this->assertTrue( $service->validate_provider_count( array( 1, 2 ) ) );
		$this->assertFalse( $service->validate_provider_count( array( 1, 2, 3, 4, 5, 6 ) ) );
	}
}