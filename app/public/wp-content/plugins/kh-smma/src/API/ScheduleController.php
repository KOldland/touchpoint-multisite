<?php
namespace KH_SMMA\API;

use KH_SMMA\Services\Card1StateStore;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScheduleController {
	/** @var Card1StateStore */
	private $store;

	public function __construct( Card1StateStore $store ) {
		$this->store = $store;
	}

	public function enforce_compliance_gate( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$variant_id = sanitize_text_field( (string) ( $payload['variant_id'] ?? '' ) );
		$variant = $this->store->get_variant( $variant_id );

		if ( empty( $variant ) ) {
			return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'This variant is no longer available. Please regenerate variants and try again.', array( 'status' => 400 ) );
		}

		$status = strtoupper( (string) ( $variant['linkedIn']['compliance_status'] ?? $variant['linkedIn']['compliance']['status'] ?? 'OK' ) );
		$reason = (string) ( $variant['linkedIn']['compliance_reason'] ?? '' );
		$matched_rules = (array) ( $variant['linkedIn']['matched_rules'] ?? array() );

		if ( 'FAIL' === $status ) {
			return new WP_Error(
				'COMPLIANCE_FAIL',
				'Variant cannot be scheduled because compliance_status=FAIL. Remove banned language before scheduling.',
				array(
					'status' => 409,
					'error' => 'COMPLIANCE_FAIL',
					'message' => 'Variant contains banned or restricted content and cannot be scheduled.',
					'matched_rules' => $matched_rules,
				)
			);
		}

		if ( 'WARN' === $status ) {
			return array(
				'status' => 'pending_approval',
				'message' => 'Variant requires sponsor/admin approval before scheduling.',
				'approval_required' => true,
				'approval_status' => 'pending',
				'compliance_status' => 'WARN',
				'compliance_reason' => $reason,
				'matched_rules' => $matched_rules,
			);
		}

		return array(
			'status' => 'ok',
			'message' => 'Scheduling allowed',
			'approval_required' => false,
			'approval_status' => 'approved',
			'compliance_status' => 'OK',
			'compliance_reason' => '',
			'matched_rules' => array(),
		);
	}
}
