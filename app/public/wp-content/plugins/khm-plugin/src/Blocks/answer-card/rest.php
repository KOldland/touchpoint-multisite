<?php
/**
 * AnswerCard REST API Endpoints
 *
 * Provides REST endpoints for:
 * - Entity autocomplete search
 * - On-demand scoring calculation
 * - Answer card retrieval
 *
 * @package KHM\Blocks\AnswerCard
 */

// Remove namespace declaration to avoid conflicts
// namespace KHM\Blocks\AnswerCard;

defined( 'ABSPATH' ) || exit;

/**
 * Register REST API routes for AnswerCard functionality.
 *
 * @return void
 */
function register_rest_routes() {
    // Entity autocomplete endpoint
    register_rest_route( 'khm-geo/v1', '/entities', array(
        'methods'             => 'GET',
        'callback'            => 'entities_autocomplete',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'args'                => array(
            'q' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => 'Search query for entity names',
            ),
            'limit' => array(
                'required'          => false,
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );

    // On-demand scoring endpoint
    register_rest_route( 'khm-geo/v1', '/score', array(
        'methods'             => 'POST',
        'callback'            => 'calculate_score_on_demand',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
    ) );

    // Get persisted score details for a post
    register_rest_route( 'khm-geo/v1', '/posts/(?P<post_id>\\d+)/score', array(
        'methods'             => 'GET',
        'callback'            => 'get_post_score_details',
        'permission_callback' => function( $request ) {
            $post_id = absint( $request->get_param( 'post_id' ) );
            return current_user_can( 'edit_post', $post_id );
        },
        'args' => array(
            'post_id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );

    // Get answer cards for a post (full data for Tracker)
    register_rest_route( 'khm-geo/v1', '/tracker/posts/(?P<post_id>\d+)/answercards', array(
        'methods'             => 'GET',
        'callback'            => 'get_post_answercards_full',
        'permission_callback' => function( $request ) {
            // Only allow authenticated users with edit permissions (for Tracker)
            $post_id = absint( $request->get_param( 'post_id' ) );
            return current_user_can( 'edit_post', $post_id );
        },
        'args' => array(
            'post_id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );

    // Get answer cards for a post (public - strips internal evidence)
    register_rest_route( 'khm-geo/v1', '/posts/(?P<post_id>\d+)/answercards', array(
        'methods'             => 'GET',
        'callback'            => 'get_post_answercards_public',
        'permission_callback' => '__return_true', // Public endpoint
        'args' => array(
            'post_id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );

    // Get all posts with GEO scores (for reporting)
    register_rest_route( 'khm-geo/v1', '/reports/scores', array(
        'methods'             => 'GET',
        'callback'            => 'get_geo_scores_report',
        'permission_callback' => function() {
            return current_user_can( 'edit_others_posts' );
        },
        'args'                => array(
            'per_page' => array(
                'type'              => 'integer',
                'default'           => 20,
                'sanitize_callback' => 'absint',
            ),
            'page' => array(
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'orderby' => array(
                'type'              => 'string',
                'default'           => 'score',
                'enum'              => array( 'score', 'title', 'date' ),
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'order' => array(
                'type'              => 'string',
                'default'           => 'DESC',
                'enum'              => array( 'ASC', 'DESC' ),
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'min_score' => array(
                'type'              => 'number',
                'default'           => 0,
                'sanitize_callback' => 'floatval',
            ),
        ),
    ) );

    // Entity suggest endpoint (Wikidata)
    register_rest_route( 'khm-geo/v1', '/entity/suggest', array(
        'methods'             => 'GET',
        'callback'            => 'suggest_entity_candidates',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'args'                => array(
            'term' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'context' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ) );

    // Entity resolve endpoint
    register_rest_route( 'khm-geo/v1', '/entity/resolve', array(
        'methods'             => 'POST',
        'callback'            => 'resolve_entity_candidate',
        'permission_callback' => function( $request ) {
            $post_id = absint( $request->get_param( 'post_id' ) );
            if ( $post_id ) {
                return current_user_can( 'edit_post', $post_id );
            }
            return current_user_can( 'edit_posts' );
        },
    ) );
}
add_action( 'rest_api_init', 'register_rest_routes' );

/**
 * Get full answer cards for a specific post (for Tracker/verification).
 * Includes all internal data: evidence, tracked_url, answer_card_id, etc.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response
 */
function get_post_answercards_full( $request ) {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $post    = get_post( $post_id );

    if ( ! $post ) {
        return new \WP_Error( 'post_not_found', 'Post not found', array( 'status' => 404 ) );
    }

    $cards = get_post_meta( $post_id, '_geo_answercards', true );

    if ( empty( $cards ) || ! is_array( $cards ) ) {
        return rest_ensure_response( array(
            'post_id' => $post_id,
            'cards'   => array(),
            'meta'    => array(
                'post_title' => get_the_title( $post_id ),
                'post_url'   => get_permalink( $post_id ),
            ),
        ) );
    }

    // Return full canonical data including evidence for authorized Tracker access
    return rest_ensure_response( array(
        'post_id' => $post_id,
        'cards'   => $cards,
        'meta'    => array(
            'post_title' => get_the_title( $post_id ),
            'post_url'   => get_permalink( $post_id ),
            'score'      => floatval( get_post_meta( $post_id, '_geo_score', true ) ),
        ),
    ) );
}

/**
 * Get public answer cards for a specific post.
 * Strips internal evidence data (confidence, source_passage) and tracked_url.
 * Only returns cards where expose_in_schema is true.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response
 */
function get_post_answercards_public( $request ) {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $post    = get_post( $post_id );

    if ( ! $post ) {
        return new \WP_Error( 'post_not_found', 'Post not found', array( 'status' => 404 ) );
    }

    // Only allow published posts for public access
    if ( 'publish' !== $post->post_status ) {
        return new \WP_Error( 'post_not_published', 'Post is not published', array( 'status' => 403 ) );
    }

    $cards = get_post_meta( $post_id, '_geo_answercards', true );

    if ( empty( $cards ) || ! is_array( $cards ) ) {
        return rest_ensure_response( array(
            'post_id' => $post_id,
            'cards'   => array(),
        ) );
    }

    // Filter and sanitize for public consumption
    $public_cards = array();
    foreach ( $cards as $card ) {
        // Skip cards not exposed in schema
        if ( empty( $card['expose_in_schema'] ) ) {
            continue;
        }

        // Strip internal evidence data
        $public_card = array(
            'answer_card_id'       => $card['answer_card_id'] ?? '',
            'question'             => $card['question'] ?? '',
            'concise_answer'       => $card['concise_answer'] ?? '',
            'preferred_summary'    => $card['preferred_summary'] ?? '',
            'public_summary_label' => $card['public_summary_label'] ?? '',
            'key_points'           => $card['key_points'] ?? array(),
            'entities'             => $card['entities'] ?? array(),
            'position'             => $card['position'] ?? 0,
        );

        // Add topic_discussed_at (this is public metadata)
        if ( ! empty( $card['topic_discussed_at'] ) ) {
            $public_card['topic_discussed_at'] = $card['topic_discussed_at'];
        }

        // Add site_keywords (public metadata for SEO)
        if ( ! empty( $card['site_keywords'] ) && is_array( $card['site_keywords'] ) ) {
            $public_card['site_keywords'] = $card['site_keywords'];
        }

        // Filter citations - remove tracked_url and internal fields
        if ( ! empty( $card['citations'] ) && is_array( $card['citations'] ) ) {
            $public_citations = array();
            foreach ( $card['citations'] as $citation ) {
                $public_citation = array(
                    'title'     => $citation['title'] ?? '',
                    'url'       => $citation['url'] ?? '',
                    'author'    => $citation['author'] ?? '',
                    'publisher' => $citation['publisher'] ?? '',
                    'year'      => $citation['year'] ?? '',
                );
                // Optionally include DOI if present (public metadata)
                if ( ! empty( $citation['doi'] ) ) {
                    $public_citation['doi'] = $citation['doi'];
                }
                $public_citations[] = $public_citation;
            }
            $public_card['citations'] = $public_citations;
        }

        // Include evidence tier but NOT confidence or source_passage
        if ( ! empty( $card['evidence']['tier'] ) ) {
            $public_card['evidence'] = array(
                'tier' => $card['evidence']['tier'],
            );
        }

        $public_cards[] = $public_card;
    }

    return rest_ensure_response( array(
        'post_id' => $post_id,
        'cards'   => $public_cards,
        'meta'    => array(
            'post_title' => get_the_title( $post_id ),
            'post_url'   => get_permalink( $post_id ),
        ),
    ) );
}

/**
 * Entity autocomplete endpoint callback.
 *
 * Searches for entities by name. If EntityManager class is available,
 * uses that; otherwise falls back to a simple stored entities search.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response
 */
function entities_autocomplete( $request ) {
    $query = $request->get_param( 'q' );
    $limit = $request->get_param( 'limit' );

    if ( empty( $query ) ) {
        return rest_ensure_response( array() );
    }

    // Check if EntityManager is available
    if ( class_exists( '\\KHM_SEO\\GEO\\EntityManager' ) ) {
        try {
            $mgr     = new \KHM_SEO\GEO\EntityManager();
            $results = $mgr->search_entities_by_name( $query, $limit );
            return rest_ensure_response( $results );
        } catch ( \Exception $e ) {
            error_log( '[KHM GEO] Entity search failed: ' . $e->getMessage() );
        }
    }

    // Fallback: Search through existing entities in postmeta
    $entities = get_cached_entities();
    $matches  = array();

    foreach ( $entities as $entity ) {
        if ( stripos( $entity, $query ) !== false ) {
            $matches[] = array(
                'name'   => $entity,
                'sameAs' => '',
            );

            if ( count( $matches ) >= $limit ) {
                break;
            }
        }
    }

    return rest_ensure_response( $matches );
}

/**
 * Get cached entities from existing answer cards.
 *
 * @return array List of unique entity names.
 */
function get_cached_entities() {
    $cache_key = 'khm_geo_entities_cache';
    $cached    = wp_cache_get( $cache_key );

    if ( false !== $cached ) {
        return $cached;
    }

    global $wpdb;

    // Get all unique entities from postmeta
    $results = $wpdb->get_col(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_geo_answercards'"
    );

    $entities = array();
    foreach ( $results as $meta_value ) {
        $cards = maybe_unserialize( $meta_value );
        if ( is_array( $cards ) ) {
            foreach ( $cards as $card ) {
                if ( isset( $card['entities'] ) && is_array( $card['entities'] ) ) {
                    foreach ( $card['entities'] as $entity ) {
                        $name = is_array( $entity ) ? ( $entity['name'] ?? '' ) : $entity;
                        if ( $name && ! in_array( $name, $entities, true ) ) {
                            $entities[] = $name;
                        }
                    }
                }
            }
        }
    }

    // Sort alphabetically
    sort( $entities );

    // Cache for 1 hour
    wp_cache_set( $cache_key, $entities, '', HOUR_IN_SECONDS );

    return $entities;
}

/**
 * Calculate score on demand endpoint callback.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function calculate_score_on_demand( $request ) {
    $payload = $request->get_json_params();

    if ( empty( $payload ) || ! is_array( $payload ) ) {
        return new \WP_Error(
            'invalid_payload',
            __( 'Invalid request payload', 'khm-membership' ),
            array( 'status' => 400 )
        );
    }

    // Check if ScoringEngine is available
    if ( class_exists( '\\KHM_SEO\\GEO\\Scoring\\ScoringEngine' ) ) {
        try {
            $engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();
            $settings = $payload;
            if ( function_exists( '\\KHM\\Blocks\\AnswerCard\\normalize_scoring_settings' ) ) {
                $settings = \KHM\Blocks\AnswerCard\normalize_scoring_settings( array(
                    'question'       => $payload['question'] ?? '',
                    'concise_answer' => $payload['concise_answer'] ?? ( $payload['conciseAnswer'] ?? '' ),
                    'key_points'     => $payload['key_points'] ?? ( $payload['keyPoints'] ?? array() ),
                    'citations'      => $payload['citations'] ?? array(),
                    'entities'       => $payload['entities'] ?? array(),
                    'evidence'       => $payload['evidence'] ?? array(),
                ) );
            }
            $score  = $engine->calculate_score( $settings, array() );
            return rest_ensure_response( $score );
        } catch ( \Exception $e ) {
            return new \WP_Error(
                'score_failed',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }

    error_log( '[KHM GEO ERROR] Scoring engine unavailable for on-demand score.' );
    return new \WP_Error(
        'scoring_unavailable',
        __( 'Scoring engine unavailable', 'khm-membership' ),
        array( 'status' => 500 )
    );
}

/**
 * Suggest entity candidates from Wikidata.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function suggest_entity_candidates( $request ) {
    $term = $request->get_param( 'term' );
    $context = $request->get_param( 'context' ) ?? '';

    if ( ! class_exists( '\\KHM_SEO\\GEO\\Entity\\EntityManager' ) ) {
        return new \WP_Error(
            'entity_manager_missing',
            'EntityManager is not available',
            array( 'status' => 500 )
        );
    }

    $manager = new \KHM_SEO\GEO\Entity\EntityManager();
    $candidates = $manager->suggest_same_as_for_name( $term, $context );

    return rest_ensure_response( array(
        'term' => $term,
        'candidates' => $candidates,
    ) );
}

/**
 * Resolve entity to Wikidata and persist same_as.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function resolve_entity_candidate( $request ) {
    $payload = $request->get_json_params();
    if ( empty( $payload ) || ! is_array( $payload ) ) {
        return new \WP_Error( 'invalid_payload', 'Invalid request payload', array( 'status' => 400 ) );
    }

    if ( ! class_exists( '\\KHM_SEO\\GEO\\Entity\\EntityManager' ) ) {
        return new \WP_Error(
            'entity_manager_missing',
            'EntityManager is not available',
            array( 'status' => 500 )
        );
    }

    $post_id = absint( $payload['post_id'] ?? 0 );
    $entity_name = sanitize_text_field( $payload['entity_name'] ?? '' );
    $qid = sanitize_text_field( $payload['qid'] ?? '' );
    $label = sanitize_text_field( $payload['label'] ?? '' );
    $provider = sanitize_text_field( $payload['provider'] ?? 'wikidata' );
    $page_role = sanitize_text_field( $payload['page_role'] ?? '' );

    if ( empty( $entity_name ) || empty( $qid ) ) {
        return new \WP_Error( 'missing_params', 'entity_name and qid are required', array( 'status' => 400 ) );
    }

    $manager = new \KHM_SEO\GEO\Entity\EntityManager();
    $entity = $manager->find_entity_by_canonical( $entity_name, 'site' );

    if ( ! $entity ) {
        $entity_id = $manager->create_entity( array(
            'canonical' => $entity_name,
            'type'      => 'Thing',
            'scope'     => 'site',
            'status'    => 'active',
        ) );
        if ( ! $entity_id ) {
            return new \WP_Error( 'entity_create_failed', 'Failed to create entity', array( 'status' => 500 ) );
        }
    } else {
        $entity_id = $entity->id;
    }

    $same_as_entry = array(
        'source' => $provider,
        'id'     => $qid,
        'url'    => 'https://www.wikidata.org/wiki/' . $qid,
        'label'  => $label,
    );

    $manager->set_same_as( $entity_id, array( $same_as_entry ) );

    if ( $post_id && in_array( $page_role, array( 'about', 'primary' ), true ) ) {
        $manager->add_entity_to_post( $post_id, $entity_id, $page_role, 0.8, 'manual' );
    }

    error_log( sprintf(
        '[KHM GEO] Entity resolved: %s (%s) by user %d',
        $entity_name,
        $qid,
        get_current_user_id()
    ) );

    $resolved_entity = $manager->get_entity( $entity_id );

    return rest_ensure_response( array(
        'entity' => $resolved_entity,
        'same_as' => $same_as_entry,
    ) );
}

/**
 * Get persisted score details for a post.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function get_post_score_details( $request ) {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $post    = get_post( $post_id );

    if ( ! $post ) {
        return new \WP_Error( 'post_not_found', 'Post not found', array( 'status' => 404 ) );
    }

    $score_details = get_post_meta( $post_id, '_geo_score_details', true );
    $score = get_post_meta( $post_id, '_geo_score', true );

    return rest_ensure_response( array(
        'post_id' => $post_id,
        'score' => $score !== '' ? floatval( $score ) : null,
        'score_details' => $score_details,
    ) );
}

/**
 * Calculate a basic GEO score based on content completeness.
 *
 * This is a fallback when ScoringEngine is not available.
 *
 * @param array $card Answer card data.
 * @return array Score data.
 */
function calculate_basic_score( $card ) {
    $total      = 0;
    $max_points = 100;
    $breakdown  = array();

    // Question present (10 points)
    $question = $card['question'] ?? '';
    if ( ! empty( $question ) ) {
        $total               += 10;
        $breakdown['question'] = array( 'score' => 10, 'max' => 10, 'status' => 'good' );
    } else {
        $breakdown['question'] = array( 'score' => 0, 'max' => 10, 'status' => 'missing' );
    }

    // Concise answer present and optimal length (25 points)
    $answer     = $card['concise_answer'] ?? $card['conciseAnswer'] ?? '';
    $word_count = str_word_count( strip_tags( $answer ) );

    if ( ! empty( $answer ) ) {
        if ( $word_count >= 40 && $word_count <= 80 ) {
            $total              += 25;
            $breakdown['answer'] = array( 'score' => 25, 'max' => 25, 'status' => 'optimal', 'words' => $word_count );
        } elseif ( $word_count >= 20 && $word_count <= 120 ) {
            $total              += 15;
            $breakdown['answer'] = array( 'score' => 15, 'max' => 25, 'status' => 'acceptable', 'words' => $word_count );
        } else {
            $total              += 5;
            $breakdown['answer'] = array( 'score' => 5, 'max' => 25, 'status' => 'suboptimal', 'words' => $word_count );
        }
    } else {
        $breakdown['answer'] = array( 'score' => 0, 'max' => 25, 'status' => 'missing' );
    }

    // Key points (20 points)
    $key_points = $card['key_points'] ?? $card['keyPoints'] ?? array();
    $kp_count   = is_array( $key_points ) ? count( array_filter( $key_points ) ) : 0;

    if ( $kp_count >= 3 ) {
        $total                  += 20;
        $breakdown['key_points'] = array( 'score' => 20, 'max' => 20, 'status' => 'good', 'count' => $kp_count );
    } elseif ( $kp_count >= 1 ) {
        $score                   = min( 15, $kp_count * 7 );
        $total                  += $score;
        $breakdown['key_points'] = array( 'score' => $score, 'max' => 20, 'status' => 'partial', 'count' => $kp_count );
    } else {
        $breakdown['key_points'] = array( 'score' => 0, 'max' => 20, 'status' => 'missing', 'count' => 0 );
    }

    // Citations (25 points)
    $citations = $card['citations'] ?? array();
    $cit_count = 0;

    if ( is_array( $citations ) ) {
        foreach ( $citations as $cit ) {
            $url = is_array( $cit ) ? ( $cit['url'] ?? '' ) : $cit;
            if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
                $cit_count++;
            }
        }
    }

    if ( $cit_count >= 2 ) {
        $total                  += 25;
        $breakdown['citations'] = array( 'score' => 25, 'max' => 25, 'status' => 'good', 'count' => $cit_count );
    } elseif ( $cit_count >= 1 ) {
        $total                  += 15;
        $breakdown['citations'] = array( 'score' => 15, 'max' => 25, 'status' => 'partial', 'count' => $cit_count );
    } else {
        $breakdown['citations'] = array( 'score' => 0, 'max' => 25, 'status' => 'missing', 'count' => 0 );
    }

    // Entities (20 points)
    $entities  = $card['entities'] ?? array();
    $ent_count = is_array( $entities ) ? count( $entities ) : 0;

    if ( $ent_count >= 3 ) {
        $total                 += 20;
        $breakdown['entities'] = array( 'score' => 20, 'max' => 20, 'status' => 'good', 'count' => $ent_count );
    } elseif ( $ent_count >= 1 ) {
        $score                 = min( 15, $ent_count * 7 );
        $total                += $score;
        $breakdown['entities'] = array( 'score' => $score, 'max' => 20, 'status' => 'partial', 'count' => $ent_count );
    } else {
        $breakdown['entities'] = array( 'score' => 0, 'max' => 20, 'status' => 'missing', 'count' => 0 );
    }

    // Calculate percentage
    $percentage = round( ( $total / $max_points ) * 100, 1 );

    return array(
        'total_score'   => $percentage,
        'raw_score'     => $total,
        'max_score'     => $max_points,
        'breakdown'     => $breakdown,
        'grade'         => get_grade( $percentage ),
        'engine'        => 'basic',
        'recommendations' => get_recommendations( $breakdown ),
    );
}

/**
 * Get letter grade from percentage score.
 *
 * @param float $percentage Score percentage.
 * @return string Letter grade.
 */
function get_grade( $percentage ) {
    if ( $percentage >= 90 ) {
        return 'A';
    } elseif ( $percentage >= 80 ) {
        return 'B';
    } elseif ( $percentage >= 70 ) {
        return 'C';
    } elseif ( $percentage >= 60 ) {
        return 'D';
    } else {
        return 'F';
    }
}

/**
 * Generate recommendations based on score breakdown.
 *
 * @param array $breakdown Score breakdown.
 * @return array List of recommendations.
 */
function get_recommendations( $breakdown ) {
    $recommendations = array();

    if ( isset( $breakdown['question'] ) && 'missing' === $breakdown['question']['status'] ) {
        $recommendations[] = __( 'Add a clear question that this content answers.', 'khm-membership' );
    }

    if ( isset( $breakdown['answer'] ) ) {
        if ( 'missing' === $breakdown['answer']['status'] ) {
            $recommendations[] = __( 'Add a concise answer (40-80 words recommended).', 'khm-membership' );
        } elseif ( 'suboptimal' === $breakdown['answer']['status'] ) {
            $words = $breakdown['answer']['words'];
            if ( $words < 40 ) {
                $recommendations[] = __( 'Expand your answer to at least 40 words for better featured snippet performance.', 'khm-membership' );
            } else {
                $recommendations[] = __( 'Consider shortening your answer to around 80 words for optimal featured snippet length.', 'khm-membership' );
            }
        }
    }

    if ( isset( $breakdown['key_points'] ) && in_array( $breakdown['key_points']['status'], array( 'missing', 'partial' ), true ) ) {
        $recommendations[] = __( 'Add 3+ key points for better scannability and list snippet potential.', 'khm-membership' );
    }

    if ( isset( $breakdown['citations'] ) && in_array( $breakdown['citations']['status'], array( 'missing', 'partial' ), true ) ) {
        $recommendations[] = __( 'Add 2+ authoritative citations to improve credibility signals.', 'khm-membership' );
    }

    if ( isset( $breakdown['entities'] ) && in_array( $breakdown['entities']['status'], array( 'missing', 'partial' ), true ) ) {
        $recommendations[] = __( 'Add 3+ relevant entities (topics/concepts) for better semantic understanding.', 'khm-membership' );
    }

    return $recommendations;
}

/**
 * Get answer cards for a specific post.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response
 */
function get_post_answercards( $request ) {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $cards   = get_post_meta( $post_id, '_geo_answercards', true );

    if ( empty( $cards ) || ! is_array( $cards ) ) {
        return rest_ensure_response( array(
            'post_id' => $post_id,
            'cards'   => array(),
            'score'   => 0,
        ) );
    }

    $score = get_post_meta( $post_id, '_geo_score', true );

    return rest_ensure_response( array(
        'post_id' => $post_id,
        'cards'   => $cards,
        'score'   => floatval( $score ),
    ) );
}

/**
 * Get GEO scores report for all posts.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response
 */
function get_geo_scores_report( $request ) {
    $per_page  = $request->get_param( 'per_page' );
    $page      = $request->get_param( 'page' );
    $orderby   = $request->get_param( 'orderby' );
    $order     = $request->get_param( 'order' );
    $min_score = $request->get_param( 'min_score' );

    $args = array(
        'post_type'      => array( 'post', 'page' ),
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query'     => array(
            array(
                'key'     => '_geo_score',
                'compare' => 'EXISTS',
            ),
        ),
    );

    // Add minimum score filter
    if ( $min_score > 0 ) {
        $args['meta_query'][] = array(
            'key'     => '_geo_score',
            'value'   => $min_score,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }

    // Handle ordering
    if ( 'score' === $orderby ) {
        $args['meta_key'] = '_geo_score';
        $args['orderby']  = 'meta_value_num';
        $args['order']    = $order;
    } else {
        $args['orderby'] = $orderby;
        $args['order']   = $order;
    }

    $query = new \WP_Query( $args );
    $posts = array();

    foreach ( $query->posts as $post ) {
        $score       = get_post_meta( $post->ID, '_geo_score', true );
        $cards       = get_post_meta( $post->ID, '_geo_answercards', true );
        $cards_count = is_array( $cards ) ? count( $cards ) : 0;

        $posts[] = array(
            'id'          => $post->ID,
            'title'       => get_the_title( $post ),
            'url'         => get_permalink( $post ),
            'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
            'post_type'   => $post->post_type,
            'date'        => get_the_date( 'c', $post ),
            'score'       => floatval( $score ),
            'cards_count' => $cards_count,
        );
    }

    return rest_ensure_response( array(
        'posts'       => $posts,
        'total'       => $query->found_posts,
        'total_pages' => $query->max_num_pages,
        'page'        => $page,
        'per_page'    => $per_page,
    ) );
}

/**
 * Invalidate entity cache when answer cards are saved.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function invalidate_entity_cache( $post_id ) {
    wp_cache_delete( 'khm_geo_entities_cache' );
}
add_action( 'save_post', 'invalidate_entity_cache', 30 );
