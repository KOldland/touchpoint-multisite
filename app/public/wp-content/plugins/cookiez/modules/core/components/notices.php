<?php

namespace Cookiez\Modules\Core\Components;

use Cookiez\Classes\Utils\Notice_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Notices
 *
 * Handles the registration and display of notices.
 */
class Notices {
	const AJAX_ACTION = 'cookiez_admin_notice_dismiss';

	/**
	 * @var Notice_Base[] $notices
	 */
	public array $notices = [];

	/**
	 * Register a notice.
	 *
	 * @param Notice_Base $notice_instance
	 * @return void
	 */
	public function register_notice( Notice_Base $notice_instance ) {
		$this->notices[ $notice_instance->get_id() ] = $notice_instance;
	}

	/**
	 * Show notices.
	 *
	 * @return void
	 */
	public function show_notices() {
		foreach ( $this->notices as $notice ) {
			$notice->maybe_show_notice();
		}
	}

	/**
	 * Handle dismiss request from the React app.
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		if ( empty( $_REQUEST['notice_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Invalid ID' ] );
		}

		$notice = $this->get_notice( sanitize_text_field( wp_unslash( $_REQUEST['notice_id'] ) ) );
		if ( ! $notice ) {
			wp_send_json_error( [ 'message' => 'Invalid ID' ] );
		}

		$notice->handle_dismiss();

		wp_send_json_success( [] );
	}

	/**
	 * @param string $sanitize_text_field
	 *
	 * @return Notice_Base|null
	 */
	private function get_notice( string $sanitize_text_field ): ?Notice_Base {
		return $this->notices[ $sanitize_text_field ] ?? null;
	}

	/**
	 * @return void
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_notices', [ $this, 'show_notices' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'handle_dismiss' ] );
		add_action( 'init', function () {
			do_action( 'cookiez_register_notices', $this );
		} );
	}
}
