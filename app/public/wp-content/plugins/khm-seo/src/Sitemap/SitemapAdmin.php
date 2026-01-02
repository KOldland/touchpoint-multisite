<?php
/**
 * Sitemap Admin Interface - Admin page for sitemap configuration and management
 * 
 * Provides admin interface for sitemap settings, generation controls,
 * statistics display, and search engine ping management.
 * 
 * @package KHM_SEO\Sitemap
 * @since 2.1.0
 */

namespace KHM_SEO\Sitemap;

/**
 * Sitemap Admin Class
 */
class SitemapAdmin {
    /**
     * @var SitemapManager Sitemap manager instance
     */
    private $manager;

    /**
     * @var array Page tabs
     */
    private $tabs;

    /**
     * Constructor
     *
     * @param SitemapManager $manager Sitemap manager
     */
    public function __construct(SitemapManager $manager) {
        $this->manager = $manager;
        $this->init_tabs();
        $this->init_hooks();
    }

    /**
     * Initialize admin tabs
     */
    private function init_tabs() {
        $this->tabs = [
            'general' => [
                'title' => 'General Settings',
                'icon' => 'admin-generic',
                'description' => 'Configure basic sitemap settings'
            ],
            'content' => [
                'title' => 'Content Settings',
                'icon' => 'admin-page',
                'description' => 'Select which content to include'
            ],
            'advanced' => [
                'title' => 'Advanced Settings',
                'icon' => 'admin-tools',
                'description' => 'Advanced configuration options'
            ],
            'search-engines' => [
                'title' => 'Search Engines',
                'icon' => 'admin-site',
                'description' => 'Search engine ping settings'
            ],
            'statistics' => [
                'title' => 'Statistics',
                'icon' => 'chart-bar',
                'description' => 'Sitemap statistics and analytics'
            ],
            'tools' => [
                'title' => 'Tools',
                'icon' => 'admin-tools',
                'description' => 'Sitemap management tools'
            ]
        ];
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_khm_seo_regenerate_sitemap', [$this, 'ajax_regenerate_sitemap']);
        add_action('wp_ajax_khm_seo_ping_search_engines', [$this, 'ajax_ping_search_engines']);
        add_action('wp_ajax_khm_seo_validate_sitemap', [$this, 'ajax_validate_sitemap']);
        add_action('wp_ajax_khm_seo_test_sitemap', [$this, 'ajax_test_sitemap']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-seo',
            'XML Sitemaps',
            'Sitemaps',
            'manage_options',
            'khm-seo-sitemaps',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting('khm_seo_sitemap_general', 'khm_seo_sitemap_settings');
        
        // Content settings  
        register_setting('khm_seo_sitemap_content', 'khm_seo_sitemap_content_settings');
        
        // Advanced settings
        register_setting('khm_seo_sitemap_advanced', 'khm_seo_sitemap_advanced_settings');
        
        // Search engine settings
        register_setting('khm_seo_sitemap_search_engines', 'khm_seo_search_engine_settings');
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (!str_contains($hook, 'khm-seo-sitemaps')) {
            return;
        }

        wp_enqueue_style(
            'khm-seo-sitemap-admin',
            plugins_url('assets/css/sitemap-admin.css', KHM_SEO_PLUGIN_FILE),
            [],
            KHM_SEO_VERSION
        );

        wp_enqueue_script(
            'khm-seo-sitemap-admin',
            plugins_url('assets/js/sitemap-admin.js', KHM_SEO_PLUGIN_FILE),
            ['jquery'],
            KHM_SEO_VERSION,
            true
        );

        wp_localize_script('khm-seo-sitemap-admin', 'khmSeoSitemapAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_seo_sitemap_admin'),
            'strings' => [
                'regenerating' => 'Regenerating sitemap...',
                'pinging' => 'Pinging search engines...',
                'testing' => 'Testing sitemap accessibility...',
                'validating' => 'Validating sitemap...',
                'success' => 'Success!',
                'error' => 'Error occurred',
                'confirm_regenerate' => 'Are you sure you want to regenerate the sitemap?'
            ]
        ]);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        if (!array_key_exists($active_tab, $this->tabs)) {
            $active_tab = 'general';
        }
        ?>
        <div class="wrap khm-seo-sitemap-admin">
            <h1>
                <span class="dashicons dashicons-networking"></span>
                XML Sitemaps
            </h1>
            
