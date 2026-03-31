<?php
namespace KH_SMMA\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SmmaGenerator {
    private $blacklist = array(
        'guaranteed results',
        'guarantee results',
        'risk-free',
        '100% guaranteed',
        'no risk',
    );

    /** @var SchemaValidator */
    private $schema_validator;

    public function __construct( SchemaValidator $schema_validator = null ) {
        $this->schema_validator = $schema_validator ?? new SchemaValidator();
    }

    public function generate( array $input ) {
        $input = $this->hydrate_input( $input );
        $input = $this->hydrate_phase_context( $input );
        $standard_mode = ! array_key_exists( 'standard_mode', $input ) || ! empty( $input['standard_mode'] );

        if ( $standard_mode ) {
            $linkedin_variant = $this->build_standard_linkedin_variant( $input );
            $google_ad_draft = $this->build_standard_google_ad_draft( $input );

            $response = array(
                'variants'          => array( $linkedin_variant ),
                'linkedin_variants' => array( $linkedin_variant ),
                'google_ad_draft'   => $google_ad_draft,
                'model'             => 'standard-template-v1',
            );

            $validation = $this->schema_validator->validate_generation_response( $response );
            if ( is_wp_error( $validation ) ) {
                $error_message = method_exists( $validation, 'get_error_message' ) ? $validation->get_error_message() : 'Schema validation failed';
                if ( function_exists( 'error_log' ) ) {
                    error_log( 'SMMA Standard Schema Validation Error: ' . $error_message );
                }
                $response['schema_validation_error'] = $error_message;
            }

            return $response;
        }

        // Generate LinkedIn variants
        $linkedin_result = $this->generate_linkedin_variants( $input );
        if ( ! empty( $linkedin_result['error'] ) && is_wp_error( $linkedin_result['error'] ) ) {
            return $linkedin_result['error'];
        }

        // Generate Google Ads draft if enabled
        $google_ad_draft = array();
        $generate_google_ads = $input['generate_google_ads'] ?? true;

        if ( $generate_google_ads ) {
            $google_ad_draft = $this->generate_google_ad_draft( $input );
        }
        if ( empty( $google_ad_draft ) && ! empty( $linkedin_result['google_ad_draft'] ) ) {
            $google_ad_draft = $linkedin_result['google_ad_draft'];
        }

        $response = array(
            'variants'          => $linkedin_result['variants'],
            'linkedin_variants' => $linkedin_result['variants'],
            'google_ad_draft'   => $google_ad_draft,
            'model'             => $linkedin_result['model'],
        );

        // Validate response schema
        $validation = $this->schema_validator->validate_generation_response( $response );
        if ( is_wp_error( $validation ) ) {
            // Log validation error but don't fail - return response with error metadata
            $error_message = method_exists( $validation, 'get_error_message' ) ? $validation->get_error_message() : 'Schema validation failed';
            if ( function_exists( 'error_log' ) ) {
                error_log( 'SMMA Schema Validation Error: ' . $error_message );
            }
            $response['schema_validation_error'] = $error_message;
        }

        return $response;
    }

    /**
     * Generate LinkedIn promotional variants
     *
     * @param array $input Input data.
     * @return array
     */
    private function generate_linkedin_variants( array $input ): array {
        $model = 'fallback';
        $variants = array();
        $parsed_payload = null;
        $strict_mode = ! empty( $input['strict_llm_json'] );
        $llm_available = class_exists( '\\Dual_GPT\\Dual_GPT_LLM_Client' );

        if ( $llm_available ) {
            $client = new \Dual_GPT\Dual_GPT_LLM_Client();
            if ( $client->has_api_key() ) {
                $system = $this->build_system_prompt();
                $user   = $this->build_user_prompt( $input );
                $response = $client->call( $system, $user, array(
                    'json_mode' => true,
                    'temperature' => 0.4,
                    'max_tokens' => 2000,
                ) );

                if ( ! is_wp_error( $response ) ) {
                    $model = $client->get_model_name();
                    $parsed_payload = $this->parse_payload( $response );
                    if ( isset( $parsed_payload['linkedin_variants'] ) && is_array( $parsed_payload['linkedin_variants'] ) ) {
                        $variants = $parsed_payload['linkedin_variants'];
                    } else {
                        $variants = $this->parse_response( $response );
                    }

                    if ( $strict_mode && empty( $variants ) && function_exists( 'error_log' ) ) {
                        error_log( 'SMMA strict JSON parse failed; using fallback variants.' );
                    }
                }
            }
        }

        if ( empty( $variants ) ) {
            $variants = $this->build_fallback_variants( $input );
        }

        $variants = $this->limit_variant_count( $variants, $input );
        $variants = $this->apply_compliance( $variants, $input, $model );

        return array(
            'variants' => $variants,
            'model'    => $model,
            'google_ad_draft' => is_array( $parsed_payload['google_ad_draft'] ?? null ) ? $parsed_payload['google_ad_draft'] : array(),
            'error' => null,
        );
    }

