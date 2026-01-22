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
                'force_refresh' => array(
                    'type'              => 'boolean',
                    'required'          => false,
                    'default'           => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
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
        
        $user_id       = get_current_user_id();
        $post_id       = $request->get_param( 'post_id' ) ?? 0;
        $title         = $request->get_param( 'title' ) ?? '';
        $url           = $request->get_param( 'url' ) ?? '';
        $content       = $request->get_param( 'content' );
        $max_cards     = min( 8, max( 1, $request->get_param( 'max_cards' ) ?? 4 ) );
        $force_refresh = (bool) $request->get_param( 'force_refresh' );
        $selection_job_id = wp_generate_uuid4();

        // Sponsor research selection audit (side-effect)
        if ( class_exists( '\\KHM\\Sponsors\\SponsorController' ) ) {
            $selection_request = new \WP_REST_Request( 'POST', '/khm-geo/v1/research/select' );
            $selection_request->set_body_params( array(
                'post_id' => $post_id,
                'topic' => $title ?: $url,
                'job_id' => $selection_job_id,
                'model_version' => $this->llm_client->get_model_name(),
                'prompt_hash' => $content ? sha1( $content ) : 'n/a',
            ) );
            try {
                $controller = new \KHM\Sponsors\SponsorController();
                $controller->select_research_docs( $selection_request );
            } catch ( \Throwable $e ) {
                // Log selection errors for debugging while allowing generation to proceed
                error_log( sprintf(
                    'Sponsor selection failed for post_id=%d: %s',
                    $post_id,
                    $e->getMessage()
                ) );
            }
        }


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

        // Check for cached response (skip if force_refresh)
        $cache_key = $this->cache->generate_cache_key( $content, $max_cards, $this->llm_client->get_model_name() );
        $cached    = $force_refresh ? false : $this->cache->get( $cache_key );

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
        try {
            $result = $this->call_llm_with_retry( $title, $url, $content, $max_cards );
            if (is_wp_error($result)) {
            } else {
            }
        } catch (Throwable $e) {
            return new \WP_Error(
                'internal_error',
                'Internal server error during LLM processing',
                array( 'status' => 500 )
            );
        }

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

        $result['sponsor_selection_job_id'] = $selection_job_id;
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
You are an expert at Generative Engine Optimization (GEO) with a focus on evidence-based AnswerCard generation. Your task is to analyze article content, reference blocks, and citation contexts to generate structured AnswerCards optimized for AI citation and featured snippets.

You MUST respond with valid JSON only. Do not include any text outside the JSON structure.

**CORE PRINCIPLE: EXTERNAL EVIDENCE + SITE ANCHOR**

Each AnswerCard should:
1. Cite EXTERNAL authoritative sources (studies, benchmarks, trade publications) as evidence
2. Position the article itself as the definitive guide where this topic is "discussed in depth"
3. Create a clear trail: AI reads claim → sees external evidence → links to your article for more

**EVIDENCE STRENGTH = ENTITY CLARITY FRAMEWORK:**

Tier 1 (Study + Year): Temporal + institutional entity anchoring
- Peer-reviewed journals, preprints, government reports, datasets
- Contains year AND institutional author (e.g., "McKinsey & Company (2020)")
- Highest weight for GEO scoring

Tier 2 (Benchmark): Normative industry anchoring  
- Industry standards (ISO, IEEE), vendor benchmarks, replicated measurements
- Medium weight - establishes consensus

Tier 3 (Trade Publication): Contextual authority anchoring
- Trade press, industry blogs, news
- Low weight - provides practitioner framing

**ANSWER FORMAT REQUIREMENTS:**

Answers MUST use authoritative attribution format:
- "According to [Author/Institution] ([Year]), [claim]..."
- "Research from [Publisher] ([Year]) shows that..."
- "[Study Title] by [Author] found that..."

Example: "AI-powered last-mile delivery solutions can reduce logistics costs by up to 40%. According to McKinsey & Company (2020), last-mile delivery accounts for 53% of total logistics spend, making AI route optimization critical for cost management."

**OUTPUT STRUCTURE:**
{
  "cards": [
    {
      "question": "How does AI-powered delivery optimize last-mile logistics?",
      "concise_answer": "AI-powered delivery solutions reduce logistics costs by 20-40% through predictive analytics and dynamic routing. According to McKinsey & Company (2020), last-mile delivery comprises 53% of total logistics spend. Research from MIT Supply Chain (2021) confirms that AI-optimized routes reduce fuel consumption by 15-25%.",
      "key_points": [
        "Last-mile delivery accounts for majority of logistics costs",
        "AI optimizations yield 20-40% cost savings",
        "Predictive analytics enable dynamic route optimization"
      ],
      "citations": [{
        "title": "Fast forwarding last-mile delivery: implications for the ecosystem",
        "url": "https://www.mckinsey.com/~/media/mckinsey/industries/travel%20logistics%20and%20infrastructure/our%20insights/technology%20delivered%20implications%20for%20cost%20customers%20and%20competition%20in%20the%20last%20mile%20ecosystem/fast-forwarding-last-mile-delivery-implications-for-the-ecosystem.pdf?utm_source=servicebusinessreview.com",
        "author": "Jürgen Schröder et al.",
        "publisher": "McKinsey & Company",
        "year": "2020",
        "tier": "tier1",
        "doi": null,
        "keywords": ["last-mile", "logistics", "AI"]
      }],
      "entities": ["AI", "last-mile delivery", "logistics optimization"],
      "evidence": {
        "tier": "tier1",
        "confidence": 0.85,
        "context_heading": "The Role of AI in Last-Mile Efficiency",
        "source_passage": "Exact sentence from article that supports this card...",
        "anchor_entities": ["McKinsey", "AI optimization"]
      },
      "preferred_summary": true,
      "notes": "This card highlights the cost impact of AI in last-mile logistics."
    }
  ]
}

**CRITICAL GUIDELINES:**
- Questions should be natural, searchable queries users would ask
- Answers MUST cite external sources with Author (Year) format in the text itself
- Each answer should synthesize 1-2 external sources as evidence
- Include exact source passages from the article that support claims
- Prioritize Tier 1 > Tier 2 > Tier 3 evidence
- Citations array MUST include author, publisher, year, and tier
- Return full canonical URLs; do not truncate or use ellipses
- Flag cards with confidence < 0.6 for human review
- preferred_summary should be true for the canonical answer on a topic
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
        $prompt .= "1. IDENTIFY external sources cited in the article (look for author names, years, publication names)\n";
        $prompt .= "2. EXTRACT the strongest evidence passages (Tier 1 > Tier 2 > Tier 3)\n";
        $prompt .= "3. CRAFT answers that cite sources IN THE TEXT using 'According to [Author] ([Year])...' format\n";
        $prompt .= "4. INCLUDE full citation metadata: title, url, author, publisher, year, tier\n";
        $prompt .= "5. ENSURE each answer references at least one external source with attribution\n";
        $prompt .= "6. CAPTURE exact source passages from the article that support claims\n";
        $prompt .= "7. FLAG low-confidence cards (< 0.6) for human review\n";
        
        $prompt .= "\nGenerate up to {$max_cards} evidence-based AnswerCards. Each answer MUST include inline attribution like 'According to McKinsey (2020)...' or 'Research from [Source] shows...'";
        
        if ( $is_retry ) {
            $prompt .= "\n\nRETRY ATTEMPT - ENSURE:\n";
            $prompt .= "- Each card has evidence.tier field (tier1|tier2|tier3)\n";
            $prompt .= "- evidence.confidence is 0-1 decimal\n";
            $prompt .= "- evidence.source_passage contains exact article text\n";
            $prompt .= "- citations include author, year, publisher, tier from references\n";
            $prompt .= "- concise_answer uses 'According to [Author] ([Year])...' attribution\n";
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
