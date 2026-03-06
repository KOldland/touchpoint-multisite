<?php
namespace KH_SMMA\Compliance;

use KH_SMMA\Services\ComplianceValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ComplianceService {
	/** @var ComplianceRuleEngine */
	private $rule_engine;

	/** @var ComplianceValidator */
	private $ai_validator;

	public function __construct( ComplianceRuleEngine $rule_engine = null, ComplianceValidator $ai_validator = null ) {
		$this->rule_engine = $rule_engine ?? new ComplianceRuleEngine();
		$this->ai_validator = $ai_validator ?? new ComplianceValidator();
	}

	/**
	 * @param string $variant_id
	 * @param string $text
	 * @return array
	 */
	public function evaluate_variant( string $variant_id, string $text ): array {
		$det = $this->rule_engine->evaluate( $text );
		$checked_at = gmdate( 'c' );

		if ( 'FAIL' === $det['status'] ) {
			return array(
				'variant_id' => $variant_id,
				'compliance_status' => 'FAIL',
				'compliance_reason' => $det['reason'],
				'matched_rules' => $det['matched_rules'],
				'ai_review_summary' => 'Skipped due to deterministic fail',
				'checked_at' => $checked_at,
			);
		}

		$ai = $this->ai_validator->validate( $text, array( 'channel' => 'linkedin' ) );
		$ai_passed = (bool) ( $ai['passed'] ?? true );
		$status = $ai_passed ? 'OK' : 'WARN';
		$reason = $ai_passed ? '' : (string) ( $ai['message'] ?? 'Potential claim language detected' );

		return array(
			'variant_id' => $variant_id,
			'compliance_status' => $status,
			'compliance_reason' => $reason,
			'matched_rules' => array(),
			'ai_review_summary' => (string) ( $ai['notes'] ?? '' ),
			'checked_at' => $checked_at,
		);
	}
}
