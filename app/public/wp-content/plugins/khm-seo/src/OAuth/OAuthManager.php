<?php
/**
 * OAuth Framework & Security Manager for SEO Measurement Module
 * 
 * Comprehensive OAuth 2.0 implementation for secure API integrations with
 * Google Search Console, Google Analytics 4, and PageSpeed Insights.
 * 
 * Features:
 * - Secure token storage with encryption
 * - Automatic token refresh handling
 * - Multi-provider OAuth support (GSC, GA4)
 * - Rate limiting and quota management
 * - Security audit logging
 * - WordPress capability restrictions
 * 
 * @package KHM_SEO
 * @subpackage OAuth
 * @since 9.0.0
 */

namespace KHM_SEO\OAuth;

class OAuthManager {
    
    /**
     * OAuth provider configurations
     */
    const PROVIDERS = [
        'gsc' => [
            'name' => 'Google Search Console',
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'redirect_uri_option' => 'khm_seo_gsc_redirect_uri'
        ],
        'ga4' => [
            'name' => 'Google Analytics 4',
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'redirect_uri_option' => 'khm_seo_ga4_redirect_uri'
        ]
    ];
    
    /**
     * Security settings
     */
    const TOKEN_EXPIRY_BUFFER = 300; // 5 minutes buffer before expiry
    const MAX_RETRY_ATTEMPTS = 3;
    const RATE_LIMIT_WINDOW = 3600; // 1 hour
    const DEFAULT_RATE_LIMITS = [
        'gsc' => 1200, // requests per hour
        'ga4' => 100,  // requests per hour  
        'psi' => 25000 // requests per day
    ];
    
    /**
     * WordPress database object
     * @var \wpdb
     */
    private $wpdb;
    
    /**
     * Encryption key for token storage
     * @var string
     */
    private $encryption_key;
    
    /**
     * Rate limiting cache
     * @var array
     */
    private $rate_limits = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->encryption_key = $this->get_encryption_key();
        
        // Hook into WordPress
        add_action('init', [$this, 'handle_oauth_callback']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_khm_seo_oauth_disconnect', [$this, 'handle_disconnect']);
        add_action('wp_ajax_khm_seo_oauth_refresh', [$this, 'handle_token_refresh']);
    }
    
    /**
     * Initialize OAuth settings and create required tables
     */
    public function initialize() {
        $this->create_oauth_tables();
        $this->register_settings();
        $this->schedule_token_refresh();
    }
    
    /**
     * Create OAuth-related database tables
     */
    private function create_oauth_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // OAuth tokens table
        $tokens_table = $this->wpdb->prefix . 'seo_oauth_tokens';
        $tokens_sql = "CREATE TABLE {$tokens_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            provider ENUM('gsc', 'ga4') NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            access_token LONGTEXT NOT NULL,
            refresh_token LONGTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            scope TEXT,
            token_type VARCHAR(50) DEFAULT 'Bearer',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_used DATETIME,
            is_active BOOLEAN DEFAULT TRUE,
            PRIMARY KEY (id),
            UNIQUE KEY uk_provider_user (provider, user_id),
            KEY idx_expires_at (expires_at),
            KEY idx_provider (provider),
            KEY idx_user_id (user_id),
            KEY idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='OAuth tokens for API integrations'";
        
        dbDelta($tokens_sql);
        
        // API usage tracking table
        $usage_table = $this->wpdb->prefix . 'seo_api_usage';
        $usage_sql = "CREATE TABLE {$usage_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            provider ENUM('gsc', 'ga4', 'psi') NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            request_date DATE NOT NULL,
            request_hour TINYINT UNSIGNED NOT NULL,
            request_count INT UNSIGNED DEFAULT 1,
            quota_consumed INT UNSIGNED DEFAULT 1,
            success_count INT UNSIGNED DEFAULT 0,
            error_count INT UNSIGNED DEFAULT 0,
            avg_response_time_ms SMALLINT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_provider_endpoint_date_hour (provider, endpoint, request_date, request_hour),
            KEY idx_provider_date (provider, request_date),
            KEY idx_request_date (request_date),
            KEY idx_quota_consumed (quota_consumed DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='API usage tracking and rate limiting'";
        
        dbDelta($usage_sql);
        
        // OAuth audit log table
        $audit_table = $this->wpdb->prefix . 'seo_oauth_audit';
        $audit_sql = "CREATE TABLE {$audit_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            provider ENUM('gsc', 'ga4') NOT NULL,
            action ENUM('connect', 'disconnect', 'refresh', 'api_call', 'error') NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            success BOOLEAN DEFAULT TRUE,
            error_message TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_provider (provider),
            KEY idx_action (action),
            KEY idx_created_at (created_at),
            KEY idx_success (success)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='OAuth security audit log'";
        
        dbDelta($audit_sql);
    }
    
