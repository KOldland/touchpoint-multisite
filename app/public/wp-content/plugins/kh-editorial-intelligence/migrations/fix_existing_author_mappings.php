<?php
/**
 * One-shot migration: Remap multi_author references on existing cloned posts.
 *
 * Reads the allocation table, finds cloned posts on target sites that still
 * reference hub-site multi_author IDs, and re-clones the author profiles to
 * the target site.
 *
 * Run via: wp eval-file migrations/fix_existing_author_mappings.php
 * Or visit: /wp-admin/admin-ajax.php?action=kh_fix_author_mappings (once wired)
 */

// Only run from CLI
if ( php_sapi_name() !== 'cli' && ! defined( 'WP_CLI' ) ) {
    echo "This script must be run via WP-CLI.\n";
    echo "Usage: wp eval-file wp-content/plugins/kh-editorial-intelligence/migrations/fix_existing_author_mappings.php\n";
    exit(1);
}

// Load WordPress
if ( ! defined( 'ABSPATH' ) ) {
    $wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
    if ( file_exists( $wp_load ) ) {
        require_once $wp_load;
    } else {
        echo "Could not find wp-load.php. Run this script from WP-CLI.\n";
        exit(1);
    }
}

echo "=== Author Mapping Fix for Existing Allocations ===\n\n";

global $wpdb;
$table = $wpdb->base_prefix . 'kh_content_allocation';

// 1. List all allocations
$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A );

if ( empty( $rows ) ) {
    echo "No allocations found in the database.\n";
    exit(0);
}

echo "Found " . count( $rows ) . " allocation(s):\n";
foreach ( $rows as $row ) {
    echo "  ID={$row['id']} | Origin Post {$row['origin_post_id']} → Blog {$row['target_blog_id']} | Target Post {$row['target_post_id']} | Rewrite: {$row['rewrite_applied']}\n";
}

echo "\n=== Processing allocations ===\n\n";

$author_ids = []; // hub_author_id => [ name, title, company, bio, photo_id, slug ]
$mappings   = []; // allocation_id => [ target_blog_id, target_post_id, old_author_ids => [new_author_ids] ]

