<?php
/**
 * KH Events Production Setup & Configuration Helper
 *
 * Helps configure API keys, integrations, and webhooks for production deployment
 */

// Load composer dependencies
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

echo "KH Events Production Setup & Configuration Helper\n";
echo "=================================================\n\n";

// Define minimal constants for testing
define('KH_EVENTS_DIR', dirname(__FILE__) . '/');
define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');

// Include core files
require_once KH_EVENTS_DIR . 'includes/class-kh-events.php';
require_once KH_EVENTS_DIR . 'includes/class-kh-events-enhanced-api.php';
require_once KH_EVENTS_DIR . 'includes/class-kh-social-media-integration.php';
require_once KH_EVENTS_DIR . 'includes/integrations/class-kh-hubspot-integration.php';

class KH_Events_Production_Setup {

    public function run_setup() {
        echo "🔧 Starting KH Events Production Configuration...\n\n";

        $this->setup_api_keys();
        $this->setup_social_media();
        $this->setup_hubspot_integration();
        $this->setup_webhooks();
        $this->generate_configuration_report();

        echo "\n✅ Production setup complete!\n";
        echo "📋 Copy the configuration values above to your WordPress admin settings.\n";
    }

    private function setup_api_keys() {
        echo "1. 🔑 API Key Generation\n";
        echo "------------------------\n";

        // Generate sample API keys
        $api_keys = array(
            'primary' => array(
                'name' => 'Primary API Key',
                'key' => $this->generate_api_key(),
                'permissions' => array('read', 'write', 'admin')
            ),
            'mobile_app' => array(
                'name' => 'Mobile App API Key',
                'key' => $this->generate_api_key(),
                'permissions' => array('read', 'write')
            ),
            'webhook_service' => array(
                'name' => 'Webhook Service API Key',
                'key' => $this->generate_api_key(),
                'permissions' => array('read')
            )
        );

        echo "Generated API Keys (save these securely):\n\n";

        foreach ($api_keys as $type => $key_data) {
            echo "📋 {$key_data['name']}:\n";
            echo "   Key: {$key_data['key']}\n";
            echo "   Permissions: " . implode(', ', $key_data['permissions']) . "\n\n";
        }

        // Save to a temporary file for reference
        $this->save_to_file('api_keys.json', json_encode($api_keys, JSON_PRETTY_PRINT));
        echo "💾 API keys saved to: api_keys.json\n\n";
    }

    private function setup_social_media() {
        echo "2. 📱 Social Media Configuration\n";
        echo "-------------------------------\n";

        $platforms = array(
            'facebook' => array(
                'name' => 'Facebook',
                'setup_steps' => array(
                    '1. Go to https://developers.facebook.com/',
                    '2. Create a new app or use existing one',
                    '3. Add Facebook Login product',
                    '4. Get App ID and App Secret',
                    '5. Configure app domains and privacy policy',
                    '6. Set up Facebook Page access token for posting'
                ),
                'credentials_needed' => array(
                    'app_id' => 'Your Facebook App ID',
                    'app_secret' => 'Your Facebook App Secret',
                    'page_id' => 'Your Facebook Page ID',
                    'access_token' => 'Page Access Token with publish permissions'
                )
            ),
            'twitter' => array(
                'name' => 'Twitter',
                'setup_steps' => array(
                    '1. Go to https://developer.twitter.com/',
                    '2. Create a new app or use existing one',
                    '3. Enable OAuth 2.0 and get API keys',
                    '4. Set app permissions to Read+Write',
                    '5. Generate access tokens'
                ),
                'credentials_needed' => array(
                    'api_key' => 'Your Twitter API Key',
                    'api_secret' => 'Your Twitter API Secret',
                    'access_token' => 'Access Token',
                    'access_token_secret' => 'Access Token Secret'
                )
            ),
            'linkedin' => array(
                'name' => 'LinkedIn',
                'setup_steps' => array(
                    '1. Go to https://developer.linkedin.com/',
                    '2. Create a new app',
                    '3. Add r_liteprofile and w_member_social permissions',
                    '4. Set OAuth redirect URL',
                    '5. Get Client ID and Secret'
                ),
                'credentials_needed' => array(
                    'client_id' => 'LinkedIn Client ID',
                    'client_secret' => 'LinkedIn Client Secret'
                )
            ),
            'instagram' => array(
                'name' => 'Instagram',
                'setup_steps' => array(
                    '1. Use Facebook app (Instagram is part of Facebook)',
                    '2. Add Instagram Basic Display product',
                    '3. Get Instagram Business Account ID',
                    '4. Generate long-lived access token'
                ),
                'credentials_needed' => array(
                    'access_token' => 'Instagram Access Token',
                    'account_id' => 'Instagram Account ID'
                )
            )
        );

        foreach ($platforms as $key => $platform) {
            echo "🌐 {$platform['name']} Setup:\n";
            echo "Setup Steps:\n";
            foreach ($platform['setup_steps'] as $step) {
                echo "   • $step\n";
            }
            echo "\nCredentials Needed:\n";
            foreach ($platform['credentials_needed'] as $field => $description) {
                echo "   • $field: $description\n";
            }
            echo "\n";
        }

        // Generate sample configuration
        $sample_config = array(
            'auto_post' => true,
            'message_template' => "{title}\n\n📅 {date}\n📍 {location}\n💰 {price}\n\n{description}\n\nRegister: {link}",
            'platforms' => array(
                'facebook' => array('enabled' => true),
                'twitter' => array('enabled' => true),
                'linkedin' => array('enabled' => false),
                'instagram' => array('enabled' => false)
            )
        );

        $this->save_to_file('social_media_config_sample.json', json_encode($sample_config, JSON_PRETTY_PRINT));
        echo "💾 Sample social media config saved to: social_media_config_sample.json\n\n";
    }

