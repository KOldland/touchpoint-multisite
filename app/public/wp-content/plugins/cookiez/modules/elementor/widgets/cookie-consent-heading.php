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
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Plugin;
use Elementor\Utils;
use Elementor\Widget_Base;

class Cookie_Consent_Heading extends Widget_Base {

	public function get_name(): string {
		return 'cookiez-heading';
	}

	public function get_title(): string {
		return esc_html__( 'Cookie Consent Heading', 'cookiez' );
	}

	public function get_icon(): string {
		return 'eicon-header';
	}

	public function get_categories(): array {
		return [ 'cookiez' ];
	}

	public function get_keywords(): array {
		return [ 'cookie', 'consent', 'heading', 'title' ];
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
				'condition'  => [
					'template_type!' => 'default',
				],
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

		$this->add_control(
			'header_size',
			[
				'label' => esc_html__( 'Heading HTML Tag', 'cookiez' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
					'div' => 'div',
					'span' => 'span',
					'p' => 'p',
				],
				'default' => 'h2',
			]
		);

		$this->add_control(
			'selected_icon',
			[
				'label' => esc_html__( 'Icon', 'cookiez' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'default' => [
					'value' => 'fas fa-cookie-bite',
					'library' => 'fa-solid',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'Style', 'cookiez' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label' => esc_html__( 'Text Alignment', 'cookiez' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Start', 'cookiez' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'cookiez' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'End', 'cookiez' ),
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justified', 'cookiez' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'classes' => 'elementor-control-start-end',
				'selectors' => [
					'{{WRAPPER}} .cookiez-heading__text' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'icon_position',
			[
				'label' => esc_html__( 'Icon Position', 'cookiez' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'row' => [
						'title' => esc_html__( 'Start', 'cookiez' ),
						'icon' => 'eicon-h-align-left',
					],
					'row-reverse' => [
						'title' => esc_html__( 'End', 'cookiez' ),
						'icon' => 'eicon-h-align-right',
					],
				],
				'default' => 'row',
				'classes' => 'elementor-control-start-end',
				'selectors' => [
					'{{WRAPPER}} .cookiez-heading' => 'flex-direction: {{VALUE}};',
				],
				'condition' => [
					'selected_icon[value]!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'icon_gap',
			[
				'label' => esc_html__( 'Icon Gap', 'cookiez' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 8,
				],
				'selectors' => [
					'{{WRAPPER}} .cookiez-heading' => 'gap: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'selected_icon[value]!' => '',
				],
			]
		);

		$this->add_control(
			'divider',
			[
				'type' => Controls_Manager::DIVIDER,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'typography',
				'selector' => '{{WRAPPER}} .cookiez-heading__text',
				'fields_options' => [
					'typography' => [
						'default' => 'custom',
					],
					'font_family' => [
						'default' => 'Poppins',
					],
					'font_size' => [
						'default' => [
							'size' => $design['titleTextSize'],
							'unit' => 'px',
						],
					],
					'font_weight' => [
						'default' => '600',
					],
					'line_height' => [
						'default' => [
							'size' => 1.5,
							'unit' => 'em',
						],
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'text_shadow',
				'selector' => '{{WRAPPER}} .cookiez-heading__text',
			]
		);

		$this->add_control(
			'title_color',
			[
				'label' => esc_html__( 'Text Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => $design['bannerTextColor'],
				'selectors' => [
					'{{WRAPPER}} .cookiez-heading__text' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_icon_style',
			[
				'label' => esc_html__( 'Icon', 'cookiez' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'selected_icon[value]!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'icon_size',
			[
				'label' => esc_html__( 'Size', 'cookiez' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range' => [
					'px' => [
						'min' => 6,
						'max' => 100,
					],
					'em' => [
						'min' => 0.5,
						'max' => 6,
						'step' => 0.1,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 16,
				],
				'selectors' => [
					'{{WRAPPER}} .cookiez-heading__icon' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .cookiez-heading__icon svg' => 'width: 1em; height: 1em;',
				],
			]
		);

		$this->add_control(
			'icon_color',
			[
				'label' => esc_html__( 'Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => $design['bannerTextColor'],
				'selectors' => [
					'{{WRAPPER}} .cookiez-heading__icon' => 'color: {{VALUE}};',
					'{{WRAPPER}} .cookiez-heading__icon svg' => 'fill: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'icon_background',
			[
				'label' => esc_html__( 'Background Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cookiez-heading__icon' => 'background-color: {{VALUE}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'icon_border',
				'selector' => '{{WRAPPER}} .cookiez-heading__icon',
				'fields_options' => [
					'border' => [
						'default' => 'solid',
					],
					'width' => [
						'default' => [
							'top' => '1',
							'right' => '1',
							'bottom' => '1',
							'left' => '1',
							'unit' => 'px',
						],
					],
				],
			]
		);

		$this->add_control(
			'icon_border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'cookiez' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default' => [
					'top' => 50,
					'right' => 50,
					'bottom' => 50,
					'left' => 50,
					'unit' => '%',
					'isLinked' => true,
				],
				'selectors' => [
					'{{WRAPPER}} .cookiez-heading__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'icon_padding',
			[
				'label' => esc_html__( 'Padding', 'cookiez' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default' => [
					'top' => 5,
					'right' => 5,
					'bottom' => 5,
					'left' => 5,
					'unit' => 'px',
					'isLinked' => true,
				],
				'selectors' => [
					'{{WRAPPER}} .cookiez-heading__icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$content = Content_Resolver::get_active_banner_fields(
			Widget_Utils::resolve_override( $this )
		);
		$settings = $this->get_settings_for_display();
		$has_icon = ! empty( $settings['selected_icon']['value'] );
		$header_size = Utils::validate_html_tag( $settings['header_size'] ?? 'h2' );
		?>
		<div class="cookiez-heading">

			<?php if ( $has_icon ) { ?>
			<span class="cookiez-heading__icon">
				<?php Icons_Manager::render_icon( $settings['selected_icon'], [ 'aria-hidden' => 'true' ] ); ?>
			</span>
			<?php } ?>

			<<?php echo esc_attr( $header_size ); ?> id="cookiez-title" class="cookiez-heading__text">
				<?php echo wp_kses_post( $content['title'] ); ?>
			</<?php echo esc_attr( $header_size ); ?>>

		</div>
		<?php
	}
}
