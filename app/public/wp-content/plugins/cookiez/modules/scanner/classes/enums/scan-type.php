<?php

namespace Cookiez\Modules\Scanner\Classes\Enums;

use Cookiez\Classes\Basic_Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scan_Type extends Basic_Enum {
	const HOMEPAGE = 'home';
	const FULL = 'full';
	const CUSTOM = 'custom';
}
