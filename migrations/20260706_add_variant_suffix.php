<?php
/**
 * Migration: Add variant_suffix field to content_registry
 * 
 * This field stores the variant identifier (A, B, C...) for cloned articles.
 * Parent articles have NULL variant_suffix, children have 'A', 'B', etc.
 */

defined('ABSPATH') || exit;

function run_add_variant_suffix_migration() {
    global $wpdb;
    
    $table_name = $wpdb->base_prefix . 'content_registry';
    
    // Add variant_suffix column if it doesn't exist
    $column_exists = $wpdb->get_row(
        $wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} WHERE Field = %s",
            'variant_suffix'
        )
    );
    
    if (!$column_exists) {
        $wpdb->query(
            "ALTER TABLE {$table_name} 
             ADD COLUMN variant_suffix VARCHAR(10) DEFAULT NULL,
             ADD INDEX idx_variant (variant_suffix)"
        );
    }
    
    // Add wp_post_id column if it doesn't exist
    $column_exists = $wpdb->get_row(
        $wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} WHERE Field = %s",
            'wp_post_id'
        )
    );
    
    if (!$column_exists) {
        $wpdb->query(
            "ALTER TABLE {$table_name} 
             ADD COLUMN wp_post_id BIGINT UNSIGNED DEFAULT NULL"
        );
    }
}

run_add_variant_suffix_migration();