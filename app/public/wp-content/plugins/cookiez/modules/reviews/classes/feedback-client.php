<?php

namespace Cookiez\Modules\Reviews\Classes;

use Cookiez\Classes\Services\Client;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Feedback_Client {

	private const SERVICE_ENDPOINT = 'feedback/reviews';

	/**
	 * Send request to the service to submit the feedback.
	 *
	 * @param array<string, mixed> $params Request payload.
	 *
	 * @return mixed
	 * @throws Exception When the request fails.
	 */
	public static function post_feedback( $params ) {
		$response = Client::get_instance()->make_request(
			'POST',
			self::SERVICE_ENDPOINT,
			$params
		);

		if ( empty( $response ) || is_wp_error( $response ) ) {
			throw new Exception( 'Failed to add the feedback.' );
		}

		return $response;
	}
}
