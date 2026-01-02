<?php
namespace KHM\Services;

use KHM\Models\DiscountCode;

/**
 * Discount Code Service
 *
 * Handles discount code validation, application, and usage tracking.
 */
class DiscountCodeService {

	private DiscountCodeRepository $repository;

	public function __construct( ?DiscountCodeRepository $repository = null ) {
		$this->repository = $repository ?: new DiscountCodeRepository();
	}

	/**
	 * Validate a discount code for a given level and user.
	 *
	 * @param string $code The discount code to validate.
	 * @param int    $level_id The membership level ID.
	 * @param int    $user_id The user ID.
	 * @return array Array with 'valid' (bool) and 'message' (string) keys. If valid, includes 'code' object.
	 */
	public function validate_code( string $code, int $level_id, int $user_id ): array {
		// Get the code from database.
		$discount_code = $this->get_code_by_name( $code );

		if ( ! $discount_code ) {
			return array(
				'valid'   => false,
				'message' => 'Invalid discount code.',
			);
		}

		// Check if code is active.
		if ( 'active' !== $discount_code->status ) {
			return array(
				'valid'   => false,
				'message' => 'This discount code is not active.',
			);
		}

		// Check date range.
		$now = current_time( 'mysql' );

		if ( $discount_code->start_date && $now < $discount_code->start_date ) {
			return array(
				'valid'   => false,
				'message' => 'This discount code is not yet valid.',
			);
		}

		if ( $discount_code->end_date && $now > $discount_code->end_date ) {
			return array(
				'valid'   => false,
				'message' => 'This discount code has expired.',
			);
		}

		// Check total usage limit.
		if ( null !== $discount_code->usage_limit && $discount_code->times_used >= $discount_code->usage_limit ) {
			return array(
				'valid'   => false,
				'message' => 'This discount code has reached its usage limit.',
			);
		}

		// Check per-user usage limit.
		if ( null !== $discount_code->per_user_limit ) {
			$user_usage_count = $this->get_user_usage_count( $discount_code->id, $user_id );

			if ( $user_usage_count >= $discount_code->per_user_limit ) {
				return array(
					'valid'   => false,
					'message' => 'You have already used this discount code the maximum number of times.',
				);
			}
		}

		// Check if code applies to this level.
		if ( ! $this->code_applies_to_level( $discount_code, $level_id ) ) {
			return array(
				'valid'   => false,
				'message' => 'This discount code is not valid for the selected membership level.',
			);
		}

		return array(
			'valid'   => true,
			'message' => 'Discount code applied successfully.',
			'code'    => $discount_code,
            'trial_days' => $discount_code->trial_days ?? null,
            'trial_amount' => $discount_code->trial_amount ?? null,
            'first_payment_only' => $discount_code->first_payment_only ?? 0,
            'recurring_discount_type' => $discount_code->recurring_discount_type ?? null,
            'recurring_discount_amount' => $discount_code->recurring_discount_amount ?? null,
		);
	}

	/**
	 * Apply discount to an amount.
	 *
	 * @param float  $amount The original amount.
	 * @param object $code The discount code object.
	 * @return float The discounted amount.
	 */
	public function apply_discount( float $amount, object $code ): float {
		// First payment only logic (if not recurring, just apply as normal)
		if (!empty($code->first_payment_only)) {
			// Only apply discount if this is the first payment (caller must check context)
			// For now, treat as normal unless recurring context is added
		}

		if ('percent' === $code->type) {
			$discount = $amount * ($code->value / 100);
			return max(0, $amount - $discount);
		}

		// Fixed amount discount.
		return max(0, $amount - $code->value);
	}

	/**
	 * Apply recurring discount to an amount (for subscriptions).
	 * @param float $amount
	 * @param object $code
	 * @return float
	 */
	public function apply_recurring_discount(float $amount, object $code): float {
		if (!empty($code->recurring_discount_type) && !empty($code->recurring_discount_amount)) {
			if ($code->recurring_discount_type === 'percent') {
				$discount = $amount * ($code->recurring_discount_amount / 100);
				return max(0, $amount - $discount);
			} elseif ($code->recurring_discount_type === 'amount') {
				return max(0, $amount - $code->recurring_discount_amount);
			}
		}
		// No recurring discount, return original amount
		return $amount;
	}

