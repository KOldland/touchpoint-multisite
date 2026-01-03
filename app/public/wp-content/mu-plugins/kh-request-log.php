<?php
/**
 * Log admin request timings to diagnose WSOD hangs.
 */

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	return;
}

$kh_log_path = WP_CONTENT_DIR . '/debug-wsod.log';
$kh_start = microtime( true );
$kh_uri = $_SERVER['REQUEST_URI'] ?? '';
$kh_bytes = 0;
$kh_stamp = gmdate( 'c' );

@file_put_contents( $kh_log_path, '[' . $kh_stamp . '] START ' . $kh_uri . "\n", FILE_APPEND );

// Track output size for the post-new screen to detect blank responses.
if ( strpos( $kh_uri, '/wp-admin/post-new.php' ) !== false ) {
	$kh_bytes = 0;
	ob_start(
		static function ( $buffer ) use ( &$kh_bytes ) {
			$kh_bytes += strlen( $buffer );
			return $buffer;
		}
	);
}

if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) {
	add_action(
		'admin_init',
		static function () use ( $kh_log_path ) {
			@file_put_contents( $kh_log_path, '[' . gmdate( 'c' ) . '] admin_init' . "\n", FILE_APPEND );
		}
	);
	add_action(
		'current_screen',
		static function ( $screen ) use ( $kh_log_path ) {
			$screen_id = is_object( $screen ) && isset( $screen->id ) ? $screen->id : 'unknown';
			@file_put_contents( $kh_log_path, '[' . gmdate( 'c' ) . '] current_screen ' . $screen_id . "\n", FILE_APPEND );
		}
	);
	add_action(
		'load-post-new.php',
		static function () use ( $kh_log_path ) {
			@file_put_contents( $kh_log_path, '[' . gmdate( 'c' ) . '] load-post-new.php' . "\n", FILE_APPEND );
		}
	);
	add_action(
		'admin_head',
		static function () use ( $kh_log_path ) {
			$level = function_exists( 'ob_get_level' ) ? ob_get_level() : -1;
			$sent = headers_sent() ? 'yes' : 'no';
			@file_put_contents(
				$kh_log_path,
				'[' . gmdate( 'c' ) . '] admin_head ob_level=' . $level . ' headers_sent=' . $sent . "\n",
				FILE_APPEND
			);
		}
	);
}

register_shutdown_function(
	static function () use ( $kh_log_path, $kh_start, $kh_uri, $kh_bytes ) {
		$elapsed = round( ( microtime( true ) - $kh_start ) * 1000, 1 );
		$stamp = gmdate( 'c' );
		$level = function_exists( 'ob_get_level' ) ? ob_get_level() : -1;
		$bytes = $kh_bytes;
		if ( $bytes === 0 && function_exists( 'ob_get_length' ) ) {
			$current = ob_get_length();
			if ( $current !== false ) {
				$bytes = $current;
			}
		}
		@file_put_contents(
			$kh_log_path,
			'[' . $stamp . '] END ' . $kh_uri . ' ' . $elapsed . 'ms bytes=' . $bytes . ' ob_level=' . $level . "\n",
			FILE_APPEND
		);
	}
);
