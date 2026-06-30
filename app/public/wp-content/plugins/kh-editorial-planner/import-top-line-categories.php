<?php
/**
 * Import script for Top-Line Categories
 */
require_once(ABSPATH . 'wp-load.php');

// Verify user capabilities
if (!current_user_can('manage_options')) {
    WP_CLI::error('Insufficient permissions');
}

// Get CSV file path from command line arguments
$csv_path = $_SERVER['argv'][2] ?? '';
if (empty($csv_path) || !file_exists($csv_path)) {
    WP_CLI::error('Invalid CSV file path');
}

// Read CSV data
$csv_data = file_get_contents($csv_path);
if (empty($csv_data)) {
    WP_CLI::error('Failed to read CSV file');
}

// Prepare API request
$request = new WP_REST_Request('POST', '/editorial/v1/planner/top-line-categories/import');
$request->set_header('Content-Type', 'application/json');
$request->set_body(json_encode(['csv' => $csv_data]));

// Process import
$response = rest_do_request($request);
$server = rest_get_server();
$data = $server->response_to_data($response, false);

if (is_wp_error($data)) {
    WP_CLI::error($data->get_error_message());
}

WP_CLI::success(sprintf(
    'Import complete: %d updated, %d skipped',
    $data['created_or_updated'] ?? 0,
    $data['skipped'] ?? 0
));