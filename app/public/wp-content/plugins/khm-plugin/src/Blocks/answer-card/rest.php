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

    // Recompute and persist score details for a post
    register_rest_route( 'khm-geo/v1', '/posts/(?P<post_id>\\d+)/score/recompute', array(
        'methods'             => 'POST',
        'callback'            => 'recompute_post_score_details',
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

    // Get topic discussed defaults for editor panel
    register_rest_route( 'khm-geo/v1', '/topic-defaults', array(
        'methods'             => 'GET',
        'callback'            => 'get_topic_discussed_defaults',
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

    // Author notes endpoint (public)
    register_rest_route( 'khm-geo/v1', '/author-notes', array(
        'methods'             => 'GET',
        'callback'            => 'get_author_notes',
        'permission_callback' => '__return_true',
        'args'                => array(
            'author_id' => array(
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

    // Entity unresolve endpoint
    register_rest_route( 'khm-geo/v1', '/entity/unresolve', array(
        'methods'             => 'POST',
        'callback'            => 'unresolve_entity_candidate',
        'permission_callback' => function( $request ) {
            $post_id = absint( $request->get_param( 'post_id' ) );
            if ( $post_id ) {
                return current_user_can( 'edit_post', $post_id );
            }
            return current_user_can( 'edit_posts' );
        },
    ) );

    // Generate concise/preferred summaries (Phase 1 PoC)
    register_rest_route( 'khm-geo/v1', '/generate-card', array(
        'methods'             => 'POST',
        'callback'            => 'generate_answercard_draft',
        'permission_callback' => function( $request ) {
            $post_id = absint( $request->get_param( 'post_id' ) );
            if ( $post_id ) {
                return current_user_can( 'edit_post', $post_id );
            }
            return current_user_can( 'edit_posts' );
        },
    ) );

    // Job status for generation pipeline
    register_rest_route( 'khm-geo/v1', '/job-status/(?P<job_id>[a-zA-Z0-9\\-_]+)', array(
        'methods'             => 'GET',
        'callback'            => 'get_generation_job_status',
        'permission_callback' => function( $request ) {
            $post_id = absint( $request->get_param( 'post_id' ) );
            if ( $post_id ) {
                return current_user_can( 'edit_post', $post_id );
            }
            return current_user_can( 'edit_posts' );
        },
        'args'                => array(
            'job_id' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'post_id' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );

    register_rest_route( 'khm-geo/v1', '/enqueue', array(
        'methods'             => 'POST',
        'callback'            => 'generate_answercard_draft',
        'permission_callback' => function( $request ) {
            $post_id = absint( $request->get_param( 'post_id' ) );
            if ( $post_id ) {
                return current_user_can( 'edit_post', $post_id );
            }
            return current_user_can( 'edit_posts' );
        },
    ) );

    register_rest_route( 'khm-geo/v1', '/job/(?P<job_id>[a-zA-Z0-9\\-_]+)', array(
        'methods'             => 'GET',
        'callback'            => 'get_generation_job_status',
        'permission_callback' => function( $request ) {
            $post_id = absint( $request->get_param( 'post_id' ) );
            if ( $post_id ) {
                return current_user_can( 'edit_post', $post_id );
            }
            return current_user_can( 'edit_posts' );
        },
        'args'                => array(
            'job_id' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'post_id' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );

    // Staging JSON-LD (drafts only)
    register_rest_route( 'khm-geo/v1', '/posts/(?P<post_id>\\d+)/answercards_staging', array(
        'methods'             => 'GET',
        'callback'            => 'get_post_answercards_staging',
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

    // Approve generated summary for staging/phase 2
    register_rest_route( 'khm-geo/v1', '/answercards/approve', array(
        'methods'             => 'POST',
        'callback'            => 'approve_answercard_summary',
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
 * Provide topic discussed defaults for the editor inspector.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function get_topic_discussed_defaults( $request ) {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $post    = get_post( $post_id );

    if ( ! $post ) {
        return new \WP_Error( 'post_not_found', 'Post not found', array( 'status' => 404 ) );
    }

    $wp_author_name = '';
    $wp_author_id   = 0;
    if ( $post->post_author ) {
        $author = get_userdata( $post->post_author );
        if ( $author ) {
            $wp_author_name = $author->display_name;
            $wp_author_id   = $author->ID;
        }
    }

    $author_identity = get_lead_author_identity_for_post( $post_id );
    $author_name     = $author_identity['name'] ?? '';
    $author_id       = $author_identity['user_id'] ?? 0;

    $date_format = get_option( 'khm_geo_date_format', 'd/m/Y' );
    $date_iso    = get_the_date( 'Y-m-d', $post );
    $date_display = get_the_date( $date_format, $post );

    $keywords_data = get_site_keywords_for_post( $post_id );
    $public_label  = get_option( 'khm_geo_public_label', '' );
    $auto_resolve  = (bool) get_option( 'khm_geo_auto_resolve', false );
    $auto_threshold = floatval( get_option( 'khm_geo_auto_resolve_threshold', 0.85 ) );

    return rest_ensure_response( array(
        'post_id' => $post_id,
        'topic'   => array(
            'title'      => get_the_title( $post_id ),
            'url'        => get_permalink( $post_id ),
            'author_name'=> $author_name,
            'author_id'  => $author_id,
            'wp_author_name' => $wp_author_name,
            'wp_author_id'   => $wp_author_id,
            'publisher'  => get_bloginfo( 'name' ),
            'date'       => $date_display,
            'date_iso'   => $date_iso,
        ),
        'site_keywords' => $keywords_data['keywords'],
        'site_keywords_source' => $keywords_data['source'],
        'public_summary_label' => $public_label,
        'public_label_enabled' => ! empty( $public_label ),
        'date_format'          => $date_format,
        'auto_resolve'         => $auto_resolve,
        'auto_resolve_threshold' => $auto_threshold,
    ) );
}

/**
 * Resolve lead author identity from Multiple Authors plugin.
 *
 * @param int $post_id Post ID.
 * @return array{name:string,user_id:int}
 */
function get_lead_author_identity_for_post( $post_id ) {
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
 * Get author-linked notes for AnswerCards.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response
 */
function get_author_notes( $request ) {
    $author_id = absint( $request->get_param( 'author_id' ) );
    if ( ! $author_id ) {
        return rest_ensure_response( array( 'author_id' => 0, 'notes' => array() ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'geo_answer_cards';

    $notes = array();
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $table_exists ) {
        // Fetch all rows and filter in PHP to avoid SQL injection risks with JSON LIKE queries
        $rows = $wpdb->get_results(
            "SELECT post_id, answer_card_id, question, topic_discussed_at FROM {$table} WHERE topic_discussed_at IS NOT NULL"
        );

        foreach ( $rows as $row ) {
            if ( 'publish' !== get_post_status( $row->post_id ) ) {
                continue;
            }
            $topic = json_decode( $row->topic_discussed_at, true );
            if ( empty( $topic ) || absint( $topic['author_id'] ?? 0 ) !== $author_id ) {
                continue;
            }
            $note = sanitize_text_field( $topic['note'] ?? '' );
            if ( empty( $note ) ) {
                continue;
            }
            $notes[] = array(
                'post_id'        => absint( $row->post_id ),
                'post_title'     => get_the_title( $row->post_id ),
                'post_url'       => get_permalink( $row->post_id ),
                'answer_card_id' => sanitize_text_field( $row->answer_card_id ?? '' ),
                'question'       => sanitize_text_field( $row->question ?? '' ),
                'note'           => $note,
            );
        }
    } else {
        $posts = get_posts( array(
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'meta_key'       => '_geo_answercards',
        ) );

        foreach ( $posts as $post ) {
            $cards = get_post_meta( $post->ID, '_geo_answercards', true );
            if ( empty( $cards ) || ! is_array( $cards ) ) {
                continue;
            }
            foreach ( $cards as $card ) {
                $topic = $card['topic_discussed_at'] ?? array();
                if ( absint( $topic['author_id'] ?? 0 ) !== $author_id ) {
                    continue;
                }
                $note = sanitize_text_field( $topic['note'] ?? '' );
                if ( empty( $note ) ) {
                    continue;
                }
                $notes[] = array(
                    'post_id'        => $post->ID,
                    'post_title'     => get_the_title( $post->ID ),
                    'post_url'       => get_permalink( $post->ID ),
                    'answer_card_id' => sanitize_text_field( $card['answer_card_id'] ?? '' ),
                    'question'       => sanitize_text_field( $card['question'] ?? '' ),
                    'note'           => $note,
                );
            }
        }
    }

    return rest_ensure_response( array(
        'author_id' => $author_id,
        'notes'     => $notes,
    ) );
}

/**
 * Extract site keywords from common SEO plugin metadata.
 *
 * @param int $post_id Post ID.
 * @return array {keywords: array, source: string}
 */
function get_site_keywords_for_post( $post_id ) {
    $sources = array(
        'khm-seo' => array(
            '_khm_seo_keywords',
            '_khm_seo_focus_keyword',
        ),
        'yoast' => array(
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_focuskeywords',
            '_yoast_wpseo_focuskw_text_input',
        ),
        'rankmath' => array(
            'rank_math_focus_keyword',
        ),
        'seopress' => array(
            '_seopress_titles_target_kw',
            '_seopress_analysis_target_kw',
        ),
    );

    foreach ( $sources as $source => $keys ) {
        if ( 'khm-seo' === $source ) {
            $combined = array();
            foreach ( $keys as $key ) {
                $raw = get_post_meta( $post_id, $key, true );
                $combined = array_merge( $combined, normalize_keywords_list( $raw ) );
            }
            $combined = array_values( array_unique( $combined ) );
            if ( ! empty( $combined ) ) {
                return array(
                    'keywords' => $combined,
                    'source'   => $source,
                );
            }
            continue;
        }

        foreach ( $keys as $key ) {
            $raw = get_post_meta( $post_id, $key, true );
            $keywords = normalize_keywords_list( $raw );
            if ( ! empty( $keywords ) ) {
                return array(
                    'keywords' => $keywords,
                    'source'   => $source,
                );
            }
        }
    }

    // Fallback to KHM SEO meta keywords when enabled (if plugin is active)
    if ( class_exists( '\\KHM_SEO\\Meta\\MetaManager' ) ) {
        $meta_keywords = get_post_meta( $post_id, '_khm_seo_keywords', true );
        $keywords = normalize_keywords_list( $meta_keywords );
        if ( ! empty( $keywords ) ) {
            return array(
                'keywords' => $keywords,
                'source'   => 'khm-seo',
            );
        }
    }

    return array(
        'keywords' => array(),
        'source'   => 'none',
    );
}

/**
 * Normalize keywords input into a clean array.
 *
 * @param mixed $raw Raw keywords value.
 * @return array
 */
function normalize_keywords_list( $raw ) {
    if ( empty( $raw ) ) {
        return array();
    }

    $keywords = array();
    if ( is_array( $raw ) ) {
        $keywords = $raw;
    } elseif ( is_string( $raw ) ) {
        $keywords = preg_split( '/[,;]+/', $raw );
    }

    $keywords = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $keywords ) ) );
    return array_values( array_unique( $keywords ) );
}

/**
 * Generate a concise and preferred summary draft (Phase 1 PoC).
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function generate_answercard_draft( $request ) {
    $payload = $request->get_json_params();
    if ( empty( $payload ) || ! is_array( $payload ) ) {
        return new \WP_Error( 'invalid_payload', 'Invalid request payload', array( 'status' => 400 ) );
    }

    $post_id = absint( $payload['post_id'] ?? 0 );
    if ( ! $post_id ) {
        return new \WP_Error( 'missing_post_id', 'post_id is required', array( 'status' => 400 ) );
    }

    $answer_card_id = sanitize_text_field( $payload['answer_card_id'] ?? '' );
    $card_index     = isset( $payload['card_index'] ) ? absint( $payload['card_index'] ) : null;
    $force          = ! empty( $payload['force'] );

    $job_id = 'geo_job_' . wp_generate_password( 8, false, false ) . '-' . time();
    $job    = array(
        'id'            => $job_id,
        'post_id'       => $post_id,
        'answer_card_id'=> $answer_card_id,
        'card_index'    => $card_index,
        'status'        => 'queued',
        'created_at'    => current_time( 'mysql' ),
        'updated_at'    => current_time( 'mysql' ),
    );

    $jobs = get_post_meta( $post_id, '_geo_generation_jobs', true );
    if ( empty( $jobs ) || ! is_array( $jobs ) ) {
        $jobs = array();
    }
    $jobs[ $job_id ] = $job;
    update_post_meta( $post_id, '_geo_generation_jobs', $jobs );

    $job['status'] = 'processing';
    $job['updated_at'] = current_time( 'mysql' );
    $jobs[ $job_id ] = $job;
    update_post_meta( $post_id, '_geo_generation_jobs', $jobs );

    $result = run_answercard_generation_job( $job, $force );
    $jobs[ $job_id ]['status'] = $result['status'];
    $jobs[ $job_id ]['updated_at'] = current_time( 'mysql' );
    $jobs[ $job_id ]['error'] = $result['error'] ?? '';
    update_post_meta( $post_id, '_geo_generation_jobs', $jobs );

    if ( 'error' === $result['status'] ) {
        return new \WP_Error( 'generation_failed', $result['error'] ?? 'Generation failed', array( 'status' => 500 ) );
    }

    return rest_ensure_response( array(
        'job_id'  => $job_id,
        'status'  => $result['status'],
        'card'    => $result['card'] ?? array(),
        'message' => $result['message'] ?? '',
    ) );
}

/**
 * Fetch generation job status.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function get_generation_job_status( $request ) {
    $job_id = sanitize_text_field( $request->get_param( 'job_id' ) );
    $post_id = absint( $request->get_param( 'post_id' ) );

    if ( ! $job_id ) {
        return new \WP_Error( 'missing_job_id', 'job_id is required', array( 'status' => 400 ) );
    }

    if ( ! $post_id ) {
        return new \WP_Error( 'missing_post_id', 'post_id is required to fetch job status', array( 'status' => 400 ) );
    }

    $jobs = get_post_meta( $post_id, '_geo_generation_jobs', true );
    if ( empty( $jobs ) || ! is_array( $jobs ) || empty( $jobs[ $job_id ] ) ) {
        return new \WP_Error( 'job_not_found', 'Job not found', array( 'status' => 404 ) );
    }

    return rest_ensure_response( $jobs[ $job_id ] );
}

/**
 * Execute a generation job (Phase 1 PoC).
 *
 * @param array $job Job data.
 * @param bool  $force Force overwrite of preferred summary.
 * @return array
 */
function run_answercard_generation_job( $job, $force = false ) {
    $post_id = absint( $job['post_id'] ?? 0 );
    if ( ! $post_id ) {
        return array( 'status' => 'error', 'error' => 'Missing post_id' );
    }

    $cards = get_post_meta( $post_id, '_geo_answercards', true );
    if ( empty( $cards ) || ! is_array( $cards ) ) {
        return array( 'status' => 'error', 'error' => 'No answer cards found' );
    }

    $target_index = null;
    if ( ! empty( $job['answer_card_id'] ) ) {
        foreach ( $cards as $index => $card ) {
            if ( ! empty( $card['answer_card_id'] ) && $card['answer_card_id'] === $job['answer_card_id'] ) {
                $target_index = $index;
                break;
            }
        }
    } elseif ( isset( $job['card_index'] ) ) {
        $target_index = absint( $job['card_index'] );
    }

    if ( $target_index === null || ! isset( $cards[ $target_index ] ) ) {
        return array( 'status' => 'error', 'error' => 'Answer card not found' );
    }

    $card = $cards[ $target_index ];
    $concise = trim( (string) ( $card['concise_answer'] ?? '' ) );
    if ( empty( $concise ) ) {
        return array( 'status' => 'error', 'error' => 'Concise answer is required before generation' );
    }

    $preferred = trim( (string) ( $card['preferred_summary'] ?? '' ) );
    $reasons   = array();
    $citation_index = null;
    $author = '';
    $publisher = '';
    $year = '';
    $title = '';

    if ( $force || empty( $preferred ) ) {
        $citation = null;
        if ( ! empty( $card['citations'] ) && is_array( $card['citations'] ) ) {
            foreach ( $card['citations'] as $idx => $candidate ) {
                if ( empty( $candidate['title'] ) && empty( $candidate['author'] ) && empty( $candidate['publisher'] ) ) {
                    continue;
                }
                $citation = $candidate;
                $citation_index = $idx;
                break;
            }
        }

        $author = $citation['author'] ?? '';
        $publisher = $citation['publisher'] ?? '';
        $year = $citation['year'] ?? '';
        $title = $citation['title'] ?? '';

        if ( empty( $author ) ) {
            $reasons[] = array( 'code' => 'missing_author', 'label' => 'Missing: author attribution', 'severity' => 'warning' );
        }
        if ( empty( $year ) ) {
            $reasons[] = array( 'code' => 'missing_year', 'label' => 'Missing: publication year', 'severity' => 'warning' );
        }
        if ( empty( $publisher ) ) {
            $reasons[] = array( 'code' => 'missing_publisher', 'label' => 'Missing: publisher', 'severity' => 'info' );
        }

        if ( $citation ) {
            $source_name = $author ?: $title ?: $publisher;
            $parts = array_filter( array( $publisher, $year ) );
            $meta = $parts ? implode( ', ', $parts ) : '';
            if ( $source_name && $meta ) {
                $preferred = sprintf( 'According to %s (%s), %s', $source_name, $meta, $concise );
            } elseif ( $source_name ) {
                $preferred = sprintf( 'According to %s, %s', $source_name, $concise );
            } else {
                $preferred = $concise;
            }
        } else {
            $reasons[] = array( 'code' => 'missing_citation', 'label' => 'Missing: citation metadata', 'severity' => 'warning' );
            $preferred = $concise;
        }
    }

    if ( empty( $card['evidence']['source_passage'] ) ) {
        $card['evidence']['source_passage'] = extract_source_passage_from_post( $post_id, $card );
    } elseif ( ! source_passage_matches_numbers( $preferred, $card['evidence']['source_passage'] ) ) {
        $card['evidence']['source_passage'] = extract_source_passage_from_post( $post_id, $card );
    }

    if ( empty( $card['evidence']['source_passage'] ) ) {
        $reasons[] = array( 'code' => 'no_source_passage', 'label' => 'Missing: source passage', 'severity' => 'warning' );
    }

    $card['evidence']['anchor_entities'] = merge_anchor_entities(
        $card['evidence']['anchor_entities'] ?? array(),
        $card['entities'] ?? array()
    );

    $verification = verify_generated_summary(
        $preferred,
        $card['evidence']['source_passage'] ?? '',
        $card,
        array(
            'author'    => $author,
            'publisher' => $publisher,
            'year'      => $year,
            'title'     => $title,
        )
    );
    if ( ! empty( $verification['reasons'] ) ) {
        foreach ( $verification['reasons'] as $reason ) {
            $reasons[] = $reason;
        }
    }

    $confidence = isset( $card['evidence']['confidence'] ) ? floatval( $card['evidence']['confidence'] ) : 0.0;
    if ( $confidence <= 0 ) {
        $confidence = 0.5;
        if ( ! empty( $card['evidence']['source_passage'] ) ) {
            $confidence += 0.2;
        }
        if ( ! empty( $author ) ) {
            $confidence += 0.1;
        }
        if ( ! empty( $year ) ) {
            $confidence += 0.1;
        }
        if ( ! empty( $publisher ) ) {
            $confidence += 0.1;
        }
        $confidence = min( 0.95, $confidence );
        $card['evidence']['confidence'] = $confidence;
    }

    $card['preferred_summary'] = $preferred;
    $card['generation_reasons'] = $reasons;
    $card['generation_status'] = empty( $verification['status'] ) ? 'draft' : $verification['status'];

    $audit_entry = array(
        'generated_at' => current_time( 'mysql' ),
        'pipeline'     => 'phase1_php_poc',
        'model'        => 'none',
        'status'       => $card['generation_status'],
        'citation_index' => $citation_index,
        'justification'  => $citation_index !== null
            ? sprintf( 'preferred summary derived from citation #%d', $citation_index + 1 )
            : 'preferred summary derived from concise answer',
        'verification'   => $verification,
    );
    if ( empty( $card['audit'] ) || ! is_array( $card['audit'] ) ) {
        $card['audit'] = array();
    }
    $card['audit'][] = $audit_entry;

    $cards[ $target_index ] = $card;
    update_post_meta( $post_id, '_geo_answercards', $cards );

    update_geo_generation_metrics( $post_id, $card['generation_status'] );

    if ( function_exists( '\\KHM\\Blocks\\AnswerCard\\persist_to_database' ) ) {
        \KHM\Blocks\AnswerCard\persist_to_database( $post_id, $cards );
    }

    return array(
        'status'  => 'complete',
        'card'    => $card,
        'message' => 'Draft summaries generated',
    );
}

/**
 * Extract a source passage from post content using citation hints.
 *
 * @param int   $post_id Post ID.
 * @param array $card    Answer card data.
 * @return string
 */
function extract_source_passage_from_post( $post_id, $card ) {
    $post = get_post( $post_id );
    if ( ! $post ) {
        return '';
    }

    $raw_content = $post->post_content;
    $raw_content = preg_replace( '/<!--.*?-->/s', '', $raw_content );
    $content = wp_strip_all_tags( $raw_content );
    $content = trim( preg_replace( '/\s+/', ' ', $content ) );
    if ( empty( $content ) ) {
        return '';
    }

    $sentences = preg_split( '/(?<=[\.!\?])\s+/', $content );
    if ( empty( $sentences ) ) {
        return '';
    }

    $search_terms = get_card_search_terms( $card );
    $number_terms = extract_numbers_from_text( $card['concise_answer'] ?? '' );
    $preferred_numbers = extract_numbers_from_text( $card['preferred_summary'] ?? '' );
    $number_terms = array_values( array_unique( array_merge( $number_terms, $preferred_numbers ) ) );
    $number_terms = prioritize_numeric_terms( $number_terms );

    $best_score = 0;
    $best_index = 0;
    foreach ( $sentences as $index => $sentence ) {
        $score = 0;
        foreach ( $search_terms as $term ) {
            if ( stripos( $sentence, $term ) !== false ) {
                $score += 3;
            }
        }
        foreach ( $number_terms as $num ) {
            if ( strpos( $sentence, $num ) !== false ) {
                $score += 2;
            }
        }
        if ( $score > $best_score ) {
            $best_score = $score;
            $best_index = $index;
        }
    }

    $number_index = find_sentence_index_for_numbers( $sentences, $number_terms );
    if ( $number_index !== null ) {
        $best_index = $number_index;
    }

    $start = max( 0, $best_index - 1 );
    $slice = array_slice( $sentences, $start, 3 );
    $passage = trim( implode( ' ', $slice ) );

    if ( ! empty( $passage ) ) {
        return $passage;
    }

    return trim( implode( ' ', array_slice( $sentences, 0, 2 ) ) );
}

/**
 * Verify generated summary against provenance and evidence.
 *
 * @param string $preferred Preferred summary.
 * @param string $source_passage Source passage.
 * @param string $author Author.
 * @param string $publisher Publisher.
 * @param string $year Year.
 * @return array
 */
function verify_generated_summary( $preferred, $source_passage, $card, $citation_meta ) {
    $reasons = array();
    $status = 'draft';

    $author = $citation_meta['author'] ?? '';
    $publisher = $citation_meta['publisher'] ?? '';
    $year = $citation_meta['year'] ?? '';

    if ( empty( $source_passage ) ) {
        $reasons[] = array( 'code' => 'no_source_passage', 'label' => 'Missing: source passage', 'severity' => 'warning' );
    }

    if ( empty( $author ) && empty( $publisher ) && empty( $year ) ) {
        $reasons[] = array( 'code' => 'missing_provenance', 'label' => 'Missing: provenance attribution', 'severity' => 'warning' );
    }

    if ( stripos( $preferred, 'according to' ) === false && ( ! empty( $author ) || ! empty( $publisher ) || ! empty( $year ) ) ) {
        $reasons[] = array( 'code' => 'missing_attribution', 'label' => 'Missing: attribution clause', 'severity' => 'warning' );
    }

    $preferred_numbers = extract_numbers_from_text( $preferred );
    $citation_years = get_citation_years( $card['citations'] ?? array() );
    foreach ( $preferred_numbers as $num ) {
        if ( $source_passage && strpos( $source_passage, $num ) !== false ) {
            continue;
        }
        if ( in_array( $num, $citation_years, true ) ) {
            continue;
        }
        $reasons[] = array( 'code' => 'hallucination', 'label' => 'Numeric claim not found in source passage', 'severity' => 'error' );
        break;
    }

    $quoted_segments = array();
    if ( preg_match_all( '/"([^"]+)"/', $preferred, $matches ) ) {
        $quoted_segments = $matches[1];
    }
    foreach ( $quoted_segments as $segment ) {
        if ( preg_match( '/[\.!\?].+[\.!\?]/', $segment ) ) {
            $reasons[] = array( 'code' => 'long_quote', 'label' => 'Quote exceeds one sentence', 'severity' => 'warning' );
            break;
        }
    }

    $anchor_entities = $card['evidence']['anchor_entities'] ?? array();
    $entity_names = array();
    if ( ! empty( $card['entities'] ) && is_array( $card['entities'] ) ) {
        foreach ( $card['entities'] as $entity ) {
            if ( is_array( $entity ) && ! empty( $entity['name'] ) ) {
                $entity_names[] = $entity['name'];
            } elseif ( is_string( $entity ) ) {
                $entity_names[] = $entity;
            }
        }
    }
    foreach ( $entity_names as $name ) {
        if ( stripos( $preferred, $name ) !== false && ! in_array( $name, $anchor_entities, true ) ) {
            $reasons[] = array( 'code' => 'entity_not_anchored', 'label' => 'Entity used but not anchored', 'severity' => 'warning' );
            break;
        }
    }

    if ( empty( $reasons ) ) {
        $status = 'draft';
    }

    return array(
        'status' => $status,
        'reasons' => $reasons,
    );
}

/**
 * Build search terms for evidence extraction.
 *
 * @param array $card Card data.
 * @return array
 */
function get_card_search_terms( $card ) {
    $terms = array();
    $citations = $card['citations'] ?? array();
    if ( ! empty( $citations ) && is_array( $citations ) ) {
        $first = $citations[0] ?? array();
        foreach ( array( 'title', 'author', 'publisher', 'year' ) as $key ) {
            if ( ! empty( $first[ $key ] ) ) {
                $terms[] = $first[ $key ];
            }
        }
    }

    $question = $card['question'] ?? '';
    if ( $question ) {
        $tokens = preg_split( '/\s+/', preg_replace( '/[^a-zA-Z0-9\s]/', ' ', $question ) );
        foreach ( $tokens as $token ) {
            if ( strlen( $token ) >= 4 ) {
                $terms[] = $token;
            }
        }
    }

    $anchors = $card['evidence']['anchor_entities'] ?? array();
    foreach ( $anchors as $anchor ) {
        if ( ! empty( $anchor ) ) {
            $terms[] = $anchor;
        }
    }

    $terms = array_filter( array_map( 'trim', $terms ) );
    $terms = array_values( array_unique( $terms ) );
    return $terms;
}

/**
 * Extract numeric tokens from text.
 *
 * @param string $text Input text.
 * @return array
 */
function extract_numbers_from_text( $text ) {
    if ( empty( $text ) ) {
        return array();
    }
    preg_match_all( '/\b\d+(?:\.\d+)?%?\b/', $text, $matches );
    if ( empty( $matches[0] ) ) {
        return array();
    }
    return array_values( array_unique( $matches[0] ) );
}

/**
 * Check if source passage contains numeric claims from a summary.
 *
 * @param string $summary Summary text.
 * @param string $source_passage Source passage text.
 * @return bool
 */
function source_passage_matches_numbers( $summary, $source_passage ) {
    $numbers = extract_numbers_from_text( $summary );
    if ( empty( $numbers ) ) {
        return true;
    }
    if ( empty( $source_passage ) ) {
        return false;
    }
    foreach ( $numbers as $num ) {
        if ( strpos( $source_passage, $num ) !== false ) {
            return true;
        }
    }
    return false;
}

/**
 * Prioritize numeric terms by likely evidentiary value.
 *
 * @param array $numbers Numeric tokens.
 * @return array
 */
function prioritize_numeric_terms( $numbers ) {
    if ( empty( $numbers ) ) {
        return array();
    }

    usort( $numbers, function( $a, $b ) {
        $a_has_percent = strpos( $a, '%' ) !== false;
        $b_has_percent = strpos( $b, '%' ) !== false;
        if ( $a_has_percent !== $b_has_percent ) {
            return $a_has_percent ? -1 : 1;
        }
        return strlen( $b ) <=> strlen( $a );
    } );

    return $numbers;
}

/**
 * Extract citation years as normalized strings.
 *
 * @param array $citations Citation list.
 * @return array
 */
function get_citation_years( $citations ) {
    $years = array();
    if ( empty( $citations ) || ! is_array( $citations ) ) {
        return $years;
    }

    foreach ( $citations as $citation ) {
        if ( ! is_array( $citation ) ) {
            continue;
        }
        $year = trim( (string) ( $citation['year'] ?? '' ) );
        if ( $year !== '' ) {
            $years[] = $year;
        }
    }

    return array_values( array_unique( $years ) );
}

/**
 * Merge entity names into anchor_entities without losing existing anchors.
 *
 * @param array $anchor_entities Existing anchors.
 * @param array $entities Entity list.
 * @return array
 */
function merge_anchor_entities( $anchor_entities, $entities ) {
    $anchors = array();
    if ( is_array( $anchor_entities ) ) {
        foreach ( $anchor_entities as $anchor ) {
            $anchor = trim( (string) $anchor );
            if ( $anchor !== '' ) {
                $anchors[] = $anchor;
            }
        }
    }

    if ( is_array( $entities ) ) {
        foreach ( $entities as $entity ) {
            if ( is_array( $entity ) && ! empty( $entity['name'] ) ) {
                $anchors[] = trim( (string) $entity['name'] );
            } elseif ( is_string( $entity ) ) {
                $anchors[] = trim( $entity );
            }
        }
    }

    $anchors = array_filter( $anchors, function( $value ) {
        return $value !== '';
    } );

    return array_values( array_unique( $anchors ) );
}

/**
 * Find the first sentence index matching prioritized numeric terms.
 *
 * @param array $sentences Sentences list.
 * @param array $numbers Numeric terms.
 * @return int|null
 */
function find_sentence_index_for_numbers( $sentences, $numbers ) {
    if ( empty( $sentences ) || empty( $numbers ) ) {
        return null;
    }

    foreach ( $numbers as $num ) {
        foreach ( $sentences as $index => $sentence ) {
            if ( strpos( $sentence, $num ) !== false ) {
                return $index;
            }
        }
    }

    return null;
}
/**
 * Track generation metrics per post.
 *
 * @param int    $post_id Post ID.
 * @param string $status  Status string.
 * @return void
 */
function update_geo_generation_metrics( $post_id, $status ) {
    $metrics = get_post_meta( $post_id, '_geo_generation_metrics', true );
    if ( empty( $metrics ) || ! is_array( $metrics ) ) {
        $metrics = array(
            'total' => 0,
            'draft' => 0,
            'approved' => 0,
            'rejected' => 0,
            'last_run' => '',
        );
    }

    $metrics['total']++;
    if ( isset( $metrics[ $status ] ) ) {
        $metrics[ $status ]++;
    }
    $metrics['last_run'] = current_time( 'mysql' );

    update_post_meta( $post_id, '_geo_generation_metrics', $metrics );
}

/**
 * Build staging JSON-LD for draft answer cards (Phase 2).
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function get_post_answercards_staging( $request ) {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $post = get_post( $post_id );
    if ( ! $post ) {
        return new \WP_Error( 'post_not_found', 'Post not found', array( 'status' => 404 ) );
    }

    $cards = get_post_meta( $post_id, '_geo_answercards', true );
    if ( empty( $cards ) || ! is_array( $cards ) ) {
        return rest_ensure_response( array(
            'post_id' => $post_id,
            'staging' => array(),
        ) );
    }

    $page_url = get_permalink( $post_id );
    $faq_items = array();

    foreach ( $cards as $card ) {
        if ( empty( $card['preferred_summary'] ) ) {
            continue;
        }
        if ( ! empty( $card['generation_status'] ) && 'draft' !== $card['generation_status'] ) {
            continue;
        }

        $answer_text = $card['preferred_summary'];
        $answer_card_id = $card['answer_card_id'] ?? '';
        $question_text = $card['question'] ?? '';

        $accepted_answer = array(
            '@type' => 'Answer',
            'text'  => $answer_text,
        );
        if ( $answer_card_id ) {
            $accepted_answer['@id'] = $page_url . '#answer-' . $answer_card_id;
        }

        $faq_item = array(
            '@type'          => 'Question',
            'name'           => $question_text,
            'acceptedAnswer' => $accepted_answer,
        );
        if ( $answer_card_id ) {
            $faq_item['@id'] = $page_url . '#question-' . $answer_card_id;
        }

        $faq_items[] = $faq_item;
    }

    $schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        '@id'        => $page_url . '#faqpage-staging',
        'mainEntity' => $faq_items,
    );

    return rest_ensure_response( array(
        'post_id' => $post_id,
        'schema'  => $schema,
    ) );
}

/**
 * Approve a generated summary for a card (Phase 2).
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function approve_answercard_summary( $request ) {
    $payload = $request->get_json_params();
    if ( empty( $payload ) || ! is_array( $payload ) ) {
        return new \WP_Error( 'invalid_payload', 'Invalid request payload', array( 'status' => 400 ) );
    }

    $post_id = absint( $payload['post_id'] ?? 0 );
    $answer_card_id = sanitize_text_field( $payload['answer_card_id'] ?? '' );
    if ( ! $post_id || ! $answer_card_id ) {
        return new \WP_Error( 'missing_params', 'post_id and answer_card_id are required', array( 'status' => 400 ) );
    }

    $cards = get_post_meta( $post_id, '_geo_answercards', true );
    if ( empty( $cards ) || ! is_array( $cards ) ) {
        return new \WP_Error( 'no_cards', 'No answer cards found', array( 'status' => 404 ) );
    }

    $updated = false;
    foreach ( $cards as $index => $card ) {
        if ( ! empty( $card['answer_card_id'] ) && $card['answer_card_id'] === $answer_card_id ) {
            $card['generation_status'] = 'approved';
            if ( empty( $card['audit'] ) || ! is_array( $card['audit'] ) ) {
                $card['audit'] = array();
            }
            $card['audit'][] = array(
                'approved_at' => current_time( 'mysql' ),
                'approved_by' => get_current_user_id(),
                'pipeline'    => 'phase2_review',
                'status'      => 'approved',
            );
            $cards[ $index ] = $card;
            $updated = true;
            break;
        }
    }

    if ( ! $updated ) {
        return new \WP_Error( 'card_not_found', 'Answer card not found', array( 'status' => 404 ) );
    }

    update_post_meta( $post_id, '_geo_answercards', $cards );
    update_geo_generation_metrics( $post_id, 'approved' );

    if ( function_exists( '\\KHM\\Blocks\\AnswerCard\\persist_to_database' ) ) {
        \KHM\Blocks\AnswerCard\persist_to_database( $post_id, $cards );
    }

    return rest_ensure_response( array(
        'status' => 'approved',
        'answer_card_id' => $answer_card_id,
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

    return rest_ensure_response( $candidates );
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
    $answer_card_id = sanitize_text_field( $payload['answer_card_id'] ?? '' );
    $resolved_by = sanitize_text_field( $payload['resolved_by'] ?? 'editor' );
    $resolved_confidence = isset( $payload['resolved_confidence'] ) ? floatval( $payload['resolved_confidence'] ) : null;
    $resolved_method = sanitize_text_field( $payload['resolved_method'] ?? 'wikidata' );
    $job_id = sanitize_text_field( $payload['job_id'] ?? '' );
    $model_version = sanitize_text_field( $payload['model_version'] ?? '' );

    if ( empty( $entity_name ) || empty( $qid ) ) {
        return new \WP_Error( 'missing_params', 'entity_name and qid are required', array( 'status' => 400 ) );
    }

    $manager = new \KHM_SEO\GEO\Entity\EntityManager();
    $commit = $manager->commit_same_as( array(
        'canonical' => $entity_name,
        'qid'       => $qid,
        'label'     => $label,
        'provider'  => $provider,
        'type'      => 'Thing',
        'scope'     => 'site',
        'status'    => 'active',
    ) );

    if ( ! $commit ) {
        return new \WP_Error( 'entity_commit_failed', 'Failed to resolve entity', array( 'status' => 500 ) );
    }

    if ( $post_id && in_array( $page_role, array( 'about', 'primary' ), true ) ) {
        $manager->add_entity_to_post( $post_id, $commit['entity']->id, $page_role, 0.8, 'manual' );
    }

    error_log( sprintf(
        '[KHM GEO] Entity resolved: %s (%s) by user %d',
        $entity_name,
        $qid,
        get_current_user_id()
    ) );

    $resolved_at = gmdate( 'c' );
    if ( $post_id ) {
        update_card_entity_resolution( $post_id, $answer_card_id, $entity_name, array(
            'same_as'             => $commit['same_as']['url'],
            'resolved_by'         => $resolved_by,
            'resolved_confidence' => $resolved_confidence,
            'resolved_at'         => $resolved_at,
            'resolved_method'     => $resolved_method,
            'qid'                 => $qid,
            'label'               => $label,
            'job_id'              => $job_id,
            'model_version'       => $model_version,
        ), 'resolve' );
    }

    return rest_ensure_response( array(
        'entity' => $commit['entity'],
        'same_as' => $commit['same_as'],
        'resolved_at' => $resolved_at,
    ) );
}

/**
 * Unresolve entity and clear same_as for the card entity.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function unresolve_entity_candidate( $request ) {
    $payload = $request->get_json_params();
    if ( empty( $payload ) || ! is_array( $payload ) ) {
        return new \WP_Error( 'invalid_payload', 'Invalid request payload', array( 'status' => 400 ) );
    }

    $post_id = absint( $payload['post_id'] ?? 0 );
    $entity_name = sanitize_text_field( $payload['entity_name'] ?? '' );
    $qid = sanitize_text_field( $payload['qid'] ?? '' );
    $answer_card_id = sanitize_text_field( $payload['answer_card_id'] ?? '' );

    if ( ! $post_id || ! $entity_name ) {
        return new \WP_Error( 'missing_params', 'post_id and entity_name are required', array( 'status' => 400 ) );
    }

    $cleared_at = gmdate( 'c' );
    $updated = update_card_entity_resolution( $post_id, $answer_card_id, $entity_name, array(
        'same_as' => '',
        'resolved_by' => '',
        'resolved_confidence' => null,
        'resolved_at' => $cleared_at,
        'resolved_method' => '',
        'qid' => $qid,
    ), 'unresolve' );

    if ( ! $updated ) {
        return new \WP_Error( 'entity_unresolve_failed', 'Unable to unresolve entity', array( 'status' => 500 ) );
    }

    return rest_ensure_response( array(
        'status' => 'ok',
        'cleared_at' => $cleared_at,
    ) );
}

/**
 * Update card entity resolution metadata and append audit entry.
 *
 * @param int    $post_id Post ID.
 * @param string $answer_card_id Answer card ID.
 * @param string $entity_name Entity name.
 * @param array  $data Resolution payload.
 * @param string $action resolve|unresolve
 * @return bool
 */
function update_card_entity_resolution( $post_id, $answer_card_id, $entity_name, $data, $action = 'resolve' ) {
    $cards = get_post_meta( $post_id, '_geo_answercards', true );
    if ( empty( $cards ) || ! is_array( $cards ) ) {
        return false;
    }

    $entity_name_norm = strtolower( $entity_name );
    $updated = false;
    $audit_entry = array(
        'event' => $action === 'unresolve' ? 'entity_unresolved' : 'entity_resolved',
        'post_id' => $post_id,
        'answer_card_id' => $answer_card_id,
        'entity_name' => $entity_name,
        'qid' => $data['qid'] ?? '',
        'resolved_by' => $data['resolved_by'] ?? '',
        'resolved_confidence' => $data['resolved_confidence'] ?? null,
        'resolved_at' => $data['resolved_at'] ?? gmdate( 'c' ),
        'resolved_method' => $data['resolved_method'] ?? '',
        'job_id' => $data['job_id'] ?? '',
        'model_version' => $data['model_version'] ?? '',
        'user_id' => get_current_user_id(),
    );

    foreach ( $cards as $idx => $card ) {
        if ( $answer_card_id && ( $card['answer_card_id'] ?? '' ) !== $answer_card_id ) {
            continue;
        }
        if ( empty( $card['entities'] ) || ! is_array( $card['entities'] ) ) {
            continue;
        }

        foreach ( $card['entities'] as $e_idx => $entity ) {
            $name = is_array( $entity ) ? ( $entity['name'] ?? '' ) : (string) $entity;
            if ( ! $name || strtolower( $name ) !== $entity_name_norm ) {
                continue;
            }
            $entity_data = is_array( $entity ) ? $entity : array( 'name' => $name );
            $entity_data['same_as'] = $data['same_as'] ?? '';
            $entity_data['resolved_by'] = $data['resolved_by'] ?? '';
            $entity_data['resolved_confidence'] = array_key_exists( 'resolved_confidence', $data ) ? $data['resolved_confidence'] : null;
            $entity_data['resolved_at'] = $data['resolved_at'] ?? '';
            $entity_data['resolved_method'] = $data['resolved_method'] ?? '';
            $cards[ $idx ]['entities'][ $e_idx ] = $entity_data;
            $updated = true;
        }

        if ( $updated ) {
            $cards[ $idx ]['audit'] = isset( $cards[ $idx ]['audit'] ) && is_array( $cards[ $idx ]['audit'] )
                ? array_merge( $cards[ $idx ]['audit'], array( $audit_entry ) )
                : array( $audit_entry );
        }
    }

    if ( $updated ) {
        update_post_meta( $post_id, '_geo_answercards', $cards );
    }

    return $updated;
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
 * Recompute and persist score details for a post.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function recompute_post_score_details( $request ) {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $post    = get_post( $post_id );

    if ( ! $post ) {
        return new \WP_Error( 'post_not_found', 'Post not found', array( 'status' => 404 ) );
    }

    if ( ! function_exists( '\\KHM\\Blocks\\AnswerCard\\run_scoring_for_post' ) ) {
        return new \WP_Error( 'scoring_unavailable', 'Scoring is unavailable', array( 'status' => 500 ) );
    }

    $cards = get_post_meta( $post_id, '_geo_answercards', true );
    if ( empty( $cards ) || ! is_array( $cards ) ) {
        return new \WP_Error( 'no_cards', 'No answer cards found', array( 'status' => 404 ) );
    }

    call_user_func( '\\KHM\\Blocks\\AnswerCard\\run_scoring_for_post', $post_id, $cards );

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
