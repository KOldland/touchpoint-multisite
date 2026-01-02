<?php
/**
 * Discount Codes Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$codes = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}khm_discount_codes ORDER BY id DESC LIMIT 50"
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Discount Codes', 'khm-membership'); ?></h1>
    <a href="#" class="page-title-action" id="khm-add-code">
        <?php esc_html_e('Add Discount Code', 'khm-membership'); ?>
    </a>
    
    <hr class="wp-header-end">

    <?php if (!empty($codes)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Code', 'khm-membership'); ?></th>
                    <th><?php esc_html_e('Discount', 'khm-membership'); ?></th>
                    <th><?php esc_html_e('Start Date', 'khm-membership'); ?></th>
                    <th><?php esc_html_e('Expires', 'khm-membership'); ?></th>
                    <th><?php esc_html_e('Uses', 'khm-membership'); ?></th>
                    <th><?php esc_html_e('Actions', 'khm-membership'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($codes as $code): ?>
                <tr>
                    <td><strong><?php echo esc_html($code->code); ?></strong></td>
                    <td>
                        <?php
                        if ($code->type === 'percent') {
                            echo esc_html($code->value . '%');
                        } else {
                            echo esc_html(khm_format_price($code->value));
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($code->start_date) {
                            echo esc_html(date_i18n(get_option('date_format'), strtotime($code->start_date)));
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($code->end_date) {
                            $expired = strtotime($code->end_date) < time();
                            if ($expired) {
                                echo '<span class="khm-expired">' . esc_html(date_i18n(get_option('date_format'), strtotime($code->end_date))) . '</span>';
                            } else {
                                echo esc_html(date_i18n(get_option('date_format'), strtotime($code->end_date)));
                            }
                        } else {
                            esc_html_e('Never', 'khm-membership');
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        $uses = (int) $code->times_used;
                        echo esc_html($uses);
                        if (!is_null($code->usage_limit) && $code->usage_limit > 0) {
                            echo ' / ' . esc_html((int) $code->usage_limit);
                        }
                        ?>
                    </td>
                    <td>
                        <a href="#" class="button"><?php esc_html_e('Edit', 'khm-membership'); ?></a>
                        <a href="#" class="button"><?php esc_html_e('Delete', 'khm-membership'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><?php esc_html_e('No discount codes found. Create your first code to offer special pricing.', 'khm-membership'); ?></p>
    <?php endif; ?>

    <div class="khm-help-box">
        <h3><?php esc_html_e('About Discount Codes', 'khm-membership'); ?></h3>
        <p><?php esc_html_e('Discount codes allow you to offer special pricing to customers. You can set percentage or fixed amount discounts, expiration dates, and usage limits.', 'khm-membership'); ?></p>
    </div>
</div>

<style>
.khm-expired {
    color: #d63638;
    font-weight: 600;
}

.khm-help-box {
    background: #f0f6fc;
    border-left: 4px solid #2271b1;
    padding: 15px 20px;
    margin-top: 30px;
}

.khm-help-box h3 {
    margin-top: 0;
}
</style>
