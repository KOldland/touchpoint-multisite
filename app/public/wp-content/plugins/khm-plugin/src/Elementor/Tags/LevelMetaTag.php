<?php

namespace KHM\Elementor\Tags;

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Tag;

/**
 * Elementor Dynamic Tag: Membership Level Meta
 */
class LevelMetaTag extends Tag {

	public function get_name() {
		return 'khm_level_meta';
	}

	public function get_title() {
		return __( 'KHM Level Meta', 'khm-membership' );
	}

	public function get_group() {
		return 'khm';
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	protected function register_controls() {
		$this->add_control(
			'level_id',
			[
				'label' => __( 'Level ID', 'khm-membership' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
			]
		);

		$this->add_control(
			'key',
			[
				'label' => __( 'Meta Key (dot notation)', 'khm-membership' ),
				'type' => Controls_Manager::TEXT,
				'placeholder' => 'features.gifting',
			]
		);

		$this->add_control(
			'array_format',
			[
				'label' => __( 'Array Output', 'khm-membership' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'json',
				'options' => [
					'json' => __( 'JSON', 'khm-membership' ),
					'lines' => __( 'New Lines', 'khm-membership' ),
					'html_list' => __( 'HTML List', 'khm-membership' ),
				],
			]
		);
	}

	public function render() {
		$level_id = (int) $this->get_settings( 'level_id' );
		$key = (string) $this->get_settings( 'key' );
		$array_format = (string) $this->get_settings( 'array_format' );

		if ( $level_id < 1 || $key === '' ) {
			return;
		}

		$value = function_exists( 'khm_get_level_meta' )
			? khm_get_level_meta( $level_id, $key, '' )
			: '';

		if ( is_array( $value ) ) {
			if ( $array_format === 'lines' ) {
				echo esc_html( implode( PHP_EOL, array_map( 'strval', $value ) ) );
				return;
			}
			if ( $array_format === 'html_list' ) {
				echo '<ul class="khm-level-meta-list">';
				foreach ( $value as $item ) {
					echo '<li>' . esc_html( (string) $item ) . '</li>';
				}
				echo '</ul>';
				return;
			}
			echo wp_json_encode( $value );
			return;
		}

		if ( is_bool( $value ) ) {
			echo $value ? '1' : '0';
			return;
		}

		echo esc_html( (string) $value );
	}
}
