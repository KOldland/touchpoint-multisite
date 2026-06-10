<?php

namespace Cookiez\Modules\Scanner\Classes\Service;

use Cookiez\Classes\Utils;
use Cookiez\Modules\Scanner\Classes\{
	Enums\Scan_Initiator,
	Service\DTO\Service_Create_Response_DTO,
	Service\DTO\Service_Scan_DTO,
};
use Cookiez\Modules\Scanner\Classes\Service\Exceptions\{
	Scan_Quota_Exceeded,
	Scan_Service_Client_Exception,
	Scan_Transport_Exception,
};

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Client
 * Communication to the external scanning service.
 */
class Service_Client {
	private const SCANS_PATH = 'scans';

	/**
	 * Initiate a scan.
	 *
	 * @param array $urls URLs to scan.
	 * @param string $type Scan type constant from Scan_Type enum.
	 * @param string $initiator Initiator constant from Scan_Initiator enum.
	 *
	 * @throws Scan_Service_Client_Exception When plan data is missing or the response is unusable.
	 * @throws Scan_Transport_Exception  On network / 5xx failures.
	 */
	public static function create_scan(
		array $urls,
		string $type,
		string $initiator = Scan_Initiator::MANUAL
	): Service_Create_Response_DTO {
		$body_params = [
			'initiator' => $initiator,
			'type' => $type,
			'urls' => array_values( $urls ),
		];

		$raw = Utils::get_api_client()->make_request(
			'POST',
			self::SCANS_PATH,
			$body_params,
			[],
			true
		);

		self::throw_on_error( $raw );

		$dto = Service_Create_Response_DTO::from_api( $raw );

		if ( ! $dto ) {
			throw new Scan_Service_Client_Exception( 'Create-scan response missing scanApiId' );
		}

		return $dto;
	}

	/**
	 * Fetch a scan by its api id.
	 *
	 * @throws Scan_Transport_Exception  On network / 5xx failures.
	 * @throws Scan_Service_Client_Exception Service 4xx / malformed response.
	 */
	public static function get_scan_by_id( string $api_id ): Service_Scan_DTO {
		$raw = Utils::get_api_client()->make_request(
			'GET',
			self::SCANS_PATH . "/$api_id",
		);

		self::throw_on_error( $raw );

		$dto = Service_Scan_DTO::from_api( $raw );

		if ( ! $dto ) {
			throw new Scan_Service_Client_Exception( 'Get-scan response missing required fields' );
		}

		return $dto;
	}

	/**
	 * Cancel a scan on the service.
	 *
	 * @throws Scan_Transport_Exception  On network / 5xx failures.
	 * @throws Scan_Service_Client_Exception Service 4xx.
	 */
	public static function cancel_scan( string $api_id ): void {
		$endpoint = sprintf( '%s/%s/cancel', self::SCANS_PATH, $api_id );

		$raw = Utils::get_api_client()->make_request(
			'POST',
			$endpoint,
			[],
			[],
			true
		);

		self::throw_on_error( $raw );
	}

	/**
	 * Translate `WP_Error` / empty payloads from the transport layer into
	 * typed exceptions. Returns silently for usable response bodies.
	 *
	 * @param mixed $raw Return value from Client::make_request().
	 *
	 * @throws Scan_Transport_Exception
	 * @throws Scan_Service_Client_Exception
	 */
	private static function throw_on_error( $raw ): void {
		if ( $raw instanceof WP_Error ) {
			$code = (int) $raw->get_error_code();
			$message = $raw->get_error_message();

			if ( $code >= 500 || $code < 400 ) {
				if ( '' !== $message ) {
					throw new Scan_Transport_Exception( esc_html( $message ) );
				}

				throw new Scan_Transport_Exception( 'Scan service transport failure' );
			}

			if ( 429 === $code ) {
				throw new Scan_Quota_Exceeded( '' !== $message ? esc_html( $message ) : 'Scan quota exceeded' );
			}

			// 4xx — service-level business error. Message is the only signal.
			if ( '' !== $message ) {
				throw new Scan_Service_Client_Exception( esc_html( $message ) );
			}

			throw new Scan_Service_Client_Exception(
				esc_html( sprintf( 'Scan service error (%d)', $code ) )
			);
		}
	}
}
