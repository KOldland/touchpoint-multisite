<?php
namespace KH_SMMA\Sponsor;

use function current_user_can;
use function get_current_user_id;
use function get_user_meta;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ApprovalPermissionService {
    public function can_manage_banned_phrases( int $user_id = 0 ): bool {
        return $this->has_capability( 'manage_options', $user_id ) || $this->has_capability( 'kh_smma_manage_compliance_rules', $user_id );
    }

    public function can_manage_sponsor_claims( int $sponsor_id, int $user_id = 0 ): bool {
        if ( $this->has_capability( 'manage_options', $user_id ) ) {
            return true;
        }

        if ( ! $this->has_capability( 'kh_smma_manage_sponsor_claims', $user_id ) ) {
            return false;
        }

        if ( $sponsor_id <= 0 ) {
            return false;
        }

        return $this->user_owns_sponsor( $sponsor_id, $user_id );
    }

    public function can_view_compliance_audit( int $user_id = 0 ): bool {
        return $this->has_capability( 'manage_options', $user_id ) || $this->has_capability( 'kh_smma_view_compliance_audit', $user_id );
    }

    private function user_owns_sponsor( int $sponsor_id, int $user_id = 0 ): bool {
        $uid = $user_id > 0 ? $user_id : get_current_user_id();
        if ( $uid <= 0 || ! function_exists( 'get_user_meta' ) ) {
            return false;
        }

        $raw = get_user_meta( $uid, 'kh_smma_sponsor_ids', true );
        if ( empty( $raw ) ) {
            return false;
        }

        if ( is_string( $raw ) ) {
            $raw = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        }

        if ( ! is_array( $raw ) ) {
            return false;
        }

        return in_array( (string) $sponsor_id, array_map( 'strval', $raw ), true );
    }

    private function has_capability( string $capability, int $user_id = 0 ): bool {
        if ( $user_id > 0 && function_exists( 'user_can' ) ) {
            return user_can( $user_id, $capability );
        }

        return current_user_can( $capability );
    }
}
