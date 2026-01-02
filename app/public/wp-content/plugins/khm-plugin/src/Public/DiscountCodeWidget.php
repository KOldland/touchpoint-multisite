<?php
/**
 * Discount Code Widget
 *
 * Renders discount code input field and applies discount to checkout.
 *
 * @package KHM\Public
 */

namespace KHM\Public;

use KHM\Services\DiscountCodeService;

class DiscountCodeWidget {

	/**
	 * Discount code service.
	 *
	 * @var DiscountCodeService
	 */
	private DiscountCodeService $discount_service;

	/**
	 * Constructor.
	 *
	 * @param DiscountCodeService $discount_service Discount code service.
	 */
	public function __construct( DiscountCodeService $discount_service ) {
		$this->discount_service = $discount_service;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Render discount field on checkout page.
		add_action( 'khm_checkout_after_billing_fields', array( $this, 'render' ), 10, 2 );

		// Enqueue scripts only on checkout pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue discount code scripts.
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->is_checkout_page() ) {
			return;
		}

		wp_enqueue_script(
			'khm-discount-codes',
			plugins_url( 'js/discount-codes.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'khm-discount-codes',
			'khmDiscountCode',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'khm_discount_code' ),
			)
		);
	}

	/**
	 * Check if current page is checkout.
	 *
	 * @return bool
	 */
	private function is_checkout_page(): bool {
		global $post;
		return $post && has_shortcode( $post->post_content, 'khm_checkout' );
	}

	/**
	 * Render discount code field.
	 *
	 * @param \WP_User     $user  Current user.
	 * @param object|null  $level Selected membership level.
	 */
	public function render( $user, $level ): void {
		// Only show discount field if a level is selected.
		if ( ! $level ) {
			return;
		}

		// Check if discount code is already applied in session.
		$applied_code = $this->get_applied_discount();
		$is_applied   = ! empty( $applied_code );

		?>
		<div class="khm-checkout-discount" data-testid="khm-discount-widget">
			<h4><?php esc_html_e( 'Discount Code', 'khm-membership' ); ?></h4>

			<?php if ( $is_applied ) : ?>
				<!-- Discount already applied -->
				<div class="khm-discount-applied" data-testid="khm-discount-applied">
					<div class="khm-form-row">
						<label><?php esc_html_e( 'Applied Code', 'khm-membership' ); ?></label>
						<div class="khm-discount-display">
							<strong><?php echo esc_html( $applied_code['code'] ); ?></strong>
							<?php if ( ! empty( $applied_code['discount_amount'] ) ) : ?>
								<span class="khm-discount-amount">
									-<?php echo esc_html( $this->format_discount( $applied_code ) ); ?>
								</span>
							<?php endif; ?>
						</div>
						<button type="button" id="khm_remove_discount" class="khm-button khm-button-link">
							<?php esc_html_e( 'Remove', 'khm-membership' ); ?>
						</button>
					</div>
					<input type="hidden" name="discount_code" value="<?php echo esc_attr( $applied_code['code'] ); ?>">
				</div>
			<?php else : ?>
				<!-- Discount input field -->
				<div class="khm-discount-input-wrapper">
					<div class="khm-form-row khm-discount-input-row">
						<label for="khm_discount_code"><?php esc_html_e( 'Enter Code', 'khm-membership' ); ?></label>
						<div class="khm-discount-input-group">
							<input 
								type="text" 
							id="khm_discount_code" 
							data-testid="khm-discount-code" 
								name="discount_code_input" 
								placeholder="<?php esc_attr_e( 'DISCOUNT10', 'khm-membership' ); ?>"
								autocomplete="off"
							>
							<button 
								type="button" 
							id="khm_apply_discount" 
							data-testid="khm-apply-discount" 
								class="khm-button khm-button-secondary"
								data-level-id="<?php echo esc_attr( $level->id ); ?>"
							>
								<?php esc_html_e( 'Apply', 'khm-membership' ); ?>
							</button>
						</div>
					</div>
					<div id="khm_discount_message" class="khm-discount-message" role="status" aria-live="polite"></div>
				</div>
			<?php endif; ?>

			<?php do_action( 'khm_checkout_after_discount_field', $user, $level ); ?>
		</div>
		<?php
	}

	/**
	 * Get applied discount from session.
	 *
	 * @return array|null
	 */
    private function get_applied_discount(): ?array {
        if ( ! session_id() ) {
            return null;
        }
        return $_SESSION['khm_discount_code'] ?? null;
    }

	/**
	 * Format discount for display.
	 *
	 * @param array $discount Discount data.
	 * @return string
	 */
	private function format_discount( array $discount ): string {
		if ( ! empty( $discount['discount_type'] ) ) {
			if ( $discount['discount_type'] === 'percent' ) {
				return $discount['discount_amount'] . '%';
			} elseif ( $discount['discount_type'] === 'amount' ) {
				return '$' . number_format( $discount['discount_amount'], 2 );
			}
		}

		// Fallback - calculate from original and final price.
		if ( ! empty( $discount['original_price'] ) && ! empty( $discount['final_price'] ) ) {
			$saved = $discount['original_price'] - $discount['final_price'];
			return '$' . number_format( $saved, 2 );
		}

		return '';
	}
}
