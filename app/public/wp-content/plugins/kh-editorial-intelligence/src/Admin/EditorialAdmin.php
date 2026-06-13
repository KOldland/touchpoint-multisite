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
        // Parent: Editorial Studio
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

        // Fetch recent jobs
        $table_jobs = $wpdb->prefix . 'ai_jobs';
        $recent_jobs = [];
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_jobs'" ) ) {
            $recent_jobs = $wpdb->get_results( "SELECT * FROM $table_jobs ORDER BY created_at DESC LIMIT 10" );
        }

        ?>
        <div class="wrap">
            <h1>Editorial Suite Status</h1>
            <div class="welcome-panel" style="padding: 20px;">
                <div class="welcome-panel-column-container">
                    <div class="welcome-panel-column">
                        <h3>Core Infrastructure</h3>
                        <ul>
                            <li>
                                <?php echo $llm_configured ? '[OK]' : '[X]'; ?> 
                                <strong>LLM API:</strong> <?php echo $llm_configured ? 'Configured' : 'Not Configured'; ?>
                            </li>
                            <li>
                                <?php echo $seo_active ? '[OK]' : '[X]'; ?> 
                                <strong>SEO Engine:</strong> <?php echo $seo_active ? 'Active' : 'Inactive'; ?>
                            </li>
                            <li>
                                <?php echo $geo_active ? '[OK]' : '[X]'; ?> 
                                <strong>GEO Researcher:</strong> <?php echo $geo_active ? 'Active' : 'Inactive'; ?>
                            </li>
                        </ul>
                    </div>
                    <div class="welcome-panel-column welcome-panel-last">
                        <h3>Recent AI Jobs</h3>
                        <?php if ( ! empty( $recent_jobs ) ) : ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $recent_jobs as $job ) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html( $job->model ?: 'N/A' ); ?></code></td>
                                            <td>
                                                <span class="status-tag status-<?php echo esc_attr( $job->status ); ?>">
                                                    <?php echo esc_html( ucfirst( $job->status ) ); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html( human_time_diff( strtotime( $job->created_at ), current_time( 'timestamp' ) ) ); ?> ago</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p>No recent jobs found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }

    public function render_settings_page() {
        if ( isset( $_POST['kh_editorial_save_settings'] ) && check_admin_referer( 'kh_editorial_settings', 'kh_editorial_nonce' ) ) {
            $settings = [
                'openai_api_key'      => sanitize_text_field( $_POST['openai_api_key'] ),
                'openai_model'        => sanitize_text_field( $_POST['openai_model'] ?? '' ),
                'google_ai_key'       => sanitize_text_field( $_POST['google_ai_key'] ),
                'openrouter_api_key'  => sanitize_text_field( $_POST['openrouter_api_key'] ),
                'provider_priority'   => sanitize_text_field( $_POST['provider_priority'] ?? 'auto' ),
                'preset_profile'      => sanitize_text_field( $_POST['preset_profile'] ?? 'balanced' ),
                'dataforseo_login'    => sanitize_text_field( $_POST['dataforseo_login'] ),
                'dataforseo_password' => sanitize_text_field( $_POST['dataforseo_password'] ),
                'serpapi_key'        => sanitize_text_field( $_POST['serpapi_key'] ),
                'tavily_key'         => sanitize_text_field( $_POST['tavily_key'] ),
                'search_primary'     => sanitize_text_field( $_POST['search_primary'] ),
                'show_prompt_editor' => isset( $_POST['show_prompt_editor'] ) ? 1 : 0,
                'agent_models'       => array_map( 'sanitize_text_field', (array) ( $_POST['agent_models'] ?? [] ) ),
                'agent_fallbacks'    => array_map( 'sanitize_text_field', (array) ( $_POST['agent_fallbacks'] ?? [] ) ),
                'persona_models'     => $this->sanitize_persona_models( $_POST['persona_models'] ?? [] ),
                'currency_rates'     => [
                    'EUR' => (float) ( $_POST['rate_eur'] ?? 1.15 ),
                    'USD' => (float) ( $_POST['rate_usd'] ?? 1.25 ),
                ],
            ];

            $preset = sanitize_text_field( $_POST['preset_profile'] ?? '' );
            if ( $preset && isset( \KH\Editorial\Core\LLMService::PRESET_PROFILES[ $preset ] ) ) {
                $settings['agent_models'] = \KH\Editorial\Core\LLMService::PRESET_PROFILES[ $preset ]['models'];
            }

            update_option( 'kh_editorial_settings', $settings );
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $defaults = [
            'openai_api_key'      => '',
            'openai_model'        => 'gpt-4o-mini',
            'google_ai_key'       => '',
            'openrouter_api_key'  => '',
            'provider_priority'   => 'auto',
            'preset_profile'      => 'balanced',
            'dataforseo_login'    => '',
            'dataforseo_password' => '',
            'serpapi_key'        => '',
            'tavily_key'         => '',
            'search_primary'     => 'serpapi',
            'show_prompt_editor' => 1,
            'agent_models'       => \KH\Editorial\Core\LLMService::DEFAULT_AGENT_MODELS,
            'agent_fallbacks'    => [],
            'persona_models'     => \KH\Editorial\Core\LLMService::DEFAULT_PERSONA_MODELS,
            'currency_rates'     => [ 'EUR' => 1.15, 'USD' => 1.25 ],
        ];
        $stored  = get_option( 'kh_editorial_settings', [] );
        $settings = array_merge( $defaults, $stored );
        if ( isset( $stored['agent_models'] ) && is_array( $stored['agent_models'] ) ) {
            $settings['agent_models'] = array_merge( $defaults['agent_models'], $stored['agent_models'] );
        }
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

        $persona_defaults = \KH\Editorial\Core\LLMService::DEFAULT_PERSONA_MODELS;
        $profiles = \KH\Editorial\Core\LLMService::PRESET_PROFILES;

        // Helper: output a model dropdown for an agent key
        $model_select = function( $key, $models ) use ( $settings ) {
            $val = $settings['agent_models'][ $key ] ?? '';
            foreach ( $models as $v => $label ) {
                printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $val, $v, false ), esc_html( $label ) );
            }
        };
        $fallback_select = function( $key ) use ( $settings ) {
            $val = $settings['agent_fallbacks'][ $key ] ?? '';
            echo '<option value="" ' . selected( $val, '', false ) . '>—</option>';
            foreach ( [
                'openai/gpt-4o-mini' => 'GPT-4o Mini (OpenRouter)',
                'openai/gpt-4o'      => 'GPT-4o (OpenRouter)',
                'deepseek/deepseek-chat' => 'DeepSeek V3 Chat',
                'mistralai/mistral-large' => 'Mistral Large',
                'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
            ] as $v => $label ) {
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
                                    <select name="preset_profile">
                                        <?php foreach ( $profiles as $key => $profile ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['preset_profile'] ?? 'balanced', $key ); ?>>
                                                <?php echo esc_html( $profile['label'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="kh-desc">Applies a bulk model config. <strong>Balanced</strong>: best mix. <strong>Cost</strong>: cheapest. <strong>Speed</strong>: fastest. <strong>Quality</strong>: best output.</div>
                                    <div class="kh-profile-actions">
                                        <input name="custom_profile_name" type="text" placeholder="Custom profile name…" style="width:200px;">
                                        <button type="button" class="button">Save as Custom</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Settings — Research -->
                        <div class="kh-subsection">
                            <h3>Research</h3>
                            <?php
                            $research_agents = [
                                'research_phase1' => 'Keyword &amp; Search Research',
                                'research_phase2' => 'Gap Analysis',
                                'research_phase3' => 'Deep Dive &amp; Insights',
                                'research_phase4' => 'Academic Grounding &amp; Validation',
                            ];
                            $research_models = [
                                'research_phase1' => [
                                    'google/gemini-2.5-flash' => 'Gemini 2.5 Flash',
                                    'deepseek/deepseek-chat' => 'DeepSeek V3 Chat',
                                    'google/gemini-2.5-pro' => 'Gemini 2.5 Pro',
                                    'gpt-4o-mini' => 'GPT-4o Mini',
                                ],
                                'research_phase2' => [
                                    'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
                                    'deepseek/deepseek-r1' => 'DeepSeek R1',
                                    'google/gemini-2.5-flash' => 'Gemini 2.5 Flash',
                                    'gpt-4o-mini' => 'GPT-4o Mini',
                                ],
                                'research_phase3' => [
                                    'anthropic/claude-sonnet-4.5' => 'Claude Sonnet 4.5',
                                    'openai/gpt-4o' => 'GPT-4o',
                                    'gpt-4o' => 'GPT-4o (direct)',
                                ],
                                'research_phase4' => [
                                    'google/gemini-2.5-pro' => 'Gemini 2.5 Pro',
                                    'anthropic/claude-sonnet-4.5' => 'Claude Sonnet 4.5',
                                    'gpt-4o' => 'GPT-4o',
                                ],
                            ];
                            foreach ( $research_agents as $agent_key => $label ) : ?>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong><?php echo $label; ?></strong></div>
                                <div class="kh-form-control">
                                    <div class="kh-fallback-row">
                                        <div><span class="kh-fallback-label">Primary</span>
                                            <select name="agent_models[<?php echo $agent_key; ?>]">
                                                <?php $model_select( $agent_key, $research_models[ $agent_key ] ); ?>
                                            </select>
                                        </div>
                                        <div><span class="kh-fallback-label">Fallback</span>
                                            <select name="agent_fallbacks[<?php echo $agent_key; ?>]">
                                                <?php $fallback_select( $agent_key ); ?>
                                            </select>
                                        </div>
                                        <div><span class="kh-fallback-label">Tertiary</span>
                                            <select name="agent_tertiaries[<?php echo $agent_key; ?>]">
                                                <?php $fallback_select( $agent_key . '_tertiary' ); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Profile Settings — Draft & Personas -->
                        <div class="kh-subsection">
                            <h3>Draft &amp; Personas</h3>
                            <?php
                            $personas = [
                                'journalist' => 'Journalist',
                                'analyst'    => 'Industry Analyst',
                                'veteran'    => 'Industry Veteran',
                                'editor'     => 'Editor-at-Large',
                            ];
                            $persona_models = [
                                'journalist' => ['deepseek/deepseek-r1' => 'DeepSeek R1', 'anthropic/claude-sonnet-4.5' => 'Claude Sonnet 4.5', 'gpt-4o' => 'GPT-4o'],
                                'analyst'    => ['mistralai/mistral-large' => 'Mistral Large', 'anthropic/claude-sonnet-4.5' => 'Claude Sonnet 4.5', 'gpt-4o' => 'GPT-4o'],
                                'veteran'    => ['meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B', 'anthropic/claude-sonnet-4.5' => 'Claude Sonnet 4.5', 'gpt-4o' => 'GPT-4o'],
                                'editor'     => ['anthropic/claude-sonnet-4.5' => 'Claude Sonnet 4.5', 'openai/gpt-4o' => 'GPT-4o', 'gpt-4o' => 'GPT-4o (direct)'],
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
                                                <?php foreach ( $persona_models[ $slug ] as $m_val => $m_label ) : ?>
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
                            <h3>Meta &amp; Utility</h3>
                            <?php
                            $utility_agents = [
                                'abstract'       => 'Abstract',
                                'excerpt'        => 'Excerpt',
                                'seo_schema'     => 'SEO / Schema',
                                'gutenberg_push' => 'Gutenberg Push',
                                'geo_cards'      => 'GEO Answer Cards',
                                'social_posts'   => 'Social Media Posts',
                            ];
                            $utility_models = [
                                'abstract'     => ['google/gemini-2.5-flash' => 'Gemini 2.5 Flash', 'deepseek/deepseek-chat' => 'DeepSeek V3 Chat', 'gpt-4o-mini' => 'GPT-4o Mini'],
                                'excerpt'      => ['google/gemini-2.5-flash' => 'Gemini 2.5 Flash', 'deepseek/deepseek-chat' => 'DeepSeek V3 Chat', 'gpt-4o-mini' => 'GPT-4o Mini'],
                                'seo_schema'   => ['google/gemini-2.5-flash' => 'Gemini 2.5 Flash', 'deepseek/deepseek-chat' => 'DeepSeek V3 Chat', 'gpt-4o-mini' => 'GPT-4o Mini'],
                                'gutenberg_push' => ['deepseek/deepseek-chat' => 'DeepSeek V3 Chat', 'google/gemini-2.5-flash' => 'Gemini 2.5 Flash', 'gpt-4o-mini' => 'GPT-4o Mini'],
                                'geo_cards'    => ['meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B', 'google/gemini-2.5-flash' => 'Gemini 2.5 Flash', 'gpt-4o-mini' => 'GPT-4o Mini'],
                                'social_posts' => ['meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B', 'google/gemini-2.5-flash' => 'Gemini 2.5 Flash', 'gpt-4o-mini' => 'GPT-4o Mini'],
                            ];
                            foreach ( $utility_agents as $agent_key => $label ) : ?>
                            <div class="kh-form-row">
                                <div class="kh-form-label"><strong><?php echo $label; ?></strong></div>
                                <div class="kh-form-control">
                                    <div class="kh-fallback-row">
                                        <div><span class="kh-fallback-label">Primary</span>
                                            <select name="agent_models[<?php echo $agent_key; ?>]">
                                                <?php $model_select( $agent_key, $utility_models[ $agent_key ] ); ?>
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
