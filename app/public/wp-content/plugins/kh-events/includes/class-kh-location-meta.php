<?php
/**
 * Location Meta Box Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Location_Meta {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'kh_location_details',
            __('Location Details', 'kh-events'),
            array($this, 'render_meta_box'),
            'kh_location',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('kh_location_meta_nonce', 'kh_location_meta_nonce');

        $address = get_post_meta($post->ID, '_kh_location_address', true);
        $city = get_post_meta($post->ID, '_kh_location_city', true);
        $state = get_post_meta($post->ID, '_kh_location_state', true);
        $zip = get_post_meta($post->ID, '_kh_location_zip', true);
        $country = get_post_meta($post->ID, '_kh_location_country', true);
        $lat = get_post_meta($post->ID, '_kh_location_lat', true);
        $lng = get_post_meta($post->ID, '_kh_location_lng', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="kh_location_address"><?php _e('Address', 'kh-events'); ?></label></th>
                <td><input type="text" id="kh_location_address" name="kh_location_address" value="<?php echo esc_attr($address); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="kh_location_city"><?php _e('City', 'kh-events'); ?></label></th>
                <td><input type="text" id="kh_location_city" name="kh_location_city" value="<?php echo esc_attr($city); ?>" /></td>
            </tr>
            <tr>
                <th><label for="kh_location_state"><?php _e('State/Province', 'kh-events'); ?></label></th>
                <td><input type="text" id="kh_location_state" name="kh_location_state" value="<?php echo esc_attr($state); ?>" /></td>
            </tr>
            <tr>
                <th><label for="kh_location_zip"><?php _e('ZIP/Postal Code', 'kh-events'); ?></label></th>
                <td><input type="text" id="kh_location_zip" name="kh_location_zip" value="<?php echo esc_attr($zip); ?>" /></td>
            </tr>
            <tr>
                <th><label for="kh_location_country"><?php _e('Country', 'kh-events'); ?></label></th>
                <td><input type="text" id="kh_location_country" name="kh_location_country" value="<?php echo esc_attr($country); ?>" /></td>
            </tr>
            <tr>
                <th><?php _e('Map Preview', 'kh-events'); ?></th>
                <td>
                    <div id="kh_location_map" style="width: 100%; height: 300px; border: 1px solid #ccc;"></div>
                    <input type="hidden" id="kh_location_lat" name="kh_location_lat" value="<?php echo esc_attr($lat); ?>" />
                    <input type="hidden" id="kh_location_lng" name="kh_location_lng" value="<?php echo esc_attr($lng); ?>" />
                    <p><button type="button" id="update_map" class="button"><?php _e('Update Map', 'kh-events'); ?></button></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function enqueue_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        global $post;
        if ('kh_location' !== $post->post_type) {
            return;
        }

        // Get Google Maps API key from settings
        $maps_settings = get_option('kh_events_maps_settings', array());
        $api_key = $maps_settings['google_maps_api_key'] ?? '';

        if (empty($api_key)) {
            echo '<div class="notice notice-warning"><p>' . __('Google Maps API key is required for location mapping. Please configure it in KH Events Settings.', 'kh-events') . '</p></div>';
            return;
        }

        wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&libraries=places', array(), null, true);
        wp_enqueue_script('kh-location-map', KH_EVENTS_URL . 'assets/js/location-map.js', array('jquery', 'google-maps'), KH_EVENTS_VERSION, true);
        wp_localize_script('kh-location-map', 'kh_location_vars', array(
            'lat' => get_post_meta($post->ID, '_kh_location_lat', true) ?: '40.7128',
            'lng' => get_post_meta($post->ID, '_kh_location_lng', true) ?: '-74.0060',
        ));
    }

    public function save_meta($post_id) {
        if (!isset($_POST['kh_location_meta_nonce']) || !wp_verify_nonce($_POST['kh_location_meta_nonce'], 'kh_location_meta_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $fields = array(
            'kh_location_address' => '_kh_location_address',
            'kh_location_city' => '_kh_location_city',
            'kh_location_state' => '_kh_location_state',
            'kh_location_zip' => '_kh_location_zip',
            'kh_location_country' => '_kh_location_country',
            'kh_location_lat' => '_kh_location_lat',
            'kh_location_lng' => '_kh_location_lng',
        );

        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }
    }
}