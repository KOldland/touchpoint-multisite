<?php
namespace KH_SMMA\CLI;

use KH_SMMA\Services\LifecycleSimulator;
use KH_SMMA\Services\AnalyticsFeedbackService;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LifecycleSimulatorCommand {
    /**
     * @var LifecycleSimulator
     */
    private $simulator;

    /**
     * @var AnalyticsFeedbackService
     */
    private $analytics;

    public function __construct( LifecycleSimulator $simulator, AnalyticsFeedbackService $analytics ) {
        $this->simulator = $simulator;
        $this->analytics = $analytics;
    }

    public function register() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
            return;
        }

        WP_CLI::add_command( 'kh-smma lifecycle-sim', $this );
    }

    /**
     * Execute the lifecycle simulator and emit telemetry JSON.
     *
     * ## OPTIONS
     *
     * [--account_id=<id>]
     * : Optional account ID to reuse. If omitted, the command will use any existing account or create a demo one.
     *
     * ## EXAMPLES
     *
     *     wp kh-smma lifecycle-sim
     *     wp kh-smma lifecycle-sim --account_id=123
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke( $args, $assoc_args ) {
        $account_id = isset( $assoc_args['account_id'] ) ? (int) $assoc_args['account_id'] : $this->resolve_account();

        if ( empty( $account_id ) ) {
            WP_CLI::error( 'Could not resolve or create an account.' );
        }

        $result = $this->simulator->run( $account_id );

        if ( is_wp_error( $result ) ) {
            WP_CLI::error( 'Lifecycle simulation failed: ' . $result->get_error_message() );
        }

        $schedule_id = (int) $result;
        $status      = get_post_meta( $schedule_id, '_kh_smma_schedule_status', true );
        $telemetry   = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );
        $metrics     = get_post_meta( $schedule_id, '_kh_smma_result_metrics', true );
        $snapshot    = $this->analytics->get_snapshot();

        $output = array(
            'schedule_id'          => $schedule_id,
            'status'               => $status ?: 'unknown',
            'telemetry'            => $telemetry ?: new \stdClass(),
            'metrics'              => $metrics ?: new \stdClass(),
            'snapshot_overall'     => $snapshot['overall'],
            'snapshot_recent_count'=> count( $snapshot['recent_events'] ),
        );

        WP_CLI::line( wp_json_encode( $output, JSON_PRETTY_PRINT ) );
    }

    /**
     * Resolve or create a demo account.
     *
     * @return int|null
     */
    private function resolve_account() {
        $existing = get_posts( array(
            'post_type'      => 'kh_smma_account',
            'post_status'    => 'publish',
            'numberposts'    => 1,
            'fields'         => 'ids',
        ) );

        if ( ! empty( $existing ) ) {
            return (int) $existing[0];
        }

        $account_id = wp_insert_post( array(
            'post_type'   => 'kh_smma_account',
            'post_status' => 'publish',
            'post_title'  => 'WP-CLI Lifecycle Demo',
        ) );

        if ( is_wp_error( $account_id ) ) {
            WP_CLI::warning( 'Failed to create account: ' . $account_id->get_error_message() );

            return null;
        }

        update_post_meta( $account_id, '_kh_smma_provider', 'manual' );
        update_post_meta( $account_id, '_kh_smma_status', 'connected' );

        return (int) $account_id;
    }
}
