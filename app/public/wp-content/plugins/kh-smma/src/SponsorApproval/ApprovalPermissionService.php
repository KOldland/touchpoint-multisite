<?php
declare( strict_types=1 );

namespace KH_SMMA\SponsorApproval;

use function current_user_can;
use function get_current_user_id;
use function get_user_meta;
use function sanitize_text_field;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ApprovalPermissionService {
    public function can_manage_approvals( ?int $user_id = null ): bool {
        return $this->is_site_admin( $user_id ) || $this->is_sponsor_manager( $user_id );
    }

    public function is_site_admin( ?int $user_id = null ): bool {
        return current_user_can( 'manage_options' );
    }

    public function is_sponsor_manager( ?int $user_id = null ): bool {
        return current_user_can( 'manage_sponsors' ) || current_user_can( 'edit_schedules' );
    }

    public function assigned_sponsor_id( ?int $user_id = null ): string {
        $user_id = $user_id ?? get_current_user_id();
        $assigned = sanitize_text_field( (string) get_user_meta( $user_id, 'assigned_sponsor_id', true ) );
        if ( '' !== $assigned ) {
            return $assigned;
        }

        return sanitize_text_field( (string) get_user_meta( $user_id, '_kh_smma_assigned_sponsor_id', true ) );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function enforce_sponsor_scope( array $filters, ?int $user_id = null ): array {
        if ( $this->is_site_admin( $user_id ) ) {
            return $filters;
        }

        if ( ! $this->is_sponsor_manager( $user_id ) ) {
            $filters['sponsor_id'] = '__no_access__';
            return $filters;
        }

        $assigned = $this->assigned_sponsor_id( $user_id );
        if ( '' === $assigned ) {
            $filters['sponsor_id'] = '__no_access__';
            return $filters;
        }

        $filters['sponsor_id'] = $assigned;
        return $filters;
    }

    /**
     * @param array<string,mixed> $schedule
     */
    public function can_approve_schedule( array $schedule, ?int $user_id = null ): bool {
        if ( $this->is_site_admin( $user_id ) ) {
            return true;
        }

        if ( ! $this->is_sponsor_manager( $user_id ) ) {
            return false;
        }

        $assigned = $this->assigned_sponsor_id( $user_id );
        $sponsor  = sanitize_text_field( (string) ( $schedule['sponsor_id'] ?? '' ) );

        return '' !== $assigned && '' !== $sponsor && $assigned === $sponsor;
    }

    public function permission_denied_message(): string {
        return 'You do not have permission to approve schedules for this sponsor.';
    }
}
