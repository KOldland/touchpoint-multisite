<?php
/**
 * AnswerCard Scoring Engine
 *
 * Evaluates AnswerCard quality based on multiple criteria including
 * content completeness, confidence scores, citations, and entity linkage.
 *
 * @package KHM_SEO\GEO\Scoring
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * ScoringEngine Class
 */
class ScoringEngine {

    /**
     * Scoring criteria weights
     */
    const CRITERIA_WEIGHTS = array(
        'content_completeness' => 0.20,
        'confidence_score' => 0.15,
        'citation_quality' => 0.20, // Increased for evidence-based scoring
        'entity_anchor_score' => 0.20, // New: evidence strength weighting
        'content_quality' => 0.15,
        'seo_optimization' => 0.10,
    );

    /**
     * Score thresholds for quality levels
     */
    const SCORE_THRESHOLDS = array(
        'excellent' => 0.90,
        'good' => 0.75,
        'fair' => 0.60,
        'poor' => 0.40,
    );

    /**
     * Calculate overall AnswerCard score
     *
     * @param array $settings Widget settings
     * @param array $context Additional context (post_id, etc.)
     * @return array Score data with breakdown
     */
    public function calculate_score( $settings, $context = array() ) {
        $scores = array(
            'content_completeness' => $this->score_content_completeness( $settings ),
            'confidence_score' => $this->score_confidence( $settings ),
            'citation_quality' => $this->score_citations( $settings ),
            'entity_anchor_score' => $this->score_entity_anchor( $settings ), // New: evidence-based entity anchoring
            'content_quality' => $this->score_content_quality( $settings ),
            'seo_optimization' => $this->score_seo_optimization( $settings, $context ),
        );

        // Calculate weighted total
        $total_score = 0;
        foreach ( $scores as $criterion => $score ) {
            $weight = self::CRITERIA_WEIGHTS[$criterion] ?? 0;
            $total_score += $score * $weight;
        }

        // Determine quality level
        $quality_level = $this->determine_quality_level( $total_score );

        // Generate recommendations
        $recommendations = $this->generate_recommendations( $scores, $settings );

        return array(
            'total_score' => round( $total_score, 3 ),
            'quality_level' => $quality_level,
            'scores' => $scores,
            'recommendations' => $recommendations,
            'is_publishable' => $total_score >= self::SCORE_THRESHOLDS['fair'],
        );
    }

    /**
     * Score content completeness
     */
    protected function score_content_completeness( $settings ) {
        $score = 0;
        $max_score = 1.0;

        // Question completeness (required)
        if ( ! empty( $settings['question'] ) && strlen( trim( $settings['question'] ) ) > 10 ) {
            $score += 0.3;
        }

        // Answer completeness (required)
        if ( ! empty( $settings['answer'] ) && strlen( trim( $settings['answer'] ) ) > 50 ) {
            $score += 0.4;
        }

        // Additional content (bonus)
        if ( ! empty( $settings['bullets'] ) && is_array( $settings['bullets'] ) && count( $settings['bullets'] ) > 0 ) {
            $score += min( 0.2, count( $settings['bullets'] ) * 0.05 );
        }

        // Citations (bonus)
        if ( ! empty( $settings['citations'] ) && is_array( $settings['citations'] ) && count( $settings['citations'] ) > 0 ) {
            $score += min( 0.1, count( $settings['citations'] ) * 0.025 );
        }

        return min( $max_score, $score );
    }

    /**
     * Score confidence level
     */
    protected function score_confidence( $settings ) {
        $confidence = $settings['confidence_score'] ?? 0.5;

        // Confidence score maps directly (0.0 to 1.0)
        return max( 0, min( 1, $confidence ) );
    }

