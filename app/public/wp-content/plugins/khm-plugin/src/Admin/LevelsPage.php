<?php
namespace KHM\Admin;

use KHM\Models\MembershipLevel;
use KHM\Services\LevelRepository;
use WP_List_Table;

class LevelsPage {
	public const PAGE_SLUG      = 'khm-levels';
	public const SETTINGS_GROUP = 'khm_membership_levels';

	private LevelRepository $repository;

	/**
	 * Allowed period values for billing/expiration.
	 *
	 * @var array<int,string>
	 */
	private array $periods = [ 'Day', 'Week', 'Month', 'Year' ];
	private const PRICE_ID_REGEX = '/^price_[A-Za-z0-9]+$/';

	public function __construct( ?LevelRepository $repository = null ) {
		$this->repository = $repository ?: new LevelRepository();
	}

	public function register(): void {
		add_action( 'admin_post_khm_save_membership_level', [ $this, 'handle_save_request' ] );
		add_action( 'admin_post_khm_delete_membership_level', [ $this, 'handle_delete_request' ] );
		error_log('LevelsPage::register() - Actions registered');
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage membership levels.', 'khm-membership' ) );
		}

		$requested_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'add' === $requested_action ) {
			$this->render_add_page();
			return;
		}

		if ( 'edit' === $requested_action ) {
			$this->render_edit_page();
			return;
		}

		$this->render_list_page();
	}

	private function render_list_page(): void {
		$levels = $this->repository->all( true );
		$list_table = new LevelsListTable( $this->repository, $levels, self::PAGE_SLUG );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Membership Levels', 'khm-membership' ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=add' ) ) . '" class="page-title-action">' . esc_html__( 'Add New Level', 'khm-membership' ) . '</a>';
		echo '<hr class="wp-header-end">';

		// Load any persisted notices from transient, then display
		$this->clear_persisted_notices();
		settings_errors( self::SETTINGS_GROUP );

		$list_table->prepare_items();
		$this->render_table( $list_table );

		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=add' ) ) . '" class="button button-primary">' . esc_html__( 'Create New Membership Level', 'khm-membership' ) . '</a></p>';

		echo '</div>';
	}

	private function render_add_page(): void {
		$form_state = $this->consume_form_state();
		$old_input = isset( $form_state['data'] ) && is_array( $form_state['data'] ) ? $form_state['data'] : [];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Add New Membership Level', 'khm-membership' ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">&larr; ' . esc_html__( 'Back to Levels', 'khm-membership' ) . '</a></p>';

		settings_errors( self::SETTINGS_GROUP );

		$this->render_form( null, $old_input );

		echo '</div>';
	}

	private function render_edit_page(): void {
		$form_state = $this->consume_form_state();
		$requested_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		$edit_id = isset( $form_state['level_id'] ) ? (int) $form_state['level_id'] : $requested_id;
		$edit_level = $edit_id ? $this->repository->get( $edit_id, true ) : null;

		if ( ! $edit_level ) {
			$this->add_notice( 'level_not_found', __( 'Membership level not found.', 'khm-membership' ), 'error' );
			$this->persist_notices();
			wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			exit;
		}

		$old_input = isset( $form_state['data'] ) && is_array( $form_state['data'] ) ? $form_state['data'] : [];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Edit Membership Level', 'khm-membership' ) . ': ' . esc_html( $edit_level->name ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">&larr; ' . esc_html__( 'Back to Levels', 'khm-membership' ) . '</a></p>';

		settings_errors( self::SETTINGS_GROUP );

		$this->render_form( $edit_level, $old_input );

		echo '</div>';
	}

	private function render_table( LevelsListTable $table ): void {
		echo '<h2 class="screen-reader-text">' . esc_html__( 'Levels List', 'khm-membership' ) . '</h2>';
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';
		$table->display();
		echo '</form>';
	}

	private function render_form( ?MembershipLevel $level, array $old_input = [] ): void {
        $defaults = [
            'level_id'           => $level ? (int) $level->id : 0,
            'name'               => $level ? $level->name : '',
            'description'        => $level ? $level->description : '',
            'confirmation'       => $level ? $level->confirmation : '',
            'initial_payment'    => $level ? (float) $level->initial_payment : 0.0,
			'billing_amount'     => $level ? (float) $level->billing_amount : 0.0,
			'cycle_number'       => $level ? (int) $level->cycle_number : 0,
			'cycle_period'       => $level ? $level->cycle_period : 'Month',
			'billing_limit'      => $level ? (int) $level->billing_limit : 0,
            'trial_amount'       => $level ? (float) $level->trial_amount : 0.0,
            'trial_limit'        => $level ? (int) $level->trial_limit : 0,
            'allow_signups'      => $level ? (int) $level->allow_signups : 1,
            'expiration_number'  => $level ? (int) $level->expiration_number : 0,
            'expiration_period'  => $level ? $level->expiration_period : 'Month',
            'custom_capabilities'=> $level && ! empty( $level->meta['custom_capabilities'] ) ? implode( "\n", (array) $level->meta['custom_capabilities'] ) : '',
            'monthly_credits'    => $level ? (int) ($level->meta['monthly_credits'] ?? 0) : 0,
            'stripe_price_id'    => $level && ! empty( $level->meta['stripe_price_id'] ) ? $level->meta['stripe_price_id'] : '',
			'khm_level_meta'     => $level && ! empty( $level->meta['khm_level_meta'] ) ? $level->meta['khm_level_meta'] : '',
        ];

		$data = wp_parse_args( $old_input, $defaults );
		$level_meta_value = $data['khm_level_meta'];
		if ( is_array( $level_meta_value ) ) {
			$level_meta_value = wp_json_encode( $level_meta_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}
		if ( ! is_string( $level_meta_value ) ) {
			$level_meta_value = '';
		}

		$is_editing = (bool) $data['level_id'];

		$level_meta = $this->normalize_level_meta( $data['khm_level_meta'] );
		$features = [
			'credits'       => (bool) ( $old_input['features']['credits'] ?? ( $level_meta['features']['credits'] ?? false ) ),
			'gifting'       => (bool) ( $old_input['features']['gifting'] ?? ( $level_meta['features']['gifting'] ?? false ) ),
			'portal'        => (bool) ( $old_input['features']['portal'] ?? ( $level_meta['features']['portal'] ?? false ) ),
			'sponsor'       => (bool) ( $old_input['features']['sponsor'] ?? ( $level_meta['features']['sponsor'] ?? false ) ),
			'forum'         => (bool) ( $old_input['features']['forum'] ?? ( $level_meta['features']['forum'] ?? false ) ),
			'founder_badge' => (bool) ( $old_input['features']['founder_badge'] ?? ( $level_meta['features']['founder_badge'] ?? false ) ),
		];
		$commerce = [
			'allow_promotion_codes'  => (bool) ( $old_input['commerce']['allow_promotion_codes'] ?? ( $level_meta['commerce']['allow_promotion_codes'] ?? false ) ),
			'allow_guest_checkout'   => (bool) ( $old_input['commerce']['allow_guest_checkout'] ?? ( $level_meta['commerce']['allow_guest_checkout'] ?? false ) ),
			'trial_days'             => (int) ( $old_input['commerce']['trial_days'] ?? ( $level_meta['commerce']['trial_days'] ?? 0 ) ),
			'default_billing_interval' => (string) ( $old_input['commerce']['default_billing_interval'] ?? ( $level_meta['commerce']['default_billing_interval'] ?? 'monthly' ) ),
		];
		$presentation = [
			'template'        => (string) ( $old_input['presentation']['template'] ?? ( $level_meta['presentation']['template'] ?? 'compact' ) ),
			'cta_text'        => (string) ( $old_input['presentation']['cta_text'] ?? ( $level_meta['presentation']['cta_text'] ?? '' ) ),
			'price_inclusive' => (bool) ( $old_input['presentation']['price_inclusive'] ?? ( $level_meta['presentation']['price_inclusive'] ?? true ) ),
		];
		$availability = [
			'start_at' => (string) ( $old_input['availability']['start_at'] ?? ( $level_meta['availability']['start_at'] ?? '' ) ),
			'end_at'   => (string) ( $old_input['availability']['end_at'] ?? ( $level_meta['availability']['end_at'] ?? '' ) ),
		];
		$credits_monthly = (int) ( $old_input['credits_monthly'] ?? ( $level_meta['credits']['monthly'] ?? $data['monthly_credits'] ) );
		$advanced_meta_value = (string) ( $old_input['khm_level_meta_advanced'] ?? '' );
		if ( '' === $advanced_meta_value ) {
			$advanced_meta_value = wp_json_encode( $level_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		$price_rows = $this->build_price_rows( $old_input['stripe_prices'] ?? null, $level_meta['stripe_price_ids'] ?? [], (string) $data['stripe_price_id'] );
		if ( empty( $price_rows ) ) {
			$price_rows[] = [
				'currency'        => strtoupper( (string) get_option( 'khm_currency', 'GBP' ) ),
				'interval'        => 'monthly',
				'price_id'        => '',
				'custom_currency' => '',
			];
		}
		
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="khm-level-form">';
		wp_nonce_field( 'khm_save_membership_level', 'khm_membership_level_nonce' );
		echo '<input type="hidden" name="action" value="khm_save_membership_level">';
		echo '<input type="hidden" name="level_id" value="' . esc_attr( (int) $data['level_id'] ) . '">';

		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row"><label for="khm-level-name">' . esc_html__( 'Name', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="khm-level-name" name="name" value="' . esc_attr( $data['name'] ) . '" required></td></tr>';

		echo '<tr><th scope="row"><label for="khm-level-description">' . esc_html__( 'Description', 'khm-membership' ) . '</label></th>';
		echo '<td><textarea id="khm-level-description" name="description" rows="5" class="large-text">' . esc_textarea( $data['description'] ) . '</textarea></td></tr>';

		echo '<tr><th scope="row"><label for="khm-level-confirmation">' . esc_html__( 'Confirmation Message', 'khm-membership' ) . '</label></th>';
		echo '<td><textarea id="khm-level-confirmation" name="confirmation" rows="4" class="large-text">' . esc_textarea( $data['confirmation'] ) . '</textarea></td></tr>';

		echo '<tr><th scope="row"><label for="khm-initial-payment">' . esc_html__( 'Initial Payment', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" step="0.01" min="0" id="khm-initial-payment" name="initial_payment" value="' . esc_attr( $data['initial_payment'] ) . '"></td></tr>';

		echo '<tr><th scope="row"><label for="khm-billing-amount">' . esc_html__( 'Billing Amount', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" step="0.01" min="0" id="khm-billing-amount" name="billing_amount" value="' . esc_attr( $data['billing_amount'] ) . '">';
		echo '<p class="description">' . esc_html__( 'Set to 0 for one-time payments.', 'khm-membership' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="khm-cycle-number">' . esc_html__( 'Billing Cycle', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" min="0" id="khm-cycle-number" name="cycle_number" value="' . esc_attr( $data['cycle_number'] ) . '"> ';
		echo '<select name="cycle_period" id="khm-cycle-period">';
		foreach ( $this->periods as $period ) {
			echo '<option value="' . esc_attr( $period ) . '"' . selected( $data['cycle_period'], $period, false ) . '>' . esc_html( $period ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="khm-billing-limit">' . esc_html__( 'Billing Limit', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" min="0" id="khm-billing-limit" name="billing_limit" value="' . esc_attr( $data['billing_limit'] ) . '">';
		echo '<p class="description">' . esc_html__( 'Number of payments before billing stops. Leave 0 for ongoing.', 'khm-membership' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="khm-trial-amount">' . esc_html__( 'Trial Amount', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" step="0.01" min="0" id="khm-trial-amount" name="trial_amount" value="' . esc_attr( $data['trial_amount'] ) . '"></td></tr>';

		echo '<tr><th scope="row"><label for="khm-trial-limit">' . esc_html__( 'Trial Cycles', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" min="0" id="khm-trial-limit" name="trial_limit" value="' . esc_attr( $data['trial_limit'] ) . '"></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Allow Signups', 'khm-membership' ) . '</th>';
		echo '<td><label><input type="checkbox" name="allow_signups" value="1"' . checked( (int) $data['allow_signups'], 1, false ) . '> ' . esc_html__( 'Users can sign up for this level', 'khm-membership' ) . '</label></td></tr>';

        echo '<tr><th scope="row"><label for="khm-expiration-number">' . esc_html__( 'Expiration', 'khm-membership' ) . '</label></th>';
        echo '<td><input type="number" min="0" id="khm-expiration-number" name="expiration_number" value="' . esc_attr( $data['expiration_number'] ) . '"> ';
        echo '<select name="expiration_period" id="khm-expiration-period">';
        foreach ( $this->periods as $period ) {
            echo '<option value="' . esc_attr( $period ) . '"' . selected( $data['expiration_period'], $period, false ) . '>' . esc_html( $period ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Leave number 0 for no expiration.', 'khm-membership' ) . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="khm-level-capabilities">' . esc_html__( 'Custom Capabilities', 'khm-membership' ) . '</label></th>';
        echo '<td><textarea id="khm-level-capabilities" name="custom_capabilities" rows="4" class="large-text">' . esc_textarea( $data['custom_capabilities'] ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Optional. Enter one capability per line to grant while this membership is active.', 'khm-membership' ) . '</p></td></tr>';

	        echo '</table>';

			echo '<h2 style="margin-top:24px;">' . esc_html__( 'KHM: Commerce & Features', 'khm-membership' ) . '</h2>';
			echo '<div class="khm-level-panel" style="background:#fff;border:1px solid #dcdcde;padding:16px;border-radius:6px;">';

			$policy_url = plugins_url( 'docs/API_VERSIONING_POLICY.md', dirname( __DIR__, 2 ) . '/khm-plugin.php' );
			echo '<h3>' . esc_html__( 'Stripe Pricing', 'khm-membership' ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'Paste the Stripe Price ID. Click Validate to confirm.', 'khm-membership' ) . '</p>';
			echo '<table class="widefat striped" style="margin-top:8px;">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Currency', 'khm-membership' ) . '</th>';
			echo '<th>' . esc_html__( 'Interval', 'khm-membership' ) . '</th>';
			echo '<th>' . esc_html__( 'Price ID', 'khm-membership' ) . '</th>';
			echo '<th>' . esc_html__( 'Actions', 'khm-membership' ) . '</th>';
			echo '</tr></thead><tbody id="khm-price-rows">';
			foreach ( $price_rows as $index => $row ) {
				$currency = strtoupper( (string) ( $row['currency'] ?? '' ) );
				$interval = (string) ( $row['interval'] ?? 'monthly' );
				$price_id = (string) ( $row['price_id'] ?? '' );
				$custom_currency = (string) ( $row['custom_currency'] ?? '' );
				$is_custom_currency = ! in_array( $currency, [ 'GBP', 'USD' ], true );
				echo '<tr class="khm-price-row" data-index="' . esc_attr( (string) $index ) . '">';
				echo '<td>';
				echo '<label class="screen-reader-text" for="khm-price-currency-' . esc_attr( (string) $index ) . '">' . esc_html__( 'Currency', 'khm-membership' ) . '</label>';
				echo '<select name="stripe_prices[' . esc_attr( (string) $index ) . '][currency]" id="khm-price-currency-' . esc_attr( (string) $index ) . '" class="khm-price-currency">';
				echo '<option value="GBP"' . selected( $currency, 'GBP', false ) . '>GBP</option>';
				echo '<option value="USD"' . selected( $currency, 'USD', false ) . '>USD</option>';
				echo '<option value="custom"' . selected( $is_custom_currency ? 'custom' : $currency, 'custom', false ) . '>' . esc_html__( 'Add new', 'khm-membership' ) . '</option>';
				echo '</select>';
				echo '<input type="text" name="stripe_prices[' . esc_attr( (string) $index ) . '][custom_currency]" class="khm-price-currency-custom" placeholder="EUR" value="' . esc_attr( $is_custom_currency ? $currency : $custom_currency ) . '" style="margin-top:6px;' . ( $is_custom_currency ? '' : 'display:none;' ) . '">';
				echo '</td>';
				echo '<td>';
				echo '<label class="screen-reader-text" for="khm-price-interval-' . esc_attr( (string) $index ) . '">' . esc_html__( 'Interval', 'khm-membership' ) . '</label>';
				echo '<select name="stripe_prices[' . esc_attr( (string) $index ) . '][interval]" id="khm-price-interval-' . esc_attr( (string) $index ) . '">';
				echo '<option value="monthly"' . selected( $interval, 'monthly', false ) . '>' . esc_html__( 'Monthly', 'khm-membership' ) . '</option>';
				echo '<option value="annual"' . selected( $interval, 'annual', false ) . '>' . esc_html__( 'Annual', 'khm-membership' ) . '</option>';
				echo '</select>';
				echo '</td>';
				echo '<td>';
				echo '<label class="screen-reader-text" for="khm-price-id-' . esc_attr( (string) $index ) . '">' . esc_html__( 'Price ID', 'khm-membership' ) . '</label>';
				echo '<input type="text" class="regular-text khm-price-id" id="khm-price-id-' . esc_attr( (string) $index ) . '" name="stripe_prices[' . esc_attr( (string) $index ) . '][price_id]" value="' . esc_attr( $price_id ) . '" placeholder="price_xxxxxxxxxxxxx">';
				echo '<div class="khm-price-badges" style="margin-top:6px;">';
				echo '<span class="khm-price-mode-badge" style="display:none;padding:2px 6px;border-radius:3px;background:#f6f7f7;"></span>';
				echo '<a href="#" class="khm-stripe-price-link" target="_blank" rel="noopener" style="display:none;margin-left:8px;">' . esc_html__( 'Open Price in Stripe', 'khm-membership' ) . '</a>';
				echo '</div>';
				echo '</td>';
				echo '<td>';
				echo '<button type="button" class="button khm-validate-price">' . esc_html__( 'Validate', 'khm-membership' ) . '</button>';
				echo '<button type="button" class="button-link-delete khm-remove-price" style="margin-left:8px;">' . esc_html__( 'Remove', 'khm-membership' ) . '</button>';
				echo '<div class="khm-price-validation-result" style="margin-top:6px;"></div>';
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '<p><button type="button" class="button" id="khm-add-price">' . esc_html__( 'Add another price', 'khm-membership' ) . '</button></p>';
			echo '<p class="description">' . wp_kses_post( sprintf(
				__( 'Policy: <a href="%1$s" target="_blank" rel="noopener">v1/v2 API versioning</a>.', 'khm-membership' ),
				esc_url( $policy_url )
			) ) . '</p>';

			echo '<hr style="margin:20px 0;">';
			echo '<h3>' . esc_html__( 'Features', 'khm-membership' ) . '</h3>';
			echo '<div class="khm-feature-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">';
			$feature_help = [
				'credits' => __( 'Enables credit usage for this level.', 'khm-membership' ),
				'gifting' => __( 'Allows gifting membership to others.', 'khm-membership' ),
				'portal' => __( 'Enables the customer portal link.', 'khm-membership' ),
				'sponsor' => __( 'Shows sponsor co-branding.', 'khm-membership' ),
				'forum' => __( 'Grants forum access.', 'khm-membership' ),
				'founder_badge' => __( 'Displays a founder badge on profiles.', 'khm-membership' ),
			];
			foreach ( $features as $key => $enabled ) {
				$label = ucwords( str_replace( '_', ' ', $key ) );
				echo '<label style="display:flex;align-items:center;gap:8px;">';
				echo '<input type="checkbox" name="features[' . esc_attr( $key ) . ']" value="1"' . checked( $enabled, true, false ) . '>';
				echo '<span>' . esc_html__( 'Enable ', 'khm-membership' ) . esc_html( $label ) . '</span>';
				echo '<span class="dashicons dashicons-editor-help" title="' . esc_attr( $feature_help[ $key ] ?? '' ) . '"></span>';
				echo '</label>';
			}
			echo '</div>';

			echo '<hr style="margin:20px 0;">';
			echo '<h3>' . esc_html__( 'Commerce & Trials', 'khm-membership' ) . '</h3>';
			echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">';
			echo '<label><input type="checkbox" name="commerce[allow_promotion_codes]" value="1"' . checked( $commerce['allow_promotion_codes'], true, false ) . '> ' . esc_html__( 'Allow promotion codes', 'khm-membership' ) . '</label>';
			echo '<label><input type="checkbox" name="commerce[allow_guest_checkout]" value="1"' . checked( $commerce['allow_guest_checkout'], true, false ) . '> ' . esc_html__( 'Allow guest checkout', 'khm-membership' ) . '</label>';
			echo '<label>' . esc_html__( 'Default trial days', 'khm-membership' ) . '<br><input type="number" min="0" name="commerce[trial_days]" value="' . esc_attr( (string) $commerce['trial_days'] ) . '" style="max-width:120px;"></label>';
			echo '<label>' . esc_html__( 'Default billing interval', 'khm-membership' ) . '<br>';
			echo '<select name="commerce[default_billing_interval]">';
			echo '<option value="monthly"' . selected( $commerce['default_billing_interval'], 'monthly', false ) . '>' . esc_html__( 'Monthly', 'khm-membership' ) . '</option>';
			echo '<option value="annual"' . selected( $commerce['default_billing_interval'], 'annual', false ) . '>' . esc_html__( 'Annual', 'khm-membership' ) . '</option>';
			echo '</select></label>';
			echo '</div>';

			echo '<hr style="margin:20px 0;">';
			echo '<h3>' . esc_html__( 'Presentation Defaults', 'khm-membership' ) . '</h3>';
			echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;align-items:start;">';
			echo '<label>' . esc_html__( 'Template', 'khm-membership' ) . '<br>';
			echo '<select name="presentation[template]" id="khm-presentation-template">';
			echo '<option value="compact"' . selected( $presentation['template'], 'compact', false ) . '>' . esc_html__( 'Compact', 'khm-membership' ) . '</option>';
			echo '<option value="full"' . selected( $presentation['template'], 'full', false ) . '>' . esc_html__( 'Full', 'khm-membership' ) . '</option>';
			echo '<option value="promo"' . selected( $presentation['template'], 'promo', false ) . '>' . esc_html__( 'Promo', 'khm-membership' ) . '</option>';
			echo '</select></label>';
			echo '<label>' . esc_html__( 'CTA text', 'khm-membership' ) . '<br><input type="text" class="regular-text" name="presentation[cta_text]" id="khm-presentation-cta" value="' . esc_attr( $presentation['cta_text'] ) . '"></label>';
			echo '<fieldset><legend>' . esc_html__( 'Price display', 'khm-membership' ) . '</legend>';
			echo '<label><input type="radio" name="presentation[price_inclusive]" value="1"' . checked( $presentation['price_inclusive'], true, false ) . '> ' . esc_html__( 'Inclusive of tax', 'khm-membership' ) . '</label><br>';
			echo '<label><input type="radio" name="presentation[price_inclusive]" value="0"' . checked( $presentation['price_inclusive'], false, false ) . '> ' . esc_html__( 'Exclusive of tax', 'khm-membership' ) . '</label>';
			echo '</fieldset>';
			echo '</div>';
			echo '<div id="khm-presentation-preview" style="margin-top:12px;padding:12px;border:1px dashed #c3c4c7;border-radius:6px;background:#f6f7f7;">';
			echo '<strong class="khm-preview-cta">' . esc_html( $presentation['cta_text'] ?: __( 'Join now', 'khm-membership' ) ) . '</strong>';
			echo '<div class="khm-preview-price" style="margin-top:6px;color:#50575e;"></div>';
			echo '</div>';

			echo '<hr style="margin:20px 0;">';
			echo '<h3>' . esc_html__( 'Availability & Credits', 'khm-membership' ) . '</h3>';
			echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">';
			echo '<label>' . esc_html__( 'Start date', 'khm-membership' ) . '<br><input type="date" name="availability[start_at]" value="' . esc_attr( $availability['start_at'] ) . '"></label>';
			echo '<label>' . esc_html__( 'End date', 'khm-membership' ) . '<br><input type="date" name="availability[end_at]" value="' . esc_attr( $availability['end_at'] ) . '"></label>';
			echo '<label>' . esc_html__( 'Monthly credits', 'khm-membership' ) . '<br><input type="number" min="0" name="credits_monthly" value="' . esc_attr( (string) $credits_monthly ) . '"></label>';
			echo '</div>';

			echo '<hr style="margin:20px 0;">';
			echo '<h3>' . esc_html__( 'Admin Tools', 'khm-membership' ) . '</h3>';
			echo '<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">';
			if ( $is_editing ) {
				echo '<button type="submit" name="khm_clone_level" value="1" class="button">' . esc_html__( 'Clone Level', 'khm-membership' ) . '</button>';
			}
			echo '<button type="button" class="button" id="khm-export-json">' . esc_html__( 'Export JSON', 'khm-membership' ) . '</button>';
			echo '<button type="button" class="button" id="khm-import-json">' . esc_html__( 'Import JSON', 'khm-membership' ) . '</button>';
			echo '<button type="button" class="button" id="khm-validate-all-prices">' . esc_html__( 'Validate All Prices', 'khm-membership' ) . '</button>';
			echo '</div>';

			echo '<details style="margin-top:20px;">';
			echo '<summary><strong>' . esc_html__( 'KHM Level Meta (Advanced)', 'khm-membership' ) . '</strong></summary>';
			echo '<p class="description">' . esc_html__( 'Advanced users can edit structured JSON directly. Saving here overrides the form values.', 'khm-membership' ) . '</p>';
			echo '<input type="hidden" name="khm_level_meta_mode" id="khm-level-meta-mode" value="form">';
			echo '<textarea id="khm-level-meta-advanced" name="khm_level_meta_advanced" rows="12" class="large-text code" style="margin-top:8px;">' . esc_textarea( $advanced_meta_value ) . '</textarea>';
			echo '<p>';
			echo '<button type="button" class="button" id="khm-revert-advanced">' . esc_html__( 'Revert to form values', 'khm-membership' ) . '</button>';
			echo '</p>';
			echo '</details>';

			echo '</div>';

			echo '<div id="khm-json-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);z-index:100000;">';
			echo '<div style="background:#fff;max-width:640px;margin:10% auto;padding:16px;border-radius:6px;box-shadow:0 2px 12px rgba(0,0,0,0.2);">';
			echo '<h3>' . esc_html__( 'KHM Level Meta JSON', 'khm-membership' ) . '</h3>';
			echo '<textarea id="khm-json-modal-textarea" rows="12" class="large-text code"></textarea>';
			echo '<p style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">';
			echo '<button type="button" class="button" id="khm-json-modal-close">' . esc_html__( 'Close', 'khm-membership' ) . '</button>';
			echo '<button type="button" class="button button-primary" id="khm-json-modal-apply">' . esc_html__( 'Import JSON', 'khm-membership' ) . '</button>';
			echo '</p>';
			echo '</div></div>';

		echo '<p class="submit">';
		echo '<input type="submit" class="button button-primary" value="' . esc_attr__( 'Save Membership Level', 'khm-membership' ) . '">';
		echo '</p>';

		$this->render_inline_script();
		echo '</form>';
	}

	private function render_inline_script(): void {
		$nonce = wp_create_nonce( 'khm_validate_stripe_price' );
		$ajax_url = admin_url( 'admin-ajax.php' );
		$stripe_secret = (string) get_option( 'khm_stripe_secret_key', '' );
		$stripe_mode = 'unknown';
		if ( $stripe_secret !== '' ) {
			if ( str_starts_with( $stripe_secret, 'sk_test_' ) ) {
				$stripe_mode = 'test';
			} elseif ( str_starts_with( $stripe_secret, 'sk_live_' ) ) {
				$stripe_mode = 'live';
			}
		}

		echo '<script>';
		echo 'document.addEventListener("DOMContentLoaded",function(){';
		echo 'var stripeMode="' . esc_js( $stripe_mode ) . '";';
		echo 'var priceRegex=/^price_[A-Za-z0-9]+$/;';
		echo 'var rowsContainer=document.getElementById("khm-price-rows");';
		echo 'function priceDashboardBase(livemode){if(livemode===true){return "https://dashboard.stripe.com/prices/";}if(livemode===false){return "https://dashboard.stripe.com/test/prices/";}return stripeMode==="test"?"https://dashboard.stripe.com/test/prices/":"https://dashboard.stripe.com/prices/";}';
		echo 'function currencySymbol(code){if(code==="GBP"){return "£";}if(code==="USD"){return "$";}return code+" ";}';
		echo 'function buildMetaFromForm(){var meta={};';
		echo 'var rows=document.querySelectorAll(".khm-price-row");var priceMap={};';
		echo 'rows.forEach(function(row){var currencySelect=row.querySelector(".khm-price-currency");var customInput=row.querySelector(".khm-price-currency-custom");var interval=row.querySelector("select[name*=\"[interval]\"]");var priceInput=row.querySelector(".khm-price-id");var currency=currencySelect?currencySelect.value:"";if(currency==="custom"&&customInput){currency=customInput.value.trim();}currency=currency.toUpperCase();var intervalVal=interval?interval.value:"monthly";var price=priceInput?priceInput.value.trim():"";if(currency&&intervalVal&&price){if(!priceMap[currency]){priceMap[currency]={};}priceMap[currency][intervalVal]=price;}});';
		echo 'if(Object.keys(priceMap).length){meta.stripe_price_ids=priceMap;}';
		echo 'var features={};document.querySelectorAll("input[name^=\"features[\"]").forEach(function(input){var key=input.name.match(/features\\[(.+)\\]/);if(key){features[key[1]]=input.checked;}});if(Object.keys(features).length){meta.features=features;}';
		echo 'var commerce={};["allow_promotion_codes","allow_guest_checkout","trial_days","default_billing_interval"].forEach(function(key){var field=document.querySelector("[name=\"commerce["+key+"]\"]");if(!field){return;}if(field.type==="checkbox"){commerce[key]=field.checked;}else{commerce[key]=field.value;}});if(Object.keys(commerce).length){meta.commerce=commerce;}';
		echo 'var presentation={};["template","cta_text"].forEach(function(key){var field=document.querySelector("[name=\"presentation["+key+"]\"]");if(field){presentation[key]=field.value;}});var priceInclusive=document.querySelector("input[name=\"presentation[price_inclusive]\"]:checked");if(priceInclusive){presentation.price_inclusive=priceInclusive.value==="1";}if(Object.keys(presentation).length){meta.presentation=presentation;}';
		echo 'var availability={};["start_at","end_at"].forEach(function(key){var field=document.querySelector("[name=\"availability["+key+"]\"]");if(field&&field.value){availability[key]=field.value;}});if(Object.keys(availability).length){meta.availability=availability;}';
		echo 'var creditsMonthly=document.querySelector("input[name=\"credits_monthly\"]");if(creditsMonthly&&creditsMonthly.value!==""){meta.credits={monthly:parseInt(creditsMonthly.value,10)||0};}';
		echo 'return meta;}';
		echo 'function updatePreview(){var cta=document.getElementById("khm-presentation-cta");var preview=document.getElementById("khm-presentation-preview");if(!preview){return;}var ctaText=cta&&cta.value?cta.value:"' . esc_js( __( 'Join now', 'khm-membership' ) ) . '";preview.querySelector(".khm-preview-cta").textContent=ctaText;var billing=document.getElementById("khm-billing-amount");var amount=billing&&billing.value?parseFloat(billing.value):0;var firstRow=document.querySelector(".khm-price-row");var currency=firstRow?firstRow.querySelector(".khm-price-currency").value:"GBP";if(currency==="custom"&&firstRow){var custom=firstRow.querySelector(".khm-price-currency-custom");currency=custom&&custom.value?custom.value.toUpperCase():"GBP";}var intervalField=document.querySelector("select[name=\"commerce[default_billing_interval]\"]");var interval=intervalField?intervalField.value:"monthly";var suffix=interval==="annual"?" / yr":" / mo";preview.querySelector(".khm-preview-price").textContent=currencySymbol(currency)+amount.toFixed(2)+suffix;};';
		echo 'function updateRowBadges(row,livemode){var badge=row.querySelector(".khm-price-mode-badge");var link=row.querySelector(".khm-stripe-price-link");var priceInput=row.querySelector(".khm-price-id");if(!badge||!priceInput){return;}var price=priceInput.value.trim();if(priceRegex.test(price)){badge.style.display="inline-block";badge.textContent=(livemode===false||stripeMode==="test")?"' . esc_js( __( 'Test', 'khm-membership' ) ) . '":"' . esc_js( __( 'Live', 'khm-membership' ) ) . '";if(link){link.href=priceDashboardBase(livemode)+encodeURIComponent(price);link.style.display="inline";}}else{badge.style.display="none";if(link){link.style.display="none";}}}';
		echo 'function validateRow(row){var priceInput=row.querySelector(".khm-price-id");var result=row.querySelector(".khm-price-validation-result");if(result){result.textContent="' . esc_js( __( 'Validating...', 'khm-membership' ) ) . '";result.style.color="#50575e";}var price=priceInput?priceInput.value.trim():"";var data=new URLSearchParams();data.append("action","khm_validate_stripe_price");data.append("nonce","' . esc_js( $nonce ) . '");data.append("price_id",price);return fetch("' . esc_js( $ajax_url ) . '",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:data.toString()}).then(function(r){return r.json();}).then(function(res){if(!result){return;}if(res&&res.success){var msg=res.data&&res.data.message?res.data.message:"' . esc_js( __( 'Valid price ID.', 'khm-membership' ) ) . '";result.textContent=msg;result.style.color="#0a0";updateRowBadges(row,res.data?res.data.livemode:null);}else{var msg=res&&res.data&&res.data.message?res.data.message:"' . esc_js( __( 'Invalid price ID.', 'khm-membership' ) ) . '";result.textContent=msg;result.style.color="#a00";updateRowBadges(row,null);}return res;}).catch(function(){if(result){result.textContent="' . esc_js( __( 'Validation failed.', 'khm-membership' ) ) . '";result.style.color="#a00";}updateRowBadges(row,null);});}';
		echo 'function bindRow(row){var currencySelect=row.querySelector(".khm-price-currency");var customInput=row.querySelector(".khm-price-currency-custom");if(currencySelect){currencySelect.addEventListener("change",function(){if(currencySelect.value==="custom"){customInput.style.display="inline-block";customInput.focus();}else{customInput.style.display="none";}updatePreview();});}if(customInput){customInput.addEventListener("input",function(){updatePreview();});}var priceInput=row.querySelector(".khm-price-id");if(priceInput){priceInput.addEventListener("input",function(){updateRowBadges(row,null);});}var validateBtn=row.querySelector(".khm-validate-price");if(validateBtn){validateBtn.addEventListener("click",function(e){e.preventDefault();validateRow(row);});}var removeBtn=row.querySelector(".khm-remove-price");if(removeBtn){removeBtn.addEventListener("click",function(){row.remove();updatePreview();});}updateRowBadges(row,null);}';
		echo 'document.querySelectorAll(".khm-price-row").forEach(function(row){bindRow(row);});';
		echo 'var addBtn=document.getElementById("khm-add-price");if(addBtn&&rowsContainer){addBtn.addEventListener("click",function(){var index=rowsContainer.querySelectorAll(".khm-price-row").length;var tr=document.createElement("tr");tr.className="khm-price-row";tr.innerHTML=' . wp_json_encode(
			'<td><label class="screen-reader-text">Currency</label><select class="khm-price-currency" name="stripe_prices[__index__][currency]"><option value="GBP">GBP</option><option value="USD">USD</option><option value="custom">Add new</option></select><input type="text" name="stripe_prices[__index__][custom_currency]" class="khm-price-currency-custom" placeholder="EUR" style="margin-top:6px;display:none;"></td><td><label class="screen-reader-text">Interval</label><select name="stripe_prices[__index__][interval]"><option value="monthly">Monthly</option><option value="annual">Annual</option></select></td><td><label class="screen-reader-text">Price ID</label><input type="text" class="regular-text khm-price-id" name="stripe_prices[__index__][price_id]" placeholder="price_xxxxxxxxxxxxx"><div class="khm-price-badges" style="margin-top:6px;"><span class="khm-price-mode-badge" style="display:none;padding:2px 6px;border-radius:3px;background:#f6f7f7;"></span><a href="#" class="khm-stripe-price-link" target="_blank" rel="noopener" style="display:none;margin-left:8px;">Open Price in Stripe</a></div></td><td><button type="button" class="button khm-validate-price">Validate</button><button type="button" class="button-link-delete khm-remove-price" style="margin-left:8px;">Remove</button><div class="khm-price-validation-result" style="margin-top:6px;"></div></td>'
		) . '.replace(/__index__/g,index);rowsContainer.appendChild(tr);bindRow(tr);updatePreview();});}';
		echo 'var validateAll=document.getElementById("khm-validate-all-prices");if(validateAll){validateAll.addEventListener("click",function(e){e.preventDefault();var rows=Array.from(document.querySelectorAll(".khm-price-row"));rows.reduce(function(p,row){return p.then(function(){return validateRow(row);});},Promise.resolve());});}';
		echo 'var exportBtn=document.getElementById("khm-export-json");var importBtn=document.getElementById("khm-import-json");var modal=document.getElementById("khm-json-modal");var modalTextarea=document.getElementById("khm-json-modal-textarea");var modalClose=document.getElementById("khm-json-modal-close");var modalApply=document.getElementById("khm-json-modal-apply");';
		echo 'function openModal(){if(modal){modal.style.display="block";}}function closeModal(){if(modal){modal.style.display="none";}}';
		echo 'if(exportBtn){exportBtn.addEventListener("click",function(){var meta=buildMetaFromForm();modalTextarea.value=JSON.stringify(meta,null,2);openModal();});}';
		echo 'if(importBtn){importBtn.addEventListener("click",function(){modalTextarea.value="";openModal();});}';
		echo 'if(modalClose){modalClose.addEventListener("click",function(){closeModal();});}';
		echo 'if(modalApply){modalApply.addEventListener("click",function(){var raw=modalTextarea.value;try{var parsed=JSON.parse(raw);var advanced=document.getElementById("khm-level-meta-advanced");var mode=document.getElementById("khm-level-meta-mode");if(advanced){advanced.value=JSON.stringify(parsed,null,2);advanced.dispatchEvent(new Event("input"));}if(mode){mode.value="advanced";}closeModal();}catch(err){alert("' . esc_js( __( 'Invalid JSON. Please fix and try again.', 'khm-membership' ) ) . '");}});}';
		echo 'var advanced=document.getElementById("khm-level-meta-advanced");var mode=document.getElementById("khm-level-meta-mode");if(advanced){advanced.addEventListener("input",function(){if(mode){mode.value="advanced";}});}var revertBtn=document.getElementById("khm-revert-advanced");if(revertBtn){revertBtn.addEventListener("click",function(e){e.preventDefault();var meta=buildMetaFromForm();if(advanced){advanced.value=JSON.stringify(meta,null,2);}if(mode){mode.value="form";}});}';
		echo 'document.querySelectorAll("#khm-presentation-template,#khm-presentation-cta,input[name=\"presentation[price_inclusive]\"],#khm-billing-amount,select[name=\"commerce[default_billing_interval]\"]").forEach(function(el){el.addEventListener("input",updatePreview);el.addEventListener("change",updatePreview);});';
		echo 'updatePreview();';
		echo '});';
		echo '</script>';
	}

	public function handle_save_request(): void {
		error_log('LevelsPage::handle_save_request() called');
		if ( ! current_user_can( 'manage_khm' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage membership levels.', 'khm-membership' ) );
		}

		check_admin_referer( 'khm_save_membership_level', 'khm_membership_level_nonce' );

		$level_id = isset( $_POST['level_id'] ) ? absint( $_POST['level_id'] ) : 0;

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$description  = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
		$confirmation = isset( $_POST['confirmation'] ) ? wp_kses_post( wp_unslash( $_POST['confirmation'] ) ) : '';

		$initial_payment = isset( $_POST['initial_payment'] ) ? (float) wp_unslash( $_POST['initial_payment'] ) : 0.0;
		$billing_amount  = isset( $_POST['billing_amount'] ) ? (float) wp_unslash( $_POST['billing_amount'] ) : 0.0;
		$cycle_number    = isset( $_POST['cycle_number'] ) ? (int) wp_unslash( $_POST['cycle_number'] ) : 0;
		$cycle_period    = isset( $_POST['cycle_period'] ) ? sanitize_text_field( wp_unslash( $_POST['cycle_period'] ) ) : 'Month';
		$billing_limit   = isset( $_POST['billing_limit'] ) ? (int) wp_unslash( $_POST['billing_limit'] ) : 0;

		$trial_amount = isset( $_POST['trial_amount'] ) ? (float) wp_unslash( $_POST['trial_amount'] ) : 0.0;
		$trial_limit  = isset( $_POST['trial_limit'] ) ? (int) wp_unslash( $_POST['trial_limit'] ) : 0;

		$allow_signups = isset( $_POST['allow_signups'] ) ? 1 : 0;

		$expiration_number = isset( $_POST['expiration_number'] ) ? (int) wp_unslash( $_POST['expiration_number'] ) : 0;
		$expiration_period = isset( $_POST['expiration_period'] ) ? sanitize_text_field( wp_unslash( $_POST['expiration_period'] ) ) : 'Month';

        $custom_caps_raw = isset( $_POST['custom_capabilities'] ) ? (string) wp_unslash( $_POST['custom_capabilities'] ) : '';
		$custom_caps = $this->sanitize_capabilities_input( $custom_caps_raw );

		$credits_monthly = isset( $_POST['credits_monthly'] ) ? absint( wp_unslash( $_POST['credits_monthly'] ) ) : 0;

		$stripe_prices = isset( $_POST['stripe_prices'] ) && is_array( $_POST['stripe_prices'] ) ? wp_unslash( $_POST['stripe_prices'] ) : [];
		$features_input = isset( $_POST['features'] ) && is_array( $_POST['features'] ) ? wp_unslash( $_POST['features'] ) : [];
		$commerce_input = isset( $_POST['commerce'] ) && is_array( $_POST['commerce'] ) ? wp_unslash( $_POST['commerce'] ) : [];
		$presentation_input = isset( $_POST['presentation'] ) && is_array( $_POST['presentation'] ) ? wp_unslash( $_POST['presentation'] ) : [];
		$availability_input = isset( $_POST['availability'] ) && is_array( $_POST['availability'] ) ? wp_unslash( $_POST['availability'] ) : [];
		$meta_mode = isset( $_POST['khm_level_meta_mode'] ) ? sanitize_key( wp_unslash( $_POST['khm_level_meta_mode'] ) ) : 'form';
		$raw_level_meta = isset( $_POST['khm_level_meta_advanced'] ) ? wp_unslash( $_POST['khm_level_meta_advanced'] ) : '';
		$raw_level_meta = is_string( $raw_level_meta ) ? trim( $raw_level_meta ) : '';

        $form_data = [
            'level_id'          => $level_id,
            'name'              => $name,
            'description'       => $description,
            'confirmation'      => $confirmation,
            'initial_payment'   => $initial_payment,
            'billing_amount'    => $billing_amount,
			'cycle_number'      => $cycle_number,
			'cycle_period'      => $cycle_period,
			'billing_limit'     => $billing_limit,
			'trial_amount'      => $trial_amount,
            'trial_limit'       => $trial_limit,
            'allow_signups'     => $allow_signups,
            'expiration_number' => $expiration_number,
            'expiration_period' => $expiration_period,
            'custom_capabilities'=> $custom_caps_raw,
            'credits_monthly'   => $credits_monthly,
			'stripe_prices'     => $stripe_prices,
			'features'          => $features_input,
			'commerce'          => $commerce_input,
			'presentation'      => $presentation_input,
			'availability'      => $availability_input,
			'khm_level_meta_mode' => $meta_mode,
			'khm_level_meta_advanced' => $raw_level_meta,
        ];

		if ( '' === $name ) {
			$this->store_form_state( $level_id, $form_data );
			$this->add_notice( 'missing_name', __( 'Membership level name is required.', 'khm-membership' ), 'error' );
			$this->persist_notices();
			$this->redirect_after_save( $level_id );
		}

		if ( $billing_amount > 0 && $cycle_number <= 0 ) {
			$this->store_form_state( $level_id, $form_data );
			$this->add_notice( 'invalid_cycle', __( 'Billing cycle must be greater than 0 when billing amount is set.', 'khm-membership' ), 'error' );
			$this->persist_notices();
			$this->redirect_after_save( $level_id );
		}

		if ( ! in_array( $cycle_period, $this->periods, true ) ) {
			$cycle_period = 'Month';
		}

		if ( ! in_array( $expiration_period, $this->periods, true ) ) {
			$expiration_period = 'Month';
		}

        $payload = [
            'name'              => $name,
            'description'       => $description,
            'confirmation'      => $confirmation,
            'initial_payment'   => $initial_payment,
            'billing_amount'    => $billing_amount,
			'cycle_number'      => $cycle_number,
			'cycle_period'      => $cycle_period,
			'billing_limit'     => $billing_limit,
            'trial_amount'      => $trial_amount,
            'trial_limit'       => $trial_limit,
            'allow_signups'     => $allow_signups,
            'expiration_number' => $expiration_number,
            'expiration_period' => $expiration_period,
        ];

        
		$validated_price_id = '';
		$price_map = [];
		if ( ! empty( $stripe_prices ) ) {
			foreach ( $stripe_prices as $row_index => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$currency = isset( $row['currency'] ) ? sanitize_text_field( (string) $row['currency'] ) : '';
				$custom_currency = isset( $row['custom_currency'] ) ? sanitize_text_field( (string) $row['custom_currency'] ) : '';
				if ( 'custom' === $currency ) {
					$currency = $custom_currency;
				}
				$currency = strtoupper( trim( $currency ) );
				$interval = isset( $row['interval'] ) ? sanitize_key( (string) $row['interval'] ) : 'monthly';
				$price_id = isset( $row['price_id'] ) ? sanitize_text_field( (string) $row['price_id'] ) : '';
				if ( '' === $currency || '' === $price_id ) {
					continue;
				}
				if ( ! preg_match( self::PRICE_ID_REGEX, $price_id ) ) {
					error_log( 'KHM Levels: Invalid Stripe Price ID "' . $price_id . '" for level ' . (int) $level_id );
					$this->store_form_state( $level_id, $form_data );
					$this->add_notice( 'invalid_stripe_price', __( 'Invalid Stripe Price ID format. Must start with "price_" followed by alphanumeric characters.', 'khm-membership' ), 'error' );
					$this->persist_notices();
					$this->redirect_after_save( $level_id );
				}
				if ( '' === $validated_price_id ) {
					$validated_price_id = $price_id;
				}
				$price_map[ $currency ][ $interval ] = $price_id;
			}
		}

		$sanitized_level_meta = [];
		if ( 'advanced' === $meta_mode ) {
			$sanitized_level_meta = $this->sanitize_level_meta( $raw_level_meta );
			if ( null === $sanitized_level_meta ) {
				$this->store_form_state( $level_id, $form_data );
				$this->add_notice( 'invalid_level_meta', __( 'KHM Level Meta must be valid JSON.', 'khm-membership' ), 'error' );
				$this->persist_notices();
				$this->redirect_after_save( $level_id );
			}
		} else {
			$sanitized_level_meta = $this->build_level_meta_from_form( $features_input, $commerce_input, $presentation_input, $availability_input, $price_map, $credits_monthly );
		}

		if ( '' === $validated_price_id && ! empty( $sanitized_level_meta['stripe_price_ids'] ) && is_array( $sanitized_level_meta['stripe_price_ids'] ) ) {
			foreach ( $sanitized_level_meta['stripe_price_ids'] as $intervals ) {
				if ( ! is_array( $intervals ) ) {
					continue;
				}
				foreach ( $intervals as $price_id ) {
					if ( is_string( $price_id ) && $price_id !== '' ) {
						$validated_price_id = $price_id;
						break 2;
					}
				}
			}
		}

        $meta = [
            'custom_capabilities' => ! empty( $custom_caps ) ? $custom_caps : null,
            'monthly_credits'     => $credits_monthly,
            'stripe_price_id'     => $validated_price_id,
				'khm_level_meta'      => $sanitized_level_meta,
        ];

		$success = false;
		$is_clone_request = isset( $_POST['khm_clone_level'] ) && $level_id;
		if ( $level_id ) {
			if ( $is_clone_request ) {
				$payload['name'] = $name ? $name . ' (Copy)' : __( 'Membership Level (Copy)', 'khm-membership' );
				$created = $this->repository->create( $payload, $meta );
				if ( $created ) {
					$level_id = (int) $created->id;
					$this->add_notice( 'level_cloned', __( 'Membership level cloned successfully.', 'khm-membership' ), 'success' );
					$success = true;
				} else {
					$this->store_form_state( $level_id, $form_data );
					$this->add_notice( 'clone_failed', __( 'Failed to clone membership level.', 'khm-membership' ), 'error' );
				}
			} else {
				$success = $this->repository->update( $level_id, $payload, $meta );
				if ( $success ) {
					$this->add_notice( 'level_updated', __( 'Membership level updated successfully.', 'khm-membership' ), 'success' );
					// Info notice if Stripe Price ID is empty for paid levels
					if ( empty( $validated_price_id ) && $billing_amount > 0 ) {
						$this->add_notice( 'missing_stripe_price', __( 'Note: This is a paid membership level but no Stripe Price ID is configured. Add a Stripe Price ID to enable automatic checkout. Find Price IDs in your Stripe Dashboard under Products → Prices.', 'khm-membership' ), 'info' );
					}
				} else {
					$this->store_form_state( $level_id, $form_data );
					$this->add_notice( 'update_failed', __( 'Failed to update membership level.', 'khm-membership' ), 'error' );
				}
			}
		} else {
			error_log('LevelsPage: Creating new level with name: ' . $name);
            $created = $this->repository->create( $payload, $meta );
			error_log('LevelsPage: create() returned: ' . ($created ? 'object with id ' . $created->id : 'null/false'));
			if ( $created ) {
				$level_id = (int) $created->id;
				$this->add_notice( 'level_created', __( 'Membership level created successfully.', 'khm-membership' ), 'success' );
				// Info notice if Stripe Price ID is empty for paid levels
				if ( empty( $validated_price_id ) && $billing_amount > 0 ) {
					$this->add_notice( 'missing_stripe_price', __( 'Note: This is a paid membership level but no Stripe Price ID is configured. Add a Stripe Price ID to enable automatic checkout. Find Price IDs in your Stripe Dashboard under Products → Prices.', 'khm-membership' ), 'info' );
				}
				$success = true;
			} else {
				$this->store_form_state( 0, $form_data );
				$this->add_notice( 'create_failed', __( 'Failed to create membership level.', 'khm-membership' ), 'error' );
			}
		}

		if ( $success ) {
			delete_transient( $this->form_state_key() );
		}

		$this->persist_notices();
		$this->redirect_after_save( $level_id );
	}

	public function handle_delete_request(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete membership levels.', 'khm-membership' ) );
		}

		$level_id = isset( $_GET['level_id'] ) ? absint( $_GET['level_id'] ) : 0;

		check_admin_referer( 'khm_delete_membership_level_' . $level_id );

		if ( ! $level_id ) {
			$this->add_notice( 'invalid_level', __( 'Invalid membership level.', 'khm-membership' ), 'error' );
			$this->persist_notices();
			$this->redirect();
		}

		if ( $this->repository->delete( $level_id ) ) {
			$this->add_notice( 'level_deleted', __( 'Membership level deleted.', 'khm-membership' ), 'success' );
		} else {
			$this->add_notice( 'delete_failed', __( 'Failed to delete membership level.', 'khm-membership' ), 'error' );
		}

		$this->persist_notices();
		$this->redirect();
	}

	private function redirect_after_save( int $level_id ): void {
		// Always redirect to list page after successful save
		$this->redirect();
	}

	private function redirect( array $args = [] ): void {
		wp_safe_redirect( $this->page_url( $args ) );
		exit;
	}

	private function page_url( array $args = [] ): string {
		$base = [ 'page' => self::PAGE_SLUG ];
		return add_query_arg( $args, add_query_arg( $base, admin_url( 'admin.php' ) ) );
	}

	private function add_notice( string $code, string $message, string $type = 'success' ): void {
		add_settings_error( self::SETTINGS_GROUP, $code, $message, $type );
	}

	private function persist_notices(): void {
		set_transient( 'khm_levels_notices', get_settings_errors( self::SETTINGS_GROUP ), 30 );
	}

	private function clear_persisted_notices(): void {
		$notices = get_transient( 'khm_levels_notices' );
		if ( $notices && is_array( $notices ) ) {
			foreach ( $notices as $notice ) {
				add_settings_error(
					$notice['setting'] ?? self::SETTINGS_GROUP,
					$notice['code'] ?? '',
					$notice['message'] ?? '',
					$notice['type'] ?? 'success'
				);
			}
			delete_transient( 'khm_levels_notices' );
		}
	}

	private function store_form_state( int $level_id, array $data ): void {
		set_transient(
			$this->form_state_key(),
			[
				'level_id' => $level_id,
				'data'     => $data,
			],
			60
		);
	}

	private function consume_form_state(): array {
		$key   = $this->form_state_key();
		$state = get_transient( $key );
		if ( false !== $state ) {
			delete_transient( $key );
		}

		return is_array( $state ) ? $state : [];
	}

    private function form_state_key(): string {
        $user_id = get_current_user_id();
        return 'khm_membership_level_form_' . ( $user_id ?: 0 );
    }

	private function normalize_level_meta( $value ): array {
		if ( is_string( $value ) && $value !== '' ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return is_array( $value ) ? $value : [];
	}

	private function build_price_rows( $input_rows, array $price_map, string $fallback_price_id ): array {
		$rows = [];
		if ( is_array( $input_rows ) && ! empty( $input_rows ) ) {
			foreach ( $input_rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$rows[] = [
					'currency'        => (string) ( $row['currency'] ?? '' ),
					'interval'        => (string) ( $row['interval'] ?? 'monthly' ),
					'price_id'        => (string) ( $row['price_id'] ?? '' ),
					'custom_currency' => (string) ( $row['custom_currency'] ?? '' ),
				];
			}
			return $rows;
		}

		if ( ! empty( $price_map ) ) {
			foreach ( $price_map as $currency => $intervals ) {
				if ( ! is_array( $intervals ) ) {
					continue;
				}
				foreach ( $intervals as $interval => $price_id ) {
					$rows[] = [
						'currency'        => (string) $currency,
						'interval'        => (string) $interval,
						'price_id'        => (string) $price_id,
						'custom_currency' => '',
					];
				}
			}
			return $rows;
		}

		if ( $fallback_price_id !== '' ) {
			$rows[] = [
				'currency'        => strtoupper( (string) get_option( 'khm_currency', 'GBP' ) ),
				'interval'        => 'monthly',
				'price_id'        => $fallback_price_id,
				'custom_currency' => '',
			];
		}

		return $rows;
	}

	private function build_level_meta_from_form( array $features, array $commerce, array $presentation, array $availability, array $price_map, int $credits_monthly ): array {
		$meta = [];
		$meta['features'] = [
			'credits'       => ! empty( $features['credits'] ),
			'gifting'       => ! empty( $features['gifting'] ),
			'portal'        => ! empty( $features['portal'] ),
			'sponsor'       => ! empty( $features['sponsor'] ),
			'forum'         => ! empty( $features['forum'] ),
			'founder_badge' => ! empty( $features['founder_badge'] ),
		];
		$meta['commerce'] = [
			'allow_promotion_codes'   => ! empty( $commerce['allow_promotion_codes'] ),
			'allow_guest_checkout'    => ! empty( $commerce['allow_guest_checkout'] ),
			'trial_days'              => isset( $commerce['trial_days'] ) ? max( 0, (int) $commerce['trial_days'] ) : 0,
			'default_billing_interval' => sanitize_key( (string) ( $commerce['default_billing_interval'] ?? 'monthly' ) ),
		];
		$meta['presentation'] = [
			'template'        => sanitize_key( (string) ( $presentation['template'] ?? 'compact' ) ),
			'cta_text'        => sanitize_text_field( (string) ( $presentation['cta_text'] ?? '' ) ),
			'price_inclusive' => isset( $presentation['price_inclusive'] ) ? (bool) $presentation['price_inclusive'] : true,
		];
		$meta['availability'] = [
			'start_at' => sanitize_text_field( (string) ( $availability['start_at'] ?? '' ) ),
			'end_at'   => sanitize_text_field( (string) ( $availability['end_at'] ?? '' ) ),
		];
		$meta['credits'] = [
			'monthly' => max( 0, $credits_monthly ),
		];
		if ( ! empty( $price_map ) ) {
			$meta['stripe_price_ids'] = $this->sanitize_price_map( $price_map );
		}

		return $meta;
	}

	/**
	 * Sanitize level meta JSON input.
	 *
	 * @param string $raw_input Raw JSON string.
	 * @return array<string,mixed>|null Null on invalid JSON.
	 */
	private function sanitize_level_meta( string $raw_input ): ?array {
		if ( '' === $raw_input ) {
			return [];
		}

		$decoded = json_decode( $raw_input, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			return null;
		}

		$allowed = [
			'features' => [ 'credits', 'gifting', 'portal', 'sponsor', 'forum', 'founder_badge' ],
			'presentation' => [ 'template', 'cta_text', 'price_inclusive' ],
			'commerce' => [ 'allow_promotion_codes', 'trial_days', 'allow_guest_checkout', 'default_billing_interval' ],
			'availability' => [ 'start_at', 'end_at' ],
			'credits' => [ 'monthly' ],
			'stripe_price_ids' => true,
		];

		$clean = [];
		foreach ( $allowed as $section => $keys ) {
			if ( ! array_key_exists( $section, $decoded ) ) {
				continue;
			}
			$value = $decoded[ $section ];
			if ( $keys === true ) {
				if ( is_array( $value ) ) {
					$clean[ $section ] = $this->sanitize_price_map( $value );
				}
				continue;
			}
			if ( ! is_array( $value ) ) {
				continue;
			}
			$clean_section = [];
			foreach ( $keys as $key ) {
				if ( ! array_key_exists( $key, $value ) ) {
					continue;
				}
				$clean_section[ $key ] = $this->sanitize_meta_value( $key, $value[ $key ] );
			}
			if ( ! empty( $clean_section ) ) {
				$clean[ $section ] = $clean_section;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize individual meta values.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return mixed
	 */
	private function sanitize_meta_value( string $key, $value ) {
		$boolean_keys = [ 'credits', 'gifting', 'portal', 'sponsor', 'forum', 'founder_badge', 'allow_promotion_codes', 'allow_guest_checkout', 'price_inclusive' ];
		if ( in_array( $key, $boolean_keys, true ) ) {
			return (bool) $value;
		}

		if ( 'trial_days' === $key ) {
			return max( 0, (int) $value );
		}

		if ( 'monthly' === $key ) {
			return max( 0, (int) $value );
		}

		if ( in_array( $key, [ 'template', 'cta_text', 'start_at', 'end_at', 'default_billing_interval' ], true ) ) {
			return sanitize_text_field( (string) $value );
		}

		return $value;
	}

	/**
	 * Sanitize nested price ID mapping.
	 *
	 * @param array $map
	 * @return array
	 */
	private function sanitize_price_map( array $map ): array {
		$clean = [];
		foreach ( $map as $currency => $intervals ) {
			if ( ! is_array( $intervals ) ) {
				continue;
			}
			$currency_key = strtoupper( sanitize_text_field( (string) $currency ) );
			foreach ( $intervals as $interval => $price_id ) {
				if ( ! is_string( $price_id ) || '' === $price_id ) {
					continue;
				}
				$interval_key = sanitize_key( (string) $interval );
				$price_id = sanitize_text_field( $price_id );
				if ( ! preg_match( self::PRICE_ID_REGEX, $price_id ) ) {
					continue;
				}
				$clean[ $currency_key ][ $interval_key ] = $price_id;
			}
		}
		return $clean;
	}

    /**
     * Sanitize capabilities input from the textarea into a clean array.
     *
     * @param string $raw_input Newline-separated capabilities.
     * @return array<string>
     */
    private function sanitize_capabilities_input( string $raw_input ): array {
        $lines = preg_split( '/\r\n|\r|\n/', $raw_input );
        if ( ! $lines || ! is_array( $lines ) ) {
            return [];
        }

        $caps = [];
        foreach ( $lines as $line ) {
            $cap = sanitize_key( trim( $line ) );
            if ( ! empty( $cap ) ) {
                $caps[] = $cap;
            }
        }

        // Remove duplicates
        return array_values( array_unique( $caps ) );
    }
}

class LevelsListTable extends WP_List_Table {
	private LevelRepository $repository;
	/** @var MembershipLevel[] */
	private array $levels;
	private string $page_slug;

	public function __construct( LevelRepository $repository, array $levels, string $page_slug ) {
		$this->repository = $repository;
		$this->levels     = $levels;
		$this->page_slug  = $page_slug;

		parent::__construct(
			[
				'singular' => 'membership_level',
				'plural'   => 'membership_levels',
				'ajax'     => false,
			]
		);
	}

	public function get_columns(): array {
		return [
			'cb'               => '<input type="checkbox" />',
			'name'             => __( 'Name', 'khm-membership' ),
			'monthly_credits'  => __( 'Monthly Credits', 'khm-membership' ),
			'initial_payment'  => __( 'Initial Payment', 'khm-membership' ),
			'billing'          => __( 'Billing', 'khm-membership' ),
			'trial'            => __( 'Trial', 'khm-membership' ),
			'expiration'       => __( 'Expiration', 'khm-membership' ),
			'allow_signups'    => __( 'Signups', 'khm-membership' ),
		];
	}

	public function get_bulk_actions(): array {
		return [
			'enable_signups'  => __( 'Enable Signups', 'khm-membership' ),
			'disable_signups' => __( 'Disable Signups', 'khm-membership' ),
			'delete'          => __( 'Delete', 'khm-membership' ),
		];
	}

	public function prepare_items(): void {
		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = [];
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$this->items = array_map(
			static function ( MembershipLevel $level ): array {
				return [
					'id'               => (int) $level->id,
					'name'             => $level->name,
					'monthly_credits'  => (int) ($level->meta['monthly_credits'] ?? 0),
					'initial_payment'  => (float) $level->initial_payment,
					'billing_amount'   => (float) $level->billing_amount,
					'cycle_number'     => (int) $level->cycle_number,
					'cycle_period'     => $level->cycle_period,
					'billing_limit'    => (int) $level->billing_limit,
					'trial_amount'     => (float) $level->trial_amount,
					'trial_limit'      => (int) $level->trial_limit,
					'allow_signups'    => (int) $level->allow_signups,
					'expiration_number'=> (int) $level->expiration_number,
					'expiration_period'=> $level->expiration_period,
				];
			},
			$this->levels
		);
	}

	public function column_cb( $item ): string {
		return '<input type="checkbox" name="ids[]" value="' . esc_attr( (int) $item['id'] ) . '">';
	}

	public function column_name( $item ): string {
		$edit_url   = admin_url( 'admin.php?page=' . $this->page_slug . '&action=edit&id=' . (int) $item['id'] );
		$delete_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=khm_delete_membership_level&level_id=' . (int) $item['id'] ),
			'khm_delete_membership_level_' . (int) $item['id']
		);

		$actions = [
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'khm-membership' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_attr__( 'Are you sure you want to delete this level?', 'khm-membership' ) . '\');">' . esc_html__( 'Delete', 'khm-membership' ) . '</a>',
		];

		return '<strong>' . esc_html( $item['name'] ) . '</strong>' . $this->row_actions( $actions );
	}

	public function column_initial_payment( $item ): string {
		return esc_html( $this->format_price( $item['initial_payment'] ) );
	}

	public function column_billing( $item ): string {
		if ( $item['billing_amount'] <= 0 ) {
			return esc_html__( 'One-time', 'khm-membership' );
		}

		$text = $this->format_price( $item['billing_amount'] ) . ' / ' . $item['cycle_number'] . ' ' . $item['cycle_period'];
		if ( $item['billing_limit'] > 0 ) {
			$text .= ' &times; ' . $item['billing_limit'];
		}

		return esc_html( $text );
	}

	public function column_trial( $item ): string {
		if ( $item['trial_limit'] <= 0 ) {
			return '—';
		}

		return esc_html(
			sprintf(
				'%s × %d',
				$this->format_price( $item['trial_amount'] ),
				$item['trial_limit']
			)
		);
	}

	public function column_expiration( $item ): string {
		if ( $item['expiration_number'] <= 0 ) {
			return esc_html__( 'No expiration', 'khm-membership' );
		}

		return esc_html( $item['expiration_number'] . ' ' . $item['expiration_period'] );
	}

	public function column_allow_signups( $item ): string {
		return $item['allow_signups'] ? esc_html__( 'Enabled', 'khm-membership' ) : esc_html__( 'Disabled', 'khm-membership' );
	}

	public function column_monthly_credits( $item ): string {
		$credits = (int) $item['monthly_credits'];
		return $credits > 0 ? esc_html( (string) $credits ) : '—';
	}

	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	public function process_bulk_action(): void {
		$action = $this->current_action();
		if ( ! in_array( $action, [ 'enable_signups', 'disable_signups', 'delete' ], true ) ) {
			return;
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		$ids = isset( $_REQUEST['ids'] ) ? array_map( 'intval', (array) $_REQUEST['ids'] ) : [];
		$ids = array_values( array_filter( $ids, static fn( $id ) => $id > 0 ) );

		if ( empty( $ids ) ) {
			return;
		}

		$processed = 0;
		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'enable_signups':
					if ( $this->repository->update( $id, [ 'allow_signups' => 1 ] ) ) {
						$processed++;
					}
					break;
				case 'disable_signups':
					if ( $this->repository->update( $id, [ 'allow_signups' => 0 ] ) ) {
						$processed++;
					}
					break;
				case 'delete':
					if ( $this->repository->delete( $id ) ) {
						$processed++;
					}
					break;
			}
		}

		if ( $processed > 0 ) {
			$message = '';
			if ( 'delete' === $action ) {
				$message = sprintf( _n( 'Deleted %d membership level.', 'Deleted %d membership levels.', $processed, 'khm-membership' ), $processed );
			} elseif ( 'enable_signups' === $action ) {
				$message = sprintf( _n( 'Enabled signups for %d level.', 'Enabled signups for %d levels.', $processed, 'khm-membership' ), $processed );
			} else {
				$message = sprintf( _n( 'Disabled signups for %d level.', 'Disabled signups for %d levels.', $processed, 'khm-membership' ), $processed );
			}
			add_settings_error( LevelsPage::SETTINGS_GROUP, 'bulk_' . $action, $message, 'success' );
		}
	}

	private function format_price( float $amount ): string {
		if ( function_exists( 'khm_format_price' ) ) {
			return khm_format_price( $amount );
		}

		return '$' . number_format_i18n( $amount, 2 );
	}

	private function sanitize_capabilities_input( string $input ): array {
		if ( '' === trim( $input ) ) {
			return [];
		}

		$parts = preg_split( '/[\r\n,]+/', $input );
		$caps  = [];

		foreach ( (array) $parts as $cap ) {
			$cap = sanitize_key( trim( (string) $cap ) );
			if ( '' === $cap ) {
				continue;
			}
			$caps[] = $cap;
		}

		return array_values( array_unique( $caps ) );
	}
}
