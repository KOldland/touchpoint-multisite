<?php

namespace KHM\Preview\Database\Repositories;

use wpdb;

class PreviewHitRepository {
    /** @var wpdb */
    private $db;

    public function __construct( wpdb $db = null ) {
        global $wpdb;
        $this->db = $db ?: $wpdb;
    }

    private function table(): string {
        return $this->db->prefix . 'khm_preview_hits';
    }

    public function insert( array $data ): int {
        $defaults = [
            'viewed_at' => current_time( 'mysql' ),
        ];
        $record = array_merge( $defaults, $data );
        $this->db->insert( $this->table(), $record );
        return (int) $this->db->insert_id;
    }

    public function get_by_link( int $link_id, int $limit = 20 ): array {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE link_id = %d ORDER BY viewed_at DESC LIMIT %d",
            $link_id,
            $limit
        );
        return $this->db->get_results( $sql, ARRAY_A ) ?: [];
    }
}
