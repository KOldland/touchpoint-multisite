<?php
namespace KHM\Admin;

use KHM\Services\LevelRepository;
use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;

class OrdersPage {
	public const PAGE_SLUG      = 'khm-orders';
	public const SETTINGS_GROUP = 'khm_orders';

	private OrderRepository $orders;
	private LevelRepository $levels;
	private MembershipRepository $memberships;

	public function __construct(
		?OrderRepository $orders = null,
		?LevelRepository $levels = null,
		?MembershipRepository $memberships = null
	) {
		$this->orders       = $orders ?: new OrderRepository();
		$this->levels       = $levels ?: new LevelRepository();
		$this->memberships  = $memberships ?: new MembershipRepository();
	}

	public function register(): void {
		add_action( 'admin_post_khm_order_mark_status', [ $this, 'handle_status_update' ] );
		add_action( 'admin_post_khm_order_refund', [ $this, 'handle_refund_request' ] );
		add_action( 'admin_post_khm_order_update_notes', [ $this, 'handle_update_notes' ] );
		add_action( 'admin_post_khm_order_resend_receipt', [ $this, 'handle_resend_receipt' ] );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage orders.', 'khm-membership' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'view' === $action ) {
			$order_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

			if ( ! $order_id ) {
				$this->add_notice( 'invalid_order', __( 'Invalid order selected.', 'khm-membership' ), 'error' );
				$this->render_list();
				return;
			}

			$order = $this->orders->getWithRelations( $order_id );

			if ( ! $order ) {
				$this->add_notice( 'order_not_found', __( 'Order not found.', 'khm-membership' ), 'error' );
				$this->render_list();
				return;
			}

			$this->render_detail( $order );
			return;
		}

