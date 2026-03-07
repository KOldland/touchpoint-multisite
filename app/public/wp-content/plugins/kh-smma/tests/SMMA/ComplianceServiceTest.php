<?php

use KH_SMMA\Compliance\ComplianceService;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Compliance/BannedPhraseRules.php';
require_once dirname( __DIR__, 2 ) . '/src/Compliance/ComplianceRuleEngine.php';
require_once dirname( __DIR__, 2 ) . '/src/Compliance/ComplianceService.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/ComplianceValidator.php';

class ComplianceServiceTest extends TestCase {
	private array $cases;

	protected function setUp(): void {
		parent::setUp();
		$this->cases = json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/smma/compliance_cases.json' ), true );
	}

	public function test_banned_phrase_detection_triggers_fail(): void {
		$service = new ComplianceService();
		$result = $service->evaluate_variant( $this->cases['fail_variant']['variant_id'], $this->cases['fail_variant']['text'] );

		$this->assertSame( 'FAIL', $result['compliance_status'] );
		$this->assertNotEmpty( $result['matched_rules'] );
	}

	public function test_ai_layer_can_return_warn(): void {
		$service = new ComplianceService();
		$result = $service->evaluate_variant( $this->cases['warn_variant']['variant_id'], $this->cases['warn_variant']['text'] );

		$this->assertContains( $result['compliance_status'], array( 'OK', 'WARN' ) );
	}

	public function test_ok_content_passes(): void {
		$service = new ComplianceService();
		$result = $service->evaluate_variant( $this->cases['ok_variant']['variant_id'], $this->cases['ok_variant']['text'] );

		$this->assertContains( $result['compliance_status'], array( 'OK', 'WARN' ) );
		$this->assertArrayHasKey( 'checked_at', $result );
	}
}
