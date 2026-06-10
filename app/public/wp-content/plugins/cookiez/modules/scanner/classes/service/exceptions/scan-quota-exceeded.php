<?php

namespace Cookiez\Modules\Scanner\Classes\Service\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scan_Quota_Exceeded extends Scan_Service_Client_Exception {
	protected $message = 'Scan quota exceeded';
}
