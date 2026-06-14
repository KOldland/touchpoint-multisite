<?php
namespace KHM;

use KHM\Services\MarketingSuiteServices;
use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;
use KHM\Services\LevelRepository;

class Plugin {

    protected static $file;
    protected static $marketing_suite;

    public static function init( $file ) {
        self::$file = $file;
        if ( function_exists('add_action') ) {
            add_action('init', [ self::class, 'on_init' ]);
            add_action('plugins_loaded', [ self::class, 'initialize_marketing_suite' ], 10);
        }
    }

    public static function on_init() {
        // Atomic Article system moved to kh-editorial-intelligence.
    }

    public static function initialize_marketing_suite() {
        if (!class_exists('KHM\\Services\\MarketingSuiteServices')) {
            return;
        }

        try {
            $memberships = new MembershipRepository();
            $orders = new OrderRepository();
            $levels = new LevelRepository();

            self::$marketing_suite = new MarketingSuiteServices($memberships, $orders, $levels);
            self::$marketing_suite->register_services();
            
            do_action('khm_marketing_suite_ready');
            
            error_log('KHM Marketing Suite initialized successfully');
            
        } catch (\Exception $e) {
            error_log('Failed to initialize KHM Marketing Suite: ' . $e->getMessage());
        }
    }

    public static function get_dir() {
        return dirname(self::$file);
    }

    public static function get_marketing_suite() {
        return self::$marketing_suite;
    }
}
