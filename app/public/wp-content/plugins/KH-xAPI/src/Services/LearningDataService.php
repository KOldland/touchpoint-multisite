<?php
namespace KH\XAPI\Services;

class LearningDataService {
    private string $table;

    private \wpdb $wpdb;

    public function __construct( \wpdb $db = null ) {
        global $wpdb;

        $this->wpdb  = $db ?: $wpdb;
        $this->table = $this->wpdb->prefix . 'kh_xapi_completions';
    }

    public function record_completion( array $args ): int {
        $defaults = [
            'content_id'  => 0,
            'user_id'     => 0,
            'status'      => null,
            'percentage'  => null,
            'score'       => null,
            'timespent'   => null,
            'statement'   => null,
            'registration'=> null,
        ];

        $data = wp_parse_args( $args, $defaults );

        $this->wpdb->insert(
            $this->table,
            [
                'content_id'  => $data['content_id'],
                'user_id'     => $data['user_id'],
                'status'      => $data['status'],
                'percentage'  => $data['percentage'],
                'score'       => $data['score'],
                'timespent'   => $data['timespent'],
                'statement'   => $data['statement'],
                'registration'=> $data['registration'],
            ],
            [ '%d', '%d', '%s', '%f', '%f', '%d', '%s', '%s' ]
        );

        return (int) $this->wpdb->insert_id;
    }

    public function query_completions( array $args = [] ): array {
        $args = $this->normalize_args( $args );

        [ $where_sql, $values ] = $this->build_where( $args );

        $sql = "SELECT * FROM {$this->table} {$where_sql}";

        $order   = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';
        $orderby = in_array( $args['orderby'], [ 'recorded_at', 'score', 'percentage' ], true ) ? $args['orderby'] : 'recorded_at';

        $sql .= " ORDER BY {$orderby} {$order}";

        if ( ! empty( $args['limit'] ) ) {
            $sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );
        }

        if ( ! empty( $values ) ) {
            $sql = $this->wpdb->prepare( $sql, $values );
        }

        return $this->wpdb->get_results( $sql );
    }

    public function get_summary( array $args = [] ): array {
        $selects = [
            'total'       => 'COUNT(id) as total',
            'completed'   => "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed",
            'in_progress' => "SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) AS in_progress",
            'avg_score'   => 'AVG(score) AS avg_score',
            'avg_percent' => 'AVG(percentage) AS avg_percent',
        ];

        $args = $this->normalize_args( $args );

        [ $where_sql, $values ] = $this->build_where( $args );

        $sql = 'SELECT ' . implode( ', ', $selects ) . " FROM {$this->table} {$where_sql}";

        if ( ! empty( $values ) ) {
            $sql = $this->wpdb->prepare( $sql, $values );
        }

        $row = $this->wpdb->get_row( $sql, ARRAY_A );

        return wp_parse_args(
            $row,
            [
                'total'       => 0,
                'completed'   => 0,
                'in_progress' => 0,
                'avg_score'   => 0,
                'avg_percent' => 0,
            ]
        );
    }

    public function aggregate_by( string $dimension, array $args = [] ): array {
        $args       = $this->normalize_args( $args );
        $dimension  = in_array( $dimension, [ 'content', 'user', 'status' ], true ) ? $dimension : 'content';
        $select     = '';
        $group_by   = '';
        $headers    = [];

        switch ( $dimension ) {
            case 'user':
                $select   = 'user_id, COUNT(id) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed, AVG(score) AS avg_score';
                $group_by = 'user_id';
                $headers  = [ 'user_id', 'total', 'completed', 'avg_score' ];
                break;
            case 'status':
                $select   = 'status, COUNT(id) as total';
                $group_by = 'status';
                $headers  = [ 'status', 'total' ];
                break;
            case 'content':
            default:
                $select   = 'content_id, COUNT(id) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed, AVG(percentage) AS avg_percent';
                $group_by = 'content_id';
                $headers  = [ 'content_id', 'total', 'completed', 'avg_percent' ];
                break;
        }

        [ $where_sql, $values ] = $this->build_where( $args );

        $sql = "SELECT {$select} FROM {$this->table} {$where_sql} GROUP BY {$group_by}";

        if ( ! empty( $values ) ) {
            $sql = $this->wpdb->prepare( $sql, $values );
        }

        $rows = $this->wpdb->get_results( $sql, ARRAY_A );

        return [
            'headers' => $headers,
            'rows'    => $rows,
        ];
    }

    private function normalize_args( array $args ): array {
        $defaults = [
            'user_id'     => null,
            'content_id'  => null,
            'status'      => null,
            'registration'=> null,
            'date_from'   => null,
            'date_to'     => null,
            'limit'       => 100,
            'offset'      => 0,
            'orderby'     => 'recorded_at',
            'order'       => 'DESC',
        ];

        $parsed = wp_parse_args( $args, $defaults );

        // Guardrails for heavy queries
        $parsed['limit']  = max( 1, min( 500, (int) $parsed['limit'] ) );
        $parsed['offset'] = max( 0, (int) $parsed['offset'] );

        return $parsed;
    }

    private function build_where( array $args ): array {
        $where  = [];
        $values = [];

        foreach ( [ 'user_id', 'content_id' ] as $int_field ) {
            if ( ! empty( $args[ $int_field ] ) ) {
                $where[]  = "{$int_field} = %d";
                $values[] = (int) $args[ $int_field ];
            }
        }

        foreach ( [ 'status', 'registration' ] as $str_field ) {
            if ( ! empty( $args[ $str_field ] ) ) {
                $where[]  = "{$str_field} = %s";
                $values[] = $args[ $str_field ];
            }
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'recorded_at >= %s';
            $values[] = gmdate( 'Y-m-d H:i:s', strtotime( $args['date_from'] ) );
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'recorded_at <= %s';
            $values[] = gmdate( 'Y-m-d H:i:s', strtotime( $args['date_to'] ) );
        }

        if ( empty( $where ) ) {
            return [ '', [] ];
        }

        return [ ' WHERE ' . implode( ' AND ', $where ), $values ];
    }
}
