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
                '

