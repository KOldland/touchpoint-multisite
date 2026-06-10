<?php

namespace Cookiez\Modules\Connect\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Config
 */
class Config {
	const APP_NAME = 'cookiez';
	const APP_PREFIX = 'cookiez';
	const APP_REST_NAMESPACE = 'cookiez/v1';
	const PLUGIN_SLUG = 'cookiez';
	const BASE_URL = 'https://my.elementor.com/connect';
	const ADMIN_PAGE = 'cookiez-settings';
	const APP_TYPE = 'app_cookie';
	const SCOPES = 'openid offline_access share_usage_data';
	const STATE_NONCE = 'cookiez_auth_nonce';
	const CONNECT_MODE = 'site';
}
