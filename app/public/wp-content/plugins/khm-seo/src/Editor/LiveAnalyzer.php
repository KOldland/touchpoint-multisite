<?php
declare(strict_types=1);

namespace KHM_SEO\Editor;

use KHM_SEO\Analysis\AnalysisEngine;

/**
 * LiveAnalyzer - Provides real-time content analysis for WordPress editors
 * 
 * This class performs live SEO analysis as users type content, providing
 * immediate feedback on content optimization opportunities.
 * 
 * @package KHM_SEO\Editor
 * @since 2.0.0
 */
class LiveAnalyzer
{
    /**
     * @var AnalysisEngine The core analysis engine from Phase 1
     */
    private AnalysisEngine $analysis_engine;

    /**
     * @var array Configuration for live analysis
     */
    private array $config;

    /**
     * @var array Cache for recent analysis results
     */
    private array $analysis_cache = [];

    /**
     * @var int Maximum cache size
     */
    private int $max_cache_size = 50;

    /**
     * Initialize the Live Analyzer
     */
    public function __construct()
    {
        $this->analysis_engine = new AnalysisEngine();
        $this->config = $this->get_default_config();
    }

    /**
     * Perform real-time content analysis
     *
     * @param array $content_data Content data including title, content, excerpt, etc.
     * @return array Analysis results with scores and recommendations
     */
    public function analyze(array $content_data): array
    {
        // Validate input data
        $validated_data = $this->validate_content_data($content_data);
        
        if (empty($validated_data)) {
            return $this->get_empty_analysis_result();
        }

        // Check cache for existing results
        $cache_key = $this->generate_cache_key($validated_data);
        if (isset($this->analysis_cache[$cache_key])) {
            return $this->analysis_cache[$cache_key];
        }

        // Perform analysis using Phase 1 Analysis Engine
        $analysis_result = $this->perform_live_analysis($validated_data);

        // Cache the results
        $this->cache_analysis_result($cache_key, $analysis_result);

        return $analysis_result;
    }

    /**
     * Perform the actual content analysis
     *
     * @param array $content_data Validated content data
     * @return array Analysis results
     */
    private function perform_live_analysis(array $content_data): array
    {
        $start_time = microtime(true);

        // Use the Phase 1 Analysis Engine to analyze content
        $engine_result = $this->analysis_engine->analyze_content(
            $content_data['content'],
            $content_data['focus_keyword'] ?? '',
            [
                'title' => $content_data['title'] ?? '',
                'excerpt' => $content_data['excerpt'] ?? '',
                'url' => $content_data['url'] ?? ''
            ]
        );

        $analysis_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        // Format results for live display
        $formatted_result = [
            'overall_score' => $engine_result['overall_score'],
            'detailed_analysis' => $this->format_detailed_analysis($engine_result),
            'real_time_feedback' => $this->generate_real_time_feedback($engine_result),
            'performance' => [
                'analysis_time' => round($analysis_time, 2),
                'timestamp' => current_time('mysql')
            ]
        ];

        return $formatted_result;
    }

    /**
     * Format detailed analysis for real-time display
     *
     * @param array $engine_result Raw analysis engine results
     * @return array Formatted analysis data
     */
    private function format_detailed_analysis(array $engine_result): array
    {
        $analysis = [];

        foreach ($engine_result['analyzer_scores'] as $analyzer => $data) {
            $analysis[$analyzer] = [
                'score' => $data['score'],
                'status' => $this->get_score_status($data['score']),
                'message' => $data['message'] ?? '',
                'details' => $data['details'] ?? [],
                'priority' => $this->calculate_improvement_priority($data['score']),
                'real_time' => true
            ];
        }

        return $analysis;
    }

    /**
     * Generate real-time feedback for immediate display
     *
     * @param array $engine_result Analysis engine results
     * @return array Real-time feedback data
     */
    private function generate_real_time_feedback(array $engine_result): array
    {
        $feedback = [
            'status' => $this->get_overall_status($engine_result['overall_score']),
            'quick_wins' => $this->identify_quick_wins($engine_result),
            'priority_issues' => $this->identify_priority_issues($engine_result),
            'progress_indicators' => $this->generate_progress_indicators($engine_result)
        ];

        return $feedback;
    }

    /**
     * Identify quick optimization wins
     *
     * @param array $engine_result Analysis results
     * @return array Quick win opportunities
     */
    private function identify_quick_wins(array $engine_result): array
    {
        $quick_wins = [];
        
        foreach ($engine_result['analyzer_scores'] as $analyzer => $data) {
            $score = $data['score'];
            
            // Identify analyzers with easy improvements (score 40-70)
            if ($score >= 40 && $score < 70) {
                $quick_wins[] = [
                    'analyzer' => $analyzer,
                    'current_score' => $score,
                    'potential_improvement' => $this->calculate_potential_improvement($analyzer, $score),
                    'action' => $this->get_quick_win_action($analyzer),
                    'effort' => 'low'
                ];
            }
        }

        // Sort by potential improvement
        usort($quick_wins, function($a, $b) {
            return $b['potential_improvement'] <=> $a['potential_improvement'];
        });

        return array_slice($quick_wins, 0, 3); // Return top 3 quick wins
    }

