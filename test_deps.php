<?php
require_once 'app/public/wp-load.php';
wp_enqueue_script('khm-geo-suggest-plugin');
echo "Script enqueued\n";
global $wp_scripts;
$deps = array('react', 'wp-api-fetch', 'wp-blocks', 'wp-components', 'wp-data', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins', 'wp-primitives');
foreach ($deps as $dep) {
    if (!isset($wp_scripts->registered[$dep])) {
        echo 'Missing dependency: ' . $dep . "\n";
    }
}
echo "All dependencies present\n";
?>
