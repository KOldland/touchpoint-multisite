<?php

namespace KHM\PublicFrontend;

use KHM\Services\MembershipRepository;

class DashboardShortcode {
    private MembershipRepository $memberships;

    public function __construct( ?MembershipRepository $memberships = null ) {
        $this->memberships = $memberships ?: new MembershipRepository();
        if ( function_exists( 'add_shortcode' ) ) {
            add_shortcode('khm_membership_dashboard', [ $this, 'render_shortcode' ]);
        }
    }

    public function render_shortcode($atts) {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in to view your membership dashboard.</p>';
        }

        $user_id = get_current_user_id();

        $active_memberships = $this->memberships->findActive( $user_id );
        
        $membership_data = null;
        if ( ! empty( $active_memberships ) ) {
            $current = $active_memberships[0];
            $membership_data = [
                'status'        => $current->status ?? 'active',
                'trial_ends_at' => null, // Legacy field not strictly defined in base model, left null unless mapped
                'tier_name'     => $current->level_name ?? '',
            ];
        }

        $data = [
            'membership' => $membership_data
        ];

        ob_start();
        $this->include_template(plugin_dir_path(__FILE__) . '../../templates/membership-dashboard.php', $data);
        return ob_get_clean();
    }

    private function include_template($template_path, $data = []) {
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
}
