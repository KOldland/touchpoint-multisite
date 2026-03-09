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
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
    }

    public function register_blocks() {
        $block_json = __DIR__ . '/block.json';
        if ( file_exists( $block_json ) ) {
            register_block_type( __DIR__ );
        }
    }

    public function register_admin_page() {
        // KHM plugin owns the active Editorial Planner admin UI.
        // When that UI is available, suppress legacy menu registration
        // to avoid duplicate Planner/Sessions entries in wp-admin.
        if ( function_exists( 'render_editorial_planner_page' ) ) {
            return;
        }

        add_menu_page(
            'Editorial',
            'Editorial',
            'edit_posts',
            'editorial_planner',
            array( $this, 'render_admin_page' ),
            'dashicons-welcome-write-blog',
            6
        );

        add_submenu_page(
            'editorial_planner',
            'Planner',
            'Planner',
            'edit_posts',
            'editorial_planner',
            array( $this, 'render_admin_page' )
        );

        add_submenu_page(
            'editorial_planner',
            'Frameworks',
            'Frameworks',
            'edit_posts',
            'editorial_frameworks',
            array( $this, 'render_frameworks_page' )
        );

        add_submenu_page(
            'editorial_planner',
            'Sessions',
            'Sessions',
            'edit_posts',
            'editorial_sessions',
            array( $this, 'render_sessions_page' )
        );

        add_submenu_page(
            'editorial_planner',
            'Exports',
            'Exports',
            'edit_posts',
            'editorial_exports',
            array( $this, 'render_exports_page' )
        );
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

        self::install_tables();

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

    private static function install_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $migrations_dir = __DIR__ . '/sql/migrations';
        if ( ! is_dir( $migrations_dir ) ) {
            return;
        }

        $files = glob( $migrations_dir . '/*.sql' );
        if ( empty( $files ) ) {
            return;
        }

        sort( $files );

        $charset_collate = $wpdb->get_charset_collate();

        foreach ( $files as $file ) {
            $sql = file_get_contents( $file );
            if ( false === $sql ) {
                continue;
            }

            $sql = str_replace( '`wp_', '`' . $wpdb->prefix, $sql );
            $sql = str_replace( 'CREATE TABLE wp_', 'CREATE TABLE ' . $wpdb->prefix, $sql );
            $sql = trim( $sql );

            if ( ! $sql ) {
                continue;
            }

            if ( stripos( $sql, 'CHARACTER SET' ) === false && stripos( $sql, 'COLLATE' ) === false ) {
                $sql = preg_replace( '/\)\s*;$/', ") $charset_collate;", $sql );
            }

            dbDelta( $sql );
        }
    }

    public function get_permission() {
        $user_id = get_current_user_id();
        $can_edit = current_user_can( 'edit_posts' );
        error_log( 'EP: get_permission called, user_id: ' . $user_id . ', can_edit_posts: ' . ($can_edit ? 'yes' : 'no') );
        return $can_edit;
    }

    private function get_db_handler() {
        if ( class_exists( 'Dual_GPT_DB_Handler' ) ) {
            return new Dual_GPT_DB_Handler();
        }

        $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
        if ( file_exists( $db_handler_file ) ) {
            require_once $db_handler_file;
            return new Dual_GPT_DB_Handler();
        }

        return null;
    }

    private function check_rate_limit( $type, $user_id, $limit, $window_seconds ) {
        $key = "ep_rate_{$type}_{$user_id}";
        $data = get_transient( $key );
        $now = time();

        if ( ! $data || ! isset( $data['window_start'] ) || ( $now - $data['window_start'] ) > $window_seconds ) {
            set_transient( $key, array( 'window_start' => $now, 'count' => 1 ), $window_seconds );
            return true;
        }

        if ( $data['count'] >= $limit ) {
            return false;
        }

        $data['count']++;
        set_transient( $key, $data, $window_seconds );
        return true;
    }

    public function start_session( WP_REST_Request $request ) {
        error_log( 'EP: start_session called' );
        $params = $request->get_json_params();
        error_log( 'EP: params received: ' . json_encode( $params ) );

        if ( empty( $params['broad_focus'] ) || empty( $params['idempotency_key'] ) ) {
            error_log( 'EP: missing required parameters' );
            return new WP_Error( 'missing_parameters', 'Missing required parameters.', array( 'status' => 400 ) );
        }

        $db_handler = $this->get_db_handler();
        if ( ! $db_handler ) {
            error_log( 'EP: Dual_GPT_DB_Handler not found' );
            return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
        }

        $user_id = get_current_user_id();
        error_log( 'EP: user_id: ' . $user_id );
        if ( ! $this->check_rate_limit( 'start', $user_id, 4, 60 ) ) {
            error_log( 'EP: rate limit exceeded' );
            return new WP_Error( 'rate_limit_exceeded', 'Rate limit exceeded. Please try again later.', array( 'status' => 429 ) );
        }

        $budget = $db_handler->check_user_budget( $user_id );
        error_log( 'EP: budget check: ' . json_encode( $budget ) );
        if ( ( $budget['token_used'] ?? 0 ) >= ( $budget['token_limit'] ?? 0 ) ) {
            error_log( 'EP: budget exceeded' );
            return new WP_Error( 'budget_exceeded', 'Token budget exceeded', array( 'status' => 403 ) );
        }

        $session_data = array(
            'idempotency_key' => $params['idempotency_key'],
            'created_by'      => $user_id,
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
            'idempotency_key' => 'phase1-' . $params['idempotency_key'],
        );

        $job_id = $db_handler->insert_job( $job_data );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        $db_handler->insert_audit_log( $job_id, 'queued', array( 'phase' => 'phase_1' ) );
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

        $db_handler = $this->get_db_handler();
        if ( ! $db_handler ) {
            return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
        }

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

        $jobs_payload = array();
        $has_waiting = false;
        foreach ( $jobs as $job ) {
            if ( $job['status'] === 'waiting_for_human' ) {
                $has_waiting = true;
            }
            $jobs_payload[] = array(
                'job_id'         => $job['id'],
                'phase'          => $job['phase'] ?? null,
                'status'         => $job['status'],
                'progress'       => $job['progress'] ?? null,
                'tokens_used'    => (int) ( ( $job['usage_prompt_tokens'] ?? 0 ) + ( $job['usage_completion_tokens'] ?? 0 ) ),
                'estimated_cost' => isset( $job['cost_micro'] ) ? ( (float) $job['cost_micro'] / 1000000 ) : null,
                'cache_hit'      => isset( $job['cache_hit'] ) ? (bool) $job['cache_hit'] : null,
            );
        }

        $results = array(
            'phase_1' => array(),
            'phase_2' => array(),
            'phase_3' => array(),
        );

        $citations_table = $wpdb->prefix . 'ep_citations';
        $citation_count = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $citations_table WHERE session_id = %s", $session_id )
        );

        $trends_table = $wpdb->prefix . 'ep_trends';
        $trend_count = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $trends_table WHERE session_id = %s", $session_id )
        );

        $results['phase_1']['citations_count'] = $citation_count;
        $results['phase_2']['citations_approved'] = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $citations_table WHERE session_id = %s AND approved = 1", $session_id )
        );
        $results['phase_3']['trends_count'] = $trend_count;

        $citations = array();
        if ( $has_waiting ) {
            $citations = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM $citations_table WHERE session_id = %s ORDER BY confidence DESC", $session_id ),
                ARRAY_A
            );
        }

        $response = new WP_REST_Response(
            array(
                'session' => $session,
                'jobs'    => $jobs_payload,
                'results' => $results,
                'citations' => $citations,
            ),
            200
        );

        $response->header( 'X-EP-Cache', 'MISS' );
        return $response;
    }

    public function submit_citation_qa( WP_REST_Request $request ) {
        global $wpdb;

        $session_id = $request->get_param( 'session_id' );
        $params = $request->get_json_params();

        if ( empty( $params['approved_citation_ids'] ) && empty( $params['rejected_citation_ids'] ) ) {
            return new WP_Error( 'missing_parameters', 'Missing required parameters.', array( 'status' => 400 ) );
        }

        $db_handler = $this->get_db_handler();
        if ( ! $db_handler ) {
            return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
        }

        // Update approved citations
        if ( ! empty( $params['approved_citation_ids'] ) ) {
            $citations_table = $wpdb->prefix . 'ep_citations';
            $ids = implode( "','", array_map( 'esc_sql', $params['approved_citation_ids'] ) );
            $wpdb->query( "UPDATE $citations_table SET approved = 1 WHERE id IN ('$ids')" );
        }

        // Update session meta with rejected citations and additional keywords
        if ( ! empty( $params['rejected_citation_ids'] ) || ! empty( $params['additional_keywords'] ) ) {
            $session = $db_handler->get_session( $session_id );
            $meta = json_decode( $session['meta'], true );

            if ( ! empty( $params['rejected_citation_ids'] ) ) {
                $meta['rejected_citations'] = array_merge( $meta['rejected_citations'] ?? [], $params['rejected_citation_ids'] );
                $meta['rejected_domains'] = array_merge( $meta['rejected_domains'] ?? [], $this->get_rejected_domains( $params['rejected_citation_ids'] ) );
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

        $db_handler->insert_audit_log( $job_id, 'queued', array( 'phase' => 'phase_3' ) );
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

        $db_handler = $this->get_db_handler();
        if ( ! $db_handler ) {
            return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
        }

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

        $db_handler->insert_audit_log( $job_id, 'queued', array( 'phase' => 'phase_2', 'force_regenerate' => true ) );
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

        $db_handler = $this->get_db_handler();
        if ( ! $db_handler ) {
            return new WP_Error( 'class_not_found', 'Dual_GPT_DB_Handler class not found.', array( 'status' => 500 ) );
        }

        $job_data = array(
            'session_id' => $session_id,
            'phase'      => 'phase_3',
            'status'     => 'queued',
        );

        $job_id = $db_handler->insert_job( $job_data );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        $db_handler->insert_audit_log( $job_id, 'queued', array( 'phase' => 'phase_3' ) );
        return new WP_REST_Response(
            array(
                'job_id'  => $job_id,
                'status'  => 'queued',
                'message' => 'Phase 3 article idea generation job has been queued.',
            ),
            202
        );
    }

    private function get_rejected_domains( $citation_ids ) {
        global $wpdb;
        $citations_table = $wpdb->prefix . 'ep_citations';
        $domains = array();

        if ( empty( $citation_ids ) ) {
            return $domains;
        }

        $placeholders = implode( ',', array_fill( 0, count( $citation_ids ), '%s' ) );
        $results = $wpdb->get_results(
            $wpdb->prepare( "SELECT url FROM $citations_table WHERE id IN ($placeholders)", $citation_ids ),
            ARRAY_A
        );

        foreach ( $results as $row ) {
            $host = parse_url( $row['url'], PHP_URL_HOST );
            if ( $host ) {
                $host = preg_replace( '/^www\./', '', $host );
                $domains[] = $host;
            }
        }

        return array_values( array_unique( $domains ) );
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


        $response = new WP_REST_Response( $brief, 200 );
        $response->header( 'X-EP-Cache', 'MISS' );
        return $response;
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        global $wpdb;

        $session_id = sanitize_text_field( $_GET['session_id'] ?? '' );
        $created_by = absint( $_GET['created_by'] ?? 0 );
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        $start_date = sanitize_text_field( $_GET['start_date'] ?? '' );
        $end_date = sanitize_text_field( $_GET['end_date'] ?? '' );

        $where = array();
        $params = array();

        if ( $session_id ) {
            $where[] = 's.id = %s';
            $params[] = $session_id;
        }

        if ( $created_by ) {
            $where[] = 's.created_by = %d';
            $params[] = $created_by;
        }

        if ( $start_date ) {
            $where[] = 'DATE(s.created_at) >= %s';
            $params[] = $start_date;
        }

        if ( $end_date ) {
            $where[] = 'DATE(s.created_at) <= %s';
            $params[] = $end_date;
        }

        if ( $status ) {
            $where[] = "EXISTS (
                SELECT 1 FROM {$wpdb->prefix}ai_jobs j
                WHERE j.session_id = s.id AND j.status = %s
            )";
            $params[] = $status;
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $query = "
            SELECT
                s.id,
                s.created_by,
                s.created_at,
                s.updated_at,
                s.role,
                s.idempotency_key,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ai_jobs j WHERE j.session_id = s.id) AS job_count,
                (SELECT MAX(j.created_at) FROM {$wpdb->prefix}ai_jobs j WHERE j.session_id = s.id) AS last_job_at,
                (SELECT SUM(j.usage_prompt_tokens + j.usage_completion_tokens) FROM {$wpdb->prefix}ai_jobs j WHERE j.session_id = s.id) AS total_tokens,
                (SELECT SUM(j.cost_micro) FROM {$wpdb->prefix}ai_jobs j WHERE j.session_id = s.id) AS total_cost_micro,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ep_citations c WHERE c.session_id = s.id) AS citations_count,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ep_citations c WHERE c.session_id = s.id AND c.approved = 1) AS citations_approved,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ep_trends t WHERE t.session_id = s.id) AS trends_count,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ep_briefs b WHERE b.session_id = s.id) AS briefs_count
            FROM {$wpdb->prefix}ai_sessions s
            $where_sql
            ORDER BY s.created_at DESC
            LIMIT 50
        ";

        $prepared = $params ? $wpdb->prepare( $query, $params ) : $query;
        $sessions = $wpdb->get_results( $prepared, ARRAY_A );

        ?>
        <div class="wrap">
            <h1>Editorial Planner Dashboard</h1>
            <form method="get">
                <input type="hidden" name="page" value="ep-dashboard" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="session_id">Session ID</label></th>
                        <td><input type="text" name="session_id" id="session_id" value="<?php echo esc_attr( $session_id ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="created_by">Created By (User ID)</label></th>
                        <td><input type="number" name="created_by" id="created_by" value="<?php echo esc_attr( $created_by ); ?>" class="small-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status">Job Status</label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="">Any</option>
                                <?php
                                $statuses = array( 'queued', 'running', 'waiting_for_human', 'completed', 'failed' );
                                foreach ( $statuses as $option ) {
                                    printf(
                                        '<option value="%1$s"%2$s>%1$s</option>',
                                        esc_attr( $option ),
                                        selected( $status, $option, false )
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="start_date">Start Date</label></th>
                        <td><input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="end_date">End Date</label></th>
                        <td><input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button( 'Filter' ); ?>
            </form>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Session ID</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th>Jobs</th>
                        <th>Citations (Approved)</th>
                        <th>Trends</th>
                        <th>Briefs</th>
                        <th>Tokens</th>
                        <th>Cost (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $sessions ) ) : ?>
                        <tr><td colspan="9">No sessions found.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $sessions as $session ) : ?>
                            <tr>
                                <td><?php echo esc_html( $session['id'] ); ?></td>
                                <td><?php echo esc_html( $session['created_by'] ); ?></td>
                                <td><?php echo esc_html( $session['created_at'] ); ?></td>
                                <td><?php echo esc_html( $session['job_count'] ); ?></td>
                                <td><?php echo esc_html( $session['citations_count'] ); ?> (<?php echo esc_html( $session['citations_approved'] ); ?>)</td>
                                <td><?php echo esc_html( $session['trends_count'] ); ?></td>
                                <td><?php echo esc_html( $session['briefs_count'] ); ?></td>
                                <td><?php echo esc_html( (int) ( $session['total_tokens'] ?? 0 ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( ( (int) ( $session['total_cost_micro'] ?? 0 ) ) / 1000000, 6 ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_frameworks_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Frameworks</h1>
            <p>Frameworks will appear here.</p>
        </div>
        <?php
    }

    public function render_sessions_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Sessions</h1>
            <p>Sessions will appear here.</p>
        </div>
        <?php
    }

    public function render_exports_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Exports</h1>
            <p>Exports will appear here.</p>
        </div>
        <?php
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
