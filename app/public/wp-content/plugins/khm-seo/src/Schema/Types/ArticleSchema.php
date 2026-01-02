<?php
/**
 * Article Schema Type - Phase 3.2
 * 
 * Comprehensive article schema implementation for blog posts and articles.
 * Supports Article, BlogPosting, and NewsArticle schema types with rich metadata.
 * 
 * Features:
 * - Automatic content type detection
 * - Author and publisher information
 * - Featured image optimization
 * - Reading time calculation
 * - Content analysis integration
 * - Social sharing optimization
 * 
 * @package KHM_SEO\Schema\Types
 * @since 3.0.0
 * @version 3.0.0
 */

namespace KHM_SEO\Schema\Types;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Article Schema Class
 * Generates structured data for articles and blog posts
 */
class ArticleSchema {
    
    /**
     * @var array Schema configuration
     */
    private $config;
    
    /**
     * Constructor
     * 
     * @param array $config Schema configuration
     */
    public function __construct( $config = array() ) {
        $this->config = wp_parse_args( $config, array(
            'default_article_type' => 'Article',
            'enable_reading_time' => true,
            'enable_word_count' => true,
            'enable_author_schema' => true,
            'organization_name' => \get_bloginfo( 'name' ),
            'organization_logo' => '',
        ) );
    }
    
    /**
     * Generate article schema
     * 
     * @param WP_Post|null $post Post object (uses global $post if null)
     * @return array Article schema data
     */
    public function generate( $post = null ) {
        if ( ! $post ) {
            global $post;
        }
        
        if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
            return array();
        }
        
        // Determine article type based on post format and content
        $article_type = $this->determine_article_type( $post );
        
