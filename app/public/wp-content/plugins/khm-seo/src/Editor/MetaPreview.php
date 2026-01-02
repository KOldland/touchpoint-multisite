<?php
declare(strict_types=1);

namespace KHM_SEO\Editor;

/**
 * MetaPreview - Generates real-time SERP (Search Engine Results Page) previews
 * 
 * This class creates live previews of how content will appear in search results,
 * social media shares, and other contexts where meta data is displayed.
 * 
 * @package KHM_SEO\Editor
 * @since 2.0.0
 */
class MetaPreview
{
    /**
     * @var array Character limits for different meta elements
     */
    private array $character_limits = [
        'title' => 60,
        'description' => 160,
        'url' => 70
    ];

    /**
     * @var array Mobile character limits (typically shorter)
     */
    private array $mobile_limits = [
        'title' => 50,
        'description' => 140,
        'url' => 50
    ];

    /**
     * @var array Preview templates for different contexts
     */
    private array $preview_templates;

    /**
     * Initialize the Meta Preview generator
     */
    public function __construct()
    {
        $this->preview_templates = $this->get_preview_templates();
    }

    /**
     * Generate comprehensive meta preview
     *
     * @param array $meta_data Meta data including title, description, url
     * @return array Complete preview data for different contexts
     */
    public function generate_preview(array $meta_data): array
    {
        $validated_data = $this->validate_meta_data($meta_data);
        
        return [
            'google_search' => $this->generate_google_search_preview($validated_data),
            'facebook' => $this->generate_facebook_preview($validated_data),
            'twitter' => $this->generate_twitter_preview($validated_data),
            'mobile' => $this->generate_mobile_search_preview($validated_data),
            'analysis' => $this->analyze_meta_effectiveness($validated_data),
            'recommendations' => $this->generate_meta_recommendations($validated_data)
        ];
    }

    /**
     * Generate Google search result preview
     *
     * @param array $meta_data Validated meta data
     * @return array Google search preview data
     */
    private function generate_google_search_preview(array $meta_data): array
    {
        $title = $meta_data['title'] ?? '';
        $description = $meta_data['description'] ?? '';
        $url = $meta_data['url'] ?? '';

        // Truncate for Google display
        $truncated_title = $this->truncate_text($title, $this->character_limits['title']);
        $truncated_description = $this->truncate_text($description, $this->character_limits['description']);
        $truncated_url = $this->truncate_url($url, $this->character_limits['url']);

        return [
            'title' => [
                'text' => $truncated_title,
                'is_truncated' => strlen($title) > $this->character_limits['title'],
                'length' => strlen($title),
                'optimal_length' => $this->character_limits['title']
            ],
            'description' => [
                'text' => $truncated_description,
                'is_truncated' => strlen($description) > $this->character_limits['description'],
                'length' => strlen($description),
                'optimal_length' => $this->character_limits['description']
            ],
            'url' => [
                'text' => $truncated_url,
                'display_url' => $this->format_display_url($url),
                'is_truncated' => strlen($url) > $this->character_limits['url']
            ],
            'html' => $this->generate_google_search_html($truncated_title, $truncated_description, $truncated_url),
            'warnings' => $this->check_google_search_warnings($meta_data)
        ];
    }

    /**
     * Generate Facebook Open Graph preview
     *
     * @param array $meta_data Validated meta data
     * @return array Facebook preview data
     */
    private function generate_facebook_preview(array $meta_data): array
    {
        $title = $meta_data['title'] ?? '';
        $description = $meta_data['description'] ?? '';
        $url = $meta_data['url'] ?? '';
        $image = $meta_data['image'] ?? '';

        // Facebook has different limits
        $fb_title = $this->truncate_text($title, 100);
        $fb_description = $this->truncate_text($description, 300);

        return [
            'title' => [
                'text' => $fb_title,
                'is_truncated' => strlen($title) > 100,
                'length' => strlen($title),
                'optimal_length' => 100
            ],
            'description' => [
                'text' => $fb_description,
                'is_truncated' => strlen($description) > 300,
                'length' => strlen($description),
                'optimal_length' => 300
            ],
            'url' => $this->format_display_url($url),
            'image' => [
                'url' => $image,
                'has_image' => !empty($image),
                'recommended_size' => '1200x630px'
            ],
            'html' => $this->generate_facebook_preview_html($fb_title, $fb_description, $url, $image),
            'warnings' => $this->check_facebook_warnings($meta_data)
        ];
    }

