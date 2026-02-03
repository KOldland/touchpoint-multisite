<?php
/*
Plugin Name: KH Ad Manager
Description: Custom Ad Manager for Touchpoint theme. Modular, clean, and no monkeys allowed.
Version: 0.1
Author: Kirsty Hennah
*/

defined('ABSPATH') || exit;

// Define paths
define('AM_PATH', plugin_dir_path(__FILE__));
define('AM_URL', plugin_dir_url(__FILE__));
define('AM_FIELD_AD_SLOT', 'field_68652678baa37');

/**
 * Detect Elementor/builder preview to avoid loading front-end popups/scripts in the editor.
 *
 * @return bool
 */
function kh_ad_manager_is_builder_preview() {
    // Elementor editor iframe sets this.
    if ( defined( 'ELEMENTOR_EDITOR' ) && ELEMENTOR_EDITOR ) {
        return true;
    }

    // Elementor preview param on frontend.
    if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return true;
    }

    // Fallback: ask Elementor if available.
    if ( class_exists( '\Elementor\Plugin' ) ) {
        $plugin = \Elementor\Plugin::$instance;
        if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
            return true;
        }
    }

    return false;
}

// Load translations after init to satisfy WP timing rules.
add_action('init', function() {
    load_plugin_textdomain('kh-ad-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
});


// Core includes
require_once AM_PATH . 'includes/cpt-ad-unit.php';
require_once AM_PATH . 'includes/ad-helper.php';
require_once AM_PATH . 'includes/campaigns.php';
require_once AM_PATH . 'includes/cpt-sponsor.php';
require_once AM_PATH . 'includes/sponsor-api.php';
require_once AM_PATH . 'includes/sponsor-admin.php';
require_once AM_PATH . 'includes/sponsor-approval-api.php';
require_once AM_PATH . 'includes/sponsor-approvals-panel.php';
require_once AM_PATH . 'includes/tracking.php';
require_once AM_PATH . 'includes/admin-columns.php';
require_once AM_PATH . 'includes/render-ad.php';
require_once AM_PATH . 'includes/ad-slot-tax.php';
require_once AM_PATH . 'includes/ad-preview.php';
require_once AM_PATH . 'includes/meta-compat.php';
require_once AM_PATH . 'includes/admin-ui.php';

register_activation_hook(__FILE__, 'kh_ad_manager_activate');
add_action('plugins_loaded', 'kh_ad_manager_maybe_update_tables');

function kh_ad_manager_get_events_table_sql() {
    global $wpdb;

    $table = $wpdb->prefix . 'kh_ad_events';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        ad_id bigint(20) unsigned NOT NULL,
        event_type varchar(20) NOT NULL,
        slot varchar(100) DEFAULT '',
        campaign_id bigint(20) unsigned DEFAULT 0,
        user_id bigint(20) unsigned DEFAULT 0,
        membership_type varchar(50) DEFAULT '',
        referer text,
        user_agent varchar(255) DEFAULT '',
        ip_hash char(64) DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY ad_idx (ad_id, event_type),
        KEY created_at (created_at)
    ) {$charset_collate};";
}

function kh_ad_manager_activate() {
    kh_ad_manager_maybe_update_tables();
}

function kh_ad_manager_maybe_update_tables() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta(kh_ad_manager_get_events_table_sql());
}

// Admin UI cleanup
add_action('add_meta_boxes', function() {
    remove_meta_box('ad-slotdiv', 'ad_unit', 'side');
    remove_meta_box('categorydiv', 'ad_unit', 'side');
}, 99);

add_action('admin_head', function() {
    echo '<style>
        #ad-slot-ad_unit .category-add, 
        #ad-slot-ad_unit .tagcloud {
            display: none !important;
        }
    </style>';
});

// ACF: Validate image dimensions based on slot rules
    add_filter('acf/validate_value/name=ad_image', function($valid, $value, $field, $input) {
        if (!$valid || !$value) return $valid;
        
        $acf_field_key = AM_FIELD_AD_SLOT;
        $ad_slots = $_POST['acf'][$acf_field_key] ?? [];
        
        if (empty($ad_slots)) return 'Please select at least one Ad Slot.';
        
        $img = wp_get_attachment_metadata($value);
        if (!$img || empty($img['width']) || empty($img['height'])) return $valid;
        
        $rules = kh_get_slot_exact_dimensions();
        $imageSlots = array_keys($rules); // <- Safe zone
        
        foreach ($ad_slots as $slot_id) {
            $term = get_term($slot_id, 'ad-slot');
            if (!$term || is_wp_error($term)) continue;
            
            $slug = $term->slug;
            if (!in_array($slug, $imageSlots)) continue;
            
            $expected = $rules[$slug];
            $w = $img['width'];
            $h = $img['height'];
            
            if ($w != $expected['width'] || $h != $expected['height']) {
                return "Image must be exactly {$expected['width']}×{$expected['height']} pixels for slot: {$slug}. Yours is {$w}×{$h}. Do better.";
            }
        }
        
        return $valid;
    }, 10, 4);



