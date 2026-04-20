<?php

namespace KHM\Tests\Connect;

use KHM\Connect\ConnectShortlistService;
use PHPUnit\Framework\TestCase;

class ConnectShortlistServiceTest extends TestCase {

	public function test_scores_exact_fit_above_partial_fit(): void {
		$service = new ConnectShortlistService();

		$providers = array(
			array(
				'id' => 1,
				'name' => 'Alpha ERP',
				'match_rules' => array(
					'industries' => array( 'aerospace', 'manufacturing' ),
					'regions' => array( 'uk' ),
					'company_sizes' => array( 'sme' ),
					'deployment' => array( 'cloud' ),
					'budget_min' => 1000,
					'budget_max' => 10000,
					'keywords' => array( 'erp', 'production planning' ),
				),
				'comparison_fields' => array( 'deployment' => 'Cloud' ),
			),
			array(
				'id' => 2,
				'name' => 'Beta CRM',
				'match_rules' => array(
					'industries' => array( 'retail' ),
					'regions' => array( 'us' ),
					'company_sizes' => array( 'enterprise' ),
					'deployment' => array( 'on-premise' ),
					'budget_min' => 20000,
					'budget_max' => 90000,
					'keywords' => array( 'crm' ),
				),
				'comparison_fields' => array( 'deployment' => 'On-premise' ),
			),
		);

		$criteria = array(
			'industries' => array( 'Aerospace' ),
			'regions' => array( 'UK' ),
			'company_sizes' => array( 'SME' ),
			'deployment' => array( 'Cloud' ),
			'budget' => 5000,
			'keywords' => array( 'ERP' ),
		);

		$results = $service->shortlist( $providers, $criteria, '', 5 );

		$this->assertCount( 1, $results );
		$this->assertSame( 'Alpha ERP', $results[0]['name'] );
		$this->assertGreaterThan( 0, $results[0]['score'] );
		$this->assertContains( 'Budget aligned', $results[0]['match_reasons'] );
	}

	public function test_applies_title_affinity_multiplier(): void {
		$service  = new ConnectShortlistService();
		$provider = array(
			'id' => 1,
			'name' => 'Gamma MES',
			'match_rules' => array(
				'industries' => array( 'manufacturing' ),
				'regions' => array( 'uk' ),
				'title_weights' => array( 'the-engineer' => 0.5 ),
			),
			'comparison_fields' => array(),
		);

		$base = $service->score_provider( $provider, array(
			'industries' => array( 'manufacturing' ),
			'regions' => array( 'uk' ),
			'company_sizes' => array(),
			'deployment' => array(),
			'keywords' => array(),
			'budget' => 0,
		), '' );

		$boosted = $service->score_provider( $provider, array(
			'industries' => array( 'manufacturing' ),
			'regions' => array( 'uk' ),
			'company_sizes' => array(),
			'deployment' => array(),
			'keywords' => array(),
			'budget' => 0,
		), 'the-engineer' );

		$this->assertGreaterThan( $base['score'], $boosted['score'] );
		$this->assertContains( 'Title affinity boost for the-engineer', $boosted['reasons'] );
	}
}