	/**
	 * Get trial info from code.
	 * @param object $code
	 * @return array|null
	 */
	public function get_trial_info(object $code): ?array {
		if (!empty($code->trial_days) || !empty($code->trial_amount)) {
			return [
				'trial_days' => (int)($code->trial_days ?? 0),
				'trial_amount' => (float)($code->trial_amount ?? 0.0)
			];
		}
		return null;
	}

	/**
	 * Track usage of a discount code.
	 *
	 * @param int $code_id The discount code ID.
	 * @param int $user_id The user ID.
	 * @param int $order_id The order ID.
	 * @return bool True on success, false on failure.
	 */
	public function track_usage( int $code_id, int $user_id, int $order_id ): bool {
		global $wpdb;

		$audit_table = $wpdb->prefix . 'khm_discount_codes_uses';

		// Get IP address and session ID for fraud detection.
		$ip_address = $this->get_client_ip();
		$session_id = session_id() ?: null;

		// Insert usage record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Usage tracking, not cacheable.
		$result = $wpdb->insert(
			$audit_table,
			array(
				'discount_code_id' => $code_id,
				'user_id'          => $user_id,
				'order_id'         => $order_id,
				'used_at'          => current_time( 'mysql' ),
				'ip_address'       => $ip_address,
				'session_id'       => $session_id,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( $result ) {
			// Increment times_used counter.
			$codes_table = $wpdb->prefix . 'khm_discount_codes';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Usage counter update.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$codes_table} SET times_used = times_used + 1 WHERE id = %d",
					$code_id
				)
			);
		}