            <div class="sitemap-header">
                <div class="sitemap-status">
                    <?php echo $this->render_sitemap_status(); ?>
                </div>
                <div class="sitemap-actions">
                    <?php echo $this->render_quick_actions(); ?>
                </div>
            </div>

            <nav class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $tab_key => $tab): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=khm-seo-sitemaps&tab=' . $tab_key)); ?>" 
                       class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($tab['icon']); ?>"></span>
                        <?php echo esc_html($tab['title']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="tab-content">
                <?php 
                switch ($active_tab) {
                    case 'general':
                        $this->render_general_tab();
                        break;
                    case 'content':
                        $this->render_content_tab();
                        break;
                    case 'advanced':
                        $this->render_advanced_tab();
                        break;
                    case 'search-engines':
                        $this->render_search_engines_tab();
                        break;
                    case 'statistics':
                        $this->render_statistics_tab();
                        break;
                    case 'tools':
                        $this->render_tools_tab();
                        break;
                }
                ?>
            </div>
        </div>

        <div id="sitemap-modal" class="sitemap-modal" style="display: none;">
            <div class="sitemap-modal-content">
                <span class="sitemap-modal-close">&times;</span>
                <div class="sitemap-modal-body"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render sitemap status
     */
    private function render_sitemap_status() {
        $sitemap_url = home_url('/sitemap.xml');
        $test_result = $this->manager->test_sitemap_accessibility();
        $is_accessible = $test_result['index']['status'] === 'success';
        
        ob_start();
        ?>
        <div class="sitemap-status-card <?php echo $is_accessible ? 'status-success' : 'status-error'; ?>">
            <div class="status-icon">
                <span class="dashicons dashicons-<?php echo $is_accessible ? 'yes-alt' : 'warning'; ?>"></span>
            </div>
            <div class="status-info">
                <h3>Sitemap Status</h3>
                <p>
                    <?php if ($is_accessible): ?>
                        Your XML sitemap is accessible and working properly.
                    <?php else: ?>
                        Your XML sitemap is not accessible or has issues.
                    <?php endif; ?>
                </p>
                <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" class="button button-small">
                    View Sitemap
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render quick actions
     */
    private function render_quick_actions() {
        ob_start();
        ?>
        <div class="quick-actions">
            <button type="button" class="button button-primary" id="regenerate-sitemap">
                <span class="dashicons dashicons-update"></span>
                Regenerate Sitemap
            </button>
            <button type="button" class="button" id="ping-search-engines">
                <span class="dashicons dashicons-admin-site"></span>
                Ping Search Engines
            </button>
            <button type="button" class="button" id="test-sitemap">
                <span class="dashicons dashicons-admin-tools"></span>
                Test Accessibility
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render general settings tab
     */
    private function render_general_tab() {
        $settings = wp_parse_args(get_option('khm_seo_sitemap_settings', []), [
            'enable_sitemap' => true,
            'auto_regenerate' => true,
            'regenerate_on_post_save' => true,
            'regenerate_on_term_save' => true,
            'max_urls_per_sitemap' => 50000
        ]);
        ?>
        <form method="post" action="options.php" class="sitemap-form">
            <?php settings_fields('khm_seo_sitemap_general'); ?>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Enable XML Sitemap</th>
                    <td>
                        <label>
                            <input type="checkbox" name="khm_seo_sitemap_settings[enable_sitemap]" 
                                   value="1" <?php checked($settings['enable_sitemap']); ?>>
                            Generate XML sitemap
                        </label>
                        <p class="description">Enable or disable XML sitemap generation.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Automatic Regeneration</th>
                    <td>
                        <label>
                            <input type="checkbox" name="khm_seo_sitemap_settings[auto_regenerate]" 
                                   value="1" <?php checked($settings['auto_regenerate']); ?>>
                            Automatically regenerate sitemap
                        </label>
                        <p class="description">Automatically regenerate sitemap when content changes.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Regeneration Triggers</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_settings[regenerate_on_post_save]" 
                                       value="1" <?php checked($settings['regenerate_on_post_save']); ?>>
                                Regenerate when posts are saved or deleted
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_settings[regenerate_on_term_save]" 
                                       value="1" <?php checked($settings['regenerate_on_term_save']); ?>>
                                Regenerate when terms are saved or deleted
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">URLs Per Sitemap</th>
                    <td>
                        <input type="number" name="khm_seo_sitemap_settings[max_urls_per_sitemap]" 
                               value="<?php echo esc_attr($settings['max_urls_per_sitemap']); ?>" 
                               min="1000" max="50000" step="1000" class="regular-text">
                        <p class="description">Maximum number of URLs per sitemap file (recommended: 50,000).</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render content settings tab
     */
    private function render_content_tab() {
        $settings = wp_parse_args(get_option('khm_seo_sitemap_content_settings', []), [
            'include_posts' => true,
            'include_pages' => true,
            'include_categories' => true,
            'include_tags' => true,
            'include_authors' => false,
            'include_media' => false,
            'custom_post_types' => [],
            'custom_taxonomies' => []
        ]);
        ?>
        <form method="post" action="options.php" class="sitemap-form">
            <?php settings_fields('khm_seo_sitemap_content'); ?>
            
            <h3>Built-in Content Types</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Post Types</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_content_settings[include_posts]" 
                                       value="1" <?php checked($settings['include_posts']); ?>>
                                Posts
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_content_settings[include_pages]" 
                                       value="1" <?php checked($settings['include_pages']); ?>>
                                Pages
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_content_settings[include_media]" 
                                       value="1" <?php checked($settings['include_media']); ?>>
                                Media (Images)
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Taxonomies</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_content_settings[include_categories]" 
                                       value="1" <?php checked($settings['include_categories']); ?>>
                                Categories
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_content_settings[include_tags]" 
                                       value="1" <?php checked($settings['include_tags']); ?>>
                                Tags
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Other Content</th>
                    <td>
                        <label>
                            <input type="checkbox" name="khm_seo_sitemap_content_settings[include_authors]" 
                                   value="1" <?php checked($settings['include_authors']); ?>>
                            Author pages
                        </label>
                        <p class="description">Include author archive pages in sitemap.</p>
                    </td>
                </tr>
            </table>
            
            <h3>Custom Post Types</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <td colspan="2">
                        <?php 
                        $post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
                        if (!empty($post_types)): ?>
                            <fieldset>
                                <?php foreach ($post_types as $post_type): ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="khm_seo_sitemap_content_settings[custom_post_types][<?php echo esc_attr($post_type->name); ?>]" 
                                               value="1" <?php checked(!empty($settings['custom_post_types'][$post_type->name])); ?>>
                                        <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php else: ?>
                            <p>No custom post types found.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <h3>Custom Taxonomies</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <td colspan="2">
                        <?php 
                        $taxonomies = get_taxonomies(['public' => true, '_builtin' => false], 'objects');
                        if (!empty($taxonomies)): ?>
                            <fieldset>
                                <?php foreach ($taxonomies as $taxonomy): ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="khm_seo_sitemap_content_settings[custom_taxonomies][<?php echo esc_attr($taxonomy->name); ?>]" 
                                               value="1" <?php checked(!empty($settings['custom_taxonomies'][$taxonomy->name])); ?>>
                                        <?php echo esc_html($taxonomy->label); ?> (<?php echo esc_html($taxonomy->name); ?>)
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php else: ?>
                            <p>No custom taxonomies found.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render advanced settings tab
     */
    private function render_advanced_tab() {
        $settings = wp_parse_args(get_option('khm_seo_sitemap_advanced_settings', []), [
            'gzip_compression' => true,
            'cache_control' => true,
            'max_age' => 86400,
            'exclude_empty_terms' => true,
            'exclude_noindex' => true,
            'image_sitemap' => true,
            'video_sitemap' => false,
            'news_sitemap' => false
        ]);
        ?>
        <form method="post" action="options.php" class="sitemap-form">
            <?php settings_fields('khm_seo_sitemap_advanced'); ?>
            
            <h3>Performance Options</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Compression</th>
                    <td>
                        <label>
                            <input type="checkbox" name="khm_seo_sitemap_advanced_settings[gzip_compression]" 
                                   value="1" <?php checked($settings['gzip_compression']); ?>>
                            Enable GZIP compression
                        </label>
                        <p class="description">Compress sitemap files to reduce bandwidth usage.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Cache Control</th>
                    <td>
                        <label>
                            <input type="checkbox" name="khm_seo_sitemap_advanced_settings[cache_control]" 
                                   value="1" <?php checked($settings['cache_control']); ?>>
                            Enable cache control headers
                        </label>
                        <p class="description">Add cache control headers to sitemap responses.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Cache Age</th>
                    <td>
                        <select name="khm_seo_sitemap_advanced_settings[max_age]">
                            <option value="3600" <?php selected($settings['max_age'], 3600); ?>>1 Hour</option>
                            <option value="21600" <?php selected($settings['max_age'], 21600); ?>>6 Hours</option>
                            <option value="86400" <?php selected($settings['max_age'], 86400); ?>>24 Hours</option>
                            <option value="604800" <?php selected($settings['max_age'], 604800); ?>>1 Week</option>
                        </select>
                        <p class="description">How long browsers and search engines should cache sitemap files.</p>
                    </td>
                </tr>
            </table>
            
            <h3>Content Filtering</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Exclude Options</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_advanced_settings[exclude_empty_terms]" 
                                       value="1" <?php checked($settings['exclude_empty_terms']); ?>>
                                Exclude empty taxonomy terms
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_advanced_settings[exclude_noindex]" 
                                       value="1" <?php checked($settings['exclude_noindex']); ?>>
                                Exclude content marked as noindex
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <h3>Specialized Sitemaps</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Additional Sitemaps</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_advanced_settings[image_sitemap]" 
                                       value="1" <?php checked($settings['image_sitemap']); ?>>
                                Generate image sitemap
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_advanced_settings[video_sitemap]" 
                                       value="1" <?php checked($settings['video_sitemap']); ?>>
                                Generate video sitemap
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_sitemap_advanced_settings[news_sitemap]" 
                                       value="1" <?php checked($settings['news_sitemap']); ?>>
                                Generate news sitemap
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render search engines tab
     */
    private function render_search_engines_tab() {
        $settings = wp_parse_args(get_option('khm_seo_search_engine_settings', []), [
            'ping_search_engines' => true,
            'google' => true,
            'bing' => true,
            'yandex' => false,
            'baidu' => false
        ]);
        
        $ping_log = get_option('khm_seo_ping_log', []);
        ?>
        <form method="post" action="options.php" class="sitemap-form">
            <?php settings_fields('khm_seo_sitemap_search_engines'); ?>
            
            <h3>Search Engine Pinging</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Enable Pinging</th>
                    <td>
                        <label>
                            <input type="checkbox" name="khm_seo_search_engine_settings[ping_search_engines]" 
                                   value="1" <?php checked($settings['ping_search_engines']); ?>>
                            Automatically ping search engines when sitemap is updated
                        </label>
                        <p class="description">Notify search engines when your sitemap is regenerated.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Search Engines</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="khm_seo_search_engine_settings[google]" 
                                       value="1" <?php checked($settings['google']); ?>>
                                Google
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_search_engine_settings[bing]" 
                                       value="1" <?php checked($settings['bing']); ?>>
                                Bing
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_search_engine_settings[yandex]" 
                                       value="1" <?php checked($settings['yandex']); ?>>
                                Yandex
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_search_engine_settings[baidu]" 
                                       value="1" <?php checked($settings['baidu']); ?>>
                                Baidu
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <?php if (!empty($ping_log)): ?>
        <h3>Recent Ping History</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Search Engine</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Response Code</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse(array_slice($ping_log, -10)) as $entry): ?>
                <tr>
                    <td><?php echo esc_html(ucfirst($entry['engine'])); ?></td>
                    <td><?php echo esc_html(date('Y-m-d H:i:s', $entry['timestamp'])); ?></td>
                    <td>
                        <span class="status-<?php echo $entry['success'] ? 'success' : 'error'; ?>">
                            <?php echo $entry['success'] ? 'Success' : 'Failed'; ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($entry['response_code'] ?: 'N/A'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }

    /**
     * Render statistics tab
     */
    private function render_statistics_tab() {
        $stats = $this->manager->get_sitemap_statistics();
        ?>
        <div class="sitemap-statistics">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total URLs</h3>
                    <div class="stat-number"><?php echo number_format($stats['total_urls']); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Last Generated</h3>
                    <div class="stat-text">
                        <?php 
                        if ($stats['last_generated']) {
                            echo esc_html(human_time_diff($stats['last_generated']) . ' ago');
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Post Types</h3>
                    <div class="stat-number"><?php echo count($stats['post_types']); ?></div>
                </div>
            </div>
            
            <h3>Content Breakdown</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Content Type</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['post_types'] as $type => $data): ?>
                    <tr>
                        <td><?php echo esc_html($data['label']); ?></td>
                        <td><?php echo number_format($data['count']); ?></td>
                        <td>
                            <?php 
                            $percentage = $stats['total_urls'] > 0 ? ($data['count'] / $stats['total_urls']) * 100 : 0;
                            echo number_format($percentage, 1) . '%';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render tools tab
     */
    private function render_tools_tab() {
        ?>
        <div class="sitemap-tools">
            <h3>Sitemap Management Tools</h3>
            
            <div class="tool-section">
                <h4>Regeneration</h4>
                <p>Manually regenerate your XML sitemap files.</p>
                <button type="button" class="button" id="force-regenerate">
                    <span class="dashicons dashicons-update"></span>
                    Force Regenerate All Sitemaps
                </button>
            </div>
            
            <div class="tool-section">
                <h4>Validation</h4>
                <p>Validate your sitemap structure and content.</p>
                <button type="button" class="button" id="validate-sitemap">
                    <span class="dashicons dashicons-yes"></span>
                    Validate Sitemap
                </button>
            </div>
            
            <div class="tool-section">
                <h4>Testing</h4>
                <p>Test sitemap accessibility and response headers.</p>
                <button type="button" class="button" id="test-sitemap-access">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Test Sitemap Accessibility
                </button>
            </div>
            
            <div class="tool-section">
                <h4>Cache Management</h4>
                <p>Clear sitemap cache to force regeneration.</p>
                <button type="button" class="button" id="clear-sitemap-cache">
                    <span class="dashicons dashicons-trash"></span>
                    Clear Sitemap Cache
                </button>
            </div>
        </div>
        
        <div id="tool-results" class="tool-results" style="display: none;">
            <h4>Results</h4>
            <div class="results-content"></div>
        </div>
        <?php
    }

    /**
     * AJAX: Regenerate sitemap
     */
    public function ajax_regenerate_sitemap() {
        check_ajax_referer('khm_seo_sitemap_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $this->manager->regenerate_sitemap();
        
        wp_send_json_success([
            'message' => 'Sitemap regeneration started successfully.'
        ]);
    }

    /**
     * AJAX: Ping search engines
     */
    public function ajax_ping_search_engines() {
        check_ajax_referer('khm_seo_sitemap_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $this->manager->ping_search_engines();
        
        wp_send_json_success([
            'message' => 'Search engines pinged successfully.'
        ]);
    }

    /**
     * AJAX: Test sitemap accessibility
     */
    public function ajax_test_sitemap() {
        check_ajax_referer('khm_seo_sitemap_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $results = $this->manager->test_sitemap_accessibility();
        
        wp_send_json_success([
            'message' => 'Sitemap accessibility test completed.',
            'results' => $results
        ]);
    }
}
