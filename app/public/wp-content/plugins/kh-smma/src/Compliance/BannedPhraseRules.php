<?php
namespace KH_SMMA\Compliance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BannedPhraseRules {
	/**
	 * @return array<int,string>
	 */
	public function all(): array {
		return array(
			'guaranteed results',
			'risk-free returns',
			'unlimited growth',
			'guaranteed leads',
		);
	}
}
