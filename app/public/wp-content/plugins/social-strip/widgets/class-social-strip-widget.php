<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class KSS_Social_Strip_Widget extends Widget_Base {

    public function get_name() { return 'kss_social_strip'; }
    public function get_title() { return 'KSS Social Strip'; }
    public function get_icon() { return 'eicon-share'; }
    public function get_categories() { return ['general']; }

    protected function register_controls() {
        $this->_register_controls();
    }

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
        $meta_pdf  = get_post_meta( $post_id, 'kss_pdf_upload', true );
        $meta_price = get_post_meta( $post_id, 'kss_article_price', true );
        $meta_credit_cost = get_post_meta( $post_id, 'kss_credit_cost', true );

        // Determine PDF url
        if (is_array($acf_pdf) && isset($acf_pdf['url'])) {
            $pdf_url = $acf_pdf['url'];
        } elseif ( is_numeric( $meta_pdf ) ) {
            $pdf_url = wp_get_attachment_url( (int) $meta_pdf );
        } elseif (is_string($acf_pdf) && filter_var($acf_pdf, FILTER_VALIDATE_URL)) {
            $pdf_url = $acf_pdf;
        } elseif ( is_string( $meta_pdf ) && filter_var( $meta_pdf, FILTER_VALIDATE_URL ) ) {
            $pdf_url = $meta_pdf;
        } elseif (!empty($settings['pdf_upload']['url'])) {
            $pdf_url = $settings['pdf_upload']['url'];
        } else {
            $pdf_url = add_query_arg( 'kh_pdf', '1', get_permalink( $post_id ) );
        }

        $price     = $acf_price ? floatval($acf_price) : ( $meta_price ? floatval( $meta_price ) : (isset($settings['article_price']) ? floatval($settings['article_price']) : 0) );
        $credit_cost = $meta_credit_cost ? (int) $meta_credit_cost : 1;
        $icon_base = plugin_dir_url(__DIR__) . 'assets/';
        
        // Pass data to partials
        $data = [
            'pdf_url'   => $pdf_url,
            'price'     => $price,
            'post_id'   => $post_id,
            'icon_base' => $icon_base,
        ];

        $is_elementor_ajax = wp_doing_ajax() && ! empty( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'];
        $is_elementor_edit = class_exists( '\Elementor\Plugin' )
            && ( \Elementor\Plugin::$instance->editor->is_edit_mode()
                || \Elementor\Plugin::$instance->preview->is_preview_mode() );

        $khm_disabled = defined( 'KSS_DISABLE_KHM' ) && KSS_DISABLE_KHM;
        $enhanced_data = ( ! $khm_disabled && ! $is_elementor_ajax && ! $is_elementor_edit && function_exists( 'kss_get_enhanced_widget_data' ) )
            ? kss_get_enhanced_widget_data( $post_id )
            : [];

        $defaults = [
            'post_id'     => $post_id,
            'post_title'  => get_the_title( $post_id ),
            'post_url'    => get_permalink( $post_id ),
            'user_id'     => get_current_user_id(),
            'is_logged_in'=> is_user_logged_in(),
            'credits'     => [ 'available' => 0, 'required' => 1, 'can_download' => false ],
            'library'     => [ 'is_saved' => false, 'can_save' => false ],
            'pricing'     => [ 'base_price' => $price, 'member_price' => $price, 'discount_percent' => 0, 'currency' => '£' ],
            'gift'        => [ 'can_gift' => true, 'price' => $price ],
            'membership'  => [ 'is_member' => false, 'level' => null ],
            'features'    => [ 'can_download' => true, 'can_save' => true, 'can_buy' => true, 'can_gift' => true, 'show_member_benefits' => false ],
            'share'       => [ 'title' => get_the_title( $post_id ), 'url' => get_permalink( $post_id ), 'excerpt' => wp_trim_words( get_post_field( 'post_excerpt', $post_id ) ?: get_post_field( 'post_content', $post_id ), 30 ) ],
            'credits'     => [ 'available' => 0, 'required' => $credit_cost, 'can_download' => false ],
        ];

        $widget_data = array_replace_recursive( $defaults, $data, $enhanced_data );

        // Pick the correct partial based on layout_mode
        if ($settings['layout_mode'] === 'horizontal') {
            $file = dirname(__DIR__) . '/partials/social-strip-horizontal.php';
        } elseif ($settings['layout_mode'] === 'horizontal_mobile') {
            $file = dirname(__DIR__) . '/partials/social-strip-horizontal-mobile.php';
        } else {
            $file = dirname(__DIR__) . '/partials/social-strip-vertical.php';
        }

        if (file_exists($file)) {
            $data = $widget_data;
            include $file;
        } else {
            echo "<!-- Social strip partial missing: " . esc_html($file) . " -->";
        }
    }
}
