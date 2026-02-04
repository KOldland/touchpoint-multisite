<?php
/**
 * KH Events Database Operations
 *
 * Core database operations for custom tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Database {

    /**
     * Table manager instance
     */
    private $table_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->table_manager = new KH_Events_Table_Manager();
    }

    /**
     * Insert or update event in custom table
     */
    public function save_event($event_data) {
        global $wpdb;

        $table = $this->table_manager->get_table('events');

        // Prepare data
        $data = array(
            'post_id' => $event_data['post_id'],
            'title' => $event_data['title'] ?? '',
            'description' => $event_data['description'] ?? '',
            'start_date' => $event_data['start_date'],
            'end_date' => $event_data['end_date'],
            'start_time' => $event_data['start_time'] ?? null,
            'end_time' => $event_data['end_time'] ?? null,
            'timezone' => $event_data['timezone'] ?? 'UTC',
            'event_status' => $event_data['event_status'] ?? 'scheduled',
            'is_recurring' => $event_data['is_recurring'] ?? 0,
            'recurring_id' => $event_data['recurring_id'] ?? null,
            'max_capacity' => $event_data['max_capacity'] ?? null,
            'current_bookings' => $event_data['current_bookings'] ?? 0,
            'price' => $event_data['price'] ?? null,
            'currency' => $event_data['currency'] ?? 'USD',
        );

        $format = array(
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%d', '%d', '%d', '%d', '%f', '%s'
        );

        // Check if event already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT event_id FROM {$table} WHERE post_id = %d",
            $event_data['post_id']
        ));

        if ($existing) {
            // Update
            $wpdb->update($table, $data, array('post_id' => $event_data['post_id']), $format, array('%d'));
            return $existing;
        } else {
            // Insert
            $wpdb->insert($table, $data, $format);
            return $wpdb->insert_id;
        }
    }

    /**
     * Get event by post ID
     */
    public function get_event($post_id) {
        global $wpdb;

        $table = $this->table_manager->get_table('events');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE post_id = %d",
            $post_id
        ), ARRAY_A);
    }

    /**
     * Get events by date range
     */
    public function get_events_by_date_range($start_date, $end_date, $status = 'scheduled') {
        global $wpdb;

        $table = $this->table_manager->get_table('events');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE start_date >= %s AND end_date <= %s
             AND event_status = %s
             ORDER BY start_date ASC",
            $start_date, $end_date, $status
        ), ARRAY_A);
    }

    /**
     * Insert or update location in custom table
     */
    public function save_location($location_data) {
        global $wpdb;

        $table = $this->table_manager->get_table('locations');

        $data = array(
            'post_id' => $location_data['post_id'],
            'name' => $location_data['name'] ?? '',
            'address' => $location_data['address'] ?? '',
            'city' => $location_data['city'] ?? '',
            'state' => $location_data['state'] ?? '',
            'zip' => $location_data['zip'] ?? '',
            'country' => $location_data['country'] ?? '',
            'latitude' => $location_data['latitude'] ?? null,
            'longitude' => $location_data['longitude'] ?? null,
            'phone' => $location_data['phone'] ?? '',
            'website' => $location_data['website'] ?? '',
            'capacity' => $location_data['capacity'] ?? null,
        );

        $format = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d');

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT location_id FROM {$table} WHERE post_id = %d",
            $location_data['post_id']
        ));

        if ($existing) {
            $wpdb->update($table, $data, array('post_id' => $location_data['post_id']), $format, array('%d'));
            return $existing;
        } else {
            $wpdb->insert($table, $data, $format);
            return $wpdb->insert_id;
        }
    }

    /**
     * Get location by post ID
     */
    public function get_location($post_id) {
        global $wpdb;

        $table = $this->table_manager->get_table('locations');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE post_id = %d",
            $post_id
        ), ARRAY_A);
    }

    /**
     * Insert booking in custom table
     */
    public function save_booking($booking_data) {
        global $wpdb;

        $table = $this->table_manager->get_table('bookings');

        $data = array(
            'post_id' => $booking_data['post_id'],
            'event_id' => $booking_data['event_id'],
            'user_id' => $booking_data['user_id'] ?? null,
            'customer_name' => $booking_data['customer_name'],
            'customer_email' => $booking_data['customer_email'],
            'customer_phone' => $booking_data['customer_phone'] ?? '',
            'booking_status' => $booking_data['booking_status'] ?? 'pending',
            'quantity' => $booking_data['quantity'] ?? 1,
            'total_amount' => $booking_data['total_amount'] ?? 0.00,
            'currency' => $booking_data['currency'] ?? 'USD',
            'payment_method' => $booking_data['payment_method'] ?? '',
            'payment_status' => $booking_data['payment_status'] ?? 'pending',
            'notes' => $booking_data['notes'] ?? '',
        );

        $format = array(
            '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d',
            '%f', '%s', '%s', '%s', '%s'
        );

        $wpdb->insert($table, $data, $format);
        $booking_id = $wpdb->insert_id;

        // Update event booking count
        $this->update_event_booking_count($booking_data['event_id']);

        return $booking_id;
    }

    /**
     * Update event booking count
     */
    private function update_event_booking_count($event_id) {
        global $wpdb;

        $events_table = $this->table_manager->get_table('events');
        $bookings_table = $this->table_manager->get_table('bookings');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(quantity) FROM {$bookings_table}
             WHERE event_id = %d AND booking_status IN ('confirmed', 'pending')",
            $event_id
        ));

        $wpdb->update(
            $events_table,
            array('current_bookings' => $count ?: 0),
            array('event_id' => $event_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Get bookings for event
     */
    public function get_event_bookings($event_id, $status = null) {
        global $wpdb;

        $table = $this->table_manager->get_table('bookings');

        $where = "WHERE event_id = %d";
        $params = array($event_id);

        if ($status) {
            $where .= " AND booking_status = %s";
            $params[] = $status;
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY booking_date DESC",
            $params
        ), ARRAY_A);
    }

    /**
     * Link event to location
     */
    public function link_event_location($event_id, $location_id, $is_primary = true) {
        global $wpdb;

        $table = $this->table_manager->get_table('event_locations');

        // Remove existing primary if this is primary
        if ($is_primary) {
            $wpdb->update(
                $table,
                array('is_primary' => 0),
                array('event_id' => $event_id),
                array('%d'),
                array('%d')
            );
        }

        // Insert or update relationship
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE event_id = %d AND location_id = %d",
            $event_id, $location_id
        ));

        $data = array(
            'event_id' => $event_id,
            'location_id' => $location_id,
            'is_primary' => $is_primary ? 1 : 0,
        );

        $format = array('%d', '%d', '%d');

        if ($existing) {
            $wpdb->update($table, $data, array('id' => $existing), $format, array('%d'));
            return $existing;
        } else {
            $wpdb->insert($table, $data, $format);
            return $wpdb->insert_id;
        }
    }

    /**
     * Get event locations
     */
    public function get_event_locations($event_id) {
        global $wpdb;

        $rel_table = $this->table_manager->get_table('event_locations');
        $loc_table = $this->table_manager->get_table('locations');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, rel.is_primary
             FROM {$loc_table} l
             INNER JOIN {$rel_table} rel ON l.location_id = rel.location_id
             WHERE rel.event_id = %d
             ORDER BY rel.is_primary DESC",
            $event_id
        ), ARRAY_A);
    }

    /**
     * Save event meta
     */
    public function save_event_meta($event_id, $meta_key, $meta_value) {
        global $wpdb;

        $table = $this->table_manager->get_table('event_meta');

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM {$table} WHERE event_id = %d AND meta_key = %s",
            $event_id, $meta_key
        ));

        $data = array(
            'event_id' => $event_id,
            'meta_key' => $meta_key,
            'meta_value' => maybe_serialize($meta_value),
        );

        $format = array('%d', '%s', '%s');

        if ($existing) {
            $wpdb->update($table, $data, array('meta_id' => $existing), $format, array('%d'));
            return $existing;
        } else {
            $wpdb->insert($table, $data, $format);
            return $wpdb->insert_id;
        }
    }

    /**
     * Get event meta
     */
    public function get_event_meta($event_id, $meta_key = '') {
        global $wpdb;

        $table = $this->table_manager->get_table('event_meta');

        if ($meta_key) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$table} WHERE event_id = %d AND meta_key = %s",
                $event_id, $meta_key
            ));
            return maybe_unserialize($result);
        } else {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$table} WHERE event_id = %d",
                $event_id
            ), ARRAY_A);

            $meta = array();
            foreach ($results as $row) {
                $meta[$row['meta_key']] = maybe_unserialize($row['meta_value']);
            }
            return $meta;
        }
    }

    /**
     * Search events with filters
     */
    public function search_events($filters = array()) {
        global $wpdb;

        $table = $this->table_manager->get_table('events');
        $where = array("1=1");
        $params = array();

        // Date filters
        if (!empty($filters['start_date'])) {
            $where[] = "start_date >= %s";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "end_date <= %s";
            $params[] = $filters['end_date'];
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where[] = "event_status = %s";
            $params[] = $filters['status'];
        }

        // Location filter (requires join)
        if (!empty($filters['location_id'])) {
            $rel_table = $this->table_manager->get_table('event_locations');
            $table = "{$table} e INNER JOIN {$rel_table} rel ON e.event_id = rel.event_id";
            $where[] = "rel.location_id = %d";
            $params[] = $filters['location_id'];
        }

        // Search term
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE %s OR description LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        $where_clause = implode(' AND ', $where);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY start_date ASC",
            $params
        ), ARRAY_A);
    }

    /**
     * Get table manager instance
     */
    public function get_table_manager() {
        return $this->table_manager;
    }
}