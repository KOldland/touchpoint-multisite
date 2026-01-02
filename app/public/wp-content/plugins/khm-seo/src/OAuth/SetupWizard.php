<?php
/**
 * Setup Wizard for SEO Measurement Module OAuth Configuration
 * 
 * Comprehensive setup wizard that guides users through:
 * - Google Search Console API setup and OAuth connection
 * - Google Analytics 4 API setup and OAuth connection  
 * - API credentials configuration and validation
 * - Property selection and verification
 * - Rate limiting and scheduling configuration
 * - Security settings and audit options
 * 
 * @package KHM_SEO
 * @subpackage OAuth
 * @since 9.0.0
 */

namespace KHM_SEO\OAuth;

class SetupWizard {
    
    /**
     * OAuth Manager instance
     * @var OAuthManager
     */
    private $oauth_manager;
    
    /**
     * Wizard steps configuration
     */
    const WIZARD_STEPS = [
        'welcome' => [
            'title' => 'Welcome to SEO Measurement Setup',
            'description' => 'Configure your API connections for comprehensive SEO analysis',
            'required' => false
        ],
        'gsc_setup' => [
            'title' => 'Google Search Console Setup',
            'description' => 'Connect to Google Search Console for search performance data',
            'required' => true
        ],
        'gsc_properties' => [
            'title' => 'Select GSC Properties',
            'description' => 'Choose which Search Console properties to monitor',
            'required' => true
        ],
        'ga4_setup' => [
            'title' => 'Google Analytics 4 Setup',
            'description' => 'Connect to Google Analytics for engagement metrics (optional)',
            'required' => false
        ],
        'ga4_properties' => [
            'title' => 'Select GA4 Properties',
            'description' => 'Choose which Analytics properties to track',
            'required' => false
        ],
        'settings' => [
            'title' => 'Configure Settings',
            'description' => 'Set up rate limits, scheduling, and security options',
            'required' => true
        ],
        'complete' => [
            'title' => 'Setup Complete',
            'description' => 'Your SEO measurement platform is ready to go!',
            'required' => false
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->oauth_manager = new OAuthManager();
        
        // Hook into WordPress admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_khm_seo_wizard_step', [$this, 'handle_wizard_step']);
        add_action('wp_ajax_khm_seo_validate_credentials', [$this, 'validate_api_credentials']);
        add_action('wp_ajax_khm_seo_get_properties', [$this, 'get_api_properties']);
        add_action('admin_init', [$this, 'handle_wizard_redirect']);
    }
    
    /**
     * Add admin menu for setup wizard
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-seo-dashboard',
            'SEO Measurement Setup',
            'Setup Wizard',
            'manage_options',
            'khm-seo-setup-wizard',
            [$this, 'render_wizard']
        );
    }
    
    /**
     * Enqueue required scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'seo_page_khm-seo-setup-wizard') {
            return;
        }
        
        wp_enqueue_script(
            'khm-seo-setup-wizard',
            plugins_url('assets/js/setup-wizard.js', __FILE__),
            ['jquery', 'wp-api-fetch'],
            '9.0.0',
            true
        );
        
        wp_enqueue_style(
            'khm-seo-setup-wizard',
            plugins_url('assets/css/setup-wizard.css', __FILE__),
            [],
            '9.0.0'
        );
        
        wp_localize_script('khm-seo-setup-wizard', 'khmSeoWizard', [
            'nonce' => wp_create_nonce('khm_seo_wizard'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'steps' => self::WIZARD_STEPS,
            'currentStep' => $this->get_current_step(),
            'completedSteps' => $this->get_completed_steps()
        ]);
    }
    
    /**
     * Render the setup wizard interface
     */
    public function render_wizard() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $current_step = $this->get_current_step();
        $completed_steps = $this->get_completed_steps();
        ?>
        <div class="khm-seo-setup-wizard">
            <div class="wizard-header">
                <h1>SEO Measurement Platform Setup</h1>
                <div class="wizard-progress">
                    <?php $this->render_progress_bar(); ?>
                </div>
            </div>
            
            <div class="wizard-container">
                <div class="wizard-sidebar">
                    <?php $this->render_step_navigation(); ?>
                </div>
                
                <div class="wizard-content">
                    <?php $this->render_current_step($current_step); ?>
                </div>
            </div>
            
            <div class="wizard-footer">
                <div class="wizard-actions">
                    <button type="button" id="wizard-prev" class="button">← Previous</button>
                    <button type="button" id="wizard-next" class="button button-primary">Next →</button>
                    <button type="button" id="wizard-skip" class="button">Skip</button>
                    <button type="button" id="wizard-complete" class="button button-primary" style="display: none;">Complete Setup</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render progress bar
     */
    private function render_progress_bar() {
        $total_steps = count(self::WIZARD_STEPS);
        $current_step_index = $this->get_step_index($this->get_current_step());
        $progress_percentage = ($current_step_index + 1) / $total_steps * 100;
        ?>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%"></div>
            <span class="progress-text"><?php echo ($current_step_index + 1) . ' of ' . $total_steps; ?></span>
        </div>
        <?php
    }
    
    /**
     * Render step navigation sidebar
     */
    private function render_step_navigation() {
        $current_step = $this->get_current_step();
        $completed_steps = $this->get_completed_steps();
        
        echo '<ul class="wizard-nav">';
        foreach (self::WIZARD_STEPS as $step_key => $step_config) {
            $is_current = $step_key === $current_step;
            $is_completed = in_array($step_key, $completed_steps);
            $is_accessible = $is_completed || $is_current || $this->is_step_accessible($step_key);
            
            $classes = ['wizard-nav-item'];
            if ($is_current) $classes[] = 'current';
            if ($is_completed) $classes[] = 'completed';
            if (!$is_accessible) $classes[] = 'disabled';
            if ($step_config['required']) $classes[] = 'required';
            
            echo '<li class="' . implode(' ', $classes) . '">';
            echo '<a href="#" data-step="' . esc_attr($step_key) . '">';
            echo '<span class="step-icon">' . ($is_completed ? '✓' : ($is_current ? '▶' : '○')) . '</span>';
            echo '<span class="step-title">' . esc_html($step_config['title']) . '</span>';
            if ($step_config['required']) {
                echo '<span class="required-indicator">*</span>';
            }
            echo '</a></li>';
        }
        echo '</ul>';
    }
    
    /**
     * Render current step content
     */
    private function render_current_step($step) {
        switch ($step) {
            case 'welcome':
                $this->render_welcome_step();
                break;
            case 'gsc_setup':
                $this->render_gsc_setup_step();
                break;
            case 'gsc_properties':
                $this->render_gsc_properties_step();
                break;
            case 'ga4_setup':
                $this->render_ga4_setup_step();
                break;
            case 'ga4_properties':
                $this->render_ga4_properties_step();
                break;
            case 'settings':
                $this->render_settings_step();
                break;
            case 'complete':
                $this->render_complete_step();
                break;
            default:
                $this->render_welcome_step();
        }
    }
    
    /**
     * Render welcome step
     */
    private function render_welcome_step() {
        ?>
        <div class="wizard-step" data-step="welcome">
            <div class="step-header">
                <h2>🚀 Welcome to SEO Measurement Platform</h2>
                <p>Transform your WordPress site into a comprehensive SEO intelligence command center!</p>
            </div>
            
            <div class="welcome-content">
                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Google Search Console</h3>
                        <p>Track clicks, impressions, CTR, and average positions for all your keywords</p>
                        <span class="feature-status required">Required</span>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">📈</div>
                        <h3>Google Analytics 4</h3>
                        <p>Monitor user engagement, session duration, and conversion metrics</p>
                        <span class="feature-status optional">Optional</span>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">⚡</div>
                        <h3>Core Web Vitals</h3>
                        <p>Automatic PageSpeed Insights monitoring for performance optimization</p>
                        <span class="feature-status automatic">Automatic</span>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">🕷️</div>
                        <h3>Internal Crawler</h3>
                        <p>Technical SEO analysis with broken link detection and schema validation</p>
                        <span class="feature-status automatic">Automatic</span>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">🚨</div>
                        <h3>Smart Alerts</h3>
                        <p>Proactive notifications for ranking drops, technical issues, and opportunities</p>
                        <span class="feature-status automatic">Automatic</span>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">🎯</div>
                        <h3>5-Score System</h3>
                        <p>Explainable SEO scoring with prioritized optimization recommendations</p>
                        <span class="feature-status automatic">Automatic</span>
                    </div>
                </div>
                
                <div class="setup-timeline">
                    <h3>⏱️ Setup Timeline</h3>
                    <ul>
                        <li><strong>5 minutes:</strong> Google Search Console connection (required)</li>
                        <li><strong>3 minutes:</strong> Google Analytics 4 connection (optional)</li>
                        <li><strong>2 minutes:</strong> Configure settings and preferences</li>
                        <li><strong>Total:</strong> 5-10 minutes to complete setup</li>
                    </ul>
                </div>
                
                <div class="prerequisite-check">
                    <h3>✅ Prerequisites Check</h3>
                    <div class="check-list">
                        <div class="check-item" data-check="admin-access">
                            <span class="check-icon">🔄</span>
                            <span class="check-text">Administrator access to WordPress</span>
                            <span class="check-status">Checking...</span>
                        </div>
                        <div class="check-item" data-check="google-account">
                            <span class="check-icon">🔄</span>
                            <span class="check-text">Google account with site verification</span>
                            <span class="check-status">Manual verification required</span>
                        </div>
                        <div class="check-item" data-check="ssl">
                            <span class="check-icon">🔄</span>
                            <span class="check-text">SSL certificate (HTTPS required for OAuth)</span>
                            <span class="check-status">Checking...</span>
                        </div>
                        <div class="check-item" data-check="curl">
                            <span class="check-icon">🔄</span>
                            <span class="check-text">PHP cURL extension for API calls</span>
                            <span class="check-status">Checking...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render GSC setup step
     */
    private function render_gsc_setup_step() {
        $connection_status = $this->oauth_manager->get_connection_status('gsc');
        ?>
        <div class="wizard-step" data-step="gsc_setup">
            <div class="step-header">
                <h2>📊 Google Search Console Setup</h2>
                <p>Connect your Google Search Console to unlock search performance insights</p>
            </div>
            
            <?php if ($connection_status['connected'] ?? false): ?>
                <div class="connection-status connected">
                    <div class="status-icon">✅</div>
                    <div class="status-info">
                        <h3>Connected Successfully!</h3>
                        <p>Connected on: <?php echo esc_html($connection_status['connected_at']); ?></p>
                        <p>Last used: <?php echo esc_html($connection_status['last_used'] ?? 'Never'); ?></p>
                        <p>Expires: <?php echo esc_html($connection_status['expires_at']); ?></p>
                    </div>
                    <div class="status-actions">
                        <button type="button" class="button" id="gsc-disconnect">Disconnect</button>
                        <button type="button" class="button" id="gsc-test">Test Connection</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="oauth-setup">
                    <div class="setup-instructions">
                        <h3>🔧 Setup Instructions</h3>
                        <ol>
                            <li><strong>Create Google Cloud Project:</strong>
                                <ul>
                                    <li>Visit <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
                                    <li>Create a new project or select existing one</li>
                                    <li>Enable the "Google Search Console API"</li>
                                </ul>
                            </li>
                            <li><strong>Configure OAuth Credentials:</strong>
                                <ul>
                                    <li>Go to "Credentials" → "Create Credentials" → "OAuth client ID"</li>
                                    <li>Application type: "Web application"</li>
                                    <li>Authorized redirect URI: <code><?php echo esc_html($this->oauth_manager->get_redirect_uri('gsc')); ?></code></li>
                                </ul>
                            </li>
                            <li><strong>Copy credentials below and connect</strong></li>
                        </ol>
                    </div>
                    
                    <div class="credentials-form">
                        <h3>🔑 API Credentials</h3>
                        <form id="gsc-credentials-form">
                            <div class="form-group">
                                <label for="gsc_client_id">Client ID</label>
                                <input type="text" 
                                       id="gsc_client_id" 
                                       name="gsc_client_id" 
                                       value="<?php echo esc_attr(get_option('khm_seo_gsc_client_id', '')); ?>"
                                       placeholder="123456789-abcdefghijklmnop.apps.googleusercontent.com" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="gsc_client_secret">Client Secret</label>
                                <input type="password" 
                                       id="gsc_client_secret" 
                                       name="gsc_client_secret" 
                                       placeholder="Enter your client secret" 
                                       required>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" id="gsc-validate" class="button">Validate Credentials</button>
                                <button type="button" id="gsc-connect" class="button button-primary" disabled>Connect to Google</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="help-section">
                        <h3>❓ Need Help?</h3>
                        <details>
                            <summary>Common Setup Issues</summary>
                            <ul>
                                <li><strong>Invalid redirect URI:</strong> Make sure the redirect URI in Google Cloud matches exactly</li>
                                <li><strong>API not enabled:</strong> Ensure Google Search Console API is enabled in your project</li>
                                <li><strong>Quota exceeded:</strong> Check your API quotas in Google Cloud Console</li>
                            </ul>
                        </details>
                        
                        <details>
                            <summary>Video Tutorial</summary>
                            <p>Watch our step-by-step video guide: <a href="#" target="_blank">Google Search Console Setup</a></p>
                        </details>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render GSC properties step
     */
    private function render_gsc_properties_step() {
        ?>
        <div class="wizard-step" data-step="gsc_properties">
            <div class="step-header">
                <h2>🏠 Select GSC Properties</h2>
                <p>Choose which Search Console properties you want to monitor</p>
            </div>
            
            <div class="properties-container">
                <div class="properties-loading" id="gsc-properties-loading">
                    <div class="loading-spinner"></div>
                    <p>Loading your Search Console properties...</p>
                </div>
                
                <div class="properties-list" id="gsc-properties-list" style="display: none;">
                    <div class="properties-header">
                        <h3>Available Properties</h3>
                        <button type="button" id="gsc-refresh-properties" class="button">🔄 Refresh</button>
                    </div>
                    
                    <div class="properties-grid" id="gsc-properties-grid">
                        <!-- Properties will be loaded via JavaScript -->
                    </div>
                    
                    <div class="properties-actions">
                        <button type="button" id="gsc-select-all" class="button">Select All</button>
                        <button type="button" id="gsc-select-none" class="button">Select None</button>
                    </div>
                </div>
                
                <div class="properties-error" id="gsc-properties-error" style="display: none;">
                    <div class="error-icon">❌</div>
                    <h3>Unable to Load Properties</h3>
                    <p id="gsc-properties-error-message"></p>
                    <button type="button" id="gsc-retry-properties" class="button">Try Again</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render GA4 setup step
     */
    private function render_ga4_setup_step() {
        $connection_status = $this->oauth_manager->get_connection_status('ga4');
        ?>
        <div class="wizard-step" data-step="ga4_setup">
            <div class="step-header">
                <h2>📈 Google Analytics 4 Setup (Optional)</h2>
                <p>Connect Google Analytics 4 to track user engagement and behavior metrics</p>
            </div>
            
            <div class="optional-notice">
                <div class="notice-icon">ℹ️</div>
                <div class="notice-content">
                    <h3>This step is optional</h3>
                    <p>GA4 integration provides additional engagement insights but isn't required for basic SEO monitoring. You can skip this step and continue with Google Search Console data only.</p>
                </div>
                <div class="notice-actions">
                    <button type="button" id="ga4-skip" class="button">Skip GA4 Setup</button>
                </div>
            </div>
            
            <?php if ($connection_status['connected'] ?? false): ?>
                <div class="connection-status connected">
                    <div class="status-icon">✅</div>
                    <div class="status-info">
                        <h3>GA4 Connected Successfully!</h3>
                        <p>Connected on: <?php echo esc_html($connection_status['connected_at']); ?></p>
                        <p>Last used: <?php echo esc_html($connection_status['last_used'] ?? 'Never'); ?></p>
                    </div>
                    <div class="status-actions">
                        <button type="button" class="button" id="ga4-disconnect">Disconnect</button>
                        <button type="button" class="button" id="ga4-test">Test Connection</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="oauth-setup">
                    <div class="benefits-section">
                        <h3>🎯 Benefits of GA4 Integration</h3>
                        <div class="benefits-grid">
                            <div class="benefit-item">
                                <span class="benefit-icon">📊</span>
                                <h4>Engagement Correlation</h4>
                                <p>See how search performance correlates with user engagement</p>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon">⏱️</span>
                                <h4>Time Metrics</h4>
                                <p>Track session duration and time on page for SEO insights</p>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon">🎯</span>
                                <h4>Conversion Tracking</h4>
                                <p>Monitor how organic traffic converts to business goals</p>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon">📱</span>
                                <h4>Device Analysis</h4>
                                <p>Understand performance across desktop, mobile, and tablet</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="credentials-form">
                        <h3>🔑 GA4 API Credentials</h3>
                        <form id="ga4-credentials-form">
                            <div class="form-group">
                                <label for="ga4_client_id">Client ID</label>
                                <input type="text" 
                                       id="ga4_client_id" 
                                       name="ga4_client_id" 
                                       value="<?php echo esc_attr(get_option('khm_seo_ga4_client_id', '')); ?>"
                                       placeholder="Use same Client ID as GSC or create new one">
                            </div>
                            
                            <div class="form-group">
                                <label for="ga4_client_secret">Client Secret</label>
                                <input type="password" 
                                       id="ga4_client_secret" 
                                       name="ga4_client_secret" 
                                       placeholder="Enter your client secret">
                            </div>
                            
                            <div class="form-note">
                                <p><strong>💡 Pro Tip:</strong> You can use the same Google Cloud project and OAuth credentials as Search Console. Just add the Google Analytics API to your existing project.</p>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" id="ga4-validate" class="button">Validate Credentials</button>
                                <button type="button" id="ga4-connect" class="button button-primary" disabled>Connect to GA4</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render GA4 properties step
     */
    private function render_ga4_properties_step() {
        ?>
        <div class="wizard-step" data-step="ga4_properties">
            <div class="step-header">
                <h2>🏠 Select GA4 Properties</h2>
                <p>Choose which Google Analytics 4 properties to monitor</p>
            </div>
            
            <!-- Similar structure to GSC properties but for GA4 -->
            <div class="properties-container">
                <div class="properties-loading" id="ga4-properties-loading">
                    <div class="loading-spinner"></div>
                    <p>Loading your Google Analytics 4 properties...</p>
                </div>
                
                <div class="properties-list" id="ga4-properties-list" style="display: none;">
                    <!-- GA4 properties will be loaded here -->
                </div>
                
                <div class="properties-error" id="ga4-properties-error" style="display: none;">
                    <div class="error-icon">❌</div>
                    <h3>Unable to Load GA4 Properties</h3>
                    <p id="ga4-properties-error-message"></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings configuration step
     */
    private function render_settings_step() {
        $rate_limits = get_option('khm_seo_rate_limits', OAuthManager::DEFAULT_RATE_LIMITS);
        ?>
        <div class="wizard-step" data-step="settings">
            <div class="step-header">
                <h2>⚙️ Configure Settings</h2>
                <p>Set up rate limits, data collection schedules, and security preferences</p>
            </div>
            
            <div class="settings-tabs">
                <ul class="tab-nav">
                    <li><a href="#tab-rate-limits" class="active">Rate Limits</a></li>
                    <li><a href="#tab-scheduling">Data Collection</a></li>
                    <li><a href="#tab-security">Security</a></li>
                    <li><a href="#tab-notifications">Notifications</a></li>
                </ul>
                
                <div class="tab-content">
                    <div id="tab-rate-limits" class="tab-panel active">
                        <h3>📊 API Rate Limits</h3>
                        <p>Configure how frequently the plugin makes API calls to prevent quota exhaustion</p>
                        
                        <div class="rate-limits-grid">
                            <div class="rate-limit-item">
                                <label for="gsc_rate_limit">Google Search Console</label>
                                <input type="number" 
                                       id="gsc_rate_limit" 
                                       name="gsc_rate_limit" 
                                       value="<?php echo esc_attr($rate_limits['gsc'] ?? 1200); ?>" 
                                       min="100" 
                                       max="2000">
                                <span class="rate-limit-unit">requests/hour</span>
                                <p class="rate-limit-note">Default: 1,200/hour (safe limit for most sites)</p>
                            </div>
                            
                            <div class="rate-limit-item">
                                <label for="ga4_rate_limit">Google Analytics 4</label>
                                <input type="number" 
                                       id="ga4_rate_limit" 
                                       name="ga4_rate_limit" 
                                       value="<?php echo esc_attr($rate_limits['ga4'] ?? 100); ?>" 
                                       min="50" 
                                       max="500">
                                <span class="rate-limit-unit">requests/hour</span>
                                <p class="rate-limit-note">Default: 100/hour (GA4 has stricter limits)</p>
                            </div>
                            
                            <div class="rate-limit-item">
                                <label for="psi_rate_limit">PageSpeed Insights</label>
                                <input type="number" 
                                       id="psi_rate_limit" 
                                       name="psi_rate_limit" 
                                       value="<?php echo esc_attr($rate_limits['psi'] ?? 25000); ?>" 
                                       min="1000" 
                                       max="100000">
                                <span class="rate-limit-unit">requests/day</span>
                                <p class="rate-limit-note">Default: 25,000/day (free tier limit)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="tab-scheduling" class="tab-panel">
                        <h3>📅 Data Collection Schedule</h3>
                        <p>Configure when and how often to collect SEO data</p>
                        
                        <div class="schedule-grid">
                            <div class="schedule-item">
                                <h4>🔍 Google Search Console Data</h4>
                                <select name="gsc_schedule">
                                    <option value="hourly">Every hour</option>
                                    <option value="twicedaily" selected>Twice daily</option>
                                    <option value="daily">Daily</option>
                                </select>
                                <p>Recommendation: Twice daily for most sites</p>
                            </div>
                            
                            <div class="schedule-item">
                                <h4>⚡ Core Web Vitals</h4>
                                <select name="cwv_schedule">
                                    <option value="daily">Daily</option>
                                    <option value="weekly" selected>Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                                <p>Recommendation: Weekly (CWV changes slowly)</p>
                            </div>
                            
                            <div class="schedule-item">
                                <h4>🕷️ Internal Crawling</h4>
                                <select name="crawl_schedule">
                                    <option value="daily" selected>Daily (partial)</option>
                                    <option value="weekly">Weekly (full)</option>
                                    <option value="monthly">Monthly only</option>
                                </select>
                                <p>Recommendation: Daily partial + weekly full crawls</p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="tab-security" class="tab-panel">
                        <h3>🔒 Security Settings</h3>
                        <p>Configure security and audit preferences</p>
                        
                        <div class="security-options">
                            <div class="security-option">
                                <label class="checkbox-label">
                                    <input type="checkbox" 
                                           name="oauth_audit_enabled" 
                                           checked="<?php echo get_option('khm_seo_oauth_audit_enabled', true); ?>">
                                    <span class="checkmark"></span>
                                    Enable OAuth audit logging
                                </label>
                                <p>Track all API connections and disconnections for security</p>
                            </div>
                            
                            <div class="security-option">
                                <label class="checkbox-label">
                                    <input type="checkbox" 
                                           name="token_encryption_enabled" 
                                           checked disabled>
                                    <span class="checkmark"></span>
                                    Encrypt stored tokens (always enabled)
                                </label>
                                <p>All OAuth tokens are encrypted using AES-256 encryption</p>
                            </div>
                            
                            <div class="security-option">
                                <label class="checkbox-label">
                                    <input type="checkbox" 
                                           name="ip_restriction_enabled">
                                    <span class="checkmark"></span>
                                    Restrict API access by IP address
                                </label>
                                <p>Only allow API calls from specific IP addresses (advanced)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="tab-notifications" class="tab-panel">
                        <h3>🚨 Alert Notifications</h3>
                        <p>Configure when and how to receive SEO alerts</p>
                        
                        <div class="notification-options">
                            <div class="notification-option">
                                <h4>📧 Email Notifications</h4>
                                <label>
                                    <input type="email" 
                                           name="alert_email" 
                                           placeholder="admin@yoursite.com" 
                                           value="<?php echo esc_attr(get_option('admin_email')); ?>">
                                </label>
                            </div>
                            
                            <div class="notification-option">
                                <h4>🔔 Alert Types</h4>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="alert_ranking_drops" checked>
                                    <span class="checkmark"></span>
                                    Ranking drops (>5 positions)
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="alert_traffic_drops" checked>
                                    <span class="checkmark"></span>
                                    Traffic drops (>30%)
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="alert_cwv_poor">
                                    <span class="checkmark"></span>
                                    Core Web Vitals issues
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="alert_crawl_errors">
                                    <span class="checkmark"></span>
                                    Technical SEO issues
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render completion step
     */
    private function render_complete_step() {
        $connections = $this->oauth_manager->get_connection_status();
        ?>
        <div class="wizard-step" data-step="complete">
            <div class="step-header">
                <h2>🎉 Setup Complete!</h2>
                <p>Your SEO Measurement Platform is ready to start collecting data</p>
            </div>
            
            <div class="completion-summary">
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-icon">📊</div>
                        <h3>Connections</h3>
                        <div class="connection-summary">
                            <div class="connection-item">
                                <span class="connection-name">Google Search Console</span>
                                <span class="connection-status <?php echo isset($connections['gsc']) ? 'connected' : 'not-connected'; ?>">
                                    <?php echo isset($connections['gsc']) ? '✅ Connected' : '❌ Not Connected'; ?>
                                </span>
                            </div>
                            <div class="connection-item">
                                <span class="connection-name">Google Analytics 4</span>
                                <span class="connection-status <?php echo isset($connections['ga4']) ? 'connected' : 'not-connected'; ?>">
                                    <?php echo isset($connections['ga4']) ? '✅ Connected' : '⏭️ Skipped'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon">⚡</div>
                        <h3>Data Collection</h3>
                        <p>Your first data collection will begin within the next hour. Check back soon to see your SEO insights!</p>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon">🎯</div>
                        <h3>What's Next?</h3>
                        <ul>
                            <li>Visit the SEO Dashboard to see your data</li>
                            <li>Configure additional alert preferences</li>
                            <li>Explore the 5-score SEO analysis</li>
                            <li>Set up automated reports</li>
                        </ul>
                    </div>
                </div>
                
                <div class="completion-actions">
                    <a href="<?php echo admin_url('admin.php?page=khm-seo-dashboard'); ?>" class="button button-primary button-large">
                        🚀 Go to SEO Dashboard
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=khm-seo-settings'); ?>" class="button button-large">
                        ⚙️ Advanced Settings
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle wizard step navigation
     */
    public function handle_wizard_step() {
        if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_wizard')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        $step = sanitize_text_field($_POST['step']);
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'save_step':
                $this->save_step_data($step, $_POST['data'] ?? []);
                break;
            case 'next_step':
                $this->go_to_next_step($step);
                break;
            case 'prev_step':
                $this->go_to_prev_step($step);
                break;
            case 'skip_step':
                $this->skip_step($step);
                break;
        }
        
        wp_send_json_success(['current_step' => $this->get_current_step()]);
    }
    
    /**
     * Validate API credentials via AJAX
     */
    public function validate_api_credentials() {
        if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_wizard')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        $client_id = sanitize_text_field($_POST['client_id']);
        $client_secret = sanitize_text_field($_POST['client_secret']);
        
        // Basic validation
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error(['message' => 'Client ID and Secret are required']);
        }
        
        // Test credentials by attempting to get authorization URL
        try {
            update_option("khm_seo_{$provider}_client_id", $client_id);
            update_option("khm_seo_{$provider}_client_secret", $this->oauth_manager->sanitize_client_secret($client_secret));
            
            $auth_url = $this->oauth_manager->get_authorization_url($provider);
            wp_send_json_success(['message' => 'Credentials validated successfully', 'auth_url' => $auth_url]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get API properties via AJAX
     */
    public function get_api_properties() {
        if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_wizard')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        
        try {
            $token = $this->oauth_manager->get_access_token($provider);
            if (!$token) {
                wp_send_json_error(['message' => 'Not connected to ' . $provider]);
            }
            
            // Get properties based on provider
            if ($provider === 'gsc') {
                $properties = $this->get_gsc_properties($token['access_token']);
            } elseif ($provider === 'ga4') {
                $properties = $this->get_ga4_properties($token['access_token']);
            } else {
                wp_send_json_error(['message' => 'Unsupported provider']);
            }
            
            wp_send_json_success(['properties' => $properties]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get current wizard step
     */
    private function get_current_step() {
        return get_option('khm_seo_wizard_current_step', 'welcome');
    }
    
    /**
     * Get completed wizard steps
     */
    private function get_completed_steps() {
        return get_option('khm_seo_wizard_completed_steps', []);
    }
    
    /**
     * Get step index
     */
    private function get_step_index($step) {
        $steps = array_keys(self::WIZARD_STEPS);
        return array_search($step, $steps);
    }
    
    /**
     * Check if step is accessible
     */
    private function is_step_accessible($step) {
        $completed_steps = $this->get_completed_steps();
        $step_index = $this->get_step_index($step);
        
        // First step is always accessible
        if ($step_index === 0) {
            return true;
        }
        
        // Check if previous required steps are completed
        $steps = array_keys(self::WIZARD_STEPS);
        for ($i = 0; $i < $step_index; $i++) {
            $prev_step = $steps[$i];
            if (self::WIZARD_STEPS[$prev_step]['required'] && !in_array($prev_step, $completed_steps)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Save step data
     */
    private function save_step_data($step, $data) {
        update_option("khm_seo_wizard_step_{$step}", $data);
    }
    
    /**
     * Mark step as completed and go to next
     */
    private function go_to_next_step($current_step) {
        $completed_steps = $this->get_completed_steps();
        if (!in_array($current_step, $completed_steps)) {
            $completed_steps[] = $current_step;
            update_option('khm_seo_wizard_completed_steps', $completed_steps);
        }
        
        $steps = array_keys(self::WIZARD_STEPS);
        $current_index = array_search($current_step, $steps);
        $next_index = $current_index + 1;
        
        if ($next_index < count($steps)) {
            update_option('khm_seo_wizard_current_step', $steps[$next_index]);
        }
    }
    
    /**
     * Go to previous step
     */
    private function go_to_prev_step($current_step) {
        $steps = array_keys(self::WIZARD_STEPS);
        $current_index = array_search($current_step, $steps);
        $prev_index = $current_index - 1;
        
        if ($prev_index >= 0) {
            update_option('khm_seo_wizard_current_step', $steps[$prev_index]);
        }
    }
    
    /**
     * Skip step
     */
    private function skip_step($step) {
        $completed_steps = $this->get_completed_steps();
        $completed_steps[] = $step . '_skipped';
        update_option('khm_seo_wizard_completed_steps', $completed_steps);
        
        $this->go_to_next_step($step);
    }
    
    /**
     * Handle redirect after OAuth connection
     */
    public function handle_wizard_redirect() {
        if (isset($_GET['connected'])) {
            // Mark appropriate step as completed
            $provider = sanitize_text_field($_GET['connected']);
            $this->complete_oauth_step($provider);
        }
    }
    
    /**
     * Complete OAuth step
     */
    private function complete_oauth_step($provider) {
        $step = $provider === 'gsc' ? 'gsc_setup' : 'ga4_setup';
        $this->go_to_next_step($step);
    }
    
    /**
     * Get GSC properties from API
     */
    private function get_gsc_properties($access_token) {
        $response = wp_remote_get('https://www.googleapis.com/webmasters/v3/sites', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception('Failed to fetch GSC properties: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new \Exception('GSC API error: ' . $data['error']['message']);
        }
        
        return $data['siteEntry'] ?? [];
    }
    
    /**
     * Get GA4 properties from API
     */
    private function get_ga4_properties($access_token) {
        // Implementation for GA4 properties API call
        // This would use Google Analytics Admin API
        return []; // Placeholder
    }
}