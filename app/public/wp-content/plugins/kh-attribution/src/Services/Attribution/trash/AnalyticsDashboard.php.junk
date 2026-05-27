<?php
/**
 * KHM Attribution Business Analytics Dashboard
 * 
 * Advanced business intelligence dashboard with P&L, funnel analysis, and forecasting
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Analytics_Dashboard {
    
    private $business_analytics;
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
    }
    
    /**
     * Add admin menu for analytics dashboard
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-attribution',
            'Business Analytics',
            'Analytics',
            'manage_options',
            'khm-business-analytics',
            array($this, 'render_analytics_dashboard')
        );
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        if (strpos($hook, 'khm-business-analytics') === false) {
            return;
        }
        
        // Chart.js for visualization
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        
        // Date picker
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), '4.6.13', true);
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13');
        
        // Custom analytics JavaScript
        wp_enqueue_script(
            'khm-analytics-dashboard',
            plugin_dir_url(__FILE__) . '../../assets/js/analytics-dashboard.js',
            array('jquery', 'chartjs', 'flatpickr'),
            '1.0.0',
            true
        );
        
        // Dashboard CSS
        wp_enqueue_style(
            'khm-analytics-dashboard',
            plugin_dir_url(__FILE__) . '../../assets/css/analytics-dashboard.css',
            array(),
            '1.0.0'
        );
        
        // Localize script for AJAX
        wp_localize_script('khm-analytics-dashboard', 'khmAnalytics', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_analytics_nonce'),
            'currency_symbol' => '$',
            'date_format' => 'Y-m-d'
        ));
    }
    
    /**
     * Render business analytics dashboard
     */
    public function render_analytics_dashboard() {
        ?>
        <div class="wrap khm-analytics-dashboard">
            <h1>📊 Business Analytics Dashboard</h1>
            
            <!-- Dashboard Controls -->
            <div class="analytics-controls">
                <div class="date-controls">
                    <label for="date-from">From:</label>
                    <input type="text" id="date-from" class="date-picker" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    
                    <label for="date-to">To:</label>
                    <input type="text" id="date-to" class="date-picker" value="<?php echo date('Y-m-d'); ?>">
                    
                    <button class="btn btn-primary" id="refresh-analytics">Refresh Analytics</button>
                </div>
                
                <div class="view-controls">
                    <button class="btn btn-tab active" data-tab="overview">Overview</button>
                    <button class="btn btn-tab" data-tab="pnl">P&L Analysis</button>
                    <button class="btn btn-tab" data-tab="funnel">Funnel Analysis</button>
                    <button class="btn btn-tab" data-tab="forecast">Forecasting</button>
                    <button class="btn btn-tab" data-tab="roi">ROI Optimization</button>
                </div>
            </div>
            
            <!-- Loading Indicator -->
            <div id="analytics-loading" class="analytics-loading" style="display: none;">
                <div class="spinner"></div>
                <span>Calculating advanced analytics...</span>
            </div>
            
            <!-- Overview Tab -->
            <div id="tab-overview" class="analytics-tab active">
                <div class="overview-grid">
                    <!-- Key Metrics -->
                    <div class="metrics-row">
                        <div class="metric-card revenue">
                            <div class="metric-value" id="overview-total-revenue">$0</div>
                            <div class="metric-label">Total Revenue</div>
                            <div class="metric-change" id="overview-revenue-change">--</div>
                        </div>
                        
                        <div class="metric-card profit">
                            <div class="metric-value" id="overview-gross-profit">$0</div>
                            <div class="metric-label">Gross Profit</div>
                            <div class="metric-change" id="overview-profit-change">--</div>
                        </div>
                        
                        <div class="metric-card margin">
                            <div class="metric-value" id="overview-profit-margin">0%</div>
                            <div class="metric-label">Profit Margin</div>
                            <div class="metric-change" id="overview-margin-change">--</div>
                        </div>
                        
                        <div class="metric-card conversion">
                            <div class="metric-value" id="overview-conversion-rate">0%</div>
                            <div class="metric-label">Conversion Rate</div>
                            <div class="metric-change" id="overview-conversion-change">--</div>
                        </div>
                    </div>
                    
                    <!-- Charts Row -->
                    <div class="charts-row">
                        <div class="chart-container">
                            <h3>📈 Revenue Trend</h3>
                            <canvas id="overview-revenue-chart"></canvas>
                        </div>
                        
                        <div class="chart-container">
                            <h3>🎯 Conversion Funnel</h3>
                            <canvas id="overview-funnel-chart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Performance Insights -->
                    <div class="insights-section">
                        <h3>🔍 Key Insights</h3>
                        <div id="overview-insights" class="insights-list">
                            <div class="insight-placeholder">Loading insights...</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- P&L Analysis Tab -->
            <div id="tab-pnl" class="analytics-tab">
                <div class="pnl-analysis">
                    <!-- P&L Summary -->
                    <div class="pnl-summary">
                        <h3>💰 Profit & Loss Summary</h3>
                        <div class="pnl-grid">
                            <div class="pnl-section revenue-section">
                                <h4>Revenue</h4>
                                <div class="pnl-item">
                                    <span class="pnl-label">Total Revenue:</span>
                                    <span class="pnl-value" id="pnl-total-revenue">$0</span>
                                </div>
                                <div class="pnl-item">
                                    <span class="pnl-label">Attributed Revenue:</span>
                                    <span class="pnl-value" id="pnl-attributed-revenue">$0</span>
                                </div>
                                <div class="pnl-item">
                                    <span class="pnl-label">Average Order Value:</span>
                                    <span class="pnl-value" id="pnl-aov">$0</span>
                                </div>
                            </div>
                            
                            <div class="pnl-section costs-section">
                                <h4>Costs</h4>
                                <div class="pnl-item">
                                    <span class="pnl-label">Commission Costs:</span>
                                    <span class="pnl-value" id="pnl-commission-costs">$0</span>
                                </div>
                                <div class="pnl-item">
                                    <span class="pnl-label">Operational Costs:</span>
                                    <span class="pnl-value" id="pnl-operational-costs">$0</span>
                                </div>
                                <div class="pnl-item">
                                    <span class="pnl-label">Technology Costs:</span>
                                    <span class="pnl-value" id="pnl-technology-costs">$0</span>
                                </div>
                            </div>
                            
                            <div class="pnl-section profit-section">
                                <h4>Profitability</h4>
                                <div class="pnl-item highlight">
                                    <span class="pnl-label">Gross Profit:</span>
                                    <span class="pnl-value" id="pnl-gross-profit">$0</span>
                                </div>
                                <div class="pnl-item highlight">
                                    <span class="pnl-label">Net Profit:</span>
                                    <span class="pnl-value" id="pnl-net-profit">$0</span>
                                </div>
                                <div class="pnl-item highlight">
                                    <span class="pnl-label">Profit Margin:</span>
                                    <span class="pnl-value" id="pnl-profit-margin">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- P&L Breakdown Charts -->
                    <div class="pnl-charts">
                        <div class="chart-container">
                            <h3>💸 Cost Breakdown</h3>
                            <canvas id="pnl-costs-chart"></canvas>
                        </div>
                        
                        <div class="chart-container">
                            <h3>📊 Profit by Channel</h3>
                            <canvas id="pnl-profit-chart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Profitability Analysis -->
                    <div class="profitability-analysis">
                        <h3>🎯 Profitability Analysis</h3>
                        <div class="profitability-grid">
                            <div class="profitability-card">
                                <h4>Break-Even Analysis</h4>
                                <div class="metric">
                                    <span class="metric-label">Break-Even Point:</span>
                                    <span class="metric-value" id="pnl-breakeven-days">-- days</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Revenue Needed:</span>
                                    <span class="metric-value" id="pnl-revenue-needed">$--</span>
                                </div>
                            </div>
                            
                            <div class="profitability-card">
                                <h4>Efficiency Metrics</h4>
                                <div class="metric">
                                    <span class="metric-label">Cost Per Conversion:</span>
                                    <span class="metric-value" id="pnl-cost-per-conversion">$--</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Revenue Per Click:</span>
                                    <span class="metric-value" id="pnl-revenue-per-click">$--</span>
                                </div>
                            </div>
                            
                            <div class="profitability-card">
                                <h4>Profitability Score</h4>
                                <div class="profitability-score">
                                    <div class="score-circle">
                                        <span class="score-value" id="pnl-profitability-score">--</span>
                                        <span class="score-label">/100</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Funnel Analysis Tab -->
            <div id="tab-funnel" class="analytics-tab">
                <div class="funnel-analysis">
                    <h3>🎯 Conversion Funnel Analysis</h3>
                    
                    <!-- Funnel Visualization -->
                    <div class="funnel-visualization">
                        <div class="funnel-chart-container">
                            <canvas id="funnel-chart"></canvas>
                        </div>
                        
                        <div class="funnel-metrics">
                            <div class="funnel-metric">
                                <span class="metric-label">Overall Conversion Rate:</span>
                                <span class="metric-value" id="funnel-overall-rate">0%</span>
                            </div>
                            <div class="funnel-metric">
                                <span class="metric-label">Average Time to Convert:</span>
                                <span class="metric-value" id="funnel-avg-time">-- hours</span>
                            </div>
                            <div class="funnel-metric">
                                <span class="metric-label">Total Funnel Revenue:</span>
                                <span class="metric-value" id="funnel-total-revenue">$0</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Funnel Steps Breakdown -->
                    <div class="funnel-steps">
                        <h4>📊 Step-by-Step Analysis</h4>
                        <div id="funnel-steps-container" class="steps-container">
                            <!-- Dynamic funnel steps will be inserted here -->
                        </div>
                    </div>
                    
                    <!-- Optimization Opportunities -->
                    <div class="funnel-optimization">
                        <h4>🔧 Optimization Opportunities</h4>
                        <div id="funnel-opportunities" class="opportunities-list">
                            <!-- Dynamic optimization opportunities will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Forecasting Tab -->
            <div id="tab-forecast" class="analytics-tab">
                <div class="forecasting-analysis">
                    <h3>🔮 Revenue & Performance Forecasting</h3>
                    
                    <!-- Forecast Controls -->
                    <div class="forecast-controls">
                        <label for="forecast-days">Forecast Period:</label>
                        <select id="forecast-days">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        
                        <label for="confidence-level">Confidence Level:</label>
                        <select id="confidence-level">
                            <option value="90">90%</option>
                            <option value="95" selected>95%</option>
                            <option value="99">99%</option>
                        </select>
                        
                        <button class="btn btn-primary" id="update-forecast">Update Forecast</button>
                    </div>
                    
                    <!-- Forecast Charts -->
                    <div class="forecast-charts">
                        <div class="chart-container">
                            <h4>📈 Revenue Forecast</h4>
                            <canvas id="forecast-revenue-chart"></canvas>
                        </div>
                        
                        <div class="chart-container">
                            <h4>🎯 Conversion Forecast</h4>
                            <canvas id="forecast-conversion-chart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Scenario Analysis -->
                    <div class="scenario-analysis">
                        <h4>📊 Scenario Analysis</h4>
                        <div class="scenario-grid">
                            <div class="scenario-card optimistic">
                                <h5>🚀 Optimistic</h5>
                                <div class="scenario-metric">
                                    <span class="metric-label">Projected Revenue:</span>
                                    <span class="metric-value" id="scenario-optimistic-revenue">$--</span>
                                </div>
                                <div class="scenario-metric">
                                    <span class="metric-label">Projected Conversions:</span>
                                    <span class="metric-value" id="scenario-optimistic-conversions">--</span>
                                </div>
                            </div>
                            
                            <div class="scenario-card realistic">
                                <h5>📊 Realistic</h5>
                                <div class="scenario-metric">
                                    <span class="metric-label">Projected Revenue:</span>
                                    <span class="metric-value" id="scenario-realistic-revenue">$--</span>
                                </div>
                                <div class="scenario-metric">
                                    <span class="metric-label">Projected Conversions:</span>
                                    <span class="metric-value" id="scenario-realistic-conversions">--</span>
                                </div>
                            </div>
                            
                            <div class="scenario-card pessimistic">
                                <h5>⚠️ Pessimistic</h5>
                                <div class="scenario-metric">
                                    <span class="metric-label">Projected Revenue:</span>
                                    <span class="metric-value" id="scenario-pessimistic-revenue">$--</span>
                                </div>
                                <div class="scenario-metric">
                                    <span class="metric-label">Projected Conversions:</span>
                                    <span class="metric-value" id="scenario-pessimistic-conversions">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Forecast Recommendations -->
                    <div class="forecast-recommendations">
                        <h4>💡 Strategic Recommendations</h4>
                        <div id="forecast-recommendations-list" class="recommendations-list">
                            <!-- Dynamic recommendations will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ROI Optimization Tab -->
            <div id="tab-roi" class="analytics-tab">
                <div class="roi-optimization">
                    <h3>🎯 ROI Optimization Analysis</h3>
                    
                    <!-- Current ROI Metrics -->
                    <div class="roi-current">
                        <h4>📊 Current ROI Performance</h4>
                        <div class="roi-metrics-grid">
                            <div class="roi-metric">
                                <span class="metric-label">Overall ROI:</span>
                                <span class="metric-value" id="roi-overall">--%</span>
                            </div>
                            <div class="roi-metric">
                                <span class="metric-label">Cost Per Acquisition:</span>
                                <span class="metric-value" id="roi-cpa">$--</span>
                            </div>
                            <div class="roi-metric">
                                <span class="metric-label">Return on Ad Spend:</span>
                                <span class="metric-value" id="roi-roas">--x</span>
                            </div>
                            <div class="roi-metric">
                                <span class="metric-label">Lifetime Value:</span>
                                <span class="metric-value" id="roi-ltv">$--</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Optimization Opportunities -->
                    <div class="roi-opportunities">
                        <h4>🚀 Optimization Opportunities</h4>
                        <div id="roi-opportunities-list" class="opportunities-grid">
                            <!-- Dynamic optimization opportunities will be inserted here -->
                        </div>
                    </div>
                    
                    <!-- Impact Estimation -->
                    <div class="roi-impact">
                        <h4>📈 Potential Impact</h4>
                        <div class="impact-chart-container">
                            <canvas id="roi-impact-chart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Action Plan -->
                    <div class="roi-action-plan">
                        <h4>✅ Recommended Action Plan</h4>
                        <div id="roi-action-plan-list" class="action-plan-list">
                            <!-- Dynamic action plan will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .khm-analytics-dashboard {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .analytics-controls {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .date-controls label {
            font-weight: bold;
            color: #374151;
        }
        
        .date-picker {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .view-controls {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background: #2563eb;
        }
        
        .btn-tab {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-tab:hover {
            background: #e5e7eb;
        }
        
        .btn-tab.active {
            background: #3b82f6;
            color: white;
        }
        
        .analytics-loading {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .analytics-tab {
            display: none;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .analytics-tab.active {
            display: block;
        }
        
        .metrics-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .metric-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .metric-card.revenue { border-left: 4px solid #22c55e; }
        .metric-card.profit { border-left: 4px solid #3b82f6; }
        .metric-card.margin { border-left: 4px solid #f59e0b; }
        .metric-card.conversion { border-left: 4px solid #8b5cf6; }
        
        .metric-value {
            font-size: 32px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .metric-label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .metric-change {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
        }
        
        .metric-change.positive {
            background: #dcfce7;
            color: #166534;
        }
        
        .metric-change.negative {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }
        
        .chart-container {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
        }
        
        .chart-container h3, .chart-container h4 {
            margin: 0 0 15px 0;
            color: #374151;
            font-size: 16px;
        }
        
        .pnl-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .pnl-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
        }
        
        .pnl-section h4 {
            margin: 0 0 15px 0;
            color: #374151;
            font-size: 16px;
            font-weight: 600;
        }
        
        .pnl-item {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .pnl-item:last-child {
            border-bottom: none;
        }
        
        .pnl-item.highlight {
            background: #fef3c7;
            padding: 12px;
            border-radius: 6px;
            border-bottom: none;
            margin: 15px 0;
            font-weight: 600;
        }
        
        .pnl-label {
            color: #6b7280;
            font-size: 14px;
        }
        
        .pnl-value {
            color: #1f2937;
            font-weight: 600;
            font-size: 14px;
        }
        
        .profitability-score {
            text-align: center;
        }
        
        .score-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: conic-gradient(#22c55e 0deg, #22c55e var(--score-angle, 0deg), #e5e7eb var(--score-angle, 0deg));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
        }
        
        .score-circle::before {
            content: '';
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            position: absolute;
        }
        
        .score-value {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            z-index: 1;
        }
        
        .score-label {
            font-size: 12px;
            color: #6b7280;
            z-index: 1;
        }
        
        .insights-section, .funnel-optimization, .forecast-recommendations, .roi-opportunities {
            margin: 30px 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
        }
        
        .insight-placeholder {
            text-align: center;
            color: #6b7280;
            font-style: italic;
            padding: 20px;
        }
        
        .scenario-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .scenario-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .scenario-card.optimistic { border-left: 4px solid #22c55e; }
        .scenario-card.realistic { border-left: 4px solid #3b82f6; }
        .scenario-card.pessimistic { border-left: 4px solid #f59e0b; }
        
        .scenario-card h5 {
            margin: 0 0 15px 0;
            color: #374151;
        }
        
        .scenario-metric {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 14px;
        }
        
        .opportunities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .opportunity-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
        }
        
        .opportunity-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .opportunity-description {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        
        .opportunity-impact {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 12px;
            background: #dcfce7;
            color: #166534;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize analytics dashboard
            initializeAnalyticsDashboard();
            
            // Set up date pickers
            $('.date-picker').flatpickr({
                dateFormat: 'Y-m-d',
                maxDate: 'today'
            });
            
            // Tab switching
            $('.btn-tab').click(function() {
                const tabId = $(this).data('tab');
                switchAnalyticsTab(tabId);
            });
            
            // Refresh analytics
            $('#refresh-analytics').click(function() {
                refreshAllAnalytics();
            });
            
            // Initial load
            refreshAllAnalytics();
        });
        
        function initializeAnalyticsDashboard() {
            initializeAnalyticsCharts();
        }
        
        function switchAnalyticsTab(tabId) {
            // Update tab buttons
            $('.btn-tab').removeClass('active');
            $(`[data-tab="${tabId}"]`).addClass('active');
            
            // Update tab content
            $('.analytics-tab').removeClass('active');
            $(`#tab-${tabId}`).addClass('active');
            
            // Load tab-specific data
            loadTabData(tabId);
        }
        
        function refreshAllAnalytics() {
            showAnalyticsLoading();
            
            const filters = {
                date_from: $('#date-from').val(),
                date_to: $('#date-to').val()
            };
            
            // Load overview data first
            loadOverviewData(filters);
        }
        
        function showAnalyticsLoading() {
            $('#analytics-loading').show();
        }
        
        function hideAnalyticsLoading() {
            $('#analytics-loading').hide();
        }
        
        function loadOverviewData(filters) {
            $.ajax({
                url: khmAnalytics.ajaxurl,
                type: 'POST',
                data: {
                    action: 'khm_analytics_pnl',
                    filters: JSON.stringify(filters),
                    nonce: khmAnalytics.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateOverviewMetrics(response.data);
                        hideAnalyticsLoading();
                    }
                },
                error: function() {
                    hideAnalyticsLoading();
                    console.error('Failed to load analytics data');
                }
            });
        }
        
        function updateOverviewMetrics(data) {
            // Update key metrics
            $('#overview-total-revenue').text('$' + formatNumber(data.revenue.total_revenue));
            $('#overview-gross-profit').text('$' + formatNumber(data.profit.gross_profit));
            $('#overview-profit-margin').text(formatPercentage(data.profit.profit_margin));
            $('#overview-conversion-rate').text(formatPercentage(data.revenue.conversion_rate));
            
            // Update P&L specific metrics
            $('#pnl-total-revenue').text('$' + formatNumber(data.revenue.total_revenue));
            $('#pnl-attributed-revenue').text('$' + formatNumber(data.revenue.attributed_revenue));
            $('#pnl-aov').text('$' + formatNumber(data.revenue.average_order_value));
            $('#pnl-commission-costs').text('$' + formatNumber(data.costs.commission_costs));
            $('#pnl-operational-costs').text('$' + formatNumber(data.costs.operational_costs));
            $('#pnl-technology-costs').text('$' + formatNumber(data.costs.technology_costs));
            $('#pnl-gross-profit').text('$' + formatNumber(data.profit.gross_profit));
            $('#pnl-net-profit').text('$' + formatNumber(data.profit.net_profit));
            $('#pnl-profit-margin').text(formatPercentage(data.profit.profit_margin));
            
            // Update profitability score
            updateProfitabilityScore(data.profit.profitability_score);
        }
        
        function updateProfitabilityScore(score) {
            $('#pnl-profitability-score').text(Math.round(score));
            
            // Update the circular progress
            const angle = (score / 100) * 360;
            $('.score-circle').css('--score-angle', angle + 'deg');
        }
        
        function loadTabData(tabId) {
            // Load specific data based on the selected tab
            switch(tabId) {
                case 'funnel':
                    loadFunnelAnalysis();
                    break;
                case 'forecast':
                    loadForecastAnalysis();
                    break;
                case 'roi':
                    loadROIAnalysis();
                    break;
            }
        }
        
        function initializeAnalyticsCharts() {
            // Initialize all charts for the analytics dashboard
            // Chart implementations would go here
        }
        
        function formatNumber(num) {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(num);
        }
        
        function formatPercentage(num) {
            return num.toFixed(1) + '%';
        }
        </script>
        <?php
    }
}

// Initialize the analytics dashboard
if (is_admin()) {
    new KHM_Attribution_Analytics_Dashboard();
}
?>