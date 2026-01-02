<?php
/**
 * Dashboard Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get stats
$total_members = $wpdb->get_var(
    "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}khm_memberships_users WHERE status = 'active'"
);

$total_orders = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}khm_membership_orders"
);

$revenue_today = $wpdb->get_var(
    "SELECT SUM(total) FROM {$wpdb->prefix}khm_membership_orders 
     WHERE status = 'success' AND DATE(timestamp) = CURDATE()"
);

$revenue_month = $wpdb->get_var(
    "SELECT SUM(total) FROM {$wpdb->prefix}khm_membership_orders 
     WHERE status = 'success' AND MONTH(timestamp) = MONTH(CURDATE()) AND YEAR(timestamp) = YEAR(CURDATE())"
);

$recent_orders = $wpdb->get_results(
    "SELECT o.*, u.user_login, u.display_name 
     FROM {$wpdb->prefix}khm_membership_orders o
     LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID
     ORDER BY o.timestamp DESC
     LIMIT 10"
);

$expiring_soon = $wpdb->get_results(
    "SELECT m.*, m.enddate AS end_date, u.user_login, u.display_name 
     FROM {$wpdb->prefix}khm_memberships_users m
     LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
     WHERE m.status = 'active' 
     AND m.enddate IS NOT NULL 
     AND m.enddate BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
     ORDER BY m.enddate ASC
     LIMIT 10"
);
?>

<div class="wrap">
    <h1><?php esc_html_e('KHM Membership Dashboard', 'khm-membership'); ?></h1>

    <!-- Stats Cards -->
    <div class="khm-stats-grid">
        <div class="khm-stat-card">
            <div class="khm-stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="khm-stat-content">
                <div class="khm-stat-value"><?php echo number_format($total_members); ?></div>
                <div class="khm-stat-label"><?php esc_html_e('Active Members', 'khm-membership'); ?></div>
            </div>
        </div>

        <div class="khm-stat-card">
            <div class="khm-stat-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="khm-stat-content">
                <div class="khm-stat-value"><?php echo number_format($total_orders); ?></div>
                <div class="khm-stat-label"><?php esc_html_e('Total Orders', 'khm-membership'); ?></div>
            </div>
        </div>

        <div class="khm-stat-card">
            <div class="khm-stat-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="khm-stat-content">
                <div class="khm-stat-value"><?php echo khm_format_price($revenue_today ?: 0); ?></div>
                <div class="khm-stat-label"><?php esc_html_e('Revenue Today', 'khm-membership'); ?></div>
            </div>
        </div>

        <div class="khm-stat-card">
            <div class="khm-stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="khm-stat-content">
                <div class="khm-stat-value"><?php echo khm_format_price($revenue_month ?: 0); ?></div>
                <div class="khm-stat-label"><?php esc_html_e('Revenue This Month', 'khm-membership'); ?></div>
            </div>
        </div>
    </div>

    <div class="khm-dashboard-grid">
        <!-- Recent Orders -->
        <div class="khm-dashboard-widget">
            <h2><?php esc_html_e('Recent Orders', 'khm-membership'); ?></h2>
            <?php if (!empty($recent_orders)): ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Order', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('User', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Amount', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Status', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Date', 'khm-membership'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=khm-orders&action=view&id=' . $order->id)); ?>">
                                    <?php echo esc_html($order->code); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($order->display_name ?: $order->user_login); ?></td>
                            <td><?php echo esc_html(khm_format_price($order->total)); ?></td>
                            <td>
                                <span class="khm-badge khm-status-<?php echo esc_attr($order->status); ?>">
                                    <?php echo esc_html(ucfirst($order->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($order->timestamp))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="khm-widget-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=khm-orders')); ?>">
                        <?php esc_html_e('View All Orders →', 'khm-membership'); ?>
                    </a>
                </p>
            <?php else: ?>
                <p><?php esc_html_e('No orders yet.', 'khm-membership'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Expiring Soon -->
        <div class="khm-dashboard-widget">
            <h2><?php esc_html_e('Expiring in Next 7 Days', 'khm-membership'); ?></h2>
            <?php if (!empty($expiring_soon)): ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('User', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('End Date', 'khm-membership'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring_soon as $membership): ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(get_edit_user_link($membership->user_id)); ?>">
                                    <?php echo esc_html($membership->display_name ?: $membership->user_login); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($membership->end_date))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="khm-widget-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=khm-members&status=expiring')); ?>">
                        <?php esc_html_e('View All Expiring →', 'khm-membership'); ?>
                    </a>
                </p>
            <?php else: ?>
                <p><?php esc_html_e('No memberships expiring soon.', 'khm-membership'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="khm-dashboard-actions">
        <h2><?php esc_html_e('Quick Actions', 'khm-membership'); ?></h2>
        <div class="khm-actions-grid">
            <a href="<?php echo esc_url(admin_url('user-new.php')); ?>" class="khm-action-card">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e('Add New Member', 'khm-membership'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=khm-levels')); ?>" class="khm-action-card">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e('Manage Levels', 'khm-membership'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=khm-discount-codes')); ?>" class="khm-action-card">
                <span class="dashicons dashicons-tag"></span>
                <?php esc_html_e('Discount Codes', 'khm-membership'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=khm-settings')); ?>" class="khm-action-card">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e('Settings', 'khm-membership'); ?>
            </a>
        </div>
    </div>
</div>

<style>
.khm-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0 30px;
}

.khm-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.khm-stat-icon {
    font-size: 40px;
    color: #2271b1;
}

.khm-stat-icon .dashicons {
    width: 50px;
    height: 50px;
    font-size: 50px;
}

.khm-stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #2271b1;
}

.khm-stat-label {
    color: #646970;
    font-size: 14px;
}

.khm-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.khm-dashboard-widget {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
}

.khm-dashboard-widget h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
}

.khm-dashboard-widget .widefat {
    margin-top: 15px;
}

.khm-widget-footer {
    margin-top: 15px;
    text-align: right;
    margin-bottom: 0;
}

.khm-dashboard-actions {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
}

.khm-dashboard-actions h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
}

.khm-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.khm-action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px 20px;
    background: #f0f6fc;
    border: 2px solid #2271b1;
    border-radius: 4px;
    text-decoration: none;
    color: #2271b1;
    font-weight: 600;
    transition: all 0.2s ease;
}

.khm-action-card:hover {
    background: #2271b1;
    color: #fff;
}

.khm-action-card .dashicons {
    font-size: 40px;
    width: 40px;
    height: 40px;
    margin-bottom: 10px;
}

@media (max-width: 782px) {
    .khm-stats-grid,
    .khm-dashboard-grid,
    .khm-actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>
