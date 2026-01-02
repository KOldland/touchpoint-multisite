/**
 * Dashboard JavaScript
 * 
 * Handles dashboard interactivity, AJAX requests, chart rendering,
 * and real-time data updates for the KHM SEO admin dashboard.
 * 
 * @package KHMSeo\Assets\JS
 * @since 2.1.0
 */

(function($) {
    'use strict';

    /**
     * Dashboard Manager Class
     */
    class DashboardManager {
        constructor() {
            this.charts = {};
            this.refreshIntervals = {};
            this.activeTab = 'overview';
            this.widgets = {};
            
            this.init();
        }

        /**
         * Initialize dashboard functionality
         */
        init() {
            this.bindEvents();
            this.initializeTabs();
            this.initializeWidgets();
            this.setupCharts();
            this.startRefreshCycles();
            
            // Initialize tooltips and popovers
            this.initializeTooltips();
            
            // Load initial data
            this.loadDashboardData();
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            const self = this;

            // Tab navigation
            $(document).on('click', '.nav-tab', function(e) {
                e.preventDefault();
                self.switchTab($(this).data('tab'));
            });

            // Widget controls
            $(document).on('click', '.widget-refresh', function(e) {
                e.preventDefault();
                const widgetId = $(this).data('widget-id');
                self.refreshWidget(widgetId);
            });

            $(document).on('click', '.widget-settings', function(e) {
                e.preventDefault();
                const widgetId = $(this).data('widget-id');
                self.openWidgetSettings(widgetId);
            });

            $(document).on('click', '.widget-hide', function(e) {
                e.preventDefault();
                const widgetId = $(this).data('widget-id');
                self.hideWidget(widgetId);
            });

            // Export functionality
            $(document).on('click', '.export-btn', function(e) {
                e.preventDefault();
                self.toggleExportOptions($(this));
            });

            $(document).on('click', '.export-option', function(e) {
                e.preventDefault();
                const format = $(this).data('format');
                self.exportData(format);
            });

            // Auto-refresh toggle
            $(document).on('change', '#auto-refresh', function() {
                if ($(this).is(':checked')) {
                    self.startRefreshCycles();
                } else {
                    self.stopRefreshCycles();
                }
            });

            // Date range picker
            $(document).on('change', '.date-range-picker', function() {
                const range = $(this).val();
                self.updateDateRange(range);
            });

            // Real-time search
            $(document).on('input', '.dashboard-search', function() {
                const query = $(this).val();
                self.filterContent(query);
            });

            // Window resize handler for charts
            $(window).on('resize', function() {
                self.resizeCharts();
            });

            // Close export dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.export-btn, .export-options').length) {
                    $('.export-options').hide();
                }
            });
        }

        /**
         * Initialize tab functionality
         */
        initializeTabs() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'overview';
            this.switchTab(tab);
        }

        /**
         * Switch active tab
         * 
         * @param {string} tabId Tab identifier
         */
        switchTab(tabId) {
            this.activeTab = tabId;
            
            // Update tab appearance
            $('.nav-tab').removeClass('nav-tab-active');
            $(`.nav-tab[data-tab="${tabId}"]`).addClass('nav-tab-active');
            
            // Show/hide tab content
            $('.tab-content').hide();
            $(`#tab-${tabId}`).show();
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', url);
            
            // Load tab-specific data
            this.loadTabData(tabId);
            
            // Initialize tab-specific charts
            this.initializeTabCharts(tabId);
        }

        /**
         * Initialize widgets
         */
        initializeWidgets() {
            $('.dashboard-widget').each((index, element) => {
                const $widget = $(element);
                const widgetId = $widget.data('widget-id');
                
                this.widgets[widgetId] = {
                    element: $widget,
                    id: widgetId,
                    lastUpdated: $widget.data('last-updated') || 0,
                    refreshInterval: this.getWidgetRefreshInterval(widgetId)
                };
            });
        }

        /**
         * Setup Chart.js charts
         */
        setupCharts() {
            this.initializePerformanceChart();
            this.initializeContentChart();
            this.initializeTechnicalChart();
            this.initializeTrafficChart();
        }

        /**
         * Initialize performance chart
         */
        initializePerformanceChart() {
            const ctx = document.getElementById('performance-chart');
            if (!ctx) return;

            this.charts.performance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'SEO Score',
                        data: [],
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Performance Score',
                        data: [],
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Score'
                            },
                            min: 0,
                            max: 100
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }

        /**
         * Initialize content distribution chart
         */
        initializeContentChart() {
            const ctx = document.getElementById('content-chart');
            if (!ctx) return;

            this.charts.content = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Excellent', 'Good', 'Fair', 'Poor'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: [
                            '#00a32a',
                            '#ffb900',
                            '#ff8c00',
                            '#d63384'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        /**
         * Initialize technical health chart
         */
        initializeTechnicalChart() {
            const ctx = document.getElementById('technical-chart');
            if (!ctx) return;

            this.charts.technical = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['Performance', 'Accessibility', 'Best Practices', 'SEO', 'Security'],
                    datasets: [{
                        label: 'Current Score',
                        data: [0, 0, 0, 0, 0],
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.2)',
                        pointBackgroundColor: '#0073aa',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#0073aa'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        r: {
                            angleLines: {
                                display: true
                            },
                            suggestedMin: 0,
                            suggestedMax: 100,
                            ticks: {
                                stepSize: 20
                            }
                        }
                    }
                }
            });
        }

        /**
         * Initialize traffic chart
         */
        initializeTrafficChart() {
            const ctx = document.getElementById('traffic-chart');
            if (!ctx) return;

            this.charts.traffic = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Organic Traffic',
                        data: [],
                        backgroundColor: '#0073aa',
                        borderColor: '#005a87',
                        borderWidth: 1
                    }, {
                        label: 'Direct Traffic',
                        data: [],
                        backgroundColor: '#00a32a',
                        borderColor: '#008a20',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Visitors'
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        /**
         * Load initial dashboard data
         */
        loadDashboardData() {
            this.showLoadingState();
            
            $.ajax({
                url: khm_seo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_seo_load_dashboard_data',
                    nonce: khm_seo_dashboard.nonce,
                    tab: this.activeTab
                },
                success: (response) => {
                    if (response.success) {
                        this.updateDashboardData(response.data);
                    } else {
                        this.showErrorState(response.data.message || 'Failed to load dashboard data');
                    }
                },
                error: () => {
                    this.showErrorState('Network error occurred');
                },
                complete: () => {
                    this.hideLoadingState();
                }
            });
        }

        /**
         * Load tab-specific data
         * 
         * @param {string} tabId Tab identifier
         */
        loadTabData(tabId) {
            $.ajax({
                url: khm_seo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_seo_load_tab_data',
                    nonce: khm_seo_dashboard.nonce,
                    tab: tabId
                },
                success: (response) => {
                    if (response.success) {
                        this.updateTabData(tabId, response.data);
                    }
                }
            });
        }

        /**
         * Update dashboard data
         * 
         * @param {Object} data Dashboard data
         */
        updateDashboardData(data) {
            // Update overview stats
            if (data.overview_stats) {
                this.updateOverviewStats(data.overview_stats);
            }

            // Update charts
            if (data.chart_data) {
                this.updateChartData(data.chart_data);
            }

            // Update widgets
            if (data.widgets) {
                this.updateWidgetData(data.widgets);
            }

            // Update last refresh time
            this.updateLastRefreshTime();
        }

        /**
         * Update overview statistics
         * 
         * @param {Object} stats Statistics data
         */
        updateOverviewStats(stats) {
            Object.keys(stats).forEach(statKey => {
                const stat = stats[statKey];
                const $statElement = $(`.stat-item[data-stat="${statKey}"]`);
                
                if ($statElement.length) {
                    $statElement.find('.stat-value').text(stat.value);
                    
                    if (stat.trend) {
                        const $trend = $statElement.find('.stat-trend');
                        $trend.removeClass('trend-up trend-down trend-neutral')
                              .addClass(`trend-${stat.trend.direction}`)
                              .text(stat.trend.text);
                    }
                }
            });
        }

        /**
         * Update chart data
         * 
         * @param {Object} chartData Chart data
         */
        updateChartData(chartData) {
            Object.keys(chartData).forEach(chartKey => {
                const chart = this.charts[chartKey];
                const data = chartData[chartKey];
                
                if (chart && data) {
                    chart.data = data;
                    chart.update('active');
                }
            });
        }

        /**
         * Refresh specific widget
         * 
         * @param {string} widgetId Widget identifier
         */
        refreshWidget(widgetId) {
            const widget = this.widgets[widgetId];
            if (!widget) return;

            const $widget = widget.element;
            const $refreshBtn = $widget.find('.widget-refresh');
            
            // Show loading state
            $refreshBtn.prop('disabled', true);
            $widget.addClass('widget-loading');
            
            $.ajax({
                url: khm_seo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_seo_refresh_widget',
                    nonce: khm_seo_dashboard.nonce,
                    widget_id: widgetId
                },
                success: (response) => {
                    if (response.success) {
                        $widget.find('.widget-content').html(response.data.content);
                        this.updateWidgetLastUpdated(widgetId);
                    } else {
                        this.showWidgetError(widgetId, response.data.message);
                    }
                },
                error: () => {
                    this.showWidgetError(widgetId, 'Network error occurred');
                },
                complete: () => {
                    $refreshBtn.prop('disabled', false);
                    $widget.removeClass('widget-loading');
                }
            });
        }

        /**
         * Export dashboard data
         * 
         * @param {string} format Export format (csv, pdf, json)
         */
        exportData(format) {
            const exportUrl = new URL(khm_seo_dashboard.ajax_url);
            exportUrl.searchParams.set('action', 'khm_seo_export_dashboard');
            exportUrl.searchParams.set('format', format);
            exportUrl.searchParams.set('tab', this.activeTab);
            exportUrl.searchParams.set('nonce', khm_seo_dashboard.nonce);
            
            // Create temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl.toString();
            link.download = `khm-seo-dashboard-${format}-${Date.now()}`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Hide export options
            $('.export-options').hide();
        }

        /**
         * Start refresh cycles for widgets
         */
        startRefreshCycles() {
            Object.keys(this.widgets).forEach(widgetId => {
                const widget = this.widgets[widgetId];
                if (widget.refreshInterval > 0) {
                    this.refreshIntervals[widgetId] = setInterval(() => {
                        this.refreshWidget(widgetId);
                    }, widget.refreshInterval * 1000);
                }
            });
        }

        /**
         * Stop refresh cycles
         */
        stopRefreshCycles() {
            Object.keys(this.refreshIntervals).forEach(widgetId => {
                clearInterval(this.refreshIntervals[widgetId]);
                delete this.refreshIntervals[widgetId];
            });
        }

        /**
         * Initialize tooltips
         */
        initializeTooltips() {
            // Initialize tooltips if available
            if (typeof $.fn.tooltip === 'function') {
                $('[data-toggle="tooltip"], [title]').tooltip();
            }
        }

        /**
         * Resize charts on window resize
         */
        resizeCharts() {
            Object.keys(this.charts).forEach(chartKey => {
                if (this.charts[chartKey]) {
                    this.charts[chartKey].resize();
                }
            });
        }

        /**
         * Show loading state
         */
        showLoadingState() {
            $('.dashboard-content').addClass('loading');
        }

        /**
         * Hide loading state
         */
        hideLoadingState() {
            $('.dashboard-content').removeClass('loading');
        }

        /**
         * Show error state
         * 
         * @param {string} message Error message
         */
        showErrorState(message) {
            console.error('Dashboard error:', message);
            // Could show a notification or error banner
        }

        /**
         * Get widget refresh interval
         * 
         * @param {string} widgetId Widget identifier
         * @returns {number} Refresh interval in seconds
         */
        getWidgetRefreshInterval(widgetId) {
            const intervals = {
                'overview_stats': 300,    // 5 minutes
                'recent_analysis': 60,    // 1 minute
                'performance_chart': 600, // 10 minutes
                'top_issues': 300,        // 5 minutes
                'content_opportunities': 600, // 10 minutes
                'technical_health': 1800  // 30 minutes
            };
            
            return intervals[widgetId] || 300; // Default 5 minutes
        }

        /**
         * Update last refresh time
         */
        updateLastRefreshTime() {
            const now = new Date();
            $('.last-updated').text(`Last updated: ${now.toLocaleTimeString()}`);
        }

        /**
         * Update widget last updated time
         * 
         * @param {string} widgetId Widget identifier
         */
        updateWidgetLastUpdated(widgetId) {
            const widget = this.widgets[widgetId];
            if (widget) {
                const now = Date.now();
                widget.lastUpdated = now;
                widget.element.find('.last-updated').text(
                    `Last updated: ${new Date(now).toLocaleTimeString()}`
                );
            }
        }

        /**
         * Show widget error
         * 
         * @param {string} widgetId Widget identifier
         * @param {string} message Error message
         */
        showWidgetError(widgetId, message) {
            const widget = this.widgets[widgetId];
            if (widget) {
                widget.element.find('.widget-content').html(
                    `<div class="error-message">
                        <span class="dashicons dashicons-warning"></span>
                        <p>${message}</p>
                    </div>`
                );
            }
        }

        /**
         * Initialize tab-specific charts
         * 
         * @param {string} tabId Tab identifier
         */
        initializeTabCharts(tabId) {
            // Initialize charts specific to the active tab
            setTimeout(() => {
                if (tabId === 'performance' && this.charts.performance) {
                    this.charts.performance.resize();
                }
                if (tabId === 'content' && this.charts.content) {
                    this.charts.content.resize();
                }
                if (tabId === 'technical' && this.charts.technical) {
                    this.charts.technical.resize();
                }
            }, 100);
        }

        /**
         * Update tab data
         * 
         * @param {string} tabId Tab identifier
         * @param {Object} data Tab data
         */
        updateTabData(tabId, data) {
            // Update tab-specific content based on data
            const $tabContent = $(`#tab-${tabId}`);
            
            if (data.html) {
                $tabContent.html(data.html);
            }
            
            if (data.chart_data && this.charts[tabId]) {
                this.charts[tabId].data = data.chart_data;
                this.charts[tabId].update();
            }
        }

        /**
         * Toggle export options
         * 
         * @param {jQuery} $btn Export button
         */
        toggleExportOptions($btn) {
            const $options = $btn.siblings('.export-options');
            $('.export-options').not($options).hide();
            $options.toggle();
        }

        /**
         * Update date range
         * 
         * @param {string} range Date range
         */
        updateDateRange(range) {
            // Reload data with new date range
            this.loadDashboardData();
        }

        /**
         * Filter content
         * 
         * @param {string} query Search query
         */
        filterContent(query) {
            // Implement content filtering logic
            console.log('Filtering content:', query);
        }

        /**
         * Open widget settings
         * 
         * @param {string} widgetId Widget identifier
         */
        openWidgetSettings(widgetId) {
            console.log('Opening settings for widget:', widgetId);
            // Implement widget settings modal
        }

        /**
         * Hide widget
         * 
         * @param {string} widgetId Widget identifier
         */
        hideWidget(widgetId) {
            const widget = this.widgets[widgetId];
            if (widget) {
                widget.element.hide();
                // Save preference
                this.saveWidgetPreference(widgetId, 'hidden', true);
            }
        }

        /**
         * Save widget preference
         * 
         * @param {string} widgetId Widget identifier
         * @param {string} key Preference key
         * @param {mixed} value Preference value
         */
        saveWidgetPreference(widgetId, key, value) {
            $.ajax({
                url: khm_seo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_seo_save_widget_preference',
                    nonce: khm_seo_dashboard.nonce,
                    widget_id: widgetId,
                    key: key,
                    value: value
                }
            });
        }
    }

    // Initialize dashboard when document is ready
    $(document).ready(function() {
        if (typeof khm_seo_dashboard !== 'undefined') {
            window.KHMSeoDashboard = new DashboardManager();
        }
    });

})(jQuery);