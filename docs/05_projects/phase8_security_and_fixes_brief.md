# Phase 8 Implementation Brief: Security & Access Control + Critical Fix

## Overview

This brief covers two work items:

1. **Critical Fix 2.14** — Add canonical URL injection to the Virtual Router Engine
2. **Phase 8** — Implement Security & Access Control for child sites in the multisite network

---

## WORK ITEM 1: Fix 2.14 — Canonical URL Protection

**Source:** Audit finding in `content-router.php`

**File to modify:**
- `app/public/app/public/wp-content/mu-plugins/content-router.php`

**The Problem:**
The `ContentRegistryVirtualRouter` class's `inject_seo_metadata()` method handles title/description meta tags for Yoast and RankMath, but does **not** inject a `<link rel="canonical" href="..." />` tag into the document `<head>`. Without this, search engines may index child site URLs as duplicate content, diluting SEO authority for the main site.

**The Fix:**
Add a `wp_head` action inside `inject_seo_metadata()` that renders a canonical URL pointing to the main site domain with the article slug:

```php
// Canonical URL — point search engines to the main site
add_action('wp_head', function() use ($seo, $slug) {
    $canonical_url = get_site_url(1) . '/' . $slug . '/';
    echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
}, 1);
```

**Requirements:**
- The `$slug` variable must be accessible within `inject_seo_metadata()`. Currently the method only receives `$seo`. You will need to either:
  - Pass `$slug` as a second parameter from line 82 (`$this->inject_seo_metadata($seo, $slug)`)
  - Or store `$slug` as a class property
- Priority `1` to ensure it fires before other SEO plugins
- The canonical URL format: `https://[main-site-domain]/[slug]/`

**Verification:**
```bash
# After implementing, load a virtual article page and check the <head>
curl -s https://childsite.com/test-article | grep -i canonical
# Expected: <link rel="canonical" href="https://mainsite.com/test-article/" />
```

---

## WORK ITEM 2: Phase 8 — Security & Access Control

### Context

This is a multisite network where child sites should be **read-only**. All content creation, editing, and publishing flows through the main site (blog_id=1) via the Centralized Content Registry. Child sites consume content via the Virtual Router Engine (`content-router.php`) — they should never write to `wp_posts`, upload media, or have publishing capabilities.

### Architecture Principles

