<?php

namespace Cookiez\Modules\Elementor\Documents\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Modules\Elementor\Documents\Document_Base;

class Cookie_Consent_Document extends Document_Base {

	const DOCUMENT_TYPE = 'cookie-consent';

	public static function get_title(): string {
		return esc_html__( 'Cookie Consent', 'cookiez' );
	}

	public static function get_plural_title(): string {
		return esc_html__( 'Cookie Consent', 'cookiez' );
	}
}
