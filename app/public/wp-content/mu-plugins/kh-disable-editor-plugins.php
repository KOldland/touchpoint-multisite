<?php
/**
 * Disable heavy plugins on editor requests to avoid hangs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

/**
 * Detect Gutenberg/Classic editor requests.
 *
 * @return bool
 */
function khm_is_editor_request() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $query_action = $_GET['action'] ?? '';

    // Don't disable plugins for Elementor editor sessions.
    if ( $query_action === 'elementor' || strpos( $uri, 'action=elementor' ) !== false ) {
        return false;
    }

    if ( strpos( $uri, '/wp-admin/post-new.php' ) !== false || strpos( $uri, '/wp-admin/post.php' ) !== false ) {
        return true;
    }

    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ( strpos( $referer, 'action=elementor' ) !== false ) {
            return false;
        }
        if ( strpos( $referer, '/wp-admin/post-new.php' ) !== false || strpos( $referer, '/wp-admin/post.php' ) !== false ) {
            return true;
        }
    }

    return false;
}

/**
 * Filter active plugins on editor requests.
 *
 * @param array $plugins Active plugins.
 * @return array
 */
function khm_filter_active_plugins_for_editor( $plugins ) {
    if ( ! khm_is_editor_request() ) {
        return $plugins;
    }

    $disabled = defined( 'KHM_EDITOR_DISABLED_PLUGINS' ) && is_array( KHM_EDITOR_DISABLED_PLUGINS )
        ? KHM_EDITOR_DISABLED_PLUGINS
        : array(
            // khm-plugin removed - needed for AnswerCard block
        );

    foreach ( $disabled as $plugin ) {
        $index = array_search( $plugin, $plugins, true );
        if ( $index !== false ) {
            unset( $plugins[ $index ] );
        }
    }

    return array_values( $plugins );
}

add_filter( 'option_active_plugins', 'khm_filter_active_plugins_for_editor' );

/**
 * Filter network-activated plugins on editor requests.
 *
 * @param array $plugins Active sitewide plugins.
 * @return array
 */
function khm_filter_sitewide_plugins_for_editor( $plugins ) {
    if ( ! khm_is_editor_request() ) {
        return $plugins;
    }

    $disabled = defined( 'KHM_EDITOR_DISABLED_PLUGINS' ) && is_array( KHM_EDITOR_DISABLED_PLUGINS )
        ? KHM_EDITOR_DISABLED_PLUGINS
        : array(
            // khm-plugin removed - needed for AnswerCard block
        );

    foreach ( $disabled as $plugin ) {
        if ( isset( $plugins[ $plugin ] ) ) {
            unset( $plugins[ $plugin ] );
        }
    }

    return $plugins;
}

add_filter( 'site_option_active_sitewide_plugins', 'khm_filter_sitewide_plugins_for_editor' );
