<?php
namespace KHM\Hooks;

use KHM\Services\DiscountCodeService;
use KHM\Services\LevelRepository;

/**
 * Discount Code Integration Hooks
 *
 * Provides WordPress hooks for integrating discount codes into checkout flow.
 * Can be extended when checkout pages are built.
 */
class DiscountCodeHooks {
	/**
	 * Discount Code Service instance.
	 *
	 * @var DiscountCodeService
	 */
	private $discount_service;
	private LevelRepository $level_repo;

	/**
	 * Session key for storing applied discount code.
	 *
	 * @var string
	 */
	const SESSION_KEY = 'khm_discount_code';

	/**
	 * Constructor.
	 *
	 * @param DiscountCodeService $discount_service The discount code service.
	 */
	public function __construct( DiscountCodeService $discount_service, ?LevelRepository $level_repo = null ) {
		$this->discount_service = $discount_service;
		$this->level_repo       = $level_repo ?: new LevelRepository();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// AJAX endpoint for validating discount codes.
		add_action( 'wp_ajax_khm_validate_discount_code', array( $this, 'ajax_validate_discount_code' ) );
		add_action( 'wp_ajax_nopriv_khm_validate_discount_code', array( $this, 'ajax_validate_discount_code' ) );

		// Apply discount to order total (legacy hook).
		add_filter( 'khm_order_total', array( $this, 'apply_discount_to_order' ), 10, 3 );

		// Apply discount to checkout order data.
		add_filter( 'khm_checkout_order_data', array( $this, 'apply_discount_to_checkout' ), 10, 3 );

		// Store discount code with order.
		add_action( 'khm_order_created', array( $this, 'store_discount_with_order' ), 10, 2 );
		add_action( 'khm_checkout_after_payment', array( $this, 'store_discount_after_checkout' ), 10, 3 );

		// Enqueue scripts for checkout page.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_scripts' ) );
	}

	/**
	 * AJAX handler for validating discount codes.
	 *
	 * @return void
	 */
	public function ajax_validate_discount_code(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'khm_discount_code' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
			return;
		}

		// Basic rate limiting per IP to prevent abuse.
		$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$key = 'khm_dc_rate_' . md5( (string) $ip );
		$cnt = (int) get_transient( $key );
		if ( $cnt >= 20 ) { // Max 20 validations per minute per IP.
			wp_send_json_error( array( 'message' => 'Too many attempts. Please wait a minute and try again.' ), 429 );
			return;
		}
		set_transient( $key, $cnt + 1, 60 );

		$code     = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$level_id = isset( $_POST['level_id'] ) ? (int) $_POST['level_id'] : 0;
		$user_id  = get_current_user_id();