foreach ( $rows as $row ) {
    $origin_blog_id  = (int) $row['origin_blog_id'];
    $origin_post_id  = (int) $row['origin_post_id'];
    $target_blog_id  = (int) $row['target_blog_id'];
    $target_post_id  = (int) $row['target_post_id'];

    echo "── Allocation #{$row['id']}: Post {$origin_post_id} → Blog {$target_blog_id} (Target Post {$target_post_id}) ──\n";

    // Get origin author IDs from the hub
    switch_to_blog( $origin_blog_id );

    $origin_author_ids = [];
    if ( function_exists( 'get_field' ) ) {
        $raw = get_field( 'field_multi_author_relationship', $origin_post_id, false );
        if ( is_array( $raw ) ) {
            $origin_author_ids = array_map( 'intval', $raw );
        }
    }
    if ( empty( $origin_author_ids ) ) {
        $raw = get_post_meta( $origin_post_id, 'field_multi_author_relationship', true );
        if ( is_array( $raw ) ) {
            $origin_author_ids = array_map( 'intval', $raw );
        } elseif ( is_numeric( $raw ) ) {
            $origin_author_ids = [ (int) $raw ];
        }
    }

    echo "  Origin author IDs: " . ( ! empty( $origin_author_ids ) ? implode( ', ', $origin_author_ids ) : '(none - using post_author)' ) . "\n";

    // Cache author data from hub
    $hub_author_data = [];
    foreach ( $origin_author_ids as $hub_id ) {
        if ( ! isset( $author_ids[ $hub_id ] ) ) {
            $author_ids[ $hub_id ] = [
                'name'    => get_post_meta( $hub_id, 'author_name', true ),
                'title'   => get_post_meta( $hub_id, 'author_title', true ),
                'company' => get_post_meta( $hub_id, 'author_company', true ),
                'bio'     => get_post_meta( $hub_id, 'author_bio', true ),
                'photo'   => get_post_meta( $hub_id, 'author_photo', true ),
                'slug'    => get_post_field( 'post_name', $hub_id ),
            ];
        }
        $hub_author_data[ $hub_id ] = $author_ids[ $hub_id ];
    }

    restore_current_blog();

    if ( empty( $origin_author_ids ) ) {
        echo "  No multi_author relationship on origin post. Skipping.\n\n";
        continue;
    }

    // Switch to target blog and fix
    switch_to_blog( $target_blog_id );

    // Check current author mapping on target post
    $current_target_authors = get_post_meta( $target_post_id, 'field_multi_author_relationship', true );
    echo "  Current target author IDs: " . ( is_array( $current_target_authors ) ? implode( ', ', $current_target_authors ) : ( $current_target_authors ?: '(empty)' ) ) . "\n";

    // Check if any of these IDs actually resolve on the target site
    $broken = [];
    foreach ( $origin_author_ids as $hub_id ) {
        // Check if this hub ID accidentally works on target (same ID, different post)
        $test = get_post( $hub_id );
        if ( ! $test || $test->post_type !== 'multi_author' ) {
            $broken[] = $hub_id;
        }
    }

    if ( empty( $broken ) ) {
        echo "  All author IDs resolve on target site. No fix needed.\n\n";
        restore_current_blog();
        continue;
    }

    echo "  Broken author IDs (don't exist on target): " . implode( ', ', $broken ) . "\n";

    // Clone author profiles
    $new_author_ids = [];

    foreach ( $origin_author_ids as $hub_id ) {
        $data = $hub_author_data[ $hub_id ];
        if ( empty( $data['name'] ) ) {
            echo "    Hub author {$hub_id}: No name, skipping\n";
            continue;
        }

        // Check if already exists on target by name
        $existing = get_posts( [
            'post_type'      => 'multi_author',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query' => [ [ 'key' => 'author_name', 'value' => $data['name'] ] ],
        ] );

        if ( ! empty( $existing ) ) {
            $local_id = (int) $existing[0];
            echo "    Hub author {$hub_id} ({$data['name']}) → Already exists on target as ID {$local_id}\n";
            $new_author_ids[] = $local_id;
            continue;
        }

        // Create author on target
        $new_id = wp_insert_post( [
            'post_type'   => 'multi_author',
            'post_title'  => $data['name'],
            'post_name'   => $data['slug'],
            'post_status' => 'publish',
            'meta_input'  => [
                'author_name'    => $data['name'],
                'author_title'   => $data['title'],
                'author_company' => $data['company'],
                'author_bio'     => $data['bio'],
            ],
        ] );

        if ( is_wp_error( $new_id ) || ! $new_id ) {
            echo "    Hub author {$hub_id} ({$data['name']}) → FAILED to create: " . ( is_wp_error( $new_id ) ? $new_id->get_error_message() : 'unknown' ) . "\n";
            continue;
        }

        $new_id = (int) $new_id;
        echo "    Hub author {$hub_id} ({$data['name']}) → Created on target as ID {$new_id}\n";

        // Clone author photo if present
        if ( $data['photo'] ) {
            switch_to_blog( $origin_blog_id );
            $photo_url = wp_get_attachment_url( (int) $data['photo'] );
            restore_current_blog();

            if ( $photo_url ) {
                $tmp = download_url( $photo_url );
                if ( ! is_wp_error( $tmp ) ) {
                    $file_array = [
                        'name'     => basename( parse_url( $photo_url, PHP_URL_PATH ) ),
                        'tmp_name' => $tmp,
                    ];
                    $att_id = media_handle_sideload( $file_array, 0 );
                    if ( ! is_wp_error( $att_id ) ) {
                        update_post_meta( $new_id, 'author_photo', (int) $att_id );
                        echo "      Photo cloned (attachment ID: $att_id)\n";
                    } else {
                        @unlink( $tmp );
                        echo "      Photo sideload failed\n";
                    }
                }
            }
        }

        $new_author_ids[] = $new_id;
    }

    // Rewrite the post meta
    if ( ! empty( $new_author_ids ) ) {
        update_post_meta( $target_post_id, 'field_multi_author_relationship', $new_author_ids );
        update_post_meta( $target_post_id, '_field_multi_author_relationship', 'field_multi_author_relationship' );
        update_post_meta( $target_post_id, 'authors', $new_author_ids );
        update_post_meta( $target_post_id, '_authors', 'field_multi_author_relationship' );

        if ( function_exists( 'update_field' ) ) {
            update_field( 'field_multi_author_relationship', $new_author_ids, $target_post_id );
        }

        echo "  ✓ Rewrote target post {$target_post_id}: author IDs [" . implode( ', ', $new_author_ids ) . "]\n\n";
    }

    restore_current_blog();
}

echo "=== Migration complete ===\n";