<?php
require_once __DIR__ . '/wp-load.php';
$title = $argv[1] ?? 'Recovered Template';
$data_file = $argv[2] ?? null;
$type_file = $argv[3] ?? null;
$cond_file = $argv[4] ?? null;
if (!$data_file || !file_exists($data_file)) {
    echo "Usage: php import-template.php \"Title\" path/to/data.json [path/to/type.txt] [path/to/conditions.json]\n";
    exit(1);
}
$post_id = wp_insert_post([
  'post_title'  => $title,
  'post_status' => 'draft',
  'post_type'   => 'elementor_library',
]);
if (!$post_id) { echo "Failed to create post\n"; exit(1); }
$data = file_get_contents($data_file);
update_post_meta($post_id, '_elementor_data', $data);
if ($type_file && file_exists($type_file)) {
    $type = trim(file_get_contents($type_file));
    if ($type !== '') update_post_meta($post_id, '_elementor_template_type', $type);
}
if ($cond_file && file_exists($cond_file) && filesize($cond_file) > 0) {
    $conds = file_get_contents($cond_file);
    update_post_meta($post_id, '_elementor_conditions', $conds);
}
echo "Imported '{$title}' as post ID {$post_id}\n";
