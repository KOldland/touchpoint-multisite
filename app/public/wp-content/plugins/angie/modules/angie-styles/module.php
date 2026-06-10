<?php

namespace Angie\Modules\AngieStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Angie\Classes\Module_Base;
use Angie\Modules\AngieStyles\Components\Animate_Css_Loader;
use Angie\Modules\AngieStyles\Components\Button_States_Loader;
use Angie\Modules\AngieStyles\Components\Style_Loader;

/**
 * Module `Angie Styles`
 *
 * Handles conditional loading of CSS libraries and styles for Angie-created pages.
 * Includes Animate.css for animations, button interactive states, and Unsplash captions.
 */
class Module extends Module_Base {

	public function get_name(): string {
		return 'angie-styles';
	}

	public static function is_active(): bool {
		return true;
	}

	protected function __construct() {
		new Animate_Css_Loader();
		new Button_States_Loader();
		new Style_Loader();
	}
}

