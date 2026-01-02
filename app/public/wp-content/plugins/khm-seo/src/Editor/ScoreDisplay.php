<?php
declare(strict_types=1);

namespace KHM_SEO\Editor;

/**
 * ScoreDisplay - Handles visual display of SEO scores in the editor
 * 
 * This class manages the visual representation of SEO analysis scores,
 * providing intuitive and actionable feedback to content creators.
 * 
 * @package KHM_SEO\Editor
 * @since 2.0.0
 */
class ScoreDisplay
{
    /**
     * @var array Score thresholds for different status levels
     */
    private array $score_thresholds = [
        'excellent' => 80,
        'good' => 60,
        'needs_improvement' => 40,
        'poor' => 0
    ];

    /**
     * @var array Color schemes for different score levels
     */
    private array $score_colors = [
        'excellent' => ['bg' => '#46b450', 'text' => '#ffffff'],
        'good' => ['bg' => '#00a32a', 'text' => '#ffffff'],
        'needs_improvement' => ['bg' => '#ffb900', 'text' => '#000000'],
        'poor' => ['bg' => '#dc3232', 'text' => '#ffffff']
    ];

    /**
     * Generate score display HTML
     *
     * @param array $analysis_result Analysis results from LiveAnalyzer
     * @return string HTML for score display
     */
    public function generate_score_display(array $analysis_result): string
    {
        $overall_score = $analysis_result['overall_score'] ?? 0;
        $detailed_analysis = $analysis_result['detailed_analysis'] ?? [];
        $real_time_feedback = $analysis_result['real_time_feedback'] ?? [];

        $html = '<div class="khm-seo-score-display">';
        
        // Overall score circle
        $html .= $this->generate_overall_score_circle($overall_score);
        
        // Progress indicators
        if (!empty($real_time_feedback['progress_indicators'])) {
            $html .= $this->generate_progress_indicators($real_time_feedback['progress_indicators']);
        }
        
        // Individual analyzer scores
        $html .= $this->generate_analyzer_scores($detailed_analysis);
        
        // Real-time status
        $html .= $this->generate_real_time_status($real_time_feedback);
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate overall score circle display
     *
     * @param int $score Overall SEO score
     * @return string HTML for score circle
     */
    private function generate_overall_score_circle(int $score): string
    {
        $status = $this->get_score_status($score);
        $colors = $this->score_colors[$status];
        
        $html = '<div class="khm-seo-overall-score">';
        $html .= '<div class="khm-seo-score-circle" style="background-color: ' . $colors['bg'] . '; color: ' . $colors['text'] . ';">';
        $html .= '<div class="score-value">' . $score . '</div>';
        $html .= '<div class="score-label">SEO</div>';
        $html .= '</div>';
        $html .= '<div class="score-description">';
        $html .= '<h4>' . $this->get_score_title($status) . '</h4>';
        $html .= '<p>' . $this->get_score_description($status, $score) . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate progress indicators display
     *
     * @param array $progress_data Progress indicator data
     * @return string HTML for progress indicators
     */
    private function generate_progress_indicators(array $progress_data): string
    {
        $html = '<div class="khm-seo-progress-indicators">';
        
        // Progress bar
        $completion = $progress_data['completion_percentage'] ?? 0;
        $html .= '<div class="progress-section">';
        $html .= '<div class="progress-label">Optimization Progress: ' . $completion . '%</div>';
        $html .= '<div class="progress-bar-container">';
        $html .= '<div class="progress-bar" style="width: ' . $completion . '%"></div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Check counters
        $html .= '<div class="check-counters">';
        $html .= '<div class="check-item passed">';
        $html .= '<span class="check-icon">✓</span>';
        $html .= '<span class="check-count">' . ($progress_data['passed_checks'] ?? 0) . '</span>';
        $html .= '<span class="check-label">Passed</span>';
        $html .= '</div>';
        
        $html .= '<div class="check-item warning">';
        $html .= '<span class="check-icon">⚠</span>';
        $html .= '<span class="check-count">' . ($progress_data['warning_checks'] ?? 0) . '</span>';
        $html .= '<span class="check-label">Warnings</span>';
        $html .= '</div>';
        
        $html .= '<div class="check-item failed">';
        $html .= '<span class="check-icon">✗</span>';
        $html .= '<span class="check-count">' . ($progress_data['failed_checks'] ?? 0) . '</span>';
        $html .= '<span class="check-label">Issues</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate individual analyzer scores display
     *
     * @param array $detailed_analysis Detailed analysis results
     * @return string HTML for analyzer scores
     */
    private function generate_analyzer_scores(array $detailed_analysis): string
    {
        if (empty($detailed_analysis)) {
            return '<div class="khm-seo-no-analysis">No analysis data available</div>';
        }

        $html = '<div class="khm-seo-analyzer-scores">';
        $html .= '<h4>SEO Analysis Details</h4>';
        $html .= '<div class="analyzer-grid">';

        foreach ($detailed_analysis as $analyzer => $data) {
            $html .= $this->generate_analyzer_item($analyzer, $data);
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate individual analyzer item
     *
     * @param string $analyzer Analyzer name
     * @param array $data Analyzer data
     * @return string HTML for analyzer item
     */
    private function generate_analyzer_item(string $analyzer, array $data): string
    {
        $score = $data['score'] ?? 0;
        $status = $data['status'] ?? $this->get_score_status($score);
        $message = $data['message'] ?? '';
        $priority = $data['priority'] ?? 'low';

        $html = '<div class="analyzer-item analyzer-' . $status . ' priority-' . $priority . '">';
        
        // Analyzer header
        $html .= '<div class="analyzer-header">';
        $html .= '<div class="analyzer-name">' . $this->get_analyzer_display_name($analyzer) . '</div>';
        $html .= '<div class="analyzer-score-badge" style="background-color: ' . $this->get_status_color($status) . '">';
        $html .= $score;
        $html .= '</div>';
        $html .= '</div>';
        
        // Status indicator
        $html .= '<div class="analyzer-status">';
        $html .= '<span class="status-icon">' . $this->get_status_icon($status) . '</span>';
        $html .= '<span class="status-text">' . $this->get_status_text($status) . '</span>';
        $html .= '</div>';
        
        // Message
        if (!empty($message)) {
            $html .= '<div class="analyzer-message">' . esc_html($message) . '</div>';
        }
        
        // Priority indicator for issues
        if (in_array($priority, ['high', 'critical'])) {
            $html .= '<div class="priority-indicator priority-' . $priority . '">';
            $html .= $this->get_priority_text($priority);
            $html .= '</div>';
        }
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate real-time status display
     *
     * @param array $real_time_feedback Real-time feedback data
     * @return string HTML for real-time status
     */
    private function generate_real_time_status(array $real_time_feedback): string
    {
        $status = $real_time_feedback['status'] ?? 'unknown';
        
        $html = '<div class="khm-seo-real-time-status status-' . $status . '">';
        $html .= '<div class="status-message">';
        $html .= $this->get_real_time_status_message($status);
        $html .= '</div>';
        
        // Performance indicator
        if (!empty($real_time_feedback['performance']['analysis_time'])) {
            $analysis_time = $real_time_feedback['performance']['analysis_time'];
            $html .= '<div class="performance-indicator">';
            $html .= 'Analyzed in ' . $analysis_time . 'ms';
            $html .= '</div>';
        }
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Get score status based on value
     *
     * @param int $score Score value
     * @return string Status key
     */
    private function get_score_status(int $score): string
    {
        foreach ($this->score_thresholds as $status => $threshold) {
            if ($score >= $threshold) {
                return $status;
            }
        }
        return 'poor';
    }

    /**
     * Get score title for display
     *
     * @param string $status Score status
     * @return string Display title
     */
    private function get_score_title(string $status): string
    {
        $titles = [
            'excellent' => 'Excellent SEO',
            'good' => 'Good SEO',
            'needs_improvement' => 'Needs Improvement',
            'poor' => 'Poor SEO'
        ];

        return $titles[$status] ?? 'Unknown';
    }

    /**
     * Get score description
     *
     * @param string $status Score status
     * @param int $score Score value
     * @return string Description text
     */
    private function get_score_description(string $status, int $score): string
    {
        $descriptions = [
            'excellent' => 'Your content is well optimized for search engines!',
            'good' => 'Your content is good but has room for improvement.',
            'needs_improvement' => 'Your content needs optimization to perform better.',
            'poor' => 'Your content requires significant SEO improvements.'
        ];

        return $descriptions[$status] ?? 'Unable to determine content quality.';
    }

    /**
     * Get analyzer display name
     *
     * @param string $analyzer Analyzer internal name
     * @return string Human-readable name
     */
    private function get_analyzer_display_name(string $analyzer): string
    {
        $display_names = [
            'keyword_density' => 'Keyword Usage',
            'meta_description' => 'Meta Description',
            'title_analysis' => 'Title Optimization',
            'heading_structure' => 'Heading Structure',
            'image_alt_tags' => 'Image Alt Tags',
            'internal_links' => 'Internal Linking',
            'readability' => 'Content Readability',
            'content_length' => 'Content Length'
        ];

        return $display_names[$analyzer] ?? ucfirst(str_replace('_', ' ', $analyzer));
    }

    /**
     * Get status color
     *
     * @param string $status Status key
     * @return string Color hex code
     */
    private function get_status_color(string $status): string
    {
        $colors = [
            'excellent' => '#46b450',
            'good' => '#00a32a',
            'needs_improvement' => '#ffb900',
            'poor' => '#dc3232'
        ];

        return $colors[$status] ?? '#666666';
    }

    /**
     * Get status icon
     *
     * @param string $status Status key
     * @return string Icon character/symbol
     */
    private function get_status_icon(string $status): string
    {
        $icons = [
            'excellent' => '✓',
            'good' => '✓',
            'needs_improvement' => '⚠',
            'poor' => '✗'
        ];

        return $icons[$status] ?? '?';
    }

    /**
     * Get status text
     *
     * @param string $status Status key
     * @return string Status text
     */
    private function get_status_text(string $status): string
    {
        $texts = [
            'excellent' => 'Excellent',
            'good' => 'Good',
            'needs_improvement' => 'Needs Work',
            'poor' => 'Poor'
        ];

        return $texts[$status] ?? 'Unknown';
    }

    /**
     * Get priority text for display
     *
     * @param string $priority Priority level
     * @return string Priority text
     */
    private function get_priority_text(string $priority): string
    {
        $texts = [
            'critical' => 'Critical Issue',
            'high' => 'High Priority',
            'medium' => 'Medium Priority',
            'low' => 'Low Priority'
        ];

        return $texts[$priority] ?? '';
    }

    /**
     * Get real-time status message
     *
     * @param string $status Real-time status
     * @return string Status message
     */
    private function get_real_time_status_message(string $status): string
    {
        $messages = [
            'optimized' => '✓ Content is well optimized',
            'good' => '👍 Content is in good shape',
            'needs_work' => '⚠ Content needs optimization',
            'poor' => '⚠ Content requires significant improvement',
            'insufficient_content' => '📝 Add more content to analyze',
            'analyzing' => '🔄 Analyzing content...',
            'unknown' => '❓ Analysis status unknown'
        ];

        return $messages[$status] ?? 'Status unknown';
    }

    /**
     * Generate CSS styles for score display (can be inlined or external)
     *
     * @return string CSS styles
     */
    public function get_score_display_css(): string
    {
        return '
        .khm-seo-score-display {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #ffffff;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }

        .khm-seo-overall-score {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e1e1e1;
        }

        .khm-seo-score-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .score-value {
            font-size: 24px;
            line-height: 1;
        }

        .score-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .score-description h4 {
            margin: 0 0 5px 0;
            font-size: 18px;
            color: #333;
        }

        .score-description p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .khm-seo-progress-indicators {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e1e1e1;
        }

        .progress-section {
            margin-bottom: 15px;
        }

        .progress-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .progress-bar-container {
            background: #e1e1e1;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #dc3232 0%, #ffb900 50%, #46b450 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .check-counters {
            display: flex;
            gap: 20px;
        }

        .check-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .check-icon {
            font-size: 16px;
        }

        .check-item.passed .check-icon { color: #46b450; }
        .check-item.warning .check-icon { color: #ffb900; }
        .check-item.failed .check-icon { color: #dc3232; }

        .check-count {
            font-weight: bold;
            font-size: 16px;
        }

        .check-label {
            font-size: 14px;
            color: #666;
        }

        .khm-seo-analyzer-scores h4 {
            margin: 0 0 15px 0;
            color: #333;
        }

        .analyzer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .analyzer-item {
            border: 1px solid #e1e1e1;
            border-radius: 6px;
            padding: 15px;
            background: #fafafa;
            transition: all 0.2s ease;
        }

        .analyzer-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .analyzer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .analyzer-name {
            font-weight: 600;
            color: #333;
        }

        .analyzer-score-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            min-width: 30px;
            text-align: center;
        }

        .analyzer-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .status-icon {
            font-size: 14px;
        }

        .status-text {
            font-size: 14px;
            font-weight: 500;
        }

        .analyzer-message {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }

        .priority-indicator {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-critical { color: #dc3232; }
        .priority-high { color: #ff6900; }

        .khm-seo-real-time-status {
            margin-top: 15px;
            padding: 12px;
            border-radius: 6px;
            background: #f0f0f1;
            border-left: 4px solid #0073aa;
        }

        .status-message {
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }

        .performance-indicator {
            font-size: 12px;
            color: #666;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .khm-seo-overall-score {
                flex-direction: column;
                text-align: center;
            }
            
            .khm-seo-score-circle {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .analyzer-grid {
                grid-template-columns: 1fr;
            }
            
            .check-counters {
                justify-content: space-around;
            }
        }
        ';
    }

    /**
     * Generate JavaScript for interactive score display
     *
     * @return string JavaScript code
     */
    public function get_score_display_js(): string
    {
        return '
        (function() {
            // Animate score circle on load
            function animateScoreCircle(element, targetScore) {
                let currentScore = 0;
                const increment = targetScore / 30; // Animation duration ~500ms
                
                const timer = setInterval(() => {
                    currentScore += increment;
                    if (currentScore >= targetScore) {
                        currentScore = targetScore;
                        clearInterval(timer);
                    }
                    element.textContent = Math.round(currentScore);
                }, 16);
            }
            
            // Initialize score display animations
            document.addEventListener("DOMContentLoaded", function() {
                const scoreElements = document.querySelectorAll(".score-value");
                scoreElements.forEach(element => {
                    const targetScore = parseInt(element.textContent);
                    element.textContent = "0";
                    setTimeout(() => animateScoreCircle(element, targetScore), 200);
                });
            });
            
            // Add tooltips to analyzer items
            document.querySelectorAll(".analyzer-item").forEach(item => {
                item.addEventListener("mouseenter", function() {
                    // Could add detailed tooltips here
                });
            });
        })();
        ';
    }
}
