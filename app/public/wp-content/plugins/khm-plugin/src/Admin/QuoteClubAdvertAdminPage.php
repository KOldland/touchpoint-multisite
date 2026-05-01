<?php

namespace KHM\Admin;

/**
 * Admin page: Sponsor advert creative review queue.
 *
 * Registered as a submenu under editorial_planner.
 * Allows editors to approve, reject (with reason), pause, or weight each
 * sponsor-submitted ad creative.
 */
class QuoteClubAdvertAdminPage {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ], 22 );
		add_action( 'wp_ajax_khm_advert_action', [ $this, 'handle_ajax' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'editorial_planner',
			__( 'Sponsor Adverts', 'khm-membership' ),
			__( 'Sponsor Adverts', 'khm-membership' ),
			'edit_posts',
			'khm-qc-adverts',
			[ $this, 'render' ]
		);
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	public function handle_ajax(): void {
		check_ajax_referer( 'khm_advert_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Insufficient permissions.', 403 );
		}

		$action  = sanitize_text_field( $_POST['advert_action'] ?? '' );
		$id      = absint( $_POST['advert_id'] ?? 0 );
		$reason  = sanitize_textarea_field( $_POST['reason'] ?? '' );
		$weight  = isset( $_POST['weight'] ) ? min( 10, max( 1, absint( $_POST['weight'] ) ) ) : null;

		if ( ! $id ) {
			wp_send_json_error( 'Missing advert ID.' );
		}

		$allowed_actions = [ 'approve', 'reject', 'pause', 'restore' ];
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			wp_send_json_error( 'Invalid action.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );
		if ( ! $row ) {
			wp_send_json_error( 'Advert not found.' );
		}

		$update = [ 'updated_at' => current_time( 'mysql', true ) ];

		switch ( $action ) {
			case 'approve':
				$update['status']           = 'approved';
				$update['rejection_reason'] = null;
				if ( $weight !== null ) {
					$update['weight'] = $weight;
				}
				break;
			case 'reject':
				$update['status']           = 'rejected';
				$update['rejection_reason'] = $reason;
				break;
			case 'pause':
				$update['status'] = 'paused';
				break;
			case 'restore':
				// Return paused/approved back to pending for re-review.
				$update['status'] = 'pending';
				break;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update( $table, $update, [ 'id' => $id ] );

		// Notify sponsor of approval or rejection.
		if ( in_array( $action, [ 'approve', 'reject' ], true ) ) {
			$user = get_user_by( 'id', $row->user_id );
			if ( $user ) {
				$subject = 'approve' === $action
					? '[QuoteClub] Your advert has been approved'
					: '[QuoteClub] Your advert was not approved';
				$message = 'approve' === $action
					? sprintf( "Your advert \"%s\" has been approved and will start serving shortly.", $row->title )
					: sprintf( "Your advert \"%s\" was not approved.\n\nReason: %s\n\nPlease revise and resubmit.", $row->title, $reason );
				wp_mail( $user->user_email, $subject, $message );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updated = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );
		wp_send_json_success( [
			'id'               => (int) $updated->id,
			'status'           => $updated->status,
			'weight'           => (int) $updated->weight,
			'rejection_reason' => $updated->rejection_reason,
		] );
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function render(): void {
		$allowed_statuses = [ 'pending', 'approved', 'rejected', 'paused', 'all' ];
		$status_filter    = isset( $_GET['qc_status'] ) ? sanitize_text_field( $_GET['qc_status'] ) : 'pending';
		if ( ! in_array( $status_filter, $allowed_statuses, true ) ) {
			$status_filter = 'pending';
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';

		// Graceful no-op if migration hasn't run yet.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			echo '<div class="wrap"><h1>Sponsor Adverts</h1><p>Adverts table not yet created. Re-activate the plugin to run migrations.</p></div>';
			return;
		}

		$where = $status_filter !== 'all'
			? $wpdb->prepare( 'WHERE a.status = %s', $status_filter )
			: '';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			"SELECT a.*, u.display_name, u.user_email,
			        s.name AS sponsor_name
			 FROM `{$table}` a
			 LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
			 LEFT JOIN {$wpdb->prefix}khm_sponsors s ON s.id = a.sponsor_id
			 {$where}
			 ORDER BY a.created_at DESC
			 LIMIT 200",
			ARRAY_A
		);

		$nonce     = wp_create_nonce( 'khm_advert_admin' );
		$admin_url = esc_url( admin_url( 'admin.php?page=khm-qc-adverts' ) );

		$tabs = [
			'pending'  => 'Pending Review',
			'approved' => 'Approved',
			'paused'   => 'Paused',
			'rejected' => 'Rejected',
			'all'      => 'All',
		];

		$placement_labels = [
			'commentary'    => 'Commentary',
			'press-release' => 'Press Releases',
			'overview'      => 'Overview',
			'sidebar'       => 'Sidebar',
		];
		?>
		<div class="wrap khm-advert-admin">
			<h1><?php esc_html_e( 'Sponsor Advert Creatives', 'khm-membership' ); ?></h1>
			<p style="color:#6b7280;font-size:13px;margin-bottom:8px">
				<?php esc_html_e( 'Review sponsor-submitted ad creatives. Approve to make live, reject with a reason (sponsor is notified by email), or pause to temporarily hide from rotation.', 'khm-membership' ); ?>
			</p>

			<nav class="nav-tab-wrapper" style="margin-bottom:1.2rem">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'qc_status', $slug, $admin_url ) ); ?>"
					   class="nav-tab<?php echo $status_filter === $slug ? ' nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No adverts found for this status.', 'khm-membership' ); ?></p>
			<?php else : ?>

			<style>
				.khm-advert-admin table th,
				.khm-advert-admin table td { vertical-align: top; padding: 10px 12px; font-size: 13px; }
				.khm-advert-admin .advert-thumb { max-width: 120px; max-height: 80px; border-radius: 4px; }
				.khm-advert-admin .advert-status { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
				.khm-advert-admin .status-pending  { background: #fef3c7; color: #92400e; }
				.khm-advert-admin .status-approved  { background: #d1fae5; color: #065f46; }
				.khm-advert-admin .status-rejected  { background: #fee2e2; color: #991b1b; }
				.khm-advert-admin .status-paused    { background: #e5e7eb; color: #6b7280; }
				.khm-advert-admin .advert-actions   { display: flex; flex-direction: column; gap: 6px; min-width: 160px; }
				.khm-advert-admin .advert-actions button { width: 100%; }
				.khm-advert-admin .reject-form      { display: none; margin-top: 6px; }
				.khm-advert-admin .reject-form textarea { width: 100%; min-height: 60px; font-size: 12px; }
				.khm-advert-admin .weight-row       { display: flex; gap: 6px; align-items: center; font-size: 12px; }
				.khm-advert-admin .weight-row input { width: 50px; }
				.khm-advert-admin .action-msg       { font-size: 12px; margin-top: 4px; }
			</style>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:130px"><?php esc_html_e( 'Creative', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Details', 'khm-membership' ); ?></th>
						<th style="width:100px"><?php esc_html_e( 'Metrics', 'khm-membership' ); ?></th>
						<th style="width:200px"><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $row ) :
					$status_cls  = 'status-' . $row['status'];
					$thumb       = $row['media_url'] ? '<img src="' . esc_url( $row['media_url'] ) . '" alt="' . esc_attr( $row['alt_text'] ) . '" class="advert-thumb">' : '<span style="color:#9ca3af;font-size:12px">No image</span>';
					$click_link  = $row['click_url'] ? '<a href="' . esc_url( $row['click_url'] ) . '" target="_blank" rel="noopener">Click URL</a>' : '—';
					$rej_reason  = $row['rejection_reason'] ? '<div style="margin-top:4px;font-size:11px;color:#991b1b">Rejected: ' . esc_html( $row['rejection_reason'] ) . '</div>' : '';
					$is_editable = in_array( $row['status'], [ 'pending', 'approved', 'paused', 'rejected' ], true );
				?>
					<tr id="advert-row-<?php echo (int) $row['id']; ?>">
						<td><?php echo $thumb; ?></td>
						<td>
							<strong><?php echo esc_html( $row['title'] ?: '(untitled)' ); ?></strong><br>
							<span class="advert-status <?php echo esc_attr( $status_cls ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span>
							<?php echo $rej_reason; ?>
							<div style="margin-top:6px;font-size:12px;color:#374151">
								<strong><?php esc_html_e( 'Sponsor:', 'khm-membership' ); ?></strong>
								<?php echo esc_html( $row['sponsor_name'] ?: '—' ); ?>
								(<?php echo esc_html( $row['display_name'] ?: $row['user_email'] ); ?>)<br>
								<strong><?php esc_html_e( 'Placement:', 'khm-membership' ); ?></strong>
								<?php echo esc_html( $placement_labels[ $row['placement'] ] ?? $row['placement'] ); ?><br>
								<strong><?php esc_html_e( 'Click URL:', 'khm-membership' ); ?></strong> <?php echo $click_link; ?><br>
								<strong><?php esc_html_e( 'Alt:', 'khm-membership' ); ?></strong> <?php echo esc_html( $row['alt_text'] ?: '—' ); ?><br>
								<strong><?php esc_html_e( 'Submitted:', 'khm-membership' ); ?></strong> <?php echo esc_html( $row['created_at'] ); ?>
							</div>
						</td>
						<td style="font-size:12px">
							<span title="Impressions">👁 <?php echo number_format( (int) $row['impressions'] ); ?></span><br>
							<span title="Clicks">🖱 <?php echo number_format( (int) $row['clicks'] ); ?></span><br>
							<span title="Weight">⚖ <?php echo (int) $row['weight']; ?>/10</span>
						</td>
						<td>
							<?php if ( $is_editable ) : ?>
							<div class="advert-actions" data-id="<?php echo (int) $row['id']; ?>">
								<?php if ( $row['status'] !== 'approved' ) : ?>
								<!-- Approve + optional weight -->
								<div>
									<div class="weight-row">
										<label for="weight-<?php echo (int) $row['id']; ?>"><?php esc_html_e( 'Weight:', 'khm-membership' ); ?></label>
										<input type="number" id="weight-<?php echo (int) $row['id']; ?>"
										       class="advert-weight-input" min="1" max="10"
										       value="<?php echo (int) $row['weight']; ?>">
										<span style="color:#6b7280">/10</span>
									</div>
									<button class="button button-primary khm-advert-approve" style="margin-top:4px">
										<?php esc_html_e( 'Approve', 'khm-membership' ); ?>
									</button>
								</div>
								<?php else : ?>
								<button class="button khm-advert-pause">
									<?php esc_html_e( 'Pause', 'khm-membership' ); ?>
								</button>
								<?php endif; ?>

								<?php if ( $row['status'] !== 'rejected' ) : ?>
								<!-- Reject -->
								<div>
									<button class="button khm-advert-reject-toggle" style="color:#991b1b;border-color:#fca5a5">
										<?php esc_html_e( 'Reject…', 'khm-membership' ); ?>
									</button>
									<div class="reject-form">
										<textarea class="advert-reject-reason" placeholder="<?php esc_attr_e( 'Reason for rejection (will be emailed to sponsor)…', 'khm-membership' ); ?>"></textarea>
										<button class="button khm-advert-reject-confirm" style="margin-top:4px;color:#991b1b;border-color:#fca5a5">
											<?php esc_html_e( 'Confirm rejection', 'khm-membership' ); ?>
										</button>
									</div>
								</div>
								<?php endif; ?>

								<?php if ( in_array( $row['status'], [ 'paused', 'rejected' ], true ) ) : ?>
								<button class="button khm-advert-restore">
									<?php esc_html_e( 'Restore to pending', 'khm-membership' ); ?>
								</button>
								<?php endif; ?>

								<div class="action-msg" id="advert-msg-<?php echo (int) $row['id']; ?>"></div>
							</div>
							<?php else : ?>
							<span style="color:#9ca3af;font-size:12px"><?php esc_html_e( 'No actions available', 'khm-membership' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php endif; ?>
		</div>

		<script>
		(function () {
			'use strict';
			var NONCE = '<?php echo esc_js( $nonce ); ?>';
			var AJAX  = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

			function doAction(id, action, extra, btn) {
				var msg = document.getElementById('advert-msg-' + id);
				if (msg) { msg.textContent = 'Saving…'; msg.style.color = '#374151'; }
				if (btn) { btn.disabled = true; }

				var body = new URLSearchParams({
					action:        'khm_advert_action',
					nonce:         NONCE,
					advert_id:     id,
					advert_action: action
				});
				if (extra.reason !== undefined) { body.append('reason', extra.reason); }
				if (extra.weight !== undefined) { body.append('weight', extra.weight); }

				fetch(AJAX, { method: 'POST', body: body })
					.then(function (r) { return r.json(); })
					.then(function (data) {
						if (btn) { btn.disabled = false; }
						if (data.success) {
							if (msg) { msg.style.color = '#065f46'; msg.textContent = 'Saved. Reload to update table.'; }
							// Update status badge inline
							var row = document.getElementById('advert-row-' + id);
							if (row) {
								var badge = row.querySelector('.advert-status');
								if (badge) {
									badge.className = 'advert-status status-' + data.data.status;
									badge.textContent = data.data.status.charAt(0).toUpperCase() + data.data.status.slice(1);
								}
							}
						} else {
							if (msg) { msg.style.color = '#991b1b'; msg.textContent = data.data || 'Error.'; }
						}
					}).catch(function () {
						if (btn) { btn.disabled = false; }
						if (msg) { msg.style.color = '#991b1b'; msg.textContent = 'Network error.'; }
					});
			}

			document.querySelectorAll('.advert-actions').forEach(function (el) {
				var id = el.dataset.id;

				var approveBtn = el.querySelector('.khm-advert-approve');
				if (approveBtn) {
					approveBtn.addEventListener('click', function () {
						var weightInput = el.querySelector('.advert-weight-input');
						var weight = weightInput ? parseInt(weightInput.value, 10) : 5;
						doAction(id, 'approve', { weight: weight }, approveBtn);
					});
				}

				var pauseBtn = el.querySelector('.khm-advert-pause');
				if (pauseBtn) {
					pauseBtn.addEventListener('click', function () {
						doAction(id, 'pause', {}, pauseBtn);
					});
				}

				var restoreBtn = el.querySelector('.khm-advert-restore');
				if (restoreBtn) {
					restoreBtn.addEventListener('click', function () {
						doAction(id, 'restore', {}, restoreBtn);
					});
				}

				var rejectToggle = el.querySelector('.khm-advert-reject-toggle');
				var rejectForm   = el.querySelector('.reject-form');
				if (rejectToggle && rejectForm) {
					rejectToggle.addEventListener('click', function () {
						rejectForm.style.display = rejectForm.style.display === 'none' ? 'block' : 'none';
					});
				}

				var rejectConfirm = el.querySelector('.khm-advert-reject-confirm');
				if (rejectConfirm) {
					rejectConfirm.addEventListener('click', function () {
						var reason = el.querySelector('.advert-reject-reason').value.trim();
						doAction(id, 'reject', { reason: reason }, rejectConfirm);
						if (rejectForm) { rejectForm.style.display = 'none'; }
					});
				}
			});
		}());
		</script>
		<?php
	}
}
