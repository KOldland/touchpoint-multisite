<?php
/**
 * Paid Reconciliation Adjustment Service
 *
 * Manages manual adjustments and reversals against reconciliation rows.
 * Adjustments affect the settled_amount used by SettlementWorker without
 * altering the original actual_spend recorded by the adapter.
 *
 * All adjustments are immutable once created — corrections are made via
 * create_reversal(), which creates an equal and opposite adjustment.
 *
 * @package KH_SMMA\Reconciliation
 * @see     docs/paid/finance_reconciliation_runbook.md
 */

namespace KH_SMMA\Reconciliation;

use KH_SMMA\Services\AuditLogger;
use wpdb;

use function current_time;
use function do_action;
use function maybe_serialize;
use function maybe_unserialize;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PaidReconciliationAdjustmentService {

    /** @var wpdb */
    private $db;

    /** @var AuditLogger */
    private $audit_logger;

    /** @var string */
    private $table;

    public function __construct( wpdb $db, AuditLogger $audit_logger ) {
        $this->db           = $db;
        $this->audit_logger = $audit_logger;
        $this->table        = $this->db->prefix . 'kh_paid_reconciliation_adjustments';
    }

    /**
     * Create the adjustments table if it doesn't exist.
     */
    public function install(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->db->get_charset_collate();
        $table           = $this->table;

        $sql = "CREATE TABLE {$table} (
            adjustment_id VARCHAR(32) NOT NULL,
            reconciliation_id VARCHAR(32) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'AUD',
            reason TEXT,
            adjusted_by BIGINT(20) UNSIGNED NOT NULL,
            reversal_of VARCHAR(32) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (adjustment_id),
            KEY reconciliation_id (reconciliation_id),
            KEY adjusted_by (adjusted_by)
        ) {$charset_collate};";

        \dbDelta( $sql );
    }

    /**
     * Create a manual adjustment against a reconciliation row.
     *
     * @param string $reconciliation_id Target reconciliation.
     * @param float  $amount            Signed amount (negative = credit to sponsor).
     * @param string $currency          ISO 4217 currency code.
     * @param string $reason            Human-readable reason.
     * @param int    $adjusted_by       WP user ID of the person making the adjustment.
     *
     * @return array The created adjustment row.
     */
    public function create_adjustment(
        string $reconciliation_id,
        float $amount,
        string $currency,
        string $reason,
        int $adjusted_by
    ): array {
        $now           = current_time( 'mysql' );
        $adjustment_id = 'adj_' . substr(
            hash( 'sha256', $reconciliation_id . '|' . $amount . '|' . $now ),
            0, 12
        );

        $data = [
            'adjustment_id'     => $adjustment_id,
            'reconciliation_id' => $reconciliation_id,
            'amount'            => $amount,
            'currency'          => strtoupper( $currency ),
            'reason'            => $reason,
            'adjusted_by'       => $adjusted_by,
            'reversal_of'       => null,
            'created_at'        => $now,
        ];

        $this->db->insert( $this->table, $data );

        $this->audit_logger->log( 'paid_adjustment.created', [
            'object_type' => 'adjustment',
            'user_id'     => $adjusted_by,
            'details'     => [
                'adjustment_id'     => $adjustment_id,
                'reconciliation_id' => $reconciliation_id,
                'amount'            => $amount,
                'currency'          => $currency,
                'reason'            => $reason,
            ],
        ] );

        do_action( 'kh_paid_adjustment_created', $data );

        return $data;
    }

    /**
     * Reverse an existing adjustment.
     *
     * Creates an equal and opposite adjustment row. Raises a RuntimeException
     * if the original adjustment has already been reversed.
     *
     * @param string $adjustment_id ID of the adjustment to reverse.
     * @param int    $adjusted_by   WP user ID of the person requesting the reversal.
     *
     * @return array The reversal row created.
     * @throws \RuntimeException If the adjustment is already reversed.
     */
    public function create_reversal( string $adjustment_id, int $adjusted_by ): array {
        // Fetch original adjustment.
        $original = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE adjustment_id = %s",
                $adjustment_id
            ),
            ARRAY_A
        );

        if ( null === $original ) {
            throw new \RuntimeException( "Adjustment not found: {$adjustment_id}" );
        }

        // Check if already reversed.
        $existing_reversal = $this->db->get_row(
            $this->db->prepare(
                "SELECT adjustment_id FROM {$this->table} WHERE reversal_of = %s",
                $adjustment_id
            ),
            ARRAY_A
        );

        if ( null !== $existing_reversal ) {
            throw new \RuntimeException(
                "Adjustment {$adjustment_id} has already been reversed by {$existing_reversal['adjustment_id']}."
            );
        }

        $now           = current_time( 'mysql' );
        $reversal_amount = -1 * (float) $original['amount'];
        $reversal_id   = 'adj_' . substr(
            hash( 'sha256', $adjustment_id . '|reversal|' . $now ),
            0, 12
        );

        $data = [
            'adjustment_id'     => $reversal_id,
            'reconciliation_id' => $original['reconciliation_id'],
            'amount'            => $reversal_amount,
            'currency'          => $original['currency'],
            'reason'            => 'Reversal of ' . $adjustment_id,
            'adjusted_by'       => $adjusted_by,
            'reversal_of'       => $adjustment_id,
            'created_at'        => $now,
        ];

        $this->db->insert( $this->table, $data );

        $this->audit_logger->log( 'paid_adjustment.reversed', [
            'object_type' => 'adjustment',
            'user_id'     => $adjusted_by,
            'details'     => [
                'reversal_id'       => $reversal_id,
                'original_id'       => $adjustment_id,
                'reconciliation_id' => $original['reconciliation_id'],
                'reversal_amount'   => $reversal_amount,
                'currency'          => $original['currency'],
            ],
        ] );

        return $data;
    }

    /**
     * Retrieve all adjustments for a given reconciliation row.
     *
     * @param string $reconciliation_id
     * @return array Array of adjustment rows (ARRAY_A).
     */
    public function get_adjustments( string $reconciliation_id ): array {
        $results = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE reconciliation_id = %s ORDER BY created_at ASC",
                $reconciliation_id
            ),
            ARRAY_A
        );
        return $results ?: [];
    }

    /**
     * Compute the settled amount for a reconciliation row.
     *
     * settled_amount = actual_spend + SUM(adjustments.amount)
     *
     * A positive adjustment increases the amount settled; a negative adjustment
     * (credit) reduces it. Rounded to 4 decimal places.
     *
     * @param string $reconciliation_id
     * @param float  $actual_spend Base actual spend from the execute response.
     * @return float
     */
    public function compute_settled_amount( string $reconciliation_id, float $actual_spend ): float {
        $adjustments = $this->get_adjustments( $reconciliation_id );
        $total_adj   = array_sum( array_column( $adjustments, 'amount' ) );
        return round( $actual_spend + (float) $total_adj, 4 );
    }
}
