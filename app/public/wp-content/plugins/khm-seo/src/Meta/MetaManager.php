<?php
/**
 * Meta Manager for handling SEO meta tags.
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

namespace KHM_SEO\Meta;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Meta manager class for handling SEO titles, descriptions, and meta tags.
 */
class MetaManager {

    /**
     * Initialize the meta manager.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        add_action( 'wp_head', array( $this, 'output_meta_tags' ), 5 );
        add_filter( 'wp_title', array( $this, 'filter_title' ), 10, 2 );
        add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ) );
        add_action( 'wp_head', array( $this, 'output_og_tags' ), 10 );
        add_action( 'wp_head', array( $this, 'output_twitter_tags' ), 15 );
        add_action( 'wp_head', array( $this, 'output_robots_meta' ), 20 );
    }

    /**
     * Get the SEO title for current page.
     *
     * @return string The SEO title.
     */
    public function get_title() {
        global $post;

        if ( \is_singular() && $post ) {
            return $this->get_post_title( $post->ID );
        }

        if ( \is_category() || \is_tag() || \is_tax() ) {
            $term = \get_queried_object();
            return $this->get_term_title( $term->term_id );
        }

        if ( \is_home() || \is_front_page() ) {
            return $this->get_home_title();
        }

        if ( \is_author() ) {
            return $this->get_author_title();
        }

        if ( \is_search() ) {
            return $this->get_search_title();
        }

        if ( \is_404() ) {
            return $this->get_404_title();
        }

        // Fallback to default title
        return \get_bloginfo( 'name' );
    }

    /**
     * Get SEO title for a post.
     *
     * @param int $post_id Post ID.
     * @return string The post title.
     */
    public function get_post_title( $post_id ) {
        // Check for custom SEO title in post meta
        $custom_title = $this->get_post_meta_value( $post_id, 'title' );
        
        if ( ! empty( $custom_title ) ) {
            return $custom_title;
        }

        $post = get_post( $post_id );
        $options = get_option( 'khm_seo_titles', array() );
        
        // Get format for this post type
        $post_type = $post->post_type;
        $format_key = $post_type . '_title_format';
        $format = isset( $options[ $format_key ] ) ? $options[ $format_key ] : '%title% %sep% %sitename%';

        return $this->replace_title_variables( $format, $post );
    }

    /**
     * Get SEO title for a term.
     *
     * @param int $term_id Term ID.
     * @return string The term title.
     */
    public function get_term_title( $term_id ) {
        // Check for custom SEO title in term meta
        $custom_title = $this->get_term_meta_value( $term_id, 'title' );
        
        if ( ! empty( $custom_title ) ) {
            return $custom_title;
        }

        $term = get_term( $term_id );
        if ( is_wp_error( $term ) ) {
            return get_bloginfo( 'name' );
        }

        $options = get_option( 'khm_seo_titles', array() );
        
        $taxonomy = $term->taxonomy;
        $format_key = $taxonomy . '_title_format';
        $format = isset( $options[ $format_key ] ) ? $options[ $format_key ] : '%term_title% %sep% %sitename%';

        return $this->replace_title_variables( $format, null, $term );
    }

    /**
     * Get home page title.
     *
     * @return string The home title.
     */
    public function get_home_title() {
        $options = get_option( 'khm_seo_general', array() );
        
        if ( ! empty( $options['home_title'] ) ) {
            return $options['home_title'];
        }

        // Default format for home page
        $title_options = get_option( 'khm_seo_titles', array() );
        $format = isset( $title_options['home_title_format'] ) 
            ? $title_options['home_title_format'] 
            : '%sitename% %sep% %tagline%';

        return $this->replace_title_variables( $format );
    }

    /**
     * Get author page title.
     *
     * @return string The author title.
     */
    public function get_author_title() {
        $options = get_option( 'khm_seo_titles', array() );
        $format = isset( $options['author_title_format'] ) ? $options['author_title_format'] : '%author% %sep% %sitename%';
        
        $author = get_queried_object();
        return $this->replace_title_variables( $format, null, null, $author );
    }

