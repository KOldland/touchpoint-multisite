<?php
/**
 * Readability Analyzer
 * 
 * Analyzes content readability using various metrics including
 * Flesch Reading Ease, sentence structure, and other readability factors.
 *
 * @package KHM_SEO
 * @subpackage Analysis
 * @version 1.0.0
 */

namespace KHM_SEO\Analysis;

/**
 * Readability Analysis Class
 * 
 * Provides comprehensive readability analysis including:
 * - Flesch Reading Ease Score
 * - Sentence length analysis
 * - Paragraph structure
 * - Transition word usage
 * - Passive voice detection
 */
class ReadabilityAnalyzer {
    
    /**
     * Analysis configuration
     *
     * @var array
     */
    private $config;
    
    /**
     * Transition words for flow analysis
     *
     * @var array
     */
    private $transition_words;
    
    /**
     * Passive voice indicators
     *
     * @var array
     */
    private $passive_indicators;
    
    /**
     * Constructor
     *
     * @param array $config Analysis configuration
     */
    public function __construct( $config = [] ) {
        $this->config = $config;
        $this->init_word_lists();
    }
    
    /**
     * Initialize word lists for analysis
     */
    private function init_word_lists() {
        $this->transition_words = [
            'however', 'therefore', 'furthermore', 'moreover', 'additionally',
            'consequently', 'meanwhile', 'nevertheless', 'nonetheless',
            'similarly', 'likewise', 'instead', 'otherwise', 'thus',
            'hence', 'accordingly', 'besides', 'indeed', 'certainly',
            'undoubtedly', 'subsequently', 'finally', 'initially',
            'first', 'second', 'third', 'next', 'then', 'later',
            'earlier', 'before', 'after', 'during', 'while',
            'although', 'though', 'whereas', 'despite', 'in contrast',
            'on the other hand', 'in addition', 'as a result',
            'for example', 'for instance', 'in particular', 'specifically',
            'in conclusion', 'to summarize', 'in summary', 'overall'
        ];
        
        $this->passive_indicators = [
            'was', 'were', 'been', 'being', 'is', 'are', 'am',
            'has been', 'have been', 'had been', 'will be',
            'would be', 'could be', 'should be', 'might be'
        ];
    }
    
    /**
     * Perform comprehensive readability analysis
     *
     * @param string $content Content to analyze
     * @return array Analysis results
     */
    public function analyze( $content ) {
        $analysis = [
            'score' => 0,
            'issues' => [],
            'improvements' => [],
            'metrics' => []
        ];
        
        if ( empty( $content ) ) {
            $analysis['issues'][] = [
                'type' => 'error',
                'message' => 'No content to analyze',
                'impact' => 'high',
                'suggestion' => 'Add content to perform readability analysis'
            ];
            return $analysis;
        }
        
        $clean_content = $this->clean_content( $content );
        
        // Basic metrics
        $word_count = $this->count_words( $clean_content );
        $sentence_count = $this->count_sentences( $clean_content );
        $paragraph_count = $this->count_paragraphs( $content );
        $syllable_count = $this->count_syllables( $clean_content );
        
        $analysis['metrics'] = [
            'word_count' => $word_count,
            'sentence_count' => $sentence_count,
            'paragraph_count' => $paragraph_count,
            'syllable_count' => $syllable_count,
            'avg_words_per_sentence' => $sentence_count > 0 ? round( $word_count / $sentence_count, 1 ) : 0,
            'avg_syllables_per_word' => $word_count > 0 ? round( $syllable_count / $word_count, 1 ) : 0
        ];
        
        // Flesch Reading Ease Score
        $flesch_score = $this->calculate_flesch_reading_ease( $word_count, $sentence_count, $syllable_count );
        $analysis['metrics']['flesch_score'] = $flesch_score;
        $this->evaluate_flesch_score( $analysis, $flesch_score );
        
        // Sentence length analysis
        $this->analyze_sentence_length( $analysis, $content );
        
        // Paragraph structure analysis
        $this->analyze_paragraph_structure( $analysis, $content );
        
        // Transition word analysis
        $this->analyze_transition_words( $analysis, $clean_content );
        
        // Passive voice analysis
        $this->analyze_passive_voice( $analysis, $clean_content );
        
        // Subheading analysis
        $this->analyze_subheadings( $analysis, $content );
        
        // Normalize overall score
        $analysis['score'] = \min( 100, \max( 0, $analysis['score'] ) );
        
        return $analysis;
    }
    