    /**
     * Generate Google Ads draft
     *
     * @param array $input Input data.
     * @return array
     */
    private function generate_google_ad_draft( array $input ): array {
        // Backward-compatible contract: this draft is filter-driven and empty by default.
        $draft = apply_filters( 'kh_smma_google_ad_draft', array(), $input );
        return is_array( $draft ) ? $draft : array();
    }

    private function hydrate_input( array $input ): array {
        $post_id = (int) ( $input['post_id'] ?? 0 );

        if ( empty( $input['blocks_json'] ) && $post_id ) {
            $input['blocks_json'] = $this->get_blocks_json( $post_id );
        }

        if ( empty( $input['keywords'] ) && $post_id ) {
            $seo = $this->get_seo_keywords( $post_id );
            if ( ! empty( $seo['keywords'] ) ) {
                $input['keywords'] = $seo['keywords'];
            }
            if ( ! empty( $seo['intent_scores'] ) ) {
                $input['intent_scores'] = $seo['intent_scores'];
            }
        }

        if ( empty( $input['sponsor_context'] ) && ! empty( $input['geo_targets'] ) && $post_id ) {
            $input['sponsor_context'] = $this->get_sponsor_context_for_geo( $post_id, (array) $input['geo_targets'] );
        }

        return $input;
    }

    private function hydrate_phase_context( array $input ): array {
        if ( empty( $input['phase_tag'] ) && ! empty( $input['phase_context']['assigned_phase'] ) ) {
            $input['phase_tag'] = $input['phase_context']['assigned_phase'];
        }

        if ( empty( $input['phase_tag'] ) ) {
            $input['phase_tag'] = 'Attention';
        }

        return $input;
    }

    private function get_blocks_json( int $post_id ) {
        $blocks = get_post_meta( $post_id, '_editorial_blocks_json', true );
        if ( ! empty( $blocks ) ) {
            return $blocks;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return array();
        }

        return parse_blocks( $post->post_content );
    }

    private function get_seo_keywords( int $post_id ): array {
        $endpoint = rest_url( 'khm-seo-agent/v1/keywords?post_id=' . $post_id );
        $headers  = array();
        if ( is_user_logged_in() ) {
            $headers['X-WP-Nonce'] = wp_create_nonce( 'wp_rest' );
        }
        $response = wp_remote_get( $endpoint, array( 'headers' => $headers, 'timeout' => 10 ) );
        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $decoded = json_decode( $body, true );
            if ( is_array( $decoded ) && ( ! empty( $decoded['keywords'] ) || ! empty( $decoded['intent_scores'] ) ) ) {
                return array(
                    'keywords' => $decoded['keywords'] ?? array(),
                    'intent_scores' => $decoded['intent_scores'] ?? array(),
                );
            }
        }

        $focus = get_post_meta( $post_id, '_khm_seo_focus_keyword', true );
        $keywords = get_post_meta( $post_id, '_khm_seo_keywords', true );

        $list = array();
        if ( is_string( $keywords ) && '' !== trim( $keywords ) ) {
            $list = array_filter( array_map( 'trim', explode( ',', $keywords ) ) );
        } elseif ( is_array( $keywords ) ) {
            $list = array_filter( $keywords );
        }

