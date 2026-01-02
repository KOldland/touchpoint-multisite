<?php

namespace KHM\Admin;

use KHM\Services\MembershipRepository;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * MembersListTable
 *
 * Renders memberships within the admin using WP_List_Table.
 */
class MembersListTable extends \WP_List_Table {

	private MembershipRepository $repository;
	private array $filters;
	private array $level_names;

	private const NOTICE_GROUP = 'khm_memberships';

	public function __construct( MembershipRepository $repository, array $filters = [], array $level_names = [] ) {
		parent::__construct(
			[
				'singular' => 'membership',
				'plural'   => 'memberships',
				'ajax'     => false,
			]
		);

		$this->repository  = $repository;
		$this->filters     = $filters;
		$this->level_names = $level_names;
	}

	public function get_columns(): array {
		return [
			'cb'          => '<input type="checkbox" />',
			'user'        => __( 'Member', 'khm-membership' ),
			'email'       => __( 'Email', 'khm-membership' ),
			'level_name'  => __( 'Membership Level', 'khm-membership' ),
			'start_date'  => __( 'Start Date', 'khm-membership' ),
			'end_date'    => __( 'End Date', 'khm-membership' ),
			'status'      => __( 'Status', 'khm-membership' ),
			'billing'     => __( 'Billing', 'khm-membership' ),
			'trial'       => __( 'Trial', 'khm-membership' ),
		];
	}

	public function get_sortable_columns(): array {
		return [
			'user'       => [ 'user', false ],
			'email'      => [ 'email', false ],
			'level_name' => [ 'level', false ],
			'start_date' => [ 'start_date', true ],
			'end_date'   => [ 'end_date', false ],
			'status'     => [ 'status', false ],
		];
	}

	public function get_bulk_actions(): array {
		return [
			'cancel' => __( 'Cancel Membership', 'khm-membership' ),
			'delete' => __( 'Delete', 'khm-membership' ),
			'export' => __( 'Export CSV', 'khm-membership' ),
		];
	}

