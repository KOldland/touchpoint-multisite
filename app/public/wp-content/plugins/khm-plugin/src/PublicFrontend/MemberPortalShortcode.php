<?php

namespace KHM\PublicFrontend;

use KHM\Services\MembershipRepository;
use KHM\Services\CreditService;
use KHM\Services\LibraryService;
use KHM\Services\LevelRepository;
use KHM\Services\CreditDownloadService;

/**
 * Member Portal Shortcode
 * 
 * Provides [khm_member_portal] shortcode for unified member dashboard.
 * Combines dashboard, library, credits, downloads, and membership management.
 */
class MemberPortalShortcode {

    private MembershipRepository $memberships;
    private CreditService $credits;
    private LibraryService $library;
    private LevelRepository $levels;
    private CreditDownloadService $downloads;

    public function __construct() {
        $this->memberships = new MembershipRepository();
        $this->levels = new LevelRepository();
        $this->credits = new CreditService($this->memberships, $this->levels);
        $this->library = new LibraryService($this->memberships);
        $this->downloads = new CreditDownloadService($this->memberships, $this->credits, $this->library);
    }

    /**
     * Register shortcode and hooks
     */
    public function register(): void {
        add_shortcode('khm_member_portal', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue portal assets
     */
    public function enqueue_assets(): void {
        global $post;
        
        if (!$post || !has_shortcode($post->post_content, 'khm_member_portal')) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(__DIR__));
        $plugin_path = plugin_dir_path(dirname(__DIR__));

        // Enqueue CSS
        wp_enqueue_style(
            'khm-member-portal',
            $plugin_url . 'assets/css/member-portal.css',
            [],
            filemtime($plugin_path . 'assets/css/member-portal.css')
        );

        // Enqueue JS
        wp_enqueue_script(
            'khm-member-portal',
            $plugin_url . 'assets/js/member-portal.js',
            ['jquery'],
            filemtime($plugin_path . 'assets/js/member-portal.js'),
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

        wp_enqueue_style(
            'khm-quote-club',
            $plugin_url . 'assets/css/quote-club.css',
            ['khm-member-portal'],
            filemtime($plugin_path . 'assets/css/quote-club.css')
        );

        wp_enqueue_script(
            'khm-quote-club',
            $plugin_url . 'assets/js/quote-club.js',
            ['jquery', 'khm-member-portal'],
            filemtime($plugin_path . 'assets/js/quote-club.js'),
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

    /**
     * Render the member portal
     */
    public function render(array $atts = []): string {
        $atts = shortcode_atts([
            'tab' => 'dashboard',
        ], $atts);

        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }

        $user_id = get_current_user_id();
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : $atts['tab'];

        ob_start();
        ?>
        <div class="khm-portal" data-user-id="<?= esc_attr($user_id); ?>">
            
            <?php $this->render_header($user_id); ?>
            
            <div class="khm-portal-body">
                <?php $this->render_navigation($current_tab); ?>
                
                <div class="khm-portal-content">
                    <?php
                    switch ($current_tab) {
                        case 'library':
                            $this->render_library_tab($user_id);
                            break;
                        case 'credits':
                            $this->render_credits_tab($user_id);
                            break;
                        case 'downloads':
                            $this->render_downloads_tab($user_id);
                            break;
                        case 'membership':
                            $this->render_membership_tab($user_id);
                            break;
                        case 'account':
                            $this->render_account_tab($user_id);
                            break;
                        case 'quoteclub':
                            $this->render_quoteclub_tab($user_id);
                            break;
                        default:
                            $this->render_dashboard_tab($user_id);
                    }
                    ?>
                </div>
            </div>
            
            <!-- Toast notifications -->
            <div id="khm-portal-toast" class="khm-toast"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login required message
     */
    private function render_login_required(): string {
        $login_url = wp_login_url(get_permalink());
        
        ob_start();
        ?>
        <div class="khm-portal-login-required">
            <div class="khm-portal-login-box">
                <h2><?php esc_html_e('Member Portal', 'khm-membership'); ?></h2>
                <p><?php esc_html_e('Please log in to access your member portal.', 'khm-membership'); ?></p>
                <a href="<?= esc_url($login_url); ?>" class="khm-btn khm-btn-primary">
                    <?php esc_html_e('Log In', 'khm-membership'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render portal header with user info
     */
    private function render_header(int $user_id): void {
        $user = get_userdata($user_id);
        $memberships = $this->memberships->findActive($user_id);
        $membership = !empty($memberships) ? $memberships[0] : null;
        $level = ($membership && !empty($membership->level_id)) ? $this->levels->get((int) $membership->level_id) : null;
        $credits = $this->credits->getUserCredits($user_id);
        ?>
        <div class="khm-portal-header">
            <div class="khm-portal-user">
                <div class="khm-portal-avatar">
                    <?= get_avatar($user_id, 64); ?>
                </div>
                <div class="khm-portal-user-info">
                    <h1 class="khm-portal-welcome">
                        <?php printf(esc_html__('Welcome, %s', 'khm-membership'), esc_html($user->display_name)); ?>
                    </h1>
                    <p class="khm-portal-level">
                        <?php if ($level): ?>
                            <span class="khm-badge khm-badge-primary"><?= esc_html($level->name); ?></span>
                        <?php else: ?>
                            <span class="khm-badge khm-badge-secondary"><?php esc_html_e('No active membership', 'khm-membership'); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="khm-portal-stats">
                <div class="khm-stat-box">
                    <span class="khm-stat-value" id="credit-balance"><?= esc_html($credits); ?></span>
                    <span class="khm-stat-label"><?php esc_html_e('Credits', 'khm-membership'); ?></span>
                </div>
                <?php if ($membership && $membership->expires_at): ?>
                <div class="khm-stat-box">
                    <span class="khm-stat-value"><?= esc_html(date('M j', strtotime($membership->expires_at))); ?></span>
                    <span class="khm-stat-label"><?php esc_html_e('Next Renewal', 'khm-membership'); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render navigation tabs
     */
    private function render_navigation(string $current_tab): void {
        $tabs = [
            'dashboard' => ['label' => __('Dashboard', 'khm-membership'), 'icon' => 'dashicons-dashboard'],
            'library' => ['label' => __('My Library', 'khm-membership'), 'icon' => 'dashicons-book-alt'],
            'downloads' => ['label' => __('Downloads', 'khm-membership'), 'icon' => 'dashicons-download'],
            'credits' => ['label' => __('Credits', 'khm-membership'), 'icon' => 'dashicons-star-filled'],
            'membership' => ['label' => __('Membership', 'khm-membership'), 'icon' => 'dashicons-id'],
            'account' => ['label' => __('Account', 'khm-membership'), 'icon' => 'dashicons-admin-users'],
            'quoteclub' => ['label' => __('Quote Club', 'khm-membership'), 'icon' => 'dashicons-format-quote'],
        ];

        $tabs = apply_filters('khm_portal_tabs', $tabs);
        ?>
        <nav class="khm-portal-nav">
            <?php foreach ($tabs as $slug => $tab): ?>
                <a href="<?= esc_url(add_query_arg('tab', $slug)); ?>" 
                   class="khm-portal-nav-item<?= $current_tab === $slug ? ' active' : ''; ?>">
                    <span class="dashicons <?= esc_attr($tab['icon']); ?>"></span>
                    <span class="khm-nav-label"><?= esc_html($tab['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * Render Quote Club tab
     */
    private function render_quoteclub_tab(int $user_id): void {
        global $wpdb;

        $commentary_table = $wpdb->prefix . 'khm_sponsor_commentary';
        $press_release_table = $wpdb->prefix . 'khm_press_releases';
        $editorial_credits = $this->credits->getEditorialCredits($user_id);
        $press_release_credits = $this->credits->getPressReleaseCredits($user_id);
        $quoteclub_portal_url = apply_filters('khm_quoteclub_portal_url', home_url('/quote-club/'));

        $allowed_per_page = [10, 20, 50, 100];
        $activity_per_page = isset($_GET['qc_activity_per_page']) ? (int) $_GET['qc_activity_per_page'] : 10;
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

        $activity_total_pages = max(1, (int) ceil($activity_total / $activity_per_page));
        if ($activity_page > $activity_total_pages) {
            $activity_page = $activity_total_pages;
        }

        $activity_offset = ($activity_page - 1) * $activity_per_page;

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
        $new_press_release_url = add_query_arg('qc_section', 'press-releases', $quoteclub_portal_url);
        $buy_credits_url = add_query_arg('qc_section', 'overview', $quoteclub_portal_url);
        $page_window_start = max(1, $activity_page - 2);
        $page_window_end = min($activity_total_pages, $page_window_start + 4);
        if (($page_window_end - $page_window_start) < 4) {
            $page_window_start = max(1, $page_window_end - 4);
        }

        ?>
        <div class="khm-portal-tab khm-quoteclub" data-user-id="<?= esc_attr($user_id); ?>">
            <div class="khm-quoteclub-invite-status" role="status" aria-live="polite"></div>

            <section class="khm-qc-member-dashboard" aria-label="<?php esc_attr_e('Quote Club member dashboard', 'khm-membership'); ?>">
                <div class="khm-qc-member-header">
                    <div>
                        <h2><?php esc_html_e('Quote Club Dashboard', 'khm-membership'); ?></h2>
                        <p><?php esc_html_e('Track your commentary workflow and jump straight into submissions.', 'khm-membership'); ?></p>
                    </div>
                    <div class="khm-qc-member-actions">
                        <a href="#khm-qc-search-workspace" class="button button-primary"><?php esc_html_e('Article Search', 'khm-membership'); ?></a>
                        <a href="<?= esc_url($new_press_release_url); ?>" class="button"><?php esc_html_e('New Press Release', 'khm-membership'); ?></a>
                        <a href="<?= esc_url($buy_credits_url); ?>" class="button"><?php esc_html_e('Buy Credits', 'khm-membership'); ?></a>
                    </div>
                </div>

                <div class="khm-qc-member-stats">
                    <article class="khm-qc-member-card">
                        <span class="khm-qc-member-value"><?= esc_html($editorial_credits); ?></span>
                        <span class="khm-qc-member-label"><?php esc_html_e('Editorial Credits', 'khm-membership'); ?></span>
                    </article>
                    <article class="khm-qc-member-card">
                        <span class="khm-qc-member-value"><?= esc_html($press_release_credits); ?></span>
                        <span class="khm-qc-member-label"><?php esc_html_e('Press Release Credits', 'khm-membership'); ?></span>
                    </article>
                    <article class="khm-qc-member-card">
                        <span class="khm-qc-member-value"><?= esc_html($my_drafts); ?></span>
                        <span class="khm-qc-member-label"><?php esc_html_e('Drafts In Progress', 'khm-membership'); ?></span>
                    </article>
                    <article class="khm-qc-member-card">
                        <span class="khm-qc-member-value"><?= esc_html($pending_review); ?></span>
                        <span class="khm-qc-member-label"><?php esc_html_e('Awaiting Review', 'khm-membership'); ?></span>
                    </article>
                    <article class="khm-qc-member-card">
                        <span class="khm-qc-member-value"><?= esc_html($published_count); ?></span>
                        <span class="khm-qc-member-label"><?php esc_html_e('Live To Date', 'khm-membership'); ?></span>
                    </article>
                </div>

                <div class="khm-qc-member-recent">
                    <h3><?php esc_html_e('Recent Activity', 'khm-membership'); ?></h3>
                    <div class="khm-qc-member-recent-controls">
                        <form method="get" class="khm-qc-member-page-size-form">
                            <input type="hidden" name="tab" value="quoteclub" />
                            <input type="hidden" name="qc_activity_page" value="1" />
                            <label for="khm-qc-activity-per-page"><?php esc_html_e('Rows per page', 'khm-membership'); ?></label>
                            <select
                                id="khm-qc-activity-per-page"
                                name="qc_activity_per_page"
                                onchange="this.form.submit()"
                            >
                                <?php foreach ($allowed_per_page as $size) : ?>
                                    <option value="<?= esc_attr($size); ?>" <?= selected($activity_per_page, $size, false); ?>>
                                        <?= esc_html($size); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <?php if (!empty($recent)) : ?>
                        <div class="khm-qc-member-table-wrap">
                            <table class="khm-qc-member-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Type', 'khm-membership'); ?></th>
                                        <th><?php esc_html_e('Item', 'khm-membership'); ?></th>
                                        <th><?php esc_html_e('Status', 'khm-membership'); ?></th>
                                        <th><?php esc_html_e('Date', 'khm-membership'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $item) :
                                        $activity_type = sanitize_key((string) ($item['activity_type'] ?? 'commentary'));
                                        $status_key = sanitize_key((string) ($item['status'] ?? ''));
                                        $status_label = $this->map_quoteclub_activity_status($status_key);
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="khm-qc-member-type"><?= esc_html($activity_type === 'press_release' ? __('Press Release', 'khm-membership') : __('Commentary', 'khm-membership')); ?></span>
                                            </td>
                                            <td>
                                                <span class="khm-qc-member-session"><?= esc_html((string) ($item['activity_label'] ?? 'Goose Egg')); ?></span>
                                            </td>
                                            <td>
                                                <span class="khm-qc-member-status khm-qc-member-status-<?= esc_attr($status_label['class']); ?>"><?= esc_html($status_label['label']); ?></span>
                                            </td>
                                            <td>
                                                <span class="khm-qc-member-date"><?= esc_html(substr((string) ($item['activity_at'] ?? ''), 0, 10)); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($activity_total_pages > 1) : ?>
                            <nav class="khm-qc-member-pagination" aria-label="<?php esc_attr_e('Recent activity pages', 'khm-membership'); ?>">
                                <?php if ($activity_page > 1) : ?>
                                    <a class="button" href="<?= esc_url(add_query_arg([
                                        'tab' => 'quoteclub',
                                        'qc_activity_page' => $activity_page - 1,
                                        'qc_activity_per_page' => $activity_per_page,
                                    ], $pagination_base_url)); ?>"><?php esc_html_e('Previous', 'khm-membership'); ?></a>
                                <?php endif; ?>

                                <?php for ($page = $page_window_start; $page <= $page_window_end; $page++) : ?>
                                    <?php if ($page === $activity_page) : ?>
                                        <span class="button khm-qc-page-current" aria-current="page"><?= esc_html($page); ?></span>
                                    <?php else : ?>
                                        <a class="button" href="<?= esc_url(add_query_arg([
                                            'tab' => 'quoteclub',
                                            'qc_activity_page' => $page,
                                            'qc_activity_per_page' => $activity_per_page,
                                        ], $pagination_base_url)); ?>"><?= esc_html($page); ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($activity_page < $activity_total_pages) : ?>
                                    <a class="button" href="<?= esc_url(add_query_arg([
                                        'tab' => 'quoteclub',
                                        'qc_activity_page' => $activity_page + 1,
                                        'qc_activity_per_page' => $activity_per_page,
                                    ], $pagination_base_url)); ?>"><?php esc_html_e('Next', 'khm-membership'); ?></a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="khm-qc-member-empty"><?php esc_html_e('No activity yet. Get started by submitting your first quote!', 'khm-membership'); ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <?php $mp_categories = $this->get_top_line_categories(); ?>
            <div class="khm-quoteclub-toolbar" id="khm-qc-search-workspace">
                <select class="khm-filter-date-range" aria-label="<?php esc_attr_e('Date Range', 'khm-membership'); ?>">
                    <option value="all"><?php esc_html_e('All', 'khm-membership'); ?></option>
                    <option value="week"><?php esc_html_e('Within the next week', 'khm-membership'); ?></option>
                    <option value="month"><?php esc_html_e('Within the next month', 'khm-membership'); ?></option>
                </select>
                <?php if ( ! empty( $mp_categories ) ) : ?>
                <select multiple class="khm-filter-categories" aria-label="<?php esc_attr_e('Categories', 'khm-membership'); ?>">
                    <?php foreach ( $mp_categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <div class="khm-topic-autocomplete">
                    <input type="text" class="khm-filter-topics" autocomplete="off" placeholder="<?php esc_attr_e('Topics', 'khm-membership'); ?>" />
                    <div class="khm-topic-suggest-menu" role="listbox" aria-label="<?php esc_attr_e('Topic suggestions', 'khm-membership'); ?>"></div>
                </div>
                <input type="text" class="khm-filter-keywords" placeholder="Keywords" />
                <select class="khm-filter-operator" aria-label="<?php esc_attr_e('Keyword operator', 'khm-membership'); ?>">
                    <option value="AND"><?php esc_html_e('AND', 'khm-membership'); ?></option>
                    <option value="OR"><?php esc_html_e('OR', 'khm-membership'); ?></option>
                </select>
                <p class="khm-filter-operator-help"><?php esc_html_e('Keyword match: AND requires all words, OR matches any word. Use AND to narrow and OR to broaden.', 'khm-membership'); ?></p>
                <select class="khm-saved-searches" aria-label="<?php esc_attr_e('Saved searches', 'khm-membership'); ?>">
                    <option value=""><?php esc_html_e('— Saved Searches —', 'khm-membership'); ?></option>
                </select>
                <button type="button" class="button khm-quoteclub-search-btn"><?php esc_html_e('Search', 'khm-membership'); ?></button>
                <button type="button" class="button khm-save-search-btn"><?php esc_html_e('Save Search', 'khm-membership'); ?></button>
            </div>

            <div class="khm-quoteclub-results"></div>
            <div class="khm-quoteclub-detail">
                <h3><?php esc_html_e('Session details', 'khm-membership'); ?></h3>
                <p><?php esc_html_e('Select a result to view the brief and submit commentary.', 'khm-membership'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Map internal activity statuses to UI labels.
     */
    private function map_quoteclub_activity_status(string $status): array {
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
     * Render Dashboard tab
     */
    private function render_dashboard_tab(int $user_id): void {
        $memberships = $this->memberships->findActive($user_id);
        $membership = !empty($memberships) ? $memberships[0] : null;
        $level = ($membership && !empty($membership->level_id)) ? $this->levels->get((int) $membership->level_id) : null;
        $credits = $this->credits->getUserCredits($user_id);
        $library_stats = $this->library->get_library_stats($user_id);
        $downloads = $this->downloads->getUserDownloads($user_id, ['limit' => 5]);
        ?>
        <div class="khm-portal-tab khm-portal-dashboard">
            <h2 class="khm-section-title"><?php esc_html_e('Dashboard Overview', 'khm-membership'); ?></h2>
            
            <!-- Quick Stats Grid -->
            <div class="khm-dashboard-grid">
                <div class="khm-dashboard-card">
                    <div class="khm-card-icon"><span class="dashicons dashicons-star-filled"></span></div>
                    <div class="khm-card-content">
                        <span class="khm-card-value"><?= esc_html($credits); ?></span>
                        <span class="khm-card-label"><?php esc_html_e('Available Credits', 'khm-membership'); ?></span>
                    </div>
                    <a href="<?= esc_url(add_query_arg('tab', 'credits')); ?>" class="khm-card-link"><?php esc_html_e('View Details →', 'khm-membership'); ?></a>
                </div>
                
                <div class="khm-dashboard-card">
                    <div class="khm-card-icon"><span class="dashicons dashicons-book-alt"></span></div>
                    <div class="khm-card-content">
                        <span class="khm-card-value"><?= esc_html($library_stats['total_saved'] ?? 0); ?></span>
                        <span class="khm-card-label"><?php esc_html_e('Saved Articles', 'khm-membership'); ?></span>
                    </div>
                    <a href="<?= esc_url(add_query_arg('tab', 'library')); ?>" class="khm-card-link"><?php esc_html_e('View Library →', 'khm-membership'); ?></a>
                </div>
                
                <div class="khm-dashboard-card">
                    <div class="khm-card-icon"><span class="dashicons dashicons-download"></span></div>
                    <div class="khm-card-content">
                        <span class="khm-card-value"><?= esc_html($this->downloads->getUserDownloadCount($user_id)); ?></span>
                        <span class="khm-card-label"><?php esc_html_e('Downloaded PDFs', 'khm-membership'); ?></span>
                    </div>
                    <a href="<?= esc_url(add_query_arg('tab', 'downloads')); ?>" class="khm-card-link"><?php esc_html_e('View Downloads →', 'khm-membership'); ?></a>
                </div>
                
                <div class="khm-dashboard-card">
                    <div class="khm-card-icon"><span class="dashicons dashicons-id"></span></div>
                    <div class="khm-card-content">
                        <span class="khm-card-value"><?= $level ? esc_html($level->name) : 'None'; ?></span>
                        <span class="khm-card-label"><?php esc_html_e('Membership Level', 'khm-membership'); ?></span>
                    </div>
                    <a href="<?= esc_url(add_query_arg('tab', 'membership')); ?>" class="khm-card-link"><?php esc_html_e('Manage →', 'khm-membership'); ?></a>
                </div>
            </div>

            <div class="khm-dashboard-section khm-voucher-section">
                <h3 class="khm-subsection-title"><?php esc_html_e('Redeem Gift Voucher', 'khm-membership'); ?></h3>
                <p class="khm-section-desc"><?php esc_html_e('Enter a voucher code to add a gifted article to your library.', 'khm-membership'); ?></p>
                <form class="khm-form khm-voucher-form" id="khm-voucher-form">
                    <div class="khm-form-row">
                        <label for="khm-voucher-code"><?php esc_html_e('Voucher Code', 'khm-membership'); ?></label>
                        <input type="text" id="khm-voucher-code" class="khm-input khm-voucher-code" placeholder="<?php esc_attr_e('Paste your voucher code', 'khm-membership'); ?>" required>
                    </div>
                    <button type="submit" class="khm-btn khm-btn-primary">
                        <?php esc_html_e('Redeem Voucher', 'khm-membership'); ?>
                    </button>
                </form>
            </div>

            <!-- Recent Downloads -->
            <?php if (!empty($downloads)): ?>
            <div class="khm-dashboard-section">
                <h3 class="khm-subsection-title"><?php esc_html_e('Recent Downloads', 'khm-membership'); ?></h3>
                <div class="khm-recent-list">
                    <?php foreach ($downloads as $download): 
                        $post = get_post($download->post_id);
                        if (!$post) continue;
                    ?>
                    <div class="khm-recent-item">
                        <div class="khm-recent-thumb">
                            <?php if ($thumb = get_the_post_thumbnail_url($post->ID, 'thumbnail')): ?>
                                <img src="<?= esc_url($thumb); ?>" alt="">
                            <?php else: ?>
                                <span class="dashicons dashicons-media-document"></span>
                            <?php endif; ?>
                        </div>
                        <div class="khm-recent-info">
                            <a href="<?= esc_url(get_permalink($post->ID)); ?>" class="khm-recent-title"><?= esc_html($post->post_title); ?></a>
                            <span class="khm-recent-date"><?= esc_html(human_time_diff(strtotime($download->last_download_at))); ?> ago</span>
                        </div>
                        <button class="khm-btn khm-btn-sm khm-btn-secondary khm-redownload-btn" 
                                data-post-id="<?= esc_attr($post->ID); ?>">
                            <span class="dashicons dashicons-download"></span>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Library tab
     */
    private function render_library_tab(int $user_id): void {
        ?>
        <div class="khm-portal-tab khm-portal-library">
            <div class="khm-section-header">
                <h2 class="khm-section-title"><?php esc_html_e('My Library', 'khm-membership'); ?></h2>
                <div class="khm-library-search">
                    <input type="text" id="library-search" placeholder="<?php esc_attr_e('Search articles...', 'khm-membership'); ?>" class="khm-input">
                </div>
            </div>
            
            <div class="khm-library-grid" id="library-items">
                <div class="khm-loading"><?php esc_html_e('Loading your library...', 'khm-membership'); ?></div>
            </div>
            
            <div class="khm-load-more" id="library-load-more" style="display: none;">
                <button class="khm-btn khm-btn-secondary"><?php esc_html_e('Load More', 'khm-membership'); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * Render Downloads tab
     */
    private function render_downloads_tab(int $user_id): void {
        ?>
        <div class="khm-portal-tab khm-portal-downloads">
            <h2 class="khm-section-title"><?php esc_html_e('Downloaded PDFs', 'khm-membership'); ?></h2>
            <p class="khm-section-desc"><?php esc_html_e('Articles you have downloaded. Re-downloads are free while your membership is active.', 'khm-membership'); ?></p>
            
            <div class="khm-downloads-list" id="downloads-list">
                <div class="khm-loading"><?php esc_html_e('Loading downloads...', 'khm-membership'); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Credits tab
     */
    private function render_credits_tab(int $user_id): void {
        $credits = $this->credits->getUserCredits($user_id);
        $history = function_exists('khm_get_credit_history') ? khm_get_credit_history($user_id, 5) : [];
        ?>
        <div class="khm-portal-tab khm-portal-credits">
            <h2 class="khm-section-title"><?php esc_html_e('Credits', 'khm-membership'); ?></h2>
            
            <div class="khm-credits-overview">
                <div class="khm-credits-balance">
                    <span class="khm-balance-value"><?= esc_html($credits); ?></span>
                    <span class="khm-balance-label"><?php esc_html_e('Available Credits', 'khm-membership'); ?></span>
                </div>
                <div class="khm-credits-actions">
                    <button class="khm-btn khm-btn-primary" id="topup-credits-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Top Up Credits', 'khm-membership'); ?>
                    </button>
                </div>
            </div>
            
            <div class="khm-credits-history">
                <h3 class="khm-subsection-title"><?php esc_html_e('Transaction History', 'khm-membership'); ?></h3>
                
                <?php if (!empty($history)): ?>
                <table class="khm-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Description', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Amount', 'khm-membership'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $item): ?>
                        <tr>
                            <td><?= esc_html(date('M j, Y', strtotime($item['created_at'] ?? $item['date'] ?? 'now'))); ?></td>
                            <td><?= esc_html($item['reason'] ?? $item['description'] ?? 'Transaction'); ?></td>
                            <td class="<?= ($item['amount'] ?? 0) > 0 ? 'khm-text-success' : 'khm-text-danger'; ?>">
                                <?= ($item['amount'] ?? 0) > 0 ? '+' : ''; ?><?= esc_html($item['amount'] ?? 0); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="khm-empty-state"><?php esc_html_e('No credit transactions yet.', 'khm-membership'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Membership tab
     */
    private function render_membership_tab(int $user_id): void {
        $memberships = $this->memberships->findActive($user_id);
        $membership = !empty($memberships) ? $memberships[0] : null;
        $level = ($membership && !empty($membership->level_id)) ? $this->levels->get((int) $membership->level_id) : null;
        ?>
        <div class="khm-portal-tab khm-portal-membership">
            <h2 class="khm-section-title"><?php esc_html_e('Membership', 'khm-membership'); ?></h2>
            
            <?php if ($membership && $level): ?>
            <div class="khm-membership-card">
                <div class="khm-membership-header">
                    <h3 class="khm-membership-level"><?= esc_html($level->name); ?></h3>
                    <span class="khm-badge khm-badge-<?= $membership->status === 'active' ? 'success' : 'warning'; ?>">
                        <?= esc_html(ucfirst($membership->status)); ?>
                    </span>
                </div>
                
                <div class="khm-membership-details">
                    <div class="khm-detail-row">
                        <span class="khm-detail-label"><?php esc_html_e('Started', 'khm-membership'); ?></span>
                        <span class="khm-detail-value"><?= esc_html(date('F j, Y', strtotime($membership->started_at))); ?></span>
                    </div>
                    <?php if ($membership->expires_at): ?>
                    <div class="khm-detail-row">
                        <span class="khm-detail-label"><?php esc_html_e('Next Renewal', 'khm-membership'); ?></span>
                        <span class="khm-detail-value"><?= esc_html(date('F j, Y', strtotime($membership->expires_at))); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($level->monthly_credits) && $level->monthly_credits > 0): ?>
                    <div class="khm-detail-row">
                        <span class="khm-detail-label"><?php esc_html_e('Monthly Credits', 'khm-membership'); ?></span>
                        <span class="khm-detail-value"><?= esc_html($level->monthly_credits); ?> credits/month</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="khm-membership-actions">
                    <?php if ($membership->status === 'active'): ?>
                        <button class="khm-btn khm-btn-secondary" id="pause-membership-btn">
                            <span class="dashicons dashicons-controls-pause"></span>
                            <?php esc_html_e('Pause Membership', 'khm-membership'); ?>
                        </button>
                    <?php elseif ($membership->status === 'paused'): ?>
                        <button class="khm-btn khm-btn-primary" id="resume-membership-btn">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Resume Membership', 'khm-membership'); ?>
                        </button>
                    <?php endif; ?>
                    
                    <button class="khm-btn khm-btn-danger khm-btn-outline" id="cancel-membership-btn">
                        <span class="dashicons dashicons-no"></span>
                        <?php esc_html_e('Cancel Membership', 'khm-membership'); ?>
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="khm-no-membership">
                <p><?php esc_html_e('You don\'t have an active membership.', 'khm-membership'); ?></p>
                <a href="<?= esc_url(home_url('/membership-levels/')); ?>" class="khm-btn khm-btn-primary">
                    <?php esc_html_e('View Membership Options', 'khm-membership'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Account tab
     */
    private function render_account_tab(int $user_id): void {
        $user = get_userdata($user_id);
        ?>
        <div class="khm-portal-tab khm-portal-account">
            <h2 class="khm-section-title"><?php esc_html_e('Account Settings', 'khm-membership'); ?></h2>
            
            <div class="khm-account-sections">
                <!-- Profile Section -->
                <div class="khm-account-section">
                    <h3 class="khm-subsection-title"><?php esc_html_e('Profile Information', 'khm-membership'); ?></h3>
                    <form class="khm-form" id="profile-form">
                        <div class="khm-form-row">
                            <label for="display_name"><?php esc_html_e('Display Name', 'khm-membership'); ?></label>
                            <input type="text" id="display_name" name="display_name" 
                                   value="<?= esc_attr($user->display_name); ?>" class="khm-input">
                        </div>
                        <div class="khm-form-row">
                            <label for="user_email"><?php esc_html_e('Email Address', 'khm-membership'); ?></label>
                            <input type="email" id="user_email" name="user_email" 
                                   value="<?= esc_attr($user->user_email); ?>" class="khm-input">
                        </div>
                        <button type="submit" class="khm-btn khm-btn-primary"><?php esc_html_e('Update Profile', 'khm-membership'); ?></button>
                    </form>
                </div>
                
                <!-- Password Section -->
                <div class="khm-account-section">
                    <h3 class="khm-subsection-title"><?php esc_html_e('Change Password', 'khm-membership'); ?></h3>
                    <form class="khm-form" id="password-form">
                        <div class="khm-form-row">
                            <label for="current_password"><?php esc_html_e('Current Password', 'khm-membership'); ?></label>
                            <input type="password" id="current_password" name="current_password" class="khm-input">
                        </div>
                        <div class="khm-form-row">
                            <label for="new_password"><?php esc_html_e('New Password', 'khm-membership'); ?></label>
                            <input type="password" id="new_password" name="new_password" class="khm-input">
                        </div>
                        <div class="khm-form-row">
                            <label for="confirm_password"><?php esc_html_e('Confirm New Password', 'khm-membership'); ?></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="khm-input">
                        </div>
                        <button type="submit" class="khm-btn khm-btn-primary"><?php esc_html_e('Update Password', 'khm-membership'); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Fetch top-line categories from the shared Dual GPT option.
     *
     * @return array<int, string>
     */
    private function get_top_line_categories(): array {
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
