<?php
/**
 * GEO Tracker Connector for KHM SEO Plugin
 * Manages connection to the headless GEO Tracker service
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

namespace KHM_SEO\API;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TrackerConnector {

    /**
     * Tracker connection settings
     */
    const TRACKER_SETTINGS = [
        'tracker_url' => [
            'name' => 'GEO Tracker URL',
            'description' => 'Base URL of your GEO Tracker service (e.g., https://tracker.yourdomain.com)',
            'type' => 'url',
            'required' => true,
        ],
        'client_id' => [
            'name' => 'Client ID',
            'description' => 'Unique identifier for this WordPress site',
            'type' => 'text',
            'required' => true,
            'default' => '', // Will be generated
        ],
        'jwt_public_key' => [
            'name' => 'JWT Public Key',
            'description' => 'RSA public key for JWT authentication',
            'type' => 'textarea',
            'required' => false,
        ],
        'jwt_private_key' => [
            'name' => 'JWT Private Key',
            'description' => 'RSA private key for JWT signing (encrypted storage)',
            'type' => 'hidden',
            'required' => false,
        ],
        'alert_email' => [
            'name' => 'Alert Email',
            'description' => 'Email address for GEO tracking alerts and reports',
            'type' => 'email',
            'required' => false,
        ],
        'alert_webhook' => [
            'name' => 'Alert Webhook URL',
            'description' => 'Webhook URL for real-time alerts (Slack, Discord, etc.)',
            'type' => 'url',
            'required' => false,
        ],
    ];

    /**
     * Encryption key for sensitive data
     */
    private $encryption_key;

    /**
     * Constructor
     */
    public function __construct() {
        $this->encryption_key = $this->get_encryption_key();

        // Register hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_khm_seo_test_tracker_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_khm_seo_generate_keys', [$this, 'ajax_generate_keys']);
        add_action('wp_ajax_khm_seo_get_dashboard_url', [$this, 'ajax_get_dashboard_url']);
    }

    /**
     * Get encryption key for sensitive data storage
     */
    private function get_encryption_key() {
        $key = get_option('khm_seo_encryption_key');
        if (!$key) {
            $key = wp_generate_password(32, true, true);
            update_option('khm_seo_encryption_key', $key);
        }
        return $key;
    }

    /**
     * Encrypt sensitive data
     */
    private function encrypt_key($data) {
        if (!$data) return '';

        $cipher = "AES-256-CBC";
        $iv_length = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($data, $cipher, $this->encryption_key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     */
    private function decrypt_key($encrypted_data) {
        if (!$encrypted_data) return '';

        $cipher = "AES-256-CBC";
        $data = base64_decode($encrypted_data);
        $iv_length = openssl_cipher_iv_length($cipher);

        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        return openssl_decrypt($encrypted, $cipher, $this->encryption_key, 0, $iv);
    }

    /**
     * Get tracker setting value
     */
    public function get_tracker_setting($setting) {
        if (!isset(self::TRACKER_SETTINGS[$setting])) {
            return null;
        }

        $option_key = "khm_seo_tracker_{$setting}";
        $value = get_option($option_key);

        // Decrypt sensitive values
        if (in_array($setting, ['jwt_private_key'])) {
            $value = $this->decrypt_key($value);
        }

        return $value;
    }

    /**
     * Set tracker setting value
     */
    public function set_tracker_setting($setting, $value) {
        if (!isset(self::TRACKER_SETTINGS[$setting])) {
            return false;
        }

        // Encrypt sensitive values
        if (in_array($setting, ['jwt_private_key'])) {
            $value = $this->encrypt_key($value);
        }

        $option_key = "khm_seo_tracker_{$setting}";
        update_option($option_key, $value);

        // Set last updated timestamp
        update_option('khm_seo_tracker_last_updated', current_time('timestamp'));

        return true;
    }

    /**
     * Generate RSA keypair for JWT authentication
     */
    public function generate_rsa_keys() {
        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        $keypair = openssl_pkey_new($config);

        if (!$keypair) {
            return ['error' => 'Failed to generate RSA keypair'];
        }

        // Extract private key
        openssl_pkey_export($keypair, $private_key);

        // Extract public key
        $public_key_details = openssl_pkey_get_details($keypair);
        $public_key = $public_key_details["key"];

        return [
            'public_key' => $public_key,
            'private_key' => $private_key,
        ];
    }

    /**
     * Test connection to GEO Tracker
     */
    public function test_connection() {
        $tracker_url = $this->get_tracker_setting('tracker_url');
        $client_id = $this->get_tracker_setting('client_id');

        if (!$tracker_url || !$client_id) {
            return [
                'success' => false,
                'error' => 'Tracker URL and Client ID are required'
            ];
        }

        // Test JWKS endpoint
        $jwks_url = rtrim($tracker_url, '/') . '/.well-known/jwks.json';

        $response = wp_remote_get($jwks_url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'KHM-SEO-WordPress/' . KHM_SEO_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => "JWKS endpoint returned HTTP {$status_code}"
            ];
        }

        $jwks = json_decode($body, true);
        if (!$jwks || !isset($jwks['keys'])) {
            return [
                'success' => false,
                'error' => 'Invalid JWKS response'
            ];
        }

        return [
            'success' => true,
            'message' => 'Connection successful! JWKS endpoint is accessible.',
            'keys_count' => count($jwks['keys'])
        ];
    }

    /**
     * Get signed dashboard URL for iframe embedding
     */
    public function get_dashboard_url() {
        $tracker_url = $this->get_tracker_setting('tracker_url');
        $client_id = $this->get_tracker_setting('client_id');
        $private_key = $this->get_tracker_setting('jwt_private_key');

        if (!$tracker_url || !$client_id || !$private_key) {
            return ['error' => 'Tracker connection not configured'];
        }

        // Create JWT token
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss' => $client_id,
            'sub' => 'wordpress_dashboard',
            'iat' => time(),
            'exp' => time() + 3600, // 1 hour
            'aud' => 'geo-tracker-dashboard'
        ]);

        $header_encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $payload_encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature_input = $header_encoded . "." . $payload_encoded;

        $private_key_resource = openssl_pkey_get_private($private_key);
        openssl_sign($signature_input, $signature, $private_key_resource, 'SHA256');
        $signature_encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $token = $signature_input . "." . $signature_encoded;

        return rtrim($tracker_url, '/') . '/dashboard/' . $client_id . '?token=' . $token;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('GEO Tracker', 'khm-seo'),
            __('GEO Tracker', 'khm-seo'),
            'manage_options',
            'khm-seo-tracker',
            [$this, 'render_admin_page'],
            'dashicons-chart-line',
            30
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('khm_seo_tracker', 'khm_seo_tracker_url');
        register_setting('khm_seo_tracker', 'khm_seo_tracker_client_id');
        register_setting('khm_seo_tracker', 'khm_seo_tracker_jwt_public_key');
        register_setting('khm_seo_tracker', 'khm_seo_tracker_jwt_private_key');
        register_setting('khm_seo_tracker', 'khm_seo_tracker_alert_email');
        register_setting('khm_seo_tracker', 'khm_seo_tracker_alert_webhook');
    }

    /**
     * Render admin page with tabs
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'connection';

        ?>
        <div class="wrap">
            <h1><?php _e('GEO Tracker Connection', 'khm-seo'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=khm-seo-tracker&tab=connection" class="nav-tab <?php echo $active_tab == 'connection' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Connection', 'khm-seo'); ?>
                </a>
                <a href="?page=khm-seo-tracker&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Dashboard', 'khm-seo'); ?>
                </a>
                <a href="?page=khm-seo-tracker&tab=alerts" class="nav-tab <?php echo $active_tab == 'alerts' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Alerts', 'khm-seo'); ?>
                </a>
            </h2>

            <?php
            switch ($active_tab) {
                case 'connection':
                    $this->render_connection_tab();
                    break;
                case 'dashboard':
                    $this->render_dashboard_tab();
                    break;
                case 'alerts':
                    $this->render_alerts_tab();
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render connection settings tab
     */
    private function render_connection_tab() {
        if (isset($_POST['submit'])) {
            $this->handle_connection_submission();
        }

        ?>
        <form method="post" action="">
            <?php wp_nonce_field('khm_seo_tracker_connection'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="khm_seo_tracker_url"><?php _e('GEO Tracker URL', 'khm-seo'); ?></label>
                    </th>
                    <td>
                        <input type="url"
                               id="khm_seo_tracker_url"
                               name="khm_seo_tracker_url"
                               value="<?php echo esc_attr($this->get_tracker_setting('tracker_url') ?: ''); ?>"
                               class="regular-text"
                               placeholder="https://tracker.yourdomain.com"
                               required />
                        <p class="description">
                            <?php _e('Base URL of your GEO Tracker service', 'khm-seo'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="khm_seo_tracker_client_id"><?php _e('Client ID', 'khm-seo'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="khm_seo_tracker_client_id"
                               name="khm_seo_tracker_client_id"
                               value="<?php echo esc_attr($this->get_tracker_setting('client_id') ?: ''); ?>"
                               class="regular-text"
                               placeholder="your-site-client-id"
                               required />
                        <p class="description">
                            <?php _e('Unique identifier for this WordPress site', 'khm-seo'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('JWT Keys', 'khm-seo'); ?></th>
                    <td>
                        <button type="button" id="generate-keys" class="button button-secondary">
                            <?php _e('Generate RSA Keypair', 'khm-seo'); ?>
                        </button>
                        <span id="key-generation-status"></span>
                        <p class="description">
                            <?php _e('Generate new RSA keys for JWT authentication with the tracker', 'khm-seo'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Connection Test', 'khm-seo'); ?></th>
                    <td>
                        <button type="button" id="test-connection" class="button button-secondary">
                            <?php _e('Test Connection', 'khm-seo'); ?>
                        </button>
                        <span id="connection-test-status"></span>
                        <p class="description">
                            <?php _e('Test connectivity to your GEO Tracker service', 'khm-seo'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Connection Settings', 'khm-seo')); ?>
        </form>

        <script>
        jQuery(document).ready(function($) {
            $('#generate-keys').on('click', function() {
                const $status = $('#key-generation-status');
                $status.html('<span style="color:orange;">Generating...</span>');

                $.post(ajaxurl, {
                    action: 'khm_seo_generate_keys',
                    nonce: '<?php echo wp_create_nonce('khm_seo_generate_keys'); ?>'
                }, function(response) {
                    if (response.success) {
                        $status.html('<span style="color:green;">✓ Keys generated successfully!</span>');
                        location.reload();
                    } else {
                        $status.html('<span style="color:red;">✗ ' + response.data.error + '</span>');
                    }
                });
            });

            $('#test-connection').on('click', function() {
                const $status = $('#connection-test-status');
                $status.html('<span style="color:orange;">Testing...</span>');

                $.post(ajaxurl, {
                    action: 'khm_seo_test_tracker_connection',
                    nonce: '<?php echo wp_create_nonce('khm_seo_test_connection'); ?>'
                }, function(response) {
                    if (response.success) {
                        $status.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $status.html('<span style="color:red;">✗ ' + response.data.error + '</span>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render dashboard tab with iframe
     */
    private function render_dashboard_tab() {
        $dashboard_url = $this->get_dashboard_url();

        if (is_array($dashboard_url) && isset($dashboard_url['error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($dashboard_url['error']) . '</p></div>';
            echo '<p>' . __('Please configure your tracker connection first.', 'khm-seo') . '</p>';
            return;
        }

        ?>
        <div class="tracker-dashboard">
            <p><?php _e('Your GEO tracking dashboard is embedded below:', 'khm-seo'); ?></p>

            <iframe src="<?php echo esc_url($dashboard_url); ?>"
                    width="100%"
                    height="800"
                    frameborder="0"
                    style="border: 1px solid #ddd; border-radius: 4px;">
                <?php _e('Your browser does not support iframes.', 'khm-seo'); ?>
            </iframe>
        </div>

        <style>
        .tracker-dashboard iframe {
            width: 100%;
            min-height: 600px;
        }
        </style>
        <?php
    }

    /**
     * Render alerts configuration tab
     */
    private function render_alerts_tab() {
        if (isset($_POST['submit'])) {
            $this->handle_alerts_submission();
        }

        ?>
        <form method="post" action="">
            <?php wp_nonce_field('khm_seo_tracker_alerts'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="khm_seo_tracker_alert_email"><?php _e('Alert Email', 'khm-seo'); ?></label>
                    </th>
                    <td>
                        <input type="email"
                               id="khm_seo_tracker_alert_email"
                               name="khm_seo_tracker_alert_email"
                               value="<?php echo esc_attr($this->get_tracker_setting('alert_email') ?: ''); ?>"
                               class="regular-text"
                               placeholder="alerts@yourdomain.com" />
                        <p class="description">
                            <?php _e('Email address for GEO tracking alerts and reports', 'khm-seo'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="khm_seo_tracker_alert_webhook"><?php _e('Alert Webhook URL', 'khm-seo'); ?></label>
                    </th>
                    <td>
                        <input type="url"
                               id="khm_seo_tracker_alert_webhook"
                               name="khm_seo_tracker_alert_webhook"
                               value="<?php echo esc_attr($this->get_tracker_setting('alert_webhook') ?: ''); ?>"
                               class="regular-text"
                               placeholder="https://hooks.slack.com/services/..." />
                        <p class="description">
                            <?php _e('Webhook URL for real-time alerts (Slack, Discord, etc.)', 'khm-seo'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Alert Settings', 'khm-seo')); ?>
        </form>
        <?php
    }

    /**
     * Handle connection settings submission
     */
    private function handle_connection_submission() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'khm_seo_tracker_connection')) {
            return;
        }

        $tracker_url = sanitize_text_field($_POST['khm_seo_tracker_url']);
        $client_id = sanitize_text_field($_POST['khm_seo_tracker_client_id']);

        $this->set_tracker_setting('tracker_url', $tracker_url);
        $this->set_tracker_setting('client_id', $client_id);

        echo '<div class="notice notice-success"><p>' . __('Connection settings saved successfully.', 'khm-seo') . '</p></div>';
    }

    /**
     * Handle alerts settings submission
     */
    private function handle_alerts_submission() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'khm_seo_tracker_alerts')) {
            return;
        }

        $alert_email = sanitize_email($_POST['khm_seo_tracker_alert_email']);
        $alert_webhook = esc_url_raw($_POST['khm_seo_tracker_alert_webhook']);

        $this->set_tracker_setting('alert_email', $alert_email);
        $this->set_tracker_setting('alert_webhook', $alert_webhook);

        echo '<div class="notice notice-success"><p>' . __('Alert settings saved successfully.', 'khm-seo') . '</p></div>';
    }

    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('khm_seo_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        $result = $this->test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for generating RSA keys
     */
    public function ajax_generate_keys() {
        check_ajax_referer('khm_seo_generate_keys', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        $keys = $this->generate_rsa_keys();

        if (isset($keys['error'])) {
            wp_send_json_error(['error' => $keys['error']]);
        } else {
            $this->set_tracker_setting('jwt_public_key', $keys['public_key']);
            $this->set_tracker_setting('jwt_private_key', $keys['private_key']);

            wp_send_json_success(['message' => 'RSA keys generated and saved successfully']);
        }
    }

    /**
     * AJAX handler for getting dashboard URL
     */
    public function ajax_get_dashboard_url() {
        check_ajax_referer('khm_seo_get_dashboard_url', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        $url = $this->get_dashboard_url();

        if (is_array($url) && isset($url['error'])) {
            wp_send_json_error(['error' => $url['error']]);
        } else {
            wp_send_json_success(['url' => $url]);
        }
    }
}