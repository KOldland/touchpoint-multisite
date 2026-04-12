<?php

namespace KHM\Elementor\Widgets;

use Elementor\Widget_Base;

/**
 * Quote Club search results container widget.
 */
class QuoteClubResults_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_quoteclub_results';
    }

    public function get_title() {
        return __('Quote Club Results', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-post-list';
    }

    public function get_categories() {
        return ['touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['quote club', 'results', 'list', 'search'];
    }

    protected function render() {
        $support = new QuoteClubWidgetSupport();
        $support->enqueue_assets();

        if (!is_user_logged_in()) {
            $support->render_login_required(__('Please log in to view Quote Club results.', 'khm-membership'));
            return;
        }

        echo '<div class="khm-quoteclub-results"></div>';
    }
}
