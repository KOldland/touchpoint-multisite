<?php
/**
 * Admin interface for Dual-GPT Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Admin {

    /**
     * Initialize admin functionality
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Dual-GPT Settings',
            'Dual-GPT',
            'manage_options',
            'dual-gpt-settings',
            array($this, 'settings_page'),
            'dashicons-admin-tools',
            12
        );

        add_submenu_page(
            'dual-gpt-settings',
            'Settings',
            'Settings',
            'manage_options',
            'dual-gpt-settings',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'dual-gpt-settings',
            'Presets',
            'Presets',
            'manage_options',
            'dual-gpt-presets',
            array($this, 'presets_page')
        );

        add_submenu_page(
            'dual-gpt-settings',
            'Budgets',
            'Budgets',
            'manage_options',
            'dual-gpt-budgets',
            array($this, 'budgets_page')
        );

        add_submenu_page(
            'dual-gpt-settings',
            'Audit Log',
            'Audit Log',
            'manage_options',
            'dual-gpt-audit',
            array($this, 'audit_page')
        );

        add_submenu_page(
            'dual-gpt-settings',
            'Integrations',
            'Integrations',
            'manage_options',
            'dual-gpt-integrations',
            array($this, 'integrations_page')
        );

        add_submenu_page(
            'dual-gpt-settings',
            'AI Images',
            'AI Images',
            'manage_options',
            'dual-gpt-images',
            array($this, 'images_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('dual_gpt_settings', 'dual_gpt_openai_api_key');
        register_setting('dual_gpt_settings', 'dual_gpt_default_model');
        register_setting('dual_gpt_settings', 'dual_gpt_max_tokens');
        register_setting('dual_gpt_settings', 'dual_gpt_default_budget');
        register_setting('dual_gpt_settings', 'dual_gpt_core_industry_focus');
        register_setting('dual_gpt_settings', 'dual_gpt_core_audience_tier');
        register_setting('dual_gpt_settings', 'dual_gpt_core_risk_tolerance');
        register_setting('dual_gpt_settings', 'dual_gpt_core_brand_profile');
        register_setting('dual_gpt_settings', 'dual_gpt_task_models', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_task_models'),
        ));
        register_setting('dual_gpt_settings', 'dual_gpt_model_override_ack');
        register_setting('dual_gpt_settings', 'dual_gpt_model_override_audit');
        register_setting('dual_gpt_integrations', 'dual_gpt_search_provider');
        register_setting('dual_gpt_integrations', 'dual_gpt_search_provider_fallbacks', array(
            'sanitize_callback' => array($this, 'sanitize_fallback_providers'),
        ));
        register_setting('dual_gpt_integrations', 'dual_gpt_search_results_limit');
        register_setting('dual_gpt_integrations', 'dual_gpt_search_cache_ttl');
        register_setting('dual_gpt_integrations', 'dual_gpt_serpapi_key');
        register_setting('dual_gpt_integrations', 'dual_gpt_tavily_key');
        register_setting('dual_gpt_integrations', 'dual_gpt_bing_key');
        register_setting('dual_gpt_integrations', 'dual_gpt_bing_endpoint');
        register_setting('dual_gpt_integrations', 'dual_gpt_google_cse_key');
        register_setting('dual_gpt_integrations', 'dual_gpt_google_cse_cx');
        register_setting('dual_gpt_integrations', 'dual_gpt_brave_key');
        register_setting('dual_gpt_integrations', 'dual_gpt_dataforseo_login');
        register_setting('dual_gpt_integrations', 'dual_gpt_dataforseo_password');
        register_setting('dual_gpt_integrations', 'dual_gpt_serper_key');
        register_setting('dual_gpt_images', 'dual_gpt_image_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_image_settings'),
        ));
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'dual-gpt') === false) {
            return;
        }

        wp_enqueue_script(
            'dual-gpt-admin',
            DUAL_GPT_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            DUAL_GPT_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'dual-gpt-admin',
            DUAL_GPT_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            DUAL_GPT_PLUGIN_VERSION
        );

        wp_localize_script('dual-gpt-admin', 'dualGptAdmin', array(
            'nonce' => wp_create_nonce('dual_gpt_admin_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('dual-gpt/v1/'),
            'modelDefaults' => class_exists('Dual_GPT_Model_Config') ? (new Dual_GPT_Model_Config())->get_default_map() : array(),
        ));
    }

    /**
     * Settings page
     */
    public function settings_page() {
        $model_config = class_exists('Dual_GPT_Model_Config') ? new Dual_GPT_Model_Config() : null;
        $task_models = $model_config ? $model_config->get_task_models() : array();
        $default_models = $model_config ? $model_config->get_default_map() : array();
        $allowed_models = $model_config ? $model_config->get_allowed_models() : array();
        ?>
        <div class="wrap">
            <h1>Dual-GPT Settings</h1>
            <?php settings_errors('dual_gpt_settings'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('dual_gpt_settings'); ?>
                <?php do_settings_sections('dual_gpt_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="password" name="dual_gpt_openai_api_key" value="<?php echo esc_attr(get_option('dual_gpt_openai_api_key')); ?>" class="regular-text" />
                            <p class="description">Enter your OpenAI API key. For production, set the OPENAI_API_KEY environment variable or DUAL_GPT_OPENAI_API_KEY constant instead of storing it in the database.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Model</th>
                        <td>
                            <select name="dual_gpt_default_model">
                                <option value="gpt-4o" <?php selected(get_option('dual_gpt_default_model', 'gpt-4o'), 'gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-4o-mini" <?php selected(get_option('dual_gpt_default_model'), 'gpt-4o-mini'); ?>>GPT-4o mini</option>
                                <option value="gpt-4.1" <?php selected(get_option('dual_gpt_default_model'), 'gpt-4.1'); ?>>GPT-4.1</option>
                                <option value="gpt-5.2" <?php selected(get_option('dual_gpt_default_model'), 'gpt-5.2'); ?>>GPT-5.2</option>
                                <option value="gpt-4" <?php selected(get_option('dual_gpt_default_model'), 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-4-turbo" <?php selected(get_option('dual_gpt_default_model'), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" <?php selected(get_option('dual_gpt_default_model'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Model Defaults (per task)</th>
                        <td>
                            <div id="dual-gpt-model-warning" style="display:none; margin-bottom: 12px; padding: 10px; background: #fff4e5; border: 1px solid #ffb74d;">
                                Warning: You have selected a non-optimal model for one or more tasks. This may increase cost or reduce quality.
                            </div>
                            <table>
                                <tr>
                                    <td style="padding-right: 12px;">Discovery</td>
                                    <td>
                                        <select name="dual_gpt_task_models[discovery]" data-default="<?php echo esc_attr($default_models['discovery'] ?? ''); ?>">
                                            <?php foreach ($allowed_models as $model) : ?>
                                                <option value="<?php echo esc_attr($model); ?>" <?php selected($task_models['discovery'] ?? '', $model); ?>>
                                                    <?php echo esc_html($model); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-right: 12px;">Validation</td>
                                    <td>
                                        <select name="dual_gpt_task_models[validation]" data-default="<?php echo esc_attr($default_models['validation'] ?? ''); ?>">
                                            <?php foreach ($allowed_models as $model) : ?>
                                                <option value="<?php echo esc_attr($model); ?>" <?php selected($task_models['validation'] ?? '', $model); ?>>
                                                    <?php echo esc_html($model); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-right: 12px;">Author Agent</td>
                                    <td>
                                        <select name="dual_gpt_task_models[author]" data-default="<?php echo esc_attr($default_models['author'] ?? ''); ?>">
                                            <?php foreach ($allowed_models as $model) : ?>
                                                <option value="<?php echo esc_attr($model); ?>" <?php selected($task_models['author'] ?? '', $model); ?>>
                                                    <?php echo esc_html($model); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-right: 12px;">Framework Agent</td>
                                    <td>
                                        <select name="dual_gpt_task_models[framework]" data-default="<?php echo esc_attr($default_models['framework'] ?? ''); ?>">
                                            <?php foreach ($allowed_models as $model) : ?>
                                                <option value="<?php echo esc_attr($model); ?>" <?php selected($task_models['framework'] ?? '', $model); ?>>
                                                    <?php echo esc_html($model); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-right: 12px;">Verification</td>
                                    <td>
                                        <select name="dual_gpt_task_models[verify]" data-default="<?php echo esc_attr($default_models['verify'] ?? ''); ?>">
                                            <?php foreach ($allowed_models as $model) : ?>
                                                <option value="<?php echo esc_attr($model); ?>" <?php selected($task_models['verify'] ?? '', $model); ?>>
                                                    <?php echo esc_html($model); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <label style="display:block; margin-top:10px;">
                                <input type="checkbox" name="dual_gpt_model_override_ack" value="1" />
                                I understand non-optimal models may increase cost or reduce quality.
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Max Tokens</th>
                        <td>
                            <input type="number" name="dual_gpt_max_tokens" value="<?php echo esc_attr(get_option('dual_gpt_max_tokens', 2000)); ?>" min="100" max="4000" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default User Budget</th>
                        <td>
                            <input type="number" name="dual_gpt_default_budget" value="<?php echo esc_attr(get_option('dual_gpt_default_budget', 100000)); ?>" min="1000" />
                            <p class="description">Default monthly token budget per user.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Industry Focus</th>
                        <td>
                            <input type="text" name="dual_gpt_core_industry_focus" value="<?php echo esc_attr(get_option('dual_gpt_core_industry_focus', 'General')); ?>" class="regular-text" />
                            <p class="description">Core industry focus used by the Author Agent.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Audience Tier</th>
                        <td>
                            <input type="text" name="dual_gpt_core_audience_tier" value="<?php echo esc_attr(get_option('dual_gpt_core_audience_tier', 'General')); ?>" class="regular-text" />
                            <p class="description">Audience tier (e.g., executive, practitioner, general).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Risk Tolerance</th>
                        <td>
                            <input type="text" name="dual_gpt_core_risk_tolerance" value="<?php echo esc_attr(get_option('dual_gpt_core_risk_tolerance', 'Moderate')); ?>" class="regular-text" />
                            <p class="description">Risk tolerance guiding the Author Agent tone.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Brand Profile</th>
                        <td>
                            <select name="dual_gpt_core_brand_profile">
                                <option value="Brand A (FSI)" <?php selected(get_option('dual_gpt_core_brand_profile', 'Brand A (FSI)'), 'Brand A (FSI)'); ?>>Brand A (FSI)</option>
                                <option value="Brand B" <?php selected(get_option('dual_gpt_core_brand_profile'), 'Brand B'); ?>>Brand B</option>
                            </select>
                            <p class="description">Controls expression only, not reasoning.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <div class="dual-gpt-test-section" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
                <h3>Test API Connection</h3>
                <button id="test-api-connection" class="button button-secondary">Test OpenAI API Connection</button>
                <div id="api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Sanitize model defaults and enforce override acknowledgement.
     */
    public function sanitize_task_models($value) {
        $config = class_exists('Dual_GPT_Model_Config') ? new Dual_GPT_Model_Config() : null;
        if (!$config) {
            return array();
        }

        $defaults = $config->get_default_map();
        $allowed = $config->get_allowed_models();
        $sanitized = array();
        $non_optimal = array();

        foreach ($defaults as $task => $default_model) {
            $candidate = sanitize_text_field($value[$task] ?? $default_model);
            if (!in_array($candidate, $allowed, true)) {
                $candidate = $default_model;
            }
            $sanitized[$task] = $candidate;
            if ($candidate !== $default_model) {
                $non_optimal[] = $task;
            }
        }

        $ack = isset($_POST['dual_gpt_model_override_ack']) ? sanitize_text_field($_POST['dual_gpt_model_override_ack']) : '';
        if (!empty($non_optimal) && $ack !== '1') {
            add_settings_error(
                'dual_gpt_task_models',
                'dual_gpt_model_override_ack_required',
                'Non-optimal model selections require confirmation.',
                'error'
            );
            return get_option('dual_gpt_task_models', $defaults);
        }

        if (!empty($non_optimal)) {
            update_option('dual_gpt_model_override_audit', array(
                'tasks' => $non_optimal,
                'updated_at' => current_time('mysql'),
                'updated_by' => get_current_user_id(),
            ));
        }

        return $sanitized;
    }

    /**
     * Sanitize fallback providers from multiselect.
     */
    public function sanitize_fallback_providers($value) {
        if (is_array($value)) {
            $value = array_map('sanitize_text_field', $value);
            $value = array_filter($value);
            return implode(',', $value);
        }

        return sanitize_text_field($value);
    }

    public function sanitize_image_settings($value) {
        $settings = class_exists('Dual_GPT_Image_Settings') ? new Dual_GPT_Image_Settings() : null;
        if (!$settings) {
            return array();
        }

        return $settings->sanitize_config($value);
    }

    /**
     * Integrations page
     */
    public function integrations_page() {
        ?>
        <div class="wrap">
            <h1>Dual-GPT Integrations</h1>
            <?php settings_errors('dual_gpt_integrations'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('dual_gpt_integrations'); ?>
                <?php do_settings_sections('dual_gpt_integrations'); ?>

                <h2>Search Providers</h2>
                <p class="description">Configure external web search providers for research workflows. Environment variables or constants override stored values.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">Primary Provider</th>
                        <td>
                            <select name="dual_gpt_search_provider">
                                <option value="none" <?php selected(get_option('dual_gpt_search_provider', 'none'), 'none'); ?>>None</option>
                                <option value="serpapi" <?php selected(get_option('dual_gpt_search_provider'), 'serpapi'); ?>>SerpAPI</option>
                                <option value="tavily" <?php selected(get_option('dual_gpt_search_provider'), 'tavily'); ?>>Tavily</option>
                                <option value="bing" <?php selected(get_option('dual_gpt_search_provider'), 'bing'); ?>>Bing Web Search</option>
                                <option value="google_cse" <?php selected(get_option('dual_gpt_search_provider'), 'google_cse'); ?>>Google CSE</option>
                                <option value="brave" <?php selected(get_option('dual_gpt_search_provider'), 'brave'); ?>>Brave Search</option>
                                <option value="dataforseo" <?php selected(get_option('dual_gpt_search_provider'), 'dataforseo'); ?>>DataForSEO</option>
                                <option value="serper" <?php selected(get_option('dual_gpt_search_provider'), 'serper'); ?>>Serper.dev</option>
                            </select>
                            <p class="description">Primary provider used by planner research. Set to None to disable live search.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Fallback Providers</th>
                        <td>
                            <?php
                            $fallbacks = get_option('dual_gpt_search_provider_fallbacks', '');
                            $fallback_values = array_filter(array_map('trim', explode(',', (string) $fallbacks)));
                            ?>
                            <select name="dual_gpt_search_provider_fallbacks[]" multiple style="min-width: 260px; height: 120px;">
                                <option value="serpapi" <?php selected(in_array('serpapi', $fallback_values, true)); ?>>SerpAPI</option>
                                <option value="tavily" <?php selected(in_array('tavily', $fallback_values, true)); ?>>Tavily</option>
                                <option value="bing" <?php selected(in_array('bing', $fallback_values, true)); ?>>Bing Web Search</option>
                                <option value="google_cse" <?php selected(in_array('google_cse', $fallback_values, true)); ?>>Google CSE</option>
                                <option value="brave" <?php selected(in_array('brave', $fallback_values, true)); ?>>Brave Search</option>
                                <option value="dataforseo" <?php selected(in_array('dataforseo', $fallback_values, true)); ?>>DataForSEO</option>
                                <option value="serper" <?php selected(in_array('serper', $fallback_values, true)); ?>>Serper.dev</option>
                            </select>
                            <p class="description">Select fallback providers. They will be used in the order shown here.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Results Limit</th>
                        <td>
                            <input type="number" name="dual_gpt_search_results_limit" value="<?php echo esc_attr(get_option('dual_gpt_search_results_limit', 16)); ?>" min="5" max="50" />
                            <p class="description">Target number of results returned per phase.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Cache TTL (minutes)</th>
                        <td>
                            <input type="number" name="dual_gpt_search_cache_ttl" value="<?php echo esc_attr(get_option('dual_gpt_search_cache_ttl', 120)); ?>" min="5" />
                            <p class="description">Cache external search responses to reduce costs.</p>
                        </td>
                    </tr>
                </table>

                <h2>Provider Credentials</h2>
                <p class="description">Prefer environment variables or constants in production. Stored values are for development only.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">SerpAPI Key</th>
                        <td>
                            <input type="password" name="dual_gpt_serpapi_key" value="<?php echo esc_attr(get_option('dual_gpt_serpapi_key')); ?>" class="regular-text" />
                            <p class="description">Env/const: DUAL_GPT_SERPAPI_KEY</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Tavily Key</th>
                        <td>
                            <input type="password" name="dual_gpt_tavily_key" value="<?php echo esc_attr(get_option('dual_gpt_tavily_key')); ?>" class="regular-text" />
                            <p class="description">Env/const: DUAL_GPT_TAVILY_KEY</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bing Key</th>
                        <td>
                            <input type="password" name="dual_gpt_bing_key" value="<?php echo esc_attr(get_option('dual_gpt_bing_key')); ?>" class="regular-text" />
                            <p class="description">Env/const: DUAL_GPT_BING_KEY</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bing Endpoint</th>
                        <td>
                            <input type="text" name="dual_gpt_bing_endpoint" value="<?php echo esc_attr(get_option('dual_gpt_bing_endpoint', 'https://api.bing.microsoft.com/v7.0/search')); ?>" class="regular-text" />
                            <p class="description">Override endpoint if using a regional host.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Google CSE API Key</th>
                        <td>
                            <input type="password" name="dual_gpt_google_cse_key" value="<?php echo esc_attr(get_option('dual_gpt_google_cse_key')); ?>" class="regular-text" />
                            <p class="description">Env/const: DUAL_GPT_GOOGLE_CSE_KEY</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Google CSE CX</th>
                        <td>
                            <input type="text" name="dual_gpt_google_cse_cx" value="<?php echo esc_attr(get_option('dual_gpt_google_cse_cx')); ?>" class="regular-text" />
                            <p class="description">Custom Search Engine ID. Env/const: DUAL_GPT_GOOGLE_CSE_CX</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Brave Search Key</th>
                        <td>
                            <input type="password" name="dual_gpt_brave_key" value="<?php echo esc_attr(get_option('dual_gpt_brave_key')); ?>" class="regular-text" />
                            <p class="description">Env/const: DUAL_GPT_BRAVE_KEY</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">DataForSEO Login</th>
                        <td>
                            <input type="text" name="dual_gpt_dataforseo_login" value="<?php echo esc_attr(get_option('dual_gpt_dataforseo_login')); ?>" class="regular-text" />
                            <p class="description">Env/const: DUAL_GPT_DATAFORSEO_LOGIN</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">DataForSEO Password</th>
                        <td>
                            <input type="password" name="dual_gpt_dataforseo_password" value="<?php echo esc_attr(get_option('dual_gpt_dataforseo_password')); ?>" class="regular-text" />
                            <p class="description">Env/const: DUAL_GPT_DATAFORSEO_PASSWORD</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Serper.dev Key</th>
                        <td>
                            <input type="password" name="dual_gpt_serper_key" value="<?php echo esc_attr(get_option('dual_gpt_serper_key')); ?>" class="regular-text" />
                            <p class="description">Env/const: DUAL_GPT_SERPER_KEY</p>
                        </td>
                    </tr>
                </table>

                <div class="dual-gpt-test-section" style="margin-top: 20px; padding: 14px; background: #f9f9f9; border: 1px solid #ddd;">
                    <h3>Test Integrations</h3>
                    <button id="test-integrations" class="button button-secondary">Test Search & Keyword Providers</button>
                    <div id="integrations-test-result" style="margin-top: 10px;"></div>
                </div>

                <?php submit_button('Save Integrations'); ?>
            </form>
        </div>
        <?php
    }

    public function images_page() {
        $settings = class_exists('Dual_GPT_Image_Settings') ? new Dual_GPT_Image_Settings() : null;
        $config = $settings ? $settings->get_config() : array();
        $catalog = $settings ? $settings->get_provider_catalog() : array();
        ?>
        <div class="wrap">
            <h1>Dual-GPT AI Images</h1>
            <?php settings_errors('dual_gpt_images'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('dual_gpt_images'); ?>
                <?php do_settings_sections('dual_gpt_images'); ?>

                <h2>Providers</h2>
                <p class="description">Configure recommendation providers, rendering providers, and house style defaults for article imagery.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">Text Recommendation Provider</th>
                        <td>
                            <select name="dual_gpt_image_settings[text_provider]">
                                <?php foreach ($catalog as $provider_key => $provider_meta) : ?>
                                    <?php if (!in_array('text', $provider_meta['supports'], true)) { continue; } ?>
                                    <option value="<?php echo esc_attr($provider_key); ?>" <?php selected($config['text_provider'] ?? '', $provider_key); ?>>
                                        <?php echo esc_html($provider_meta['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Image Rendering Provider</th>
                        <td>
                            <select name="dual_gpt_image_settings[image_provider]">
                                <?php foreach ($catalog as $provider_key => $provider_meta) : ?>
                                    <?php if (!in_array('image', $provider_meta['supports'], true)) { continue; } ?>
                                    <option value="<?php echo esc_attr($provider_key); ?>" <?php selected($config['image_provider'] ?? '', $provider_key); ?>>
                                        <?php echo esc_html($provider_meta['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Fallback Providers</th>
                        <td>
                            <select name="dual_gpt_image_settings[fallback_providers][]" multiple style="min-width: 260px; height: 100px;">
                                <?php foreach ($catalog as $provider_key => $provider_meta) : ?>
                                    <option value="<?php echo esc_attr($provider_key); ?>" <?php selected(in_array($provider_key, (array) ($config['fallback_providers'] ?? array()), true)); ?>>
                                        <?php echo esc_html($provider_meta['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2>Provider Credentials</h2>
                <table class="form-table">
                    <?php foreach ($catalog as $provider_key => $provider_meta) : ?>
                        <?php $provider_config = $config['providers'][$provider_key] ?? array(); ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($provider_meta['label']); ?></th>
                            <td>
                                <label style="display:block; margin-bottom: 8px;">
                                    <input type="checkbox" name="dual_gpt_image_settings[providers][<?php echo esc_attr($provider_key); ?>][enabled]" value="1" <?php checked(!empty($provider_config['enabled'])); ?> />
                                    Enabled
                                </label>
                                <input type="password" class="regular-text" name="dual_gpt_image_settings[providers][<?php echo esc_attr($provider_key); ?>][api_key]" value="<?php echo esc_attr($provider_config['api_key'] ?? ''); ?>" placeholder="API key" />
                                <?php if (!empty($provider_config['text_model'])) : ?>
                                    <div style="margin-top: 8px;">
                                        <input type="text" class="regular-text" name="dual_gpt_image_settings[providers][<?php echo esc_attr($provider_key); ?>][text_model]" value="<?php echo esc_attr($provider_config['text_model']); ?>" placeholder="Text model" />
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($provider_config['image_model'])) : ?>
                                    <div style="margin-top: 8px;">
                                        <input type="text" class="regular-text" name="dual_gpt_image_settings[providers][<?php echo esc_attr($provider_key); ?>][image_model]" value="<?php echo esc_attr($provider_config['image_model']); ?>" placeholder="Image model" />
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2>House Style Presets</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Default Preset</th>
                        <td>
                            <select name="dual_gpt_image_settings[default_preset_key]">
                                <?php foreach (($config['presets'] ?? array()) as $preset_key => $preset): ?>
                                    <option value="<?php echo esc_attr($preset_key); ?>" <?php selected($config['default_preset_key'] ?? '', $preset_key); ?>>
                                        <?php echo esc_html($preset['label'] ?? $preset_key); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Content managers will see this preset selected by default in the post editor.</p>
                        </td>
                    </tr>
                    <?php $preset_key = 'layered_editorial_cutout'; $preset = $config['presets'][$preset_key] ?? array(); ?>
                    <tr>
                        <th scope="row">Preset Name</th>
                        <td><input type="text" class="regular-text" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][label]" value="<?php echo esc_attr($preset['label'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Preset Description</th>
                        <td><textarea rows="2" class="large-text" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][description]"><?php echo esc_textarea($preset['description'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Art Direction</th>
                        <td><textarea rows="4" class="large-text" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][art_direction]"><?php echo esc_textarea($preset['art_direction'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Brand Palette</th>
                        <td><input type="text" class="regular-text" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][brand_palette]" value="<?php echo esc_attr($preset['brand_palette'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Negative Prompt</th>
                        <td><textarea rows="3" class="large-text" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][negative_prompt]"><?php echo esc_textarea($preset['negative_prompt'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Open Instructions</th>
                        <td><textarea rows="3" class="large-text" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][open_instructions]"><?php echo esc_textarea($preset['open_instructions'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Aspect Ratio</th>
                        <td><input type="text" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][aspect_ratio]" value="<?php echo esc_attr($preset['aspect_ratio'] ?? '16:9'); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Realism</th>
                        <td><input type="number" min="0" max="100" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][realism]" value="<?php echo esc_attr($preset['realism'] ?? 20); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Illustration Strength</th>
                        <td><input type="number" min="0" max="100" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][illustration_strength]" value="<?php echo esc_attr($preset['illustration_strength'] ?? 88); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Brand Strictness</th>
                        <td><input type="number" min="0" max="100" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][brand_strictness]" value="<?php echo esc_attr($preset['brand_strictness'] ?? 70); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Composition Toggles</th>
                        <td>
                            <label style="display:block;"><input type="checkbox" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][allow_text_overlays]" value="1" <?php checked(!empty($preset['allow_text_overlays'])); ?> /> Allow text overlays</label>
                            <label style="display:block;"><input type="checkbox" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][prefer_people]" value="1" <?php checked(!empty($preset['prefer_people'])); ?> /> Prefer people</label>
                            <label style="display:block;"><input type="checkbox" name="dual_gpt_image_settings[presets][<?php echo esc_attr($preset_key); ?>][prefer_clean_backgrounds]" value="1" <?php checked(!empty($preset['prefer_clean_backgrounds'])); ?> /> Prefer clean backgrounds</label>
                        </td>
                    </tr>
                </table>

                <h2>Workflow</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Generator Capability</th>
                        <td><input type="text" name="dual_gpt_image_settings[workflow][generator_capability]" value="<?php echo esc_attr($config['workflow']['generator_capability'] ?? 'edit_posts'); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Manager Capability</th>
                        <td><input type="text" name="dual_gpt_image_settings[workflow][manager_capability]" value="<?php echo esc_attr($config['workflow']['manager_capability'] ?? 'manage_options'); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Max Variants</th>
                        <td><input type="number" min="1" max="8" name="dual_gpt_image_settings[workflow][max_variants]" value="<?php echo esc_attr($config['workflow']['max_variants'] ?? 4); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Workflow Toggles</th>
                        <td>
                            <label style="display:block;"><input type="checkbox" name="dual_gpt_image_settings[workflow][auto_store_media]" value="1" <?php checked(!empty($config['workflow']['auto_store_media'])); ?> /> Auto-store in media library</label>
                            <label style="display:block;"><input type="checkbox" name="dual_gpt_image_settings[workflow][allow_featured_image_replace]" value="1" <?php checked(!empty($config['workflow']['allow_featured_image_replace'])); ?> /> Allow featured image replacement</label>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save AI Image Settings'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Presets page
     */
    public function presets_page() {
        $db = new Dual_GPT_DB_Handler();
        $presets = $db->get_presets();

        ?>
        <div class="wrap">
            <h1>Dual-GPT Presets</h1>

            <div style="margin-bottom: 20px;">
                <button id="add-preset" class="button button-primary">Add New Preset</button>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Model</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presets as $preset): ?>
                    <tr>
                        <td><?php echo esc_html($preset['name']); ?></td>
                        <td><?php echo esc_html($preset['role']); ?></td>
                        <td><?php echo esc_html($preset['default_model']); ?></td>
                        <td><?php echo $preset['is_locked'] ? '<span class="dashicons dashicons-lock"></span> Locked' : 'Editable'; ?></td>
                        <td>
                            <button class="button button-small edit-preset" data-id="<?php echo esc_attr($preset['id']); ?>">Edit</button>
                            <?php if (!$preset['is_locked']): ?>
                            <button class="button button-small button-link-delete delete-preset" data-id="<?php echo esc_attr($preset['id']); ?>">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Preset Modal -->
        <div id="preset-modal" style="display: none;">
            <div class="modal-content">
                <h3 id="modal-title">Add Preset</h3>
                <form id="preset-form">
                    <input type="hidden" id="preset-id" name="id" />
                    <table class="form-table">
                        <tr>
                            <th><label for="preset-name">Name</label></th>
                            <td><input type="text" id="preset-name" name="name" required /></td>
                        </tr>
                        <tr>
                            <th><label for="preset-role">Role</label></th>
                            <td>
                                <select id="preset-role" name="role" required>
                                    <option value="research">Research</option>
                                    <option value="author">Author</option>
                                    <option value="seo">SEO</option>
                                    <option value="both">Both</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="preset-model">Default Model</label></th>
                            <td>
                            <select id="preset-model" name="default_model">
                                <option value="gpt-4o">GPT-4o</option>
                                <option value="gpt-4o-mini">GPT-4o mini</option>
                                <option value="gpt-4.1">GPT-4.1</option>
                                <option value="gpt-5.2">GPT-5.2</option>
                                <option value="gpt-4">GPT-4</option>
                                <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                            </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="preset-prompt">System Prompt</label></th>
                            <td><textarea id="preset-prompt" name="system_prompt" rows="5" required></textarea></td>
                        </tr>
                    </table>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="button button-primary">Save Preset</button>
                        <button type="button" class="button" onclick="jQuery('#preset-modal').hide()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Budgets page
     */
    public function budgets_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_budgets';
        $budgets = $wpdb->get_results("SELECT * FROM $table ORDER BY scope, scope_id", ARRAY_A);

        ?>
        <div class="wrap">
            <h1>Dual-GPT Budgets</h1>

            <div style="margin-bottom: 20px;">
                <button id="add-budget" class="button button-primary">Add Budget</button>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Scope</th>
                        <th>Scope ID</th>
                        <th>Token Limit</th>
                        <th>Tokens Used</th>
                        <th>Reset Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgets as $budget): ?>
                    <tr>
                        <td><?php echo esc_html($budget['scope']); ?></td>
                        <td><?php echo esc_html($budget['scope_id']); ?></td>
                        <td><?php echo number_format($budget['token_limit']); ?></td>
                        <td><?php echo number_format($budget['token_used']); ?></td>
                        <td><?php echo esc_html($budget['reset_at']); ?></td>
                        <td>
                            <button class="button button-small edit-budget" data-id="<?php echo esc_attr($budget['id']); ?>">Edit</button>
                            <button class="button button-small button-link-delete delete-budget" data-id="<?php echo esc_attr($budget['id']); ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Audit page
     */
    public function audit_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_audit';
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100", ARRAY_A);

        ?>
        <div class="wrap">
            <h1>Dual-GPT Audit Log</h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Event Type</th>
                        <th>Timestamp</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log['job_id']); ?></td>
                        <td><?php echo esc_html($log['event_type']); ?></td>
                        <td><?php echo esc_html($log['created_at']); ?></td>
                        <td>
                            <?php
                            $payload = json_decode($log['payload_json'], true);
                            if ($payload) {
                                echo '<details><summary>View Details</summary><pre>' . esc_html(wp_json_encode($payload, JSON_PRETTY_PRINT)) . '</pre></details>';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
