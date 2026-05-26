#!/bin/bash

echo "=========================================="
echo "Editorial Planner Flow Test"
echo "=========================================="
echo ""

cd "/Users/krisoldland/Local Sites/touchpoint-template/app/public"

# Test 1: Check if post type is registered
echo "Test 1: Checking planner_session post type registration..."
wp post-type list --allow-root 2>&1 | grep planner_session > /dev/null && echo "✓ planner_session post type found" || echo "✗ planner_session post type NOT found"
echo ""

# Test 2: Check meta field registration
echo "Test 2: Checking meta fields..."
wp eval 'foreach(["audience","angle","key_messages","framework","geo","tone","word_count","status"] as $f){echo registered_meta_key_exists("post",$f,"planner_session") ? "✓ $f\n" : "✗ $f\n";}' --allow-root
echo ""

# Test 3: Check JS file
echo "Test 3: Checking editorial-planner.js file..."
if [ -f "wp-content/plugins/khm-plugin/assets/js/editorial-planner.js" ]; then
    size=$(stat -f%z "wp-content/plugins/khm-plugin/assets/js/editorial-planner.js" 2>/dev/null || stat -c%s "wp-content/plugins/khm-plugin/assets/js/editorial-planner.js")
    echo "✓ File exists ($size bytes)"
    echo ""
    echo "Checking for key functions..."
    grep -q "startNewSession" wp-content/plugins/khm-plugin/assets/js/editorial-planner.js && echo "✓ startNewSession function" || echo "✗ startNewSession function"
    grep -q "loadSession" wp-content/plugins/khm-plugin/assets/js/editorial-planner.js && echo "✓ loadSession function" || echo "✗ loadSession function"
    grep -q "saveSession" wp-content/plugins/khm-plugin/assets/js/editorial-planner.js && echo "✓ saveSession function" || echo "✗ saveSession function"
    grep -q "returnToDashboard" wp-content/plugins/khm-plugin/assets/js/editorial-planner.js && echo "✓ returnToDashboard function" || echo "✗ returnToDashboard function"
    grep -q "session_id" wp-content/plugins/khm-plugin/assets/js/editorial-planner.js && echo "✓ Uses session_id (not id)" || echo "✗ Still using old 'id' field"
else
    echo "✗ File NOT found"
fi
echo ""

# Test 4: Test the EP endpoint existence
echo "Test 4: Checking Editorial Planner REST endpoints..."
wp eval 'global $wp_rest_server; $routes = rest_get_routes(); echo isset($routes["\/ep\/v1\/start"]) ? "✓ /ep/v1/start endpoint exists\n" : "✗ /ep/v1/start endpoint NOT found\n";' --allow-root
echo ""

# Test 5: Check PHP syntax
echo "Test 5: Checking PHP syntax..."
php -l wp-content/plugins/khm-plugin/khm-plugin.php > /dev/null 2>&1 && echo "✓ khm-plugin.php syntax OK" || echo "✗ khm-plugin.php has syntax errors"
echo ""

# Test 6: Create a test session
echo "Test 6: Creating test session..."
wp eval '
$db_handler = new Dual_GPT_DB_Handler();
$session_data = [
    "idempotency_key" => "test-flow-" . time(),
    "created_by" => 1,
    "status" => "queued",
    "meta" => json_encode(["broad_focus" => "Test Session"])
];
$session_id = $db_handler->insert_session($session_data);
if (is_wp_error($session_id)) {
    echo "✗ Failed: " . $session_id->get_error_message() . "\n";
} else {
    echo "✓ Session created with ID: $session_id\n";
}
' --allow-root 2>&1
echo ""

echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo "All checks should show ✓ for the flow to work"
echo ""
echo "Flow walkthrough:"
echo "1. User visits Editorial Planner Dashboard"
echo "2. Enters 'Broad Focus' text"
echo "3. Clicks 'Start New Session'"
echo "4. JS calls POST /wp-json/ep/v1/start"
echo "5. Server returns {session_id, job_ids, status, message}"
echo "6. JS redirects to ?page=editorial_planner&session_id={uuid}&auto_open_editor=1"
echo "7. Page loads with session editor"
echo "8. User fills fields and clicks Save"
echo "9. Data stored to localStorage"
echo "10. User can click 'Back to Dashboard' to return"
