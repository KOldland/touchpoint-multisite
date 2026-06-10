<?php

namespace Cookiez\Modules\Elementor\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Modules\Elementor\Classes\Widget_Utils;
use Cookiez\Modules\Elementor\Documents\Types\Cookie_Consent_Document;
use Cookiez\Modules\Settings\Classes\Content_Resolver;
use Cookiez\Modules\Settings\Classes\Design_Resolver;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Plugin;
use Elementor\Widget_Base;

class Cookie_Consent_Footer extends Widget_Base {

	public function get_name(): string {
		return 'cookiez-footer';
	}

	public function get_title(): string {
		return esc_html__( 'Cookie Consent Footer', 'cookiez' );
	}

	public function get_icon(): string {
		return 'eicon-footer';
	}

	public function get_categories(): array {
		return [ 'cookiez' ];
	}

	public function get_keywords(): array {
		return [ 'cookie', 'consent', 'footer', 'buttons', 'actions' ];
	}

	public function get_style_depends(): array {
		return [ 'cookiez-widget-cookie-consent' ];
	}

	public function show_in_panel(): bool {
		$document = Plugin::instance()->documents->get_current();

		if ( ! $document ) {
			return false;
		}

		return Cookie_Consent_Document::DOCUMENT_TYPE === $document->get_name();
	}

