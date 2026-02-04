<?php
/**
 * KH Events Import/Export Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_Import_Export {

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
        add_action('admin_menu', array($this, 'add_import_export_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_kh_import_events', array($this, 'ajax_import_events'));
        add_action('wp_ajax_kh_export_events', array($this, 'ajax_export_events'));
        add_action('wp_ajax_kh_import_ical', array($this, 'ajax_import_ical'));
        add_action('wp_ajax_kh_import_facebook', array($this, 'ajax_import_facebook'));
    }

    /**
     * Add import/export menu
     */
    public function add_import_export_menu() {
        add_submenu_page(
            'edit.php?post_type=kh_event',
            __('Import/Export Events', 'kh-events'),
            __('Import/Export', 'kh-events'),
            'manage_options',
            'kh-events-import-export',
            array($this, 'render_import_export_page')
        );
    }

    /**
     * Render import/export page
     */
    public function render_import_export_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Import/Export Events', 'kh-events'); ?></h1>

            <div class="kh-import-export-tabs">
                <div class="nav-tab-wrapper">
                    <a href="#kh-export" class="nav-tab nav-tab-active"><?php _e('Export', 'kh-events'); ?></a>
                    <a href="#kh-import" class="nav-tab"><?php _e('Import', 'kh-events'); ?></a>
                    <a href="#kh-ical" class="nav-tab"><?php _e('iCal', 'kh-events'); ?></a>
                    <a href="#kh-facebook" class="nav-tab"><?php _e('Facebook Events', 'kh-events'); ?></a>
                </div>

                <div id="kh-export" class="tab-content">
                    <?php $this->render_export_tab(); ?>
                </div>

                <div id="kh-import" class="tab-content" style="display:none;">
                    <?php $this->render_import_tab(); ?>
                </div>

                <div id="kh-ical" class="tab-content" style="display:none;">
                    <?php $this->render_ical_tab(); ?>
                </div>

                <div id="kh-facebook" class="tab-content" style="display:none;">
                    <?php $this->render_facebook_tab(); ?>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });
        });
        </script>
        <?php
    }

    /**
     * Render export tab
     */
    private function render_export_tab() {
        ?>
        <div class="kh-export-section">
            <h2><?php _e('Export Events', 'kh-events'); ?></h2>
            <p><?php _e('Export your events to CSV or iCal format.', 'kh-events'); ?></p>

            <form id="kh-export-form" method="post">
                <?php wp_nonce_field('kh_export_events', 'kh_export_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="export_format"><?php _e('Export Format', 'kh-events'); ?></label></th>
                        <td>
                            <select name="export_format" id="export_format">
                                <option value="csv"><?php _e('CSV', 'kh-events'); ?></option>
                                <option value="ical"><?php _e('iCal', 'kh-events'); ?></option>
                                <option value="json"><?php _e('JSON', 'kh-events'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th><label><?php _e('Date Range', 'kh-events'); ?></label></th>
                        <td>
                            <label>
                                <input type="radio" name="date_range" value="all" checked />
                                <?php _e('All events', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="date_range" value="upcoming" />
                                <?php _e('Upcoming events only', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="date_range" value="custom" />
                                <?php _e('Custom date range:', 'kh-events'); ?>
                                <input type="date" name="start_date" id="export_start_date" disabled />
                                <?php _e('to', 'kh-events'); ?>
                                <input type="date" name="end_date" id="export_end_date" disabled />
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><label><?php _e('Event Status', 'kh-events'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_published" checked />
                                <?php _e('Published events', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_draft" />
                                <?php _e('Draft events', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_pending" />
                                <?php _e('Pending events', 'kh-events'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><label><?php _e('Include Fields', 'kh-events'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_categories" checked />
                                <?php _e('Categories', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_tags" checked />
                                <?php _e('Tags', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_locations" checked />
                                <?php _e('Locations', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_custom_fields" checked />
                                <?php _e('Custom fields', 'kh-events'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="kh-export-button">
                        <?php _e('Export Events', 'kh-events'); ?>
                    </button>
                    <span class="spinner" style="float:none;"></span>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render import tab
     */
    private function render_import_tab() {
        ?>
        <div class="kh-import-section">
            <h2><?php _e('Import Events', 'kh-events'); ?></h2>
            <p><?php _e('Import events from CSV file. Download the sample CSV to see the required format.', 'kh-events'); ?></p>

            <div class="kh-import-sample">
                <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=kh_download_sample_csv'), 'kh_download_sample_csv'); ?>" class="button">
                    <?php _e('Download Sample CSV', 'kh-events'); ?>
                </a>
            </div>

            <form id="kh-import-form" enctype="multipart/form-data" method="post">
                <?php wp_nonce_field('kh_import_events', 'kh_import_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="import_file"><?php _e('CSV File', 'kh-events'); ?></label></th>
                        <td>
                            <input type="file" name="import_file" id="import_file" accept=".csv" required />
                            <p class="description"><?php _e('Select a CSV file to import events from.', 'kh-events'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><label><?php _e('Import Options', 'kh-events'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="skip_duplicates" checked />
                                <?php _e('Skip duplicate events (based on title and date)', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="update_existing" />
                                <?php _e('Update existing events with same title', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="import_categories" checked />
                                <?php _e('Import categories', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="import_locations" checked />
                                <?php _e('Import locations', 'kh-events'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="default_status"><?php _e('Default Status', 'kh-events'); ?></label></th>
                        <td>
                            <select name="default_status" id="default_status">
                                <option value="publish"><?php _e('Published', 'kh-events'); ?></option>
                                <option value="draft"><?php _e('Draft', 'kh-events'); ?></option>
                                <option value="pending"><?php _e('Pending Review', 'kh-events'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <div id="kh-import-progress" style="display:none;">
                    <div class="kh-progress-bar">
                        <div class="kh-progress-fill" style="width:0%"></div>
                    </div>
                    <p id="kh-import-status"><?php _e('Importing events...', 'kh-events'); ?></p>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="kh-import-button">
                        <?php _e('Import Events', 'kh-events'); ?>
                    </button>
                    <span class="spinner" style="float:none;"></span>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render iCal tab
     */
    private function render_ical_tab() {
        ?>
        <div class="kh-ical-section">
            <h2><?php _e('iCal Import/Export', 'kh-events'); ?></h2>

            <div class="kh-ical-export">
                <h3><?php _e('Export iCal Feed', 'kh-events'); ?></h3>
                <p><?php _e('Generate an iCal feed URL for your events.', 'kh-events'); ?></p>
                <input type="text" readonly value="<?php echo esc_url(site_url('/?kh_events_ical=1')); ?>" class="regular-text" />
                <button class="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">
                    <?php _e('Copy URL', 'kh-events'); ?>
                </button>
            </div>

            <div class="kh-ical-import">
                <h3><?php _e('Import from iCal URL', 'kh-events'); ?></h3>
                <form id="kh-ical-import-form">
                    <?php wp_nonce_field('kh_import_ical', 'kh_ical_nonce'); ?>
                    <p>
                        <input type="url" name="ical_url" placeholder="https://example.com/events.ics" class="regular-text" required />
                    </p>
                    <p>
                        <button type="submit" class="button button-primary"><?php _e('Import iCal', 'kh-events'); ?></button>
                        <span class="spinner" style="float:none;"></span>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render Facebook tab
     */
    private function render_facebook_tab() {
        ?>
        <div class="kh-facebook-section">
            <h2><?php _e('Facebook Events Import', 'kh-events'); ?></h2>
            <p><?php _e('Import events from Facebook pages or groups.', 'kh-events'); ?></p>

            <div class="notice notice-info">
                <p><?php _e('Facebook import requires a Facebook App ID and App Secret. Configure these in the settings.', 'kh-events'); ?></p>
            </div>

            <form id="kh-facebook-import-form">
                <?php wp_nonce_field('kh_import_facebook', 'kh_facebook_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="facebook_page_id"><?php _e('Facebook Page ID or URL', 'kh-events'); ?></label></th>
                        <td>
                            <input type="text" name="facebook_page_id" id="facebook_page_id" class="regular-text"
                                   placeholder="https://www.facebook.com/yourpage or page_id" required />
                        </td>
                    </tr>

                    <tr>
                        <th><label for="facebook_app_id"><?php _e('Facebook App ID', 'kh-events'); ?></label></th>
                        <td>
                            <input type="text" name="facebook_app_id" id="facebook_app_id" class="regular-text" />
                            <p class="description"><?php _e('Get this from your Facebook Developer Console.', 'kh-events'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="facebook_app_secret"><?php _e('Facebook App Secret', 'kh-events'); ?></label></th>
                        <td>
                            <input type="password" name="facebook_app_secret" id="facebook_app_secret" class="regular-text" />
                        </td>
                    </tr>

                    <tr>
                        <th><label><?php _e('Import Options', 'kh-events'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="import_past_events" />
                                <?php _e('Import past events', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="auto_import" />
                                <?php _e('Set up automatic daily import', 'kh-events'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Import Facebook Events', 'kh-events'); ?></button>
                    <span class="spinner" style="float:none;"></span>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX export events
     */
    public function ajax_export_events() {
        check_ajax_referer('kh_export_events', 'nonce');

        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $date_range = sanitize_text_field($_POST['date_range'] ?? 'all');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $statuses = array_filter(array_map('sanitize_text_field', $_POST['statuses'] ?? array()));

        $args = array(
            'post_type' => 'kh_event',
            'posts_per_page' => -1,
            'post_status' => $statuses ?: array('publish', 'draft', 'pending')
        );

        // Add date filtering
        if ($date_range === 'upcoming') {
            $args['meta_query'] = array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            );
        } elseif ($date_range === 'custom' && $start_date && $end_date) {
            $args['meta_query'] = array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => array($start_date, $end_date),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            );
        }

        $events = get_posts($args);

        switch ($format) {
            case 'csv':
                $this->export_csv($events);
                break;
            case 'ical':
                $this->export_ical($events);
                break;
            case 'json':
                $this->export_json($events);
                break;
        }

        wp_die();
    }

    /**
     * Export to CSV
     */
    private function export_csv($events) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=kh-events-export-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, array(
            'ID', 'Title', 'Description', 'Start Date', 'Start Time', 'End Date', 'End Time',
            'Location', 'Categories', 'Tags', 'Status', 'Author', 'Created', 'Modified'
        ));

        foreach ($events as $event) {
            $location = get_post_meta($event->ID, '_kh_event_location', true);
            $location_name = $location ? get_the_title($location['id'] ?? 0) : '';

            $categories = wp_get_post_terms($event->ID, 'kh_event_category', array('fields' => 'names'));
            $tags = wp_get_post_terms($event->ID, 'kh_event_tag', array('fields' => 'names'));

            fputcsv($output, array(
                $event->ID,
                $event->post_title,
                strip_tags($event->post_content),
                get_post_meta($event->ID, '_kh_event_start_date', true),
                get_post_meta($event->ID, '_kh_event_start_time', true),
                get_post_meta($event->ID, '_kh_event_end_date', true),
                get_post_meta($event->ID, '_kh_event_end_time', true),
                $location_name,
                implode(', ', $categories),
                implode(', ', $tags),
                $event->post_status,
                get_the_author_meta('display_name', $event->post_author),
                $event->post_date,
                $event->post_modified
            ));
        }

        fclose($output);
    }

    /**
     * Export to iCal
     */
    private function export_ical($events) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename=kh-events-export-' . date('Y-m-d') . '.ics');

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
            echo "SUMMARY:" . $this->escape_ical_text($event->post_title) . "\r\n";
            echo "DESCRIPTION:" . $this->escape_ical_text(strip_tags($event->post_content)) . "\r\n";
            echo "URL:" . get_permalink($event->ID) . "\r\n";
            echo "END:VEVENT\r\n";
        }

        echo "END:VCALENDAR\r\n";
    }

    /**
     * Export to JSON
     */
    private function export_json($events) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=kh-events-export-' . date('Y-m-d') . '.json');

        $export_data = array();
        foreach ($events as $event) {
            $export_data[] = array(
                'id' => $event->ID,
                'title' => $event->post_title,
                'content' => $event->post_content,
                'excerpt' => $event->post_excerpt,
                'status' => $event->post_status,
                'start_date' => get_post_meta($event->ID, '_kh_event_start_date', true),
                'start_time' => get_post_meta($event->ID, '_kh_event_start_time', true),
                'end_date' => get_post_meta($event->ID, '_kh_event_end_date', true),
                'end_time' => get_post_meta($event->ID, '_kh_event_end_time', true),
                'location' => get_post_meta($event->ID, '_kh_event_location', true),
                'categories' => wp_get_post_terms($event->ID, 'kh_event_category', array('fields' => 'names')),
                'tags' => wp_get_post_terms($event->ID, 'kh_event_tag', array('fields' => 'names')),
                'custom_fields' => get_post_custom($event->ID),
                'author' => get_the_author_meta('display_name', $event->post_author),
                'created' => $event->post_date,
                'modified' => $event->post_modified
            );
        }

        echo json_encode($export_data, JSON_PRETTY_PRINT);
    }

    /**
     * AJAX import events
     */
    public function ajax_import_events() {
        check_ajax_referer('kh_import_events', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (empty($_FILES['import_file'])) {
            wp_send_json_error('No file uploaded');
        }

        $file = $_FILES['import_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error');
        }

        $csv_data = $this->parse_csv_file($file['tmp_name']);
        if (!$csv_data) {
            wp_send_json_error('Invalid CSV file');
        }

        $options = array(
            'skip_duplicates' => isset($_POST['skip_duplicates']),
            'update_existing' => isset($_POST['update_existing']),
            'import_categories' => isset($_POST['import_categories']),
            'import_locations' => isset($_POST['import_locations']),
            'default_status' => sanitize_text_field($_POST['default_status'] ?? 'publish')
        );

        $results = $this->import_csv_data($csv_data, $options);

        wp_send_json_success($results);
    }

    /**
     * Parse CSV file
     */
    private function parse_csv_file($file_path) {
        $data = array();

        if (($handle = fopen($file_path, 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ',');

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($headers) === count($row)) {
                    $data[] = array_combine($headers, $row);
                }
            }

            fclose($handle);
        }

        return $data;
    }

    /**
     * Import CSV data
     */
    private function import_csv_data($csv_data, $options) {
        $imported = 0;
        $skipped = 0;
        $errors = array();

        foreach ($csv_data as $row) {
            try {
                $event_id = $this->import_single_event($row, $options);
                if ($event_id) {
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                $skipped++;
            }
        }

        return array(
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        );
    }

    /**
     * Import single event
     */
    private function import_single_event($row, $options) {
        // Check for duplicates
        if ($options['skip_duplicates']) {
            $existing = get_posts(array(
                'post_type' => 'kh_event',
                'title' => $row['Title'] ?? '',
                'meta_query' => array(
                    array(
                        'key' => '_kh_event_start_date',
                        'value' => $row['Start Date'] ?? '',
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            ));

            if (!empty($existing)) {
                return false;
            }
        }

        // Prepare event data
        $event_data = array(
            'post_title' => $row['Title'] ?? '',
            'post_content' => $row['Description'] ?? '',
            'post_status' => $options['default_status'],
            'post_type' => 'kh_event'
        );

        // Check if updating existing
        if ($options['update_existing']) {
            $existing = get_posts(array(
                'post_type' => 'kh_event',
                'title' => $row['Title'] ?? '',
                'posts_per_page' => 1
            ));

            if (!empty($existing)) {
                $event_data['ID'] = $existing[0]->ID;
            }
        }

        $event_id = wp_insert_post($event_data);

        if (!$event_id) {
            throw new Exception('Failed to create event: ' . $row['Title']);
        }

        // Save event meta
        if (!empty($row['Start Date'])) {
            update_post_meta($event_id, '_kh_event_start_date', sanitize_text_field($row['Start Date']));
        }
        if (!empty($row['Start Time'])) {
            update_post_meta($event_id, '_kh_event_start_time', sanitize_text_field($row['Start Time']));
        }
        if (!empty($row['End Date'])) {
            update_post_meta($event_id, '_kh_event_end_date', sanitize_text_field($row['End Date']));
        }
        if (!empty($row['End Time'])) {
            update_post_meta($event_id, '_kh_event_end_time', sanitize_text_field($row['End Time']));
        }

        // Import categories
        if ($options['import_categories'] && !empty($row['Categories'])) {
            $categories = array_map('trim', explode(',', $row['Categories']));
            wp_set_post_terms($event_id, $categories, 'kh_event_category');
        }

        // Import tags
        if (!empty($row['Tags'])) {
            $tags = array_map('trim', explode(',', $row['Tags']));
            wp_set_post_terms($event_id, $tags, 'kh_event_tag');
        }

        // Import location
        if ($options['import_locations'] && !empty($row['Location'])) {
            $location_id = $this->find_or_create_location($row['Location']);
            if ($location_id) {
                update_post_meta($event_id, '_kh_event_location', array(
                    'id' => $location_id,
                    'name' => $row['Location']
                ));
            }
        }

        return $event_id;
    }

    /**
     * Find or create location
     */
    private function find_or_create_location($location_name) {
        $existing = get_posts(array(
            'post_type' => 'kh_location',
            'title' => $location_name,
            'posts_per_page' => 1
        ));

        if (!empty($existing)) {
            return $existing[0]->ID;
        }

        // Create new location
        $location_id = wp_insert_post(array(
            'post_title' => $location_name,
            'post_type' => 'kh_location',
            'post_status' => 'publish'
        ));

        return $location_id;
    }

    /**
     * AJAX import iCal
     */
    public function ajax_import_ical() {
        check_ajax_referer('kh_import_ical', 'nonce');

        $ical_url = esc_url_raw($_POST['ical_url']);
        if (!$ical_url) {
            wp_send_json_error('Invalid iCal URL');
        }

        $ical_data = $this->fetch_ical_data($ical_url);
        if (!$ical_data) {
            wp_send_json_error('Failed to fetch iCal data');
        }

        $events = $this->parse_ical_events($ical_data);
        $imported = 0;

        foreach ($events as $event) {
            try {
                $this->import_ical_event($event);
                $imported++;
            } catch (Exception $e) {
                // Log error but continue
                error_log('iCal import error: ' . $e->getMessage());
            }
        }

        wp_send_json_success(array('imported' => $imported));
    }

    /**
     * Fetch iCal data
     */
    private function fetch_ical_data($url) {
        $response = wp_remote_get($url, array('timeout' => 30));

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Parse iCal events
     */
    private function parse_ical_events($ical_data) {
        $events = array();
        $lines = explode("\n", $ical_data);
        $in_event = false;
        $current_event = array();

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $in_event = true;
                $current_event = array();
            } elseif ($line === 'END:VEVENT') {
                $in_event = false;
                if (!empty($current_event)) {
                    $events[] = $current_event;
                }
            } elseif ($in_event) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $current_event[strtolower($key)] = $value;
                }
            }
        }

        return $events;
    }

    /**
     * Import iCal event
     */
    private function import_ical_event($ical_event) {
        $event_data = array(
            'post_title' => $ical_event['summary'] ?? 'Untitled Event',
            'post_content' => $ical_event['description'] ?? '',
            'post_status' => 'publish',
            'post_type' => 'kh_event'
        );

        $event_id = wp_insert_post($event_data);

        if ($event_id && isset($ical_event['dtstart'])) {
            $start_datetime = $this->parse_ical_datetime($ical_event['dtstart']);
            update_post_meta($event_id, '_kh_event_start_date', $start_datetime['date']);
            update_post_meta($event_id, '_kh_event_start_time', $start_datetime['time']);
        }

        if ($event_id && isset($ical_event['dtend'])) {
            $end_datetime = $this->parse_ical_datetime($ical_event['dtend']);
            update_post_meta($event_id, '_kh_event_end_date', $end_datetime['date']);
            update_post_meta($event_id, '_kh_event_end_time', $end_datetime['time']);
        }
    }

    /**
     * Parse iCal datetime
     */
    private function parse_ical_datetime($datetime) {
        // Handle different iCal datetime formats
        if (strlen($datetime) === 8) { // DATE format: 20231225
            $date = substr($datetime, 0, 4) . '-' . substr($datetime, 4, 2) . '-' . substr($datetime, 6, 2);
            return array('date' => $date, 'time' => '');
        } elseif (strlen($datetime) >= 15) { // DATETIME format: 20231225T143000
            $date = substr($datetime, 0, 4) . '-' . substr($datetime, 4, 2) . '-' . substr($datetime, 6, 2);
            $time = substr($datetime, 9, 2) . ':' . substr($datetime, 11, 2);
            if (strlen($datetime) > 13) {
                $time .= ':' . substr($datetime, 13, 2);
            }
            return array('date' => $date, 'time' => $time);
        }

        return array('date' => '', 'time' => '');
    }

    /**
     * AJAX import Facebook events
     */
    public function ajax_import_facebook() {
        check_ajax_referer('kh_import_facebook', 'nonce');

        $page_id = sanitize_text_field($_POST['facebook_page_id']);
        $app_id = sanitize_text_field($_POST['facebook_app_id']);
        $app_secret = sanitize_text_field($_POST['facebook_app_secret']);

        if (!$page_id || !$app_id || !$app_secret) {
            wp_send_json_error('Missing Facebook credentials');
        }

        $events = $this->fetch_facebook_events($page_id, $app_id, $app_secret);
        $imported = 0;

        foreach ($events as $fb_event) {
            try {
                $this->import_facebook_event($fb_event);
                $imported++;
            } catch (Exception $e) {
                error_log('Facebook import error: ' . $e->getMessage());
            }
        }

        wp_send_json_success(array('imported' => $imported));
    }

    /**
     * Fetch Facebook events
     */
    private function fetch_facebook_events($page_id, $app_id, $app_secret) {
        // Get access token
        $token_url = "https://graph.facebook.com/oauth/access_token?client_id={$app_id}&client_secret={$app_secret}&grant_type=client_credentials";
        $token_response = wp_remote_get($token_url);

        if (is_wp_error($token_response)) {
            throw new Exception('Failed to get Facebook access token');
        }

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        if (!isset($token_data['access_token'])) {
            throw new Exception('Invalid Facebook access token');
        }

        $access_token = $token_data['access_token'];

        // Fetch events
        $events_url = "https://graph.facebook.com/v18.0/{$page_id}/events?access_token={$access_token}&fields=id,name,description,start_time,end_time,place";
        $events_response = wp_remote_get($events_url);

        if (is_wp_error($events_response)) {
            throw new Exception('Failed to fetch Facebook events');
        }

        $events_data = json_decode(wp_remote_retrieve_body($events_response), true);
        return $events_data['data'] ?? array();
    }

    /**
     * Import Facebook event
     */
    private function import_facebook_event($fb_event) {
        $event_data = array(
            'post_title' => $fb_event['name'] ?? 'Untitled Event',
            'post_content' => $fb_event['description'] ?? '',
            'post_status' => 'publish',
            'post_type' => 'kh_event'
        );

        $event_id = wp_insert_post($event_data);

        if ($event_id && isset($fb_event['start_time'])) {
            $start_datetime = new DateTime($fb_event['start_time']);
            update_post_meta($event_id, '_kh_event_start_date', $start_datetime->format('Y-m-d'));
            update_post_meta($event_id, '_kh_event_start_time', $start_datetime->format('H:i'));
        }

        if ($event_id && isset($fb_event['end_time'])) {
            $end_datetime = new DateTime($fb_event['end_time']);
            update_post_meta($event_id, '_kh_event_end_date', $end_datetime->format('Y-m-d'));
            update_post_meta($event_id, '_kh_event_end_time', $end_datetime->format('H:i'));
        }

        if ($event_id && isset($fb_event['place']['name'])) {
            $location_id = $this->find_or_create_location($fb_event['place']['name']);
            if ($location_id) {
                update_post_meta($event_id, '_kh_event_location', array(
                    'id' => $location_id,
                    'name' => $fb_event['place']['name']
                ));
            }
        }
    }

    /**
     * Escape iCal text
     */
    private function escape_ical_text($text) {
        return str_replace(array('\\', ',', ';', "\n"), array('\\\\', '\\,', '\\;', '\\n'), $text);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'kh_event_page_kh-events-import-export') {
            return;
        }

        wp_enqueue_script('kh-import-export-admin', KH_EVENTS_URL . 'assets/js/import-export-admin.js', array('jquery'), KH_EVENTS_VERSION, true);
        wp_enqueue_style('kh-import-export-admin', KH_EVENTS_URL . 'assets/css/import-export-admin.css', array(), KH_EVENTS_VERSION);

        wp_localize_script('kh-import-export-admin', 'kh_import_export_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kh_import_export'),
            'strings' => array(
                'exporting' => __('Exporting events...', 'kh-events'),
                'importing' => __('Importing events...', 'kh-events'),
                'complete' => __('Complete!', 'kh-events'),
                'error' => __('Error occurred', 'kh-events')
            )
        ));
    }
}