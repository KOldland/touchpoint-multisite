<?php

namespace KHM\Elementor\Widgets;

use Elementor\Widget_Base;

/**
 * Quote Club KPI cards widget.
 */
class QuoteClubStats_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_quoteclub_stats';
    }

    public function get_title() {
        return __('Quote Club Stats', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-counter';
    }

    public function get_categories() {
        return ['touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['quote club', 'stats', 'credits', 'dashboard', 'cards'];
    }

    protected function render() {
        $support = new QuoteClubWidgetSupport();
        $support->enqueue_assets();

        if (!is_user_logged_in()) {
            $support->render_login_required(__('Please log in to view Quote Club stats.', 'khm-membership'));
            return;
        }

        $stats = $support->get_stats_context(get_current_user_id());
        ?>
        <div class="khm-qc-member-stats">
            <article class="khm-qc-member-card">
                <span class="khm-qc-member-value"><?php echo esc_html($stats['editorial_credits']); ?></span>
                <span class="khm-qc-member-label"><?php esc_html_e('Editorial Credits', 'khm-membership'); ?></span>
            </article>
            <article class="khm-qc-member-card">
                <span class="khm-qc-member-value"><?php echo esc_html($stats['press_release_credits']); ?></span>
                <span class="khm-qc-member-label"><?php esc_html_e('Press Release Credits', 'khm-membership'); ?></span>
            </article>
            <article class="khm-qc-member-card">
                <span class="khm-qc-member-value"><?php echo esc_html($stats['my_drafts']); ?></span>
                <span class="khm-qc-member-label"><?php esc_html_e('Drafts In Progress', 'khm-membership'); ?></span>
            </article>
            <article class="khm-qc-member-card">
                <span class="khm-qc-member-value"><?php echo esc_html($stats['pending_review']); ?></span>
                <span class="khm-qc-member-label"><?php esc_html_e('Awaiting Review', 'khm-membership'); ?></span>
            </article>
            <article class="khm-qc-member-card">
                <span class="khm-qc-member-value"><?php echo esc_html($stats['published_count']); ?></span>
                <span class="khm-qc-member-label"><?php esc_html_e('Live To Date', 'khm-membership'); ?></span>
            </article>
        </div>
        <?php
    }
}
