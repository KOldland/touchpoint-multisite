<?php
/**
 * Social Media Generator - Generates Open Graph, Twitter Cards, and social metadata
 * 
 * This class handles the generation of social media meta tags for various platforms.
 * It provides automatic social media optimization, supports multiple social platforms,
 * and includes validation tools to ensure optimal social sharing performance.
 * 
 * Supported Platforms:
 * - Facebook (Open Graph)
 * - Twitter (Twitter Cards)
 * - LinkedIn (Open Graph + specific tags)
 * - Pinterest (Open Graph + Pinterest-specific)
 * - WhatsApp (Open Graph)
 * - Telegram (Open Graph)
 * 
 * @package KHM_SEO\Social
 * @since 2.5.0
 */

namespace KHM_SEO\Social;

/**
 * Social Media Generator Class
 */
class SocialMediaGenerator {
    /**
     * @var array Social media configuration
     */
    private $config;

    /**
     * @var array Supported platforms
     */
    private $platforms;

    /**
     * @var array Default image dimensions
     */
    private $image_dimensions;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_config();
        $this->init_platforms();
        $this->init_image_dimensions();
    }

    /**
     * Initialize social media configuration
     */
    private function init_config() {
        $this->config = wp_parse_args(get_option('khm_seo_social_settings', []), [
            'enable_social_tags' => true,
            'enable_open_graph' => true,
            'enable_twitter_cards' => true,
            'enable_linkedin' => true,
            'enable_pinterest' => false,
            'default_image' => '',
            'fallback_image' => '',
            'twitter_username' => '',
            'facebook_app_id' => '',
            'linkedin_company_id' => '',
            'image_optimization' => true,
            'auto_generate_descriptions' => true,
            'description_length' => 160,
            'use_featured_image' => true,
            'use_post_excerpt' => true,
            'include_site_name' => true,
            'locale' => 'en_US',
            'article_author' => true,
            'article_publisher' => true
        ]);
    }

    /**
     * Initialize supported platforms
     */
    private function init_platforms() {
        $this->platforms = [
            'facebook' => [
                'name' => 'Facebook',
                'prefix' => 'og',
                'required_tags' => ['title', 'type', 'image', 'url'],
                'optional_tags' => ['description', 'site_name', 'locale'],
                'image_specs' => [
                    'min_width' => 200,
                    'min_height' => 200,
                    'recommended_ratio' => '1.91:1',
                    'max_size' => '8MB'
                ]
            ],
            'twitter' => [
                'name' => 'Twitter',
                'prefix' => 'twitter',
                'required_tags' => ['card', 'title', 'description'],
                'optional_tags' => ['site', 'creator', 'image'],
                'card_types' => ['summary', 'summary_large_image', 'app', 'player'],
                'image_specs' => [
                    'min_width' => 120,
                    'min_height' => 120,
                    'summary_ratio' => '1:1',
                    'large_ratio' => '2:1',
                    'max_size' => '5MB'
                ]
            ],
            'linkedin' => [
                'name' => 'LinkedIn',
                'prefix' => 'og',
                'required_tags' => ['title', 'type', 'image', 'url', 'description'],
                'optional_tags' => ['site_name', 'article:author'],
                'image_specs' => [
                    'min_width' => 1200,
                    'min_height' => 627,
                    'recommended_ratio' => '1.91:1',
                    'max_size' => '5MB'
                ]
            ],
            'pinterest' => [
                'name' => 'Pinterest',
                'prefix' => 'og',
                'required_tags' => ['title', 'type', 'image', 'url', 'description'],
                'optional_tags' => ['site_name'],
                'image_specs' => [
                    'min_width' => 600,
                    'min_height' => 900,
                    'recommended_ratio' => '2:3',
                    'max_size' => '20MB'
                ]
            ]
        ];
    }

    /**
     * Initialize image dimensions for different platforms
     */
    private function init_image_dimensions() {
        $this->image_dimensions = [
            'facebook_share' => ['width' => 1200, 'height' => 630],
            'twitter_large' => ['width' => 1024, 'height' => 512],
            'twitter_summary' => ['width' => 400, 'height' => 400],
            'linkedin_share' => ['width' => 1200, 'height' => 627],
            'pinterest_pin' => ['width' => 735, 'height' => 1102]
        ];
    }

    /**
     * Generate social media tags for current context
     *
     * @param mixed $context Post, term, user, or null for global
     * @return string Social media meta tags
     */
    public function generate_social_tags($context = null) {
        if (!$this->config['enable_social_tags']) {
            return '';
        }

        $tags = [];
        
        // Generate Open Graph tags
        if ($this->config['enable_open_graph']) {
            $og_tags = $this->generate_open_graph_tags($context);
            $tags = array_merge($tags, $og_tags);
        }

        // Generate Twitter Card tags
        if ($this->config['enable_twitter_cards']) {
            $twitter_tags = $this->generate_twitter_card_tags($context);
            $tags = array_merge($tags, $twitter_tags);
        }

        // Generate platform-specific tags
        if ($this->config['enable_linkedin']) {
            $linkedin_tags = $this->generate_linkedin_tags($context);
            $tags = array_merge($tags, $linkedin_tags);
        }

        if ($this->config['enable_pinterest']) {
            $pinterest_tags = $this->generate_pinterest_tags($context);
            $tags = array_merge($tags, $pinterest_tags);
        }

        return $this->format_meta_tags($tags);
    }

    /**
     * Generate Open Graph tags
     *
     * @param mixed $context Content context
     * @return array Open Graph tags
     */
    private function generate_open_graph_tags($context) {
        $tags = [];

        // Get basic information
        $title = $this->get_social_title($context);
        $description = $this->get_social_description($context);
        $url = $this->get_canonical_url($context);
        $image = $this->get_social_image($context);
        $type = $this->get_open_graph_type($context);

        // Required Open Graph tags
        $tags['og:title'] = $title;
        $tags['og:type'] = $type;
        $tags['og:url'] = $url;
        $tags['og:description'] = $description;

        // Image tags
        if ($image) {
            $tags['og:image'] = $image['url'];
            if (!empty($image['width'])) {
                $tags['og:image:width'] = $image['width'];
            }
            if (!empty($image['height'])) {
                $tags['og:image:height'] = $image['height'];
            }
            if (!empty($image['alt'])) {
                $tags['og:image:alt'] = $image['alt'];
            }
        }

        // Site information
        if ($this->config['include_site_name']) {
            $tags['og:site_name'] = get_bloginfo('name');
        }

        $tags['og:locale'] = $this->config['locale'];

        // Article-specific tags
        if ($context instanceof WP_Post && $this->is_article_type($context)) {
            $article_tags = $this->generate_article_tags($context);
            $tags = array_merge($tags, $article_tags);
        }

        // Facebook-specific tags
        if (!empty($this->config['facebook_app_id'])) {
            $tags['fb:app_id'] = $this->config['facebook_app_id'];
        }

        return apply_filters('khm_seo_open_graph_tags', $tags, $context);
    }

    /**
     * Generate Twitter Card tags
     *
     * @param mixed $context Content context
     * @return array Twitter Card tags
     */
    private function generate_twitter_card_tags($context) {
        $tags = [];

        // Determine card type
        $card_type = $this->get_twitter_card_type($context);
        $tags['twitter:card'] = $card_type;

        // Basic information
        $title = $this->get_social_title($context);
        $description = $this->get_social_description($context);
        $image = $this->get_social_image($context, 'twitter');

        $tags['twitter:title'] = $title;
        $tags['twitter:description'] = $description;

        // Image
        if ($image) {
            $tags['twitter:image'] = $image['url'];
            if (!empty($image['alt'])) {
                $tags['twitter:image:alt'] = $image['alt'];
            }
        }

        // Site and creator
        if (!empty($this->config['twitter_username'])) {
            $tags['twitter:site'] = '@' . ltrim($this->config['twitter_username'], '@');
        }

        // Author information
        if ($context instanceof WP_Post && $this->config['article_author']) {
            $author_twitter = get_user_meta($context->post_author, 'twitter', true);
            if ($author_twitter) {
                $tags['twitter:creator'] = '@' . ltrim($author_twitter, '@');
            }
        }

        return apply_filters('khm_seo_twitter_card_tags', $tags, $context);
    }

    /**
     * Generate LinkedIn-specific tags
     *
     * @param mixed $context Content context
     * @return array LinkedIn tags
     */
    private function generate_linkedin_tags($context) {
        $tags = [];

        // LinkedIn uses Open Graph but with some specific requirements
        if ($context instanceof WP_Post && $this->is_article_type($context)) {
            // LinkedIn prefers article:author for professional content
            $author = get_user_by('ID', $context->post_author);
            if ($author) {
                $linkedin_profile = get_user_meta($author->ID, 'linkedin', true);
                if ($linkedin_profile) {
                    $tags['article:author'] = $linkedin_profile;
                }
            }

            // LinkedIn company page
            if (!empty($this->config['linkedin_company_id'])) {
                $tags['article:publisher'] = 'https://www.linkedin.com/company/' . $this->config['linkedin_company_id'];
            }
        }

        return apply_filters('khm_seo_linkedin_tags', $tags, $context);
    }

    /**
     * Generate Pinterest-specific tags
     *
     * @param mixed $context Content context
     * @return array Pinterest tags
     */
    private function generate_pinterest_tags($context) {
        $tags = [];

        // Pinterest-specific meta tags
        $tags['pinterest:rich_pin'] = 'true';
        
        // Pinterest prefers certain image dimensions
        $image = $this->get_social_image($context, 'pinterest');
        if ($image) {
            $tags['pinterest:image'] = $image['url'];
        }

        // Pinterest description (can be longer than other platforms)
        $description = $this->get_social_description($context, 500);
        if ($description) {
            $tags['pinterest:description'] = $description;
        }

        return apply_filters('khm_seo_pinterest_tags', $tags, $context);
    }

    /**
     * Generate article-specific Open Graph tags
     *
     * @param WP_Post $post Post object
     * @return array Article tags
     */
    private function generate_article_tags($post) {
        $tags = [];

        // Publication date
        $tags['article:published_time'] = get_the_date('c', $post);
        
        // Modified date
        $modified_date = get_the_modified_date('c', $post);
        if ($modified_date !== get_the_date('c', $post)) {
            $tags['article:modified_time'] = $modified_date;
        }

        // Author
        if ($this->config['article_author']) {
            $author = get_user_by('ID', $post->post_author);
            if ($author) {
                $author_url = get_author_posts_url($author->ID);
                $tags['article:author'] = $author_url;
                
                // Facebook profile if available
                $facebook_profile = get_user_meta($author->ID, 'facebook', true);
                if ($facebook_profile) {
                    $tags['article:author'] = $facebook_profile;
                }
            }
        }

        // Publisher
        if ($this->config['article_publisher']) {
            $facebook_page = get_option('khm_seo_social_facebook_page', '');
            if ($facebook_page) {
                $tags['article:publisher'] = $facebook_page;
            }
        }

        // Section (category)
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $tags['article:section'] = $categories[0]->name;
        }

        // Tags
        $post_tags = get_the_tags($post->ID);
        if ($post_tags) {
            foreach (array_slice($post_tags, 0, 6) as $tag) {
                $tags['article:tag'] = $tag->name;
            }
        }

        return $tags;
    }

    /**
     * Get social media title
     *
     * @param mixed $context Content context
     * @return string Social title
     */
    private function get_social_title($context) {
        // Check for custom social title
        if ($context instanceof WP_Post) {
            $custom_title = get_post_meta($context->ID, '_khm_seo_social_title', true);
            if (!empty($custom_title)) {
                return $custom_title;
            }

            return $context->post_title;
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            return $term->name;
        } elseif (is_author()) {
            $author = get_queried_object();
            return $author->display_name;
        } elseif (is_home() || is_front_page()) {
            return get_bloginfo('name');
        }

        return get_bloginfo('name');
    }

    /**
     * Get social media description
     *
     * @param mixed $context Content context
     * @param int $max_length Maximum description length
     * @return string Social description
     */
    private function get_social_description($context, $max_length = null) {
        if (is_null($max_length)) {
            $max_length = $this->config['description_length'];
        }

        // Check for custom social description
        if ($context instanceof WP_Post) {
            $custom_description = get_post_meta($context->ID, '_khm_seo_social_description', true);
            if (!empty($custom_description)) {
                return wp_trim_words($custom_description, $max_length);
            }

            // Use post excerpt if available and enabled
            if ($this->config['use_post_excerpt'] && !empty($context->post_excerpt)) {
                return wp_trim_words(wp_strip_all_tags($context->post_excerpt), $max_length);
            }

            // Generate from content
            if ($this->config['auto_generate_descriptions']) {
                $content = wp_strip_all_tags($context->post_content);
                return wp_trim_words($content, $max_length);
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if (!empty($term->description)) {
                return wp_trim_words(wp_strip_all_tags($term->description), $max_length);
            }
        }

        // Fallback to site description
        return wp_trim_words(get_bloginfo('description'), $max_length);
    }

    /**
     * Get social media image
     *
     * @param mixed $context Content context
     * @param string $platform Platform-specific optimization
     * @return array|null Image data
     */
    private function get_social_image($context, $platform = 'facebook') {
        $image = null;

        // Check for custom social image
        if ($context instanceof WP_Post) {
            $custom_image_id = get_post_meta($context->ID, '_khm_seo_social_image', true);
            if ($custom_image_id) {
                $image = $this->get_image_data($custom_image_id, $platform);
                if ($image) {
                    return $image;
                }
            }

            // Use featured image if enabled
            if ($this->config['use_featured_image']) {
                $featured_image_id = get_post_thumbnail_id($context->ID);
                if ($featured_image_id) {
                    $image = $this->get_image_data($featured_image_id, $platform);
                    if ($image) {
                        return $image;
                    }
                }
            }

            // Extract first image from content
            $first_image = $this->extract_first_image($context->post_content);
            if ($first_image) {
                return $first_image;
            }
        }

        // Use default image
        if (!empty($this->config['default_image'])) {
            $default_image_id = attachment_url_to_postid($this->config['default_image']);
            if ($default_image_id) {
                $image = $this->get_image_data($default_image_id, $platform);
                if ($image) {
                    return $image;
                }
            }

            // Direct URL fallback
            return [
                'url' => $this->config['default_image'],
                'width' => '',
                'height' => '',
                'alt' => get_bloginfo('name') . ' - Default Social Image'
            ];
        }

        // Fallback image
        if (!empty($this->config['fallback_image'])) {
            return [
                'url' => $this->config['fallback_image'],
                'width' => '',
                'height' => '',
                'alt' => get_bloginfo('name') . ' - Social Image'
            ];
        }

        return null;
    }

    /**
     * Get image data for specific platform
     *
     * @param int $attachment_id Attachment ID
     * @param string $platform Platform identifier
     * @return array|null Image data
     */
    private function get_image_data($attachment_id, $platform = 'facebook') {
        // Determine optimal size based on platform
        $size = $this->get_optimal_image_size($platform);
        $image_data = wp_get_attachment_image_src($attachment_id, $size);
        
        if (!$image_data) {
            // Fallback to full size
            $image_data = wp_get_attachment_image_src($attachment_id, 'full');
        }

        if (!$image_data) {
            return null;
        }

        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        
        return [
            'url' => $image_data[0],
            'width' => $image_data[1],
            'height' => $image_data[2],
            'alt' => $alt_text ?: get_the_title($attachment_id)
        ];
    }

    /**
     * Get optimal image size for platform
     *
     * @param string $platform Platform identifier
     * @return string WordPress image size
     */
    private function get_optimal_image_size($platform) {
        $size_map = [
            'facebook' => 'large',
            'twitter' => 'large',
            'linkedin' => 'large',
            'pinterest' => 'full'
        ];

        return $size_map[$platform] ?? 'large';
    }

    /**
     * Extract first image from post content
     *
     * @param string $content Post content
     * @return array|null Image data
     */
    private function extract_first_image($content) {
        // Look for images in content
        preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches);
        
        if (!empty($matches[1])) {
            $image_url = $matches[1];
            
            // Try to get alt text
            preg_match('/alt=[\'"]([^\'"]*)[\'"]/', $matches[0], $alt_matches);
            $alt_text = !empty($alt_matches[1]) ? $alt_matches[1] : '';
            
            return [
                'url' => $image_url,
                'width' => '',
                'height' => '',
                'alt' => $alt_text
            ];
        }

        return null;
    }

    /**
     * Get canonical URL for current context
     *
     * @param mixed $context Content context
     * @return string Canonical URL
     */
    private function get_canonical_url($context) {
        if ($context instanceof WP_Post) {
            return get_permalink($context);
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            return get_term_link($term);
        } elseif (is_author()) {
            $author = get_queried_object();
            return get_author_posts_url($author->ID);
        }

        return home_url();
    }

    /**
     * Get Open Graph type
     *
     * @param mixed $context Content context
     * @return string Open Graph type
     */
    private function get_open_graph_type($context) {
        if ($context instanceof WP_Post) {
            // Check for custom type
            $custom_type = get_post_meta($context->ID, '_khm_seo_og_type', true);
            if (!empty($custom_type)) {
                return $custom_type;
            }

            // Default based on post type
            if ($this->is_article_type($context)) {
                return 'article';
            }

            return 'website';
        } elseif (is_home() || is_front_page()) {
            return 'website';
        }

        return 'website';
    }

    /**
     * Get Twitter card type
     *
     * @param mixed $context Content context
     * @return string Twitter card type
     */
    private function get_twitter_card_type($context) {
        // Check for custom card type
        if ($context instanceof WP_Post) {
            $custom_card = get_post_meta($context->ID, '_khm_seo_twitter_card', true);
            if (!empty($custom_card)) {
                return $custom_card;
            }
        }

        // Determine based on image
        $image = $this->get_social_image($context, 'twitter');
        if ($image && !empty($image['width']) && !empty($image['height'])) {
            $ratio = $image['width'] / $image['height'];
            
            // Use large image card for landscape images
            if ($ratio > 1.3) {
                return 'summary_large_image';
            }
        }

        return 'summary';
    }

    /**
     * Check if post is article type
     *
     * @param WP_Post $post Post object
     * @return bool Is article type
     */
    private function is_article_type($post) {
        $article_types = ['post', 'article', 'news'];
        return in_array($post->post_type, apply_filters('khm_seo_article_post_types', $article_types));
    }

    /**
     * Format meta tags for output
     *
     * @param array $tags Meta tags array
     * @return string Formatted meta tags
     */
    private function format_meta_tags($tags) {
        if (empty($tags)) {
            return '';
        }

        $output = [];
        
        foreach ($tags as $property => $content) {
            if (empty($content)) {
                continue;
            }

            // Escape content
            $content = esc_attr($content);
            
            // Determine if it's an Open Graph tag or regular meta
            if (strpos($property, 'og:') === 0 || strpos($property, 'fb:') === 0 || strpos($property, 'article:') === 0) {
                $output[] = '<meta property="' . esc_attr($property) . '" content="' . $content . '">';
            } else {
                $output[] = '<meta name="' . esc_attr($property) . '" content="' . $content . '">';
            }
        }

        return implode("\n", $output);
    }

    /**
     * Validate social media tags
     *
     * @param array $tags Tags to validate
     * @return array Validation results
     */
    public function validate_social_tags($tags) {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'platforms' => []
        ];

        foreach ($this->platforms as $platform_id => $platform) {
            $platform_validation = $this->validate_platform_tags($tags, $platform);
            $validation['platforms'][$platform_id] = $platform_validation;
            
            if (!$platform_validation['valid']) {
                $validation['valid'] = false;
                $validation['errors'] = array_merge($validation['errors'], $platform_validation['errors']);
            }
            
            $validation['warnings'] = array_merge($validation['warnings'], $platform_validation['warnings']);
        }

        return $validation;
    }

    /**
     * Validate tags for specific platform
     *
     * @param array $tags Tags array
     * @param array $platform Platform configuration
     * @return array Platform validation results
     */
    private function validate_platform_tags($tags, $platform) {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        $prefix = $platform['prefix'];

        // Check required tags
        foreach ($platform['required_tags'] as $required_tag) {
            $tag_key = $prefix . ':' . $required_tag;
            
            if (!isset($tags[$tag_key]) || empty($tags[$tag_key])) {
                $validation['valid'] = false;
                $validation['errors'][] = "Missing required tag: {$tag_key}";
            }
        }

        // Validate image specifications
        if (isset($tags[$prefix . ':image']) && !empty($platform['image_specs'])) {
            $image_validation = $this->validate_image_specs($tags, $platform);
            if (!empty($image_validation['warnings'])) {
                $validation['warnings'] = array_merge($validation['warnings'], $image_validation['warnings']);
            }
        }

        return $validation;
    }

    /**
     * Validate image specifications
     *
     * @param array $tags Tags array
     * @param array $platform Platform configuration
     * @return array Image validation results
     */
    private function validate_image_specs($tags, $platform) {
        $validation = [
            'warnings' => []
        ];

        $prefix = $platform['prefix'];
        $specs = $platform['image_specs'];
        
        $width = isset($tags[$prefix . ':image:width']) ? intval($tags[$prefix . ':image:width']) : 0;
        $height = isset($tags[$prefix . ':image:height']) ? intval($tags[$prefix . ':image:height']) : 0;

        if ($width && $height) {
            if ($width < $specs['min_width']) {
                $validation['warnings'][] = "Image width ({$width}px) below recommended minimum ({$specs['min_width']}px) for {$platform['name']}";
            }
            
            if ($height < $specs['min_height']) {
                $validation['warnings'][] = "Image height ({$height}px) below recommended minimum ({$specs['min_height']}px) for {$platform['name']}";
            }
        }

        return $validation;
    }

    /**
     * Get social media statistics
     *
     * @return array Social media statistics
     */
    public function get_social_statistics() {
        $stats = [
            'total_posts_with_social' => 0,
            'posts_with_custom_images' => 0,
            'posts_with_custom_descriptions' => 0,
            'platforms_enabled' => 0,
            'last_generated' => get_option('khm_seo_social_last_generated', 0)
        ];

        // Count enabled platforms
        if ($this->config['enable_open_graph']) $stats['platforms_enabled']++;
        if ($this->config['enable_twitter_cards']) $stats['platforms_enabled']++;
        if ($this->config['enable_linkedin']) $stats['platforms_enabled']++;
        if ($this->config['enable_pinterest']) $stats['platforms_enabled']++;

        // Count posts with social metadata
        global $wpdb;
        
        $stats['posts_with_custom_images'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_khm_seo_social_image' 
             AND meta_value != ''"
        );

        $stats['posts_with_custom_descriptions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_khm_seo_social_description' 
             AND meta_value != ''"
        );

        $stats['total_posts_with_social'] = wp_count_posts('post')->publish + wp_count_posts('page')->publish;

        return $stats;
    }
}