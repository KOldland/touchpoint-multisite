<?php

namespace KHM\Services;

use KHM\Services\MembershipRepository;
use KHM\Services\CreditService;
use KHM\Services\LibraryService;

/**
 * Credit Download Service
 * 
 * Manages credit-based PDF downloads with the following business rules:
 * - Credit cost per article is variable (from kss_credit_cost post meta)
 * - First download costs credits
 * - Re-downloads are FREE while membership is active
 * - Re-downloads cost credits if membership expired
 * - Automatically adds downloaded articles to user's library
 */
class CreditDownloadService {

    private MembershipRepository $memberships;
    private CreditService $credits;
    private LibraryService $library;
    private string $downloads_table;

    public function __construct(
        MembershipRepository $memberships,
        CreditService $credits,
        LibraryService $library
    ) {
        global $wpdb;
        $this->memberships = $memberships;
        $this->credits = $credits;
        $this->library = $library;
        $this->downloads_table = $wpdb->prefix . 'khm_credit_downloads';
    }

    /**
     * Create database table for credit downloads
     */
    public function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->downloads_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            post_id int(11) NOT NULL,
            credits_used int(11) NOT NULL DEFAULT 0,
            download_count int(11) NOT NULL DEFAULT 1,
            first_download_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_download_at datetime DEFAULT CURRENT_TIMESTAMP,
            membership_active_at_purchase tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_post (user_id, post_id),
            KEY idx_user_id (user_id),
            KEY idx_post_id (post_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get the credit cost for an article
     * Reads from kss_credit_cost post meta, defaults to 0
     *
     * @param int $post_id
     * @return int
     */
    public function getArticleCreditCost(int $post_id): int {
        $cost = get_post_meta($post_id, 'kss_credit_cost', true);
        return $cost !== '' ? (int) $cost : 0;
    }

    /**
     * Check if user has previously downloaded this article with credits
     *
     * @param int $user_id
     * @param int $post_id
     * @return bool
     */
    public function hasDownloaded(int $user_id, int $post_id): bool {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->downloads_table} 
             WHERE user_id = %d AND post_id = %d",
            $user_id,
            $post_id
        ));

        return $count > 0;
    }

    /**
     * Get download record for user/article
     *
     * @param int $user_id
     * @param int $post_id
     * @return object|null
     */
    public function getDownloadRecord(int $user_id, int $post_id): ?object {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->downloads_table} 
             WHERE user_id = %d AND post_id = %d",
            $user_id,
            $post_id
        ));
    }

    /**
     * Check if user has an active membership
     *
     * @param int $user_id
     * @return bool
     */
    public function hasActiveMembership(int $user_id): bool {
        $memberships = $this->memberships->findActive($user_id);
        return !empty($memberships);
    }

    /**
     * Determine download eligibility and cost
     * 
     * Returns array with:
     * - can_download: bool
     * - is_free: bool (re-download with active membership)
     * - credits_required: int
     * - reason: string (explanation)
     * - user_credits: int
     *
     * @param int $user_id
     * @param int $post_id
     * @return array
     */
    public function checkDownloadEligibility(int $user_id, int $post_id): array {
        $has_active = $this->hasActiveMembership($user_id);
        $has_downloaded = $this->hasDownloaded($user_id, $post_id);
        $article_cost = $this->getArticleCreditCost($post_id);
        $user_credits = $this->credits->getUserCredits($user_id);

        // Must have membership to use credit system
        if (!$has_active && !$has_downloaded) {
            return [
                'can_download' => false,
                'is_free' => false,
                'credits_required' => $article_cost,
                'reason' => 'membership_required',
                'user_credits' => $user_credits
            ];
        }

        // Previously downloaded with active membership = FREE re-download
        if ($has_downloaded && $has_active) {
            return [
                'can_download' => true,
                'is_free' => true,
                'credits_required' => 0,
                'reason' => 're_download_free',
                'user_credits' => $user_credits
            ];
        }

        // Previously downloaded but membership expired = costs credits
        if ($has_downloaded && !$has_active) {
            if ($user_credits >= $article_cost) {
                return [
                    'can_download' => true,
                    'is_free' => false,
                    'credits_required' => $article_cost,
                    'reason' => 're_download_expired_membership',
                    'user_credits' => $user_credits
                ];
            } else {
                return [
                    'can_download' => false,
                    'is_free' => false,
                    'credits_required' => $article_cost,
                    'reason' => 'insufficient_credits',
                    'user_credits' => $user_credits
                ];
            }
        }

        // First download - check credits
        if ($user_credits >= $article_cost) {
            return [
                'can_download' => true,
                'is_free' => false,
                'credits_required' => $article_cost,
                'reason' => 'first_download',
                'user_credits' => $user_credits
            ];
        }

        return [
            'can_download' => false,
            'is_free' => false,
            'credits_required' => $article_cost,
            'reason' => 'insufficient_credits',
            'user_credits' => $user_credits
        ];
    }

    /**
     * Process a credit-based download
     * 
     * @param int $user_id
     * @param int $post_id
     * @return array ['success' => bool, 'error' => string, 'credits_used' => int, 'credits_remaining' => int]
     */
    public function processDownload(int $user_id, int $post_id): array {
        global $wpdb;

        $eligibility = $this->checkDownloadEligibility($user_id, $post_id);

        if (!$eligibility['can_download']) {
            return [
                'success' => false,
                'error' => $this->getErrorMessage($eligibility['reason']),
                'credits_used' => 0,
                'credits_remaining' => $eligibility['user_credits']
            ];
        }

        $credits_to_use = $eligibility['credits_required'];
        $has_downloaded = $this->hasDownloaded($user_id, $post_id);

        // Use credits if not free
        if (!$eligibility['is_free'] && $credits_to_use > 0) {
            $deducted = $this->credits->useCredits(
                $user_id,
                $credits_to_use,
                'article_download',
                $post_id
            );

            if (!$deducted) {
                return [
                    'success' => false,
                    'error' => 'Failed to deduct credits. Please try again.',
                    'credits_used' => 0,
                    'credits_remaining' => $eligibility['user_credits']
                ];
            }
        }

        // Record or update download
        if ($has_downloaded) {
            // Update existing record
            $wpdb->update(
                $this->downloads_table,
                [
                    'download_count' => new \stdClass(), // Will be replaced with raw SQL
                    'last_download_at' => current_time('mysql')
                ],
                [
                    'user_id' => $user_id,
                    'post_id' => $post_id
                ],
                ['%s', '%s'],
                ['%d', '%d']
            );

            // Use raw SQL for increment
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->downloads_table} 
                 SET download_count = download_count + 1, 
                     last_download_at = %s 
                 WHERE user_id = %d AND post_id = %d",
                current_time('mysql'),
                $user_id,
                $post_id
            ));
        } else {
            // Create new record
            $wpdb->insert(
                $this->downloads_table,
                [
                    'user_id' => $user_id,
                    'post_id' => $post_id,
                    'credits_used' => $credits_to_use,
                    'download_count' => 1,
                    'first_download_at' => current_time('mysql'),
                    'last_download_at' => current_time('mysql'),
                    'membership_active_at_purchase' => $this->hasActiveMembership($user_id) ? 1 : 0
                ],
                ['%d', '%d', '%d', '%d', '%s', '%s', '%d']
            );

            // Auto-add to library on first download
            $this->library->save_to_library($user_id, $post_id);
        }

        // Fire action hook
        do_action('khm_article_downloaded', $user_id, $post_id, $eligibility['is_free'], $credits_to_use);

        return [
            'success' => true,
            'error' => null,
            'credits_used' => $eligibility['is_free'] ? 0 : $credits_to_use,
            'credits_remaining' => $this->credits->getUserCredits($user_id),
            'is_free' => $eligibility['is_free']
        ];
    }

    /**
     * Get user's download history
     *
     * @param int $user_id
     * @param array $args
     * @return array
     */
    public function getUserDownloads(int $user_id, array $args = []): array {
        global $wpdb;

        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'last_download_at',
            'order' => 'DESC'
        ];

        $args = array_merge($defaults, $args);

        $order_clause = sprintf(
            "ORDER BY %s %s",
            sanitize_sql_orderby($args['orderby']),
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        $downloads = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, p.post_title, p.post_excerpt
             FROM {$this->downloads_table} d
             LEFT JOIN {$wpdb->posts} p ON d.post_id = p.ID
             WHERE d.user_id = %d
             {$order_clause}
             LIMIT %d OFFSET %d",
            $user_id,
            $args['limit'],
            $args['offset']
        ));

        return $downloads ?: [];
    }

    /**
     * Get download count for a user
     *
     * @param int $user_id
     * @return int
     */
    public function getUserDownloadCount(int $user_id): int {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->downloads_table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get human-readable error message
     *
     * @param string $reason
     * @return string
     */
    private function getErrorMessage(string $reason): string {
        $messages = [
            'membership_required' => __('An active membership is required to download articles with credits.', 'khm-membership'),
            'insufficient_credits' => __('You don\'t have enough credits for this download.', 'khm-membership'),
            're_download_expired_membership' => __('Your membership has expired. Credits are required to re-download.', 'khm-membership'),
        ];

        return $messages[$reason] ?? __('Unable to process download.', 'khm-membership');
    }

    /**
     * Get download statistics
     *
     * @return array
     */
    public function getDownloadStats(): array {
        global $wpdb;

        $total_downloads = $wpdb->get_var("SELECT SUM(download_count) FROM {$this->downloads_table}");
        $unique_downloads = $wpdb->get_var("SELECT COUNT(*) FROM {$this->downloads_table}");
        $total_credits_used = $wpdb->get_var("SELECT SUM(credits_used) FROM {$this->downloads_table}");
        $unique_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$this->downloads_table}");
        $unique_articles = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$this->downloads_table}");

        return [
            'total_downloads' => (int) $total_downloads,
            'unique_downloads' => (int) $unique_downloads,
            'total_credits_used' => (int) $total_credits_used,
            'unique_users' => (int) $unique_users,
            'unique_articles' => (int) $unique_articles,
            'average_downloads_per_user' => $unique_users > 0 ? round($total_downloads / $unique_users, 2) : 0
        ];
    }
}
