<?php
/**
 * Sponsor Application Admin UI
 *
 * Manages sponsor applications in the WordPress admin panel.
 *
 * @package KHM\Sponsors
 */

namespace KHM\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorApplicationAdminUI {

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_khm_app_approve', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_khm_app_reject', array( $this, 'handle_reject' ) );
	}

	/**
	 * Register admin menu
	 */
	public function register_menu(): void {
		add_submenu_page(
			'khm-sponsorship',
			__( 'Applications', 'khm-membership' ),
			__( 'Applications', 'khm-membership' ),
			'manage_options',
			'khm-sponsorship-applications',
			array( $this, 'render_applications_page' )
		);
	}

	/**
	 * Render applications list page
	 */
	public function render_applications_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_applications';

		// Get action from query
		$action = $_GET['action'] ?? 'list';

		if ( 'view' === $action && isset( $_GET['id'] ) ) {
			$this->render_application_detail( (int) $_GET['id'] );
		} else {
			$this->render_applications_list();
		}
	}

	/**
	 * Render applications list
	 */
	private function render_applications_list(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_applications';

		// Get filter
		$status = $_GET['status'] ?? '';
		$where  = '';
		if ( $status && in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
			$where = $wpdb->prepare( 'WHERE status = %s', $status );
		}

		// Get applications
		$applications = $wpdb->get_results(
			"SELECT * FROM {$table} {$where} ORDER BY created_at DESC",
			ARRAY_A
		);

		// Count by status
		$pending  = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
		$approved = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'approved'" );
		$rejected = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'rejected'" );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sponsor Applications', 'khm-membership' ); ?></h1>

			<div class="subsubsub">
				<a href="admin.php?page=khm-sponsorship-applications<?php echo empty( $status ) ? ' class="current"' : ''; ?>">
					<?php echo esc_html__( 'All', 'khm-membership' ) . ' (' . intval( $pending + $approved + $rejected ) . ')'; ?>
				</a>
				|
				<a href="admin.php?page=khm-sponsorship-applications&status=pending<?php echo 'pending' === $status ? ' class="current"' : ''; ?>">
					<?php echo esc_html__( 'Pending', 'khm-membership' ) . ' (' . intval( $pending ) . ')'; ?>
				</a>
				|
				<a href="admin.php?page=khm-sponsorship-applications&status=approved<?php echo 'approved' === $status ? ' class="current"' : ''; ?>">
					<?php echo esc_html__( 'Approved', 'khm-membership' ) . ' (' . intval( $approved ) . ')'; ?>
				</a>
				|
				<a href="admin.php?page=khm-sponsorship-applications&status=rejected<?php echo 'rejected' === $status ? ' class="current"' : ''; ?>">
					<?php echo esc_html__( 'Rejected', 'khm-membership' ) . ' (' . intval( $rejected ) . ')'; ?>
				</a>
			</div>

			<table class="wp-list-table widefat fixed striped posts">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Company', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Contact', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Sector', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Submitted', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $applications ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No applications found.', 'khm-membership' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $applications as $app ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $app['company_name'] ); ?></strong></td>
								<td>
									<a href="mailto:<?php echo esc_attr( $app['contact_email'] ); ?>">
										<?php echo esc_html( $app['contact_name'] ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $app['sector'] ); ?></td>
								<td>
									<span class="khm-badge khm-badge-<?php echo esc_attr( $app['status'] ); ?>">
										<?php echo esc_html( ucfirst( $app['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $app['created_at'] ) ) ); ?></td>
								<td>
									<a href="admin.php?page=khm-sponsorship-applications&action=view&id=<?php echo intval( $app['id'] ); ?>" class="button button-small">
										<?php esc_html_e( 'View', 'khm-membership' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<style>
				.khm-badge {
					display: inline-block;
					padding: 4px 8px;
					border-radius: 3px;
					font-size: 12px;
					font-weight: bold;
				}
				.khm-badge-pending {
					background-color: #fff8e5;
					color: #856404;
				}
				.khm-badge-approved {
					background-color: #d4edda;
					color: #155724;
				}
				.khm-badge-rejected {
					background-color: #f8d7da;
					color: #721c24;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Render application detail/review page
	 */
	private function render_application_detail( $app_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_applications';

		$app = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $app_id ),
			ARRAY_A
		);

		if ( ! $app ) {
			wp_die( 'Application not found.' );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $app['company_name'] ); ?> - <?php esc_html_e( 'Application', 'khm-membership' ); ?></h1>

			<div class="khm-app-detail">
				<div class="khm-app-status">
					<span class="khm-badge khm-badge-<?php echo esc_attr( $app['status'] ); ?>">
						<?php echo esc_html( ucfirst( $app['status'] ) ); ?>
					</span>
				</div>

				<div class="khm-app-info">
					<h3><?php esc_html_e( 'Contact Information', 'khm-membership' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Company Name', 'khm-membership' ); ?></th>
							<td><?php echo esc_html( $app['company_name'] ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Contact Name', 'khm-membership' ); ?></th>
							<td><?php echo esc_html( $app['contact_name'] ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Email', 'khm-membership' ); ?></th>
							<td><a href="mailto:<?php echo esc_attr( $app['contact_email'] ); ?>"><?php echo esc_html( $app['contact_email'] ); ?></a></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Phone', 'khm-membership' ); ?></th>
							<td><?php echo $app['contact_phone'] ? esc_html( $app['contact_phone'] ) : '—'; ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Website', 'khm-membership' ); ?></th>
							<td><?php echo $app['company_url'] ? '<a href="' . esc_url( $app['company_url'] ) . '" target="_blank">' . esc_html( $app['company_url'] ) . '</a>' : '—'; ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Sector', 'khm-membership' ); ?></th>
							<td><?php echo esc_html( $app['sector'] ); ?></td>
						</tr>
					</table>
				</div>

				<div class="khm-app-details">
					<h3><?php esc_html_e( 'Application Details', 'khm-membership' ); ?></h3>
					<div class="khm-field">
						<h4><?php esc_html_e( 'Use Case / Goals', 'khm-membership' ); ?></h4>
						<p><?php echo wp_kses_post( nl2br( $app['use_case'] ) ); ?></p>
					</div>
					<?php if ( $app['message'] ) : ?>
						<div class="khm-field">
							<h4><?php esc_html_e( 'Additional Message', 'khm-membership' ); ?></h4>
							<p><?php echo wp_kses_post( nl2br( $app['message'] ) ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<div class="khm-app-metadata">
					<h4><?php esc_html_e( 'Metadata', 'khm-membership' ); ?></h4>
					<p><strong><?php esc_html_e( 'Submitted:', 'khm-membership' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $app['created_at'] ) ) ); ?></p>
					<?php if ( $app['reviewed_at'] ) : ?>
						<p><strong><?php esc_html_e( 'Reviewed:', 'khm-membership' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $app['reviewed_at'] ) ) ); ?></p>
						<?php if ( $app['reviewed_by'] ) : ?>
							<p><strong><?php esc_html_e( 'Reviewed by:', 'khm-membership' ); ?></strong> <?php echo esc_html( get_user_by( 'id', $app['reviewed_by'] )->display_name ?? '—' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<?php if ( 'pending' === $app['status'] ) : ?>
					<div class="khm-app-actions">
						<h3><?php esc_html_e( 'Review Actions', 'khm-membership' ); ?></h3>

						<!-- Approve form -->
						<form method="post" action="admin-post.php" class="khm-inline-form">
							<?php wp_nonce_field( 'khm_app_approve' ); ?>
							<input type="hidden" name="action" value="khm_app_approve">
							<input type="hidden" name="app_id" value="<?php echo intval( $app_id ); ?>">
							<button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e( 'Approve this application?', 'khm-membership' ); ?>');">
								<?php esc_html_e( 'Approve', 'khm-membership' ); ?>
							</button>
						</form>

						<!-- Reject form -->
						<form method="post" action="admin-post.php" class="khm-inline-form khm-reject-form" style="display: none;">
							<?php wp_nonce_field( 'khm_app_reject' ); ?>
							<input type="hidden" name="action" value="khm_app_reject">
							<input type="hidden" name="app_id" value="<?php echo intval( $app_id ); ?>">
							<div>
								<label><?php esc_html_e( 'Rejection Reason:', 'khm-membership' ); ?></label>
								<textarea name="rejection_reason" rows="3" placeholder="<?php esc_attr_e( 'Explain why this application is being rejected...', 'khm-membership' ); ?>" required></textarea>
							</div>
							<button type="submit" class="button button-secondary">
								<?php esc_html_e( 'Confirm Rejection', 'khm-membership' ); ?>
							</button>
							<button type="button" class="button" onclick="document.querySelector('.khm-reject-form').style.display='none'; document.querySelector('.khm-reject-toggle').style.display='inline-block';">
								<?php esc_html_e( 'Cancel', 'khm-membership' ); ?>
							</button>
						</form>
						<button type="button" class="button khm-reject-toggle" onclick="document.querySelector('.khm-reject-form').style.display='block'; this.style.display='none';">
							<?php esc_html_e( 'Reject', 'khm-membership' ); ?>
						</button>
					</div>
				<?php endif; ?>

				<div class="khm-app-back">
					<a href="admin.php?page=khm-sponsorship-applications" class="button">
						<?php esc_html_e( '← Back to Applications', 'khm-membership' ); ?>
					</a>
				</div>
			</div>

			<style>
				.khm-app-detail {
					background: #fff;
					padding: 20px;
					border-radius: 5px;
					max-width: 900px;
				}
				.khm-app-status {
					margin-bottom: 20px;
				}
				.khm-app-info, .khm-app-details, .khm-app-metadata, .khm-app-actions, .khm-app-back {
					margin: 30px 0;
				}
				.khm-app-details .khm-field {
					margin: 15px 0;
				}
				.khm-badge {
					display: inline-block;
					padding: 4px 8px;
					border-radius: 3px;
					font-size: 12px;
					font-weight: bold;
				}
				.khm-badge-pending {
					background-color: #fff8e5;
					color: #856404;
				}
				.khm-badge-approved {
					background-color: #d4edda;
					color: #155724;
				}
				.khm-badge-rejected {
					background-color: #f8d7da;
					color: #721c24;
				}
				.khm-inline-form {
					display: inline-block;
					margin-right: 10px;
				}
				.khm-reject-form textarea {
					width: 100%;
					margin: 10px 0;
				}
				.khm-app-back {
					border-top: 1px solid #eee;
					padding-top: 20px;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Handle application approval
	 */
	public function handle_approve(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'khm_app_approve' ) ) {
			wp_die( 'Security check failed' );
		}

		$app_id = (int) ( $_POST['app_id'] ?? 0 );
		if ( ! $app_id ) {
			wp_die( 'Invalid application ID' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_applications';

		$app = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $app_id ),
			ARRAY_A
		);

		if ( ! $app ) {
			wp_die( 'Application not found' );
		}

		// Create sponsor account
		$sponsor_data = array(
			'name'         => $app['company_name'],
			'website'      => $app['company_url'] ?: '',
			'sector'       => $app['sector'],
			'contact_name' => $app['contact_name'],
			'contact_email' => $app['contact_email'],
			'contact_phone' => $app['contact_phone'] ?: '',
			'created_at'   => current_time( 'mysql' ),
		);

		$sponsor_table = SponsorMigration::sponsors_table_name();
		$sponsor_result = $wpdb->insert(
			$sponsor_table,
			$sponsor_data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $sponsor_result ) {
			error_log( '[KHM Sponsors] Failed to create sponsor: ' . $wpdb->last_error );
			wp_die( 'Failed to create sponsor account' );
		}

		$sponsor_id = $wpdb->insert_id;

		// Update application
		$wpdb->update(
			$table,
			array(
				'status'      => 'approved',
				'sponsor_id'  => $sponsor_id,
				'reviewed_at' => current_time( 'mysql' ),
				'reviewed_by' => get_current_user_id(),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $app_id ),
			array( '%s', '%d', '%s', '%d', '%s' )
		);

		// Send approval email
		$this->send_approval_email( $app, $sponsor_id );

		wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-applications&action=view&id=' . $app_id . '&message=approved' ) );
		exit;
	}

	/**
	 * Handle application rejection
	 */
	public function handle_reject(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'khm_app_reject' ) ) {
			wp_die( 'Security check failed' );
		}

		$app_id = (int) ( $_POST['app_id'] ?? 0 );
		$reason = sanitize_textarea_field( $_POST['rejection_reason'] ?? '' );

		if ( ! $app_id ) {
			wp_die( 'Invalid application ID' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_applications';

		$app = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $app_id ),
			ARRAY_A
		);

		if ( ! $app ) {
			wp_die( 'Application not found' );
		}

		// Update application
		$wpdb->update(
			$table,
			array(
				'status'           => 'rejected',
				'rejection_reason' => $reason,
				'reviewed_at'      => current_time( 'mysql' ),
				'reviewed_by'      => get_current_user_id(),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $app_id ),
			array( '%s', '%s', '%s', '%d', '%s' )
		);

		// Send rejection email
		$this->send_rejection_email( $app, $reason );

		wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-applications&action=view&id=' . $app_id . '&message=rejected' ) );
		exit;
	}

	/**
	 * Send approval email
	 */
	private function send_approval_email( $app, $sponsor_id ): void {
		$to      = $app['contact_email'];
		$subject = sprintf( __( 'Your Sponsorship Application Has Been Approved – %s', 'khm-membership' ), get_bloginfo( 'name' ) );
		$body    = sprintf(
			__( "Hi %s,\n\nGreat news! Your sponsorship application for %s has been approved.\n\nYour sponsor account is now active. You can log in and access your sponsor dashboard at: %s\n\nIf you have any questions, please don't hesitate to contact us.\n\nBest regards,\nThe %s Team",
				'khm-membership' ),
			$app['contact_name'],
			$app['company_name'],
			admin_url(),
			get_bloginfo( 'name' )
		);

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Send rejection email
	 */
	private function send_rejection_email( $app, $reason ): void {
		$to      = $app['contact_email'];
		$subject = sprintf( __( 'Sponsorship Application Status – %s', 'khm-membership' ), get_bloginfo( 'name' ) );
		$body    = sprintf(
			__( "Hi %s,\n\nThank you for your interest in becoming a sponsor with %s.\n\nUnfortunately, we are unable to move forward with your application at this time.\n\nReason: %s\n\nIf you have any questions or would like to discuss further opportunities, please feel free to reach out.\n\nBest regards,\nThe %s Team",
				'khm-membership' ),
			$app['contact_name'],
			get_bloginfo( 'name' ),
			$reason,
			get_bloginfo( 'name' )
		);

		wp_mail( $to, $subject, $body );
	}
}
