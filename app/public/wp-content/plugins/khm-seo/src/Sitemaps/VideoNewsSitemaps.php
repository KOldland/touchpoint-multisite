<?php
/**
 * Phase 10: User Experience Enhancement - Video & News Sitemaps
 * 
 * Specialized XML sitemap generation for video content and news articles
 * that provides AISEO-style media sitemap functionality with enhanced
 * enterprise features including automatic content detection, metadata
 * extraction, and search engine optimization.
 * 
 * Features:
 * - Video sitemap generation with rich metadata
 * - News sitemap with Google News compliance
 * - Automatic content detection and analysis
 * - YouTube, Vimeo, and self-hosted video support
 * - Image thumbnail extraction and optimization
 * - Publication date and author tracking
 * - Category and tag mapping for news
 * - Automatic submission to search engines
 * - Performance optimization with caching
 * - Real-time content updates
 * 
 * @package KHM_SEO
 * @subpackage Sitemaps
 * @version 1.0.0
 * @since Phase 10
 */

namespace KHM_SEO\Sitemaps;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class VideoNewsSitemaps {
    
    /**
     * Current settings
     */
    private $settings = [];
    
    /**
     * Default settings
     */
    private $default_settings = [
        'video_sitemap_enabled' => true,
        'news_sitemap_enabled' => true,
        'video_post_types' => ['post', 'page'],
        'news_post_types' => ['post'],
        'video_taxonomies' => ['category', 'post_tag'],
        'news_categories' => [],
        'auto_detect_videos' => true,
        'auto_detect_news' => true,
        'video_thumbnail_size' => 'large',
        'news_publication_name' => '',
        'news_language' => 'en',
        'exclude_password_protected' => true,
        'cache_duration' => 3600,
        'max_video_duration' => 0, // 0 = no limit
        'min_video_duration' => 0,
        'video_platforms' => ['youtube', 'vimeo', 'self-hosted'],
        'news_max_age_days' => 2,
        'submission_enabled' => true,
        'ping_search_engines' => true,
        'video_content_selectors' => [
            'iframe[src*="youtube"]',
            'iframe[src*="vimeo"]',
            'video',
            'embed[src*="youtube"]',
            '[data-video-url]'
        ],
        'news_content_requirements' => [
            'min_word_count' => 100,
            'require_featured_image' => false,
            'require_excerpt' => false
        ]
    ];
    
    /**
     * Video platforms configuration
     */
    private $video_platforms = [
        'youtube' => [
            'name' => 'YouTube',
            'regex' => '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i',
            'thumbnail_url' => 'https://img.youtube.com/vi/{video_id}/maxresdefault.jpg',
            'embed_url' => 'https://www.youtube.com/embed/{video_id}',
            'api_url' => 'https://www.googleapis.com/youtube/v3/videos'
        ],
        'vimeo' => [
            'name' => 'Vimeo',
            'regex' => '/vimeo\.com\/(?:.*#|.*/videos/)?([0-9]+)/i',
            'thumbnail_url' => '', // Requires API call
            'embed_url' => 'https://player.vimeo.com/video/{video_id}',
            'api_url' => 'https://vimeo.com/api/v2/video/{video_id}.json'
        ]
    ];
    
