<?php

namespace KHM\Services;

use KHM\Services\MembershipRepository;

/**
 * AnswerCard Library Service
 *
 * Stores saved AnswerCards for members.
 */
class AnswerCardLibraryService {

    private MembershipRepository $memberships;
    private string $library_table;

    public function __construct( MembershipRepository $memberships ) {
        global $wpdb;
        $this->memberships = $memberships;
        $this->library_table = $wpdb->prefix . 'khm_answercard_library';
    }

    public function table_exists(): bool {
        global $wpdb;
        $table = $this->library_table;
        return $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
    }

    public function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->library_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id int(11) NOT NULL,
            answer_card_id varchar(64) NOT NULL,
            member_id int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_member_answercard (member_id, answer_card_id),
            KEY idx_member (member_id),
            KEY idx_post (post_id),
            KEY idx_answer_card (answer_card_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function save_to_library( int $member_id, int $post_id, string $answer_card_id ): bool {
        global $wpdb;

        if ( $this->is_saved( $member_id, $answer_card_id ) ) {
            return true;
        }

        $result = $wpdb->insert(
            $this->library_table,
            [
                'post_id' => $post_id,
                'answer_card_id' => $answer_card_id,
                'member_id' => $member_id,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%d', '%s' ]
        );

        return false !== $result;
    }

    public function remove_from_library( int $member_id, string $answer_card_id ): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->library_table,
            [
                'member_id' => $member_id,
                'answer_card_id' => $answer_card_id,
            ],
            [ '%d', '%s' ]
        );

        return false !== $result;
    }

    public function is_saved( int $member_id, string $answer_card_id ): bool {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->library_table} WHERE member_id = %d AND answer_card_id = %s",
            $member_id,
            $answer_card_id
        ) );

        return $count > 0;
    }

    public function get_member_answercards( int $member_id, array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args( $args, $defaults );

        // Whitelist allowed orderby columns to prevent SQL injection
        $allowed_orderby = [ 'created_at', 'updated_at', 'id' ];
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';

        $order_clause = sprintf(
            'ORDER BY %s %s',
            $orderby,
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        $sql = "
            SELECT *
            FROM {$this->library_table}
            WHERE member_id = %d
            {$order_clause}
            LIMIT %d OFFSET %d
        ";

        return $wpdb->get_results( $wpdb->prepare( $sql, $member_id, $args['limit'], $args['offset'] ) );
    }
}
