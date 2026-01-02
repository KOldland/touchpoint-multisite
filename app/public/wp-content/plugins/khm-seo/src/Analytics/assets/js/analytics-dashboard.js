/**
 * Advanced Analytics Dashboard JavaScript
 * Provides interactive functionality for the SEO analytics dashboard
 *
 * @package KHM_SEO\Analytics
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Analytics Dashboard Controller
     */
    class AnalyticsDashboard {
        constructor() {
            this.charts = {};
            this.currentPeriod = 'last_30_days';
            this.currentPostId = 0;
            this.refreshInterval = null;
            this.chartColors = khmAnalytics.chart_config.color_scheme;
            
            this.init();
        }
        
        /**
         * Initialize dashboard
         */
        init() {
            this.bindEvents();
            this.initializeCharts();
            this.loadInitialData();
            this.setupAutoRefresh();
        }
        
        /**
         * Bind event handlers
         */
        bindEvents() {
            // Period selector
            $('#period-selector').on('change', (e) => {
                this.currentPeriod = e.target.value;
                this.refreshAllData();
            });
            
            // Post selector
            $('#post-selector').on('change', (e) => {
                this.currentPostId = parseInt(e.target.value) || 0;
                this.refreshAllData();
            });
            
            // Tab navigation
            $('.nav-tab').on('click', (e) => {
                e.preventDefault();
                this.switchTab($(e.target).attr('href').substring(1));
            });
            
            // Export report button
            $('#export-report').on('click', () => {
                this.showExportModal();
            });
            
            // Refresh data button
            $('#refresh-data').on('click', () => {
                this.refreshAllData();
            });
            
            // Export modal
            $('.modal-close').on('click', () => {
                this.hideExportModal();
            });
            
            $('#export-form').on('submit', (e) => {
                e.preventDefault();
                this.exportReport();
            });
            
            // Compare periods button
            $('#compare-periods').on('click', () => {
                this.togglePeriodComparison();
            });
            
            // Trend metric selector
            $('#trend-metric').on('change', (e) => {
                this.updateTrendChart(e.target.value);
            });
        }
        
        /**
         * Initialize all charts
         */
        initializeCharts() {
            this.initPerformanceTrendChart();
            this.initKeywordPositionChart();
            this.initTrafficSourcesChart();
            this.initDeviceBrowserChart();
        }
        
        /**
         * Initialize performance trend chart
         */
        initPerformanceTrendChart() {
            const ctx = document.getElementById('performance-trend-chart');
            if (!ctx) return;
            
            this.charts.performanceTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'SEO Score',
                        data: [],
                        borderColor: this.chartColors.primary,
                        backgroundColor: this.hexToRgba(this.chartColors.primary, 0.1),
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    ...khmAnalytics.chart_config.default_options,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        /**
         * Initialize keyword position distribution chart
         */
        initKeywordPositionChart() {
            const ctx = document.getElementById('keyword-position-chart');
            if (!ctx) return;
            
            this.charts.keywordPosition = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Top 3', '4-10', '11-20', '21-50', '50+'],
                    datasets: [{
                        data: [0, 0, 0, 0, 0],
                        backgroundColor: [
                            this.chartColors.success,
                            this.chartColors.info,
                            this.chartColors.warning,
                            this.chartColors.secondary,
                            this.chartColors.danger
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    ...khmAnalytics.chart_config.default_options,
                    ...khmAnalytics.chart_config.chart_types.doughnut,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        /**
         * Initialize traffic sources chart
         */
        initTrafficSourcesChart() {
            const ctx = document.getElementById('traffic-sources-chart');
            if (!ctx) return;
            
            this.charts.trafficSources = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Sessions',
                        data: [],
                        backgroundColor: this.chartColors.primary,
                        borderColor: this.chartColors.primary,
                        borderWidth: 1
                    }]
                },
                options: {
                    ...khmAnalytics.chart_config.default_options,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
        
        /**
         * Initialize device/browser chart
         */
        initDeviceBrowserChart() {
            const ctx = document.getElementById('device-browser-chart');
            if (!ctx) return;
            
            this.charts.deviceBrowser = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['Desktop', 'Mobile', 'Tablet'],
                    datasets: [{
                        label: 'Sessions',
                        data: [0, 0, 0],
                        borderColor: this.chartColors.primary,
                        backgroundColor: this.hexToRgba(this.chartColors.primary, 0.2),
                        pointBackgroundColor: this.chartColors.primary,
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: this.chartColors.primary
                    }]
                },
                options: {
                    ...khmAnalytics.chart_config.default_options,
                    scales: {
                        r: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        /**
         * Load initial dashboard data
         */
        loadInitialData() {
            this.showLoadingState();
            
            // Load summary data
            this.loadSummaryCards();
            
            // Load chart data
            this.loadChartData('seo_score');
            this.loadKeywordData();
            this.loadTrafficData();
            
            // Load content lists
            this.loadTopContent();
            this.loadLatestInsights();
        }
        
        /**
         * Load summary card data
         */
        loadSummaryCards() {
            this.makeAjaxRequest('khm_get_analytics_data', {
                metric: 'summary',
                period: this.currentPeriod,
                post_id: this.currentPostId
            }, (response) => {
                if (response.success && response.data) {
                    this.updateSummaryCards(response.data);
                }
            });
        }
        
        /**
         * Update summary cards with data
         */
        updateSummaryCards(data) {
            // SEO Overview
            if (data.seo_overview) {
                $('#overall-seo-score').text(data.seo_overview.score + '%');
                $('#seo-score-change').text(this.formatChange(data.seo_overview.change));
                $('#content-score').text(data.seo_overview.content_score + '%');
                $('#technical-score').text(data.seo_overview.technical_score + '%');
                $('#performance-score').text(data.seo_overview.performance_score + '%');
                
                // Update progress bars
                this.updateProgressBar('content-score', data.seo_overview.content_score);
                this.updateProgressBar('technical-score', data.seo_overview.technical_score);
                this.updateProgressBar('performance-score', data.seo_overview.performance_score);
            }
            
            // Traffic Overview
            if (data.traffic_overview) {
                $('#organic-sessions').text(this.formatNumber(data.traffic_overview.sessions));
                $('#sessions-change').text(this.formatChange(data.traffic_overview.sessions_change));
                $('#organic-users').text(this.formatNumber(data.traffic_overview.users));
                $('#users-change').text(this.formatChange(data.traffic_overview.users_change));
                $('#avg-session-duration').text(this.formatDuration(data.traffic_overview.avg_duration));
                $('#duration-change').text(this.formatChange(data.traffic_overview.duration_change));
                $('#bounce-rate').text(data.traffic_overview.bounce_rate + '%');
                $('#bounce-change').text(this.formatChange(data.traffic_overview.bounce_change));
            }
            
            // Keyword Overview
            if (data.keyword_overview) {
                $('#total-keywords').text(this.formatNumber(data.keyword_overview.total));
                $('#top-10-keywords').text(this.formatNumber(data.keyword_overview.top_10));
                $('#avg-position').text(data.keyword_overview.avg_position);
                $('#keyword-improvements').text(this.formatNumber(data.keyword_overview.improvements));
            }
            
            // Conversion Overview
            if (data.conversion_overview) {
                $('#total-conversions').text(this.formatNumber(data.conversion_overview.total));
                $('#conversions-change').text(this.formatChange(data.conversion_overview.total_change));
                $('#conversion-rate').text(data.conversion_overview.rate + '%');
                $('#rate-change').text(this.formatChange(data.conversion_overview.rate_change));
                $('#total-value').text(this.formatCurrency(data.conversion_overview.value));
                $('#value-change').text(this.formatChange(data.conversion_overview.value_change));
                $('#seo-attribution').text(data.conversion_overview.seo_attribution + '%');
                $('#attribution-change').text(this.formatChange(data.conversion_overview.attribution_change));
            }
        }
        
        /**
         * Load chart data for specified metric
         */
        loadChartData(metric) {
            this.makeAjaxRequest('khm_get_analytics_data', {
                metric: metric,
                period: this.currentPeriod,
                post_id: this.currentPostId
            }, (response) => {
                if (response.success && response.data) {
                    this.updateChart('performanceTrend', response.data);
                }
            });
        }
        
        /**
         * Load keyword distribution data
         */
        loadKeywordData() {
            this.makeAjaxRequest('khm_get_analytics_data', {
                metric: 'keyword_distribution',
                period: this.currentPeriod,
                post_id: this.currentPostId
            }, (response) => {
                if (response.success && response.data) {
                    this.updateChart('keywordPosition', response.data);
                }
            });
        }
        
        /**
         * Load traffic sources data
         */
        loadTrafficData() {
            this.makeAjaxRequest('khm_get_analytics_data', {
                metric: 'traffic_sources',
                period: this.currentPeriod,
                post_id: this.currentPostId
            }, (response) => {
                if (response.success && response.data) {
                    this.updateChart('trafficSources', response.data);
                }
            });
        }
        
        /**
         * Load top performing content
         */
        loadTopContent() {
            this.makeAjaxRequest('khm_get_analytics_data', {
                metric: 'top_content',
                period: this.currentPeriod,
                post_id: this.currentPostId
            }, (response) => {
                if (response.success && response.data) {
                    this.updateTopContent(response.data);
                }
            });
        }
        
        /**
         * Load latest SEO insights
         */
        loadLatestInsights() {
            this.makeAjaxRequest('khm_get_insights', {
                limit: 5,
                post_id: this.currentPostId
            }, (response) => {
                if (response.success && response.data) {
                    this.updateInsights(response.data);
                }
            });
        }
        
        /**
         * Update chart with new data
         */
        updateChart(chartName, data) {
            if (!this.charts[chartName] || !data) return;
            
            const chart = this.charts[chartName];
            
            if (data.labels) {
                chart.data.labels = data.labels;
            }
            
            if (data.datasets) {
                chart.data.datasets = data.datasets;
            }
            
            chart.update('active');
        }
        
        /**
         * Update progress bar
         */
        updateProgressBar(metric, value) {
            const progressBar = $(`.progress-fill[data-metric="${metric}"]`);
            progressBar.css('width', value + '%');
            
            // Add color based on score
            progressBar.removeClass('score-excellent score-good score-fair score-poor');
            if (value >= 90) {
                progressBar.addClass('score-excellent');
            } else if (value >= 75) {
                progressBar.addClass('score-good');
            } else if (value >= 50) {
                progressBar.addClass('score-fair');
            } else {
                progressBar.addClass('score-poor');
            }
        }
        
        /**
         * Switch dashboard tab
         */
        switchTab(tabId) {
            // Update nav tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $(`.nav-tab[href="#${tabId}"]`).addClass('nav-tab-active');
            
            // Update tab content
            $('.tab-content').removeClass('active');
            $(`#${tabId}`).addClass('active');
            
            // Load tab-specific data
            this.loadTabData(tabId);
        }
        
        /**
         * Load data for specific tab
         */
        loadTabData(tabId) {
            switch(tabId) {
                case 'keywords':
                    this.loadKeywordsTab();
                    break;
                case 'content':
                    this.loadContentTab();
                    break;
                case 'competitors':
                    this.loadCompetitorsTab();
                    break;
                case 'insights':
                    this.loadInsightsTab();
                    break;
                case 'reports':
                    this.loadReportsTab();
                    break;
            }
        }
        
        /**
         * Show export modal
         */
        showExportModal() {
            $('#export-modal').fadeIn();
        }
        
        /**
         * Hide export modal
         */
        hideExportModal() {
            $('#export-modal').fadeOut();
        }
        
        /**
         * Export analytics report
         */
        exportReport() {
            const formData = new FormData($('#export-form')[0]);
            formData.append('action', 'khm_export_report');
            formData.append('nonce', khmAnalytics.nonce);
            formData.append('period', this.currentPeriod);
            formData.append('post_id', this.currentPostId);
            
            // Show progress
            $('#export-form').hide();
            $('#export-progress').show();
            
            $.ajax({
                url: khmAnalytics.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.downloadFile(response.data.download_url);
                        this.showNotification(khmAnalytics.strings.export_success, 'success');
                        this.hideExportModal();
                    } else {
                        this.showNotification(response.data.message || khmAnalytics.strings.export_error, 'error');
                    }
                },
                error: () => {
                    this.showNotification(khmAnalytics.strings.export_error, 'error');
                },
                complete: () => {
                    $('#export-progress').hide();
                    $('#export-form').show();
                }
            });
        }
        
        /**
         * Refresh all dashboard data
         */
        refreshAllData() {
            this.showLoadingState();
            this.loadInitialData();
        }
        
        /**
         * Setup auto-refresh
         */
        setupAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }
            
            this.refreshInterval = setInterval(() => {
                this.refreshAllData();
            }, khmAnalytics.chart_config.dashboard_refresh_rate * 1000);
        }
        
        /**
         * Make AJAX request
         */
        makeAjaxRequest(action, data, callback) {
            $.post(khmAnalytics.ajax_url, {
                action: action,
                nonce: khmAnalytics.nonce,
                ...data
            }, callback);
        }
        
        /**
         * Show loading state
         */
        showLoadingState() {
            $('.summary-card .metric-number').text('--');
            $('.metric-change').text('--');
        }
        
        /**
         * Utility functions
         */
        formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }
        
        formatChange(change) {
            const sign = change > 0 ? '+' : '';
            return sign + change + '%';
        }
        
        formatDuration(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes}m ${remainingSeconds}s`;
        }
        
        formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }
        
        hexToRgba(hex, alpha) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
        
        showNotification(message, type = 'info') {
            // WordPress-style admin notice
            const notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            $('.khm-analytics-dashboard h1').after(notice);
            
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);
        }
        
        downloadFile(url) {
            const link = document.createElement('a');
            link.href = url;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
    
    // Initialize dashboard when DOM is ready
    $(document).ready(() => {
        new AnalyticsDashboard();
    });

})(jQuery);