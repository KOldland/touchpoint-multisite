<?php

namespace KHM\Public;

class CommerceCheckoutButtonShortcode {
	private bool $assets_enqueued = false;

	public function register(): void {
		add_shortcode( 'khm_commerce_checkout_button', array( $this, 'render' ) );
	}

	/**
	 * Render shortcode:
	 * [khm_commerce_checkout_button post_id="123" label="Buy now" class="my-class"]
	 *
	 * @param array<string,mixed> $atts
	 * @return string
	 */
	public function render( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
				'label' => '',
				'class' => '',
			),
			(array) $atts,
			'khm_commerce_checkout_button'
		);

		$post_id = absint( $atts['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return '';
		}

		$this->enqueue_assets();

		$label = trim( (string) $atts['label'] );
		if ( $label === '' ) {
			$label = __( 'Buy Now', 'khm-membership' );
		}

		$css_classes = 'khm-commerce-checkout-trigger';
		$extra_class = trim( (string) $atts['class'] );
		if ( $extra_class !== '' ) {
			$parts = preg_split( '/\s+/', $extra_class );
			$parts = is_array( $parts ) ? $parts : array();
			$parts = array_filter( array_map( 'sanitize_html_class', $parts ) );
			if ( ! empty( $parts ) ) {
				$css_classes .= ' ' . implode( ' ', $parts );
			}
		}

		$image_url = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';

		ob_start();
		?>
		<button
			type="button"
			class="<?php echo esc_attr( $css_classes ); ?>"
			data-post-id="<?php echo esc_attr( $post_id ); ?>"
			data-title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"
			data-image-url="<?php echo esc_url( $image_url ); ?>">
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

		wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );

		$js_path = $plugin_path . 'assets/js/commerce-modal.js';
		if ( file_exists( $js_path ) ) {
			wp_enqueue_script(
				'khm-commerce-modal',
				$plugin_url . 'assets/js/commerce-modal.js',
				array( 'jquery', 'stripe-js' ),
				(string) filemtime( $js_path ),
				true
			);
		}

		$css_path = $plugin_path . 'assets/css/commerce-modal.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'khm-commerce-modal',
				$plugin_url . 'assets/css/commerce-modal.css',
				array(),
				(string) filemtime( $css_path )
			);
		}

		wp_localize_script(
			'khm-commerce-modal',
			'khmCommerce',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'khm_commerce' ),
				'stripe_key' => get_option( 'khm_stripe_publishable_key', '' ),
			)
		);

		$this->assets_enqueued = true;
	}
}

