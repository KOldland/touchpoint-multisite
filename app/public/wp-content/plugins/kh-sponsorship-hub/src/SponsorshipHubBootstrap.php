<?php
namespace KhSponsorshipHub\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorshipHubBootstrap {
    public function register() {
        if ( is_admin() ) {
            $sponsor_ui = new SponsorAdminUI();
            $sponsor_ui->register();

            $sponsor_app_ui = new SponsorApplicationAdminUI();
            $sponsor_app_ui->register();
        }

        $sponsor_shortcode = new SponsorApplicationShortcode();
        $sponsor_shortcode->register();

        $sponsor_controller = new SponsorController();
        add_action('rest_api_init', [$sponsor_controller, 'register_routes']);

        $advert_scheduler = new AdvertScheduler();
        $advert_scheduler->register();
        
        $sponsor_dashboard = new SponsorDashboard();
        $sponsor_dashboard->register();
    }
}
