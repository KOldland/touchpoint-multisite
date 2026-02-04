<?php
echo "🧪 KH Events Staging Environment Test Suite\n";
echo "=============================================\n\n";

require_once 'credential_manager.php';

$credential_manager = new KH_Events_Credential_Manager();

echo "🔍 Checking Integration Status...\n\n";

$integrations = array(
    'facebook' => array('app_id', 'app_secret', 'access_token'),
    'twitter' => array('api_key', 'api_secret', 'access_token', 'access_token_secret'),
    'hubspot' => array('api_key')
);

$results = array();
$overall_status = true;

foreach ($integrations as $key => $required_fields) {
    echo "📡 Testing {$key} Integration:\n";

    $credentials = $credential_manager->get_social_credentials($key);
    if ($key === 'hubspot') {
        $credentials = $credential_manager->get_hubspot_credentials();
    }

    $missing = array();
    foreach ($required_fields as $field) {
        if (empty($credentials[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        echo "❌ Missing credentials: " . implode(', ', $missing) . "\n";
        $results[$key] = array('status' => 'missing_credentials', 'missing' => $missing);
        $overall_status = false;
    } else {
        echo "✅ Credentials configured\n";
        $results[$key] = array('status' => 'success');
    }

    echo "\n";
}

echo "📊 Integration Validation Summary:\n";
echo "===================================\n";

foreach ($results as $key => $result) {
    $icon = $result['status'] === 'success' ? '✅' : '❌';
    $message = $result['status'] === 'success' ? 'Ready' : 'Missing credentials: ' . implode(', ', $result['missing']);
    echo "$icon " . ucfirst($key) . ": $message\n";
}

echo "\n🎯 Overall Status: " . ($overall_status ? '✅ All integrations ready' : '⚠️ Some integrations need attention') . "\n\n";

echo "✨ Staging environment testing complete!\n";
?>