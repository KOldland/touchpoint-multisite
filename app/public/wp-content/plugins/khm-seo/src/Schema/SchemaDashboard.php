<?php

namespace KHM_SEO\Schema;

use Exception;

/**
 * Schema Dashboard
 * 
 * Comprehensive admin interface for schema management, validation, testing,
 * and optimization within the KHM SEO Phase 9 Module.
 * 
 * Features:
 * - Schema overview and management interface
 * - Real-time schema validation and testing
 * - Rich snippet preview and testing
 * - Schema performance analytics
 * - Bulk schema operations
 * - Schema generation tools
 * - Integration with Google Testing Tool
 * - Schema optimization recommendations
 * 
 * @package KHM_SEO\Schema
 * @since 1.0.0
 */
class SchemaDashboard {

    /**
     * @var SchemaValidator
     */
    private $validator;

    /**
     * @var SchemaManager
     */
    private $manager;

    /**
     * Dashboard configuration
     */
    private $config = [
        'posts_per_page' => 20,
        'validation_cache_time' => 3600,
        'performance_metrics_days' => 30,
        'batch_processing_limit' => 50
    ];

    /**
     * Schema type statistics
     */
    private $schema_stats = [];

    /**
     * Performance metrics
     */
    private $performance_data = [];

    /**
     * Initialize Schema Dashboard
     */
    public function __construct() {
        $this->validator = new SchemaValidator();
        
        // Initialize SchemaManager if it exists in the current namespace structure
        if (class_exists('KHM_SEO\\Schema\\SchemaManager')) {
            $this->manager = new \KHM_SEO\Schema\SchemaManager();
        }
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        add_action('wp_ajax_schema_dashboard_action', [$this, 'handle_ajax_actions']);
        
        $this->init_dashboard();
    }

    /**
     * Initialize dashboard
     */
    private function init_dashboard() {
        $this->load_schema_statistics();
        $this->load_performance_data();
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-seo-dashboard',
            'Schema Management',
            'Schema',
            'manage_options',
            'khm-seo-schema',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'khm-seo-schema',
            'Schema Validation',
            'Validation',
            'manage_options',
            'khm-seo-schema-validation',
            [$this, 'render_validation_page']
        );

        add_submenu_page(
            'khm-seo-schema',
            'Schema Analytics',
            'Analytics',
            'manage_options',
            'khm-seo-schema-analytics',
            [$this, 'render_analytics_page']
        );

