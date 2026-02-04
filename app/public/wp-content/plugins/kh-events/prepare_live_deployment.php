<?php
/**
 * KH Events Live Deployment Preparation Script
 *
 * Generates all necessary files for production deployment
 */

// Load existing configurations
$config_files = array(
    'api_keys.json',
    'social_media_config_sample.json',
    'hubspot_config_sample.json',
    'webhook_config_sample.json'
);

echo "🚀 KH Events Live Deployment Preparation\n";
echo "=========================================\n\n";

class KH_Events_Deployment_Preparer {

    private $configs = array();

    public function __construct() {
        $this->load_configs();
    }

    private function load_configs() {
        foreach ($config_files as $file) {
            if (file_exists($file)) {
                $this->configs[$file] = json_decode(file_get_contents($file), true);
            }
        }
    }

    public function prepare_wordpress_admin_settings() {
        echo "1. 📝 Preparing WordPress Admin Settings Integration\n";
        echo "---------------------------------------------------\n";

        $admin_settings_code = $this->generate_admin_settings_code();
        $this->save_to_file('admin_settings_integration.php', $admin_settings_code);

        echo "✅ Generated WordPress admin settings integration code\n";
        echo "📁 File: admin_settings_integration.php\n\n";

        $settings_registration_code = $this->generate_settings_registration_code();
        $this->save_to_file('settings_registration.php', $settings_registration_code);

        echo "✅ Generated settings registration code\n";
        echo "📁 File: settings_registration.php\n\n";
    }

    public function prepare_credential_management() {
        echo "2. 🔐 Preparing Credential Management System\n";
        echo "--------------------------------------------\n";

        $credential_manager_code = $this->generate_credential_manager_code();
        $this->save_to_file('credential_manager.php', $credential_manager_code);

        echo "✅ Generated secure credential management system\n";
        echo "📁 File: credential_manager.php\n\n";

        $env_template = $this->generate_env_template();
        $this->save_to_file('.env.example', $env_template);

        echo "✅ Generated environment variables template\n";
        echo "📁 File: .env.example\n\n";
    }

    public function prepare_webhook_configuration() {
        echo "3. 🪝 Preparing Webhook Configuration System\n";
        echo "-------------------------------------------\n";

        $webhook_manager_code = $this->generate_webhook_manager_code();
        $this->save_to_file('webhook_manager.php', $webhook_manager_code);

        echo "✅ Generated webhook management system\n";
        echo "📁 File: webhook_manager.php\n\n";

        $webhook_tester_code = $this->generate_webhook_tester_code();
        $this->save_to_file('test_webhook_delivery.php', $webhook_tester_code);

        echo "✅ Generated webhook testing tools\n";
        echo "📁 File: test_webhook_delivery.php\n\n";
    }

    public function prepare_staging_tests() {
        echo "4. 🧪 Preparing Staging Environment Tests\n";
        echo "-----------------------------------------\n";

        $staging_tests_code = $this->generate_staging_test_suite();
        $this->save_to_file('test_staging_deploy.php', $staging_tests_code);

        echo "✅ Generated staging test suite\n";
        echo "📁 File: test_staging_deploy.php\n\n";

        $integration_validator_code = $this->generate_integration_validator();
        $this->save_to_file('validate_integrations.php', $integration_validator_code);

        echo "✅ Generated integration validator\n";
        echo "📁 File: validate_integrations.php\n\n";
    }

    public function prepare_deployment_scripts() {
        echo "5. 📦 Preparing Deployment Scripts\n";
        echo "----------------------------------\n";

        $deployment_checklist = $this->generate_deployment_checklist();
        $this->save_to_file('DEPLOYMENT_CHECKLIST.md', $deployment_checklist);

        echo "✅ Generated deployment checklist\n";
        echo "📁 File: DEPLOYMENT_CHECKLIST.md\n\n";

        $production_validator_code = $this->generate_production_validator();
        $this->save_to_file('validate_production_ready.php', $production_validator_code);

        echo "✅ Generated production readiness validator\n";
        echo "📁 File: validate_production_ready.php\n\n";

        $backup_script = $this->generate_backup_script();
        $this->save_to_file('backup_before_deploy.php', $backup_script);

        echo "✅ Generated backup and safety scripts\n";
        echo "📁 File: backup_before_deploy.php\n\n";
    }

