<?php
namespace KH_SMMA\Services;

use KH_SMMA\Security\CredentialVault;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TokenRepository {
    const TABLE = 'kh_smma_tokens';

    /** @var wpdb */
    private $db;

    /** @var CredentialVault */
    private $vault;

    public function __construct( wpdb $db, CredentialVault $vault ) {
        $this->db    = $db;
        $this->vault = $vault;
    }

    public function install() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->db->get_charset_collate();
        $table_name      = $this->get_table_name();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            account_id bigint(20) unsigned NOT NULL,
            encrypted_token longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY account_id (account_id)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    public function save_token( $account_id, $token_data ) {
        $payload = array(
            'account_id'      => $account_id,
            'encrypted_token' => $this->vault->encrypt( $token_data ),
            'created_at'      => current_time( 'mysql' ),
            'updated_at'      => current_time( 'mysql' ),
        );

        $this->db->insert( $this->get_table_name(), $payload );

        return (int) $this->db->insert_id;
    }

    public function update_token( $token_id, $token_data ) {
        return $this->db->update(
            $this->get_table_name(),
            array(
                'encrypted_token' => $this->vault->encrypt( $token_data ),
                'updated_at'      => current_time( 'mysql' ),
            ),
            array( 'id' => $token_id )
        );
    }

    public function get_token( $token_id ) {
        if ( empty( $token_id ) ) {
            return null;
        }

        $row = $this->db->get_row( $this->db->prepare( 'SELECT encrypted_token FROM ' . $this->get_table_name() . ' WHERE id = %d', $token_id ) );
        if ( ! $row ) {
            return null;
        }

        return $this->vault->decrypt( $row->encrypted_token );
    }

    private function get_table_name() {
        return $this->db->prefix . self::TABLE;
    }
}
