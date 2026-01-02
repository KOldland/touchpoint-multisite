<?php
/**
 * Content Analyzer
 *
 * Analyzes page content to extract questions, answers, and citations
 * for AnswerCard auto-population from H2/H3 headings and surrounding content.
 *
 * @package KHM_SEO\GEO\Content
 * @since 2.1.0
 */

namespace KHM_SEO\GEO\Content;

use KHM_SEO\GEO\Entity\EntityManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Content Analyzer Class
 * Extracts Q/A pairs and citations from page content
 */
class ContentAnalyzer {

    /**
     * @var EntityManager Entity manager instance
     */
    private $entity_manager;

    /**
     * @var int Max content length to analyze (words)
     */
    private $max_content_length = 2000;

    /**
     * @var int Max answer length (words)
     */
    private $max_answer_length = 150;

    /**
     * @var array Question patterns to detect
     */
    private $question_patterns = array(
        '/^(what|how|why|when|where|who|which|can|does|do|is|are|will|should|could)/i',
        '/\?$/',
        '/^(guide|tutorial|tips?|advice|best|top|complete|ultimate)/i'
    );

    /**
     * Constructor
     */
    public function __construct( EntityManager $entity_manager ) {
        $this->entity_manager = $entity_manager;
    }

    /**
     * Analyze content around a heading to generate Q/A pair
     *
     * @param string $heading_text The heading text
     * @param string $content_after Content after the heading
     * @param int $post_id Post ID
     * @return array Q/A data with confidence score
     */
    public function analyze_heading_content( $heading_text, $content_after, $post_id = 0 ) {
        $result = array(
            'question' => '',
            'answer' => '',
            'bullets' => array(),
            'citations' => array(),
            'confidence' => 0,
            'sources' => array()
        );

        // Clean and prepare heading
        $heading_text = $this->clean_heading_text( $heading_text );

        // Generate question from heading
        $result['question'] = $this->generate_question_from_heading( $heading_text );

        // Extract answer from content
        $answer_data = $this->extract_answer_from_content( $content_after );
        $result['answer'] = $answer_data['text'];
        $result['bullets'] = $answer_data['bullets'];

        // Find citations and sources
        $result['citations'] = $this->extract_citations( $content_after );
        $result['sources'] = $this->identify_sources( $content_after, $post_id );

        // Calculate confidence score
        $result['confidence'] = $this->calculate_confidence_score( $result );

        return $result;
    }

    /**
     * Clean heading text for processing
     *
     * @param string $heading Raw heading text
     * @return string Cleaned heading
     */
    private function clean_heading_text( $heading ) {
        // Remove HTML tags
        $heading = wp_strip_all_tags( $heading );

        // Remove extra whitespace
        $heading = trim( preg_replace( '/\s+/', ' ', $heading ) );

        // Remove common heading prefixes
        $heading = preg_replace( '/^(what|how|why|when|where|who|which|can|does|do|is|are|will|should|could)\s+/i', '', $heading );

        return $heading;
    }

    /**
     * Generate question from heading text
     *
     * @param string $heading Clean heading text
     * @return string Question
     */
    private function generate_question_from_heading( $heading ) {
        // If it already looks like a question, return as-is
        if ( $this->is_question( $heading ) ) {
            return $heading;
        }

        // Add question prefix based on content type
        $question_starters = array(
            'What is' => array('guide', 'tutorial', 'overview', 'introduction'),
            'How to' => array('steps', 'process', 'method', 'way'),
            'Why' => array('important', 'matter', 'need', 'benefit'),
            'When to' => array('use', 'apply', 'implement'),
            'Where to' => array('find', 'get', 'locate')
        );

        $heading_lower = strtolower( $heading );

        foreach ( $question_starters as $starter => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( strpos( $heading_lower, $keyword ) !== false ) {
                    return $starter . ' ' . $heading;
                }
            }
        }

