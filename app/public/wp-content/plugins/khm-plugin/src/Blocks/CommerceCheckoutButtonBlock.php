<?php

namespace KHM\Blocks;

class CommerceCheckoutButtonBlock {
	public function register(): void {
		if ( did_action( 'init' ) ) {
			$this->register_block();
			return;
		}
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$plugin_file = dirname( __DIR__, 2 ) . '/khm-plugin.php';
		$plugin_path = plugin_dir_path( $plugin_file );
		$plugin_url = plugin_dir_url( $plugin_file );

		$editor_js_path = $plugin_path . 'assets/js/commerce-checkout-button-block.js';
		if ( ! file_exists( $editor_js_path ) ) {
			return;
		}

		wp_register_script(
			'khm-commerce-checkout-button-block',
			$plugin_url . 'assets/js/commerce-checkout-button-block.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ],
			(string) filemtime( $editor_js_path ),
			true
		);

		register_block_type(
			'khm/commerce-checkout-button',
			[
				'editor_script' => 'khm-commerce-checkout-button-block',
				'render_callback' => [ $this, 'render_block' ],
				'attributes' => [
					'postId' => [
						'type' => 'number',
						'default' => 0,
					],
					'label' => [
						'type' => 'string',
						'default' => 'Buy Now',
					],
					'buttonClass' => [
						'type' => 'string',
						'default' => '',
					],
				],
			]
		);
	}

	/**
	 * @param array<string,mixed> $attributes
	 * @return string
	 */
	public function render_block( array $attributes = [] ): string {
		$post_id = absint( $attributes['postId'] ?? 0 );
		if ( $post_id <= 0 ) {
			return '';
		}

		$label = sanitize_text_field( (string) ( $attributes['label'] ?? '' ) );
		$button_class = sanitize_text_field( (string) ( $attributes['buttonClass'] ?? '' ) );

		$atts = [
			'post_id="' . esc_attr( (string) $post_id ) . '"',
		];

		if ( $label !== '' ) {
			$atts[] = 'label="' . esc_attr( $label ) . '"';
		}
		if ( $button_class !== '' ) {
			$atts[] = 'class="' . esc_attr( $button_class ) . '"';
		}

		return do_shortcode( '[khm_commerce_checkout_button ' . implode( ' ', $atts ) . ']' );
	}
}
