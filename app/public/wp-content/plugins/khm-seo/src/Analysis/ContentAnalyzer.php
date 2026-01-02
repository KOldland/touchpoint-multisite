<?php
/**
 * Content Analyzer
 * 
 * Analyzes content quality, structure, and engagement factors
 * including power words, sentiment, and call-to-action detection.
 *
 * @package KHM_SEO
 * @subpackage Analysis
 * @version 1.0.0
 */

namespace KHM_SEO\Analysis;

/**
 * Content Quality Analysis Class
 * 
 * Provides comprehensive content analysis including:
 * - Power word detection
 * - Sentiment analysis
 * - Call-to-action identification
 * - Content structure evaluation
 * - Engagement factor assessment
 */
class ContentAnalyzer {
    
    /**
     * Analysis configuration
     *
     * @var array
     */
    private $config;
    
    /**
     * Power words for engagement
     *
     * @var array
     */
    private $power_words;
    
    /**
     * Call-to-action phrases
     *
     * @var array
     */
    private $cta_phrases;
    
    /**
     * Positive sentiment words
     *
     * @var array
     */
    private $positive_words;
    
    /**
     * Negative sentiment words
     *
     * @var array
     */
    private $negative_words;
    
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
        $this->power_words = [
            'amazing', 'incredible', 'outstanding', 'fantastic', 'exceptional',
            'proven', 'guaranteed', 'exclusive', 'ultimate', 'secret',
            'instantly', 'immediately', 'breakthrough', 'revolutionary',
            'powerful', 'effective', 'essential', 'crucial', 'vital',
            'unleash', 'transform', 'discover', 'reveal', 'unlock',
            'master', 'expert', 'professional', 'advanced', 'premium',
            'free', 'bonus', 'limited', 'special', 'unique',
            'complete', 'comprehensive', 'definitive', 'ultimate',
            'successful', 'profitable', 'valuable', 'important',
            'critical', 'significant', 'remarkable', 'extraordinary'
        ];
        
        $this->cta_phrases = [
            'click here', 'learn more', 'read more', 'find out',
            'discover', 'get started', 'sign up', 'register',
            'download', 'subscribe', 'join', 'contact us',
            'call now', 'buy now', 'order now', 'shop now',
            'try now', 'start today', 'get your', 'claim your',
            'book now', 'schedule', 'request', 'apply',
            'explore', 'view all', 'see more', 'browse',
            'follow us', 'share', 'like', 'comment'
        ];
        
        $this->positive_words = [
            'good', 'great', 'excellent', 'wonderful', 'fantastic',
            'amazing', 'outstanding', 'superb', 'brilliant', 'perfect',
            'beautiful', 'awesome', 'incredible', 'marvelous',
            'spectacular', 'magnificent', 'exceptional', 'remarkable',
            'impressive', 'extraordinary', 'delightful', 'pleasant',
            'enjoyable', 'satisfying', 'successful', 'beneficial',
            'valuable', 'useful', 'helpful', 'effective', 'efficient',
            'reliable', 'trustworthy', 'professional', 'quality'
        ];
        
