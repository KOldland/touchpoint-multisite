<?php
/**
 * Plugin Name: KH Sponsorship Hub
 * Description: Dedicated micro-plugin for managing Sponsors, B2B Leads, Adverts, and Applications.
 * Version: 1.0.0
 * Author: 1927media
 * Text Domain: kh-sponsorship-hub
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

use KhSponsorshipHub\Sponsors\SponsorshipHubBootstrap;

add_action( 'plugins_loaded', function() {
    $bootstrap = new SponsorshipHubBootstrap();
    $bootstrap->register();
});
