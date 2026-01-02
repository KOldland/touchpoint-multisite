<?php
namespace KH_SMMA\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SocialAccountPostType {
    /**
     * Register post type representing connected social providers (Meta, LinkedIn, etc.).
     */
    public function register() {
        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __( 'Social Accounts', 'kh-smma' ),
            'singular_name'      => __( 'Social Account', 'kh-smma' ),
            'add_new_item'       => __( 'Add Social Account', 'kh-smma' ),
            'edit_item'          => __( 'Edit Social Account', 'kh-smma' ),
            'new_item'           => __( 'New Social Account', 'kh-smma' ),
            'view_item'          => __( 'View Social Account', 'kh-smma' ),
            'search_items'       => __( 'Search Social Accounts', 'kh-smma' ),
            'not_found'          => __( 'No social accounts found', 'kh-smma' ),
            'not_found_in_trash' => __( 'No social accounts found in Trash', 'kh-smma' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-share',
            'supports'            => array( 'title', 'custom-fields' ),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'show_in_rest'        => false,
            'rewrite'             => false,
        );

        register_post_type( 'kh_smma_account', $args );
    }
}
