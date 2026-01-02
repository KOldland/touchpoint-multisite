<?php
/**
 * Order Repository
 *
 * Handles CRUD operations for membership orders.
 *
 * @package KHM\Services
 */

namespace KHM\Services;

use KHM\Contracts\OrderRepositoryInterface;

class OrderRepository implements OrderRepositoryInterface {

    private string $tableName;
    private string $levelsTable;
    private string $usersTable;

    public function __construct() {
        global $wpdb;
        $this->tableName = $wpdb->prefix . 'khm_membership_orders';
        $this->levelsTable = $wpdb->prefix . 'khm_membership_levels';
        $this->usersTable  = $wpdb->users;
    }

    /**
     * Create a new order.
     */
    public function create( array $data ): object {
        global $wpdb;

        // Generate code if not provided
        if ( empty($data['code']) ) {
            $data['code'] = $this->generateCode();
        }

        // Set defaults
        $defaults = [
            'status' => 'pending',
            'gateway_environment' => 'production',
            'timestamp' => current_time('mysql', true),
        ];

        $data = array_merge($defaults, $data);

        // Sanitize discount metadata fields if present
        $allowed_discount_fields = [
            'discount_code',
            'discount_amount',
            'trial_days',
            'trial_amount',
            'first_payment_only',
            'recurring_discount_type',
            'recurring_discount_amount',
        ];

        foreach ( $allowed_discount_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                // Keep only allowed fields; sanitize as needed
                if ( $field === 'discount_code' ) {
                    $data[ $field ] = sanitize_text_field( $data[ $field ] );
                } elseif ( $field === 'first_payment_only' ) {
                    $data[ $field ] = (int) (bool) $data[ $field ];
                } elseif ( in_array( $field, [ 'discount_amount', 'trial_amount', 'recurring_discount_amount' ], true ) ) {
                    $data[ $field ] = isset( $data[ $field ] ) ? (float) $data[ $field ] : null;
                } elseif ( $field === 'trial_days' ) {
                    $data[ $field ] = isset( $data[ $field ] ) ? (int) $data[ $field ] : null;
                } elseif ( $field === 'recurring_discount_type' ) {
                    $data[ $field ] = in_array( $data[ $field ], [ 'percent', 'amount' ], true ) ? $data[ $field ] : null;
                }
            }
        }

        // Normalise failure metadata.
        if ( isset( $data['failure_code'] ) ) {
            $data['failure_code'] = sanitize_text_field( $data['failure_code'] );
        }
        if ( isset( $data['failure_message'] ) ) {
            $data['failure_message'] = function_exists( 'sanitize_textarea_field' )
                ? sanitize_textarea_field( $data['failure_message'] )
                : sanitize_text_field( $data['failure_message'] );
        }
        if ( isset( $data['failure_at'] ) ) {
            $data['failure_at'] = gmdate( 'Y-m-d H:i:s', strtotime( (string) $data['failure_at'] ) );
        }

        // Normalise refund metadata.
        if ( isset( $data['refund_amount'] ) ) {
            $data['refund_amount'] = (float) $data['refund_amount'];
        }
        if ( isset( $data['refund_reason'] ) ) {
            $data['refund_reason'] = function_exists( 'sanitize_textarea_field' )
                ? sanitize_textarea_field( $data['refund_reason'] )
                : sanitize_text_field( $data['refund_reason'] );
        }
        if ( isset( $data['refunded_at'] ) ) {
            $data['refunded_at'] = gmdate( 'Y-m-d H:i:s', strtotime( (string) $data['refunded_at'] ) );
        }

        // Insert order
        $wpdb->insert($this->tableName, $data);
        $orderId = $wpdb->insert_id;

        // Fire action
        do_action('khm_order_created', $orderId, $data);

