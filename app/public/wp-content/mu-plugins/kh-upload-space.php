<?php
/**
 * KH Upload Space — Disable Multisite Upload Quota
 *
 * WordPress multisite enforces a 100 MB per-site upload quota by default.
 * This mu-plugin removes that limit entirely, allowing unlimited uploads.
 *
 * @package Touchpoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Override the per-site upload space limit.
 *
 * Returning a large number makes the upload space check always pass.
 * This does NOT disable the per-file upload_max_filesize / post_max_size PHP limits —
 * those are controlled server-side via php.ini.
 */
add_filter( 'pre_site_option_blog_upload_space', function() {
	return 99999;
} );

/**
 * Also filter the final calculated value for any code path
 * that calls get_space_allowed() directly.
 */
add_filter( 'get_space_allowed', function() {
	return 99999;
} );