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
        'evidence_confidence' => 0.15,
        'citation_quality' => 0.20, // Increased for evidence-based scoring
        'entity_anchor_score' => 0.20, // New: evidence strength weighting
        'metadata' => 0.25,
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
        $content_quality = $this->score_content_quality( $settings );
        $seo_optimization = $this->score_seo_optimization( $settings, $context );
        $metadata_score = $this->score_metadata( $content_quality, $seo_optimization );
        $citation_data = $this->calculate_citation_score( $settings );

        $scores = array(
            'content_completeness' => $this->score_content_completeness( $settings ),
            'evidence_confidence' => $this->score_confidence( $settings ),
            'citation_quality' => $citation_data['score'],
            'entity_anchor_score' => $this->score_entity_anchor( $settings, $context ), // New: evidence-based entity anchoring
            'metadata' => $metadata_score,
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
        $recommendations = $this->generate_recommendations(
            $scores,
            $settings,
            array(
                'content_quality' => $content_quality,
                'seo_optimization' => $seo_optimization,
            )
        );
        $reasons         = $this->get_confidence_reasons( $settings, $context );

        return array(
            'total_score' => round( $total_score, 3 ),
            'quality_level' => $quality_level,
            'scores' => $scores,
            'citation_contributions' => $citation_data['contributions'],
            'recommendations' => $recommendations,
            'reasons' => $reasons,
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
        $confidence = $settings['confidence_score'] ?? ( $settings['evidence']['confidence'] ?? 0.5 );

        // Confidence score maps directly (0.0 to 1.0)
        return max( 0, min( 1, $confidence ) );
    }

    /**
     * Score citation quality with evidence tier weighting
     */
    protected function score_citations( $settings ) {
        $citation_data = $this->calculate_citation_score( $settings );
        return $citation_data['score'];
    }

    /**
     * Get per-citation contributions for debugging and QA.
     *
     * @param array $citations Citation list.
     * @return array Contributions.
     */
    public function get_citation_contributions( $citations ) {
        $data = $this->calculate_citation_score( array( 'citations' => $citations ) );
        return $data['contributions'];
    }

    /**
     * Calculate citation score and contribution details.
     *
     * @param array $settings Card settings.
     * @return array Score and contributions.
     */
    protected function calculate_citation_score( $settings ) {
        $citations = $settings['citations'] ?? array();
        if ( empty( $citations ) ) {
            return array(
                'score' => 0.2,
                'contributions' => array(),
            );
        }

        // base
        $score = 0.0;
        $total_weight = 0.0;
        $contributions = array();

        // Tier weights as defined
        $tier_weights = array( 'tier1' => 0.4, 'tier2' => 0.25, 'tier3' => 0.15 );

        foreach ( $citations as $idx => $c ) {
            $tier = strtolower( $c['tier'] ?? '' );
            $conf = isset( $c['confidence'] ) ? floatval( $c['confidence'] ) : 0.5;

            // default small weight if unknown
            $w = isset( $tier_weights[$tier] ) ? $tier_weights[$tier] : 0.05;

            // quality factor: confidence + presence of author/date/doi increases reliability
            $quality = 0.5;
            if ( ! empty( $c['author'] ) ) $quality += 0.15;
            if ( ! empty( $c['publisher'] ) ) $quality += 0.1;
            if ( ! empty( $c['date'] ) ) $quality += 0.1;
            if ( ! empty( $c['year'] ) ) $quality += 0.1;
            if ( ! empty( $c['doi'] ) ) $quality += 0.1;
            $quality = min( 1.0, $quality + $conf * 0.3 );

            $raw = $w * $quality;
            $score += $raw;
            $total_weight += $w;
            $contributions[] = array(
                'idx' => $idx,
                'tier' => $tier ?: 'unknown',
                'contribution_raw' => $raw,
            );
        }

        // normalize to [0,1]
        if ( $total_weight > 0 ) {
            $score = $score / $total_weight;
        }

        $normalized_contributions = array();
        if ( $total_weight > 0 ) {
            foreach ( $contributions as $item ) {
                $normalized_contributions[] = array(
                    'idx' => $item['idx'],
                    'tier' => $item['tier'],
                    'contribution' => round( $item['contribution_raw'] / $total_weight, 3 ),
                );
            }
        }

        // map into expected [0.0 - 1.0] output with a small floor
        return array(
            'score' => max( 0.2, min( 1.0, $score ) ),
            'contributions' => $normalized_contributions,
        );
    }

    /**
     * Score entity anchor strength (evidence-based entity linkage)
     */
    protected function score_entity_anchor( $settings, $context = array() ) {
        $evidence = $settings['evidence'] ?? array();
        $anchor_entities = ! empty( $evidence['anchor_entities'] ) ? (array) $evidence['anchor_entities'] : array();

        $resolution = $this->get_resolved_entity_counts( $settings, $context );
        if ( ! empty( $anchor_entities ) ) {
            $anchor_count = count( $anchor_entities );
        } else {
            $anchor_count = $resolution['resolved_count'];
        }

        // scoring rule: 0 anchors -> 0.3, 1-2 anchors -> 0.7, 3+ anchors -> 1.0
        if ( $anchor_count >= 3 ) {
            $score = 1.0;
        } elseif ( $anchor_count >= 1 ) {
            $score = 0.7;
        } else {
            $score = 0.3;
        }

        if ( empty( $anchor_entities ) && $resolution['unresolved_count'] > 5 ) {
            $score = max( 0, $score - 0.05 );
        }

        return $score;
    }

    /**
     * Get confidence reasons for low-quality signals.
     *
     * @param array $card Answer card data.
     * @return array Reasons array with code, label, and severity.
     */
    public function get_confidence_reasons( $card, $context = array() ) {
        $reasons   = array();
        $evidence  = $card['evidence'] ?? array();
        $citations = $card['citations'] ?? array();
        $entities  = $card['entities'] ?? array();

        $tier = strtolower( $evidence['tier'] ?? '' );
        if ( empty( $tier ) || 'tier3' === $tier ) {
            $reasons[] = array(
                'code'     => 'only_tier3',
                'label'    => 'Only Tier-3 or unclassified evidence',
                'severity' => 'high',
            );
        }

        $has_source_passage = ! empty( $evidence['source_passage'] );
        if ( ! $has_source_passage ) {
            $reasons[] = array(
                'code'     => 'no_source_passage',
                'label'    => 'No source passage provided',
                'severity' => 'high',
            );
        }

        $has_author = false;
        $has_year   = false;
        if ( is_array( $citations ) ) {
            foreach ( $citations as $citation ) {
                if ( ! is_array( $citation ) ) {
                    continue;
                }
                if ( ! empty( $citation['author'] ) ) {
                    $has_author = true;
                }
                if ( ! empty( $citation['year'] ) ) {
                    $has_year = true;
                }
            }
        }

        if ( ! $has_author ) {
            $reasons[] = array(
                'code'     => 'missing_author',
                'label'    => 'Missing: author attribution',
                'severity' => 'medium',
            );
        }

        if ( ! $has_year ) {
            $reasons[] = array(
                'code'     => 'missing_year',
                'label'    => 'Missing: publication year',
                'severity' => 'medium',
            );
        }

        $anchor_entities = $evidence['anchor_entities'] ?? array();
        $anchor_count    = is_array( $anchor_entities ) ? count( $anchor_entities ) : 0;
        $resolution      = $this->get_resolved_entity_counts( $card, $context );
        if ( 0 === $anchor_count ) {
            $anchor_count = $resolution['resolved_count'];
        }

        if ( $anchor_count < 2 ) {
            $reasons[] = array(
                'code'     => 'few_anchor_entities',
                'label'    => 'Few anchor entities (fewer than 2)',
                'severity' => 'low',
            );
        }

        if ( $anchor_count === 0 && ! empty( $entities ) && $resolution['resolved_count'] === 0 ) {
            $reasons[] = array(
                'code'     => 'entities_unresolved',
                'label'    => 'Entities are unresolved; resolve or anchor to use in scoring',
                'severity' => 'medium',
            );
        }

        return $reasons;
    }

    /**
     * Resolve entity counts for scoring.
     *
     * @param array $settings Card data.
     * @param array $context Scoring context.
     * @return array {resolved_count, unresolved_count}
     */
    protected function get_resolved_entity_counts( $settings, $context = array() ) {
        $entities = $settings['entities'] ?? array();
        $entity_names = array();
        foreach ( (array) $entities as $entity ) {
            if ( is_array( $entity ) ) {
                $name = $entity['name'] ?? '';
            } else {
                $name = $entity;
            }
            if ( $name ) {
                $entity_names[] = $name;
            }
        }

        $resolved_ids = array();
        $resolved_count = 0;

        if ( class_exists( '\\KHM_SEO\\GEO\\Entity\\EntityManager' ) && function_exists( 'get_post' ) ) {
            $manager = new \KHM_SEO\GEO\Entity\EntityManager();

            foreach ( $entity_names as $name ) {
                $entity = $manager->find_entity_by_canonical( $name, 'site' );
                if ( $entity && ! empty( $entity->same_as ) ) {
                    $resolved_ids[ $entity->id ] = true;
                }
            }

            $post_id = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;
            if ( $post_id ) {
                $page_entities = $manager->get_post_entities( $post_id );
                foreach ( $page_entities as $page_entity ) {
                    if ( ! in_array( $page_entity->role, array( 'about', 'primary' ), true ) ) {
                        continue;
                    }
                    if ( floatval( $page_entity->confidence ) < 0.6 ) {
                        continue;
                    }
                    $resolved_ids[ $page_entity->entity_id ] = true;
                }
            }
        }

        $resolved_count = count( $resolved_ids );
        $unresolved_count = max( 0, count( $entity_names ) - $resolved_count );

        return array(
            'resolved_count' => $resolved_count,
            'unresolved_count' => $unresolved_count,
        );
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
     * Score metadata quality using content and SEO signals.
     *
     * @param float $content_quality Content quality score.
     * @param float $seo_optimization SEO optimization score.
     * @return float Metadata score.
     */
    protected function score_metadata( $content_quality, $seo_optimization ) {
        $content_quality = max( 0, min( 1, $content_quality ) );
        $seo_optimization = max( 0, min( 1, $seo_optimization ) );

        return min( 1.0, ( $content_quality + $seo_optimization ) / 2 );
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
    protected function generate_recommendations( $scores, $settings, $detail_scores = array() ) {
        $recommendations = array();
        $content_quality = $detail_scores['content_quality'] ?? 0;
        $seo_optimization = $detail_scores['seo_optimization'] ?? 0;

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
        if ( $scores['evidence_confidence'] < 0.6 ) {
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
        if ( $content_quality < 0.7 || $seo_optimization < 0.7 ) {
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

        if ( $score_data['scores']['evidence_confidence'] < 0.5 ) {
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
