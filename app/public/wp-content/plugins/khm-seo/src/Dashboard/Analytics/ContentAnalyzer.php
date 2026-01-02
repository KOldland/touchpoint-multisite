<?php
/**
 * Content Analyzer - Content-focused SEO analysis and insights
 * 
 * Analyzes content quality, optimization opportunities,
 * and provides content-specific recommendations for SEO improvement.
 * 
 * @package KHM_SEO\Dashboard\Analytics
 * @since 2.1.0
 */

namespace KHM_SEO\Dashboard\Analytics;

use KHM_SEO\Core\AnalysisEngine;

/**
 * Content Analyzer Class
 */
class ContentAnalyzer {
    /**
     * @var AnalysisEngine
     */
    private $analysis_engine;

    /**
     * @var array Content analysis configuration
     */
    private $config;

    /**
     * @var array Cached content data
     */
    private $content_cache;

    /**
     * Constructor
     *
     * @param AnalysisEngine $analysis_engine Analysis engine instance
     */
    public function __construct(AnalysisEngine $analysis_engine) {
        $this->analysis_engine = $analysis_engine;
        $this->init_config();
        $this->content_cache = [];
    }

    /**
     * Initialize content analysis configuration
     */
    private function init_config() {
        $this->config = [
            'quality_thresholds' => [
                'excellent' => 90,
                'good' => 75,
                'fair' => 60,
                'poor' => 45,
                'critical' => 0
            ],
            'content_types' => [
                'post' => 'Blog Posts',
                'page' => 'Pages',
                'product' => 'Products',
                'service' => 'Services'
            ],
            'analysis_factors' => [
                'content_length' => ['weight' => 15, 'target' => 300],
                'keyword_density' => ['weight' => 20, 'target' => 2.5],
                'readability' => ['weight' => 15, 'target' => 60],
                'heading_structure' => ['weight' => 15, 'target' => 80],
                'meta_description' => ['weight' => 10, 'target' => 150],
                'title_analysis' => ['weight' => 15, 'target' => 55],
                'internal_links' => ['weight' => 5, 'target' => 3],
                'image_alt_tags' => ['weight' => 5, 'target' => 90]
            ]
        ];
    }

    /**
     * Get comprehensive content analysis data
     *
     * @return array Content analysis dashboard data
     */
    public function get_content_data() {
        $cache_key = 'content_analysis_data';
        
        if (isset($this->content_cache[$cache_key])) {
            return $this->content_cache[$cache_key];
        }

        $data = [
            'quality_distribution' => $this->get_content_quality_distribution(),
            'content_type_performance' => $this->get_content_type_performance(),
            'top_performing_content' => $this->get_top_performing_content(10),
            'content_opportunities' => $this->get_content_opportunities(15),
            'keyword_analysis' => $this->get_keyword_analysis(),
            'content_trends' => $this->get_content_trends(30),
            'optimization_suggestions' => $this->get_optimization_suggestions(),
            'content_gaps' => $this->identify_content_gaps()
        ];

        $this->content_cache[$cache_key] = $data;
        
        return $data;
    }

    /**
     * Get content quality distribution
     *
     * @return array Distribution of content by quality score
     */
    private function get_content_quality_distribution() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $distribution = [];
        foreach ($this->config['quality_thresholds'] as $level => $min_score) {
            $distribution[$level] = 0;
        }

        $results = $wpdb->get_results(
            "SELECT JSON_EXTRACT(analysis_data, '$.overall_score') as score, COUNT(*) as count
             FROM {$table_name} 
             WHERE analyzed_at = (
                 SELECT MAX(analyzed_at) 
                 FROM {$table_name} ar2 
                 WHERE ar2.post_id = {$table_name}.post_id
             )
             GROUP BY JSON_EXTRACT(analysis_data, '$.overall_score')",
            ARRAY_A
        );

        foreach ($results as $result) {
            $score = (float) $result['score'];
            $count = (int) $result['count'];
            $quality_level = $this->determine_quality_level($score);
            $distribution[$quality_level] += $count;
        }

