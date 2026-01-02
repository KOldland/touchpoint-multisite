<?php
/**
 * Keyword Analyzer
 * 
 * Specialized class for analyzing keyword usage, density,
 * and optimization in content.
 *
 * @package KHM_SEO
 * @subpackage Analysis
 * @version 1.0.0
 */

namespace KHM_SEO\Analysis;

/**
 * Keyword Analysis Class
 * 
 * Handles keyword density analysis, keyword placement evaluation,
 * and semantic keyword suggestions.
 */
class KeywordAnalyzer {
    
    /**
     * Analysis configuration
     *
     * @var array
     */
    private $config;
    
    /**
     * Stopwords list for better keyword analysis
     *
     * @var array
     */
    private $stopwords;
    
    /**
     * Constructor
     *
     * @param array $config Analysis configuration
     */
    public function __construct( $config = [] ) {
        $this->config = $config;
        $this->init_stopwords();
    }
    
    /**
     * Initialize stopwords list
     */
    private function init_stopwords() {
        $this->stopwords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
            'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
            'to', 'was', 'will', 'with', 'would', 'you', 'your', 'this', 'these',
            'those', 'they', 'them', 'their', 'his', 'her', 'she', 'him', 'we',
            'us', 'our', 'me', 'my', 'i', 'am', 'can', 'could', 'should', 'would',
            'but', 'or', 'so', 'if', 'when', 'where', 'why', 'how', 'what', 'who'
        ];
    }
    
    /**
     * Analyze keyword usage in content
     *
     * @param string $content The content to analyze
     * @param string $focus_keyword The focus keyword
     * @param string $title The title (optional)
     * @return array Analysis results
     */
    public function analyze( $content, $focus_keyword = '', $title = '' ) {
        $analysis = [
            'score' => 0,
            'issues' => [],
            'improvements' => [],
            'metrics' => []
        ];
        
        if ( empty( $focus_keyword ) ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'No focus keyword specified',
                'impact' => 'medium',
                'suggestion' => 'Define a focus keyword to optimize your content'
            ];
            return $analysis;
        }
        
        $clean_content = $this->clean_content( $content );
        $word_count = $this->count_words( $clean_content );
        
        if ( $word_count === 0 ) {
            $analysis['issues'][] = [
                'type' => 'error',
                'message' => 'No content to analyze',
                'impact' => 'high',
                'suggestion' => 'Add content to analyze keyword usage'
            ];
            return $analysis;
        }
        
        // Calculate keyword density
        $keyword_density = $this->calculate_keyword_density( $clean_content, $focus_keyword );
        $analysis['metrics']['density'] = $keyword_density;
        $analysis['metrics']['word_count'] = $word_count;
        
        // Evaluate keyword density
        $this->evaluate_keyword_density( $analysis, $keyword_density );
        
        // Check keyword placement
        $placement_analysis = $this->analyze_keyword_placement( $content, $focus_keyword, $title );
        $analysis['metrics']['placement'] = $placement_analysis;
        
        // Score keyword placement
        $this->score_keyword_placement( $analysis, $placement_analysis );
        
        // Analyze semantic keywords
        $semantic_analysis = $this->analyze_semantic_keywords( $clean_content, $focus_keyword );
        $analysis['metrics']['semantic'] = $semantic_analysis;
        
        // Check keyword variations
        $variations = $this->find_keyword_variations( $clean_content, $focus_keyword );
        $analysis['metrics']['variations'] = $variations;
        
        if ( ! empty( $variations ) ) {
            $analysis['improvements'][] = 'Keyword variations found: ' . \implode( ', ', \array_slice( $variations, 0, 3 ) );
            $analysis['score'] += 15;
        }
        
        // Check for keyword stuffing patterns
        $stuffing_check = $this->check_keyword_stuffing( $clean_content, $focus_keyword );
        if ( $stuffing_check['is_stuffed'] ) {
            $analysis['issues'][] = [
                'type' => 'error',
                'message' => 'Potential keyword stuffing detected',
                'impact' => 'high',
                'suggestion' => 'Reduce keyword frequency and use more natural language'
            ];
            $analysis['metrics']['keyword_stuffing'] = true;
        } else {
            $analysis['metrics']['keyword_stuffing'] = false;
            $analysis['score'] += 10;
        }
        
        // Normalize score
        $analysis['score'] = \min( 100, $analysis['score'] );
        
        return $analysis;
    }
    
    /**
     * Calculate keyword density
     *
     * @param string $content Clean content
     * @param string $keyword Focus keyword
     * @return float Keyword density percentage
     */
    public function calculate_keyword_density( $content, $keyword ) {
        $word_count = $this->count_words( $content );
        if ( $word_count === 0 ) {
            return 0;
        }
        
        $keyword_count = $this->count_keyword_occurrences( $content, $keyword );
        return ( $keyword_count / $word_count ) * 100;
    }
    
    /**
     * Count keyword occurrences
     *
     * @param string $content Content to search
     * @param string $keyword Keyword to count
     * @return int Number of occurrences
     */
    public function count_keyword_occurrences( $content, $keyword ) {
        if ( empty( $keyword ) || empty( $content ) ) {
            return 0;
        }
        
        $keyword_lower = \strtolower( $keyword );
        $content_lower = \strtolower( $content );
        
        // Count exact matches
        $exact_matches = \substr_count( $content_lower, $keyword_lower );
        
        // Count word boundary matches (more accurate)
        $pattern = '/\b' . \preg_quote( $keyword_lower, '/' ) . '\b/i';
        $boundary_matches = \preg_match_all( $pattern, $content_lower );
        
        return \max( $exact_matches, $boundary_matches );
    }
    
    /**
     * Check if keyword is present in text
     *
     * @param string $text Text to check
     * @param string $keyword Keyword to find
     * @return bool True if keyword is present
     */
    public function check_keyword_presence( $text, $keyword ) {
        if ( empty( $keyword ) || empty( $text ) ) {
            return false;
        }
        
        return \stripos( $text, $keyword ) !== false;
    }
    
    /**
     * Get keyword position in text (first occurrence)
     *
     * @param string $text Text to search
     * @param string $keyword Keyword to find
     * @return int Position (word number) or -1 if not found
     */
    public function get_keyword_position( $text, $keyword ) {
        if ( empty( $keyword ) || empty( $text ) ) {
            return -1;
        }
        
        $words = \explode( ' ', \strtolower( $text ) );
        $keyword_lower = \strtolower( $keyword );
        
        foreach ( $words as $index => $word ) {
            if ( \strpos( $word, $keyword_lower ) !== false ) {
                return $index + 1; // Return 1-based position
            }
        }
        
        return -1;
    }
    
    /**
     * Evaluate keyword density and add to analysis
     *
     * @param array &$analysis Analysis array to update
     * @param float $density Keyword density percentage
     */
    private function evaluate_keyword_density( &$analysis, $density ) {
        $min_density = $this->config['keyword_analysis']['min_density'] ?? 0.5;
        $optimal_density = $this->config['keyword_analysis']['optimal_density'] ?? 1.5;
        $max_density = $this->config['keyword_analysis']['max_density'] ?? 3.0;
        
        if ( $density === 0 ) {
            $analysis['issues'][] = [
                'type' => 'error',
                'message' => 'Focus keyword not found in content',
                'impact' => 'high',
                'suggestion' => 'Include your focus keyword naturally in the content'
            ];
        } elseif ( $density < $min_density ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => \sprintf( 'Keyword density too low (%.1f%%)', $density ),
                'impact' => 'medium',
                'suggestion' => \sprintf( 'Increase keyword usage to at least %.1f%%', $min_density )
            ];
            $analysis['score'] += 40;
        } elseif ( $density > $max_density ) {
            $analysis['issues'][] = [
                'type' => 'error',
                'message' => \sprintf( 'Keyword density too high (%.1f%%)', $density ),
                'impact' => 'high',
                'suggestion' => \sprintf( 'Reduce keyword usage to under %.1f%%', $max_density )
            ];
            $analysis['score'] += 20;
        } elseif ( $density > $optimal_density ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => \sprintf( 'Keyword density slightly high (%.1f%%)', $density ),
                'impact' => 'low',
                'suggestion' => \sprintf( 'Consider reducing to around %.1f%% for optimal density', $optimal_density )
            ];
            $analysis['score'] += 80;
        } else {
            $analysis['improvements'][] = \sprintf( 'Optimal keyword density (%.1f%%)', $density );
            $analysis['score'] += 100;
        }
    }
    
    /**
     * Analyze keyword placement in strategic locations
     *
     * @param string $content Full content
     * @param string $keyword Focus keyword
     * @param string $title Title text
     * @return array Placement analysis
     */
    private function analyze_keyword_placement( $content, $keyword, $title ) {
        $placement = [
            'in_title' => false,
            'in_first_paragraph' => false,
            'in_h1' => false,
            'in_h2' => false,
            'in_h3' => false,
            'in_last_paragraph' => false,
            'in_alt_text' => false,
            'in_url' => false // Would need URL to check
        ];
        
        // Check title
        $placement['in_title'] = $this->check_keyword_presence( $title, $keyword );
        
        // Check headings
        $headings = $this->extract_headings( $content );
        $placement['in_h1'] = $this->check_keyword_in_headings( $headings['h1'], $keyword );
        $placement['in_h2'] = $this->check_keyword_in_headings( $headings['h2'], $keyword );
        $placement['in_h3'] = $this->check_keyword_in_headings( $headings['h3'], $keyword );
        
        // Check first and last paragraphs
        $paragraphs = $this->extract_paragraphs( $content );
        if ( ! empty( $paragraphs ) ) {
            $placement['in_first_paragraph'] = $this->check_keyword_presence( $paragraphs[0], $keyword );
            $placement['in_last_paragraph'] = $this->check_keyword_presence( \end( $paragraphs ), $keyword );
        }
        
        // Check alt text
        $placement['in_alt_text'] = $this->check_keyword_in_alt_text( $content, $keyword );
        
        return $placement;
    }
    
    /**
     * Score keyword placement and update analysis
     *
     * @param array &$analysis Analysis array to update
     * @param array $placement Placement analysis results
     */
    private function score_keyword_placement( &$analysis, $placement ) {
        $config = $this->config['keywords'] ?? [];
        $score = 0;
        
        // Title placement
        if ( $placement['in_title'] ) {
            $analysis['improvements'][] = 'Keyword found in title';
            $score += $config['title_keyword_bonus'] ?? 15;
        } else {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Keyword not found in title',
                'impact' => 'medium',
                'suggestion' => 'Include focus keyword in the title'
            ];
        }
        
        // H1 placement
        if ( $placement['in_h1'] ) {
            $analysis['improvements'][] = 'Keyword found in H1 heading';
            $score += $config['h1_keyword_bonus'] ?? 10;
        }
        
        // First paragraph placement
        if ( $placement['in_first_paragraph'] ) {
            $analysis['improvements'][] = 'Keyword found in first paragraph';
            $score += $config['first_paragraph_bonus'] ?? 8;
        } else {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Keyword not in first paragraph',
                'impact' => 'medium',
                'suggestion' => 'Include focus keyword in the opening paragraph'
            ];
        }
        
        // Alt text placement
        if ( $placement['in_alt_text'] ) {
            $analysis['improvements'][] = 'Keyword found in image alt text';
            $score += $config['alt_text_bonus'] ?? 5;
        }
        
        // Subheading placement bonus
        if ( $placement['in_h2'] || $placement['in_h3'] ) {
            $analysis['improvements'][] = 'Keyword found in subheadings';
            $score += 5;
        }
        
        $analysis['score'] += $score;
    }
    
    /**
     * Analyze semantic keywords (LSI keywords)
     *
     * @param string $content Content to analyze
     * @param string $focus_keyword Primary keyword
     * @return array Semantic analysis
     */
    private function analyze_semantic_keywords( $content, $focus_keyword ) {
        // Simple semantic keyword detection
        $semantic_keywords = $this->generate_semantic_keywords( $focus_keyword );
        $found_semantic = [];
        
        foreach ( $semantic_keywords as $keyword ) {
            if ( $this->check_keyword_presence( $content, $keyword ) ) {
                $found_semantic[] = $keyword;
            }
        }
        
        return [
            'suggested' => $semantic_keywords,
            'found' => $found_semantic,
            'count' => \count( $found_semantic ),
            'coverage' => \count( $semantic_keywords ) > 0 ? ( \count( $found_semantic ) / \count( $semantic_keywords ) ) * 100 : 0
        ];
    }
    
    /**
     * Find keyword variations in content
     *
     * @param string $content Content to search
     * @param string $keyword Base keyword
     * @return array Found variations
     */
    private function find_keyword_variations( $content, $keyword ) {
        $variations = [];
        
        // Generate possible variations
        $possible_variations = $this->generate_keyword_variations( $keyword );
        
        foreach ( $possible_variations as $variation ) {
            if ( $this->check_keyword_presence( $content, $variation ) ) {
                $variations[] = $variation;
            }
        }
        
        return $variations;
    }
    
    /**
     * Check for keyword stuffing patterns
     *
     * @param string $content Content to check
     * @param string $keyword Focus keyword
     * @return array Stuffing analysis
     */
    private function check_keyword_stuffing( $content, $keyword ) {
        $density = $this->calculate_keyword_density( $content, $keyword );
        $consecutive_count = $this->count_consecutive_keywords( $content, $keyword );
        
        $is_stuffed = false;
        $reasons = [];
        
        // Check density threshold
        if ( $density > 4.0 ) {
            $is_stuffed = true;
            $reasons[] = 'High keyword density';
        }
        
        // Check consecutive occurrences
        if ( $consecutive_count > 2 ) {
            $is_stuffed = true;
            $reasons[] = 'Consecutive keyword repetition';
        }
        
        // Check unnatural frequency in short segments
        $segments = $this->split_content_into_segments( $content, 100 );
        foreach ( $segments as $segment ) {
            $segment_density = $this->calculate_keyword_density( $segment, $keyword );
            if ( $segment_density > 8.0 ) {
                $is_stuffed = true;
                $reasons[] = 'High density in content segment';
                break;
            }
        }
        
        return [
            'is_stuffed' => $is_stuffed,
            'reasons' => $reasons,
            'density' => $density,
            'consecutive_count' => $consecutive_count
        ];
    }
    
    /**
     * Generate semantic keywords based on focus keyword
     *
     * @param string $keyword Base keyword
     * @return array Semantic keywords
     */
    private function generate_semantic_keywords( $keyword ) {
        // Basic semantic keyword generation
        // In a real implementation, this could connect to an API or use a more sophisticated algorithm
        $semantic_map = [
            'seo' => [ 'search engine optimization', 'organic traffic', 'search rankings', 'SERP', 'keywords' ],
            'wordpress' => [ 'CMS', 'website builder', 'blog platform', 'content management', 'plugins' ],
            'marketing' => [ 'digital marketing', 'online marketing', 'promotion', 'advertising', 'strategy' ],
            'content' => [ 'articles', 'blog posts', 'copywriting', 'writing', 'publishing' ]
        ];
        
        $keyword_lower = \strtolower( $keyword );
        $semantic_keywords = [];
        
        foreach ( $semantic_map as $base => $related ) {
            if ( \strpos( $keyword_lower, $base ) !== false ) {
                $semantic_keywords = \array_merge( $semantic_keywords, $related );
            }
        }
        
        // Generate variations of the original keyword
        $variations = $this->generate_keyword_variations( $keyword );
        $semantic_keywords = \array_merge( $semantic_keywords, $variations );
        
        return \array_unique( $semantic_keywords );
    }
    
    /**
     * Generate keyword variations
     *
     * @param string $keyword Base keyword
     * @return array Keyword variations
     */
    private function generate_keyword_variations( $keyword ) {
        $variations = [];
        
        // Add plural/singular forms
        if ( \substr( $keyword, -1 ) === 's' ) {
            $variations[] = \substr( $keyword, 0, -1 ); // Remove 's'
        } else {
            $variations[] = $keyword . 's'; // Add 's'
        }
        
        // Add -ing form
        if ( \substr( $keyword, -1 ) === 'e' ) {
            $variations[] = \substr( $keyword, 0, -1 ) . 'ing';
        } else {
            $variations[] = $keyword . 'ing';
        }
        
        // Add -ed form
        if ( \substr( $keyword, -1 ) === 'e' ) {
            $variations[] = \substr( $keyword, 0, -1 ) . 'ed';
        } else {
            $variations[] = $keyword . 'ed';
        }
        
        // Add synonyms for common words
        $synonym_map = [
            'good' => [ 'great', 'excellent', 'best', 'top' ],
            'fast' => [ 'quick', 'rapid', 'speedy' ],
            'easy' => [ 'simple', 'effortless' ],
            'cheap' => [ 'affordable', 'budget', 'inexpensive' ]
        ];
        
        foreach ( $synonym_map as $word => $synonyms ) {
            if ( \stripos( $keyword, $word ) !== false ) {
                foreach ( $synonyms as $synonym ) {
                    $variations[] = \str_ireplace( $word, $synonym, $keyword );
                }
            }
        }
        
        return \array_unique( \array_filter( $variations ) );
    }
    
    /**
     * Helper methods for content processing
     */
    
    private function clean_content( $content ) {
        // Remove HTML tags
        $content = \wp_strip_all_tags( $content );
        // Remove extra whitespace
        $content = \preg_replace( '/\s+/', ' ', $content );
        return \trim( $content );
    }
    
    private function count_words( $content ) {
        if ( empty( \trim( $content ) ) ) {
            return 0;
        }
        return \count( \preg_split( '/\s+/', \trim( $content ) ) );
    }
    
    private function extract_headings( $content ) {
        $headings = [ 'h1' => [], 'h2' => [], 'h3' => [] ];
        
        for ( $i = 1; $i <= 3; $i++ ) {
            $pattern = "/<h{$i}[^>]*>(.*?)<\/h{$i}>/i";
            if ( \preg_match_all( $pattern, $content, $matches ) ) {
                $headings["h{$i}"] = $matches[1];
            }
        }
        
        return $headings;
    }
    
    private function check_keyword_in_headings( $headings, $keyword ) {
        foreach ( $headings as $heading ) {
            if ( $this->check_keyword_presence( \wp_strip_all_tags( $heading ), $keyword ) ) {
                return true;
            }
        }
        return false;
    }
    
    private function extract_paragraphs( $content ) {
        // Simple paragraph extraction
        $paragraphs = \preg_split( '/<\/p>|<br\s*\/?>/', $content );
        $clean_paragraphs = [];
        
        foreach ( $paragraphs as $paragraph ) {
            $clean = \trim( \wp_strip_all_tags( $paragraph ) );
            if ( ! empty( $clean ) ) {
                $clean_paragraphs[] = $clean;
            }
        }
        
        return $clean_paragraphs;
    }
    
    private function check_keyword_in_alt_text( $content, $keyword ) {
        $pattern = '/<img[^>]+alt=["\']([^"\']*' . \preg_quote( $keyword, '/' ) . '[^"\']*)["\'][^>]*>/i';
        return \preg_match( $pattern, $content ) > 0;
    }
    
    private function count_consecutive_keywords( $content, $keyword ) {
        $words = \explode( ' ', \strtolower( $this->clean_content( $content ) ) );
        $keyword_lower = \strtolower( $keyword );
        $max_consecutive = 0;
        $current_consecutive = 0;
        
        foreach ( $words as $word ) {
            if ( \strpos( $word, $keyword_lower ) !== false ) {
                $current_consecutive++;
                $max_consecutive = \max( $max_consecutive, $current_consecutive );
            } else {
                $current_consecutive = 0;
            }
        }
        
        return $max_consecutive;
    }
    
    private function split_content_into_segments( $content, $word_count ) {
        $words = \explode( ' ', $this->clean_content( $content ) );
        $segments = [];
        
        for ( $i = 0; $i < \count( $words ); $i += $word_count ) {
            $segment = \array_slice( $words, $i, $word_count );
            $segments[] = \implode( ' ', $segment );
        }
        
        return $segments;
    }
}