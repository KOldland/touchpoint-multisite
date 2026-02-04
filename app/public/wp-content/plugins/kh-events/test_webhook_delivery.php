<?php
echo "🪝 KH Events Webhook Delivery Tester\n";
echo "====================================\n\n";

require_once 'webhook_manager.php';

$webhook_manager = new KH_Events_Webhook_Manager();
$webhooks = $webhook_manager->get_webhooks();

if (empty($webhooks)) {
    echo "❌ No webhooks configured.\n\n";
    exit(1);
}

echo "📋 Configured Webhooks:\n";
foreach ($webhooks as $index => $webhook) {
    $status = ($webhook['active'] ?? false) ? 'Active' : 'Inactive';
    echo "  " . ($index + 1) . ". {$webhook['name']} - $status\n";
    echo "     URL: {$webhook['url']}\n\n";
}

echo "🧪 Testing Webhook Delivery...\n\n";

foreach ($webhooks as $index => $webhook) {
    if (!($webhook['active'] ?? false)) {
        echo "⏭️  Skipping inactive webhook: {$webhook['name']}\n\n";
        continue;
    }

    echo "📡 Testing webhook: {$webhook['name']}\n";

    $url_test = wp_remote_head($webhook['url'], array('timeout' => 10));

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
        'event' => 'test.webhook_delivery',
        'timestamp' => time(),
        'data' => array(
            'test_id' => 'webhook_test_' . time(),
            'message' => 'KH Events webhook delivery test',
            'source' => 'webhook_tester.php'
        ),
        'source' => 'kh-events-webhook-test'
    );

    $signature = hash_hmac('sha256', wp_json_encode($test_payload), $webhook['secret']);

    $args = array(
        'body' => wp_json_encode($test_payload),
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-KH-Events-Signature' => 'sha256=' . $signature,
            'X-KH-Events-Event' => 'test.webhook_delivery',
            'User-Agent' => 'KH-Events-Webhook-Test/1.0'
        ),
        'timeout' => 30
    );

    echo "📤 Sending test payload...\n";
    $response = wp_remote_post($webhook['url'], $args);

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
?>