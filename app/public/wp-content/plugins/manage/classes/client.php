<?php
namespace Manage\Classes;

use Manage\Modules\Connect\Module as Connect;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Client {
	const BASE_URL = 'https://my.elementor.com/manage/api/v1/';

	private bool $refreshed = false;

	public static ?Client $instance = null;

	public static function get_instance(): ?Client {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function get_site_info(): array {
		return [
			// Which API version to use
			'app_version' => MANAGE_VERSION,
			// Which language to return.
			'site_lang' => get_bloginfo( 'language' ),
			// Site to connect
			'site_url' => trailingslashit( home_url() ),
		];
	}

	public static function get_client_id(): ?string {
		return Connect::get_connect()->data()->get_client_id();
	}

	public function make_request( $method, $endpoint, $body = [], array $headers = [], $send_json = false ) {
		$headers = array_replace_recursive(
			$headers,
			static::is_connected() ? $this->generate_authentication_headers( $endpoint ) : []
		);

		$body = array_replace_recursive( $body, $this->get_site_info() );

		if ( $send_json ) {
			$headers['Content-Type'] = 'application/json';
			$body = wp_json_encode( $body );
		}

		return $this->request(
			$method,
			$endpoint,
			[
				'timeout' => 100,
				'headers' => $headers,
				'body' => $body,
			]
		);
	}

	private static function get_remote_url( $endpoint ): string {
		return self::BASE_URL . $endpoint;
	}

	public static function is_connected(): bool {
		return Connect::is_connected();
	}

	public function add_bearer_token( $headers ) {
		if ( static::is_connected() ) {
			$headers['Authorization'] = 'Bearer ' . Connect::get_connect()->data()->get_access_token();
		}
		return $headers;
	}

	protected function generate_authentication_headers( $endpoint ): array {
		$headers = [
			'endpoint' => $endpoint,
		];

		return $this->add_bearer_token( $headers );
	}

	protected function request( $method, $endpoint, $args = [] ) {
		$args['method'] = $method;

		$response = wp_remote_request( self::get_remote_url( $endpoint ), $args );

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();

			return new \WP_Error( $response->get_error_code(), is_array( $message ) ? join( ', ', $message ) : $message );
		}

		$body = wp_remote_retrieve_body( $response );
		$response_code = (int) wp_remote_retrieve_response_code( $response );

		if ( ! $response_code ) {
			return new \WP_Error( \WP_Http::INTERNAL_SERVER_ERROR, 'No Response' );
		}

		// Server sent a success message without content.
		if ( 'null' === $body || 'OK' === $body ) {
			$body = true;
		}

		// Return with no content on successfully deletion of domain from service.
		if ( in_array( $response_code, [ \WP_Http::CREATED, \WP_Http::NO_CONTENT ], true ) ) {
			$body = true;

			return $body;
		}

		$body = json_decode( $body );

		if ( empty( $body ) ) {
			return new \WP_Error( \WP_Http::UNPROCESSABLE_ENTITY, 'Wrong Server Response' );
		}

		// If the token is invalid, refresh it and try again once only.
		//if ( ! $this->refreshed && ! empty( $body->message ) && ( false !== strpos( $body->message, 'Invalid Token' ) ) ) {
		if ( ! $this->refreshed && \WP_Http::UNAUTHORIZED === $response_code ) {
			Connect::get_connect()->service()->renew_access_token();

			$this->refreshed = true;
			$args['headers'] = $this->add_bearer_token( $args['headers'] );

			return $this->request( $method, $endpoint, $args );
		}

		// If there is mismatch then trigger the mismatch flow explicitly.
		if ( ! $this->refreshed && ! empty( $body->message ) && ( false !== strpos( $body->message, 'site url mismatch' ) ) ) {
			Connect::get_connect()->data()->set_home_url( 'https://wrongurl' );

			return new \WP_Error( \WP_Http::UNAUTHORIZED, 'site url mismatch' );
		}

		if ( \WP_Http::OK !== $response_code ) {
			// In case $as_array = true.
			$message = $body->message ?? wp_remote_retrieve_response_message( $response );
			$message = is_array( $message ) ? join( ', ', $message ) : $message;
			$code = isset( $body->code ) ? (int) $body->code : $response_code;

			return new \WP_Error( $code, $message );
		}

		return $body;
	}

	public static function register_website() {
		$home_url = trailingslashit( home_url() );

		return self::get_instance()->make_request( 'POST', 'sites', [
			'rest_url' => get_rest_url( null, 'manage/v1' ),
			'home_url' => $home_url,
		] );
	}
}
