<?php

namespace Cookiez\Modules\Elementor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Classes\Utils;
use Elementor\Widget_Base;

class Widget_Utils {

	public const CONTROL_NAME = 'template_type';

	private const OPT_IN = 'opt-in';
	private const OPT_OUT = 'opt-out';

	public static function resolve_override( Widget_Base $widget ): ?string {
		if ( ! Utils::is_elementor_editor() ) {
			return null;
		}

		$value = $widget->get_settings_for_display( self::CONTROL_NAME );

		return ( self::OPT_IN === $value || self::OPT_OUT === $value ) ? $value : null;
	}
}
