<?php
/**
 * Production Configuration Validator
 *
 * Validates that all production configurations are properly set up
 */

echo "🔍 KH Events Production Configuration Validator\n";
echo "===============================================\n\n";

$errors = array();
$warnings = array();

// Check if configuration files exist
$config_files = array(
    'api_keys.json',
    'social_media_config_sample.json',
    'hubspot_config_sample.json',
    'webhook_config_sample.json',
    'production_setup_report.json'
);

echo "1. 📁 Checking Configuration Files\n";
echo "----------------------------------\n";

foreach ($config_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file - Found\n";
    } else {
        echo "❌ $file - Missing\n";
        $errors[] = "Configuration file $file not found";
    }
}

echo "\n2. 🔑 Validating API Keys\n";
echo "-------------------------\n";

if (file_exists('api_keys.json')) {
    $api_keys = json_decode(file_get_contents('api_keys.json'), true);
    if ($api_keys) {
        foreach ($api_keys as $type => $key_data) {
            if (isset($key_data['key']) && strpos($key_data['key'], 'kh_') === 0) {
                echo "✅ {$key_data['name']} - Valid format\n";
            } else {
                echo "❌ {$key_data['name']} - Invalid format\n";
                $errors[] = "API key for {$key_data['name']} has invalid format";
            }
        }
    } else {
        echo "❌ api_keys.json - Invalid JSON\n";
        $errors[] = "api_keys.json contains invalid JSON";
    }
}

echo "\n3. 📱 Validating Social Media Config\n";
echo "-----------------------------------\n";

if (file_exists('social_media_config_sample.json')) {
    $social_config = json_decode(file_get_contents('social_media_config_sample.json'), true);
    if ($social_config) {
        echo "✅ Configuration structure - Valid\n";
        if (isset($social_config['platforms'])) {
            $platforms = $social_config['platforms'];
            foreach ($platforms as $platform => $settings) {
                $status = isset($settings['enabled']) && $settings['enabled'] ? 'Enabled' : 'Disabled';
                echo "📍 $platform - $status\n";
            }
        }
        echo "\n⚠️  Note: Actual API credentials need to be configured in WordPress admin\n";
        $warnings[] = "Social media credentials must be set in WordPress admin settings";
    } else {
        echo "❌ social_media_config_sample.json - Invalid JSON\n";
        $errors[] = "social_media_config_sample.json contains invalid JSON";
    }
}

echo "\n4. 🎯 Validating HubSpot Config\n";
echo "-------------------------------\n";

if (file_exists('hubspot_config_sample.json')) {
    $hubspot_config = json_decode(file_get_contents('hubspot_config_sample.json'), true);
    if ($hubspot_config) {
        echo "✅ Configuration structure - Valid\n";
        if (isset($hubspot_config['api_key']) && $hubspot_config['api_key'] === '[YOUR_HUBSPOT_API_KEY]') {
            echo "⚠️  API Key placeholder detected - needs real key\n";
            $warnings[] = "HubSpot API key needs to be replaced with actual key";
        }
        echo "\n⚠️  Note: Actual HubSpot credentials need to be configured in WordPress admin\n";
        $warnings[] = "HubSpot credentials must be set in WordPress admin settings";
    } else {
        echo "❌ hubspot_config_sample.json - Invalid JSON\n";
        $errors[] = "hubspot_config_sample.json contains invalid JSON";
    }
}

echo "\n5. 🪝 Validating Webhook Config\n";
echo "------------------------------\n";

if (file_exists('webhook_config_sample.json')) {
    $webhook_config = json_decode(file_get_contents('webhook_config_sample.json'), true);
    if ($webhook_config && is_array($webhook_config)) {
        echo "✅ Configuration structure - Valid\n";
        foreach ($webhook_config as $webhook) {
            if (isset($webhook['url']) && isset($webhook['secret'])) {
                $url_valid = filter_var($webhook['url'], FILTER_VALIDATE_URL) !== false;
                $secret_valid = strlen($webhook['secret']) >= 32;
                $status = $url_valid && $secret_valid ? 'Valid' : 'Invalid';
                echo "📡 {$webhook['name']} - $status\n";
                if (!$url_valid) {
                    $errors[] = "Invalid URL for webhook: {$webhook['name']}";
                }
                if (!$secret_valid) {
                    $errors[] = "Invalid secret for webhook: {$webhook['name']}";
                }
            }
        }
        echo "\n⚠️  Note: Webhook URLs and secrets need to be configured in WordPress admin\n";
        $warnings[] = "Webhook endpoints must be set in WordPress admin settings";
    } else {
        echo "❌ webhook_config_sample.json - Invalid JSON or structure\n";
        $errors[] = "webhook_config_sample.json contains invalid JSON or structure";
    }
}

echo "\n6. 📊 Integration Classes Check\n";
echo "-------------------------------\n";

// Check if integration classes exist
$integration_files = array(
    'includes/class-kh-events-enhanced-api.php',
    'includes/class-kh-social-media-integration.php',
    'includes/integrations/class-kh-hubspot-integration.php'
);

foreach ($integration_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file - Found\n";
    } else {
        echo "❌ $file - Missing\n";
        $errors[] = "Integration file $file not found";
    }
}

echo "\n📋 Validation Summary\n";
echo "====================\n";

if (empty($errors)) {
    echo "✅ All configuration files are present and valid!\n";
} else {
    echo "❌ Configuration issues found:\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠️  Configuration warnings:\n";
    foreach ($warnings as $warning) {
        echo "   • $warning\n";
    }
}

echo "\n🚀 Production Readiness Status:\n";
echo "===============================\n";

$readiness_score = 100 - (count($errors) * 20) - (count($warnings) * 5);
$readiness_score = max(0, min(100, $readiness_score));

if ($readiness_score >= 90) {
    echo "🟢 HIGH READINESS - Ready for production with minor configuration\n";
} elseif ($readiness_score >= 70) {
    echo "🟡 MEDIUM READINESS - Requires additional setup\n";
} else {
    echo "🔴 LOW READINESS - Significant configuration needed\n";
}

echo "Readiness Score: {$readiness_score}%\n\n";

echo "📝 Next Steps for Production Deployment:\n";
echo "========================================\n";
echo "1. Set up actual API credentials in WordPress admin\n";
echo "2. Configure webhook endpoints with real URLs\n";
echo "3. Test integrations in staging environment\n";
echo "4. Deploy to production server\n";
echo "5. Monitor webhook delivery and API usage\n";
echo "6. Set up proper security measures (HTTPS, rate limiting)\n\n";

echo "🔐 Security Checklist:\n";
echo "=====================\n";
echo "• Store API keys in environment variables\n";
echo "• Use HTTPS for all webhook endpoints\n";
echo "• Implement HMAC signature verification\n";
echo "• Set up proper access controls\n";
echo "• Monitor for unusual API usage\n";
echo "• Rotate keys regularly\n\n";