<?php

namespace Cookiez\Modules\Scanner\Classes\Dto;

use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scan_Url_DTO
 * Per-URL row from the `cookiez_scan_urls` table.
 */
class Scan_Url_DTO {
	public int $scan_id;
	public string $url;
	public string $status;
	public ?string $error_code = null;

	public static function from_entry( stdClass $row ): Scan_Url_DTO {
		$dto = new self();

		$dto->scan_id = $row->scan_id;
		$dto->url = $row->url;
		$dto->status = $row->scan_status;
		$dto->error_code = ! empty( $row->error_code ) ? (string) $row->error_code : null;

		return $dto;
	}

	public function to_array(): array {
		return [
			'url' => $this->url,
			'status' => $this->status,
			'error_code' => $this->error_code,
		];
	}
}
