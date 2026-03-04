<?php
/**
 * Membership Repository
 *
 * Handles user membership lifecycle management.
 *
 * @package KHM\Services
 */

namespace KHM\Services;

use KHM\Contracts\MembershipRepositoryInterface;
use DateTime;
use DateTimeInterface;

class MembershipRepository implements MembershipRepositoryInterface {

    private const TEMP_ATTRIBUTION_OPTION_PREFIX = 'khm_temp_attribution_';
    private const SIGNUP_IDEMPOTENCY_OPTION_PREFIX = 'khm_signup_init_idem_';
    private const RETENTION_DEFAULT_DAYS = 730;

    private string $tableName;
    private string $levelsTable;
    private string $usersTable;
    private string $promotionAttributionTable;
    private string $sponsorsTable;
    private string $postsTable;
    private bool $hasPromotionAttributionTable;
    private bool $hasSponsorsTable;
    private bool $hasPostsTable;
    private LevelRepository $levels;

    public function __construct() {
        global $wpdb;
        $this->tableName = $wpdb->prefix . 'khm_memberships_users';
        $this->levelsTable = $wpdb->prefix . 'khm_membership_levels';
        $this->usersTable  = isset( $wpdb->users ) && is_string( $wpdb->users ) && $wpdb->users !== ''
            ? $wpdb->users
            : $wpdb->prefix . 'users';
        $this->promotionAttributionTable = $wpdb->prefix . 'promotion_attribution';
        $this->sponsorsTable = $wpdb->prefix . 'khm_sponsors';
        $this->postsTable = isset( $wpdb->posts ) && is_string( $wpdb->posts ) && $wpdb->posts !== ''
            ? $wpdb->posts
            : $wpdb->prefix . 'posts';
        $this->hasPromotionAttributionTable = $this->tableExists( $this->promotionAttributionTable );
        $this->hasSponsorsTable = $this->tableExists( $this->sponsorsTable );
        $this->hasPostsTable = $this->tableExists( $this->postsTable );
        $this->levels = new LevelRepository();
    }

    public function storeTempAttribution( string $sessionId, array $payload, int $ttlSeconds = 86400 ): bool {
        $sessionId = sanitize_text_field( $sessionId );
        if ( '' === $sessionId ) {
            return false;
        }

        $ttlSeconds = max( 60, $ttlSeconds );
        $record = [
            'session_id' => $sessionId,
            'payload' => $payload,
            'stored_at' => gmdate( 'c' ),
            'expires_at' => time() + $ttlSeconds,
        ];

        $optionKey = self::TEMP_ATTRIBUTION_OPTION_PREFIX . $sessionId;
        $updated = update_option( $optionKey, $record, false );
        set_transient( $optionKey, $record, $ttlSeconds );

        return (bool) $updated || get_option( $optionKey, null ) !== null;
    }

    public function getTempAttribution( string $sessionId ): ?array {
        $sessionId = sanitize_text_field( $sessionId );
        if ( '' === $sessionId ) {
            return null;
        }

        $optionKey = self::TEMP_ATTRIBUTION_OPTION_PREFIX . $sessionId;
        $record = get_transient( $optionKey );
        if ( ! is_array( $record ) ) {
            $record = get_option( $optionKey, null );
        }

        if ( ! is_array( $record ) ) {
            return null;
        }

        $expiresAt = isset( $record['expires_at'] ) ? (int) $record['expires_at'] : 0;
        if ( $expiresAt > 0 && $expiresAt < time() ) {
            delete_transient( $optionKey );
            return null;
        }

        return $record;
    }

    public function storeSignupInitIdempotency( string $idempotencyKey, string $sessionId, string $checkoutUrl, int $ttlSeconds = 86400 ): bool {
        $idempotencyKey = sanitize_text_field( $idempotencyKey );
        $sessionId = sanitize_text_field( $sessionId );
        if ( '' === $idempotencyKey || '' === $sessionId ) {
            return false;
        }

        $ttlSeconds = max( 60, $ttlSeconds );
        $optionKey = self::SIGNUP_IDEMPOTENCY_OPTION_PREFIX . md5( $idempotencyKey );
        $record = [
            'idempotency_key' => $idempotencyKey,
            'session_id' => $sessionId,
            'checkout_url' => function_exists( 'esc_url_raw' ) ? esc_url_raw( $checkoutUrl ) : ( filter_var( $checkoutUrl, FILTER_SANITIZE_URL ) ?: '' ),
            'stored_at' => gmdate( 'c' ),
            'expires_at' => time() + $ttlSeconds,
        ];

        $updated = update_option( $optionKey, $record, false );
        set_transient( $optionKey, $record, $ttlSeconds );

        return (bool) $updated || get_option( $optionKey, null ) !== null;
    }

    public function getSignupInitByIdempotency( string $idempotencyKey ): ?array {
        $idempotencyKey = sanitize_text_field( $idempotencyKey );
        if ( '' === $idempotencyKey ) {
            return null;
        }

        $optionKey = self::SIGNUP_IDEMPOTENCY_OPTION_PREFIX . md5( $idempotencyKey );
        $record = get_transient( $optionKey );
        if ( ! is_array( $record ) ) {
            $record = get_option( $optionKey, null );
        }

        if ( ! is_array( $record ) ) {
            return null;
        }

        $expiresAt = isset( $record['expires_at'] ) ? (int) $record['expires_at'] : 0;
        if ( $expiresAt > 0 && $expiresAt < time() ) {
            delete_transient( $optionKey );
            return null;
        }

        return $record;
    }

    public function resolveLandingSchedule( string $scheduleId ): array {
        $scheduleId = sanitize_text_field( $scheduleId );
        $scheduleId = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', $scheduleId ), 0, 128 );

        $payload = [
            'id' => $scheduleId,
            'title' => '',
            'recommended_post_time' => '',
            'boost_copy' => '',
        ];

        if ( '' === $scheduleId ) {
            $payload['id'] = 'unknown';
            $payload['title'] = __( 'Membership Success', 'khm-membership' );
            return $payload;
        }

        $numericId = 0;
        if ( preg_match( '/(\d+)/', $scheduleId, $matches ) ) {
            $numericId = absint( $matches[1] );
        }

        if ( $numericId > 0 && function_exists( 'get_post' ) ) {
            $post = get_post( $numericId );
            if ( is_object( $post ) && isset( $post->post_title ) ) {
                $payload['title'] = sanitize_text_field( (string) $post->post_title );
            }

            if ( function_exists( 'get_post_meta' ) ) {
                $payload['recommended_post_time'] = sanitize_text_field( (string) get_post_meta( $numericId, 'recommended_post_time', true ) );
                $payload['boost_copy'] = sanitize_text_field( (string) get_post_meta( $numericId, 'boost_copy', true ) );
            }
        }

        if ( '' === $payload['title'] ) {
            $payload['title'] = __( 'Membership Success', 'khm-membership' );
        }

        return $payload;
    }

