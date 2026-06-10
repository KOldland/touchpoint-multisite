<?php

namespace Angie\Modules\AngieStyles\Components;

use Angie\Classes\CSS_Loader_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Animate.css Loader Component
 *
 * Conditionally loads Animate.css library for pages created by Angie.
 */
class Animate_Css_Loader extends CSS_Loader_Base {

	const ANIMATE_CSS_URL = 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css';
	const ANIMATE_CSS_VERSION = '4.1.1';
	const ANIMATE_CSS_HANDLE = 'angie-animate-css';

	protected function get_css_handle(): string {
		return self::ANIMATE_CSS_HANDLE;
	}

	protected function get_css_url(): string {
		return self::ANIMATE_CSS_URL;
	}

	protected function get_css_version(): string {
		return self::ANIMATE_CSS_VERSION;
	}
}

