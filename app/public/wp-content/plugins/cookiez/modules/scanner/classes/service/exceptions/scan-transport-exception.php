<?php

namespace Cookiez\Modules\Scanner\Classes\Service\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown on transport-level failures reaching the external scanning service:
 * network error, timeout, 5xx response, auth failure at transport level.
 */
class Scan_Transport_Exception extends Scan_Service_Client_Exception {
	protected $message = 'Failed to reach the external scanning service';
}
