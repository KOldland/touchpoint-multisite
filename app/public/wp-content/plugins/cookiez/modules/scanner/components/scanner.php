<?php

namespace Cookiez\Modules\Scanner\Components;

use Cookiez\Classes\Exceptions\Quota_Exceeded_Error;
use Cookiez\Modules\Connect\Classes\Data as Connect_Data;
use Cookiez\Modules\Cookie\Classes\Dto\Cookie_DTO;
use Cookiez\Modules\Cookie\Components\Cookie;
use Cookiez\Modules\Scanner\Classes\{
	Enums\Scan_Error,
	Enums\Scan_Status,
	Service\DTO\Service_Scan_DTO,
	Service\Exceptions\Scan_Quota_Exceeded,
	Service\Exceptions\Scan_Service_Client_Exception,
	Service\Exceptions\Scan_Transport_Exception,
	Service\Service_Client,
	Dto\Scan_DTO,
	Dto\Scan_Url_DTO,
};
use Cookiez\Modules\Scanner\Database\{
	Scan_Entry,
	Scan_Table,
	Scan_Url_Entry,
	Scan_Url_Table,
};
use Cookiez\Modules\Settings\Module as Settings_Module;
use Cookiez\Modules\Script\Classes\Enums\Script_Blocking_Mode;
use Cookiez\Modules\Script\Classes\Dto\Script_DTO;
use Cookiez\Modules\Script\Components\Script;

