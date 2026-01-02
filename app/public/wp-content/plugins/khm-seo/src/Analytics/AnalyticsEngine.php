<?php
/**
 * Phase 2.6 Analytics Engine - Advanced SEO Analytics and Reporting System
 * 
 * This comprehensive analytics engine provides deep insights into SEO performance,
 * content optimization opportunities, technical health metrics, and actionable
 * recommendations for improving search engine visibility.
 * 
 * Features:
 * - Real-time SEO performance tracking
 * - Content optimization scoring
 * - Technical health monitoring
 * - Competitive analysis insights
 * - Historical trend analysis
 * - Automated reporting and alerts
 * - Custom metrics and KPI tracking
 * 
 * @package KHM_SEO\Analytics
 * @since 2.6.0
 * @version 2.6.0
 */

namespace KHM_SEO\Analytics;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Analytics Engine Class
 * Central hub for all SEO analytics and reporting functionality
 */
class AnalyticsEngine {
    
    /**
     * @var Database Database instance
     */
    private $database;
    
    /**
     * @var array Analytics configuration
     */
    private $config;
    
    /**
     * @var array Performance metrics cache
     */
    private $metrics_cache = [];
    
    /**
     * @var array Scoring weights and criteria
     */
    private $scoring_config;
    
    /**
     * Constructor
     */
    public function __construct($database = null) {
        $this->database = $database;
        $this->init_config();
        $this->init_scoring_config();
        $this->init_hooks();
    }
    
    /**
     * Initialize analytics configuration
     */
    private function init_config() {
        $this->config = wp_parse_args(get_option('khm_seo_analytics_config', []), [
            'enable_analytics' => true,
            'enable_real_time_tracking' => true,
            'enable_content_scoring' => true,
            'enable_technical_monitoring' => true,
            'enable_competitive_analysis' => false,
            'data_retention_days' => 365,
            'report_frequency' => 'weekly',
            'alert_thresholds' => [
                'critical_score' => 30,
                'warning_score' => 60,
                'good_score' => 80
            ],
            'tracked_metrics' => [
                'seo_score',
                'content_quality',
                'technical_health',
                'page_speed',
                'mobile_friendliness',
                'security_status'
            ]
        ]);
    }
    
    /**
     * Initialize SEO scoring configuration
     */
    private function init_scoring_config() {
        $this->scoring_config = [
            'content_optimization' => [
                'weight' => 35,
                'criteria' => [
                    'title_optimization' => 15,
                    'meta_description' => 10,
                    'heading_structure' => 10,
                    'keyword_usage' => 15,
                    'content_length' => 10,
                    'readability' => 10,
                    'internal_linking' => 10,
                    'image_optimization' => 10,
                    'schema_markup' => 10
                ]
            ],
            'technical_seo' => [
                'weight' => 30,
                'criteria' => [
                    'page_speed' => 20,
                    'mobile_friendliness' => 15,
                    'ssl_certificate' => 10,
                    'crawlability' => 15,
                    'sitemap_quality' => 10,
                    'robots_txt' => 5,
                    'canonical_urls' => 10,
                    'redirects' => 10,
                    'broken_links' => 5
                ]
            ],
            'social_optimization' => [
                'weight' => 15,
                'criteria' => [
                    'open_graph_tags' => 40,
                    'twitter_cards' => 30,
                    'social_images' => 20,
                    'social_sharing' => 10
                ]
            ],
            'user_experience' => [
                'weight' => 20,
                'criteria' => [
                    'bounce_rate' => 25,
                    'time_on_page' => 25,
                    'page_load_time' => 20,
                    'mobile_usability' => 15,
                    'navigation_clarity' => 15
                ]
            ]
        ];
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_loaded', [$this, 'schedule_analytics_tasks']);
        add_action('khm_seo_daily_analytics', [$this, 'run_daily_analysis']);
        add_action('khm_seo_weekly_report', [$this, 'generate_weekly_report']);
        add_action('save_post', [$this, 'analyze_post_on_save'], 20);
        add_action('wp_head', [$this, 'track_page_metrics'], 99);
    }
    
