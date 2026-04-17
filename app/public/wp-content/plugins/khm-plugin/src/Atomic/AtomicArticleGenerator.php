<?php
/**
 * Atomic Article Generator
 *
 * Decomposes a WordPress post into atomic articles via GPT-4o.
 * Each atomic article covers exactly one concept from the parent post.
 *
 * GPT returns a JSON array of units:
 * [
 *   {
 *     "title":       string,          // Self-contained H1
 *     "schema_type": string,          // Article | FAQPage | HowTo | DefinedTerm
 *     "summary":     string,          // 1-2 sentence intro (plain text)
 *     "sections":    [                // H2-level sections
 *       { "heading": string, "content": string }
 *     ]
 *   }
 * ]
 *
 * @package KHM\Atomic
 */

namespace KHM\Atomic;

use KHM\GEO\LLMClient;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic Article Generator
 */
class AtomicArticleGenerator {

    /**
     * LLM client instance.
     *
     * @var LLMClient
     */
    private LLMClient $llm;

    /**
     * Embedding service instance.
     *
     * @var AtomicEmbeddingService
     */
    private AtomicEmbeddingService $embedding_service;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->llm               = new LLMClient();
        $this->embedding_service = new AtomicEmbeddingService();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );
    }

    /**
     * Hook: trigger generation when a post is published/updated.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @return void
     */
    public function on_save_post( int $post_id, \WP_Post $post ): void {
        // Ignore autosaves, revisions, and non-enabled post types.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Only fire on published posts.
        if ( 'publish' !== $post->post_status ) {
            return;
        }

        // Skip the atomic_article CPT itself to prevent recursion.
        if ( AtomicArticlePostType::POST_TYPE === $post->post_type ) {
            return;
        }

        // Respect the per-post opt-in.
        if ( ! AtomicArticlePostType::is_generation_enabled( $post_id ) ) {
            return;
        }

        // Enqueue as a scheduled event to avoid blocking the save_post request.
        if ( ! wp_next_scheduled( 'khm_generate_atomic_articles', array( $post_id ) ) ) {
            wp_schedule_single_event( time(), 'khm_generate_atomic_articles', array( $post_id ) );
        }
    }

    /**
     * Generate (or regenerate) atomic articles for a parent post.
     *
     * Called by the scheduled event and directly by the REST regenerate endpoint.
     *
     * @param int $parent_id Parent post ID.
     * @return int[]|\WP_Error IDs of created/updated atomic_article posts, or error.
     */
    public function generate( int $parent_id ) {
        $parent = get_post( $parent_id );
        if ( ! $parent ) {
            return new \WP_Error( 'invalid_post', 'Parent post not found.', array( 'status' => 404 ) );
        }

        if ( ! $this->llm->has_api_key() ) {
            return new \WP_Error( 'no_api_key', 'OpenAI API key not configured.', array( 'status' => 500 ) );
        }

        // Build clean plain-text content for the prompt.
        $content = $this->prepare_content( $parent );

        // Call GPT.
        $units = $this->decompose_via_gpt( $parent->post_title, $content );

        if ( is_wp_error( $units ) ) {
            return $units;
        }

        // Delete previously generated atomics for this parent.
        $this->delete_stale_atomics( $parent_id );

        // Create new atomic_article posts.
        $new_ids = array();
        foreach ( $units as $unit ) {
            $id = $this->upsert_atomic_article( $parent_id, $unit );
            if ( ! is_wp_error( $id ) ) {
                $new_ids[] = $id;
            }
        }

        AtomicArticlePostType::set_ids_for_parent( $parent_id, $new_ids );

        return $new_ids;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Strip HTML and reduce post content to plain text suitable for a GPT prompt.
     *
     * @param \WP_Post $post Post object.
     * @return string
     */
    private function prepare_content( \WP_Post $post ): string {
        $content = wp_strip_all_tags( $post->post_content );
        // Collapse whitespace.
        $content = preg_replace( '/\s+/', ' ', $content );
        // Cap at ~12 000 chars to stay within a 16k-token context comfortably.
        return mb_substr( trim( $content ), 0, 12000 );
    }

    /**
     * Call GPT to decompose the article into atomic units.
     *
     * @param string $title   Parent article title.
     * @param string $content Cleaned article text.
     * @return array[]|\WP_Error Array of unit arrays or WP_Error.
     */
    private function decompose_via_gpt( string $title, string $content ) {
        $max_units = AtomicArticlePostType::MAX_PER_POST;

        $system_prompt = <<<PROMPT
You are a content strategist specialising in GEO (Generative Engine Optimisation).
Your task is to decompose a long-form article into atomic articles.

DEFINITION OF AN ATOMIC ARTICLE:
- Covers exactly ONE concept, question, or topic
- Is fully self-contained — a reader needs no other context to understand it
- Is suitable for AI crawlers and LLM training data (no dangling references)

INSTRUCTIONS:
1. Read the article title and body.
2. Identify the distinct atomic concepts — aim for 5–{$max_units} units.
3. For each unit, determine the most appropriate schema type:
   - Article       → factual explanation or news item
   - FAQPage       → question-and-answer format
   - HowTo         → step-by-step guide
   - DefinedTerm   → definition or glossary entry
4. Write each unit so it stands completely alone. Do NOT use phrases like "as mentioned above" or "see the main article".
5. Return ONLY a valid JSON array — no markdown fences, no preamble, no comments.

JSON SCHEMA (each element must match exactly):
{
  "title":       string,   // Self-contained H1, max 80 chars
  "schema_type": string,   // One of: Article, FAQPage, HowTo, DefinedTerm
  "summary":     string,   // 1–2 sentence plain-text intro, max 200 chars
  "sections": [
    {
      "heading": string,   // H2 heading, max 60 chars
      "content": string    // Plain text paragraphs for this section
    }
  ]
}
PROMPT;

        $user_prompt = "ARTICLE TITLE: {$title}\n\nARTICLE BODY:\n{$content}";

        $result = $this->llm->call(
            $system_prompt,
            $user_prompt,
            array(
                'model'       => 'gpt-4o',
                'temperature' => 0.3,
                'max_tokens'  => 4096,
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $raw = $result['content'] ?? '';
        $parsed = json_decode( $raw, true );

        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $parsed ) ) {
            error_log( '[KHM Atomic] GPT returned invalid JSON: ' . substr( $raw, 0, 500 ) );
            return new \WP_Error( 'invalid_response', 'GPT returned unparseable JSON.', array( 'status' => 502 ) );
        }

        return array_slice( $this->sanitize_units( $parsed ), 0, $max_units );
    }

    /**
     * Sanitize and validate each GPT-returned unit.
     *
     * @param array $units Raw decoded array.
     * @return array[]
     */
    private function sanitize_units( array $units ): array {
        $valid_schema_types = AtomicArticlePostType::SCHEMA_TYPES;
        $clean = array();

        foreach ( $units as $unit ) {
            if ( ! is_array( $unit ) ) {
                continue;
            }

            $title = sanitize_text_field( $unit['title'] ?? '' );
            if ( empty( $title ) ) {
                continue;
            }

            $schema_type = in_array( $unit['schema_type'] ?? '', $valid_schema_types, true )
                ? $unit['schema_type']
                : 'Article';

            $summary  = sanitize_text_field( $unit['summary'] ?? '' );
            $sections = array();

            foreach ( (array) ( $unit['sections'] ?? array() ) as $section ) {
                if ( ! is_array( $section ) ) {
                    continue;
                }
                $heading = sanitize_text_field( $section['heading'] ?? '' );
                $content = wp_kses_post( $section['content'] ?? '' );
                if ( $heading || $content ) {
                    $sections[] = compact( 'heading', 'content' );
                }
            }

            $clean[] = compact( 'title', 'schema_type', 'summary', 'sections' );
        }

        return $clean;
    }

    /**
     * Create or update a single atomic_article CPT post from a unit array.
     *
     * @param int   $parent_id Parent post ID.
     * @param array $unit      Sanitized unit array.
     * @return int|\WP_Error Post ID or error.
     */
    private function upsert_atomic_article( int $parent_id, array $unit ) {
        // Build the post content from sections.
        $post_content = '';
        foreach ( $unit['sections'] as $section ) {
            if ( $section['heading'] ) {
                $post_content .= '<h2>' . esc_html( $section['heading'] ) . "</h2>\n";
            }
            $post_content .= wpautop( wp_kses_post( $section['content'] ) ) . "\n";
        }

        $post_data = array(
            'post_type'    => AtomicArticlePostType::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $unit['title'],
            'post_content' => $post_content,
            'post_excerpt' => $unit['summary'],
        );

        // Disable the on_save_post hook to prevent re-triggering generation.
        remove_action( 'save_post', array( $this, 'on_save_post' ), 20 );

        $post_id = wp_insert_post( $post_data, true );

        add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $post_id, '_atomic_parent_id', $parent_id );
        update_post_meta( $post_id, '_atomic_schema_type', $unit['schema_type'] );
        update_post_meta( $post_id, '_atomic_generated_at', gmdate( 'c' ) );

        // Queue embedding generation.
        $this->embedding_service->queue_embed( $post_id );

        return $post_id;
    }

    /**
     * Delete all previously generated atomic articles for a parent post.
     *
     * @param int $parent_id Parent post ID.
     * @return void
     */
    private function delete_stale_atomics( int $parent_id ): void {
        $existing_ids = AtomicArticlePostType::get_ids_for_parent( $parent_id );

        foreach ( $existing_ids as $id ) {
            wp_delete_post( $id, true ); // force-delete, bypass trash
        }

        AtomicArticlePostType::set_ids_for_parent( $parent_id, array() );
    }
}
