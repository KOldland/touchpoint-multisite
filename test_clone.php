<?php
/**
 * Test script for AllocationService clone functionality.
 * Run from the command line: php test_clone.php
 *
 * Tests that:
 * 1. Taxonomies (tags, categories) are cloned
 * 2. Post meta (ACF/SEO fields) are cloned
 * 3. Featured image is cloned
 */

// Load WordPress
define( 'WP_USE_THEMES', false );

// Determine the path to wp-load.php
$wp_load_path = __DIR__ . '/app/public/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
    die( "Could not find WordPress at: $wp_load_path\n" );
}

$_SERVER['HTTP_HOST'] = 'touchpoint-multisite.local';
require_once $wp_load_path;

// Load the required classes
require_once __DIR__ . '/app/public/wp-content/plugins/kh-editorial-intelligence/src/Database/AllocationTable.php';
require_once __DIR__ . '/app/public/wp-content/plugins/kh-editorial-intelligence/src/Services/AllocationService.php';

use KH\Editorial\Services\AllocationService;
use KH\Editorial\Database\AllocationTable;

echo "========================================\n";
echo "AllocationService Clone Test\n";
echo "========================================\n\n";

// Switch to hub site (blog 1)
switch_to_blog(1);
echo "Current blog: " . get_current_blog_id() . " (" . get_bloginfo('name') . ")\n\n";

// Find a test post
$posts = get_posts([
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 1,
    'meta_query'     => [
        [
            'key'     => '_thumbnail_id',
            'compare' => 'EXISTS',
        ],
    ],
    'tax_query' => [
        [
            'taxonomy' => 'post_tag',
            'operator' => 'EXISTS',
        ],
    ],
]);

if ( empty( $posts ) ) {
    // Try without featured image requirement
    $posts = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
    ]);
}

if ( empty( $posts ) ) {
    die("ERROR: No published posts found to test with.\n");
}

$test_post = $posts[0];
$post_id   = $test_post->ID;
$title     = $test_post->post_title;

echo "Test post: #{$post_id} \"{$title}\"\n";

// Show origin data
echo "\n--- ORIGIN POST DATA ---\n";

// Taxonomies
$taxonomies = get_object_taxonomies( 'post' );
foreach ( $taxonomies as $tax ) {
    $terms = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'names' ] );
    echo "  {$tax}: " . ( $terms ? implode( ', ', $terms ) : '(none)' ) . "\n";
}

// Featured image
$thumb_id  = get_post_thumbnail_id( $post_id );
$thumb_url = get_the_post_thumbnail_url( $post_id, 'full' );
echo "  Featured image: " . ( $thumb_url ? $thumb_url : '(none)' ) . "\n";

// Post meta sample
$meta_keys = get_post_meta( $post_id );
$acf_keys  = array_filter( array_keys( $meta_keys ), function( $k ) {
    return str_starts_with( $k, 'field_' ) || str_starts_with( $k, '_field_' );
} );
echo "  ACF/SEO meta keys: " . ( $acf_keys ? implode( ', ', $acf_keys ) : '(none)' ) . "\n";
echo "  Total meta keys: " . count( $meta_keys ) . "\n";

// Get a target site
$service = new AllocationService();
$sites   = $service->get_available_sites();

if ( empty( $sites ) ) {
    die("ERROR: No target sites available.\n");
}

$target_site = $sites[0];
$target_blog_id = $target_site['blog_id'];
$target_slug    = $target_site['slug'];
$target_label   = $target_site['label'];

echo "\n--- TARGET SITE ---\n";
echo "  Slug: {$target_slug}\n";
echo "  Label: {$target_label}\n";
echo "  Blog ID: {$target_blog_id}\n";

// Check if already cloned — if so, delete the existing allocation first
$existing = AllocationTable::find( $post_id, $target_blog_id );
if ( $existing ) {
    echo "\nINFO: Post already cloned to this site (target_post_id={$existing['target_post_id']}). Deleting for clean test...\n";
    // Delete the target post
    switch_to_blog( $target_blog_id );
    wp_delete_post( (int) $existing['target_post_id'], true );
    restore_current_blog();
    // Delete allocation record
    global $wpdb;
    $table = AllocationTable::get_table_name();
    $wpdb->delete( $table, [ 'id' => $existing['id'] ] );
    echo "  Cleaned up.\n";
}

// Clone the post
echo "\n--- CLONING ---\n";
echo "  Calling clone_to_site( post_id={$post_id}, blog_id={$target_blog_id}, rewrite=false )...\n";

$result = $service->clone_to_site( $post_id, $target_blog_id, false );

