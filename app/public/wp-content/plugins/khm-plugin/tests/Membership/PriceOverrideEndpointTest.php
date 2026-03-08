<?php

namespace KHM\Tests\Membership;

use KHM\Membership\PriceOverrideEndpoint;
use KHM\Services\MembershipRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

require_once dirname(__DIR__, 2) . '/src/Membership/PriceOverrideEndpoint.php';
require_once dirname(__DIR__, 2) . '/src/Services/MembershipRepository.php';

class PriceOverrideEndpointTest extends TestCase {
    private PriceOverrideEndpoint $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new PriceOverrideEndpoint();

        global $khm_test_options;
        $khm_test_options = [];
    }

    public function test_override_persists_when_amounts_are_valid(): void {
        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/price-override' );
        $request->set_body( wp_json_encode( [
            'reference_id' => 'demo-price-review',
            'currency' => 'AUD',
            'items' => [
                [ 'key' => 'creative_setup', 'label' => 'Creative setup', 'amount_cents' => 4500 ],
                [ 'key' => 'campaign_management', 'label' => 'Campaign management', 'amount_cents' => 8500 ],
            ],
        ] ) );

        $response = $this->endpoint->handle_request( $request );

        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertTrue( (bool) $data['ok'] );
        $this->assertSame( 13000, $data['total_amount_cents'] );

        $repo = new MembershipRepository();
        $stored = $repo->getPriceReviewOverride( 'demo-price-review' );
        $this->assertIsArray( $stored );
        $this->assertSame( 'AUD', $stored['currency'] );
        $this->assertCount( 2, $stored['items'] );
    }

    public function test_override_rejects_out_of_range_amounts(): void {
        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/price-override' );
        $request->set_body( wp_json_encode( [
            'reference_id' => 'demo-price-review',
            'currency' => 'AUD',
            'items' => [
                [ 'key' => 'creative_setup', 'label' => 'Creative setup', 'amount_cents' => 9000000 ],
            ],
        ] ) );

        $response = $this->endpoint->handle_request( $request );

        $this->assertEquals( 422, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( 'Override amount is outside the allowed range.', $data['message'] );
    }
}
