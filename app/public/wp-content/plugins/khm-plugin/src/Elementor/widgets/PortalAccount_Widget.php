<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Portal Account Widget
 * 
 * Displays account settings, profile info, and security options.
 */
class PortalAccount_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_portal_account';
    }

    public function get_title() {
        return __('Portal Account', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-lock-user';
    }

    public function get_categories() {
        return ['touchpoint', 'touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['portal', 'account', 'member', 'khm', 'profile', 'settings'];
    }

    public function show_in_panel() {
        return true;
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Account Settings', 'khm-membership'),
            ]
        );

        $this->add_control(
            'show_profile',
            [
                'label' => __('Show Profile Section', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_avatar',
            [
                'label' => __('Show Avatar', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'show_profile' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_password',
            [
                'label' => __('Show Password Section', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_email_prefs',
            [
                'label' => __('Show Email Preferences', 'khm-membership'),
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
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your account settings.', 'khm-membership') . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        $this->enqueue_portal_styles();
        $this->enqueue_portal_scripts();
        $accent_color = $settings['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-account" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <?php if ($settings['show_profile'] === 'yes'): ?>
            <div class="khm-account-section khm-profile-section">
                <h3><?php esc_html_e('Profile Information', 'khm-membership'); ?></h3>
                
                <form class="khm-profile-form" data-form="profile">
                    <?php if ($settings['show_avatar'] === 'yes'): ?>
                    <div class="khm-avatar-row">
                        <?php echo get_avatar($user_id, 80, '', '', ['class' => 'khm-user-avatar']); ?>
                        <p class="khm-avatar-hint"><?php esc_html_e('Avatar is managed via Gravatar', 'khm-membership'); ?></p>
                    </div>
                    <?php endif; ?>

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

                    <button type="submit" class="khm-save-btn"><?php esc_html_e('Save Changes', 'khm-membership'); ?></button>
                    <span class="khm-form-message"></span>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_password'] === 'yes'): ?>
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

            <?php if ($settings['show_email_prefs'] === 'yes'): ?>
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
                    'saving' => __('Saving...', 'khm-membership'),
                    'saved' => __('Saved!', 'khm-membership'),
                    'error' => __('An error occurred.', 'khm-membership'),
                    'passwords_mismatch' => __('Passwords do not match.', 'khm-membership'),
                ],
            ]);
        }
    }
}
