<?php
add_action('init', function() {
    register_post_type('ad_unit', [
        'label'               => 'Ads',
        'labels'              => [
            'name'               => 'Ads',
            'singular_name'      => 'ad_unit',
            'add_new_item'       => 'Add New Ad',
            'edit_item'          => 'Edit Ad',
            'new_item'           => 'New Ad',
            'view_item'          => 'View Ad',
            'search_items'       => 'Search Ads',
            'not_found'          => 'No ads found.',
            'not_found_in_trash' => 'No ads found in Trash.',
        ],
        'public'              => false,           // Not shown on frontend queries
        'show_ui'             => true,            // Show in admin
        'show_in_menu'        => 'ad-studio',     // Nested under Ad Studio top-level menu
        'menu_icon'           => 'dashicons-megaphone',
        'supports'            => ['title'],
        'taxonomies'          => ['category', 'ad-slot'],
        'has_archive'         => false,
        'show_in_rest'        => true,            // Enable REST API for client portal (Phase 2)
        'capability_type'     => 'post',
    ]);
});
