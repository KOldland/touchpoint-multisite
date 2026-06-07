<?php
namespace TouchpointCore\Shortcodes;

class StyledExcerpt {
    public function register() {
        if (!shortcode_exists('styled_excerpt')) {
            add_shortcode('styled_excerpt', [$this, 'render_shortcode']);
        }
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        wp_register_script(
            'tp-excerpt-toggle',
            TOUCHPOINT_CORE_URL . 'assets/js/excerpt-toggle.js',
            [],
            '1.0.0',
            true
        );
    }

    public function render_shortcode() {
        wp_enqueue_script('tp-excerpt-toggle');
        
        $full_excerpt = get_the_excerpt();
        $word_limit = 30;
        $words = explode(' ', $full_excerpt);
        $short_excerpt = $full_excerpt;
        
        if (count($words) > $word_limit) {
            $short_excerpt = implode(' ', array_slice($words, 0, $word_limit)) . '…';
        }
        
        $html = '<div class="excerpt-wrapper">';
        $html .= '<strong>' . esc_html__('Summary', 'touchpoint-core') . ':</strong>&nbsp;&nbsp;';
        $html .= '<span class="excerpt-text" data-full="' . esc_attr($full_excerpt) . '" data-short="' . esc_attr($short_excerpt) . '">' . esc_html($short_excerpt) . '</span>';
        $html .= '<button type="button" class="excerpt-toggle" onclick="toggleExcerpt(this)" aria-expanded="false"><em><strong>More</strong></em></button>';
        $html .= '</div>';
        
        return $html;
    }
}
