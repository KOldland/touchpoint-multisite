<?php

namespace QuoteClub\Elementor\Widgets;

use Elementor\Widget_Base;

/**
 * Quote Club session details panel widget.
 */
class QuoteClubSessionDetail_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_quoteclub_session_detail';
    }

    public function get_title() {
        return __('Quote Club Session Detail', 'kh-quote-club');
    }

    public function get_icon() {
        return 'eicon-editor-list-ul';
    }

    public function get_categories() {
        return ['touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['quote club', 'session', 'detail', 'brief'];
    }

    protected function render() {
        $support = new QuoteClubWidgetSupport();
        $support->enqueue_assets();

        if (!is_user_logged_in()) {
            $support->render_login_required(__('Please log in to view Quote Club details.', 'kh-quote-club'));
            return;
        }
        ?>
        <div class="khm-quoteclub-detail">
            <h3><?php esc_html_e('Session details', 'kh-quote-club'); ?></h3>
            <p><?php esc_html_e('Select a result to view the brief and submit commentary.', 'kh-quote-club'); ?></p>
        </div>
        <?php
    }
}