    private function setup_hubspot_integration() {
        echo "3. 🎯 HubSpot CRM Integration Setup\n";
        echo "-----------------------------------\n";

        echo "HubSpot Setup Steps:\n";
        echo "1. Log in to your HubSpot account\n";
        echo "2. Go to Settings > Account Setup > Integrations > API key\n";
        echo "3. Generate a new API key (or use existing one)\n";
        echo "4. Copy the API key\n\n";

        echo "HubSpot Configuration:\n";
        echo "• API Key: [Your HubSpot API Key Here]\n";
        echo "• Auto Sync Contacts: Enable to sync new bookings automatically\n";
        echo "• Auto Create Deals: Enable to create deals for event bookings\n\n";

        // Generate sample HubSpot configuration
        $hubspot_config = array(
            'api_key' => '[YOUR_HUBSPOT_API_KEY]',
            'auto_sync' => true,
            'auto_create_deals' => true,
            'contact_properties' => array(
                'event_attendee' => 'Event Attendee',
                'last_event_booked' => 'Last Event Booked',
                'total_event_spend' => 'Total Event Spend'
            ),
            'deal_stages' => array(
                'event_booking' => 'Event Booking',
                'closed_won' => 'Closed Won'
            )
        );

        $this->save_to_file('hubspot_config_sample.json', json_encode($hubspot_config, JSON_PRETTY_PRINT));
        echo "💾 Sample HubSpot config saved to: hubspot_config_sample.json\n\n";
    }

    private function setup_webhooks() {
        echo "4. 🪝 Webhook Configuration\n";
        echo "-------------------------\n";

        $sample_webhooks = array(
            array(
                'name' => 'Booking Notification Service',
                'url' => 'https://api.example.com/webhooks/kh-events',
                'events' => array('booking.completed', 'booking.cancelled'),
                'secret' => $this->generate_webhook_secret(),
                'active' => true
            ),
            array(
                'name' => 'CRM Sync Service',
                'url' => 'https://crm.example.com/webhooks/events',
                'events' => array('event.created', 'event.updated', 'booking.completed'),
                'secret' => $this->generate_webhook_secret(),
                'active' => true
            ),
            array(
                'name' => 'Analytics Platform',
                'url' => 'https://analytics.example.com/webhooks/kh-events',
                'events' => array('event.created', 'booking.completed'),
                'secret' => $this->generate_webhook_secret(),
                'active' => true
            )
        );

        echo "Sample Webhook Configurations:\n\n";

        foreach ($sample_webhooks as $webhook) {
            echo "📡 {$webhook['name']}:\n";
            echo "   URL: {$webhook['url']}\n";
            echo "   Events: " . implode(', ', $webhook['events']) . "\n";
            echo "   Secret: {$webhook['secret']}\n";
            echo "   Active: " . ($webhook['active'] ? 'Yes' : 'No') . "\n\n";
        }

        $this->save_to_file('webhook_config_sample.json', json_encode($sample_webhooks, JSON_PRETTY_PRINT));
        echo "💾 Sample webhook config saved to: webhook_config_sample.json\n\n";

        echo "Webhook Security Notes:\n";
        echo "• Store webhook secrets securely (never in code)\n";
        echo "• Use HTTPS URLs only for production\n";
        echo "• Implement HMAC signature verification\n";
        echo "• Handle webhook retries and failures\n\n";
    }

    private function generate_configuration_report() {
        echo "5. 📊 Configuration Report\n";
        echo "-------------------------\n";

        $report = array(
            'generated_at' => date('Y-m-d H:i:s'),
            'environment' => 'development',
            'files_created' => array(
                'api_keys.json',
                'social_media_config_sample.json',
                'hubspot_config_sample.json',
                'webhook_config_sample.json'
            ),
            'next_steps' => array(
                '1. Review and customize the generated configuration files',
                '2. Set up API credentials with respective platforms',
                '3. Configure webhooks with your external services',
                '4. Test integrations in a staging environment',
                '5. Deploy configurations to production',
                '6. Monitor webhook delivery and API usage'
            ),
            'security_notes' => array(
                'Store API keys and secrets securely',
                'Use environment variables for sensitive data',
                'Rotate keys regularly',
                'Monitor API usage for anomalies',
                'Implement rate limiting'
            )
        );

        $this->save_to_file('production_setup_report.json', json_encode($report, JSON_PRETTY_PRINT));

        echo "Configuration files generated:\n";
        foreach ($report['files_created'] as $file) {
            echo "✓ $file\n";
        }

        echo "\nNext Steps:\n";
        foreach ($report['next_steps'] as $step) {
            echo "• $step\n";
        }

        echo "\nSecurity Notes:\n";
        foreach ($report['security_notes'] as $note) {
            echo "• $note\n";
        }

        echo "\n📄 Full report saved to: production_setup_report.json\n";
    }

    private function generate_api_key() {
        return 'kh_' . bin2hex(random_bytes(16));
    }

    private function generate_webhook_secret() {
        return bin2hex(random_bytes(32));
    }

    private function save_to_file($filename, $content) {
        $filepath = dirname(__FILE__) . '/' . $filename;
        file_put_contents($filepath, $content);
    }
}

// Run the setup
$setup = new KH_Events_Production_Setup();
$setup->run_setup();

echo "\n🎉 KH Events Production Configuration Helper Complete!\n";
echo "=====================================================\n";
echo "All configuration files have been generated in the plugin directory.\n";
echo "Review, customize, and securely store these configurations before deploying.\n\n";