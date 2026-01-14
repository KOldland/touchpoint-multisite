<?php
/**
 * Suggest AnswerCards REST Endpoint
 *
 * POST /wp-json/khm-geo/v1/suggest-answercards
 *
 * Generates AI-powered AnswerCard suggestions for a given post content.
 *
 * @package KHM\GEO
 */

namespace KHM\GEO;

defined( 'ABSPATH' ) || exit;

/**
 * Suggest AnswerCards Endpoint Class
 */
class SuggestAnswerCardsEndpoint {

    /**
     * LLM Client instance
     *
     * @var LLMClient
     */
    private $llm_client;

    /**
     * Validator instance
     *
     * @var AnswerCardSchemaValidator
     */
    private $validator;

    /**
     * Cache manager instance
     *
     * @var SuggestionCacheManager
     */
    private $cache;

    /**
     * Rate limiter instance
     *
     * @var RateLimiter
     */
    private $rate_limiter;

    /**
     * Audit logger instance
     *
     * @var SuggestionAuditLogger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->llm_client   = new LLMClient();
        $this->validator    = new AnswerCardSchemaValidator();
        $this->cache        = new SuggestionCacheManager();
        $this->rate_limiter = new RateLimiter();
        $this->logger       = new SuggestionAuditLogger();
    }

    /**
     * Register the REST route
     *
     * @return void
     */
    public function register() {
        register_rest_route( 'khm-geo/v1', '/suggest-answercards', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_request' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'post_id'   => array(
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ),
                'title'     => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'url'       => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'esc_url_raw',
                ),
                'content'   => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'wp_kses_post',
                ),
                'max_cards' => array(
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 4,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );
    }

    /**
     * Check if user has permission
     *
     * @return bool
     */
    public function check_permission() {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Handle the request
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_request( $request ) {
        $user_id   = get_current_user_id();
        $post_id   = $request->get_param( 'post_id' ) ?? 0;
        $title     = $request->get_param( 'title' ) ?? '';
        $url       = $request->get_param( 'url' ) ?? '';
        $content   = $request->get_param( 'content' );
        $max_cards = min( 8, max( 1, $request->get_param( 'max_cards' ) ?? 4 ) );

        // Check rate limit
        $rate_check = $this->rate_limiter->check_limit( $user_id );
        if ( is_wp_error( $rate_check ) ) {
            $this->logger->log_request( array(
                'user_id'       => $user_id,
                'post_id'       => $post_id,
                'action'        => 'suggest',
                'cached'        => false,
                'error_message' => 'Rate limit exceeded',
            ) );

            return $rate_check;
        }

        // Check for cached response
        $cache_key = $this->cache->generate_cache_key( $content, $max_cards, $this->llm_client->get_model_name() );
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            $this->logger->log_request( array(
                'user_id'       => $user_id,
                'post_id'       => $post_id,
                'action'        => 'suggest',
                'model'         => $this->llm_client->get_model_name(),
                'cached'        => true,
                'response_size' => strlen( wp_json_encode( $cached ) ),
            ) );

            $response = rest_ensure_response( $cached );
            $response->header( 'X-KHM-GEO-Cache', 'HIT' );
            return $response;
        }

        // Check API key
        if ( ! $this->llm_client->has_api_key() ) {
            return new \WP_Error(
                'no_api_key',
                __( 'OpenAI API key not configured. Please set it in Dual GPT settings.', 'khm-membership' ),
                array( 'status' => 500 )
            );
        }

        // Call LLM with retry on validation failure
        $result = $this->call_llm_with_retry( $title, $url, $content, $max_cards );

        if ( is_wp_error( $result ) ) {
            $this->logger->log_request( array(
                'user_id'       => $user_id,
                'post_id'       => $post_id,
                'action'        => 'suggest',
                'model'         => $this->llm_client->get_model_name(),
                'cached'        => false,
                'error_message' => $result->get_error_message(),
            ) );

            return $result;
        }

        // Increment rate limit counter
        $this->rate_limiter->increment( $user_id );

        // Cache the result
        $this->cache->set( $cache_key, $result );

        // Log success
        $this->logger->log_request( array(
            'user_id'           => $user_id,
            'post_id'           => $post_id,
            'action'            => 'suggest',
            'model'             => $result['model'] ?? $this->llm_client->get_model_name(),
            'cached'            => false,
            'response_size'     => strlen( wp_json_encode( $result ) ),
            'prompt_tokens'     => $result['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
            'estimated_cost'    => $result['estimated_cost'] ?? 0,
        ) );

        $response = rest_ensure_response( $result );
        $response->header( 'X-KHM-GEO-Cache', 'MISS' );
        return $response;
    }

    /**
     * Call LLM with retry on validation failure
     *
     * @param string $title     Article title.
     * @param string $url       Article URL.
     * @param string $content   Article content.
     * @param int    $max_cards Maximum cards to generate.
     * @return array|\WP_Error
     */
    private function call_llm_with_retry( $title, $url, $content, $max_cards ) {
        $max_attempts = 2;

        for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
            $result = $this->call_llm( $title, $url, $content, $max_cards, $attempt > 1 );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            // Validate response
            if ( $this->validator->validate( $result['cards'] ) ) {
                // Normalize cards
                $result['cards'] = array_map(
                    array( $this->validator, 'normalize' ),
                    $result['cards']
                );
                return $result;
            }

            // Log validation failure
            error_log( sprintf(
                '[KHM GEO] Validation failed on attempt %d: %s',
                $attempt,
                $this->validator->get_errors_string()
            ) );

            if ( $attempt >= $max_attempts ) {
                return new \WP_Error(
                    'validation_failed',
                    __( 'Generated content failed validation after retry. Please try again.', 'khm-membership' ),
                    array(
                        'status' => 422,
                        'errors' => $this->validator->get_errors(),
                    )
                );
            }
        }

        return new \WP_Error( 'unknown_error', __( 'Unknown error occurred', 'khm-membership' ) );
    }

    /**
     * Call the LLM
     *
     * @param string $title      Article title.
     * @param string $url        Article URL.
     * @param string $content    Article content.
     * @param int    $max_cards  Maximum cards.
     * @param bool   $is_retry   Whether this is a retry attempt.
     * @return array|\WP_Error
     */
    private function call_llm( $title, $url, $content, $max_cards, $is_retry = false ) {
        $system_prompt = $this->build_system_prompt();
        $user_prompt   = $this->build_user_prompt( $title, $url, $content, $max_cards, $is_retry );

        $response = $this->llm_client->call(
            $system_prompt,
            $user_prompt,
            array(
                'json_mode'   => true,
                'temperature' => $is_retry ? 0.5 : 0.7, // Lower temperature on retry
                'max_tokens'  => 3000,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Extract content
        $content_str = $this->llm_client->extract_content( $response );
        if ( empty( $content_str ) ) {
            return new \WP_Error( 'empty_response', __( 'Empty response from LLM', 'khm-membership' ) );
        }

        // Parse JSON
        $parsed = json_decode( $content_str, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'json_parse_error', __( 'Failed to parse LLM response as JSON', 'khm-membership' ) );
        }

        // Get usage and cost
        $usage = $this->llm_client->get_usage( $response );
        $cost  = $this->llm_client->estimate_cost( $usage );

        return array(
            'cards'          => $parsed['cards'] ?? $parsed,
            'model'          => $this->llm_client->get_model_name(),
            'generated_at'   => current_time( 'mysql' ),
            'usage'          => $usage,
            'estimated_cost' => $cost,
        );
    }

    /**
     * Build the system prompt
     *
     * @return string
     */
    private function build_system_prompt() {
        return <<<PROMPT
You are an expert at Generative Engine Optimization (GEO). Your task is to analyze article content and generate structured AnswerCards optimized for AI citation and featured snippets.

For each AnswerCard, provide:
1. A clear question the content answers
2. A concise answer (40-80 words ideal for featured snippets)
3. 3-5 key points as bullet takeaways
4. Relevant citations from the content (if URLs mentioned)
5. Key entities (topics, concepts, people, organizations)
6. A confidence score (0-1) based on how well the content supports the answer
7. Optional notes for the editor

Output valid JSON with this structure:
{
  "cards": [
    {
      "question": "What is...?",
      "concise_answer": "...",
      "key_points": ["point 1", "point 2", "point 3"],
      "citations": [{"title": "Source Name", "url": "https://..."}],
      "entities": [{"name": "Entity Name", "sameAs": "https://wikidata.org/wiki/Q..."}],
      "confidence": 0.85,
      "notes": "Optional editor notes"
    }
  ]
}

Guidelines:
- Questions should be natural queries users might search
- Answers should be direct and authoritative
- Key points should be scannable and actionable
- Only include citations that appear in the source content
- Entities should be key concepts, not common words
- Be conservative with confidence scores
PROMPT;
    }

    /**
     * Build the user prompt
     *
     * @param string $title     Article title.
     * @param string $url       Article URL.
     * @param string $content   Article content.
     * @param int    $max_cards Maximum cards.
     * @param bool   $is_retry  Whether this is a retry.
     * @return string
     */
    private function build_user_prompt( $title, $url, $content, $max_cards, $is_retry = false ) {
        $prompt = "Analyze this article and generate up to {$max_cards} AnswerCards.\n\n";

        if ( $title ) {
            $prompt .= "Title: {$title}\n";
        }
        if ( $url ) {
            $prompt .= "URL: {$url}\n";
        }

        $prompt .= "\nContent:\n{$content}";

        if ( $is_retry ) {
            $prompt .= "\n\nIMPORTANT: This is a retry. Please ensure:\n";
            $prompt .= "- Each card has a valid question (under 500 chars)\n";
            $prompt .= "- Each concise_answer is 20-150 words\n";
            $prompt .= "- Each card has at least 2 key_points\n";
            $prompt .= "- All URLs are valid format\n";
            $prompt .= "- Confidence is a decimal between 0 and 1\n";
        }

        return $prompt;
    }
}

/**
 * Register the endpoint
 */
function register_suggest_answercards_endpoint() {
    $endpoint = new SuggestAnswerCardsEndpoint();
    $endpoint->register();
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_suggest_answercards_endpoint' );
