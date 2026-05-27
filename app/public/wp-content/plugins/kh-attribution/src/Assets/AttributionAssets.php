<?php
namespace KH\Attribution\Assets;

class AttributionAssets {
    private $manager;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    public function register() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_footer', [$this, 'inject_tracking_code']);
    }

    public function enqueue() {
        if (!$this->manager->should_load_tracking()) return;

        wp_enqueue_script(
            'khm-attribution-tracker',
            plugin_dir_url(__FILE__) . '../../assets/js/attribution-tracker.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('khm-attribution-tracker', 'khmAttribution', array(
            'apiUrl' => rest_url('kh-attribution/v1/'),
            'nonce'  => wp_create_nonce('wp_rest'),
            'attributionWindow' => $this->manager->get_attribution_window(), // Assuming you add this getter
            'debug'  => defined('WP_DEBUG') && WP_DEBUG
        ));
    }


    public function inject_tracking_code() {
        if (!$this->manager->should_load_tracking()) {
            return;
        }
        
        echo '<script type="text/javascript">';
        echo 'window.khmAttributionReady = true;';
        echo 'if (typeof KHMAttributionTracker !== "undefined") { KHMAttributionTracker.init(); }';
        echo '</script>';
    }
}