        if ( $focus && ! in_array( $focus, $list, true ) ) {
            array_unshift( $list, $focus );
        }

        return array(
            'keywords' => $list,
            'intent_scores' => array(),
        );
    }

    private function get_sponsor_context_for_geo( int $post_id, array $geo_targets ): array {
        if ( ! function_exists( 'khm_seo' ) || ! khm_seo()->get_geo_manager() ) {
            return array();
        }

        $geo_manager = khm_seo()->get_geo_manager();
        if ( ! method_exists( $geo_manager, 'getSponsorPolicyForPost' ) ) {
            return array();
        }

        $policy = null;
        foreach ( $geo_targets as $geo ) {
            $policy = $geo_manager->getSponsorPolicyForPost( $post_id, sanitize_text_field( $geo ) );
            if ( is_array( $policy ) && ! empty( $policy['sponsor_id'] ) ) {
                break;
            }
        }

        if ( ! is_array( $policy ) || empty( $policy['sponsor_id'] ) ) {
            return array();
        }

        $context = array(
            'sponsor_id' => (int) $policy['sponsor_id'],
            'policy' => $policy['policy'] ?? 'co-brand',
        );

        if ( function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            $sponsor_meta = kh_ad_manager_get_sponsor_meta( (int) $policy['sponsor_id'] );
            if ( is_array( $sponsor_meta ) ) {
                $context['allowed_claims'] = $sponsor_meta['allowed_claims'] ?? array();
                $context['sponsor_assets'] = $sponsor_meta['sponsor_assets'] ?? array();
                $context['approval_required'] = ! empty( $sponsor_meta['approval_contact'] );
            }
        }

        return $context;
    }

    private function build_system_prompt(): string {
        return 'You are SMMA-AI v1. Produce LinkedIn-native promotional variants optimized for the given 4A phase and geo. Enforce sponsor rules and return JSON in the exact schema.';
    }

    private function build_user_prompt( array $input ): string {
        return wp_json_encode( array(
            'post_id' => $input['post_id'] ?? 0,
            'blocks_json' => $input['blocks_json'] ?? array(),
            'blocks_summary' => $input['blocks_summary'] ?? '',
            'phase_tag' => $input['phase_tag'] ?? 'Attention',
            'phase_context' => $input['phase_context'] ?? array(),
            'keywords' => $input['keywords'] ?? array(),
            'intent_scores' => $input['intent_scores'] ?? array(),
            'geo_targets' => $input['geo_targets'] ?? array(),
            'sponsor_context' => $input['sponsor_context'] ?? array(),
            'user_controls' => $input['user_controls'] ?? array(),
            'audience_presets' => $input['audience_presets'] ?? array(),
        ) );
    }

    private function parse_response( array $response ): array {
        $decoded = $this->parse_payload( $response );
        if ( ! is_array( $decoded ) ) {
            return array();
        }

        if ( isset( $decoded['variants'] ) && is_array( $decoded['variants'] ) ) {
            return $decoded['variants'];
        }

        if ( isset( $decoded['linkedin_variants'] ) && is_array( $decoded['linkedin_variants'] ) ) {
            return $decoded['linkedin_variants'];
        }

        if ( isset( $decoded[0] ) ) {
            return $decoded;
        }

        return array();
    }

    private function parse_payload( array $response ) {
        $body = (string) ( $response['choices'][0]['message']['content'] ?? '' );
        if ( '' === $body ) {
            return null;
        }

        $decoded = json_decode( $body, true );
        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        $candidate = $this->extract_json_candidate( $body );
        if ( '' === $candidate ) {
            return null;
        }

        $decoded = json_decode( $candidate, true );
        return is_array( $decoded ) ? $decoded : null;
    }

    private function extract_json_candidate( string $body ): string {
        $trimmed = trim( $body );
        if ( '' === $trimmed ) {
            return '';
        }

        // Common model behavior: wrap JSON in markdown code fences.
        if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*\}|\[[\s\S]*\])\s*```/i', $trimmed, $matches ) ) {
            return trim( (string) $matches[1] );
        }

        // Fallback: extract the widest object/array block from mixed text.
        $first_obj = strpos( $trimmed, '{' );
        $last_obj  = strrpos( $trimmed, '}' );
        if ( false !== $first_obj && false !== $last_obj && $last_obj > $first_obj ) {
            return substr( $trimmed, $first_obj, ( $last_obj - $first_obj ) + 1 );
        }

        $first_arr = strpos( $trimmed, '[' );
        $last_arr  = strrpos( $trimmed, ']' );
        if ( false !== $first_arr && false !== $last_arr && $last_arr > $first_arr ) {
            return substr( $trimmed, $first_arr, ( $last_arr - $first_arr ) + 1 );
        }

        return '';
    }

    private function build_fallback_variants( array $input ): array {
        $post_id = (int) ( $input['post_id'] ?? 0 );
        $phase   = $input['phase_tag'] ?? 'Attention';
        $tone    = $input['tone'] ?? ( $input['user_controls']['tone'] ?? 'Authority' );
        $num     = (int) ( $input['num_variants'] ?? ( $input['user_controls']['num_variants'] ?? 1 ) );
        $num     = max( 1, min( 5, $num ) );
        $geo_targets = $input['geo_targets'] ?? array();
        $title   = $this->resolve_source_title( $input, $post_id );
        $summary = $this->resolve_source_summary( $input );
        $keyword = $this->resolve_primary_keyword( $input );
        $hooks   = array(
            'Most field service teams are still leaving uptime gains on the table.',
            'A practical operations playbook can outperform pure firefighting.',
            'Better maintenance outcomes usually start with better workflow design.',
            'Service leaders are winning by tightening process before adding complexity.',
            'Small process shifts can create outsized reliability improvements.',
        );
        $ctas = array(
            'What change would you pilot first in your workflow?',
            'Where is your team seeing the biggest preventable delay today?',
            'Which KPI would improve fastest if this was implemented this quarter?',
            'Would this approach fit your current service model?',
            'What would be the hardest part of rolling this out in your operation?',
        );

        $variants = array();
        for ( $i = 0; $i < $num; $i++ ) {
            $variant_id = 'v-fallback-' . wp_generate_uuid4();
            $hook = $hooks[ $i % count( $hooks ) ];
            $cta  = $ctas[ $i % count( $ctas ) ];
            $topic_line = '' !== $title ? $title : 'Operational reliability in field service';
            $context_line = '' !== $summary
                ? $summary
                : 'Teams that standardize planning and execution reduce avoidable downtime and improve delivery confidence.';
            if ( '' !== $keyword ) {
                $context_line .= ' Focus area: ' . $keyword . '.';
            }
            $variants[] = array(
                'variant_id' => $variant_id,
                'channel' => 'linkedin',
                'text' => $hook . "\n\n" . $topic_line . "\n\n" . $context_line . "\n\n" . $cta,
                'phase_tag' => $phase,
                'tone' => $tone,
                'recommended_post_time_gmt' => time() + ( $i + 1 ) * 3600,
                'time_window' => '08:30-10:00 GMT',
                'geo_recommendations' => $this->build_geo_recommendations( $geo_targets ),
                'asset_hints' => array(
                    'image_aspect' => '1.91:1',
                    'alt_text' => 'Preview image for promoted article',
                ),
                'sponsor_flag' => ! empty( $input['sponsor_context'] ),
                'sponsor_mode' => $input['sponsor_context']['policy'] ?? '',
                'sponsor_asset' => $input['sponsor_context']['sponsor_assets'][0] ?? array(),
                'compliance_notes' => 'OK: contextual fallback variant generated',
                'approval_required' => false,
                'explainability' => 'Fallback used source title/summary and added a phase-aligned CTA for LinkedIn readability.',
                'audit' => array(
                    'source_post_id' => $post_id,
                    'generated_by' => 'smma-ai-v1',
                    'model_version' => 'fallback',
                    'created_at' => time(),
                ),
            );
        }

        return $variants;
    }

    private function resolve_source_title( array $input, int $post_id ): string {
        $title = trim( (string) ( $input['title'] ?? '' ) );
        if ( '' !== $title ) {
            return $title;
        }
        if ( $post_id > 0 ) {
            $post_title = get_the_title( $post_id );
            if ( is_string( $post_title ) ) {
                return trim( wp_strip_all_tags( $post_title ) );
            }
        }

        return '';
    }

    private function resolve_source_summary( array $input ): string {
        $summary = trim( (string) ( $input['blocks_summary'] ?? '' ) );
        if ( '' === $summary ) {
            return '';
        }

        $lower = strtolower( $summary );
        if ( 'post content summary' === $lower || 'summary unavailable.' === $lower ) {
            return '';
        }

        $summary = preg_replace( '/\s+/', ' ', wp_strip_all_tags( $summary ) );
        $summary = trim( (string) $summary );
        if ( '' === $summary ) {
            return '';
        }

        if ( 0 === stripos( $summary, 'Post content summary' ) ) {
            $summary = trim( substr( $summary, strlen( 'Post content summary' ) ) );
        }
        if ( '' === $summary ) {
            return '';
        }

        if ( strlen( $summary ) > 220 ) {
            $summary = substr( $summary, 0, 217 ) . '...';
        }

        return $summary;
    }

    private function resolve_primary_keyword( array $input ): string {
        $keywords = $input['keywords'] ?? array();
        if ( ! is_array( $keywords ) || empty( $keywords ) ) {
            return '';
        }

        foreach ( $keywords as $keyword ) {
            $value = trim( (string) $keyword );
            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }

    private function build_geo_recommendations( array $geo_targets ): array {
        $recommendations = array();
        foreach ( $geo_targets as $geo ) {
            $recommendations[] = array(
                'geo' => $geo,
                'time_window' => '08:30-10:00 GMT',
                'rationale' => 'Morning engagement window for professionals.',
            );
        }
        return $recommendations;
    }

    private function apply_compliance( array $variants, array $input, string $model ): array {
        $allowed_claims = $input['sponsor_context']['allowed_claims'] ?? array();
        $post_id = (int) ( $input['post_id'] ?? 0 );
        $phase   = $input['phase_tag'] ?? 'Attention';
        $tone    = $input['tone'] ?? ( $input['user_controls']['tone'] ?? 'Authority' );

        foreach ( $variants as $index => $variant ) {
            $text = $variant['text'] ?? '';
            $notes = $this->check_blacklist( $text );
            if ( empty( $notes ) && ! empty( $allowed_claims ) ) {
                $notes = $this->check_allowed_claims( $text, $allowed_claims );
            }

            $compliance_notes = $notes ?: ( $variant['compliance_notes'] ?? 'OK' );

            // Compute approval_required based on compliance level
            // WARN or FAIL compliance requires approval before paid promotion
            $approval_required = false;
            $notes_upper = strtoupper( $compliance_notes );
            if ( strpos( $notes_upper, 'FAIL' ) !== false || strpos( $notes_upper, 'WARN' ) !== false ) {
                $approval_required = true;
            }

            $variants[ $index ] = array_merge( array(
                'variant_id' => $variant['variant_id'] ?? 'v-' . wp_generate_uuid4(),
                'channel' => $variant['channel'] ?? 'linkedin',
                'text' => $text,
                'phase_tag' => $variant['phase_tag'] ?? $phase,
                'tone' => $variant['tone'] ?? $tone,
                'recommended_post_time_gmt' => $variant['recommended_post_time_gmt'] ?? time(),
                'time_window' => $variant['time_window'] ?? '08:30-10:00 GMT',
                'geo_recommendations' => $variant['geo_recommendations'] ?? array(),
                'asset_hints' => $variant['asset_hints'] ?? array(),
                'sponsor_flag' => (bool) ( $variant['sponsor_flag'] ?? ! empty( $input['sponsor_context'] ) ),
                'sponsor_mode' => $variant['sponsor_mode'] ?? ( $input['sponsor_context']['policy'] ?? '' ),
                'sponsor_asset' => $variant['sponsor_asset'] ?? array(),
                'compliance_notes' => $compliance_notes,
                'approval_required' => $approval_required,
                'explainability' => $variant['explainability'] ?? 'Aligned with phase and tone requirements.',
                'audit' => $variant['audit'] ?? array(
                    'source_post_id' => $post_id,
                    'generated_by' => 'smma-ai-v1',
                    'model_version' => $model,
                    'created_at' => time(),
                ),
            ), $variant );

            $variants[ $index ] = $this->enforce_length_limits( $variants[ $index ] );
        }

        return $variants;
    }

    private function enforce_length_limits( array $variant ): array {
        $channel = $variant['channel'] ?? 'linkedin';
        if ( 'linkedin' === $channel ) {
            $variant['text'] = mb_substr( (string) $variant['text'], 0, 3000 );
        }
        if ( 'google_ads' === $channel ) {
            if ( isset( $variant['ad_groups'] ) && is_array( $variant['ad_groups'] ) ) {
                foreach ( $variant['ad_groups'] as $group_index => $group ) {
                    $variant['ad_groups'][ $group_index ]['headlines'] = $this->truncate_array( $group['headlines'] ?? array(), 30 );
                    $variant['ad_groups'][ $group_index ]['descriptions'] = $this->truncate_array( $group['descriptions'] ?? array(), 90 );
                }
            }
        }

        return $variant;
    }

    private function truncate_array( array $items, int $limit ): array {
        $output = array();
        foreach ( $items as $item ) {
            $output[] = mb_substr( (string) $item, 0, $limit );
        }
        return $output;
    }

    private function check_blacklist( string $text ): string {
        foreach ( $this->blacklist as $phrase ) {
            if ( stripos( $text, $phrase ) !== false ) {
                return 'WARN: blocked phrase detected.';
            }
        }
        return '';
    }

    private function check_allowed_claims( string $text, array $allowed_claims ): string {
        $allowed = array_filter( array_map( 'trim', $allowed_claims ) );
        if ( empty( $allowed ) ) {
            return '';
        }

        foreach ( $allowed as $claim ) {
            if ( '' !== $claim && stripos( $text, $claim ) !== false ) {
                return '';
            }
        }

        return 'WARN: no allowed sponsor claims detected.';
    }

    private function limit_variant_count( array $variants, array $input ): array {
        $requested = (int) ( $input['num_variants'] ?? ( $input['user_controls']['num_variants'] ?? 1 ) );
        $requested = max( 1, min( 5, $requested ) );

        return array_slice( $variants, 0, $requested );
    }

    private function build_standard_linkedin_variant( array $input ): array {
        $post_id = (int) ( $input['post_id'] ?? 0 );
        $title = $this->resolve_source_title( $input, $post_id );
        $excerpt = trim( (string) ( $input['excerpt'] ?? '' ) );
        if ( '' === $excerpt ) {
            $excerpt = $this->resolve_source_summary( $input );
        }
        if ( '' === $excerpt ) {
            $excerpt = 'New article now live with practical takeaways.';
        }

        $tags = array();
        $lead = trim( (string) ( $input['lead_category'] ?? '' ) );
        if ( '' !== $lead ) {
            $tags[] = $lead;
        }
        $additional = $input['additional_categories'] ?? array();
        if ( is_array( $additional ) ) {
            foreach ( $additional as $entry ) {
                $value = trim( (string) $entry );
                if ( '' !== $value && ! in_array( $value, $tags, true ) ) {
                    $tags[] = $value;
                }
                if ( count( $tags ) >= 3 ) {
                    break;
                }
            }
        }

        $text_parts = array();
        if ( '' !== $title ) {
            $text_parts[] = $title;
        }
        $text_parts[] = $excerpt;
        if ( ! empty( $tags ) ) {
            $hashtags = array();
            foreach ( $tags as $tag ) {
                $hashtags[] = $this->format_hashtag( $tag );
            }
            $hashtags = array_values( array_filter( $hashtags ) );
            if ( ! empty( $hashtags ) ) {
                $text_parts[] = implode( ' ', $hashtags );
            }
        }

        return array(
            'variant_id' => 'v-standard-' . wp_generate_uuid4(),
            'channel' => 'linkedin',
            'text' => implode( "\n\n", $text_parts ),
            'phase_tag' => $input['phase_tag'] ?? 'Attention',
            'tone' => $input['tone'] ?? 'Authority',
            'recommended_post_time_gmt' => time() + 3600,
            'time_window' => '08:30-10:00 GMT',
            'geo_recommendations' => $this->build_geo_recommendations( (array) ( $input['geo_targets'] ?? array() ) ),
            'asset_hints' => array(
                array(
                    'type' => 'image',
                    'description' => 'Feature image aligned with article theme',
                ),
            ),
            'sponsor_flag' => ! empty( $input['sponsor_context'] ),
            'sponsor_mode' => $input['sponsor_context']['policy'] ?? '',
            'sponsor_asset' => $input['sponsor_context']['sponsor_assets'][0] ?? array(),
            'compliance_notes' => 'OK: standard mode from title/excerpt/tags',
            'approval_required' => false,
            'explainability' => 'Generated from post title, excerpt, and category tags.',
            'audit' => array(
                'source_post_id' => $post_id,
                'generated_by' => 'smma-standard-v1',
                'model_version' => 'standard-template-v1',
                'created_at' => time(),
            ),
        );
    }

    private function build_standard_google_ad_draft( array $input ): array {
        $post_id = (int) ( $input['post_id'] ?? 0 );
        $seo_title = trim( (string) ( $input['seo_title'] ?? '' ) );
        $seo_desc = trim( (string) ( $input['seo_description'] ?? '' ) );
        if ( '' === $seo_title ) {
            $seo_title = $this->resolve_source_title( $input, $post_id );
        }
        if ( '' === $seo_desc ) {
            $seo_desc = trim( (string) ( $input['excerpt'] ?? '' ) );
        }
        if ( '' === $seo_desc ) {
            $seo_desc = $this->resolve_source_summary( $input );
        }

        $lead = trim( (string) ( $input['lead_category'] ?? '' ) );
        if ( '' === $lead ) {
            $lead = $this->resolve_primary_keyword( $input );
        }
        if ( '' === $lead ) {
            $lead = 'Field Service Optimization';
        }

        $final_url = trim( (string) ( $input['canonical_url'] ?? '' ) );
        if ( '' === $final_url ) {
            $final_url = home_url( '/' );
        }

        return array(
            'ad_groups' => array(
                array(
                    'keyword_cluster' => $lead,
                    'headlines' => array(
                        mb_substr( $seo_title ?: 'Field Service Insights', 0, 30 ),
                        mb_substr( $lead . ' Strategies', 0, 30 ),
                        'Read the Full Guide',
                    ),
                    'descriptions' => array(
                        mb_substr( $seo_desc ?: 'Practical strategies to improve service outcomes and operational reliability.', 0, 90 ),
                        mb_substr( 'Get actionable insights and implementation ideas for your team.', 0, 90 ),
                    ),
                    'final_url' => $final_url,
                    'meta_title' => $seo_title,
                    'meta_description' => $seo_desc,
                ),
            ),
        );
    }

    private function format_hashtag( string $label ): string {
        $clean = preg_replace( '/[^a-zA-Z0-9\s]/', ' ', $label );
        $clean = trim( preg_replace( '/\s+/', ' ', (string) $clean ) );
        if ( '' === $clean ) {
            return '';
        }

        $words = explode( ' ', $clean );
        $normalized = array();
        foreach ( $words as $word ) {
            if ( '' === $word ) {
                continue;
            }
            if ( $word === strtolower( $word ) ) {
                $normalized[] = ucfirst( $word );
            } else {
                $normalized[] = $word;
            }
        }

        if ( empty( $normalized ) ) {
            return '';
        }

        return '#' . implode( '', $normalized );
    }
}
