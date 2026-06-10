<?php

namespace Cookiez\Modules\Scanner\Classes\Service\DTO;

use Cookiez\Classes\Enums\Cookie_Category;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Scan_DTO
 * Normalized snapshot of `GET /scans/{scanApiId}`. Shape mirrors
 * `ScanDetailResponse` on the service verbatim so callers can pick what they
 * need without relearning the service schema.
 */
final class Service_Scan_DTO {
	public string $api_id;
	public string $status;
	public ?string $started_at = null;
	public ?string $completed_at = null;

	/**
	 * @var array{
	 *   totalUrls:int, completedUrls:int, failedUrls:int, totalCookies:int
	 * }
	 */
	public array $summary = [];

	/** @var array<int, array{ url: string, status: string, error_code: ?string }> */
	public array $pages = [];

	/**
	 * Normalized to local semantics: `duration` is a positive int (lifetime in
	 * seconds) or null (session cookie); `category` is always a valid
	 * Cookie_Category enum value, with `unclassified` as the safe fallback
	 * for anything the service couldn't classify.
	 *
	 * @var array<int, array{ id: int, name: string, domain: string, duration: ?int, category: string, description: string }>
	 */
	public array $cookies = [];

	/**
	 * `value` is the script's identifier: origin+pathname for external scripts,
	 * content signature for inline scripts (mirrors the API's `ScriptDetail`).
	 * `category` is normalized to a valid Cookie_Category value — same rules
	 * as cookies — the script's category is displayed alongside the cookies
	 * it sets and must share the enum.
	 *
	 * @var array<int, array{ type: string, value: string, category: string, description: string, cookies: int[] }>
	 */
	public array $scripts = [];

	/**
	 * Parse a raw response body from the service. Returns null when required
	 * identity fields are missing.
	 *
	 * @param mixed $raw
	 * @return self|null
	 */
	public static function from_api( $raw ): ?self {
		$data = self::to_array( $raw );

		if ( empty( $data['scanApiId'] ) || empty( $data['status'] ) ) {
			return null;
		}

		$dto = new self();
		$dto->api_id = (string) $data['scanApiId'];
		$dto->status = (string) $data['status'];
		$dto->started_at = isset( $data['startedAt'] ) ? (string) $data['startedAt'] : null;
		$dto->completed_at = isset( $data['completedAt'] ) && null !== $data['completedAt']
			? (string) $data['completedAt']
			: null;
		$dto->summary = is_array( $data['summary'] ?? null ) ? $data['summary'] : [];
		$dto->pages = is_array( $data['pages'] ?? null ) ? $data['pages'] : [];
		$dto->cookies = self::normalize_cookies( $data['cookies'] ?? null );
		$dto->scripts = self::normalize_scripts( $data['scripts'] ?? null );

		return $dto;
	}

	/**
	 * Normalize the cookies array from raw service shape into local semantics:
	 * - `duration` becomes `?int` — positive lifetime in seconds, or null
	 *   for session cookies. Zero/negative/non-numeric collapses to null.
	 * - `category` becomes a valid Cookie_Category enum value; unknown or
	 *   blank inputs fall back to `unclassified`.
	 *
	 * @param mixed $raw
	 * @return array<int, array{ id: int, name: string, domain: string, duration: ?int, category: string, description: string }>
	 */
	private static function normalize_cookies( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $cookie ) {
			if ( ! is_array( $cookie ) ) {
				continue;
			}

			$cookie['duration'] = self::normalize_duration( $cookie['duration'] ?? null );
			$cookie['category'] = self::normalize_category( $cookie['category'] ?? '' );

			$out[] = $cookie;
		}

		return $out;
	}

	/**
	 * Normalize the scripts array — same category rules as cookies.
	 *
	 * @param mixed $raw
	 * @return array<int, array{ type:string, value:string, category:string, description:string, cookies:int[] }>
	 */
	private static function normalize_scripts( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $script ) {
			if ( ! is_array( $script ) ) {
				continue;
			}

			$script['category'] = self::normalize_category( $script['category'] ?? '' );

			$out[] = $script;
		}

		return $out;
	}

	/**
	 * Coerce a service-reported cookie duration into a positive int (seconds)
	 * or null (session cookie). Zero is not a meaningful value in the local
	 * schema — it collapses to null along with negatives and non-numerics.
	 *
	 * @param mixed $raw
	 */
	private static function normalize_duration( $raw ): ?int {
		if ( ! is_numeric( $raw ) ) {
			return null;
		}

		$int = (int) $raw;

		return $int > 0 ? $int : null;
	}

	/**
	 * Coerce a service-reported category string to a valid Cookie_Category.
	 * The FE schemas reject blank/unknown categories; `unclassified` is the
	 * correct "we don't know yet" sentinel.
	 *
	 * @param mixed $raw
	 */
	private static function normalize_category( $raw ): string {
		$value = is_string( $raw ) ? $raw : '';

		return Cookie_Category::is_valid( $value ) ? $value : Cookie_Category::UNCLASSIFIED;
	}

	/**
	 * Persistence projection for Scan_Table::SUMMARY. Translates the service's
	 * camelCase keys into the snake_case shape the local Scan_DTO reads, and
	 * derives `scripts_count` + `categories_found` which the service doesn't
	 * surface in its summary block.
	 *
	 * @return array
	 */
	public function to_summary(): array {
		$completed = (int) ( $this->summary['completedUrls'] ?? 0 );
		$failed = (int) ( $this->summary['failedUrls'] ?? 0 );

		$cookies_by_category = [
			Cookie_Category::NECESSARY => 0,
			Cookie_Category::FUNCTIONAL => 0,
			Cookie_Category::ANALYTICS => 0,
			Cookie_Category::ADVERTISING => 0,
			Cookie_Category::UNCLASSIFIED => 0,
		];

		foreach ( $this->cookies as $cookie ) {
			$category = $cookie['category'] ?? '';

			if ( isset( $cookies_by_category[ $category ] ) ) {
				$cookies_by_category[ $category ]++;
			}
		}

		$categories_found = count( array_filter( $cookies_by_category ) );

		return [
			'total_urls' => (int) ( $this->summary['totalUrls'] ?? 0 ),
			'scanned_urls' => $completed + $failed,
			'completed_urls' => $completed,
			'failed_urls' => $failed,
			'cookies_count' => (int) ( $this->summary['totalCookies'] ?? 0 ),
			'scripts_count' => count( $this->scripts ),
			'categories_found' => $categories_found,
			'cookies_by_category' => $cookies_by_category,
			'completed_at' => $this->completed_at,
		];
	}

	/**
	 * Normalise either an object (json_decode default) or an array into an
	 * associative array so the rest of from_api() has one shape to work with.
	 *
	 * @param mixed $raw
	 * @return array
	 */
	private static function to_array( $raw ): array {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( is_object( $raw ) ) {
			$decoded = json_decode( wp_json_encode( $raw ), true );

			return is_array( $decoded ) ? $decoded : [];
		}

		return [];
	}
}
