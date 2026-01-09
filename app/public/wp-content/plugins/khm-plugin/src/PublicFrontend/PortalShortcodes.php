<?php

namespace KHM\PublicFrontend;

use KHM\Services\MembershipRepository;
use KHM\Services\CreditService;
use KHM\Services\LibraryService;
use KHM\Services\LevelRepository;
use KHM\Services\CreditDownloadService;

/**
 * Portal Shortcodes
 * 
 * Provides shortcodes for member portal sections
 */
class PortalShortcodes {

    public function __construct() {
        add_shortcode('khm_portal_dashboard', [$this, 'dashboard_shortcode']);
        add_shortcode('khm_portal_credits', [$this, 'credits_shortcode']);
        add_shortcode('khm_portal_downloads', [$this, 'downloads_shortcode']);
        add_shortcode('khm_portal_membership', [$this, 'membership_shortcode']);
        add_shortcode('khm_portal_account', [$this, 'account_shortcode']);
    }

    public function dashboard_shortcode($atts) {
        $atts = shortcode_atts([
            'show_welcome' => 'yes',
            'show_stats' => 'yes',
            'show_activity' => 'yes',
            'activity_limit' => 5,
            'show_quick_actions' => 'yes',
            'accent_color' => '#6b0b0b',
        ], $atts);

        if (!is_user_logged_in()) {
            return '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your dashboard.', 'khm-membership') . '</p>';
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        // Get services
        $memberships_repo = new MembershipRepository();
        $levels_repo = new LevelRepository();
        $credits_service = new CreditService($memberships_repo, $levels_repo);
        $library_service = new LibraryService($memberships_repo);
        $downloads_service = new CreditDownloadService($memberships_repo, $credits_service, $library_service);

        // Get data
        $memberships = $memberships_repo->findActive($user_id);
        $membership = !empty($memberships) ? $memberships[0] : null;
        $level = ($membership && $membership->level_id) ? $levels_repo->get($membership->level_id) : null;
        $credits = $credits_service->getUserCredits($user_id);
        $library_stats = $library_service->get_library_stats($user_id);
        $recent_downloads = $downloads_service->getUserDownloads($user_id, ['limit' => 5]);

        ob_start();
        ?>
        <div class="khm-portal-dashboard" style="--khm-accent: <?php echo esc_attr($atts['accent_color']); ?>">
            
            <?php if ($atts['show_welcome'] === 'yes'): ?>
            <div class="khm-dashboard-welcome">
                <h2><?php printf(esc_html__('Welcome back, %s', 'khm-membership'), esc_html($user->display_name)); ?></h2>
                <?php if ($level): ?>
                <p class="khm-membership-badge"><?php echo esc_html($level->name); ?> Member</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($atts['show_stats'] === 'yes'): ?>
            <div class="khm-dashboard-stats">
                <div class="khm-stat-card">
                    <span class="khm-stat-icon dashicons dashicons-book"></span>
                    <div class="khm-stat-content">
                        <span class="khm-stat-value"><?php echo esc_html($library_stats['total'] ?? 0); ?></span>
                        <span class="khm-stat-label"><?php esc_html_e('Saved Articles', 'khm-membership'); ?></span>
                    </div>
                </div>
                <div class="khm-stat-card">
                    <span class="khm-stat-icon dashicons dashicons-credit-card"></span>
                    <div class="khm-stat-content">
                        <span class="khm-stat-value"><?php echo esc_html($credits); ?></span>
                        <span class="khm-stat-label"><?php esc_html_e('Credits Available', 'khm-membership'); ?></span>
                    </div>
                </div>
                <div class="khm-stat-card">
                    <span class="khm-stat-icon dashicons dashicons-download"></span>
                    <div class="khm-stat-content">
                        <span class="khm-stat-value"><?php echo esc_html(count($recent_downloads)); ?></span>
                        <span class="khm-stat-label"><?php esc_html_e('Recent Downloads', 'khm-membership'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($atts['show_quick_actions'] === 'yes'): ?>
            <div class="khm-quick-actions">
                <h3><?php esc_html_e('Quick Actions', 'khm-membership'); ?></h3>
                <div class="khm-action-buttons">
                    <a href="<?php echo esc_url(get_permalink(get_option('khm_library_page_id'))); ?>" class="khm-action-btn">
                        <?php esc_html_e('Browse Articles', 'khm-membership'); ?>
                    </a>
                    <a href="#credits" class="khm-action-btn khm-action-secondary">
                        <?php esc_html_e('Top Up Credits', 'khm-membership'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($atts['show_activity'] === 'yes' && !empty($recent_downloads)): ?>
            <div class="khm-recent-activity">
                <h3><?php esc_html_e('Recent Downloads', 'khm-membership'); ?></h3>
                <ul class="khm-activity-list">
                    <?php foreach (array_slice($recent_downloads, 0, (int)$atts['activity_limit']) as $download): ?>
                    <li class="khm-activity-item">
                        <span class="khm-activity-title"><?php echo esc_html(get_the_title($download->post_id)); ?></span>
                        <span class="khm-activity-date"><?php echo esc_html(human_time_diff(strtotime($download->created_at), current_time('timestamp')) . ' ago'); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    public function credits_shortcode($atts) {
        $atts = shortcode_atts([
            'show_balance' => 'yes',
            'show_history' => 'yes',
            'history_limit' => 10,
            'show_topup' => 'yes',
            'topup_url' => '',
            'accent_color' => '#6b0b0b',
        ], $atts);

        ob_start();
        
        if (!is_user_logged_in()) {
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your credits.', 'khm-membership') . '</p>';
            return ob_get_clean();
        }

        $user_id = get_current_user_id();
        $memberships_repo = new MembershipRepository();
        $levels_repo = new LevelRepository();
        $credits_service = new CreditService($memberships_repo, $levels_repo);

        // Get data
        $credits = $credits_service->getUserCredits($user_id);
        $transactions = $atts['show_history'] === 'yes' 
            ? $this->get_credit_transactions($user_id, (int)$atts['history_limit'])
            : [];

        $this->enqueue_portal_styles();
        $accent_color = $atts['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-credits" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <?php if ($atts['show_balance'] === 'yes'): ?>
            <div class="khm-credits-balance">
                <div class="khm-balance-display">
                    <span class="khm-balance-icon dashicons dashicons-credit-card"></span>
                    <div class="khm-balance-info">
                        <span class="khm-balance-value"><?php echo esc_html($credits); ?></span>
                        <span class="khm-balance-label"><?php esc_html_e('Credits Available', 'khm-membership'); ?></span>
                    </div>
                </div>
                
                <?php if ($atts['show_topup'] === 'yes'): 
                    $topup_url = !empty($atts['topup_url']) ? $atts['topup_url'] : '#';
                ?>
                <a href="<?php echo esc_url($topup_url); ?>" class="khm-topup-btn">
                    <?php esc_html_e('Top Up Credits', 'khm-membership'); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($atts['show_history'] === 'yes' && !empty($transactions)): ?>
            <div class="khm-credits-history">
                <h3><?php esc_html_e('Transaction History', 'khm-membership'); ?></h3>
                <table class="khm-transactions-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Description', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Amount', 'khm-membership'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tx->created_at))); ?></td>
                            <td><?php echo esc_html($tx->reason); ?></td>
                            <td class="<?php echo $tx->amount > 0 ? 'khm-credit-positive' : 'khm-credit-negative'; ?>">
                                <?php echo $tx->amount > 0 ? '+' : ''; ?><?php echo esc_html($tx->amount); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($atts['show_history'] === 'yes'): ?>
            <div class="khm-credits-history">
                <h3><?php esc_html_e('Transaction History', 'khm-membership'); ?></h3>
                <p class="khm-empty-message"><?php esc_html_e('No transactions yet.', 'khm-membership'); ?></p>
            </div>
            <?php endif; ?>

        </div>
        <?php
        
        return ob_get_clean();
    }

    public function downloads_shortcode($atts) {
        $atts = shortcode_atts([
            'per_page' => 10,
            'show_date' => 'yes',
            'show_credits' => 'yes',
            'allow_redownload' => 'yes',
            'accent_color' => '#6b0b0b',
        ], $atts);

        ob_start();
        
        if (!is_user_logged_in()) {
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your library.', 'khm-membership') . '</p>';
            return ob_get_clean();
        }

        $user_id = get_current_user_id();
        $memberships_repo = new MembershipRepository();
        $levels_repo = new LevelRepository();
        $credits_service = new CreditService($memberships_repo, $levels_repo);
        $library_service = new LibraryService($memberships_repo);
        $downloads_service = new CreditDownloadService($memberships_repo, $credits_service, $library_service);

        // Get saved library items (which includes both saved and downloaded articles)
        $library_items = $library_service->get_member_library($user_id, [
            'limit' => (int)$atts['per_page'],
        ]);

        $this->enqueue_portal_styles();
        $this->enqueue_portal_scripts();
        $accent_color = $atts['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-downloads" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <h3><?php esc_html_e('Your Articles', 'khm-membership'); ?></h3>

            <?php if (!empty($library_items)): ?>
            <div class="khm-downloads-list">
                <?php foreach ($library_items as $item): 
                    $post = get_post($item->post_id);
                    if (!$post) continue;
                    
                    // Check if this article has been downloaded
                    $has_downloaded = $downloads_service->hasDownloaded($user_id, $item->post_id);
                    $credit_cost = $downloads_service->getArticleCreditCost($item->post_id);
                ?>
                <div class="khm-download-item">
                    <div class="khm-download-info">
                        <h4 class="khm-download-title">
                            <a href="<?php echo esc_url(get_permalink($item->post_id)); ?>">
                                <?php echo esc_html(get_the_title($item->post_id)); ?>
                            </a>
                        </h4>
                        <div class="khm-download-meta">
                            <?php if ($atts['show_date'] === 'yes'): ?>
                            <span class="khm-download-date">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item->created_at))); ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($atts['show_credits'] === 'yes' && $has_downloaded): 
                                $download_record = $downloads_service->getDownloadRecord($user_id, $item->post_id);
                                if ($download_record && isset($download_record->credits_used)): ?>
                            <span class="khm-download-credits">
                                <?php printf(esc_html__('%d credit(s)', 'khm-membership'), $download_record->credits_used); ?>
                            </span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="khm-download-actions">
                        <?php if ($has_downloaded && $atts['allow_redownload'] === 'yes'): ?>
                        <button class="khm-redownload-btn" data-post-id="<?php echo esc_attr($item->post_id); ?>">
                            <span class="khm-btn-icon dashicons dashicons-download"></span>
                            <?php esc_html_e('Download Again', 'khm-membership'); ?>
                        </button>
                        <?php else: ?>
                        <button class="khm-download-btn" data-post-id="<?php echo esc_attr($item->post_id); ?>" data-credits="<?php echo esc_attr($credit_cost); ?>">
                            <span class="khm-btn-icon dashicons dashicons-download"></span>
                            <?php printf(esc_html__('Download (%d Credits)', 'khm-membership'), $credit_cost); ?>
                        </button>
                        <?php endif; ?>
                        <button class="khm-remove-btn" data-post-id="<?php echo esc_attr($item->post_id); ?>" data-title="<?php echo esc_attr(get_the_title($item->post_id)); ?>">
                            <span class="khm-btn-icon dashicons dashicons-trash"></span>
                            <?php esc_html_e('Remove', 'khm-membership'); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="khm-empty-state">
                <span class="khm-empty-icon dashicons dashicons-download"></span>
                <p><?php esc_html_e('No saved articles yet. Browse and save articles to your library.', 'khm-membership'); ?></p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="khm-browse-btn">
                    <?php esc_html_e('Browse Articles', 'khm-membership'); ?>
                </a>
            </div>
            <?php endif; ?>

        </div>
        <?php
        
        return ob_get_clean();
    }

    public function membership_shortcode($atts) {
        $atts = shortcode_atts([
            'show_status' => 'yes',
            'show_level' => 'yes',
            'show_renewal' => 'yes',
            'allow_pause' => 'yes',
            'allow_cancel' => 'yes',
            'upgrade_url' => '',
            'accent_color' => '#6b0b0b',
        ], $atts);

        ob_start();
        
        if (!is_user_logged_in()) {
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your membership.', 'khm-membership') . '</p>';
            return ob_get_clean();
        }

        $user_id = get_current_user_id();
        $memberships_repo = new MembershipRepository();
        $levels_repo = new LevelRepository();

        // Get membership data
        $memberships = $memberships_repo->findActive($user_id);
        $membership = !empty($memberships) ? $memberships[0] : null;
        $level = ($membership && $membership->level_id) ? $levels_repo->get($membership->level_id) : null;

        // Check for paused membership if no active
        if (!$membership) {
            global $wpdb;
            $table = $wpdb->prefix . 'khm_memberships';
            $paused = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND status = 'paused' ORDER BY id DESC LIMIT 1",
                $user_id
            ));
            if ($paused) {
                $membership = $paused;
                $level = $levels_repo->get($paused->level_id);
            }
        }

        $this->enqueue_portal_styles();
        $this->enqueue_portal_scripts();
        $accent_color = $atts['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-membership" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <?php if ($membership && $level): ?>
            
            <div class="khm-membership-card">
                <?php if ($atts['show_status'] === 'yes'): ?>
                <div class="khm-membership-status">
                    <span class="khm-status-badge khm-status-<?php echo esc_attr($membership->status); ?>">
                        <?php echo esc_html(ucfirst($membership->status)); ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if ($atts['show_level'] === 'yes'): ?>
                <div class="khm-membership-level">
                    <h3><?php echo esc_html($level->name); ?></h3>
                    <?php if (!empty($level->description)): ?>
                    <p><?php echo esc_html($level->description); ?></p>
                    <?php endif; ?>
                    
                    <div class="khm-level-features">
                        <?php if (isset($level->monthly_credits)): ?>
                        <div class="khm-feature">
                            <span class="khm-feature-icon dashicons dashicons-credit-card"></span>
                            <span><?php printf(esc_html__('%d monthly credits', 'khm-membership'), $level->monthly_credits); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($atts['show_renewal'] === 'yes' && !empty($membership->expires_at)): ?>
                <div class="khm-membership-renewal">
                    <span class="khm-renewal-label">
                        <?php echo $membership->status === 'active' 
                            ? esc_html__('Renews on', 'khm-membership') 
                            : esc_html__('Access until', 'khm-membership'); ?>
                    </span>
                    <span class="khm-renewal-date">
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($membership->expires_at))); ?>
                    </span>
                </div>
                <?php endif; ?>

