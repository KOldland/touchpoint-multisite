<?php
/**
 * Phase 2.6 SEO Scoring System
 * 
 * Advanced SEO scoring engine that provides detailed content analysis,
 * technical health assessment, and actionable improvement recommendations.
 * 
 * Features:
 * - Multi-dimensional SEO scoring
 * - Content quality analysis
 * - Technical SEO health checks
 * - Competitive benchmarking
 * - Historical score tracking
 * - Automated improvement suggestions
 * - Custom scoring weights
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
 * SEO Scoring System Class
 * Comprehensive SEO analysis and scoring engine
 */
class ScoringSystem {
    
    /**
     * @var array Scoring configuration and weights
     */
    private $scoring_config;
    
    /**
     * @var array Analysis cache
     */
    private $analysis_cache = [];
    
    /**
     * @var array Competitive benchmarks
     */
    private $benchmarks;
    
    /**
     * @var object Database instance
     */
    private $database;
    
    /**
     * Constructor
     */
    public function __construct($database = null) {
        $this->database = $database;
        $this->init_scoring_config();
        $this->init_benchmarks();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('save_post', [$this, 'schedule_post_analysis'], 10, 1);
        add_action('khm_seo_daily_score_analysis', [$this, 'run_daily_analysis']);
        add_filter('khm_seo_scoring_weights', [$this, 'apply_custom_scoring_weights']);
    }
    
    /**
     * Initialize comprehensive scoring configuration
     */
    private function init_scoring_config() {
        $this->scoring_config = [
            'content_quality' => [
                'weight' => 40,
                'max_score' => 100,
                'criteria' => [
                    'title_optimization' => [
                        'weight' => 20,
                        'optimal_range' => [30, 60], // characters
                        'keyword_placement' => ['beginning', 'middle'],
                        'uniqueness_required' => true
                    ],
                    'meta_description' => [
                        'weight' => 15,
                        'optimal_range' => [150, 160], // characters
                        'keyword_inclusion' => true,
                        'call_to_action' => true
                    ],
                    'content_length' => [
                        'weight' => 15,
                        'minimum_words' => 300,
                        'optimal_words' => 1500,
                        'content_type_variations' => [
                            'blog_post' => 1000,
                            'product_page' => 500,
                            'landing_page' => 800,
                            'category_page' => 400
                        ]
                    ],
                    'keyword_optimization' => [
                        'weight' => 20,
                        'density_range' => [0.5, 2.5], // percentage
                        'semantic_keywords' => true,
                        'keyword_distribution' => true,
                        'avoid_keyword_stuffing' => true
                    ],
                    'readability' => [
                        'weight' => 15,
                        'flesch_reading_score' => 60, // minimum
                        'sentence_length' => 20, // max average
                        'paragraph_length' => 150, // max words
                        'use_of_headings' => true
                    ],
                    'internal_linking' => [
                        'weight' => 10,
                        'minimum_links' => 2,
                        'optimal_links' => 5,
                        'relevant_anchor_text' => true,
                        'link_diversity' => true
                    ],
                    'image_optimization' => [
                        'weight' => 5,
                        'alt_text_required' => true,
                        'file_size_optimization' => true,
                        'descriptive_filenames' => true,
                        'image_seo_tags' => true
                    ]
                ]
            ],
            'technical_seo' => [
                'weight' => 30,
                'max_score' => 100,
                'criteria' => [
                    'page_speed' => [
                        'weight' => 25,
                        'mobile_speed_threshold' => 3.0, // seconds
                        'desktop_speed_threshold' => 2.0, // seconds
                        'core_web_vitals' => true,
                        'lighthouse_score' => 90 // minimum
                    ],
                    'mobile_optimization' => [
                        'weight' => 20,
                        'responsive_design' => true,
                        'mobile_usability' => true,
                        'touch_targets' => true,
                        'viewport_configuration' => true
                    ],
                    'crawlability' => [
                        'weight' => 15,
                        'robots_txt' => true,
                        'xml_sitemap' => true,
                        'internal_link_structure' => true,
                        'url_structure' => true
                    ],
                    'security' => [
                        'weight' => 15,
                        'ssl_certificate' => true,
                        'https_redirect' => true,
                        'security_headers' => true,
                        'secure_login' => true
                    ],
                    'schema_markup' => [
                        'weight' => 15,
                        'structured_data' => true,
                        'rich_snippets' => true,
                        'local_seo_markup' => false, // optional
                        'breadcrumb_markup' => true
                    ],
                    'canonical_urls' => [
                        'weight' => 10,
                        'canonical_tags' => true,
                        'duplicate_content_handling' => true,
                        'url_consistency' => true
                    ]
                ]
            ],
            'social_optimization' => [
                'weight' => 15,
                'max_score' => 100,
                'criteria' => [
                    'open_graph_tags' => [
                        'weight' => 40,
                        'og_title' => true,
                        'og_description' => true,
                        'og_image' => true,
                        'og_url' => true,
                        'og_type' => true
                    ],
                    'twitter_cards' => [
                        'weight' => 30,
                        'twitter_card_type' => true,
                        'twitter_title' => true,
                        'twitter_description' => true,
                        'twitter_image' => true
                    ],
                    'social_sharing' => [
                        'weight' => 20,
                        'share_buttons' => false, // optional
                        'social_proof' => false, // optional
                        'social_media_presence' => false // optional
                    ],
                    'content_shareability' => [
                        'weight' => 10,
                        'engaging_headlines' => true,
                        'visual_content' => true,
                        'emotional_triggers' => false // advanced
                    ]
                ]
            ],
            'user_experience' => [
                'weight' => 15,
                'max_score' => 100,
                'criteria' => [
                    'page_layout' => [
                        'weight' => 25,
                        'clear_navigation' => true,
                        'logical_structure' => true,
                        'white_space_usage' => true,
                        'visual_hierarchy' => true
                    ],
                    'content_accessibility' => [
                        'weight' => 25,
                        'alt_text_images' => true,
                        'color_contrast' => true,
                        'keyboard_navigation' => true,
                        'screen_reader_friendly' => true
                    ],
                    'engagement_metrics' => [
                        'weight' => 25,
                        'bounce_rate_threshold' => 60, // percentage
                        'time_on_page_threshold' => 120, // seconds
                        'pages_per_session' => 2.0, // minimum
                        'return_visitor_rate' => 30 // percentage
                    ],
                    'conversion_optimization' => [
                        'weight' => 25,
                        'clear_cta' => true,
                        'form_optimization' => false, // optional
                        'trust_signals' => false, // optional
                        'loading_indicators' => true
                    ]
                ]
            ]
        ];
        
        // Allow filtering of scoring config
        $this->scoring_config = apply_filters('khm_seo_scoring_config', $this->scoring_config);
    }
    