    /**
     * Register WordPress settings for OAuth configuration
     */
    public function register_settings() {
        // GSC OAuth settings
        register_setting('khm_seo_oauth', 'khm_seo_gsc_client_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('khm_seo_oauth', 'khm_seo_gsc_client_secret', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_client_secret']
        ]);
        
        // GA4 OAuth settings
        register_setting('khm_seo_oauth', 'khm_seo_ga4_client_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('khm_seo_oauth', 'khm_seo_ga4_client_secret', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_client_secret']
        ]);
        
        // Rate limiting settings
        register_setting('khm_seo_oauth', 'khm_seo_rate_limits', [
            'type' => 'array',
            'default' => self::DEFAULT_RATE_LIMITS
        ]);
        
        // Security settings
        register_setting('khm_seo_oauth', 'khm_seo_oauth_audit_enabled', [
            'type' => 'boolean',
            'default' => true
        ]);
    }
    
    /**
     * Generate OAuth authorization URL
     */
    public function get_authorization_url($provider, $state = null) {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
        }
        
        // Security check
        if (!current_user_can('manage_options')) {
            throw new \Exception('Insufficient permissions for OAuth setup');
        }
        
        $config = self::PROVIDERS[$provider];
        $client_id = get_option("khm_seo_{$provider}_client_id");
        
        if (empty($client_id)) {
            throw new \Exception("Client ID not configured for provider: {$provider}");
        }
        
        // Generate secure state parameter
        if (!$state) {
            $state = wp_create_nonce("khm_seo_oauth_{$provider}") . '_' . time();
        }
        
        // Store state for verification
        set_transient("khm_seo_oauth_state_{$provider}", $state, 600); // 10 minutes
        
        $redirect_uri = $this->get_redirect_uri($provider);
        
        $params = [
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => $config['scope'],
            'response_type' => 'code',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        $auth_url = $config['auth_url'] . '?' . http_build_query($params);
        
        // Audit log
        $this->log_oauth_action(get_current_user_id(), $provider, 'connect', [
            'redirect_uri' => $redirect_uri,
            'scope' => $config['scope']
        ]);
        
        return $auth_url;
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function exchange_code_for_token($provider, $code, $state) {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
        }
        
        // Verify state parameter
        $stored_state = get_transient("khm_seo_oauth_state_{$provider}");
        if (!$stored_state || $stored_state !== $state) {
            throw new \Exception('Invalid OAuth state parameter');
        }
        
        // Clean up state
        delete_transient("khm_seo_oauth_state_{$provider}");
        
        $config = self::PROVIDERS[$provider];
        $client_id = get_option("khm_seo_{$provider}_client_id");
        $client_secret = $this->decrypt_secret(get_option("khm_seo_{$provider}_client_secret"));
        
        $token_data = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->get_redirect_uri($provider)
        ];
        
        $response = wp_remote_post($config['token_url'], [
            'body' => $token_data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            $this->log_oauth_action(get_current_user_id(), $provider, 'error', [
                'error' => $response->get_error_message()
            ], false);
            throw new \Exception('Failed to exchange code for token: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $token_response = json_decode($body, true);
        
        if (isset($token_response['error'])) {
            $this->log_oauth_action(get_current_user_id(), $provider, 'error', [
                'error' => $token_response['error'],
                'description' => $token_response['error_description'] ?? ''
            ], false);
            throw new \Exception('OAuth error: ' . $token_response['error']);
        }
        
        // Store tokens securely
        $this->store_tokens($provider, $token_response);
        
        // Audit log
        $this->log_oauth_action(get_current_user_id(), $provider, 'connect', [
            'scope' => $token_response['scope'] ?? $config['scope'],
            'expires_in' => $token_response['expires_in'] ?? 3600
        ]);
        
        return true;
    }
    
    /**
     * Store OAuth tokens securely
     */
    private function store_tokens($provider, $token_response) {
        $user_id = get_current_user_id();
        $expires_at = date('Y-m-d H:i:s', time() + ($token_response['expires_in'] ?? 3600));
        
        $tokens_table = $this->wpdb->prefix . 'seo_oauth_tokens';
        
        $token_data = [
            'provider' => $provider,
            'user_id' => $user_id,
            'access_token' => $this->encrypt_token($token_response['access_token']),
            'refresh_token' => $this->encrypt_token($token_response['refresh_token'] ?? ''),
            'expires_at' => $expires_at,
            'scope' => $token_response['scope'] ?? self::PROVIDERS[$provider]['scope'],
            'token_type' => $token_response['token_type'] ?? 'Bearer',
            'is_active' => true
        ];
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
        $sql = "INSERT INTO {$tokens_table} 
                (provider, user_id, access_token, refresh_token, expires_at, scope, token_type, is_active)
                VALUES (%s, %d, %s, %s, %s, %s, %s, %d)
                ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                expires_at = VALUES(expires_at),
                scope = VALUES(scope),
                token_type = VALUES(token_type),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP";
        
        $result = $this->wpdb->query($this->wpdb->prepare(
            $sql,
            $token_data['provider'],
            $token_data['user_id'],
            $token_data['access_token'],
            $token_data['refresh_token'],
            $token_data['expires_at'],
            $token_data['scope'],
            $token_data['token_type'],
            $token_data['is_active']
        ));
        
        if ($result === false) {
            throw new \Exception('Failed to store OAuth tokens: ' . $this->wpdb->last_error);
        }
    }
    
    /**
     * Get valid access token (refresh if needed)
     */
    public function get_access_token($provider, $auto_refresh = true) {
        $tokens_table = $this->wpdb->prefix . 'seo_oauth_tokens';
        $user_id = get_current_user_id();
        
        $token_row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$tokens_table} WHERE provider = %s AND user_id = %d AND is_active = 1",
            $provider,
            $user_id
        ));
        
        if (!$token_row) {
            return null;
        }
        
        // Check if token needs refresh
        $expires_at = strtotime($token_row->expires_at);
        $now = time();
        
        if ($expires_at - $now < self::TOKEN_EXPIRY_BUFFER) {
            if ($auto_refresh && !empty($token_row->refresh_token)) {
                return $this->refresh_access_token($provider, $token_row);
            } else {
                return null; // Token expired and can't refresh
            }
        }
        
        // Update last used
        $this->wpdb->update(
            $tokens_table,
            ['last_used' => current_time('mysql')],
            ['id' => $token_row->id]
        );
        
        return [
            'access_token' => $this->decrypt_token($token_row->access_token),
            'token_type' => $token_row->token_type,
            'expires_at' => $token_row->expires_at,
            'scope' => $token_row->scope
        ];
    }
    
    /**
     * Refresh access token using refresh token
     */
    private function refresh_access_token($provider, $token_row) {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
        }
        
        $config = self::PROVIDERS[$provider];
        $client_id = get_option("khm_seo_{$provider}_client_id");
        $client_secret = $this->decrypt_secret(get_option("khm_seo_{$provider}_client_secret"));
        $refresh_token = $this->decrypt_token($token_row->refresh_token);
        
        $refresh_data = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        ];
        
        $response = wp_remote_post($config['token_url'], [
            'body' => $refresh_data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            $this->log_oauth_action($token_row->user_id, $provider, 'error', [
                'action' => 'refresh_token',
                'error' => $response->get_error_message()
            ], false);
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $token_response = json_decode($body, true);
        
        if (isset($token_response['error'])) {
            $this->log_oauth_action($token_row->user_id, $provider, 'error', [
                'action' => 'refresh_token',
                'error' => $token_response['error']
            ], false);
            return null;
        }
        
        // Update stored tokens
        $tokens_table = $this->wpdb->prefix . 'seo_oauth_tokens';
        $expires_at = date('Y-m-d H:i:s', time() + ($token_response['expires_in'] ?? 3600));
        
        $update_data = [
            'access_token' => $this->encrypt_token($token_response['access_token']),
            'expires_at' => $expires_at,
            'last_used' => current_time('mysql')
        ];
        
        // Update refresh token if provided
        if (isset($token_response['refresh_token'])) {
            $update_data['refresh_token'] = $this->encrypt_token($token_response['refresh_token']);
        }
        
        $this->wpdb->update(
            $tokens_table,
            $update_data,
            ['id' => $token_row->id]
        );
        
        // Audit log
        $this->log_oauth_action($token_row->user_id, $provider, 'refresh', [
            'expires_in' => $token_response['expires_in'] ?? 3600
        ]);
        
        return [
            'access_token' => $token_response['access_token'],
            'token_type' => $token_response['token_type'] ?? 'Bearer',
            'expires_at' => $expires_at,
            'scope' => $token_response['scope'] ?? $token_row->scope
        ];
    }
    
    /**
     * Disconnect OAuth provider
     */
    public function disconnect_provider($provider) {
        if (!current_user_can('manage_options')) {
            throw new \Exception('Insufficient permissions to disconnect OAuth provider');
        }
        
        $tokens_table = $this->wpdb->prefix . 'seo_oauth_tokens';
        $user_id = get_current_user_id();
        
        $result = $this->wpdb->update(
            $tokens_table,
            ['is_active' => false],
            [
                'provider' => $provider,
                'user_id' => $user_id
            ]
        );
        
        // Audit log
        $this->log_oauth_action($user_id, $provider, 'disconnect', [
            'rows_affected' => $result
        ]);
        
        return $result !== false;
    }
    
    /**
     * Check rate limits for API calls
     */
    public function check_rate_limit($provider, $endpoint = 'default') {
        $limits = get_option('khm_seo_rate_limits', self::DEFAULT_RATE_LIMITS);
        $limit = $limits[$provider] ?? self::DEFAULT_RATE_LIMITS[$provider] ?? 100;
        
        $usage_table = $this->wpdb->prefix . 'seo_api_usage';
        $current_date = date('Y-m-d');
        $current_hour = date('H');
        
        // Get current usage for this hour
        $current_usage = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COALESCE(SUM(request_count), 0) FROM {$usage_table} 
             WHERE provider = %s AND request_date = %s AND request_hour = %d",
            $provider,
            $current_date,
            $current_hour
        ));
        
        // For daily limits (PSI), check full day
        if ($provider === 'psi') {
            $daily_usage = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COALESCE(SUM(request_count), 0) FROM {$usage_table} 
                 WHERE provider = %s AND request_date = %s",
                $provider,
                $current_date
            ));
            return $daily_usage < $limit;
        }
        
        return $current_usage < $limit;
    }
    
    /**
     * Record API usage for rate limiting
     */
    public function record_api_usage($provider, $endpoint = 'default', $success = true, $response_time_ms = 0) {
        $usage_table = $this->wpdb->prefix . 'seo_api_usage';
        $current_date = date('Y-m-d');
        $current_hour = date('H');
        
        $sql = "INSERT INTO {$usage_table} 
                (provider, endpoint, request_date, request_hour, request_count, success_count, error_count, avg_response_time_ms)
                VALUES (%s, %s, %s, %d, 1, %d, %d, %d)
                ON DUPLICATE KEY UPDATE
                request_count = request_count + 1,
                success_count = success_count + %d,
                error_count = error_count + %d,
                avg_response_time_ms = (avg_response_time_ms + %d) / 2,
                updated_at = CURRENT_TIMESTAMP";
        
        $success_count = $success ? 1 : 0;
        $error_count = $success ? 0 : 1;
        
        $this->wpdb->query($this->wpdb->prepare(
            $sql,
            $provider,
            $endpoint,
            $current_date,
            $current_hour,
            $success_count,
            $error_count,
            $response_time_ms,
            $success_count,
            $error_count,
            $response_time_ms
        ));
    }
    
    /**
     * Get OAuth connection status
     */
    public function get_connection_status($provider = null) {
        $tokens_table = $this->wpdb->prefix . 'seo_oauth_tokens';
        $user_id = get_current_user_id();
        
        if ($provider) {
            $token = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT provider, expires_at, scope, created_at, last_used FROM {$tokens_table} 
                 WHERE provider = %s AND user_id = %d AND is_active = 1",
                $provider,
                $user_id
            ));
            
            if (!$token) {
                return ['connected' => false];
            }
            
            return [
                'connected' => true,
                'provider' => $token->provider,
                'expires_at' => $token->expires_at,
                'scope' => $token->scope,
                'connected_at' => $token->created_at,
                'last_used' => $token->last_used,
                'expires_soon' => strtotime($token->expires_at) - time() < self::TOKEN_EXPIRY_BUFFER
            ];
        }
        
        // Get all connections
        $tokens = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT provider, expires_at, scope, created_at, last_used FROM {$tokens_table} 
             WHERE user_id = %d AND is_active = 1",
            $user_id
        ));
        
        $status = [];
        foreach ($tokens as $token) {
            $status[$token->provider] = [
                'connected' => true,
                'expires_at' => $token->expires_at,
                'scope' => $token->scope,
                'connected_at' => $token->created_at,
                'last_used' => $token->last_used,
                'expires_soon' => strtotime($token->expires_at) - time() < self::TOKEN_EXPIRY_BUFFER
            ];
        }
        
        return $status;
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return;
        }
        
        // Extract provider from state
        $state_parts = explode('_', $_GET['state']);
        if (count($state_parts) < 3) {
            return;
        }
        
        $provider = str_replace(['khm', 'seo', 'oauth'], '', $state_parts[3]);
        
        if (!isset(self::PROVIDERS[$provider])) {
            wp_die('Invalid OAuth provider');
        }
        
        try {
            $this->exchange_code_for_token($provider, $_GET['code'], $_GET['state']);
            
            // Redirect to success page
            wp_redirect(admin_url('admin.php?page=khm-seo-oauth&connected=' . $provider));
            exit;
            
        } catch (\Exception $e) {
            wp_die('OAuth error: ' . esc_html($e->getMessage()));
        }
    }
    
    /**
     * Handle OAuth disconnect
     */
    public function handle_disconnect() {
        if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_oauth_disconnect')) {
            wp_die('Security check failed');
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        
        try {
            $result = $this->disconnect_provider($provider);
            wp_send_json_success(['message' => "Disconnected from {$provider}"]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Handle token refresh
     */
    public function handle_token_refresh() {
        if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_oauth_refresh')) {
            wp_die('Security check failed');
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        
        try {
            $token = $this->get_access_token($provider, true);
            if ($token) {
                wp_send_json_success(['message' => "Token refreshed for {$provider}"]);
            } else {
                wp_send_json_error(['message' => 'Failed to refresh token']);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get redirect URI for provider
     */
    public function get_redirect_uri($provider) {
        return add_query_arg(['khm_seo_oauth' => $provider], home_url());
    }
    
    /**
     * Get or generate encryption key
     */
    private function get_encryption_key() {
        $key = get_option('khm_seo_encryption_key');
        if (!$key) {
            $key = wp_generate_password(32, true, true);
            update_option('khm_seo_encryption_key', $key, false);
        }
        return $key;
    }
    
    /**
     * Encrypt sensitive data
     */
    private function encrypt_token($token) {
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . '::' . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    private function decrypt_token($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
    }
    
    /**
     * Sanitize client secret
     */
    public function sanitize_client_secret($value) {
        if (empty($value)) {
            return '';
        }
        return $this->encrypt_token($value);
    }
    
    /**
     * Decrypt client secret
     */
    private function decrypt_secret($encrypted_value) {
        return $this->decrypt_token($encrypted_value);
    }
    
    /**
     * Log OAuth actions for security audit
     */
    private function log_oauth_action($user_id, $provider, $action, $details = [], $success = true, $error_message = null) {
        if (!get_option('khm_seo_oauth_audit_enabled', true)) {
            return;
        }
        
        $audit_table = $this->wpdb->prefix . 'seo_oauth_audit';
        
        $this->wpdb->insert($audit_table, [
            'user_id' => $user_id,
            'provider' => $provider,
            'action' => $action,
            'details' => json_encode($details),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'success' => $success,
            'error_message' => $error_message
        ]);
    }
    
    /**
     * Schedule automatic token refresh
     */
    private function schedule_token_refresh() {
        if (!wp_next_scheduled('khm_seo_oauth_token_refresh')) {
            wp_schedule_event(time(), 'hourly', 'khm_seo_oauth_token_refresh');
        }
    }
    
    /**
     * Cleanup expired tokens and old audit logs
     */
    public function cleanup_oauth_data() {
        $tokens_table = $this->wpdb->prefix . 'seo_oauth_tokens';
        $usage_table = $this->wpdb->prefix . 'seo_api_usage';
        $audit_table = $this->wpdb->prefix . 'seo_oauth_audit';
        
        // Delete expired inactive tokens older than 30 days
        $this->wpdb->query("
            DELETE FROM {$tokens_table} 
            WHERE is_active = 0 AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Delete old API usage data older than 90 days
        $this->wpdb->query("
            DELETE FROM {$usage_table} 
            WHERE request_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        ");
        
        // Delete old audit logs older than 180 days
        $this->wpdb->query("
            DELETE FROM {$audit_table} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
        ");
    }
    
    /**
     * Get API usage statistics
     */
    public function get_api_usage_stats($provider = null, $days = 7) {
        $usage_table = $this->wpdb->prefix . 'seo_api_usage';
        
        $where_clause = "WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)";
        $params = [$days];
        
        if ($provider) {
            $where_clause .= " AND provider = %s";
            $params[] = $provider;
        }
        
        $sql = "SELECT 
                    provider,
                    request_date,
                    SUM(request_count) as total_requests,
                    SUM(success_count) as successful_requests,
                    SUM(error_count) as failed_requests,
                    AVG(avg_response_time_ms) as avg_response_time
                FROM {$usage_table} 
                {$where_clause}
                GROUP BY provider, request_date
                ORDER BY provider, request_date DESC";
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params));
    }
}