// Enqueue frontend assets
add_action('wp_enqueue_scripts', function() {
    if ( defined( 'KHM_DISABLE_ADS' ) && KHM_DISABLE_ADS ) {
        return;
    }
    if ( kh_ad_manager_is_builder_preview() ) {
        return;
    }

    wp_enqueue_style('ad-manager-frontend', AM_URL . 'assets/ad-manager-frontend.css', [], '0.1');
    wp_enqueue_script('ad-manager-frontend', AM_URL . 'assets/ad-manager-frontend.js', [], '0.1', true);

    wp_localize_script('ad-manager-frontend', 'khAdManager', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('kh_ad_click'),
        'slotSchedules' => kh_ad_manager_get_overlay_schedules(),
    ]);
});

// Enqueue admin assets on ad_unit screen only
add_action('admin_enqueue_scripts', function() {
    // ensure sponsorOptions exists (avoid ReferenceError)
    wp_add_inline_script('jquery', 'window.sponsorOptions = window.sponsorOptions || {};', 'before');

    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'ad_unit') {
        wp_enqueue_style('ad-manager-admin', AM_URL . 'assets/ad-manager-admin.css', [], '0.1');
        wp_enqueue_script('ad-manager-admin', AM_URL . 'assets/ad-manager-admin.js', ['jquery'], '0.1', true);
    }
});

// Optional public footer injection
add_action('wp_footer', function() {
    if ( defined( 'KHM_DISABLE_ADS' ) && KHM_DISABLE_ADS ) {
        return;
    }
    if ( kh_ad_manager_is_builder_preview() ) {
        return;
    }

    include AM_PATH . 'partials/ad-popups.php';
});

// Ad options page menu
    add_action('acf/init', function() {
        if (function_exists('acf_add_options_page')) {
            acf_add_options_page([
                'page_title' => 'KH Ad Settings',
                'menu_title' => 'Ad Settings',
                'menu_slug'  => 'kh-ad-settings',
                'capability' => 'edit_posts',
                'redirect'   => false
            ]);
        }
    });

// Move Ad Slot Overrides field group to the editor sidebar
    add_filter('acf/render_field/key=field_68652678baa37', function($field) {
        if (!empty($field['value']) && is_numeric($field['value'])) {
            $term = get_term($field['value'], 'ad-slot');
            if ($term && !is_wp_error($term)) {
                $field_key = esc_attr($field['key']);
                $slug      = esc_js($term->slug);
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var slotField = document.querySelector('#acf-field_{$field_key}');
                        if (slotField) { slotField.setAttribute('data-selected-slot', '{$slug}'); }
                    });
                </script>";
            }
        }
        return $field;
    });


add_filter('acf/location/screen', function($screen) {
    if (!empty($screen['post_type']) && $screen['post_type'] === 'post') {
        // Tell ACF to use metabox position in the block editor
        $screen['block_editor'] = true;
    }
    return $screen;
});
    
    
add_filter('acf/location/rule_match', function($match, $rule, $screen) {
        if ($rule['param'] !== 'post_type' || $rule['value'] !== 'ad_unit') return $match;
        
        $post_id = $screen['post_id'] ?? get_the_ID();
        
        // Allow if new post (so ACF UI doesn't break)
        if (!$post_id || str_starts_with($post_id, 'new_')) {
            return true;
        }
        
        // Only show if one of the special slots is selected
        $terms = wp_get_post_terms($post_id, 'ad-slot', ['fields' => 'slugs']);
        return in_array('slide-in', $terms) || in_array('pop-up', $terms);
    }, 10, 3);


function kh_ad_manager_get_overlay_schedules() {
    $slots = [];
    foreach (kh_ad_manager_overlay_slots() as $slot => $label) {
        $defaults = [
            'central-popup'  => 8000,
            'exit-intent'    => 0,
            'bottom-slidein' => 15000,
            'top-ticker'     => 5000,
        ];

        $element_ids = [
            'central-popup'  => 'central-popup-ad',
            'exit-intent'    => 'exit-intent-ad',
            'bottom-slidein' => 'bottom-slidein-ad',
            'top-ticker'     => 'top-ticker-ad',
        ];

        $triggers = [
            'central-popup'  => 'delay',
            'exit-intent'    => 'exit_intent',
            'bottom-slidein' => 'delay',
            'top-ticker'     => 'delay',
        ];

        $option_base = 'kh_' . str_replace('-', '_', $slot);
        $delay_value = get_option($option_base . '_delay', isset($defaults[$slot]) ? $defaults[$slot] : 0);
        $enabled_value = get_option($option_base . '_enabled', true);

        $slots[$slot] = [
            'elementId' => $element_ids[$slot] ?? '',
            'trigger'   => $triggers[$slot] ?? 'delay',
            'delay'     => (int) $delay_value,
            'enabled'   => (bool) $enabled_value,
        ];
    }

    return apply_filters('kh_ad_manager_overlay_schedules', $slots);
}

