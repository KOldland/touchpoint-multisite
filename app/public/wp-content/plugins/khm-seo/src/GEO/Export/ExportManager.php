<?php
/**
 * Export Manager
 *
 * Comprehensive data export functionality for GEO entities, series, and analytics
 *
 * @package KHM_SEO\GEO\Export
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Export;

use KHM_SEO\GEO\Entity\EntityManager;
use KHM_SEO\GEO\Series\SeriesManager;
use KHM_SEO\GEO\Measurement\MeasurementManager;
use Exception;
use SimpleXMLElement;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * ExportManager Class
 */
class ExportManager {

    /**
     * @var EntityManager Entity manager instance
     */
    private $entity_manager;

    /**
     * @var SeriesManager Series manager instance
     */
    private $series_manager;

    /**
     * @var MeasurementManager Measurement manager instance
     */
    private $measurement_manager;

    /**
     * @var array Export configuration
     */
    private $config = array();

    /**
     * @var array Supported export formats
     */
    private $supported_formats = array(
        'json' => 'JSON',
        'csv' => 'CSV',
        'xml' => 'XML',
        'xlsx' => 'Excel (XLSX)',
        'yaml' => 'YAML',
        'sql' => 'SQL Dump'
    );

    /**
     * Constructor - Initialize export functionality
     *
     * @param EntityManager $entity_manager
     * @param SeriesManager $series_manager
     * @param MeasurementManager $measurement_manager
     */
    public function __construct( EntityManager $entity_manager, SeriesManager $series_manager = null, MeasurementManager $measurement_manager = null ) {
        $this->entity_manager = $entity_manager;
        $this->series_manager = $series_manager;
        $this->measurement_manager = $measurement_manager;
        $this->init_hooks();
        $this->load_config();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin interface
        add_action( 'admin_menu', array( $this, 'add_export_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_export_admin_scripts' ) );

        // AJAX handlers
        add_action( 'wp_ajax_khm_geo_export_data', array( $this, 'ajax_export_data' ) );
        add_action( 'wp_ajax_khm_geo_schedule_export', array( $this, 'ajax_schedule_export' ) );
        add_action( 'wp_ajax_khm_geo_get_export_status', array( $this, 'ajax_get_export_status' ) );

        // Scheduled exports
        add_action( 'khm_geo_scheduled_export', array( $this, 'process_scheduled_export' ) );

        // Cleanup old exports
        add_action( 'khm_geo_cleanup_exports', array( $this, 'cleanup_old_exports' ) );
    }

    /**
     * Load export configuration
     */
    private function load_config() {
        $this->config = array(
            'enabled' => true,
            'max_export_rows' => 10000,
            'export_retention_days' => 30,
            'allow_scheduled_exports' => true,
            'default_format' => 'json',
            'include_metadata' => true,
            'compress_exports' => true,
            'export_path' => wp_upload_dir()['basedir'] . '/khm-geo-exports/',
            'batch_size' => 1000
        );

        // Allow override from options
        $saved_config = get_option( 'khm_geo_export_config', array() );
        $this->config = array_merge( $this->config, $saved_config );

        // Ensure export directory exists
        $this->ensure_export_directory();
    }

    /**
     * Ensure export directory exists
     */
    private function ensure_export_directory() {
        $export_dir = $this->config['export_path'];

        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );

            // Create .htaccess to protect exports
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents( $export_dir . '.htaccess', $htaccess_content );

