<?php

namespace Cookiez\Modules\Settings\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Content_Resolver {

	private const ALLOWED_TEMPLATE_TYPES = [ 'opt-in', 'opt-out' ];

	private const COOKIE_CATEGORIES = [
		'necessary',
		'functional',
		'analytics',
		'advertising',
		'unclassified',
	];

	private const BANNER_STRING_DEFAULTS = [
		'title'                 => 'We use cookies',
		'cookieMessage'         => 'We use cookies to improve your experience on this website. You may choose which types of cookies to allow and change your preferences at any time. Disabling cookies may impact your experience on this website. You can learn more by viewing our Cookie Policy.',
		'acceptAllLabel'        => 'Accept All',
		'denyLabel'             => 'Deny',
		'customizeLabel'        => 'Customize',
		'cookiePolicyLinkText'  => '',
		'cookiePolicyUrl'       => '',
		'privacyPolicyLinkText' => '',
		'privacyPolicyUrl'      => '',
		'doNotSellLinkText'     => 'Do Not Sell or Share My Personal Information',
	];

	private const BANNER_BOOL_DEFAULTS = [
		'cookiePolicyLink'  => false,
		'privacyPolicyLink' => false,
	];

	private const OPT_IN_PREFERENCES_DEFAULTS = [
		'header'               => 'Customize consent preference',
		'privacyOverview'      => 'We use cookies to improve your experience on this website. You may choose which types of cookies to allow and change your preferences at any time. Disabling cookies may impact your experience on this website. You can learn more by viewing our Cookie Policy.',
		'acceptAllLabel'       => 'Accept all',
		'denyLabel'            => 'Deny',
		'savePreferencesLabel' => 'Save preferences',
	];

	private const OPT_OUT_DIALOG_DEFAULTS = [
		'header'               => 'Customize consent preference',
		'privacyOverview'      => 'We use cookies to improve your experience on this website. You may choose which types of cookies to allow and change your preferences at any time. Disabling cookies may impact your experience on this website. You can learn more by viewing our Cookie Policy.',
		'denyLabel'            => 'Deny',
		'savePreferencesLabel' => 'Save preferences',
	];

	private const CATEGORY_DEFAULTS = [
		'necessary' => [
			'title'       => 'Necessary',
			'description' => 'These cookies are essential for you to browse the website and use its features, such as accessing secure areas of the site or provide the basic services.',
		],
		'functional' => [
			'title'       => 'Functional',
			'description' => 'These cookies allow a website to remember choices you have made in the past, like what language you prefer, what region you would like weather reports for, or what your user name and password are so you can automatically log in.',
		],
		'analytics' => [
			'title'       => 'Analytical',
			'description' => 'These cookies collect information about how you use a website, like which pages you visited and which links you clicked on. None of this information can be used to identify you. It is all aggregated and, therefore, anonymized. Their sole purpose is to improve website functions.',
		],
		'advertising' => [
			'title'       => 'Advertisement',
			'description' => 'These cookies track your online activity to help advertisers deliver more relevant advertising or to limit how many times you see an ad. These cookies can share that information with other organizations or advertisers.',
		],
		'unclassified' => [
			'title'       => 'Uncategorized',
			'description' => 'These are cookies that are currently in the process of being classified together with other cookies.',
		],
	];

