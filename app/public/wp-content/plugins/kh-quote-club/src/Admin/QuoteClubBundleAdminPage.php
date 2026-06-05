<?php
/**
 * Quote Club Credit Bundles — Admin Page
 *
 * Provides a WP admin interface under the existing KHM Membership menu for
 * creating and managing purchasable credit bundle definitions.
 *
 * @package KHM\Admin
 */

namespace QuoteClub\Admin;

use QuoteClub\Services\QuoteClubCreditBundleService;

class QuoteClubBundleAdminPage {

	private QuoteClubCreditBundleService $bundles;

	public function __construct( QuoteClubCreditBundleService $bundles ) {
		$this->bundles = $bundles;
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_post_khm_qc_bundle_save', [ $this, 'handle_save' ] );
		add_action( 'admin_post_khm_qc_bundle_toggle', [ $this, 'handle_toggle' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'kh-quote-club',
			__( 'Quote Club Bundles', 'kh-quote-club' ),
			__( 'QC Bundles', 'kh-quote-club' ),
			'manage_options',
			'khm-qc-bundles',
			[ $this, 'render_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'kh-quote-club' ) );
		}

		$all_bundles = $this->bundles->list_bundles( false );
		$edit_id     = isset( $_GET['edit_id'] ) ? (int) $_GET['edit_id'] : 0;
		$edit_bundle = $edit_id ? $this->bundles->get_bundle( $edit_id ) : null;

		$notice = isset( $_GET['khm_qc_notice'] ) ? sanitize_text_field( $_GET['khm_qc_notice'] ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Quote Club Credit Bundles', 'kh-quote-club' ); ?></h1>

			<?php if ( $notice === 'saved' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bundle saved.', 'kh-quote-club' ); ?></p></div>
			<?php elseif ( $notice === 'toggled' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bundle status updated.', 'kh-quote-club' ); ?></p></div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:2fr 1fr;gap:2rem;align-items:start;">

				<!-- Bundle list -->
				<div>
					<h2><?php esc_html_e( 'All Bundles', 'kh-quote-club' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'kh-quote-club' ); ?></th>
								<th><?php esc_html_e( 'Ed. Credits', 'kh-quote-club' ); ?></th>
								<th><?php esc_html_e( 'PR Credits', 'kh-quote-club' ); ?></th>
								<th><?php esc_html_e( 'Price', 'kh-quote-club' ); ?></th>
								<th><?php esc_html_e( 'Stripe Price ID', 'kh-quote-club' ); ?></th>
								<th><?php esc_html_e( 'Active', 'kh-quote-club' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'kh-quote-club' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $all_bundles ) ) : ?>
								<tr>
									<td colspan="7"><?php esc_html_e( 'No bundles defined yet.', 'kh-quote-club' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $all_bundles as $bundle ) : ?>
									<tr>
										<td><?php echo esc_html( $bundle->name ); ?></td>
										<td><?php echo (int) $bundle->editorial_credits; ?></td>
										<td><?php echo (int) $bundle->press_release_credits; ?></td>
										<td>$<?php echo number_format( (int) $bundle->price_cents / 100, 2 ); ?></td>
										<td><code><?php echo esc_html( (string) $bundle->stripe_price_id ); ?></code></td>
										<td><?php echo $bundle->active ? '<span style="color:green">&#10003;</span>' : '<span style="color:#999">&#8212;</span>'; ?></td>
										<td>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-qc-bundles&edit_id=' . (int) $bundle->id ) ); ?>"><?php esc_html_e( 'Edit', 'kh-quote-club' ); ?></a>
											&nbsp;|&nbsp;
											<?php
											$toggle_nonce = wp_create_nonce( 'khm_qc_bundle_toggle_' . (int) $bundle->id );
											$toggle_label = $bundle->active ? __( 'Deactivate', 'kh-quote-club' ) : __( 'Activate', 'kh-quote-club' );
											?>
											<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=khm_qc_bundle_toggle&bundle_id=' . (int) $bundle->id . '&_wpnonce=' . $toggle_nonce ) ); ?>"><?php echo esc_html( $toggle_label ); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<!-- Create / Edit form -->
				<div>
					<h2><?php echo $edit_bundle ? esc_html__( 'Edit Bundle', 'kh-quote-club' ) : esc_html__( 'New Bundle', 'kh-quote-club' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'khm_qc_bundle_save' ); ?>
						<input type="hidden" name="action" value="khm_qc_bundle_save">
						<?php if ( $edit_bundle ) : ?>
							<input type="hidden" name="bundle_id" value="<?php echo (int) $edit_bundle->id; ?>">
						<?php endif; ?>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="qcb_name"><?php esc_html_e( 'Bundle Name', 'kh-quote-club' ); ?></label></th>
								<td><input type="text" id="qcb_name" name="qcb_name" class="regular-text" required value="<?php echo esc_attr( (string) ( $edit_bundle->name ?? '' ) ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="qcb_desc"><?php esc_html_e( 'Description', 'kh-quote-club' ); ?></label></th>
								<td><textarea id="qcb_desc" name="qcb_desc" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $edit_bundle->description ?? '' ) ); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="qcb_ed"><?php esc_html_e( 'Editorial Credits', 'kh-quote-club' ); ?></label></th>
								<td>
									<input type="number" id="qcb_ed" name="qcb_ed" min="0" class="small-text" value="<?php echo (int) ( $edit_bundle->editorial_credits ?? 0 ); ?>">
									<p class="description"><?php esc_html_e( 'Credits added to the sponsor\'s editorial balance (1 credit = 120 words of commentary).', 'kh-quote-club' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="qcb_pr"><?php esc_html_e( 'Press Release Credits', 'kh-quote-club' ); ?></label></th>
								<td><input type="number" id="qcb_pr" name="qcb_pr" min="0" class="small-text" value="<?php echo (int) ( $edit_bundle->press_release_credits ?? 0 ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="qcb_price"><?php esc_html_e( 'Price (USD)', 'kh-quote-club' ); ?></label></th>
								<td>
									<input type="number" id="qcb_price" name="qcb_price" min="0" step="0.01" class="small-text" value="<?php echo number_format( (int) ( $edit_bundle->price_cents ?? 0 ) / 100, 2 ); ?>">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="qcb_stripe"><?php esc_html_e( 'Stripe Price ID', 'kh-quote-club' ); ?></label></th>
								<td>
									<input type="text" id="qcb_stripe" name="qcb_stripe" class="regular-text" placeholder="price_…" value="<?php echo esc_attr( (string) ( $edit_bundle->stripe_price_id ?? '' ) ); ?>">
									<p class="description"><?php esc_html_e( 'Leave blank to use a one-time Stripe checkout price. When set, this price ID is used for the Stripe checkout session.', 'kh-quote-club' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Active', 'kh-quote-club' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="qcb_active" value="1" <?php checked( isset( $edit_bundle ) ? (int) $edit_bundle->active : 1, 1 ); ?>>
										<?php esc_html_e( 'Make this bundle available for purchase', 'kh-quote-club' ); ?>
									</label>
								</td>
							</tr>
						</table>

						<?php submit_button( $edit_bundle ? __( 'Update Bundle', 'kh-quote-club' ) : __( 'Create Bundle', 'kh-quote-club' ) ); ?>
					</form>
				</div>

			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Form handlers
	// -------------------------------------------------------------------------

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'kh-quote-club' ) );
		}

		check_admin_referer( 'khm_qc_bundle_save' );

		$price_dollars = (float) ( $_POST['qcb_price'] ?? 0 );

		$data = [
			'name'                  => sanitize_text_field( $_POST['qcb_name'] ?? '' ),
			'description'           => sanitize_textarea_field( $_POST['qcb_desc'] ?? '' ),
			'editorial_credits'     => max( 0, (int) ( $_POST['qcb_ed'] ?? 0 ) ),
			'press_release_credits' => max( 0, (int) ( $_POST['qcb_pr'] ?? 0 ) ),
			'price_cents'           => (int) round( $price_dollars * 100 ),
			'stripe_price_id'       => sanitize_text_field( $_POST['qcb_stripe'] ?? '' ),
			'active'                => isset( $_POST['qcb_active'] ) ? 1 : 0,
		];

		$bundle_id = isset( $_POST['bundle_id'] ) ? (int) $_POST['bundle_id'] : 0;

		if ( $bundle_id ) {
			$this->bundles->update_bundle( $bundle_id, $data );
		} else {
			$this->bundles->create_bundle( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=khm-qc-bundles&khm_qc_notice=saved' ) );
		exit;
	}

	public function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'kh-quote-club' ) );
		}

		$bundle_id = (int) ( $_GET['bundle_id'] ?? 0 );
		check_admin_referer( 'khm_qc_bundle_toggle_' . $bundle_id );

		$bundle = $this->bundles->get_bundle( $bundle_id );
		if ( $bundle ) {
			$this->bundles->update_bundle( $bundle_id, [ 'active' => $bundle->active ? 0 : 1 ] );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=khm-qc-bundles&khm_qc_notice=toggled' ) );
		exit;
	}
}
