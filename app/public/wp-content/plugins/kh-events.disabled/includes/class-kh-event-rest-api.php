<?php
/**
 * KH Events REST API Endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_REST_API {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('rest_prepare_kh_event', array($this, 'prepare_event_response'), 10, 3);
        add_filter('rest_prepare_kh_location', array($this, 'prepare_location_response'), 10, 3);
        add_filter('rest_prepare_kh_booking', array($this, 'prepare_booking_response'), 10, 3);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Events endpoints
        register_rest_route('kh-events/v1', '/events', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_events'),
                'permission_callback' => array($this, 'get_events_permissions_check'),
                'args' => $this->get_events_collection_params(),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_event'),
                'permission_callback' => array($this, 'create_event_permissions_check'),
                'args' => $this->get_event_params(),
            ),
        ));

        register_rest_route('kh-events/v1', '/events/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_event'),
                'permission_callback' => array($this, 'get_event_permissions_check'),
                'args' => array(
                    'id' => array(
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ),
                    'context' => array(
                        'default' => 'view',
                    ),
                ),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_event'),
                'permission_callback' => array($this, 'update_event_permissions_check'),
                'args' => $this->get_event_params(),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_event'),
                'permission_callback' => array($this, 'delete_event_permissions_check'),
            ),
        ));

        // Locations endpoints
        register_rest_route('kh-events/v1', '/locations', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_locations'),
                'permission_callback' => array($this, 'get_locations_permissions_check'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_location'),
                'permission_callback' => array($this, 'create_location_permissions_check'),
                'args' => $this->get_location_params(),
            ),
        ));

        register_rest_route('kh-events/v1', '/locations/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_location'),
                'permission_callback' => array($this, 'get_location_permissions_check'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_location'),
                'permission_callback' => array($this, 'update_location_permissions_check'),
                'args' => $this->get_location_params(),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_location'),
                'permission_callback' => array($this, 'delete_location_permissions_check'),
            ),
        ));

        // Bookings endpoints
        register_rest_route('kh-events/v1', '/bookings', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_bookings'),
                'permission_callback' => array($this, 'get_bookings_permissions_check'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_booking'),
                'permission_callback' => array($this, 'create_booking_permissions_check'),
                'args' => $this->get_booking_params(),
            ),
        ));

        register_rest_route('kh-events/v1', '/bookings/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_booking'),
                'permission_callback' => array($this, 'get_booking_permissions_check'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_booking'),
                'permission_callback' => array($this, 'update_booking_permissions_check'),
                'args' => $this->get_booking_params(),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_booking'),
                'permission_callback' => array($this, 'delete_booking_permissions_check'),
            ),
        ));

        // Categories and Tags endpoints
        register_rest_route('kh-events/v1', '/categories', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_categories'),
            'permission_callback' => array($this, 'get_categories_permissions_check'),
        ));

        register_rest_route('kh-events/v1', '/tags', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_tags'),
            'permission_callback' => array($this, 'get_tags_permissions_check'),
        ));

        // Search endpoint
        register_rest_route('kh-events/v1', '/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'search_events'),
            'permission_callback' => array($this, 'search_events_permissions_check'),
            'args' => array(
                'q' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'per_page' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Calendar feed endpoint
        register_rest_route('kh-events/v1', '/calendar', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_calendar_feed'),
            'permission_callback' => '__return_true', // Public access for calendar feeds
            'args' => array(
                'format' => array(
                    'default' => 'json',
                    'enum' => array('json', 'ical'),
                ),
                'start_date' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'end_date' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Get events collection parameters
     */
    private function get_events_collection_params() {
        return array(
            'page' => array(
                'description' => __('Current page of the collection.', 'kh-events'),
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description' => __('Maximum number of items to be returned in result set.', 'kh-events'),
                'type' => 'integer',
                'default' => 10,
                'sanitize_callback' => 'absint',
            ),
            'search' => array(
                'description' => __('Limit results to those matching a string.', 'kh-events'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'after' => array(
                'description' => __('Limit response to posts published after a given ISO8601 compliant date.', 'kh-events'),
                'type' => 'string',
                'format' => 'date-time',
            ),
            'before' => array(
                'description' => __('Limit response to posts published before a given ISO8601 compliant date.', 'kh-events'),
                'type' => 'string',
                'format' => 'date-time',
            ),
            'status' => array(
                'description' => __('Limit result set to events assigned one or more statuses.', 'kh-events'),
                'type' => 'array',
                'items' => array(
                    'enum' => array('publish', 'future', 'draft', 'pending', 'private', 'trash'),
                    'type' => 'string',
                ),
                'default' => array('publish'),
            ),
            'categories' => array(
                'description' => __('Limit result set to events assigned to specific categories.', 'kh-events'),
                'type' => 'array',
                'items' => array(
                    'type' => 'integer',
                ),
            ),
            'tags' => array(
                'description' => __('Limit result set to events assigned to specific tags.', 'kh-events'),
                'type' => 'array',
                'items' => array(
                    'type' => 'integer',
                ),
            ),
            'location' => array(
                'description' => __('Limit result set to events at a specific location.', 'kh-events'),
                'type' => 'integer',
            ),
            'start_date' => array(
                'description' => __('Limit result set to events starting after this date.', 'kh-events'),
                'type' => 'string',
                'format' => 'date',
            ),
            'end_date' => array(
                'description' => __('Limit result set to events ending before this date.', 'kh-events'),
                'type' => 'string',
                'format' => 'date',
            ),
        );
    }

    /**
     * Get event parameters for create/update
     */
    private function get_event_params() {
        return array(
            'title' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'content' => array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ),
            'excerpt' => array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ),
            'status' => array(
                'type' => 'string',
                'enum' => array('publish', 'future', 'draft', 'pending', 'private'),
                'default' => 'publish',
            ),
            'start_date' => array(
                'required' => true,
                'type' => 'string',
                'format' => 'date',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'start_time' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'end_date' => array(
                'type' => 'string',
                'format' => 'date',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'end_time' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'location_id' => array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'categories' => array(
                'type' => 'array',
                'items' => array(
                    'type' => 'integer',
                ),
            ),
            'tags' => array(
                'type' => 'array',
                'items' => array(
                    'type' => 'integer',
                ),
            ),
            'featured_image' => array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'custom_fields' => array(
                'type' => 'object',
            ),
        );
    }

    /**
     * Get location parameters
     */
    private function get_location_params() {
        return array(
            'title' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'content' => array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ),
            'address' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'city' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'state' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'zip' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'country' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'latitude' => array(
                'type' => 'number',
                'format' => 'float',
            ),
            'longitude' => array(
                'type' => 'number',
                'format' => 'float',
            ),
        );
    }

    /**
     * Get booking parameters
     */
    private function get_booking_params() {
        return array(
            'event_id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'user_id' => array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'attendee_name' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'attendee_email' => array(
                'required' => true,
                'type' => 'string',
                'format' => 'email',
                'sanitize_callback' => 'sanitize_email',
            ),
            'attendee_phone' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'quantity' => array(
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ),
            'notes' => array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ),
        );
    }

    /**
     * Get events
     */
    public function get_events($request) {
        $args = array(
            'post_type' => 'kh_event',
            'post_status' => $request->get_param('status'),
            'posts_per_page' => $request->get_param('per_page'),
            'paged' => $request->get_param('page'),
            's' => $request->get_param('search'),
        );

        // Date filters
        if ($request->get_param('after')) {
            $args['date_query'][] = array(
                'after' => $request->get_param('after'),
                'inclusive' => true,
            );
        }

        if ($request->get_param('before')) {
            $args['date_query'][] = array(
                'before' => $request->get_param('before'),
                'inclusive' => true,
            );
        }

        // Event-specific filters
        $meta_query = array();

        if ($request->get_param('start_date')) {
            $meta_query[] = array(
                'key' => '_kh_event_start_date',
                'value' => $request->get_param('start_date'),
                'compare' => '>=',
                'type' => 'DATE'
            );
        }

        if ($request->get_param('end_date')) {
            $meta_query[] = array(
                'key' => '_kh_event_end_date',
                'value' => $request->get_param('end_date'),
                'compare' => '<=',
                'type' => 'DATE'
            );
        }

        if ($request->get_param('location')) {
            $meta_query[] = array(
                'key' => '_kh_event_location',
                'value' => $request->get_param('location'),
                'compare' => 'LIKE'
            );
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        // Taxonomy filters
        $tax_query = array();

        if ($request->get_param('categories')) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_category',
                'field' => 'term_id',
                'terms' => $request->get_param('categories'),
            );
        }

        if ($request->get_param('tags')) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_tag',
                'field' => 'term_id',
                'terms' => $request->get_param('tags'),
            );
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);
        $events = array();

        foreach ($query->posts as $post) {
            $events[] = $this->prepare_event_for_response($post, $request);
        }

        $response = new WP_REST_Response($events, 200);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Get single event
     */
    public function get_event($request) {
        $event_id = $request->get_param('id');
        $post = get_post($event_id);

        if (!$post || $post->post_type !== 'kh_event') {
            return new WP_Error('kh_event_not_found', __('Event not found.', 'kh-events'), array('status' => 404));
        }

        return $this->prepare_event_for_response($post, $request);
    }

    /**
     * Create event
     */
    public function create_event($request) {
        $event_data = array(
            'post_title' => $request->get_param('title'),
            'post_content' => $request->get_param('content'),
            'post_excerpt' => $request->get_param('excerpt'),
            'post_status' => $request->get_param('status'),
            'post_type' => 'kh_event',
        );

        $event_id = wp_insert_post($event_data);

        if (is_wp_error($event_id)) {
            return $event_id;
        }

        // Save event meta
        $this->save_event_meta($event_id, $request);

        // Set featured image
        if ($request->get_param('featured_image')) {
            set_post_thumbnail($event_id, $request->get_param('featured_image'));
        }

        // Set taxonomies
        if ($request->get_param('categories')) {
            wp_set_post_terms($event_id, $request->get_param('categories'), 'kh_event_category');
        }

        if ($request->get_param('tags')) {
            wp_set_post_terms($event_id, $request->get_param('tags'), 'kh_event_tag');
        }

        $post = get_post($event_id);
        return $this->prepare_event_for_response($post, $request);
    }

    /**
     * Update event
     */
    public function update_event($request) {
        $event_id = $request->get_param('id');
        $post = get_post($event_id);

        if (!$post || $post->post_type !== 'kh_event') {
            return new WP_Error('kh_event_not_found', __('Event not found.', 'kh-events'), array('status' => 404));
        }

        $event_data = array(
            'ID' => $event_id,
            'post_title' => $request->get_param('title') ?: $post->post_title,
            'post_content' => $request->get_param('content') ?: $post->post_content,
            'post_excerpt' => $request->get_param('excerpt') ?: $post->post_excerpt,
            'post_status' => $request->get_param('status') ?: $post->post_status,
        );

        wp_update_post($event_data);

        // Update meta
        $this->save_event_meta($event_id, $request);

        // Update featured image
        if ($request->get_param('featured_image')) {
            set_post_thumbnail($event_id, $request->get_param('featured_image'));
        }

        // Update taxonomies
        if ($request->get_param('categories')) {
            wp_set_post_terms($event_id, $request->get_param('categories'), 'kh_event_category');
        }

        if ($request->get_param('tags')) {
            wp_set_post_terms($event_id, $request->get_param('tags'), 'kh_event_tag');
        }

        $post = get_post($event_id);
        return $this->prepare_event_for_response($post, $request);
    }

    /**
     * Delete event
     */
    public function delete_event($request) {
        $event_id = $request->get_param('id');
        $force = $request->get_param('force');

        $result = wp_delete_post($event_id, $force);

        if (!$result) {
            return new WP_Error('kh_event_delete_failed', __('Failed to delete event.', 'kh-events'), array('status' => 500));
        }

        return new WP_REST_Response(null, 204);
    }

    /**
     * Save event meta data
     */
    private function save_event_meta($event_id, $request) {
        $meta_fields = array(
            'start_date' => '_kh_event_start_date',
            'start_time' => '_kh_event_start_time',
            'end_date' => '_kh_event_end_date',
            'end_time' => '_kh_event_end_time',
        );

        foreach ($meta_fields as $param => $meta_key) {
            if ($request->get_param($param)) {
                update_post_meta($event_id, $meta_key, sanitize_text_field($request->get_param($param)));
            }
        }

        // Save location
        if ($request->get_param('location_id')) {
            $location_data = array(
                'id' => $request->get_param('location_id'),
                'name' => get_the_title($request->get_param('location_id')),
            );
            update_post_meta($event_id, '_kh_event_location', $location_data);
        }

        // Save custom fields
        if ($request->get_param('custom_fields')) {
            foreach ($request->get_param('custom_fields') as $key => $value) {
                update_post_meta($event_id, $key, $value);
            }
        }
    }

    /**
     * Prepare event for response
     */
    private function prepare_event_for_response($post, $request) {
        $data = array(
            'id' => $post->ID,
            'title' => array(
                'rendered' => get_the_title($post->ID),
            ),
            'content' => array(
                'rendered' => apply_filters('the_content', $post->post_content),
            ),
            'excerpt' => array(
                'rendered' => get_the_excerpt($post->ID),
            ),
            'status' => $post->post_status,
            'date' => $post->post_date,
            'date_gmt' => $post->post_date_gmt,
            'modified' => $post->post_modified,
            'modified_gmt' => $post->post_modified_gmt,
            'start_date' => get_post_meta($post->ID, '_kh_event_start_date', true),
            'start_time' => get_post_meta($post->ID, '_kh_event_start_time', true),
            'end_date' => get_post_meta($post->ID, '_kh_event_end_date', true),
            'end_time' => get_post_meta($post->ID, '_kh_event_end_time', true),
            'location' => get_post_meta($post->ID, '_kh_event_location', true),
            'categories' => wp_get_post_terms($post->ID, 'kh_event_category', array('fields' => 'id=>name')),
            'tags' => wp_get_post_terms($post->ID, 'kh_event_tag', array('fields' => 'id=>name')),
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'full'),
            'permalink' => get_permalink($post->ID),
            'custom_fields' => get_post_custom($post->ID),
        );

        return $data;
    }

    /**
     * Get locations
     */
    public function get_locations($request) {
        $args = array(
            'post_type' => 'kh_location',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged' => $request->get_param('page') ?: 1,
        );

        $query = new WP_Query($args);
        $locations = array();

        foreach ($query->posts as $post) {
            $locations[] = $this->prepare_location_for_response($post, $request);
        }

        $response = new WP_REST_Response($locations, 200);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Get single location
     */
    public function get_location($request) {
        $location_id = $request->get_param('id');
        $post = get_post($location_id);

        if (!$post || $post->post_type !== 'kh_location') {
            return new WP_Error('kh_location_not_found', __('Location not found.', 'kh-events'), array('status' => 404));
        }

        return $this->prepare_location_for_response($post, $request);
    }

    /**
     * Create location
     */
    public function create_location($request) {
        $location_data = array(
            'post_title' => $request->get_param('title'),
            'post_content' => $request->get_param('content'),
            'post_status' => 'publish',
            'post_type' => 'kh_location',
        );

        $location_id = wp_insert_post($location_data);

        if (is_wp_error($location_id)) {
            return $location_id;
        }

        // Save location meta
        $this->save_location_meta($location_id, $request);

        $post = get_post($location_id);
        return $this->prepare_location_for_response($post, $request);
    }

    /**
     * Update location
     */
    public function update_location($request) {
        $location_id = $request->get_param('id');
        $post = get_post($location_id);

        if (!$post || $post->post_type !== 'kh_location') {
            return new WP_Error('kh_location_not_found', __('Location not found.', 'kh-events'), array('status' => 404));
        }

        $location_data = array(
            'ID' => $location_id,
            'post_title' => $request->get_param('title') ?: $post->post_title,
            'post_content' => $request->get_param('content') ?: $post->post_content,
        );

        wp_update_post($location_data);
        $this->save_location_meta($location_id, $request);

        $post = get_post($location_id);
        return $this->prepare_location_for_response($post, $request);
    }

    /**
     * Delete location
     */
    public function delete_location($request) {
        $location_id = $request->get_param('id');
        $result = wp_delete_post($location_id, $request->get_param('force'));

        if (!$result) {
            return new WP_Error('kh_location_delete_failed', __('Failed to delete location.', 'kh-events'), array('status' => 500));
        }

        return new WP_REST_Response(null, 204);
    }

    /**
     * Save location meta
     */
    private function save_location_meta($location_id, $request) {
        $meta_fields = array(
            'address' => '_kh_location_address',
            'city' => '_kh_location_city',
            'state' => '_kh_location_state',
            'zip' => '_kh_location_zip',
            'country' => '_kh_location_country',
            'latitude' => '_kh_location_latitude',
            'longitude' => '_kh_location_longitude',
        );

        foreach ($meta_fields as $param => $meta_key) {
            if ($request->get_param($param) !== null) {
                update_post_meta($location_id, $meta_key, sanitize_text_field($request->get_param($param)));
            }
        }
    }

    /**
     * Prepare location for response
     */
    private function prepare_location_for_response($post, $request) {
        return array(
            'id' => $post->ID,
            'title' => array(
                'rendered' => get_the_title($post->ID),
            ),
            'content' => array(
                'rendered' => apply_filters('the_content', $post->post_content),
            ),
            'address' => get_post_meta($post->ID, '_kh_location_address', true),
            'city' => get_post_meta($post->ID, '_kh_location_city', true),
            'state' => get_post_meta($post->ID, '_kh_location_state', true),
            'zip' => get_post_meta($post->ID, '_kh_location_zip', true),
            'country' => get_post_meta($post->ID, '_kh_location_country', true),
            'latitude' => get_post_meta($post->ID, '_kh_location_latitude', true),
            'longitude' => get_post_meta($post->ID, '_kh_location_longitude', true),
            'permalink' => get_permalink($post->ID),
        );
    }

    /**
     * Get bookings
     */
    public function get_bookings($request) {
        $args = array(
            'post_type' => 'kh_booking',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged' => $request->get_param('page') ?: 1,
        );

        $query = new WP_Query($args);
        $bookings = array();

        foreach ($query->posts as $post) {
            $bookings[] = $this->prepare_booking_for_response($post, $request);
        }

        $response = new WP_REST_Response($bookings, 200);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Get single booking
     */
    public function get_booking($request) {
        $booking_id = $request->get_param('id');
        $post = get_post($booking_id);

        if (!$post || $post->post_type !== 'kh_booking') {
            return new WP_Error('kh_booking_not_found', __('Booking not found.', 'kh-events'), array('status' => 404));
        }

        return $this->prepare_booking_for_response($post, $request);
    }

    /**
     * Create booking
     */
    public function create_booking($request) {
        // Validate event exists and is bookable
        $event_id = $request->get_param('event_id');
        $event = get_post($event_id);

        if (!$event || $event->post_type !== 'kh_event') {
            return new WP_Error('kh_event_invalid', __('Invalid event.', 'kh-events'), array('status' => 400));
        }

        $booking_data = array(
            'post_title' => sprintf(__('Booking for %s', 'kh-events'), $event->post_title),
            'post_status' => 'publish',
            'post_type' => 'kh_booking',
        );

        $booking_id = wp_insert_post($booking_data);

        if (is_wp_error($booking_id)) {
            return $booking_id;
        }

        // Save booking meta
        $this->save_booking_meta($booking_id, $request);

        $post = get_post($booking_id);
        return $this->prepare_booking_for_response($post, $request);
    }

    /**
     * Update booking
     */
    public function update_booking($request) {
        $booking_id = $request->get_param('id');
        $post = get_post($booking_id);

        if (!$post || $post->post_type !== 'kh_booking') {
            return new WP_Error('kh_booking_not_found', __('Booking not found.', 'kh-events'), array('status' => 404));
        }

        $this->save_booking_meta($booking_id, $request);

        $post = get_post($booking_id);
        return $this->prepare_booking_for_response($post, $request);
    }

    /**
     * Delete booking
     */
    public function delete_booking($request) {
        $booking_id = $request->get_param('id');
        $result = wp_delete_post($booking_id, $request->get_param('force'));

        if (!$result) {
            return new WP_Error('kh_booking_delete_failed', __('Failed to delete booking.', 'kh-events'), array('status' => 500));
        }

        return new WP_REST_Response(null, 204);
    }

    /**
     * Save booking meta
     */
    private function save_booking_meta($booking_id, $request) {
        $meta_fields = array(
            'event_id' => '_kh_booking_event_id',
            'user_id' => '_kh_booking_user_id',
            'attendee_name' => '_kh_booking_attendee_name',
            'attendee_email' => '_kh_booking_attendee_email',
            'attendee_phone' => '_kh_booking_attendee_phone',
            'quantity' => '_kh_booking_quantity',
            'notes' => '_kh_booking_notes',
        );

        foreach ($meta_fields as $param => $meta_key) {
            if ($request->get_param($param) !== null) {
                $value = $request->get_param($param);
                if ($param === 'notes') {
                    $value = wp_kses_post($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                update_post_meta($booking_id, $meta_key, $value);
            }
        }

        // Set booking date
        update_post_meta($booking_id, '_kh_booking_date', current_time('mysql'));
    }

    /**
     * Prepare booking for response
     */
    private function prepare_booking_for_response($post, $request) {
        $event_id = get_post_meta($post->ID, '_kh_booking_event_id', true);
        $event = get_post($event_id);

        return array(
            'id' => $post->ID,
            'event' => $event ? array(
                'id' => $event->ID,
                'title' => get_the_title($event->ID),
                'permalink' => get_permalink($event->ID),
            ) : null,
            'user_id' => get_post_meta($post->ID, '_kh_booking_user_id', true),
            'attendee_name' => get_post_meta($post->ID, '_kh_booking_attendee_name', true),
            'attendee_email' => get_post_meta($post->ID, '_kh_booking_attendee_email', true),
            'attendee_phone' => get_post_meta($post->ID, '_kh_booking_attendee_phone', true),
            'quantity' => get_post_meta($post->ID, '_kh_booking_quantity', true),
            'notes' => get_post_meta($post->ID, '_kh_booking_notes', true),
            'booking_date' => get_post_meta($post->ID, '_kh_booking_date', true),
            'date' => $post->post_date,
        );
    }

    /**
     * Get categories
     */
    public function get_categories($request) {
        $terms = get_terms(array(
            'taxonomy' => 'kh_event_category',
            'hide_empty' => false,
        ));

        $categories = array();
        foreach ($terms as $term) {
            $categories[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'count' => $term->count,
            );
        }

        return new WP_REST_Response($categories, 200);
    }

    /**
     * Get tags
     */
    public function get_tags($request) {
        $terms = get_terms(array(
            'taxonomy' => 'kh_event_tag',
            'hide_empty' => false,
        ));

        $tags = array();
        foreach ($terms as $term) {
            $tags[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'count' => $term->count,
            );
        }

        return new WP_REST_Response($tags, 200);
    }

    /**
     * Search events
     */
    public function search_events($request) {
        $search_term = $request->get_param('q');
        $per_page = $request->get_param('per_page');

        $args = array(
            'post_type' => 'kh_event',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            's' => $search_term,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_kh_event_start_date',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_kh_event_end_date',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                ),
            ),
        );

        $query = new WP_Query($args);
        $events = array();

        foreach ($query->posts as $post) {
            $events[] = $this->prepare_event_for_response($post, $request);
        }

        $response = new WP_REST_Response($events, 200);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Get calendar feed
     */
    public function get_calendar_feed($request) {
        $format = $request->get_param('format');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');

        $args = array(
            'post_type' => 'kh_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => array($start_date ?: '1970-01-01', $end_date ?: date('Y-m-d', strtotime('+1 year'))),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            ),
        );

        $query = new WP_Query($args);

        if ($format === 'ical') {
            return $this->generate_ical_feed($query->posts);
        }

        // JSON format
        $events = array();
        foreach ($query->posts as $post) {
            $events[] = array(
                'id' => $post->ID,
                'title' => get_the_title($post->ID),
                'start' => get_post_meta($post->ID, '_kh_event_start_date', true) . 'T' . get_post_meta($post->ID, '_kh_event_start_time', true),
                'end' => get_post_meta($post->ID, '_kh_event_end_date', true) . 'T' . get_post_meta($post->ID, '_kh_event_end_time', true),
                'url' => get_permalink($post->ID),
                'description' => get_the_excerpt($post->ID),
            );
        }

        return new WP_REST_Response($events, 200);
    }

    /**
     * Generate iCal feed
     */
    private function generate_ical_feed($events) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename=kh-events-calendar.ics');

        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//KH Events//EN\r\n";

        foreach ($events as $event) {
            $start_date = get_post_meta($event->ID, '_kh_event_start_date', true);
            $start_time = get_post_meta($event->ID, '_kh_event_start_time', true);
            $end_date = get_post_meta($event->ID, '_kh_event_end_date', true);
            $end_time = get_post_meta($event->ID, '_kh_event_end_time', true);

            $start_datetime = $start_date . ($start_time ? 'T' . str_replace(':', '', $start_time) : '');
            $end_datetime = $end_date . ($end_time ? 'T' . str_replace(':', '', $end_time) : '');

            echo "BEGIN:VEVENT\r\n";
            echo "UID:" . $event->ID . "@" . site_url() . "\r\n";
            echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
            echo "DTSTART:" . $start_datetime . "\r\n";
            if ($end_datetime) {
                echo "DTEND:" . $end_datetime . "\r\n";
            }
            echo "SUMMARY:" . $this->escape_ical_text(get_the_title($event->ID)) . "\r\n";
            echo "DESCRIPTION:" . $this->escape_ical_text(get_the_excerpt($event->ID)) . "\r\n";
            echo "URL:" . get_permalink($event->ID) . "\r\n";
            echo "END:VEVENT\r\n";
        }

        echo "END:VCALENDAR\r\n";
        exit;
    }

    /**
     * Escape iCal text
     */
    private function escape_ical_text($text) {
        return str_replace(array('\\', ',', ';', "\n"), array('\\\\', '\\,', '\\;', '\\n'), $text);
    }

    /**
     * Permission check methods
     */
    public function get_events_permissions_check($request) {
        return true; // Public read access
    }

    public function get_event_permissions_check($request) {
        return true; // Public read access
    }

    public function create_event_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function update_event_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function delete_event_permissions_check($request) {
        return current_user_can('delete_posts');
    }

    public function get_locations_permissions_check($request) {
        return true; // Public read access
    }

    public function get_location_permissions_check($request) {
        return true; // Public read access
    }

    public function create_location_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function update_location_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function delete_location_permissions_check($request) {
        return current_user_can('delete_posts');
    }

    public function get_bookings_permissions_check($request) {
        return current_user_can('edit_posts'); // Admin access for bookings
    }

    public function get_booking_permissions_check($request) {
        return current_user_can('edit_posts'); // Admin access for bookings
    }

    public function create_booking_permissions_check($request) {
        return true; // Allow public booking creation
    }

    public function update_booking_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function delete_booking_permissions_check($request) {
        return current_user_can('delete_posts');
    }

    public function get_categories_permissions_check($request) {
        return true; // Public read access
    }

    public function get_tags_permissions_check($request) {
        return true; // Public read access
    }

    public function search_events_permissions_check($request) {
        return true; // Public search access
    }

    /**
     * Prepare response filters
     */
    public function prepare_event_response($response, $post, $request) {
        if ($post->post_type === 'kh_event') {
            $data = $response->get_data();
            $data['event_meta'] = array(
                'start_date' => get_post_meta($post->ID, '_kh_event_start_date', true),
                'start_time' => get_post_meta($post->ID, '_kh_event_start_time', true),
                'end_date' => get_post_meta($post->ID, '_kh_event_end_date', true),
                'end_time' => get_post_meta($post->ID, '_kh_event_end_time', true),
                'location' => get_post_meta($post->ID, '_kh_event_location', true),
            );
            $response->set_data($data);
        }
        return $response;
    }

    public function prepare_location_response($response, $post, $request) {
        if ($post->post_type === 'kh_location') {
            $data = $response->get_data();
            $data['location_meta'] = array(
                'address' => get_post_meta($post->ID, '_kh_location_address', true),
                'city' => get_post_meta($post->ID, '_kh_location_city', true),
                'state' => get_post_meta($post->ID, '_kh_location_state', true),
                'zip' => get_post_meta($post->ID, '_kh_location_zip', true),
                'country' => get_post_meta($post->ID, '_kh_location_country', true),
                'latitude' => get_post_meta($post->ID, '_kh_location_latitude', true),
                'longitude' => get_post_meta($post->ID, '_kh_location_longitude', true),
            );
            $response->set_data($data);
        }
        return $response;
    }

    public function prepare_booking_response($response, $post, $request) {
        if ($post->post_type === 'kh_booking') {
            $data = $response->get_data();
            $data['booking_meta'] = array(
                'event_id' => get_post_meta($post->ID, '_kh_booking_event_id', true),
                'user_id' => get_post_meta($post->ID, '_kh_booking_user_id', true),
                'attendee_name' => get_post_meta($post->ID, '_kh_booking_attendee_name', true),
                'attendee_email' => get_post_meta($post->ID, '_kh_booking_attendee_email', true),
                'attendee_phone' => get_post_meta($post->ID, '_kh_booking_attendee_phone', true),
                'quantity' => get_post_meta($post->ID, '_kh_booking_quantity', true),
                'notes' => get_post_meta($post->ID, '_kh_booking_notes', true),
                'booking_date' => get_post_meta($post->ID, '_kh_booking_date', true),
            );
            $response->set_data($data);
        }
        return $response;
    }
}