    /**
     * Generate Twitter Card preview
     *
     * @param array $meta_data Validated meta data
     * @return array Twitter preview data
     */
    private function generate_twitter_preview(array $meta_data): array
    {
        $title = $meta_data['title'] ?? '';
        $description = $meta_data['description'] ?? '';
        $url = $meta_data['url'] ?? '';
        $image = $meta_data['image'] ?? '';

        // Twitter limits
        $twitter_title = $this->truncate_text($title, 70);
        $twitter_description = $this->truncate_text($description, 200);

        return [
            'title' => [
                'text' => $twitter_title,
                'is_truncated' => strlen($title) > 70,
                'length' => strlen($title),
                'optimal_length' => 70
            ],
            'description' => [
                'text' => $twitter_description,
                'is_truncated' => strlen($description) > 200,
                'length' => strlen($description),
                'optimal_length' => 200
            ],
            'url' => $this->format_display_url($url),
            'image' => [
                'url' => $image,
                'has_image' => !empty($image),
                'recommended_size' => '1200x600px'
            ],
            'card_type' => !empty($image) ? 'summary_large_image' : 'summary',
            'html' => $this->generate_twitter_preview_html($twitter_title, $twitter_description, $url, $image),
            'warnings' => $this->check_twitter_warnings($meta_data)
        ];
    }

    /**
     * Generate mobile search preview
     *
     * @param array $meta_data Validated meta data
     * @return array Mobile preview data
     */
    private function generate_mobile_search_preview(array $meta_data): array
    {
        $title = $meta_data['title'] ?? '';
        $description = $meta_data['description'] ?? '';
        $url = $meta_data['url'] ?? '';

        // Mobile has stricter limits
        $mobile_title = $this->truncate_text($title, $this->mobile_limits['title']);
        $mobile_description = $this->truncate_text($description, $this->mobile_limits['description']);
        $mobile_url = $this->truncate_url($url, $this->mobile_limits['url']);

        return [
            'title' => [
                'text' => $mobile_title,
                'is_truncated' => strlen($title) > $this->mobile_limits['title'],
                'length' => strlen($title),
                'optimal_length' => $this->mobile_limits['title']
            ],
            'description' => [
                'text' => $mobile_description,
                'is_truncated' => strlen($description) > $this->mobile_limits['description'],
                'length' => strlen($description),
                'optimal_length' => $this->mobile_limits['description']
            ],
            'url' => [
                'text' => $mobile_url,
                'display_url' => $this->format_display_url($url),
                'is_truncated' => strlen($url) > $this->mobile_limits['url']
            ],
            'html' => $this->generate_mobile_search_html($mobile_title, $mobile_description, $mobile_url),
            'warnings' => $this->check_mobile_warnings($meta_data)
        ];
    }

    /**
     * Analyze meta data effectiveness
     *
     * @param array $meta_data Validated meta data
     * @return array Analysis results
     */
    private function analyze_meta_effectiveness(array $meta_data): array
    {
        $title = $meta_data['title'] ?? '';
        $description = $meta_data['description'] ?? '';
        $focus_keyword = $meta_data['focus_keyword'] ?? '';

        $analysis = [
            'title_analysis' => $this->analyze_title_effectiveness($title, $focus_keyword),
            'description_analysis' => $this->analyze_description_effectiveness($description, $focus_keyword),
            'overall_score' => 0,
            'click_through_potential' => 'unknown'
        ];

        // Calculate overall score
        $title_score = $analysis['title_analysis']['score'];
        $description_score = $analysis['description_analysis']['score'];
        $analysis['overall_score'] = round(($title_score + $description_score) / 2);

        // Determine click-through potential
        $analysis['click_through_potential'] = $this->calculate_click_through_potential($analysis['overall_score']);

        return $analysis;
    }

