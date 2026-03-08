<?php
declare( strict_types=1 );

namespace KH_SMMA\Sponsor;

use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;

use function do_action;
use function time;
use function wp_generate_uuid4;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ApprovalTelemetryService {
    private ScheduleRepository $repository;
    private AuditLogger $logger;

    public function __construct( ScheduleRepository $repository, AuditLogger $logger ) {
        $this->repository = $repository;
        $this->logger     = $logger;
    }

    /**
     * @param array<string,mixed> $schedule
     */
    public function approval_requested( array $schedule, string $approval_reason = '', ?string $trace_id = null, string $review_notes = '', int $reviewer_user_id = 0 ): void {
        $trace_id = $this->trace_id( $trace_id );
        $event_time = time();

        $payload = array(
            'trace_id'          => $trace_id,
            'schedule_id'       => (string) ( $schedule['schedule_id'] ?? '' ),
            'sponsor_id'        => (string) ( $schedule['sponsor_id'] ?? '' ),
            'compliance_status' => strtoupper( (string) ( $schedule['compliance_status'] ?? 'OK' ) ),
            'approval_reason'   => (string) $approval_reason,
            'timestamp'         => $event_time,
        );

        do_action( 'kh_smma_telemetry_event', 'sponsor.approval.requested', $payload );

        $this->logger->record_event( $trace_id, 'sponsor.approval.requested', $event_time, array(
            'schedule_id'       => $payload['schedule_id'],
            'sponsor_id'        => $payload['sponsor_id'],
            'reviewer_user_id'  => $reviewer_user_id,
            'timestamp'         => $event_time,
            'review_notes'      => $review_notes,
            'compliance_status' => $payload['compliance_status'],
            'approval_reason'   => $payload['approval_reason'],
        ) );

        $this->emit_backlog_alert( $trace_id );
    }

    /**
     * @param array<string,mixed> $schedule
     */
    public function approval_approved( array $schedule, int $reviewer_user_id, string $review_notes = '', ?string $trace_id = null ): void {
        $this->approval_decision( 'approved', $schedule, $reviewer_user_id, $review_notes, $trace_id );
    }

    /**
     * @param array<string,mixed> $schedule
     */
    public function approval_rejected( array $schedule, int $reviewer_user_id, string $review_notes = '', ?string $trace_id = null ): void {
        $this->approval_decision( 'rejected', $schedule, $reviewer_user_id, $review_notes, $trace_id );
    }

    /**
     * @param array<string,mixed> $schedule
     */
    private function approval_decision( string $decision, array $schedule, int $reviewer_user_id, string $review_notes, ?string $trace_id ): void {
        $trace_id = $this->trace_id( $trace_id );
        $event_time = time();

        $event_name = 'approved' === $decision
            ? 'sponsor.approval.approved'
            : 'sponsor.approval.rejected';

        $telemetry_payload = array(
            'trace_id'         => $trace_id,
            'schedule_id'      => (string) ( $schedule['schedule_id'] ?? '' ),
            'sponsor_id'       => (string) ( $schedule['sponsor_id'] ?? '' ),
            'reviewer_user_id' => $reviewer_user_id,
            'timestamp'        => $event_time,
        );

        do_action( 'kh_smma_telemetry_event', $event_name, $telemetry_payload );

        $this->logger->record_event( $trace_id, $event_name, $event_time, array(
            'schedule_id'      => $telemetry_payload['schedule_id'],
            'sponsor_id'       => $telemetry_payload['sponsor_id'],
            'reviewer_user_id' => $reviewer_user_id,
            'timestamp'        => $event_time,
            'review_notes'     => $review_notes,
        ) );

        $this->emit_backlog_alert( $trace_id );
        $this->emit_reject_rate_alert( (string) $telemetry_payload['sponsor_id'], $trace_id );
    }

    private function emit_backlog_alert( string $trace_id ): void {
        $pending_count = $this->repository->pendingApprovalsCount();
        if ( $pending_count <= 10 ) {
            return;
        }

        $severity = $pending_count > 25 ? 'CRITICAL' : 'WARNING';
        do_action( 'kh_smma_telemetry_event', 'alert.approval_backlog', array(
            'trace_id'      => $trace_id,
            'pending_count' => $pending_count,
            'severity'      => $severity,
            'timestamp'     => time(),
        ) );
    }

    private function emit_reject_rate_alert( string $sponsor_id, string $trace_id ): void {
        if ( '' === $sponsor_id ) {
            return;
        }

        $stats = $this->repository->sponsorDecisionStats( $sponsor_id, 10 );
        if ( (int) ( $stats['approval_count'] ?? 0 ) <= 0 ) {
            return;
        }

        if ( (float) ( $stats['reject_rate'] ?? 0.0 ) <= 0.60 ) {
            return;
        }

        do_action( 'kh_smma_telemetry_event', 'alert.sponsor_reject_spike', array(
            'trace_id'       => $trace_id,
            'sponsor_id'     => $sponsor_id,
            'reject_count'   => (int) ( $stats['reject_count'] ?? 0 ),
            'approval_count' => (int) ( $stats['approval_count'] ?? 0 ),
            'timestamp'      => time(),
        ) );
    }

    private function trace_id( ?string $trace_id = null ): string {
        if ( is_string( $trace_id ) && '' !== $trace_id ) {
            return $trace_id;
        }

        return function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'trace_', true );
    }
}
