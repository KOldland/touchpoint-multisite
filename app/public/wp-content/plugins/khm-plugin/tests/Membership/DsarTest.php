<?php

namespace KHM\Tests\Membership;

use KHM\Membership\DsarController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class DsarTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['khm_test_options'] = [];
        $GLOBALS['khm_test_transients'] = [];
        $GLOBALS['khm_test_current_user_caps'] = [];
    }

    public function test_authenticated_user_can_request_anonymize_dsar_and_admin_can_approve(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'promotion_attribution';

        $wpdb->insert( $table, [
            'id' => 9201,
            'user_id' => 500,
            'user_email' => 'dsar@example.com',
            'utm_source' => 'newsletter',
            'consent' => 1,
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
        ] );

        $controller = new DsarController();

        $GLOBALS['khm_test_current_user_id'] = 500;
        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/dsar/request' );
        $request->set_body( wp_json_encode( [ 'type' => 'anonymize', 'ticket_id' => 'T-123' ] ) );
        $response = $controller->request( $request );

        $this->assertEquals( 202, $response->get_status() );
        $data = $response->get_data();
        $this->assertNotEmpty( $data['request_id'] ?? '' );

        $GLOBALS['khm_test_current_user_id'] = 1;
        $GLOBALS['khm_test_current_user_caps'] = [ 'manage_options' => true ];

        $approve = new WP_REST_Request( 'POST', '/kh-membership/v1/dsar/approve' );
        $approve->set_body( wp_json_encode( [
            'request_id' => $data['request_id'],
            'ticket_id' => 'T-123',
        ] ) );
        $approveResponse = $controller->approve( $approve );

        $this->assertEquals( 200, $approveResponse->get_status() );

        $row = $wpdb->get_row( "SELECT * FROM {$table} WHERE id = 9201", ARRAY_A );
        $this->assertNotEmpty( $row['anonymized_at'] ?? '' );
        $this->assertEmpty( $row['utm_source'] ?? null );
    }
}
