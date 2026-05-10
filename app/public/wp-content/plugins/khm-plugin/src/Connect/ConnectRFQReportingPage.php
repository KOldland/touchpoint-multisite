<?php
/**
 * Connect RFQ Reporting Page (Phase H)
 *
 * Admin reporting dashboard for the mini-RFQ commission system.
 * Registered as a submenu under "Memberships" alongside existing Connect pages.
 *
 * Shows:
 *   - Commission invoice pipeline (pending → disputed → charged → failed)
 *   - Seller payment registration status
 *   - Buyer validation queue summary + quick approve/reject links
 *   - Date-ranged filtering
 */

namespace KHM\Connect;

use KHM\Migrations\CreateRFPSupportTables;
use KHM\Migrations\CreateSellerPaymentProfilesTable;
use KHM\Migrations\ConnectWorkflowMigration;

defined( 'ABSPATH' ) || exit;

class ConnectRFQReportingPage {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_post_khm_rfq_buyer_approve', [ $this, 'handle_buyer_approve' ] );
		add_action( 'admin_post_khm_rfq_buyer_reject',  [ $this, 'handle_buyer_reject' ] );
		add_action( 'admin_post_khm_rfq_invoice_cancel', [ $this, 'handle_invoice_cancel' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'khm-membership',
			__( 'RFQ Commission Report', 'khm-membership' ),
			__( 'RFQ Commission Report', 'khm-membership' ),
			'manage_options',
			'khm-rfp-commission-report',
			[ $this, 'render_page' ]
		);
	}

