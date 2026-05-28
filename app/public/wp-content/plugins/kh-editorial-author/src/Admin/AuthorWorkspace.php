<?php

namespace KH\EditorialAuthor\Admin;

/**
 * Class AuthorWorkspace
 * 
 * Handles the Admin UI for the Writing Studio / Authoring tools.
 */
class AuthorWorkspace {

    public function init() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_menu() {
        add_submenu_page(
            'kh-editorial-planner',
            __('Writing Studio', 'kh-editorial-author'),
            __('Author', 'kh-editorial-author'),
            'edit_posts',
            'kh-editorial-author',
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'kh-editorial-author') === false) {
            return;
        }

        wp_enqueue_script(
            'kh-author-react',
            plugin_dir_url(dirname(__DIR__, 2)) . 'assets/js/editorial-frameworks.js',
            ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-data', 'wp-i18n'],
            '1.0.0',
            true
        );

        wp_localize_script('kh-author-react', 'authorData', [
            'apiRoot' => esc_url_raw(rest_url('editorial/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    public function render_page() {
        echo '<div id="editorial-frameworks-app"></div>';
    }
}