    /**
     * Calculate Flesch Reading Ease Score
     *
     * @param int $word_count Total words
     * @param int $sentence_count Total sentences
     * @param int $syllable_count Total syllables
     * @return float Flesch Reading Ease Score
     */
    private function calculate_flesch_reading_ease( $word_count, $sentence_count, $syllable_count ) {
        if ( $word_count === 0 || $sentence_count === 0 ) {
            return 0;
        }
        
        $avg_sentence_length = $word_count / $sentence_count;
        $avg_syllables_per_word = $syllable_count / $word_count;
        
        // Flesch Reading Ease formula
        $score = 206.835 - ( 1.015 * $avg_sentence_length ) - ( 84.6 * $avg_syllables_per_word );
        
        return \round( \max( 0, \min( 100, $score ) ), 1 );
    }
    
    /**
     * Evaluate Flesch Reading Ease Score
     *
     * @param array &$analysis Analysis array to update
     * @param float $score Flesch score
     */
    private function evaluate_flesch_score( &$analysis, $score ) {
        if ( $score >= 90 ) {
            $analysis['improvements'][] = 'Excellent readability (very easy to read)';
            $analysis['score'] += 100;
        } elseif ( $score >= 80 ) {
            $analysis['improvements'][] = 'Good readability (easy to read)';
            $analysis['score'] += 90;
        } elseif ( $score >= 70 ) {
            $analysis['improvements'][] = 'Fairly easy to read';
            $analysis['score'] += 80;
        } elseif ( $score >= 60 ) {
            $analysis['improvements'][] = 'Standard readability';
            $analysis['score'] += 70;
        } elseif ( $score >= 50 ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Fairly difficult to read',
                'impact' => 'medium',
                'suggestion' => 'Consider shorter sentences and simpler words'
            ];
            $analysis['score'] += 50;
        } elseif ( $score >= 30 ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Difficult to read',
                'impact' => 'medium',
                'suggestion' => 'Simplify language and sentence structure'
            ];
            $analysis['score'] += 30;
        } else {
            $analysis['issues'][] = [
                'type' => 'error',
                'message' => 'Very difficult to read',
                'impact' => 'high',
                'suggestion' => 'Significantly simplify language and use shorter sentences'
            ];
            $analysis['score'] += 10;
        }
    }
    
    /**
     * Analyze sentence length
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Full content with HTML
     */
    private function analyze_sentence_length( &$analysis, $content ) {
        $sentences = $this->extract_sentences( $content );
        $total_length = 0;
        $long_sentences = 0;
        $max_length = $this->config['readability']['max_sentence_length'] ?? 20;
        
        foreach ( $sentences as $sentence ) {
            $word_count = $this->count_words( $sentence );
            $total_length += $word_count;
            
            if ( $word_count > $max_length ) {
                $long_sentences++;
            }
        }
        
        $avg_sentence_length = \count( $sentences ) > 0 ? $total_length / \count( $sentences ) : 0;
        $analysis['metrics']['avg_sentence_length'] = \round( $avg_sentence_length, 1 );
        $analysis['metrics']['long_sentences'] = $long_sentences;
        
        if ( $long_sentences === 0 ) {
            $analysis['improvements'][] = 'All sentences are an appropriate length';
            $analysis['score'] += 20;
        } elseif ( $long_sentences <= \count( $sentences ) * 0.1 ) { // Less than 10% long
            $analysis['improvements'][] = 'Most sentences are an appropriate length';
            $analysis['score'] += 15;
        } else {
            $percentage = \round( ( $long_sentences / \count( $sentences ) ) * 100 );
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => "{$percentage}% of sentences are too long",
                'impact' => 'medium',
                'suggestion' => 'Break long sentences into shorter ones for better readability'
            ];
            $analysis['score'] += \max( 0, 20 - $long_sentences * 2 );
        }
    }
    
    /**
     * Analyze paragraph structure
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Full content with HTML
     */
    private function analyze_paragraph_structure( &$analysis, $content ) {
        $paragraphs = $this->extract_paragraphs( $content );
        $long_paragraphs = 0;
        $max_paragraph_length = $this->config['readability']['max_paragraph_length'] ?? 150;
        
        foreach ( $paragraphs as $paragraph ) {
            $word_count = $this->count_words( $paragraph );
            if ( $word_count > $max_paragraph_length ) {
                $long_paragraphs++;
            }
        }
        
        $analysis['metrics']['long_paragraphs'] = $long_paragraphs;
        
        if ( $long_paragraphs === 0 ) {
            $analysis['improvements'][] = 'Paragraphs are an appropriate length';
            $analysis['score'] += 15;
        } else {
            $percentage = \round( ( $long_paragraphs / \count( $paragraphs ) ) * 100 );
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => "{$percentage}% of paragraphs are too long",
                'impact' => 'low',
                'suggestion' => 'Break long paragraphs into shorter ones'
            ];
            $analysis['score'] += \max( 0, 15 - $long_paragraphs );
        }
    }
    
    /**
     * Analyze transition word usage
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Clean content
     */
    private function analyze_transition_words( &$analysis, $content ) {
        $sentence_count = $this->count_sentences( $content );
        $transition_count = 0;
        
        foreach ( $this->transition_words as $transition ) {
            if ( \stripos( $content, $transition ) !== false ) {
                $transition_count++;
            }
        }
        
        $transition_percentage = $sentence_count > 0 ? ( $transition_count / $sentence_count ) * 100 : 0;
        $analysis['metrics']['transition_percentage'] = \round( $transition_percentage, 1 );
        
        $threshold = $this->config['readability']['transition_word_threshold'] ?? 30;
        
        if ( $transition_percentage >= $threshold ) {
            $analysis['improvements'][] = 'Good use of transition words for flow';
            $analysis['score'] += 15;
        } elseif ( $transition_percentage >= $threshold * 0.5 ) {
            $analysis['improvements'][] = 'Decent use of transition words';
            $analysis['score'] += 10;
        } else {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Limited use of transition words',
                'impact' => 'low',
                'suggestion' => 'Add transition words to improve content flow'
            ];
            $analysis['score'] += 5;
        }
    }
    
    /**
     * Analyze passive voice usage
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Clean content
     */
    private function analyze_passive_voice( &$analysis, $content ) {
        $sentences = $this->extract_sentences( $content );
        $passive_sentences = 0;
        
        foreach ( $sentences as $sentence ) {
            if ( $this->contains_passive_voice( $sentence ) ) {
                $passive_sentences++;
            }
        }
        
        $passive_percentage = \count( $sentences ) > 0 ? ( $passive_sentences / \count( $sentences ) ) * 100 : 0;
        $analysis['metrics']['passive_voice_percentage'] = \round( $passive_percentage, 1 );
        
        $threshold = $this->config['readability']['passive_voice_threshold'] ?? 10;
        
        if ( $passive_percentage <= $threshold ) {
            $analysis['improvements'][] = 'Good use of active voice';
            $analysis['score'] += 15;
        } elseif ( $passive_percentage <= $threshold * 2 ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Some passive voice usage detected',
                'impact' => 'low',
                'suggestion' => 'Consider using more active voice for better engagement'
            ];
            $analysis['score'] += 10;
        } else {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'High passive voice usage detected',
                'impact' => 'medium',
                'suggestion' => 'Replace passive voice with active voice where possible'
            ];
            $analysis['score'] += 5;
        }
    }
    
    /**
     * Analyze subheading usage
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Full content with HTML
     */
    private function analyze_subheadings( &$analysis, $content ) {
        $word_count = $this->count_words( $this->clean_content( $content ) );
        $headings = $this->extract_headings( $content );
        $total_headings = \count( $headings );
        
        $analysis['metrics']['heading_count'] = $total_headings;
        
        // Recommend one heading per 300 words
        $recommended_headings = \max( 1, \floor( $word_count / 300 ) );
        
        if ( $total_headings >= $recommended_headings ) {
            $analysis['improvements'][] = 'Good use of subheadings for structure';
            $analysis['score'] += 10;
        } elseif ( $total_headings >= $recommended_headings * 0.5 ) {
            $analysis['improvements'][] = 'Decent subheading structure';
            $analysis['score'] += 7;
        } else {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Content could benefit from more subheadings',
                'impact' => 'low',
                'suggestion' => 'Add subheadings to break up long sections of text'
            ];
            $analysis['score'] += 3;
        }
    }
    
    /**
     * Count words in text
     *
     * @param string $text Text to count
     * @return int Word count
     */
    public function count_words( $text ) {
        if ( empty( \trim( $text ) ) ) {
            return 0;
        }
        return \count( \preg_split( '/\s+/', \trim( $text ) ) );
    }
    
    /**
     * Count sentences in text
     *
     * @param string $text Text to count
     * @return int Sentence count
     */
    public function count_sentences( $text ) {
        if ( empty( \trim( $text ) ) ) {
            return 0;
        }
        
        // Split on sentence-ending punctuation
        $sentences = \preg_split( '/[.!?]+/', $text );
        
        // Filter out empty sentences
        $sentences = \array_filter( $sentences, function( $sentence ) {
            return ! empty( \trim( $sentence ) );
        });
        
        return \count( $sentences );
    }
    
    /**
     * Count paragraphs in content
     *
     * @param string $content Content with HTML
     * @return int Paragraph count
     */
    public function count_paragraphs( $content ) {
        // Count paragraph tags and line breaks
        $p_count = \substr_count( $content, '</p>' );
        $br_count = \substr_count( $content, '<br' );
        
        // If no HTML, count double line breaks
        if ( $p_count === 0 && $br_count === 0 ) {
            $paragraphs = \preg_split( '/\n\s*\n/', $content );
            return \count( \array_filter( $paragraphs, function( $p ) {
                return ! empty( \trim( $p ) );
            }));
        }
        
        return \max( $p_count, 1 );
    }
    
    /**
     * Count syllables in text (simplified algorithm)
     *
     * @param string $text Text to analyze
     * @return int Syllable count
     */
    private function count_syllables( $text ) {
        $words = \preg_split( '/\s+/', \strtolower( $text ) );
        $total_syllables = 0;
        
        foreach ( $words as $word ) {
            $word = \preg_replace( '/[^a-z]/', '', $word );
            if ( empty( $word ) ) {
                continue;
            }
            
            $syllables = $this->count_syllables_in_word( $word );
            $total_syllables += $syllables;
        }
        
        return $total_syllables;
    }
    
    /**
     * Count syllables in a single word
     *
     * @param string $word Word to analyze
     * @return int Syllable count
     */
    private function count_syllables_in_word( $word ) {
        if ( \strlen( $word ) <= 3 ) {
            return 1;
        }
        
        // Count vowel groups
        $vowels = 'aeiouy';
        $syllables = 0;
        $previous_was_vowel = false;
        
        for ( $i = 0; $i < \strlen( $word ); $i++ ) {
            $is_vowel = \strpos( $vowels, $word[$i] ) !== false;
            
            if ( $is_vowel && ! $previous_was_vowel ) {
                $syllables++;
            }
            
            $previous_was_vowel = $is_vowel;
        }
        
        // Adjust for silent 'e'
        if ( \substr( $word, -1 ) === 'e' ) {
            $syllables--;
        }
        
        // Minimum one syllable
        return \max( 1, $syllables );
    }
    
    /**
     * Check if sentence contains passive voice
     *
     * @param string $sentence Sentence to check
     * @return bool True if passive voice detected
     */
    private function contains_passive_voice( $sentence ) {
        $sentence_lower = \strtolower( $sentence );
        
        foreach ( $this->passive_indicators as $indicator ) {
            if ( \strpos( $sentence_lower, $indicator ) !== false ) {
                // Simple check for past participle following the indicator
                $pattern = '/\b' . \preg_quote( $indicator, '/' ) . '\s+\w+ed\b/';
                if ( \preg_match( $pattern, $sentence_lower ) ) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Extract sentences from content
     *
     * @param string $content Content to process
     * @return array Array of sentences
     */
    private function extract_sentences( $content ) {
        $clean_content = $this->clean_content( $content );
        $sentences = \preg_split( '/[.!?]+/', $clean_content );
        
        return \array_filter( \array_map( 'trim', $sentences ), function( $sentence ) {
            return ! empty( $sentence );
        });
    }
    
    /**
     * Extract paragraphs from content
     *
     * @param string $content Content with HTML
     * @return array Array of paragraphs
     */
    private function extract_paragraphs( $content ) {
        // Split on paragraph tags or double line breaks
        if ( \strpos( $content, '<p>' ) !== false ) {
            $paragraphs = \preg_split( '/<\/?p[^>]*>/', $content );
        } else {
            $paragraphs = \preg_split( '/\n\s*\n/', $content );
        }
        
        $clean_paragraphs = [];
        foreach ( $paragraphs as $paragraph ) {
            $clean = \trim( \wp_strip_all_tags( $paragraph ) );
            if ( ! empty( $clean ) ) {
                $clean_paragraphs[] = $clean;
            }
        }
        
        return $clean_paragraphs;
    }
    
    /**
     * Extract headings from content
     *
     * @param string $content Content with HTML
     * @return array Array of heading texts
     */
    private function extract_headings( $content ) {
        $headings = [];
        
        for ( $i = 2; $i <= 6; $i++ ) {
            $pattern = "/<h{$i}[^>]*>(.*?)<\/h{$i}>/i";
            if ( \preg_match_all( $pattern, $content, $matches ) ) {
                foreach ( $matches[1] as $heading ) {
                    $headings[] = \wp_strip_all_tags( $heading );
                }
            }
        }
        
        return $headings;
    }
    
    /**
     * Clean content for analysis
     *
     * @param string $content Raw content
     * @return string Clean content
     */
    private function clean_content( $content ) {
        // Remove HTML tags
        $content = \wp_strip_all_tags( $content );
        // Remove extra whitespace
        $content = \preg_replace( '/\s+/', ' ', $content );
        return \trim( $content );
    }
}