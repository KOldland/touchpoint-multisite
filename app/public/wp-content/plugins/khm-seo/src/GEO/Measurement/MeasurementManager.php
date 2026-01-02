<?php
/**
 * Measurement & Tracking Manager
 *
 * Tracks AnswerCard performance metrics, SEO data, and user engagement
 * Provides analytics and insights for content optimization
 *
 * @package KHM_SEO\GEO\Measurement
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Measurement;

use KHM_SEO\GEO\Entity\EntityManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * MeasurementManager Class
 */
class MeasurementManager {

    /**
     * @var EntityManager Entity manager instance
     */
    private $entity_manager;

    /**
     * @var array Measurement configuration
     */
    private $config = array();

    /**
     * Constructor - Initialize measurement system
     *
     * @param EntityManager $entity_manager
     */
    public function __construct( EntityManager $entity_manager ) {
        $this->entity_manager = $entity_manager;
        $this->init_hooks();
        $this->load_config();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Content tracking hooks
        add_action( 'wp_head', array( $this, 'inject_tracking_scripts' ) );
        add_action( 'wp_ajax_khm_geo_track_engagement', array( $this, 'ajax_track_engagement' ) );
        add_action( 'wp_ajax_nopriv_khm_geo_track_engagement', array( $this, 'ajax_track_engagement' ) );

        // Post/page view tracking
        add_action( 'wp', array( $this, 'track_page_view' ) );

        // AnswerCard interaction tracking
        add_action( 'khm_answer_card_displayed', array( $this, 'track_answer_card_display' ), 10, 2 );
        add_action( 'khm_answer_card_expanded', array( $this, 'track_answer_card_expansion' ), 10, 2 );
        add_action( 'khm_answer_card_citation_clicked', array( $this, 'track_citation_click' ), 10, 3 );

        // SEO data collection
        add_action( 'khm_seo_collect_search_data', array( $this, 'collect_search_analytics' ) );

        // Periodic analytics processing
        add_action( 'khm_geo_daily_analytics', array( $this, 'process_daily_analytics' ) );

        // Admin hooks
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_khm_geo_get_analytics', array( $this, 'ajax_get_analytics' ) );
    }

    /**
     * Load measurement configuration
     */
    private function load_config() {
        $this->config = array(
            'tracking_enabled' => true,
            'metrics_retention_days' => 365,
            'real_time_tracking' => true,
            'anonymize_ips' => true,
            'track_search_console' => true,
            'track_google_analytics' => true,
            'performance_thresholds' => array(
                'min_views_for_analysis' => 100,
                'good_engagement_rate' => 0.15,
                'excellent_ctr' => 0.05
            )
        );

        // Allow override from options
        $saved_config = get_option( 'khm_geo_measurement_config', array() );
        $this->config = array_merge( $this->config, $saved_config );
    }