		if ( empty( $code ) || $level_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ) );
			return;
		}

		// Validate the discount code.
		$validation = $this->discount_service->validate_code( $code, $level_id, $user_id );

		if ( $validation['valid'] ) {
			// Store code in session.
			$this->set_session_discount_code( $code );

			// Build structured response for UI updates.
			$code_object = $validation['code'];
			$trial_info = $this->discount_service->get_trial_info( $code_object );
			$first_only = ! empty( $code_object->first_payment_only );
			$recurring = null;
			if ( ! empty( $code_object->recurring_discount_type ) && ! empty( $code_object->recurring_discount_amount ) ) {
				$recurring = array(
					'type'   => $code_object->recurring_discount_type,
					'amount' => (float) $code_object->recurring_discount_amount,
				);
			}

			// Fetch level initial payment for due today calculation
			$initial = 0.0;
			if ( $level_id > 0 ) {
				$level = $this->level_repo->get( $level_id );
				if ( $level ) {
					$initial = (float) ( $level->initial_payment ?? 0 );
				}
			}

			$due_today = $initial;
			if ( $trial_info && ! empty( $trial_info['trial_days'] ) ) {
				$due_today = (float) ( $trial_info['trial_amount'] ?? 0 );
			} elseif ( $first_only ) {
				$breakdown = $this->discount_service->get_discount_breakdown( $initial, $code_object );
				$due_today = (float) ( $breakdown['final'] ?? $initial );
			}

			$response = array(
				'message' => $validation['message'],
				'code'    => $code,
				'trial'   => $trial_info ? array(
					'days'   => (int) $trial_info['trial_days'],
					'amount' => (float) $trial_info['trial_amount'],
				) : null,
				'first_payment_only' => $first_only,
				'recurring_discount' => $recurring,
				'initial_payment'    => $initial,
				'due_today'          => $due_today,
			);

			wp_send_json_success( $response );
		} else {
			wp_send_json_error( array( 'message' => $validation['message'] ) );
		}
	}

	/**
	 * Apply discount to order total.
	 *
	 * @param float $total The original order total.
	 * @param int   $level_id The membership level ID.
	 * @param int   $user_id The user ID.
	 * @return float The discounted total.
	 */
	public function apply_discount_to_order( float $total, int $level_id, int $user_id ): float {
		$code = $this->get_session_discount_code();

		if ( empty( $code ) ) {
			return $total;
		}

		// Validate the code again before applying.
		$validation = $this->discount_service->validate_code( $code, $level_id, $user_id );

		if ( ! $validation['valid'] ) {
			return $total;
		}

		// Apply the discount.
		return $this->discount_service->apply_discount( $total, $validation['code'] );
	}

	/**
	 * Store discount code with order after creation.
	 *
	 * @param int   $order_id The order ID.
	 * @param array $order_data The order data.
	 * @return void
	 */
	public function store_discount_with_order( int $order_id, array $order_data ): void {
		$code = $this->get_session_discount_code();

		if ( empty( $code ) ) {
			return;
		}

		$user_id  = isset( $order_data['user_id'] ) ? (int) $order_data['user_id'] : 0;
		$level_id = isset( $order_data['membership_id'] ) ? (int) $order_data['membership_id'] : 0;

		// Validate one more time.
		$validation = $this->discount_service->validate_code( $code, $level_id, $user_id );

		if ( $validation['valid'] ) {
			// Track usage.
			$this->discount_service->track_usage( $validation['code']->id, $user_id, $order_id );

			// Clear session.
			$this->clear_session_discount_code();
		}
	}

	/**
	 * Apply discount to checkout order data.
	 *
	 * @param array    $order_data The order data.
	 * @param object   $level The membership level.
	 * @param \WP_User $user The current user.
	 * @return array Modified order data with discount applied.
	 */
	public function apply_discount_to_checkout( array $order_data, object $level, \WP_User $user ): array {
		$code = $this->get_session_discount_code();

		if ( empty( $code ) ) {
			return $order_data;
		}

		$level_id = isset( $order_data['membership_id'] ) ? (int) $order_data['membership_id'] : 0;
		$user_id  = $user->ID;

		// Validate the code.
		$validation = $this->discount_service->validate_code( $code, $level_id, $user_id );

		if ( ! $validation['valid'] ) {
			return $order_data;
		}

		$code_object = $validation['code'];

		// Get discount breakdown.
		$breakdown = $this->discount_service->get_discount_breakdown(
			$order_data['subtotal'],
			$code_object
		);

		// Apply discount to order data.
		$order_data['subtotal']        = $breakdown['final'];
		$order_data['discount_code']   = $code;
		$order_data['discount_id']     = $code_object->id;
		$order_data['discount_amount'] = $breakdown['discount'];

		// Add trial information if present.
		$trial_info = $this->discount_service->get_trial_info( $code_object );
		if ( $trial_info ) {
			$order_data['trial_days']   = $trial_info['trial_days'];
			$order_data['trial_amount'] = $trial_info['trial_amount'];
		}

		// Add first payment only flag.
		if ( ! empty( $code_object->first_payment_only ) ) {
			$order_data['discount_first_payment_only'] = true;
		}

		// Add recurring discount information.
		if ( ! empty( $code_object->recurring_discount_type ) && ! empty( $code_object->recurring_discount_amount ) ) {
			$order_data['recurring_discount_type']   = $code_object->recurring_discount_type;
			$order_data['recurring_discount_amount'] = $code_object->recurring_discount_amount;
			
			// Calculate recurring billing amount with discount applied.
			if ( isset( $order_data['billing_amount'] ) && $order_data['billing_amount'] > 0 ) {
				$order_data['billing_amount_original'] = $order_data['billing_amount'];
				$order_data['billing_amount'] = $this->discount_service->apply_recurring_discount(
					$order_data['billing_amount'],
					$code_object
				);
			}
		}

		// Recalculate total with tax.
		$order_data['total'] = $order_data['subtotal'] + ( $order_data['tax'] ?? 0 );

		return $order_data;
	}

	/**
	 * Store discount after checkout completes.
	 *
	 * @param object   $order The order object.
	 * @param object   $level The membership level.
	 * @param \WP_User $user The current user.
	 * @return void
	 */
	public function store_discount_after_checkout( object $order, object $level, \WP_User $user ): void {
		$code = $this->get_session_discount_code();

		if ( empty( $code ) ) {
			return;
		}

		$level_id = isset( $order->membership_id ) ? (int) $order->membership_id : 0;
		$user_id  = $user->ID;
		$order_id = isset( $order->id ) ? (int) $order->id : 0;

		// Validate one more time.
		$validation = $this->discount_service->validate_code( $code, $level_id, $user_id );

		if ( $validation['valid'] && $order_id > 0 ) {
			// Track usage.
			$this->discount_service->track_usage( $validation['code']->id, $user_id, $order_id );

			// Clear session.
			$this->clear_session_discount_code();
		}
	}

	/**
	 * Enqueue scripts for checkout page.
	 *
	 * @return void
	 */
	public function enqueue_checkout_scripts(): void {
		// Only enqueue on checkout page (when it exists).
		if ( ! is_page( 'checkout' ) && ! is_page( 'membership-checkout' ) ) {
			return;
		}

		wp_enqueue_script(
			'khm-discount-codes',
			plugins_url( 'js/discount-codes.js', dirname( dirname( __FILE__ ) ) ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'khm-discount-codes',
			'khmDiscountCodes',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'khm_discount_code' ),
			)
		);
	}

	/**
	 * Store discount code in session.
	 *
	 * @param string $code The discount code.
	 * @return void
	 */
	private function set_session_discount_code( string $code ): void {
		if ( ! session_id() ) {
			session_start();
		}
		$_SESSION[ self::SESSION_KEY ] = $code;
	}

	/**
	 * Get discount code from session.
	 *
	 * @return string The discount code or empty string.
	 */
	private function get_session_discount_code(): string {
		if ( ! session_id() ) {
			session_start();
		}
		return isset( $_SESSION[ self::SESSION_KEY ] ) ? sanitize_text_field( $_SESSION[ self::SESSION_KEY ] ) : '';
	}

	/**
	 * Clear discount code from session.
	 *
	 * @return void
	 */
	private function clear_session_discount_code(): void {
		if ( ! session_id() ) {
			session_start();
		}
		unset( $_SESSION[ self::SESSION_KEY ] );
	}
}
