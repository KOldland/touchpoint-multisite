<?php

namespace Angie\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base CSS Loader Component
 *
 * Abstract base class for conditionally loading CSS files on Angie-created pages.
 * Used by animate-css and button-interactive-states modules.
 */
abstract class CSS_Loader_Base {

	const ANGIE_PAGE_META_KEY = '_angie_page';

	/**
	 * Get the CSS handle for wp_enqueue_style
	 *
	 * @return string
	 */
	abstract protected function get_css_handle(): string;

	/**
	 * Get the CSS URL
	 *
	 * @return string
	 */
	abstract protected function get_css_url(): string;

	/**
	 * Get the CSS version
	 *
	 * @return string
	 */
	abstract protected function get_css_version(): string;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_in_editor' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_on_frontend' ] );
	}

	/**
	 * Check if current post/page was created by Angie
	 *
	 * @return bool True if page was created by Angie, false otherwise.
	 */
	protected function is_angie_created_page(): bool {
		global $post;

		if ( ! $post || ! isset( $post->ID ) ) {
			return false;
		}

		$angie_page = get_post_meta( $post->ID, self::ANGIE_PAGE_META_KEY, true );
		return ! empty( $angie_page );
	}

	/**
	 * Enqueue CSS in Gutenberg editor
	 */
	public function enqueue_in_editor(): void {
		if ( ! $this->is_angie_created_page() ) {
			return;
		}

		$this->enqueue_css();
	}

	/**
	 * Enqueue CSS on frontend
	 */
	public function enqueue_on_frontend(): void {
		if ( ! is_singular() || ! $this->is_angie_created_page() ) {
			return;
		}

		$this->enqueue_css();
	}

	/**
	 * Enqueue the CSS file
	 */
	protected function enqueue_css(): void {
		wp_enqueue_style(
			$this->get_css_handle(),
			$this->get_css_url(),
			[],
			$this->get_css_version()
		);
	}
}
