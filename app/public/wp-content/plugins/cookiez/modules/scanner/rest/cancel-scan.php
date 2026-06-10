<?php

namespace Cookiez\Modules\Scanner\Rest;

use Cookiez\Classes\Logger;
use Cookiez\Classes\Rest\Sanitizer;
use Cookiez\Modules\Scanner\Classes\{
	Route_Base,
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

class Cancel_Scan extends Route_Base {
	public string $path = '(?P<id>\d+)\/cancel';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'cancel-scan';
	}

	protected function sanitize_fields(): array {
		return [
			'id' => Sanitizer::absint(),
		];
	}

	public function POST(): WP_REST_Response {
		try {
			$error = $this->verify_capability();

			if ( $error ) {
				return $error;
			}

			$scan_id = (int) $this->params['id'];
			$row = Scan_Entry::find_by_id( $scan_id );

			if ( ! $row ) {
				return $this->respond_error_json( [
					'message' => esc_html__( 'Scan not found', 'cookiez' ),
					'code' => 'scan_not_found',
				], 404 );
			}

			$scan = ( new Scanner() )->cancel_scan( $scan_id );

			return $this->respond_success_json( [
				'message' => esc_html__( 'Scan cancelled', 'cookiez' ),
				'scan_id' => $scan->id,
				'scan' => $scan->to_array(),
			] );
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