        // Calculate percentages and prepare chart data
        $total = array_sum($distribution);
        $chart_data = [
            'labels' => [],
            'data' => [],
            'colors' => [],
            'percentages' => []
        ];

        $quality_colors = [
            'excellent' => '#00a32a',
            'good' => '#72aee6',
            'fair' => '#dba617',
            'poor' => '#ff6900',
            'critical' => '#d63638'
        ];

        foreach ($distribution as $level => $count) {
            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            
            $chart_data['labels'][] = ucfirst($level);
            $chart_data['data'][] = $count;
            $chart_data['colors'][] = $quality_colors[$level];
            $chart_data['percentages'][] = $percentage;
        }

        return [
            'distribution' => $distribution,
            'chart_data' => $chart_data,
            'total_content' => $total,
            'quality_score' => $this->calculate_overall_content_quality($distribution, $total)
        ];
    }

    /**
     * Get content type performance analysis
     *
     * @return array Performance data by content type
     */
    private function get_content_type_performance() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $performance_data = [];

        foreach ($this->config['content_types'] as $post_type => $label) {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_count,
                    AVG(JSON_EXTRACT(ar.analysis_data, '$.overall_score')) as avg_score,
                    MAX(JSON_EXTRACT(ar.analysis_data, '$.overall_score')) as max_score,
                    MIN(JSON_EXTRACT(ar.analysis_data, '$.overall_score')) as min_score,
                    SUM(CASE WHEN JSON_EXTRACT(ar.analysis_data, '$.overall_score') >= 80 THEN 1 ELSE 0 END) as optimized_count
                 FROM {$table_name} ar
                 JOIN {$wpdb->posts} p ON ar.post_id = p.ID
                 WHERE p.post_type = %s
                 AND p.post_status = 'publish'
                 AND ar.analyzed_at = (
                     SELECT MAX(analyzed_at) 
                     FROM {$table_name} ar2 
                     WHERE ar2.post_id = ar.post_id
                 )",
                $post_type
            ), ARRAY_A);

            if ($result && $result['total_count'] > 0) {
                $performance_data[$post_type] = [
                    'label' => $label,
                    'total_count' => (int) $result['total_count'],
                    'avg_score' => round((float) $result['avg_score'], 1),
                    'max_score' => round((float) $result['max_score'], 1),
                    'min_score' => round((float) $result['min_score'], 1),
                    'optimized_count' => (int) $result['optimized_count'],
                    'optimization_rate' => round(((int) $result['optimized_count'] / (int) $result['total_count']) * 100, 1),
                    'quality_level' => $this->determine_quality_level((float) $result['avg_score'])
                ];
            }
        }

        return $performance_data;
    }

    /**
     * Get top performing content
     *
     * @param int $limit Number of items to return
     * @return array Top performing content items
     */
    private function get_top_performing_content($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ar.post_id,
                p.post_title,
                p.post_type,
                p.post_date,
                JSON_EXTRACT(ar.analysis_data, '$.overall_score') as score,
                ar.analysis_data,
                ar.analyzed_at
             FROM {$table_name} ar
             JOIN {$wpdb->posts} p ON ar.post_id = p.ID
             WHERE p.post_status = 'publish'
             AND ar.analyzed_at = (
                 SELECT MAX(analyzed_at) 
                 FROM {$table_name} ar2 
                 WHERE ar2.post_id = ar.post_id
             )
             ORDER BY JSON_EXTRACT(ar.analysis_data, '$.overall_score') DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return array_map(function($result) {
            $analysis_data = json_decode($result['analysis_data'], true);
            $strengths = $this->identify_content_strengths($analysis_data);
            
            return [
                'post_id' => $result['post_id'],
                'title' => $result['post_title'],
                'post_type' => ucfirst($result['post_type']),
                'score' => round((float) $result['score'], 1),
                'quality_level' => $this->determine_quality_level((float) $result['score']),
                'published' => strtotime($result['post_date']),
                'analyzed_at' => strtotime($result['analyzed_at']),
                'strengths' => $strengths,
                'edit_link' => get_edit_post_link($result['post_id']),
                'view_link' => get_permalink($result['post_id'])
            ];
        }, $results);
    }

    /**
     * Get content optimization opportunities
     *
     * @param int $limit Number of items to return
     * @return array Content items with optimization potential
     */
    private function get_content_opportunities($limit = 15) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ar.post_id,
                p.post_title,
                p.post_type,
                p.post_date,
                JSON_EXTRACT(ar.analysis_data, '$.overall_score') as score,
                ar.analysis_data,
                ar.analyzed_at
             FROM {$table_name} ar
             JOIN {$wpdb->posts} p ON ar.post_id = p.ID
             WHERE p.post_status = 'publish'
             AND JSON_EXTRACT(ar.analysis_data, '$.overall_score') BETWEEN 40 AND 79
             AND ar.analyzed_at = (
                 SELECT MAX(analyzed_at) 
                 FROM {$table_name} ar2 
                 WHERE ar2.post_id = ar.post_id
             )
             ORDER BY (JSON_EXTRACT(ar.analysis_data, '$.overall_score') - 
                      (SELECT AVG(JSON_EXTRACT(analysis_data, '$.overall_score')) 
                       FROM {$table_name})) DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return array_map(function($result) {
            $analysis_data = json_decode($result['analysis_data'], true);
            $opportunities = $this->identify_optimization_opportunities($analysis_data);
            $improvement_potential = $this->calculate_improvement_potential($analysis_data);
            
            return [
                'post_id' => $result['post_id'],
                'title' => $result['post_title'],
                'post_type' => ucfirst($result['post_type']),
                'current_score' => round((float) $result['score'], 1),
                'potential_score' => round((float) $result['score'] + $improvement_potential, 1),
                'improvement_potential' => $improvement_potential,
                'published' => strtotime($result['post_date']),
                'opportunities' => $opportunities,
                'priority' => $this->calculate_optimization_priority($analysis_data, $improvement_potential),
                'edit_link' => get_edit_post_link($result['post_id'])
            ];
        }, $results);
    }

    /**
     * Get keyword analysis data
     *
     * @return array Keyword optimization analysis
     */
    private function get_keyword_analysis() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        // Get keyword density analysis across content
        $keyword_data = $wpdb->get_results(
            "SELECT 
                ar.post_id,
                p.post_title,
                JSON_EXTRACT(ar.analysis_data, '$.detailed_analysis.keyword_density') as keyword_data
             FROM {$table_name} ar
             JOIN {$wpdb->posts} p ON ar.post_id = p.ID
             WHERE p.post_status = 'publish'
             AND ar.analyzed_at = (
                 SELECT MAX(analyzed_at) 
                 FROM {$table_name} ar2 
                 WHERE ar2.post_id = ar.post_id
             )
             AND JSON_EXTRACT(ar.analysis_data, '$.detailed_analysis.keyword_density') IS NOT NULL",
            ARRAY_A
        );

        $analysis = [
            'keyword_optimization_rate' => 0,
            'common_keyword_issues' => [],
            'keyword_opportunities' => [],
            'density_distribution' => []
        ];

        $total_analyzed = count($keyword_data);
        $optimized_count = 0;
        $density_ranges = [
            'under_optimized' => 0,  // < 0.5%
            'optimal' => 0,          // 0.5% - 3%
            'over_optimized' => 0    // > 3%
        ];

        foreach ($keyword_data as $item) {
            $keyword_analysis = json_decode($item['keyword_data'], true);
            
            if ($keyword_analysis && isset($keyword_analysis['score'])) {
                if ($keyword_analysis['score'] >= 70) {
                    $optimized_count++;
                }

                // Analyze density if available
                if (isset($keyword_analysis['density'])) {
                    $density = (float) $keyword_analysis['density'];
                    if ($density < 0.5) {
                        $density_ranges['under_optimized']++;
                    } elseif ($density <= 3.0) {
                        $density_ranges['optimal']++;
                    } else {
                        $density_ranges['over_optimized']++;
                    }
                }
            }
        }

        if ($total_analyzed > 0) {
            $analysis['keyword_optimization_rate'] = round(($optimized_count / $total_analyzed) * 100, 1);
        }

        $analysis['density_distribution'] = $density_ranges;

        // Identify common issues
        if ($density_ranges['under_optimized'] > $total_analyzed * 0.3) {
            $analysis['common_keyword_issues'][] = [
                'issue' => 'Under-optimized content',
                'description' => 'Many pages have insufficient keyword density',
                'affected_count' => $density_ranges['under_optimized']
            ];
        }

        if ($density_ranges['over_optimized'] > 0) {
            $analysis['common_keyword_issues'][] = [
                'issue' => 'Keyword stuffing',
                'description' => 'Some pages have excessive keyword density',
                'affected_count' => $density_ranges['over_optimized']
            ];
        }

        return $analysis;
    }

    /**
     * Get content trends over time
     *
     * @param int $days Number of days to analyze
     * @return array Content trends data
     */
    private function get_content_trends($days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        // Get daily content analysis data
        $daily_trends = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(ar.analyzed_at) as analysis_date,
                AVG(JSON_EXTRACT(ar.analysis_data, '$.overall_score')) as avg_score,
                COUNT(*) as content_analyzed,
                SUM(CASE WHEN JSON_EXTRACT(ar.analysis_data, '$.overall_score') >= 80 THEN 1 ELSE 0 END) as optimized_content,
                AVG(JSON_EXTRACT(ar.analysis_data, '$.detailed_analysis.content_length.word_count')) as avg_word_count,
                AVG(JSON_EXTRACT(ar.analysis_data, '$.detailed_analysis.readability.score')) as avg_readability
             FROM {$table_name} ar
             JOIN {$wpdb->posts} p ON ar.post_id = p.ID
             WHERE p.post_status = 'publish'
             AND ar.analyzed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(ar.analyzed_at)
             ORDER BY analysis_date ASC",
            $days
        ), ARRAY_A);

        // Prepare chart data
        $chart_data = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Average Score',
                    'data' => [],
                    'borderColor' => '#2271b1',
                    'backgroundColor' => '#2271b120',
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Content Analyzed',
                    'data' => [],
                    'borderColor' => '#72aee6',
                    'backgroundColor' => '#72aee620',
                    'yAxisID' => 'y1'
                ]
            ]
        ];

        foreach ($daily_trends as $day) {
            $chart_data['labels'][] = date('M j', strtotime($day['analysis_date']));
            $chart_data['datasets'][0]['data'][] = round((float) $day['avg_score'], 1);
            $chart_data['datasets'][1]['data'][] = (int) $day['content_analyzed'];
        }

        return [
            'chart_data' => $chart_data,
            'trends' => $daily_trends,
            'summary' => $this->analyze_content_trends($daily_trends)
        ];
    }

    /**
     * Get optimization suggestions for content
     *
     * @return array Optimization suggestions
     */
    private function get_optimization_suggestions() {
        $suggestions = [
            'priority_actions' => [],
            'quick_wins' => [],
            'long_term_strategies' => []
        ];

        // Analyze current content state to generate suggestions
        $quality_dist = $this->get_content_quality_distribution();
        $type_performance = $this->get_content_type_performance();

        // Priority actions based on quality distribution
        if ($quality_dist['distribution']['critical'] > 0) {
            $suggestions['priority_actions'][] = [
                'title' => 'Fix Critical Content Issues',
                'description' => sprintf('%d pieces of content have critical SEO issues', $quality_dist['distribution']['critical']),
                'action' => 'Review and optimize content with scores below 45',
                'estimated_impact' => 'High',
                'effort' => 'Medium'
            ];
        }

        // Quick wins
        if ($quality_dist['distribution']['fair'] > 0) {
            $suggestions['quick_wins'][] = [
                'title' => 'Optimize Fair-Quality Content',
                'description' => sprintf('%d pieces of content could easily reach good quality', $quality_dist['distribution']['fair']),
                'action' => 'Focus on meta descriptions and title optimization',
                'estimated_impact' => 'Medium',
                'effort' => 'Low'
            ];
        }

        // Long-term strategies
        $suggestions['long_term_strategies'][] = [
            'title' => 'Develop Content Quality Standards',
            'description' => 'Establish guidelines to ensure new content meets SEO standards',
            'action' => 'Create content templates and optimization checklists',
            'estimated_impact' => 'High',
            'effort' => 'High'
        ];

        return $suggestions;
    }

    /**
     * Identify content gaps and opportunities
     *
     * @return array Content gap analysis
     */
    private function identify_content_gaps() {
        global $wpdb;
        
        // Get all published content
        $total_published = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_type IN ('post', 'page')"
        );

        // Get analyzed content
        $total_analyzed = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT post_id) 
                 FROM {$wpdb->prefix}khm_seo_analysis_results ar
                 JOIN {$wpdb->posts} p ON ar.post_id = p.ID
                 WHERE p.post_status = 'publish' 
                 AND p.post_type IN ('post', 'page')"
            )
        );

        $analysis_coverage = $total_published > 0 ? round(($total_analyzed / $total_published) * 100, 1) : 0;

        $gaps = [
            'analysis_coverage' => $analysis_coverage,
            'unanalyzed_content' => $total_published - $total_analyzed,
            'recommendations' => []
        ];

        if ($analysis_coverage < 80) {
            $gaps['recommendations'][] = [
                'type' => 'coverage',
                'title' => 'Expand SEO Analysis Coverage',
                'description' => sprintf('Only %s%% of your content has been analyzed for SEO', $analysis_coverage),
                'action' => 'Run bulk analysis on remaining content'
            ];
        }

        // Check for content type gaps
        foreach ($this->config['content_types'] as $post_type => $label) {
            $type_total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                 WHERE post_status = 'publish' AND post_type = %s",
                $post_type
            ));

            $type_analyzed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT ar.post_id) 
                 FROM {$wpdb->prefix}khm_seo_analysis_results ar
                 JOIN {$wpdb->posts} p ON ar.post_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type = %s",
                $post_type
            ));

            $type_coverage = $type_total > 0 ? round(($type_analyzed / $type_total) * 100, 1) : 0;

            if ($type_coverage < 50 && $type_total > 5) {
                $gaps['recommendations'][] = [
                    'type' => 'content_type',
                    'title' => sprintf('Low %s Analysis Coverage', $label),
                    'description' => sprintf('Only %s%% of %s have been analyzed', $type_coverage, strtolower($label)),
                    'action' => sprintf('Prioritize %s for SEO analysis', strtolower($label))
                ];
            }
        }

        return $gaps;
    }

    /**
     * Helper method to determine quality level from score
     *
     * @param float $score SEO score
     * @return string Quality level
     */
    private function determine_quality_level($score) {
        foreach ($this->config['quality_thresholds'] as $level => $min_score) {
            if ($score >= $min_score) {
                return $level;
            }
        }
        return 'critical';
    }

    /**
     * Calculate overall content quality score
     *
     * @param array $distribution Quality distribution
     * @param int $total Total content count
     * @return float Overall quality score
     */
    private function calculate_overall_content_quality($distribution, $total) {
        if ($total === 0) return 0;

        $weighted_score = 0;
        $quality_weights = [
            'excellent' => 95,
            'good' => 80,
            'fair' => 65,
            'poor' => 40,
            'critical' => 20
        ];

        foreach ($distribution as $level => $count) {
            $weighted_score += ($quality_weights[$level] * $count);
        }

        return round($weighted_score / $total, 1);
    }

    /**
     * Identify content strengths from analysis data
     *
     * @param array $analysis_data Analysis results
     * @return array Content strengths
     */
    private function identify_content_strengths($analysis_data) {
        $strengths = [];

        if (isset($analysis_data['detailed_analysis'])) {
            foreach ($analysis_data['detailed_analysis'] as $analyzer => $data) {
                if ($data['score'] >= 85) {
                    $strengths[] = [
                        'analyzer' => $analyzer,
                        'score' => $data['score'],
                        'title' => ucwords(str_replace('_', ' ', $analyzer))
                    ];
                }
            }
        }

        return array_slice($strengths, 0, 3); // Return top 3 strengths
    }

    /**
     * Identify optimization opportunities from analysis data
     *
     * @param array $analysis_data Analysis results
     * @return array Optimization opportunities
     */
    private function identify_optimization_opportunities($analysis_data) {
        $opportunities = [];

        if (isset($analysis_data['detailed_analysis'])) {
            foreach ($analysis_data['detailed_analysis'] as $analyzer => $data) {
                if ($data['score'] >= 40 && $data['score'] < 75) {
                    $improvement_potential = min(25, (75 - $data['score']));
                    $opportunities[] = [
                        'analyzer' => $analyzer,
                        'current_score' => $data['score'],
                        'improvement_potential' => $improvement_potential,
                        'title' => ucwords(str_replace('_', ' ', $analyzer)),
                        'priority' => $this->calculate_opportunity_priority($data['score'], $improvement_potential)
                    ];
                }
            }
        }

        // Sort by priority and improvement potential
        usort($opportunities, function($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return $b['improvement_potential'] <=> $a['improvement_potential'];
            }
            return $a['priority'] === 'high' ? -1 : 1;
        });

        return array_slice($opportunities, 0, 5); // Return top 5 opportunities
    }

    /**
     * Calculate improvement potential for content
     *
     * @param array $analysis_data Analysis results
     * @return float Improvement potential
     */
    private function calculate_improvement_potential($analysis_data) {
        $potential = 0;

        if (isset($analysis_data['detailed_analysis'])) {
            foreach ($analysis_data['detailed_analysis'] as $analyzer => $data) {
                $factor_weight = $this->config['analysis_factors'][$analyzer]['weight'] ?? 5;
                $factor_potential = max(0, min(25, (85 - $data['score']))) * ($factor_weight / 100);
                $potential += $factor_potential;
            }
        }

        return round($potential, 1);
    }

    /**
     * Calculate optimization priority
     *
     * @param array $analysis_data Analysis results
     * @param float $improvement_potential Improvement potential
     * @return string Priority level
     */
    private function calculate_optimization_priority($analysis_data, $improvement_potential) {
        $overall_score = $analysis_data['overall_score'] ?? 0;

        if ($overall_score < 50 && $improvement_potential > 15) {
            return 'high';
        } elseif ($overall_score < 70 && $improvement_potential > 10) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate opportunity priority
     *
     * @param float $current_score Current analyzer score
     * @param float $improvement_potential Improvement potential
     * @return string Priority level
     */
    private function calculate_opportunity_priority($current_score, $improvement_potential) {
        if ($current_score < 50 && $improvement_potential > 15) {
            return 'high';
        } elseif ($improvement_potential > 10) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Analyze content trends
     *
     * @param array $daily_trends Daily trend data
     * @return array Trend analysis summary
     */
    private function analyze_content_trends($daily_trends) {
        if (count($daily_trends) < 2) {
            return ['status' => 'insufficient_data'];
        }

        $first_week = array_slice($daily_trends, 0, 7);
        $last_week = array_slice($daily_trends, -7);

        $first_avg = array_sum(array_column($first_week, 'avg_score')) / count($first_week);
        $last_avg = array_sum(array_column($last_week, 'avg_score')) / count($last_week);

        $trend_change = $last_avg - $first_avg;
        $trend_direction = $trend_change > 1 ? 'improving' : ($trend_change < -1 ? 'declining' : 'stable');

        return [
            'status' => 'analyzed',
            'trend_direction' => $trend_direction,
            'change' => round($trend_change, 1),
            'first_week_avg' => round($first_avg, 1),
            'last_week_avg' => round($last_avg, 1)
        ];
    }

    /**
     * Clear content analysis cache
     */
    public function clear_cache() {
        $this->content_cache = [];
    }
}