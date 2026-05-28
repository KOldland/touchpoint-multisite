<?php

namespace KH\Editorial\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EditorialAdmin {

    public function init() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dashboard_assets' ] );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Editorial Admin', 'kh-editorial-intelligence' ),
            __( 'Editorial Admin', 'kh-editorial-intelligence' ),
            'manage_options',
            'kh-editorial-admin',
            [ $this, 'render_dashboard_page' ],
            'dashicons-admin-generic',
            3
        );

        add_submenu_page(
            'kh-editorial-admin',
            __( 'Status Dashboard', 'kh-editorial-intelligence' ),
            __( 'Status Dashboard', 'kh-editorial-intelligence' ),
            'manage_options',
            'kh-editorial-admin',
            [ $this, 'render_dashboard_page' ]
        );

        add_submenu_page(
            'kh-editorial-admin',
            __( 'API Settings', 'kh-editorial-intelligence' ),
            __( 'API Settings', 'kh-editorial-intelligence' ),
            'manage_options',
            'kh-editorial-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'kh-editorial-admin',
            __( 'Rate Limits', 'kh-editorial-intelligence' ),
            __( 'Rate Limits', 'kh-editorial-intelligence' ),
            'manage_options',
            'kh-editorial-rate-limits',
            [ $this, 'render_rate_limits_page' ]
        );

        add_submenu_page(
            'kh-editorial-admin',
            __( 'Database Init', 'kh-editorial-intelligence' ),
            __( 'Database Init', 'kh-editorial-intelligence' ),
            'manage_options',
            'kh-editorial-db',
            [ $this, 'render_db_init_page' ]
        );

        // Planner Bridge (Temporary sub-menu items until Workspace is created)
        add_submenu_page(
            'kh-editorial-admin',
            __( 'Editorial Planner', 'kh-editorial-intelligence' ),
            __( '--- Workspace ---', 'kh-editorial-intelligence' ),
            'edit_posts',
            'editorial_planner',
            [ $this, 'render_planner_page' ]
        );
    }

    public function render_dashboard_page() {
        $llm_configured = \KH\Editorial\Core\LLMService::is_configured();
        $seo_active = function_exists('khm_seo');
        $geo_active = class_exists('\KHM\GEO\SuggestAnswerCardsEndpoint');
        ?>
        <div class="wrap">
            <h1>Editorial Suite Status</h1>
            <div class="welcome-panel" style="padding: 20px;">
                <div class="welcome-panel-column-container">
                    <div class="welcome-panel-column">
                        <h3>Core Infrastructure</h3>
                        <ul>
                            <li>
                                <?php echo $llm_configured ? '✅' : '❌'; ?> 
                                <strong>LLM API:</strong> <?php echo $llm_configured ? 'Configured' : 'Not Configured'; ?>
                            </li>
                            <li>
                                <?php echo $seo_active ? '✅' : '❌'; ?> 
                                <strong>SEO Engine:</strong> <?php echo $seo_active ? 'Active' : 'Inactive'; ?>
                            </li>
                            <li>
                                <?php echo $geo_active ? '✅' : '❌'; ?> 
                                <strong>GEO Researcher:</strong> <?php echo $geo_active ? 'Active' : 'Inactive'; ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings_page() {
        if ( isset( $_POST['kh_editorial_save_settings'] ) && check_admin_referer( 'kh_editorial_settings', 'kh_editorial_nonce' ) ) {
            $settings = [
                'openai_api_key'     => sanitize_text_field( $_POST['openai_api_key'] ),
                'openai_model'       => sanitize_text_field( $_POST['openai_model'] ),
                'dataforseo_login'    => sanitize_text_field( $_POST['dataforseo_login'] ),
                'dataforseo_password' => sanitize_text_field( $_POST['dataforseo_password'] ),
                'serpapi_key'        => sanitize_text_field( $_POST['serpapi_key'] ),
                'tavily_key'         => sanitize_text_field( $_POST['tavily_key'] ),
                'search_primary'     => sanitize_text_field( $_POST['search_primary'] ),
            ];
            update_option( 'kh_editorial_settings', $settings );
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $settings = get_option( 'kh_editorial_settings', [
            'openai_api_key'     => '',
            'openai_model'       => 'gpt-4o-mini',
            'dataforseo_login'    => '',
            'dataforseo_password' => '',
            'serpapi_key'        => '',
            'tavily_key'         => '',
            'search_primary'     => 'serpapi',
        ] );

        ?>
        <div class="wrap">
            <h1>Suite API Settings</h1>
            <form method="post">
                <?php wp_nonce_field( 'kh_editorial_settings', 'kh_editorial_nonce' ); ?>
                
                <h2>LLM Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="openai_api_key">OpenAI API Key</label></th>
                        <td>
                            <input name="openai_api_key" type="password" id="openai_api_key" value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>" class="regular-text">
                            <p class="description">Global key used by all AI agents in the editorial suite.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="openai_model">Default Model</label></th>
                        <td>
                            <select name="openai_model" id="openai_model">
                                <option value="gpt-4o" <?php selected( $settings['openai_model'], 'gpt-4o' ); ?>>GPT-4o</option>
                                <option value="gpt-4o-mini" <?php selected( $settings['openai_model'], 'gpt-4o-mini' ); ?>>GPT-4o-mini</option>
                                <option value="o1-preview" <?php selected( $settings['openai_model'], 'o1-preview' ); ?>>o1-preview</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2>DataForSEO (Keywords)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="dataforseo_login">Login</label></th>
                        <td><input name="dataforseo_login" type="text" id="dataforseo_login" value="<?php echo esc_attr( $settings['dataforseo_login'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dataforseo_password">Password</label></th>
                        <td><input name="dataforseo_password" type="password" id="dataforseo_password" value="<?php echo esc_attr( $settings['dataforseo_password'] ); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <h2>Search Providers (SERP)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="search_primary">Primary Search Provider</label></th>
                        <td>
                            <select name="search_primary" id="search_primary">
                                <option value="serpapi" <?php selected( $settings['search_primary'], 'serpapi' ); ?>>SerpAPI (Google)</option>
                                <option value="tavily" <?php selected( $settings['search_primary'], 'tavily' ); ?>>Tavily (AI Search)</option>
                                <option value="dataforseo" <?php selected( $settings['search_primary'], 'dataforseo' ); ?>>DataForSEO</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="serpapi_key">SerpAPI Key</label></th>
                        <td><input name="serpapi_key" type="password" id="serpapi_key" value="<?php echo esc_attr( $settings['serpapi_key'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tavily_key">Tavily Key</label></th>
                        <td><input name="tavily_key" type="password" id="tavily_key" value="<?php echo esc_attr( $settings['tavily_key'] ); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="kh_editorial_save_settings" id="submit" class="button button-primary" value="Save Credentials">
                </p>
            </form>
        </div>
        <?php
    }

    public function render_rate_limits_page() {
        if ( isset( $_POST['kh_editorial_save_limits'] ) && check_admin_referer( 'kh_editorial_limits', 'kh_editorial_nonce' ) ) {
            update_option( 'kh_editorial_limit_minute', absint( $_POST['limit_minute'] ) );
            update_option( 'kh_editorial_limit_day', absint( $_POST['limit_day'] ) );
            update_option( 'kh_editorial_limit_exempt_admins', isset( $_POST['exempt_admins'] ) );
            echo '<div class="notice notice-success"><p>Limits updated.</p></div>';
        }

        $min = get_option( 'kh_editorial_limit_minute', 3 );
        $day = get_option( 'kh_editorial_limit_day', 50 );
        $exempt = get_option( 'kh_editorial_limit_exempt_admins', true );

        ?>
        <div class="wrap">
            <h1>Global Rate Limits</h1>
            <p>Control AI token consumption across the editorial suite.</p>
            <form method="post">
                <?php wp_nonce_field( 'kh_editorial_limits', 'kh_editorial_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Requests Per Minute</th>
                        <td><input name="limit_minute" type="number" value="<?php echo esc_attr($min); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Requests Per Day</th>
                        <td><input name="limit_day" type="number" value="<?php echo esc_attr($day); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Exempt Admins</th>
                        <td><input name="exempt_admins" type="checkbox" <?php checked($exempt); ?>></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="kh_editorial_save_limits" class="button button-primary" value="Save Limits">
                </p>
            </form>
        </div>
        <?php
    }

    public function render_db_init_page() {
        if ( isset( $_POST['kh_editorial_init_db'] ) && check_admin_referer( 'kh_editorial_db', 'kh_editorial_nonce' ) ) {
            \KH\Editorial\Database\AISchema::up();
            echo '<div class="notice notice-success"><p>Database tables initialized.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Editorial Suite Database</h1>
            <p>Initialize or update the centralized AI job queue and budgeting tables.</p>
            <form method="post">
                <?php wp_nonce_field( 'kh_editorial_db', 'kh_editorial_nonce' ); ?>
                <p class="submit">
                    <input type="submit" name="kh_editorial_init_db" class="button button-primary" value="Initialize Infrastructure">
                </p>
            </form>
        </div>
        <?php
    }

    private function get_asset_url( $path ) {
        return plugins_url( 'app/public/wp-content/plugins/khm-plugin/' . $path, ABSPATH );
    }

    public function render_planner_page() {
        echo '<div id="editorial-planner-app"></div>';
        $this->enqueue_script( 'editorial-planner', 'assets/js/editorial-planner.js' );
    }

    private function enqueue_script( $handle, $rel_path, $deps = [ 'wp-element', 'wp-api-fetch', 'wp-components', 'wp-data' ] ) {
        $url = $this->get_asset_url( $rel_path );
        wp_enqueue_script( $handle, $url, $deps, KH_EDITORIAL_VERSION, true );
        wp_localize_script( $handle, 'dualGptData', [
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'restUrl' => rest_url( 'dual-gpt/v1/' ),
        ] );
    }

    public function enqueue_dashboard_assets( $hook ) {
        if ( $hook !== 'index.php' ) return;
        
        wp_enqueue_script(
            'editorial-dashboard',
            $this->get_asset_url( 'assets/js/editorial-dashboard.js' ),
            [ 'wp-api-fetch' ],
            KH_EDITORIAL_VERSION,
            true
        );

        wp_localize_script( 'editorial-dashboard', 'editorialData', [
            'restBase' => rest_url( 'editorial/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' )
        ] );
    }
}