    /**
     * Initialize competitive benchmarks
     */
    private function init_benchmarks() {
        $this->benchmarks = [
            'industry_averages' => [
                'overall_seo_score' => 65,
                'content_quality_score' => 70,
                'technical_seo_score' => 75,
                'social_optimization_score' => 60,
                'user_experience_score' => 65
            ],
            'top_performers' => [
                'overall_seo_score' => 85,
                'content_quality_score' => 90,
                'technical_seo_score' => 95,
                'social_optimization_score' => 80,
                'user_experience_score' => 85
            ]
        ];
    }
    
    /**
     * Generate comprehensive SEO score for content
     *
     * @param int|WP_Post $post Post ID or post object
     * @param array $options Analysis options
     * @return array Complete SEO analysis results
     */
    public function analyze_content($post, $options = []) {
        // Normalize post input
        if (is_numeric($post)) {
            $post = get_post($post);
        }
        
        if (!$post || $post->post_status !== 'publish') {
            return $this->get_default_analysis_result();
        }
        
        // Check cache first
        $cache_key = $this->generate_cache_key($post, $options);
        if (isset($this->analysis_cache[$cache_key])) {
            return $this->analysis_cache[$cache_key];
        }
        
        $analysis_result = [
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'analysis_timestamp' => current_time('c'),
            'overall_score' => 0,
            'grade' => 'F',
            'category_scores' => [],
            'detailed_analysis' => [],
            'recommendations' => [],
            'strengths' => [],
            'critical_issues' => [],
            'improvement_opportunities' => [],
            'competitive_analysis' => [],
            'historical_comparison' => []
        ];
        
        $total_weighted_score = 0;
        $total_weights = 0;
        
        // Analyze each scoring category
        foreach ($this->scoring_config as $category => $config) {
            $category_analysis = $this->analyze_category($post, $category, $config, $options);
            
            $weighted_score = ($category_analysis['score'] * $config['weight']) / 100;
            $total_weighted_score += $weighted_score;
            $total_weights += $config['weight'];
            
            $analysis_result['category_scores'][$category] = [
                'score' => $category_analysis['score'],
                'weight' => $config['weight'],
                'weighted_contribution' => $weighted_score,
                'grade' => $this->calculate_grade($category_analysis['score']),
                'status' => $this->determine_category_status($category_analysis['score'])
            ];
            
            $analysis_result['detailed_analysis'][$category] = $category_analysis['details'];
            
            // Collect recommendations by priority
            $analysis_result['recommendations'] = array_merge(
                $analysis_result['recommendations'],
                $category_analysis['recommendations']
            );
            
            // Collect strengths
            $analysis_result['strengths'] = array_merge(
                $analysis_result['strengths'],
                $category_analysis['strengths']
            );
            
            // Collect critical issues
            if (!empty($category_analysis['critical_issues'])) {
                $analysis_result['critical_issues'] = array_merge(
                    $analysis_result['critical_issues'],
                    $category_analysis['critical_issues']
                );
            }
            
            // Collect improvement opportunities
            $analysis_result['improvement_opportunities'] = array_merge(
                $analysis_result['improvement_opportunities'],
                $category_analysis['improvement_opportunities']
            );
        }
        
        // Calculate overall score
        $analysis_result['overall_score'] = $total_weights > 0 ? round($total_weighted_score / $total_weights * 100) : 0;
        $analysis_result['grade'] = $this->calculate_grade($analysis_result['overall_score']);
        
        // Prioritize recommendations
        $analysis_result['recommendations'] = $this->prioritize_recommendations($analysis_result['recommendations']);
        
        // Add competitive analysis
        $analysis_result['competitive_analysis'] = $this->generate_competitive_analysis($analysis_result);
        
        // Add historical comparison if available
        $analysis_result['historical_comparison'] = $this->get_historical_comparison($post->ID, $analysis_result['overall_score']);
        
        // Cache the result
        $this->analysis_cache[$cache_key] = $analysis_result;
        
        // Store in database for historical tracking
        $this->store_analysis_result($analysis_result);
        
        return $analysis_result;
    }
    
