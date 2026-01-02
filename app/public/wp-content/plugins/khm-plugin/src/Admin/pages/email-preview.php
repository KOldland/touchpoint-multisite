<?php
/**
 * Email Preview Admin Page
 */

use KHM\Services\EmailService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_khm' ) ) {
    wp_die( __( 'You do not have permission to access this page.', 'khm-membership' ) );
}

$plugin_dir = dirname( __DIR__, 3 );
$email      = new EmailService( $plugin_dir );

// Available templates (discover from plugin email folder)
$templates = array();
$email_dir = trailingslashit( $plugin_dir ) . 'email';
if ( is_dir( $email_dir ) ) {
    foreach ( glob( $email_dir . '/*.html' ) as $file ) {
        $templates[] = basename( $file, '.html' );
    }
}

// Provide a stable preferred ordering
$preferred_order = array( 'checkout_paid', 'invoice', 'invoice_admin', 'renewal', 'renewal_admin', 'membership_expiring', 'membership_expired', 'default' );
usort( $templates, function ( $a, $b ) use ( $preferred_order ) {
    $pa = array_search( $a, $preferred_order, true );
    $pb = array_search( $b, $preferred_order, true );
    $pa = $pa === false ? PHP_INT_MAX : $pa;
    $pb = $pb === false ? PHP_INT_MAX : $pb;
    if ( $pa === $pb ) {
        return strcmp( $a, $b );
    }
    return $pa <=> $pb;
} );

// Default template selection
$selected_template = isset( $_REQUEST['khm_template'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['khm_template'] ) ) : 'invoice';
$recipient         = isset( $_REQUEST['khm_recipient'] ) ? sanitize_email( wp_unslash( $_REQUEST['khm_recipient'] ) ) : get_option( 'admin_email' );
$action            = isset( $_POST['khm_action'] ) ? sanitize_text_field( wp_unslash( $_POST['khm_action'] ) ) : '';
$order_id_param    = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;

// Utility: get most recent order id
if ( ! function_exists( 'khm_get_most_recent_order_id' ) ) {
    function khm_get_most_recent_order_id(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_membership_orders';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $id = (int) $wpdb->get_var( "SELECT id FROM {$table} ORDER BY id DESC LIMIT 1" );
        return $id ?: 0;
    }
}

