<?php
/*
Plugin Name: KH Suggested Reading
Description: Context-aware Suggested Reading block with manual overrides, category/tag logic, and Elementor/shortcode support.
Version: 1.0
Author: Kirsty Hennah
*/

// Core logic
require_once plugin_dir_path(__FILE__) . 'includes/kh-sr-functions.php';


// Shortcode (optional, can be deprecated later)
	require_once plugin_dir_path(__FILE__) . '/includes/kh-sr-shortcode.php';

// Elementor widget (deferred + safe load)
add_action( 'elementor/widgets/widgets_registered', function( $widgets_manager ) {
	if ( ! did_action( 'elementor/loaded' ) ) return;

	require_once plugin_dir_path( __FILE__ ) . 'widgets/class-kh-suggested-reading-widget.php';

	if ( method_exists( $widgets_manager, 'register' ) ) {
		$widgets_manager->register( new \KH_Suggested_Reading_Widget() );
	} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
		$widgets_manager->register_widget_type( new \KH_Suggested_Reading_Widget() );
	}
});

// Enqueue styles
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style(
		'kh-suggested-reading-style',
		plugin_dir_url( __FILE__ ) . 'assets/css/kh-suggested-reading.css',
		[],
		'1.0'
	);
});
