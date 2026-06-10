<?php

namespace Cookiez\Modules\Banner\Dynamic_Tags;

use Elementor\Core\DynamicTags\Data_Tag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Dynamic Tag: URL that opens Cookiez preferences via Elementor urlActions.
 */
class Preferences_Trigger extends Data_Tag {

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'cookiez-preferences-trigger';
	}

	/**
	 * @return string
	 */
	public function get_title(): string {
		return esc_html__( 'Cookiez preferences', 'cookiez' );
	}

	/**
	 * @param void
	 */
	public function get_group(): string {
		return 'site';
	}

	/**
	 * @return array
	 */
	public function get_categories(): array {
		return [ 'url' ];
	}

	/**
	 * @param array $options[]
	 * @return string
	 */
	protected function get_value( array $options = [] ): string {
		return \Elementor\Plugin::instance()->frontend->create_action_hash(
			'cookiezBanner:openPreferences',
			[]
		);
	}
}
