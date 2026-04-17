<?php

namespace KHM\Tests\Membership;

use KHM\Membership\DsarController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class DsarExportPrivacyTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['khm_test_options'] = [];
        $GLOBALS['khm_test_current_user_caps'] = [];
        $GLOBALS['khm_test_current_user_id'] = 0;
    }

    protected function tearDown(): void {
        $GLOBALS['khm_test_current_user_id'] = 0;
        $GLOBALS['khm_test_current_user_caps'] = [];
        parent::tearDown();
    }

    public function testDsarExportRedactsWhenConsentFalse(): void {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive extension not available in test runtime.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'promotion_attribution';

        $wpdb->insert($table, [
            'id' => 9401,
            'user_id' => 777,
            'user_email' => '',
            'schedule_id' => 12,
            'sponsor_id' => 19,
            'utm_source' => '',
            'conversion_type' => 'paid_no_consent',
            'consent' => 0,
            'reference' => 'cs_privacy_001',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $controller = new DsarController();

        $GLOBALS['khm_test_current_user_id'] = 777;
        $request = new WP_REST_Request('POST', '/kh-membership/v1/dsar/request');
        $request->set_body(wp_json_encode([
            'type' => 'export',
            'ticket_id' => 'T-DSAR-EXPORT-1',
        ]));
        $queued = $controller->request($request);

        $this->assertEquals(202, $queued->get_status());
        $requestId = (string) ($queued->get_data()['request_id'] ?? '');
        $this->assertNotSame('', $requestId);

        $GLOBALS['khm_test_current_user_id'] = 1;
        $GLOBALS['khm_test_current_user_caps'] = [ 'manage_options' => true ];

        $approve = new WP_REST_Request('POST', '/kh-membership/v1/dsar/approve');
        $approve->set_body(wp_json_encode([
            'request_id' => $requestId,
            'ticket_id' => 'T-DSAR-EXPORT-1',
        ]));
        $approved = $controller->approve($approve);

        $this->assertEquals(200, $approved->get_status());
        $file = (string) ($approved->get_data()['file'] ?? '');
        $this->assertNotSame('', $file);
        $this->assertFileExists($file);

        $zip = new \ZipArchive();
        $opened = $zip->open($file);
        $this->assertTrue($opened === true);
        $json = $zip->getFromName('attribution.json');
        $zip->close();

        $this->assertIsString($json);
        $payload = json_decode((string) $json, true);
        $this->assertIsArray($payload);

        $rows = $payload['rows'] ?? [];
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);

        $row = $rows[0];
        $this->assertSame('', (string) ($row['user_email'] ?? ''));
        $this->assertSame('', (string) ($row['utm_source'] ?? ''));
    }
}
