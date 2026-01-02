<?php
/**
 * KH Events Table Manager
 *
 * Handles creation and management of custom database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Table_Manager {

    /**
     * Table names
     */
    private $tables;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        $this->tables = array(
            'events' => $wpdb->prefix . 'kh_events',
            'locations' => $wpdb->prefix . 'kh_event_locations',
            'bookings' => $wpdb->prefix . 'kh_event_bookings',
            'event_locations' => $wpdb->prefix . 'kh_event_locations_rel',
            'event_meta' => $wpdb->prefix . 'kh_event_meta',
            'booking_meta' => $wpdb->prefix . 'kh_booking_meta',
            'recurring_events' => $wpdb->prefix . 'kh_recurring_events',
        );
    }

    /**
     * Create all custom tables
     */
    public function create_tables() {
        $this->create_events_table();
        $this->create_locations_table();
        $this->create_bookings_table();
        $this->create_event_locations_table();
        $this->create_event_meta_table();
        $this->create_booking_meta_table();
        $this->create_recurring_events_table();

        $this->add_indexes();
    }

    /**
     * Create events table
     */
    private function create_events_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->tables['events']} (
            event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            title text NOT NULL,
            description longtext,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            start_time time,
            end_time time,
            timezone varchar(50) DEFAULT 'UTC',
            event_status enum('scheduled','canceled','postponed','draft') DEFAULT 'scheduled',
            is_recurring tinyint(1) DEFAULT 0,
            recurring_id bigint(20) unsigned DEFAULT NULL,
            max_capacity int(11) DEFAULT NULL,
            current_bookings int(11) DEFAULT 0,
            price decimal(10,2) DEFAULT NULL,
            currency varchar(3) DEFAULT 'USD',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id),
            KEY post_id (post_id),
            KEY start_date (start_date),
            KEY end_date (end_date),
            KEY event_status (event_status),
            KEY is_recurring (is_recurring),
            KEY recurring_id (recurring_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create locations table
     */
    private function create_locations_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->tables['locations']} (
            location_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            address text,
            city varchar(100),
            state varchar(100),
            zip varchar(20),
            country varchar(100),
            latitude decimal(10,8),
            longitude decimal(11,8),
            phone varchar(50),
            website varchar(255),
            capacity int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (location_id),
            KEY post_id (post_id),
            KEY city (city),
            KEY state (state),
            KEY country (country),
            KEY latitude (latitude),
            KEY longitude (longitude)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create bookings table
     */
    private function create_bookings_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->tables['bookings']} (
            booking_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50),
            booking_status enum('pending','confirmed','canceled','refunded') DEFAULT 'pending',
            quantity int(11) DEFAULT 1,
            total_amount decimal(10,2) DEFAULT 0.00,
            currency varchar(3) DEFAULT 'USD',
            payment_method varchar(50),
            payment_status enum('pending','paid','failed','refunded') DEFAULT 'pending',
            booking_date datetime DEFAULT CURRENT_TIMESTAMP,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (booking_id),
            KEY post_id (post_id),
            KEY event_id (event_id),
            KEY user_id (user_id),
            KEY customer_email (customer_email),
            KEY booking_status (booking_status),
            KEY payment_status (payment_status),
            KEY booking_date (booking_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create event-locations relationship table
     */
    private function create_event_locations_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->tables['event_locations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            location_id bigint(20) unsigned NOT NULL,
            is_primary tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY event_location (event_id, location_id),
            KEY event_id (event_id),
            KEY location_id (location_id),
            KEY is_primary (is_primary)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create event meta table
     */
    private function create_event_meta_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->tables['event_meta']} (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (meta_id),
            KEY event_id (event_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create booking meta table
     */
    private function create_booking_meta_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->tables['booking_meta']} (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (meta_id),
            KEY booking_id (booking_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create recurring events table
     */
    private function create_recurring_events_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->tables['recurring_events']} (
            recurring_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            parent_event_id bigint(20) unsigned NOT NULL,
            pattern_type enum('daily','weekly','monthly','yearly') NOT NULL,
            pattern_interval int(11) DEFAULT 1,
            pattern_days varchar(255), -- JSON array for weekly days
            pattern_month_day int(11), -- Day of month for monthly
            pattern_month int(11), -- Month for yearly
            end_date datetime,
            max_occurrences int(11),
            exceptions text, -- JSON array of exception dates
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (recurring_id),
            KEY parent_event_id (parent_event_id),
            KEY pattern_type (pattern_type),
            KEY end_date (end_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add additional indexes for performance
     */
    private function add_indexes() {
        global $wpdb;

        // Composite indexes for common queries
        $indexes = array(
            "ALTER TABLE {$this->tables['events']} ADD INDEX idx_event_dates (start_date, end_date)",
            "ALTER TABLE {$this->tables['events']} ADD INDEX idx_event_status_dates (event_status, start_date)",
            "ALTER TABLE {$this->tables['bookings']} ADD INDEX idx_event_status_date (event_id, booking_status, booking_date)",
            "ALTER TABLE {$this->tables['bookings']} ADD INDEX idx_customer_event (customer_email, event_id)",
            "ALTER TABLE {$this->tables['locations']} ADD INDEX idx_location_coords (latitude, longitude)",
        );

        foreach ($indexes as $index_sql) {
            // Check if index already exists before adding
            try {
                $wpdb->query($index_sql);
            } catch (Exception $e) {
                // Index might already exist, continue
                continue;
            }
        }
    }

    /**
     * Upgrade database tables
     */
    public function upgrade_tables($current_version) {
        // Handle version-specific upgrades
        if (version_compare($current_version, '1.1.0', '<')) {
            $this->upgrade_to_1_1_0();
        }

        if (version_compare($current_version, '1.2.0', '<')) {
            $this->upgrade_to_1_2_0();
        }
    }

    /**
     * Upgrade to version 1.1.0
     */
    private function upgrade_to_1_1_0() {
        global $wpdb;

        // Add timezone column if it doesn't exist
        $wpdb->query("ALTER TABLE {$this->tables['events']} ADD COLUMN timezone varchar(50) DEFAULT 'UTC' AFTER end_time");

        // Add payment fields to bookings table
        $wpdb->query("ALTER TABLE {$this->tables['bookings']} ADD COLUMN payment_method varchar(50) AFTER total_amount");
        $wpdb->query("ALTER TABLE {$this->tables['bookings']} ADD COLUMN payment_status enum('pending','paid','failed','refunded') DEFAULT 'pending' AFTER payment_method");
    }

    /**
     * Upgrade to version 1.2.0
     */
    private function upgrade_to_1_2_0() {
        global $wpdb;

        // Add capacity tracking to events
        $wpdb->query("ALTER TABLE {$this->tables['events']} ADD COLUMN max_capacity int(11) DEFAULT NULL AFTER is_recurring");
        $wpdb->query("ALTER TABLE {$this->tables['events']} ADD COLUMN current_bookings int(11) DEFAULT 0 AFTER max_capacity");
    }

    /**
     * Get table name
     */
    public function get_table($table_name) {
        return isset($this->tables[$table_name]) ? $this->tables[$table_name] : false;
    }

    /**
     * Get all table names
     */
    public function get_tables() {
        return $this->tables;
    }

    /**
     * Drop all tables (for testing/uninstall)
     */
    public function drop_tables() {
        global $wpdb;

        foreach ($this->tables as $table_name => $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
}