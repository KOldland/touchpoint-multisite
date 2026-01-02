<?php
/**
 * Scheduled task handlers for KHM Membership plugin.
 *
 * @package KHM\Scheduled
 */

namespace KHM\Scheduled;

use KHM\Services\MembershipRepository;
use KHM\Services\EmailService;
use KHM\Services\LevelRepository;

/**
 * Tasks
 *
 * Contains scheduled job handlers: expirations, warnings, cleanup.
 */
class Tasks {

	/**
	 * Repository for membership operations.
	 *
	 * @var MembershipRepository
	 */
	private MembershipRepository $memberships;

	/**
	 * Service for sending templated emails.
	 *
	 * @var EmailService
	 */
	private EmailService $email;

	/**
	 * Repository for level information.
	 *
	 * @var LevelRepository
	 */
	private LevelRepository $levels;

	/**
	 * Constructor.
	 *
	 * @param MembershipRepository|null $memberships Optional repository instance for memberships.
	 * @param EmailService|null         $email       Optional email service instance.
	 * @param LevelRepository|null      $levels      Optional level repository instance.
	 */
	public function __construct( ?MembershipRepository $memberships = null, ?EmailService $email = null, ?LevelRepository $levels = null ) {
		$this->memberships = $memberships ? $memberships : new MembershipRepository();
		// Plugin root directory (src/Scheduled -> plugin root).
		$this->email       = $email ? $email : new EmailService( dirname( __DIR__, 2 ) );
		$this->levels      = $levels ? $levels : new LevelRepository();
	}

	/**
	 * Run daily tasks
	 *
	 * @return array{expired:int,warned:int}
	 */
	public function run_daily(): array {
		// Allow early bail.
		if ( ! apply_filters( 'khm_run_daily_tasks', true ) ) {
			return array(
				'expired' => 0,
				'warned'  => 0,
			);
		}

		$expired = $this->process_expirations();
		$warned  = $this->send_expiration_warnings();

		do_action( 'khm_daily_tasks_completed' );

		return array(
			'expired' => $expired,
			'warned'  => $warned,
		);
	}

