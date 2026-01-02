<?php
namespace KH_SMMA\Services;

use WP_Query;
use WP_Error;

use KH_SMMA\Services\TokenRepository;
use KH_SMMA\Services\AuditLogger;

use function __;
use function absint;
use function add_action;
use function apply_filters;
use function get_post_meta;
use function time;
use function update_post_meta;
use function wp_reset_postdata;
use function array_merge;
use function in_array;
use function do_action;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScheduleQueueProcessor {
    const STATUS_META     = '_kh_smma_schedule_status';
    const SCHEDULED_META  = '_kh_smma_scheduled_at';
    const LAST_ERROR_META = '_kh_smma_last_error';
    const PROCESSED_META  = '_kh_smma_processed_at';

    /** @var TokenRepository */
    private $tokens;

    /** @var AuditLogger */
    private $logger;

    public function __construct( TokenRepository $tokens, AuditLogger $logger ) {
        $this->tokens = $tokens;
        $this->logger = $logger;
    }

    public function register() {
        add_action( 'kh_smma_run_queue', array( $this, 'process_due_schedules' ) );
    }

    public function process_due_schedules() {
        $now = time();

        $query = new WP_Query( array(
            'post_type'      => 'kh_smma_schedule',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => self::STATUS_META,
                    'value'   => 'pending',
                    'compare' => '=',
                ),
                array(
                    'key'     => self::SCHEDULED_META,
                    'value'   => $now,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ),
            ),
        ) );

        if ( empty( $query->posts ) ) {
            return;
        }

        foreach ( $query->posts as $schedule_id ) {
            $this->mark_status( $schedule_id, 'processing' );

            $account_id  = absint( get_post_meta( $schedule_id, '_kh_smma_account_id', true ) );
            $campaign_id = absint( get_post_meta( $schedule_id, '_kh_smma_campaign_id', true ) );
            $payload     = get_post_meta( $schedule_id, '_kh_smma_payload', true );
            $delivery    = get_post_meta( $schedule_id, '_kh_smma_delivery_mode', true );
            $provider    = $account_id ? get_post_meta( $account_id, '_kh_smma_provider', true ) : 'manual';
            $token_id    = $account_id ? absint( get_post_meta( $account_id, '_kh_smma_token_id', true ) ) : 0;
            $sandbox     = $account_id ? (bool) get_post_meta( $account_id, '_kh_smma_sandbox_mode', true ) : false;

            $context = array(
                'account_id'  => $account_id,
                'campaign_id' => $campaign_id,
                'delivery'    => $delivery ?: 'auto',
                'provider'    => $provider ?: 'manual',
                'token'       => $this->tokens->get_token( $token_id ),
            );

            if ( $sandbox ) {
                $this->handle_sandbox_preview( $schedule_id, $payload, $context );
                continue;
            }

            try {
                $result = apply_filters( 'kh_smma_dispatch_schedule', null, $schedule_id, $payload, $context );

                if ( $result instanceof WP_Error ) {
                    self::log_telemetry( $schedule_id, array(
                        'mode'     => 'live',
                        'provider' => $context['provider'],
                        'error'    => $result->get_error_message(),
                    ) );
                    $this->mark_status( $schedule_id, 'failed', array( 'error' => $result->get_error_message() ) );
                    continue;
                }

                if ( is_string( $result ) && ! empty( $result ) ) {
                    $this->mark_status( $schedule_id, $result );
                } else {
                    $this->mark_status( $schedule_id, 'completed' );
                }
            } catch ( \Throwable $e ) {
                self::log_telemetry( $schedule_id, array(
                    'mode'     => 'live',
                    'provider' => $context['provider'],
                    'error'    => $e->getMessage(),
                ) );
                $this->mark_status( $schedule_id, 'failed', array( 'error' => $e->getMessage() ) );
            }
        }

        wp_reset_postdata();
    }

    private function handle_sandbox_preview( $schedule_id, $payload, $context ) {
        self::log_telemetry( $schedule_id, array(
            'mode'            => 'sandbox',
            'provider'        => $context['provider'] ?? 'manual',
            'payload_preview' => $payload,
            'note'            => __( 'Sandbox enabled – payload logged without hitting the API.', 'kh-smma' ),
        ) );

        $this->logger->log( 'schedule_sandbox_preview', array(
            'object_type' => 'schedule',
            'object_id'   => $schedule_id,
            'details'     => array(
                'account_id' => $context['account_id'] ?? 0,
                'provider'   => $context['provider'] ?? 'manual',
            ),
        ) );

        $this->mark_status( $schedule_id, 'sandboxed' );
    }

    public static function log_telemetry( $schedule_id, array $data ) {
        if ( ! $schedule_id ) {
            return;
        }

        $telemetry = array_merge(
            array(
                'timestamp' => time(),
            ),
            $data
        );

        update_post_meta( $schedule_id, '_kh_smma_last_telemetry', $telemetry );
    }

    private function mark_status( $schedule_id, $status, $args = array() ) {
        update_post_meta( $schedule_id, self::STATUS_META, $status );

        if ( in_array( $status, array( 'completed', 'sandboxed' ), true ) ) {
            update_post_meta( $schedule_id, self::PROCESSED_META, time() );
            update_post_meta( $schedule_id, self::LAST_ERROR_META, '' );
        }

        if ( 'failed' === $status && ! empty( $args['error'] ) ) {
            update_post_meta( $schedule_id, self::LAST_ERROR_META, $args['error'] );
        }

        do_action( 'kh_smma_schedule_status_changed', $schedule_id, $status );
    }
}