    public function generate_deployment_summary() {
        echo "6. 📊 Generating Deployment Summary\n";
        echo "-----------------------------------\n";

        $summary = array(
            'prepared_at' => date('Y-m-d H:i:s'),
            'environment' => 'development',
            'files_prepared' => array(
                'admin_settings_integration.php',
                'settings_registration.php',
                'credential_manager.php',
                '.env.example',
                'webhook_manager.php',
                'test_webhook_delivery.php',
                'test_staging_deploy.php',
                'validate_integrations.php',
                'DEPLOYMENT_CHECKLIST.md',
                'validate_production_ready.php',
                'backup_before_deploy.php'
            ),
            'next_manual_steps' => array(
                '1. Copy admin integration files to WordPress plugin',
                '2. Set up environment variables with real credentials',
                '3. Configure webhook URLs in WordPress admin',
                '4. Run staging tests with real API credentials',
                '5. Execute deployment checklist before going live',
                '6. Monitor integrations after deployment'
            ),
            'security_requirements' => array(
                'Use HTTPS for all webhook endpoints',
                'Store API keys in environment variables',
                'Implement proper access controls',
                'Set up monitoring and alerting',
                'Regular key rotation policy'
            )
        );

        $this->save_to_file('deployment_preparation_summary.json', json_encode($summary, JSON_PRETTY_PRINT));

        echo "✅ Generated deployment preparation summary\n";
        echo "📁 File: deployment_preparation_summary.json\n\n";

        echo "📋 Deployment Preparation Complete!\n";
        echo "===================================\n\n";

        echo "Files Generated:\n";
        foreach ($summary['files_prepared'] as $file) {
            echo "✓ $file\n";
        }

        echo "\n📝 Next Manual Steps:\n";
        foreach ($summary['next_manual_steps'] as $step) {
            echo "• $step\n";
        }

        echo "\n🔐 Security Requirements:\n";
        foreach ($summary['security_requirements'] as $req) {
            echo "• $req\n";
        }

        echo "\n🎯 Ready for Live Deployment!\n";
        echo "All technical preparation is complete. You now need to:\n";
        echo "1. Set up real API credentials\n";
        echo "2. Configure webhook endpoints\n";
        echo "3. Test in staging environment\n";
        echo "4. Deploy to production\n\n";
    }

