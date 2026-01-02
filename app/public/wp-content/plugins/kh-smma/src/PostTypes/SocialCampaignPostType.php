<?php
namespace KH_SMMA\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SocialCampaignPostType {
    /**
     * Register campaigns that group multiple scheduled jobs and creatives.
     */
    public function register() {
        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __( 'Social Campaigns', 'kh-smma' ),
            'singular_name'      => __( 'Social Campaign', 'kh-smma' ),
            'add_new_item'       => __( 'Add Social Campaign', 'kh-smma' ),
            'edit_item'          => __( 'Edit Social Campaign', 'kh-smma' ),
            'new_item'           => __( 'New Social Campaign', 'kh-smma' ),
            'view_item'          => __( 'View Social Campaign', 'kh-smma' ),
            'search_items'       => __( 'Search Social Campaigns', 'kh-smma' ),
            'not_found'          => __( 'No social campaigns found', 'kh-smma' ),
            'not_found_in_trash' => __( 'No social campaigns found in Trash', 'kh-smma' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=kh_smma_account',
            'supports'           => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'show_in_rest'       => false,
            'rewrite'            => false,
        );

        register_post_type( 'kh_smma_campaign', $args );
    }
}
