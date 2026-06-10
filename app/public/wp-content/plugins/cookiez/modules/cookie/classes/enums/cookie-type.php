<?php

namespace Cookiez\Modules\Cookie\Classes\Enums;

use Cookiez\Classes\Basic_Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cookie_Type extends Basic_Enum {
	public const SCAN = 'scan';
	public const MANUAL = 'manual';
}
