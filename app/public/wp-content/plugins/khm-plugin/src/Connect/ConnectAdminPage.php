<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

class ConnectAdminPage {

	private ConnectProviderRepository $providers;

	public function __construct( ?ConnectProviderRepository $providers = null ) {
		$this->providers = $providers ?? new ConnectProviderRepository();
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_khm_connect_provider_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_khm_connect_provider_delete', array( $this, 'handle_delete' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'khm-membership',
			__( 'Connect Providers', 'khm-membership' ),
			__( 'Connect Providers', 'khm-membership' ),
			'manage_options',
			'khm-connect-providers',
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Connect providers.', 'khm-membership' ) );
		}

		$providers     = $this->providers->list_all();
		$edit_provider = isset( $_GET['provider_id'] ) ? $this->providers->get_by_id( absint( $_GET['provider_id'] ) ) : null;
		$notice        = isset( $_GET['connect_notice'] ) ? sanitize_key( (string) $_GET['connect_notice'] ) : '';

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connect Providers', 'khm-membership' ); ?></h1>
			<?php $this->render_notice( $notice ); ?>
			<p class="description" style="margin-bottom:16px;"><?php esc_html_e( 'Manage the provider catalog used by Connect.Net shortlist, comparison, commentary, and ad targeting flows.', 'khm-membership' ); ?></p>

