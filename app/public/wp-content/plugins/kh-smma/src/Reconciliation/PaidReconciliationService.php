<?php
/**
 * Paid Reconciliation Service
 *
 * Ingests adapter execute() responses, writes canonical reconciliation rows to
 * wp_kh_paid_reconciliations, fires action hooks and audit events, and alerts
 * on large discrepancies.
 *
 * @package KH_SMMA\Reconciliation
 * @see     docs/paid/reconciliation_runbook.md
 */

namespace KH_SMMA\Reconciliation;

use KH_SMMA\Services\AuditLogger;
use wpdb;

use function current_time;
use function do_action;
use function maybe_serialize;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PaidReconciliationService {

    /** @var wpdb */
    private $db;

    /** @var AuditLogger */
    private $audit_logger;

    /** @var float */
    private $discrepancy_threshold;

    /** @var string */
    private $table;

    /**
     * @param wpdb        $db                    WordPress database instance.
     * @param AuditLogger $audit_logger          Audit logger.
     * @param float       $discrepancy_threshold Alert threshold in percent (default 10.0).
     */
    public function __construct( wpdb $db, AuditLogger $audit_logger, float $discrepancy_threshold = 10.0 ) {
        $this->db                    = $db;
        $this->audit_logger          = $audit_logger;
        $this->discrepancy_threshold = $discrepancy_threshold;
        $this->table                 = $this->db->prefix . 'kh_paid_reconciliations';
    }

    /**
     * Create the reconciliations table if it doesn't exist.
     */
    public function install(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->db->get_charset_collate();
        $table           = $this->table;

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reconciliation_id varchar(32) NOT NULL,
            manifest_id varchar(100) NOT NULL,
            execute_idempotency_key varchar(255) NOT NULL DEFAULT '',
            sponsor_id varchar(100) NOT NULL DEFAULT '',
            campaign_id varchar(100) NOT NULL DEFAULT '',
            adapter varchar(50) NOT NULL DEFAULT '',
            estimated_spend DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            actual_spend DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            currency CHAR(3) NOT NULL DEFAULT 'AUD',
            discrepancy_percent DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
            status varchar(20) NOT NULL DEFAULT 'reconciled',
            partial_failure TINYINT(1) NOT NULL DEFAULT 0,
            operation_ids LONGTEXT,
            notes TEXT,
            settlement_id VARCHAR(32) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reconciliation_id (reconciliation_id),
            UNIQUE KEY manifest_idem (manifest_id, execute_idempotency_key),
            KEY sponsor_id (sponsor_id),
            KEY campaign_id (campaign_id),
            KEY created_at (created_at),
            KEY status (status),
            KEY settlement_id (settlement_id)
        ) {$charset_collate};";

        \dbDelta( $sql );
    }

    /**
     * Reconcile an adapter execute() response.
     *
     * Idempotent: identical manifest_id + idempotency_key always returns the same row.
     *
     * @param string     $manifest_id       Manifest identifier.
     * @param array      $execute_response  Response from adapter->execute().
     * @param array|null $dry_run_response  Response from adapter->dry_run() (optional).
     * @param array      $context           Optional overrides: sponsor_id, campaign_id, idempotency_key.
     *
     * @return array Reconciliation row array.
     */
    public function reconcile(
        string $manifest_id,
        array $execute_response,
        ?array $dry_run_response = null,
        array $context = []
    ): array {
        // 1. Extract idempotency key.
        $idem_key = $execute_response['adapter_meta']['idempotency_key']
            ?? $context['idempotency_key']
            ?? '';

        // 2. Compute deterministic reconciliation_id.
        $rec_id = 'rec_' . substr( hash( 'sha256', $manifest_id . '|' . $idem_key ), 0, 16 );

        // 3. Idempotency check — return cached row if already reconciled.
        $existing = $this->db->get_row(
            $this->db->prepare( "SELECT * FROM {$this->table} WHERE reconciliation_id = %s", $rec_id ),
            ARRAY_A
        );
        if ( null !== $existing ) {
            return $existing;
        }

        // 4. Compute spend figures.
        $estimated  = isset( $dry_run_response['total_estimated_spend'] )
            ? (float) $dry_run_response['total_estimated_spend']
            : 0.0;
        $actual     = (float) ( $execute_response['total_actual_spend'] ?? 0.0 );
        $currency   = $execute_response['currency'] ?? 'AUD';
        $exec_status = $execute_response['status'] ?? 'success';
        $adapter    = $execute_response['adapter_meta']['adapter'] ?? '';
        $sponsor_id  = $context['sponsor_id'] ?? '';
        $campaign_id = $context['campaign_id'] ?? '';

        // Collect operation IDs for reference.
        $op_ids = array_column( $execute_response['operation_results'] ?? [], 'operation_id' );

        // 5. Compute discrepancy percentage.
        $discrepancy_percent = $estimated > 0.0
            ? ( ( $actual - $estimated ) / $estimated ) * 100.0
            : 0.0;

        // 6. Determine reconciliation status.
        $partial_failure = 'partial_success' === $exec_status;
        if ( 'failed' === $exec_status ) {
            $status = 'error';
        } elseif ( $partial_failure ) {
            $status = 'partial';
        } elseif ( abs( $discrepancy_percent ) > $this->discrepancy_threshold ) {
            $status = 'discrepancy';
        } else {
            $status = 'reconciled';
        }

        $now = current_time( 'mysql' );

        $data = [
            'reconciliation_id'       => $rec_id,
            'manifest_id'             => $manifest_id,
            'execute_idempotency_key' => $idem_key,
            'sponsor_id'              => $sponsor_id,
            'campaign_id'             => $campaign_id,
            'adapter'                 => $adapter,
            'estimated_spend'         => $estimated,
            'actual_spend'            => $actual,
            'currency'                => $currency,
            'discrepancy_percent'     => round( $discrepancy_percent, 4 ),
            'status'                  => $status,
            'partial_failure'         => (int) $partial_failure,
            'operation_ids'           => maybe_serialize( $op_ids ),
            'notes'                   => null,
            'created_at'              => $now,
            'updated_at'              => $now,
        ];

        // 7. Insert — UNIQUE KEY catches any DB-level race condition harmlessly.
        $this->db->insert( $this->table, $data );

        $row = $data;

        // 8. Audit: reconciled event.
        $this->audit_logger->log( 'paid_adapter.reconciled', [
            'object_type' => 'reconciliation',
            'details'     => [
                'reconciliation_id'   => $rec_id,
                'manifest_id'         => $manifest_id,
                'adapter'             => $adapter,
                'status'              => $status,
                'estimated_spend'     => $estimated,
                'actual_spend'        => $actual,
                'discrepancy_percent' => round( $discrepancy_percent, 4 ),
                'currency'            => $currency,
                'sponsor_id'          => $sponsor_id,
            ],
        ] );

        // 9. Alert if discrepancy exceeds threshold.
        if ( abs( $discrepancy_percent ) > $this->discrepancy_threshold ) {
            $this->audit_logger->log( 'paid_reconciliation.discrepancy_alert', [
                'object_type' => 'reconciliation',
                'details'     => [
                    'reconciliation_id'   => $rec_id,
                    'manifest_id'         => $manifest_id,
                    'discrepancy_percent' => round( $discrepancy_percent, 4 ),
                    'estimated_spend'     => $estimated,
                    'actual_spend'        => $actual,
                    'threshold'           => $this->discrepancy_threshold,
                ],
            ] );
            do_action( 'kh_paid_reconciliation_discrepancy', $row );
        }

        // 10. Fire complete action — kh-ad-manager hooks here for spend updates.
        do_action( 'kh_paid_reconciliation_complete', $row );

        // 11. Return row.
        return $row;
    }

    /**
     * Retrieve a single reconciliation row by reconciliation_id.
     *
     * @param string $reconciliation_id
     * @return array|null
     */
    public function get_row( string $reconciliation_id ): ?array {
        $result = $this->db->get_row(
            $this->db->prepare( "SELECT * FROM {$this->table} WHERE reconciliation_id = %s", $reconciliation_id ),
            ARRAY_A
        );
        return $result ?: null;
    }

    /**
     * List reconciliation rows with optional filters.
     *
     * @param array $filters  sponsor_id, status, date_start, date_end, per_page (default 25), paged (default 1).
     * @return array  { items: array[], total: int }
     */
    public function list_rows( array $filters = [] ): array {
        $per_page = isset( $filters['per_page'] ) ? max( 1, (int) $filters['per_page'] ) : 25;
        $paged    = isset( $filters['paged'] ) ? max( 1, (int) $filters['paged'] ) : 1;
        $offset   = ( $paged - 1 ) * $per_page;

        $where  = [ '1=1' ];
        $params = [];

        if ( ! empty( $filters['sponsor_id'] ) ) {
            $where[]  = 'sponsor_id = %s';
            $params[] = $filters['sponsor_id'];
        }

        if ( ! empty( $filters['status'] ) ) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }

        if ( ! empty( $filters['date_start'] ) ) {
            $where[]  = 'created_at >= %s';
            $params[] = $filters['date_start'] . ' 00:00:00';
        }

        if ( ! empty( $filters['date_end'] ) ) {
            $where[]  = 'created_at <= %s';
            $params[] = $filters['date_end'] . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );

        $query_sql = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";

        $items = $this->db->get_results(
            $this->db->prepare( $query_sql, array_merge( $params, [ $per_page, $offset ] ) ),
            ARRAY_A
        );

        $total = (int) ( ! empty( $params )
            ? $this->db->get_var( $this->db->prepare( $count_sql, $params ) )
            : $this->db->get_var( $count_sql )
        );

        return [
            'items' => $items ?: [],
            'total' => $total,
        ];
    }
}
