<?php

namespace Cookiez\Modules\Elementor\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Modules\Cookie\Database\Cookie_Entry;
use Cookiez\Modules\Cookie\Database\Cookie_Table;
use Cookiez\Modules\Elementor\Classes\Widget_Utils;
use Cookiez\Modules\Elementor\Documents\Types\Preferences_Banner_Document;
use Cookiez\Modules\Settings\Classes\Content_Resolver;
use Cookiez\Modules\Settings\Classes\Design_Resolver;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Plugin;
use Elementor\Widget_Base;

class Preferences_Content extends Widget_Base {

	public function get_name(): string {
		return 'cookiez-preferences-content';
	}

	public function get_title(): string {
		return esc_html__( 'Preferences Content', 'cookiez' );
	}

	public function get_icon(): string {
		return 'eicon-accordion';
	}

	public function get_categories(): array {
		return [ 'cookiez' ];
	}

	public function get_keywords(): array {
		return [ 'cookie', 'consent', 'preferences', 'content' ];
	}

	public function get_style_depends(): array {
		return [ 'cookiez-widget-preferences-banner' ];
	}

	public function show_in_panel(): bool {
		$document = Plugin::instance()->documents->get_current();

		if ( ! $document ) {
			return false;
		}

		return Preferences_Banner_Document::DOCUMENT_TYPE === $document->get_name();
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

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_container',
			[
				'label' => esc_html__( 'Container', 'cookiez' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label' => esc_html__( 'Gap', 'cookiez' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'default' => [
					'unit' => 'px',
					'size' => 12,
				],
				'selectors' => [
					'{{WRAPPER}} .cookiez-preferences-content' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_category',
			[
				'label' => esc_html__( 'Category', 'cookiez' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'category_border_color',
			[
				'label' => esc_html__( 'Border Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => 'rgba(0, 0, 0, 0.12)',
				'selectors' => [
					'{{WRAPPER}} .cookiez-preferences-content-category' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .cookiez-preferences-content-category__details-paper' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .cookiez-content-cookie-divider' => 'border-block-start-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'category_border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'cookiez' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem' ],
				'default' => [
					'unit' => 'px',
					'top' => 8,
					'right' => 8,
					'bottom' => 8,
					'left' => 8,
					'isLinked' => true,
				],
				'selectors' => [
					'{{WRAPPER}} .cookiez-preferences-content-category' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'category_title_typography',
				'label' => esc_html__( 'Title Typography', 'cookiez' ),
				'selector' => '{{WRAPPER}} .cookiez-preferences-content-category__title',
				'fields_options' => [
					'typography' => [ 'default' => 'custom' ],
					'font_family' => [ 'default' => 'Poppins' ],
					'font_size' => [
						'default' => [
							'size' => $design['titleTextSize'],
							'unit' => 'px',
						],
					],
					'font_weight' => [ 'default' => '600' ],
				],
			]
		);

		$this->add_control(
			'category_title_color',
			[
				'label' => esc_html__( 'Title Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => $design['bannerTextColor'],
				'selectors' => [
					'{{WRAPPER}} .cookiez-preferences-content-category__title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'category_chevron_color',
			[
				'label' => esc_html__( 'Chevron Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => $design['bannerTextColor'],
				'selectors' => [
					'{{WRAPPER}} .cookiez-preferences-content-category__chevron' => 'color: {{VALUE}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'category_description_typography',
				'label' => esc_html__( 'Description Typography', 'cookiez' ),
				'selector' => '{{WRAPPER}} .cookiez-preferences-content-category__description',
				'fields_options' => [
					'typography' => [ 'default' => 'custom' ],
					'font_family' => [ 'default' => 'Poppins' ],
					'font_size' => [
						'default' => [
							'size' => $design['bodyTextSize'],
							'unit' => 'px',
						],
					],
				],
			]
		);

		$this->add_control(
			'category_description_color',
			[
				'label' => esc_html__( 'Description Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => $design['bannerTextColor'],
				'selectors' => [
					'{{WRAPPER}} .cookiez-preferences-content-category__description, {{WRAPPER}} .cookiez-preferences-content-category__empty, {{WRAPPER}} .cookiez-preferences-content-category__details-paper' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'category_switch_color',
			[
				'label' => esc_html__( 'Switch Color (Checked)', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => $design['buttonsAndTogglesColor'],
				'selectors' => [
					'{{WRAPPER}} input.cookiez-preferences-content-category__switch:checked::before' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} input.cookiez-preferences-content-category__switch:checked::after' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} input.cookiez-preferences-content-category__switch:checked:hover' => 'background: color-mix(in srgb, {{VALUE}} 8%, transparent);',
					'{{WRAPPER}} input.cookiez-preferences-content-opt-out__input:checked::before' => 'background-color: {{VALUE}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'cookie_label_typography',
				'label' => esc_html__( 'Cookies Typography', 'cookiez' ),
				'selector' => '{{WRAPPER}} .cookiez-content-cookie-row__label',
				'fields_options' => [
					'typography' => [ 'default' => 'custom' ],
					'font_family' => [ 'default' => 'Poppins' ],
					'font_size' => [
						'default' => [
							'size' => 12,
							'unit' => 'px',
						],
					],
				],
			]
		);

		$this->add_control(
			'cookie_label_color',
			[
				'label' => esc_html__( 'Cookies Label Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => $design['bannerTextColor'],
				'selectors' => [
					'{{WRAPPER}} .cookiez-content-cookie-row__label' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'cookie_value_color',
			[
				'label' => esc_html__( 'Cookies Value Color', 'cookiez' ),
				'type' => Controls_Manager::COLOR,
				'default' => $design['bannerTextColor'],
				'selectors' => [
					'{{WRAPPER}} .cookiez-content-cookie-row__value' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$override = Widget_Utils::resolve_override( $this );
		$template_type = Content_Resolver::get_active_template_type( $override );
		?>
		<div class="cookiez-preferences-content">
			<?php if ( 'opt-out' === $template_type ) {
				$this->render_opt_out( $override );
			} else {
				$this->render_opt_in( $override );
			} ?>
		</div>
		<?php
	}

	private function render_opt_in( ?string $override ): void {
		$categories_content = Content_Resolver::get_active_category_fields( $override );
		$cookies_by_category = $this->group_cookies_by_category();

		foreach ( Content_Resolver::get_cookie_category_keys() as $category_key ) {
			$category = $categories_content[ $category_key ];
			$category_cookies = $cookies_by_category[ $category_key ] ?? [];
			$is_necessary = 'necessary' === $category_key;
			$is_disabled = $is_necessary || empty( $category_cookies );
			$this->render_category( $category_key, $category, $category_cookies, $is_disabled, $is_necessary );
		}
	}

	private function render_opt_out( ?string $override ): void {
		$content = Content_Resolver::get_active_preferences_fields( $override );
		$banner = Content_Resolver::get_active_banner_fields( $override );
		$label = $banner['doNotSellLinkText'];
		?>
		<p class="cookiez-preferences-content__overview"><?php echo wp_kses_post( $content['privacyOverview'] ); ?></p>
		<label class="cookiez-preferences-content-opt-out">
			<input
				type="checkbox"
				class="cookiez-preferences-content-opt-out__input"
				data-cookiez-preferences-content-category-toggle="advertising"
				aria-label="<?php echo esc_attr( $label ); ?>"
			/>
			<span class="cookiez-preferences-content-opt-out__label"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	private function render_category( string $category_key, array $category, array $cookies, bool $is_disabled, bool $is_necessary ): void {
		$panel_id = 'cookiez-preferences-content-category-panel-' . esc_attr( $category_key );
		?>
		<div class="cookiez-preferences-content-category" data-cookiez-preferences-content-category="<?php echo esc_attr( $category_key ); ?>">
			<div class="cookiez-preferences-content-category__header">
				<button
					type="button"
					class="cookiez-preferences-content-category__summary"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $panel_id ); ?>"
				>
					<span class="cookiez-preferences-content-category__chevron">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
							<path fill-rule="evenodd" clip-rule="evenodd" d="M9.01972 18.2803C8.72683 17.9874 8.72683 17.5126 9.01972 17.2197L14.4894 11.75L9.01972 6.28033C8.72682 5.98744 8.72682 5.51256 9.01972 5.21967C9.31261 4.92678 9.78749 4.92678 10.0804 5.21967L16.0804 11.2197C16.3733 11.5126 16.3733 11.9874 16.0804 12.2803L10.0804 18.2803C9.78749 18.5732 9.31261 18.5732 9.01972 18.2803Z"/>
						</svg>
					</span>
					<span class="cookiez-preferences-content-category__heading">
						<span class="cookiez-preferences-content-category__title"><?php echo esc_html( $category['title'] ); ?></span>
						<span class="cookiez-preferences-content-category__description"><?php echo esc_html( $category['description'] ); ?></span>
					</span>
				</button>
				<?php $this->render_category_control( $category_key, $category, $is_disabled, $is_necessary ); ?>
			</div>
			<div
				class="cookiez-preferences-content-category__panel"
				id="<?php echo esc_attr( $panel_id ); ?>"
				role="region"
				aria-hidden="true"
			>
				<?php $this->render_category_panel( $cookies ); ?>
			</div>
		</div>
		<?php
	}

	private function render_category_control( string $category_key, array $category, bool $is_disabled, bool $is_necessary ): void {
		if ( $is_necessary ) {
			?>
			<span class="cookiez-preferences-content-category__always-active"><?php esc_html_e( 'Always active', 'cookiez' ); ?></span>
			<?php
			return;
		}
		?>
		<input
			type="checkbox"
			class="cookiez-preferences-content-category__switch"
			role="switch"
			data-cookiez-preferences-content-category-toggle="<?php echo esc_attr( $category_key ); ?>"
			<?php echo $is_disabled ? 'disabled' : ''; ?>
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: cookie category title */ __( 'Enable %s cookies', 'cookiez' ), $category['title'] ) ); ?>"
		/>
		<?php
	}

	private function render_category_panel( array $cookies ): void {
		?>
		<div class="cookiez-preferences-content-category__details-paper">
			<?php if ( empty( $cookies ) ) { ?>
				<p class="cookiez-preferences-content-category__empty"><?php esc_html_e( 'No cookies in this category.', 'cookiez' ); ?></p>
			<?php } else {
				foreach ( $cookies as $index => $cookie ) {
					if ( $index > 0 ) {
						echo '<hr class="cookiez-content-cookie-divider" />';
					}
					$this->render_cookie_item( $cookie );
				}
			} ?>
		</div>
		<?php
	}

	private function render_cookie_item( array $cookie ): void {
		?>
		<div class="cookiez-preferences-content-cookie-item">
			<div class="cookiez-content-cookie-row">
				<span class="cookiez-content-cookie-row__label"><?php esc_html_e( 'Cookie', 'cookiez' ); ?></span>
				<span class="cookiez-content-cookie-row__value"><?php echo esc_html( $cookie['name'] ); ?></span>
			</div>
			<div class="cookiez-content-cookie-row">
				<span class="cookiez-content-cookie-row__label"><?php esc_html_e( 'Duration', 'cookiez' ); ?></span>
				<span class="cookiez-content-cookie-row__value"><?php echo esc_html( $this->format_duration( $cookie['duration'] ) ); ?></span>
			</div>
			<div class="cookiez-content-cookie-row">
				<span class="cookiez-content-cookie-row__label"><?php esc_html_e( 'Description', 'cookiez' ); ?></span>
				<span class="cookiez-content-cookie-row__value"><?php echo esc_html( $cookie['description'] ); ?></span>
			</div>
		</div>
		<?php
	}

	private function format_duration( $duration_seconds ): string {
		if ( null === $duration_seconds || '' === $duration_seconds ) {
			return esc_html__( 'Session', 'cookiez' );
		}

		$seconds = (int) $duration_seconds;
		if ( 0 >= $seconds ) {
			return esc_html__( 'Session', 'cookiez' );
		}

		$days = (int) floor( $seconds / DAY_IN_SECONDS );
		if ( $days >= 365 ) {
			$years = (int) floor( $days / 365 );
			return sprintf(
				/* translators: %d: number of years */
				_n( '%d year', '%d years', $years, 'cookiez' ),
				$years
			);
		}
		if ( $days >= 30 ) {
			$months = (int) floor( $days / 30 );
			return sprintf(
				/* translators: %d: number of months */
				_n( '%d month', '%d months', $months, 'cookiez' ),
				$months
			);
		}
		if ( $days >= 1 ) {
			return sprintf(
				/* translators: %d: number of days */
				_n( '%d day', '%d days', $days, 'cookiez' ),
				$days
			);
		}
		$hours = (int) floor( $seconds / HOUR_IN_SECONDS );
		if ( $hours >= 1 ) {
			return sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour', '%d hours', $hours, 'cookiez' ),
				$hours
			);
		}
		$minutes = (int) floor( $seconds / MINUTE_IN_SECONDS );
		if ( $minutes >= 1 ) {
			return sprintf(
				/* translators: %d: number of minutes */
				_n( '%d minute', '%d minutes', $minutes, 'cookiez' ),
				$minutes
			);
		}
		return sprintf(
			/* translators: %d: number of seconds */
			_n( '%d second', '%d seconds', $seconds, 'cookiez' ),
			$seconds
		);
	}

	/**
	 * @return array<string, array<int, array{name: string, duration: int|null, description: string}>>
	 */
	private function group_cookies_by_category(): array {
		$grouped = [];
		foreach ( Content_Resolver::get_cookie_category_keys() as $category_key ) {
			$grouped[ $category_key ] = [];
		}

		$cookies = Cookie_Entry::find_all();
		if ( ! is_array( $cookies ) ) {
			return $grouped;
		}

		foreach ( $cookies as $cookie ) {
			$category = $this->get_cookie_field( $cookie, Cookie_Table::CATEGORY );
			if ( ! is_string( $category ) || ! array_key_exists( $category, $grouped ) ) {
				continue;
			}
			$duration_raw = $this->get_cookie_field( $cookie, Cookie_Table::DURATION );
			$grouped[ $category ][] = [
				'name' => (string) $this->get_cookie_field( $cookie, Cookie_Table::NAME ),
				'duration' => is_numeric( $duration_raw ) ? (int) $duration_raw : null,
				'description' => (string) $this->get_cookie_field( $cookie, Cookie_Table::DESCRIPTION ),
			];
		}

		return $grouped;
	}

	private function get_cookie_field( $cookie, string $field ) {
		if ( is_object( $cookie ) ) {
			if ( method_exists( $cookie, 'get' ) ) {
				return $cookie->get( $field );
			}
			if ( property_exists( $cookie, $field ) ) {
				return $cookie->{$field};
			}
		}
		if ( is_array( $cookie ) ) {
			return $cookie[ $field ] ?? null;
		}
		return null;
	}
}
