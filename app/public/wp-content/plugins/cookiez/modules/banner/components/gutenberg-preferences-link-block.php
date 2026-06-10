<?php

namespace Cookiez\Modules\Banner\Components;

use Cookiez\Classes\Utils\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg block: custom link to open Cookiez preferences.
 */
class Gutenberg_Preferences_Link_Block {

	/**
	 * @throws \Error
	 */
	public function enqueue_custom_link_block_assets(): void {
		register_block_type( 'cookiez/preferences-link', [] );

		if ( is_admin() ) {
			Assets::enqueue_app_assets( 'gutenberg-blocks/preferences-link/block', false );
		}
	}

	/**
	 * @param string $block_content Saved block HTML.
	 * @return string
	 */
	public function enqueue_custom_link_block_frontend( $block_content ): string {
		static $enqueued = false;

		if ( ! $enqueued ) {
			$enqueued = true;

			Assets::enqueue_app_assets( 'gutenberg-blocks/preferences-link/frontend', false );
		}

		return $block_content;
	}

	/**
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'enqueue_custom_link_block_assets' ] );
		add_filter( 'render_block_cookiez/preferences-link', [ $this, 'enqueue_custom_link_block_frontend' ] );
	}
}