    /**
     * Score citation quality with evidence tier weighting
     */
    protected function score_citations( $settings ) {
        $citations = $settings['citations'] ?? array();
        $evidence = $settings['evidence'] ?? array();

        if ( empty( $citations ) ) {
            return 0.1; // Minimal base score for having some citations
        }

        $score = 0.1; // Base score
        $evidence_bonus = 0;

        // Evidence tier weighting (Tier 1 > Tier 2 > Tier 3)
        $tier_weights = array(
            1 => 0.4, // Study+Year: Highest weight
            2 => 0.25, // Benchmark: Medium weight
            3 => 0.15, // Trade Publication: Lower weight
        );

        if ( ! empty( $evidence['tier'] ) && isset( $tier_weights[ $evidence['tier'] ] ) ) {
            $evidence_bonus += $tier_weights[ $evidence['tier'] ];
        }

        // Evidence confidence multiplier
        $confidence_multiplier = $evidence['confidence'] ?? 0.5;
        $evidence_bonus *= max( 0.3, min( 1.0, $confidence_multiplier ) );

        // Source passage quality bonus
        if ( ! empty( $evidence['source_passage'] ) && strlen( $evidence['source_passage'] ) > 20 ) {
            $evidence_bonus += 0.1;
        }

        $score += $evidence_bonus;

        // Traditional citation scoring (reduced weight)
        $valid_citations = 0;
        foreach ( $citations as $citation ) {
            if ( empty( $citation ) ) continue;

            $citation = trim( $citation );

            // Check if it's a URL
            if ( filter_var( $citation, FILTER_VALIDATE_URL ) ) {
                $score += 0.1;
                $valid_citations++;
            }
            // Check if it's a reasonable text citation
            elseif ( strlen( $citation ) > 10 ) {
                $score += 0.05;
                $valid_citations++;
            }
        }

        // Bonus for multiple citations (reduced)
        if ( $valid_citations > 1 ) {
            $score += min( 0.1, $valid_citations * 0.03 );
        }

        return min( 1.0, $score );
    }

    /**
     * Score entity anchor strength (evidence-based entity linkage)
     */
    protected function score_entity_anchor( $settings ) {
        $score = 0.2; // Base score for potential entity linkage

        // Evidence tier provides entity anchoring strength
        $evidence = $settings['evidence'] ?? array();
        if ( ! empty( $evidence['tier'] ) ) {
            // Higher tiers provide stronger entity anchoring
            $tier_anchors = array(
                1 => 0.8, // Tier 1: Strongest entity anchoring (Study+Year)
                2 => 0.6, // Tier 2: Good entity anchoring (Benchmark)
                3 => 0.4, // Tier 3: Moderate entity anchoring (Trade Publication)
            );

            if ( isset( $tier_anchors[ $evidence['tier'] ] ) ) {
                $score = $tier_anchors[ $evidence['tier'] ];
            }
        }

        // Evidence confidence affects anchoring strength
        $confidence = $evidence['confidence'] ?? 0.5;
        $score *= max( 0.3, min( 1.0, $confidence ) );

        // Source passage quality strengthens anchoring
        if ( ! empty( $evidence['source_passage'] ) && strlen( $evidence['source_passage'] ) > 30 ) {
            $score += 0.1;
        }

        // Traditional entity ID still provides bonus
        if ( ! empty( $settings['entity_id'] ) ) {
            $score += 0.1;
        }

        return min( 1.0, $score );
    }

    /**
     * Score content quality
     */
    protected function score_content_quality( $settings ) {
        $score = 0.5; // Base score

        $question = $settings['question'] ?? '';
        $answer = $settings['answer'] ?? '';

        // Question quality
        if ( strlen( $question ) > 20 ) {
            $score += 0.1;
        }

        // Question starts with question word
        if ( preg_match( '/^(what|how|why|when|where|who|which|can|does|is|are|do)/i', $question ) ) {
            $score += 0.1;
        }

        // Answer quality
        if ( strlen( $answer ) > 100 ) {
            $score += 0.1;
        }

        // Answer has structure (paragraphs, lists)
        if ( strpos( $answer, '</p>' ) !== false || strpos( $answer, "\n" ) !== false ) {
            $score += 0.1;
        }

        // Avoid keyword stuffing (negative scoring)
        $question_words = str_word_count( $question );
        $answer_words = str_word_count( strip_tags( $answer ) );

        if ( $question_words > 0 && $answer_words / $question_words > 50 ) {
            $score -= 0.2; // Potential keyword stuffing
        }

        return max( 0, min( 1, $score ) );
    }

    /**
     * Score SEO optimization
     */
    protected function score_seo_optimization( $settings, $context ) {
        $score = 0.5; // Base score

        $question = $settings['question'] ?? '';
        $answer = $settings['answer'] ?? '';

        // Question contains target keywords (assuming we have context)
        if ( ! empty( $context['target_keywords'] ) ) {
            $keywords = is_array( $context['target_keywords'] ) ? $context['target_keywords'] : array( $context['target_keywords'] );
            foreach ( $keywords as $keyword ) {
                if ( stripos( $question, $keyword ) !== false ) {
                    $score += 0.2;
                    break;
                }
            }
        }

        // Answer includes structured data potential
        if ( ! empty( $settings['bullets'] ) || ! empty( $settings['citations'] ) ) {
            $score += 0.1;
        }

        // Entity linkage for rich snippets
        if ( ! empty( $settings['entity_id'] ) ) {
            $score += 0.2;
        }

        return min( 1.0, $score );
    }