	/**
	 * Expire memberships whose end_date has passed
	 */
	public function process_expirations(): int {
		global $wpdb;
		$now = current_time( 'mysql' );

		// Find active memberships with end_date <= now.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, membership_id, end_date FROM {$wpdb->prefix}khm_memberships_users 
             WHERE status = 'active' AND end_date IS NOT NULL AND end_date <= %s",
				$now
			)
		);

		foreach ( $rows as $row ) {
			// Expire membership.
			$this->memberships->expire( (int) $row->user_id, (int) $row->membership_id );

			// Send 'membership_expired' email once.
			if ( ! $this->was_notified( $row->user_id, $row->id, 'expired' ) ) {
				$this->send_email( 'membership_expired', (int) $row->user_id, (int) $row->membership_id );
				$this->mark_notified( $row->user_id, $row->id, 'expired' );
			}
		}

		do_action( 'khm_cron_process_expirations', $rows );

		return is_array( $rows ) ? count( $rows ) : 0;
	}

	/**
	 * Send upcoming expiration warnings
	 */
	public function send_expiration_warnings(): int {
		global $wpdb;

		$days_before = (int) get_option( 'khm_expiry_warning_days', 7 );
		if ( $days_before <= 0 ) {
			return 0;
		}

		// Window: now -> now + N days (match exact day to avoid spamming).
		$start = new \DateTimeImmutable( current_time( 'mysql' ) ); // Already in site timezone string.
		$end   = $start->modify( "+{$days_before} days" );

		$start_str = $start->format( 'Y-m-d 00:00:00' );
		$end_str   = $end->format( 'Y-m-d 23:59:59' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, membership_id, end_date FROM {$wpdb->prefix}khm_memberships_users 
             WHERE status = 'active' AND end_date IS NOT NULL 
             AND end_date BETWEEN %s AND %s",
				$start_str,
				$end_str
			)
		);

		$sent = 0;
		foreach ( $rows as $row ) {
			if ( $this->was_notified( $row->user_id, $row->id, 'expiring' ) ) {
				continue;
			}

			$this->send_email(
				'membership_expiring',
				(int) $row->user_id,
				(int) $row->membership_id,
				array(
					'end_date'  => $row->end_date,
					'days_left' => $days_before,
				)
			);
			$this->mark_notified( $row->user_id, $row->id, 'expiring' );
			++$sent;
		}

		do_action( 'khm_cron_send_expiration_warnings', $rows, $days_before );

		return $sent;
	}

	/**
	 * Helper: send email using EmailService.
	 *
	 * @param string $template Email template slug.
	 * @param int    $user_id  User ID to send to.
	 * @param int    $level_id Membership level ID.
	 * @param array  $data     Additional token data for the template.
	 * @return void
	 */
	private function send_email( string $template, int $user_id, int $level_id, array $data = array() ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$level = $this->get_level_info( $level_id );

		$tokens = array_merge(
			array(
				'name'             => $user->display_name,
				'email'            => $user->user_email,
				'membership_level' => $level ? $level->name : 'Member',
				'account_url'      => function_exists( 'khm_get_account_url' ) ? khm_get_account_url( 'memberships' ) : home_url( '/account/' ),
			),
			$data
		);

		$subject = $this->compute_subject( $template, $tokens );

		$this->email
			->setFrom( get_option( 'khm_email_from_address', get_option( 'admin_email' ) ), get_option( 'khm_email_from_name', get_bloginfo( 'name' ) ) )
			->setSubject( $subject )
			->send( $template, $user->user_email, $tokens );
	}

	/**
	 * Retrieve membership level information.
	 *
	 * @param int $level_id Membership level ID.
	 * @return object|null  Database row for the level or null if not found.
	 */
	private function get_level_info( int $level_id ) {
		return $this->levels->get( $level_id, true );
	}

	/**
	 * Build the usermeta key used to track sent notifications for a membership.
	 *
	 * @param int    $membership_id Membership record ID.
	 * @param string $type          Notification type (e.g. 'expired' or 'expiring').
	 * @return string Meta key name.
	 */
	private function notified_key( int $membership_id, string $type ): string {
		return 'khm_notified_' . $type . '_' . $membership_id;
	}

	/**
	 * Check if a notification of a given type was already sent for a membership.
	 *
	 * @param int    $user_id        User ID.
	 * @param int    $membership_id  Membership record ID.
	 * @param string $type           Notification type (e.g. 'expired' or 'expiring').
	 * @return bool  True if a notification was already sent.
	 */
	private function was_notified( int $user_id, int $membership_id, string $type ): bool {
		return (bool) get_user_meta( $user_id, $this->notified_key( $membership_id, $type ), true );
	}

	/**
	 * Mark a notification as sent for a given membership.
	 *
	 * @param int    $user_id        User ID.
	 * @param int    $membership_id  Membership record ID.
	 * @param string $type           Notification type (e.g. 'expired' or 'expiring').
	 * @return void
	 */
	private function mark_notified( int $user_id, int $membership_id, string $type ): void {
		update_user_meta( $user_id, $this->notified_key( $membership_id, $type ), current_time( 'mysql' ) );
	}

	/**
	 * Compute the email subject for a given template.
	 *
	 * @param string $template Template slug.
	 * @param array  $data     Token data passed to filters.
	 * @return string Subject line.
	 */
	private function compute_subject( string $template, array $data ): string {
		switch ( $template ) {
			case 'membership_expired':
				return apply_filters( 'khm_email_subject_membership_expired', __( 'Your membership has expired', 'khm-membership' ), $data );
			case 'membership_expiring':
				return apply_filters( 'khm_email_subject_membership_expiring', __( 'Your membership is expiring soon', 'khm-membership' ), $data );
			default:
				return apply_filters( 'khm_email_subject_default', __( 'Membership notification', 'khm-membership' ), $data );
		}
	}
}
