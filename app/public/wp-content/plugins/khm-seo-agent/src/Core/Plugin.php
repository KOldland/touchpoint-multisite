<?php

namespace KHM_SEO_AGENT\Core;

use KHM_SEO_AGENT\API\Rest_Api;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'admin_notices', array( $this, 'maybe_show_dependency_notice' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'khm-seo-agent', false, dirname( plugin_basename( KHM_SEO_AGENT_PLUGIN_FILE ) ) . '/languages' );
    }

    public function register_rest_routes() {
        $api = new Rest_Api();
        $api->register_routes();
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'khm-seo-agent-editor-modal',
            KHM_SEO_AGENT_PLUGIN_URL . 'assets/js/editor-modal.js',
            array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' ),
            KHM_SEO_AGENT_VERSION,
            true
        );

        wp_enqueue_style(
            'khm-seo-agent-editor-modal',
            KHM_SEO_AGENT_PLUGIN_URL . 'assets/css/editor-modal.css',
            array(),
            KHM_SEO_AGENT_VERSION
        );

        wp_localize_script( 'khm-seo-agent-editor-modal', 'khmSeoAgentData', array(
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'restUrl' => rest_url( 'khm-seo-agent/v1/' ),
        ) );
    }

    public function maybe_show_dependency_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! $this->has_dependencies() ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__( 'KHM SEO Agent requires Dual-GPT and KHM SEO to be active.', 'khm-seo-agent' )
            );
        }
    }

    public function has_dependencies() {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        return is_plugin_active( 'dual-gpt-wordpress-plugin/dual-gpt-wordpress-plugin.php' )
            && is_plugin_active( 'khm-seo/khm-seo.php' );
    }
}
