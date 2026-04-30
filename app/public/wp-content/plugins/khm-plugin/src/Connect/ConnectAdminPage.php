<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

class ConnectAdminPage {
	private const EDITORIAL_CAMPAIGN_OPTION = 'khm_connect_editorial_campaign';
	private const PROMOTION_BUILDER_OPTION = 'khm_connect_promotion_builder';

	private ConnectProviderRepository $providers;
	private ConnectOpportunityRepository $opportunities;

	public function __construct(
		?ConnectProviderRepository $providers = null,
		?ConnectOpportunityRepository $opportunities = null
	) {
		$this->providers     = $providers ?? new ConnectProviderRepository();
		$this->opportunities = $opportunities ?? new ConnectOpportunityRepository();
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_khm_connect_provider_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_khm_connect_provider_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_khm_connect_pricing_save', array( $this, 'handle_pricing_save' ) );
		add_action( 'admin_post_khm_connect_editorial_campaign_save', array( $this, 'handle_editorial_campaign_save' ) );
		add_action( 'admin_post_khm_connect_promotion_builder_save', array( $this, 'handle_promotion_builder_save' ) );
		add_action( 'admin_post_khm_connect_opportunity_accept', array( $this, 'handle_opportunity_accept' ) );
		add_action( 'admin_post_khm_connect_opportunity_status', array( $this, 'handle_opportunity_status' ) );
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
		add_submenu_page(
			'khm-membership',
			__( 'Connect Pricing', 'khm-membership' ),
			__( 'Connect Pricing', 'khm-membership' ),
			'manage_options',
			'khm-connect-pricing',
			array( $this, 'render_pricing_page' )
		);
		add_submenu_page(
			'khm-membership',
			__( 'Connect Opportunities', 'khm-membership' ),
			__( 'Connect Opportunities', 'khm-membership' ),
			'manage_options',
			'khm-connect-opportunities',
			array( $this, 'render_opportunities_page' )
		);
		add_submenu_page(
			'khm-membership',
			__( 'Connect Editorial Campaign', 'khm-membership' ),
			__( 'Connect Editorial Campaign', 'khm-membership' ),
			'manage_options',
			'khm-connect-editorial-campaign',
			array( $this, 'render_editorial_campaign_page' )
		);
		add_submenu_page(
			'khm-membership',
			__( 'Connect Promotion Builder', 'khm-membership' ),
			__( 'Connect Promotion Builder', 'khm-membership' ),
			'manage_options',
			'khm-connect-promotion-builder',
			array( $this, 'render_promotion_builder_page' )
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

	public function render_pricing_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Connect pricing.', 'khm-membership' ) );
		}

		$notice = isset( $_GET['connect_notice'] ) ? sanitize_key( (string) $_GET['connect_notice'] ) : '';
		$config = ConnectTiering::get_config();
		$tiers  = ConnectTiering::TIERS;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connect Pricing', 'khm-membership' ); ?></h1>
			<?php if ( 'pricing_saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Connect pricing saved.', 'khm-membership' ); ?></p></div>
			<?php endif; ?>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'Configure the default pricing model, unit price, and engaged-tier commission settings for each Connect commercial tier. These values are snapshotted when an opportunity is first created.', 'khm-membership' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'khm_connect_pricing_save', 'khm_connect_pricing_nonce' ); ?>
				<input type="hidden" name="action" value="khm_connect_pricing_save" />

