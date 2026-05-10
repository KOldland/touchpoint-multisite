<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * ConnectEngagedSettingsPage
 *
 * Admin settings page for the Connect Engaged tier (dual-pathway pricing).
 * Controls Option 2 (commission-based pricing) visibility and related labels.
 *
 * Settings are stored as network options so the same toggle applies
 * across all sites in the multisite install.
 */
class ConnectEngagedSettingsPage {

	/** Network option key for Option 2 enabled flag. */
	public const OPTION_TWO_ENABLED = 'khm_engaged_option_two_enabled';

	/** Network option key for Option 1 label. */
	public const OPTION_ONE_LABEL = 'khm_engaged_option_one_label';

	/** Network option key for Option 2 label. */
	public const OPTION_TWO_LABEL = 'khm_engaged_option_two_label';

	/** Network option key for Option 1 price description. */
	public const OPTION_ONE_PRICE_DESC = 'khm_engaged_option_one_price_desc';

	/** Network option key for Option 2 price description. */
	public const OPTION_TWO_PRICE_DESC = 'khm_engaged_option_two_price_desc';

	// -------------------------------------------------------------------------
	// Defaults
	// -------------------------------------------------------------------------

	public const DEFAULT_OPTION_ONE_LABEL      = 'Option 1: Fixed Fee';
	public const DEFAULT_OPTION_TWO_LABEL      = 'Option 2: Success Fee';
	public const DEFAULT_OPTION_ONE_PRICE_DESC = '£1,500 one-off introduction fee';
	public const DEFAULT_OPTION_TWO_PRICE_DESC = '£375 listing fee + 15% commission on first-year contract value';

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_post_khm_connect_engaged_settings_save', [ $this, 'handle_save' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'khm-membership',
			__( 'Connect Engaged Settings', 'khm-membership' ),
			__( 'Engaged Settings', 'khm-membership' ),
			'manage_options',
			'khm-connect-engaged-settings',
			[ $this, 'render_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Connect Engaged settings.', 'khm-membership' ) );
		}

		$notice            = isset( $_GET['connect_notice'] ) ? sanitize_key( (string) $_GET['connect_notice'] ) : '';
		$option_two_enabled = (bool) get_site_option( self::OPTION_TWO_ENABLED, true );
		$option_one_label   = (string) get_site_option( self::OPTION_ONE_LABEL, self::DEFAULT_OPTION_ONE_LABEL );
		$option_two_label   = (string) get_site_option( self::OPTION_TWO_LABEL, self::DEFAULT_OPTION_TWO_LABEL );
		$option_one_desc    = (string) get_site_option( self::OPTION_ONE_PRICE_DESC, self::DEFAULT_OPTION_ONE_PRICE_DESC );
		$option_two_desc    = (string) get_site_option( self::OPTION_TWO_PRICE_DESC, self::DEFAULT_OPTION_TWO_PRICE_DESC );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connect Engaged Settings', 'khm-membership' ); ?></h1>

			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'khm-membership' ); ?></p></div>
			<?php endif; ?>

			<p class="description" style="margin-bottom:20px;">
				<?php esc_html_e( 'Configure the dual-pathway pricing options shown in the sponsor Connect portal. Changes take effect immediately across all sponsor accounts.', 'khm-membership' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'khm_connect_engaged_settings_save', 'khm_connect_engaged_settings_nonce' ); ?>
				<input type="hidden" name="action" value="khm_connect_engaged_settings_save" />

				<h2><?php esc_html_e( 'Option 2 Visibility', 'khm-membership' ); ?></h2>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'When disabled, sponsors only see Option 1 (fixed fee) in both Active Matches and RFQ Requests sections. Option 2 (commission-based) is hidden completely.', 'khm-membership' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Option 2', 'khm-membership' ); ?></th>
						<td>
							<label for="khm_option_two_enabled">
								<input
									type="checkbox"
									id="khm_option_two_enabled"
									name="option_two_enabled"
									value="1"
									<?php checked( $option_two_enabled ); ?>
								/>
								<?php esc_html_e( 'Show commission-based pricing option (Option 2) to sponsors', 'khm-membership' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Uncheck this to run a fixed-fee-only launch or to temporarily disable Option 2 during testing.', 'khm-membership' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 style="margin-top:24px;"><?php esc_html_e( 'Option Labels', 'khm-membership' ); ?></h2>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'These labels appear on the "Request Option 1" and "Request Option 2" buttons in the sponsor portal and on intro thread badges.', 'khm-membership' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="khm_option_one_label"><?php esc_html_e( 'Option 1 Button Label', 'khm-membership' ); ?></label></th>
						<td>
							<input
								class="regular-text"
								type="text"
								id="khm_option_one_label"
								name="option_one_label"
								value="<?php echo esc_attr( $option_one_label ); ?>"
								placeholder="<?php echo esc_attr( self::DEFAULT_OPTION_ONE_LABEL ); ?>"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_option_two_label"><?php esc_html_e( 'Option 2 Button Label', 'khm-membership' ); ?></label></th>
						<td>
							<input
								class="regular-text"
								type="text"
								id="khm_option_two_label"
								name="option_two_label"
								value="<?php echo esc_attr( $option_two_label ); ?>"
								placeholder="<?php echo esc_attr( self::DEFAULT_OPTION_TWO_LABEL ); ?>"
							/>
						</td>
					</tr>
				</table>

				<h2 style="margin-top:24px;"><?php esc_html_e( 'Price Descriptions', 'khm-membership' ); ?></h2>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'These descriptions appear below each option button so sponsors understand the cost structure before committing.', 'khm-membership' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="khm_option_one_price_desc"><?php esc_html_e( 'Option 1 Price Description', 'khm-membership' ); ?></label></th>
						<td>
							<input
								class="large-text"
								type="text"
								id="khm_option_one_price_desc"
								name="option_one_price_desc"
								value="<?php echo esc_attr( $option_one_desc ); ?>"
								placeholder="<?php echo esc_attr( self::DEFAULT_OPTION_ONE_PRICE_DESC ); ?>"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_option_two_price_desc"><?php esc_html_e( 'Option 2 Price Description', 'khm-membership' ); ?></label></th>
						<td>
							<input
								class="large-text"
								type="text"
								id="khm_option_two_price_desc"
								name="option_two_price_desc"
								value="<?php echo esc_attr( $option_two_desc ); ?>"
								placeholder="<?php echo esc_attr( self::DEFAULT_OPTION_TWO_PRICE_DESC ); ?>"
							/>
						</td>
					</tr>
				</table>

				<?php $this->render_current_config_preview( $option_two_enabled, $option_one_label, $option_two_label, $option_one_desc, $option_two_desc ); ?>

				<?php submit_button( __( 'Save Engaged Settings', 'khm-membership' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a live preview card showing the current sponsor-facing output.
	 */
	private function render_current_config_preview( bool $enabled, string $label1, string $label2, string $desc1, string $desc2 ): void {
		?>
		<h2 style="margin-top:24px;"><?php esc_html_e( 'Sponsor-Facing Preview', 'khm-membership' ); ?></h2>
		<p class="description" style="margin-bottom:12px;"><?php esc_html_e( 'This is how the pricing options will appear to sponsors with a saved opportunity in the Engaged tier.', 'khm-membership' ); ?></p>
		<div style="border:1px solid #ddd;border-radius:6px;padding:20px;max-width:480px;background:#fff;">
			<div style="margin-bottom:12px;">
				<button type="button" class="button button-primary" style="width:100%;margin-bottom:6px;"><?php echo esc_html( $label1 ); ?></button>
				<p style="margin:0;font-size:12px;color:#666;"><?php echo esc_html( $desc1 ); ?></p>
			</div>
			<?php if ( $enabled ) : ?>
				<div>
					<button type="button" class="button" style="width:100%;margin-bottom:6px;"><?php echo esc_html( $label2 ); ?></button>
					<p style="margin:0;font-size:12px;color:#666;"><?php echo esc_html( $desc2 ); ?></p>
				</div>
			<?php else : ?>
				<div style="background:#f9f9f9;border:1px dashed #ccc;border-radius:4px;padding:10px;color:#888;font-size:13px;text-align:center;">
					<?php esc_html_e( 'Option 2 is currently disabled — not visible to sponsors.', 'khm-membership' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Form handler
	// -------------------------------------------------------------------------

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
		}

		check_admin_referer( 'khm_connect_engaged_settings_save', 'khm_connect_engaged_settings_nonce' );

		// Checkbox: present = true, absent = false.
		$option_two_enabled = isset( $_POST['option_two_enabled'] ) && '1' === $_POST['option_two_enabled'];

		update_site_option( self::OPTION_TWO_ENABLED, $option_two_enabled );

		update_site_option(
			self::OPTION_ONE_LABEL,
			sanitize_text_field( (string) ( $_POST['option_one_label'] ?? self::DEFAULT_OPTION_ONE_LABEL ) )
		);

		update_site_option(
			self::OPTION_TWO_LABEL,
			sanitize_text_field( (string) ( $_POST['option_two_label'] ?? self::DEFAULT_OPTION_TWO_LABEL ) )
		);

		update_site_option(
			self::OPTION_ONE_PRICE_DESC,
			sanitize_text_field( (string) ( $_POST['option_one_price_desc'] ?? self::DEFAULT_OPTION_ONE_PRICE_DESC ) )
		);

		update_site_option(
			self::OPTION_TWO_PRICE_DESC,
			sanitize_text_field( (string) ( $_POST['option_two_price_desc'] ?? self::DEFAULT_OPTION_TWO_PRICE_DESC ) )
		);

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'khm-connect-engaged-settings', 'connect_notice' => 'saved' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Static helpers for reading settings elsewhere in the plugin
	// -------------------------------------------------------------------------

	/**
	 * Whether Option 2 is enabled. Defaults to true (on by default).
	 */
	public static function is_option_two_enabled(): bool {
		return (bool) get_site_option( self::OPTION_TWO_ENABLED, true );
	}

	/**
	 * Returns the full settings array for JS injection.
	 *
	 * @return array{
	 *   engagedOptionTwoEnabled: bool,
	 *   optionOneLabel: string,
	 *   optionTwoLabel: string,
	 *   optionOnePriceDesc: string,
	 *   optionTwoPriceDesc: string,
	 * }
	 */
	public static function get_js_config(): array {
		return [
			'engagedOptionTwoEnabled' => self::is_option_two_enabled(),
			'optionOneLabel'          => (string) get_site_option( self::OPTION_ONE_LABEL, self::DEFAULT_OPTION_ONE_LABEL ),
			'optionTwoLabel'          => (string) get_site_option( self::OPTION_TWO_LABEL, self::DEFAULT_OPTION_TWO_LABEL ),
			'optionOnePriceDesc'      => (string) get_site_option( self::OPTION_ONE_PRICE_DESC, self::DEFAULT_OPTION_ONE_PRICE_DESC ),
			'optionTwoPriceDesc'      => (string) get_site_option( self::OPTION_TWO_PRICE_DESC, self::DEFAULT_OPTION_TWO_PRICE_DESC ),
		];
	}
}
