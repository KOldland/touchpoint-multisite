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
	
/* Lead category (native, no ACF dependency) */
function touchpointcrm_get_lead_category_id( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id ) {
		return 0;
	}

	$lead_id = (int) get_post_meta( $post_id, '_lead_category_id', true );
	if ( $lead_id ) {
		return $lead_id;
	}

	$cats = wp_get_post_categories( $post_id );
	return ! empty( $cats ) ? (int) $cats[0] : 0;
}

function touchpointcrm_render_category_row( $term, $selected_ids, $lead_id, $depth ) {
	$indent = str_repeat( '&nbsp;&nbsp;&nbsp;', $depth );
	$checked = in_array( $term->term_id, $selected_ids, true ) ? ' checked' : '';
	$lead_checked = ( (int) $lead_id === (int) $term->term_id ) ? ' checked' : '';

	echo '<li>';
	echo '<label style="display:inline-flex;align-items:center;gap:6px;">';
	echo '<input type="checkbox" name="tax_input[category][]" value="' . esc_attr( $term->term_id ) . '"' . $checked . ' />';
	echo $indent . esc_html( $term->name );
	echo '</label>';
	echo '<label style="margin-left:12px;">';
	echo '<input type="radio" name="touchpoint_lead_category" value="' . esc_attr( $term->term_id ) . '"' . $lead_checked . ' /> ';
	echo esc_html__( 'Lead', 'touchpoint' );
	echo '</label>';
	echo '</li>';
}

add_action( 'add_meta_boxes', function() {
	remove_meta_box( 'categorydiv', 'post', 'side' );

	add_meta_box(
		'touchpoint-categories',
		__( 'Categories', 'touchpoint' ),
		function( $post ) {
	wp_nonce_field( 'touchpoint_lead_category_save', 'touchpoint_lead_category_nonce' );
	$ajax_nonce = wp_create_nonce( 'touchpoint_lead_category_save' );
	echo '<input type="hidden" id="touchpoint-lead-category-nonce" value="' . esc_attr( $ajax_nonce ) . '" />';
	echo '<div class="touchpoint-category-status" id="touchpoint-category-status" style="margin-top:6px;color:#b32d2e;"></div>';
			$selected_ids = wp_get_post_categories( $post->ID );
			$lead_id = (int) get_post_meta( $post->ID, '_lead_category_id', true );
			$terms = get_terms( array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'parent'     => 0,
			) );

			echo '<p class="description">' . esc_html__( 'Choose categories and mark one as the lead.', 'touchpoint' ) . '</p>';
			echo '<ul class="touchpoint-categories-list" style="margin:0;padding:0;list-style:none;">';
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					touchpointcrm_render_category_row( $term, $selected_ids, $lead_id, 0 );
					$children = get_terms( array(
						'taxonomy'   => 'category',
						'hide_empty' => false,
						'parent'     => $term->term_id,
					) );
					if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
						foreach ( $children as $child ) {
							touchpointcrm_render_category_row( $child, $selected_ids, $lead_id, 1 );
						}
					}
				}
			} else {
				echo '<li>' . esc_html__( 'No categories found.', 'touchpoint' ) . '</li>';
			}
			echo '</ul>';
			echo '<div class="touchpoint-category-add" style="margin-top:8px;">';
			echo '<input type="text" id="touchpoint-new-category" class="widefat" placeholder="' . esc_attr__( 'Add new category', 'touchpoint' ) . '" />';
			echo '<button type="button" class="button button-link" id="touchpoint-add-category" style="margin-top:4px;">' . esc_html__( 'Add Category', 'touchpoint' ) . '</button>';
			echo '</div>';
		},
		'post',
		'side',
		'default'
	);
} );

add_action( 'enqueue_block_editor_assets', function() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}

	wp_add_inline_script(
		'wp-edit-post',
		"window.wp && wp.domReady(function(){var dispatch=wp.data&&wp.data.dispatch?wp.data.dispatch('core/edit-post'):null;if(dispatch&&dispatch.removeEditorPanel){dispatch.removeEditorPanel('taxonomy-panel-category');}});"
	);
} );

add_action( 'init', function() {
	register_post_meta( 'post', '_lead_category_id', array(
		'type'              => 'integer',
		'single'            => true,
		'show_in_rest'      => true,
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
		'sanitize_callback' => 'absint',
	) );
} );

add_action( 'rest_after_insert_post', function( $post, $request, $creating ) {
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$cat_ids = array_values( array_filter( array_map( 'absint', (array) $request->get_param( 'categories' ) ) ) );
	if ( ! empty( $cat_ids ) ) {
		wp_set_post_categories( $post->ID, $cat_ids );
	}

	$meta = (array) $request->get_param( 'meta' );
	if ( ! empty( $meta['_lead_category_id'] ) ) {
		update_post_meta( $post->ID, '_lead_category_id', (int) $meta['_lead_category_id'] );
	}
}, 10, 3 );