// Default sample data by template
$sample_data = array(
    'checkout_paid' => array(
        'name' => wp_get_current_user()->display_name,
        'membership_level' => 'Gold Plan',
        'amount' => 49.00,
        'due_today' => 9.00,
        'discount_summary' => 'Discount SAVE80 applied: -$40.00',
        'trial_summary' => 'Paid trial: 14 days ($9.00 due today)',
        'recurring_summary' => 'Recurring discount: $5.00 off each renewal',
    ),
    'invoice' => array(
        'user_name' => wp_get_current_user()->display_name,
        'user_email' => wp_get_current_user()->user_email,
        'user_login' => wp_get_current_user()->user_login,
        'user_id' => get_current_user_id(),
        'level_name' => 'Gold Plan',
        'level_id' => 1,
        'amount' => 9.00,
        'formatted_amount' => '$9.00',
        'due_today' => 9.00,
        'formatted_due' => '$9.00',
        'invoice_id' => 'in_test_12345',
        'billing_reason' => 'subscription_create',
        'coupon_code' => 'SAVE80',
        'savings' => 40.00,
        'formatted_savings' => '$40.00',
        'discount_summary' => 'Discount SAVE80 applied: -$40.00',
        'trial_summary' => 'Paid trial: 14 days ($9.00 due today)',
        'recurring_summary' => 'Recurring discount: $5.00 off each renewal',
    ),
    'invoice_admin' => array(
        'user_name' => wp_get_current_user()->display_name,
        'user_email' => wp_get_current_user()->user_email,
        'user_login' => wp_get_current_user()->user_login,
        'user_id' => get_current_user_id(),
        'level_name' => 'Gold Plan',
        'level_id' => 1,
        'amount' => 9.00,
        'formatted_amount' => '$9.00',
        'invoice_id' => 'in_test_12345',
        'billing_reason' => 'subscription_create',
        'discount_summary' => 'Discount SAVE80 applied: -$40.00',
        'trial_summary' => 'Paid trial: 14 days ($9.00 due today)',
        'recurring_summary' => 'Recurring discount: $5.00 off each renewal',
        'order_url' => admin_url( 'admin.php?page=khm-orders' ),
    ),
    'renewal' => array(
        'user_name' => wp_get_current_user()->display_name,
        'user_email' => wp_get_current_user()->user_email,
        'user_login' => wp_get_current_user()->user_login,
        'user_id' => get_current_user_id(),
        'level_name' => 'Gold Plan',
        'level_id' => 1,
        'amount' => 49.00,
        'formatted_amount' => '$49.00',
        'due_today' => 49.00,
        'formatted_due' => '$49.00',
        'invoice_id' => 'in_test_67890',
        'billing_reason' => 'subscription_cycle',
        'discount_summary' => 'Recurring discount: $5.00 off each renewal',
        'recurring_summary' => 'Recurring discount: $5.00 off each renewal',
    ),
    'renewal_admin' => array(
        'user_name' => wp_get_current_user()->display_name,
        'user_email' => wp_get_current_user()->user_email,
        'user_login' => wp_get_current_user()->user_login,
        'user_id' => get_current_user_id(),
        'level_name' => 'Gold Plan',
        'level_id' => 1,
        'amount' => 49.00,
        'formatted_amount' => '$49.00',
        'invoice_id' => 'in_test_67890',
        'billing_reason' => 'subscription_cycle',
        'discount_summary' => 'Recurring discount: $5.00 off each renewal',
        'recurring_summary' => 'Recurring discount: $5.00 off each renewal',
        'order_url' => admin_url( 'admin.php?page=khm-orders' ),
    ),
    'membership_expiring' => array(
        'name' => wp_get_current_user()->display_name,
        'enddate' => gmdate( 'Y-m-d', strtotime( '+3 days' ) ),
    ),
    'membership_expired' => array(
        'name' => wp_get_current_user()->display_name,
        'enddate' => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
    ),
    'default' => array(
        'sitename' => get_bloginfo( 'name' ),
        'siteurl' => home_url(),
    ),
);

$default_json = isset( $sample_data[ $selected_template ] ) ? wp_json_encode( $sample_data[ $selected_template ], JSON_PRETTY_PRINT ) : wp_json_encode( $sample_data['default'], JSON_PRETTY_PRINT );
$data_json    = isset( $_POST['khm_data_json'] ) ? wp_unslash( $_POST['khm_data_json'] ) : $default_json;

