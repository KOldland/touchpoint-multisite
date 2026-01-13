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
        return ['touchpoint', 'touchpoint', 'theme-elements'];
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
                'default' => 5,
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
        $limit = min((int) $settings['history_limit'], 5);
        $page = isset($_GET['khm_tx_page']) ? max(1, (int) $_GET['khm_tx_page']) : 1;
        $total_transactions = $settings['show_history'] === 'yes'
            ? $this->get_transaction_total($user_id)
            : 0;
        $total_pages = $limit > 0 ? (int) ceil($total_transactions / $limit) : 1;
        if ($page > $total_pages && $total_pages > 0) {
            $page = $total_pages;
        }
        $offset = ($page - 1) * $limit;
        $transactions = $settings['show_history'] === 'yes'
            ? $this->get_transactions($user_id, $limit, $offset)
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
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tx['date']))); ?></td>
                            <td><?php echo esc_html($tx['description']); ?></td>
                            <td class="<?php echo esc_attr($tx['amount_class']); ?>">
                                <?php echo esc_html($tx['amount_display']); ?>
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
            <?php if ($settings['show_history'] === 'yes' && $total_pages > 1): ?>
            <div class="khm-transactions-pagination">
                <?php
                $base_url = remove_query_arg('khm_tx_page');
                for ($i = 1; $i <= $total_pages; $i++):
                    $url = add_query_arg('khm_tx_page', $i, $base_url);
                    $class = $i === $page ? 'is-active' : '';
                ?>
                    <a class="khm-page-link <?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html($i); ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }

    private function get_transactions(int $user_id, int $limit, int $offset = 0): array {
        global $wpdb;
        $transactions = [];

        $usage_table = $wpdb->prefix . 'khm_credit_usage';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$usage_table}'") === $usage_table) {
            $usage = $wpdb->get_results($wpdb->prepare(
                "SELECT credits_used, purpose, object_id, created_at
                 FROM {$usage_table}
                 WHERE user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            ));

            foreach ($usage as $row) {
                $credits = (int) $row->credits_used;
                if ($credits === 0) {
                    continue;
                }

                $label = $this->format_credit_reason($row->purpose ?? '', $row->object_id ?? 0);
                $amount_display = '-' . abs($credits) . ' credits';
                $transactions[] = [
                    'date' => $row->created_at,
                    'description' => $label,
                    'amount_display' => $amount_display,
                    'amount_class' => 'khm-credit-negative',
                ];
            }
        }

        $purchases_table = $wpdb->prefix . 'khm_purchases';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'") === $purchases_table) {
            $purchases = $wpdb->get_results($wpdb->prepare(
                "SELECT pr.post_id, pr.purchase_price, pr.created_at, p.post_title
                 FROM {$purchases_table} pr
                 LEFT JOIN {$wpdb->posts} p ON pr.post_id = p.ID
                 WHERE pr.user_id = %d AND pr.status = 'completed'
                 ORDER BY pr.created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            ));

            foreach ($purchases as $purchase) {
                $price = (float) ($purchase->purchase_price ?? 0);
                $transactions[] = [
                    'date' => $purchase->created_at,
                    'description' => sprintf(
                        /* translators: %s is the article title */
                        __('Purchased: %s', 'khm-membership'),
                        $purchase->post_title ?: __('Article', 'khm-membership')
                    ),
                    'amount_display' => '-' . $this->format_price($price),
                    'amount_class' => 'khm-credit-negative',
                ];
            }
        }

        $gifts_table = $wpdb->prefix . 'khm_gifts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$gifts_table}'") === $gifts_table) {
            $gifts = $wpdb->get_results($wpdb->prepare(
                "SELECT g.post_id, g.gift_price, g.recipient_email, g.created_at, p.post_title
                 FROM {$gifts_table} g
                 LEFT JOIN {$wpdb->posts} p ON g.post_id = p.ID
                 WHERE g.sender_id = %d AND g.status IN ('sent', 'redeemed')
                 ORDER BY g.created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            ));

            foreach ($gifts as $gift) {
                $price = (float) ($gift->gift_price ?? 0);
                $recipient = $gift->recipient_email ? sprintf(' (%s)', $gift->recipient_email) : '';
                $transactions[] = [
                    'date' => $gift->created_at,
                    'description' => sprintf(
                        /* translators: %s is the article title */
                        __('Gift sent: %s', 'khm-membership'),
                        ($gift->post_title ?: __('Article', 'khm-membership')) . $recipient
                    ),
                    'amount_display' => '-' . $this->format_price($price),
                    'amount_class' => 'khm-credit-negative',
                ];
            }
        }

        usort($transactions, function($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
        });

        return array_slice($transactions, 0, $limit);
    }

    private function get_transaction_total(int $user_id): int {
        global $wpdb;
        $total = 0;

        $usage_table = $wpdb->prefix . 'khm_credit_usage';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$usage_table}'") === $usage_table) {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$usage_table} WHERE user_id = %d",
                $user_id
            ));
            $total += $count;
        }

        $purchases_table = $wpdb->prefix . 'khm_purchases';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'") === $purchases_table) {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$purchases_table} WHERE user_id = %d AND status = 'completed'",
                $user_id
            ));
            $total += $count;
        }

        $gifts_table = $wpdb->prefix . 'khm_gifts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$gifts_table}'") === $gifts_table) {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$gifts_table} WHERE sender_id = %d AND status IN ('sent', 'redeemed')",
                $user_id
            ));
            $total += $count;
        }

        return $total;
    }

    private function format_credit_reason(string $purpose, int $object_id = 0): string {
        $purpose = trim($purpose);
        if ($purpose === 'article_download' && $object_id) {
            $title = get_the_title($object_id);
            if ($title) {
                return sprintf(__('Downloaded: %s', 'khm-membership'), $title);
            }
        }

        if ($purpose !== '') {
            return ucwords(str_replace('_', ' ', $purpose));
        }

        return __('Credit Usage', 'khm-membership');
    }

    private function format_price(float $amount): string {
        $currency = get_option('khm_currency', 'GBP');
        if (function_exists('khm_format_price')) {
            return khm_format_price($amount, $currency);
        }

        return number_format_i18n($amount, 2);
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
