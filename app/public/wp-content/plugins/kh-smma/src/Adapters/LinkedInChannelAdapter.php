<?php
namespace KH_SMMA\Adapters;

use KH_SMMA\Services\TokenRepository;
use KH_SMMA\Services\ScheduleQueueProcessor;
use WP_Error;

use function __;
use function add_filter;
use function apply_filters;
use function time;
use function update_post_meta;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_header;
use function wp_json_encode;
use function json_decode;
use function is_array;
use function is_wp_error;
use function array_filter;
use function is_numeric;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LinkedInChannelAdapter {
    /** @var TokenRepository */
    private $tokens;

    public function __construct( TokenRepository $tokens ) {
        $this->tokens = $tokens;
    }

    public function register() {
        add_filter( 'kh_smma_dispatch_schedule', array( $this, 'handle_linkedin_dispatch' ), 25, 4 );
    }

    public function handle_linkedin_dispatch( $result, $schedule_id, $payload, $context ) {
        if ( empty( $context['provider'] ) || 'linkedin' !== $context['provider'] ) {
            return $result;
        }

        $token = $context['token'];
        if ( empty( $token['access_token'] ) || empty( $token['author'] ) ) {
            return new WP_Error( 'kh_smma_linkedin_missing_token', __( 'LinkedIn account missing token or author URN.', 'kh-smma' ) );
        }

        $endpoint = 'https://api.linkedin.com/v2/ugcPosts';
        $asset    = isset( $payload['asset'] ) && is_array( $payload['asset'] ) ? $payload['asset'] : array();

        $share_content = array(
            'shareCommentary'    => array( 'text' => $payload['message'] ?? '' ),
            'shareMediaCategory' => 'NONE',
        );

        if ( ! empty( $asset['link'] ) ) {
            $share_content['shareMediaCategory'] = 'ARTICLE';
            $share_content['media'] = array(
                array(
                    'status'        => 'READY',
                    'originalUrl'   => $asset['link'],
                    'title'         => array( 'text' => $asset['title'] ?? '' ),
                    'description'   => array( 'text' => $asset['description'] ?? '' ),
                ),
            );
        }

        $post_body = array(
            'author'          => $token['author'],
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => array(
                'com.linkedin.ugc.ShareContent' => $share_content,
            ),
            'visibility' => array(
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ),
        );
        $log_body = $post_body;

        $response = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token['access_token'],
                'Content-Type'  => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ),
            'body'    => wp_json_encode( $post_body ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
                'mode'     => 'live',
                'provider' => 'linkedin',
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
                'provider'      => 'linkedin',
                'response_code' => $code,
                'response'      => $error_body,
                'error'         => __( 'LinkedIn API responded with an error.', 'kh-smma' ),
                'request'       => array(
                    'endpoint' => $endpoint,
                    'body'     => $log_body,
                ),
                'rate_limits'   => $this->collect_rate_limits( $response ),
            ) );
            return new WP_Error( 'kh_smma_linkedin_http_error', __( 'LinkedIn API responded with an error.', 'kh-smma' ), $error_body );
        }

        $data    = json_decode( wp_remote_retrieve_body( $response ), true );
        $metrics = $this->extract_metrics( $data, $context, $schedule_id );

        update_post_meta( $schedule_id, '_kh_smma_result_metrics', array(
            'note'      => 'LinkedIn share published',
            'queued_at' => time(),
            'response'  => $data,
            'metrics'   => $metrics,
        ) );

        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode'          => 'live',
            'provider'      => 'linkedin',
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
            'x-ratelimit-remaining' => wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ),
            'x-ratelimit-limit'     => wp_remote_retrieve_header( $response, 'x-ratelimit-limit' ),
            'x-ratelimit-reset'     => wp_remote_retrieve_header( $response, 'x-ratelimit-reset' ),
        );

        return array_filter( $headers );
    }

    private function extract_metrics( array $response, array $context, $schedule_id ): array {
        $metrics = array();

        if ( isset( $response['metrics'] ) && is_array( $response['metrics'] ) ) {
            foreach ( $response['metrics'] as $metric_key => $metric_value ) {
                if ( is_numeric( $metric_value ) ) {
                    $metrics[ $metric_key ] = (int) $metric_value;
                }
            }
        }

        $metrics = apply_filters( 'kh_smma_linkedin_metrics', $metrics, $response, $context, $schedule_id );

        return $metrics;
    }
}
