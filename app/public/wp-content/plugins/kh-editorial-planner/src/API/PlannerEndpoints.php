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
        add_action( 'rest_api_init', [ $this, 'register_routes' ], 99 );
        // Seed categories on init if empty
        add_action( 'init', [ $this, 'seed_categories_if_empty' ], 20 );
        // Wire the filter so the legacy stub endpoint returns real data
        add_filter( 'kh_editorial_planner_top_line_categories', [ $this, 'get_categories_for_filter' ] );
        // Flush rewrite rules on admin init to ensure new REST routes are picked up
        add_action( 'admin_init', [ $this, 'maybe_flush_on_admin' ] );
    }

    public function maybe_flush_on_admin() {
        $flushed = get_option( 'kh_planner_routes_flushed_v3', false );
        if ( ! $flushed ) {
            flush_rewrite_rules( true );
            update_option( 'kh_planner_routes_flushed_v3', true );
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

        register_rest_route( 'editorial/v1', '/sessions/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_session' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // ─── Phase API Endpoints ──────────────────────────────────────
        register_rest_route( 'editorial/v1', '/planner/phase1', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_phase1' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/phase2', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_phase2' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/phase2-qualification', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_phase2' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/phase3', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_phase3' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/phase4', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_phase4' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // ─── Queue Endpoints ─────────────────────────────────────────────

        register_rest_route( 'editorial/v1', '/planner/queue', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_planner_queue' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/queue/add', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'queue_add' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/queue/run', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'queue_run' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/queue/run-bulk', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'queue_run_bulk' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/queue/reorder', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'queue_reorder' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/queue/remove', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'queue_remove' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/queue/stop', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'queue_stop' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/queue/remove-all', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'queue_remove_all' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/queue/clear', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'queue_clear' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // ─── Research / Policy / Author Endpoints ─────────────────────────

        register_rest_route( 'editorial/v1', '/planner/research-validation', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_research_validation' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/author-policy', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_author_policy' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/author-policy', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'save_author_policy' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/policy', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'save_policy' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/run-framework', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_framework' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/synopsis-plan', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'synopsis_plan' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/synopses', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_synopses' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/export', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'planner_export' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/export-synopses', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'export_synopses' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/export-framework', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'export_framework' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );


        register_rest_route( 'editorial/v1', '/planner/convert-to-pdf', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'convert_html_to_pdf' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/convert-to-docx', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'convert_html_to_docx' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/run-author', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_author' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'editorial/v1', '/planner/article-action', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'article_action' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // POST editorial/v1/planner/dispatch-job — async job dispatch
        // Called via wp_remote_post(blocking=false) for jobs that take too long
        // to run synchronously (e.g. dive_deeper on free model deepseek/deepseek-v4-flash
        // which can take 60-120s). Permission is public because this is an internal
        // fire-and-forget call that passes the job_id (a UUID) as proof of intent.
        register_rest_route( 'editorial/v1', '/planner/dispatch-job', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'dispatch_job' ],
            'permission_callback' => '__return_true',
        ] );

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

        // ─── Summaries Dashboard Endpoint ────────────────────────────────
        register_rest_route( 'editorial/v1', '/planner/summaries', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_summaries' ],
            'permission_callback' => [ $this, 'check_permission' ],
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

    // ─── Top-Line Categories Store ──────────────────────────────────────

    private function get_store(): TopLineCategoriesStore {
        if ( ! $this->categories_store ) {
            $this->categories_store = new TopLineCategoriesStore();
        }
        return $this->categories_store;
    }

    /**
     * Seed categories from SiteAudienceProfile on init if store is empty.
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

        // Support legacy format names and the newer "word" alias.
        // "word" should produce a DOCX file, not HTML.
        if ( $format === 'docx' ) {
            $result = $agent->export_to_docx( $session_id );
        } elseif ( $format === 'word' ) {
            // Use the explicit word export method which returns a DOCX.
            $result = $agent->export_to_word( $session_id );
        } elseif ( $format === 'html' ) {
            $result = $agent->export_to_html( $session_id );
        } elseif ( $format === 'pdf' ) {
            $result = $agent->export_to_pdf( $session_id );
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
            $meta_raw  = get_post_meta( $p->ID, 'kh_planner_meta', true );
            // get_post_meta may return an array if the value was stored as
            // PHP-serialized array (unserialized by WP) — guard against that.
            if ( is_array( $meta_raw ) ) {
                $meta = $meta_raw;
                $meta_json = null;
            } elseif ( is_string( $meta_raw ) && $meta_raw !== '' ) {
                $decoded = json_decode( $meta_raw, true );
                $meta = ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) ? $decoded : [];
                $meta_json = $meta_raw;
            } else {
                $meta = [];
                $meta_json = null;
            }
            $articles  = $meta['articles'] ?? [];

            return [
                'id'            => (string) $p->ID,
                'session_id'    => (string) $p->ID,
                'title'         => $p->post_title,
                'created_at'    => $p->post_date,
                'updated_at'    => $p->post_modified,
                'status'        => get_post_meta( $p->ID, 'kh_planner_status', true ) ?: 'draft',
                'pillar'        => get_post_meta( $p->ID, 'kh_planner_pillar', true ) ?: '',
                'article_count' => is_array( $articles ) ? count( $articles ) : 0,
            ];
        }, $posts );

        return rest_ensure_response( $out );
    }

    public function create_session( $request ) {
        $params = $request->get_json_params();
        $title  = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
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

    public function run_phase1( $request ) {
        $params = $request->get_json_params();
        $id = $params['id'] ?? 0;
        if ( ! $id ) {
            return new \WP_Error( 'missing_id', 'Session ID is required.', [ 'status' => 400 ] );
        }
        $orchestrator = new PlannerOrchestrator();
        $result = $orchestrator->run( $id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( $result );
    }

    public function run_phase2( $request ) {
        $params = $request->get_json_params();
        $id = $params['id'] ?? 0;
        if ( ! $id ) {
            return new \WP_Error( 'missing_id', 'Session ID is required.', [ 'status' => 400 ] );
        }
        $orchestrator = new PlannerOrchestrator();
        $result = $orchestrator->run_phase2( $id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( $result );
    }

    public function run_phase3( $request ) {
        $params = $request->get_json_params();
        $id = $params['id'] ?? 0;
        if ( ! $id ) {
            return new \WP_Error( 'missing_id', 'Session ID is required.', [ 'status' => 400 ] );
        }
        $orchestrator = new PlannerOrchestrator();
        $result = $orchestrator->run_phase3( $id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( $result );
    }

    public function run_phase4( $request ) {
        $params = $request->get_json_params();
        $id = $params['id'] ?? 0;
        if ( ! $id ) {
            return new \WP_Error( 'missing_id', 'Session ID is required.', [ 'status' => 400 ] );
        }
        $orchestrator = new PlannerOrchestrator();
        $result = $orchestrator->run_phase4( $id );
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
        $id   = $request['id'];
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'planner_session' ) {
            return new \WP_Error( 'not_found', 'Session not found', [ 'status' => 404 ] );
        }

        $status   = get_post_meta( $id, 'kh_planner_status', true ) ?: 'draft';
        $phase1   = get_post_meta( $id, 'kh_planner_phase1_result', true ) ?: null;
        $phase2   = get_post_meta( $id, 'kh_planner_phase2_result', true ) ?: null;
        $phase3   = get_post_meta( $id, 'kh_planner_phase3_result', true ) ?: null;
        $phase4   = get_post_meta( $id, 'kh_planner_phase4_result', true ) ?: null;
        $synopses = get_post_meta( $id, 'kh_planner_final_synopses', true ) ?: null;

        // Derive per-phase status — 'completed' when a result exists, 'running' when
        // the overall session status indicates that phase is executing, else 'pending'.
        $phases = [
            'phase1' => [
                'status'  => $phase1 ? 'completed' : ( $status === 'phase1_running' ? 'running' : 'pending' ),
                'payload' => $phase1,
            ],
            'phase2' => [
                'status'  => $phase2 ? 'completed' : ( $status === 'phase2_running' ? 'running' : 'pending' ),
                'payload' => $phase2,
            ],
            'phase3' => [
                'status'  => $phase3 ? 'completed' : ( $status === 'phase3_running' ? 'running' : 'pending' ),
                'payload' => $phase3,
            ],
            'phase4' => [
                'status'  => $phase4 ? 'completed' : ( $status === 'phase4_running' ? 'running' : 'pending' ),
                'payload' => $phase4,
            ],
        ];

        // Load the existing kh_planner_meta JSON blob (may contain articles, research_policy, etc.)
        $meta_base = $this->get_planner_meta_array( $id );

        // Load dive deeper research results
        $dives = get_post_meta( $id, 'kh_planner_dives', true ) ?: [];

        // Load author plugin data (_kh_planner_data) which contains author output, edit_url, etc.
        // This data is written by kh-editorial-author plugin and needs to be merged with kh_planner_meta
        // so the frontend can display draft previews and edit URLs.
        $author_meta = get_post_meta( $id, '_kh_planner_data', true );
        if ( is_array( $author_meta ) ) {
            // Merge author data into meta_base - author data takes precedence for article fields
            // This ensures article.author.output, article.edit_url, article.status are available
            $meta_base = $this->merge_author_meta_with_planner_meta( $meta_base, $author_meta );
        }

        // Merge phases and flat phase aliases so JS can read either
        // sessionDetail.meta.phases.phase1.status  OR  sessionDetail.meta.phase1.trends
        $meta = array_merge( $meta_base, [
            'phases' => $phases,
            // Flat aliases for legacy JS access paths (e.g. meta?.phase1?.candidate_keywords)
            'phase1' => $phase1,
            'phase2' => $phase2,
            'phase3' => $phase3,
            'phase4' => $phase4,
            'dives'  => $dives,
        ] );

        // Convert synopses to meta.articles entries so the JS renderArticlesTable can display them.
        // Persist back to post meta so run_framework_generation() etc. can find the articles.
        $articles_persisted = false;
        if ( $synopses && is_array( $synopses['synopses'] ?? null ) ) {
            $existing_articles = $meta['articles'] ?? [];
            $existing_ids      = wp_list_pluck( $existing_articles, 'id' );
            $articles          = $existing_articles;

            foreach ( $synopses['synopses'] as $i => $s ) {
                $slug = sanitize_title( $s['headline'] ?? ( 'synopsis-' . $i ) );
                if ( in_array( $slug, $existing_ids, true ) ) {
                    continue; // already exists
                }
                $articles[] = [
                    'id'             => $slug,
                    'headline'       => $s['headline'] ?? '',
                    'summary'        => $s['summary'] ?? '',
                    'key_points'     => $s['key_points'] ?? [],
                    'keywords'       => $s['keywords'] ?? ( $s['target_keywords'] ?? [] ),
                    'priority_score' => $s['priority_score'] ?? 0.0,
                    'citations'      => $s['citations'] ?? [],
                    'synopsis'       => $s,
                ];
            }

            // Only persist if we actually added new articles (no-op if everything already existed)
            if ( count( $articles ) > count( $existing_articles ) ) {
                $meta['articles'] = $articles;
                update_post_meta( $id, 'kh_planner_meta', wp_json_encode( $meta ) );
                $articles_persisted = true;
            }
        }

        // Enrich articles with live dive_deeper job status from AI job table
        $meta = $this->enrich_articles_with_dive_status( $meta );

        // Fallback: if articles have dive_deeper_jobs from the AI job table,
        // also merge in any dive results stored directly in kh_planner_dives
        // that match the article headline. This catches runs where the job
        // reference wasn't linked (e.g. before the synopsis→article fix).
        if ( ! empty( $dives ) && ! empty( $meta['articles'] ) ) {
            $meta = $this->merge_stored_dives_into_articles( $meta, $dives );
        }

        // Enrich articles with completed framework results from the AI job table.
        // This catches runs where the fw- idempotency key handler wasn't present
        // (e.g. before the AIWorker fix was deployed).
        $meta = $this->enrich_articles_with_framework_results( $meta, $id );

        return rest_ensure_response( [
            'id'     => $post->ID,
            'title'  => $post->post_title,
            'status' => $status,
            'meta'   => $meta,
            // Keep 'results' for backward compatibility
            'results' => [
                'phase1'   => $phase1,
                'phase2'   => $phase2,
                'phase3'   => $phase3,
                'phase4'   => $phase4,
                'synopses' => $synopses,
            ],
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

    // ─── Queue Callbacks ─────────────────────────────────────────────────

    /**
     * GET editorial/v1/planner/queue
     * Returns current queue counts and active items for this session.
     */
    public function get_planner_queue( \WP_REST_Request $request ) {
        $session_id = $request->get_param( 'id' ) ?: 0;
        $items = [];

        if ( $session_id ) {
            $raw = get_post_meta( (int) $session_id, 'kh_planner_queue', true );
            if ( is_array( $raw ) ) {
                $items = $raw;
            }
        }

        $counts = [ 'queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0 ];
        foreach ( $items as $item ) {
            $status = $item['status'] ?? 'queued';
            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ]++;
            }
        }

        return rest_ensure_response( [
            'counts'       => $counts,
            'active_items' => $items,
        ] );
    }

    /**
     * POST editorial/v1/planner/queue/add
     */
    public function queue_add( \WP_REST_Request $request ) {
        $params    = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );
        $task_type  = sanitize_text_field( $params['task_type'] ?? '' );
        $article_id = sanitize_text_field( $params['article_id'] ?? '' );

        if ( ! $session_id || ! $task_type ) {
            return new \WP_Error( 'missing_params', 'id and task_type are required.', [ 'status' => 400 ] );
        }

        $queue_id = uniqid( 'q_', true );
        $item = [
            'id'         => $queue_id,
            'task_type'  => $task_type,
            'article_id' => $article_id,
            'status'     => 'queued',
            'created_at' => current_time( 'mysql' ),
        ];
        if ( ! empty( $params['payload'] ) ) {
            $item['payload'] = $params['payload'];
        }

        $queue = get_post_meta( $session_id, 'kh_planner_queue', true );
        if ( ! is_array( $queue ) ) {
            $queue = [];
        }
        $queue[] = $item;
        update_post_meta( $session_id, 'kh_planner_queue', $queue );

        return rest_ensure_response( [ 'ok' => true, 'queue_id' => $queue_id, 'item' => $item ] );
    }

    /**
     * POST editorial/v1/planner/queue/run
     * 
     * Processes a single queue item. For framework_generation tasks,
     * delegates to PlannerOrchestrator::run_framework_generation().
     */
    public function queue_run( \WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $queue_id = sanitize_text_field( $params['queue_id'] ?? '' );
        $session_id = (int) ( $params['id'] ?? 0 );

        if ( ! $queue_id ) {
            return new \WP_Error( 'missing_queue_id', 'queue_id is required.', [ 'status' => 400 ] );
        }

        // Locate the queue item in session meta
        $queue = get_post_meta( $session_id, 'kh_planner_queue', true );
        if ( ! is_array( $queue ) ) {
            return new \WP_Error( 'queue_empty', 'No queue items found.', [ 'status' => 404 ] );
        }

        $item = null;
        $item_idx = null;
        foreach ( $queue as $idx => $qi ) {
            if ( ( $qi['id'] ?? '' ) === $queue_id ) {
                $item = $qi;
                $item_idx = $idx;
                break;
            }
        }
        if ( ! $item ) {
            return new \WP_Error( 'queue_item_not_found', 'Queue item not found.', [ 'status' => 404 ] );
        }

        // Mark as running
        $queue[ $item_idx ]['status'] = 'running';
        $queue[ $item_idx ]['started_at'] = current_time( 'mysql' );
        update_post_meta( $session_id, 'kh_planner_queue', $queue );

        $task_type  = $item['task_type'] ?? '';
        $article_id = $item['article_id'] ?? '';

            if ( $task_type === 'framework_generation' && $article_id && $session_id ) {
                $orchestrator = new \KH\Planner\Agents\PlannerOrchestrator();
                $result = $orchestrator->run_framework_generation( $session_id, $article_id );

                if ( is_wp_error( $result ) ) {
                    $queue[ $item_idx ]['status'] = 'failed';
                    $queue[ $item_idx ]['error_message'] = $result->get_error_message();
                    $queue[ $item_idx ]['completed_at'] = current_time( 'mysql' );
                    update_post_meta( $session_id, 'kh_planner_queue', $queue );
                    return $result;
                }

                $queue[ $item_idx ]['status'] = 'dispatched';
                $queue[ $item_idx ]['job_id'] = $result['job_id'];
                $queue[ $item_idx ]['completed_at'] = current_time( 'mysql' );
                update_post_meta( $session_id, 'kh_planner_queue', $queue );

                // Direct fallback: if the job is still queued after a short wait, process it synchronously.
                if ( $result['job_id'] && class_exists( '\\KH\\Editorial\\Services\\AI\\AIStorage' ) ) {
                    $storage = new \KH\Editorial\Services\AI\AIStorage();
                    $job = $storage->get_job( $result['job_id'] );
                    $status = $job['status'] ?? '';
                    if ( in_array( $status, [ 'queued', 'running', '' ], true ) && class_exists( '\\KH\\Editorial\\Services\\AI\\AIWorker' ) ) {
                        $worker = new \KH\Editorial\Services\AI\AIWorker();
                        $worker->process_job( $result['job_id'], 'planner' );
                    }
                }

                return rest_ensure_response( [
                    'ok'      => true,
                    'job_id'  => $result['job_id'],
                    'queue_id' => $queue_id,
                    'status'  => 'dispatched',
                ] );
            }

        if ( $task_type === 'article_creation' && $article_id && $session_id ) {
            $author_profile   = $item['payload']['author_profile'] ?? '';
            $word_count_range = $item['payload']['word_count_range'] ?? 'short';
            $orchestrator = new \KH\Planner\Agents\PlannerOrchestrator();
            $result = $orchestrator->run_author_generation( $session_id, $article_id, $author_profile, $word_count_range );

            if ( is_wp_error( $result ) ) {
                $queue[ $item_idx ]['status'] = 'failed';
                $queue[ $item_idx ]['error_message'] = $result->get_error_message();
                $queue[ $item_idx ]['completed_at'] = current_time( 'mysql' );
                update_post_meta( $session_id, 'kh_planner_queue', $queue );
                return $result;
            }

            $queue[ $item_idx ]['status'] = 'dispatched';
            $queue[ $item_idx ]['job_id'] = $result['job_id'];
            $queue[ $item_idx ]['completed_at'] = current_time( 'mysql' );
            update_post_meta( $session_id, 'kh_planner_queue', $queue );

            return rest_ensure_response( [
                'ok'      => true,
                'job_id'  => $result['job_id'],
                'queue_id' => $queue_id,
                'status'  => 'dispatched',
            ] );
        }

        // For other task types, return simple acknowledgment
        $job_id = uniqid( 'job_', true );
        return rest_ensure_response( [ 'ok' => true, 'job_id' => $job_id, 'queue_id' => $queue_id ] );
    }

    /**
     * POST editorial/v1/planner/queue/run-bulk
     * 
     * Processes multiple queue items. For each queued framework_generation item,
     * delegates to PlannerOrchestrator::run_framework_generation(). Unlike the
     * single queue/run endpoint, this processes items synchronously in the current
     * request (each framework job is dispatched to the AI worker, not run inline).
     */
    public function queue_run_bulk( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $queue_ids  = $params['queue_ids'] ?? [];
        $run_all    = ! empty( $params['run_all_queued'] );
        $session_id = (int) ( $params['id'] ?? 0 );

        if ( ! $session_id ) {
            return new \WP_Error( 'missing_session', 'Session id is required.', [ 'status' => 400 ] );
        }

        $queue = get_post_meta( $session_id, 'kh_planner_queue', true );
        if ( ! is_array( $queue ) ) {
            return rest_ensure_response( [ 'ok' => true, 'started' => 0, 'failed' => 0 ] );
        }

        if ( $run_all ) {
            $queue_ids = array_values( array_map( function( $item ) {
                return $item['id'] ?? '';
            }, array_filter( $queue, function( $item ) {
                return ( $item['status'] ?? '' ) === 'queued';
            } ) ) );
        }

        $started = 0;
        $failed  = 0;

        foreach ( $queue as $idx => $qi ) {
            $qid = $qi['id'] ?? '';
            if ( ! in_array( $qid, $queue_ids, true ) ) {
                continue;
            }
            if ( ( $qi['status'] ?? '' ) !== 'queued' ) {
                continue;
            }

            $task_type = $qi['task_type'] ?? '';
            $article_id = $qi['article_id'] ?? '';

            // Mark as running
            $queue[ $idx ]['status'] = 'running';
            $queue[ $idx ]['started_at'] = current_time( 'mysql' );
            update_post_meta( $session_id, 'kh_planner_queue', $queue );

            $started++;

            if ( $task_type === 'framework_generation' && $article_id ) {
                $orchestrator = new \KH\Planner\Agents\PlannerOrchestrator();
                $result = $orchestrator->run_framework_generation( $session_id, $article_id );

                if ( is_wp_error( $result ) ) {
                    $queue[ $idx ]['status'] = 'failed';
                    $queue[ $idx ]['error_message'] = $result->get_error_message();
                    $queue[ $idx ]['completed_at'] = current_time( 'mysql' );
                    $failed++;
                } else {
                    $queue[ $idx ]['status'] = 'dispatched';
                    $queue[ $idx ]['job_id'] = $result['job_id'] ?? '';
                    $queue[ $idx ]['completed_at'] = current_time( 'mysql' );
                }
                update_post_meta( $session_id, 'kh_planner_queue', $queue );
            }

        if ( $task_type === 'article_creation' && $article_id ) {
            $author_profile   = $qi['payload']['author_profile'] ?? '';
            $word_count_range = $qi['payload']['word_count_range'] ?? 'short';
            $orchestrator = new \KH\Planner\Agents\PlannerOrchestrator();
            $result = $orchestrator->run_author_generation( $session_id, $article_id, $author_profile, $word_count_range );

                if ( is_wp_error( $result ) ) {
                    $queue[ $idx ]['status'] = 'failed';
                    $queue[ $idx ]['error_message'] = $result->get_error_message();
                    $queue[ $idx ]['completed_at'] = current_time( 'mysql' );
                    $failed++;
                } else {
                    $queue[ $idx ]['status'] = 'dispatched';
                    $queue[ $idx ]['job_id'] = $result['job_id'] ?? '';
                    $queue[ $idx ]['completed_at'] = current_time( 'mysql' );
                }
                update_post_meta( $session_id, 'kh_planner_queue', $queue );
            }
        }

        return rest_ensure_response( [
            'ok'      => true,
            'started' => $started,
            'failed'  => $failed,
        ] );
    }

    /**
     * POST editorial/v1/planner/queue/reorder
     */
    public function queue_reorder( \WP_REST_Request $request ) {
        $params      = $request->get_json_params();
        $session_id  = (int) ( $params['id'] ?? 0 );
        $ordered_ids = $params['ordered_ids'] ?? [];

        if ( $session_id && is_array( $ordered_ids ) ) {
            $queue = get_post_meta( $session_id, 'kh_planner_queue', true );
            if ( is_array( $queue ) ) {
                $indexed = [];
                foreach ( $queue as $item ) {
                    $indexed[ $item['id'] ] = $item;
                }
                $reordered = [];
                foreach ( $ordered_ids as $qid ) {
                    if ( isset( $indexed[ $qid ] ) ) {
                        $reordered[] = $indexed[ $qid ];
                    }
                }
                update_post_meta( $session_id, 'kh_planner_queue', $reordered );
            }
        }

        return rest_ensure_response( [ 'ok' => true ] );
    }

    /**
     * POST editorial/v1/planner/queue/remove
     *
     * Removes a single queue item from the session's queue array.
     * Expects { queue_id: string } in the request body.
     * The session_id is extracted from the queue item's stored data.
     */
    public function queue_remove( \WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $queue_id = sanitize_text_field( $params['queue_id'] ?? '' );
        $session_id = (int) ( $params['id'] ?? 0 );

        if ( ! $queue_id ) {
            return new \WP_Error( 'missing_queue_id', 'queue_id is required.', [ 'status' => 400 ] );
        }

        if ( $session_id ) {
            $queue = get_post_meta( $session_id, 'kh_planner_queue', true );
            if ( is_array( $queue ) ) {
                $updated = array_values( array_filter( $queue, function( $item ) use ( $queue_id ) {
                    return ( $item['id'] ?? '' ) !== $queue_id;
                } ) );
                update_post_meta( $session_id, 'kh_planner_queue', $updated );
            }
        }

        return rest_ensure_response( [ 'ok' => true, 'queue_id' => $queue_id ] );
    }

    /**
     * POST editorial/v1/planner/queue/stop
     *
     * Marks a running queue item as 'stopped' in the session's queue array.
     * Expects { queue_id: string, reason?: string } in the request body.
     */
    public function queue_stop( \WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $queue_id = sanitize_text_field( $params['queue_id'] ?? '' );
        $session_id = (int) ( $params['id'] ?? 0 );
        $reason   = sanitize_text_field( $params['reason'] ?? 'Stopped by operator.' );

        if ( ! $queue_id ) {
            return new \WP_Error( 'missing_queue_id', 'queue_id is required.', [ 'status' => 400 ] );
        }

        if ( $session_id ) {
            $queue = get_post_meta( $session_id, 'kh_planner_queue', true );
            if ( is_array( $queue ) ) {
                foreach ( $queue as $idx => $item ) {
                    if ( ( $item['id'] ?? '' ) === $queue_id ) {
                        $queue[ $idx ]['status'] = 'stopped';
                        $queue[ $idx ]['error_message'] = $reason;
                        $queue[ $idx ]['completed_at'] = current_time( 'mysql' );
                        break;
                    }
                }
                update_post_meta( $session_id, 'kh_planner_queue', $queue );
            }
        }

        return rest_ensure_response( [ 'ok' => true, 'queue_id' => $queue_id, 'stopped' => true ] );
    }

    /**
     * POST editorial/v1/planner/queue/remove-all
     */
    public function queue_remove_all( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );

        if ( $session_id ) {
            delete_post_meta( $session_id, 'kh_planner_queue' );
        }

        return rest_ensure_response( [ 'ok' => true ] );
    }

    /**
     * POST editorial/v1/planner/queue/clear
     */
    public function queue_clear( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );

        if ( $session_id ) {
            $queue = get_post_meta( $session_id, 'kh_planner_queue', true );
            if ( is_array( $queue ) ) {
                $active = array_filter( $queue, function( $item ) {
                    return in_array( $item['status'] ?? '', [ 'running', 'dispatched' ], true );
                } );
                update_post_meta( $session_id, 'kh_planner_queue', array_values( $active ) );
            }
        }

        return rest_ensure_response( [ 'ok' => true ] );
    }

    // ─── Research / Policy / Author Callbacks ────────────────────────────

    /**
     * GET editorial/v1/planner/research-validation
     */
    public function get_research_validation( \WP_REST_Request $request ) {
        $session_id = (int) ( $request->get_param( 'id' ) ?: 0 );

        $research_policy    = $session_id ? get_post_meta( $session_id, 'kh_planner_research_policy', true ) : null;
        $research_validation = $session_id ? get_post_meta( $session_id, 'kh_planner_research_validation', true ) : null;

        return rest_ensure_response( [
            'research_policy'       => $research_policy ?: null,
            'research_validation'   => $research_validation ?: null,
            'search_provider_status'=> null,
        ] );
    }

    /**
     * GET editorial/v1/planner/author-policy
     */
    public function get_author_policy( \WP_REST_Request $request ) {
        $session_id   = (int) ( $request->get_param( 'id' ) ?: 0 );
        $author_policy = $session_id ? get_post_meta( $session_id, 'kh_planner_author_policy', true ) : null;

        return rest_ensure_response( [
            'author_policy' => $author_policy ?: null,
        ] );
    }

    /**
     * POST editorial/v1/planner/author-policy
     */
    public function save_author_policy( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );

        if ( $session_id && ! empty( $params['author_policy'] ) ) {
            update_post_meta( $session_id, 'kh_planner_author_policy', $params['author_policy'] );
        }

        return rest_ensure_response( [ 'ok' => true ] );
    }

    /**
     * POST editorial/v1/planner/policy
     */
    public function save_policy( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );

        if ( $session_id && ! empty( $params['policy'] ) ) {
            update_post_meta( $session_id, 'kh_planner_research_policy', $params['policy'] );
        }

        return rest_ensure_response( [ 'ok' => true ] );
    }

    /**
     * POST editorial/v1/planner/run-framework
     * 
     * Generates an editorial framework for a specific article using
     * web search + LLM synthesis. Uses the connection-close pattern
     * (like dive_deeper) for synchronous background processing.
     */
    public function run_framework( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );
        $article_id = sanitize_text_field( $params['article_id'] ?? '' );

        if ( ! $session_id ) {
            return new \WP_Error( 'missing_id', 'Session ID is required.', [ 'status' => 400 ] );
        }
        if ( ! $article_id ) {
            return new \WP_Error( 'missing_article_id', 'article_id is required.', [ 'status' => 400 ] );
        }

        $orchestrator = new \KH\Planner\Agents\PlannerOrchestrator();
        $result = $orchestrator->run_framework_generation( $session_id, $article_id, true );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $job_id = $result['job_id'] ?? '';

        if ( empty( $job_id ) ) {
            return rest_ensure_response( [
                'ok'         => true,
                'session_id' => $session_id,
                'article_id' => $article_id,
                'job_id'     => '',
                'status'     => 'skipped',
            ] );
        }

        $response_data = [
            'ok'         => true,
            'session_id' => $session_id,
            'article_id' => $article_id,
            'job_id'     => $job_id,
            'status'     => 'queued',
        ];
        $response_json = wp_json_encode( $response_data );

        // ─── Early Response: Close HTTP connection ──────────────
        ignore_user_abort( true );
        set_time_limit( 300 );

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'Content-Length: ' . strlen( $response_json ) );
        header( 'Connection: close' );

        echo $response_json;

        if ( function_exists( 'ob_flush' ) ) {
            @ob_flush();
        }
        flush();

        // ─── Background Processing ───────────────────────────────
        error_log( '[PLANNER] Early response sent for run_framework job: ' . $job_id . ' — starting background processing' );
        if ( class_exists( '\KH\Editorial\Services\AI\AIWorker' ) ) {
            $worker = new \KH\Editorial\Services\AI\AIWorker();
            $worker->process_job( $job_id, 'planner' );
        }
        error_log( '[PLANNER] Background processing completed for run_framework job: ' . $job_id );

        exit;
    }

    /**
     * POST editorial/v1/planner/synopsis-plan
     */
    public function synopsis_plan( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );

        if ( ! $session_id ) {
            return new \WP_Error( 'missing_id', 'Session ID is required.', [ 'status' => 400 ] );
        }

        $orchestrator = new PlannerOrchestrator();
        if ( method_exists( $orchestrator, 'run_synopsis_plan' ) ) {
            $result = $orchestrator->run_synopsis_plan( $session_id );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            return rest_ensure_response( $result );
        }

        return rest_ensure_response( [ 'ok' => true, 'session_id' => $session_id ] );
    }

    /**
     * POST editorial/v1/planner/synopses
     */
    public function run_synopses( \WP_REST_Request $request ) {
        $params      = $request->get_json_params();
        $session_id  = (int) ( $params['id'] ?? 0 );
        $synopsis_count = isset( $params['synopsis_count'] ) ? (int) $params['synopsis_count'] : 0;

        if ( ! $session_id ) {
            return new \WP_Error( 'missing_id', 'Session ID is required.', [ 'status' => 400 ] );
        }

        // Persist the synopsis count to session meta so orchestrator.reads it
        if ( $synopsis_count > 0 && in_array( $synopsis_count, [ 1, 4, 8, 20 ], true ) ) {
            $meta = $this->get_planner_meta_array( $session_id );
            $meta['synopsis_count'] = $synopsis_count;
            update_post_meta( $session_id, 'kh_planner_meta', wp_json_encode( $meta ) );
        }

        $orchestrator = new PlannerOrchestrator();
        if ( method_exists( $orchestrator, 'run_synopses' ) ) {
            $result = $orchestrator->run_synopses( $session_id );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            return rest_ensure_response( $result );
        }

        return rest_ensure_response( [ 'ok' => true, 'session_id' => $session_id ] );
    }

    /**
     * POST editorial/v1/planner/export
     */
    public function planner_export( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );
        $format     = sanitize_text_field( $params['format'] ?? 'json' );

        return rest_ensure_response( [ 'ok' => true, 'session_id' => $session_id, 'format' => $format ] );
    }

    /**
     * POST editorial/v1/planner/export-synopses
     */

    /**
     * POST editorial/v1/planner/convert-to-pdf
     *
     * Converts HTML content to PDF using DomPDF.
     * Accepts { html: string, filename?: string }
     */
    public function convert_html_to_pdf( \WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $html     = $params['html'] ?? '';
        $filename = sanitize_text_field( $params['filename'] ?? 'export' );

        if ( empty( $html ) ) {
            return new \WP_Error( 'missing_html', 'HTML content is required.', [ 'status' => 400 ] );
        }

        $pdf_helper = new \KH\Planner\Agents\ExportPDFHelper();
        $result = $pdf_helper->generate_pdf( $html, $filename . '-' . date('Y-m-d-H-i-s') );

        if ( ! $result ) {
            return new \WP_Error( 'pdf_failed', 'PDF generation failed.', [ 'status' => 500 ] );
        }

        return rest_ensure_response( $result );
    }

    /**
     * POST editorial/v1/planner/convert-to-docx
     *
     * Converts HTML content to DOCX using PHPWord.
     * Accepts { html: string, filename?: string }
     */
    public function convert_html_to_docx( \WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $html     = $params['html'] ?? '';
        $filename = sanitize_text_field( $params['filename'] ?? 'export' );

        if ( empty( $html ) ) {
            return new \WP_Error( 'missing_html', 'HTML content is required.', [ 'status' => 400 ] );
        }

        if ( ! class_exists( 'PhpOffice\\PhpWord\\PhpWord' ) ) {
            return new \WP_Error( 'phpword_not_found', 'PHPWord library not available.', [ 'status' => 500 ] );
        }

        $export_dir = $this->get_export_path();
        $sanitized_filename = sanitize_title( $filename );
        $filepath = $export_dir . '/' . $sanitized_filename . '-' . date('Y-m-d-H-i-s') . '.docx';

        try {
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $section = $phpWord->addSection();

            // Add title
            $section->addText( 'Generated by Touchpoint Editorial Planner', ['size' => 10, 'color' => '666666'] );
            $section->addTextBreak(1);

            // Convert HTML to DOCX - process as paragraphs
            $text = strip_tags( $html );
            $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            $text = preg_replace( '/\s+/', ' ', $text );
            
            // Split into sentences/paragraphs for better formatting
            $paragraphs = preg_split( '/[.!?]+\s+/', $text );
            foreach ( $paragraphs as $para ) {
                $para = trim( $para );
                if ( ! empty( $para ) ) {
                    $section->addText( ucfirst( $para ) . '. ' );
                    $section->addTextBreak(1);
                }
            }

            // Ensure directory exists
            if ( ! file_exists( $export_dir ) ) {
                wp_mkdir_p( $export_dir );
            }

            // Save with error handling
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter( $phpWord, 'Word2007' );
            $objWriter->save( $filepath );

            // Verify file was created
            if ( ! file_exists( $filepath ) ) {
                return new \WP_Error( 'docx_save_failed', 'Failed to create DOCX file.', [ 'status' => 500 ] );
            }

            // Verify file is valid (check ZIP structure - DOCX is a ZIP file)
            $zip = new \ZipArchive();
            $result = $zip->open( $filepath );
            if ( $result !== true ) {
                unlink( $filepath ); // Remove corrupt file
                return new \WP_Error( 'docx_invalid', 'Generated DOCX file is invalid.', [ 'status' => 500 ] );
            }
            $zip->close();

            $file_url = $this->get_export_url( $sanitized_filename . '-' . date('Y-m-d-H-i-s') . '.docx' );

            return rest_ensure_response( [
                'file_url'  => $file_url,
                'file_path' => $filepath,
                'filename'  => basename( $filepath ),
            ] );
        } catch ( \Exception $e ) {
            error_log( 'DOCX conversion error: ' . $e->getMessage() );
            return new \WP_Error( 'docx_error', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    private function get_export_path() {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/editorial_exports';
        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
        }
        return $export_dir;
    }

    private function get_export_url( $filename ) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/editorial_exports/' . $filename;
    }

    public function export_synopses( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );

        return rest_ensure_response( [ 'ok' => true, 'session_id' => $session_id ] );
    }

    /**
     * POST editorial/v1/planner/export-framework
     *
     * Exports the framework for the given article as a self-contained
     * HTML document and returns the file URL so the frontend can
     * present a download link or open the file directly.
     *
     * Accepts: { id: session_id, article_id: string, format?: 'html'|'json' }
     */
    public function export_framework( \WP_REST_Request $request ) {
        $params      = $request->get_json_params();
        $session_id  = (int) ( $params['id'] ?? 0 );
        $article_id  = sanitize_text_field( $params['article_id'] ?? '' );
        $format      = sanitize_text_field( $params['format'] ?? 'html' );

        if ( ! $session_id ) {
            return new \WP_Error( 'missing_id', 'Session ID is required.', [ 'status' => 400 ] );
        }
        if ( ! $article_id ) {
            return new \WP_Error( 'missing_article_id', 'article_id is required.', [ 'status' => 400 ] );
        }

        $agent = new \KH\Planner\Agents\ExportAgent();

        // Support "word" as an alias for the HTML export of a framework.
        // The UI can then treat the result as a DOCX download if needed.
        if ( $format === 'json' ) {
            $result = $agent->export_framework_to_json( $session_id, $article_id );
        } elseif ( $format === 'pdf' ) {
            $result = $agent->export_framework_to_pdf( $session_id, $article_id );
        } elseif ( $format === 'word' ) {
            // Return a true DOCX file for the framework.
            $result = $agent->export_framework_to_docx( $session_id, $article_id );
        } else {
            $result = $agent->export_framework_to_html( $session_id, $article_id );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array_merge( [ 'ok' => true ], $result ) );
    }

    /**
     * POST editorial/v1/planner/run-author
     *
     * Runs author generation for the specified article.
     * Uses connection-close pattern for synchronous background processing
     * (same as dive_deeper in article_action).
     *
     * Expects: { id: session_id, article_id: string, author_profile?: string, word_count_range?: string }
     * word_count_range: 'short' (800-1500), 'standard' (1500-3000), 'long' (3000-5000). Default 'short'.
     */
    public function run_author( \WP_REST_Request $request ) {
        $params           = $request->get_json_params();
        $session_id       = (int) ( $params['id'] ?? 0 );
        $article_id       = sanitize_text_field( $params['article_id'] ?? '' );
        $author_profile   = sanitize_text_field( $params['author_profile'] ?? '' );
        $word_count_range = in_array( $params['word_count_range'] ?? '', [ 'short', 'standard', 'long' ], true )
            ? $params['word_count_range']
            : 'short';

        if ( ! $session_id ) {
            return new \WP_Error( 'missing_id', 'Session ID is required.', [ 'status' => 400 ] );
        }

        if ( ! $article_id ) {
            return new \WP_Error( 'missing_article_id', 'article_id is required.', [ 'status' => 400 ] );
        }

        // Pre-flight: mark the article as running so the frontend sees it immediately.
        // If the article already has a 'running' status but no output, the previous
        // run crashed — allow re-running by clearing the stale state first.
        $meta = $this->get_planner_meta_array( $session_id );
        $articles = $meta['articles'] ?? [];
        $found = false;
        foreach ( $articles as $idx => $a ) {
            if ( $a['id'] === $article_id ) {
                $existing_author = $articles[ $idx ]['author'] ?? [];
                $existing_status  = $existing_author['status'] ?? '';
                $has_output       = ! empty( $existing_author['output'] );

                // If the previous run died mid-flight (status=running but no output),
                // clear the stale author key so the frontend doesn't block re-runs.
                if ( $existing_status === 'running' && ! $has_output ) {
                    error_log( '[PLANNER] run_author: clearing stale running state for article ' . $article_id . ' (no output from previous run)' );
                    unset( $articles[ $idx ]['author'] );
                }

                if ( ! isset( $articles[ $idx ]['author'] ) ) {
                    $articles[ $idx ]['author'] = [];
                }
                $articles[ $idx ]['author']['status']  = 'running';
                $articles[ $idx ]['author']['profile'] = $author_profile;
                $found = true;
                break;
            }
        }
        if ( $found ) {
            $meta['articles'] = $articles;
            update_post_meta( $session_id, 'kh_planner_meta', wp_json_encode( $meta ) );
        }

        // Create an AI job so the worker can process this asynchronously.
        // This avoids the close-connection-early pattern which doesn't work
        // reliably on all environments (Local, nginx buffering, etc.).
        $job_id = '';
        if ( class_exists( '\KH\Editorial\Services\AI\AIStorage' ) ) {
            $storage = new \KH\Editorial\Services\AI\AIStorage();
            $job_id = $storage->insert_job( [
                'type'       => 'planner',
                'status'     => 'queued',
                'payload'    => wp_json_encode( [
                    'task_type'        => 'author_generation',
                    'session_id'       => $session_id,
                    'article_id'       => $article_id,
                    'author_profile'   => $author_profile,
                    'word_count_range' => $word_count_range,
                ] ),
                'created_by' => get_current_user_id(),
            ] );
        }

        // If we got a valid job_id (not an error), dispatch it asynchronously.
        // After dispatch we will perform a short watchdog check (5 seconds) to see
        // if the job has been picked up. If it remains queued/running we fall back
        // to processing the job directly via AIWorker.
        if ( $job_id && ! is_wp_error( $job_id ) ) {
            if ( function_exists( 'wp_remote_post' ) ) {
                $dispatch_url = rest_url( 'editorial/v1/planner/dispatch-job' );
                wp_remote_post( $dispatch_url, [
                    'timeout'   => 1,
                    'blocking'  => false,
                    'headers'   => [ 'Content-Type' => 'application/json' ],
                    'body'      => wp_json_encode( [
                        'job_id'    => $job_id,
                        'type'      => 'planner',
                    ] ),
                ] );
            }

            // Return early response to client.
            $response = [
                'ok'         => true,
                'session_id' => $session_id,
                'article_id' => $article_id,
                'job_id'     => $job_id,
                'status'     => 'queued',
            ];

            // Watchdog: wait a few seconds then verify job status.
            sleep( 5 );
            $job_status = '';
            if ( class_exists( '\\KH\\Editorial\\Services\\AI\\AIStorage' ) ) {
                $storage_check = new \KH\Editorial\Services\AI\AIStorage();
                $job_record    = $storage_check->get_job( $job_id );
                if ( $job_record && ! empty( $job_record['status'] ) ) {
                    $job_status = $job_record['status'];
                }
            }

            // If still pending, process directly.
            if ( in_array( $job_status, [ 'queued', 'running', '' ], true ) ) {
                if ( class_exists( '\\KH\\Editorial\\Services\\AI\\AIWorker' ) ) {
                    $worker = new \KH\Editorial\Services\AI\AIWorker();
                    $worker->process_job( $job_id, 'planner' );
                }
            }

            return rest_ensure_response( $response );
        }

        // Fallback: process synchronously (kept for environments where AIStorage isn't available)
        // The LLM call can take 60-180s — ensure PHP doesn't time out mid-request.
        error_log( '[PLANNER] run_author: AIStorage unavailable or insert failed, falling back to synchronous processing for article ' . $article_id );
        set_time_limit( 300 ); // 5 minutes
        @ini_set( 'max_execution_time', 300 );

        try {
            $orchestrator = new \KH\Planner\Agents\PlannerOrchestrator();
            $result = $orchestrator->run_author_generation( $session_id, $article_id, $author_profile, $word_count_range );

            if ( is_wp_error( $result ) ) {
                error_log( '[PLANNER] run_author fallback failed: ' . $result->get_error_message() );
                return $result;
            }

            error_log( '[PLANNER] run_author fallback succeeded for article ' . $article_id . ' in session ' . $session_id );
            return rest_ensure_response( array_merge( $result, [ 'ok' => true ] ) );
        } catch ( \Throwable $e ) {
            error_log( '[PLANNER] run_author fallback fatal error: ' . $e->getMessage() );
            // Best-effort: mark article as failed
            $meta2 = $this->get_planner_meta_array( $session_id );
            $articles2 = $meta2['articles'] ?? [];
            foreach ( $articles2 as $idx2 => $a2 ) {
                if ( $a2['id'] === $article_id ) {
                    if ( ! isset( $articles2[ $idx2 ]['author'] ) ) {
                        $articles2[ $idx2 ]['author'] = [];
                    }
                    $articles2[ $idx2 ]['author']['status']  = 'failed';
                    $articles2[ $idx2 ]['author']['error']   = $e->getMessage();
                    $articles2[ $idx2 ]['author']['completed_at'] = current_time( 'mysql' );
                    break;
                }
            }
            $meta2['articles'] = $articles2;
            update_post_meta( $session_id, 'kh_planner_meta', wp_json_encode( $meta2 ) );
            return new \WP_Error( 'author_generation_failed', $e->getMessage() );
        }
    }

    /**
     * POST editorial/v1/planner/article-action
     *
     * Dispatches research actions on a completed article synopsis:
     *   - dive_deeper: Find additional citations for the article
     *   - opinion_piece: Generate an opinion piece (placeholder)
     *   - expand: Expand the article into a full brief (placeholder)
     */
    public function article_action( \WP_REST_Request $request ) {
        $params     = $request->get_json_params();
        $session_id = (int) ( $params['id'] ?? 0 );
        $action     = sanitize_text_field( $params['action'] ?? '' );
        $article_id = sanitize_text_field( $params['article_id'] ?? '' );
        $extra      = $params['params'] ?? [];

        if ( ! $session_id || ! $action ) {
            return new \WP_Error( 'missing_params', 'id and action are required.', [ 'status' => 400 ] );
        }

        $orchestrator = new \KH\Planner\Agents\PlannerOrchestrator();

        switch ( $action ) {
            case 'dive_deeper':
                // On some (particularly local) environments, WP-Cron doesn't fire
                // reliably, so jobs scheduled via wp_schedule_single_event may sit
                // in the queue forever. Instead, we use the "close-connection-then-
                // process" pattern: flush the HTTP response to the browser immediately,
                // then run the job synchronously in the same PHP process (which keeps
                // running after the connection is closed). The JS polls every 3s via
                // refreshSessionDetail to pick up the completed status.
                //
                // First create the job record synchronously (fast), then send the
                // early response, then process the job in background.
                $result = $orchestrator->run_dive_deeper( $session_id, $article_id, $extra );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                $job_id = $result['job_id'] ?? '';

                // If skipped (already 4+ citations), return immediately
                if ( empty( $job_id ) ) {
                    return rest_ensure_response( [
                        'ok'         => true,
                        'session_id' => $session_id,
                        'article_id' => $article_id,
                        'action'     => $action,
                        'job_id'     => '',
                        'status'     => 'skipped',
                        'message'    => $result['message'] ?? 'Article already has 4+ citations.',
                    ] );
                }

                // Persist the job reference to the article in session meta so
                // the frontend can find it via article.dive_deeper_jobs.
                $meta = $this->get_planner_meta_array( $session_id );
                $articles = $meta['articles'] ?? [];

                // If meta.articles is empty, rebuild it from the synopses data
                // so we can attach the dive_deeper_jobs reference.
                if ( empty( $articles ) ) {
                    $synopses = get_post_meta( $session_id, 'kh_planner_final_synopses', true );
                    if ( $synopses && is_array( $synopses['synopses'] ?? null ) ) {
                        $articles = [];
                        foreach ( $synopses['synopses'] as $i => $s ) {
                            $slug = sanitize_title( $s['headline'] ?? ( 'synopsis-' . $i ) );
                            $articles[] = [
                                'id'               => $slug,
                                'headline'         => $s['headline'] ?? '',
                                'summary'          => $s['summary'] ?? '',
                                'key_points'       => $s['key_points'] ?? [],
                                'keywords'         => $s['keywords'] ?? ( $s['target_keywords'] ?? [] ),
                                'priority_score'   => $s['priority_score'] ?? 0.0,
                                'citations'        => $s['citations'] ?? [],
                                'citation_count'   => count( $s['citations'] ?? [] ),
                                'dive_deeper_jobs' => [],
                            ];
                        }
                    }
                }

                $found = false;
                foreach ( $articles as &$article ) {
                    if ( $article['id'] === $article_id ) {
                        if ( ! isset( $article['dive_deeper_jobs'] ) || ! is_array( $article['dive_deeper_jobs'] ) ) {
                            $article['dive_deeper_jobs'] = [];
                        }
                        $article['dive_deeper_jobs'][] = [
                            'job_id'     => $job_id,
                            'status'     => 'queued',
                            'created_at' => current_time( 'mysql' ),
                        ];
                        $found = true;
                        break;
                    }
                }
                unset( $article );
                if ( $found ) {
                    $meta['articles'] = $articles;
                    update_post_meta( $session_id, 'kh_planner_meta', wp_json_encode( $meta ) );
                }

                // Build the JSON response body
                $response_data = [
                    'ok'         => true,
                    'session_id' => $session_id,
                    'article_id' => $article_id,
                    'action'     => $action,
                    'job_id'     => $job_id,
                ];
                $response_json = wp_json_encode( $response_data );

                // ─── Early Response: Close HTTP connection ──────────────
                // Send the JSON response to the browser immediately, then
                // process the dive_deeper job synchronously in the background.
                ignore_user_abort( true );
                set_time_limit( 300 ); // 5 minutes for the LLM call

                // Remove any output buffering layers that would prevent flush
                while ( ob_get_level() > 0 ) {
                    ob_end_clean();
                }

                header( 'Content-Type: application/json; charset=UTF-8' );
                header( 'Content-Length: ' . strlen( $response_json ) );
                header( 'Connection: close' );

                echo $response_json;

                // Flush the response via FastCGI/proxy buffers — ignore any
                // notice if no user-level output buffer was active.
                if ( function_exists( 'ob_flush' ) ) {
                    @ob_flush();
                }
                flush();

                // ─── Background Processing ───────────────────────────────
                // The browser has already received the response. Now process
                // the job — the LLM call runs here, after the HTTP connection
                // is closed, so the frontend gets the job_id instantly.
                error_log( '[PLANNER] Early response sent for dive_deeper job: ' . $job_id . ' — starting background processing' );
                if ( class_exists( '\KH\Editorial\Services\AI\AIWorker' ) ) {
                    $worker = new \KH\Editorial\Services\AI\AIWorker();
                    $worker->process_job( $job_id, 'planner' );
                }
                error_log( '[PLANNER] Background processing completed for dive_deeper job: ' . $job_id );

                // We must exit to prevent WP from sending more output after us
                exit;


            case 'opinion_piece':
                // Placeholder — will be implemented with RunAuthorAgent
                return rest_ensure_response( [
                    'ok'         => true,
                    'session_id' => $session_id,
                    'article_id' => $article_id,
                    'action'     => $action,
                    'job_id'     => '',
                ] );

            case 'dismiss':
                // Remove the article from session meta.articles array
                if ( ! $article_id ) {
                    return new \WP_Error( 'missing_article_id', 'article_id is required for dismiss action.', [ 'status' => 400 ] );
                }
                $meta = $this->get_planner_meta_array( $session_id );
                $articles = $meta['articles'] ?? [];
                $found = false;
                foreach ( $articles as $idx => $a ) {
                    if ( ( $a['id'] ?? '' ) === $article_id ) {
                        array_splice( $articles, $idx, 1 );
                        $found = true;
                        break;
                    }
                }
                if ( $found ) {
                    $meta['articles'] = $articles;
                    update_post_meta( $session_id, 'kh_planner_meta', wp_json_encode( $meta ) );
                }
                return rest_ensure_response( [
                    'ok'         => true,
                    'session_id' => $session_id,
                    'article_id' => $article_id,
                    'action'     => $action,
                    'dismissed'  => $found,
                ] );

            default:
                return new \WP_Error( 'unknown_action', 'Unknown article action: ' . $action, [ 'status' => 400 ] );
        }
    }

    /**
     * POST editorial/v1/planner/dispatch-job
     *
     * Internal endpoint called via non-blocking wp_remote_post to process
     * dive_deeper and other long-running jobs asynchronously. The caller
     * fires and forgets — this endpoint handles the actual LLM call.
     * 
     * Note: This endpoint is called internally by the orchestrator, so it
     * validates either the nonce OR checks for internal call signature.
     */
    public function dispatch_job( \WP_REST_Request $request ) {
        // Allow internal calls (from same WordPress instance) or validate nonce
        $is_internal = $request->get_header( 'X-Internal-Call' ) === 'true';
        $nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( 'nonce' );
        
        if ( ! $is_internal && $nonce ) {
            if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return new \WP_Error( 'invalid_nonce', 'Invalid nonce.', [ 'status' => 403 ] );
            }
        } elseif ( ! $is_internal && ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'insufficient_permissions', 'Insufficient permissions.', [ 'status' => 403 ] );
        }
        
        $params = $request->get_json_params();
        $job_id = $params['job_id'] ?? '';
        $type   = $params['type'] ?? 'planner';

        if ( empty( $job_id ) ) {
            return new \WP_Error( 'missing_job_id', 'job_id is required.', [ 'status' => 400 ] );
        }

        $worker = new \KH\Editorial\Services\AI\AIWorker();
        $worker->process_job( $job_id, $type );

        return rest_ensure_response( [ 'ok' => true, 'job_id' => $job_id ] );
    }

    /**
     * Merge stored kh_planner_dives into articles by matching headline slug.
     *
     * This is a fallback for existing dives that were stored before the
     * synopsis→article linking fix was deployed. It matches each dive's
     * article_headline to an article's slug and appends the citations.
     */
    private function merge_stored_dives_into_articles( $meta, array $dives ) {
        $articles = $meta['articles'] ?? [];
        if ( empty( $articles ) || empty( $dives ) ) {
            return $meta;
        }

        // Index dives by article slug, keeping only the latest per article
        $latest_dive_per_article = [];
        foreach ( $dives as $dive ) {
            $dive_headline = $dive['article_headline'] ?? '';
            if ( ! $dive_headline ) {
                continue;
            }
            $dive_slug = sanitize_title( $dive_headline );
            // Later dives overwrite earlier ones in the loop
            $latest_dive_per_article[ $dive_slug ] = $dive;
        }

        foreach ( $articles as &$article ) {
            $article_slug = $article['id'] ?? '';
            if ( ! $article_slug ) {
                continue;
            }

            $dive = $latest_dive_per_article[ $article_slug ] ?? null;
            if ( ! $dive ) {
                continue;
            }

            $dive_citations = $dive['citations'] ?? [];
            if ( empty( $dive_citations ) ) {
                continue;
            }

            // Build existing title set from the article's original citations
            $existing_titles = [];
            foreach ( $article['citations'] ?? [] as $c ) {
                $existing_titles[ strtolower( trim( $c['title'] ?? '' ) ) ] = true;
            }

            // Only append citations that don't already exist, cap at target_citations
            $needed = $dive['target_citations'] ?? 4;
            $new_citations = [];
            foreach ( $dive_citations as $dc ) {
                if ( count( $new_citations ) >= $needed ) {
                    break;
                }
                $t = strtolower( trim( $dc['title'] ?? '' ) );
                if ( ! isset( $existing_titles[ $t ] ) ) {
                    $existing_titles[ $t ] = true;
                    $new_citations[] = [
                        'url'         => $dc['url'] ?? '',
                        'title'       => $dc['title'] ?? '',
                        'source_type' => $dc['source_type'] ?? 'industry',
                        'relevance'   => $dc['relevance_note'] ?? $dc['relevance'] ?? '',
                        'publication_date' => $dc['publication_date'] ?? '',
                    ];
                }
            }

            if ( ! empty( $new_citations ) ) {
                $article['citations'] = array_merge( $article['citations'] ?? [], $new_citations );
                $article['citation_count'] = count( $article['citations'] );
            }
        }
        unset( $article );

        $meta['articles'] = $articles;
        return $meta;
    }

    /**
     * Enrich meta.articles with dive_deeper_jobs status from the AI job table.
     *
     * Called from get_session_detail after articles are assembled.
     */
    private function enrich_articles_with_dive_status( $meta ) {
        $articles = $meta['articles'] ?? [];
        if ( empty( $articles ) ) {
            return $meta;
        }

        if ( ! class_exists( '\KH\Editorial\Services\AI\AIStorage' ) ) {
            return $meta;
        }

        $storage = new \KH\Editorial\Services\AI\AIStorage();

        foreach ( $articles as &$article ) {
            $jobs = $article['dive_deeper_jobs'] ?? [];
            if ( ! is_array( $jobs ) || empty( $jobs ) ) {
                continue;
            }
            $dive_citations = [];
            $updated = [];
            foreach ( $jobs as $job ) {
                $job_id = $job['job_id'] ?? '';
                if ( $job_id ) {
                    $row = $storage->get_job( $job_id );
                    if ( $row && ! empty( $row['status'] ) ) {
                        $job['status'] = $row['status'];
                        $job['response'] = isset( $row['response'] ) ? json_decode( $row['response'], true ) : null;
                        
                        // If the dive completed with citations, extract them for article display
                        if ( $job['status'] === 'completed' && ! empty( $job['response']['content']['citations'] ) ) {
                            foreach ( $job['response']['content']['citations'] as $c ) {
                                $dive_citations[] = [
                                    'url'         => $c['url'] ?? '',
                                    'title'       => $c['title'] ?? '',
                                    'source_type' => $c['source_type'] ?? 'industry',
                                    'relevance'   => $c['relevance_note'] ?? '',
                                    'publication_date' => $c['publication_date'] ?? '',
                                ];
                            }
                        }
                    }
                }
                $updated[] = $job;
            }
            $article['dive_deeper_jobs'] = $updated;
            
            // Merge dive citations into article.citations for display
            if ( ! empty( $dive_citations ) ) {
                $existing_citations = $article['citations'] ?? [];
                $article['citations'] = array_merge( $existing_citations, $dive_citations );
                // Recalculate citation_count from the merged array
                $article['citation_count'] = count( $article['citations'] );
            }
        }
        unset( $article );

        $meta['articles'] = $articles;
        return $meta;
    }

    /**
     * Load kh_planner_meta for a session and return it as an array.
     *
     * WordPress's get_post_meta() with $single=true can return an already-
     * unserialized array if the value was stored as a PHP-serialized array
     * rather than a JSON string. This helper handles both shapes safely.
     *
     * @param int $session_id
     * @return array
     */
    private function get_planner_meta_array( $session_id ) {
        $raw = get_post_meta( $session_id, 'kh_planner_meta', true );
        if ( is_array( $raw ) ) {
            return $raw;
        }
        if ( is_string( $raw ) && $raw !== '' ) {
            $decoded = json_decode( $raw, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                return $decoded;
            }
        }
        return [];
    }

    /**
     * Merge author metadata (_kh_planner_data) into planner metadata (kh_planner_meta).
     *
     * This ensures that article.author.output, article.edit_url, article.status, etc.
     * from the kh-editorial-author plugin are available in the session detail response.
     *
     * @param array $planner_meta The base planner metadata.
     * @param array $author_meta  The author plugin metadata.
     * @return array Merged metadata with author data taking precedence for article fields.
     */
    private function merge_author_meta_with_planner_meta( $planner_meta, $author_meta ) {
        // Merge top-level fields from author_meta into planner_meta
        foreach ( $author_meta as $key => $value ) {
            // For articles, we need to merge deeply
            if ( $key === 'articles' && is_array( $value ) && is_array( $planner_meta['articles'] ?? null ) ) {
                $planner_meta['articles'] = $this->merge_articles_deep( $planner_meta['articles'], $value );
            } else {
                // For other fields, author_meta takes precedence
                $planner_meta[ $key ] = $value;
            }
        }
        return $planner_meta;
    }

    /**
     * Deep merge articles arrays, matching by article ID.
     *
     * @param array $planner_articles Articles from kh_planner_meta.
     * @param array $author_articles  Articles from _kh_planner_data.
     * @return array Merged articles array.
     */
    private function merge_articles_deep( $planner_articles, $author_articles ) {
        // Index planner articles by ID for quick lookup
        $merged = [];
        $indexed = [];
        foreach ( $planner_articles as $article ) {
            $id = $article['id'] ?? '';
            if ( $id ) {
                $indexed[ $id ] = $article;
            }
        }

        // Merge author articles into indexed planner articles
        foreach ( $author_articles as $author_article ) {
            $id = $author_article['id'] ?? '';
            if ( $id && isset( $indexed[ $id ] ) ) {
                // Deep merge: author data takes precedence for nested fields
                $merged[ $id ] = array_merge( $indexed[ $id ], $author_article );
                unset( $indexed[ $id ] );
            } else {
                // New article from author plugin
                $merged[ $id ] = $author_article;
            }
        }

        // Add any remaining planner articles that weren't in author_articles
        foreach ( $indexed as $id => $article ) {
            $merged[ $id ] = $article;
        }

        return array_values( $merged );
    }

    /**
     * Enrich meta.articles with completed framework results from the AI job table.
     *
     * This is a fallback for runs where the fw- idempotency key handler in
     * AIWorker wasn't present (e.g. before the fix was deployed). It looks
     * for completed jobs with fw- idempotency keys and writes the LLM
     * response into article.framework.output.
     *
     * @param array $meta      Session meta array.
     * @param int   $session_id Session post ID.
     * @return array Updated meta.
     */
    private function enrich_articles_with_framework_results( $meta, $session_id ) {
        $articles = $meta['articles'] ?? [];
        if ( empty( $articles ) ) {
            return $meta;
        }

        if ( ! class_exists( '\KH\Editorial\Services\AI\AIStorage' ) ) {
            return $meta;
        }

        $storage = new \KH\Editorial\Services\AI\AIStorage();

        foreach ( $articles as &$article ) {
            // Skip if framework is already set
            if ( ! empty( $article['framework']['output'] ) ) {
                continue;
            }

            // Build the expected idempotency key base for this article
            $article_hash = substr( md5( $article['id'] ?? '' ), 0, 12 );

            // Try both the base key (no re-run) and any fw- keys with -r{timestamp} suffix
            $idempotency_candidates = [
                'fw-' . $session_id . '-' . $article_hash,
                'fw-' . $session_id . '-' . $article_hash . '-r',
            ];

            $found_job = null;
            foreach ( $idempotency_candidates as $candidate_base ) {
                if ( substr( $candidate_base, -2 ) === '-r' ) {
                    // Wildcard: search for any job starting with this prefix
                    $pattern = $candidate_base;
                } else {
                    // Exact match first (non-force mode)
                    $job = $storage->get_job_by_idempotency( $session_id, $candidate_base );
                    if ( $job ) {
                        $full_job = $storage->get_job( $job['id'] );
                        if ( $full_job && $full_job['status'] === 'completed' ) {
                            $found_job = $full_job;
                            break;
                        }
                    }
                    continue;
                }
            }

            // If no exact match, try direct MySQL LIKE query for -r{timestamp} variants
            if ( ! $found_job ) {
                global $wpdb;
                $table = $wpdb->prefix . 'ai_jobs';
                $prefix = 'fw-' . $session_id . '-' . $article_hash . '-r';
                $rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM $table WHERE session_id = %d AND idempotency_key LIKE %s AND status = 'completed' ORDER BY finished_at DESC LIMIT 1",
                    $session_id,
                    $prefix . '%'
                ), ARRAY_A );
                if ( ! empty( $rows ) ) {
                    $found_job = $rows[0];
                }
            }

            if ( ! $found_job ) {
                continue;
            }

            // Parse the response
            $response = isset( $found_job['response'] ) ? json_decode( $found_job['response'], true ) : null;
            if ( ! $response || empty( $response['content'] ) ) {
                continue;
            }

            $article['framework'] = [
                'status'       => 'completed',
                'output'       => $response['content'],
                'completed_at' => $found_job['finished_at'] ?? current_time( 'mysql' ),
            ];

            error_log( '[PLANNER] Framework result enriched from AI job table for article ' . $article['id'] . ' in session ' . $session_id );
        }
        unset( $article );

        $meta['articles'] = $articles;
        return $meta;
    }

    /**
     * Get all article summaries for the dashboard.
     */
    public function get_summaries( $request ) {
        $args = array(
            'post_type'      => array( 'post', 'article' ),
            'posts_per_page' => 50,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $posts = get_posts( $args );
        $summaries = array();

        foreach ( $posts as $post ) {
            $seo_score = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ) ? 85 : 0;
            $geo_score = get_post_meta( $post->ID, '_kh_geo_score', true ) ?: 0;
            $quote_club_commentary = get_post_meta( $post->ID, '_kh_quote_club_rating', true ) ?: 0;
            $boosted = get_post_meta( $post->ID, '_kh_boosted', true ) === '1';

            $summaries[] = array(
                'id'               => $post->ID,
                'title'            => $post->post_title,
                'status'           => $post->post_status,
                'seo_score'        => (int) $seo_score,
                'geo_score'        => (int) $geo_score,
                'quote_club_rating'=> (int) $quote_club_commentary,
                'boosted'          => $boosted,
                'scheduled_date'   => get_post_meta( $post->ID, '_kh_scheduled_date', true ) ?: $post->post_date,
                'views'            => get_post_meta( $post->ID, '_kh_views', true ) ?: 0,
                'clicks'           => get_post_meta( $post->ID, '_kh_clicks', true ) ?: 0,
                'permalink'        => get_permalink( $post->ID ),
                'edit_url'         => get_edit_post_link( $post->ID ),
            );
        }

        return rest_ensure_response( $summaries );
    }

    /**
     * Check permissions for REST API endpoints.
     * 
     * Validates nonce for authenticated requests and checks user capabilities.
     *
     * @param \WP_REST_Request $request Full request object.
     * @return bool|WP_Error True if permitted, WP_Error otherwise.
     */
    public function check_permission( $request ) {
        // Check nonce for authenticated requests
        $nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( 'nonce' );
        
        if ( $nonce ) {
            if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return new \WP_Error( 'invalid_nonce', 'Invalid nonce.', [ 'status' => 403 ] );
            }
        } else {
            // Fall back to capability check if no nonce provided
            if ( ! current_user_can( 'edit_posts' ) ) {
                return new \WP_Error( 'insufficient_permissions', 'Insufficient permissions.', [ 'status' => 403 ] );
            }
        }
        
        return true;
    }
}
