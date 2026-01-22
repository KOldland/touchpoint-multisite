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

add_action( 'enqueue_block_editor_assets', function() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}

	wp_add_inline_script(
		'wp-edit-post',
		"window.wp&&wp.domReady(function(){var select=wp.data&&wp.data.select?wp.data.select('core/edit-post'):null;var dispatch=wp.data&&wp.data.dispatch?wp.data.dispatch('core/edit-post'):null;if(!select||!dispatch||!dispatch.toggleEditorPanelEnabled){return;}var enabled=select.isEditorPanelEnabled?select.isEditorPanelEnabled('taxonomy-panel-post_tag'):true;if(enabled===false){dispatch.toggleEditorPanelEnabled('taxonomy-panel-post_tag');}});"
	);

	wp_enqueue_script(
		'touchpoint-editor-seo-sync',
		get_stylesheet_directory_uri() . '/js/editor-seo-sync.js',
		array( 'wp-data', 'wp-dom-ready' ),
		filemtime( get_stylesheet_directory() . '/js/editor-seo-sync.js' ),
		true
	);

	wp_enqueue_script(
		'touchpoint-editor-guidance',
		get_stylesheet_directory_uri() . '/js/editor-guidance.js',
		array( 'wp-block-editor', 'wp-compose', 'wp-data', 'wp-element', 'wp-hooks', 'wp-notices' ),
		filemtime( get_stylesheet_directory() . '/js/editor-guidance.js' ),
		true
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

function touchpointcrm_register_article_template() {
	$post_type = get_post_type_object( 'post' );
	if ( ! $post_type ) {
		return;
	}

	$post_type->template = array(
		array( 'acf/abstract', array() ),
		array( 'core/heading', array(
			'level'   => 2,
			'content' => 'Use H2 for primary sub-headings. H3 for section sub-headings.',
		) ),
		array( 'core/paragraph', array(
			'placeholder' => 'Start your article here...',
		) ),
		array( 'core/pullquote', array(
			'value'    => '"Put a citable stat approx every 500 words..."',
			'citation' => 'Always cite your source',
		) ),
		array( 'core/heading', array(
			'level'   => 2,
			'content' => 'Use H2 to start a new segment of your article.',
		) ),
		array( 'core/paragraph', array(
			'placeholder' => 'Continue your article here. Repeat this flow until complete.',
		) ),
		array( 'core/pullquote', array(
			'value'    => '"Put a citable stat approx every 500 words..."',
			'citation' => 'Always cite your source',
		) ),
		array( 'acf/footnotes', array() ),
	);

	$post_type->template_lock = false;
}
add_action( 'init', 'touchpointcrm_register_article_template', 20 );

add_action( 'init', function() {
	register_post_meta( 'post', 'kss_article_price', array(
		'type'              => 'number',
		'single'            => true,
		'show_in_rest'      => true,
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
		'sanitize_callback' => 'sanitize_text_field',
	) );
	register_post_meta( 'post', 'kss_credit_cost', array(
		'type'              => 'integer',
		'single'            => true,
		'show_in_rest'      => true,
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
		'sanitize_callback' => 'absint',
	) );
} );

add_action( 'init', function() {
	register_taxonomy_for_object_type( 'post_tag', 'post' );
} );

add_action( 'init', function() {
	$seo_meta = array(
		'_khm_seo_title' => 'sanitize_text_field',
		'_khm_seo_description' => 'sanitize_textarea_field',
		'_khm_seo_keywords' => 'sanitize_text_field',
		'_khm_seo_robots' => 'sanitize_text_field',
		'_khm_seo_canonical' => 'esc_url_raw',
		'_khm_seo_focus_keyword' => 'sanitize_text_field',
	);

	foreach ( $seo_meta as $key => $sanitize_callback ) {
		register_post_meta( 'post', $key, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => function() {
				return current_user_can( 'edit_posts' );
			},
			'sanitize_callback' => $sanitize_callback,
		) );
	}
} );

add_action( 'init', function() {
	register_post_meta( 'post', 'authors', array(
		'type'              => 'array',
		'single'            => true,
		'show_in_rest'      => array(
			'schema' => array(
				'type'  => 'array',
				'items' => array(
					'type' => 'integer',
				),
			),
		),
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
		'sanitize_callback' => function( $value ) {
			$value = is_array( $value ) ? $value : array( $value );
			return array_values( array_filter( array_map( 'absint', $value ) ) );
		},
	) );
} );

