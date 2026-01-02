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

	public function __construct( ?LevelRepository $repository = null ) {
		$this->repository = $repository ?: new LevelRepository();
	}

	public function register(): void {
		add_action( 'admin_post_khm_save_membership_level', [ $this, 'handle_save_request' ] );
		add_action( 'admin_post_khm_delete_membership_level', [ $this, 'handle_delete_request' ] );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage membership levels.', 'khm-membership' ) );
		}

		$form_state = $this->consume_form_state();

		$requested_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$requested_id     = ( 'edit' === $requested_action && isset( $_GET['id'] ) ) ? absint( $_GET['id'] ) : 0;

		$edit_id = isset( $form_state['level_id'] ) ? (int) $form_state['level_id'] : 0;
		if ( ! $edit_id ) {
			$edit_id = $requested_id;
		}

		$edit_level = $edit_id ? $this->repository->get( $edit_id, true ) : null;

		if ( $edit_id && ! $edit_level ) {
			$this->add_notice( 'level_not_found', __( 'Membership level not found.', 'khm-membership' ), 'error' );
			$this->persist_notices();
			$edit_id    = 0;
			$edit_level = null;
		}

		$old_input = isset( $form_state['data'] ) && is_array( $form_state['data'] ) ? $form_state['data'] : [];

		$levels    = $this->repository->all( true );
		$list_table = new LevelsListTable( $this->repository, $levels, self::PAGE_SLUG );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Membership Levels', 'khm-membership' ) . '</h1>';

		settings_errors( self::SETTINGS_GROUP );

		$list_table->prepare_items();
		$this->render_table( $list_table );
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
        ];

		$data = wp_parse_args( $old_input, $defaults );

		$form_title = $data['level_id'] ? esc_html__( 'Edit Membership Level', 'khm-membership' ) : esc_html__( 'Add Membership Level', 'khm-membership' );

		echo '<h2>' . $form_title . '</h2>';
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

		echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Save Membership Level', 'khm-membership' ) . '"></p>';

		echo '</form>';
	}

	public function handle_save_request(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
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

        $custom_caps = $this->sanitize_capabilities_input( $custom_caps_raw );
        $meta = [
            'custom_capabilities' => ! empty( $custom_caps ) ? $custom_caps : null,
        ];

		$success = false;
		if ( $level_id ) {
            $success = $this->repository->update( $level_id, $payload, $meta );
			if ( $success ) {
				$this->add_notice( 'level_updated', __( 'Membership level updated successfully.', 'khm-membership' ), 'success' );
			} else {
				$this->store_form_state( $level_id, $form_data );
				$this->add_notice( 'update_failed', __( 'Failed to update membership level.', 'khm-membership' ), 'error' );
			}
		} else {
            $created = $this->repository->create( $payload, $meta );
			if ( $created ) {
				$level_id = (int) $created->id;
				$this->add_notice( 'level_created', __( 'Membership level created successfully.', 'khm-membership' ), 'success' );
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
		if ( $level_id > 0 ) {
			$this->redirect(
				[
					'action' => 'edit',
					'id'     => $level_id,
				]
			);
		}

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
		set_transient( 'settings_errors', get_settings_errors( self::SETTINGS_GROUP ), 30 );
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
			'initial_payment'  => __( 'Initial Payment', 'khm-membership' ),
			'billing'          => __( 'Billing', 'khm-membership' ),
			'trial'            => __( 'Trial', 'khm-membership' ),
			'expiration'       => __( 'Expiration', 'khm-membership' ),
			'allow_signups'    => __( 'Signups', 'khm-membership' ),
			'created_at'       => __( 'Created', 'khm-membership' ),
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
					'created_at'       => $level->created_at,
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

	public function column_created_at( $item ): string {
		return $item['created_at'] ? esc_html( mysql2date( 'Y-m-d', $item['created_at'], false ) ) : '';
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
