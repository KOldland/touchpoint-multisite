<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use KHM\Services\MembershipRepository;
use KHM\Services\CreditService;
use KHM\Services\LevelRepository;

/**
 * Portal Credits Widget
 * 
 * Displays credit balance with top-up options.
 */
class PortalCredits_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_portal_credits';
    }

    public function get_title() {
        return __('Portal Credits', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-price-table';
    }

    public function get_categories() {
        return ['general', 'touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['portal', 'credits', 'member', 'khm', 'balance', 'topup'];
    }

    public function show_in_panel() {
        return true;
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Credits Display', 'khm-membership'),
            ]
        );

        $this->add_control(
            'show_balance',
            [
                'label' => __('Show Balance', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_history',
            [
                'label' => __('Show Transaction History', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'history_limit',
            [
                'label' => __('History Items', 'khm-membership'),
                'type' => Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 50,
                'condition' => [
                    'show_history' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_topup',
            [
                'label' => __('Show Top-Up Button', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'topup_url',
            [
                'label' => __('Top-Up Page URL', 'khm-membership'),
                'type' => Controls_Manager::URL,
                'placeholder' => __('https://your-site.com/credits', 'khm-membership'),
                'condition' => [
                    'show_topup' => 'yes',
                ],
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
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your credits.', 'khm-membership') . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();

        // Get services
        $memberships_repo = new MembershipRepository();
        $levels_repo = new LevelRepository();
        $credits_service = new CreditService($memberships_repo, $levels_repo);

        // Get data
        $credits = $credits_service->getUserCredits($user_id);
        $transactions = $settings['show_history'] === 'yes' 
            ? $this->get_credit_transactions($user_id, (int)$settings['history_limit'])
            : [];

        $this->enqueue_portal_styles();
        $accent_color = $settings['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-credits" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <?php if ($settings['show_balance'] === 'yes'): ?>
            <div class="khm-credits-balance">
                <div class="khm-balance-display">
                    <span class="khm-balance-icon">💳</span>
                    <div class="khm-balance-info">
                        <span class="khm-balance-value"><?php echo esc_html($credits); ?></span>
                        <span class="khm-balance-label"><?php esc_html_e('Credits Available', 'khm-membership'); ?></span>
                    </div>
                </div>
                
                <?php if ($settings['show_topup'] === 'yes'): 
                    $topup_url = !empty($settings['topup_url']['url']) ? $settings['topup_url']['url'] : '#';
                ?>
                <a href="<?php echo esc_url($topup_url); ?>" class="khm-topup-btn">
                    <?php esc_html_e('Top Up Credits', 'khm-membership'); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_history'] === 'yes' && !empty($transactions)): ?>
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
            <?php elseif ($settings['show_history'] === 'yes'): ?>
            <div class="khm-credits-history">
                <h3><?php esc_html_e('Transaction History', 'khm-membership'); ?></h3>
                <p class="khm-empty-message"><?php esc_html_e('No transactions yet.', 'khm-membership'); ?></p>
            </div>
            <?php endif; ?>

        </div>
        <?php
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
}
