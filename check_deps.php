<?php
require_once 'app/public/wp-load.php';
global $wp_scripts;
$deps = array('react', 'wp-api-fetch', 'wp-blocks', 'wp-components', 'wp-data', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins', 'wp-primitives');
$missing = array();
foreach ($deps as $dep) {
    if (!isset($wp_scripts->registered[$dep])) {
        $missing[] = $dep;
    }
}
if (empty($missing)) {
    echo 'All dependencies are registered' . PHP_EOL;
} else {
    echo 'Missing dependencies: ' . implode(', ', $missing) . PHP_EOL;
}
?>