                <div class="khm-membership-actions">
                    <?php if (!empty($atts['upgrade_url'])): ?>
                    <a href="<?php echo esc_url($atts['upgrade_url']); ?>" class="khm-upgrade-btn">
                        <?php esc_html_e('Upgrade Plan', 'khm-membership'); ?>
                    </a>
                    <?php endif; ?>

                    <?php if ($atts['allow_pause'] === 'yes' && $membership->status === 'active'): ?>
                    <button class="khm-pause-btn" data-action="pause">
                        <?php esc_html_e('Pause Membership', 'khm-membership'); ?>
                    </button>
                    <?php elseif ($atts['allow_pause'] === 'yes' && $membership->status === 'paused'): ?>
                    <button class="khm-resume-btn" data-action="resume">
                        <?php esc_html_e('Resume Membership', 'khm-membership'); ?>
                    </button>
                    <?php endif; ?>

                    <?php if ($atts['allow_cancel'] === 'yes' && in_array($membership->status, ['active', 'paused'])): ?>
                    <button class="khm-cancel-btn" data-action="cancel">
                        <?php esc_html_e('Cancel Membership', 'khm-membership'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            
            <div class="khm-no-membership">
                <span class="khm-empty-icon dashicons dashicons-lock"></span>
                <h3><?php esc_html_e('No Active Membership', 'khm-membership'); ?></h3>
                <p><?php esc_html_e('Start your membership to access premium content.', 'khm-membership'); ?></p>
                <?php if (!empty($atts['upgrade_url'])): ?>
                <a href="<?php echo esc_url($atts['upgrade_url']); ?>" class="khm-join-btn">
                    <?php esc_html_e('Join Now', 'khm-membership'); ?>
                </a>
                <?php endif; ?>
            </div>

            <?php endif; ?>

        </div>
        <?php
        
        return ob_get_clean();
    }

