<?php
/*
Plugin Name: Multiple Authors
Description: Adds multi-author bios with greyscaled images, roles, and beautifully styled titles.
Version: 1.0
Author: Kirsty Hennah
*/

add_action('init', 'kh_register_multiple_authors_cpt');

function kh_register_multiple_authors_cpt() {
	register_post_type('multi_author', [
		'labels' => [
			'name'                  => __('Authors', 'multiple-authors'),
			'singular_name'         => __('Author', 'multiple-authors'),
			'all_items'             => __('All Authors', 'multiple-authors'),
			'add_new'               => __('Add New', 'multiple-authors'),
			'add_new_item'          => __('Add New Author', 'multiple-authors'),
			'edit_item'             => __('Edit Author', 'multiple-authors'),
			'new_item'              => __('New Author', 'multiple-authors'),
			'view_item'             => __('View Author', 'multiple-authors'),
			'search_items'          => __('Search Authors', 'multiple-authors'),
			'not_found'             => __('No authors found', 'multiple-authors'),
			'not_found_in_trash'    => __('No authors found in Trash', 'multiple-authors'),
		],
		'public'                => true,
		'publicly_queryable'   => false,
		'has_archive'          => false,
		'show_ui'              => true,
		'show_in_menu'         => true,
		'menu_position'        => 5,
		'menu_icon'            => 'dashicons-admin-users',
		'supports'             => [],
		'show_in_rest'         => false,
	]);
}

// Force classic editor for author profiles.
add_filter( 'use_block_editor_for_post_type', function( $use_block_editor, $post_type ) {
	if ( 'multi_author' === $post_type ) {
		return false;
	}
	return $use_block_editor;
}, 10, 2 );

// Remove the content editor from author profiles.
add_action( 'admin_init', function() {
	remove_post_type_support( 'multi_author', 'editor' );
	remove_post_type_support( 'multi_author', 'title' );
} );

// ACF fields for author profiles.
add_action( 'acf/init', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key' => 'group_multi_author_fields',
		'title' => 'Author Profile',
		'fields' => array(
			array(
				'key' => 'field_author_name',
				'label' => 'Name',
				'name' => 'author_name',
				'type' => 'text',
			),
			array(
				'key' => 'field_author_title',
				'label' => 'Title',
				'name' => 'author_title',
				'type' => 'text',
			),
			array(
				'key' => 'field_author_company',
				'label' => 'Company',
				'name' => 'author_company',
				'type' => 'text',
			),
			array(
				'key' => 'field_author_bio',
				'label' => 'Bio',
				'name' => 'author_bio',
				'type' => 'textarea',
				'rows' => 6,
			),
			array(
				'key' => 'field_author_photo',
				'label' => 'Profile Photo',
				'name' => 'author_photo',
				'type' => 'image',
				'return_format' => 'id',
				'preview_size' => 'thumbnail',
				'library' => 'all',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'multi_author',
				),
			),
		),
	) );
} );

function kh_render_author_block($post_id) {
		$photo_id = get_field('author_photo', $post_id);
		$photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
		$name = get_field('author_name', $post_id);
		$title = get_field('author_title', $post_id);
		$company = get_field('author_company', $post_id);
		$bio = get_field('author_bio', $post_id);
		if ( ! $name ) {
			$name = get_the_title( $post_id );
		}
		
		ob_start();
?>
<div class="multi-author">
	<?php if ($photo_url): ?>
	<img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($name); ?>" class="author-photo greyscale" />
	<?php endif; ?>
	
	<div class="author-bio">
		<strong class="author-name"><?php echo esc_html($name ?: 'Anonymous'); ?></strong>
		<?php if ( $title || $company ) : ?>
		<p class="author-title"><?php echo esc_html($title); ?><?php echo $title && $company ? ' at ' : ''; ?><?php echo esc_html($company); ?></p>
		<?php endif; ?>
		
		<?php if ($bio): ?>
		<div class="author-description">
			<?php echo wp_kses_post( wpautop( $bio ) ); ?>
		</div>
		<?php endif; ?>
	</div>
</div>
<?php
	return ob_get_clean();
	}

