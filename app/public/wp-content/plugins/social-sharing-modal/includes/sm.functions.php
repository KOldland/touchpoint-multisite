<?php

// Register scripts and styles
function ssm_enqueue_assets() {
    // Corrected paths to match assets/css and assets/js structure
    wp_enqueue_style('ssm-modal-style', plugin_dir_url(__FILE__) . '../assets/css/social-modal.css');
    wp_enqueue_script('ssm-modal-script', plugin_dir_url(__FILE__) . '../assets/js/modal.js', ['jquery'], null, true);
}
add_action('wp_enqueue_scripts', 'ssm_enqueue_assets');
