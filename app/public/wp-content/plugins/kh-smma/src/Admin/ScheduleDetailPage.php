<?php
declare( strict_types=1 );

namespace KH_SMMA\Admin;

use function add_action;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_post;
use function get_post_meta;
use function rest_url;
use function sanitize_text_field;
use function wp_create_nonce;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScheduleDetailPage {
    private string $page_hook = '';

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function add_menu(): void {
        if ( ! $this->can_access() ) {
            return;
        }

        $this->page_hook = (string) add_submenu_page(
            null,
            __( 'Schedule Detail', 'kh-smma' ),
            __( 'Schedule Detail', 'kh-smma' ),
            'read',
            'kh-smma-schedule-detail',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( '' === $this->page_hook || $hook !== $this->page_hook ) {
            return;
        }

        wp_enqueue_style(
            'kh-smma-sponsor-approval',
            KH_SMMA_URL . 'assets/css/sponsor-approval.css',
            array(),
            KH_SMMA_VERSION
        );

        wp_enqueue_script(
            'kh-smma-sponsor-approval',
            KH_SMMA_URL . 'assets/js/sponsor-approval.js',
            array( 'jquery' ),
            KH_SMMA_VERSION,
            true
        );

        wp_localize_script( 'kh-smma-sponsor-approval', 'khSmmaSponsorApproval', array(
            'apiBase' => rest_url( 'kh-smma/v1/sponsor-approvals' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'pageUrl' => admin_url( 'admin.php?page=smma-pending-approvals' ),
        ) );
    }

    public function render_page(): void {
        if ( ! $this->can_access() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        $schedule_id = sanitize_text_field( (string) ( $_GET['schedule_id'] ?? '' ) );
        if ( '' === $schedule_id ) {
            wp_die( esc_html__( 'Missing schedule_id.', 'kh-smma' ) );
        }

        $post = get_post( (int) $schedule_id );
        $title = $post ? (string) $post->post_title : __( 'Unknown schedule', 'kh-smma' );
        $approval_status = strtolower( (string) get_post_meta( (int) $schedule_id, '_kh_smma_approval_status', true ) );
        if ( 'auto_approved' === $approval_status ) {
            $approval_status = 'approved';
        }
        if ( 'denied' === $approval_status ) {
            $approval_status = 'rejected';
        }
        if ( '' === $approval_status ) {
            $approval_status = 'pending';
        }
        $queue_label = 'pending' === $approval_status ? 'Awaiting Approval' : ( 'approved' === $approval_status ? 'Ready' : 'Rejected' );
        $budget_cents = (int) get_post_meta( (int) $schedule_id, '_kh_smma_budget_cents', true );
        $estimated_spend = $budget_cents > 0 ? round( $budget_cents / 100, 2 ) : 0.0;
        $estimated_ops = 1;
        $notifications = get_post_meta( (int) $schedule_id, '_kh_smma_in_app_notifications', true );
        if ( ! is_array( $notifications ) ) {
            $notifications = array();
        }
        $notifications = array_reverse( $notifications );
        $bundle_endpoint = rest_url( 'kh-smma/v1/manual-export/schedule/' . $schedule_id . '/bundle' );
        $download_endpoint = rest_url( 'kh-smma/v1/manual-export/schedule/' . $schedule_id . '/download' );
        ?>
        <div class="wrap kh-smma-schedule-detail">
            <h1><?php esc_html_e( 'Schedule Detail', 'kh-smma' ); ?></h1>
            <p>
                <strong><?php esc_html_e( 'Schedule ID:', 'kh-smma' ); ?></strong>
                <?php echo esc_html( $schedule_id ); ?>
            </p>
            <p>
                <strong><?php esc_html_e( 'Title:', 'kh-smma' ); ?></strong>
                <?php echo esc_html( $title ); ?>
            </p>
            <p>
                <strong><?php esc_html_e( 'Approval Status:', 'kh-smma' ); ?></strong>
                <?php echo esc_html( ucfirst( $approval_status ) ); ?>
                <span style="margin-left:8px;"><?php echo esc_html( '(' . $queue_label . ')' ); ?></span>
            </p>

            <h2><?php esc_html_e( 'Approval History', 'kh-smma' ); ?></h2>
            <div id="kh-smma-approval-history" class="kh-smma-approval-history" data-schedule-id="<?php echo esc_attr( $schedule_id ); ?>">
                <p><?php esc_html_e( 'No approval history recorded', 'kh-smma' ); ?></p>
            </div>

            <h2><?php esc_html_e( 'In-App Notifications', 'kh-smma' ); ?></h2>
            <div class="kh-smma-notification-list">
                <?php if ( empty( $notifications ) ) : ?>
                    <p><?php esc_html_e( 'No notifications recorded', 'kh-smma' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $notifications as $notification ) : ?>
                        <div class="kh-smma-notification-item">
                            <div class="kh-smma-notification-message"><?php echo esc_html( (string) ( $notification['message'] ?? '' ) ); ?></div>
                            <div class="kh-smma-notification-meta">
                                <?php
                                $decision = (string) ( $notification['decision'] ?? '' );
                                $recipient = (string) ( $notification['recipient_type'] ?? '' );
                                $at = (string) ( $notification['timestamp'] ?? '' );
                                echo esc_html( sprintf( '%s · %s · %s', $decision, $recipient, $at ) );
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2><?php esc_html_e( 'Manual Export', 'kh-smma' ); ?></h2>
            <p>
                <strong><?php esc_html_e( 'Estimated Spend:', 'kh-smma' ); ?></strong>
                <?php echo esc_html( '$' . number_format( (float) $estimated_spend, 2 ) ); ?>
            </p>
            <p>
                <strong><?php esc_html_e( 'Estimated Ops:', 'kh-smma' ); ?></strong>
                <?php echo esc_html( (string) $estimated_ops . ' campaign' ); ?>
            </p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( $bundle_endpoint ); ?>">
                    <?php esc_html_e( 'Generate Export Bundle', 'kh-smma' ); ?>
                </a>
                <a class="button" href="<?php echo esc_url( $download_endpoint ); ?>">
                    <?php esc_html_e( 'Download Export Bundle', 'kh-smma' ); ?>
                </a>
            </p>

            <p>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=smma-pending-approvals' ) ); ?>">
                    <?php esc_html_e( 'Back to Pending Approvals', 'kh-smma' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function can_access(): bool {
        return current_user_can( 'manage_sponsors' )
            || current_user_can( 'edit_schedules' )
            || current_user_can( 'manage_options' );
    }
}
