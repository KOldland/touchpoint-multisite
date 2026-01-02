<?php
	/**
	* The site's entry point.
	*
	* Loads the relevant template part,
	* the loop is executed (when needed) by the relevant template part.
	*
	* @package TouchpointCRM
	*/
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly.
	}

	get_header();
	
	// Check if Elementor theme exists and is loaded
	$is_elementor_theme_exist = function_exists( 'elementor_theme_do_location' );

	// Singular content
	if ( is_singular() ) {
		// Check for single post template part, fallback if Elementor not found
		if ( ! $is_elementor_theme_exist || ! elementor_theme_do_location( 'single' ) ) {
			get_template_part( 'template-parts/single' );
		}
	} 
	// Archive or Home page
	elseif ( is_home() || is_archive() ) {
		// Check for archive template part, fallback if Elementor not found
		if ( ! $is_elementor_theme_exist || ! elementor_theme_do_location( 'archive' ) ) {
			get_template_part( 'template-parts/archive' );
		}
	} 
	// Search page
	elseif ( is_search() ) {
		// Check for search template part, fallback if Elementor not found
		if ( ! $is_elementor_theme_exist || ! elementor_theme_do_location( 'search' ) ) {
			get_template_part( 'template-parts/search' );
		}
	} 
	// 404 page
	else {
		// Fallback for 404 template part if Elementor not found
		if ( ! $is_elementor_theme_exist || ! elementor_theme_do_location( 'single' ) ) {
			get_template_part( 'template-parts/404' );
		}
	}

	get_footer();
