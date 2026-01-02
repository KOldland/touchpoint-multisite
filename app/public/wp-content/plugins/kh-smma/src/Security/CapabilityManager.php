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

    public function register() {
        add_action( 'init', array( $this, 'ensure_capabilities' ) );
    }

    public function ensure_capabilities() {
        $role_caps = array(
            'administrator' => array( self::CAP_VIEW, self::CAP_SCHEDULE, self::CAP_MANAGE ),
            'editor'        => array( self::CAP_VIEW, self::CAP_SCHEDULE ),
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
}
