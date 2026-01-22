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
 * Ensure frontend assets load even if block.json enqueue is skipped.
 */
function enqueue_answercard_frontend_assets() {
    if ( is_admin() ) {
        return;
    }

    if ( ! function_exists( 'has_block' ) ) {
        return;
    }

    $post = get_post();
    if ( ! $post || ! has_block( 'khm/answer-card', $post ) ) {
        return;
    }

    $asset_path = __DIR__ . '/build/view.asset.php';
    $asset = file_exists( $asset_path ) ? include $asset_path : array( 'dependencies' => array(), 'version' => null );
    $version = $asset['version'] ?? filemtime( __DIR__ . '/build/view.js' );
    $css_version = filemtime( __DIR__ . '/build/view.css' );

    wp_enqueue_style( 'dashicons' );
    wp_enqueue_script(
        'khm-answer-card-view',
        plugins_url( 'build/view.js', __FILE__ ),
        $asset['dependencies'] ?? array(),
        $version,
        true
    );

    wp_enqueue_style(
        'khm-answer-card-view',
        plugins_url( 'build/view.css', __FILE__ ),
        array(),
        $css_version
    );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_answercard_frontend_assets' );

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
    $evidence    = isset( $attributes['evidence'] ) && is_array( $attributes['evidence'] ) ? $attributes['evidence'] : array();

    $topic_meta = isset( $attributes['topicDiscussedAt'] ) && is_array( $attributes['topicDiscussedAt'] )
        ? $attributes['topicDiscussedAt']
        : array();
    $meta_title = isset( $topic_meta['title'] ) ? sanitize_text_field( $topic_meta['title'] ) : '';
    $meta_url = isset( $topic_meta['url'] ) ? esc_url_raw( $topic_meta['url'] ) : '';
    $meta_author = isset( $topic_meta['author_name'] ) ? sanitize_text_field( $topic_meta['author_name'] ) : '';
    if ( ! $meta_author && isset( $topic_meta['author'] ) ) {
        $meta_author = sanitize_text_field( $topic_meta['author'] );
    }
    $meta_publisher = isset( $topic_meta['publisher'] ) ? sanitize_text_field( $topic_meta['publisher'] ) : '';
    $meta_date = isset( $topic_meta['date'] ) ? sanitize_text_field( $topic_meta['date'] ) : '';

    $answer_card_id = isset( $attributes['answerCardId'] ) ? sanitize_text_field( $attributes['answerCardId'] ) : '';
    $modal_id = $answer_card_id ? 'khm-answer-card-modal-' . $answer_card_id : 'khm-answer-card-modal-' . uniqid();
    $meta_id = $modal_id . '-meta';
    $post_id = function_exists( 'get_the_ID' ) ? get_the_ID() : 0;
    $rest_nonce = is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '';
    $rest_root = function_exists( 'rest_url' ) ? rest_url() : '';
    $share_nonce = is_user_logged_in() ? wp_create_nonce( 'khm_library_nonce' ) : '';
    $ajax_url = function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '';
    $login_url = function_exists( 'wp_login_url' ) ? wp_login_url( get_permalink() ) : '';
    $bookmark_icon = function_exists( 'plugins_url' ) ? plugins_url( 'social-strip/assets/bookmark.png' ) : '';

    // Build HTML output
    $html  = '<section class="khm-answer-card" role="region" aria-label="' . esc_attr( $question ) . '">';
    $html .= '<div class="khm-answer-card__trigger">';
    $html .= '<span class="khm-answer-card__divider" aria-hidden="true"></span>';
    $html .= '<button type="button" class="khm-answer-card__toggle" aria-expanded="false" aria-controls="' . esc_attr( $modal_id ) . '">';
    $html .= '<span class="khm-answer-card__toggle-icon" aria-hidden="true"></span>';
    $html .= esc_html__( 'Section Summary', 'khm-membership' );
    $html .= '</button>';
    $html .= '</div>';

    $html .= '<div id="' . esc_attr( $modal_id ) . '" class="khm-answer-card__modal khm-modal-backdrop" aria-hidden="true">';
    $html .= '<div class="khm-modal khm-modal large khm-answer-card__modal-card" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $modal_id ) . '-title">';
    $html .= '<div class="khm-modal-header">';
    $html .= '<div class="khm-answer-card__header-text">';
    $html .= '<span class="khm-answer-card__eyebrow" style="display:block;line-height:1;margin-bottom:-12px;">' . esc_html__( 'Section Summary', 'khm-membership' ) . '</span>';
    if ( ! empty( $attributes['sponsorToggle'] ) ) {
        $html .= '<span class="khm-answer-card__sponsor-badge">' . esc_html__( 'Sponsored', 'khm-membership' ) . '</span>';
    }
    $html .= '<h3 id="' . esc_attr( $modal_id ) . '-title" class="khm-modal-title" style="line-height:1.2;margin:0;padding-top:0;">' . $question . '</h3>';
    $html .= '</div>';
    $html .= '<button type="button" class="khm-modal-close khm-answer-card__modal-close" aria-label="' . esc_attr__( 'Close', 'khm-membership' ) . '">&times;</button>';
    $html .= '</div>';
    $html .= '<div class="khm-modal-content">';
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
        $tag_names = array();
        foreach ( $entities as $entity ) {
            $entity_name = is_array( $entity ) && isset( $entity['name'] ) ? $entity['name'] : $entity;
            if ( $entity_name ) {
                $tag_names[] = esc_html( html_entity_decode( $entity_name, ENT_QUOTES, 'UTF-8' ) );
            }
        }

        if ( ! empty( $tag_names ) ) {
            $html .= '<div class="khm-answer-card__entities">';
            $html .= '<p class="khm-answer-card__tags inline-keywords"><span class="khm-answer-card__tags-label">' . esc_html__( 'Relates to:', 'khm-membership' ) . '</span> ' . implode( ', ', $tag_names ) . '</p>';
            $html .= '</div>';
        }
    }

    $html .= '<div class="khm-answer-card__actions">';
    $html .= '<button type="button" class="khm-answer-card__share" data-post-id="' . esc_attr( $post_id ) . '" data-answer-card-question="' . esc_attr( $question ) . '" data-ajax-url="' . esc_url( $ajax_url ) . '" data-share-nonce="' . esc_attr( $share_nonce ) . '" data-login-url="' . esc_url( $login_url ) . '" title="' . esc_attr__( 'Share section summary', 'khm-membership' ) . '" aria-label="' . esc_attr__( 'Share section summary', 'khm-membership' ) . '">';
    $html .= '<span class="dashicons dashicons-email" aria-hidden="true"></span>';
    $html .= '<span class="khm-answer-card__share-label">' . esc_html__( 'Share summary', 'khm-membership' ) . '</span>';
    $html .= '</button>';
    $html .= '</div>';

    $html .= '<button type="button" class="khm-answer-card__meta-toggle" aria-expanded="false" aria-controls="' . esc_attr( $meta_id ) . '">';
    $html .= '<span class="khm-answer-card__triangle" aria-hidden="true"></span>';
    $html .= '<span class="khm-answer-card__meta-label">' . esc_html__( 'Show meta', 'khm-membership' ) . '</span>';
    $html .= '</button>';
    $html .= '<div id="' . esc_attr( $meta_id ) . '" class="khm-answer-card__meta" hidden>';
    if ( $meta_title ) {
        $html .= '<p><strong>' . esc_html__( 'Title:', 'khm-membership' ) . '</strong> ' . esc_html( $meta_title ) . '</p>';
    }
    if ( $meta_url ) {
        $html .= '<p><strong>' . esc_html__( 'URL:', 'khm-membership' ) . '</strong> <a href="' . esc_url( $meta_url ) . '">' . esc_html( $meta_url ) . '</a></p>';
    }
    if ( $meta_author ) {
        $html .= '<p><strong>' . esc_html__( 'Author:', 'khm-membership' ) . '</strong> ' . esc_html( $meta_author ) . '</p>';
    }
    if ( $meta_publisher ) {
        $html .= '<p><strong>' . esc_html__( 'Publisher:', 'khm-membership' ) . '</strong> ' . esc_html( $meta_publisher ) . '</p>';
    }
    if ( $meta_date ) {
        $html .= '<p><strong>' . esc_html__( 'Date:', 'khm-membership' ) . '</strong> ' . esc_html( $meta_date ) . '</p>';
    }
    $html .= '</div>';

    $html .= '<button type="button" class="khm-answer-card__save khm-answer-card__save--floating" data-post-id="' . esc_attr( $post_id ) . '" data-answer-card-id="' . esc_attr( $answer_card_id ) . '" data-answer-card-question="' . esc_attr( $question ) . '" data-rest-nonce="' . esc_attr( $rest_nonce ) . '" data-login-url="' . esc_url( $login_url ) . '" data-rest-root="' . esc_url( $rest_root ) . '" title="' . esc_attr__( 'Save to library', 'khm-membership' ) . '">';
    if ( $bookmark_icon ) {
        $html .= '<img src="' . esc_url( $bookmark_icon ) . '" alt="" aria-hidden="true">';
    }
    $html .= '<span class="khm-answer-card__save-label">' . esc_html__( 'Save to library', 'khm-membership' ) . '</span>';
    $html .= '</button>';

    $html .= '</div>'; // modal content
    $html .= '</div>'; // modal card
    $html .= '</div>'; // modal backdrop

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
    $existing_cards = get_post_meta( $post_id, '_geo_answercards', true );
    $existing_by_id = array();
    if ( is_array( $existing_cards ) ) {
        foreach ( $existing_cards as $existing_card ) {
            if ( ! empty( $existing_card['answer_card_id'] ) ) {
                $existing_by_id[ $existing_card['answer_card_id'] ] = $existing_card;
            }
        }
    }
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

        $review_justification = isset( $attrs['reviewJustification'] ) ? sanitize_text_field( $attrs['reviewJustification'] ) : '';
        if ( $requires_review && empty( $review_justification ) ) {
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

        $public_summary_label = isset( $attrs['publicSummaryLabel'] ) ? sanitize_text_field( $attrs['publicSummaryLabel'] ) : '';

        // Ensure topic_discussed_at includes related metadata for internal use
        $topic_discussed_at['site_keywords'] = $site_keywords;
        $topic_discussed_at['public_summary_label'] = $public_summary_label;

        $existing_card = isset( $existing_by_id[ $answer_card_id ] ) ? $existing_by_id[ $answer_card_id ] : array();
        if ( empty( $evidence['source_passage'] ) && ! empty( $existing_card['evidence']['source_passage'] ) ) {
            $evidence['source_passage'] = $existing_card['evidence']['source_passage'];
        }

        $preferred_summary = isset( $attrs['preferredSummary'] ) ? sanitize_text_field( $attrs['preferredSummary'] ) : '';
        if ( empty( $preferred_summary ) && ! empty( $existing_card['preferred_summary'] ) ) {
            $preferred_summary = sanitize_text_field( $existing_card['preferred_summary'] );
        }

        $generation_override = isset( $attrs['generationOverride'] )
            ? (bool) $attrs['generationOverride']
            : ( ! empty( $existing_card['generation_override'] ) );
        $generation_override_note = isset( $attrs['generationOverrideNote'] )
            ? sanitize_text_field( $attrs['generationOverrideNote'] )
            : ( $existing_card['generation_override_note'] ?? '' );

        $sponsor_toggle = isset( $attrs['sponsorToggle'] )
            ? (bool) $attrs['sponsorToggle']
            : ( ! empty( $existing_card['sponsor_toggle'] ) );
        $sponsor_id = isset( $attrs['sponsorId'] )
            ? absint( $attrs['sponsorId'] )
            : absint( $existing_card['sponsor_id'] ?? 0 );
        $sponsor_name = isset( $attrs['sponsorName'] )
            ? sanitize_text_field( $attrs['sponsorName'] )
            : sanitize_text_field( $existing_card['sponsor_name'] ?? '' );
        $sponsor_url = isset( $attrs['sponsorUrl'] )
            ? esc_url_raw( $attrs['sponsorUrl'] )
            : esc_url_raw( $existing_card['sponsor_url'] ?? '' );
        $sponsor_boost = isset( $attrs['sponsorBoost'] )
            ? floatval( $attrs['sponsorBoost'] )
            : floatval( $existing_card['sponsor_boost'] ?? 0.0 );
        $sponsor_requires_approval = isset( $attrs['sponsorRequiresApproval'] )
            ? (bool) $attrs['sponsorRequiresApproval']
            : (bool) ( $existing_card['sponsor_requires_approval'] ?? true );
        $sponsor_approved = isset( $attrs['sponsorApproved'] )
            ? (bool) $attrs['sponsorApproved']
            : (bool) ( $existing_card['sponsor_approved'] ?? false );
        $sponsor_justification = isset( $attrs['sponsorJustification'] )
            ? sanitize_text_field( $attrs['sponsorJustification'] )
            : sanitize_text_field( $existing_card['sponsor_justification'] ?? '' );
        $sponsor_doc_ids = isset( $attrs['sponsorDocIds'] ) && is_array( $attrs['sponsorDocIds'] )
            ? array_map( 'absint', $attrs['sponsorDocIds'] )
            : ( $existing_card['sponsor_doc_ids'] ?? array() );
        $citation_ordering = isset( $attrs['citationOrdering'] )
            ? sanitize_text_field( $attrs['citationOrdering'] )
            : sanitize_text_field( $existing_card['citation_ordering'] ?? '' );

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
            'preferred_summary'    => $preferred_summary,
            'public_summary_label' => $public_summary_label,
            'expose_in_schema'     => $expose_in_schema,
            'requires_review'      => $requires_review,
            'review_justification' => $review_justification,
            'generation_override'  => $generation_override,
            'generation_override_note' => $generation_override_note,
            'sponsor_toggle'       => $sponsor_toggle,
            'sponsor_id'           => $sponsor_id,
            'sponsor_name'         => $sponsor_name,
            'sponsor_url'          => $sponsor_url,
            'sponsor_boost'        => max( 0, min( 0.1, $sponsor_boost ) ),
            'sponsor_requires_approval' => $sponsor_requires_approval,
            'sponsor_approved'     => $sponsor_approved,
            'sponsor_justification'=> $sponsor_justification,
            'sponsor_doc_ids'      => $sponsor_doc_ids,
            'citation_ordering'    => $citation_ordering,
            'position'             => $position++,
            'updated_at'           => current_time( 'mysql' ),
        );

        if ( $sponsor_toggle ) {
            $card['sponsor'] = array(
                'id'   => $sponsor_id,
                'name' => $sponsor_name,
                'url'  => $sponsor_url,
            );
        }

        if ( ! empty( $existing_card['generation_status'] ) ) {
            $card['generation_status'] = $existing_card['generation_status'];
        }
        if ( ! empty( $existing_card['generation_reasons'] ) ) {
            $card['generation_reasons'] = $existing_card['generation_reasons'];
        }
        if ( ! empty( $existing_card['audit'] ) ) {
            $card['audit'] = $existing_card['audit'];
        }

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
            $has_tracking_key = array_key_exists( 'enableTracking', $citation ) || array_key_exists( 'enable_tracking', $citation );
            $item = array(
                'title'           => isset( $citation['title'] ) ? sanitize_text_field( $citation['title'] ) : '',
                'url'             => isset( $citation['url'] ) ? esc_url_raw( $citation['url'] ) : '',
                'author'          => isset( $citation['author'] ) ? sanitize_text_field( $citation['author'] ) : '',
                'publisher'       => isset( $citation['publisher'] ) ? sanitize_text_field( $citation['publisher'] ) : '',
                'year'            => isset( $citation['year'] ) ? sanitize_text_field( strval( $citation['year'] ) ) : '',
                'tier'            => isset( $citation['tier'] ) ? sanitize_text_field( $citation['tier'] ) : '',
                'doi'             => isset( $citation['doi'] ) ? sanitize_text_field( $citation['doi'] ) : '',
                'sponsor_id'      => isset( $citation['sponsor_id'] ) ? absint( $citation['sponsor_id'] ) : 0,
                'sponsor_doc_id'  => isset( $citation['sponsor_doc_id'] ) ? absint( $citation['sponsor_doc_id'] ) : 0,
                'sponsor_approved'=> ! empty( $citation['sponsor_approved'] ),
                'keywords'        => isset( $citation['keywords'] ) && is_array( $citation['keywords'] )
                                     ? array_map( 'sanitize_text_field', $citation['keywords'] )
                                     : array(),
                'enable_tracking' => $has_tracking_key
                                     ? ( ! empty( $citation['enableTracking'] ) || ! empty( $citation['enable_tracking'] ) )
                                     : true,
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
                'enable_tracking' => true,
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
            $same_as = $entity['same_as'] ?? ( $entity['sameAs'] ?? '' );
            $resolved_by = $entity['resolved_by'] ?? ( $entity['resolvedBy'] ?? '' );
            $resolved_confidence = $entity['resolved_confidence'] ?? ( $entity['resolvedConfidence'] ?? '' );
            $resolved_at = $entity['resolved_at'] ?? ( $entity['resolvedAt'] ?? '' );
            $resolved_method = $entity['resolved_method'] ?? ( $entity['resolvedMethod'] ?? '' );
            $sanitized[] = array(
                'name'   => isset( $entity['name'] ) ? sanitize_text_field( $entity['name'] ) : '',
                'same_as' => $same_as ? esc_url_raw( $same_as ) : '',
                'resolved_by' => $resolved_by ? sanitize_text_field( $resolved_by ) : '',
                'resolved_confidence' => $resolved_confidence !== '' ? floatval( $resolved_confidence ) : null,
                'resolved_at' => $resolved_at ? sanitize_text_field( $resolved_at ) : '',
                'resolved_method' => $resolved_method ? sanitize_text_field( $resolved_method ) : '',
            );
        } elseif ( is_string( $entity ) ) {
            $sanitized[] = array(
                'name'   => sanitize_text_field( $entity ),
                'same_as' => '',
                'resolved_by' => '',
                'resolved_confidence' => null,
                'resolved_at' => '',
                'resolved_method' => '',
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
    $context_heading = '';
    if ( isset( $evidence['contextHeading'] ) ) {
        $context_heading = $evidence['contextHeading'];
    } elseif ( isset( $evidence['context_heading'] ) ) {
        $context_heading = $evidence['context_heading'];
    }

    $source_passage = '';
    if ( isset( $evidence['sourcePassage'] ) ) {
        $source_passage = $evidence['sourcePassage'];
    } elseif ( isset( $evidence['source_passage'] ) ) {
        $source_passage = $evidence['source_passage'];
    }

    $anchor_entities = array();
    if ( isset( $evidence['anchorEntities'] ) && is_array( $evidence['anchorEntities'] ) ) {
        $anchor_entities = $evidence['anchorEntities'];
    } elseif ( isset( $evidence['anchor_entities'] ) && is_array( $evidence['anchor_entities'] ) ) {
        $anchor_entities = $evidence['anchor_entities'];
    }

    return array(
        'tier'            => isset( $evidence['tier'] ) ? sanitize_text_field( $evidence['tier'] ) : '',
        'confidence'      => isset( $evidence['confidence'] ) ? floatval( $evidence['confidence'] ) : 0.0,
        'context_heading' => $context_heading ? sanitize_text_field( $context_heading ) : '',
        'source_passage'  => $source_passage ? sanitize_text_field( $source_passage ) : '',
        'anchor_entities' => ! empty( $anchor_entities )
                             ? array_map( 'sanitize_text_field', $anchor_entities )
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

    // Get post author name (prefer lead author from Multiple Authors)
    $author_name    = '';
    $author_id      = 0;
    $wp_author_name = '';
    $wp_author_id   = 0;
    if ( $post && $post->post_author ) {
        $author = get_userdata( $post->post_author );
        if ( $author ) {
            $wp_author_name = $author->display_name;
            $wp_author_id   = $author->ID;
        }
    }
    if ( $post_id ) {
        $lead_author = get_lead_author_identity( $post_id );
        if ( ! empty( $lead_author['name'] ) ) {
            $author_name = $lead_author['name'];
            $author_id   = $lead_author['user_id'] ?? 0;
        }
    }

    // Get site name as publisher
    $publisher = get_bloginfo( 'name' );

    $raw_date = isset( $topic_discussed_at['date'] ) && ! empty( $topic_discussed_at['date'] )
        ? sanitize_text_field( $topic_discussed_at['date'] )
        : $post_date;

    $date_format = get_option( 'khm_geo_date_format', 'd/m/Y' );
    $date_iso    = normalize_geo_date_to_iso( $raw_date, $date_format );

    $current_author = isset( $topic_discussed_at['author_name'] ) && ! empty( $topic_discussed_at['author_name'] )
        ? sanitize_text_field( $topic_discussed_at['author_name'] )
        : ( isset( $topic_discussed_at['author'] ) && ! empty( $topic_discussed_at['author'] )
            ? sanitize_text_field( $topic_discussed_at['author'] )
            : '' );
    $current_author_id = isset( $topic_discussed_at['author_id'] ) ? absint( $topic_discussed_at['author_id'] ) : 0;
    $should_use_lead = ! empty( $author_name ) && (
        empty( $current_author ) ||
        ( $wp_author_name && $current_author === $wp_author_name ) ||
        ( $wp_author_id && $current_author_id === $wp_author_id )
    );

    return array(
        'url'       => isset( $topic_discussed_at['url'] ) && ! empty( $topic_discussed_at['url'] )
                       ? esc_url_raw( $topic_discussed_at['url'] )
                       : $post_url,
        'title'     => isset( $topic_discussed_at['title'] ) && ! empty( $topic_discussed_at['title'] )
                       ? sanitize_text_field( $topic_discussed_at['title'] )
                       : $post_title,
        'author'    => isset( $topic_discussed_at['author'] ) && ! empty( $topic_discussed_at['author'] )
                       ? sanitize_text_field( $topic_discussed_at['author'] )
                       : ( $should_use_lead ? $author_name : ( isset( $topic_discussed_at['author_name'] ) && ! empty( $topic_discussed_at['author_name'] )
                           ? sanitize_text_field( $topic_discussed_at['author_name'] )
                           : $author_name ) ),
        'author_name' => isset( $topic_discussed_at['author_name'] ) && ! empty( $topic_discussed_at['author_name'] )
                         ? sanitize_text_field( $topic_discussed_at['author_name'] )
                         : ( $should_use_lead ? $author_name : ( isset( $topic_discussed_at['author'] ) && ! empty( $topic_discussed_at['author'] )
                             ? sanitize_text_field( $topic_discussed_at['author'] )
                             : $author_name ) ),
        'author_id' => $should_use_lead
                       ? $author_id
                       : ( isset( $topic_discussed_at['author_id'] ) ? absint( $topic_discussed_at['author_id'] ) : $author_id ),
        'publisher' => isset( $topic_discussed_at['publisher'] ) && ! empty( $topic_discussed_at['publisher'] )
                       ? sanitize_text_field( $topic_discussed_at['publisher'] )
                       : $publisher,
        'date'      => $date_iso,
        'note'      => isset( $topic_discussed_at['note'] )
                       ? sanitize_text_field( $topic_discussed_at['note'] )
                       : '',
        'site_keywords' => isset( $topic_discussed_at['site_keywords'] ) && is_array( $topic_discussed_at['site_keywords'] )
                           ? array_values( array_filter( array_map( 'sanitize_text_field', $topic_discussed_at['site_keywords'] ) ) )
                           : array(),
        'public_summary_label' => isset( $topic_discussed_at['public_summary_label'] )
                                  ? sanitize_text_field( $topic_discussed_at['public_summary_label'] )
                                  : '',
    );
}

/**
 * Resolve lead author identity from the Multiple Authors plugin.
 *
 * @param int $post_id Post ID.
 * @return array{name:string,user_id:int}
 */
function get_lead_author_identity( $post_id ) {
    $author_name = '';
    $author_id   = 0;

    if ( function_exists( 'kh_get_post_authors' ) ) {
        $authors = kh_get_post_authors( $post_id );
        if ( ! empty( $authors ) && is_array( $authors ) ) {
            $primary = $authors[0];
            $author_post = is_object( $primary ) ? $primary : ( is_numeric( $primary ) ? get_post( (int) $primary ) : null );
            if ( $author_post ) {
                if ( function_exists( 'get_field' ) ) {
                    $author_name = get_field( 'author_name', $author_post->ID );
                }
                if ( ! $author_name ) {
                    $author_name = get_post_meta( $author_post->ID, 'author_name', true );
                }
                if ( ! $author_name ) {
                    $author_name = get_the_title( $author_post->ID );
                }

                if ( $author_name ) {
                    $user = get_user_by( 'display_name', $author_name );
                    if ( ! $user ) {
                        $login = sanitize_title( $author_name );
                        if ( $login ) {
                            $user = get_user_by( 'login', $login );
                        }
                    }
                    if ( $user ) {
                        $author_id = $user->ID;
                    }
                }
            }
        }
    }

    if ( ! $author_name ) {
        $post = get_post( $post_id );
        if ( $post && $post->post_author ) {
            $author = get_userdata( $post->post_author );
            if ( $author ) {
                $author_name = $author->display_name;
                $author_id   = $author->ID;
            }
        }
    }

    return array(
        'name'    => $author_name,
        'user_id' => $author_id,
    );
}

/**
 * Normalize a GEO date string to ISO format (YYYY-MM-DD).
 *
 * @param string $raw_date Raw date input from editor.
 * @param string $format   Expected display format (WordPress date format).
 * @return string ISO date or empty string.
 */
function normalize_geo_date_to_iso( $raw_date, $format ) {
    $raw_date = trim( (string) $raw_date );
    if ( empty( $raw_date ) ) {
        return '';
    }

    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ) {
        return $raw_date;
    }

    $timezone = wp_timezone();
    $date     = \DateTime::createFromFormat( $format, $raw_date, $timezone );
    if ( $date instanceof \DateTime ) {
        return $date->format( 'Y-m-d' );
    }

    $fallback = \DateTime::createFromFormat( 'Y-m-d', $raw_date, $timezone );
    if ( $fallback instanceof \DateTime ) {
        return $fallback->format( 'Y-m-d' );
    }

    return '';
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
        error_log( '[KHM GEO ERROR] Scoring engine unavailable for post ' . $post_id );
        delete_post_meta( $post_id, '_geo_score' );
        update_post_meta( $post_id, '_geo_score_details', array(
            'status' => 'unavailable',
            'error'  => 'Scoring engine unavailable',
        ) );
        return array(
            'status' => 'unavailable',
        );
    }

    try {
        $scoring_engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();
        $page_scores    = array();
        $total_scores   = array();

        foreach ( $canonical as $card ) {
            $context    = array( 'post_id' => $post_id );
            $score_settings = normalize_scoring_settings( $card );
            $score_data = $scoring_engine->calculate_score( $score_settings, $context );

            $page_scores[] = array(
                'card'       => $card,
                'score_data' => $score_data,
            );

            $total_scores[] = isset( $score_data['total_score'] ) ? floatval( $score_data['total_score'] ) : 0.0;
        }

        // Set expose_in_schema=false for cards below confidence threshold
        foreach ( $page_scores as &$score_item ) {
            $card     = $score_item['card'];
            $evidence = $card['evidence'] ?? array();
            if ( ! empty( $evidence['confidence'] ) && $evidence['confidence'] < 0.6 ) {
                if ( empty( $card['review_justification'] ) ) {
                    $score_item['card']['expose_in_schema'] = false;
                }
            }
        }

        // Calculate composite score (average across all cards)
        $composite_total = 0.0;
        if ( count( $total_scores ) > 0 ) {
            $composite_total = array_sum( $total_scores ) / count( $total_scores );
        }

        update_post_meta( $post_id, '_geo_score', $composite_total );
        update_post_meta( $post_id, '_geo_score_details', $page_scores );

        return array(
            'score'   => $composite_total,
            'details' => $page_scores,
        );
    } catch ( \Exception $e ) {
        error_log( '[KHM GEO ERROR] Scoring failed for post ' . $post_id . ': ' . $e->getMessage() );
        delete_post_meta( $post_id, '_geo_score' );
        update_post_meta( $post_id, '_geo_score_details', array(
            'status' => 'error',
            'error'  => $e->getMessage(),
        ) );
        return array(
            'status' => 'error',
            'error'  => $e->getMessage(),
        );
    }
}

