<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register REST endpoints for Sponsor API.
 * 
 * Endpoints:
 * - GET  /wp-json/kh-ad-manager/v1/sponsor/{id}
 * - GET  /wp-json/kh-ad-manager/v1/sponsor/{id}/geo-rules
 * - POST /wp-json/kh-ad-manager/v1/sponsor-approve (via SMMA, see RestController)
 */

add_action( 'rest_api_init', function() {
    register_rest_route( 'kh-ad-manager/v1', '/sponsor/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'kh_ad_manager_rest_get_sponsor',
        'permission_callback' => 'kh_ad_manager_rest_sponsor_permissions_check', // Restrict access to authenticated users with edit_posts
    ) );

    register_rest_route( 'kh-ad-manager/v1', '/sponsor/(?P<id>\d+)/geo-rules', array(
        'methods'             => 'GET',
        'callback'            => 'kh_ad_manager_rest_get_sponsor_geo_rules',
        'permission_callback' => 'kh_ad_manager_rest_sponsor_permissions_check',
    ) );

    register_rest_route( 'kh-ad-manager/v1', '/sponsor/(?P<id>\d+)/assets', array(
        'methods'             => 'GET',
        'callback'            => 'kh_ad_manager_rest_get_sponsor_assets',
        'permission_callback' => 'kh_ad_manager_rest_sponsor_permissions_check',
    ) );

    register_rest_route( 'kh-ad-manager/v1', '/sponsor/(?P<id>\d+)/budget', array(
        'methods'             => 'GET',
        'callback'            => 'kh_ad_manager_rest_get_sponsor_budget',
        'permission_callback' => 'kh_ad_manager_rest_sponsor_permissions_check',
    ) );
} );

/**
 * Permission callback for Sponsor API routes.
 * 
 * Restricts access to authenticated users who can edit posts.
 * 
 * @param WP_REST_Request $request The current REST request.
 * @return bool True if the user has permission, false otherwise.
 */
function kh_ad_manager_rest_sponsor_permissions_check( WP_REST_Request $request ) {
    return current_user_can( 'edit_posts' );
}

/**
 * GET /wp-json/kh-ad-manager/v1/sponsor/{id}
 * 
 * Returns full sponsor metadata including allowed_claims, co_brand_policy,
 * assets, budgets, and contact info.
 */
function kh_ad_manager_rest_get_sponsor( WP_REST_Request $request ) {
    $id = absint( $request->get_param( 'id' ) );

    if ( ! $id ) {
        return new WP_Error(
            'kh_ad_manager_missing_sponsor',
            __( 'Sponsor ID is required.', 'kh-ad-manager' ),
            array( 'status' => 400 )
        );
    }

    $post = get_post( $id );
    if ( ! $post || 'kh_sponsor' !== $post->post_type ) {
        return new WP_Error(
            'kh_ad_manager_sponsor_not_found',
            __( 'Sponsor not found.', 'kh-ad-manager' ),
            array( 'status' => 404 )
        );
    }

    $sponsor = kh_ad_manager_get_sponsor_meta( $id );

    return rest_ensure_response( array(
        'sponsor_id'      => (int) $sponsor['sponsor_id'],
        'name'            => $sponsor['name'] ?? '',
        'allowed_claims'  => array_filter( $sponsor['allowed_claims'] ?? array() ),
        'co_brand_policy' => $sponsor['co_brand_policy'] ?? 'co-brand',
        'assets'          => kh_ad_manager_format_assets_for_api( $sponsor['sponsor_assets'] ?? array() ),
        'ppc_budget'      => array(
            'total'       => (float) ( $sponsor['ppc_budget_total'] ?? 0 ),
            'daily_cap'   => (float) ( $sponsor['ppc_daily_cap'] ?? 0 ),
        ),
        'geo_rules'       => is_array( $sponsor['geo_rules'] ) ? $sponsor['geo_rules'] : array(),
        'approval_contact'=> $sponsor['approval_contact'] ?? array(),
        'linkedin_page'   => $sponsor['linkedin_page_url'] ?? '',
        'linkedin_handles'=> array_filter( $sponsor['linkedin_handles'] ?? array() ),
        'content_library' => $sponsor['content_library_url'] ?? '',
        'ppc_account_id'  => $sponsor['ppc_account_id'] ?? '',
    ) );
}

/**
 * GET /wp-json/kh-ad-manager/v1/sponsor/{id}/geo-rules
 * 
 * Returns geo-specific sponsor rules keyed by country code.
 */
