<?php

namespace {
    if ( ! function_exists( 'khm_get_membership_level' ) ) {
        function khm_get_membership_level( int $levelId ) {
            return $GLOBALS['khm_test_membership_levels'][ $levelId ] ?? null;
        }
    }
}

namespace KHM\Tests\Membership {

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
        unset( $GLOBALS['khm_test_current_user_id'], $GLOBALS['khm_test_users_by'] );
        $GLOBALS['khm_test_membership_levels'] = [];
        global $wpdb;
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
        $endpoint = new class extends SignupEndpoint {
            public int $createCallCount = 0;

            protected function create_stripe_checkout_session( array $params, array $options = [] ) {
                $this->createCallCount++;
                return (object) [
                    'id' => 'cs_should_not_exist',
                    'url' => 'https://checkout.stripe.test/should-not-exist',
                ];
            }
        };

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

        $response = $endpoint->handle_signup_init( $request );

        $this->assertSame( 400, $response->get_status() );
        $body = $response->get_data();
        $this->assertSame( 'MBR_ERR_INVALID_PROMO', $body['code'] ?? '' );
        $this->assertSame( 'Invalid promotion code.', $body['message'] ?? '' );
        $this->assertFalse( (bool) ( $body['retryable'] ?? true ) );
        $this->assertArrayNotHasKey( 'session_id', $body );
        $this->assertArrayNotHasKey( 'checkout_url', $body );
        $this->assertSame( 0, $endpoint->createCallCount );
    }

    public function test_signup_init_valid_promo_creates_checkout_session(): void {
        putenv( 'KH_STRIPE_SECRET_KEY=sk_test_signup_checkout' );

        $endpoint = new class extends SignupEndpoint {
            public array $capturedParams = [];
            public array $capturedOptions = [];

            protected function create_stripe_checkout_session( array $params, array $options = [] ) {
                $this->capturedParams = $params;
                $this->capturedOptions = $options;

                return (object) [
                    'id' => 'cs_signup_valid_001',
                    'url' => 'https://checkout.stripe.test/cs_signup_valid_001',
                ];
            }
        };

        $override = static function ( $value, $promoCode ) {
            if ( 'WELCOME20' === $promoCode ) {
                return [
                    'valid' => true,
                    'message' => 'Discount code applied successfully.',
                    'code' => (object) [
                        'id' => 99,
                        'code' => 'WELCOME20',
                        'stripe_promotion_code' => 'promo_validated_20',
                    ],
                ];
            }
            return $value;
        };
        $this->add_test_filter( 'khm_membership_signup_init_validate_promo_override', $override, 10, 2 );
        $this->add_test_filter( 'khm_membership_signup_init_use_mock_session', '__return_false' );
        $this->add_test_filter( 'khm_membership_signup_init_price_id', static fn() => 'price_signup_phase2_001' );

        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/signup-init' );
        $request->set_body( wp_json_encode( [
            'schedule_id' => 'sch_125',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring-launch',
            'utm_term' => 'phase2',
            'utm_content' => 'hero',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174556',
            'promo_code' => 'WELCOME20',
            'consent' => true,
            'profile_marketing_optin' => false,
        ] ) );

        $response = $endpoint->handle_signup_init( $request );

        $this->assertSame( 201, $response->get_status() );
        $body = $response->get_data();
        $this->assertSame( 'cs_signup_valid_001', $body['session_id'] ?? '' );
        $this->assertSame( 'https://checkout.stripe.test/cs_signup_valid_001', $body['checkout_url'] ?? '' );
        $this->assertSame( 'promo_validated_20', $endpoint->capturedParams['discounts'][0]['promotion_code'] ?? '' );
        $this->assertSame( '123e4567-e89b-12d3-a456-426614174556', $endpoint->capturedOptions['idempotency_key'] ?? '' );
        $this->assertSame( 'price_signup_phase2_001', $endpoint->capturedParams['line_items'][0]['price'] ?? '' );
        $this->assertSame( 'sch_125', $endpoint->capturedParams['metadata']['schedule_id'] ?? '' );
        $this->assertSame( '', $endpoint->capturedParams['metadata']['sponsor_id'] ?? '' );
        $this->assertSame( 'newsletter', $endpoint->capturedParams['metadata']['utm_source'] ?? '' );
        $this->assertSame( 'email', $endpoint->capturedParams['metadata']['utm_medium'] ?? '' );
        $this->assertSame( 'spring-launch', $endpoint->capturedParams['metadata']['utm_campaign'] ?? '' );
        $this->assertSame( 'phase2', $endpoint->capturedParams['metadata']['utm_term'] ?? '' );
        $this->assertSame( 'hero', $endpoint->capturedParams['metadata']['utm_content'] ?? '' );
        $this->assertSame( '0', $endpoint->capturedParams['metadata']['profile_marketing_optin'] ?? '' );
        $this->assertSame( '1', $endpoint->capturedParams['metadata']['consent'] ?? '' );
        $this->assertArrayHasKey( 'wp_user_id', $endpoint->capturedParams['metadata'] ?? [] );
    }

    public function test_checkout_controller_validated_promo_attaches_discount_and_metadata(): void {
        putenv( 'KH_STRIPE_SECRET_KEY=sk_test_checkout_controller' );
        $GLOBALS['khm_test_current_user_id'] = 321;
        $GLOBALS['khm_test_users_by']['id']['321'] = (object) [
            'ID' => 321,
            'user_email' => 'member321@example.com',
        ];

        $levelId = 41;
        $GLOBALS['khm_test_membership_levels'][ $levelId ] = (object) [
            'id' => $levelId,
            'name' => 'Phase 2 Pro',
        ];
        $this->add_test_filter(
            'khm_stripe_membership_price_map',
            static fn( $value, $requestedLevelId ) => $requestedLevelId === $levelId ? [ $levelId => 'price_rest_phase2_001' ] : $value,
            10,
            2
        );

        $controller = new class extends CheckoutController {
            public array $capturedParams = [];

            protected function create_stripe_checkout_session( array $params ) {
                $this->capturedParams = $params;
                return (object) [
                    'id' => 'cs_rest_valid_001',
                    'url' => 'https://checkout.stripe.test/cs_rest_valid_001',
                ];
            }
        };

        $discounts = $this->createMock( DiscountCodeService::class );
        $discounts->expects( $this->once() )
            ->method( 'validate_code' )
            ->with( 'REST20', $levelId, 321 )
            ->willReturn( [
                'valid' => true,
                'code' => (object) [
                    'id' => 88,
                    'type' => 'percentage',
                    'value' => 20,
                    'stripe_promotion_code' => 'promo_rest_valid_20',
                ],
            ] );
        $this->set_private_property( $controller, 'discounts', $discounts );

        $request = new WP_REST_Request( 'POST', '/khm/v1/checkout/subscription' );
        $request->set_param( 'membership_level_id', $levelId );
        $request->set_param( 'schedule_id', 'sch_rest_001' );
        $request->set_param( 'sponsor_id', 'sp_rest_09' );
        $request->set_param( 'utm_source', 'linkedin' );
        $request->set_param( 'utm_medium', 'social' );
        $request->set_param( 'utm_campaign', 'q2-demo' );
        $request->set_param( 'utm_term', 'exec' );
        $request->set_param( 'utm_content', 'cta' );
        $request->set_param( 'idempotency_key', 'idem-rest-001' );
        $request->set_param( 'profile_marketing_optin', false );
        $request->set_param( 'consent', true );
        $request->set_param( 'promo_code', 'REST20' );

        $response = $controller->create_subscription_checkout( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'https://checkout.stripe.test/cs_rest_valid_001', $response->get_data()['url'] ?? '' );
        $this->assertSame( 'promo_rest_valid_20', $controller->capturedParams['discounts'][0]['promotion_code'] ?? '' );
        $this->assertSame( '321', $controller->capturedParams['metadata']['wp_user_id'] ?? '' );
        $this->assertSame( 'sch_rest_001', $controller->capturedParams['metadata']['schedule_id'] ?? '' );
        $this->assertSame( 'sp_rest_09', $controller->capturedParams['metadata']['sponsor_id'] ?? '' );
        $this->assertSame( 'linkedin', $controller->capturedParams['metadata']['utm_source'] ?? '' );
        $this->assertSame( 'social', $controller->capturedParams['metadata']['utm_medium'] ?? '' );
        $this->assertSame( 'q2-demo', $controller->capturedParams['metadata']['utm_campaign'] ?? '' );
        $this->assertSame( 'exec', $controller->capturedParams['metadata']['utm_term'] ?? '' );
        $this->assertSame( 'cta', $controller->capturedParams['metadata']['utm_content'] ?? '' );
        $this->assertSame( 'idem-rest-001', $controller->capturedParams['metadata']['idempotency_key'] ?? '' );
        $this->assertSame( '0', $controller->capturedParams['metadata']['profile_marketing_optin'] ?? '' );
        $this->assertSame( '1', $controller->capturedParams['metadata']['consent'] ?? '' );
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
}
