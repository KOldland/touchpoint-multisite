<?php
/**
 * Sponsor Application Form Shortcode
 *
 * Provides the [khm_sponsor_apply] shortcode which renders a form for
 * potential sponsors to submit applications.
 *
 * @package KHM\Sponsors
 */

namespace KHM\Sponsors;

use KHM\Migrations\CreateSponsorApplicationsTable;

defined( 'ABSPATH' ) || exit;

class SponsorApplicationShortcode {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_shortcode( 'khm_sponsor_apply', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_nopriv_khm_sponsor_apply', array( $this, 'handle_application' ) );
		add_action( 'wp_ajax_khm_sponsor_apply', array( $this, 'handle_application' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_assets(): void {
		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'khm_sponsor_apply' ) ) {
			return;
		}

		wp_enqueue_style(
			'khm-sponsor-apply',
			plugin_dir_url( dirname( __DIR__ ) . '/khm-membership.php' ) . 'assets/css/sponsor-apply.css',
			array(),
			filemtime( plugin_dir_path( dirname( __DIR__ ) . '/khm-membership.php' ) . 'assets/css/sponsor-apply.css' )
		);

		wp_enqueue_script(
			'khm-sponsor-apply',
			plugin_dir_url( dirname( __DIR__ ) . '/khm-membership.php' ) . 'assets/js/sponsor-apply.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( dirname( __DIR__ ) . '/khm-membership.php' ) . 'assets/js/sponsor-apply.js' ),
			true
		);

		wp_localize_script( 'khm-sponsor-apply', 'khm_sponsor_apply', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'khm_sponsor_apply_nonce' ),
		) );
	}

	/**
	 * Render the application form shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_shortcode( $atts = array() ): string {
		// Ensure table exists
		if ( ! SponsorMigration::table_exists() ) {
			CreateSponsorApplicationsTable::create_tables();
		}

		ob_start();
		?>
		<div class="khm-sponsor-apply-container">
			<div class="khm-sponsor-apply-form">
				<h2><?php esc_html_e( 'Become a Sponsor', 'khm-membership' ); ?></h2>
				<p><?php esc_html_e( 'Fill out the form below to apply for sponsorship opportunities with our platform.', 'khm-membership' ); ?></p>

				<form id="khm-sponsor-apply-form" class="khm-form">
					<?php wp_nonce_field( 'khm_sponsor_apply_nonce', 'khm_sponsor_apply_nonce' ); ?>

					<div class="form-group">
						<label for="company_name"><?php esc_html_e( 'Company Name', 'khm-membership' ); ?> <span class="required">*</span></label>
						<input 
							type="text" 
							id="company_name" 
							name="company_name" 
							class="form-control" 
							required 
							placeholder="Your company name"
						>
					</div>

					<div class="form-row">
						<div class="form-group form-col-6">
							<label for="contact_name"><?php esc_html_e( 'Contact Name', 'khm-membership' ); ?> <span class="required">*</span></label>
							<input 
								type="text" 
								id="contact_name" 
								name="contact_name" 
								class="form-control" 
								required 
								placeholder="Your name"
							>
						</div>
						<div class="form-group form-col-6">
							<label for="contact_email"><?php esc_html_e( 'Email', 'khm-membership' ); ?> <span class="required">*</span></label>
							<input 
								type="email" 
								id="contact_email" 
								name="contact_email" 
								class="form-control" 
								required 
								placeholder="your@email.com"
							>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group form-col-6">
							<label for="contact_phone"><?php esc_html_e( 'Phone', 'khm-membership' ); ?></label>
							<input 
								type="tel" 
								id="contact_phone" 
								name="contact_phone" 
								class="form-control" 
								placeholder="+1 (555) 123-4567"
							>
						</div>
						<div class="form-group form-col-6">
							<label for="sector"><?php esc_html_e( 'Industry/Sector', 'khm-membership' ); ?> <span class="required">*</span></label>
							<select id="sector" name="sector" class="form-control" required>
								<option value=""><?php esc_html_e( '-- Select --', 'khm-membership' ); ?></option>
								<option value="Aerospace &amp; Defense"><?php esc_html_e( 'Aerospace &amp; Defense', 'khm-membership' ); ?></option>
								<option value="Energy"><?php esc_html_e( 'Energy', 'khm-membership' ); ?></option>
								<option value="Built Environment"><?php esc_html_e( 'Built Environment', 'khm-membership' ); ?></option>
								<option value="Industrial"><?php esc_html_e( 'Industrial', 'khm-membership' ); ?></option>
								<option value="Field Service"><?php esc_html_e( 'Field Service', 'khm-membership' ); ?></option>
								<option value="Spare Parts &amp; Aftermarket"><?php esc_html_e( 'Spare Parts &amp; Aftermarket', 'khm-membership' ); ?></option>
								<option value="Other"><?php esc_html_e( 'Other', 'khm-membership' ); ?></option>
							</select>
						</div>
					</div>

					<div class="form-group">
						<label for="company_url"><?php esc_html_e( 'Company Website', 'khm-membership' ); ?></label>
						<input 
							type="url" 
							id="company_url" 
							name="company_url" 
							class="form-control" 
							placeholder="https://example.com"
						>
					</div>

					<div class="form-group">
						<label for="use_case"><?php esc_html_e( 'Use Case / Goals', 'khm-membership' ); ?> <span class="required">*</span></label>
						<textarea 
							id="use_case" 
							name="use_case" 
							class="form-control" 
							rows="5" 
							required 
							placeholder="Tell us about your sponsorship goals and how you'd like to engage with our audience..."
						></textarea>
					</div>

					<div class="form-group">
						<label for="message"><?php esc_html_e( 'Additional Message', 'khm-membership' ); ?></label>
						<textarea 
							id="message" 
							name="message" 
							class="form-control" 
							rows="3" 
							placeholder="Anything else we should know?"
						></textarea>
					</div>

					<div class="form-group checkbox">
						<label>
							<input type="checkbox" name="accept_terms" required>
							<?php 
							echo wp_kses_post(
								sprintf(
									__( 'I agree to the <a href="%s" target="_blank">terms and conditions</a>', 'khm-membership' ),
									esc_url( home_url( '/terms/' ) )
								)
							); 
							?>
						</label>
					</div>

					<button type="submit" class="btn btn-primary" id="khm-sponsor-apply-submit">
						<?php esc_html_e( 'Submit Application', 'khm-membership' ); ?>
					</button>

					<div id="khm-sponsor-apply-message" class="khm-message" style="display: none;"></div>
				</form>
			</div>

			<div id="khm-sponsor-apply-success" class="khm-sponsor-apply-success" style="display: none;">
				<div class="success-card">
					<h3><?php esc_html_e( 'Application Received!', 'khm-membership' ); ?></h3>
					<p><?php esc_html_e( 'Thank you for your interest in becoming a sponsor. We\'ve received your application and our team will review it shortly.', 'khm-membership' ); ?></p>
					<p><?php esc_html_e( 'You\'ll receive an email update within 2-3 business days.', 'khm-membership' ); ?></p>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle application submission via AJAX
	 */
	public function handle_application(): void {
		// Check nonce
		$nonce = $_POST['khm_sponsor_apply_nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'khm_sponsor_apply_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
		}

		// Validate required fields
		$required = array( 'company_name', 'contact_name', 'contact_email', 'sector', 'use_case' );
		foreach ( $required as $field ) {
			if ( empty( $_POST[ $field ] ) ) {
				wp_send_json_error( array( 'message' => "Field '{$field}' is required." ) );
			}
		}

		// Sanitize input
		$data = array(
			'company_name'   => sanitize_text_field( $_POST['company_name'] ),
			'contact_name'   => sanitize_text_field( $_POST['contact_name'] ),
			'contact_email'  => sanitize_email( $_POST['contact_email'] ),
			'contact_phone'  => sanitize_text_field( $_POST['contact_phone'] ?? '' ),
			'sector'         => sanitize_text_field( $_POST['sector'] ),
			'company_url'    => esc_url_raw( $_POST['company_url'] ?? '' ),
			'use_case'       => sanitize_textarea_field( $_POST['use_case'] ),
			'message'        => sanitize_textarea_field( $_POST['message'] ?? '' ),
		);

		// Validate email
		if ( ! is_email( $data['contact_email'] ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid email address.' ) );
		}

		// Check for duplicate application
		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_applications';
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE contact_email = %s AND company_name = %s AND status = 'pending' LIMIT 1",
			$data['contact_email'],
			$data['company_name']
		) );

		if ( $existing ) {
			wp_send_json_error( array( 'message' => 'Your application is already under review. Please check your email for updates.' ) );
		}

		// Insert into database
		$result = $wpdb->insert(
			$table,
			array(
				'company_name'  => $data['company_name'],
				'contact_name'  => $data['contact_name'],
				'contact_email' => $data['contact_email'],
				'contact_phone' => $data['contact_phone'],
				'sector'        => $data['sector'],
				'company_url'   => $data['company_url'],
				'use_case'      => $data['use_case'],
				'message'       => $data['message'],
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			error_log( '[KHM Sponsors] Failed to insert sponsor application: ' . $wpdb->last_error );
			wp_send_json_error( array( 'message' => 'Failed to submit application. Please try again.' ) );
		}

		$app_id = $wpdb->insert_id;

		// Send emails
		$this->send_applicant_confirmation( $data, $app_id );
		$this->send_admin_notification( $data, $app_id );

		wp_send_json_success( array( 'message' => 'Application submitted successfully!' ) );
	}

	/**
	 * Send confirmation email to applicant
	 */
	private function send_applicant_confirmation( $data, $app_id ): void {
		$to      = $data['contact_email'];
		$subject = sprintf( __( 'Sponsorship Application Received – %s', 'khm-membership' ), get_bloginfo( 'name' ) );
		$body    = sprintf(
			__( "Hi %s,\n\nThank you for your interest in becoming a sponsor with %s.\n\nWe've received your application and our team will review it shortly. You can expect to hear from us within 2-3 business days.\n\nBest regards,\nThe %s Team", 'khm-membership' ),
			$data['contact_name'],
			get_bloginfo( 'name' ),
			get_bloginfo( 'name' )
		);

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Send notification email to admin
	 */
	private function send_admin_notification( $data, $app_id ): void {
		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf( __( 'New Sponsorship Application: %s', 'khm-membership' ), $data['company_name'] );
		$body        = sprintf(
			__( "New sponsor application received:\n\nCompany: %s\nContact: %s\nEmail: %s\nPhone: %s\nSector: %s\nWebsite: %s\n\nUse Case:\n%s\n\nMessage:\n%s\n\nReview this application: %s",
				'khm-membership' ),
			$data['company_name'],
			$data['contact_name'],
			$data['contact_email'],
			$data['contact_phone'] ?: '(Not provided)',
			$data['sector'],
			$data['company_url'] ?: '(Not provided)',
			$data['use_case'],
			$data['message'] ?: '(None)',
			admin_url( 'admin.php?page=khm-sponsorship-applications&action=view&id=' . $app_id )
		);

		wp_mail( $admin_email, $subject, $body );
	}
}
