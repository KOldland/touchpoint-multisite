<?php

namespace KHM\Services;

use KHM\Services\MembershipRepository;
use KHM\Services\LevelRepository;

/**
 * Credit System Service
 * 
 * Core business logic for KHM membership credit system:
 * - Monthly credit allocation based on membership levels
 * - Credit usage tracking and logging
 * - Bonus credit system for manual additions
 * - Automatic monthly resets
 */
class CreditService {

    private MembershipRepository $memberships;
    private LevelRepository $levels;
    public function __construct(
        MembershipRepository $memberships,
        LevelRepository $levels
    ) {
        $this->memberships = $memberships;
        $this->levels = $levels;
    }

    /**
     * Get user's current credit balance
     *
     * @param int $user_id
     * @return int
     */
    public function getUserCredits(int $user_id): int {
        global $wpdb;
        
        $table = $wpdb->prefix . 'khm_user_credits';
        
        $current_month = date('Y-m');
        
        $credits = $wpdb->get_var($wpdb->prepare(
            "SELECT current_balance FROM {$table} 
             WHERE user_id = %d AND allocation_month = %s",
            $user_id,
            $current_month
        ));

        if ($credits === null) {
            // No record for this month, try to create one
            if ($this->allocateMonthlyCredits($user_id)) {
                // Allocation succeeded, fetch the new balance
                $credits = $wpdb->get_var($wpdb->prepare(
                    "SELECT current_balance FROM {$table} 
                     WHERE user_id = %d AND allocation_month = %s",
                    $user_id,
                    $current_month
                ));
                return (int) ($credits ?? 0);
            }
            // User has no active membership, return 0
            return 0;
        }

        return (int) $credits;
    }

