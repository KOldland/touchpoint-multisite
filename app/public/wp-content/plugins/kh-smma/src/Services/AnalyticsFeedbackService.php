<?php
namespace KH_SMMA\Services;

use function absint;
use function add_action;
use function apply_filters;
use function do_action;
use function get_option;
use function update_option;
use function get_post_meta;
use function get_the_title;
use function time;
use function array_unshift;
use function array_slice;
use function sanitize_key;
use function wp_parse_args;
use function is_array;
use function is_numeric;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tracks lightweight analytics feedback derived from schedule telemetry/metrics.
 *
 * Provides a snapshot that the admin dashboard and other plugins can consume
 * while we plan deeper data warehouse integrations.
 */
class AnalyticsFeedbackService {
    const OPTION_KEY = 'kh_smma_analytics_snapshot';

    public function register() {
        add_action( 'kh_smma_schedule_status_changed', array( $this, 'handle_status_change' ), 10, 2 );
    }

    public function handle_status_change( $schedule_id, $status ) {
        $schedule_id = absint( $schedule_id );
        if ( ! $schedule_id ) {
            return;
        }

        $status  = $status ? sanitize_key( $status ) : 'unknown';
        $snapshot = $this->get_snapshot();

        $event = $this->build_event_payload( $schedule_id, $status );

        if ( empty( $event ) ) {
            return;
        }

        $snapshot['updated_at'] = time();
        $snapshot['overall'][ $status ] = isset( $snapshot['overall'][ $status ] ) ? (int) $snapshot['overall'][ $status ] + 1 : 1;

        $provider = $event['provider'] ?: 'manual';
        if ( ! isset( $snapshot['provider_summary'][ $provider ] ) ) {
            $snapshot['provider_summary'][ $provider ] = array();
        }
        $snapshot['provider_summary'][ $provider ][ $status ] = isset( $snapshot['provider_summary'][ $provider ][ $status ] )
            ? (int) $snapshot['provider_summary'][ $provider ][ $status ] + 1
            : 1;

        $campaign_id = $event['campaign_id'];
        if ( $campaign_id ) {
            if ( ! isset( $snapshot['campaign_summary'][ $campaign_id ] ) ) {
                $snapshot['campaign_summary'][ $campaign_id ] = array(
                    'label' => $event['campaign_label'],
                );
            }
            $snapshot['campaign_summary'][ $campaign_id ][ $status ] = isset( $snapshot['campaign_summary'][ $campaign_id ][ $status ] )
                ? (int) $snapshot['campaign_summary'][ $campaign_id ][ $status ] + 1
                : 1;
        }

        array_unshift( $snapshot['recent_events'], $event );
        $snapshot['recent_events'] = array_slice( $snapshot['recent_events'], 0, 10 );

        update_option( self::OPTION_KEY, $snapshot, false );

        /**
         * Fires whenever a KH SMMA analytics feedback event is recorded.
         *
         * @param array $event    Event payload.
         * @param array $snapshot Updated analytics snapshot.
         */
        do_action( 'kh_smma_analytics_feedback_recorded', $event, $snapshot );
    }

    public function get_snapshot(): array {
        $defaults = array(
            'updated_at'       => 0,
            'overall'          => array(),
            'provider_summary' => array(),
            'campaign_summary' => array(),
            'recent_events'    => array(),
        );

        $snapshot = get_option( self::OPTION_KEY, array() );

        return wp_parse_args( is_array( $snapshot ) ? $snapshot : array(), $defaults );
    }

    private function build_event_payload( int $schedule_id, string $status ): array {
        $account_id   = absint( get_post_meta( $schedule_id, '_kh_smma_account_id', true ) );
        $campaign_id  = absint( get_post_meta( $schedule_id, '_kh_smma_campaign_id', true ) );
        $provider     = $account_id ? get_post_meta( $account_id, '_kh_smma_provider', true ) : 'manual';
        $scheduled_at = (int) get_post_meta( $schedule_id, '_kh_smma_scheduled_at', true );
        $processed_at = (int) get_post_meta( $schedule_id, '_kh_smma_processed_at', true );
        $approval     = get_post_meta( $schedule_id, '_kh_smma_approval_status', true );
        $telemetry    = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );
        $metrics      = get_post_meta( $schedule_id, '_kh_smma_result_metrics', true );
        $last_error   = get_post_meta( $schedule_id, '_kh_smma_last_error', true );

        $event = array(
            'schedule_id'    => $schedule_id,
            'status'         => $status,
            'provider'       => $provider ?: 'manual',
            'account_id'     => $account_id,
            'account_label'  => $account_id ? get_the_title( $account_id ) : '',
            'campaign_id'    => $campaign_id,
            'campaign_label' => $campaign_id ? get_the_title( $campaign_id ) : '',
            'scheduled_at'   => $scheduled_at,
            'processed_at'   => $processed_at,
            'approval'       => $approval ?: 'auto',
            'telemetry'      => $this->summarize_telemetry( $telemetry ),
            'metrics'        => $this->summarize_metrics( $metrics ),
            'error'          => $last_error,
            'timestamp'      => time(),
        );

        /**
         * Allow other systems to adjust the analytics event before it is stored.
         */
        return apply_filters( 'kh_smma_analytics_event_payload', $event );
    }

    private function summarize_telemetry( $telemetry ): array {
        if ( ! is_array( $telemetry ) ) {
            return array();
        }

        $summary = array();
        foreach ( array( 'mode', 'note', 'error', 'response_code', 'provider' ) as $key ) {
            if ( isset( $telemetry[ $key ] ) && '' !== $telemetry[ $key ] ) {
                $summary[ $key ] = $telemetry[ $key ];
            }
        }

        if ( ! empty( $telemetry['rate_limits'] ) && is_array( $telemetry['rate_limits'] ) ) {
            $summary['rate_limits'] = $telemetry['rate_limits'];
        }

        return $summary;
    }

    private function summarize_metrics( $metrics ): array {
        if ( ! is_array( $metrics ) ) {
            return array();
        }

        $summary = array();
        foreach ( array( 'note', 'response', 'queued_at' ) as $key ) {
            if ( isset( $metrics[ $key ] ) && '' !== $metrics[ $key ] ) {
                $summary[ $key ] = $metrics[ $key ];
            }
        }

        if ( isset( $metrics['metrics'] ) && is_array( $metrics['metrics'] ) ) {
            $summary['metrics'] = array();
            foreach ( $metrics['metrics'] as $metric_key => $metric_value ) {
                if ( is_numeric( $metric_value ) ) {
                    $summary['metrics'][ $metric_key ] = (int) $metric_value;
                }
            }
        }

        return $summary;
    }
}
