<?php
namespace KHM\Admin;

use KHM\Models\DiscountCode;
use KHM\Services\DiscountCodeService;
use KHM\Services\LevelRepository;
use WP_List_Table;

class DiscountCodesPage {
	public const PAGE_SLUG      = 'khm-discount-codes';
	public const SETTINGS_GROUP = 'khm_discount_codes';

	private DiscountCodeService $service;
	private LevelRepository $levels;

	public function __construct( ?DiscountCodeService $service = null, ?LevelRepository $level_repo = null ) {
		$this->service = $service ?: new DiscountCodeService();
		$this->levels  = $level_repo ?: new LevelRepository();
	}

	public function register(): void {
		add_submenu_page(
			'khm-dashboard',
			'KHM Discount Codes',
			'Discount Codes',
			'manage_khm',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		add_action( 'admin_post_khm_save_discount_code', array( $this, 'handle_save_request' ) );
		add_action( 'admin_post_khm_delete_discount_code', array( $this, 'handle_delete_request' ) );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage discount codes.', 'khm-membership' ) );
		}

		$form_state = $this->consume_form_state();

		$requested_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$requested_id     = ( 'edit' === $requested_action && isset( $_GET['id'] ) ) ? absint( $_GET['id'] ) : 0;

		$edit_id = isset( $form_state['code_id'] ) ? (int) $form_state['code_id'] : 0;
		if ( ! $edit_id ) {
			$edit_id = $requested_id;
		}

		$edit_code = $edit_id ? $this->service->get_code( $edit_id ) : null;
		if ( $edit_id && ! $edit_code ) {
			$this->add_notice( 'code_not_found', 'Discount code not found.', 'error' );
			$this->persist_notices();
			$edit_id   = 0;
			$edit_code = null;
		}

		$old_input = isset( $form_state['data'] ) && is_array( $form_state['data'] ) ? $form_state['data'] : array();

		$level_names = $this->levels->getNameMap();
		$table       = new DiscountCodesListTable( $this->service, $level_names, self::PAGE_SLUG );
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'KHM Discount Codes', 'khm-membership' ) . '</h1>';

		settings_errors( self::SETTINGS_GROUP );

