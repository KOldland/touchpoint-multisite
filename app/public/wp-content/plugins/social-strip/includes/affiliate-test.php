<?php
/**
 * Affiliate Program Activation Script
 * 
 * Test script to configure and activate the affiliate program
 */

if (!defined('ABSPATH')) exit;

/**
 * Set up default affiliate commission rates
 */
function kss_setup_affiliate_defaults() {
    // Set default commission rates if not already set
    $defaults = [
        'kss_affiliate_commission_rate' => 10,        // 10% for articles
        'kss_affiliate_membership_commission_rate' => 25,  // 25% for memberships 
        'kss_affiliate_gift_commission_rate' => 15,        // 15% for gifts
        'kss_affiliate_general_commission_rate' => 15      // 15% for general orders
    ];
    
    foreach ($defaults as $option => $value) {
        if (get_option($option) === false) {
            update_option($option, $value);
            echo "Set {$option} to {$value}%\n";
        }
    }
}

/**
 * Test affiliate URL generation
 */
function kss_test_affiliate_url_generation() {
    echo "Testing affiliate URL generation...\n";
    
    if (!khm_is_marketing_suite_ready()) {
        echo "❌ KHM Marketing Suite not available\n";
        return false;
    }
    
    // Test URL generation
    $test_url = 'https://touchpointreview.com/test-article';
    $test_code = 'TEST123';
    
    // Simulate affiliate URL generation
    $affiliate_url = add_query_arg(['ref' => $test_code], $test_url);
    
    if (strpos($affiliate_url, 'ref=' . $test_code) !== false) {
        echo "✅ Affiliate URL generation working: {$affiliate_url}\n";
        return true;
    } else {
        echo "❌ Affiliate URL generation failed\n";
        return false;
    }
}

/**
 * Test click tracking
 */
function kss_test_click_tracking() {
    echo "Testing click tracking...\n";
    
    if (!khm_is_marketing_suite_ready()) {
        echo "❌ KHM Marketing Suite not available\n";
        return false;
    }
    
    $test_code = 'TEST123';
    $test_post_id = 1;
    $test_ip = '127.0.0.1';
    $test_user_agent = 'Test Browser';
    
    // Test if click tracking function exists
    if (function_exists('khm_track_affiliate_click')) {
        $result = khm_track_affiliate_click($test_code, $test_post_id, $test_ip, $test_user_agent);
        if ($result) {
            echo "✅ Click tracking function working\n";
            return true;
        } else {
            echo "❌ Click tracking function returned false\n";
            return false;
        }
    } else {
        echo "❌ Click tracking function not found\n";
        return false;
    }
}

/**
 * Test conversion tracking
 */
function kss_test_conversion_tracking() {
    echo "Testing conversion tracking...\n";
    
    if (!khm_is_marketing_suite_ready()) {
        echo "❌ KHM Marketing Suite not available\n";
        return false;
    }
    
    $test_code = 'TEST123';
    $test_post_id = 1;
    $test_commission = 5.99;
    $test_type = 'test_purchase';
    
    // Test if conversion tracking function exists
    if (function_exists('khm_track_affiliate_conversion')) {
        $result = khm_track_affiliate_conversion($test_code, $test_post_id, $test_commission, $test_type);
        if ($result) {
            echo "✅ Conversion tracking function working\n";
            return true;
        } else {
            echo "❌ Conversion tracking function returned false\n";
            return false;
        }
    } else {
        echo "❌ Conversion tracking function not found\n";
        return false;
    }
}

/**
 * Check database tables
 */
function kss_check_affiliate_tables() {
    echo "Checking affiliate database tables...\n";
    
    global $wpdb;
    
    $required_tables = [
        'kh_affiliate_codes',
        'kh_affiliate_clicks', 
        'kh_affiliate_conversions',
        'kh_affiliate_generations',
        'kh_social_shares'
    ];
    
    $all_exist = true;
    
    foreach ($required_tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($exists) {
            echo "✅ Table {$table} exists\n";
        } else {
            echo "❌ Table {$table} missing\n";
            $all_exist = false;
        }
    }
    
    return $all_exist;
}

/**
 * Display current commission rates
 */