add_filter( 'wp_insert_post_data', function( $data, $postarr ) {
	if ( empty( $data['post_type'] ) || 'post' !== $data['post_type'] ) {
		return $data;
	}
	if ( empty( $data['post_title'] ) || 'auto-draft' === $data['post_status'] ) {
		return $data;
	}
	if ( empty( $postarr['post_name'] ) && ! empty( $data['post_name'] ) && preg_match( '/^\d+(?:-\d+)?$/', $data['post_name'] ) ) {
		$data['post_name'] = sanitize_title( $data['post_title'] );
	}
	return $data;
}, 10, 2 );

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

	$acf = (array) $request->get_param( 'acf' );
	$raw_authors = $acf['authors'] ?? $acf['field_multi_author_relationship'] ?? null;
	if ( null !== $raw_authors ) {
		$authors = is_array( $raw_authors ) ? $raw_authors : array( $raw_authors );
		$authors = array_values( array_filter( array_map( 'absint', $authors ) ) );
		if ( function_exists( 'update_field' ) ) {
			update_field( 'field_multi_author_relationship', $authors, $post->ID );
		}
		update_post_meta( $post->ID, 'authors', $authors );
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

  var $price = $('#kss_article_price');
  var $credits = $('#kss_credit_cost');
  function setMeta(key, value){
    if (window.wp && wp.data && wp.data.dispatch) {
      var meta = {};
      meta[key] = value;
      wp.data.dispatch('core/editor').editPost({meta: meta});
    }
  }
  $(document).on('change', '#kss_article_price', function(){
    setMeta('kss_article_price', $(this).val());
  });
  $(document).on('change', '#kss_credit_cost', function(){
    var val = parseInt($(this).val(), 10);
    setMeta('kss_credit_cost', isNaN(val) ? 0 : val);
  });
});
JS;
	$script = str_replace( '__LEAD_LABEL__', $lead_label_json, $script );
	wp_add_inline_script( 'jquery', $script );
} );

/* Enqueue Block Editor Styles for Previews */
add_action( 'enqueue_block_editor_assets', function() {
    wp_enqueue_style( 'touchpoint-block-editor', get_template_directory_uri() . '/assets/css/block-editor-style.css', [], '1.0' );
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

/* Enqueue Admin Styles */
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('touchpoint-admin', get_template_directory_uri() . '/assets/css/admin-style.css', [], '1.0');
});

/* Enqueue Login Styles */
add_action('login_enqueue_scripts', function() {
    $logo_url = '';
    if ( has_custom_logo() ) {
        $logo_id = get_theme_mod( 'custom_logo' );
        $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
    }
    // Only set fallback if custom logo is not available
    // Note: Update the path below to point to an actual default logo if needed
    // if ( ! $logo_url ) {
    //     $logo_url = get_template_directory_uri() . '/assets/images/logo.png';
    // }
    
    wp_enqueue_style('touchpoint-login', get_template_directory_uri() . '/assets/css/login-style.css', [], '1.0');
    if ( $logo_url ) {
        wp_add_inline_style('touchpoint-login', "#login h1 a { background-image: url('$logo_url'); }");
    }
});

/* ACF Field Styling */
add_action('acf/input/admin_head', function() {
    echo '<style>
        .acf-field[data-name="overview"] textarea {
            background: #fffef5;
            border-left: 4px solid #6b0b0b;
        }
        .acf-field[data-name="key_points"] .acf-repeater .acf-row {
            border-left: 3px solid #6b0b0b;
            margin-bottom: 10px;
            padding-left: 10px;
        }
        .acf-field label {
            font-weight: 600;
            color: #000;
        }
        .acf-field textarea, .acf-field input[type="text"] {
            border-radius: 4px;
            border: 1px solid #d0d0d0;
        }
    </style>';
});

/* PDF export (native, no ACF dependency) */
function touchpointcrm_get_primary_author( $post_id ) {
	if ( function_exists( 'kh_get_post_authors' ) ) {
		$authors = kh_get_post_authors( $post_id );
		if ( ! empty( $authors ) && is_array( $authors ) ) {
			return $authors[0];
		}
	}
	return null;
}

