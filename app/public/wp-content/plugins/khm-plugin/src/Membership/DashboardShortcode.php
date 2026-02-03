<?php

namespace KHM\Membership;

class DashboardShortcode {
    public function __construct() {
        add_shortcode('khm_membership_dashboard', [ $this, 'render_shortcode' ]);
    }

    public function render_shortcode($atts) {
        if ( !is_user_logged_in() ) {
            return '<p>Please log in to view your membership dashboard.</p>';
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $membership_tier_table = $wpdb->prefix . 'membership_tier';

        $query = $wpdb->prepare(
            "SELECT um.status, um.trial_ends_at, mt.name as tier_name
             FROM $user_membership_table um
             LEFT JOIN $membership_tier_table mt ON um.tier_id = mt.id
             WHERE um.user_id = %d",
            $user_id
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        $data = [
            'membership' => $result
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
