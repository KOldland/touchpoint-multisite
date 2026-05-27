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
        // 1. Editorial Frameworks (Post Types)
        register_taxonomy( 'editorial_framework', [ 'planner_session', 'post' ], [
            'labels' => [
                'name'          => 'Article Frameworks',
                'singular_name' => 'Framework',
            ],
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
        ] );

        // 2. Top-Line Categories
        register_taxonomy( 'editorial_category', [ 'planner_session', 'post' ], [
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
