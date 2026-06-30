<?php
/**
 * Answer Synthesizer
 *
 * Optionally generates a human-readable answer from search results using
 * the configured LLM (OpenRouter via LLMService). If no LLM is configured,
 * returns the best excerpt directly — no error, just graceful degradation.
 *
 * Fallback chain (cost-aware):
 *   1. qwen/qwen3-coder:free  (OpenRouter free tier)
 *   2. nvidia/nemotron-nano-9b-v2:free (OpenRouter free tier)
 *   3. openai/gpt-4o-mini (paid fallback if available)
 *
 * @package KH\Editorial\Search\Services
 */

namespace KH\Editorial\Search\Services;

use KH\Editorial\Core\LLMService;
use KH\Editorial\Search\Models\SearchResult;
use KH\Editorial\Search\Models\ResultSet;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnswerSynthesizer
 */
class AnswerSynthesizer {

    /**
     * Synthesize a human-readable answer from search results.
     *
     * If LLM is configured, sends top results + query to generate a
     * conversational answer. Otherwise returns the best excerpt.
     *
     * @param string $query     Original search query.
     * @param array  $results   Array of SearchResult objects (already sorted by score).
     * @param string $fallback  The best-excerpt fallback if LLM is not available.
     * @return string The synthesized answer or the best excerpt.
     */
    public function synthesize( string $query, array $results, string $fallback = '' ): string {
        if ( empty( $results ) ) {
            return $fallback;
        }

        // Try LLM synthesis if configured.
        $answer = $this->try_llm_synthesis( $query, $results );
        if ( null !== $answer ) {
            return $answer;
        }

        // Fallback to the best excerpt.
        return $fallback;
    }

    /**
     * Attempt LLM-based answer synthesis via configured models.
     *
     * Reads model settings from kh_editorial_settings:
     *   - ai_answer_model    (primary, default: openrouter/free)
     *   - ai_answer_fallback (secondary, default: deepseek/deepseek-v4-flash)
     *   - ai_answer_tertiary (tertiary, default: gpt-4o-mini)
     *
     * All go through OpenRouter for model routing.
     *
     * @param string $query   The search query.
     * @param array  $results Array of SearchResult objects.
     * @return string|null Synthesized answer, or null on failure.
     */
    private function try_llm_synthesis( string $query, array $results ): ?string {
        // Check if LLMService is available and configured.
        if ( ! class_exists( '\KH\Editorial\Core\LLMService' ) ) {
            return null;
        }

        if ( ! LLMService::is_configured() ) {
            return null;
        }

        // Build a compact context from the top results.
        $context = $this->build_context( $results );

        if ( empty( $context ) ) {
            return null;
        }

        // Read configured models from settings.
        $settings   = LLMService::get_settings();
        $primary    = ! empty( $settings['ai_answer_model'] )    ? $settings['ai_answer_model']    : 'openrouter/free';
        $secondary  = ! empty( $settings['ai_answer_fallback'] ) ? $settings['ai_answer_fallback'] : 'deepseek/deepseek-v4-flash';
        $tertiary   = ! empty( $settings['ai_answer_tertiary'] ) ? $settings['ai_answer_tertiary'] : 'gpt-4o-mini';

        $prompt = $this->build_prompt( $query, $context );

        try {
            $result = LLMService::post_completion_with_retry(
                [
                    [
                        'role'    => 'system',
                        'content' => 'You are a helpful search assistant for a knowledge base. Answer the user\'s question based *only* on the provided content excerpts. Be concise but informative (2–4 sentences). If the excerpts don\'t answer the question, say so. Cite article titles in bold when referencing them.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                [
                    'provider'       => 'openrouter',
                    'model'          => $primary,
                    'temperature'    => 0.3,
                    'max_tokens'     => 500,
                    'fallback_chain' => [
                        $secondary,
                        $tertiary,
                    ],
                ]
            );

            if ( is_wp_error( $result ) ) {
                error_log( '[KH AnswerSynthesizer] LLM failed: ' . $result->get_error_message() );
                return null;
            }

            $content = trim( $result['content'] ?? '' );
            if ( empty( $content ) ) {
                return null;
            }

            return $content;

        } catch ( \Exception $e ) {
            error_log( '[KH AnswerSynthesizer] Exception: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Build a compact text context from search results for the LLM prompt.
     *
     * @param array $results Array of SearchResult objects.
     * @return string
     */
    private function build_context( array $results ): string {
        $lines = [];
        $max_results = min( count( $results ), 5 );

        for ( $i = 0; $i < $max_results; $i++ ) {
            $result = $results[ $i ];
            $data   = $result->to_array();

            $title   = $data['title'] ?? 'Untitled';
            $excerpt = $data['excerpt'] ?? '';
            $url     = $data['url'] ?? '';
            $score   = $data['score'] ?? 0;

            // Trim excerpt to avoid token blowout.
            if ( mb_strlen( $excerpt ) > 300 ) {
                $excerpt = mb_substr( $excerpt, 0, 300 ) . '…';
            }

            $lines[] = sprintf(
                "[%d] **%s** (Relevance: %.2f)\n   URL: %s\n   Excerpt: %s",
                $i + 1,
                $title,
                $score,
                $url,
                $excerpt
            );
        }

        return implode( "\n\n", $lines );
    }

    /**
     * Build the user prompt for the LLM.
     *
     * @param string $query   The search query.
     * @param string $context The formatted context text.
     * @return string
     */
    private function build_prompt( string $query, string $context ): string {
        return sprintf(
            "Search Query: \"%s\"\n\nHere are the most relevant content excerpts from our knowledge base:\n\n%s\n\n---\n\nBased *only* on the excerpts above, write a helpful answer to the user's question. Be concise (2–4 sentences). Reference article titles in bold when relevant.",
            $query,
            $context
        );
    }
}