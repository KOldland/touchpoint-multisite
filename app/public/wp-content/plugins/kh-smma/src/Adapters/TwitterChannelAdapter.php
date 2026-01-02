<?php
namespace KH_SMMA\Adapters;

use KH_SMMA\Services\TokenRepository;
use KH_SMMA\Services\ScheduleQueueProcessor;
use WP_Error;

use function __;
use function add_filter;
use function apply_filters;
use function in_array;
use function time;
use function update_post_meta;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_header;
use function is_wp_error;
use function wp_json_encode;
use function json_decode;
use function is_array;
use function array_filter;
use function is_numeric;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TwitterChannelAdapter {
    /** @var TokenRepository */
    private $tokens;

    public function __construct( TokenRepository $tokens ) {
        $this->tokens = $tokens;
    }

    public function register() {
        add_filter( 'kh_smma_dispatch_schedule', array( $this, 'handle_twitter_dispatch' ), 25, 4 );
    }

    public function handle_twitter_dispatch( $result, $schedule_id, $payload, $context ) {
        if ( empty( $context['provider'] ) || ! in_array( $context['provider'], array( 'twitter', 'x' ), true ) ) {
            return $result;
        }

        $token = $context['token'];
        if ( empty( $token['bearer_token'] ) ) {
            return new WP_Error( 'kh_smma_twitter_missing_token', __( 'X/Twitter account missing bearer token.', 'kh-smma' ) );
        }

        $endpoint = 'https://api.twitter.com/2/tweets';
        $message  = $payload['message'] ?? '';
        $asset    = isset( $payload['asset'] ) && is_array( $payload['asset'] ) ? $payload['asset'] : array();

        if ( ! empty( $asset['link'] ) && strpos( $message, $asset['link'] ) === false ) {
            $message .= "\n\n" . $asset['link'];
        }

        $body = array(
            'text' => trim( $message ),
        );

        $body = apply_filters( 'kh_smma_twitter_body', $body, $payload, $context );
        $log_body = $body;

        $response = $this->request_with_retry( $endpoint, $body, $token['bearer_token'] );

        if ( is_wp_error( $response ) ) {
            ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
                'mode'     => 'live',
                'provider' => 'twitter',
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
            $trimmed    = $this->truncate_body( $error_body );
            ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
                'mode'          => 'live',
                'provider'      => 'twitter',
                'response_code' => $code,
                'response'      => $trimmed,
                'error'         => __( 'Twitter API responded with an error.', 'kh-smma' ),
                'request'       => array(
                    'endpoint' => $endpoint,
                    'body'     => $log_body,
                ),
                'rate_limits'   => $this->collect_rate_limits( $response ),
            ) );
            return new WP_Error( 'kh_smma_twitter_http_error', __( 'Twitter API responded with an error.', 'kh-smma' ), $trimmed );
        }

        $data    = json_decode( wp_remote_retrieve_body( $response ), true );
        $metrics = $this->extract_metrics( $data, $context, $schedule_id );

        update_post_meta( $schedule_id, '_kh_smma_result_metrics', array(
            'note'      => 'X/Twitter post published',
            'queued_at' => time(),
            'response'  => $data,
            'metrics'   => $metrics,
        ) );

        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode'          => 'live',
            'provider'      => 'twitter',
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

    private function collect_rate_limits( $response ): array {
        $headers = array(
            'x-rate-limit-remaining' => wp_remote_retrieve_header( $response, 'x-rate-limit-remaining' ),
            'x-rate-limit-limit'     => wp_remote_retrieve_header( $response, 'x-rate-limit-limit' ),
            'x-rate-limit-reset'     => wp_remote_retrieve_header( $response, 'x-rate-limit-reset' ),
        );

        return array_filter( $headers );
    }

    private function extract_metrics( array $response, array $context, $schedule_id ): array {
        $metrics = array();

        if ( isset( $response['data']['public_metrics'] ) && is_array( $response['data']['public_metrics'] ) ) {
            foreach ( $response['data']['public_metrics'] as $metric_key => $metric_value ) {
                if ( is_numeric( $metric_value ) ) {
                    $metrics[ $metric_key ] = (int) $metric_value;
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

        $metrics = apply_filters( 'kh_smma_twitter_metrics', $metrics, $response, $context, $schedule_id );

        return $metrics;
    }

    /**
     * Retry wrapper for Twitter API to handle 429/5xx with small backoff.
     */
    private function request_with_retry( $endpoint, $body, $bearer, $attempts = 2 ) {
        $payload = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        );

        for ( $i = 0; $i <= $attempts; $i++ ) {
            $response = wp_remote_post( $endpoint, $payload );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( $code === 429 || ( $code >= 500 && $code < 600 ) ) {
                if ( $i === $attempts ) {
                    return $response;
                }

                $retry_after = intval( wp_remote_retrieve_header( $response, 'retry-after' ) );
                if ( $retry_after <= 0 ) {
                    $retry_after = pow( 2, $i + 1 );
                }
                sleep( min( $retry_after, 30 ) );
                continue;
            }

            return $response;
        }

        return $response;
    }

    private function truncate_body( $body ) {
        if ( ! is_string( $body ) ) {
            return $body;
        }
        return ( strlen( $body ) > 800 ) ? substr( $body, 0, 800 ) . '…' : $body;
    }
}