        $this->negative_words = [
            'bad', 'poor', 'terrible', 'awful', 'horrible',
            'disappointing', 'unsatisfactory', 'inadequate',
            'inferior', 'defective', 'faulty', 'broken',
            'useless', 'worthless', 'ineffective', 'unreliable',
            'problematic', 'difficult', 'challenging', 'complex',
            'confusing', 'frustrating', 'annoying', 'boring',
            'slow', 'expensive', 'costly', 'overpriced',
            'limited', 'restricted', 'insufficient', 'lacking'
        ];
    }
    
    /**
     * Perform comprehensive content analysis
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
                'suggestion' => 'Add content to perform quality analysis'
            ];
            return $analysis;
        }
        
        $clean_content = $this->clean_content( $content );
        $word_count = $this->count_words( $clean_content );
        
        // Content length analysis
        $this->analyze_content_length( $analysis, $word_count );
        
        // Power word analysis
        $this->analyze_power_words( $analysis, $clean_content );
        
        // Call-to-action analysis
        $this->analyze_cta( $analysis, $content );
        
        // Sentiment analysis
        $this->analyze_sentiment( $analysis, $clean_content );
        
        // List and bullet point analysis
        $this->analyze_lists( $analysis, $content );
        
        // Image and media analysis
        $this->analyze_media( $analysis, $content );
        
        // Link analysis
        $this->analyze_links( $analysis, $content );
        
        // Content freshness indicators
        $this->analyze_freshness( $analysis, $clean_content );
        
        // Normalize overall score
        $analysis['score'] = \min( 100, \max( 0, $analysis['score'] ) );
        
        return $analysis;
    }
    
    /**
     * Analyze content length
     *
     * @param array &$analysis Analysis array to update
     * @param int $word_count Word count
     */
    private function analyze_content_length( &$analysis, $word_count ) {
        $analysis['metrics']['word_count'] = $word_count;
        
        $min_length = $this->config['content']['min_word_count'] ?? 300;
        $optimal_length = $this->config['content']['optimal_word_count'] ?? 1000;
        
        if ( $word_count < $min_length ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => "Content is too short ({$word_count} words)",
                'impact' => 'high',
                'suggestion' => "Consider expanding content to at least {$min_length} words"
            ];
            $analysis['score'] += \max( 10, ( $word_count / $min_length ) * 50 );
        } elseif ( $word_count < $optimal_length ) {
            $analysis['improvements'][] = "Good content length ({$word_count} words)";
            $analysis['score'] += 70 + ( ( $word_count - $min_length ) / ( $optimal_length - $min_length ) ) * 20;
        } else {
            $analysis['improvements'][] = "Excellent content length ({$word_count} words)";
            $analysis['score'] += 90;
        }
    }
    
    /**
     * Analyze power word usage
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Clean content
     */
    private function analyze_power_words( &$analysis, $content ) {
        $content_lower = \strtolower( $content );
        $power_word_count = 0;
        $found_power_words = [];
        
        foreach ( $this->power_words as $power_word ) {
            if ( \strpos( $content_lower, $power_word ) !== false ) {
                $power_word_count++;
                $found_power_words[] = $power_word;
            }
        }
        
        $word_count = $this->count_words( $content );
        $power_word_density = $word_count > 0 ? ( $power_word_count / $word_count ) * 100 : 0;
        
        $analysis['metrics']['power_words'] = [
            'count' => $power_word_count,
            'density' => \round( $power_word_density, 2 ),
            'words' => $found_power_words
        ];
        
        $target_density = $this->config['content']['power_word_density'] ?? 1.0;
        
        if ( $power_word_density >= $target_density ) {
            $analysis['improvements'][] = 'Good use of power words for engagement';
            $analysis['score'] += 20;
        } elseif ( $power_word_density >= $target_density * 0.5 ) {
            $analysis['improvements'][] = 'Decent use of power words';
            $analysis['score'] += 15;
        } else {
            $analysis['issues'][] = [
                'type' => 'suggestion',
                'message' => 'Limited use of power words',
                'impact' => 'low',
                'suggestion' => 'Add more engaging power words to improve content appeal'
            ];
            $analysis['score'] += 10;
        }
    }
    
    /**
     * Analyze call-to-action presence
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Full content with HTML
     */
    private function analyze_cta( &$analysis, $content ) {
        $content_lower = \strtolower( $content );
        $cta_count = 0;
        $found_ctas = [];
        
        foreach ( $this->cta_phrases as $cta ) {
            if ( \strpos( $content_lower, $cta ) !== false ) {
                $cta_count++;
                $found_ctas[] = $cta;
            }
        }
        
        // Check for buttons
        $button_count = \substr_count( $content_lower, '<button' ) + \substr_count( $content_lower, 'class="button"' );
        
        $total_ctas = $cta_count + $button_count;
        
        $analysis['metrics']['cta'] = [
            'phrase_count' => $cta_count,
            'button_count' => $button_count,
            'total' => $total_ctas,
            'phrases' => $found_ctas
        ];
        
        $min_ctas = $this->config['content']['min_cta_count'] ?? 1;
        
        if ( $total_ctas >= $min_ctas ) {
            $analysis['improvements'][] = 'Good call-to-action presence';
            $analysis['score'] += 15;
        } else {
            $analysis['issues'][] = [
                'type' => 'suggestion',
                'message' => 'No clear call-to-action found',
                'impact' => 'medium',
                'suggestion' => 'Add clear calls-to-action to guide user behavior'
            ];
            $analysis['score'] += 5;
        }
    }
    
    /**
     * Analyze sentiment of content
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Clean content
     */
    private function analyze_sentiment( &$analysis, $content ) {
        $content_lower = \strtolower( $content );
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ( $this->positive_words as $word ) {
            if ( \strpos( $content_lower, $word ) !== false ) {
                $positive_count++;
            }
        }
        
        foreach ( $this->negative_words as $word ) {
            if ( \strpos( $content_lower, $word ) !== false ) {
                $negative_count++;
            }
        }
        
        $total_sentiment_words = $positive_count + $negative_count;
        $sentiment_ratio = $total_sentiment_words > 0 ? $positive_count / $total_sentiment_words : 0.5;
        
        $analysis['metrics']['sentiment'] = [
            'positive_count' => $positive_count,
            'negative_count' => $negative_count,
            'ratio' => \round( $sentiment_ratio, 2 ),
            'tone' => $this->get_sentiment_label( $sentiment_ratio )
        ];
        
        if ( $sentiment_ratio >= 0.7 ) {
            $analysis['improvements'][] = 'Positive and engaging tone';
            $analysis['score'] += 15;
        } elseif ( $sentiment_ratio >= 0.5 ) {
            $analysis['improvements'][] = 'Balanced tone';
            $analysis['score'] += 12;
        } elseif ( $sentiment_ratio >= 0.3 ) {
            $analysis['issues'][] = [
                'type' => 'suggestion',
                'message' => 'Tone could be more positive',
                'impact' => 'low',
                'suggestion' => 'Consider using more positive language'
            ];
            $analysis['score'] += 8;
        } else {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Tone appears negative',
                'impact' => 'medium',
                'suggestion' => 'Rewrite content with more positive language'
            ];
            $analysis['score'] += 5;
        }
    }
    
    /**
     * Analyze list and bullet point usage
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Full content with HTML
     */
    private function analyze_lists( &$analysis, $content ) {
        $ul_count = \substr_count( $content, '<ul>' );
        $ol_count = \substr_count( $content, '<ol>' );
        $li_count = \substr_count( $content, '<li>' );
        
        $total_lists = $ul_count + $ol_count;
        
        $analysis['metrics']['lists'] = [
            'unordered' => $ul_count,
            'ordered' => $ol_count,
            'total_lists' => $total_lists,
            'list_items' => $li_count
        ];
        
        $word_count = $this->count_words( $this->clean_content( $content ) );
        $recommended_lists = \floor( $word_count / 500 ); // One list per 500 words
        
        if ( $total_lists >= $recommended_lists && $total_lists > 0 ) {
            $analysis['improvements'][] = 'Good use of lists for scannable content';
            $analysis['score'] += 10;
        } elseif ( $total_lists > 0 ) {
            $analysis['improvements'][] = 'Some use of lists';
            $analysis['score'] += 7;
        } else {
            $analysis['issues'][] = [
                'type' => 'suggestion',
                'message' => 'No lists found',
                'impact' => 'low',
                'suggestion' => 'Add bullet points or numbered lists to improve scannability'
            ];
            $analysis['score'] += 3;
        }
    }
    
    /**
     * Analyze media usage (images, videos)
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Full content with HTML
     */
    private function analyze_media( &$analysis, $content ) {
        $img_count = \substr_count( $content, '<img' );
        $video_count = \substr_count( $content, '<video' ) + \substr_count( $content, 'youtube.com' ) + \substr_count( $content, 'vimeo.com' );
        
        // Check for alt attributes on images
        $alt_count = \substr_count( $content, 'alt=' );
        $missing_alt = \max( 0, $img_count - $alt_count );
        
        $analysis['metrics']['media'] = [
            'images' => $img_count,
            'videos' => $video_count,
            'missing_alt' => $missing_alt,
            'alt_coverage' => $img_count > 0 ? \round( ( $alt_count / $img_count ) * 100, 1 ) : 100
        ];
        
        $word_count = $this->count_words( $this->clean_content( $content ) );
        $recommended_images = \max( 1, \floor( $word_count / 300 ) ); // One image per 300 words
        
        if ( $img_count >= $recommended_images ) {
            $analysis['improvements'][] = 'Good use of images to break up text';
            $analysis['score'] += 10;
        } elseif ( $img_count > 0 ) {
            $analysis['improvements'][] = 'Some visual content present';
            $analysis['score'] += 7;
        } else {
            $analysis['issues'][] = [
                'type' => 'suggestion',
                'message' => 'No images found',
                'impact' => 'medium',
                'suggestion' => 'Add relevant images to make content more engaging'
            ];
            $analysis['score'] += 3;
        }
        
        if ( $missing_alt > 0 ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => "{$missing_alt} images missing alt text",
                'impact' => 'medium',
                'suggestion' => 'Add descriptive alt text to all images for accessibility and SEO'
            ];
        } elseif ( $img_count > 0 ) {
            $analysis['improvements'][] = 'All images have alt text';
            $analysis['score'] += 5;
        }
    }
    
    /**
     * Analyze link usage
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Full content with HTML
     */
    private function analyze_links( &$analysis, $content ) {
        $internal_links = \preg_match_all( '/<a[^>]+href=["\'](?!http)[^"\']*["\'][^>]*>/i', $content );
        $external_links = \preg_match_all( '/<a[^>]+href=["\']https?:\/\/[^"\']*["\'][^>]*>/i', $content );
        
        $total_links = $internal_links + $external_links;
        
        $analysis['metrics']['links'] = [
            'internal' => $internal_links,
            'external' => $external_links,
            'total' => $total_links
        ];
        
        $word_count = $this->count_words( $this->clean_content( $content ) );
        $link_density = $word_count > 0 ? ( $total_links / $word_count ) * 100 : 0;
        
        $analysis['metrics']['link_density'] = \round( $link_density, 2 );
        
        if ( $internal_links > 0 ) {
            $analysis['improvements'][] = 'Good internal linking structure';
            $analysis['score'] += 8;
        } else {
            $analysis['issues'][] = [
                'type' => 'suggestion',
                'message' => 'No internal links found',
                'impact' => 'low',
                'suggestion' => 'Add internal links to related content'
            ];
            $analysis['score'] += 3;
        }
        
        if ( $external_links > 0 && $external_links <= 5 ) {
            $analysis['improvements'][] = 'Appropriate external link usage';
            $analysis['score'] += 5;
        } elseif ( $external_links > 5 ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'High number of external links',
                'impact' => 'low',
                'suggestion' => 'Consider reducing external links to keep users on your site'
            ];
            $analysis['score'] += 3;
        }
    }
    
    /**
     * Analyze content freshness indicators
     *
     * @param array &$analysis Analysis array to update
     * @param string $content Clean content
     */
    private function analyze_freshness( &$analysis, $content ) {
        $current_year = \date( 'Y' );
        $last_year = $current_year - 1;
        
        $has_current_year = \strpos( $content, (string) $current_year ) !== false;
        $has_last_year = \strpos( $content, (string) $last_year ) !== false;
        
        // Check for dated phrases
        $outdated_phrases = [
            'last year', 'this year', 'recently', 'new', 'latest',
            'current', 'today', 'now', 'modern', 'updated'
        ];
        
        $freshness_indicators = 0;
        foreach ( $outdated_phrases as $phrase ) {
            if ( \stripos( $content, $phrase ) !== false ) {
                $freshness_indicators++;
            }
        }
        
        $analysis['metrics']['freshness'] = [
            'current_year_mentioned' => $has_current_year,
            'freshness_indicators' => $freshness_indicators,
            'appears_current' => $has_current_year || $freshness_indicators >= 3
        ];
        
        if ( $has_current_year && $freshness_indicators >= 3 ) {
            $analysis['improvements'][] = 'Content appears current and fresh';
            $analysis['score'] += 10;
        } elseif ( $has_current_year || $freshness_indicators >= 2 ) {
            $analysis['improvements'][] = 'Content has some freshness indicators';
            $analysis['score'] += 7;
        } else {
            $analysis['issues'][] = [
                'type' => 'suggestion',
                'message' => 'Content may appear outdated',
                'impact' => 'low',
                'suggestion' => 'Add current dates or update language to appear more current'
            ];
            $analysis['score'] += 3;
        }
    }
    
    /**
     * Get sentiment label from ratio
     *
     * @param float $ratio Sentiment ratio
     * @return string Sentiment label
     */
    private function get_sentiment_label( $ratio ) {
        if ( $ratio >= 0.8 ) {
            return 'Very Positive';
        } elseif ( $ratio >= 0.6 ) {
            return 'Positive';
        } elseif ( $ratio >= 0.4 ) {
            return 'Neutral';
        } elseif ( $ratio >= 0.2 ) {
            return 'Negative';
        } else {
            return 'Very Negative';
        }
    }
    
    /**
     * Count words in text
     *
     * @param string $text Text to count
     * @return int Word count
     */
    private function count_words( $text ) {
        if ( empty( \trim( $text ) ) ) {
            return 0;
        }
        return \count( \preg_split( '/\s+/', \trim( $text ) ) );
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