<?php

namespace Cookiez\Modules\Scanner\Classes\Dto;

use Cookiez\Classes\Enums\Cookie_Category;
use Cookiez\Modules\Scanner\Database\Scan_Table;
use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scan_DTO
 */
class Scan_DTO {
	public ?int $id;
	public ?string $api_id;
	public string $status;
	public ?string $type;
	public ?string $initiator;
	public ?array $summary;
	public ?string $created_at;
	public ?string $updated_at;
	public ?int $total_urls;
	public ?int $scanned_urls;
	public ?int $cookies_count;
	public ?int $scripts_count;

	/** @var Scan_Url_DTO[] */
	public array $urls = [];

	public static function from_entry( stdClass $scan ): Scan_DTO {
		$dto = new self();

		$dto->id = $scan->id;
		$dto->api_id = $scan->api_id;
		$dto->status = $scan->status;
		$dto->type = $scan->type ?? null;
		$dto->initiator = $scan->initiator ?? null;
		$dto->summary = isset( $scan->summary ) && $scan->summary
			? json_decode( $scan->summary, true )
			: null;
		$dto->total_urls = $scan->total_urls ?? null;
		$dto->scanned_urls = $scan->scanned_urls ?? null;
		$dto->cookies_count = $scan->cookies_count ?? null;
		$dto->scripts_count = $scan->scripts_count ?? null;
		$dto->created_at = $scan->created_at;
		$dto->updated_at = $scan->updated_at;

		return $dto;
	}

	public function to_entry(): array {
		return [
			Scan_Table::ID => $this->id,
			Scan_Table::API_ID => $this->api_id,
			Scan_Table::STATUS => $this->status,
			Scan_Table::TYPE => $this->type,
			Scan_Table::INITIATOR => $this->initiator,
			Scan_Table::SUMMARY => null !== $this->summary ? wp_json_encode( $this->summary ) : null,
			Scan_Table::CREATED_AT => $this->created_at,
			Scan_Table::UPDATED_AT => $this->updated_at,
		];
	}

	/**
	 * Counts come from the stored summary snapshot when the scan is terminal;
	 * live counts come from the SQL JOIN while the scan is in progress.
	 */
	public function to_array(): array {
		$summary = is_array( $this->summary ) ? $this->summary : [];

		$total_urls = $summary['total_urls'] ?? $this->total_urls ?? 0;
		$scanned_urls = $summary['scanned_urls'] ?? $this->scanned_urls ?? 0;
		$cookies_count = $summary['cookies_count'] ?? $this->cookies_count ?? 0;
		$scripts_count = $summary['scripts_count'] ?? $this->scripts_count ?? 0;
		$categories_found = $summary['categories_found'] ?? 0;
		$cookies_by_category = array_merge(
			[
				Cookie_Category::NECESSARY => 0,
				Cookie_Category::FUNCTIONAL => 0,
				Cookie_Category::ANALYTICS => 0,
				Cookie_Category::ADVERTISING => 0,
				Cookie_Category::UNCLASSIFIED => 0,
			],
			is_array( $summary['cookies_by_category'] ?? null ) ? $summary['cookies_by_category'] : []
		);

		return [
			'id' => $this->id,
			'status' => $this->status,
			'type' => $this->type,
			'initiator' => $this->initiator,
			'total_urls' => $total_urls,
			'scanned_urls' => $scanned_urls,
			'cookies_count' => $cookies_count,
			'scripts_count' => $scripts_count,
			'categories_found' => $categories_found,
			'cookies_by_category' => $cookies_by_category,
			'created_at' => $this->created_at,
			'duration' => $this->calculate_duration(),
			'urls' => array_map( fn( Scan_Url_DTO $url ) => $url->to_array(), $this->urls ),
		];
	}

	private function calculate_duration(): ?int {
		if ( ! $this->created_at || ! $this->updated_at ) {
			return null;
		}

		$start = strtotime( $this->created_at );
		$end = strtotime( $this->updated_at );

		if ( false === $start || false === $end ) {
			return null;
		}

		return max( 0, $end - $start );
	}
}