    public function resolveLandingSponsor( ?string $sponsorId ): ?array {
        if ( null === $sponsorId ) {
            return null;
        }

        $sponsorId = sanitize_text_field( $sponsorId );
        $sponsorId = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', $sponsorId ), 0, 128 );
        if ( '' === $sponsorId ) {
            return null;
        }

        $numericId = 0;
        if ( preg_match( '/(\d+)/', $sponsorId, $matches ) ) {
            $numericId = absint( $matches[1] );
        }

        $name = '';
        if ( $numericId > 0 ) {
            global $wpdb;
            $table = $wpdb->prefix . 'khm_sponsors';
            $name = (string) $wpdb->get_var(
                $wpdb->prepare( "SELECT name FROM {$table} WHERE id = %d LIMIT 1", $numericId )
            );
        }

        $logo = (string) get_option( 'khm_sponsor_logo_' . $sponsorId, '' );
        $accent = sanitize_text_field( (string) get_option( 'khm_sponsor_accent_' . $sponsorId, '' ) );
        $blurb = (string) get_option( 'khm_sponsor_blurb_' . $sponsorId, '' );

        $allowedBlurb = [
            'a' => [ 'href' => [], 'target' => [], 'rel' => [] ],
            'strong' => [],
            'em' => [],
            'br' => [],
            'p' => [],
            'span' => [ 'class' => [] ],
        ];

        return [
            'id' => $sponsorId,
            'name' => sanitize_text_field( $name ),
            'logo_url' => function_exists( 'esc_url_raw' ) ? esc_url_raw( $logo ) : ( filter_var( $logo, FILTER_SANITIZE_URL ) ?: '' ),
            'accent_color' => preg_match( '/^#[A-Fa-f0-9]{6}$/', $accent ) ? $accent : '',
            'blurb' => function_exists( 'wp_kses' ) ? wp_kses( $blurb, $allowedBlurb ) : strip_tags( $blurb, '<a><strong><em><br><p><span>' ),
        ];
    }

    public function buildLandingSuccessCtas(): array {
        return [
            [ 'name' => 'Go to account', 'action' => 'account_url', 'url' => home_url( '/account' ) ],
            [ 'name' => 'Download welcome pack', 'action' => 'download', 'url' => home_url( '/download/pack.pdf' ) ],
            [ 'name' => 'Invite a friend', 'action' => 'invite', 'url' => home_url( '/invite' ) ],
            [ 'name' => 'Manage membership', 'action' => 'manage', 'url' => home_url( '/account/membership' ) ],
        ];
    }

    /**
     * Assign a membership level to a user.
     */
    public function assign( int $userId, int $levelId, array $options = [] ): object {
        global $wpdb;

        // Check if user already has this level
        $existing = $this->find($userId, $levelId);

        if ( $existing ) {
            // Update existing membership
            return $this->updateExisting($existing, $options);
        }

        // Create new membership
        $data = [
            'user_id' => $userId,
            'membership_id' => $levelId,
            'status' => $options['status'] ?? 'active',
            'startdate' => isset($options['start_date'])
                ? $this->formatDateTime($options['start_date'])
                : current_time('mysql', true),
        ];

        if ( isset($options['end_date']) ) {
            $data['enddate'] = $this->formatDateTime($options['end_date']);
        }

        if ( isset($options['initial_payment']) ) {
            $data['initial_payment'] = $options['initial_payment'];
        }

        if ( isset($options['billing_amount']) ) {
            $data['billing_amount'] = $options['billing_amount'];
        }

        if ( isset($options['cycle_number']) ) {
            $data['cycle_number'] = $options['cycle_number'];
        }

        if ( isset($options['cycle_period']) ) {
            $data['cycle_period'] = $options['cycle_period'];
        }

        if ( isset($options['billing_limit']) ) {
            $data['billing_limit'] = $options['billing_limit'];
        }

        if ( isset($options['trial_amount']) ) {
            $data['trial_amount'] = $options['trial_amount'];
        }

        if ( isset($options['trial_limit']) ) {
            $data['trial_limit'] = $options['trial_limit'];
        }

        $wpdb->insert($this->tableName, $data);
        $membershipId = $wpdb->insert_id;

        do_action('khm_membership_assigned', $userId, $levelId, $membershipId, $options);
        $this->recalculateUserCapabilities( $userId );

        return $this->find($userId, $levelId);
    }

    /**
     * Cancel a user's membership.
     */
    public function cancel( int $userId, int $levelId, string $reason = '' ): bool {
        $membership = $this->find($userId, $levelId);

        if ( ! $membership ) {
            return false;
        }

        $graceDays = (int) apply_filters( 'khm_membership_grace_period_days', 0, $userId, $levelId, $reason );
        $status    = 'cancelled';
        $extra     = [
            'enddate'       => current_time( 'mysql', true ),
            'grace_enddate' => null,
        ];

        if ( $graceDays > 0 ) {
            $status = 'grace';
            $graceEnd = gmdate( 'Y-m-d H:i:s', time() + ( $graceDays * DAY_IN_SECONDS ) );
            $extra['enddate']       = $graceEnd;
            $extra['grace_enddate'] = $graceEnd;
        }

        $changed = $this->setStatus( $userId, $levelId, $status, $reason, $extra );

        if ( $changed ) {
            do_action('khm_membership_cancelled', $userId, $levelId, $reason);
        }

        return $changed;
    }

    /**
     * Cancel a membership by record ID.
     */
    public function cancelById( int $membershipId, string $reason = '' ): bool {
        $membership = $this->getById( $membershipId );
        if ( ! $membership ) {
            return false;
        }

        return $this->cancel( (int) $membership->user_id, (int) $membership->membership_id, $reason );
    }

    /**
     * Pause a membership.
     */
    public function pause( int $userId, int $levelId, ?DateTime $resumeAt = null, string $reason = '' ): bool {
        $membership = $this->find( $userId, $levelId );
        if ( ! $membership ) {
            return false;
        }

        $extra = [
            'paused_at'   => current_time( 'mysql', true ),
            'pause_until' => $resumeAt ? $resumeAt->format( 'Y-m-d H:i:s' ) : null,
        ];

        return $this->setStatus(
            $userId,
            $levelId,
            'paused',
            $reason ?: __( 'Membership paused.', 'khm-membership' ),
            $extra
        );
    }

    /**
     * Pause membership by record id.
     */
    public function pauseById( int $membershipId, ?DateTime $resumeAt = null, string $reason = '' ): bool {
        $membership = $this->getById( $membershipId );
        if ( ! $membership ) {
            return false;
        }

        return $this->pause( (int) $membership->user_id, (int) $membership->membership_id, $resumeAt, $reason );
    }

