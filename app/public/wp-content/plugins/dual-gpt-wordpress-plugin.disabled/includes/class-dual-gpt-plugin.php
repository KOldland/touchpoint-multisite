<?php
/**
 * Main plugin class for Dual-GPT WordPress Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Plugin {

    /**
     * Initialize the plugin
     */
    public function init() {
        // Hook into WordPress
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('dual_gpt_process_job', array($this, 'handle_process_job'), 10, 1);

        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Initialize admin if in admin area
        if (is_admin()) {
            $admin = new Dual_GPT_Admin();
            $admin->init();

            // Add AJAX handlers
            add_action('wp_ajax_dual_gpt_test_api', array($this, 'ajax_test_api'));
            add_action('wp_ajax_dual_gpt_test_integrations', array($this, 'ajax_test_integrations'));
            add_action('admin_notices', array($this, 'maybe_show_api_key_notice'));
        }

        // Include additional classes
        $this->include_classes();

        // Ensure schema is up to date for planner metadata
        $this->maybe_upgrade_schema();
    }

    public function handle_process_job($job_id) {
        $this->process_job($job_id);
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('dual-gpt-wordpress-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Enqueue scripts for admin pages if needed
    }

    /**
     * Warn admins about API key configuration issues
     */
    public function maybe_show_api_key_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $connector = new Dual_GPT_OpenAI_Connector();
        $source = $connector->get_api_key_source();

        if (!$source) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Dual-GPT: OpenAI API key is missing. Set OPENAI_API_KEY in the environment (recommended) or define DUAL_GPT_OPENAI_API_KEY.', 'dual-gpt-wordpress-plugin')
            );
            return;
        }

        if ($source === 'option') {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__('Dual-GPT: API key is loaded from the WordPress database. For production, prefer an environment variable (OPENAI_API_KEY) or a wp-config constant to keep secrets out of the database.', 'dual-gpt-wordpress-plugin')
            );
        }
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        // If using minified production asset
        $file = 'assets/js/sidebar.min.js';
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $file = 'assets/js/sidebar.build.js';
        }

        wp_enqueue_script(
            'dual-gpt-sidebar',
            DUAL_GPT_PLUGIN_URL . $file,
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch'),
            DUAL_GPT_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'dual-gpt-sidebar',
            DUAL_GPT_PLUGIN_URL . 'assets/css/sidebar.css',
            array(),
            DUAL_GPT_PLUGIN_VERSION
        );

        // Localize script with data
        wp_localize_script('dual-gpt-sidebar', 'dualGptData', array(
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('dual-gpt/v1/'),
            'coreSettings' => array(
                'industry_focus' => get_option('dual_gpt_core_industry_focus', 'General'),
                'audience_tier' => get_option('dual_gpt_core_audience_tier', 'General'),
                'risk_tolerance' => get_option('dual_gpt_core_risk_tolerance', 'Moderate'),
                'brand_profile' => get_option('dual_gpt_core_brand_profile', 'Brand A (FSI)'),
            ),
        ));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        if (!defined('DUAL_GPT_LEGACY_MODE') || !DUAL_GPT_LEGACY_MODE) {
            return;
        }

        // Sessions endpoints
        register_rest_route('dual-gpt/v1', '/sessions', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'list_sessions'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_session'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));

        register_rest_route('dual-gpt/v1', '/sessions/(?P<id>[a-zA-Z0-9\\-]+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_session_detail'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_session'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));

        // Planner orchestration endpoints
        register_rest_route('dual-gpt/v1', '/planner/run', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_planner_orchestration'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/framework', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_planner_framework'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/run-framework', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_planner_framework'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/session/(?P<id>[a-zA-Z0-9\\-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_session_detail'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/article/(?P<id>[a-zA-Z0-9\\-]+)/citations', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_planner_article_citations'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/article-action', array(
            'methods' => 'POST',
            'callback' => array($this, 'planner_article_action'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/phase3', array(
            'methods' => 'POST',
            'callback' => array($this, 'rerun_planner_phase3'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/phase4', array(
            'methods' => 'POST',
            'callback' => array($this, 'rerun_planner_phase4'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/phase2-qualification', array(
            'methods' => 'POST',
            'callback' => array($this, 'rerun_planner_phase2_qualification'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/synopses', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_planner_synopses'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/synopsis-plan', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_planner_synopsis_plan'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/jobs-status', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_planner_jobs_status'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/research-validation', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_planner_research_validation'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/policy', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_planner_policy'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/author-policy', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_planner_author_policy'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_planner_author_policy'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));

        register_rest_route('dual-gpt/v1', '/planner/top-line-categories', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_planner_top_line_categories'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'upsert_planner_top_line_category'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));

        register_rest_route('dual-gpt/v1', '/planner/top-line-categories/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_planner_top_line_categories'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/export', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_planner_validation'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/export-synopses', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_planner_synopses'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/export-framework', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_planner_framework'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/queue', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_planner_queue_status'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/queue/add', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_planner_queue_item'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/queue/run', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_planner_queue_item'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/queue/run-bulk', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_planner_queue_bulk'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/queue/reorder', array(
            'methods' => 'POST',
            'callback' => array($this, 'reorder_planner_queue'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/queue/remove', array(
            'methods' => 'POST',
            'callback' => array($this, 'remove_planner_queue_item'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/queue/stop', array(
            'methods' => 'POST',
            'callback' => array($this, 'stop_planner_queue_item'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/queue/clear', array(
            'methods' => 'POST',
            'callback' => array($this, 'clear_planner_queue'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/queue/remove-all', array(
            'methods' => 'POST',
            'callback' => array($this, 'remove_all_planner_queue_items'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));






        // register_rest_route('dual-gpt/v1', '/planner/run-author', array(
        //     'methods' => 'POST',
        //     'callback' => array($this, 'run_planner_author'),
        //     'permission_callback' => array($this, 'check_permissions'),
        // ));

        register_rest_route('dual-gpt/v1', '/planner/phase2', array(
            'methods' => 'POST',
            'callback' => array($this, 'rerun_planner_phase2'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/planner/phase1', array(
            'methods' => 'POST',
            'callback' => array($this, 'rerun_planner_phase1'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Jobs endpoint
        register_rest_route('dual-gpt/v1', '/jobs', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_job'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Streaming endpoint
        register_rest_route('dual-gpt/v1', '/jobs/(?P<id>[a-zA-Z0-9\-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'stream_job'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/images/config', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_image_generation_config'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/images/recommend', array(
            'methods' => 'POST',
            'callback' => array($this, 'recommend_image_generation'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/images/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_image_asset'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Presets endpoints
        register_rest_route('dual-gpt/v1', '/presets', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_presets'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_preset'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        // Individual preset endpoints
        register_rest_route('dual-gpt/v1', '/presets/(?P<id>[a-zA-Z0-9\-]+)', array(
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_preset'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_preset'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        // Audit endpoint
        register_rest_route('dual-gpt/v1', '/audit', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_audit_logs'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Budgets endpoint
        register_rest_route('dual-gpt/v1', '/budgets', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_budgets'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_budget'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        // Blocks import endpoint
        register_rest_route('dual-gpt/v1', '/blocks/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_blocks'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Pull quote metadata endpoint
        register_rest_route('dual-gpt/v1', '/pullquote-meta/(?P<post_id>\\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pullquote_metadata'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('dual-gpt/v1', '/user-preferences/pullquote-view', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_pullquote_view_preference'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'set_pullquote_view_preference'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => array(
                    'view' => array(
                        'type' => 'string',
                        'enum' => array('list', 'table'),
                        'required' => true,
                    ),
                ),
            ),
        ));

        register_rest_route('dual-gpt/v1', '/user-preferences', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_user_preferences'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => array(
                    'key' => array(
                        'type' => 'string',
                        'required' => false,
                    ),
                ),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'set_user_preference'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => array(
                    'key' => array(
                        'type' => 'string',
                        'required' => true,
                    ),
                    'value' => array(
                        'type' => 'string',
                        'required' => true,
                    ),
                ),
            ),
        ));

        // Framework Generator endpoints
        $fg_api = new Framework_Generator_API();
        $fg_api->register_routes();

        // Author Agent endpoints


        // $author_api = new Dual_GPT_Author_Agent_API();
        // $author_api->register_routes();
    }

    /**
     * Check if job is a Framework Generator job
     */
    private function is_framework_generator_job($job) {
        $idempotency = $job['idempotency_key'] ?? '';
        return (bool) preg_match('/^phase[123]-/', $idempotency) ||
               (($job['preset_id'] ?? null) === 'fg-framework-generator');
    }

    /**
     * Process Framework Generator job
     */
    private function process_framework_generator_job($job) {
        $workers = new Framework_Generator_Workers();

        if (strpos($job['idempotency_key'], 'phase1') === 0) {
            $workers->process_phase1($job['id']);
        } elseif (strpos($job['idempotency_key'], 'phase2') === 0) {
            $workers->process_phase2($job['id']);
        } elseif (strpos($job['idempotency_key'], 'phase3') === 0) {
            $workers->process_phase3($job['id']);
        } elseif (strpos($job['idempotency_key'], 'author-') === 0) {
            // Handle author pass-through (would delegate to author system)
            $db = new Dual_GPT_DB_Handler();
            $db->update_job_status($job['id'], 'completed');
        }
    }

    /**
     * Check admin permissions
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * Check basic permissions
     */
    public function check_permissions() {
        return current_user_can('edit_posts');
    }

    public function get_image_generation_config() {
        if (!class_exists('Dual_GPT_Image_Generation_Service')) {
            return new WP_Error('image_service_missing', 'Image generation service is unavailable.', array('status' => 500));
        }

        $service = new Dual_GPT_Image_Generation_Service();

        return new WP_REST_Response($service->get_public_config(), 200);
    }

    public function recommend_image_generation($request) {
        if (!class_exists('Dual_GPT_Image_Generation_Service')) {
            return new WP_Error('image_service_missing', 'Image generation service is unavailable.', array('status' => 500));
        }

        $service = new Dual_GPT_Image_Generation_Service();
        $payload = $this->build_image_request_payload($request);
        $recommendation = $service->recommend($payload);

        return new WP_REST_Response($recommendation, 200);
    }

    public function generate_image_asset($request) {
        if (!class_exists('Dual_GPT_Image_Generation_Service')) {
            return new WP_Error('image_service_missing', 'Image generation service is unavailable.', array('status' => 500));
        }

        $service = new Dual_GPT_Image_Generation_Service();
        $payload = $this->build_image_request_payload($request);
        $result = $service->generate($payload);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }

    private function build_image_request_payload($request) {
        $post_id = intval($request->get_param('post_id'));
        $payload = array(
            'post_id' => $post_id,
            'title' => sanitize_text_field($request->get_param('title')),
            'summary' => sanitize_textarea_field($request->get_param('summary')),
            'audience' => sanitize_text_field($request->get_param('audience')),
            'keywords' => $request->get_param('keywords'),
            'provider' => sanitize_key($request->get_param('provider')),
            'prompt' => sanitize_textarea_field($request->get_param('prompt')),
            'negative_prompt' => sanitize_textarea_field($request->get_param('negative_prompt')),
            'size' => sanitize_text_field($request->get_param('size')),
            'quality' => sanitize_text_field($request->get_param('quality')),
            'alt_text' => sanitize_text_field($request->get_param('alt_text')),
            'caption' => sanitize_text_field($request->get_param('caption')),
            'preset_key' => sanitize_key($request->get_param('preset_key')),
            'text_in_image' => sanitize_text_field($request->get_param('text_in_image')),
            'editorial_accuracy' => rest_sanitize_boolean($request->get_param('editorial_accuracy')),
            'dry_run' => rest_sanitize_boolean($request->get_param('dry_run')),
        );

        if ($request->get_param('store_in_media_library') !== null) {
            $payload['store_in_media_library'] = rest_sanitize_boolean($request->get_param('store_in_media_library'));
        }
        if ($request->get_param('set_featured_image') !== null) {
            $payload['set_featured_image'] = rest_sanitize_boolean($request->get_param('set_featured_image'));
        }

        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post) {
                if ($payload['title'] === '') {
                    $payload['title'] = $post->post_title;
                }
                if ($payload['summary'] === '') {
                    $payload['summary'] = wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 55, '...');
                }
            }
        }

        return $payload;
    }

    /**
     * Get pull quote metadata for a post
     */
    public function get_pullquote_metadata($request) {
        $post_id = (int) $request->get_param('post_id');
        if ($post_id <= 0) {
            return new WP_Error('invalid_post_id', 'Invalid post ID.', array('status' => 400));
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found.', array('status' => 404));
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('rest_forbidden', 'You are not allowed to access this post.', array('status' => 403));
        }

        if (!function_exists('\\Dual_GPT\\Blocks\\CitationQA\\extract_pullquote_metadata_from_post')) {
            return new WP_Error('metadata_unavailable', 'Pull quote metadata helper is unavailable.', array('status' => 500));
        }

        $metadata = \Dual_GPT\Blocks\CitationQA\extract_pullquote_metadata_from_post($post_id);

        return array(
            'post_id' => $post_id,
            'count' => count($metadata),
            'items' => $metadata,
        );
    }

    /**
     * Get pull quote view preference
     */
    public function get_pullquote_view_preference() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'User not logged in.', array('status' => 401));
        }

        $view = get_user_meta($user_id, 'dual_gpt_pullquote_view', true);
        if (!in_array($view, array('list', 'table'), true)) {
            $view = 'list';
        }

        return array('view' => $view);
    }

    /**
     * Set pull quote view preference
     */
    public function set_pullquote_view_preference($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'User not logged in.', array('status' => 401));
        }

        $view = sanitize_text_field($request->get_param('view'));
        if (!in_array($view, array('list', 'table'), true)) {
            return new WP_Error('invalid_view', 'View must be list or table.', array('status' => 400));
        }

        update_user_meta($user_id, 'dual_gpt_pullquote_view', $view);

        return array('view' => $view);
    }

    /**
     * Get user preferences
     */
    public function get_user_preferences($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'User not logged in.', array('status' => 401));
        }

        $key = sanitize_text_field($request->get_param('key'));
        $allowed = $this->get_allowed_user_preferences();

        if ($key) {
            if (!array_key_exists($key, $allowed)) {
                return new WP_Error('invalid_key', 'Preference key is not allowed.', array('status' => 400));
            }
            $meta_key = $allowed[$key]['meta_key'];
            $value = get_user_meta($user_id, $meta_key, true);
            if ($value === '') {
                $value = $allowed[$key]['default'];
            }
            return array('key' => $key, 'value' => $value);
        }

        $values = array();
        foreach ($allowed as $pref_key => $config) {
            $value = get_user_meta($user_id, $config['meta_key'], true);
            if ($value === '') {
                $value = $config['default'];
            }
            $values[$pref_key] = $value;
        }

        return array('preferences' => $values);
    }

    /**
     * Set user preference
     */
    public function set_user_preference($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'User not logged in.', array('status' => 401));
        }

        $key = sanitize_text_field($request->get_param('key'));
        $value = sanitize_text_field($request->get_param('value'));
        $allowed = $this->get_allowed_user_preferences();

        if (!array_key_exists($key, $allowed)) {
            return new WP_Error('invalid_key', 'Preference key is not allowed.', array('status' => 400));
        }

        $validator = $allowed[$key]['validate'];
        if (is_callable($validator) && !$validator($value)) {
            return new WP_Error('invalid_value', 'Preference value is invalid.', array('status' => 400));
        }

        update_user_meta($user_id, $allowed[$key]['meta_key'], $value);

        return array('key' => $key, 'value' => $value);
    }

    /**
     * Allowed preference keys
     */
    private function get_allowed_user_preferences() {
        return array(
            'pullquote_view' => array(
                'meta_key' => 'dual_gpt_pullquote_view',
                'default' => 'list',
                'validate' => function($value) {
                    return in_array($value, array('list', 'table'), true);
                },
            ),
        );
    }

    /**
     * Sanitize session meta payloads
     */
    private function sanitize_session_meta($meta) {
        if (!is_array($meta)) {
            return null;
        }

        $clean = array();
        foreach ($meta as $key => $value) {
            $safe_key = is_string($key) ? sanitize_text_field($key) : $key;
            $clean[$safe_key] = $this->sanitize_meta_value($value);
        }

        if (!empty($clean['research_policy']) && is_array($clean['research_policy'])) {
            $clean['research_policy'] = $this->sanitize_research_policy($clean['research_policy']);
        }

        if (!empty($clean['author_policy']) && is_array($clean['author_policy'])) {
            $clean['author_policy'] = $this->sanitize_author_policy($clean['author_policy']);
        }

        return $clean;
    }

    private function sanitize_research_policy($policy_input) {
        $defaults = $this->default_research_policy();
        $policy_input = is_array($policy_input) ? $policy_input : array();

        $source_mix_input = is_array($policy_input['source_mix_minimums'] ?? null) ? $policy_input['source_mix_minimums'] : array();

        return array(
            'priority_domains' => $this->normalize_research_domain_list($policy_input['priority_domains'] ?? $defaults['priority_domains']),
            'blocked_domains' => $this->normalize_research_domain_list($policy_input['blocked_domains'] ?? $defaults['blocked_domains']),
            'blocked_keywords' => $this->normalize_research_term_list($policy_input['blocked_keywords'] ?? $defaults['blocked_keywords']),
            'preferred_sources' => $this->normalize_research_title_list($policy_input['preferred_sources'] ?? $defaults['preferred_sources']),
            'source_mix_minimums' => array(
                'academic' => max(0, intval($source_mix_input['academic'] ?? $defaults['source_mix_minimums']['academic'])),
                'analyst' => max(0, intval($source_mix_input['analyst'] ?? $defaults['source_mix_minimums']['analyst'])),
                'industry' => max(0, intval($source_mix_input['industry'] ?? $defaults['source_mix_minimums']['industry'])),
                'case_study' => max(0, intval($source_mix_input['case_study'] ?? $defaults['source_mix_minimums']['case_study'])),
            ),
            'max_citations_per_org' => max(1, intval($policy_input['max_citations_per_org'] ?? $defaults['max_citations_per_org'])),
            'recency_months' => max(1, intval($policy_input['recency_months'] ?? $defaults['recency_months'])),
            'min_priority_domains_hit' => max(0, intval($policy_input['min_priority_domains_hit'] ?? $defaults['min_priority_domains_hit'])),
        );
    }

    private function default_author_policy() {
        return array(
            'reporter_voice_required' => true,
            'disallow_first_person' => true,
            'disallow_em_dash' => true,
            'disallow_rhetorical_binaries' => true,
            'disallow_listicle_framing' => true,
            'disallow_tidy_conclusion' => true,
            'min_words' => 1200,
            'max_words' => 2600,
            'banned_phrases' => array(),
        );
    }

    private function sanitize_author_policy($policy_input) {
        $defaults = $this->default_author_policy();
        $policy_input = is_array($policy_input) ? $policy_input : array();

        $banned_phrases = $policy_input['banned_phrases'] ?? $defaults['banned_phrases'];
        if (is_string($banned_phrases)) {
            $banned_phrases = array_filter(array_map('trim', explode(',', $banned_phrases)));
        }
        if (!is_array($banned_phrases)) {
            $banned_phrases = array();
        }
        $banned_phrases = array_values(array_unique(array_filter(array_map(function ($phrase) {
            return strtolower(trim((string) $phrase));
        }, $banned_phrases))));

        $min_words = max(300, intval($policy_input['min_words'] ?? $defaults['min_words']));
        $max_words = max($min_words, intval($policy_input['max_words'] ?? $defaults['max_words']));

        return array(
            'reporter_voice_required' => (bool) ($policy_input['reporter_voice_required'] ?? $defaults['reporter_voice_required']),
            'disallow_first_person' => (bool) ($policy_input['disallow_first_person'] ?? $defaults['disallow_first_person']),
            'disallow_em_dash' => (bool) ($policy_input['disallow_em_dash'] ?? $defaults['disallow_em_dash']),
            'disallow_rhetorical_binaries' => (bool) ($policy_input['disallow_rhetorical_binaries'] ?? $defaults['disallow_rhetorical_binaries']),
            'disallow_listicle_framing' => (bool) ($policy_input['disallow_listicle_framing'] ?? $defaults['disallow_listicle_framing']),
            'disallow_tidy_conclusion' => (bool) ($policy_input['disallow_tidy_conclusion'] ?? $defaults['disallow_tidy_conclusion']),
            'min_words' => $min_words,
            'max_words' => $max_words,
            'banned_phrases' => $banned_phrases,
        );
    }

    /**
     * Sanitize nested meta values
     */
    private function sanitize_meta_value($value) {
        if (is_array($value)) {
            $sanitized = array();
            foreach ($value as $key => $item) {
                $safe_key = is_string($key) ? sanitize_text_field($key) : $key;
                $sanitized[$safe_key] = $this->sanitize_meta_value($item);
            }
            return $sanitized;
        }

        if (is_string($value)) {
            return sanitize_textarea_field($value);
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return null;
    }

    /**
     * List sessions
     */
    public function list_sessions($request) {
        $db = new Dual_GPT_DB_Handler();
        $user_id = get_current_user_id();

        $params = $request->get_params();
        $limit = !empty($params['limit']) ? (int) $params['limit'] : 20;

        $args = array(
            'limit' => $limit,
        );

        if (current_user_can('manage_options') && !empty($params['created_by'])) {
            $args['created_by'] = (int) $params['created_by'];
        } else {
            $args['created_by'] = $user_id;
        }

        $sessions = $db->get_sessions($args);
        $response = array();

        foreach ($sessions as $session) {
            $meta = null;
            if (!empty($session['meta_json'])) {
                $decoded = json_decode($session['meta_json'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $meta = $decoded;
                }
            }

            $response[] = array(
                'id' => $session['id'],
                'title' => $session['title'],
                'role' => $session['role'],
                'preset_id' => $session['preset_id'],
                'created_at' => $session['created_at'],
                'updated_at' => $session['updated_at'],
                'meta' => $meta,
            );
        }

        return new WP_REST_Response($response, 200);
    }

    /**
     * Get session detail
     */
    public function get_session_detail($request) {
        $db = new Dual_GPT_DB_Handler();
        $session_id = $request->get_param('id');

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = null;
        if (!empty($session['meta_json'])) {
            $decoded = json_decode($session['meta_json'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $meta = $decoded;
            }
        }

        $meta = $this->hydrate_planner_meta_from_jobs($session_id, $meta);
        $meta = $this->ensure_research_policy_in_meta($meta);
        $meta = $this->ensure_author_policy_in_meta($meta);

        $session['meta'] = $meta;
        unset($session['meta_json']);

        return new WP_REST_Response($session, 200);
    }

    /**
     * Delete session
     */
    public function delete_session($request) {
        $db = new Dual_GPT_DB_Handler();
        $session_id = sanitize_text_field($request->get_param('id'));

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to delete this session', array('status' => 403));
        }

        $deleted = $db->delete_session($session_id);
        if (is_wp_error($deleted)) {
            return $deleted;
        }

        if (!$deleted) {
            return new WP_Error('session_delete_failed', 'Failed to delete session', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'session_id' => $session_id,
        ), 200);
    }

    /**
     * Create a new session
     */
    public function create_session($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();
        $role = sanitize_text_field($params['role'] ?? 'research');
        $preset_id = !empty($params['preset_id']) ? sanitize_text_field($params['preset_id']) : null;
        $title = !empty($params['title']) ? sanitize_text_field($params['title']) : null;
        $post_id = !empty($params['post_id']) ? intval($params['post_id']) : null;
        $meta_input = isset($params['meta']) && is_array($params['meta']) ? $params['meta'] : array();
        $meta = isset($params['meta']) ? $this->sanitize_session_meta($params['meta']) : null;
        if (!is_array($meta)) {
            $meta = array();
        }

        $topic = sanitize_text_field((string) ($meta['topic'] ?? $title ?? ''));
        $category = $this->find_top_line_category_by_topic($topic);
        if (is_array($category)) {
            $meta['lead_category'] = $category['name'];
            $meta['category_profile'] = array(
                'slug' => $category['slug'] ?? '',
                'name' => $category['name'] ?? '',
                'category_type' => $category['category_type'] ?? '',
                'pref_domain' => $category['pref_domain'] ?? '',
                'core_content_channel' => $category['core_content_channel'] ?? '',
                'target_personas' => $category['target_personas'] ?? array(),
                'target_sponsors' => $category['target_sponsors'] ?? array(),
                'key_competitors' => $category['key_competitors'] ?? array(),
                'trade_associations' => $category['trade_associations'] ?? array(),
                'academic_journals' => $category['academic_journals'] ?? array(),
                'acronyms' => $category['acronyms'] ?? array(),
                'cultural_lexicon' => $category['cultural_lexicon'] ?? array(),
                'key_speakers' => $category['key_speakers'] ?? array(),
                'subgroups' => $category['subgroups'] ?? array(),
            );

            $requested_subgroup = sanitize_text_field((string) ($meta['subgroup'] ?? ''));
            if ($requested_subgroup !== '' && !empty($category['subgroups']) && is_array($category['subgroups'])) {
                foreach ($category['subgroups'] as $subgroup_name) {
                    if (strcasecmp((string) $subgroup_name, $requested_subgroup) === 0) {
                        $meta['subgroup_profile'] = array(
                            'name' => (string) $subgroup_name,
                            'parent_category' => $category['name'] ?? '',
                        );
                        break;
                    }
                }
            }

            $category_policy = is_array($category['research_policy'] ?? null) ? $category['research_policy'] : array();
            $policy_override = is_array($meta_input['research_policy'] ?? null) ? $meta_input['research_policy'] : array();
            $meta['research_policy'] = $this->merge_research_policy($category_policy, $policy_override);
        }

        $meta = $this->ensure_research_policy_in_meta($meta);
        $meta = $this->ensure_author_policy_in_meta($meta);

        // Content channel + exclusion context
        $allowed_channels = array('house', 'quote_club', 'circle');
        $channel = sanitize_text_field((string) ($meta_input['content_channel'] ?? 'house'));
        $meta['content_channel'] = in_array($channel, $allowed_channels, true) ? $channel : 'house';

        if ($meta['content_channel'] === 'quote_club') {
            $mode = sanitize_text_field((string) ($meta_input['quote_club_mode'] ?? 'summary'));
            $meta['quote_club_mode'] = in_array($mode, array('summary', 'framework'), true) ? $mode : 'summary';
            if ($meta['quote_club_mode'] === 'framework') {
                $sv = is_array($meta_input['submitting_vendor'] ?? null) ? $meta_input['submitting_vendor'] : array();
                $meta['submitting_vendor'] = array(
                    'name' => sanitize_text_field((string) ($sv['name'] ?? '')),
                    'type' => strtolower(sanitize_text_field((string) ($sv['type'] ?? ''))),
                );
            }
        } elseif ($meta['content_channel'] === 'circle') {
            $cc = is_array($meta_input['circle_client'] ?? null) ? $meta_input['circle_client'] : array();
            $meta['circle_client'] = array(
                'name' => sanitize_text_field((string) ($cc['name'] ?? '')),
                'type' => strtolower(sanitize_text_field((string) ($cc['type'] ?? ''))),
            );
        }

        $idempotency_key = !empty($params['idempotency_key']) ? sanitize_text_field($params['idempotency_key']) : null;
        if ($idempotency_key && strlen($idempotency_key) > 64) {
            $idempotency_key = substr($idempotency_key, 0, 64);
        }

        $user_id = get_current_user_id();
        if (!$this->check_rate_limit('session', $user_id, 10, 60)) {
            return new WP_Error(
                'rate_limited',
                'Too many session requests. Please wait a moment and try again.',
                array('status' => 429)
            );
        }

        $session_data = array(
            'role' => $role,
            'preset_id' => $preset_id,
            'title' => $title,
            'post_id' => $post_id,
            'idempotency_key' => $idempotency_key,
            'meta_json' => $meta ? wp_json_encode($meta) : null,
        );

        if ($idempotency_key) {
            $existing = $db->get_session_by_idempotency($idempotency_key);
            if ($existing) {
                return new WP_REST_Response(array(
                    'session_id' => $existing['id'],
                    'role' => $existing['role'],
                    'preset_id' => $existing['preset_id'],
                    'idempotent' => true,
                ), 200);
            }
        }

        $session_id = $db->insert_session($session_data);

        if (is_wp_error($session_id)) {
            return new WP_Error('session_creation_failed', $session_id->get_error_message(), array('status' => 500));
        }

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'role' => $role,
            'preset_id' => $preset_id,
        ), 201);
    }

    /**
     * Run the planner orchestration for a session
     */
    public function run_planner_orchestration($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        $focus_level = $this->normalize_focus_level($request->get_param('focus_level'));
        $requested_policy = $request->get_param('research_policy');
        if ($focus_level !== null || is_array($requested_policy)) {
            $db = new Dual_GPT_DB_Handler();
            $session = $db->get_session($session_id);
            if (!$session) {
                return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
            }
            if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
                return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
            }
            $meta = $this->decode_session_meta($session['meta_json'] ?? null);
            $meta = $this->ensure_research_policy_in_meta($meta);
            $meta = $this->ensure_author_policy_in_meta($meta);
            if ($focus_level !== null) {
                $meta['focus_level'] = $focus_level;
            }
            if (is_array($requested_policy)) {
                $meta['research_policy'] = $this->sanitize_research_policy($requested_policy);
            }
            $meta = $this->ensure_research_policy_in_meta($meta);
            $meta = $this->ensure_author_policy_in_meta($meta);
            $db->update_session_meta($session_id, $meta);
        }

        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $result = $orchestrator->run($session_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 202);
    }

    /**
     * Generate a framework from an article summary and attach to planner session
     */
    public function generate_planner_framework($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $article = $request->get_param('article');

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        if (empty($article) || !is_array($article)) {
            return new WP_Error('missing_article', 'Article summary is required', array('status' => 400));
        }

        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $result = $orchestrator->generate_framework($session_id, $article);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Run framework for a single article by ID
     */
    public function run_planner_framework($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $article_id = sanitize_text_field($request->get_param('article_id'));
        $force = (bool) $request->get_param('force');

        if (empty($session_id) || empty($article_id)) {
            return new WP_Error('missing_params', 'Session ID and article ID are required', array('status' => 400));
        }
        error_log('[PLANNER][FRAMEWORK] Run requested for session ' . $session_id . ' article ' . $article_id);

        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $result = $orchestrator->run_framework_for_article($session_id, $article_id, $force);

        if (is_wp_error($result)) {
            error_log('[PLANNER][FRAMEWORK] Run failed for session ' . $session_id . ' article ' . $article_id . ': ' . $result->get_error_message());
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Get citations for an article in a planner session
     */
    public function get_planner_article_citations($request) {
        $article_id = sanitize_text_field($request->get_param('id'));
        $session_id = sanitize_text_field($request->get_param('session_id'));

        if (empty($article_id) || empty($session_id)) {
            return new WP_Error('missing_params', 'Session ID and article ID are required', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $articles = $meta['articles'] ?? array();
        foreach ($articles as $article) {
            if (($article['id'] ?? '') === $article_id) {
                return new WP_REST_Response(array(
                    'citations' => $article['citations'] ?? array(),
                ), 200);
            }
        }

        return new WP_Error('article_not_found', 'Article not found', array('status' => 404));
    }

    /**
     * Perform planner article-level actions (dismiss, deep_dive)
     */
    public function planner_article_action($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $article_id = sanitize_text_field($request->get_param('article_id'));
        $action = sanitize_key($request->get_param('action'));
        $params = $request->get_param('params');

        if (empty($session_id) || empty($article_id) || empty($action)) {
            return new WP_Error('missing_params', 'Session ID, article ID, and action are required', array('status' => 400));
        }

        if (!in_array($action, array('dismiss', 'deep_dive', 'dive_deeper', 'opinion_piece'), true)) {
            return new WP_Error('invalid_action', 'Unsupported article action', array('status' => 400));
        }

        if ($action === 'dive_deeper' && empty($params)) {
            return new WP_Error('missing_params', 'Dive Deeper action requires params object', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();

        $article_index = -1;
        foreach ($articles as $index => $article) {
            if (($article['id'] ?? '') === $article_id) {
                $article_index = $index;
                break;
            }
        }

        if ($article_index < 0) {
            return new WP_Error('article_not_found', 'Article not found', array('status' => 404));
        }

        if ($action === 'dismiss') {
            unset($articles[$article_index]);
            $meta['articles'] = array_values($articles);
            $db->update_session_meta($session_id, $meta);

            return new WP_REST_Response(array(
                'session_id' => $session_id,
                'article_id' => $article_id,
                'action' => 'dismiss',
                'remaining_articles' => count($meta['articles']),
            ), 200);
        }

        if ($action === 'opinion_piece') {
            error_log('[PLANNER][OPINION] Run requested for session ' . $session_id . ' article ' . $article_id);
            $opinion_article = $articles[$article_index] ?? array();
            $existing_framework = $opinion_article['framework']['output'] ?? null;
            $lite_framework_generated = false;
            if (empty($existing_framework)) {
                $lite_framework = $this->build_opinion_lite_framework($opinion_article);
                $existing_framework_meta = isset($opinion_article['framework']) && is_array($opinion_article['framework']) ? $opinion_article['framework'] : array();
                $articles[$article_index]['framework'] = array_merge(
                    $existing_framework_meta,
                    array(
                        'status' => 'completed',
                        'output' => $lite_framework,
                        'generated_at' => current_time('mysql'),
                        'lite_mode' => 'opinion',
                        'is_lite_framework' => true,
                        'error_message' => '',
                    )
                );
                $meta['articles'] = $articles;
                $db->update_session_meta($session_id, $meta);
                $lite_framework_generated = true;
                error_log('[PLANNER][OPINION] Lite framework generated for session ' . $session_id . ' article ' . $article_id);
            }

            $opinion_request = new WP_REST_Request('POST');
            $opinion_request->set_param('session_id', $session_id);
            $opinion_request->set_param('article_id', $article_id);
            $opinion_request->set_param('author_profile', 'journalistic');
            $response = $this->run_planner_author($opinion_request);
            if (is_wp_error($response)) {
                error_log('[PLANNER][OPINION] Run failed for session ' . $session_id . ' article ' . $article_id . ': ' . $response->get_error_message());
                return $response;
            }

            $data = $response instanceof WP_REST_Response ? $response->get_data() : (is_array($response) ? $response : array());
            $opinion_job_id = sanitize_text_field((string) ($data['job_id'] ?? ''));
            error_log('[PLANNER][OPINION] Run queued for session ' . $session_id . ' article ' . $article_id . ' job ' . ($opinion_job_id ?: 'unknown'));
            return new WP_REST_Response(array(
                'session_id' => $session_id,
                'article_id' => $article_id,
                'action' => 'opinion_piece',
                'job_id' => $opinion_job_id,
                'lite_framework_generated' => $lite_framework_generated,
                'status' => 'queued',
            ), 200);
        }

        // Handle dive_deeper: queue specialist evidence-check job (no synthetic citation merge)
        if ($action === 'dive_deeper') {
            $article = $articles[$article_index];
            $job_id = $this->queue_dive_deeper_job($article, $session_id, $meta, $params);

            error_log(sprintf(
                '[PLANNER][DIVE_DEEPER] session=%s article=%s job_id=%s status=%s',
                (string) $session_id,
                (string) $article_id,
                (string) ($job_id ?? 'null'),
                $job_id ? 'queued' : 'queue_failed'
            ));

            return new WP_REST_Response(array(
                'session_id' => $session_id,
                'article_id' => $article_id,
                'action' => 'dive_deeper',
                'job_id' => $job_id,
                'status' => 'queued',
                'message' => 'Source-check job queued. Results will be applied when completed.',
            ), 200);
        }

        // Legacy deep_dive handling (simple enrichment)
        $article = $articles[$article_index];
        $before_count = isset($article['citations']) && is_array($article['citations']) ? count($article['citations']) : 0;
        $article = $this->enrich_article_citations_from_phase4($article, $meta);
        $after_count = isset($article['citations']) && is_array($article['citations']) ? count($article['citations']) : 0;

        $article['deep_dive'] = array(
            'requested_at' => gmdate('c'),
            'citations_added' => max(0, $after_count - $before_count),
        );

        $articles[$article_index] = $article;
        $meta['articles'] = $articles;
        $db->update_session_meta($session_id, $meta);

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'article_id' => $article_id,
            'action' => 'deep_dive',
            'citations_before' => $before_count,
            'citations_after' => $after_count,
            'citations_added' => max(0, $after_count - $before_count),
        ), 200);
    }

    private function queue_dive_deeper_job($article, $session_id, $meta, $params) {
        $db = new Dual_GPT_DB_Handler();
        $article_id = $article['id'] ?? null;
        $headline = $article['headline'] ?? $article['title'] ?? 'Article';
        $summary = $article['summary'] ?? $article['brief'] ?? '';

        // Build specialist source-check prompt using article summary/headline keywords.
        $target_min = intval($params['target_min_citations'] ?? 4);
        $recency = intval($params['recency_months'] ?? 18);
        $source_mix = $params['source_mix_minimums'] ?? array('industry' => 1, 'news' => 1, 'research' => 1);
        $keyword_seed = strtolower(trim(($headline . ' ' . $summary)));
        $keyword_seed = preg_replace('/[^a-z0-9\s]/', ' ', $keyword_seed);
        $tokens = array_values(array_filter(array_map('trim', explode(' ', $keyword_seed))));
        $stopwords = array('the', 'and', 'for', 'with', 'this', 'that', 'from', 'into', 'about', 'what', 'when', 'where', 'will', 'would', 'could', 'should', 'are', 'is', 'was', 'were', 'has', 'have', 'had', 'your', 'their', 'our', 'its', 'you');
        $tokens = array_values(array_filter($tokens, function($token) use ($stopwords) {
            return strlen($token) >= 4 && !in_array($token, $stopwords, true);
        }));
        $keywords = array_slice(array_values(array_unique($tokens)), 0, 12);
        $keyword_text = !empty($keywords) ? implode(', ', $keywords) : 'none';

        $prompt = sprintf(
            'Research specialist: perform a source-check for this article and return supporting evidence for its argumentation. Headline: "%s". Summary: "%s". Keyword anchors: %s. Target: %d relevant citations from last %d months. Source mix preference: %s. Requirements: (1) citation must directly support or challenge a concrete claim implied by the summary, (2) avoid generic background links, (3) prefer primary/authoritative sources, (4) include a short relevance_note for each citation that ties it to the article argument. Return ONLY valid JSON: {"citations":[{"title":"...","url":"...","source":"...","published_date":"...","relevance_note":"..."}]}.',
            $headline,
            $summary,
            $keyword_text,
            $target_min,
            $recency,
            implode(', ', array_keys($source_mix))
        );

        error_log(sprintf(
            '[PLANNER][DIVE_DEEPER] queue_start session=%s article=%s target_min=%d recency=%d keywords=%s',
            (string) $session_id,
            (string) ($article_id ?? 'unknown'),
            (int) $target_min,
            (int) $recency,
            $keyword_text
        ));

        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $idempotency_key = 'planner-dive-deeper-' . md5($session_id . ':' . ($article_id ?? 'unknown') . ':' . json_encode($params));
        $job_id = $orchestrator->run_job($session_id, $idempotency_key, $prompt, 'verify');

        if (is_wp_error($job_id)) {
            error_log(sprintf(
                '[PLANNER][DIVE_DEEPER] queue_error session=%s article=%s error=%s',
                (string) $session_id,
                (string) ($article_id ?? 'unknown'),
                $job_id->get_error_message()
            ));
        } else {
            error_log(sprintf(
                '[PLANNER][DIVE_DEEPER] queue_ok session=%s article=%s job_id=%s',
                (string) $session_id,
                (string) ($article_id ?? 'unknown'),
                (string) $job_id
            ));
        }

        if (!isset($article['dive_deeper_jobs']) || !is_array($article['dive_deeper_jobs'])) {
            $article['dive_deeper_jobs'] = array();
        }

        $resolved_job_id = is_wp_error($job_id) ? null : (string) $job_id;
        $resolved_status = 'queued';
        if ($resolved_job_id) {
            $job_row = $db->get_job($resolved_job_id);
            if ($job_row && !empty($job_row['status'])) {
                $resolved_status = sanitize_key((string) $job_row['status']);
            }
        } elseif (is_wp_error($job_id)) {
            $resolved_status = 'failed';
        }

        $job_payload = array(
            'job_id' => $resolved_job_id,
            'status' => $resolved_status,
            'requested_at' => gmdate('c'),
            'params' => $params,
            'keyword_anchors' => $keywords,
        );

        $updated_existing = false;
        if ($resolved_job_id) {
            foreach ($article['dive_deeper_jobs'] as $job_index => $existing_job) {
                if (($existing_job['job_id'] ?? '') !== $resolved_job_id) {
                    continue;
                }
                $article['dive_deeper_jobs'][$job_index] = array_merge($existing_job, $job_payload);
                $updated_existing = true;
                break;
            }
        }

        if (!$updated_existing) {
            $article['dive_deeper_jobs'][] = $job_payload;
        }

        // Persist job metadata on the session article.
        if ($article_id !== null) {
            $articles = $meta['articles'] ?? array();
            foreach ($articles as $idx => $a) {
                if (($a['id'] ?? null) == $article_id) {
                    $articles[$idx] = $article;
                    break;
                }
            }
            $meta['articles'] = $articles;
            $db->update_session_meta($session_id, $meta);
        }

        return is_wp_error($job_id) ? null : $job_id;
    }

    /**
     * Phase 3 auto dive-deeper: silently queue evidence-check jobs for under-cited articles
     * once all synopsis batch jobs reach a terminal state. Fires at most once per session
     * (guarded by the auto_dive_fired flag in session meta).
     */
    private function maybe_auto_dive_after_synopses($session, $meta, $db) {
        $synopsis_job_ids = $meta['synopsis_job_ids'] ?? array();
        if (empty($synopsis_job_ids) || !is_array($synopsis_job_ids)) {
            return;
        }

        // Return early if any synopsis job is still active.
        $terminal = array('completed', 'failed');
        foreach ($synopsis_job_ids as $jid) {
            $job_row = $db->get_job(sanitize_text_field((string) $jid));
            if (!$job_row || !in_array(sanitize_key((string) ($job_row['status'] ?? '')), $terminal, true)) {
                return;
            }
        }

        // Guard against double-firing if two jobs complete simultaneously.
        $session_id = (string) ($session['id'] ?? '');
        if (!empty($meta['auto_dive_fired'])) {
            return;
        }
        $meta['auto_dive_fired'] = true;
        $db->update_session_meta($session_id, $meta);

        // Find articles still below the 4-citation threshold.
        $auto_dive_max = 6;
        $articles      = $meta['articles'] ?? array();
        $under_cited   = array();
        foreach ($articles as $article) {
            if (count($article['citations'] ?? array()) >= 4) {
                continue;
            }
            // Skip articles that already have an active or completed dive-deeper job.
            foreach ($article['dive_deeper_jobs'] ?? array() as $dj) {
                if (in_array($dj['status'] ?? '', array('queued', 'running', 'processing', 'completed'), true)) {
                    continue 2;
                }
            }
            $article['_auto_cite_count'] = count($article['citations'] ?? array());
            $under_cited[] = $article;
        }

        if (empty($under_cited)) {
            error_log(sprintf(
                '[PLANNER][AUTO_DIVE] All synopsis batches complete. Citation quality OK — no under-cited articles (session: %s).',
                $session_id
            ));
            return;
        }

        usort($under_cited, function ($a, $b) {
            return ($a['_auto_cite_count'] ?? 0) - ($b['_auto_cite_count'] ?? 0);
        });

        $to_queue    = array_slice($under_cited, 0, $auto_dive_max);
        $dive_params = array(
            'target_min_citations' => 6,
            'recency_months'       => 24,
            'source_mix_minimums'  => array('industry' => 2, 'news' => 1, 'research' => 1),
        );

        $queued_count = 0;
        foreach ($to_queue as $article_to_dive) {
            unset($article_to_dive['_auto_cite_count']);
            $job_id = $this->queue_dive_deeper_job($article_to_dive, $session_id, $meta, $dive_params);
            if ($job_id) {
                $queued_count++;
            }
            // Reload meta so the next iteration sees dive_deeper_jobs entries written by the previous call.
            $fresh = $db->get_session($session_id);
            if ($fresh) {
                $meta = $this->decode_session_meta($fresh['meta_json'] ?? null);
            }
        }

        error_log(sprintf(
            '[PLANNER][AUTO_DIVE] Queued %d/%d dive-deeper jobs for under-cited articles (session: %s).',
            $queued_count,
            count($to_queue),
            $session_id
        ));
    }

    private function enrich_article_citations_from_phase4($article, $meta) {
        $existing = isset($article['citations']) && is_array($article['citations']) ? $article['citations'] : array();
        $existing_map = array();

        foreach ($existing as $citation) {
            $key = strtolower(trim(($citation['url'] ?? '') . '|' . ($citation['title'] ?? '')));
            if ($key !== '|') {
                $existing_map[$key] = true;
            }
        }

        $article_blob = strtolower(
            implode(' ', array_filter(array(
                (string) ($article['headline'] ?? ''),
                (string) ($article['title'] ?? ''),
                (string) ($article['summary'] ?? ''),
                (string) ($article['brief'] ?? ''),
                implode(' ', isset($article['keywords']) && is_array($article['keywords']) ? $article['keywords'] : array()),
            )))
        );

        $validated_topics = $meta['phases']['phase4']['payload']['validated_topics'] ?? array();
        if (!is_array($validated_topics) || empty($validated_topics)) {
            $article['citation_count'] = count($existing);
            return $article;
        }

        $merged = $existing;
        foreach ($validated_topics as $topic) {
            $topic_blob = strtolower(
                implode(' ', array_filter(array(
                    (string) ($topic['topic'] ?? ''),
                    (string) ($topic['title'] ?? ''),
                    implode(' ', isset($topic['keywords']) && is_array($topic['keywords']) ? $topic['keywords'] : array()),
                )))
            );

            $is_related = false;
            if ($topic_blob !== '' && $article_blob !== '') {
                $topic_tokens = preg_split('/\\s+/', $topic_blob);
                foreach ($topic_tokens as $token) {
                    $token = trim((string) $token);
                    if (strlen($token) < 5) {
                        continue;
                    }
                    if (strpos($article_blob, $token) !== false) {
                        $is_related = true;
                        break;
                    }
                }
            }

            if (!$is_related) {
                continue;
            }

            $topic_citations = isset($topic['citations']) && is_array($topic['citations']) ? $topic['citations'] : array();
            foreach ($topic_citations as $citation) {
                if (!is_array($citation)) {
                    continue;
                }
                $key = strtolower(trim(($citation['url'] ?? '') . '|' . ($citation['title'] ?? '')));
                if ($key === '|' || isset($existing_map[$key])) {
                    continue;
                }
                $existing_map[$key] = true;
                $merged[] = $citation;
            }
        }

        $article['citations'] = $merged;
        $article['citation_count'] = count($merged);
        return $article;
    }

    /**
     * Re-run planner Phase 3 for a session
     */
    public function rerun_planner_phase3($request) {
        $db = new Dual_GPT_DB_Handler();
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $focus_level = $this->normalize_focus_level($request->get_param('focus_level'));

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $meta = $this->ensure_research_policy_in_meta($meta);
        if ($focus_level !== null) {
            $meta['focus_level'] = $focus_level;
        }
        $topic = $meta['topic'] ?? $session['title'] ?? '';
        $includes = $this->normalize_terms($meta['includes'] ?? array());
        $excludes = $this->normalize_terms($meta['excludes'] ?? array());
        $phase1_summary = $meta['phases']['phase1']['summary'] ?? '';

        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $phase2_context = $orchestrator->build_phase2_context($meta);
        $prompt = $orchestrator->build_phase2_prompt(
            $topic,
            $includes,
            $excludes,
            $phase1_summary,
            $phase2_context,
            $meta['focus_level'] ?? 50
        );
        $job_id = $orchestrator->run_job($session_id, 'planner-phase3-' . $session_id . '-' . time(), $prompt, 'verify');

        if (is_wp_error($job_id)) {
            return $job_id;
        }

        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
        $phases['phase3'] = array(
            'title' => 'Research Phase 3',
            'job_id' => $job_id,
            'status' => 'queued',
        );
        if (isset($phases['phase4'])) {
            unset($phases['phase4']);
        }
        $meta['phases'] = $phases;
        $meta['articles'] = array();
        $db->update_session_meta($session_id, $meta);

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'job_id' => $job_id,
        ), 200);
    }

    /**
     * Re-run planner Phase 4 for a session
     */
    public function rerun_planner_phase4($request) {
        $db = new Dual_GPT_DB_Handler();
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $focus_level = $this->normalize_focus_level($request->get_param('focus_level'));

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $meta = $this->ensure_research_policy_in_meta($meta);
        if ($focus_level !== null) {
            $meta['focus_level'] = $focus_level;
        }

        $topic = $meta['topic'] ?? $session['title'] ?? '';
        $includes = $this->normalize_terms($meta['includes'] ?? array());
        $excludes = $this->normalize_terms($meta['excludes'] ?? array());
        $phase3_summary = $meta['phases']['phase3']['summary'] ?? '';

        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $phase3_context = $orchestrator->build_phase3_context($meta);
        $prompt = $orchestrator->build_phase3_prompt(
            $topic,
            $includes,
            $excludes,
            $phase3_summary,
            $phase3_context,
            $meta['focus_level'] ?? 50
        );
        $job_id = $orchestrator->run_job($session_id, 'planner-phase4-' . $session_id . '-' . time(), $prompt, 'verify');

        if (is_wp_error($job_id)) {
            return $job_id;
        }

        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
        $phases['phase4'] = array(
            'title' => 'Research Phase 4',
            'job_id' => $job_id,
            'status' => 'queued',
        );
        $meta['phases'] = $phases;
        $meta['articles'] = array();
        $db->update_session_meta($session_id, $meta);

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'job_id' => $job_id,
        ), 200);
    }

    /**
     * Re-run planner Phase 2 (Qualification) for a session
     */
    public function rerun_planner_phase2_qualification($request) {
        $db = new Dual_GPT_DB_Handler();
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $focus_level = $this->normalize_focus_level($request->get_param('focus_level'));

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        error_log('[PLANNER][PHASE2] Qualification rerun requested for session ' . $session_id);

        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $meta = $this->ensure_research_policy_in_meta($meta);
        if ($focus_level !== null) {
            $meta['focus_level'] = $focus_level;
        }

        $candidate_keywords = $meta['phase1']['candidate_keywords'] ?? array();
        if (empty($candidate_keywords)) {
            $phase1_payload = $meta['phases']['phase1']['payload'] ?? array();
            if (!empty($phase1_payload['candidate_keywords']) && is_array($phase1_payload['candidate_keywords'])) {
                $candidate_keywords = $phase1_payload['candidate_keywords'];
                $meta['phase1']['candidate_keywords'] = $candidate_keywords;
                $db->update_session_meta($session_id, $meta);
            }
        }
        if (empty($candidate_keywords)) {
            error_log('[PLANNER][PHASE2] No candidate keywords for session ' . $session_id);
            return new WP_Error(
                'phase1_missing',
                'Phase 1 returned no candidate keywords. Re-run Phase 1 or widen focus to generate at least 12 keywords.',
                array('status' => 400)
            );
        }

        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $effective_focus = $meta['focus_level'] ?? 50;
        $max_keywords = $this->map_focus_to_keyword_limit($effective_focus);
        $phase1_5 = $orchestrator->run_phase1_5($session_id, $candidate_keywords, $max_keywords);
        if (is_wp_error($phase1_5)) {
            error_log('[PLANNER][PHASE2] Qualification failed for session ' . $session_id . ': ' . $phase1_5->get_error_message());
            $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
            $phases['phase2'] = array(
                'title' => 'Research Phase 2',
                'status' => 'failed',
                'completed_at' => current_time('mysql'),
                'error' => $phase1_5->get_error_message(),
            );
            $meta['phases'] = $phases;
            $meta['phase2_error'] = $phase1_5->get_error_message();
            $db->update_session_meta($session_id, $meta);
            return $phase1_5;
        }

        $meta['phase2'] = $phase1_5;
        $phase2_validation = $this->validate_research_phase_payload('phase2', $phase1_5, $meta);
        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
        $phases['phase2'] = array(
            'title' => 'Research Phase 2',
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'payload' => $phase1_5,
            'validation' => $phase2_validation,
            'summary' => $phase1_5['summary'] ?? '',
        );
        $meta['phases'] = $phases;
        $meta = $this->refresh_research_validation_index($meta);
        $db->update_session_meta($session_id, $meta);

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'status' => 'completed',
        ), 200);
    }

    /**
     * Return recommended synopsis plan for a session
     */
    public function get_planner_synopsis_plan($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $total = intval($request->get_param('total')) ?: 20;

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $plan = $this->build_synopsis_plan($meta, $total);
        if (is_wp_error($plan)) {
            return $plan;
        }

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'total' => $total,
            'plan' => $plan,
        ), 200);
    }

    public function get_planner_research_validation($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $meta = $this->hydrate_planner_meta_from_jobs($session_id, $meta);
        $meta = $this->ensure_author_policy_in_meta($meta);

        $validation = $meta['research_validation'] ?? array(
            'summary' => array(
                'error_count' => 0,
                'warning_count' => 0,
                'has_errors' => false,
                'generated_at' => current_time('mysql'),
            ),
            'policy' => $this->resolve_research_policy($meta),
            'by_phase' => array(),
            'issues' => array(),
        );

        $search_provider_status = get_transient('dual_gpt_search_provider_status');
        if (!is_array($search_provider_status)) {
            $search_provider_status = array(
                'has_errors' => false,
                'provider' => 'unknown',
                'provider_chain' => array(),
                'warning' => '',
                'provider_errors' => array(),
                'checked_at' => null,
            );
        }

        $provider_errors = is_array($search_provider_status['provider_errors'] ?? null)
            ? $search_provider_status['provider_errors']
            : array();
        $serpapi_error = false;
        foreach ($provider_errors as $provider_error) {
            if (stripos((string) $provider_error, 'serpapi:') !== false) {
                $serpapi_error = true;
                break;
            }
        }
        if ($serpapi_error) {
            $search_provider_status['admin_instruction'] = 'Search provider failure detected in SerpAPI. Please contact your System Administrator to restore SerpAPI quota/credentials and verify fallback provider support before re-running Phase 4.';
        }

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'research_policy' => $this->resolve_research_policy($meta),
            'research_validation' => $validation,
            'search_provider_status' => $search_provider_status,
        ), 200);
    }

    public function update_planner_policy($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $policy_input = $request->get_param('research_policy');

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        if (!is_array($policy_input)) {
            return new WP_Error('missing_policy', 'research_policy payload is required', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $meta = $this->ensure_research_policy_in_meta($meta);
        $meta = $this->ensure_author_policy_in_meta($meta);

        $old_policy = $this->resolve_research_policy($meta);
        $meta['research_policy'] = $this->sanitize_research_policy($policy_input);
        $meta = $this->ensure_research_policy_in_meta($meta);
        $new_policy = $this->resolve_research_policy($meta);
        $policy_changed = wp_json_encode($old_policy) !== wp_json_encode($new_policy);

        $updated = $db->update_session_meta($session_id, $meta);
        if (!$updated) {
            return new WP_Error('policy_save_failed', 'Failed to save research policy', array('status' => 500));
        }

        if ($policy_changed) {
            $db->insert_audit_log(null, 'planner_policy_updated', array(
                'session_id' => $session_id,
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql'),
                'old_policy' => $old_policy,
                'new_policy' => $new_policy,
            ));
        }

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'changed' => $policy_changed,
            'research_policy' => $new_policy,
        ), 200);
    }

    public function get_planner_author_policy($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $meta = $this->ensure_author_policy_in_meta($meta);

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'author_policy' => $this->resolve_author_policy($meta),
        ), 200);
    }

    public function update_planner_author_policy($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $policy_input = $request->get_param('author_policy');

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        if (!is_array($policy_input)) {
            return new WP_Error('missing_policy', 'author_policy payload is required', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $meta = $this->ensure_author_policy_in_meta($meta);

        $old_policy = $this->resolve_author_policy($meta);
        $meta['author_policy'] = $this->sanitize_author_policy($policy_input);
        $meta = $this->ensure_author_policy_in_meta($meta);
        $new_policy = $this->resolve_author_policy($meta);
        $policy_changed = wp_json_encode($old_policy) !== wp_json_encode($new_policy);

        $updated = $db->update_session_meta($session_id, $meta);
        if (!$updated) {
            return new WP_Error('policy_save_failed', 'Failed to save author policy', array('status' => 500));
        }

        if ($policy_changed) {
            $db->insert_audit_log(null, 'planner_author_policy_updated', array(
                'session_id' => $session_id,
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql'),
                'old_policy' => $old_policy,
                'new_policy' => $new_policy,
            ));
        }

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'changed' => $policy_changed,
            'author_policy' => $new_policy,
        ), 200);
    }

    public function get_planner_top_line_categories($request) {
        $categories = array_values($this->get_top_line_categories());

        return new WP_REST_Response(array(
            'top_line_categories' => $categories,
        ), 200);
    }

    public function upsert_planner_top_line_category($request) {
        $payload = $request->get_param('top_line_category');
        if (!is_array($payload)) {
            return new WP_Error('missing_category', 'top_line_category payload is required', array('status' => 400));
        }

        $existing = $this->get_top_line_categories();
        $sanitized = $this->sanitize_top_line_category($payload);

        if ($sanitized['name'] === '') {
            return new WP_Error('invalid_name', 'Category name is required', array('status' => 400));
        }

        $slug = $this->normalize_top_line_category_slug($payload['slug'] ?? $sanitized['name']);
        if ($slug === '') {
            return new WP_Error('invalid_slug', 'Unable to derive a category slug', array('status' => 400));
        }

        if (!empty($payload['name']) && is_string($payload['name'])) {
            foreach ($existing as $existing_slug => $row) {
                if ($existing_slug === $slug) {
                    continue;
                }
                if (strcasecmp((string) ($row['name'] ?? ''), (string) $payload['name']) === 0) {
                    $slug = $existing_slug;
                    break;
                }
            }
        }

        $sanitized['slug'] = $slug;
        $existing[$slug] = $sanitized;

        if (!$this->save_top_line_categories($existing)) {
            return new WP_Error('save_failed', 'Unable to save top-line category', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'top_line_category' => $sanitized,
            'updated' => true,
        ), 200);
    }

    public function import_planner_top_line_categories($request) {
        $rows = $request->get_param('rows');
        $csv = $request->get_param('csv');

        if (!is_array($rows) && !is_string($csv)) {
            return new WP_Error('missing_payload', 'Provide rows[] or csv string for import.', array('status' => 400));
        }

        if (!is_array($rows)) {
            $rows = $this->parse_top_line_categories_csv($csv);
            if (is_wp_error($rows)) {
                return $rows;
            }
        }

        $existing = $this->get_top_line_categories();
        $created_or_updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $mapped = $this->map_top_line_category_import_row($row);
            $sanitized = $this->sanitize_top_line_category($mapped);
            if ($sanitized['name'] === '') {
                $skipped++;
                continue;
            }

            $slug = $this->normalize_top_line_category_slug($sanitized['slug'] ?? $sanitized['name']);
            if ($slug === '') {
                $skipped++;
                continue;
            }

            $sanitized['slug'] = $slug;
            $existing[$slug] = $sanitized;
            $created_or_updated++;
        }

        if (!$this->save_top_line_categories($existing)) {
            return new WP_Error('save_failed', 'Unable to save imported top-line categories', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'updated' => true,
            'created_or_updated' => $created_or_updated,
            'skipped' => $skipped,
            'total' => $created_or_updated + $skipped,
        ), 200);
    }

    public function get_planner_queue_status($request) {
        global $wpdb;

        $table = $wpdb->prefix . 'planner_task_queue';
        $this->sync_planner_queue_linked_job_statuses();
        $counts = array(
            'queued' => 0,
            'running' => 0,
            'dispatched' => 0,
            'completed' => 0,
            'failed' => 0,
        );

        $count_rows = $wpdb->get_results("SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", ARRAY_A);
        if (is_array($count_rows)) {
            foreach ($count_rows as $row) {
                $status = sanitize_key($row['status'] ?? '');
                if (array_key_exists($status, $counts)) {
                    $counts[$status] = intval($row['c'] ?? 0);
                }
            }
        }

        $items = $wpdb->get_results(
            "SELECT id, session_id, article_id, task_type, status, payload_json, linked_job_id, position, created_at, updated_at, error_message
             FROM {$table}
             ORDER BY FIELD(status, 'queued', 'running', 'dispatched', 'failed', 'completed'), position ASC, created_at ASC
             LIMIT 250",
            ARRAY_A
        );

        $active_items = array();
        if (is_array($items)) {
            foreach ($items as $item) {
                $active_items[] = array(
                    'id' => (string) ($item['id'] ?? ''),
                    'session_id' => (string) ($item['session_id'] ?? ''),
                    'article_id' => (string) ($item['article_id'] ?? ''),
                    'task_type' => (string) ($item['task_type'] ?? ''),
                    'status' => (string) ($item['status'] ?? ''),
                    'payload' => !empty($item['payload_json']) ? json_decode($item['payload_json'], true) : null,
                    'linked_job_id' => (string) ($item['linked_job_id'] ?? ''),
                    'position' => intval($item['position'] ?? 0),
                    'created_at' => (string) ($item['created_at'] ?? ''),
                    'updated_at' => (string) ($item['updated_at'] ?? ''),
                    'error_message' => (string) ($item['error_message'] ?? ''),
                );
            }
        }

        return new WP_REST_Response(array(
            'counts' => $counts,
            'active_items' => $active_items,
        ), 200);
    }

    public function get_planner_jobs_status($request) {
        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        $job_ids = $request->get_param('job_ids');

        if ($session_id === '') {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        if (!is_array($job_ids) || empty($job_ids)) {
            return new WP_Error('missing_job_ids', 'At least one job ID is required', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $normalized_ids = array_values(array_unique(array_filter(array_map('sanitize_text_field', $job_ids))));
        $jobs = array();
        $failed_count = 0;
        $active_count = 0;
        $not_found_count = 0;

        foreach ($normalized_ids as $job_id) {
            $job = $db->get_job($job_id);
            if (!$job || (string) ($job['session_id'] ?? '') !== $session_id) {
                $not_found_count++;
                $jobs[] = array(
                    'job_id' => $job_id,
                    'status' => 'not_found',
                    'error_message' => 'Job not found for this session.',
                );
                continue;
            }

            $status = sanitize_key((string) ($job['status'] ?? 'queued'));
            $created_at = (string) ($job['created_at'] ?? '');
            $age_seconds = $created_at !== '' ? max(0, time() - strtotime($created_at)) : 0;

            // Nudge queued jobs in case cron scheduling missed them on Local.
            if ($status === 'queued' && $age_seconds >= 3) {
                $this->process_job_async($job_id);
                $job = $db->get_job($job_id) ?: $job;
                $status = sanitize_key((string) ($job['status'] ?? $status));
            }

            if (in_array($status, array('queued', 'running', 'processing'), true)) {
                $active_count++;
            }
            if ($status === 'failed') {
                $failed_count++;
            }

            $jobs[] = array(
                'job_id' => $job_id,
                'status' => $status,
                'error_message' => (string) ($job['error_message'] ?? ''),
                'created_at' => (string) ($job['created_at'] ?? ''),
                'finished_at' => (string) ($job['finished_at'] ?? ''),
            );
        }

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'jobs' => $jobs,
            'all_complete' => $active_count === 0,
            'active_count' => $active_count,
            'failed_count' => $failed_count,
            'not_found_count' => $not_found_count,
        ), 200);
    }

    private function sync_planner_queue_linked_job_statuses() {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'planner_task_queue';
        $jobs_table = $wpdb->prefix . 'ai_jobs';
        $rows = $wpdb->get_results(
            "SELECT id, linked_job_id, status FROM {$queue_table} WHERE linked_job_id IS NOT NULL AND linked_job_id != '' AND status IN ('running', 'dispatched')",
            ARRAY_A
        );

        if (!is_array($rows) || empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $job_id = (string) ($row['linked_job_id'] ?? '');
            if ($job_id === '') {
                continue;
            }
            $job = $wpdb->get_row(
                $wpdb->prepare("SELECT status, error_message, idempotency_key, created_at FROM {$jobs_table} WHERE id = %s", $job_id)
            );
        }
    }



























































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































       
