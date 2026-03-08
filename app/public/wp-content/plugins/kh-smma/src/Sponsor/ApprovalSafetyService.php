<?php
declare( strict_types=1 );

namespace KH_SMMA\Sponsor;

use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use WP_Error;

use function do_action;
use function get_current_user_id;
use function sanitize_text_field;
use function time;
use function wp_generate_uuid4;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ApprovalSafetyService {
    private ScheduleRepository $repository;
    private AuditLogger $logger;
    private ApprovalTelemetryService $telemetry;

    public function __construct( ScheduleRepository $repository, AuditLogger $logger, ?ApprovalTelemetryService $telemetry = null ) {
        $this->repository = $repository;
        $this->logger     = $logger;
        $this->telemetry  = $telemetry ?: new ApprovalTelemetryService( $repository, $logger );
    }

    /**
     * @param array<string,mixed> $schedule
     * @return array<string,mixed>
     */
    public function apply_re_review_if_needed( array $schedule, ?int $actor_id = null ): array {
        $status = strtolower( (string) ( $schedule['approval_status'] ?? 'pending' ) );
        if ( 'approved' !== $status ) {
            return $schedule;
        }

        if ( $this->has_compliance_changed( $schedule ) ) {
            return $this->revoke_approval( $schedule, 'compliance_changed', $actor_id );
        }

        if ( $this->has_claim_permission_changed( $schedule ) ) {
            return $this->revoke_approval( $schedule, 'sponsor_claim_change', $actor_id );
        }

        return $schedule;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function trigger_claim_change_re_review( string $sponsor_id, array $previous_allowed_claims, array $current_allowed_claims, ?int $actor_id = null, ?array $fixture_rows = null ): array {
        $removed_claims = array_values( array_diff( $this->normalize_list( $previous_allowed_claims ), $this->normalize_list( $current_allowed_claims ) ) );
        if ( '' === sanitize_text_field( $sponsor_id ) || empty( $removed_claims ) ) {
            return array();
        }

        $impacted = $this->repository->findSchedulesImpactedByClaimChange( $sponsor_id, $removed_claims, $fixture_rows );
        $results  = array();

        foreach ( $impacted as $row ) {
            $results[] = $this->revoke_approval( $row, 'sponsor_claim_change', $actor_id );

            $this->logger->log( 'schedule.claim_permission_change', array(
                'object_type' => 'schedule',
                'object_id'   => (int) ( $row['schedule_id'] ?? 0 ),
                'user_id'     => $actor_id ?? get_current_user_id(),
                'details'     => array(
                    'schedule_id' => (string) ( $row['schedule_id'] ?? '' ),
                    'actor'       => $actor_id ?? get_current_user_id(),
                    'reason'      => 'sponsor_claim_change',
                    'timestamp'   => time(),
                ),
            ) );
        }

        return $results;
    }

    /**
     * @param array<string,mixed> $schedule
     */
    public function ensure_approvable( array $schedule, ?int $actor_id = null ): ?WP_Error {
        $compliance = strtoupper( (string) ( $schedule['compliance_status'] ?? $schedule['compliance_result'] ?? 'OK' ) );
        if ( 'FAIL' !== $compliance ) {
            return null;
        }

        $trace_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'trace_', true );
        $event_time = time();
        $schedule_id = (string) ( $schedule['schedule_id'] ?? '' );

        $this->logger->log( 'approval.blocked_compliance_fail', array(
            'object_type' => 'schedule',
            'object_id'   => (int) $schedule_id,
            'user_id'     => $actor_id ?? get_current_user_id(),
            'details'     => array(
                'schedule_id' => $schedule_id,
                'actor'       => $actor_id ?? get_current_user_id(),
                'reason'      => 'compliance_fail',
                'timestamp'   => $event_time,
            ),
        ) );

        do_action( 'kh_smma_telemetry_event', 'approval.blocked', array(
            'trace_id'    => $trace_id,
            'schedule_id' => $schedule_id,
            'reason'      => 'compliance_fail',
            'timestamp'   => $event_time,
        ) );

        return new WP_Error(
            'COMPLIANCE_FAIL_APPROVAL_BLOCKED',
            'Schedules with compliance FAIL cannot be approved. Variant must be edited and pass compliance before approval.',
            array(
                'status'  => 409,
                'error'   => 'COMPLIANCE_FAIL_APPROVAL_BLOCKED',
                'message' => 'Schedules with compliance FAIL cannot be approved. Variant must be edited and pass compliance before approval.',
            )
        );
    }

    /**
     * @param array<string,mixed> $schedule
     * @return array<string,mixed>
     */
    private function revoke_approval( array $schedule, string $reason, ?int $actor_id = null ): array {
        $schedule_id = (string) ( $schedule['schedule_id'] ?? '' );
        if ( '' === $schedule_id ) {
            return $schedule;
        }

        $this->repository->markScheduleForReReview( $schedule_id, $reason );

        $event_time = time();
        $trace_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'trace_', true );

        $this->logger->log( 'schedule.re_review_required', array(
            'object_type' => 'schedule',
            'object_id'   => (int) $schedule_id,
            'user_id'     => $actor_id ?? get_current_user_id(),
            'details'     => array(
                'schedule_id'      => $schedule_id,
                'actor'            => $actor_id ?? get_current_user_id(),
                'reason'           => $reason,
                'previous_status'  => (string) ( $schedule['approval_status'] ?? '' ),
                'timestamp'        => $event_time,
            ),
        ) );

        do_action( 'kh_smma_telemetry_event', 'sponsor.approval.revoked', array(
            'trace_id'    => $trace_id,
            'schedule_id' => $schedule_id,
            'reason'      => $reason,
            'timestamp'   => $event_time,
        ) );

        do_action( 'kh_smma_telemetry_event', 'schedule.re_review_required', array(
            'trace_id'    => $trace_id,
            'schedule_id' => $schedule_id,
            'reason'      => $reason,
            'timestamp'   => $event_time,
        ) );

        $this->telemetry->approval_requested(
            array(
                'schedule_id'       => $schedule_id,
                'sponsor_id'        => (string) ( $schedule['sponsor_id'] ?? '' ),
                'compliance_status' => (string) ( $schedule['compliance_status'] ?? 'OK' ),
            ),
            $reason,
            $trace_id
        );

        $schedule['approval_status']   = 'pending';
        $schedule['approval_required'] = true;
        $schedule['approval_reason']   = $reason;

        return $schedule;
    }

    /**
     * @param array<string,mixed> $schedule
     */
    private function has_compliance_changed( array $schedule ): bool {
        $current_compliance = strtoupper( (string) ( $schedule['compliance_status'] ?? $schedule['compliance_result'] ?? '' ) );
        $approved_compliance = strtoupper( (string) ( $schedule['last_approved_compliance_status'] ?? '' ) );

        $current_ruleset = (string) ( $schedule['ruleset_version'] ?? '' );
        $approved_ruleset = (string) ( $schedule['last_approved_ruleset_version'] ?? '' );

        if ( '' !== $approved_compliance && '' !== $current_compliance && $approved_compliance !== $current_compliance ) {
            return true;
        }

        if ( '' !== $approved_ruleset && '' !== $current_ruleset && $approved_ruleset !== $current_ruleset ) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $schedule
     */
    private function has_claim_permission_changed( array $schedule ): bool {
        $claims_used = $this->normalize_list( $schedule['claims_used'] ?? array() );
        if ( empty( $claims_used ) ) {
            return false;
        }

        $approved_allowed = $this->normalize_list( $schedule['last_approved_allowed_claims'] ?? array() );
        if ( empty( $approved_allowed ) ) {
            return false;
        }

        $currently_allowed = $this->normalize_list( $schedule['allowed_claims'] ?? array() );
        if ( empty( $currently_allowed ) ) {
            return false;
        }

        $removed = array_values( array_diff( $approved_allowed, $currently_allowed ) );
        if ( empty( $removed ) ) {
            return false;
        }

        return ! empty( array_intersect( $claims_used, $removed ) );
    }

    /**
     * @param mixed $items
     * @return array<int,string>
     */
    private function normalize_list( $items ): array {
        if ( is_string( $items ) ) {
            $decoded = json_decode( $items, true );
            if ( is_array( $decoded ) ) {
                $items = $decoded;
            } else {
                $items = array_filter( array_map( 'trim', explode( ',', $items ) ) );
            }
        }

        if ( ! is_array( $items ) ) {
            return array();
        }

        $normalized = array();
        foreach ( $items as $item ) {
            $clean = sanitize_text_field( (string) $item );
            if ( '' !== $clean ) {
                $normalized[] = $clean;
            }
        }

        return array_values( array_unique( $normalized ) );
    }
}
