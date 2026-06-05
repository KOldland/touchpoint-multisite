<?php
namespace KHM;

use KHM\Cron\ConnectRFQUpsellWorker;
use KHM\Cron\ConnectRFQCommissionWorker;
use KHM\Cron\ConnectSubscriptionExpiryWorker;
use KHM\Migrations\RenameRfpToRfq;
use KHM\Migrations\AddConnectBuyerDirectoryColumns;
use KHM\Migrations\AddConnectRfpExtendedFields;
use KHM\Migrations\CreateConnectSavedSearchesTable;
use KHM\QuickBooks\QBOAuthEndpoint;
use KHM\QuickBooks\QBOWebhookEndpoint;
use KHM\Migrations\AddRFPUpsellSentAt;
use KHM\Atomic\AtomicArticleGenerator;
use KHM\Atomic\AtomicArticlePostType;
use KHM\Atomic\AtomicEmbeddingService;
use KHM\Atomic\AtomicMetaBox;
use KHM\Atomic\AtomicRegenerateEndpoint;
use KHM\Atomic\AtomicSchemaEmitter;
use KHM\Atomic\AtomicSearchEndpoint;
use KHM\Atomic\AtomicSearchWidget;
use KHM\Migrations\AtomicEmbeddingsMigration;
use KHM\Migrations\AddCommentaryConnectColumns;
use KHM\Migrations\ConnectProvidersMigration;
use KHM\Migrations\ConnectWorkflowMigration;
use KHM\Migrations\AddEngagedWorkflowFields;
use KHM\Migrations\AddRFPResponseFieldsToThreads;
use KHM\Migrations\AddBuyerIcpFieldsToThreads;
use KHM\Migrations\AddIsDemoToProviders;
use KHM\Migrations\AddBuyerFieldsToOpportunities;
use KHM\Migrations\CreateSellerPaymentProfilesTable;
use KHM\Migrations\CreateRFPSupportTables;
use KHM\PublicFrontend\ConnectDirectoryShortcode;
use KHM\Services\MarketingSuiteServices;
use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;
use KHM\Services\LevelRepository;

class Plugin {

    protected static $file;
    protected static $marketing_suite;

    public static function init( $file ) {
        self::$file = $file;
        // Hook into WordPress if available. These calls are placeholders.
        if ( function_exists('add_action') ) {
            add_action('init', [ self::class, 'on_init' ]);
            add_action('plugins_loaded', [ self::class, 'initialize_marketing_suite' ], 10);
        }
    }

    public static function on_init() {
        // Register custom post types, shortcodes, etc.
        self::initialize_atomic();
        self::initialize_connect();
    }

    /**
     * Initialize the Marketing Suite Services
     */
    public static function initialize_marketing_suite() {
        if (!class_exists('KHM\\Services\\MarketingSuiteServices')) {
            return;
        }

        try {
            // Initialize repositories
            $memberships = new MembershipRepository();
            $orders = new OrderRepository();
            $levels = new LevelRepository();

            // Initialize MarketingSuiteServices
            self::$marketing_suite = new MarketingSuiteServices($memberships, $orders, $levels);
            
            // Register all services
            self::$marketing_suite->register_services();
            
            // Trigger action to let other plugins know KHM is ready
            do_action('khm_marketing_suite_ready');
            
            error_log('KHM Marketing Suite initialized successfully');
            
        } catch (\Exception $e) {
            error_log('Failed to initialize KHM Marketing Suite: ' . $e->getMessage());
        }
    }

    /**
     * Bootstrap the Atomic Articles subsystem (CPT, REST endpoints, cron, widgets).
     *
     * @return void
     */
    public static function initialize_atomic(): void {
        // Run DB migration idempotently on every boot so the table is always present.
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

    /**
     * Bootstrap the initial Connect.Net subsystem.
     *
     * @return void
     */
    public static function initialize_connect(): void {
        ConnectProvidersMigration::run();
        ConnectWorkflowMigration::run();
        AddCommentaryConnectColumns::run();
        AddEngagedWorkflowFields::up();
        AddRFPResponseFieldsToThreads::up();
        AddBuyerIcpFieldsToThreads::up();
        AddIsDemoToProviders::up();
        AddBuyerFieldsToOpportunities::up();
        AddRFPUpsellSentAt::up();
        RenameRfpToRfq::up();
        AddConnectBuyerDirectoryColumns::up();
        AddConnectRfpExtendedFields::up();
        CreateSellerPaymentProfilesTable::run();
        CreateRFPSupportTables::run();
        CreateConnectSavedSearchesTable::up();

        ( new QBOAuthEndpoint() )->register();
        ( new QBOWebhookEndpoint() )->register();
    }

    public static function get_dir() {
        return dirname(self::$file);
    }

    public static function get_marketing_suite() {
        return self::$marketing_suite;
    }
}