			<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,1fr);gap:24px;align-items:start;">
				<div>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Titles', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Capabilities', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $providers ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No Connect providers found yet.', 'khm-membership' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $providers as $provider ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $provider['name'] ); ?></strong><br />
											<code><?php echo esc_html( $provider['slug'] ); ?></code>
										</td>
										<td><?php echo esc_html( ucfirst( $provider['status'] ) ); ?></td>
										<td><?php echo ! empty( $provider['titles'] ) ? esc_html( implode( ', ', $provider['titles'] ) ) : 'All'; ?></td>
										<td>
											<?php echo $provider['commentary_enabled'] ? esc_html__( 'Commentary', 'khm-membership' ) : ''; ?>
											<?php if ( $provider['commentary_enabled'] && $provider['ad_targeting_enabled'] ) : ?> / <?php endif; ?>
											<?php echo $provider['ad_targeting_enabled'] ? esc_html__( 'Ad targeting', 'khm-membership' ) : ''; ?>
										</td>
										<td>
											<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=khm-connect-providers&provider_id=' . absint( $provider['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'khm-membership' ); ?></a>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px;">
												<?php wp_nonce_field( 'khm_connect_provider_delete_' . absint( $provider['id'] ), 'khm_connect_provider_delete_nonce' ); ?>
												<input type="hidden" name="action" value="khm_connect_provider_delete" />
												<input type="hidden" name="provider_id" value="<?php echo esc_attr( absint( $provider['id'] ) ); ?>" />
												<button class="button" type="submit" onclick="return confirm('<?php echo esc_js( __( 'Delete this provider?', 'khm-membership' ) ); ?>');"><?php esc_html_e( 'Delete', 'khm-membership' ); ?></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<div>
					<h2><?php echo $edit_provider ? esc_html__( 'Edit Provider', 'khm-membership' ) : esc_html__( 'Add Provider', 'khm-membership' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'khm_connect_provider_save', 'khm_connect_provider_nonce' ); ?>
						<input type="hidden" name="action" value="khm_connect_provider_save" />
						<input type="hidden" name="provider_id" value="<?php echo esc_attr( (int) ( $edit_provider['id'] ?? 0 ) ); ?>" />
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="khm_connect_name"><?php esc_html_e( 'Name', 'khm-membership' ); ?></label></th>
								<td><input class="regular-text" type="text" id="khm_connect_name" name="name" required value="<?php echo esc_attr( (string) ( $edit_provider['name'] ?? '' ) ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_slug"><?php esc_html_e( 'Slug', 'khm-membership' ); ?></label></th>
								<td><input class="regular-text" type="text" id="khm_connect_slug" name="slug" value="<?php echo esc_attr( (string) ( $edit_provider['slug'] ?? '' ) ); ?>" /><p class="description"><?php esc_html_e( 'Leave blank to derive from the name.', 'khm-membership' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_sponsor_id"><?php esc_html_e( 'Sponsor ID', 'khm-membership' ); ?></label></th>
								<td><input class="small-text" type="number" min="0" id="khm_connect_sponsor_id" name="sponsor_id" value="<?php echo esc_attr( (string) ( $edit_provider['sponsor_id'] ?? '' ) ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_website"><?php esc_html_e( 'Website', 'khm-membership' ); ?></label></th>
								<td><input class="regular-text" type="url" id="khm_connect_website" name="website_url" value="<?php echo esc_attr( (string) ( $edit_provider['website_url'] ?? '' ) ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_provider_type"><?php esc_html_e( 'Provider Type', 'khm-membership' ); ?></label></th>
								<td><input class="regular-text" type="text" id="khm_connect_provider_type" name="provider_type" value="<?php echo esc_attr( (string) ( $edit_provider['provider_type'] ?? '' ) ); ?>" /><p class="description"><?php esc_html_e( 'Examples: agency, platform, consultancy, data-provider.', 'khm-membership' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_sweet_spot_summary"><?php esc_html_e( 'Sweet Spot Summary', 'khm-membership' ); ?></label></th>
								<td><textarea class="large-text" rows="3" id="khm_connect_sweet_spot_summary" name="sweet_spot_summary"><?php echo esc_textarea( (string) ( $edit_provider['sweet_spot_summary'] ?? '' ) ); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_regions"><?php esc_html_e( 'Regions', 'khm-membership' ); ?></label></th>
								<td><input class="regular-text" type="text" id="khm_connect_regions" name="regions" value="<?php echo esc_attr( implode( ', ', $edit_provider['regions'] ?? array() ) ); ?>" /><p class="description"><?php esc_html_e( 'Comma-separated region slugs or labels.', 'khm-membership' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_deployment_modes"><?php esc_html_e( 'Deployment Modes', 'khm-membership' ); ?></label></th>
								<td><input class="regular-text" type="text" id="khm_connect_deployment_modes" name="deployment_modes" value="<?php echo esc_attr( implode( ', ', $edit_provider['deployment_modes'] ?? array() ) ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_support_tiers"><?php esc_html_e( 'Support Tiers', 'khm-membership' ); ?></label></th>
								<td><input class="regular-text" type="text" id="khm_connect_support_tiers" name="support_tiers" value="<?php echo esc_attr( implode( ', ', $edit_provider['support_tiers'] ?? array() ) ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Fit Range', 'khm-membership' ); ?></th>
								<td>
									<input class="small-text" type="number" min="0" name="company_size_min" value="<?php echo esc_attr( (string) ( $edit_provider['company_size_min'] ?? '' ) ); ?>" />
									<span><?php esc_html_e( 'to', 'khm-membership' ); ?></span>
									<input class="small-text" type="number" min="0" name="company_size_max" value="<?php echo esc_attr( (string) ( $edit_provider['company_size_max'] ?? '' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'Typical company size range the provider is best suited for.', 'khm-membership' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Budget Range', 'khm-membership' ); ?></th>
								<td>
									<input class="small-text" type="number" min="0" name="budget_min" value="<?php echo esc_attr( (string) ( $edit_provider['budget_min'] ?? '' ) ); ?>" />
									<span><?php esc_html_e( 'to', 'khm-membership' ); ?></span>
									<input class="small-text" type="number" min="0" name="budget_max" value="<?php echo esc_attr( (string) ( $edit_provider['budget_max'] ?? '' ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_onboarding_days"><?php esc_html_e( 'Typical Onboarding Days', 'khm-membership' ); ?></label></th>
								<td><input class="small-text" type="number" min="0" id="khm_connect_onboarding_days" name="onboarding_days" value="<?php echo esc_attr( (string) ( $edit_provider['onboarding_days'] ?? '' ) ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_status"><?php esc_html_e( 'Status', 'khm-membership' ); ?></label></th>
								<td>
									<select id="khm_connect_status" name="status">
										<option value="active" <?php selected( (string) ( $edit_provider['status'] ?? 'active' ), 'active' ); ?>><?php esc_html_e( 'Active', 'khm-membership' ); ?></option>
										<option value="inactive" <?php selected( (string) ( $edit_provider['status'] ?? 'active' ), 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'khm-membership' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_titles"><?php esc_html_e( 'Title Contexts', 'khm-membership' ); ?></label></th>
								<td><textarea class="large-text" rows="3" id="khm_connect_titles" name="titles"><?php echo esc_textarea( implode( ', ', $edit_provider['titles'] ?? array() ) ); ?></textarea><p class="description"><?php esc_html_e( 'Comma-separated title slugs. Leave blank for all titles.', 'khm-membership' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_description"><?php esc_html_e( 'Description', 'khm-membership' ); ?></label></th>
								<td><textarea class="large-text" rows="4" id="khm_connect_description" name="description"><?php echo esc_textarea( (string) ( $edit_provider['description'] ?? '' ) ); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_comparison_fields"><?php esc_html_e( 'Comparison Fields JSON', 'khm-membership' ); ?></label></th>
								<td><textarea class="large-text code" rows="8" id="khm_connect_comparison_fields" name="comparison_fields"><?php echo esc_textarea( ! empty( $edit_provider['comparison_fields'] ) ? wp_json_encode( $edit_provider['comparison_fields'], JSON_PRETTY_PRINT ) : '{}' ); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="khm_connect_match_rules"><?php esc_html_e( 'Match Rules JSON', 'khm-membership' ); ?></label></th>
								<td><textarea class="large-text code" rows="10" id="khm_connect_match_rules" name="match_rules"><?php echo esc_textarea( ! empty( $edit_provider['match_rules'] ) ? wp_json_encode( $edit_provider['match_rules'], JSON_PRETTY_PRINT ) : '{}' ); ?></textarea><p class="description"><?php esc_html_e( 'Use keys such as industries, regions, company_sizes, deployment, keywords, budget_min, budget_max, and title_weights.', 'khm-membership' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Capabilities', 'khm-membership' ); ?></th>
								<td>
									<label><input type="checkbox" name="commentary_enabled" value="1" <?php checked( ! empty( $edit_provider['commentary_enabled'] ) ); ?> /> <?php esc_html_e( 'Eligible for commentary contexts', 'khm-membership' ); ?></label><br />
									<label><input type="checkbox" name="ad_targeting_enabled" value="1" <?php checked( ! empty( $edit_provider['ad_targeting_enabled'] ) ); ?> /> <?php esc_html_e( 'Eligible for Connect ad targeting', 'khm-membership' ); ?></label>
								</td>
							</tr>
						</table>
						<?php submit_button( $edit_provider ? __( 'Update Provider', 'khm-membership' ) : __( 'Create Provider', 'khm-membership' ) ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function handle_save(): void {
		$this->assert_manage_options();
		check_admin_referer( 'khm_connect_provider_save', 'khm_connect_provider_nonce' );

		$provider_id = $this->providers->save(
			array(
				'id'                   => isset( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0,
				'name'                 => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
				'slug'                 => isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '',
				'sponsor_id'           => isset( $_POST['sponsor_id'] ) ? absint( $_POST['sponsor_id'] ) : 0,
				'website_url'          => isset( $_POST['website_url'] ) ? wp_unslash( $_POST['website_url'] ) : '',
				'provider_type'        => isset( $_POST['provider_type'] ) ? wp_unslash( $_POST['provider_type'] ) : '',
				'sweet_spot_summary'   => isset( $_POST['sweet_spot_summary'] ) ? wp_unslash( $_POST['sweet_spot_summary'] ) : '',
				'regions'              => isset( $_POST['regions'] ) ? wp_unslash( $_POST['regions'] ) : '',
				'deployment_modes'     => isset( $_POST['deployment_modes'] ) ? wp_unslash( $_POST['deployment_modes'] ) : '',
				'support_tiers'        => isset( $_POST['support_tiers'] ) ? wp_unslash( $_POST['support_tiers'] ) : '',
				'company_size_min'     => isset( $_POST['company_size_min'] ) ? wp_unslash( $_POST['company_size_min'] ) : null,
				'company_size_max'     => isset( $_POST['company_size_max'] ) ? wp_unslash( $_POST['company_size_max'] ) : null,
				'budget_min'           => isset( $_POST['budget_min'] ) ? wp_unslash( $_POST['budget_min'] ) : null,
				'budget_max'           => isset( $_POST['budget_max'] ) ? wp_unslash( $_POST['budget_max'] ) : null,
				'onboarding_days'      => isset( $_POST['onboarding_days'] ) ? wp_unslash( $_POST['onboarding_days'] ) : null,
				'status'               => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'active',
				'titles'               => isset( $_POST['titles'] ) ? wp_unslash( $_POST['titles'] ) : '',
				'description'          => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'comparison_fields'    => isset( $_POST['comparison_fields'] ) ? wp_unslash( $_POST['comparison_fields'] ) : '{}',
				'match_rules'          => isset( $_POST['match_rules'] ) ? wp_unslash( $_POST['match_rules'] ) : '{}',
				'commentary_enabled'   => ! empty( $_POST['commentary_enabled'] ),
				'ad_targeting_enabled' => ! empty( $_POST['ad_targeting_enabled'] ),
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=khm-connect-providers&provider_id=' . $provider_id . '&connect_notice=saved' ) );
		exit;
	}

	public function handle_delete(): void {
		$this->assert_manage_options();

		$provider_id = isset( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0;
		check_admin_referer( 'khm_connect_provider_delete_' . $provider_id, 'khm_connect_provider_delete_nonce' );

		if ( $provider_id > 0 ) {
			$this->providers->delete( $provider_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=khm-connect-providers&connect_notice=deleted' ) );
		exit;
	}

	private function render_notice( string $notice ): void {
		if ( 'saved' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Connect provider saved.', 'khm-membership' ) . '</p></div>';
			return;
		}

		if ( 'deleted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Connect provider deleted.', 'khm-membership' ) . '</p></div>';
		}
	}

	private function assert_manage_options(): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_die( esc_html__( 'You do not have permission to manage Connect providers.', 'khm-membership' ) );
	}
}