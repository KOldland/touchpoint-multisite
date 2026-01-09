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
        return ['general', 'touchpoint', 'theme-elements'];
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

        // Get downloads
        $downloads = $downloads_service->getUserDownloads($user_id, [
            'limit' => (int)$settings['per_page'],
        ]);

        $this->enqueue_portal_styles();
        $this->enqueue_portal_scripts();
        $accent_color = $settings['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-downloads" style="--khm-accent: <?php echo esc_attr($accent_color); ?>">
            
            <h3><?php esc_html_e('Your Downloads', 'khm-membership'); ?></h3>

            <?php if (!empty($downloads)): ?>
            <div class="khm-downloads-list">
                <?php foreach ($downloads as $download): 
                    $post = get_post($download->post_id);
                    if (!$post) continue;
                ?>
                <div class="khm-download-item">
                    <div class="khm-download-info">
                        <h4 class="khm-download-title">
                            <a href="<?php echo esc_url(get_permalink($download->post_id)); ?>">
                                <?php echo esc_html(get_the_title($download->post_id)); ?>
                            </a>
                        </h4>
                        <div class="khm-download-meta">
                            <?php if ($settings['show_date'] === 'yes'): ?>
                            <span class="khm-download-date">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($download->created_at))); ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($settings['show_credits_used'] === 'yes' && isset($download->credits_used)): ?>
                            <span class="khm-download-credits">
                                <?php printf(esc_html__('%d credit(s)', 'khm-membership'), $download->credits_used); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($settings['allow_redownload'] === 'yes'): ?>
                    <div class="khm-download-actions">
                        <button class="khm-redownload-btn" data-post-id="<?php echo esc_attr($download->post_id); ?>">
                            <span class="khm-btn-icon">📥</span>
                            <?php esc_html_e('Download Again', 'khm-membership'); ?>
                        </button>
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
                'restNonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }
}
