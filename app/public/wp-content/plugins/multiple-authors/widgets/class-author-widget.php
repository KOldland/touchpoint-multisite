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
		return ['touchpoint'];
	}

	public function render() {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}

		$post_id = function_exists( 'kh_get_author_context_post_id' ) ? kh_get_author_context_post_id() : get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$authors = function_exists( 'kh_get_post_authors' ) ? kh_get_post_authors( $post_id ) : get_field( 'authors', $post_id );
		if ( empty( $authors ) || ! is_array( $authors ) ) {
			return;
		}

		echo '<div class="multi-author-block">';
		foreach ( $authors as $author_post ) {
			echo kh_render_author_block( $author_post->ID );
		}
		echo '</div>';
	}
}