function touchpointcrm_build_pdf_html( $post_id, $user_label ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$lead_id = function_exists( 'touchpointcrm_get_lead_category_id' ) ? touchpointcrm_get_lead_category_id( $post_id ) : 0;
	$lead_term = $lead_id ? get_term( $lead_id, 'category' ) : null;
	$lead_name = ( $lead_term && ! is_wp_error( $lead_term ) ) ? $lead_term->name : '';

	$featured = get_the_post_thumbnail_url( $post_id, 'large' );
	$title = get_the_title( $post_id );
	$date = get_the_date( 'F j, Y', $post_id );
	$author_post = touchpointcrm_get_primary_author( $post_id );
	$author_name = '';
	$author_title = '';
	$author_company = '';
	$author_bio = '';
	if ( $author_post ) {
		if ( function_exists( 'get_field' ) ) {
			$author_name = get_field( 'author_name', $author_post->ID );
			$author_title = get_field( 'author_title', $author_post->ID );
			$author_company = get_field( 'author_company', $author_post->ID );
			$author_bio = get_field( 'author_bio', $author_post->ID );
		}
		if ( ! $author_name ) {
			$author_name = get_the_title( $author_post );
		}
	}

	$synopsis = get_the_excerpt( $post_id );
	if ( ! $synopsis ) {
		$synopsis = wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
	}

	$content = apply_filters( 'the_content', $post->post_content );

	$author_line = $author_name;
	if ( $author_title ) {
		$author_line .= $author_title ? ' — ' . $author_title : '';
	}
	if ( $author_company ) {
		$author_line .= $author_company ? ', ' . $author_company : '';
	}

	$watermark = $user_label ? 'Personal copy of ' . $user_label : 'Personal copy';

	ob_start();
	?>
	<!doctype html>
	<html>
	<head>
		<meta charset="utf-8">
		<style>
			@page { margin: 1in; }
			body { font-family: DejaVu Sans, Arial, sans-serif; color: #111; }
			.cover { page-break-after: always; }
			.cover .lead { font-size: 12pt; letter-spacing: 0.08em; text-transform: uppercase; color: #6d0b0b; margin: 0 0 12px; }
			.cover .title { font-size: 28pt; font-weight: 700; margin: 12px 0 10px; }
			.cover .meta { font-size: 10pt; color: #555; margin: 0 0 16px; }
			.cover .synopsis { font-size: 10pt; color: #333; margin: 16px 0 0; }
			.cover img { width: 100%; height: auto; margin: 10px 0 16px; }
			.watermark { position: fixed; top: 45%; left: 50%; transform: translate(-50%, -50%) rotate(-25deg); opacity: 0.08; font-size: 36pt; color: #6d0b0b; z-index: 0; }
			.body { column-count: 2; column-gap: 24px; font-size: 10pt; color: #474747; }
			.body p { margin: 0 0 10px; }
			.body h2, .body h3 { column-span: all; color: #111; }
			.wp-block-pullquote { border-left: 3px solid #6d0b0b; padding-left: 12px; color: #333; font-style: italic; margin: 12px 0; }
			.final { page-break-before: always; font-size: 10pt; color: #474747; }
			.final h2 { font-size: 16pt; margin-bottom: 10px; color: #111; }
		</style>
	</head>
	<body>
		<div class="watermark"><?php echo esc_html( $watermark ); ?></div>
		<section class="cover">
			<?php if ( $lead_name ) : ?>
				<div class="lead"><?php echo esc_html( $lead_name ); ?></div>
			<?php endif; ?>
			<?php if ( $featured ) : ?>
				<img src="<?php echo esc_url( $featured ); ?>" alt="">
			<?php endif; ?>
			<div class="title"><?php echo esc_html( $title ); ?></div>
			<?php if ( $author_name || $date ) : ?>
				<div class="meta"><?php echo esc_html( trim( $author_line ) ); ?><?php echo $author_line && $date ? ' · ' : ''; ?><?php echo esc_html( $date ); ?></div>
			<?php endif; ?>
			<?php if ( $synopsis ) : ?>
				<div class="synopsis"><?php echo esc_html( $synopsis ); ?></div>
			<?php endif; ?>
		</section>

		<section class="body">
			<?php echo $content; ?>
		</section>

		<section class="final">
			<h2>Author Bio</h2>
			<?php if ( $author_name ) : ?>
				<p><strong><?php echo esc_html( $author_name ); ?></strong><?php echo $author_title ? ' — ' . esc_html( $author_title ) : ''; ?><?php echo $author_company ? ', ' . esc_html( $author_company ) : ''; ?></p>
			<?php endif; ?>
			<?php if ( $author_bio ) : ?>
				<?php echo wp_kses_post( wpautop( $author_bio ) ); ?>
			<?php else : ?>
				<p><?php echo esc_html__( 'Author bio not available.', 'touchpoint' ); ?></p>
			<?php endif; ?>
		</section>
	</body>
	</html>
	<?php
	return ob_get_clean();
}

function touchpointcrm_render_pdf( $post_id ) {
	if ( ! $post_id ) {
		return;
	}

	if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
		wp_die( esc_html__( 'PDF engine not available.', 'touchpoint' ) );
	}

	$user = wp_get_current_user();
	$user_label = $user && $user->exists() ? $user->display_name : '';
	$html = touchpointcrm_build_pdf_html( $post_id, $user_label );
	if ( ! $html ) {
		wp_die( esc_html__( 'Unable to build PDF.', 'touchpoint' ) );
	}

	$options = new \Dompdf\Options();
	$options->set( 'isRemoteEnabled', true );
	$dompdf = new \Dompdf\Dompdf( $options );
	$dompdf->loadHtml( $html );
	$dompdf->setPaper( 'A4', 'portrait' );
	$dompdf->render();

	$filename = sanitize_file_name( get_the_title( $post_id ) . '.pdf' );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . $filename . '"' );
	echo $dompdf->output();
	exit;
}

add_action( 'template_redirect', function() {
	if ( empty( $_GET['kh_pdf'] ) ) {
		return;
	}
	$post_id = get_queried_object_id();
	if ( ! $post_id && ! empty( $_GET['p'] ) && is_numeric( $_GET['p'] ) ) {
		$post_id = (int) $_GET['p'];
	}
	if ( $post_id ) {
		touchpointcrm_render_pdf( $post_id );
	}
} );

/* Social Strip meta (native, no ACF dependency) */
add_action( 'add_meta_boxes', function() {
	add_meta_box(
		'touchpoint-social-strip',
		__( 'eCommerce', 'touchpoint' ),
		function( $post ) {
			wp_nonce_field( 'touchpoint_social_strip_save', 'touchpoint_social_strip_nonce' );
			$price = get_post_meta( $post->ID, 'kss_article_price', true );

			echo '<p><label for="kss_article_price">' . esc_html__( 'Article Price (£)', 'touchpoint' ) . '</label></p>';
			echo '<input type="number" step="0.01" min="0" class="widefat" id="kss_article_price" name="kss_article_price" value="' . esc_attr( $price ) . '">';

			$credit_cost = get_post_meta( $post->ID, 'kss_credit_cost', true );

			echo '<p style="margin-top:12px;"><label for="kss_credit_cost">' . esc_html__( 'Download Credit Cost', 'touchpoint' ) . '</label></p>';
			echo '<input type="number" step="1" min="0" class="widefat" id="kss_credit_cost" name="kss_credit_cost" value="' . esc_attr( $credit_cost ) . '">';

			echo '<p style="margin-top:12px;">' . esc_html__( 'Gifting uses the same price as Article Price.', 'touchpoint' ) . '</p>';
		},
		'post',
		'side',
		'default'
	);
} );

add_action( 'save_post', function( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['touchpoint_social_strip_nonce'] ) || ! wp_verify_nonce( $_POST['touchpoint_social_strip_nonce'], 'touchpoint_social_strip_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$price = isset( $_POST['kss_article_price'] ) ? sanitize_text_field( wp_unslash( $_POST['kss_article_price'] ) ) : '';
	$credit_cost = isset( $_POST['kss_credit_cost'] ) ? absint( $_POST['kss_credit_cost'] ) : 0;

	if ( '' !== $price ) {
		update_post_meta( $post_id, 'kss_article_price', $price );
	} else {
		delete_post_meta( $post_id, 'kss_article_price' );
	}

	if ( $credit_cost > 0 ) {
		update_post_meta( $post_id, 'kss_credit_cost', $credit_cost );
	} else {
		delete_post_meta( $post_id, 'kss_credit_cost' );
	}

	delete_post_meta( $post_id, 'kss_show_gifting' );
	delete_post_meta( $post_id, 'kss_gift_price' );
	delete_post_meta( $post_id, 'kss_pdf_upload' );
}, 20 );
	
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
