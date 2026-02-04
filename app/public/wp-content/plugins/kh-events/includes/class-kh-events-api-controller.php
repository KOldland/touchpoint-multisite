<?php
/**
 * KH Events API Controller
 *
 * Handles REST API requests for events, bookings, and related data
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_API_Controller {

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
     * Get events
     */
    public function get_events($request) {
        $params = $request->get_params();

        // Build query filters
        $filters = array(
            'status' => $params['status'] ?? 'scheduled',
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 10
        );

        if (!empty($params['start_date'])) {
            $filters['start_date'] = $params['start_date'];
        }

        if (!empty($params['end_date'])) {
            $filters['end_date'] = $params['end_date'];
        }

        if (!empty($params['categories'])) {
            $filters['categories'] = $params['categories'];
        }

        if (!empty($params['locations'])) {
            $filters['locations'] = $params['locations'];
        }

        try {
            $events = $this->database->search_events($filters);
            $total = $this->database->get_events_count($filters);

            return new WP_REST_Response(array(
                'events' => $this->format_events_for_api($events),
                'total' => $total,
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total_pages' => ceil($total / $filters['per_page'])
            ), 200);
        } catch (Exception $e) {
            return new WP_Error('events_query_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Get single event
     */
    public function get_event($request) {
        $event_id = (int) $request->get_param('id');

        try {
            $event = $this->database->get_event($event_id);

            if (!$event) {
                return new WP_Error('event_not_found', __('Event not found', 'kh-events'), array('status' => 404));
            }

            return new WP_REST_Response($this->format_event_for_api($event), 200);
        } catch (Exception $e) {
            return new WP_Error('event_query_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Create event
     */
    public function create_event($request) {
        $params = $request->get_params();

        // Validate required fields
        if (empty($params['title']) || empty($params['start_date'])) {
            return new WP_Error('missing_required_fields', __('Title and start date are required', 'kh-events'), array('status' => 400));
        }

        try {
            $event_data = array(
                'title' => sanitize_text_field($params['title']),
                'description' => wp_kses_post($params['description'] ?? ''),
                'start_date' => sanitize_text_field($params['start_date']),
                'end_date' => sanitize_text_field($params['end_date'] ?? $params['start_date']),
                'start_time' => sanitize_text_field($params['start_time'] ?? null),
                'end_time' => sanitize_text_field($params['end_time'] ?? null),
                'max_capacity' => intval($params['max_capacity'] ?? 0),
                'price' => floatval($params['price'] ?? 0),
                'currency' => sanitize_text_field($params['currency'] ?? 'USD'),
                'status' => 'scheduled'
            );

            $event_id = $this->database->create_event($event_data);

            // Handle categories
            if (!empty($params['categories']) && is_array($params['categories'])) {
                foreach ($params['categories'] as $category_id) {
                    $this->database->add_event_category($event_id, intval($category_id));
                }
            }

            // Handle location
            if (!empty($params['location_id'])) {
                $this->database->set_event_location($event_id, intval($params['location_id']));
            }

            $event = $this->database->get_event($event_id);

            return new WP_REST_Response($this->format_event_for_api($event), 201);
        } catch (Exception $e) {
            return new WP_Error('event_creation_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Update event
     */
    public function update_event($request) {
        $event_id = (int) $request->get_param('id');
        $params = $request->get_params();

        try {
            // Check if event exists
            $existing_event = $this->database->get_event($event_id);
            if (!$existing_event) {
                return new WP_Error('event_not_found', __('Event not found', 'kh-events'), array('status' => 404));
            }

            $event_data = array();

            if (isset($params['title'])) {
                $event_data['title'] = sanitize_text_field($params['title']);
            }

            if (isset($params['description'])) {
                $event_data['description'] = wp_kses_post($params['description']);
            }

            if (isset($params['start_date'])) {
                $event_data['start_date'] = sanitize_text_field($params['start_date']);
            }

            if (isset($params['end_date'])) {
                $event_data['end_date'] = sanitize_text_field($params['end_date']);
            }

            if (isset($params['start_time'])) {
                $event_data['start_time'] = sanitize_text_field($params['start_time']);
            }

            if (isset($params['end_time'])) {
                $event_data['end_time'] = sanitize_text_field($params['end_time']);
            }

            if (isset($params['max_capacity'])) {
                $event_data['max_capacity'] = intval($params['max_capacity']);
            }

            if (isset($params['price'])) {
                $event_data['price'] = floatval($params['price']);
            }

            if (isset($params['currency'])) {
                $event_data['currency'] = sanitize_text_field($params['currency']);
            }

            if (isset($params['status'])) {
                $event_data['status'] = sanitize_text_field($params['status']);
            }

            $this->database->update_event($event_id, $event_data);

            // Handle categories
            if (isset($params['categories']) && is_array($params['categories'])) {
                $this->database->clear_event_categories($event_id);
                foreach ($params['categories'] as $category_id) {
                    $this->database->add_event_category($event_id, intval($category_id));
                }
            }

            // Handle location
            if (isset($params['location_id'])) {
                $this->database->set_event_location($event_id, intval($params['location_id']));
            }

            $event = $this->database->get_event($event_id);

            return new WP_REST_Response($this->format_event_for_api($event), 200);
        } catch (Exception $e) {
            return new WP_Error('event_update_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Delete event
     */
    public function delete_event($request) {
        $event_id = (int) $request->get_param('id');

        try {
            // Check if event exists
            $event = $this->database->get_event($event_id);
            if (!$event) {
                return new WP_Error('event_not_found', __('Event not found', 'kh-events'), array('status' => 404));
            }

            $this->database->delete_event($event_id);

            return new WP_REST_Response(array(
                'message' => __('Event deleted successfully', 'kh-events'),
                'event_id' => $event_id
            ), 200);
        } catch (Exception $e) {
            return new WP_Error('event_deletion_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Get bookings
     */
    public function get_bookings($request) {
        $params = $request->get_params();

        $filters = array(
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 10
        );

        if (!empty($params['event_id'])) {
            $filters['event_id'] = intval($params['event_id']);
        }

        if (!empty($params['status'])) {
            $filters['status'] = sanitize_text_field($params['status']);
        }

        try {
            $bookings = $this->database->get_bookings($filters);
            $total = $this->database->get_bookings_count($filters);

            return new WP_REST_Response(array(
                'bookings' => $this->format_bookings_for_api($bookings),
                'total' => $total,
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total_pages' => ceil($total / $filters['per_page'])
            ), 200);
        } catch (Exception $e) {
            return new WP_Error('bookings_query_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Create booking
     */
    public function create_booking($request) {
        $params = $request->get_params();

        // Validate required fields
        if (empty($params['event_id']) || empty($params['attendee_name']) || empty($params['attendee_email'])) {
            return new WP_Error('missing_required_fields', __('Event ID, attendee name, and email are required', 'kh-events'), array('status' => 400));
        }

        try {
            // Check if event exists and has capacity
            $event = $this->database->get_event($params['event_id']);
            if (!$event) {
                return new WP_Error('event_not_found', __('Event not found', 'kh-events'), array('status' => 404));
            }

            if ($event['max_capacity'] > 0 && $event['current_bookings'] >= $event['max_capacity']) {
                return new WP_Error('event_full', __('Event is fully booked', 'kh-events'), array('status' => 409));
            }

            $booking_data = array(
                'event_id' => intval($params['event_id']),
                'attendee_name' => sanitize_text_field($params['attendee_name']),
                'attendee_email' => sanitize_email($params['attendee_email']),
                'quantity' => intval($params['quantity'] ?? 1),
                'booking_date' => current_time('mysql'),
                'status' => 'confirmed',
                'notes' => sanitize_textarea_field($params['notes'] ?? ''),
                'total_amount' => $event['price'] * intval($params['quantity'] ?? 1),
                'currency' => $event['currency']
            );

            $booking_id = $this->database->create_booking($booking_data);

            // Update event booking count
            $this->database->update_event_bookings_count($params['event_id']);

            $booking = $this->database->get_booking($booking_id);

            return new WP_REST_Response($this->format_booking_for_api($booking), 201);
        } catch (Exception $e) {
            return new WP_Error('booking_creation_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Get locations
     */
    public function get_locations($request) {
        $params = $request->get_params();

        $filters = array(
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 10
        );

        try {
            $locations = $this->database->get_locations($filters);
            $total = $this->database->get_locations_count($filters);

            return new WP_REST_Response(array(
                'locations' => $locations,
                'total' => $total,
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total_pages' => ceil($total / $filters['per_page'])
            ), 200);
        } catch (Exception $e) {
            return new WP_Error('locations_query_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Create location
     */
    public function create_location($request) {
        $params = $request->get_params();

        // Validate required fields
        if (empty($params['name'])) {
            return new WP_Error('missing_required_fields', __('Location name is required', 'kh-events'), array('status' => 400));
        }

        try {
            $location_data = array(
                'name' => sanitize_text_field($params['name']),
                'address' => sanitize_text_field($params['address'] ?? ''),
                'city' => sanitize_text_field($params['city'] ?? ''),
                'state' => sanitize_text_field($params['state'] ?? ''),
                'zip_code' => sanitize_text_field($params['zip_code'] ?? ''),
                'country' => sanitize_text_field($params['country'] ?? ''),
                'latitude' => floatval($params['latitude'] ?? 0),
                'longitude' => floatval($params['longitude'] ?? 0)
            );

            $location_id = $this->database->create_location($location_data);
            $location = $this->database->get_location($location_id);

            return new WP_REST_Response($location, 201);
        } catch (Exception $e) {
            return new WP_Error('location_creation_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Get categories
     */
    public function get_categories($request) {
        $categories = get_terms(array(
            'taxonomy' => 'kh_event_category',
            'hide_empty' => false,
            'fields' => 'all'
        ));

        $formatted_categories = array();
        foreach ($categories as $category) {
            $formatted_categories[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->count
            );
        }

        return new WP_REST_Response(array(
            'categories' => $formatted_categories
        ), 200);
    }

    /**
     * Get feed
     */
    public function get_feed($request) {
        $format = $request->get_param('format');
        $feed_generator = kh_events_get_service('kh_events_feed_generator');

        try {
            switch ($format) {
                case 'ical':
                    $content = $feed_generator->generate_ical();
                    $content_type = 'text/calendar';
                    $filename = 'kh-events.ics';
                    break;

                case 'json':
                    $content = $feed_generator->generate_json();
                    $content_type = 'application/json';
                    $filename = 'kh-events.json';
                    break;

                case 'rss':
                    $content = $feed_generator->generate_rss();
                    $content_type = 'application/rss+xml';
                    $filename = 'kh-events.xml';
                    break;

                default:
                    return new WP_Error('invalid_format', __('Invalid feed format', 'kh-events'), array('status' => 400));
            }

            $response = new WP_REST_Response($content, 200);
            $response->set_headers(array(
                'Content-Type' => $content_type . '; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ));

            return $response;
        } catch (Exception $e) {
            return new WP_Error('feed_generation_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Format events for API response
     */
    private function format_events_for_api($events) {
        $formatted = array();

        foreach ($events as $event) {
            $formatted[] = $this->format_event_for_api($event);
        }

        return $formatted;
    }

    /**
     * Format single event for API response
     */
    private function format_event_for_api($event) {
        $categories = $this->database->get_event_categories($event['event_id']);
        $locations = $this->database->get_event_locations($event['event_id']);

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
            'post_id' => $event['post_id'],
            'permalink' => get_permalink($event['post_id']),
            'categories' => $categories,
            'locations' => $locations,
            'created_at' => $event['created_at'],
            'updated_at' => $event['updated_at']
        );
    }

    /**
     * Format bookings for API response
     */
    private function format_bookings_for_api($bookings) {
        $formatted = array();

        foreach ($bookings as $booking) {
            $formatted[] = $this->format_booking_for_api($booking);
        }

        return $formatted;
    }

    /**
     * Format single booking for API response
     */
    private function format_booking_for_api($booking) {
        return array(
            'id' => $booking['booking_id'],
            'event_id' => $booking['event_id'],
            'attendee_name' => $booking['attendee_name'],
            'attendee_email' => $booking['attendee_email'],
            'quantity' => $booking['quantity'],
            'total_amount' => $booking['total_amount'],
            'currency' => $booking['currency'],
            'status' => $booking['status'],
            'booking_date' => $booking['booking_date'],
            'notes' => $booking['notes']
        );
    }
}