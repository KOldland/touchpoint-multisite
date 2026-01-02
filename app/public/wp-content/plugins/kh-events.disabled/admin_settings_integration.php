<?php
/**
 * WordPress Admin Settings Integration for KH Events
 */

// Add admin menu
add_action('admin_menu', 'kh_events_add_admin_menu');
add_action('admin_init', 'kh_events_settings_init');

function kh_events_add_admin_menu() {
    add_menu_page(
        'KH Events Settings',
        'KH Events',
        'manage_options',
        'kh-events-settings',
        'kh_events_settings_page',
        'dashicons-calendar-alt',
        30
    );

    add_submenu_page(
        'kh-events-settings',
        'API Settings',
        'API Settings',
        'manage_options',
        'kh-events-api',
        'kh_events_api_settings_page'
    );

    add_submenu_page(
        'kh-events-settings',
        'Social Media',
        'Social Media',
        'manage_options',
        'kh-events-social',
        'kh_events_social_settings_page'
    );

    add_submenu_page(
        'kh-events-settings',
        'CRM Integration',
        'CRM Integration',
        'manage_options',
        'kh-events-crm',
        'kh_events_crm_settings_page'
    );

    add_submenu_page(
        'kh-events-settings',
        'Webhooks',
        'Webhooks',
        'manage_options',
        'kh-events-webhooks',
        'kh_events_webhooks_settings_page'
    );
}

function kh_events_settings_init() {
    register_setting('kh_events_api', 'kh_events_api_keys');
    register_setting('kh_events_social', 'kh_events_facebook_settings');
    register_setting('kh_events_social', 'kh_events_twitter_settings');
    register_setting('kh_events_social', 'kh_events_linkedin_settings');
    register_setting('kh_events_social', 'kh_events_instagram_settings');
    register_setting('kh_events_social', 'kh_events_social_general');
    register_setting('kh_events_crm', 'kh_events_hubspot_settings');
    register_setting('kh_events_crm', 'kh_events_crm_general');
    register_setting('kh_events_webhooks', 'kh_events_webhooks');
}

function kh_events_settings_page() {
    ?>
    <div class="wrap">
        <h1>KH Events - Production Settings</h1>
        <p>Configure your production integrations.</p>
        <h2>Setup Steps:</h2>
        <ol>
            <li><a href="admin.php?page=kh-events-api">Configure API Keys</a></li>
            <li><a href="admin.php?page=kh-events-social">Set up Social Media</a></li>
            <li><a href="admin.php?page=kh-events-crm">Configure CRM</a></li>
            <li><a href="admin.php?page=kh-events-webhooks">Set up Webhooks</a></li>
        </ol>
    </div>
    <?php
}

