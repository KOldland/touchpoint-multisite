<?php

namespace KHM\Tests\Rest;

use KHM\Rest\FourAIngestionController;
use KHM\Services\CpEventIngestionService;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class FourAIngestionPermissionTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['khm_test_options']['khm_4a_ingest_token'] = 'secret-token';
    }

    protected function tearDown(): void {
        unset($GLOBALS['khm_test_options']['khm_4a_ingest_token']);
        parent::tearDown();
    }

    public function test_permission_callback_rejects_missing_token(): void {
        $service = $this->getMockBuilder(CpEventIngestionService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $controller = new FourAIngestionController($service);
        $request = new WP_REST_Request('POST', '/khm/v1/ingest/ga4');

        $result = $controller->permission_callback($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public function test_permission_callback_allows_valid_token(): void {
        $service = $this->getMockBuilder(CpEventIngestionService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $controller = new FourAIngestionController($service);
        $request = new WP_REST_Request('POST', '/khm/v1/ingest/ga4');
        $request->set_header('x-khm-ingest-key', 'secret-token');

        $result = $controller->permission_callback($request);

        $this->assertTrue($result);
    }
}
