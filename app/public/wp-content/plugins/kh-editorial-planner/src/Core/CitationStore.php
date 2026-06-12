<?php

namespace KH\Planner\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CitationStore — persistence layer for verified citations.
 *
 * Creates and manages the kh_planner_citations table, providing
 * CRUD operations for verified citations with tier, authority score,
 * and approval workflow — matching Dual GPT's fg_validated_citations.
 */
class CitationStore {

    const TABLE = 'kh_planner_citations';
    const DB_VERSION_KEY = 'kh_planner_citations_db_version';
    const DB_VERSION = '1.0.0';

    /**
     * Create or update the citations table.
     * Called on plugin activation.
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id BIGINT(20) NOT NULL,
            job_id VARCHAR(36) NULL,
            brief_id VARCHAR(36) NULL,
            title TEXT,
            lead_author VARCHAR(255),
            additional_authors TEXT,
            publication VARCHAR(255),
            organisation VARCHAR(255),
            year SMALLINT,
            publication_date VARCHAR(50),
            url TEXT,
            apa_string TEXT,
            apa_details_available TINYINT(1) DEFAULT 1,
            passage_snippet TEXT,
            source_type VARCHAR(50),
            tier VARCHAR(10),
            authority_score FLOAT DEFAULT 0.0,
            confidence FLOAT DEFAULT 0.5,
            sponsored TINYINT(1) DEFAULT 0,
            approved TINYINT(1) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_brief_id (brief_id),
            INDEX idx_approved (approved),
            INDEX idx_tier (tier),
            INDEX idx_authority (authority_score)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );
    }

    /**
     * Save a batch of verified citations for a session.
     *
     * @param int    $session_id Planner session post ID.
     * @param string $job_id     Optional job ID these citations came from.
     * @param array  $citations  Array of citation data from CitationVerifier.
     * @return int Number of citations inserted.
     */
    public function save_citations( $session_id, $job_id, array $citations ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $inserted = 0;

        foreach ( $citations as $citation ) {
            if ( empty( $citation['url'] ) ) {
                continue;
            }

            $result = $wpdb->insert( $table, [
                'id'                  => wp_generate_uuid4(),
                'session_id'          => $session_id,
                'job_id'              => $job_id,
                'title'               => $citation['title'] ?? '',
                'lead_author'         => $citation['lead_author'] ?? '',
                'additional_authors'  => $citation['additional_authors'] ?? '',
                'publication'         => $citation['publication'] ?? '',
                'organisation'        => $citation['organisation'] ?? '',
                'year'                => ! empty( $citation['year'] ) ? (int) $citation['year'] : null,
                'publication_date'    => $citation['publication_date'] ?? '',
                'url'                 => $citation['url'],
                'apa_string'          => $citation['apa_string'] ?? 'details_unavailable',
                'apa_details_available' => ! empty( $citation['apa_string'] ) && $citation['apa_string'] !== 'details_unavailable' ? 1 : 0,
                'passage_snippet'     => $citation['passage_snippet'] ?? '',
                'source_type'         => $citation['source_type'] ?? 'industry',
                'tier'                => $citation['tier'] ?? 'tier3',
                'authority_score'     => $citation['authority_score'] ?? 0.5,
                'confidence'          => $citation['confidence'] ?? 0.5,
                'sponsored'           => ! empty( $citation['sponsored'] ) ? 1 : 0,
                'approved'            => null, // Pending review
            ] );

            if ( $result !== false ) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Get citations for a session.
     *
     * @param int  $session_id   Planner session post ID.
     * @param bool $approved_only Only return approved citations.
     * @return array
     */
    public function get_citations_by_session( $session_id, $approved_only = false ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $sql = "SELECT * FROM $table WHERE session_id = %d";
        $args = [ $session_id ];

        if ( $approved_only ) {
            $sql .= " AND approved = 1";
        }

        $sql .= " ORDER BY authority_score DESC";

        return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
    }

    /**
     * Get citations linked to a brief.
     *
     * @param string $brief_id The brief UUID.
     * @return array
     */
    public function get_citations_by_brief( $brief_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE brief_id = %s ORDER BY authority_score DESC",
            $brief_id
        ), ARRAY_A );
    }

    /**
     * Approve a citation.
     */
    public function approve_citation( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->update( $table, [ 'approved' => 1 ], [ 'id' => $id ] );
    }

    /**
     * Reject a citation.
     */
    public function reject_citation( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->update( $table, [ 'approved' => 0 ], [ 'id' => $id ] );
    }

    /**
     * Link citations to a brief.
     *
     * @param string $brief_id
     * @param int    $session_id
     */
    public function link_citations_to_brief( $brief_id, $session_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->update(
            $table,
            [ 'brief_id' => $brief_id ],
            [ 'session_id' => $session_id, 'brief_id' => null ]
        );
    }

    /**
     * Delete citations for a session.
     */
    public function delete_citations_by_session( $session_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->delete( $table, [ 'session_id' => $session_id ] );
    }
}