        // Base article schema
        $schema = array(
            '@type' => $article_type,
            '@id' => \get_permalink( $post ) . '#article',
            'headline' => \get_the_title( $post ),
            'description' => $this->get_article_description( $post ),
            'datePublished' => \get_the_date( 'c', $post ),
            'dateModified' => \get_the_modified_date( 'c', $post ),
            'author' => $this->generate_author_schema( $post ),
            'publisher' => $this->generate_publisher_schema(),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => \get_permalink( $post ),
            ),
            'url' => \get_permalink( $post ),
        );
        
        // Add featured image
        $image_schema = $this->generate_image_schema( $post );
        if ( ! empty( $image_schema ) ) {
            $schema['image'] = $image_schema;
        }
        
        // Add content-specific properties
        $this->add_content_properties( $schema, $post );
        
        // Add category/section information
        $this->add_category_information( $schema, $post );
        
        // Add reading time and word count
        if ( $this->config['enable_reading_time'] || $this->config['enable_word_count'] ) {
            $this->add_content_metrics( $schema, $post );
        }
        
        // Add article body (for better content understanding)
        $this->add_article_body( $schema, $post );
        
        // Filter schema before returning
        return apply_filters( 'khm_seo_article_schema', $schema, $post );
    }
    
    /**
     * Determine appropriate article type
     * 
     * @param WP_Post $post Post object
     * @return string Article type
     */
    private function determine_article_type( $post ) {
        // Check post format
        $post_format = \get_post_format( $post );
        
        // Check categories for news content
        $categories = \get_the_category( $post->ID );
        $category_names = array_map( function( $cat ) {
            return strtolower( $cat->name );
        }, $categories );
        
        $news_indicators = array( 'news', 'breaking', 'current events', 'press release' );
        $is_news = ! empty( array_intersect( $category_names, $news_indicators ) );
        
        // Determine schema type
        if ( $is_news ) {
            return 'NewsArticle';
        }
        
        // Check if it's a review
        if ( $post_format === 'aside' || strpos( strtolower( $post->post_title ), 'review' ) !== false ) {
            return 'Review';
        }
        
        // Check for tutorial/how-to content
        $how_to_indicators = array( 'how to', 'tutorial', 'guide', 'step by step' );
        $title_lower = strtolower( $post->post_title );
        foreach ( $how_to_indicators as $indicator ) {
            if ( strpos( $title_lower, $indicator ) !== false ) {
                return 'HowTo';
            }
        }
        
        // Default to Article or BlogPosting
        return $post->post_type === 'post' ? 'BlogPosting' : 'Article';
    }
    
    /**
     * Get article description
     * 
     * @param WP_Post $post Post object
     * @return string Article description
     */
    private function get_article_description( $post ) {
        // Try custom SEO description first
        $seo_description = \get_post_meta( $post->ID, '_khm_seo_description', true );
        if ( ! empty( $seo_description ) ) {
            return $seo_description;
        }
        
        // Try excerpt
        if ( ! empty( $post->post_excerpt ) ) {
            return wp_strip_all_tags( $post->post_excerpt );
        }
        
        // Generate from content
        $content = wp_strip_all_tags( $post->post_content );
        $content = preg_replace( '/\s+/', ' ', $content );
        
        if ( strlen( $content ) > 160 ) {
            $content = substr( $content, 0, 157 ) . '...';
        }
        
        return $content;
    }
    
    /**
     * Generate author schema
     * 
     * @param WP_Post $post Post object
     * @return array Author schema
     */
    private function generate_author_schema( $post ) {
        $author_id = $post->post_author;
        $author = \get_userdata( $author_id );
        
        if ( ! $author ) {
            return array();
        }
        
        $author_schema = array(
            '@type' => 'Person',
            '@id' => \get_author_posts_url( $author_id ) . '#person',
            'name' => $author->display_name,
            'url' => \get_author_posts_url( $author_id ),
        );
        
        // Add author description/bio
        if ( ! empty( $author->description ) ) {
            $author_schema['description'] = $author->description;
        }
        
        // Add author image
        $avatar_url = \get_avatar_url( $author_id, array( 'size' => 512 ) );
        if ( $avatar_url ) {
            $author_schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $avatar_url,
                'width' => 512,
                'height' => 512,
            );
        }
        
        // Add social profiles if available
        $social_profiles = array();
        $social_fields = array( 'twitter', 'facebook', 'linkedin', 'instagram' );
        
        foreach ( $social_fields as $field ) {
            $profile_url = \get_user_meta( $author_id, $field, true );
            if ( ! empty( $profile_url ) ) {
                $social_profiles[] = esc_url( $profile_url );
            }
        }
        
        if ( ! empty( $social_profiles ) ) {
            $author_schema['sameAs'] = $social_profiles;
        }
        
        return $author_schema;
    }
    
    /**
     * Generate publisher schema
     * 
     * @return array Publisher schema
     */
    private function generate_publisher_schema() {
        $publisher_schema = array(
            '@type' => 'Organization',
            '@id' => \home_url( '/' ) . '#organization',
            'name' => $this->config['organization_name'],
            'url' => \home_url( '/' ),
        );
        
        // Add organization logo
        if ( ! empty( $this->config['organization_logo'] ) ) {
            $publisher_schema['logo'] = array(
                '@type' => 'ImageObject',
                'url' => $this->config['organization_logo'],
            );
        }
        
        return $publisher_schema;
    }
    
    /**
     * Generate image schema for featured image
     * 
     * @param WP_Post $post Post object
     * @return array|null Image schema or null if no image
     */
    private function generate_image_schema( $post ) {
        if ( ! \has_post_thumbnail( $post ) ) {
            return null;
        }
        
        $image_id = \get_post_thumbnail_id( $post );
        $image_data = \wp_get_attachment_image_src( $image_id, 'full' );
        
        if ( ! $image_data ) {
            return null;
        }
        
        $image_schema = array(
            '@type' => 'ImageObject',
            'url' => $image_data[0],
            'width' => $image_data[1],
            'height' => $image_data[2],
        );
        
        // Add image alt text and caption
        $image_alt = \get_post_meta( $image_id, '_wp_attachment_image_alt', true );
        if ( $image_alt ) {
            $image_schema['alternateName'] = $image_alt;
        }
        
        $image_caption = \wp_get_attachment_caption( $image_id );
        if ( $image_caption ) {
            $image_schema['caption'] = $image_caption;
        }
        
        return $image_schema;
    }
    
    /**
     * Add content-specific properties to schema
     * 
     * @param array   $schema Schema array (by reference)
     * @param WP_Post $post   Post object
     */
    private function add_content_properties( &$schema, $post ) {
        // Add article section if it's a news article
        if ( $schema['@type'] === 'NewsArticle' ) {
            $categories = \get_the_category( $post->ID );
            if ( ! empty( $categories ) ) {
                $schema['articleSection'] = $categories[0]->name;
            }
        }
        
        // Add language
        $language = \get_locale();
        if ( $language ) {
            $schema['inLanguage'] = str_replace( '_', '-', $language );
        }
        
        // Add keywords from tags
        $tags = \get_the_tags( $post->ID );
        if ( $tags && ! \is_wp_error( $tags ) ) {
            $keywords = array_map( function( $tag ) {
                return $tag->name;
            }, $tags );
            
            if ( ! empty( $keywords ) ) {
                $schema['keywords'] = $keywords;
            }
        }
        
        // Add about property for better content understanding
        $focus_keyword = \get_post_meta( $post->ID, '_khm_seo_focus_keyword', true );
        if ( ! empty( $focus_keyword ) ) {
            $schema['about'] = array(
                '@type' => 'Thing',
                'name' => $focus_keyword,
            );
        }
    }
    
    /**
     * Add category information to schema
     * 
     * @param array   $schema Schema array (by reference)
     * @param WP_Post $post   Post object
     */
    private function add_category_information( &$schema, $post ) {
        $categories = \get_the_category( $post->ID );
        if ( empty( $categories ) ) {
            return;
        }
        
        // Add primary category as genre
        $primary_category = $categories[0];
        $schema['genre'] = $primary_category->name;
        
        // Add category URLs for better linking
        if ( count( $categories ) > 1 ) {
            $category_urls = array();
            foreach ( $categories as $category ) {
                $category_urls[] = \get_category_link( $category->term_id );
            }
            
            $schema['mentions'] = array_map( function( $url ) {
                return array(
                    '@type' => 'Thing',
                    'url' => $url,
                );
            }, $category_urls );
        }
    }
    
    /**
     * Add content metrics (reading time, word count)
     * 
     * @param array   $schema Schema array (by reference)
     * @param WP_Post $post   Post object
     */
    private function add_content_metrics( &$schema, $post ) {
        $content = wp_strip_all_tags( $post->post_content );
        
        if ( $this->config['enable_word_count'] ) {
            $word_count = str_word_count( $content );
            $schema['wordCount'] = $word_count;
        }
        
        if ( $this->config['enable_reading_time'] ) {
            // Calculate reading time (average 200 words per minute)
            $word_count = str_word_count( $content );
            $reading_time_minutes = max( 1, ceil( $word_count / 200 ) );
            
            // Convert to ISO 8601 duration format
            $schema['timeRequired'] = 'PT' . $reading_time_minutes . 'M';
        }
    }
    
    /**
     * Add article body for content understanding
     * 
     * @param array   $schema Schema array (by reference)
     * @param WP_Post $post   Post object
     */
    private function add_article_body( &$schema, $post ) {
        // Add first paragraph as articleBody for better understanding
        $content = wp_strip_all_tags( $post->post_content );
        $paragraphs = explode( "\n\n", $content );
        
        if ( ! empty( $paragraphs[0] ) && strlen( $paragraphs[0] ) > 50 ) {
            // Limit to first 500 characters for performance
            $first_paragraph = substr( $paragraphs[0], 0, 500 );
            if ( strlen( $paragraphs[0] ) > 500 ) {
                $first_paragraph .= '...';
            }
            
            $schema['articleBody'] = $first_paragraph;
        }
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed  $default Default value
     * @return mixed Configuration value
     */
    public function get_config( $key, $default = null ) {
        return isset( $this->config[ $key ] ) ? $this->config[ $key ] : $default;
    }
    
    /**
     * Update configuration
     * 
     * @param array $new_config New configuration values
     */
    public function update_config( $new_config ) {
        $this->config = array_merge( $this->config, $new_config );
    }
}