<?php
declare(strict_types=1);

namespace KHM_SEO\Editor;

/**
 * EditorManager - Coordinates real-time SEO analysis in WordPress editors
 * 
 * This class handles the integration of live SEO analysis into WordPress
 * post/page editors, providing real-time scoring, suggestions, and previews
 * as users create and edit content.
 * 
 * @package KHM_SEO\Editor
 * @since 2.0.0
 */
class EditorManager
{
    /**
     * @var LiveAnalyzer Real-time content analyzer
     */
    private LiveAnalyzer $live_analyzer;

    /**
     * @var ScoreDisplay SEO score display component
     */
    private ScoreDisplay $score_display;

    /**
     * @var SuggestionEngine Optimization recommendation engine
     */
    private SuggestionEngine $suggestion_engine;

    /**
     * @var MetaPreview SERP preview generator
     */
    private MetaPreview $meta_preview;

    /**
     * @var array Supported editor types
     */
    private array $supported_editors = [
        'classic',
        'gutenberg',
        'elementor'
    ];

    /**
     * @var array Configuration settings
     */
    private array $config;

    /**
     * Initialize the Editor Manager
     */
    public function __construct()
    {
        $this->config = $this->get_default_config();
        $this->init_components();
    }

    /**
     * Initialize all editor components
     *
     * @return void
     */
    private function init_components(): void
    {
        $this->live_analyzer = new LiveAnalyzer();
        $this->score_display = new ScoreDisplay();
        $this->suggestion_engine = new SuggestionEngine();
        $this->meta_preview = new MetaPreview();
    }

    /**
     * Initialize WordPress hooks and filters
     *
     * @return void
     */
    public function init(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_editor_assets']);
        add_action('wp_ajax_khm_seo_live_analysis', [$this, 'handle_live_analysis']);
        add_action('wp_ajax_khm_seo_meta_preview', [$this, 'handle_meta_preview']);
        add_action('add_meta_boxes', [$this, 'add_seo_meta_box']);
        add_filter('script_loader_tag', [$this, 'add_script_attributes'], 10, 3);
        
        // Gutenberg integration
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_gutenberg_assets']);
        
        // Classic editor integration
        add_action('admin_head', [$this, 'add_classic_editor_styles']);
    }

