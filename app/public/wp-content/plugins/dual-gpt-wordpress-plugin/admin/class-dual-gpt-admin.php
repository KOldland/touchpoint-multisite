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
            30
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
        ));
    }

    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Dual-GPT Settings</h1>

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
                                <option value="gpt-4" <?php selected(get_option('dual_gpt_default_model', 'gpt-4'), 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-4-turbo" <?php selected(get_option('dual_gpt_default_model'), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" <?php selected(get_option('dual_gpt_default_model'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            </select>
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
                                    <option value="both">Both</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="preset-model">Default Model</label></th>
                            <td>
                                <select id="preset-model" name="default_model">
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
