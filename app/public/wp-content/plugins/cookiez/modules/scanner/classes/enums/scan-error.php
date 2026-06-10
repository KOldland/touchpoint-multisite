<?php

namespace Cookiez\Modules\Scanner\Classes\Enums;

use Cookiez\Classes\Basic_Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scan_Error extends Basic_Enum {
	const PAGE_TOO_BIG = 'page_too_big';
	const DOMAIN_NOT_REACHABLE = 'domain_not_reachable';
	const ACCESS_ERROR = 'access_error';
	const UNSUPPORTED_MEDIA_TYPE = 'unsupported_media_type';
	const NOT_FOUND = 'not_found';
	const QUOTA_EXCEEDED = 'quota_exceeded';
	const GENERIC = 'generic';
}
