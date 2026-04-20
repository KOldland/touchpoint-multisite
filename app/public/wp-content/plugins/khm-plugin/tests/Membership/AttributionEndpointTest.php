<?php

namespace KHM\Tests\Membership;

use PHPUnit\Framework\TestCase;
use KHM\Membership\AttributionEndpoint;
use WP_REST_Request;
use WP_REST_Response;

class AttributionEndpointTest extends TestCase {
    private $endpoint;
    private $table_name;

    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'promotion_attribution';
        $this->endpoint = new AttributionEndpoint();

        // Clean up test data
        $wpdb->query("DELETE FROM {$this->table_name}");
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->table_name}");
        parent::tearDown();
    }

    public function test_rejects_invalid_conversion_type() {
        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode([
            'conversion_type' => 'invalid_type'
        ]));

        $response = $this->endpoint->handle_request($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('invalid conversion_type', $data['error']);
    }

    public function test_creates_attribution_record() {
        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode([
            'conversion_type' => 'signup',
            'consent' => true,
            'user_id' => 123,
            'user_email' => 'test@example.com',
            'schedule_id' => 99,
            'sponsor_id' => 12,
            'utm_source' => 'newsletter',
            'plan_id' => 1
        ]));

        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertGreaterThan(0, $data['id']);

        // Verify record in database
        global $wpdb;
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $data['id']
        ), ARRAY_A);

        $this->assertNotNull($record);
        $this->assertEquals('signup', $record['conversion_type']);
        $this->assertEquals(123, $record['user_id']);
        $this->assertEquals(99, $record['schedule_id']);
    }

    public function test_idempotency_prevents_duplicates() {
        $payload = [
            'conversion_type' => 'signup',
            'consent' => true,
            'user_id' => 123,
            'schedule_id' => 99
        ];

        // First request
        $request1 = new WP_REST_Request('POST');
        $request1->set_body(json_encode($payload));
        $response1 = $this->endpoint->handle_request($request1);
        $data1 = $response1->get_data();
        $first_id = $data1['id'];

        // Second identical request within 10 minutes
        $request2 = new WP_REST_Request('POST');
        $request2->set_body(json_encode($payload));
        $response2 = $this->endpoint->handle_request($request2);
        $data2 = $response2->get_data();

        // Should return same ID (idempotent)
        $this->assertEquals($first_id, $data2['id']);

        // Verify only one record exists
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $this->assertEquals(1, $count);
    }

    public function test_idempotency_user_email_alternative() {
        $payload = [
            'conversion_type' => 'signup',
            'consent' => true,
            'user_email' => 'test@example.com',
            'schedule_id' => 99
        ];

        // First request
        $request1 = new WP_REST_Request('POST');
        $request1->set_body(json_encode($payload));
        $response1 = $this->endpoint->handle_request($request1);
        $data1 = $response1->get_data();
        $first_id = $data1['id'];

        // Second identical request
        $request2 = new WP_REST_Request('POST');
        $request2->set_body(json_encode($payload));
        $response2 = $this->endpoint->handle_request($request2);
        $data2 = $response2->get_data();

        // Should return same ID
        $this->assertEquals($first_id, $data2['id']);
    }

    public function test_different_conversion_types_create_separate_records() {
        $base_payload = [
            'user_id' => 123,
            'schedule_id' => 99
        ];

        // Create signup attribution
        $request1 = new WP_REST_Request('POST');
        $request1->set_body(json_encode(array_merge($base_payload, ['conversion_type' => 'signup'])));
        $response1 = $this->endpoint->handle_request($request1);
        $id1 = $response1->get_data()['id'];

        // Create trial attribution (different type)
        $request2 = new WP_REST_Request('POST');
        $request2->set_body(json_encode(array_merge($base_payload, ['conversion_type' => 'trial'])));
        $response2 = $this->endpoint->handle_request($request2);
        $id2 = $response2->get_data()['id'];

        // Should create two separate records
        $this->assertNotEquals($id1, $id2);

        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $this->assertEquals(2, $count);
    }

    public function test_null_schedule_id_handled_correctly() {
        $payload = [
            'conversion_type' => 'signup',
            'user_id' => 123
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($payload));
        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function test_accepts_connect_conversion_types() {
        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode([
            'conversion_type' => 'connect_mql',
            'consent' => true,
            'user_id' => 321,
            'schedule_id' => 55,
            'reference' => 'connect-ref-1',
            'reference_metadata' => [
                'provider_id' => 9,
                'title_context' => 'the-engineer',
            ],
        ]));

        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);

        global $wpdb;
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $response->get_data()['id']
        ), ARRAY_A);

        $this->assertNotNull($record);
        $this->assertEquals('connect_mql', $record['conversion_type']);
    }
}
