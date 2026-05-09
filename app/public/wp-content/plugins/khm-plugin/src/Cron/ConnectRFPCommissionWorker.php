<?php
/**
 * RFP Commission Charge Worker
 *
 * Daily WP-Cron job that processes due commission invoices.
 *
 * Flow:
 *  1. Find connect_pending_commission_invoices rows where:
 *       status = 'pending'  AND  auto_debit_date <= NOW()
 *  2. For each: resolve seller -> stripe_customer_id -> default payment method.
 *  3. Execute off-session Stripe PaymentIntent for commission_amount (GBP pence).
 *  4. On success: status -> 'charged', stamps stripe_payment_intent_id + settled_at.
 *  5. On failure: status -> 'failed', stamps failure reason into dispute_reason.
 *  6. Disputed invoices are never touched by this job -- admin resolves manually.
 *
 * Fires:
 *   do_action( 'khm_rfp_commission_charged', $invoice_id, $thread_id, $sponsor_id, $intent_id, $commission_amount )
 *   do_action( 'khm_rfp_commission_failed',  $invoice_id, $sponsor_id, $reason )
 *
 * @package KHM\Cron
 */

namespace KHM\Cron;

use KHM\Connect\ConnectSellerPaymentRepository;
use KHM\Migrations\CreateRFPSupportTables;

defined( 'ABSPATH' ) || exit;

class ConnectRFPCommissionWorker {

	public const HOOK       = 'khm_rfp_commission_daily';
	public const CHUNK_SIZE = 25;

	private ConnectSellerPaymentRepository $payment_repo;

	public function __construct( ?ConnectSellerPaymentRepository $payment_repo = null ) {
		$this->payment_repo = $payment_repo ?? new ConnectSellerPaymentRepository();
	}

	public function register(): void {
		add_action( 'init',     [ $this, 'schedule' ] );
		add_action( self::HOOK, [ $this, 'run' ] );
	}

