<?php

namespace KHM\Preview\Database\Repositories;

use wpdb;

class PreviewLinkRepository {
    /** @var wpdb */
    private $db;

    public function __construct( wpdb $db = null ) {
        global $wpdb;
        $this->db = $db ?: $wpdb;
    }

    private function table(): string {
        return $this->db->prefix . 'khm_preview_links';
    }

    public function insert( array $data ): int {
        $defaults = [
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ];
        $record = array_merge( $defaults, $data );

        $this->db->insert( $this->table(), $record );

        return (int) $this->db->insert_id;
    }

    public function find_by_token_hash( string $hash ): ?array {
        $sql = $this->db->prepare( "SELECT * FROM {$this->table()} WHERE token_hash = %s", $hash );
        $row = $this->db->get_row( $sql, ARRAY_A );
        return $row ?: null;
    }

    public function find_active_by_post( int $post_id ): ?array {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE post_id = %d AND status = %s AND expires_at > %s ORDER BY id DESC LIMIT 1",
            $post_id,
            'active',
            current_time( 'mysql' )
        );
        $row = $this->db->get_row( $sql, ARRAY_A );
        return $row ?: null;
    }

    public function update_status( int $id, string $status ): bool {
        return (bool) $this->db->update(
            $this->table(),
            [
                'status'     => $status,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ]
        );
    }

    public function update_expiration( int $id, string $expires_at ): bool {
        return (bool) $this->db->update(
            $this->table(),
            [
                'expires_at' => $expires_at,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ]
        );
    }

    public function find( int $id ): ?array {
        $sql = $this->db->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id );
        $row = $this->db->get_row( $sql, ARRAY_A );
        return $row ?: null;
    }
}