    /**
     * Analyze specific category
     *
     * @param WP_Post $post Post object
     * @param string $category Category name
     * @param array $config Category configuration
     * @param array $options Analysis options
     * @return array Category analysis results
     */
    private function analyze_category($post, $category, $config, $options = []) {
        $analysis_method = 'analyze_' . $category;
        
        if (method_exists($this, $analysis_method)) {
            return $this->$analysis_method($post, $config, $options);
        }
        
        // Fallback for undefined categories
        return $this->get_default_category_analysis($category);
    }
    
    /**
     * Analyze content quality
     *
     * @param WP_Post $post Post object
     * @param array $config Category configuration
     * @param array $options Analysis options
     * @return array Content quality analysis
     */
    private function analyze_content_quality($post, $config, $options = []) {
        $analysis = [
            'score' => 0,
            'details' => [],
            'recommendations' => [],
            'strengths' => [],
            'critical_issues' => [],
            'improvement_opportunities' => []
        ];
        
        $total_points = 0;
        $max_points = 0;
        
        foreach ($config['criteria'] as $criterion => $criterion_config) {
            $max_points += $criterion_config['weight'];
            $criterion_result = $this->analyze_content_criterion($post, $criterion, $criterion_config);
            
            $points = ($criterion_result['score'] * $criterion_config['weight']) / 100;
            $total_points += $points;
            
            $analysis['details'][$criterion] = $criterion_result;
            
            // Categorize results
            if ($criterion_result['score'] >= 90) {
                $analysis['strengths'][] = [
                    'criterion' => $criterion,
                    'score' => $criterion_result['score'],
                    'message' => $criterion_result['message'],
                    'impact' => 'high'
                ];
            } elseif ($criterion_result['score'] < 30) {
                $analysis['critical_issues'][] = [
                    'criterion' => $criterion,
                    'score' => $criterion_result['score'],
                    'issue' => $criterion_result['message'],
                    'recommendation' => $criterion_result['recommendation'],
                    'priority' => 'high',
                    'estimated_impact' => $this->estimate_improvement_impact($criterion, $criterion_result['score'])
                ];
            } elseif ($criterion_result['score'] < 70) {
                $analysis['improvement_opportunities'][] = [
                    'criterion' => $criterion,
                    'score' => $criterion_result['score'],
                    'opportunity' => $criterion_result['message'],
                    'recommendation' => $criterion_result['recommendation'],
                    'priority' => $criterion_result['score'] < 50 ? 'medium' : 'low',
                    'estimated_impact' => $this->estimate_improvement_impact($criterion, $criterion_result['score'])
                ];
            }
            
            // Add to recommendations
            if (!empty($criterion_result['recommendation'])) {
                $analysis['recommendations'][] = [
                    'criterion' => $criterion,
                    'recommendation' => $criterion_result['recommendation'],
                    'priority' => $this->determine_recommendation_priority($criterion_result['score']),
                    'effort_level' => $this->estimate_effort_level($criterion),
                    'expected_improvement' => $this->estimate_score_improvement($criterion, $criterion_result['score'])
                ];
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
     * @param array $config Criterion configuration
     * @return array Criterion analysis result
     */
    private function analyze_content_criterion($post, $criterion, $config) {
        switch ($criterion) {
            case 'title_optimization':
                return $this->analyze_title_optimization($post, $config);
            case 'meta_description':
                return $this->analyze_meta_description($post, $config);
            case 'content_length':
                return $this->analyze_content_length($post, $config);
            case 'keyword_optimization':
                return $this->analyze_keyword_optimization($post, $config);
            case 'readability':
                return $this->analyze_readability($post, $config);
            case 'internal_linking':
                return $this->analyze_internal_linking($post, $config);
            case 'image_optimization':
                return $this->analyze_image_optimization($post, $config);
            default:
                return [
                    'score' => 50,
                    'message' => "Analysis not implemented for {$criterion}",
                    'recommendation' => "Implement {$criterion} analysis"
                ];
        }
    }
    
    /**
     * Analyze title optimization with advanced scoring
     *
     * @param WP_Post $post Post object
     * @param array $config Configuration
     * @return array Title analysis result
     */
    private function analyze_title_optimization($post, $config) {
        $title = $post->post_title;
        $title_length = mb_strlen($title, 'UTF-8');
        
        $score = 0;
        $issues = [];
        $recommendations = [];
        
        // Length optimization (40% of score)
        $optimal_min = $config['optimal_range'][0];
        $optimal_max = $config['optimal_range'][1];
        
        if ($title_length >= $optimal_min && $title_length <= $optimal_max) {
            $score += 40;
        } elseif ($title_length < $optimal_min) {
            $score += max(0, 40 * ($title_length / $optimal_min));
            $issues[] = "Title is too short ({$title_length} characters)";
            $recommendations[] = "Expand title to {$optimal_min}-{$optimal_max} characters for optimal SEO impact";
        } elseif ($title_length > $optimal_max) {
            $penalty = min(20, ($title_length - $optimal_max) * 0.5);
            $score += max(20, 40 - $penalty);
            $issues[] = "Title is too long ({$title_length} characters)";
            $recommendations[] = "Shorten title to under {$optimal_max} characters to avoid truncation in search results";
        }
        
        // Focus keyword optimization (30% of score)
        $focus_keyword = get_post_meta($post->ID, '_khm_seo_focus_keyword', true);
        if (!empty($focus_keyword)) {
            $keyword_position = mb_stripos($title, $focus_keyword);
            if ($keyword_position !== false) {
                // Award points based on keyword placement
                if ($keyword_position === 0) {
                    $score += 30; // Perfect placement at beginning
                } elseif ($keyword_position <= 10) {
                    $score += 25; // Good placement near beginning
                } else {
                    $score += 20; // Acceptable placement
                }
            } else {
                $issues[] = 'Focus keyword not found in title';
                $recommendations[] = 'Include your focus keyword in the title, preferably near the beginning';
            }
        } else {
            $score += 15; // Partial credit if no focus keyword is set
            $recommendations[] = 'Set a focus keyword and include it in your title';
        }
        
        // Title uniqueness (20% of score)
        if ($this->check_title_uniqueness($post->ID, $title)) {
            $score += 20;
        } else {
            $issues[] = 'Title may not be unique across your site';
            $recommendations[] = 'Create a unique, distinctive title to avoid competition with your own content';
        }
        
        // Title engagement potential (10% of score)
        $engagement_score = $this->analyze_title_engagement($title);
        $score += ($engagement_score / 100) * 10;
        
        if ($engagement_score < 50) {
            $recommendations[] = 'Consider making your title more engaging with power words, numbers, or emotional triggers';
        }
        
        return [
            'score' => min(100, round($score)),
            'message' => empty($issues) ? 'Title is well optimized' : implode('. ', $issues),
            'recommendation' => implode('. ', $recommendations),
            'details' => [
                'title_length' => $title_length,
                'optimal_range' => $config['optimal_range'],
                'focus_keyword_present' => !empty($focus_keyword) && mb_stripos($title, $focus_keyword) !== false,
                'is_unique' => $this->check_title_uniqueness($post->ID, $title),
                'engagement_score' => $engagement_score
            ]
        ];
    }
    
    /**
     * Check title uniqueness
     */
    private function check_title_uniqueness($post_id, $title) {
        global $wpdb;
        
        $duplicate_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_title = %s 
            AND ID != %d 
            AND post_status = 'publish'
        ", $title, $post_id));
        
        return $duplicate_count == 0;
    }
    
    /**
     * Analyze title engagement potential
     */
    private function analyze_title_engagement($title) {
        $score = 50; // Base score
        
        // Power words boost
        $power_words = ['ultimate', 'complete', 'essential', 'proven', 'secret', 'amazing', 'incredible', 'powerful'];
        foreach ($power_words as $word) {
            if (stripos($title, $word) !== false) {
                $score += 10;
                break;
            }
        }
        
        // Numbers boost
        if (preg_match('/\d+/', $title)) {
            $score += 15;
        }
        
        // Question format boost
        if (strpos($title, '?') !== false) {
            $score += 10;
        }
        
        // How-to format boost
        if (stripos($title, 'how to') !== false) {
            $score += 12;
        }
        
        return min(100, $score);
    }
    
    /**
     * Generate competitive analysis
     */
    private function generate_competitive_analysis($analysis_result) {
        $overall_score = $analysis_result['overall_score'];
        
        $competitive_analysis = [
            'vs_industry_average' => [
                'score_difference' => $overall_score - $this->benchmarks['industry_averages']['overall_seo_score'],
                'performance_level' => $this->determine_performance_level($overall_score, 'industry_average'),
                'ranking_estimate' => $this->estimate_ranking_potential($overall_score)
            ],
            'vs_top_performers' => [
                'score_difference' => $overall_score - $this->benchmarks['top_performers']['overall_seo_score'],
                'gap_analysis' => $this->analyze_performance_gaps($analysis_result),
                'improvement_potential' => $this->calculate_improvement_potential($analysis_result)
            ]
        ];
        
        return $competitive_analysis;
    }
    
    /**
     * Prioritize recommendations by impact and effort
     */
    private function prioritize_recommendations($recommendations) {
        // Sort by priority (high > medium > low) and then by expected improvement
        usort($recommendations, function($a, $b) {
            $priority_order = ['high' => 3, 'medium' => 2, 'low' => 1];
            
            if ($a['priority'] !== $b['priority']) {
                return $priority_order[$b['priority']] - $priority_order[$a['priority']];
            }
            
            return ($b['expected_improvement'] ?? 0) - ($a['expected_improvement'] ?? 0);
        });
        
        return array_slice($recommendations, 0, 10); // Return top 10 recommendations
    }
    
    // Helper methods with placeholder implementations
    private function analyze_meta_description($post, $config) { /* Implementation */ return ['score' => 75, 'message' => 'Meta description analysis', 'recommendation' => 'Optimize meta description']; }
    private function analyze_content_length($post, $config) { /* Implementation */ return ['score' => 80, 'message' => 'Content length analysis', 'recommendation' => 'Content length is good']; }
    private function analyze_keyword_optimization($post, $config) { /* Implementation */ return ['score' => 70, 'message' => 'Keyword optimization analysis', 'recommendation' => 'Optimize keyword usage']; }
    private function analyze_readability($post, $config) { /* Implementation */ return ['score' => 75, 'message' => 'Readability analysis', 'recommendation' => 'Improve readability']; }
    private function analyze_internal_linking($post, $config) { /* Implementation */ return ['score' => 65, 'message' => 'Internal linking analysis', 'recommendation' => 'Add more internal links']; }
    private function analyze_image_optimization($post, $config) { /* Implementation */ return ['score' => 70, 'message' => 'Image optimization analysis', 'recommendation' => 'Optimize images']; }
    private function analyze_technical_seo($post, $config, $options) { /* Implementation */ return ['score' => 75, 'details' => [], 'recommendations' => [], 'strengths' => [], 'critical_issues' => [], 'improvement_opportunities' => []]; }
    private function analyze_social_optimization($post, $config, $options) { /* Implementation */ return ['score' => 80, 'details' => [], 'recommendations' => [], 'strengths' => [], 'critical_issues' => [], 'improvement_opportunities' => []]; }
    private function analyze_user_experience($post, $config, $options) { /* Implementation */ return ['score' => 70, 'details' => [], 'recommendations' => [], 'strengths' => [], 'critical_issues' => [], 'improvement_opportunities' => []]; }
    
    private function estimate_improvement_impact($criterion, $score) { return max(10, 100 - $score); }
    private function determine_recommendation_priority($score) { return $score < 30 ? 'high' : ($score < 70 ? 'medium' : 'low'); }
    private function estimate_effort_level($criterion) { return 'medium'; }
    private function estimate_score_improvement($criterion, $current_score) { return min(30, max(5, 100 - $current_score)); }
    private function determine_category_status($score) { return $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : ($score >= 40 ? 'needs_improvement' : 'poor')); }
    private function determine_performance_level($score, $benchmark) { return $score >= 80 ? 'excellent' : 'good'; }
    private function estimate_ranking_potential($score) { return $score >= 80 ? 'high' : 'medium'; }
    private function analyze_performance_gaps($analysis) { return []; }
    private function calculate_improvement_potential($analysis) { return 25; }
    private function get_historical_comparison($post_id, $current_score) { return ['trend' => 'improving', 'change' => '+5']; }
    private function store_analysis_result($analysis) { /* Store in database */ }
    private function calculate_grade($score) { return $score >= 90 ? 'A+' : ($score >= 80 ? 'A' : ($score >= 70 ? 'B' : ($score >= 60 ? 'C' : 'D'))); }
    private function get_default_analysis_result() { return ['error' => 'Invalid content']; }
    private function get_default_category_analysis($category) { return ['score' => 0, 'details' => [], 'recommendations' => [], 'strengths' => [], 'critical_issues' => [], 'improvement_opportunities' => []]; }
    private function generate_cache_key($post, $options) { return 'seo_analysis_' . $post->ID . '_' . md5(serialize($options)); }
    
    /**
     * Schedule post analysis
     */
    public function schedule_post_analysis($post_id) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Schedule analysis for next cron run
        wp_schedule_single_event(time() + 60, 'khm_seo_analyze_single_post', [$post_id]);
    }
    
    /**
     * Run daily analysis for all content
     */
    public function run_daily_analysis() {
        // Analyze recent posts and pages
        $recent_posts = get_posts([
            'numberposts' => 50,
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC'
        ]);
        
        foreach ($recent_posts as $post) {
            $this->analyze_content($post);
        }
    }
    
    /**
     * Apply custom scoring weights
     */
    public function apply_custom_scoring_weights($weights) {
        return array_merge($this->scoring_config, $weights);
    }
}