	public function schedule(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// 03:00 AM to avoid conflict with the existing RFP upsell cron at 02:00.
			wp_schedule_event( strtotime( 'tomorrow 03:00:00' ), 'daily', self::HOOK );
		}
	}

	/**
	 * Main cron callback. Processes all overdue pending commission invoices.
	 *
	 * @return array{ charged: int, failed: int, skipped: int }
	 */
	public function run(): array {
		global $wpdb;

		$invoices_table = CreateRFPSupportTables::invoices_table_name();
		$threads_table  = $wpdb->prefix . 'connect_intro_threads';

		// Join threads so we can get sponsor_id without a second query per row.
		$due_invoices = $wpdb->get_results( $wpdb->prepare(
			"SELECT inv.*, t.sponsor_id
			 FROM `{$invoices_table}` inv
			 INNER JOIN `{$threads_table}` t ON t.id = inv.thread_id
			 WHERE inv.status = 'pending'
			   AND inv.auto_debit_date <= %s
			 ORDER BY inv.auto_debit_date ASC
			 LIMIT %d",
			current_time( 'mysql', true ),
			self::CHUNK_SIZE
		) );

		if ( empty( $due_invoices ) ) {
			return [ 'charged' => 0, 'failed' => 0, 'skipped' => 0 ];
		}

		$stripe_key = function_exists( 'khm_get_stripe_secret' )
			? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
			: '';

		if ( empty( $stripe_key ) ) {
			error_log( '[KHM RFP Commission] Stripe not configured — aborting cron run.' );
			return [ 'charged' => 0, 'failed' => 0, 'skipped' => count( $due_invoices ) ];
		}

		\Stripe\Stripe::setApiKey( $stripe_key );

		$charged = 0;
		$failed  = 0;
		$skipped = 0;

		foreach ( $due_invoices as $invoice ) {
			$invoice_id        = (int) $invoice->id;
			$thread_id         = (int) $invoice->thread_id;
			$sponsor_id        = (int) $invoice->sponsor_id;
			$commission_amount = (float) $invoice->commission_amount; // GBP decimal (e.g. 150.00)
			$amount_pence      = (int) round( $commission_amount * 100 );

			if ( $amount_pence <= 0 ) {
				error_log( sprintf( '[KHM RFP Commission] Invoice #%d has zero/negative amount — skipping.', $invoice_id ) );
				$skipped++;
				continue;
			}

			// Resolve seller payment profile.
			$profile = $this->payment_repo->get_by_seller_id( $sponsor_id );

			if ( ! $profile || empty( $profile->stripe_customer_id ) ) {
				error_log( sprintf(
					'[KHM RFP Commission] No Stripe payment profile for sponsor %d (invoice #%d) — marking failed.',
					$sponsor_id, $invoice_id
				) );
				$this->mark_failed( $invoice_id, 'no_payment_profile' );
				$failed++;
				do_action( 'khm_rfp_commission_failed', $invoice_id, $sponsor_id, 'no_payment_profile' );
				continue;
			}

			$stripe_customer_id = (string) $profile->stripe_customer_id;

			try {
				// Retrieve customer to get their default payment method.
				$customer = \Stripe\Customer::retrieve( $stripe_customer_id );
				$pm_id    = isset( $customer->invoice_settings->default_payment_method )
					? (string) $customer->invoice_settings->default_payment_method
					: '';

				if ( empty( $pm_id ) ) {
					error_log( sprintf(
						'[KHM RFP Commission] No default payment method for customer %s (invoice #%d) — marking failed.',
						$stripe_customer_id, $invoice_id
					) );
					$this->mark_failed( $invoice_id, 'no_default_payment_method' );
					$failed++;
					do_action( 'khm_rfp_commission_failed', $invoice_id, $sponsor_id, 'no_default_payment_method' );
					continue;
				}

				$intent = \Stripe\PaymentIntent::create( [
					'amount'         => $amount_pence,
					'currency'       => 'gbp',
					'customer'       => $stripe_customer_id,
					'payment_method' => $pm_id,
					'off_session'    => true,
					'confirm'        => true,
					'description'    => sprintf( 'RFP commission — thread #%d', $thread_id ),
					'metadata'       => [
						'purchase_type' => 'rfp_commission',
						'invoice_id'    => (string) $invoice_id,
						'thread_id'     => (string) $thread_id,
						'sponsor_id'    => (string) $sponsor_id,
					],
				] );

				if ( 'succeeded' === $intent->status ) {
					$this->mark_charged( $invoice_id, $intent->id );
					$charged++;
					error_log( sprintf(
						'[KHM RFP Commission] Invoice #%d charged GBP %.2f for thread #%d (intent %s).',
						$invoice_id, $commission_amount, $thread_id, $intent->id
					) );
					/**
					 * Fires after a commission invoice is successfully charged.
					 *
					 * @param int    $invoice_id
					 * @param int    $thread_id
					 * @param int    $sponsor_id
					 * @param string $intent_id         Stripe PaymentIntent ID.
					 * @param float  $commission_amount GBP decimal.
					 */
					do_action( 'khm_rfp_commission_charged', $invoice_id, $thread_id, $sponsor_id, $intent->id, $commission_amount );
				} else {
					// Intent is in requires_action or other incomplete state.
					error_log( sprintf(
						'[KHM RFP Commission] Invoice #%d intent %s has status "%s" — marking failed.',
						$invoice_id, $intent->id, $intent->status
					) );
					$this->mark_failed( $invoice_id, 'intent_status_' . sanitize_key( $intent->status ) );
					$failed++;
					do_action( 'khm_rfp_commission_failed', $invoice_id, $sponsor_id, $intent->status );
				}
			} catch ( \Stripe\Exception\CardException $e ) {
				error_log( sprintf( '[KHM RFP Commission] Card declined for invoice #%d: %s', $invoice_id, $e->getMessage() ) );
				$this->mark_failed( $invoice_id, 'card_declined' );
				$failed++;
				do_action( 'khm_rfp_commission_failed', $invoice_id, $sponsor_id, 'card_declined' );
			} catch ( \Exception $e ) {
				error_log( sprintf( '[KHM RFP Commission] Exception for invoice #%d: %s', $invoice_id, $e->getMessage() ) );
				$this->mark_failed( $invoice_id, 'stripe_exception' );
				$failed++;
				do_action( 'khm_rfp_commission_failed', $invoice_id, $sponsor_id, 'stripe_exception' );
			}
		}

		return [ 'charged' => $charged, 'failed' => $failed, 'skipped' => $skipped ];
	}

	// ─── Status updaters ──────────────────────────────────────────────────────

	private function mark_charged( int $invoice_id, string $intent_id ): void {
		global $wpdb;

		$wpdb->update(
			CreateRFPSupportTables::invoices_table_name(),
			[
				'status'                   => 'charged',
				'stripe_payment_intent_id' => $intent_id,
				'settled_at'               => current_time( 'mysql', true ),
			],
			[ 'id' => $invoice_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	private function mark_failed( int $invoice_id, string $reason ): void {
		global $wpdb;

		$wpdb->update(
			CreateRFPSupportTables::invoices_table_name(),
			[
				'status'         => 'failed',
				'dispute_reason' => sanitize_text_field( $reason ),
			],
			[ 'id' => $invoice_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}
}