    /**
     * Resume a paused membership.
     */
    public function resume( int $userId, int $levelId, string $reason = '' ): bool {
        $membership = $this->find( $userId, $levelId );
        if ( ! $membership ) {
            return false;
        }

        return $this->setStatus(
            $userId,
            $levelId,
            'active',
            $reason ?: __( 'Membership resumed.', 'khm-membership' ),
            [
                'paused_at'      => null,
                'pause_until'    => null,
                'status_reason'  => $reason ?: null,
            ]
        );
    }

    public function resumeById( int $membershipId, string $reason = '' ): bool {
        $membership = $this->getById( $membershipId );
        if ( ! $membership ) {
            return false;
        }

        return $this->resume( (int) $membership->user_id, (int) $membership->membership_id, $reason );
    }

    /**
     * Expire a membership by record ID.
     */
    public function expireById( int $membershipId ): bool {
        $membership = $this->getById( $membershipId );
        if ( ! $membership ) {
            return false;
        }

        return $this->expire( (int) $membership->user_id, (int) $membership->membership_id );
    }

    /**
     * Reactivate a membership by record ID.
     */
    public function reactivateById( int $membershipId ): bool {
        $membership = $this->getById( $membershipId );
        if ( ! $membership ) {
            return false;
        }

        $reactivated = $this->setStatus(
            (int) $membership->user_id,
            (int) $membership->membership_id,
            'active',
            __( 'Membership reactivated via admin.', 'khm-membership' ),
            [
                'enddate'        => null,
                'grace_enddate'  => null,
                'paused_at'      => null,
                'pause_until'    => null,
            ]
        );

        return $reactivated;
    }

    /**
     * Change the membership level for an existing membership (update in place).
     * This updates the membership_id field rather than creating a new record.
     *
     * @param int $membershipId The membership record ID to update
     * @param int $newLevelId The new level ID to assign
     * @param array $options Optional updates (status, start_date, end_date)
     * @return bool True on success
     */
    public function changeLevelById( int $membershipId, int $newLevelId, array $options = [] ): bool {
        global $wpdb;

        $membership = $this->getById( $membershipId );
        if ( ! $membership ) {
            return false;
        }

        $oldLevelId = (int) $membership->membership_id;
        $userId = (int) $membership->user_id;

        $data = [
            'membership_id' => $newLevelId,
        ];
        $formats = [ '%d' ];

        if ( isset( $options['status'] ) ) {
            $data['status'] = $options['status'];
            $formats[] = '%s';
        }

        if ( isset( $options['start_date'] ) ) {
            $data['startdate'] = $this->formatDateTime( $options['start_date'] );
            $formats[] = '%s';
        }

        if ( isset( $options['end_date'] ) ) {
            $data['enddate'] = $this->formatDateTime( $options['end_date'] );
            $formats[] = '%s';
        } elseif ( array_key_exists( 'end_date', $options ) && $options['end_date'] === null ) {
            $data['enddate'] = null;
            $formats[] = '%s';
        }

        $result = $wpdb->update(
            $this->tableName,
            $data,
            [ 'id' => $membershipId ],
            $formats,
            [ '%d' ]
        );

        if ( $result !== false ) {
            do_action( 'khm_membership_level_changed', $userId, $oldLevelId, $newLevelId, $membershipId );
            $this->recalculateUserCapabilities( $userId );
            return true;
        }

        return false;
    }

    /**
     * Expire a user's membership.
     */
    public function expire( int $userId, int $levelId ): bool {
        $membership = $this->find($userId, $levelId);

        if ( ! $membership ) {
            return false;
        }

        $changed = $this->setStatus(
            $userId,
            $levelId,
            'expired',
            __( 'Membership expired.', 'khm-membership' ),
            [
                'enddate'       => current_time( 'mysql', true ),
                'grace_enddate' => null,
            ]
        );

        if ( $changed ) {
            do_action('khm_membership_expired', $userId, $levelId);
        }

        return $changed;
    }

    /**
     * Find all active memberships for a user.
     */
    public function findActive( int $userId ): array {
        global $wpdb;

        /* phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
        $memberships = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, l.name as level_name 
                 FROM {$this->tableName} m
                 LEFT JOIN {$wpdb->prefix}khm_membership_levels l ON m.membership_id = l.id
                 WHERE m.user_id = %d
                 AND m.status IN ('active','grace')
                 AND (m.enddate IS NULL OR m.enddate > %s)
                 ORDER BY m.startdate DESC",
                $userId,
                current_time( 'mysql', true )
            )
        );
        /* phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared */

        return $memberships ?: [];
    }

