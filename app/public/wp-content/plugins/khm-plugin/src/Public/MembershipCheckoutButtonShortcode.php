<?php

namespace KHM\Public;

use KHM\Services\LevelRepository;

class MembershipCheckoutButtonShortcode {
	private bool $assets_enqueued = false;

	public function register(): void {
		add_shortcode( 'khm_membership_checkout_button', array( $this, 'render' ) );
	}

	public function render( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'level_id' => 0,
				'label'    => '',
				'class'    => '',
			),
			(array) $atts,
			'khm_membership_checkout_button'
		);

		$level_id = absint( $atts['level_id'] ?? 0 );
		if ( $level_id <= 0 ) {
			return '';
		}

		$level = ( new LevelRepository() )->get( $level_id, true );
		if ( ! $level ) {
			return '';
		}

		$this->enqueue_assets();

		$label = trim( (string) $atts['label'] );
		if ( $label === '' ) {
			$label = __( 'Start Membership', 'khm-membership' );
		}

		$billing_amount = isset( $level->billing_amount ) ? (float) $level->billing_amount : 0.0;
		$interval       = isset( $level->cycle_period ) ? strtolower( (string) $level->cycle_period ) : 'month';
		$description    = isset( $level->description ) ? (string) $level->description : '';
		$monthly_credits = isset( $level->monthly_credits ) ? (int) $level->monthly_credits : 0;

		$price_display = '$' . number_format( $billing_amount, 2 );
		if ( $billing_amount <= 0 ) {
			$price_display = __( 'Free', 'khm-membership' );
		}

		$extra_class = trim( (string) $atts['class'] );
		$css_classes = 'khm-shortcode-membership-button';
		if ( $extra_class !== '' ) {
			$parts = preg_split( '/\s+/', $extra_class );
			$parts = is_array( $parts ) ? $parts : array();
			$parts = array_filter( array_map( 'sanitize_html_class', $parts ) );
			if ( ! empty( $parts ) ) {
				$css_classes .= ' ' . implode( ' ', $parts );
			}
		}

		ob_start();
		?>
		<button
			class="khm-checkout-trigger <?php echo esc_attr( $css_classes ); ?>"
			data-membership-level-id="<?php echo esc_attr( $level_id ); ?>"
			data-membership-level-name="<?php echo esc_attr( (string) ( $level->name ?? '' ) ); ?>"
			data-membership-price="<?php echo esc_attr( (string) $billing_amount ); ?>"
			data-membership-price-display="<?php echo esc_attr( $price_display ); ?>"
			data-membership-interval="<?php echo esc_attr( $interval ); ?>"
			data-purchase-type="subscription"
			data-membership-description="<?php echo esc_attr( $description ); ?>"
			data-membership-monthly-credits="<?php echo esc_attr( $monthly_credits ); ?>">
			<?php echo esc_html( $label ); ?>
		</button>
		<?php
		return (string) ob_get_clean();
	}

	private function enqueue_assets(): void {
		if ( $this->assets_enqueued ) {
			return;
		}

		$plugin_file = dirname( __DIR__, 2 ) . '/khm-plugin.php';
		$plugin_url  = plugin_dir_url( $plugin_file );
		$plugin_path = plugin_dir_path( $plugin_file );

		$css_path = $plugin_path . 'assets/css/membership-modal.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'khm-membership-modal',
				$plugin_url . 'assets/css/membership-modal.css',
				array(),
				(string) filemtime( $css_path )
			);
		}

		$js_path = $plugin_path . 'assets/js/membership-modal.js';
        $helper_path = $plugin_path . 'assets/js/checkout-ui-helpers.js';
        if ( file_exists( $helper_path ) ) {
            wp_enqueue_script(
                'khm-checkout-ui-helpers',
                $plugin_url . 'assets/js/checkout-ui-helpers.js',
                array(),
                (string) filemtime( $helper_path ),
                true
            );
        }
		if ( file_exists( $js_path ) ) {
			wp_enqueue_script(
				'khm-membership-modal',
				$plugin_url . 'assets/js/membership-modal.js',
				array( 'jquery', 'khm-checkout-ui-helpers' ),
				(string) filemtime( $js_path ),
				true
			);

			wp_localize_script(
				'khm-membership-modal',
				'khmMembershipModal',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'khm_membership_checkout_nonce' ),
					'isLoggedIn' => is_user_logged_in(),
					'strings' => array(
						'error_generic' => __( 'An error occurred. Please try again.', 'khm-membership' ),
						'loading'       => __( 'Loading...', 'khm-membership' ),
						'processing'    => __( 'Processing...', 'khm-membership' ),
					),
				)
			);
		}

		$this->assets_enqueued = true;
	}
}
