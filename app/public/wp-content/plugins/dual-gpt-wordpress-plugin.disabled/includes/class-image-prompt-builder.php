<?php
/**
 * Prompt builder for article-aware image generation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Image_Prompt_Builder {
    private $settings;

    public function __construct($settings = null) {
        $this->settings = $settings instanceof Dual_GPT_Image_Settings ? $settings : new Dual_GPT_Image_Settings();
    }

    public function build_recommendation($payload) {
        $config = $this->settings->get_config();
        $article = $this->normalize_article_context($payload);
        $preset_key = sanitize_key($payload['preset_key'] ?? $config['default_preset_key'] ?? '');
        $style = $config['presets'][$preset_key] ?? $config['house_style'];
        $style_label = $style['label'] ?? 'configured house style';

        $subject = $article['title'] ?: 'Untitled article';
        $summary = $article['summary'];
        $keywords = $article['keywords'];
        $audience = $article['audience'];

        $prompt_parts = array(
            'Create a publication-quality editorial image for the article "' . $subject . '".',
            'Art direction: ' . $style['art_direction'],
        );

        if ($summary !== '') {
            $prompt_parts[] = 'Article summary: ' . $summary;
        }
        if (!empty($keywords)) {
            $prompt_parts[] = 'Key themes: ' . implode(', ', array_slice($keywords, 0, 8)) . '.';
        }
        if ($audience !== '') {
            $prompt_parts[] = 'Target audience: ' . $audience . '.';
        }
        if ($style['brand_palette'] !== '') {
            $prompt_parts[] = 'Brand palette guidance: ' . $style['brand_palette'] . '.';
        }
        if (!empty($style['prefer_people'])) {
            $prompt_parts[] = 'Prefer a human-centered composition when appropriate.';
        }
        if (!empty($style['prefer_clean_backgrounds'])) {
            $prompt_parts[] = 'Keep backgrounds clean and non-distracting.';
        }
        if (!empty($style['open_instructions'])) {
            $prompt_parts[] = 'Additional house guidance: ' . $style['open_instructions'];
        }
        $prompt_parts[] = 'Balance realism at ' . intval($style['realism']) . '/100 and illustration at ' . intval($style['illustration_strength']) . '/100.';
        $prompt_parts[] = 'Preferred aspect ratio: ' . $style['aspect_ratio'] . '.';

        $prompt = implode(' ', $prompt_parts);
        $alt_text = $this->build_alt_text($article);
        $caption = $this->build_caption($article);

        return array(
            'prompt' => $prompt,
            'negative_prompt' => $style['negative_prompt'],
            'alt_text' => $alt_text,
            'caption' => $caption,
            'rationale' => 'Prompt built from article title, summary, keywords, audience, and the "' . $style_label . '" house style preset.',
            'style_profile' => $style,
            'preset_key' => $preset_key,
            'article_context' => $article,
        );
    }

    private function normalize_article_context($payload) {
        $payload = is_array($payload) ? $payload : array();
        $keywords = $payload['keywords'] ?? array();
        if (!is_array($keywords)) {
            $keywords = array_filter(array_map('trim', explode(',', (string) $keywords)));
        }

        return array(
            'post_id' => intval($payload['post_id'] ?? 0),
            'title' => sanitize_text_field($payload['title'] ?? ''),
            'summary' => sanitize_textarea_field($payload['summary'] ?? ''),
            'audience' => sanitize_text_field($payload['audience'] ?? ''),
            'keywords' => array_values(array_filter(array_map('sanitize_text_field', $keywords))),
        );
    }

    private function build_alt_text($article) {
        if ($article['title'] !== '') {
            return 'Editorial illustration for ' . $article['title'];
        }

        return 'Editorial illustration';
    }

    private function build_caption($article) {
        if ($article['summary'] !== '') {
            return wp_trim_words($article['summary'], 18, '...');
        }

        if ($article['title'] !== '') {
            return $article['title'];
        }

        return '';
    }
}
