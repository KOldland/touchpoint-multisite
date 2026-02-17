<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// Session path will be configured by 000-session-config.php mu-plugin
// This ensures it loads before any plugins try to start sessions

// Ensure WP_CONTENT_DIR is defined for Local / custom setups
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' );
}

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
$env_db_host = getenv( 'TEMP_DB_HOST' );
if ( ! $env_db_host ) {
	$env_db_host = getenv( 'DB_HOST' );
}

if ( $env_db_host ) {
	define( 'DB_HOST', $env_db_host );
} else {
	$local_run_base   = getenv( 'LOCAL_RUN_BASE' );
	$local_run_base   = $local_run_base ? $local_run_base : '/Users/krisoldland/Library/Application Support/Local/run';
	$socket_candidates = glob( rtrim( $local_run_base, '/' ) . '/*/mysql/mysqld.sock' );

	if ( ! empty( $socket_candidates ) ) {
		usort(
			$socket_candidates,
			static function ( $a, $b ) {
				return ( filemtime( $b ) ?: 0 ) <=> ( filemtime( $a ) ?: 0 );
			}
		);
		define( 'DB_HOST', 'localhost:' . $socket_candidates[0] );
	} else {
		// Fallback for environments using TCP MySQL on localhost.
		define( 'DB_HOST', '127.0.0.1' );
	}
}

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'Re`M]dotD/1;KJ.XTLK@N]eixXTaQsl7s&@Mc$^7)NKE$As(=&Rp~p<.s=(Q_Z+V' );
define( 'SECURE_AUTH_KEY',   '2TuBLb|V6uFmIe0MG4b_Lv$q-&U;49Pe{A1x]UJ07ssQKld6avxhGZZ1v_TWw%4I' );
define( 'LOGGED_IN_KEY',     ' q%T@V`];MZD-,w&9k4&8{ wr/1-ODh+9e;1~z9NFerWF;&iuvprvSqgPt@SSj6a' );
define( 'NONCE_KEY',         'a:6R5$Wu@U<6(T*4/j/b_>a?=PRkc[w1in1GM$kMO~IDuyk.KLdr99T<6-v^<A3I' );
define( 'AUTH_SALT',         'hj5_?;#}g]2g`7:AF;2u#q9bn66$?#p7c8^/cx`e$udi8i7+3M.t:elZ+9.A5_iP' );
define( 'SECURE_AUTH_SALT',  '#|lYWy@P?[iU|]dt%IO_LsoIQt!L,dd3%L3b9bm6UC{EGuz?r-L!dzn[d|dDl7.A' );
define( 'LOGGED_IN_SALT',    '.9c,?.T*NCOXXDjBpyA{+zi]6n(0NR3_$!0UwDoTi$[S#+.Gx?eS@Ex2z*G-eTjn' );
define( 'NONCE_SALT',        'e%;ksX[@,wAyhF,5jOyFNPJ}sRX^?mG.O#q}Ejq;&HIkt~lE!7PQh.bbH;#%FB-7' );
define( 'WP_CACHE_KEY_SALT', 'wFiS(+ (TJ)bzzI,gKBy/wC?l68ABb>#/`}&BdET+mgtW~Tu+MUNsuDy{aJJbi}I' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

// Only enable debug logging in development/local environments
$is_dev_environment = defined( 'WP_ENVIRONMENT_TYPE' ) && 
                      in_array( WP_ENVIRONMENT_TYPE, array( 'local', 'development' ), true );

if ( ! defined( 'WP_DEBUG_LOG' ) ) {
	define( 'WP_DEBUG_LOG', $is_dev_environment );
}

if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', false );
}

// Simple debug log rotation to prevent huge files in local dev.
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
	register_shutdown_function( function() {
		$log_path = WP_DEBUG_LOG;
		if ( is_string( $log_path ) && file_exists( $log_path ) ) {
			$max_bytes = 10 * 1024 * 1024; // 10MB
			$size = filesize( $log_path );
			if ( $size !== false && $size > $max_bytes ) {
				@file_put_contents( $log_path, '' );
			}
		}
	} );
}

// Toggle to fully disable KHM SEO social previews if needed.
define( 'KHM_SEO_DISABLE_SOCIAL_PREVIEW', false );
// Temporarily disable KHM SEO Elementor integration to stabilize the editor.
define( 'KHM_SEO_DISABLE_ELEMENTOR', true );
// Temporarily disable KHM SEO editor scripts to isolate editor hangs.
define( 'KHM_SEO_DISABLE_EDITOR', true );
// Temporarily disable heavy plugins on editor requests.
// NOTE: khm-plugin removed from this list - needed for AnswerCard Gutenberg block
define( 'KHM_EDITOR_DISABLED_PLUGINS', array(
    // 'khm-plugin/khm-plugin.php', // Removed - needed for AnswerCard block
) );
// Temporarily disable KH ad rendering.
define( 'KHM_DISABLE_ADS', true );
// Temporarily disable KHM data calls in Social Strip.
// define( 'KSS_DISABLE_KHM', true );

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
