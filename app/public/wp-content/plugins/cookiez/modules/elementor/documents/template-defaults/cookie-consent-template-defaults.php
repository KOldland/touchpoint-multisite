<?php

namespace Cookiez\Modules\Elementor\Documents\Template_Defaults;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Modules\Elementor\Documents\Template_Defaults_Base;

class Cookie_Consent_Template_Defaults extends Template_Defaults_Base {

	protected static function get_default_template_data(): array {
		return [
			[
				'id'       => self::generate_element_id(),
				'elType'   => 'container',
				'settings' => [
					'content_width' => 'full',
					'flex_gap' => [
						'unit' => 'px',
						'size' => 0,
						'row' => '0',
						'column' => '0',
						'isLinked' => true,
					],
				],
				'elements' => [
					[
						'id'         => self::generate_element_id(),
						'elType'     => 'widget',
						'widgetType' => 'cookiez-heading',
						'settings'   => [],
						'elements'   => [],
					],
					[
						'id'         => self::generate_element_id(),
						'elType'     => 'widget',
						'widgetType' => 'divider',
						'settings'   => [
							'color' => 'rgba(0, 0, 0, 0.12)',
						],
						'elements'   => [],
					],
					[
						'id'         => self::generate_element_id(),
						'elType'     => 'widget',
						'widgetType' => 'cookiez-content',
						'settings'   => [],
						'elements'   => [],
					],
					[
						'id'         => self::generate_element_id(),
						'elType'     => 'widget',
						'widgetType' => 'divider',
						'settings'   => [
							'gap'   => [
								'unit' => 'px',
								'size' => 20,
							],
							'color' => 'rgba(0, 0, 0, 0.12)',
						],
						'elements'   => [],
					],
					[
						'id'         => self::generate_element_id(),
						'elType'     => 'widget',
						'widgetType' => 'cookiez-footer',
						'settings'   => [],
						'elements'   => [],
					],
				],
			],
		];
	}
}
