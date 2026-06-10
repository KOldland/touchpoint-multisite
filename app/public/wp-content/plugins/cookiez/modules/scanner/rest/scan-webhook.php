<?php

namespace Cookiez\Modules\Scanner\Rest;

use Cookiez\Classes\Logger;
use Cookiez\Classes\Services\Webhook_Token;
use Cookiez\Modules\Scanner\Classes\{
	Route_Base,
	Service\Exceptions\Scan_Service_Client_Exception,
	Service\Exceptions\Scan_Transport_Exception,
};
use Cookiez\Modules\Scanner\Components\Scanner;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scan_Webhook
 * Receives scan final-state notifications posted by the external scanning
 * service. Authentication is a per-site token embedded in the URL that the
 * service stored at registration time; see Webhook_Token.
 *
 * The payload itself is treated as a push hint only — the handler pulls a
 * fresh snapshot via GET /scans/{id} so a forged payload cannot corrupt
 * local state.
 */
class Scan_Webhook extends Route_Base {
	public string $path = 'webhook';

	/**
	 * Server-to-server endpoint — WP user auth does not apply. Token check
	 * lives in POST_permission_callback.
	 *
	 * @var bool
	 */
	protected $auth = false;

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'scan-webhook';
	}

	protected function sanitize_fields(): array {
		return [];
	}

	/**
	 * Gate the webhook on a constant-time token match. Token is supplied as
	 * a query parameter because the service does not attach custom headers.
	 */
	public function POST_permission_callback( WP_REST_Request $request ): bool {
		$provided = (string) $request->get_param( 'token' );
		$expected = Webhook_Token::get();

		return '' !== $provided
			&& '' !== $expected
			&& hash_equals( $expected, $provided );
	}

	/**
	 * @return WP_REST_Response
	 */
	public function POST(): WP_REST_Response {
		$api_id = isset( $this->params['scanApiId'] ) ? (string) $this->params['scanApiId'] : '';
		$status = isset( $this->params['status'] ) ? (string) $this->params['status'] : '';

		if ( '' === $api_id || '' === $status ) {
			return $this->respond_error_json( [
				'message' => esc_html__( 'Malformed webhook payload', 'cookiez' ),
				'code' => 'invalid_payload',
			], 400 );
		}

		try {
			( new Scanner() )->handle_webhook_call( $api_id );
		} catch ( Scan_Transport_Exception $e ) {
			return $this->respond_error_json( [
				'message' => esc_html__( 'Reconciliation failed', 'cookiez' ),
				'code' => 'reconciliation_failed',
			], 502 );
		} catch ( Scan_Service_Client_Exception $ssce ) {
			Logger::error( $ssce->getMessage() );
		} catch ( Throwable $t ) {
			Logger::error( $t->getMessage() );

			return $this->respond_error_json( [
				'message' => esc_html__( 'Internal error', 'cookiez' ),
				'code' => 'internal_server_error',
			], 500 );
		}

		return $this->respond_success_json( [ 'ok' => true ] );
	}
}
