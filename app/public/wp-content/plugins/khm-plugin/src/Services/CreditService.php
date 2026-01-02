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
            // No record for this month, create one
            $this->allocateMonthlyCredits($user_id);
            return $this->getUserCredits($user_id);
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
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s']
        );

        if ($inserted !== false) {
            do_action('khm_monthly_credits_allocated', $user_id, $monthly_credits, $current_month);
            return true;
        }

        return false;
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
}