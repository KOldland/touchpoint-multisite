<?php

namespace KHM\Admin;

use KHM\Services\OrderRepository;

if ( ! class_exists('WP_List_Table') ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * OrdersListTable
 *
 * Displays orders list in WordPress admin using WP_List_Table.
 */
class OrdersListTable extends \WP_List_Table {

    private OrderRepository $repository;
    private array $filters;

    public function __construct( OrderRepository $repository, array $filters = [] ) {
        parent::__construct([
            'singular' => 'order',
            'plural' => 'orders',
            'ajax' => false,
        ]);

        $this->repository = $repository;
        $this->filters    = $filters;
    }

    /**
     * Get columns
     */
    public function get_columns(): array {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'code' => __('Order #', 'khm-membership'),
            'user' => __('User', 'khm-membership'),
            'level' => __('Level', 'khm-membership'),
            'subtotal' => __('Subtotal', 'khm-membership'),
            'tax' => __('Tax', 'khm-membership'),
            'total' => __('Total', 'khm-membership'),
            'gateway' => __('Gateway', 'khm-membership'),
            'status' => __('Status', 'khm-membership'),
            'discount' => __('Discount', 'khm-membership'),
            'trial' => __('Trial', 'khm-membership'),
            'recurring_discount' => __('Recurring Discount', 'khm-membership'),
            'timestamp' => __('Date', 'khm-membership'),
        ];

        return apply_filters('khm_orders_list_columns', $columns);
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns(): array {
        return [
            'code' => [ 'code', false ],
            'total' => [ 'total', false ],
            'status' => [ 'status', false ],
            'timestamp' => [ 'timestamp', true ],
        ];
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions(): array {
        $actions = [
            'delete' => __('Delete', 'khm-membership'),
            'export' => __('Export to CSV', 'khm-membership'),
        ];

        return apply_filters('khm_orders_bulk_actions', $actions);
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action(): void {
        if ( ! isset($_POST['_wpnonce']) ) {
            return;
        }

        $action = $this->current_action();

        if ( ! $action ) {
            return;
        }

        check_admin_referer('bulk-orders');

        $order_ids = isset($_POST['order']) ? array_map('intval', $_POST['order']) : [];

        if ( empty($order_ids) ) {
            return;
        }

        switch ( $action ) {
            case 'delete':
                foreach ( $order_ids as $id ) {
                    $this->repository->delete($id);
                }
                add_settings_error(
                    'khm_messages',
                    'khm_message',
                    // translators: %d is the number of orders deleted.
                    sprintf(__('%d order(s) deleted.', 'khm-membership'), count($order_ids)),
                    'updated'
                );
                break;

            case 'export':
                $this->export_orders($order_ids);
                break;
        }

        do_action('khm_orders_bulk_action_' . $action, $order_ids);
    }

    /**
     * Export orders to CSV
     */
    private function export_orders( array $order_ids ): void {
        $orders = $this->repository->getManyWithRelations( $order_ids );

        if ( empty($orders) ) {
            return;
        }

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=khm-orders-' . gmdate('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, [
            'Order ID',
            'Code',
            'User',
            'Email',
            'Level ID',
            'Subtotal',
            'Tax',
            'Total',
            'Gateway',
            'Status',
            'Transaction ID',
            'Subscription ID',
            'Discount Code',
            'Discount Amount',
            'Trial Days',
            'Trial Amount',
            'First Payment Only',
            'Recurring Discount Type',
            'Recurring Discount Amount',
            'Date',
        ]);

        // Rows
        foreach ( $orders as $order ) {
            fputcsv($output, [
                $order['id'],
                $order['code'],
                $order['user_login'],
                $order['user_email'],
                $order['membership_id'],
                $order['subtotal'],
                $order['tax'],
                $order['total'],
                $order['gateway'],
                $order['status'],
                $order['payment_transaction_id'],
                $order['subscription_transaction_id'],
                $order['discount_code'],
                $order['discount_amount'],
                $order['trial_days'],
                $order['trial_amount'],
                $order['first_payment_only'],
                $order['recurring_discount_type'],
                $order['recurring_discount_amount'],
                $order['timestamp'],
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Prepare items for display
     */
    public function prepare_items(): void {
        $this->process_bulk_action();

        $per_page = $this->get_items_per_page( 'khm_orders_per_page', 20 );
        $current_page = max( 1, $this->get_pagenum() );
        $offset = ( $current_page - 1 ) * $per_page;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $result = $this->repository->paginate(
            [
                'search'   => $this->filters['search'] ?? '',
                'status'   => $this->filters['status'] ?? '',
                'gateway'  => $this->filters['gateway'] ?? '',
                'level_id' => $this->filters['level'] ?? null,
                'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'timestamp',
                'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'DESC',
                'per_page' => $per_page,
                'offset'   => $offset,
            ]
        );

        $this->items = array_map(
            static function ( array $row ): array {
                $row['subtotal'] = (float) ( $row['subtotal'] ?? 0 );
                $row['tax']      = (float) ( $row['tax'] ?? 0 );
                $row['total']    = (float) ( $row['total'] ?? 0 );
                return $row;
            },
            $result['items']
        );

        $this->set_pagination_args([
            'total_items' => (int) $result['total'],
            'per_page' => $per_page,
            'total_pages' => $per_page ? (int) ceil( $result['total'] / $per_page ) : 0,
        ]);
    }

    /**
     * Default column output
     */
    public function column_default( $item, $column_name ) {
        return $item[ $column_name ] ?? '';
    }

    /**
     * Checkbox column
     */
    public function column_cb( $item ): string {
        return sprintf('<input type="checkbox" name="order[]" value="%d" />', $item['id']);
    }

    /**
     * Code column
     */
    public function column_code( $item ): string {
        $edit_url = add_query_arg([
            'page' => OrdersPage::PAGE_SLUG,
            'action' => 'view',
             'id' => $item['id'],
        ], admin_url('admin.php'));

        $actions = [
            'view' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('View', 'khm-membership')),
            'email_preview' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(
                    add_query_arg(
                        [
                            'page' => 'khm-email-preview',
                            'order_id' => $item['id'],
                            'khm_template' => 'invoice',
                        ],
                        admin_url('admin.php')
                    )
                ),
                __('Email Preview', 'khm-membership')
            ),
        ];

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url($edit_url),
            esc_html($item['code']),
            $this->row_actions($actions)
        );
    }

    /**
     * User column
     */
    public function column_user( $item ): string {
        return sprintf(
            '<a href="%s">%s</a>',
            esc_url(get_edit_user_link($item['user_id'])),
            esc_html($item['display_name'] ?? $item['user_login'])
        );
    }

    /**
     * Level column
     */
    public function column_level( $item ): string {
        $level = $item['level_name'] ?? '';
        if ( ! $level ) {
            $level = $this->get_level_name($item['membership_id']);
        }
        return esc_html($level);
    }

    /**
     * Money columns
     */
    public function column_subtotal( $item ): string {
        return $this->format_price($item['subtotal']);
    }

    public function column_tax( $item ): string {
        return $this->format_price((float) $item['tax']);
    }

    public function column_total( $item ): string {
        return '<strong>' . $this->format_price((float) $item['total']) . '</strong>';
    }

    /**
     * Discount column: show code and amount
     */
    public function column_discount( $item ): string {
        $code = $item['discount_code'] ?? '';
        $amount = (float) ( $item['discount_amount'] ?? 0 );
        if ( ! empty($code) && $amount > 0 ) {
            return sprintf('%s (%s)', esc_html($code), $this->format_price($amount * -1));
        }
        return '—';
    }

    /**
     * Trial column: show days and amount
     */
    public function column_trial( $item ): string {
        $days = (int) ( $item['trial_days'] ?? 0 );
        $amount = (float) ( $item['trial_amount'] ?? 0 );
        if ( $days > 0 ) {
            return $amount > 0
                ? sprintf( _n('%d day (%s)', '%d days (%s)', $days, 'khm-membership'), $days, $this->format_price($amount) )
                : sprintf( _n('%d day (free)', '%d days (free)', $days, 'khm-membership'), $days );
        }
        return '—';
    }

    /**
     * Recurring discount column: show type and amount
     */
    public function column_recurring_discount( $item ): string {
        $type = $item['recurring_discount_type'] ?? '';
        $amount = (float) ( $item['recurring_discount_amount'] ?? 0 );
        if ( $type && $amount > 0 ) {
            if ( $type === 'percent' ) {
                return sprintf('%s%% / renewal', number_format($amount, 2));
            }
            return sprintf('%s / renewal', $this->format_price($amount));
        }
        return '—';
    }

    /**
     * Gateway column
     */
    public function column_gateway( $item ): string {
        return esc_html( $item['gateway'] ? ucfirst($item['gateway']) : '—' );
    }

    /**
     * Status column
     */
    public function column_status( $item ): string {
        $status = $item['status'];
        $class = 'khm-status-' . $status;

        return sprintf(
            '<span class="khm-badge %s">%s</span>',
            esc_attr($class),
            esc_html(ucfirst($status))
        );
    }

    /**
     * Timestamp column
     */
    public function column_timestamp( $item ): string {
        if ( empty($item['timestamp']) ) {
            return '—';
        }
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['timestamp']));
    }

    /**
     * Format price
     */
    private function format_price( float $amount ): string {
        if ( function_exists( 'khm_format_price' ) ) {
            return khm_format_price( $amount );
        }

        return '$' . number_format($amount, 2);
    }

    /**
     * Get level name by ID
     */
    private function get_level_name( int $level_id ): string {
        global $wpdb;

        $level = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}khm_membership_levels WHERE id = %d",
            $level_id
        ));

        return $level ?: __('Unknown', 'khm-membership');
    }

    /**
     * Display extra table navigation
     */
    protected function extra_tablenav( $which ): void {
        if ( $which !== 'top' ) {
            return;
        }

        $current_status = $this->filters['status'] ?? '';
        $current_gateway = $this->filters['gateway'] ?? '';
        $current_level = $this->filters['level'] ?? null;
        $level_map = apply_filters( 'khm_orders_level_map', [] );

        ?>
        <div class="alignleft actions">
            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'khm-membership'); ?></option>
                <option value="success" <?php selected($current_status, 'success'); ?>><?php esc_html_e('Success', 'khm-membership'); ?></option>
                <option value="pending" <?php selected($current_status, 'pending'); ?>><?php esc_html_e('Pending', 'khm-membership'); ?></option>
                <option value="cancelled" <?php selected($current_status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'khm-membership'); ?></option>
                <option value="refunded" <?php selected($current_status, 'refunded'); ?>><?php esc_html_e('Refunded', 'khm-membership'); ?></option>
                <option value="failed" <?php selected($current_status, 'failed'); ?>><?php esc_html_e('Failed', 'khm-membership'); ?></option>
            </select>

            <select name="gateway">
                <option value=""><?php esc_html_e('All Gateways', 'khm-membership'); ?></option>
                <option value="stripe" <?php selected($current_gateway, 'stripe'); ?>><?php esc_html_e('Stripe', 'khm-membership'); ?></option>
                <option value="paypal" <?php selected($current_gateway, 'paypal'); ?>><?php esc_html_e('PayPal', 'khm-membership'); ?></option>
                <option value="check" <?php selected($current_gateway, 'check'); ?>><?php esc_html_e('Check', 'khm-membership'); ?></option>
            </select>

            <?php if ( ! empty( $level_map ) ) : ?>
            <select name="level">
                <option value=""><?php esc_html_e( 'All Levels', 'khm-membership' ); ?></option>
                <?php foreach ( $level_map as $level_id => $level_name ) : ?>
                    <option value="<?php echo esc_attr( $level_id ); ?>" <?php selected( (int) $current_level, (int) $level_id ); ?>>
                        <?php echo esc_html( $level_name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <?php submit_button(__('Filter', 'khm-membership'), '', 'filter_action', false); ?>
        </div>
        <?php
    }
}
