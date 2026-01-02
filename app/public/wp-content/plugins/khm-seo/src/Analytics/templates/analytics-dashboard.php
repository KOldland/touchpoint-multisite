<?php
/**
 * Analytics Dashboard Template
 * Advanced SEO Analytics and Reporting Interface
 *
 * @package KHM_SEO\Analytics
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current analytics data
$current_period = $_GET['period'] ?? 'last_30_days';
$selected_post = $_GET['post_id'] ?? 0;

// Get plugin instance for data
$plugin = \KHM_SEO\Core\Plugin::instance();
?>

<div class="wrap khm-analytics-dashboard">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-area"></span>
        <?php _e('SEO Analytics Dashboard', 'khm-seo'); ?>
    </h1>
    
    <div class="page-title-action">
        <button type="button" class="button button-primary" id="export-report">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Export Report', 'khm-seo'); ?>
        </button>
        <button type="button" class="button" id="refresh-data">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh Data', 'khm-seo'); ?>
        </button>
    </div>
    
    <hr class="wp-header-end">
    
    <!-- Dashboard Controls -->
    <div class="analytics-controls">
        <div class="control-group">
            <label for="period-selector"><?php _e('Time Period:', 'khm-seo'); ?></label>
            <select id="period-selector" name="period">
                <option value="today"><?php _e('Today', 'khm-seo'); ?></option>
                <option value="last_7_days"><?php _e('Last 7 Days', 'khm-seo'); ?></option>
                <option value="last_30_days" selected><?php _e('Last 30 Days', 'khm-seo'); ?></option>
                <option value="last_90_days"><?php _e('Last 90 Days', 'khm-seo'); ?></option>
                <option value="this_month"><?php _e('This Month', 'khm-seo'); ?></option>
                <option value="last_month"><?php _e('Last Month', 'khm-seo'); ?></option>
                <option value="this_year"><?php _e('This Year', 'khm-seo'); ?></option>
                <option value="custom"><?php _e('Custom Range', 'khm-seo'); ?></option>
            </select>
        </div>
        
        <div class="control-group">
            <label for="post-selector"><?php _e('Content Filter:', 'khm-seo'); ?></label>
            <select id="post-selector" name="post_id">
                <option value="0"><?php _e('All Content', 'khm-seo'); ?></option>
                <option value="posts"><?php _e('Blog Posts', 'khm-seo'); ?></option>
                <option value="pages"><?php _e('Pages', 'khm-seo'); ?></option>
                <!-- Specific posts will be loaded via AJAX -->
            </select>
        </div>
        
        <div class="control-group">
            <button type="button" class="button" id="compare-periods">
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Compare Periods', 'khm-seo'); ?>
            </button>
        </div>
    </div>
    
    <!-- Analytics Summary Cards -->
    <div class="analytics-summary-cards">
        <div class="summary-card seo-overview">
            <div class="card-header">
                <h3><span class="dashicons dashicons-chart-pie"></span> <?php _e('SEO Overview', 'khm-seo'); ?></h3>
            </div>
            <div class="card-content">
                <div class="metric-display">
                    <div class="metric-number" id="overall-seo-score">--</div>
                    <div class="metric-label"><?php _e('Overall SEO Score', 'khm-seo'); ?></div>
                    <div class="metric-change" id="seo-score-change">--</div>
                </div>
                <div class="score-breakdown">
                    <div class="breakdown-item">
                        <span class="breakdown-label"><?php _e('Content', 'khm-seo'); ?></span>
                        <div class="progress-bar">
                            <div class="progress-fill" data-metric="content-score"></div>
                        </div>
                        <span class="breakdown-value" id="content-score">--</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label"><?php _e('Technical', 'khm-seo'); ?></span>
                        <div class="progress-bar">
                            <div class="progress-fill" data-metric="technical-score"></div>
                        </div>
                        <span class="breakdown-value" id="technical-score">--</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label"><?php _e('Performance', 'khm-seo'); ?></span>
                        <div class="progress-bar">
                            <div class="progress-fill" data-metric="performance-score"></div>
                        </div>
                        <span class="breakdown-value" id="performance-score">--</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="summary-card traffic-overview">
            <div class="card-header">
                <h3><span class="dashicons dashicons-chart-area"></span> <?php _e('Traffic Overview', 'khm-seo'); ?></h3>
            </div>
            <div class="card-content">
                <div class="traffic-metrics">
                    <div class="traffic-metric">
                        <div class="metric-number" id="organic-sessions">--</div>
                        <div class="metric-label"><?php _e('Organic Sessions', 'khm-seo'); ?></div>
                        <div class="metric-change" id="sessions-change">--</div>
                    </div>
                    <div class="traffic-metric">
                        <div class="metric-number" id="organic-users">--</div>
                        <div class="metric-label"><?php _e('Organic Users', 'khm-seo'); ?></div>
                        <div class="metric-change" id="users-change">--</div>
                    </div>
                    <div class="traffic-metric">
                        <div class="metric-number" id="avg-session-duration">--</div>
                        <div class="metric-label"><?php _e('Avg. Session Duration', 'khm-seo'); ?></div>
                        <div class="metric-change" id="duration-change">--</div>
                    </div>
                    <div class="traffic-metric">
                        <div class="metric-number" id="bounce-rate">--</div>
                        <div class="metric-label"><?php _e('Bounce Rate', 'khm-seo'); ?></div>
                        <div class="metric-change" id="bounce-change">--</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="summary-card keyword-overview">
            <div class="card-header">
                <h3><span class="dashicons dashicons-search"></span> <?php _e('Keyword Performance', 'khm-seo'); ?></h3>
            </div>
            <div class="card-content">
                <div class="keyword-metrics">
                    <div class="keyword-metric">
                        <div class="metric-number" id="total-keywords">--</div>
                        <div class="metric-label"><?php _e('Total Keywords', 'khm-seo'); ?></div>
                    </div>
                    <div class="keyword-metric">
                        <div class="metric-number" id="top-10-keywords">--</div>
                        <div class="metric-label"><?php _e('Top 10 Rankings', 'khm-seo'); ?></div>
                    </div>
                    <div class="keyword-metric">
                        <div class="metric-number" id="avg-position">--</div>
                        <div class="metric-label"><?php _e('Avg. Position', 'khm-seo'); ?></div>
                    </div>
                    <div class="keyword-metric">
                        <div class="metric-number" id="keyword-improvements">--</div>
                        <div class="metric-label"><?php _e('Improvements', 'khm-seo'); ?></div>
                    </div>
                </div>
                <div class="keyword-distribution">
                    <canvas id="keyword-position-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="summary-card conversion-overview">
            <div class="card-header">
                <h3><span class="dashicons dashicons-cart"></span> <?php _e('Conversion Tracking', 'khm-seo'); ?></h3>
            </div>
            <div class="card-content">
                <div class="conversion-metrics">
                    <div class="conversion-metric">
                        <div class="metric-number" id="total-conversions">--</div>
                        <div class="metric-label"><?php _e('Total Conversions', 'khm-seo'); ?></div>
                        <div class="metric-change" id="conversions-change">--</div>
                    </div>
                    <div class="conversion-metric">
                        <div class="metric-number" id="conversion-rate">--</div>
                        <div class="metric-label"><?php _e('Conversion Rate', 'khm-seo'); ?></div>
                        <div class="metric-change" id="rate-change">--</div>
                    </div>
                    <div class="conversion-metric">
                        <div class="metric-number" id="total-value">--</div>
                        <div class="metric-label"><?php _e('Total Value', 'khm-seo'); ?></div>
                        <div class="metric-change" id="value-change">--</div>
                    </div>
                    <div class="conversion-metric">
                        <div class="metric-number" id="seo-attribution">--</div>
                        <div class="metric-label"><?php _e('SEO Attribution', 'khm-seo'); ?></div>
                        <div class="metric-change" id="attribution-change">--</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Analytics Dashboard -->
    <div class="analytics-dashboard-content">
        <div class="dashboard-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#overview" class="nav-tab nav-tab-active">
                    <span class="dashicons dashicons-dashboard"></span>
                    <?php _e('Overview', 'khm-seo'); ?>
                </a>
                <a href="#traffic" class="nav-tab">
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php _e('Traffic Analysis', 'khm-seo'); ?>
                </a>
                <a href="#keywords" class="nav-tab">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Keyword Rankings', 'khm-seo'); ?>
                </a>
                <a href="#content" class="nav-tab">
                    <span class="dashicons dashicons-edit-page"></span>
                    <?php _e('Content Performance', 'khm-seo'); ?>
                </a>
                <a href="#competitors" class="nav-tab">
                    <span class="dashicons dashicons-groups"></span>
                    <?php _e('Competitor Analysis', 'khm-seo'); ?>
                </a>
                <a href="#insights" class="nav-tab">
                    <span class="dashicons dashicons-lightbulb"></span>
                    <?php _e('SEO Insights', 'khm-seo'); ?>
                </a>
                <a href="#reports" class="nav-tab">
                    <span class="dashicons dashicons-media-document"></span>
                    <?php _e('Reports', 'khm-seo'); ?>
                </a>
            </nav>
            
            <!-- Overview Tab -->
            <div id="overview" class="tab-content active">
                <div class="dashboard-grid">
                    <div class="dashboard-widget wide">
                        <div class="widget-header">
                            <h3><?php _e('SEO Performance Trend', 'khm-seo'); ?></h3>
                            <div class="widget-controls">
                                <select id="trend-metric">
                                    <option value="seo_score"><?php _e('SEO Score', 'khm-seo'); ?></option>
                                    <option value="organic_traffic"><?php _e('Organic Traffic', 'khm-seo'); ?></option>
                                    <option value="keyword_rankings"><?php _e('Keyword Rankings', 'khm-seo'); ?></option>
                                    <option value="conversions"><?php _e('Conversions', 'khm-seo'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="widget-content">
                            <canvas id="performance-trend-chart" width="800" height="400"></canvas>
                        </div>
                    </div>
                    
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><?php _e('Top Performing Content', 'khm-seo'); ?></h3>
                        </div>
                        <div class="widget-content">
                            <div class="top-content-loading">
                                <span class="spinner is-active"></span>
                                <?php _e('Loading top content...', 'khm-seo'); ?>
                            </div>
                            <div id="top-content-list" class="content-list"></div>
                        </div>
                    </div>
                    
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><?php _e('Latest SEO Insights', 'khm-seo'); ?></h3>
                        </div>
                        <div class="widget-content">
                            <div class="insights-loading">
                                <span class="spinner is-active"></span>
                                <?php _e('Loading insights...', 'khm-seo'); ?>
                            </div>
                            <div id="latest-insights" class="insights-list"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Traffic Analysis Tab -->
            <div id="traffic" class="tab-content">
                <div class="dashboard-grid">
                    <div class="dashboard-widget wide">
                        <div class="widget-header">
                            <h3><?php _e('Traffic Sources Breakdown', 'khm-seo'); ?></h3>
                        </div>
                        <div class="widget-content">
                            <canvas id="traffic-sources-chart" width="800" height="400"></canvas>
                        </div>
                    </div>
                    
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><?php _e('Geographic Distribution', 'khm-seo'); ?></h3>
                        </div>
                        <div class="widget-content">
                            <div id="geographic-data" class="geographic-list"></div>
                        </div>
                    </div>
                    
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><?php _e('Device & Browser Stats', 'khm-seo'); ?></h3>
                        </div>
                        <div class="widget-content">
                            <canvas id="device-browser-chart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional tabs content will be loaded via AJAX -->
            <div id="keywords" class="tab-content">
                <div class="tab-loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading keyword data...', 'khm-seo'); ?>
                </div>
            </div>
            
            <div id="content" class="tab-content">
                <div class="tab-loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading content analysis...', 'khm-seo'); ?>
                </div>
            </div>
            
            <div id="competitors" class="tab-content">
                <div class="tab-loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading competitor data...', 'khm-seo'); ?>
                </div>
            </div>
            
            <div id="insights" class="tab-content">
                <div class="tab-loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading SEO insights...', 'khm-seo'); ?>
                </div>
            </div>
            
            <div id="reports" class="tab-content">
                <div class="tab-loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading reports...', 'khm-seo'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="export-modal" class="khm-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Export Analytics Report', 'khm-seo'); ?></h3>
            <button type="button" class="modal-close">×</button>
        </div>
        <div class="modal-body">
            <form id="export-form">
                <div class="form-group">
                    <label for="export-format"><?php _e('Export Format:', 'khm-seo'); ?></label>
                    <select id="export-format" name="format">
                        <option value="pdf"><?php _e('PDF Report', 'khm-seo'); ?></option>
                        <option value="excel"><?php _e('Excel Spreadsheet', 'khm-seo'); ?></option>
                        <option value="csv"><?php _e('CSV Data', 'khm-seo'); ?></option>
                        <option value="json"><?php _e('JSON Data', 'khm-seo'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="export-period"><?php _e('Time Period:', 'khm-seo'); ?></label>
                    <select id="export-period" name="period">
                        <option value="last_7_days"><?php _e('Last 7 Days', 'khm-seo'); ?></option>
                        <option value="last_30_days"><?php _e('Last 30 Days', 'khm-seo'); ?></option>
                        <option value="last_90_days"><?php _e('Last 90 Days', 'khm-seo'); ?></option>
                        <option value="this_month"><?php _e('This Month', 'khm-seo'); ?></option>
                        <option value="last_month"><?php _e('Last Month', 'khm-seo'); ?></option>
                        <option value="this_year"><?php _e('This Year', 'khm-seo'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="include_charts" checked>
                        <?php _e('Include Charts and Visualizations', 'khm-seo'); ?>
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="include_insights" checked>
                        <?php _e('Include SEO Insights and Recommendations', 'khm-seo'); ?>
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Generate Report', 'khm-seo'); ?>
                    </button>
                    <button type="button" class="button modal-close">
                        <?php _e('Cancel', 'khm-seo'); ?>
                    </button>
                </div>
            </form>
            
            <div id="export-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p class="progress-text"><?php _e('Generating report...', 'khm-seo'); ?></p>
            </div>
        </div>
    </div>
</div>