		return (bool) $result;
	}

	/**
	 * Get a discount code by name.
	 *
	 * @param string $code The discount code.
	 * @return DiscountCode|null The code object or null if not found.
	 */
	public function get_code_by_name( string $code ): ?DiscountCode {
		return $this->repository->find_by_code( $code );
	}

	/**
	 * Check if a user can use a discount code.
	 *
	 * @param string $code The discount code.
	 * @param int    $user_id The user ID.
	 * @param int    $level_id The membership level ID.
	 * @return bool True if the user can use the code, false otherwise.
	 */
	public function can_use_code( string $code, int $user_id, int $level_id ): bool {
		$validation = $this->validate_code( $code, $level_id, $user_id );
		return $validation['valid'];
	}

	/**
	 * Get the number of times a user has used a discount code.
	 *
	 * @param int $code_id The discount code ID.
	 * @param int $user_id The user ID.
	 * @return int The usage count.
	 */
	private function get_user_usage_count( int $code_id, int $user_id ): int {
		global $wpdb;
		$audit_table = $wpdb->prefix . 'khm_discount_codes_uses';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Usage count lookup.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$audit_table} WHERE discount_code_id = %d AND user_id = %d",
				$code_id,
				$user_id
			)
		);
	}

	/**
	 * Check if a discount code applies to a membership level.
	 *
	 * @param object $code The discount code object.
	 * @param int    $level_id The membership level ID.
	 * @return bool True if the code applies to the level, false otherwise.
	 */
	private function code_applies_to_level( object $code, int $level_id ): bool {
		if ( $code instanceof DiscountCode ) {
			if ( empty( $code->level_ids ) ) {
				return true;
			}

			return in_array( $level_id, $code->level_ids, true );
		}

		// If no levels specified, code applies to all levels.
		if ( empty( $code->levels ) ) {
			return true;
		}

		// Parse CSV of level IDs.
		$applicable_levels = array_map( 'intval', array_map( 'trim', explode( ',', $code->levels ) ) );

		return in_array( $level_id, $applicable_levels, true );
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string|null The IP address or null if not available.
	 */
	private function get_client_ip(): ?string {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// If multiple IPs, take the first one.
				if ( strpos( $ip, ',' ) !== false ) {
					$ip_parts = explode( ',', $ip );
					$ip       = trim( $ip_parts[0] );
				}

				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return null;
	}

	/**
	 * Calculate discount breakdown for display.
	 *
	 * @param float  $original_amount The original amount before discount.
	 * @param object $code The discount code object.
	 * @return array Array with 'original', 'discount', 'final' keys.
	 */
	public function get_discount_breakdown( float $original_amount, object $code ): array {
		$final_amount = $this->apply_discount( $original_amount, $code );
		$discount     = $original_amount - $final_amount;

		return array(
			'original' => $original_amount,
			'discount' => $discount,
			'final'    => $final_amount,
		);
	}

	/**
	 * Retrieve all discount codes for admin screens.
	 *
	 * @return DiscountCode[]
	 */
	public function list_codes(): array {
		return $this->repository->all();
	}

	/**
	 * Paginate discount codes with optional filters.
	 *
	 * @param array<string,mixed> $args {
	 *     @type string $search Search term.
	 *     @type string $status Status filter.
	 *     @type int    $limit  Items per page.
	 *     @type int    $offset Offset for pagination.
	 * }
	 * @return array{items:DiscountCode[],total:int}
	 */
	public function paginate_codes( array $args = array() ): array {
		$search = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';

		if ( ! in_array( $status, array( 'active', 'inactive', 'expired' ), true ) ) {
			$status = '';
		}

		$limit  = isset( $args['limit'] ) ? max( 0, (int) $args['limit'] ) : 0;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		return $this->repository->paginate(
			array(
				'search' => $search,
				'status' => $status,
				'limit'  => $limit,
				'offset' => $offset,
			)
		);
	}

	/**
	 * Retrieve a discount code by identifier.
	 *
	 * @param int $id Discount code ID.
	 * @return DiscountCode|null
	 */
	public function get_code( int $id ): ?DiscountCode {
		return $this->repository->find( $id );
	}

	/**
	 * Create a new discount code.
	 *
	 * @param array<string,mixed> $payload Data for the new code.
	 * @return DiscountCode|null
	 */
	public function create_code( array $payload ): ?DiscountCode {
		$level_ids = $payload['level_ids'] ?? array();
		unset( $payload['level_ids'] );

		$data = $this->sanitize_payload( $payload );

		return $this->repository->create( $data, (array) $level_ids );
	}

	/**
	 * Update an existing discount code.
	 *
	 * @param int                 $id      Discount code ID.
	 * @param array<string,mixed> $payload Updated data.
	 * @return bool
	 */
	public function update_code( int $id, array $payload ): bool {
		$level_ids = $payload['level_ids'] ?? array();
		unset( $payload['level_ids'] );

		$data = $this->sanitize_payload( $payload );

		return $this->repository->update( $id, $data, (array) $level_ids );
	}

	/**
	 * Delete a discount code.
	 *
	 * @param int $id Discount code ID.
	 * @return bool
	 */
	public function delete_code( int $id ): bool {
		return $this->repository->delete( $id );
	}

	/**
	 * Aggregate usage metrics for given codes.
	 *
	 * @param array<int> $ids Discount code IDs.
	 * @return array<int,array{total:int,unique_users:int}>
	 */
	public function get_usage_map( array $ids ): array {
		return $this->repository->get_usage_map( $ids );
	}

	/**
	 * Ensure only known fields are passed to the repository with proper types.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	private function sanitize_payload( array $payload ): array {
		$field_types = array(
			'code'                     => 'string',
			'type'                     => 'string',
			'value'                    => 'float',
			'start_date'               => 'string',
			'end_date'                 => 'string',
			'usage_limit'              => 'int',
			'per_user_limit'           => 'int',
			'status'                   => 'string',
			'times_used'               => 'int',
			'trial_days'               => 'int',
			'trial_amount'             => 'float',
			'first_payment_only'       => 'bool',
			'recurring_discount_type'  => 'string',
			'recurring_discount_amount'=> 'float',
		);

		$clean = array();
		foreach ( $field_types as $field => $type ) {
			if ( ! array_key_exists( $field, $payload ) ) {
				continue;
			}

			$value = $payload[ $field ];

			if ( null === $value || '' === $value ) {
				$clean[ $field ] = null;
				continue;
			}

			switch ( $type ) {
				case 'int':
					$clean[ $field ] = (int) $value;
					break;
				case 'float':
					$clean[ $field ] = round( (float) $value, 2 );
					break;
				case 'bool':
					$clean[ $field ] = $value ? 1 : 0;
					break;
				case 'string':
					$clean[ $field ] = (string) $value;
					break;
			}
		}

		return $clean;
	}
}
