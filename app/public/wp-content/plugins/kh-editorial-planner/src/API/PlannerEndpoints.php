<?php

namespace KH\Planner\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KH\Planner\Agents\PlannerOrchestrator;
use KH\Planner\Core\TopLineCategoriesStore;

class PlannerEndpoints {

    private $categories_store;

    public function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        // Seed categories on init if empty
        add_action( 'init', [ $this, 'seed_categories_if_empty' ], 20 );
        // Migrate legacy dual_gpt option to new key
        add_action( 'init', [ $this, 'migrate_legacy_option' ], 5 );
        // Wire the filter so the legacy stub endpoint returns real data
        add_filter( 'kh_editorial_planner_top_line_categories', [ $this, 'get_categories_for_filter' ] );
    }

    /**
     * Migrate legacy dual_gpt_top_line_categories option to new key.
     * Runs once on init, then removes itself.
     */
    public function migrate_legacy_option() {
        $legacy_key = 'dual_gpt_top_line_categories';
        $new_key    = 'kh_planner_top_line_categories';

        $legacy = get_option( $legacy_key, false );
        if ( $legacy !== false && ! empty( $legacy ) ) {
            $current = get_option( $new_key, [] );
            if ( empty( $current ) ) {
                update_option( $new_key, $legacy, false );
            }
            delete_option( $legacy_key );
        }
    }

    public function register_routes() {
        register_rest_route( 'editorial/v1', '/sessions', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_sessions' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        register_rest_route( 'editorial/v1', '/sessions', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_session' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [ 
                'title' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ] 
            ]
        ] );

        register_rest_route( 'editorial/v1', '/frameworks', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_frameworks' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        register_rest_route( 'editorial/v1', '/pipeline', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_pipeline' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        register_rest_route( 'editorial/v1', '/sessions/(?P<id>\d+)/run', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_session' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/sessions/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_session_detail' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        // New DELETE route
        register_rest_route( 'editorial/v1', '/sessions/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_session' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // GET presets endpoint (Step 5)
        register_rest_route("editorial/v1", "/presets", [
            "methods" => "GET",
            "callback" => [$this, "get_presets"],
            "permission_callback" => [$this, "check_permission"],
        ]);

        // Export endpoints
        register_rest_route( 'editorial/v1', '/sessions/(?P<id>\d+)/export/(?P<format>[a-z]+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'export_session' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // Add POST jobs endpoint (Step 8) for khm-seo-agent
        register_rest_route("editorial/v1", "/jobs", [
            "methods" => "POST",
            "callback" => [$this, "create_job"],
            "permission_callback" => [$this, "check_permission"],
        ]);

        // GET top-line-categories (legacy stub — now returns real data via filter)
        register_rest_route( 'editorial/v1', '/top-line-categories', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_top_line_categories' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        // ─── Top-Line Categories CRUD (planner namespace — used by React UI) ───

        register_rest_route( 'editorial/v1', '/planner/top-line-categories', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'list_top_line_categories' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        register_rest_route( 'editorial/v1', '/planner/top-line-categories', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'save_top_line_category' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        register_rest_route( 'editorial/v1', '/planner/top-line-categories/import', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'import_top_line_categories' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        // GET pillars for a top-line category slug
        register_rest_route( 'editorial/v1', '/planner/top-line-categories/(?P<slug>[a-z0-9-]+)/pillars', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_category_pillars' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        // POST seed pillars from SiteAudienceProfile (admin)
        register_rest_route( 'editorial/v1', '/planner/top-line-categories/seed-pillars', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'seed_pillars' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );

        // ─── Content Gap Analysis Endpoints ─────────────────────────────

        // GET content-gaps/dashboard — summary across all audience sites
        register_rest_route( 'editorial/v1', '/content-gaps/dashboard', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_gap_dashboard' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'days_back' => [
                    'default'           => 730,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        // GET content-gaps/audit — detailed per-audience/pillar gap breakdown
        register_rest_route( 'editorial/v1', '/content-gaps/audit', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_gap_audit' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'audience_slug' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_title',
                ],
                'pillar_slug' => [
                    'sanitize_callback' => 'sanitize_title',
                ],
                'days_back' => [
                    'default'           => 730,
                    'sanitize_callback' => 'absint',
                ],
                'topic' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        // POST content-gaps/create-session — create planner session targeting a gap
        register_rest_route( 'editorial/v1', '/content-gaps/create-session', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_gap_session' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'topic' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'audience_slug' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_title',
                ],
                'pillar' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'pillar_slug' => [
                    'sanitize_callback' => 'sanitize_title',
                ],
            ],
        ] );
    }

    public function check_permission() {
        return current_user_can( 'edit_posts' );
    }

    // ─── Top-Line Categories Store ──────────────────────────────────────

    private function get_store(): TopLineCategoriesStore {
        if ( ! $this->categories_store ) {
            $this->categories_store = new TopLineCategoriesStore();
        }
        return $this->categories_store;
    }

    /**
     * Seed categories from legacy backup on init if store is empty.
     */
    public function seed_categories_if_empty() {
        $this->get_store()->seed_if_empty();
    }

    /**
     * Filter callback for kh_editorial_planner_top_line_categories.
     */
    public function get_categories_for_filter() {
        return $this->get_store()->get_all();
    }

    /**
     * GET editorial/v1/top-line-categories (legacy stub — now real).
     */
    public function get_top_line_categories() {
        $categories = apply_filters( 'kh_editorial_planner_top_line_categories', [] );
        return rest_ensure_response( [ 'top_line_categories' => $categories ] );
    }

    /**
     * GET editorial/v1/planner/top-line-categories — list all.
     */
    public function list_top_line_categories() {
        $categories = $this->get_store()->get_all();
        return rest_ensure_response( [ 'top_line_categories' => $categories ] );
    }

    /**
     * POST editorial/v1/planner/top-line-categories — save or update.
     */
    public function save_top_line_category( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $category = $params['top_line_category'] ?? $params ?? [];

        if ( empty( $category['name'] ) ) {
            return new \WP_Error( 'missing_name', 'Category name is required.', [ 'status' => 400 ] );
        }

        $ok = $this->get_store()->save( $category );
        if ( ! $ok ) {
            return new \WP_Error( 'save_failed', 'Failed to save category.', [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'ok' => true, 'category' => $category ] );
    }

    /**
     * POST editorial/v1/planner/top-line-categories/import — bulk import.
     */
    public function import_top_line_categories( \WP_REST_Request $request ) {
        $params = $request->get_json_params();

        $result = [ 'created_or_updated' => 0, 'skipped' => 0 ];

        if ( ! empty( $params['csv'] ) ) {
            $result = $this->get_store()->import_csv( $params['csv'] );
        } elseif ( ! empty( $params['rows'] ) && is_array( $params['rows'] ) ) {
            $result = $this->get_store()->import_rows( $params['rows'] );
        }

        return rest_ensure_response( $result );
    }

    // ─── Existing Endpoints ────────────────────────────────────────────

    public function export_session( $request ) {
        $session_id = $request->get_param( 'id' );
        $format     = $request->get_param( 'format' );

        $agent = new \KH\Planner\Agents\ExportAgent();

        if ( $format === 'docx' ) {
            $result = $agent->export_to_docx( $session_id );
        } elseif ( $format === 'html' ) {
            $result = $agent->export_to_html( $session_id );
        } else {
            return new \WP_Error( 'invalid_format', 'Invalid export format' );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public function get_sessions( $request ) {
        $limit = intval( $request->get_param( 'limit' ) ?: 20 );
        $args  = [
            'post_type'      => 'planner_session',
            'posts_per_page' => $limit,
            'post_status'    => 'any'
        ];
        
        $posts = get_posts( $args );
        
        $out = array_map( function( $p ) {
            $meta = [
                'role'       => get_post_meta( $p->ID, 'kh_planner_role', true ) ?: 'research',
                'preset_id'  => get_post_meta( $p->ID, 'kh_planner_preset_id', true ) ?: 'research-default',
                'status'     => get_post_meta( $p->ID, 'kh_planner_status', true ) ?: 'draft',
                'created_by' => (int) get_post_meta( $p->ID, 'created_by', true ) ?: (int) $p->post_author,
            ];
            return [
                'id'         => (string) $p->ID,
                'session_id' => (string) $p->ID,
                'title'      => $p->post_title,
                'role'       => $meta['role'],
                'preset_id'  => $meta['preset_id'],
                'created_at' => $p->post_date,
                'updated_at' => $p->post_modified,
                'status'     => $meta['status'],
                'meta'       => $meta,
            ];
        }, $posts );

        return rest_ensure_response( $out );
    }

    public function create_session( $request ) {
        $params = $request->get_json_params();
        $title  = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
        $role   = isset( $params['role'] ) ? sanitize_text_field( $params['role'] ) : 'research';
        $preset_id = isset( $params['preset_id'] ) ? sanitize_text_field( $params['preset_id'] ) : null;
        $meta_input = isset( $params['meta'] ) && is_array( $params['meta'] ) ? $params['meta'] : [];

        if ( empty( $title ) ) {
            return new \WP_Error( 'missing_title', 'Title is required', [ 'status' => 400 ] );
        }

        $post_id = wp_insert_post( [
            'post_type'   => 'planner_session',
            'post_title'  => $title,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            error_log( '[PLANNER] wp_insert_post failed: ' . $post_id->get_error_message() );
            return new \WP_Error( 'insert_failed', $post_id->get_error_message(), [ 'status' => 500 ] );
        }

        update_post_meta( $post_id, 'kh_planner_status', 'draft' );
        update_post_meta( $post_id, 'created_by', get_current_user_id() );
        update_post_meta( $post_id, 'kh_planner_role', $role );
        if ( $preset_id ) {
            update_post_meta( $post_id, 'kh_planner_preset_id', $preset_id );
        }
        if ( ! empty( $meta_input ) ) {
            update_post_meta( $post_id, 'kh_planner_meta', wp_json_encode( $meta_input ) );
        }

        // Persist pillar and audience meta from meta_input if provided
        if ( ! empty( $meta_input['pillar'] ) ) {
            update_post_meta( $post_id, 'kh_planner_pillar', sanitize_text_field( $meta_input['pillar'] ) );
        }
        if ( ! empty( $meta_input['pillar_slug'] ) ) {
            update_post_meta( $post_id, 'kh_planner_pillar_slug', sanitize_text_field( $meta_input['pillar_slug'] ) );
        }
        if ( ! empty( $meta_input['audience_slug'] ) ) {
            update_post_meta( $post_id, 'kh_planner_audience_slug', sanitize_text_field( $meta_input['audience_slug'] ) );
        }

        // Auto-resolve audience_slug from top-line category if not explicitly provided
        if ( empty( $meta_input['audience_slug'] ) || empty( get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ) ) {
            $store = new TopLineCategoriesStore();
            $categories = $store->get_all();
            foreach ( $categories as $cat ) {
                if ( strcasecmp( $cat['name'] ?? '', $title ) === 0 ) {
                    $profile_slug = $cat['site_slug'] ?? $cat['slug'] ?? '';
                    if ( $profile_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
                        $profile = \KH\Editorial\Services\SiteAudienceProfile::get_profile( $profile_slug );
                        if ( $profile ) {
                            update_post_meta( $post_id, 'kh_planner_audience_slug', $profile_slug );
                        }
                    }
                    break;
                }
            }
        }

        // Read back resolved values for response transparency
        $resolved_audience_slug = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';
        $resolved_pillar        = get_post_meta( $post_id, 'kh_planner_pillar', true ) ?: '';
        $resolved_pillar_slug   = get_post_meta( $post_id, 'kh_planner_pillar_slug', true ) ?: '';

        return rest_ensure_response( [
            'session_id'    => (string) $post_id,
            'id'            => (string) $post_id,
            'role'          => $role,
            'preset_id'     => $preset_id,
            'audience_slug' => $resolved_audience_slug,
            'pillar'        => $resolved_pillar,
            'pillar_slug'   => $resolved_pillar_slug,
        ] );
    }

    public function delete_session( $request ) {
        $id = (int) $request['id'];
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'planner_session' ) {
            return new \WP_Error( 'not_found', 'Session not found', [ 'status' => 404 ] );
        }

        $deleted = wp_delete_post( $id, true );
        if ( ! $deleted ) {
            return new \WP_Error( 'delete_failed', 'Failed to delete session', [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'deleted' => true, 'session_id' => (string) $id ] );
    }

    public function get_frameworks() {
        return rest_ensure_response( [] );
    }

    public function get_pipeline() {
        return rest_ensure_response( [] );
    }

    public function get_presets() {
        $presets = apply_filters( 'kh_editorial_planner_presets', [] );
        return rest_ensure_response( $presets );
    }

    public function create_job( $request ) {
        $params = $request->get_json_params();
        return rest_ensure_response( [
            'job_id' => uniqid( 'job_', true ),
            'status' => 'queued',
        ] );
    }

    public function run_session( $request ) {
        $id = $request['id'];
        $orchestrator = new PlannerOrchestrator();
        $result = $orchestrator->run( $id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    /**
     * GET editorial/v1/planner/top-line-categories/{slug}/pillars
     *
     * Returns pillars for a given category slug, merging from SiteAudienceProfile.
     */
    public function get_category_pillars( \WP_REST_Request $request ) {
        $slug = $request->get_param( 'slug' );

        if ( empty( $slug ) ) {
            return new \WP_Error( 'missing_slug', 'Category slug is required.', [ 'status' => 400 ] );
        }

        $pillars = $this->get_store()->get_pillars( $slug );

        return rest_ensure_response( [
            'slug'    => $slug,
            'pillars' => $pillars,
        ] );
    }

    /**
     * POST editorial/v1/planner/top-line-categories/seed-pillars
     *
     * Seeds pillar data from SiteAudienceProfile for all matching categories.
     */
    public function seed_pillars() {
        $updated = $this->get_store()->seed_pillars_from_profiles();

        return rest_ensure_response( [
            'updated' => $updated,
            'message' => sprintf( 'Pillars seeded for %d categories from audience profiles.', $updated ),
        ] );
    }

    public function get_session_detail( $request ) {
        $id = $request['id'];
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'planner_session' ) {
            return new \WP_Error( 'not_found', 'Session not found', [ 'status' => 404 ] );
        }

        return rest_ensure_response( [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'status'  => get_post_meta( $id, 'kh_planner_status', true ) ?: 'draft',
            'results' => [
                'phase1' => get_post_meta( $id, 'kh_planner_phase1_result', true ),
                'phase2' => get_post_meta( $id, 'kh_planner_phase2_result', true ),
                'phase3' => get_post_meta( $id, 'kh_planner_phase3_result', true ),
                'phase4' => get_post_meta( $id, 'kh_planner_phase4_result', true ),
                'synopses' => get_post_meta( $id, 'kh_planner_final_synopses', true ),
            ]
        ] );
    }

    // ─── Content Gap Analysis Callbacks ─────────────────────────────────

    /**
     * GET editorial/v1/content-gaps/dashboard
     *
     * Returns a high-level coverage summary across ALL audience sites,
     * showing how many posts exist per site and the overall gap count
     * from an unscoped broad query (no topic — just counts total posts
     * per audience and per pillar).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_gap_dashboard( \WP_REST_Request $request ) {
        $days_back = $request->get_param( 'days_back' ) ?: 730;

        if ( ! class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            return rest_ensure_response( [ 'audiences' => [], 'summary' => 'SiteAudienceProfile not available.' ] );
        }

        if ( ! class_exists( '\KH\Editorial\Services\AllocationService' ) ) {
            return rest_ensure_response( [ 'audiences' => [], 'summary' => 'AllocationService not available.' ] );
        }

        $alloc = new \KH\Editorial\Services\AllocationService();
        $site_slugs = array_keys( \KH\Editorial\Services\AllocationService::TARGET_SITES );
        $date_cutoff = gmdate( 'Y-m-d', strtotime( "-{$days_back} days" ) );
        $post_types = [ 'post', 'atomic_article' ];

        $audience_data = [];
        $processed = 0;
        $max_sites = 10; // Safety cap

        foreach ( $site_slugs as $slug ) {
            if ( $processed >= $max_sites ) {
                break;
            }

            $profile = \KH\Editorial\Services\SiteAudienceProfile::get_profile( $slug );
            if ( ! $profile ) {
                continue;
            }

            $blog_id = $alloc->resolve_blog_id( $slug );
            $label = $profile['label'] ?? $slug;

            if ( ! $blog_id ) {
                $audience_data[ $slug ] = [
                    'slug'        => $slug,
                    'label'       => $label,
                    'blog_id'     => null,
                    'error'       => 'No blog mapping found.',
                    'total_posts' => 0,
                    'pillars'     => [],
                ];
                $processed++;
                continue;
            }

            // Switch to the target blog to count its posts
            $switched = false;
            if ( is_multisite() ) {
                switch_to_blog( $blog_id );
                $switched = true;
            }

            // Count total published posts for this site (date-windowed) — single fast query
            $total_args = [
                'post_type'           => $post_types,
                'post_status'         => 'publish',
                'posts_per_page'      => 1,
                'fields'              => 'ids',
                'date_query'          => [
                    [ 'after' => $date_cutoff, 'inclusive' => true ],
                ],
                'no_found_rows'       => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'suppress_filters'    => true,
            ];
            $total_query = new \WP_Query( $total_args );
            $total_posts = (int) $total_query->found_posts;

            // Count posts per pillar (single query per pillar — acceptable for dashboard overview)
            $pillar_data = [];
            $pillars = $profile['pillars'] ?? [];

            foreach ( $pillars as $pillar ) {
                $pillar_slug = sanitize_title( $pillar['name'] ?? '' );
                if ( empty( $pillar_slug ) || ! taxonomy_exists( 'tp_pillar' ) ) {
                    $pillar_data[] = [
                        'name'        => $pillar['name'] ?? $pillar_slug,
                        'slug'        => $pillar_slug,
                        'total_posts' => 0,
                        'term_exists' => false,
                    ];
                    continue;
                }

                $term = get_term_by( 'slug', $pillar_slug, 'tp_pillar' );
                if ( ! $term ) {
                    $pillar_data[] = [
                        'name'        => $pillar['name'] ?? $pillar_slug,
                        'slug'        => $pillar_slug,
                        'total_posts' => 0,
                        'term_exists' => false,
                    ];
                    continue;
                }

                $pillar_args = array_merge( $total_args, [
                    'tax_query' => [
                        [
                            'taxonomy' => 'tp_pillar',
                            'field'    => 'slug',
                            'terms'    => $pillar_slug,
                        ],
                    ],
                ] );
                $pillar_query = new \WP_Query( $pillar_args );

                $pillar_data[] = [
                    'name'        => $pillar['name'] ?? $pillar_slug,
                    'slug'        => $pillar_slug,
                    'total_posts' => (int) $pillar_query->found_posts,
                    'term_exists' => true,
                ];
            }

            if ( $switched ) {
                restore_current_blog();
            }

            $audience_data[ $slug ] = [
                'slug'        => $slug,
                'label'       => $label,
                'blog_id'     => $blog_id,
                'total_posts' => $total_posts,
                'pillars'     => $pillar_data,
                'date_range'  => "Last {$days_back} days (since {$date_cutoff})",
            ];

            $processed++;
        }

        return rest_ensure_response( [
            'audiences' => $audience_data,
            'days_back' => $days_back,
            'date_from' => $date_cutoff,
        ] );
    }

    /**
     * GET editorial/v1/content-gaps/audit
     *
     * Runs a detailed content gap analysis for a specific audience site,
     * optionally scoped to a pillar. Accepts a topic to search for;
     * otherwise returns per-pillar post counts.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_gap_audit( \WP_REST_Request $request ) {
        $audience_slug = $request->get_param( 'audience_slug' );
        $pillar_slug   = $request->get_param( 'pillar_slug' ) ?: '';
        $days_back     = $request->get_param( 'days_back' ) ?: 730;
        $topic         = $request->get_param( 'topic' ) ?: '';

        if ( ! class_exists( '\KH\Editorial\Services\AllocationService' ) ) {
            return new \WP_Error( 'service_missing', 'AllocationService not available.', [ 'status' => 500 ] );
        }

        $alloc   = new \KH\Editorial\Services\AllocationService();
        $blog_id = $alloc->resolve_blog_id( $audience_slug );

        if ( ! $blog_id ) {
            return new \WP_Error( 'no_blog', "No blog mapping found for audience '{$audience_slug}'.", [ 'status' => 404 ] );
        }

        // Resolve pillar display name from pillar_slug
        $pillar_name = '';
        if ( $pillar_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $profile = \KH\Editorial\Services\SiteAudienceProfile::get_profile( $audience_slug );
            if ( $profile ) {
                foreach ( $profile['pillars'] ?? [] as $p ) {
                    if ( sanitize_title( $p['name'] ?? '' ) === $pillar_slug ) {
                        $pillar_name = $p['name'];
                        break;
                    }
                }
            }
        }

        // Use the topic provided, or fall back to the audience slug as a broad topic
        $search_topic = ! empty( $topic ) ? $topic : $audience_slug;

        // Use ResearchAgent to build full coverage analysis
        $research_agent = new \KH\Planner\Agents\ResearchAgent();
        $coverage = $research_agent->build_internal_content_coverage(
            $search_topic,                 // topic
            [],                            // includes
            '',                            // subgroup
            [],                            // candidate_keywords
            $pillar_name,                  // pillar
            $pillar_slug,                  // pillar_slug
            $audience_slug,                // audience_slug
            $days_back,                    // days_back
            $blog_id                       // blog_id
        );

        // Get the audience label
        $label = $audience_slug;
        if ( class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $profile_label = \KH\Editorial\Services\SiteAudienceProfile::get_label( $audience_slug );
            if ( $profile_label ) {
                $label = $profile_label;
            }
        }

        return rest_ensure_response( [
            'audience_slug'       => $audience_slug,
            'label'               => $label,
            'blog_id'             => $blog_id,
            'pillar_slug'         => $pillar_slug,
            'pillar_name'         => $pillar_name,
            'days_back'           => $days_back,
            'internal_coverage'   => $coverage,
        ] );
    }

    /**
     * POST editorial/v1/content-gaps/create-session
     *
     * Creates a new planner session pre-configured with audience and pillar
     * context, targeting a specific gap topic. Returns the session_id so
     * the UI can navigate to it or offer to run it immediately.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_gap_session( \WP_REST_Request $request ) {
        $topic         = $request->get_param( 'topic' );
        $audience_slug = $request->get_param( 'audience_slug' );
        $pillar        = $request->get_param( 'pillar' ) ?: '';
        $pillar_slug   = $request->get_param( 'pillar_slug' ) ?: '';

        if ( empty( $topic ) || empty( $audience_slug ) ) {
            return new \WP_Error( 'missing_params', 'Topic and audience_slug are required.', [ 'status' => 400 ] );
        }

        $post_id = wp_insert_post( [
            'post_type'   => 'planner_session',
            'post_title'  => $topic,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return new \WP_Error( 'insert_failed', $post_id->get_error_message(), [ 'status' => 500 ] );
        }

        // Set standard session meta
        update_post_meta( $post_id, 'kh_planner_status', 'draft' );
        update_post_meta( $post_id, 'created_by', get_current_user_id() );
        update_post_meta( $post_id, 'kh_planner_role', 'research' );

        // Set pillar and audience context
        if ( $pillar ) {
            update_post_meta( $post_id, 'kh_planner_pillar', sanitize_text_field( $pillar ) );
        }
        if ( $pillar_slug ) {
            update_post_meta( $post_id, 'kh_planner_pillar_slug', sanitize_text_field( $pillar_slug ) );
        }
        if ( $audience_slug ) {
            update_post_meta( $post_id, 'kh_planner_audience_slug', sanitize_text_field( $audience_slug ) );
        }

        // Store gap origin context so we know this session was spawned from the gap dashboard
        update_post_meta( $post_id, 'kh_planner_gap_origin', [
            'audience_slug' => $audience_slug,
            'pillar'        => $pillar,
            'pillar_slug'   => $pillar_slug,
            'created_at'    => current_time( 'mysql' ),
        ] );

        return rest_ensure_response( [
            'session_id'   => (string) $post_id,
            'id'           => (string) $post_id,
            'title'        => $topic,
            'audience_slug'=> $audience_slug,
            'pillar'       => $pillar,
            'pillar_slug'  => $pillar_slug,
            'status'       => 'draft',
            'message'      => 'New planner session created targeting this content gap.',
        ] );
    }
}