		$this->render_list();
	}

	private function render_list(): void {
		$filters = $this->get_filters();
		$level_map = $this->levels->getNameMap();

		add_filter(
			'khm_orders_level_map',
			static function () use ( $level_map ) {
				return $level_map;
			}
		);

		$list_table = new OrdersListTable( $this->orders, $filters );
		$list_table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Orders', 'khm-membership' ) . '</h1>';
		echo '<hr class="wp-header-end">';

		settings_errors( self::SETTINGS_GROUP );

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';
		$list_table->search_box( __( 'Search Orders', 'khm-membership' ), 'khm-orders' );
		$list_table->display();
		echo '</form>';
		echo '</div>';
	}

	private function render_detail( array $order ): void {
		$user_link    = get_edit_user_link( (int) $order['user_id'] );
		$back_url     = add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'admin.php' ) );
		$membership   = $this->memberships->find( (int) $order['user_id'], (int) $order['membership_id'] );
		$membership_link = '';

		if ( $membership && isset( $membership->id ) ) {
			$membership_link = add_query_arg(
				[
					'page'   => MembersPage::PAGE_SLUG,
					'action' => 'view',
					'id'     => (int) $membership->id,
				],
				admin_url( 'admin.php' )
			);
		}

		echo '<div class="wrap khm-order-detail">';
		echo '<h1>' . sprintf( esc_html__( 'Order #%s', 'khm-membership' ), esc_html( $order['code'] ) ) . '</h1>';
		echo '<a href="' . esc_url( $back_url ) . '" class="page-title-action">&larr; ' . esc_html__( 'Back to Orders', 'khm-membership' ) . '</a>';

		$email_preview_url = add_query_arg(
			[
				'page'         => 'khm-email-preview',
				'order_id'     => (int) $order['id'],
				'khm_template' => 'invoice',
			],
			admin_url( 'admin.php' )
		);

		echo '<a href="' . esc_url( $email_preview_url ) . '" class="page-title-action">' . esc_html__( 'Email Preview', 'khm-membership' ) . '</a>';
		echo '<hr class="wp-header-end">';

		settings_errors( self::SETTINGS_GROUP );

		echo '<div class="khm-order-grid">';
		$this->render_summary_section( $order, $membership_link );
		$this->render_customer_section( $order, $user_link );
		$this->render_pricing_section( $order );
		$this->render_discounts_section( $order );
		$this->render_status_actions( $order );
		$this->render_refund_section( $order );
		$this->render_notes_section( $order );
		echo '</div>';

		echo '</div>';
	}

	public function handle_status_update(): void {
		$this->ensure_capability();

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		check_admin_referer( 'khm_order_mark_status_' . $order_id );

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $order_id || ! $status ) {
			$this->add_notice( 'status_error', __( 'Unable to update order status.', 'khm-membership' ), 'error' );
			$this->redirect();
		}

		if ( $this->orders->updateStatus( $order_id, $status ) ) {
			$this->add_notice( 'status_updated', __( 'Order status updated.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'status_failed', __( 'Failed to update order status.', 'khm-membership' ), 'error' );
		}

		$this->redirect( [ 'action' => 'view', 'id' => $order_id ] );
	}

	public function handle_refund_request(): void {
		$this->ensure_capability();

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		check_admin_referer( 'khm_order_refund_' . $order_id );

		$amount_raw = isset( $_POST['refund_amount'] ) ? wp_unslash( $_POST['refund_amount'] ) : '';
		$reason     = isset( $_POST['refund_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['refund_reason'] ) ) : '';

		$amount = (float) $amount_raw;

		if ( $amount <= 0 ) {
			$this->add_notice( 'refund_invalid', __( 'Please enter a valid refund amount.', 'khm-membership' ), 'error' );
			$this->redirect( [ 'action' => 'view', 'id' => $order_id ] );
		}

		if ( $this->orders->recordRefund( $order_id, $amount, $reason ?: __( 'Manual refund recorded in admin.', 'khm-membership' ) ) ) {
			$this->add_notice( 'refund_recorded', __( 'Refund recorded.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'refund_failed', __( 'Failed to record refund.', 'khm-membership' ), 'error' );
		}

		$this->redirect( [ 'action' => 'view', 'id' => $order_id ] );
	}

	public function handle_update_notes(): void {
		$this->ensure_capability();

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		check_admin_referer( 'khm_order_update_notes_' . $order_id );

		$notes = isset( $_POST['order_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['order_notes'] ) ) : '';

		if ( $this->orders->updateNotes( $order_id, $notes ) ) {
			$this->add_notice( 'notes_saved', __( 'Notes saved.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'notes_failed', __( 'Failed to save notes.', 'khm-membership' ), 'error' );
		}

		$this->redirect( [ 'action' => 'view', 'id' => $order_id ] );
	}

	public function handle_resend_receipt(): void {
		$this->ensure_capability();

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( 'khm_order_resend_receipt_' . $order_id );

		if ( $order_id ) {
			do_action( 'khm_order_resend_receipt', $order_id );
			$this->add_notice( 'receipt_sent', __( 'Receipt resend queued.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'receipt_failed', __( 'Unable to resend receipt.', 'khm-membership' ), 'error' );
		}

		$this->redirect( [ 'action' => 'view', 'id' => $order_id ] );
	}

	private function render_summary_section( array $order, string $membership_link ): void {
		echo '<div class="khm-order-section">';
		echo '<h2>' . esc_html__( 'Order Summary', 'khm-membership' ) . '</h2>';
		echo '<table class="form-table">';
		echo '<tr><th>' . esc_html__( 'Order Code', 'khm-membership' ) . '</th><td><strong>' . esc_html( $order['code'] ) . '</strong></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'khm-membership' ) . '</th><td><span class="khm-badge khm-status-' . esc_attr( $order['status'] ) . '">' . esc_html( ucfirst( $order['status'] ) ) . '</span></td></tr>';
		echo '<tr><th>' . esc_html__( 'Date', 'khm-membership' ) . '</th><td>' . esc_html( $this->format_date_display( $order['timestamp'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Total', 'khm-membership' ) . '</th><td><strong>' . esc_html( $this->format_price( (float) $order['total'] ) ) . '</strong></td></tr>';
		echo '<tr><th>' . esc_html__( 'Gateway', 'khm-membership' ) . '</th><td>' . esc_html( $order['gateway'] ? ucfirst( $order['gateway'] ) : '—' ) . '</td></tr>';

		if ( ! empty( $order['payment_transaction_id'] ) ) {
			echo '<tr><th>' . esc_html__( 'Transaction ID', 'khm-membership' ) . '</th><td><code>' . esc_html( $order['payment_transaction_id'] ) . '</code></td></tr>';
		}

		if ( ! empty( $order['subscription_transaction_id'] ) ) {
			echo '<tr><th>' . esc_html__( 'Subscription ID', 'khm-membership' ) . '</th><td><code>' . esc_html( $order['subscription_transaction_id'] ) . '</code></td></tr>';
		}

		if ( $membership_link ) {
			echo '<tr><th>' . esc_html__( 'Membership Record', 'khm-membership' ) . '</th><td><a href="' . esc_url( $membership_link ) . '">' . esc_html__( 'View membership record', 'khm-membership' ) . '</a></td></tr>';
		}

		echo '</table>';
		echo '</div>';
	}

	private function render_customer_section( array $order, string $user_link ): void {
		echo '<div class="khm-order-section">';
		echo '<h2>' . esc_html__( 'Customer', 'khm-membership' ) . '</h2>';
		echo '<table class="form-table">';
		echo '<tr><th>' . esc_html__( 'User', 'khm-membership' ) . '</th><td><a href="' . esc_url( $user_link ) . '">' . esc_html( $order['display_name'] ?? $order['user_login'] ) . '</a></td></tr>';
		echo '<tr><th>' . esc_html__( 'Email', 'khm-membership' ) . '</th><td><a href="mailto:' . esc_attr( $order['user_email'] ) . '">' . esc_html( $order['user_email'] ) . '</a></td></tr>';
		echo '<tr><th>' . esc_html__( 'Level', 'khm-membership' ) . '</th><td>' . esc_html( $order['level_name'] ?? __( 'Unknown', 'khm-membership' ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Billing Name', 'khm-membership' ) . '</th><td>' . esc_html( $order['billing_name'] ?: '—' ) . '</td></tr>';

		if ( ! empty( $order['billing_street'] ) ) {
			echo '<tr><th>' . esc_html__( 'Billing Address', 'khm-membership' ) . '</th><td>';
			echo esc_html( $order['billing_street'] ) . '<br>';
			echo esc_html( $order['billing_city'] . ', ' . $order['billing_state'] . ' ' . $order['billing_zip'] ) . '<br>';
			echo esc_html( $order['billing_country'] );
			echo '</td></tr>';
		}

		echo '</table>';
		echo '</div>';
	}

	private function render_pricing_section( array $order ): void {
		echo '<div class="khm-order-section">';
		echo '<h2>' . esc_html__( 'Amounts', 'khm-membership' ) . '</h2>';
		echo '<table class="form-table">';
		echo '<tr><th>' . esc_html__( 'Subtotal', 'khm-membership' ) . '</th><td>' . esc_html( $this->format_price( (float) $order['subtotal'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Tax', 'khm-membership' ) . '</th><td>' . esc_html( $this->format_price( (float) $order['tax'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Total', 'khm-membership' ) . '</th><td><strong>' . esc_html( $this->format_price( (float) $order['total'] ) ) . '</strong></td></tr>';
		echo '</table>';
		echo '</div>';
	}

	private function render_discounts_section( array $order ): void {
		echo '<div class="khm-order-section">';
		echo '<h2>' . esc_html__( 'Discounts & Trials', 'khm-membership' ) . '</h2>';
		echo '<table class="form-table">';
		echo '<tr><th>' . esc_html__( 'Discount Code', 'khm-membership' ) . '</th><td>' . ( $order['discount_code'] ? '<code>' . esc_html( $order['discount_code'] ) . '</code>' : '—' ) . '</td></tr>';

		if ( ! empty( $order['discount_amount'] ) ) {
			echo '<tr><th>' . esc_html__( 'Discount Amount', 'khm-membership' ) . '</th><td>' . esc_html( $this->format_price( (float) $order['discount_amount'] * -1 ) ) . '</td></tr>';
		}

		echo '<tr><th>' . esc_html__( 'First Payment Only', 'khm-membership' ) . '</th><td>' . ( ! empty( $order['first_payment_only'] ) ? esc_html__( 'Yes', 'khm-membership' ) : esc_html__( 'No', 'khm-membership' ) ) . '</td></tr>';

		if ( ! empty( $order['trial_days'] ) ) {
			$trial_label = ( ! empty( $order['trial_amount'] ) && (float) $order['trial_amount'] > 0 )
				? sprintf( __( '%1$d days (%2$s due today)', 'khm-membership' ), (int) $order['trial_days'], $this->format_price( (float) $order['trial_amount'] ) )
				: sprintf( __( '%d days (free)', 'khm-membership' ), (int) $order['trial_days'] );
			echo '<tr><th>' . esc_html__( 'Trial', 'khm-membership' ) . '</th><td>' . esc_html( $trial_label ) . '</td></tr>';
		}

		if ( ! empty( $order['recurring_discount_type'] ) && (float) $order['recurring_discount_amount'] > 0 ) {
			$recurring = 'percent' === $order['recurring_discount_type']
				? sprintf( __( '%s%% off each renewal', 'khm-membership' ), number_format( (float) $order['recurring_discount_amount'], 2 ) )
				: sprintf( __( '%s off each renewal', 'khm-membership' ), $this->format_price( (float) $order['recurring_discount_amount'] ) );
			echo '<tr><th>' . esc_html__( 'Recurring Discount', 'khm-membership' ) . '</th><td>' . esc_html( $recurring ) . '</td></tr>';
		}

		echo '</table>';
		echo '</div>';
	}

	private function render_status_actions( array $order ): void {
		$statuses = [
			'success'  => __( 'Mark as Paid', 'khm-membership' ),
			'pending'  => __( 'Mark as Pending', 'khm-membership' ),
			'failed'   => __( 'Mark as Failed', 'khm-membership' ),
			'cancelled'=> __( 'Mark as Cancelled', 'khm-membership' ),
		];

		echo '<div class="khm-order-section">';
		echo '<h2>' . esc_html__( 'Status', 'khm-membership' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="khm-order-status-form">';
		wp_nonce_field( 'khm_order_mark_status_' . (int) $order['id'] );
		echo '<input type="hidden" name="action" value="khm_order_mark_status">';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (int) $order['id'] ) . '">';
		echo '<p><label for="khm-order-status-select">' . esc_html__( 'Update Status', 'khm-membership' ) . '</label><br>';
		echo '<select id="khm-order-status-select" name="status">';
		foreach ( $statuses as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $status, $order['status'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Update', 'khm-membership' ) . '</button></p>';
		echo '</form>';

		$resend_url = wp_nonce_url(
			add_query_arg(
				[
					'action'    => 'khm_order_resend_receipt',
					'order_id'  => (int) $order['id'],
				],
				admin_url( 'admin-post.php' )
			),
			'khm_order_resend_receipt_' . (int) $order['id']
		);

		echo '<p><a class="button" href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend Receipt', 'khm-membership' ) . '</a></p>';
		echo '</div>';
	}

	private function render_refund_section( array $order ): void {
		echo '<div class="khm-order-section">';
		echo '<h2>' . esc_html__( 'Record Refund', 'khm-membership' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="khm-order-refund-form">';
		wp_nonce_field( 'khm_order_refund_' . (int) $order['id'] );
		echo '<input type="hidden" name="action" value="khm_order_refund">';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (int) $order['id'] ) . '">';

		echo '<p><label for="khm-refund-amount">' . esc_html__( 'Refund Amount', 'khm-membership' ) . '</label><br>';
		echo '<input type="number" step="0.01" min="0" id="khm-refund-amount" name="refund_amount" value="' . esc_attr( $order['refund_amount'] ?? '' ) . '" required></p>';

		echo '<p><label for="khm-refund-reason">' . esc_html__( 'Reason', 'khm-membership' ) . '</label><br>';
		echo '<textarea id="khm-refund-reason" name="refund_reason" rows="3" class="large-text">' . esc_textarea( $order['refund_reason'] ?? '' ) . '</textarea></p>';

		echo '<p><button type="submit" class="button button-secondary">' . esc_html__( 'Record Refund', 'khm-membership' ) . '</button></p>';
		echo '</form>';
		echo '</div>';
	}

	private function render_notes_section( array $order ): void {
		echo '<div class="khm-order-section">';
		echo '<h2>' . esc_html__( 'Notes', 'khm-membership' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'khm_order_update_notes_' . (int) $order['id'] );
		echo '<input type="hidden" name="action" value="khm_order_update_notes">';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (int) $order['id'] ) . '">';
		echo '<textarea name="order_notes" rows="6" class="large-text">' . esc_textarea( $order['notes'] ?? '' ) . '</textarea>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save Notes', 'khm-membership' ) . '</button></p>';
		echo '</form>';
		echo '</div>';
	}

	private function ensure_capability(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage orders.', 'khm-membership' ) );
		}
	}

	private function get_filters(): array {
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$gateway = isset( $_GET['gateway'] ) ? sanitize_key( wp_unslash( $_GET['gateway'] ) ) : '';
		$level   = isset( $_GET['level'] ) ? (int) $_GET['level'] : 0;

		return [
			'search'  => $search,
			'status'  => $status,
			'gateway' => $gateway,
			'level'   => $level > 0 ? $level : null,
		];
	}

	private function add_notice( string $code, string $message, string $type = 'success' ): void {
		add_settings_error( self::SETTINGS_GROUP, $code, $message, $type );
		set_transient( 'settings_errors', get_settings_errors( self::SETTINGS_GROUP ), 30 );
	}

	private function redirect( array $args = [] ): void {
		$url = add_query_arg(
			array_merge(
				[ 'page' => self::PAGE_SLUG ],
				$args
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	private function format_date_display( $value ): string {
		if ( empty( $value ) ) {
			return '—';
		}

		$timestamp = strtotime( (string) $value );
		if ( ! $timestamp ) {
			return '—';
		}

		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	private function format_price( float $amount ): string {
		if ( function_exists( 'khm_format_price' ) ) {
			return khm_format_price( $amount );
		}

		return '$' . number_format_i18n( $amount, 2 );
	}
}
