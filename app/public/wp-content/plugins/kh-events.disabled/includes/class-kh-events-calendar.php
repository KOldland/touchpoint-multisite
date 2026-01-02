<?php
/**
 * KH Events Calendar
 *
 * Core calendar functionality and event management
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Calendar {

    /**
     * Get events for calendar display
     */
    public function get_calendar_events($start_date, $end_date, $filters = array()) {
        $database = kh_events_get_service('kh_events_db');

        $query_filters = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'scheduled'
        );

        // Add additional filters
        if (!empty($filters['categories'])) {
            $query_filters['categories'] = $filters['categories'];
        }

        if (!empty($filters['locations'])) {
            $query_filters['locations'] = $filters['locations'];
        }

        return $database->search_events($query_filters);
    }

    /**
     * Format events for FullCalendar
     */
    public function format_events_for_calendar($events) {
        $formatted_events = array();

        foreach ($events as $event) {
            $formatted_events[] = array(
                'id' => $event['event_id'],
                'title' => $event['title'],
                'start' => $event['start_date'],
                'end' => $event['end_date'],
                'url' => get_permalink($event['post_id']),
                'className' => 'kh-event-calendar-item',
                'extendedProps' => array(
                    'description' => wp_trim_words($event['description'], 20),
                    'location' => $this->get_event_location_name($event['event_id']),
                    'time' => $this->format_event_time($event),
                    'capacity' => $event['max_capacity'],
                    'bookings' => $event['current_bookings'],
                    'price' => $event['price'],
                    'currency' => $event['currency'],
                )
            );
        }

        return $formatted_events;
    }

    /**
     * Get event location name
     */
    private function get_event_location_name($event_id) {
        $database = kh_events_get_service('kh_events_db');
        $locations = $database->get_event_locations($event_id);

        if (!empty($locations)) {
            return $locations[0]['name'];
        }

        return '';
    }

    /**
     * Format event time for display
     */
    private function format_event_time($event) {
        $time_format = get_option('time_format', 'g:i A');

        if ($event['start_time'] && $event['end_time']) {
            return date_i18n($time_format, strtotime($event['start_time'])) . ' - ' .
                   date_i18n($time_format, strtotime($event['end_time']));
        } elseif ($event['start_time']) {
            return __('Starts at', 'kh-events') . ' ' . date_i18n($time_format, strtotime($event['start_time']));
        }

        return __('All day', 'kh-events');
    }

    /**
     * Get calendar date range for a specific view
     */
    public function get_date_range($current_date, $view = 'month') {
        $current_date = strtotime($current_date);

        switch ($view) {
            case 'month':
                $start_date = date('Y-m-01', $current_date);
                $end_date = date('Y-m-t', $current_date);
                break;

            case 'week':
                $start_of_week = get_option('start_of_week', 1); // 0 = Sunday, 1 = Monday
                $current_day = date('w', $current_date);
                $days_to_subtract = ($current_day - $start_of_week + 7) % 7;
                $start_date = date('Y-m-d', strtotime("-{$days_to_subtract} days", $current_date));
                $end_date = date('Y-m-d', strtotime("+6 days", strtotime($start_date)));
                break;

            case 'day':
                $start_date = date('Y-m-d', $current_date);
                $end_date = $start_date;
                break;

            case 'list':
                $start_date = date('Y-m-d', $current_date);
                $end_date = date('Y-m-d', strtotime('+30 days', $current_date));
                break;

            default:
                $start_date = date('Y-m-01', $current_date);
                $end_date = date('Y-m-t', $current_date);
        }

        return array(
            'start' => $start_date,
            'end' => $end_date
        );
    }

    /**
     * Get calendar navigation dates
     */
    public function get_navigation_dates($current_date, $direction, $view = 'month') {
        $current_date = strtotime($current_date);

        switch ($view) {
            case 'month':
                if ($direction === 'next') {
                    $new_date = strtotime('+1 month', $current_date);
                } else {
                    $new_date = strtotime('-1 month', $current_date);
                }
                break;

            case 'week':
                if ($direction === 'next') {
                    $new_date = strtotime('+1 week', $current_date);
                } else {
                    $new_date = strtotime('-1 week', $current_date);
                }
                break;

            case 'day':
                if ($direction === 'next') {
                    $new_date = strtotime('+1 day', $current_date);
                } else {
                    $new_date = strtotime('-1 day', $current_date);
                }
                break;

            default:
                $new_date = $current_date;
        }

        return date('Y-m-d', $new_date);
    }

    /**
     * Get calendar title for display
     */
    public function get_calendar_title($current_date, $view = 'month') {
        $current_date = strtotime($current_date);

        switch ($view) {
            case 'month':
                return date_i18n('F Y', $current_date);

            case 'week':
                $start_of_week = get_option('start_of_week', 1);
                $current_day = date('w', $current_date);
                $days_to_subtract = ($current_day - $start_of_week + 7) % 7;
                $week_start = strtotime("-{$days_to_subtract} days", $current_date);
                $week_end = strtotime("+6 days", $week_start);

                if (date('m', $week_start) === date('m', $week_end)) {
                    return date_i18n('F j', $week_start) . ' - ' . date_i18n('j, Y', $week_end);
                } else {
                    return date_i18n('M j', $week_start) . ' - ' . date_i18n('M j, Y', $week_end);
                }

            case 'day':
                return date_i18n('l, F j, Y', $current_date);

            case 'list':
                $end_date = strtotime('+30 days', $current_date);
                return date_i18n('M j', $current_date) . ' - ' . date_i18n('M j, Y', $end_date);

            default:
                return date_i18n('F Y', $current_date);
        }
    }

    /**
     * Check if date has events
     */
    public function date_has_events($date) {
        $database = kh_events_get_service('kh_events_db');
        $events = $database->get_events_by_date_range($date, $date);

        return !empty($events);
    }

    /**
     * Get events for a specific date
     */
    public function get_events_for_date($date) {
        $database = kh_events_get_service('kh_events_db');
        return $database->get_events_by_date_range($date, $date);
    }

    /**
     * Get upcoming events for calendar
     */
    public function get_upcoming_events($limit = 10) {
        $database = kh_events_get_service('kh_events_db');
        $events = $database->search_events(array(
            'start_date' => current_time('Y-m-d'),
            'status' => 'scheduled'
        ));

        // Sort by start date and limit
        usort($events, function($a, $b) {
            return strcmp($a['start_date'], $b['start_date']);
        });

        return array_slice($events, 0, $limit);
    }
}