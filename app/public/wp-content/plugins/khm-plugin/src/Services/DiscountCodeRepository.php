<?php
namespace KHM\Services;

use KHM\Models\DiscountCode;

/**
 * Data access layer responsible for discount code persistence.
 */
class DiscountCodeRepository {
	private string $codes_table;
	private string $levels_table;
	private string $uses_table;

	/**
	 * Mapping of column format placeholders.
	 *
	 * @var array<string,string>
	 */
	private array $format_map = array(
		'code'                     => '%s',
		'type'                     => '%s',
		'value'                    => '%f',
		'start_date'               => '%s',
		'end_date'                 => '%s',
		'usage_limit'              => '%d',
		'per_user_limit'           => '%d',
		'levels'                   => '%s',
		'status'                   => '%s',
		'times_used'               => '%d',
		'trial_days'               => '%d',
		'trial_amount'             => '%f',
		'first_payment_only'       => '%d',
		'recurring_discount_type'  => '%s',
		'recurring_discount_amount'=> '%f',
		'created_at'               => '%s',
		'updated_at'               => '%s',
	);

	public function __construct() {
		global $wpdb;
		$this->codes_table  = $wpdb->prefix . 'khm_discount_codes';
		$this->levels_table = $wpdb->prefix . 'khm_discount_codes_levels';
		$this->uses_table   = $wpdb->prefix . 'khm_discount_codes_uses';
	}

	/**
	 * Retrieve all discount codes ordered by creation date (newest first).
	 *
	 * @return DiscountCode[]
	 */
	public function all(): array {
		$result = $this->paginate();
		return $result['items'];
	}

	/**
	 * Paginate discount codes with optional search and status filters.
	 *
	 * @param array<string,mixed> $args {
	 *     @type string $search Search term (code).
	 *     @type string $status Status filter.
	 *     @type int    $limit  Number of items per page (0 = no limit).
	 *     @type int    $offset Offset for pagination.
	 * }
	 * @return array{items:DiscountCode[],total:int}
	 */
	public function paginate( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'search' => '',
			'status' => '',
			'limit'  => 0,
			'offset' => 0,
		);

		$args = array_merge( $defaults, $args );

		$where_clauses = array();
		$where_params  = array();

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_params[]  = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = 'code LIKE %s';
			$where_params[]  = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		$limit  = max( 0, (int) $args['limit'] );
		$offset = max( 0, (int) $args['offset'] );

		$query = "SELECT * FROM {$this->codes_table}{$where_sql} ORDER BY created_at DESC";

		$params = $where_params;
		if ( $limit > 0 ) {
			$query   .= ' LIMIT %d OFFSET %d';
			$params[] = $limit;
			$params[] = $offset;
		}

		$prepared = ! empty( $params ) ? $wpdb->prepare( $query, $params ) : $query;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin data fetch.
		$rows = $wpdb->get_results( $prepared );

		if ( empty( $rows ) ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$count_query   = "SELECT COUNT(*) FROM {$this->codes_table}{$where_sql}";
		$count_prepared = ! empty( $where_params ) ? $wpdb->prepare( $count_query, $where_params ) : $count_query;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin count query.
		$total = (int) $wpdb->get_var( $count_prepared );

		$ids       = array_map( static fn( $row ) => (int) $row->id, $rows );
		$level_map = $this->get_levels_for_codes( $ids );

		$items = array_map(
			function ( $row ) use ( $level_map ) {
				$code_id = (int) $row->id;
				$levels  = $level_map[ $code_id ] ?? array();
				return DiscountCode::from_row( $row, $levels );
			},
			$rows
		);

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Find a discount code by primary key.
	 *
	 * @param int $id Discount code ID.
	 * @return DiscountCode|null
	 */
	public function find( int $id ): ?DiscountCode {
		global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin lookup.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->codes_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $row ) {
			return null;
		}

		return DiscountCode::from_row( $row, $this->get_levels_for_codes( array( $id ) )[ $id ] ?? array() );
	}

