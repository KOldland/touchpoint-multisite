<?php

namespace Cookiez\Modules\Script\Classes\Enums;

use Cookiez\Classes\Basic_Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Script_Blocking_Mode extends Basic_Enum {
	public const ALWAYS = 'always';
	public const UNTIL_CONSENT = 'until_consent';
	public const NEVER = 'never';
}
