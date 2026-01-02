<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class KSS_Social_Strip_Widget extends Widget_Base {

    public function get_name() { return 'kss_social_strip'; }
    public function get_title() { return 'KSS Social Strip'; }
    public function get_icon() { return 'eicon-share'; }
    public function get_categories() { return ['general']; }

    protected function _register_controls() {
        $this->start_controls_section('content_section', [
            'label' => __('Social Links', 'kss'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);
        $this->add_control('layout_mode', [
            'label'   => __('Layout Mode', 'kss'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'vertical'          => __('Vertical (Floating Sidebar)', 'kss'),
                'horizontal'        => __('Horizontal (Static/Fixed Bar)', 'kss'),
                'horizontal_mobile' => __('Horizontal (Mobile, No Labels)', 'kss'),
            ],
            'default' => 'vertical',
        ]);
        $this->add_control('pdf_upload', [
            'label' => __('PDF File', 'kss'),
            'type' => Controls_Manager::MEDIA,
            'media_type' => 'application/pdf',
            'default' => [],
        ]);
        $this->add_control('article_price', [
            'label' => __('Article Price (£)', 'kss'),
            'type' => Controls_Manager::NUMBER,
            'min' => 0,
            'step' => 0.01,
            'default' => 0,
        ]);
        $this->end_controls_section();
    }

    protected function render() {
        $settings  = $this->get_settings_for_display();
        $post_id   = get_the_ID();
        $acf_pdf   = function_exists('get_field') ? get_field('kss_pdf_upload', $post_id) : '';
        $acf_price = function_exists('get_field') ? get_field('kss_article_price', $post_id) : 0;

        // Determine PDF url
        if (is_array($acf_pdf) && isset($acf_pdf['url'])) {
            $pdf_url = $acf_pdf['url'];
        } elseif (is_string($acf_pdf) && filter_var($acf_pdf, FILTER_VALIDATE_URL)) {
            $pdf_url = $acf_pdf;
        } elseif (!empty($settings['pdf_upload']['url'])) {
            $pdf_url = $settings['pdf_upload']['url'];
        } else {
            $pdf_url = '';
        }

        $price     = $acf_price ? floatval($acf_price) : (isset($settings['article_price']) ? floatval($settings['article_price']) : 0);
        $icon_base = plugin_dir_url(__DIR__) . 'assets/';
        
        // Pass data to partials
        $data = [
            'pdf_url'   => $pdf_url,
            'price'     => $price,
            'post_id'   => $post_id,
            'icon_base' => $icon_base,
        ];

        // Pick the correct partial based on layout_mode
        if ($settings['layout_mode'] === 'horizontal') {
            $file = dirname(__DIR__) . '/partials/social-strip-horizontal.php';
        } elseif ($settings['layout_mode'] === 'horizontal_mobile') {
            $file = dirname(__DIR__) . '/partials/social-strip-horizontal-mobile.php';
        } else {
            $file = dirname(__DIR__) . '/partials/social-strip-vertical.php';
        }

        if (file_exists($file)) {
            include $file;
        } else {
            echo "<!-- Social strip partial missing: " . esc_html($file) . " -->";
        }
    }
}
