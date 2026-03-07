<?php

namespace KHM\Tests\Membership;

use KHM\Membership\SignupEndpoint;
use KHM\Rest\CheckoutController;
use KHM\Services\DiscountCodeService;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class PromoValidationTest extends TestCase {
    /** @var array<int,array{tag:string,callback:mixed,priority:int}> */
    private array $registeredFilters = [];

    protected function tearDown(): void {
        if ( function_exists( 'remove_filter' ) ) {
            foreach ( $this->registeredFilters as $filter ) {
                remove_filter( $filter['tag'], $filter['callback'], $filter['priority'] );
            }
        }
        $this->registeredFilters = [];
        putenv( 'KH_STRIPE_SECRET_KEY' );
        parent::tearDown();
    }

    public function test_signup_rejects_invalid_promo_before_checkout_creation(): void {
        global $wpdb;

        $wpdb->insert( $wpdb->prefix . 'membership_tier', [
            'slug' => 'promo-paid',
            'name' => 'Promo Paid',
            'price_cents' => 5000,
            'trial_days' => 0,
            'is_active' => 1,
        ] );
        $planId = (int) $wpdb->insert_id;

        $override = static function ( $value, $promoCode ) {
            if ( 'INVALID50' === $promoCode ) {
                return new \WP_Error( 'invalid_promo', 'Invalid promotion code.' );
            }
            return $value;
        };
        $this->add_test_filter( 'khm_membership_signup_init_validate_promo_override', $override, 10, 2 );

        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/signup' );
        $request->set_body( wp_json_encode( [
            'email' => 'promo.invalid.signup@example.com',
            'plan_id' => $planId,
            'promo_code' => 'INVALID50',
        ] ) );

        $endpoint = new SignupEndpoint();
        $response = $endpoint->handle_request( $request );

        $this->assertSame( 400, $response->get_status() );
        $body = $response->get_data();
        $this->assertSame( 'MBR_ERR_INVALID_PROMO', $body['code'] ?? '' );
        $this->assertSame( 'Invalid promotion code.', $body['message'] ?? '' );
        $this->assertFalse( (bool) ( $body['retryable'] ?? true ) );
    }

    public function test_checkout_controller_rejects_invalid_promo_in_resolver(): void {
        $controller = new CheckoutController();
        $discounts = $this->createMock( DiscountCodeService::class );
        $discounts->expects( $this->once() )
            ->method( 'validate_code' )
            ->with( 'INVALID50', 3, 0 )
            ->willReturn( [
                'valid' => false,
                'message' => 'Invalid discount code.',
            ] );
        $this->set_private_property( $controller, 'discounts', $discounts );

        $request = new WP_REST_Request( 'POST', '/khm/v1/checkout/subscription' );
        $request->set_param( 'promo_code', 'INVALID50' );

        $method = new \ReflectionMethod( CheckoutController::class, 'resolve_membership_promo' );
        $method->setAccessible( true );
        $result = $method->invoke( $controller, 3, 0, $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_signup_init_invalid_promo_returns_structured_error_and_no_session(): void {
        $override = static function ( $value, $promoCode ) {
            if ( 'INVALID50' === $promoCode ) {
                return new \WP_Error( 'invalid_promo', 'Invalid promotion code.' );
            }
            return $value;
        };
        add_filter( 'khm_membership_signup_init_validate_promo_override', $override, 10, 2 );

        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/signup-init' );
        $request->set_body( wp_json_encode( [
            'schedule_id' => 'sch_125',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174555',
            'promo_code' => 'INVALID50',
            'consent' => true,
        ] ) );

        $endpoint = new SignupEndpoint();
        $response = $endpoint->handle_signup_init( $request );

        $this->assertSame( 400, $response->get_status() );
        $body = $response->get_data();
        $this->assertSame( 'MBR_ERR_INVALID_PROMO', $body['code'] ?? '' );
        $this->assertSame( 'Invalid promotion code.', $body['message'] ?? '' );
        $this->assertFalse( (bool) ( $body['retryable'] ?? true ) );
        $this->assertArrayNotHasKey( 'session_id', $body );
        $this->assertArrayNotHasKey( 'checkout_url', $body );
    }

    public function test_signup_init_valid_promo_creates_checkout_session(): void {
        $override = static function ( $value, $promoCode ) {
            if ( 'WELCOME20' === $promoCode ) {
                return [
                    'valid' => true,
                    'message' => 'Discount code applied successfully.',
                    'code' => (object) [ 'id' => 99, 'code' => 'WELCOME20' ],
                ];
            }
            return $value;
        };
        $this->add_test_filter( 'khm_membership_signup_init_validate_promo_override', $override, 10, 2 );
        $this->add_test_filter( 'khm_membership_signup_init_use_mock_session', '__return_true' );

        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/signup-init' );
        $request->set_body( wp_json_encode( [
            'schedule_id' => 'sch_125',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174556',
            'promo_code' => 'WELCOME20',
            'consent' => true,
        ] ) );

        $endpoint = new SignupEndpoint();
        $response = $endpoint->handle_signup_init( $request );

        $this->assertSame( 201, $response->get_status() );
        $body = $response->get_data();
        $this->assertNotEmpty( $body['session_id'] ?? '' );
        $this->assertNotEmpty( $body['checkout_url'] ?? '' );
    }

    private function set_private_property( object $object, string $name, $value ): void {
        $reflection = new \ReflectionClass( $object );
        while ( $reflection ) {
            if ( $reflection->hasProperty( $name ) ) {
                $property = $reflection->getProperty( $name );
                $property->setAccessible( true );
                $property->setValue( $object, $value );
                return;
            }
            $reflection = $reflection->getParentClass();
        }

        $this->fail( sprintf( 'Expected private property "%s" not found.', $name ) );
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