    /**
     * Get search results title.
     *
     * @return string The search title.
     */
    public function get_search_title() {
        $options = get_option( 'khm_seo_titles', array() );
        $format = isset( $options['search_title_format'] ) ? $options['search_title_format'] : 'Search results for "%search_term%" %sep% %sitename%';
        
        return $this->replace_title_variables( $format );
    }

    /**
     * Get 404 page title.
     *
     * @return string The 404 title.
     */
    public function get_404_title() {
        $options = get_option( 'khm_seo_titles', array() );
        $format = isset( $options['404_title_format'] ) ? $options['404_title_format'] : 'Page Not Found %sep% %sitename%';
        
        return $this->replace_title_variables( $format );
    }

    /**
     * Get meta description for current page.
     *
     * @return string The meta description.
     */
    public function get_description() {
        global $post;

        if ( is_singular() && $post ) {
            return $this->get_post_description( $post->ID );
        }

        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            return $this->get_term_description( $term->term_id );
        }

        if ( is_home() || is_front_page() ) {
            return $this->get_home_description();
        }

        if ( is_author() ) {
            return $this->get_author_description();
        }

        if ( is_search() ) {
            return $this->get_search_description();
        }

        return '';
    }

    /**
     * Get meta description for a post.
     *
     * @param int $post_id Post ID.
     * @return string The post description.
     */
    public function get_post_description( $post_id ) {
        // Check for custom SEO description in post meta
        $custom_description = $this->get_post_meta_value( $post_id, 'description' );
        
        if ( ! empty( $custom_description ) ) {
            return $custom_description;
        }

        $post = get_post( $post_id );
        
        // Try to extract from excerpt
        if ( ! empty( $post->post_excerpt ) ) {
            return wp_trim_words( strip_tags( $post->post_excerpt ), 30, '...' );
        }

        // Try to extract from content
        if ( ! empty( $post->post_content ) ) {
            $content = wp_strip_all_tags( $post->post_content );
            $content = preg_replace( '/\s+/', ' ', $content );
            return wp_trim_words( $content, 30, '...' );
        }

        return '';
    }

    /**
     * Get meta description for a term.
     *
     * @param int $term_id Term ID.
     * @return string The term description.
     */
    public function get_term_description( $term_id ) {
        // Check for custom SEO description in term meta
        $custom_description = $this->get_term_meta_value( $term_id, 'description' );
        
        if ( ! empty( $custom_description ) ) {
            return $custom_description;
        }

        $term = get_term( $term_id );
        if ( is_wp_error( $term ) ) {
            return '';
        }

        // Use term description if available
        if ( ! empty( $term->description ) ) {
            return wp_trim_words( strip_tags( $term->description ), 30, '...' );
        }

        return '';
    }

    /**
     * Get home page description.
     *
     * @return string The home description.
     */
    public function get_home_description() {
        $options = get_option( 'khm_seo_general', array() );
        
        if ( ! empty( $options['home_description'] ) ) {
            return $options['home_description'];
        }

        // Fallback to site tagline
        return get_bloginfo( 'description' );
    }

    /**
     * Get author page description.
     *
     * @return string The author description.
     */
    public function get_author_description() {
        $author = get_queried_object();
        $bio = get_user_meta( $author->ID, 'description', true );
        
        if ( ! empty( $bio ) ) {
            return wp_trim_words( strip_tags( $bio ), 30, '...' );
        }

        return sprintf( 'Posts by %s', $author->display_name );
    }

    /**
     * Get search results description.
     *
     * @return string The search description.
     */
    public function get_search_description() {
        $search_term = get_search_query();
        return sprintf( 'Search results for "%s"', $search_term );
    }

    /**
     * Replace title variables with actual values.
     *
     * @param string $format The title format.
     * @param object $post   The post object.
     * @param object $term   The term object.
     * @param object $author The author object.
     * @return string The processed title.
     */
    private function replace_title_variables( $format, $post = null, $term = null, $author = null ) {
        $options = get_option( 'khm_seo_general', array() );
        $separator = isset( $options['separator'] ) ? $options['separator'] : '|';
        $sitename = get_bloginfo( 'name' );
        $tagline = get_bloginfo( 'description' );

        $replacements = array(
            '%sitename%'    => $sitename,
            '%tagline%'     => $tagline,
            '%sep%'         => $separator,
            '%title%'       => $post ? $post->post_title : '',
            '%term_title%'  => $term ? $term->name : '',
            '%author%'      => $author ? $author->display_name : ( is_author() ? get_the_author() : '' ),
            '%search_term%' => is_search() ? get_search_query() : '',
            '%date%'        => is_date() ? get_the_date() : '',
            '%year%'        => date( 'Y' ),
            '%month%'       => date( 'F' ),
            '%day%'         => date( 'j' ),
        );

        // Clean up the title
        $title = str_replace( array_keys( $replacements ), array_values( $replacements ), $format );
        $title = preg_replace( '/\s+/', ' ', $title );
        $title = trim( $title );
        
        // Remove empty separators
        $title = preg_replace( '/\s*' . preg_quote( $separator, '/' ) . '\s*' . preg_quote( $separator, '/' ) . '\s*/', ' ' . $separator . ' ', $title );
        $title = trim( $title, " \t\n\r\0\x0B" . $separator );

        return $title;
    }

    /**
     * Get post meta value for SEO fields.
     *
     * @param int    $post_id Post ID.
     * @param string $field   Field name.
     * @return string The meta value.
     */
    private function get_post_meta_value( $post_id, $field ) {
        $meta_key = '_khm_seo_' . $field;
        return get_post_meta( $post_id, $meta_key, true );
    }

    /**
     * Get term meta value for SEO fields.
     *
     * @param int    $term_id Term ID.
     * @param string $field   Field name.
     * @return string The meta value.
     */
    private function get_term_meta_value( $term_id, $field ) {
        $meta_key = 'khm_seo_' . $field;
        return get_term_meta( $term_id, $meta_key, true );
    }

    /**
     * Output basic meta tags.
     */
    public function output_meta_tags() {
        // SEO title override
        $title = $this->get_title();
        if ( $title ) {
            echo '<title>' . esc_html( $title ) . '</title>' . "\n";
        }

        // Meta description
        $description = $this->get_description();
        if ( $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        // Meta keywords (if enabled)
        $keywords = $this->get_meta_keywords();
        if ( $keywords ) {
            echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '">' . "\n";
        }

        // Canonical URL
        $canonical = $this->get_canonical_url();
        if ( $canonical ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
        }

        // Generator meta (optional branding)
        echo '<meta name="generator" content="KHM SEO ' . KHM_SEO_VERSION . '">' . "\n";
    }

    /**
     * Get meta keywords.
     *
     * @return string The meta keywords.
     */
    private function get_meta_keywords() {
        global $post;

        $options = get_option( 'khm_seo_meta', array() );
        
        // Check if keywords are enabled
        if ( empty( $options['enable_keywords'] ) ) {
            return '';
        }

        if ( is_singular() && $post ) {
            $custom_keywords = $this->get_post_meta_value( $post->ID, 'keywords' );
            if ( ! empty( $custom_keywords ) ) {
                return $custom_keywords;
            }
            
            // Auto-generate from tags
            $tags = get_the_tags( $post->ID );
            if ( $tags ) {
                $keywords = array();
                foreach ( $tags as $tag ) {
                    $keywords[] = $tag->name;
                }
                return implode( ', ', $keywords );
            }
        }

        return '';
    }

    /**
     * Get canonical URL.
     *
     * @return string The canonical URL.
     */
    private function get_canonical_url() {
        global $wp;
        
        if ( is_singular() ) {
            return get_permalink();
        }

        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            return get_term_link( $term );
        }

        if ( is_author() ) {
            $author = get_queried_object();
            return get_author_posts_url( $author->ID );
        }

        if ( is_home() || is_front_page() ) {
            return home_url( '/' );
        }

        // For other archive pages
        return home_url( add_query_arg( array(), $wp->request ) );
    }

    /**
     * Output Open Graph tags.
     */
    public function output_og_tags() {
        $options = get_option( 'khm_seo_meta', array() );
        
        // Check if Open Graph is enabled (default to enabled)
        if ( isset( $options['enable_og_tags'] ) && ! $options['enable_og_tags'] ) {
            return;
        }

        // Basic OG tags
        echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( $this->get_og_type() ) . '">' . "\n";
        
        $og_title = $this->get_title();
        if ( $og_title ) {
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
        }
        
        $og_description = $this->get_description();
        if ( $og_description ) {
            echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '">' . "\n";
        }
        
        $og_url = $this->get_canonical_url();
        if ( $og_url ) {
            echo '<meta property="og:url" content="' . esc_url( $og_url ) . '">' . "\n";
        }
        
        $og_image = $this->get_og_image();
        if ( $og_image ) {
            echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
            
            // Additional image properties
            $image_size = $this->get_image_dimensions( $og_image );
            if ( $image_size ) {
                echo '<meta property="og:image:width" content="' . esc_attr( $image_size['width'] ) . '">' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( $image_size['height'] ) . '">' . "\n";
            }
        }
    }

    /**
     * Output Twitter Card tags.
     */
    public function output_twitter_tags() {
        $options = get_option( 'khm_seo_meta', array() );
        
        // Check if Twitter Cards are enabled (default to enabled)
        if ( isset( $options['enable_twitter_cards'] ) && ! $options['enable_twitter_cards'] ) {
            return;
        }

        $twitter_image = $this->get_og_image();
        $card_type = $twitter_image ? 'summary_large_image' : 'summary';
        
        echo '<meta name="twitter:card" content="' . esc_attr( $card_type ) . '">' . "\n";
        
        if ( ! empty( $options['twitter_site'] ) ) {
            echo '<meta name="twitter:site" content="' . esc_attr( $options['twitter_site'] ) . '">' . "\n";
        }
        
        $twitter_title = $this->get_title();
        if ( $twitter_title ) {
            echo '<meta name="twitter:title" content="' . esc_attr( $twitter_title ) . '">' . "\n";
        }
        
        $twitter_description = $this->get_description();
        if ( $twitter_description ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $twitter_description ) . '">' . "\n";
        }
        
        if ( $twitter_image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $twitter_image ) . '">' . "\n";
        }
    }

    /**
     * Output robots meta tag.
     */
    public function output_robots_meta() {
        $robots_directives = array();
        
        // Check for noindex
        if ( $this->is_noindex() ) {
            $robots_directives[] = 'noindex';
        } else {
            $robots_directives[] = 'index';
        }
        
        // Check for nofollow
        if ( $this->is_nofollow() ) {
            $robots_directives[] = 'nofollow';
        } else {
            $robots_directives[] = 'follow';
        }
        
        // Additional directives
        if ( $this->is_noarchive() ) {
            $robots_directives[] = 'noarchive';
        }
        
        if ( $this->is_nosnippet() ) {
            $robots_directives[] = 'nosnippet';
        }
        
        if ( ! empty( $robots_directives ) ) {
            echo '<meta name="robots" content="' . esc_attr( implode( ', ', $robots_directives ) ) . '">' . "\n";
        }
    }

    /**
     * Filter WordPress title.
     */
    public function filter_title( $title, $sep = '' ) {
        $options = get_option( 'khm_seo_titles', array() );
        
        // Check if title rewriting is enabled (default to enabled)
        if ( isset( $options['enable_title_rewrite'] ) && ! $options['enable_title_rewrite'] ) {
            return $title;
        }

        $seo_title = $this->get_title();
        return $seo_title ? $seo_title : $title;
    }

    /**
     * Filter document title parts.
     */
    public function filter_title_parts( $title_parts ) {
        $options = get_option( 'khm_seo_titles', array() );
        
        // Check if title rewriting is enabled (default to enabled)
        if ( isset( $options['enable_title_rewrite'] ) && ! $options['enable_title_rewrite'] ) {
            return $title_parts;
        }

        $seo_title = $this->get_title();
        
        if ( $seo_title ) {
            return array( 'title' => $seo_title );
        }

        return $title_parts;
    }

    /**
     * Get Open Graph type.
     *
     * @return string The OG type.
     */
    private function get_og_type() {
        if ( is_singular() ) {
            return 'article';
        }
        
        return 'website';
    }

    /**
     * Get Open Graph image.
     *
     * @return string The image URL.
     */
    private function get_og_image() {
        global $post;
        
        if ( is_singular() && $post ) {
            // Check for custom OG image
            $custom_image = $this->get_post_meta_value( $post->ID, 'og_image' );
            if ( ! empty( $custom_image ) ) {
                return $custom_image;
            }
            
            // Use featured image
            if ( has_post_thumbnail( $post->ID ) ) {
                $featured_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'large' );
                if ( $featured_image ) {
                    return $featured_image[0];
                }
            }
        }
        
        // Fallback to default image
        $options = get_option( 'khm_seo_meta', array() );
        if ( ! empty( $options['default_og_image'] ) ) {
            return $options['default_og_image'];
        }
        
        return '';
    }

    /**
     * Get image dimensions.
     *
     * @param string $image_url Image URL.
     * @return array|false Image dimensions or false.
     */
    private function get_image_dimensions( $image_url ) {
        $attachment_id = attachment_url_to_postid( $image_url );
        
        if ( $attachment_id ) {
            $image_meta = wp_get_attachment_metadata( $attachment_id );
            if ( $image_meta && isset( $image_meta['width'] ) && isset( $image_meta['height'] ) ) {
                return array(
                    'width'  => $image_meta['width'],
                    'height' => $image_meta['height']
                );
            }
        }
        
        return false;
    }

    /**
     * Check if current page should be noindex.
     *
     * @return bool Whether page should be noindex.
     */
    private function is_noindex() {
        global $post;
        
        if ( is_singular() && $post ) {
            $noindex = $this->get_post_meta_value( $post->ID, 'noindex' );
            if ( $noindex ) {
                return true;
            }
        }
        
        // Check global settings
        $options = get_option( 'khm_seo_robots', array() );
        
        if ( is_search() && ! empty( $options['noindex_search'] ) ) {
            return true;
        }
        
        if ( is_404() && ! empty( $options['noindex_404'] ) ) {
            return true;
        }
        
        if ( is_archive() && ! empty( $options['noindex_archives'] ) ) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if current page should be nofollow.
     *
     * @return bool Whether page should be nofollow.
     */
    private function is_nofollow() {
        global $post;
        
        if ( is_singular() && $post ) {
            $nofollow = $this->get_post_meta_value( $post->ID, 'nofollow' );
            if ( $nofollow ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if current page should be noarchive.
     *
     * @return bool Whether page should be noarchive.
     */
    private function is_noarchive() {
        global $post;
        
        if ( is_singular() && $post ) {
            $noarchive = $this->get_post_meta_value( $post->ID, 'noarchive' );
            return (bool) $noarchive;
        }
        
        return false;
    }

    /**
     * Check if current page should be nosnippet.
     *
     * @return bool Whether page should be nosnippet.
     */
    private function is_nosnippet() {
        global $post;
        
        if ( is_singular() && $post ) {
            $nosnippet = $this->get_post_meta_value( $post->ID, 'nosnippet' );
            return (bool) $nosnippet;
        }
        
        return false;
    }
}