echo "  Result:\n";
echo "    success: " . ( $result['success'] ? 'true' : 'false' ) . "\n";
echo "    message: " . $result['message'] . "\n";
if ( $result['success'] ) {
    echo "    target_post_id: " . $result['target_post_id'] . "\n";
    echo "    edit_url: " . $result['edit_url'] . "\n";
    echo "    rewrite_applied: " . ( $result['rewrite_applied'] ? 'true' : 'false' ) . "\n";
} else {
    echo "\n  TEST FAILED: Clone was not successful.\n";
    echo "  Error: " . ( $result['message'] ?? 'Unknown error' ) . "\n";
    exit(1);
}

// Verify the clone
echo "\n--- VERIFYING CLONE ---\n";
switch_to_blog( $target_blog_id );
$target_post_id = $result['target_post_id'];
$target_post = get_post( $target_post_id );

if ( ! $target_post ) {
    restore_current_blog();
    echo "  FAIL: Target post #{$target_post_id} not found on target site!\n";
    exit(1);
}

$passed  = 0;
$failed  = 0;

// Check 1: Post title matches
if ( $target_post->post_title === $title ) {
    echo "  ✓ Title matches: \"{$target_post->post_title}\"\n";
    $passed++;
} else {
    echo "  ✗ Title mismatch: expected \"{$title}\", got \"{$target_post->post_title}\"\n";
    $failed++;
}

// Check 2: Post status is draft
if ( $target_post->post_status === 'draft' ) {
    echo "  ✓ Status is draft\n";
    $passed++;
} else {
    echo "  ✗ Status is '{$target_post->post_status}', expected 'draft'\n";
    $failed++;
}

// Check 3: Taxonomies
foreach ( $taxonomies as $tax ) {
    $target_terms = wp_get_post_terms( $target_post_id, $tax, [ 'fields' => 'names' ] );
    $origin_terms = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'names' ] );
    $origin_terms_final = [];
    switch_to_blog(1);
    $origin_terms_final = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'names' ] );
    restore_current_blog();
    
    if ( $target_terms ) {
        echo "  ✓ {$tax} cloned: " . implode( ', ', $target_terms ) . "\n";
        $passed++;
    } elseif ( empty( $origin_terms_final ) ) {
        echo "  - {$tax}: (none on origin, none on target — OK)\n";
        $passed++;
    } else {
        echo "  ✗ {$tax} NOT cloned: expected " . implode( ', ', $origin_terms_final ) . ", got none\n";
        $failed++;
    }
}

// Check 4: Featured image
$target_thumb_id  = get_post_thumbnail_id( $target_post_id );
$target_thumb_url = get_the_post_thumbnail_url( $target_post_id, 'full' );
if ( $thumb_url ) {
    if ( $target_thumb_id && $target_thumb_url ) {
        echo "  ✓ Featured image cloned: {$target_thumb_url}\n";
        $passed++;
    } else {
        echo "  ✗ Featured image NOT cloned\n";
        $failed++;
    }
} else {
    echo "  - Featured image: (none on origin — OK)\n";
    $passed++;
}

// Check 5: Post meta (ACF fields)
$target_meta = get_post_meta( $target_post_id );
$target_acf_keys = array_filter( array_keys( $target_meta ), function( $k ) {
    return str_starts_with( $k, 'field_' ) || str_starts_with( $k, '_field_' );
} );
$target_meta_count = count( $target_meta );
$origin_meta_count = count( $meta_keys );

echo "  Target meta keys: {$target_meta_count} (origin: {$origin_meta_count})\n";
if ( $target_meta_count >= $origin_meta_count - 4 ) { // Allow for skipped keys (_edit_lock, etc.)
    echo "  ✓ Post meta count looks reasonable\n";
    $passed++;
} else {
    echo "  ✗ Post meta count mismatch: target={$target_meta_count}, origin={$origin_meta_count}\n";
    $failed++;
}

if ( $acf_keys ) {
    $missing_acf = array_diff( $acf_keys, array_keys( $target_meta ) );
    if ( empty( $missing_acf ) ) {
        echo "  ✓ ACF/SEO fields present: " . implode( ', ', array_intersect( $acf_keys, array_keys( $target_meta ) ) ) . "\n";
        $passed++;
    } else {
        echo "  ✗ Missing ACF/SEO fields: " . implode( ', ', $missing_acf ) . "\n";
        $failed++;
    }
}

restore_current_blog();

echo "\n========================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "========================================\n";

if ( $failed > 0 ) {
    echo "\n⚠️  Some tests failed. Review the output above.\n";
    exit(1);
} else {
    echo "\n✅ All tests passed!\n";
    exit(0);
}