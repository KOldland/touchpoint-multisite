<?php
namespace KhSponsorshipHub\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorshipHubBootstrap {
    public function init() {
        if ( is_admin() ) {
            $sponsor_ui = new SponsorAdminUI();
            $sponsor_ui->init();

            $sponsor_app_ui = new SponsorApplicationAdminUI();
            $sponsor_app_ui->init();
        }

        $sponsor_shortcode = new SponsorApplicationShortcode();
        $sponsor_shortcode->init();

        $sponsor_controller = new SponsorController();
        $sponsor_controller->init();

        $advert_scheduler = new AdvertScheduler();
        $advert_scheduler->init();
        
        $sponsor_dashboard = new SponsorDashboard();
        $sponsor_dashboard->init();
    }
}
