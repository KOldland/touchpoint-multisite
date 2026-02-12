<?php

namespace KHM\Admin;

use KHM\Services\LevelPriceResolver;

class PriceValidationAjax {
	public function register(): void {
		add_action( 'wp_ajax_khm_validate_stripe_price', [ $this, 'handle' ] );
	}

	public function handle(): void {
		check_ajax_referer( 'khm_validate_stripe_price', 'nonce' );

		if ( ! current_user_can( 'manage_khm' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'khm-membership' ) ], 403 );
		}

		$price_id = isset( $_POST['price_id'] ) ? sanitize_text_field( wp_unslash( $_POST['price_id'] ) ) : '';
		$this->log_validation_event( 'attempt', $price_id );
		if ( $price_id === '' ) {
			$this->log_validation_event( 'missing_price_id', $price_id );
			wp_send_json_error( [ 'message' => __( 'Price ID is required.', 'khm-membership' ) ], 400 );
		}

		if ( ! preg_match( LevelPriceResolver::PRICE_ID_REGEX, $price_id ) ) {
			$this->log_validation_event( 'invalid_format', $price_id );
			wp_send_json_error( [ 'message' => __( 'Invalid Stripe Price ID format.', 'khm-membership' ) ], 400 );
		}

		$secret = get_option( 'khm_stripe_secret_key', '' );
		if ( empty( $secret ) ) {
			$this->log_validation_event( 'stripe_not_configured', $price_id );
			wp_send_json_error( [ 'message' => __( 'Stripe is not configured.', 'khm-membership' ) ], 400 );
		}

		$cache_key = 'khm_price_validate_' . md5( $price_id );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			if ( ! empty( $cached['ok'] ) ) {
				wp_send_json_success( $cached['data'] );
			}
			wp_send_json_error( [ 'message' => $cached['message'] ?? __( 'Price ID not found in Stripe.', 'khm-membership' ) ], 404 );
		}

		try {
			\Stripe\Stripe::setApiKey( $secret );
			$price = \Stripe\Price::retrieve( $price_id );
		} catch ( \Throwable $e ) {
			$this->log_validation_event( 'stripe_lookup_failed', $price_id, $e->getMessage() );
			set_transient( $cache_key, [ 'ok' => false, 'message' => __( 'Price ID not found in Stripe.', 'khm-membership' ) ], 5 * MINUTE_IN_SECONDS );
			wp_send_json_error( [ 'message' => __( 'Price ID not found in Stripe.', 'khm-membership' ) ], 404 );
		}

		$is_recurring = is_object( $price ) && isset( $price->recurring ) && $price->recurring;
		if ( ! $is_recurring ) {
			$this->log_validation_event( 'price_not_recurring', $price_id );
			set_transient( $cache_key, [ 'ok' => false, 'message' => __( 'Price is not recurring. Use a recurring price for memberships.', 'khm-membership' ) ], 5 * MINUTE_IN_SECONDS );
			wp_send_json_error( [ 'message' => __( 'Price is not recurring. Use a recurring price for memberships.', 'khm-membership' ) ], 400 );
		}

		$livemode = is_object( $price ) && isset( $price->livemode ) ? (bool) $price->livemode : null;
		$currency = is_object( $price ) && isset( $price->currency ) ? strtoupper( (string) $price->currency ) : null;
		$interval = is_object( $price ) && isset( $price->recurring->interval ) ? (string) $price->recurring->interval : null;

		$this->log_validation_event( 'validated', $price_id, null, $livemode );

		$dashboard_base = $livemode === false ? 'https://dashboard.stripe.com/test/prices/' : 'https://dashboard.stripe.com/prices/';
		$dashboard_url  = $dashboard_base . rawurlencode( $price_id );

		$data = [
			'message'       => __( 'Valid price ID.', 'khm-membership' ),
			'livemode'      => $livemode,
			'currency'      => $currency,
			'interval'      => $interval,
			'dashboard_url' => $dashboard_url,
		];
		set_transient( $cache_key, [ 'ok' => true, 'data' => $data ], 5 * MINUTE_IN_SECONDS );
		wp_send_json_success( $data );
	}

	private function log_validation_event( string $event, string $price_id, ?string $error_message = null, ?bool $livemode = null ): void {
		$user_id = get_current_user_id();
		$context = [
			'event'   => $event,
			'price'   => $price_id,
			'user_id' => $user_id,
		];
		if ( null !== $livemode ) {
			$context['livemode'] = $livemode ? 'live' : 'test';
		}
		if ( $error_message ) {
			$context['error'] = $error_message;
		}

		error_log( 'KHM price validation: ' . wp_json_encode( $context ) );

		if ( function_exists( '\Sentry\addBreadcrumb' ) ) {
			\Sentry\addBreadcrumb(
				[
					'category' => 'khm.admin.price_validation',
					'message'  => $event,
					'level'    => $error_message ? 'error' : 'info',
					'data'     => $context,
				]
			);
		}
	}
}
