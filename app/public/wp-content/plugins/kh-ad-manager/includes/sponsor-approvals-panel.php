<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sponsor Approvals Admin UI
 * 
 * Adds a "Sponsor Approvals" section to Boost Visibility that displays
 * pending sponsor approval schedules and allows approve/reject actions.
 */

add_action( 'admin_init', function() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    // Handle sponsor approval form submission
    if ( isset( $_POST['kh_sponsor_approval_action'] ) && check_admin_referer( 'kh_sponsor_approval_nonce' ) ) {
        $action = sanitize_key( $_POST['kh_sponsor_approval_action'] );
        $schedule_id = absint( $_POST['schedule_id'] ?? 0 );
        $notes = sanitize_textarea_field( $_POST['approval_notes'] ?? '' );

        if ( ! $schedule_id ) {
            wp_die( esc_html__( 'Invalid schedule ID.', 'kh-ad-manager' ) );
        }

        $schedule = get_post( $schedule_id );
        if ( ! $schedule || 'kh_smma_schedule' !== $schedule->post_type ) {
            wp_die( esc_html__( 'Schedule not found.', 'kh-ad-manager' ) );
        }

        if ( 'approve' === $action ) {
            update_post_meta( $schedule_id, '_kh_smma_sponsor_approval_status', 'approved' );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_approved_by', get_current_user_id() );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_approved_at', time() );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_approval_notes', $notes );
            wp_safe_redirect( add_query_arg( 'message', 'sponsor_approved', remove_query_arg( 'action' ) ) );
            exit;
        } elseif ( 'reject' === $action ) {
            update_post_meta( $schedule_id, '_kh_smma_sponsor_approval_status', 'rejected' );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_approved_by', get_current_user_id() );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_approved_at', time() );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_approval_notes', $notes );
            wp_safe_redirect( add_query_arg( 'message', 'sponsor_rejected', remove_query_arg( 'action' ) ) );
            exit;
        }
    }
} );

/**
 * Render sponsor approvals panel (called from Boost Visibility)
 * 
 * @param array $args Optional arguments
 */
