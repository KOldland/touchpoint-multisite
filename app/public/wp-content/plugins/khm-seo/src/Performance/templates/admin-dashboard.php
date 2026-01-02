<?php
/**
 * Performance Monitor Admin Dashboard Template
 * 
 * @package KHM_SEO\Performance
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get performance summary
$performance_monitor = new KHM_SEO\Performance\PerformanceMonitor();
$summary = $performance_monitor->get_performance_summary();
?>

<div class="wrap khm-performance-dashboard">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-performance"></span>
        <?php esc_html_e('Performance Monitor', 'khm-seo'); ?>
    </h1>
    
    <div class="page-title-action">
        <button type="button" id="run-performance-test" class="button button-primary">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Run New Test', 'khm-seo'); ?>
        </button>
    </div>
    
    <hr class="wp-header-end">
    
    <!-- Performance Summary Cards -->
    <div class="performance-summary-cards">
        
        <div class="summary-card performance-score">
            <div class="card-header">
                <h3><?php esc_html_e('Performance Score', 'khm-seo'); ?></h3>
                <span class="card-icon dashicons dashicons-chart-line"></span>
            </div>
            <div class="card-content">
                <div class="score-display">
                    <span class="score-number" id="current-score">
                        <?php echo $summary['score'] ?? '--'; ?>
                    </span>
                    <span class="score-suffix">/100</span>
                </div>
                <div class="score-status status-<?php echo strtolower(str_replace(' ', '-', $summary['status'] ?? 'unknown')); ?>">
                    <?php echo esc_html($summary['status'] ?? 'No Data'); ?>
                </div>
                <div class="score-update">
                    <?php if ($summary['last_check']): ?>
                        <?php printf(esc_html__('Last updated: %s', 'khm-seo'), esc_html($summary['last_check'])); ?>
                    <?php else: ?>
                        <?php esc_html_e('No data available', 'khm-seo'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="summary-card core-web-vitals">
            <div class="card-header">
                <h3><?php esc_html_e('Core Web Vitals', 'khm-seo'); ?></h3>
                <span class="card-icon dashicons dashicons-clock"></span>
            </div>
            <div class="card-content" id="cwv-summary">
                <div class="cwv-loading">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading Core Web Vitals...', 'khm-seo'); ?>
                </div>
            </div>
        </div>
        
        <div class="summary-card recommendations">
            <div class="card-header">
                <h3><?php esc_html_e('Active Recommendations', 'khm-seo'); ?></h3>
                <span class="card-icon dashicons dashicons-lightbulb"></span>
            </div>
            <div class="card-content">
                <div class="recommendation-count">
                    <span class="count-number"><?php echo (int) $summary['recommendations_count']; ?></span>
                    <span class="count-label"><?php esc_html_e('items to improve', 'khm-seo'); ?></span>
                </div>
                <a href="#recommendations" class="view-recommendations">
                    <?php esc_html_e('View Recommendations', 'khm-seo'); ?>
                </a>
            </div>
        </div>
        
        <div class="summary-card monitoring-status">
            <div class="card-header">
                <h3><?php esc_html_e('Monitoring Status', 'khm-seo'); ?></h3>
                <span class="card-icon dashicons dashicons-visibility"></span>
            </div>
            <div class="card-content">
                <div class="status-indicator">
                    <span class="status-dot active"></span>
                    <span class="status-text"><?php esc_html_e('Active', 'khm-seo'); ?></span>
                </div>
                <div class="monitoring-info">
                    <?php esc_html_e('Real-time monitoring enabled', 'khm-seo'); ?>
                </div>
                <a href="#settings" class="configure-monitoring">
                    <?php esc_html_e('Configure', 'khm-seo'); ?>
                </a>
            </div>
        </div>
        
    </div>
    
    <!-- Main Dashboard Content -->
    <div class="performance-dashboard-content">
        
        <!-- Navigation Tabs -->
        <nav class="nav-tab-wrapper">
            <a href="#dashboard" class="nav-tab nav-tab-active" data-tab="dashboard">
                <span class="dashicons dashicons-dashboard"></span>
                <?php esc_html_e('Dashboard', 'khm-seo'); ?>
            </a>
            <a href="#core-web-vitals" class="nav-tab" data-tab="core-web-vitals">
                <span class="dashicons dashicons-clock"></span>
                <?php esc_html_e('Core Web Vitals', 'khm-seo'); ?>
            </a>
            <a href="#performance-history" class="nav-tab" data-tab="performance-history">
                <span class="dashicons dashicons-chart-area"></span>
                <?php esc_html_e('Performance History', 'khm-seo'); ?>
            </a>
            <a href="#recommendations" class="nav-tab" data-tab="recommendations">
                <span class="dashicons dashicons-lightbulb"></span>
                <?php esc_html_e('Recommendations', 'khm-seo'); ?>
            </a>
            <a href="#settings" class="nav-tab" data-tab="settings">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e('Settings', 'khm-seo'); ?>
            </a>
        </nav>
        
        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content active">
            
            <div class="dashboard-grid">
                
                <!-- Performance Overview Chart -->
                <div class="dashboard-widget wide">
                    <div class="widget-header">
                        <h3><?php esc_html_e('Performance Score Trend', 'khm-seo'); ?></h3>
                        <div class="widget-controls">
                            <select id="chart-timeframe">
                                <option value="7"><?php esc_html_e('Last 7 days', 'khm-seo'); ?></option>
                                <option value="30" selected><?php esc_html_e('Last 30 days', 'khm-seo'); ?></option>
                                <option value="90"><?php esc_html_e('Last 3 months', 'khm-seo'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="widget-content">
                        <canvas id="performance-trend-chart" width="800" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Latest Test Results -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3><?php esc_html_e('Latest Test Results', 'khm-seo'); ?></h3>
                        <button type="button" class="button button-secondary refresh-results">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                    </div>
                    <div class="widget-content" id="latest-results">
                        <div class="results-loading">
                            <span class="spinner is-active"></span>
                            <?php esc_html_e('Loading latest results...', 'khm-seo'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Page Performance Breakdown -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3><?php esc_html_e('Page Performance', 'khm-seo'); ?></h3>
                        <select id="page-selector">
                            <option value="<?php echo esc_attr(home_url()); ?>"><?php esc_html_e('Homepage', 'khm-seo'); ?></option>
                        </select>
                    </div>
                    <div class="widget-content" id="page-performance">
                        <div class="page-loading">
                            <span class="spinner is-active"></span>
                            <?php esc_html_e('Loading page performance...', 'khm-seo'); ?>
                        </div>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- Core Web Vitals Tab -->
        <div id="core-web-vitals" class="tab-content">
            
            <div class="cwv-explanation">
                <h3><?php esc_html_e('Understanding Core Web Vitals', 'khm-seo'); ?></h3>
                <p><?php esc_html_e('Core Web Vitals are a set of real-world, user-centered metrics that quantify key aspects of the user experience.', 'khm-seo'); ?></p>
                
                <div class="cwv-metrics-info">
                    <div class="metric-info">
                        <h4>LCP <span class="metric-full">Largest Contentful Paint</span></h4>
                        <p><?php esc_html_e('Measures loading performance. Good LCP is 2.5 seconds or faster.', 'khm-seo'); ?></p>
                    </div>
                    <div class="metric-info">
                        <h4>FID <span class="metric-full">First Input Delay</span></h4>
                        <p><?php esc_html_e('Measures interactivity. Good FID is 100 milliseconds or less.', 'khm-seo'); ?></p>
                    </div>
                    <div class="metric-info">
                        <h4>CLS <span class="metric-full">Cumulative Layout Shift</span></h4>
                        <p><?php esc_html_e('Measures visual stability. Good CLS is 0.1 or less.', 'khm-seo'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="cwv-dashboard">
                
                <!-- CWV Overview Cards -->
                <div class="cwv-cards">
                    <div class="cwv-card" data-metric="lcp">
                        <div class="cwv-card-header">
                            <h4>LCP</h4>
                            <span class="cwv-score" id="lcp-score">--</span>
                        </div>
                        <div class="cwv-card-body">
                            <div class="cwv-value" id="lcp-value">Loading...</div>
                            <div class="cwv-status" id="lcp-status">--</div>
                        </div>
                    </div>
                    
                    <div class="cwv-card" data-metric="fid">
                        <div class="cwv-card-header">
                            <h4>FID</h4>
                            <span class="cwv-score" id="fid-score">--</span>
                        </div>
                        <div class="cwv-card-body">
                            <div class="cwv-value" id="fid-value">Loading...</div>
                            <div class="cwv-status" id="fid-status">--</div>
                        </div>
                    </div>
                    
                    <div class="cwv-card" data-metric="cls">
                        <div class="cwv-card-header">
                            <h4>CLS</h4>
                            <span class="cwv-score" id="cls-score">--</span>
                        </div>
                        <div class="cwv-card-body">
                            <div class="cwv-value" id="cls-value">Loading...</div>
                            <div class="cwv-status" id="cls-status">--</div>
                        </div>
                    </div>
                </div>
                
                <!-- CWV Historical Data -->
                <div class="cwv-history-chart">
                    <h3><?php esc_html_e('Core Web Vitals Trend', 'khm-seo'); ?></h3>
                    <canvas id="cwv-trend-chart" width="800" height="400"></canvas>
                </div>
                
                <!-- Real User vs Lab Data Comparison -->
                <div class="data-comparison">
                    <div class="comparison-section">
                        <h4><?php esc_html_e('Real User Monitoring (RUM)', 'khm-seo'); ?></h4>
                        <div id="rum-data">
                            <div class="rum-loading">
                                <span class="spinner is-active"></span>
                                <?php esc_html_e('Loading real user data...', 'khm-seo'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="comparison-section">
                        <h4><?php esc_html_e('Lab Data (PageSpeed Insights)', 'khm-seo'); ?></h4>
                        <div id="lab-data">
                            <div class="lab-loading">
                                <span class="spinner is-active"></span>
                                <?php esc_html_e('Loading lab data...', 'khm-seo'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- Performance History Tab -->
        <div id="performance-history" class="tab-content">
            
            <div class="history-controls">
                <div class="control-group">
                    <label for="history-url"><?php esc_html_e('URL:', 'khm-seo'); ?></label>
                    <select id="history-url">
                        <option value=""><?php esc_html_e('All Pages', 'khm-seo'); ?></option>
                        <option value="<?php echo esc_attr(home_url()); ?>" selected><?php esc_html_e('Homepage', 'khm-seo'); ?></option>
                    </select>
                </div>
                
                <div class="control-group">
                    <label for="history-days"><?php esc_html_e('Period:', 'khm-seo'); ?></label>
                    <select id="history-days">
                        <option value="7"><?php esc_html_e('Last 7 days', 'khm-seo'); ?></option>
                        <option value="30" selected><?php esc_html_e('Last 30 days', 'khm-seo'); ?></option>
                        <option value="90"><?php esc_html_e('Last 3 months', 'khm-seo'); ?></option>
                    </select>
                </div>
                
                <div class="control-group">
                    <label for="history-strategy"><?php esc_html_e('Device:', 'khm-seo'); ?></label>
                    <select id="history-strategy">
                        <option value="mobile" selected><?php esc_html_e('Mobile', 'khm-seo'); ?></option>
                        <option value="desktop"><?php esc_html_e('Desktop', 'khm-seo'); ?></option>
                    </select>
                </div>
                
                <button type="button" id="update-history" class="button button-primary">
                    <?php esc_html_e('Update Chart', 'khm-seo'); ?>
                </button>
            </div>
            
            <div class="history-charts">
                
                <!-- Performance Score History -->
                <div class="history-chart-container">
                    <h3><?php esc_html_e('Performance Score History', 'khm-seo'); ?></h3>
                    <canvas id="score-history-chart" width="800" height="300"></canvas>
                </div>
                
                <!-- Core Web Vitals History -->
                <div class="history-chart-container">
                    <h3><?php esc_html_e('Core Web Vitals History', 'khm-seo'); ?></h3>
                    <canvas id="cwv-history-chart" width="800" height="400"></canvas>
                </div>
                
            </div>
            
            <!-- Historical Data Table -->
            <div class="history-table-container">
                <h3><?php esc_html_e('Performance Data Table', 'khm-seo'); ?></h3>
                <div id="history-table">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <?php esc_html_e('Loading historical data...', 'khm-seo'); ?>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Recommendations Tab -->
        <div id="recommendations" class="tab-content">
            
            <div class="recommendations-header">
                <h3><?php esc_html_e('Performance Optimization Recommendations', 'khm-seo'); ?></h3>
                <p><?php esc_html_e('Based on your latest performance test, here are actionable recommendations to improve your site\'s speed and user experience.', 'khm-seo'); ?></p>
            </div>
            
            <div class="recommendations-content" id="recommendations-list">
                <div class="recommendations-loading">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading recommendations...', 'khm-seo'); ?>
                </div>
            </div>
            
            <!-- Optimization Quick Actions -->
            <div class="quick-actions">
                <h4><?php esc_html_e('Quick Optimizations', 'khm-seo'); ?></h4>
                <p><?php esc_html_e('These are automatic optimizations you can enable with one click.', 'khm-seo'); ?></p>
                
                <div class="quick-action-cards">
                    <div class="quick-action-card">
                        <h5><?php esc_html_e('Image Optimization', 'khm-seo'); ?></h5>
                        <p><?php esc_html_e('Automatically optimize and compress images for better loading times.', 'khm-seo'); ?></p>
                        <button type="button" class="button button-primary enable-optimization" data-feature="images">
                            <?php esc_html_e('Enable', 'khm-seo'); ?>
                        </button>
                    </div>
                    
                    <div class="quick-action-card">
                        <h5><?php esc_html_e('Resource Minification', 'khm-seo'); ?></h5>
                        <p><?php esc_html_e('Minify CSS and JavaScript files to reduce file sizes.', 'khm-seo'); ?></p>
                        <button type="button" class="button button-primary enable-optimization" data-feature="minify">
                            <?php esc_html_e('Enable', 'khm-seo'); ?>
                        </button>
                    </div>
                    
                    <div class="quick-action-card">
                        <h5><?php esc_html_e('Browser Caching', 'khm-seo'); ?></h5>
                        <p><?php esc_html_e('Set optimal cache headers for better repeat visit performance.', 'khm-seo'); ?></p>
                        <button type="button" class="button button-primary enable-optimization" data-feature="caching">
                            <?php esc_html_e('Enable', 'khm-seo'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Settings Tab -->
        <div id="settings" class="tab-content">
            
            <form id="performance-settings-form">
                
                <h3><?php esc_html_e('Performance Monitoring Settings', 'khm-seo'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Monitoring', 'khm-seo'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(get_option('khm_performance_monitoring', true)); ?>>
                                <?php esc_html_e('Enable automatic performance monitoring', 'khm-seo'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Automatically run performance checks at scheduled intervals.', 'khm-seo'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Monitoring Interval', 'khm-seo'); ?></th>
                        <td>
                            <select name="tracking_interval">
                                <option value="300" <?php selected(get_option('khm_performance_interval', 300), 300); ?>><?php esc_html_e('5 minutes', 'khm-seo'); ?></option>
                                <option value="900" <?php selected(get_option('khm_performance_interval', 300), 900); ?>><?php esc_html_e('15 minutes', 'khm-seo'); ?></option>
                                <option value="1800" <?php selected(get_option('khm_performance_interval', 300), 1800); ?>><?php esc_html_e('30 minutes', 'khm-seo'); ?></option>
                                <option value="3600" <?php selected(get_option('khm_performance_interval', 300), 3600); ?>><?php esc_html_e('1 hour', 'khm-seo'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('How often to run automatic performance checks.', 'khm-seo'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('PageSpeed Insights API Key', 'khm-seo'); ?></th>
                        <td>
                            <input type="text" name="pagespeed_api_key" value="<?php echo esc_attr(get_option('khm_pagespeed_api_key', '')); ?>" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Enter your Google PageSpeed Insights API key for detailed performance analysis.', 'khm-seo'); ?>
                                <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank"><?php esc_html_e('Get API Key', 'khm-seo'); ?></a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Real User Monitoring', 'khm-seo'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="real_user_monitoring" value="1" <?php checked(get_option('khm_real_user_monitoring', true)); ?>>
                                <?php esc_html_e('Enable Real User Monitoring (RUM)', 'khm-seo'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Collect Core Web Vitals data from actual users visiting your site.', 'khm-seo'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Performance Alerts', 'khm-seo'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_alerts" value="1" <?php checked(get_option('khm_performance_alerts', true)); ?>>
                                <?php esc_html_e('Send email alerts for poor performance', 'khm-seo'); ?>
                            </label>
                            <br><br>
                            <label>
                                <?php esc_html_e('Alert threshold (performance score):', 'khm-seo'); ?>
                                <input type="number" name="alert_threshold" value="<?php echo esc_attr(get_option('khm_performance_alert_threshold', 50)); ?>" min="0" max="100" class="small-text">
                            </label>
                            <p class="description"><?php esc_html_e('Send alerts when performance score drops below this value.', 'khm-seo'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Store History', 'khm-seo'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="store_history" value="1" <?php checked(get_option('khm_performance_history', true)); ?>>
                                <?php esc_html_e('Store performance data history', 'khm-seo'); ?>
                            </label>
                            <br><br>
                            <label>
                                <?php esc_html_e('Keep history for (days):', 'khm-seo'); ?>
                                <input type="number" name="history_days" value="<?php echo esc_attr(get_option('khm_performance_history_days', 30)); ?>" min="7" max="365" class="small-text">
                            </label>
                            <p class="description"><?php esc_html_e('How long to keep historical performance data.', 'khm-seo'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php esc_html_e('Performance Optimizations', 'khm-seo'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Image Lazy Loading', 'khm-seo'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="lazy_loading" value="1" <?php checked(get_option('khm_lazy_loading', true)); ?>>
                                <?php esc_html_e('Enable lazy loading for images', 'khm-seo'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Resource Optimization', 'khm-seo'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="defer_css" value="1" <?php checked(get_option('khm_defer_css', false)); ?>>
                                <?php esc_html_e('Defer non-critical CSS', 'khm-seo'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="defer_js" value="1" <?php checked(get_option('khm_defer_js', false)); ?>>
                                <?php esc_html_e('Defer non-critical JavaScript', 'khm-seo'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php wp_nonce_field('khm_performance_settings', 'khm_performance_nonce'); ?>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Settings', 'khm-seo'); ?>
                    </button>
                </p>
                
            </form>
            
        </div>
        
    </div>
    
</div>

<!-- Performance Test Modal -->
<div id="performance-test-modal" class="khm-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php esc_html_e('Running Performance Test', 'khm-seo'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="test-progress">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-status">
                    <p id="test-status"><?php esc_html_e('Initializing performance test...', 'khm-seo'); ?></p>
                    <div class="test-steps">
                        <div class="step active" data-step="1"><?php esc_html_e('Preparing test', 'khm-seo'); ?></div>
                        <div class="step" data-step="2"><?php esc_html_e('Running PageSpeed analysis', 'khm-seo'); ?></div>
                        <div class="step" data-step="3"><?php esc_html_e('Analyzing Core Web Vitals', 'khm-seo'); ?></div>
                        <div class="step" data-step="4"><?php esc_html_e('Generating recommendations', 'khm-seo'); ?></div>
                        <div class="step" data-step="5"><?php esc_html_e('Complete', 'khm-seo'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>