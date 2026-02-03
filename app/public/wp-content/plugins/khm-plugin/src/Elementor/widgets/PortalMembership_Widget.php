<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use KHM\Services\MembershipRepository;
use KHM\Services\LevelRepository;

/**
 * Portal Membership Widget
 * 
 * Displays membership status with pause/resume/cancel actions.
 */
class PortalMembership_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_portal_membership';
    }

    public function get_title() {
        return __('Portal Membership', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return ['touchpoint', 'touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['portal', 'membership', 'member', 'khm', 'subscription', 'plan'];
    }

    public function show_in_panel() {
        return true;
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Membership Display', 'khm-membership'),
            ]
        );

        $this->add_control(
            'show_status',
            [
                'label' => __('Show Membership Status', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_level_details',
            [
                'label' => __('Show Level Details', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_renewal_date',
            [
                'label' => __('Show Renewal Date', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'allow_pause',
            [
                'label' => __('Allow Pause', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'allow_cancel',
            [
                'label' => __('Allow Cancel', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'upgrade_url',
            [
                'label' => __('Upgrade Page URL', 'khm-membership'),
                'type' => Controls_Manager::URL,
                'placeholder' => __('https://your-site.com/upgrade', 'khm-membership'),
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
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your membership.', 'khm-membership') . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();

        // Get services
        $memberships_repo = new MembershipRepository();
        $levels_repo = new LevelRepository();

        // Get membership data
        $memberships = $memberships_repo->findActive($user_id);
        $membership = !empty($memberships) ? $memberships[0] : null;
        $level = $membership ? $levels_repo->get($membership->level_id) : null;

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
        $accent_color = $settings['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-membership" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <?php if ($membership && $level): ?>
            
            <div class="khm-membership-card">
                <?php if ($settings['show_status'] === 'yes'): ?>
                <div class="khm-membership-status">
                    <span class="khm-status-badge khm-status-<?php echo esc_attr($membership->status); ?>">
                        <?php echo esc_html(ucfirst($membership->status)); ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if ($settings['show_level_details'] === 'yes'): ?>
                <div class="khm-membership-level">
                    <h3><?php echo esc_html($level->name); ?></h3>
                    <?php if (!empty($level->description)): ?>
                    <p><?php echo esc_html($level->description); ?></p>
                    <?php endif; ?>
                    
                    <div class="khm-level-features">
                        <?php if (isset($level->monthly_credits)): ?>
                        <div class="khm-feature">
                            <span class="khm-feature-icon">💳</span>
                            <span><?php printf(esc_html__('%d monthly credits', 'khm-membership'), $level->monthly_credits); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($settings['show_renewal_date'] === 'yes' && !empty($membership->expires_at)): ?>
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
                    <?php if (!empty($settings['upgrade_url']['url'])): ?>
                    <a href="<?php echo esc_url($settings['upgrade_url']['url']); ?>" class="khm-upgrade-btn">
                        <?php esc_html_e('Upgrade Plan', 'khm-membership'); ?>
                    </a>
                    <?php endif; ?>

                    <?php if ($settings['allow_pause'] === 'yes' && $membership->status === 'active'): ?>
                    <button class="khm-pause-btn" data-action="pause">
                        <?php esc_html_e('Pause Membership', 'khm-membership'); ?>
                    </button>
                    <?php elseif ($settings['allow_pause'] === 'yes' && $membership->status === 'paused'): ?>
                    <button class="khm-resume-btn" data-action="resume">
                        <?php esc_html_e('Resume Membership', 'khm-membership'); ?>
                    </button>
                    <?php endif; ?>

                    <?php if ($settings['allow_cancel'] === 'yes' && in_array($membership->status, ['active', 'paused'])): ?>
                    <button class="khm-cancel-btn" data-action="cancel">
                        <?php esc_html_e('Cancel Membership', 'khm-membership'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            
            <div class="khm-no-membership">
                <span class="khm-empty-icon">🔒</span>
                <h3><?php esc_html_e('No Active Membership', 'khm-membership'); ?></h3>
                <p><?php esc_html_e('Start your membership to access premium content.', 'khm-membership'); ?></p>
                <?php if (!empty($settings['upgrade_url']['url'])): ?>
                <a href="<?php echo esc_url($settings['upgrade_url']['url']); ?>" class="khm-join-btn">
                    <?php esc_html_e('Join Now', 'khm-membership'); ?>
                </a>
                <?php endif; ?>
            </div>

            <?php endif; ?>

        </div>
        <?php
    }

    private function enqueue_portal_styles() {
        $css_path = plugin_dir_path(dirname(dirname(__DIR__))) . 'assets/css/portal-widgets.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'khm-portal-widgets',
                plugin_dir_url(dirname(dirname(__DIR__))) . 'assets/css/portal-widgets.css',
                [],
                filemtime($css_path)
            );
        }
    }

    private function enqueue_portal_scripts() {
        $js_path = plugin_dir_path(dirname(dirname(__DIR__))) . 'assets/js/portal-widgets.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'khm-portal-widgets',
                plugin_dir_url(dirname(dirname(__DIR__))) . 'assets/js/portal-widgets.js',
                ['jquery'],
                filemtime($js_path),
                true
            );

            wp_localize_script('khm-portal-widgets', 'khmPortalWidgets', [
                'restUrl' => esc_url_raw(rest_url('khm/v1/portal/')),
                'restNonce' => wp_create_nonce('wp_rest'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'shareNonce' => wp_create_nonce('khm_library_nonce'),
                'strings' => [
                    'confirm_pause' => __('Are you sure you want to pause your membership?', 'khm-membership'),
                    'confirm_cancel' => __('Are you sure you want to cancel? You will retain access until the end of your billing period.', 'khm-membership'),
                ],
            ]);
        }
    }
}
