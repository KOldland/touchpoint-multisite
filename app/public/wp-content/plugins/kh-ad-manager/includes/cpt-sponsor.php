<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        'show_in_rest'       => false,
        'has_archive'        => false,
        'menu_position'      => 28,
        'supports'           => array( 'title' ),
        'capability_type'    => 'post',
    ) );
} );
