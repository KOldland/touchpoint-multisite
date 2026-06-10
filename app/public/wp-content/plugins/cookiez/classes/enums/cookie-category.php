<?php

namespace Cookiez\Classes\Enums;

use Cookiez\Classes\Basic_Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cookie_Category extends Basic_Enum {
	public const UNCLASSIFIED = 'unclassified';
	public const NECESSARY = 'necessary';
	public const FUNCTIONAL = 'functional';
	public const ANALYTICS = 'analytics';
	public const ADVERTISING = 'advertising';
}
