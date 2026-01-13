<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;
use KHM\Services\EmailService;
use KHM\Services\GiftService;

/**
 * Portal Gifts Sent Widget
 *
 * Displays gifts sent by the logged-in user.
 */
class PortalGiftsSent_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_portal_gifts_sent';
    }

    public function get_title() {
        return __( 'Gifts Sent', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-gift';
    }

    public function get_categories() {
        return ['touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['portal', 'gifts', 'member', 'khm', 'voucher'];
    }

    public function show_in_panel() {
        return true;
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Gifts Display', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label' => __( 'Items per page', 'khm-membership' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 50,
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!is_user_logged_in()) {
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your gifts.', 'khm-membership') . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();

        $plugin_dir = plugin_dir_path(dirname(dirname(dirname(__DIR__))));
        $gift_service = new GiftService(
            new MembershipRepository(),
            new OrderRepository(),
            new EmailService($plugin_dir)
        );

        $per_page = min((int) $settings['per_page'], 5);
        $page = isset($_GET['khm_gift_page']) ? max(1, (int) $_GET['khm_gift_page']) : 1;
        $all_gifts = $gift_service->get_sent_gifts($user_id, 200);
        $groups = $this->group_gifts($all_gifts);
        $total_pages = $per_page > 0 ? (int) ceil(count($groups) / $per_page) : 1;
        if ($page > $total_pages && $total_pages > 0) {
            $page = $total_pages;
        }
        $offset = ($page - 1) * $per_page;
        $gifts = array_slice($groups, $offset, $per_page);

        $this->enqueue_portal_styles();
        ?>
        <div class="khm-portal-gifts">
            <h3><?php esc_html_e('Gifts Sent', 'khm-membership'); ?></h3>

            <?php if (!empty($gifts)): ?>
                <div class="khm-gifts-list">
                    <?php foreach ($gifts as $gift): ?>
                        <details class="khm-gift-item">
                            <summary class="khm-gift-summary">
                                <span class="khm-gift-date"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($gift['created_at']))); ?></span>
                                <span class="khm-gift-title"><?php echo esc_html($gift['summary_title']); ?></span>
                            </summary>
                            <div class="khm-gift-details">
                                <?php foreach ($gift['gifts'] as $entry): ?>
                                    <div class="khm-gift-row">
                                        <span class="khm-gift-label"><?php esc_html_e('Recipient', 'khm-membership'); ?></span>
                                        <span class="khm-gift-value"><?php echo esc_html($entry['recipient_email'] ?? ''); ?></span>
                                    </div>
                                    <div class="khm-gift-row">
                                        <span class="khm-gift-label"><?php esc_html_e('Voucher Code', 'khm-membership'); ?></span>
                                        <span class="khm-gift-value khm-gift-code"><?php echo esc_html($entry['redemption_token'] ?? ''); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="khm-empty-state">
                    <p><?php esc_html_e('No gifts sent yet.', 'khm-membership'); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($total_pages > 1): ?>
            <div class="khm-transactions-pagination">
                <?php
                $base_url = remove_query_arg('khm_gift_page');
                for ($i = 1; $i <= $total_pages; $i++):
                    $url = add_query_arg('khm_gift_page', $i, $base_url);
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

    private function group_gifts(array $gifts): array {
        $groups = [];
        foreach ($gifts as $gift) {
            $order_id = (int) ($gift['order_id'] ?? 0);
            $post_id = (int) ($gift['post_id'] ?? 0);
            $created_at = $gift['created_at'] ?? '';
            $key = $order_id
                ? 'order:' . $order_id
                : 'gift:' . $post_id . ':' . date('Y-m-d H:i', strtotime($created_at));

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'created_at' => $created_at,
                    'post_titles' => [],
                    'gifts' => [],
                ];
            }

            if (!empty($gift['post_title'])) {
                $groups[$key]['post_titles'][$gift['post_title']] = true;
            }
            $groups[$key]['gifts'][] = $gift;
        }

        $grouped = [];
        foreach ($groups as $group) {
            $titles = array_keys($group['post_titles']);
            if (count($titles) === 1) {
                $summary_title = $titles[0];
            } elseif (count($titles) > 1) {
                $summary_title = sprintf(__('Multiple articles (%d)', 'khm-membership'), count($titles));
            } else {
                $summary_title = __('Gifted Article', 'khm-membership');
            }

            $group['summary_title'] = $summary_title;
            $grouped[] = $group;
        }

        usort($grouped, function($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return $grouped;
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
