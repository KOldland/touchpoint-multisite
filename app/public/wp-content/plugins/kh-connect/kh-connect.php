<?php
/**
 * Plugin Name: KH Connect
 * Description: Tech.Connect Relational Endpoints, RFQ Endpoints, and Match Payment handlers.
 * Version: 1.0.0
 * Author: 1927media
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';

add_action('plugins_loaded', function() {
    \KH\Connect\Plugin::init();
});

if ( defined('WP_CLI') && WP_CLI ) {
    \WP_CLI::add_command( 'khm connect', '\KH\Connect\ConnectDemoSeedCommand' );
}
