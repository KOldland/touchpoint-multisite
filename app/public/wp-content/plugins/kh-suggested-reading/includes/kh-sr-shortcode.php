<?php
if ( ! defined( 'ABSPATH' ) ) exit;

	function kh_suggested_reading_shortcode( $atts = [] ) {
		$atts = shortcode_atts([
			'position' => 'top', // 'top', 'footer', or 'both'
			'title'    => 'Suggested Reading',
		], $atts);
		
		$posts = kh_suggested_reading_get_posts( get_the_ID() );
		if ( empty( $posts ) ) return '';
		
		$args = [
			'posts'    => $posts,
			'position' => $atts['position'],
			'title'    => $atts['title'],
		];
		
		set_query_var( 'kh_sr_args', $args );
		
		ob_start();
		get_template_part( 'partials/kh-suggested-reading-dual' );
		return ob_get_clean();
	}
	
	add_shortcode( 'kh_suggested_reading', 'kh_suggested_reading_shortcode' );

