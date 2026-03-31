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

        register_rest_route('dual-gpt/v1', '/planner/run-author', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_planner_author'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

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
        $author_api = new Dual_GPT_Author_Agent_API();
        $author_api->register_routes();
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
            $category_policy = is_array($category['research_policy'] ?? null) ? $category['research_policy'] : array();
            $policy_override = is_array($meta_input['research_policy'] ?? null) ? $meta_input['research_policy'] : array();
            $meta['research_policy'] = $this->merge_research_policy($category_policy, $policy_override);
        }

        $meta = $this->ensure_research_policy_in_meta($meta);
        $meta = $this->ensure_author_policy_in_meta($meta);
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
        $phase2_context = $meta['phases']['phase2']['payload'] ?? array();

        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
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
        $prompt = $orchestrator->build_phase3_prompt(
            $topic,
            $includes,
            $excludes,
            $phase3_summary,
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
                $wpdb->prepare("SELECT status, error_message, idempotency_key, created_at FROM {$jobs_table} WHERE id = %s", $job_id),
                ARRAY_A
            );
            if (!$job) {
                continue;
            }

            $job_status = (string) ($job['status'] ?? '');
            $job_idempotency = (string) ($job['idempotency_key'] ?? '');
            $job_created_at = (string) ($job['created_at'] ?? '');

            $is_planner_framework_job = strpos($job_idempotency, 'planner-framework-') === 0 || strpos($job_idempotency, 'planner-fw-') === 0;
            if ($job_status === 'running' && $is_planner_framework_job && $job_created_at !== '') {
                $job_age_seconds = time() - strtotime($job_created_at);
                if ($job_age_seconds > 120) {
                    $timeout_message = 'Framework job timed out. Please retry.';
                    error_log('[PLANNER][JOB] Auto-timeout for framework job ' . $job_id . ' after ' . $job_age_seconds . 's');
                    $wpdb->update(
                        $jobs_table,
                        array(
                            'status' => 'failed',
                            'error_message' => $timeout_message,
                            'finished_at' => current_time('mysql'),
                        ),
                        array('id' => $job_id),
                        array('%s', '%s', '%s'),
                        array('%s')
                    );
                    $job_status = 'failed';
                    $job['error_message'] = $timeout_message;
                }
            }

            $next_status = null;
            if ($job_status === 'completed') {
                $next_status = 'completed';
            } elseif ($job_status === 'failed') {
                $next_status = 'failed';
            } elseif (in_array($job_status, array('queued', 'running'), true)) {
                $next_status = 'dispatched';
            }

            if ($next_status && $next_status !== ($row['status'] ?? '')) {
                $wpdb->update(
                    $queue_table,
                    array(
                        'status' => $next_status,
                        'error_message' => $next_status === 'failed' ? (string) ($job['error_message'] ?? '') : null,
                        'updated_at' => current_time('mysql'),
                    ),
                    array('id' => $row['id']),
                    array('%s', '%s', '%s'),
                    array('%s')
                );
            }
        }
    }

    public function add_planner_queue_item($request) {
        global $wpdb;

        $session_id = sanitize_text_field($request->get_param('session_id'));
        $article_id = sanitize_text_field($request->get_param('article_id'));
        $task_type = sanitize_key($request->get_param('task_type'));
        $payload = $request->get_param('payload');

        if (empty($session_id) || empty($task_type)) {
            return new WP_Error('missing_params', 'Session ID and task type are required.', array('status' => 400));
        }

        if (!in_array($task_type, array('dive_deeper', 'framework_generation', 'article_creation'), true)) {
            return new WP_Error('invalid_task_type', 'Unsupported task type.', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }
        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $table = $wpdb->prefix . 'planner_task_queue';
        $next_position = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(position), 0) + 1 FROM {$table} WHERE session_id = %s",
                $session_id
            )
        ));
        if ($next_position < 1) {
            $next_position = 1;
        }

        $queue_id = wp_generate_uuid4();
        $inserted = $wpdb->insert(
            $table,
            array(
                'id' => $queue_id,
                'session_id' => $session_id,
                'article_id' => $article_id ?: null,
                'task_type' => $task_type,
                'status' => 'queued',
                'payload_json' => is_array($payload) ? wp_json_encode($payload) : null,
                'position' => $next_position,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('queue_insert_failed', 'Failed to add item to planner queue.', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'queue_id' => $queue_id,
            'status' => 'queued',
        ), 200);
    }

    public function run_planner_queue_item($request) {
        $queue_id = sanitize_text_field($request->get_param('queue_id'));
        $retry_failed = (bool) $request->get_param('retry_failed');
        $retry_payload = $request->get_param('retry_payload');
        if (!is_array($retry_payload)) {
            $retry_payload = array();
        }
        if (empty($queue_id)) {
            return new WP_Error('missing_queue_id', 'Queue item ID is required.', array('status' => 400));
        }

        return $this->execute_planner_queue_item($queue_id, $retry_failed, $retry_payload);
    }

    public function run_planner_queue_bulk($request) {
        global $wpdb;

        $queue_ids = $request->get_param('queue_ids');
        $run_all_queued = (bool) $request->get_param('run_all_queued');
        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        $table = $wpdb->prefix . 'planner_task_queue';

        if ($run_all_queued) {
            if ($session_id === '') {
                return new WP_Error('missing_session_id', 'Session ID is required to run all queued items.', array('status' => 400));
            }
            $queue_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE session_id = %s AND status = 'queued' ORDER BY position ASC, created_at ASC",
                    $session_id
                )
            );
        }

        if (!is_array($queue_ids) || empty($queue_ids)) {
            return new WP_Error('missing_queue_ids', 'At least one queued item is required.', array('status' => 400));
        }

        $results = array();
        $started = 0;
        $failed = 0;
        foreach ($queue_ids as $queue_id) {
            $queue_id = sanitize_text_field((string) $queue_id);
            if ($queue_id === '') {
                continue;
            }
            $result = $this->execute_planner_queue_item($queue_id);
            if (is_wp_error($result)) {
                $failed++;
                $results[] = array(
                    'queue_id' => $queue_id,
                    'status' => 'failed',
                    'error' => $result->get_error_message(),
                );
                continue;
            }

            $data = $result instanceof WP_REST_Response ? $result->get_data() : (array) $result;
            $started++;
            $results[] = array(
                'queue_id' => $queue_id,
                'status' => (string) ($data['status'] ?? 'completed'),
                'job_id' => (string) ($data['job_id'] ?? ''),
            );
        }

        return new WP_REST_Response(array(
            'started' => $started,
            'failed' => $failed,
            'results' => $results,
        ), 200);
    }

    public function reorder_planner_queue($request) {
        global $wpdb;

        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        $ordered_ids = $request->get_param('ordered_ids');
        if ($session_id === '' || !is_array($ordered_ids) || empty($ordered_ids)) {
            return new WP_Error('missing_params', 'Session ID and ordered queue IDs are required.', array('status' => 400));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }
        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $table = $wpdb->prefix . 'planner_task_queue';
        $valid_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE session_id = %s",
                $session_id
            )
        );
        $valid_lookup = array_fill_keys(array_map('strval', $valid_ids ?: array()), true);

        $position = 1;
        foreach ($ordered_ids as $queue_id) {
            $queue_id = sanitize_text_field((string) $queue_id);
            if ($queue_id === '' || !isset($valid_lookup[$queue_id])) {
                continue;
            }
            $wpdb->update(
                $table,
                array(
                    'position' => $position,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $queue_id),
                array('%d', '%s'),
                array('%s')
            );
            $position++;
            unset($valid_lookup[$queue_id]);
        }

        if (!empty($valid_lookup)) {
            foreach (array_keys($valid_lookup) as $queue_id) {
                $wpdb->update(
                    $table,
                    array(
                        'position' => $position,
                        'updated_at' => current_time('mysql'),
                    ),
                    array('id' => $queue_id),
                    array('%d', '%s'),
                    array('%s')
                );
                $position++;
            }
        }

        return new WP_REST_Response(array(
            'reordered' => true,
        ), 200);
    }

    private function execute_planner_queue_item($queue_id, $retry_failed = false, $retry_payload = array()) {
        global $wpdb;

        $table = $wpdb->prefix . 'planner_task_queue';
        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $queue_id),
            ARRAY_A
        );
        if (!$item) {
            return new WP_Error('queue_item_not_found', 'Queue item not found.', array('status' => 404));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($item['session_id']);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }
        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $current_status = (string) ($item['status'] ?? '');
        if ($current_status !== 'queued' && !($retry_failed && $current_status === 'failed')) {
            return new WP_Error('queue_item_not_queued', 'Only queued items can be started.', array('status' => 400));
        }

        $wpdb->update(
            $table,
            array(
                'status' => 'running',
                'error_message' => null,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $queue_id),
            array('%s', '%s', '%s'),
            array('%s')
        );

        $payload = !empty($item['payload_json']) ? json_decode($item['payload_json'], true) : array();
        if (!is_array($payload)) {
            $payload = array();
        }
        if (!empty($retry_payload)) {
            $payload = array_merge($payload, $retry_payload);
            $wpdb->update(
                $table,
                array(
                    'payload_json' => wp_json_encode($payload),
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $queue_id),
                array('%s', '%s'),
                array('%s')
            );
        }

        $dispatch = $this->dispatch_planner_queue_task($item, $payload);
        if (is_wp_error($dispatch)) {
            $error_message = $this->normalize_queue_error_message($dispatch);
            $wpdb->update(
                $table,
                array(
                    'status' => 'failed',
                    'error_message' => $error_message,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $queue_id),
                array('%s', '%s', '%s'),
                array('%s')
            );
            return $dispatch;
        }

        $linked_job_id = sanitize_text_field((string) ($dispatch['job_id'] ?? ''));
        $wpdb->update(
            $table,
            array(
                'status' => 'dispatched',
                'linked_job_id' => $linked_job_id,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $queue_id),
            array('%s', '%s', '%s'),
            array('%s')
        );

        return new WP_REST_Response(array(
            'queue_id' => $queue_id,
            'status' => 'dispatched',
            'job_id' => $linked_job_id,
        ), 200);
    }

    private function normalize_queue_error_message($error) {
        if (!is_wp_error($error)) {
            return 'Queue task failed.';
        }
        $code = (string) $error->get_error_code();
        $message = (string) $error->get_error_message();

        if ($code === 'prompt_too_long' || stripos($message, 'Prompt exceeds maximum length') !== false) {
            return 'Prompt too long.';
        }
        if ($code === 'budget_exceeded' || stripos($message, 'Token budget exceeded') !== false) {
            return 'Token limit reached - speak to admin.';
        }
        if ($code === 'rate_limited') {
            return 'Rate limited. Please retry shortly.';
        }
        if ($message === '') {
            return 'Queue task failed.';
        }

        return $message;
    }

    public function remove_planner_queue_item($request) {
        global $wpdb;

        $queue_id = sanitize_text_field((string) $request->get_param('queue_id'));
        if ($queue_id === '') {
            return new WP_Error('missing_queue_id', 'Queue item ID is required.', array('status' => 400));
        }

        $table = $wpdb->prefix . 'planner_task_queue';
        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $queue_id),
            ARRAY_A
        );
        if (!$item) {
            return new WP_Error('queue_item_not_found', 'Queue item not found.', array('status' => 404));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($item['session_id']);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }
        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        if (in_array((string) ($item['status'] ?? ''), array('running', 'dispatched'), true)) {
            return new WP_Error('queue_item_locked', 'This queue item has already been dispatched and cannot be removed.', array('status' => 400));
        }

        $deleted = $wpdb->delete($table, array('id' => $queue_id), array('%s'));
        if ($deleted === false) {
            return new WP_Error('queue_remove_failed', 'Failed to remove queue item.', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'removed' => $deleted > 0,
            'queue_id' => $queue_id,
        ), 200);
    }

    public function stop_planner_queue_item($request) {
        global $wpdb;

        $queue_id = sanitize_text_field((string) $request->get_param('queue_id'));
        $reason = sanitize_text_field((string) $request->get_param('reason'));
        if ($queue_id === '') {
            return new WP_Error('missing_queue_id', 'Queue item ID is required.', array('status' => 400));
        }
        if ($reason === '') {
            $reason = 'Stopped by operator (manual cancel).';
        }

        $table = $wpdb->prefix . 'planner_task_queue';
        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $queue_id),
            ARRAY_A
        );
        if (!$item) {
            return new WP_Error('queue_item_not_found', 'Queue item not found.', array('status' => 404));
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($item['session_id']);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }
        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $current_status = sanitize_key((string) ($item['status'] ?? ''));
        if (in_array($current_status, array('completed', 'failed'), true)) {
            return new WP_Error('queue_item_not_stoppable', 'Only queued, running, or dispatched items can be stopped.', array('status' => 400));
        }

        $linked_job_id = sanitize_text_field((string) ($item['linked_job_id'] ?? ''));
        if ($linked_job_id !== '') {
            wp_clear_scheduled_hook('dual_gpt_process_job', array($linked_job_id));
            $job = $db->get_job($linked_job_id);
            $job_status = sanitize_key((string) ($job['status'] ?? ''));
            if (in_array($job_status, array('queued', 'running'), true)) {
                $db->update_job_status($linked_job_id, 'failed', array(
                    'error_message' => $reason,
                ));
            }
        }

        $updated = $wpdb->update(
            $table,
            array(
                'status' => 'failed',
                'error_message' => $reason,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $queue_id),
            array('%s', '%s', '%s'),
            array('%s')
        );

        if ($updated === false) {
            return new WP_Error('queue_stop_failed', 'Failed to stop queue item.', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'stopped' => true,
            'queue_id' => $queue_id,
            'previous_status' => $current_status,
            'linked_job_id' => $linked_job_id,
        ), 200);
    }

    private function dispatch_planner_queue_task($item, $payload) {
        $task_type = sanitize_key($item['task_type'] ?? '');
        $session_id = sanitize_text_field($item['session_id'] ?? '');
        $article_id = sanitize_text_field($item['article_id'] ?? '');

        if ($task_type === 'dive_deeper') {
            $request = new WP_REST_Request('POST');
            $request->set_param('session_id', $session_id);
            $request->set_param('article_id', $article_id);
            $request->set_param('action', 'dive_deeper');
            $request->set_param('params', is_array($payload) ? $payload : array());
            $response = $this->planner_article_action($request);
            return $this->extract_job_id_from_rest_result($response);
        }

        if ($task_type === 'framework_generation') {
            $request = new WP_REST_Request('POST');
            $request->set_param('session_id', $session_id);
            $request->set_param('article_id', $article_id);
            $request->set_param('force', !empty($payload['force']));
            $response = $this->run_planner_framework($request);
            return $this->extract_job_id_from_rest_result($response);
        }

        if ($task_type === 'article_creation') {
            $request = new WP_REST_Request('POST');
            $request->set_param('session_id', $session_id);
            $request->set_param('article_id', $article_id);
            $request->set_param('author_profile', sanitize_key((string) ($payload['author_profile'] ?? '')));
            $request->set_param('retry_compact_prompt', !empty($payload['retry_compact_prompt']));
            $response = $this->run_planner_author($request);
            return $this->extract_job_id_from_rest_result($response);
        }

        return new WP_Error('unsupported_queue_task', 'Unsupported queued task type.', array('status' => 400));
    }

    private function extract_job_id_from_rest_result($response) {
        if (is_wp_error($response)) {
            return $response;
        }

        if ($response instanceof WP_REST_Response) {
            $data = $response->get_data();
        } elseif (is_array($response)) {
            $data = $response;
        } else {
            $data = array();
        }

        $job_id = sanitize_text_field((string) ($data['job_id'] ?? ''));
        if ($job_id === '') {
            return new WP_Error('missing_job_id', 'Task was dispatched but no job ID was returned.', array('status' => 500));
        }

        return array('job_id' => $job_id);
    }

    public function clear_planner_queue($request) {
        global $wpdb;

        $table = $wpdb->prefix . 'planner_task_queue';
        $params = $request->get_params();
        $older_than_seconds = max(0, intval($params['older_than_seconds'] ?? 0));

        if ($older_than_seconds > 0) {
            $cutoff = gmdate('Y-m-d H:i:s', time() - $older_than_seconds);
            $queued_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE status = 'queued' AND created_at <= %s",
                    $cutoff
                )
            );
        } else {
            $queued_ids = $wpdb->get_col("SELECT id FROM {$table} WHERE status = 'queued'");
        }

        if (!is_array($queued_ids) || empty($queued_ids)) {
            return new WP_REST_Response(array(
                'cleared' => 0,
                'message' => 'No queued jobs found.',
            ), 200);
        }

        $cleared = 0;
        foreach ($queued_ids as $job_id) {
            $updated = $wpdb->update(
                $table,
                array(
                    'status' => 'failed',
                    'error_message' => 'Job cleared manually from planner queue.',
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $job_id),
                array('%s', '%s', '%s'),
                array('%s')
            );

            if ($updated !== false && intval($updated) > 0) {
                $cleared++;
            }

        }

        return new WP_REST_Response(array(
            'cleared' => $cleared,
            'message' => sprintf('Cleared %d queued job(s).', $cleared),
        ), 200);
    }

    public function remove_all_planner_queue_items($request) {
        global $wpdb;

        $table = $wpdb->prefix . 'planner_task_queue';

        $ids = $wpdb->get_col(
            "SELECT id FROM {$table} WHERE status NOT IN ('running', 'dispatched')"
        );

        if (empty($ids)) {
            return new WP_REST_Response(array(
                'removed' => 0,
                'message' => 'No removable queue items found.',
            ), 200);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%s'));
        $deleted = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids)
        );

        return new WP_REST_Response(array(
            'removed' => intval($deleted),
            'message' => sprintf('Removed %d queue item(s).', intval($deleted)),
        ), 200);
    }

    /**
     * Export Phase 4 validation output as HTML for download/print
     */
    public function export_planner_validation($request) {
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
        $phase4_payload = $meta['phases']['phase4']['payload'] ?? array();
        if (empty($phase4_payload)) {
            return new WP_Error('phase4_missing', 'Phase 4 validation output is required', array('status' => 400));
        }

        $title = $meta['topic'] ?? $session['title'] ?? 'Editorial Validation';
        $html = $this->build_validation_export_html($title, $phase4_payload);
        $filename = 'validation-' . sanitize_title($title) . '-' . date('Y-m-d-H-i-s') . '.html';

        return new WP_REST_Response(array(
            'filename' => $filename,
            'html' => $html,
        ), 200);
    }

    public function export_planner_synopses($request) {
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
        $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
        if (empty($articles)) {
            return new WP_Error('synopses_missing', 'No article synopses found for export.', array('status' => 400));
        }

        $title = $meta['topic'] ?? $session['title'] ?? 'Editorial Synopses';
        $html = $this->build_synopses_export_html($title, $articles);
        $filename = 'synopses-' . sanitize_title($title) . '-' . date('Y-m-d-H-i-s') . '.html';

        return new WP_REST_Response(array(
            'filename' => $filename,
            'html' => $html,
        ), 200);
    }

    public function export_planner_framework($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $article_id = sanitize_text_field($request->get_param('article_id'));

        if (empty($session_id) || empty($article_id)) {
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
        $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
        $article = null;
        foreach ($articles as $item) {
            if (($item['id'] ?? '') === $article_id) {
                $article = $item;
                break;
            }
        }

        if (!$article) {
            return new WP_Error('article_not_found', 'Article not found', array('status' => 404));
        }

        $framework = $article['framework']['output'] ?? null;
        if (empty($framework)) {
            return new WP_Error('framework_missing', 'Framework output is required for export.', array('status' => 400));
        }

        $title = $article['title'] ?? $session['title'] ?? 'Framework';
        $html = $this->build_framework_export_html($title, $article);
        $filename = 'framework-' . sanitize_title($title) . '-' . date('Y-m-d-H-i-s') . '.html';

        return new WP_REST_Response(array(
            'filename' => $filename,
            'html' => $html,
        ), 200);
    }

    public function run_planner_author($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $article_id = sanitize_text_field($request->get_param('article_id'));
        $author_profile = sanitize_key((string) $request->get_param('author_profile'));
        $retry_compact_prompt = (bool) $request->get_param('retry_compact_prompt');
        $soft_prompt_limit = 8000;
        $hard_prompt_limit = 9800;

        error_log('[PLANNER][AUTHOR] Run requested for session ' . $session_id . ' article ' . $article_id);

        if (empty($session_id) || empty($article_id)) {
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
        $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
        $article = null;
        foreach ($articles as $item) {
            if (($item['id'] ?? '') === $article_id) {
                $article = $item;
                break;
            }
        }

        if (!$article) {
            return new WP_Error('article_not_found', 'Article not found', array('status' => 404));
        }

        $framework = $article['framework']['output'] ?? null;
        if (empty($framework)) {
            return new WP_Error('missing_framework', 'Framework output is required before running author.', array('status' => 400));
        }

        $allowed_profiles = array('balanced', 'journalistic', 'analytical', 'executive');
        if (!in_array($author_profile, $allowed_profiles, true)) {
            $author_profile = $this->recommend_author_profile($article, $framework);
        }

        $post_id = $article['author']['post_id'] ?? null;
        if (empty($post_id)) {
            $post_id = $this->create_author_post($session, $article);
        }
        $edit_url = $post_id ? get_edit_post_link($post_id, 'raw') : '';

        $selected_compact_mode = $retry_compact_prompt;
        $prompt = $this->build_author_prompt($article, $framework, $author_profile, $selected_compact_mode);
        $prompt_size = strlen($prompt);

        if (!$selected_compact_mode && $prompt_size > $soft_prompt_limit) {
            error_log('[PLANNER][AUTHOR] Prompt approaching limit. Escalating to compact prompt for session ' . $session_id . ' article ' . $article_id . ' size=' . $prompt_size);
            $selected_compact_mode = true;
            $prompt = $this->build_author_prompt($article, $framework, $author_profile, true);
            $prompt_size = strlen($prompt);
        }

        if ($prompt_size > $hard_prompt_limit) {
            return new WP_Error('prompt_too_long', 'Prompt too long.', array('status' => 400));
        }

        error_log('[PLANNER][AUTHOR] Using prompt mode ' . ($selected_compact_mode ? 'compact' : 'full') . ' for session ' . $session_id . ' article ' . $article_id . ' size=' . $prompt_size);

        $idempotency = 'planner-author-' . substr(md5($session_id), 0, 8) . '-' . substr(md5($article_id), 0, 8) . '-' . time();
        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $job_id = $orchestrator->run_job($session_id, $idempotency, $prompt, 'author');
        if (is_wp_error($job_id) && (string) $job_id->get_error_code() === 'prompt_too_long' && !$selected_compact_mode) {
            error_log('[PLANNER][AUTHOR] Prompt too long, retrying compact prompt for session ' . $session_id . ' article ' . $article_id);
            $compact_prompt = $this->build_author_prompt($article, $framework, $author_profile, true);
            if (strlen($compact_prompt) > $hard_prompt_limit) {
                return new WP_Error('prompt_too_long', 'Prompt too long.', array('status' => 400));
            }
            $compact_idempotency = $idempotency . '-c';
            $job_id = $orchestrator->run_job($session_id, $compact_idempotency, $compact_prompt, 'author');
            if (!is_wp_error($job_id)) {
                $selected_compact_mode = true;
                $prompt_size = strlen($compact_prompt);
            }
        }
        if (is_wp_error($job_id)) {
            error_log('[PLANNER][AUTHOR] Run failed for session ' . $session_id . ' article ' . $article_id . ': ' . $job_id->get_error_message());
            return $job_id;
        }

        foreach ($articles as $index => $item) {
            if (($item['id'] ?? '') !== $article_id) {
                continue;
            }
            $articles[$index]['author'] = array(
                'status' => 'running',
                'job_id' => $job_id,
                'started_at' => current_time('mysql'),
                'post_id' => $post_id,
                'edit_url' => $edit_url,
                'profile' => $author_profile,
                'prompt_size' => $prompt_size,
                'compact_tier' => $selected_compact_mode ? 1 : 0,
            );
            break;
        }
        $meta['articles'] = $articles;
        $db->update_session_meta($session_id, $meta);

        error_log('[PLANNER][AUTHOR] Run queued for session ' . $session_id . ' article ' . $article_id . ' job ' . $job_id);

        return new WP_REST_Response(array(
            'job_id' => $job_id,
            'author_profile' => $author_profile,
        ), 200);
    }

    private function build_opinion_lite_framework($article) {
        $headline = trim((string) ($article['title'] ?? $article['headline'] ?? 'Untitled'));
        $summary = trim((string) ($article['summary'] ?? $article['brief'] ?? ''));
        $keywords = isset($article['keywords']) && is_array($article['keywords']) ? array_values(array_filter(array_map('strval', $article['keywords']))) : array();
        $citations = isset($article['citations']) && is_array($article['citations']) ? $article['citations'] : array();

        $reader = 'Operators, engineering leads, and technical decision-makers';
        $use_case = 'Publish an evidence-based opinion article with clear trade-offs and practical implications';
        $overview = $summary;
        if ($overview === '') {
            $overview = 'This opinion examines practical implications, evidence strength, and execution trade-offs for teams working on ' . $headline . '.';
        }
        if (strlen($overview) > 280) {
            $overview = substr($overview, 0, 277);
            $overview = rtrim($overview, " \t\n\r\0\x0B,.;:-") . '...';
        }
        $context = $summary !== ''
            ? $summary
            : ('This opinion addresses the practical implications of ' . $headline . ' for real-world teams.');

        $key_themes = array_slice($keywords, 0, 5);
        if (empty($key_themes)) {
            $key_themes = array('Operational context', 'Evidence quality', 'Decision trade-offs');
        }

        $observations = array();
        foreach (array_slice($citations, 0, 3) as $citation) {
            $source_title = trim((string) ($citation['title'] ?? $citation['apa'] ?? $citation['url'] ?? 'Source'));
            if ($source_title !== '') {
                $observations[] = 'Ground arguments in evidence from: ' . $source_title;
            }
        }
        if (empty($observations)) {
            $observations[] = 'State uncertainty explicitly where source coverage is limited.';
            $observations[] = 'Prioritize operationally testable claims over broad generalizations.';
        }

        return array(
            'title' => $headline,
            'overview' => $overview,
            'context' => $context,
            'application' => array(
                'intended_reader' => $reader,
                'use_case' => $use_case,
            ),
            'observations' => $observations,
            'key_themes' => $key_themes,
            'h2_sections' => array(
                array(
                    'heading' => 'What the Evidence Actually Says',
                    'h3_sections' => array('Source quality and relevance', 'Key findings with constraints'),
                ),
                array(
                    'heading' => 'Where the Trade-offs Sit',
                    'h3_sections' => array('Operational costs and risks', 'Who benefits and who absorbs downside'),
                ),
                array(
                    'heading' => 'A Defensible Opinion and Next Steps',
                    'h3_sections' => array('Clear position statement', 'Practical actions for teams'),
                ),
            ),
            'is_lite_framework' => true,
            'lite_mode' => 'opinion',
        );
    }

    /**
     * Generate article synopses from Phase 4 validation output
     */
    public function generate_planner_synopses($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $plan = $request->get_param('plan');
        $batch_size = intval($request->get_param('batch_size')) ?: 2;

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }
        error_log('[PLANNER][SYNOPSES] Request received for session ' . $session_id);

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            error_log('[PLANNER][SYNOPSES] Session not found: ' . $session_id);
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            error_log('[PLANNER][SYNOPSES] Access denied for session ' . $session_id);
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $phase4_payload = $meta['phases']['phase4']['payload'] ?? ($meta['phase4']['payload'] ?? $meta['phase4'] ?? array());
        if (empty($phase4_payload)) {
            error_log('[PLANNER][SYNOPSES] Phase 4 payload missing for session ' . $session_id);
            return new WP_Error('phase4_missing', 'Phase 4 validation output is required', array('status' => 400));
        }

        if (empty($plan) || !is_array($plan)) {
            $plan = $this->build_synopsis_plan($meta, 20);
            if (is_wp_error($plan)) {
                error_log('[PLANNER][SYNOPSES] Plan build failed for session ' . $session_id . ': ' . $plan->get_error_message());
                return $plan;
            }
        }

        $plan = array_filter($plan, function($count) {
            return intval($count) > 0;
        });

        if (empty($plan)) {
            error_log('[PLANNER][SYNOPSES] Plan empty for session ' . $session_id);
            return new WP_Error('plan_empty', 'Synopsis plan must include at least one topic.', array('status' => 400));
        }

        $meta['synopsis_plan'] = $plan;
        $meta['synopses_batch_mode'] = true;
        $db->update_session_meta($session_id, $meta);

        $batch_size = max(1, $batch_size);
        $batches = $this->build_synopsis_batches($plan, $batch_size);
        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $phase1_payload = $meta['phases']['phase1']['payload'] ?? ($meta['phase1']['payload'] ?? $meta['phase1'] ?? array());
        $phase2_context = $orchestrator->build_phase2_context($meta);
        $phase3_payload = $meta['phases']['phase3']['payload'] ?? ($meta['phase3']['payload'] ?? $meta['phase3'] ?? array());

        $existing_titles = array();
        if (!empty($meta['articles']) && is_array($meta['articles'])) {
            foreach ($meta['articles'] as $article) {
                $title = $article['headline'] ?? $article['title'] ?? '';
                if ($title !== '') {
                    $existing_titles[] = $title;
                }
            }
        }

        $job_ids = array();
        foreach ($batches as $index => $batch_plan) {
            $prompt = $orchestrator->build_synopsis_prompt(
                $meta['topic'] ?? $session['title'] ?? '',
                $phase1_payload,
                $phase2_context,
                $phase3_payload,
                $phase4_payload,
                $batch_plan,
                $existing_titles
            );
            $job_id = $orchestrator->run_job(
                $session_id,
                'planner-synopses-' . $session_id . '-b' . ($index + 1) . '-' . time(),
                $prompt,
                'author'
            );
            if (is_wp_error($job_id)) {
                error_log('[PLANNER][SYNOPSES] Job creation failed for session ' . $session_id . ': ' . $job_id->get_error_message());
                return $job_id;
            }
            $job_ids[] = $job_id;
        }

        $meta['synopsis_batches'] = $batches;
        $meta['synopsis_job_ids'] = $job_ids;
        $db->update_session_meta($session_id, $meta);
        error_log(sprintf(
            '[PLANNER][SYNOPSES] Queued %d batch(es), %d job(s) for session %s.',
            count($batches),
            count($job_ids),
            $session_id
        ));

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'job_ids' => $job_ids,
            'batch_count' => count($batches),
        ), 202);
    }

    /**
     * Re-run planner Phase 2 for a session
     */
    public function rerun_planner_phase2($request) {
        $db = new Dual_GPT_DB_Handler();
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $focus_level = $this->normalize_focus_level($request->get_param('focus_level'));

        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        error_log('[PLANNER][PHASE3] Deep dive rerun requested for session ' . $session_id);

        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
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
        $job_id = $orchestrator->run_job($session_id, 'planner-phase2-' . $session_id . '-' . time(), $prompt, 'author');

        if (is_wp_error($job_id)) {
            error_log('[PLANNER][PHASE3] Deep dive job failed for session ' . $session_id . ': ' . $job_id->get_error_message());
            return $job_id;
        }

        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
        $phases['phase3'] = array(
            'title' => 'Research Phase 3',
            'job_id' => $job_id,
            'status' => 'queued',
        );
        $meta['phases'] = $phases;
        $db->update_session_meta($session_id, $meta);

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'job_id' => $job_id,
        ), 200);
    }

    /**
     * Re-run planner Phase 1 for a session
     */
    public function rerun_planner_phase1($request) {
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
        if ($focus_level !== null) {
            $meta['focus_level'] = $focus_level;
        }
        $topic = $meta['topic'] ?? $session['title'] ?? '';
        $includes = $this->normalize_terms($meta['includes'] ?? array());
        $excludes = $this->normalize_terms($meta['excludes'] ?? array());

        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $phase1_data = $orchestrator->build_phase1_data($topic, $includes, $excludes);
        if (is_wp_error($phase1_data)) {
            return $phase1_data;
        }
        $meta['phase1'] = $phase1_data;
        $prompt = $orchestrator->build_phase1_prompt(
            $topic,
            $includes,
            $excludes,
            wp_json_encode($phase1_data),
            $meta['focus_level'] ?? 50
        );
        $job_id = $orchestrator->run_job($session_id, 'planner-phase1-' . $session_id . '-' . time(), $prompt, 'discovery');

        if (is_wp_error($job_id)) {
            return $job_id;
        }

        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
        $phases['phase1'] = array(
            'title' => 'Research Phase 1',
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
     * Create a new job with enhanced validation
     */
    public function create_job($request) {
        $db = new Dual_GPT_DB_Handler();
        $openai = new Dual_GPT_OpenAI_Connector();

        $params = $request->get_params();

        // Enhanced input validation
        $session_id = sanitize_text_field($params['session_id'] ?? '');
        $prompt = sanitize_textarea_field($params['prompt'] ?? '');
        $model = sanitize_text_field($params['model'] ?? get_option('dual_gpt_default_model', 'gpt-4o-mini'));

        // Validate required fields
        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        if (empty($prompt)) {
            return new WP_Error('missing_prompt', 'Prompt is required', array('status' => 400));
        }

        $idempotency_key = !empty($params['idempotency_key']) ? sanitize_text_field($params['idempotency_key']) : null;
        if ($idempotency_key && strlen($idempotency_key) > 64) {
            $idempotency_key = substr($idempotency_key, 0, 64);
        }
        $user_id = get_current_user_id();

        if (!$this->check_rate_limit('job', $user_id, 15, 60)) {
            return new WP_Error(
                'rate_limited',
                'Too many job requests. Please slow down and retry shortly.',
                array('status' => 429)
            );
        }

        // Enhanced prompt validation
        if (strlen($prompt) > 10000) {
            return new WP_Error('prompt_too_long', 'Prompt too long.', array('status' => 400));
        }

        if (strlen($prompt) < 10) {
            return new WP_Error('prompt_too_short', 'Prompt must be at least 10 characters long', array('status' => 400));
        }

        // Validate model
        $allowed_models = array(
            'gpt-5.2',
            'gpt-5',
            'gpt-4.1',
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
        );
        if (!in_array($model, $allowed_models)) {
            return new WP_Error('invalid_model', 'Invalid model specified. Allowed models: ' . implode(', ', $allowed_models), array('status' => 400));
        }

        // Validate session exists and is accessible
        $session = $db->get_session($session_id);
        if (!$session) {
            error_log('[PLANNER][JOB] create_job invalid_session for session_id=' . $session_id);
            return new WP_Error('invalid_session', 'Session not found', array('status' => 404));
        }

        // Check if user owns the session or has permission to access it
        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            error_log('[PLANNER][JOB] create_job access_denied for session_id=' . $session_id . ' user_id=' . get_current_user_id());
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        // Check budget with more detailed response
        $user_id = get_current_user_id();
        $budget = $db->check_user_budget($user_id);
        if ($budget['token_used'] >= $budget['token_limit']) {
            error_log('[PLANNER][JOB] create_job budget_exceeded for user_id=' . $user_id . ' used=' . intval($budget['token_used']) . ' limit=' . intval($budget['token_limit']));
            return new WP_Error('budget_exceeded', 'Token limit reached - speak to admin.', array('status' => 429));
        }

        // Validate API key with better error message
        if (!$openai->validate_api_key()) {
            error_log('[PLANNER][JOB] create_job invalid_api_key for session_id=' . $session_id);
            return new WP_Error('invalid_api_key',
                'OpenAI API key is not configured or invalid. Please check your settings.',
                array('status' => 500)
            );
        }

        $job_data = array(
            'session_id' => $session_id,
            'model' => $model,
            'input_prompt' => $prompt,
            'idempotency_key' => $idempotency_key,
        );

        if ($idempotency_key) {
            $existing_job = $db->get_job_by_idempotency($session_id, $idempotency_key);
            if ($existing_job) {
                $existing_status = sanitize_key((string) ($existing_job['status'] ?? ''));
                $existing_created = strtotime((string) ($existing_job['created_at'] ?? ''));
                $existing_age_seconds = $existing_created ? max(0, time() - $existing_created) : PHP_INT_MAX;

                $is_active = in_array($existing_status, array('queued', 'running', 'processing'), true);
                $is_fresh_active = $is_active && $existing_age_seconds <= 120;

                if ($is_fresh_active) {
                    if ($existing_status === 'queued') {
                        $this->process_job_async($existing_job['id']);
                        error_log('[PLANNER][JOB] nudged existing queued idempotent job ' . $existing_job['id']);
                    }

                    return new WP_REST_Response(array(
                        'job_id' => $existing_job['id'],
                        'status' => $existing_job['status'],
                        'message' => 'Existing active job returned via idempotency key',
                        'idempotent' => true,
                    ), 200);
                }

                $idempotency_key = substr($idempotency_key . '-r' . time(), 0, 64);
                error_log(sprintf(
                    '[PLANNER][JOB] stale idempotent job bypassed old_job=%s status=%s age=%ds new_key=%s',
                    (string) ($existing_job['id'] ?? ''),
                    $existing_status,
                    (int) $existing_age_seconds,
                    $idempotency_key
                ));

                $job_data['idempotency_key'] = $idempotency_key;
            }
        }

        $job_id = $db->insert_job($job_data);

        if (is_wp_error($job_id)) {
            $this->log_error('Failed to create job in database', array(
                'session_id' => $session_id,
                'error' => $job_id->get_error_message()
            ));
            error_log('[PLANNER][JOB] create_job db_insert_error for session_id=' . $session_id . ': ' . $job_id->get_error_message());
            return new WP_Error('job_creation_failed',
                'Failed to create job: ' . $job_id->get_error_message(),
                array('status' => 500)
            );
        }

        // Start job processing asynchronously
        $this->process_job_async($job_id);

        return new WP_REST_Response(array(
            'job_id' => $job_id,
            'status' => 'queued',
            'message' => 'Job created and queued for processing',
            'estimated_tokens' => $this->estimate_token_usage($prompt, $model),
        ), 201);
    }

    /**
     * Stream job results
     */
    public function stream_job($request) {
        $job_id = $request->get_param('id');

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Stream job updates
        $this->stream_job_updates($job_id);

        exit;
    }

    /**
     * Get presets
     */
    public function get_presets($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();
        $role = !empty($params['role']) ? sanitize_text_field($params['role']) : null;

        $presets = $db->get_presets($role);

        return new WP_REST_Response($presets, 200);
    }

    /**
     * Create preset
     */
    public function create_preset($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();

        // Validate required fields
        $required = array('name', 'role', 'system_prompt');
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return new WP_Error('missing_field', "Required field: $field", array('status' => 400));
            }
        }

        $preset_data = array(
            'name' => sanitize_text_field($params['name']),
            'role' => sanitize_text_field($params['role']),
            'system_prompt' => sanitize_textarea_field($params['system_prompt']),
            'default_model' => sanitize_text_field($params['default_model'] ?? 'gpt-4'),
            'params_json' => !empty($params['params']) ? wp_json_encode($params['params']) : null,
            'tool_whitelist' => !empty($params['tool_whitelist']) ? wp_json_encode($params['tool_whitelist']) : null,
        );

        $preset_id = $db->insert_preset($preset_data);

        if (is_wp_error($preset_id)) {
            return $preset_id;
        }

        $preset_data['id'] = $preset_id;
        return new WP_REST_Response($preset_data, 201);
    }

    /**
     * Update preset
     */
    public function update_preset($request) {
        $db = new Dual_GPT_DB_Handler();
        $preset_id = $request->get_param('id');

        $params = $request->get_params();

        // Check if preset exists and is not locked
        $existing_preset = $db->get_preset($preset_id);
        if (!$existing_preset) {
            return new WP_Error('preset_not_found', 'Preset not found', array('status' => 404));
        }

        if ($existing_preset['is_locked']) {
            return new WP_Error('preset_locked', 'Cannot modify locked preset', array('status' => 403));
        }

        $update_data = array();

        if (isset($params['name'])) {
            $update_data['name'] = sanitize_text_field($params['name']);
        }
        if (isset($params['system_prompt'])) {
            $update_data['system_prompt'] = sanitize_textarea_field($params['system_prompt']);
        }
        if (isset($params['default_model'])) {
            $update_data['default_model'] = sanitize_text_field($params['default_model']);
        }
        if (isset($params['params'])) {
            $update_data['params_json'] = wp_json_encode($params['params']);
        }
        if (isset($params['tool_whitelist'])) {
            $update_data['tool_whitelist'] = wp_json_encode($params['tool_whitelist']);
        }

        $success = $db->update_preset($preset_id, $update_data);

        if (!$success) {
            return new WP_Error('update_failed', 'Failed to update preset', array('status' => 500));
        }

        $updated_preset = $db->get_preset($preset_id);
        return new WP_REST_Response($updated_preset, 200);
    }

    /**
     * Delete preset
     */
    public function delete_preset($request) {
        $db = new Dual_GPT_DB_Handler();
        $preset_id = $request->get_param('id');

        // Check if preset exists and is not locked
        $existing_preset = $db->get_preset($preset_id);
        if (!$existing_preset) {
            return new WP_Error('preset_not_found', 'Preset not found', array('status' => 404));
        }

        if ($existing_preset['is_locked']) {
            return new WP_Error('preset_locked', 'Cannot delete locked preset', array('status' => 403));
        }

        $success = $db->delete_preset($preset_id);

        if (!$success) {
            return new WP_Error('delete_failed', 'Failed to delete preset', array('status' => 500));
        }

        return new WP_REST_Response(array('message' => 'Preset deleted'), 200);
    }

    /**
     * Get audit logs
     */
    public function get_audit_logs($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();
        $job_id = !empty($params['job_id']) ? sanitize_text_field($params['job_id']) : null;
        $limit = intval($params['limit'] ?? 50);

        // Would implement audit log retrieval
        // For now, return empty array
        return new WP_REST_Response(array(
            'logs' => array(),
            'total' => 0,
        ), 200);
    }

    /**
     * Get budgets
     */
    public function get_budgets($request) {
        $db = new Dual_GPT_DB_Handler();

        $user_id = get_current_user_id();
        $budget = $db->check_user_budget($user_id);

        return new WP_REST_Response(array(
            'budget' => $budget,
        ), 200);
    }

    /**
     * Update budget
     */
    public function update_budget($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();

        // Validate required fields
        if (!isset($params['scope']) || !isset($params['scope_id']) || !isset($params['token_limit'])) {
            return new WP_Error('missing_fields', 'Required fields: scope, scope_id, token_limit', array('status' => 400));
        }

        $scope = sanitize_text_field($params['scope']);
        $scope_id = sanitize_text_field($params['scope_id']);
        $token_limit = intval($params['token_limit']);

        // For now, we'll update or create a budget record
        // In a real implementation, you'd want more sophisticated budget management
        global $wpdb;
        $table = $wpdb->prefix . 'ai_budgets';

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE scope = %s AND scope_id = %s",
                $scope,
                $scope_id
            )
        );

        if ($existing) {
            $wpdb->update(
                $table,
                array(
                    'token_limit' => $token_limit,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $existing->id)
            );
        } else {
            $wpdb->insert($table, array(
                'scope' => $scope,
                'scope_id' => $scope_id,
                'period' => 'monthly',
                'token_limit' => $token_limit,
                'token_used' => 0,
                'reset_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
            ));
        }

        return new WP_REST_Response(array(
            'message' => 'Budget updated',
            'scope' => $scope,
            'scope_id' => $scope_id,
            'token_limit' => $token_limit,
        ), 200);
    }

    /**
     * Import blocks
     */
    public function import_blocks($request) {
        $params = $request->get_params();
        $blocks_json = $params['blocks_json'] ?? '';
        $post_id = intval($params['post_id'] ?? 0);

        if (empty($blocks_json) || !$post_id) {
            return new WP_Error('missing_data', 'Blocks JSON and post ID required', array('status' => 400));
        }

        $blocks_data = json_decode($blocks_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Invalid blocks JSON', array('status' => 400));
        }

        // Validate and convert blocks
        $gutenberg_blocks = $this->convert_blocks_json_to_gutenberg($blocks_data);

        if (is_wp_error($gutenberg_blocks)) {
            return $gutenberg_blocks;
        }

        // Insert blocks into post
        $this->insert_blocks_into_post($post_id, $gutenberg_blocks);

        return new WP_REST_Response(array(
            'message' => 'Blocks imported successfully',
            'blocks_count' => count($gutenberg_blocks),
        ), 200);
    }

    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_api() {
        check_ajax_referer('dual_gpt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $openai = new Dual_GPT_OpenAI_Connector();

        if (!$openai->validate_api_key()) {
            wp_send_json_error(array('message' => 'Invalid or missing API key'));
        }

        wp_send_json_success(array('message' => 'API key is valid'));
    }

    /**
     * Process job asynchronously
     */
    private function process_job_async($job_id) {
        // Prefer background processing to avoid blocking requests.
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_single_event')) {
            $existing = wp_next_scheduled('dual_gpt_process_job', array($job_id));
            if (!$existing) {
                $scheduled = wp_schedule_single_event(time() + 1, 'dual_gpt_process_job', array($job_id));
                if ($scheduled !== false) {
                    if (function_exists('spawn_cron')) {
                        spawn_cron();
                    }

                    if (!function_exists('wp_doing_cron') || !wp_doing_cron()) {
                        add_action('shutdown', function() use ($job_id) {
                            if (function_exists('fastcgi_finish_request')) {
                                @fastcgi_finish_request();
                            }

                            $db = new Dual_GPT_DB_Handler();
                            $job = $db->get_job($job_id);
                            if (!$job || (($job['status'] ?? '') !== 'queued')) {
                                return;
                            }

                            error_log('[PLANNER][JOB] shutdown fallback processing queued job ' . $job_id);
                            $this->process_job($job_id);
                        }, 99);
                    }

                    return;
                }
            }
        }

        // Fallback to synchronous processing if scheduling fails.
        $this->process_job($job_id);
    }

    /**
     * Process a job with enhanced error handling and recovery
     */
    private function process_job($job_id) {
        $db = new Dual_GPT_DB_Handler();

        $job = $db->get_job($job_id);
        if (!$job) {
            $this->log_error('Job not found', array('job_id' => $job_id));
            return;
        }

        if (($job['status'] ?? '') !== 'queued') {
            return;
        }

        // Author Agent jobs
        if ($this->is_author_agent_job($job)) {
            $this->process_author_agent_job($job);
            return;
        }

        // Special handling for Framework Generator jobs
        if ($this->is_framework_generator_job($job)) {
            $this->process_framework_generator_job($job);
            return;
        }

        $openai = new Dual_GPT_OpenAI_Connector();

        $db->update_job_status($job_id, 'running');

        $retry_count = 0;
        $max_retries = 3;
        $last_error = null;

        while ($retry_count <= $max_retries) {
            try {
                $latest_job = $db->get_job($job_id);
                $latest_status = sanitize_key((string) ($latest_job['status'] ?? ''));
                $latest_error = (string) ($latest_job['error_message'] ?? '');
                if ($latest_status === 'failed' && stripos($latest_error, 'Stopped by operator') !== false) {
                    return;
                }
                if (!in_array($latest_status, array('queued', 'running'), true)) {
                    return;
                }

                // Get session and preset info
                $session = $db->get_session($job['session_id']);
                if (!$session) {
                    throw new Exception('Session not found for job ' . $job_id);
                }

                $preset = $session ? $db->get_preset($session['preset_id']) : null;

                // Validate preset if specified
                if ($session['preset_id'] && !$preset) {
                    throw new Exception('Preset not found: ' . $session['preset_id']);
                }

                // Prepare messages
                $messages = array(
                    array(
                        'role' => 'system',
                        'content' => $preset ? $preset['system_prompt'] : 'You are a helpful assistant.',
                    ),
                    array(
                        'role' => 'user',
                        'content' => $job['input_prompt'],
                    ),
                );

                // Prepare tools based on role
                $tools = array();
                $idempotency_key = (string) ($job['idempotency_key'] ?? '');
                $is_planner_framework_job = strpos($idempotency_key, 'planner-fw-') === 0 || strpos($idempotency_key, 'planner-framework-') === 0;

                if ($is_planner_framework_job) {
                    $tools = array();
                } elseif ($session && $session['role'] === 'research') {
                    $research_tools = new Dual_GPT_Research_Tools();
                    $tools = $research_tools->get_tool_definitions();
                } elseif ($session && $session['role'] === 'author') {
                    $author_tools = new Dual_GPT_Author_Tools();
                    $tools = $author_tools->get_tool_definitions();
                } elseif ($session && $session['role'] === 'seo') {
                    $seo_tools = new Dual_GPT_SEO_Tools();
                    $tools = $seo_tools->get_tool_definitions();
                }

                $planner_request_options = array(
                    'timeout' => 90,
                    'max_retries' => 0,
                    'retry_on_rate_limit' => false,
                );

                // Make OpenAI call with timeout handling
                $start_time = microtime(true);
                $response = $openai->create_chat_completion($messages, $job['model'], $tools, 'auto', $planner_request_options);
                $duration_ms = round((microtime(true) - $start_time) * 1000);

                if (is_wp_error($response)) {
                    $error_code = $response->get_error_code();
                    $error_message = $response->get_error_message();
                    error_log('[PLANNER][JOB] OpenAI call failed for job ' . $job_id . ' in ' . $duration_ms . 'ms: ' . $error_code . ' ' . $error_message);

                    if ($this->is_rate_limited_error($error_code, $error_message)) {
                        throw new Exception('Rate limit exceeded. Please retry shortly.');
                    }

                    // Check if this is a retryable error
                    if ($this->is_retryable_error($error_code) && $retry_count < $max_retries) {
                        $retry_count++;
                        $this->log_warning('Retryable error, attempt ' . $retry_count . '/' . $max_retries, array(
                            'job_id' => $job_id,
                            'error' => $error_message,
                            'retry_count' => $retry_count
                        ));
                        sleep(pow(2, $retry_count)); // Exponential backoff
                        continue;
                    }

                    throw new Exception('OpenAI API error: ' . $error_message);
                }

                error_log('[PLANNER][JOB] OpenAI call completed for job ' . $job_id . ' in ' . $duration_ms . 'ms');

                if (!isset($response['choices']) || empty($response['choices'])) {
                    throw new Exception('Invalid response structure from OpenAI API');
                }

                // Process tool calls if any
                $tool_calls = $response['choices'][0]['message']['tool_calls'] ?? array();
                $final_response = $response;

                if (!empty($tool_calls)) {
                    // Execute tools and continue conversation (support multiple rounds)
                    $tool_rounds = 0;
                    $final_response = $response;
                    while (!empty($tool_calls) && $tool_rounds < 3) {
                        $tool_rounds++;
                        $final_response = $this->process_tool_calls($tool_calls, $messages, $job, $session);
                        if (is_wp_error($final_response)) {
                            throw new Exception($final_response->get_error_message());
                        }
                        $tool_calls = $final_response['choices'][0]['message']['tool_calls'] ?? array();
                    }

                    if (!empty($tool_calls)) {
                        // Force a final response without further tool calls
                        $final_response = $openai->create_chat_completion($messages, $job['model'], array(), 'none', $planner_request_options);
                    }
                }

                // Extract response content
                $response_content = $final_response['choices'][0]['message']['content'] ?? '';

                // Validate response content
                if (empty($response_content) && empty($tool_calls)) {
                    throw new Exception('Empty response from AI model');
                }

                $this->maybe_update_planner_meta($job, $session, $response_content);

                // Calculate costs
                $prompt_tokens = $final_response['usage']['prompt_tokens'] ?? 0;
                $completion_tokens = $final_response['usage']['completion_tokens'] ?? 0;
                $cost_data = $openai->calculate_cost($job['model'], $prompt_tokens, $completion_tokens);

                // Update job with results
                $update_data = array(
                    'response_json' => wp_json_encode($final_response),
                    'usage_prompt_tokens' => $prompt_tokens,
                    'usage_completion_tokens' => $completion_tokens,
                    'cost_micro' => $cost_data['cost_micro'],
                );

                // Try to parse as Blocks JSON
                $blocks_json = $this->extract_blocks_json($response_content);
                if ($blocks_json) {
                    $update_data['output_blocks_json'] = wp_json_encode($blocks_json);
                }

                $db->update_job_status($job_id, 'completed', $update_data);

                // Update user token usage
                $usage_user_id = !empty($job['created_by']) ? intval($job['created_by']) : get_current_user_id();
                $db->update_token_usage($usage_user_id, $prompt_tokens + $completion_tokens);

                // Audit logging
                $db->insert_audit_log($job_id, 'finish', array(
                    'status' => 'completed',
                    'tokens_used' => $prompt_tokens + $completion_tokens,
                    'cost' => $cost_data['cost_usd'],
                    'retries' => $retry_count,
                ));

                // Success - break out of retry loop
                break;

            } catch (Exception $e) {
                $last_error = $e;
                $retry_count++;
                $is_rate_limited = $this->is_rate_limited_error('', $e->getMessage());

                // Log the error with context
                $this->log_error('Job processing error', array(
                    'job_id' => $job_id,
                    'error' => $e->getMessage(),
                    'retry_count' => $retry_count,
                    'max_retries' => $max_retries,
                    'trace' => $e->getTraceAsString()
                ));

                // If we've exhausted retries or it's not a retryable error, fail the job
                if ($retry_count > $max_retries || $is_rate_limited || !$this->is_retryable_error($e->getMessage())) {
                    $db->update_job_status($job_id, 'failed', array(
                        'error_message' => $e->getMessage(),
                        'retry_count' => $retry_count,
                    ));

                    $this->maybe_update_planner_meta_failure($job, $session ?? null, $e->getMessage());

                    $db->insert_audit_log($job_id, 'error', array(
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'retries_attempted' => $retry_count,
                    ));

                    // Send notification if configured
                    $this->send_error_notification($job_id, $e->getMessage());
                    break;
                }

                $latest_job = $db->get_job($job_id);
                $latest_status = sanitize_key((string) ($latest_job['status'] ?? ''));
                $latest_error = (string) ($latest_job['error_message'] ?? '');
                if ($latest_status === 'failed' && stripos($latest_error, 'Stopped by operator') !== false) {
                    return;
                }

                // Wait before retrying
                sleep(min(pow(2, $retry_count), 30)); // Exponential backoff, max 30 seconds
            }
        }
    }

    /**
     * AJAX handler for testing integrations
     */
    public function ajax_test_integrations() {
        check_ajax_referer('dual_gpt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $results = array();
        $provider = get_option('dual_gpt_search_provider', 'none');

        if ($provider === 'serpapi') {
            $key = get_option('dual_gpt_serpapi_key');
            if (empty($key)) {
                wp_send_json_error(array('message' => 'SerpAPI key is missing.'));
            }

            $url = add_query_arg(array(
                'engine' => 'google',
                'q' => 'field service trends',
                'num' => 1,
                'api_key' => $key,
            ), 'https://serpapi.com/search.json');

            $response = wp_remote_get($url, array('timeout' => 20));
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'SerpAPI request failed: ' . $response->get_error_message()));
            }
            $status = wp_remote_retrieve_response_code($response);
            if ($status !== 200) {
                wp_send_json_error(array('message' => 'SerpAPI request failed with status ' . $status));
            }
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array('message' => 'SerpAPI response was invalid JSON.'));
            }
            $results[] = 'SerpAPI: ok';
        } elseif ($provider !== 'none') {
            $results[] = 'Search provider set to ' . $provider . ' (test not implemented).';
        } else {
            $results[] = 'Search provider is disabled.';
        }

        $dataforseo_login = get_option('dual_gpt_dataforseo_login');
        $dataforseo_password = get_option('dual_gpt_dataforseo_password');
        if (!empty($dataforseo_login) && !empty($dataforseo_password)) {
            $keyword_provider = new Dual_GPT_Keyword_Providers();
            $d4s_results = array();

            $test = $keyword_provider->keyword_suggestions('field service', 1);
            $d4s_results[] = is_wp_error($test)
                ? 'suggestions: ' . $test->get_error_message()
                : 'suggestions: ok';

            $test = $keyword_provider->keyword_metrics(array('field service'));
            $d4s_results[] = is_wp_error($test)
                ? 'search_volume: ' . $test->get_error_message()
                : 'search_volume: ok';

            $test = $keyword_provider->keyword_difficulty(array('field service'));
            $d4s_results[] = is_wp_error($test)
                ? 'difficulty: ' . $test->get_error_message()
                : 'difficulty: ok';

            $test = $keyword_provider->keyword_trends(array('field service'));
            $d4s_results[] = is_wp_error($test)
                ? 'trends: ' . $test->get_error_message()
                : 'trends: ok';

            $results[] = 'DataForSEO: ' . implode(', ', $d4s_results);
        } else {
            $results[] = 'DataForSEO: credentials missing.';
        }

        wp_send_json_success(array('message' => implode(' | ', $results)));
    }

    /**
     * Process tool calls
     */
    private function process_tool_calls($tool_calls, &$messages, $job, $session) {
        $openai = new Dual_GPT_OpenAI_Connector();
        $db = new Dual_GPT_DB_Handler();

        // Add assistant's tool call message
        $messages[] = array(
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => $tool_calls,
        );

        // Execute each tool call
        $tool_results = array();
        foreach ($tool_calls as $tool_call) {
            $function_name = $tool_call['function']['name'];
            $function_args = json_decode($tool_call['function']['arguments'], true);

            // Audit tool call
            $db->insert_audit_log($job['id'], 'tool_call', array(
                'tool' => $function_name,
                'args' => $function_args,
            ));

            // Execute tool
            $result = $this->execute_tool($function_name, $function_args, $session);

            $tool_results[] = array(
                'tool_call_id' => $tool_call['id'],
                'content' => wp_json_encode($result),
            );

            // Add tool result message
            $messages[] = array(
                'role' => 'tool',
                'tool_call_id' => $tool_call['id'],
                'content' => wp_json_encode($result),
            );
        }

        // Make final call with tool results
        return $openai->create_chat_completion($messages, $job['model'], array(), 'auto', array(
            'timeout' => 90,
            'max_retries' => 0,
            'retry_on_rate_limit' => false,
        ));
    }

    /**
     * Check if job should run through Author Agent
     */
    private function is_author_agent_job($job) {
        if (!empty($job['idempotency_key']) && strpos($job['idempotency_key'], 'author-') === 0) {
            return true;
        }

        $payload = json_decode($job['input_prompt'] ?? '', true);
        if (is_array($payload) && !empty($payload['author_mode'])) {
            return true;
        }

        return false;
    }

    /**
     * Process Author Agent job
     */
    private function process_author_agent_job($job) {
        $db = new Dual_GPT_DB_Handler();

        $db->update_job_status($job['id'], 'running');

        $payload = json_decode($job['input_prompt'] ?? '', true);
        if (!is_array($payload)) {
            $db->update_job_status($job['id'], 'failed', array('error_message' => 'Invalid author agent payload.'));
            return;
        }

        $agent = new Dual_GPT_Author_Agent();
        $result = $agent->run($payload, $job['created_by'] ?? get_current_user_id());

        if (is_wp_error($result)) {
            $db->update_job_status($job['id'], 'failed', array('error_message' => $result->get_error_message()));
            return;
        }

        $update_data = array(
            'response_json' => wp_json_encode($result),
        );

        if (!empty($result['output']['blocks'])) {
            $update_data['output_blocks_json'] = wp_json_encode(array('blocks' => $result['output']['blocks']));
        }

        $db->update_job_status($job['id'], 'completed', $update_data);

        $db->insert_audit_log($job['id'], 'finish', array(
            'status' => 'completed',
            'mode' => $result['mode'] ?? null,
        ));
    }

    /**
     * Execute a tool
     */
    private function execute_tool($tool_name, $args, $session) {
        if ($session['role'] === 'research') {
            $tools = new Dual_GPT_Research_Tools();
        } elseif ($session['role'] === 'author') {
            $tools = new Dual_GPT_Author_Tools();
        } elseif ($session['role'] === 'seo') {
            $tools = new Dual_GPT_SEO_Tools();
        } else {
            return array('error' => 'Unknown role');
        }

        return $tools->execute_tool($tool_name, $args);
    }

    /**
     * Extract Blocks JSON from response
     */
    private function extract_blocks_json($content) {
        // Try to parse as JSON first
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['blocks'])) {
            return $data;
        }

        // Look for JSON code blocks
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['blocks'])) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Stream job updates
     */
    private function stream_job_updates($job_id) {
        $db = new Dual_GPT_DB_Handler();

        // Send initial connection
        $this->send_sse_event('connected', array('job_id' => $job_id));

        $last_status = '';
        $timeout = 30; // 30 seconds timeout
        $start_time = time();

        while (time() - $start_time < $timeout) {
            $job = $db->get_job($job_id);

            if (!$job) {
                $this->send_sse_event('error', array('message' => 'Job not found'));
                break;
            }

            if ($job['status'] !== $last_status) {
                $this->send_sse_event('status', array(
                    'status' => $job['status'],
                    'job' => $job,
                ));
                $last_status = $job['status'];
            }

            if (in_array($job['status'], array('completed', 'failed'))) {
                break;
            }

            // Sleep for a bit before checking again
            sleep(1);
        }

        $this->send_sse_event('end', array());
    }

    /**
     * Send SSE event
     */
    private function send_sse_event($event, $data) {
        echo "event: $event\n";
        echo "data: " . wp_json_encode($data) . "\n\n";
        flush();
    }

    /**
     * Convert Blocks JSON to Gutenberg blocks
     */
    private function convert_blocks_json_to_gutenberg($blocks_data) {
        if (!isset($blocks_data['blocks']) || !is_array($blocks_data['blocks'])) {
            return new WP_Error('invalid_blocks', 'Invalid blocks structure', array('status' => 400));
        }

        $gutenberg_blocks = array();

        foreach ($blocks_data['blocks'] as $block_data) {
            $block_type = $block_data['type'] ?? 'paragraph';
            $content = $block_data['content'] ?? '';

            switch ($block_type) {
                case 'acf_block':
                    $acf_name = $block_data['name'] ?? '';
                    if ($acf_name === '') {
                        break;
                    }
                    $block_name = 'acf/' . $acf_name;
                    $attrs = array(
                        'name' => $block_name,
                        'data' => $block_data['data'] ?? array(),
                        'mode' => 'edit',
                    );
                    if (!empty($block_data['anchor'])) {
                        $attrs['anchor'] = $block_data['anchor'];
                    }
                    if (!empty($block_data['id'])) {
                        $attrs['id'] = $block_data['id'];
                    }
                    $gutenberg_blocks[] = array(
                        'blockName' => $block_name,
                        'attrs' => $attrs,
                        'innerBlocks' => array(),
                        'innerHTML' => '',
                        'innerContent' => array(),
                    );
                    break;
                case 'heading':
                    $level = $block_data['level'] ?? 2;
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/heading',
                        'attrs' => array(
                            'level' => $level,
                        ),
                        'innerBlocks' => array(),
                        'innerHTML' => "<h$level>$content</h$level>",
                        'innerContent' => array("<h$level>$content</h$level>"),
                    );
                    break;

                case 'paragraph':
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => "<p>$content</p>",
                        'innerContent' => array("<p>$content</p>"),
                    );
                    break;

                case 'list':
                    $ordered = $block_data['ordered'] ?? false;
                    $items = $block_data['items'] ?? array();
                    $list_type = $ordered ? 'ol' : 'ul';
                    $list_items = '';
                    foreach ($items as $item) {
                        $list_items .= "<li>$item</li>";
                    }
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/list',
                        'attrs' => array(
                            'ordered' => $ordered,
                        ),
                        'innerBlocks' => array(),
                        'innerHTML' => "<$list_type>$list_items</$list_type>",
                        'innerContent' => array("<$list_type>$list_items</$list_type>"),
                    );
                    break;

                case 'quote':
                    $cite = $block_data['cite'] ?? '';
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/quote',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => "<blockquote class=\"wp-block-quote\"><p>$content</p><cite>$cite</cite></blockquote>",
                        'innerContent' => array(
                            "<blockquote class=\"wp-block-quote\">",
                            "<p>$content</p>",
                            "<cite>$cite</cite>",
                            "</blockquote>"
                        ),
                    );
                    break;

                case 'pullquote':
                    $cite = $block_data['cite'] ?? '';
                    $meta = $block_data['meta'] ?? array();
                    $data_attrs = '';
                    foreach ($meta as $key => $value) {
                        if ($value === '' || $value === null) {
                            continue;
                        }
                        $data_attrs .= ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
                    }
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/pullquote',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => "<figure class=\"wp-block-pullquote\"$data_attrs><blockquote><p>$content</p><cite>$cite</cite></blockquote></figure>",
                        'innerContent' => array(
                            "<figure class=\"wp-block-pullquote\"$data_attrs><blockquote>",
                            "<p>$content</p>",
                            "<cite>$cite</cite>",
                            "</blockquote></figure>"
                        ),
                    );
                    break;

                case 'separator':
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/separator',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => '<hr class="wp-block-separator has-alpha-channel-opacity"/>',
                        'innerContent' => array('<hr class="wp-block-separator has-alpha-channel-opacity"/>'),
                    );
                    break;

                default:
                    // Default to paragraph for unknown types
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => "<p>$content</p>",
                        'innerContent' => array("<p>$content</p>"),
                    );
                    break;
            }
        }

        return $gutenberg_blocks;
    }

    /**
     * Insert blocks into post
     */
    private function insert_blocks_into_post($post_id, $gutenberg_blocks) {
        // Convert to Gutenberg content
        $content = '';
        foreach ($gutenberg_blocks as $block) {
            $content .= serialize_block($block) . "\n";
        }

        // Update post content
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content,
        ));
    }

    /**
     * Enhanced error logging with context
     */
    private function log_error($message, $context = array()) {
        $log_message = '[Dual-GPT Error] ' . $message;
        $sanitized_context = $this->sanitize_context($context);
        if (!empty($sanitized_context)) {
            $log_message .= ' | Context: ' . wp_json_encode($sanitized_context);
        }
        error_log($log_message);

        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_message);
        }
    }

    /**
     * Enhanced warning logging with context
     */
    private function log_warning($message, $context = array()) {
        $log_message = '[Dual-GPT Warning] ' . $message;
        $sanitized_context = $this->sanitize_context($context);
        if (!empty($sanitized_context)) {
            $log_message .= ' | Context: ' . wp_json_encode($sanitized_context);
        }
        error_log($log_message);
    }

    /**
     * Redact/trim sensitive values before logging
     */
    private function sanitize_context($context) {
        if (empty($context)) {
            return array();
        }

        $redacted_keys = array('input_prompt', 'response_json', 'output_blocks_json', 'tool_calls', 'trace', 'args');
        $sanitized = array();

        foreach ($context as $key => $value) {
            if (in_array($key, $redacted_keys, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_string($value) && strlen($value) > 300) {
                $sanitized[$key] = substr($value, 0, 300) . '...';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if an error is retryable
     */
    private function is_retryable_error($error) {
        $retryable_patterns = array(
            'timeout',
            'rate limit',
            '429',
            '502',
            '503',
            '504',
            'connection',
            'network',
            'temporary',
        );

        $error_lower = strtolower($error);
        foreach ($retryable_patterns as $pattern) {
            if (strpos($error_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_rate_limited_error($error_code, $error_message) {
        $code = strtolower((string) $error_code);
        $message = strtolower((string) $error_message);
        if ($code === 'rate_limited') {
            return true;
        }
        return strpos($message, 'rate limit') !== false || strpos($message, '429') !== false;
    }

    /**
     * Send error notification (placeholder for future implementation)
     */
    private function send_error_notification($job_id, $error_message) {
        // Placeholder for email/Slack notifications
        // In a real implementation, this could send notifications to admins
        $this->log_warning('Error notification triggered', array(
            'job_id' => $job_id,
            'error' => $error_message,
        ));

        // TODO: Implement actual notification system
        // - Email notifications to admins
        // - Slack/Discord webhooks
        // - WordPress admin notices
    }

    /**
     * Estimate token usage for a prompt and model
     */
    private function estimate_token_usage($prompt, $model) {
        // Rough estimation: ~4 characters per token for English text
        $char_count = strlen($prompt);
        $estimated_tokens = ceil($char_count / 4);

        // Add some overhead for system messages and response
        $estimated_tokens += 100;

        // Model-specific adjustments
        switch ($model) {
            case 'gpt-4':
                $estimated_tokens = ceil($estimated_tokens * 1.1); // GPT-4 uses more tokens
                break;
            case 'gpt-4o':
                $estimated_tokens = ceil($estimated_tokens * 0.9); // GPT-4o is more efficient
                break;
            case 'gpt-4o-mini':
                $estimated_tokens = ceil($estimated_tokens * 0.8); // GPT-4o mini is most efficient
                break;
            case 'gpt-4-turbo':
                $estimated_tokens = ceil($estimated_tokens * 0.9); // More efficient
                break;
            case 'gpt-3.5-turbo':
                $estimated_tokens = ceil($estimated_tokens * 0.8); // Most efficient
                break;
        }

        return $estimated_tokens;
    }

    /**
     * Update planner session metadata from completed jobs
     */
    private function maybe_update_planner_meta($job, $session, $response_content) {
        if (empty($response_content)) {
            return;
        }

        $idempotency = $job['idempotency_key'] ?? '';
        $db = new Dual_GPT_DB_Handler();

        if (strpos($idempotency, 'planner-synopses-') === 0) {
            $payload = $this->extract_json_from_content($response_content);
            if (!is_array($payload) || empty($payload['synopses']) || !is_array($payload['synopses'])) {
                $meta = $this->decode_session_meta($session['meta_json'] ?? null);
                $meta['synopses_raw_response'] = substr($response_content ?? '', 0, 5000);
                $db->update_session_meta($session['id'], $meta);
                error_log('[PLANNER][SYNOPSES] Invalid response payload. Raw response stored (first 5000 chars).');
                return;
            }
            $meta = $this->decode_session_meta($session['meta_json'] ?? null);
            $synopses = array();
            foreach ($payload['synopses'] as $synopsis) {
                if (!is_array($synopsis)) {
                    continue;
                }
                $summary = $synopsis['summary'] ?? ($synopsis['summary_two_sentences'] ?? '');
                $synopses[] = array(
                    'id' => $synopsis['id'] ?? wp_generate_uuid4(),
                    'topic' => $synopsis['topic'] ?? '',
                    'title' => $synopsis['headline'] ?? '',
                    'brief' => $summary,
                    'summary' => $summary,
                    'summary_two_sentences' => $synopsis['summary_two_sentences'] ?? '',
                    'key_points' => isset($synopsis['key_points']) && is_array($synopsis['key_points']) ? $synopsis['key_points'] : array(),
                    'keywords' => isset($synopsis['keywords']) && is_array($synopsis['keywords']) ? $synopsis['keywords'] : array(),
                    'citations' => isset($synopsis['citations']) && is_array($synopsis['citations']) ? $synopsis['citations'] : array(),
                    'citation_count' => isset($synopsis['citations']) && is_array($synopsis['citations']) ? count($synopsis['citations']) : 0,
                    'recommended_word_count' => $synopsis['recommended_word_count'] ?? '',
                    'topic_coverage_level' => $synopsis['topic_coverage_level'] ?? '',
                    'audience_segment' => $synopsis['audience_segment'] ?? '',
                    'priority_score' => $synopsis['priority_score'] ?? null,
                    'opening_hook' => $synopsis['opening_hook'] ?? '',
                    'framework' => array(
                        'status' => 'pending',
                    ),
                );
            }
            $target_total = 0;
            if (!empty($meta['synopsis_plan']) && is_array($meta['synopsis_plan'])) {
                $target_total = array_sum(array_map('intval', $meta['synopsis_plan']));
            }

            // Phase 1: Enrich each synopsis with Phase 4 validated citations — zero extra API calls.
            foreach ($synopses as &$synopsis_item) {
                $synopsis_item = $this->enrich_article_citations_from_phase4($synopsis_item, $meta);
            }
            unset($synopsis_item);

            // Log citation quality for monitoring before merge.
            $under_cited = 0;
            foreach ($synopses as $s) {
                if (count($s['citations'] ?? array()) < 4) {
                    $under_cited++;
                }
            }
            if ($under_cited > 0) {
                error_log(sprintf(
                    '[PLANNER][SYNOPSES] Citation quality warning: %d/%d synopses have fewer than 4 citations in batch %s.',
                    $under_cited,
                    count($synopses),
                    $idempotency
                ));
            }

            if (!empty($meta['synopses_batch_mode'])) {
                $meta['articles'] = $this->merge_synopses($meta['articles'] ?? array(), $synopses);
            } else {
                $meta['articles'] = $synopses;
            }
            $final_count = count($meta['articles'] ?? array());
            if ($target_total > 0 && $final_count < $target_total) {
                $meta['synopses_status'] = 'incomplete';
                $meta['synopses_error'] = sprintf(
                    'Generated %d synopses, expected %d from plan.',
                    $final_count,
                    $target_total
                );
                error_log('[PLANNER][SYNOPSES] ' . $meta['synopses_error']);
            } else {
                $meta['synopses_status'] = 'complete';
                $meta['synopses_error'] = '';
            }
            $db->update_session_meta($session['id'], $meta);

            // Phase 3: Silent auto dive-deeper safety net.
            $this->maybe_auto_dive_after_synopses($session, $meta, $db);
            return;
        }

        if (strpos($idempotency, 'planner-framework-') === 0 || strpos($idempotency, 'planner-fw-') === 0) {
            $meta = $this->decode_session_meta($session['meta_json'] ?? null);
            $payload = $this->extract_json_from_content($response_content);
            if (!is_array($payload)) {
                return;
            }

            $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
            foreach ($articles as $index => $article) {
                if (($article['framework']['job_id'] ?? '') !== ($job['id'] ?? '')) {
                    continue;
                }
                $framework = $payload['framework'] ?? array();
                $citations = isset($payload['citations']) && is_array($payload['citations']) ? $payload['citations'] : array();
                $existing_citations = isset($article['citations']) && is_array($article['citations']) ? $article['citations'] : array();
                if (empty($citations) && !empty($existing_citations)) {
                    $citations = $existing_citations;
                }
                if (!empty($citations) && !empty($existing_citations)) {
                    $lookup = array();
                    foreach ($existing_citations as $existing) {
                        if (!is_array($existing)) {
                            continue;
                        }
                        $key = $existing['url'] ?? '';
                        if ($key === '') {
                            $key = $existing['title'] ?? '';
                        }
                        if ($key !== '') {
                            $lookup[$key] = $existing;
                        }
                    }
                    foreach ($citations as $idx => $citation) {
                        if (!is_array($citation)) {
                            continue;
                        }
                        $key = $citation['url'] ?? '';
                        if ($key === '') {
                            $key = $citation['title'] ?? '';
                        }
                        if ($key === '' || empty($lookup[$key])) {
                            continue;
                        }
                        $existing = $lookup[$key];
                        foreach (array('lead_author','additional_authors','publication_date','organisation','source','title','url') as $field) {
                            if (empty($citation[$field]) && !empty($existing[$field])) {
                                $citation[$field] = $existing[$field];
                            }
                        }
                        $citations[$idx] = $citation;
                    }
                }
                $articles[$index]['framework'] = array(
                    'status' => 'complete',
                    'generated_at' => current_time('mysql'),
                    'output' => $framework,
                    'model_used' => $job['model'] ?? '',
                    'non_optimal_model' => class_exists('Dual_GPT_Model_Config')
                        ? (new Dual_GPT_Model_Config())->is_non_optimal('framework', $job['model'] ?? '')
                        : false,
                    'job_id' => $job['id'] ?? '',
                );
                $articles[$index]['citations'] = $citations;
                $articles[$index]['citation_count'] = count($citations);
                break;
            }

            $meta['articles'] = $articles;
            $db->update_session_meta($session['id'], $meta);
            return;
        }

        if (strpos($idempotency, 'planner-phase') === 0) {
            error_log('[PLANNER] Completing phase for job ' . ($job['id'] ?? 'unknown'));
            $meta = $this->decode_session_meta($session['meta_json'] ?? null);

            if (!preg_match('/planner-(phase\\d)-/', $idempotency, $matches)) {
                return;
            }

            $phase_key = $matches[1];
            $storage_key = $phase_key;
            if ($phase_key === 'phase2') {
                $storage_key = 'phase3';
            } elseif ($phase_key === 'phase3') {
                $storage_key = 'phase4';
            }
            $titles = array(
                'phase1' => 'Research Phase 1',
                'phase2' => 'Research Phase 2',
                'phase3' => 'Research Phase 3',
                'phase4' => 'Research Phase 4',
            );

            $payload = $this->extract_json_from_content($response_content);
            $summary = '';
            $citations = array();
            $articles = array();

            if (is_array($payload)) {
                $summary = $payload['summary'] ?? '';
                $citations = isset($payload['citations']) && is_array($payload['citations']) ? $payload['citations'] : array();
                $articles = isset($payload['articles']) && is_array($payload['articles']) ? $payload['articles'] : array();
            } else {
                $summary = trim($response_content);
            }

            if (empty($summary) && is_array($payload)) {
                $summary = $payload['executive_summary']
                    ?? $payload['deep_dive_summary']
                    ?? $payload['article_summary']
                    ?? $payload['validation_summary']
                    ?? $payload['executive_research_summary']
                    ?? '';
            }

            $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
            $phase_data = array(
                'title' => $titles[$storage_key] ?? $storage_key,
                'job_id' => $job['id'],
                'status' => 'completed',
                'summary' => $summary,
                'citations' => $citations,
                'completed_at' => current_time('mysql'),
            );
            if (is_array($payload)) {
                $phase_data['payload'] = $payload;
                $phase_data['validation'] = $this->validate_research_phase_payload($storage_key, $payload, $meta);
            }

            $task_map = array(
                'phase1' => 'discovery',
                'phase2' => 'author',
                'phase3' => 'verify',
            );
            $model_config = class_exists('Dual_GPT_Model_Config') ? new Dual_GPT_Model_Config() : null;
            $task_name = $task_map[$phase_key] ?? '';
            $phase_data['model_used'] = $job['model'] ?? '';
            $phase_data['non_optimal_model'] = $model_config && $task_name
                ? $model_config->is_non_optimal($task_name, $phase_data['model_used'])
                : false;

            if (!empty($articles)) {
                $normalized_articles = array();
                foreach ($articles as $article) {
                    if (!is_array($article)) {
                        continue;
                    }
                    $normalized_articles[] = array(
                        'id' => $article['id'] ?? wp_generate_uuid4(),
                        'title' => $article['title'] ?? $article['headline'] ?? '',
                        'brief' => $article['brief'] ?? $article['summary_two_sentences'] ?? '',
                        'keywords' => isset($article['keywords']) && is_array($article['keywords']) ? $article['keywords'] : array(),
                        'score' => $article['score'] ?? 0,
                        'initial_score' => $article['initial_score'] ?? ($article['score'] ?? 0),
                        'framework' => array(
                            'status' => 'queued',
                        ),
                        'citations' => array(),
                        'citation_count' => 0,
                    );
                }
                $phase_data['articles'] = $normalized_articles;
                $meta['articles'] = $normalized_articles;
            }

            $phases[$storage_key] = array_merge($phases[$storage_key] ?? array(), $phase_data);
            $meta['phases'] = $phases;

            if ($phase_key === 'phase1' && is_array($payload)) {
                $meta['phase1']['summary'] = $payload['executive_summary'] ?? $summary;
                if (!empty($payload['trend_summary'])) {
                    $meta['phase1']['trend_summary'] = $payload['trend_summary'];
                }
                if (!empty($payload['candidate_keywords'])) {
                    $meta['phase1']['candidate_keywords'] = $payload['candidate_keywords'];
                }
            }

            $meta = $this->refresh_research_validation_index($meta);

            $updated = $db->update_session_meta($session['id'], $meta);
            if (!$updated) {
                error_log('[PLANNER] Failed to update session meta for ' . $session['id']);
            }

            if ($phase_key === 'phase1' && !empty($meta['phase1']['candidate_keywords'])) {
                $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
                $effective_focus = $meta['focus_level'] ?? 50;
                $max_keywords = $this->map_focus_to_keyword_limit($effective_focus);
                $phase1_5 = $orchestrator->run_phase1_5($session['id'], $meta['phase1']['candidate_keywords'], $max_keywords);
                if (!is_wp_error($phase1_5)) {
                    $meta['phase2'] = $phase1_5;
                    $phase2_validation = $this->validate_research_phase_payload('phase2', $phase1_5, $meta);
                    $meta['phases']['phase2'] = array(
                        'title' => 'Research Phase 2',
                        'status' => 'completed',
                        'completed_at' => current_time('mysql'),
                        'payload' => $phase1_5,
                        'validation' => $phase2_validation,
                        'summary' => $phase1_5['summary'] ?? '',
                    );
                    $meta = $this->refresh_research_validation_index($meta);
                    $db->update_session_meta($session['id'], $meta);

                    $prompt = $orchestrator->build_phase2_prompt(
                        $meta['topic'] ?? $session['title'] ?? '',
                        $this->normalize_terms($meta['includes'] ?? array()),
                        $this->normalize_terms($meta['excludes'] ?? array()),
                        $meta['phases']['phase1']['summary'] ?? '',
                        $orchestrator->build_phase2_context($meta),
                        $effective_focus
                    );
                    $job_id = $orchestrator->run_job($session['id'], 'planner-phase2-' . $session['id'] . '-' . time(), $prompt, 'author');
                    if (!is_wp_error($job_id)) {
                        $meta['phases']['phase3'] = array(
                            'title' => 'Research Phase 3',
                            'job_id' => $job_id,
                            'status' => 'queued',
                        );
                        $db->update_session_meta($session['id'], $meta);
                    }
                }
            }

            if ($phase_key === 'phase2') {
                // Phase 4 is manual; do not auto-queue validation.
            }

            return;
        }

        $session_meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $planner_session_id = $session_meta['planner_session_id'] ?? '';

        if (!empty($planner_session_id) && ($session['preset_id'] ?? '') === 'fg-framework-generator') {
            $planner_session = $db->get_session($planner_session_id);
            if (!$planner_session) {
                return;
            }

            $planner_meta = $this->decode_session_meta($planner_session['meta_json'] ?? null);
            $frameworks = isset($planner_meta['frameworks']) && is_array($planner_meta['frameworks']) ? $planner_meta['frameworks'] : array();
            $frameworks[] = array(
                'article_title' => $session_meta['article_title'] ?? $session['title'] ?? 'Framework',
                'article_tags' => $session_meta['article_tags'] ?? array(),
                'job_id' => $job['id'],
                'framework_session_id' => $session['id'],
                'output' => $response_content,
                'created_at' => current_time('mysql'),
            );

            $planner_meta['frameworks'] = $frameworks;
            $db->update_session_meta($planner_session_id, $planner_meta);
        }
    }

    private function maybe_update_planner_meta_failure($job, $session, $error_message) {
        if (empty($job)) {
            return;
        }

        $idempotency = $job['idempotency_key'] ?? '';
        if (strpos($idempotency, 'planner-framework-') === 0 || strpos($idempotency, 'planner-fw-') === 0) {
            $db = new Dual_GPT_DB_Handler();
            if (!$session) {
                $session = $db->get_session($job['session_id']);
            }

            if (!$session) {
                return;
            }

            $meta = $this->decode_session_meta($session['meta_json'] ?? null);
            $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
            foreach ($articles as $index => $article) {
                if (($article['framework']['job_id'] ?? '') !== ($job['id'] ?? '')) {
                    continue;
                }
                $articles[$index]['framework'] = array(
                    'status' => 'failed',
                    'error_message' => $error_message,
                    'completed_at' => current_time('mysql'),
                    'job_id' => $job['id'] ?? '',
                );
                break;
            }
            $meta['articles'] = $articles;
            $db->update_session_meta($session['id'], $meta);
            return;
        }

        if (strpos($idempotency, 'planner-phase') !== 0) {
            return;
        }

        $db = new Dual_GPT_DB_Handler();
        if (!$session) {
            $session = $db->get_session($job['session_id']);
        }

        if (!$session) {
            return;
        }

        if (!preg_match('/planner-(phase\\d)-/', $idempotency, $matches)) {
            return;
        }

        $phase_key = $matches[1];
        $titles = array(
            'phase1' => 'Research Phase 1',
            'phase2' => 'Research Phase 2',
            'phase3' => 'Research Phase 3',
            'phase4' => 'Research Phase 4',
        );

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
        $phases[$phase_key] = array_merge($phases[$phase_key] ?? array(), array(
            'title' => $titles[$phase_key] ?? $phase_key,
            'job_id' => $job['id'],
            'status' => 'failed',
            'error_message' => $error_message,
            'completed_at' => current_time('mysql'),
        ));

        $meta['phases'] = $phases;
        $db->update_session_meta($session['id'], $meta);
    }

    private function enqueue_planner_phase($session_id, $topic, $includes, $excludes, $context, $phase_key) {
        $orchestrator = new Dual_GPT_Planner_Orchestrator($this);
        $job_id = $orchestrator->enqueue_phase_job($session_id, $phase_key, $topic, $includes, $excludes, $context);

        if (is_wp_error($job_id)) {
            return;
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return;
        }

        $meta = $this->decode_session_meta($session['meta_json'] ?? null);
        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
        $titles = array(
            'phase1' => 'Research Phase 1',
            'phase2' => 'Research Phase 2',
            'phase3' => 'Research Phase 3',
            'phase4' => 'Research Phase 4',
        );

        $phases[$phase_key] = array_merge($phases[$phase_key] ?? array(), array(
            'title' => $titles[$phase_key] ?? $phase_key,
            'job_id' => $job_id,
            'status' => 'queued',
        ));

        $meta['phases'] = $phases;
        $db->update_session_meta($session_id, $meta);
    }

    private function decode_session_meta($meta_json) {
        if (empty($meta_json)) {
            return array();
        }

        $decoded = json_decode($meta_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return array();
        }

        return $decoded;
    }

    private function extract_json_from_content($content) {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        $first = strpos($trimmed, '{');
        $last = strrpos($trimmed, '}');
        if ($first === false || $last === false || $last <= $first) {
            return null;
        }

        $json = substr($trimmed, $first, $last - $first + 1);
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    private function normalize_terms($terms) {
        if (is_string($terms)) {
            $terms = array_filter(array_map('trim', explode(',', $terms)));
        }

        if (!is_array($terms)) {
            return array();
        }

        return array_values(array_filter(array_map('sanitize_text_field', $terms)));
    }

    private function normalize_focus_level($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $level = intval($value);
        if ($level < 0) {
            return 0;
        }
        if ($level > 100) {
            return 100;
        }
        return $level;
    }

    private function map_focus_to_keyword_limit($focus_level) {
        $focus_level = $this->normalize_focus_level($focus_level);
        if ($focus_level === null) {
            $focus_level = 50;
        }
        $min = 8;
        $max = 20;
        $ratio = 1 - ($focus_level / 100);
        return (int) round($min + (($max - $min) * $ratio));
    }

    private function build_synopsis_plan($meta, $total = 20) {
        $phase4_payload = $meta['phases']['phase4']['payload'] ?? ($meta['phase4']['payload'] ?? $meta['phase4'] ?? array());
        $validated_topics = isset($phase4_payload['validated_topics']) && is_array($phase4_payload['validated_topics'])
            ? $phase4_payload['validated_topics']
            : array();

        if (empty($validated_topics)) {
            return new WP_Error('phase4_topics_missing', 'Validated topics are required to build a synopsis plan.');
        }

        $phase1_payload = $meta['phases']['phase1']['payload'] ?? ($meta['phase1']['payload'] ?? $meta['phase1'] ?? array());
        $phase1_trends = isset($phase1_payload['trends']) && is_array($phase1_payload['trends']) ? $phase1_payload['trends'] : array();
        $phase1_trend_summary = isset($meta['phase1']['trend_summary']) && is_array($meta['phase1']['trend_summary'])
            ? $meta['phase1']['trend_summary']
            : (isset($phase1_payload['trend_summary']) && is_array($phase1_payload['trend_summary']) ? $phase1_payload['trend_summary'] : array());

        $phase2_metrics = isset($meta['phase2']['keyword_metrics']) && is_array($meta['phase2']['keyword_metrics'])
            ? $meta['phase2']['keyword_metrics']
            : (isset($meta['phases']['phase2']['payload']['keyword_metrics']) && is_array($meta['phases']['phase2']['payload']['keyword_metrics'])
                ? $meta['phases']['phase2']['payload']['keyword_metrics']
                : array());

        $phase3_payload = $meta['phases']['phase3']['payload'] ?? ($meta['phase3']['payload'] ?? $meta['phase3'] ?? array());
        $phase3_topics = isset($phase3_payload['prioritized_topics']) && is_array($phase3_payload['prioritized_topics'])
            ? $phase3_payload['prioritized_topics']
            : array();

        $total = max(1, intval($total));
        $topic_data = array();
        foreach ($validated_topics as $topic_item) {
            if (!is_array($topic_item)) {
                continue;
            }
            $topic = sanitize_text_field($topic_item['topic'] ?? '');
            if ($topic === '') {
                continue;
            }
            $key = $this->normalize_topic_key($topic);
            $topic_data[$key] = array(
                'topic' => $topic,
                'keywords' => isset($topic_item['keywords']) && is_array($topic_item['keywords']) ? $topic_item['keywords'] : array(),
                'phase4_citations' => isset($topic_item['citations']) && is_array($topic_item['citations']) ? $topic_item['citations'] : array(),
            );
        }

        if (empty($topic_data)) {
            return new WP_Error('phase4_topics_missing', 'Validated topics are required to build a synopsis plan.');
        }

        $importance_scores = array();
        $correlation_scores = array();
        $citation_scores = array();
        $gap_scores = array();

        $max_volume = 0;
        foreach ($phase2_metrics as $metric) {
            $volume = isset($metric['search_volume']) ? floatval($metric['search_volume']) : 0;
            if ($volume > $max_volume) {
                $max_volume = $volume;
            }
        }

        $max_citations = 0;
        foreach ($topic_data as $key => $data) {
            $topic = $data['topic'];
            $importance_scores[$key] = $this->score_topic_importance($topic, $phase1_trends, $phase1_trend_summary);
            $correlation_scores[$key] = $this->score_topic_correlation($topic, $data['keywords'], $phase2_metrics, $max_volume);
            $citation_scores[$key] = $this->count_topic_citations($topic, $phase3_topics, $data['phase4_citations']);
            if ($citation_scores[$key] > $max_citations) {
                $max_citations = $citation_scores[$key];
            }
            $gap_scores[$key] = $this->score_topic_gap($topic);
        }

        $scores = array();
        foreach ($topic_data as $key => $data) {
            $importance = $importance_scores[$key] ?: 0;
            $correlation = $correlation_scores[$key] ?: 0;
            $citations = $max_citations > 0 ? ($citation_scores[$key] / $max_citations) : 0;
            $gap = $gap_scores[$key] ?: 0;

            $scores[$key] = (0.30 * $importance) + (0.25 * $correlation) + (0.25 * $citations) + (0.20 * $gap);
        }

        $topic_count = count($topic_data);
        $plan = array();

        if ($total <= $topic_count) {
            arsort($scores);
            $assigned = 0;
            foreach ($scores as $key => $score) {
                if ($assigned >= $total) {
                    $plan[$topic_data[$key]['topic']] = 0;
                    continue;
                }
                $plan[$topic_data[$key]['topic']] = 1;
                $assigned++;
            }
            return $plan;
        }

        foreach ($topic_data as $key => $data) {
            $plan[$data['topic']] = 1;
        }

        $remaining = $total - $topic_count;
        $sum_scores = array_sum($scores);

        if ($sum_scores <= 0) {
            $topics = array_keys($plan);
            for ($i = 0; $i < $remaining; $i++) {
                $topic = $topics[$i % count($topics)];
                $plan[$topic] += 1;
            }
            return $plan;
        }

        $fractions = array();
        foreach ($scores as $key => $score) {
            $share = ($score / $sum_scores) * $remaining;
            $count = (int) floor($share);
            $topic = $topic_data[$key]['topic'];
            $plan[$topic] += $count;
            $fractions[$topic] = $share - $count;
        }

        $allocated = array_sum($plan);
        $leftover = $total - $allocated;
        arsort($fractions);
        if ($leftover > 0) {
            foreach ($fractions as $topic => $fraction) {
                if ($leftover <= 0) {
                    break;
                }
                $plan[$topic] += 1;
                $leftover--;
            }
        }

        return $plan;
    }

    private function normalize_topic_key($topic) {
        $key = strtolower(trim($topic));
        $key = preg_replace('/\s+/', ' ', $key);
        return $key;
    }

    private function score_topic_importance($topic, $trends, $trend_summary) {
        $score = 0;
        foreach ($trends as $trend) {
            if (!is_array($trend)) {
                continue;
            }
            $title = $trend['title'] ?? '';
            if ($title !== '' && stripos($title, $topic) !== false) {
                $score += 1;
            }
        }
        foreach ($trend_summary as $item) {
            if (!is_array($item)) {
                continue;
            }
            $trend = $item['trend'] ?? '';
            if ($trend !== '' && stripos($trend, $topic) !== false) {
                $score += ($item['repeated_in_research'] ?? '') === 'yes' ? 1 : 0.5;
            }
        }
        return min(1, $score / 2);
    }

    private function score_topic_correlation($topic, $keywords, $metrics, $max_volume) {
        if ($max_volume <= 0 || empty($metrics)) {
            return 0;
        }

        $best = 0;
        foreach ($metrics as $metric) {
            $keyword = $metric['keyword'] ?? '';
            if ($keyword === '') {
                continue;
            }
            $volume = isset($metric['search_volume']) ? floatval($metric['search_volume']) : 0;
            $matches = stripos($topic, $keyword) !== false || stripos($keyword, $topic) !== false;
            if (!$matches && !empty($keywords)) {
                foreach ($keywords as $kw) {
                    if ($kw !== '' && (stripos($keyword, $kw) !== false || stripos($kw, $keyword) !== false)) {
                        $matches = true;
                        break;
                    }
                }
            }
            if ($matches) {
                $best = max($best, $volume / $max_volume);
            }
        }

        return min(1, $best);
    }

    private function count_topic_citations($topic, $phase3_topics, $phase4_citations) {
        $count = is_array($phase4_citations) ? count($phase4_citations) : 0;
        foreach ($phase3_topics as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item_topic = $item['topic'] ?? '';
            if ($item_topic !== '' && stripos($item_topic, $topic) !== false) {
                $citations = isset($item['citations']) && is_array($item['citations']) ? $item['citations'] : array();
                $count += count($citations);
            }
        }
        return $count;
    }

    private function score_topic_gap($topic) {
        $count = $this->count_existing_articles_by_topic($topic);
        return 1 / (1 + $count);
    }

    private function count_existing_articles_by_topic($topic) {
        $query = new WP_Query(array(
            's' => $topic,
            'post_type' => 'post',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 1,
        ));
        return intval($query->found_posts ?? 0);
    }

    private function build_synopsis_batches($plan, $batch_size) {
        $batch_size = max(1, intval($batch_size));
        $expanded = array();
        foreach ($plan as $topic => $count) {
            $count = intval($count);
            if ($count <= 0) {
                continue;
            }
            for ($i = 0; $i < $count; $i++) {
                $expanded[] = $topic;
            }
        }
        if (empty($expanded)) {
            return array();
        }
        $chunks = array_chunk($expanded, $batch_size);
        $batches = array();
        foreach ($chunks as $chunk) {
            $batch_plan = array();
            foreach ($chunk as $topic) {
                $batch_plan[$topic] = isset($batch_plan[$topic]) ? $batch_plan[$topic] + 1 : 1;
            }
            $batches[] = $batch_plan;
        }
        return $batches;
    }

    private function merge_synopses($existing, $incoming) {
        $existing = is_array($existing) ? $existing : array();
        $incoming = is_array($incoming) ? $incoming : array();
        if (empty($existing)) {
            return $incoming;
        }
        $seen = array();
        foreach ($existing as $item) {
            $key = $item['id'] ?? ($item['title'] ?? '');
            if ($key !== '') {
                $seen[$key] = true;
            }
        }
        foreach ($incoming as $item) {
            $key = $item['id'] ?? ($item['title'] ?? '');
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            $existing[] = $item;
            if ($key !== '') {
                $seen[$key] = true;
            }
        }
        return $existing;
    }

    private function build_validation_export_html($title, $payload) {
        $summary = $payload['validation_summary'] ?? '';
        $topics = isset($payload['validated_topics']) && is_array($payload['validated_topics'])
            ? $payload['validated_topics']
            : array();

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        $html .= '<title>' . esc_html($title) . ' - Validation</title>';
        $html .= '<style>body{font-family:Arial,Helvetica,sans-serif;line-height:1.5;margin:40px;color:#111}';
        $html .= 'h1{font-size:26px;margin-bottom:12px}h2{margin-top:24px;font-size:20px}';
        $html .= 'ul{margin:6px 0 12px 20px}small{color:#555}</style></head><body>';
        $html .= '<h1>' . esc_html($title) . ' — Validation</h1>';
        if ($summary !== '') {
            $html .= '<p>' . esc_html($summary) . '</p>';
        }
        foreach ($topics as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $html .= '<h2>' . esc_html($topic['topic'] ?? 'Topic') . '</h2>';
            if (!empty($topic['validated_insights']) && is_array($topic['validated_insights'])) {
                $html .= '<strong>Validated insights</strong><ul>';
                foreach ($topic['validated_insights'] as $insight) {
                    $html .= '<li>' . esc_html($insight) . '</li>';
                }
                $html .= '</ul>';
            }
            $html .= '<p><small>Trend maturity: ' . esc_html($topic['trend_maturity'] ?? '—') . '</small></p>';
            $html .= '<p><small>Relevance score: ' . esc_html($topic['relevance_score'] ?? '—') . ' | Confidence score: ' . esc_html($topic['confidence_score'] ?? '—') . '</small></p>';
            if (!empty($topic['keywords']) && is_array($topic['keywords'])) {
                $html .= '<strong>Keywords</strong><ul>';
                foreach ($topic['keywords'] as $keyword) {
                    $html .= '<li>' . esc_html($keyword) . '</li>';
                }
                $html .= '</ul>';
            }
            if (!empty($topic['citations']) && is_array($topic['citations'])) {
                $html .= '<strong>Citations</strong><ul>';
                foreach ($topic['citations'] as $citation) {
                    if (!is_array($citation)) {
                        continue;
                    }
                    $label = $citation['title'] ?? $citation['url'] ?? 'Citation';
                    $html .= '<li>' . esc_html($label) . '</li>';
                }
                $html .= '</ul>';
            }
        }
        $html .= '</body></html>';

        return $html;
    }

    private function build_synopses_export_html($title, $articles) {
        $safe_title = esc_html($title);
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . $safe_title . ' - Synopses</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;margin:24px;color:#111;}h1{font-size:24px;}h2{font-size:18px;margin-top:20px;}ul{margin:6px 0 12px 18px;}li{margin:4px 0;}hr{border:none;border-top:1px solid #ddd;margin:16px 0;}</style></head><body>';
        $html .= '<h1>' . $safe_title . ' — Article Synopses</h1>';

        foreach ($articles as $article) {
            $headline = esc_html($article['title'] ?? 'Untitled');
            $summary = esc_html($article['summary'] ?? ($article['brief'] ?? ''));
            $keywords = isset($article['keywords']) && is_array($article['keywords']) ? $article['keywords'] : array();
            $key_points = isset($article['key_points']) && is_array($article['key_points']) ? $article['key_points'] : array();
            $citations = isset($article['citations']) && is_array($article['citations']) ? $article['citations'] : array();
            $word_count = esc_html($article['recommended_word_count'] ?? '');
            $coverage = esc_html($article['topic_coverage_level'] ?? '');

            $html .= '<h2>' . $headline . '</h2>';
            if ($summary !== '') {
                $html .= '<p><strong>Summary:</strong> ' . $summary . '</p>';
            }
            if (!empty($key_points)) {
                $html .= '<p><strong>Key Points:</strong></p><ul>';
                foreach ($key_points as $point) {
                    $html .= '<li>' . esc_html($point) . '</li>';
                }
                $html .= '</ul>';
            }
            if (!empty($keywords)) {
                $html .= '<p><strong>Keywords:</strong> ' . esc_html(implode(', ', $keywords)) . '</p>';
            }
            if (!empty($citations)) {
                $html .= '<p><strong>Supporting Citations:</strong></p><ul>';
                foreach ($citations as $citation) {
                    $label = esc_html($citation['title'] ?? $citation['url'] ?? 'Citation');
                    $url = esc_url($citation['url'] ?? '');
                    $html .= $url ? '<li><a href="' . $url . '">' . $label . '</a></li>' : '<li>' . $label . '</li>';
                }
                $html .= '</ul>';
            }
            if ($word_count !== '') {
                $html .= '<p><strong>Recommended Word Count:</strong> ' . $word_count . '</p>';
            }
            if ($coverage !== '') {
                $html .= '<p><strong>Topic Coverage Level:</strong> ' . $coverage . '</p>';
            }
            $html .= '<hr>';
        }

        $html .= '</body></html>';
        return $html;
    }

    private function build_framework_export_html($title, $article) {
        $safe_title = esc_html($title);
        $framework = $article['framework']['output'] ?? array();
        $citations = isset($article['citations']) && is_array($article['citations']) ? $article['citations'] : array();

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . $safe_title . ' - Framework</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;margin:24px;color:#111;}h1{font-size:24px;}h2{font-size:18px;margin-top:20px;}h3{font-size:15px;margin-top:12px;}ul{margin:6px 0 12px 18px;}li{margin:4px 0;}hr{border:none;border-top:1px solid #ddd;margin:16px 0;}</style></head><body>';
        $html .= '<h1>' . $safe_title . ' — Framework</h1>';

        if (!empty($framework['title'])) {
            $html .= '<h2>' . esc_html($framework['title']) . '</h2>';
        }
        if (!empty($framework['overview'])) {
            $html .= '<p><strong>Overview:</strong> ' . esc_html($framework['overview']) . '</p>';
        }
        if (!empty($framework['context'])) {
            $html .= '<p><strong>Context:</strong> ' . esc_html($framework['context']) . '</p>';
        }
        if (!empty($framework['application']) && is_array($framework['application'])) {
            $reader = esc_html($framework['application']['intended_reader'] ?? '');
            $use_case = esc_html($framework['application']['use_case'] ?? '');
            if ($reader !== '' || $use_case !== '') {
                $html .= '<p><strong>Application:</strong></p>';
                if ($reader !== '') {
                    $html .= '<p>Intended Reader: ' . $reader . '</p>';
                }
                if ($use_case !== '') {
                    $html .= '<p>Use Case: ' . $use_case . '</p>';
                }
            }
        }
        if (!empty($framework['observations']) && is_array($framework['observations'])) {
            $html .= '<h3>Observations</h3><ul>';
            foreach ($framework['observations'] as $item) {
                $headline = esc_html($item['headline'] ?? '');
                $detail = esc_html($item['detail'] ?? '');
                $html .= '<li><strong>' . $headline . '</strong>' . ($detail ? ': ' . $detail : '') . '</li>';
            }
            $html .= '</ul>';
        }
        if (!empty($framework['key_themes']) && is_array($framework['key_themes'])) {
            $html .= '<h3>Key Themes</h3><ul>';
            foreach ($framework['key_themes'] as $theme) {
                $html .= '<li>' . esc_html($theme) . '</li>';
            }
            $html .= '</ul>';
        }
        if (!empty($framework['h2_sections']) && is_array($framework['h2_sections'])) {
            foreach ($framework['h2_sections'] as $section) {
                $html .= '<h2>' . esc_html($section['title'] ?? 'Section') . '</h2>';
                if (!empty($section['h3_sections']) && is_array($section['h3_sections'])) {
                    $html .= '<ul>';
                    foreach ($section['h3_sections'] as $h3) {
                        $html .= '<li>' . esc_html($h3) . '</li>';
                    }
                    $html .= '</ul>';
                }
            }
        }

        if (!empty($citations)) {
            $html .= '<h3>Citations</h3><ul>';
            foreach ($citations as $citation) {
                $label = esc_html($citation['apa'] ?? $citation['title'] ?? $citation['url'] ?? 'Citation');
                $url = esc_url($citation['url'] ?? '');
                $relevance = esc_html($citation['relevance'] ?? '');
                $html .= $url ? '<li><a href="' . $url . '">' . $label . '</a>' : '<li>' . $label;
                if ($relevance !== '') {
                    $html .= '<br><em>' . $relevance . '</em>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</body></html>';
        return $html;
    }

    private function build_author_prompt($article, $framework, $author_profile = 'balanced', $compact_mode = false) {
        $headline = $article['title'] ?? 'Untitled';
        $summary = $article['summary'] ?? ($article['brief'] ?? '');
        $keywords = isset($article['keywords']) && is_array($article['keywords']) ? $article['keywords'] : array();
        $citations = isset($article['citations']) && is_array($article['citations']) ? $article['citations'] : array();

        $author_profile = sanitize_key((string) $author_profile);
        $author_style_guidance = $this->get_author_profile_guidance($author_profile);

        $framework_json = wp_json_encode($framework);
        $citation_json = wp_json_encode(array_slice($citations, 0, 5));
        if (!is_string($framework_json)) {
            $framework_json = '{}';
        }
        if (!is_string($citation_json)) {
            $citation_json = '[]';
        }
        if ($compact_mode && strlen($framework_json) > 3000) {
            $framework_json = substr($framework_json, 0, 3000) . '...';
        }

        $lines = array(
            'You are the Author Agent. Write a draft article based on the provided framework.',
            'Follow strict authorship heuristics to avoid AI-like patterns:',
            '- No rhetorical binaries (e.g., "It is not X, it is Y").',
            '- Avoid fragment beats and forced cadence.',
            '- Do not use em dashes or double hyphens.',
            '- Do not use emojis anywhere.',
            '- Avoid mirrored transitions and formulaic lists.',
            '- Keep headings sparse: H2 no more frequently than every 500 words; H3 no more frequently than every 200 words.',
            '- If a heading would appear too soon, merge with the previous section and continue the body text.',
            '- Put headings on their own line (no heading + paragraph in the same line).',
            '- Target total length: 1500–2500 words. If short, expand depth, examples, and operational detail.',
            '- Use H2 for primary sections and H3 for sub-sections only when needed (aim for 3–6 H2s total).',
            '- Include at least 3 inline citation markers like [1] that map to the citations list.',
            'Selected author profile: ' . $author_profile,
            'Author profile guidance: ' . $author_style_guidance,
            'Return ONLY valid JSON.',
            'Schema: {"title":"","draft":"","key_points":[""],"citations":[{"title":"","url":"","quote":"","lead_author":"","additional_authors":"","organisation":"","publication_date":""}]}',
            'Use the provided citations list. Do not invent new authors or dates; if missing, leave blank.',
            'Article headline: ' . $headline,
        );
        if (!empty($summary)) {
            $lines[] = 'Synopsis: ' . $summary;
        }
        if (!empty($keywords)) {
            $lines[] = 'Keywords: ' . implode(', ', array_slice($keywords, 0, 6));
        }
        $lines[] = 'Framework:';
        $lines[] = $framework_json;
        $lines[] = 'Citations:';
        $lines[] = $citation_json;

        $prompt = implode("\n", $lines);
        if ($compact_mode && strlen($prompt) > 9800) {
            $prompt = substr($prompt, 0, 9800);
        }

        return $prompt;
    }

    private function get_author_profile_guidance($profile) {
        $profile = sanitize_key((string) $profile);
        $guidance = array(
            'balanced' => 'Use a balanced professional voice with moderate depth, clear structure, and practical examples.',
            'journalistic' => 'Use journalistic reporting tone: concise lead, attribution-forward paragraphs, evidence-first sequencing, and neutral wording.',
            'analytical' => 'Use an analytical tone with deeper causal reasoning, trade-off analysis, and explicit assumptions tied to cited evidence.',
            'executive' => 'Use executive briefing tone: decision-oriented, outcome-focused, concise sections, and clear implications for leadership.',
        );
        return $guidance[$profile] ?? $guidance['balanced'];
    }

    private function recommend_author_profile($article, $framework) {
        $title = strtolower((string) ($article['title'] ?? $article['headline'] ?? ''));
        $summary = strtolower((string) ($article['summary'] ?? $article['brief'] ?? ''));
        $keywords = '';
        if (isset($article['keywords']) && is_array($article['keywords'])) {
            $keywords = strtolower(implode(' ', $article['keywords']));
        }

        $framework_blob = strtolower(wp_json_encode($framework));
        $haystack = trim($title . ' ' . $summary . ' ' . $keywords . ' ' . $framework_blob);

        if (preg_match('/\b(board|ceo|cfo|leadership|executive|strategy|roadmap|portfolio|investment)\b/', $haystack)) {
            return 'executive';
        }
        if (preg_match('/\b(data|model|forecast|sensitivity|variance|analysis|benchmark|quant|correlation|method)\b/', $haystack)) {
            return 'analytical';
        }
        if (preg_match('/\b(report|interview|case study|investigation|survey|field|news|press|announced)\b/', $haystack)) {
            return 'journalistic';
        }

        return 'balanced';
    }

    private function create_author_post($session, $article) {
        $title = $article['title'] ?? $article['headline'] ?? $session['title'] ?? 'Draft Article';
        $summary = $article['summary'] ?? ($article['brief'] ?? '');
        $post_data = array(
            'post_title' => $title,
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
        );
        if (!empty($summary)) {
            $post_data['post_excerpt'] = wp_strip_all_tags($summary);
        }
        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            error_log('[PLANNER][AUTHOR] Failed to create draft post: ' . $post_id->get_error_message());
            return null;
        }

        $this->apply_author_post_tags($post_id, $article);
        return $post_id;
    }

    private function extract_author_post_tags($article) {
        $tags = array();

        $keywords = $article['keywords'] ?? array();
        if (is_string($keywords)) {
            $keywords = preg_split('/[,;\n]+/', $keywords);
        }
        if (is_array($keywords)) {
            foreach ($keywords as $keyword) {
                $tag = sanitize_text_field((string) $keyword);
                $tag = trim($tag);
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        $framework_key_themes = $article['framework']['output']['key_themes'] ?? array();
        if (is_array($framework_key_themes)) {
            foreach ($framework_key_themes as $theme) {
                if (is_array($theme)) {
                    $theme = $theme['headline'] ?? $theme['title'] ?? '';
                }
                $tag = sanitize_text_field((string) $theme);
                $tag = trim($tag);
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        return array_values(array_unique($tags));
    }

    private function apply_author_post_tags($post_id, $article) {
        if (empty($post_id) || !taxonomy_exists('post_tag')) {
            return;
        }

        $tags = $this->extract_author_post_tags($article);
        if (empty($tags)) {
            return;
        }

        $result = wp_set_post_terms((int) $post_id, $tags, 'post_tag', true);
        if (is_wp_error($result)) {
            error_log('[PLANNER][AUTHOR] Failed to assign tags for post ' . $post_id . ': ' . $result->get_error_message());
        }
    }

    private function build_author_abstract_text($draft, $fallback = '') {
        $draft = trim((string) $draft);
        if ($fallback) {
            return trim($fallback);
        }
        if ($draft === '') {
            return '';
        }
        $sentences = preg_split('/(?<=[.!?])\\s+/', $draft);
        $sentences = array_values(array_filter(array_map('trim', $sentences)));
        if (empty($sentences)) {
            return '';
        }
        $excerpt = implode(' ', array_slice($sentences, 0, 2));
        return trim($excerpt);
    }

    private function format_author_inline_markdown($text) {
        $text = (string) $text;
        $text = preg_replace('/\\*\\*([^*]+)\\*\\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\\*([^*]+)\\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text);
        return $text;
    }

    private function strip_author_markdown_wrappers($text) {
        $text = trim((string) $text);
        return preg_replace('/^([*_]{1,2})(.+?)\\1$/', '$2', $text);
    }

    private function sanitize_author_draft($draft) {
        $draft = (string) $draft;
        $draft = preg_replace('/—|–|--/', '', $draft);
        $draft = preg_replace('/[\x{1F000}-\x{1FAFF}\x{1F300}-\x{1F5FF}\x{1F600}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $draft);
        return $draft;
    }

    private function build_citation_attribution($citation) {
        $title = trim((string) ($citation['title'] ?? ''));
        $lead_author = trim((string) ($citation['lead_author'] ?? $citation['author'] ?? ''));
        $additional_authors = trim((string) ($citation['additional_authors'] ?? $citation['authors'] ?? ''));
        $organisation = trim((string) ($citation['organisation'] ?? $citation['source'] ?? ''));
        $publication_date = trim((string) ($citation['publication_date'] ?? $citation['published_at'] ?? $citation['year'] ?? ''));
        if ($publication_date !== '' && preg_match('/\\b(19|20)\\d{2}\\b/', $publication_date, $matches)) {
            $publication_date = $matches[0];
        }

        if ($lead_author === '') {
            $lead_author = $organisation;
        }

        $parts = array();
        if ($title !== '') {
            $parts[] = $title;
        }
        if ($lead_author !== '') {
            $parts[] = $lead_author;
        }
        if ($additional_authors !== '') {
            $parts[] = 'et al.';
        }
        if ($organisation !== '' && $organisation !== $lead_author) {
            $parts[] = $organisation;
        }
        if ($publication_date !== '') {
            $parts[] = $publication_date;
        }

        return implode(', ', array_filter($parts));
    }

    private function parse_author_draft_blocks($draft) {
        $draft = trim((string) $draft);
        if ($draft === '') {
            return array();
        }
        $blocks = array();
        $lines = preg_split('/\\r\\n|\\r|\\n/', $draft);
        $paragraph_lines = array();
        $list_items = array();
        $list_ordered = null;

        $flush_paragraph = function () use (&$paragraph_lines, &$blocks) {
            if (empty($paragraph_lines)) {
                return;
            }
            $text = trim(implode(' ', array_map('trim', $paragraph_lines)));
            $paragraph_lines = array();
            if ($text === '') {
                return;
            }
            $blocks[] = array(
                'type' => 'paragraph',
                'content' => wp_kses_post($this->format_author_inline_markdown($text)),
            );
        };

        $flush_list = function () use (&$list_items, &$blocks, &$list_ordered) {
            if (empty($list_items)) {
                return;
            }
            $blocks[] = array(
                'type' => 'list',
                'ordered' => (bool) $list_ordered,
                'items' => array_map(function ($item) {
                    return wp_kses_post($this->format_author_inline_markdown($item));
                }, $list_items),
            );
            $list_items = array();
            $list_ordered = null;
        };

        $line_count = count($lines);
        foreach ($lines as $index => $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                $flush_list();
                $flush_paragraph();
                continue;
            }
            if (preg_match('/^(\\d+)\\.\\s+(.+)$/', $trimmed)) {
                $flush_paragraph();
                $parts = preg_split('/\\s(?=\\d+\\.\\s+)/', $trimmed);
                if (is_array($parts) && !empty($parts)) {
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part === '') {
                            continue;
                        }
                        $part = preg_replace('/^\\d+\\.\\s+/', '', $part);
                        if ($part !== '') {
                            $list_items[] = $part;
                            $list_ordered = true;
                        }
                    }
                    continue;
                }
            }
            if (preg_match('/^(?:[-*•])\\s+(.+)$/', $trimmed, $matches)) {
                $flush_paragraph();
                if ($list_ordered === true) {
                    $flush_list();
                }
                $item_text = trim($matches[1]);
                if ($item_text !== '') {
                    $list_items[] = $item_text;
                    $list_ordered = false;
                }
                continue;
            }
            if (preg_match('/^(#{1,3})\\s+(.*)$/', $trimmed, $matches)) {
                $flush_list();
                $flush_paragraph();
                $level = strlen($matches[1]);
                $heading_line = trim($matches[2]);
                $body_text = '';

                $original_line = $heading_line;
                $split_found = false;
                foreach (array(':', ' - ', ' — ', ' – ') as $delimiter) {
                    $pos = strpos($heading_line, $delimiter);
                    if ($pos !== false && $pos > 0 && $pos < 120) {
                        $heading_line = trim(substr($heading_line, 0, $pos));
                        $body_text = trim(substr($original_line, $pos + strlen($delimiter)));
                        $split_found = true;
                        break;
                    }
                }

                if (!$split_found && str_word_count($heading_line) > 12) {
                    $words = preg_split('/\\s+/', $heading_line);
                    $heading_line = trim(implode(' ', array_slice($words, 0, 10)));
                    $body_text = trim(implode(' ', array_slice($words, 10)));
                }

                $blocks[] = array(
                    'type' => 'heading',
                    'level' => $level,
                    'content' => wp_kses_post($heading_line),
                );
                if ($body_text !== '') {
                    $blocks[] = array(
                        'type' => 'paragraph',
                        'content' => wp_kses_post($this->format_author_inline_markdown($body_text)),
                    );
                }
                continue;
            }
            if (empty($paragraph_lines)) {
                $heading_candidate = $this->strip_author_markdown_wrappers($trimmed);
                $heading_candidate = rtrim($heading_candidate, ':');
                $word_count = str_word_count($heading_candidate);
                $ends_with_punctuation = preg_match('/[.!?]\\s*$/', $heading_candidate);
                if ($word_count >= 1 && $word_count <= 12 && !$ends_with_punctuation) {
                    $flush_list();
                    $level = $word_count <= 8 ? 3 : 2;
                    $blocks[] = array(
                        'type' => 'heading',
                        'level' => $level,
                        'content' => wp_kses_post($heading_candidate),
                    );
                    continue;
                }
            }
            if (strpos($trimmed, ' - ') !== false && preg_match('/\\s-\\s+\\*\\*|\\s-\\s+[A-Z]/', $trimmed)) {
                $flush_paragraph();
                $parts = preg_split('/\\s-\\s+(?=\\*\\*|[A-Z])/', $trimmed);
                if (is_array($parts) && count($parts) > 1) {
                    foreach ($parts as $part) {
                        $part = ltrim($part, '- ');
                        $part = trim($part);
                        if ($part !== '') {
                            $list_items[] = $part;
                        }
                    }
                    continue;
                }
            }
            $paragraph_lines[] = $trimmed;
        }

        $flush_list();
        $flush_paragraph();
        return $blocks;
    }

    private function build_author_post_blocks($author_output, $article) {
        $draft = $author_output['draft'] ?? ($author_output['content'] ?? '');
        $draft = $this->sanitize_author_draft($draft);
        $summary = $article['summary'] ?? ($article['brief'] ?? '');
        $citations = isset($author_output['citations']) && is_array($author_output['citations'])
            ? $author_output['citations']
            : array();
        $article_citations = isset($article['citations']) && is_array($article['citations'])
            ? $article['citations']
            : array();
        if (empty($citations) && !empty($article_citations)) {
            $citations = $article_citations;
        }
        if (!empty($citations) && !empty($article_citations)) {
            $lookup = array();
            foreach ($article_citations as $existing) {
                if (!is_array($existing)) {
                    continue;
                }
                $key = $existing['url'] ?? '';
                if ($key === '') {
                    $key = $existing['title'] ?? '';
                }
                if ($key !== '') {
                    $lookup[$key] = $existing;
                }
            }
            foreach ($citations as $idx => $citation) {
                if (!is_array($citation)) {
                    continue;
                }
                $key = $citation['url'] ?? '';
                if ($key === '') {
                    $key = $citation['title'] ?? '';
                }
                if ($key === '' || empty($lookup[$key])) {
                    continue;
                }
                $existing = $lookup[$key];
                foreach (array('lead_author','additional_authors','publication_date','organisation','source','title','url') as $field) {
                    if (empty($citation[$field]) && !empty($existing[$field])) {
                        $citation[$field] = $existing[$field];
                    }
                }
                $citations[$idx] = $citation;
            }

            // Preserve full article citation coverage even when Author output returns a reduced subset.
            $citation_keys = array();
            foreach ($citations as $citation) {
                if (!is_array($citation)) {
                    continue;
                }
                $key = trim((string) ($citation['url'] ?? ''));
                if ($key === '') {
                    $key = trim((string) ($citation['title'] ?? ''));
                }
                if ($key !== '') {
                    $citation_keys[$key] = true;
                }
            }
            foreach ($article_citations as $existing) {
                if (!is_array($existing)) {
                    continue;
                }
                $key = trim((string) ($existing['url'] ?? ''));
                if ($key === '') {
                    $key = trim((string) ($existing['title'] ?? ''));
                }
                if ($key === '') {
                    continue;
                }
                if (!isset($citation_keys[$key])) {
                    $citations[] = $existing;
                    $citation_keys[$key] = true;
                }
            }
        }
        $framework = $article['framework']['output'] ?? array();

        $blocks = array();

        $overview = $framework['overview'] ?? $this->build_author_abstract_text($draft, $summary);
        $context = $framework['context'] ?? '';
        $application = $framework['application'] ?? array();
        $application_text = '';
        if (is_array($application)) {
            $application_parts = array();
            foreach ($application as $key => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $label = ucwords(str_replace('_', ' ', (string) $key));
                $application_parts[] = $label . ': ' . trim((string) $value);
            }
            $application_text = implode("\n", $application_parts);
        } else {
            $application_text = (string) $application;
        }

        $abstract_data = array(
            'overview' => wp_kses_post($overview),
            '_overview' => 'field_abstract_overview',
            'context' => wp_kses_post($context),
            '_context' => 'field_abstract_context',
            'application' => wp_kses_post($application_text),
            '_application' => 'field_abstract_application',
        );

        $observations = is_array($framework['observations'] ?? null) ? $framework['observations'] : array();
        $bullet_count = 0;
        foreach ($observations as $index => $observation) {
            $headline = trim((string) ($observation['headline'] ?? ''));
            if ($headline === '') {
                continue;
            }
            $abstract_data['key_points_' . $bullet_count . '_bullet'] = $headline;
            $abstract_data['_key_points_' . $bullet_count . '_bullet'] = 'field_abstract_key_points_bullet';
            $bullet_count++;
        }
        $abstract_data['key_points'] = $bullet_count;
        $abstract_data['_key_points'] = 'field_abstract_key_points';

        $blocks[] = array(
            'type' => 'acf_block',
            'name' => 'abstract',
            'data' => $abstract_data,
        );

        $draft_blocks = $this->parse_author_draft_blocks($draft);
        $normalized_blocks = array();

        foreach ($draft_blocks as $block) {
            $normalized_blocks[] = $block;
        }

        $quote_candidates = array();
        foreach ($citations as $citation) {
            $quote = trim((string) ($citation['quote'] ?? $citation['snippet'] ?? ''));
            if ($quote === '') {
                continue;
            }
            $quote_candidates[] = array(
                'text' => $quote,
                'cite' => $this->build_citation_attribution($citation),
            );
        }

        $words_since_quote = 0;
        $quote_index = 0;
        $section_has_heading = false;
        $apply_citation_superscripts = function ($content) {
            return preg_replace_callback(
                '/\\[(\\d{1,3})\\]/',
                function ($matches) {
                    return '<sup class="tp-citation"><a href="#tp-footnotes">[' . $matches[1] . ']</a></sup>';
                },
                (string) $content
            );
        };
        foreach ($normalized_blocks as $block) {
            if ($block['type'] === 'heading') {
                $block['level'] = $section_has_heading ? 3 : 2;
                $section_has_heading = true;
            }
            if ($block['type'] === 'paragraph') {
                $block['content'] = $apply_citation_superscripts($block['content'] ?? '');
            }
            $blocks[] = $block;
            if ($block['type'] !== 'paragraph') {
                continue;
            }
            $text = wp_strip_all_tags($block['content'] ?? '');
            $words_since_quote += str_word_count($text);

            if ($words_since_quote < 500) {
                continue;
            }
            if (empty($quote_candidates)) {
                continue;
            }
            $candidate = $quote_candidates[$quote_index % count($quote_candidates)];
            $blocks[] = array(
                'type' => 'pullquote',
                'content' => wp_kses_post($candidate['text']),
                'cite' => wp_kses_post($candidate['cite']),
                'meta' => array(),
            );
            $quote_index++;
            $words_since_quote = 0;
            $section_has_heading = false;
        }

        if (!empty($citations)) {
            $footnote_data = array();
            $footnote_count = 0;
            foreach ($citations as $citation) {
                $label = trim((string) ($citation['title'] ?? $citation['url'] ?? 'Source'));
                if ($label === '') {
                    continue;
                }
                $footnote_data['footnotes_' . $footnote_count . '_reference_text'] = $label;
                $footnote_data['_footnotes_' . $footnote_count . '_reference_text'] = 'field_footnotes_reference_text';
                $footnote_data['footnotes_' . $footnote_count . '_reference_link'] = $citation['url'] ?? '';
                $footnote_data['_footnotes_' . $footnote_count . '_reference_link'] = 'field_footnotes_reference_link';
                $footnote_data['footnotes_' . $footnote_count . '_publication_date'] = $citation['publication_date'] ?? ($citation['published_at'] ?? ($citation['year'] ?? ''));
                $footnote_data['_footnotes_' . $footnote_count . '_publication_date'] = 'field_footnotes_publication_date';
                $footnote_data['footnotes_' . $footnote_count . '_lead_author'] = $citation['lead_author'] ?? ($citation['author'] ?? ($citation['organisation'] ?? ''));
                $footnote_data['_footnotes_' . $footnote_count . '_lead_author'] = 'field_footnotes_lead_author';
                $footnote_data['footnotes_' . $footnote_count . '_additional_authors'] = $citation['additional_authors'] ?? ($citation['authors'] ?? '');
                $footnote_data['_footnotes_' . $footnote_count . '_additional_authors'] = 'field_footnotes_additional_authors';
                $footnote_count++;
            }
            $footnote_data['footnotes'] = $footnote_count;
            $footnote_data['_footnotes'] = 'field_footnotes_items';

            $blocks[] = array(
                'type' => 'acf_block',
                'name' => 'footnotes',
                'anchor' => 'tp-footnotes',
                'data' => $footnote_data,
            );
        }

        return array('blocks' => $blocks);
    }

    private function update_author_post_from_output($post_id, $author_output, $article) {
        if (!$post_id || empty($author_output)) {
            return false;
        }

        if (!empty($author_output['title'])) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $author_output['title'],
            ));
        }

        $draft = $author_output['draft'] ?? ($author_output['content'] ?? '');
        $draft = $this->sanitize_author_draft($draft);
        $block_data = $this->build_author_post_blocks($author_output, $article);
        if (empty($block_data) || empty($block_data['blocks'])) {
            if (!empty($draft)) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => wpautop($draft),
                ));
                return true;
            }
            return false;
        }

        $gutenberg_blocks = $this->convert_blocks_json_to_gutenberg($block_data);
        if (is_wp_error($gutenberg_blocks)) {
            error_log('[PLANNER][AUTHOR] Failed to convert author blocks: ' . $gutenberg_blocks->get_error_message());
            if (!empty($draft)) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => wpautop($draft),
                ));
                return true;
            }
            return false;
        }
        if (empty($gutenberg_blocks)) {
            if (!empty($draft)) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => wpautop($draft),
                ));
                return true;
            }
            return false;
        }

        $this->insert_blocks_into_post($post_id, $gutenberg_blocks);
        $this->apply_author_post_tags($post_id, $article);
        return true;
    }

    private function evaluate_author_heuristics($draft) {
        $draft = (string) $draft;
        $draft = $this->sanitize_author_draft($draft);
        $issues = array();
        $word_count = str_word_count(wp_strip_all_tags($draft));
        if ($word_count < 1500) {
            $issues[] = 'Draft is below the minimum target length (1500 words).';
        }
        if ($word_count > 2500) {
            $issues[] = 'Draft exceeds the maximum target length (2500 words).';
        }
        $em_dash_count = preg_match_all('/—|–|--/', $draft, $matches);
        $em_dash_limit = max(1, (int) floor($word_count / 300));
        if ($em_dash_count > $em_dash_limit) {
            $issues[] = 'Em dash usage exceeds heuristic threshold.';
        }
        if (preg_match('/[\x{1F000}-\x{1FAFF}\x{1F300}-\x{1F5FF}\x{1F600}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $draft)) {
            $issues[] = 'Emoji usage detected in draft.';
        }

        if (preg_match('/\\b(it|this)\\s+is\\s+not\\b.{0,80}\\b(it|this)\\s+is\\b/i', $draft)) {
            $issues[] = 'Rhetorical binary construction detected.';
        }
        if (preg_match('/\\b(The truth|The answer|What changed)\\?/', $draft)) {
            $issues[] = 'Dramatic fragment prompt detected.';
        }

        $heading_violations = 0;
        $words_since_h2 = 0;
        $words_since_heading = 0;
        $h2_count = 0;
        $h3_count = 0;
        $blocks = $this->parse_author_draft_blocks($draft);
        foreach ($blocks as $block) {
            if ($block['type'] === 'heading') {
                $level = intval($block['level'] ?? 2);
                if ($level <= 2) {
                    $h2_count++;
                } else {
                    $h3_count++;
                }
                $min_words = $level <= 2 ? 500 : 200;
                $words_since = $level <= 2 ? $words_since_h2 : $words_since_heading;
                if ($words_since > 0 && $words_since < $min_words) {
                    $heading_violations++;
                }
                $words_since_heading = 0;
                if ($level <= 2) {
                    $words_since_h2 = 0;
                }
                continue;
            }
            $text = wp_strip_all_tags($block['content'] ?? '');
            $count = str_word_count($text);
            $words_since_h2 += $count;
            $words_since_heading += $count;
        }
        if ($heading_violations > 0) {
            $issues[] = 'Heading density exceeds minimum spacing rules.';
        }
        if ($h2_count === 0) {
            $issues[] = 'No H2 headings detected in draft.';
        }
        if (!preg_match('/\\[\\d+\\]/', $draft)) {
            $issues[] = 'No inline citation markers detected in draft.';
        }

        return array(
            'word_count' => $word_count,
            'em_dash_count' => $em_dash_count,
            'h2_count' => $h2_count,
            'h3_count' => $h3_count,
            'issues' => $issues,
        );
    }

    private function validate_research_phase_payload($phase_key, $payload, $meta = array()) {
        if (!is_array($payload)) {
            return array(
                'issues' => array(),
                'summary' => array(
                    'phase' => $phase_key,
                    'error_count' => 0,
                    'warning_count' => 0,
                    'has_errors' => false,
                    'generated_at' => current_time('mysql'),
                ),
            );
        }

        $policy = $this->resolve_research_policy($meta);
        $issues = array();
        $citations = $this->extract_research_citations_from_payload($phase_key, $payload);
        $blocked_domains = $policy['blocked_domains'];
        $priority_domains = $policy['priority_domains'];
        $blocked_terms = $policy['blocked_keywords'];
        $max_citations_per_org = max(1, intval($policy['max_citations_per_org'] ?? 2));
        $recency_months = max(1, intval($policy['recency_months'] ?? 36));
        $source_mix_minimums = is_array($policy['source_mix_minimums'] ?? null) ? $policy['source_mix_minimums'] : array();
        $source_mix_minimums = array_merge(array(
            'academic' => 1,
            'analyst' => 1,
            'industry' => 1,
            'case_study' => 1,
        ), $source_mix_minimums);
        $min_priority_domains_hit = max(0, intval($policy['min_priority_domains_hit'] ?? 0));
        $org_counts = array();
        $seen_keys = array();
        $priority_domain_hits = array();
        $type_counts = array(
            'academic' => 0,
            'analyst' => 0,
            'industry' => 0,
            'case_study' => 0,
        );

        foreach ($citations as $index => $citation) {
            if (!is_array($citation)) {
                continue;
            }

            $path = 'citations[' . $index . ']';
            $url = trim((string) ($citation['url'] ?? ''));
            $title = trim((string) ($citation['title'] ?? ''));
            $org = trim((string) ($citation['organisation'] ?? $citation['organization'] ?? $citation['source'] ?? $citation['publication'] ?? ''));
            $date_value = trim((string) (
                $citation['publication_date']
                ?? $citation['published_at']
                ?? $citation['date']
                ?? $citation['year']
                ?? ''
            ));

            $dedupe_key = $url !== '' ? strtolower($url) : strtolower($title);
            if ($dedupe_key !== '') {
                if (isset($seen_keys[$dedupe_key])) {
                    $issues[] = $this->build_research_issue(
                        'error',
                        'citation_duplicate',
                        $phase_key,
                        $path,
                        'Citation appears more than once in phase output.',
                        array('dedupe_key' => $dedupe_key)
                    );
                }
                $seen_keys[$dedupe_key] = true;
            }

            if ($url !== '') {
                $host = $this->normalize_research_domain((string) parse_url($url, PHP_URL_HOST));
                foreach ($blocked_domains as $blocked_domain) {
                    if ($this->research_domain_matches($host, $blocked_domain)) {
                        $issues[] = $this->build_research_issue(
                            'error',
                            'blocked_domain',
                            $phase_key,
                            $path . '.url',
                            'Citation domain is blocked by research policy.',
                            array('domain' => $host)
                        );
                        break;
                    }
                }

                foreach ($priority_domains as $priority_domain) {
                    if ($this->research_domain_matches($host, $priority_domain)) {
                        $priority_domain_hits[$priority_domain] = true;
                    }
                }
            }

            $haystack = strtolower($title . ' ' . ($citation['snippet'] ?? '') . ' ' . ($citation['quote'] ?? ''));
            foreach ($blocked_terms as $term) {
                if ($haystack !== '' && strpos($haystack, strtolower($term)) !== false) {
                    $issues[] = $this->build_research_issue(
                        'warning',
                        'blocked_term',
                        $phase_key,
                        $path,
                        'Citation content includes a blocked or low-trust keyword.',
                        array('term' => $term)
                    );
                    break;
                }
            }

            if ($org !== '') {
                $org_key = strtolower($org);
                $org_counts[$org_key] = ($org_counts[$org_key] ?? 0) + 1;
                if ($org_counts[$org_key] > $max_citations_per_org) {
                    $issues[] = $this->build_research_issue(
                        'error',
                        'org_overrepresented',
                        $phase_key,
                        $path,
                        'Citation count exceeds allowed maximum per organization.',
                        array('organization' => $org, 'count' => $org_counts[$org_key], 'max_allowed' => $max_citations_per_org)
                    );
                }
            }

            $timestamp = $this->parse_research_citation_date($date_value);
            if ($date_value === '' || $timestamp === null) {
                $issues[] = $this->build_research_issue(
                    'error',
                    'citation_date_missing_or_invalid',
                    $phase_key,
                    $path . '.date',
                    'Citation date is missing or cannot be parsed.',
                    array('value' => $date_value)
                );
            } else {
                $cutoff = strtotime('-' . $recency_months . ' months');
                if ($timestamp < $cutoff) {
                    $issues[] = $this->build_research_issue(
                        'error',
                        'citation_stale',
                        $phase_key,
                        $path . '.date',
                        'Citation exceeds recency window.',
                        array('value' => $date_value, 'recency_months' => $recency_months)
                    );
                }
            }

            $type_key = $this->classify_research_citation_type($citation);
            if ($type_key !== null && isset($type_counts[$type_key])) {
                $type_counts[$type_key]++;
            }
        }

        if (in_array($phase_key, array('phase3', 'phase4'), true)) {
            foreach ($type_counts as $type => $count) {
                $required_minimum = max(0, intval($source_mix_minimums[$type] ?? 0));
                if ($count < $required_minimum) {
                    $issues[] = $this->build_research_issue(
                        'warning',
                        'source_mix_missing_' . $type,
                        $phase_key,
                        'citations',
                        'Source mix minimum missing for required type: ' . $type . '.',
                        array('required_minimum' => $required_minimum, 'actual' => $count)
                    );
                }
            }

            if ($min_priority_domains_hit > 0 && count($priority_domain_hits) < $min_priority_domains_hit) {
                $issues[] = $this->build_research_issue(
                    'warning',
                    'priority_domain_coverage_low',
                    $phase_key,
                    'citations',
                    'Priority domain coverage is below target.',
                    array('required_minimum' => $min_priority_domains_hit, 'actual' => count($priority_domain_hits))
                );
            }
        }

        $error_count = 0;
        $warning_count = 0;
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'error') {
                $error_count++;
            } else {
                $warning_count++;
            }
        }

        return array(
            'issues' => $issues,
            'summary' => array(
                'phase' => $phase_key,
                'citation_count' => count($citations),
                'error_count' => $error_count,
                'warning_count' => $warning_count,
                'has_errors' => $error_count > 0,
                'policy' => array(
                    'recency_months' => $recency_months,
                    'max_citations_per_org' => $max_citations_per_org,
                    'source_mix_minimums' => $source_mix_minimums,
                    'min_priority_domains_hit' => $min_priority_domains_hit,
                ),
                'generated_at' => current_time('mysql'),
            ),
        );
    }

    private function default_research_policy() {
        return array(
            'priority_domains' => array(),
            'blocked_domains' => array('wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'),
            'blocked_keywords' => array('chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study'),
            'preferred_sources' => array(),
            'source_mix_minimums' => array(
                'academic' => 1,
                'analyst' => 1,
                'industry' => 1,
                'case_study' => 1,
            ),
            'max_citations_per_org' => 2,
            'recency_months' => 36,
            'min_priority_domains_hit' => 0,
        );
    }

    private function default_top_line_categories() {
        return array(
            'manufacturing' => array(
                'slug' => 'manufacturing',
                'name' => 'Manufacturing',
                'research_policy' => array(
                    'priority_domains' => array('mckinsey.com', 'bain.com', 'gartner.com', 'idc.com'),
                    'blocked_domains' => array('wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'),
                    'blocked_keywords' => array('chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study'),
                    'source_mix_minimums' => array('academic' => 1, 'analyst' => 1, 'industry' => 1, 'case_study' => 1),
                    'max_citations_per_org' => 2,
                    'recency_months' => 36,
                    'min_priority_domains_hit' => 1,
                ),
            ),
            'field-service' => array(
                'slug' => 'field-service',
                'name' => 'Field Service',
                'research_policy' => array(
                    // Named brands with authoritative direct domains — validator checks citation URLs against these
                    'priority_domains' => array(
                        'mckinsey.com',          // McKinsey & Company (field service, operations, digital)
                        'bain.com',              // Bain & Company
                        'bcg.com',               // Boston Consulting Group
                        'gartner.com',           // Gartner (Field Service Management, Workforce Enablement)
                        'forrester.com',         // Forrester
                        'idc.com',               // IDC (field service, mobile workforce)
                        'fieldservicenews.com',  // Field Service News
                        'servicecouncil.com',    // Service Council / Field Service News Research
                        'tsia.com',              // TSIA (Technology & Services Industry Association)
                        'hbr.org',               // Harvard Business Review (operations, field service)
                        'sloanreview.mit.edu',   // MIT Sloan Management Review (digital transformation, field ops)
                    ),
                    'blocked_domains' => array('wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'),
                    'blocked_keywords' => array('chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study'),
                    // Specific journals, report series and publications the research agent should actively seek
                    'preferred_sources' => array(
                        // Peer-reviewed journals
                        'Journal of Service Management',
                        'International Journal of Operations & Production Management',
                        'Field Service Management',
                        'Service Industries Journal',
                        'Production and Operations Management',
                        // Analyst reports & series
                        'Gartner Magic Quadrant for Field Service Management',
                        'Gartner Market Guide for Field Service Management',
                        'IDC Field Service Management',
                        'IDC MarketScape Field Service Management',
                        'Forrester Wave Field Service Management',
                        // Industry media & annual studies
                        'TSIA State of Field Services',
                        'Service Council Smarter Services',
                        'Field Service News Research',
                        // Consulting thought-leadership
                        'McKinsey Global Survey on Operations',
                        'BCG Field Operations',
                        // Specialty research bodies
                        'Cambridge Service Alliance',
                        'Aberdeen Field Service Management',
                        'Advance Services Group',
                    ),
                    'source_mix_minimums' => array('academic' => 2, 'analyst' => 2, 'industry' => 2, 'case_study' => 2),
                    'max_citations_per_org' => 2,
                    'recency_months' => 36,
                    'min_priority_domains_hit' => 3,
                ),
            ),
            'logistics' => array(
                'slug' => 'logistics',
                'name' => 'Logistics',
                'research_policy' => array(
                    'priority_domains' => array('freightwaves.com', 'logisticsmgmt.com', 'supplychaindive.com', 'gartner.com', 'mckinsey.com'),
                    'blocked_domains' => array('wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'),
                    'blocked_keywords' => array('chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study'),
                    'source_mix_minimums' => array('academic' => 1, 'analyst' => 2, 'industry' => 2, 'case_study' => 1),
                    'max_citations_per_org' => 2,
                    'recency_months' => 36,
                    'min_priority_domains_hit' => 2,
                ),
            ),
            'energy' => array(
                'slug' => 'energy',
                'name' => 'Energy',
                'research_policy' => array(
                    'priority_domains' => array('iea.org', 'eia.gov', 'mckinsey.com', 'bloomberg.com'),
                    'blocked_domains' => array('wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'),
                    'blocked_keywords' => array('chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study'),
                    'source_mix_minimums' => array('academic' => 1, 'analyst' => 1, 'industry' => 1, 'case_study' => 1),
                    'max_citations_per_org' => 2,
                    'recency_months' => 36,
                    'min_priority_domains_hit' => 1,
                ),
            ),
            'retail' => array(
                'slug' => 'retail',
                'name' => 'Retail',
                'research_policy' => array(
                    'priority_domains' => array('mckinsey.com', 'bain.com', 'gartner.com', 'forrester.com'),
                    'blocked_domains' => array('wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'),
                    'blocked_keywords' => array('chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study'),
                    'source_mix_minimums' => array('academic' => 1, 'analyst' => 1, 'industry' => 1, 'case_study' => 1),
                    'max_citations_per_org' => 2,
                    'recency_months' => 36,
                    'min_priority_domains_hit' => 1,
                ),
            ),
            'pricing' => array(
                'slug' => 'pricing',
                'name' => 'Pricing',
                'research_policy' => array(
                    'priority_domains' => array('pricingbrew.com', 'mckinsey.com', 'bain.com', 'gartner.com', 'hbr.org'),
                    'blocked_domains' => array('wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'),
                    'blocked_keywords' => array('chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study'),
                    'source_mix_minimums' => array('academic' => 1, 'analyst' => 2, 'industry' => 2, 'case_study' => 1),
                    'max_citations_per_org' => 2,
                    'recency_months' => 36,
                    'min_priority_domains_hit' => 2,
                ),
            ),
        );
    }

    private function get_top_line_categories() {
        $defaults = $this->default_top_line_categories();
        $stored = get_option('dual_gpt_top_line_categories', null);
        if (!is_array($stored)) {
            return $defaults;
        }

        $merged = $defaults;
        foreach ($stored as $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidate = $this->sanitize_top_line_category($row);
            $slug = $this->normalize_top_line_category_slug($candidate['slug'] ?? $candidate['name']);
            if ($slug === '') {
                continue;
            }
            $candidate['slug'] = $slug;
            $merged[$slug] = $candidate;
        }

        return $merged;
    }

    private function save_top_line_categories($categories) {
        $rows = array();
        foreach ((array) $categories as $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidate = $this->sanitize_top_line_category($row);
            $slug = $this->normalize_top_line_category_slug($candidate['slug'] ?? $candidate['name']);
            if ($slug === '' || $candidate['name'] === '') {
                continue;
            }
            $candidate['slug'] = $slug;
            $rows[$slug] = $candidate;
        }

        return update_option('dual_gpt_top_line_categories', array_values($rows), false);
    }

    private function normalize_top_line_category_slug($value) {
        return sanitize_title((string) $value);
    }

    private function sanitize_top_line_category($category_input) {
        $category_input = is_array($category_input) ? $category_input : array();

        return array(
            'slug' => $this->normalize_top_line_category_slug($category_input['slug'] ?? $category_input['name'] ?? ''),
            'name' => sanitize_text_field((string) ($category_input['name'] ?? '')),
            'research_policy' => $this->sanitize_research_policy($category_input['research_policy'] ?? array()),
        );
    }

    private function find_top_line_category_by_topic($topic) {
        $topic = strtolower(trim((string) $topic));
        if ($topic === '') {
            return null;
        }

        foreach ($this->get_top_line_categories() as $category) {
            $name = strtolower(trim((string) ($category['name'] ?? '')));
            $slug = strtolower(trim((string) ($category['slug'] ?? '')));
            if ($name === $topic || $slug === sanitize_title($topic)) {
                return $category;
            }
        }

        return null;
    }

    private function merge_research_policy($base_policy, $override_policy_input) {
        $base = $this->sanitize_research_policy($base_policy);
        if (!is_array($override_policy_input)) {
            return $base;
        }

        $override = $this->sanitize_research_policy($override_policy_input);
        $merged = $base;

        $scalar_keys = array('max_citations_per_org', 'recency_months', 'min_priority_domains_hit');
        foreach ($scalar_keys as $key) {
            if (array_key_exists($key, $override_policy_input)) {
                $merged[$key] = $override[$key];
            }
        }

        $array_keys = array('priority_domains', 'blocked_domains', 'blocked_keywords', 'preferred_sources');
        foreach ($array_keys as $key) {
            if (array_key_exists($key, $override_policy_input)) {
                $merged[$key] = $override[$key];
            }
        }

        if (array_key_exists('source_mix_minimums', $override_policy_input) && is_array($override_policy_input['source_mix_minimums'])) {
            $source_mix = $merged['source_mix_minimums'];
            foreach (array('academic', 'analyst', 'industry', 'case_study') as $mix_key) {
                if (array_key_exists($mix_key, $override_policy_input['source_mix_minimums'])) {
                    $source_mix[$mix_key] = $override['source_mix_minimums'][$mix_key];
                }
            }
            $merged['source_mix_minimums'] = $source_mix;
        }

        return $this->sanitize_research_policy($merged);
    }

    private function resolve_research_policy($meta) {
        $defaults = $this->default_research_policy();
        if (!is_array($meta)) {
            return $defaults;
        }

        $candidate = array();
        if (!empty($meta['research_policy']) && is_array($meta['research_policy'])) {
            $candidate = $meta['research_policy'];
        } elseif (!empty($meta['persona_policy']['research']) && is_array($meta['persona_policy']['research'])) {
            $candidate = $meta['persona_policy']['research'];
        }

        return $this->sanitize_research_policy($candidate);
    }

    private function resolve_author_policy($meta) {
        $defaults = $this->default_author_policy();
        if (!is_array($meta)) {
            return $defaults;
        }

        $candidate = array();
        if (!empty($meta['author_policy']) && is_array($meta['author_policy'])) {
            $candidate = $meta['author_policy'];
        } elseif (!empty($meta['persona_policy']['author']) && is_array($meta['persona_policy']['author'])) {
            $candidate = $meta['persona_policy']['author'];
        }

        return $this->sanitize_author_policy($candidate);
    }

    private function ensure_research_policy_in_meta($meta) {
        if (!is_array($meta)) {
            $meta = array();
        }
        $meta['research_policy'] = $this->resolve_research_policy($meta);
        return $meta;
    }

    private function ensure_author_policy_in_meta($meta) {
        if (!is_array($meta)) {
            $meta = array();
        }
        $meta['author_policy'] = $this->resolve_author_policy($meta);
        return $meta;
    }

    private function normalize_research_term_list($terms) {
        if (is_string($terms)) {
            $terms = array_filter(array_map('trim', explode(',', $terms)));
        }
        if (!is_array($terms)) {
            return array();
        }

        $normalized = array();
        foreach ($terms as $term) {
            $term = strtolower(trim((string) $term));
            if ($term === '') {
                continue;
            }
            $normalized[$term] = true;
        }

        return array_keys($normalized);
    }

    /**
     * Normalize a list of publication / journal titles.
     * Preserves original casing; deduplicates case-insensitively (first occurrence wins).
     */
    private function normalize_research_title_list($titles) {
        if (is_string($titles)) {
            $titles = array_filter(array_map('trim', explode(',', $titles)));
        }
        if (!is_array($titles)) {
            return array();
        }

        $seen = array();
        $normalized = array();
        foreach ($titles as $title) {
            $title = trim(sanitize_text_field((string) $title));
            if ($title === '') {
                continue;
            }
            $key = strtolower($title);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $normalized[] = $title;
            }
        }

        return $normalized;
    }

    private function normalize_research_domain_list($domains) {
        if (is_string($domains)) {
            $domains = array_filter(array_map('trim', explode(',', $domains)));
        }
        if (!is_array($domains)) {
            return array();
        }

        $normalized = array();
        foreach ($domains as $domain) {
            $domain = $this->normalize_research_domain($domain);
            if ($domain === '') {
                continue;
            }
            $normalized[$domain] = true;
        }

        return array_keys($normalized);
    }

    private function normalize_research_domain($domain) {
        $domain = strtolower(trim((string) $domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        return trim($domain, '. ');
    }

    private function research_domain_matches($host, $domain) {
        $host = $this->normalize_research_domain($host);
        $domain = $this->normalize_research_domain($domain);
        if ($host === '' || $domain === '') {
            return false;
        }
        return $host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain;
    }

    private function research_domain_in_list($host, $domains) {
        foreach ((array) $domains as $domain) {
            if ($this->research_domain_matches($host, $domain)) {
                return true;
            }
        }
        return false;
    }

    private function build_research_issue($severity, $code, $phase_key, $path, $message, $meta = array()) {
        return array(
            'severity' => $severity,
            'code' => $code,
            'phase' => $phase_key,
            'path' => $path,
            'message' => $message,
            'meta' => is_array($meta) ? $meta : array(),
        );
    }

    private function extract_research_citations_from_payload($phase_key, $payload) {
        $citations = array();
        if (!is_array($payload)) {
            return $citations;
        }

        if (!empty($payload['citations']) && is_array($payload['citations'])) {
            $citations = array_merge($citations, $payload['citations']);
        }

        if ($phase_key === 'phase1' && !empty($payload['trends']) && is_array($payload['trends'])) {
            foreach ($payload['trends'] as $trend) {
                if (!is_array($trend)) {
                    continue;
                }
                if (!empty($trend['citations']) && is_array($trend['citations'])) {
                    $citations = array_merge($citations, $trend['citations']);
                }
            }
        }

        if ($phase_key === 'phase4' && !empty($payload['validated_topics']) && is_array($payload['validated_topics'])) {
            foreach ($payload['validated_topics'] as $topic) {
                if (!is_array($topic)) {
                    continue;
                }
                if (!empty($topic['citations']) && is_array($topic['citations'])) {
                    $citations = array_merge($citations, $topic['citations']);
                }
            }
        }

        return array_values(array_filter($citations, 'is_array'));
    }

    private function classify_research_citation_type($citation) {
        $type_raw = strtolower(trim((string) ($citation['type'] ?? $citation['source_type'] ?? $citation['tier'] ?? '')));
        $source_raw = strtolower(trim((string) ($citation['source'] ?? $citation['publication'] ?? $citation['organisation'] ?? '')));
        $title_raw = strtolower(trim((string) ($citation['title'] ?? '')));
        $combined = $type_raw . ' ' . $source_raw . ' ' . $title_raw;

        if (preg_match('/academic|journal|conference|doi|arxiv/', $combined)) {
            return 'academic';
        }
        if (preg_match('/analyst|gartner|forrester|idc|mckinsey|bain|bcg/', $combined)) {
            return 'analyst';
        }
        if (preg_match('/case[_\s-]?study|customer story|success story/', $combined)) {
            return 'case_study';
        }
        if (preg_match('/industry|trade|news|media|association/', $combined)) {
            return 'industry';
        }

        return null;
    }

    private function parse_research_citation_date($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return strtotime($value . '-12-31');
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return $timestamp;
    }

    private function refresh_research_validation_index($meta) {
        if (!is_array($meta)) {
            return $meta;
        }

        $policy = $this->resolve_research_policy($meta);
        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
        $by_phase = array();
        $all_issues = array();
        $error_count = 0;
        $warning_count = 0;

        foreach ($phases as $phase_key => $phase_data) {
            if (!is_array($phase_data) || empty($phase_data['validation']) || !is_array($phase_data['validation'])) {
                continue;
            }
            $validation = $phase_data['validation'];
            $by_phase[$phase_key] = $validation;
            $phase_issues = isset($validation['issues']) && is_array($validation['issues']) ? $validation['issues'] : array();
            foreach ($phase_issues as $issue) {
                $all_issues[] = $issue;
                if (($issue['severity'] ?? '') === 'error') {
                    $error_count++;
                } else {
                    $warning_count++;
                }
            }
        }

        $meta['research_validation'] = array(
            'summary' => array(
                'error_count' => $error_count,
                'warning_count' => $warning_count,
                'has_errors' => $error_count > 0,
                'generated_at' => current_time('mysql'),
            ),
            'policy' => $policy,
            'by_phase' => $by_phase,
            'issues' => $all_issues,
        );

        return $meta;
    }

    private function hydrate_planner_meta_from_jobs($session_id, $meta) {
        global $wpdb;

        if (!is_array($meta)) {
            $meta = array();
        }
        $meta = $this->ensure_research_policy_in_meta($meta);
        $meta = $this->ensure_author_policy_in_meta($meta);

        $jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, status, idempotency_key, response_json, error_message, created_at FROM {$wpdb->prefix}ai_jobs WHERE session_id = %s AND (idempotency_key LIKE %s OR idempotency_key LIKE %s OR idempotency_key LIKE %s OR idempotency_key LIKE %s OR idempotency_key LIKE %s OR idempotency_key LIKE %s) ORDER BY created_at ASC",
                $session_id,
                'planner-phase%',
                'planner-framework%',
                'planner-fw%',
                'planner-synopses-%',
                'planner-author-%',
                'planner-dive-deeper-%'
            ),
            ARRAY_A
        );

        if (empty($jobs)) {
            return $meta;
        }

        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();
        $titles = array(
            'phase1' => 'Research Phase 1',
            'phase2' => 'Research Phase 2',
            'phase3' => 'Research Phase 3',
            'phase4' => 'Research Phase 4',
        );

        $db = new Dual_GPT_DB_Handler();
        foreach ($jobs as $job) {
            $idempotency = $job['idempotency_key'] ?? '';
            if (($job['status'] ?? '') === 'running' && !empty($job['created_at'])) {
                $age = time() - strtotime($job['created_at']);
                if ($age > 600) {
                    $db->update_job_status($job['id'], 'failed', array(
                        'error_message' => 'Framework job timed out. Please retry.',
                    ));
                    $job['status'] = 'failed';
                    $job['error_message'] = 'Framework job timed out. Please retry.';
                }
            }
            if (strpos($idempotency, 'planner-synopses-') === 0) {
                if (($job['status'] ?? '') !== 'completed' || empty($job['response_json'])) {
                    continue;
                }
                $response_content = $this->extract_response_content_from_job_json($job['response_json']);
                $payload = $this->extract_json_from_content($response_content);
                if (!is_array($payload) || empty($payload['synopses']) || !is_array($payload['synopses'])) {
                    continue;
                }
                $synopses = array();
                foreach ($payload['synopses'] as $synopsis) {
                    if (!is_array($synopsis)) {
                        continue;
                    }
                    $summary = $synopsis['summary'] ?? ($synopsis['summary_two_sentences'] ?? '');
                    $synopses[] = array(
                        'id' => $synopsis['id'] ?? wp_generate_uuid4(),
                        'topic' => $synopsis['topic'] ?? '',
                        'title' => $synopsis['headline'] ?? '',
                        'brief' => $summary,
                        'summary' => $summary,
                        'summary_two_sentences' => $synopsis['summary_two_sentences'] ?? '',
                        'key_points' => isset($synopsis['key_points']) && is_array($synopsis['key_points']) ? $synopsis['key_points'] : array(),
                        'keywords' => isset($synopsis['keywords']) && is_array($synopsis['keywords']) ? $synopsis['keywords'] : array(),
                        'citations' => isset($synopsis['citations']) && is_array($synopsis['citations']) ? $synopsis['citations'] : array(),
                        'citation_count' => isset($synopsis['citations']) && is_array($synopsis['citations']) ? count($synopsis['citations']) : 0,
                        'recommended_word_count' => $synopsis['recommended_word_count'] ?? '',
                        'topic_coverage_level' => $synopsis['topic_coverage_level'] ?? '',
                        'audience_segment' => $synopsis['audience_segment'] ?? '',
                        'priority_score' => $synopsis['priority_score'] ?? null,
                        'opening_hook' => $synopsis['opening_hook'] ?? '',
                        'framework' => array(
                            'status' => 'pending',
                        ),
                    );
                }
                if (!empty($synopses)) {
                    $meta['articles'] = $this->merge_synopses($meta['articles'] ?? array(), $synopses);
                }
                continue;
            }
            if (strpos($idempotency, 'planner-framework-') === 0 || strpos($idempotency, 'planner-fw-') === 0) {
                $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
                foreach ($articles as $index => $article) {
                    if (($article['framework']['job_id'] ?? '') !== ($job['id'] ?? '')) {
                        continue;
                    }
                    $articles[$index]['framework']['status'] = $job['status'] ?? 'queued';
                    if (($job['status'] ?? '') === 'failed') {
                        $articles[$index]['framework']['error_message'] = $job['error_message'] ?? 'Framework failed.';
                    }
                    if (!empty($job['response_json']) && ($job['status'] ?? '') === 'completed') {
                        $response_content = $this->extract_response_content_from_job_json($job['response_json']);
                        $payload = $this->extract_json_from_content($response_content);
                        if (is_array($payload)) {
                            $articles[$index]['framework']['output'] = $payload['framework'] ?? array();
                            $articles[$index]['citations'] = isset($payload['citations']) && is_array($payload['citations']) ? $payload['citations'] : array();
                            $articles[$index]['citation_count'] = count($articles[$index]['citations']);
                        }
                    }
                    break;
                }
                $meta['articles'] = $articles;
                continue;
            }

            if (strpos($idempotency, 'planner-dive-deeper-') === 0) {
                $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
                foreach ($articles as $index => $article) {
                    $jobs_list = isset($article['dive_deeper_jobs']) && is_array($article['dive_deeper_jobs'])
                        ? $article['dive_deeper_jobs']
                        : array();

                    $matched = false;
                    foreach ($jobs_list as $job_index => $dive_job) {
                        if (($dive_job['job_id'] ?? '') !== ($job['id'] ?? '')) {
                            continue;
                        }
                        $matched = true;
                        $jobs_list[$job_index]['status'] = $job['status'] ?? 'queued';
                        if (($job['status'] ?? '') === 'failed') {
                            $jobs_list[$job_index]['error_message'] = $job['error_message'] ?? 'Dive Deeper job failed.';
                        }

                        if (!empty($job['response_json']) && ($job['status'] ?? '') === 'completed') {
                            $response_content = $this->extract_response_content_from_job_json($job['response_json']);
                            $payload = $this->extract_json_from_content($response_content);
                            $new_citations = isset($payload['citations']) && is_array($payload['citations'])
                                ? $payload['citations']
                                : array();

                            $existing = isset($article['citations']) && is_array($article['citations'])
                                ? $article['citations']
                                : array();
                            $seen = array();
                            foreach ($existing as $citation) {
                                $key = strtolower(trim(($citation['url'] ?? '') . '|' . ($citation['title'] ?? '')));
                                if ($key !== '|') {
                                    $seen[$key] = true;
                                }
                            }

                            foreach ($new_citations as $citation) {
                                if (!is_array($citation)) {
                                    continue;
                                }
                                $key = strtolower(trim(($citation['url'] ?? '') . '|' . ($citation['title'] ?? '')));
                                if ($key === '|' || isset($seen[$key])) {
                                    continue;
                                }
                                $existing[] = $citation;
                                $seen[$key] = true;
                            }

                            $article['citations'] = $existing;
                            $article['citation_count'] = count($existing);
                            $article['dive_deeper_last_completed_at'] = gmdate('c');
                            $jobs_list[$job_index]['applied_citations'] = count($new_citations);
                        }

                        break;
                    }

                    if ($matched) {
                        $article['dive_deeper_jobs'] = $jobs_list;
                        $articles[$index] = $article;
                        break;
                    }
                }

                $meta['articles'] = $articles;
                $db->update_session_meta($session_id, $meta);
                continue;
            }

            if (strpos($idempotency, 'planner-author-') === 0) {
                $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
                foreach ($articles as $index => $article) {
                    if (($article['author']['job_id'] ?? '') !== ($job['id'] ?? '')) {
                        continue;
                    }
                    $articles[$index]['author']['status'] = $job['status'] ?? 'queued';
                    if (($job['status'] ?? '') === 'failed') {
                        $articles[$index]['author']['error_message'] = $job['error_message'] ?? 'Author run failed.';
                    }
                    if (!empty($job['response_json']) && ($job['status'] ?? '') === 'completed') {
                        $response_content = $this->extract_response_content_from_job_json($job['response_json']);
                        $payload = $this->extract_json_from_content($response_content);
                        if (is_array($payload)) {
                            $articles[$index]['author']['output'] = $payload;
                            if (!empty($payload['draft']) || !empty($payload['content'])) {
                                $draft_text = $payload['draft'] ?? $payload['content'];
                                $articles[$index]['author']['validation'] = $this->evaluate_author_heuristics($draft_text);
                            }
                            $post_id = $articles[$index]['author']['post_id'] ?? null;
                            if (empty($post_id)) {
                                $session = $db->get_session($session_id);
                                $post_id = $this->create_author_post($session, $article);
                                $articles[$index]['author']['post_id'] = $post_id;
                                $articles[$index]['author']['edit_url'] = $post_id ? get_edit_post_link($post_id, 'raw') : '';
                            }
                            $output_hash = md5(wp_json_encode($payload));
                            $stored_hash = $articles[$index]['author']['output_hash'] ?? '';
                            $needs_fill = false;
                            if ($post_id) {
                                $current_content = get_post_field('post_content', $post_id);
                                $needs_fill = empty($current_content);
                            }
                            if ($post_id && ($output_hash !== $stored_hash || $needs_fill)) {
                                $did_update = $this->update_author_post_from_output($post_id, $payload, $article);
                                if ($did_update) {
                                    $articles[$index]['author']['output_hash'] = $output_hash;
                                    $articles[$index]['author']['post_filled_at'] = current_time('mysql');
                                }
                            }
                        }
                    }
                    break;
                }
                $meta['articles'] = $articles;
                $db->update_session_meta($session_id, $meta);
                continue;
            }

            if (!preg_match('/planner-(phase\\d)-/', $idempotency, $matches)) {
                continue;
            }

            $phase_key = $matches[1];
            if ($phase_key === 'phase2') {
                $phase_key = 'phase3';
            } elseif ($phase_key === 'phase3') {
                $phase_key = 'phase4';
            }
            $status = $job['status'] ?? 'queued';
            $phase = $phases[$phase_key] ?? array();

            $phase['title'] = $titles[$phase_key] ?? $phase_key;
            $phase['job_id'] = $job['id'];
            $phase['status'] = $status;

            if ($status === 'failed') {
                $phase['error_message'] = $job['error_message'] ?? 'Job failed.';
            }

            if (!empty($job['response_json']) && ($status === 'completed' || $status === 'running')) {
                $response_content = $this->extract_response_content_from_job_json($job['response_json']);
                if (!empty($response_content)) {
                    $payload = $this->extract_json_from_content($response_content);
                    if (is_array($payload)) {
                        $validation = $this->validate_research_phase_payload($phase_key, $payload, $meta);
                        $phase['payload'] = $payload;
                        $phase['validation'] = $validation;
                        $phase['summary'] = $payload['summary'] ?? $phase['summary'] ?? '';
                        if (empty($phase['summary'])) {
                            $phase['summary'] = $payload['executive_summary']
                                ?? $payload['deep_dive_summary']
                                ?? $payload['article_summary']
                                ?? $payload['validation_summary']
                                ?? $payload['executive_research_summary']
                                ?? '';
                        }
                        if ($phase_key === 'phase1') {
                            if (!empty($payload['candidate_keywords']) && is_array($payload['candidate_keywords'])) {
                                $meta['phase1']['candidate_keywords'] = $payload['candidate_keywords'];
                            }
                            if (!empty($payload['trend_summary']) && is_array($payload['trend_summary'])) {
                                $meta['phase1']['trend_summary'] = $payload['trend_summary'];
                            }
                            if (!empty($payload['executive_summary'])) {
                                $meta['phase1']['summary'] = $payload['executive_summary'];
                            }
                        }
                        if (!empty($payload['articles']) && is_array($payload['articles'])) {
                            $normalized_articles = array();
                            foreach ($payload['articles'] as $article) {
                                if (!is_array($article)) {
                                    continue;
                                }
                                $normalized_articles[] = array(
                                    'id' => $article['id'] ?? wp_generate_uuid4(),
                                    'title' => $article['title'] ?? '',
                                    'brief' => $article['brief'] ?? '',
                                    'keywords' => isset($article['keywords']) && is_array($article['keywords']) ? $article['keywords'] : array(),
                                    'score' => $article['score'] ?? 0,
                                    'initial_score' => $article['initial_score'] ?? ($article['score'] ?? 0),
                                    'framework' => array(
                                        'status' => 'queued',
                                    ),
                                    'citations' => array(),
                                    'citation_count' => 0,
                                );
                            }
                            $phase['articles'] = $normalized_articles;
                            $meta['articles'] = $normalized_articles;
                            if (empty($payload['summary'])) {
                                $phase['summary'] = '';
                            }
                        }
                    } elseif (empty($phase['summary'])) {
                        $phase['summary'] = trim($response_content);
                    }
                }
            }

            $phases[$phase_key] = $phase;
        }

        $meta['phases'] = $phases;
        $meta = $this->refresh_research_validation_index($meta);

        if (!empty($meta['articles']) && is_array($meta['articles'])) {
            foreach ($meta['articles'] as $index => $article) {
                $status = $article['framework']['status'] ?? '';
                if ($status === 'completed') {
                    $meta['articles'][$index]['framework']['status'] = 'complete';
                }
                if ($status === 'running' && empty($article['framework']['job_id'])) {
                    $meta['articles'][$index]['framework']['status'] = 'pending';
                }
            }
        }

        $db = new Dual_GPT_DB_Handler();
        $db->update_session_meta($session_id, $meta);

        return $meta;
    }

    private function extract_response_content_from_job_json($response_json) {
        $decoded = json_decode($response_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }

        return $decoded['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Include additional classes
     */
    private function include_classes() {
        // Include database handler
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-db-handler.php';

        // Include OpenAI connector
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-openai-connector.php';

        // Include LLM client
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-llm-client.php';

        // Include Search Providers
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-search-providers.php';

        // Include Keyword Providers
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-keyword-providers.php';

        // Include Model Config
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-model-config.php';

        // Include image generation foundation
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-image-settings.php';
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-image-provider-registry.php';
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-image-prompt-builder.php';
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-image-generation-service.php';
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/providers/class-openai-image-provider.php';
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/providers/class-google-image-provider.php';

        // Include Planner Orchestrator
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-planner-orchestrator.php';

        // Include Framework Generator API
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-generator-api.php';

        // Include Framework Generator Workers
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-generator-workers.php';

        // Include Framework Generator Citation Verifier
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-generator-citation-verifier.php';

        // Include Framework Generator Exporter
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-generator-exporter.php';

        // Include Framework Generator Blocks
        require_once DUAL_GPT_PLUGIN_DIR . 'blocks/citation-qa/citation-qa.php';

        // Include Framework Brief Validator
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-brief-validator.php';

        // Include tool classes
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/tools/class-research-tools.php';
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/tools/class-author-tools.php';
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/tools/class-seo-tools.php';

        // Include Author Agent
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-author-agent.php';

        // Include admin class
        if (is_admin()) {
            require_once DUAL_GPT_PLUGIN_DIR . 'admin/class-dual-gpt-admin.php';
        }
    }

    /**
     * Upgrade schema if needed
     */
    private function maybe_upgrade_schema() {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_sessions';
        $column = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", 'meta_json'));
        $queue_table = $wpdb->prefix . 'planner_task_queue';
        $queue_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $queue_table));
        $queue_status_column = $queue_exists === $queue_table
            ? $wpdb->get_row("SHOW COLUMNS FROM {$queue_table} LIKE 'status'", ARRAY_A)
            : null;
        $needs_queue_upgrade = !$queue_status_column || strpos((string) ($queue_status_column['Type'] ?? ''), 'dispatched') === false;
        if (empty($column) || $queue_exists !== $queue_table || $needs_queue_upgrade) {
            $this->create_tables();
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

        // Create default presets
        $db = new Dual_GPT_DB_Handler();
        $db->create_default_presets();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // AI Sessions table
        $table_sessions = $wpdb->prefix . 'ai_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            post_id BIGINT(20) UNSIGNED NULL,
            role ENUM('research', 'author', 'seo') NOT NULL,
            preset_id VARCHAR(36) NULL,
            title VARCHAR(255) NULL,
            meta_json LONGTEXT NULL,
            system_prompt TEXT NULL,
            preset_version VARCHAR(20) NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            idempotency_key VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_post_id (post_id),
            INDEX idx_role (role),
            INDEX idx_created_by (created_by),
            UNIQUE KEY idx_session_idempotency (idempotency_key)
        ) $charset_collate;";

        // AI Jobs table
        $table_jobs = $wpdb->prefix . 'ai_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            model VARCHAR(50) NOT NULL,
            status ENUM('queued', 'running', 'completed', 'failed') DEFAULT 'queued',
            input_prompt TEXT NULL,
            response_json LONGTEXT NULL,
            output_blocks_json LONGTEXT NULL,
            compliance_json LONGTEXT NULL,
            schema_version VARCHAR(10) DEFAULT '1',
            usage_prompt_tokens INT DEFAULT 0,
            usage_completion_tokens INT DEFAULT 0,
            cost_micro INT DEFAULT 0,
            preset_version VARCHAR(20) NULL,
            tool_chain TEXT NULL,
            hard_cap_hit BOOLEAN DEFAULT FALSE,
            error_code VARCHAR(50) NULL,
            error_message TEXT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            idempotency_key VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            INDEX idx_session_id (session_id),
            INDEX idx_status (status),
            UNIQUE KEY idx_job_idempotency (session_id, idempotency_key),
            FOREIGN KEY (session_id) REFERENCES $table_sessions(id) ON DELETE CASCADE
        ) $charset_collate;";

        // AI Presets table
        $table_presets = $wpdb->prefix . 'ai_presets';
        $sql_presets = "CREATE TABLE $table_presets (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            role ENUM('research', 'author', 'seo', 'both') NOT NULL,
            system_prompt TEXT NULL,
            default_model VARCHAR(50) NULL,
            params_json LONGTEXT NULL,
            tool_whitelist TEXT NULL,
            preset_version VARCHAR(20) DEFAULT '1.0.0',
            is_locked BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role (role)
        ) $charset_collate;";

        // AI Audit table
        $table_audit = $wpdb->prefix . 'ai_audit';
        $sql_audit = "CREATE TABLE $table_audit (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            job_id VARCHAR(36) NOT NULL,
            event_type ENUM('queued', 'tool_call', 'delta', 'finish', 'error') NOT NULL,
            payload_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_job_id (job_id),
            INDEX idx_event_type (event_type),
            FOREIGN KEY (job_id) REFERENCES $table_jobs(id) ON DELETE CASCADE
        ) $charset_collate;";

        // AI Budgets table
        $table_budgets = $wpdb->prefix . 'ai_budgets';
        $sql_budgets = "CREATE TABLE $table_budgets (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            scope ENUM('site', 'role', 'user') NOT NULL,
            scope_id VARCHAR(100) NULL,
            period ENUM('monthly') NOT NULL,
            token_limit INT NOT NULL,
            token_used INT DEFAULT 0,
            reset_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_scope (scope, scope_id),
            INDEX idx_reset_at (reset_at)
        ) $charset_collate;";

        // Framework Generator Validated Citations table
        $table_fg_citations = $wpdb->prefix . 'fg_validated_citations';
        $sql_fg_citations = "CREATE TABLE $table_fg_citations (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            job_id VARCHAR(36) NULL,
            brief_id VARCHAR(36) NULL,
            title TEXT,
            lead_author VARCHAR(255),
            additional_authors TEXT,
            publication VARCHAR(255),
            organisation VARCHAR(255),
            year SMALLINT,
            publication_date VARCHAR(50),
            url TEXT,
            apa_string TEXT,
            apa_details_available TINYINT(1) DEFAULT 1,
            passage_snippet TEXT,
            type VARCHAR(50),
            tier VARCHAR(10),
            authority_score FLOAT DEFAULT 0.0,
            confidence FLOAT DEFAULT 0.5,
            sponsored TINYINT(1) DEFAULT 0,
            approved TINYINT(1) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_org (organisation),
            INDEX idx_type (type),
            INDEX idx_brief_id (brief_id),
            INDEX idx_approved (approved)
        ) $charset_collate;";

        // Framework Generator Briefs table
        $table_fg_briefs = $wpdb->prefix . 'fg_briefs';
        $sql_fg_briefs = "CREATE TABLE $table_fg_briefs (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            article_idea JSON,
            title VARCHAR(1000),
            overview TEXT,
            context TEXT,
            application JSON,
            observations JSON,
            key_themes JSON,
            citations JSON,
            writer_guidance JSON,
            metadata JSON,
            produced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            scoring JSON,
            INDEX idx_session_id (session_id)
        ) $charset_collate;";

        // Framework Generator Exports table
        $table_fg_exports = $wpdb->prefix . 'fg_exports';
        $sql_fg_exports = "CREATE TABLE $table_fg_exports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brief_id VARCHAR(36),
            format VARCHAR(20),
            file_url TEXT,
            file_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        // Framework Generator Raw Articles table (staging)
        $table_fg_raw_articles = $wpdb->prefix . 'fg_raw_articles';
        $sql_fg_raw_articles = "CREATE TABLE $table_fg_raw_articles (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            title TEXT,
            url TEXT,
            domain VARCHAR(255),
            author VARCHAR(255),
            date DATE,
            source_type VARCHAR(50),
            snippet TEXT,
            extracted_claims JSON,
            keywords JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            FOREIGN KEY (session_id) REFERENCES $table_sessions(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Framework Generator Session Exclusions table
        $table_fg_exclusions = $wpdb->prefix . 'fg_session_exclusions';
        $sql_fg_exclusions = "CREATE TABLE $table_fg_exclusions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            domain VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            UNIQUE KEY idx_session_domain (session_id, domain)
        ) $charset_collate;";

        // Framework Generator Session Keywords table
        $table_fg_keywords = $wpdb->prefix . 'fg_session_keywords';
        $sql_fg_keywords = "CREATE TABLE $table_fg_keywords (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            keyword VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id)
        ) $charset_collate;";

        // Planner editor-managed task queue table
        $table_planner_queue = $wpdb->prefix . 'planner_task_queue';
        $sql_planner_queue = "CREATE TABLE $table_planner_queue (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            article_id VARCHAR(36) NULL,
            task_type ENUM('dive_deeper','framework_generation','article_creation') NOT NULL,
            status ENUM('queued','running','dispatched','completed','failed') NOT NULL DEFAULT 'queued',
            payload_json LONGTEXT NULL,
            linked_job_id VARCHAR(36) NULL,
            position INT NOT NULL DEFAULT 1,
            error_message TEXT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_session_status (session_id, status),
            INDEX idx_position (position),
            INDEX idx_task_type (task_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_jobs);
        dbDelta($sql_presets);
        dbDelta($sql_audit);
        dbDelta($sql_budgets);
        dbDelta($sql_fg_citations);
        dbDelta($sql_fg_briefs);
        dbDelta($sql_fg_exports);
        dbDelta($sql_fg_raw_articles);
        dbDelta($sql_fg_exclusions);
        dbDelta($sql_fg_keywords);
        dbDelta($sql_planner_queue);
    }

    /**
     * Simple per-user rate limiting to reduce abuse
     */
    private function check_rate_limit($type, $user_id, $limit, $window_seconds) {
        $key = "dual_gpt_rate_{$type}_{$user_id}";
        $data = get_transient($key);
        $now = time();

        if (!$data || !isset($data['window_start']) || ($now - $data['window_start']) > $window_seconds) {
            set_transient($key, array('window_start' => $now, 'count' => 1), $window_seconds);
            return true;
        }

        if ($data['count'] >= $limit) {
            return false;
        }

        $data['count']++;
        set_transient($key, $data, $window_seconds);
        return true;
    }
}