		$this->render_table( $table );
		$this->render_form( $edit_code, $old_input );
		echo '</div>';
	}

	private function render_table( DiscountCodesListTable $table ): void {
		echo '<h2>' . esc_html__( 'Existing Discount Codes', 'khm-membership' ) . '</h2>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';
		$table->search_box( esc_html__( 'Search Codes', 'khm-membership' ), 'khm-discount-codes' );
		$table->display();
		echo '</form>';
	}

	private function render_form( ?DiscountCode $edit_code, array $old_input = array() ): void {
		$default_levels = $edit_code ? $edit_code->level_ids : array();

		$defaults = array(
			'code_id'                  => $edit_code ? (int) $edit_code->id : 0,
			'code'                     => $edit_code ? $edit_code->code : '',
			'type'                     => $edit_code ? $edit_code->type : 'amount',
			'value'                    => $edit_code ? (float) $edit_code->value : '',
			'start_date'               => $edit_code && $edit_code->start_date ? mysql2date( 'Y-m-d', $edit_code->start_date, false ) : '',
			'end_date'                 => $edit_code && $edit_code->end_date ? mysql2date( 'Y-m-d', $edit_code->end_date, false ) : '',
			'usage_limit'              => $edit_code && null !== $edit_code->usage_limit ? (int) $edit_code->usage_limit : '',
			'per_user_limit'           => $edit_code && null !== $edit_code->per_user_limit ? (int) $edit_code->per_user_limit : '',
			'status'                   => $edit_code ? $edit_code->status : 'active',
			'level_ids'                => $default_levels,
			'trial_days'               => $edit_code && null !== $edit_code->trial_days ? (int) $edit_code->trial_days : '',
			'trial_amount'             => $edit_code && null !== $edit_code->trial_amount ? (float) $edit_code->trial_amount : '',
			'first_payment_only'       => $edit_code ? (int) $edit_code->first_payment_only : 0,
			'recurring_discount_type'  => $edit_code ? (string) $edit_code->recurring_discount_type : '',
			'recurring_discount_amount'=> $edit_code && null !== $edit_code->recurring_discount_amount ? (float) $edit_code->recurring_discount_amount : '',
		);

		$data         = wp_parse_args( $old_input, $defaults );
		$selected_ids = array_map( 'intval', (array) $data['level_ids'] );
		$level_names  = $this->levels->getNameMap();

		$form_title = $data['code_id'] ? esc_html__( 'Edit Discount Code', 'khm-membership' ) : esc_html__( 'Add Discount Code', 'khm-membership' );

		echo '<h2>' . $form_title . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'khm_save_discount_code', 'khm_discount_code_nonce' );
		echo '<input type="hidden" name="action" value="khm_save_discount_code">';
		echo '<input type="hidden" name="code_id" value="' . esc_attr( (int) $data['code_id'] ) . '">';

		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row"><label for="khm-code">' . esc_html__( 'Code', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="text" id="khm-code" name="code" value="' . esc_attr( $data['code'] ) . '" required class="regular-text"></td></tr>';

		echo '<tr><th scope="row"><label for="khm-type">' . esc_html__( 'Type', 'khm-membership' ) . '</label></th><td><select id="khm-type" name="type">';
		echo '<option value="amount"' . selected( $data['type'], 'amount', false ) . '>' . esc_html__( 'Amount', 'khm-membership' ) . '</option>';
		echo '<option value="percent"' . selected( $data['type'], 'percent', false ) . '>' . esc_html__( 'Percent', 'khm-membership' ) . '</option>';
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="khm-value">' . esc_html__( 'Value', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" step="0.01" id="khm-value" name="value" value="' . esc_attr( $data['value'] ) . '" required></td></tr>';

		echo '<tr><th scope="row"><label for="khm-start-date">' . esc_html__( 'Start Date', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="date" id="khm-start-date" name="start_date" value="' . esc_attr( $data['start_date'] ) . '"></td></tr>';

		echo '<tr><th scope="row"><label for="khm-end-date">' . esc_html__( 'End Date', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="date" id="khm-end-date" name="end_date" value="' . esc_attr( $data['end_date'] ) . '"></td></tr>';

		echo '<tr><th scope="row"><label for="khm-usage-limit">' . esc_html__( 'Usage Limit', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" id="khm-usage-limit" name="usage_limit" value="' . esc_attr( $data['usage_limit'] ) . '" min="0" step="1"> <p class="description">' . esc_html__( 'Leave blank for unlimited uses.', 'khm-membership' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="khm-per-user-limit">' . esc_html__( 'Per User Limit', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" id="khm-per-user-limit" name="per_user_limit" value="' . esc_attr( $data['per_user_limit'] ) . '" min="0" step="1"> <p class="description">' . esc_html__( 'Leave blank for unlimited uses per user.', 'khm-membership' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="khm-levels">' . esc_html__( 'Applicable Levels', 'khm-membership' ) . '</label></th>';
		echo '<td><select id="khm-levels" name="level_ids[]" multiple size="' . esc_attr( max( 4, min( 8, count( $level_names ) ) ) ) . '" style="min-width: 240px;">';
		foreach ( $level_names as $level_id => $level_name ) {
			echo '<option value="' . esc_attr( (int) $level_id ) . '"' . selected( in_array( (int) $level_id, $selected_ids, true ), true, false ) . '>' . esc_html( $level_name ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Hold Command/Ctrl to select multiple levels. Leave empty to apply to all levels.', 'khm-membership' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="khm-status">' . esc_html__( 'Status', 'khm-membership' ) . '</label></th>';
		echo '<td><select id="khm-status" name="status">';
		echo '<option value="active"' . selected( $data['status'], 'active', false ) . '>' . esc_html__( 'Active', 'khm-membership' ) . '</option>';
		echo '<option value="inactive"' . selected( $data['status'], 'inactive', false ) . '>' . esc_html__( 'Inactive', 'khm-membership' ) . '</option>';
		echo '<option value="expired"' . selected( $data['status'], 'expired', false ) . '>' . esc_html__( 'Expired', 'khm-membership' ) . '</option>';
		echo '</select></td></tr>';

		echo '<tr><th colspan="2"><h3>' . esc_html__( 'Trial Settings (Optional)', 'khm-membership' ) . '</h3></th></tr>';
		echo '<tr><th scope="row"><label for="khm-trial-days">' . esc_html__( 'Trial Days', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" id="khm-trial-days" name="trial_days" value="' . esc_attr( $data['trial_days'] ) . '" min="0" step="1"></td></tr>';

		echo '<tr><th scope="row"><label for="khm-trial-amount">' . esc_html__( 'Trial Amount', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" step="0.01" id="khm-trial-amount" name="trial_amount" value="' . esc_attr( $data['trial_amount'] ) . '"></td></tr>';

		echo '<tr><th colspan="2"><h3>' . esc_html__( 'Payment Options', 'khm-membership' ) . '</h3></th></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'First Payment Only', 'khm-membership' ) . '</th>';
		echo '<td><label><input type="checkbox" name="first_payment_only" value="1"' . checked( (int) $data['first_payment_only'], 1, false ) . '> ' . esc_html__( 'Apply discount only to the first payment', 'khm-membership' ) . '</label></td></tr>';

		echo '<tr><th colspan="2"><h3>' . esc_html__( 'Recurring Discount (Optional)', 'khm-membership' ) . '</h3></th></tr>';
		echo '<tr><th scope="row"><label for="khm-recurring-type">' . esc_html__( 'Recurring Discount Type', 'khm-membership' ) . '</label></th>';
		echo '<td><select id="khm-recurring-type" name="recurring_discount_type">';
		echo '<option value=""' . selected( $data['recurring_discount_type'], '', false ) . '>' . esc_html__( 'None', 'khm-membership' ) . '</option>';
		echo '<option value="amount"' . selected( $data['recurring_discount_type'], 'amount', false ) . '>' . esc_html__( 'Amount', 'khm-membership' ) . '</option>';
		echo '<option value="percent"' . selected( $data['recurring_discount_type'], 'percent', false ) . '>' . esc_html__( 'Percent', 'khm-membership' ) . '</option>';
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="khm-recurring-amount">' . esc_html__( 'Recurring Discount Value', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" step="0.01" id="khm-recurring-amount" name="recurring_discount_amount" value="' . esc_attr( $data['recurring_discount_amount'] ) . '"></td></tr>';

		echo '</table>';

		echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Save Discount Code', 'khm-membership' ) . '"></p>';
		echo '</form>';
	}

	public function handle_save_request(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage discount codes.', 'khm-membership' ) );
		}

		check_admin_referer( 'khm_save_discount_code', 'khm_discount_code_nonce' );

		$code_id = isset( $_POST['code_id'] ) ? absint( $_POST['code_id'] ) : 0;

		$code   = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$type   = isset( $_POST['type'] ) && in_array( $_POST['type'], array( 'amount', 'percent' ), true )
			? sanitize_text_field( wp_unslash( $_POST['type'] ) )
			: 'amount';
		$value_raw = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		$value     = is_numeric( $value_raw ) ? (float) $value_raw : 0.0;

		$start_date = isset( $_POST['start_date'] ) ? $this->normalize_date( wp_unslash( $_POST['start_date'] ) ) : null;
		$end_date   = isset( $_POST['end_date'] ) ? $this->normalize_date( wp_unslash( $_POST['end_date'] ) ) : null;

		$usage_limit_raw    = isset( $_POST['usage_limit'] ) ? trim( (string) wp_unslash( $_POST['usage_limit'] ) ) : '';
		$per_user_limit_raw = isset( $_POST['per_user_limit'] ) ? trim( (string) wp_unslash( $_POST['per_user_limit'] ) ) : '';
		$usage_limit        = '' === $usage_limit_raw ? null : max( 0, (int) $usage_limit_raw );
		$per_user_limit     = '' === $per_user_limit_raw ? null : max( 0, (int) $per_user_limit_raw );

		$status = isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'active', 'inactive', 'expired' ), true )
			? sanitize_text_field( wp_unslash( $_POST['status'] ) )
			: 'active';

		$level_ids = isset( $_POST['level_ids'] ) ? array_map( 'intval', (array) $_POST['level_ids'] ) : array();
		$level_ids = array_values( array_unique( array_filter( $level_ids, static fn( $id ) => $id > 0 ) ) );
		$valid_levels = $this->levels->getNameMap();
		$level_ids    = array_values(
			array_filter(
				$level_ids,
				static fn( $id ) => isset( $valid_levels[ $id ] )
			)
		);

		$trial_days_raw   = isset( $_POST['trial_days'] ) ? trim( (string) wp_unslash( $_POST['trial_days'] ) ) : '';
		$trial_amount_raw = isset( $_POST['trial_amount'] ) ? wp_unslash( $_POST['trial_amount'] ) : '';
		$trial_days       = '' === $trial_days_raw ? null : max( 0, (int) $trial_days_raw );
		$trial_amount     = '' === $trial_amount_raw ? null : round( (float) $trial_amount_raw, 2 );

		$first_payment_only = isset( $_POST['first_payment_only'] ) ? 1 : 0;

		$recurring_type = isset( $_POST['recurring_discount_type'] ) && in_array( $_POST['recurring_discount_type'], array( 'percent', 'amount' ), true )
			? sanitize_text_field( wp_unslash( $_POST['recurring_discount_type'] ) )
			: '';
		$recurring_amount_raw = isset( $_POST['recurring_discount_amount'] ) ? wp_unslash( $_POST['recurring_discount_amount'] ) : '';
		$recurring_amount     = '' === $recurring_amount_raw ? null : round( (float) $recurring_amount_raw, 2 );

		if ( '' === $recurring_type ) {
			$recurring_amount = null;
		}

		$form_data = array(
			'code_id'                  => $code_id,
			'code'                     => $code,
			'type'                     => $type,
			'value'                    => $value_raw,
			'start_date'               => $start_date ? mysql2date( 'Y-m-d', $start_date, false ) : '',
			'end_date'                 => $end_date ? mysql2date( 'Y-m-d', $end_date, false ) : '',
			'usage_limit'              => $usage_limit_raw,
			'per_user_limit'           => $per_user_limit_raw,
			'status'                   => $status,
			'level_ids'                => $level_ids,
			'trial_days'               => $trial_days_raw,
			'trial_amount'             => $trial_amount_raw,
			'first_payment_only'       => $first_payment_only,
			'recurring_discount_type'  => $recurring_type,
			'recurring_discount_amount'=> $recurring_amount_raw,
		);

		if ( empty( $code ) ) {
			$this->store_form_state( $code_id, $form_data );
			$this->add_notice( 'missing_code', 'Discount code is required.', 'error' );
			$this->persist_notices();
			$this->redirect_after_save( $code_id );
		}

		if ( $value <= 0 ) {
			$this->store_form_state( $code_id, $form_data );
			$this->add_notice( 'invalid_value', 'Discount value must be greater than 0.', 'error' );
			$this->persist_notices();
			$this->redirect_after_save( $code_id );
		}

		if ( 'percent' === $type && $value > 100 ) {
			$this->store_form_state( $code_id, $form_data );
			$this->add_notice( 'invalid_percent', 'Percent discount cannot exceed 100%.', 'error' );
			$this->persist_notices();
			$this->redirect_after_save( $code_id );
		}

		if ( 'percent' === $recurring_type && null !== $recurring_amount && $recurring_amount > 100 ) {
			$this->store_form_state( $code_id, $form_data );
			$this->add_notice( 'invalid_recurring_percent', 'Recurring percent discount cannot exceed 100%.', 'error' );
			$this->persist_notices();
			$this->redirect_after_save( $code_id );
		}

		$existing = $this->service->get_code_by_name( $code );
		if ( $existing && ( ! $code_id || (int) $existing->id !== $code_id ) ) {
			$this->store_form_state( $code_id, $form_data );
			$this->add_notice( 'duplicate_code', 'A discount code with this code already exists.', 'error' );
			$this->persist_notices();
			$this->redirect_after_save( $code_id );
		}

		$payload = array(
			'code'                     => $code,
			'type'                     => $type,
			'value'                    => $value,
			'start_date'               => $start_date,
			'end_date'                 => $end_date,
			'usage_limit'              => $usage_limit,
			'per_user_limit'           => $per_user_limit,
			'status'                   => $status,
			'trial_days'               => $trial_days,
			'trial_amount'             => $trial_amount,
			'first_payment_only'       => $first_payment_only,
			'recurring_discount_type'  => $recurring_type ?: null,
			'recurring_discount_amount'=> $recurring_amount,
			'level_ids'                => $level_ids,
		);

		$success = false;
		if ( $code_id ) {
			$success = $this->service->update_code( $code_id, $payload );
			if ( $success ) {
				$this->add_notice( 'code_updated', 'Discount code updated successfully.', 'success' );
			} else {
				$this->store_form_state( $code_id, $form_data );
				$this->add_notice( 'update_failed', 'Failed to update discount code.', 'error' );
			}
		} else {
			$created = $this->service->create_code( $payload );
			if ( $created ) {
				$code_id = (int) $created->id;
				$this->add_notice( 'code_created', 'Discount code created successfully.', 'success' );
				$success = true;
			} else {
				$this->store_form_state( 0, $form_data );
				$this->add_notice( 'insert_failed', 'Failed to create discount code.', 'error' );
			}
		}

		if ( $success ) {
			delete_transient( $this->form_state_key() );
		}

		$this->persist_notices();
		$this->redirect_after_save( $code_id );
	}

	public function handle_delete_request(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete discount codes.', 'khm-membership' ) );
		}

		$code_id = isset( $_GET['code_id'] ) ? absint( $_GET['code_id'] ) : 0;

		check_admin_referer( 'khm_delete_discount_code_' . $code_id );

		if ( ! $code_id ) {
			$this->add_notice( 'invalid_code', 'Invalid discount code.', 'error' );
			$this->persist_notices();
			$this->redirect();
		}

		if ( $this->service->delete_code( $code_id ) ) {
			$this->add_notice( 'code_deleted', 'Discount code deleted successfully.', 'success' );
		} else {
			$this->add_notice( 'delete_failed', 'Failed to delete discount code.', 'error' );
		}

		$this->persist_notices();
		$this->redirect();
	}

	private function normalize_date( $date ): ?string {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return null;
		}

		$dt = date_create_from_format( 'Y-m-d', $date );
		if ( false === $dt ) {
			return null;
		}

		return $dt->format( 'Y-m-d' );
	}

	private function redirect_after_save( int $code_id ): void {
		if ( $code_id > 0 ) {
			$this->redirect(
				array(
					'action' => 'edit',
					'id'     => $code_id,
				)
			);
		}

		$this->redirect();
	}

	private function redirect( array $args = array() ): void {
		$url = $this->page_url( $args );
		wp_safe_redirect( $url );
		exit;
	}

	private function page_url( array $args = array() ): string {
		$base = array( 'page' => self::PAGE_SLUG );
		return add_query_arg( $args, add_query_arg( $base, admin_url( 'admin.php' ) ) );
	}

	private function add_notice( string $code, string $message, string $type = 'success' ): void {
		add_settings_error( self::SETTINGS_GROUP, $code, $message, $type );
	}

	private function persist_notices(): void {
		set_transient( 'settings_errors', get_settings_errors( self::SETTINGS_GROUP ), 30 );
	}

	private function store_form_state( int $code_id, array $data ): void {
		set_transient(
			$this->form_state_key(),
			array(
				'code_id' => $code_id,
				'data'    => $data,
			),
			60
		);
	}

	private function consume_form_state(): array {
		$key   = $this->form_state_key();
		$state = get_transient( $key );
		if ( false !== $state ) {
			delete_transient( $key );
		}

		return is_array( $state ) ? $state : array();
	}

	private function form_state_key(): string {
		$user_id = get_current_user_id();
		return 'khm_discount_code_form_' . ( $user_id ?: 0 );
	}
}

