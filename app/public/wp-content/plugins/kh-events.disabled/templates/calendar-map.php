<?php
/**
 * Map View Template
 *
 * Override this template by copying it to yourtheme/kh-events/calendar-map.php
 *
 * @package KH-Events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables available:
// $atts - Shortcode attributes
// $events - Array of events with location data
// $api_key - Google Maps API key
// $default_zoom - Default map zoom level
// $event_data - JSON data for JavaScript
?>

<?php if (empty($api_key)): ?>
    <div class="kh-events-notice kh-events-notice-warning">
        <?php _e('Google Maps API key is required to display the map. Please configure it in KH Events Settings.', 'kh-events'); ?>
    </div>
<?php else: ?>
    <div class="kh-events-map-container">
        <div id="kh-events-map" style="height: <?php echo esc_attr($atts['height']); ?>; width: 100%;"></div>

        <?php if (!empty($events)): ?>
            <div class="kh-map-events-list">
                <h3><?php _e('Events on Map', 'kh-events'); ?></h3>
                <div class="kh-map-events">
                    <?php foreach ($events as $event): ?>
                        <div class="kh-map-event-item" data-event-id="<?php echo esc_attr($event['id']); ?>">
                            <div class="kh-map-event-title">
                                <a href="<?php echo esc_url($event['permalink']); ?>">
                                    <?php echo esc_html($event['title']); ?>
                                </a>
                            </div>
                            <div class="kh-map-event-date">
                                <?php echo esc_html($event['date']); ?>
                                <?php if ($event['time']): ?>
                                    at <?php echo esc_html($event['time']); ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($event['location']): ?>
                                <div class="kh-map-event-location">
                                    <?php echo esc_html($event['location']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Initialize map when Google Maps API is loaded
        function initKHMap() {
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                setTimeout(initKHMap, 100);
                return;
            }

            var mapOptions = {
                zoom: <?php echo intval($default_zoom); ?>,
                center: {lat: 40.7128, lng: -74.0060}, // Default to NYC, will be overridden by bounds
                mapTypeId: google.maps.MapTypeId.ROADMAP
            };

            var map = new google.maps.Map(document.getElementById('kh-events-map'), mapOptions);
            var bounds = new google.maps.LatLngBounds();
            var markers = [];
            var infoWindows = [];

            // Event data from PHP
            var events = <?php echo wp_json_encode($event_data); ?>;

            events.forEach(function(eventData, index) {
                if (eventData.lat && eventData.lng) {
                    var position = {lat: parseFloat(eventData.lat), lng: parseFloat(eventData.lng)};

                    var marker = new google.maps.Marker({
                        position: position,
                        map: map,
                        title: eventData.title,
                        eventId: eventData.id
                    });

                    var infoWindow = new google.maps.InfoWindow({
                        content: '<div class="kh-map-info-window">' +
                            '<h4><a href="' + eventData.permalink + '">' + eventData.title + '</a></h4>' +
                            '<p><strong>Date:</strong> ' + eventData.date + '</p>' +
                            (eventData.time ? '<p><strong>Time:</strong> ' + eventData.time + '</p>' : '') +
                            (eventData.location ? '<p><strong>Location:</strong> ' + eventData.location + '</p>' : '') +
                            (eventData.address ? '<p><strong>Address:</strong> ' + eventData.address + '</p>' : '') +
                            '</div>'
                    });

                    marker.addListener('click', function() {
                        // Close other info windows
                        infoWindows.forEach(function(iw) { iw.close(); });
                        infoWindow.open(map, marker);
                    });

                    markers.push(marker);
                    infoWindows.push(infoWindow);
                    bounds.extend(position);
                }
            });

            // Fit map to show all markers
            if (markers.length > 0) {
                map.fitBounds(bounds);

                // Prevent zoom too close for single marker
                google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
                    if (map.getZoom() > 15) {
                        map.setZoom(15);
                    }
                });
            }

            // Store map reference for external access
            window.khEventsMap = map;
            window.khMapMarkers = markers;
        }

        // Initialize map
        initKHMap();
    </script>
<?php endif; ?>