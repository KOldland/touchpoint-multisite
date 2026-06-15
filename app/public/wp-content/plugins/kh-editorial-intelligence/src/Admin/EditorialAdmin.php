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
        // Parent: Editorial Studio — the dashboard hub
        add_menu_page(
            __( 'Editorial Studio', 'kh-editorial-intelligence' ),
            __( 'Editorial Studio', 'kh-editorial-intelligence' ),
            'edit_posts',
            'kh-editorial-studio',
            [ $this, 'render_dashboard_page' ],
            'dashicons-edit-page',
            25
        );

        // Sub: Planner (registered by kh-editorial-planner, do not duplicate)
        // Sub: Writing Studio (registered by kh-editorial-author, do not duplicate)

        // Sub: Posts — keep as sidebar fallback, but primary nav lives in the dashboard
        remove_menu_page( 'edit.php' );
        add_submenu_page(
            'kh-editorial-studio',
            __( 'All Posts', 'kh-editorial-intelligence' ),
            __( 'All Posts', 'kh-editorial-intelligence' ),
            'edit_posts',
            'edit.php'
        );
        // Note: Add New, Categories, Tags, Authors, Atomic Articles are all
        // surfaced as cards on the dashboard — no sidebar clutter needed.

        // Sub: Intelligence Admin
        add_submenu_page(
            'kh-editorial-studio',
            __( 'Intelligence Admin', 'kh-editorial-intelligence' ),
            __( 'Settings', 'kh-editorial-intelligence' ),
            'manage_options',
            'kh-editorial-admin',
            [ $this, 'render_dashboard_page' ]
        );

        // API Settings — registered under top-level parent for sidebar visibility
        add_submenu_page(
            'kh-editorial-studio',
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
    }

    public function render_dashboard_page() {
        global $wpdb;
        $llm_configured = \KH\Editorial\Core\LLMService::is_configured();
        $seo_active = function_exists('khm_seo');
        $geo_active = class_exists('\KHM\GEO\SuggestAnswerCardsEndpoint');

        // Fetch post counts
        $post_counts = wp_count_posts();
        $total_posts = (int) $post_counts->publish + (int) $post_counts->draft + (int) $post_counts->pending + (int) $post_counts->future;

        // Fetch recent posts
        $recent_posts = get_posts( [
            'post_type'      => 'post',
            'post_status'    => [ 'publish', 'draft', 'pending', 'future' ],
            'posts_per_page' => 5,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );

        // Fetch recent AI jobs
        $table_jobs = $wpdb->prefix . 'ai_jobs';
        $recent_jobs = [];
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_jobs'" ) ) {
            $recent_jobs = $wpdb->get_results( "SELECT * FROM $table_jobs ORDER BY created_at DESC LIMIT 10" );
        }

        // Navigation menu items (like a front-end grid)
        $nav_items = [
            [
                'title'    => __( 'New Post', 'kh-editorial-intelligence' ),
                'url'      => admin_url( 'post-new.php' ),
                'icon'     => 'dashicons-plus-alt',
                'desc'     => __( 'Create a new article.', 'kh-editorial-intelligence' ),
                'primary'  => true,
            ],
            [
                'title'    => __( 'All Posts', 'kh-editorial-intelligence' ),
                'url'      => admin_url( 'edit.php' ),
                'icon'     => 'dashicons-admin-post',
                'desc'     => __( 'Browse and manage all editorial content.', 'kh-editorial-intelligence' ),
                'primary'  => false,
            ],
            [
                'title'    => __( 'Content Distribution', 'kh-editorial-intelligence' ),
                'url'      => admin_url( 'admin.php?page=kh-distribution' ),
                'icon'     => 'dashicons-networking',
                'desc'     => __( 'View and manage cross-site content distribution.', 'kh-editorial-intelligence' ),
                'primary'  => false,
            ],
            [
                'title'    => __( 'Categories', 'kh-editorial-intelligence' ),
                'url'      => admin_url( 'edit-tags.php?taxonomy=category' ),
                'icon'     => 'dashicons-category',
                'desc'     => __( 'Organise content by topic.', 'kh-editorial-intelligence' ),
                'primary'  => false,
            ],
            [
                'title'    => __( 'Tags', 'kh-editorial-intelligence' ),
                'url'      => admin_url( 'edit-tags.php?taxonomy=post_tag' ),
                'icon'     => 'dashicons-tag',
                'desc'     => __( 'Manage content tags.', 'kh-editorial-intelligence' ),
                'primary'  => false,
            ],
            [
                'title'    => __( 'Authors', 'kh-editorial-intelligence' ),
                'url'      => post_type_exists( 'multi_author' ) ? admin_url( 'edit.php?post_type=multi_author' ) : '',
                'icon'     => 'dashicons-admin-users',
                'desc'     => __( 'Manage author profiles and bios.', 'kh-editorial-intelligence' ),
                'primary'  => false,
                'cond'     => post_type_exists( 'multi_author' ),
            ],
            [
                'title'    => __( 'Atomic Articles', 'kh-editorial-intelligence' ),
                'url'      => post_type_exists( 'atomic_article' ) ? admin_url( 'edit.php?post_type=atomic_article' ) : '',
                'icon'     => 'dashicons-grid-view',
                'desc'     => __( 'LLM-decomposed micro-content for RAG.', 'kh-editorial-intelligence' ),
                'primary'  => false,
                'cond'     => post_type_exists( 'atomic_article' ),
            ],
            [
                'title'    => __( 'Planner', 'kh-editorial-intelligence' ),
                'url'      => admin_url( 'admin.php?page=kh-editorial-planner' ),
                'icon'     => 'dashicons-calendar-alt',
                'desc'     => __( 'Plan editorial sessions and strategy.', 'kh-editorial-intelligence' ),
                'primary'  => false,
            ],
            [
                'title'    => __( 'Writing Studio', 'kh-editorial-intelligence' ),
                'url'      => admin_url( 'admin.php?page=kh-editorial-author' ),
                'icon'     => 'dashicons-edit',
                'desc'     => __( 'AI-assisted writing and frameworks.', 'kh-editorial-intelligence' ),
                'primary'  => false,
            ],
            [
                'title'    => __( 'API Settings', 'kh-editorial-intelligence' ),
                'url'      => admin_url( 'admin.php?page=kh-editorial-settings' ),
                'icon'     => 'dashicons-admin-settings',
                'desc'     => __( 'LLM keys, social, and search config.', 'kh-editorial-intelligence' ),
                'primary'  => false,
                'cond'     => current_user_can( 'manage_options' ),
            ],
        ];

        ?>
        <div class="wrap kh-studio-dashboard">
            <h1><?php esc_html_e( 'Editorial Studio', 'kh-editorial-intelligence' ); ?></h1>

            <!-- Stat cards row -->
            <div class="kh-stats-row" style="display: flex; gap: 16px; margin-bottom: 24px;">
                <div class="kh-stat-card" style="flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #1d2327;"><?php echo esc_html( $total_posts ); ?></div>
                    <div style="color: #646970; font-size: 13px;"><?php esc_html_e( 'Total Posts', 'kh-editorial-intelligence' ); ?></div>
                </div>
                <div class="kh-stat-card" style="flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #dba617;"><?php echo esc_html( $post_counts->draft ?? 0 ); ?></div>
                    <div style="color: #646970; font-size: 13px;"><?php esc_html_e( 'Drafts', 'kh-editorial-intelligence' ); ?></div>
                </div>
                <div class="kh-stat-card" style="flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #d63638;"><?php echo esc_html( $post_counts->pending ?? 0 ); ?></div>
                    <div style="color: #646970; font-size: 13px;"><?php esc_html_e( 'Pending Review', 'kh-editorial-intelligence' ); ?></div>
                </div>
                <div class="kh-stat-card" style="flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #2271b1;"><?php echo esc_html( $post_counts->future ?? 0 ); ?></div>
                    <div style="color: #646970; font-size: 13px;"><?php esc_html_e( 'Scheduled', 'kh-editorial-intelligence' ); ?></div>
                </div>
            </div>

            <!-- Navigation grid (hub-style) -->
            <h2 style="margin-bottom: 12px;"><?php esc_html_e( 'Quick Actions', 'kh-editorial-intelligence' ); ?></h2>
            <div class="kh-nav-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px;">
                <?php foreach ( $nav_items as $item ) :
                    if ( isset( $item['cond'] ) && ! $item['cond'] ) continue;
                    $card_style = ! empty( $item['primary'] )
                        ? 'background: #2271b1; color: #fff; border-color: #2271b1;'
                        : 'background: #fff; border: 1px solid #c3c4c7;';
                    $text_style = ! empty( $item['primary'] ) ? 'color: #fff;' : 'color: #1d2327;';
                    $desc_style = ! empty( $item['primary'] ) ? 'color: rgba(255,255,255,0.75);' : 'color: #646970;';
                ?>
                    <a href="<?php echo esc_url( $item['url'] ); ?>" class="kh-nav-card" style="display: flex; align-items: flex-start; gap: 12px; padding: 20px; border-radius: 6px; text-decoration: none; <?php echo $card_style; ?> transition: transform 0.15s, box-shadow 0.15s;">
                        <span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" style="font-size: 28px; width: 28px; height: 28px; <?php echo $text_style; ?>"></span>
                        <div>
                            <strong style="display: block; font-size: 14px; <?php echo $text_style; ?>"><?php echo esc_html( $item['title'] ); ?></strong>
                            <span style="font-size: 12px; <?php echo $desc_style; ?>"><?php echo esc_html( $item['desc'] ); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Bottom row: Recent Posts + System Status -->
            <div style="display: flex; gap: 20px;">
                <!-- Recently Modified Posts -->
                <div style="flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e( 'Recently Modified', 'kh-editorial-intelligence' ); ?></h3>
                    <?php if ( ! empty( $recent_posts ) ) : ?>
                        <ul style="margin: 0; padding-left: 0; list-style: none;">
                            <?php foreach ( $recent_posts as $post ) : ?>
                                <li style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f1;">
                                    <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" style="font-weight: 600;">
                                        <?php echo esc_html( get_the_title( $post ) ?: '(no title)' ); ?>
                                    </a>
                                    <span style="color: #888; font-size: 12px; margin-left: 8px;">
                                        — <?php echo esc_html( $post->post_status ); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p style="color: #646970;"><?php esc_html_e( 'No posts yet.', 'kh-editorial-intelligence' ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- System Status -->
                <div style="flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e( 'System Status', 'kh-editorial-intelligence' ); ?></h3>
                    <table class="widefat" style="border: none;">
                        <tbody>
                            <tr>
                                <td style="border: none; padding: 6px 0;"><?php echo $llm_configured ? '✅' : '❌'; ?> <strong><?php esc_html_e( 'LLM API', 'kh-editorial-intelligence' ); ?></strong></td>
                                <td style="border: none; padding: 6px 0; text-align: right;"><?php echo $llm_configured ? esc_html__( 'Configured', 'kh-editorial-intelligence' ) : esc_html__( 'Offline', 'kh-editorial-intelligence' ); ?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 6px 0;"><?php echo $seo_active ? '✅' : '❌'; ?> <strong><?php esc_html_e( 'SEO Engine', 'kh-editorial-intelligence' ); ?></strong></td>
                                <td style="border: none; padding: 6px 0; text-align: right;"><?php echo $seo_active ? esc_html__( 'Active', 'kh-editorial-intelligence' ) : esc_html__( 'Inactive', 'kh-editorial-intelligence' ); ?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 6px 0;"><?php echo $geo_active ? '✅' : '❌'; ?> <strong><?php esc_html_e( 'GEO Researcher', 'kh-editorial-intelligence' ); ?></strong></td>
                                <td style="border: none; padding: 6px 0; text-align: right;"><?php echo $geo_active ? esc_html__( 'Active', 'kh-editorial-intelligence' ) : esc_html__( 'Inactive', 'kh-editorial-intelligence' ); ?></td>
                            </tr>
                            <?php if ( ! empty( $recent_jobs ) ) : ?>
                                <tr><td colspan="2" style="border: none; padding-top: 12px;"><strong><?php esc_html_e( 'Recent AI Jobs', 'kh-editorial-intelligence' ); ?></strong></td></tr>
                                <?php foreach ( array_slice( $recent_jobs, 0, 5 ) as $job ) : ?>
                                    <tr>
                                        <td style="border: none; padding: 3px 0; font-size: 12px;"><code><?php echo esc_html( $job->model ?: 'N/A' ); ?></code></td>
                                        <td style="border: none; padding: 3px 0; font-size: 12px; text-align: right;">
                                            <?php echo esc_html( ucfirst( $job->status ) ); ?>
                                            — <?php echo esc_html( human_time_diff( strtotime( $job->created_at ), current_time( 'timestamp' ) ) ); ?> ago
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <style>
                .kh-nav-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                @media (max-width: 900px) {
                    .kh-nav-grid { grid-template-columns: repeat(2, 1fr) !important; }
                    .kh-stats-row { flex-wrap: wrap; }
                }
                @media (max-width: 600px) {
                    .kh-nav-grid { grid-template-columns: 1fr !important; }
                }
            </style>
        </div>
        <?php
    }

    public function render_settings_page() {
        if ( isset( $_POST['kh_editorial_save_settings'] ) && check_admin_referer( 'kh_editorial_settings', 'kh_editorial_nonce' ) ) {
            $preset_key = sanitize_text_field( $_POST['preset_profile'] ?? 'speed' );
            $agent_models    = array_map( 'sanitize_text_field', (array) ( $_POST['agent_models'] ?? [] ) );
            $agent_fallbacks = array_map( 'sanitize_text_field', (array) ( $_POST['agent_fallbacks'] ?? [] ) );
            $agent_tertiaries = array_map( 'sanitize_text_field', (array) ( $_POST['agent_tertiaries'] ?? [] ) );

            // If user only changed the profile without touching individual agent dropdowns,
            // seed all model data from the selected preset so it displays correctly.
            if ( empty( $agent_models ) && isset( \KH\Editorial\Core\LLMService::PRESET_PROFILES[ $preset_key ] ) ) {
                $preset = \KH\Editorial\Core\LLMService::PRESET_PROFILES[ $preset_key ];
                $agent_models = $preset['models'];
                $chains       = $preset['fallback_chains'] ?? [];
                $agent_fallbacks = [];
                $agent_tertiaries = [];
                foreach ( $chains as $agent => $chain ) {
                    if ( isset( $chain[0] ) ) {
                        $agent_fallbacks[ $agent ] = $chain[0];
                    }
                    if ( isset( $chain[1] ) ) {
                        $agent_tertiaries[ $agent ] = $chain[1];
                    }
                }
            }

            $settings = [
                'openai_api_key'      => sanitize_text_field( $_POST['openai_api_key'] ),
                'openai_model'        => sanitize_text_field( $_POST['openai_model'] ?? '' ),
                'google_ai_key'       => sanitize_text_field( $_POST['google_ai_key'] ),
                'openrouter_api_key'  => sanitize_text_field( $_POST['openrouter_api_key'] ),
                'provider_priority'   => sanitize_text_field( $_POST['provider_priority'] ?? 'auto' ),
                'preset_profile'      => $preset_key,
                'dataforseo_login'    => sanitize_text_field( $_POST['dataforseo_login'] ),
                'dataforseo_password' => sanitize_text_field( $_POST['dataforseo_password'] ),
                'serpapi_key'        => sanitize_text_field( $_POST['serpapi_key'] ),
                'tavily_key'         => sanitize_text_field( $_POST['tavily_key'] ),
                'search_primary'     => sanitize_text_field( $_POST['search_primary'] ),
                'show_prompt_editor' => isset( $_POST['show_prompt_editor'] ) ? 1 : 0,
                'agent_models'       => $agent_models,
                'agent_fallbacks'    => $agent_fallbacks,
                'agent_tertiaries'   => $agent_tertiaries,
                'persona_models'     => $this->sanitize_persona_models( $_POST['persona_models'] ?? [] ),
                'currency_rates'     => [
                    'EUR' => (float) ( $_POST['rate_eur'] ?? 1.15 ),
                    'USD' => (float) ( $_POST['rate_usd'] ?? 1.25 ),
                ],
                'linkedin_client_id'     => sanitize_text_field( $_POST['linkedin_client_id'] ?? '' ),
                'linkedin_client_secret' => sanitize_text_field( $_POST['linkedin_client_secret'] ?? '' ),
                'linkedin_access_token'  => sanitize_text_field( $_POST['linkedin_access_token'] ?? '' ),
                'linkedin_author_urn'    => sanitize_text_field( $_POST['linkedin_author_urn'] ?? '' ),
            ];

            update_option( 'kh_editorial_settings', $settings );
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $defaults = [
            'openai_api_key'      => '',
            'openai_model'        => 'gpt-4o-mini',
            'google_ai_key'       => '',
            'openrouter_api_key'  => '',
            'provider_priority'   => 'auto',
            'preset_profile'      => 'speed',
            'dataforseo_login'    => '',
            'dataforseo_password' => '',
            'serpapi_key'        => '',
            'tavily_key'         => '',
            'search_primary'     => 'serpapi',
            'show_prompt_editor' => 1,
            'agent_models'       => [],
            'agent_fallbacks'    => [],
            'agent_tertiaries'   => [],
            'persona_models'     => \KH\Editorial\Core\LLMService::DEFAULT_PERSONA_MODELS,
            'currency_rates'     => [ 'EUR' => 1.15, 'USD' => 1.25 ],
        ];
        $stored  = get_option( 'kh_editorial_settings', [] );
        $settings = array_merge( $defaults, $stored );
        if ( isset( $stored['currency_rates'] ) && is_array( $stored['currency_rates'] ) ) {
            $settings['currency_rates'] = array_merge( $defaults['currency_rates'], $stored['currency_rates'] );
        }
        if ( isset( $stored['persona_models'] ) && is_array( $stored['persona_models'] ) ) {
            foreach ( $defaults['persona_models'] as $key => $default_config ) {
                if ( isset( $stored['persona_models'][ $key ] ) && is_array( $stored['persona_models'][ $key ] ) ) {
                    $settings['persona_models'][ $key ] = array_merge( $default_config, $stored['persona_models'][ $key ] );
                }
            }
        }

        // If agent_models are empty (no overrides saved yet), populate from the active preset.
        $active_preset = $settings['preset_profile'] ?? 'speed';
        if ( empty( array_filter( (array) ( $settings['agent_models'] ?? [] ) ) )
            && isset( \KH\Editorial\Core\LLMService::PRESET_PROFILES[ $active_preset ] ) ) {
            $preset = \KH\Editorial\Core\LLMService::PRESET_PROFILES[ $active_preset ];
            $settings['agent_models'] = $preset['models'];
            $chains = $preset['fallback_chains'] ?? [];
            foreach ( $chains as $agent => $chain ) {
                if ( isset( $chain[0] ) ) {
                    $settings['agent_fallbacks'][ $agent ] = $chain[0];
                }
                if ( isset( $chain[1] ) ) {
                    $settings['agent_tertiaries'][ $agent ] = $chain[1];
                }
            }
        }

        $persona_defaults = \KH\Editorial\Core\LLMService::DEFAULT_PERSONA_MODELS;
        $profiles         = \KH\Editorial\Core\LLMService::PRESET_PROFILES;
        $all_models       = \KH\Editorial\Core\LLMService::ALL_MODELS;

        // Helper: output a model dropdown for an agent key (uses unified registry)
        $model_select = function( $key ) use ( $settings, $all_models ) {
            $val = $settings['agent_models'][ $key ] ?? '';
            echo '<option value="" ' . selected( $val, '', false ) . '>—</option>';
            foreach ( $all_models as $v => $label ) {
                printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $val, $v, false ), esc_html( $label ) );
            }
        };
        $fallback_select = function( $key ) use ( $settings, $all_models ) {
            $val = $settings['agent_fallbacks'][ $key ] ?? '';
            echo '<option value="" ' . selected( $val, '', false ) . '>—</option>';
            foreach ( $all_models as $v => $label ) {
                printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $val, $v, false ), esc_html( $label ) );
            }
        };
        $tertiary_select = function( $key ) use ( $settings, $all_models ) {
            $val = $settings['agent_tertiaries'][ $key ] ?? '';
            echo '<option value="" ' . selected( $val, '', false ) . '>—</option>';
            foreach ( $all_models as $v => $label ) {
                printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $val, $v, false ), esc_html( $label ) );
            }
        };

        ?>
        <div class="kh-editorial-wrap">
            <h1>API Settings</h1>
            <script>
            (function(){document.querySelectorAll('.kh-section-header').forEach(function(h){h.onclick=function(){this.parentElement.classList.toggle('open')}});})();
            </script>
            <form method="post">
                <?php wp_nonce_field( 'kh_editorial_settings', 'kh_editorial_nonce' ); ?>

                <!-- =========================== SECTION: API KEYS ============================ -->
                <div class="kh-section open">
                    <div class="kh-section-header">
                        <h2><span class="dashicons dashicons-admin-network"></span> API Keys</h2>
                        <span class="kh-section-toggle"></span>
                    </div>
                    <div class="kh-section-body">

                        <!-- LLMs -->
                        <div class="kh-subsection">
                            <h3>LLMs</h3>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong>OpenAI</strong><span class="kh-help">BYOK</span></div>
                                <div class="kh-form-control">
                                    <input name="openai_api_key" type="password" value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>" placeholder="sk-...">
                                    <div class="kh-desc">Global key for all AI agents.</div>
                                </div>
                            </div>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong>Google AI</strong><span class="kh-help">BYOK</span></div>
                                <div class="kh-form-control">
                                    <input name="google_ai_key" type="password" value="<?php echo esc_attr( $settings['google_ai_key'] ); ?>" placeholder="AIza...">
                                    <div class="kh-desc">Gemini models + Imagen image generation.</div>
                                </div>
                            </div>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong>OpenRouter</strong><span class="kh-help">BYOK</span></div>
                                <div class="kh-form-control">
                                    <input name="openrouter_api_key" type="password" value="<?php echo esc_attr( $settings['openrouter_api_key'] ); ?>" placeholder="sk-or-...">
                                    <div class="kh-desc">Flux, Recraft, and LLM routing. <a href="https://openrouter.ai/keys" target="_blank">Get a key</a></div>
                                </div>
                            </div>

                            <h3 style="margin-top:20px;">Provider Priority</h3>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong>Primary / Secondary</strong></div>
                                <div class="kh-form-control">
                                    <select name="provider_priority">
                                        <option value="auto" <?php selected( $settings['provider_priority'], 'auto' ); ?>>Auto-detect from model ID</option>
                                        <option value="openai" <?php selected( $settings['provider_priority'], 'openai' ); ?>>OpenAI First → OpenRouter</option>
                                        <option value="openrouter" <?php selected( $settings['provider_priority'], 'openrouter' ); ?>>OpenRouter First → OpenAI</option>
                                    </select>
                                    <div class="kh-desc">When providers are added they appear here. Set primary and fallback order.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Social Media -->
                        <div class="kh-subsection">
                            <h3>Social Media</h3>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong>LinkedIn</strong><span class="kh-help">OAuth</span></div>
                                <div class="kh-form-control">
                                    <input name="linkedin_client_id" type="text" value="<?php echo esc_attr( $settings['linkedin_client_id'] ?? '' ); ?>" placeholder="Client ID" class="small" style="width:300px;margin-bottom:6px;">
                                    <input name="linkedin_client_secret" type="password" value="<?php echo esc_attr( $settings['linkedin_client_secret'] ?? '' ); ?>" placeholder="Client Secret" class="small" style="width:300px;margin-bottom:6px;">
                                    <input name="linkedin_access_token" type="password" value="<?php echo esc_attr( $settings['linkedin_access_token'] ?? '' ); ?>" placeholder="Access Token" class="small" style="width:300px;margin-bottom:6px;">
                                    <input name="linkedin_author_urn" type="text" value="<?php echo esc_attr( $settings['linkedin_author_urn'] ?? '' ); ?>" placeholder="Author URN (e.g. urn:li:person:abc123)" class="small" style="width:350px;">
                                    <div class="kh-desc">Client ID + Secret from <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developer Apps</a>. Access Token from OAuth flow. Author URN identifies the person/organization posting.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Data -->
                        <div class="kh-subsection">
                            <h3>Data</h3>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong>Keywords</strong><span class="kh-help">DataForSEO</span></div>
                                <div class="kh-form-control">
                                    <input name="dataforseo_login" type="text" value="<?php echo esc_attr( $settings['dataforseo_login'] ); ?>" placeholder="Login" class="small" style="width:180px;margin-bottom:6px;">
                                    <input name="dataforseo_password" type="password" value="<?php echo esc_attr( $settings['dataforseo_password'] ); ?>" placeholder="Password" class="small" style="width:180px;">
                                    <div class="kh-desc">BYOK — bring your own DataForSEO credentials.</div>
                                </div>
                            </div>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong>Search (SERP)</strong></div>
                                <div class="kh-form-control">
                                    <select name="search_primary">
                                        <option value="serpapi" <?php selected( $settings['search_primary'], 'serpapi' ); ?>>SerpAPI (Google) — Default</option>
                                        <option value="tavily" <?php selected( $settings['search_primary'], 'tavily' ); ?>>Tavily (AI Search)</option>
                                        <option value="dataforseo" <?php selected( $settings['search_primary'], 'dataforseo' ); ?>>DataForSEO</option>
                                    </select>
                                    <div style="margin-top:6px;">
                                        <input name="serpapi_key" type="password" value="<?php echo esc_attr( $settings['serpapi_key'] ); ?>" placeholder="SerpAPI Key" style="width:300px;">
                                    </div>
                                    <div style="margin-top:4px;">
                                        <input name="tavily_key" type="password" value="<?php echo esc_attr( $settings['tavily_key'] ); ?>" placeholder="Tavily Key" style="width:300px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- =========================== SECTION: MODELS ============================ -->
                <div class="kh-section">
                    <div class="kh-section-header">
                        <h2><span class="dashicons dashicons-admin-generic"></span> Models</h2>
                        <span class="kh-section-toggle"></span>
                    </div>
                    <div class="kh-section-body">

                        <!-- Profile -->
                        <div class="kh-subsection">
                            <h3>Profile</h3>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong>Apply Profile</strong></div>
                                <div class="kh-form-control">
                                    <select name="preset_profile" id="preset_profile_select">
                                        <?php foreach ( $profiles as $key => $profile ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['preset_profile'] ?? 'speed', $key ); ?>>
                                                <?php echo esc_html( $profile['label'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="kh-desc">Applies a bulk model config. <strong>Balanced</strong>: best mix. <strong>Cost</strong>: cheapest. <strong>Speed</strong>: fastest. <strong>Quality</strong>: best output.</div>
                                    <button type="button" id="kh-customise-toggle" class="button" style="margin-top:8px;">Customise</button>
                                </div>
                            </div>
                        </div>

                        <script>
                        (function(){
                            var profiles = <?php echo wp_json_encode( $profiles ); ?>;
                            var presetSelect = document.getElementById('preset_profile_select');
                            if (!presetSelect) return;

                            function applyProfile(key) {
                                var p = profiles[key];
                                if (!p) return;
                                // Apply primary models
                                for (var agent in p.models) {
                                    var sel = document.querySelector('select[name="agent_models[' + agent + ']"]');
                                    if (sel) sel.value = p.models[agent];
                                }
                                // Apply fallback chains (secondary + tertiary)
                                for (var agent in p.fallback_chains) {
                                    var chain = p.fallback_chains[agent];
                                    if (chain[0]) {
                                        var fb = document.querySelector('select[name="agent_fallbacks[' + agent + ']"]');
                                        if (fb) fb.value = chain[0];
                                    }
                                    if (chain[1]) {
                                        var tert = document.querySelector('select[name="agent_tertiaries[' + agent + ']"]');
                                        if (tert) tert.value = chain[1];
                                    }
                                }
                            }

                            presetSelect.addEventListener('change', function(){
                                applyProfile(this.value);
                            });
                        })();
                        </script>

                        <!-- Collapsible agent model selectors -->
                        <div id="kh-customise-panel" style="display:none;">

                        <!-- Profile Settings — Research -->
                        <div class="kh-subsection">
                            <h3>Research</h3>
                            <?php
                            $research_agents = [
                                'research_phase1' => 'Keyword & Search Research',
                                'research_phase2' => 'Gap Analysis',
                                'research_phase3' => 'Deep Dive & Insights',
                                'research_phase4' => 'Academic Grounding & Validation',
                            ];
                            foreach ( $research_agents as $agent_key => $label ) : ?>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong><?php echo $label; ?></strong></div>
                                <div class="kh-form-control">
                                    <div class="kh-fallback-row">
                                        <div><span class="kh-fallback-label">Primary</span>
                                            <select name="agent_models[<?php echo $agent_key; ?>]">
                                                <?php $model_select( $agent_key ); ?>
                                            </select>
                                        </div>
                                        <div><span class="kh-fallback-label">Fallback</span>
                                            <select name="agent_fallbacks[<?php echo $agent_key; ?>]">
                                                <?php $fallback_select( $agent_key ); ?>
                                            </select>
                                        </div>
                                        <div><span class="kh-fallback-label">Tertiary</span>
                                            <select name="agent_tertiaries[<?php echo $agent_key; ?>]">
                                                <?php $tertiary_select( $agent_key ); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Profile Settings — Draft & Personas -->
                        <div class="kh-subsection">
                            <h3>Draft & Personas</h3>
                            <?php
                            $personas = [
                                'journalist' => 'Journalist',
                                'analyst'    => 'Industry Analyst',
                                'veteran'    => 'Industry Veteran',
                                'editor'     => 'Editor-at-Large',
                            ];
                            foreach ( $personas as $slug => $name ) :
                                $p_model = $settings['persona_models'][ $slug ]['model'] ?? $persona_defaults[ $slug ]['model'];
                                $p_temp  = $settings['persona_models'][ $slug ]['temperature'] ?? $persona_defaults[ $slug ]['temperature'];
                            ?>
                            <div class="kh-form-row">
                                <div class="kh-form-label">
                                    <strong><?php echo esc_html( $name ); ?></strong>
                                    <span class="kh-help">Temp: <?php echo esc_html( $p_temp ); ?> — Controls creativity (0 = precise, 1 = creative)</span>
                                </div>
                                <div class="kh-form-control">
                                    <div class="kh-fallback-row">
                                        <div><span class="kh-fallback-label">Model</span>
                                            <select name="persona_models[<?php echo $slug; ?>][model]">
                                                <option value="" <?php selected( $p_model, '' ); ?>>—</option>
                                                <?php foreach ( $all_models as $m_val => $m_label ) : ?>
                                                    <option value="<?php echo esc_attr( $m_val ); ?>" <?php selected( $p_model, $m_val ); ?>><?php echo esc_html( $m_label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div><span class="kh-fallback-label">Temperature</span>
                                            <div class="kh-temp-slider">
                                                <input type="range" min="0" max="1" step="0.1" value="<?php echo esc_attr( $p_temp ); ?>" oninput="this.nextElementSibling.textContent=this.value" style="width:120px;">
                                                <span class="kh-temp-val"><?php echo esc_html( $p_temp ); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Profile Settings — Meta & Utility -->
                        <div class="kh-subsection">
                            <h3>Meta & Utility</h3>
                            <?php
                            $utility_agents = [
                                'abstract'       => 'Abstract',
                                'excerpt'        => 'Excerpt',
                                'seo_schema'     => 'SEO / Schema',
                                'gutenberg_push' => 'Gutenberg Push',
                                'geo_cards'      => 'GEO Answer Cards',
                                'social_posts'   => 'Social Media Posts',
                            ];
                            foreach ( $utility_agents as $agent_key => $label ) : ?>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong><?php echo $label; ?></strong></div>
                                <div class="kh-form-control">
                                    <div class="kh-fallback-row">
                                        <div><span class="kh-fallback-label">Primary</span>
                                            <select name="agent_models[<?php echo $agent_key; ?>]">
                                                <?php $model_select( $agent_key ); ?>
                                            </select>
                                        </div>
                                        <div><span class="kh-fallback-label">Fallback</span>
                                            <select name="agent_fallbacks[<?php echo $agent_key; ?>]">
                                                <?php $fallback_select( $agent_key ); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Save as Custom (inside collapsible, at the bottom) -->
                        <div class="kh-subsection">
                            <h3>Save as Custom Profile</h3>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong>Profile Name</strong></div>
                                <div class="kh-form-control">
                                    <div class="kh-profile-actions">
                                        <input name="custom_profile_name" type="text" placeholder="Custom profile name…" style="width:200px;">
                                        <button type="button" class="button">Save as Custom</button>
                                    </div>
                                    <div class="kh-desc">Save your current model selections as a custom preset for quick switching.</div>
                                </div>
                            </div>
                        </div>

                        </div><!-- #kh-customise-panel -->

                        <script>
                        (function(){
                            var btn = document.getElementById('kh-customise-toggle');
                            var panel = document.getElementById('kh-customise-panel');
                            if (!btn || !panel) return;
                            btn.addEventListener('click', function(){
                                var isOpen = panel.style.display !== 'none';
                                panel.style.display = isOpen ? 'none' : 'block';
                                btn.textContent = isOpen ? 'Customise' : 'Close Customise';
                            });
                        })();
                        </script>
                        <!-- End collapsible agent model selectors -->

                    </div>
                </div>

                <!-- =========================== SUBMIT ============================ -->
                <div class="kh-submit">
                    <input type="submit" name="kh_editorial_save_settings" class="button button-primary" value="Save Settings">
                </div>
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
        return plugins_url( $path, KH_EDITORIAL_PLUGIN_DIR . 'kh-editorial-intelligence.php' );
    }



    /**
     * Sanitize persona_models POST data.
     */
    private function sanitize_persona_models( $input ) {
        $clean = [];
        if ( ! is_array( $input ) ) return $clean;
        foreach ( $input as $slug => $config ) {
            if ( ! is_array( $config ) ) continue;
            $clean[ sanitize_key( $slug ) ] = [
                'model'       => sanitize_text_field( $config['model'] ?? '' ),
                'temperature' => min( 1.0, max( 0.0, (float) ( $config['temperature'] ?? 0.4 ) ) ),
            ];
        }
        return $clean;
    }

    public function enqueue_dashboard_assets( $hook ) {
        // Only enqueue on our own admin pages
        $screen = get_current_screen();
        $is_ours = $screen && (
            $screen->id === 'toplevel_page_kh-editorial-studio'
            || strpos( $screen->id, 'kh-editorial-studio' ) !== false
            || strpos( $screen->id, 'kh-editorial-admin' ) !== false
            || strpos( $screen->id, 'kh-editorial-settings' ) !== false
            || strpos( $screen->id, 'kh-editorial-rate-limits' ) !== false
            || strpos( $screen->id, 'kh-editorial-db' ) !== false
        );
        
        if ( ! $is_ours ) {
            return;
        }

        wp_enqueue_style(
            'kh-editorial-admin',
            $this->get_asset_url( 'assets/css/admin.css' ),
            [],
            KH_EDITORIAL_VERSION
        );
        wp_enqueue_script(
            'kh-editorial-admin',
            $this->get_asset_url( 'assets/js/admin.js' ),
            [],
            KH_EDITORIAL_VERSION,
            true
        );
    }

    /**
     * Localize settings for the Gutenberg image sidebar.
     */
    public static function get_sidebar_settings() {
        $settings = get_option( 'kh_editorial_settings', [] );
        return [
            'show_prompt_editor' => ! empty( $settings['show_prompt_editor'] ),
        ];
    }
}