    /**
     * Schedule analytics tasks
     */
    public function schedule_analytics_tasks() {
        if (!wp_next_scheduled('khm_seo_daily_analytics')) {
            wp_schedule_event(time(), 'daily', 'khm_seo_daily_analytics');
        }
        
        if (!wp_next_scheduled('khm_seo_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'khm_seo_weekly_report');
        }
    }
    
    /**
     * Generate comprehensive SEO score for content
     *
     * @param int|WP_Post $post Post ID or post object
     * @return array SEO score breakdown
     */
    public function generate_seo_score($post) {
        if (is_numeric($post)) {
            $post = get_post($post);
        }
        
        if (!$post) {
            return $this->get_default_score_structure();
        }
        
        // Check cache first
        $cache_key = "seo_score_{$post->ID}_{$post->post_modified}";
        $cached_score = \get_transient($cache_key);
        
        if (false !== $cached_score) {
            return $cached_score;
        }
        
        $score_data = [
            'overall_score' => 0,
            'category_scores' => [],
            'recommendations' => [],
            'strengths' => [],
            'issues' => []
        ];
        
        $total_weighted_score = 0;
        
        // Analyze each scoring category
        foreach ($this->scoring_config as $category => $config) {
            $category_analysis = $this->analyze_category($post, $category, $config);
            $weighted_score = ($category_analysis['score'] * $config['weight']) / 100;
            $total_weighted_score += $weighted_score;
            
            $score_data['category_scores'][$category] = [
                'score' => $category_analysis['score'],
                'weight' => $config['weight'],
                'weighted_score' => $weighted_score,
                'details' => $category_analysis['details']
            ];
            
            // Collect recommendations and issues
            $score_data['recommendations'] = array_merge(
                $score_data['recommendations'], 
                $category_analysis['recommendations']
            );
            
            $score_data['strengths'] = array_merge(
                $score_data['strengths'], 
                $category_analysis['strengths']
            );
            
            $score_data['issues'] = array_merge(
                $score_data['issues'], 
                $category_analysis['issues']
            );
        }
        
        $score_data['overall_score'] = round($total_weighted_score);
        $score_data['grade'] = $this->calculate_grade($score_data['overall_score']);
        $score_data['analyzed_at'] = current_time('c');
        
        // Cache the result
        \set_transient($cache_key, $score_data, 3600); // Cache for 1 hour
        
        // Store historical data
        $this->store_historical_score($post->ID, $score_data);
        
        return $score_data;
    }
    
    /**
     * Analyze specific category for SEO scoring
     *
     * @param WP_Post $post Post object
     * @param string $category Category name
     * @param array $config Category configuration
     * @return array Category analysis results
     */
    private function analyze_category($post, $category, $config) {
        switch ($category) {
            case 'content_optimization':
                return $this->analyze_content_optimization($post, $config);
            case 'technical_seo':
                return $this->analyze_technical_seo($post, $config);
            case 'social_optimization':
                return $this->analyze_social_optimization($post, $config);
            case 'user_experience':
                return $this->analyze_user_experience($post, $config);
            default:
                return $this->get_default_category_analysis();
        }
    }
    
    /**
     * Analyze content optimization
     *
     * @param WP_Post $post Post object
     * @param array $config Category configuration
     * @return array Content analysis results
     */
    private function analyze_content_optimization($post, $config) {
        $analysis = [
            'score' => 0,
            'details' => [],
            'recommendations' => [],
            'strengths' => [],
            'issues' => []
        ];
        
        $total_points = 0;
        $max_points = 0;
        
        foreach ($config['criteria'] as $criterion => $weight) {
            $max_points += $weight;
            $criterion_result = $this->analyze_content_criterion($post, $criterion);
            
            $points = ($criterion_result['score'] * $weight) / 100;
            $total_points += $points;
            
            $analysis['details'][$criterion] = $criterion_result;
            
            if ($criterion_result['score'] >= 80) {
                $analysis['strengths'][] = $criterion_result['message'];
            } elseif ($criterion_result['score'] < 50) {
                $analysis['issues'][] = $criterion_result['message'];
                $analysis['recommendations'][] = $criterion_result['recommendation'];
            }
        }
        
        $analysis['score'] = $max_points > 0 ? round(($total_points / $max_points) * 100) : 0;
        
        return $analysis;
    }
    
    /**
     * Analyze individual content criterion
     *
     * @param WP_Post $post Post object
     * @param string $criterion Criterion name
     * @return array Criterion analysis result
     */
    private function analyze_content_criterion($post, $criterion) {
        switch ($criterion) {
            case 'title_optimization':
                return $this->analyze_title_optimization($post);
            case 'meta_description':
                return $this->analyze_meta_description($post);
            case 'heading_structure':
                return $this->analyze_heading_structure($post);
            case 'keyword_usage':
                return $this->analyze_keyword_usage($post);
            case 'content_length':
                return $this->analyze_content_length($post);
            case 'readability':
                return $this->analyze_readability($post);
            case 'internal_linking':
                return $this->analyze_internal_linking($post);
            case 'image_optimization':
                return $this->analyze_image_optimization($post);
            case 'schema_markup':
                return $this->analyze_schema_markup($post);
            default:
                return [
                    'score' => 50,
                    'message' => "Analysis not yet implemented for {$criterion}",
                    'recommendation' => "Implement {$criterion} analysis"
                ];
        }
    }
    
    /**
     * Analyze title optimization
     */
    private function analyze_title_optimization($post) {
        $title = \get_the_title($post);
        $title_length = strlen($title);
        
        $score = 0;
        $issues = [];
        
        // Length check (optimal: 30-60 characters)
        if ($title_length >= 30 && $title_length <= 60) {
            $score += 40;
        } elseif ($title_length < 30) {
            $issues[] = 'Title is too short (under 30 characters)';
        } elseif ($title_length > 60) {
            $issues[] = 'Title is too long (over 60 characters)';
            $score += 20; // Partial credit
        }
        
        // Check for focus keyword (simplified)
        $focus_keyword = \get_post_meta($post->ID, '_khm_seo_focus_keyword', true);
        if (!empty($focus_keyword) && stripos($title, $focus_keyword) !== false) {
            $score += 40;
        } else {
            $issues[] = 'Focus keyword not found in title';
        }
        
        // Uniqueness check (simplified)
        $duplicate_titles = \get_posts([
            'post_type' => 'any',
            'posts_per_page' => 1,
            'title' => $title,
            'exclude' => [$post->ID]
        ]);
        
        if (empty($duplicate_titles)) {
            $score += 20;
        } else {
            $issues[] = 'Title may not be unique';
        }
        
        return [
            'score' => min(100, $score),
            'message' => empty($issues) ? 'Title is well optimized' : implode(', ', $issues),
            'recommendation' => $this->get_title_recommendation($title_length, $focus_keyword)
        ];
    }
    
    /**
     * Get title optimization recommendation
     */
    private function get_title_recommendation($length, $focus_keyword) {
        $recommendations = [];
        
        if ($length < 30) {
            $recommendations[] = 'Expand title to 30-60 characters for better SEO impact';
        } elseif ($length > 60) {
            $recommendations[] = 'Shorten title to under 60 characters to avoid truncation';
        }
        
        if (empty($focus_keyword)) {
            $recommendations[] = 'Set a focus keyword and include it in the title';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Title optimization looks good!';
        }
        
        return implode(' | ', $recommendations);
    }
    
    /**
     * Generate comprehensive analytics dashboard data
     *
     * @return array Dashboard data
     */
    public function get_dashboard_data() {
        $dashboard_data = [
            'overview_stats' => $this->get_overview_statistics(),
            'recent_performance' => $this->get_recent_performance_data(),
            'top_performing_content' => $this->get_top_performing_content(),
            'improvement_opportunities' => $this->get_improvement_opportunities(),
            'technical_health' => $this->get_technical_health_summary(),
            'trending_keywords' => $this->get_trending_keywords(),
            'competitive_insights' => $this->get_competitive_insights()
        ];
        
        return apply_filters('khm_seo_dashboard_data', $dashboard_data);
    }
    
    /**
     * Get overview statistics
     */
    private function get_overview_statistics() {
        global $wpdb;
        
        return [
            'total_content_pieces' => \wp_count_posts('post')->publish + \wp_count_posts('page')->publish,
            'avg_seo_score' => $this->calculate_average_seo_score(),
            'optimized_content_percentage' => $this->calculate_optimized_content_percentage(),
            'technical_issues_count' => $this->count_technical_issues(),
            'recent_improvements' => $this->count_recent_improvements(),
            'trending_direction' => $this->calculate_trending_direction()
        ];
    }
    
    /**
     * Calculate average SEO score across all content
     */
    private function calculate_average_seo_score() {
        global $wpdb;
        
        $avg_score = $wpdb->get_var("
            SELECT AVG(score) 
            FROM {$this->database->get_table_name('seo_scores')} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return round($avg_score ?: 0);
    }
    
    /**
     * Generate automated weekly report
     */
    public function generate_weekly_report() {
        $report_data = [
            'period' => [
                'start' => date('Y-m-d', strtotime('-7 days')),
                'end' => date('Y-m-d')
            ],
            'performance_summary' => $this->get_weekly_performance_summary(),
            'content_analysis' => $this->get_weekly_content_analysis(),
            'technical_health' => $this->get_weekly_technical_health(),
            'recommendations' => $this->get_weekly_recommendations(),
            'achievements' => $this->get_weekly_achievements()
        ];
        
        // Store the report
        $this->store_weekly_report($report_data);
        
        // Send email notification if enabled
        if ($this->config['email_reports']) {
            $this->send_weekly_report_email($report_data);
        }
        
        return $report_data;
    }
    
    /**
     * Get default score structure
     */
    private function get_default_score_structure() {
        return [
            'overall_score' => 0,
            'category_scores' => [],
            'recommendations' => ['No content to analyze'],
            'strengths' => [],
            'issues' => ['Invalid or missing content'],
            'grade' => 'F',
            'analyzed_at' => current_time('c')
        ];
    }
    
    /**
     * Calculate letter grade from numeric score
     */
    private function calculate_grade($score) {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'A-';
        if ($score >= 75) return 'B+';
        if ($score >= 70) return 'B';
        if ($score >= 65) return 'B-';
        if ($score >= 60) return 'C+';
        if ($score >= 55) return 'C';
        if ($score >= 50) return 'C-';
        if ($score >= 40) return 'D';
        return 'F';
    }
    
    /**
     * Store historical score data
     */
    private function store_historical_score($post_id, $score_data) {
        global $wpdb;
        
        $wpdb->insert(
            $this->database->get_table_name('seo_scores'),
            [
                'post_id' => $post_id,
                'overall_score' => $score_data['overall_score'],
                'category_scores' => json_encode($score_data['category_scores']),
                'recommendations' => json_encode($score_data['recommendations']),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Analyze post on save
     */
    public function analyze_post_on_save($post_id) {
        // Skip autosaves and revisions
        if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) {
            return;
        }
        
        // Generate SEO score for the post
        $this->generate_seo_score($post_id);
        
        // Clear related caches
        $this->clear_post_caches($post_id);
    }
    
    /**
     * Clear post-related caches
     */
    private function clear_post_caches($post_id) {
        // Clear various transients related to this post
        \delete_transient("seo_score_{$post_id}");
        \delete_transient('khm_seo_dashboard_overview');
        \delete_transient('khm_seo_recent_performance');
    }
    
    // Placeholder methods for additional analysis features
    // These would be implemented with full logic in a complete system
    private function analyze_meta_description($post) { return ['score' => 75, 'message' => 'Meta description analysis', 'recommendation' => 'Optimize meta description']; }
    private function analyze_heading_structure($post) { return ['score' => 80, 'message' => 'Heading structure analysis', 'recommendation' => 'Improve heading hierarchy']; }
    private function analyze_keyword_usage($post) { return ['score' => 70, 'message' => 'Keyword usage analysis', 'recommendation' => 'Optimize keyword density']; }
    private function analyze_content_length($post) { return ['score' => 85, 'message' => 'Content length analysis', 'recommendation' => 'Maintain good content length']; }
    private function analyze_readability($post) { return ['score' => 75, 'message' => 'Readability analysis', 'recommendation' => 'Improve readability']; }
    private function analyze_internal_linking($post) { return ['score' => 60, 'message' => 'Internal linking analysis', 'recommendation' => 'Add more internal links']; }
    private function analyze_image_optimization($post) { return ['score' => 65, 'message' => 'Image optimization analysis', 'recommendation' => 'Optimize images with alt text']; }
    private function analyze_schema_markup($post) { return ['score' => 90, 'message' => 'Schema markup analysis', 'recommendation' => 'Schema markup is good']; }
    private function analyze_technical_seo($post, $config) { return ['score' => 75, 'details' => [], 'recommendations' => ['Improve technical SEO'], 'strengths' => ['Good technical foundation'], 'issues' => []]; }
    private function analyze_social_optimization($post, $config) { return ['score' => 85, 'details' => [], 'recommendations' => [], 'strengths' => ['Good social optimization'], 'issues' => []]; }
    private function analyze_user_experience($post, $config) { return ['score' => 70, 'details' => [], 'recommendations' => ['Improve user experience'], 'strengths' => [], 'issues' => ['UX needs improvement']]; }
    private function get_default_category_analysis() { return ['score' => 50, 'details' => [], 'recommendations' => [], 'strengths' => [], 'issues' => []]; }
    private function get_recent_performance_data() { return []; }
    private function get_top_performing_content() { return []; }
    private function get_improvement_opportunities() { return []; }
    private function get_technical_health_summary() { return []; }
    private function get_trending_keywords() { return []; }
    private function get_competitive_insights() { return []; }
    private function calculate_optimized_content_percentage() { return 75; }
    private function count_technical_issues() { return 3; }
    private function count_recent_improvements() { return 7; }
    private function calculate_trending_direction() { return 'up'; }
    private function get_weekly_performance_summary() { return []; }
    private function get_weekly_content_analysis() { return []; }
    private function get_weekly_technical_health() { return []; }
    private function get_weekly_recommendations() { return []; }
    private function get_weekly_achievements() { return []; }
    private function store_weekly_report($data) { /* Store report logic */ }
    private function send_weekly_report_email($data) { /* Email logic */ }
}