    /**
     * Analyze title effectiveness
     *
     * @param string $title Title text
     * @param string $focus_keyword Focus keyword
     * @return array Title analysis
     */
    private function analyze_title_effectiveness(string $title, string $focus_keyword = ''): array
    {
        $score = 0;
        $feedback = [];

        // Length check
        $length = strlen($title);
        if ($length >= 30 && $length <= 60) {
            $score += 25;
        } elseif ($length < 30) {
            $feedback[] = 'Title is too short. Aim for 30-60 characters.';
        } else {
            $feedback[] = 'Title is too long and will be truncated in search results.';
        }

        // Keyword presence
        if (!empty($focus_keyword) && stripos($title, $focus_keyword) !== false) {
            $score += 25;
        } elseif (!empty($focus_keyword)) {
            $feedback[] = 'Consider including your focus keyword in the title.';
        }

        // Unique words count
        $words = str_word_count($title);
        if ($words >= 4 && $words <= 12) {
            $score += 20;
        } else {
            $feedback[] = 'Title should contain 4-12 words for optimal impact.';
        }

        // Power words detection
        if ($this->contains_power_words($title)) {
            $score += 15;
        } else {
            $feedback[] = 'Consider adding compelling words to increase click-through rates.';
        }

        // Clarity and appeal
        if ($this->assess_title_clarity($title)) {
            $score += 15;
        }

        return [
            'score' => $score,
            'feedback' => $feedback,
            'length' => $length,
            'word_count' => $words,
            'has_keyword' => !empty($focus_keyword) && stripos($title, $focus_keyword) !== false,
            'has_power_words' => $this->contains_power_words($title)
        ];
    }

    /**
     * Analyze description effectiveness
     *
     * @param string $description Description text
     * @param string $focus_keyword Focus keyword
     * @return array Description analysis
     */
    private function analyze_description_effectiveness(string $description, string $focus_keyword = ''): array
    {
        $score = 0;
        $feedback = [];

        // Length check
        $length = strlen($description);
        if ($length >= 120 && $length <= 160) {
            $score += 30;
        } elseif ($length < 120) {
            $feedback[] = 'Description is too short. Aim for 120-160 characters.';
        } else {
            $feedback[] = 'Description is too long and will be truncated.';
        }

        // Keyword presence
        if (!empty($focus_keyword) && stripos($description, $focus_keyword) !== false) {
            $score += 25;
        } elseif (!empty($focus_keyword)) {
            $feedback[] = 'Include your focus keyword in the meta description.';
        }

        // Call to action
        if ($this->has_call_to_action($description)) {
            $score += 20;
        } else {
            $feedback[] = 'Consider adding a call-to-action to encourage clicks.';
        }

        // Uniqueness and appeal
        if ($this->assess_description_uniqueness($description)) {
            $score += 15;
        }

        // Readability
        if ($this->assess_description_readability($description)) {
            $score += 10;
        }

        return [
            'score' => $score,
            'feedback' => $feedback,
            'length' => $length,
            'has_keyword' => !empty($focus_keyword) && stripos($description, $focus_keyword) !== false,
            'has_call_to_action' => $this->has_call_to_action($description),
            'readability_score' => $this->calculate_readability_score($description)
        ];
    }

