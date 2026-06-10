<?php

namespace Cookiez\Modules\Scanner\Classes\Enums;

use Cookiez\Classes\Basic_Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scan_Initiator extends Basic_Enum {
	const MANUAL = 'manual';
	const AUTO = 'auto';
	const SCHEDULE = 'schedule';
}
