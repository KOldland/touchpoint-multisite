<?php
add_action('init', function() {
    register_taxonomy('ad-slot', 'ad_unit', [
        'label'              => 'Ad Slots',
        'labels'             => [
            'name'          => 'Ad Slots',
            'singular_name' => 'Ad Slot',
            'search_items'  => 'Search Ad Slots',
            'all_items'     => 'All Slots',
            'edit_item'     => 'Edit Slot',
            'update_item'   => 'Update Slot',
            'add_new_item'  => 'Add New Slot',
            'new_item_name' => 'New Slot Name',
            'menu_name'     => 'Ad Slots',
        ],
        'public'             => false,
        'show_ui'            => true,
        'hierarchical'       => true,
        'show_in_quick_edit' => false,
        'show_in_rest'       => false,
    ]);
});