// Remove non-editor meta boxes from author edit screens.
add_action( 'add_meta_boxes', function( $post_type ) {
	if ( 'multi_author' !== $post_type ) {
		return;
	}

	$meta_boxes = array(
		'khm-seo-meta',
		'khm-seo-meta-box',
		'khm-seo-schema',
		'khm-social-preview',
		'khm-seo-social',
		'khm_seo_social_meta',
		'khm-seo-boost-visibility',
		'khm-preview-meta',
	);

	foreach ( $meta_boxes as $box_id ) {
		remove_meta_box( $box_id, 'multi_author', 'normal' );
		remove_meta_box( $box_id, 'multi_author', 'side' );
		remove_meta_box( $box_id, 'multi_author', 'advanced' );
	}
}, 99 );

// Rename title column and show author name instead of post title.
add_filter( 'manage_multi_author_posts_columns', function( $columns ) {
	$updated = array();
	if ( isset( $columns['cb'] ) ) {
		$updated['cb'] = $columns['cb'];
	}
	$updated['author_photo'] = __( 'Photo', 'multiple-authors' );
	foreach ( $columns as $key => $value ) {
		if ( 'cb' === $key ) {
			continue;
		}
		$updated[ $key ] = $value;
	}
	if ( isset( $updated['title'] ) ) {
		$updated['title'] = __( 'Name', 'multiple-authors' );
	}
	return $updated;
} );

add_action( 'manage_multi_author_posts_custom_column', function( $column, $post_id ) {
	if ( 'author_photo' !== $column ) {
		return;
	}
	if ( ! function_exists( 'get_field' ) ) {
		echo '&mdash;';
		return;
	}

	$photo_id = get_field( 'author_photo', $post_id );
	if ( ! $photo_id ) {
		echo '&mdash;';
		return;
	}

	$image = wp_get_attachment_image( $photo_id, array( 48, 48 ), true, array( 'style' => 'border-radius:50%;' ) );
	echo $image ? $image : '&mdash;';
}, 10, 2 );

// Tighter column layout for author list.
add_action( 'admin_head-edit.php', function() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-multi_author' !== $screen->id ) {
		return;
	}
	echo '<style>
		.fixed .column-author_photo { width: 72px; }
		.fixed .column-title { width: auto; }
	</style>';
} );

add_filter( 'the_title', function( $title, $post_id ) {
	if ( ! is_admin() ) {
		return $title;
	}
	$post = get_post( $post_id );
	if ( ! $post || 'multi_author' !== $post->post_type ) {
		return $title;
	}

	$name = function_exists( 'get_field' ) ? get_field( 'author_name', $post_id ) : '';
	return $name ? $name : $title;
}, 10, 2 );

function kh_sync_multi_author_title( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! function_exists( 'get_field' ) ) {
		return;
	}

	$name = get_field( 'author_name', $post_id );
	if ( $name && $name !== $post->post_title ) {
		remove_action( 'save_post_multi_author', 'kh_sync_multi_author_title', 10 );
		wp_update_post( array(
			'ID' => $post_id,
			'post_title' => $name,
		) );
		add_action( 'save_post_multi_author', 'kh_sync_multi_author_title', 10, 2 );
	}
}
add_action( 'save_post_multi_author', 'kh_sync_multi_author_title', 20, 2 );

// Ensure author relationships are saved when ACF posts data.
add_action( 'acf/save_post', function( $post_id ) {
	if ( ! is_numeric( $post_id ) ) {
		return;
	}
	if ( empty( $_POST['acf']['field_multi_author_relationship'] ) ) {
		return;
	}

	$value = $_POST['acf']['field_multi_author_relationship'];
	if ( function_exists( 'update_field' ) ) {
		update_field( 'field_multi_author_relationship', $value, $post_id );
	} else {
		update_post_meta( $post_id, 'authors', $value );
	}
}, 20 );

function kh_get_post_authors( $post_id ) {
	if ( ! $post_id ) {
		return array();
	}

	$authors = array();
	if ( function_exists( 'get_field' ) ) {
		$authors = get_field( 'authors', $post_id );
	}

	if ( empty( $authors ) ) {
		$raw = get_post_meta( $post_id, 'authors', true );
		if ( is_array( $raw ) ) {
			$authors = $raw;
		}
	}

	if ( empty( $authors ) || ! is_array( $authors ) ) {
		return array();
	}

	$normalized = array();
	foreach ( $authors as $author ) {
		if ( is_object( $author ) && isset( $author->ID ) ) {
			$normalized[] = $author;
			continue;
		}
		$author_id = is_numeric( $author ) ? (int) $author : 0;
		if ( $author_id ) {
			$author_post = get_post( $author_id );
			if ( $author_post ) {
				$normalized[] = $author_post;
			}
		}
	}

	return $normalized;
}

