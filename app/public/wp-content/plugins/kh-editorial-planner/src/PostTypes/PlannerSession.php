<?php

namespace KH\Planner\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PlannerSession {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_type' ], 0 );
    }

    public static function register_post_type() {
        $args = array(
            'label'           => 'Planner Sessions',
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false, // Handled by Mission Control Admin UI
            'supports'        => array( 'title', 'editor', 'author', 'custom-fields' ),
            'capability_type' => 'post',
            'show_in_rest'    => true,
            'menu_icon'       => 'dashicons-welcome-write-blog',
        );

        register_post_type( 'planner_session', $args );

        // Register meta fields for REST API
        $meta_fields = array(
            'audience', 
            'angle', 
            'key_messages', 
            'framework', 
            'geo', 
            'tone', 
            'word_count', 
            'status', 
            'created_by', 
            'topics', 
            'portfolio',
            'pillar',
            'pillar_slug',
            'audience_slug',
        );

        foreach ( $meta_fields as $field ) {
            register_post_meta( 'planner_session', $field, array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'string',
                'auth_callback' => function() {
                    return current_user_can( 'edit_posts' );
                }
            ) );
        }
    }
}
