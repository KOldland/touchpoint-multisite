<?php
/**
 * Plugin Name: Touchpoint Quote Club
 * Description: Quotes, commentary and B2B engagement toolkit for the multisite architecture.
 * Version: 1.0.0
 * Author: Kris Oldland
 *
 * @package QuoteClub
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

final class KH_Quote_Club {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('elementor/widgets/register', [$this, 'register_elementor_widgets']);
    }

    public function register_rest_routes() {
        if (class_exists('\\QuoteClub\\Rest\\QuoteClubController')) {
            $controller = new \QuoteClub\Rest\QuoteClubController();
            $controller->register_routes();
        }
    }

    public function register_shortcodes() {
        if (class_exists('\\QuoteClub\\PublicFrontend\\QuoteClubPortalShortcode')) {
            \QuoteClub\PublicFrontend\QuoteClubPortalShortcode::init();
        }
    }

    public function register_admin_pages() {
        if (class_exists('\\QuoteClub\\Admin\\QuoteClubCommentaryAdminPage')) {
            new \QuoteClub\Admin\QuoteClubCommentaryAdminPage();
        }
        if (class_exists('\\QuoteClub\\Admin\\QuoteClubAdvertAdminPage')) {
            new \QuoteClub\Admin\QuoteClubAdvertAdminPage();
        }
        if (class_exists('\\QuoteClub\\Admin\\QuoteClubPressReleaseAdminPage')) {
            new \QuoteClub\Admin\QuoteClubPressReleaseAdminPage();
        }
    }

    public function register_elementor_widgets($widgets_manager) {
        $widgets = [
            '\\QuoteClub\\Elementor\\widgets\\QuoteClubSearchToolbar_Widget',
            '\\QuoteClub\\Elementor\\widgets\\QuoteClubSessionDetail_Widget',
            '\\QuoteClub\\Elementor\\widgets\\QuoteClubResults_Widget',
            '\\QuoteClub\\Elementor\\widgets\\QuoteClubInviteStatus_Widget'
        ];
        foreach ($widgets as $widget) {
            if (class_exists($widget)) {
                $widgets_manager->register(new $widget());
            }
        }
    }
}

add_action('plugins_loaded', ['KH_Quote_Club', 'get_instance']);