    /**
     * Generate meta optimization recommendations
     *
     * @param array $meta_data Validated meta data
     * @return array Recommendations
     */
    private function generate_meta_recommendations(array $meta_data): array
    {
        $recommendations = [];
        $title = $meta_data['title'] ?? '';
        $description = $meta_data['description'] ?? '';

        // Title recommendations
        if (strlen($title) > 60) {
            $recommendations[] = [
                'type' => 'title',
                'priority' => 'high',
                'message' => 'Shorten your title to under 60 characters to prevent truncation.',
                'action' => 'Edit the title to be more concise while keeping the main keyword.'
            ];
        } elseif (strlen($title) < 30) {
            $recommendations[] = [
                'type' => 'title',
                'priority' => 'medium',
                'message' => 'Your title could be longer to provide more context.',
                'action' => 'Expand the title with descriptive words or benefits.'
            ];
        }

        // Description recommendations
        if (strlen($description) > 160) {
            $recommendations[] = [
                'type' => 'description',
                'priority' => 'high',
                'message' => 'Shorten your meta description to under 160 characters.',
                'action' => 'Remove less important words while keeping the core message.'
            ];
        } elseif (strlen($description) < 120) {
            $recommendations[] = [
                'type' => 'description',
                'priority' => 'medium',
                'message' => 'Your meta description could be longer and more descriptive.',
                'action' => 'Add more compelling details about your content.'
            ];
        }

        // Keyword recommendations
        $focus_keyword = $meta_data['focus_keyword'] ?? '';
        if (!empty($focus_keyword)) {
            if (stripos($title, $focus_keyword) === false) {
                $recommendations[] = [
                    'type' => 'keyword',
                    'priority' => 'high',
                    'message' => 'Include your focus keyword in the title.',
                    'action' => 'Add "' . $focus_keyword . '" naturally to your title.'
                ];
            }

            if (stripos($description, $focus_keyword) === false) {
                $recommendations[] = [
                    'type' => 'keyword',
                    'priority' => 'high',
                    'message' => 'Include your focus keyword in the meta description.',
                    'action' => 'Add "' . $focus_keyword . '" naturally to your description.'
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Validate and sanitize meta data
     *
     * @param array $meta_data Raw meta data
     * @return array Validated meta data
     */
    private function validate_meta_data(array $meta_data): array
    {
        return [
            'title' => sanitize_text_field($meta_data['title'] ?? ''),
            'description' => sanitize_textarea_field($meta_data['description'] ?? ''),
            'url' => esc_url_raw($meta_data['url'] ?? ''),
            'image' => esc_url_raw($meta_data['image'] ?? ''),
            'focus_keyword' => sanitize_text_field($meta_data['focus_keyword'] ?? '')
        ];
    }

    /**
     * Truncate text to specified length with ellipsis
     *
     * @param string $text Text to truncate
     * @param int $limit Character limit
     * @return string Truncated text
     */
    private function truncate_text(string $text, int $limit): string
    {
        if (strlen($text) <= $limit) {
            return $text;
        }

        return substr($text, 0, $limit - 3) . '...';
    }

    /**
     * Truncate URL for display
     *
     * @param string $url URL to truncate
     * @param int $limit Character limit
     * @return string Truncated URL
     */
    private function truncate_url(string $url, int $limit): string
    {
        $display_url = $this->format_display_url($url);
        
        if (strlen($display_url) <= $limit) {
            return $display_url;
        }

        return substr($display_url, 0, $limit - 3) . '...';
    }

    /**
     * Format URL for display (remove protocol, etc.)
     *
     * @param string $url Full URL
     * @return string Formatted display URL
     */
    private function format_display_url(string $url): string
    {
        $parsed = parse_url($url);
        $display = '';

        if (isset($parsed['host'])) {
            $display = $parsed['host'];
            
            // Remove www
            $display = preg_replace('/^www\./', '', $display);
            
            // Add path if it exists and is not just /
            if (isset($parsed['path']) && $parsed['path'] !== '/') {
                $display .= $parsed['path'];
            }
        }

        return $display;
    }

    /**
     * Generate HTML for Google search preview
     *
     * @param string $title Truncated title
     * @param string $description Truncated description
     * @param string $url Truncated URL
     * @return string HTML preview
     */
    private function generate_google_search_html(string $title, string $description, string $url): string
    {
        return '<div class="google-search-preview">
            <div class="search-url">' . esc_html($url) . '</div>
            <div class="search-title">' . esc_html($title) . '</div>
            <div class="search-description">' . esc_html($description) . '</div>
        </div>';
    }

    /**
     * Generate HTML for Facebook preview
     *
     * @param string $title Title
     * @param string $description Description
     * @param string $url URL
     * @param string $image Image URL
     * @return string HTML preview
     */
    private function generate_facebook_preview_html(string $title, string $description, string $url, string $image): string
    {
        $image_html = !empty($image) ? '<div class="fb-image"><img src="' . esc_url($image) . '" alt="Preview"></div>' : '';
        
        return '<div class="facebook-preview">
            ' . $image_html . '
            <div class="fb-content">
                <div class="fb-url">' . esc_html($this->format_display_url($url)) . '</div>
                <div class="fb-title">' . esc_html($title) . '</div>
                <div class="fb-description">' . esc_html($description) . '</div>
            </div>
        </div>';
    }

    /**
     * Generate HTML for Twitter preview
     *
     * @param string $title Title
     * @param string $description Description
     * @param string $url URL
     * @param string $image Image URL
     * @return string HTML preview
     */
    private function generate_twitter_preview_html(string $title, string $description, string $url, string $image): string
    {
        $image_html = !empty($image) ? '<div class="twitter-image"><img src="' . esc_url($image) . '" alt="Preview"></div>' : '';
        
        return '<div class="twitter-preview">
            ' . $image_html . '
            <div class="twitter-content">
                <div class="twitter-title">' . esc_html($title) . '</div>
                <div class="twitter-description">' . esc_html($description) . '</div>
                <div class="twitter-url">' . esc_html($this->format_display_url($url)) . '</div>
            </div>
        </div>';
    }

    /**
     * Generate HTML for mobile search preview
     *
     * @param string $title Title
     * @param string $description Description
     * @param string $url URL
     * @return string HTML preview
     */
    private function generate_mobile_search_html(string $title, string $description, string $url): string
    {
        return '<div class="mobile-search-preview">
            <div class="mobile-url">' . esc_html($url) . '</div>
            <div class="mobile-title">' . esc_html($title) . '</div>
            <div class="mobile-description">' . esc_html($description) . '</div>
        </div>';
    }

    /**
     * Helper methods for analysis
     */
    
    private function contains_power_words(string $text): bool
    {
        $power_words = ['ultimate', 'essential', 'complete', 'proven', 'amazing', 'incredible', 'best', 'top', 'guide', 'tips', 'secrets', 'exclusive'];
        
        foreach ($power_words as $word) {
            if (stripos($text, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function assess_title_clarity(string $title): bool
    {
        // Simple clarity check - no excessive punctuation, reasonable length, etc.
        $punctuation_count = preg_match_all('/[!?.,;:]/', $title);
        $word_count = str_word_count($title);
        
        return $punctuation_count <= 2 && $word_count >= 3;
    }

    private function has_call_to_action(string $text): bool
    {
        $cta_patterns = ['learn', 'discover', 'find out', 'read more', 'get', 'download', 'buy', 'shop', 'try', 'start'];
        
        foreach ($cta_patterns as $pattern) {
            if (stripos($text, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function assess_description_uniqueness(string $description): bool
    {
        // Check if description is not generic
        $generic_phrases = ['this page', 'this article', 'click here', 'read more'];
        
        foreach ($generic_phrases as $phrase) {
            if (stripos($description, $phrase) !== false) {
                return false;
            }
        }
        
        return true;
    }

    private function assess_description_readability(string $description): bool
    {
        $sentences = preg_split('/[.!?]+/', $description);
        $avg_words_per_sentence = str_word_count($description) / max(1, count(array_filter($sentences)));
        
        return $avg_words_per_sentence <= 20; // Good readability
    }

    private function calculate_readability_score(string $text): int
    {
        // Simplified readability calculation
        $words = str_word_count($text);
        $sentences = max(1, preg_match_all('/[.!?]+/', $text));
        $avg_words = $words / $sentences;
        
        if ($avg_words <= 15) return 90;
        if ($avg_words <= 20) return 75;
        if ($avg_words <= 25) return 60;
        return 45;
    }

    private function calculate_click_through_potential(int $score): string
    {
        if ($score >= 80) return 'high';
        if ($score >= 60) return 'medium';
        if ($score >= 40) return 'low';
        return 'very_low';
    }

    private function check_google_search_warnings(array $meta_data): array
    {
        $warnings = [];
        
        if (strlen($meta_data['title'] ?? '') > 60) {
            $warnings[] = 'Title will be truncated in search results';
        }
        
        if (strlen($meta_data['description'] ?? '') > 160) {
            $warnings[] = 'Description will be truncated in search results';
        }
        
        return $warnings;
    }

    private function check_facebook_warnings(array $meta_data): array
    {
        $warnings = [];
        
        if (empty($meta_data['image'])) {
            $warnings[] = 'No image specified for social sharing';
        }
        
        return $warnings;
    }

    private function check_twitter_warnings(array $meta_data): array
    {
        return $this->check_facebook_warnings($meta_data);
    }

    private function check_mobile_warnings(array $meta_data): array
    {
        $warnings = [];
        
        if (strlen($meta_data['title'] ?? '') > 50) {
            $warnings[] = 'Title may be too long for mobile devices';
        }
        
        return $warnings;
    }

    private function get_preview_templates(): array
    {
        // Define preview templates if needed
        return [];
    }
}
