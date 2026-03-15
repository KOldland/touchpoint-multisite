<?php
namespace KH_SMMA\Services;

use KH_SMMA\Compliance\ComplianceRulesStore;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ComplianceValidator {
    private ?ComplianceRulesStore $rules_store = null;

    public function __construct( ?ComplianceRulesStore $rules_store = null ) {
        $this->rules_store = $rules_store;
    }

    private $blacklist = array(
        'guaranteed results',
        'guarantee results',
        'risk-free',
        '100% guaranteed',
        'no risk',
        'get rich quick',
        'miracle cure',
        'instant results',
    );

    /**
     * Deterministic first-line compliance check.
     *
     * @param string $text Variant content.
     * @return array{status:string,reasons:array}
     */
    public function deterministic_check( string $text ): array {
        $reasons = array();
        $lower = strtolower( $text );

        foreach ( $this->blacklist as $phrase ) {
            if ( strpos( $lower, strtolower( $phrase ) ) !== false ) {
                $reasons[] = 'Blocked phrase: ' . $phrase;
            }
        }

        if ( ! empty( $reasons ) ) {
            return array(
                'status' => 'FAIL',
                'reasons' => $reasons,
            );
        }

        return array(
            'status' => 'PASS',
            'reasons' => array(),
        );
    }

    /**
     * Validate variant text using both rule-based and AI-powered checks.
     *
     * @param string $text Variant text to validate
     * @param array  $context Validation context including sponsor info, channel, etc.
     * @return array Validation result with passed, message, notes, and confidence_score
     */
    public function validate( string $text, array $context = array() ): array {
        // First pass: Rule-based validation (fast)
        $rule_check = $this->validate_rules( $text, $context );
        if ( ! $rule_check['passed'] ) {
            return $rule_check;
        }

        // Second pass: AI-powered validation (slower but more nuanced)
        $ai_check = $this->validate_with_ai( $text, $context );
        if ( ! $ai_check['passed'] ) {
            return $ai_check;
        }

        // Both checks passed
        return array(
            'passed' => true,
            'message' => '',
            'notes' => 'OK: All compliance checks passed',
            'confidence_score' => $ai_check['confidence_score'] ?? 0.95,
            'details' => array(
                'rule_check' => $rule_check,
                'ai_check' => $ai_check,
            ),
        );
    }

    /**
     * Rule-based validation: blacklist, length limits, required claims.
     *
     * @param string $text Variant text
     * @param array  $context Validation context
     * @return array Validation result
     */
    private function validate_rules( string $text, array $context ): array {
        // Check blacklist phrases
        foreach ( $this->blacklist as $phrase ) {
            if ( stripos( $text, $phrase ) !== false ) {
                return array(
                    'passed' => false,
                    'message' => sprintf( __( 'Blocked phrase detected: %s', 'kh-smma' ), $phrase ),
                    'notes' => 'FAIL: blacklist violation',
                    'confidence_score' => 1.0,
                    'violation_type' => 'blacklist',
                );
            }
        }

        // Check channel-specific length limits
        $channel = $context['channel'] ?? 'linkedin';
        $max_length = $this->get_channel_max_length( $channel );
        if ( mb_strlen( $text ) > $max_length ) {
            return array(
                'passed' => false,
                'message' => sprintf( __( 'Text exceeds %d character limit for %s', 'kh-smma' ), $max_length, $channel ),
                'notes' => 'FAIL: length violation',
                'confidence_score' => 1.0,
                'violation_type' => 'length',
            );
        }

        // Check sponsor-specific allowed claims
        if ( ! empty( $context['sponsor_id'] ) && ! empty( $context['allowed_claims'] ) ) {
            $claim_check = $this->check_allowed_claims( $text, $context['allowed_claims'] );
            if ( ! $claim_check['passed'] ) {
                return $claim_check;
            }
        }

        return array(
            'passed' => true,
            'message' => '',
            'notes' => 'OK: rule-based checks passed',
            'confidence_score' => 0.9,
        );
    }

    /**
     * AI-powered validation using secondary LLM for nuanced compliance checks.
     *
     * @param string $text Variant text
     * @param array  $context Validation context
     * @return array Validation result
     */
    private function validate_with_ai( string $text, array $context ): array {
        $llm_available = class_exists( '\\Dual_GPT\\Dual_GPT_LLM_Client' );
        if ( ! $llm_available ) {
            return array(
                'passed' => true,
                'message' => '',
                'notes' => 'SKIP: AI validation unavailable, falling back to rule-based only',
                'confidence_score' => 0.7,
            );
        }

        try {
            $client = new \Dual_GPT\Dual_GPT_LLM_Client();
            if ( ! $client->has_api_key() ) {
                return array(
                    'passed' => true,
                    'message' => '',
                    'notes' => 'SKIP: No LLM API key configured',
                    'confidence_score' => 0.7,
                );
            }

            $system = $this->build_compliance_system_prompt();
            $user   = $this->build_compliance_user_prompt( $text, $context );

            $response = $client->call( $system, $user, array(
                'json_mode' => true,
                'temperature' => 0.2, // Low temperature for consistent compliance checking
                'max_tokens' => 500,
            ) );

            if ( is_wp_error( $response ) ) {
                return array(
                    'passed' => true,
                    'message' => '',
                    'notes' => 'SKIP: AI validation failed, falling back to rule-based only',
                    'confidence_score' => 0.7,
                );
            }

            $result = $this->parse_ai_response( $response );
            return $result;

        } catch ( \Exception $e ) {
            return array(
                'passed' => true,
                'message' => '',
                'notes' => 'SKIP: AI validation exception - ' . $e->getMessage(),
                'confidence_score' => 0.7,
            );
        }
    }

    /**
     * Build system prompt for AI compliance validation.
     *
     * @return string System prompt
     */
    private function build_compliance_system_prompt(): string {
        return 'You are a compliance validator for social media advertising content. Analyze the provided text for:
1. Misleading claims or promises
2. Regulatory compliance (FTC, ASA guidelines)
3. Tone appropriateness for professional audiences
4. Sponsor policy adherence
5. Ethical marketing standards

Return JSON with: passed (boolean), message (string explaining any issues), confidence_score (0.0-1.0), and flags (array of concern areas).';
    }

    /**
     * Build user prompt with text and context for AI validation.
     *
     * @param string $text Variant text
     * @param array  $context Validation context
     * @return string User prompt as JSON
     */
    private function build_compliance_user_prompt( string $text, array $context ): string {
        return wp_json_encode( array(
            'text' => $text,
            'channel' => $context['channel'] ?? 'linkedin',
            'phase' => $context['phase_tag'] ?? 'Attention',
            'sponsor_policy' => $context['sponsor_policy'] ?? '',
            'allowed_claims' => $context['allowed_claims'] ?? array(),
            'geo_targets' => $context['geo_targets'] ?? array(),
        ) );
    }

    /**
     * Parse AI response into validation result.
     *
     * @param array $response LLM API response
     * @return array Validation result
     */
    private function parse_ai_response( array $response ): array {
        $body = $response['choices'][0]['message']['content'] ?? '';
        $decoded = json_decode( $body, true );

        if ( ! is_array( $decoded ) ) {
            return array(
                'passed' => true,
                'message' => '',
                'notes' => 'SKIP: Could not parse AI response',
                'confidence_score' => 0.7,
            );
        }

        return array(
            'passed' => (bool) ( $decoded['passed'] ?? true ),
            'message' => $decoded['message'] ?? '',
            'notes' => ( $decoded['passed'] ?? true ) ? 'OK: AI compliance passed' : 'FAIL: AI flagged compliance issues',
            'confidence_score' => (float) ( $decoded['confidence_score'] ?? 0.85 ),
            'flags' => $decoded['flags'] ?? array(),
        );
    }

    /**
     * Check if text contains at least one allowed claim for sponsored content.
     *
     * @param string $text Variant text
     * @param array  $allowed_claims Array of allowed claim phrases
     * @return array Check result
     */
    private function check_allowed_claims( string $text, array $allowed_claims ): array {
        $allowed = array_filter( array_map( 'trim', $allowed_claims ) );
        if ( empty( $allowed ) ) {
            return array(
                'passed' => true,
                'message' => '',
                'notes' => 'OK: no claim restrictions',
                'confidence_score' => 0.9,
            );
        }

        foreach ( $allowed as $claim ) {
            if ( '' !== $claim && stripos( $text, $claim ) !== false ) {
                return array(
                    'passed' => true,
                    'message' => '',
                    'notes' => 'OK: allowed claim found',
                    'confidence_score' => 0.95,
                );
            }
        }

        return array(
            'passed' => false,
            'message' => __( 'Sponsored content must include at least one approved claim from the sponsor.', 'kh-smma' ),
            'notes' => 'FAIL: no allowed sponsor claims detected',
            'confidence_score' => 1.0,
            'violation_type' => 'missing_claim',
        );
    }

    /**
     * Get maximum character length for a given channel.
     *
     * @param string $channel Channel name (linkedin, google_ads, etc.)
     * @return int Max character length
     */
    private function get_channel_max_length( string $channel ): int {
        $limits = array(
            'linkedin' => 3000,
            'twitter' => 280,
            'facebook' => 63206,
            'google_ads' => 90, // Description limit
            'instagram' => 2200,
        );

        return $limits[ $channel ] ?? 3000;
    }

    /**
     * Validate multiple variants in batch.
     *
     * @param array $variants Array of variant objects with 'text' key
     * @param array $context Shared validation context
     * @return array Validation results indexed by variant ID
     */
    public function validate_batch( array $variants, array $context = array() ): array {
        $results = array();
        foreach ( $variants as $variant ) {
            $variant_id = $variant['variant_id'] ?? '';
            $text = $variant['text'] ?? '';
            $variant_context = array_merge( $context, array(
                'channel' => $variant['channel'] ?? 'linkedin',
                'phase_tag' => $variant['phase_tag'] ?? 'Attention',
            ) );

            $results[ $variant_id ] = $this->validate( $text, $variant_context );
        }

        return $results;
    }

    /**
     * Validate Google Ads draft for compliance.
     *
     * Checks all headlines and descriptions in all ad groups for:
     * - Blacklisted phrases
     * - Sponsor allowed claims (if applicable)
     * - Character limits
     * - Policy violations
     *
     * @param array $draft Google Ads draft with ad_groups
     * @param array $context Validation context (sponsor_id, allowed_claims)
     * @return array Validation result with overall status and per-group details
     */
    public function validate_google_ad_draft( array $draft, array $context = array() ): array {
        if ( empty( $draft['ad_groups'] ) || ! is_array( $draft['ad_groups'] ) ) {
            return array(
                'passed' => true,
                'message' => '',
                'notes' => 'OK: No ad groups to validate',
                'ad_group_results' => array(),
            );
        }

        $ad_group_results = array();
        $overall_passed = true;
        $failure_messages = array();

        foreach ( $draft['ad_groups'] as $index => $ad_group ) {
            $group_key = 'group_' . $index;
            $keyword_cluster = $ad_group['keyword_cluster'] ?? '';
            $headlines = (array) ( $ad_group['headlines'] ?? array() );
            $descriptions = (array) ( $ad_group['descriptions'] ?? array() );

            $group_result = array(
                'keyword_cluster' => $keyword_cluster,
                'passed' => true,
                'headline_violations' => array(),
                'description_violations' => array(),
            );

            // Validate each headline
            foreach ( $headlines as $h_index => $headline ) {
                $check = $this->validate_rules( $headline, array_merge( $context, array(
                    'channel' => 'google_ads',
                ) ) );

                if ( ! $check['passed'] ) {
                    $group_result['passed'] = false;
                    $overall_passed = false;
                    $group_result['headline_violations'][] = array(
                        'index' => $h_index,
                        'text' => $headline,
                        'issue' => $check['message'],
                        'violation_type' => $check['violation_type'] ?? 'unknown',
                    );
                    $failure_messages[] = sprintf(
                        'Ad group %d, headline %d: %s',
                        $index + 1,
                        $h_index + 1,
                        $check['message']
                    );
                }

                // Check headline length (Google Ads: max 30 chars)
                if ( mb_strlen( $headline ) > 30 ) {
                    $group_result['passed'] = false;
                    $overall_passed = false;
                    $group_result['headline_violations'][] = array(
                        'index' => $h_index,
                        'text' => $headline,
                        'issue' => sprintf( 'Headline exceeds 30 characters (%d chars)', mb_strlen( $headline ) ),
                        'violation_type' => 'length',
                    );
                    $failure_messages[] = sprintf(
                        'Ad group %d, headline %d: Exceeds 30 characters',
                        $index + 1,
                        $h_index + 1
                    );
                }
            }

            // Validate each description
            foreach ( $descriptions as $d_index => $description ) {
                $check = $this->validate_rules( $description, array_merge( $context, array(
                    'channel' => 'google_ads',
                ) ) );

                if ( ! $check['passed'] ) {
                    $group_result['passed'] = false;
                    $overall_passed = false;
                    $group_result['description_violations'][] = array(
                        'index' => $d_index,
                        'text' => $description,
                        'issue' => $check['message'],
                        'violation_type' => $check['violation_type'] ?? 'unknown',
                    );
                    $failure_messages[] = sprintf(
                        'Ad group %d, description %d: %s',
                        $index + 1,
                        $d_index + 1,
                        $check['message']
                    );
                }

                // Check description length (Google Ads: max 90 chars)
                if ( mb_strlen( $description ) > 90 ) {
                    $group_result['passed'] = false;
                    $overall_passed = false;
                    $group_result['description_violations'][] = array(
                        'index' => $d_index,
                        'text' => $description,
                        'issue' => sprintf( 'Description exceeds 90 characters (%d chars)', mb_strlen( $description ) ),
                        'violation_type' => 'length',
                    );
                    $failure_messages[] = sprintf(
                        'Ad group %d, description %d: Exceeds 90 characters',
                        $index + 1,
                        $d_index + 1
                    );
                }
            }

            $ad_group_results[ $group_key ] = $group_result;
        }

        $message = '';
        if ( ! $overall_passed ) {
            $message = implode( '; ', array_slice( $failure_messages, 0, 3 ) );
            if ( count( $failure_messages ) > 3 ) {
                $message .= sprintf( ' (and %d more)', count( $failure_messages ) - 3 );
            }
        }

        return array(
            'passed' => $overall_passed,
            'message' => $message,
            'notes' => $overall_passed ? 'OK: All ad groups passed compliance' : 'FAIL: Compliance violations detected',
            'ad_group_results' => $ad_group_results,
            'total_violations' => count( $failure_messages ),
        );
    }
}
