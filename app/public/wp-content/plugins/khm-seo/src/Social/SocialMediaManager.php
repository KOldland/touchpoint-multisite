<?php
/**
 * Social Media Manager - Phase 3.4
 * 
 * Advanced social media optimization for enhanced sharing and engagement.
 * Extends basic Open Graph and Twitter Card functionality with rich previews.
 * 
 * Features:
 * - Enhanced Open Graph meta tags
 * - Advanced Twitter Card types
 * - LinkedIn optimization
 * - Pinterest rich pins
 * - Custom social meta for posts
 * - Social media preview generation
 * - Image optimization for social sharing
 * 
 * @package KHM_SEO\Social
 * @since 3.0.0
 * @version 3.0.0
 */

namespace KHM_SEO\Social;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Social Media Manager Class
 * Handles advanced social media optimization and previews
 */
class SocialMediaManager {
    
    /**
     * @var array Social media configuration
     */
    private $config;
    
    /**
     * @var array Supported platforms
     */
    private $platforms = array(
        'facebook' => 'Facebook',
        'twitter' => 'Twitter',
        'linkedin' => 'LinkedIn',
        'pinterest' => 'Pinterest',
        'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        $this->init_hooks();
    }
    
    /**
     * Load social media configuration
     */
    private function load_config() {
        $defaults = array(
            'enable_enhanced_og' => true,
            'enable_twitter_cards' => true,
            'enable_linkedin_tags' => true,
            'enable_pinterest_tags' => true,
            'default_card_type' => 'summary_large_image',
            'twitter_site' => '',
            'twitter_creator' => '',
            'facebook_app_id' => '',
            'facebook_admin_ids' => '',
            'og_image_dimensions' => array( 'width' => 1200, 'height' => 630 ),
            'twitter_image_dimensions' => array( 'width' => 1024, 'height' => 512 ),
            'auto_generate_og_images' => false,
            'social_image_fallback' => '',
        );
        
        $this->config = wp_parse_args( \get_option( 'khm_seo_social_settings', array() ), $defaults );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Enhanced social meta output
        add_action( 'wp_head', array( $this, 'output_enhanced_og_tags' ), 10 );
        add_action( 'wp_head', array( $this, 'output_enhanced_twitter_tags' ), 12 );
        add_action( 'wp_head', array( $this, 'output_linkedin_tags' ), 14 );
        add_action( 'wp_head', array( $this, 'output_pinterest_tags' ), 16 );
        add_action( 'wp_head', array( $this, 'output_additional_social_tags' ), 18 );
        
        // Admin hooks for social meta configuration
        add_action( 'add_meta_boxes', array( $this, 'add_social_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_social_meta_data' ) );
        
        // AJAX hooks for social preview
        add_action( 'wp_ajax_khm_seo_social_preview', array( $this, 'ajax_social_preview' ) );
        add_action( 'wp_ajax_khm_seo_generate_social_image', array( $this, 'ajax_generate_social_image' ) );
        
        // Admin assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }
    
    /**
     * Output enhanced Open Graph tags
     */
    public function output_enhanced_og_tags() {
        if ( ! $this->config['enable_enhanced_og'] ) {
            return;
        }
        
        global $post;
        
        // Basic Open Graph tags
        echo '<meta property="og:site_name" content="' . esc_attr( \get_bloginfo( 'name' ) ) . '">' . "\n";
        echo '<meta property="og:locale" content="' . esc_attr( $this->get_og_locale() ) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( $this->get_og_type() ) . '">' . "\n";
        
        // Title with custom social title support
        $og_title = $this->get_social_title( 'facebook' );
        if ( $og_title ) {
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
        }
        
        // Description with custom social description support
        $og_description = $this->get_social_description( 'facebook' );
        if ( $og_description ) {
            echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '">' . "\n";
        }
        
        // URL
        $og_url = $this->get_canonical_url();
        if ( $og_url ) {
            echo '<meta property="og:url" content="' . esc_url( $og_url ) . '">' . "\n";
        }
        
        // Image with enhanced support
        $this->output_og_image_tags();
        
        // Article-specific tags for posts
        // @phpstan-ignore-next-line WordPress function available in runtime
        if ( function_exists('is_single') && \is_single() && $post ) {
            $this->output_article_og_tags( $post );
        }
        
        // Facebook App ID
        if ( ! empty( $this->config['facebook_app_id'] ) ) {
            echo '<meta property="fb:app_id" content="' . esc_attr( $this->config['facebook_app_id'] ) . '">' . "\n";
        }
        
        // Facebook Admin IDs
        if ( ! empty( $this->config['facebook_admin_ids'] ) ) {
            $admin_ids = explode( ',', $this->config['facebook_admin_ids'] );
            foreach ( $admin_ids as $admin_id ) {
                $admin_id = trim( $admin_id );
                if ( ! empty( $admin_id ) ) {
                    echo '<meta property="fb:admins" content="' . esc_attr( $admin_id ) . '">' . "\n";
                }
            }
        }
    }
    
    /**
     * Output enhanced Twitter Card tags
     */
    public function output_enhanced_twitter_tags() {
        if ( ! $this->config['enable_twitter_cards'] ) {
            return;
        }
        
        global $post;
        
        // Determine card type
        $card_type = $this->get_twitter_card_type();
        echo '<meta name="twitter:card" content="' . esc_attr( $card_type ) . '">' . "\n";
        
        // Site and creator
        if ( ! empty( $this->config['twitter_site'] ) ) {
            echo '<meta name="twitter:site" content="' . esc_attr( $this->config['twitter_site'] ) . '">' . "\n";
        }
        
        $creator = $this->get_twitter_creator();
        if ( $creator ) {
            echo '<meta name="twitter:creator" content="' . esc_attr( $creator ) . '">' . "\n";
        }
        
        // Title with custom social title support
        $twitter_title = $this->get_social_title( 'twitter' );
        if ( $twitter_title ) {
            echo '<meta name="twitter:title" content="' . esc_attr( $twitter_title ) . '">' . "\n";
        }
        
        // Description with custom social description support
        $twitter_description = $this->get_social_description( 'twitter' );
        if ( $twitter_description ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $twitter_description ) . '">' . "\n";
        }
        
        // Image
        $twitter_image = $this->get_social_image( 'twitter' );
        if ( $twitter_image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $twitter_image ) . '">' . "\n";
            
            // Image alt text
            $image_alt = $this->get_social_image_alt( 'twitter' );
            if ( $image_alt ) {
                echo '<meta name="twitter:image:alt" content="' . esc_attr( $image_alt ) . '">' . "\n";
            }
        }
        
