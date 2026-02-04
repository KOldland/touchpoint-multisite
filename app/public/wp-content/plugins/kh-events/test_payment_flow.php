<?php
/**
 * Comprehensive Payment Flow Test for KH Events
 */

require_once '/Users/krisoldland/Documents/GitHub/1927MSuite/wp-load.php';

if (!defined('ABSPATH')) {
    exit;
}

echo "<h1>KH Events Payment Flow Test</h1>";

// Test 1: Payment Handler Initialization
echo "<h2>Test 1: Payment Handler</h2>";
$payment_handler = KH_Payment_Handler::instance();
echo "✓ Payment handler initialized<br>";

// Test 2: Available Gateways
echo "<h2>Test 2: Available Payment Gateways</h2>";
$gateways = $payment_handler->get_available_gateways();
if (empty($gateways)) {
    echo "⚠️ No payment gateways enabled. Please configure Stripe or PayPal in settings.<br>";
} else {
    echo "✓ Available gateways: " . implode(', ', array_keys($gateways)) . "<br>";
}

// Test 3: Stripe Gateway (if enabled)
if (isset($gateways['stripe'])) {
    echo "<h2>Test 3: Stripe Gateway Configuration</h2>";
    $stripe = $gateways['stripe'];
    $test_mode = $stripe->get_setting('testmode');
    $pub_key = $stripe->get_setting('publishable_key');
    $sec_key = $stripe->get_setting('secret_key');

    echo "✓ Stripe enabled<br>";
    echo "Test mode: " . ($test_mode === 'yes' ? 'Enabled' : 'Disabled') . "<br>";
    echo "Publishable key: " . (empty($pub_key) ? '❌ Not set' : '✓ Set') . "<br>";
    echo "Secret key: " . (empty($sec_key) ? '❌ Not set' : '✓ Set') . "<br>";
}

// Test 4: Booking Class
echo "<h2>Test 4: Booking System</h2>";
if (class_exists('KH_Event_Bookings')) {
    echo "✓ KH_Event_Bookings class loaded<br>";
} else {
    echo "❌ KH_Event_Bookings class not found<br>";
}

// Test 5: Payment Processing Simulation
echo "<h2>Test 5: Payment Processing Simulation</h2>";
if (!empty($gateways)) {
    $gateway_id = key($gateways);
    $test_payment_data = array(
        'amount' => 25.00,
        'currency' => 'USD',
        'token' => 'tok_test_card', // Test token
        'order_id' => 'test_booking_' . time(),
        'customer_email' => 'test@example.com',
        'description' => 'Test booking payment',
    );

    echo "Testing payment with gateway: $gateway_id<br>";
    echo "Amount: $" . $test_payment_data['amount'] . "<br>";

    // Note: This will fail without real Stripe keys, but tests the integration
    $result = $payment_handler->process_payment($gateway_id, $test_payment_data);

    if ($result['success']) {
        echo "✓ Payment processed successfully<br>";
        echo "Transaction ID: " . $result['transaction_id'] . "<br>";
    } else {
        echo "⚠️ Payment failed (expected in test environment): " . $result['error'] . "<br>";
    }
} else {
    echo "⚠️ Skipping payment test - no gateways enabled<br>";
}

// Test 6: Admin Columns
echo "<h2>Test 6: Admin Interface</h2>";
global $wpdb;
$booking_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'kh_booking'");
echo "✓ Current bookings in database: $booking_count<br>";

// Test 7: Logging System
echo "<h2>Test 7: Payment Logging</h2>";
if (class_exists('KH_Payment_Logger')) {
    KH_Payment_Logger::log('test', 'Payment integration test completed', 'info');
    echo "✓ Payment logging system active<br>";
} else {
    echo "❌ Payment logger not found<br>";
}

echo "<h2>Test Summary</h2>";
echo "<p>Payment integration test completed. Check the results above and configure your payment gateways in the admin settings.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>Configure Stripe API keys in KH Events → Settings → Payment</li>";
echo "<li>Create a test event with priced tickets</li>";
echo "<li>Use the [kh_event_booking_form] shortcode on a page</li>";
echo "<li>Test the complete booking and payment flow</li>";
echo "</ul>";