    /**
     * Identify high-priority issues
     *
     * @param array $engine_result Analysis results
     * @return array Priority issues
     */
    private function identify_priority_issues(array $engine_result): array
    {
        $priority_issues = [];

        foreach ($engine_result['analyzer_scores'] as $analyzer => $data) {
            $score = $data['score'];
            
            // Critical issues (score < 40)
            if ($score < 40) {
                $priority_issues[] = [
                    'analyzer' => $analyzer,
                    'score' => $score,
                    'severity' => $score < 20 ? 'critical' : 'high',
                    'impact' => $this->calculate_seo_impact($analyzer),
                    'recommendation' => $this->get_priority_recommendation($analyzer, $score)
                ];
            }
        }

        return $priority_issues;
    }

    /**
     * Generate progress indicators for visual feedback
     *
     * @param array $engine_result Analysis results
     * @return array Progress indicators
     */
    private function generate_progress_indicators(array $engine_result): array
    {
        $total_analyzers = count($engine_result['analyzer_scores']);
        $good_scores = 0; // Scores >= 70
        $ok_scores = 0;   // Scores 40-69
        $poor_scores = 0; // Scores < 40

        foreach ($engine_result['analyzer_scores'] as $data) {
            $score = $data['score'];
            if ($score >= 70) {
                $good_scores++;
            } elseif ($score >= 40) {
                $ok_scores++;
            } else {
                $poor_scores++;
            }
        }

        return [
            'total_checks' => $total_analyzers,
            'passed_checks' => $good_scores,
            'warning_checks' => $ok_scores,
            'failed_checks' => $poor_scores,
            'completion_percentage' => round(($good_scores / $total_analyzers) * 100),
            'overall_health' => $this->calculate_content_health($good_scores, $ok_scores, $poor_scores)
        ];
    }

    /**
     * Validate and sanitize content data
     *
     * @param array $content_data Raw content data
     * @return array Validated content data
     */
    private function validate_content_data(array $content_data): array
    {
        $validated = [];

        // Content validation
        if (isset($content_data['content'])) {
            $content = sanitize_textarea_field($content_data['content']);
            if (strlen($content) >= $this->config['min_content_length']) {
                $validated['content'] = $content;
            }
        }

        // Title validation
        if (isset($content_data['title'])) {
            $validated['title'] = sanitize_text_field($content_data['title']);
        }

        // Excerpt validation
        if (isset($content_data['excerpt'])) {
            $validated['excerpt'] = sanitize_textarea_field($content_data['excerpt']);
        }

        // Focus keyword validation
        if (isset($content_data['focus_keyword'])) {
            $validated['focus_keyword'] = sanitize_text_field($content_data['focus_keyword']);
        }

        // URL validation
        if (isset($content_data['url'])) {
            $validated['url'] = esc_url_raw($content_data['url']);
        }

        return $validated;
    }

    /**
     * Generate cache key for analysis results
     *
     * @param array $content_data Content data
     * @return string Cache key
     */
    private function generate_cache_key(array $content_data): string
    {
        return md5(serialize($content_data));
    }

    /**
     * Cache analysis results
     *
     * @param string $cache_key Cache key
     * @param array $result Analysis result
     * @return void
     */
    private function cache_analysis_result(string $cache_key, array $result): void
    {
        // Implement simple LRU cache
        if (count($this->analysis_cache) >= $this->max_cache_size) {
            // Remove oldest entry
            array_shift($this->analysis_cache);
        }

        $this->analysis_cache[$cache_key] = $result;
    }

    /**
     * Get score status (excellent, good, needs_improvement)
     *
     * @param int $score Score value
     * @return string Status
     */
    private function get_score_status(int $score): string
    {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        return 'needs_improvement';
    }

    /**
     * Get overall content status
     *
     * @param int $overall_score Overall score
     * @return string Status
     */
    private function get_overall_status(int $overall_score): string
    {
        if ($overall_score >= 80) return 'optimized';
        if ($overall_score >= 60) return 'good';
        if ($overall_score >= 40) return 'needs_work';
        return 'poor';
    }

    /**
     * Calculate improvement priority
     *
     * @param int $score Current score
     * @return string Priority level
     */
    private function calculate_improvement_priority(int $score): string
    {
        if ($score < 30) return 'critical';
        if ($score < 50) return 'high';
        if ($score < 70) return 'medium';
        return 'low';
    }

