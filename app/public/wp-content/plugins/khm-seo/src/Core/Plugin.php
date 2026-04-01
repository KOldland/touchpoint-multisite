<?php
/**
 * Main plugin class for KHM SEO.
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

namespace KHM_SEO\Core;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KHM_SEO\Meta\MetaManager;
use KHM_SEO\Schema\SchemaManager;
use KHM_SEO\Schema\Admin\SchemaAdminManager;
use KHM_SEO\Sitemap\SitemapManager;
use KHM_SEO\Admin\AdminManager;
use KHM_SEO\Tools\ToolsManager;
use KHM_SEO\Social\SocialMediaManager;
use KHM_SEO\Validation\SchemaValidator;
use KHM_SEO\Utils\DatabaseManager;
use KHM_SEO\Analysis\AnalysisEngine;
use KHM_SEO\Editor\EditorManager;
use KHM_SEO\Performance\PerformanceMonitor;
use KHM_SEO\Analytics\AdvancedAnalyticsEngine;
use KHM_SEO\GEO\GEOManager;
use KHM_SEO\Elementor\Widgets\Breadcrumbs_Widget;
use KHM_SEO\Elementor\Widgets\LocalBusiness_Widget;
use KHM_SEO\Elementor\Widgets\SeoWidget_Widget;
use KHM_SEO\Elementor\Widgets\SeoChart_Widget;
use KHM_SEO\Elementor\Widgets\SeoStats_Widget;
use KHM_SEO\Elementor\Widgets\SeoAlerts_Widget;
use KHM_SEO\Elementor\ElementorIntegration;

/**
 * Main plugin class.
 */
final class Plugin {

    /**
     * Plugin instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version = KHM_SEO_VERSION;

    /**
     * Meta manager instance.
     *
     * @var MetaManager|null
     */
    public $meta = null;

    /**
     * Schema manager instance.
     *
     * @var SchemaManager|null
     */
    public $schema = null;

    /**
     * Sitemap manager instance.
     *
     * @var SitemapManager|null
     */
    public $sitemap = null;

    /**
     * Admin manager instance.
     *
     * @var AdminManager|null
     */
    public $admin = null;

    /**
     * Tools manager instance.
     *
     * @var ToolsManager|null
     */
    public $tools = null;

    /**
     * Social media manager instance.
     *
     * @var SocialMediaManager|null
     */
    public $social = null;

    /**
     * Schema admin manager instance.
     *
     * @var Schema\Admin\SchemaAdminManager|null
     */
    public $schema_admin = null;

    /**
     * Schema validator instance.
     *
     * @var SchemaValidator|null
     */
    public $validator = null;

    /**
     * Database manager instance.
     *
     * @var DatabaseManager|null
     */
    public $database = null;

    /**
     * Analysis engine instance.
     *
     * @var AnalysisEngine|null
     */
    public $analysis = null;

    /**
     * Editor manager instance.
     *
     * @var EditorManager|null
     */
    public $editor = null;

    /**
     * Performance monitor instance.
     *
     * @var PerformanceMonitor|null
     */
    public $performance = null;
    
    /**
     * Advanced analytics engine instance.
     *
     * @var AdvancedAnalyticsEngine|null
     */
    public $analytics = null;
    
    /**
     * GEO (Generative Engine Optimization) manager instance.
     *
     * @var \KHM_SEO\GEO\GEOManager|null
     */
    public $geo = null;

    /**
     * Elementor integration instance.
     *
     * @var \KHM_SEO\Elementor\ElementorIntegration|null
     */
    public $elementor = null;

    /**
     * Tracker connector instance.
     *
     * @var \KHM_SEO\API\TrackerConnector|null
     */
    public $tracker = null;

