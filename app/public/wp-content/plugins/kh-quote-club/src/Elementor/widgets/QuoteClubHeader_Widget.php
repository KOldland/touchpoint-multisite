<?php

namespace QuoteClub\Elementor\Widgets;

use Elementor\Widget_Base;

/**
 * Quote Club header and quick actions widget.
 */
class QuoteClubHeader_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_quoteclub_header';
    }

    public function get_title() {
        return __('Quote Club Header', 'kh-quote-club');
    }

    public function get_icon() {
        return 'eicon-header';
    }

    public function get_categories() {
        return ['touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['quote club', 'header', 'actions', 'member', 'dashboard'];
    }

    protected function render() {
        $support = new QuoteClubWidgetSupport();
        $support->enqueue_assets();

        if (!is_user_logged_in()) {
            $support->render_login_required(__('Please log in to view Quote Club.', 'kh-quote-club'));
            return;
        }

        $urls = $support->get_portal_urls();
        ?>
        <div class="khm-qc-member-header">
            <div>
                <h2><?php esc_html_e('Quote Club Dashboard', 'kh-quote-club'); ?></h2>
                <p><?php esc_html_e('Track your commentary workflow and jump straight into submissions.', 'kh-quote-club'); ?></p>
            </div>
            <div class="khm-qc-member-actions">
                <a href="#khm-qc-search-workspace" class="button button-primary"><?php esc_html_e('Article Search', 'kh-quote-club'); ?></a>
                <a href="<?php echo esc_url($urls['new_press_release_url']); ?>" class="button"><?php esc_html_e('New Press Release', 'kh-quote-club'); ?></a>
                <a href="<?php echo esc_url($urls['buy_credits_url']); ?>" class="button"><?php esc_html_e('Buy Credits', 'kh-quote-club'); ?></a>
            </div>
        </div>
        <?php
    }
}
