<?php
/**
 * Plugin Name: Touchpoint Core
 * Description: Shared functionality for Touchpoint (taxonomies, shortcodes, ACF tweaks).
 * Version: 1.0.0
 * Author: Kris Oldland
 * Text Domain: touchpoint-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* -----------------------------------------------------------
 * 1) Make sure ACF meta box is available (moved from theme)
 * ----------------------------------------------------------*/
if ( ! function_exists( 'touchpointcore_enable_acf_wp_metabox' ) ) {
    add_filter('acf/settings/remove_wp_meta_box', '__return_false');
}

/* -----------------------------------------------------------
 * 2) Content Type taxonomy (moved from theme)
 * ----------------------------------------------------------*/
function touchpointcore_create_content_type_taxonomy() {
    register_taxonomy(
        'content_type',
        'post',
        array(
            'label' => __('Content Type', 'touchpoint-core'),
            'rewrite' => array('slug' => 'content-type'),
            'hierarchical' => false,
            'show_admin_column' => true,
        )
    );
}
add_action('init', 'touchpointcore_create_content_type_taxonomy');

/* -----------------------------------------------------------
 * 3) Styled excerpt shortcode (moved from theme)
 * ----------------------------------------------------------*/
if ( ! shortcode_exists( 'styled_excerpt' ) ) {
    add_shortcode( 'styled_excerpt', function() {
        $full_excerpt = get_the_excerpt();
        $word_limit = 30;
        $words = explode( ' ', $full_excerpt );
        $short_excerpt = $full_excerpt;
        if ( count( $words ) > $word_limit ) {
            $short_excerpt = implode( ' ', array_slice( $words, 0, $word_limit ) ) . '…';
        }
        return '<div class="excerpt-wrapper"><strong>' . esc_html__( 'Summary.', 'touchpoint-core' ) . '</strong><span class="excerpt-text" data-full="' . esc_attr( $full_excerpt ) . '">' . esc_html( $short_excerpt ) . '</span><a href="javascript:void(0);" class="excerpt-toggle" onclick="toggleExcerpt(this)"><em><strong>More</strong></em></a></div>';
    } );
}

// Elementor widget registration
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
        return;
    }

    $widget_file = __DIR__ . '/includes/Elementor/StyledExcerpt_Widget.php';
    if ( file_exists( $widget_file ) ) {
        require_once $widget_file;
    }

    if ( class_exists( '\TouchpointCore\Elementor\StyledExcerpt_Widget' ) ) {
        $widgets_manager->register( new \TouchpointCore\Elementor\StyledExcerpt_Widget() );
    }
} );

/* -----------------------------------------------------------
 * 4) ACF: load field main_category choices (moved from theme)
 * ----------------------------------------------------------*/
add_filter('acf/load_field/name=main_category', function( $field ) {
    $categories = get_categories( ['hide_empty' => false] );
    $choices = [];
    foreach( $categories as $cat ) {
        $choices[$cat->term_id] = $cat->name;
    }
    $field['choices'] = $choices;
    return $field;
});
