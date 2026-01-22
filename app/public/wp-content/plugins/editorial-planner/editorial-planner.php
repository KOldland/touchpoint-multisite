<?php
/**
 * Plugin Name: Editorial Planner
 * Description: An autonomous agent that runs a three-phase research pipeline to produce an executive research summary and validated topic ideas.
 * Version: 1.0.0
 * Author: Gemini
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once __DIR__ . '/includes/class-ep-worker.php';

class Editorial_Planner_Plugin {

    protected $worker;

    public function __construct() {
        $this->worker = new EP_Worker();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $namespace = 'ep/v1';

        register_rest_route( $namespace, '/start', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'start_session' ),
            'permission_callback' => array( $this, 'get_permission' ),
        ) );

        register_rest_route( $namespace, '/session/(?P<session_id>[a-zA-Z0-9-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_session_status' ),
            'permission_callback' => array( $this, 'get_permission' ),
        ) );

        register_rest_route( $namespace, '/citation-qa/(?P<session_id>[a-zA-Z0-9-]+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'submit_citation_qa' ),
            'permission_callback' => array( $this, 'get_permission' ),
        ) );

        register_rest_route( $namespace, '/regenerate-citations/(?P<session_id>[a-zA-Z0-9-]+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'regenerate_citations' ),
            'permission_callback' => array( $this, 'get_permission' ),
        ) );

        register_rest_route( $namespace, '/generate-article-ideas/(?P<session_id>[a-zA-Z0-9-]+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'generate_article_ideas' ),
            'permission_callback' => array( $this, 'get_permission' ),
        ) );

        register_rest_route( $namespace, '/brief/(?P<session_id>[a-zA-Z0-9-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_final_brief' ),
            'permission_callback' => array( $this, 'get_permission' ),
        ) );

        register_rest_route( $namespace, '/brief/(?P<session_id>[a-zA-Z0-9-]+)/export', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'export_brief' ),
            'permission_callback' => array( $this, 'get_permission' ),
        ) );
    }

    public static function activate_plugin() {
        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
            if ( file_exists( $db_handler_file ) ) {
                require_once $db_handler_file;
            } else {
                // Cannot proceed without the DB handler.
                // You might want to log this error or display an admin notice.
                return;
            }
        }
        $db_handler = new Dual_GPT_DB_Handler();

        $preset_data = array(
            'id' => 'ep-editorial-planner',
            'name' => 'Editorial Planner',
            'role' => 'research',
            'system_prompt' => 'You are the Editorial Planner for [Publication]. Run a three-phase research pipeline: Phase 1 Broad Discovery, Phase 2 Confirmation & Refinement, Phase 3 Trend Identification. Produce machine-readable JSON that conforms to the requested schemas, cite all claims, and never invent bibliographic metadata. If APA metadata is missing, set "details_unavailable". Prioritize 2023–2026 sources. Respect the tool whitelist and budget constraints.',
            'default_model' => 'gpt-4o-mini',
            'params_json' => wp_json_encode(array('temperature' => 0.2, 'max_tokens' => 2000)),
            'tool_whitelist' => wp_json_encode(array('web_search', 'fetch_url', 'summarize_pdf', 'keyword_api', 'citation_check')),
            'is_locked' => true
        );

        // Check if preset already exists
        if ( ! $db_handler->get_preset( $preset_data['id'] ) ) {
            $db_handler->insert_preset( $preset_data );
        }
    }

    public function get_permission() {
        return current_user_can( 'edit_posts' );
    }

    public function start_session( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        if ( empty( $params['broad_focus'] ) || empty( $params['idempotency_key'] ) ) {
            return new WP_Error( 'missing_parameters', 'Missing required parameters.', array( 'status' => 400 ) );
        }

        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
            if ( file_exists( $db_handler_file ) ) {
                require_once $db_handler_file;
            } else {
                return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
            }
        }

        $db_handler = new Dual_GPT_DB_Handler();

        $session_data = array(
            'idempotency_key' => $params['idempotency_key'],
            'created_by'      => get_current_user_id(),
            'status'          => 'queued',
            'meta'            => wp_json_encode( $params ),
        );

        $session_id = $db_handler->insert_session( $session_data );

        if ( is_wp_error( $session_id ) ) {
            return $session_id;
        }

        $job_data = array(
            'session_id' => $session_id,
            'phase'      => 'phase_1',
            'status'     => 'queued',
        );

        $job_id = $db_handler->insert_job( $job_data );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        wp_schedule_single_event( time(), 'ep_run_phase_1_job', array( $job_id ) );

        return new WP_REST_Response(
            array(
                'session_id' => $session_id,
                'job_ids'    => array( $job_id ),
                'status'     => 'queued',
                'message'    => 'Session created; Phase 1 queued.',
            ),
            200
        );
    }

    public function get_session_status( WP_REST_Request $request ) {
        $session_id = $request->get_param( 'session_id' );

        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
            if ( file_exists( $db_handler_file ) ) {
                require_once $db_handler_file;
            } else {
                return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
            }
        }

        $db_handler = new Dual_GPT_DB_Handler();
        $session = $db_handler->get_session( $session_id );

        if ( empty( $session ) ) {
            return new WP_Error( 'not_found', 'Session not found.', array( 'status' => 404 ) );
        }

        global $wpdb;
        $jobs_table = $wpdb->prefix . 'ai_jobs';
        $jobs = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $jobs_table WHERE session_id = %s", $session_id ),
            ARRAY_A
        );

        // TODO: Get results for each phase.

        return new WP_REST_Response(
            array(
                'session' => $session,
                'jobs'    => $jobs,
                'results' => array(
                    'phase_1' => new stdClass(),
                    'phase_2' => new stdClass(),
                    'phase_3' => new stdClass(),
                ),
            ),
            200
        );
    }

    public function submit_citation_qa( WP_REST_Request $request ) {
        global $wpdb;

        $session_id = $request->get_param( 'session_id' );
        $params = $request->get_json_params();

        if ( empty( $params['approved_citation_ids'] ) && empty( $params['rejected_citation_ids'] ) ) {
            return new WP_Error( 'missing_parameters', 'Missing required parameters.', array( 'status' => 400 ) );
        }

        // Update approved citations
        if ( ! empty( $params['approved_citation_ids'] ) ) {
            $citations_table = $wpdb->prefix . 'ep_citations';
            $ids = implode( "','", array_map( 'esc_sql', $params['approved_citation_ids'] ) );
            $wpdb->query( "UPDATE $citations_table SET approved = 1 WHERE id IN ('$ids')" );
        }

        // Update session meta with rejected citations and additional keywords
        if ( ! empty( $params['rejected_citation_ids'] ) || ! empty( $params['additional_keywords'] ) ) {
            if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
                $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
                if ( file_exists( $db_handler_file ) ) {
                    require_once $db_handler_file;
                } else {
                    return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
                }
            }
            $db_handler = new Dual_GPT_DB_Handler();
            $session = $db_handler->get_session( $session_id );
            $meta = json_decode( $session['meta'], true );

            if ( ! empty( $params['rejected_citation_ids'] ) ) {
                $meta['rejected_citations'] = array_merge( $meta['rejected_citations'] ?? [], $params['rejected_citation_ids'] );
            }

            if ( ! empty( $params['additional_keywords'] ) ) {
                $meta['additional_keywords'] = array_merge( $meta['additional_keywords'] ?? [], $params['additional_keywords'] );
            }

            $wpdb->update( $wpdb->prefix . 'ai_sessions', array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => $session_id ) );
        }

        // Enqueue Phase 3 job
        $job_data = array(
            'session_id' => $session_id,
            'phase'      => 'phase_3',
            'status'     => 'queued',
        );

        $job_id = $db_handler->insert_job( $job_data );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        wp_schedule_single_event( time(), 'ep_run_phase_3_job', array( $job_id ) );

        return new WP_REST_Response(
            array(
                'job_id'  => $job_id,
                'status'  => 'queued',
                'message' => 'Phase 3 generation job has been queued.',
            ),
            200
        );
    }

    public function regenerate_citations( WP_REST_Request $request ) {
        $session_id = $request->get_param( 'session_id' );
        $params = $request->get_json_params();

        if ( empty( $params['idempotency_key'] ) ) {
            return new WP_Error( 'missing_parameters', 'Missing idempotency_key.', array( 'status' => 400 ) );
        }

        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
            if ( file_exists( $db_handler_file ) ) {
                require_once $db_handler_file;
            } else {
                return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
            }
        }
        $db_handler = new Dual_GPT_DB_Handler();

        $job_data = array(
            'session_id'      => $session_id,
            'phase'           => 'phase_2',
            'status'          => 'queued',
            'idempotency_key' => $params['idempotency_key'],
            'meta'            => wp_json_encode( array( 'force_regenerate' => true ) ),
        );

        $job_id = $db_handler->insert_job( $job_data );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        wp_schedule_single_event( time(), 'ep_run_phase_2_job', array( $job_id ) );

        return new WP_REST_Response(
            array(
                'job_id'  => $job_id,
                'status'  => 'queued',
                'message' => 'Citation regeneration job accepted.',
            ),
            202
        );
    }

    public function generate_article_ideas( WP_REST_Request $request ) {
        $session_id = $request->get_param( 'session_id' );

        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
            if ( file_exists( $db_handler_file ) ) {
                require_once $db_handler_file;
            } else {
                return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
            }
        }
        $db_handler = new Dual_GPT_DB_Handler();

        $job_data = array(
            'session_id' => $session_id,
            'phase'      => 'phase_3',
            'status'     => 'queued',
        );

        $job_id = $db_handler->insert_job( $job_data );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        return new WP_REST_Response(
            array(
                'job_id'  => $job_id,
                'status'  => 'queued',
                'message' => 'Phase 3 article idea generation job has been queued.',
            ),
            202
        );
    }

    public function get_final_brief( WP_REST_Request $request ) {
        global $wpdb;
        $session_id = $request->get_param( 'session_id' );

        $brief_table = $wpdb->prefix . 'ep_briefs';
        $brief = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $brief_table WHERE session_id = %s", $session_id ),
            ARRAY_A
        );

        if ( empty( $brief ) ) {
            return new WP_Error( 'not_found', 'Brief not found.', array( 'status' => 404 ) );
        }

        // Fetch trends
        $trends_table = $wpdb->prefix . 'ep_trends';
        $trends = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $trends_table WHERE session_id = %s", $session_id ),
            ARRAY_A
        );

        // Fetch article ideas and group by trend
        $article_ideas_table = $wpdb->prefix . 'ep_article_ideas';
        $article_ideas = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $article_ideas_table WHERE session_id = %s", $session_id ),
            ARRAY_A
        );
        $ideas_by_trend = array();
        foreach ( $article_ideas as $idea ) {
            $ideas_by_trend[ $idea['trend_id'] ][] = $idea;
        }

        // Add article ideas to trends
        foreach ( $trends as &$trend ) {
            $trend['article_ideas'] = $ideas_by_trend[ $trend['id'] ] ?? [];
        }

        // Fetch citations
        $citations_table = $wpdb->prefix . 'ep_citations';
        $citations = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $citations_table WHERE session_id = %s AND approved = 1", $session_id ),
            ARRAY_A
        );

        $brief['key_themes'] = $trends;
        $brief['citations'] = $citations;

        // Decode JSON fields
        $json_fields = array( 'application', 'observations', 'key_themes', 'citations' );
        foreach ( $json_fields as $field ) {
            if ( isset( $brief[ $field ] ) && is_string( $brief[ $field ] ) ) {
                $brief[ $field ] = json_decode( $brief[ $field ], true );
            }
        }


        return new WP_REST_Response( $brief, 200 );
    }

    public function export_brief( WP_REST_Request $request ) {
        $session_id = $request->get_param( 'session_id' );
        $format = $request->get_param( 'format' );

        if ( ! in_array( $format, array( 'docx', 'html' ), true ) ) {
            return new WP_Error( 'invalid_format', 'Invalid export format.', array( 'status' => 400 ) );
        }

        $brief_data = $this->get_final_brief( $request );
        if ( is_wp_error( $brief_data ) ) {
            return $brief_data;
        }

        if ( 'docx' === $format ) {
            // Placeholder for DOCX generation
            $file_content = 'This is a placeholder for the DOCX file.';
            $filename = "brief-{$session_id}.docx";
            $content_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        } else {
            // Placeholder for HTML generation
            $file_content = '<html><body><h1>Brief</h1><p>This is a placeholder for the HTML file.</p></body></html>';
            $filename = "brief-{$session_id}.html";
            $content_type = 'text/html';
        }

        header( 'Content-Type: ' . $content_type );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        echo $file_content;
        exit;
    }
}

new Editorial_Planner_Plugin();

register_activation_hook( __FILE__, array( 'Editorial_Planner_Plugin', 'activate_plugin' ) );
