<?php
// import-exported-json-dev1.php
// Usage: php import-exported-json-dev1.php path/to/export.json

if ($argc < 2) {
    echo "Usage: php import-exported-json-dev1.php INPUT.json\n";
    exit(1);
}

$in = $argv[1];
if (! file_exists($in)) { echo "File not found: $in\n"; exit(2); }

// load WP (Local site)
require_once __DIR__ . '/wp-load.php';

// read & decode
$raw = file_get_contents($in);
$json = json_decode($raw, true);
if ($json === null) {
    echo "JSON decode failed: " . json_last_error_msg() . "\n";
    exit(3);
}

// find title, type, conditions, and elementor data
$orig_title = isset($json['title']) ? $json['title'] : (isset($json['name']) ? $json['name'] : basename($in));
$title = "Dev Site 1 - " . $orig_title;

$type = isset($json['type']) ? $json['type'] : (isset($json['template_type']) ? $json['template_type'] : '');
$conditions = isset($json['conditions']) ? $json['conditions'] : (isset($json['display_conditions']) ? $json['display_conditions'] : '');

// elementor layout payload: prefer 'data' (our wrapper), otherwise 'elementor', otherwise full object
$elementor_payload = null;
if (isset($json['data'])) {
    $elementor_payload = $json['data'];
} elseif (isset($json['elementor'])) {
    $elementor_payload = $json['elementor'];
} else {
    $elementor_payload = $json;
}

// Ensure _elementor_data is a JSON string (Elementor stores it as JSON string)
if (is_string($elementor_payload)) {
    $elementor_data = $elementor_payload;
} else {
    $elementor_data = json_encode($elementor_payload, JSON_UNESCAPED_SLASHES);
    if ($elementor_data === false) {
        echo "ERROR: Failed to json_encode elementor payload\n";
        exit(4);
    }
}

// Create a draft elementor_library post
$postarr = array(
    'post_title'  => $title,
    'post_type'   => 'elementor_library',
    'post_status' => 'draft',
);

$post_id = wp_insert_post($postarr);

if (! $post_id) {
    echo "Failed to create elementor_library post\n";
    exit(5);
}

// Save meta
update_post_meta($post_id, '_elementor_data', $elementor_data);

// Template type
if (! empty($type)) update_post_meta($post_id, '_elementor_template_type', $type);

// Display conditions if present
if (! empty($conditions)) update_post_meta($post_id, '_elementor_conditions', $conditions);

echo "Imported: {$in} -> elementor_library post ID {$post_id} (title: {$title})\n";
