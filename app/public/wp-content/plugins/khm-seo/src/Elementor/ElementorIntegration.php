<?php
/**
 * Elementor Integration Manager
 *
 * Integrates KHM SEO GEO functionality with Elementor page builder.
 * Provides entity autocomplete and custom widgets for GEO features.
 *
 * @package KHM_SEO\Elementor
 * @since 2.0.0
 */

namespace KHM_SEO\Elementor;

use KHM_SEO\GEO\GEOManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Elementor Integration Class
 * Manages Elementor integration for GEO features
 */
class ElementorIntegration {

    /**
     * @var GEOManager GEO manager instance
     */
    private $geo_manager;
    /**
     * Detect Elementor editor contexts (UI load or editor AJAX).
     *
     * @return bool
     */
    private function is_editor_context() {
        if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            return true;
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) {
            return true;
        }
        if ( isset( $_GET['elementor-preview'] ) ) {
            return true;
        }

        if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && strpos( $_REQUEST['action'], 'elementor' ) === 0 ) {
            return true;
        }

        return false;
    }
    
    /**
     * @param string $message
     */
    private function log( $message ) {
        $stamp = '[' . gmdate( 'c' ) . '] ' . $message;
        // Always try PHP error log.
        error_log( $stamp );
        // Also force-write to wp-content logs.
        if ( defined( 'WP_CONTENT_DIR' ) ) {
            @file_put_contents( WP_CONTENT_DIR . '/debug.log', $stamp . "\n", FILE_APPEND );
            @file_put_contents( WP_CONTENT_DIR . '/khm-seo-elementor.log', $stamp . "\n", FILE_APPEND );
        }
    }

    /**
     * Constructor
     */
    public function __construct( GEOManager $geo_manager ) {
        $this->geo_manager = $geo_manager;
        $this->log( 'KHM SEO Elementor: __construct.' );
        $this->init();
    }

    /**
     * Initialize Elementor integration
     */
    public function init() {
        // Check if Elementor is active
        if ( ! did_action( 'elementor/loaded' ) ) {
            $this->log( 'KHM SEO Elementor: init skipped, elementor not loaded.' );
            return;
        }
        $this->log( 'KHM SEO Elementor: init start.' );

        // Register widget category
        add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_category' ) );

        // Register widgets
        if ( ! defined( 'KHM_SEO_DISABLE_ELEMENTOR_WIDGETS' ) || ! KHM_SEO_DISABLE_ELEMENTOR_WIDGETS ) {
            add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
        }

        // Register controls
        if ( ! defined( 'KHM_SEO_DISABLE_ELEMENTOR_CONTROLS' ) || ! KHM_SEO_DISABLE_ELEMENTOR_CONTROLS ) {
            add_action( 'elementor/controls/register', array( $this, 'register_controls' ) );
        }

        // Enqueue scripts and styles
        if ( ! defined( 'KHM_SEO_DISABLE_ELEMENTOR_EDITOR_ASSETS' ) || ! KHM_SEO_DISABLE_ELEMENTOR_EDITOR_ASSETS ) {
            add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
        }
        if ( ! defined( 'KHM_SEO_DISABLE_ELEMENTOR_FRONT_ASSETS' ) || ! KHM_SEO_DISABLE_ELEMENTOR_FRONT_ASSETS ) {
            add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
        }

        // Register AJAX handlers for entity autocomplete
        if ( ! defined( 'KHM_SEO_DISABLE_ELEMENTOR_AJAX' ) || ! KHM_SEO_DISABLE_ELEMENTOR_AJAX ) {
            add_action( 'wp_ajax_khm_geo_entity_search', array( $this, 'ajax_entity_search' ) );
        }
        $this->log( 'KHM SEO Elementor: init hooks registered.' );
    }

    /**
     * Register widget category
     */
    public function register_widget_category( $elements_manager ) {
        $this->log( 'KHM SEO Elementor: register_widget_category.' );
        $elements_manager->add_category(
            'khm-seo',
            array(
                'title' => __( 'KHM SEO', 'khm-seo' ),
                'icon' => 'fa fa-search',
            )
        );
    }

    /**
     * Register Elementor widgets
     */
    public function register_widgets( $widgets_manager ) {
        $this->log( 'KHM SEO Elementor: register_widgets.' );
        $is_editor = $this->is_editor_context();

        // Skip AnswerCard entirely in the editor to isolate the spinner cause.
        if ( $is_editor ) {
            $this->log( 'KHM SEO Elementor: editor mode detected, skipping AnswerCard registration.' );
        }

        // Register AnswerCard widget with entity autocomplete
        if ( ! $is_editor && ! ( defined( 'KHM_SEO_DISABLE_WIDGET_ANSWERCARD' ) && KHM_SEO_DISABLE_WIDGET_ANSWERCARD ) ) {
            require_once __DIR__ . '/widgets/AnswerCard.php';
            $widgets_manager->register( new \KHM_SEO\Elementor\Widgets\AnswerCard() );
        }

        // Register Client Badge widget
        if ( ! defined( 'KHM_SEO_DISABLE_WIDGET_CLIENTBADGE' ) || ! KHM_SEO_DISABLE_WIDGET_CLIENTBADGE ) {
            require_once __DIR__ . '/widgets/ClientBadge.php';
            $widgets_manager->register( new \KHM_SEO\Elementor\Widgets\ClientBadge() );
        }
    }

    /**
     * Register custom controls
     */
    public function register_controls( $controls_manager ) {
        $this->log( 'KHM SEO Elementor: register_controls.' );
        // Register entity autocomplete control (skip in editor to avoid init issues)
        $is_editor = $this->is_editor_context();
        if ( $is_editor ) {
            return;
        }

        require_once __DIR__ . '/controls/EntityAutocomplete.php';
        $controls_manager->register( new \KHM_SEO\Elementor\Controls\EntityAutocomplete() );
    }

    /**
     * Enqueue editor scripts
     */
    public function enqueue_editor_scripts() {
        $this->log( 'KHM SEO Elementor: enqueue_editor_scripts.' );
        // Ensure we enqueue after Elementor web-cli so its elementorWebCliConfig is available.
        wp_enqueue_script(
            'khm-geo-elementor-editor',
            KHM_SEO_PLUGIN_URL . 'assets/js/elementor-editor.js',
            array( 'jquery', 'elementor-editor', 'elementor-web-cli' ),
            KHM_SEO_VERSION,
            true
        );

        // Canonical REST nonce for editor requests.
        $rest_nonce = wp_create_nonce( 'wp_rest' );

        wp_localize_script(
            'khm-geo-elementor-editor',
            'khmGeoElementor',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => $rest_nonce,
                'strings'  => array(
                    'search_entities'   => __( 'Search entities...', 'khm-seo' ),
                    'no_entities_found' => __( 'No entities found', 'khm-seo' ),
                    'select_entity'     => __( 'Select entity', 'khm-seo' ),
                ),
            )
        );

        // Inline shim to ensure Elementor has a top-level REST nonce.
        $inline = "
