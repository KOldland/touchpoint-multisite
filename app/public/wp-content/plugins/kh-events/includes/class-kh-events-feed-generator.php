<?php
/**
 * KH Events Feed Generator
 *
 * Generates various feed formats (iCal, JSON, RSS) for events
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Feed_Generator {

    /**
     * Database service
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct() {
        $this->database = kh_events_get_service('kh_events_db');
    }

    /**
     * Generate iCal feed
     */
    public function generate_ical($filters = array()) {
        $events = $this->database->search_events(array_merge($filters, array(
            'status' => 'scheduled',
            'limit' => 1000 // Limit for performance
        )));

        $ical = $this->get_ical_header();

        foreach ($events as $event) {
            $ical .= $this->format_event_as_ical($event);
        }

        $ical .= $this->get_ical_footer();

        return $ical;
    }

    /**
     * Generate JSON feed
     */
    public function generate_json($filters = array()) {
        $events = $this->database->search_events(array_merge($filters, array(
            'status' => 'scheduled',
            'limit' => 1000
        )));

        $feed_data = array(
            'version' => '1.0',
            'generator' => 'KH Events',
            'timestamp' => current_time('timestamp'),
            'events' => array()
        );

        foreach ($events as $event) {
            $feed_data['events'][] = $this->format_event_as_json($event);
        }

        return wp_json_encode($feed_data, JSON_PRETTY_PRINT);
    }

    /**
     * Generate RSS feed
     */
    public function generate_rss($filters = array()) {
        $events = $this->database->search_events(array_merge($filters, array(
            'status' => 'scheduled',
            'limit' => 50 // RSS typically shows fewer items
        )));

        $rss = $this->get_rss_header();

        foreach ($events as $event) {
            $rss .= $this->format_event_as_rss($event);
        }

        $rss .= $this->get_rss_footer();

        return $rss;
    }

    /**
     * Get iCal header
     */
    private function get_ical_header() {
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');

        return "BEGIN:VCALENDAR\r\n" .
               "VERSION:2.0\r\n" .
               "PRODID:-//{$site_name}//KH Events//EN\r\n" .
               "X-WR-CALNAME:{$site_name} Events\r\n" .
               "X-WR-CALDESC:Event calendar from {$site_name}\r\n" .
               "X-WR-TIMEZONE:" . wp_timezone_string() . "\r\n" .
               "CALSCALE:GREGORIAN\r\n";
    }

    /**
     * Get iCal footer
     */
    private function get_ical_footer() {
        return "END:VCALENDAR\r\n";
    }

    /**
     * Format event as iCal
     */
    private function format_event_as_ical($event) {
        $start_datetime = $this->format_datetime_for_ical($event['start_date'], $event['start_time']);
        $end_datetime = $this->format_datetime_for_ical($event['end_date'], $event['end_time']);

        // If no end time, make it an all-day event
        if (empty($event['start_time'])) {
            $start_date = date('Ymd', strtotime($event['start_date']));
            $end_date = date('Ymd', strtotime($event['end_date'] . ' +1 day'));
            $ical_event = "BEGIN:VEVENT\r\n" .
                         "UID:event-{$event['event_id']}@kh-events\r\n" .
                         "DTSTART;VALUE=DATE:{$start_date}\r\n" .
                         "DTEND;VALUE=DATE:{$end_date}\r\n";
        } else {
            $ical_event = "BEGIN:VEVENT\r\n" .
                         "UID:event-{$event['event_id']}@kh-events\r\n" .
                         "DTSTART:{$start_datetime}\r\n" .
                         "DTEND:{$end_datetime}\r\n";
        }

        $ical_event .= "SUMMARY:" . $this->escape_ical_text($event['title']) . "\r\n";

        if (!empty($event['description'])) {
            $ical_event .= "DESCRIPTION:" . $this->escape_ical_text(wp_strip_all_tags($event['description'])) . "\r\n";
        }

        // Add location if available
        $locations = $this->database->get_event_locations($event['event_id']);
        if (!empty($locations)) {
            $location = $locations[0];
            $location_string = $location['name'];
            if (!empty($location['address'])) {
                $location_string .= ', ' . $location['address'];
            }
            if (!empty($location['city'])) {
                $location_string .= ', ' . $location['city'];
            }
            $ical_event .= "LOCATION:" . $this->escape_ical_text($location_string) . "\r\n";
        }

        // Add URL
        $event_url = get_permalink($event['post_id']);
        $ical_event .= "URL:" . $this->escape_ical_text($event_url) . "\r\n";

        // Add categories
        $categories = $this->database->get_event_categories($event['event_id']);
        if (!empty($categories)) {
            $category_names = wp_list_pluck($categories, 'name');
            $ical_event .= "CATEGORIES:" . $this->escape_ical_text(implode(',', $category_names)) . "\r\n";
        }

        $ical_event .= "CREATED:" . date('Ymd\THis\Z', strtotime($event['created_at'])) . "\r\n";
        $ical_event .= "LAST-MODIFIED:" . date('Ymd\THis\Z', strtotime($event['updated_at'])) . "\r\n";
        $ical_event .= "END:VEVENT\r\n";

        return $ical_event;
    }

    /**
     * Format datetime for iCal
     */
    private function format_datetime_for_ical($date, $time) {
        if (empty($time)) {
            return date('Ymd\THis\Z', strtotime($date . ' 00:00:00'));
        } else {
            return date('Ymd\THis\Z', strtotime($date . ' ' . $time));
        }
    }

    /**
     * Escape text for iCal
     */
    private function escape_ical_text($text) {
        // Escape commas, semicolons, and backslashes
        $text = str_replace(array('\\', ',', ';'), array('\\\\', '\\,', '\\;'), $text);

        // Handle line folding for long lines
        if (strlen($text) > 75) {
            $text = wordwrap($text, 75, "\r\n ", true);
        }

        return $text;
    }

    /**
     * Format event as JSON
     */
    private function format_event_as_json($event) {
        $locations = $this->database->get_event_locations($event['event_id']);
        $categories = $this->database->get_event_categories($event['event_id']);

        return array(
            'id' => $event['event_id'],
            'title' => $event['title'],
            'description' => $event['description'],
            'start_date' => $event['start_date'],
            'end_date' => $event['end_date'],
            'start_time' => $event['start_time'],
            'end_time' => $event['end_time'],
            'max_capacity' => $event['max_capacity'],
            'current_bookings' => $event['current_bookings'],
            'price' => $event['price'],
            'currency' => $event['currency'],
            'status' => $event['status'],
            'url' => get_permalink($event['post_id']),
            'locations' => $locations,
            'categories' => $categories,
            'created_at' => $event['created_at'],
            'updated_at' => $event['updated_at']
        );
    }

    /**
     * Get RSS header
     */
    private function get_rss_header() {
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $site_description = get_bloginfo('description');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
               '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n" .
               '<channel>' . "\n" .
               '<title>' . esc_html($site_name . ' - Events') . '</title>' . "\n" .
               '<description>' . esc_html($site_description) . '</description>' . "\n" .
               '<link>' . esc_url($site_url) . '</link>' . "\n" .
               '<atom:link href="' . esc_url(add_query_arg('feed', 'events', $site_url)) . '" rel="self" type="application/rss+xml" />' . "\n" .
               '<language>' . get_bloginfo('language') . '</language>' . "\n" .
               '<lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
    }

    /**
     * Get RSS footer
     */
    private function get_rss_footer() {
        return '</channel>' . "\n" .
               '</rss>';
    }

    /**
     * Format event as RSS
     */
    private function format_event_as_rss($event) {
        $event_url = get_permalink($event['post_id']);
        $event_date = strtotime($event['start_date'] . ' ' . ($event['start_time'] ?: '00:00:00'));

        $item = '<item>' . "\n";
        $item .= '<title>' . esc_html($event['title']) . '</title>' . "\n";
        $item .= '<link>' . esc_url($event_url) . '</link>' . "\n";
        $item .= '<guid>' . esc_url($event_url) . '</guid>' . "\n";
        $item .= '<pubDate>' . date('r', $event_date) . '</pubDate>' . "\n";

        // Description
        $description = wp_trim_words(wp_strip_all_tags($event['description']), 50);
        $item .= '<description>' . esc_html($description) . '</description>' . "\n";

        // Categories
        $categories = $this->database->get_event_categories($event['event_id']);
        foreach ($categories as $category) {
            $item .= '<category>' . esc_html($category['name']) . '</category>' . "\n";
        }

        $item .= '</item>' . "\n";

        return $item;
    }

    /**
     * Generate feed URL
     */
    public function get_feed_url($format) {
        $base_url = rest_url('kh-events/v1/feed/' . $format);
        return $base_url;
    }

    /**
     * Get available feed formats
     */
    public function get_feed_formats() {
        return array(
            'ical' => array(
                'name' => __('iCalendar', 'kh-events'),
                'extension' => 'ics',
                'mime_type' => 'text/calendar',
                'description' => __('Standard calendar format compatible with Google Calendar, Outlook, and Apple Calendar', 'kh-events')
            ),
            'json' => array(
                'name' => __('JSON', 'kh-events'),
                'extension' => 'json',
                'mime_type' => 'application/json',
                'description' => __('JSON format for developers and custom integrations', 'kh-events')
            ),
            'rss' => array(
                'name' => __('RSS', 'kh-events'),
                'extension' => 'xml',
                'mime_type' => 'application/rss+xml',
                'description' => __('RSS feed for event listings and syndication', 'kh-events')
            )
        );
    }

    /**
     * Validate feed format
     */
    public function is_valid_format($format) {
        $formats = $this->get_feed_formats();
        return isset($formats[$format]);
    }

    /**
     * Get feed cache key
     */
    private function get_cache_key($format, $filters) {
        return 'kh_events_feed_' . $format . '_' . md5(serialize($filters));
    }

    /**
     * Get cached feed
     */
    private function get_cached_feed($cache_key) {
        return get_transient($cache_key);
    }

    /**
     * Set cached feed
     */
    private function set_cached_feed($cache_key, $content) {
        // Cache for 1 hour
        set_transient($cache_key, $content, HOUR_IN_SECONDS);
    }

    /**
     * Clear feed cache
     */
    public function clear_cache() {
        global $wpdb;

        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_kh_events_feed_%'");
    }

    /**
     * Generate cached feed
     */
    public function generate_cached_feed($format, $filters = array()) {
        $cache_key = $this->get_cache_key($format, $filters);

        // Try to get from cache first
        $cached = $this->get_cached_feed($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Generate fresh content
        switch ($format) {
            case 'ical':
                $content = $this->generate_ical($filters);
                break;
            case 'json':
                $content = $this->generate_json($filters);
                break;
            case 'rss':
                $content = $this->generate_rss($filters);
                break;
            default:
                return false;
        }

        // Cache the result
        $this->set_cached_feed($cache_key, $content);

        return $content;
    }
}