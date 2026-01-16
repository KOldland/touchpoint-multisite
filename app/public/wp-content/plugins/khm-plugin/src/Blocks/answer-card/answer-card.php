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
    $question    = isset( $attributes['question'] ) ? esc_html( $attributes['question'] ) : '';
    $answer      = isset( $attributes['conciseAnswer'] ) ? wp_kses_post( $attributes['conciseAnswer'] ) : '';
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
            $html .= '<li>' . esc_html( $point ) . '</li>';
        }
        $html .= '</ul>';
    }

    // Citations list
    if ( ! empty( $citations ) ) {
        $html .= '<div class="khm-answer-card__citations">';
        $html .= '<strong>' . esc_html__( 'Sources:', 'khm-membership' ) . '</strong>';
        $html .= '<ul>';
        foreach ( $citations as $citation ) {
            if ( is_array( $citation ) && ! empty( $citation['url'] ) ) {
                $title = ! empty( $citation['title'] ) ? esc_html( $citation['title'] ) : esc_url( $citation['url'] );
                $html .= '<li><a href="' . esc_url( $citation['url'] ) . '" rel="noopener noreferrer">' . $title . '</a></li>';
            } elseif ( is_string( $citation ) ) {
                $html .= '<li><a href="' . esc_url( $citation ) . '" rel="noopener noreferrer">' . esc_url( $citation ) . '</a></li>';
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
        $card = array(
            'question'         => isset( $attrs['question'] ) ? sanitize_text_field( $attrs['question'] ) : '',
            'concise_answer'   => isset( $attrs['conciseAnswer'] ) ? wp_kses_post( $attrs['conciseAnswer'] ) : '',
            'key_points'       => isset( $attrs['keyPoints'] ) && is_array( $attrs['keyPoints'] )
                                    ? array_map( 'sanitize_text_field', $attrs['keyPoints'] )
                                    : array(),
            'citations'        => isset( $attrs['citations'] ) && is_array( $attrs['citations'] )
                                    ? sanitize_citations( $attrs['citations'] )
                                    : array(),
            'entities'         => isset( $attrs['entities'] ) && is_array( $attrs['entities'] )
                                    ? sanitize_entities( $attrs['entities'] )
                                    : array(),
            'expose_in_schema' => isset( $attrs['exposeInSchema'] ) ? (bool) $attrs['exposeInSchema'] : true,
            'position'         => $position++,
            'updated_at'       => current_time( 'mysql' ),
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
 * @param array $citations Raw citations array.
 * @return array Sanitized citations.
 */
function sanitize_citations( $citations ) {
    $sanitized = array();
    foreach ( $citations as $citation ) {
        if ( is_array( $citation ) ) {
            $sanitized[] = array(
                'title' => isset( $citation['title'] ) ? sanitize_text_field( $citation['title'] ) : '',
                'url'   => isset( $citation['url'] ) ? esc_url_raw( $citation['url'] ) : '',
            );
        } elseif ( is_string( $citation ) ) {
            $sanitized[] = array(
                'title' => '',
                'url'   => esc_url_raw( $citation ),
            );
        }
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
        $wpdb->insert(
            $table,
            array(
                'post_id'          => $post_id,
                'question'         => $card['question'],
                'concise_answer'   => $card['concise_answer'],
                'key_points'       => wp_json_encode( $card['key_points'] ),
                'citations'        => wp_json_encode( $card['citations'] ),
                'entities'         => wp_json_encode( $card['entities'] ),
                'expose_in_schema' => $card['expose_in_schema'] ? 1 : 0,
                'position'         => $card['position'],
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
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
    foreach ( $cards as $card ) {
        // Skip cards not exposed in schema
        if ( empty( $card['expose_in_schema'] ) ) {
            continue;
        }

        $part = array(
            '@type'    => 'WebPageElement',
            'name'     => $card['question'],
            'text'     => $card['concise_answer'],
            'position' => isset( $card['position'] ) ? intval( $card['position'] ) : 0,
        );

        // Add citations
        if ( ! empty( $card['citations'] ) && is_array( $card['citations'] ) ) {
            $cits = array();
            foreach ( $card['citations'] as $c ) {
                if ( is_array( $c ) && ! empty( $c['url'] ) ) {
                    $citation_item = array(
                        '@type' => 'CreativeWork',
                        'url'   => esc_url_raw( $c['url'] ),
                    );
                    if ( ! empty( $c['title'] ) ) {
                        $citation_item['name'] = sanitize_text_field( $c['title'] );
                    }
                    $cits[] = $citation_item;
                } elseif ( is_string( $c ) ) {
                    $cits[] = array(
                        '@type' => 'CreativeWork',
                        'url'   => esc_url_raw( $c ),
                    );
                }
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
            'url'      => get_permalink( $post_id ),
            'name'     => get_the_title( $post_id ),
            'hasPart'  => $has_part,
        );

        // Optional: Add FAQPage schema for better SEO
        $faq_items = array();
        foreach ( $cards as $card ) {
            if ( ! empty( $card['expose_in_schema'] ) && ! empty( $card['question'] ) && ! empty( $card['concise_answer'] ) ) {
                $faq_items[] = array(
                    '@type'          => 'Question',
                    'name'           => $card['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text'  => $card['concise_answer'],
                    ),
                );
            }
        }

        if ( ! empty( $faq_items ) ) {
            $faq_schema = array(
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $faq_items,
            );
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
    
    // Debug logging
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[KHM GEO] enqueue_suggest_plugin called, screen: ' . ( $screen ? $screen->id : 'null' ) . ', hook: ' . current_action() );
        if ( $screen ) {
            error_log( '[KHM GEO] Screen properties: id=' . $screen->id . ', base=' . $screen->base . ', is_block_editor=' . ( method_exists( $screen, 'is_block_editor' ) ? ( $screen->is_block_editor() ? 'true' : 'false' ) : 'method_not_exists' ) );
        }
    }
    
    // For now, be more permissive - enqueue on any screen that might be an editor
    $is_editor_screen = false;
    if ( $screen ) {
        // Check for various editor screen patterns
        $is_editor_screen = in_array( $screen->id, array( 'post', 'page', 'toplevel_page_content', 'edit-post', 'khm-seo-geo-post' ), true ) ||
                           strpos( $screen->id, 'post' ) !== false ||
                           strpos( $screen->base, 'post' ) !== false ||
                           ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) ||
                           strpos( $screen->id, 'khm-seo-geo' ) !== false; // Add this line for GEO screens
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
        }
        register_suggest_plugin_assets();
    }
    
    wp_enqueue_script( 'khm-geo-suggest-plugin' );
    wp_enqueue_style( 'khm-geo-suggest-plugin' );
    
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
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[KHM GEO] Suggest plugin enqueued on editor screen, post_id: ' . $post_id . ', screen: ' . ( $screen ? $screen->id : 'null' ) );
    }
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_suggest_plugin' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_suggest_plugin' );
add_action( 'current_screen', __NAMESPACE__ . '\\enqueue_suggest_plugin' );