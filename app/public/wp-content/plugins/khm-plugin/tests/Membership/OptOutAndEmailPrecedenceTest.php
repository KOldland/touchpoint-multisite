<?php

namespace KHM\Tests\Membership;

use KHM\Membership\SignupEndpoint;
use KHM\Membership\StripeWebhookHandler;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

require_once dirname(__DIR__) . '/helpers/stripe_signature.php';

class OptOutAndEmailPrecedenceTest extends TestCase {
    private StripeWebhookHandler $handler;
    private SignupEndpoint $signupEndpoint;

    protected function setUp(): void {
        parent::setUp();

        putenv( 'KH_STRIPE_WEBHOOK_SECRET=whsec_test_secret' );
        $GLOBALS['khm_test_options'] = [
            'khm_membership_transactional_emails_enabled' => false,
        ];
        $GLOBALS['khm_test_transients'] = [];
        $GLOBALS['khm_test_filters'] = [];
        $GLOBALS['khm_test_users_by'] = [];

        add_filter( 'khm_membership_signup_init_use_mock_session', '__return_true' );

        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_processed_webhooks" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_processed_webhook_events" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_membership_webhook_operations" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}promotion_attribution" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}user_membership" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}membership_tier" );

        $this->handler = new StripeWebhookHandler();
        $this->signupEndpoint = new SignupEndpoint();
    }

    protected function tearDown(): void {
        putenv( 'KH_STRIPE_WEBHOOK_SECRET' );
        $GLOBALS['khm_test_users_by'] = [];
        parent::tearDown();
    }

    public function test_signup_init_persists_explicit_marketing_opt_out(): void {
        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/signup-init' );
        $request->set_body( wp_json_encode([
            'schedule_id' => 'sch_optout_001',
            'sponsor_id' => null,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'optout-campaign',
            'phase_at_click' => 'attention',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174901',
            'consent' => true,
            'client_reference' => 'join',
            'plan_id' => null,
            'profile_marketing_optin' => false,
        ]) );

        $response = $this->signupEndpoint->handle_signup_init( $request );
        $this->assertEquals( 201, $response->get_status() );

        $body = $response->get_data();
        $stored = get_option( 'khm_temp_attribution_' . $body['session_id'] );

        $this->assertIsArray( $stored );
        $this->assertArrayHasKey( 'profile_marketing_optin', $stored['payload'] );
        $this->assertFalse( (bool) $stored['payload']['profile_marketing_optin'] );
    }

    public function test_webhook_prefers_session_customer_email_over_guest_email(): void {
        $customerUser = (object) [ 'ID' => 555 ];
        $guestUser = (object) [ 'ID' => 777 ];

        $GLOBALS['khm_test_users_by']['email'] = [
            'stripe-customer@example.com' => $customerUser,
            'guest@example.com' => $guestUser,
        ];

        $session = (object) [
            'customer' => (object) [ 'email' => 'stripe-customer@example.com' ],
            'customer_email' => 'fallback@example.com',
        ];
        $metadata = [
            'guest_email' => 'guest@example.com',
        ];

        $method = new \ReflectionMethod( StripeWebhookHandler::class, 'resolve_or_create_user_from_session' );
        $method->setAccessible( true );
        $resolvedUserId = (int) $method->invoke( $this->handler, $session, $metadata, 0 );

        $this->assertSame( 555, $resolvedUserId );
    }

    public function test_webhook_no_consent_redacts_attribution_fields(): void {
        global $wpdb;

        $wpdb->insert( $wpdb->prefix . 'membership_tier', [
            'slug' => 'premium',
            'name' => 'Premium',
            'price_cents' => 2999,
            'is_active' => 1,
        ] );
        $tierId = (int) $wpdb->insert_id;

        $filter = static function (): array {
            return [
                'premium' => [
                    'price_id' => 'price_premium_monthly',
                    'trial_eligible' => false,
                    'trial_days' => 0,
                ],
            ];
        };
        add_filter( 'khm_membership_tier_registry', $filter );

        $payload = [
            'id' => 'evt_optout_001',
            'type' => 'checkout.session.completed',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'cs_optout_001',
                    'mode' => 'subscription',
                    'customer' => 'cus_optout_001',
                    'subscription' => 'sub_optout_001',
                    'metadata' => [
                        'user_id' => '123',
                        'membership_level_id' => (string) $tierId,
                        'tier_slug' => 'premium',
                        'stripe_price_id' => 'price_premium_monthly',
                        'schedule_id' => '77',
                        'sponsor_id' => '11',
                        'utm_source' => 'newsletter',
                        'utm_medium' => 'email',
                        'utm_campaign' => 'spring',
                        'phase_at_click' => 'decision',
                        'consent' => '0',
                        'profile_marketing_optin' => '0',
                    ],
                ],
            ],
        ];

        $request = $this->signedRequest( $payload );
        $response = $this->handler->handle_request( $request );
        $this->assertEquals( 200, $response->get_status() );

        $this->handler->process_queued_event([
            'event_id' => (string) $payload['id'],
            'event_type' => 'checkout.session.completed',
            'data_object' => (array) $payload['data']['object'],
            'event_created' => (int) $payload['created'],
            'trace_id' => 'optout-integration',
        ]);

        $attrib = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}promotion_attribution WHERE user_id = %d", 123 ),
            ARRAY_A
        );

        $this->assertNotNull( $attrib );
        $this->assertSame( 'paid_no_consent', (string) ( $attrib['conversion_type'] ?? '' ) );
        $this->assertSame( '', (string) ( $attrib['utm_source'] ?? '' ) );
        $this->assertSame( '', (string) ( $attrib['utm_campaign'] ?? '' ) );

        remove_filter( 'khm_membership_tier_registry', $filter );
    }

    private function signedRequest( array $payload ): WP_REST_Request {
        $body = wp_json_encode( $payload );
        $header = khm_test_build_stripe_signature_header( (string) $body, 'whsec_test_secret' );

        $request = new WP_REST_Request( 'POST', '/khm/v1/webhooks/stripe' );
        $request->set_body( (string) $body );
        $request->set_header( 'stripe-signature', $header );

        return $request;
    }
}
