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
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin;
use Elementor\Utils;
use Elementor\Widget_Base;

class Cookie_Consent_Content extends Widget_Base {

	public function get_name(): string {
		return 'cookiez-content';
	}

	public function get_title(): string {
		return esc_html__( 'Cookie Consent Content', 'cookiez' );
	}

	public function get_icon(): string {
		return 'eicon-accordion';
	}

	public function get_categories(): array {
		return [ 'cookiez' ];
	}

	public function get_keywords(): array {
		return [ 'cookie', 'consent', 'content' ];
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
			'content_html_tag',
			[
				'label' => esc_html__( 'HTML Tag', 'cookiez' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'p' => 'p',
					'div' => 'div',
					'span' => 'span',
				],
				'default' => 'p',
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
				'label' => esc_html__( 'Alignment', 'cookiez' ),
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
					'{{WRAPPER}} .cookiez-content' => 'text-align: {{VALUE}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'typography',
				'selector' => '{{WRAPPER}} .cookiez-content',
				'fields_options' => [
					'typography' => [
						'default' => 'custom',
					],
					'font_family' => [
						'default' => 'Poppins',
					],
					'font_size' => [
						'default' => [
							'size' => $design['bodyTextSize'],
							'unit' => 'px',
						],
					],
					'font_weight' => [
						'default' => '400',
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
				'selector' => '{{WRAPPER}} .cookiez-content',
			]
		);

		$this->start_controls_tabs( 'link_colors' );

		$this->start_controls_tab(
			'colors_normal',
			[
				'label' => esc_html__( 'Normal', 'cookiez' ),
			]
		);

		$this->add_control(
			'text_color',
			[
				'label' => esc_html__( 'Text Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => $design['bannerTextColor'],
				'selectors' => [
					'{{WRAPPER}} .cookiez-content' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'link_color',
			[
				'label' => esc_html__( 'Link Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cookiez-content a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'colors_hover',
			[
				'label' => esc_html__( 'Hover', 'cookiez' ),
			]
		);

		$this->add_control(
			'link_hover_color',
			[
				'label' => esc_html__( 'Link Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cookiez-content a:hover, {{WRAPPER}} .cookiez-content a:focus-visible' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'link_hover_color_transition_duration',
			[
				'label' => esc_html__( 'Transition Duration', 'cookiez' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'default' => [
					'unit' => 's',
				],
				'selectors' => [
					'{{WRAPPER}} .cookiez-content a' => 'transition-duration: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	protected function render(): void {
		$content = Content_Resolver::get_active_banner_fields(
			Widget_Utils::resolve_override( $this )
		);
		$settings = $this->get_settings_for_display();
		$content_html_tag = Utils::validate_html_tag( $settings['content_html_tag'] ?? 'p' );
		$message = $this->build_message_with_policy_links( $content );
		?>
		<<?php echo esc_attr( $content_html_tag ); ?> id="cookiez-description" class="cookiez-content">
			<?php echo wp_kses_post( $message ); ?>
		</<?php echo esc_attr( $content_html_tag ); ?>>
		<?php
	}

	/**
	 * @param array<string, string|bool> $content
	 */
	private function build_message_with_policy_links( array $content ): string {
		$message = (string) $content['cookieMessage'];

		$message .= $this->build_policy_link(
			(bool) $content['privacyPolicyLink'],
			(string) $content['privacyPolicyUrl'],
			(string) $content['privacyPolicyLinkText'],
			__( 'Privacy Policy', 'cookiez' )
		);

		$message .= $this->build_policy_link(
			(bool) $content['cookiePolicyLink'],
			(string) $content['cookiePolicyUrl'],
			(string) $content['cookiePolicyLinkText'],
			__( 'Cookie Policy', 'cookiez' )
		);

		return $message;
	}

	private function build_policy_link( bool $enabled, string $url, string $custom_label, string $default_label ): string {
		if ( ! $enabled || '' === $url ) {
			return '';
		}

		$label = '' !== $custom_label ? $custom_label : $default_label;

		return ' ' . sprintf(
			'<a class="cc-link" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}
}
