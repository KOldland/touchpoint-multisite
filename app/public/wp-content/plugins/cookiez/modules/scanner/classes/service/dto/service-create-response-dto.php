<?php

namespace Cookiez\Modules\Scanner\Classes\Service\DTO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Create_Response_DTO
 * Minimal shape of `POST /scans` — the service returns just the api id.
 */
final class Service_Create_Response_DTO {
	public string $api_id;

	/**
	 * Parse a raw response body from the service. Returns null when the
	 * payload doesn't contain a non-empty scanApiId.
	 *
	 * @param mixed $raw Either an object (json_decode default) or an array.
	 * @return self|null
	 */
	public static function from_api( $raw ): ?self {
		if ( is_object( $raw ) ) {
			$decoded = json_decode( wp_json_encode( $raw ), true );
			$raw = is_array( $decoded ) ? $decoded : [];
		}

		if ( ! is_array( $raw ) || empty( $raw['scanApiId'] ) ) {
			return null;
		}

		$dto = new self();
		$dto->api_id = (string) $raw['scanApiId'];

		return $dto;
	}
}
