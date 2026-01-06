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
}