    /**
     * Get plugin instance.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize the plugin.
     */
    private function init() {
        // Load text domain
        add_action( 'init', array( $this, 'load_textdomain' ) );
        
        // Initialize core components
        add_action( 'init', array( $this, 'init_components' ) );
        
        // Initialize database only if not in testing mode
        if ( ! defined( 'KHM_SEO_TESTING' ) || ! KHM_SEO_TESTING ) {
            $this->database = new DatabaseManager();
        }
        
        // Hook into WordPress
        add_filter( 'wp_title', array( $this, 'filter_title' ), 10, 2 );
        add_action( 'wp_footer', array( $this, 'output_footer_tags' ) );
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'khm-seo',
            false,
            dirname( plugin_basename( KHM_SEO_PLUGIN_FILE ) ) . '/languages'
        );
    }

    /**
     * Initialize plugin components.
     */
    public function init_components() {
        error_log( 'KHM SEO: init_components start.' );
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_post_editor = is_admin() && ( false !== strpos( $request_uri, 'post-new.php' ) || false !== strpos( $request_uri, 'post.php' ) );
        $is_elementor_editor = is_admin() && isset( $_GET['action'] ) && 'elementor' === $_GET['action'];
        $is_rest_request = defined( 'REST_REQUEST' ) && REST_REQUEST;
        $disable_editor = defined( 'KHM_SEO_DISABLE_EDITOR' ) && KHM_SEO_DISABLE_EDITOR;
        // Initialize managers
        $this->meta = new MetaManager();
        $this->schema = new SchemaManager();
        if ( ! $is_post_editor && ! $is_rest_request ) {
            $this->sitemap = new SitemapManager( new \KHM_SEO\Sitemap\SitemapGenerator() );
        }
        $this->admin = new AdminManager();
        if ( ! $is_post_editor && ! $is_rest_request ) {
            $this->tools = new ToolsManager();
        }
        
        // Initialize Phase 3 social media manager
        $this->social = new SocialMediaManager();
        
        // Initialize Phase 4 schema admin interface
        if ( is_admin() ) {
            $this->schema_admin = new SchemaAdminManager();
        }
        
        // Initialize Phase 5 schema validator
        if ( ( ! $is_post_editor && ! $is_rest_request ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            $this->validator = new SchemaValidator();
        }
        
        // Initialize analysis engine with default configuration
        if ( ! $is_post_editor && ! $is_rest_request ) {
            $this->analysis = new AnalysisEngine( $this->get_analysis_config() );
        }

        if ( is_admin() ) {
            $this->performance = new PerformanceMonitor();
        }
        
        // Initialize editor manager for Phase 2 (skip on editor/REST to avoid hangs).
        if ( ! $is_post_editor && ! $is_rest_request && ! $disable_editor ) {
            $this->editor = new EditorManager();
            $this->editor->init();
        }
        
        
        // Ensure GEO manager is ready before Elementor integration.
        if ( ( ! $is_post_editor && ! $is_rest_request ) || $is_elementor_editor ) {
            if ( ! $this->geo instanceof GEOManager ) {
                $this->geo = class_exists( GEOManager::class ) ? new GEOManager() : null;
            }
            error_log( 'KHM SEO: GEO manager status: ' . ( $this->geo instanceof GEOManager ? 'ready' : 'missing' ) );

            // Initialize Elementor integration (resilient to load order)
            if ( defined( 'KHM_SEO_DISABLE_ELEMENTOR' ) && KHM_SEO_DISABLE_ELEMENTOR ) {
                error_log( 'KHM SEO: Elementor integration disabled via KHM_SEO_DISABLE_ELEMENTOR.' );
            } elseif ( $this->geo instanceof GEOManager ) {
                if ( did_action( 'elementor/loaded' ) ) {
                    error_log( 'KHM SEO: Elementor already loaded, bootstrapping integration.' );
                    $this->elementor = new ElementorIntegration( $this->geo );
                } else {
                    $that = $this;
                    add_action( 'elementor/loaded', function() use ( $that ) {
                        if ( ! $that->geo instanceof GEOManager ) {
                            $that->geo = class_exists( GEOManager::class ) ? new GEOManager() : null;
                        }

                        if ( $that->geo instanceof GEOManager ) {
                            error_log( 'KHM SEO: Elementor loaded later, bootstrapping integration.' );
                            $that->elementor = new ElementorIntegration( $that->geo );
                        }
                    } );
                }
            } else {
                // Guard: avoid fatal if GEO is unavailable; emit log for troubleshooting.
                error_log( 'KHM SEO: Skipping Elementor integration because GEOManager is not available.' );
            }
        }

        // Register Elementor widgets for SEO shortcodes.
        add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );

        // Initialize tracker connector
        $this->tracker = new \KHM_SEO\API\TrackerConnector();
    }

    /**
     * Register Elementor widgets for SEO shortcodes.
     */
    public function register_elementor_widgets( $widgets_manager ) {
        if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
            return;
        }

        $widgets = array(
            Breadcrumbs_Widget::class,
            LocalBusiness_Widget::class,
            SeoWidget_Widget::class,
            SeoChart_Widget::class,
            SeoStats_Widget::class,
            SeoAlerts_Widget::class,
            \KHM_SEO\Elementor\Widgets\SmartTag_Widget::class,
            \KHM_SEO\Elementor\Widgets\ConditionalContent_Widget::class,
        );

        foreach ( $widgets as $widget_class ) {
            if ( class_exists( $widget_class ) ) {
                $widgets_manager->register( new $widget_class() );
            }
        }
    }

    /**
     * Output SEO tags in the head section.
     */
    public function output_head_tags() {
        if ( ! $this->meta ) {
            return;
        }
        
        // Output meta tags
        $this->meta->output_meta_tags();
        
        // Output schema markup
        if ( $this->schema ) {
            $this->schema->output_schema();
        }
    }

    /**
     * Filter the WordPress title.
     *
     * @param string $title The current title.
     * @param string $sep   The separator.
     * @return string Modified title.
     */
    public function filter_title( $title, $sep = '' ) {
        if ( ! $this->meta ) {
            return $title;
        }
        
        return $this->meta->get_title() ?: $title;
    }

    /**
     * Output footer tags if needed.
     */
    public function output_footer_tags() {
        // Reserved for future footer-specific SEO elements
        do_action( 'khm_seo_footer_output' );
    }

    /**
     * Get plugin information.
     *
     * @return array Plugin information.
     */
    public function get_plugin_info() {
        return array(
            'name'        => 'KHM SEO',
            'version'     => $this->version,
            'description' => __( 'Complete SEO solution for content marketing and publishing.', 'khm-seo' ),
            'author'      => 'KHM Development Team',
            'url'         => 'https://1927magazine.com/',
        );
    }

    /**
     * Check if plugin is properly initialized.
     *
     * @return bool True if initialized, false otherwise.
     */
    public function is_initialized() {
        return null !== $this->meta && 
               null !== $this->schema && 
               null !== $this->sitemap && 
               null !== $this->admin && 
               null !== $this->tools &&
               null !== $this->analysis &&
               ( ! is_admin() || null !== $this->performance );
    }

    /**
     * Get analysis engine configuration.
     *
     * @return array Analysis configuration
     */
    private function get_analysis_config() {
        // Get options from WordPress or use defaults
        $options = get_option( 'khm_seo_analysis', array() );
        
        return wp_parse_args( $options, array(
            'keywords' => array(
                'target_density_min' => 0.5,
                'target_density_max' => 2.5,
                'max_keyword_stuffing' => 3.0
            ),
            'readability' => array(
                'max_sentence_length' => 20,
                'max_paragraph_length' => 150,
                'transition_word_threshold' => 30,
                'passive_voice_threshold' => 10
            ),
            'content' => array(
                'min_word_count' => 300,
                'optimal_word_count' => 1000,
                'power_word_density' => 1.0,
                'min_cta_count' => 1
            )
        ) );
    }

    /**
     * Get meta manager instance.
     *
     * @return MetaManager|null
     */
    public function get_meta_manager() {
        return $this->meta;
    }

    /**
     * Get schema manager instance.
     *
     * @return SchemaManager|null
     */
    public function get_schema_manager() {
        return $this->schema;
    }

    /**
     * Get sitemap manager instance.
     *
     * @return SitemapManager|null
     */
    public function get_sitemap_manager() {
        return $this->sitemap;
    }

    /**
     * Get admin manager instance.
     *
     * @return AdminManager|null
     */
    public function get_admin_manager() {
        return $this->admin;
    }

    /**
     * Get tools manager instance.
     *
     * @return ToolsManager|null
     */
    public function get_tools_manager() {
        return $this->tools;
    }

    /**
     * Get analysis engine instance.
     *
     * @return AnalysisEngine|null
     */
    public function get_analysis_engine() {
        return $this->analysis;
    }

    /**
     * Get performance monitor instance.
     *
     * @return PerformanceMonitor|null
     */
    public function get_performance_monitor() {
        return $this->performance;
    }

    /**
     * Get GEO manager instance.
     *
     * @return \KHM_SEO\GEO\GEOManager|null
     */
    public function get_geo_manager() {
        return $this->geo;
    }

    /**
     * Analyze content for SEO
     *
     * @param string $content Content to analyze
     * @param string $keyword Target keyword
     * @return array Analysis results
     */
    public function analyze_content( $content, $keyword = '' ) {
        if ( ! $this->analysis ) {
            return array(
                'overall_score' => 0,
                'suggestions' => array( 'Analysis engine not initialized' ),
                'component_scores' => array(),
                'error' => 'Analysis engine not available'
            );
        }

        return $this->analysis->analyze( $content, $keyword );
    }
}
