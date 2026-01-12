<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use KHM\Services\MembershipRepository;
use KHM\Services\CreditService;
use KHM\Services\LibraryService;
use KHM\Services\LevelRepository;
use KHM\Services\CreditDownloadService;

/**
 * Portal Dashboard Widget
 * 
 * Displays member dashboard overview with stats, recent activity, quick actions.
 */
class PortalDashboard_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_portal_dashboard';
    }

    public function get_title() {
        return __('Portal Dashboard', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-dashboard';
    }

    public function get_categories() {
        return ['touchpoint', 'touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['portal', 'dashboard', 'member', 'khm', 'account', 'stats'];
    }

    public function show_in_panel() {
        return true;
    }

    public function get_script_depends() {
        return [];
    }

    public function get_style_depends() {
        return [];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Dashboard', 'khm-membership'),
            ]
        );

        $this->add_control(
            'show_welcome',
            [
                'label' => __('Show Welcome Message', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_stats',
            [
                'label' => __('Show Stats Cards', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_activity',
            [
                'label' => __('Show Recent Activity', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'activity_limit',
            [
                'label' => __('Activity Items', 'khm-membership'),
                'type' => Controls_Manager::NUMBER,
                'default' => 5,
                'min' => 1,
                'max' => 20,
                'condition' => [
                    'show_activity' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_quick_actions',
            [
                'label' => __('Show Quick Actions', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Style section
        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'khm-membership'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'accent_color',
            [
                'label' => __('Accent Color', 'khm-membership'),
                'type' => Controls_Manager::COLOR,
                'default' => '#6b0b0b',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!is_user_logged_in()) {
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your dashboard.', 'khm-membership') . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
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
        $level_id = ($membership && isset($membership->level_id)) ? (int) $membership->level_id : 0;
        $level = $level_id > 0 ? $levels_repo->get($level_id) : null;
        $credits = $credits_service->getUserCredits($user_id);
        $library_stats = $library_service->get_library_stats($user_id);
        $recent_downloads = $downloads_service->getUserDownloads($user_id, ['limit' => 5]);

        $this->enqueue_portal_styles();
        $accent_color = $settings['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-dashboard" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <?php if ($settings['show_welcome'] === 'yes'): ?>
            <div class="khm-dashboard-welcome">
                <h2><?php printf(esc_html__('Welcome back, %s', 'khm-membership'), esc_html($user->display_name)); ?></h2>
                <?php if ($level): ?>
                <p class="khm-membership-badge"><?php echo esc_html($level->name); ?> Member</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_stats'] === 'yes'): ?>
            <div class="khm-dashboard-stats">
                <div class="khm-stat-card">
                    <span class="khm-stat-icon">📚</span>
                    <div class="khm-stat-content">
                        <span class="khm-stat-value"><?php echo esc_html($library_stats['total'] ?? 0); ?></span>
                        <span class="khm-stat-label"><?php esc_html_e('Saved Articles', 'khm-membership'); ?></span>
                    </div>
                </div>
                <div class="khm-stat-card">
                    <span class="khm-stat-icon">💳</span>
                    <div class="khm-stat-content">
                        <span class="khm-stat-value"><?php echo esc_html($credits); ?></span>
                        <span class="khm-stat-label"><?php esc_html_e('Credits Available', 'khm-membership'); ?></span>
                    </div>
                </div>
                <div class="khm-stat-card">
                    <span class="khm-stat-icon">📥</span>
                    <div class="khm-stat-content">
                        <span class="khm-stat-value"><?php echo esc_html(count($recent_downloads)); ?></span>
                        <span class="khm-stat-label"><?php esc_html_e('Recent Downloads', 'khm-membership'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_quick_actions'] === 'yes'): ?>
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

            <?php if ($settings['show_activity'] === 'yes' && !empty($recent_downloads)): ?>
            <div class="khm-recent-activity">
                <h3><?php esc_html_e('Recent Downloads', 'khm-membership'); ?></h3>
                <ul class="khm-activity-list">
                    <?php foreach (array_slice($recent_downloads, 0, (int)$settings['activity_limit']) as $download): ?>
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
    }

    private function enqueue_portal_styles() {
        wp_enqueue_style(
            'khm-portal-widgets',
            plugin_dir_url(dirname(dirname(__DIR__))) . 'assets/css/portal-widgets.css',
            [],
            filemtime(plugin_dir_path(dirname(dirname(__DIR__))) . 'assets/css/portal-widgets.css')
        );
    }
}