    /**
     * Find all users with a specific membership level.
     */
    public function findByLevel( int $levelId, array $filters = [] ): array {
        global $wpdb;

        $where = [ 'membership_id = %d' ];
        $values = [ $levelId ];

        if ( ! empty($filters['status']) ) {
            if ( is_array( $filters['status'] ) ) {
                $statuses   = array_map( 'sanitize_key', $filters['status'] );
                $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
                $where[]    = "status IN ({$placeholders})";
                $values     = array_merge( $values, $statuses );
            } else {
                $where[] = 'status = %s';
                $values[] = sanitize_key( $filters['status'] );
            }
        } else {
            $where[] = "status IN ('active','grace')";
        }

        $whereClause = implode(' AND ', $where);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a known safe, plugin-owned table string.
        $sql = "SELECT * FROM {$this->tableName} WHERE {$whereClause} ORDER BY startdate DESC";

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name is a known safe, plugin-owned table string, and the SQL is prepared on the next line.
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * Find memberships expiring within N days.
     */
    public function findExpiring( int $days = 7 ): array {
        global $wpdb;

        $now = current_time('mysql', true);
        $futureDate = gmdate('Y-m-d H:i:s', strtotime("+{$days} days"));

        /* phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
        $memberships = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, l.name as level_name, u.user_email
             FROM {$this->tableName} m
             LEFT JOIN {$wpdb->prefix}khm_membership_levels l ON m.membership_id = l.id
             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             WHERE m.status IN ('active','grace')
             AND m.enddate IS NOT NULL
             AND m.enddate > %s
             AND m.enddate <= %s
             ORDER BY m.enddate ASC",
            $now,
            $futureDate
        ));
        /* phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared */

        return $memberships ?: [];
    }

    /**
     * Check if a user has access to a specific membership level.
     */
    public function hasAccess( int $userId, int $levelId ): bool {
        $membership = $this->find($userId, $levelId);

        if ( ! $membership || ! in_array( $membership->status, [ 'active', 'grace' ], true ) ) {
            return false;
        }

        // Check if expired
        if ( ! empty($membership->enddate) ) {
            $endDate = strtotime($membership->enddate);
            $now = time();

            if ( $endDate < $now ) {
                // Expire the membership
                $this->expire($userId, $levelId);
                return false;
            }
        }

        return true;
    }

    /**
     * Update the end date for a membership.
     */
    public function updateEndDate( int $userId, int $levelId, ?DateTime $endDate ): bool {
        global $wpdb;

        $formattedEnd = $endDate ? $endDate->format('Y-m-d H:i:s') : null;
        $data = [
            'enddate' => $formattedEnd,
            'grace_enddate' => $formattedEnd,
        ];

        $result = $wpdb->update(
            $this->tableName,
            $data,
            [
                'user_id' => $userId,
                'membership_id' => $levelId,
            ],
            [ '%s' ],
            [ '%d', '%d' ]
        );

        if ( $result !== false ) {
            do_action('khm_membership_end_date_updated', $userId, $levelId, $endDate);
        }

        return $result !== false;
    }

    /**
     * Get membership details for a user and level.
     */
    public function find( int $userId, int $levelId ): ?object {
        global $wpdb;

        /* phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
        $membership = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT m.*, l.name as level_name
                 FROM {$this->tableName} m
                 LEFT JOIN {$wpdb->prefix}khm_membership_levels l ON m.membership_id = l.id
                 WHERE m.user_id = %d AND m.membership_id = %d
                 ORDER BY m.id DESC LIMIT 1",
                $userId,
                $levelId
            )
        );
        /* phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared */

        return $membership ?: null;
    }

    /**
     * Retrieve a membership by record ID.
     */
    public function getById( int $membershipId ): ?object {
        global $wpdb;
        $attribution = $this->attributionSelectSql( 'm.user_id', true );

        $membership = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT m.*, l.name AS level_name, u.user_login, u.user_email, u.display_name, {$attribution['select']}
                 FROM {$this->tableName} m
                 LEFT JOIN {$this->levelsTable} l ON m.membership_id = l.id
                 LEFT JOIN {$this->usersTable} u ON m.user_id = u.ID
                 {$attribution['join']}
                 WHERE m.id = %d LIMIT 1",
                $membershipId
            )
        );

