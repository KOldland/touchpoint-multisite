<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class TestPortalDashboard_Widget extends Widget_Base {

	public function get_name() {
		return 'test_portal_dashboard_v2';
	}

	public function get_title() {
		return __( 'TEST v2 Portal Dashboard', 'khm-membership' );
	}

	public function get_icon() {
		return 'eicon-dashboard';
	}

	public function get_categories() {
		return [ 'general' ];
	}
	
	public function get_keywords() {
		return ['test', 'portal', 'dashboard'];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Dashboard Settings', 'khm-membership' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_welcome',
			[
				'label'   => __( 'Show Welcome', 'khm-membership' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->end_controls_section();
	}

	public function render() {
		$settings = $this->get_settings_for_display();
		
		echo '<div class="khm-portal-dashboard-test">';
		echo '<h2>TEST Portal Dashboard Widget</h2>';
		echo '<p>If you can see this, the widget is working!</p>';
		echo '</div>';
	}
}
