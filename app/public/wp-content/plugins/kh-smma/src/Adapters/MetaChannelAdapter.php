<?php
namespace KH_SMMA\Adapters;

use KH_SMMA\Services\TokenRepository;
use KH_SMMA\Services\ScheduleQueueProcessor;
use WP_Error;

use function __;
use function add_filter;
use function apply_filters;
use function update_post_meta;
use function time;
use function is_wp_error;
use function wp_remote_post;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_header;
use function wp_json_encode;
use function json_decode;
use function is_array;
use function array_filter;
use function is_numeric;
use function in_array;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Placeholder adapter for Meta (Facebook/Instagram) publishing.
 */
class MetaChannelAdapter {
    /** @var TokenRepository */
    private $tokens;

    public function __construct( TokenRepository $tokens ) {
        $this->tokens = $tokens;
    }

    public function register() {
        add_filter( 'kh_smma_dispatch_schedule', array( $this, 'handle_meta_dispatch' ), 20, 4 );
    }

    public function handle_meta_dispatch( $result, $schedule_id, $payload, $context ) {
        if ( empty( $context['provider'] ) || 'meta' !== $context['provider'] ) {
            return $result;
        }

        $token = $context['token'];
        if ( empty( $token['access_token'] ) ) {
            return new WP_Error( 'kh_smma_meta_missing_token', __( 'Meta account missing token.', 'kh-smma' ) );
        }

        $page_id = ! empty( $token['page_id'] ) ? $token['page_id'] : 'me';
        $endpoint = sprintf( 'https://graph.facebook.com/v18.0/%s/feed', $page_id );

        $asset      = isset( $payload['asset'] ) && is_array( $payload['asset'] ) ? $payload['asset'] : array();
        $page_token = $token['page_access_token'] ?? $token['access_token'];

        if ( empty( $page_token ) ) {
            return new WP_Error( 'kh_smma_meta_no_page_token', __( 'Meta page access token missing.', 'kh-smma' ) );
        }

        $body = array(
            'access_token' => $page_token,
            'message'      => $payload['message'] ?? '',
        );

        if ( ! empty( $asset['link'] ) ) {
            $body['link'] = $asset['link'];
        }

        if ( ! empty( $asset['media']['url'] ) ) {
            $body['url'] = $asset['media']['url'];
        }

        $body = apply_filters( 'kh_smma_meta_body', $body, $payload, $context );
        $log_body = $this->sanitize_body_for_telemetry( $body );

        $response = wp_remote_post( $endpoint, array(
            'timeout' => 15,
            'body'    => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
                'mode'     => 'live',
                'provider' => 'meta',
                'error'    => $response->get_error_message(),
                'request'  => array(
                    'endpoint' => $endpoint,
                    'body'     => $log_body,
                ),
            ) );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            $error_body = wp_remote_retrieve_body( $response );
            ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
                'mode'          => 'live',
                'provider'      => 'meta',
                'response_code' => $code,
                'response'      => $error_body,
                'error'         => __( 'Meta API responded with an error.', 'kh-smma' ),
                'request'       => array(
                    'endpoint' => $endpoint,
                    'body'     => $log_body,
                ),
                'rate_limits'   => $this->collect_rate_limits( $response ),
            ) );
            return new WP_Error( 'kh_smma_meta_http_error', __( 'Meta API responded with an error.', 'kh-smma' ), $error_body );
        }

        $data    = json_decode( wp_remote_retrieve_body( $response ), true );
        $metrics = $this->extract_metrics( $data, $payload, $context, $schedule_id );

        update_post_meta( $schedule_id, '_kh_smma_result_metrics', array(
            'note'      => 'Meta post published',
            'queued_at' => time(),
            'response'  => $data,
            'metrics'   => $metrics,
        ) );

        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode'          => 'live',
            'provider'      => 'meta',
            'response_code' => $code,
            'response'      => $data,
            'request'       => array(
                'endpoint' => $endpoint,
                'body'     => $log_body,
            ),
            'rate_limits'   => $this->collect_rate_limits( $response ),
        ) );

        return 'completed';
    }

    private function sanitize_body_for_telemetry( array $body ): array {
        if ( isset( $body['access_token'] ) ) {
            $body['access_token'] = '***';
        }
        if ( isset( $body['access_token_page'] ) ) {
            $body['access_token_page'] = '***';
        }
        return $body;
    }

    private function collect_rate_limits( $response ): array {
        $headers = array(
            'x-business-use-case-usage' => wp_remote_retrieve_header( $response, 'x-business-use-case-usage' ),
            'x-app-usage'               => wp_remote_retrieve_header( $response, 'x-app-usage' ),
            'x-ratelimit-remaining'     => wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ),
        );

        return array_filter( $headers );
    }

    private function extract_metrics( array $response, array $payload, array $context, $schedule_id ): array {
        $metrics = array();

        if ( isset( $response['insights']['data'] ) && is_array( $response['insights']['data'] ) ) {
            foreach ( $response['insights']['data'] as $insight ) {
                if ( empty( $insight['name'] ) || empty( $insight['values'][0]['value'] ) ) {
                    continue;
                }
                $name  = strtolower( $insight['name'] );
                $value = $insight['values'][0]['value'];
                if ( in_array( $name, array( 'post_impressions', 'post_clicks', 'post_engaged_users' ), true ) && is_numeric( $value ) ) {
                    $key = 'post_impressions' === $name ? 'reach' : ( 'post_clicks' === $name ? 'clicks' : 'engagement' );
                    $metrics[ $key ] = (int) $value;
                }
            }
        }

        if ( isset( $response['metrics'] ) && is_array( $response['metrics'] ) ) {
            foreach ( $response['metrics'] as $metric_key => $metric_value ) {
                if ( is_numeric( $metric_value ) ) {
                    $metrics[ $metric_key ] = (int) $metric_value;
                }
            }
        }

        $metrics = apply_filters( 'kh_smma_meta_metrics', $metrics, $response, $payload, $context, $schedule_id );

        return $metrics;
    }
}
