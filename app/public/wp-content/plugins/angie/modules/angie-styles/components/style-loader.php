<?php

namespace Angie\Modules\AngieStyles\Components;

use Angie\Classes\CSS_Loader_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Style CSS Loader Component
 *
 * Conditionally loads style CSS for pages created by Angie.
 */
class Style_Loader extends CSS_Loader_Base {

	const STYLE_HANDLE = 'angie-style';
	const STYLE_VERSION = '1.0.0';

	protected function get_css_handle(): string {
		return self::STYLE_HANDLE;
	}

	protected function get_css_url(): string {
		return plugin_dir_url( dirname( __FILE__ ) ) . 'css/style.css';
	}

	protected function get_css_version(): string {
		return self::STYLE_VERSION;
	}
}

