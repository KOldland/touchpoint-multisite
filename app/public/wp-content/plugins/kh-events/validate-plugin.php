<?php
/**
 * Simple KH Events Plugin Validation
 */

echo "KH Events Plugin Validation\n";
echo "===========================\n\n";

// Check file existence
$files_to_check = array(
    'kh-events.php',
    'includes/class-kh-events.php',
    'includes/class-kh-event-meta.php',
    'includes/class-kh-location-meta.php',
    'includes/class-kh-events-views.php',
    'includes/class-kh-event-tickets.php',
    'includes/class-kh-event-bookings.php',
    'includes/class-kh-recurring-events.php',
    'includes/class-kh-event-filters-widget.php',
    'includes/class-kh-events-admin-settings.php',
    'includes/class-kh-event-import-export.php',
    'includes/class-kh-event-rest-api.php',
    'includes/class-kh-event-status.php',
    'includes/class-kh-event-timezone.php',
    'assets/css/kh-events.css',
    'assets/js/kh-events.js',
    'assets/css/kh-events-admin.css',
    'assets/js/kh-events-admin.js',
    'assets/js/timezone-admin.js',
    'assets/js/timezone-frontend.js',
    'assets/css/timezone.css',
    'README.md'
);

echo "Checking file structure...\n";
foreach ($files_to_check as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file missing\n";
    }
}

echo "\nChecking PHP syntax...\n";
$php_files = array(
    'kh-events.php',
    'includes/class-kh-events.php',
    'includes/class-kh-event-meta.php',
    'includes/class-kh-location-meta.php',
    'includes/class-kh-events-views.php',
    'includes/class-kh-event-tickets.php',
    'includes/class-kh-event-bookings.php',
    'includes/class-kh-recurring-events.php',
    'includes/class-kh-event-filters-widget.php',
    'includes/class-kh-events-admin-settings.php',
    'includes/class-kh-event-import-export.php',
    'includes/class-kh-event-rest-api.php',
    'includes/class-kh-event-status.php',
    'includes/class-kh-event-timezone.php'
);

foreach ($php_files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $output = shell_exec("php -l \"$path\" 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "✓ $file syntax OK\n";
        } else {
            echo "✗ $file syntax error: $output\n";
        }
    }
}

echo "\nChecking for key features...\n";

// Check for post types
$content = file_get_contents(__DIR__ . '/includes/class-kh-events.php');
if (strpos($content, "register_post_type('kh_event'") !== false) {
    echo "✓ Event post type registration found\n";
} else {
    echo "✗ Event post type registration missing\n";
}

if (strpos($content, "register_post_type('kh_location'") !== false) {
    echo "✓ Location post type registration found\n";
} else {
    echo "✗ Location post type registration missing\n";
}

if (strpos($content, "register_post_type('kh_booking'") !== false) {
    echo "✓ Booking post type registration found\n";
} else {
    echo "✗ Booking post type registration missing\n";
}

// Check for taxonomies
if (strpos($content, "register_taxonomy('kh_event_category'") !== false) {
    echo "✓ Event category taxonomy found\n";
} else {
    echo "✗ Event category taxonomy missing\n";
}

if (strpos($content, "register_taxonomy('kh_event_tag'") !== false) {
    echo "✓ Event tag taxonomy found\n";
} else {
    echo "✗ Event tag taxonomy missing\n";
}

// Check for shortcodes
$views_content = file_get_contents(__DIR__ . '/includes/class-kh-events-views.php');
if (strpos($views_content, 'calendar_shortcode') !== false) {
    echo "✓ Calendar shortcode found\n";
} else {
    echo "✗ Calendar shortcode missing\n";
}

if (strpos($views_content, 'list_shortcode') !== false) {
    echo "✓ List shortcode found\n";
} else {
    echo "✗ List shortcode missing\n";
}

if (strpos($views_content, 'day_shortcode') !== false) {
    echo "✓ Day shortcode found\n";
} else {
    echo "✗ Day shortcode missing\n";
}

// Check for widget
$widget_content = file_get_contents(__DIR__ . '/includes/class-kh-event-filters-widget.php');
if (strpos($widget_content, 'class KH_Event_Filters_Widget') !== false) {
    echo "✓ Event filters widget found\n";
} else {
    echo "✗ Event filters widget missing\n";
}

// Check for recurring events
$recurring_content = file_get_contents(__DIR__ . '/includes/class-kh-recurring-events.php');
if (strpos($recurring_content, 'class KH_Recurring_Events') !== false) {
    echo "✓ Recurring events class found\n";
} else {
    echo "✗ Recurring events class missing\n";
}

// Check for admin settings
$admin_content = file_get_contents(__DIR__ . '/includes/class-kh-events-admin-settings.php');
if (strpos($admin_content, 'class KH_Events_Admin_Settings') !== false) {
    echo "✓ Admin settings class found\n";
} else {
    echo "✗ Admin settings class missing\n";
}

// Check for import/export functionality
$import_export_content = file_get_contents(__DIR__ . '/includes/class-kh-event-import-export.php');
if (strpos($import_export_content, 'class KH_Event_Import_Export') !== false) {
    echo "✓ Import/Export class found\n";
} else {
    echo "✗ Import/Export class missing\n";
}

// Check for REST API functionality
$rest_api_content = file_get_contents(__DIR__ . '/includes/class-kh-event-rest-api.php');
if (strpos($rest_api_content, 'class KH_Event_REST_API') !== false) {
    echo "✓ REST API class found\n";
} else {
    echo "✗ REST API class missing\n";
}

// Check for event status functionality
$status_content = file_get_contents(__DIR__ . '/includes/class-kh-event-status.php');
if (strpos($status_content, 'class KH_Event_Status') !== false) {
    echo "✓ Event Status class found\n";
} else {
    echo "✗ Event Status class missing\n";
}

// Check for timezone functionality
$timezone_content = file_get_contents(__DIR__ . '/includes/class-kh-event-timezone.php');
if (strpos($timezone_content, 'class KH_Event_Timezone') !== false) {
    echo "✓ Timezone class found\n";
} else {
    echo "✗ Timezone class missing\n";
}

echo "\nValidation complete!\n";
echo "\nNext recommended steps:\n";
echo "1. Test plugin activation in WordPress environment\n";
echo "2. Create sample events and test shortcodes\n";
echo "3. Test AJAX calendar navigation\n";
echo "4. Test booking system functionality\n";
echo "5. ✅ COMPLETED: Add admin settings page\n";
echo "6. ✅ COMPLETED: Implement import/export functionality\n";
echo "7. ✅ COMPLETED: Add REST API endpoints\n";
echo "8. ✅ COMPLETED: Implement event status management\n";
echo "9. ✅ COMPLETED: Add multi-timezone support\n";
echo "10. Test integration with other 1927MSuite plugins\n";
echo "11. Final production deployment\n";