<?php
/**
 * Migration Script: Centralized Content Registry Table Creation
 * File: app/public/migrations/20260630_create_content_registry.php
 */

defined('ABSPATH') || exit;

function run_content_registry_migration() {
    global $wpdb;

    // Table name must use base_prefix to stay centralized on the main network database
    $table_name = $wpdb->base_prefix . 'content_registry';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        parent_post_id BIGINT(20) UNSIGNED DEFAULT NULL,
        target_blog_id BIGINT(20) UNSIGNED NOT NULL,
        slug VARCHAR(200) NOT NULL,
        article_status ENUM('Summary','Framework','Draft','Scheduled','Live') NOT NULL,
        title TEXT NOT NULL,
        content_body LONGTEXT NULL,
        excerpt TEXT NULL,
        seo_metadata JSON NULL,
        sponsor_commentary LONGTEXT NULL,
        smma_flags JSON NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        INDEX idx_target_blog_slug_status (target_blog_id, slug, article_status),
        INDEX idx_parent_post (parent_post_id),
        INDEX idx_updated_at (updated_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Execute migration
run_content_registry_migration();