        // Default to "What is"
        return 'What is ' . $heading . '?';
    }

    /**
     * Check if text is already a question
     *
     * @param string $text Text to check
     * @return bool
     */
    private function is_question( $text ) {
        foreach ( $this->question_patterns as $pattern ) {
            if ( preg_match( $pattern, $text ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract answer from content after heading
     *
     * @param string $content Content after heading
     * @return array Answer data with text and bullets
     */
    private function extract_answer_from_content( $content ) {
        $result = array(
            'text' => '',
            'bullets' => array()
        );

        // Clean content
        $content = wp_strip_all_tags( $content );
        $content = preg_replace( '/\s+/', ' ', $content );
        $content = trim( $content );

        // Look for bullet points first
        $bullets = $this->extract_bullets( $content );
        if ( ! empty( $bullets ) ) {
            $result['bullets'] = array_slice( $bullets, 0, 5 ); // Limit to 5 bullets
            $result['text'] = implode( ' ', array_slice( $bullets, 0, 3 ) ); // Use first 3 for summary
        } else {
            // Extract paragraph-based answer
            $sentences = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
            $sentences = array_map( 'trim', $sentences );
            $sentences = array_filter( $sentences, function( $s ) {
                return strlen( $s ) > 10; // Filter out very short sentences
            });

            $answer_sentences = array_slice( $sentences, 0, 3 ); // Take first 3 sentences
            $result['text'] = implode( '. ', $answer_sentences ) . '.';
        }

        // Limit answer length
        if ( str_word_count( $result['text'] ) > $this->max_answer_length ) {
            $words = explode( ' ', $result['text'] );
            $result['text'] = implode( ' ', array_slice( $words, 0, $this->max_answer_length ) ) . '...';
        }

        return $result;
    }

    /**
     * Extract bullet points from content
     *
     * @param string $content Content to analyze
     * @return array Bullet points
     */
    private function extract_bullets( $content ) {
        $bullets = array();

        // Look for common bullet markers
        $patterns = array(
            '/(?:^|\n)[•\-\*]\s*([^\n]+)/',
            '/(?:^|\n)\d+\.\s*([^\n]+)/',
            '/(?:^|\n)[a-z]\)\s*([^\n]+)/'
        );

        foreach ( $patterns as $pattern ) {
            if ( preg_match_all( $pattern, $content, $matches ) ) {
                $bullets = array_merge( $bullets, $matches[1] );
            }
        }

        return array_map( 'trim', $bullets );
    }

    /**
     * Extract citations from content
     *
     * @param string $content Content to analyze
     * @return array Citations
     */
    private function extract_citations( $content ) {
        $citations = array();

        // Look for citation patterns
        $patterns = array(
            '/\[(\d+)\]/', // [1], [2], etc.
            '/\((\d{4})\)/', // (2023), (2024), etc.
            '/\((\w+),\s*(\d{4})\)/', // (Author, 2023)
            '/according to\s+([^\.,]+)/i', // "according to Source"
            '/source:\s*([^\n]+)/i' // "Source: ..."
        );

        foreach ( $patterns as $pattern ) {
            if ( preg_match_all( $pattern, $content, $matches ) ) {
                $citations = array_merge( $citations, $matches[1] );
            }
        }

        return array_unique( array_map( 'trim', $citations ) );
    }

    /**
     * Identify sources from content and post
     *
     * @param string $content Content to analyze
     * @param int $post_id Post ID
     * @return array Sources
     */
    private function identify_sources( $content, $post_id ) {
        $sources = array();

        // Add current post as source
        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $sources[] = array(
                    'type' => 'internal',
                    'title' => $post->post_title,
                    'url' => get_permalink( $post_id ),
                    'date' => $post->post_date
                );
            }
        }

        // Look for external links as sources
        if ( preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', $content, $matches ) ) {
            for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
                $url = $matches[1][$i];
                $text = $matches[2][$i];

                if ( strpos( $url, home_url() ) === false ) { // External link
                    $sources[] = array(
                        'type' => 'external',
                        'title' => $text,
                        'url' => $url,
                        'date' => null // Unknown date for external sources
                    );
                }
            }
        }

        return $sources;
    }

    /**
     * Calculate confidence score for Q/A pair
     *
     * @param array $data Q/A data
     * @return float Confidence score (0-1)
     */
    private function calculate_confidence_score( $data ) {
        $score = 0;

        // Question quality (0-0.3)
        if ( ! empty( $data['question'] ) && strlen( $data['question'] ) > 10 ) {
            $score += 0.3;
        }

        // Answer quality (0-0.3)
        if ( ! empty( $data['answer'] ) && str_word_count( $data['answer'] ) > 20 ) {
            $score += 0.3;
        }

        // Citations boost (0-0.2)
        if ( ! empty( $data['citations'] ) ) {
            $score += min( 0.2, count( $data['citations'] ) * 0.05 );
        }

        // Sources boost (0-0.2)
        if ( ! empty( $data['sources'] ) ) {
            $score += min( 0.2, count( $data['sources'] ) * 0.1 );
        }

        return min( 1.0, $score );
    }

    /**
     * Get headings from post content
     *
     * @param int $post_id Post ID
     * @return array Headings with context
     */
    public function get_post_headings( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array();
        }

        $content = $post->post_content;
        $headings = array();

        // Parse HTML to find headings
        $dom = new \DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( '<div>' . $content . '</div>', 'HTML-ENTITIES', 'UTF-8' ) );

        $xpath = new \DOMXPath( $dom );
        $heading_nodes = $xpath->query( '//h1 | //h2 | //h3 | //h4 | //h5 | //h6' );

        foreach ( $heading_nodes as $heading ) {
            if ( $heading instanceof \DOMElement ) {
                $heading_text = trim( $heading->textContent );

                // Get content after this heading
                $content_after = $this->get_content_after_heading( $heading, $dom );

                if ( ! empty( $heading_text ) && ! empty( $content_after ) ) {
                    $headings[] = array(
                        'level' => intval( $heading->tagName[1] ), // h1, h2, etc.
                        'text' => $heading_text,
                        'content_after' => $content_after,
                        'element' => $heading
                    );
                }
            }
        }

        return $headings;
    }

    /**
     * Get content after a heading element
     *
     * @param \DOMElement $heading Heading element
     * @param \DOMDocument $dom DOM document
     * @return string Content after heading
     */
    private function get_content_after_heading( $heading, $dom ) {
        $content = '';
        $current = $heading;

        // Get all siblings after this heading
        while ( $current = $current->nextSibling ) {
            if ( $current->nodeType === XML_ELEMENT_NODE ) {
                // Stop at next heading
                if ( preg_match( '/^h[1-6]$/i', $current->tagName ) ) {
                    break;
                }

                $content .= $dom->saveHTML( $current );
            } elseif ( $current->nodeType === XML_TEXT_NODE ) {
                $content .= $current->textContent;
            }

            // Limit content length
            if ( strlen( $content ) > 2000 ) {
                break;
            }
        }

        return $content;
    }
}
