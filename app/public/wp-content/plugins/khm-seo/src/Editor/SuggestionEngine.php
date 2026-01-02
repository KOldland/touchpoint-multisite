<?php
declare(strict_types=1);

namespace KHM_SEO\Editor;

/**
 * SuggestionEngine - Generates contextual SEO optimization recommendations
 * 
 * This class analyzes content performance and generates actionable
 * suggestions for improving SEO scores in real-time.
 * 
 * @package KHM_SEO\Editor
 * @since 2.0.0
 */
class SuggestionEngine
{
    /**
     * @var array Suggestion templates by analyzer type
     */
    private array $suggestion_templates;

    /**
     * @var array Priority weights for different improvement types
     */
    private array $priority_weights = [
        'critical' => 10,
        'high' => 7,
        'medium' => 5,
        'low' => 3
    ];

    /**
     * Initialize the Suggestion Engine
     */
    public function __construct()
    {
        $this->suggestion_templates = $this->get_suggestion_templates();
    }

    /**
     * Generate optimization suggestions based on analysis results
     *
     * @param array $analysis_result Complete analysis result from LiveAnalyzer
     * @return array Structured suggestions with priorities and actions
     */
    public function generate_suggestions(array $analysis_result): array
    {
        $detailed_analysis = $analysis_result['detailed_analysis'] ?? [];
        $real_time_feedback = $analysis_result['real_time_feedback'] ?? [];

        $suggestions = [
            'quick_wins' => $this->generate_quick_win_suggestions($real_time_feedback['quick_wins'] ?? []),
            'priority_fixes' => $this->generate_priority_fix_suggestions($real_time_feedback['priority_issues'] ?? []),
            'detailed_recommendations' => $this->generate_detailed_recommendations($detailed_analysis),
            'content_strategy' => $this->generate_content_strategy_suggestions($analysis_result),
            'technical_seo' => $this->generate_technical_suggestions($detailed_analysis)
        ];

        // Sort all suggestions by priority and impact
        $suggestions['all_suggestions'] = $this->compile_and_sort_suggestions($suggestions);

        return $suggestions;
    }