/**
 * Normalize a card into scoring settings expected by ScoringEngine.
 *
 * @param array $card Canonical card data.
 * @return array
 */
function normalize_scoring_settings( $card ) {
    $evidence = $card['evidence'] ?? array();
    $confidence = isset( $evidence['confidence'] ) ? floatval( $evidence['confidence'] ) : 0.5;

    return array(
        'question'         => $card['question'] ?? '',
        'answer'           => $card['concise_answer'] ?? '',
        'bullets'          => $card['key_points'] ?? array(),
        'citations'        => $card['citations'] ?? array(),
        'entities'         => $card['entities'] ?? array(),
        'evidence'         => $evidence,
        'confidence_score' => $confidence,
        'sponsor_toggle'   => ! empty( $card['sponsor_toggle'] ),
        'sponsor_boost'    => isset( $card['sponsor_boost'] ) ? floatval( $card['sponsor_boost'] ) : 0.0,
    );
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
 * Check if a card should be exposed in JSON-LD schema.
 *
 * @param array $card Card data.
 * @return bool
 */
function can_expose_card_in_schema( $card, $post_id = 0 ) {
    if ( empty( $card['expose_in_schema'] ) ) {
        return false;
    }

    if ( ! empty( $card['requires_review'] ) && empty( $card['review_justification'] ) ) {
        return false;
    }

    if ( is_geo_auto_approved( $card, $post_id ) ) {
        return true;
    }

    if ( ! empty( $card['generation_override'] ) && ! empty( $card['generation_override_note'] ) ) {
        return true;
    }

    return false;
}

/**
 * Determine if sponsor metadata can be exposed in JSON-LD.
 *
 * @param array $card Card data.
 * @param int   $post_id Post ID.
 * @return bool
 */
function can_expose_sponsor_in_schema( $card, $post_id = 0 ) {
    if ( empty( $card['sponsor_toggle'] ) ) {
        return false;
    }

    if ( ! can_expose_card_in_schema( $card, $post_id ) ) {
        return false;
    }

    if ( ! empty( $card['sponsor_requires_approval'] ) && empty( $card['sponsor_approved'] ) ) {
        return false;
    }

    if ( is_geo_auto_approved( $card, $post_id ) ) {
        return true;
    }

    if ( ! empty( $card['sponsor_justification'] ) ) {
        return true;
    }

    return false;
}

/**
 * Determine if a card meets Phase 3 auto-approval criteria.
 *
 * @param array $card Card data.
 * @param int   $post_id Post ID.
 * @return bool
 */
function is_geo_auto_approved( $card, $post_id = 0 ) {
    $confidence = isset( $card['evidence']['confidence'] ) ? floatval( $card['evidence']['confidence'] ) : 0.0;
    $score_details = array();
    $score_data = array();
    if ( $post_id ) {
        $score_details = get_post_meta( $post_id, '_geo_score_details', true );
        if ( is_string( $score_details ) ) {
            $decoded = json_decode( $score_details, true );
            if ( is_array( $decoded ) ) {
                $score_details = $decoded;
            }
        }
    }

    if ( isset( $score_details['total_score'] ) || isset( $score_details['reasons'] ) ) {
        $score_data = $score_details;
    } elseif ( is_array( $score_details ) ) {
        $card_id = $card['answer_card_id'] ?? '';
        foreach ( $score_details as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            if ( isset( $item['score_data'] ) && is_array( $item['score_data'] ) ) {
                $item_card = $item['card']['answer_card_id'] ?? '';
                if ( $card_id && $item_card === $card_id ) {
                    $score_data = $item['score_data'];
                    break;
                }
                if ( empty( $score_data ) ) {
                    $score_data = $item['score_data'];
                }
            }
        }
    }

    $geo_score = isset( $score_data['total_score'] ) ? floatval( $score_data['total_score'] ) : 0.0;
    $reasons = isset( $score_data['reasons'] ) && is_array( $score_data['reasons'] ) ? $score_data['reasons'] : array();
    $blocking_codes = array( 'missing_author', 'no_source_passage', 'hallucination' );

    foreach ( $reasons as $reason ) {
        if ( ! empty( $reason['code'] ) && in_array( $reason['code'], $blocking_codes, true ) ) {
            return false;
        }
    }

    if ( $confidence >= 0.85 && $geo_score >= 0.8 ) {
        return true;
    }

    return false;
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
        if ( ! can_expose_card_in_schema( $card, $post_id ) ) {
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
                    if ( isset( $c['allowed_for_export'] ) && ! $c['allowed_for_export'] ) {
                        continue;
                    }
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
                    $same_as = $entity['same_as'] ?? ( $entity['sameAs'] ?? '' );
                    if ( ! empty( $same_as ) ) {
                        $entity_item['sameAs'] = esc_url_raw( $same_as );
                    }
                } else {
                    $entity_item['name'] = sanitize_text_field( $entity );
                }
                $part['about'][] = $entity_item;
            }
        }

        if ( can_expose_sponsor_in_schema( $card, $post_id ) && ! empty( $card['sponsor'] ) ) {
            $part['sponsor'] = array(
                '@type' => 'Organization',
                'name'  => sanitize_text_field( $card['sponsor']['name'] ?? '' ),
            );
            if ( ! empty( $card['sponsor']['url'] ) ) {
                $part['sponsor']['url'] = esc_url_raw( $card['sponsor']['url'] );
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
            if ( ! can_expose_card_in_schema( $card, $post_id ) ) {
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
                $topic_author = $topic['author_name'] ?? $topic['author'] ?? '';
                if ( ! empty( $topic_author ) ) {
                    $accepted_answer['author'] = array(
                        '@type' => 'Person',
                        'name'  => html_entity_decode( $topic_author, ENT_QUOTES, 'UTF-8' ),
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
                            $same_as = $entity['same_as'] ?? ( $entity['sameAs'] ?? '' );
                            if ( ! empty( $same_as ) ) {
                                $entity_item['sameAs'] = esc_url_raw( $same_as );
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

                if ( can_expose_sponsor_in_schema( $card, $post_id ) && ! empty( $card['sponsor'] ) ) {
                    $faq_item['sponsor'] = array(
                        '@type' => 'Organization',
                        'name'  => sanitize_text_field( $card['sponsor']['name'] ?? '' ),
                    );
                    if ( ! empty( $card['sponsor']['url'] ) ) {
                        $faq_item['sponsor']['url'] = esc_url_raw( $card['sponsor']['url'] );
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
                if ( can_expose_card_in_schema( $card, $post_id ) ) {
                    $topic = $card['topic_discussed_at'] ?? array();
                    $topic_author = $topic['author_name'] ?? $topic['author'] ?? '';
                    if ( ! empty( $topic_author ) ) {
                        $faq_schema['author'] = array(
                            '@type' => 'Person',
                            'name'  => html_entity_decode( $topic_author, ENT_QUOTES, 'UTF-8' ),
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
add_action( 'wp_footer', __NAMESPACE__ . '\\output_answercard_jsonld', 5 );

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
 * WP-CLI command to recompute GEO score and print breakdown.
 */
function cli_score_post( $args, $assoc_args ) {
    if ( empty( $args[0] ) ) {
        \WP_CLI::error( 'Post ID is required.' );
    }

    $post_id = absint( $args[0] );
    $cards = get_post_meta( $post_id, '_geo_answercards', true );

    if ( empty( $cards ) || ! is_array( $cards ) ) {
        \WP_CLI::error( 'No AnswerCards found for this post.' );
    }

    $result = run_scoring_for_post( $post_id, $cards );

    if ( isset( $result['status'] ) && 'error' === $result['status'] ) {
        \WP_CLI::error( $result['error'] ?? 'Scoring failed.' );
    }

    if ( ! empty( $assoc_args['print'] ) ) {
        \WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
        return;
    }

    \WP_CLI::success( 'GEO score recomputed.' );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'khm-geo score', __NAMESPACE__ . '\\cli_score_post' );
}

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
                var target = e.target;
                var button = target && target.closest ? target.closest(".khm-geo-suggestions-btn") : null;
                if (button) {
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
