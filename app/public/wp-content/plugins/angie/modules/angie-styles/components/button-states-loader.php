<?php

namespace Angie\Modules\AngieStyles\Components;

use Angie\Classes\CSS_Loader_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Button States Loader Component
 *
 * Conditionally loads button interactive states CSS for pages created by Angie.
 */
class Button_States_Loader extends CSS_Loader_Base {

	const BUTTON_STATES_HANDLE = 'angie-button-states';
	const BUTTON_STATES_VERSION = '1.0.0';

	protected function get_css_handle(): string {
		return self::BUTTON_STATES_HANDLE;
	}

	protected function get_css_url(): string {
		return plugin_dir_url( dirname( __FILE__ ) ) . 'css/button-states.css';
	}

	protected function get_css_version(): string {
		return self::BUTTON_STATES_VERSION;
	}
}

