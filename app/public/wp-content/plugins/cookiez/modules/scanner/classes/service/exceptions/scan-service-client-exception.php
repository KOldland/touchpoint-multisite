<?php

namespace Cookiez\Modules\Scanner\Classes\Service\Exceptions;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown on any 4xx response from the scanning service or on a usable response
 * that fails validation (missing required keys, unknown status, etc.).
 *
 * The service emits 400 for every business error (not-found, already in a
 * final state, invalid domain, etc.). Callers cannot disambiguate without
 * inspecting the message string — a follow-up GET is the authoritative tool.
 */
class Scan_Service_Client_Exception extends Exception {
	protected $message = 'Unknown Scan service client error';
}