    /**
     * Inject tracking scripts in page head
     */
    public function inject_tracking_scripts() {
        if ( ! $this->config['tracking_enabled'] || ! $this->should_track_current_page() ) {
            return;
        }

        $tracking_data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'khm_geo_tracking' ),
            'post_id' => get_the_ID(),
            'user_id' => get_current_user_id(),
            'page_url' => $this->get_current_page_url(),
            'real_time' => $this->config['real_time_tracking']
        );

        ?>
        <script type="text/javascript">
        window.KHMGeoTracking = <?php echo json_encode( $tracking_data ); ?>;

        // Track page view
        if ( window.KHMGeoTracking.real_time ) {
            jQuery(document).ready(function($) {
                $.post(window.KHMGeoTracking.ajax_url, {
                    action: 'khm_geo_track_engagement',
                    nonce: window.KHMGeoTracking.nonce,
                    type: 'page_view',
                    post_id: window.KHMGeoTracking.post_id,
                    data: {
                        url: window.KHMGeoTracking.page_url,
                        referrer: document.referrer,
                        user_agent: navigator.userAgent
                    }
                });
            });
        }

        // Track AnswerCard interactions
        jQuery(document).on('click', '.khm-answer-card .khm-expand-toggle', function() {
            var $card = jQuery(this).closest('.khm-answer-card');
            var cardId = $card.data('card-id');

            jQuery.post(window.KHMGeoTracking.ajax_url, {
                action: 'khm_geo_track_engagement',
                nonce: window.KHMGeoTracking.nonce,
                type: 'answer_card_expansion',
                post_id: window.KHMGeoTracking.post_id,
                data: {
                    card_id: cardId,
                    element: 'expand_toggle'
                }
            });
        });

        // Track citation clicks
        jQuery(document).on('click', '.khm-answer-card .khm-citation-link', function() {
            var $link = jQuery(this);
            var citationUrl = $link.attr('href');
            var cardId = $link.closest('.khm-answer-card').data('card-id');

            jQuery.post(window.KHMGeoTracking.ajax_url, {
                action: 'khm_geo_track_engagement',
                nonce: window.KHMGeoTracking.nonce,
                type: 'citation_click',
                post_id: window.KHMGeoTracking.post_id,
                data: {
                    card_id: cardId,
                    citation_url: citationUrl
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Track page view (server-side fallback)
     */
    public function track_page_view() {
        if ( ! $this->config['tracking_enabled'] || is_admin() || wp_doing_ajax() ) {
            return;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }

        $this->record_metric( 'page_view', array(
            'post_id' => $post_id,
            'url' => $this->get_current_page_url(),
            'referrer' => wp_get_referer(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->anonymize_ip( $_SERVER['REMOTE_ADDR'] ?? '' ),
            'timestamp' => current_time( 'mysql' )
        ));
    }

    /**
     * AJAX handler for engagement tracking
     */
    public function ajax_track_engagement() {
        check_ajax_referer( 'khm_geo_tracking', 'nonce' );

        $type = sanitize_text_field( $_POST['type'] ?? '' );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        $data = $_POST['data'] ?? array();

        if ( ! $type || ! $post_id ) {
            wp_send_json_error( 'Missing required parameters' );
        }

        // Sanitize data
        $sanitized_data = array();
        foreach ( $data as $key => $value ) {
            $sanitized_data[ sanitize_key( $key ) ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
        }

        $metric_data = array_merge( $sanitized_data, array(
            'post_id' => $post_id,
            'user_id' => get_current_user_id(),
            'session_id' => $this->get_session_id(),
            'timestamp' => current_time( 'mysql' )
        ));

        $result = $this->record_metric( $type, $metric_data );

        if ( $result ) {
            wp_send_json_success( array( 'recorded' => true ) );
        } else {
            wp_send_json_error( 'Failed to record metric' );
        }
    }

    /**
     * Track AnswerCard display
     *
     * @param int $post_id Post ID
     * @param array $card_data Card data
     */
    public function track_answer_card_display( $post_id, $card_data ) {
        $this->record_metric( 'answer_card_display', array(
            'post_id' => $post_id,
            'card_id' => $card_data['id'] ?? '',
            'question' => $card_data['question'] ?? '',
            'entity_id' => $card_data['entity_id'] ?? 0,
            'timestamp' => current_time( 'mysql' )
        ));
    }

    /**
     * Track AnswerCard expansion
     *
     * @param int $post_id Post ID
     * @param array $card_data Card data
     */
    public function track_answer_card_expansion( $post_id, $card_data ) {
        $this->record_metric( 'answer_card_expansion', array(
            'post_id' => $post_id,
            'card_id' => $card_data['id'] ?? '',
            'question' => $card_data['question'] ?? '',
            'timestamp' => current_time( 'mysql' )
        ));
    }

    /**
     * Track citation clicks
     *
     * @param int $post_id Post ID
     * @param array $card_data Card data
     * @param string $citation_url Citation URL
     */
    public function track_citation_click( $post_id, $card_data, $citation_url ) {
        $this->record_metric( 'citation_click', array(
            'post_id' => $post_id,
            'card_id' => $card_data['id'] ?? '',
            'citation_url' => $citation_url,
            'timestamp' => current_time( 'mysql' )
        ));
    }

    /**
     * Collect search analytics data
     *
     * @param array $search_data Search data from external sources
     */
    public function collect_search_analytics( $search_data ) {
        global $wpdb;

        foreach ( $search_data as $data ) {
            $wpdb->insert(
                $wpdb->prefix . 'geo_search_analytics',
                array(
                    'entity_id' => $data['entity_id'] ?? 0,
                    'keyword' => $data['keyword'] ?? '',
                    'position' => $data['position'] ?? 0,
                    'impressions' => $data['impressions'] ?? 0,
                    'clicks' => $data['clicks'] ?? 0,
                    'ctr' => $data['ctr'] ?? 0,
                    'date_collected' => current_time( 'mysql' )
                ),
                array( '%d', '%s', '%d', '%d', '%d', '%f', '%s' )
            );
        }
    }

    /**
     * Process daily analytics
     */
    public function process_daily_analytics() {
        // Calculate engagement rates
        $this->calculate_engagement_rates();

        // Update performance scores
        $this->update_performance_scores();

        // Clean up old data
        $this->cleanup_old_data();

        // Generate insights
        $this->generate_insights();
    }

    /**
     * Calculate engagement rates for posts
     */
    private function calculate_engagement_rates() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'geo_metrics';

        // Calculate engagement rates for the last 30 days
        $sql = $wpdb->prepare(
            "SELECT
                post_id,
                COUNT(CASE WHEN metric_type = 'page_view' THEN 1 END) as page_views,
                COUNT(CASE WHEN metric_type = 'answer_card_expansion' THEN 1 END) as expansions,
                COUNT(CASE WHEN metric_type = 'citation_click' THEN 1 END) as citation_clicks
            FROM {$table_name}
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY post_id
            HAVING page_views > %d",
            $this->config['performance_thresholds']['min_views_for_analysis']
        );

        $results = $wpdb->get_results( $sql );

        foreach ( $results as $result ) {
            $engagement_rate = $result->expansions / $result->page_views;
            $citation_rate = $result->citation_clicks / $result->page_views;

            // Store calculated metrics
            update_post_meta( $result->post_id, '_khm_geo_engagement_rate', $engagement_rate );
            update_post_meta( $result->post_id, '_khm_geo_citation_rate', $citation_rate );
        }
    }

    /**
     * Update performance scores
     */
    private function update_performance_scores() {
        global $wpdb;

        $posts = get_posts( array(
            'post_type' => 'any',
            'meta_key' => '_khm_geo_engagement_rate',
            'posts_per_page' => -1
        ));

        foreach ( $posts as $post ) {
            $engagement_rate = get_post_meta( $post->ID, '_khm_geo_engagement_rate', true );
            $citation_rate = get_post_meta( $post->ID, '_khm_geo_citation_rate', true );

            // Calculate overall performance score (0-100)
            $performance_score = $this->calculate_performance_score( $engagement_rate, $citation_rate );

            update_post_meta( $post->ID, '_khm_geo_performance_score', $performance_score );
        }
    }

    /**
     * Calculate performance score
     *
     * @param float $engagement_rate
     * @param float $citation_rate
     * @return int Performance score (0-100)
     */
    private function calculate_performance_score( $engagement_rate, $citation_rate ) {
        $score = 0;

        // Engagement rate scoring (40% weight)
        if ( $engagement_rate >= $this->config['performance_thresholds']['excellent_ctr'] ) {
            $score += 40;
        } elseif ( $engagement_rate >= $this->config['performance_thresholds']['good_engagement_rate'] ) {
            $score += 25;
        } elseif ( $engagement_rate > 0 ) {
            $score += 10;
        }

        // Citation rate scoring (30% weight)
        if ( $citation_rate >= 0.1 ) {
            $score += 30;
        } elseif ( $citation_rate >= 0.05 ) {
            $score += 20;
        } elseif ( $citation_rate > 0 ) {
            $score += 10;
        }

        // Content quality scoring (30% weight) - based on validation scores
        $quality_score = get_post_meta( get_the_ID(), '_khm_geo_quality_score', true );
        if ( $quality_score ) {
            $score += min( 30, $quality_score * 0.3 );
        }

        return min( 100, $score );
    }

    /**
     * Clean up old metric data
     */
    private function cleanup_old_data() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'geo_metrics';

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $this->config['metrics_retention_days']
        ));
    }

    /**
     * Generate insights from analytics data
     */
    private function generate_insights() {
        // This would analyze trends and generate actionable insights
        // For now, just log that insights were generated
        error_log( 'GEO Analytics: Daily insights generated at ' . current_time( 'mysql' ) );
    }

    /**
     * Record a metric
     *
     * @param string $type Metric type
     * @param array $data Metric data
     * @return bool Success
     */
    private function record_metric( $type, $data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'geo_metrics';

        $result = $wpdb->insert(
            $table_name,
            array(
                'metric_type' => $type,
                'post_id' => $data['post_id'] ?? 0,
                'user_id' => $data['user_id'] ?? 0,
                'session_id' => $data['session_id'] ?? '',
                'metric_data' => json_encode( $data ),
                'timestamp' => $data['timestamp'] ?? current_time( 'mysql' )
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%s' )
        );

        return $result !== false;
    }

    /**
     * Get analytics data
     *
     * @param array $args Query arguments
     * @return array Analytics data
     */
    public function get_analytics( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'post_id' => null,
            'metric_type' => null,
            'date_from' => null,
            'date_to' => null,
            'limit' => 100
        );

        $args = array_merge( $defaults, $args );

        $where = array( '1=1' );
        $where_values = array();

        if ( $args['post_id'] ) {
            $where[] = 'post_id = %d';
            $where_values[] = $args['post_id'];
        }

        if ( $args['metric_type'] ) {
            $where[] = 'metric_type = %s';
            $where_values[] = $args['metric_type'];
        }

        if ( $args['date_from'] ) {
            $where[] = 'timestamp >= %s';
            $where_values[] = $args['date_from'];
        }

        if ( $args['date_to'] ) {
            $where[] = 'timestamp <= %s';
            $where_values[] = $args['date_to'];
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}geo_metrics
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY timestamp DESC
            LIMIT %d",
            array_merge( $where_values, array( $args['limit'] ) )
        );

        return $wpdb->get_results( $sql );
    }

    /**
     * Get performance summary for a post
     *
     * @param int $post_id Post ID
     * @return array Performance summary
     */
    public function get_performance_summary( $post_id ) {
        $engagement_rate = get_post_meta( $post_id, '_khm_geo_engagement_rate', true );
        $citation_rate = get_post_meta( $post_id, '_khm_geo_citation_rate', true );
        $performance_score = get_post_meta( $post_id, '_khm_geo_performance_score', true );

        // Get recent metrics
        $recent_metrics = $this->get_analytics( array(
            'post_id' => $post_id,
            'date_from' => date( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
            'limit' => 1000
        ));

        $summary = array(
            'post_id' => $post_id,
            'engagement_rate' => floatval( $engagement_rate ),
            'citation_rate' => floatval( $citation_rate ),
            'performance_score' => intval( $performance_score ),
            'total_views' => 0,
            'total_expansions' => 0,
            'total_citation_clicks' => 0,
            'date_range' => '30 days'
        );

        foreach ( $recent_metrics as $metric ) {
            switch ( $metric->metric_type ) {
                case 'page_view':
                    $summary['total_views']++;
                    break;
                case 'answer_card_expansion':
                    $summary['total_expansions']++;
                    break;
                case 'citation_click':
                    $summary['total_citation_clicks']++;
                    break;
            }
        }

        return $summary;
    }

    /**
     * AJAX handler for analytics data
     */
    public function ajax_get_analytics() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        $metric_type = sanitize_text_field( $_POST['metric_type'] ?? '' );

        if ( $post_id ) {
            $data = $this->get_performance_summary( $post_id );
        } else {
            $data = $this->get_analytics( array(
                'metric_type' => $metric_type ?: null,
                'limit' => 500
            ));
        }

        wp_send_json_success( $data );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'khm-seo' ) === false && $hook !== 'post.php' ) {
            return;
        }

        wp_enqueue_script(
            'khm-geo-analytics',
            plugins_url( 'assets/js/geo-analytics.js', KHM_SEO_PLUGIN_FILE ),
            array( 'jquery' ),
            KHM_SEO_VERSION,
            true
        );

        wp_localize_script( 'khm-geo-analytics', 'KHMGeoAnalytics', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'khm_seo_ajax' )
        ));
    }

    /**
     * Check if current page should be tracked
     *
     * @return bool
     */
    private function should_track_current_page() {
        if ( is_admin() || is_preview() || is_feed() || is_robots() || is_trackback() ) {
            return false;
        }

        return true;
    }

    /**
     * Get current page URL
     *
     * @return string
     */
    private function get_current_page_url() {
        if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }

        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Anonymize IP address
     *
     * @param string $ip IP address
     * @return string Anonymized IP
     */
    private function anonymize_ip( $ip ) {
        if ( ! $this->config['anonymize_ips'] ) {
            return $ip;
        }

        // Anonymize IPv4
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return preg_replace( '/\.\d+$/', '.0', $ip );
        }

        // Anonymize IPv6
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            return preg_replace( '/:[^:]+$/', ':0000', $ip );
        }

        return $ip;
    }

    /**
     * Get session ID
     *
     * @return string
     */
    private function get_session_id() {
        if ( ! session_id() ) {
            session_start();
        }

        return session_id();
    }

    /**
     * Get measurement configuration
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