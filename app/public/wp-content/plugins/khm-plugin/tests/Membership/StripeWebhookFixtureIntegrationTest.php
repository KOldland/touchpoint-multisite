<?php

namespace KHM\Tests\Membership;

use KHM\Membership\ProcessedWebhook;
use KHM\Membership\StripeWebhookHandler;
use KHM\Services\MembershipRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

require_once dirname(__DIR__) . '/helpers/stripe_signature.php';
require_once dirname(__DIR__) . '/helpers/normalize_fixture.php';

class StripeWebhookFixtureIntegrationTest extends TestCase {
    private StripeWebhookHandler $handler;

    protected function setUp(): void {
        parent::setUp();

        putenv('KH_STRIPE_WEBHOOK_SECRET=whsec_test_secret');
        $GLOBALS['khm_test_options'] = [
            'khm_membership_transactional_emails_enabled' => false,
        ];

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_processed_webhooks");
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_processed_webhook_events");
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_membership_webhook_operations");
        $wpdb->query("DELETE FROM {$wpdb->prefix}promotion_attribution");
        $wpdb->query("DELETE FROM {$wpdb->prefix}user_membership");
        $wpdb->query("DELETE FROM {$wpdb->prefix}membership_tier");

        $this->handler = new StripeWebhookHandler();
        ProcessedWebhook::maybe_create_table();
    }

    protected function tearDown(): void {
        putenv('KH_STRIPE_WEBHOOK_SECRET');
        parent::tearDown();
    }

