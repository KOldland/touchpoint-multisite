<?php

namespace KH\Editorial\Services\AI;

use KH\Editorial\Core\LLMService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SEOAgent Service
 * 
 * Orchestrates SEO analysis by combining deterministic engine results
 * with LLM strategic insights.
 * 
 * @package KH\Editorial\Services\AI
 */
class SEOAgent {

    /**
     * Content limit for LLM prompt context window.
     * 
     * @var int
     */
    const CONTENT_LIMIT = 1200;

    /**
     * @var \KHM_SEO\Analysis\AnalysisEngine|null
     */
    private ?\KHM_SEO\Analysis\AnalysisEngine $analysis_engine = null;

    /**
     * Constructor
     */
    public function __construct() {
        if ( class_exists( '\KHM_SEO\Analysis\AnalysisEngine' ) ) {
            $this->analysis_engine = new \KHM_SEO\Analysis\AnalysisEngine();
        }
    }

    /**
     * Perform a complete SEO Audit.
     * 
     * @param int $post_id Post ID to audit.
     * @param string $keyword Optional focus keyword override.
     * @return array Audit results.
     */
    public function audit( int $post_id, string $keyword = '' ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'error' => 'Post not found' ];
        }

        // 1. Get Deterministic Analysis
        $analysis = $this->run_deterministic_analysis( $post, $keyword );
        if ( isset( $analysis['error'] ) ) {
            return $analysis;
        }

        // 2. Persist the latest score
        $this->persist_seo_score( $post_id, $analysis );

        // 3. Prepare fallback (Deterministic Suggestions)
        $fallback_payload = $this->get_deterministic_payload( $post, $analysis, $keyword );

        // 4. Check LLM Availability
        if ( ! LLMService::is_configured() ) {
            return [
                'status' => 'fallback',
                'analysis' => $analysis,
                'llm_output' => $fallback_payload,
                'error' => [
                    'code' => 'llm_not_configured',
                    'message' => 'LLM Service is not configured. Returning deterministic results.'
                ]
            ];
        }

        return [
            'status' => 'ready',
            'analysis' => $analysis,
            'deterministic_payload' => $fallback_payload,
            'prompt' => $this->build_llm_prompt( $post, $analysis, $keyword )
        ];
    }

    /**
     * Process an LLM response and merge it with deterministic context.
     * 
     * @param string $raw_response Raw string from LLM.
     * @param array $context Context including post_id and deterministic_payload.
     * @return array Unified payload.
     */
    public function process_response( string $raw_response, array $context ): array {
        $deterministic = $context['deterministic_payload'] ?? [];
        
        $payload = $this->parse_and_validate( $raw_response );
        
        if ( is_wp_error( $payload ) ) {
            return array_merge( $deterministic, [
                'status' => 'fallback',
                'error' => [
                    'code' => $payload->get_error_code(),
                    'message' => $payload->get_error_message()
                ]
            ]);
        }

        // Augmentation Logic: Ensure fallback data fills any gaps in LLM output
        return $this->augment_payload( $payload, $deterministic );
    }

    /**
     * Strip Markdown and validate LLM JSON schema.
     * 
     * @return array|\WP_Error
     */
    private function parse_and_validate( string $response ) {
        $clean = preg_replace('/^```(?:json)?[\r\n]+|```[\r\n]*$/', '', trim($response));
        $decoded = json_decode( $clean, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'invalid_json', 'LLM returned invalid JSON: ' . json_last_error_msg() );
        }

        // Validate top-level keys
        $required = [ 'summary', 'issues', 'suggestions', 'apply_actions', 'upstream_signals' ];
        foreach ( $required as $key ) {
            if ( ! isset( $decoded[$key] ) || ! is_array( $decoded[$key] ) ) {
                return new \WP_Error( 'missing_schema_key', "LLM output missing or invalid key: {$key}" );
            }
        }

        // Validate summary fields
        $summary_fields = [ 'issues_total', 'issues_high', 'suggestions_total', 'score' ];
        foreach ( $summary_fields as $field ) {
            if ( ! isset( $decoded['summary'][$field] ) ) {
                return new \WP_Error( 'missing_summary_field', "LLM output missing summary field: {$field}" );
            }
        }

        return $decoded;
    }

    /**
     * Merges LLM insights with deterministic results and ensures data truth.
     */
    private function augment_payload( array $llm, array $deterministic ): array {
        $unified = $llm;

        // Sanitize and Normalize LLM-provided items (Issues and Suggestions)
        $unified['issues'] = $this->sanitize_items( $unified['issues'], 'Issue' );
        $unified['suggestions'] = $this->sanitize_items( $unified['suggestions'], 'Suggestion' );

        // If LLM failed to provide actions, use deterministic ones
        if ( empty( $unified['apply_actions'] ) && ! empty( $deterministic['apply_actions'] ) ) {
            $unified['apply_actions'] = $deterministic['apply_actions'];
        }

        // Merge and sanitize upstream signals for audit trail
        $llm_signals = $this->sanitize_signals( is_array( $llm['upstream_signals'] ) ? $llm['upstream_signals'] : [] );
        $deterministic_signals = $this->sanitize_signals( is_array( $deterministic['upstream_signals'] ) ? $deterministic['upstream_signals'] : [] );
        
        $unified['upstream_signals'] = array_merge( $llm_signals, $deterministic_signals );

        // Truth Verification: Recalculate summary counts using max() to prevent hallucinations
        $unified['summary']['issues_total'] = max( 
            (int) ($unified['summary']['issues_total'] ?? 0), 
            count( $unified['issues'] ) 
        );
        $unified['summary']['suggestions_total'] = max( 
            (int) ($unified['summary']['suggestions_total'] ?? 0), 
            count( $unified['suggestions'] ),
            count( $unified['apply_actions'] )
        );
        $unified['summary']['issues_high'] = max(
            (int) ($unified['summary']['issues_high'] ?? 0),
            $this->count_high_priority_items( $unified['issues'] )
        );

        // Ensure scores are synced
        if ( ! isset( $unified['summary']['score'] ) ) {
            $unified['summary']['score'] = $deterministic['summary']['score'] ?? 0;
        }

        return $unified;
    }

    /**
     * Sanitize upstream signal items.
     */
    private function sanitize_signals( array $signals ): array {
        return array_map( function( $signal ) {
            if ( ! is_array( $signal ) ) return [];
            return [
                'source' => sanitize_text_field( $signal['source'] ?? 'unknown' ),
                'overall_score' => (int) ($signal['overall_score'] ?? 0),
                'focus_keyword' => sanitize_text_field( $signal['focus_keyword'] ?? '' ),
            ];
        }, array_filter( $signals, 'is_array' ) );
    }

    /**
     * Sanitize, Normalize and strictly type arrays of issues or suggestions.
     */
    private function sanitize_items( array $items, string $fallback_title ): array {
        $sanitized = [];
        foreach ( $items as $item ) {
            if ( is_string( $item ) ) {
                $sanitized[] = [
                    'title' => sanitize_text_field( $fallback_title ),
                    'message' => sanitize_text_field( $item ),
                    'priority' => 'medium',
                ];
                continue;
            }

            if ( ! is_array( $item ) ) continue;

            $sanitized[] = [
                'title' => sanitize_text_field( $item['title'] ?? $item['category'] ?? $fallback_title ),
                'message' => sanitize_text_field( $item['message'] ?? $item['action'] ?? $item['suggestion'] ?? '' ),
                'priority' => $this->normalize_priority( $item['priority'] ?? $item['impact'] ?? 'medium' ),
            ];
        }

        return array_filter( $sanitized, function( array $i ) { return '' !== $i['message']; } );
    }

    /**
     * Run the underlying KHM SEO Analysis Engine.
     */
    private function run_deterministic_analysis( \WP_Post $post, string $keyword = '' ): array {
        if ( ! $this->analysis_engine ) {
            return [ 'error' => 'KHM SEO Analysis Engine not available' ];
        }

        $focus_keyword = $keyword ?: get_post_meta( $post->ID, '_khm_seo_focus_keyword', true );

        $data = [
            'post_id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'meta_description' => get_post_meta( $post->ID, '_khm_seo_description', true ),
            'focus_keyword' => sanitize_text_field( $focus_keyword ),
        ];

        return $this->analysis_engine->analyze( $data );
    }

    /**
     * Build a prompt for the LLM based on deterministic analysis.
     */
    public function build_llm_prompt( \WP_Post $post, array $analysis, string $keyword ): string {
        $current_state = [
            'seo_title' => get_post_meta( $post->ID, '_khm_seo_title', true ),
            'meta_description' => get_post_meta( $post->ID, '_khm_seo_description', true ),
            'focus_keyword' => get_post_meta( $post->ID, '_khm_seo_focus_keyword', true ),
            'keywords' => get_post_meta( $post->ID, '_khm_seo_keywords', true ),
            'schema_config' => get_post_meta( $post->ID, '_khm_seo_schema_config', true ),
        ];

        $analysis_summary = [
            'overall_score' => $analysis['overall_score'] ?? null,
            'individual_scores' => $analysis['individual_scores'] ?? [],
            'suggestions' => array_slice( $analysis['suggestions'] ?? [], 0, 8 ),
            'technical_issues' => array_slice( $analysis['technical_issues'] ?? [], 0, 8 ),
        ];

        $settings = \KH\Editorial\Core\LLMService::get_settings();
        $sponsor_safe = ! empty( $settings['sponsor_safe'] ) ? 'true' : 'false';

        $prompt = [
            'You are the KHM SEO Agent. Return JSON only that matches the required schema.',
            'sponsor_safe=' . $sponsor_safe,
            'no_hallucination=true',
            'Use only these supported action types: set_meta_title, set_meta_description, set_focus_keyword, set_keywords, set_schema_config.',
            'set_schema_config payload must be {"value":{"enabled":true,"type":"article|organization|person|product|breadcrumb","custom_fields":{},"options":{}}}.',
            'All other actions must look like {"action_type":"set_meta_title","payload":{"value":"..."}}.',
            'Return 2 to 5 apply_actions whenever title, description, focus keyword, keywords, or schema config can be improved.',
            '',
            'Post Title: ' . $post->post_title,
            'Focus Keyword: ' . $keyword,
            'Current SEO State JSON:',
            wp_json_encode( $current_state, JSON_UNESCAPED_SLASHES ),
            '',
            'SEO Analysis Summary JSON:',
            wp_json_encode( $analysis_summary, JSON_UNESCAPED_SLASHES ),
            '',
            'Post Content (truncated):',
            mb_substr( wp_strip_all_tags( $post->post_content ), 0, self::CONTENT_LIMIT ),
            '',
            'Output schema:',
            '{"summary":{"issues_total":0,"issues_high":0,"suggestions_total":0,"score":0},"issues":[],"suggestions":[],"apply_actions":[],"upstream_signals":[]}'
        ];

        return implode( "\n", $prompt );
    }

    /**
     * Build the deterministic payload for fallbacks.
     */
    public function get_deterministic_payload( \WP_Post $post, array $analysis, string $keyword ): array {
        $issues = $this->map_analysis_items( $analysis['technical_issues'] ?? [], 'issue' );
        $suggestions = $this->map_analysis_items( $analysis['suggestions'] ?? [], 'suggestion' );
        $actions = $this->synthesize_apply_actions( $post, $analysis, $keyword );
        
        return [
            'summary' => [
                'issues_total' => count( $issues ),
                'issues_high' => $this->count_high_priority_items( $issues ),
                'suggestions_total' => max( count( $suggestions ), count( $actions ) ),
                'score' => intval( $analysis['overall_score'] ?? 0 ),
            ],
            'issues' => $issues,
            'suggestions' => $suggestions,
            'apply_actions' => $actions,
            'upstream_signals' => [
                [
                    'source' => 'khm_seo_analysis_engine',
                    'overall_score' => intval( $analysis['overall_score'] ?? 0 ),
                    'focus_keyword' => $this->resolve_focus_keyword( $post, $keyword ),
                ],
            ],
        ];
    }

    /**
     * Synthesize deterministic apply actions based on analysis.
     */
    private function synthesize_apply_actions( \WP_Post $post, array $analysis, string $keyword ): array {
        $actions = [];
        $resolved_keyword = $this->resolve_focus_keyword( $post, $keyword );
        $current_title = trim( (string) get_post_meta( $post->ID, '_khm_seo_title', true ) );
        $current_description = trim( (string) get_post_meta( $post->ID, '_khm_seo_description', true ) );
        $current_focus_keyword = trim( (string) get_post_meta( $post->ID, '_khm_seo_focus_keyword', true ) );
        $current_keywords = trim( (string) get_post_meta( $post->ID, '_khm_seo_keywords', true ) );
        $current_schema = get_post_meta( $post->ID, '_khm_seo_schema_config', true );

        $recommended_title = $this->build_recommended_meta_title( $post, $resolved_keyword );
        if ( '' !== $recommended_title && $recommended_title !== $current_title ) {
            $actions[] = [
                'action_type' => 'set_meta_title',
                'payload' => [ 'value' => $recommended_title ],
            ];
        }

        $recommended_description = $this->build_recommended_meta_description( $post, $resolved_keyword, $current_description );
        if ( '' !== $recommended_description && $recommended_description !== $current_description ) {
            $actions[] = [
                'action_type' => 'set_meta_description',
                'payload' => [ 'value' => $recommended_description ],
            ];
        }

        if ( '' !== $resolved_keyword && $resolved_keyword !== $current_focus_keyword ) {
            $actions[] = [
                'action_type' => 'set_focus_keyword',
                'payload' => [ 'value' => $resolved_keyword ],
            ];
        }

        $recommended_keywords = $this->build_recommended_keywords( $resolved_keyword, $current_keywords );
        if ( '' !== $recommended_keywords && $recommended_keywords !== $current_keywords ) {
            $actions[] = [
                'action_type' => 'set_keywords',
                'payload' => [ 'value' => $recommended_keywords ],
            ];
        }

        $recommended_schema = $this->build_recommended_schema_config( $post, $current_schema, $recommended_title, $recommended_description );
        if ( $this->schema_configs_differ( $current_schema, $recommended_schema ) ) {
            $actions[] = [
                'action_type' => 'set_schema_config',
                'payload' => [ 'value' => $recommended_schema ],
            ];
        }

        return array_slice( $actions, 0, 5 );
    }

    private function build_recommended_keywords( string $keyword, string $current_keywords ): string {
        $keywords = [];
        if ( '' !== trim( $keyword ) ) $keywords[] = sanitize_text_field( $keyword );

        if ( '' !== trim( (string) $current_keywords ) ) {
            foreach ( preg_split( '/[,;]+/', $current_keywords ) as $existing ) {
                $existing = sanitize_text_field( trim( $existing ) );
                if ( '' !== $existing ) $keywords[] = $existing;
            }
        }

        $keywords = array_values( array_unique( $keywords ) );
        return implode( ', ', array_slice( $keywords, 0, 6 ) );
    }

    private function build_recommended_schema_config( \WP_Post $post, $current_schema, string $headline, string $description ): array {
        $schema = is_array( $current_schema ) ? $current_schema : [];
        $schema['enabled'] = true;
        $schema['type'] = sanitize_key( $schema['type'] ?? $this->get_default_schema_type( $post ) );

        if ( ! isset( $schema['custom_fields'] ) || ! is_array( $schema['custom_fields'] ) ) {
            $schema['custom_fields'] = [];
        }

        if ( in_array( $schema['type'], [ 'article', 'person', 'organization', 'product' ] ) ) {
            if ( '' !== $headline ) {
                $schema['custom_fields']['headline'] = sanitize_text_field( $headline );
            }
            if ( '' !== $description ) {
                $schema['custom_fields']['description'] = sanitize_textarea_field( $description );
            }
        }

        if ( ! isset( $schema['options'] ) || ! is_array( $schema['options'] ) ) {
            $schema['options'] = [];
        }

        $schema['options']['auto_generate'] = '1';
        $schema['options']['validate_output'] = '1';
        
        if ( 'breadcrumb' !== $schema['type'] ) {
            $schema['options']['include_breadcrumbs'] = '1';
        }
        
        return $schema;
    }

    private function schema_configs_differ( $current_schema, array $recommended_schema ): bool {
        $current_schema = is_array( $current_schema ) ? $current_schema : [];
        return wp_json_encode( $current_schema ) !== wp_json_encode( $recommended_schema );
    }

    private function get_default_schema_type( \WP_Post $post ): string {
        if ( 'product' === $post->post_type ) return 'product';
        if ( 'page' === $post->post_type ) {
            $title = strtolower( $post->post_title );
            if ( strpos( $title, 'about' ) !== false ) return 'organization';
        }
        return 'article';
    }

    private function resolve_focus_keyword( \WP_Post $post, string $keyword ): string {
        $keyword = sanitize_text_field( $keyword );
        if ( '' !== $keyword ) return $keyword;

        $stored = sanitize_text_field( get_post_meta( $post->ID, '_khm_seo_focus_keyword', true ) );
        if ( '' !== $stored ) return $stored;

        $title = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $post->post_title ) ) );
        if ( '' === $title ) return '';

        $title_words = preg_split( '/\s+/', $title );
        $title_words = array_filter( $title_words, function( string $word ) { return mb_strlen( $word ) > 2; } );

        return sanitize_text_field( implode( ' ', array_slice( $title_words, 0, 4 ) ) );
    }

    private function build_recommended_meta_title( \WP_Post $post, string $keyword ): string {
        $base_title = trim( (string) get_post_meta( $post->ID, '_khm_seo_title', true ) );
        if ( '' === $base_title ) $base_title = trim( wp_strip_all_tags( $post->post_title ) );

        $keyword = trim( $keyword );
        $candidate = $base_title;
        if ( '' !== $keyword && stripos( $candidate, $keyword ) === false ) {
            $candidate = $keyword . ' | ' . $base_title;
        }

        return $this->trim_to_length( $candidate, 60 );
    }

    private function build_recommended_meta_description( \WP_Post $post, string $keyword, string $current_description ): string {
        $description = trim( (string) $current_description );
        if ( '' === $description ) $description = trim( wp_strip_all_tags( $post->post_excerpt ) );
        if ( '' === $description ) $description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $post->post_content ) ) );
        if ( '' === $description ) $description = trim( wp_strip_all_tags( $post->post_title ) );

        $description = $this->trim_to_length( $description, 155 );
        if ( '' !== $keyword && stripos( $description, $keyword ) === false ) {
            $description = $this->trim_to_length( $keyword . ': ' . $description, 155 );
        }

        return $description;
    }

    private function trim_to_length( string $text, int $max_length ): string {
        $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
        if ( mb_strlen( $text ) <= $max_length ) return $text;

        $trimmed = mb_substr( $text, 0, $max_length - 1 );
        $last_space = mb_strrpos( $trimmed, ' ' );
        if ( false !== $last_space ) $trimmed = mb_substr( $trimmed, 0, $last_space );

        return rtrim( $trimmed, " ,.|-" );
    }

    /**
     * Map analysis items to unified format.
     */
    private function map_analysis_items( array $items, string $fallback_title ): array {
        $mapped = [];
        foreach ( array_slice( $items, 0, 6 ) as $item ) {
            if ( is_string( $item ) ) {
                $mapped[] = [
                    'title' => ucfirst( $fallback_title ),
                    'message' => sanitize_text_field( $item ),
                    'priority' => 'medium',
                ];
                continue;
            }

            if ( ! is_array( $item ) ) continue;

            $message = $item['message'] ?? $item['action'] ?? $item['suggestion'] ?? '';
            if ( '' === $message ) continue;

            $mapped[] = [
                'title' => sanitize_text_field( $item['category'] ?? ucfirst( $fallback_title ) ),
                'message' => sanitize_text_field( $message ),
                'priority' => $this->normalize_priority( $item['priority'] ?? $item['impact'] ?? 'medium' ),
            ];
        }
        return $mapped;
    }

    private function normalize_priority( string $priority ): string {
        $priority = strtolower( (string) $priority );
        if ( in_array( $priority, [ 'critical', 'high' ] ) ) return 'high';
        if ( in_array( $priority, [ 'low', 'minor' ] ) ) return 'low';
        return 'medium';
    }

    private function count_high_priority_items( array $items ): int {
        return count( array_filter( $items, function( array $i ) { return ($i['priority'] ?? '') === 'high'; } ) );
    }

    private function persist_seo_score( int $post_id, array $analysis ): void {
        $score = max( 0, min( 100, (int) ( $analysis['overall_score'] ?? 0 ) ) );
        update_post_meta( $post_id, '_khm_seo_score', $score );
    }
}
