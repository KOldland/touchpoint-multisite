<?php

/**
 * Compatibility helpers to read/write ad meta and options without ACF.
 * Native only; no ACF fallback.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Get a post meta value with optional ACF fallback.
 */
function kh_ad_get_meta( int $post_id, string $key, $default = null ) {
    $val = get_post_meta( $post_id, $key, true );
    return ( $val !== '' && $val !== null ) ? $val : $default;
}

/**
 * Update post meta (safe wrapper).
 */
function kh_ad_update_meta( int $post_id, string $key, $value ): void {
    if ( $value === null || $value === '' ) {
        delete_post_meta( $post_id, $key );
        return;
    }
    update_post_meta( $post_id, $key, $value );
}

/**
 * Get option with optional ACF fallback.
 */
function kh_ad_get_option( string $key, $default = null ) {
    $val = get_option( $key, null );
    return ( $val !== null && $val !== '' ) ? $val : $default;
}

/**
 * Read ad slot slugs for an ad.
 */
function kh_ad_get_slot_slugs( int $ad_id ): array {
    $terms = wp_get_post_terms( $ad_id, 'ad-slot', [ 'fields' => 'slugs' ] );
    if ( is_wp_error( $terms ) ) {
        return [];
    }
    return array_filter( array_map( 'sanitize_title', (array) $terms ) );
}