	protected function register_controls(): void {
		$design = Design_Resolver::get_active_design();
		$settings_url = admin_url( 'admin.php?page=cookiez-settings#settings' );
		$content_url = admin_url( 'admin.php?page=cookiez-settings#content' );

		$button_size_styles = Design_Resolver::get_button_size_styles( (string) $design['buttonSize'] );
		$button_padding_parts = explode( ' ', $button_size_styles['padding'] );
		$button_padding_vertical = isset( $button_padding_parts[0] ) ? (int) $button_padding_parts[0] : 6;
		$button_padding_horizontal = isset( $button_padding_parts[1] ) ? (int) $button_padding_parts[1] : 16;

		$button_defaults = [
			'font_size' => (int) $button_size_styles['font_size'],
			'padding_top' => $button_padding_vertical,
			'padding_right' => $button_padding_horizontal,
			'padding_bottom' => $button_padding_vertical,
			'padding_left' => $button_padding_horizontal,
			'border_radius' => (int) $design['buttonsCornerRadius'],
		];

		$not_default_condition = [ 'template_type!' => 'default' ];
		$opt_in_condition = [ 'template_type' => [ 'default', 'opt-in' ] ];
		$opt_out_condition = [ 'template_type' => [ 'default', 'opt-out' ] ];

		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'Content', 'cookiez' ),
			]
		);

		$this->add_control(
			'template_type',
			[
				'label' => esc_html__( 'Preview Template Type', 'cookiez' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'default',
				'options' => [
					'default' => esc_html__( 'Default', 'cookiez' ),
					'opt-in' => esc_html__( 'Opt-in', 'cookiez' ),
					'opt-out' => esc_html__( 'Opt-out', 'cookiez' ),
				],
				'description' => sprintf(
					/* translators: 1: <a> tag with href to the settings page; 2: </a> tag */
					esc_html__( 'Select the template type to style, regardless of the template in the %1$sSettings Page%2$s.', 'cookiez' ),
					'<a href="' . esc_url( $settings_url ) . '" target="_blank">',
					'</a>',
				),
				'label_block' => true,
			]
		);

		$this->add_control(
			'preview_notice',
			[
				'type'       => Controls_Manager::ALERT,
				'alert_type' => 'warning',
				'heading'    => esc_html__( 'Please note', 'cookiez' ),
				'content'    => esc_html__( 'This setting is only for preview. It does not change how the banner looks on the website.', 'cookiez' ),
				'condition'  => $not_default_condition,
			]
		);

		$this->add_control(
			'content_heading',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Content', 'cookiez' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'content_notice',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => sprintf(
					/* translators: 1: <a> tag with href to the content page; 2: </a> tag */
					esc_html__( 'To change the content, head over to the %1$sContent Page%2$s.', 'cookiez' ),
					'<a href="' . esc_url( $content_url ) . '" target="_blank">',
					'</a>',
				),
				'content_classes' => 'elementor-control-field-description',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_container',
			[
				'label' => esc_html__( 'Container', 'cookiez' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label' => esc_html__( 'Alignment', 'cookiez' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [
						'title' => esc_html__( 'Start', 'cookiez' ),
						'icon' => 'eicon-align-start-h',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'cookiez' ),
						'icon' => 'eicon-align-center-h',
					],
					'flex-end' => [
						'title' => esc_html__( 'End', 'cookiez' ),
						'icon' => 'eicon-align-end-h',
					],
					'stretch' => [
						'title' => esc_html__( 'Stretch', 'cookiez' ),
						'icon' => 'eicon-align-stretch-h',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .cookiez-footer' => 'justify-content: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label' => esc_html__( 'Gap', 'cookiez' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'default' => [
					'size' => 8,
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .cookiez-footer' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->register_button_style_controls(
			'customize_button',
			esc_html__( 'Customize Button', 'cookiez' ),
			'.cookiez-footer-button--customize',
			array_merge(
				$button_defaults,
				[
					'text_color' => (string) $design['buttonsAndTogglesColor'],
					'background_color' => 'transparent',
					'border_color' => (string) $design['buttonsAndTogglesColor'],
				]
			),
			$opt_in_condition
		);

		$this->register_button_style_controls(
			'reject_button',
			esc_html__( 'Reject Button', 'cookiez' ),
			'.cookiez-footer-button--deny',
			array_merge(
				$button_defaults,
				[
					'text_color' => '#FFFFFF',
					'background_color' => (string) $design['buttonsAndTogglesColor'],
					'border_color' => (string) $design['buttonsAndTogglesColor'],
				]
			),
			$opt_in_condition
		);

		$this->register_button_style_controls(
			'accept_button',
			esc_html__( 'Accept Button', 'cookiez' ),
			'.cookiez-footer-button--accept',
			array_merge(
				$button_defaults,
				[
					'text_color' => '#FFFFFF',
					'background_color' => (string) $design['buttonsAndTogglesColor'],
					'border_color' => (string) $design['buttonsAndTogglesColor'],
				]
			),
			$opt_in_condition
		);

		$this->register_button_style_controls(
			'do_not_sell_button',
			esc_html__( 'Do Not Sell Button', 'cookiez' ),
			'.cookiez-footer-button--do-not-sell',
			array_merge(
				$button_defaults,
				[
					'text_color' => '#000000',
					'background_color' => 'transparent',
					'border_color' => 'transparent',
				]
			),
			$opt_out_condition
		);
	}

	private function register_button_style_controls( string $prefix, string $label, string $selector, array $defaults, array $condition = [] ): void {
		$section_args = [
			'label' => $label,
			'tab' => Controls_Manager::TAB_STYLE,
		];

		if ( $condition ) {
			$section_args['condition'] = $condition;
		}

		$this->start_controls_section( $prefix . '_section_style', $section_args );

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => $prefix . '_typography',
				'selector' => '{{WRAPPER}} ' . $selector,
				'fields_options' => [
					'typography' => [
						'default' => 'custom',
					],
					'font_family' => [
						'default' => 'Poppins',
					],
					'font_size' => [
						'default' => [
							'size' => $defaults['font_size'],
							'unit' => 'px',
						],
					],
					'font_weight' => [
						'default' => '500',
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => $prefix . '_border',
				'selector' => '{{WRAPPER}} ' . $selector,
			]
		);

		$this->add_responsive_control(
			$prefix . '_border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'cookiez' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} ' . $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'default' => [
					'unit' => 'px',
					'top' => (int) $defaults['border_radius'],
					'right' => (int) $defaults['border_radius'],
					'bottom' => (int) $defaults['border_radius'],
					'left' => (int) $defaults['border_radius'],
				],
			]
		);

		$this->add_responsive_control(
			$prefix . '_padding',
			[
				'label' => esc_html__( 'Padding', 'cookiez' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem', '%' ],
				'selectors' => [
					'{{WRAPPER}} ' . $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'default' => [
					'unit' => 'px',
					'top' => (int) $defaults['padding_top'],
					'right' => (int) $defaults['padding_right'],
					'bottom' => (int) $defaults['padding_bottom'],
					'left' => (int) $defaults['padding_left'],
				],
			]
		);

		$this->start_controls_tabs( $prefix . '_style_tabs' );

		$this->start_controls_tab(
			$prefix . '_tab_normal',
			[
				'label' => esc_html__( 'Normal', 'cookiez' ),
			]
		);

		$this->add_control(
			$prefix . '_text_color',
			[
				'label' => esc_html__( 'Text Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => (string) $defaults['text_color'],
				'selectors' => [
					'{{WRAPPER}} ' . $selector => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			$prefix . '_background_color',
			[
				'label' => esc_html__( 'Background Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => (string) $defaults['background_color'],
				'selectors' => [
					'{{WRAPPER}} ' . $selector => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			$prefix . '_tab_hover',
			[
				'label' => esc_html__( 'Hover', 'cookiez' ),
			]
		);

		$this->add_control(
			$prefix . '_hover_text_color',
			[
				'label' => esc_html__( 'Text Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} ' . $selector . ':hover, {{WRAPPER}} ' . $selector . ':focus-visible' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			$prefix . '_hover_background_color',
			[
				'label' => esc_html__( 'Background Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} ' . $selector . ':hover, {{WRAPPER}} ' . $selector . ':focus-visible' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			$prefix . '_hover_border_color',
			[
				'label' => esc_html__( 'Border Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} ' . $selector . ':hover, {{WRAPPER}} ' . $selector . ':focus-visible' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			$prefix . '_hover_transition_duration',
			[
				'label' => esc_html__( 'Transition Duration', 'cookiez' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'default' => [
					'unit' => 's',
				],
				'selectors' => [
					'{{WRAPPER}} ' . $selector => 'transition-duration: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	protected function render(): void {
		$override = Widget_Utils::resolve_override( $this );
		$template_type = Content_Resolver::get_active_template_type( $override );
		$content = Content_Resolver::get_active_banner_fields( $override );
		?>
		<div class="cookiez-footer">
			<?php if ( 'opt-out' === $template_type ) { ?>
				<button class="cookiez-footer-button cookiez-footer-button--do-not-sell" type="button" data-cookiez-action="customize"><?php echo esc_html( $content['doNotSellLinkText'] ); ?></button>
			<?php } else { ?>
				<button class="cookiez-footer-button cookiez-footer-button--outlined cookiez-footer-button--customize" type="button" data-cookiez-action="customize"><?php echo esc_html( $content['customizeLabel'] ); ?></button>
				<button class="cookiez-footer-button cookiez-footer-button--contained cookiez-footer-button--deny" type="button" data-cookiez-action="deny-all"><?php echo esc_html( $content['denyLabel'] ); ?></button>
				<button class="cookiez-footer-button cookiez-footer-button--contained cookiez-footer-button--accept" type="button" data-cookiez-action="accept-all"><?php echo esc_html( $content['acceptAllLabel'] ); ?></button>
			<?php } ?>
		</div>
		<?php
	}
}
