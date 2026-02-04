<?php
/**
 * API Settings View for KH Events
 */

// Only show if this is included in the admin settings
if (!defined('ABSPATH') || !current_user_can('manage_options')) {
    exit;
}
?>

<div class="kh-events-api-settings">
    <h3><?php _e('API Configuration', 'kh-events'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('API Status', 'kh-events'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="kh_events_api_settings[enabled]" value="1" <?php checked(get_option('kh_events_api_settings')['enabled'] ?? 0); ?>>
                    <?php _e('Enable REST API', 'kh-events'); ?>
                </label>
                <p class="description"><?php _e('Enable or disable the KH Events REST API endpoints', 'kh-events'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php _e('Authentication Method', 'kh-events'); ?></th>
            <td>
                <select name="kh_events_api_settings[auth_method]">
                    <?php
                    $auth_methods = array(
                        'api_key' => __('API Key', 'kh-events'),
                        'oauth2' => __('OAuth 2.0', 'kh-events'),
                        'basic_auth' => __('Basic Authentication', 'kh-events')
                    );
                    $current_method = get_option('kh_events_api_settings')['auth_method'] ?? 'api_key';

                    foreach ($auth_methods as $method => $name) {
                        echo '<option value="' . esc_attr($method) . '" ' . selected($current_method, $method, false) . '>' . esc_html($name) . '</option>';
                    }
                    ?>
                </select>
                <p class="description"><?php _e('Choose the authentication method for API access', 'kh-events'); ?></p>
            </td>
        </tr>

        <tr id="api-key-row">
            <th scope="row"><?php _e('API Key', 'kh-events'); ?></th>
            <td>
                <?php
                $settings = get_option('kh_events_api_settings', array());
                $api_key = $settings['api_key'] ?? '';
                ?>
                <input type="text" name="kh_events_api_settings[api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" readonly>
                <button type="button" id="generate-api-key" class="button"><?php _e('Generate New Key', 'kh-events'); ?></button>
                <p class="description"><?php _e('API key for authenticating requests. Keep this secure!', 'kh-events'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php _e('Rate Limiting', 'kh-events'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="kh_events_api_settings[rate_limiting]" value="1" <?php checked($settings['rate_limiting'] ?? 0); ?>>
                    <?php _e('Enable rate limiting', 'kh-events'); ?>
                </label>
                <br>
                <input type="number" name="kh_events_api_settings[rate_limit]" value="<?php echo esc_attr($settings['rate_limit'] ?? 100); ?>" min="1" max="10000" class="small-text">
                <?php _e('requests per hour', 'kh-events'); ?>
                <p class="description"><?php _e('Limit API requests to prevent abuse', 'kh-events'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php _e('API Logging', 'kh-events'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="kh_events_api_settings[logging]" value="1" <?php checked($settings['logging'] ?? 0); ?>>
                    <?php _e('Enable API request logging', 'kh-events'); ?>
                </label>
                <p class="description"><?php _e('Log all API requests for debugging and monitoring', 'kh-events'); ?></p>
            </td>
        </tr>
    </table>

    <h3><?php _e('Feed URLs', 'kh-events'); ?></h3>
    <p><?php _e('Use these URLs to subscribe to your event feeds:', 'kh-events'); ?></p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php _e('Format', 'kh-events'); ?></th>
                <th><?php _e('URL', 'kh-events'); ?></th>
                <th><?php _e('Description', 'kh-events'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php _e('iCalendar (.ics)', 'kh-events'); ?></td>
                <td><code><?php echo esc_url(rest_url('kh-events/v1/feed/ical')); ?></code></td>
                <td><?php _e('Compatible with Google Calendar, Outlook, Apple Calendar', 'kh-events'); ?></td>
            </tr>
            <tr>
                <td><?php _e('JSON', 'kh-events'); ?></td>
                <td><code><?php echo esc_url(rest_url('kh-events/v1/feed/json')); ?></code></td>
                <td><?php _e('For developers and custom integrations', 'kh-events'); ?></td>
            </tr>
            <tr>
                <td><?php _e('RSS', 'kh-events'); ?></td>
                <td><code><?php echo esc_url(rest_url('kh-events/v1/feed/rss')); ?></code></td>
                <td><?php _e('For RSS readers and syndication', 'kh-events'); ?></td>
            </tr>
        </tbody>
    </table>

    <h3><?php _e('API Documentation', 'kh-events'); ?></h3>
    <div class="kh-events-api-docs">
        <h4><?php _e('Authentication', 'kh-events'); ?></h4>
        <p><?php _e('Include your API key in requests using one of these methods:', 'kh-events'); ?></p>
        <ul>
            <li><strong><?php _e('Header:', 'kh-events'); ?></strong> <code>Authorization: Bearer YOUR_API_KEY</code></li>
            <li><strong><?php _e('Query Parameter:', 'kh-events'); ?></strong> <code>?api_key=YOUR_API_KEY</code></li>
            <li><strong><?php _e('Header:', 'kh-events'); ?></strong> <code>X-API-Key: YOUR_API_KEY</code></li>
        </ul>

        <h4><?php _e('Example Requests', 'kh-events'); ?></h4>

        <h5><?php _e('Get Events', 'kh-events'); ?></h5>
        <pre><code>GET <?php echo esc_url(rest_url('kh-events/v1/events')); ?>
Authorization: Bearer YOUR_API_KEY</code></pre>

        <h5><?php _e('Create Event', 'kh-events'); ?></h5>
        <pre><code>POST <?php echo esc_url(rest_url('kh-events/v1/events')); ?>
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "title": "Sample Event",
  "description": "This is a sample event",
  "start_date": "2024-01-15",
  "end_date": "2024-01-15",
  "start_time": "14:00:00",
  "end_time": "16:00:00"
}</code></pre>

        <h5><?php _e('Create Booking', 'kh-events'); ?></h5>
        <pre><code>POST <?php echo esc_url(rest_url('kh-events/v1/bookings')); ?>
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "event_id": 123,
  "attendee_name": "John Doe",
  "attendee_email": "john@example.com",
  "quantity": 2
}</code></pre>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Generate new API key
    $('#generate-api-key').on('click', function() {
        var newKey = '';
        var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for (var i = 0; i < 32; i++) {
            newKey += characters.charAt(Math.floor(Math.random() * characters.length));
        }
        $('input[name="kh_events_api_settings[api_key]"]').val(newKey);
    });

    // Show/hide API key field based on auth method
    $('select[name="kh_events_api_settings[auth_method]"]').on('change', function() {
        if ($(this).val() === 'api_key') {
            $('#api-key-row').show();
        } else {
            $('#api-key-row').hide();
        }
    }).trigger('change');
});
</script>

<style>
.kh-events-api-settings .form-table th {
    width: 200px;
}

.kh-events-api-settings .description {
    margin-top: 5px;
    color: #666;
}

.kh-events-api-docs {
    background: #f9f9f9;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 20px 0;
}

.kh-events-api-docs h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #333;
}

.kh-events-api-docs h5 {
    margin-top: 20px;
    margin-bottom: 5px;
    color: #555;
}

.kh-events-api-docs pre {
    background: #fff;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
    margin: 10px 0;
}

.kh-events-api-docs code {
    font-family: Monaco, Consolas, monospace;
    font-size: 12px;
}

.kh-events-api-docs ul {
    margin: 10px 0;
}

.kh-events-api-docs li {
    margin-bottom: 5px;
}
</style>