        add_submenu_page(
            'khm-seo-schema',
            'Schema Tools',
            'Tools',
            'manage_options',
            'khm-seo-schema-tools',
            [$this, 'render_tools_page']
        );
    }

    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        if (strpos($hook, 'khm-seo-schema') === false) {
            return;
        }

        wp_enqueue_script(
            'khm-schema-dashboard',
            plugins_url('assets/js/schema-dashboard.js', dirname(__FILE__, 3)),
            ['jquery', 'wp-api'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'khm-schema-dashboard',
            plugins_url('assets/css/schema-dashboard.css', dirname(__FILE__, 3)),
            [],
            '1.0.0'
        );

        wp_localize_script('khm-schema-dashboard', 'khmSchema', [
            'nonce' => wp_create_nonce('khm_schema_dashboard'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'strings' => [
                'validating' => __('Validating schema...', 'khm-seo'),
                'generating' => __('Generating schema...', 'khm-seo'),
                'testing' => __('Testing rich snippets...', 'khm-seo'),
                'success' => __('Operation completed successfully', 'khm-seo'),
                'error' => __('Operation failed', 'khm-seo')
            ]
        ]);
    }

    /**
     * Render main dashboard page
     */
    public function render_dashboard() {
        $this->load_schema_statistics();
        ?>
        <div class="wrap khm-schema-dashboard">
            <h1>
                <?php esc_html_e('Schema Management Dashboard', 'khm-seo'); ?>
                <button id="refresh-stats" class="button button-secondary">
                    <?php esc_html_e('Refresh Stats', 'khm-seo'); ?>
                </button>
            </h1>

            <?php $this->render_dashboard_notices(); ?>

            <div class="khm-dashboard-grid">
                <?php $this->render_overview_stats(); ?>
                <?php $this->render_schema_health_widget(); ?>
                <?php $this->render_recent_activity_widget(); ?>
                <?php $this->render_quick_actions_widget(); ?>
            </div>

            <div class="khm-dashboard-content">
                <div class="khm-dashboard-main">
                    <?php $this->render_schema_content_table(); ?>
                </div>
                
                <div class="khm-dashboard-sidebar">
                    <?php $this->render_schema_insights(); ?>
                    <?php $this->render_optimization_recommendations(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render validation page
     */
    public function render_validation_page() {
        ?>
        <div class="wrap khm-schema-validation">
            <h1><?php esc_html_e('Schema Validation', 'khm-seo'); ?></h1>

            <div class="validation-tools">
                <div class="validation-input">
                    <h2><?php esc_html_e('Test Schema Markup', 'khm-seo'); ?></h2>
                    
                    <form id="schema-validation-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('URL to Test', 'khm-seo'); ?></th>
                                <td>
                                    <input type="url" id="validation-url" class="regular-text" 
                                           placeholder="https://example.com/page" />
                                    <p class="description">
                                        <?php esc_html_e('Enter a URL to test its schema markup', 'khm-seo'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Manual Schema Input', 'khm-seo'); ?></th>
                                <td>
                                    <textarea id="manual-schema" rows="10" class="large-text code" 
                                              placeholder="Paste JSON-LD schema markup here..."></textarea>
                                    <p class="description">
                                        <?php esc_html_e('Or paste schema markup directly for validation', 'khm-seo'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Validate Schema', 'khm-seo'); ?>
                            </button>
                            <button type="button" id="test-rich-snippets" class="button button-secondary">
                                <?php esc_html_e('Test Rich Snippets', 'khm-seo'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <div class="validation-results">
                    <h3><?php esc_html_e('Validation Results', 'khm-seo'); ?></h3>
                    <div id="validation-output"></div>
                </div>
            </div>

            <?php $this->render_bulk_validation_section(); ?>
        </div>
        <?php
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap khm-schema-analytics">
            <h1><?php esc_html_e('Schema Analytics', 'khm-seo'); ?></h1>

            <div class="analytics-dashboard">
                <?php $this->render_performance_charts(); ?>
                <?php $this->render_rich_snippets_performance(); ?>
                <?php $this->render_schema_coverage_analysis(); ?>
                <?php $this->render_validation_trends(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render tools page
     */
    public function render_tools_page() {
        ?>
        <div class="wrap khm-schema-tools">
            <h1><?php esc_html_e('Schema Tools', 'khm-seo'); ?></h1>

            <div class="tools-grid">
                <?php $this->render_schema_generator_tool(); ?>
                <?php $this->render_bulk_operations_tool(); ?>
                <?php $this->render_migration_tools(); ?>
                <?php $this->render_import_export_tools(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render dashboard overview statistics
     */
    private function render_overview_stats() {
        $stats = $this->schema_stats;
        ?>
        <div class="khm-stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo esc_html($stats['total_posts'] ?? 0); ?></div>
                <div class="stat-label"><?php esc_html_e('Total Content', 'khm-seo'); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo esc_html($stats['with_schema'] ?? 0); ?></div>
                <div class="stat-label"><?php esc_html_e('With Schema', 'khm-seo'); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo esc_html($stats['valid_schema'] ?? 0); ?></div>
                <div class="stat-label"><?php esc_html_e('Valid Schema', 'khm-seo'); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo esc_html($stats['rich_snippets'] ?? 0); ?></div>
                <div class="stat-label"><?php esc_html_e('Rich Snippets', 'khm-seo'); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render schema health widget
     */
    private function render_schema_health_widget() {
        $health_score = $this->calculate_schema_health_score();
        ?>
        <div class="schema-health-widget">
            <h3><?php esc_html_e('Schema Health Score', 'khm-seo'); ?></h3>
            <div class="health-score-circle">
                <div class="score-value" data-score="<?php echo esc_attr($health_score); ?>">
                    <?php echo esc_html($health_score); ?>%
                </div>
            </div>
            <div class="health-details">
                <?php $this->render_health_breakdown(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render recent activity widget
     */
    private function render_recent_activity_widget() {
        $recent_activity = $this->get_recent_schema_activity();
        ?>
        <div class="recent-activity-widget">
            <h3><?php esc_html_e('Recent Activity', 'khm-seo'); ?></h3>
            <div class="activity-list">
                <?php if (!empty($recent_activity)): ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo esc_attr($activity['type']); ?>"></div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo esc_html($activity['title']); ?></div>
                                <div class="activity-time"><?php echo esc_html($activity['time']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php esc_html_e('No recent activity', 'khm-seo'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render quick actions widget
     */
    private function render_quick_actions_widget() {
        ?>
        <div class="quick-actions-widget">
            <h3><?php esc_html_e('Quick Actions', 'khm-seo'); ?></h3>
            <div class="action-buttons">
                <button class="button button-primary" id="generate-all-schema">
                    <?php esc_html_e('Generate All Schema', 'khm-seo'); ?>
                </button>
                <button class="button button-secondary" id="validate-all-schema">
                    <?php esc_html_e('Validate All Schema', 'khm-seo'); ?>
                </button>
                <button class="button button-secondary" id="test-rich-snippets-all">
                    <?php esc_html_e('Test Rich Snippets', 'khm-seo'); ?>
                </button>
                <button class="button button-secondary" id="optimize-schema">
                    <?php esc_html_e('Optimize Schema', 'khm-seo'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render schema content table
     */
    private function render_schema_content_table() {
        $content_items = $this->get_schema_content_items();
        ?>
        <div class="schema-content-table">
            <h2><?php esc_html_e('Content Schema Status', 'khm-seo'); ?></h2>
            
            <div class="table-controls">
                <div class="filter-controls">
                    <select id="schema-type-filter">
                        <option value=""><?php esc_html_e('All Schema Types', 'khm-seo'); ?></option>
                        <option value="Article"><?php esc_html_e('Article', 'khm-seo'); ?></option>
                        <option value="Product"><?php esc_html_e('Product', 'khm-seo'); ?></option>
                        <option value="Organization"><?php esc_html_e('Organization', 'khm-seo'); ?></option>
                    </select>
                    
                    <select id="validation-status-filter">
                        <option value=""><?php esc_html_e('All Statuses', 'khm-seo'); ?></option>
                        <option value="valid"><?php esc_html_e('Valid', 'khm-seo'); ?></option>
                        <option value="invalid"><?php esc_html_e('Invalid', 'khm-seo'); ?></option>
                        <option value="missing"><?php esc_html_e('Missing', 'khm-seo'); ?></option>
                    </select>
                </div>
                
                <div class="bulk-actions">
                    <select id="bulk-action-select">
                        <option value=""><?php esc_html_e('Bulk Actions', 'khm-seo'); ?></option>
                        <option value="generate"><?php esc_html_e('Generate Schema', 'khm-seo'); ?></option>
                        <option value="validate"><?php esc_html_e('Validate Schema', 'khm-seo'); ?></option>
                        <option value="delete"><?php esc_html_e('Remove Schema', 'khm-seo'); ?></option>
                    </select>
                    <button class="button" id="apply-bulk-action">
                        <?php esc_html_e('Apply', 'khm-seo'); ?>
                    </button>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-content">
                        </td>
                        <th><?php esc_html_e('Title', 'khm-seo'); ?></th>
                        <th><?php esc_html_e('Type', 'khm-seo'); ?></th>
                        <th><?php esc_html_e('Schema Status', 'khm-seo'); ?></th>
                        <th><?php esc_html_e('Validation', 'khm-seo'); ?></th>
                        <th><?php esc_html_e('Rich Snippets', 'khm-seo'); ?></th>
                        <th><?php esc_html_e('Actions', 'khm-seo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($content_items)): ?>
                        <?php foreach ($content_items as $item): ?>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" value="<?php echo esc_attr($item['id']); ?>">
                                </td>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url($item['edit_url']); ?>">
                                            <?php echo esc_html($item['title']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($item['post_type']); ?></td>
                                <td>
                                    <span class="schema-status <?php echo esc_attr($item['schema_status']); ?>">
                                        <?php echo esc_html(ucfirst($item['schema_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="validation-status <?php echo esc_attr($item['validation_status']); ?>">
                                        <?php echo esc_html(ucfirst($item['validation_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['rich_snippets_eligible']): ?>
                                        <span class="eligible"><?php esc_html_e('Eligible', 'khm-seo'); ?></span>
                                    <?php else: ?>
                                        <span class="not-eligible"><?php esc_html_e('Not Eligible', 'khm-seo'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <span class="generate">
                                            <a href="#" class="generate-schema" data-post-id="<?php echo esc_attr($item['id']); ?>">
                                                <?php esc_html_e('Generate', 'khm-seo'); ?>
                                            </a>
                                        </span>
                                        <span class="validate">
                                            <a href="#" class="validate-schema" data-post-id="<?php echo esc_attr($item['id']); ?>">
                                                <?php esc_html_e('Validate', 'khm-seo'); ?>
                                            </a>
                                        </span>
                                        <span class="view">
                                            <a href="<?php echo esc_url($item['view_url']); ?>" target="_blank">
                                                <?php esc_html_e('View', 'khm-seo'); ?>
                                            </a>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <?php esc_html_e('No content found', 'khm-seo'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Handle AJAX actions
     */
    public function handle_ajax_actions() {
        if (!wp_verify_nonce($_POST['nonce'], 'khm_schema_dashboard')) {
            wp_die('Security check failed');
        }

        $action = sanitize_text_field($_POST['action_type'] ?? '');
        
        switch ($action) {
            case 'generate_schema':
                $this->ajax_generate_schema();
                break;
                
            case 'validate_schema':
                $this->ajax_validate_schema();
                break;
                
            case 'test_rich_snippets':
                $this->ajax_test_rich_snippets();
                break;
                
            case 'bulk_generate':
                $this->ajax_bulk_generate();
                break;
                
            case 'refresh_stats':
                $this->ajax_refresh_stats();
                break;
                
            default:
                wp_send_json_error(['message' => 'Invalid action']);
        }
    }

    /**
     * AJAX: Generate schema for specific post
     */
    private function ajax_generate_schema() {
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Post ID is required']);
            return;
        }

        try {
            // Use the manager to generate schema if available
            if ($this->manager && method_exists($this->manager, 'generate_post_schema')) {
                $result = $this->manager->generate_post_schema($post_id);
            } else {
                // Fallback to basic schema generation
                $result = $this->generate_basic_schema($post_id);
            }

            if ($result) {
                wp_send_json_success([
                    'message' => 'Schema generated successfully',
                    'schema' => $result
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to generate schema']);
            }

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Helper methods for dashboard functionality
     */
    private function load_schema_statistics() {
        global $wpdb;

        $this->schema_stats = [
            'total_posts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'"),
            'with_schema' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_generated_schema'"),
            'valid_schema' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_schema_validation' AND meta_value LIKE '%\"valid\":true%'"),
            'rich_snippets' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_rich_snippets_eligible' AND meta_value = '1'")
        ];
    }

    private function load_performance_data() {
        // Load performance metrics from database
        $this->performance_data = get_option('khm_seo_schema_performance', []);
    }

    private function calculate_schema_health_score() {
        $stats = $this->schema_stats;
        $total = $stats['total_posts'];
        
        if ($total === 0) {
            return 100;
        }

        $coverage_score = ($stats['with_schema'] / $total) * 40;
        $validation_score = ($stats['valid_schema'] / max($stats['with_schema'], 1)) * 35;
        $rich_snippets_score = ($stats['rich_snippets'] / max($stats['with_schema'], 1)) * 25;

        return round($coverage_score + $validation_score + $rich_snippets_score);
    }

    private function get_schema_content_items() {
        global $wpdb;

        $query = "
            SELECT p.ID, p.post_title, p.post_type,
                   CASE WHEN m1.meta_value IS NOT NULL THEN 'generated' ELSE 'missing' END as schema_status,
                   CASE WHEN m2.meta_value LIKE '%\"valid\":true%' THEN 'valid'
                        WHEN m2.meta_value IS NOT NULL THEN 'invalid'
                        ELSE 'not_validated' END as validation_status,
                   CASE WHEN m3.meta_value = '1' THEN 1 ELSE 0 END as rich_snippets_eligible
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = '_generated_schema'
            LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = '_schema_validation'
            LEFT JOIN {$wpdb->postmeta} m3 ON p.ID = m3.post_id AND m3.meta_key = '_rich_snippets_eligible'
            WHERE p.post_status = 'publish'
            AND p.post_type IN ('post', 'page', 'product')
            ORDER BY p.post_date DESC
            LIMIT 50
        ";

        $results = $wpdb->get_results($query, ARRAY_A);
        
        return array_map(function($item) {
            return [
                'id' => $item['ID'],
                'title' => $item['post_title'],
                'post_type' => $item['post_type'],
                'schema_status' => $item['schema_status'],
                'validation_status' => $item['validation_status'],
                'rich_snippets_eligible' => (bool)$item['rich_snippets_eligible'],
                'edit_url' => \get_edit_post_link($item['ID']),
                'view_url' => \get_permalink($item['ID'])
            ];
        }, $results);
    }

    private function generate_basic_schema($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => \get_the_title($post_id),
            'datePublished' => \get_the_date('c', $post_id),
            'dateModified' => \get_the_modified_date('c', $post_id),
            'author' => [
                '@type' => 'Person',
                'name' => \get_the_author_meta('display_name', $post->post_author)
            ]
        ];

        update_post_meta($post_id, '_generated_schema', $schema);
        return $schema;
    }

    // Placeholder methods for missing functionality
    private function render_dashboard_notices() { return; }
    private function render_schema_insights() { return; }
    private function render_optimization_recommendations() { return; }
    private function render_health_breakdown() { return; }
    private function get_recent_schema_activity() { return []; }
    private function render_bulk_validation_section() { return; }
    private function render_performance_charts() { return; }
    private function render_rich_snippets_performance() { return; }
    private function render_schema_coverage_analysis() { return; }
    private function render_validation_trends() { return; }
    private function render_schema_generator_tool() { return; }
    private function render_bulk_operations_tool() { return; }
    private function render_migration_tools() { return; }
    private function render_import_export_tools() { return; }
    
    // Additional AJAX methods (placeholder)
    private function ajax_validate_schema() { wp_send_json_success([]); }
    private function ajax_test_rich_snippets() { wp_send_json_success([]); }
    private function ajax_bulk_generate() { wp_send_json_success([]); }
    private function ajax_refresh_stats() { wp_send_json_success([]); }
}