    public function account_shortcode($atts) {
        $atts = shortcode_atts([
            'show_profile' => 'yes',
            'show_avatar' => 'yes',
            'show_password' => 'yes',
            'show_email_prefs' => 'yes',
            'accent_color' => '#6b0b0b',
        ], $atts);

        ob_start();
        
        if (!is_user_logged_in()) {
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your account.', 'khm-membership') . '</p>';
            return ob_get_clean();
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        $this->enqueue_portal_styles();
        $this->enqueue_portal_scripts();
        $accent_color = $atts['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-account" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <?php if ($atts['show_profile'] === 'yes'): ?>
            <div class="khm-account-section khm-profile-section">
                <h3><?php esc_html_e('Profile Information', 'khm-membership'); ?></h3>
                
                <form class="khm-profile-form" data-form="profile">
                    <div class="khm-form-row">
                        <label for="khm-display-name"><?php esc_html_e('Display Name', 'khm-membership'); ?></label>
                        <input type="text" id="khm-display-name" name="display_name" value="<?php echo esc_attr($user->display_name); ?>">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-first-name"><?php esc_html_e('First Name', 'khm-membership'); ?></label>
                        <input type="text" id="khm-first-name" name="first_name" value="<?php echo esc_attr($user->first_name); ?>">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-last-name"><?php esc_html_e('Last Name', 'khm-membership'); ?></label>
                        <input type="text" id="khm-last-name" name="last_name" value="<?php echo esc_attr($user->last_name); ?>">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-email"><?php esc_html_e('Email', 'khm-membership'); ?></label>
                        <input type="email" id="khm-email" name="email" value="<?php echo esc_attr($user->user_email); ?>">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-job-title"><?php esc_html_e('Job Title', 'khm-membership'); ?></label>
                        <input type="text" id="khm-job-title" name="job_title" value="<?php echo esc_attr(get_user_meta($user_id, 'job_title', true)); ?>">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-company"><?php esc_html_e('Company', 'khm-membership'); ?></label>
                        <input type="text" id="khm-company" name="company" value="<?php echo esc_attr(get_user_meta($user_id, 'company', true)); ?>">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-location"><?php esc_html_e('Location', 'khm-membership'); ?></label>
                        <input type="text" id="khm-location" name="location" value="<?php echo esc_attr(get_user_meta($user_id, 'location', true)); ?>">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-linkedin"><?php esc_html_e('LinkedIn', 'khm-membership'); ?></label>
                        <input type="text" id="khm-linkedin" name="linkedin" value="<?php echo esc_attr(get_user_meta($user_id, 'linkedin', true)); ?>" placeholder="https://linkedin.com/in/yourprofile">
                    </div>

                    <button type="submit" class="khm-save-btn"><?php esc_html_e('Save Changes', 'khm-membership'); ?></button>
                    <span class="khm-form-message"></span>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($atts['show_password'] === 'yes'): ?>
            <div class="khm-account-section khm-password-section">
                <h3><?php esc_html_e('Change Password', 'khm-membership'); ?></h3>
                
                <form class="khm-password-form" data-form="password">
                    <div class="khm-form-row">
                        <label for="khm-current-password"><?php esc_html_e('Current Password', 'khm-membership'); ?></label>
                        <input type="password" id="khm-current-password" name="current_password" required>
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-new-password"><?php esc_html_e('New Password', 'khm-membership'); ?></label>
                        <input type="password" id="khm-new-password" name="new_password" required minlength="8">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-confirm-password"><?php esc_html_e('Confirm New Password', 'khm-membership'); ?></label>
                        <input type="password" id="khm-confirm-password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="khm-save-btn"><?php esc_html_e('Update Password', 'khm-membership'); ?></button>
                    <span class="khm-form-message"></span>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($atts['show_email_prefs'] === 'yes'): ?>
            <div class="khm-account-section khm-email-prefs-section">
                <h3><?php esc_html_e('Email Preferences', 'khm-membership'); ?></h3>
                
                <form class="khm-email-prefs-form" data-form="email_prefs">
                    <?php
                    $newsletter = get_user_meta($user_id, 'khm_newsletter_optin', true);
                    $notifications = get_user_meta($user_id, 'khm_email_notifications', true) ?: 'yes';
                    ?>
                    
                    <div class="khm-form-row khm-checkbox-row">
                        <label>
                            <input type="checkbox" name="newsletter" value="1" <?php checked($newsletter, '1'); ?>>
                            <?php esc_html_e('Subscribe to newsletter', 'khm-membership'); ?>
                        </label>
                    </div>

                    <div class="khm-form-row khm-checkbox-row">
                        <label>
                            <input type="checkbox" name="notifications" value="1" <?php checked($notifications, 'yes'); ?>>
                            <?php esc_html_e('Receive membership notifications', 'khm-membership'); ?>
                        </label>
                    </div>

                    <button type="submit" class="khm-save-btn"><?php esc_html_e('Save Preferences', 'khm-membership'); ?></button>
                    <span class="khm-form-message"></span>
                </form>
            </div>
            <?php endif; ?>

        </div>
        <?php
        
        return ob_get_clean();
    }

    private function get_credit_transactions(int $user_id, int $limit): array {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_credit_transactions';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
    }

    private function enqueue_portal_styles() {
        // Enqueue Dashicons for flat icons
        wp_enqueue_style('dashicons');
        
        $css_path = plugin_dir_path(dirname(__DIR__)) . 'assets/css/portal-widgets.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'khm-portal-widgets',
                plugin_dir_url(dirname(__DIR__)) . 'assets/css/portal-widgets.css',
                ['dashicons'],
                filemtime($css_path)
            );
        }
    }

    private function enqueue_portal_scripts() {
        $js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/js/portal-widgets.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'khm-portal-widgets',
                plugin_dir_url(dirname(__DIR__)) . 'assets/js/portal-widgets.js',
                ['jquery'],
                filemtime($js_path),
                true
            );

            wp_localize_script('khm-portal-widgets', 'khmPortalWidgets', [
                'restUrl' => esc_url_raw(rest_url('khm/v1/portal/')),
                'restNonce' => wp_create_nonce('wp_rest'),
                'strings' => [
                    'saving' => __('Saving...', 'khm-membership'),
                    'saved' => __('Saved!', 'khm-membership'),
                    'error' => __('An error occurred.', 'khm-membership'),
                    'passwords_mismatch' => __('Passwords do not match.', 'khm-membership'),
                    'confirm_pause' => __('Are you sure you want to pause your membership?', 'khm-membership'),
                    'confirm_cancel' => __('Are you sure you want to cancel? You will retain access until the end of your billing period.', 'khm-membership'),
                ],
            ]);
        }
    }
}
