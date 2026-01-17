<?php
/**
 * AnswerCard Gutenberg block registration, save handler and JSON-LD output.
 *
 * Location: src/Blocks/answer-card/answer-card.php
 *
 * Responsibilities:
 *  - Registers block assets and block type 'khm/answer-card'
 *  - Parses post content on save_post, collects answer-card blocks, persists canonical postmeta
 *  - Calls ScoringEngine and persists _geo_score/_geo_score_details
 *  - Emits JSON-LD for answer cards on front-end (wp_head)
 *
 * Security: sanitise inputs, capability checks and autosave/revision guards are included.
 *
 * @package KHM\Blocks\AnswerCard
 */

namespace KHM\Blocks\AnswerCard;

defined( 'ABSPATH' ) || exit;

// DEBUG: Add a simple inline script to admin head to prove this file is loading
add_action( 'admin_head', function() {
    echo '<script>console.log("[KHM DEBUG] answer-card.php is loading - admin_head hook fired!");</script>';
} );

/**
 * Register the AnswerCard block using block.json metadata.
 *
 * @return void
 */
function register_answercard_block() {
    $block_dir = __DIR__;

    // Register block type using block.json for metadata
    // WordPress will auto-register scripts/styles from the file: declarations in block.json
    register_block_type( $block_dir, array(
        'render_callback' => __NAMESPACE__ . '\\render_answercard_block',
    ) );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[KHM GEO] AnswerCard block registered at ' . $block_dir );
    }
}
add_action( 'init', __NAMESPACE__ . '\\register_answercard_block' );

/**
 * Enqueue block editor assets.
 * WordPress should auto-enqueue from block.json, but we add this as a fallback.
 */
