<?php
/**
 * Settlement Delivery Service
 *
 * Orchestrates the delivery of settled ledgers to accounting systems.
 * Wraps adapter dry_run/execute calls with idempotency, retry/backoff,
 * DLQ escalation, and full audit trails.
 *
 * Flow:
 *  1. deliver()      — idempotency check → dry_run → execute → record row → audit → hook
 *  2. retry()        — re-execute failed delivery, increment attempts, DLQ on exhaustion
 *  3. record_ack()   — mark a delivery as externally acknowledged
 *  4. run_scheduled()— cron callback: deliver all undelivered settlements
 *
 * @package KH_SMMA\Reconciliation
 * @see     docs/paid/accounting_integration_runbook.md
 * @see     docs/contracts/paid_delivery.json
 */

namespace KH_SMMA\Reconciliation;

use KH_SMMA\Services\AuditLogger;
use wpdb;

use function current_time;
use function do_action;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettlementDeliveryService {

    /** Maximum delivery attempts before a row moves to failed_permanent (DLQ). */
    const DEFAULT_MAX_RETRIES = 3;

    /** @var wpdb */
    private $db;

    /** @var SettlementWorker */
    private $settlement_worker;

    /** @var AuditLogger */
    private $logger;

    /** @var DeliveryIdempotencyStore */
    private $store;

    /** @var string */
    private $deliveries_table;

    /** @var string */
    private $settlements_table;

    public function __construct(
        wpdb $db,
        SettlementWorker $settlement_worker,
        AuditLogger $logger,
        DeliveryIdempotencyStore $store
    ) {
        $this->db                = $db;
        $this->settlement_worker = $settlement_worker;
        $this->logger            = $logger;
        $this->store             = $store;
        $this->deliveries_table  = $db->prefix . 'kh_paid_settlement_deliveries';
        $this->settlements_table = $db->prefix . 'kh_paid_settlements';
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    /**
     * Create the deliveries table if it does not exist.
     * Safe to call on plugin activation (dbDelta is idempotent).
     */
    public function install(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->db->get_charset_collate();
        $table           = $this->deliveries_table;

        $sql = "CREATE TABLE {$table} (
            delivery_id              VARCHAR(32) NOT NULL,
            settlement_id            VARCHAR(32) NOT NULL,
            adapter                  VARCHAR(50) NOT NULL,
            status                   VARCHAR(30) NOT NULL DEFAULT 'pending',
            delivery_idempotency_key VARCHAR(255) NOT NULL DEFAULT '',
            delivered_at             DATETIME NULL,
            acked_at                 DATETIME NULL,
            checksum                 VARCHAR(64) NULL,
            attempts                 TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_error               TEXT NULL,
            notes                    TEXT NULL,
            created_at               DATETIME NOT NULL,
            updated_at               DATETIME NOT NULL,
            PRIMARY KEY  (delivery_id),
            KEY settlement_id (settlement_id),
            KEY adapter (adapter),
            KEY status (status),
            KEY delivered_at (delivered_at)
        ) {$charset_collate};";

        \dbDelta( $sql );
    }

    // ── Core delivery ─────────────────────────────────────────────────────────

    /**
     * Deliver a settlement to the accounting system.
     *
     * Idempotent: if a successful delivery already exists for this
     * settlement_id + adapter pair the cached row is returned immediately
     * without re-executing.
     *
     * @param string                   $settlement_id
     * @param AccountingAdapterContract $adapter
     * @param array                    $opts  Passed through to adapter->execute().
     * @return array  The wp_kh_paid_settlement_deliveries row that was created.
     * @throws \RuntimeException  If the settlement does not exist or dry_run() is invalid.
     */
    public function deliver(
        string $settlement_id,
        AccountingAdapterContract $adapter,
        array $opts = []
    ): array {
        $adapter_name = $adapter->adapter_name();

        // ── Idempotency: skip if already delivered ───────────────────────────
        $cached = $this->store->get( $settlement_id, $adapter_name );
        if ( null !== $cached ) {
            return $cached;
        }

        // ── Fetch settlement ─────────────────────────────────────────────────
        $settlement = $this->settlement_worker->get_settlement( $settlement_id );
        if ( null === $settlement ) {
            throw new \RuntimeException( "Settlement not found: {$settlement_id}" );
        }

        // ── dry_run validation ───────────────────────────────────────────────
        $dry = $adapter->dry_run( $settlement, $opts );
        if ( empty( $dry['valid'] ) ) {
            $err_msg = $dry['error']['message'] ?? 'dry_run() validation failed.';
            throw new \RuntimeException( $err_msg );
        }

        $now         = current_time( 'mysql' );
        $delivery_id = 'del_' . substr(
            hash( 'sha256', $settlement_id . '|' . $adapter_name . '|' . $now ),
            0, 12
        );
        $idem_key    = $settlement_id . '|' . $adapter_name;

        // ── Execute delivery ─────────────────────────────────────────────────
        $execute_result = null;
        $status         = 'failed';
        $last_error     = null;
        $checksum       = null;
        $delivered_at   = null;

        try {
            $execute_result = $adapter->execute( $settlement, $opts );
            $status         = $execute_result['status'] ?? 'failed';
            $checksum       = $execute_result['checksum'] ?? null;
            $delivered_at   = $execute_result['delivered_at'] ?? null;

            if ( isset( $execute_result['error'] ) && is_array( $execute_result['error'] ) ) {
                $last_error = $execute_result['error']['message'] ?? null;
            }
        } catch ( \Throwable $e ) {
            $status     = 'failed';
            $last_error = $e->getMessage();
        }

        // Sync delivery_id from adapter result if available.
        if ( isset( $execute_result['delivery_id'] ) ) {
            $delivery_id = $execute_result['delivery_id'];
        }

        // ── Persist delivery row ─────────────────────────────────────────────
        $delivery_row = [
            'delivery_id'              => $delivery_id,
            'settlement_id'            => $settlement_id,
            'adapter'                  => $adapter_name,
            'status'                   => $status,
            'delivery_idempotency_key' => $idem_key,
            'delivered_at'             => $delivered_at,
            'acked_at'                 => null,
            'checksum'                 => $checksum,
            'attempts'                 => 1,
            'last_error'               => $last_error,
            'notes'                    => null,
            'created_at'               => $now,
            'updated_at'               => $now,
        ];

        $this->db->insert( $this->deliveries_table, $delivery_row );

        // ── Cache successful delivery ────────────────────────────────────────
        if ( 'delivered' === $status ) {
            $this->store->store( $settlement_id, $adapter_name, $delivery_row );
        }

        // ── Audit ─────────────────────────────────────────────────────────────
        $this->logger->log( 'delivered' === $status ? 'paid_delivery.delivered' : 'paid_delivery.failed', [
            'object_type' => 'settlement',
            'details'     => [
                'delivery_id'   => $delivery_id,
                'settlement_id' => $settlement_id,
                'adapter'       => $adapter_name,
                'status'        => $status,
                'checksum'      => $checksum,
                'last_error'    => $last_error,
            ],
        ] );

        // ── Action hook ───────────────────────────────────────────────────────
        if ( 'delivered' === $status ) {
            do_action( 'kh_paid_delivery_complete', $delivery_row );
        }

        return $delivery_row;
    }

    // ── Retry / DLQ ───────────────────────────────────────────────────────────

    /**
     * Retry a failed delivery.
     *
     * Increments the attempts counter. If attempts reach max_retries the
     * delivery is moved to failed_permanent (DLQ) and no further retries
     * are possible without manual intervention.
     *
     * @param string                   $delivery_id
     * @param AccountingAdapterContract $adapter
     * @param array                    $opts
     * @param int                      $max_retries
     * @return array  Updated delivery row.
     * @throws \RuntimeException  If delivery_id not found or already permanent.
     */
    public function retry(
        string $delivery_id,
        AccountingAdapterContract $adapter,
        array $opts = [],
        int $max_retries = self::DEFAULT_MAX_RETRIES
    ): array {
        $existing = $this->get_delivery( $delivery_id );
        if ( null === $existing ) {
            throw new \RuntimeException( "Delivery not found: {$delivery_id}" );
        }

        if ( 'failed_permanent' === $existing['status'] ) {
            throw new \RuntimeException( "Delivery {$delivery_id} is in DLQ — cannot retry." );
        }

        $settlement_id = $existing['settlement_id'];
        $attempts      = (int) ( $existing['attempts'] ?? 0 ) + 1;
        $now           = current_time( 'mysql' );

        if ( $attempts >= $max_retries ) {
            // Move to DLQ.
            $this->db->update(
                $this->deliveries_table,
                [ 'status' => 'failed_permanent', 'attempts' => $attempts, 'updated_at' => $now ],
                [ 'delivery_id' => $delivery_id ]
            );

            $dlq_row = array_merge( $existing, [
                'status'     => 'failed_permanent',
                'attempts'   => $attempts,
                'updated_at' => $now,
            ] );

            $this->logger->log( 'paid_delivery.dlq', [
                'object_type' => 'settlement',
                'details'     => [
                    'delivery_id'   => $delivery_id,
                    'settlement_id' => $settlement_id,
                    'adapter'       => $existing['adapter'],
                    'attempts'      => $attempts,
                ],
            ] );

            do_action( 'kh_paid_delivery_dlq', $dlq_row );
            return $dlq_row;
        }

        // Re-execute.
        $settlement = $this->settlement_worker->get_settlement( $settlement_id );
        if ( null === $settlement ) {
            throw new \RuntimeException( "Settlement not found: {$settlement_id}" );
        }

        $status       = 'failed';
        $checksum     = null;
        $delivered_at = null;
        $last_error   = null;

        try {
            $result       = $adapter->execute( $settlement, $opts );
            $status       = $result['status'] ?? 'failed';
            $checksum     = $result['checksum'] ?? null;
            $delivered_at = $result['delivered_at'] ?? null;

            if ( isset( $result['error'] ) && is_array( $result['error'] ) ) {
                $last_error = $result['error']['message'] ?? null;
            }
        } catch ( \Throwable $e ) {
            $status     = 'failed';
            $last_error = $e->getMessage();
        }

        $this->db->update(
            $this->deliveries_table,
            [
                'status'       => $status,
                'checksum'     => $checksum,
                'delivered_at' => $delivered_at,
                'attempts'     => $attempts,
                'last_error'   => $last_error,
                'updated_at'   => $now,
            ],
            [ 'delivery_id' => $delivery_id ]
        );

        $updated_row = array_merge( $existing, [
            'status'       => $status,
            'checksum'     => $checksum,
            'delivered_at' => $delivered_at,
            'attempts'     => $attempts,
            'last_error'   => $last_error,
            'updated_at'   => $now,
        ] );

        if ( 'delivered' === $status ) {
            $this->store->store( $settlement_id, $existing['adapter'], $updated_row );
            do_action( 'kh_paid_delivery_complete', $updated_row );
        }

        $this->logger->log( 'delivered' === $status ? 'paid_delivery.delivered' : 'paid_delivery.retry_failed', [
            'object_type' => 'settlement',
            'details'     => [
                'delivery_id'   => $delivery_id,
                'settlement_id' => $settlement_id,
                'adapter'       => $existing['adapter'],
                'attempts'      => $attempts,
                'status'        => $status,
                'last_error'    => $last_error,
            ],
        ] );

        return $updated_row;
    }

    // ── ACK ───────────────────────────────────────────────────────────────────

    /**
     * Record an external acknowledgement for a delivery.
     *
     * @param string $delivery_id
     * @return array  Updated delivery row with status='acked'.
     * @throws \RuntimeException  If delivery not found.
     */
    public function record_ack( string $delivery_id ): array {
        $existing = $this->get_delivery( $delivery_id );
        if ( null === $existing ) {
            throw new \RuntimeException( "Delivery not found: {$delivery_id}" );
        }

        $now = current_time( 'mysql' );

        $this->db->update(
            $this->deliveries_table,
            [ 'status' => 'acked', 'acked_at' => $now, 'updated_at' => $now ],
            [ 'delivery_id' => $delivery_id ]
        );

        $acked_row = array_merge( $existing, [
            'status'     => 'acked',
            'acked_at'   => $now,
            'updated_at' => $now,
        ] );

        $this->logger->log( 'paid_delivery.acked', [
            'object_type' => 'settlement',
            'details'     => [
                'delivery_id'   => $delivery_id,
                'settlement_id' => $existing['settlement_id'],
                'adapter'       => $existing['adapter'],
                'acked_at'      => $now,
            ],
        ] );

        do_action( 'kh_paid_delivery_acked', $acked_row );

        return $acked_row;
    }

    // ── Query ─────────────────────────────────────────────────────────────────

    /**
     * Fetch a single delivery row by delivery_id.
     *
     * @param string $delivery_id
     * @return array|null
     */
    public function get_delivery( string $delivery_id ): ?array {
        $result = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->deliveries_table} WHERE delivery_id = %s",
                $delivery_id
            ),
            ARRAY_A
        );
        return $result ?: null;
    }

    /**
     * List delivery rows with optional filters.
     *
     * @param array $filters {
     *     @type string $settlement_id  Filter to a single settlement.
     *     @type string $adapter        Filter by adapter slug.
     *     @type string $status         Filter by status.
     *     @type string $date_start     YYYY-MM-DD lower bound on created_at.
     *     @type string $date_end       YYYY-MM-DD upper bound on created_at.
     *     @type int    $per_page       Default 25.
     *     @type int    $paged          Default 1.
     * }
     * @return array{items: array, total: int}
     */
    public function list_deliveries( array $filters = [] ): array {
        $where  = [];
        $params = [];

        if ( ! empty( $filters['settlement_id'] ) ) {
            $where[]  = 'settlement_id = %s';
            $params[] = $filters['settlement_id'];
        }

        if ( ! empty( $filters['adapter'] ) ) {
            $where[]  = 'adapter = %s';
            $params[] = $filters['adapter'];
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

        $per_page = max( 1, (int) ( $filters['per_page'] ?? 25 ) );
        $paged    = max( 1, (int) ( $filters['paged']    ?? 1  ) );
        $offset   = ( $paged - 1 ) * $per_page;

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $count_sql = "SELECT COUNT(*) FROM {$this->deliveries_table} {$where_sql}";
        $items_sql = "SELECT * FROM {$this->deliveries_table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        $all_params = array_merge( $params, [ $per_page, $offset ] );

        $total = (int) ( ! empty( $params )
            ? $this->db->get_var( $this->db->prepare( $count_sql, $params ) )
            : $this->db->get_var( $count_sql ) );

        $items = ! empty( $all_params )
            ? $this->db->get_results( $this->db->prepare( $items_sql, $all_params ), ARRAY_A )
            : $this->db->get_results( $items_sql, ARRAY_A );

        return [
            'items' => $items ?: [],
            'total' => $total,
        ];
    }

    // ── Scheduled run ─────────────────────────────────────────────────────────

    /**
     * Deliver all unsettled (undelivered) settlements.
     *
     * Called by WP-cron or WP-CLI. Queries for settlements with no
     * successful delivery row, delivers each, and returns a summary.
     *
     * @param AccountingAdapterContract $adapter
     * @param array                     $filters  Optional: sponsor_id, date_start, date_end.
     * @return array  Array of delivery rows created.
     */
    public function run_scheduled(
        AccountingAdapterContract $adapter,
        array $filters = []
    ): array {
        // Fetch settlements that have no acked/delivered delivery row.
        $where  = [ 's.settlement_id IS NOT NULL' ];
        $params = [];

        if ( ! empty( $filters['sponsor_id'] ) ) {
            $where[]  = 's.sponsor_id = %s';
            $params[] = $filters['sponsor_id'];
        }

        if ( ! empty( $filters['date_start'] ) ) {
            $where[]  = 's.settled_at >= %s';
            $params[] = $filters['date_start'] . ' 00:00:00';
        }

        if ( ! empty( $filters['date_end'] ) ) {
            $where[]  = 's.settled_at <= %s';
            $params[] = $filters['date_end'] . ' 23:59:59';
        }

        $adapter_name = $adapter->adapter_name();

        // Settlements not yet successfully delivered via this adapter.
        $not_delivered_sql =
            "SELECT s.* FROM {$this->settlements_table} s
             LEFT JOIN {$this->deliveries_table} d
               ON d.settlement_id = s.settlement_id
               AND d.adapter = '{$adapter_name}'
               AND d.status IN ('delivered','acked')
             WHERE d.delivery_id IS NULL";

        $settlements = ! empty( $params )
            ? $this->db->get_results( $this->db->prepare( $not_delivered_sql ), ARRAY_A )
            : $this->db->get_results( $not_delivered_sql, ARRAY_A );

        if ( empty( $settlements ) ) {
            return [];
        }

        $results = [];
        foreach ( $settlements as $settlement ) {
            try {
                $row = $this->deliver( $settlement['settlement_id'], $adapter );
                $results[] = $row;
            } catch ( \Throwable $e ) {
                // Log and continue; don't abort the batch on one failure.
                $this->logger->log( 'paid_delivery.batch_error', [
                    'object_type' => 'settlement',
                    'details'     => [
                        'settlement_id' => $settlement['settlement_id'],
                        'error'         => $e->getMessage(),
                    ],
                ] );
            }
        }

        return $results;
    }
}
