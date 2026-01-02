<?php
/**
 * SEO Analysis Engine
 * 
 * Core analysis engine that provides comprehensive SEO scoring,
 * content analysis, and automated suggestions.
 *
 * @package KHM_SEO
 * @subpackage Analysis
 * @version 1.0.0
 */

namespace KHM_SEO\Analysis;

/**
 * Main SEO Analysis Engine Class
 * 
 * Handles all SEO analysis functions including content scoring,
 * keyword analysis, readability assessment, and suggestion generation.
 */
class AnalysisEngine {
    
    /**
     * Analysis configuration
     *
     * @var array
     */
    private $config;
    
    /**
     * Current analysis results
     *
     * @var array
     */
    private $results;
    
    /**
     * Keywords analyzer instance
     *
     * @var KeywordAnalyzer
     */
    private $keyword_analyzer;
    
    /**
     * Readability analyzer instance
     *
     * @var ReadabilityAnalyzer
     */
    private $readability_analyzer;
    
    /**
     * Content analyzer instance
     *
     * @var ContentAnalyzer
     */
    private $content_analyzer;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_config();
        $this->init_analyzers();
        $this->results = [];
    }
    
    /**
     * Initialize analysis configuration
     */
    private function init_config() {
        $this->config = [
            'scoring' => [
                'title_weight' => 20,
                'content_weight' => 25,
                'meta_description_weight' => 15,
                'keyword_weight' => 20,
                'readability_weight' => 10,
                'technical_weight' => 10
            ],
            'thresholds' => [
                'excellent' => 90,
                'good' => 75,
                'okay' => 60,
                'poor' => 40
            ],
            'content_analysis' => [
                'min_word_count' => 300,
                'optimal_word_count' => 1000,
                'max_word_count' => 3000,
                'min_title_length' => 30,
                'optimal_title_length' => 60,
                'max_title_length' => 70,
                'min_meta_description' => 120,
                'optimal_meta_description' => 160,
                'max_meta_description' => 170
            ],
            'keyword_analysis' => [
                'min_density' => 0.5,
                'optimal_density' => 1.5,
                'max_density' => 3.0,
                'title_keyword_bonus' => 15,
                'h1_keyword_bonus' => 10,
                'first_paragraph_bonus' => 8,
                'alt_text_bonus' => 5
            ],
            'readability' => [
                'max_sentence_length' => 20,
                'max_paragraph_length' => 150,
                'passive_voice_threshold' => 10,
                'transition_word_threshold' => 30
            ]
        ];
        
        // Allow configuration to be filtered
        $this->config = \apply_filters( 'khm_seo_analysis_config', $this->config );
    }
    
    /**
     * Initialize analyzer instances
     */
    private function init_analyzers() {
        $this->keyword_analyzer = new KeywordAnalyzer( $this->config );
        $this->readability_analyzer = new ReadabilityAnalyzer( $this->config );
        $this->content_analyzer = new ContentAnalyzer( $this->config );
    }
    
    /**
     * Perform comprehensive SEO analysis
     *
     * @param array $data Analysis data containing content, title, meta, etc.
     * @return array Complete analysis results
     */
    public function analyze( $data ) {
        // Reset results
        $this->results = [
            'overall_score' => 0,
            'individual_scores' => [],
            'suggestions' => [],
            'technical_issues' => [],
            'performance_metrics' => [],
            'timestamp' => \current_time( 'timestamp' )
        ];
        
        // Validate input data
        $data = $this->validate_analysis_data( $data );
        
        // Perform individual analyses
        $title_analysis = $this->analyze_title( $data );
        $content_analysis = $this->analyze_content( $data );
        $meta_analysis = $this->analyze_meta_description( $data );
        $keyword_analysis = $this->analyze_keywords( $data );
        $readability_analysis = $this->analyze_readability( $data );
        $technical_analysis = $this->analyze_technical_seo( $data );
        
        // Store individual scores
        $this->results['individual_scores'] = [
            'title' => $title_analysis,
            'content' => $content_analysis,
            'meta_description' => $meta_analysis,
            'keywords' => $keyword_analysis,
            'readability' => $readability_analysis,
            'technical' => $technical_analysis
        ];
        
        // Calculate overall score
        $this->results['overall_score'] = $this->calculate_overall_score();
        
        // Generate suggestions
        $this->results['suggestions'] = $this->generate_suggestions();
        
        // Detect technical issues
        $this->results['technical_issues'] = $this->detect_technical_issues( $data );
        
        // Calculate performance metrics
        $this->results['performance_metrics'] = $this->calculate_performance_metrics( $data );
        
        return $this->results;
    }
    
    /**
     * Analyze title SEO
     *
     * @param array $data Analysis data
     * @return array Title analysis results
     */
    private function analyze_title( $data ) {
        $title = $data['title'] ?? '';
        $focus_keyword = $data['focus_keyword'] ?? '';
        
        $analysis = [
            'score' => 0,
            'issues' => [],
            'improvements' => [],
            'metrics' => []
        ];
        
        $title_length = \mb_strlen( $title );
        $analysis['metrics']['length'] = $title_length;
        
        // Title length analysis
        if ( empty( $title ) ) {
            $analysis['issues'][] = [
                'type' => 'error',
                'message' => 'No title specified',
                'impact' => 'high',
                'suggestion' => 'Add a descriptive title to improve SEO'
            ];
        } elseif ( $title_length < $this->config['content_analysis']['min_title_length'] ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Title is too short',
                'impact' => 'medium',
                'suggestion' => 'Expand title to at least ' . $this->config['content_analysis']['min_title_length'] . ' characters'
            ];
            $analysis['score'] += 40;
        } elseif ( $title_length > $this->config['content_analysis']['max_title_length'] ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Title may be too long',
                'impact' => 'medium',
                'suggestion' => 'Consider shortening title to under ' . $this->config['content_analysis']['max_title_length'] . ' characters'
            ];
            $analysis['score'] += 70;
        } else {
            $analysis['improvements'][] = 'Title length is optimal';
            $analysis['score'] += 100;
        }
        
        // Focus keyword in title
        if ( ! empty( $focus_keyword ) ) {
            $keyword_in_title = $this->keyword_analyzer->check_keyword_presence( $title, $focus_keyword );
            $analysis['metrics']['keyword_present'] = $keyword_in_title;
            
            if ( $keyword_in_title ) {
                $analysis['improvements'][] = 'Focus keyword found in title';
                $analysis['score'] += 100;
                
                // Check keyword position
                $keyword_position = $this->keyword_analyzer->get_keyword_position( $title, $focus_keyword );
                if ( $keyword_position <= 5 ) {
                    $analysis['improvements'][] = 'Focus keyword appears early in title';
                    $analysis['score'] += 20;
                }
            } else {
                $analysis['issues'][] = [
                    'type' => 'error',
                    'message' => 'Focus keyword not found in title',
                    'impact' => 'high',
                    'suggestion' => 'Include your focus keyword in the title for better SEO'
                ];
            }
        }
        
        // Title uniqueness check (if we have other posts data)
        if ( isset( $data['check_uniqueness'] ) && $data['check_uniqueness'] ) {
            $is_unique = $this->check_title_uniqueness( $title, $data['post_id'] ?? 0 );
            $analysis['metrics']['is_unique'] = $is_unique;
            
            if ( ! $is_unique ) {
                $analysis['issues'][] = [
                    'type' => 'warning',
                    'message' => 'Title may not be unique',
                    'impact' => 'medium',
                    'suggestion' => 'Consider making your title more unique'
                ];
            } else {
                $analysis['improvements'][] = 'Title appears to be unique';
                $analysis['score'] += 10;
            }
        }
        
        // Power words analysis
        $power_words_count = $this->count_power_words_in_text( $title );
        $analysis['metrics']['power_words'] = $power_words_count;
        
        if ( $power_words_count > 0 ) {
            $analysis['improvements'][] = "Title contains {$power_words_count} power word(s)";
            $analysis['score'] += min( $power_words_count * 5, 15 );
        }
        
        // Sentiment analysis
        $sentiment = $this->analyze_text_sentiment( $title );
        $analysis['metrics']['sentiment'] = $sentiment;
        
        if ( $sentiment === 'positive' ) {
            $analysis['improvements'][] = 'Title has positive sentiment';
            $analysis['score'] += 5;
        }
        
        // Normalize score (0-100)
        $analysis['score'] = min( 100, $analysis['score'] / 2.5 );
        
        return $analysis;
    }
    
    /**
     * Analyze content SEO
     *
     * @param array $data Analysis data
     * @return array Content analysis results
     */
    private function analyze_content( $data ) {
        $content = $data['content'] ?? '';
        $focus_keyword = $data['focus_keyword'] ?? '';
        
        return $this->content_analyzer->analyze( $content, $focus_keyword );
    }
    
    /**
     * Analyze meta description
     *
     * @param array $data Analysis data
     * @return array Meta description analysis results
     */
    private function analyze_meta_description( $data ) {
        $meta_description = $data['meta_description'] ?? '';
        $focus_keyword = $data['focus_keyword'] ?? '';
        
        $analysis = [
            'score' => 0,
            'issues' => [],
            'improvements' => [],
            'metrics' => []
        ];
        
        $meta_length = \mb_strlen( $meta_description );
        $analysis['metrics']['length'] = $meta_length;
        
        // Meta description length analysis
        if ( empty( $meta_description ) ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'No meta description specified',
                'impact' => 'medium',
                'suggestion' => 'Add a compelling meta description to improve click-through rates'
            ];
        } elseif ( $meta_length < $this->config['content_analysis']['min_meta_description'] ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Meta description is too short',
                'impact' => 'low',
                'suggestion' => 'Expand meta description to at least ' . $this->config['content_analysis']['min_meta_description'] . ' characters'
            ];
            $analysis['score'] += 60;
        } elseif ( $meta_length > $this->config['content_analysis']['max_meta_description'] ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Meta description may be too long',
                'impact' => 'low',
                'suggestion' => 'Consider shortening meta description to under ' . $this->config['content_analysis']['max_meta_description'] . ' characters'
            ];
            $analysis['score'] += 80;
        } else {
            $analysis['improvements'][] = 'Meta description length is optimal';
            $analysis['score'] += 100;
        }
        
        // Focus keyword in meta description
        if ( ! empty( $focus_keyword ) && ! empty( $meta_description ) ) {
            $keyword_in_meta = $this->keyword_analyzer->check_keyword_presence( $meta_description, $focus_keyword );
            $analysis['metrics']['keyword_present'] = $keyword_in_meta;
            
            if ( $keyword_in_meta ) {
                $analysis['improvements'][] = 'Focus keyword found in meta description';
                $analysis['score'] += 50;
            } else {
                $analysis['issues'][] = [
                    'type' => 'warning',
                    'message' => 'Focus keyword not found in meta description',
                    'impact' => 'medium',
                    'suggestion' => 'Include your focus keyword in the meta description'
                ];
            }
        }
        
        // Call-to-action analysis
        if ( ! empty( $meta_description ) ) {
            $has_cta = $this->content_analyzer->has_call_to_action( $meta_description );
            $analysis['metrics']['has_cta'] = $has_cta;
            
            if ( $has_cta ) {
                $analysis['improvements'][] = 'Meta description includes call-to-action';
                $analysis['score'] += 15;
            }
        }
        
        // Uniqueness check
        if ( isset( $data['check_uniqueness'] ) && $data['check_uniqueness'] && ! empty( $meta_description ) ) {
            $is_unique = $this->check_meta_description_uniqueness( $meta_description, $data['post_id'] ?? 0 );
            $analysis['metrics']['is_unique'] = $is_unique;
            
            if ( ! $is_unique ) {
                $analysis['issues'][] = [
                    'type' => 'warning',
                    'message' => 'Meta description may not be unique',
                    'impact' => 'medium',
                    'suggestion' => 'Consider making your meta description more unique'
                ];
            } else {
                $analysis['improvements'][] = 'Meta description appears to be unique';
                $analysis['score'] += 10;
            }
        }
        
        // Normalize score
        $analysis['score'] = min( 100, $analysis['score'] / 1.75 );
        
        return $analysis;
    }
    
    /**
     * Analyze keywords
     *
     * @param array $data Analysis data
     * @return array Keyword analysis results
     */
    private function analyze_keywords( $data ) {
        $content = $data['content'] ?? '';
        $focus_keyword = $data['focus_keyword'] ?? '';
        $title = $data['title'] ?? '';
        
        return $this->keyword_analyzer->analyze( $content, $focus_keyword, $title );
    }
    
    /**
     * Analyze readability
     *
     * @param array $data Analysis data
     * @return array Readability analysis results
     */
    private function analyze_readability( $data ) {
        $content = $data['content'] ?? '';
        
        return $this->readability_analyzer->analyze( $content );
    }
    
    /**
     * Analyze technical SEO aspects
     *
     * @param array $data Analysis data
     * @return array Technical analysis results
     */
    private function analyze_technical_seo( $data ) {
        $analysis = [
            'score' => 0,
            'issues' => [],
            'improvements' => [],
            'metrics' => []
        ];
        
        $content = $data['content'] ?? '';
        
        // Image alt text analysis
        $images = $this->extract_images_from_content( $content );
        $images_without_alt = 0;
        $total_images = count( $images );
        
        foreach ( $images as $image ) {
            if ( empty( $image['alt'] ) ) {
                $images_without_alt++;
            }
        }
        
        $analysis['metrics']['total_images'] = $total_images;
        $analysis['metrics']['images_without_alt'] = $images_without_alt;
        
        if ( $total_images > 0 ) {
            if ( $images_without_alt === 0 ) {
                $analysis['improvements'][] = 'All images have alt text';
                $analysis['score'] += 50;
            } else {
                $analysis['issues'][] = [
                    'type' => 'warning',
                    'message' => "{$images_without_alt} image(s) missing alt text",
                    'impact' => 'medium',
                    'suggestion' => 'Add descriptive alt text to all images for better accessibility and SEO'
                ];
                $analysis['score'] += max( 0, 50 - ( $images_without_alt * 10 ) );
            }
        }
        
        // Heading structure analysis
        $headings = $this->extract_headings_from_content( $content );
        $analysis['metrics']['headings'] = $headings;
        
        if ( empty( $headings['h1'] ) && empty( $data['title'] ) ) {
            $analysis['issues'][] = [
                'type' => 'error',
                'message' => 'No H1 heading found',
                'impact' => 'high',
                'suggestion' => 'Add an H1 heading to establish content hierarchy'
            ];
        } elseif ( count( $headings['h1'] ) > 1 ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'Multiple H1 headings found',
                'impact' => 'medium',
                'suggestion' => 'Use only one H1 heading per page'
            ];
            $analysis['score'] += 20;
        } else {
            $analysis['improvements'][] = 'Good heading structure';
            $analysis['score'] += 30;
        }
        
        // Internal linking analysis
        $internal_links = $this->extract_internal_links_from_content( $content );
        $analysis['metrics']['internal_links'] = count( $internal_links );
        
        if ( count( $internal_links ) === 0 ) {
            $analysis['issues'][] = [
                'type' => 'warning',
                'message' => 'No internal links found',
                'impact' => 'low',
                'suggestion' => 'Add internal links to related content to improve site structure'
            ];
        } else {
            $analysis['improvements'][] = count( $internal_links ) . ' internal link(s) found';
            $analysis['score'] += min( count( $internal_links ) * 5, 20 );
        }
        
        // External linking analysis
        $external_links = $this->extract_external_links_from_content( $content );
        $analysis['metrics']['external_links'] = count( $external_links );
        
        if ( count( $external_links ) > 0 ) {
            $analysis['improvements'][] = count( $external_links ) . ' external link(s) found';
            $analysis['score'] += min( count( $external_links ) * 2, 10 );
        }
        
        return $analysis;
    }
    
    /**
     * Calculate overall SEO score
     *
     * @return int Overall score (0-100)
     */
    private function calculate_overall_score() {
        $scores = $this->results['individual_scores'];
        $config = $this->config['scoring'];
        
        $weighted_score = 0;
        $total_weight = 0;
        
        foreach ( $scores as $category => $analysis ) {
            $weight = $config[ $category . '_weight' ] ?? 0;
            if ( $weight > 0 && isset( $analysis['score'] ) ) {
                $weighted_score += $analysis['score'] * $weight;
                $total_weight += $weight;
            }
        }
        
        return $total_weight > 0 ? round( $weighted_score / $total_weight ) : 0;
    }
    
    /**
     * Generate improvement suggestions
     *
     * @return array Array of suggestions
     */
    private function generate_suggestions() {
        $suggestions = [];
        $overall_score = $this->results['overall_score'];
        
        // Priority suggestions based on overall score
        if ( $overall_score < 40 ) {
            $suggestions[] = [
                'priority' => 'high',
                'category' => 'general',
                'message' => 'Your content needs significant SEO improvements. Focus on optimizing title, content length, and keyword usage.',
                'action' => 'Review all SEO elements systematically'
            ];
        } elseif ( $overall_score < 75 ) {
            $suggestions[] = [
                'priority' => 'medium',
                'category' => 'general',
                'message' => 'Good foundation! Focus on fine-tuning your keyword usage and content structure.',
                'action' => 'Optimize weak areas identified in the analysis'
            ];
        } else {
            $suggestions[] = [
                'priority' => 'low',
                'category' => 'general',
                'message' => 'Excellent SEO! Consider minor optimizations for even better performance.',
                'action' => 'Maintain current quality and monitor performance'
            ];
        }
        
        // Category-specific suggestions
        foreach ( $this->results['individual_scores'] as $category => $analysis ) {
            if ( isset( $analysis['issues'] ) ) {
                foreach ( $analysis['issues'] as $issue ) {
                    $suggestions[] = [
                        'priority' => $this->map_impact_to_priority( $issue['impact'] ),
                        'category' => $category,
                        'message' => $issue['message'],
                        'action' => $issue['suggestion']
                    ];
                }
            }
        }
        
        // Sort by priority
        \usort( $suggestions, function( $a, $b ) {
            $priority_order = [ 'high' => 1, 'medium' => 2, 'low' => 3 ];
            return $priority_order[ $a['priority'] ] - $priority_order[ $b['priority'] ];
        });
        
        return $suggestions;
    }
    
    /**
     * Detect technical SEO issues
     *
     * @param array $data Analysis data
     * @return array Technical issues found
     */
    private function detect_technical_issues( $data ) {
        $issues = [];
        
        // Check for common technical issues
        $content = $data['content'] ?? '';
        
        // Broken link detection (basic)
        $links = $this->extract_all_links_from_content( $content );
        foreach ( $links as $link ) {
            if ( $this->is_potentially_broken_link( $link ) ) {
                $issues[] = [
                    'type' => 'broken_link',
                    'severity' => 'medium',
                    'message' => 'Potentially broken link detected',
                    'details' => [ 'url' => $link ]
                ];
            }
        }
        
        // Large image detection
        $images = $this->extract_images_from_content( $content );
        foreach ( $images as $image ) {
            if ( $this->is_large_image( $image ) ) {
                $issues[] = [
                    'type' => 'large_image',
                    'severity' => 'low',
                    'message' => 'Large image detected - may slow page load',
                    'details' => [ 'src' => $image['src'] ]
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Calculate performance metrics
     *
     * @param array $data Analysis data
     * @return array Performance metrics
     */
    private function calculate_performance_metrics( $data ) {
        $content = $data['content'] ?? '';
        $title = $data['title'] ?? '';
        $meta_description = $data['meta_description'] ?? '';
        
        return [
            'content_length' => \mb_strlen( \wp_strip_all_tags( $content ) ),
            'word_count' => $this->count_words_simple( $content ),
            'sentence_count' => $this->readability_analyzer->count_sentences( $content ),
            'paragraph_count' => $this->readability_analyzer->count_paragraphs( $content ),
            'heading_count' => count( $this->extract_headings_from_content( $content )['all'] ),
            'image_count' => count( $this->extract_images_from_content( $content ) ),
            'link_count' => count( $this->extract_all_links_from_content( $content ) ),
            'title_length' => \mb_strlen( $title ),
            'meta_description_length' => \mb_strlen( $meta_description ),
            'analysis_time' => \microtime( true ) - ( $this->results['timestamp'] ?? \microtime( true ) )
        ];
    }
    
    /**
     * Validate analysis data
     *
     * @param array $data Raw input data
     * @return array Validated and sanitized data
     */
    private function validate_analysis_data( $data ) {
        $validated = [];
        
        // Sanitize strings
        $string_fields = [ 'title', 'content', 'meta_description', 'focus_keyword' ];
        foreach ( $string_fields as $field ) {
            $validated[ $field ] = isset( $data[ $field ] ) ? \sanitize_text_field( $data[ $field ] ) : '';
        }
        
        // Handle content separately (may contain HTML)
        if ( isset( $data['content'] ) ) {
            $validated['content'] = \wp_kses_post( $data['content'] );
        }
        
        // Validate numeric fields
        $numeric_fields = [ 'post_id', 'author_id' ];
        foreach ( $numeric_fields as $field ) {
            $validated[ $field ] = isset( $data[ $field ] ) ? \absint( $data[ $field ] ) : 0;
        }
        
        // Boolean fields
        $boolean_fields = [ 'check_uniqueness', 'check_readability' ];
        foreach ( $boolean_fields as $field ) {
            $validated[ $field ] = ! empty( $data[ $field ] );
        }
        
        return $validated;
    }
    
    /**
     * Helper method to map impact level to priority
     */
    private function map_impact_to_priority( $impact ) {
        switch ( $impact ) {
            case 'high':
                return 'high';
            case 'medium':
                return 'medium';
            case 'low':
            default:
                return 'low';
        }
    }
    
    /**
     * Check if title is unique
     */
    private function check_title_uniqueness( $title, $post_id = 0 ) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND ID != %d AND post_status = 'publish'",
            $title,
            $post_id
        );
        
        $existing = $wpdb->get_var( $query );
        return empty( $existing );
    }
    
    /**
     * Check if meta description is unique
     */
    private function check_meta_description_uniqueness( $meta_description, $post_id = 0 ) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'khm_seo_meta_description' AND meta_value = %s AND post_id != %d",
            $meta_description,
            $post_id
        );
        
        $existing = $wpdb->get_var( $query );
        return empty( $existing );
    }
    
    /**
     * Extract images from content
     */
    private function extract_images_from_content( $content ) {
        $images = [];
        $pattern = '/<img[^>]+>/i';
        
        if ( \preg_match_all( $pattern, $content, $matches ) ) {
            foreach ( $matches[0] as $img_tag ) {
                $src = '';
                $alt = '';
                
                if ( \preg_match( '/src=["\']([^"\']+)["\']/', $img_tag, $src_match ) ) {
                    $src = $src_match[1];
                }
                
                if ( \preg_match( '/alt=["\']([^"\']+)["\']/', $img_tag, $alt_match ) ) {
                    $alt = $alt_match[1];
                }
                
                $images[] = [
                    'src' => $src,
                    'alt' => $alt,
                    'tag' => $img_tag
                ];
            }
        }
        
        return $images;
    }
    
    /**
     * Extract headings from content
     */
    private function extract_headings_from_content( $content ) {
        $headings = [
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'all' => []
        ];
        
        for ( $i = 1; $i <= 6; $i++ ) {
            $pattern = "/<h{$i}[^>]*>(.*?)<\/h{$i}>/i";
            if ( \preg_match_all( $pattern, $content, $matches ) ) {
                $headings["h{$i}"] = $matches[1];
                $headings['all'] = \array_merge( $headings['all'], $matches[1] );
            }
        }
        
        return $headings;
    }
    
    /**
     * Extract internal links from content
     */
    private function extract_internal_links_from_content( $content ) {
        $links = [];
        $site_url = \home_url();
        $pattern = '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i';
        
        if ( \preg_match_all( $pattern, $content, $matches ) ) {
            foreach ( $matches[1] as $href ) {
                if ( \strpos( $href, $site_url ) === 0 || \strpos( $href, '/' ) === 0 ) {
                    $links[] = $href;
                }
            }
        }
        
        return $links;
    }
    
    /**
     * Extract external links from content
     */
    private function extract_external_links_from_content( $content ) {
        $links = [];
        $site_url = \home_url();
        $pattern = '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i';
        
        if ( \preg_match_all( $pattern, $content, $matches ) ) {
            foreach ( $matches[1] as $href ) {
                if ( \strpos( $href, 'http' ) === 0 && \strpos( $href, $site_url ) === false ) {
                    $links[] = $href;
                }
            }
        }
        
        return $links;
    }
    
    /**
     * Extract all links from content
     */
    private function extract_all_links_from_content( $content ) {
        $links = [];
        $pattern = '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i';
        
        if ( \preg_match_all( $pattern, $content, $matches ) ) {
            $links = $matches[1];
        }
        
        return $links;
    }
    
    /**
     * Check if link is potentially broken
     */
    private function is_potentially_broken_link( $link ) {
        // Basic checks for obviously broken links
        $broken_patterns = [
            'localhost',
            '127.0.0.1',
            'example.com',
            'test.com',
            'placeholder'
        ];
        
        foreach ( $broken_patterns as $pattern ) {
            if ( \strpos( $link, $pattern ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if image is large
     */
    private function is_large_image( $image ) {
        // Check file extension for potential large formats
        $large_formats = [ '.bmp', '.tiff', '.raw' ];
        
        foreach ( $large_formats as $format ) {
            if ( \strpos( \strtolower( $image['src'] ), $format ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get analysis results
     *
     * @return array Current analysis results
     */
    public function get_results() {
        return $this->results;
    }
    
    /**
     * Get configuration
     *
     * @return array Current configuration
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Update configuration
     *
     * @param array $new_config New configuration values
     */
    public function update_config( $new_config ) {
        $this->config = \wp_parse_args( $new_config, $this->config );
        $this->init_analyzers(); // Reinitialize with new config
    }
    
    /**
     * Count power words in text
     *
     * @param string $text Text to analyze
     * @return int Power word count
     */
    private function count_power_words_in_text( $text ) {
        $power_words = [
            'amazing', 'incredible', 'outstanding', 'fantastic', 'exceptional',
            'proven', 'guaranteed', 'exclusive', 'ultimate', 'secret',
            'instantly', 'immediately', 'breakthrough', 'revolutionary',
            'powerful', 'effective', 'essential', 'crucial', 'vital'
        ];
        
        $text_lower = \strtolower( $text );
        $count = 0;
        
        foreach ( $power_words as $word ) {
            if ( \strpos( $text_lower, $word ) !== false ) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Analyze text sentiment (simplified)
     *
     * @param string $text Text to analyze
     * @return string Sentiment (positive, neutral, negative)
     */
    private function analyze_text_sentiment( $text ) {
        $positive_words = ['amazing', 'excellent', 'great', 'wonderful', 'fantastic'];
        $negative_words = ['bad', 'poor', 'terrible', 'awful', 'horrible'];
        
        $text_lower = \strtolower( $text );
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ( $positive_words as $word ) {
            if ( \strpos( $text_lower, $word ) !== false ) {
                $positive_count++;
            }
        }
        
        foreach ( $negative_words as $word ) {
            if ( \strpos( $text_lower, $word ) !== false ) {
                $negative_count++;
            }
        }
        
        if ( $positive_count > $negative_count ) {
            return 'positive';
        } elseif ( $negative_count > $positive_count ) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }
    
    /**
     * Simple word count function
     *
     * @param string $content Content to count
     * @return int Word count
     */
    private function count_words_simple( $content ) {
        $clean_content = \wp_strip_all_tags( $content );
        $clean_content = \preg_replace( '/\s+/', ' ', $clean_content );
        $clean_content = \trim( $clean_content );
        
        if ( empty( $clean_content ) ) {
            return 0;
        }
        
        return \count( \preg_split( '/\s+/', $clean_content ) );
    }
}

// Backwards compatibility for previous namespace spelling.
if ( ! class_exists( 'KHM_SEO\\Analysis\\AnalysisEngine' ) ) {
    class_alias( __NAMESPACE__ . '\\AnalysisEngine', 'KHM_SEO\\Analysis\\AnalysisEngine' );
}
