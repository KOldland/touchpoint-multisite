<?php
namespace KH_SMMA\Services;

use WP_Error;

use function absint;
use function add_action;
use function apply_filters;
use function array_filter;
use function current_time;
use function get_current_user_id;
use function get_post;
use function get_post_meta;
use function in_array;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function strtotime;
use function time;
use function update_post_meta;
use function wp_insert_post;
use function wp_parse_args;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * High level orchestration service for enqueueing and managing social publishing jobs.
 *
 * The service creates `kh_smma_schedule` posts, ensures approval / OAuth constraints are met,
 * and exposes hooks so other KH plugins can enqueue jobs without duplicating logic.
 */
class SocialPublishingService {
    private TokenRepository $tokens;
    private AuditLogger $logger;

    public function __construct( TokenRepository $tokens, AuditLogger $logger ) {
        $this->tokens = $tokens;
        $this->logger = $logger;
    }

    public function register(): void {
        add_action( 'kh_smma_queue_social_post', array( $this, 'handle_external_enqueue' ) );
    }

    /**
     * Allow other systems to enqueue posts by firing an action with payload args.
     *
     * @param array $args
     */
    public function handle_external_enqueue( $args ): void {
        if ( empty( $args ) || ! is_array( $args ) ) {
            return;
        }

        $result = $this->enqueue_post( $args );

        /**
         * Fire a feedback hook so callers can react to enqueue errors.
         */
        do_action( 'kh_smma_external_enqueue_result', $args, $result );
    }

