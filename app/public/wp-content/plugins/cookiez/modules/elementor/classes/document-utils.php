<?php

namespace Cookiez\Modules\Elementor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Modules\Elementor\Documents\Template_Defaults\Cookie_Consent_Template_Defaults;
use Cookiez\Modules\Elementor\Documents\Template_Defaults\Preferences_Banner_Template_Defaults;
use Cookiez\Modules\Elementor\Documents\Types\Cookie_Consent_Document;
use Cookiez\Modules\Elementor\Documents\Types\Preferences_Banner_Document;

class Document_Utils {

	public static function is_cookiez_document_type( string $type ): bool {
		return in_array( $type, [ Cookie_Consent_Document::DOCUMENT_TYPE, Preferences_Banner_Document::DOCUMENT_TYPE ], true );
	}

	public static function get_document_name( $document ): ?string {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_name' ) ) {
			return null;
		}
		return $document->get_name();
	}

	public static function get_document_defaults_map(): array {
		return [
			Cookie_Consent_Document::DOCUMENT_TYPE    => Cookie_Consent_Template_Defaults::class,
			Preferences_Banner_Document::DOCUMENT_TYPE => Preferences_Banner_Template_Defaults::class,
		];
	}
}
