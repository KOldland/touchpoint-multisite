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

        // Generate LinkedIn variants
        $linkedin_result = $this->generate_linkedin_variants( $input );

        // Generate Google Ads draft if enabled
        $google_ad_draft = array();
        $generate_google_ads = $input['generate_google_ads'] ?? true;

        if ( $generate_google_ads ) {
            $google_ad_draft = $this->generate_google_ad_draft( $input );
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
                    $variants = $this->parse_response( $response );
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
        $body = $response['choices'][0]['message']['content'] ?? '';
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return array();
        }

        if ( isset( $decoded['variants'] ) && is_array( $decoded['variants'] ) ) {
            return $decoded['variants'];
        }

        if ( isset( $decoded[0] ) ) {
            return $decoded;
        }

        return array();
    }

    private function build_fallback_variants( array $input ): array {
        $post_id = (int) ( $input['post_id'] ?? 0 );
        $phase   = $input['phase_tag'] ?? 'Attention';
        $tone    = $input['tone'] ?? ( $input['user_controls']['tone'] ?? 'Authority' );
        $num     = (int) ( $input['num_variants'] ?? ( $input['user_controls']['num_variants'] ?? 1 ) );
        $num     = max( 1, min( 5, $num ) );
        $geo_targets = $input['geo_targets'] ?? array();

        $variants = array();
        for ( $i = 0; $i < $num; $i++ ) {
            $variant_id = 'v-fallback-' . wp_generate_uuid4();
            $variants[] = array(
                'variant_id' => $variant_id,
                'channel' => 'linkedin',
                'text' => sprintf( 'New insight from post %d. %s phase: explore the key takeaways and share your perspective.', $post_id, $phase ),
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
                'compliance_notes' => 'OK: fallback variant generated',
                'approval_required' => false,
                'explainability' => 'Matches requested phase with a soft CTA and professional tone.',
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
}
