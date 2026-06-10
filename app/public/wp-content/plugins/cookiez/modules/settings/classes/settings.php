<?php

namespace Cookiez\Modules\Settings\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Settings {
	public const COOKIEZ_SETTINGS = 'cookiez_settings';
	public const COOKIEZ_CONTENT = 'cookiez_content';
	public const IS_VALID_PLAN_DATA = 'cookiez_is_valid_plan_data';
	public const PLAN_DATA = 'cookiez_plan_data';
	public const PLAN_SCOPE = 'cookiez_plan_scope';
	public const PLAN_DATA_REFRESH_TRANSIENT = 'cookiez_plan_data_refresh';
	public const SUBSCRIPTION_ID = 'cookiez_subscription_id';
	public const CLIENT_ID = 'cookiez_client_id';
	public const REVIEW_DATA = 'cookiez_review_data';

	/**
	 * Returns plugin settings data by option name typecasted to an appropriate data type.
	 *
	 * @param string $option_name
	 *
	 * @return mixed
	 */
	public static function get( string $option_name ) {
		$data = get_option( $option_name, [] );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Update the settings data by option name.
	 *
	 * @param string $option_name
	 * @param $value
	 *
	 * @return bool
	 */
	public static function set( string $option_name, $value ): bool {
		return update_option( $option_name, $value, false );
	}
}
