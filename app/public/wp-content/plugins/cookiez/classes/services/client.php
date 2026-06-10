<?php

namespace Cookiez\Classes\Services;

use Cookiez\Modules\Connect\Module as Connect;
use ElementorOne\Connect\Exceptions\Service_Exception;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Client
 */
class Client {
	private const BASE_URL = 'https://my.elementor.com/apps/api/v1/cookiez-consent/';

	private const BASE_URL_FEEDBACK = 'https://feedback-api.prod.apps.elementor.red/apps/api/v1/';

	private bool $refreshed = false;

	public static ?Client $instance = null;

	/**
	 * set_instance
	 * used for testing
	 * @param $instance
	 */
	public static function set_instance( $instance ) {
		self::$instance = $instance;
	}

	/**
	 * get_instance
	 * @return Client|null
	 */
	public static function get_instance(): ?Client {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * get_site_info function
	 *
	 * @return array
	 */
	public static function get_site_info(): array {
		return [
			// Which API version is used.
			'app_version' => COOKIEZ_VERSION,
			// Which language to return.
			'site_lang' => get_bloginfo( 'language' ),
			// site to connect
			'site_url' => trailingslashit( home_url() ),
			// Alt site url
			'alt_site_url' => trailingslashit( site_url() ),
			// current user
			'local_id' => get_current_user_id(),
			// User Agent
			'user_agent' => ! empty( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: 'Unknown',
			'webhook_url' => self::webhook_endpoint(),
		];
	}

	/**
	 * Scan webhook endpoint
	 * @return string
	 */
	private static function webhook_endpoint(): string {
		$blog_id = get_current_blog_id();
		$url = get_rest_url( $blog_id, 'cookiez/v1/scan/webhook' );

		return add_query_arg( 'token', Webhook_Token::get(), $url );
	}

	/**
	 * make_request function
	 *
	 * @param $method - http method allowed: POST, GET, PUT, DELETE, PATCH, HEAD, OPTIONS
	 * @param $endpoint - endpoint url
	 * @param array $body - body data
	 * @param array $headers - headers data
	 * @param boolean $send_json - send json data
	 * @return mixed|WP_Error
	 */
	public function make_request( $method, $endpoint, $body = [], array $headers = [], $send_json = false ) {
		$headers = array_replace_recursive( [
			'x-elementor-cookiez' => COOKIEZ_VERSION,
			'x-elementor-apps'    => 'cookiez',
		], $headers );

		$headers = array_replace_recursive(
			$headers,
			$this->is_connected() ? $this->generate_authentication_headers( $endpoint ) : []
		);

		$body = array_replace_recursive( $body, $this->get_site_info() );

		if ( $send_json ) {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
			$body = wp_json_encode( $body );
		}

		return $this->request(
			$method,
			$endpoint,
			[
				'timeout' => 100,
				'headers' => $headers,
				'body'    => $body,
			]
		);
	}

	/**
	 * get_client_base_url function
	 *
	 * @return string
	 */
	public static function get_client_base_url() {
		return apply_filters( 'cookiez_client_base_url', self::BASE_URL );
	}

	/**
	 * get_feedback_base_url function
	 *
	 * @return void
	 */
	public static function get_feedback_base_url() {
		return apply_filters( 'cookiez_feedback_base_url', self::BASE_URL_FEEDBACK );
	}

	/**
	 * get_remote_url function
	 *
	 * @param [type] $endpoint
	 * @return string
	 */
	private static function get_remote_url( $endpoint ): string {
		if ( strpos( $endpoint, 'feedback/' ) !== false ) {
			return self::get_feedback_base_url() . $endpoint;
		}
		return self::get_client_base_url() . $endpoint;
	}

	/**
	 * is_connected function
	 *
	 * @return boolean
	 */
	protected function is_connected(): bool {
		return Connect::is_connected();
	}

	/**
	 * add_bearer_token function
	 *
	 * @param array $headers - headers data
	 * @return array
	 */
	public function add_bearer_token( $headers ) {
		if ( $this->is_connected() ) {
			$headers['Authorization'] = 'Bearer ' . Connect::get_connect()->data()->get_access_token();
		}
		return $headers;
	}

	/**
	 * generate_authentication_headers
	 *
	 * @param [type] $endpoint
	 * @return array
	 */
	protected function generate_authentication_headers( $endpoint ): array {
		$headers = [
			'endpoint' => $endpoint,
		];

		return $this->add_bearer_token( $headers );
	}

	/**
	 * @throws Service_Exception
	 */
	protected function request( $method, $endpoint, $args = [] ) {
		$args['method'] = $method;

		$response = wp_remote_request(
			self::get_remote_url( $endpoint ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();

			return new WP_Error(
				$response->get_error_code(),
				is_array( $message ) ? join( ', ', $message ) : $message
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$response_code = (int) wp_remote_retrieve_response_code( $response );

		if ( ! $response_code ) {
			return new WP_Error( 500, 'No Response' );
		}

		// Server sent a success message without content.
		if ( 'null' === $body ) {
			$body = true;
		}

		// Return with no content on successful deletion of domain from service.
		if ( 204 === $response_code ) {
			return true;
		}

		if ( strpos( $endpoint, 'consent-logs/export' ) !== false ) {
			if ( ! in_array( $response_code, [ 200, 201 ], true ) ) {
				return new WP_Error( $response_code, wp_remote_retrieve_response_message( $response ) );
			}
			return $body;
		}

		$body = json_decode( $body );

		if ( false === $body ) {
			return new WP_Error( 422, 'Wrong Server Response' );
		}

		// If the token is invalid, refresh it and try again once only.
		if ( ! $this->refreshed && ! empty( $body->message ) && ( false !== strpos( $body->message, 'Invalid Token' ) ) ) {
			Connect::get_connect()->service()->renew_access_token();
			$this->refreshed = true;
			$args['headers'] = $this->add_bearer_token( $args['headers'] );
			return $this->request( $method, $endpoint, $args );
		}

		// If there is mismatch then trigger the mismatch flow explicitly.
		if ( ! $this->refreshed && ! empty( $body->message ) && ( false !== strpos( $body->message, 'site url mismatch' ) ) ) {
			Connect::get_connect()->data()->set_home_url( 'https://wrongurl' );
			return new WP_Error( 401, 'site url mismatch' );
		}

		if ( ! in_array( $response_code, [ 200, 201 ], true ) ) {
			// In case $as_array = true.
			$message = $body->message ?? wp_remote_retrieve_response_message( $response );
			$message = is_array( $message ) ? join( ', ', $message ) : $message;
			$code = isset( $body->code ) ? (int) $body->code : $response_code;

			return new WP_Error( $code, $message );
		}

		return $body;
	}

	/**
	 * get_site_data
	 * @return mixed|WP_Error  Site data
	 */
	public static function get_site_data() {
		return self::get_instance()->make_request( 'POST', 'stats' );
	}

	/**
	 * register_website
	 * @return mixed|WP_Error  Site data
	 */
	public static function register_website() {
		return self::get_instance()->make_request( 'POST', 'site' );
	}
}