	/**
	 * Find a discount code by its code string.
	 *
	 * @param string $code Discount code.
	 * @return DiscountCode|null
	 */
	public function find_by_code( string $code ): ?DiscountCode {
		global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Runtime lookup.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->codes_table} WHERE code = %s",
				$code
			)
		);

		if ( ! $row ) {
			return null;
		}

		$code_id = (int) $row->id;
		return DiscountCode::from_row( $row, $this->get_levels_for_codes( array( $code_id ) )[ $code_id ] ?? array() );
	}

	/**
	 * Persist a new discount code.
	 *
	 * @param array<string,mixed> $data Column data.
	 * @param array<int>          $level_ids Related membership level IDs.
	 * @return DiscountCode|null
	 */
	public function create( array $data, array $level_ids = array() ): ?DiscountCode {
		global $wpdb;

		$clean_levels       = $this->sanitize_ids( $level_ids );
		$data['levels']     = $this->levels_to_csv( $clean_levels );
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );

		$filtered = $this->filter_null_data( $data );
		$formats  = $this->build_formats( $filtered );

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin write operation.
		$result = $wpdb->insert(
			$this->codes_table,
			$filtered,
			$formats
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			return null;
		}

		$code_id = (int) $wpdb->insert_id;

		if ( ! $this->sync_levels( $code_id, $clean_levels ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			return null;
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

		return $this->find( $code_id );
	}

	/**
	 * Update an existing discount code.
	 *
	 * @param int                 $id Discount code ID.
	 * @param array<string,mixed> $data Column data.
	 * @param array<int>          $level_ids Related membership level IDs.
	 * @return bool
	 */
	public function update( int $id, array $data, array $level_ids = array() ): bool {
		global $wpdb;

		$clean_levels       = $this->sanitize_ids( $level_ids );
		$data['levels']     = $this->levels_to_csv( $clean_levels );
		$data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );

		$filtered = $this->filter_null_data( $data );
		$formats  = $this->build_formats( $filtered );

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin write operation.
		$result = $wpdb->update(
			$this->codes_table,
			$filtered,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			return false;
		}

		if ( ! $this->sync_levels( $id, $clean_levels ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			return false;
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

		return true;
	}

	/**
	 * Delete a discount code and its level mappings.
	 *
	 * @param int $id Discount code ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup mappings first.
		$wpdb->delete(
			$this->levels_table,
			array( 'discount_code_id' => $id ),
			array( '%d' )
		);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin delete.
		$result = $wpdb->delete(
			$this->codes_table,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			return false;
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

		return (bool) $result;
	}

	/**
	 * Return usage statistics for the provided discount codes.
	 *
	 * @param array<int> $code_ids Discount code IDs.
	 * @return array<int,array{total:int,unique_users:int}>
	 */
	public function get_usage_map( array $code_ids ): array {
		global $wpdb;

		$ids = $this->sanitize_ids( $code_ids );

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregation query for admin stats.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT discount_code_id, COUNT(*) AS total, COUNT(DISTINCT user_id) AS unique_users
				FROM {$this->uses_table}
				WHERE discount_code_id IN ($placeholders)
				GROUP BY discount_code_id",
				$ids
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row['discount_code_id'] ] = array(
				'total'        => (int) $row['total'],
				'unique_users' => (int) $row['unique_users'],
			);
		}

		return $map;
	}

	/**
	 * Get level IDs for discount codes.
	 *
	 * @param array<int> $code_ids Discount code IDs.
	 * @return array<int,array<int>>
	 */
	private function get_levels_for_codes( array $code_ids ): array {
		global $wpdb;

		$ids = $this->sanitize_ids( $code_ids );

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Level mapping lookup.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT discount_code_id, level_id FROM {$this->levels_table} WHERE discount_code_id IN ($placeholders)",
				$ids
			)
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$map = array();
		foreach ( $rows as $row ) {
			$code_id = (int) $row->discount_code_id;
			if ( ! isset( $map[ $code_id ] ) ) {
				$map[ $code_id ] = array();
			}
			$map[ $code_id ][] = (int) $row->level_id;
		}

		return $map;
	}

	/**
	 * Synchronise level mappings for a discount code.
	 *
	 * @param int        $code_id   Discount code ID.
	 * @param array<int> $level_ids Level IDs.
	 * @return bool
	 */
	private function sync_levels( int $code_id, array $level_ids ): bool {
		global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Clear previous mappings.
		$wpdb->delete(
			$this->levels_table,
			array( 'discount_code_id' => $code_id ),
			array( '%d' )
		);

		if ( empty( $level_ids ) ) {
			return true;
		}

		$values       = array();
		$placeholders = array();

		foreach ( $level_ids as $level_id ) {
			if ( $level_id <= 0 ) {
				continue;
			}

			$placeholders[] = '(%d, %d)';
			$values[]       = $code_id;
			$values[]       = $level_id;
		}

		if ( empty( $placeholders ) ) {
			return true;
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert mappings.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$this->levels_table} (discount_code_id, level_id) VALUES " . implode( ',', $placeholders ),
				$values
			)
		);

		return false !== $result;
	}

	/**
	 * Build format array aligned to the provided data keys.
	 *
	 * @param array<string,mixed> $data Prepared data.
	 * @return array<int,string>
	 */
	private function build_formats( array $data ): array {
		$formats = array();
		foreach ( $data as $key => $value ) {
			if ( ! isset( $this->format_map[ $key ] ) ) {
				continue;
			}
			$formats[] = $this->format_map[ $key ];
		}
		return $formats;
	}

	/**
	 * Remove null values so the database defaults can apply.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	private function filter_null_data( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( null === $value ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}

	/**
	 * Normalise identifiers to positive integers.
	 *
	 * @param array<int> $level_ids Raw values.
	 * @return array<int>
	 */
	private function sanitize_ids( array $level_ids ): array {
		$ids = array();
		foreach ( $level_ids as $level_id ) {
			$level_id = (int) $level_id;
			if ( $level_id > 0 ) {
				$ids[] = $level_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Convert level IDs to legacy CSV string.
	 *
	 * @param array<int> $level_ids Level IDs.
	 * @return string|null
	 */
	private function levels_to_csv( array $level_ids ): ?string {
		if ( empty( $level_ids ) ) {
			return null;
		}

		return implode( ',', $level_ids );
	}
}