// Helper: compose email data from an order
if ( ! function_exists( 'khm_compose_email_data_from_order' ) ) {
    function khm_compose_email_data_from_order( int $order_id, string $template ): ?array {
        $order_repo = new \KHM\Services\OrderRepository();
        $order      = $order_repo->find( $order_id );
        if ( ! $order ) {
            return null;
        }

        $user = get_userdata( (int) $order->user_id );
        $level_name = 'Membership';
        if ( ! empty( $order->membership_id ) ) {
            $level_repo = new \KHM\Services\LevelRepository();
            $level      = $level_repo->get( (int) $order->membership_id );
            $level_name = $level ? $level->name : 'Membership';
        }

        // Summaries from stored metadata
        $discount_summary = '';
        if ( ! empty( $order->discount_code ) && (float) ( $order->discount_amount ?? 0 ) > 0 ) {
            $discount_summary = sprintf( __( 'Discount %1$s applied: -$%2$s', 'khm-membership' ), esc_html( $order->discount_code ), number_format( (float) $order->discount_amount, 2 ) );
        }

        $trial_summary = '';
        if ( ! empty( $order->trial_days ) ) {
            $trial_amount = (float) ( $order->trial_amount ?? 0.0 );
            $trial_summary = $trial_amount > 0
                ? sprintf( __( 'Paid trial: %d days ($%s due today)', 'khm-membership' ), (int) $order->trial_days, number_format( $trial_amount, 2 ) )
                : sprintf( __( 'Free trial: %d days', 'khm-membership' ), (int) $order->trial_days );
        }

        $recurring_summary = '';
        if ( ! empty( $order->recurring_discount_type ) && (float) ( $order->recurring_discount_amount ?? 0 ) > 0 ) {
            if ( $order->recurring_discount_type === 'percent' ) {
                $recurring_summary = sprintf( __( 'Recurring discount: %s%% off each renewal', 'khm-membership' ), number_format( (float) $order->recurring_discount_amount, 2 ) );
            } else {
                $recurring_summary = sprintf( __( 'Recurring discount: $%s off each renewal', 'khm-membership' ), number_format( (float) $order->recurring_discount_amount, 2 ) );
            }
        }

        $base = array(
            'user_name'         => $user ? $user->display_name : __( 'Member', 'khm-membership' ),
            'user_email'        => $user ? $user->user_email : get_option( 'admin_email' ),
            'user_login'        => $user ? $user->user_login : 'user',
            'user_id'           => (int) ( $order->user_id ?? 0 ),
            'level_name'        => $level_name,
            'level_id'          => (int) ( $order->membership_id ?? 0 ),
            'amount'            => (float) ( $order->total ?? 0 ),
            'formatted_amount'  => '$' . number_format( (float) ( $order->total ?? 0 ), 2 ),
            'due_today'         => (float) ( $order->total ?? 0 ),
            'formatted_due'     => '$' . number_format( (float) ( $order->total ?? 0 ), 2 ),
            'invoice_id'        => (string) ( $order->payment_transaction_id ?? $order->code ),
            'billing_reason'    => 'subscription_create',
            'discount_summary'  => $discount_summary,
            'trial_summary'     => $trial_summary,
            'recurring_summary' => $recurring_summary,
            'account_url'       => home_url( '/account/' ),
            'order_url'         => admin_url( 'admin.php?page=khm-orders&action=view&id=' . (int) $order->id ),
            'sitename'          => get_bloginfo( 'name' ),
            'siteurl'           => home_url(),
            'date'              => gmdate( 'Y-m-d H:i:s' ),
        );

        switch ( $template ) {
            case 'checkout_paid':
                return array(
                    'name'               => $base['user_name'],
                    'membership_level'   => $base['level_name'],
                    'amount'             => $base['amount'],
                    'due_today'          => $base['due_today'],
                    'discount_summary'   => $base['discount_summary'],
                    'trial_summary'      => $base['trial_summary'],
                    'recurring_summary'  => $base['recurring_summary'],
                );
            case 'invoice':
            case 'invoice_admin':
            case 'renewal':
            case 'renewal_admin':
            default:
                return $base;
        }
    }
}

// If linked from Orders list with an order_id, prefill JSON unless user already edited it
if ( $order_id_param && empty( $_POST['khm_data_json'] ) ) {
    $maybe = khm_compose_email_data_from_order( $order_id_param, $selected_template );
    if ( is_array( $maybe ) ) {
        $data_json = wp_json_encode( $maybe, JSON_PRETTY_PRINT );
    }
}

