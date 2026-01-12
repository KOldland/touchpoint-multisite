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
 * Portal Downloads Widget
 * 
 * Displays download history with re-download options.
 */
class PortalDownloads_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_portal_downloads';
    }

    public function get_title() {
        return __('Portal Downloads', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-download-kit';
    }

    public function get_categories() {
        return ['touchpoint', 'touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['portal', 'downloads', 'member', 'khm', 'pdf', 'files'];
    }

    public function show_in_panel() {
        return true;
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Downloads Display', 'khm-membership'),
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label' => __('Items per page', 'khm-membership'),
                'type' => Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 50,
            ]
        );

        $this->add_control(
            'show_date',
            [
                'label' => __('Show Download Date', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_credits_used',
            [
                'label' => __('Show Credits Used', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'allow_redownload',
            [
                'label' => __('Allow Re-download', 'khm-membership'),
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
            echo '<p class="khm-portal-login-required">' . esc_html__('Please log in to view your downloads.', 'khm-membership') . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();

        // Get services
        $memberships_repo = new MembershipRepository();
        $levels_repo = new LevelRepository();
        $credits_service = new CreditService($memberships_repo, $levels_repo);
        $library_service = new LibraryService($memberships_repo);
        $downloads_service = new CreditDownloadService($memberships_repo, $credits_service, $library_service);

        // Get saved library items (includes saved and downloaded articles)
        $library_items = $library_service->get_member_library($user_id, [
            'limit' => (int)$settings['per_page'],
        ]);

        $purchased_lookup = [];
        if (!empty($library_items)) {
            global $wpdb;
            $post_ids = array_map(static function($item) {
                return (int) $item->post_id;
            }, $library_items);
            $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
            $sql = "SELECT post_id FROM {$wpdb->prefix}khm_purchases
                WHERE user_id = %d AND status = 'completed' AND post_id IN ({$placeholders})";
            $args = array_merge([$sql, $user_id], $post_ids);
            $query = call_user_func_array([$wpdb, 'prepare'], $args);
            $purchased_ids = $wpdb->get_col($query);
            foreach ($purchased_ids as $post_id) {
                $purchased_lookup[(int) $post_id] = true;
            }
        }

        $this->enqueue_portal_styles();
        $this->enqueue_portal_scripts();
        $accent_color = $settings['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-downloads" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <h3><?php esc_html_e('Your Articles', 'khm-membership'); ?></h3>

            <?php if (!empty($library_items)): ?>
            <div class="khm-downloads-list">
                <?php foreach ($library_items as $item): 
                    $post = get_post($item->post_id);
                    if (!$post) continue;
                    $has_downloaded = $downloads_service->hasDownloaded($user_id, $item->post_id);
                    $is_purchased = !empty($purchased_lookup[(int) $item->post_id]);
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
                            <?php if ($settings['show_date'] === 'yes'): ?>
                            <span class="khm-download-date">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item->created_at))); ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($settings['show_credits_used'] === 'yes' && $has_downloaded): 
                                $download_record = $downloads_service->getDownloadRecord($user_id, $item->post_id);
                                if ($download_record && isset($download_record->credits_used)): ?>
                            <span class="khm-download-credits">
                                <?php printf(esc_html__('%d credit(s)', 'khm-membership'), $download_record->credits_used); ?>
                            </span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($settings['allow_redownload'] === 'yes'): ?>
                    <div class="khm-download-actions">
                        <?php if ($has_downloaded): ?>
                        <button class="khm-redownload-btn" data-post-id="<?php echo esc_attr($item->post_id); ?>" title="<?php esc_attr_e('Re-download PDF', 'khm-membership'); ?>" aria-label="<?php esc_attr_e('Re-download PDF', 'khm-membership'); ?>">
                            <span class="khm-btn-icon dashicons dashicons-download"></span>
                        </button>
                        <?php else: ?>
                        <button class="khm-download-btn" data-post-id="<?php echo esc_attr($item->post_id); ?>" data-credits="<?php echo esc_attr($credit_cost); ?>" title="<?php echo esc_attr(sprintf(__('Download (%d credits)', 'khm-membership'), $credit_cost)); ?>" aria-label="<?php echo esc_attr(sprintf(__('Download (%d credits)', 'khm-membership'), $credit_cost)); ?>">
                            <span class="khm-btn-icon dashicons dashicons-download"></span>
                        </button>
                        <?php endif; ?>
                        <?php if ($is_purchased): ?>
                            <span class="khm-purchased-badge" title="<?php esc_attr_e('Purchased', 'khm-membership'); ?>">$</span>
                        <?php else: ?>
                            <button class="khm-remove-btn" data-post-id="<?php echo esc_attr($item->post_id); ?>" data-title="<?php echo esc_attr(get_the_title($item->post_id)); ?>" title="<?php esc_attr_e('Remove from library', 'khm-membership'); ?>" aria-label="<?php esc_attr_e('Remove from library', 'khm-membership'); ?>">
                                <span class="khm-btn-icon dashicons dashicons-trash"></span>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="khm-empty-state">
                <span class="khm-empty-icon">📥</span>
                <p><?php esc_html_e('No downloads yet. Browse articles to download PDFs.', 'khm-membership'); ?></p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="khm-browse-btn">
                    <?php esc_html_e('Browse Articles', 'khm-membership'); ?>
                </a>
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
                'downloadRestUrl' => esc_url_raw(rest_url('khm/v1/download/')),
                'restNonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }
}
