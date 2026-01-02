<?php
namespace KH_SMMA\Services;

use WP_Error;

use function __;
use function absint;
use function get_post_meta;
use function get_posts;
use function is_wp_error;
use function sanitize_text_field;
use function time;
use function update_post_meta;
use function wp_insert_post;
use function do_action;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates a representative schedule lifecycle to seed telemetry/analytics for QA.
 */
class LifecycleSimulator {
    /**
     * Run a simulated lifecycle for the provided account ID.
     *
     * @param int $account_id
     *
     * @return int|WP_Error Schedule ID on success.
     */
    public function run( $account_id = 0 ) {
        $account_id = $account_id ? absint( $account_id ) : $this->get_default_account_id();
        if ( ! $account_id ) {
            return new WP_Error( 'kh_smma_no_account', __( 'No KH SMMA account available for the lifecycle simulation.', 'kh-smma' ) );
        }

        $provider = sanitize_text_field( get_post_meta( $account_id, '_kh_smma_provider', true ) );
        $schedule_id = wp_insert_post( array(
            'post_type'   => 'kh_smma_schedule',
            'post_status' => 'publish',
            'post_title'  => __( 'Lifecycle Simulation Post', 'kh-smma' ),
            'post_content'=> __( 'Demonstration payload for telemetry + analytics tracking.', 'kh-smma' ),
        ) );

        if ( is_wp_error( $schedule_id ) ) {
            return $schedule_id;
        }

        $payload = array(
            'message' => __( 'Lifecycle simulation payload', 'kh-smma' ),
        );

        update_post_meta( $schedule_id, '_kh_smma_account_id', $account_id );
        update_post_meta( $schedule_id, '_kh_smma_campaign_id', 0 );
        update_post_meta( $schedule_id, '_kh_smma_payload', $payload );
        update_post_meta( $schedule_id, '_kh_smma_scheduled_at', time() );
        update_post_meta( $schedule_id, '_kh_smma_delivery_mode', 'auto' );
        update_post_meta( $schedule_id, '_kh_smma_approval_status', 'requested' );
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'pending_approval' );

        // Stage 1: Sandbox draft preview.
        $this->transition_status( $schedule_id, 'sandboxed', array(
            'mode'     => 'sandbox',
            'provider' => $provider,
            'note'     => __( 'Lifecycle sandbox preview generated.', 'kh-smma' ),
        ) );

        // Stage 2: Awaiting approval.
        $this->transition_status( $schedule_id, 'pending_approval', array(
            'mode'     => 'live',
            'provider' => $provider,
            'note'     => __( 'Lifecycle demo awaiting approval.', 'kh-smma' ),
        ) );

        // Stage 3: Approved + queued.
        update_post_meta( $schedule_id, '_kh_smma_approval_status', 'approved' );
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'pending' );
        $this->transition_status( $schedule_id, 'pending', array(
            'mode'     => 'live',
            'provider' => $provider,
            'note'     => __( 'Lifecycle demo approved for dispatch.', 'kh-smma' ),
        ) );

        // Stage 4: Published completion.
        $metrics = array(
            'note'      => __( 'Lifecycle simulation completed.', 'kh-smma' ),
            'queued_at' => time(),
            'metrics'   => array(
                'reach'      => 420,
                'clicks'     => 37,
                'engagement' => 18,
            ),
        );

        update_post_meta( $schedule_id, '_kh_smma_result_metrics', $metrics );

        $this->transition_status( $schedule_id, 'completed', array(
            'mode'          => 'live',
            'provider'      => $provider,
            'note'          => __( 'Lifecycle demo dispatched.', 'kh-smma' ),
            'response_code' => 200,
        ) );

        return $schedule_id;
    }

    private function transition_status( $schedule_id, $status, array $telemetry = array() ) {
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', $status );
        if ( ! empty( $telemetry ) ) {
            ScheduleQueueProcessor::log_telemetry( $schedule_id, $telemetry );
        }
        do_action( 'kh_smma_schedule_status_changed', $schedule_id, $status );
    }

    private function get_default_account_id() {
        $accounts = get_posts( array(
            'post_type'      => 'kh_smma_account',
            'post_status'    => 'publish',
            'numberposts'    => 1,
            'fields'         => 'ids',
        ) );

        return ! empty( $accounts ) ? absint( $accounts[0] ) : 0;
    }
}