        // Product card specific tags (for WooCommerce)
        if ( $card_type === 'product' && function_exists( 'is_product' ) && \is_product() ) {
            $this->output_twitter_product_tags( $post );
        }
    }
    
    /**
     * Output LinkedIn specific tags
     */
    public function output_linkedin_tags() {
        if ( ! $this->config['enable_linkedin_tags'] ) {
            return;
        }
        
        // LinkedIn uses Open Graph tags, but we can add specific enhancements
        echo '<!-- LinkedIn Enhanced Tags -->' . "\n";
        
        // Company page link if available
        $linkedin_company = \get_option( 'khm_seo_linkedin_company' );
        if ( ! empty( $linkedin_company ) ) {
            echo '<meta property="article:publisher" content="' . esc_url( $linkedin_company ) . '">' . "\n";
        }
        
        // Author LinkedIn profile for articles
        if ( \is_single() ) {
            global $post;
            $author_linkedin = \get_user_meta( $post->post_author, 'linkedin_profile', true );
            if ( ! empty( $author_linkedin ) ) {
                echo '<meta property="article:author" content="' . esc_url( $author_linkedin ) . '">' . "\n";
            }
        }
    }
    
    /**
     * Output Pinterest Rich Pins tags
     */
    public function output_pinterest_tags() {
        if ( ! $this->config['enable_pinterest_tags'] ) {
            return;
        }
        
        echo '<!-- Pinterest Rich Pins -->' . "\n";
        
        global $post;
        
        if ( \is_single() && $post ) {
            // Article Rich Pin
            echo '<meta property="article:published_time" content="' . esc_attr( \get_the_date( 'c', $post ) ) . '">' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr( \get_the_modified_date( 'c', $post ) ) . '">' . "\n";
            
            // Author information
            $author_name = \get_the_author_meta( 'display_name', $post->post_author );
            if ( $author_name ) {
                echo '<meta property="article:author" content="' . esc_attr( $author_name ) . '">' . "\n";
            }
            
            // Pinterest specific image
            $pinterest_image = $this->get_social_image( 'pinterest' );
            if ( $pinterest_image ) {
                echo '<meta property="pinterest:image" content="' . esc_url( $pinterest_image ) . '">' . "\n";
            }
        }
        
        // Product Rich Pin (for WooCommerce)
        if ( function_exists( 'is_product' ) && \is_product() ) {
            $this->output_pinterest_product_tags();
        }
    }
    
    /**
     * Output additional social platform tags
     */
    public function output_additional_social_tags() {
        echo '<!-- Additional Social Platform Tags -->' . "\n";
        
        // WhatsApp sharing optimization
        $whatsapp_image = $this->get_social_image( 'whatsapp' );
        if ( $whatsapp_image ) {
            echo '<meta property="whatsapp:image" content="' . esc_url( $whatsapp_image ) . '">' . "\n";
        }
        
        // Telegram sharing optimization
        echo '<meta property="telegram:channel" content="@' . esc_attr( \get_option( 'khm_seo_telegram_channel', '' ) ) . '">' . "\n";
        
        // Additional meta for social sharing
        echo '<meta property="social:sharing:enabled" content="true">' . "\n";
    }
    
    /**
     * Get social media title for specific platform
     * 
     * @param string $platform Platform name
     * @return string Social media title
     */
    public function get_social_title( $platform = 'facebook' ) {
        global $post;
        
        // Try custom social title first
        if ( $post ) {
            $custom_title = \get_post_meta( $post->ID, "_khm_seo_social_{$platform}_title", true );
            if ( ! empty( $custom_title ) ) {
                return $custom_title;
            }
            
            // Fallback to generic social title
            $social_title = \get_post_meta( $post->ID, '_khm_seo_social_title', true );
            if ( ! empty( $social_title ) ) {
                return $social_title;
            }
        }
        
        // Fallback to SEO title
        return $this->get_seo_title();
    }
    
    /**
     * Get social media description for specific platform
     * 
     * @param string $platform Platform name
     * @return string Social media description
     */
    public function get_social_description( $platform = 'facebook' ) {
        global $post;
        
        // Try custom social description first
        if ( $post ) {
            $custom_description = \get_post_meta( $post->ID, "_khm_seo_social_{$platform}_description", true );
            if ( ! empty( $custom_description ) ) {
                return $custom_description;
            }
            
            // Fallback to generic social description
            $social_description = \get_post_meta( $post->ID, '_khm_seo_social_description', true );
            if ( ! empty( $social_description ) ) {
                return $social_description;
            }
        }
        
        // Fallback to SEO description
        return $this->get_seo_description();
    }
    
    /**
     * Get social media image for specific platform
     * 
     * @param string $platform Platform name
     * @return string|null Social media image URL
     */
    public function get_social_image( $platform = 'facebook' ) {
        global $post;
        
        // Try custom social image first
        if ( $post ) {
            $custom_image_id = \get_post_meta( $post->ID, "_khm_seo_social_{$platform}_image", true );
            if ( ! empty( $custom_image_id ) ) {
                $image_data = \wp_get_attachment_image_src( $custom_image_id, 'full' );
                if ( $image_data ) {
                    return $image_data[0];
                }
            }
            
            // Fallback to generic social image
            $social_image_id = \get_post_meta( $post->ID, '_khm_seo_social_image', true );
            if ( ! empty( $social_image_id ) ) {
                $image_data = \wp_get_attachment_image_src( $social_image_id, 'full' );
                if ( $image_data ) {
                    return $image_data[0];
                }
            }
            
            // Try featured image
            if ( \has_post_thumbnail( $post ) ) {
                $featured_image = \wp_get_attachment_image_src( \get_post_thumbnail_id( $post ), 'full' );
                if ( $featured_image ) {
                    return $featured_image[0];
                }
            }
        }
        
        // Fallback to default social image
        if ( ! empty( $this->config['social_image_fallback'] ) ) {
            return $this->config['social_image_fallback'];
        }
        
        return null;
    }
    
    /**
     * Get social media image alt text
     * 
     * @param string $platform Platform name
     * @return string Image alt text
     */
    public function get_social_image_alt( $platform = 'facebook' ) {
        global $post;
        
        if ( $post ) {
            // Try custom alt text
            $custom_alt = \get_post_meta( $post->ID, "_khm_seo_social_{$platform}_image_alt", true );
            if ( ! empty( $custom_alt ) ) {
                return $custom_alt;
            }
            
            // Try featured image alt
            if ( \has_post_thumbnail( $post ) ) {
                $image_id = \get_post_thumbnail_id( $post );
                $alt_text = \get_post_meta( $image_id, '_wp_attachment_image_alt', true );
                if ( ! empty( $alt_text ) ) {
                    return $alt_text;
                }
            }
        }
        
        // Fallback to title
        return $this->get_social_title( $platform );
    }
    
    /**
     * Get Open Graph locale
     * 
     * @return string OG locale
     */
    private function get_og_locale() {
        $locale = \get_locale();
        
        // Convert WordPress locale to OG locale format
        $og_locale_map = array(
            'en_US' => 'en_US',
            'en_GB' => 'en_GB',
            'es_ES' => 'es_ES',
            'fr_FR' => 'fr_FR',
            'de_DE' => 'de_DE',
            'it_IT' => 'it_IT',
            'pt_BR' => 'pt_BR',
            'ru_RU' => 'ru_RU',
            'ja' => 'ja_JP',
            'ko_KR' => 'ko_KR',
            'zh_CN' => 'zh_CN',
        );
        
        return isset( $og_locale_map[ $locale ] ) ? $og_locale_map[ $locale ] : 'en_US';
    }
    
    /**
     * Get Open Graph type
     * 
     * @return string OG type
     */
    private function get_og_type() {
        if ( \is_single() ) {
            return 'article';
        } elseif ( \is_page() ) {
            return 'website';
        } elseif ( function_exists( 'is_product' ) && \is_product() ) {
            return 'product';
        } else {
            return 'website';
        }
    }
    
    /**
     * Get Twitter card type
     * 
     * @return string Twitter card type
     */
    private function get_twitter_card_type() {
        global $post;
        
        // Check for custom card type
        if ( $post ) {
            $custom_card_type = \get_post_meta( $post->ID, '_khm_seo_twitter_card_type', true );
            if ( ! empty( $custom_card_type ) ) {
                return $custom_card_type;
            }
        }
        
        // Auto-determine based on content
        if ( function_exists( 'is_product' ) && \is_product() ) {
            return 'product';
        }
        
        // Check if we have a good image for large image card
        $twitter_image = $this->get_social_image( 'twitter' );
        if ( $twitter_image ) {
            return 'summary_large_image';
        }
        
        return $this->config['default_card_type'];
    }
    
    /**
     * Get Twitter creator handle
     * 
     * @return string|null Twitter creator handle
     */
    private function get_twitter_creator() {
        global $post;
        
        if ( $post ) {
            // Try post author Twitter
            $author_twitter = \get_user_meta( $post->post_author, 'twitter', true );
            if ( ! empty( $author_twitter ) ) {
                return $author_twitter;
            }
        }
        
        // Fallback to site Twitter
        return ! empty( $this->config['twitter_creator'] ) ? $this->config['twitter_creator'] : null;
    }
    
    /**
     * Output OG image tags with enhanced support
     */
    private function output_og_image_tags() {
        $og_image = $this->get_social_image( 'facebook' );
        
        if ( $og_image ) {
            echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
            
            // Get image dimensions
            $image_id = \attachment_url_to_postid( $og_image );
            if ( $image_id ) {
                $image_meta = \wp_get_attachment_metadata( $image_id );
                if ( $image_meta ) {
                    echo '<meta property="og:image:width" content="' . esc_attr( $image_meta['width'] ) . '">' . "\n";
                    echo '<meta property="og:image:height" content="' . esc_attr( $image_meta['height'] ) . '">' . "\n";
                }
                
                // Image alt text
                $image_alt = \get_post_meta( $image_id, '_wp_attachment_image_alt', true );
                if ( $image_alt ) {
                    echo '<meta property="og:image:alt" content="' . esc_attr( $image_alt ) . '">' . "\n";
                }
                
                // Image type
                $image_mime = \get_post_mime_type( $image_id );
                if ( $image_mime ) {
                    echo '<meta property="og:image:type" content="' . esc_attr( $image_mime ) . '">' . "\n";
                }
            }
        }
    }
    
    /**
     * Output article-specific OG tags
     * 
     * @param WP_Post $post Post object
     */
    private function output_article_og_tags( $post ) {
        // Publication date
        echo '<meta property="article:published_time" content="' . esc_attr( \get_the_date( 'c', $post ) ) . '">' . "\n";
        
        // Modified date
        echo '<meta property="article:modified_time" content="' . esc_attr( \get_the_modified_date( 'c', $post ) ) . '">' . "\n";
        
        // Author
        $author_name = \get_the_author_meta( 'display_name', $post->post_author );
        if ( $author_name ) {
            echo '<meta property="article:author" content="' . esc_attr( $author_name ) . '">' . "\n";
        }
        
        // Section/Category
        $categories = \get_the_category( $post->ID );
        if ( ! empty( $categories ) ) {
            echo '<meta property="article:section" content="' . esc_attr( $categories[0]->name ) . '">' . "\n";
        }
        
        // Tags
        $tags = \get_the_tags( $post->ID );
        if ( $tags && ! \is_wp_error( $tags ) ) {
            foreach ( array_slice( $tags, 0, 5 ) as $tag ) { // Limit to 5 tags
                echo '<meta property="article:tag" content="' . esc_attr( $tag->name ) . '">' . "\n";
            }
        }
    }
    
    /**
     * Output Twitter product card tags
     * 
     * @param WP_Post $post Product post
     */
    private function output_twitter_product_tags( $post ) {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return;
        }
        
        $product = \wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }
        
        echo '<meta name="twitter:label1" content="Price">' . "\n";
        echo '<meta name="twitter:data1" content="' . esc_attr( $product->get_price_html() ) . '">' . "\n";
        
        if ( $product->is_in_stock() ) {
            echo '<meta name="twitter:label2" content="Availability">' . "\n";
            echo '<meta name="twitter:data2" content="In Stock">' . "\n";
        }
    }
    
    /**
     * Output Pinterest product tags
     */
    private function output_pinterest_product_tags() {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return;
        }
        
        global $post;
        $product = \wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }
        
        echo '<meta property="product:price:amount" content="' . esc_attr( $product->get_price() ) . '">' . "\n";
        echo '<meta property="product:price:currency" content="' . esc_attr( \get_woocommerce_currency() ) . '">' . "\n";
        
        if ( $product->is_in_stock() ) {
            echo '<meta property="product:availability" content="in stock">' . "\n";
        } else {
            echo '<meta property="product:availability" content="out of stock">' . "\n";
        }
    }
    
    /**
     * Get SEO title (fallback method)
     */
    private function get_seo_title() {
        // This would integrate with the main MetaManager
        global $post;
        
        if ( $post ) {
            $custom_title = \get_post_meta( $post->ID, '_khm_seo_title', true );
            if ( ! empty( $custom_title ) ) {
                return $custom_title;
            }
        }
        
        return \wp_get_document_title();
    }
    
    /**
     * Get SEO description (fallback method)
     */
    private function get_seo_description() {
        global $post;
        
        if ( $post ) {
            $custom_description = \get_post_meta( $post->ID, '_khm_seo_description', true );
            if ( ! empty( $custom_description ) ) {
                return $custom_description;
            }
            
            if ( ! empty( $post->post_excerpt ) ) {
                return wp_strip_all_tags( $post->post_excerpt );
            }
        }
        
        return \get_bloginfo( 'description' );
    }
    
    /**
     * Get canonical URL
     */
    private function get_canonical_url() {
        global $wp;
        return \home_url( $wp->request );
    }
    
    /**
     * Add social media meta boxes
     */
    public function add_social_meta_boxes() {
        $post_types = \get_post_types( array( 'public' => true ), 'names' );
        
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'khm-seo-social',
                __( 'Social Media Optimization', 'khm-seo' ),
                array( $this, 'render_social_meta_box' ),
                $post_type,
                'normal',
                'default'
            );
        }
    }
    
    /**
     * Render social media meta box
     * 
     * @param WP_Post $post Current post object
     */
    public function render_social_meta_box( $post ) {
        \wp_nonce_field( 'khm_seo_social_meta', 'khm_seo_social_nonce' );
        
        echo '<div class="khm-seo-social-tabs">';
        echo '<ul class="social-tab-nav">';
        
        foreach ( $this->platforms as $platform => $label ) {
            $active = $platform === 'facebook' ? ' active' : '';
            echo '<li><a href="#social-' . $platform . '" class="social-tab' . $active . '">' . $label . '</a></li>';
        }
        
        echo '</ul>';
        
        foreach ( $this->platforms as $platform => $label ) {
            $this->render_platform_fields( $post, $platform, $label );
        }
        
        echo '</div>';
        
        echo '<div class="social-preview-container">';
        echo '<h4>' . __( 'Social Media Previews', 'khm-seo' ) . '</h4>';
        echo '<button type="button" class="button" onclick="khmSeoGenerateSocialPreviews()">' . __( 'Generate Previews', 'khm-seo' ) . '</button>';
        echo '<div id="social-previews"></div>';
        echo '</div>';
    }
    
    /**
     * Render platform-specific fields
     * 
     * @param WP_Post $post Post object
     * @param string  $platform Platform key
     * @param string  $label Platform label
     */
    private function render_platform_fields( $post, $platform, $label ) {
        $display = $platform === 'facebook' ? 'block' : 'none';
        echo '<div id="social-' . $platform . '" class="social-tab-content" style="display:' . $display . ';">';
        echo '<h4>' . sprintf( __( '%s Optimization', 'khm-seo' ), $label ) . '</h4>';
        
        // Title field
        $title_value = \get_post_meta( $post->ID, "_khm_seo_social_{$platform}_title", true );
        echo '<p><label for="social_' . $platform . '_title">' . __( 'Title:', 'khm-seo' ) . '</label>';
        echo '<input type="text" name="social_' . $platform . '_title" id="social_' . $platform . '_title" value="' . esc_attr( $title_value ) . '" style="width:100%;" /></p>';
        
        // Description field
        $description_value = \get_post_meta( $post->ID, "_khm_seo_social_{$platform}_description", true );
        echo '<p><label for="social_' . $platform . '_description">' . __( 'Description:', 'khm-seo' ) . '</label>';
        echo '<textarea name="social_' . $platform . '_description" id="social_' . $platform . '_description" rows="3" style="width:100%;">' . esc_textarea( $description_value ) . '</textarea></p>';
        
        // Image field
        $image_value = \get_post_meta( $post->ID, "_khm_seo_social_{$platform}_image", true );
        echo '<p><label for="social_' . $platform . '_image">' . __( 'Image:', 'khm-seo' ) . '</label>';
        echo '<input type="hidden" name="social_' . $platform . '_image" id="social_' . $platform . '_image" value="' . esc_attr( $image_value ) . '" />';
        echo '<button type="button" class="button" onclick="khmSeoSelectSocialImage(\'' . $platform . '\')">' . __( 'Select Image', 'khm-seo' ) . '</button>';
        
        if ( $image_value ) {
            $image_src = \wp_get_attachment_image_src( $image_value, 'medium' );
            if ( $image_src ) {
                echo '<br><img src="' . esc_url( $image_src[0] ) . '" style="max-width:300px;margin-top:10px;" />';
            }
        }
        
        echo '</p>';
        
        echo '</div>';
    }
    
    /**
     * Save social media meta data
     * 
     * @param int $post_id Post ID
     */
    public function save_social_meta_data( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['khm_seo_social_nonce'] ) || 
             ! \wp_verify_nonce( $_POST['khm_seo_social_nonce'], 'khm_seo_social_meta' ) ) {
            return;
        }
        
        // Check permissions
        if ( ! \current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Save platform-specific data
        foreach ( array_keys( $this->platforms ) as $platform ) {
            $fields = array( 'title', 'description', 'image' );
            
            foreach ( $fields as $field ) {
                $meta_key = "_khm_seo_social_{$platform}_{$field}";
                $post_key = "social_{$platform}_{$field}";
                
                if ( isset( $_POST[ $post_key ] ) ) {
                    $value = sanitize_text_field( $_POST[ $post_key ] );
                    if ( $field === 'description' ) {
                        $value = sanitize_textarea_field( $_POST[ $post_key ] );
                    }
                    
                    if ( ! empty( $value ) ) {
                        \update_post_meta( $post_id, $meta_key, $value );
                    } else {
                        \delete_post_meta( $post_id, $meta_key );
                    }
                }
            }
        }
    }
    
    /**
     * AJAX handler for social media preview
     */
    public function ajax_social_preview() {
        \check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        $post_id = intval( $_POST['post_id'] ?? 0 );
        $platform = sanitize_text_field( $_POST['platform'] ?? 'facebook' );
        
        if ( ! $post_id ) {
            \wp_send_json_error( __( 'Invalid post ID', 'khm-seo' ) );
        }
        
        $preview_data = array(
            'title' => $this->get_social_title( $platform ),
            'description' => $this->get_social_description( $platform ),
            'image' => $this->get_social_image( $platform ),
            'url' => \get_permalink( $post_id ),
        );
        
        \wp_send_json_success( $preview_data );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
            \wp_enqueue_script(
                'khm-seo-social-admin',
                KHM_SEO_PLUGIN_URL . 'assets/js/social-admin.js',
                array( 'jquery' ),
                KHM_SEO_VERSION,
                true
            );
            
            \wp_localize_script( 'khm-seo-social-admin', 'khmSeoSocial', array(
                'ajaxurl' => \admin_url( 'admin-ajax.php' ),
                'nonce' => \wp_create_nonce( 'khm_seo_ajax' ),
                'platforms' => $this->platforms,
            ) );
            
            \wp_enqueue_style(
                'khm-seo-social-admin',
                KHM_SEO_PLUGIN_URL . 'assets/css/social-admin.css',
                array(),
                KHM_SEO_VERSION
            );
        }
    }
}