class DiscountCodesListTable extends WP_List_Table {
	private DiscountCodeService $service;
	private array $level_names;
	private string $page_slug;

	public function __construct( DiscountCodeService $service, array $level_names, string $page_slug ) {
		$this->service     = $service;
		$this->level_names = $level_names;
		$this->page_slug   = $page_slug;

		parent::__construct(
			array(
				'singular' => 'discount_code',
				'plural'   => 'discount_codes',
				'ajax'     => false,
			)
		);
	}

	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox" />',
			'code'           => 'Code',
			'type'           => 'Type',
			'value'          => 'Value',
			'start_date'     => 'Start Date',
			'end_date'       => 'End Date',
			'usage_limit'    => 'Usage Limit',
			'per_user_limit' => 'Per User Limit',
			'levels'         => 'Levels',
			'status'         => 'Status',
			'times_used'     => 'Times Used',
		);
	}

	public function get_bulk_actions(): array {
		return array(
			'activate'   => 'Activate',
			'deactivate' => 'Deactivate',
			'delete'     => 'Delete',
		);
	}

	public function prepare_items(): void {
		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = $this->get_items_per_page( 'khm_discount_codes_per_page', 20 );
		$current_page = max( 1, $this->get_pagenum() );
		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$status       = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';

		$result = $this->service->paginate_codes(
			array(
				'search' => $search,
				'status' => $status,
				'limit'  => $per_page,
				'offset' => ( $current_page - 1 ) * $per_page,
			)
		);

		$codes = $result['items'];
		$total = (int) $result['total'];

		if ( empty( $codes ) ) {
			$this->items = array();
			$this->set_pagination_args(
				array(
					'total_items' => $total,
					'per_page'    => $per_page,
				)
			);
			return;
		}

		$ids       = array_map( static fn( DiscountCode $code ) => (int) $code->id, $codes );
		$usage_map = ! empty( $ids ) ? $this->service->get_usage_map( $ids ) : array();

		$this->items = array_map(
			function ( DiscountCode $code ) use ( $usage_map ) {
				$usage = $usage_map[ (int) $code->id ] ?? array(
					'total'        => (int) $code->times_used,
					'unique_users' => 0,
				);

				return array(
					'id'             => (int) $code->id,
					'code'           => $code->code,
					'type'           => $code->type,
					'value'          => (float) $code->value,
					'start_date'     => $code->start_date,
					'end_date'       => $code->end_date,
					'usage_limit'    => $code->usage_limit,
					'per_user_limit' => $code->per_user_limit,
					'levels'         => $code->level_ids,
					'status'         => $code->status,
					'times_used'     => $usage,
				);
			},
			$codes
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No discount codes found.', 'khm-membership' );
	}

	public function column_cb( $item ): string {
		return '<input type="checkbox" name="ids[]" value="' . esc_attr( (int) $item['id'] ) . '">';
	}

	public function column_code( $item ): string {
		$edit_url   = admin_url( 'admin.php?page=' . $this->page_slug . '&action=edit&id=' . (int) $item['id'] );
		$delete_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=khm_delete_discount_code&code_id=' . (int) $item['id'] ),
			'khm_delete_discount_code_' . (int) $item['id']
		);

		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'khm-membership' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_attr__( 'Are you sure you want to delete this discount code?', 'khm-membership' ) . '\');">' . esc_html__( 'Delete', 'khm-membership' ) . '</a>',
		);

		return '<strong>' . esc_html( $item['code'] ) . '</strong>' . $this->row_actions( $actions );
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'type':
				return esc_html( ucfirst( $item['type'] ) );
			case 'value':
				return esc_html( number_format_i18n( (float) $item['value'], 2 ) );
			case 'start_date':
			case 'end_date':
				return $item[ $column_name ] ? esc_html( mysql2date( 'Y-m-d', $item[ $column_name ], false ) ) : '';
			case 'usage_limit':
			case 'per_user_limit':
				return null !== $item[ $column_name ] ? esc_html( (string) (int) $item[ $column_name ] ) : '';
			case 'levels':
				return esc_html( $this->format_levels( (array) $item['levels'] ) );
			case 'status':
				return esc_html( ucfirst( $item['status'] ) );
			case 'times_used':
				$total        = (int) $item['times_used']['total'];
				$unique_users = (int) $item['times_used']['unique_users'];
				return esc_html( sprintf( '%d / %d users', $total, $unique_users ) );
		}

		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	public function process_bulk_action(): void {
		$action = $this->current_action();
		if ( ! in_array( $action, array( 'activate', 'deactivate', 'delete' ), true ) ) {
			return;
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		$ids = isset( $_REQUEST['ids'] ) ? array_map( 'intval', (array) $_REQUEST['ids'] ) : array();
		$ids = array_values( array_filter( $ids, static fn( $id ) => $id > 0 ) );

		if ( empty( $ids ) ) {
			return;
		}

		$processed = 0;
		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'activate':
					if ( $this->service->update_code( $id, array( 'status' => 'active' ) ) ) {
						$processed++;
					}
					break;
				case 'deactivate':
					if ( $this->service->update_code( $id, array( 'status' => 'inactive' ) ) ) {
						$processed++;
					}
					break;
				case 'delete':
					if ( $this->service->delete_code( $id ) ) {
						$processed++;
					}
					break;
			}
		}

		if ( $processed > 0 ) {
			$message = '';
			if ( 'delete' === $action ) {
				$message = sprintf( _n( 'Deleted %d discount code.', 'Deleted %d discount codes.', $processed, 'khm-membership' ), $processed );
			} elseif ( 'activate' === $action ) {
				$message = sprintf( _n( 'Activated %d discount code.', 'Activated %d discount codes.', $processed, 'khm-membership' ), $processed );
			} else {
				$message = sprintf( _n( 'Deactivated %d discount code.', 'Deactivated %d discount codes.', $processed, 'khm-membership' ), $processed );
			}

			add_settings_error( DiscountCodesPage::SETTINGS_GROUP, 'bulk_' . $action, $message, 'success' );
		}
	}

	private function format_levels( array $level_ids ): string {
		if ( empty( $level_ids ) ) {
			return 'All Levels';
		}

		$names = array();
		foreach ( $level_ids as $level_id ) {
			if ( isset( $this->level_names[ $level_id ] ) ) {
				$names[] = $this->level_names[ $level_id ];
			} else {
				$names[] = sprintf( 'Level #%d', (int) $level_id );
			}
		}

		return implode( ', ', $names );
	}
}