	// ─── Main render ───────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'khm-membership' ) );
		}

		$from   = isset( $_GET['from'] ) ? sanitize_text_field( (string) $_GET['from'] ) : gmdate( 'Y-m-01' );
		$to     = isset( $_GET['to'] )   ? sanitize_text_field( (string) $_GET['to'] )   : gmdate( 'Y-m-d' );
		$notice = isset( $_GET['rfq_notice'] ) ? sanitize_key( (string) $_GET['rfq_notice'] ) : '';

		$invoice_stats   = $this->get_invoice_stats( $from, $to );
		$invoice_rows    = $this->get_invoice_rows( $from, $to );
		$payment_stats   = $this->get_payment_profile_stats();
		$validation_stats = $this->get_buyer_validation_stats();
		$pending_buyers  = $this->get_pending_buyers();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RFQ Commission Report', 'khm-membership' ); ?></h1>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'Track commission invoices, seller payment registrations, and buyer verification approvals.', 'khm-membership' ); ?>
			</p>

			<?php if ( 'approved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Buyer approved.', 'khm-membership' ); ?></p></div>
			<?php elseif ( 'rejected' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Buyer rejected.', 'khm-membership' ); ?></p></div>
			<?php elseif ( 'cancelled' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Invoice cancelled.', 'khm-membership' ); ?></p></div>
			<?php endif; ?>

			<!-- Date range filter -->
			<form method="get" style="margin-bottom:16px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
				<input type="hidden" name="page" value="khm-rfp-commission-report" />
				<label><?php esc_html_e( 'From', 'khm-membership' ); ?><br />
					<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" /></label>
				<label><?php esc_html_e( 'To', 'khm-membership' ); ?><br />
					<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" /></label>
				<?php submit_button( __( 'Apply', 'khm-membership' ), 'secondary', '', false ); ?>
			</form>

			<!-- ─── Summary cards ─────────────────────────────────────────── -->
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:24px;">
				<?php
				$cards = [
					[ 'label' => __( 'Pending invoices', 'khm-membership' ),   'value' => $invoice_stats['pending'] ],
					[ 'label' => __( 'Disputed invoices', 'khm-membership' ),  'value' => $invoice_stats['disputed'] ],
					[ 'label' => __( 'Charged invoices', 'khm-membership' ),   'value' => $invoice_stats['charged'] ],
					[ 'label' => __( 'Failed charges', 'khm-membership' ),     'value' => $invoice_stats['failed'] ],
					[ 'label' => __( 'Revenue (GBP)', 'khm-membership' ),      'value' => '£' . number_format( (float) $invoice_stats['total_charged_gbp'], 2 ) ],
					[ 'label' => __( 'Sellers registered', 'khm-membership' ), 'value' => $payment_stats['registered'] . ' / ' . $payment_stats['total'] ],
					[ 'label' => __( 'Buyers pending', 'khm-membership' ),     'value' => $validation_stats['pending'] ],
					[ 'label' => __( 'Buyers verified', 'khm-membership' ),    'value' => $validation_stats['verified'] ],
				];
				foreach ( $cards as $card ) : ?>
					<div style="border:1px solid #dcdcde;border-radius:6px;padding:12px;background:#fff;">
						<div style="font-size:12px;color:#50575e;"><?php echo esc_html( $card['label'] ); ?></div>
						<div style="font-size:22px;font-weight:600;line-height:1.2;"><?php echo esc_html( $card['value'] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- ─── Buyer verification queue ──────────────────────────────── -->
			<h2><?php esc_html_e( 'Buyer Verification Queue', 'khm-membership' ); ?></h2>
			<table class="widefat striped" style="max-width:900px;margin-bottom:24px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Buyer', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Email', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $pending_buyers ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No pending buyer verifications.', 'khm-membership' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $pending_buyers as $buyer ) : ?>
							<tr>
								<td><?php echo esc_html( $buyer['name'] ); ?></td>
								<td><?php echo esc_html( $buyer['email'] ); ?></td>
								<td><span style="color:#d97706;font-weight:600;"><?php esc_html_e( 'Pending', 'khm-membership' ); ?></span></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'khm_rfq_buyer_approve_' . $buyer['id'], 'khm_rfq_nonce' ); ?>
										<input type="hidden" name="action" value="khm_rfq_buyer_approve" />
										<input type="hidden" name="buyer_id" value="<?php echo esc_attr( $buyer['id'] ); ?>" />
										<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Approve', 'khm-membership' ); ?></button>
									</form>
									&nbsp;
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'khm_rfq_buyer_reject_' . $buyer['id'], 'khm_rfq_nonce' ); ?>
										<input type="hidden" name="action" value="khm_rfq_buyer_reject" />
										<input type="hidden" name="buyer_id" value="<?php echo esc_attr( $buyer['id'] ); ?>" />
										<button type="submit" class="button button-small" style="border-color:#c00;color:#c00;"><?php esc_html_e( 'Reject', 'khm-membership' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- ─── Commission invoice pipeline ──────────────────────────── -->
			<h2><?php esc_html_e( 'Commission Invoices', 'khm-membership' ); ?></h2>
			<table class="widefat striped" style="max-width:1100px;margin-bottom:24px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Thread', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Contract Ref', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Rate', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Amount (GBP)', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Claimed', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Auto-debit Date', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $invoice_rows ) ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'No commission invoices in this date range.', 'khm-membership' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $invoice_rows as $inv ) :
							$status_colours = [
								'pending'   => '#d97706',
								'disputed'  => '#dc2626',
								'charged'   => '#059669',
								'failed'    => '#7c3aed',
								'cancelled' => '#6b7280',
							];
							$colour = $status_colours[ $inv->status ] ?? '#374151';
						?>
							<tr>
								<td><?php echo esc_html( (string) $inv->id ); ?></td>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-connect-providers&thread_id=' . $inv->thread_id ) ); ?>">#<?php echo esc_html( (string) $inv->thread_id ); ?></a></td>
								<td><?php echo esc_html( (string) $inv->contract_ref ); ?></td>
								<td><?php echo esc_html( $inv->commission_rate . '%' ); ?></td>
								<td>£<?php echo esc_html( number_format( (float) $inv->commission_amount, 2 ) ); ?></td>
								<td><?php echo esc_html( $inv->claimed_at ? gmdate( 'd M Y', strtotime( $inv->claimed_at ) ) : '—' ); ?></td>
								<td><?php echo esc_html( $inv->auto_debit_date ? gmdate( 'd M Y', strtotime( $inv->auto_debit_date ) ) : '—' ); ?></td>
								<td><span style="color:<?php echo esc_attr( $colour ); ?>;font-weight:600;"><?php echo esc_html( ucfirst( (string) $inv->status ) ); ?></span></td>
								<td>
									<?php if ( in_array( $inv->status, [ 'pending', 'disputed' ], true ) ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
											<?php wp_nonce_field( 'khm_rfq_invoice_cancel_' . $inv->id, 'khm_rfq_nonce' ); ?>
											<input type="hidden" name="action" value="khm_rfq_invoice_cancel" />
											<input type="hidden" name="invoice_id" value="<?php echo esc_attr( $inv->id ); ?>" />
											<input type="hidden" name="from" value="<?php echo esc_attr( $from ); ?>" />
											<input type="hidden" name="to" value="<?php echo esc_attr( $to ); ?>" />
											<button type="submit" class="button button-small"
												onclick="return confirm('<?php esc_attr_e( 'Cancel this invoice? This cannot be undone.', 'khm-membership' ); ?>')">
												<?php esc_html_e( 'Cancel', 'khm-membership' ); ?>
											</button>
										</form>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- ─── Seller payment registrations ──────────────────────────── -->
			<h2><?php esc_html_e( 'Seller Payment Profiles', 'khm-membership' ); ?></h2>
			<p class="description"><?php
				echo esc_html( sprintf(
					/* translators: %1$d registered, %2$d total */
					__( '%1$d of %2$d active sellers have registered a payment method.', 'khm-membership' ),
					(int) $payment_stats['registered'],
					(int) $payment_stats['total']
				) );
			?></p>
		</div>
		<?php
	}

	// ─── Form handlers ─────────────────────────────────────────────────────────

	public function handle_buyer_approve(): void {
		$buyer_id = absint( $_POST['buyer_id'] ?? 0 );
		check_admin_referer( 'khm_rfq_buyer_approve_' . $buyer_id, 'khm_rfq_nonce' );

		if ( ! current_user_can( 'manage_options' ) || ! $buyer_id ) {
			wp_die( esc_html__( 'Access denied.', 'khm-membership' ) );
		}

		$validation = new ConnectBuyerValidationService();
		$validation->set_status( $buyer_id, 'verified' );
		update_user_meta( $buyer_id, 'khm_buyer_validation_status', 'verified' );

		do_action( 'khm_buyer_verification_approved', $buyer_id );

		wp_safe_redirect( add_query_arg( [ 'page' => 'khm-rfp-commission-report', 'rfq_notice' => 'approved' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_buyer_reject(): void {
		$buyer_id = absint( $_POST['buyer_id'] ?? 0 );
		check_admin_referer( 'khm_rfq_buyer_reject_' . $buyer_id, 'khm_rfq_nonce' );

		if ( ! current_user_can( 'manage_options' ) || ! $buyer_id ) {
			wp_die( esc_html__( 'Access denied.', 'khm-membership' ) );
		}

		$validation = new ConnectBuyerValidationService();
		$validation->set_status( $buyer_id, 'rejected' );
		update_user_meta( $buyer_id, 'khm_buyer_validation_status', 'rejected' );

		do_action( 'khm_buyer_verification_rejected', $buyer_id );

		wp_safe_redirect( add_query_arg( [ 'page' => 'khm-rfp-commission-report', 'rfq_notice' => 'rejected' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_invoice_cancel(): void {
		global $wpdb;

		$invoice_id = absint( $_POST['invoice_id'] ?? 0 );
		$from       = sanitize_text_field( (string) ( $_POST['from'] ?? gmdate( 'Y-m-01' ) ) );
		$to         = sanitize_text_field( (string) ( $_POST['to']   ?? gmdate( 'Y-m-d' ) ) );

		check_admin_referer( 'khm_rfq_invoice_cancel_' . $invoice_id, 'khm_rfq_nonce' );

		if ( ! current_user_can( 'manage_options' ) || ! $invoice_id ) {
			wp_die( esc_html__( 'Access denied.', 'khm-membership' ) );
		}

		$table = CreateRFPSupportTables::invoices_table_name();
		$wpdb->update(
			$table,
			[ 'status' => 'cancelled', 'settled_at' => current_time( 'mysql' ) ],
			[ 'id' => $invoice_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		wp_safe_redirect( add_query_arg( [ 'page' => 'khm-rfp-commission-report', 'from' => $from, 'to' => $to, 'rfq_notice' => 'cancelled' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	// ─── Data queries ──────────────────────────────────────────────────────────

	private function get_invoice_stats( string $from, string $to ): array {
		global $wpdb;

		$table = CreateRFPSupportTables::invoices_table_name();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, COUNT(*) as cnt, SUM(commission_amount) as total
			 FROM `{$table}`
			 WHERE claimed_at BETWEEN %s AND %s
			 GROUP BY status",
			$from . ' 00:00:00',
			$to   . ' 23:59:59'
		) );

		$stats = [ 'pending' => 0, 'disputed' => 0, 'charged' => 0, 'failed' => 0, 'cancelled' => 0, 'total_charged_gbp' => 0.0 ];

		foreach ( $rows as $row ) {
			if ( isset( $stats[ $row->status ] ) ) {
				$stats[ $row->status ] = (int) $row->cnt;
			}
			if ( 'charged' === $row->status ) {
				$stats['total_charged_gbp'] = (float) $row->total;
			}
		}

		return $stats;
	}

	private function get_invoice_rows( string $from, string $to ): array {
		global $wpdb;

		$table = CreateRFPSupportTables::invoices_table_name();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM `{$table}`
			 WHERE claimed_at BETWEEN %s AND %s
			 ORDER BY claimed_at DESC
			 LIMIT 200",
			$from . ' 00:00:00',
			$to   . ' 23:59:59'
		) ) ?: [];
	}

	private function get_payment_profile_stats(): array {
		global $wpdb;

		$table = CreateSellerPaymentProfilesTable::table_name();

		$total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$registered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE stripe_customer_id IS NOT NULL AND payment_auth_granted_at IS NOT NULL" );

		return [ 'total' => $total, 'registered' => $registered ];
	}

	private function get_buyer_validation_stats(): array {
		global $wpdb;

		$table = ConnectWorkflowMigration::opportunities_table_name();

		$rows = $wpdb->get_results(
			"SELECT buyer_validation_status, COUNT(DISTINCT buyer_account_id) as cnt
			 FROM `{$table}`
			 WHERE buyer_account_id IS NOT NULL
			 GROUP BY buyer_validation_status"
		) ?: [];

		$stats = [ 'unverified' => 0, 'pending' => 0, 'verified' => 0, 'rejected' => 0 ];

		foreach ( $rows as $row ) {
			if ( isset( $stats[ $row->buyer_validation_status ] ) ) {
				$stats[ $row->buyer_validation_status ] = (int) $row->cnt;
			}
		}

		return $stats;
	}

	private function get_pending_buyers(): array {
		global $wpdb;

		$table = ConnectWorkflowMigration::opportunities_table_name();

		$rows = $wpdb->get_results(
			"SELECT DISTINCT buyer_account_id FROM `{$table}` WHERE buyer_validation_status = 'pending' AND buyer_account_id IS NOT NULL"
		) ?: [];

		$buyers = [];

		foreach ( $rows as $row ) {
			$user = get_userdata( (int) $row->buyer_account_id );
			$buyers[] = [
				'id'    => (int) $row->buyer_account_id,
				'name'  => $user ? $user->display_name : 'Unknown',
				'email' => $user ? $user->user_email : '',
			];
		}

		return $buyers;
	}
}
