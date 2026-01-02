<?php
namespace KH_SMMA\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SocialSchedulePostType {
    /**
     * Register schedules that act as individual queue jobs.
     */
    public function register() {
        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __( 'Scheduled Posts', 'kh-smma' ),
            'singular_name'      => __( 'Scheduled Post', 'kh-smma' ),
            'add_new_item'       => __( 'Add Scheduled Post', 'kh-smma' ),
            'edit_item'          => __( 'Edit Scheduled Post', 'kh-smma' ),
            'new_item'           => __( 'New Scheduled Post', 'kh-smma' ),
            'view_item'          => __( 'View Scheduled Post', 'kh-smma' ),
            'search_items'       => __( 'Search Scheduled Posts', 'kh-smma' ),
            'not_found'          => __( 'No scheduled posts found', 'kh-smma' ),
            'not_found_in_trash' => __( 'No scheduled posts found in Trash', 'kh-smma' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=kh_smma_account',
            'supports'           => array( 'title', 'editor', 'custom-fields' ),
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'show_in_rest'       => false,
            'rewrite'            => false,
        );

        register_post_type( 'kh_smma_schedule', $args );
    }
}