(function(){
    try {
        window.elementorCommonConfig = window.elementorCommonConfig || {};
        if ( typeof window.elementorCommonConfig.nonce === 'undefined' || ! window.elementorCommonConfig.nonce ) {
            window.elementorCommonConfig.nonce = '" . esc_js( $rest_nonce ) . "';
        }
    } catch (e) {
        console.warn('KH-SEO: elementor nonce injection failed', e);
    }
})();
";
        wp_add_inline_script( 'elementor-web-cli', $inline );
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        $this->log( 'KHM SEO Elementor: enqueue_frontend_scripts.' );
        wp_enqueue_style(
            'khm-geo-elementor-frontend',
            KHM_SEO_PLUGIN_URL . 'assets/css/elementor-frontend.css',
            array(),
            KHM_SEO_VERSION
        );
    }

    /**
     * AJAX handler for entity search
     */
    public function ajax_entity_search() {
        $this->log( 'KHM SEO Elementor: ajax_entity_search.' );
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        $search = sanitize_text_field( $_POST['search'] ?? '' );
        $limit = intval( $_POST['limit'] ?? 10 );

        if ( empty( $search ) ) {
            wp_send_json_error( 'Search term required' );
        }

        $entities = $this->geo_manager->get_entity_manager()->search_entities( array(
            'search' => $search,
            'status' => 'active',
            'limit' => $limit,
        ) );

        $results = array();
        foreach ( $entities as $entity ) {
            $results[] = array(
                'id' => $entity->id,
                'text' => $entity->canonical,
                'type' => $entity->type,
                'url' => get_permalink( $entity->id ),
            );
        }

        wp_send_json_success( $results );
    }
}