function kh_ad_manager_overlay_slots() {
    return [
        'central-popup'   => __('Central Pop-Up', 'kh-ad-manager'),
        'exit-intent'     => __('Exit Intent', 'kh-ad-manager'),
        'bottom-slidein'  => __('Bottom Slide-In', 'kh-ad-manager'),
        'top-ticker'      => __('Top Ticker', 'kh-ad-manager'),
    ];
}

add_action('admin_menu', function() {
    add_options_page(
        __('Ad Overlay Settings', 'kh-ad-manager'),
        __('Ad Overlays', 'kh-ad-manager'),
        'manage_options',
        'kh-ad-overlays',
        'kh_ad_manager_render_overlay_settings_page'
    );
});

add_action('admin_init', 'kh_ad_manager_register_overlay_settings');

function kh_ad_manager_register_overlay_settings() {
    foreach (kh_ad_manager_overlay_slots() as $slot => $label) {
        $base = 'kh_' . str_replace('-', '_', $slot);
        register_setting('kh_ad_overlay_settings', $base . '_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => function($value) {
                return (bool) $value;
            },
            'default' => true,
        ]);

        register_setting('kh_ad_overlay_settings', $base . '_delay', [
            'type' => 'integer',
            'sanitize_callback' => function($value) {
                $int = (int) $value;
                return max(0, $int);
            },
            'default' => kh_ad_manager_get_overlay_default_delay($slot),
        ]);
    }
}

function kh_ad_manager_render_overlay_settings_page() {
    if (! current_user_can('manage_options')) {
        return;
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Ad Overlay Settings', 'kh-ad-manager'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('kh_ad_overlay_settings'); ?>
            <table class="widefat fixed" style="max-width:800px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Overlay', 'kh-ad-manager'); ?></th>
                        <th><?php esc_html_e('Enabled', 'kh-ad-manager'); ?></th>
                        <th><?php esc_html_e('Delay (milliseconds)', 'kh-ad-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (kh_ad_manager_overlay_slots() as $slot => $label) :
                        $base = 'kh_' . str_replace('-', '_', $slot);
                        $enabled = get_option($base . '_enabled', true);
                        $delay   = get_option($base . '_delay', kh_ad_manager_get_overlay_default_delay($slot));
                    ?>
                    <tr>
                        <td><label for="<?php echo esc_attr($base . '_delay'); ?>"><?php echo esc_html($label); ?></label></td>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr($base . '_enabled'); ?>" value="0" />
                            <input type="checkbox" name="<?php echo esc_attr($base . '_enabled'); ?>" value="1" <?php checked($enabled, true); ?> />
                        </td>
                        <td><input type="number" min="0" step="100" name="<?php echo esc_attr($base . '_delay'); ?>" id="<?php echo esc_attr($base . '_delay'); ?>" value="<?php echo esc_attr($delay); ?>" /></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function kh_ad_manager_get_overlay_default_delay($slot) {
    switch ($slot) {
        case 'central-popup':
            return 8000;
        case 'exit-intent':
            return 0;
        case 'bottom-slidein':
            return 15000;
        case 'top-ticker':
            return 5000;
        default:
            return 0;
    }
}


add_shortcode('kh_ad', function($atts) {
    $atts = shortcode_atts([
        'slot'        => '',
        'category_id' => 0,
    ], $atts, 'kh_ad');

    if (! $atts['slot']) {
        return '';
    }

    ob_start();
    kh_render_ad_slot($atts['slot'], [
        'category_id' => (int) $atts['category_id'],
    ]);
    return ob_get_clean();
});

// Elementor widget registration
add_action('elementor/widgets/register', function($widgets_manager) {
    if (! class_exists('\Elementor\Widget_Base')) {
        return;
    }

    // Ensure our widget file is loaded.
    if (file_exists(__DIR__ . '/includes/Elementor/Ad_Widget.php')) {
        require_once __DIR__ . '/includes/Elementor/Ad_Widget.php';
    }

    if (class_exists('\KHAdManager\Elementor\Ad_Widget')) {
        $widgets_manager->register(new \KHAdManager\Elementor\Ad_Widget());
    }
});
