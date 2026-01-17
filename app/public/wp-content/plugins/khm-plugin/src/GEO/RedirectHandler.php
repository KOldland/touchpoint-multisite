<?php
/**
 * GEO Redirect Handler
 *
 * Handles the /r/<code> short URLs for tracking citation clicks.
 * This allows us to track click-through on citations without modifying
 * the publisher's canonical URL.
 *
 * @package KHM\GEO
 */

namespace KHM\GEO;

defined( 'ABSPATH' ) || exit;

use KHM\Migrations\GeoAnswerCardMigration;

/**
 * GEO Redirect Handler Class
 */
class RedirectHandler {

    /**
     * Initialize the redirect handler.
     *
     * @return void
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
        add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ) );
    }

    /**
     * Register rewrite rules for /r/<code> URLs.
     *
     * @return void
     */
    public static function register_rewrite_rules() {
        add_rewrite_rule(
            '^r/([a-zA-Z0-9]+)/?$',
            'index.php?khm_geo_redirect=$matches[1]',
            'top'
        );
    }

    /**
     * Add our custom query var.
     *
     * @param array $vars Existing query vars.
     * @return array Modified query vars.
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'khm_geo_redirect';
        return $vars;
    }

    /**
     * Handle the redirect request.
     *
     * @return void
     */
    public static function handle_redirect() {
        $code = get_query_var( 'khm_geo_redirect' );

        if ( empty( $code ) ) {
            return;
        }

        // Sanitize the code
        $code = sanitize_text_field( $code );

        // Look up the redirect
        $redirect = GeoAnswerCardMigration::get_redirect_by_code( $code );

        if ( ! $redirect || empty( $redirect->target_url ) ) {
            // Code not found - redirect to home
            wp_safe_redirect( home_url(), 302 );
            exit;
        }

        // Log the click
        GeoAnswerCardMigration::log_redirect_click( $redirect->id, $code );

        // Perform the redirect
        // Note: wp_safe_redirect only allows redirects to whitelisted hosts.
        // For external URLs, we need to use wp_redirect with validation.
        $target_url = esc_url_raw( $redirect->target_url );

        // Validate the URL scheme
        $scheme = wp_parse_url( $target_url, PHP_URL_SCHEME );
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            wp_safe_redirect( home_url(), 302 );
            exit;
        }

        // Use 302 temporary redirect (allows search engines to index the original)
        // We use wp_redirect here since we validated the URL
        wp_redirect( $target_url, 302, 'KHM-GEO-Tracker' );
        exit;
    }

    /**
     * Flush rewrite rules. Call this on plugin activation.
     *
     * @return void
     */
    public static function flush_rules() {
        self::register_rewrite_rules();
        flush_rewrite_rules();
    }
}

// Initialize the handler
RedirectHandler::init();