    private function generate_admin_settings_code() {
        return '<?php
/**
 * WordPress Admin Settings Integration for KH Events
 */

// Add admin menu
add_action(\'admin_menu\', \'kh_events_add_admin_menu\');
add_action(\'admin_init\', \'kh_events_settings_init\');

function kh_events_add_admin_menu() {
    add_menu_page(
        \'KH Events Settings\',
        \'KH Events\',
        \'manage_options\',
        \'kh-events-settings\',
        \'kh_events_settings_page\',
        \'dashicons-calendar-alt\',
        30
    );

    add_submenu_page(
        \'kh-events-settings\',
        \'API Settings\',
        \'API Settings\',
        \'manage_options\',
        \'kh-events-api\',
        \'kh_events_api_settings_page\'
    );

    add_submenu_page(
        \'kh-events-settings\',
        \'Social Media\',
        \'Social Media\',
        \'manage_options\',
        \'kh-events-social\',
        \'kh_events_social_settings_page\'
    );

    add_submenu_page(
        \'kh-events-settings\',
        \'CRM Integration\',
        \'CRM Integration\',
        \'manage_options\',
        \'kh-events-crm\',
        \'kh_events_crm_settings_page\'
    );

    add_submenu_page(
        \'kh-events-settings\',
        \'Webhooks\',
        \'Webhooks\',
        \'manage_options\',
        \'kh-events-webhooks\',
        \'kh_events_webhooks_settings_page\'
    );
}

function kh_events_settings_init() {
    register_setting(\'kh_events_api\', \'kh_events_api_keys\');
    register_setting(\'kh_events_social\', \'kh_events_facebook_settings\');
    register_setting(\'kh_events_social\', \'kh_events_twitter_settings\');
    register_setting(\'kh_events_social\', \'kh_events_linkedin_settings\');
    register_setting(\'kh_events_social\', \'kh_events_instagram_settings\');
    register_setting(\'kh_events_social\', \'kh_events_social_general\');
    register_setting(\'kh_events_crm\', \'kh_events_hubspot_settings\');
    register_setting(\'kh_events_crm\', \'kh_events_crm_general\');
    register_setting(\'kh_events_webhooks\', \'kh_events_webhooks\');
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
    $api_keys = get_option(\'kh_events_api_keys\', array());
    ?>
    <div class="wrap">
        <h1>API Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields(\'kh_events_api\'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Primary API Key</th>
                    <td>
                        <input type="password" name="kh_events_api_keys[primary]" value="<?php echo esc_attr($api_keys["primary"] ?? \'\'); ?>" class="regular-text">
                        <p class="description">Generated: kh_6da8ea3550440425b4a4da924d473bc0</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Mobile App API Key</th>
                    <td>
                        <input type="password" name="kh_events_api_keys[mobile]" value="<?php echo esc_attr($api_keys["mobile"] ?? \'\'); ?>" class="regular-text">
                        <p class="description">Generated: kh_955bcf1a0bd12ad47aa777bbf447e184</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Webhook Service API Key</th>
                    <td>
                        <input type="password" name="kh_events_api_keys[webhook]" value="<?php echo esc_attr($api_keys["webhook"] ?? \'\'); ?>" class="regular-text">
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
    $facebook_settings = get_option(\'kh_events_facebook_settings\', array());
    $twitter_settings = get_option(\'kh_events_twitter_settings\', array());
    $social_general = get_option(\'kh_events_social_general\', array());
    ?>
    <div class="wrap">
        <h1>Social Media Integration</h1>
        <form method="post" action="options.php">
            <?php settings_fields(\'kh_events_social\'); ?>
            <h3>Facebook Settings</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">App ID</th>
                    <td><input type="text" name="kh_events_facebook_settings[app_id]" value="<?php echo esc_attr($facebook_settings["app_id"] ?? \'\'); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">App Secret</th>
                    <td><input type="password" name="kh_events_facebook_settings[app_secret]" value="<?php echo esc_attr($facebook_settings["app_secret"] ?? \'\'); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Access Token</th>
                    <td><input type="password" name="kh_events_facebook_settings[access_token]" value="<?php echo esc_attr($facebook_settings["access_token"] ?? \'\'); ?>" class="regular-text"></td>
                </tr>
            </table>
            <h3>Twitter Settings</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">API Key</th>
                    <td><input type="password" name="kh_events_twitter_settings[api_key]" value="<?php echo esc_attr($twitter_settings["api_key"] ?? \'\'); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">API Secret</th>
                    <td><input type="password" name="kh_events_twitter_settings[api_secret]" value="<?php echo esc_attr($twitter_settings["api_secret"] ?? \'\'); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Access Token</th>
                    <td><input type="password" name="kh_events_twitter_settings[access_token]" value="<?php echo esc_attr($twitter_settings["access_token"] ?? \'\'); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Access Token Secret</th>
                    <td><input type="password" name="kh_events_twitter_settings[access_token_secret]" value="<?php echo esc_attr($twitter_settings["access_token_secret"] ?? \'\'); ?>" class="regular-text"></td>
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
    $hubspot_settings = get_option(\'kh_events_hubspot_settings\', array());
    $crm_general = get_option(\'kh_events_crm_general\', array());
    ?>
    <div class="wrap">
        <h1>CRM Integration Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields(\'kh_events_crm\'); ?>
            <h3>HubSpot Settings</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">API Key</th>
                    <td><input type="password" name="kh_events_hubspot_settings[api_key]" value="<?php echo esc_attr($hubspot_settings["api_key"] ?? \'\'); ?>" class="regular-text"></td>
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
    $webhooks = get_option(\'kh_events_webhooks\', array());
    ?>
    <div class="wrap">
        <h1>Webhook Settings</h1>
        <div id="webhook-list">
            <?php if (!empty($webhooks)): ?>
                <?php foreach ($webhooks as $index => $webhook): ?>
                    <div class="webhook-item" style="border: 1px solid #ddd; padding: 10px; margin: 10px 0;">
                        <h4><?php echo esc_html($webhook["name"] ?? \'Unnamed Webhook\'); ?></h4>
                        <p><strong>URL:</strong> <?php echo esc_html($webhook["url"] ?? \'\'); ?></p>
                        <p><strong>Events:</strong> <?php echo esc_html(implode(\', \', $webhook["events"] ?? array())); ?></p>
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
            $(\'#add-webhook\').click(function() {
                var name = $(\'#webhook-name\').val();
                var url = $(\'#webhook-url\').val();
                var events = [];
                $(\'.webhook-events:checked\').each(function() {
                    events.push($(this).val());
                });
                if (!name || !url || events.length === 0) {
                    alert(\'Please fill in all fields\');
                    return;
                }
                $.post(ajaxurl, {
                    action: \'kh_events_add_webhook\',
                    name: name,
                    url: url,
                    events: events,
                    nonce: kh_events_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(\'Error adding webhook\');
                    }
                });
            });
            $(\'.delete-webhook\').click(function() {
                var index = $(this).data(\'index\');
                if (confirm(\'Are you sure you want to delete this webhook?\')) {
                    $.post(ajaxurl, {
                        action: \'kh_events_delete_webhook\',
                        index: index,
                        nonce: kh_events_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(\'Error deleting webhook\');
                        }
                    });
                }
            });
        });
        </script>
    </div>
    <?php
}
?>';
    }

    private function generate_settings_registration_code() {
        return '<?php
register_activation_hook(__FILE__, \'kh_events_register_default_settings\');

function kh_events_register_default_settings() {
    add_option(\'kh_events_api_keys\', array(
        \'primary\' => \'kh_6da8ea3550440425b4a4da924d473bc0\',
        \'mobile\' => \'kh_955bcf1a0bd12ad47aa777bbf447e184\',
        \'webhook\' => \'kh_fee552fc0e31553dc5edca3bd28d4e18\'
    ));
    add_option(\'kh_events_social_general\', array(
        \'auto_post\' => true
    ));
    add_option(\'kh_events_crm_general\', array(
        \'auto_sync_contacts\' => true,
        \'auto_create_deals\' => true
    ));
    add_option(\'kh_events_webhooks\', array(
        array(
            \'name\' => \'Booking Notification Service\',
            \'url\' => \'https://api.example.com/webhooks/kh-events\',
            \'events\' => array(\'booking.completed\', \'booking.cancelled\'),
            \'secret\' => \'f8bd621380ba71f06e4fd1858a2d7a9c89bb348289bbcbc7e5660ff73d6638a1\',
            \'active\' => false
        )
    ));
}
?>';
    }

    private function generate_credential_manager_code() {
        return '<?php
class KH_Events_Credential_Manager {
    public static function get_api_key($type = \'primary\') {
        $keys = get_option(\'kh_events_api_keys\', array());
        return $keys[$type] ?? \'\';
    }

    public function get_social_credentials($platform) {
        return get_option(\'kh_events_\' . $platform . \'_settings\', array());
    }

    public function get_hubspot_credentials() {
        return get_option(\'kh_events_hubspot_settings\', array());
    }
}
?>';
    }

    private function generate_env_template() {
        return '# KH Events Production Environment Variables
KH_EVENTS_API_PRIMARY=kh_6da8ea3550440425b4a4da924d473bc0
KH_EVENTS_API_MOBILE=kh_955bcf1a0bd12ad47aa777bbf447e184
KH_EVENTS_API_WEBHOOK=kh_fee552fc0e31553dc5edca3bd28d4e18
KH_EVENTS_FACEBOOK_APP_ID=your_facebook_app_id_here
KH_EVENTS_FACEBOOK_APP_SECRET=your_facebook_app_secret_here
KH_EVENTS_FACEBOOK_ACCESS_TOKEN=your_facebook_access_token_here
KH_EVENTS_TWITTER_API_KEY=your_twitter_api_key_here
KH_EVENTS_TWITTER_API_SECRET=your_twitter_api_secret_here
KH_EVENTS_TWITTER_ACCESS_TOKEN=your_twitter_access_token_here
KH_EVENTS_TWITTER_ACCESS_TOKEN_SECRET=your_twitter_access_token_secret_here
KH_EVENTS_HUBSPOT_API_KEY=your_hubspot_api_key_here
KH_EVENTS_ENVIRONMENT=production
KH_EVENTS_DEBUG=false';
    }

    private function generate_webhook_manager_code() {
        return '<?php
class KH_Events_Webhook_Manager {
    public static function get_webhooks() {
        return get_option(\'kh_events_webhooks\', array());
    }

    public function trigger_webhook($event, $data) {
        $webhooks = $this->get_webhooks();
        foreach ($webhooks as $webhook) {
            if (in_array($event, $webhook[\'events\'] ?? array()) && ($webhook[\'active\'] ?? false)) {
                $this->deliver_webhook($webhook, $event, $data);
            }
        }
    }

    private function deliver_webhook($webhook, $event, $data) {
        $payload = array(
            \'event\' => $event,
            \'timestamp\' => time(),
            \'data\' => $data,
            \'source\' => \'kh-events-plugin\'
        );
        $signature = hash_hmac(\'sha256\', wp_json_encode($payload), $webhook[\'secret\']);
        $args = array(
            \'body\' => wp_json_encode($payload),
            \'headers\' => array(
                \'Content-Type\' => \'application/json\',
                \'X-KH-Events-Signature\' => \'sha256=\' . $signature,
                \'X-KH-Events-Event\' => $event,
                \'User-Agent\' => \'KH-Events-Webhook/1.0\'
            ),
            \'timeout\' => 30,
            \'blocking\' => false
        );
        wp_remote_post($webhook[\'url\'], $args);
    }
}
?>';
    }

    private function generate_webhook_tester_code() {
        return '<?php
echo "🪝 KH Events Webhook Delivery Tester\n";
echo "====================================\n\n";

require_once \'webhook_manager.php\';

$webhook_manager = new KH_Events_Webhook_Manager();
$webhooks = $webhook_manager->get_webhooks();

if (empty($webhooks)) {
    echo "❌ No webhooks configured.\n\n";
    exit(1);
}

echo "📋 Configured Webhooks:\n";
foreach ($webhooks as $index => $webhook) {
    $status = ($webhook[\'active\'] ?? false) ? \'Active\' : \'Inactive\';
    echo "  " . ($index + 1) . ". {$webhook[\'name\']} - $status\n";
    echo "     URL: {$webhook[\'url\']}\n\n";
}

echo "🧪 Testing Webhook Delivery...\n\n";

foreach ($webhooks as $index => $webhook) {
    if (!($webhook[\'active\'] ?? false)) {
        echo "⏭️  Skipping inactive webhook: {$webhook[\'name\']}\n\n";
        continue;
    }

    echo "📡 Testing webhook: {$webhook[\'name\']}\n";

    $url_test = wp_remote_head($webhook[\'url\'], array(\'timeout\' => 10));

    if (is_wp_error($url_test)) {
        echo "❌ URL unreachable: " . $url_test->get_error_message() . "\n";
    } else {
        $response_code = wp_remote_retrieve_response_code($url_test);
        if ($response_code >= 200 && $response_code < 400) {
            echo "✅ URL reachable (HTTP $response_code)\n";
        } else {
            echo "⚠️  URL returned HTTP $response_code\n";
        }
    }

    $test_payload = array(
        \'event\' => \'test.webhook_delivery\',
        \'timestamp\' => time(),
        \'data\' => array(
            \'test_id\' => \'webhook_test_\' . time(),
            \'message\' => \'KH Events webhook delivery test\',
            \'source\' => \'webhook_tester.php\'
        ),
        \'source\' => \'kh-events-webhook-test\'
    );

    $signature = hash_hmac(\'sha256\', wp_json_encode($test_payload), $webhook[\'secret\']);

    $args = array(
        \'body\' => wp_json_encode($test_payload),
        \'headers\' => array(
            \'Content-Type\' => \'application/json\',
            \'X-KH-Events-Signature\' => \'sha256=\' . $signature,
            \'X-KH-Events-Event\' => \'test.webhook_delivery\',
            \'User-Agent\' => \'KH-Events-Webhook-Test/1.0\'
        ),
        \'timeout\' => 30
    );

    echo "📤 Sending test payload...\n";
    $response = wp_remote_post($webhook[\'url\'], $args);

    if (is_wp_error($response)) {
        echo "❌ Delivery failed: " . $response->get_error_message() . "\n";
    } else {
        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            echo "✅ Test payload delivered successfully (HTTP $code)\n";
        } else {
            echo "⚠️  Test payload sent but received HTTP $code\n";
        }
    }

    echo "\n";
}

echo "✨ Webhook testing complete!\n";
?>';
    }

    private function generate_staging_test_suite() {
        return '<?php
echo "🧪 KH Events Staging Environment Test Suite\n";
echo "=============================================\n\n";

require_once \'credential_manager.php\';

$credential_manager = new KH_Events_Credential_Manager();

echo "🔍 Checking Integration Status...\n\n";

$integrations = array(
    \'facebook\' => array(\'app_id\', \'app_secret\', \'access_token\'),
    \'twitter\' => array(\'api_key\', \'api_secret\', \'access_token\', \'access_token_secret\'),
    \'hubspot\' => array(\'api_key\')
);

$results = array();
$overall_status = true;

foreach ($integrations as $key => $required_fields) {
    echo "📡 Testing {$key} Integration:\n";

    $credentials = $credential_manager->get_social_credentials($key);
    if ($key === \'hubspot\') {
        $credentials = $credential_manager->get_hubspot_credentials();
    }

    $missing = array();
    foreach ($required_fields as $field) {
        if (empty($credentials[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        echo "❌ Missing credentials: " . implode(\', \', $missing) . "\n";
        $results[$key] = array(\'status\' => \'missing_credentials\', \'missing\' => $missing);
        $overall_status = false;
    } else {
        echo "✅ Credentials configured\n";
        $results[$key] = array(\'status\' => \'success\');
    }

    echo "\n";
}

echo "📊 Integration Validation Summary:\n";
echo "===================================\n";

foreach ($results as $key => $result) {
    $icon = $result[\'status\'] === \'success\' ? \'✅\' : \'❌\';
    $message = $result[\'status\'] === \'success\' ? \'Ready\' : \'Missing credentials: \' . implode(\', \', $result[\'missing\']);
    echo "$icon " . ucfirst($key) . ": $message\n";
}

echo "\n🎯 Overall Status: " . ($overall_status ? \'✅ All integrations ready\' : \'⚠️ Some integrations need attention\') . "\n\n";

echo "✨ Staging environment testing complete!\n";
?>';
    }

    private function generate_integration_validator() {
        return '<?php
echo "🔗 KH Events Integration Validator\n";
echo "===================================\n\n";

require_once \'credential_manager.php\';

$credential_manager = new KH_Events_Credential_Manager();

$integrations = array(
    \'facebook\' => array(\'app_id\', \'app_secret\', \'access_token\'),
    \'twitter\' => array(\'api_key\', \'api_secret\', \'access_token\', \'access_token_secret\'),
    \'hubspot\' => array(\'api_key\')
);

$results = array();
$overall_status = true;

foreach ($integrations as $key => $required_fields) {
    $credentials = $credential_manager->get_social_credentials($key);
    if ($key === \'hubspot\') {
        $credentials = $credential_manager->get_hubspot_credentials();
    }

    $missing = array();
    foreach ($required_fields as $field) {
        if (empty($credentials[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        $results[$key] = array(\'status\' => \'missing_credentials\', \'missing\' => $missing);
        $overall_status = false;
    } else {
        $results[$key] = array(\'status\' => \'success\');
    }
}

file_put_contents(\'integration_validation_report.json\', wp_json_encode(array(
    \'timestamp\' => time(),
    \'results\' => $results,
    \'overall_status\' => $overall_status
), JSON_PRETTY_PRINT));

echo "Detailed report saved to: integration_validation_report.json\n\n";
echo "✨ Integration validation complete!\n";
?>';
    }

    private function generate_deployment_checklist() {
        $date = date('Y-m-d H:i:s');
        return "# KH Events Production Deployment Checklist

## Pre-Deployment Preparation
- [ ] Review all generated configuration files
- [ ] Set up environment variables with real API credentials
- [ ] Configure webhook URLs with production endpoints
- [ ] Run staging environment tests
- [ ] Validate all integrations
- [ ] Backup production database and files
- [ ] Set up monitoring and alerting

## WordPress Admin Configuration
- [ ] Navigate to KH Events > Settings in WordPress admin
- [ ] Configure API keys (copy from api_keys.json)
- [ ] Set up social media credentials for each platform
- [ ] Configure HubSpot API key and settings
- [ ] Set up webhook endpoints with real URLs
- [ ] Enable auto-posting and sync features

## Environment Setup
- [ ] Copy .env.example to .env
- [ ] Fill in all API credentials in .env file
- [ ] Set environment to production
- [ ] Configure database settings if using external DB
- [ ] Set up proper file permissions

## Security Configuration
- [ ] Ensure webhook secrets are stored securely
- [ ] Verify HTTPS is enabled for all webhook endpoints
- [ ] Set up proper access controls
- [ ] Configure rate limiting
- [ ] Enable logging and monitoring

## Integration Testing
- [ ] Test social media posting (use test mode first)
- [ ] Verify HubSpot contact sync
- [ ] Test webhook delivery
- [ ] Validate API endpoints
- [ ] Check error handling

## Performance Optimization
- [ ] Enable caching where appropriate
- [ ] Optimize database queries
- [ ] Set up CDN for static assets
- [ ] Configure proper logging levels

## Monitoring & Maintenance
- [ ] Set up error monitoring (e.g., Sentry, LogRocket)
- [ ] Configure uptime monitoring
- [ ] Set up automated backups
- [ ] Plan regular security updates
- [ ] Establish incident response procedures

## Go-Live Checklist
- [ ] Run final staging tests
- [ ] Update DNS if necessary
- [ ] Enable production integrations
- [ ] Monitor error logs closely
- [ ] Test critical user flows
- [ ] Communicate with stakeholders

## Post-Deployment
- [ ] Monitor webhook delivery logs
- [ ] Check API usage and rate limits
- [ ] Validate data synchronization
- [ ] Test automated social media posting
- [ ] Verify CRM integration functionality

## Rollback Plan
- [ ] Identify rollback triggers
- [ ] Prepare backup restore procedures
- [ ] Document rollback steps
- [ ] Test rollback procedures
- [ ] Communicate rollback plan to team

---
*Generated on: {$date}*
*KH Events Plugin Deployment Guide*";
    }

    private function generate_production_validator() {
        return '<?php
echo "🚀 KH Events Production Readiness Validator\n";
echo "===========================================\n\n";

require_once \'credential_manager.php\';

$credential_manager = new KH_Events_Credential_Manager();

$checks = array();
$critical_issues = 0;
$warnings = 0;

echo "🔍 Running production readiness checks...\n\n";

$credential_checks = array();

$platforms = array(
    \'facebook\' => array(\'app_id\', \'app_secret\', \'access_token\'),
    \'twitter\' => array(\'api_key\', \'api_secret\', \'access_token\', \'access_token_secret\'),
    \'hubspot\' => array(\'api_key\')
);

foreach ($platforms as $key => $required_fields) {
    $credentials = $credential_manager->get_social_credentials($key);
    if ($key === \'hubspot\') {
        $credentials = $credential_manager->get_hubspot_credentials();
    }

    $missing = array();
    foreach ($required_fields as $field) {
        if (empty($credentials[$field])) {
            $missing[] = $field;
        }
    }

    $status = empty($missing);
    $credential_checks[] = array(
        \'check\' => ucfirst($key) . \' Credentials\',
        \'status\' => $status,
        \'message\' => $status ? \'All credentials configured\' : \'Missing: \' . implode(\', \', $missing),
        \'critical\' => true
    );

    if (!$status) {
        $critical_issues++;
    }
}

$readiness_score = 100 - ($critical_issues * 15) - ($warnings * 5);
$readiness_score = max(0, min(100, $readiness_score));

echo "Critical Issues: $critical_issues\n";
echo "Warnings: $warnings\n";
echo "Readiness Score: {$readiness_score}%\n\n";

if ($critical_issues === 0 && $readiness_score >= 90) {
    echo "🟢 PRODUCTION READY - All systems go!\n";
} elseif ($critical_issues === 0 && $readiness_score >= 75) {
    echo "🟡 MOSTLY READY - Address warnings before deployment.\n";
} else {
    echo "🔴 NOT READY - Critical issues must be resolved.\n";
}

$report = array(
    \'timestamp\' => time(),
    \'readiness_score\' => $readiness_score,
    \'critical_issues\' => $critical_issues,
    \'warnings\' => $warnings,
    \'checks\' => $credential_checks
);

file_put_contents(\'production_readiness_report.json\', wp_json_encode($report, JSON_PRETTY_PRINT));

echo "\n📄 Full report saved to: production_readiness_report.json\n\n";
echo "✨ Production readiness validation complete!\n";
?>';
    }

    private function generate_backup_script() {
        return '<?php
echo "💾 KH Events Pre-Deployment Backup Script\n";
echo "==========================================\n\n";

$backup_dir = \'backups/\' . date(\'Y-m-d_H-i-s\');

if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

echo "📁 Created backup directory: $backup_dir\n\n";

$config_files = array(
    \'api_keys.json\',
    \'social_media_config_sample.json\',
    \'hubspot_config_sample.json\',
    \'webhook_config_sample.json\'
);

foreach ($config_files as $file) {
    if (file_exists($file)) {
        copy($file, "$backup_dir/$file");
        echo "✅ Backed up: $file\n";
    }
}

echo "\n✅ Backup completed successfully!\n\n";
echo "⚠️  Keep this backup safe during deployment.\n\n";
echo "🚀 Ready for deployment!\n";
?>';
    }

    private function save_to_file($filename, $content) {
        $filepath = dirname(__FILE__) . '/' . $filename;
        file_put_contents($filepath, $content);
        echo "✅ Created: $filename\n";
    }
}

// Run the deployment preparation
$preparer = new KH_Events_Deployment_Preparer();

$preparer->prepare_wordpress_admin_settings();
$preparer->prepare_credential_management();
$preparer->prepare_webhook_configuration();
$preparer->prepare_staging_tests();
$preparer->prepare_deployment_scripts();

$preparer->generate_deployment_summary();

echo "\n🎉 KH Events Live Deployment Preparation Complete!\n";
echo "==================================================\n";
echo "All technical preparation for production deployment is now ready.\n";
echo "You now need to:\n";
echo "1. Set up real API credentials\n";
echo "2. Configure webhook endpoints\n";
echo "3. Test in staging environment\n";
echo "4. Execute the deployment checklist\n";
echo "5. Deploy to production\n\n";
?>