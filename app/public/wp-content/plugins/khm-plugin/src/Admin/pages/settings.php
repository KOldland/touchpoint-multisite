<?php
/**
 * Settings Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['khm_settings_submit'])) {
    check_admin_referer('khm_settings');
    
    // Gateway settings
    update_option('khm_stripe_secret_key', sanitize_text_field($_POST['khm_stripe_secret_key'] ?? ''));
    update_option('khm_stripe_publishable_key', sanitize_text_field($_POST['khm_stripe_publishable_key'] ?? ''));
    update_option('khm_stripe_webhook_secret', sanitize_text_field($_POST['khm_stripe_webhook_secret'] ?? ''));
    
    // Email settings
    update_option('khm_email_from_name', sanitize_text_field($_POST['khm_email_from_name'] ?? ''));
    update_option('khm_email_from_address', sanitize_email($_POST['khm_email_from_address'] ?? ''));
    
    // General settings
    update_option('khm_currency', sanitize_text_field($_POST['khm_currency'] ?? 'USD'));
    update_option('khm_tax_rate', floatval($_POST['khm_tax_rate'] ?? 0));

    // Cron settings
    $cron_enabled = isset($_POST['khm_cron_enabled']) ? (bool) $_POST['khm_cron_enabled'] : false;
    update_option('khm_cron_enabled', $cron_enabled);

    $cron_time = sanitize_text_field($_POST['khm_cron_time'] ?? '02:00');
    if (!preg_match('/^\d{2}:\d{2}$/', $cron_time)) {
        $cron_time = '02:00';
    }
    update_option('khm_cron_time', $cron_time);

    $warning_days = max(0, intval($_POST['khm_expiry_warning_days'] ?? 7));
    update_option('khm_expiry_warning_days', $warning_days);
    
    add_settings_error('khm_messages', 'khm_message', __('Settings saved.', 'khm-membership'), 'updated');

    // Re-evaluate scheduling after settings change
    if (class_exists('KHM\\Scheduled\\Scheduler')) {
        KHM\Scheduled\Scheduler::deactivate();
        KHM\Scheduled\Scheduler::activate();
    }
}
elseif (isset($_POST['khm_run_daily_now'])) {
    check_admin_referer('khm_settings');
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to perform this action.', 'khm-membership'));
    }
    if (class_exists('KHM\\Scheduled\\Tasks')) {
        $tasks = new KHM\Scheduled\Tasks();
        $result = $tasks->run_daily();
        $expired = isset($result['expired']) ? intval($result['expired']) : 0;
        $warned = isset($result['warned']) ? intval($result['warned']) : 0;
        $msg = sprintf(__('Daily tasks executed. Expired: %d, Warnings sent: %d', 'khm-membership'), $expired, $warned);
        add_settings_error('khm_messages', 'khm_run_now', $msg, 'updated');
    } else {
        add_settings_error('khm_messages', 'khm_run_now_missing', __('Daily tasks class not found.', 'khm-membership'));
    }
}

// Get current settings
$stripe_secret = get_option('khm_stripe_secret_key', '');
$stripe_publishable = get_option('khm_stripe_publishable_key', '');
$stripe_webhook = get_option('khm_stripe_webhook_secret', '');
$email_from_name = get_option('khm_email_from_name', get_bloginfo('name'));
$email_from_address = get_option('khm_email_from_address', get_option('admin_email'));
$currency = get_option('khm_currency', 'USD');
$tax_rate = get_option('khm_tax_rate', 0);
$cron_enabled_val = get_option('khm_cron_enabled', true);
$cron_time_val = get_option('khm_cron_time', '02:00');
$warning_days_val = get_option('khm_expiry_warning_days', 7);
$next_run_human = '—';
if (class_exists('KHM\\Scheduled\\Scheduler')) {
    $ts = wp_next_scheduled(KHM\Scheduled\Scheduler::HOOK_DAILY);
    if ($ts) {
        $dt = wp_date('Y-m-d H:i:s T', $ts);
        $next_run_human = esc_html($dt);
    } else {
        $next_run_human = esc_html__('Not scheduled', 'khm-membership');
    }
}
?>

<div class="wrap">
    <h1><?php esc_html_e('KHM Membership Settings', 'khm-membership'); ?></h1>

    <?php settings_errors('khm_messages'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('khm_settings'); ?>

        <h2 class="title"><?php esc_html_e('Gateway Settings', 'khm-membership'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="khm_stripe_secret_key"><?php esc_html_e('Stripe Secret Key', 'khm-membership'); ?></label>
                </th>
                <td>
                    <input type="text" id="khm_stripe_secret_key" name="khm_stripe_secret_key" 
                           value="<?php echo esc_attr($stripe_secret); ?>" class="regular-text" />
                    <p class="description">
                        <?php esc_html_e('Your Stripe secret key (sk_test_... or sk_live_...)', 'khm-membership'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="khm_stripe_publishable_key"><?php esc_html_e('Stripe Publishable Key', 'khm-membership'); ?></label>
                </th>
                <td>
                    <input type="text" id="khm_stripe_publishable_key" name="khm_stripe_publishable_key" 
                           value="<?php echo esc_attr($stripe_publishable); ?>" class="regular-text" />
                    <p class="description">
                        <?php esc_html_e('Your Stripe publishable key (pk_test_... or pk_live_...)', 'khm-membership'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="khm_stripe_webhook_secret"><?php esc_html_e('Stripe Webhook Secret', 'khm-membership'); ?></label>
                </th>
                <td>
                    <input type="text" id="khm_stripe_webhook_secret" name="khm_stripe_webhook_secret" 
                           value="<?php echo esc_attr($stripe_webhook); ?>" class="regular-text" />
                    <p class="description">
                        <?php
                        printf(
                            esc_html__('Your webhook signing secret. Webhook URL: %s', 'khm-membership'),
                            '<code>' . esc_url(rest_url('khm/v1/webhooks/stripe')) . '</code>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('Email Settings', 'khm-membership'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="khm_email_from_name"><?php esc_html_e('From Name', 'khm-membership'); ?></label>
                </th>
                <td>
                    <input type="text" id="khm_email_from_name" name="khm_email_from_name" 
                           value="<?php echo esc_attr($email_from_name); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="khm_email_from_address"><?php esc_html_e('From Email', 'khm-membership'); ?></label>
                </th>
                <td>
                    <input type="email" id="khm_email_from_address" name="khm_email_from_address" 
                           value="<?php echo esc_attr($email_from_address); ?>" class="regular-text" />
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('General Settings', 'khm-membership'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="khm_currency"><?php esc_html_e('Currency', 'khm-membership'); ?></label>
                </th>
                <td>
                    <select id="khm_currency" name="khm_currency">
                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD - US Dollar</option>
                        <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro</option>
                        <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound</option>
                        <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD - Canadian Dollar</option>
                        <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD - Australian Dollar</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="khm_tax_rate"><?php esc_html_e('Tax Rate (%)', 'khm-membership'); ?></label>
                </th>
                <td>
                    <input type="number" id="khm_tax_rate" name="khm_tax_rate" 
                           value="<?php echo esc_attr($tax_rate); ?>" step="0.01" min="0" max="100" />
                    <p class="description">
                        <?php esc_html_e('Default tax rate to apply to orders (e.g., 7.5 for 7.5%)', 'khm-membership'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('Scheduled Tasks', 'khm-membership'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="khm_cron_enabled"><?php esc_html_e('Enable Daily Tasks', 'khm-membership'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="khm_cron_enabled" name="khm_cron_enabled" value="1" <?php checked((bool)$cron_enabled_val, true); ?> />
                        <?php esc_html_e('Run daily maintenance (expirations, warning emails).', 'khm-membership'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="khm_cron_time"><?php esc_html_e('Daily Run Time', 'khm-membership'); ?></label>
                </th>
                <td>
                    <input type="time" id="khm_cron_time" name="khm_cron_time" value="<?php echo esc_attr($cron_time_val); ?>" />
                    <p class="description">
                        <?php esc_html_e('Time in site timezone to run daily tasks.', 'khm-membership'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="khm_expiry_warning_days"><?php esc_html_e('Expiration Warning (days)', 'khm-membership'); ?></label>
                </th>
                <td>
                    <input type="number" id="khm_expiry_warning_days" name="khm_expiry_warning_days" value="<?php echo esc_attr($warning_days_val); ?>" min="0" max="365" />
                    <p class="description">
                        <?php esc_html_e('Send a warning this many days before membership end date. Set 0 to disable.', 'khm-membership'); ?>
                    </p>
                </td>
            </tr>
        </table>

    <p><strong><?php esc_html_e('Next scheduled run:', 'khm-membership'); ?></strong> <?php echo $next_run_human; ?></p>

    <?php submit_button(__('Run Now', 'khm-membership'), 'secondary', 'khm_run_daily_now', false); ?>

        <?php submit_button(__('Save Settings', 'khm-membership'), 'primary', 'khm_settings_submit'); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('System Information', 'khm-membership'); ?></h2>
    <table class="widefat">
        <tr>
            <td><strong><?php esc_html_e('Plugin Version:', 'khm-membership'); ?></strong></td>
            <td>0.1.0</td>
        </tr>
        <tr>
            <td><strong><?php esc_html_e('WordPress Version:', 'khm-membership'); ?></strong></td>
            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
        </tr>
        <tr>
            <td><strong><?php esc_html_e('PHP Version:', 'khm-membership'); ?></strong></td>
            <td><?php echo esc_html(phpversion()); ?></td>
        </tr>
        <tr>
            <td><strong><?php esc_html_e('Webhook Endpoint:', 'khm-membership'); ?></strong></td>
            <td><code><?php echo esc_url(rest_url('khm/v1/webhooks/stripe')); ?></code></td>
        </tr>
    </table>
</div>
