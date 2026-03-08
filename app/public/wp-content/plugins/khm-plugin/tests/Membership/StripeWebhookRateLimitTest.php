<?php

namespace KHM\Tests\Membership;

use KHM\Membership\StripeWebhookHandler;
use KHM\Services\RateLimitService;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class StripeWebhookRateLimitTest extends TestCase {
    /** @var array<int,array{tag:string,callback:mixed,priority:int}> */
    private array $registeredFilters = [];

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['khm_test_transients'] = [];
    }

    protected function tearDown(): void {
        foreach ( $this->registeredFilters as $filter ) {
            remove_filter( $filter['tag'], $filter['callback'], $filter['priority'] );
        }
        $this->registeredFilters = [];
        $GLOBALS['khm_test_transients'] = [];
        parent::tearDown();
    }

    public function test_rate_limit_service_blocks_after_threshold_and_emits_alias_telemetry(): void {
        $events = [];
        $service = new RateLimitService(
            static function ( string $metric, array $context ) use ( &$events ): void {
                $events[] = [
                    'metric' => $metric,
                    'context' => $context,
                ];
            }
        );

        $this->add_test_filter( 'khm_membership_webhook_rate_limit_max_requests', static fn() => 2 );
        $this->add_test_filter( 'khm_membership_webhook_rate_limit_window', static fn() => 60 );

        $first = $service->consumeWebhookRequest( '203.0.113.10' );
        $second = $service->consumeWebhookRequest( '203.0.113.10' );
        $third = $service->consumeWebhookRequest( '203.0.113.10' );

        $this->assertTrue( $first['allowed'] );
        $this->assertTrue( $second['allowed'] );
        $this->assertFalse( $third['allowed'] );
        $this->assertSame( 'webhook.rate_limit.exceeded', $events[0]['metric'] ?? '' );
        $this->assertSame( '203.0.113.10', $events[0]['context']['ip'] ?? '' );
        $this->assertSame( 3, $events[0]['context']['count'] ?? 0 );
    }

    public function test_invalid_signature_threshold_blocks_ip_and_emits_telemetry(): void {
        $events = [];
        $service = new RateLimitService(
            static function ( string $metric, array $context ) use ( &$events ): void {
                $events[] = [
                    'metric' => $metric,
                    'context' => $context,
                ];
            }
        );

        $this->add_test_filter( 'khm_membership_webhook_bad_signature_threshold', static fn() => 2 );
        $this->add_test_filter( 'khm_membership_webhook_bad_signature_block_seconds', static fn() => 300 );

        $first = $service->recordInvalidSignature( '198.51.100.42', [ 'code' => 'khm_invalid_signature' ] );
        $second = $service->recordInvalidSignature( '198.51.100.42', [ 'code' => 'khm_invalid_signature' ] );

        $this->assertFalse( $first['blocked'] );
        $this->assertTrue( $second['blocked'] );
        $this->assertTrue( $service->isBlocked( '198.51.100.42' ) );
        $this->assertSame( 'webhook.invalid_signature', $events[0]['metric'] ?? '' );
        $this->assertSame( 'webhook.invalid_signature', $events[1]['metric'] ?? '' );
    }

    public function test_handler_returns_429_after_rate_limit_threshold(): void {
        $this->add_test_filter( 'khm_membership_webhook_skip_signature_verification', '__return_true' );
        $this->add_test_filter( 'khm_membership_webhook_rate_limit_max_requests', static fn() => 2 );

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.55';

        $handler = new StripeWebhookHandler();
        $request = new WP_REST_Request( 'POST', '/khm/v1/webhooks/stripe' );

        $payloadA = [
            'id' => 'evt_rate_limit_1',
            'type' => 'noop.event',
            'data' => [ 'object' => [] ],
        ];
        $payloadB = [
            'id' => 'evt_rate_limit_2',
            'type' => 'noop.event',
            'data' => [ 'object' => [] ],
        ];
        $payloadC = [
            'id' => 'evt_rate_limit_3',
            'type' => 'noop.event',
            'data' => [ 'object' => [] ],
        ];

        $request->set_body( wp_json_encode( $payloadA ) );
        $responseA = $handler->handle_request( $request );
        $request->set_body( wp_json_encode( $payloadB ) );
        $responseB = $handler->handle_request( $request );
        $request->set_body( wp_json_encode( $payloadC ) );
        $responseC = $handler->handle_request( $request );

        $this->assertSame( 200, $responseA->get_status() );
        $this->assertSame( 200, $responseB->get_status() );
        $this->assertSame( 429, $responseC->get_status() );
    }

    private function add_test_filter( string $tag, $callback, int $priority = 10, int $acceptedArgs = 1 ): void {
        add_filter( $tag, $callback, $priority, $acceptedArgs );
        $this->registeredFilters[] = [
            'tag' => $tag,
            'callback' => $callback,
            'priority' => $priority,
        ];
    }
}
