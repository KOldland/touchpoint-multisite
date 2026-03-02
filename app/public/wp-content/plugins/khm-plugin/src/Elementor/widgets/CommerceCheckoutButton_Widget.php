<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/**
 * Commerce Checkout Button Widget
 *
 * Renders a button that opens the unified commerce modal for a target post.
 */
class CommerceCheckoutButton_Widget extends Widget_Base {

	public function get_name() {
		return 'khm_commerce_checkout_button';
	}

	public function get_title() {
		return __( 'Commerce Checkout Button', 'khm-membership' );
	}

	public function get_icon() {
		return 'eicon-cart';
	}

	public function get_categories() {
		return [ 'touchpoint', 'theme-elements' ];
	}

	public function get_keywords() {
		return [ 'commerce', 'checkout', 'buy', 'article', 'khm', 'button', 'modal' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Button Content', 'khm-membership' ),
			]
		);

		$this->add_control(
			'post_id',
			[
				'label' => __( 'Post ID', 'khm-membership' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'description' => __( 'Post to purchase in the commerce modal.', 'khm-membership' ),
			]
		);

		$this->add_control(
			'button_text',
			[
				'label' => __( 'Button Text', 'khm-membership' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Buy Now', 'khm-membership' ),
			]
		);

		$this->add_control(
			'css_classes',
			[
				'label' => __( 'Additional CSS Classes', 'khm-membership' ),
				'type' => Controls_Manager::TEXT,
				'description' => __( 'Optional additional classes for the button.', 'khm-membership' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_button',
			[
				'label' => __( 'Button Style', 'khm-membership' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'button_typography',
				'selector' => '{{WRAPPER}} .khm-commerce-checkout-trigger',
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label' => __( 'Text Color', 'khm-membership' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .khm-commerce-checkout-trigger' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_background_color',
			[
				'label' => __( 'Background Color', 'khm-membership' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#0b4c6b',
				'selectors' => [
					'{{WRAPPER}} .khm-commerce-checkout-trigger' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'button_border',
				'selector' => '{{WRAPPER}} .khm-commerce-checkout-trigger',
			]
		);

		$this->add_control(
			'button_border_radius',
			[
				'label' => __( 'Border Radius', 'khm-membership' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .khm-commerce-checkout-trigger' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'button_padding',
			[
				'label' => __( 'Padding', 'khm-membership' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .khm-commerce-checkout-trigger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'default' => [
					'top' => '12',
					'right' => '24',
					'bottom' => '12',
					'left' => '24',
					'unit' => 'px',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$post_id = absint( $settings['post_id'] ?? 0 );
		$button_text = trim( (string) ( $settings['button_text'] ?? '' ) );
		$button_text = $button_text !== '' ? $button_text : __( 'Buy Now', 'khm-membership' );
		$css_classes = trim( (string) ( $settings['css_classes'] ?? '' ) );

		if ( $post_id <= 0 ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#d00;">' . esc_html__( '⚠️ Please set a Post ID for Commerce Checkout Button.', 'khm-membership' ) . '</p>';
			}
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#d00;">' . esc_html__( '⚠️ Selected post not found or not published.', 'khm-membership' ) . '</p>';
			}
			return;
		}

		$this->enqueue_commerce_assets();

		$class_string = 'khm-commerce-checkout-trigger';
		if ( $css_classes !== '' ) {
			$parts = preg_split( '/\s+/', $css_classes );
			$parts = is_array( $parts ) ? $parts : [];
			$parts = array_filter( array_map( 'sanitize_html_class', $parts ) );
			if ( ! empty( $parts ) ) {
				$class_string .= ' ' . implode( ' ', $parts );
			}
		}

		$image_url = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
		?>
		<button
			type="button"
			class="<?php echo esc_attr( $class_string ); ?>"
			data-post-id="<?php echo esc_attr( $post_id ); ?>"
			data-title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"
			data-image-url="<?php echo esc_url( $image_url ); ?>">
			<?php echo esc_html( $button_text ); ?>
		</button>
		<?php
	}

	private function enqueue_commerce_assets(): void {
		$plugin_file = dirname( __DIR__, 3 ) . '/khm-plugin.php';
		$plugin_url  = plugin_dir_url( $plugin_file );
		$plugin_path = plugin_dir_path( $plugin_file );

		wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );

		$js_file = $plugin_path . 'assets/js/commerce-modal.js';
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				'khm-commerce-modal',
				$plugin_url . 'assets/js/commerce-modal.js',
				[ 'jquery', 'stripe-js' ],
				(string) filemtime( $js_file ),
				true
			);
		}

		$css_file = $plugin_path . 'assets/css/commerce-modal.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'khm-commerce-modal',
				$plugin_url . 'assets/css/commerce-modal.css',
				[],
				(string) filemtime( $css_file )
			);
		}

		wp_localize_script(
			'khm-commerce-modal',
			'khmCommerce',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'khm_commerce' ),
				'stripe_key' => get_option( 'khm_stripe_publishable_key', '' ),
			]
		);
	}
}