function kh_ad_manager_rest_get_sponsor_geo_rules( WP_REST_Request $request ) {
    $id = absint( $request->get_param( 'id' ) );

    if ( ! $id ) {
        return new WP_Error(
            'kh_ad_manager_missing_sponsor',
            __( 'Sponsor ID is required.', 'kh-ad-manager' ),
            array( 'status' => 400 )
        );
    }

    $post = get_post( $id );
    if ( ! $post || 'kh_sponsor' !== $post->post_type ) {
        return new WP_Error(
            'kh_ad_manager_sponsor_not_found',
            __( 'Sponsor not found.', 'kh-ad-manager' ),
            array( 'status' => 404 )
        );
    }

    $geo_rules = get_post_meta( $id, 'geo_rules', true );

    return rest_ensure_response( array(
        'sponsor_id' => (int) $id,
        'geo_rules'  => is_array( $geo_rules ) ? $geo_rules : array(),
    ) );
}

/**
 * GET /wp-json/kh-ad-manager/v1/sponsor/{id}/assets
 * 
 * Returns formatted sponsor assets with URLs, thumbnails, alt text, metadata.
 */
function kh_ad_manager_rest_get_sponsor_assets( WP_REST_Request $request ) {
    $id = absint( $request->get_param( 'id' ) );

    if ( ! $id ) {
        return new WP_Error(
            'kh_ad_manager_missing_sponsor',
            __( 'Sponsor ID is required.', 'kh-ad-manager' ),
            array( 'status' => 400 )
        );
    }

    $post = get_post( $id );
    if ( ! $post || 'kh_sponsor' !== $post->post_type ) {
        return new WP_Error(
            'kh_ad_manager_sponsor_not_found',
            __( 'Sponsor not found.', 'kh-ad-manager' ),
            array( 'status' => 404 )
        );
    }

    $raw_assets = get_post_meta( $id, 'sponsor_assets', true );
    $assets     = kh_ad_manager_format_assets_for_api( $raw_assets );

    return rest_ensure_response( array(
        'sponsor_id' => (int) $id,
        'assets'     => $assets,
        'count'      => count( $assets ),
    ) );
}

/**
 * GET /wp-json/kh-ad-manager/v1/sponsor/{id}/budget
 * 
 * Returns current and historical budget info including spend tracking.
 */
function kh_ad_manager_rest_get_sponsor_budget( WP_REST_Request $request ) {
    $id = absint( $request->get_param( 'id' ) );

    if ( ! $id ) {
        return new WP_Error(
            'kh_ad_manager_missing_sponsor',
            __( 'Sponsor ID is required.', 'kh-ad-manager' ),
            array( 'status' => 400 )
        );
    }

    $post = get_post( $id );
    if ( ! $post || 'kh_sponsor' !== $post->post_type ) {
        return new WP_Error(
            'kh_ad_manager_sponsor_not_found',
            __( 'Sponsor not found.', 'kh-ad-manager' ),
            array( 'status' => 404 )
        );
    }

    $sponsor = kh_ad_manager_get_sponsor_meta( $id );
    $spend   = get_post_meta( $id, 'spend_tracking', true );

    if ( ! is_array( $spend ) ) {
        $spend = array(
            'total_spent'  => 0,
            'today_spent'  => 0,
            'last_updated' => 0,
        );
    }

    return rest_ensure_response( array(
        'sponsor_id'   => (int) $id,
        'budget_total' => (float) ( $sponsor['ppc_budget_total'] ?? 0 ),
        'budget_daily' => (float) ( $sponsor['ppc_daily_cap'] ?? 0 ),
        'spend'        => array(
            'total'        => (float) ( $spend['total_spent'] ?? 0 ),
            'today'        => (float) ( $spend['today_spent'] ?? 0 ),
            'last_updated' => (int) ( $spend['last_updated'] ?? 0 ),
        ),
        'remaining'    => array(
            'total' => max( 0, (float) ( $sponsor['ppc_budget_total'] ?? 0 ) - (float) ( $spend['total_spent'] ?? 0 ) ),
            'daily' => max( 0, (float) ( $sponsor['ppc_daily_cap'] ?? 0 ) - (float) ( $spend['today_spent'] ?? 0 ) ),
        ),
    ) );
}

/**
 * Helper: Format assets for REST API response.
 * 
 * Ensures each asset has id, url, thumb, alt, type, and metadata.
 */
function kh_ad_manager_format_assets_for_api( $raw_assets ) {
    if ( ! is_array( $raw_assets ) ) {
        return array();
    }

    $formatted = array();
    foreach ( $raw_assets as $asset ) {
        if ( ! is_array( $asset ) || empty( $asset['id'] ) ) {
            continue;
        }

        $attachment_id = (int) $asset['id'];
        $attachment    = get_post( $attachment_id );

        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            continue;
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        $thumb_id = get_post_thumbnail_id( $attachment_id );
        $thumb_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : wp_get_attachment_url( $attachment_id );

        $formatted[] = array(
            'id'       => $attachment_id,
            'url'      => wp_get_attachment_url( $attachment_id ),
            'thumb'    => $thumb_url,
            'alt'      => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
            'type'     => $asset['type'] ?? get_post_mime_type( $attachment_id ),
            'metadata' => is_array( $metadata ) ? $metadata : array(),
        );
    }

    return $formatted;
}
