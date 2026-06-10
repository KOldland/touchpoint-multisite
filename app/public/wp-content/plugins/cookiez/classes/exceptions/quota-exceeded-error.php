<?php

namespace Cookiez\Classes\Exceptions;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Quota_Exceeded_Error extends RuntimeException {}
