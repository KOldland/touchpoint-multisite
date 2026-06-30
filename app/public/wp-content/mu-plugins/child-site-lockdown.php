<?php
/**
 * Plugin Name: KH Child Site Lockdown
 * Description: Enforces read-only mode on child sites. Redirects non-super-admin users from wp-admin on child sites.
 * Version: 1.0.0
 * Author: KHM Dev
 */

defined('ABSPATH') || exit;

/**
 * Check if current site can render admin UI.
 * 
 * @return bool True if admin UI should be shown.
 */
function khm_can_show_admin_ui(): bool {
    if (is_super_admin()) return true;
    if (get_current_blog_id() === 1) return true;
    return false;
}

/**
 * 8.1.1 Admin Lockdown - Redirect non-main-site non-superadmins from wp-admin
 */
add_action('admin_init', function() {
    // Never block these
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('DOING_CRON') && DOING_CRON) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if ($GLOBALS['pagenow'] === 'wp-login.php') return;

    // Super admins bypass all restrictions
    if (is_super_admin()) return;

    // Main site bypass
    if (get_current_blog_id() === 1) return;

    // Child site — redirect
    wp_safe_redirect(add_query_arg('lockdown', '1', get_site_url(1)));
    exit;
}, 0);

/**
 * 8.2.1 Capability Stripping - Revoke editing/publishing capabilities on child sites
 */
add_filter('user_has_cap', function(array $allcaps, array $caps, array $args): array {
    if (is_super_admin()) return $allcaps;
    if (get_current_blog_id() === 1) return $allcaps;
    
    $revoke = [
        'edit_posts', 'edit_others_posts', 'edit_published_posts',
        'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts',
        'create_posts', 'upload_files',
        'edit_pages', 'edit_others_pages', 'edit_published_pages',
        'publish_pages', 'delete_pages', 'delete_others_pages', 'delete_published_pages',
    ];
    
    foreach ($revoke as $cap) {
        $allcaps[$cap] = false;
    }
    
    return $allcaps;
}, 10, 3);

/**
 * 8.3.2 Media Upload Blocking - Additional guard to block file uploads on child sites
 */
add_filter('wp_handle_upload_prefilter', function(array $file): array {
    if (is_super_admin()) return $file;
    if (get_current_blog_id() === 1) return $file;
    
    $file['error'] = __('Uploads are disabled on child sites.', 'kh-child-lockdown');
    return $file;
});