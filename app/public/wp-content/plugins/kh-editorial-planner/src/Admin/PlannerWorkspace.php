<?php

namespace KH\Planner\Admin;

/**
 * Class PlannerWorkspace
 * 
 * Handles the Admin UI for the Editorial Planner, enqueuing assets and 
 * rendering the React container.
 */
class PlannerWorkspace {

    public function init() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_menu() {
        add_menu_page(
            __('Editorial Planner', 'kh-editorial-planner'),
            __('Planner', 'kh-editorial-planner'),
            'edit_posts',
            'kh-editorial-planner',
            [$this, 'render_planner_page'],
            'dashicons-media-document',
            25
        );

        add_submenu_page(
            'kh-editorial-planner',
            __('New Session', 'kh-editorial-planner'),
            __('New Session', 'kh-editorial-planner'),
            'edit_posts',
            'kh-planner-new',
            [$this, 'render_new_session_page']
        );

        add_submenu_page(
            'kh-editorial-planner',
            __('Past Sessions', 'kh-editorial-planner'),
            __('Past Sessions', 'kh-editorial-planner'),
            'edit_posts',
            'kh-planner-sessions',
            [$this, 'render_sessions_page']
        );

        add_submenu_page(
            'kh-editorial-planner',
            __('Top-Line Categories', 'kh-editorial-planner'),
            __('Top-Line Categories', 'kh-editorial-planner'),
            'edit_posts',
            'kh-planner-categories',
            [$this, 'render_categories_page']
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'kh-planner') === false && strpos($hook, 'kh-editorial-planner') === false) {
            return;
        }

        wp_enqueue_style(
            'kh-planner-css',
            KH_PLANNER_PLUGIN_URL . 'assets/css/enhanced-dashboard.css',
            [],
            KH_PLANNER_VERSION
        );

        // Map hooks to their respective JS files
        $script_handle = '';
        $script_file = '';

        if (strpos($hook, 'kh-planner-new') !== false) {
            $script_handle = 'kh-planner-new-js';
            $script_file = 'assets/js/editorial-new-session.js';
        } elseif (strpos($hook, 'kh-planner-sessions') !== false) {
            $script_handle = 'kh-planner-sessions-js';
            $script_file = 'assets/js/editorial-sessions.js';
        } elseif (strpos($hook, 'kh-planner-categories') !== false) {
            $script_handle = 'kh-planner-categories-js';
            $script_file = 'assets/js/editorial-top-line-categories.js';
        } else {
            $script_handle = 'kh-planner-main-js';
            $script_file = 'assets/js/editorial-planner.js';
        }

        wp_enqueue_script(
            $script_handle,
            KH_PLANNER_PLUGIN_URL . $script_file,
            ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-data', 'wp-i18n'],
            KH_PLANNER_VERSION,
            true
        );

        wp_localize_script($script_handle, 'editorialData', [
            'apiRoot' => esc_url_raw(rest_url('editorial/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'user'    => get_current_user_id(),
        ]);
    }

    public function render_planner_page() {
        echo '<div id="editorial-planner-app"></div>';
    }

    public function render_new_session_page() {
        echo '<div id="editorial-new-session-app"></div>';
    }

    public function render_sessions_page() {
        echo '<div id="editorial-sessions-app"></div>';
    }

    public function render_categories_page() {
        echo '<div id="editorial-top-line-categories-app"></div>';
    }
}