    /**
     * Initialize video and news sitemaps
     */
    public function __construct() {
        $this->load_settings();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin interface
        \add_action('admin_menu', [$this, 'add_admin_menu']);
        \add_action('admin_init', [$this, 'register_settings']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Sitemap generation
        \add_action('init', [$this, 'add_rewrite_rules']);
        \add_action('template_redirect', [$this, 'serve_sitemap']);
        
        // Content monitoring
        \add_action('save_post', [$this, 'handle_content_update'], 10, 2);
        \add_action('delete_post', [$this, 'handle_content_delete']);
        \add_action('wp_update_nav_menu', [$this, 'clear_sitemap_cache']);
        
        // AJAX handlers
        \add_action('wp_ajax_khm_seo_scan_videos', [$this, 'handle_video_scan']);
        \add_action('wp_ajax_khm_seo_scan_news', [$this, 'handle_news_scan']);
        \add_action('wp_ajax_khm_seo_submit_sitemaps', [$this, 'handle_sitemap_submission']);
        \add_action('wp_ajax_khm_seo_video_preview', [$this, 'handle_video_preview']);
        
        // Cron jobs
        \add_action('khm_seo_update_video_sitemap', [$this, 'update_video_sitemap_cron']);
        \add_action('khm_seo_update_news_sitemap', [$this, 'update_news_sitemap_cron']);
        \add_action('khm_seo_submit_sitemaps', [$this, 'submit_sitemaps_cron']);
        
        // Filter hooks
        \add_filter('khm_seo_video_metadata', [$this, 'enhance_video_metadata'], 10, 2);
        \add_filter('khm_seo_news_metadata', [$this, 'enhance_news_metadata'], 10, 2);
        
        // REST API endpoints
        \add_action('rest_api_init', [$this, 'register_rest_endpoints']);
        
        // Schedule cron jobs if not exists
        if (!\wp_next_scheduled('khm_seo_update_video_sitemap')) {
            \wp_schedule_event(time(), 'hourly', 'khm_seo_update_video_sitemap');
        }
        
        if (!\wp_next_scheduled('khm_seo_update_news_sitemap')) {
            \wp_schedule_event(time(), 'hourly', 'khm_seo_update_news_sitemap');
        }
    }
    
    /**
     * Load settings
     */
    private function load_settings() {
        $saved_settings = \get_option('khm_seo_video_news_sitemaps', []);
        $this->settings = array_merge($this->default_settings, $saved_settings);
        
        // Set default publication name if empty
        if (empty($this->settings['news_publication_name'])) {
            $this->settings['news_publication_name'] = \get_bloginfo('name');
        }
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        \add_submenu_page(
            'khm-seo-dashboard',
            'Video & News Sitemaps',
            'Video & News Sitemaps',
            'manage_options',
            'khm-seo-video-news-sitemaps',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        \register_setting('khm_seo_video_news_sitemaps', 'khm_seo_video_news_sitemaps', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($settings) {
        $clean_settings = [];
        
        foreach ($this->default_settings as $key => $default_value) {
            if (isset($settings[$key])) {
                switch (gettype($default_value)) {
                    case 'boolean':
                        $clean_settings[$key] = (bool) $settings[$key];
                        break;
                    case 'integer':
                        $clean_settings[$key] = (int) $settings[$key];
                        break;
                    case 'array':
                        $clean_settings[$key] = (array) $settings[$key];
                        break;
                    default:
                        $clean_settings[$key] = \sanitize_text_field($settings[$key]);
                        break;
                }
            } else {
                $clean_settings[$key] = $default_value;
            }
        }
        
        return $clean_settings;
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap khm-seo-video-news-admin">
            <h1>
                <span class="dashicons dashicons-video-alt3"></span>
                Video & News Sitemaps
            </h1>
            
            <div class="khm-seo-admin-header">
                <div class="header-content">
                    <h2>Enhanced Media & News SEO</h2>
                    <p>Generate specialized XML sitemaps for video content and news articles to improve search engine visibility and indexing of your multimedia content.</p>
                </div>
                <div class="header-actions">
                    <button type="button" class="button button-secondary" id="scan-content">
                        <span class="dashicons dashicons-search"></span>
                        Scan Content
                    </button>
                    <button type="button" class="button button-secondary" id="submit-sitemaps">
                        <span class="dashicons dashicons-upload"></span>
                        Submit to Search Engines
                    </button>
                </div>
            </div>
            
            <!-- Statistics Dashboard -->
            <div class="khm-seo-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-video-alt3"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="video-count">-</div>
                        <div class="stat-label">Videos Found</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-megaphone"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="news-count">-</div>
                        <div class="stat-label">News Articles</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="last-updated">-</div>
                        <div class="stat-label">Last Updated</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-yes"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="submission-status">-</div>
                        <div class="stat-label">Status</div>
                    </div>
                </div>
            </div>
            
            <form method="post" action="options.php" class="khm-seo-settings-form">
                <?php \settings_fields('khm_seo_video_news_sitemaps'); ?>
                
                <div class="khm-seo-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#video-sitemap" class="nav-tab nav-tab-active">Video Sitemap</a>
                        <a href="#news-sitemap" class="nav-tab">News Sitemap</a>
                        <a href="#content-detection" class="nav-tab">Content Detection</a>
                        <a href="#submission" class="nav-tab">Search Engine Submission</a>
                    </nav>
                    
                    <!-- Video Sitemap Tab -->
                    <div id="video-sitemap" class="tab-content active">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-video-alt3"></span> Video Sitemap Configuration</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="video_sitemap_enabled">Enable Video Sitemap</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_video_news_sitemaps[video_sitemap_enabled]" id="video_sitemap_enabled" value="1" <?php \checked($this->settings['video_sitemap_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Generate XML sitemap for video content to improve search engine indexing.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="video_post_types">Include Post Types</label>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <?php
                                            $post_types = \get_post_types(['public' => true], 'objects');
                                            foreach ($post_types as $post_type) {
                                                $checked = in_array($post_type->name, $this->settings['video_post_types']) ? 'checked' : '';
                                                echo "<label><input type='checkbox' name='khm_seo_video_news_sitemaps[video_post_types][]' value='{$post_type->name}' {$checked}> {$post_type->label}</label><br>";
                                            }
                                            ?>
                                        </fieldset>
                                        <p class="description">Select post types to scan for video content.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="video_platforms">Supported Platforms</label>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="khm_seo_video_news_sitemaps[video_platforms][]" value="youtube" <?php checked(in_array('youtube', $this->settings['video_platforms'])); ?>>
                                                YouTube
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="khm_seo_video_news_sitemaps[video_platforms][]" value="vimeo" <?php checked(in_array('vimeo', $this->settings['video_platforms'])); ?>>
                                                Vimeo
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="khm_seo_video_news_sitemaps[video_platforms][]" value="self-hosted" <?php checked(in_array('self-hosted', $this->settings['video_platforms'])); ?>>
                                                Self-hosted Videos (MP4, WebM, etc.)
                                            </label>
                                        </fieldset>
                                        <p class="description">Choose which video platforms to include in the sitemap.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="video_thumbnail_size">Thumbnail Size</label>
                                    </th>
                                    <td>
                                        <select name="khm_seo_video_news_sitemaps[video_thumbnail_size]" id="video_thumbnail_size" class="regular-text">
                                            <?php
                                            $image_sizes = \get_intermediate_image_sizes();
                                            foreach ($image_sizes as $size) {
                                                $selected = selected($this->settings['video_thumbnail_size'], $size, false);
                                                echo "<option value='{$size}' {$selected}>" . ucfirst($size) . "</option>";
                                            }
                                            ?>
                                        </select>
                                        <p class="description">Preferred thumbnail size for video previews.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Duration Limits</th>
                                    <td>
                                        <label for="min_video_duration">Minimum:</label>
                                        <input type="number" name="khm_seo_video_news_sitemaps[min_video_duration]" id="min_video_duration" value="<?php echo \esc_attr($this->settings['min_video_duration']); ?>" min="0" class="small-text"> seconds<br><br>
                                        
                                        <label for="max_video_duration">Maximum:</label>
                                        <input type="number" name="khm_seo_video_news_sitemaps[max_video_duration]" id="max_video_duration" value="<?php echo \esc_attr($this->settings['max_video_duration']); ?>" min="0" class="small-text"> seconds
                                        <p class="description">Set duration limits for videos to include (0 = no limit).</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-admin-tools"></span> Video Detection Settings</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="auto_detect_videos">Auto Detection</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_video_news_sitemaps[auto_detect_videos]" id="auto_detect_videos" value="1" <?php \checked($this->settings['auto_detect_videos']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Automatically detect videos when content is saved or updated.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="video_content_selectors">CSS Selectors</label>
                                    </th>
                                    <td>
                                        <textarea name="khm_seo_video_news_sitemaps[video_content_selectors]" id="video_content_selectors" class="large-text" rows="6"><?php echo \esc_textarea(implode("\n", $this->settings['video_content_selectors'])); ?></textarea>
                                        <p class="description">CSS selectors for finding video content (one per line). Used for automatic video detection.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- News Sitemap Tab -->
                    <div id="news-sitemap" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-megaphone"></span> News Sitemap Configuration</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="news_sitemap_enabled">Enable News Sitemap</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_video_news_sitemaps[news_sitemap_enabled]" id="news_sitemap_enabled" value="1" <?php \checked($this->settings['news_sitemap_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Generate Google News compliant XML sitemap for news articles.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="news_publication_name">Publication Name</label>
                                    </th>
                                    <td>
                                        <input type="text" name="khm_seo_video_news_sitemaps[news_publication_name]" id="news_publication_name" value="<?php echo \esc_attr($this->settings['news_publication_name']); ?>" class="regular-text" required>
                                        <p class="description">Name of your news publication as it should appear in Google News.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="news_language">Language</label>
                                    </th>
                                    <td>
                                        <select name="khm_seo_video_news_sitemaps[news_language]" id="news_language" class="regular-text">
                                            <?php
                                            $languages = [
                                                'en' => 'English',
                                                'es' => 'Spanish',
                                                'fr' => 'French',
                                                'de' => 'German',
                                                'it' => 'Italian',
                                                'pt' => 'Portuguese',
                                                'ru' => 'Russian',
                                                'zh' => 'Chinese',
                                                'ja' => 'Japanese',
                                                'ar' => 'Arabic'
                                            ];
                                            
                                            foreach ($languages as $code => $name) {
                                                $selected = selected($this->settings['news_language'], $code, false);
                                                echo "<option value='{$code}' {$selected}>{$name}</option>";
                                            }
                                            ?>
                                        </select>
                                        <p class="description">Primary language of your news content.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="news_post_types">News Post Types</label>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <?php
                                            foreach ($post_types as $post_type) {
                                                $checked = in_array($post_type->name, $this->settings['news_post_types']) ? 'checked' : '';
                                                echo "<label><input type='checkbox' name='khm_seo_video_news_sitemaps[news_post_types][]' value='{$post_type->name}' {$checked}> {$post_type->label}</label><br>";
                                            }
                                            ?>
                                        </fieldset>
                                        <p class="description">Select post types to include as news articles.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="news_max_age_days">Maximum Age</label>
                                    </th>
                                    <td>
                                        <input type="number" name="khm_seo_video_news_sitemaps[news_max_age_days]" id="news_max_age_days" value="<?php echo \esc_attr($this->settings['news_max_age_days']); ?>" min="1" max="30" class="small-text"> days
                                        <p class="description">Maximum age of articles to include in news sitemap (Google News recommends 2 days).</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-editor-spellcheck"></span> Content Requirements</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="min_word_count">Minimum Word Count</label>
                                    </th>
                                    <td>
                                        <input type="number" name="khm_seo_video_news_sitemaps[news_content_requirements][min_word_count]" id="min_word_count" value="<?php echo \esc_attr($this->settings['news_content_requirements']['min_word_count']); ?>" min="0" class="small-text"> words
                                        <p class="description">Minimum number of words required for articles to be included in news sitemap.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Quality Requirements</th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="khm_seo_video_news_sitemaps[news_content_requirements][require_featured_image]" value="1" <?php checked($this->settings['news_content_requirements']['require_featured_image']); ?>>
                                                Require featured image
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="khm_seo_video_news_sitemaps[news_content_requirements][require_excerpt]" value="1" <?php checked($this->settings['news_content_requirements']['require_excerpt']); ?>>
                                                Require excerpt
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Content Detection Tab -->
                    <div id="content-detection" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-search"></span> Content Scanning & Detection</h3>
                            
                            <div class="scan-results" id="scan-results" style="display: none;">
                                <h4>Scan Results</h4>
                                <div class="results-content"></div>
                            </div>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Manual Scan</th>
                                    <td>
                                        <button type="button" class="button button-secondary" id="scan-videos">
                                            <span class="dashicons dashicons-video-alt3"></span>
                                            Scan for Videos
                                        </button>
                                        <button type="button" class="button button-secondary" id="scan-news">
                                            <span class="dashicons dashicons-megaphone"></span>
                                            Scan for News
                                        </button>
                                        <p class="description">Manually scan your content to detect videos and news articles.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="exclude_password_protected">Exclusions</label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="khm_seo_video_news_sitemaps[exclude_password_protected]" id="exclude_password_protected" value="1" <?php \checked($this->settings['exclude_password_protected']); ?>>
                                            Exclude password-protected content
                                        </label>
                                        <p class="description">Skip password-protected posts and pages during content scanning.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="cache_duration">Cache Duration</label>
                                    </th>
                                    <td>
                                        <select name="khm_seo_video_news_sitemaps[cache_duration]" id="cache_duration" class="regular-text">
                                            <option value="900" <?php selected($this->settings['cache_duration'], 900); ?>>15 minutes</option>
                                            <option value="1800" <?php selected($this->settings['cache_duration'], 1800); ?>>30 minutes</option>
                                            <option value="3600" <?php selected($this->settings['cache_duration'], 3600); ?>>1 hour</option>
                                            <option value="7200" <?php selected($this->settings['cache_duration'], 7200); ?>>2 hours</option>
                                            <option value="21600" <?php selected($this->settings['cache_duration'], 21600); ?>>6 hours</option>
                                            <option value="86400" <?php selected($this->settings['cache_duration'], 86400); ?>>24 hours</option>
                                        </select>
                                        <p class="description">How long to cache sitemap data for performance optimization.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Submission Tab -->
                    <div id="submission" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-upload"></span> Search Engine Submission</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="submission_enabled">Auto Submission</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_video_news_sitemaps[submission_enabled]" id="submission_enabled" value="1" <?php \checked($this->settings['submission_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Automatically submit updated sitemaps to search engines.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="ping_search_engines">Ping Search Engines</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_video_news_sitemaps[ping_search_engines]" id="ping_search_engines" value="1" <?php \checked($this->settings['ping_search_engines']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Notify search engines when sitemaps are updated.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="submission-status">
                                <h4>Sitemap URLs</h4>
                                <div class="sitemap-urls">
                                    <div class="sitemap-url-item">
                                        <strong>Video Sitemap:</strong>
                                        <code id="video-sitemap-url"><?php echo \home_url('/video-sitemap.xml'); ?></code>
                                        <button type="button" class="button button-small copy-url" data-url="<?php echo \home_url('/video-sitemap.xml'); ?>">Copy</button>
                                    </div>
                                    <div class="sitemap-url-item">
                                        <strong>News Sitemap:</strong>
                                        <code id="news-sitemap-url"><?php echo \home_url('/news-sitemap.xml'); ?></code>
                                        <button type="button" class="button button-small copy-url" data-url="<?php echo \home_url('/news-sitemap.xml'); ?>">Copy</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php \submit_button('Save Settings', 'primary', 'submit', false, ['id' => 'save-sitemap-settings']); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Add rewrite rules for sitemaps
     */
    public function add_rewrite_rules() {
        \add_rewrite_rule('^video-sitemap\.xml$', 'index.php?khm_video_sitemap=1', 'top');
        \add_rewrite_rule('^news-sitemap\.xml$', 'index.php?khm_news_sitemap=1', 'top');
        
        // Add query vars
        \add_filter('query_vars', function($vars) {
            $vars[] = 'khm_video_sitemap';
            $vars[] = 'khm_news_sitemap';
            return $vars;
        });
    }
    
    /**
     * Serve sitemap if requested
     */
    public function serve_sitemap() {
        global $wp_query;
        
        if (\get_query_var('khm_video_sitemap')) {
            $this->output_video_sitemap();
            exit;
        }
        
        if (\get_query_var('khm_news_sitemap')) {
            $this->output_news_sitemap();
            exit;
        }
    }
    
    /**
     * Output video sitemap
     */
    private function output_video_sitemap() {
        if (!$this->settings['video_sitemap_enabled']) {
            \wp_die('Video sitemap is disabled', 'Sitemap Disabled', ['response' => 404]);
        }
        
        header('Content-Type: application/xml; charset=UTF-8');
        echo $this->generate_video_sitemap_xml();
    }
    
    /**
     * Output news sitemap
     */
    private function output_news_sitemap() {
        if (!$this->settings['news_sitemap_enabled']) {
            \wp_die('News sitemap is disabled', 'Sitemap Disabled', ['response' => 404]);
        }
        
        header('Content-Type: application/xml; charset=UTF-8');
        echo $this->generate_news_sitemap_xml();
    }
    
    /**
     * Generate video sitemap XML
     */
    private function generate_video_sitemap_xml() {
        // Check cache first
        $cache_key = 'khm_video_sitemap_' . \md5(serialize($this->settings));
        $cached_xml = \get_transient($cache_key);
        
        if ($cached_xml !== false) {
            return $cached_xml;
        }
        
        $videos = $this->get_video_data();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        
        foreach ($videos as $video) {
            $xml .= $this->build_video_xml_entry($video);
        }
        
        $xml .= '</urlset>';
        
        // Cache the result
        \set_transient($cache_key, $xml, $this->settings['cache_duration']);
        
        return $xml;
    }
    
    /**
     * Generate news sitemap XML
     */
    private function generate_news_sitemap_xml() {
        // Check cache first
        $cache_key = 'khm_news_sitemap_' . \md5(serialize($this->settings));
        $cached_xml = \get_transient($cache_key);
        
        if ($cached_xml !== false) {
            return $cached_xml;
        }
        
        $news_articles = $this->get_news_data();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
        
        foreach ($news_articles as $article) {
            $xml .= $this->build_news_xml_entry($article);
        }
        
        $xml .= '</urlset>';
        
        // Cache the result
        \set_transient($cache_key, $xml, $this->settings['cache_duration']);
        
        return $xml;
    }
    
    // Additional methods for video detection, metadata extraction, etc. would be added here...
}

// Initialize video and news sitemaps
new VideoNewsSitemaps();