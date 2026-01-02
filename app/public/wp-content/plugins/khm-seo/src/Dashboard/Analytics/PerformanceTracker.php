<?php
/**
 * Performance Tracker - SEO performance monitoring and analytics
 * 
 * Tracks and analyzes SEO performance metrics over time,
 * providing insights into score trends and improvement patterns.
 * 
 * @package KHM_SEO\Dashboard\Analytics
 * @since 2.1.0
 */

namespace KHM_SEO\Dashboard\Analytics;

/**
 * Performance Tracker Class
 */
class PerformanceTracker {
    /**
     * @var array Performance metrics configuration
     */
    private $metrics_config;

    /**
     * @var array Cached performance data
     */
    private $performance_cache;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_metrics_config();
        $this->performance_cache = [];
    }

    /**
     * Initialize metrics configuration
     */
    private function init_metrics_config() {
        $this->metrics_config = [
            'score' => [
                'label' => 'SEO Score',
                'color' => '#2271b1',
                'target' => 85
            ],
            'issues' => [
                'label' => 'Issues Found',
                'color' => '#d63638',
                'target' => 0,
                'inverted' => true // Lower is better
            ],
            'improvements' => [
                'label' => 'Improvements Made',
                'color' => '#00a32a',
                'target' => null // No specific target
            ],
            'page_count' => [
                'label' => 'Pages Analyzed',
                'color' => '#72aee6',
                'target' => null
            ]
        ];
    }

    /**
     * Get performance data for dashboard charts
     *
     * @param int $days Number of days to retrieve data for
     * @return array Performance data formatted for charts
     */
    public function get_performance_data($days = 30) {
        $cache_key = "performance_data_{$days}";
        
        if (isset($this->performance_cache[$cache_key])) {
            return $this->performance_cache[$cache_key];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        // Get daily performance data
        $daily_data = $this->get_daily_performance_data($days);
        
        // Get comparative metrics
        $current_period = $this->get_period_metrics($days);
        $previous_period = $this->get_period_metrics($days, $days);
        
        // Calculate trends
        $trends = $this->calculate_performance_trends($current_period, $previous_period);
        
        // Prepare chart data
        $chart_data = $this->prepare_chart_data($daily_data, $days);
        
        $performance_data = [
            'chart_data' => $chart_data,
            'current_period' => $current_period,
            'previous_period' => $previous_period,
            'trends' => $trends,
            'summary' => $this->generate_performance_summary($current_period, $trends),
            'recommendations' => $this->generate_performance_recommendations($trends)
        ];

        $this->performance_cache[$cache_key] = $performance_data;
        
        return $performance_data;
    }

    /**
     * Get daily performance data from database
     *
     * @param int $days Number of days
     * @return array Daily performance metrics
     */
    private function get_daily_performance_data($days) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(analyzed_at) as analysis_date,
                COUNT(*) as analyses_count,
                AVG(JSON_EXTRACT(analysis_data, '$.overall_score')) as avg_score,
                SUM(CASE WHEN JSON_EXTRACT(analysis_data, '$.overall_score') >= 80 THEN 1 ELSE 0 END) as optimized_count,
                COUNT(DISTINCT post_id) as unique_pages
             FROM {$table_name} 
             WHERE analyzed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(analyzed_at)
             ORDER BY analysis_date ASC",
            $days
        ), ARRAY_A);

        // Fill in missing dates with zeros
        $daily_data = [];
        $start_date = strtotime("-{$days} days");
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days", $start_date));
            $daily_data[$date] = [
                'date' => $date,
                'analyses_count' => 0,
                'avg_score' => 0,
                'optimized_count' => 0,
                'unique_pages' => 0,
                'issues_count' => 0,
                'improvements_count' => 0
            ];
        }

        // Merge actual data
        foreach ($results as $result) {
            if (isset($daily_data[$result['analysis_date']])) {
                $daily_data[$result['analysis_date']] = array_merge(
                    $daily_data[$result['analysis_date']],
                    [
                        'analyses_count' => (int) $result['analyses_count'],
                        'avg_score' => round((float) $result['avg_score'], 1),
                        'optimized_count' => (int) $result['optimized_count'],
                        'unique_pages' => (int) $result['unique_pages']
                    ]
                );
            }
        }

        return array_values($daily_data);
    }

    /**
     * Get period metrics
     *
     * @param int $days Number of days in period
     * @param int $offset Offset in days from current date
     * @return array Period metrics
     */
    private function get_period_metrics($days, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $start_date = date('Y-m-d', strtotime("-" . ($days + $offset) . " days"));
        $end_date = date('Y-m-d', strtotime("-{$offset} days"));

        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_analyses,
                AVG(JSON_EXTRACT(analysis_data, '$.overall_score')) as avg_score,
                COUNT(DISTINCT post_id) as unique_pages,
                SUM(CASE WHEN JSON_EXTRACT(analysis_data, '$.overall_score') >= 80 THEN 1 ELSE 0 END) as optimized_pages,
                MIN(JSON_EXTRACT(analysis_data, '$.overall_score')) as min_score,
                MAX(JSON_EXTRACT(analysis_data, '$.overall_score')) as max_score
             FROM {$table_name} 
             WHERE analyzed_at >= %s AND analyzed_at < %s",
            $start_date,
            $end_date
        ), ARRAY_A);

        return [
            'total_analyses' => (int) ($metrics['total_analyses'] ?? 0),
            'avg_score' => round((float) ($metrics['avg_score'] ?? 0), 1),
            'unique_pages' => (int) ($metrics['unique_pages'] ?? 0),
            'optimized_pages' => (int) ($metrics['optimized_pages'] ?? 0),
            'min_score' => round((float) ($metrics['min_score'] ?? 0), 1),
            'max_score' => round((float) ($metrics['max_score'] ?? 0), 1),
            'optimization_rate' => $metrics['unique_pages'] > 0 
                ? round(($metrics['optimized_pages'] / $metrics['unique_pages']) * 100, 1)
                : 0
        ];
    }

    /**
     * Calculate performance trends
     *
     * @param array $current_period Current period metrics
     * @param array $previous_period Previous period metrics
     * @return array Trend calculations
     */
    private function calculate_performance_trends($current_period, $previous_period) {
        $trends = [];

        $metrics_to_compare = ['avg_score', 'unique_pages', 'optimized_pages', 'optimization_rate'];

        foreach ($metrics_to_compare as $metric) {
            $current = $current_period[$metric] ?? 0;
            $previous = $previous_period[$metric] ?? 0;

            if ($previous > 0) {
                $change = (($current - $previous) / $previous) * 100;
            } else {
                $change = $current > 0 ? 100 : 0;
            }

            $trends[$metric] = [
                'current' => $current,
                'previous' => $previous,
                'change_percent' => round($change, 1),
                'change_absolute' => round($current - $previous, 1),
                'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable')
            ];
        }

        return $trends;
    }

    /**
     * Prepare data for chart visualization
     *
     * @param array $daily_data Daily performance data
     * @param int $days Number of days
     * @return array Chart-ready data
     */
    private function prepare_chart_data($daily_data, $days) {
        $labels = [];
        $datasets = [];

        // Prepare labels (dates)
        foreach ($daily_data as $day) {
            $labels[] = date('M j', strtotime($day['date']));
        }

        // Prepare datasets for each metric
        foreach ($this->metrics_config as $metric => $config) {
            $data = [];
            
            foreach ($daily_data as $day) {
                switch ($metric) {
                    case 'score':
                        $data[] = $day['avg_score'];
                        break;
                    case 'issues':
                        $data[] = $day['issues_count'];
                        break;
                    case 'improvements':
                        $data[] = $day['improvements_count'];
                        break;
                    case 'page_count':
                        $data[] = $day['unique_pages'];
                        break;
                    default:
                        $data[] = 0;
                }
            }

            $datasets[] = [
                'label' => $config['label'],
                'data' => $data,
                'borderColor' => $config['color'],
                'backgroundColor' => $config['color'] . '20', // Add transparency
                'fill' => false,
                'tension' => 0.3
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets
        ];
    }

    /**
     * Generate performance summary
     *
     * @param array $current_period Current period metrics
     * @param array $trends Trend data
     * @return array Performance summary
     */
    private function generate_performance_summary($current_period, $trends) {
        $summary = [
            'overall_health' => $this->calculate_overall_health($current_period),
            'key_insights' => [],
            'achievements' => [],
            'concerns' => []
        ];

        // Generate key insights
        if ($trends['avg_score']['direction'] === 'up') {
            $summary['key_insights'][] = sprintf(
                'SEO score improved by %s points (%s%%) compared to the previous period',
                $trends['avg_score']['change_absolute'],
                $trends['avg_score']['change_percent']
            );
        }

        if ($trends['optimized_pages']['direction'] === 'up') {
            $summary['achievements'][] = sprintf(
                '%d more pages reached optimization targets',
                $trends['optimized_pages']['change_absolute']
            );
        }

        if ($current_period['avg_score'] < 60) {
            $summary['concerns'][] = 'Overall site score is below recommended threshold of 60';
        }

        if ($current_period['optimization_rate'] < 50) {
            $summary['concerns'][] = 'Less than 50% of analyzed pages meet optimization targets';
        }

        return $summary;
    }

    /**
     * Calculate overall health score
     *
     * @param array $current_period Current period metrics
     * @return array Health score and status
     */
    private function calculate_overall_health($current_period) {
        $health_score = 0;
        $factors = [];

        // Factor 1: Average SEO score (40% weight)
        $score_factor = min(100, ($current_period['avg_score'] / 85) * 100);
        $health_score += $score_factor * 0.4;
        $factors['seo_score'] = $score_factor;

        // Factor 2: Optimization rate (35% weight)
        $optimization_factor = $current_period['optimization_rate'];
        $health_score += $optimization_factor * 0.35;
        $factors['optimization_rate'] = $optimization_factor;

        // Factor 3: Content coverage (25% weight)
        $total_posts = wp_count_posts()->publish ?? 1;
        $coverage_rate = min(100, ($current_period['unique_pages'] / $total_posts) * 100);
        $health_score += $coverage_rate * 0.25;
        $factors['coverage'] = $coverage_rate;

        $health_score = round($health_score);

        // Determine status
        $status = 'poor';
        if ($health_score >= 85) $status = 'excellent';
        elseif ($health_score >= 70) $status = 'good';
        elseif ($health_score >= 50) $status = 'fair';

        return [
            'score' => $health_score,
            'status' => $status,
            'factors' => $factors
        ];
    }

    /**
     * Generate performance recommendations
     *
     * @param array $trends Trend data
     * @return array Performance recommendations
     */
    private function generate_performance_recommendations($trends) {
        $recommendations = [];

        // Score-based recommendations
        if ($trends['avg_score']['current'] < 70) {
            $recommendations[] = [
                'type' => 'improvement',
                'priority' => 'high',
                'title' => 'Focus on Core SEO Issues',
                'description' => 'Your average SEO score suggests fundamental issues need attention',
                'actions' => [
                    'Review and fix meta descriptions',
                    'Optimize title tags',
                    'Improve content structure with proper headings'
                ]
            ];
        }

        // Trend-based recommendations
        if ($trends['avg_score']['direction'] === 'down') {
            $recommendations[] = [
                'type' => 'alert',
                'priority' => 'medium',
                'title' => 'Declining SEO Performance',
                'description' => sprintf('SEO score has decreased by %s%% recently', abs($trends['avg_score']['change_percent'])),
                'actions' => [
                    'Audit recent content changes',
                    'Check for broken links or technical issues',
                    'Review optimization strategies'
                ]
            ];
        }

        // Coverage recommendations
        if ($trends['unique_pages']['current'] < wp_count_posts()->publish * 0.5) {
            $recommendations[] = [
                'type' => 'opportunity',
                'priority' => 'medium',
                'title' => 'Expand SEO Analysis Coverage',
                'description' => 'Many pages haven\'t been analyzed yet',
                'actions' => [
                    'Run analysis on remaining pages',
                    'Prioritize high-traffic content',
                    'Set up automated analysis schedules'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Get performance comparison data
     *
     * @param array $periods Array of period configurations
     * @return array Comparison data
     */
    public function get_performance_comparison($periods = null) {
        if ($periods === null) {
            $periods = [
                ['days' => 7, 'label' => 'Last 7 days'],
                ['days' => 30, 'label' => 'Last 30 days'],
                ['days' => 90, 'label' => 'Last 90 days']
            ];
        }

        $comparison_data = [];

        foreach ($periods as $period) {
            $metrics = $this->get_period_metrics($period['days']);
            $comparison_data[] = [
                'period' => $period['label'],
                'metrics' => $metrics
            ];
        }

        return $comparison_data;
    }

    /**
     * Get top performing pages
     *
     * @param int $limit Number of pages to return
     * @return array Top performing pages
     */
    public function get_top_performing_pages($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ar.post_id,
                p.post_title,
                p.post_type,
                JSON_EXTRACT(ar.analysis_data, '$.overall_score') as score,
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
            return [
                'post_id' => $result['post_id'],
                'title' => $result['post_title'],
                'post_type' => ucfirst($result['post_type']),
                'score' => round((float) $result['score'], 1),
                'analyzed_at' => strtotime($result['analyzed_at']),
                'edit_link' => get_edit_post_link($result['post_id'])
            ];
        }, $results);
    }

    /**
     * Get pages needing attention
     *
     * @param int $limit Number of pages to return
     * @return array Pages with low scores or issues
     */
    public function get_pages_needing_attention($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ar.post_id,
                p.post_title,
                p.post_type,
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
             AND JSON_EXTRACT(ar.analysis_data, '$.overall_score') < 60
             ORDER BY JSON_EXTRACT(ar.analysis_data, '$.overall_score') ASC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return array_map(function($result) {
            $analysis_data = json_decode($result['analysis_data'], true);
            $main_issues = $this->identify_main_issues($analysis_data);
            
            return [
                'post_id' => $result['post_id'],
                'title' => $result['post_title'],
                'post_type' => ucfirst($result['post_type']),
                'score' => round((float) $result['score'], 1),
                'main_issues' => $main_issues,
                'analyzed_at' => strtotime($result['analyzed_at']),
                'edit_link' => get_edit_post_link($result['post_id'])
            ];
        }, $results);
    }

    /**
     * Identify main issues from analysis data
     *
     * @param array $analysis_data Analysis results
     * @return array Main issues to fix
     */
    private function identify_main_issues($analysis_data) {
        $issues = [];

        if (isset($analysis_data['detailed_analysis'])) {
            foreach ($analysis_data['detailed_analysis'] as $analyzer => $data) {
                if ($data['score'] < 50) {
                    $issues[] = [
                        'analyzer' => $analyzer,
                        'score' => $data['score'],
                        'title' => ucwords(str_replace('_', ' ', $analyzer))
                    ];
                }
            }
        }

        // Sort by lowest score first
        usort($issues, function($a, $b) {
            return $a['score'] <=> $b['score'];
        });

        return array_slice($issues, 0, 3); // Return top 3 issues
    }

    /**
     * Clear performance cache
     */
    public function clear_cache() {
        $this->performance_cache = [];
        
        // Also clear WordPress transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_khm_seo_performance_%' 
             OR option_name LIKE '_transient_timeout_khm_seo_performance_%'"
        );
    }
}