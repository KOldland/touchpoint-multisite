<?php
/**
 * Technical Analyzer - Technical SEO analysis and monitoring
 * 
 * Analyzes technical SEO aspects including site performance,
 * crawlability, indexability, and technical optimization opportunities.
 * 
 * @package KHM_SEO\Dashboard\Analytics
 * @since 2.1.0
 */

namespace KHM_SEO\Dashboard\Analytics;

/**
 * Technical Analyzer Class
 */
class TechnicalAnalyzer {
    /**
     * @var array Technical analysis configuration
     */
    private $config;

    /**
     * @var array Cached technical data
     */
    private $technical_cache;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_config();
        $this->technical_cache = [];
    }

    /**
     * Initialize technical analysis configuration
     */
    private function init_config() {
        $this->config = [
            'technical_checks' => [
                'meta_tags' => ['weight' => 25, 'critical' => true],
                'heading_structure' => ['weight' => 20, 'critical' => true],
                'image_optimization' => ['weight' => 15, 'critical' => false],
                'internal_linking' => ['weight' => 15, 'critical' => false],
                'url_structure' => ['weight' => 10, 'critical' => false],
                'schema_markup' => ['weight' => 10, 'critical' => false],
                'site_performance' => ['weight' => 5, 'critical' => false]
            ],
            'severity_thresholds' => [
                'critical' => 30,
                'high' => 50,
                'medium' => 70,
                'low' => 85
            ],
            'performance_metrics' => [
                'page_load_time' => ['target' => 3000, 'unit' => 'ms'],
                'image_optimization' => ['target' => 90, 'unit' => '%'],
                'mobile_friendly' => ['target' => 100, 'unit' => '%'],
                'ssl_status' => ['target' => 100, 'unit' => '%']
            ]
        ];
    }

    /**
     * Get comprehensive technical analysis data
     *
     * @return array Technical analysis dashboard data
     */
    public function get_technical_data() {
        $cache_key = 'technical_analysis_data';
        
        if (isset($this->technical_cache[$cache_key])) {
            return $this->technical_cache[$cache_key];
        }

        $data = [
            'site_health_score' => $this->calculate_site_health_score(),
            'technical_issues' => $this->get_technical_issues(),
            'performance_metrics' => $this->get_performance_metrics(),
            'crawlability_analysis' => $this->analyze_crawlability(),
            'meta_tag_analysis' => $this->analyze_meta_tags(),
            'image_optimization' => $this->analyze_image_optimization(),
            'mobile_optimization' => $this->analyze_mobile_optimization(),
            'security_analysis' => $this->analyze_security_status(),
            'recommendations' => $this->generate_technical_recommendations()
        ];

        $this->technical_cache[$cache_key] = $data;
        
        return $data;
    }

    /**
     * Calculate overall site health score
     *
     * @return array Site health score and breakdown
     */
    private function calculate_site_health_score() {
        $health_factors = [
            'meta_compliance' => $this->check_meta_compliance(),
            'heading_structure' => $this->check_heading_structure(),
            'image_optimization' => $this->check_image_optimization(),
            'internal_linking' => $this->check_internal_linking(),
            'mobile_optimization' => $this->check_mobile_optimization(),
            'site_security' => $this->check_site_security()
        ];

        $total_score = 0;
        $factor_count = count($health_factors);

        foreach ($health_factors as $factor => $score) {
            $total_score += $score;
        }

        $overall_score = $factor_count > 0 ? round($total_score / $factor_count) : 0;

        return [
            'overall_score' => $overall_score,
            'health_status' => $this->determine_health_status($overall_score),
            'factors' => $health_factors,
            'chart_data' => [
                'labels' => array_keys($health_factors),
                'data' => array_values($health_factors),
                'colors' => array_map([$this, 'get_score_color'], array_values($health_factors))
            ]
        ];
    }

    /**
     * Get technical issues across the site
     *
     * @return array Technical issues by severity
     */
    private function get_technical_issues() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $issues = [
            'critical' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
            'summary' => ['total' => 0, 'by_severity' => []]
        ];

        // Analyze meta tag issues
        $meta_issues = $this->identify_meta_tag_issues();
        $this->categorize_issues($issues, $meta_issues);

        // Analyze heading structure issues
        $heading_issues = $this->identify_heading_issues();
        $this->categorize_issues($issues, $heading_issues);

        // Analyze image optimization issues
        $image_issues = $this->identify_image_issues();
        $this->categorize_issues($issues, $image_issues);

        // Analyze internal linking issues
        $linking_issues = $this->identify_linking_issues();
        $this->categorize_issues($issues, $linking_issues);

        // Calculate summary
        $issues['summary']['total'] = 
            count($issues['critical']) + 
            count($issues['high']) + 
            count($issues['medium']) + 
            count($issues['low']);

        $issues['summary']['by_severity'] = [
            'critical' => count($issues['critical']),
            'high' => count($issues['high']),
            'medium' => count($issues['medium']),
            'low' => count($issues['low'])
        ];

        return $issues;
    }

    /**
     * Get performance metrics
     *
     * @return array Performance analysis data
     */
    private function get_performance_metrics() {
        $metrics = [
            'page_speed' => $this->analyze_page_speed(),
            'image_optimization' => $this->get_image_optimization_metrics(),
            'mobile_performance' => $this->analyze_mobile_performance(),
            'core_web_vitals' => $this->analyze_core_web_vitals()
        ];

        return [
            'metrics' => $metrics,
            'overall_performance' => $this->calculate_performance_score($metrics),
            'recommendations' => $this->generate_performance_recommendations($metrics)
        ];
    }

    /**
     * Analyze site crawlability
     *
     * @return array Crawlability analysis
     */
    private function analyze_crawlability() {
        $analysis = [
            'robots_txt' => $this->check_robots_txt(),
            'sitemap' => $this->check_sitemap_status(),
            'internal_links' => $this->analyze_internal_link_structure(),
            'orphaned_pages' => $this->identify_orphaned_pages(),
            'crawl_errors' => $this->check_crawl_errors()
        ];

        $crawlability_score = $this->calculate_crawlability_score($analysis);

        return [
            'analysis' => $analysis,
            'crawlability_score' => $crawlability_score,
            'issues_found' => $this->identify_crawlability_issues($analysis),
            'recommendations' => $this->generate_crawlability_recommendations($analysis)
        ];
    }

    /**
     * Analyze meta tags across the site
     *
     * @return array Meta tag analysis
     */
    private function analyze_meta_tags() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $meta_analysis = $wpdb->get_results(
            "SELECT 
                ar.post_id,
                p.post_title,
                p.post_type,
                JSON_EXTRACT(ar.analysis_data, '$.detailed_analysis.meta_description') as meta_desc_data,
                JSON_EXTRACT(ar.analysis_data, '$.detailed_analysis.title_analysis') as title_data
             FROM {$table_name} ar
             JOIN {$wpdb->posts} p ON ar.post_id = p.ID
             WHERE p.post_status = 'publish'
             AND ar.analyzed_at = (
                 SELECT MAX(analyzed_at) 
                 FROM {$table_name} ar2 
                 WHERE ar2.post_id = ar.post_id
             )",
            ARRAY_A
        );

        $stats = [
            'total_pages' => count($meta_analysis),
            'pages_with_meta_desc' => 0,
            'pages_with_optimized_titles' => 0,
            'meta_desc_issues' => [],
            'title_issues' => [],
            'compliance_rate' => 0
        ];

        foreach ($meta_analysis as $page) {
            $meta_desc_data = json_decode($page['meta_desc_data'], true);
            $title_data = json_decode($page['title_data'], true);

            // Check meta description
            if ($meta_desc_data && $meta_desc_data['score'] >= 70) {
                $stats['pages_with_meta_desc']++;
            } else {
                $stats['meta_desc_issues'][] = [
                    'post_id' => $page['post_id'],
                    'title' => $page['post_title'],
                    'issue' => 'Missing or poor meta description'
                ];
            }

            // Check title optimization
            if ($title_data && $title_data['score'] >= 70) {
                $stats['pages_with_optimized_titles']++;
            } else {
                $stats['title_issues'][] = [
                    'post_id' => $page['post_id'],
                    'title' => $page['post_title'],
                    'issue' => 'Title not optimized'
                ];
            }
        }

        $stats['compliance_rate'] = $stats['total_pages'] > 0 
            ? round((($stats['pages_with_meta_desc'] + $stats['pages_with_optimized_titles']) / ($stats['total_pages'] * 2)) * 100, 1)
            : 0;

        return $stats;
    }

    /**
     * Analyze image optimization
     *
     * @return array Image optimization analysis
     */
    private function analyze_image_optimization() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $image_analysis = $wpdb->get_results(
            "SELECT 
                ar.post_id,
                p.post_title,
                JSON_EXTRACT(ar.analysis_data, '$.detailed_analysis.image_alt_tags') as image_data
             FROM {$table_name} ar
             JOIN {$wpdb->posts} p ON ar.post_id = p.ID
             WHERE p.post_status = 'publish'
             AND ar.analyzed_at = (
                 SELECT MAX(analyzed_at) 
                 FROM {$table_name} ar2 
                 WHERE ar2.post_id = ar.post_id
             )
             AND JSON_EXTRACT(ar.analysis_data, '$.detailed_analysis.image_alt_tags') IS NOT NULL",
            ARRAY_A
        );

        $stats = [
            'total_pages_with_images' => count($image_analysis),
            'pages_with_optimized_images' => 0,
            'total_images' => 0,
            'images_with_alt_text' => 0,
            'optimization_rate' => 0,
            'issues' => []
        ];

        foreach ($image_analysis as $page) {
            $image_data = json_decode($page['image_data'], true);

            if ($image_data) {
                $stats['total_images'] += $image_data['total_images'] ?? 0;
                $stats['images_with_alt_text'] += $image_data['images_with_alt'] ?? 0;

                if (($image_data['score'] ?? 0) >= 70) {
                    $stats['pages_with_optimized_images']++;
                } else {
                    $stats['issues'][] = [
                        'post_id' => $page['post_id'],
                        'title' => $page['post_title'],
                        'missing_alt' => ($image_data['total_images'] ?? 0) - ($image_data['images_with_alt'] ?? 0)
                    ];
                }
            }
        }

        $stats['optimization_rate'] = $stats['total_images'] > 0 
            ? round(($stats['images_with_alt_text'] / $stats['total_images']) * 100, 1)
            : 0;

        return $stats;
    }

    /**
     * Analyze mobile optimization
     *
     * @return array Mobile optimization analysis
     */
    private function analyze_mobile_optimization() {
        // This would typically integrate with Google PageSpeed Insights API
        // For now, returning structure with sample data
        return [
            'mobile_friendly_score' => 85,
            'mobile_issues' => [
                'viewport_not_set' => 0,
                'text_too_small' => 2,
                'clickable_elements_too_close' => 1,
                'content_wider_than_screen' => 0
            ],
            'mobile_performance' => [
                'first_contentful_paint' => 2.1,
                'largest_contentful_paint' => 3.8,
                'cumulative_layout_shift' => 0.12
            ],
            'recommendations' => [
                'Optimize images for mobile',
                'Improve touch target sizes',
                'Reduce server response time'
            ]
        ];
    }

    /**
     * Analyze security status
     *
     * @return array Security analysis
     */
    private function analyze_security_status() {
        $security_checks = [
            'ssl_certificate' => $this->check_ssl_status(),
            'https_redirect' => $this->check_https_redirect(),
            'security_headers' => $this->check_security_headers(),
            'wp_version' => $this->check_wp_version_security()
        ];

        $security_score = array_sum($security_checks) / count($security_checks);

        return [
            'security_score' => round($security_score),
            'checks' => $security_checks,
            'status' => $security_score >= 80 ? 'good' : ($security_score >= 60 ? 'fair' : 'poor'),
            'recommendations' => $this->generate_security_recommendations($security_checks)
        ];
    }

    /**
     * Generate technical recommendations
     *
     * @return array Technical optimization recommendations
     */
    private function generate_technical_recommendations() {
        $recommendations = [
            'immediate' => [],
            'short_term' => [],
            'long_term' => []
        ];

        $health_score = $this->calculate_site_health_score();

        // Immediate recommendations for critical issues
        if ($health_score['factors']['meta_compliance'] < 50) {
            $recommendations['immediate'][] = [
                'title' => 'Fix Critical Meta Tag Issues',
                'description' => 'Many pages are missing essential meta tags',
                'impact' => 'High',
                'effort' => 'Medium'
            ];
        }

        if ($health_score['factors']['site_security'] < 70) {
            $recommendations['immediate'][] = [
                'title' => 'Address Security Vulnerabilities',
                'description' => 'Site security needs immediate attention',
                'impact' => 'Critical',
                'effort' => 'High'
            ];
        }

        // Short-term recommendations
        if ($health_score['factors']['image_optimization'] < 70) {
            $recommendations['short_term'][] = [
                'title' => 'Optimize Images Site-wide',
                'description' => 'Implement alt text and optimize image sizes',
                'impact' => 'Medium',
                'effort' => 'Medium'
            ];
        }

        // Long-term recommendations
        $recommendations['long_term'][] = [
            'title' => 'Implement Automated SEO Monitoring',
            'description' => 'Set up regular automated checks for technical SEO issues',
            'impact' => 'High',
            'effort' => 'High'
        ];

        return $recommendations;
    }

    /**
     * Helper methods for specific checks
     */
    private function check_meta_compliance() {
        $meta_analysis = $this->analyze_meta_tags();
        return $meta_analysis['compliance_rate'];
    }

    private function check_heading_structure() {
        // Implementation would analyze H1-H6 structure across content
        return 75; // Sample value
    }

    private function check_image_optimization() {
        $image_analysis = $this->analyze_image_optimization();
        return $image_analysis['optimization_rate'];
    }

    private function check_internal_linking() {
        // Implementation would analyze internal link patterns
        return 68; // Sample value
    }

    private function check_mobile_optimization() {
        $mobile_analysis = $this->analyze_mobile_optimization();
        return $mobile_analysis['mobile_friendly_score'];
    }

    private function check_site_security() {
        $security_analysis = $this->analyze_security_status();
        return $security_analysis['security_score'];
    }

    private function determine_health_status($score) {
        if ($score >= 85) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'fair';
        return 'poor';
    }

    private function get_score_color($score) {
        if ($score >= 85) return '#00a32a';
        if ($score >= 70) return '#72aee6';
        if ($score >= 50) return '#dba617';
        return '#d63638';
    }

    // Placeholder implementations for various analysis methods
    private function identify_meta_tag_issues() { return []; }
    private function identify_heading_issues() { return []; }
    private function identify_image_issues() { return []; }
    private function identify_linking_issues() { return []; }
    private function categorize_issues(&$issues, $new_issues) { /* Implementation */ }
    private function analyze_page_speed() { return ['score' => 75]; }
    private function get_image_optimization_metrics() { return ['score' => 68]; }
    private function analyze_mobile_performance() { return ['score' => 82]; }
    private function analyze_core_web_vitals() { return ['score' => 71]; }
    private function calculate_performance_score($metrics) { return 74; }
    private function generate_performance_recommendations($metrics) { return []; }
    private function check_robots_txt() { return ['status' => 'found', 'score' => 85]; }
    private function check_sitemap_status() { return ['status' => 'found', 'score' => 90]; }
    private function analyze_internal_link_structure() { return ['score' => 72]; }
    private function identify_orphaned_pages() { return []; }
    private function check_crawl_errors() { return []; }
    private function calculate_crawlability_score($analysis) { return 78; }
    private function identify_crawlability_issues($analysis) { return []; }
    private function generate_crawlability_recommendations($analysis) { return []; }
    private function check_ssl_status() { return 100; }
    private function check_https_redirect() { return 95; }
    private function check_security_headers() { return 70; }
    private function check_wp_version_security() { return 90; }
    private function generate_security_recommendations($checks) { return []; }

    /**
     * Clear technical analysis cache
     */
    public function clear_cache() {
        $this->technical_cache = [];
    }
}