<?php

namespace KHM\Tests\Membership;

use KHM\Membership\LandingSuccessEndpoint;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class LandingSuccessEndpointTest extends TestCase {
    private LandingSuccessEndpoint $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new LandingSuccessEndpoint();

        global $khm_test_options;
        $khm_test_options = [];
    }

    public function testCompletePayload(): void {
        $sessionId = 'cs_test_success_001';
        update_option( 'khm_temp_attribution_' . $sessionId, [
            'session_id' => $sessionId,
            'payload' => [
                'schedule_id' => 'sch_123',
                'sponsor_id' => 'sp_456',
                'utm_source' => 'newsletter',
                'utm_medium' => 'email',
                'utm_campaign' => 'spring',
                'phase_at_click' => 'landing',
                'consent' => true,
                'status' => 'complete',
                'membership_status' => 'active',
            ],
            'expires_at' => time() + 3600,
        ] );

        $request = new WP_REST_Request( 'GET', '/kh-membership/v1/landing-success' );
        $request->set_param( 'session_id', $sessionId );

        $response = $this->endpoint->handle_request( $request );

        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();

        $this->assertEquals( $sessionId, $data['session_id'] );
        $this->assertEquals( 'complete', $data['status'] );
        $this->assertEquals( 'active', $data['membership_status'] );
        $this->assertArrayHasKey( 'schedule', $data );
        $this->assertArrayHasKey( 'ctas', $data );
        $this->assertIsArray( $data['ctas'] );
        $this->assertNotEmpty( $data['ctas'] );
        $this->assertTrue( (bool) $data['consent'] );
        $this->assertIsArray( $data['attribution'] );
        $this->assertEquals( 'newsletter', $data['attribution']['utm_source'] ?? '' );
    }

    public function testConsentFalseHidesAttribution(): void {
        $sessionId = 'cs_test_success_002';
        update_option( 'khm_temp_attribution_' . $sessionId, [
            'session_id' => $sessionId,
            'payload' => [
                'schedule_id' => 'sch_123',
                'sponsor_id' => 'sp_456',
                'utm_source' => 'newsletter',
                'utm_medium' => 'email',
                'utm_campaign' => 'spring',
                'phase_at_click' => 'landing',
                'consent' => false,
                'status' => 'complete',
                'membership_status' => 'active',
            ],
            'expires_at' => time() + 3600,
        ] );

        $request = new WP_REST_Request( 'GET', '/kh-membership/v1/landing-success' );
        $request->set_param( 'session_id', $sessionId );

        $response = $this->endpoint->handle_request( $request );

        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();

        $this->assertFalse( (bool) $data['consent'] );
        $this->assertNull( $data['attribution'] );
        $this->assertNull( $data['sponsor'] );
    }
}
