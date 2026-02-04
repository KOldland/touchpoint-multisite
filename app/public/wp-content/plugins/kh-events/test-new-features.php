<?php
/**
 * Simple test script for KH-Events new features
 * This checks file structure without loading WordPress
 */

echo "Testing KH-Events new features implementation...\n\n";

// Check if files exist
$files_to_check = array(
    'includes/class-kh-events-views.php',
    'assets/js/kh-events.js',
    'assets/css/kh-events.css'
);

foreach ($files_to_check as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path)) {
        echo "✓ File exists: $file\n";
    } else {
        echo "✗ File missing: $file\n";
    }
}

// Check for new methods in views class
echo "\nChecking for new methods in KH_Events_Views class...\n";

$views_file = __DIR__ . '/includes/class-kh-events-views.php';
if (file_exists($views_file)) {
    $content = file_get_contents($views_file);

    $methods_to_check = array(
        'search_shortcode',
        'submit_shortcode',
        'dashboard_shortcode',
        'ajax_search_events',
        'ajax_submit_event',
        'ajax_get_dashboard_stats',
        'ajax_get_user_events'
    );

    foreach ($methods_to_check as $method) {
        if (strpos($content, "public function $method") !== false) {
            echo "✓ Method found: $method\n";
        } else {
            echo "✗ Method missing: $method\n";
        }
    }
}

// Check for shortcode registrations
echo "\nChecking for shortcode registrations...\n";

$shortcodes_to_check = array(
    'kh_events_search',
    'kh_events_submit',
    'kh_events_dashboard'
);

foreach ($shortcodes_to_check as $shortcode) {
    if (strpos($content, "add_shortcode('$shortcode'") !== false) {
        echo "✓ Shortcode registration found: $shortcode\n";
    } else {
        echo "✗ Shortcode registration missing: $shortcode\n";
    }
}

// Check JavaScript additions
echo "\nChecking JavaScript additions...\n";

$js_file = __DIR__ . '/assets/js/kh-events.js';
if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);

    $js_features = array(
        'kh-search-input',
        'kh-submit-form',
        'kh-events-dashboard',
        'kh_search_events',
        'kh_submit_event',
        'kh_get_dashboard_stats'
    );

    foreach ($js_features as $feature) {
        if (strpos($js_content, $feature) !== false) {
            echo "✓ JavaScript feature found: $feature\n";
        } else {
            echo "✗ JavaScript feature missing: $feature\n";
        }
    }
}

// Check CSS additions
echo "\nChecking CSS additions...\n";

$css_file = __DIR__ . '/assets/css/kh-events.css';
if (file_exists($css_file)) {
    $css_content = file_get_contents($css_file);

    $css_classes = array(
        'kh-events-search',
        'kh-event-submit',
        'kh-events-dashboard',
        'kh-search-form',
        'kh-submit-form',
        'kh-dashboard-stats'
    );

    foreach ($css_classes as $class) {
        if (strpos($css_content, ".$class") !== false) {
            echo "✓ CSS class found: $class\n";
        } else {
            echo "✗ CSS class missing: $class\n";
        }
    }
}

echo "\nTest completed!\n";
echo "Note: This is a basic file structure test. Full functionality testing requires WordPress environment.\n";