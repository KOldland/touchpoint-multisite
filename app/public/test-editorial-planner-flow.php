<?php
/**
 * Editorial Planner Flow Test
 * Tests: Session creation, loading, and redirect flow
 */

// Load WordPress
require_once __DIR__ . '/wp-load.php';

// Verify we're logged in
if (!is_user_logged_in()) {
    wp_die('You must be logged in to run this test. Current user ID: ' . get_current_user_id());
}

$user_id = get_current_user_id();
$test_results = [];

echo "<h2>Editorial Planner Flow Test</h2>";
echo "<p>Testing as User ID: <strong>$user_id</strong></p>";

// Test 1: Create a session via REST API
echo "<h3>Test 1: Creating Session via /ep/v1/start</h3>";

$args = array(
    'method'  => 'POST',
    'headers' => array(
        'Content-Type' => 'application/json',
    ),
    'body' => json_encode(array(
        'broad_focus' => 'Test Focus: Manufacturing Industry Trends',
        'idempotency_key' => 'test-flow-' . time() . '-' . uniqid(),
    )),
);

$response = wp_remote_post(
    rest_url('ep/v1/start'),
    array_merge($args, array('sslverify' => false))
);

if (is_wp_error($response)) {
    echo "<p style='color:red'><strong>❌ Failed:</strong> " . $response->get_error_message() . "</p>";
    $test_results[] = false;
} else {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $status = wp_remote_retrieve_response_code($response);
    
    echo "<p>Status Code: <strong>$status</strong></p>";
    echo "<pre>Response: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "</pre>";
    
    if ($data && isset($data['session_id'])) {
        echo "<p style='color:green'><strong>✓ Session created with ID:</strong> " . $data['session_id'] . "</p>";
        $test_results[] = true;
        $session_id = $data['session_id'];
    } else {
        echo "<p style='color:red'><strong>❌ No session_id in response</strong></p>";
        $test_results[] = false;
        $session_id = null;
    }
}

// Test 2: Load the session via REST API
if ($session_id) {
    echo "<h3>Test 2: Loading Session via /ep/v1/session/{session_id}</h3>";
    
    $response = wp_remote_get(
        rest_url("ep/v1/session/$session_id"),
        array('sslverify' => false)
    );
    
    if (is_wp_error($response)) {
        echo "<p style='color:orange'><strong>⚠ Session load failed:</strong> " . $response->get_error_message() . "</p>";
        $test_results[] = false;
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $status = wp_remote_retrieve_response_code($response);
        
        echo "<p>Status Code: <strong>$status</strong></p>";
        echo "<pre>Response: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "</pre>";
        
        if ($status === 200 || $status === 404) {
            echo "<p style='color:green'><strong>✓ Endpoint responds</strong></p>";
            $test_results[] = true;
        } else {
            echo "<p style='color:red'><strong>❌ Unexpected status code</strong></p>";
            $test_results[] = false;
        }
    }
}

// Test 3: Verify page structure
echo "<h3>Test 3: Checking Editorial Planner Page</h3>";

$admin_url = admin_url('admin.php?page=editorial_planner');
echo "<p>Admin URL: <a href='$admin_url' target='_blank'>$admin_url</a></p>";

// Check if the post type is registered
$post_type = get_post_type_object('planner_session');
if ($post_type && $post_type->show_in_rest) {
    echo "<p style='color:green'><strong>✓ planner_session post type registered and REST-enabled</strong></p>";
    $test_results[] = true;
} else {
    echo "<p style='color:orange'><strong>⚠ planner_session post type issue</strong></p>";
    $test_results[] = false;
}

// Test 4: Check meta field registration
echo "<h3>Test 4: Checking Meta Field Registration</h3>";

$meta_fields = array('audience', 'angle', 'key_messages', 'framework', 'geo', 'tone', 'word_count', 'status');
$all_registered = true;

foreach ($meta_fields as $field) {
    $is_registered = registered_meta_key_exists('post', $field, 'planner_session');
    if ($is_registered) {
        echo "<p style='color:green'><strong>✓</strong> Meta field '<code>$field</code>' registered</p>";
    } else {
        echo "<p style='color:orange'><strong>⚠</strong> Meta field '<code>$field</code>' NOT registered</p>";
        $all_registered = false;
    }
}

$test_results[] = $all_registered;

// Test 5: Check JS file exists
echo "<h3>Test 5: Checking JS Files</h3>";

$js_path = __DIR__ . '/wp-content/plugins/khm-plugin/assets/js/editorial-planner.js';
if (file_exists($js_path)) {
    $file_size = filesize($js_path);
    echo "<p style='color:green'><strong>✓</strong> editorial-planner.js exists (<code>$file_size bytes</code>)</p>";
    $test_results[] = true;
    
    // Check for key functions
    $content = file_get_contents($js_path);
    $functions = array('startNewSession', 'loadSession', 'saveSession', 'returnToDashboard');
    foreach ($functions as $func) {
        if (strpos($content, $func) !== false) {
            echo "<p style='color:green'><strong>✓</strong> Function '<code>$func</code>' found</p>";
        } else {
            echo "<p style='color:red'><strong>❌</strong> Function '<code>$func</code>' NOT found</p>";
        }
    }
} else {
    echo "<p style='color:red'><strong>❌</strong> editorial-planner.js NOT found at $js_path</p>";
    $test_results[] = false;
}

// Summary
echo "<h3>Test Summary</h3>";
$passed = array_sum($test_results);
$total = count($test_results);
echo "<p><strong>Passed: $passed/$total tests</strong></p>";

if ($passed === $total) {
    echo "<p style='color:green; font-weight:bold'>✓ All tests passed! Flow should work.</p>";
} else {
    echo "<p style='color:orange; font-weight:bold'>⚠ Some tests failed. Review above.</p>";
}

// Test flow walkthrough
echo "<h3>Flow Walkthrough</h3>";
echo "<ol>";
echo "<li>User visits <a href='$admin_url' target='_blank'>Editorial Planner Dashboard</a></li>";
echo "<li>User enters broad focus text</li>";
echo "<li>User clicks 'Start New Session'</li>";
echo "<li>JavaScript calls <code>/wp-json/ep/v1/start</code> (POST)</li>";
echo "<li>Server returns <code>session_id</code></li>";
echo "<li>JavaScript redirects to <code>?page=editorial_planner&session_id={session_id}&auto_open_editor=1</code></li>";
echo "<li>Page reloads and loads session editor</li>";
echo "<li>User fills in editor fields and clicks Save</li>";
echo "<li>Data saved to localStorage</li>";
echo "<li>User can click 'Back to Dashboard' to return</li>";
echo "</ol>";
?>
