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
        add_filter('script_loader_tag', [$this, 'add_module_type_to_script'], 10, 2);
    }

    /**
     * Add type="module" to ES module scripts.
     */
    public function add_module_type_to_script($tag, $handle) {
        $module_handles = [
            'kh-planner-main-js',
            'kh-planner-new-js',
            'kh-planner-sessions-js',
            'kh-planner-categories-js',
            'kh-planner-content-gaps-js',
        ];
        if (in_array($handle, $module_handles, true)) {
            $tag = str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }

    public function register_menu() {
        // Main Editorial Planner page - top-level menu to match expected URL slug
        add_menu_page(
            __('Editorial Planner', 'kh-editorial-planner'),
            __('Editorial Planner', 'kh-editorial-planner'),
            'edit_posts',
            'editorial_planner',
            [$this, 'render_planner_page'],
            'dashicons-admin-page',
            6
        );

        add_submenu_page(
            'editorial_planner',
            __('New Session', 'kh-editorial-planner'),
            __('New Session', 'kh-editorial-planner'),
            'edit_posts',
            'kh-planner-new',
            [$this, 'render_new_session_page']
        );

        add_submenu_page(
            'editorial_planner',
            __('Past Sessions', 'kh-editorial-planner'),
            __('Past Sessions', 'kh-editorial-planner'),
            'edit_posts',
            'kh-planner-sessions',
            [$this, 'render_sessions_page']
        );

        add_submenu_page(
            'editorial_planner',
            __('Top-Line Categories', 'kh-editorial-planner'),
            __('Top-Line Categories', 'kh-editorial-planner'),
            'edit_posts',
            'kh-planner-categories',
            [$this, 'render_categories_page']
        );

        add_submenu_page(
            'editorial_planner',
            __('Content Gaps', 'kh-editorial-planner'),
            __('Content Gaps', 'kh-editorial-planner'),
            'edit_posts',
            'kh-planner-content-gaps',
            [$this, 'render_content_gaps_page']
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'kh-planner') === false && strpos($hook, 'kh-editorial-planner') === false && strpos($hook, 'editorial_planner') === false) {
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
            $script_file = 'assets/js/build/editorial-new-session.js';
        } elseif (strpos($hook, 'kh-planner-sessions') !== false) {
            $script_handle = 'kh-planner-sessions-js';
            $script_file = 'assets/js/build/editorial-sessions.js';
        } elseif (strpos($hook, 'kh-planner-categories') !== false) {
            $script_handle = 'kh-planner-categories-js';
            $script_file = 'assets/js/build/editorial-top-line-categories.js';
        } elseif (strpos($hook, 'kh-planner-content-gaps') !== false) {
            $script_handle = 'kh-planner-content-gaps-js';
            $script_file = 'assets/js/build/editorial-content-gaps.js';
        } else {
            $script_handle = 'kh-planner-main-js';
            $script_file = 'assets/js/build/editorial-planner.js';
        }

        wp_enqueue_script(
            $script_handle,
            KH_PLANNER_PLUGIN_URL . $script_file,
            ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-data', 'wp-i18n'],
            KH_PLANNER_VERSION,
            true
        );

        wp_localize_script($script_handle, 'editorialData', [
            'apiRoot'  => esc_url_raw(rest_url('editorial/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'user'     => get_current_user_id(),
            'adminUrl' => esc_url_raw(admin_url()),
        ]);
    }

    public function render_planner_page() {
        echo '<div id="editorial-planner-app" aria-live="polite">
            <div class="kh-planner-loading">
                <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                <span>Loading Editorial Planner...</span>
            </div>
        </div>';
    }

    public function render_new_session_page() {
        echo '<div id="editorial-new-session-app" aria-live="polite">
            <div class="kh-planner-loading">
                <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                <span>Loading New Session form...</span>
            </div>
        </div>';
    }

    public function render_sessions_page() {
        echo '<div id="editorial-sessions-app" aria-live="polite">
            <div class="kh-planner-loading">
                <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                <span>Loading sessions list...</span>
            </div>
        </div>';
    }

    public function render_categories_page() {
        echo '<div id="editorial-top-line-categories-app" aria-live="polite">
            <div class="kh-planner-loading">
                <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                <span>Loading Top-Line Categories...</span>
            </div>
        </div>';
    }

    public function render_content_gaps_page() {
        echo '<div id="editorial-content-gaps-app" aria-live="polite">
            <div class="kh-planner-loading">
                <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                <span>Loading Content Gap Analysis...</span>
            </div>
        </div>';
    }
}

