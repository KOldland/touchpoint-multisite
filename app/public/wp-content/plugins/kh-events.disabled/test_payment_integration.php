<?php
/**
 * Test Payment Integration for KH Events
 * Simple test without full WordPress loading
 */

// Load composer dependencies
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

echo "Testing Payment Gateway Classes...\n";

// Define minimal constants for testing
define('KH_EVENTS_DIR', dirname(__FILE__) . '/');
define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');

// Include the payment gateways class
require_once KH_EVENTS_DIR . 'includes/class-kh-payment-gateways.php';

echo "Payment gateway classes loaded successfully.\n";

// Test abstract gateway class exists
if (class_exists('KH_Payment_Gateway')) {
    echo "KH_Payment_Gateway abstract class exists.\n";
} else {
    echo "ERROR: KH_Payment_Gateway class not found.\n";
}

// Test Stripe gateway class exists
if (class_exists('KH_Stripe_Gateway')) {
    echo "KH_Stripe_Gateway class exists.\n";
} else {
    echo "ERROR: KH_Stripe_Gateway class not found.\n";
}

// Test PayPal gateway class exists
if (class_exists('KH_PayPal_Gateway')) {
    echo "KH_PayPal_Gateway class exists.\n";
} else {
    echo "ERROR: KH_PayPal_Gateway class not found.\n";
}

// Test payment handler class exists
if (class_exists('KH_Payment_Handler')) {
    echo "KH_Payment_Handler class exists.\n";
} else {
    echo "ERROR: KH_Payment_Handler class not found.\n";
}

// Test logger class exists
if (class_exists('KH_Payment_Logger')) {
    echo "KH_Payment_Logger class exists.\n";
} else {
    echo "ERROR: KH_Payment_Logger class not found.\n";
}

// Test Stripe library availability
if (class_exists('\Stripe\StripeClient')) {
    echo "✓ Stripe\\StripeClient class available\n";
} else {
    echo "✗ Stripe\\StripeClient class not found\n";
}

echo "\nBasic class loading test completed.\n";