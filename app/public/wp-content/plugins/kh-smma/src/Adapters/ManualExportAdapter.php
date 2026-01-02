<?php
namespace KH_SMMA\Adapters;

use KH_SMMA\Services\ScheduleQueueProcessor;

use function add_filter;
use function time;
use function update_post_meta;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ManualExportAdapter {
    public function register() {
        add_filter( 'kh_smma_dispatch_schedule', array( $this, 'handle_manual_queue' ), 10, 4 );
    }

    public function handle_manual_queue( $result, $schedule_id, $payload, $context ) {
        $is_manual = ( isset( $context['delivery'] ) && 'manual_export' === $context['delivery'] ) || ( isset( $context['provider'] ) && 'manual' === $context['provider'] );

        if ( ! $is_manual ) {
            return $result;
        }

        $export_bundle = array(
            'schedule_id' => $schedule_id,
            'account_id'  => $context['account_id'] ?? 0,
            'payload'     => $payload,
            'generated'   => time(),
        );

        update_post_meta( $schedule_id, '_kh_smma_export_bundle', $export_bundle );
        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode'            => 'manual',
            'provider'        => $context['provider'] ?? 'manual',
            'payload_preview' => $payload,
            'note'            => __( 'Manual export bundle generated.', 'kh-smma' ),
        ) );

        return 'awaiting_manual_export';
    }
}
