<?php
echo "🔗 KH Events Integration Validator\n";
echo "===================================\n\n";

require_once 'credential_manager.php';

$credential_manager = new KH_Events_Credential_Manager();

$integrations = array(
    'facebook' => array('app_id', 'app_secret', 'access_token'),
    'twitter' => array('api_key', 'api_secret', 'access_token', 'access_token_secret'),
    'hubspot' => array('api_key')
);

$results = array();
$overall_status = true;

foreach ($integrations as $key => $required_fields) {
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
        $results[$key] = array('status' => 'missing_credentials', 'missing' => $missing);
        $overall_status = false;
    } else {
        $results[$key] = array('status' => 'success');
    }
}

file_put_contents('integration_validation_report.json', wp_json_encode(array(
    'timestamp' => time(),
    'results' => $results,
    'overall_status' => $overall_status
), JSON_PRETTY_PRINT));

echo "Detailed report saved to: integration_validation_report.json\n\n";
echo "✨ Integration validation complete!\n";
?>