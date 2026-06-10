<?php

namespace Cookiez\Modules\Scanner\Rest;

use Cookiez\Classes\Exceptions\Quota_Exceeded_Error;
use Cookiez\Classes\Logger;
use Cookiez\Classes\Rest\{
	Sanitizer,
	Validator,
};
use Cookiez\Modules\Scanner\Classes\{
	Enums\Scan_Initiator,
	Enums\Scan_Type,
	Route_Base,
	Scan_Url_Collector,
	Service\Exceptions\Scan_Service_Client_Exception,
	Service\Exceptions\Scan_Transport_Exception,
};
use Cookiez\Modules\Scanner\Components\Scanner;
use Cookiez\Modules\Scanner\Database\Scan_Entry;
use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Create_Scan
 * REST endpoint for creating scans.
 */
class Create_Scan extends Route_Base {
	public string $path = '';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'create-scan';
	}

	protected function sanitize_fields(): array {
		return [
			'type' => Sanitizer::text(),
			'urls' => Sanitizer::array( Sanitizer::url() ),
			'excluded_urls' => Sanitizer::array( Sanitizer::url() ),
		];
	}

	protected function validate_fields(): array {
		$url_item = ( new Validator() )
			->url( [ 'message' => esc_html__( 'Must be a valid URL', 'cookiez' ) ] );

		return [
			'type' => ( new Validator() )
				->enum( Scan_Type::class,
					[ 'message' => esc_html__( 'Invalid scan type', 'cookiez' ) ]
				),
			'urls' => ( new Validator() )
				->array(
					$url_item,
					[ 'message' => esc_html__( 'URLs must be an array', 'cookiez' ) ]
				)
				->nullable(),
			'excluded_urls' => ( new Validator() )
				->array(
					$url_item,
					[ 'message' => esc_html__( 'Excluded URLs must be an array', 'cookiez' ) ]
				)
				->nullable(),
		];
	}

	/**
	 * Start a scan.
	 *
	 * @return WP_REST_Response
	 */
	public function POST(): WP_REST_Response {
		try {
			$error = $this->verify_capability();

			if ( $error ) {
				return $error;
			}

			$validation_errors = $this->validate( $this->params );

			if ( ! empty( $validation_errors ) ) {
				return $this->respond_validation_error( $validation_errors );
			}

			if ( Scan_Entry::is_active_scan() ) {
				return $this->respond_error_json( [
					'message' => esc_html__( 'Scanning is already in progress', 'cookiez' ),
					'code' => 'scan_already_active',
				], 409 );
			}

			$type = $this->params['type'];
			$initiator = Scan_Initiator::MANUAL;

			$urls = Scan_Url_Collector::collect(
				$type,
				$this->params['urls'] ?? [],
				$this->params['excluded_urls'] ?? []
			);

			if ( empty( $urls ) ) {
				return $this->respond_error_json( [
					'message' => esc_html__( 'No URLs to scan. Try to exclude less URLs', 'cookiez' ),
					'code' => 'scan_create_failed',
				], 409 );
			}

			$scan = ( new Scanner() )->start_scan( $type, $initiator, $urls );

			return $this->respond_success_json( [
				'message' => esc_html__( 'Scan created successfully', 'cookiez' ),
				'scan_id' => $scan->id,
			], 201 );
		} catch ( Quota_Exceeded_Error $qee ) {
			return $this->respond_error_json( [
				'message' => esc_html__( 'Scan quota exceeded', 'cookiez' ),
				'code' => 'quota_exceeded',
			], 429 );
		} catch ( Scan_Transport_Exception $ste ) {
			return $this->respond_error_json( [
				'message' => esc_html__( 'Scan service unavailable', 'cookiez' ),
				'code' => 'scan_service_unavailable',
			], 502 );
		} catch ( Scan_Service_Client_Exception $ssce ) {
			Logger::error( $ssce->getMessage() );

			return $this->respond_error_json( [
				'message' => $ssce->getMessage(),
				'code' => 'scan_service_error',
			], 400 );
		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			], 500 );
		}
	}
}
