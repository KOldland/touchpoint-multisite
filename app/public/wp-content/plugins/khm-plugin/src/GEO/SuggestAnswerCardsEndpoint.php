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
        // Direct file logging for debugging
        file_put_contents(ABSPATH . 'geo_debug.txt', '[GEO] handle_request called at ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
        
        $user_id   = get_current_user_id();
        $post_id   = $request->get_param( 'post_id' ) ?? 0;
        $title     = $request->get_param( 'title' ) ?? '';
        $url       = $request->get_param( 'url' ) ?? '';
        $content   = $request->get_param( 'content' );
        $max_cards = min( 8, max( 1, $request->get_param( 'max_cards' ) ?? 4 ) );

        file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] User ID: $user_id, Content length: " . strlen($content) . PHP_EOL, FILE_APPEND);

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
            file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] No API key found!" . PHP_EOL, FILE_APPEND);
            file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Sources checked: " . print_r(array(
                'env' => getenv( 'OPENAI_API_KEY' ) ? 'set' : 'not set',
                'dual_gpt_option' => get_option( 'dual_gpt_openai_api_key' ) ? 'set' : 'not set',
                'khm_option' => get_option( 'khm_geo_openai_api_key' ) ? 'set' : 'not set',
                'constant' => defined( 'OPENAI_API_KEY' ) ? 'set' : 'not set',
                'dual_gpt_constant' => defined( 'DUAL_GPT_OPENAI_API_KEY' ) ? 'set' : 'not set',
            ), true), FILE_APPEND);
            return new \WP_Error(
                'no_api_key',
                __( 'OpenAI API key not configured. Please set it in Dual GPT settings.', 'khm-membership' ),
                array( 'status' => 500 )
            );
        }

        file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] API key found, proceeding..." . PHP_EOL, FILE_APPEND);

        // Call LLM with retry on validation failure
        file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] About to call call_llm_with_retry..." . PHP_EOL, FILE_APPEND);
        try {
            $result = $this->call_llm_with_retry( $title, $url, $content, $max_cards );
            file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] call_llm_with_retry completed successfully" . PHP_EOL, FILE_APPEND);
            file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Result type: " . gettype($result) . PHP_EOL, FILE_APPEND);
            if (is_wp_error($result)) {
                file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Result is WP_Error: " . $result->get_error_message() . PHP_EOL, FILE_APPEND);
            } else {
                file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Result keys: " . implode(', ', array_keys($result)) . PHP_EOL, FILE_APPEND);
            }
        } catch (Throwable $e) {
            file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] FATAL ERROR in call_llm_with_retry: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL, FILE_APPEND);
            return new \WP_Error(
                'internal_error',
                'Internal server error during LLM processing',
                array( 'status' => 500 )
            );
        }

        if ( is_wp_error( $result ) ) {
            file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Returning WP_Error to client" . PHP_EOL, FILE_APPEND);
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
        file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Incrementing rate limit..." . PHP_EOL, FILE_APPEND);
        $this->rate_limiter->increment( $user_id );

        // Cache the result
        file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Caching result..." . PHP_EOL, FILE_APPEND);
        $this->cache->set( $cache_key, $result );

        // Log success
        file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Logging success..." . PHP_EOL, FILE_APPEND);
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

        file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Building response..." . PHP_EOL, FILE_APPEND);
        $response = rest_ensure_response( $result );
        $response->header( 'X-KHM-GEO-Cache', 'MISS' );
        file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] Returning response successfully!" . PHP_EOL, FILE_APPEND);
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
        file_put_contents(ABSPATH . 'geo_debug.txt', "[GEO] call_llm_with_retry started" . PHP_EOL, FILE_APPEND);
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
You are an expert at Generative Engine Optimization (GEO) with a focus on evidence-based AnswerCard generation. Your task is to analyze article content, reference blocks, and citation contexts to generate structured AnswerCards optimized for AI citation and featured snippets.

**EVIDENCE STRENGTH = ENTITY CLARITY FRAMEWORK:**

Tier 1 (Study + Year): Temporal + institutional entity anchoring
- Peer-reviewed journals, preprints, government reports, datasets
- Contains year AND institutional author
- Highest weight for GEO scoring

Tier 2 (Benchmark): Normative industry anchoring  
- Industry standards (ISO, IEEE), vendor benchmarks, replicated measurements
- Medium weight - establishes consensus

Tier 3 (Trade Publication): Contextual authority anchoring
- Trade press, industry blogs, news
- Low weight - provides practitioner framing

**ITERATIVE WORKFLOW:**
1. Familiarize with article + reference block structure
2. Parse H2/H3 headings and citation contexts  
3. Extract evidence passages with entity anchoring
4. Generate AnswerCards grounded in strongest evidence
5. Include full citation metadata and evidence tier

