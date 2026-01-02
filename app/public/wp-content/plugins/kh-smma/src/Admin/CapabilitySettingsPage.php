<?php
namespace KH_SMMA\Admin;

use KH_SMMA\Security\CapabilityManager;

use function add_action;
use function add_submenu_page;
use function admin_url;
use function check_admin_referer;
use function current_user_can;
use function esc_html_e;
use function esc_html;
use function esc_attr;
use function esc_url;
use function get_editable_roles;
use function get_role;
use function submit_button;
use function wp_die;
use function wp_nonce_field;
use function wp_safe_redirect;
use function absint;
use function update_option;
use function delete_option;
use function wp_unslash;
use function sanitize_text_field;
use function in_array;
use function array_map;
use function __;
use function checked;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CapabilitySettingsPage {
    public function register() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_post_kh_smma_save_caps', array( $this, 'save_caps' ) );
    }

    public function add_menu() {
        add_submenu_page(
            'kh-smma-dashboard',
            __( 'KH Social Permissions', 'kh-smma' ),
            __( 'Permissions', 'kh-smma' ),
            CapabilityManager::CAP_MANAGE,
            'kh-smma-permissions',
            array( $this, 'render_page' )
        );
    }

    public function render_page() {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        $caps = array(
            CapabilityManager::CAP_VIEW     => __( 'View queue/dashboard', 'kh-smma' ),
            CapabilityManager::CAP_SCHEDULE => __( 'Schedule posts', 'kh-smma' ),
            CapabilityManager::CAP_MANAGE   => __( 'Manage accounts & settings', 'kh-smma' ),
        );
        $roles = get_editable_roles();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Social Manager Permissions', 'kh-smma' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'kh_smma_save_caps' ); ?>
                <input type="hidden" name="action" value="kh_smma_save_caps" />
                <table class="widefat fixed">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Role', 'kh-smma' ); ?></th>
                            <?php foreach ( $caps as $cap_slug => $label ) : ?>
                                <th><?php echo esc_html( $label ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $roles as $role_slug => $role ) :
                            $role_obj = get_role( $role_slug );
                            ?>
                            <tr>
                                <td><?php echo esc_html( $role['name'] ); ?></td>
                                <?php foreach ( $caps as $cap_slug => $label ) : ?>
                                    <td>
                                        <input type="checkbox" name="caps[<?php echo esc_attr( $role_slug ); ?>][]" value="<?php echo esc_attr( $cap_slug ); ?>" <?php checked( $role_obj && $role_obj->has_cap( $cap_slug ) ); ?> />
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button( __( 'Save Permissions', 'kh-smma' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function save_caps() {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_save_caps' );

        $submitted = isset( $_POST['caps'] ) ? (array) $_POST['caps'] : array();
        $caps      = array( CapabilityManager::CAP_VIEW, CapabilityManager::CAP_SCHEDULE, CapabilityManager::CAP_MANAGE );

        foreach ( get_editable_roles() as $role_slug => $role ) {
            $role_obj = get_role( $role_slug );
            if ( ! $role_obj ) {
                continue;
            }

            foreach ( $caps as $cap ) {
                if ( isset( $submitted[ $role_slug ] ) && in_array( $cap, (array) $submitted[ $role_slug ], true ) ) {
                    $role_obj->add_cap( $cap );
                } else {
                    $role_obj->remove_cap( $cap );
                }
            }
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-permissions', 'updated' => 'true' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
