<?php
declare( strict_types=1 );

namespace KH_SMMA\Scheduling;

use KH_SMMA\Services\AuditLogger;
use WP_Error;

use function add_filter;
use function do_action;
use function get_post_meta;
use function gmdate;
use function is_wp_error;
use function strtolower;
use function update_post_meta;
use function wp_generate_uuid4;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DispatchEligibilityService {
	private ?AuditLogger $logger;

	public function __construct( ?AuditLogger $logger = null ) {
		$this->logger = $logger;
	}

	public function register(): void {
		add_filter( 'kh_smma_dispatch_schedule', array( $this, 'enforce_before_dispatch' ), 1, 4 );
	}

	/**
	 * @param mixed $result
	 * @param int|string $schedule_id
	 * @param mixed $payload
	 * @param mixed $context
	 * @return mixed
	 */
	public function enforce_before_dispatch( $result, $schedule_id, $payload, $context ) {
		if ( is_wp_error( $result ) || null !== $result ) {
			return $result;
		}

		$sid = (int) $schedule_id;
		$approval_required = (bool) get_post_meta( $sid, '_kh_smma_approval_required', true );
		$approval_status = strtolower( (string) get_post_meta( $sid, '_kh_smma_approval_status', true ) );
		if ( '' === $approval_status ) {
			$approval_status = 'pending';
		}

		$evaluation = $this->evaluate( array(
			'approval_required' => $approval_required,
			'approval_status' => $approval_status,
		) );

		update_post_meta( $sid, '_kh_smma_queue_label', $evaluation['queue_label'] );
		if ( ! $evaluation['eligible'] ) {
			$blocked_status = 'rejected' === $evaluation['approval_status'] ? 'rejected' : 'pending_approval';
			update_post_meta( $sid, '_kh_smma_schedule_status', $blocked_status );

			$trace_id = wp_generate_uuid4();
			$telemetry = array(
				'trace_id' => $trace_id,
				'schedule_id' => (string) $sid,
				'reason' => 'approval_required',
				'approval_status' => $evaluation['approval_status'],
				'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			);
			do_action( 'kh_smma_telemetry_event', 'schedule.blocked', $telemetry );

			if ( $this->logger ) {
				$this->logger->log( 'smma_schedule_blocked', array(
					'object_type' => 'schedule',
					'object_id' => $sid,
					'details' => $telemetry,
				) );
			}

			return new WP_Error(
				'APPROVAL_REQUIRED',
				'Schedule requires sponsor approval before dispatch.',
				array(
					'status' => 409,
					'error' => 'APPROVAL_REQUIRED',
					'approval_status' => $evaluation['approval_status'],
				)
			);
		}

		if ( 'approved' === $evaluation['approval_status'] ) {
			update_post_meta( $sid, '_kh_smma_schedule_status', 'pending' );
		}

		return $result;
	}

	public function evaluate( array $schedule ): array {
		$approval_required = ! empty( $schedule['approval_required'] );
		$approval_status = strtolower( (string) ( $schedule['approval_status'] ?? 'pending' ) );
		if ( 'auto_approved' === $approval_status ) {
			$approval_status = 'approved';
		}
		if ( 'denied' === $approval_status ) {
			$approval_status = 'rejected';
		}

		if ( ! $approval_required ) {
			return array(
				'eligible' => true,
				'approval_status' => $approval_status,
				'queue_label' => 'Ready',
				'reason' => '',
			);
		}

		if ( 'approved' === $approval_status ) {
			return array(
				'eligible' => true,
				'approval_status' => $approval_status,
				'queue_label' => 'Ready',
				'reason' => '',
			);
		}

		if ( 'rejected' === $approval_status ) {
			return array(
				'eligible' => false,
				'approval_status' => $approval_status,
				'queue_label' => 'Rejected',
				'reason' => 'approval_required',
			);
		}

		return array(
			'eligible' => false,
			'approval_status' => 'pending',
			'queue_label' => 'Awaiting Approval',
			'reason' => 'approval_required',
		);
	}
}
