<?php
/**
 * KH Events Analytics & Reporting
 * Comprehensive analytics and reporting system for enterprise event management
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_Analytics {

    private static $instance = null;

    // Analytics data types
    const DATA_TYPE_ATTENDANCE = 'attendance';
    const DATA_TYPE_REVENUE = 'revenue';
    const DATA_TYPE_ENGAGEMENT = 'engagement';
    const DATA_TYPE_OPERATIONAL = 'operational';

    // Time periods
    const PERIOD_TODAY = 'today';
    const PERIOD_WEEK = 'week';
    const PERIOD_MONTH = 'month';
    const PERIOD_QUARTER = 'quarter';
    const PERIOD_YEAR = 'year';
    const PERIOD_CUSTOM = 'custom';

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
        add_action('init', array($this, 'init'));

        // Data collection hooks
        add_action('kh_event_booking_created', array($this, 'track_booking_created'), 10, 2);
        add_action('kh_event_booking_cancelled', array($this, 'track_booking_cancelled'), 10, 2);
        add_action('kh_event_payment_completed', array($this, 'track_payment_completed'), 10, 2);
        add_action('kh_event_attendance_marked', array($this, 'track_attendance'), 10, 3);
        add_action('kh_event_viewed', array($this, 'track_event_view'), 10, 2);

        // Admin hooks
        add_action('admin_menu', array($this, 'add_analytics_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));

        // AJAX handlers
        add_action('wp_ajax_kh_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_kh_export_analytics_report', array($this, 'ajax_export_report'));
        add_action('wp_ajax_kh_schedule_analytics_report', array($this, 'ajax_schedule_report'));

        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));

        // Cron jobs for automated reports
        add_action('kh_daily_analytics_cleanup', array($this, 'cleanup_old_data'));
        add_action('kh_weekly_analytics_report', array($this, 'generate_weekly_report'));
        add_action('kh_monthly_analytics_report', array($this, 'generate_monthly_report'));
    }

    public function init() {
        $this->create_analytics_tables();
        $this->schedule_cron_jobs();
    }

    private function create_analytics_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Events analytics table
        $table_name = $wpdb->prefix . 'kh_events_analytics';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            data_type varchar(50) NOT NULL,
            metric_key varchar(100) NOT NULL,
            metric_value text NOT NULL,
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20),
            session_id varchar(100),
            metadata longtext,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY data_type (data_type),
            KEY metric_key (metric_key),
            KEY recorded_at (recorded_at),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Reports table
        $reports_table = $wpdb->prefix . 'kh_events_reports';
        $reports_sql = "CREATE TABLE $reports_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            report_type varchar(50) NOT NULL,
            report_title varchar(255) NOT NULL,
            report_data longtext NOT NULL,
            generated_by bigint(20) NOT NULL,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            date_from date,
            date_to date,
            filters longtext,
            file_path varchar(255),
            PRIMARY KEY (id),
            KEY report_type (report_type),
            KEY generated_by (generated_by),
            KEY generated_at (generated_at)
        ) $charset_collate;";

        // Scheduled reports table
        $scheduled_table = $wpdb->prefix . 'kh_events_scheduled_reports';
        $scheduled_sql = "CREATE TABLE $scheduled_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            report_type varchar(50) NOT NULL,
            frequency varchar(20) NOT NULL,
            recipients text NOT NULL,
            filters longtext,
            last_sent datetime,
            next_send datetime,
            is_active tinyint(1) DEFAULT 1,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY frequency (frequency),
            KEY next_send (next_send),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($reports_sql);
        dbDelta($scheduled_sql);
    }

    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('kh_daily_analytics_cleanup')) {
            wp_schedule_event(time(), 'daily', 'kh_daily_analytics_cleanup');
        }
        if (!wp_next_scheduled('kh_weekly_analytics_report')) {
            wp_schedule_event(strtotime('next monday'), 'weekly', 'kh_weekly_analytics_report');
        }
        if (!wp_next_scheduled('kh_monthly_analytics_report')) {
            wp_schedule_event(strtotime('first day of next month'), 'monthly', 'kh_monthly_analytics_report');
        }
    }

    // Data Collection Methods

    public function track_booking_created($booking_id, $event_id) {
        $this->record_metric($event_id, self::DATA_TYPE_ENGAGEMENT, 'booking_created', 1, array(
            'booking_id' => $booking_id,
            'user_id' => get_current_user_id()
        ));
    }

    public function track_booking_cancelled($booking_id, $event_id) {
        $this->record_metric($event_id, self::DATA_TYPE_ENGAGEMENT, 'booking_cancelled', 1, array(
            'booking_id' => $booking_id,
            'user_id' => get_current_user_id()
        ));
    }

    public function track_payment_completed($booking_id, $amount) {
        $event_id = get_post_meta($booking_id, '_kh_event_id', true);
        if ($event_id) {
            $this->record_metric($event_id, self::DATA_TYPE_REVENUE, 'payment_completed', $amount, array(
                'booking_id' => $booking_id,
                'currency' => get_option('kh_events_currency', 'USD')
            ));
        }
    }

    public function track_attendance($event_id, $user_id, $status) {
        $this->record_metric($event_id, self::DATA_TYPE_ATTENDANCE, 'attendance_' . $status, 1, array(
            'user_id' => $user_id,
            'status' => $status
        ));
    }

    public function track_event_view($event_id, $user_id = null) {
        $this->record_metric($event_id, self::DATA_TYPE_ENGAGEMENT, 'event_view', 1, array(
            'user_id' => $user_id ?: 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
        ));
    }

    private function record_metric($event_id, $data_type, $metric_key, $metric_value, $metadata = array()) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'kh_events_analytics';

        $wpdb->insert(
            $table_name,
            array(
                'event_id' => $event_id,
                'data_type' => $data_type,
                'metric_key' => $metric_key,
                'metric_value' => maybe_serialize($metric_value),
                'user_id' => get_current_user_id(),
                'session_id' => session_id() ?: $this->generate_session_id(),
                'metadata' => maybe_serialize($metadata)
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
        );
    }

    private function generate_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }

    // Analytics Retrieval Methods

    public function get_analytics_data($event_id = null, $data_type = null, $period = self::PERIOD_MONTH, $date_from = null, $date_to = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'kh_events_analytics';

        $where = array();
        $where_values = array();

        if ($event_id) {
            $where[] = 'event_id = %d';
            $where_values[] = $event_id;
        }

        if ($data_type) {
            $where[] = 'data_type = %s';
            $where_values[] = $data_type;
        }

        // Date filtering
        $date_range = $this->get_date_range($period, $date_from, $date_to);
        if ($date_range) {
            $where[] = 'recorded_at BETWEEN %s AND %s';
            $where_values[] = $date_range['from'];
            $where_values[] = $date_range['to'];
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
            data_type,
            metric_key,
            COUNT(*) as count,
            SUM(CAST(metric_value AS DECIMAL(10,2))) as total_value,
            DATE(recorded_at) as date
        FROM $table_name
        $where_clause
        GROUP BY data_type, metric_key, DATE(recorded_at)
        ORDER BY date DESC, data_type, metric_key";

        $prepared_sql = $wpdb->prepare($sql, $where_values);
        $results = $wpdb->get_results($prepared_sql, ARRAY_A);

        return $this->format_analytics_results($results);
    }

    public function get_dashboard_metrics() {
        $metrics = array();

        // Total events
        $metrics['total_events'] = wp_count_posts('kh_event')->publish;

        // Total bookings this month
        $monthly_bookings = $this->get_analytics_data(null, self::DATA_TYPE_ENGAGEMENT, self::PERIOD_MONTH);
        $metrics['monthly_bookings'] = isset($monthly_bookings['engagement']['booking_created']['total']) ?
            $monthly_bookings['engagement']['booking_created']['total'] : 0;

        // Total revenue this month
        $monthly_revenue = $this->get_analytics_data(null, self::DATA_TYPE_REVENUE, self::PERIOD_MONTH);
        $metrics['monthly_revenue'] = isset($monthly_revenue['revenue']['payment_completed']['total_value']) ?
            $monthly_revenue['revenue']['payment_completed']['total_value'] : 0;

        // Attendance rate
        $attendance_data = $this->get_analytics_data(null, self::DATA_TYPE_ATTENDANCE, self::PERIOD_MONTH);
        $total_attended = isset($attendance_data['attendance']['attendance_present']['total']) ?
            $attendance_data['attendance']['attendance_present']['total'] : 0;
        $total_registered = isset($attendance_data['attendance']['attendance_registered']['total']) ?
            $attendance_data['attendance']['attendance_registered']['total'] : 0;

        $metrics['attendance_rate'] = $total_registered > 0 ?
            round(($total_attended / $total_registered) * 100, 1) : 0;

        // Popular events
        $metrics['popular_events'] = $this->get_popular_events(5);

        return $metrics;
    }

    public function get_popular_events($limit = 10) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'kh_events_analytics';

        $sql = $wpdb->prepare("
            SELECT
                e.ID,
                e.post_title as title,
                COUNT(CASE WHEN a.metric_key = 'event_view' THEN 1 END) as views,
                COUNT(CASE WHEN a.metric_key = 'booking_created' THEN 1 END) as bookings
            FROM {$wpdb->posts} e
            LEFT JOIN $table_name a ON e.ID = a.event_id
            WHERE e.post_type = 'kh_event'
            AND e.post_status = 'publish'
            AND a.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY e.ID, e.post_title
            ORDER BY bookings DESC, views DESC
            LIMIT %d
        ", $limit);

        return $wpdb->get_results($sql, ARRAY_A);
    }

    private function get_date_range($period, $date_from = null, $date_to = null) {
        if ($period === self::PERIOD_CUSTOM && $date_from && $date_to) {
            return array(
                'from' => $date_from . ' 00:00:00',
                'to' => $date_to . ' 23:59:59'
            );
        }

        $now = current_time('mysql');
        $date = strtotime($now);

        switch ($period) {
            case self::PERIOD_TODAY:
                $from = date('Y-m-d 00:00:00', $date);
                $to = date('Y-m-d 23:59:59', $date);
                break;
            case self::PERIOD_WEEK:
                $from = date('Y-m-d 00:00:00', strtotime('monday this week', $date));
                $to = date('Y-m-d 23:59:59', strtotime('sunday this week', $date));
                break;
            case self::PERIOD_MONTH:
                $from = date('Y-m-01 00:00:00', $date);
                $to = date('Y-m-t 23:59:59', $date);
                break;
            case self::PERIOD_QUARTER:
                $quarter = ceil(date('n', $date) / 3);
                $year = date('Y', $date);
                $from = date('Y-m-d 00:00:00', strtotime($year . '-' . (($quarter - 1) * 3 + 1) . '-01'));
                $to = date('Y-m-d 23:59:59', strtotime($year . '-' . ($quarter * 3) . '-' . date('t', strtotime($year . '-' . ($quarter * 3) . '-01'))));
                break;
            case self::PERIOD_YEAR:
                $from = date('Y-01-01 00:00:00', $date);
                $to = date('Y-12-31 23:59:59', $date);
                break;
            default:
                return null;
        }

        return array('from' => $from, 'to' => $to);
    }

    private function format_analytics_results($results) {
        $formatted = array();

        foreach ($results as $result) {
            $data_type = $result['data_type'];
            $metric_key = $result['metric_key'];

            if (!isset($formatted[$data_type])) {
                $formatted[$data_type] = array();
            }

            if (!isset($formatted[$data_type][$metric_key])) {
                $formatted[$data_type][$metric_key] = array(
                    'count' => 0,
                    'total' => 0,
                    'total_value' => 0,
                    'daily' => array()
                );
            }

            $formatted[$data_type][$metric_key]['count'] += intval($result['count']);
            $formatted[$data_type][$metric_key]['total'] += intval($result['count']);
            $formatted[$data_type][$metric_key]['total_value'] += floatval($result['total_value']);
            $formatted[$data_type][$metric_key]['daily'][$result['date']] = array(
                'count' => intval($result['count']),
                'value' => floatval($result['total_value'])
            );
        }

        return $formatted;
    }

    // Reporting Methods

    public function generate_report($report_type, $filters = array(), $format = 'html') {
        $data = array();

        switch ($report_type) {
            case 'event_performance':
                $data = $this->generate_event_performance_report($filters);
                break;
            case 'revenue':
                $data = $this->generate_revenue_report($filters);
                break;
            case 'attendance':
                $data = $this->generate_attendance_report($filters);
                break;
            case 'user_engagement':
                $data = $this->generate_engagement_report($filters);
                break;
            case 'operational':
                $data = $this->generate_operational_report($filters);
                break;
        }

        return $this->format_report($data, $report_type, $format);
    }

    private function generate_event_performance_report($filters) {
        $events = get_posts(array(
            'post_type' => 'kh_event',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'date_query' => isset($filters['date_range']) ? $filters['date_range'] : null
        ));

        $report_data = array();

        foreach ($events as $event) {
            $analytics = $this->get_analytics_data($event->ID);

            $report_data[] = array(
                'event_id' => $event->ID,
                'event_title' => $event->post_title,
                'event_date' => get_post_meta($event->ID, '_kh_event_date', true),
                'views' => isset($analytics['engagement']['event_view']['total']) ?
                    $analytics['engagement']['event_view']['total'] : 0,
                'bookings' => isset($analytics['engagement']['booking_created']['total']) ?
                    $analytics['engagement']['booking_created']['total'] : 0,
                'revenue' => isset($analytics['revenue']['payment_completed']['total_value']) ?
                    $analytics['revenue']['payment_completed']['total_value'] : 0,
                'attendance' => isset($analytics['attendance']['attendance_present']['total']) ?
                    $analytics['attendance']['attendance_present']['total'] : 0
            );
        }

        return $report_data;
    }

    private function generate_revenue_report($filters) {
        $analytics = $this->get_analytics_data(null, self::DATA_TYPE_REVENUE,
            $filters['period'] ?? self::PERIOD_MONTH,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null);

        return isset($analytics['revenue']) ? $analytics['revenue'] : array();
    }

    private function generate_attendance_report($filters) {
        $analytics = $this->get_analytics_data(null, self::DATA_TYPE_ATTENDANCE,
            $filters['period'] ?? self::PERIOD_MONTH,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null);

        return isset($analytics['attendance']) ? $analytics['attendance'] : array();
    }

    private function generate_engagement_report($filters) {
        $analytics = $this->get_analytics_data(null, self::DATA_TYPE_ENGAGEMENT,
            $filters['period'] ?? self::PERIOD_MONTH,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null);

        return isset($analytics['engagement']) ? $analytics['engagement'] : array();
    }

    private function generate_operational_report($filters) {
        // Event creation stats
        $event_stats = wp_count_posts('kh_event');

        // User registration stats
        $user_stats = count_users();

        // Booking stats
        $booking_stats = array(
            'total_bookings' => get_option('kh_events_total_bookings', 0),
            'cancelled_bookings' => get_option('kh_events_cancelled_bookings', 0)
        );

        return array(
            'events' => $event_stats,
            'users' => $user_stats,
            'bookings' => $booking_stats
        );
    }

    private function format_report($data, $report_type, $format) {
        switch ($format) {
            case 'csv':
                return $this->format_csv_report($data, $report_type);
            case 'pdf':
                return $this->format_pdf_report($data, $report_type);
            case 'json':
                return wp_json_encode($data);
            case 'html':
            default:
                return $this->format_html_report($data, $report_type);
        }
    }

    private function format_csv_report($data, $report_type) {
        if (empty($data)) return '';

        $output = fopen('php://temp', 'r+');

        // Add headers
        if (is_array($data) && isset($data[0])) {
            fputcsv($output, array_keys($data[0]));
        }

        // Add data
        foreach ($data as $row) {
            if (is_array($row)) {
                fputcsv($output, $row);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private function format_html_report($data, $report_type) {
        ob_start();
        ?>
        <div class="kh-analytics-report">
            <h2><?php echo esc_html(ucwords(str_replace('_', ' ', $report_type)) . ' Report'); ?></h2>
            <p>Generated on: <?php echo current_time('F j, Y \a\t g:i A'); ?></p>

            <?php if (is_array($data) && !empty($data)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($data[0]) as $header): ?>
                                <th><?php echo esc_html(ucwords(str_replace('_', ' ', $header))); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <?php foreach ($row as $value): ?>
                                    <td><?php echo is_numeric($value) ? number_format($value) : esc_html($value); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No data available for the selected criteria.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function format_pdf_report($data, $report_type) {
        // PDF generation would require a PDF library like TCPDF or FPDF
        // For now, return HTML that can be converted to PDF
        return $this->format_html_report($data, $report_type);
    }

    // Admin Interface Methods

    public function add_analytics_menu() {
        add_submenu_page(
            'edit.php?post_type=kh_event',
            __('Analytics & Reports', 'kh-events'),
            __('Analytics & Reports', 'kh-events'),
            'manage_options',
            'kh-events-analytics',
            array($this, 'analytics_page')
        );
    }

    public function analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('KH Events Analytics & Reports', 'kh-events'); ?></h1>

            <div class="kh-analytics-dashboard">
                <div class="kh-analytics-filters">
                    <form method="get" action="">
                        <input type="hidden" name="post_type" value="kh_event">
                        <input type="hidden" name="page" value="kh-events-analytics">

                        <select name="report_type">
                            <option value="event_performance"><?php _e('Event Performance', 'kh-events'); ?></option>
                            <option value="revenue"><?php _e('Revenue Report', 'kh-events'); ?></option>
                            <option value="attendance"><?php _e('Attendance Report', 'kh-events'); ?></option>
                            <option value="user_engagement"><?php _e('User Engagement', 'kh-events'); ?></option>
                            <option value="operational"><?php _e('Operational Report', 'kh-events'); ?></option>
                        </select>

                        <select name="period">
                            <option value="today"><?php _e('Today', 'kh-events'); ?></option>
                            <option value="week"><?php _e('This Week', 'kh-events'); ?></option>
                            <option value="month" selected><?php _e('This Month', 'kh-events'); ?></option>
                            <option value="quarter"><?php _e('This Quarter', 'kh-events'); ?></option>
                            <option value="year"><?php _e('This Year', 'kh-events'); ?></option>
                        </select>

                        <button type="submit" class="button button-primary"><?php _e('Generate Report', 'kh-events'); ?></button>
                        <button type="button" class="button" id="kh-export-report"><?php _e('Export', 'kh-events'); ?></button>
                    </form>
                </div>

                <div id="kh-analytics-results">
                    <?php
                    $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'event_performance';
                    $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';

                    $filters = array('period' => $period);
                    $report = $this->generate_report($report_type, $filters);

                    echo $report;
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_analytics_scripts($hook) {
        if (strpos($hook, 'kh-events-analytics') !== false) {
            wp_enqueue_script('kh-analytics', KH_EVENTS_URL . 'assets/js/analytics.js', array('jquery'), KH_EVENTS_VERSION, true);
            wp_enqueue_style('kh-analytics', KH_EVENTS_URL . 'assets/css/analytics.css', array(), KH_EVENTS_VERSION);

            wp_localize_script('kh-analytics', 'kh_analytics_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kh_analytics_nonce')
            ));
        }
    }

    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'kh_events_analytics_widget',
            __('KH Events Analytics', 'kh-events'),
            array($this, 'dashboard_widget_content')
        );
    }

    public function dashboard_widget_content() {
        $metrics = $this->get_dashboard_metrics();
        ?>
        <div class="kh-dashboard-metrics">
            <div class="metric-item">
                <h4><?php _e('Total Events', 'kh-events'); ?></h4>
                <span class="metric-value"><?php echo number_format($metrics['total_events']); ?></span>
            </div>

            <div class="metric-item">
                <h4><?php _e('Monthly Bookings', 'kh-events'); ?></h4>
                <span class="metric-value"><?php echo number_format($metrics['monthly_bookings']); ?></span>
            </div>

            <div class="metric-item">
                <h4><?php _e('Monthly Revenue', 'kh-events'); ?></h4>
                <span class="metric-value"><?php echo '$' . number_format($metrics['monthly_revenue'], 2); ?></span>
            </div>

            <div class="metric-item">
                <h4><?php _e('Attendance Rate', 'kh-events'); ?></h4>
                <span class="metric-value"><?php echo $metrics['attendance_rate'] . '%'; ?></span>
            </div>
        </div>

        <div class="kh-popular-events">
            <h4><?php _e('Popular Events', 'kh-events'); ?></h4>
            <ul>
                <?php foreach ($metrics['popular_events'] as $event): ?>
                    <li>
                        <a href="<?php echo get_edit_post_link($event['ID']); ?>">
                            <?php echo esc_html($event['title']); ?>
                        </a>
                        <span class="event-stats">
                            <?php echo sprintf(__('%d bookings, %d views', 'kh-events'), $event['bookings'], $event['views']); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    // AJAX Handlers

    public function ajax_get_analytics_data() {
        check_ajax_referer('kh_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'kh-events'));
        }

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
        $data_type = isset($_POST['data_type']) ? sanitize_text_field($_POST['data_type']) : null;
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : self::PERIOD_MONTH;

        $data = $this->get_analytics_data($event_id, $data_type, $period);

        wp_send_json_success($data);
    }

    public function ajax_export_report() {
        check_ajax_referer('kh_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'kh-events'));
        }

        $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : 'event_performance';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();

        $report_data = $this->generate_report($report_type, $filters, $format);

        // Save report to database
        global $wpdb;
        $reports_table = $wpdb->prefix . 'kh_events_reports';

        $wpdb->insert(
            $reports_table,
            array(
                'report_type' => $report_type,
                'report_title' => ucwords(str_replace('_', ' ', $report_type)) . ' Report',
                'report_data' => $report_data,
                'generated_by' => get_current_user_id(),
                'filters' => maybe_serialize($filters)
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );

        $report_id = $wpdb->insert_id;

        // Generate filename
        $filename = 'kh-events-' . $report_type . '-report-' . date('Y-m-d') . '.' . $format;

        // For CSV, send file directly
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $report_data;
            exit;
        }

        wp_send_json_success(array(
            'report_id' => $report_id,
            'filename' => $filename,
            'data' => $report_data
        ));
    }

    public function ajax_schedule_report() {
        check_ajax_referer('kh_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'kh-events'));
        }

        $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : '';
        $recipients = isset($_POST['recipients']) ? sanitize_textarea_field($_POST['recipients']) : '';
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();

        if (empty($report_type) || empty($frequency) || empty($recipients)) {
            wp_send_json_error(__('Missing required fields', 'kh-events'));
        }

        global $wpdb;
        $scheduled_table = $wpdb->prefix . 'kh_events_scheduled_reports';

        $next_send = $this->calculate_next_send_time($frequency);

        $wpdb->insert(
            $scheduled_table,
            array(
                'report_type' => $report_type,
                'frequency' => $frequency,
                'recipients' => $recipients,
                'filters' => maybe_serialize($filters),
                'next_send' => $next_send,
                'created_by' => get_current_user_id()
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d')
        );

        wp_send_json_success(__('Report scheduled successfully', 'kh-events'));
    }

    private function calculate_next_send_time($frequency) {
        $now = current_time('timestamp');

        switch ($frequency) {
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('tomorrow', $now));
            case 'weekly':
                return date('Y-m-d H:i:s', strtotime('next monday', $now));
            case 'monthly':
                return date('Y-m-d H:i:s', strtotime('first day of next month', $now));
            default:
                return date('Y-m-d H:i:s', strtotime('+1 day', $now));
        }
    }

    // Cleanup Methods

    public function cleanup_old_data() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'kh_events_analytics';

        // Keep data for 2 years
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-2 years'));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE recorded_at < %s",
            $cutoff_date
        ));
    }

    public function generate_weekly_report() {
        $this->send_scheduled_reports('weekly');
    }

    public function generate_monthly_report() {
        $this->send_scheduled_reports('monthly');
    }

    private function send_scheduled_reports($frequency) {
        global $wpdb;

        $scheduled_table = $wpdb->prefix . 'kh_events_scheduled_reports';

        $reports = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $scheduled_table WHERE frequency = %s AND is_active = 1 AND next_send <= NOW()",
            $frequency
        ));

        foreach ($reports as $report) {
            $this->send_scheduled_report($report);

            // Update next send time
            $next_send = $this->calculate_next_send_time($frequency);
            $wpdb->update(
                $scheduled_table,
                array('next_send' => $next_send, 'last_sent' => current_time('mysql')),
                array('id' => $report->id),
                array('%s', '%s'),
                array('%d')
            );
        }
    }

    private function send_scheduled_report($report) {
        $filters = maybe_unserialize($report->filters);
        $report_data = $this->generate_report($report->report_type, $filters, 'html');

        $subject = sprintf(__('KH Events %s Report - %s', 'kh-events'),
            ucwords(str_replace('_', ' ', $report->report_type)),
            date('F j, Y')
        );

        $recipients = array_map('trim', explode("\n", $report->recipients));

        foreach ($recipients as $recipient) {
            if (is_email($recipient)) {
                wp_mail($recipient, $subject, $report_data, array('Content-Type: text/html; charset=UTF-8'));
            }
        }
    }
}