**OUTPUT STRUCTURE:**
{
  "cards": [
    {
      "question": "What is...?",
      "concise_answer": "40-80 word synthesis...",
      "key_points": ["point 1", "point 2", "point 3"],
      "citations": [{
        "url": "https://...",
        "title": "Study Title", 
        "author": "Author Name",
        "publisher": "Journal Name",
        "year": 2023,
        "tier": "tier1"
      }],
      "entities": ["entity_id_1", "entity_id_2"],
      "evidence": {
        "tier": "tier1",
        "confidence": 0.92,
        "context_heading": "H2 heading text",
        "source_passage": "Exact sentence from article...",
        "anchor_entities": ["entity_id_1"]
      },
      "preferred_summary": true,
      "notes": "Optional editor notes"
    }
  ]
}

**GUIDELINES:**
- Questions should be natural, searchable queries
- Answers should synthesize evidence with authoritative phrasing
- Include exact source passages that support claims
- Prioritize Tier 1 > Tier 2 > Tier 3 evidence
- Use "According to [Author, Year, Institution]..." format
- Flag low-confidence cards (< 0.6) for human review
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
        // Extract reference block if present
        $reference_block = $this->extract_reference_block( $content );
        $headings_map = $this->parse_headings_and_citations( $content );
        
        $prompt = "ANALYZE THIS ARTICLE FOR EVIDENCE-BASED ANSWERCARDS\n\n";
        
        if ( $title ) {
            $prompt .= "ARTICLE TITLE: {$title}\n";
        }
        if ( $url ) {
            $prompt .= "ARTICLE URL: {$url}\n";
        }
        
        $prompt .= "\n=== ARTICLE CONTENT ===\n{$content}\n";
        
        if ( ! empty( $reference_block ) ) {
            $prompt .= "\n=== REFERENCE BLOCK ===\n{$reference_block}\n";
        }
        
        if ( ! empty( $headings_map ) ) {
            $prompt .= "\n=== HEADING STRUCTURE & CITATIONS ===\n";
            foreach ( $headings_map as $heading => $citations ) {
                $prompt .= "HEADING: {$heading}\nCITATIONS: " . implode(', ', $citations) . "\n\n";
            }
        }
        
        $prompt .= "\n=== INSTRUCTIONS ===\n";
        $prompt .= "1. IDENTIFY strongest evidence passages (Tier 1 > Tier 2 > Tier 3)\n";
        $prompt .= "2. LOCATE H2/H3 context around superscript citations\n";
        $prompt .= "3. EXTRACT exact source passages that support claims\n";
        $prompt .= "4. GENERATE AnswerCards grounded in evidence\n";
        $prompt .= "5. INCLUDE full citation metadata from reference block\n";
        $prompt .= "6. USE authoritative phrasing: 'According to [Author, Year, Institution]...'\n";
        $prompt .= "7. FLAG low-confidence cards (< 0.6) for human review\n";
        
        $prompt .= "\nGenerate up to {$max_cards} evidence-based AnswerCards following the framework above.";
        
        if ( $is_retry ) {
            $prompt .= "\n\nRETRY ATTEMPT - ENSURE:\n";
            $prompt .= "- Each card has evidence.tier field (tier1|tier2|tier3)\n";
            $prompt .= "- evidence.confidence is 0-1 decimal\n";
            $prompt .= "- evidence.source_passage contains exact article text\n";
            $prompt .= "- citations include author, year, publisher from references\n";
            $prompt .= "- preferred_summary is true for canonical syntheses\n";
        }
        
        return $prompt;
    }

    /**
     * Extract reference block from content
     *
     * @param string $content Article content.
     * @return string Reference block text.
     */
    private function extract_reference_block( $content ) {
        // Look for common reference section patterns
        $patterns = [
            '/(?:References?|Bibliography|Sources?|Citations?)\s*:?\s*\n(.*?)(?:\n\n|\n#|\n===|$)/is',
            '/\n(\d+\..*?)(?:\n\n|\n#|\n===|$)/is', // Numbered references
            '/\n(\[.*?\].*?)(?:\n\n|\n#|\n===|$)/is', // Bracketed references
        ];
        
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $content, $matches ) ) {
                return trim( $matches[1] );
            }
        }
        
        return '';
    }

    /**
     * Parse headings and nearby citations
     *
     * @param string $content Article content.
     * @return array Heading => citations map.
     */
    private function parse_headings_and_citations( $content ) {
        $headings_map = [];
        $lines = explode( "\n", $content );
        $current_heading = '';
        
        foreach ( $lines as $line ) {
            // Check for H2/H3 headings
            if ( preg_match( '/^(#{2,3})\s+(.+)$/', $line, $matches ) ) {
                $current_heading = trim( $matches[2] );
                $headings_map[ $current_heading ] = [];
            }
            // Look for superscript citations in current heading context
            elseif ( $current_heading && preg_match_all( '/(\d+|\[\d+\]|\(\d+\))/', $line, $citation_matches ) ) {
                $headings_map[ $current_heading ] = array_merge(
                    $headings_map[ $current_heading ],
                    $citation_matches[1]
                );
            }
        }
        
        return array_filter( $headings_map ); // Remove empty entries
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