        return $this->find($orderId);
    }

    /**
     * Update an existing order.
     */
    public function update( int $orderId, array $data ): object {
        global $wpdb;

        // Reuse create() sanitisation for updates.
        if ( isset( $data['failure_code'] ) ) {
            $data['failure_code'] = sanitize_text_field( $data['failure_code'] );
        }
        if ( isset( $data['failure_message'] ) ) {
            $data['failure_message'] = function_exists( 'sanitize_textarea_field' )
                ? sanitize_textarea_field( $data['failure_message'] )
                : sanitize_text_field( $data['failure_message'] );
        }
        if ( isset( $data['failure_at'] ) ) {
            $data['failure_at'] = gmdate( 'Y-m-d H:i:s', strtotime( (string) $data['failure_at'] ) );
        }
        if ( isset( $data['refund_amount'] ) ) {
            $data['refund_amount'] = (float) $data['refund_amount'];
        }
        if ( isset( $data['refund_reason'] ) ) {
            $data['refund_reason'] = function_exists( 'sanitize_textarea_field' )
                ? sanitize_textarea_field( $data['refund_reason'] )
                : sanitize_text_field( $data['refund_reason'] );
        }
        if ( isset( $data['refunded_at'] ) ) {
            $data['refunded_at'] = gmdate( 'Y-m-d H:i:s', strtotime( (string) $data['refunded_at'] ) );
        }

        $wpdb->update(
            $this->tableName,
            $data,
            [ 'id' => $orderId ],
            null,
            [ '%d' ]
        );

        // Fire action
        do_action('khm_order_updated', $orderId, $data);

        return $this->find($orderId);
    }

    /**
     * Find an order by ID.
     */
    public function find( int $orderId ): ?object {
        global $wpdb;

        $order = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a known safe, plugin-owned table string.
                "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
                $orderId
            )
        );

        return $order ?: null;
    }

    /**
     * Find an order by public code.
     */
    public function findByCode( string $code ): ?object {
        global $wpdb;

        $order = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a known safe, plugin-owned table string.
                "SELECT * FROM {$this->tableName} WHERE code = %s LIMIT 1",
                $code
            )
        );

        return $order ?: null;
    }

    /**
     * Find an order by payment transaction ID.
     */
    public function findByPaymentTransactionId( string $txnId ): ?object {
        global $wpdb;

        $order = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a known safe, plugin-owned table string.
                "SELECT * FROM {$this->tableName} WHERE payment_transaction_id = %s LIMIT 1",
                $txnId
            )
        );

        return $order ?: null;
    }

    /**
     * Find the most recent order for a subscription.
     */
    public function findLastBySubscriptionId( string $subscriptionId ): ?object {
        global $wpdb;

        $order = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a known safe, plugin-owned table string.
                "SELECT * FROM {$this->tableName} 
                 WHERE subscription_transaction_id = %s 
                 ORDER BY id DESC LIMIT 1",
                $subscriptionId
            )
        );

        return $order ?: null;
    }

    /**
     * Find orders for a user.
     */
    public function findByUser( int $userId, array $filters = [] ): array {
        global $wpdb;

        $where = [ 'user_id = %d' ];
        $values = [ $userId ];

        if ( ! empty($filters['status']) ) {
            if ( is_array($filters['status']) ) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '%s'));
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only placeholder string is interpolated; values are safely prepared.
                $where[] = "status IN ({$placeholders})";
                $values = array_merge($values, $filters['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $filters['status'];
            }
        }

        if ( ! empty($filters['gateway']) ) {
            $where[] = 'gateway = %s';
            $values[] = $filters['gateway'];
        }

        if ( ! empty($filters['membership_id']) ) {
            $where[] = 'membership_id = %d';
            $values[] = $filters['membership_id'];
        }

		$whereClause = implode(' AND ', $where);
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a known safe, plugin-owned table string.
		$sql = "SELECT * FROM {$this->tableName} WHERE {$whereClause} ORDER BY id DESC";

        if ( ! empty($filters['limit']) ) {
            $sql .= $wpdb->prepare(' LIMIT %d', $filters['limit']);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name is a known safe, plugin-owned table string, and the SQL is prepared on the next line.
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * Retrieve an order with related user and level information.
     *
     * @param int $orderId Order ID.
     * @return array<string,mixed>|null
     */
    public function getWithRelations( int $orderId ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT o.*,
                        u.user_login,
                        u.user_email,
                        u.display_name,
                        l.name AS level_name
                 FROM {$this->tableName} o
                 LEFT JOIN {$this->usersTable} u ON o.user_id = u.ID
                 LEFT JOIN {$this->levelsTable} l ON o.membership_id = l.id
                 WHERE o.id = %d
                 LIMIT 1",
                $orderId
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Retrieve multiple orders by ID with related data.
     *
     * @param array<int> $ids Order IDs.
     * @return array<array<string,mixed>>
     */
    public function getManyWithRelations( array $ids ): array {
        global $wpdb;

        $ids = array_values(
            array_filter(
                array_map( 'intval', $ids ),
                static fn( $id ) => $id > 0
            )
        );

        if ( empty( $ids ) ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.*,
                        u.user_login,
                        u.user_email,
                        u.display_name,
                        l.name AS level_name
                 FROM {$this->tableName} o
                 LEFT JOIN {$this->usersTable} u ON o.user_id = u.ID
                 LEFT JOIN {$this->levelsTable} l ON o.membership_id = l.id
                 WHERE o.id IN ($placeholders)
                 ORDER BY o.id ASC",
                $ids
            ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    /**
     * Paginate orders for admin listings.
     *
     * @param array<string,mixed> $args Query args.
     * @return array{items: array<array<string,mixed>>, total: int}
     */
    public function paginate( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'search'   => '',
            'status'   => '',
            'gateway'  => '',
            'level_id' => null,
            'orderby'  => 'timestamp',
            'order'    => 'DESC',
            'per_page' => 20,
            'offset'   => 0,
        ];

        $args = array_merge(
            $defaults,
            array_intersect_key( $args, $defaults )
        );

        $where  = [];
        $values = [];

        if ( ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(o.code LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'o.status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['gateway'] ) ) {
            $where[]  = 'o.gateway = %s';
            $values[] = $args['gateway'];
        }

        if ( ! empty( $args['level_id'] ) ) {
            $where[]  = 'o.membership_id = %d';
            $values[] = (int) $args['level_id'];
        }

        $whereSql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $orderMap = [
            'code'      => 'o.code',
            'total'     => 'o.total',
            'status'    => 'o.status',
            'timestamp' => 'o.timestamp',
        ];

        $orderBy = isset( $orderMap[ $args['orderby'] ] ) ? $orderMap[ $args['orderby'] ] : 'o.timestamp';
        $order   = strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = max( 1, (int) $args['per_page'] );
        $offset = max( 0, (int) $args['offset'] );

        $selectSql = "SELECT o.*,
                             u.user_login,
                             u.user_email,
                             u.display_name,
                             l.name AS level_name
                      FROM {$this->tableName} o
                      LEFT JOIN {$this->usersTable} u ON o.user_id = u.ID
                      LEFT JOIN {$this->levelsTable} l ON o.membership_id = l.id
                      {$whereSql}
                      ORDER BY {$orderBy} {$order}
                      LIMIT %d OFFSET %d";

        $items = $wpdb->get_results(
            $wpdb->prepare(
                $selectSql,
                array_merge( $values, [ $limit, $offset ] )
            ),
            ARRAY_A
        );

        $countSql = "SELECT COUNT(*)
                     FROM {$this->tableName} o
                     LEFT JOIN {$this->usersTable} u ON o.user_id = u.ID
                     LEFT JOIN {$this->levelsTable} l ON o.membership_id = l.id
                     {$whereSql}";

        $total = (int) $wpdb->get_var(
            $values ? $wpdb->prepare( $countSql, $values ) : $countSql
        );

        return [
            'items' => $items ?: [],
            'total' => $total,
        ];
    }

    /**
     * Update order status.
     */
    public function updateStatus( int $orderId, string $status, string $notes = '' ): bool {
        global $wpdb;

        $data = [ 'status' => $status ];

        if ( ! empty($notes) ) {
            $data['notes'] = $notes;
        }

        $result = $wpdb->update(
            $this->tableName,
            $data,
            [ 'id' => $orderId ],
            null,
            [ '%d' ]
        );

        if ( $result !== false ) {
            do_action('khm_order_status_changed', $orderId, $status, $notes);
        }

        return $result !== false;
    }

    /**
     * Update order notes.
     *
     * @param int    $orderId Order ID.
     * @param string $notes   Notes content.
     * @return bool
     */
    public function updateNotes( int $orderId, string $notes ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $this->tableName,
            [ 'notes' => $notes ],
            [ 'id' => $orderId ],
            null,
            [ '%d' ]
        );

        return $result !== false;
    }

    /**
     * Record refund metadata.
     *
     * @param int    $orderId Order ID.
     * @param float  $amount  Refund amount.
     * @param string $reason  Refund reason.
     * @param string|null $refundedAt Timestamp for refund.
     * @return bool
     */
    public function recordRefund( int $orderId, float $amount, string $reason = '', ?string $refundedAt = null ): bool {
        global $wpdb;

        $data = [
            'refund_amount' => $amount,
            'refund_reason' => $reason,
            'status'        => 'refunded',
        ];

        if ( $refundedAt ) {
            $data['refunded_at'] = gmdate( 'Y-m-d H:i:s', strtotime( $refundedAt ) );
        } else {
            $data['refunded_at'] = current_time( 'mysql', true );
        }

        $result = $wpdb->update(
            $this->tableName,
            $data,
            [ 'id' => $orderId ],
            null,
            [ '%d' ]
        );

        if ( false !== $result ) {
            $order = $this->getWithRelations( $orderId );
            if ( ! $order ) {
                $found = $this->find( $orderId );
                $order = $found ? (array) $found : [];
            }

            do_action( 'khm_order_status_changed', $orderId, 'refunded', $reason );
            /**
             * Fires after an order refund has been recorded in admin.
             *
             * @param int   $orderId Order ID.
             * @param float $amount  Refund amount.
             * @param string $reason Refund reason.
             * @param array $order   Order data (with related info when available).
             */
            do_action( 'khm_order_refund_recorded', $orderId, $amount, $reason, $order );
        }

        return false !== $result;
    }

    /**
     * Delete an order (soft delete).
     */
    public function delete( int $orderId ): bool {
        // Soft delete by setting status
        $result = $this->updateStatus($orderId, 'deleted', 'Order deleted');

        if ( $result ) {
            do_action('khm_order_deleted', $orderId);
        }

        return $result;
    }

    /**
     * Generate a unique order code.
     */
    public function generateCode(): string {
        global $wpdb;

        do {
            $code = strtoupper(wp_generate_password(10, false));
            /* phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tableName} WHERE code = %s",
                    $code
                )
            );
            /* phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
        } while ( $exists > 0 );

        return apply_filters('khm_order_code', $code);
    }

    /**
     * Calculate tax for an order.
     */
    public function calculateTax( object $order ): float {
        // Get tax settings
        $taxState = get_option('khm_tax_state');
        $taxRate = get_option('khm_tax_rate');

        $tax = 0.0;

        // Calculate tax if state matches
        if ( $taxState && $taxRate && ! empty($order->billing_state) ) {
            if ( strtoupper(trim($order->billing_state)) === strtoupper(trim($taxState)) ) {
                $subtotal = $order->subtotal ?? 0;
                $tax = round( (float) $subtotal * (float) $taxRate, 2);
            }
        }

        // Apply filter
        $values = [
            'subtotal' => $order->subtotal ?? 0,
            'tax_state' => $taxState,
            'tax_rate' => $taxRate,
            'billing_state' => $order->billing_state ?? '',
            'billing_city' => $order->billing_city ?? '',
            'billing_zip' => $order->billing_zip ?? '',
            'billing_country' => $order->billing_country ?? '',
        ];

        return (float) apply_filters('khm_order_tax', $tax, $values, $order);
    }
}
