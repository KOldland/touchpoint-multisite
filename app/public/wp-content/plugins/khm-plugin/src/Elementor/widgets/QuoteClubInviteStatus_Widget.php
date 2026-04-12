<?php

namespace KHM\Elementor\Widgets;

use Elementor\Widget_Base;

/**
 * Quote Club invite status region widget.
 */
class QuoteClubInviteStatus_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_quoteclub_invite_status';
    }

    public function get_title() {
        return __('Quote Club Invite Status', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-info-circle';
    }

    public function get_categories() {
        return ['touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['quote club', 'invite', 'status'];
    }

    protected function render() {
        $support = new QuoteClubWidgetSupport();
        $support->enqueue_assets();

        if (!is_user_logged_in()) {
            $support->render_login_required(__('Please log in to view Quote Club.', 'khm-membership'));
            return;
        }

        echo '<div class="khm-quoteclub-invite-status" role="status" aria-live="polite"></div>';
    }
}
