<?php
namespace KH_SMMA;

use KH_SMMA\PostTypes\SocialAccountPostType;
use KH_SMMA\PostTypes\SocialCampaignPostType;
use KH_SMMA\PostTypes\SocialSchedulePostType;
use KH_SMMA\Meta\MetaRegistrar;
use KH_SMMA\Admin\AdminInterface;
use KH_SMMA\Admin\AuditLogPage;
use KH_SMMA\Admin\CapabilitySettingsPage;
use KH_SMMA\Admin\AssetsManager;
use KH_SMMA\Admin\ImageUploadPage;
use KH_SMMA\Admin\PendingApprovalsPage;
use KH_SMMA\Admin\ScheduleDetailPage;
use KH_SMMA\Admin\PostBoostPage;
use KH_SMMA\Services\ScheduleQueueProcessor;
use KH_SMMA\Services\TokenRepository;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\AnalyticsFeedbackService;
use KH_SMMA\Services\LifecycleSimulator;
use KH_SMMA\Services\EngagementMetricsService;
use KH_SMMA\Services\PhaseEngine;
use KH_SMMA\Services\FeatureFlags;
use KH_SMMA\Services\SmmaGenerator;
use KH_SMMA\Services\Card1StateStore;
use KH_SMMA\API\RestController;
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
use KH_SMMA\CLI\EventCatalogCommand;
use KH_SMMA\CLI\SettlementCommand;
use KH_SMMA\Reconciliation\PaidReconciliationService;
use KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService;
use KH_SMMA\Reconciliation\FxService;
use KH_SMMA\Reconciliation\SettlementWorker;
use KH_SMMA\Api\ReconciliationController;
use KH_SMMA\Api\SettlementAckController;
use KH_SMMA\CLI\SettlementDeliverCommand;
use KH_SMMA\Reconciliation\SettlementDeliveryService;
use KH_SMMA\Reconciliation\DeliveryIdempotencyStore;
use KH_SMMA\Reconciliation\SftpAccountingAdapter;
use KH_SMMA\Reconciliation\AccountingApiAdapter;
use KH_SMMA\Adapters\ReconciliationService;
use KH_SMMA\Admin\PaidReconciliationPage;
use KH_SMMA\Api\PaidReconciliationRunController;
use KH_SMMA\Api\ManualExportController;
use KH_SMMA\Api\SponsorApprovalController;
use KH_SMMA\CLI\ReconcileCommand;
use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Scheduling\DispatchEligibilityService;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\EventQueue;
use KH_SMMA\Telemetry\TelemetryRetryService;
use KH_SMMA\Telemetry\TelemetryPayloadSanitizer;
use KH_SMMA\Telemetry\TelemetryConfigService;
use KH_SMMA\Telemetry\TraceContext;
use KH_SMMA\Telemetry\AnalyticsFeedbackService as TelemetryAnalyticsFeedbackService;
use KH_SMMA\Telemetry\MetricsSnapshotRepository;
use KH_SMMA\Membership\SignupHandler;
use KH_SMMA\Membership\AttributionService;
use KH_SMMA\Admin\ObservabilityDashboardPage;
use KH_SMMA\Admin\TelemetryDebugPage;
use KH_SMMA\Telemetry\AlertEvaluator;
use KH_SMMA\Telemetry\TelemetryTraceService;
use KH_SMMA\Notifications\ApprovalNotificationService;
use KH_SMMA\SponsorApproval\ApprovalPermissionService;

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
     * @var PaidReconciliationService
     */
    private $reconciliation_service;

    /**
     * @var PaidReconciliationAdjustmentService
     */
    private $adjustment_service;

    /**
     * @var FxService
     */
    private $fx_service;

    /**
     * @var SettlementWorker
     */
    private $settlement_worker;

    /**
     * @var SettlementDeliveryService
     */
    private $delivery_service;

    /**
     * @var ReconciliationService
     */
    private $recon_service;

    /**
     * @var EventEmitter
     */
    private $event_emitter;

    /**
     * @var MetricsSnapshotRepository
     */
    private $snapshot_repo;

    /**
     * @var TelemetryAnalyticsFeedbackService
     */
    private $telemetry_analytics;

    /**
     * @var AlertEvaluator
     */
    private $alert_evaluator;

    /**
     * @var EventQueue
     */
    private $event_queue;

    /**
     * @var TelemetryRetryService
     */
    private $retry_service;

    /**
     * @var TelemetryPayloadSanitizer
     */
    private $payload_sanitizer;

    /**
     * @var TelemetryConfigService
     */
    private $telemetry_config;

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

        $this->vault                  = new CredentialVault();
        $this->token_repository       = new TokenRepository( $wpdb, $this->vault );
        $this->audit_logger           = new AuditLogger( $wpdb );
        $this->capability_manager     = new CapabilityManager();
        $this->analytics_feedback     = new AnalyticsFeedbackService();
        $this->lifecycle_simulator    = new LifecycleSimulator();
        $this->engagement_metrics     = new EngagementMetricsService();
        if ( $this->has_reconciliation_support() ) {
            $this->reconciliation_service = new PaidReconciliationService( $wpdb, $this->audit_logger );
            $this->fx_service             = FxService::from_config();
            $this->adjustment_service     = new PaidReconciliationAdjustmentService( $wpdb, $this->audit_logger );
            $this->settlement_worker      = new SettlementWorker( $wpdb, $this->adjustment_service, $this->fx_service, $this->audit_logger );
            $this->delivery_service       = new SettlementDeliveryService( $wpdb, $this->settlement_worker, $this->audit_logger, new DeliveryIdempotencyStore() );
            $this->recon_service          = new ReconciliationService( $wpdb, $this->audit_logger, $this->reconciliation_service );
        }
        $this->retry_service          = new TelemetryRetryService( $wpdb );
        $this->event_queue            = new EventQueue( $this->retry_service );
        $this->payload_sanitizer      = new TelemetryPayloadSanitizer();
        $this->event_emitter          = new EventEmitter( $this->audit_logger, $this->event_queue, $this->payload_sanitizer );
        $this->telemetry_config       = new TelemetryConfigService( $wpdb, $this->event_emitter );
        $this->snapshot_repo          = new MetricsSnapshotRepository( $wpdb );
        $this->telemetry_analytics    = new TelemetryAnalyticsFeedbackService( $this->snapshot_repo );
        $this->alert_evaluator        = new AlertEvaluator( $this->snapshot_repo, $this->event_emitter, $this->audit_logger );
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

        $phase_engine = new PhaseEngine( $wpdb );
        ( new AdminInterface( $this->token_repository, $this->audit_logger, $this->analytics_feedback, $this->lifecycle_simulator, $phase_engine ) )->register();
        ( new AuditLogPage( $wpdb ) )->register();
        ( new CapabilitySettingsPage() )->register();
        ( new AssetsManager() )->register();
        ( new ImageUploadPage() )->register();
        if ( $this->has_reconciliation_support() ) {
            ( new PaidReconciliationPage( $this->recon_service, $this->audit_logger ) )->register();
        }
        ( new PendingApprovalsPage( new ScheduleRepository( $this->audit_logger ), new ApprovalPermissionService() ) )->register();
        ( new ScheduleDetailPage() )->register();
        ( new PostBoostPage() )->register();
        // OBS-03/04: Observability dashboard with alert indicators.
        ( new ObservabilityDashboardPage( $this->snapshot_repo, $this->audit_logger, $this->alert_evaluator ) )->register();
        // OBS-08: Telemetry Debug page (manage_observability required).
        $trace_service = new TelemetryTraceService( $this->audit_logger, $this->payload_sanitizer );
        ( new TelemetryDebugPage( $trace_service ) )->register();
    }

    /**
     * Register queue services and channel adapters.
     */
    private function register_services() {
        global $wpdb;

        ( new ScheduleQueueProcessor( $this->token_repository, $this->audit_logger ) )->register();
        $this->analytics_feedback->register();
        $this->engagement_metrics->register();
        ( new PhaseEngine( $wpdb ) )->register();
        $flags = new FeatureFlags();
        $flags->ensure_defaults();
        ( new RestController( $flags, new SmmaGenerator(), $this->audit_logger, new PhaseEngine( $wpdb ), null, null, $this->event_emitter ) )->register();
        ( new DispatchEligibilityService( $this->audit_logger ) )->register();

        // OBS-02: Telemetry analytics aggregation.
        $this->telemetry_analytics->register();

        // OBS-04: Alert evaluation.
        $this->alert_evaluator->register();

        // OBS-06: Non-blocking event queue and retry service.
        $this->event_queue->register();
        $this->retry_service->register();

        // OBS-07: Telemetry governance — cleanup cron.
        $this->telemetry_config->register();

        // OBS: Membership telemetry listeners.
        ( new SignupHandler( $this->event_emitter ) )->register();
        ( new AttributionService( $this->event_emitter ) )->register();

        // OBS: schedule.dispatch — fires whenever ScheduleQueueProcessor updates status.
        $emitter = $this->event_emitter;
        add_action( 'kh_smma_schedule_status_changed', function ( $schedule_id, $status ) use ( $emitter ) {
            $result = 'completed' === $status || 'sandboxed' === $status ? 'dispatched'
                    : ( 'failed' === $status ? 'failed' : 'exported' );
            $emitter->emit( 'schedule.dispatch', array(
                'schedule_id' => (string) $schedule_id,
                'adapter'     => (string) get_post_meta( $schedule_id, '_kh_smma_delivery_mode', true ) ?: 'manual',
                'result'      => $result,
                'service'     => 'smma',
            ) );
        }, 10, 2 );
        ( new ManualExportAdapter() )->register();
        if ( $this->has_reconciliation_support() ) {
            ( new ReconciliationController(
                $this->reconciliation_service,
                $this->adjustment_service,
                $this->settlement_worker,
                $this->audit_logger
            ) )->register();
            $this->settlement_worker->register();
            ( new SettlementAckController( $this->delivery_service, $this->audit_logger ) )->register();
            ( new PaidReconciliationRunController( $this->recon_service, $this->audit_logger ) )->register();
        }
        ( new ManualExportController( $this->audit_logger ) )->register();
        ( new SponsorApprovalController( new ScheduleRepository( $this->audit_logger ), $this->audit_logger, new ApprovalPermissionService() ) )->register();
        ( new ApprovalNotificationService( $this->audit_logger ) )->register();
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
        global $wpdb;

        ( new LifecycleSimulatorCommand( $this->lifecycle_simulator, $this->analytics_feedback ) )->register();
        ( new EventCatalogCommand( new PhaseEngine( $wpdb ) ) )->register();
        if ( $this->has_reconciliation_support() ) {
            ( new SettlementCommand( $this->settlement_worker ) )->register();
            ( new SettlementDeliverCommand( $this->delivery_service, new SftpAccountingAdapter(), new AccountingApiAdapter() ) )->register();
            ( new ReconcileCommand( $this->recon_service ) )->register();
        }
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

        if ( ! wp_next_scheduled( 'kh_smma_phase_aggregate' ) ) {
            wp_schedule_event( time(), 'hourly', 'kh_smma_phase_aggregate' );
        }

        if ( ! wp_next_scheduled( 'kh_smma_run_settlement' ) ) {
            wp_schedule_event( time(), 'daily', 'kh_smma_run_settlement' );
        }

        // OBS-02: flush telemetry analytics snapshot every 5 minutes.
        if ( ! wp_next_scheduled( \KH_SMMA\Telemetry\AnalyticsFeedbackService::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'kh_smma_five_minutes', \KH_SMMA\Telemetry\AnalyticsFeedbackService::CRON_HOOK );
        }

        // OBS-04: alert evaluation every 5 minutes.
        if ( ! wp_next_scheduled( \KH_SMMA\Telemetry\AlertEvaluator::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'kh_smma_five_minutes', \KH_SMMA\Telemetry\AlertEvaluator::CRON_HOOK );
        }

        // OBS-06: event queue flush every 5 minutes.
        if ( ! wp_next_scheduled( \KH_SMMA\Telemetry\EventQueue::FLUSH_CRON_HOOK ) ) {
            wp_schedule_event( time(), 'kh_smma_five_minutes', \KH_SMMA\Telemetry\EventQueue::FLUSH_CRON_HOOK );
        }

        // OBS-06: replay buffered telemetry events every 5 minutes.
        if ( ! wp_next_scheduled( \KH_SMMA\Telemetry\TelemetryRetryService::REPLAY_CRON_HOOK ) ) {
            wp_schedule_event( time(), 'kh_smma_five_minutes', \KH_SMMA\Telemetry\TelemetryRetryService::REPLAY_CRON_HOOK );
        }

        // OBS-07: daily telemetry retention cleanup.
        if ( ! wp_next_scheduled( \KH_SMMA\Telemetry\TelemetryConfigService::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', \KH_SMMA\Telemetry\TelemetryConfigService::CRON_HOOK );
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

        if ( ! isset( $schedules['kh_smma_five_minutes'] ) ) {
            $schedules['kh_smma_five_minutes'] = array(
                'interval' => 300,
                'display'  => __( 'KH SMMA – every 5 minutes', 'kh-smma' ),
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
        global $wpdb;

        $plugin = new self();
        $plugin->register_autoloader();
        $plugin->bootstrap_services();
        $plugin->register_post_types();
        $plugin->register_meta();
        add_filter( 'cron_schedules', array( $plugin, 'register_custom_cron_interval' ) );
        $plugin->token_repository->install();
        ( new PhaseEngine( $wpdb ) )->install();
        $audit = new AuditLogger( $wpdb );
        ( new Card1StateStore( $wpdb ) )->install();
        if ( $plugin->has_reconciliation_support() ) {
            ( new PaidReconciliationService( $wpdb, $audit ) )->install();
            $adj_svc = new PaidReconciliationAdjustmentService( $wpdb, $audit );
            $adj_svc->install();
            $settlement_worker = new SettlementWorker( $wpdb, $adj_svc, FxService::from_config(), $audit );
            $settlement_worker->install();
            ( new SettlementDeliveryService( $wpdb, $settlement_worker, $audit, new DeliveryIdempotencyStore() ) )->install();
            ( new ReconciliationService( $wpdb, $audit, new PaidReconciliationService( $wpdb, $audit ) ) )->install();
        }
        // OBS-02: analytics snapshots table.
        ( new MetricsSnapshotRepository( $wpdb ) )->install();

        // OBS-06: telemetry retry buffer table.
        ( new TelemetryRetryService( $wpdb ) )->install();

        flush_rewrite_rules();

        if ( ! wp_next_scheduled( 'kh_smma_process_queue' ) ) {
            wp_schedule_event( time(), 'kh_smma_minute', 'kh_smma_process_queue' );
        }

        if ( ! wp_next_scheduled( 'kh_smma_phase_aggregate' ) ) {
            wp_schedule_event( time(), 'hourly', 'kh_smma_phase_aggregate' );
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

        $timestamp = wp_next_scheduled( 'kh_smma_phase_aggregate' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'kh_smma_phase_aggregate' );
        }

        $timestamp = wp_next_scheduled( 'kh_smma_run_settlement' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'kh_smma_run_settlement' );
        }

        $timestamp = wp_next_scheduled( \KH_SMMA\Telemetry\AnalyticsFeedbackService::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, \KH_SMMA\Telemetry\AnalyticsFeedbackService::CRON_HOOK );
        }

        $timestamp = wp_next_scheduled( \KH_SMMA\Telemetry\AlertEvaluator::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, \KH_SMMA\Telemetry\AlertEvaluator::CRON_HOOK );
        }

        $timestamp = wp_next_scheduled( \KH_SMMA\Telemetry\EventQueue::FLUSH_CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, \KH_SMMA\Telemetry\EventQueue::FLUSH_CRON_HOOK );
        }

        $timestamp = wp_next_scheduled( \KH_SMMA\Telemetry\TelemetryRetryService::REPLAY_CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, \KH_SMMA\Telemetry\TelemetryRetryService::REPLAY_CRON_HOOK );
        }

        $timestamp = wp_next_scheduled( \KH_SMMA\Telemetry\TelemetryConfigService::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, \KH_SMMA\Telemetry\TelemetryConfigService::CRON_HOOK );
        }

        flush_rewrite_rules();
    }

    /**
     * Reconciliation is optional in this branch; skip wiring when the package is absent.
     */
    private function has_reconciliation_support() {
        $required = array(
            PaidReconciliationService::class,
            PaidReconciliationAdjustmentService::class,
            FxService::class,
            SettlementWorker::class,
            SettlementDeliveryService::class,
            DeliveryIdempotencyStore::class,
            SftpAccountingAdapter::class,
            AccountingApiAdapter::class,
            ReconciliationService::class,
            ReconciliationController::class,
            SettlementAckController::class,
            PaidReconciliationRunController::class,
            PaidReconciliationPage::class,
            SettlementCommand::class,
            SettlementDeliverCommand::class,
            ReconcileCommand::class,
        );

        foreach ( $required as $class ) {
            if ( ! class_exists( $class ) ) {
                return false;
            }
        }

        return true;
    }
}