            // Create index.php to prevent directory listing
            file_put_contents( $export_dir . 'index.php', '<?php // Silence is golden' );
        }
    }

    /**
     * Add export admin menu
     */
    public function add_export_admin_menu() {
        add_submenu_page(
            'khm-seo-entities',
            __( 'Data Export', 'khm-seo' ),
            __( 'Export', 'khm-seo' ),
            'manage_options',
            'khm-seo-export',
            array( $this, 'render_export_admin_page' )
        );
    }

    /**
     * Enqueue export admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_export_admin_scripts( $hook ) {
        if ( strpos( $hook, 'khm-seo-export' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'khm-geo-export-admin',
            KHM_SEO_PLUGIN_URL . 'assets/js/geo-export-admin.js',
            array( 'jquery', 'jquery-ui-progressbar' ),
            KHM_SEO_VERSION,
            true
        );

        wp_enqueue_style(
            'khm-geo-export-admin',
            KHM_SEO_PLUGIN_URL . 'assets/css/geo-export-admin.css',
            array(),
            KHM_SEO_VERSION
        );

        wp_localize_script( 'khm-geo-export-admin', 'khmGeoExport', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'khm_seo_ajax' ),
            'strings' => array(
                'exporting' => __( 'Exporting...', 'khm-seo' ),
                'preparing' => __( 'Preparing export...', 'khm-seo' ),
                'downloading' => __( 'Downloading...', 'khm-seo' ),
                'complete' => __( 'Export complete!', 'khm-seo' ),
                'error' => __( 'Export failed', 'khm-seo' ),
                'confirmLargeExport' => __( 'This export contains a large amount of data. Continue?', 'khm-seo' )
            )
        ) );
    }

    /**
     * Render export admin page
     */
    public function render_export_admin_page() {
        $recent_exports = $this->get_recent_exports();
        $scheduled_exports = $this->get_scheduled_exports();
        ?>
        <div class="wrap">
            <h1><?php _e( 'GEO Data Export', 'khm-seo' ); ?></h1>

            <div class="khm-export-container">
                <div class="khm-export-main">
                    <div class="khm-export-section">
                        <h2><?php _e( 'Quick Export', 'khm-seo' ); ?></h2>
                        <form id="khm-quick-export-form" class="khm-export-form">
                            <div class="khm-export-options">
                                <div class="khm-export-option-group">
                                    <h3><?php _e( 'Data Types', 'khm-seo' ); ?></h3>
                                    <label><input type="checkbox" name="data_types[]" value="entities" checked> <?php _e( 'Entities', 'khm-seo' ); ?></label>
                                    <label><input type="checkbox" name="data_types[]" value="series" <?php checked( $this->series_manager !== null ); ?>> <?php _e( 'Series', 'khm-seo' ); ?></label>
                                    <label><input type="checkbox" name="data_types[]" value="measurements" <?php checked( $this->measurement_manager !== null ); ?>> <?php _e( 'Analytics Data', 'khm-seo' ); ?></label>
                                    <label><input type="checkbox" name="data_types[]" value="relationships"> <?php _e( 'Entity Relationships', 'khm-seo' ); ?></label>
                                </div>

                                <div class="khm-export-option-group">
                                    <h3><?php _e( 'Export Format', 'khm-seo' ); ?></h3>
                                    <?php foreach ( $this->supported_formats as $format => $label ) : ?>
                                        <label>
                                            <input type="radio" name="format" value="<?php echo esc_attr( $format ); ?>" <?php checked( $format === $this->config['default_format'] ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div class="khm-export-option-group">
                                    <h3><?php _e( 'Options', 'khm-seo' ); ?></h3>
                                    <label><input type="checkbox" name="include_metadata" <?php checked( $this->config['include_metadata'] ); ?>> <?php _e( 'Include metadata', 'khm-seo' ); ?></label>
                                    <label><input type="checkbox" name="compress" <?php checked( $this->config['compress_exports'] ); ?>> <?php _e( 'Compress export file', 'khm-seo' ); ?></label>
                                    <label><input type="checkbox" name="anonymize" id="khm-anonymize-data"> <?php _e( 'Anonymize sensitive data', 'khm-seo' ); ?></label>
                                </div>

                                <div class="khm-export-option-group">
                                    <h3><?php _e( 'Date Range (Optional)', 'khm-seo' ); ?></h3>
                                    <label><?php _e( 'From:', 'khm-seo' ); ?> <input type="date" name="date_from"></label>
                                    <label><?php _e( 'To:', 'khm-seo' ); ?> <input type="date" name="date_to"></label>
                                </div>
                            </div>

                            <div class="khm-export-actions">
                                <button type="submit" class="button button-primary button-large">
                                    <?php _e( 'Start Export', 'khm-seo' ); ?>
                                </button>
                                <div id="khm-export-progress" style="display: none;">
                                    <div class="khm-progress-bar"></div>
                                    <div class="khm-progress-text"><?php _e( 'Preparing export...', 'khm-seo' ); ?></div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php if ( $this->config['allow_scheduled_exports'] ) : ?>
                    <div class="khm-export-section">
                        <h2><?php _e( 'Scheduled Exports', 'khm-seo' ); ?></h2>
                        <form id="khm-scheduled-export-form" class="khm-export-form">
                            <div class="khm-export-options">
                                <div class="khm-export-option-group">
                                    <h3><?php _e( 'Schedule', 'khm-seo' ); ?></h3>
                                    <select name="frequency">
                                        <option value="daily"><?php _e( 'Daily', 'khm-seo' ); ?></option>
                                        <option value="weekly"><?php _e( 'Weekly', 'khm-seo' ); ?></option>
                                        <option value="monthly"><?php _e( 'Monthly', 'khm-seo' ); ?></option>
                                    </select>
                                </div>

                                <div class="khm-export-option-group">
                                    <h3><?php _e( 'Recipients', 'khm-seo' ); ?></h3>
                                    <input type="email" name="email" placeholder="<?php esc_attr_e( 'Email address', 'khm-seo' ); ?>" multiple>
                                    <p class="description"><?php _e( 'Comma-separated email addresses', 'khm-seo' ); ?></p>
                                </div>
                            </div>

                            <div class="khm-export-actions">
                                <button type="submit" class="button button-secondary">
                                    <?php _e( 'Schedule Export', 'khm-seo' ); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="khm-export-sidebar">
                    <div class="khm-export-section">
                        <h3><?php _e( 'Recent Exports', 'khm-seo' ); ?></h3>
                        <?php if ( empty( $recent_exports ) ) : ?>
                            <p><?php _e( 'No recent exports found.', 'khm-seo' ); ?></p>
                        <?php else : ?>
                            <ul class="khm-export-list">
                                <?php foreach ( $recent_exports as $export ) : ?>
                                    <li class="khm-export-item">
                                        <div class="khm-export-info">
                                            <strong><?php echo esc_html( $export['filename'] ); ?></strong>
                                            <br>
                                            <small><?php echo esc_html( $export['created_at'] ); ?> • <?php echo esc_html( $export['file_size'] ); ?></small>
                                        </div>
                                        <div class="khm-export-actions">
                                            <a href="<?php echo esc_url( $export['download_url'] ); ?>" class="button button-small">
                                                <?php _e( 'Download', 'khm-seo' ); ?>
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $scheduled_exports ) ) : ?>
                    <div class="khm-export-section">
                        <h3><?php _e( 'Scheduled Exports', 'khm-seo' ); ?></h3>
                        <ul class="khm-export-list">
                            <?php foreach ( $scheduled_exports as $scheduled ) : ?>
                                <li class="khm-export-item">
                                    <div class="khm-export-info">
                                        <strong><?php echo esc_html( $scheduled['name'] ); ?></strong>
                                        <br>
                                        <small><?php echo esc_html( $scheduled['next_run'] ); ?> • <?php echo esc_html( $scheduled['frequency'] ); ?></small>
                                    </div>
                                    <div class="khm-export-actions">
                                        <button class="button button-small khm-cancel-scheduled" data-id="<?php echo esc_attr( $scheduled['id'] ); ?>">
                                            <?php _e( 'Cancel', 'khm-seo' ); ?>
                                        </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for data export
     */
    public function ajax_export_data() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $data_types = isset( $_POST['data_types'] ) ? (array) $_POST['data_types'] : array();
        $format = sanitize_text_field( $_POST['format'] ?? $this->config['default_format'] );
        $options = array(
            'include_metadata' => isset( $_POST['include_metadata'] ),
            'compress' => isset( $_POST['compress'] ),
            'anonymize' => isset( $_POST['anonymize'] ),
            'date_from' => sanitize_text_field( $_POST['date_from'] ?? '' ),
            'date_to' => sanitize_text_field( $_POST['date_to'] ?? '' )
        );

        if ( empty( $data_types ) ) {
            wp_send_json_error( 'No data types selected' );
        }

        if ( ! in_array( $format, array_keys( $this->supported_formats ) ) ) {
            wp_send_json_error( 'Invalid export format' );
        }

        try {
            $export_id = $this->start_export( $data_types, $format, $options );

            wp_send_json_success( array(
                'export_id' => $export_id,
                'message' => __( 'Export started successfully', 'khm-seo' )
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( 'Export failed: ' . $e->getMessage() );
        }
    }

    /**
     * Start data export process
     *
     * @param array $data_types Data types to export
     * @param string $format Export format
     * @param array $options Export options
     * @return string Export ID
     */
    public function start_export( $data_types, $format, $options = array() ) {
        $export_id = 'export_' . time() . '_' . wp_generate_password( 8, false );

        // Store export job in transient
        $export_job = array(
            'id' => $export_id,
            'data_types' => $data_types,
            'format' => $format,
            'options' => $options,
            'status' => 'preparing',
            'progress' => 0,
            'started_at' => time(),
            'user_id' => get_current_user_id()
        );

        set_transient( 'khm_geo_export_' . $export_id, $export_job, HOUR_IN_SECONDS );

        // Start background processing
        wp_schedule_single_event( time(), 'khm_geo_process_export', array( $export_id ) );

        return $export_id;
    }

    /**
     * Process export in background
     *
     * @param string $export_id Export ID
     */
    public function process_export( $export_id ) {
        $export_job = get_transient( 'khm_geo_export_' . $export_id );

        if ( ! $export_job ) {
            return;
        }

        try {
            // Update status
            $export_job['status'] = 'processing';
            set_transient( 'khm_geo_export_' . $export_id, $export_job, HOUR_IN_SECONDS );

            // Collect data
            $data = $this->collect_export_data( $export_job['data_types'], $export_job['options'] );

            // Update progress
            $export_job['progress'] = 50;
            set_transient( 'khm_geo_export_' . $export_id, $export_job, HOUR_IN_SECONDS );

            // Format data
            $formatted_data = $this->format_export_data( $data, $export_job['format'], $export_job['options'] );

            // Update progress
            $export_job['progress'] = 75;
            set_transient( 'khm_geo_export_' . $export_id, $export_job, HOUR_IN_SECONDS );

            // Save file
            $filename = $this->save_export_file( $formatted_data, $export_job['format'], $export_job['options'] );

            // Complete export
            $export_job['status'] = 'completed';
            $export_job['progress'] = 100;
            $export_job['filename'] = $filename;
            $export_job['file_path'] = $this->config['export_path'] . $filename;
            $export_job['file_size'] = filesize( $export_job['file_path'] );
            $export_job['completed_at'] = time();

            // Store in permanent storage
            $this->store_export_record( $export_job );

            // Clean up transient
            delete_transient( 'khm_geo_export_' . $export_id );

        } catch ( Exception $e ) {
            $export_job['status'] = 'failed';
            $export_job['error'] = $e->getMessage();
            set_transient( 'khm_geo_export_' . $export_id, $export_job, HOUR_IN_SECONDS );
        }
    }

    /**
     * Collect data for export
     *
     * @param array $data_types Data types to collect
     * @param array $options Export options
     * @return array Collected data
     */
    private function collect_export_data( $data_types, $options ) {
        $data = array(
            'metadata' => array(
                'export_date' => current_time( 'mysql' ),
                'plugin_version' => KHM_SEO_VERSION,
                'data_types' => $data_types,
                'options' => $options
            )
        );

        foreach ( $data_types as $data_type ) {
            switch ( $data_type ) {
                case 'entities':
                    $data['entities'] = $this->collect_entities_data( $options );
                    break;

                case 'series':
                    if ( $this->series_manager ) {
                        $data['series'] = $this->collect_series_data( $options );
                    }
                    break;

                case 'measurements':
                    if ( $this->measurement_manager ) {
                        $data['measurements'] = $this->collect_measurements_data( $options );
                    }
                    break;

                case 'relationships':
                    $data['relationships'] = $this->collect_relationships_data( $options );
                    break;
            }
        }

        return $data;
    }

    /**
     * Collect entities data
     *
     * @param array $options Export options
     * @return array Entities data
     */
    private function collect_entities_data( $options ) {
        $entities = $this->entity_manager->search_entities();

        if ( $options['anonymize'] ) {
            $entities = $this->anonymize_entities_data( $entities );
        }

        return array(
            'count' => count( $entities ),
            'entities' => $entities
        );
    }

    /**
     * Collect series data
     *
     * @param array $options Export options
     * @return array Series data
     */
    private function collect_series_data( $options ) {
        $series = $this->series_manager->get_all_series();
        $series_data = array();

        foreach ( $series as $series_item ) {
            $items = $this->series_manager->get_series_items( $series_item->id );
            $series_data[] = array(
                'series' => $series_item,
                'items' => $items
            );
        }

        return array(
            'count' => count( $series_data ),
            'series' => $series_data
        );
    }

    /**
     * Collect measurements data
     *
     * @param array $options Export options
     * @return array Measurements data
     */
    private function collect_measurements_data( $options ) {
        // This would integrate with the measurement manager
        // For now, return placeholder
        return array(
            'count' => 0,
            'measurements' => array()
        );
    }

    /**
     * Collect relationships data
     *
     * @param array $options Export options
     * @return array Relationships data
     */
    private function collect_relationships_data( $options ) {
        // This would collect entity relationships
        // For now, return placeholder
        return array(
            'count' => 0,
            'relationships' => array()
        );
    }

    /**
     * Anonymize entities data
     *
     * @param array $entities Entities data
     * @return array Anonymized data
     */
    private function anonymize_entities_data( $entities ) {
        // Remove or hash sensitive information
        foreach ( $entities as &$entity ) {
            // Remove internal IDs, timestamps, etc.
            unset( $entity['id'], $entity['created_at'], $entity['updated_at'] );
        }

        return $entities;
    }

    /**
     * Format export data
     *
     * @param array $data Raw data
     * @param string $format Export format
     * @param array $options Export options
     * @return string Formatted data
     */
    private function format_export_data( $data, $format, $options ) {
        switch ( $format ) {
            case 'json':
                return $this->format_as_json( $data, $options );

            case 'csv':
                return $this->format_as_csv( $data, $options );

            case 'xml':
                return $this->format_as_xml( $data, $options );

            case 'xlsx':
                return $this->format_as_xlsx( $data, $options );

            case 'yaml':
                return $this->format_as_yaml( $data, $options );

            case 'sql':
                return $this->format_as_sql( $data, $options );

            default:
                throw new Exception( 'Unsupported export format: ' . $format );
        }
    }

    /**
     * Format data as JSON
     *
     * @param array $data Data to format
     * @param array $options Export options
     * @return string JSON string
     */
    private function format_as_json( $data, $options ) {
        return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Format data as CSV
     *
     * @param array $data Data to format
     * @param array $options Export options
     * @return string CSV string
     */
    private function format_as_csv( $data, $options ) {
        // Flatten the data structure for CSV
        $csv_data = $this->flatten_for_csv( $data );

        if ( empty( $csv_data ) ) {
            return '';
        }

        $output = fopen( 'php://temp', 'r+' );

        // Write headers
        fputcsv( $output, array_keys( $csv_data[0] ) );

        // Write data
        foreach ( $csv_data as $row ) {
            fputcsv( $output, $row );
        }

        rewind( $output );
        $csv_content = stream_get_contents( $output );
        fclose( $output );

        return $csv_content;
    }

    /**
     * Format data as XML
     *
     * @param array $data Data to format
     * @param array $options Export options
     * @return string XML string
     */
    private function format_as_xml( $data, $options ) {
        $xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><geo-export></geo-export>' );

        $this->array_to_xml( $data, $xml );

        return $xml->asXML();
    }

    /**
     * Convert array to XML
     *
     * @param array $data Data array
     * @param SimpleXMLElement $xml XML element
     */
    private function array_to_xml( $data, &$xml ) {
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                if ( is_numeric( $key ) ) {
                    $key = 'item' . $key;
                }
                $subnode = $xml->addChild( $key );
                $this->array_to_xml( $value, $subnode );
            } else {
                $xml->addChild( $key, htmlspecialchars( $value ) );
            }
        }
    }

    /**
     * Format data as XLSX
     *
     * @param array $data Data to format
     * @param array $options Export options
     * @return string XLSX file content (base64 encoded)
     */
    private function format_as_xlsx( $data, $options ) {
        // For XLSX, we'd need a library like PhpSpreadsheet
        // For now, fall back to CSV
        return $this->format_as_csv( $data, $options );
    }

    /**
     * Format data as YAML
     *
     * @param array $data Data to format
     * @param array $options Export options
     * @return string YAML string
     */
    private function format_as_yaml( $data, $options ) {
        // For YAML, we'd need a YAML library
        // For now, fall back to JSON
        return $this->format_as_json( $data, $options );
    }

    /**
     * Format data as SQL
     *
     * @param array $data Data to format
     * @param array $options Export options
     * @return string SQL string
     */
    private function format_as_sql( $data, $options ) {
        $sql = array();
        $sql[] = "-- GEO Export SQL Dump";
        $sql[] = "-- Generated: " . current_time( 'mysql' );
        $sql[] = "";

        // Add INSERT statements for each data type
        if ( isset( $data['entities'] ) ) {
            $sql = array_merge( $sql, $this->generate_entity_sql( $data['entities']['entities'] ) );
        }

        if ( isset( $data['series'] ) ) {
            $sql = array_merge( $sql, $this->generate_series_sql( $data['series']['series'] ) );
        }

        return implode( "\n", $sql );
    }

    /**
     * Generate SQL for entities
     *
     * @param array $entities Entities data
     * @return array SQL statements
     */
    private function generate_entity_sql( $entities ) {
        global $wpdb;

        $sql = array();
        $sql[] = "-- Entities";

        foreach ( $entities as $entity ) {
            $values = array(
                $entity['canonical_url'] ?? '',
                $entity['entity_type'] ?? '',
                json_encode( $entity['data'] ?? array() ),
                $entity['status'] ?? 'active'
            );

            $sql[] = $wpdb->prepare(
                "INSERT INTO `{$wpdb->prefix}khm_geo_entities` (`canonical_url`, `entity_type`, `data`, `status`) VALUES (%s, %s, %s, %s);",
                $values[0],
                $values[1],
                $values[2],
                $values[3]
            );
        }

        $sql[] = "";
        return $sql;
    }

    /**
     * Generate SQL for series
     *
     * @param array $series Series data
     * @return array SQL statements
     */
    private function generate_series_sql( $series ) {
        global $wpdb;

        $sql = array();
        $sql[] = "-- Series";

        foreach ( $series as $series_item ) {
            $series_data = $series_item['series'];
            $values = array(
                $series_data->title ?? '',
                $series_data->description ?? '',
                $series_data->type ?? 'sequential',
                $series_data->auto_progression ?? 1
            );

            $sql[] = $wpdb->prepare(
                "INSERT INTO `{$wpdb->prefix}khm_geo_series` (`title`, `description`, `type`, `auto_progression`) VALUES (%s, %s, %s, %d);",
                $values[0],
                $values[1],
                $values[2],
                $values[3]
            );
        }

        $sql[] = "";
        return $sql;
    }

    /**
     * Flatten data structure for CSV export
     *
     * @param array $data Data to flatten
     * @return array Flattened data
     */
    private function flatten_for_csv( $data ) {
        $flattened = array();

        // Flatten entities
        if ( isset( $data['entities']['entities'] ) ) {
            foreach ( $data['entities']['entities'] as $entity ) {
                $flattened[] = array(
                    'type' => 'entity',
                    'canonical_url' => $entity['canonical_url'] ?? '',
                    'entity_type' => $entity['entity_type'] ?? '',
                    'status' => $entity['status'] ?? '',
                    'data' => json_encode( $entity['data'] ?? array() )
                );
            }
        }

        // Flatten series
        if ( isset( $data['series']['series'] ) ) {
            foreach ( $data['series']['series'] as $series_item ) {
                $series = $series_item['series'];
                $flattened[] = array(
                    'type' => 'series',
                    'title' => $series->title ?? '',
                    'description' => $series->description ?? '',
                    'series_type' => $series->type ?? '',
                    'auto_progression' => $series->auto_progression ?? 0
                );
            }
        }

        return $flattened;
    }

    /**
     * Save export file
     *
     * @param string $content File content
     * @param string $format Export format
     * @param array $options Export options
     * @return string Filename
     */
    private function save_export_file( $content, $format, $options ) {
        $timestamp = current_time( 'Y-m-d_H-i-s' );
        $filename = sprintf( 'khm-geo-export-%s.%s', $timestamp, $format );

        if ( $options['compress'] && in_array( $format, array( 'json', 'xml', 'yaml' ) ) ) {
            $filename .= '.gz';
            $content = gzencode( $content );
        }

        $file_path = $this->config['export_path'] . $filename;

        file_put_contents( $file_path, $content );

        return $filename;
    }

    /**
     * Store export record
     *
     * @param array $export_job Export job data
     */
    private function store_export_record( $export_job ) {
        $exports = get_option( 'khm_geo_exports', array() );

        // Keep only last 50 exports
        if ( count( $exports ) >= 50 ) {
            array_shift( $exports );
        }

        $exports[] = array(
            'id' => $export_job['id'],
            'filename' => $export_job['filename'],
            'format' => $export_job['format'],
            'data_types' => $export_job['data_types'],
            'file_size' => $export_job['file_size'],
            'created_at' => date( 'Y-m-d H:i:s', $export_job['started_at'] ),
            'download_url' => add_query_arg( array(
                'khm_geo_download' => $export_job['id'],
                'nonce' => wp_create_nonce( 'khm_geo_download_' . $export_job['id'] )
            ), admin_url( 'admin.php' ) )
        );

        update_option( 'khm_geo_exports', $exports );
    }

    /**
     * Get recent exports
     *
     * @return array Recent exports
     */
    public function get_recent_exports() {
        return get_option( 'khm_geo_exports', array() );
    }

    /**
     * Get scheduled exports
     *
     * @return array Scheduled exports
     */
    public function get_scheduled_exports() {
        // This would retrieve scheduled export jobs
        // For now, return empty array
        return array();
    }

    /**
     * AJAX handler for scheduling exports
     */
    public function ajax_schedule_export() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        // Implementation for scheduling exports
        wp_send_json_success( array(
            'message' => __( 'Export scheduling not yet implemented', 'khm-seo' )
        ) );
    }

    /**
     * AJAX handler for getting export status
     */
    public function ajax_get_export_status() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        $export_id = sanitize_text_field( $_POST['export_id'] ?? '' );

        if ( empty( $export_id ) ) {
            wp_send_json_error( 'Invalid export ID' );
        }

        $export_job = get_transient( 'khm_geo_export_' . $export_id );

        if ( ! $export_job ) {
            wp_send_json_error( 'Export not found' );
        }

        wp_send_json_success( array(
            'status' => $export_job['status'],
            'progress' => $export_job['progress'],
            'filename' => $export_job['filename'] ?? null
        ) );
    }

    /**
     * Process scheduled export
     *
     * @param array $args Scheduled export arguments
     */
    public function process_scheduled_export( $args ) {
        // Implementation for processing scheduled exports
    }

    /**
     * Cleanup old exports
     */
    public function cleanup_old_exports() {
        $exports = $this->get_recent_exports();
        $retention_days = $this->config['export_retention_days'];
        $cutoff_time = time() - ( $retention_days * DAY_IN_SECONDS );

        $updated_exports = array();

        foreach ( $exports as $export ) {
            $export_time = strtotime( $export['created_at'] );

            if ( $export_time > $cutoff_time ) {
                $updated_exports[] = $export;
            } else {
                // Delete old file
                $file_path = $this->config['export_path'] . $export['filename'];
                if ( file_exists( $file_path ) ) {
                    unlink( $file_path );
                }
            }
        }

        update_option( 'khm_geo_exports', $updated_exports );
    }

    /**
     * Handle file download
     *
     * @param string $export_id Export ID
     */
    public function handle_download( $export_id ) {
        $exports = $this->get_recent_exports();

        foreach ( $exports as $export ) {
            if ( $export['id'] === $export_id ) {
                $file_path = $this->config['export_path'] . $export['filename'];

                if ( file_exists( $file_path ) ) {
                    header( 'Content-Type: application/octet-stream' );
                    header( 'Content-Disposition: attachment; filename="' . $export['filename'] . '"' );
                    header( 'Content-Length: ' . filesize( $file_path ) );
                    readfile( $file_path );
                    exit;
                }
            }
        }

        wp_die( __( 'Export file not found', 'khm-seo' ) );
    }

    /**
     * Get supported export formats
     *
     * @return array Supported formats
     */
    public function get_supported_formats() {
        return $this->supported_formats;
    }

    /**
     * Get export configuration
     *
     * @param string $key Configuration key
     * @return mixed Configuration value
     */
    public function get_config( $key = null ) {
        if ( $key ) {
            return $this->config[ $key ] ?? null;
        }

        return $this->config;
    }
}