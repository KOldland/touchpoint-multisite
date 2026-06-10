<?php

namespace Cookiez\Modules\Core\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Pointers
 */
class Pointers {
	const DISMISSED_POINTERS_META_KEY = 'cookiez_dismissed_pointers';

	/**
	 * Dismiss pointers
	 *
	 * @return void {JSON}
	 * @throws WP_Error
	 */
	public function dismiss_pointers() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cookiez-pointer-dismissed' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
		}

		$pointer = sanitize_text_field( wp_unslash( $_POST['data']['pointer'] ?? '' ) );

		if ( empty( $pointer ) ) {
			wp_send_json_error( [ 'message' => 'The pointer id must be provided' ] );
		}

		$pointer = explode( ',', $pointer );

		$user_dismissed_meta = get_user_meta( get_current_user_id(), self::DISMISSED_POINTERS_META_KEY, true );

		if ( ! $user_dismissed_meta ) {
			$user_dismissed_meta = [];
		}

		foreach ( $pointer as $item ) {
			$user_dismissed_meta[ $item ] = true;
		}

		update_user_meta( get_current_user_id(), self::DISMISSED_POINTERS_META_KEY, $user_dismissed_meta );

		wp_send_json_success( [] );
	}

	/**
	 * Check if a pointer is dismissed
	 *
	 * @param string $slug The pointer slug
	 * @return bool
	 */
	public static function is_dismissed( string $slug ): bool {
		$meta = (array) get_user_meta( get_current_user_id(), self::DISMISSED_POINTERS_META_KEY, true );

		return key_exists( $slug, $meta );
	}

	/**
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_ajax_cookiez_pointer_dismissed', [ $this, 'dismiss_pointers' ] );
	}
}