function kh_events_api_settings_page() {
    $api_keys = get_option('kh_events_api_keys', array());
    ?>
    <div class="wrap">
        <h1>API Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('kh_events_api'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Primary API Key</th>
                    <td>
                        <input type="password" name="kh_events_api_keys[primary]" value="<?php echo esc_attr($api_keys["primary"] ?? ''); ?>" class="regular-text">
                        <p class="description">Generated: kh_6da8ea3550440425b4a4da924d473bc0</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Mobile App API Key</th>
                    <td>
                        <input type="password" name="kh_events_api_keys[mobile]" value="<?php echo esc_attr($api_keys["mobile"] ?? ''); ?>" class="regular-text">
                        <p class="description">Generated: kh_955bcf1a0bd12ad47aa777bbf447e184</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Webhook Service API Key</th>
                    <td>
                        <input type="password" name="kh_events_api_keys[webhook]" value="<?php echo esc_attr($api_keys["webhook"] ?? ''); ?>" class="regular-text">
                        <p class="description">Generated: kh_fee552fc0e31553dc5edca3bd28d4e18</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function kh_events_social_settings_page() {
    $facebook_settings = get_option('kh_events_facebook_settings', array());
    $twitter_settings = get_option('kh_events_twitter_settings', array());
    $social_general = get_option('kh_events_social_general', array());
    ?>
    <div class="wrap">
        <h1>Social Media Integration</h1>
        <form method="post" action="options.php">
            <?php settings_fields('kh_events_social'); ?>
            <h3>Facebook Settings</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">App ID</th>
                    <td><input type="text" name="kh_events_facebook_settings[app_id]" value="<?php echo esc_attr($facebook_settings["app_id"] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">App Secret</th>
                    <td><input type="password" name="kh_events_facebook_settings[app_secret]" value="<?php echo esc_attr($facebook_settings["app_secret"] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Access Token</th>
                    <td><input type="password" name="kh_events_facebook_settings[access_token]" value="<?php echo esc_attr($facebook_settings["access_token"] ?? ''); ?>" class="regular-text"></td>
                </tr>
            </table>
            <h3>Twitter Settings</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">API Key</th>
                    <td><input type="password" name="kh_events_twitter_settings[api_key]" value="<?php echo esc_attr($twitter_settings["api_key"] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">API Secret</th>
                    <td><input type="password" name="kh_events_twitter_settings[api_secret]" value="<?php echo esc_attr($twitter_settings["api_secret"] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Access Token</th>
                    <td><input type="password" name="kh_events_twitter_settings[access_token]" value="<?php echo esc_attr($twitter_settings["access_token"] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Access Token Secret</th>
                    <td><input type="password" name="kh_events_twitter_settings[access_token_secret]" value="<?php echo esc_attr($twitter_settings["access_token_secret"] ?? ''); ?>" class="regular-text"></td>
                </tr>
            </table>
            <h3>General Settings</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Auto Post Events</th>
                    <td><input type="checkbox" name="kh_events_social_general[auto_post]" value="1" <?php checked(($social_general["auto_post"] ?? 0), 1); ?>></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function kh_events_crm_settings_page() {
    $hubspot_settings = get_option('kh_events_hubspot_settings', array());
    $crm_general = get_option('kh_events_crm_general', array());
    ?>
    <div class="wrap">
        <h1>CRM Integration Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('kh_events_crm'); ?>
            <h3>HubSpot Settings</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">API Key</th>
                    <td><input type="password" name="kh_events_hubspot_settings[api_key]" value="<?php echo esc_attr($hubspot_settings["api_key"] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Auto Sync Contacts</th>
                    <td><input type="checkbox" name="kh_events_crm_general[auto_sync_contacts]" value="1" <?php checked(($crm_general["auto_sync_contacts"] ?? 0), 1); ?>></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function kh_events_webhooks_settings_page() {
    $webhooks = get_option('kh_events_webhooks', array());
    ?>
    <div class="wrap">
        <h1>Webhook Settings</h1>
        <div id="webhook-list">
            <?php if (!empty($webhooks)): ?>
                <?php foreach ($webhooks as $index => $webhook): ?>
                    <div class="webhook-item" style="border: 1px solid #ddd; padding: 10px; margin: 10px 0;">
                        <h4><?php echo esc_html($webhook["name"] ?? 'Unnamed Webhook'); ?></h4>
                        <p><strong>URL:</strong> <?php echo esc_html($webhook["url"] ?? ''); ?></p>
                        <p><strong>Events:</strong> <?php echo esc_html(implode(', ', $webhook["events"] ?? array())); ?></p>
                        <button class="button delete-webhook" data-index="<?php echo $index; ?>">Delete</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No webhooks configured yet.</p>
            <?php endif; ?>
        </div>
        <h3>Add New Webhook</h3>
        <form id="add-webhook-form">
            <table class="form-table">
                <tr>
                    <th scope="row">Name</th>
                    <td><input type="text" id="webhook-name" class="regular-text" placeholder="e.g., Booking Notification Service"></td>
                </tr>
                <tr>
                    <th scope="row">URL</th>
                    <td><input type="url" id="webhook-url" class="regular-text" placeholder="https://api.example.com/webhooks/kh-events"></td>
                </tr>
                <tr>
                    <th scope="row">Events</th>
                    <td>
                        <label><input type="checkbox" class="webhook-events" value="event.created"> Event Created</label><br>
                        <label><input type="checkbox" class="webhook-events" value="booking.completed"> Booking Completed</label>
                    </td>
                </tr>
            </table>
            <button type="button" class="button button-primary" id="add-webhook">Add Webhook</button>
        </form>
        <script>
        jQuery(document).ready(function($) {
            $('#add-webhook').click(function() {
                var name = $('#webhook-name').val();
                var url = $('#webhook-url').val();
                var events = [];
                $('.webhook-events:checked').each(function() {
                    events.push($(this).val());
                });
                if (!name || !url || events.length === 0) {
                    alert('Please fill in all fields');
                    return;
                }
                $.post(ajaxurl, {
                    action: 'kh_events_add_webhook',
                    name: name,
                    url: url,
                    events: events,
                    nonce: kh_events_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error adding webhook');
                    }
                });
            });
            $('.delete-webhook').click(function() {
                var index = $(this).data('index');
                if (confirm('Are you sure you want to delete this webhook?')) {
                    $.post(ajaxurl, {
                        action: 'kh_events_delete_webhook',
                        index: index,
                        nonce: kh_events_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error deleting webhook');
                        }
                    });
                }
            });
        });
        </script>
    </div>
    <?php
}
?>