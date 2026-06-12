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
                'openai_model'        => sanitize_text_field( $_POST['openai_model'] ),
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

            // Apply preset profile if selected — overwrites agent_models with the profile's models
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
        // Ensure agent_models sub-keys have defaults if missing
        if ( isset( $stored['agent_models'] ) && is_array( $stored['agent_models'] ) ) {
            $settings['agent_models'] = array_merge( $defaults['agent_models'], $stored['agent_models'] );
        }
        // Ensure currency_rates sub-keys have defaults if missing
        if ( isset( $stored['currency_rates'] ) && is_array( $stored['currency_rates'] ) ) {
            $settings['currency_rates'] = array_merge( $defaults['currency_rates'], $stored['currency_rates'] );
        }
        // Ensure persona_models sub-keys have defaults if missing
        if ( isset( $stored['persona_models'] ) && is_array( $stored['persona_models'] ) ) {
            foreach ( $defaults['persona_models'] as $key => $default_config ) {
                if ( isset( $stored['persona_models'][ $key ] ) && is_array( $stored['persona_models'][ $key ] ) ) {
                    $settings['persona_models'][ $key ] = array_merge( $default_config, $stored['persona_models'][ $key ] );
                }
            }
        }

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
                    <tr>
                        <th scope="row"><label for="google_ai_key">Google AI API Key</label></th>
                        <td>
                            <input name="google_ai_key" type="password" id="google_ai_key" value="<?php echo esc_attr( $settings['google_ai_key'] ); ?>" class="regular-text">
                            <p class="description">Used for Gemini models and Imagen image generation.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="openrouter_api_key">OpenRouter API Key</label></th>
                        <td>
                            <input name="openrouter_api_key" type="password" id="openrouter_api_key" value="<?php echo esc_attr( $settings['openrouter_api_key'] ); ?>" class="regular-text">
                            <p class="description">
                                Used for Flux, Recraft, and other image models via OpenRouter.
                                Also used for LLM agent routing when agent model IDs contain a "/".
                                Get your key at <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</a>.
                            </p>
                        </td>
                    </tr>
                </table>

                <h2>Provider Priority</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="provider_priority">Preferred Provider</label></th>
                        <td>
                            <select name="provider_priority" id="provider_priority">
                                <option value="auto" <?php selected( $settings['provider_priority'], 'auto' ); ?>>Auto (detect from model ID)</option>
                                <option value="openai" <?php selected( $settings['provider_priority'], 'openai' ); ?>>OpenAI First (fallback to OpenRouter)</option>
                                <option value="openrouter" <?php selected( $settings['provider_priority'], 'openrouter' ); ?>>OpenRouter First (fallback to OpenAI)</option>
                            </select>
                            <p class="description">Controls which provider is tried first. "Auto" routes model IDs with "/" through OpenRouter, plain names through OpenAI.</p>
                        </td>
                    </tr>
                </table>

                <h2>Preset Profiles</h2>
                <p class="description">Apply a bulk model configuration. WARNING: This will overwrite all current agent model selections.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">Apply Profile</th>
                        <td>
                            <select name="preset_profile" id="preset_profile">
                                <?php foreach ( \KH\Editorial\Core\LLMService::PRESET_PROFILES as $key => $profile ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['preset_profile'] ?? 'balanced', $key ); ?>>
                                        <?php echo esc_html( $profile['label'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <strong>Balanced:</strong> Best overall mix. <strong>Cost:</strong> Minimises spend.
                                <strong>Speed:</strong> Fastest responses. <strong>Quality:</strong> Best output.
                            </p>
                        </td>
                    </tr>
                </table>

                <h2>Agent Model Routing</h2>
                <p class="description">Select which model each agent uses. Models with a "/" (e.g. <code>openai/gpt-4o-mini</code>) route through OpenRouter. Plain names (e.g. <code>gpt-4o-mini</code>) route through OpenAI.</p>

                <h3>Research Pipeline</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Phase 1 — Keyword/Search</th>
                        <td>
                            <select name="agent_models[research_phase1]">
                                <?php $s = $settings['agent_models']['research_phase1'] ?? 'google/gemini-2.5-flash'; ?>
                                <option value="google/gemini-2.5-flash" <?php selected($s, 'google/gemini-2.5-flash'); ?>>Gemini 2.5 Flash (fast extraction)</option>
                                <option value="deepseek/deepseek-chat" <?php selected($s, 'deepseek/deepseek-chat'); ?>>DeepSeek V3 Chat (cheap, fast)</option>
                                <option value="google/gemini-2.5-pro" <?php selected($s, 'google/gemini-2.5-pro'); ?>>Gemini 2.5 Pro (massive context)</option>
                                <option value="gpt-4o-mini" <?php selected($s, 'gpt-4o-mini'); ?>>OpenAI: GPT-4o-mini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Phase 2 — Gap Analysis</th>
                        <td>
                            <select name="agent_models[research_phase2]">
                                <?php $s = $settings['agent_models']['research_phase2'] ?? 'meta-llama/llama-3.3-70b-instruct'; ?>
                                <option value="meta-llama/llama-3.3-70b-instruct" <?php selected($s, 'meta-llama/llama-3.3-70b-instruct'); ?>>Llama 3.3 70B (reliable logic)</option>
                                <option value="deepseek/deepseek-r1" <?php selected($s, 'deepseek/deepseek-r1'); ?>>DeepSeek R1 (deep reasoning)</option>
                                <option value="google/gemini-2.5-flash" <?php selected($s, 'google/gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                <option value="gpt-4o-mini" <?php selected($s, 'gpt-4o-mini'); ?>>OpenAI: GPT-4o-mini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Phase 3 — Synopsis</th>
                        <td>
                            <select name="agent_models[research_phase3]">
                                <?php $s = $settings['agent_models']['research_phase3'] ?? 'anthropic/claude-sonnet-4.5'; ?>
                                <option value="anthropic/claude-sonnet-4.5" <?php selected($s, 'anthropic/claude-sonnet-4.5'); ?>>Claude Sonnet 4.5 (strict formatting)</option>
                                <option value="openai/gpt-4o" <?php selected($s, 'openai/gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-4o" <?php selected($s, 'gpt-4o'); ?>>OpenAI: GPT-4o</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Phase 4 — Academic Grounding</th>
                        <td>
                            <select name="agent_models[research_phase4]">
                                <?php $s = $settings['agent_models']['research_phase4'] ?? 'google/gemini-2.5-pro'; ?>
                                <option value="google/gemini-2.5-pro" <?php selected($s, 'google/gemini-2.5-pro'); ?>>Gemini 2.5 Pro (needle-in-haystack)</option>
                                <option value="anthropic/claude-sonnet-4.5" <?php selected($s, 'anthropic/claude-sonnet-4.5'); ?>>Claude Sonnet 4.5</option>
                                <option value="gpt-4o" <?php selected($s, 'gpt-4o'); ?>>OpenAI: GPT-4o</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Framework (Synopsis)</th>
                        <td>
                            <select name="agent_models[framework]">
                                <?php $s = $settings['agent_models']['framework'] ?? 'anthropic/claude-sonnet-4.5'; ?>
                                <option value="anthropic/claude-sonnet-4.5" <?php selected($s, 'anthropic/claude-sonnet-4.5'); ?>>Claude Sonnet 4.5</option>
                                <option value="openai/gpt-4o" <?php selected($s, 'openai/gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-4o" <?php selected($s, 'gpt-4o'); ?>>OpenAI: GPT-4o</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3>Fallback Models</h3>
                <p class="description">If a primary model fails, the system will try the fallback model before falling through to the ultimate fallback (gpt-4o-mini).</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">Synopsis Fallback</th>
                        <td>
                            <select name="agent_fallbacks[research_phase3]">
                                <?php $fb = $settings['agent_fallbacks']['research_phase3'] ?? ''; ?>
                                <option value="" <?php selected($fb, ''); ?>>None (use ultimate fallback)</option>
                                <option value="mistralai/mistral-large" <?php selected($fb, 'mistralai/mistral-large'); ?>>Mistral Large</option>
                                <option value="openai/gpt-4o" <?php selected($fb, 'openai/gpt-4o'); ?>>GPT-4o</option>
                                <option value="openai/gpt-4o-mini" <?php selected($fb, 'openai/gpt-4o-mini'); ?>>GPT-4o-mini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Draft Fallback</th>
                        <td>
                            <select name="agent_fallbacks[draft]">
                                <?php $fb = $settings['agent_fallbacks']['draft'] ?? ''; ?>
                                <option value="" <?php selected($fb, ''); ?>>None (use ultimate fallback)</option>
                                <option value="mistralai/mistral-large" <?php selected($fb, 'mistralai/mistral-large'); ?>>Mistral Large</option>
                                <option value="openai/gpt-4o" <?php selected($fb, 'openai/gpt-4o'); ?>>GPT-4o</option>
                                <option value="meta-llama/llama-3.3-70b-instruct" <?php selected($fb, 'meta-llama/llama-3.3-70b-instruct'); ?>>Llama 3.3 70B</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Research Phase 2 Fallback</th>
                        <td>
                            <select name="agent_fallbacks[research_phase2]">
                                <?php $fb = $settings['agent_fallbacks']['research_phase2'] ?? ''; ?>
                                <option value="" <?php selected($fb, ''); ?>>None (use ultimate fallback)</option>
                                <option value="deepseek/deepseek-chat" <?php selected($fb, 'deepseek/deepseek-chat'); ?>>DeepSeek V3 Chat</option>
                                <option value="openai/gpt-4o-mini" <?php selected($fb, 'openai/gpt-4o-mini'); ?>>GPT-4o-mini</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3>Personas</h3>
                <p class="description">Each persona uses a different model and temperature. When the Draft Agent receives a persona, it routes to the corresponding model below.</p>
                <table class="form-table">
                    <?php $persona_defaults = \KH\Editorial\Core\LLMService::DEFAULT_PERSONA_MODELS; ?>
                    <tr>
                        <th scope="row">Journalist</th>
                        <td>
                            <select name="persona_models[journalist][model]">
                                <?php $pm = $settings['persona_models']['journalist']['model'] ?? $persona_defaults['journalist']['model']; ?>
                                <option value="deepseek/deepseek-r1" <?php selected($pm, 'deepseek/deepseek-r1'); ?>>DeepSeek R1 (reasoning, active voice)</option>
                                <option value="anthropic/claude-sonnet-4.5" <?php selected($pm, 'anthropic/claude-sonnet-4.5'); ?>>Claude Sonnet 4.5 (tight style)</option>
                                <option value="gpt-4o" <?php selected($pm, 'gpt-4o'); ?>>OpenAI: GPT-4o</option>
                            </select>
                            <label>Temperature: <input name="persona_models[journalist][temperature]" type="number" step="0.1" min="0" max="1" value="<?php echo esc_attr($settings['persona_models']['journalist']['temperature'] ?? $persona_defaults['journalist']['temperature']); ?>" class="small-text"></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Industry Analyst</th>
                        <td>
                            <select name="persona_models[analyst][model]">
                                <?php $pm = $settings['persona_models']['analyst']['model'] ?? $persona_defaults['analyst']['model']; ?>
                                <option value="mistralai/mistral-large" <?php selected($pm, 'mistralai/mistral-large'); ?>>Mistral Large (direct, B2B)</option>
                                <option value="anthropic/claude-sonnet-4.5" <?php selected($pm, 'anthropic/claude-sonnet-4.5'); ?>>Claude Sonnet 4.5</option>
                                <option value="gpt-4o" <?php selected($pm, 'gpt-4o'); ?>>OpenAI: GPT-4o</option>
                            </select>
                            <label>Temperature: <input name="persona_models[analyst][temperature]" type="number" step="0.1" min="0" max="1" value="<?php echo esc_attr($settings['persona_models']['analyst']['temperature'] ?? $persona_defaults['analyst']['temperature']); ?>" class="small-text"></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Industry Veteran</th>
                        <td>
                            <select name="persona_models[veteran][model]">
                                <?php $pm = $settings['persona_models']['veteran']['model'] ?? $persona_defaults['veteran']['model']; ?>
                                <option value="meta-llama/llama-3.3-70b-instruct" <?php selected($pm, 'meta-llama/llama-3.3-70b-instruct'); ?>>Llama 3.3 70B (conversational)</option>
                                <option value="anthropic/claude-sonnet-4.5" <?php selected($pm, 'anthropic/claude-sonnet-4.5'); ?>>Claude Sonnet 4.5</option>
                                <option value="gpt-4o" <?php selected($pm, 'gpt-4o'); ?>>OpenAI: GPT-4o</option>
                            </select>
                            <label>Temperature: <input name="persona_models[veteran][temperature]" type="number" step="0.1" min="0" max="1" value="<?php echo esc_attr($settings['persona_models']['veteran']['temperature'] ?? $persona_defaults['veteran']['temperature']); ?>" class="small-text"></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Editor-at-Large</th>
                        <td>
                            <select name="persona_models[editor][model]">
                                <?php $pm = $settings['persona_models']['editor']['model'] ?? $persona_defaults['editor']['model']; ?>
                                <option value="anthropic/claude-sonnet-4.5" <?php selected($pm, 'anthropic/claude-sonnet-4.5'); ?>>Claude Sonnet 4.5 (narrative)</option>
                                <option value="openai/gpt-4o" <?php selected($pm, 'openai/gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-4o" <?php selected($pm, 'gpt-4o'); ?>>OpenAI: GPT-4o</option>
                            </select>
                            <label>Temperature: <input name="persona_models[editor][temperature]" type="number" step="0.1" min="0" max="1" value="<?php echo esc_attr($settings['persona_models']['editor']['temperature'] ?? $persona_defaults['editor']['temperature']); ?>" class="small-text"></label>
                        </td>
                    </tr>
                </table>

                <h3>Metadata & Utility</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Abstract</th>
                        <td>
                            <select name="agent_models[abstract]">
                                <?php $s = $settings['agent_models']['abstract'] ?? 'google/gemini-2.5-flash'; ?>
                                <option value="google/gemini-2.5-flash" <?php selected($s, 'google/gemini-2.5-flash'); ?>>Gemini 2.5 Flash (fast JSON)</option>
                                <option value="deepseek/deepseek-chat" <?php selected($s, 'deepseek/deepseek-chat'); ?>>DeepSeek V3 Chat</option>
                                <option value="gpt-4o-mini" <?php selected($s, 'gpt-4o-mini'); ?>>OpenAI: GPT-4o-mini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Excerpt</th>
                        <td>
                            <select name="agent_models[excerpt]">
                                <?php $s = $settings['agent_models']['excerpt'] ?? 'google/gemini-2.5-flash'; ?>
                                <option value="google/gemini-2.5-flash" <?php selected($s, 'google/gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                <option value="deepseek/deepseek-chat" <?php selected($s, 'deepseek/deepseek-chat'); ?>>DeepSeek V3 Chat</option>
                                <option value="gpt-4o-mini" <?php selected($s, 'gpt-4o-mini'); ?>>OpenAI: GPT-4o-mini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">SEO / Schema</th>
                        <td>
                            <select name="agent_models[seo_schema]">
                                <?php $s = $settings['agent_models']['seo_schema'] ?? 'google/gemini-2.5-flash'; ?>
                                <option value="google/gemini-2.5-flash" <?php selected($s, 'google/gemini-2.5-flash'); ?>>Gemini 2.5 Flash (fast JSON-LD)</option>
                                <option value="deepseek/deepseek-chat" <?php selected($s, 'deepseek/deepseek-chat'); ?>>DeepSeek V3 Chat</option>
                                <option value="gpt-4o-mini" <?php selected($s, 'gpt-4o-mini'); ?>>OpenAI: GPT-4o-mini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Gutenberg Push</th>
                        <td>
                            <select name="agent_models[gutenberg_push]">
                                <?php $s = $settings['agent_models']['gutenberg_push'] ?? 'deepseek/deepseek-chat'; ?>
                                <option value="deepseek/deepseek-chat" <?php selected($s, 'deepseek/deepseek-chat'); ?>>DeepSeek V3 Chat (clean HTML)</option>
                                <option value="google/gemini-2.5-flash" <?php selected($s, 'google/gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                <option value="gpt-4o-mini" <?php selected($s, 'gpt-4o-mini'); ?>>OpenAI: GPT-4o-mini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">GEO Answer Cards</th>
                        <td>
                            <select name="agent_models[geo_cards]">
                                <?php $s = $settings['agent_models']['geo_cards'] ?? 'meta-llama/llama-3.3-70b-instruct'; ?>
                                <option value="meta-llama/llama-3.3-70b-instruct" <?php selected($s, 'meta-llama/llama-3.3-70b-instruct'); ?>>Llama 3.3 70B (regional nuance)</option>
                                <option value="google/gemini-2.5-flash" <?php selected($s, 'google/gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                <option value="gpt-4o-mini" <?php selected($s, 'gpt-4o-mini'); ?>>OpenAI: GPT-4o-mini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Social Media Posts</th>
                        <td>
                            <select name="agent_models[social_posts]">
                                <?php $s = $settings['agent_models']['social_posts'] ?? 'meta-llama/llama-3.3-70b-instruct'; ?>
                                <option value="meta-llama/llama-3.3-70b-instruct" <?php selected($s, 'meta-llama/llama-3.3-70b-instruct'); ?>>Llama 3.3 70B (punchy copy)</option>
                                <option value="google/gemini-2.5-flash" <?php selected($s, 'google/gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                <option value="gpt-4o-mini" <?php selected($s, 'gpt-4o-mini'); ?>>OpenAI: GPT-4o-mini</option>
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

                <h2>Image Generator</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Show Prompt Editor</th>
                        <td>
                            <label>
                                <input name="show_prompt_editor" type="checkbox" value="1" <?php checked( $settings['show_prompt_editor'], 1 ); ?>>
                                Allow editors to edit the generated prompt before generating.
                            </label>
                            <p class="description">When unchecked, the prompt is auto-generated and hidden from the editor.</p>
                        </td>
                    </tr>
                </table>

                <h2>Currency & GEO</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="rate_eur">EUR Exchange Rate (1 GBP = ?)</label></th>
                        <td>
                            <input name="rate_eur" type="number" step="0.01" id="rate_eur" value="<?php echo esc_attr( $settings['currency_rates']['EUR'] ); ?>" class="small-text">
                            <p class="description">Used for dynamic pricing in the EU zone.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rate_usd">USD Exchange Rate (1 GBP = ?)</label></th>
                        <td>
                            <input name="rate_usd" type="number" step="0.01" id="rate_usd" value="<?php echo esc_attr( $settings['currency_rates']['USD'] ); ?>" class="small-text">
                            <p class="description">Used for dynamic pricing for Rest of World.</p>
                        </td>
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
        return plugins_url( $path, KH_EDITORIAL_PLUGIN_DIR );
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
        $our_pages = [
            'toplevel_page_kh-editorial-studio',
            'editorial-studio_page_kh-editorial-admin',
            'editorial-studio_page_kh-editorial-settings',
            'editorial-studio_page_kh-editorial-rate-limits',
            'editorial-studio_page_kh-editorial-db',
        ];
        
        if ( ! in_array( $hook, $our_pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'kh-editorial-admin',
            $this->get_asset_url( 'assets/css/admin.css' ),
            [],
            KH_EDITORIAL_VERSION
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
