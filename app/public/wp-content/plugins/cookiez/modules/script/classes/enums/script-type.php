<?php

namespace Cookiez\Modules\Script\Classes\Enums;

use Cookiez\Classes\Basic_Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Script_Type extends Basic_Enum {
	public const EXTERNAL = 'external';
	public const INLINE = 'inline';
}