add_filter( 'acf/fields/relationship/result', function( $title, $post, $field, $post_id ) {
	if ( empty( $field['name'] ) || 'authors' !== $field['name'] ) {
		return $title;
	}
	if ( ! $post || 'multi_author' !== $post->post_type ) {
		return $title;
	}

	if ( function_exists( 'get_field' ) ) {
		$name = get_field( 'author_name', $post->ID );
		if ( $name ) {
			return $name;
		}
	}

	return $title;
}, 10, 4 );

function kh_sync_post_author_from_multi_author( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! $post || 'post' !== $post->post_type ) {
		return;
	}
	if ( ! function_exists( 'get_field' ) ) {
		return;
	}

	$authors = get_field( 'authors', $post_id );
	if ( empty( $authors ) || ! is_array( $authors ) ) {
		return;
	}

	$primary = $authors[0];
	$author_id = is_object( $primary ) ? $primary->ID : ( is_numeric( $primary ) ? (int) $primary : 0 );
	if ( ! $author_id ) {
		return;
	}

	$author_name = function_exists( 'get_field' ) ? get_field( 'author_name', $author_id ) : '';
	if ( ! $author_name ) {
		return;
	}

	$user = get_user_by( 'display_name', $author_name );
	if ( ! $user ) {
		$login = sanitize_title( $author_name );
		if ( $login ) {
			$user = get_user_by( 'login', $login );
		}
	}
	if ( ! $user || (int) $post->post_author === (int) $user->ID ) {
		return;
	}

	remove_action( 'save_post', 'kh_sync_post_author_from_multi_author', 20 );
	wp_update_post( array(
		'ID' => $post_id,
		'post_author' => $user->ID,
	) );
	add_action( 'save_post', 'kh_sync_post_author_from_multi_author', 20, 2 );
}
add_action( 'save_post', 'kh_sync_post_author_from_multi_author', 20, 2 );

function kh_get_author_context_post_id() {
	if ( ! empty( $_GET['p'] ) && is_numeric( $_GET['p'] ) ) {
		return (int) $_GET['p'];
	}
	if ( ! empty( $_GET['preview_id'] ) && is_numeric( $_GET['preview_id'] ) ) {
		return (int) $_GET['preview_id'];
	}

	$post_id = get_the_ID();
	if ( $post_id ) {
		return $post_id;
	}

	$queried_id = get_queried_object_id();
	if ( $queried_id ) {
		return $queried_id;
	}

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
		$doc = \Elementor\Plugin::$instance->documents->get_current();
		if ( $doc && method_exists( $doc, 'get_main_id' ) ) {
			$main_id = $doc->get_main_id();
			if ( $main_id ) {
				return $main_id;
			}
		}
	}

	return 0;
}
	
	add_shortcode('fancy_author_block', function($atts) {
		$atts = shortcode_atts([
			'id' => 0
		], $atts);
		
		$post_id = (int) $atts['id'];
		if ( ! $post_id ) {
			$post_id = kh_get_author_context_post_id();
		}

		if ( ! $post_id ) {
			return '';
		}

		$authors = kh_get_post_authors( $post_id );
		if ( empty( $authors ) || ! is_array( $authors ) ) {
			return '';
		}

		$html = '<div class="multi-author-block">';
		foreach ( $authors as $author_post ) {
			$html .= kh_render_author_block( $author_post->ID );
		}
		$html .= '</div>';

		return $html;
	});
	
	add_action('elementor/widgets/widgets_registered', function($widgets_manager) {
		if ( class_exists('\Elementor\Widget_Base') && file_exists(plugin_dir_path(__FILE__) . 'widgets/class-author-widget.php') ) {
			require_once plugin_dir_path(__FILE__) . 'widgets/class-author-widget.php';
			if ( class_exists('Elementor_Fancy_Author_Widget') ) {
				$widgets_manager->register(new \Elementor_Fancy_Author_Widget());
			}
		}
	});

// Elementor widget registration for newer versions.
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
	if ( class_exists('\Elementor\Widget_Base') && file_exists(plugin_dir_path(__FILE__) . 'widgets/class-author-widget.php') ) {
		require_once plugin_dir_path(__FILE__) . 'widgets/class-author-widget.php';
		if ( class_exists('Elementor_Fancy_Author_Widget') ) {
			$widgets_manager->register(new \Elementor_Fancy_Author_Widget());
		}
	}
} );
