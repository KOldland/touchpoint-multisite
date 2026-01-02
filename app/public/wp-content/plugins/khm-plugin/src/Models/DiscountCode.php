<?php
namespace KHM\Models;

/**
 * DiscountCode model representing a discount and its metadata.
 */
class DiscountCode {
	public $id;
	public $code;
	public $type;
	public $value;
	public $start_date;
	public $end_date;
	public $usage_limit;
	public $per_user_limit;
	public $status;
	public $times_used;
	public $trial_days;
	public $trial_amount;
	public $first_payment_only;
	public $recurring_discount_type;
	public $recurring_discount_amount;
	public $created_at;
	public $updated_at;

	/**
	 * Level IDs the code applies to.
	 *
	 * @var array<int>
	 */
	public array $level_ids = array();

	/**
	 * Raw CSV string stored for back-compat as the `levels` column.
	 *
	 * @var string|null
	 */
	public ?string $levels_csv = null;

	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * Hydrate the model from a database row.
	 *
	 * @param object     $row       Database row.
	 * @param array<int> $level_ids Associated membership level IDs.
	 * @return self
	 */
	public static function from_row( object $row, array $level_ids = array() ): self {
		$instance             = new self( (array) $row );
		$instance->level_ids  = array_map( 'intval', $level_ids );
		$instance->levels_csv = isset( $row->levels ) ? (string) $row->levels : null;

		if ( empty( $instance->level_ids ) && ! empty( $instance->levels_csv ) ) {
			$csv_levels = array_map( 'trim', explode( ',', $instance->levels_csv ) );
			$instance->level_ids = array_values(
				array_unique(
					array_map( 'intval', array_filter( $csv_levels ) )
				)
			);
		}

		return $instance;
	}

	/**
	 * Ensure level IDs are stored as integers without duplicates.
	 *
	 * @param array<int> $levels Level identifiers.
	 * @return void
	 */
	public function set_levels( array $levels ): void {
		$this->level_ids = array_values(
			array_unique(
				array_map( 'intval', $levels )
			)
		);
	}

	/**
	 * Export a plain array representation.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		$data                 = get_object_vars( $this );
		$data['level_ids']    = $this->level_ids;
		$data['levels_csv']   = $this->levels_csv;
		return $data;
	}
}
