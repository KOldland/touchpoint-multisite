<?php
/**
 * Schema Validation Admin Page Template
 * 
 * @package KHM_SEO
 * @subpackage Validation
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get validation stats
$validation_stats = [
    'total_posts' => wp_count_posts()->publish,
    'validated_posts' => 0,
    'error_count' => 0,
    'warning_count' => 0,
    'average_score' => 0
];

// Get recent validation results
$recent_validations = [];
?>

<div class="wrap khm-validation-admin">
    <h1><?php esc_html_e('Schema Validation & Testing', 'khm-seo'); ?></h1>
    
    <!-- Validation Dashboard -->
    <div class="khm-validation-dashboard">
        <div class="khm-dashboard-stats">
            <div class="stat-card">
                <h3><?php echo number_format($validation_stats['total_posts']); ?></h3>
                <p><?php esc_html_e('Total Posts', 'khm-seo'); ?></p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo number_format($validation_stats['validated_posts']); ?></h3>
                <p><?php esc_html_e('Validated Posts', 'khm-seo'); ?></p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo number_format($validation_stats['error_count']); ?></h3>
                <p><?php esc_html_e('Errors Found', 'khm-seo'); ?></p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo number_format($validation_stats['warning_count']); ?></h3>
                <p><?php esc_html_e('Warnings', 'khm-seo'); ?></p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo number_format($validation_stats['average_score'], 1); ?>%</h3>
                <p><?php esc_html_e('Average Score', 'khm-seo'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Validation Tabs -->
    <div class="khm-validation-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#single-validation" class="nav-tab nav-tab-active" data-tab="single-validation">
                <?php esc_html_e('Single Validation', 'khm-seo'); ?>
            </a>
            <a href="#bulk-validation" class="nav-tab" data-tab="bulk-validation">
                <?php esc_html_e('Bulk Validation', 'khm-seo'); ?>
            </a>
            <a href="#rich-results" class="nav-tab" data-tab="rich-results">
                <?php esc_html_e('Rich Results Test', 'khm-seo'); ?>
            </a>
            <a href="#debug-tools" class="nav-tab" data-tab="debug-tools">
                <?php esc_html_e('Debug Tools', 'khm-seo'); ?>
            </a>
            <a href="#reports" class="nav-tab" data-tab="reports">
                <?php esc_html_e('Validation Reports', 'khm-seo'); ?>
            </a>
        </nav>
        
        <!-- Single Validation Tab -->
        <div id="single-validation" class="tab-content active">
            <div class="khm-validation-section">
                <h2><?php esc_html_e('Single Page/Post Validation', 'khm-seo'); ?></h2>
                
                <div class="validation-input-group">
                    <label for="validation-post-id"><?php esc_html_e('Post/Page ID:', 'khm-seo'); ?></label>
                    <input type="number" id="validation-post-id" class="regular-text" placeholder="Enter Post ID">
                    <button type="button" id="validate-single" class="button button-primary">
                        <?php esc_html_e('Validate Schema', 'khm-seo'); ?>
                    </button>
                </div>
                
                <div class="validation-input-group">
                    <label for="validation-url"><?php esc_html_e('Or enter URL:', 'khm-seo'); ?></label>
                    <input type="url" id="validation-url" class="regular-text" placeholder="https://example.com/page">
                    <button type="button" id="validate-url" class="button button-primary">
                        <?php esc_html_e('Validate URL', 'khm-seo'); ?>
                    </button>
                </div>
                
                <!-- Validation Results -->
                <div id="validation-results" class="validation-results" style="display: none;">
                    <h3><?php esc_html_e('Validation Results', 'khm-seo'); ?></h3>
                    <div class="validation-score">
                        <div class="score-circle">
                            <span class="score-number">0</span>
                            <span class="score-label"><?php esc_html_e('Score', 'khm-seo'); ?></span>
                        </div>
                    </div>
                    
                    <div class="validation-details">
                        <!-- Errors -->
                        <div class="validation-group errors" style="display: none;">
                            <h4><?php esc_html_e('Errors', 'khm-seo'); ?></h4>
                            <ul class="validation-list error-list"></ul>
                        </div>
                        
                        <!-- Warnings -->
                        <div class="validation-group warnings" style="display: none;">
                            <h4><?php esc_html_e('Warnings', 'khm-seo'); ?></h4>
                            <ul class="validation-list warning-list"></ul>
                        </div>
                        
                        <!-- Rich Results -->
                        <div class="validation-group rich-results" style="display: none;">
                            <h4><?php esc_html_e('Rich Results Eligibility', 'khm-seo'); ?></h4>
                            <ul class="validation-list rich-results-list"></ul>
                        </div>
                        
                        <!-- Suggestions -->
                        <div class="validation-group suggestions" style="display: none;">
                            <h4><?php esc_html_e('Suggestions', 'khm-seo'); ?></h4>
                            <ul class="validation-list suggestions-list"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bulk Validation Tab -->
        <div id="bulk-validation" class="tab-content">
            <div class="khm-validation-section">
                <h2><?php esc_html_e('Bulk Validation', 'khm-seo'); ?></h2>
                
                <div class="bulk-validation-controls">
                    <div class="validation-input-group">
                        <label><?php esc_html_e('Select Posts to Validate:', 'khm-seo'); ?></label>
                        <select id="bulk-post-type" class="regular-text">
                            <option value="post"><?php esc_html_e('Posts', 'khm-seo'); ?></option>
                            <option value="page"><?php esc_html_e('Pages', 'khm-seo'); ?></option>
                            <option value="product"><?php esc_html_e('Products', 'khm-seo'); ?></option>
                            <option value="all"><?php esc_html_e('All Types', 'khm-seo'); ?></option>
                        </select>
                    </div>
                    
                    <div class="validation-input-group">
                        <label><?php esc_html_e('Number of posts:', 'khm-seo'); ?></label>
                        <input type="number" id="bulk-limit" class="small-text" value="50" min="1" max="500">
                    </div>
                    
                    <button type="button" id="start-bulk-validation" class="button button-primary">
                        <?php esc_html_e('Start Bulk Validation', 'khm-seo'); ?>
                    </button>
                </div>
                
                <!-- Bulk Progress -->
                <div id="bulk-progress" class="bulk-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%;"></div>
                    </div>
                    <div class="progress-text">
                        <span class="current-progress">0</span> / <span class="total-progress">0</span>
                        <?php esc_html_e('posts validated', 'khm-seo'); ?>
                    </div>
                </div>
                
                <!-- Bulk Results -->
                <div id="bulk-results" class="bulk-results">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Post', 'khm-seo'); ?></th>
                                <th><?php esc_html_e('Type', 'khm-seo'); ?></th>
                                <th><?php esc_html_e('Score', 'khm-seo'); ?></th>
                                <th><?php esc_html_e('Status', 'khm-seo'); ?></th>
                                <th><?php esc_html_e('Issues', 'khm-seo'); ?></th>
                                <th><?php esc_html_e('Actions', 'khm-seo'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="bulk-results-tbody">
                            <!-- Results will be populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Rich Results Test Tab -->
        <div id="rich-results" class="tab-content">
            <div class="khm-validation-section">
                <h2><?php esc_html_e('Google Rich Results Test', 'khm-seo'); ?></h2>
                
                <div class="validation-input-group">
                    <label for="rich-results-url"><?php esc_html_e('Test URL:', 'khm-seo'); ?></label>
                    <input type="url" id="rich-results-url" class="regular-text" placeholder="https://example.com/page">
                    <button type="button" id="test-rich-results" class="button button-primary">
                        <?php esc_html_e('Test Rich Results', 'khm-seo'); ?>
                    </button>
                </div>
                
                <div class="rich-results-info">
                    <p><?php esc_html_e('This tool tests your pages for Rich Results eligibility similar to Google\'s Rich Results Test. It analyzes structured data markup and provides feedback on potential rich snippet opportunities.', 'khm-seo'); ?></p>
                </div>
                
                <!-- Rich Results -->
                <div id="rich-results-output" class="rich-results-output" style="display: none;">
                    <h3><?php esc_html_e('Rich Results Test Results', 'khm-seo'); ?></h3>
                    <div class="rich-results-content">
                        <!-- Results populated via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Debug Tools Tab -->
        <div id="debug-tools" class="tab-content">
            <div class="khm-validation-section">
                <h2><?php esc_html_e('Schema Debug Tools', 'khm-seo'); ?></h2>
                
                <div class="debug-controls">
                    <div class="validation-input-group">
                        <label for="debug-post-id"><?php esc_html_e('Post/Page ID to debug:', 'khm-seo'); ?></label>
                        <input type="number" id="debug-post-id" class="regular-text" placeholder="Enter Post ID">
                        <button type="button" id="start-debug" class="button button-primary">
                            <?php esc_html_e('Debug Schema', 'khm-seo'); ?>
                        </button>
                    </div>
                    
                    <div class="debug-options">
                        <label>
                            <input type="checkbox" id="debug-hooks" checked>
                            <?php esc_html_e('Include WordPress hooks information', 'khm-seo'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" id="debug-settings" checked>
                            <?php esc_html_e('Include plugin settings', 'khm-seo'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" id="debug-meta" checked>
                            <?php esc_html_e('Include post meta data', 'khm-seo'); ?>
                        </label>
                    </div>
                </div>
                
                <!-- Debug Output -->
                <div id="debug-output" class="debug-output" style="display: none;">
                    <h3><?php esc_html_e('Debug Information', 'khm-seo'); ?></h3>
                    <div class="debug-content">
                        <pre id="debug-data"></pre>
                    </div>
                    
                    <div class="debug-actions">
                        <button type="button" id="copy-debug" class="button">
                            <?php esc_html_e('Copy Debug Info', 'khm-seo'); ?>
                        </button>
                        <button type="button" id="download-debug" class="button">
                            <?php esc_html_e('Download Debug Report', 'khm-seo'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reports Tab -->
        <div id="reports" class="tab-content">
            <div class="khm-validation-section">
                <h2><?php esc_html_e('Validation Reports', 'khm-seo'); ?></h2>
                
                <div class="reports-overview">
                    <div class="report-filters">
                        <select id="report-timeframe">
                            <option value="7"><?php esc_html_e('Last 7 days', 'khm-seo'); ?></option>
                            <option value="30" selected><?php esc_html_e('Last 30 days', 'khm-seo'); ?></option>
                            <option value="90"><?php esc_html_e('Last 90 days', 'khm-seo'); ?></option>
                        </select>
                        
                        <button type="button" id="generate-report" class="button button-primary">
                            <?php esc_html_e('Generate Report', 'khm-seo'); ?>
                        </button>
                        
                        <button type="button" id="export-report" class="button">
                            <?php esc_html_e('Export CSV', 'khm-seo'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Reports Chart -->
                <div class="reports-chart">
                    <canvas id="validation-chart" width="400" height="200"></canvas>
                </div>
                
                <!-- Most Common Issues -->
                <div class="common-issues">
                    <h3><?php esc_html_e('Most Common Schema Issues', 'khm-seo'); ?></h3>
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Issue Type', 'khm-seo'); ?></th>
                                <th><?php esc_html_e('Occurrences', 'khm-seo'); ?></th>
                                <th><?php esc_html_e('Severity', 'khm-seo'); ?></th>
                                <th><?php esc_html_e('Resolution', 'khm-seo'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="common-issues-tbody">
                            <!-- Populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p class="loading-text"><?php esc_html_e('Processing...', 'khm-seo'); ?></p>
        </div>
    </div>
</div>

<!-- Templates for JavaScript -->
<script type="text/template" id="validation-error-template">
    <li class="validation-item error">
        <strong class="item-type"><%- type %></strong>
        <span class="item-message"><%- message %></span>
        <span class="item-severity severity-<%- severity %>"><%- severity %></span>
    </li>
</script>

<script type="text/template" id="validation-warning-template">
    <li class="validation-item warning">
        <strong class="item-type"><%- type %></strong>
        <span class="item-message"><%- message %></span>
        <span class="item-severity severity-<%- severity %>"><%- severity %></span>
    </li>
</script>

<script type="text/template" id="rich-result-template">
    <li class="validation-item rich-result <%- eligible ? 'eligible' : 'not-eligible' %>">
        <strong class="item-type"><%- type %></strong>
        <span class="item-description"><%- description %></span>
        <span class="item-status"><%- eligible ? '✓ Eligible' : '✗ Not Eligible' %></span>
    </li>
</script>

<script type="text/template" id="bulk-result-row-template">
    <tr class="bulk-result-row">
        <td>
            <strong><%- post_title %></strong>
            <div class="row-actions">
                <span><a href="post.php?post=<%- post_id %>&action=edit">Edit</a> |</span>
                <span><a href="<?php echo get_permalink('<%- post_id %>'); ?>" target="_blank">View</a></span>
            </div>
        </td>
        <td><%- post_type %></td>
        <td>
            <span class="score-badge score-<%- overall_score >= 80 ? 'good' : overall_score >= 60 ? 'warning' : 'error' %>">
                <%- overall_score.toFixed(1) %>%
            </span>
        </td>
        <td>
            <span class="status-badge status-<%- has_errors ? 'error' : 'success' %>">
                <%- has_errors ? 'Issues Found' : 'Valid' %>
            </span>
        </td>
        <td><%- error_count %> errors, <%- warning_count %> warnings</td>
        <td>
            <button type="button" class="button button-small view-details" data-post-id="<%- post_id %>">
                View Details
            </button>
        </td>
    </tr>
</script>