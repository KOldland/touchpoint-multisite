<?php
/**
 * Author Tools for Dual-GPT Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Author_Tools {

    /**
     * Outline from brief - Enhanced implementation
     */
    public function outline_from_brief($brief, $target_reader = 'general', $length_words = 1000, $brand_tone = 'professional') {
        // Analyze the brief to determine content type and structure
        $brief_lower = strtolower($brief);
        $content_type = $this->analyze_content_type($brief_lower);

        // Adjust outline based on content type
        $outline = array();

        switch ($content_type) {
            case 'how-to':
            case 'tutorial':
                $outline = $this->create_tutorial_outline($brief, $length_words);
                break;
            case 'opinion':
            case 'analysis':
                $outline = $this->create_analysis_outline($brief, $length_words);
                break;
            case 'news':
            case 'announcement':
                $outline = $this->create_news_outline($brief, $length_words);
                break;
            case 'review':
                $outline = $this->create_review_outline($brief, $length_words);
                break;
            default:
                $outline = $this->create_general_outline($brief, $length_words);
                break;
        }

        // Adjust tone and style based on target reader
        $this->adjust_outline_for_reader($outline, $target_reader, $brand_tone);

        return array(
            'outline' => $outline,
            'estimated_word_count' => $this->calculate_outline_word_count($outline),
            'target_reader' => $target_reader,
            'brand_tone' => $brand_tone,
            'content_type' => $content_type,
            'structure_type' => $this->determine_structure_type($outline),
        );
    }

    /**
     * Analyze content type from brief
     */
    private function analyze_content_type($brief_lower) {
        $type_indicators = array(
            'how-to' => array('how to', 'tutorial', 'guide', 'step by step', 'learn to'),
            'opinion' => array('i think', 'in my opinion', 'i believe', 'analysis', 'perspective'),
            'news' => array('announcement', 'breaking', 'update', 'news', 'released'),
            'review' => array('review', 'rating', 'recommend', 'pros and cons', 'evaluation'),
            'case-study' => array('case study', 'success story', 'example', 'implementation'),
            'comparison' => array('vs', 'versus', 'comparison', 'compare', 'difference'),
        );

        foreach ($type_indicators as $type => $indicators) {
            foreach ($indicators as $indicator) {
                if (strpos($brief_lower, $indicator) !== false) {
                    return $type;
                }
            }
        }

        return 'general';
    }

    /**
     * Create tutorial outline
     */
    private function create_tutorial_outline($brief, $length_words) {
        return array(
            array(
                'heading' => 'Introduction',
                'key_points' => array(
                    'Brief overview of what readers will learn',
                    'Prerequisites or requirements',
                    'Expected outcomes and benefits',
                ),
            ),
            array(
                'heading' => 'Preparation',
                'key_points' => array(
                    'Required tools, software, or materials',
                    'Setting up the development environment',
                    'Installing necessary dependencies',
                ),
            ),
            array(
                'heading' => 'Step-by-Step Instructions',
                'key_points' => array(
                    'Detailed step-by-step process',
                    'Code examples and explanations',
                    'Common pitfalls and how to avoid them',
                ),
            ),
            array(
                'heading' => 'Testing and Validation',
                'key_points' => array(
                    'How to test the implementation',
                    'Troubleshooting common issues',
                    'Verification steps',
                ),
            ),
            array(
                'heading' => 'Conclusion',
                'key_points' => array(
                    'Summary of what was accomplished',
                    'Next steps and advanced topics',
                    'Additional resources and references',
                ),
            ),
        );
    }

    /**
     * Create analysis outline
     */
    private function create_analysis_outline($brief, $length_words) {
        return array(
            array(
                'heading' => 'Introduction',
                'key_points' => array(
                    'Context and background of the topic',
                    'Thesis statement or main argument',
                    'Overview of analysis approach',
                ),
            ),
            array(
                'heading' => 'Current State Analysis',
                'key_points' => array(
                    'Examination of current trends and data',
                    'Key players and their positions',
                    'Market or industry dynamics',
                ),
            ),
            array(
                'heading' => 'Key Findings',
                'key_points' => array(
                    'Primary insights and discoveries',
                    'Supporting evidence and examples',
                    'Data-driven conclusions',
                ),
            ),
            array(
                'heading' => 'Implications and Recommendations',
                'key_points' => array(
                    'Practical implications for stakeholders',
                    'Strategic recommendations',
                    'Future outlook and predictions',
                ),
            ),
            array(
                'heading' => 'Conclusion',
                'key_points' => array(
                    'Summary of key points',
                    'Final thoughts and call to action',
                    'Areas for further research',
                ),
            ),
        );
    }

    /**
     * Create news outline
     */
    private function create_news_outline($brief, $length_words) {
        return array(
            array(
                'heading' => 'Headline',
                'key_points' => array(
                    'Compelling headline that captures attention',
                    'Subheadline with key details',
                    'Lead paragraph summarizing the main news',
                ),
            ),
            array(
                'heading' => 'Background and Context',
                'key_points' => array(
                    'Relevant background information',
                    'Timeline of events leading up to the news',
                    'Context for why this matters',
                ),
            ),
            array(
                'heading' => 'Key Details',
                'key_points' => array(
                    'Who, what, when, where, why, and how',
                    'Quotes from key stakeholders',
                    'Supporting facts and figures',
                ),
            ),
            array(
                'heading' => 'Impact and Implications',
                'key_points' => array(
                    'Immediate and long-term effects',
                    'Stakeholder reactions',
                    'Industry or community response',
                ),
            ),
            array(
                'heading' => 'Next Steps',
                'key_points' => array(
                    'What happens next',
                    'Timeline for future developments',
                    'Contact information for more details',
                ),
            ),
        );
    }

    /**
     * Create review outline
     */
    private function create_review_outline($brief, $length_words) {
        return array(
            array(
                'heading' => 'Overview',
                'key_points' => array(
                    'Brief introduction to what is being reviewed',
                    'Overall impression and rating',
                    'Key strengths and highlights',
                ),
            ),
            array(
                'heading' => 'Detailed Analysis',
                'key_points' => array(
                    'In-depth examination of features and functionality',
                    'Performance and reliability assessment',
                    'User experience and ease of use',
                ),
            ),
            array(
                'heading' => 'Pros and Cons',
                'key_points' => array(
                    'List of advantages and benefits',
                    'Areas for improvement or drawbacks',
                    'Comparison with alternatives',
                ),
            ),
            array(
                'heading' => 'Recommendations',
                'key_points' => array(
                    'Who would benefit most from this',
                    'Use cases and scenarios',
                    'Final verdict and rating',
                ),
            ),
        );
    }

    /**
     * Create general outline
     */
    private function create_general_outline($brief, $length_words) {
        return array(
            array(
                'heading' => 'Introduction',
                'key_points' => array(
                    'Hook and attention-grabbing opening',
                    'Background and context',
                    'Thesis or main purpose statement',
                ),
            ),
            array(
                'heading' => 'Main Content',
                'key_points' => array(
                    'Primary arguments or information',
                    'Supporting evidence and examples',
                    'Analysis and interpretation',
                ),
            ),
            array(
                'heading' => 'Supporting Details',
                'key_points' => array(
                    'Additional evidence and data',
                    'Case studies or examples',
                    'Expert opinions and research',
                ),
            ),
            array(
                'heading' => 'Conclusion',
                'key_points' => array(
                    'Summary of key points',
                    'Implications and significance',
                    'Call to action or final thoughts',
                ),
            ),
        );
    }

    /**
     * Adjust outline for target reader
     */
    private function adjust_outline_for_reader(&$outline, $target_reader, $brand_tone) {
        $reader_adjustments = array(
            'beginner' => array(
                'complexity' => 'simplify',
                'add_sections' => array('Glossary', 'FAQ'),
                'tone' => 'encouraging and patient',
            ),
            'expert' => array(
                'complexity' => 'technical',
                'add_sections' => array('Advanced Implementation', 'Technical Details'),
                'tone' => 'concise and precise',
            ),
            'executive' => array(
                'complexity' => 'high-level',
                'add_sections' => array('Executive Summary', 'ROI Analysis'),
                'tone' => 'strategic and results-focused',
            ),
            'general' => array(
                'complexity' => 'balanced',
                'tone' => 'accessible and engaging',
            ),
        );

        $adjustment = $reader_adjustments[$target_reader] ?? $reader_adjustments['general'];

        // Apply tone adjustments to key points
        foreach ($outline as &$section) {
            foreach ($section['key_points'] as &$point) {
                // This would be enhanced with actual tone adjustment logic
                $point .= ' (tailored for ' . $target_reader . ' audience)';
            }
        }
    }

    /**
     * Calculate outline word count
     */
    private function calculate_outline_word_count($outline) {
        $total_words = 0;
        foreach ($outline as $section) {
            $total_words += str_word_count($section['heading']);
            foreach ($section['key_points'] as $point) {
                $total_words += str_word_count($point);
            }
            // Estimate additional content per section
            $total_words += 150; // Rough estimate
        }
        return $total_words;
    }

    /**
     * Determine structure type
     */
    private function determine_structure_type($outline) {
        $section_count = count($outline);

        if ($section_count <= 3) return 'brief';
        if ($section_count <= 5) return 'standard';
        return 'comprehensive';
    }

    /**
     * Expand section - Enhanced implementation
     */
    public function expand_section($heading, $key_points, $constraints = array()) {
        $word_count = isset($constraints['word_count']) ? $constraints['word_count'] : 300;
        $tone = isset($constraints['tone']) ? $constraints['tone'] : 'professional';
        $style = isset($constraints['style']) ? $constraints['style'] : 'informative';

        // Analyze heading and key points to determine content type
        $content_type = $this->analyze_section_content_type($heading, $key_points);

        // Generate structured content based on type
        $content_parts = array();

        // Opening paragraph
        $content_parts[] = $this->generate_section_opening($heading, $key_points, $content_type);

        // Main content paragraphs
        $main_paragraphs = $this->generate_main_content($heading, $key_points, $content_type, $word_count);
        $content_parts = array_merge($content_parts, $main_paragraphs);

        // Closing paragraph if needed
        if ($content_type === 'analysis' || $content_type === 'tutorial') {
            $content_parts[] = $this->generate_section_closing($heading, $key_points, $content_type);
        }

        // Combine into markdown
        $markdown_content = "# $heading\n\n" . implode("\n\n", $content_parts);

        // Apply tone and style adjustments
        $markdown_content = $this->apply_tone_and_style($markdown_content, $tone, $style);

        return array(
            'content_markdown' => $markdown_content,
            'word_count' => str_word_count(strip_tags($markdown_content)),
            'heading' => $heading,
            'content_type' => $content_type,
            'structure' => array(
                'paragraphs' => count($content_parts),
                'key_points_covered' => count($key_points),
            ),
        );
    }

    /**
     * Analyze section content type
     */
    private function analyze_section_content_type($heading, $key_points) {
        $heading_lower = strtolower($heading);
        $all_points = strtolower(implode(' ', $key_points));

        $type_indicators = array(
            'introduction' => array('introduction', 'overview', 'background'),
            'tutorial' => array('step', 'how to', 'guide', 'tutorial', 'instructions'),
            'analysis' => array('analysis', 'examine', 'evaluate', 'assess', 'review'),
            'conclusion' => array('conclusion', 'summary', 'final', 'wrap up'),
            'case-study' => array('case study', 'example', 'implementation', 'success story'),
            'comparison' => array('comparison', 'versus', 'vs', 'difference', 'alternative'),
        );

        foreach ($type_indicators as $type => $indicators) {
            foreach ($indicators as $indicator) {
                if (strpos($heading_lower, $indicator) !== false || strpos($all_points, $indicator) !== false) {
                    return $type;
                }
            }
        }

        return 'general';
    }

    /**
     * Generate section opening
     */
    private function generate_section_opening($heading, $key_points, $content_type) {
        $templates = array(
            'introduction' => "When it comes to [topic], understanding the fundamentals is crucial. This section explores [first_point] and establishes the foundation for [second_point].",
            'tutorial' => "Let's dive into the practical steps for [heading]. We'll start by [first_point], then move on to [second_point] to ensure you have a complete understanding.",
            'analysis' => "A thorough analysis of [heading] reveals several important insights. By examining [first_point], we can better understand [second_point] and their implications.",
            'general' => "This section covers [heading] in detail, focusing on [first_point] and [second_point]. Understanding these concepts is essential for [context].",
        );

        $template = $templates[$content_type] ?? $templates['general'];

        return str_replace(
            array('[topic]', '[heading]', '[first_point]', '[second_point]', '[context]'),
            array(
                strtolower($heading),
                strtolower($heading),
                isset($key_points[0]) ? strtolower($key_points[0]) : 'key concepts',
                isset($key_points[1]) ? strtolower($key_points[1]) : 'important details',
                'comprehensive understanding'
            ),
            $template
        );
    }

    /**
     * Generate main content
     */
    private function generate_main_content($heading, $key_points, $content_type, $target_word_count) {
        $paragraphs = array();
        $current_word_count = 0;
        $target_words_per_paragraph = 80;

        foreach ($key_points as $index => $point) {
            if ($current_word_count >= $target_word_count * 0.8) {
                break; // Leave room for closing
            }

            $paragraph = $this->expand_key_point($point, $content_type, $index + 1);

            // Add supporting details
            $paragraph .= ' ' . $this->generate_supporting_details($point, $content_type);

            // Add examples or evidence
            if ($content_type === 'tutorial' || $content_type === 'analysis') {
                $paragraph .= ' ' . $this->generate_examples($point, $content_type);
            }

            $paragraphs[] = $paragraph;
            $current_word_count += str_word_count($paragraph);
        }

        return $paragraphs;
    }

    /**
     * Expand key point into paragraph
     */
    private function expand_key_point($point, $content_type, $index) {
        $expansions = array(
            'tutorial' => "The $index step involves [point]. This means carefully following the process to ensure [benefit]. Many people find that [additional_insight].",
            'analysis' => "Regarding [point], the evidence shows [finding]. This suggests that [implication]. Furthermore, [additional_context].",
            'general' => "[point] represents an important aspect that deserves careful consideration. This involves [explanation] and leads to [outcome].",
        );

        $template = $expansions[$content_type] ?? $expansions['general'];

        return str_replace(
            array('[point]', '[benefit]', '[additional_insight]', '[finding]', '[implication]', '[additional_context]', '[explanation]', '[outcome]'),
            array(
                $point,
                'successful implementation',
                'taking the time to understand each step leads to better results',
                'clear patterns and trends',
                'broader implications for the field',
                'historical context provides valuable perspective',
                'understanding the underlying principles',
                'improved decision-making and outcomes',
            ),
            $template
        );
    }

    /**
     * Generate supporting details
     */
    private function generate_supporting_details($point, $content_type) {
        $details = array(
            'tutorial' => "It's important to note that [detail] can significantly impact the final result. Experienced practitioners often [best_practice].",
            'analysis' => "Research indicates that [statistic] supports this observation. This aligns with [theory] and provides [insight].",
            'general' => "This concept builds upon [foundation] and connects to [related_concept] in meaningful ways.",
        );

        $template = $details[$content_type] ?? $details['general'];

        return str_replace(
            array('[detail]', '[best_practice]', '[statistic]', '[theory]', '[insight]', '[foundation]', '[related_concept]'),
            array(
                'attention to detail',
                'recommend documenting each step for future reference',
                'approximately 70% of successful implementations',
                'established frameworks in the field',
                'deeper understanding of the underlying dynamics',
                'fundamental principles',
                'broader industry trends',
            ),
            $template
        );
    }

    /**
     * Generate examples
     */
    private function generate_examples($point, $content_type) {
        if ($content_type === 'tutorial') {
            return "For example, when [action], you'll notice [result]. This demonstrates how [principle] works in practice.";
        } elseif ($content_type === 'analysis') {
            return "Consider the case of [example] where [situation] led to [outcome]. This illustrates [concept] clearly.";
        }

        return "This can be seen in various contexts, from [simple_example] to more complex scenarios involving [complex_example].";
    }

    /**
     * Generate section closing
     */
    private function generate_section_closing($heading, $key_points, $content_type) {
        $closings = array(
            'tutorial' => "By following these steps and understanding these key points, you'll be well-equipped to [outcome]. Remember that [reminder].",
            'analysis' => "In summary, the analysis of [heading] reveals [key_insight]. This understanding enables [application].",
            'general' => "The concepts covered in this section provide a solid foundation for [next_steps]. Moving forward, [consideration].",
        );

        $template = $closings[$content_type] ?? $closings['general'];

        return str_replace(
            array('[heading]', '[outcome]', '[reminder]', '[key_insight]', '[application]', '[next_steps]', '[consideration]'),
            array(
                strtolower($heading),
                'implement these techniques effectively',
                'practice and patience are key to mastery',
                'important patterns and relationships',
                'more informed decision-making',
                'advanced topics and applications',
                'these principles should guide your approach',
            ),
            $template
        );
    }

    /**
     * Apply tone and style adjustments
     */
    private function apply_tone_and_style($content, $tone, $style) {
        // This would be enhanced with actual NLP/text processing
        // For now, return content as-is
        return $content;
    }

    /**
     * Style guard - Enhanced implementation with PLL Writer rules
     */
    public function style_guard($content_markdown, $rules = array()) {
        $issues = array();
        $stats = array(
            'word_count' => str_word_count(strip_tags($content_markdown)),
            'sentence_count' => 0,
            'paragraph_count' => substr_count($content_markdown, "\n\n"),
            'character_count' => strlen(strip_tags($content_markdown)),
        );

        // Convert markdown to plain text for analysis
        $plain_text = $this->markdown_to_plain_text($content_markdown);

        // Analyze sentences
        $sentences = preg_split('/[.!?]+/', $plain_text, -1, PREG_SPLIT_NO_EMPTY);
        $stats['sentence_count'] = count($sentences);

        // PLL Writer Style Rules (based on project brief)
        $pll_rules = array(
            'no_very' => array(
                'pattern' => '/\bvery\b/i',
                'message' => 'Avoid using "very" - use more precise adjectives',
                'severity' => 'warning',
            ),
            'no_really' => array(
                'pattern' => '/\breally\b/i',
                'message' => 'Avoid using "really" - be more direct',
                'severity' => 'warning',
            ),
            'no_so' => array(
                'pattern' => '/\bso\b/i',
                'message' => 'Avoid starting sentences with "so"',
                'severity' => 'info',
            ),
            'no_just' => array(
                'pattern' => '/\bjust\b/i',
                'message' => 'Avoid using "just" as filler word',
                'severity' => 'info',
            ),
            'no_that' => array(
                'pattern' => '/\bthat\b/i',
                'message' => 'Review use of "that" - often unnecessary',
                'severity' => 'info',
            ),
        );

        // Check for PLL style violations
        foreach ($pll_rules as $rule_name => $rule) {
            if (preg_match_all($rule['pattern'], $plain_text, $matches) > 0) {
                $count = count($matches[0]);
                $issues[] = array(
                    'rule' => $rule_name,
                    'message' => $rule['message'],
                    'severity' => $rule['severity'],
                    'count' => $count,
                    'suggestion' => $this->get_style_suggestion($rule_name),
                );
            }
        }

        // Check for em dashes (PLL style)
        $stats['emdash_count'] = substr_count($plain_text, '—');
        if ($stats['emdash_count'] === 0) {
            $issues[] = array(
                'rule' => 'missing_emdash',
                'message' => 'Consider using em dashes (—) for sophisticated punctuation',
                'severity' => 'info',
                'count' => 0,
                'suggestion' => 'Use em dashes for interruptions or parenthetical phrases',
            );
        }

        // Check for reversal markers (PLL style)
        $reversal_markers = array('but', 'however', 'although', 'yet', 'still', 'nevertheless');
        $has_reversal = false;
        foreach ($reversal_markers as $marker) {
            if (stripos($plain_text, $marker) !== false) {
                $has_reversal = true;
                break;
            }
        }
        $stats['reversal_marker_present'] = $has_reversal;

        if (!$has_reversal) {
            $issues[] = array(
                'rule' => 'missing_reversal',
                'message' => 'Consider adding a reversal marker for sophisticated argumentation',
                'severity' => 'info',
                'count' => 0,
                'suggestion' => 'Use words like "but", "however", or "although" to show contrast',
            );
        }

        // Check for micro transitions (PLL style)
        $micro_transitions = array('and', 'but', 'or', 'so', 'because', 'although', 'while');
        $transition_count = 0;
        foreach ($micro_transitions as $transition) {
            $transition_count += substr_count(strtolower($plain_text), $transition);
        }
        $stats['micro_transitions'] = $transition_count;

        if ($transition_count < 2) {
            $issues[] = array(
                'rule' => 'few_transitions',
                'message' => 'Consider adding more micro transitions for better flow',
                'severity' => 'info',
                'count' => $transition_count,
                'suggestion' => 'Use words like "and", "but", "so", "because" to connect ideas',
            );
        }

        // Analyze sentence structure
        $this->analyze_sentence_structure($sentences, $issues, $stats);

        // Analyze paragraph structure
        $this->analyze_paragraph_structure($content_markdown, $issues, $stats);

        // Determine overall compliance
        $critical_issues = array_filter($issues, fn($issue) => $issue['severity'] === 'error');
        $warning_issues = array_filter($issues, fn($issue) => $issue['severity'] === 'warning');

        $ok = empty($critical_issues) && count($warning_issues) <= 2;

        return array(
            'ok' => $ok,
            'issues' => $issues,
            'stats' => $stats,
            'compliance_score' => $this->calculate_compliance_score($issues),
            'recommendations' => $this->generate_recommendations($issues),
        );
    }

    /**
     * Convert markdown to plain text
     */
    private function markdown_to_plain_text($markdown) {
        // Remove markdown headers
        $text = preg_replace('/^#{1,6}\s+/m', '', $markdown);

        // Remove markdown links
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);

        // Remove emphasis
        $text = preg_replace('/\*\*([^\*]+)\*\*/', '$1', $text);
        $text = preg_replace('/\*([^\*]+)\*/', '$1', $text);

        // Remove code blocks
        $text = preg_replace('/```[\s\S]*?```/', '', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        return trim($text);
    }

    /**
     * Get style suggestion
     */
    private function get_style_suggestion($rule_name) {
        $suggestions = array(
            'no_very' => 'Replace "very" with more precise words like "extremely", "highly", or "exceptionally"',
            'no_really' => 'Remove "really" or replace with stronger alternatives',
            'no_so' => 'Start sentences more directly or use "therefore" for better flow',
            'no_just' => 'Remove "just" or use "simply" for clarity',
            'no_that' => 'Review each "that" - many can be removed for conciseness',
        );

        return $suggestions[$rule_name] ?? 'Review and revise for better style';
    }

    /**
     * Analyze sentence structure
     */
    private function analyze_sentence_structure($sentences, &$issues, &$stats) {
        $sentence_lengths = array_map('str_word_count', $sentences);

        if (!empty($sentence_lengths)) {
            $stats['avg_sentence_length'] = array_sum($sentence_lengths) / count($sentence_lengths);
            $stats['long_sentences'] = count(array_filter($sentence_lengths, fn($len) => $len > 25));
            $stats['short_sentences'] = count(array_filter($sentence_lengths, fn($len) => $len < 5));

            // Check for overly long sentences
            if ($stats['long_sentences'] > count($sentences) * 0.3) {
                $issues[] = array(
                    'rule' => 'long_sentences',
                    'message' => 'Too many long sentences - aim for variety in sentence length',
                    'severity' => 'warning',
                    'count' => $stats['long_sentences'],
                    'suggestion' => 'Break up long sentences or use semicolons/em dashes',
                );
            }

            // Check for sentence variety
            $sentence_variety_ratio = $stats['short_sentences'] / max($stats['long_sentences'], 1);
            if ($sentence_variety_ratio < 0.5) {
                $issues[] = array(
                    'rule' => 'sentence_variety',
                    'message' => 'Improve sentence variety - mix short and long sentences',
                    'severity' => 'info',
                    'count' => 0,
                    'suggestion' => 'Use short sentences for emphasis and longer ones for explanation',
                );
            }
        }
    }

    /**
     * Analyze paragraph structure
     */
    private function analyze_paragraph_structure($content, &$issues, &$stats) {
        $paragraphs = array_filter(explode("\n\n", $content));

        if (!empty($paragraphs)) {
            $paragraph_lengths = array_map(function($p) {
                return str_word_count(strip_tags($p));
            }, $paragraphs);

            $stats['avg_paragraph_length'] = array_sum($paragraph_lengths) / count($paragraph_lengths);
            $stats['long_paragraphs'] = count(array_filter($paragraph_lengths, fn($len) => $len > 150));

            // Check for overly long paragraphs
            if ($stats['long_paragraphs'] > 0) {
                $issues[] = array(
                    'rule' => 'long_paragraphs',
                    'message' => 'Some paragraphs are very long - consider breaking them up',
                    'severity' => 'warning',
                    'count' => $stats['long_paragraphs'],
                    'suggestion' => 'Break long paragraphs at natural transition points',
                );
            }
        }
    }

    /**
     * Calculate compliance score
     */
    private function calculate_compliance_score($issues) {
        $total_score = 100;

        foreach ($issues as $issue) {
            $penalty = 0;
            switch ($issue['severity']) {
                case 'error':
                    $penalty = 10;
                    break;
                case 'warning':
                    $penalty = 5;
                    break;
                case 'info':
                    $penalty = 2;
                    break;
            }

            // Increase penalty for multiple occurrences
            $penalty *= min($issue['count'] ?: 1, 5);

            $total_score -= $penalty;
        }

        return max(0, $total_score);
    }

    /**
     * Generate recommendations
     */
    private function generate_recommendations($issues) {
        $recommendations = array();

        if (count(array_filter($issues, fn($i) => $i['severity'] === 'error')) > 0) {
            $recommendations[] = 'Address critical style issues before publishing';
        }

        if (count(array_filter($issues, fn($i) => $i['rule'] === 'long_sentences')) > 0) {
            $recommendations[] = 'Read your content aloud to identify run-on sentences';
        }

        if (count(array_filter($issues, fn($i) => in_array($i['rule'], ['no_very', 'no_really', 'no_just']))) > 0) {
            $recommendations[] = 'Replace filler words with more precise language';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Content follows good style guidelines - consider minor refinements';
        }

        return $recommendations;
    }

    /**
     * Citation guard - Enhanced implementation with comprehensive citation checking
     */
    public function citation_guard($content_markdown, $required_citations = array()) {
        $issues = array();
        $citations_found = array();
        $stats = array(
            'citation_count' => 0,
            'inline_citations' => 0,
            'footnote_citations' => 0,
            'bibliography_entries' => 0,
            'missing_citations' => 0,
        );

        // Extract citations from content
        $citations_found = $this->extract_citations($content_markdown);

        // Analyze citation patterns
        $stats['citation_count'] = count($citations_found);
        $stats['inline_citations'] = count(array_filter($citations_found, fn($c) => $c['type'] === 'inline'));
        $stats['footnote_citations'] = count(array_filter($citations_found, fn($c) => $c['type'] === 'footnote'));

        // Check for bibliography section
        $bibliography_section = $this->extract_bibliography($content_markdown);
        $stats['bibliography_entries'] = count($bibliography_section);

        // Validate citation completeness
        $citation_issues = $this->validate_citation_completeness($citations_found, $bibliography_section);
        $issues = array_merge($issues, $citation_issues);

        // Check citation formatting
        $formatting_issues = $this->check_citation_formatting($citations_found);
        $issues = array_merge($issues, $formatting_issues);

        // Check for required citations
        if (!empty($required_citations)) {
            $missing_required = $this->check_required_citations($citations_found, $required_citations);
            $issues = array_merge($issues, $missing_required);
            $stats['missing_citations'] = count($missing_required);
        }

        // Check citation density
        $word_count = str_word_count(strip_tags($content_markdown));
        $citation_density = $word_count > 0 ? $stats['citation_count'] / ($word_count / 1000) : 0;

        if ($citation_density < 1) {
            $issues[] = array(
                'rule' => 'low_citation_density',
                'message' => 'Citation density is low - consider adding more supporting references',
                'severity' => 'warning',
                'count' => $stats['citation_count'],
                'suggestion' => 'Aim for at least 1 citation per 1000 words for academic content',
            );
        } elseif ($citation_density > 5) {
            $issues[] = array(
                'rule' => 'high_citation_density',
                'message' => 'Citation density is very high - review for citation overkill',
                'severity' => 'info',
                'count' => $stats['citation_count'],
                'suggestion' => 'Ensure citations are necessary and not excessive',
            );
        }

        // Check for citation clustering
        $clustering_issues = $this->check_citation_clustering($citations_found);
        $issues = array_merge($issues, $clustering_issues);

        // Determine overall compliance
        $critical_issues = array_filter($issues, fn($issue) => $issue['severity'] === 'error');
        $warning_issues = array_filter($issues, fn($issue) => $issue['severity'] === 'warning');

        $ok = empty($critical_issues) && count($warning_issues) <= 2;

        return array(
            'ok' => $ok,
            'issues' => $issues,
            'stats' => $stats,
            'citations_found' => $citations_found,
            'bibliography' => $bibliography_section,
            'compliance_score' => $this->calculate_citation_compliance_score($issues),
            'recommendations' => $this->generate_citation_recommendations($issues),
        );
    }

    /**
     * Extract citations from content
     */
    private function extract_citations($content) {
        $citations = array();

        // Match inline citations like (Author, Year), [1], (Smith 2020)
        $inline_patterns = array(
            '/\([A-Za-z][A-Za-z\s,]+\d{4}[a-z]?\)/',  // (Author, Year)
            '/\([A-Za-z]+\s+\d{4}[a-z]?\)/',          // (Author Year)
            '/\[(\d+)\]/',                             // [1]
            '/\[\d+(,\s*\d+)*\]/',                     // [1,2,3]
        );

        foreach ($inline_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $citations[] = array(
                        'text' => $match[0],
                        'position' => $match[1],
                        'type' => 'inline',
                        'format' => $this->identify_citation_format($match[0]),
                    );
                }
            }
        }

        // Match footnote citations like [^1]
        if (preg_match_all('/\[\^(\d+)\]/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $citations[] = array(
                    'text' => $match[0],
                    'position' => $match[1],
                    'type' => 'footnote',
                    'footnote_number' => $match[1],
                );
            }
        }

        // Sort by position
        usort($citations, fn($a, $b) => $a['position'] <=> $b['position']);

        return $citations;
    }

    /**
     * Extract bibliography section
     */
    private function extract_bibliography($content) {
        $bibliography = array();

        // Look for bibliography/works cited/references section
        $patterns = array(
            '/(?:#+\s*)?(?:Bibliography|Works Cited|References|Sources?)\s*\n(.*?)(?=\n#|\n\n#|\n\n---|\n\n\[\^|$)/si',
            '/(?:#+\s*)?(?:Bibliography|Works Cited|References|Sources?)\s*\n(.*?)$/si',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $bib_content = $matches[1];

                // Split into individual entries
                $entries = preg_split('/\n\s*\n/', trim($bib_content));
                foreach ($entries as $entry) {
                    $entry = trim($entry);
                    if (!empty($entry) && !preg_match('/^\s*$/', $entry)) {
                        $bibliography[] = array(
                            'text' => $entry,
                            'format' => $this->identify_bibliography_format($entry),
                        );
                    }
                }
                break;
            }
        }

        return $bibliography;
    }

    /**
     * Identify citation format
     */
    private function identify_citation_format($citation) {
        if (preg_match('/\([A-Za-z][A-Za-z\s,]+\d{4}[a-z]?\)/', $citation)) {
            return 'APA';
        } elseif (preg_match('/\([A-Za-z]+\s+\d{4}[a-z]?\)/', $citation)) {
            return 'MLA';
        } elseif (preg_match('/\[\d+\]/', $citation)) {
            return 'numeric';
        }
        return 'unknown';
    }

    /**
     * Identify bibliography format
     */
    private function identify_bibliography_format($entry) {
        if (preg_match('/^[A-Za-z][A-Za-z\s,]+\([0-9]{4}[a-z]?\)/', $entry)) {
            return 'APA';
        } elseif (preg_match('/^[A-Za-z]+,\s+[A-Za-z]+\./', $entry)) {
            return 'MLA';
        } elseif (preg_match('/^\[\d+\]/', $entry)) {
            return 'numeric';
        }
        return 'unknown';
    }

    /**
     * Validate citation completeness
     */
    private function validate_citation_completeness($citations, $bibliography) {
        $issues = array();

        // Check for orphaned citations (cited but not in bibliography)
        $cited_numbers = array();
        $bib_numbers = array();

        foreach ($citations as $citation) {
            if ($citation['type'] === 'inline' && isset($citation['format']) && $citation['format'] === 'numeric') {
                if (preg_match('/\[(\d+)\]/', $citation['text'], $matches)) {
                    $cited_numbers[] = (int)$matches[1];
                }
            }
        }

        foreach ($bibliography as $entry) {
            if (preg_match('/^\[(\d+)\]/', $entry['text'], $matches)) {
                $bib_numbers[] = (int)$matches[1];
            }
        }

        $orphaned = array_diff($cited_numbers, $bib_numbers);
        if (!empty($orphaned)) {
            $issues[] = array(
                'rule' => 'orphaned_citations',
                'message' => 'Found citations without corresponding bibliography entries: ' . implode(', ', $orphaned),
                'severity' => 'error',
                'count' => count($orphaned),
                'suggestion' => 'Add missing bibliography entries or remove citations',
            );
        }

        // Check for unused bibliography entries
        $unused = array_diff($bib_numbers, $cited_numbers);
        if (!empty($unused)) {
            $issues[] = array(
                'rule' => 'unused_bibliography',
                'message' => 'Found bibliography entries that are not cited: ' . implode(', ', $unused),
                'severity' => 'warning',
                'count' => count($unused),
                'suggestion' => 'Remove unused bibliography entries or add citations',
            );
        }

        return $issues;
    }

    /**
     * Check citation formatting
     */
    private function check_citation_formatting($citations) {
        $issues = array();

        $formats = array_unique(array_column($citations, 'format'));
        $formats = array_filter($formats, fn($f) => $f !== 'unknown');

        if (count($formats) > 1) {
            $issues[] = array(
                'rule' => 'mixed_formats',
                'message' => 'Mixed citation formats detected: ' . implode(', ', $formats),
                'severity' => 'warning',
                'count' => count($formats),
                'suggestion' => 'Use consistent citation format throughout',
            );
        }

        // Check for malformed citations
        foreach ($citations as $citation) {
            if ($citation['format'] === 'unknown') {
                $issues[] = array(
                    'rule' => 'malformed_citation',
                    'message' => 'Potentially malformed citation: ' . $citation['text'],
                    'severity' => 'warning',
                    'count' => 1,
                    'suggestion' => 'Review citation format and correct if necessary',
                );
            }
        }

        return $issues;
    }

    /**
     * Check required citations
     */
    private function check_required_citations($citations_found, $required_citations) {
        $issues = array();

        foreach ($required_citations as $required) {
            $found = false;
            foreach ($citations_found as $citation) {
                if (stripos($citation['text'], $required) !== false) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $issues[] = array(
                    'rule' => 'missing_required_citation',
                    'message' => 'Missing required citation: ' . $required,
                    'severity' => 'error',
                    'count' => 1,
                    'suggestion' => 'Add citation for required source',
                );
            }
        }

        return $issues;
    }

    /**
     * Check citation clustering
     */
    private function check_citation_clustering($citations) {
        $issues = array();

        if (count($citations) < 3) {
            return $issues;
        }

        // Look for clusters of citations (more than 2 within 500 characters)
        $clusters = array();
        $current_cluster = array();

        for ($i = 0; $i < count($citations); $i++) {
            $current_cluster[] = $citations[$i];

            if ($i < count($citations) - 1) {
                $gap = $citations[$i + 1]['position'] - ($citations[$i]['position'] + strlen($citations[$i]['text']));
                if ($gap > 500) {
                    if (count($current_cluster) > 2) {
                        $clusters[] = $current_cluster;
                    }
                    $current_cluster = array();
                }
            }
        }

        if (count($current_cluster) > 2) {
            $clusters[] = $current_cluster;
        }

        if (!empty($clusters)) {
            $issues[] = array(
                'rule' => 'citation_clustering',
                'message' => 'Found ' . count($clusters) . ' cluster(s) of closely spaced citations',
                'severity' => 'info',
                'count' => count($clusters),
                'suggestion' => 'Consider spreading out citations or consolidating where appropriate',
            );
        }

        return $issues;
    }

    /**
     * Calculate citation compliance score
     */
    private function calculate_citation_compliance_score($issues) {
        $total_score = 100;

        foreach ($issues as $issue) {
            $penalty = 0;
            switch ($issue['severity']) {
                case 'error':
                    $penalty = 15;
                    break;
                case 'warning':
                    $penalty = 7;
                    break;
                case 'info':
                    $penalty = 3;
                    break;
            }

            $penalty *= min($issue['count'] ?: 1, 5);
            $total_score -= $penalty;
        }

        return max(0, $total_score);
    }

    /**
     * Generate citation recommendations
     */
    private function generate_citation_recommendations($issues) {
        $recommendations = array();

        if (count(array_filter($issues, fn($i) => $i['rule'] === 'orphaned_citations')) > 0) {
            $recommendations[] = 'Add missing bibliography entries for all cited sources';
        }

        if (count(array_filter($issues, fn($i) => $i['rule'] === 'mixed_formats')) > 0) {
            $recommendations[] = 'Choose one citation format (APA, MLA, or numeric) and use consistently';
        }

        if (count(array_filter($issues, fn($i) => $i['rule'] === 'low_citation_density')) > 0) {
            $recommendations[] = 'Add more supporting citations to strengthen your arguments';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Citation structure looks good - review for minor improvements';
        }

        return $recommendations;
    }

    /**
     * Get tool definitions for OpenAI
     */
    public function get_tool_definitions() {
        return array(
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'outline_from_brief',
                    'description' => 'Create a content outline from a writing brief',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'brief' => array(
                                'type' => 'string',
                                'description' => 'The writing brief or topic description',
                            ),
                            'target_reader' => array(
                                'type' => 'string',
                                'description' => 'Target audience for the content',
                                'default' => 'general',
                            ),
                            'length_words' => array(
                                'type' => 'integer',
                                'description' => 'Target word count',
                                'default' => 1000,
                            ),
                            'brand_tone' => array(
                                'type' => 'string',
                                'description' => 'Brand voice or tone',
                                'default' => 'professional',
                            ),
                        ),
                        'required' => array('brief'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'expand_section',
                    'description' => 'Expand a content section with detailed writing',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'heading' => array(
                                'type' => 'string',
                                'description' => 'Section heading',
                            ),
                            'key_points' => array(
                                'type' => 'array',
                                'items' => array('type' => 'string'),
                                'description' => 'Key points to cover in this section',
                            ),
                            'constraints' => array(
                                'type' => 'object',
                                'properties' => array(
                                    'word_count' => array(
                                        'type' => 'integer',
                                        'description' => 'Target word count for this section',
                                    ),
                                ),
                            ),
                        ),
                        'required' => array('heading', 'key_points'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'style_guard',
                    'description' => 'Validate content against style guidelines',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'content_markdown' => array(
                                'type' => 'string',
                                'description' => 'Content to validate',
                            ),
                            'rules' => array(
                                'type' => 'array',
                                'items' => array('type' => 'string'),
                                'description' => 'Style rules to check against',
                            ),
                        ),
                        'required' => array('content_markdown'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'citation_guard',
                    'description' => 'Validate citations and references comprehensively',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'content_markdown' => array(
                                'type' => 'string',
                                'description' => 'Content with citations to validate',
                            ),
                            'required_citations' => array(
                                'type' => 'array',
                                'items' => array('type' => 'string'),
                                'description' => 'List of required citations that must be present',
                            ),
                        ),
                        'required' => array('content_markdown'),
                    ),
                ),
            ),
        );
    }

    /**
     * Execute a tool call
     */
    public function execute_tool($tool_name, $arguments) {
        if (!method_exists($this, $tool_name)) {
            return array('error' => 'Tool not found');
        }

        return call_user_func_array(array($this, $tool_name), $arguments);
    }
}