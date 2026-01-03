<?php
/**
 * Capture fatal errors during admin requests to avoid silent WSODs.
 */

register_shutdown_function(
	static function () {
		$error = error_get_last();
		if ( ! $error ) {
			return;
		}

		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR );
		if ( ! in_array( $error['type'], $fatal_types, true ) ) {
			return;
		}

		$log_path = defined( 'WP_CONTENT_DIR' )
			? WP_CONTENT_DIR . '/debug.log'
			: dirname( __DIR__ ) . '/debug.log';

		$stamp = gmdate( 'c' );
		$message = sprintf(
			'[%s] FATAL type=%s message=%s file=%s line=%s',
			$stamp,
			$error['type'],
			$error['message'],
			$error['file'],
			$error['line']
		);

		error_log( $message );
		@file_put_contents( $log_path, $message . "\n", FILE_APPEND );
	}
);