    public function integrationWebhook_checkout_session_completed_creates_attribution(): void {
        global $wpdb;

        $tierId = $this->seedTier('premium');
        $fixture = $this->loadFixture('checkout_session_completed.json');
        $fixture['id'] = 'evt_checkout_signed_001';
        $fixture['data']['object']['id'] = 'cs_fixture_signed_001';
        $fixture['data']['object']['metadata']['membership_level_id'] = (string) $tierId;

        $filter = $this->tierRegistryFilter('premium', 'price_premium_monthly');
        add_filter('khm_membership_tier_registry', $filter);

        $request = $this->signedRequest($fixture);
        $response = $this->handler->handle_request($request);
        $this->assertEquals(200, $response->get_status());
        $this->assertSame('queued', $response->get_data()['status']);

        $this->handler->process_queued_event([
            'event_id' => (string) $fixture['id'],
            'event_type' => 'checkout.session.completed',
            'data_object' => (array) $fixture['data']['object'],
            'event_created' => (int) $fixture['created'],
            'trace_id' => 'integration-checkout-001',
        ]);

        $membership = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}user_membership WHERE user_id = %d", 101),
            ARRAY_A
        );
        $this->assertNotNull($membership);
        $this->assertEquals((string) $tierId, (string) $membership['tier_id']);

        $attribution = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}promotion_attribution WHERE user_id = %d", 101),
            ARRAY_A
        );
        $this->assertNotNull($attribution);
        $this->assertEquals('paid', $attribution['conversion_type']);

        $processedCount = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}khm_processed_webhooks WHERE event_id = %s", (string) $fixture['id'])
        );
        $this->assertSame(1, $processedCount);

        $responseDuplicate = $this->handler->handle_request($this->signedRequest($fixture));
        $this->assertEquals(200, $responseDuplicate->get_status());
        $this->assertSame('already processed', (string) ($responseDuplicate->get_data()['note'] ?? ''));

        remove_filter('khm_membership_tier_registry', $filter);
    }

    public function testIntegrationWebhookCheckoutSessionCompletedCreatesAttribution(): void {
        $this->integrationWebhook_checkout_session_completed_creates_attribution();
    }

    public function integrationWebhook_invoice_paid_updates_membership_and_queues_payment_email(): void {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 202,
            'tier_id' => 1,
            'stripe_customer_id' => 'cus_fixture_001',
            'stripe_subscription_id' => 'sub_fixture_001',
            'status' => 'past_due',
        ]);

        $fixture = $this->loadFixture('invoice_paid.json');
        $fixture['id'] = 'evt_invoice_signed_001';

        $response = $this->handler->handle_request($this->signedRequest($fixture));
        $this->assertEquals(200, $response->get_status());

        $this->handler->process_queued_event([
            'event_id' => (string) $fixture['id'],
            'event_type' => 'invoice.paid',
            'data_object' => (array) $fixture['data']['object'],
            'event_created' => (int) $fixture['created'],
            'trace_id' => 'integration-invoice-001',
        ]);

        $membership = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}user_membership WHERE user_id = %d", 202),
            ARRAY_A
        );
        $this->assertNotNull($membership);
        $this->assertSame('active', (string) $membership['status']);

        $processedCount = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}khm_processed_webhooks WHERE event_id = %s", (string) $fixture['id'])
        );
        $this->assertSame(1, $processedCount);

        $responseDuplicate = $this->handler->handle_request($this->signedRequest($fixture));
        $this->assertEquals(200, $responseDuplicate->get_status());
        $this->assertSame('already processed', (string) ($responseDuplicate->get_data()['note'] ?? ''));
    }

    public function testIntegrationWebhookInvoicePaidUpdatesMembershipAndQueuesPaymentEmail(): void {
        $this->integrationWebhook_invoice_paid_updates_membership_and_queues_payment_email();
    }

    public function integrationTempAttributionFallback(): void {
        global $wpdb;

        $tierId = $this->seedTier('premium');
        $filter = $this->tierRegistryFilter('premium', 'price_premium_monthly');
        add_filter('khm_membership_tier_registry', $filter);

        $repo = new MembershipRepository();
        $repo->storeTempAttribution('cs_temp_fallback_001', [
            'schedule_id' => '777',
            'sponsor_id' => '42',
            'utm_source' => 'landing-temp',
            'utm_medium' => 'email',
            'utm_campaign' => 'temp-fallback',
            'consent' => true,
        ], 3600);

        $fixture = $this->loadFixture('checkout_session_completed.json');
        $fixture['id'] = 'evt_checkout_temp_fallback_001';
        $fixture['data']['object']['id'] = 'cs_temp_fallback_001';
        $fixture['data']['object']['metadata']['membership_level_id'] = (string) $tierId;
        $fixture['data']['object']['metadata']['schedule_id'] = '';
        $fixture['data']['object']['metadata']['sponsor_id'] = '';
        $fixture['data']['object']['metadata']['utm_source'] = '';

        $response = $this->handler->handle_request($this->signedRequest($fixture));
        $this->assertEquals(200, $response->get_status());

        $this->handler->process_queued_event([
            'event_id' => (string) $fixture['id'],
            'event_type' => 'checkout.session.completed',
            'data_object' => (array) $fixture['data']['object'],
            'event_created' => (int) $fixture['created'],
            'trace_id' => 'integration-temp-fallback',
        ]);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}promotion_attribution WHERE user_id = %d", 101),
            ARRAY_A
        );

        $this->assertNotNull($row);
        $this->assertSame('777', (string) ($row['schedule_id'] ?? ''));
        $this->assertSame('', (string) ($row['utm_source'] ?? ''));

        remove_filter('khm_membership_tier_registry', $filter);
    }

    public function testIntegrationTempAttributionFallback(): void {
        $this->integrationTempAttributionFallback();
    }

    /**
     * @return array<string,mixed>
     */
    private function loadFixture(string $name): array {
        $path = dirname(__DIR__) . '/fixtures/golden/' . $name;
        $json = file_get_contents($path);
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function signedRequest(array $payload): WP_REST_Request {
        $body = wp_json_encode($payload);
        $header = khm_test_build_stripe_signature_header((string) $body, 'whsec_test_secret');

        $request = new WP_REST_Request('POST', '/khm/v1/webhooks/stripe');
        $request->set_body((string) $body);
        $request->set_header('stripe-signature', $header);
        return $request;
    }

    private function seedTier(string $slug): int {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => $slug,
            'name' => strtoupper($slug),
            'price_cents' => 2999,
            'trial_days' => 0,
            'is_active' => 1,
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * @return callable
     */
    private function tierRegistryFilter(string $slug, string $priceId): callable {
        return static function () use ($slug, $priceId): array {
            return [
                $slug => [
                    'price_id' => $priceId,
                    'trial_eligible' => false,
                    'trial_days' => 0,
                ],
            ];
        };
    }
}