    /**
     * Determine quality level from score
     */
    protected function determine_quality_level( $score ) {
        if ( $score >= self::SCORE_THRESHOLDS['excellent'] ) {
            return 'excellent';
        } elseif ( $score >= self::SCORE_THRESHOLDS['good'] ) {
            return 'good';
        } elseif ( $score >= self::SCORE_THRESHOLDS['fair'] ) {
            return 'fair';
        } elseif ( $score >= self::SCORE_THRESHOLDS['poor'] ) {
            return 'poor';
        } else {
            return 'critical';
        }
    }

    /**
     * Generate recommendations based on scores
     */
    protected function generate_recommendations( $scores, $settings ) {
        $recommendations = array();

        // Content completeness recommendations
        if ( $scores['content_completeness'] < 0.7 ) {
            if ( empty( $settings['question'] ) || strlen( trim( $settings['question'] ) ) < 10 ) {
                $recommendations[] = 'Add a clear, specific question (minimum 10 characters)';
            }
            if ( empty( $settings['answer'] ) || strlen( trim( $settings['answer'] ) ) < 50 ) {
                $recommendations[] = 'Provide a comprehensive answer (minimum 50 characters)';
            }
        }

        // Confidence recommendations
        if ( $scores['confidence_score'] < 0.6 ) {
            $recommendations[] = 'Consider using auto-population to generate content from page headings';
        }

        // Citation recommendations (now evidence-based)
        if ( $scores['citation_quality'] < 0.5 ) {
            $recommendations[] = 'Add high-quality evidence citations (prefer Tier 1: Study+Year sources)';
            $recommendations[] = 'Include source passages that directly support your answer';
        }

        // Entity anchor recommendations
        if ( $scores['entity_anchor_score'] < 0.6 ) {
            $recommendations[] = 'Strengthen entity anchoring with higher-tier evidence sources';
            $recommendations[] = 'Ensure evidence confidence is above 0.6 for better entity linkage';
        }

        // Content quality recommendations
        if ( $scores['content_quality'] < 0.7 ) {
            $recommendations[] = 'Ensure your question starts with a question word (What, How, Why, etc.)';
            $recommendations[] = 'Provide detailed, structured answers with multiple paragraphs if needed';
        }

        return array_slice( $recommendations, 0, 5 ); // Limit to top 5 recommendations
    }

    /**
     * Get quality level label and color
     */
    public function get_quality_display( $quality_level ) {
        $levels = array(
            'excellent' => array( 'label' => 'Excellent', 'color' => '#2e7d32', 'bg_color' => '#e8f5e8' ),
            'good' => array( 'label' => 'Good', 'color' => '#1976d2', 'bg_color' => '#e3f2fd' ),
            'fair' => array( 'label' => 'Fair', 'color' => '#f57c00', 'bg_color' => '#fff3e0' ),
            'poor' => array( 'label' => 'Poor', 'color' => '#d32f2f', 'bg_color' => '#ffebee' ),
            'critical' => array( 'label' => 'Critical', 'color' => '#7b1fa2', 'bg_color' => '#f3e5f5' ),
        );

        return $levels[$quality_level] ?? $levels['critical'];
    }

    /**
     * Validate AnswerCard for publishing
     */
    public function validate_for_publish( $settings, $context = array() ) {
        $score_data = $this->calculate_score( $settings, $context );

        $errors = array();
        $warnings = array();

        // Critical errors
        if ( empty( $settings['question'] ) ) {
            $errors[] = 'Question is required';
        }
        if ( empty( $settings['answer'] ) ) {
            $errors[] = 'Answer is required';
        }

        // Quality warnings
        if ( $score_data['total_score'] < self::SCORE_THRESHOLDS['fair'] ) {
            $warnings[] = 'AnswerCard quality is below recommended threshold';
        }

        if ( $score_data['scores']['confidence_score'] < 0.5 ) {
            $warnings[] = 'Low confidence score - consider reviewing content accuracy';
        }

        // Evidence quality warnings
        $evidence = $settings['evidence'] ?? array();
        if ( ! empty( $evidence['confidence'] ) && $evidence['confidence'] < 0.6 ) {
            $warnings[] = 'Low evidence confidence - consider regenerating with stronger sources';
        }

        if ( empty( $evidence['tier'] ) ) {
            $warnings[] = 'Missing evidence tier classification - evidence strength cannot be determined';
        }

        return array(
            'can_publish' => empty( $errors ),
            'errors' => $errors,
            'warnings' => $warnings,
            'score_data' => $score_data,
        );
    }
}