<?php
/**
 * Atomic Article Schema Emitter
 *
 * Outputs JSON-LD structured data in <head> for atomic_article singular pages.
 *
 * Emits:
 *   - Article (always)
 *   - FAQPage | HowTo | DefinedTerm overlay when schema_type meta matches
 *
 * The Article schema includes isPartOf → parent post URL so GEO crawlers
 * can trace provenance back to the canonical long-form source.
 *
 * @package KHM\Atomic
 */

namespace KHM\Atomic;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic Schema Emitter
 */
class AtomicSchemaEmitter {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'wp_head', array( $this, 'emit_schema' ) );
    }

    /**
     * Output JSON-LD for atomic article pages.
     *
     * @return void
     */
    public function emit_schema(): void {
        if ( ! is_singular( AtomicArticlePostType::POST_TYPE ) ) {
            return;
        }

        $post = get_post();
        if ( ! $post ) {
            return;
        }

        $schema = $this->build_schema( $post );
        if ( empty( $schema ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the JSON-LD schema graph for a post.
     *
     * @param \WP_Post $post Atomic article post.
     * @return array
     */
    private function build_schema( \WP_Post $post ): array {
        $permalink    = get_permalink( $post );
        $schema_type  = get_post_meta( $post->ID, '_atomic_schema_type', true ) ?: 'Article';
        $parent_id    = (int) get_post_meta( $post->ID, '_atomic_parent_id', true );
        $generated_at = get_post_meta( $post->ID, '_atomic_generated_at', true );

        $published = get_post_datetime( $post, 'date', 'gmt' );
        $modified  = get_post_datetime( $post, 'modified', 'gmt' );

        // --- Article node (always present) ---
        $article = array(
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            '@id'              => $permalink . '#article',
            'headline'         => get_the_title( $post ),
            'description'      => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '…' ),
            'articleBody'      => wp_strip_all_tags( $post->post_content ),
            'url'              => $permalink,
            'datePublished'    => $published ? $published->format( 'c' ) : '',
            'dateModified'     => $modified ? $modified->format( 'c' ) : '',
            'inLanguage'       => get_bloginfo( 'language' ),
            'publisher'        => $this->get_publisher(),
        );

        if ( $parent_id ) {
            $parent_url = get_permalink( $parent_id );
            if ( $parent_url ) {
                $article['isPartOf'] = array(
                    '@type' => 'WebPage',
                    '@id'   => $parent_url,
                    'name'  => get_the_title( $parent_id ),
                    'url'   => $parent_url,
                );
            }
        }

        if ( $generated_at ) {
            $article['dateCreated'] = $generated_at;
        }

        $graph = array( $article );

        // --- Overlay schema based on schema_type ---
        $overlay = $this->build_overlay( $schema_type, $post, $permalink );
        if ( $overlay ) {
            $graph[] = $overlay;
        }

        return array(
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        );
    }

    /**
     * Build an overlay schema node for FAQPage, HowTo, or DefinedTerm types.
     *
     * @param string   $schema_type Schema type string.
     * @param \WP_Post $post        Post object.
     * @param string   $permalink   Post permalink.
     * @return array|null
     */
    private function build_overlay( string $schema_type, \WP_Post $post, string $permalink ): ?array {
        switch ( $schema_type ) {
            case 'FAQPage':
                return $this->build_faq_overlay( $post, $permalink );

            case 'HowTo':
                return $this->build_howto_overlay( $post, $permalink );

            case 'DefinedTerm':
                return array(
                    '@context'   => 'https://schema.org',
                    '@type'      => 'DefinedTerm',
                    '@id'        => $permalink . '#term',
                    'name'       => get_the_title( $post ),
                    'description' => has_excerpt( $post ) ? get_the_excerpt( $post ) : '',
                    'url'        => $permalink,
                );

            default:
                return null;
        }
    }

    /**
     * Build a FAQPage overlay by treating each H2 as a Q&A pair.
     *
     * @param \WP_Post $post      Post object.
     * @param string   $permalink Post permalink.
     * @return array|null
     */
    private function build_faq_overlay( \WP_Post $post, string $permalink ): ?array {
        $sections = $this->extract_h2_sections( $post->post_content );

        if ( empty( $sections ) ) {
            return null;
        }

        $entities = array();
        foreach ( $sections as $section ) {
            $entities[] = array(
                '@type'          => 'Question',
                'name'           => $section['heading'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $section['content'] ),
                ),
            );
        }

        return array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            '@id'        => $permalink . '#faq',
            'mainEntity' => $entities,
        );
    }

    /**
     * Build a HowTo overlay treating each H2 section as a step.
     *
     * @param \WP_Post $post      Post object.
     * @param string   $permalink Post permalink.
     * @return array|null
     */
    private function build_howto_overlay( \WP_Post $post, string $permalink ): ?array {
        $sections = $this->extract_h2_sections( $post->post_content );

        if ( empty( $sections ) ) {
            return null;
        }

        $steps = array();
        foreach ( $sections as $i => $section ) {
            $steps[] = array(
                '@type'    => 'HowToStep',
                'position' => $i + 1,
                'name'     => $section['heading'],
                'text'     => wp_strip_all_tags( $section['content'] ),
            );
        }

        return array(
            '@context' => 'https://schema.org',
            '@type'    => 'HowTo',
            '@id'      => $permalink . '#howto',
            'name'     => get_the_title( $post ),
            'step'     => $steps,
        );
    }

    /**
     * Extract H2 headings and their following content from post HTML.
     *
     * @param string $html Post content HTML.
     * @return array[] Array of {heading, content} pairs.
     */
    private function extract_h2_sections( string $html ): array {
        // Split on <h2 ...> tags.
        $parts = preg_split( '/<h2[^>]*>(.*?)<\/h2>/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );

        if ( ! $parts || count( $parts ) < 3 ) {
            return array();
        }

        $sections = array();
        // $parts[0] = content before first h2 (ignored)
        // $parts[1] = h2 text, $parts[2] = content after h2, $parts[3] = next h2 text, …
        for ( $i = 1; $i < count( $parts ) - 1; $i += 2 ) {
            $heading = wp_strip_all_tags( $parts[ $i ] );
            $content = wp_strip_all_tags( $parts[ $i + 1 ] ?? '' );
            if ( $heading ) {
                $sections[] = compact( 'heading', 'content' );
            }
        }

        return $sections;
    }

    /**
     * Build a minimal publisher Organization node.
     *
     * @return array
     */
    private function get_publisher(): array {
        return array(
            '@type' => 'Organization',
            'name'  => get_bloginfo( 'name' ),
            'url'   => home_url( '/' ),
        );
    }
}
