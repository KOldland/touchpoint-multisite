<?php

namespace KHM\Tests\Rest;

use KHM\Contracts\IdempotencyStoreInterface;
use KHM\Contracts\MembershipRepositoryInterface;
use KHM\Contracts\OrderRepositoryInterface;
use KHM\Contracts\WebhookVerifierInterface;
use KHM\Rest\WebhooksController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class WebhooksRateLimitTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['khm_test_options']['khm_webhook_rate_limit_per_minute'] = 2;
        $GLOBALS['khm_test_options']['khm_webhook_badsig_threshold'] = 2;
        $GLOBALS['khm_test_options']['khm_webhook_badsig_window'] = 60;
        $GLOBALS['khm_test_options']['khm_webhook_block_base_ttl'] = 60;
        $GLOBALS['khm_test_options']['khm_webhook_block_max_ttl'] = 3600;
        $GLOBALS['khm_test_transients'] = [];
        putenv('KH_STRIPE_WEBHOOK_SECRET=whsec_test_secret');
    }

    protected function tearDown(): void {
        putenv('KH_STRIPE_WEBHOOK_SECRET');
        $GLOBALS['khm_test_transients'] = [];
        parent::tearDown();
    }

    public function test_returns_429_after_per_ip_threshold(): void {
        $counter = 0;
        $verifier = $this->createMock(WebhookVerifierInterface::class);
        $verifier->method('verify')->willReturnCallback(function () use (&$counter) {
            $counter++;
            return (object) [
                'id' => 'evt_' . $counter,
                'type' => 'noop.event',
                'data' => (object) [ 'object' => (object) [] ],
            ];
        });
        $verifier->method('getEventId')->willReturnCallback(static fn($event) => $event->id ?? 'evt_fallback');
        $verifier->method('getEventType')->willReturnCallback(static fn($event) => $event->type ?? 'noop.event');

        $idempotency = $this->createMock(IdempotencyStoreInterface::class);
        $idempotency->method('hasProcessed')->willReturn(false);

        $controller = new WebhooksController(
            $verifier,
            $idempotency,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(MembershipRepositoryInterface::class)
        );

        $request = new WP_REST_Request('POST', '/khm/v1/webhooks/stripe');
        $request->set_body('{}');
        $request->set_header('x-forwarded-for', '203.0.113.10');

        $r1 = $controller->handle_stripe($request, 'all');
        $r2 = $controller->handle_stripe($request, 'all');
        $r3 = $controller->handle_stripe($request, 'all');

        $this->assertInstanceOf(\WP_REST_Response::class, $r1);
        $this->assertSame(200, $r1->get_status());
        $this->assertSame(200, $r2->get_status());
        $this->assertInstanceOf(\WP_REST_Response::class, $r3);
        $this->assertSame(429, $r3->get_status());

        $payload = $r3->get_data();
        $this->assertSame('rate_limited', $payload['error']);
    }

    public function test_badsig_progressively_blocks_ip(): void {
        $verifier = $this->createMock(WebhookVerifierInterface::class);
        $verifier->method('verify')->willReturn(false);

        $idempotency = $this->createMock(IdempotencyStoreInterface::class);
        $idempotency->method('hasProcessed')->willReturn(false);

        $controller = new WebhooksController(
            $verifier,
            $idempotency,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(MembershipRepositoryInterface::class)
        );

        $request = new WP_REST_Request('POST', '/khm/v1/webhooks/stripe');
        $request->set_body('{}');
        $request->set_header('x-forwarded-for', '198.51.100.42');

        $r1 = $controller->handle_stripe($request, 'all');
        $r2 = $controller->handle_stripe($request, 'all');
        $r3 = $controller->handle_stripe($request, 'all');
        $r4 = $controller->handle_stripe($request, 'all');

        $this->assertInstanceOf(\WP_Error::class, $r1);
        $this->assertSame(400, $r1->get_error_data()['status']);
        $this->assertInstanceOf(\WP_Error::class, $r2);
        $this->assertSame(400, $r2->get_error_data()['status']);
        $this->assertInstanceOf(\WP_Error::class, $r3);
        $this->assertSame(400, $r3->get_error_data()['status']);

        $this->assertInstanceOf(\WP_REST_Response::class, $r4);
        $this->assertSame(429, $r4->get_status());

        $blockKey = 'khm_webhook_block:' . md5('198.51.100.42');
        $this->assertNotFalse(get_transient($blockKey));
    }
}