$preview_html = '';
$notice       = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['khm_email_preview_nonce'] ) ) {
    check_admin_referer( 'khm_email_preview', 'khm_email_preview_nonce' );

    $decoded = json_decode( stripslashes( $data_json ), true );
    if ( ! is_array( $decoded ) ) {
        $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Invalid JSON data. Please correct and try again.', 'khm-membership' ) . '</p></div>';
    } else {
        if ( 'preview' === $action ) {
            $preview_html = $email->render( $selected_template, $decoded );
        } elseif ( 'send' === $action ) {
            if ( empty( $recipient ) ) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Recipient email is required to send.', 'khm-membership' ) . '</p></div>';
            } else {
                $sent = $email->send( $selected_template, $recipient, $decoded );
                if ( $sent ) {

        // Seed from most recent order by default when no order_id provided and not Posting
        if ( ! $order_id_param && empty( $_POST ) ) {
            $latest_id = khm_get_most_recent_order_id();
            if ( $latest_id ) {
                $order_id_param = $latest_id;
                $maybe          = khm_compose_email_data_from_order( $order_id_param, $selected_template );
                if ( is_array( $maybe ) ) {
                    $data_json = wp_json_encode( $maybe, JSON_PRETTY_PRINT );
                }
            }
        }
                    $notice = '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Email "%1$s" sent to %2$s.', 'khm-membership' ), esc_html( $selected_template ), esc_html( $recipient ) ) . '</p></div>';
                } else {
                    $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Email failed to send. Check logs and settings.', 'khm-membership' ) . '</p></div>';
                }
            }
        } elseif ( 'load_order' === $action ) {
            $oid = isset( $_POST['khm_order_id'] ) ? absint( $_POST['khm_order_id'] ) : 0;
            if ( $oid ) {
                $from_order = khm_compose_email_data_from_order( $oid, $selected_template );
                if ( is_array( $from_order ) ) {
                    $data_json = wp_json_encode( $from_order, JSON_PRETTY_PRINT );
                    $notice    = '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Loaded data from Order #%d.', 'khm-membership' ), $oid ) . '</p></div>';
                } else {
                    $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Order not found.', 'khm-membership' ) . '</p></div>';
                }
            }
        } elseif ( 'load_sample' === $action ) {
            $data_json = $default_json;
            $notice    = '<div class="notice notice-success"><p>' . esc_html__( 'Loaded sample data for template.', 'khm-membership' ) . '</p></div>';
        }
    }
}

?>
<div class="wrap khm-email-preview">
    <h1><?php esc_html_e( 'Email Preview', 'khm-membership' ); ?></h1>

    <?php if ( $notice ) { echo $notice; } ?>

    <form method="post">
        <?php wp_nonce_field( 'khm_email_preview', 'khm_email_preview_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="khm_order_id"><?php esc_html_e( 'Order ID (optional)', 'khm-membership' ); ?></label></th>
                    <td>
                        <input type="number" class="small-text" name="khm_order_id" id="khm_order_id" value="<?php echo esc_attr( $order_id_param ); ?>" />
                        <button type="submit" class="button" name="khm_action" value="load_order"><?php esc_html_e( 'Load from Order', 'khm-membership' ); ?></button>
                        <button type="submit" class="button" name="khm_action" value="load_sample"><?php esc_html_e( 'Load Sample Data', 'khm-membership' ); ?></button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="khm_template"><?php esc_html_e( 'Template', 'khm-membership' ); ?></label></th>
                    <td>
                        <select name="khm_template" id="khm_template">
                            <?php foreach ( $templates as $slug ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected_template, $slug ); ?>><?php echo esc_html( $slug ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="khm_recipient"><?php esc_html_e( 'Recipient (for Send)', 'khm-membership' ); ?></label></th>
                    <td>
                        <input type="email" class="regular-text" name="khm_recipient" id="khm_recipient" value="<?php echo esc_attr( $recipient ); ?>" placeholder="name@example.com" />
                        <p class="description"><?php esc_html_e( 'Optional for preview. Required to send.', 'khm-membership' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="khm_data_json"><?php esc_html_e( 'Template Data (JSON)', 'khm-membership' ); ?></label></th>
                    <td>
                        <textarea name="khm_data_json" id="khm_data_json" rows="14" class="large-text code"><?php echo esc_textarea( $data_json ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Customize variables to preview or send. Invalid JSON will be rejected.', 'khm-membership' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-secondary" name="khm_action" value="preview"><?php esc_html_e( 'Preview', 'khm-membership' ); ?></button>
            <button type="submit" class="button button-primary" name="khm_action" value="send"><?php esc_html_e( 'Send Test Email', 'khm-membership' ); ?></button>
        </p>
    </form>

    <?php if ( $preview_html ) : ?>
        <hr />
        <h2><?php esc_html_e( 'Preview', 'khm-membership' ); ?></h2>
        <div class="khm-email-preview-frame" style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:800px;">
            <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview html comes from sanitized templates and replaceVariables. ?>
        </div>
    <?php endif; ?>
</div>