    /**
     * Enqueue editor-specific JavaScript and CSS assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_editor_assets(string $hook): void
    {
        // Only load on post edit screens
        if (!in_array($hook, ['post.php', 'post-new.php', 'page.php', 'page-new.php'])) {
            return;
        }

        $current_editor = $this->detect_current_editor();
        
        if (!in_array($current_editor, $this->supported_editors)) {
            return;
        }

        $version = KHM_SEO_VERSION ?? '2.0.0';
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

        // Core editor JavaScript
        wp_enqueue_script(
            'khm-seo-live-analyzer',
            $plugin_url . 'assets/js/editor/live-analyzer.js',
            ['jquery', 'wp-api'],
            $version,
            true
        );

        // Score display component
        wp_enqueue_script(
            'khm-seo-score-display',
            $plugin_url . 'assets/js/editor/seo-score-display.js',
            ['khm-seo-live-analyzer'],
            $version,
            true
        );

        // Suggestion panel
        wp_enqueue_script(
            'khm-seo-suggestions',
            $plugin_url . 'assets/js/editor/suggestion-panel.js',
            ['khm-seo-live-analyzer'],
            $version,
            true
        );

        // Meta preview
        wp_enqueue_script(
            'khm-seo-meta-preview',
            $plugin_url . 'assets/js/editor/meta-preview.js',
            ['khm-seo-live-analyzer'],
            $version,
            true
        );

        // Main editor integration
        wp_enqueue_script(
            'khm-seo-editor-integration',
            $plugin_url . 'assets/js/editor/editor-integration.js',
            [
                'khm-seo-live-analyzer',
                'khm-seo-score-display',
                'khm-seo-suggestions',
                'khm-seo-meta-preview'
            ],
            $version,
            true
        );

        // Editor styles
        wp_enqueue_style(
            'khm-seo-editor-styles',
            $plugin_url . 'assets/css/editor.css',
            [],
            $version
        );

        // Localize script with configuration
        wp_localize_script('khm-seo-editor-integration', 'khmSeoEditor', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            // Use REST nonce to align with editor REST calls.
            'nonce' => wp_create_nonce('wp_rest'),
            'currentEditor' => $current_editor,
            'config' => $this->get_editor_config(),
            'strings' => $this->get_localized_strings()
        ]);
    }

    /**
     * Enqueue Gutenberg-specific assets
     *
     * @return void
     */
    public function enqueue_gutenberg_assets(): void
    {
        $version = KHM_SEO_VERSION ?? '2.0.0';
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

        wp_enqueue_script(
            'khm-seo-gutenberg-integration',
            $plugin_url . 'assets/js/editor/gutenberg-integration.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-data'],
            $version,
            true
        );
    }

    /**
     * Add styles for classic editor
     *
     * @return void
     */
    public function add_classic_editor_styles(): void
    {
        if ($this->detect_current_editor() !== 'classic') {
            return;
        }

        echo '<style id="khm-seo-classic-editor-styles">
            .khm-seo-meta-box {
                background: #f9f9f9;
                border: 1px solid #e1e1e1;
                padding: 20px;
                margin-bottom: 20px;
            }
            .khm-seo-score-circle {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                color: white;
                margin-right: 15px;
            }
            .khm-seo-score-good { background: #46b450; }
            .khm-seo-score-ok { background: #ffb900; }
            .khm-seo-score-bad { background: #dc3232; }
        </style>';
    }

    /**
     * Handle AJAX request for live content analysis
     *
     * @return void
     */
    public function handle_live_analysis(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'khm_seo_editor_nonce')) {
            wp_die('Security check failed');
        }

        // Get content from request
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $excerpt = sanitize_textarea_field($_POST['excerpt'] ?? '');
        $focus_keyword = sanitize_text_field($_POST['focus_keyword'] ?? '');

        // Prepare analysis data
        $analysis_data = [
            'content' => $content,
            'title' => $title,
            'excerpt' => $excerpt,
            'focus_keyword' => $focus_keyword
        ];

        // Perform live analysis
        $analysis_result = $this->live_analyzer->analyze($analysis_data);

        // Return JSON response
        wp_send_json_success([
            'score' => $analysis_result['overall_score'],
            'analysis' => $analysis_result['detailed_analysis'],
            'suggestions' => $this->suggestion_engine->generate_suggestions($analysis_result),
            'timestamp' => current_time('timestamp')
        ]);
    }

    /**
     * Handle AJAX request for meta preview generation
     *
     * @return void
     */
    public function handle_meta_preview(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'khm_seo_editor_nonce')) {
            wp_die('Security check failed');
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $url = sanitize_url($_POST['url'] ?? '');

        $preview_data = $this->meta_preview->generate_preview([
            'title' => $title,
            'description' => $description,
            'url' => $url
        ]);

        wp_send_json_success($preview_data);
    }

    /**
     * Add SEO meta box to post edit screens
     *
     * @return void
     */
    public function add_seo_meta_box(): void
    {
        $post_types = get_post_types(['public' => true]);
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'khm-seo-meta-box',
                'SEO Analysis & Optimization',
                [$this, 'render_seo_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Render the SEO meta box content
     *
     * @param \WP_Post $post Current post object
     * @return void
     */
    public function render_seo_meta_box(\WP_Post $post): void
    {
        // Add nonce field
        wp_nonce_field('khm_seo_meta_box', 'khm_seo_meta_box_nonce');

        echo '<div id="khm-seo-editor-container" class="khm-seo-meta-box">';
        echo '<div id="khm-seo-loading" style="text-align: center; padding: 20px;">Loading SEO Analysis...</div>';
        echo '<div id="khm-seo-score-display" style="display: none;"></div>';
        echo '<div id="khm-seo-suggestions-panel" style="display: none;"></div>';
        echo '<div id="khm-seo-meta-preview" style="display: none;"></div>';
        echo '</div>';
    }

    /**
     * Add defer attribute to editor scripts for better performance
     *
     * @param string $tag HTML script tag
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @return string Modified script tag
     */
    public function add_script_attributes(string $tag, string $handle, string $src): string
    {
        $defer_scripts = [
            'khm-seo-live-analyzer',
            'khm-seo-score-display',
            'khm-seo-suggestions',
            'khm-seo-meta-preview'
        ];

        if (in_array($handle, $defer_scripts)) {
            return str_replace('<script', '<script defer', $tag);
        }

        return $tag;
    }

    /**
     * Detect current editor type
     *
     * @return string Editor type (classic, gutenberg, elementor)
     */
    private function detect_current_editor(): string
    {
        global $pagenow;

        // Check for Elementor
        if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
            return 'elementor';
        }

        // Check for Classic Editor plugin
        if (function_exists('use_block_editor_for_post')) {
            $post_id = $_GET['post'] ?? null;
            if ($post_id && !use_block_editor_for_post($post_id)) {
                return 'classic';
            }
        }

        // Default to Gutenberg for new WordPress versions
        if (in_array($pagenow, ['post.php', 'post-new.php'])) {
            return 'gutenberg';
        }

        return 'classic';
    }

    /**
     * Get default configuration settings
     *
     * @return array Default configuration
     */
    private function get_default_config(): array
    {
        return [
            'analysis_debounce' => 500, // ms
            'min_content_length' => 50,
            'target_score' => 75,
            'enable_suggestions' => true,
            'enable_meta_preview' => true,
            'auto_save_scores' => true
        ];
    }

    /**
     * Get editor-specific configuration
     *
     * @return array Editor configuration
     */
    private function get_editor_config(): array
    {
        return apply_filters('khm_seo_editor_config', [
            'analysis' => [
                'debounce_delay' => $this->config['analysis_debounce'],
                'min_content_length' => $this->config['min_content_length'],
                'target_score' => $this->config['target_score']
            ],
            'features' => [
                'live_analysis' => true,
                'suggestions' => $this->config['enable_suggestions'],
                'meta_preview' => $this->config['enable_meta_preview'],
                'score_tracking' => $this->config['auto_save_scores']
            ]
        ]);
    }

    /**
     * Get localized strings for JavaScript
     *
     * @return array Localized strings
     */
    private function get_localized_strings(): array
    {
        return [
            'analyzing' => __('Analyzing content...', 'khm-seo'),
            'score_excellent' => __('Excellent', 'khm-seo'),
            'score_good' => __('Good', 'khm-seo'),
            'score_needs_improvement' => __('Needs Improvement', 'khm-seo'),
            'suggestions_title' => __('SEO Suggestions', 'khm-seo'),
            'preview_title' => __('Search Engine Preview', 'khm-seo'),
            'error_analysis_failed' => __('Analysis failed. Please try again.', 'khm-seo'),
            'loading' => __('Loading...', 'khm-seo')
        ];
    }

    /**
     * Get live analyzer instance
     *
     * @return LiveAnalyzer
     */
    public function get_live_analyzer(): LiveAnalyzer
    {
        return $this->live_analyzer;
    }

    /**
     * Get score display instance
     *
     * @return ScoreDisplay
     */
    public function get_score_display(): ScoreDisplay
    {
        return $this->score_display;
    }

    /**
     * Get suggestion engine instance
     *
     * @return SuggestionEngine
     */
    public function get_suggestion_engine(): SuggestionEngine
    {
        return $this->suggestion_engine;
    }

    /**
     * Get meta preview instance
     *
     * @return MetaPreview
     */
    public function get_meta_preview(): MetaPreview
    {
        return $this->meta_preview;
    }
}
