<?php

namespace Cookiez\Modules\Elementor\Documents\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Modules\Elementor\Documents\Document_Base;

class Preferences_Banner_Document extends Document_Base {

	const DOCUMENT_TYPE = 'preferences-banner';

	public static function get_title(): string {
		return esc_html__( 'Preferences Banner', 'cookiez' );
	}

	public static function get_plural_title(): string {
		return esc_html__( 'Preferences Banner', 'cookiez' );
	}
}
