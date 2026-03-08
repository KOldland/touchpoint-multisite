<?php

namespace KHM\Membership\Admin;

use KHM\Membership\ProcessedWebhook;

class WebhookEventsPage {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_post_khm_membership_webhook_event_action', [ $this, 'handle_action' ] );
    }

    public function add_admin_menu(): void {
        add_submenu_page(
            'khm-membership',
            __( 'Webhook Events', 'khm-membership' ),
            __( 'Webhook Events', 'khm-membership' ),
            'manage_options',
            'khm-membership-webhooks',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( sprintf( 'unauthorized_admin_access user_id=%d resource=%s', (int) get_current_user_id(), 'khm-membership-webhooks' ) );
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        $rows = ProcessedWebhook::list_events( 100 );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Stripe Webhook Events', 'khm-membership' ); ?></h1>
            <p><?php echo esc_html__( 'Inspect, requeue, or mark webhook events.', 'khm-membership' ); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Event ID', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Attempts', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Updated', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Notes', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'No webhook events found.', 'khm-membership' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $row['event_id'] ); ?></code></td>
                                <td><?php echo esc_html( $row['event_type'] ); ?></td>
                                <td><?php echo esc_html( $row['status'] ); ?></td>
                                <td><?php echo esc_html( $row['attempts'] ); ?></td>
                                <td><?php echo esc_html( $row['updated_at'] ); ?></td>
                                <td><?php echo esc_html( (string) ( $row['notes'] ?? '' ) ); ?></td>
                                <td>
                                    <?php echo wp_kses_post( $this->action_links( $row['event_id'] ) ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_action(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( sprintf( 'unauthorized_admin_access user_id=%d resource=%s', (int) get_current_user_id(), 'khm-membership-webhooks-action' ) );
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_membership_webhook_action' );

        $event_id = sanitize_text_field( (string) ( $_GET['event_id'] ?? '' ) );
        $op = sanitize_text_field( (string) ( $_GET['op'] ?? '' ) );
        if ( '' === $event_id || '' === $op ) {
            wp_safe_redirect( admin_url( 'admin.php?page=khm-membership-webhooks' ) );
            exit;
        }

        if ( 'requeue' === $op ) {
            $this->requeue_event( $event_id );
        } elseif ( 'mark_processed' === $op ) {
            ProcessedWebhook::mark_processed( $event_id, 'Marked processed by admin.' );
        } elseif ( 'mark_failed' === $op ) {
            ProcessedWebhook::mark_failed( $event_id, 'Marked failed by admin.' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=khm-membership-webhooks' ) );
        exit;
    }

    private function requeue_event( string $event_id ): void {
        $row = ProcessedWebhook::get_event( $event_id );
        if ( ! $row || empty( $row['payload'] ) ) {
            ProcessedWebhook::mark_failed( $event_id, 'Admin requeue failed: payload missing.' );
            return;
        }

        $event = json_decode( (string) $row['payload'], true );
        if ( ! is_array( $event ) || empty( $event['type'] ) ) {
            $stripe_client = apply_filters( 'khm_membership_stripe_client', null );

            // Fallback: build a StripeClient from the stored API key if the filter provides none.
            if ( ! ( $stripe_client instanceof \Stripe\StripeClient ) && class_exists( '\Stripe\StripeClient' ) ) {
                $secret = function_exists( 'khm_get_stripe_secret' )
                    ? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
                    : '';
                if ( '' !== $secret ) {
                    $stripe_client = new \Stripe\StripeClient( $secret );
                }
            }

            if ( $stripe_client instanceof \Stripe\StripeClient ) {
                try {
                    $stripe_event = $stripe_client->events->retrieve( $event_id, [] );
                    if ( $stripe_event ) {
                        $event = $stripe_event->toArray();
                    }
                } catch ( \Exception $e ) {
                    ProcessedWebhook::mark_failed( $event_id, 'Admin requeue failed: could not retrieve event from Stripe (' . $e->getMessage() . ').' );
                    return;
                }
            }
        }

        if ( ! is_array( $event ) || empty( $event['type'] ) ) {
            ProcessedWebhook::mark_failed( $event_id, 'Admin requeue failed: payload missing or could not be reconstructed.' );
            return;
        }

        $job = [
            'event_id' => $event_id,
            'event_type' => sanitize_text_field( (string) $event['type'] ),
            'data_object' => isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ? $event['data']['object'] : [],
            'trace_id' => wp_generate_uuid4(),
        ];

        ProcessedWebhook::mark_processing( $event_id, 'Requeued by admin.' );
        if ( function_exists( 'wp_schedule_single_event' ) ) {
            wp_schedule_single_event( time() + 1, 'khm_process_membership_stripe_webhook_event', [ $job ] );
            return;
        }

        do_action( 'khm_process_membership_stripe_webhook_event', $job );
    }

    private function action_links( string $event_id ): string {
        $base = admin_url( 'admin-post.php?action=khm_membership_webhook_event_action&event_id=' . rawurlencode( $event_id ) );
        $requeue = wp_nonce_url( $base . '&op=requeue', 'khm_membership_webhook_action' );
        $processed = wp_nonce_url( $base . '&op=mark_processed', 'khm_membership_webhook_action' );
        $failed = wp_nonce_url( $base . '&op=mark_failed', 'khm_membership_webhook_action' );

        return sprintf(
            '<a href="%1$s">%2$s</a> | <a href="%3$s">%4$s</a> | <a href="%5$s">%6$s</a>',
            esc_url( $requeue ),
            esc_html__( 'Requeue', 'khm-membership' ),
            esc_url( $processed ),
            esc_html__( 'Mark Processed', 'khm-membership' ),
            esc_url( $failed ),
            esc_html__( 'Mark Failed', 'khm-membership' )
        );
    }
}