function enqueue_block_editor_assets() {
    error_log( '[KHM GEO DEBUG] enqueue_block_editor_assets() called!' );
    
    wp_enqueue_script( 'khm-answer-card-editor-script' );
    wp_enqueue_style( 'khm-answer-card-editor-style' );
    
    // Add inline debug script
    wp_add_inline_script( 'khm-answer-card-editor-script', 'console.log("[KHM DEBUG] Inline script attached to khm-answer-card-editor-script");', 'before' );
    
    error_log( '[KHM GEO DEBUG] Scripts enqueued!' );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_block_editor_assets' );

/**
 * Also enqueue via admin_enqueue_scripts as a fallback for post editor screens.
 */
function admin_enqueue_block_assets( $hook ) {
    // Only on post edit screens
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }
    
    // Check if block editor is being used
    global $post;
    if ( ! $post || ! use_block_editor_for_post( $post ) ) {
        return;
    }
    
    wp_enqueue_script( 'khm-answer-card-editor-script' );
    wp_enqueue_style( 'khm-answer-card-editor-style' );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_enqueue_block_assets' );

/**
 * Server-rendered front-end HTML for AnswerCard block.
 *
 * Keep light-weight and semantic. JSON-LD is emitted separately.
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Block inner content.
 * @return string
 */
function render_answercard_block( $attributes, $content ) {
    $question    = isset( $attributes['question'] ) ? esc_html( html_entity_decode( $attributes['question'], ENT_QUOTES, 'UTF-8' ) ) : '';
    $answer      = isset( $attributes['conciseAnswer'] ) ? wp_kses_post( html_entity_decode( $attributes['conciseAnswer'], ENT_QUOTES, 'UTF-8' ) ) : '';
    $key_points  = isset( $attributes['keyPoints'] ) && is_array( $attributes['keyPoints'] ) ? $attributes['keyPoints'] : array();
    $citations   = isset( $attributes['citations'] ) && is_array( $attributes['citations'] ) ? $attributes['citations'] : array();
    $entities    = isset( $attributes['entities'] ) && is_array( $attributes['entities'] ) ? $attributes['entities'] : array();

    // Build HTML output
    $html  = '<section class="khm-answer-card" role="region" aria-label="' . esc_attr( $question ) . '">';
    $html .= '<h3 class="khm-answer-card__question">' . $question . '</h3>';
    $html .= '<div class="khm-answer-card__answer">' . $answer . '</div>';

    // Key points list
    if ( ! empty( $key_points ) ) {
        $html .= '<ul class="khm-answer-card__points">';
        foreach ( $key_points as $point ) {
            $html .= '<li>' . esc_html( html_entity_decode( $point, ENT_QUOTES, 'UTF-8' ) ) . '</li>';
        }
        $html .= '</ul>';
    }

    // Citations list - display as Title — Author (Year) • Publisher
    if ( ! empty( $citations ) ) {
        $html .= '<div class="khm-answer-card__citations">';
        $html .= '<strong>' . esc_html__( 'Sources:', 'khm-membership' ) . '</strong>';
        $html .= '<ul>';
        foreach ( $citations as $citation ) {
            if ( is_array( $citation ) && ! empty( $citation['url'] ) ) {
                // Decode HTML entities for clean display
                $title     = ! empty( $citation['title'] )
                             ? html_entity_decode( $citation['title'], ENT_QUOTES, 'UTF-8' )
                             : '';
                $author    = ! empty( $citation['author'] )
                             ? html_entity_decode( $citation['author'], ENT_QUOTES, 'UTF-8' )
                             : '';
                $year      = ! empty( $citation['year'] ) ? $citation['year'] : '';
                $publisher = ! empty( $citation['publisher'] )
                             ? html_entity_decode( $citation['publisher'], ENT_QUOTES, 'UTF-8' )
                             : '';

                // Build meta text: Author (Year), Publisher
                $meta_parts = array();
                if ( $author && $year ) {
                    $meta_parts[] = esc_html( $author ) . ' (' . esc_html( $year ) . ')';
                } elseif ( $author ) {
                    $meta_parts[] = esc_html( $author );
                } elseif ( $year ) {
                    $meta_parts[] = '(' . esc_html( $year ) . ')';
                }
                if ( $publisher ) {
                    $meta_parts[] = esc_html( $publisher );
                }
                $meta_text = implode( ', ', $meta_parts );

                // Build link with title only, then append meta
                $link_title = $title ? esc_html( $title ) : esc_url( $citation['url'] );
                $aria_label = esc_attr( 'Open citation: ' . ( $title ?: $citation['url'] ) . ' (opens in new tab)' );

                $html .= '<li class="khm-answer-card__citation-item">';
                $html .= '<a href="' . esc_url( $citation['url'] ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . $aria_label . '">' . $link_title . '</a>';
                if ( $meta_text ) {
                    $html .= '<span class="khm-answer-card__citation-meta"> — ' . $meta_text . '</span>';
                }
                $html .= '</li>';
            } elseif ( is_string( $citation ) ) {
                $html .= '<li><a href="' . esc_url( $citation ) . '" target="_blank" rel="noopener noreferrer">' . esc_url( $citation ) . '</a></li>';
            }
        }
        $html .= '</ul>';
        $html .= '</div>';
    }

    // Entity tags
    if ( ! empty( $entities ) ) {
        $html .= '<div class="khm-answer-card__entities">';
        foreach ( $entities as $entity ) {
            $entity_name = is_array( $entity ) && isset( $entity['name'] ) ? $entity['name'] : $entity;
            $html .= '<span class="khm-answer-card__entity-tag">' . esc_html( $entity_name ) . '</span>';
        }
        $html .= '</div>';
    }

    $html .= '</section>';

    return $html;
}

/**
 * Recursively collect blocks of a given name and accumulate their attributes.
 *
 * @param array  $blocks Array of parsed blocks.
 * @param string $name   Block name to search for.
 * @param array  $out    Reference to output array.
 * @return void
 */
function collect_blocks_recursive( $blocks, $name, &$out ) {
    foreach ( $blocks as $block ) {
        if ( isset( $block['blockName'] ) && $block['blockName'] === $name ) {
            $attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
            $out[] = $attrs;
        }
        if ( ! empty( $block['innerBlocks'] ) ) {
            collect_blocks_recursive( $block['innerBlocks'], $name, $out );
        }
    }
}

/**
 * On post save: parse blocks, collect AnswerCards, persist canonical postmeta, call scoring engine.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    Post object.
 * @return void
 */
function save_answercards_on_save_post( $post_id, $post ) {
    // Basic guards
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    // Supported post types - extend as needed
    $supported_types = apply_filters( 'khm_geo_answercard_post_types', array( 'post', 'page' ) );
    if ( ! in_array( $post->post_type, $supported_types, true ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Parse blocks from post content
    $blocks    = parse_blocks( $post->post_content );
    $collected = array();
    collect_blocks_recursive( $blocks, 'khm/answer-card', $collected );

    // Normalize to canonical format
    $canonical = array();
    $position  = 0;
    foreach ( $collected as $attrs ) {
        // Generate or preserve answer_card_id
        $answer_card_id = isset( $attrs['answerCardId'] ) && ! empty( $attrs['answerCardId'] )
                          ? sanitize_text_field( $attrs['answerCardId'] )
                          : generate_answer_card_id( $post_id );

        // Sanitize evidence first to check confidence
        $evidence   = isset( $attrs['evidence'] ) && is_array( $attrs['evidence'] )
                      ? sanitize_evidence( $attrs['evidence'] )
                      : array();
        $confidence = isset( $evidence['confidence'] ) ? floatval( $evidence['confidence'] ) : 0.0;

        // Determine requires_review based on confidence threshold
        $requires_review = $confidence < 0.6;

        // If requires_review is true, force expose_in_schema to false by default
        $expose_in_schema = isset( $attrs['exposeInSchema'] ) ? (bool) $attrs['exposeInSchema'] : true;
        if ( $requires_review && ! isset( $attrs['exposeInSchema'] ) ) {
            $expose_in_schema = false;
        }

        // Sanitize topic_discussed_at with auto-population
        $topic_discussed_at = isset( $attrs['topicDiscussedAt'] ) && is_array( $attrs['topicDiscussedAt'] )
                              ? sanitize_topic_discussed_at( $attrs['topicDiscussedAt'], $post_id )
                              : sanitize_topic_discussed_at( array(), $post_id );

        // Sanitize site_keywords array
        $site_keywords = array();
        if ( isset( $attrs['siteKeywords'] ) && is_array( $attrs['siteKeywords'] ) ) {
            $site_keywords = array_values( array_filter( array_map( 'sanitize_text_field', $attrs['siteKeywords'] ) ) );
        }

        $card = array(
            'answer_card_id'       => $answer_card_id,
            'question'             => isset( $attrs['question'] ) ? sanitize_text_field( $attrs['question'] ) : '',
            'concise_answer'       => isset( $attrs['conciseAnswer'] ) ? wp_kses_post( $attrs['conciseAnswer'] ) : '',
            'key_points'           => isset( $attrs['keyPoints'] ) && is_array( $attrs['keyPoints'] )
                                        ? array_map( 'sanitize_text_field', $attrs['keyPoints'] )
                                        : array(),
            'citations'            => isset( $attrs['citations'] ) && is_array( $attrs['citations'] )
                                        ? sanitize_citations( $attrs['citations'], $post_id, $answer_card_id )
                                        : array(),
            'entities'             => isset( $attrs['entities'] ) && is_array( $attrs['entities'] )
                                        ? sanitize_entities( $attrs['entities'] )
                                        : array(),
            'evidence'             => $evidence,
            'topic_discussed_at'   => $topic_discussed_at,
            'site_keywords'        => $site_keywords,
            'preferred_summary'    => isset( $attrs['preferredSummary'] ) ? sanitize_text_field( $attrs['preferredSummary'] ) : '',
            'public_summary_label' => isset( $attrs['publicSummaryLabel'] ) ? sanitize_text_field( $attrs['publicSummaryLabel'] ) : '',
            'expose_in_schema'     => $expose_in_schema,
            'requires_review'      => $requires_review,
            'position'             => $position++,
            'updated_at'           => current_time( 'mysql' ),
        );
        $canonical[] = $card;
    }

    // Save canonical meta
    update_post_meta( $post_id, '_geo_answercards', $canonical );

    // Run scoring integration
    run_scoring_for_post( $post_id, $canonical );

    // Optionally persist to database table for reporting
    persist_to_database( $post_id, $canonical );
}
add_action( 'save_post', __NAMESPACE__ . '\\save_answercards_on_save_post', 20, 2 );

/**
 * Sanitize citations array.
 *
 * @param array  $citations       Raw citations array.
 * @param int    $post_id         Post ID for creating tracked URLs.
 * @param string $answer_card_id  Answer card ID for creating tracked URLs.
 * @return array Sanitized citations.
 */
function sanitize_citations( $citations, $post_id = null, $answer_card_id = null ) {
    $sanitized = array();
    $index     = 0;

    foreach ( $citations as $citation ) {
        if ( is_array( $citation ) ) {
            $item = array(
                'title'           => isset( $citation['title'] ) ? sanitize_text_field( $citation['title'] ) : '',
                'url'             => isset( $citation['url'] ) ? esc_url_raw( $citation['url'] ) : '',
                'author'          => isset( $citation['author'] ) ? sanitize_text_field( $citation['author'] ) : '',
                'publisher'       => isset( $citation['publisher'] ) ? sanitize_text_field( $citation['publisher'] ) : '',
                'year'            => isset( $citation['year'] ) ? sanitize_text_field( strval( $citation['year'] ) ) : '',
                'tier'            => isset( $citation['tier'] ) ? sanitize_text_field( $citation['tier'] ) : '',
                'doi'             => isset( $citation['doi'] ) ? sanitize_text_field( $citation['doi'] ) : '',
                'keywords'        => isset( $citation['keywords'] ) && is_array( $citation['keywords'] )
                                     ? array_map( 'sanitize_text_field', $citation['keywords'] )
                                     : array(),
                'enable_tracking' => ! empty( $citation['enableTracking'] ) || ! empty( $citation['enable_tracking'] ),
                'tracked_url'     => '',
            );

            // Generate tracked URL if tracking is enabled and we have a valid URL
            if ( $item['enable_tracking'] && ! empty( $item['url'] ) && $post_id && $answer_card_id ) {
                // Check if GeoAnswerCardMigration class is available
                if ( class_exists( '\\KHM\\Migrations\\GeoAnswerCardMigration' ) ) {
                    $tracked = \KHM\Migrations\GeoAnswerCardMigration::create_redirect_record(
                        $item['url'],
                        $post_id,
                        $answer_card_id,
                        $index
                    );
                    if ( $tracked ) {
                        $item['tracked_url'] = $tracked;
                    }
                }
            } elseif ( isset( $citation['trackedUrl'] ) || isset( $citation['tracked_url'] ) ) {
                // Preserve existing tracked URL
                $item['tracked_url'] = esc_url_raw( $citation['trackedUrl'] ?? $citation['tracked_url'] ?? '' );
            }

            $sanitized[] = $item;
        } elseif ( is_string( $citation ) ) {
            $sanitized[] = array(
                'title'           => '',
                'url'             => esc_url_raw( $citation ),
                'author'          => '',
                'publisher'       => '',
                'year'            => '',
                'tier'            => '',
                'doi'             => '',
                'keywords'        => array(),
                'enable_tracking' => false,
                'tracked_url'     => '',
            );
        }
        $index++;
    }
    return $sanitized;
}

/**
 * Sanitize entities array.
 *
 * @param array $entities Raw entities array.
 * @return array Sanitized entities.
 */
function sanitize_entities( $entities ) {
    $sanitized = array();
    foreach ( $entities as $entity ) {
        if ( is_array( $entity ) ) {
            $sanitized[] = array(
                'name'   => isset( $entity['name'] ) ? sanitize_text_field( $entity['name'] ) : '',
                'sameAs' => isset( $entity['sameAs'] ) ? esc_url_raw( $entity['sameAs'] ) : '',
            );
        } elseif ( is_string( $entity ) ) {
            $sanitized[] = array(
                'name'   => sanitize_text_field( $entity ),
                'sameAs' => '',
            );
        }
    }
    return $sanitized;
}

/**
 * Sanitize evidence data.
 *
 * @param array $evidence Raw evidence array.
 * @return array Sanitized evidence.
 */
function sanitize_evidence( $evidence ) {
    return array(
        'tier'            => isset( $evidence['tier'] ) ? sanitize_text_field( $evidence['tier'] ) : '',
        'confidence'      => isset( $evidence['confidence'] ) ? floatval( $evidence['confidence'] ) : 0.0,
        'context_heading' => isset( $evidence['contextHeading'] ) ? sanitize_text_field( $evidence['contextHeading'] ) : '',
        'source_passage'  => isset( $evidence['sourcePassage'] ) ? sanitize_text_field( $evidence['sourcePassage'] ) : '',
        'anchor_entities' => isset( $evidence['anchorEntities'] ) && is_array( $evidence['anchorEntities'] )
                             ? array_map( 'sanitize_text_field', $evidence['anchorEntities'] )
                             : array(),
    );
}

/**
 * Sanitize topic_discussed_at data.
 *
 * @param array  $topic_discussed_at Raw topic data.
 * @param int    $post_id            Post ID for auto-populating defaults.
 * @return array Sanitized topic_discussed_at.
 */
function sanitize_topic_discussed_at( $topic_discussed_at, $post_id = null ) {
    // Get defaults from the post if not provided
    $post       = $post_id ? get_post( $post_id ) : null;
    $post_url   = $post ? get_permalink( $post ) : '';
    $post_title = $post ? get_the_title( $post ) : '';
    $post_date  = $post ? get_the_date( 'Y-m-d', $post ) : '';

    // Get post author name
    $author_name = '';
    if ( $post && $post->post_author ) {
        $author = get_userdata( $post->post_author );
        if ( $author ) {
            $author_name = $author->display_name;
        }
    }

    // Get site name as publisher
    $publisher = get_bloginfo( 'name' );

    return array(
        'url'       => isset( $topic_discussed_at['url'] ) && ! empty( $topic_discussed_at['url'] )
                       ? esc_url_raw( $topic_discussed_at['url'] )
                       : $post_url,
        'title'     => isset( $topic_discussed_at['title'] ) && ! empty( $topic_discussed_at['title'] )
                       ? sanitize_text_field( $topic_discussed_at['title'] )
                       : $post_title,
        'author'    => isset( $topic_discussed_at['author'] ) && ! empty( $topic_discussed_at['author'] )
                       ? sanitize_text_field( $topic_discussed_at['author'] )
                       : $author_name,
        'publisher' => isset( $topic_discussed_at['publisher'] ) && ! empty( $topic_discussed_at['publisher'] )
                       ? sanitize_text_field( $topic_discussed_at['publisher'] )
                       : $publisher,
        'date'      => isset( $topic_discussed_at['date'] ) && ! empty( $topic_discussed_at['date'] )
                       ? sanitize_text_field( $topic_discussed_at['date'] )
                       : $post_date,
        'note'      => isset( $topic_discussed_at['note'] )
                       ? sanitize_text_field( $topic_discussed_at['note'] )
                       : '',
    );
}

/**
 * Generate a stable answer_card_id for an answer card.
 *
 * @param int $post_id The post ID.
 * @return string The generated answer card ID.
 */
function generate_answer_card_id( $post_id ) {
    return sprintf( 'AC-%d-%s', absint( $post_id ), bin2hex( random_bytes( 4 ) ) );
}

/**
 * Run the scoring engine for a post's answer cards.
 *
 * @param int   $post_id   Post ID.
 * @param array $canonical Array of canonical answer cards.
 * @return void
 */
function run_scoring_for_post( $post_id, $canonical ) {
    // Check if ScoringEngine is available
    if ( ! class_exists( '\\KHM_SEO\\GEO\\Scoring\\ScoringEngine' ) ) {
        // Initialize with zero score if no engine available
        update_post_meta( $post_id, '_geo_score', 0 );
        update_post_meta( $post_id, '_geo_score_details', array() );
        return;
    }

    try {
        $scoring_engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();
        $page_scores    = array();
        $total_scores   = array();

        foreach ( $canonical as $card ) {
            $context    = array( 'post_id' => $post_id );
            $score_data = $scoring_engine->calculate_score( $card, $context );

            $page_scores[] = array(
                'card'       => $card,
                'score_data' => $score_data,
            );

            $total_scores[] = isset( $score_data['total_score'] ) ? floatval( $score_data['total_score'] ) : 0.0;
        }

        // Set expose_in_schema=false for cards below confidence threshold
        foreach ( $page_scores as &$score_item ) {
            $card = $score_item['card'];
            $evidence = $card['evidence'] ?? array();
            if ( ! empty( $evidence['confidence'] ) && $evidence['confidence'] < 0.6 ) {
                $score_item['card']['expose_in_schema'] = false;
            }
        }

        // Calculate composite score (average across all cards)
        $composite_total = 0.0;
        if ( count( $total_scores ) > 0 ) {
            $composite_total = array_sum( $total_scores ) / count( $total_scores );
        }

        update_post_meta( $post_id, '_geo_score', $composite_total );
        update_post_meta( $post_id, '_geo_score_details', $page_scores );

    } catch ( \Exception $e ) {
        error_log( '[KHM GEO] Scoring failed for post ' . $post_id . ': ' . $e->getMessage() );
        update_post_meta( $post_id, '_geo_score', 0 );
        update_post_meta( $post_id, '_geo_score_details', array() );
    }
}

/**
 * Persist answer cards to database table for reporting/tracker.
 *
 * @param int   $post_id   Post ID.
 * @param array $canonical Array of canonical answer cards.
 * @return void
 */
function persist_to_database( $post_id, $canonical ) {
    global $wpdb;

    $table = $wpdb->prefix . 'geo_answer_cards';

    // Check if table exists
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( ! $table_exists ) {
        return; // Table not migrated yet
    }

    // Delete existing cards for this post
    $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );

    // Insert new cards
    foreach ( $canonical as $card ) {
        $answer     = $card['concise_answer'] ?? '';
        $word_count = str_word_count( strip_tags( $answer ) );

        $wpdb->insert(
            $table,
            array(
                'post_id'            => $post_id,
                'answer_card_id'     => $card['answer_card_id'] ?? '',
                'question'           => $card['question'],
                'concise_answer'     => $card['concise_answer'],
                'key_points'         => wp_json_encode( $card['key_points'] ),
                'citations'          => wp_json_encode( $card['citations'] ),
                'entities'           => wp_json_encode( $card['entities'] ),
                'evidence_json'      => wp_json_encode( $card['evidence'] ),
                'preferred_summary'  => $card['preferred_summary'] ?? '',
                'topic_discussed_at' => wp_json_encode( $card['topic_discussed_at'] ?? array() ),
                'expose_in_schema'   => ! empty( $card['expose_in_schema'] ) ? 1 : 0,
                'requires_review'    => ! empty( $card['requires_review'] ) ? 1 : 0,
                'position'           => $card['position'] ?? 0,
                'word_count'         => $word_count,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
        );
    }
}

/**
 * Generate JSON-LD for exposed AnswerCards and output in wp_head.
 *
 * @return void
 */
function output_answercard_jsonld() {
    if ( ! is_singular() ) {
        return;
    }

    $post_id = get_queried_object_id();
    if ( ! $post_id ) {
        return;
    }

    $cards = get_post_meta( $post_id, '_geo_answercards', true );
    if ( empty( $cards ) || ! is_array( $cards ) ) {
        return;
    }

    $has_part = array();
    $page_url = get_permalink( $post_id );

    foreach ( $cards as $card ) {
        // Skip cards not exposed in schema
        if ( empty( $card['expose_in_schema'] ) ) {
            continue;
        }

        // Skip cards flagged for review
        if ( ! empty( $card['requires_review'] ) ) {
            continue;
        }

        // Use preferred_summary if available, otherwise fall back to concise_answer
        $answer_text = ! empty( $card['preferred_summary'] )
                       ? $card['preferred_summary']
                       : ( $card['concise_answer'] ?? '' );

        // Generate @id anchor using answer_card_id
        $answer_card_id = $card['answer_card_id'] ?? '';
        $element_id     = $answer_card_id ? $page_url . '#answer-' . $answer_card_id : '';

        $part = array(
            '@type'    => 'WebPageElement',
            'name'     => $card['question'] ?? '',
            'text'     => $answer_text,
            'position' => isset( $card['position'] ) ? intval( $card['position'] ) : 0,
        );

        // Add @id for stable reference
        if ( $element_id ) {
            $part['@id'] = $element_id;
        }

        // Add citations with enhanced CreativeWork metadata
        if ( ! empty( $card['citations'] ) && is_array( $card['citations'] ) ) {
            $cits = array();
            foreach ( $card['citations'] as $c ) {
                $citation_item = array( '@type' => 'CreativeWork' );

                if ( is_array( $c ) ) {
                    // Enhanced citation with metadata (public safe fields only)
                    // Use publisher canonical URL - never use tracked_url in JSON-LD
                    if ( ! empty( $c['url'] ) ) {
                        $citation_item['url'] = esc_url_raw( $c['url'] );
                    }
                    if ( ! empty( $c['title'] ) ) {
                        // Decode HTML entities for clean display
                        $citation_item['name'] = html_entity_decode( sanitize_text_field( $c['title'] ), ENT_QUOTES, 'UTF-8' );
                    }
                    if ( ! empty( $c['author'] ) ) {
                        $citation_item['author'] = array(
                            '@type' => 'Person',
                            'name'  => html_entity_decode( sanitize_text_field( $c['author'] ), ENT_QUOTES, 'UTF-8' ),
                        );
                    }
                    if ( ! empty( $c['publisher'] ) ) {
                        $citation_item['publisher'] = array(
                            '@type' => 'Organization',
                            'name'  => html_entity_decode( sanitize_text_field( $c['publisher'] ), ENT_QUOTES, 'UTF-8' ),
                        );
                    }
                    // Use year field for datePublished
                    if ( ! empty( $c['year'] ) ) {
                        $citation_item['datePublished'] = sanitize_text_field( $c['year'] );
                    } elseif ( ! empty( $c['date'] ) ) {
                        $citation_item['datePublished'] = sanitize_text_field( $c['date'] );
                    }
                    // Add DOI if present
                    if ( ! empty( $c['doi'] ) ) {
                        $citation_item['identifier'] = array(
                            '@type'        => 'PropertyValue',
                            'propertyID'   => 'doi',
                            'value'        => sanitize_text_field( $c['doi'] ),
                        );
                    }
                    // Keep evidence tier private - don't expose in public JSON-LD
                } elseif ( is_string( $c ) ) {
                    // Fallback for simple string citations
                    $citation_item['url'] = esc_url_raw( $c );
                }

                $cits[] = $citation_item;
            }
            if ( ! empty( $cits ) ) {
                $part['citation'] = $cits;
            }
        }

        // Add entities as "about"
        if ( ! empty( $card['entities'] ) && is_array( $card['entities'] ) ) {
            $part['about'] = array();
            foreach ( $card['entities'] as $entity ) {
                $entity_item = array( '@type' => 'Thing' );
                if ( is_array( $entity ) ) {
                    $entity_item['name'] = sanitize_text_field( $entity['name'] ?? '' );
                    if ( ! empty( $entity['sameAs'] ) ) {
                        $entity_item['sameAs'] = esc_url_raw( $entity['sameAs'] );
                    }
                } else {
                    $entity_item['name'] = sanitize_text_field( $entity );
                }
                $part['about'][] = $entity_item;
            }
        }

        $has_part[] = $part;
    }

    if ( ! empty( $has_part ) ) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'WebPage',
            '@id'      => $page_url,
            'url'      => $page_url,
            'name'     => get_the_title( $post_id ),
            'hasPart'  => $has_part,
        );

        // Optional: Add FAQPage schema for better SEO
        $faq_items = array();
        foreach ( $cards as $card ) {
            // Skip cards not exposed or flagged for review
            if ( empty( $card['expose_in_schema'] ) || ! empty( $card['requires_review'] ) ) {
                continue;
            }

            if ( ! empty( $card['question'] ) && ! empty( $card['concise_answer'] ) ) {
                // Use preferred_summary if available
                $answer_text = ! empty( $card['preferred_summary'] )
                               ? $card['preferred_summary']
                               : $card['concise_answer'];

                $answer_card_id = $card['answer_card_id'] ?? '';
                $topic          = $card['topic_discussed_at'] ?? array();

                // Decode HTML entities for clean JSON output
                $question_text = html_entity_decode( $card['question'], ENT_QUOTES, 'UTF-8' );
                $answer_text   = html_entity_decode( $answer_text, ENT_QUOTES, 'UTF-8' );

                // Build acceptedAnswer with site anchor metadata
                $accepted_answer = array(
                    '@type' => 'Answer',
                    'text'  => $answer_text,
                );

                // Add @id for stable reference
                if ( $answer_card_id ) {
                    $accepted_answer['@id'] = $page_url . '#answer-' . $answer_card_id;
                }

                // Add author from topic_discussed_at (site's author, not external citation)
                if ( ! empty( $topic['author'] ) ) {
                    $accepted_answer['author'] = array(
                        '@type' => 'Person',
                        'name'  => html_entity_decode( $topic['author'], ENT_QUOTES, 'UTF-8' ),
                    );
                }

                // Add datePublished from topic_discussed_at
                if ( ! empty( $topic['date'] ) ) {
                    $accepted_answer['datePublished'] = $topic['date'];
                }

                // Build the Question item
                $faq_item = array(
                    '@type'          => 'Question',
                    'name'           => $question_text,
                    'acceptedAnswer' => $accepted_answer,
                );

                // Add @id for Question
                if ( $answer_card_id ) {
                    $faq_item['@id'] = $page_url . '#question-' . $answer_card_id;
                }

                // Add site_keywords if available
                if ( ! empty( $card['site_keywords'] ) && is_array( $card['site_keywords'] ) ) {
                    $faq_item['keywords'] = implode( ', ', array_map( function( $kw ) {
                        return html_entity_decode( sanitize_text_field( $kw ), ENT_QUOTES, 'UTF-8' );
                    }, $card['site_keywords'] ) );
                }

                // Add about (entities) for semantic linking
                if ( ! empty( $card['entities'] ) && is_array( $card['entities'] ) ) {
                    $about_items = array();
                    foreach ( $card['entities'] as $entity ) {
                        $entity_item = array( '@type' => 'Thing' );
                        if ( is_array( $entity ) ) {
                            $entity_item['name'] = html_entity_decode( sanitize_text_field( $entity['name'] ?? '' ), ENT_QUOTES, 'UTF-8' );
                            if ( ! empty( $entity['sameAs'] ) ) {
                                $entity_item['sameAs'] = esc_url_raw( $entity['sameAs'] );
                            }
                        } else {
                            $entity_item['name'] = html_entity_decode( sanitize_text_field( $entity ), ENT_QUOTES, 'UTF-8' );
                        }
                        $about_items[] = $entity_item;
                    }
                    if ( ! empty( $about_items ) ) {
                        $faq_item['about'] = $about_items;
                    }
                }

                $faq_items[] = $faq_item;
            }
        }

        if ( ! empty( $faq_items ) ) {
            $faq_schema = array(
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                '@id'        => $page_url . '#faqpage',
                'mainEntity' => $faq_items,
            );

            // Add site author/publisher from first card's topic_discussed_at if available
            foreach ( $cards as $card ) {
                if ( ! empty( $card['expose_in_schema'] ) && empty( $card['requires_review'] ) ) {
                    $topic = $card['topic_discussed_at'] ?? array();
                    if ( ! empty( $topic['author'] ) ) {
                        $faq_schema['author'] = array(
                            '@type' => 'Person',
                            'name'  => html_entity_decode( $topic['author'], ENT_QUOTES, 'UTF-8' ),
                        );
                    }
                    if ( ! empty( $topic['publisher'] ) ) {
                        $faq_schema['publisher'] = array(
                            '@type' => 'Organization',
                            'name'  => html_entity_decode( $topic['publisher'], ENT_QUOTES, 'UTF-8' ),
                        );
                    }
                    if ( ! empty( $topic['date'] ) ) {
                        $faq_schema['datePublished'] = $topic['date'];
                    }
                    break; // Use first eligible card's topic_discussed_at
                }
            }

            // Output FAQPage schema
            echo "\n<script type=\"application/ld+json\">\n" . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n</script>\n";
        }

        // Output WebPage schema
        echo "\n<script type=\"application/ld+json\">\n" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n</script>\n";
    }
}
add_action( 'wp_head', __NAMESPACE__ . '\\output_answercard_jsonld', 100 );

/**
 * Add GEO score column to admin post list.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function add_geo_score_column( $columns ) {
    $columns['geo_score'] = __( 'GEO Score', 'khm-membership' );
    return $columns;
}
add_filter( 'manage_posts_columns', __NAMESPACE__ . '\\add_geo_score_column' );
add_filter( 'manage_pages_columns', __NAMESPACE__ . '\\add_geo_score_column' );

/**
 * Display GEO score in admin column.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 * @return void
 */
function display_geo_score_column( $column, $post_id ) {
    if ( 'geo_score' !== $column ) {
        return;
    }

    $score = get_post_meta( $post_id, '_geo_score', true );
    if ( ! $score && $score !== 0 ) {
        echo '<span class="geo-score geo-score--none">—</span>';
        return;
    }

    $score = floatval( $score );
    $class = 'geo-score--low';
    if ( $score >= 70 ) {
        $class = 'geo-score--high';
    } elseif ( $score >= 40 ) {
        $class = 'geo-score--medium';
    }

    printf(
        '<span class="geo-score %s">%s</span>',
        esc_attr( $class ),
        esc_html( number_format( $score, 1 ) )
    );
}
add_action( 'manage_posts_custom_column', __NAMESPACE__ . '\\display_geo_score_column', 10, 2 );
add_action( 'manage_pages_custom_column', __NAMESPACE__ . '\\display_geo_score_column', 10, 2 );

/**
 * Make GEO score column sortable.
 *
 * @param array $columns Sortable columns.
 * @return array Modified sortable columns.
 */
function make_geo_score_sortable( $columns ) {
    $columns['geo_score'] = 'geo_score';
    return $columns;
}
add_filter( 'manage_edit-post_sortable_columns', __NAMESPACE__ . '\\make_geo_score_sortable' );
add_filter( 'manage_edit-page_sortable_columns', __NAMESPACE__ . '\\make_geo_score_sortable' );

/**
 * Handle GEO score column sorting.
 *
 * @param \WP_Query $query The query object.
 * @return void
 */
function handle_geo_score_sorting( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( 'geo_score' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', '_geo_score' );
        $query->set( 'orderby', 'meta_value_num' );
    }
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\handle_geo_score_sorting' );

/**
 * Add admin styles for GEO score column.
 *
 * @return void
 */
function add_admin_styles() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'edit-page' ), true ) ) {
        return;
    }

    echo '<style>
        .geo-score { display: inline-block; padding: 3px 8px; border-radius: 3px; font-weight: 600; }
        .geo-score--high { background: #d4edda; color: #155724; }
        .geo-score--medium { background: #fff3cd; color: #856404; }
        .geo-score--low { background: #f8d7da; color: #721c24; }
        .geo-score--none { color: #999; }
        .column-geo_score { width: 80px; text-align: center; }
    </style>';
}

add_action( 'admin_head', __NAMESPACE__ . '\\add_admin_styles' );

/**
 * Register the GEO Suggest AnswerCards plugin script and style.
 *
 * @return void
 */
function register_suggest_plugin_assets() {
    $asset_file = __DIR__ . '/build/suggest-plugin.asset.php';
    
    if ( ! file_exists( $asset_file ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[KHM GEO] suggest-plugin.asset.php not found at ' . $asset_file );
        }
        return;
    }
    
    $asset_data = include $asset_file;
    
    $script_url = plugins_url( 'build/suggest-plugin.js', __FILE__ );
    $style_url = plugins_url( 'build/suggest-plugin.css', __FILE__ );
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[KHM GEO] Registering suggest plugin script: ' . $script_url );
        error_log( '[KHM GEO] Asset data: ' . print_r( $asset_data, true ) );
    }
    
    wp_register_script(
        'khm-geo-suggest-plugin',
        $script_url,
        $asset_data['dependencies'] ?? array(),
        $asset_data['version'] ?? '1.0.0',
        true
    );
    
    wp_register_style(
        'khm-geo-suggest-plugin',
        $style_url,
        array(),
        $asset_data['version'] ?? '1.0.0'
    );
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[KHM GEO] Suggest plugin assets registered successfully' );
    }
}
add_action( 'init', __NAMESPACE__ . '\\register_suggest_plugin_assets' );

/**
 * Enqueue the GEO Suggest AnswerCards plugin on post editor screens.
 *
 * @return void
 */
function enqueue_suggest_plugin() {
    // Only enqueue on post editor screens
    $screen = get_current_screen();
    $current_action = current_action();
    
    // Add a debug script to the page to confirm this function runs
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        echo '<script>console.log("[KHM GEO DEBUG] enqueue_suggest_plugin function called, hook: ' . esc_js($current_action) . '");</script>';
    }
    
    // Debug logging
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[KHM GEO] enqueue_suggest_plugin called, screen: ' . ( $screen ? $screen->id : 'null' ) . ', hook: ' . $current_action );
        if ( $screen ) {
            error_log( '[KHM GEO] Screen properties: id=' . $screen->id . ', base=' . $screen->base . ', is_block_editor=' . ( method_exists( $screen, 'is_block_editor' ) ? ( $screen->is_block_editor() ? 'true' : 'false' ) : 'method_not_exists' ) );
        }
    }
    
    // Special handling for enqueue_block_editor_assets hook
    if ( $current_action === 'enqueue_block_editor_assets' ) {
        // If we're in the block editor enqueue hook, assume it's a valid editor screen
        $is_editor_screen = true;
    } else {
        // For other hooks, check screen properties
        $is_editor_screen = false;
        if ( $screen ) {
            // Exclude specific admin pages that are not editors
            if ( in_array( $screen->id, array( 'khm-seo_page_khm-seo-geo-post', 'khm-seo-geo-post' ), true ) ) {
                $is_editor_screen = false;
            } else {
                // Check for various editor screen patterns
                $is_editor_screen = in_array( $screen->id, array( 'post', 'page', 'toplevel_page_content', 'edit-post' ), true ) ||
                                   strpos( $screen->id, 'post' ) !== false ||
                                   strpos( $screen->base, 'post' ) !== false ||
                                   ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() );
            }
        }
    }
    
    if ( ! $is_editor_screen ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[KHM GEO] Not an editor screen, skipping enqueue' );
        }
        return;
    }
    
    // Check if script is registered
    if ( ! wp_script_is( 'khm-geo-suggest-plugin', 'registered' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[KHM GEO] Script khm-geo-suggest-plugin not registered, registering now' );
            echo '<script>console.log("[KHM GEO DEBUG] Registering script...");</script>';
        }
        register_suggest_plugin_assets();
        
        // Check again after registration
        if ( ! wp_script_is( 'khm-geo-suggest-plugin', 'registered' ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[KHM GEO] Script registration failed' );
                echo '<script>console.log("[KHM GEO DEBUG] Script registration failed");</script>';
            }
            return;
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                echo '<script>console.log("[KHM GEO DEBUG] Script registration successful");</script>';
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            echo '<script>console.log("[KHM GEO DEBUG] Script already registered");</script>';
        }
    }
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        echo '<script>console.log("[KHM GEO DEBUG] About to enqueue scripts...");</script>';
    }
    
    wp_enqueue_script( 'khm-geo-suggest-plugin' );
    wp_enqueue_style( 'khm-geo-suggest-plugin' );
    
    // Add debug output to confirm enqueuing
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        echo '<script>console.log("[KHM GEO DEBUG] Scripts enqueued successfully");</script>';
    }
    
    // Determine the current post ID in the editor context.
    $post_id = 0;
    global $post;
    if ( $post instanceof \WP_Post ) {
        $post_id = $post->ID;
    } elseif ( isset( $screen->post ) && $screen->post instanceof \WP_Post ) {
        $post_id = $screen->post->ID;
    } elseif ( isset( $_GET['post_id'] ) && is_numeric( $_GET['post_id'] ) ) {
        $post_id = intval( $_GET['post_id'] );
    } elseif ( isset( $_GET['post'] ) && is_numeric( $_GET['post'] ) ) {
        $post_id = intval( $_GET['post'] );
    }
    
    // Localize script with API endpoint and nonce
    wp_localize_script( 'khm-geo-suggest-plugin', 'khmGeoSuggest', array(
        'apiUrl' => rest_url( 'khm-geo/v1/suggest-answercards' ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
        'postId' => $post_id,
        'strings' => array(
            'title' => __( 'GEO AnswerCards', 'khm-membership' ),
            'suggestButton' => __( 'Suggest AnswerCards', 'khm-membership' ),
            'generating' => __( 'Generating suggestions...', 'khm-membership' ),
            'insert' => __( 'Insert Selected', 'khm-membership' ),
            'cancel' => __( 'Cancel', 'khm-membership' ),
            'error' => __( 'Error generating suggestions', 'khm-membership' ),
        ),
    ) );
    
    // Add JavaScript to handle Boost Visibility GEO button clicks
    wp_add_inline_script( 'khm-geo-suggest-plugin', '
        document.addEventListener("DOMContentLoaded", function() {
            document.addEventListener("click", function(e) {
                if (e.target && e.target.classList.contains("khm-geo-suggestions-btn")) {
                    e.preventDefault();
                    // Dispatch custom event to open GEO suggestions modal
                    window.dispatchEvent(new CustomEvent("khmGeoOpenSuggestions"));
                }
            });
        });
    ' );
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[KHM GEO] Suggest plugin enqueued on editor screen, post_id: ' . $post_id . ', screen: ' . ( $screen ? $screen->id : 'null' ) );
    }
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_suggest_plugin' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_suggest_plugin' );
add_action( 'current_screen', __NAMESPACE__ . '\\enqueue_suggest_plugin' );