    /**
     * Calculate potential improvement for quick wins
     *
     * @param string $analyzer Analyzer name
     * @param int $current_score Current score
     * @return int Potential improvement points
     */
    private function calculate_potential_improvement(string $analyzer, int $current_score): int
    {
        // Different analyzers have different improvement potentials
        $improvement_factors = [
            'keyword_density' => 25,
            'meta_description' => 30,
            'title_analysis' => 20,
            'heading_structure' => 15,
            'image_alt_tags' => 20,
            'internal_links' => 15,
            'readability' => 10,
            'content_length' => 25
        ];

        $factor = $improvement_factors[$analyzer] ?? 15;
        return min($factor, 100 - $current_score);
    }

    /**
     * Get quick win action for analyzer
     *
     * @param string $analyzer Analyzer name
     * @return string Quick action suggestion
     */
    private function get_quick_win_action(string $analyzer): string
    {
        $actions = [
            'keyword_density' => 'Optimize keyword usage in content',
            'meta_description' => 'Write compelling meta description',
            'title_analysis' => 'Improve title for SEO and readability',
            'heading_structure' => 'Add proper heading tags (H1, H2, H3)',
            'image_alt_tags' => 'Add alt text to images',
            'internal_links' => 'Add relevant internal links',
            'readability' => 'Simplify sentences and paragraphs',
            'content_length' => 'Expand content with valuable information'
        ];

        return $actions[$analyzer] ?? 'Optimize this element';
    }

    /**
     * Calculate SEO impact of analyzer
     *
     * @param string $analyzer Analyzer name
     * @return string Impact level
     */
    private function calculate_seo_impact(string $analyzer): string
    {
        $high_impact = ['title_analysis', 'meta_description', 'keyword_density'];
        $medium_impact = ['heading_structure', 'content_length', 'readability'];
        
        if (in_array($analyzer, $high_impact)) return 'high';
        if (in_array($analyzer, $medium_impact)) return 'medium';
        return 'low';
    }

    /**
     * Get priority recommendation for analyzer
     *
     * @param string $analyzer Analyzer name
     * @param int $score Current score
     * @return string Detailed recommendation
     */
    private function get_priority_recommendation(string $analyzer, int $score): string
    {
        // Return specific recommendations based on analyzer and score
        $recommendations = [
            'keyword_density' => $score < 20 ? 'Add your focus keyword to the content' : 'Improve keyword distribution',
            'meta_description' => 'Write a compelling meta description under 160 characters',
            'title_analysis' => $score < 20 ? 'Add your focus keyword to the title' : 'Optimize title length and readability',
            'heading_structure' => 'Structure your content with proper heading tags',
            'image_alt_tags' => 'Add descriptive alt text to all images',
            'internal_links' => 'Add 2-3 relevant internal links',
            'readability' => 'Use shorter sentences and simpler words',
            'content_length' => 'Expand content to at least 300 words'
        ];

        return $recommendations[$analyzer] ?? 'Improve this SEO element';
    }

    /**
     * Calculate overall content health
     *
     * @param int $good_scores Number of good scores
     * @param int $ok_scores Number of OK scores
     * @param int $poor_scores Number of poor scores
     * @return string Health status
     */
    private function calculate_content_health(int $good_scores, int $ok_scores, int $poor_scores): string
    {
        $total = $good_scores + $ok_scores + $poor_scores;
        if ($total === 0) return 'unknown';
        
        $good_percentage = ($good_scores / $total) * 100;
        $poor_percentage = ($poor_scores / $total) * 100;
        
        if ($poor_percentage > 50) return 'critical';
        if ($good_percentage >= 70) return 'excellent';
        if ($good_percentage >= 50) return 'good';
        return 'needs_improvement';
    }

    /**
     * Get empty analysis result for invalid input
     *
     * @return array Empty analysis structure
     */
    private function get_empty_analysis_result(): array
    {
        return [
            'overall_score' => 0,
            'detailed_analysis' => [],
            'real_time_feedback' => [
                'status' => 'insufficient_content',
                'quick_wins' => [],
                'priority_issues' => [],
                'progress_indicators' => [
                    'total_checks' => 0,
                    'passed_checks' => 0,
                    'warning_checks' => 0,
                    'failed_checks' => 0,
                    'completion_percentage' => 0,
                    'overall_health' => 'insufficient_content'
                ]
            ],
            'performance' => [
                'analysis_time' => 0,
                'timestamp' => current_time('mysql')
            ]
        ];
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration
     */
    private function get_default_config(): array
    {
        return [
            'min_content_length' => 50,
            'cache_enabled' => true,
            'max_cache_size' => 50,
            'performance_tracking' => true
        ];
    }

    /**
     * Clear analysis cache
     *
     * @return void
     */
    public function clear_cache(): void
    {
        $this->analysis_cache = [];
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_cache_stats(): array
    {
        return [
            'size' => count($this->analysis_cache),
            'max_size' => $this->max_cache_size,
            'usage_percentage' => (count($this->analysis_cache) / $this->max_cache_size) * 100
        ];
    }
}
