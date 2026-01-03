<?php
	/**
	* Theme functions and definitions
	*
	* @package TouchpointCRM
	*/
	


	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly.
	}
	
	define( 'TOUCHPOINT_VERSION', '1.0.0' );
	
	if ( ! isset( $content_width ) ) {
		$content_width = 800; // Pixels.
	}
	
	if ( ! function_exists( 'touchpointcrm_setup' ) ) {
		/**
		* Set up theme support.
		*/
		function touchpointcrm_setup() {
			
			// Register navigation menus.
			register_nav_menus( [
				'primary' => esc_html__( 'Primary Menu', 'touchpoint' ),
				'footer'  => esc_html__( 'Footer Menu', 'touchpoint' ),
			] );
			
			// Theme support options.
			add_theme_support( 'post-thumbnails' );
			add_theme_support( 'automatic-feed-links' );
			add_theme_support( 'title-tag' );
			add_theme_support( 'html5', [
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'script',
				'style',
			] );
			add_theme_support( 'custom-logo', [
				'height'      => 100,
				'width'       => 350,
				'flex-height' => true,
				'flex-width'  => true,
			] );
			add_theme_support( 'align-wide' );
			add_theme_support( 'responsive-embeds' );
			add_theme_support( 'editor-styles' );
			add_editor_style( 'editor-styles.css' );
			
			// WooCommerce support
			add_theme_support( 'woocommerce' );
			add_theme_support( 'wc-product-gallery-zoom' );
			add_theme_support( 'wc-product-gallery-lightbox' );
			add_theme_support( 'wc-product-gallery-slider' );
			
					add_theme_support('acf-blocks');
			
					// Localisation support
					load_theme_textdomain( 'touchpoint', get_stylesheet_directory() . '/languages' );
			
				}	}
	
	add_action( 'after_setup_theme', 'touchpointcrm_setup' );


/**
 * Safe wrappers so the theme doesn't depend on Hello Elementor being present.
 * - touchpoint_elementor_setting() uses hello_elementor_get_setting() if available
 * - touchpoint_apply_hello_filter() applies a hello filter only if present
 * - touchpoint_hello_header_class() provides fallback for hello_get_header_layout_class()
 * - touchpoint_hello_header_display() provides fallback for hello_get_header_display()
 * - touchpoint_show_or_hide() provides fallback for hello_show_or_hide()
 */

if ( ! function_exists( 'touchpoint_elementor_setting' ) ) {
	function touchpoint_elementor_setting( $name, $default = '' ) {
		if ( function_exists( 'hello_elementor_get_setting' ) ) {
			return hello_elementor_get_setting( $name );
		}
		return $default;
	}
}

if ( ! function_exists( 'touchpoint_apply_hello_filter' ) ) {
	function touchpoint_apply_hello_filter( $filter_name, $value ) {
		if ( has_filter( $filter_name ) ) {
			return apply_filters( $filter_name, $value );
		}
		return $value;
	}
}

if ( ! function_exists( 'touchpoint_hello_header_class' ) ) {
	function touchpoint_hello_header_class() {
		if ( function_exists( 'hello_get_header_layout_class' ) ) {
			return hello_get_header_layout_class();
		}
		// return a sensible default class name used in your theme markup
		return 'touchpoint-header-default';
	}
}

if ( ! function_exists( 'touchpoint_hello_header_display' ) ) {
    function touchpoint_hello_header_display() {
        if ( function_exists( 'hello_get_header_display' ) ) {
            return hello_get_header_display();
        }
        return true; // sensible default
    }
}

if ( ! function_exists( 'touchpoint_show_or_hide' ) ) {
    function touchpoint_show_or_hide( $option, $default = 'show' ) {
        if ( function_exists( 'hello_show_or_hide' ) ) {
            return hello_show_or_hide( $option );
        }
        // A simple default implementation
        $setting = touchpoint_elementor_setting( $option, true );
        return $setting ? 'show' : 'hide';
    }
}


// Re-enable default WordPress category meta box (useful for ACF taxonomy fields)
	add_filter('acf/settings/remove_wp_meta_box', '__return_false');

// Re-enable default WordPress category meta box (useful for ACF taxonomy fields)
	

