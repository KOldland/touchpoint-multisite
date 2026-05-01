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
    if (!current_user_can('manage_options')) {
        error_log(sprintf('unauthorized_admin_access user_id=%d resource=%s', (int) get_current_user_id(), 'khm-settings-save'));
        wp_die(__('You do not have permission to perform this action.', 'khm-membership'));
    }
    
    // Gateway settings: only non-secret keys are stored in options.
    $stripe_publishable_input = sanitize_text_field($_POST['khm_stripe_publishable_key'] ?? '');
    update_option('khm_stripe_publishable_key', $stripe_publishable_input);
    update_option('khm_stripe_keys_are_set', isset($_POST['khm_stripe_keys_are_set']) ? 1 : 0);

    if ($stripe_publishable_input !== '' && !preg_match('/^pk_(test|live)_/i', $stripe_publishable_input)) {
        add_settings_error('khm_messages', 'khm_stripe_publishable_format', __('Stripe Publishable Key format looks invalid. Expected pk_test_... or pk_live_....', 'khm-membership'));
    }

    // Remove any legacy secret options so keys do not persist in DB.
    if (function_exists('delete_option')) {
        delete_option('khm_stripe_secret_key');
        delete_option('khm_stripe_webhook_secret');
        delete_option('khm_stripe_webhook_secret_marketing');
        delete_option('khm_stripe_webhook_secret_billing');
    }

    // Webhook rate limiting settings.
    update_option('khm_webhook_rate_limit_per_minute', max(1, intval($_POST['khm_webhook_rate_limit_per_minute'] ?? 60)));
    update_option('khm_webhook_badsig_threshold', max(1, intval($_POST['khm_webhook_badsig_threshold'] ?? 10)));
    update_option('khm_webhook_badsig_window', max(1, intval($_POST['khm_webhook_badsig_window'] ?? 60)));
    update_option('khm_webhook_block_base_ttl', max(1, intval($_POST['khm_webhook_block_base_ttl'] ?? 60)));
    update_option('khm_webhook_block_max_ttl', max(1, intval($_POST['khm_webhook_block_max_ttl'] ?? 3600)));
    
    // Email settings
    update_option('khm_email_from_name', sanitize_text_field($_POST['khm_email_from_name'] ?? ''));
    update_option('khm_email_from_address', sanitize_email($_POST['khm_email_from_address'] ?? ''));
    update_option('khm_membership_transactional_emails_enabled', isset($_POST['khm_membership_transactional_emails_enabled']) ? 1 : 0);
    
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
    
    // LinkedIn API credentials
    $li_client_id = sanitize_text_field( $_POST['khm_linkedin_client_id'] ?? '' );
    update_option( 'khm_linkedin_client_id', $li_client_id );
    // Only overwrite secret if a non-empty value was submitted.
    if ( ! empty( $_POST['khm_linkedin_client_secret'] ) ) {
        update_option( 'khm_linkedin_client_secret', sanitize_text_field( $_POST['khm_linkedin_client_secret'] ) );
    }

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
        error_log(sprintf('unauthorized_admin_access user_id=%d resource=%s', (int) get_current_user_id(), 'khm-settings-run-daily'));
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
$stripe_secret = function_exists('khm_get_stripe_secret') ? (string) (khm_get_stripe_secret('KH_STRIPE_SECRET_KEY') ?? '') : '';
$stripe_publishable = get_option('khm_stripe_publishable_key', '');
$stripe_webhook = function_exists('khm_get_stripe_secret') ? (string) (khm_get_stripe_secret('KH_STRIPE_WEBHOOK_SECRET') ?? '') : '';
$stripe_webhook_marketing = function_exists('khm_get_stripe_secret') ? (string) (khm_get_stripe_secret('KH_STRIPE_WEBHOOK_SECRET_MARKETING') ?? '') : '';
$stripe_webhook_billing = function_exists('khm_get_stripe_secret') ? (string) (khm_get_stripe_secret('KH_STRIPE_WEBHOOK_SECRET_BILLING') ?? '') : '';
$webhook_endpoint = rest_url('khm/v1/webhooks/stripe');
$webhook_endpoint_marketing = rest_url('khm/v1/webhooks/stripe/marketing');
$webhook_endpoint_billing = rest_url('khm/v1/webhooks/stripe/billing');
$webhook_rate_limit_per_minute = (int) get_option('khm_webhook_rate_limit_per_minute', 60);
$webhook_badsig_threshold = (int) get_option('khm_webhook_badsig_threshold', 10);
$webhook_badsig_window = (int) get_option('khm_webhook_badsig_window', 60);
$webhook_block_base_ttl = (int) get_option('khm_webhook_block_base_ttl', 60);
$webhook_block_max_ttl = (int) get_option('khm_webhook_block_max_ttl', 3600);
$stripe_keys_are_set = (bool) get_option('khm_stripe_keys_are_set', 0);
$stripe_secret_mode = '';
if (strpos($stripe_secret, 'sk_test_') === 0) {
    $stripe_secret_mode = 'test';
} elseif (strpos($stripe_secret, 'sk_live_') === 0) {
    $stripe_secret_mode = 'live';
}
$stripe_publishable_mode = '';
if (strpos($stripe_publishable, 'pk_test_') === 0) {
    $stripe_publishable_mode = 'test';
} elseif (strpos($stripe_publishable, 'pk_live_') === 0) {
    $stripe_publishable_mode = 'live';
}
$has_secret_key = is_string($stripe_secret) && $stripe_secret !== '';
$has_publishable_key = is_string($stripe_publishable) && $stripe_publishable !== '';
$has_webhook_secret = is_string($stripe_webhook) && $stripe_webhook !== '';
$has_webhook_secret_marketing = is_string($stripe_webhook_marketing) && $stripe_webhook_marketing !== '';
$has_webhook_secret_billing = is_string($stripe_webhook_billing) && $stripe_webhook_billing !== '';
$keys_mode_match = ($stripe_secret_mode !== '' && $stripe_secret_mode === $stripe_publishable_mode);
$webhook_ready = $has_secret_key && $has_publishable_key && $has_webhook_secret;
$webhook_ready_split = $has_secret_key && $has_publishable_key && $has_webhook_secret_marketing && $has_webhook_secret_billing;
$email_from_name = get_option('khm_email_from_name', get_bloginfo('name'));
$email_from_address = get_option('khm_email_from_address', get_option('admin_email'));
$transactional_emails_enabled = (bool) get_option('khm_membership_transactional_emails_enabled', false);
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
                    <?php esc_html_e('Stripe Secret Key', 'khm-membership'); ?>
                </th>
                <td>
                    <strong><?php echo $has_secret_key ? esc_html__('Configured via environment', 'khm-membership') : esc_html__('Missing', 'khm-membership'); ?></strong>
                    <p class="description"><?php esc_html_e('Set KH_STRIPE_SECRET_KEY as an environment variable or wp-config constant. Secret values are not stored in WordPress options.', 'khm-membership'); ?></p>
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
                    <?php esc_html_e('Stripe Webhook Secret', 'khm-membership'); ?>
                </th>
                <td>
                    <strong><?php echo $has_webhook_secret ? esc_html__('Configured via environment', 'khm-membership') : esc_html__('Missing', 'khm-membership'); ?></strong>
                    <p class="description">
                        <?php
                        printf(
                            esc_html__('Set KH_STRIPE_WEBHOOK_SECRET in deployment secrets. Webhook URL: %s', 'khm-membership'),
                            '<code>' . esc_url($webhook_endpoint) . '</code>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e('Stripe Marketing Webhook Secret', 'khm-membership'); ?>
                </th>
                <td>
                    <strong><?php echo $has_webhook_secret_marketing ? esc_html__('Configured via environment', 'khm-membership') : esc_html__('Optional / missing', 'khm-membership'); ?></strong>
                    <p class="description">
                        <?php
                        printf(
                            esc_html__('Optional KH_STRIPE_WEBHOOK_SECRET_MARKETING for marketing endpoint: %s', 'khm-membership'),
                            '<code>' . esc_url($webhook_endpoint_marketing) . '</code>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e('Stripe Billing Webhook Secret', 'khm-membership'); ?>
                </th>
                <td>
                    <strong><?php echo $has_webhook_secret_billing ? esc_html__('Configured via environment', 'khm-membership') : esc_html__('Optional / missing', 'khm-membership'); ?></strong>
                    <p class="description">
                        <?php
                        printf(
                            esc_html__('Optional KH_STRIPE_WEBHOOK_SECRET_BILLING for billing endpoint: %s', 'khm-membership'),
                            '<code>' . esc_url($webhook_endpoint_billing) . '</code>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="khm_stripe_keys_are_set"><?php esc_html_e('Env Keys Confirmed', 'khm-membership'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="khm_stripe_keys_are_set" name="khm_stripe_keys_are_set" value="1" <?php checked($stripe_keys_are_set, true); ?> />
                        <?php esc_html_e('Staging/dev confirmation only. This flag does not store secrets.', 'khm-membership'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('Stripe Webhook Sync Readiness', 'khm-membership'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Status', 'khm-membership'); ?></th>
                <td>
                    <strong><?php echo $webhook_ready ? esc_html__('Ready', 'khm-membership') : esc_html__('Needs setup', 'khm-membership'); ?></strong>
                    <?php if (!$webhook_ready) : ?>
                        <p class="description"><?php esc_html_e('Set Stripe env vars/constants and save this page to refresh checks.', 'khm-membership'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Secret Key', 'khm-membership'); ?></th>
                <td><?php echo $has_secret_key ? esc_html__('Configured', 'khm-membership') : esc_html__('Missing', 'khm-membership'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Publishable Key', 'khm-membership'); ?></th>
                <td><?php echo $has_publishable_key ? esc_html__('Configured', 'khm-membership') : esc_html__('Missing', 'khm-membership'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Webhook Secret', 'khm-membership'); ?></th>
                <td><?php echo $has_webhook_secret ? esc_html__('Configured', 'khm-membership') : esc_html__('Missing', 'khm-membership'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Marketing Webhook Secret', 'khm-membership'); ?></th>
                <td><?php echo $has_webhook_secret_marketing ? esc_html__('Configured', 'khm-membership') : esc_html__('Missing', 'khm-membership'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Billing Webhook Secret', 'khm-membership'); ?></th>
                <td><?php echo $has_webhook_secret_billing ? esc_html__('Configured', 'khm-membership') : esc_html__('Missing', 'khm-membership'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Key Mode Match', 'khm-membership'); ?></th>
                <td>
                    <?php
                    if (!$has_secret_key || !$has_publishable_key) {
                        echo esc_html__('Unknown (missing key)', 'khm-membership');
                    } else {
                        echo $keys_mode_match ? esc_html__('Yes', 'khm-membership') : esc_html__('No (test/live mismatch)', 'khm-membership');
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Webhook Endpoint', 'khm-membership'); ?></th>
                <td><code><?php echo esc_url($webhook_endpoint); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Marketing Endpoint', 'khm-membership'); ?></th>
                <td><code><?php echo esc_url($webhook_endpoint_marketing); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Billing Endpoint', 'khm-membership'); ?></th>
                <td><code><?php echo esc_url($webhook_endpoint_billing); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Required Stripe Events', 'khm-membership'); ?></th>
                <td><code>Marketing: product.updated (optional product.created)</code><br><code>Billing: subscription/invoice/payment events</code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Split Endpoint Readiness', 'khm-membership'); ?></th>
                <td><?php echo $webhook_ready_split ? esc_html__('Ready', 'khm-membership') : esc_html__('Needs marketing+billing secrets', 'khm-membership'); ?></td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('Webhook Rate Limiting', 'khm-membership'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="khm_webhook_rate_limit_per_minute"><?php esc_html_e('Per-IP Requests/Minute', 'khm-membership'); ?></label></th>
                <td><input type="number" id="khm_webhook_rate_limit_per_minute" name="khm_webhook_rate_limit_per_minute" value="<?php echo esc_attr($webhook_rate_limit_per_minute); ?>" min="1" max="1000" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="khm_webhook_badsig_threshold"><?php esc_html_e('Bad Signature Threshold', 'khm-membership'); ?></label></th>
                <td><input type="number" id="khm_webhook_badsig_threshold" name="khm_webhook_badsig_threshold" value="<?php echo esc_attr($webhook_badsig_threshold); ?>" min="1" max="1000" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="khm_webhook_badsig_window"><?php esc_html_e('Bad Signature Window (sec)', 'khm-membership'); ?></label></th>
                <td><input type="number" id="khm_webhook_badsig_window" name="khm_webhook_badsig_window" value="<?php echo esc_attr($webhook_badsig_window); ?>" min="1" max="3600" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="khm_webhook_block_base_ttl"><?php esc_html_e('Initial Block TTL (sec)', 'khm-membership'); ?></label></th>
                <td><input type="number" id="khm_webhook_block_base_ttl" name="khm_webhook_block_base_ttl" value="<?php echo esc_attr($webhook_block_base_ttl); ?>" min="1" max="3600" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="khm_webhook_block_max_ttl"><?php esc_html_e('Max Block TTL (sec)', 'khm-membership'); ?></label></th>
                <td><input type="number" id="khm_webhook_block_max_ttl" name="khm_webhook_block_max_ttl" value="<?php echo esc_attr($webhook_block_max_ttl); ?>" min="1" max="86400" /></td>
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
            <tr>
                <th scope="row">
                    <label for="khm_membership_transactional_emails_enabled"><?php esc_html_e('Transactional Membership Emails', 'khm-membership'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="khm_membership_transactional_emails_enabled" name="khm_membership_transactional_emails_enabled" value="1" <?php checked($transactional_emails_enabled, true); ?> />
                        <?php esc_html_e('Enable welcome and payment confirmation emails from membership webhooks.', 'khm-membership'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Safety toggle for staging/production rollout. Disable to stop webhook-triggered transactional sends immediately.', 'khm-membership'); ?>
                    </p>
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

    <hr>
    <h2><?php esc_html_e( 'LinkedIn API Credentials', 'khm-membership' ); ?></h2>
    <p style="color:#6b7280;font-size:13px"><?php esc_html_e( 'Required for sponsor LinkedIn post scheduling (S9). Create an application at developer.linkedin.com and add the redirect URI shown below.', 'khm-membership' ); ?></p>
    <?php
    $li_redirect = home_url( '/wp-json/khm/v1/social/linkedin/callback' );
    ?>
    <p style="font-size:12px"><strong><?php esc_html_e( 'Redirect URI to register in LinkedIn app:', 'khm-membership' ); ?></strong><br>
        <code><?php echo esc_html( $li_redirect ); ?></code>
    </p>
    <table class="form-table">
        <tr>
            <th><label for="khm_linkedin_client_id"><?php esc_html_e( 'Client ID', 'khm-membership' ); ?></label></th>
            <td><input type="text" id="khm_linkedin_client_id" name="khm_linkedin_client_id"
                       value="<?php echo esc_attr( get_option( 'khm_linkedin_client_id', '' ) ); ?>"
                       class="regular-text" autocomplete="off">
            </td>
        </tr>
        <tr>
            <th><label for="khm_linkedin_client_secret"><?php esc_html_e( 'Client Secret', 'khm-membership' ); ?></label></th>
            <td><input type="password" id="khm_linkedin_client_secret" name="khm_linkedin_client_secret"
                       value="" placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'khm-membership' ); ?>"
                       class="regular-text" autocomplete="new-password">
                <p class="description"><?php esc_html_e( 'The current secret is stored in the database. Enter a new value only to change it.', 'khm-membership' ); ?></p>
            </td>
        </tr>
    </table>

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
            <td><code><?php echo esc_url($webhook_endpoint); ?></code></td>
        </tr>
        <tr>
            <td><strong><?php esc_html_e('Marketing Webhook Endpoint:', 'khm-membership'); ?></strong></td>
            <td><code><?php echo esc_url($webhook_endpoint_marketing); ?></code></td>
        </tr>
        <tr>
            <td><strong><?php esc_html_e('Billing Webhook Endpoint:', 'khm-membership'); ?></strong></td>
            <td><code><?php echo esc_url($webhook_endpoint_billing); ?></code></td>
        </tr>
    </table>
</div>
