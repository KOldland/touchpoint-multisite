<?php

namespace Cookiez\Modules\Connect\Classes;

use Cookiez\Modules\Settings\Classes\Settings;
use Cookiez\Modules\Settings\Module as Settings_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Data {
	public static function scans_left(): int {
		if ( Settings_Module::is_elementor_one() ) {
			return PHP_INT_MAX;
		}

		$plan = get_option( Settings::PLAN_DATA );

		$allowed = (int) ( $plan->quota->cookie_scans->allowed ?? 0 );
		$used = (int) ( $plan->quota->cookie_scans->used ?? 0 );

		return max( 0, $allowed - $used );
	}
}
