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
    }

    public function check_permission() {
        return current_user_can( 'edit_posts' );
    }

    public function get_sessions( $request ) {
        $limit = intval( $request->get_param( 'limit' ) ?: 6 );
        $args  = [ 
            'post_type'      => 'planner_session', 
            'posts_per_page' => $limit, 
            'post_status'    => 'any' 
        ];
        
        $posts = get_posts( $args );
        
        $out = array_map( function( $p ) {
            return [
                'id'         => $p->ID,
                'title'      => $p->post_title,
                'status'     => get_post_meta( $p->ID, 'kh_planner_status', true ) ?: 'draft',
                'link'       => admin_url( "admin.php?page=editorial_planner&session_id={$p->ID}" )
            ];
        }, $posts );

        return rest_ensure_response( $out );
    }

    public function create_session( $request ) {
        $params = $request->get_json_params();
        $title  = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';

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

        return rest_ensure_response( [
            'id'   => (int) $post_id,
            'link' => admin_url( 'admin.php?page=editorial_planner&session_id=' . $post_id ),
        ] );
    }

    public function get_frameworks() {
        return rest_ensure_response( [] );
    }

    public function get_pipeline() {
        return rest_ensure_response( [] );
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