	public function prepare_items(): void {
		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$per_page     = $this->get_items_per_page( 'khm_members_per_page', 20 );
		$current_page = max( 1, $this->get_pagenum() );
		$offset       = ( $current_page - 1 ) * $per_page;

		$result = $this->repository->paginate(
			[
				'search'   => $this->filters['search'] ?? '',
				'level_id' => $this->filters['level'] ?? null,
				'status'   => $this->filters['status'] ?? '',
				'orderby'  => $this->get_orderby_param(),
				'order'    => $this->get_order_param(),
				'per_page' => $per_page,
				'offset'   => $offset,
			]
		);

		$items = array_map(
			static function ( array $row ): array {
				$row['start_date'] = $row['start_date'] ?? '';
				$row['end_date']   = $row['end_date'] ?? '';
				$row['billing']    = [
					'amount'      => isset( $row['billing_amount'] ) ? (float) $row['billing_amount'] : 0.0,
					'cycle'       => isset( $row['cycle_number'] ) ? (int) $row['cycle_number'] : 0,
					'period'      => $row['cycle_period'] ?? 'Month',
					'limit'       => isset( $row['billing_limit'] ) ? (int) $row['billing_limit'] : 0,
				];
				$row['trial_data'] = [
					'amount' => isset( $row['trial_amount'] ) ? (float) $row['trial_amount'] : 0.0,
					'limit'  => isset( $row['trial_limit'] ) ? (int) $row['trial_limit'] : 0,
				];
				return $row;
			},
			$result['items']
		);

		$this->items = $items;

		$this->set_pagination_args(
			[
				'total_items' => (int) $result['total'],
				'per_page'    => $per_page,
				'total_pages' => $per_page ? (int) ceil( $result['total'] / $per_page ) : 0,
			]
		);
	}

	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id'] );
	}

	public function column_user( $item ): string {
		$membership_id = (int) $item['id'];
		$user_id       = (int) $item['user_id'];
		$name          = $item['display_name'] ?: $item['user_login'];

		$edit_url = add_query_arg(
			[
				'page'   => MembersPage::PAGE_SLUG,
				'action' => 'edit',
				'id'     => $membership_id,
			],
			admin_url( 'admin.php' )
		);

		$actions = [
			'view_user' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_edit_user_link( $user_id ) ),
				esc_html__( 'View User', 'khm-membership' )
			),
		];

		$status = $item['status'] ?? '';

		if ( 'active' === $status ) {
			$actions['cancel'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( 'khm_membership_cancel', $membership_id ) ),
				esc_html__( 'Cancel', 'khm-membership' )
			);
			$actions['expire'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( 'khm_membership_expire', $membership_id ) ),
				esc_html__( 'Expire', 'khm-membership' )
			);
		} else {
			$actions['reactivate'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( 'khm_membership_reactivate', $membership_id ) ),
				esc_html__( 'Reactivate', 'khm-membership' )
			);
		}

		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( $this->action_url( 'khm_membership_delete', $membership_id ) ),
			esc_js( __( 'Delete this membership record?', 'khm-membership' ) ),
			esc_html__( 'Delete', 'khm-membership' )
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $name ),
			$this->row_actions( $actions )
		);
	}

	public function column_email( $item ): string {
		return sprintf(
			'<a href="mailto:%1$s">%2$s</a>',
			esc_attr( $item['user_email'] ),
			esc_html( $item['user_email'] )
		);
	}

	public function column_level_name( $item ): string {
		return esc_html( $item['level_name'] ?: __( 'Unknown', 'khm-membership' ) );
	}

	public function column_start_date( $item ): string {
		if ( empty( $item['start_date'] ) ) {
			return '—';
		}

		return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item['start_date'] ) ) );
	}

	public function column_end_date( $item ): string {
		if ( empty( $item['end_date'] ) ) {
			return esc_html__( 'Never', 'khm-membership' );
		}

		$timestamp = strtotime( $item['end_date'] );
		$formatted = date_i18n( get_option( 'date_format' ), $timestamp );

		if ( $timestamp && $timestamp < time() ) {
			return '<span class="khm-expired">' . esc_html( $formatted ) . '</span>';
		}

		return esc_html( $formatted );
	}

	public function column_status( $item ): string {
		$status = $item['status'] ?? 'unknown';

		return sprintf(
			'<span class="khm-badge khm-status-%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( ucfirst( $status ) )
		);
	}

	public function column_billing( $item ): string {
		$billing = $item['billing'];
		if ( $billing['amount'] <= 0 ) {
			return esc_html__( 'One-time', 'khm-membership' );
		}

		$text = sprintf(
			'%s / %d %s',
			esc_html( $this->format_price( $billing['amount'] ) ),
			(int) $billing['cycle'],
			esc_html( $billing['period'] )
		);

		if ( $billing['limit'] > 0 ) {
			$text .= ' × ' . (int) $billing['limit'];
		}

		return esc_html( $text );
	}

	public function column_trial( $item ): string {
		$trial = $item['trial_data'];
		if ( $trial['limit'] <= 0 ) {
			return '—';
		}

		return esc_html(
			sprintf(
				'%s × %d',
				$this->format_price( $trial['amount'] ),
				$trial['limit']
			)
		);
	}

	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	public function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$current_level  = (int) ( $this->filters['level'] ?? 0 );
		$current_status = $this->filters['status'] ?? '';

		?>
		<div class="alignleft actions">
			<select name="level">
				<option value=""><?php esc_html_e( 'All Levels', 'khm-membership' ); ?></option>
				<?php foreach ( $this->level_names as $level_id => $level_name ) : ?>
					<option value="<?php echo esc_attr( $level_id ); ?>" <?php selected( $current_level, $level_id ); ?>>
						<?php echo esc_html( $level_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'khm-membership' ); ?></option>
				<?php foreach ( [ 'active', 'cancelled', 'expired', 'past_due' ] as $status ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $current_status, $status ); ?>>
						<?php echo esc_html( ucfirst( $status ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'khm-membership' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function process_bulk_action(): void {
		$action = $this->current_action();

		if ( ! in_array( $action, [ 'cancel', 'delete', 'export' ], true ) ) {
			return;
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		$ids = isset( $_REQUEST['ids'] ) ? array_map( 'intval', (array) $_REQUEST['ids'] ) : [];
		$ids = array_values(
			array_filter(
				$ids,
				static fn( $id ) => $id > 0
			)
		);

		if ( empty( $ids ) ) {
			return;
		}

		switch ( $action ) {
			case 'cancel':
				$count = 0;
				foreach ( $ids as $id ) {
					if ( $this->repository->cancelById( $id, __( 'Cancelled via admin bulk action.', 'khm-membership' ) ) ) {
						$count++;
					}
				}

				if ( $count > 0 ) {
					add_settings_error(
						self::NOTICE_GROUP,
						'khm_members_bulk_cancel',
						sprintf(
							_n( 'Cancelled %d membership.', 'Cancelled %d memberships.', $count, 'khm-membership' ),
							$count
						),
						'success'
					);
				}
				break;

			case 'delete':
				$count = 0;
				foreach ( $ids as $id ) {
					if ( $this->repository->deleteById( $id ) ) {
						$count++;
					}
				}

				if ( $count > 0 ) {
					add_settings_error(
						self::NOTICE_GROUP,
						'khm_members_bulk_delete',
						sprintf(
							_n( 'Deleted %d membership.', 'Deleted %d memberships.', $count, 'khm-membership' ),
							$count
						),
						'success'
					);
				}
				break;

			case 'export':
				$this->export_memberships( $ids );
				exit;
		}
	}

	private function get_orderby_param(): string {
		$allowed = array_keys( $this->get_sortable_columns() );
		$requested = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';

		if ( in_array( $requested, $allowed, true ) ) {
			return $requested;
		}

		return 'start_date';
	}

	private function get_order_param(): string {
		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		return 'ASC' === $order ? 'ASC' : 'DESC';
	}

	private function action_url( string $action, int $membership_id, array $extra = [] ): string {
		$args = array_merge(
			$extra,
			[
				'action'        => $action,
				'membership_id' => $membership_id,
			]
		);

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			$action . '_' . $membership_id
		);
	}

	private function export_memberships( array $ids ): void {
		$rows = $this->repository->getMany( $ids );

		if ( empty( $rows ) ) {
			return;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=khm-members-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		fputcsv(
			$output,
			[
				'Membership ID',
				'User ID',
				'Username',
				'Display Name',
				'Email',
				'Membership Level',
				'Status',
				'Start Date',
				'End Date',
				'Initial Payment',
				'Billing Amount',
				'Billing Cycle',
				'Billing Limit',
				'Trial Amount',
				'Trial Limit',
			]
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				[
					$row['id'],
					$row['user_id'],
					$row['user_login'],
					$row['display_name'],
					$row['user_email'],
					$row['level_name'],
					$row['status'],
					$row['start_date'],
					$row['end_date'],
					number_format( (float) $row['initial_payment'], 2 ),
					number_format( (float) $row['billing_amount'], 2 ),
					$row['cycle_number'] . ' ' . $row['cycle_period'],
					$row['billing_limit'],
					number_format( (float) $row['trial_amount'], 2 ),
					$row['trial_limit'],
				]
			);
		}

		fclose( $output );
	}

	private function format_price( float $amount ): string {
		if ( function_exists( 'khm_format_price' ) ) {
			return khm_format_price( $amount );
		}

		return '$' . number_format_i18n( $amount, 2 );
	}
}
