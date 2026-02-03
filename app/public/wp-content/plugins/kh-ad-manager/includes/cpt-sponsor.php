<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the Sponsor CPT.
 * 
 * The sponsor CPT stores canonical sponsor records with metadata for:
 * - Contact info and linked social profiles
 * - Allowed claims and co-branding policies
 * - Asset library (logos, creatives, captions)
 * - Geo-specific rules and budget caps
 * - Approval contact and PPC account info
 */
add_action( 'init', function() {
    $labels = array(
        'name'               => __( 'Sponsors', 'kh-ad-manager' ),
        'singular_name'      => __( 'Sponsor', 'kh-ad-manager' ),
        'add_new'            => __( 'Add Sponsor', 'kh-ad-manager' ),
        'add_new_item'       => __( 'Add New Sponsor', 'kh-ad-manager' ),
        'edit_item'          => __( 'Edit Sponsor', 'kh-ad-manager' ),
        'new_item'           => __( 'New Sponsor', 'kh-ad-manager' ),
        'view_item'          => __( 'View Sponsor', 'kh-ad-manager' ),
        'search_items'       => __( 'Search Sponsors', 'kh-ad-manager' ),
        'not_found'          => __( 'No sponsors found', 'kh-ad-manager' ),
        'not_found_in_trash' => __( 'No sponsors found in Trash', 'kh-ad-manager' ),
        'menu_name'          => __( 'Sponsors', 'kh-ad-manager' ),
    );

    register_post_type( 'kh_sponsor', array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => 'edit.php?post_type=ad_unit',
        'show_in_rest'       => true,
        'rest_base'          => 'sponsors',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'has_archive'        => false,
        'menu_position'      => 28,
        'supports'           => array( 'title' ),
        'capability_type'    => 'post',
    ) );

    // Register post meta for REST API
    register_post_meta( 'kh_sponsor', 'linkedin_page_url', array(
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'show_in_rest'      => true,
    ) );

    register_post_meta( 'kh_sponsor', 'linkedin_handles', array(
        'type'              => 'array',
        'sanitize_callback' => 'kh_ad_manager_sanitize_array',
        'show_in_rest'      => array(
            'schema' => array(
                'type'  => 'array',
                'items' => array( 'type' => 'string' ),
            ),
        ),
    ) );

    register_post_meta( 'kh_sponsor', 'quotable_representatives', array(
        'type'              => 'array',
        'sanitize_callback' => 'kh_ad_manager_sanitize_array',
        'show_in_rest'      => array(
            'schema' => array(
                'type'  => 'array',
                'items' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'name'  => array( 'type' => 'string' ),
                        'title' => array( 'type' => 'string' ),
                    ),
                ),
            ),
        ),
    ) );

    register_post_meta( 'kh_sponsor', 'content_library_url', array(
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'show_in_rest'      => true,
    ) );

    register_post_meta( 'kh_sponsor', 'sponsor_assets', array(
        'type'              => 'array',
        'sanitize_callback' => 'kh_ad_manager_sanitize_array',
        'show_in_rest'      => array(
            'schema' => array(
                'type'  => 'array',
                'items' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id'       => array( 'type' => 'integer' ),
                        'url'      => array( 'type' => 'string' ),
                        'thumb'    => array( 'type' => 'string' ),
                        'alt'      => array( 'type' => 'string' ),
                        'type'     => array( 'type' => 'string' ),
                        'metadata' => array( 'type' => 'object' ),
                    ),
                ),
            ),
        ),
    ) );

    register_post_meta( 'kh_sponsor', 'allowed_claims', array(
        'type'              => 'array',
        'sanitize_callback' => 'kh_ad_manager_sanitize_array',
        'show_in_rest'      => array(
            'schema' => array(
                'type'  => 'array',
                'items' => array( 'type' => 'string' ),
            ),
        ),
    ) );

    register_post_meta( 'kh_sponsor', 'co_brand_policy', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => true,
    ) );

    register_post_meta( 'kh_sponsor', 'geo_rules', array(
        'type'              => 'array',
        'sanitize_callback' => 'kh_ad_manager_sanitize_array',
        'show_in_rest'      => array(
            'schema' => array(
                'type'                 => 'object',
                'additionalProperties' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'policy'     => array( 'type' => 'string' ),
                        'asset_id'   => array( 'type' => 'integer' ),
                        'budget_cap' => array( 'type' => 'number' ),
                    ),
                ),
            ),
        ),
    ) );

    register_post_meta( 'kh_sponsor', 'ppc_budget_total', array(
        'type'              => 'number',
        'sanitize_callback' => 'floatval',
        'show_in_rest'      => true,
    ) );

    register_post_meta( 'kh_sponsor', 'ppc_daily_cap', array(
        'type'              => 'number',
        'sanitize_callback' => 'floatval',
        'show_in_rest'      => true,
    ) );

    register_post_meta( 'kh_sponsor', 'ppc_account_id', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => true,
    ) );

    register_post_meta( 'kh_sponsor', 'approval_contact', array(
        'type'              => 'array',
        'sanitize_callback' => 'kh_ad_manager_sanitize_array',
        'show_in_rest'      => array(
            'schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'name'  => array( 'type' => 'string' ),
                    'email' => array( 'type' => 'string' ),
                    'role'  => array( 'type' => 'string' ),
                ),
            ),
        ),
    ) );

    register_post_meta( 'kh_sponsor', 'spend_tracking', array(
        'type'              => 'array',
        'sanitize_callback' => 'kh_ad_manager_sanitize_array',
        'show_in_rest'      => array(
            'schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'total_spent'  => array( 'type' => 'number' ),
                    'today_spent'  => array( 'type' => 'number' ),
                    'last_updated' => array( 'type' => 'integer' ),
                ),
            ),
        ),
    ) );
} );

/**
 * Sanitize array values for post meta.
 */
function kh_ad_manager_sanitize_array( $value ) {
    if ( ! is_array( $value ) ) {
        return is_string( $value ) ? array( sanitize_text_field( $value ) ) : array();
    }
    return array_map( function( $item ) {
        if ( is_array( $item ) ) {
            return array_map( 'sanitize_text_field', $item );
        }
        return sanitize_text_field( $item );
    }, $value );
}
