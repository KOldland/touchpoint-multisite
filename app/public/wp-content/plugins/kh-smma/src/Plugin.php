<?php
namespace KH_SMMA;

use KH_SMMA\PostTypes\SocialAccountPostType;
use KH_SMMA\PostTypes\SocialCampaignPostType;
use KH_SMMA\PostTypes\SocialSchedulePostType;
use KH_SMMA\Meta\MetaRegistrar;
use KH_SMMA\Admin\AdminInterface;
use KH_SMMA\Admin\AuditLogPage;
use KH_SMMA\Admin\CapabilitySettingsPage;
use KH_SMMA\Services\ScheduleQueueProcessor;
use KH_SMMA\Services\TokenRepository;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\AnalyticsFeedbackService;
use KH_SMMA\Services\LifecycleSimulator;
use KH_SMMA\Services\EngagementMetricsService;
use KH_SMMA\Security\CredentialVault;
use KH_SMMA\Security\CapabilityManager;
use KH_SMMA\Integration\MarketingSuiteBridge;
use KH_SMMA\Integration\SocialStripBridge;
use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Adapters\MetaChannelAdapter;
use KH_SMMA\Adapters\LinkedInChannelAdapter;
use KH_SMMA\Adapters\TwitterChannelAdapter;
use KH_SMMA\OAuth\OAuthManager;
use KH_SMMA\CLI\LifecycleSimulatorCommand;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    /**
     * @var TokenRepository
     */
    private $token_repository;

    /**
     * @var CredentialVault
     */
    private $vault;

    /**
     * @var \KH_SMMA\Services\AuditLogger
     */
    private $audit_logger;

    /**
     * @var \KH_SMMA\Security\CapabilityManager
     */
    private $capability_manager;

    /**
     * @var AnalyticsFeedbackService
     */
    private $analytics_feedback;

    /**
     * @var EngagementMetricsService
     */
    private $engagement_metrics;

    /**
     * @var LifecycleSimulator
     */
    private $lifecycle_simulator;

    /**
     * Primary bootstrap entrypoint.
     */
    public function register() {
        $this->register_autoloader();
        $this->bootstrap_services();
        $this->register_post_types();
        $this->register_meta();
        $this->register_hooks();
        $this->register_admin();
        $this->register_services();
        $this->register_integrations();
        $this->register_oauth();
        $this->register_cli();
        $this->capability_manager->register();
    }

    /**
     * Simple PSR-4-ish autoloader so we can add additional classes without manual requires.
     */
    private function register_autoloader() {
        spl_autoload_register( function ( $class ) {
            if ( strpos( $class, __NAMESPACE__ . '\\' ) !== 0 ) {
                return;
            }

            $relative     = str_replace( __NAMESPACE__ . '\\', '', $class );
            $relative     = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
            $file         = KH_SMMA_PATH . 'src/' . $relative . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        } );
    }

    /**
     * Bootstrap shared services.
     */
    private function bootstrap_services() {
        global $wpdb;

        $this->vault               = new CredentialVault();
        $this->token_repository    = new TokenRepository( $wpdb, $this->vault );
        $this->audit_logger        = new AuditLogger( $wpdb );
        $this->capability_manager  = new CapabilityManager();
        $this->analytics_feedback  = new AnalyticsFeedbackService();
        $this->lifecycle_simulator = new LifecycleSimulator();
        $this->engagement_metrics  = new EngagementMetricsService();
    }

    /**
     * Register the core custom post types that act as data containers for accounts, campaigns, and schedules.
     */
    private function register_post_types() {
        ( new SocialAccountPostType() )->register();
        ( new SocialCampaignPostType() )->register();
        ( new SocialSchedulePostType() )->register();
    }

    /**
     * Register structured meta for CPTs.
     */
    private function register_meta() {
        ( new MetaRegistrar() )->register();
    }

    /**
     * Register admin UI handlers.
     */
    private function register_admin() {
        global $wpdb;

        ( new AdminInterface( $this->token_repository, $this->audit_logger, $this->analytics_feedback, $this->lifecycle_simulator ) )->register();
        ( new AuditLogPage( $wpdb ) )->register();
        ( new CapabilitySettingsPage() )->register();
    }

    /**
     * Register queue services and channel adapters.
     */
    private function register_services() {
        ( new ScheduleQueueProcessor( $this->token_repository, $this->audit_logger ) )->register();
        $this->analytics_feedback->register();
        $this->engagement_metrics->register();
        ( new ManualExportAdapter() )->register();
        ( new MetaChannelAdapter( $this->token_repository ) )->register();
        ( new LinkedInChannelAdapter( $this->token_repository ) )->register();
        ( new TwitterChannelAdapter( $this->token_repository ) )->register();
    }

    private function register_integrations() {
        ( new MarketingSuiteBridge() )->register();
        ( new SocialStripBridge() )->register();
    }

    private function register_oauth() {
        ( new OAuthManager( $this->token_repository ) )->register();
    }

    private function register_cli() {
        ( new LifecycleSimulatorCommand( $this->lifecycle_simulator, $this->analytics_feedback ) )->register();
    }

    /**
     * Hook into WordPress for cron events and integration entrypoints.
     */
    private function register_hooks() {
        add_action( 'init', array( $this, 'register_cron' ) );
        add_filter( 'cron_schedules', array( $this, 'register_custom_cron_interval' ) );
        add_action( 'kh_smma_process_queue', array( $this, 'handle_queue_processing' ) );
    }

    /**
     * Schedule the processing event that future queue workers will use.
     */
    public function register_cron() {
        if ( ! wp_next_scheduled( 'kh_smma_process_queue' ) ) {
            wp_schedule_event( time(), 'kh_smma_minute', 'kh_smma_process_queue' );
        }
    }

    /**
     * Add a lightweight minute interval so scheduled posts can be processed quickly.
     *
     * @param array $schedules
     *
     * @return array
     */
    public function register_custom_cron_interval( $schedules ) {
        if ( ! isset( $schedules['kh_smma_minute'] ) ) {
            $schedules['kh_smma_minute'] = array(
                'interval' => 60,
                'display'  => __( 'KH SMMA – every minute', 'kh-smma' ),
            );
        }

        return $schedules;
    }

    /**
     * Placeholder queue processor. The actual dispatcher will be added as adapters come online.
     */
    public function handle_queue_processing() {
        /**
         * Fires when the KH SMMA queue should be processed.
         *
         * Allows other KH plugins (Ad Manager, Marketing Suite, Analytics) to hook into the dispatcher.
         */
        do_action( 'kh_smma_run_queue' );
    }

    /**
     * Activation callback – ensures cron schedules exist and future DB tables can be created.
     */
    public static function activate() {
        $plugin = new self();
        $plugin->register_autoloader();
        $plugin->bootstrap_services();
        $plugin->register_post_types();
        $plugin->register_meta();
        add_filter( 'cron_schedules', array( $plugin, 'register_custom_cron_interval' ) );
        $plugin->token_repository->install();

        flush_rewrite_rules();

        if ( ! wp_next_scheduled( 'kh_smma_process_queue' ) ) {
            wp_schedule_event( time(), 'kh_smma_minute', 'kh_smma_process_queue' );
        }
    }

    /**
     * Deactivation callback cleans up cron entries but keeps data intact.
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'kh_smma_process_queue' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'kh_smma_process_queue' );
        }

        flush_rewrite_rules();
    }
}
