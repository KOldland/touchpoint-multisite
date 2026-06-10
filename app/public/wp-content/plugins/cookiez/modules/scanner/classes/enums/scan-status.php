<?php

namespace Cookiez\Modules\Scanner\Classes\Enums;

use Cookiez\Classes\Basic_Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scan_Status extends Basic_Enum {
	public const COMPLETED = 'completed';
	public const IN_PROGRESS = 'in_progress';
	public const FAILED = 'failed';
	public const CANCELLED = 'cancelled';
}