    /**
     * Use credits for a specific purpose
     *
     * @param int $user_id
     * @param int $amount
     * @param string $purpose
     * @param int|null $object_id Related object (article, order, etc.)
     * @return bool Success
     */
    public function useCredits(int $user_id, int $amount, string $purpose, ?int $object_id = null): bool {
        global $wpdb;
        
        $current_balance = $this->getUserCredits($user_id);
        
        if ($current_balance < $amount) {
            return false;
        }

        $wpdb->query('START TRANSACTION');

        try {
            // Update balance
            $credits_table = $wpdb->prefix . 'khm_user_credits';
            $usage_table = $wpdb->prefix . 'khm_credit_usage';
            $current_month = date('Y-m');

            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$credits_table} 
                 SET current_balance = current_balance - %d,
                     total_used = total_used + %d,
                     updated_at = %s
                 WHERE user_id = %d AND allocation_month = %s",
                $amount,
                $amount,
                current_time('mysql'),
                $user_id,
                $current_month
            ));

            if ($updated === false) {
                throw new \Exception('Failed to update credit balance');
            }

            // Log usage
            $logged = $wpdb->insert(
                $usage_table,
                [
                    'user_id' => $user_id,
                    'credits_used' => $amount,
                    'purpose' => $purpose,
                    'object_id' => $object_id,
                    'balance_before' => $current_balance,
                    'balance_after' => $current_balance - $amount,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%d', '%d', '%d', '%s']
            );

            if ($logged === false) {
                throw new \Exception('Failed to log credit usage');
            }

            $wpdb->query('COMMIT');

            // Fire action hook for other plugins
            do_action('khm_credits_used', $user_id, $amount, $purpose, $object_id);

            return true;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Credit usage failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add bonus credits to a user
     *
     * @param int $user_id
     * @param int $amount
     * @param string $reason
     * @return bool
     */
    public function addBonusCredits(int $user_id, int $amount, string $reason = 'manual'): bool {
        global $wpdb;
        
        $credits_table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');
        
        // Ensure user has a credit record for this month
        $this->allocateMonthlyCredits($user_id);
        
        $current_balance = $this->getUserCredits($user_id);
        
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$credits_table} 
             SET current_balance = current_balance + %d,
                 bonus_credits = bonus_credits + %d,
                 updated_at = %s
             WHERE user_id = %d AND allocation_month = %s",
            $amount,
            $amount,
            current_time('mysql'),
            $user_id,
            $current_month
        ));

        if ($updated !== false) {
            // Log the bonus addition
            do_action('khm_bonus_credits_added', $user_id, $amount, $reason);
            return true;
        }

        return false;
    }

    /**
     * Allocate monthly credits based on membership level
     *
     * @param int $user_id
     * @return bool
     */
    public function allocateMonthlyCredits(int $user_id): bool {
        global $wpdb;
        
        $membership = $this->memberships->findActive($user_id);
        
        if (empty($membership)) {
            return false;
        }

        // Get the first active membership
        $membership = $membership[0];
        $level = $this->levels->get($membership->membership_id);
        $monthly_credits = $level->monthly_credits ?? 0;
        
        $credits_table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');
        
        // Check if allocation already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$credits_table} 
             WHERE user_id = %d AND allocation_month = %s",
            $user_id,
            $current_month
        ));

        if ($existing) {
            return true; // Already allocated
        }

        // Create new allocation
        $inserted = $wpdb->insert(
            $credits_table,
            [
                'user_id' => $user_id,
                'membership_level_id' => $membership->membership_id,
                'allocation_month' => $current_month,
                'allocated_credits' => $monthly_credits,
                'current_balance' => $monthly_credits,
                'total_used' => 0,
                'bonus_credits' => 0,
                'credit_period_start' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );

        if ($inserted !== false) {
            do_action('khm_monthly_credits_allocated', $user_id, $monthly_credits, $current_month);
            return true;
        }

        return false;
    }

    /**
     * Allocate initial credits when a user is enrolled in a membership level.
     * This will ADD credits to the user's existing balance (additive behavior for upgrades).
     *
     * @param int $user_id
     * @param int $level_id
     * @return int The number of credits allocated (0 if none)
     */
    public function allocateEnrollmentCredits(int $user_id, int $level_id): int {
        global $wpdb;

        $level = $this->levels->get($level_id, true);
        if (!$level) {
            return 0;
        }

        $monthly_credits = (int) ($level->meta['monthly_credits'] ?? 0);
        if ($monthly_credits <= 0) {
            return 0; // No credits to allocate
        }

        $credits_table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        // Check if allocation already exists for this month
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, allocated_credits, current_balance FROM {$credits_table} 
             WHERE user_id = %d AND allocation_month = %s",
            $user_id,
            $current_month
        ));

        if ($existing) {
            // Record exists - add credits to existing balance (additive for upgrades)
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$credits_table} 
                 SET allocated_credits = allocated_credits + %d,
                     current_balance = current_balance + %d,
                     membership_level_id = %d,
                     updated_at = %s
                 WHERE id = %d",
                $monthly_credits,
                $monthly_credits,
                $level_id,
                current_time('mysql'),
                $existing->id
            ));

            if ($updated !== false) {
                do_action('khm_enrollment_credits_allocated', $user_id, $monthly_credits, $level_id);
                return $monthly_credits;
            }
            return 0;
        }

        // No record exists - create new allocation
        $inserted = $wpdb->insert(
            $credits_table,
            [
                'user_id' => $user_id,
                'membership_level_id' => $level_id,
                'allocation_month' => $current_month,
                'allocated_credits' => $monthly_credits,
                'current_balance' => $monthly_credits,
                'total_used' => 0,
                'bonus_credits' => 0,
                'credit_period_start' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );

        if ($inserted !== false) {
            do_action('khm_enrollment_credits_allocated', $user_id, $monthly_credits, $level_id);
            return $monthly_credits;
        }

        return 0;
    }

    /**
     * Get credit usage history for a user
     *
     * @param int $user_id
     * @param int $limit
     * @return array
     */
    public function getCreditHistory(int $user_id, int $limit = 20): array {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'khm_credit_usage';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$usage_table} 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
    }

    /**
     * Get credit history count for a user
     *
     * @param int $user_id
     * @return int
     */
    public function getCreditHistoryCount(int $user_id): int {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'khm_credit_usage';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$usage_table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Process monthly credit resets for all users
     * Should be called via cron job
     *
     * @return int Number of users processed
     */
    public function processMonthlyResets(): int {
        global $wpdb;
        
        $processed = 0;
        
        // Get all users with active memberships
        $users_table = $wpdb->prefix . 'khm_memberships_users';
        $user_ids = $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$users_table} 
             WHERE status = 'active'"
        );
        
        foreach ($user_ids as $user_id) {
            if ($this->allocateMonthlyCredits($user_id)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Refund credits (e.g., for failed downloads)
     *
     * @param int $user_id
     * @param int $amount
     * @param string $reason
     * @return bool
     */
    public function refundCredits(int $user_id, int $amount, string $reason): bool {
        return $this->addBonusCredits($user_id, $amount, "refund: {$reason}");
    }

    /**
     * Get credit statistics for admin dashboard
     *
     * @return array
     */
    public function getCreditStats(): array {
        global $wpdb;
        
        $credits_table = $wpdb->prefix . 'khm_user_credits';
        $usage_table = $wpdb->prefix . 'khm_credit_usage';
        $current_month = date('Y-m');
        
        return [
            'total_allocated_this_month' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(allocated_credits) FROM {$credits_table} 
                 WHERE allocation_month = %s",
                $current_month
            )),
            'total_used_this_month' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_used) FROM {$credits_table} 
                 WHERE allocation_month = %s",
                $current_month
            )),
            'total_bonus_this_month' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(bonus_credits) FROM {$credits_table} 
                 WHERE allocation_month = %s",
                $current_month
            )),
            'active_users_with_credits' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$credits_table} 
                 WHERE allocation_month = %s AND current_balance > 0",
                $current_month
            ))
        ];
    }

    /**
     * Create database tables for credit system
     */
    public static function createTables(): void {
        global $wpdb;

        $credits_table = $wpdb->prefix . 'khm_user_credits';
        $usage_table = $wpdb->prefix . 'khm_credit_usage';

        $charset_collate = $wpdb->get_charset_collate();

        // Credits allocation table
        $credits_sql = "CREATE TABLE IF NOT EXISTS {$credits_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            membership_level_id bigint(20) unsigned NOT NULL,
            allocation_month varchar(7) NOT NULL,
            allocated_credits int(11) NOT NULL DEFAULT 0,
            current_balance int(11) NOT NULL DEFAULT 0,
            total_used int(11) NOT NULL DEFAULT 0,
            bonus_credits int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_month (user_id, allocation_month),
            KEY idx_user_id (user_id),
            KEY idx_allocation_month (allocation_month),
            KEY idx_membership_level (membership_level_id)
        ) {$charset_collate};";

        // Usage history table
        $usage_sql = "CREATE TABLE IF NOT EXISTS {$usage_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            credits_used int(11) NOT NULL,
            purpose varchar(50) NOT NULL,
            object_id bigint(20) unsigned NULL,
            balance_before int(11) NOT NULL,
            balance_after int(11) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_purpose (purpose),
            KEY idx_created_at (created_at),
            KEY idx_object_id (object_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($credits_sql);
        dbDelta($usage_sql);
    }

    /**
     * Reset the credit period start date for a user.
     * Called when membership level changes to restart the 30-day clock.
     *
     * @param int $user_id
     * @return bool
     */
    public function resetCreditPeriod(int $user_id): bool {
        global $wpdb;

        $credits_table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        $updated = $wpdb->update(
            $credits_table,
            [
                'credit_period_start' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            [
                'user_id' => $user_id,
                'allocation_month' => $current_month,
            ],
            ['%s', '%s'],
            ['%d', '%s']
        );

        return $updated !== false;
    }

    /**
     * Process credit expiration for all free account users.
     * Called by daily cron job. Resets credits for free accounts
     * where 30 days have passed since credit_period_start.
     *
     * @return array Stats about processed users
     */
    public function processExpiredCredits(): array {
        global $wpdb;

        $credits_table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        $stats = [
            'processed' => 0,
            'expired' => 0,
            'skipped_paid' => 0,
            'skipped_no_period' => 0,
        ];

        // Get all credit records for current month where period has expired
        $expired_records = $wpdb->get_results($wpdb->prepare(
            "SELECT uc.*, uc.id as credit_id 
             FROM {$credits_table} uc
             WHERE uc.allocation_month = %s 
               AND uc.credit_period_start IS NOT NULL 
               AND uc.credit_period_start <= %s",
            $current_month,
            $thirty_days_ago
        ));

        foreach ($expired_records as $record) {
            $stats['processed']++;

            // Check if user's current level is paid
            $user_id = (int) $record->user_id;
            $level_id = (int) $record->membership_level_id;

            if ($this->levels->isPaidLevel($level_id)) {
                // Paid accounts don't expire - credits roll over
                $stats['skipped_paid']++;
                continue;
            }

            // Free account - reset credits to level's monthly allocation
            $level = $this->levels->get($level_id, true);
            $monthly_credits = (int) ($level->meta['monthly_credits'] ?? 0);

            $wpdb->update(
                $credits_table,
                [
                    'current_balance' => $monthly_credits,
                    'allocated_credits' => $monthly_credits,
                    'bonus_credits' => 0,
                    'total_used' => 0,
                    'credit_period_start' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $record->credit_id],
                ['%d', '%d', '%d', '%d', '%s', '%s'],
                ['%d']
            );

            $stats['expired']++;

            do_action('khm_credits_expired', $user_id, $level_id, (int) $record->current_balance);
        }

        // Also handle records without a period start - set it now
        $no_period_records = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$credits_table} 
             WHERE allocation_month = %s 
               AND credit_period_start IS NULL",
            $current_month
        ));

        foreach ($no_period_records as $record) {
            $wpdb->update(
                $credits_table,
                ['credit_period_start' => current_time('mysql')],
                ['id' => $record->id],
                ['%s'],
                ['%d']
            );
            $stats['skipped_no_period']++;
        }

        return $stats;
    }

    /**
     * Get the credit period start date for a user.
     *
     * @param int $user_id
     * @return string|null DateTime string or null
     */
    public function getCreditPeriodStart(int $user_id): ?string {
        global $wpdb;

        $credits_table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        return $wpdb->get_var($wpdb->prepare(
            "SELECT credit_period_start FROM {$credits_table} 
             WHERE user_id = %d AND allocation_month = %s",
            $user_id,
            $current_month
        ));
    }

    // -------------------------------------------------------------------------
    // Quote Club editorial & press-release credit methods
    // These operate on the extra columns added by AddEditorialCredits migration:
    // editorial_allocated_credits, editorial_bonus_credits, editorial_allocation_month,
    // press_release_credits, press_release_credits_used
    // -------------------------------------------------------------------------

    /**
     * Get user's current editorial credit balance (Quote Club).
     * Auto-allocates this month's quota if not yet done.
     */
    public function getEditorialCredits(int $user_id): int {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT editorial_allocated_credits, editorial_bonus_credits, editorial_allocation_month
             FROM {$table} WHERE user_id = %d AND allocation_month = %s LIMIT 1",
            $user_id, $current_month
        ));

        if (!$row || $row->editorial_allocation_month !== $current_month) {
            if ($this->allocateMonthlyEditorialCredits($user_id)) {
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT editorial_allocated_credits, editorial_bonus_credits
                     FROM {$table} WHERE user_id = %d AND allocation_month = %s LIMIT 1",
                    $user_id, $current_month
                ));
            }
        }

        if (!$row) {
            return 0;
        }

        return max(0, (int) $row->editorial_allocated_credits + (int) $row->editorial_bonus_credits);
    }

    /**
     * Allocate monthly editorial credits for a user based on their Quote Club tier.
     * Reads quota from level meta key 'qc_editorial_credits_monthly'.
     */
    public function allocateMonthlyEditorialCredits(int $user_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, editorial_allocation_month FROM {$table}
             WHERE user_id = %d AND allocation_month = %s LIMIT 1",
            $user_id, $current_month
        ));

        if ($existing && $existing->editorial_allocation_month === $current_month) {
            return true;
        }

        $membership = $this->memberships->findActive($user_id);
        if (empty($membership)) {
            return false;
        }

        $level_id = (int) $membership[0]->membership_id;
        $quota = (int) $this->levels->getMeta($level_id, 'qc_editorial_credits_monthly', 0);
        $quota = (int) apply_filters('khm_editorial_credits_monthly_quota', $quota, $user_id);

        if ($existing) {
            $wpdb->update(
                $table,
                [
                    'editorial_allocated_credits' => $quota,
                    'editorial_allocation_month'  => $current_month,
                    'updated_at'                  => current_time('mysql'),
                ],
                ['id' => (int) $existing->id],
                ['%d', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'user_id'                     => $user_id,
                    'membership_level_id'         => $level_id,
                    'allocation_month'            => $current_month,
                    'allocated_credits'           => 0,
                    'current_balance'             => 0,
                    'total_used'                  => 0,
                    'bonus_credits'               => 0,
                    'editorial_allocated_credits' => $quota,
                    'editorial_bonus_credits'     => 0,
                    'editorial_allocation_month'  => $current_month,
                    'press_release_credits'       => 1,
                    'press_release_credits_used'  => 0,
                    'credit_period_start'         => current_time('mysql'),
                    'created_at'                  => current_time('mysql'),
                    'updated_at'                  => current_time('mysql'),
                ],
                ['%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s']
            );
        }

        do_action('khm_editorial_credits_allocated', $user_id, $quota, $current_month);
        return true;
    }

    /**
     * Consume editorial credits for a Quote Club commentary submission.
     * Deducts from editorial_allocated_credits first, then editorial_bonus_credits.
     */
    public function useEditorialCredits(int $user_id, int $amount, string $purpose = 'quote_club_commentary', ?int $object_id = null): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        if ($this->getEditorialCredits($user_id) < $amount) {
            return false;
        }

        $wpdb->query('START TRANSACTION');
        try {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, editorial_allocated_credits, editorial_bonus_credits
                 FROM {$table} WHERE user_id = %d AND allocation_month = %s LIMIT 1 FOR UPDATE",
                $user_id, $current_month
            ));

            if (!$row || ((int) $row->editorial_allocated_credits + (int) $row->editorial_bonus_credits) < $amount) {
                $wpdb->query('ROLLBACK');
                return false;
            }

            $deduct_alloc = min($amount, (int) $row->editorial_allocated_credits);
            $deduct_bonus = $amount - $deduct_alloc;

            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$table}
                 SET editorial_allocated_credits = editorial_allocated_credits - %d,
                     editorial_bonus_credits     = editorial_bonus_credits - %d,
                     updated_at                  = %s
                 WHERE id = %d",
                $deduct_alloc,
                $deduct_bonus,
                current_time('mysql'),
                (int) $row->id
            ));

            if ($updated === false) {
                throw new \Exception('Failed to deduct editorial credits');
            }

            $wpdb->query('COMMIT');
            do_action('khm_editorial_credits_used', $user_id, $amount, $purpose, $object_id);
            return true;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[KHM QC] Editorial credit deduction failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add bonus editorial credits to a user (e.g. admin grant, bundle purchase).
     */
    public function addEditorialBonusCredits(int $user_id, int $amount, string $reason = 'manual'): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        $this->allocateMonthlyEditorialCredits($user_id);

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET editorial_bonus_credits = editorial_bonus_credits + %d,
                 updated_at              = %s
             WHERE user_id = %d AND allocation_month = %s",
            $amount,
            current_time('mysql'),
            $user_id,
            $current_month
        ));

        if ($updated !== false) {
            do_action('khm_editorial_bonus_credits_added', $user_id, $amount, $reason);
            return true;
        }

        return false;
    }

    /**
     * Get user's remaining press release credits.
     */
    public function getPressReleaseCredits(int $user_id): int {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT press_release_credits, press_release_credits_used
             FROM {$table} WHERE user_id = %d AND allocation_month = %s LIMIT 1",
            $user_id, $current_month
        ));

        if (!$row) {
            return 0;
        }

        return max(0, (int) $row->press_release_credits - (int) $row->press_release_credits_used);
    }

    /**
     * Consume one press release credit.
     */
    public function usePressReleaseCredit(int $user_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        if ($this->getPressReleaseCredits($user_id) < 1) {
            return false;
        }

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET press_release_credits_used = press_release_credits_used + 1,
                 updated_at                 = %s
             WHERE user_id = %d AND allocation_month = %s
               AND (press_release_credits - press_release_credits_used) >= 1",
            current_time('mysql'),
            $user_id,
            $current_month
        ));

        if ($updated) {
            do_action('khm_press_release_credit_used', $user_id);
            return true;
        }

        return false;
    }

    /**
     * Refund one press release credit (e.g. editorial rejection).
     */
    public function refundPressReleaseCredit(int $user_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_user_credits';
        $current_month = date('Y-m');

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET press_release_credits_used = GREATEST(0, press_release_credits_used - 1),
                 updated_at                 = %s
             WHERE user_id = %d AND allocation_month = %s",
            current_time('mysql'),
            $user_id,
            $current_month
        ));

        return $updated !== false;
    }

    /**
     * Get days remaining until credits expire for a free account user.
     *
     * @param int $user_id
     * @return int|null Days remaining, or null if paid account or no data
     */
    public function getDaysUntilExpiry(int $user_id): ?int {
        // Get user's current membership level
        $memberships = $this->memberships->findActive($user_id);
        if (empty($memberships)) {
            return null;
        }

        $level_id = (int) $memberships[0]->membership_id;

        // Paid accounts don't expire
        if ($this->levels->isPaidLevel($level_id)) {
            return null;
        }

        $period_start = $this->getCreditPeriodStart($user_id);
        if (!$period_start) {
            return 30; // No period set yet, assume full 30 days
        }

        $start_timestamp = strtotime($period_start);
        $expiry_timestamp = $start_timestamp + (30 * DAY_IN_SECONDS);
        $now = time();

        $days_remaining = ceil(($expiry_timestamp - $now) / DAY_IN_SECONDS);

        return max(0, (int) $days_remaining);
    }

    /**
     * Get transaction ledger for a user
     *
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getTransactionLedger(int $user_id, int $limit = 20, int $offset = 0): array {
        global $wpdb;
        $transactions = [];

        // 1. Credit Usage
        $usage_table = $wpdb->prefix . 'khm_credit_usage';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$usage_table}'") === $usage_table) {
            $usage = $wpdb->get_results($wpdb->prepare(
                "SELECT credits_used, purpose, object_id, created_at
                 FROM {$usage_table}
                 WHERE user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            ));

            foreach ($usage as $row) {
                $credits = (int) $row->credits_used;
                if ($credits === 0) {
                    continue;
                }

                $label = $this->format_credit_reason($row->purpose ?? '', $row->object_id ?? 0);
                $amount_display = '-' . abs($credits) . ' credits';
                $transactions[] = [
                    'date' => $row->created_at,
                    'description' => $label,
                    'amount_display' => $amount_display,
                    'amount_class' => 'khm-credit-negative',
                ];
            }
        }

        // 2. Purchases (ECommerce)
        $purchases_table = $wpdb->prefix . 'khm_purchases';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'") === $purchases_table) {
            $purchases = $wpdb->get_results($wpdb->prepare(
                "SELECT pr.post_id, pr.purchase_price, pr.created_at, p.post_title
                 FROM {$purchases_table} pr
                 LEFT JOIN {$wpdb->posts} p ON pr.post_id = p.ID
                 WHERE pr.user_id = %d AND pr.status = 'completed'
                 ORDER BY pr.created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            ));

            foreach ($purchases as $purchase) {
                $price = (float) ($purchase->purchase_price ?? 0);
                $transactions[] = [
                    'date' => $purchase->created_at,
                    'description' => sprintf(
                        __('Purchased: %s', 'khm-membership'),
                        $purchase->post_title ?: __('Article', 'khm-membership')
                    ),
                    'amount_display' => '-' . $this->format_price($price),
                    'amount_class' => 'khm-credit-negative',
                ];
            }
        }

        // 3. Gifts Sent
        $gifts_table = $wpdb->prefix . 'khm_gifts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$gifts_table}'") === $gifts_table) {
            $gifts = $wpdb->get_results($wpdb->prepare(
                "SELECT g.post_id, g.gift_price, g.recipient_email, g.created_at, p.post_title
                 FROM {$gifts_table} g
                 LEFT JOIN {$wpdb->posts} p ON g.post_id = p.ID
                 WHERE g.sender_id = %d AND g.status IN ('sent', 'redeemed')
                 ORDER BY g.created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            ));

            foreach ($gifts as $gift) {
                $price = (float) ($gift->gift_price ?? 0);
                $recipient = $gift->recipient_email ? sprintf(' (%s)', $gift->recipient_email) : '';
                $transactions[] = [
                    'date' => $gift->created_at,
                    'description' => sprintf(
                        __('Gift sent: %s', 'khm-membership'),
                        ($gift->post_title ?: __('Article', 'khm-membership')) . $recipient
                    ),
                    'amount_display' => '-' . $this->format_price($price),
                    'amount_class' => 'khm-credit-negative',
                ];
            }
        }

        usort($transactions, function($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
        });

        return array_slice($transactions, 0, $limit);
    }

    /**
     * Get transaction ledger total count for a user
     *
     * @param int $user_id
     * @return int
     */
    public function getTransactionLedgerTotal(int $user_id): int {
        global $wpdb;
        $total = 0;

        $usage_table = $wpdb->prefix . 'khm_credit_usage';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$usage_table}'") === $usage_table) {
            $total += (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$usage_table} WHERE user_id = %d",
                $user_id
            ));
        }

        $purchases_table = $wpdb->prefix . 'khm_purchases';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'") === $purchases_table) {
            $total += (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$purchases_table} WHERE user_id = %d AND status = 'completed'",
                $user_id
            ));
        }

        $gifts_table = $wpdb->prefix . 'khm_gifts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$gifts_table}'") === $gifts_table) {
            $total += (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$gifts_table} WHERE sender_id = %d AND status IN ('sent', 'redeemed')",
                $user_id
            ));
        }

        return $total;
    }

    private function format_credit_reason(string $purpose, int $object_id = 0): string {
        $purpose = trim($purpose);
        if ($purpose === 'article_download' && $object_id) {
            $title = get_the_title($object_id);
            if ($title) {
                return sprintf(__('Downloaded: %s', 'khm-membership'), $title);
            }
        }

        if ($purpose !== '') {
            return ucwords(str_replace('_', ' ', $purpose));
        }

        return __('Credit Usage', 'khm-membership');
    }

    private function format_price(float $amount): string {
        $currency = get_option('khm_currency', 'GBP');
        if (function_exists('khm_format_price')) {
            return khm_format_price($amount, $currency);
        }

        return number_format_i18n($amount, 2);
    }
}