<?php
/**
 * KH Events Analytics & Reporting
 *
 * Advanced analytics and reporting for event performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Analytics {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->create_tables();
    }

    private function init_hooks() {
        // Track event views
        add_action('wp', array($this, 'track_event_view'));

        // Track booking completions
        add_action('kh_event_booking_completed', array($this, 'track_booking'), 10, 2);

        // Track cancellations
        add_action('kh_event_booking_cancelled', array($this, 'track_cancellation'), 10, 2);

        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // AJAX handlers for dashboard
        add_action('wp_ajax_kh_events_analytics_data', array($this, 'ajax_get_analytics_data'));

        // Enqueue scripts for admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Event views table
        $table_views = $wpdb->prefix . 'kh_event_views';
        $sql_views = "CREATE TABLE $table_views (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            referrer text DEFAULT '',
            view_date datetime DEFAULT CURRENT_TIMESTAMP,
            session_id varchar(64) DEFAULT '',
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY view_date (view_date),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Booking analytics table
        $table_bookings = $wpdb->prefix . 'kh_booking_analytics';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            event_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            booking_date datetime DEFAULT CURRENT_TIMESTAMP,
            ticket_type varchar(100) DEFAULT '',
            ticket_quantity int(11) DEFAULT 1,
            total_amount decimal(10,2) DEFAULT 0.00,
            currency varchar(3) DEFAULT 'USD',
            payment_method varchar(50) DEFAULT '',
            status varchar(20) DEFAULT 'completed',
            source varchar(50) DEFAULT 'website',
            utm_source varchar(100) DEFAULT '',
            utm_medium varchar(100) DEFAULT '',
            utm_campaign varchar(100) DEFAULT '',
            utm_term varchar(100) DEFAULT '',
            utm_content varchar(100) DEFAULT '',
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY event_id (event_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $charset_collate;";

        // Revenue tracking table
        $table_revenue = $wpdb->prefix . 'kh_event_revenue';
        $sql_revenue = "CREATE TABLE $table_revenue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            date date NOT NULL,
            total_revenue decimal(10,2) DEFAULT 0.00,
            ticket_sales decimal(10,2) DEFAULT 0.00,
            booking_count int(11) DEFAULT 0,
            refund_amount decimal(10,2) DEFAULT 0.00,
            refund_count int(11) DEFAULT 0,
            currency varchar(3) DEFAULT 'USD',
            PRIMARY KEY (id),
            UNIQUE KEY event_date (event_id, date),
            KEY date (date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_views);
        dbDelta($sql_bookings);
        dbDelta($sql_revenue);
    }

    public function track_event_view() {
        if (!is_singular('kh_event')) {
            return;
        }

        global $post;
        $event_id = $post->ID;

        // Avoid tracking admin views, bots, etc.
        if (is_admin() || $this->is_bot()) {
            return;
        }

        $this->record_event_view($event_id);
    }

    private function is_bot() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bots = array('bot', 'spider', 'crawler', 'slurp', 'yahoo', 'google', 'bing', 'duckduck');

        foreach ($bots as $bot) {
            if (stripos($user_agent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }

    private function record_event_view($event_id) {
        global $wpdb;

        $session_id = session_id();
        if (empty($session_id)) {
            session_start();
            $session_id = session_id();
        }

        // Check if this session already viewed this event recently (prevent spam)
        $recent_view = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}kh_event_views
             WHERE event_id = %d AND session_id = %s AND view_date > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $event_id, $session_id
        ));

        if ($recent_view) {
            return; // Already recorded recently
        }

        $wpdb->insert(
            $wpdb->prefix . 'kh_event_views',
            array(
                'event_id' => $event_id,
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'session_id' => $session_id,
                'view_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }

    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (like X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function track_booking($booking_id, $booking_data) {
        global $wpdb;

        // Extract UTM parameters from session/cookies
        $utm_data = $this->get_utm_data();

        $analytics_data = array(
            'booking_id' => $booking_id,
            'event_id' => $booking_data['event_id'] ?? 0,
            'user_id' => $booking_data['user_id'] ?? get_current_user_id(),
            'booking_date' => current_time('mysql'),
            'ticket_type' => $booking_data['ticket_type'] ?? '',
            'ticket_quantity' => $booking_data['quantity'] ?? 1,
            'total_amount' => $booking_data['total'] ?? 0.00,
            'currency' => $booking_data['currency'] ?? 'USD',
            'payment_method' => $booking_data['payment_method'] ?? '',
            'status' => 'completed',
            'source' => $this->detect_source(),
            'utm_source' => $utm_data['utm_source'] ?? '',
            'utm_medium' => $utm_data['utm_medium'] ?? '',
            'utm_campaign' => $utm_data['utm_campaign'] ?? '',
            'utm_term' => $utm_data['utm_term'] ?? '',
            'utm_content' => $utm_data['utm_content'] ?? ''
        );

        $wpdb->insert(
            $wpdb->prefix . 'kh_booking_analytics',
            $analytics_data,
            array('%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        // Update revenue tracking
        $this->update_revenue_data($booking_data['event_id'], $booking_data['total'] ?? 0.00, $booking_data['currency'] ?? 'USD');
    }

    public function track_cancellation($booking_id, $booking_data) {
        global $wpdb;

        // Update booking status
        $wpdb->update(
            $wpdb->prefix . 'kh_booking_analytics',
            array('status' => 'cancelled'),
            array('booking_id' => $booking_id),
            array('%s'),
            array('%d')
        );

        // Update revenue (subtract refund)
        if (isset($booking_data['refund_amount'])) {
            $this->update_revenue_data(
                $booking_data['event_id'],
                -$booking_data['refund_amount'],
                $booking_data['currency'] ?? 'USD',
                true // is refund
            );
        }
    }

    private function update_revenue_data($event_id, $amount, $currency = 'USD', $is_refund = false) {
        global $wpdb;

        $today = current_time('Y-m-d');

        // Get existing revenue record for today
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kh_event_revenue WHERE event_id = %d AND date = %s",
            $event_id, $today
        ));

        if ($existing) {
            if ($is_refund) {
                $wpdb->update(
                    $wpdb->prefix . 'kh_event_revenue',
                    array(
                        'refund_amount' => $existing->refund_amount + abs($amount),
                        'refund_count' => $existing->refund_count + 1
                    ),
                    array('id' => $existing->id),
                    array('%s', '%d'),
                    array('%d')
                );
            } else {
                $wpdb->update(
                    $wpdb->prefix . 'kh_event_revenue',
                    array(
                        'total_revenue' => $existing->total_revenue + $amount,
                        'ticket_sales' => $existing->ticket_sales + $amount,
                        'booking_count' => $existing->booking_count + 1
                    ),
                    array('id' => $existing->id),
                    array('%s', '%s', '%d'),
                    array('%d')
                );
            }
        } else {
            $data = array(
                'event_id' => $event_id,
                'date' => $today,
                'currency' => $currency
            );

            if ($is_refund) {
                $data['refund_amount'] = abs($amount);
                $data['refund_count'] = 1;
            } else {
                $data['total_revenue'] = $amount;
                $data['ticket_sales'] = $amount;
                $data['booking_count'] = 1;
            }

            $wpdb->insert(
                $wpdb->prefix . 'kh_event_revenue',
                $data,
                array('%d', '%s', '%s', '%s', '%d', '%s')
            );
        }
    }

    private function get_utm_data() {
        $utm_data = array();

        // Check session first
        if (isset($_SESSION)) {
            $utm_data = array(
                'utm_source' => $_SESSION['utm_source'] ?? '',
                'utm_medium' => $_SESSION['utm_medium'] ?? '',
                'utm_campaign' => $_SESSION['utm_campaign'] ?? '',
                'utm_term' => $_SESSION['utm_term'] ?? '',
                'utm_content' => $_SESSION['utm_content'] ?? ''
            );
        }

        // Fallback to cookies
        if (empty($utm_data['utm_source'])) {
            $utm_data = array(
                'utm_source' => $_COOKIE['utm_source'] ?? '',
                'utm_medium' => $_COOKIE['utm_medium'] ?? '',
                'utm_campaign' => $_COOKIE['utm_campaign'] ?? '',
                'utm_term' => $_COOKIE['utm_term'] ?? '',
                'utm_content' => $_COOKIE['utm_content'] ?? ''
            );
        }

        return $utm_data;
    }

    private function detect_source() {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';

        if (strpos($referrer, 'facebook.com') !== false) {
            return 'facebook';
        } elseif (strpos($referrer, 'twitter.com') !== false) {
            return 'twitter';
        } elseif (strpos($referrer, 'google.com') !== false) {
            return 'google';
        } elseif (strpos($referrer, 'instagram.com') !== false) {
            return 'instagram';
        } elseif (empty($referrer)) {
            return 'direct';
        } else {
            return 'referral';
        }
    }

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=kh_event',
            __('Analytics & Reports', 'kh-events'),
            __('Analytics', 'kh-events'),
            'manage_options',
            'kh-events-analytics',
            array($this, 'render_analytics_page')
        );
    }

    public function render_analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('KH Events Analytics & Reports', 'kh-events'); ?></h1>

            <div class="kh-analytics-dashboard">
                <!-- Overview Cards -->
                <div class="kh-analytics-cards">
                    <div class="kh-card">
                        <h3><?php _e('Total Events', 'kh-events'); ?></h3>
                        <div class="kh-metric" id="total-events">-</div>
                    </div>
                    <div class="kh-card">
                        <h3><?php _e('Total Bookings', 'kh-events'); ?></h3>
                        <div class="kh-metric" id="total-bookings">-</div>
                    </div>
                    <div class="kh-card">
                        <h3><?php _e('Total Revenue', 'kh-events'); ?></h3>
                        <div class="kh-metric" id="total-revenue">$-</div>
                    </div>
                    <div class="kh-card">
                        <h3><?php _e('Event Views', 'kh-events'); ?></h3>
                        <div class="kh-metric" id="total-views">-</div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="kh-analytics-charts">
                    <div class="kh-chart-container">
                        <h3><?php _e('Revenue Over Time', 'kh-events'); ?></h3>
                        <canvas id="revenue-chart" width="400" height="200"></canvas>
                    </div>
                    <div class="kh-chart-container">
                        <h3><?php _e('Booking Sources', 'kh-events'); ?></h3>
                        <canvas id="sources-chart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Top Events Table -->
                <div class="kh-analytics-table">
                    <h3><?php _e('Top Performing Events', 'kh-events'); ?></h3>
                    <table class="wp-list-table widefat fixed striped" id="top-events-table">
                        <thead>
                            <tr>
                                <th><?php _e('Event', 'kh-events'); ?></th>
                                <th><?php _e('Views', 'kh-events'); ?></th>
                                <th><?php _e('Bookings', 'kh-events'); ?></th>
                                <th><?php _e('Revenue', 'kh-events'); ?></th>
                                <th><?php _e('Conversion Rate', 'kh-events'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5"><?php _e('Loading...', 'kh-events'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <style>
            .kh-analytics-dashboard { margin-top: 20px; }
            .kh-analytics-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .kh-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .kh-card h3 { margin: 0 0 10px 0; color: #23282d; font-size: 14px; text-transform: uppercase; }
            .kh-metric { font-size: 32px; font-weight: bold; color: #007cba; }
            .kh-analytics-charts { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .kh-chart-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .kh-analytics-table { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        </style>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        if ('kh_event_page_kh-events-analytics' !== $hook) {
            return;
        }

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        wp_enqueue_script('kh-events-analytics', KH_EVENTS_URL . 'assets/js/analytics.js', array('jquery', 'chart-js'), KH_EVENTS_VERSION, true);

        wp_localize_script('kh-events-analytics', 'kh_events_analytics', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kh_events_analytics')
        ));
    }

    public function ajax_get_analytics_data() {
        check_ajax_referer('kh_events_analytics', 'nonce');

        $period = $_POST['period'] ?? '30';
        $data = $this->get_analytics_data($period);

        wp_send_json_success($data);
    }

    private function get_analytics_data($days = 30) {
        global $wpdb;

        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        // Overview metrics
        $overview = array(
            'total_events' => wp_count_posts('kh_event')->publish,
            'total_bookings' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kh_booking_analytics WHERE booking_date >= '{$start_date}'"),
            'total_revenue' => $wpdb->get_var("SELECT SUM(total_revenue) FROM {$wpdb->prefix}kh_event_revenue WHERE date >= '{$start_date}'"),
            'total_views' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kh_event_views WHERE view_date >= '{$start_date}'")
        );

        // Revenue chart data
        $revenue_data = $wpdb->get_results($wpdb->prepare(
            "SELECT date, SUM(total_revenue) as revenue FROM {$wpdb->prefix}kh_event_revenue
             WHERE date >= %s GROUP BY date ORDER BY date",
            $start_date
        ), ARRAY_A);

        // Sources chart data
        $sources_data = $wpdb->get_results($wpdb->prepare(
            "SELECT source, COUNT(*) as count FROM {$wpdb->prefix}kh_booking_analytics
             WHERE booking_date >= %s GROUP BY source",
            $start_date
        ), ARRAY_A);

        // Top events
        $top_events = $wpdb->get_results($wpdb->prepare(
            "SELECT
                e.post_title as event_title,
                COUNT(DISTINCT v.id) as views,
                COUNT(DISTINCT b.id) as bookings,
                COALESCE(SUM(b.total_amount), 0) as revenue,
                CASE WHEN COUNT(DISTINCT v.id) > 0 THEN ROUND((COUNT(DISTINCT b.id) / COUNT(DISTINCT v.id)) * 100, 2) ELSE 0 END as conversion_rate
             FROM {$wpdb->posts} e
             LEFT JOIN {$wpdb->prefix}kh_event_views v ON e.ID = v.event_id AND v.view_date >= %s
             LEFT JOIN {$wpdb->prefix}kh_booking_analytics b ON e.ID = b.event_id AND b.booking_date >= %s
             WHERE e.post_type = 'kh_event' AND e.post_status = 'publish'
             GROUP BY e.ID, e.post_title
             ORDER BY revenue DESC
             LIMIT 10",
            $start_date, $start_date
        ), ARRAY_A);

        return array(
            'overview' => $overview,
            'revenue_chart' => $revenue_data,
            'sources_chart' => $sources_data,
            'top_events' => $top_events
        );
    }
}