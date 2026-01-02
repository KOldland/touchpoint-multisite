<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class KH_Suggested_Reading_Widget extends Widget_Base {

	public function get_name() {
		return 'kh_suggested_reading';
	}

	public function get_title() {
		return __( 'KH Suggested Reading', 'kh-suggested-reading' );
	}

	public function get_icon() {
		return 'eicon-posts-group';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'sr_content_section',
			[
				'label' => __( 'Suggested Reading Settings', 'kh-suggested-reading' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'sr_position',
			[
				'label'   => __( 'Position', 'kh-suggested-reading' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'top',
				'options' => [
					'top'    => __( 'Top (Sidebar)', 'kh-suggested-reading' ),
					'footer' => __( 'Footer (Bottom Section)', 'kh-suggested-reading' ),
				],
			]
		);

		$this->add_control(
			'sr_title',
			[
				'label'       => __( 'Title', 'kh-suggested-reading' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Suggested Reading', 'kh-suggested-reading' ),
				'placeholder' => __( 'Enter widget title', 'kh-suggested-reading' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {
		if ( ! function_exists( 'kh_suggested_reading_get_posts' ) ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$posts    = kh_suggested_reading_get_posts( get_the_ID() );

		if ( empty( $posts ) ) return;

		$args = [
			'posts'    => $posts,
			'title'    => $settings['sr_title'] ?? 'Suggested Reading',
			'position' => $settings['sr_position'] ?? 'top',
		];

		set_query_var( 'kh_sr_args', $args );
		include plugin_dir_path( __FILE__ ) . '../partials/kh-suggested-reading-dual.php';
	}
}
