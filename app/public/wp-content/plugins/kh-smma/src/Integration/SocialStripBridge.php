<?php
namespace KH_SMMA\Integration;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Listens for Social Strip share events so we can mirror them inside KH-SMMA
 * and feed the analytics dashboard / telemetry stream.
 */
class SocialStripBridge {

    const OPTION_ACCOUNTS = 'kh_smma_social_strip_accounts';

    public function register() {
        add_action( 'kss_share_tracked', array( $this, 'ingest_share_event' ), 10, 1 );
    }

    /**
     * Convert a Social Strip share into a completed schedule so analytics stay unified.
     *
     * @param array $event
     */
    public function ingest_share_event( $event ) {
        if ( empty( $event ) || ! is_array( $event ) ) {
            return;
        }

        $platform  = sanitize_key( $event['platform'] ?? '' );
        $share_url = esc_url_raw( $event['share_url'] ?? ( $event['url'] ?? '' ) );

        if ( empty( $platform ) || empty( $share_url ) ) {
            return;
        }

        $hash = md5( implode( '|', array(
            $platform,
            $share_url,
            $event['timestamp'] ?? microtime( true ),
        ) ) );

        if ( $this->schedule_exists( $hash ) ) {
            return;
        }

        $account_id = $this->ensure_account( $platform );
        if ( ! $account_id ) {
            return;
        }

        $content    = sanitize_text_field( $event['content'] ?? '' );
        $timestamp  = ! empty( $event['timestamp'] ) ? strtotime( $event['timestamp'] ) : false;
        $created_at = $timestamp && $timestamp > 0 ? $timestamp : time();
        $hashtags   = array();

        if ( ! empty( $event['hashtags'] ) && is_array( $event['hashtags'] ) ) {
            foreach ( $event['hashtags'] as $tag ) {
                $tag = sanitize_text_field( $tag );
                if ( $tag !== '' ) {
                    $hashtags[] = $tag;
                }
            }
        }

        $title = sprintf(
            /* translators: %s is the platform name */
            __( 'Social Strip Share – %s', 'kh-smma' ),
            ucfirst( $platform )
        );

        $schedule_id = wp_insert_post( array(
            'post_type'    => 'kh_smma_schedule',
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
        ), true );

        if ( is_wp_error( $schedule_id ) ) {
            return;
        }

        $telemetry = array(
            'timestamp'    => $created_at,
            'mode'         => 'manual',
            'provider'     => $platform,
            'note'         => __( 'Shared via Social Strip modal', 'kh-smma' ),
            'share_url'    => $share_url,
            'hashtags'     => $hashtags,
            'char_count'   => absint( $event['char_count'] ?? strlen( $content ) ),
            'source'       => $event['source'] ?? 'social_strip_modal',
            'meta'         => $event['meta'] ?? array(),
            'user_id'      => absint( $event['user_id'] ?? $event['user'] ?? 0 ),
            'ip'           => sanitize_text_field( $event['ip'] ?? '' ),
            'user_agent'   => sanitize_text_field( $event['user_agent'] ?? '' ),
        );

        update_post_meta( $schedule_id, '_kh_smma_account_id', $account_id );
        update_post_meta( $schedule_id, '_kh_smma_payload', array(
            'message'   => $content,
            'share_url' => $share_url,
            'hashtags'  => $hashtags,
        ) );
        update_post_meta( $schedule_id, '_kh_smma_scheduled_at', $created_at );
        update_post_meta( $schedule_id, '_kh_smma_delivery_mode', 'manual_export' );
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'completed' );
        update_post_meta( $schedule_id, '_kh_smma_share_hash', $hash );
        update_post_meta( $schedule_id, '_kh_smma_last_telemetry', $telemetry );
        update_post_meta( $schedule_id, '_kh_smma_result_metrics', array(
            'note'    => __( 'Recorded via Social Strip', 'kh-smma' ),
            'queued_at' => $created_at,
            'metrics' => array(
                'characters' => $telemetry['char_count'],
            ),
        ) );

        do_action( 'kh_smma_schedule_status_changed', $schedule_id, 'completed' );
    }

    /**
     * Ensure we have a placeholder account for a given platform.
     *
     * @param string $platform
     *
     * @return int
     */
    private function ensure_account( string $platform ): int {
        $accounts = get_option( self::OPTION_ACCOUNTS, array() );

        if ( isset( $accounts[ $platform ] ) && get_post( $accounts[ $platform ] ) ) {
            return (int) $accounts[ $platform ];
        }

        $account_id = wp_insert_post( array(
            'post_type'   => 'kh_smma_account',
            'post_status' => 'publish',
            'post_title'  => sprintf(
                /* translators: %s is a platform name */
                __( 'Social Strip – %s', 'kh-smma' ),
                ucfirst( $platform )
            ),
        ), true );

        if ( is_wp_error( $account_id ) ) {
            return 0;
        }

        update_post_meta( $account_id, '_kh_smma_provider', $platform );
        update_post_meta( $account_id, '_kh_smma_status', 'connected' );
        update_post_meta( $account_id, '_kh_smma_sandbox_mode', true );

        $accounts[ $platform ] = (int) $account_id;
        update_option( self::OPTION_ACCOUNTS, $accounts );

        return (int) $account_id;
    }

    /**
     * Determine if we've already ingested a share event.
     *
     * @param string $hash
     *
     * @return bool
     */
    private function schedule_exists( string $hash ): bool {
        $existing = get_posts( array(
            'post_type'      => 'kh_smma_schedule',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_kh_smma_share_hash',
            'meta_value'     => $hash,
        ) );

        return ! empty( $existing );
    }
}
