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
		'supports'             => ['title'],
		'show_in_rest'         => true,
	]);
}

	function kh_render_author_block($post_id) {
		$photo_id = get_field('photo', $post_id);
		$photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
		$first = get_field('first_name', $post_id);
		$last = get_field('second_family_name', $post_id);
		$title = get_field('job_title', $post_id);
		$company = get_field('company', $post_id);
		$bio = get_field('bio', $post_id);
		$linkedin = get_field('linkedin_url', $post_id);
		
		ob_start();
?>
<div class="multi-author">
	<?php if ($photo_url): ?>
	<img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr("$first $last"); ?>" class="author-photo greyscale" />
	<?php endif; ?>
	
	<div class="author-bio">
		<strong class="author-name"><?php echo esc_html(trim("$first $last")) ?: 'Anonymous'; ?></strong>
		<p class="author-title"><?php echo esc_html($title); ?> at <?php echo esc_html($company); ?></p>
		
		<?php if ($bio): ?>
		<div class="author-description">
			<?php echo wp_kses_post($bio); ?>
		</div>
		<?php endif; ?>

		<?php if ($linkedin): ?>
		<p class="author-linkedin">
			<a href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener noreferrer">
				Connect with <?php echo esc_html($first); ?> on LinkedIn
			</a>
		</p>
		<?php endif; ?>
	</div>
</div>
<?php
	return ob_get_clean();
	}
	
	add_shortcode('fancy_author_block', function($atts) {
		$atts = shortcode_atts([
			'id' => get_the_ID()
		], $atts);
		
		return kh_render_author_block($atts['id']);
	});
	
	add_action('elementor/widgets/widgets_registered', function($widgets_manager) {
		if ( class_exists('\Elementor\Widget_Base') && file_exists(plugin_dir_path(__FILE__) . 'widgets/class-author-widget.php') ) {
			require_once plugin_dir_path(__FILE__) . 'widgets/class-author-widget.php';
			if ( class_exists('Elementor_Fancy_Author_Widget') ) {
				$widgets_manager->register(new \Elementor_Fancy_Author_Widget());
			}
		}
	});
