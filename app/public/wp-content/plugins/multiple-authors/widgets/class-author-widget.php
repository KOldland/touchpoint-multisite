<?php
use Elementor\Widget_Base;

class Elementor_Fancy_Author_Widget extends Widget_Base {

	public function get_name() {
		return 'fancy_author_widget';
	}

	public function get_title() {
		return __('Fancy Author Block', 'multiple-authors');
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return ['general'];
	}

	public function render() {
		$authors = get_field('authors'); // This is your ACF field on the post
		if (!empty($authors)) {
			echo '<div class="multi-author-block">';
			foreach ($authors as $author_post) {
				echo kh_render_author_block($author_post->ID); // Your beautiful block function
			}
			echo '</div>';
		}
	}
}