function kss_display_commission_rates() {
    echo "\nCurrent Commission Rates:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $rates = [
        'Articles' => get_option('kss_affiliate_commission_rate', 10),
        'Memberships' => get_option('kss_affiliate_membership_commission_rate', 25),
        'Gifts' => get_option('kss_affiliate_gift_commission_rate', 15),
        'General Orders' => get_option('kss_affiliate_general_commission_rate', 15)
    ];
    
    foreach ($rates as $type => $rate) {
        echo sprintf("%-15s: %s%%\n", $type, $rate);
    }
    echo "\n";
}

/**
 * Test affiliate dashboard functionality
 */
function kss_test_affiliate_dashboard() {
    echo "Testing affiliate dashboard...\n";
    
    // Check if user can generate affiliate code
    $test_user_id = 1; // Admin user
    
    if (function_exists('kss_get_user_affiliate_code')) {
        $affiliate_code = kss_get_user_affiliate_code($test_user_id);
        
        if ($affiliate_code) {
            echo "✅ Affiliate code generation working: {$affiliate_code}\n";
            
            // Test performance data
            if (function_exists('kss_get_affiliate_performance')) {
                $performance = kss_get_affiliate_performance($affiliate_code);
                echo "✅ Performance data retrieval working\n";
                echo "   Clicks: {$performance['clicks']}\n";
                echo "   Conversions: {$performance['conversions']}\n";
                echo "   Earnings: £{$performance['earnings']}\n";
                echo "   Conversion Rate: {$performance['conversion_rate']}%\n";
                return true;
            } else {
                echo "❌ Performance data function not found\n";
                return false;
            }
        } else {
            echo "❌ Affiliate code generation failed\n";
            return false;
        }
    } else {
        echo "❌ Affiliate code function not found\n";
        return false;
    }
}

/**
 * Run complete affiliate system test
 */
function kss_run_affiliate_system_test() {
    echo "🚀 AFFILIATE PROGRAM ACTIVATION TEST\n";
    echo "════════════════════════════════════\n\n";
    
    // Set up defaults
    kss_setup_affiliate_defaults();
    echo "\n";
    
    // Display current rates
    kss_display_commission_rates();
    
    // Run tests
    $tests = [
        'Database Tables' => 'kss_check_affiliate_tables',
        'URL Generation' => 'kss_test_affiliate_url_generation', 
        'Click Tracking' => 'kss_test_click_tracking',
        'Conversion Tracking' => 'kss_test_conversion_tracking',
        'Affiliate Dashboard' => 'kss_test_affiliate_dashboard'
    ];
    
    $passed = 0;
    $total = count($tests);
    
    foreach ($tests as $test_name => $test_function) {
        echo "\n{$test_name}:\n";
        echo str_repeat("-", strlen($test_name) + 1) . "\n";
        
        if (function_exists($test_function)) {
            $result = call_user_func($test_function);
            if ($result) {
                $passed++;
            }
        } else {
            echo "❌ Test function {$test_function} not found\n";
        }
    }
    
    echo "\n" . str_repeat("═", 40) . "\n";
    echo "RESULTS: {$passed}/{$total} tests passed\n";
    
    if ($passed === $total) {
        echo "🎉 AFFILIATE PROGRAM READY TO GO!\n";
        echo "\nNext steps:\n";
        echo "1. Share affiliate dashboard shortcode: [affiliate_dashboard]\n";
        echo "2. Test with real users and purchases\n";
        echo "3. Monitor conversion rates in admin\n";
    } else {
        echo "⚠️  Some tests failed - check configuration\n";
    }
    
    echo "\n";
}

// Auto-run test if accessed directly
if (defined('WP_CLI') && WP_CLI) {
    kss_run_affiliate_system_test();
}

// Add admin page for running tests
function kss_add_affiliate_test_page() {
    add_management_page(
        'Affiliate System Test',
        'Affiliate Test', 
        'manage_options',
        'affiliate-test',
        'kss_affiliate_test_page'
    );
}
add_action('admin_menu', 'kss_add_affiliate_test_page');

function kss_affiliate_test_page() {
    echo '<div class="wrap">';
    echo '<h1>Affiliate System Test</h1>';
    
    if (isset($_POST['run_test'])) {
        echo '<pre style="background: #f1f1f1; padding: 20px; border-radius: 5px;">';
        kss_run_affiliate_system_test();
        echo '</pre>';
    } else {
        echo '<p>Click the button below to test the affiliate system configuration.</p>';
        echo '<form method="post">';
        echo '<input type="submit" name="run_test" value="Run Affiliate System Test" class="button-primary">';
        echo '</form>';
    }
    
    echo '</div>';
}
?>