1. **Main site (blog_id=1)** — Full admin access. All content operations permitted.
2. **Child sites (blog_id >= 2)** — Read-only. No posting, no editing, no media uploads.
3. **Super Admins** — Bypass all restrictions (can access any site's admin).
3. **Non-super-admin users on child sites** — Blocked from wp-admin entirely (redirected to main site or logged out).

---

### Phase 8 Audit Check Map

#### 8.1 Admin Lockdown

| # | Audit Item | Spec |
|---|-----------|-------|
| 8.1.1 | `admin_init` redirect for non-main-site non-superadmins | If `get_current_blog_id() !== 1` and user is NOT a super admin, redirect away from wp-admin |
| 8.1.2 | Redirect target | Either `wp_redirect(get_site_url(1))` or `wp_logout()` + redirect to login page |
| 8.1.3 | AJAX/API exemption | Must NOT block `admin-ajax.php`, `wp-json/*`, or cron requests |
| 8.1.4 | Login page exemption | Must NOT redirect `wp-login.php` |

**Implementation approach:**
- Create a new mu-plugin at `app/public/app/public/wp-content/mu-plugins/child-site-lockdown.php`
- Hook into `admin_init` with a very early priority (0 or 1)
- Logged-in users with `is_super_admin()` return early (bypass)
- Users on blog_id 1 return early (bypass)
- All others get redirected to main site with a message

```php
<?php
/**
 * Plugin Name: KH Child Site Lockdown
 * Description: Enforces read-only mode on child sites. Redirects non-super-admin users from wp-admin on child sites.
 * Version: 1.0.0
 * Author: KHM Dev
 */

defined('ABSPATH') || exit;

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
```

#### 8.2 Capability Stripping

| # | Audit Item | Spec |
|---|-----------|-------|
| 8.2.1 | `user_has_cap` filter on child sites | Revoke `edit_posts`, `publish_posts`, `delete_posts`, `edit_others_posts`, `delete_others_posts` |
| 8.2.2 | Also strip `upload_files` | Prevent media uploads |
| 8.2.3 | Also strip `edit_published_posts`, `delete_published_posts` | Prevent modification of any existing posts |
| 8.2.4 | Also strip `create_posts` | Prevent creation of any post type |
| 8.2.5 | Super admin bypass | Super admins keep all capabilities |

**Implementation approach:**
- Add to the same mu-plugin file
- Filter `user_has_cap` to selectively revoke capabilities on child sites

```php
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
```

#### 8.3 Media Upload Disabled on Child Sites

| # | Audit Item | Spec |
|---|-----------|-------|
| 8.3.1 | `upload_files` capability stripped | Handled by 8.2 above |
| 8.3.2 | Media library UI suppressed on child sites | Hide admin menu items, block direct access to `upload.php` |
| 8.3.3 | `wp_handle_upload_prefilter` hook on child sites | Additional guard to block file uploads |

```php
add_filter('wp_handle_upload_prefilter', function(array $file): array {
    if (is_super_admin()) return $file;
    if (get_current_blog_id() === 1) return $file;
    
    $file['error'] = __('Uploads are disabled on child sites.', 'kh-child-lockdown');
    return $file;
});
```

#### 8.4 Plugin UI Suppression on Child Sites

| # | Audit Item | Spec |
|---|-----------|-------|
| 8.4.1 | All plugin admin screens wrapped in `get_current_blog_id() !== 1` return guard | Per-plugin basis |
| 8.4.2 | KH Content Registry admin pages hidden on child sites | `kh-content-registry` plugin should check blog_id before registering admin pages |
| 8.4.3 | KH Editorial Intelligence admin pages hidden on child sites | `kh-editorial-intelligence` plugin should check blog_id before registering admin pages |
| 8.4.4 | Dashboard widgets removed on child sites | Remove unnecessary dashboard widgets |

**Implementation approach:**
This needs to be done per-plugin. The most efficient way is to add a shared guard function in the mu-plugin, then wrap admin menu/page registrations in a blog_id check:

```php
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
```

Then in each plugin's admin registration:

```php
if (function_exists('khm_can_show_admin_ui') && !khm_can_show_admin_ui()) {
    return; // Skip admin UI on child sites
}
```

---

### Files to Create/Modify

| File | Action | Purpose |
|------|--------|---------|
| `app/public/app/public/wp-content/mu-plugins/child-site-lockdown.php` | **CREATE** | Central lockdown mu-plugin (items 8.1-8.3) |
| `app/public/app/public/wp-content/mu-plugins/content-router.php` | **MODIFY** | Fix 2.14 — Add canonical URL injection |
| `app/public/app/public/wp-content/plugins/kh-content-registry/kh-content-registry.php` | **MODIFY** | Wrap admin registrations in blog_id guard (8.4) |
| `app/public/app/public/wp-content/plugins/kh-editorial-intelligence/kh-editorial-intelligence.php` | **MODIFY** | Wrap admin registrations in blog_id guard (8.4) |
| `app/public/app/public/wp-content/plugins/kh-editorial-intelligence/src/Admin/EditorialAdmin.php` | **MODIFY** | Add khm_can_show_admin_ui() guard to admin init |
| `app/public/app/public/wp-content/plugins/kh-editorial-intelligence/src/Admin/SearchAnalyticsPage.php` | **MODIFY** | Add khm_can_show_admin_ui() guard to admin page registration |

---

### Deployment Notes

1. The mu-plugin (`child-site-lockdown.php`) activates immediately on deployment — no WP activation step needed.
2. Test on a **staging child site first** — a bug could lock all admins out of all child site admin panels.
3. Ensure the `REST_REQUEST` exemption is correct for your REST API prefix. If you use a custom prefix, adjust accordingly.
4. Consider adding a `khm_lockdown_bypass` filter for edge cases where a specific admin page needs to be accessible on a child site.

---

### Verification Commands

```bash
# 1. Test admin redirect on child site (non-super-admin)
# Log in as a subscriber/editor on a child site
# Navigate to wp-admin — should redirect to main site

# 2. Test capability stripping
wp --path=app/public/app/public eval "
switch_to_blog(2);
\$user = wp_get_current_user();
echo 'edit_posts: ' . (user_can(\$user, 'edit_posts') ? 'YES' : 'NO') . PHP_EOL;
echo 'upload_files: ' . (user_can(\$user, 'upload_files') ? 'YES' : 'NO') . PHP_EOL;
echo 'publish_posts: ' . (user_can(\$user, 'publish_posts') ? 'YES' : 'NO') . PHP_EOL;
restore_current_blog();
"

# 3. Verify main site unaffected
wp --path=app/public/app/public eval "
\$user = wp_get_current_user();
echo 'Main site — edit_posts: ' . (user_can(\$user, 'edit_posts') ? 'YES' : 'NO') . PHP_EOL;
"

# 4. Test media upload block on child site
wp --path=app/public/app/public eval "
switch_to_blog(2);
\$upload = wp_upload_dir();
echo 'Upload dir: ' . \$upload['baseurl'] . PHP_EOL;
echo 'Can upload: ' . (user_can(wp_get_current_user(), 'upload_files') ? 'YES (FAIL)' : 'NO (PASS)') . PHP_EOL;
restore_current_blog();
"

# 5. Test canonical URL
curl -s 'https://childsite.com/test-article' | grep -i 'rel="canonical"'
# Expected: <link rel="canonical" href="https://mainsite.com/test-article/" />

# 6. Verify REST API still accessible on child site
curl -s -X POST 'https://childsite.com/wp-json/kh-editorial/v1/public/search' \
  -H 'Content-Type: application/json' \
  -d '{"query":"test"}' | head -c 200
```

---

### Key Files Reference

| File Path | Purpose |
|-----------|---------|
| `app/public/app/public/wp-content/mu-plugins/content-router.php` | Virtual router — needs canonical URL fix (2.14) |
| `app/public/app/public/wp-content/mu-plugins/` | Target directory for new `child-site-lockdown.php` |
| `app/public/app/public/wp-content/plugins/kh-content-registry/kh-content-registry.php` | Content registry plugin bootstrap — needs admin UI guard |
| `app/public/app/public/wp-content/plugins/kh-content-registry/src/Services/ContentRegistryService.php` | Core CRUD service (for reference, not modified) |
| `app/public/app/public/wp-content/plugins/kh-editorial-intelligence/kh-editorial-intelligence.php` | Editorial intelligence bootstrap — needs admin UI guard |
| `app/public/app/public/wp-content/plugins/kh-editorial-intelligence/src/Admin/EditorialAdmin.php` | Editorial admin page registration |
| `app/public/app/public/wp-content/plugins/kh-editorial-intelligence/src/Admin/SearchAnalyticsPage.php` | Search analytics admin page |
| `app/public/app/public/wp-content/plugins/kh-content-registry/kh-content-registry.php` | Bootstrap with REST route registration (needs admin context guard) |

---

### Testing Checklist

- [ ] Non-super-admin on child site redirected from wp-admin
- [ ] Super admin on child site can access wp-admin
- [ ] Users on main site (blog_id=1) unaffected
- [ ] AJAX requests on child sites not blocked
- [ ] REST API requests on child sites not blocked
- [ ] Cron jobs on child sites not blocked
- [ ] Login page on child sites not blocked
- [ ] `edit_posts` capability stripped on child site
- [ ] `upload_files` capability stripped on child site
- [ ] `publish_posts` capability stripped on child site
- [ ] Media upload blocked on child site (with descriptive error)
- [ ] KH Content Registry admin pages hidden on child sites
- [ ] KH Editorial Intelligence admin pages hidden on child sites
- [ ] Search Analytics page hidden on child sites
- [ ] Canonical `<link>` tag present on virtual article pages
- [ ] Canonical URL points to main site domain with correct slug
- [ ] Canonical tag does NOT appear on non-virtual pages (main site pages)