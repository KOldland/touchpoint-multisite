<?php

namespace KHM\Tests\Membership;

use KHM\Membership\CustomerPortalEndpoint;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class CustomerPortalEndpointTest extends TestCase {
    private $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new CustomerPortalEndpoint();
        $GLOBALS['khm_test_current_user_id'] = 0;
        $GLOBALS['khm_test_current_user_caps'] = [];
    }

    protected function tearDown(): void {
        $GLOBALS['khm_test_current_user_id'] = 0;
        $GLOBALS['khm_test_current_user_caps'] = [];
        parent::tearDown();
    }

    public function test_check_permission_requires_authentication(): void {
        $request = new WP_REST_Request('POST');
        $result = $this->endpoint->check_permission($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('rest_forbidden', $result->get_error_code());
        $this->assertEquals(401, $result->get_error_data()['status']);
    }

    public function test_check_permission_enforces_ownership_for_non_admin(): void {
        $GLOBALS['khm_test_current_user_id'] = 1001;
        $GLOBALS['khm_test_current_user_caps'] = [ 'manage_options' => false ];

        $request = new WP_REST_Request('POST');
        $request->set_param('user_id', 1002);
        $result = $this->endpoint->check_permission($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('rest_forbidden', $result->get_error_code());
        $this->assertEquals(403, $result->get_error_data()['status']);
    }

    public function test_check_permission_allows_admin_override(): void {
        $GLOBALS['khm_test_current_user_id'] = 1;
        $GLOBALS['khm_test_current_user_caps'] = [ 'manage_options' => true ];

        $request = new WP_REST_Request('POST');
        $request->set_param('user_id', 2000);
        $result = $this->endpoint->check_permission($request);

        $this->assertTrue($result);
    }
}