				<?php foreach ( $tiers as $tier ) :
					$row   = $config[ $tier ];
					$label = ucfirst( $tier );
				?>
				<h2 style="border-bottom:1px solid #ccd0d4;padding-bottom:8px;margin-top:32px;">
					<?php echo esc_html( $label ); ?> <?php esc_html_e( 'Tier', 'khm-membership' ); ?>
				</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="khm_pricing_<?php echo esc_attr( $tier ); ?>_model"><?php esc_html_e( 'Pricing Model', 'khm-membership' ); ?></label></th>
						<td>
							<select id="khm_pricing_<?php echo esc_attr( $tier ); ?>_model" name="pricing[<?php echo esc_attr( $tier ); ?>][pricing_model]">
								<option value="cpl" <?php selected( $row['pricing_model'], 'cpl' ); ?>><?php esc_html_e( 'CPL — Cost Per Lead', 'khm-membership' ); ?></option>
								<option value="cpa" <?php selected( $row['pricing_model'], 'cpa' ); ?>><?php esc_html_e( 'CPA — Cost Per Acquisition', 'khm-membership' ); ?></option>
								<option value="flat" <?php selected( $row['pricing_model'], 'flat' ); ?>><?php esc_html_e( 'Flat Fee', 'khm-membership' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_pricing_<?php echo esc_attr( $tier ); ?>_unit"><?php esc_html_e( 'Unit Price (cents)', 'khm-membership' ); ?></label></th>
						<td>
							<input class="small-text" type="number" min="0" step="1" id="khm_pricing_<?php echo esc_attr( $tier ); ?>_unit" name="pricing[<?php echo esc_attr( $tier ); ?>][unit_price_cents]" value="<?php echo esc_attr( (string) (int) $row['unit_price_cents'] ); ?>" />
							<p class="description"><?php echo esc_html( sprintf( __( 'e.g. 15000 = $150.00. Current: $%s', 'khm-membership' ), number_format( (int) $row['unit_price_cents'] / 100, 2 ) ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Commission Eligible', 'khm-membership' ); ?></th>
						<td>
							<label><input type="checkbox" name="pricing[<?php echo esc_attr( $tier ); ?>][commission_eligible]" value="1" <?php checked( ! empty( $row['commission_eligible'] ) ); ?> /> <?php esc_html_e( 'Opportunities in this tier are eligible for commission.', 'khm-membership' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_pricing_<?php echo esc_attr( $tier ); ?>_acv"><?php esc_html_e( 'Engaged ACV (cents)', 'khm-membership' ); ?></label></th>
						<td>
							<input class="small-text" type="number" min="0" step="1" id="khm_pricing_<?php echo esc_attr( $tier ); ?>_acv" name="pricing[<?php echo esc_attr( $tier ); ?>][engaged_acv_cents]" value="<?php echo esc_attr( (string) (int) $row['engaged_acv_cents'] ); ?>" />
							<p class="description"><?php echo esc_html( sprintf( __( 'Annual contract value baseline for engaged leads. Current: $%s', 'khm-membership' ), number_format( (int) $row['engaged_acv_cents'] / 100, 2 ) ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_pricing_<?php echo esc_attr( $tier ); ?>_rate"><?php esc_html_e( 'Engaged Commission Rate', 'khm-membership' ); ?></label></th>
						<td>
							<input class="small-text" type="number" min="0" max="1" step="0.01" id="khm_pricing_<?php echo esc_attr( $tier ); ?>_rate" name="pricing[<?php echo esc_attr( $tier ); ?>][engaged_commission_rate]" value="<?php echo esc_attr( (string) (float) $row['engaged_commission_rate'] ); ?>" />
							<p class="description"><?php esc_html_e( '0.0 to 1.0 (e.g. 0.10 = 10%).', 'khm-membership' ); ?></p>
						</td>
					</tr>
				</table>
				<?php endforeach; ?>

				<?php submit_button( __( 'Save Pricing', 'khm-membership' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_pricing_save(): void {
		$this->assert_manage_options();
		check_admin_referer( 'khm_connect_pricing_save', 'khm_connect_pricing_nonce' );

		$raw = isset( $_POST['pricing'] ) && is_array( $_POST['pricing'] ) ? wp_unslash( $_POST['pricing'] ) : array();

		$config = array();
		foreach ( ConnectTiering::TIERS as $tier ) {
			$row                         = is_array( $raw[ $tier ] ?? null ) ? $raw[ $tier ] : array();
			$config[ $tier ]             = array(
				'pricing_model'           => $row['pricing_model'] ?? 'cpl',
				'unit_price_cents'        => $row['unit_price_cents'] ?? 0,
				'commission_eligible'     => ! empty( $row['commission_eligible'] ) ? 1 : 0,
				'engaged_acv_cents'       => $row['engaged_acv_cents'] ?? 0,
				'engaged_commission_rate' => $row['engaged_commission_rate'] ?? 0.0,
			);
		}

		ConnectTiering::save_config( $config );

		wp_safe_redirect( admin_url( 'admin.php?page=khm-connect-pricing&connect_notice=pricing_saved' ) );
		exit;
	}

	public function render_editorial_campaign_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Connect editorial campaigns.', 'khm-membership' ) );
		}

		$notice         = isset( $_GET['connect_notice'] ) ? sanitize_key( (string) $_GET['connect_notice'] ) : '';
		$campaign       = $this->get_editorial_campaign_config();
		$active_providers = $this->providers->list_active();
		$stage_options  = array_keys( ConnectTiering::STAGE_TIER_MAP );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connect Editorial Campaign Setup', 'khm-membership' ); ?></h1>
			<?php if ( 'editorial_campaign_saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Editorial campaign settings saved.', 'khm-membership' ); ?></p></div>
			<?php endif; ?>

			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'Define campaign brief and stage targeting, then align sponsor/provider controls used by editorial and promotional Connect surfaces.', 'khm-membership' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'khm_connect_editorial_campaign_save', 'khm_connect_editorial_campaign_nonce' ); ?>
				<input type="hidden" name="action" value="khm_connect_editorial_campaign_save" />

				<h2><?php esc_html_e( 'Campaign Brief', 'khm-membership' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="khm_editorial_campaign_name"><?php esc_html_e( 'Campaign Name', 'khm-membership' ); ?></label></th>
						<td><input class="regular-text" id="khm_editorial_campaign_name" type="text" name="campaign[name]" value="<?php echo esc_attr( (string) $campaign['name'] ); ?>" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_editorial_campaign_slug"><?php esc_html_e( 'Campaign Slug', 'khm-membership' ); ?></label></th>
						<td><input class="regular-text" id="khm_editorial_campaign_slug" type="text" name="campaign[slug]" value="<?php echo esc_attr( (string) $campaign['slug'] ); ?>" /><p class="description"><?php esc_html_e( 'Used as an internal routing key for reporting and attribution.', 'khm-membership' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_editorial_campaign_summary"><?php esc_html_e( 'Campaign Summary', 'khm-membership' ); ?></label></th>
						<td><textarea class="large-text" rows="4" id="khm_editorial_campaign_summary" name="campaign[summary]"><?php echo esc_textarea( (string) $campaign['summary'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_editorial_campaign_goals"><?php esc_html_e( 'Editorial Goals', 'khm-membership' ); ?></label></th>
						<td><textarea class="large-text" rows="4" id="khm_editorial_campaign_goals" name="campaign[goals]"><?php echo esc_textarea( (string) $campaign['goals'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_editorial_campaign_audience"><?php esc_html_e( 'Target Audience', 'khm-membership' ); ?></label></th>
						<td><textarea class="large-text" rows="3" id="khm_editorial_campaign_audience" name="campaign[audience]" placeholder="CMOs at B2B SaaS firms, 50-500 employees, evaluating attribution stack."><?php echo esc_textarea( (string) $campaign['audience'] ); ?></textarea></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Stage Targeting + Sponsor Alignment', 'khm-membership' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Stage Targets', 'khm-membership' ); ?></th>
						<td>
							<?php foreach ( $stage_options as $stage ) : ?>
								<label style="display:inline-block;margin-right:12px;">
									<input type="checkbox" name="campaign[stage_targets][]" value="<?php echo esc_attr( $stage ); ?>" <?php checked( in_array( $stage, $campaign['stage_targets'], true ) ); ?> />
									<?php echo esc_html( ucfirst( $stage ) ); ?>
									<small>(<?php echo esc_html( ConnectTiering::STAGE_TIER_MAP[ $stage ] ); ?>)</small>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_editorial_sponsor_id"><?php esc_html_e( 'Sponsor ID', 'khm-membership' ); ?></label></th>
						<td><input class="small-text" id="khm_editorial_sponsor_id" type="number" min="0" name="campaign[sponsor_id]" value="<?php echo esc_attr( (string) (int) $campaign['sponsor_id'] ); ?>" /><p class="description"><?php esc_html_e( 'Optional sponsor owner for this campaign.', 'khm-membership' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_editorial_default_provider"><?php esc_html_e( 'Default Connect Provider', 'khm-membership' ); ?></label></th>
						<td>
							<select id="khm_editorial_default_provider" name="campaign[default_provider_id]">
								<option value="0"><?php esc_html_e( '— none —', 'khm-membership' ); ?></option>
								<?php foreach ( $active_providers as $provider ) : ?>
									<option value="<?php echo esc_attr( (string) (int) $provider['id'] ); ?>" <?php selected( (int) $campaign['default_provider_id'], (int) $provider['id'] ); ?>>
										#<?php echo esc_html( (string) (int) $provider['id'] ); ?> — <?php echo esc_html( (string) $provider['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_editorial_promo_notes"><?php esc_html_e( 'Promotion Notes', 'khm-membership' ); ?></label></th>
						<td><textarea class="large-text" rows="3" id="khm_editorial_promo_notes" name="campaign[promotion_notes]"><?php echo esc_textarea( (string) $campaign['promotion_notes'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Campaign Active', 'khm-membership' ); ?></th>
						<td>
							<label><input type="checkbox" name="campaign[active]" value="1" <?php checked( ! empty( $campaign['active'] ) ); ?> /> <?php esc_html_e( 'Enable this campaign for editorial/promotional workflows', 'khm-membership' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Editorial Campaign', 'khm-membership' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_editorial_campaign_save(): void {
		$this->assert_manage_options();
		check_admin_referer( 'khm_connect_editorial_campaign_save', 'khm_connect_editorial_campaign_nonce' );

		$raw = isset( $_POST['campaign'] ) && is_array( $_POST['campaign'] ) ? wp_unslash( $_POST['campaign'] ) : array();
		$clean = $this->sanitize_editorial_campaign_config( $raw );

		update_option( self::EDITORIAL_CAMPAIGN_OPTION, $clean, false );

		wp_safe_redirect( admin_url( 'admin.php?page=khm-connect-editorial-campaign&connect_notice=editorial_campaign_saved' ) );
		exit;
	}

	public function render_promotion_builder_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Connect promotions.', 'khm-membership' ) );
		}

		$notice = isset( $_GET['connect_notice'] ) ? sanitize_key( (string) $_GET['connect_notice'] ) : '';
		$config = $this->get_promotion_builder_config();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connect Promotion Builder', 'khm-membership' ); ?></h1>
			<?php if ( 'promotion_builder_saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Promotion builder settings saved.', 'khm-membership' ); ?></p></div>
			<?php endif; ?>

			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'Configure audience rules, budget pacing, and approval state for Connect promotional delivery.', 'khm-membership' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'khm_connect_promotion_builder_save', 'khm_connect_promotion_builder_nonce' ); ?>
				<input type="hidden" name="action" value="khm_connect_promotion_builder_save" />

				<h2><?php esc_html_e( 'Audience Rules', 'khm-membership' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="khm_promo_include_titles"><?php esc_html_e( 'Include Title Contexts', 'khm-membership' ); ?></label></th>
						<td><input class="regular-text" id="khm_promo_include_titles" type="text" name="promotion[include_titles]" value="<?php echo esc_attr( (string) $config['include_titles'] ); ?>" /><p class="description"><?php esc_html_e( 'Comma-separated slugs, e.g. cmo, revops, demand-gen.', 'khm-membership' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_promo_exclude_titles"><?php esc_html_e( 'Exclude Title Contexts', 'khm-membership' ); ?></label></th>
						<td><input class="regular-text" id="khm_promo_exclude_titles" type="text" name="promotion[exclude_titles]" value="<?php echo esc_attr( (string) $config['exclude_titles'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_promo_include_regions"><?php esc_html_e( 'Include Regions', 'khm-membership' ); ?></label></th>
						<td><input class="regular-text" id="khm_promo_include_regions" type="text" name="promotion[include_regions]" value="<?php echo esc_attr( (string) $config['include_regions'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_promo_exclude_regions"><?php esc_html_e( 'Exclude Regions', 'khm-membership' ); ?></label></th>
						<td><input class="regular-text" id="khm_promo_exclude_regions" type="text" name="promotion[exclude_regions]" value="<?php echo esc_attr( (string) $config['exclude_regions'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_promo_keywords"><?php esc_html_e( 'Audience Keywords', 'khm-membership' ); ?></label></th>
						<td><input class="regular-text" id="khm_promo_keywords" type="text" name="promotion[keywords]" value="<?php echo esc_attr( (string) $config['keywords'] ); ?>" /><p class="description"><?php esc_html_e( 'Comma-separated intent terms used for lightweight matching.', 'khm-membership' ); ?></p></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Budget + Pacing', 'khm-membership' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="khm_promo_budget_daily"><?php esc_html_e( 'Daily Budget (cents)', 'khm-membership' ); ?></label></th>
						<td><input class="small-text" id="khm_promo_budget_daily" type="number" min="0" step="1" name="promotion[budget_daily_cents]" value="<?php echo esc_attr( (string) (int) $config['budget_daily_cents'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_promo_budget_total"><?php esc_html_e( 'Total Budget (cents)', 'khm-membership' ); ?></label></th>
						<td><input class="small-text" id="khm_promo_budget_total" type="number" min="0" step="1" name="promotion[budget_total_cents]" value="<?php echo esc_attr( (string) (int) $config['budget_total_cents'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_promo_pacing"><?php esc_html_e( 'Pacing Mode', 'khm-membership' ); ?></label></th>
						<td>
							<select id="khm_promo_pacing" name="promotion[pacing_mode]">
								<option value="steady" <?php selected( (string) $config['pacing_mode'], 'steady' ); ?>><?php esc_html_e( 'Steady', 'khm-membership' ); ?></option>
								<option value="front_loaded" <?php selected( (string) $config['pacing_mode'], 'front_loaded' ); ?>><?php esc_html_e( 'Front Loaded', 'khm-membership' ); ?></option>
								<option value="back_loaded" <?php selected( (string) $config['pacing_mode'], 'back_loaded' ); ?>><?php esc_html_e( 'Back Loaded', 'khm-membership' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Approval + Audit', 'khm-membership' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="khm_promo_approval_status"><?php esc_html_e( 'Approval Status', 'khm-membership' ); ?></label></th>
						<td>
							<select id="khm_promo_approval_status" name="promotion[approval_status]">
								<option value="draft" <?php selected( (string) $config['approval_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'khm-membership' ); ?></option>
								<option value="pending_approval" <?php selected( (string) $config['approval_status'], 'pending_approval' ); ?>><?php esc_html_e( 'Pending Approval', 'khm-membership' ); ?></option>
								<option value="approved" <?php selected( (string) $config['approval_status'], 'approved' ); ?>><?php esc_html_e( 'Approved', 'khm-membership' ); ?></option>
								<option value="paused" <?php selected( (string) $config['approval_status'], 'paused' ); ?>><?php esc_html_e( 'Paused', 'khm-membership' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_promo_owner"><?php esc_html_e( 'Owner', 'khm-membership' ); ?></label></th>
						<td><input class="regular-text" id="khm_promo_owner" type="text" name="promotion[owner]" value="<?php echo esc_attr( (string) $config['owner'] ); ?>" placeholder="editorial-ops" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="khm_promo_notes"><?php esc_html_e( 'Audit Notes', 'khm-membership' ); ?></label></th>
						<td><textarea class="large-text" rows="4" id="khm_promo_notes" name="promotion[audit_notes]"><?php echo esc_textarea( (string) $config['audit_notes'] ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Promotion Builder', 'khm-membership' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_promotion_builder_save(): void {
		$this->assert_manage_options();
		check_admin_referer( 'khm_connect_promotion_builder_save', 'khm_connect_promotion_builder_nonce' );

		$raw = isset( $_POST['promotion'] ) && is_array( $_POST['promotion'] ) ? wp_unslash( $_POST['promotion'] ) : array();
		$clean = $this->sanitize_promotion_builder_config( $raw );

		update_option( self::PROMOTION_BUILDER_OPTION, $clean, false );

		wp_safe_redirect( admin_url( 'admin.php?page=khm-connect-promotion-builder&connect_notice=promotion_builder_saved' ) );
		exit;
	}

	public function render_opportunities_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view Connect opportunities.', 'khm-membership' ) );
		}

		// Allow scoping the inbox to a specific sponsor_id for testing. In a
		// production sponsor portal this would be the logged-in user's sponsor ID.
		$sponsor_id     = isset( $_GET['sponsor_id'] ) ? absint( $_GET['sponsor_id'] ) : 0;
		$notice         = isset( $_GET['connect_notice'] ) ? sanitize_key( (string) $_GET['connect_notice'] ) : '';
		$active_id      = isset( $_GET['opportunity_id'] ) ? absint( $_GET['opportunity_id'] ) : 0;

		if ( $sponsor_id > 0 ) {
			$rows = $this->opportunities->list_inbox_for_sponsor( $sponsor_id );
		} else {
			$rows = $this->opportunities->list_all_inbox();
		}

		$active_opportunity = $active_id > 0 ? $this->opportunities->get_by_id( $active_id ) : null;
		$active_providers   = $this->providers->list_active();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connect Opportunity Inbox', 'khm-membership' ); ?></h1>

			<?php if ( 'accepted' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Opportunity accepted and provider assigned.', 'khm-membership' ); ?></p></div>
			<?php elseif ( 'status_updated' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Opportunity status updated.', 'khm-membership' ); ?></p></div>
			<?php elseif ( 'error_accept' === $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Acceptance failed. Ensure sponsor ID and provider are both valid.', 'khm-membership' ); ?></p></div>
			<?php endif; ?>

			<div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
				<form method="get">
					<input type="hidden" name="page" value="khm-connect-opportunities" />
					<label><?php esc_html_e( 'Filter by Sponsor ID:', 'khm-membership' ); ?>
						<input class="small-text" type="number" min="1" name="sponsor_id" value="<?php echo esc_attr( $sponsor_id > 0 ? (string) $sponsor_id : '' ); ?>" />
					</label>
					<?php submit_button( __( 'Apply', 'khm-membership' ), 'secondary', '', false ); ?>
					<?php if ( $sponsor_id > 0 ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-connect-opportunities' ) ); ?>"><?php esc_html_e( 'Clear', 'khm-membership' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,1fr);gap:24px;align-items:start;">
				<div>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Tier', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Score', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Price', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Domain', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Sponsor / Provider', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Created', 'khm-membership' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $rows ) ) : ?>
								<tr><td colspan="9"><?php esc_html_e( 'No opportunities found.', 'khm-membership' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $rows as $opp ) :
									$row_url = add_query_arg(
										array(
											'page'           => 'khm-connect-opportunities',
											'opportunity_id' => $opp['id'],
											'sponsor_id'     => $sponsor_id > 0 ? $sponsor_id : false,
										),
										admin_url( 'admin.php' )
									);
									$is_active = $active_id === (int) $opp['id'];
								?>
									<tr <?php echo $is_active ? 'style="background:#f0f6fb;"' : ''; ?>>
										<td><?php echo esc_html( (string) $opp['id'] ); ?></td>
										<td><span style="text-transform:capitalize;"><?php echo esc_html( str_replace( '_', ' ', $opp['opportunity_status'] ) ); ?></span></td>
										<td><?php echo esc_html( ucfirst( $opp['commercial_tier'] ) ); ?></td>
										<td><?php echo esc_html( number_format( (float) $opp['person_score'], 1 ) ); ?></td>
										<td>
											<?php echo esc_html( strtoupper( $opp['pricing_model'] ) ); ?>
											$<?php echo esc_html( number_format( (int) $opp['unit_price_cents'] / 100, 2 ) ); ?>
											<?php if ( $opp['commission_eligible'] ) : ?>
												<span title="<?php esc_attr_e( 'Commission eligible', 'khm-membership' ); ?>">★</span>
											<?php endif; ?>
										</td>
										<td><code><?php echo esc_html( $opp['actor_email_domain'] ); ?></code></td>
										<td>
											<?php if ( $opp['sponsor_id'] > 0 ) : ?>
												<span title="<?php esc_attr_e( 'Sponsor', 'khm-membership' ); ?>">#<?php echo esc_html( (string) $opp['sponsor_id'] ); ?></span>
												/ <span title="<?php esc_attr_e( 'Provider', 'khm-membership' ); ?>">#<?php echo esc_html( (string) $opp['provider_id'] ); ?></span>
											<?php else : ?>
												<em><?php esc_html_e( 'Unassigned', 'khm-membership' ); ?></em>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( substr( $opp['created_at'], 0, 10 ) ); ?></td>
										<td>
											<a class="button button-secondary" href="<?php echo esc_url( $row_url ); ?>"><?php esc_html_e( 'Detail', 'khm-membership' ); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					<?php if ( ! empty( $rows ) ) : ?>
						<p class="description" style="margin-top:8px;"><?php echo esc_html( sprintf( _n( '%d opportunity', '%d opportunities', count( $rows ), 'khm-membership' ), count( $rows ) ) ); ?></p>
					<?php endif; ?>
				</div>

				<div>
					<?php if ( null !== $active_opportunity ) : ?>
						<h2><?php
							/* translators: %d = opportunity ID */
							echo esc_html( sprintf( __( 'Opportunity #%d', 'khm-membership' ), $active_opportunity['id'] ) );
						?></h2>
						<table class="widefat" style="margin-bottom:16px;">
							<tbody>
								<tr><th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th><td><?php echo esc_html( str_replace( '_', ' ', $active_opportunity['opportunity_status'] ) ); ?></td></tr>
								<tr><th><?php esc_html_e( 'Tier', 'khm-membership' ); ?></th><td><?php echo esc_html( ucfirst( $active_opportunity['commercial_tier'] ) ); ?></td></tr>
								<tr><th><?php esc_html_e( 'Stage', 'khm-membership' ); ?></th><td><?php echo esc_html( $active_opportunity['internal_stage'] ); ?></td></tr>
								<tr><th><?php esc_html_e( 'Person Score', 'khm-membership' ); ?></th><td><?php echo esc_html( number_format( (float) $active_opportunity['person_score'], 2 ) ); ?></td></tr>
								<tr><th><?php esc_html_e( 'Pricing Model', 'khm-membership' ); ?></th><td><?php echo esc_html( strtoupper( $active_opportunity['pricing_model'] ) ); ?></td></tr>
								<tr><th><?php esc_html_e( 'Unit Price', 'khm-membership' ); ?></th><td>$<?php echo esc_html( number_format( (int) $active_opportunity['unit_price_cents'] / 100, 2 ) ); ?></td></tr>
								<tr><th><?php esc_html_e( 'Commission', 'khm-membership' ); ?></th><td><?php echo $active_opportunity['commission_eligible'] ? esc_html__( 'Yes', 'khm-membership' ) : esc_html__( 'No', 'khm-membership' ); ?></td></tr>
								<tr><th><?php esc_html_e( 'Domain', 'khm-membership' ); ?></th><td><code><?php echo esc_html( $active_opportunity['actor_email_domain'] ); ?></code></td></tr>
								<tr><th><?php esc_html_e( 'Sponsor ID', 'khm-membership' ); ?></th><td><?php echo $active_opportunity['sponsor_id'] > 0 ? esc_html( (string) $active_opportunity['sponsor_id'] ) : '<em>' . esc_html__( 'None', 'khm-membership' ) . '</em>'; ?></td></tr>
								<tr><th><?php esc_html_e( 'Provider ID', 'khm-membership' ); ?></th><td><?php echo $active_opportunity['provider_id'] > 0 ? esc_html( (string) $active_opportunity['provider_id'] ) : '<em>' . esc_html__( 'None', 'khm-membership' ) . '</em>'; ?></td></tr>
								<tr><th><?php esc_html_e( 'Created', 'khm-membership' ); ?></th><td><?php echo esc_html( $active_opportunity['created_at'] ); ?></td></tr>
							</tbody>
						</table>

						<?php if ( 'sponsor_accepted' !== $active_opportunity['opportunity_status'] ) : ?>
							<h3><?php esc_html_e( 'Accept Opportunity', 'khm-membership' ); ?></h3>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'khm_connect_opportunity_accept_' . (int) $active_opportunity['id'], 'khm_connect_opp_accept_nonce' ); ?>
								<input type="hidden" name="action" value="khm_connect_opportunity_accept" />
								<input type="hidden" name="opportunity_id" value="<?php echo esc_attr( (string) (int) $active_opportunity['id'] ); ?>" />
								<input type="hidden" name="redirect_sponsor_id" value="<?php echo esc_attr( (string) $sponsor_id ); ?>" />
								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><label for="khm_opp_sponsor_id"><?php esc_html_e( 'Sponsor ID', 'khm-membership' ); ?></label></th>
										<td>
											<input class="small-text" type="number" min="1" id="khm_opp_sponsor_id" name="sponsor_id" required value="<?php echo esc_attr( $sponsor_id > 0 ? (string) $sponsor_id : '' ); ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="khm_opp_provider_id"><?php esc_html_e( 'Provider', 'khm-membership' ); ?></label></th>
										<td>
											<select id="khm_opp_provider_id" name="provider_id" required>
												<option value=""><?php esc_html_e( '— select provider —', 'khm-membership' ); ?></option>
												<?php foreach ( $active_providers as $prov ) : ?>
													<option value="<?php echo esc_attr( (string) (int) $prov['id'] ); ?>">
														<?php echo esc_html( $prov['name'] ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
								</table>
								<?php submit_button( __( 'Accept + Assign Provider', 'khm-membership' ), 'primary' ); ?>
							</form>
						<?php else : ?>
							<p class="notice notice-info" style="padding:8px 12px;"><?php esc_html_e( 'This opportunity has already been accepted.', 'khm-membership' ); ?></p>
						<?php endif; ?>

						<h3 style="margin-top:24px;"><?php esc_html_e( 'Update Status', 'khm-membership' ); ?></h3>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'khm_connect_opportunity_status_' . (int) $active_opportunity['id'], 'khm_connect_opp_status_nonce' ); ?>
							<input type="hidden" name="action" value="khm_connect_opportunity_status" />
							<input type="hidden" name="opportunity_id" value="<?php echo esc_attr( (string) (int) $active_opportunity['id'] ); ?>" />
							<input type="hidden" name="redirect_sponsor_id" value="<?php echo esc_attr( (string) $sponsor_id ); ?>" />
							<select name="opportunity_status">
								<?php foreach ( array( 'detected', 'offered', 'sponsor_accepted', 'intro_requested', 'introduced', 'rejected', 'expired' ) as $s ) : ?>
									<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $active_opportunity['opportunity_status'], $s ); ?>>
										<?php echo esc_html( ucwords( str_replace( '_', ' ', $s ) ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php submit_button( __( 'Update Status', 'khm-membership' ), 'secondary', '', false ); ?>
						</form>

					<?php else : ?>
						<div style="padding:16px;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;">
							<p><?php esc_html_e( 'Select an opportunity from the list to view its detail and take action.', 'khm-membership' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public function handle_opportunity_accept(): void {
		$this->assert_manage_options();

		$opportunity_id    = isset( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
		$redirect_sponsor  = isset( $_POST['redirect_sponsor_id'] ) ? absint( $_POST['redirect_sponsor_id'] ) : 0;

		check_admin_referer( 'khm_connect_opportunity_accept_' . $opportunity_id, 'khm_connect_opp_accept_nonce' );

		$sponsor_id  = isset( $_POST['sponsor_id'] ) ? absint( $_POST['sponsor_id'] ) : 0;
		$provider_id = isset( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0;

		$ok = ( $opportunity_id > 0 && $sponsor_id > 0 && $provider_id > 0 )
			? $this->opportunities->mark_sponsor_acceptance( $opportunity_id, $sponsor_id, $provider_id )
			: false;

		$redirect = add_query_arg(
			array_filter(
				array(
					'page'           => 'khm-connect-opportunities',
					'opportunity_id' => $ok ? $opportunity_id : false,
					'sponsor_id'     => $redirect_sponsor > 0 ? $redirect_sponsor : false,
					'connect_notice' => $ok ? 'accepted' : 'error_accept',
				)
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public function handle_opportunity_status(): void {
		$this->assert_manage_options();

		$opportunity_id   = isset( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
		$redirect_sponsor = isset( $_POST['redirect_sponsor_id'] ) ? absint( $_POST['redirect_sponsor_id'] ) : 0;

		check_admin_referer( 'khm_connect_opportunity_status_' . $opportunity_id, 'khm_connect_opp_status_nonce' );

		$allowed_statuses = array( 'detected', 'offered', 'sponsor_accepted', 'intro_requested', 'introduced', 'rejected', 'expired' );
		$new_status       = isset( $_POST['opportunity_status'] ) ? sanitize_key( (string) $_POST['opportunity_status'] ) : '';

		if ( $opportunity_id > 0 && in_array( $new_status, $allowed_statuses, true ) ) {
			$this->opportunities->mark_status( $opportunity_id, $new_status );
			$notice = 'status_updated';
		} else {
			$notice = 'error_accept';
		}

		$redirect = add_query_arg(
			array_filter(
				array(
					'page'           => 'khm-connect-opportunities',
					'opportunity_id' => $opportunity_id > 0 ? $opportunity_id : false,
					'sponsor_id'     => $redirect_sponsor > 0 ? $redirect_sponsor : false,
					'connect_notice' => $notice,
				)
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
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

	private function get_editorial_campaign_config(): array {
		$defaults = array(
			'name'               => '',
			'slug'               => '',
			'summary'            => '',
			'goals'              => '',
			'audience'           => '',
			'stage_targets'      => array_keys( ConnectTiering::STAGE_TIER_MAP ),
			'sponsor_id'         => 0,
			'default_provider_id'=> 0,
			'promotion_notes'    => '',
			'active'             => 0,
		);

		$stored = get_option( self::EDITORIAL_CAMPAIGN_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $this->sanitize_editorial_campaign_config( $stored ) );
	}

	private function sanitize_editorial_campaign_config( array $raw ): array {
		$valid_stages = array_keys( ConnectTiering::STAGE_TIER_MAP );
		$targets = isset( $raw['stage_targets'] ) && is_array( $raw['stage_targets'] ) ? array_map( 'sanitize_key', $raw['stage_targets'] ) : array();
		$targets = array_values( array_intersect( $targets, $valid_stages ) );
		if ( empty( $targets ) ) {
			$targets = array( 'attention' );
		}

		$slug = sanitize_title( (string) ( $raw['slug'] ?? '' ) );
		if ( '' === $slug ) {
			$slug = sanitize_title( (string) ( $raw['name'] ?? '' ) );
		}

		return array(
			'name'                => sanitize_text_field( (string) ( $raw['name'] ?? '' ) ),
			'slug'                => $slug,
			'summary'             => sanitize_textarea_field( (string) ( $raw['summary'] ?? '' ) ),
			'goals'               => sanitize_textarea_field( (string) ( $raw['goals'] ?? '' ) ),
			'audience'            => sanitize_textarea_field( (string) ( $raw['audience'] ?? '' ) ),
			'stage_targets'       => $targets,
			'sponsor_id'          => absint( $raw['sponsor_id'] ?? 0 ),
			'default_provider_id' => absint( $raw['default_provider_id'] ?? 0 ),
			'promotion_notes'     => sanitize_textarea_field( (string) ( $raw['promotion_notes'] ?? '' ) ),
			'active'              => ! empty( $raw['active'] ) ? 1 : 0,
		);
	}

	private function get_promotion_builder_config(): array {
		$defaults = array(
			'include_titles'      => '',
			'exclude_titles'      => '',
			'include_regions'     => '',
			'exclude_regions'     => '',
			'keywords'            => '',
			'budget_daily_cents'  => 0,
			'budget_total_cents'  => 0,
			'pacing_mode'         => 'steady',
			'approval_status'     => 'draft',
			'owner'               => '',
			'audit_notes'         => '',
		);

		$stored = get_option( self::PROMOTION_BUILDER_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $this->sanitize_promotion_builder_config( $stored ) );
	}

	private function sanitize_promotion_builder_config( array $raw ): array {
		$valid_pacing = array( 'steady', 'front_loaded', 'back_loaded' );
		$valid_approval = array( 'draft', 'pending_approval', 'approved', 'paused' );

		$pacing = sanitize_key( (string) ( $raw['pacing_mode'] ?? 'steady' ) );
		$approval = sanitize_key( (string) ( $raw['approval_status'] ?? 'draft' ) );

		return array(
			'include_titles'      => sanitize_text_field( (string) ( $raw['include_titles'] ?? '' ) ),
			'exclude_titles'      => sanitize_text_field( (string) ( $raw['exclude_titles'] ?? '' ) ),
			'include_regions'     => sanitize_text_field( (string) ( $raw['include_regions'] ?? '' ) ),
			'exclude_regions'     => sanitize_text_field( (string) ( $raw['exclude_regions'] ?? '' ) ),
			'keywords'            => sanitize_text_field( (string) ( $raw['keywords'] ?? '' ) ),
			'budget_daily_cents'  => max( 0, (int) ( $raw['budget_daily_cents'] ?? 0 ) ),
			'budget_total_cents'  => max( 0, (int) ( $raw['budget_total_cents'] ?? 0 ) ),
			'pacing_mode'         => in_array( $pacing, $valid_pacing, true ) ? $pacing : 'steady',
			'approval_status'     => in_array( $approval, $valid_approval, true ) ? $approval : 'draft',
			'owner'               => sanitize_text_field( (string) ( $raw['owner'] ?? '' ) ),
			'audit_notes'         => sanitize_textarea_field( (string) ( $raw['audit_notes'] ?? '' ) ),
		);
	}

	private function assert_manage_options(): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_die( esc_html__( 'You do not have permission to manage Connect providers.', 'khm-membership' ) );
	}
}