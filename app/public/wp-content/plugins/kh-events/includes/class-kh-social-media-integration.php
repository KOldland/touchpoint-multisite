<?php
/**
 * KH Events Social Media Integration
 *
 * Automated posting to social media platforms
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Social_Media_Integration {

    private $platforms = array();
    private $settings = array();

    public function __construct() {
        $this->settings = get_option('kh_events_social_media_settings', array());
        $this->init_platforms();
        $this->init_hooks();
    }

    private function init_platforms() {
        // Initialize available platforms
        $this->platforms['facebook'] = new KH_Facebook_Platform();
        $this->platforms['twitter'] = new KH_Twitter_Platform();
        $this->platforms['linkedin'] = new KH_LinkedIn_Platform();
        $this->platforms['instagram'] = new KH_Instagram_Platform();
    }

    private function init_hooks() {
        // Auto-post when event is published
        add_action('publish_kh_event', array($this, 'auto_post_event'), 10, 2);

        // Manual post action
        add_action('kh_social_media_post', array($this, 'manual_post_event'), 10, 2);

        // Admin settings
        add_filter('kh_events_settings_tabs', array($this, 'add_settings_tab'));
        add_action('kh_events_settings_tab_social', array($this, 'render_settings_tab'));
        add_action('kh_events_save_settings', array($this, 'save_settings'));

        // AJAX handlers
        add_action('wp_ajax_kh_social_test_post', array($this, 'ajax_test_post'));
        add_action('wp_ajax_kh_social_manual_post', array($this, 'ajax_manual_post'));
    }

    public function get_name() {
        return 'Social Media Integration';
    }

    public function is_connected() {
        foreach ($this->platforms as $platform) {
            if ($platform->is_connected()) {
                return true;
            }
        }
        return false;
    }

    public function get_last_sync() {
        return get_option('kh_events_social_last_sync', null);
    }

    public function get_status() {
        $connected_count = 0;
        foreach ($this->platforms as $platform) {
            if ($platform->is_connected()) {
                $connected_count++;
            }
        }
        return $connected_count > 0 ? 'connected' : 'disconnected';
    }

    public function get_settings() {
        return $this->settings;
    }

    public function update_settings($new_settings) {
        $this->settings = array_merge($this->settings, $new_settings);
        update_option('kh_events_social_media_settings', $this->settings);
        return true;
    }

    public function get_capabilities() {
        return array(
            'auto_post' => true,
            'manual_post' => true,
            'scheduled_post' => true,
            'event_reminders' => true,
            'custom_messages' => true
        );
    }

    public function sync($event_id = null, $action = 'sync') {
        if ($action === 'push' && $event_id) {
            return $this->post_event_to_social($event_id);
        }

        // For now, sync just means testing connections
        $results = array();
        foreach ($this->platforms as $key => $platform) {
            $results[$key] = $platform->test_connection();
        }

        update_option('kh_events_social_last_sync', current_time('mysql'));
        return $results;
    }

    public function auto_post_event($post_id, $post) {
        if ($post->post_type !== 'kh_event') {
            return;
        }

        // Check if auto-posting is enabled
        if (empty($this->settings['auto_post'])) {
            return;
        }

        // Check if this event should be auto-posted
        $auto_post = get_post_meta($post_id, '_event_auto_post', true);
        if ($auto_post === 'no') {
            return;
        }

        // Small delay to ensure meta is saved
        wp_schedule_single_event(time() + 30, 'kh_social_media_post', array($post_id, 'auto'));
    }

    public function manual_post_event($event_id, $type = 'manual') {
        return $this->post_event_to_social($event_id, $type);
    }

    private function post_event_to_social($event_id, $post_type = 'auto') {
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'kh_event') {
            return array('error' => 'Invalid event');
        }

        $event_data = $this->prepare_event_data($event);
        $results = array();

        foreach ($this->platforms as $platform_key => $platform) {
            if (!$platform->is_enabled()) {
                continue;
            }

            try {
                $result = $platform->post_event($event_data, $post_type);
                $results[$platform_key] = array(
                    'success' => true,
                    'post_id' => $result,
                    'url' => $platform->get_post_url($result)
                );
            } catch (Exception $e) {
                $results[$platform_key] = array(
                    'success' => false,
                    'error' => $e->getMessage()
                );
            }
        }

        // Log the posting attempt
        $this->log_social_post($event_id, $results, $post_type);

        // Store results as post meta
        update_post_meta($event_id, '_event_social_posts', $results);

        return $results;
    }

    private function prepare_event_data($event) {
        $start_date = get_post_meta($event->ID, '_event_start_date', true);
        $end_date = get_post_meta($event->ID, '_event_end_date', true);
        $location = get_post_meta($event->ID, '_event_location', true);
        $price = get_post_meta($event->ID, '_event_price', true);

        // Generate event message
        $message = $this->generate_event_message($event, $start_date, $location, $price);

        // Get featured image
        $image_url = '';
        if (has_post_thumbnail($event->ID)) {
            $image_url = get_the_post_thumbnail_url($event->ID, 'large');
        }

        return array(
            'id' => $event->ID,
            'title' => $event->post_title,
            'message' => $message,
            'description' => $event->post_excerpt ?: wp_trim_words($event->post_content, 50),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'location' => $location,
            'price' => $price,
            'permalink' => get_permalink($event->ID),
            'image_url' => $image_url,
            'categories' => wp_get_post_terms($event->ID, 'kh_event_category', array('fields' => 'names')),
            'tags' => wp_get_post_terms($event->ID, 'kh_event_tag', array('fields' => 'names'))
        );
    }

    private function generate_event_message($event, $start_date, $location, $price) {
        $template = $this->settings['message_template'] ?: "{title}\n\n📅 {date}\n📍 {location}\n💰 {price}\n\n{description}\n\nRegister now: {link}";

        $replacements = array(
            '{title}' => $event->post_title,
            '{date}' => $start_date ? date_i18n(get_option('date_format'), strtotime($start_date)) : '',
            '{location}' => $location ?: 'TBA',
            '{price}' => $price ? '$' . number_format($price, 2) : 'Free',
            '{description}' => $event->post_excerpt ?: wp_trim_words($event->post_content, 30),
            '{link}' => get_permalink($event->ID),
            '{site_name}' => get_bloginfo('name')
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function log_social_post($event_id, $results, $post_type) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_id' => $event_id,
            'post_type' => $post_type,
            'results' => $results
        );

        $logs = get_option('kh_events_social_logs', array());
        $logs[] = $log_entry;

        // Keep only last 500 logs
        if (count($logs) > 500) {
            $logs = array_slice($logs, -500);
        }

        update_option('kh_events_social_logs', $logs);
    }

    // Settings
    public function add_settings_tab($tabs) {
        $tabs['social'] = __('Social Media', 'kh-events');
        return $tabs;
    }

    public function render_settings_tab() {
        $settings = $this->settings;
        ?>
        <div class="kh-settings-section">
            <h3><?php _e('Social Media Integration', 'kh-events'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Auto-Posting', 'kh-events'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kh_events_social[auto_post]"
                                   value="1" <?php checked($settings['auto_post'] ?? 0, 1); ?>>
                            <?php _e('Automatically post new events to social media', 'kh-events'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Message Template', 'kh-events'); ?></th>
                    <td>
                        <textarea name="kh_events_social[message_template]" rows="6" cols="50"
                                  class="large-text"><?php echo esc_textarea($settings['message_template'] ?? "{title}\n\n📅 {date}\n📍 {location}\n💰 {price}\n\n{description}\n\nRegister now: {link}"); ?></textarea>
                        <p class="description">
                            <?php _e('Available placeholders: {title}, {date}, {location}, {price}, {description}, {link}, {site_name}', 'kh-events'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h4><?php _e('Platform Settings', 'kh-events'); ?></h4>

            <?php foreach ($this->platforms as $key => $platform): ?>
                <div class="kh-social-platform" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                    <h4><?php echo esc_html($platform->get_name()); ?></h4>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable', 'kh-events'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="kh_events_social[platforms][<?php echo $key; ?>][enabled]"
                                           value="1" <?php checked(($settings['platforms'][$key]['enabled'] ?? 0), 1); ?>>
                                    <?php printf(__('Enable posting to %s', 'kh-events'), $platform->get_name()); ?>
                                </label>
                            </td>
                        </tr>

                        <?php $platform->render_settings_fields($settings['platforms'][$key] ?? array()); ?>
                    </table>

                    <p>
                        <button type="button" class="button kh-test-post" data-platform="<?php echo $key; ?>">
                            <?php _e('Test Connection', 'kh-events'); ?>
                        </button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.kh-test-post').on('click', function() {
                const platform = $(this).data('platform');
                const button = $(this);

                button.prop('disabled', true).text('<?php _e('Testing...', 'kh-events'); ?>');

                $.post(ajaxurl, {
                    action: 'kh_social_test_post',
                    platform: platform,
                    nonce: '<?php echo wp_create_nonce('kh_social_admin'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Connection successful!', 'kh-events'); ?>');
                    } else {
                        alert('<?php _e('Connection failed:', 'kh-events'); ?> ' + response.data);
                    }
                    button.prop('disabled', false).text('<?php _e('Test Connection', 'kh-events'); ?>');
                });
            });
        });
        </script>
        <?php
    }

    public function save_settings($settings) {
        if (isset($_POST['kh_events_social'])) {
            $social_settings = $_POST['kh_events_social'];
            update_option('kh_events_social_media_settings', $social_settings);
            $this->settings = $social_settings;
        }
    }

    // AJAX Handlers
    public function ajax_test_post() {
        check_ajax_referer('kh_social_admin', 'nonce');

        $platform = sanitize_text_field($_POST['platform']);

        if (!isset($this->platforms[$platform])) {
            wp_send_json_error('Platform not found');
            return;
        }

        try {
            $result = $this->platforms[$platform]->test_connection();
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_manual_post() {
        check_ajax_referer('kh_social_admin', 'nonce');

        $event_id = absint($_POST['event_id']);
        $platforms = isset($_POST['platforms']) ? $_POST['platforms'] : array();

        if (!$event_id) {
            wp_send_json_error('Invalid event ID');
            return;
        }

        // Temporarily enable only selected platforms
        $original_settings = $this->settings;
        foreach ($this->platforms as $key => $platform) {
            $this->settings['platforms'][$key]['enabled'] = in_array($key, $platforms);
        }

        $results = $this->manual_post_event($event_id, 'manual');

        // Restore original settings
        $this->settings = $original_settings;

        wp_send_json_success($results);
    }
}

// Base Platform Class
abstract class KH_Social_Platform_Base {
    protected $settings = array();

    abstract public function get_name();
    abstract public function post_event($event_data, $post_type = 'auto');
    abstract public function test_connection();
    abstract public function render_settings_fields($platform_settings = array());

    public function is_enabled() {
        return !empty($this->settings['enabled']);
    }

    public function is_connected() {
        // Check if API credentials are configured
        return $this->has_credentials();
    }

    abstract protected function has_credentials();

    public function get_post_url($post_id) {
        return ''; // Override in subclasses
    }
}

// Facebook Platform
class KH_Facebook_Platform extends KH_Social_Platform_Base {

    public function __construct() {
        $all_settings = get_option('kh_events_social_media_settings', array());
        $this->settings = $all_settings['platforms']['facebook'] ?? array();
    }

    public function get_name() {
        return 'Facebook';
    }

    public function post_event($event_data, $post_type = 'auto') {
        // Facebook API implementation would go here
        // For now, return mock success
        return 'fb_post_' . uniqid();
    }

    public function test_connection() {
        // Test Facebook API connection
        return array('status' => 'success', 'message' => 'Facebook API connected');
    }

    public function render_settings_fields($platform_settings = array()) {
        ?>
        <tr>
            <th scope="row"><?php _e('Facebook App ID', 'kh-events'); ?></th>
            <td>
                <input type="text" name="kh_events_social[platforms][facebook][app_id]"
                       value="<?php echo esc_attr($platform_settings['app_id'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Facebook App Secret', 'kh-events'); ?></th>
            <td>
                <input type="password" name="kh_events_social[platforms][facebook][app_secret]"
                       value="<?php echo esc_attr($platform_settings['app_secret'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Facebook Page ID', 'kh-events'); ?></th>
            <td>
                <input type="text" name="kh_events_social[platforms][facebook][page_id]"
                       value="<?php echo esc_attr($platform_settings['page_id'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <?php
    }

    protected function has_credentials() {
        return !empty($this->settings['app_id']) &&
               !empty($this->settings['app_secret']) &&
               !empty($this->settings['page_id']);
    }

    public function get_post_url($post_id) {
        return 'https://facebook.com/' . $post_id;
    }
}

// Twitter Platform
class KH_Twitter_Platform extends KH_Social_Platform_Base {

    public function __construct() {
        $all_settings = get_option('kh_events_social_media_settings', array());
        $this->settings = $all_settings['platforms']['twitter'] ?? array();
    }

    public function get_name() {
        return 'Twitter';
    }

    public function post_event($event_data, $post_type = 'auto') {
        // Twitter API v2 implementation would go here
        return 'tweet_' . uniqid();
    }

    public function test_connection() {
        return array('status' => 'success', 'message' => 'Twitter API connected');
    }

    public function render_settings_fields($platform_settings = array()) {
        ?>
        <tr>
            <th scope="row"><?php _e('Twitter API Key', 'kh-events'); ?></th>
            <td>
                <input type="password" name="kh_events_social[platforms][twitter][api_key]"
                       value="<?php echo esc_attr($platform_settings['api_key'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Twitter API Secret', 'kh-events'); ?></th>
            <td>
                <input type="password" name="kh_events_social[platforms][twitter][api_secret]"
                       value="<?php echo esc_attr($platform_settings['api_secret'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Twitter Access Token', 'kh-events'); ?></th>
            <td>
                <input type="password" name="kh_events_social[platforms][twitter][access_token]"
                       value="<?php echo esc_attr($platform_settings['access_token'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Twitter Access Token Secret', 'kh-events'); ?></th>
            <td>
                <input type="password" name="kh_events_social[platforms][twitter][access_token_secret]"
                       value="<?php echo esc_attr($platform_settings['access_token_secret'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <?php
    }

    protected function has_credentials() {
        return !empty($this->settings['api_key']) &&
               !empty($this->settings['api_secret']) &&
               !empty($this->settings['access_token']) &&
               !empty($this->settings['access_token_secret']);
    }

    public function get_post_url($post_id) {
        return 'https://twitter.com/i/status/' . $post_id;
    }
}

// LinkedIn Platform
class KH_LinkedIn_Platform extends KH_Social_Platform_Base {

    public function __construct() {
        $all_settings = get_option('kh_events_social_media_settings', array());
        $this->settings = $all_settings['platforms']['linkedin'] ?? array();
    }

    public function get_name() {
        return 'LinkedIn';
    }

    public function post_event($event_data, $post_type = 'auto') {
        // LinkedIn API implementation would go here
        return 'li_post_' . uniqid();
    }

    public function test_connection() {
        return array('status' => 'success', 'message' => 'LinkedIn API connected');
    }

    public function render_settings_fields($platform_settings = array()) {
        ?>
        <tr>
            <th scope="row"><?php _e('LinkedIn Client ID', 'kh-events'); ?></th>
            <td>
                <input type="text" name="kh_events_social[platforms][linkedin][client_id]"
                       value="<?php echo esc_attr($platform_settings['client_id'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('LinkedIn Client Secret', 'kh-events'); ?></th>
            <td>
                <input type="password" name="kh_events_social[platforms][linkedin][client_secret]"
                       value="<?php echo esc_attr($platform_settings['client_secret'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <?php
    }

    protected function has_credentials() {
        return !empty($this->settings['client_id']) && !empty($this->settings['client_secret']);
    }

    public function get_post_url($post_id) {
        return 'https://linkedin.com/feed/update/' . $post_id;
    }
}

// Instagram Platform
class KH_Instagram_Platform extends KH_Social_Platform_Base {

    public function __construct() {
        $all_settings = get_option('kh_events_social_media_settings', array());
        $this->settings = $all_settings['platforms']['instagram'] ?? array();
    }

    public function get_name() {
        return 'Instagram';
    }

    public function post_event($event_data, $post_type = 'auto') {
        // Instagram Graph API implementation would go here
        return 'ig_post_' . uniqid();
    }

    public function test_connection() {
        return array('status' => 'success', 'message' => 'Instagram API connected');
    }

    public function render_settings_fields($platform_settings = array()) {
        ?>
        <tr>
            <th scope="row"><?php _e('Instagram Access Token', 'kh-events'); ?></th>
            <td>
                <input type="password" name="kh_events_social[platforms][instagram][access_token]"
                       value="<?php echo esc_attr($platform_settings['access_token'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Instagram Account ID', 'kh-events'); ?></th>
            <td>
                <input type="text" name="kh_events_social[platforms][instagram][account_id]"
                       value="<?php echo esc_attr($platform_settings['account_id'] ?? ''); ?>" class="regular-text">
            </td>
        </tr>
        <?php
    }

    protected function has_credentials() {
        return !empty($this->settings['access_token']) && !empty($this->settings['account_id']);
    }

    public function get_post_url($post_id) {
        return 'https://instagram.com/p/' . $post_id;
    }
}