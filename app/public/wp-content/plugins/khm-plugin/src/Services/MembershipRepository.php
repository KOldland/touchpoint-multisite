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

    private string $tableName;
    private string $levelsTable;
    private string $usersTable;
    private LevelRepository $levels;

    public function __construct() {
        global $wpdb;
        $this->tableName = $wpdb->prefix . 'khm_memberships_users';
        $this->levelsTable = $wpdb->prefix . 'khm_membership_levels';
        $this->usersTable  = $wpdb->users;
        $this->levels = new LevelRepository();
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

        $membership = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT m.*, l.name AS level_name, u.user_login, u.user_email, u.display_name
                 FROM {$this->tableName} m
                 LEFT JOIN {$this->levelsTable} l ON m.membership_id = l.id
                 LEFT JOIN {$this->usersTable} u ON m.user_id = u.ID
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

        $whereSql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $orderMap = [
            'user'       => 'u.display_name',
            'email'      => 'u.user_email',
            'level'      => 'l.name',
            'start_date' => 'm.startdate',
            'end_date'   => 'm.enddate',
            'status'     => 'm.status',
        ];

        $orderBy = isset( $orderMap[ $args['orderby'] ] ) ? $orderMap[ $args['orderby'] ] : 'm.startdate';
        $order   = strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = max( 1, (int) $args['per_page'] );
        $offset = max( 0, (int) $args['offset'] );

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
                             l.name AS level_name
                      FROM {$this->tableName} m
                      LEFT JOIN {$this->levelsTable} l ON m.membership_id = l.id
                      LEFT JOIN {$this->usersTable} u ON m.user_id = u.ID
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
