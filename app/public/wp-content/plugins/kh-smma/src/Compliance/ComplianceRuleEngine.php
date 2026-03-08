<?php
namespace KH_SMMA\Compliance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ComplianceRuleEngine {
	/** @var BannedPhraseRules */
	private $rules;

	public function __construct( BannedPhraseRules $rules = null ) {
		$this->rules = $rules ?? new BannedPhraseRules();
	}

	/**
	 * @param string $text
	 * @return array{status:string,matched_rules:array,reason:string}
	 */
	public function evaluate( string $text ): array {
		$matched = array();
		$lower = strtolower( $text );
		foreach ( $this->rules->all() as $phrase ) {
			if ( strpos( $lower, strtolower( $phrase ) ) !== false ) {
				$matched[] = $phrase;
			}
		}

		if ( ! empty( $matched ) ) {
			return array(
				'status' => 'FAIL',
				'matched_rules' => $matched,
				'reason' => 'Variant contains banned phrases',
			);
		}

		return array(
			'status' => 'OK',
			'matched_rules' => array(),
			'reason' => '',
		);
	}
}