    /**
     * Enqueue a social publishing job.
     *
     * @param array $args
     *
     * @return int|WP_Error
     */
    public function enqueue_post( array $args ) {
        $defaults = array(
            'title'        => '',
            'message'      => '',
            'account_id'   => 0,
            'campaign_id'  => 0,
            'delivery'     => 'auto',
            'scheduled_at' => '',
            'asset'        => array(),
            'source'       => 'external',
            'author_id'    => get_current_user_id(),
            'batch_id'     => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $account_id  = absint( $args['account_id'] );
        $campaign_id = absint( $args['campaign_id'] );
        $author_id   = absint( $args['author_id'] );

        if ( ! $account_id || ! get_post( $account_id ) ) {
            return new WP_Error( 'kh_smma_missing_account', __( 'A connected account is required.', 'kh-smma' ) );
        }

        $message = sanitize_textarea_field( $args['message'] );
        if ( strlen( $message ) < 5 ) {
            return new WP_Error( 'kh_smma_short_message', __( 'Message body must contain at least 5 characters.', 'kh-smma' ) );
        }

        $title = sanitize_text_field( $args['title'] );
        if ( '' === $title ) {
            $title = sprintf(
                /* translators: %s is the account label */
                __( 'Queued Social Post – %s', 'kh-smma' ),
                get_the_title( $account_id )
            );
        }

        $delivery = sanitize_key( $args['delivery'] );
        if ( ! in_array( $delivery, array( 'auto', 'manual_export' ), true ) ) {
            $delivery = 'auto';
        }

        $scheduled_at = $this->normalize_timestamp( $args['scheduled_at'] );
        $provider     = sanitize_key( get_post_meta( $account_id, '_kh_smma_provider', true ) );

        if ( 'auto' === $delivery && $this->provider_requires_oauth( $provider ) && ! $this->account_has_token( $account_id ) ) {
            return new WP_Error( 'kh_smma_missing_token', __( 'Account must be connected via OAuth before auto publishing.', 'kh-smma' ) );
        }

        $payload = array(
            'message' => $message,
        );

        $asset = is_array( $args['asset'] ) ? $args['asset'] : array();
        $payload['asset'] = $this->normalize_asset( $asset );

        $post_id = wp_insert_post( array(
            'post_type'    => 'kh_smma_schedule',
            'post_title'   => $title,
            'post_content' => $message,
            'post_status'  => 'publish',
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $requires_approval = (bool) get_post_meta( $account_id, '_kh_smma_require_approval', true );
        $status            = $requires_approval ? 'pending_approval' : 'pending';

        update_post_meta( $post_id, '_kh_smma_account_id', $account_id );
        update_post_meta( $post_id, '_kh_smma_campaign_id', $campaign_id );
        update_post_meta( $post_id, '_kh_smma_payload', array_filter( $payload ) );
        update_post_meta( $post_id, '_kh_smma_scheduled_at', $scheduled_at );
        update_post_meta( $post_id, '_kh_smma_delivery_mode', $delivery );
        update_post_meta( $post_id, '_kh_smma_schedule_status', $status );
        update_post_meta( $post_id, '_kh_smma_approval_status', $requires_approval ? 'requested' : 'auto_approved' );
        update_post_meta( $post_id, '_kh_smma_entry_source', sanitize_key( $args['source'] ) );
        update_post_meta( $post_id, '_kh_smma_created_by', $author_id ?: get_current_user_id() );
        if ( $args['batch_id'] ) {
            update_post_meta( $post_id, '_kh_smma_batch_id', sanitize_text_field( $args['batch_id'] ) );
        }

        if ( ! $requires_approval ) {
            update_post_meta( $post_id, '_kh_smma_approved_by', $author_id ?: get_current_user_id() );
            update_post_meta( $post_id, '_kh_smma_approved_at', time() );
            update_post_meta( $post_id, '_kh_smma_approval_note', '' );
        }

        $this->logger->log( 'schedule_created', array(
            'object_type' => 'schedule',
            'object_id'   => $post_id,
            'details'     => array(
                'account_id'  => $account_id,
                'campaign_id' => $campaign_id,
                'delivery'    => $delivery,
                'scheduled_at'=> $scheduled_at,
                'requires_approval' => $requires_approval,
                'source'      => $args['source'],
            ),
            'user_id' => $author_id ?: get_current_user_id(),
        ) );

        do_action( 'kh_smma_social_post_enqueued', $post_id, $args );

        return $post_id;
    }

    /**
     * Enqueue the same payload for multiple accounts.
     *
     * @param array $account_ids
     * @param array $args
     *
     * @return array Summary with queued IDs and failures.
     */
    public function enqueue_for_accounts( array $account_ids, array $args ): array {
        $summary = array(
            'queued'   => 0,
            'ids'      => array(),
            'failures' => array(),
        );

        $account_ids = array_filter( array_map( 'absint', $account_ids ) );

        if ( count( $account_ids ) > 50 ) {
            $summary['failures'][] = array(
                'account_id' => 0,
                'code'       => 'kh_smma_batch_limit',
                'message'    => __( 'Batch enqueue limited to 50 accounts per request to respect provider rate limits.', 'kh-smma' ),
            );
            $account_ids = array_slice( $account_ids, 0, 50 );
        }

        foreach ( $account_ids as $account_id ) {
            $result = $this->enqueue_post( wp_parse_args( array(
                'account_id' => $account_id,
            ), $args ) );

            if ( is_wp_error( $result ) ) {
                $summary['failures'][] = array(
                    'account_id' => $account_id,
                    'code'       => $result->get_error_code(),
                    'message'    => $result->get_error_message(),
                );
                continue;
            }

            $summary['queued']++;
            $summary['ids'][] = $result;
        }

        return $summary;
    }

    private function account_has_token( int $account_id ): bool {
        $token_id = absint( get_post_meta( $account_id, '_kh_smma_token_id', true ) );
        if ( ! $token_id ) {
            return false;
        }

        $token = $this->tokens->get_token( $token_id );
        return ! empty( $token );
    }

    private function provider_requires_oauth( string $provider ): bool {
        $providers = apply_filters( 'kh_smma_oauth_required_providers', array( 'linkedin', 'meta', 'twitter' ) );
        return in_array( $provider, $providers, true );
    }

    private function normalize_timestamp( $value ): int {
        if ( is_numeric( $value ) ) {
            $timestamp = (int) $value;
        } elseif ( is_string( $value ) && '' !== $value ) {
            $timestamp = strtotime( $value );
        } else {
            $timestamp = 0;
        }

        if ( ! $timestamp || $timestamp < time() ) {
            $timestamp = time();
        }

        return $timestamp;
    }

    private function normalize_asset( array $asset ): array {
        $normalized = array();

        if ( ! empty( $asset['link'] ) ) {
            $normalized['link'] = esc_url_raw( $asset['link'] );
        }
        if ( ! empty( $asset['title'] ) ) {
            $normalized['title'] = sanitize_text_field( $asset['title'] );
        }
        if ( ! empty( $asset['description'] ) ) {
            $normalized['description'] = sanitize_textarea_field( $asset['description'] );
        }
        if ( ! empty( $asset['message'] ) ) {
            $normalized['message'] = sanitize_textarea_field( $asset['message'] );
        }

        return $normalized;
    }
}
