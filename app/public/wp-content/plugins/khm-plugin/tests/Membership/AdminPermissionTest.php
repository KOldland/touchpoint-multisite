<?php

namespace KHM\Membership\Admin {
    function error_log($message, $message_type = 0, $destination = null, $extra_headers = null) {
        if (!isset($GLOBALS['khm_test_error_logs']) || !is_array($GLOBALS['khm_test_error_logs'])) {
            $GLOBALS['khm_test_error_logs'] = [];
        }
        $GLOBALS['khm_test_error_logs'][] = (string) $message;
        return true;
    }
}

namespace KHM\Admin {
    function error_log($message, $message_type = 0, $destination = null, $extra_headers = null) {
        if (!isset($GLOBALS['khm_test_error_logs']) || !is_array($GLOBALS['khm_test_error_logs'])) {
            $GLOBALS['khm_test_error_logs'] = [];
        }
        $GLOBALS['khm_test_error_logs'][] = (string) $message;
        return true;
    }
}

namespace KHM\Tests\Membership {

use KHM\Admin\MembersPage;
use KHM\Membership\Admin\ReportsPage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AdminPermissionTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['khm_test_current_user_id'] = 22;
        $GLOBALS['khm_test_current_user_caps'] = [];
        $GLOBALS['khm_test_error_logs'] = [];
    }

    public function test_reports_export_denies_non_admin_and_logs_unauthorized_access(): void {
        $page = new ReportsPage();

        try {
            $page->handle_export();
            $this->fail('Expected wp_die to be triggered for non-admin export.');
        } catch (RuntimeException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertStringContainsString('do not have permission', strtolower($e->getMessage()));
        }

        $this->assertUnauthorizedLogContains('khm-membership-reports-export');

    }

    public function test_member_anonymize_denies_non_admin_and_logs_unauthorized_access(): void {
        $reflection = new \ReflectionClass(MembersPage::class);
        $page = $reflection->newInstanceWithoutConstructor();

        try {
            $page->handle_anonymize_attribution_request();
            $this->fail('Expected wp_die to be triggered for non-admin anonymize.');
        } catch (RuntimeException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertStringContainsString('do not have permission', strtolower($e->getMessage()));
        }

        $this->assertUnauthorizedLogContains('khm-members-anonymize');
    }

    private function assertUnauthorizedLogContains(string $resource): void {
        $logs = is_array($GLOBALS['khm_test_error_logs'] ?? null) ? $GLOBALS['khm_test_error_logs'] : [];
        $this->assertNotEmpty($logs);

        $matched = false;
        foreach ($logs as $line) {
            if (str_contains((string) $line, 'unauthorized_admin_access') && str_contains((string) $line, $resource)) {
                $matched = true;
                break;
            }
        }

        $this->assertTrue($matched, 'Expected unauthorized_admin_access log for resource: ' . $resource);

    }
}
}
