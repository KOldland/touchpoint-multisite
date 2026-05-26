<?php
namespace KHM\Bootstrap;

class AdminBootstrap {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'create_main_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_admin_handlers'], 1);
    }

    public static function create_main_admin_menu() {
        global $admin_page_hooks;
        if (!isset($admin_page_hooks['khm-main-menu'])) {
            add_menu_page(
                'KHM Plugin',
                'KHM Plugin',
                'manage_options',
                'khm-main-menu',
                'khm_render_main_admin_page',
                'dashicons-chart-line',
                9
            );
        }
    }

    public static function register_admin_handlers() {
        // Register LevelsPage
        if ( class_exists('KHM\Admin\LevelsPage') ) {
            $levels_page = new \KHM\Admin\LevelsPage();
            $levels_page->register();
            $GLOBALS['khm_levels_page'] = $levels_page;
        }
        
        // Register AddMemberPage
        if ( class_exists('KHM\Admin\AddMemberPage') ) {
            $add_member_page = new \KHM\Admin\AddMemberPage();
            $add_member_page->register();
            $GLOBALS['khm_add_member_page'] = $add_member_page;
        }

        // Register Membership Reports Page
        if ( class_exists('KHM\Membership\Admin\ReportsPage') ) {
            new \KHM\Membership\Admin\ReportsPage();
        }
    }
}
