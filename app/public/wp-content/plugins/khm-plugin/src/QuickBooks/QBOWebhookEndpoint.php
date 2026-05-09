<?php
/**
 * QuickBooks Online Webhook Handler
 *
 * Listens on: POST /khm/v1/qbo/webhook
 *
 * Intuit sends webhook notifications when QB entities change.
 * We handle `Invoice` events: when Balance drops to 0 (fully paid),
 * we activate the sponsor's site connection subscription.
 *
 * Security: Intuit signs requests with a verifier token.
 * Store it in option `khm_qbo_webhook_verifier_token` (set from Intuit Developer Portal).
 *
 * @see https://developer.intuit.com/app/developer/qbo/docs/develop/webhooks
 * @package KHM\QuickBooks
 */

namespace KHM\QuickBooks;

use KHM\Connect\ConnectSponsorProviderEndpoint;
use KHM\Connect\ConnectSubscriptionEndpoint;

defined( 'ABSPATH' ) || exit;

class QBOWebhookEndpoint {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	public function register_route(): void {
		register_rest_route( 'khm/v1', '/qbo/webhook', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'handle' ],
			'permission_callback' => '__return_true',
		] );
	}

	// ─── Main handler ─────────────────────────────────────────────────────────

	public function handle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// 1. Verify Intuit signature.
		if ( ! $this->verify_signature( $request ) ) {
			error_log( '[KHM QBO Webhook] Signature verification failed.' );
			return new \WP_Error( 'qbo_invalid_signature', 'Unauthorized', [ 'status' => 401 ] );
		}

		$body = $request->get_json_params();

		if ( empty( $body['eventNotifications'] ) || ! is_array( $body['eventNotifications'] ) ) {
			return new \WP_REST_Response( [ 'ok' => true ] ); // Intuit expects 200 even for unknown payloads.
		}

		foreach ( $body['eventNotifications'] as $notification ) {
			$realm_id = sanitize_text_field( (string) ( $notification['realmId'] ?? '' ) );
			$stored_realm = (string) get_option( 'khm_qbo_realm_id', '' );

			if ( $realm_id !== $stored_realm ) {
				continue; // Not our company — skip.
			}

			foreach ( $notification['dataChangeEvent']['entities'] ?? [] as $entity ) {
				$entity_name = sanitize_text_field( (string) ( $entity['name'] ?? '' ) );
				$entity_id   = sanitize_text_field( (string) ( $entity['id'] ?? '' ) );
				$operation   = sanitize_text_field( (string) ( $entity['operation'] ?? '' ) );

				if ( 'Invoice' !== $entity_name ) {
					continue;
				}

				if ( in_array( $operation, [ 'Update', 'Create' ], true ) ) {
					$this->process_invoice_event( $entity_id );
				}
			}
		}

		return new \WP_REST_Response( [ 'ok' => true ] );
	}

	// ─── Invoice processing ───────────────────────────────────────────────────

	private function process_invoice_event( string $invoice_id ): void {
		if ( ! $invoice_id ) {
			return;
		}

		try {
			$service = new QBOService();
			if ( ! $service->is_connected() ) {
				error_log( '[KHM QBO Webhook] Not connected — cannot fetch invoice ' . $invoice_id );
				return;
			}

			$invoice = $service->get_invoice( $invoice_id );
			if ( ! $invoice ) {
				return;
			}

			$balance = (float) ( $invoice->Balance ?? 1 );
			if ( $balance > 0 ) {
				return; // Not yet fully paid.
			}

			// Invoice is paid — look up which user has this QB invoice ID.
			$this->activate_subscription_for_invoice( $invoice_id, $invoice );

		} catch ( \Throwable $e ) {
			error_log( '[KHM QBO Webhook] process_invoice_event error: ' . $e->getMessage() );
		}
	}

	/**
	 * Find the user whose pending subscription references this QB invoice and activate it.
	 */
	private function activate_subscription_for_invoice( string $invoice_id, object $invoice ): void {
		global $wpdb;

		// We store qbo_invoice_id in user meta `khm_connect_subscription`.
		// Query all users who have a pending subscription tied to this invoice.
		$user_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta}
			 WHERE meta_key = 'khm_connect_subscription'
			 AND meta_value LIKE %s",
			'%' . $wpdb->esc_like( '"qbo_invoice_id":"' . $invoice_id . '"' ) . '%'
		) );

		if ( empty( $user_ids ) ) {
			error_log( '[KHM QBO Webhook] No subscription found for QB invoice ' . $invoice_id );
			return;
		}

		foreach ( $user_ids as $user_id ) {
			$user_id      = (int) $user_id;
			$subscription = get_user_meta( $user_id, 'khm_connect_subscription', true );

			if ( ! is_array( $subscription ) ) {
				continue;
			}

			if ( ( $subscription['status'] ?? '' ) === 'active' ) {
				continue; // Already activated.
			}

			// Activate the subscription.
			$subscription['status']           = 'active';
			$subscription['activated_at']     = gmdate( 'Y-m-d H:i:s' );
			$subscription['qbo_paid_invoice'] = $invoice_id;

			update_user_meta( $user_id, 'khm_connect_subscription', $subscription );

			// Fire activation hook so any downstream processes can run (email, access grants, etc.).
			do_action( 'khm_connect_subscription_activated', $user_id, $subscription );

			error_log( sprintf(
				'[KHM QBO] Subscription activated for user %d via QB invoice %s',
				$user_id,
				$invoice_id
			) );
		}

		// Also search khm_connect_site_subscriptions for per-site QB invoice entries.
		$site_sub_user_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta}
			 WHERE meta_key = 'khm_connect_site_subscriptions'
			 AND meta_value LIKE %s",
			'%' . $wpdb->esc_like( '"qbo_invoice_id":"' . $invoice_id . '"' ) . '%'
		) );

		foreach ( $site_sub_user_ids as $user_id ) {
			$user_id   = (int) $user_id;
			$site_subs = get_user_meta( $user_id, 'khm_connect_site_subscriptions', true );
			if ( ! is_array( $site_subs ) ) {
				continue;
			}

			$sites_to_activate = [];
			foreach ( $site_subs as $slug => $sub ) {
				if ( ( $sub['qbo_invoice_id'] ?? '' ) === $invoice_id && ( $sub['status'] ?? '' ) !== 'active' ) {
					$sites_to_activate[] = $slug;
				}
			}

			if ( empty( $sites_to_activate ) ) {
				continue;
			}

			\KHM\Connect\ConnectSubscriptionEndpoint::activate_site_subscriptions(
				$user_id, $sites_to_activate, 'site', '', ''
			);

			error_log( sprintf(
				'[KHM QBO] Per-site subscriptions activated for user %d (sites: %s) via QB invoice %s',
				$user_id,
				implode( ', ', $sites_to_activate ),
				$invoice_id
			) );
		}
	}

	// ─── Signature verification ───────────────────────────────────────────────

	/**
	 * Verify the Intuit webhook signature.
	 *
	 * Intuit computes: Base64( HMAC-SHA256( payload_bytes, verifier_token ) )
	 * and sends it in the `intuit-signature` header.
	 *
	 * @see https://developer.intuit.com/app/developer/qbo/docs/develop/webhooks/managing-webhooks-notifications#validating-the-notification
	 */
	private function verify_signature( \WP_REST_Request $request ): bool {
		$verifier_token = (string) get_option( 'khm_qbo_webhook_verifier_token', '' );

		if ( ! $verifier_token ) {
			// Token not configured yet — allow through but log a warning.
			error_log( '[KHM QBO Webhook] khm_qbo_webhook_verifier_token not configured; skipping signature check.' );
			return true;
		}

		$signature_header = $_SERVER['HTTP_INTUIT_SIGNATURE'] ?? '';
		if ( ! $signature_header ) {
			return false;
		}

		$payload  = $request->get_body();
		$expected = base64_encode( hash_hmac( 'sha256', $payload, $verifier_token, true ) );

		return hash_equals( $expected, $signature_header );
	}
}