	/**
	 * @return array<string, string|bool>
	 */
	public static function get_active_banner_fields( ?string $template_type_override = null ): array {
		$template_type = self::resolve_template_type( $template_type_override );
		$template      = self::get_active_template_for_type( $template_type );

		$buttons        = isset( $template['buttons'] ) && is_array( $template['buttons'] ) ? $template['buttons'] : [];
		$banner_options = isset( $template['banner-options'] ) && is_array( $template['banner-options'] ) ? $template['banner-options'] : [];

		$string_map = [
			'title'                 => $template['title'] ?? '',
			'cookieMessage'         => $template['cookie-message'] ?? '',
			'acceptAllLabel'        => $buttons['accept-all'] ?? '',
			'denyLabel'             => $buttons['deny'] ?? '',
			'customizeLabel'        => $buttons['customize'] ?? '',
			'cookiePolicyLinkText'  => $banner_options['cookie-policy-link-text'] ?? '',
			'cookiePolicyUrl'       => $banner_options['cookie-policy-url'] ?? '',
			'privacyPolicyLinkText' => $banner_options['privacy-policy-link-text'] ?? '',
			'privacyPolicyUrl'      => $banner_options['privacy-policy-url'] ?? '',
			'doNotSellLinkText'     => $banner_options['do-not-sell-link-text'] ?? '',
		];

		$out = [];
		foreach ( $string_map as $key => $value ) {
			$stored = is_string( $value ) && '' !== $value ? $value : null;
			$out[ $key ] = $stored ?? self::BANNER_STRING_DEFAULTS[ $key ];
		}

		$out['cookiePolicyLink']  = isset( $banner_options['cookie-policy-link'] ) ? (bool) $banner_options['cookie-policy-link'] : self::BANNER_BOOL_DEFAULTS['cookiePolicyLink'];
		$out['privacyPolicyLink'] = isset( $banner_options['privacy-policy-link'] ) ? (bool) $banner_options['privacy-policy-link'] : self::BANNER_BOOL_DEFAULTS['privacyPolicyLink'];

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_active_preferences_fields( ?string $template_type_override = null ): array {
		$template_type = self::resolve_template_type( $template_type_override );
		$template      = self::get_active_template_for_type( $template_type );

		$is_opt_out = 'opt-out' === $template_type;
		$source_key = $is_opt_out ? 'opt-out-dialog' : 'preferences';
		$defaults   = $is_opt_out ? self::OPT_OUT_DIALOG_DEFAULTS : self::OPT_IN_PREFERENCES_DEFAULTS;

		$raw = isset( $template[ $source_key ] ) && is_array( $template[ $source_key ] )
			? $template[ $source_key ]
			: [];

		$wire_to_output = $is_opt_out
			? [
				'header'           => 'header',
				'privacy-overview' => 'privacyOverview',
				'deny-button'      => 'denyLabel',
				'save-preferences' => 'savePreferencesLabel',
			]
			: [
				'header'           => 'header',
				'privacy-overview' => 'privacyOverview',
				'accept-button'    => 'acceptAllLabel',
				'deny-button'      => 'denyLabel',
				'save-preferences' => 'savePreferencesLabel',
			];

		$out = $defaults;
		foreach ( $wire_to_output as $wire_key => $output_key ) {
			if ( isset( $raw[ $wire_key ] ) && '' !== $raw[ $wire_key ] ) {
				$out[ $output_key ] = is_string( $raw[ $wire_key ] ) ? $raw[ $wire_key ] : (string) $raw[ $wire_key ];
			}
		}

		return $out;
	}

	public static function get_active_template_type( ?string $template_type_override = null ): string {
		return self::resolve_template_type( $template_type_override );
	}

	/**
	 * @return array<string, array{title: string, description: string}>
	 */
	public static function get_active_category_fields( ?string $template_type_override = null ): array {
		$template_type = self::resolve_template_type( $template_type_override );
		$template      = self::get_active_template_for_type( $template_type );

		$raw = isset( $template['cookie-categories'] ) && is_array( $template['cookie-categories'] )
			? $template['cookie-categories']
			: [];

		$out = [];
		foreach ( self::COOKIE_CATEGORIES as $category ) {
			$default      = self::CATEGORY_DEFAULTS[ $category ];
			$category_raw = isset( $raw[ $category ] ) && is_array( $raw[ $category ] ) ? $raw[ $category ] : [];

			$out[ $category ] = [
				'title'       => isset( $category_raw['title'] ) && '' !== $category_raw['title']
					? (string) $category_raw['title']
					: $default['title'],
				'description' => isset( $category_raw['description'] ) && '' !== $category_raw['description']
					? (string) $category_raw['description']
					: $default['description'],
			];
		}

		return $out;
	}

	/**
	 * @return string[]
	 */
	public static function get_cookie_category_keys(): array {
		return self::COOKIE_CATEGORIES;
	}

	private static function resolve_template_type( ?string $override ): string {
		if ( null !== $override && in_array( $override, self::ALLOWED_TEMPLATE_TYPES, true ) ) {
			return $override;
		}

		$settings      = Settings::get( Settings::COOKIEZ_SETTINGS );
		$template_type = $settings['templateType'] ?? 'opt-in';

		return in_array( $template_type, self::ALLOWED_TEMPLATE_TYPES, true ) ? $template_type : 'opt-in';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get_active_template_for_type( string $template_type ): array {
		$content_root = Settings::get( Settings::COOKIEZ_CONTENT );
		$by_lang      = isset( $content_root['content'] ) && is_array( $content_root['content'] )
			? $content_root['content']
			: [];
		$lang_data = self::resolve_language_block( $by_lang );

		return isset( $lang_data[ $template_type ] ) && is_array( $lang_data[ $template_type ] )
			? $lang_data[ $template_type ]
			: [];
	}

	/**
	 * @param array<string, mixed> $by_lang
	 * @return array<string, mixed>
	 */
	private static function resolve_language_block( array $by_lang ): array {
		$locale = get_locale();

		if ( isset( $by_lang[ $locale ] ) && is_array( $by_lang[ $locale ] ) ) {
			return $by_lang[ $locale ];
		}

		$short = strlen( $locale ) > 2 ? substr( $locale, 0, 2 ) : $locale;
		if ( isset( $by_lang[ $short ] ) && is_array( $by_lang[ $short ] ) ) {
			return $by_lang[ $short ];
		}

		if ( isset( $by_lang['en'] ) && is_array( $by_lang['en'] ) ) {
			return $by_lang['en'];
		}

		return [];
	}
}
