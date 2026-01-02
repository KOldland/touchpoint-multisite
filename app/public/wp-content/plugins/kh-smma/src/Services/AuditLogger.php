<?php
namespace KH_SMMA\Services;

use wpdb;

use function current_time;
use function get_current_user_id;
use function maybe_serialize;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuditLogger {
    /** @var wpdb */
    private $db;

    /** @var string */
    private $table;

    public function __construct( wpdb $db ) {
        $this->db    = $db;
        $this->table = $this->db->prefix . 'kh_smma_audit_log';
    }

    public function install() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->db->get_charset_collate();
        $sql             = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50) DEFAULT '',
            object_id bigint(20) unsigned DEFAULT 0,
            details longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY object_type (object_type)
        ) {$charset_collate};";

        \dbDelta( $sql );
    }

    public function log( $action, array $context = array() ) {
        $data = array(
            'user_id'    => isset( $context['user_id'] ) ? (int) $context['user_id'] : get_current_user_id(),
            'action'     => $action,
            'object_type'=> $context['object_type'] ?? '',
            'object_id'  => isset( $context['object_id'] ) ? (int) $context['object_id'] : 0,
            'details'    => maybe_serialize( $context['details'] ?? array() ),
            'created_at' => current_time( 'mysql' ),
        );

        $this->db->insert( $this->table, $data );
    }
}
