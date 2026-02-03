<?php

namespace KHM\Membership;

class LandingPageShortcode {
    public function __construct() {
        add_shortcode('khm_landing_page', [ $this, 'render_shortcode' ]);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'schedule_id' => null,
            'sponsor_id' => null,
        ], $atts);

        // Get from query params if not in shortcode
        if (isset($_GET['schedule_id'])) {
            $atts['schedule_id'] = intval($_GET['schedule_id']);
        }
        if (isset($_GET['sponsor_id'])) {
            $atts['sponsor_id'] = intval($_GET['sponsor_id']);
        }

        // Get user phase (mocked)
        $user_phase = 'default';
        if (is_user_logged_in()) {
            // In a real scenario, you would call the user phase endpoint
            // For now, we'll just mock it.
            $user_phases = ['Attention', 'Acceptance', 'Action'];
            $user_phase = $user_phases[array_rand($user_phases)];
        }


        $data = [
            'schedule_id' => $atts['schedule_id'],
            'sponsor_id' => $atts['sponsor_id'],
            'user_phase' => $user_phase,
        ];

        ob_start();
        $this->include_template(plugin_dir_path(__FILE__) . '../../templates/landing-page.php', $data);
        return ob_get_clean();
    }

    private function include_template($template_path, $data = []) {
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
}
