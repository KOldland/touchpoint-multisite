<?php

namespace KHM\Elementor\Widgets;

use KHM\Services\CreditService;
use KHM\Services\LevelRepository;
use KHM\Services\MembershipRepository;

/**
 * Shared data and rendering helpers for Quote Club Elementor widgets.
 */
class QuoteClubWidgetSupport {

    private MembershipRepository $memberships;
    private LevelRepository $levels;
    private CreditService $credits;

    public function __construct() {
        $this->memberships = new MembershipRepository();
        $this->levels = new LevelRepository();
        $this->credits = new CreditService($this->memberships, $this->levels);
    }

    public function enqueue_assets(): void {
        $plugin_url = plugin_dir_url(dirname(dirname(__DIR__)));
        $plugin_path = plugin_dir_path(dirname(dirname(__DIR__)));

        $member_portal_css = $plugin_path . 'assets/css/member-portal.css';
        if (file_exists($member_portal_css)) {
            wp_enqueue_style(
                'khm-member-portal',
                $plugin_url . 'assets/css/member-portal.css',
                [],
                filemtime($member_portal_css)
            );
        }

        $member_portal_js = $plugin_path . 'assets/js/member-portal.js';
        if (file_exists($member_portal_js)) {
            wp_enqueue_script(
                'khm-member-portal',
                $plugin_url . 'assets/js/member-portal.js',
                ['jquery'],
                filemtime($member_portal_js),
                true
            );

            wp_localize_script('khm-member-portal', 'khmPortal', [
                'restUrl' => esc_url_raw(rest_url('khm/v1/portal/')),
                'downloadRestUrl' => esc_url_raw(rest_url('khm/v1/download/')),
                'restNonce' => wp_create_nonce('wp_rest'),
                'userId' => get_current_user_id(),
                'strings' => [
                    'loading' => __('Loading...', 'khm-membership'),
                    'error' => __('An error occurred. Please try again.', 'khm-membership'),
                    'saved' => __('Changes saved!', 'khm-membership'),
                    'confirm_pause' => __('Are you sure you want to pause your membership?', 'khm-membership'),
                    'confirm_cancel' => __('Are you sure you want to cancel your membership? You will retain access until the end of your billing period.', 'khm-membership'),
                    'confirm_remove' => __('Remove this article from your library?', 'khm-membership'),
                ],
            ]);
        }

        $quote_club_css = $plugin_path . 'assets/css/quote-club.css';
        if (file_exists($quote_club_css)) {
            wp_enqueue_style(
                'khm-quote-club',
                $plugin_url . 'assets/css/quote-club.css',
                ['khm-member-portal'],
                filemtime($quote_club_css)
            );
        }

        $quote_club_js = $plugin_path . 'assets/js/quote-club.js';
        if (file_exists($quote_club_js)) {
            wp_enqueue_script(
                'khm-quote-club',
                $plugin_url . 'assets/js/quote-club.js',
                ['jquery', 'khm-member-portal'],
                filemtime($quote_club_js),
                true
            );

            $current_user = wp_get_current_user();
            wp_localize_script('khm-quote-club', 'khmQuoteClub', [
                'restUrl' => esc_url_raw(rest_url('khm/v1/portal/quoteclub/')),
                'sponsorRestUrl' => esc_url_raw(rest_url('khm/v1/sponsor/')),
                'connectRestUrl' => esc_url_raw(rest_url('khm/v1/connect/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'userId' => get_current_user_id(),
                'currentUserName' => $current_user->exists() ? esc_html($current_user->display_name) : '',
                'currentUserEmail' => $current_user->exists() ? sanitize_email($current_user->user_email) : '',
                'editorialCredits' => $this->credits->getEditorialCredits(get_current_user_id()),
                'pressReleaseCredits' => $this->credits->getPressReleaseCredits(get_current_user_id()),
                'wordsPerCredit' => 120,
                'availableCategories' => $this->get_top_line_categories(),
                'inviteToken' => sanitize_text_field((string) ($_GET['khm_sponsor_invite'] ?? '')),
                'inviteEmail' => sanitize_email((string) ($_GET['khm_sponsor_invite_email'] ?? '')),
            ]);
        }
    }

    public function render_login_required(string $message): void {
        echo '<p class="khm-portal-login-required">' . esc_html($message) . '</p>';
    }

    public function get_portal_urls(): array {
        $quoteclub_portal_url = apply_filters('khm_quoteclub_portal_url', home_url('/quote-club/'));

        return [
            'quoteclub_portal_url' => $quoteclub_portal_url,
            'new_press_release_url' => add_query_arg('qc_section', 'press-releases', $quoteclub_portal_url),
            'buy_credits_url' => add_query_arg('qc_section', 'overview', $quoteclub_portal_url),
        ];
    }

    public function get_activity_context(int $user_id, int $default_per_page = 10): array {
        global $wpdb;

        $commentary_table = $wpdb->prefix . 'khm_sponsor_commentary';
        $press_release_table = $wpdb->prefix . 'khm_press_releases';
        $allowed_per_page = [10, 20, 50, 100];

        $activity_per_page = isset($_GET['qc_activity_per_page']) ? (int) $_GET['qc_activity_per_page'] : $default_per_page;
        if (!in_array($activity_per_page, $allowed_per_page, true)) {
            $activity_per_page = 10;
        }

        $activity_page = isset($_GET['qc_activity_page']) ? max(1, (int) $_GET['qc_activity_page']) : 1;
        $has_press_release_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $press_release_table)) === $press_release_table;

        if ($has_press_release_table) {
            $activity_total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM {$commentary_table} WHERE user_id = %d)
                    +
                    (SELECT COUNT(*) FROM {$press_release_table} WHERE user_id = %d)",
                $user_id,
                $user_id
            ));
        } else {
            $activity_total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$commentary_table} WHERE user_id = %d",
                $user_id
            ));
        }

        $activity_total_pages = max(1, (int) ceil($activity_total / max(1, $activity_per_page)));
        if ($activity_page > $activity_total_pages) {
            $activity_page = $activity_total_pages;
        }

        $activity_offset = ($activity_page - 1) * $activity_per_page;

        if ($has_press_release_table) {
            $recent = $wpdb->get_results($wpdb->prepare(
                "SELECT *
                 FROM (
                    SELECT
                        'commentary' AS activity_type,
                        id AS activity_id,
                        session_id AS activity_label,
                        status,
                        COALESCE(submitted_at, created_at) AS activity_at
                    FROM {$commentary_table}
                    WHERE user_id = %d

                    UNION ALL

                    SELECT
                        'press_release' AS activity_type,
                        id AS activity_id,
                        title AS activity_label,
                        status,
                        COALESCE(submission_date, published_date, updated_at, created_at) AS activity_at
                    FROM {$press_release_table}
                    WHERE user_id = %d
                 ) activity
                 ORDER BY activity_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $user_id,
                $activity_per_page,
                $activity_offset
            ), ARRAY_A);
        } else {
            $recent = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    'commentary' AS activity_type,
                    id AS activity_id,
                    session_id AS activity_label,
                    status,
                    COALESCE(submitted_at, created_at) AS activity_at
                 FROM {$commentary_table}
                 WHERE user_id = %d
                 ORDER BY activity_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $activity_per_page,
                $activity_offset
            ), ARRAY_A);
        }

        $pagination_base_url = remove_query_arg(['qc_activity_page', 'qc_activity_per_page']);
        $page_window_start = max(1, $activity_page - 2);
        $page_window_end = min($activity_total_pages, $page_window_start + 4);
        if (($page_window_end - $page_window_start) < 4) {
            $page_window_start = max(1, $page_window_end - 4);
        }

        return [
            'allowed_per_page' => $allowed_per_page,
            'activity_per_page' => $activity_per_page,
            'activity_page' => $activity_page,
            'activity_total_pages' => $activity_total_pages,
            'recent' => is_array($recent) ? $recent : [],
            'pagination_base_url' => $pagination_base_url,
            'page_window_start' => $page_window_start,
            'page_window_end' => $page_window_end,
        ];
    }

    public function get_stats_context(int $user_id): array {
        global $wpdb;

        $commentary_table = $wpdb->prefix . 'khm_sponsor_commentary';

        $my_drafts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$commentary_table} WHERE user_id = %d AND status = %s",
            $user_id,
            'draft'
        ));

        $pending_review = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$commentary_table} WHERE user_id = %d AND status = %s",
            $user_id,
            'pending_editorial'
        ));

        $published_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$commentary_table} WHERE user_id = %d AND status = %s",
            $user_id,
            'published'
        ));

        return [
            'editorial_credits' => $this->credits->getEditorialCredits($user_id),
            'press_release_credits' => $this->credits->getPressReleaseCredits($user_id),
            'my_drafts' => $my_drafts,
            'pending_review' => $pending_review,
            'published_count' => $published_count,
        ];
    }

    public function map_activity_status(string $status): array {
        switch ($status) {
            case 'pending_editorial':
                return ['label' => __('Awaiting Review', 'khm-membership'), 'class' => 'awaiting_review'];
            case 'submitted':
                return ['label' => __('Submitted', 'khm-membership'), 'class' => 'submitted'];
            case 'approved':
                return ['label' => __('Scheduled', 'khm-membership'), 'class' => 'scheduled'];
            case 'published':
                return ['label' => __('Live', 'khm-membership'), 'class' => 'live'];
            case 'draft':
                return ['label' => __('Draft', 'khm-membership'), 'class' => 'draft'];
            case 'rejected':
                return ['label' => __('Needs Revision', 'khm-membership'), 'class' => 'needs_revision'];
            default:
                return [
                    'label' => ucfirst(str_replace('_', ' ', $status)),
                    'class' => 'submitted',
                ];
        }
    }

    /**
     * Fetch top-line categories from the shared Dual GPT option.
     *
     * @return array<int, string>
     */
    public function get_top_line_categories(): array {
        $stored = get_option( 'dual_gpt_top_line_categories', null );
        if ( ! is_array( $stored ) || empty( $stored ) ) {
            return [];
        }
        $categories = [];
        foreach ( $stored as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
            if ( $name !== '' ) {
                $categories[] = $name;
            }
        }
        return array_values( array_unique( $categories ) );
    }
}
