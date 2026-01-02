<?php
namespace KH_SMMA\Services;

use function add_filter;
use function add_query_arg;
use function is_wp_error;
use function wp_remote_get;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_body;
use function json_decode;
use function is_array;
use function is_numeric;
use function esc_url_raw;
use function rawurlencode;

class EngagementMetricsService {
    public function register() {
        add_filter( 'kh_smma_meta_metrics', array( $this, 'fetch_meta_metrics' ), 10, 5 );
        add_filter( 'kh_smma_linkedin_metrics', array( $this, 'fetch_linkedin_metrics' ), 10, 4 );
        add_filter( 'kh_smma_twitter_metrics', array( $this, 'fetch_twitter_metrics' ), 10, 4 );
    }

    public function fetch_meta_metrics( $metrics, $response, $payload, $context, $schedule_id ) {
        if ( ! empty( $metrics ) ) {
            return $metrics;
        }

        $post_id = isset( $response['id'] ) ? $response['id'] : '';
        $token   = $context['token'] ?? array();
        $access_token = $token['page_access_token'] ?? ( $token['access_token'] ?? '' );

        if ( empty( $post_id ) || empty( $access_token ) ) {
            return $metrics;
        }

        $endpoint = esc_url_raw( sprintf( 'https://graph.facebook.com/v18.0/%s/insights', $post_id ) );
        $endpoint = add_query_arg( array(
            'metric'       => 'post_impressions,post_clicks,post_engaged_users',
            'access_token' => $access_token,
        ), $endpoint );

        $response = wp_remote_get( $endpoint, array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) ) {
            return $metrics;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return $metrics;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
            return $metrics;
        }

        foreach ( $body['data'] as $insight ) {
            if ( empty( $insight['name'] ) || empty( $insight['values'][0]['value'] ) ) {
                continue;
            }
            $value = $insight['values'][0]['value'];
            if ( ! is_numeric( $value ) ) {
                continue;
            }
            switch ( $insight['name'] ) {
                case 'post_impressions':
                    $metrics['reach'] = (int) $value;
                    break;
                case 'post_clicks':
                    $metrics['clicks'] = (int) $value;
                    break;
                case 'post_engaged_users':
                    $metrics['engagement'] = (int) $value;
                    break;
            }
        }

        return $metrics;
    }

    public function fetch_linkedin_metrics( $metrics, $response, $context, $schedule_id ) {
        if ( ! empty( $metrics ) ) {
            return $metrics;
        }

        $post_id = isset( $response['id'] ) ? $response['id'] : '';
        $token   = $context['token']['access_token'] ?? '';

        if ( empty( $post_id ) || empty( $token ) ) {
            return $metrics;
        }

        $endpoint = sprintf( 'https://api.linkedin.com/v2/ugcPosts/%s?projection=(id,created,time,ugcShareSocialDetail)' , rawurlencode( $post_id ) );
        $response = wp_remote_get( $endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return $metrics;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return $metrics;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $social_detail = $body['ugcShareSocialDetail'] ?? array();
        $share_stats   = $social_detail['shareStatistics'] ?? array();
        foreach ( array( 'impressionCount' => 'reach', 'clickCount' => 'clicks', 'likeCount' => 'likes', 'commentCount' => 'comments' ) as $source => $target ) {
            if ( isset( $share_stats[ $source ] ) && is_numeric( $share_stats[ $source ] ) ) {
                $metrics[ $target ] = (int) $share_stats[ $source ];
            }
        }

        return $metrics;
    }

    public function fetch_twitter_metrics( $metrics, $response, $context, $schedule_id ) {
        if ( ! empty( $metrics ) ) {
            return $metrics;
        }

        $tweet_id = $response['data']['id'] ?? ( $response['id'] ?? '' );
        $token    = $context['token']['bearer_token'] ?? '';

        if ( empty( $tweet_id ) || empty( $token ) ) {
            return $metrics;
        }

        $endpoint = sprintf( 'https://api.twitter.com/2/tweets/%s?tweet.fields=public_metrics', rawurlencode( $tweet_id ) );
        $response = wp_remote_get( $endpoint, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $metrics;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return $metrics;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $public = $body['data']['public_metrics'] ?? array();
        foreach ( array( 'impression_count' => 'reach', 'retweet_count' => 'retweets', 'reply_count' => 'replies', 'like_count' => 'likes' ) as $source => $target ) {
            if ( isset( $public[ $source ] ) && is_numeric( $public[ $source ] ) ) {
                $metrics[ $target ] = (int) $public[ $source ];
            }
        }

        return $metrics;
    }
}
