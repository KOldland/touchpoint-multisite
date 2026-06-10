<?php

namespace Cookiez\Modules\Settings\Notices;

use Cookiez\Classes\Utils\Notice_Base;
use Cookiez\Modules\Settings\Classes\Settings;
use Cookiez\Modules\Settings\Module as Settings_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Quota_100
 *
 * If the consent usage is 100%, the notice will be shown.
 */
class Quota_100 extends Notice_Base {
	public string $type = 'error-elementor';
	public bool $is_dismissible = true;
	public bool $per_user = false;
	public $capability = 'manage_options';
	public string $id = 'quota-banner-100';

	/**
	 * Get the notice content.
	 *
	 * @return string
	 */
	public function content(): string {
		return sprintf(
			'<h3>%s</h3><p>%s</p><p><a class="button button-primary cookiez-dismiss-button" href="%s">%s</a></p>',
			esc_html__( 'You\'ve reached your monthly consent limit', 'cookiez' ),
			esc_html__( 'Upgrade now to increase your limit and avoid interruption, or wait for your quota to renew next month.', 'cookiez' ),
			esc_url( Settings_Module::get_upgrade_link( 'cookiez-100-quota-consents' ) ),
			esc_html__( 'Upgrade plan', 'cookiez' )
		);
	}

	/**
	 * @return void
	 */
	public function maybe_add_quota_100_notice(): void {
		$plan_data = get_option( Settings::PLAN_DATA );

		if ( ! $plan_data ) {
			$this->conditions = false;
			return;
		}

		if ( ! isset( $plan_data->plan->name ) || 'Free' !== $plan_data->plan->name ) {
			$this->conditions = false;
			return;
		}

		$consent_usage = Settings_Module::get_consent_quota_usage_percent();

		if ( 100 === $consent_usage ) {
			$this->conditions = true;
		} else {
			$this->undismiss();
			$this->conditions = false;
		}
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function __construct() {
		add_action( 'current_screen', [ $this, 'maybe_add_quota_100_notice' ] );

		parent::__construct();
	}
}
