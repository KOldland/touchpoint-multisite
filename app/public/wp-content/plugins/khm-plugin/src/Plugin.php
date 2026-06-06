<?php
namespace KHM;

use KHM\Atomic\AtomicArticleGenerator;
use KHM\Atomic\AtomicArticlePostType;
use KHM\Atomic\AtomicEmbeddingService;
use KHM\Atomic\AtomicMetaBox;
use KHM\Atomic\AtomicRegenerateEndpoint;
use KHM\Atomic\AtomicSchemaEmitter;
use KHM\Atomic\AtomicSearchEndpoint;
use KHM\Atomic\AtomicSearchWidget;
use KHM\Migrations\AtomicEmbeddingsMigration;
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
        self::initialize_atomic();
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

    public static function initialize_atomic(): void {
        AtomicEmbeddingsMigration::run();

        ( new AtomicArticlePostType() )->register();
        ( new AtomicSchemaEmitter() )->register();
        ( new AtomicArticleGenerator() )->register();
        ( new AtomicMetaBox() )->register();
        ( new AtomicEmbeddingService() )->register();
        ( new AtomicRegenerateEndpoint() )->register();
        ( new AtomicSearchEndpoint() )->register();
        ( new AtomicSearchWidget() )->register();
    }

    public static function get_dir() {
        return dirname(self::$file);
    }

    public static function get_marketing_suite() {
        return self::$marketing_suite;
    }
}
