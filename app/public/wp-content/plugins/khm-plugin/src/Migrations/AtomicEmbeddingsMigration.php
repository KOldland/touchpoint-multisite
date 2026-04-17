<?php
/**
 * Atomic Embeddings Database Migration
 *
 * Creates the wp_atomic_embeddings table for storing OpenAI text embeddings
 * against each atomic_article post. Kept in a dedicated table (not post meta)
 * so the RAG search endpoint can do a single bulk read of all embeddings
 * without hitting the post meta API for every row.
 *
 * Schema:
 *   post_id    BIGINT UNSIGNED NOT NULL PRIMARY KEY   FK → wp_posts.ID
 *   embedding  MEDIUMTEXT NOT NULL                    JSON float array (1 536 dims)
 *   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
 *
 * Usage:
 *   AtomicEmbeddingsMigration::run();   // idempotent, safe to call on every plugin activation
 *
 * @package KHM\Migrations
 */

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic Embeddings Migration
 */
class AtomicEmbeddingsMigration {

    /**
     * Table name without WP prefix.
     */
    const TABLE = 'atomic_embeddings';

    /**
     * Return the full table name with WP prefix.
     *
     * @return string
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Create (or upgrade) the table.
     * Uses dbDelta so it is safe to call repeatedly.
     *
     * @return void
     */
    public static function run(): void {
        global $wpdb;

        $table      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        // Two spaces before column definitions required by dbDelta.
        $sql = "CREATE TABLE {$table} (
  post_id BIGINT(20) UNSIGNED NOT NULL,
  embedding MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id)
) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Drop the table entirely (used on plugin uninstall).
     *
     * @return void
     */
    public static function drop(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_name() );
    }
}
