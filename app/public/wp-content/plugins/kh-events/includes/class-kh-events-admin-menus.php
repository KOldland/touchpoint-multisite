<?php
/**
 * KH Events Admin Menus
 *
 * Handles admin menu registration and structure
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Admin_Menus {

    /**
     * Register admin menus
     */
    public function register_menus() {
        // Main menu
        add_menu_page(
            __('KH Events', 'kh-events'),
            __('KH Events', 'kh-events'),
            'manage_options',
            'kh-events',
            array($this, 'main_dashboard_page'),
            'dashicons-calendar-alt',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'kh-events',
            __('Dashboard', 'kh-events'),
            __('Dashboard', 'kh-events'),
            'manage_options',
            'kh-events',
            array($this, 'main_dashboard_page')
        );

        // Settings submenu
        add_submenu_page(
            'kh-events',
            __('Settings', 'kh-events'),
            __('Settings', 'kh-events'),
            'manage_options',
            'kh-events-settings',
            array($this, 'settings_page')
        );

        // Tools submenu
        add_submenu_page(
            'kh-events',
            __('Tools', 'kh-events'),
            __('Tools', 'kh-events'),
            'manage_options',
            'kh-events-tools',
            array($this, 'tools_page')
        );

        // Reports submenu
        add_submenu_page(
            'kh-events',
            __('Reports', 'kh-events'),
            __('Reports', 'kh-events'),
            'manage_options',
            'kh-events-reports',
            array($this, 'reports_page')
        );
    }

    /**
     * Main dashboard page
     */
    public function main_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('KH Events Dashboard', 'kh-events'); ?></h1>

            <div class="kh-events-dashboard">
                <?php $this->render_dashboard_stats(); ?>
                <?php $this->render_recent_events(); ?>
                <?php $this->render_upcoming_events(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('KH Events Settings', 'kh-events'); ?></h1>

            <?php settings_errors(); ?>

            <div class="kh-events-settings">
                <div class="kh-events-settings-sidebar">
                    <ul class="kh-events-settings-tabs">
                        <li class="active" data-tab="general"><?php _e('General', 'kh-events'); ?></li>
                        <li data-tab="booking"><?php _e('Booking', 'kh-events'); ?></li>
                        <li data-tab="display"><?php _e('Display', 'kh-events'); ?></li>
                        <li data-tab="email"><?php _e('Email', 'kh-events'); ?></li>
                        <li data-tab="payment"><?php _e('Payment', 'kh-events'); ?></li>
                    </ul>
                </div>

                <div class="kh-events-settings-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('kh_events_settings');
                        do_settings_sections('kh_events_settings');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Tools page
     */
    public function tools_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('KH Events Tools', 'kh-events'); ?></h1>

            <div class="kh-events-tools">
                <div class="kh-events-tool-section">
                    <h3><?php _e('Data Management', 'kh-events'); ?></h3>
                    <p><?php _e('Import, export, and manage your event data.', 'kh-events'); ?></p>
                    <button class="button button-primary"><?php _e('Import Events', 'kh-events'); ?></button>
                    <button class="button"><?php _e('Export Events', 'kh-events'); ?></button>
                </div>

                <div class="kh-events-tool-section">
                    <h3><?php _e('System Status', 'kh-events'); ?></h3>
                    <p><?php _e('Check system requirements and troubleshoot issues.', 'kh-events'); ?></p>
                    <button class="button"><?php _e('Run System Check', 'kh-events'); ?></button>
                </div>

                <div class="kh-events-tool-section">
                    <h3><?php _e('Database Maintenance', 'kh-events'); ?></h3>
                    <p><?php _e('Optimize and repair database tables.', 'kh-events'); ?></p>
                    <button class="button"><?php _e('Optimize Tables', 'kh-events'); ?></button>
                    <button class="button"><?php _e('Repair Tables', 'kh-events'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Reports page
     */
    public function reports_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('KH Events Reports', 'kh-events'); ?></h1>

            <div class="kh-events-reports">
                <div class="kh-events-report-filters">
                    <select name="report_type">
                        <option value="bookings"><?php _e('Booking Report', 'kh-events'); ?></option>
                        <option value="revenue"><?php _e('Revenue Report', 'kh-events'); ?></option>
                        <option value="attendance"><?php _e('Attendance Report', 'kh-events'); ?></option>
                    </select>

                    <input type="date" name="start_date" placeholder="Start Date">
                    <input type="date" name="end_date" placeholder="End Date">

                    <button class="button button-primary"><?php _e('Generate Report', 'kh-events'); ?></button>
                </div>

                <div class="kh-events-report-content">
                    <!-- Report content will be loaded here -->
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render dashboard statistics
     */
    private function render_dashboard_stats() {
        // Get stats from database
        global $wpdb;
        $database = kh_events_get_service('kh_events_db');

        // Count events
        $total_events = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'kh_event' AND post_status = 'publish'");

        // Count upcoming events
        $upcoming_events = $database->get_events_by_date_range(date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));

        // Count total bookings
        $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'kh_booking'");

        ?>
        <div class="kh-events-stats-grid">
            <div class="kh-events-stat-card">
                <h3><?php echo esc_html($total_events); ?></h3>
                <p><?php _e('Total Events', 'kh-events'); ?></p>
            </div>

            <div class="kh-events-stat-card">
                <h3><?php echo esc_html(count($upcoming_events)); ?></h3>
                <p><?php _e('Upcoming Events', 'kh-events'); ?></p>
            </div>

            <div class="kh-events-stat-card">
                <h3><?php echo esc_html($total_bookings); ?></h3>
                <p><?php _e('Total Bookings', 'kh-events'); ?></p>
            </div>

            <div class="kh-events-stat-card">
                <h3>$0</h3>
                <p><?php _e('Revenue', 'kh-events'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render recent events
     */
    private function render_recent_events() {
        $recent_events = get_posts(array(
            'post_type' => 'kh_event',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        ?>
        <div class="kh-events-dashboard-section">
            <h2><?php _e('Recent Events', 'kh-events'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Event', 'kh-events'); ?></th>
                        <th><?php _e('Date', 'kh-events'); ?></th>
                        <th><?php _e('Status', 'kh-events'); ?></th>
                        <th><?php _e('Actions', 'kh-events'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_events as $event): ?>
                        <tr>
                            <td><a href="<?php echo get_edit_post_link($event->ID); ?>"><?php echo esc_html($event->post_title); ?></a></td>
                            <td><?php echo get_the_date('', $event->ID); ?></td>
                            <td><?php _e('Scheduled', 'kh-events'); ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($event->ID); ?>" class="button button-small"><?php _e('Edit', 'kh-events'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render upcoming events
     */
    private function render_upcoming_events() {
        $database = kh_events_get_service('kh_events_db');
        $upcoming_events = $database->get_events_by_date_range(date('Y-m-d'), date('Y-m-d', strtotime('+7 days')));

        ?>
        <div class="kh-events-dashboard-section">
            <h2><?php _e('Upcoming Events (Next 7 Days)', 'kh-events'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Event', 'kh-events'); ?></th>
                        <th><?php _e('Date & Time', 'kh-events'); ?></th>
                        <th><?php _e('Location', 'kh-events'); ?></th>
                        <th><?php _e('Bookings', 'kh-events'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($upcoming_events, 0, 5) as $event): ?>
                        <tr>
                            <td><?php echo esc_html($event['title']); ?></td>
                            <td><?php echo date_i18n('M j, Y g:i A', strtotime($event['start_date'])); ?></td>
                            <td><?php _e('TBD', 'kh-events'); ?></td>
                            <td><?php echo esc_html($event['current_bookings'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}