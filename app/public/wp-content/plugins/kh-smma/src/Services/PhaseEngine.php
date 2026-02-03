<?php
namespace KH_SMMA\Services;

use WP_Error;
use wpdb;

use function absint;
use function add_action;
use function current_time;
use function get_option;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PhaseEngine {
    const CATALOG_TABLE = 'kh_smma_event_catalog';
    const EVENT_TABLE   = 'kh_smma_user_event';
    const SCORE_TABLE   = 'kh_smma_user_phase_score';

    /** @var wpdb */
    private $db;

    /** @var array */
    private $phase_totals = array(
        'Attention'   => 72,
        'Antagonistic'=> 90,
        'Anxiety'     => 108,
        'Acceptance'  => 90,
    );

    public function __construct( wpdb $db ) {
        $this->db = $db;
    }

    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'kh_smma_phase_aggregate', array( $this, 'aggregate_all' ) );
    }

    public function install(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $this->db->get_charset_collate();
        $catalog = $this->table( self::CATALOG_TABLE );
        $events  = $this->table( self::EVENT_TABLE );
        $scores  = $this->table( self::SCORE_TABLE );

        $sql = "CREATE TABLE {$catalog} (
            event_id varchar(191) NOT NULL,
            label text NOT NULL,
            points int NOT NULL,
            phase_tag varchar(40) NOT NULL,
            biases longtext NULL,
            default_decay_days int NOT NULL DEFAULT 30,
            PRIMARY KEY  (event_id)
        ) {$charset};

        CREATE TABLE {$events} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            event_type varchar(191) NOT NULL,
            event_points int NOT NULL,
            phase_tag varchar(40) NOT NULL,
            bias_tags longtext NULL,
            source varchar(191) NULL,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset};

        CREATE TABLE {$scores} (
            user_id bigint(20) unsigned NOT NULL,
            attention_points double NOT NULL DEFAULT 0,
            antagonistic_points double NOT NULL DEFAULT 0,
            anxiety_points double NOT NULL DEFAULT 0,
            acceptance_points double NOT NULL DEFAULT 0,
            assigned_phase varchar(40) NULL,
            norm_scores longtext NULL,
            top_events longtext NULL,
            computed_at datetime NOT NULL,
            PRIMARY KEY  (user_id)
        ) {$charset};";

        dbDelta( $sql );

        if ( $this->is_catalog_empty() ) {
            $default_path = KH_SMMA_PATH . 'resources/event_catalog.csv';
            if ( file_exists( $default_path ) ) {
                $this->import_event_catalog( $default_path );
            }
        }
    }

    public function register_routes(): void {
        register_rest_route(
            'kh-smma/v1',
            '/user-phase',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_user_phase_request' ),
                'permission_callback' => array( $this, 'can_access_phase' ),
                'args'                => array(
                    'user_id' => array(
                        'required'          => true,
                        'validate_callback' => 'is_numeric',
                    ),
                ),
            )
        );
    }

    public function handle_user_phase_request( $request ) {
        $user_id = absint( $request->get_param( 'user_id' ) );
        if ( ! $user_id ) {
            return new WP_Error( 'kh_smma_invalid_user', 'Invalid user_id', array( 'status' => 400 ) );
        }

        $payload = $this->get_user_phase( $user_id );
        return rest_ensure_response( $payload );
    }

    public function can_access_phase( $request ): bool {
        if ( current_user_can( 'kh_smma_view_queue' ) || current_user_can( 'manage_options' ) ) {
            return true;
        }

        $service_token = get_option( 'kh_smma_service_token' );
        if ( ! $service_token ) {
            return false;
        }

        $header_token = $request->get_header( 'x-kh-service-token' );
        if ( ! $header_token ) {
            $header_token = $request->get_header( 'x-service-token' );
        }

        return ! empty( $header_token ) && hash_equals( $service_token, $header_token );
    }

    public function record_event( int $user_id, string $event_id, string $source = '', array $metadata = array() ) {
        $user_id  = absint( $user_id );
        $event_id = sanitize_text_field( $event_id );
        $source   = sanitize_text_field( $source );

        if ( ! $user_id || ! $event_id ) {
            return new WP_Error( 'kh_smma_invalid_event', 'Missing user_id or event_id' );
        }

        $catalog = $this->get_catalog_event( $event_id );
        if ( ! $catalog ) {
            return new WP_Error( 'kh_smma_unknown_event', 'Unknown event_id' );
        }

        $inserted = $this->db->insert(
            $this->table( self::EVENT_TABLE ),
            array(
                'user_id'      => $user_id,
                'event_type'   => $event_id,
                'event_points' => (int) $catalog['points'],
                'phase_tag'    => $catalog['phase_tag'],
                'bias_tags'    => $catalog['biases'],
                'source'       => $source,
                'metadata'     => wp_json_encode( $metadata ),
                'created_at'   => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'kh_smma_event_insert_failed', 'Failed to record event' );
        }

        return (int) $this->db->insert_id;
    }

    public function aggregate_all(): void {
        $events_table = $this->table( self::EVENT_TABLE );
        $user_ids     = $this->db->get_col( "SELECT DISTINCT user_id FROM {$events_table}" );

        if ( empty( $user_ids ) ) {
            return;
        }

        foreach ( $user_ids as $user_id ) {
            $this->aggregate_user( (int) $user_id );
        }
    }

    public function aggregate_user( int $user_id ): array {
        $scores = $this->calculate_scores( $user_id );
        $norm   = $this->normalize_scores( $scores );
        $phase  = $this->assign_phase( $user_id, $norm );
        $top    = $this->get_top_events( $user_id );

        $this->upsert_score( $user_id, $scores, $norm, $phase, $top );

        return array(
            'user_id'       => $user_id,
            'scores'        => $scores,
            'norm_scores'   => $norm,
            'assigned_phase'=> $phase,
            'top_events'    => $top,
            'computed_at'   => current_time( 'mysql', true ),
        );
    }

    public function get_user_phase( int $user_id ): array {
        $scores_table = $this->table( self::SCORE_TABLE );
        $row          = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$scores_table} WHERE user_id = %d", $user_id ), ARRAY_A );

        if ( ! $row ) {
            return $this->aggregate_user( $user_id );
        }

        $computed_at = strtotime( $row['computed_at'] );
        if ( $computed_at && ( time() - $computed_at ) > HOUR_IN_SECONDS ) {
            return $this->aggregate_user( $user_id );
        }

        $scores = array(
            'Attention'   => (float) $row['attention_points'],
            'Antagonistic'=> (float) $row['antagonistic_points'],
            'Anxiety'     => (float) $row['anxiety_points'],
            'Acceptance'  => (float) $row['acceptance_points'],
        );

        $norm = $row['norm_scores'] ? json_decode( $row['norm_scores'], true ) : $this->normalize_scores( $scores );
        $top  = $row['top_events'] ? json_decode( $row['top_events'], true ) : $this->get_top_events( $user_id );

        return array(
            'user_id'       => $user_id,
            'scores'        => $scores,
            'norm_scores'   => $norm,
            'assigned_phase'=> $row['assigned_phase'] ?: null,
            'top_events'    => $top,
            'computed_at'   => $row['computed_at'],
        );
    }

    public function import_event_catalog( string $path ): int {
        if ( ! file_exists( $path ) ) {
            return 0;
        }

        $handle = fopen( $path, 'r' );
        if ( ! $handle ) {
            return 0;
        }

        $header = fgetcsv( $handle );
        if ( empty( $header ) ) {
            fclose( $handle );
            return 0;
        }

        $imported = 0;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $data = array_combine( $header, $row );
            if ( empty( $data['event_id'] ) ) {
                continue;
            }

            $biases = $this->normalize_biases( $data['biases'] ?? '' );

            $this->db->replace(
                $this->table( self::CATALOG_TABLE ),
                array(
                    'event_id'           => sanitize_text_field( $data['event_id'] ),
                    'label'              => sanitize_text_field( $data['label'] ?? '' ),
                    'points'             => (int) ( $data['points'] ?? 0 ),
                    'phase_tag'          => sanitize_text_field( $data['phase_tag'] ?? '' ),
                    'biases'             => $biases,
                    'default_decay_days' => (int) ( $data['default_decay_days'] ?? 30 ),
                ),
                array( '%s', '%s', '%d', '%s', '%s', '%d' )
            );

            $imported++;
        }

        fclose( $handle );
        return $imported;
    }

    public function get_catalog_count(): int {
        $catalog_table = $this->table( self::CATALOG_TABLE );
        $count = $this->db->get_var( "SELECT COUNT(*) FROM {$catalog_table}" );

        return (int) $count;
    }

    private function calculate_scores( int $user_id ): array {
        $events_table  = $this->table( self::EVENT_TABLE );
        $catalog_table = $this->table( self::CATALOG_TABLE );

        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT ue.phase_tag,
                    SUM(ue.event_points * POW(2, -(TIMESTAMPDIFF(SECOND, ue.created_at, UTC_TIMESTAMP()) / (ec.default_decay_days * 86400)))) AS decayed_points
                 FROM {$events_table} ue
                 INNER JOIN {$catalog_table} ec ON ue.event_type = ec.event_id
                 WHERE ue.user_id = %d
                 GROUP BY ue.phase_tag",
                $user_id
            ),
            ARRAY_A
        );

        $scores = array(
            'Attention'   => 0.0,
            'Antagonistic'=> 0.0,
            'Anxiety'     => 0.0,
            'Acceptance'  => 0.0,
        );

        foreach ( $rows as $row ) {
            $tag = $row['phase_tag'];
            if ( isset( $scores[ $tag ] ) ) {
                $scores[ $tag ] = (float) $row['decayed_points'];
            }
        }

        return $scores;
    }

    private function normalize_scores( array $scores ): array {
        $normalized = array();
        foreach ( $scores as $phase => $value ) {
            $total = $this->phase_totals[ $phase ] ?? 1;
            $normalized[ $phase ] = $total ? round( $value / $total, 4 ) : 0.0;
        }

        return $normalized;
    }

    private function assign_phase( int $user_id, array $norm_scores ): ?string {
        if ( $this->has_recent_point_of_sale_event( $user_id ) ) {
            return 'Acceptance';
        }

        $max_phase = null;
        $max_score = 0.0;

        foreach ( $norm_scores as $phase => $score ) {
            if ( $score > $max_score ) {
                $max_score = $score;
                $max_phase = $phase;
            }
        }

        if ( $max_score >= 0.45 ) {
            return $max_phase;
        }

        return null;
    }

    private function get_top_events( int $user_id, int $limit = 5 ): array {
        $events_table  = $this->table( self::EVENT_TABLE );
        $catalog_table = $this->table( self::CATALOG_TABLE );

        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT ue.event_type, ue.event_points, ue.created_at, ue.phase_tag, ue.bias_tags,
                        (ue.event_points * POW(2, -(TIMESTAMPDIFF(SECOND, ue.created_at, UTC_TIMESTAMP()) / (ec.default_decay_days * 86400)))) AS decayed_points
                 FROM {$events_table} ue
                 INNER JOIN {$catalog_table} ec ON ue.event_type = ec.event_id
                 WHERE ue.user_id = %d
                 ORDER BY decayed_points DESC
                 LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        $events = array();
        foreach ( $rows as $row ) {
            $events[] = array(
                'event_id'       => $row['event_type'],
                'points'         => (int) $row['event_points'],
                'decayed_points' => round( (float) $row['decayed_points'], 3 ),
                'phase_tag'      => $row['phase_tag'],
                'biases'         => $row['bias_tags'] ? json_decode( $row['bias_tags'], true ) : array(),
                'ts'             => $row['created_at'],
            );
        }

        return $events;
    }

    private function upsert_score( int $user_id, array $scores, array $norm_scores, ?string $phase, array $top_events ): void {
        $scores_table = $this->table( self::SCORE_TABLE );
        $data = array(
            'user_id'             => $user_id,
            'attention_points'    => $scores['Attention'],
            'antagonistic_points' => $scores['Antagonistic'],
            'anxiety_points'      => $scores['Anxiety'],
            'acceptance_points'   => $scores['Acceptance'],
            'assigned_phase'      => $phase,
            'norm_scores'         => wp_json_encode( $norm_scores ),
            'top_events'          => wp_json_encode( $top_events ),
            'computed_at'         => current_time( 'mysql', true ),
        );

        $existing = $this->db->get_var( $this->db->prepare( "SELECT user_id FROM {$scores_table} WHERE user_id = %d", $user_id ) );
        if ( $existing ) {
            $this->db->update( $scores_table, $data, array( 'user_id' => $user_id ) );
        } else {
            $this->db->insert( $scores_table, $data );
        }
    }

    private function has_recent_point_of_sale_event( int $user_id ): bool {
        $events_table = $this->table( self::EVENT_TABLE );

        $sql = $this->db->prepare(
            "SELECT 1 FROM {$events_table}
             WHERE user_id = %d
             AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
             AND (bias_tags LIKE %s OR event_type LIKE %s)
             LIMIT 1",
            $user_id,
            '%point_of_sale%',
            'pos_%'
        );

        return (bool) $this->db->get_var( $sql );
    }

    private function get_catalog_event( string $event_id ): ?array {
        $catalog_table = $this->table( self::CATALOG_TABLE );
        $row           = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$catalog_table} WHERE event_id = %s", $event_id ), ARRAY_A );
        if ( ! $row ) {
            return null;
        }

        $row['biases'] = $row['biases'] ? $row['biases'] : '';
        return $row;
    }

    private function normalize_biases( string $biases ): string {
        $biases = trim( $biases );
        if ( $biases === '' ) {
            return wp_json_encode( array() );
        }

        $delimiter = '|';
        if ( strpos( $biases, '|' ) === false && strpos( $biases, ';' ) !== false ) {
            $delimiter = ';';
        } elseif ( strpos( $biases, '|' ) === false && strpos( $biases, ',' ) !== false ) {
            $delimiter = ',';
        }

        $parts = array_map( 'trim', explode( $delimiter, $biases ) );
        $parts = array_values( array_filter( $parts ) );

        return wp_json_encode( $parts );
    }

    private function is_catalog_empty(): bool {
        $catalog_table = $this->table( self::CATALOG_TABLE );
        $count = $this->db->get_var( "SELECT COUNT(*) FROM {$catalog_table}" );
        return ! $count;
    }

    private function table( string $suffix ): string {
        return $this->db->prefix . $suffix;
    }
}