add_action( 'save_post', function( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['touchpoint_lead_category_nonce'] ) || ! wp_verify_nonce( $_POST['touchpoint_lead_category_nonce'], 'touchpoint_lead_category_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$selected = array();
	if ( isset( $_POST['tax_input']['category'] ) && is_array( $_POST['tax_input']['category'] ) ) {
		$selected = array_values( array_filter( array_map( 'intval', $_POST['tax_input']['category'] ) ) );
	} elseif ( isset( $_POST['post_category'] ) && is_array( $_POST['post_category'] ) ) {
		$selected = array_values( array_filter( array_map( 'intval', $_POST['post_category'] ) ) );
	}
	$lead_id = isset( $_POST['touchpoint_lead_category'] ) ? (int) $_POST['touchpoint_lead_category'] : 0;

	if ( $lead_id && ! in_array( $lead_id, $selected, true ) ) {
		$selected[] = $lead_id;
	}

	if ( ! empty( $selected ) ) {
		wp_set_post_categories( $post_id, $selected );
		if ( ! $lead_id ) {
			$lead_id = (int) $selected[0];
		}
		update_post_meta( $post_id, '_lead_category_id', $lead_id );
	} else {
		wp_set_post_categories( $post_id, array() );
		delete_post_meta( $post_id, '_lead_category_id' );
	}
}, 20 );

add_action( 'wp_ajax_touchpoint_add_category', function() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'touchpoint' ) ), 403 );
	}
	check_ajax_referer( 'touchpoint_lead_category_save', 'nonce' );

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( '' === $name ) {
		wp_send_json_error( array( 'message' => __( 'Category name is required.', 'touchpoint' ) ), 400 );
	}

	$term = wp_insert_term( $name, 'category' );
	if ( is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => $term->get_error_message() ), 400 );
	}

	$term_obj = get_term( $term['term_id'], 'category' );
	if ( ! $term_obj || is_wp_error( $term_obj ) ) {
		wp_send_json_error( array( 'message' => __( 'Unable to load category.', 'touchpoint' ) ), 400 );
	}

	wp_send_json_success( array(
		'id'   => $term_obj->term_id,
		'name' => $term_obj->name,
	) );
} );

add_action( 'enqueue_block_editor_assets', function() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script( 'jquery' );

	$lead_label = esc_js( __( 'Lead', 'touchpoint' ) );
	$lead_label_json = wp_json_encode( $lead_label );
	$script = <<<'JS'
jQuery(function($){
  var $list = $('.touchpoint-categories-list');
  var $status = $('#touchpoint-category-status');
  function showError(msg){$status.text(msg||'Unable to add category.');}
  function getSelected(){
    var ids = [];
    $list.find('input[type=checkbox]:checked').each(function(){
      ids.push(parseInt($(this).val(), 10));
    });
    return ids;
  }
  function setCategories(ids){
    if (window.wp && wp.data && wp.data.dispatch) {
      wp.data.dispatch('core/editor').editPost({categories: ids});
    }
  }
  function setLead(id){
    if (window.wp && wp.data && wp.data.dispatch) {
      wp.data.dispatch('core/editor').editPost({meta: {_lead_category_id: id}});
    }
  }
  $list.on('change', 'input[type=checkbox]', function(){
    setCategories(getSelected());
  });
  $list.on('change', 'input[type=radio]', function(){
    var id = parseInt($(this).val(), 10);
    if (!isNaN(id)) {
      var $box = $list.find('input[type=checkbox][value=' + id + ']');
      if ($box.length && !$box.prop('checked')) {
        $box.prop('checked', true);
        setCategories(getSelected());
      }
      setLead(id);
    }
  });
  $('#touchpoint-add-category').on('click', function(){
    var name = $.trim($('#touchpoint-new-category').val());
    $status.text('');
    if(!name){showError('Category name is required.');return;}
    var nonce = $('#touchpoint-lead-category-nonce').val();
    var data = {action:'touchpoint_add_category', name:name, nonce:nonce};
    $.post(ajaxurl, data).done(function(resp){
      if(!resp || !resp.success){
        showError(resp && resp.data && resp.data.message ? resp.data.message : 'Unable to add category.');
        return;
      }
      var id = resp.data.id;
      var label = $('<label/>', {css:{display:'inline-flex',alignItems:'center',gap:'6px'}});
      label.append($('<input/>', {type:'checkbox', name:'tax_input[category][]', value:id, checked:true}));
      label.append(document.createTextNode(' '+resp.data.name));
      var leadLabel = $('<label/>', {css:{marginLeft:'12px'}});
      leadLabel.append($('<input/>', {type:'radio', name:'touchpoint_lead_category', value:id}));
      leadLabel.append(' ' + __LEAD_LABEL__);
      var li = $('<li/>');
      li.append(label).append(leadLabel);
      $list.append(li);
      $('#touchpoint-new-category').val('');
      setCategories(getSelected());
    }).fail(function(xhr){
      var msg = 'Unable to add category.';
      if(xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message){
        msg = xhr.responseJSON.data.message;
      }
      showError(msg);
    });
  });
});
JS;
	$script = str_replace( '__LEAD_LABEL__', $lead_label_json, $script );
	wp_add_inline_script( 'jquery', $script );
} );


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
		wp_enqueue_style( 'lora-font', 'https://fonts.googleapis.com/css2?family=Lora:wght@400;700&display=swap', false );
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
				$lead_id = function_exists( 'touchpointcrm_get_lead_category_id' ) ? touchpointcrm_get_lead_category_id( get_the_ID() ) : 0;
				if ( $lead_id ) {
					$term = get_term( $lead_id, 'category' );
					if ( $term && ! is_wp_error( $term ) ) {
						$html .= '<div class="lead-category"><a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a></div>';
					}
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
