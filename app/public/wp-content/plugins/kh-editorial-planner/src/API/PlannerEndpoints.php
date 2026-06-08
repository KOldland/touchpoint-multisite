<?php

namespace KH\Planner\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KH\Planner\Agents\PlannerOrchestrator;

class PlannerEndpoints {

    public function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
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

        // Add GET top-line-categories endpoint (Step 4)
        
        // Add GET presets endpoint (Step 5)
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
        register_rest_route( 'editorial/v1', '/top-line-categories', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_top_line_categories' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ] );
    }

    public function check_permission() {
        return current_user_can( 'edit_posts' );
    }

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

        return rest_ensure_response( [
            'session_id' => (string) $post_id,
            'id'         => (string) $post_id,
            'role'       => $role,
            'preset_id'  => $preset_id,
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

    public function get_top_line_categories() {
        $categories = apply_filters( 'kh_editorial_planner_top_line_categories', [] );
        return rest_ensure_response( $categories );
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
}