    /**
     * Generate quick win suggestions for immediate improvements
     *
     * @param array $quick_wins Quick win data from analysis
     * @return array Quick win suggestions
     */
    private function generate_quick_win_suggestions(array $quick_wins): array
    {
        $suggestions = [];

        foreach ($quick_wins as $quick_win) {
            $analyzer = $quick_win['analyzer'];
            $current_score = $quick_win['current_score'];
            $potential_improvement = $quick_win['potential_improvement'];

            $suggestion = [
                'type' => 'quick_win',
                'analyzer' => $analyzer,
                'title' => $this->get_quick_win_title($analyzer),
                'description' => $this->get_quick_win_description($analyzer, $current_score),
                'action_steps' => $this->get_quick_win_actions($analyzer),
                'estimated_time' => $this->get_estimated_time($analyzer, 'quick_win'),
                'impact_score' => $potential_improvement,
                'difficulty' => 'easy',
                'priority' => $this->calculate_suggestion_priority($potential_improvement, 'easy')
            ];

            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * Generate priority fix suggestions for critical issues
     *
     * @param array $priority_issues Priority issue data from analysis
     * @return array Priority fix suggestions
     */
    private function generate_priority_fix_suggestions(array $priority_issues): array
    {
        $suggestions = [];

        foreach ($priority_issues as $issue) {
            $analyzer = $issue['analyzer'];
            $score = $issue['score'];
            $severity = $issue['severity'];
            $impact = $issue['impact'];

            $suggestion = [
                'type' => 'priority_fix',
                'analyzer' => $analyzer,
                'title' => $this->get_priority_fix_title($analyzer, $severity),
                'description' => $this->get_priority_fix_description($analyzer, $score, $severity),
                'action_steps' => $this->get_priority_fix_actions($analyzer, $severity),
                'estimated_time' => $this->get_estimated_time($analyzer, 'priority_fix'),
                'impact_score' => $this->calculate_fix_impact($score, $impact),
                'difficulty' => $this->get_fix_difficulty($analyzer, $severity),
                'priority' => $this->get_severity_priority($severity),
                'warning_level' => $severity
            ];

            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * Generate detailed recommendations for each analyzer
     *
     * @param array $detailed_analysis Detailed analysis results
     * @return array Detailed recommendations
     */
    private function generate_detailed_recommendations(array $detailed_analysis): array
    {
        $recommendations = [];

        foreach ($detailed_analysis as $analyzer => $data) {
            $score = $data['score'];
            $status = $data['status'];
            $priority = $data['priority'] ?? 'medium';

            $recommendation = [
                'analyzer' => $analyzer,
                'current_score' => $score,
                'status' => $status,
                'target_score' => $this->get_target_score($analyzer),
                'improvement_potential' => $this->calculate_improvement_potential($analyzer, $score),
                'specific_recommendations' => $this->get_specific_recommendations($analyzer, $score),
                'best_practices' => $this->get_best_practices($analyzer),
                'examples' => $this->get_examples($analyzer),
                'resources' => $this->get_learning_resources($analyzer)
            ];

            $recommendations[$analyzer] = $recommendation;
        }

        return $recommendations;
    }

    /**
     * Generate content strategy suggestions
     *
     * @param array $analysis_result Complete analysis result
     * @return array Content strategy suggestions
     */
    private function generate_content_strategy_suggestions(array $analysis_result): array
    {
        $overall_score = $analysis_result['overall_score'] ?? 0;
        $detailed_analysis = $analysis_result['detailed_analysis'] ?? [];

        $strategy_suggestions = [];

        // Content length strategy
        if (isset($detailed_analysis['content_length'])) {
            $content_score = $detailed_analysis['content_length']['score'];
            if ($content_score < 60) {
                $strategy_suggestions[] = [
                    'type' => 'content_expansion',
                    'title' => 'Expand Your Content',
                    'description' => 'Your content could benefit from additional depth and detail.',
                    'suggestions' => $this->get_content_expansion_suggestions(),
                    'priority' => 'high'
                ];
            }
        }

        // Keyword strategy
        if (isset($detailed_analysis['keyword_density'])) {
            $keyword_score = $detailed_analysis['keyword_density']['score'];
            $strategy_suggestions[] = [
                'type' => 'keyword_optimization',
                'title' => 'Keyword Optimization Strategy',
                'description' => 'Optimize your keyword usage for better search visibility.',
                'suggestions' => $this->get_keyword_strategy_suggestions($keyword_score),
                'priority' => 'high'
            ];
        }

        // Structure strategy
        if (isset($detailed_analysis['heading_structure'])) {
            $structure_score = $detailed_analysis['heading_structure']['score'];
            if ($structure_score < 70) {
                $strategy_suggestions[] = [
                    'type' => 'content_structure',
                    'title' => 'Improve Content Structure',
                    'description' => 'Better content organization will improve readability and SEO.',
                    'suggestions' => $this->get_structure_suggestions(),
                    'priority' => 'medium'
                ];
            }
        }

        return $strategy_suggestions;
    }

    /**
     * Generate technical SEO suggestions
     *
     * @param array $detailed_analysis Detailed analysis results
     * @return array Technical SEO suggestions
     */
    private function generate_technical_suggestions(array $detailed_analysis): array
    {
        $technical_suggestions = [];

        // Meta description optimization
        if (isset($detailed_analysis['meta_description'])) {
            $meta_score = $detailed_analysis['meta_description']['score'];
            if ($meta_score < 80) {
                $technical_suggestions[] = [
                    'type' => 'meta_optimization',
                    'title' => 'Meta Description Optimization',
                    'description' => 'Improve your meta description for better click-through rates.',
                    'action_steps' => $this->get_meta_description_steps($meta_score),
                    'priority' => 'high'
                ];
            }
        }

        // Title optimization
        if (isset($detailed_analysis['title_analysis'])) {
            $title_score = $detailed_analysis['title_analysis']['score'];
            if ($title_score < 80) {
                $technical_suggestions[] = [
                    'type' => 'title_optimization',
                    'title' => 'Title Tag Optimization',
                    'description' => 'Optimize your title tag for better search rankings.',
                    'action_steps' => $this->get_title_optimization_steps($title_score),
                    'priority' => 'high'
                ];
            }
        }

        // Image optimization
        if (isset($detailed_analysis['image_alt_tags'])) {
            $image_score = $detailed_analysis['image_alt_tags']['score'];
            if ($image_score < 70) {
                $technical_suggestions[] = [
                    'type' => 'image_optimization',
                    'title' => 'Image SEO Optimization',
                    'description' => 'Add alt text to images for better accessibility and SEO.',
                    'action_steps' => $this->get_image_optimization_steps(),
                    'priority' => 'medium'
                ];
            }
        }

        return $technical_suggestions;
    }

    /**
     * Get quick win title for analyzer
     *
     * @param string $analyzer Analyzer name
     * @return string Quick win title
     */
    private function get_quick_win_title(string $analyzer): string
    {
        $titles = [
            'keyword_density' => 'Quick Keyword Optimization',
            'meta_description' => 'Add Meta Description',
            'title_analysis' => 'Improve Title Tag',
            'heading_structure' => 'Add Heading Tags',
            'image_alt_tags' => 'Add Image Alt Text',
            'internal_links' => 'Add Internal Links',
            'readability' => 'Improve Readability',
            'content_length' => 'Expand Content'
        ];

        return $titles[$analyzer] ?? 'Optimization Opportunity';
    }

    /**
     * Get quick win description
     *
     * @param string $analyzer Analyzer name
     * @param int $current_score Current score
     * @return string Description
     */
    private function get_quick_win_description(string $analyzer, int $current_score): string
    {
        $templates = $this->suggestion_templates[$analyzer]['quick_win'] ?? [];
        
        if ($current_score < 30) {
            return $templates['low'] ?? 'This element needs immediate attention for better SEO.';
        } elseif ($current_score < 60) {
            return $templates['medium'] ?? 'This element can be easily improved for better SEO.';
        } else {
            return $templates['high'] ?? 'This element is good but has potential for optimization.';
        }
    }

    /**
     * Get quick win action steps
     *
     * @param string $analyzer Analyzer name
     * @return array Action steps
     */
    private function get_quick_win_actions(string $analyzer): array
    {
        $actions = [
            'keyword_density' => [
                'Use your focus keyword naturally in the content',
                'Include variations and related keywords',
                'Aim for 1-3% keyword density'
            ],
            'meta_description' => [
                'Write a compelling 150-160 character description',
                'Include your focus keyword naturally',
                'Make it click-worthy and informative'
            ],
            'title_analysis' => [
                'Include your focus keyword in the title',
                'Keep title under 60 characters',
                'Make it compelling and descriptive'
            ],
            'heading_structure' => [
                'Add an H1 tag with your main keyword',
                'Use H2 and H3 tags to structure content',
                'Include keywords in subheadings naturally'
            ],
            'image_alt_tags' => [
                'Add descriptive alt text to all images',
                'Include relevant keywords when appropriate',
                'Keep alt text concise and descriptive'
            ],
            'internal_links' => [
                'Add 2-3 relevant internal links',
                'Use descriptive anchor text',
                'Link to related content on your site'
            ],
            'readability' => [
                'Use shorter sentences (under 20 words)',
                'Break up long paragraphs',
                'Use simple, clear language'
            ],
            'content_length' => [
                'Add more valuable information',
                'Expand on key points',
                'Aim for at least 300 words'
            ]
        ];

        return $actions[$analyzer] ?? ['Optimize this element for better SEO'];
    }

    /**
     * Get estimated time for improvement
     *
     * @param string $analyzer Analyzer name
     * @param string $type Improvement type
     * @return string Time estimate
     */
    private function get_estimated_time(string $analyzer, string $type): string
    {
        $time_estimates = [
            'keyword_density' => ['quick_win' => '5-10 minutes', 'priority_fix' => '15-20 minutes'],
            'meta_description' => ['quick_win' => '5 minutes', 'priority_fix' => '10 minutes'],
            'title_analysis' => ['quick_win' => '3 minutes', 'priority_fix' => '5 minutes'],
            'heading_structure' => ['quick_win' => '10 minutes', 'priority_fix' => '20 minutes'],
            'image_alt_tags' => ['quick_win' => '5 minutes per image', 'priority_fix' => '10-15 minutes'],
            'internal_links' => ['quick_win' => '10 minutes', 'priority_fix' => '20 minutes'],
            'readability' => ['quick_win' => '15 minutes', 'priority_fix' => '30-45 minutes'],
            'content_length' => ['quick_win' => '20-30 minutes', 'priority_fix' => '45-60 minutes']
        ];

        return $time_estimates[$analyzer][$type] ?? '10-15 minutes';
    }

    /**
     * Calculate suggestion priority
     *
     * @param int $impact_score Impact score
     * @param string $difficulty Difficulty level
     * @return string Priority level
     */
    private function calculate_suggestion_priority(int $impact_score, string $difficulty): string
    {
        $difficulty_multiplier = ['easy' => 2, 'medium' => 1.5, 'hard' => 1];
        $weighted_score = $impact_score * ($difficulty_multiplier[$difficulty] ?? 1);

        if ($weighted_score >= 40) return 'critical';
        if ($weighted_score >= 25) return 'high';
        if ($weighted_score >= 15) return 'medium';
        return 'low';
    }

    /**
     * Get suggestion templates for different scenarios
     *
     * @return array Suggestion templates
     */
    private function get_suggestion_templates(): array
    {
        return [
            'keyword_density' => [
                'quick_win' => [
                    'low' => 'Your focus keyword is rarely used. Include it naturally throughout your content.',
                    'medium' => 'Your keyword usage could be optimized. Aim for natural placement throughout the content.',
                    'high' => 'Your keyword usage is good but could be fine-tuned for better distribution.'
                ],
                'priority_fix' => [
                    'critical' => 'Your focus keyword is missing or severely under-used. This significantly impacts SEO.',
                    'high' => 'Keyword density is too low. Search engines need clearer signals about your content topic.'
                ]
            ],
            'meta_description' => [
                'quick_win' => [
                    'low' => 'Add a compelling meta description to improve click-through rates from search results.',
                    'medium' => 'Your meta description needs optimization for better search performance.',
                    'high' => 'Your meta description is good but could be more compelling.'
                ]
            ],
            'title_analysis' => [
                'quick_win' => [
                    'low' => 'Your title needs your focus keyword and better optimization for search engines.',
                    'medium' => 'Improve your title by including keywords and making it more compelling.',
                    'high' => 'Your title is good but has room for optimization.'
                ]
            ]
            // Add more templates as needed
        ];
    }

    /**
     * Compile and sort all suggestions by priority and impact
     *
     * @param array $suggestions All suggestion categories
     * @return array Sorted suggestions
     */
    private function compile_and_sort_suggestions(array $suggestions): array
    {
        $all_suggestions = [];

        // Collect all suggestions
        foreach ($suggestions as $category => $category_suggestions) {
            if ($category === 'detailed_recommendations') {
                // Skip detailed recommendations in main list
                continue;
            }
            
            if (is_array($category_suggestions)) {
                $all_suggestions = array_merge($all_suggestions, $category_suggestions);
            }
        }

        // Sort by priority weight and impact
        usort($all_suggestions, function($a, $b) {
            $priority_a = $this->priority_weights[$a['priority'] ?? 'low'] ?? 0;
            $priority_b = $this->priority_weights[$b['priority'] ?? 'low'] ?? 0;
            
            $impact_a = $a['impact_score'] ?? 0;
            $impact_b = $b['impact_score'] ?? 0;
            
            // Sort by priority first, then by impact
            if ($priority_a === $priority_b) {
                return $impact_b <=> $impact_a; // Higher impact first
            }
            return $priority_b <=> $priority_a; // Higher priority first
        });

        return array_slice($all_suggestions, 0, 10); // Return top 10 suggestions
    }

    /**
     * Additional helper methods for generating specific recommendations
     */
    
    private function get_content_expansion_suggestions(): array
    {
        return [
            'Add more detailed explanations of key concepts',
            'Include relevant examples and case studies',
            'Add frequently asked questions section',
            'Provide step-by-step instructions where appropriate',
            'Include relevant statistics and data points'
        ];
    }

    private function get_keyword_strategy_suggestions(int $score): array
    {
        if ($score < 40) {
            return [
                'Include your focus keyword in the first paragraph',
                'Use keyword variations throughout the content',
                'Add related keywords and semantic terms',
                'Ensure natural keyword placement'
            ];
        } else {
            return [
                'Fine-tune keyword density for optimal distribution',
                'Add long-tail keyword variations',
                'Include LSI (Latent Semantic Indexing) keywords'
            ];
        }
    }

    private function get_structure_suggestions(): array
    {
        return [
            'Use a clear H1 tag for your main title',
            'Add H2 tags for main section headings',
            'Use H3 tags for subsections',
            'Create logical content hierarchy',
            'Include a table of contents for longer articles'
        ];
    }

    private function get_meta_description_steps(int $score): array
    {
        if ($score < 40) {
            return [
                'Write a meta description (currently missing)',
                'Keep it between 150-160 characters',
                'Include your focus keyword naturally',
                'Make it compelling and action-oriented'
            ];
        } else {
            return [
                'Optimize length to stay within character limits',
                'Improve keyword placement',
                'Make it more compelling and click-worthy',
                'Include a call-to-action if appropriate'
            ];
        }
    }

    private function get_title_optimization_steps(int $score): array
    {
        return [
            'Include your focus keyword in the title',
            'Keep title under 60 characters for full display',
            'Make it compelling and click-worthy',
            'Ensure it accurately describes the content',
            'Consider using power words to increase appeal'
        ];
    }

    private function get_image_optimization_steps(): array
    {
        return [
            'Add descriptive alt text to all images',
            'Include relevant keywords when natural',
            'Keep alt text under 125 characters',
            'Describe the image content accurately',
            'Use proper file names for images before uploading'
        ];
    }

    private function get_target_score(string $analyzer): int
    {
        // Target scores for different analyzers
        return 85; // Default target
    }

    private function calculate_improvement_potential(string $analyzer, int $current_score): int
    {
        $target = $this->get_target_score($analyzer);
        return max(0, $target - $current_score);
    }

    private function get_specific_recommendations(string $analyzer, int $score): array
    {
        // Return specific recommendations based on analyzer and score
        return $this->get_quick_win_actions($analyzer);
    }

    private function get_best_practices(string $analyzer): array
    {
        // Return best practices for the analyzer
        return ["Follow SEO best practices for " . str_replace('_', ' ', $analyzer)];
    }

    private function get_examples(string $analyzer): array
    {
        // Return examples for the analyzer
        return ["Example for " . str_replace('_', ' ', $analyzer)];
    }

    private function get_learning_resources(string $analyzer): array
    {
        // Return learning resources
        return ["Learn more about " . str_replace('_', ' ', $analyzer)];
    }

    private function get_priority_fix_title(string $analyzer, string $severity): string
    {
        return ucfirst($severity) . ': ' . $this->get_quick_win_title($analyzer);
    }

    private function get_priority_fix_description(string $analyzer, int $score, string $severity): string
    {
        return $this->get_quick_win_description($analyzer, $score);
    }

    private function get_priority_fix_actions(string $analyzer, string $severity): array
    {
        return $this->get_quick_win_actions($analyzer);
    }

    private function calculate_fix_impact(int $score, string $impact): int
    {
        $impact_multipliers = ['high' => 3, 'medium' => 2, 'low' => 1];
        return (100 - $score) * ($impact_multipliers[$impact] ?? 1);
    }

    private function get_fix_difficulty(string $analyzer, string $severity): string
    {
        if ($severity === 'critical') return 'medium';
        return 'easy';
    }

    private function get_severity_priority(string $severity): string
    {
        return $severity === 'critical' ? 'critical' : 'high';
    }
}