        return $membership ?: null;
    }

    /**
     * Retrieve multiple memberships by IDs.
     *
     * @param array<int> $ids Membership record IDs.
     * @return array<array<string,mixed>>
     */
    public function getMany( array $ids ): array {
        global $wpdb;

        $ids = array_values(
            array_filter(
                array_map( 'intval', $ids ),
                static fn( $id ) => $id > 0
            )
        );

        if ( empty( $ids ) ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.id,
                        m.user_id,
                        m.membership_id,
                        m.status,
                        m.startdate AS start_date,
                        m.enddate AS end_date,
                        m.initial_payment,
                        m.billing_amount,
                        m.cycle_number,
                        m.cycle_period,
                        m.billing_limit,
                        m.trial_amount,
                        m.trial_limit,
                        u.user_login,
                        u.user_email,
                        u.display_name,
                        l.name AS level_name
                 FROM {$this->tableName} m
                 LEFT JOIN {$this->levelsTable} l ON m.membership_id = l.id
                 LEFT JOIN {$this->usersTable} u ON m.user_id = u.ID
                 WHERE m.id IN ($placeholders)
                 ORDER BY m.id ASC",
                $ids
            ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    /**
     * Update an existing membership.
     */
    private function updateExisting( object $membership, array $options ): object {
        global $wpdb;

        $data = [];

        if ( isset($options['start_date']) ) {
            $data['startdate'] = $this->formatDateTime($options['start_date']);
        }

        if ( isset($options['end_date']) ) {
            $data['enddate'] = $this->formatDateTime($options['end_date']);
            $data['grace_enddate'] = $data['enddate'];
        }

        if ( isset($options['billing_amount']) ) {
            $data['billing_amount'] = $options['billing_amount'];
        }

        if ( ! empty($data) ) {
            $wpdb->update(
                $this->tableName,
                $data,
                [ 'id' => $membership->id ],
                null,
                [ '%d' ]
            );

            do_action('khm_membership_updated', $membership->user_id, $membership->membership_id, $data);
        }

        if ( isset( $options['status'] ) ) {
            $this->setStatus(
                (int) $membership->user_id,
                (int) $membership->membership_id,
                (string) $options['status'],
                __( 'Membership status updated.', 'khm-membership' )
            );
        } else {
            $this->recalculateUserCapabilities( (int) $membership->user_id );
        }

        return $this->find($membership->user_id, $membership->membership_id);
    }

    /**
     * Set the status for a membership record.
     */
    public function setStatus( int $userId, int $levelId, string $status, string $reason = '', array $fields = [] ): bool {
        global $wpdb;

        $data = array_merge(
            [
                'status'         => $status,
                'modified'       => current_time('mysql', true),
                'status_reason'  => $reason ?: null,
            ],
            $this->normalizeLifecycleFields( $fields )
        );

        $result = $wpdb->update(
            $this->tableName,
            $data,
            [
                'user_id' => $userId,
                'membership_id' => $levelId,
            ],
            null,
            [ '%d', '%d' ]
        );

        if ( false !== $result ) {
            $this->recalculateUserCapabilities( $userId );
            do_action( 'khm_membership_status_changed', $userId, $levelId, $status, $reason );
        }

        return false !== $result;
    }

    /**
     * Update membership status by record ID.
     */
    public function setStatusById( int $membershipId, string $status, string $reason = '', array $fields = [] ): bool {
        $membership = $this->getById( $membershipId );
        if ( ! $membership ) {
            return false;
        }

        return $this->setStatus(
            (int) $membership->user_id,
            (int) $membership->membership_id,
            $status,
            $reason,
            $fields
        );
    }

    /**
     * Mark a membership as past due.
     */
    public function markPastDue( int $userId, int $levelId, string $reason = '' ): bool {
        $changed = $this->setStatus( $userId, $levelId, 'past_due', $reason );

        if ( $changed ) {
            do_action( 'khm_membership_marked_past_due', $userId, $levelId, $reason );
        }

        return $changed;
    }

    /**
     * Update billing profile attributes.
     */
    public function updateBillingProfile( int $userId, int $levelId, array $attributes = [] ): bool {
        global $wpdb;

        if ( empty( $attributes ) ) {
            return false;
        }

        $allowed = [
            'billing_amount',
            'billing_limit',
            'cycle_number',
            'cycle_period',
            'trial_amount',
            'trial_limit',
            'initial_payment',
            'status',
        ];

        $data = [];
        foreach ( $allowed as $field ) {
            if ( array_key_exists( $field, $attributes ) ) {
                $data[ $field ] = $attributes[ $field ];
            }
        }

        $status = null;
        if ( isset( $data['status'] ) ) {
            $status = $data['status'];
            unset( $data['status'] );
        }

        if ( empty( $data ) && null === $status ) {
            return false;
        }

        $result = true;

        if ( ! empty( $data ) ) {
            $data['modified'] = current_time( 'mysql', true );

            $result = $wpdb->update(
                $this->tableName,
                $data,
                [
                    'user_id' => $userId,
                    'membership_id' => $levelId,
                ],
                null,
                [ '%d', '%d' ]
            );

            if ( false !== $result ) {
                do_action( 'khm_membership_billing_profile_updated', $userId, $levelId, $data );
            }
        }

        $statusResult = true;
        if ( null !== $status ) {
            $statusResult = $this->setStatus( $userId, $levelId, (string) $status, 'Stripe subscription update' );
        }

        return false !== $result && $statusResult;
    }

    /**
     * Delete a membership by record ID.
     */
    public function deleteById( int $membershipId ): bool {
        global $wpdb;

        $membership = $this->getById( $membershipId );

        $result = $wpdb->delete(
            $this->tableName,
            [ 'id' => $membershipId ],
            [ '%d' ]
        );

        if ( $result && $membership ) {
            do_action(
                'khm_membership_deleted',
                (int) $membership->user_id,
                (int) $membership->membership_id,
                $membershipId
            );
        }

        return (bool) $result;
    }

    /**
     * Paginate membership records for admin listing.
     *
     * @param array<string,mixed> $args Query arguments.
     * @return array{items: array<array<string,mixed>>, total: int}
     */
    public function paginate( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'search'   => '',
            'level_id' => null,
            'status'   => '',
            'schedule_id' => null,
            'sponsor_id' => null,
            'conversion_type' => '',
            'orderby'  => 'start_date',
            'order'    => 'DESC',
            'per_page' => 20,
            'offset'   => 0,
        ];

        $args = array_merge(
            $defaults,
            array_intersect_key( $args, $defaults )
        );

        $where  = [];
        $values = [];

        if ( ! empty( $args['search'] ) ) {
            $like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]   = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
            $values[]  = $like;
            $values[]  = $like;
            $values[]  = $like;
        }

        if ( ! empty( $args['level_id'] ) ) {
            $where[]  = 'm.membership_id = %d';
            $values[] = (int) $args['level_id'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'm.status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['schedule_id'] ) && $this->hasPromotionAttributionTable ) {
            $where[]  = 'pa.schedule_id = %d';
            $values[] = (int) $args['schedule_id'];
        }

        if ( ! empty( $args['sponsor_id'] ) && $this->hasPromotionAttributionTable ) {
            $where[]  = 'pa.sponsor_id = %d';
            $values[] = (int) $args['sponsor_id'];
        }

        if ( ! empty( $args['conversion_type'] ) && $this->hasPromotionAttributionTable ) {
            $where[]  = 'pa.conversion_type = %s';
            $values[] = (string) $args['conversion_type'];
        }

        $whereSql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $orderMap = [
            'user'       => 'u.display_name',
            'email'      => 'u.user_email',
            'level'      => 'l.name',
            'start_date' => 'm.startdate',
            'end_date'   => 'm.enddate',
            'status'     => 'm.status',
            'attribution_schedule' => 'pa.schedule_id',
            'attribution_sponsor' => 'pa.sponsor_id',
            'attribution_conversion' => 'pa.conversion_type',
            'attribution_created' => 'pa.created_at',
        ];

        $orderBy = isset( $orderMap[ $args['orderby'] ] ) ? $orderMap[ $args['orderby'] ] : 'm.startdate';
        if ( ! $this->hasPromotionAttributionTable && strpos( $orderBy, 'pa.' ) === 0 ) {
            $orderBy = 'm.startdate';
        }
        $order   = strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = max( 1, (int) $args['per_page'] );
        $offset = max( 0, (int) $args['offset'] );
        $attribution = $this->attributionSelectSql( 'm.user_id', false );

        $selectSql = "SELECT m.id,
                             m.user_id,
                             m.membership_id,
                             m.status,
                             m.startdate AS start_date,
                             m.enddate AS end_date,
                             m.initial_payment,
                             m.billing_amount,
                             m.cycle_number,
                             m.cycle_period,
                             m.billing_limit,
                             m.trial_amount,
                             m.trial_limit,
                             u.user_login,
                             u.user_email,
                             u.display_name,
                             l.name AS level_name,
                             {$attribution['select']}
                      FROM {$this->tableName} m
                      LEFT JOIN {$this->levelsTable} l ON m.membership_id = l.id
                      LEFT JOIN {$this->usersTable} u ON m.user_id = u.ID
                      {$attribution['join']}
                      {$whereSql}
                      ORDER BY {$orderBy} {$order}
                      LIMIT %d OFFSET %d";

        $items = $wpdb->get_results(
            $wpdb->prepare(
                $selectSql,
                array_merge( $values, [ $limit, $offset ] )
            ),
            ARRAY_A
        );

        $countSql = "SELECT COUNT(*)
                     FROM {$this->tableName} m
                     LEFT JOIN {$this->levelsTable} l ON m.membership_id = l.id
                     LEFT JOIN {$this->usersTable} u ON m.user_id = u.ID
                     {$whereSql}";

        $total = (int) $wpdb->get_var(
            $values ? $wpdb->prepare( $countSql, $values ) : $countSql
        );

        return [
            'items' => $items ?: [],
            'total' => $total,
        ];
    }

    /**
     * Retrieve attribution history rows for a specific user.
     *
     * @param int $userId User ID.
     * @param int $limit Max rows.
     * @return array<int,array<string,mixed>>
     */
    public function getAttributionHistoryForUser( int $userId, int $limit = 100 ): array {
        if ( $userId <= 0 || ! $this->hasPromotionAttributionTable ) {
            return [];
        }

        global $wpdb;

        $limit = max( 1, min( 500, $limit ) );

        $fields = [
            'pa.id',
            'pa.schedule_id',
            'pa.sponsor_id',
            'pa.user_id',
            'pa.user_email',
            'pa.utm_source',
            'pa.utm_medium',
            'pa.utm_campaign',
            'pa.utm_term',
            'pa.utm_content',
            'pa.phase_at_click',
            'pa.conversion_type',
            'pa.plan_id',
            'pa.reference_metadata',
            'pa.created_at',
        ];

        if ( $this->hasPostsTable ) {
            $fields[] = 'schedule_post.post_title AS schedule_title';
        } else {
            $fields[] = 'NULL AS schedule_title';
        }

        if ( $this->hasSponsorsTable ) {
            $fields[] = 'sponsor.name AS sponsor_name';
        } else {
            $fields[] = 'NULL AS sponsor_name';
        }

        $sql = "SELECT " . implode( ', ', $fields ) . "
                FROM {$this->promotionAttributionTable} pa";

        if ( $this->hasPostsTable ) {
            $sql .= " LEFT JOIN {$this->postsTable} schedule_post ON schedule_post.ID = pa.schedule_id";
        }

        if ( $this->hasSponsorsTable ) {
            $sql .= " LEFT JOIN {$this->sponsorsTable} sponsor ON sponsor.id = pa.sponsor_id";
        }

        $sql .= " WHERE pa.user_id = %d ORDER BY pa.created_at DESC, pa.id DESC LIMIT %d";

        $rows = $wpdb->get_results(
            $wpdb->prepare( $sql, $userId, $limit ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    /**
     * Anonymize attribution rows for a specific user.
     *
     * @param int $userId User ID.
     * @return int Number of updated rows.
     */
    public function anonymizeAttributionForUser( int $userId ): int {
        if ( $userId <= 0 || ! $this->hasPromotionAttributionTable ) {
            return 0;
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$this->promotionAttributionTable} WHERE user_id = %d ORDER BY id DESC LIMIT %d",
                $userId,
                50000
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return 0;
        }

        $count = 0;
        $actor_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
        foreach ( $rows as $row ) {
            $id = isset( $row['id'] ) ? (int) $row['id'] : 0;
            if ( $id <= 0 ) {
                continue;
            }
            if ( $this->anonymizeAttributionById( $id, $actor_id, 'user_request_or_admin' ) ) {
                $count++;
            }
        }

        return $count;
    }

    public function anonymizeAttributionById( int $id, int $actorId = 0, string $reason = 'manual' ): bool {
        if ( $id <= 0 || ! $this->hasPromotionAttributionTable ) {
            return false;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->promotionAttributionTable} WHERE id = %d LIMIT 1", $id ),
            ARRAY_A
        );

        if ( ! is_array( $row ) ) {
            return false;
        }

        $reference = isset( $row['reference'] ) ? (string) $row['reference'] : '';
        if ( '' === $reference ) {
            $metadata = isset( $row['reference_metadata'] ) ? json_decode( (string) $row['reference_metadata'], true ) : null;
            if ( is_array( $metadata ) ) {
                $reference = isset( $metadata['reference'] ) ? (string) $metadata['reference'] : ( isset( $metadata['stripe_session_id'] ) ? (string) $metadata['stripe_session_id'] : '' );
            }
        }

        $referenceHash = $reference !== '' ? $this->buildReferenceHash( $reference ) : null;

        $metadata_audit = [
            'anonymized' => true,
            'anonymized_at' => gmdate( 'c' ),
            'anonymized_by' => $actorId,
            'reason' => $reason,
            'reference_hash' => $referenceHash,
        ];

        $updated = $wpdb->update(
            $this->promotionAttributionTable,
            [
                'user_id' => null,
                'user_email' => null,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'utm_term' => null,
                'utm_content' => null,
                'phase_at_click' => null,
                'reference' => null,
                'reference_hash' => $referenceHash,
                'consent' => 0,
                'consent_source' => 'anonymized',
                'anonymized_at' => current_time( 'mysql', 1 ),
                'anonymized_by' => $actorId > 0 ? $actorId : null,
                'anonymize_reason' => substr( sanitize_text_field( $reason ), 0, 255 ),
                'reference_metadata' => wp_json_encode( $metadata_audit ),
            ],
            [ 'id' => $id ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            do_action( 'khm_membership_reporting_telemetry', 'membership.anonymize.failed', [
                'id' => $id,
                'actor_id' => $actorId,
                'reason' => $reason,
            ] );
            return false;
        }

        do_action( 'khm_membership_reporting_telemetry', 'membership.anonymize.succeeded', [
            'id' => $id,
            'actor_id' => $actorId,
            'reason' => $reason,
        ] );
        do_action( 'khm_membership_attribution_mutated', [
            'operation' => 'anonymize_by_id',
            'id' => $id,
        ] );

        return true;
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function anonymizeAttributionByFilters( array $filters, int $actorId, string $reason, int $limit = 500, bool $dryRun = false ): array {
        if ( ! $this->hasPromotionAttributionTable ) {
            return [ 'matched' => 0, 'updated' => 0, 'ids' => [] ];
        }

        global $wpdb;
        $limit = max( 1, min( 5000, $limit ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->promotionAttributionTable} ORDER BY created_at ASC, id ASC LIMIT %d",
                100000
            ),
            ARRAY_A
        );

        $matchedIds = [];
        foreach ( $rows as $row ) {
            if ( count( $matchedIds ) >= $limit ) {
                break;
            }

            if ( ! empty( $row['anonymized_at'] ) ) {
                continue;
            }

            if ( isset( $filters['consent'] ) ) {
                $consentWanted = (int) $filters['consent'];
                $rowConsent = isset( $row['consent'] ) ? (int) $row['consent'] : 0;
                if ( $rowConsent !== $consentWanted ) {
                    continue;
                }
            }

            if ( ! empty( $filters['created_before'] ) ) {
                $cutoff = strtotime( (string) $filters['created_before'] );
                $rowTime = isset( $row['created_at'] ) ? strtotime( (string) $row['created_at'] ) : false;
                if ( false === $rowTime || false === $cutoff || $rowTime >= $cutoff ) {
                    continue;
                }
            }

            $id = isset( $row['id'] ) ? (int) $row['id'] : 0;
            if ( $id > 0 ) {
                $matchedIds[] = $id;
            }
        }

        if ( $dryRun ) {
            return [ 'matched' => count( $matchedIds ), 'updated' => 0, 'ids' => $matchedIds ];
        }

        $updated = 0;
        foreach ( $matchedIds as $id ) {
            if ( $this->anonymizeAttributionById( (int) $id, $actorId, $reason ) ) {
                $updated++;
            }
        }

        if ( $updated > 0 ) {
            do_action( 'khm_membership_attribution_mutated', [
                'operation' => 'anonymize_by_filters',
                'updated' => $updated,
                'matched' => count( $matchedIds ),
            ] );
        }

        return [ 'matched' => count( $matchedIds ), 'updated' => $updated, 'ids' => $matchedIds ];
    }

    public function anonymizeExpiredAttribution( int $retentionDays = self::RETENTION_DEFAULT_DAYS, int $chunkSize = 500 ): array {
        $retentionDays = max( 1, $retentionDays );
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retentionDays * 86400 ) );

        $result = $this->anonymizeAttributionByFilters(
            [
                'created_before' => $cutoff,
            ],
            0,
            'retention_expired',
            $chunkSize,
            false
        );

        $result['cutoff'] = $cutoff;
        return $result;
    }

    /**
     * Cursor-based retention anonymization to reduce lock contention at scale.
     *
     * @return array{matched:int,updated:int,ids:array<int,int>,last_id:int,cutoff:string}
     */
    public function anonymizeExpiredAttributionBatch( int $retentionDays = self::RETENTION_DEFAULT_DAYS, int $chunkSize = 500, int $lastSeenId = 0 ): array {
        $retentionDays = max( 1, $retentionDays );
        $chunkSize = max( 1, min( 5000, $chunkSize ) );
        $lastSeenId = max( 0, $lastSeenId );
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retentionDays * 86400 ) );

        $ids = $this->findRetentionCandidateIds( $cutoff, $chunkSize, $lastSeenId );
        if ( empty( $ids ) ) {
            return [
                'matched' => 0,
                'updated' => 0,
                'ids' => [],
                'last_id' => $lastSeenId,
                'cutoff' => $cutoff,
            ];
        }

        $updated = 0;
        foreach ( $ids as $id ) {
            if ( $this->anonymizeAttributionById( $id, 0, 'retention_expired' ) ) {
                $updated++;
            }
        }

        return [
            'matched' => count( $ids ),
            'updated' => $updated,
            'ids' => $ids,
            'last_id' => (int) max( $ids ),
            'cutoff' => $cutoff,
        ];
    }

    public function deleteAttributionForUser( int $userId ): int {
        if ( $userId <= 0 || ! $this->hasPromotionAttributionTable ) {
            return 0;
        }

        global $wpdb;
        $deleted = $wpdb->delete( $this->promotionAttributionTable, [ 'user_id' => $userId ], [ '%d' ] );
        $count = is_numeric( $deleted ) ? (int) $deleted : 0;
        if ( $count > 0 ) {
            do_action( 'khm_membership_attribution_mutated', [
                'operation' => 'delete_for_user',
                'user_id' => $userId,
                'deleted' => $count,
            ] );
        }
        return $count;
    }

    public function deleteExpiredAttribution( int $retentionDays = self::RETENTION_DEFAULT_DAYS, int $limit = 500 ): int {
        if ( ! $this->hasPromotionAttributionTable ) {
            return 0;
        }

        global $wpdb;
        $retentionDays = max( 1, $retentionDays );
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retentionDays * 86400 ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$this->promotionAttributionTable}
                 WHERE created_at < %s
                 AND (legal_hold_until IS NULL OR legal_hold_until < %s)
                 ORDER BY created_at ASC, id ASC
                 LIMIT %d",
                $cutoff,
                current_time( 'mysql', 1 ),
                max( 1, min( 5000, $limit ) )
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return 0;
        }

        $count = 0;
        foreach ( $rows as $row ) {
            $id = isset( $row['id'] ) ? (int) $row['id'] : 0;
            if ( $id <= 0 ) {
                continue;
            }
            $deleted = $wpdb->delete( $this->promotionAttributionTable, [ 'id' => $id ], [ '%d' ] );
            if ( is_numeric( $deleted ) && (int) $deleted > 0 ) {
                $count++;
            }
        }

        if ( $count > 0 ) {
            do_action( 'khm_membership_attribution_mutated', [
                'operation' => 'delete_expired',
                'deleted' => $count,
                'retention_days' => $retentionDays,
            ] );
        }

        return $count;
    }

    /**
     * Cursor-based retention delete path to avoid full-table scans during large cleanup runs.
     *
     * @return array{matched:int,deleted:int,ids:array<int,int>,last_id:int,cutoff:string}
     */
    public function deleteExpiredAttributionBatch( int $retentionDays = self::RETENTION_DEFAULT_DAYS, int $chunkSize = 500, int $lastSeenId = 0 ): array {
        if ( ! $this->hasPromotionAttributionTable ) {
            return [ 'matched' => 0, 'deleted' => 0, 'ids' => [], 'last_id' => $lastSeenId, 'cutoff' => '' ];
        }

        global $wpdb;
        $retentionDays = max( 1, $retentionDays );
        $chunkSize = max( 1, min( 5000, $chunkSize ) );
        $lastSeenId = max( 0, $lastSeenId );
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retentionDays * 86400 ) );
        $now = current_time( 'mysql', 1 );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$this->promotionAttributionTable}
                 WHERE id > %d
                   AND created_at < %s
                   AND (legal_hold_until IS NULL OR legal_hold_until < %s)
                 ORDER BY id ASC
                 LIMIT %d",
                $lastSeenId,
                $cutoff,
                $now,
                $chunkSize
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return [ 'matched' => 0, 'deleted' => 0, 'ids' => [], 'last_id' => $lastSeenId, 'cutoff' => $cutoff ];
        }

        $ids = [];
        foreach ( $rows as $row ) {
            $id = isset( $row['id'] ) ? (int) $row['id'] : 0;
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }

        $deleted = 0;
        foreach ( $ids as $id ) {
            $removed = $wpdb->delete( $this->promotionAttributionTable, [ 'id' => $id ], [ '%d' ] );
            if ( is_numeric( $removed ) && (int) $removed > 0 ) {
                $deleted++;
            }
        }

        if ( $deleted > 0 ) {
            do_action( 'khm_membership_attribution_mutated', [
                'operation' => 'delete_expired_batch',
                'deleted' => $deleted,
                'retention_days' => $retentionDays,
            ] );
        }

        return [
            'matched' => count( $ids ),
            'deleted' => $deleted,
            'ids' => $ids,
            'last_id' => (int) max( $ids ),
            'cutoff' => $cutoff,
        ];
    }

    /**
     * @return array<int,int>
     */
    private function findRetentionCandidateIds( string $cutoff, int $limit, int $afterId = 0 ): array {
        if ( ! $this->hasPromotionAttributionTable ) {
            return [];
        }

        global $wpdb;
        $limit = max( 1, min( 5000, $limit ) );
        $afterId = max( 0, $afterId );
        $now = current_time( 'mysql', 1 );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id
                 FROM {$this->promotionAttributionTable}
                 WHERE id > %d
                   AND created_at < %s
                   AND anonymized_at IS NULL
                   AND (legal_hold_until IS NULL OR legal_hold_until < %s)
                 ORDER BY id ASC
                 LIMIT %d",
                $afterId,
                $cutoff,
                $now,
                $limit
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return [];
        }

        $ids = [];
        foreach ( $rows as $row ) {
            $id = isset( $row['id'] ) ? (int) $row['id'] : 0;
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function buildReferenceHash( string $reference ): string {
        $salt = getenv( 'KHM_ANON_SALT' );
        if ( ! is_string( $salt ) || '' === trim( $salt ) ) {
            $salt = (string) apply_filters( 'khm_membership_anonymization_salt_fallback', 'khm-anon-default-salt' );
        }

        return hash( 'sha256', $salt . $reference );
    }

    /**
     * Format DateTime object to MySQL datetime string.
     */
    private function formatDateTime( $date ): string {
        if ( $date instanceof DateTime ) {
            return $date->format('Y-m-d H:i:s');
        }

        if ( is_string($date) ) {
            return gmdate('Y-m-d H:i:s', strtotime($date));
        }

        return current_time('mysql', true);
    }

    /**
     * Normalize lifecycle update fields for status changes.
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    private function normalizeLifecycleFields( array $fields ): array {
        $allowed = [
            'enddate',
            'grace_enddate',
            'paused_at',
            'pause_until',
            'status_reason',
        ];

        $normalized = [];

        foreach ( $fields as $key => $value ) {
            if ( ! in_array( $key, $allowed, true ) ) {
                continue;
            }

            if ( $value instanceof DateTimeInterface ) {
                $normalized[ $key ] = $value->format( 'Y-m-d H:i:s' );
                continue;
            }

            if ( is_string( $value ) ) {
                $trimmed = trim( $value );
                $normalized[ $key ] = $trimmed === '' ? null : $trimmed;
                continue;
            }

            if ( $value === null ) {
                $normalized[ $key ] = null;
                continue;
            }

            $normalized[ $key ] = $value;
        }

        return $normalized;
    }

    private function attributionSelectSql( string $userIdSql, bool $extended = false ): array {
        if ( ! $this->hasPromotionAttributionTable ) {
            $fields = [
                'NULL AS attribution_schedule_id',
                'NULL AS attribution_sponsor_id',
                'NULL AS attribution_utm_source',
                'NULL AS attribution_phase_at_click',
                'NULL AS attribution_conversion_type',
                'NULL AS attribution_created_at',
                'NULL AS attribution_schedule_title',
                'NULL AS attribution_sponsor_name',
            ];

            if ( $extended ) {
                $fields[] = 'NULL AS attribution_utm_medium';
                $fields[] = 'NULL AS attribution_utm_campaign';
                $fields[] = 'NULL AS attribution_utm_term';
                $fields[] = 'NULL AS attribution_utm_content';
                $fields[] = 'NULL AS attribution_reference_metadata';
            }

            return [
                'select' => implode( ', ', $fields ),
                'join'   => '',
            ];
        }

        $fields = [
            'pa.schedule_id AS attribution_schedule_id',
            'pa.sponsor_id AS attribution_sponsor_id',
            'pa.utm_source AS attribution_utm_source',
            'pa.phase_at_click AS attribution_phase_at_click',
            'pa.conversion_type AS attribution_conversion_type',
            'pa.created_at AS attribution_created_at',
        ];

        if ( $extended ) {
            $fields[] = 'pa.utm_medium AS attribution_utm_medium';
            $fields[] = 'pa.utm_campaign AS attribution_utm_campaign';
            $fields[] = 'pa.utm_term AS attribution_utm_term';
            $fields[] = 'pa.utm_content AS attribution_utm_content';
            $fields[] = 'pa.reference_metadata AS attribution_reference_metadata';
        }

        if ( $this->hasPostsTable ) {
            $fields[] = 'schedule_post.post_title AS attribution_schedule_title';
        } else {
            $fields[] = 'NULL AS attribution_schedule_title';
        }

        if ( $this->hasSponsorsTable ) {
            $fields[] = 'sponsor.name AS attribution_sponsor_name';
        } else {
            $fields[] = 'NULL AS attribution_sponsor_name';
        }

        $join = "LEFT JOIN {$this->promotionAttributionTable} pa
                 ON pa.id = (
                    SELECT pa2.id
                    FROM {$this->promotionAttributionTable} pa2
                    WHERE pa2.user_id = {$userIdSql}
                    ORDER BY pa2.created_at DESC, pa2.id DESC
                    LIMIT 1
                 )";

        if ( $this->hasPostsTable ) {
            $join .= " LEFT JOIN {$this->postsTable} schedule_post ON schedule_post.ID = pa.schedule_id";
        }

        if ( $this->hasSponsorsTable ) {
            $join .= " LEFT JOIN {$this->sponsorsTable} sponsor ON sponsor.id = pa.sponsor_id";
        }

        return [
            'select' => implode( ', ', $fields ),
            'join'   => $join,
        ];
    }

    private function tableExists( string $table ): bool {
        global $wpdb;

        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        return $found === $table;
    }

    /**
     * Recalculate all capabilities that should be granted to a user.
     *
     * @param int $userId
     * @return void
     */
    private function recalculateUserCapabilities( int $userId ): void {
        if ( $userId <= 0 ) {
            return;
        }

        $user = get_userdata( $userId );
        if ( ! $user instanceof \WP_User ) {
            return;
        }

        global $wpdb;

        $current = current_time( 'mysql', true );
        $levelIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT membership_id FROM {$this->tableName}
                 WHERE user_id = %d
                 AND status IN ('active','grace')
                 AND (enddate IS NULL OR enddate > %s)",
                $userId,
                $current
            )
        );

        $caps = [];
        foreach ( (array) $levelIds as $levelId ) {
            $levelId = (int) $levelId;
            if ( $levelId < 1 ) {
                continue;
            }

            $caps[] = $this->getLevelCapabilitySlug( $levelId );
            foreach ( $this->getCustomCapabilitiesForLevel( $levelId ) as $cap ) {
                $caps[] = $cap;
            }
        }

        $caps = array_values( array_unique( array_filter( $caps ) ) );
        $previous = get_user_meta( $userId, '_khm_managed_caps', true );
        if ( ! is_array( $previous ) ) {
            $previous = [];
        }

        $toAdd    = array_diff( $caps, $previous );
        $toRemove = array_diff( $previous, $caps );

        foreach ( $toAdd as $cap ) {
            $user->add_cap( $cap );
        }

        foreach ( $toRemove as $cap ) {
            if ( $cap ) {
                $user->remove_cap( $cap );
            }
        }

        update_user_meta( $userId, '_khm_managed_caps', $caps );
    }

    private function getLevelCapabilitySlug( int $levelId ): string {
        return sanitize_key( 'khm_level_' . $levelId );
    }

    /**
     * Retrieve the custom capabilities defined for a membership level.
     *
     * @param int $levelId
     * @return array<int,string>
     */
    private function getCustomCapabilitiesForLevel( int $levelId ): array {
        $raw = $this->levels->getMeta( $levelId, 'custom_capabilities', [] );

        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\r\n,]+/', $raw );
        }

        if ( ! is_array( $raw ) ) {
            return [];
        }

        $caps = [];
        foreach ( $raw as $cap ) {
            $cap = trim( (string) $cap );
            if ( $cap === '' ) {
                continue;
            }
            $caps[] = sanitize_key( $cap );
        }

        return array_values( array_unique( $caps ) );
    }
}