function kh_ad_manager_render_sponsor_approvals_panel( $args = array() ) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    // Get pending sponsor approvals
    $pending_query = new WP_Query( array(
        'post_type'      => 'kh_smma_schedule',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_kh_smma_sponsor_id',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ),
            array(
                'key'     => '_kh_smma_sponsor_approval_status',
                'value'   => 'pending',
                'compare' => '=',
            ),
        ),
    ) );

    // Success/error messages
    if ( isset( $_GET['message'] ) ) {
        $message = sanitize_key( $_GET['message'] );
        if ( 'sponsor_approved' === $message ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sponsor approval saved.', 'kh-ad-manager' ) . '</p></div>';
        } elseif ( 'sponsor_rejected' === $message ) {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Sponsor rejection saved.', 'kh-ad-manager' ) . '</p></div>';
        }
    }

    ?>
    <div class="kh-sponsor-approvals-panel">
        <h2><?php esc_html_e( 'Sponsor Approvals', 'kh-ad-manager' ); ?></h2>
        
        <?php if ( $pending_query->have_posts() ) : ?>
            <p class="description">
                <?php esc_html_e( 'Review and approve ad variants before they are sent to sponsors for final sign-off.', 'kh-ad-manager' ); ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Schedule', 'kh-ad-manager' ); ?></th>
                        <th><?php esc_html_e( 'Sponsor', 'kh-ad-manager' ); ?></th>
                        <th><?php esc_html_e( 'Variant Text Preview', 'kh-ad-manager' ); ?></th>
                        <th><?php esc_html_e( 'Scheduled For', 'kh-ad-manager' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'kh-ad-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ( $pending_query->have_posts() ) : $pending_query->the_post();
                        $schedule_id = get_the_ID();
                        $sponsor_id = (int) get_post_meta( $schedule_id, '_kh_smma_sponsor_id', true );
                        $payload = get_post_meta( $schedule_id, '_kh_smma_payload', true );
                        $scheduled_at = get_post_meta( $schedule_id, '_kh_smma_scheduled_at', true );

                        $sponsor_meta = array();
                        $sponsor_name = '—';
                        if ( $sponsor_id && function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
                            $sponsor_meta = kh_ad_manager_get_sponsor_meta( $sponsor_id );
                            $sponsor_name = $sponsor_meta['name'] ?? '';
                        }

                        $variant_text = '';
                        if ( is_array( $payload ) ) {
                            $variant_text = $payload['message'] ?? $payload['text'] ?? '';
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( get_the_title() ?: sprintf( __( '#%d', 'kh-ad-manager' ), $schedule_id ) ); ?></strong>
                                <br />
                                <small style="color: #666;">ID: <?php echo esc_html( $schedule_id ); ?></small>
                            </td>
                            <td>
                                <?php if ( $sponsor_id && ! empty( $sponsor_name ) ) : ?>
                                    <strong><?php echo esc_html( $sponsor_name ); ?></strong>
                                    <br />
                                    <small style="color: #666;">
                                        <?php esc_html_e( 'Contact:', 'kh-ad-manager' ); ?>
                                        <?php 
                                            $approval_contact = $sponsor_meta['approval_contact'] ?? array();
                                            echo esc_html( is_array( $approval_contact ) ? $approval_contact['name'] ?? '—' : '—' );
                                        ?>
                                    </small>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <p style="max-width: 300px; white-space: normal; word-break: break-word;">
                                    <?php echo esc_html( substr( $variant_text, 0, 150 ) ); ?>
                                    <?php if ( strlen( $variant_text ) > 150 ) : ?>
                                        …
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td>
                                <?php 
                                    $scheduled_at = (int) $scheduled_at;
                                    echo $scheduled_at ? esc_html( wp_date( 'M d, Y H:i', $scheduled_at ) ) : '—';
                                ?>
                            </td>
                            <td>
                                <button 
                                    type="button" 
                                    class="button kh-sponsor-approve-btn"
                                    data-schedule-id="<?php echo esc_attr( $schedule_id ); ?>"
                                    onclick="kh_show_approval_modal(<?php echo esc_attr( $schedule_id ); ?>, 'approve')"
                                >
                                    <?php esc_html_e( 'Approve', 'kh-ad-manager' ); ?>
                                </button>
                                <button 
                                    type="button" 
                                    class="button button-secondary kh-sponsor-reject-btn"
                                    data-schedule-id="<?php echo esc_attr( $schedule_id ); ?>"
                                    onclick="kh_show_approval_modal(<?php echo esc_attr( $schedule_id ); ?>, 'reject')"
                                >
                                    <?php esc_html_e( 'Reject', 'kh-ad-manager' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php wp_reset_postdata(); ?>

        <?php else : ?>
            <p class="description">
                <?php esc_html_e( 'No pending sponsor approvals.', 'kh-ad-manager' ); ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Approval Modal -->
    <div id="kh-approval-modal" class="kh-modal" style="display:none;">
        <div class="kh-modal-content">
            <span class="kh-modal-close" onclick="kh_close_approval_modal()">&times;</span>
            <h2 id="modal-title"><?php esc_html_e( 'Sponsor Approval', 'kh-ad-manager' ); ?></h2>
            
            <form method="post" class="kh-approval-form">
                <?php wp_nonce_field( 'kh_sponsor_approval_nonce' ); ?>
                <input type="hidden" name="kh_sponsor_approval_action" id="approval_action" value="approve" />
                <input type="hidden" name="schedule_id" id="approval_schedule_id" value="" />

                <div class="kh-form-group">
                    <label for="approval_notes">
                        <strong><?php esc_html_e( 'Notes', 'kh-ad-manager' ); ?></strong>
                    </label>
                    <textarea 
                        id="approval_notes" 
                        name="approval_notes"
                        rows="4"
                        class="widefat"
                        placeholder="<?php esc_attr_e( 'Add notes for the record...', 'kh-ad-manager' ); ?>"
                    ></textarea>
                    <small><?php esc_html_e( 'This will be stored in the audit trail.', 'kh-ad-manager' ); ?></small>
                </div>

                <div class="kh-modal-actions">
                    <button type="submit" class="button button-primary" id="approval_submit_btn">
                        <?php esc_html_e( 'Confirm', 'kh-ad-manager' ); ?>
                    </button>
                    <button type="button" class="button" onclick="kh_close_approval_modal()">
                        <?php esc_html_e( 'Cancel', 'kh-ad-manager' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
    .kh-sponsor-approvals-panel {
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        margin: 20px 0;
        border-radius: 4px;
    }

    .kh-sponsor-approvals-panel h2 {
        margin-top: 0;
    }

    .kh-modal {
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.4);
    }

    .kh-modal-content {
        background-color: #fefefe;
        margin: 100px auto;
        padding: 20px;
        border: 1px solid #888;
        width: 500px;
        max-width: 90%;
        border-radius: 4px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .kh-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .kh-modal-close:hover,
    .kh-modal-close:focus {
        color: #000;
    }

    .kh-approval-form {
        margin: 20px 0;
    }

    .kh-form-group {
        margin-bottom: 15px;
    }

    .kh-form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .kh-form-group small {
        display: block;
        margin-top: 5px;
        color: #666;
    }

    .kh-modal-actions {
        text-align: right;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }

    .kh-modal-actions button {
        margin-left: 10px;
    }
    </style>

    <script>
    function kh_show_approval_modal(scheduleId, action) {
        document.getElementById('approval_action').value = action;
        document.getElementById('approval_schedule_id').value = scheduleId;
        document.getElementById('approval_notes').value = '';
        
        const modalTitle = 'approve' === action 
            ? '<?php esc_html_e( 'Approve Sponsor Submission', 'kh-ad-manager' ); ?>'
            : '<?php esc_html_e( 'Reject Sponsor Submission', 'kh-ad-manager' ); ?>';
        
        const buttonText = 'approve' === action
            ? '<?php esc_html_e( 'Approve', 'kh-ad-manager' ); ?>'
            : '<?php esc_html_e( 'Reject', 'kh-ad-manager' ); ?>';

        document.getElementById('modal-title').innerText = modalTitle;
        document.getElementById('approval_submit_btn').innerText = buttonText;
        document.getElementById('approval_submit_btn').className = 'approve' === action 
            ? 'button button-primary' 
            : 'button button-secondary';

        document.getElementById('kh-approval-modal').style.display = 'block';
    }

    function kh_close_approval_modal() {
        document.getElementById('kh-approval-modal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('kh-approval-modal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
    </script>
    <?php
}

// Add styles for sponsor approval section in Boost Visibility
add_action( 'admin_head', function() {
    ?>
    <style>
    .kh-sponsor-approvals-panel table {
        margin-top: 20px;
    }

    .kh-sponsor-approvals-panel .wp-list-table {
        margin-top: 15px;
    }

    .kh-sponsor-approvals-panel td {
        vertical-align: middle;
    }

    .kh-sponsor-approve-btn {
        background-color: #46b450;
        border-color: #2d6a3e;
        color: #fff;
    }

    .kh-sponsor-approve-btn:hover {
        background-color: #3d9f41;
    }

    .kh-sponsor-reject-btn {
        background-color: #dc3232;
        border-color: #8a1818;
        color: #fff;
    }

    .kh-sponsor-reject-btn:hover {
        background-color: #c71919;
    }
    </style>
    <?php
} );