/* DEque Hello and enque Touch */
function redirect_hello_elementor_assets() {
	// Dequeue Hello Elementor scripts/styles only if registered/enqueued
	if ( function_exists( 'wp_script_is' ) && ( wp_script_is( 'hello-elementor-main-js', 'registered' ) || wp_script_is( 'hello-elementor-main-js', 'enqueued' ) ) ) {
		wp_dequeue_script( 'hello-elementor-main-js' );
	}
	if ( function_exists( 'wp_style_is' ) && ( wp_style_is( 'hello-elementor-style', 'registered' ) || wp_style_is( 'hello-elementor-style', 'enqueued' ) ) ) {
		wp_dequeue_style( 'hello-elementor-style' );
	}

	// Enqueue Touchpoint's own script reliably
	wp_enqueue_script(
		'touchpointcrm-main-js',
		get_stylesheet_directory_uri() . '/js/main.js',
		array(),
		filemtime( get_stylesheet_directory() . '/js/main.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'redirect_hello_elementor_assets', 11 );



	function touchpointcrm_enqueue_styles() {
		// Dequeue Hello Elementor's style.css
		wp_dequeue_style('hello-elementor-style'); 
		
		// Enqueue TouchpointCRM's style.css
		wp_enqueue_style(
			'touchpointcrm-style',
			get_stylesheet_uri(),
			[],
			filemtime(get_stylesheet_directory() . '/style.css'),
			'all'
		);
	}
		
	add_action('wp_enqueue_scripts', 'touchpointcrm_enqueue_styles', 10); // Priority 10, load first
	
/* Load DM-Sans */
	function touchpointcrm_fonts() {
		// Enqueue the necessary fonts from Google Fonts
		wp_enqueue_style( 'barlow-font', 'https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;600&display=swap', false );
		wp_enqueue_style( 'dm-sans-font', 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&display=swap', false );
		wp_enqueue_style( 'inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap', false );
	}
	add_action( 'wp_enqueue_scripts', 'touchpointcrm_fonts',  5 ); // Priority 5, load after theme styles

/* ACF abstract block */
	add_action('acf/init', 'register_abstract_block');
	function register_abstract_block() {
		if( function_exists('acf_register_block_type') ) {
			acf_register_block_type(array(
				'name'              => 'abstract',
				'title'             => __('Abstract Block'),
				'description'       => __('Executive summary content for gated content'),
				'render_template'   => 'template-parts/blocks/abstract/abstract.php',
				'category'          => 'formatting',
				'icon'              => 'excerpt-view',
				'mode'              => 'edit',
				'keywords'          => array( 'abstract', 'summary', 'overview' ),
				'supports'          => [ 'align' => false ]
			));
		}
	}

/* ACF fields for abstract block */
	add_action( 'acf/init', 'register_abstract_block_fields' );
	function register_abstract_block_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( array(
			'key' => 'group_abstract_block',
			'title' => 'Abstract Block',
			'fields' => array(
				array(
					'key' => 'field_abstract_overview',
					'label' => 'Overview',
					'name' => 'overview',
					'type' => 'textarea',
					'rows' => 4,
				),
				array(
					'key' => 'field_abstract_context',
					'label' => 'Context',
					'name' => 'context',
					'type' => 'textarea',
					'rows' => 4,
				),
				array(
					'key' => 'field_abstract_application',
					'label' => 'Application',
					'name' => 'application',
					'type' => 'textarea',
					'rows' => 4,
				),
				array(
					'key' => 'field_abstract_key_points',
					'label' => 'Observations',
					'name' => 'key_points',
					'type' => 'repeater',
					'layout' => 'table',
					'button_label' => 'Add Observation',
					'sub_fields' => array(
						array(
							'key' => 'field_abstract_key_points_bullet',
							'label' => 'Bullet',
							'name' => 'bullet',
							'type' => 'text',
						),
					),
				),
			),
			'location' => array(
				array(
					array(
						'param' => 'block',
						'operator' => '==',
						'value' => 'acf/abstract',
					),
				),
			),
		) );
	}

/* ACF fields for footnotes block */
	add_action( 'acf/init', 'register_footnotes_block_fields' );
	function register_footnotes_block_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( array(
			'key' => 'group_footnotes_block',
			'title' => 'Footnotes Block',
			'fields' => array(
				array(
					'key' => 'field_footnotes_items',
					'label' => 'Footnotes',
					'name' => 'footnotes',
					'type' => 'repeater',
					'layout' => 'row',
					'button_label' => 'Add Footnote',
					'sub_fields' => array(
						array(
							'key' => 'field_footnotes_reference_text',
							'label' => 'Reference Text',
							'name' => 'reference_text',
							'type' => 'text',
						),
						array(
							'key' => 'field_footnotes_reference_link',
							'label' => 'Reference Link',
							'name' => 'reference_link',
							'type' => 'url',
						),
					),
				),
			),
			'location' => array(
				array(
					array(
						'param' => 'block',
						'operator' => '==',
						'value' => 'acf/footnotes',
					),
				),
			),
		) );
	}

/* ACF fields for multi-author selection */
	add_action( 'acf/init', 'register_multi_author_fields' );
	function register_multi_author_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( array(
			'key' => 'group_multi_author',
			'title' => 'Authors',
			'position' => 'side',
			'style' => 'seamless',
			'fields' => array(
				array(
					'key' => 'field_multi_author_relationship',
					'label' => 'Authors',
					'name' => 'authors',
					'type' => 'relationship',
					'post_type' => array( 'multi_author' ),
					'filters' => array( 'search' ),
					'return_format' => 'object',
					'min' => 0,
					'max' => 0,
				),
			),
			'location' => array(
				array(
					array(
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'post',
					),
				),
			),
		) );
	}

/* ACF abstract block short code */
	function render_abstract_shortcode() {
		ob_start();
		get_template_part('template-parts/blocks/abstract/abstract');
		return ob_get_clean();
	}
	add_shortcode('abstract_block', 'render_abstract_shortcode');

/* ACF Footnote block */	
	add_action('acf/init', function() {
		if( function_exists('acf_register_block_type') ) {
			acf_register_block_type([
				'name'            => 'footnotes',
				'title'           => __('Footnotes'),
				'render_template' => 'template-parts/blocks/footnotes/footnotes.php',
				'category'        => 'formatting',
				'icon'            => 'editor-ol',
				'mode'            => 'edit',
			]);
		}
	});

/* Footnotes block short code */
	function render_footnotes_shortcode() {
		ob_start();
		get_template_part('template-parts/blocks/footnotes/footnotes');
		return ob_get_clean();
	}
	add_shortcode('footnotes_block', 'render_footnotes_shortcode');

	

/* Post-Meta Block */
		function render_post_meta_block( $atts ) {
			if ( ! function_exists( 'get_field' ) ) {
				return '<!-- ACF not available -->';
			}
			
			$atts = shortcode_atts(
				[
					'show' => 'category,title,author,date',
				],
				(array) $atts,
				'post_meta_block'
			);
			
			// Ensure $show is always an array
			$show = is_array( $atts['show'] ) ? $atts['show'] : explode( ',', (string) $atts['show'] );
			$show = array_map( 'trim', $show ); // trim spaces
			$html = '<div class="post-meta">';
			
			// CATEGORY
			if ( in_array( 'category', $show, true ) ) {
				$term = get_field( 'lead_category' );
				if ( $term instanceof WP_Term ) {
					$html .= '<div class="lead-category"><a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a></div>';
				}
			}
			
			// TITLE
			if ( in_array( 'title', $show, true ) ) {
				$html .= '<h1 class="post-title">' . esc_html( get_the_title() ) . '</h1>';
			}
			
			// AUTHOR(S)
			if ( in_array( 'author', $show, true ) ) {
				$authors = function_exists( 'kh_get_post_authors' ) ? kh_get_post_authors( get_the_ID() ) : get_field( 'authors' );
				if ( ! empty( $authors ) && is_array( $authors ) ) {
					$names = array_map( function ( $post ) {
						$name = function_exists( 'get_field' ) ? get_field( 'author_name', $post->ID ) : '';
						return $name ? $name : get_the_title( $post );
					}, $authors );
					
					if ( count( $names ) > 2 ) {
						$last           = array_pop( $names );
						$authors_string = implode( ', ', $names ) . ', and ' . $last;
					} else {
						$authors_string = implode( ' and ', $names );
					}
					
					$html .= '<div class="author-meta">By ' . esc_html( $authors_string ) . '</div>';
				}
			}
			
			// DATE
			if ( in_array( 'date', $show, true ) ) {
				$date  = get_the_date();
				$html .= '<div class="meta-date">' . esc_html( $date ) . '</div>';
			}
			
			$html .= '</div>'; // Close .post-meta
			return $html;
		}
		add_shortcode( 'post_meta_block', 'render_post_meta_block' );
		
/* Fuck Grammerly*/
	add_action( 'admin_enqueue_scripts', function() {
		if ( is_admin() ) {
			wp_dequeue_script( 'grammarly' );
			wp_dequeue_script( 'grammarly-extension' );
		}
	}, 100 );
	
	
// Temporarily disable debug.log clearing to preserve diagnostics.
// add_action('shutdown', function() {
// 	if ( defined('WP_DEBUG') && WP_DEBUG && current_user_can('administrator') ) {
// 		@file_put_contents( WP_CONTENT_DIR . '/debug.log', '' );
// 	}
// });

// Elementor widgets for theme shortcodes
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
		return;
	}

	$widgets = array(
		'\Touchpoint\Elementor\Abstract_Widget'  => get_theme_file_path( 'inc/elementor/Abstract_Widget.php' ),
		'\Touchpoint\Elementor\Footnotes_Widget' => get_theme_file_path( 'inc/elementor/Footnotes_Widget.php' ),
		'\Touchpoint\Elementor\PostMeta_Widget'  => get_theme_file_path( 'inc/elementor/PostMeta_Widget.php' ),
		'\Touchpoint\Elementor\TestSlots_Widget' => get_theme_file_path( 'inc/elementor/TestSlots_Widget.php' ),
	);

	foreach ( $widgets as $class => $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
		}
		if ( class_exists( $class ) ) {
			$widgets_manager->register( new $class() );
		}
	}
} );

/* Elementor Ads Quick Test*/
	add_shortcode('kh_test_slots', function() {
		ob_start();
		$slots = [
			'exit_overlay',
			'footer',
			'header',
			'popup',
			'sidebar1',
			'sidebar2',
			'ticker',
			'slide_in',
		];
		
		foreach ($slots as $slot) {
			echo "<div style='border:1px dashed #ccc;margin:10px;padding:10px;'>";
			echo "<h4>Slot: $slot</h4>";
			kh_ad_manager_render_ad_for_slot_in_context($slot);
			echo "</div>";
		}
		return ob_get_clean();
	});