use stdClass;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scanner {
	private const BASE_SEQUENCE = [ 100, 50, 25, 10, 5, 1 ];

	/**
	 * Starts a scan.
	 *
	 * @throws Quota_Exceeded_Error No URLs can be scanned.
	 * @throws Scan_Transport_Exception Network / 5xx reaching the service.
	 * @throws Scan_Service_Client_Exception Service 4xx / malformed response.
	 */
	public function start_scan( string $type, string $initiator, array $urls ): Scan_DTO {
		[ $response, $accepted, $skipped ] = $this->create_scan( $type, $initiator, $urls );

		$entry = Scan_Entry::insert_one( $type, $initiator, $response->api_id );
		$scan_id = (int) $entry->id;

		$dtos = array_merge(
			array_map( fn( string $url ) => self::build_url_dto( $scan_id, $url, Scan_Status::IN_PROGRESS ), $accepted ),
			array_map( fn( string $url ) => self::build_url_dto( $scan_id, $url, Scan_Status::FAILED, Scan_Error::QUOTA_EXCEEDED ), $skipped ),
		);

		Scan_Url_Entry::insert_many( $dtos );

		return $this->build_scan_dto( Scan_Entry::find_by_id( $scan_id ) );
	}

	private static function build_url_dto( int $scan_id, string $url, string $status, ?string $code = null ): Scan_Url_DTO {
		$dto = new Scan_Url_DTO();

		$dto->scan_id = $scan_id;
		$dto->url = $url;
		$dto->status = $status;
		$dto->error_code = $code;

		return $dto;
	}

	/**
	 * @throws Quota_Exceeded_Error
	 * @throws Scan_Transport_Exception
	 * @throws Scan_Service_Client_Exception
	 */
	private function create_scan( string $type, string $initiator, array $urls ): array {
		if ( Settings_Module::is_elementor_one() ) {
			return $this->pick_scan_quota( $type, $initiator, $urls );
		}

		$left = Connect_Data::scans_left();

		if ( 0 === $left ) {
			throw new Quota_Exceeded_Error( esc_html__( 'Scan quota exceeded', 'cookiez' ) );
		}

		$accepted = array_values( array_slice( $urls, 0, $left ) );
		$skipped = array_values( array_slice( $urls, $left ) );

		$response = Service_Client::create_scan( $accepted, $type, $initiator );

		return [ $response, $accepted, $skipped ];
	}

	/**
	 * @throws Quota_Exceeded_Error
	 * @throws Scan_Transport_Exception
	 * @throws Scan_Service_Client_Exception
	 */
	private function pick_scan_quota( string $type, string $initiator, array $urls ): array {
		$count = count( $urls );
		$batch_sizes = array_values( array_unique( array_filter(
			array_merge( [ $count, intval( $count / 2 ) ], self::BASE_SEQUENCE ),
			fn( $size ) => $size > 0 && $size <= $count
		) ) );

		$last_error = null;

		foreach ( $batch_sizes as $batch_size ) {
			$accepted = array_values( array_slice( $urls, 0, $batch_size ) );
			$skipped = array_values( array_slice( $urls, $batch_size ) );

			try {
				$response = Service_Client::create_scan( $accepted, $type, $initiator );

				return [ $response, $accepted, $skipped ];
			} catch ( Scan_Quota_Exceeded $sqe ) {
				$last_error = $sqe;
				continue;
			}
		}

		throw new Quota_Exceeded_Error(
			esc_html( $last_error ? $last_error->getMessage() : __( 'Scan quota exceeded', 'cookiez' ) )
		);
	}

	/**
	 * Return the local DTO for a scan, refreshing from the service first if
	 * the scan is still in progress. Final-state scans short-circuit to DB.
	 *
	 * @throws Scan_Transport_Exception
	 * @throws Scan_Service_Client_Exception
	 */
	public function refresh_scan( int $local_id ): Scan_DTO {
		$row = Scan_Entry::find_by_id( $local_id );

		if ( ! $row ) {
			throw new Scan_Service_Client_Exception( 'Scan not found' );
		}

		if ( Scan_Status::IN_PROGRESS !== $row->status ) {
			return $this->build_scan_dto( $row );
		}

		$snapshot = Service_Client::get_scan_by_id( (string) $row->api_id );

		if ( Scan_Status::IN_PROGRESS !== $snapshot->status ) {
			self::apply_snapshot( (int) $row->id, $snapshot );
		}

		return $this->build_scan_dto( Scan_Entry::find_by_id( (int) $row->id ) );
	}

	/**
	 * Cancel a scan.
	 *
	 * @throws Scan_Transport_Exception
	 * @throws Scan_Service_Client_Exception
	 */
	public function cancel_scan( int $local_id ): Scan_DTO {
		$row = Scan_Entry::find_by_id( $local_id );

		if ( ! $row ) {
			throw new Scan_Service_Client_Exception( 'Scan not found' );
		}

		if ( Scan_Status::IN_PROGRESS !== $row->status ) {
			return $this->build_scan_dto( $row );
		}

		try {
			Service_Client::cancel_scan( (string) $row->api_id );

			try {
				$snapshot = Service_Client::get_scan_by_id( (string) $row->api_id );
				$snapshot->status = Scan_Status::CANCELLED;

				self::apply_snapshot( (int) $row->id, $snapshot );
			} catch ( Throwable $refresh_error ) {
				Scan_Entry::update_one(
					(int) $row->id,
					[ Scan_Table::STATUS => Scan_Status::CANCELLED ]
				);
			}
		} catch ( Scan_Transport_Exception $ste ) {
			throw $ste;
		} catch ( Scan_Service_Client_Exception $ssce ) {
			self::handle_failed_cancel( (int) $row->id, (string) $row->api_id );
		}

		return $this->build_scan_dto( Scan_Entry::find_by_id( (int) $row->id ) );
	}

	/**
	 * Handles a webhook call.
	 *
	 * @throws Scan_Transport_Exception  Follow-up GET failed at transport level.
	 * @throws Scan_Service_Client_Exception Unknown api_id / malformed response.
	 */
	public function handle_webhook_call( string $api_id ): Scan_DTO {
		$rows = Scan_Entry::find_many( [ Scan_Table::API_ID => $api_id ], 1 );
		$row = $rows[0] ?? null;

		if ( ! $row ) {
			throw new Scan_Service_Client_Exception( 'Scan not found' );
		}

		if ( Scan_Status::IN_PROGRESS !== $row->status ) {
			return $this->build_scan_dto( $row );
		}

		$snapshot = Service_Client::get_scan_by_id( $api_id );

		self::apply_snapshot( (int) $row->id, $snapshot );

		return $this->build_scan_dto( Scan_Entry::find_by_id( (int) $row->id ) );
	}

	/**
	 * Build a `Scan_DTO` from a scan row and attach the per-URL DTOs the FE
	 * needs to render partial-scan + Errors block. Centralised here so the DTO
	 * itself stays free of repository calls.
	 */
	private function build_scan_dto( stdClass $row ): Scan_DTO {
		$dto = Scan_DTO::from_entry( $row );
		$dto->urls = Scan_Url_Entry::find_many( [ Scan_Url_Table::SCAN_ID => (int) $row->id ] );

		return $dto;
	}

	/**
	 * Persist a service snapshot to local tables atomically. All writes (cookie
	 * ingestion, script ingestion, scan status/summary) share one DB transaction
	 * so a mid-batch failure rolls the scan back instead of leaving a partial
	 * ingest visible to the UI.
	 */
	private static function apply_snapshot( int $local_id, Service_Scan_DTO $snapshot ): void {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			self::save_scan_results( $local_id, $snapshot );

			self::apply_page_outcomes( $local_id, $snapshot->pages );

			Scan_Entry::update_one( $local_id, [
				Scan_Table::STATUS => $snapshot->status,
				Scan_Table::SUMMARY => wp_json_encode( $snapshot->to_summary() ),
			] );

			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $t ) {
			$wpdb->query( 'ROLLBACK' );
			throw $t;
		}
	}

	/**
	 * Stamp each scan_url row with the per-page outcome the service reported.
	 * Out-of-quota rows are skipped because they were never sent to the service
	 * (already stamped at start_scan time).
	 *
	 * @param array<int, array{ url:string, status:string, error_code:?string }> $pages
	 */
	private static function apply_page_outcomes( int $local_id, array $pages ): void {
		foreach ( $pages as $page ) {
			if ( '' === $page['url'] || '' === $page['status'] ) {
				continue;
			}

			Scan_Url_Entry::update_many(
				[
					Scan_Url_Table::SCAN_STATUS => $page['status'],
					Scan_Url_Table::ERROR_CODE => $page['error_code'],
				],
				[
					Scan_Url_Table::SCAN_ID => $local_id,
					Scan_Url_Table::URL => $page['url'],
					Scan_Url_Table::SCAN_STATUS => Scan_Status::IN_PROGRESS,
				]
			);
		}
	}

	private static function save_scan_results( int $local_scan_id, Service_Scan_DTO $snapshot ): void {
		if ( empty( $snapshot->cookies ) && empty( $snapshot->scripts ) ) {
			return;
		}

		$cookie_id_map = self::save_cookies( $local_scan_id, $snapshot->cookies );

		if ( ! empty( $snapshot->scripts ) ) {
			self::save_scripts( $snapshot->scripts, $cookie_id_map );
		}
	}

	/**
	 * @param array $cookies Normalized service cookie shapes.
	 * @return array<int,int> service id => local id
	 */
	private static function save_cookies( int $local_scan_id, array $cookies ): array {
		$map = [];
		$cookie = new Cookie();

		foreach ( $cookies as $cookie_data ) {
			$service_id = isset( $cookie_data['id'] ) ? (int) $cookie_data['id'] : 0;
			$name = (string) ( $cookie_data['name'] ?? '' );
			$domain = (string) ( $cookie_data['domain'] ?? '' );

			if ( '' === $name ) {
				continue;
			}

			$dto = Cookie_DTO::from_scan( $local_scan_id, [
				'name' => $name,
				'domain' => $domain,
				'duration' => $cookie_data['duration'] ?? null,
				'category' => (string) ( $cookie_data['category'] ?? '' ),
				'description' => (string) ( $cookie_data['description'] ?? '' ),
			] );

			$saved = $cookie->upsert_from_scan( $dto );

			if ( $service_id && $saved->id ) {
				$map[ $service_id ] = (int) $saved->id;
			}
		}

		return $map;
	}

	/**
	 * @param array $scripts Normalized service script shapes.
	 * @param array<int,int> $cookie_id_map service id => local id from save_cookies().
	 */
	private static function save_scripts( array $scripts, array $cookie_id_map ): void {
		$script = new Script();

		foreach ( $scripts as $script_data ) {
			$value = (string) ( $script_data['value'] ?? '' );

			if ( '' === $value ) {
				continue;
			}

			$refs = isset( $script_data['cookies'] ) && is_array( $script_data['cookies'] ) ? $script_data['cookies'] : [];
			$local_cookie_id = null;

			foreach ( $refs as $service_cookie_id ) {
				$candidate = $cookie_id_map[ (int) $service_cookie_id ] ?? null;

				if ( $candidate ) {
					$local_cookie_id = $candidate;
					break;
				}
			}

			$dto = Script_DTO::from_scan( [
				'value' => $value,
				'type' => (string) ( $script_data['type'] ?? '' ),
				'category' => (string) ( $script_data['category'] ?? '' ),
				'blocking_mode' => Script_Blocking_Mode::UNTIL_CONSENT,
				'description' => (string) ( $script_data['description'] ?? '' ),
			] );

			$dto->cookie_id = $local_cookie_id;

			$script->upsert_from_scan( $dto );
		}
	}

	/**
	 * Fallback path for `cancel_scan` when the initial cancel call returns 4xx.
	 *
	 * @throws Scan_Transport_Exception Follow-up GET failed at transport level.
	 */
	private static function handle_failed_cancel(
		int $local_id,
		string $api_id
	): void {
		try {
			$snapshot = Service_Client::get_scan_by_id( $api_id );

			self::apply_snapshot( $local_id, $snapshot );
		} catch ( Scan_Transport_Exception $ste ) {
			throw $ste;
		} catch ( Scan_Service_Client_Exception $ssce ) {
			Scan_Entry::update_one(
				$local_id,
				[ Scan_Table::STATUS => Scan_Status::CANCELLED ]
			);
		}
	}
}
