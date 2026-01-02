<?php
echo "🚀 KH Events Production Readiness Validator\n";
echo "===========================================\n\n";

require_once 'credential_manager.php';

$credential_manager = new KH_Events_Credential_Manager();

$checks = array();
$critical_issues = 0;
$warnings = 0;

echo "🔍 Running production readiness checks...\n\n";

$credential_checks = array();

$platforms = array(
    'facebook' => array('app_id', 'app_secret', 'access_token'),
    'twitter' => array('api_key', 'api_secret', 'access_token', 'access_token_secret'),
    'hubspot' => array('api_key')
);

foreach ($platforms as $key => $required_fields) {
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

    $status = empty($missing);
    $credential_checks[] = array(
        'check' => ucfirst($key) . ' Credentials',
        'status' => $status,
        'message' => $status ? 'All credentials configured' : 'Missing: ' . implode(', ', $missing),
        'critical' => true
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
    'timestamp' => time(),
    'readiness_score' => $readiness_score,
    'critical_issues' => $critical_issues,
    'warnings' => $warnings,
    'checks' => $credential_checks
);

file_put_contents('production_readiness_report.json', wp_json_encode($report, JSON_PRETTY_PRINT));

echo "\n📄 Full report saved to: production_readiness_report.json\n\n";
echo "✨ Production readiness validation complete!\n";
?>