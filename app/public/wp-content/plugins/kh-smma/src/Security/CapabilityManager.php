<?php
namespace KH_SMMA\Security;

use WP_Roles;

use function add_action;
use function current_user_can;
use function get_role;
use function wp_roles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CapabilityManager {
    const CAP_VIEW     = 'kh_smma_view_queue';
    const CAP_SCHEDULE = 'kh_smma_schedule_posts';
    const CAP_MANAGE   = 'kh_smma_manage_accounts';
    const CAP_APPROVE_SPONSOR      = 'approve_sponsor_posts';
    const CAP_MANAGE_COMPLIANCE_RULES = 'kh_smma_manage_compliance_rules';
    const CAP_MANAGE_SPONSOR_CLAIMS   = 'kh_smma_manage_sponsor_claims';
    const CAP_VIEW_COMPLIANCE_AUDIT   = 'kh_smma_view_compliance_audit';
    const CAP_FINANCE              = 'kh_paid_finance';
    const CAP_MANAGE_PAID_ADAPTERS = 'manage_paid_adapters';
    // OBS-08: Observability access controls.
    const CAP_VIEW_OBSERVABILITY   = 'view_observability';
    const CAP_MANAGE_OBSERVABILITY = 'manage_observability';

    public function register() {
        add_action( 'init', array( $this, 'ensure_capabilities' ) );
    }

    public function ensure_capabilities() {
        $role_caps = array(
            'administrator' => array( self::CAP_VIEW, self::CAP_SCHEDULE, self::CAP_MANAGE, self::CAP_APPROVE_SPONSOR, self::CAP_FINANCE, self::CAP_MANAGE_PAID_ADAPTERS, self::CAP_VIEW_OBSERVABILITY, self::CAP_MANAGE_OBSERVABILITY, self::CAP_MANAGE_COMPLIANCE_RULES, self::CAP_MANAGE_SPONSOR_CLAIMS, self::CAP_VIEW_COMPLIANCE_AUDIT ),
            'editor'        => array( self::CAP_VIEW, self::CAP_SCHEDULE, self::CAP_APPROVE_SPONSOR, self::CAP_MANAGE_PAID_ADAPTERS, self::CAP_VIEW_OBSERVABILITY, self::CAP_MANAGE_SPONSOR_CLAIMS, self::CAP_VIEW_COMPLIANCE_AUDIT ),
            'author'        => array( self::CAP_VIEW ),
        );

        foreach ( $role_caps as $role_name => $caps ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }
            foreach ( $caps as $cap ) {
                if ( ! $role->has_cap( $cap ) ) {
                    $role->add_cap( $cap );
                }
            }
        }
    }

    public static function can_view() {
        return current_user_can( self::CAP_VIEW ) || current_user_can( 'manage_options' );
    }

    public static function can_schedule() {
        return current_user_can( self::CAP_SCHEDULE ) || current_user_can( 'manage_options' );
    }

    public static function can_manage_accounts() {
        return current_user_can( self::CAP_MANAGE ) || current_user_can( 'manage_options' );
    }

    public static function can_approve_sponsor_content() {
        return current_user_can( self::CAP_APPROVE_SPONSOR ) || current_user_can( 'manage_options' );
    }

    public static function can_manage_finance(): bool {
        return current_user_can( self::CAP_FINANCE ) || current_user_can( 'manage_options' );
    }

    public static function can_manage_paid_adapters(): bool {
        return current_user_can( self::CAP_MANAGE_PAID_ADAPTERS ) || current_user_can( 'manage_options' );
    }

    public static function can_view_observability(): bool {
        return current_user_can( self::CAP_VIEW_OBSERVABILITY ) || current_user_can( 'manage_options' );
    }

    public static function can_manage_observability(): bool {
        return current_user_can( self::CAP_MANAGE_OBSERVABILITY ) || current_user_can( 'manage_options' );
    }

    public static function can_manage_compliance_rules(): bool {
        return current_user_can( self::CAP_MANAGE_COMPLIANCE_RULES ) || current_user_can( 'manage_options' );
    }

    public static function can_manage_sponsor_claims(): bool {
        return current_user_can( self::CAP_MANAGE_SPONSOR_CLAIMS ) || current_user_can( 'manage_options' );
    }
}
