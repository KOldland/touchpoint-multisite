<?php

namespace KH\Editorial\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EditorialTaxonomy {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_taxonomies' ], 0 );
    }

    public static function register_taxonomies() {
        // Top-Line Categories (planner only — post categories use core 'category' taxonomy)
        register_taxonomy( 'editorial_category', [ 'planner_session' ], [
            'labels' => [
                'name'          => 'Editorial Categories',
                'singular_name' => 'Editorial Category',
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
        ] );
    }
}
