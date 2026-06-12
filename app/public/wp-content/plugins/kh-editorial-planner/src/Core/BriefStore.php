<?php

namespace KH\Planner\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BriefStore — persistence layer for framework briefs.
 *
 * Creates and manages the kh_planner_briefs table, storing
 * the full framework brief (generated during finalize_session)
 * with its associated citations, scoring, and metadata.
 *
 * Mirrors Dual GPT's fg_briefs + fg_exports tables.
 */
class BriefStore {

    const TABLE = 'kh_planner_briefs';
    const DB_VERSION_KEY = 'kh_planner_briefs_db_version';
    const DB_VERSION = '1.0.0';

    /**
     * Create or update the briefs table.
     * Called on plugin activation.
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id BIGINT(20) NOT NULL,
            title VARCHAR(500),
            overview TEXT,
            context TEXT,
            key_themes JSON,
            citations JSON,
            writer_guidance JSON,
            scoring JSON,
            metadata JSON,
            produced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_produced (produced_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );
    }

    /**
     * Save a framework brief.
     *
     * @param int    $session_id Planner session post ID.
     * @param array  $data       {
     *     @type string $title          Brief title.
     *     @type string $overview       Executive overview.
     *     @type string $context        Research context.
     *     @type array  $key_themes     Prioritised themes.
     *     @type array  $citations      Citation IDs or data.
     *     @type array  $writer_guidance Writer instructions.
     *     @type array  $scoring        Quality/confidence scores.
     *     @type array  $metadata       Extra metadata.
     * }
     * @return string|false The brief UUID on success, false on failure.
     */
    public function save_brief( $session_id, array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $brief_id = wp_generate_uuid4();

        $result = $wpdb->insert( $table, [
            'id'             => $brief_id,
            'session_id'     => $session_id,
            'title'          => $data['title'] ?? '',
            'overview'       => $data['overview'] ?? '',
            'context'        => $data['context'] ?? '',
            'key_themes'     => wp_json_encode( $data['key_themes'] ?? [] ),
            'citations'      => wp_json_encode( $data['citations'] ?? [] ),
            'writer_guidance'=> wp_json_encode( $data['writer_guidance'] ?? [] ),
            'scoring'        => wp_json_encode( $data['scoring'] ?? [] ),
            'metadata'       => wp_json_encode( $data['metadata'] ?? [] ),
        ] );

        if ( $result === false ) {
            error_log( '[BriefStore] Failed to save brief: ' . $wpdb->last_error );
            return false;
        }

        return $brief_id;
    }

    /**
     * Get a brief by its UUID.
     *
     * @param string $brief_id
     * @return array|null
     */
    public function get_brief( $brief_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %s", $brief_id
        ), ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        // Decode JSON fields
        foreach ( [ 'key_themes', 'citations', 'writer_guidance', 'scoring', 'metadata' ] as $field ) {
            if ( ! empty( $row[ $field ] ) ) {
                $row[ $field ] = json_decode( $row[ $field ], true );
            }
        }

        return $row;
    }

    /**
     * Get the latest brief for a session.
     *
     * @param int $session_id
     * @return array|null
     */
    public function get_brief_by_session( $session_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %d ORDER BY produced_at DESC LIMIT 1",
            $session_id
        ), ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        foreach ( [ 'key_themes', 'citations', 'writer_guidance', 'scoring', 'metadata' ] as $field ) {
            if ( ! empty( $row[ $field ] ) ) {
                $row[ $field ] = json_decode( $row[ $field ], true );
            }
        }

        return $row;
    }

    /**
     * Store an export record (file reference for a brief).
     *
     * @param string $brief_id
     * @param string $format   'docx' or 'html'
     * @param string $file_url
     * @param string $file_path
     * @return int|false
     */
    public function store_export( $brief_id, $format, $file_url, $file_path ) {
        global $wpdb;

        return $wpdb->insert( $wpdb->prefix . 'kh_planner_exports', [
            'brief_id' => $brief_id,
            'format'   => $format,
            'file_url' => $file_url,
            'file_path'=> $file_path,
        ] );
    }

    /**
     * Create the exports table.
     */
    public static function create_exports_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'kh_planner_exports';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brief_id VARCHAR(36),
            format VARCHAR(20),
            file_url TEXT,
            file_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_brief_id (brief_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}