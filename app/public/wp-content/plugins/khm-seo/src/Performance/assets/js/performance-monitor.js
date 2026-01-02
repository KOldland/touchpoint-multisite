/**
 * Performance Monitor Dashboard JavaScript
 * Handles interactive performance monitoring dashboard functionality
 * 
 * @package KHM_SEO\Performance
 */

(function($) {
    'use strict';
    
    // Main Performance Dashboard Object
    window.KHMPerformanceDashboard = {
        
        // Configuration from PHP
        config: khmPerformance || {},
        
        // Chart instances
        charts: {},
        
        // Dashboard state
        state: {
            activeTab: 'dashboard',
            currentUrl: '',
            currentTimeframe: '30',
            currentStrategy: 'mobile',
            isTestRunning: false,
            testProgress: 0
        },
        
        /**
         * Initialize the dashboard
         */
        init: function() {
            this.bindEvents();
            this.initializeTabs();
            this.loadInitialData();
            this.setupCharts();
            
            console.log('KHM Performance Dashboard initialized');
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                self.switchTab(tab);
            });
            
            // Run performance test
            $('#run-performance-test, .refresh-results').on('click', function(e) {
                e.preventDefault();
                self.runPerformanceTest();
            });
            
            // Chart timeframe changes
            $('#chart-timeframe, #history-days').on('change', function() {
                self.state.currentTimeframe = $(this).val();
                self.updateCharts();
            });
            
            // Strategy changes
            $('#history-strategy').on('change', function() {
                self.state.currentStrategy = $(this).val();
                self.updateCharts();
            });
            
            // URL selection changes
            $('#page-selector, #history-url').on('change', function() {
                self.state.currentUrl = $(this).val();
                self.updateCharts();
            });
            
            // Update history button
            $('#update-history').on('click', function() {
                self.updateHistoryCharts();
            });
            
            // Settings form
            $('#performance-settings-form').on('submit', function(e) {
                e.preventDefault();
                self.saveSettings();
            });
            
            // Quick optimization actions
            $('.enable-optimization').on('click', function() {
                const feature = $(this).data('feature');
                self.enableOptimization(feature);
            });
            
            // Modal close
            $('.modal-close, .modal-overlay').on('click', function() {
                self.closeModal();
            });
        },
        
        /**
         * Initialize tab system
         */
        initializeTabs: function() {
            $('.tab-content').hide();
            $('.tab-content.active').show();
        },
        
        /**
         * Switch between dashboard tabs
         */
        switchTab: function(tab) {
            // Update navigation
            $('.nav-tab').removeClass('nav-tab-active');
            $(`.nav-tab[data-tab="${tab}"]`).addClass('nav-tab-active');
            
            // Update content
            $('.tab-content').removeClass('active').hide();
            $(`#${tab}`).addClass('active').show();
            
            this.state.activeTab = tab;
            
            // Load tab-specific data
            this.loadTabData(tab);
        },
        
        /**
         * Load initial dashboard data
         */
        loadInitialData: function() {
            this.state.currentUrl = this.config.siteUrl;
            
            // Load Core Web Vitals summary
            this.loadCWVSummary();
            
            // Load latest results
            this.loadLatestResults();
            
            // Load page performance
            this.loadPagePerformance();
        },
        
        /**
         * Load tab-specific data
         */
        loadTabData: function(tab) {
            switch (tab) {
                case 'core-web-vitals':
                    this.loadCWVData();
                    break;
                case 'performance-history':
                    this.loadHistoryData();
                    break;
                case 'recommendations':
                    this.loadRecommendations();
                    break;
            }
        },
        
        /**
         * Setup chart instances
         */
        setupCharts: function() {
            // Performance trend chart
            this.charts.performanceTrend = this.createChart('performance-trend-chart', 'line', {
                title: 'Performance Score Trend',
                yMin: 0,
                yMax: 100
            });
            
            // CWV trend chart
            this.charts.cwvTrend = this.createChart('cwv-trend-chart', 'line', {
                title: 'Core Web Vitals Trend',
                multiAxis: true
            });
            
            // Score history chart
            this.charts.scoreHistory = this.createChart('score-history-chart', 'line', {
                title: 'Performance Score History',
                yMin: 0,
                yMax: 100
            });
            
            // CWV history chart
            this.charts.cwvHistory = this.createChart('cwv-history-chart', 'line', {
                title: 'Core Web Vitals History',
                multiAxis: true
            });
        },
        
        /**
         * Create a chart instance
         */
        createChart: function(canvasId, type, options = {}) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                console.warn(`Canvas element ${canvasId} not found`);
                return null;
            }
            
            const ctx = canvas.getContext('2d');
            
            const config = {
                type: type,
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            min: options.yMin || 0,
                            max: options.yMax || undefined
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: options.title || ''
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            };
            
            // Multi-axis configuration for CWV charts
            if (options.multiAxis) {
                config.options.scales = {
                    x: {
                        display: true
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Milliseconds'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'CLS Score'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                };
            }
            
            return new Chart(ctx, config);
        },
        
        /**
         * Load Core Web Vitals summary
         */
        loadCWVSummary: function() {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_get_cwv_data',
                    nonce: this.config.nonce,
                    url: this.state.currentUrl
                }
            })
            .done((response) => {
                if (response.success) {
                    this.renderCWVSummary(response.data);
                } else {
                    this.showError('Error loading Core Web Vitals data: ' + response.data);
                }
            })
            .fail(() => {
                this.showError('Network error loading Core Web Vitals data');
            });
        },
        
        /**
         * Render CWV summary
         */
        renderCWVSummary: function(data) {
            const container = $('#cwv-summary');
            
            if (!data.rum && !data.pagespeed) {
                container.html('<p>No Core Web Vitals data available yet.</p>');
                return;
            }
            
            let html = '<div class="cwv-summary-metrics">';
            
            // Use RUM data if available, fallback to PageSpeed
            const metrics = data.rum || data.pagespeed;
            
            if (metrics.lcp) {
                const lcpStatus = this.getCWVStatus('lcp', metrics.lcp);
                html += `
                    <div class="cwv-metric ${lcpStatus}">
                        <span class="metric-label">LCP</span>
                        <span class="metric-value">${this.formatTime(metrics.lcp)}</span>
                        <span class="metric-status">${lcpStatus}</span>
                    </div>
                `;
            }
            
            if (metrics.fid) {
                const fidStatus = this.getCWVStatus('fid', metrics.fid);
                html += `
                    <div class="cwv-metric ${fidStatus}">
                        <span class="metric-label">FID</span>
                        <span class="metric-value">${this.formatTime(metrics.fid)}</span>
                        <span class="metric-status">${fidStatus}</span>
                    </div>
                `;
            }
            
            if (metrics.cls) {
                const clsStatus = this.getCWVStatus('cls', metrics.cls);
                html += `
                    <div class="cwv-metric ${clsStatus}">
                        <span class="metric-label">CLS</span>
                        <span class="metric-value">${metrics.cls.toFixed(3)}</span>
                        <span class="metric-status">${clsStatus}</span>
                    </div>
                `;
            }
            
            html += '</div>';
            
            if (data.rum && data.pagespeed) {
                html += `
                    <div class="cwv-data-sources">
                        <span class="data-source real-user" title="Real User Monitoring data">RUM</span>
                        <span class="data-source lab-data" title="Lab data from PageSpeed Insights">Lab</span>
                    </div>
                `;
            }
            
            container.html(html);
        },
        
        /**
         * Get CWV status based on thresholds
         */
        getCWVStatus: function(metric, value) {
            const thresholds = this.config.thresholds[metric];
            if (!thresholds) return 'unknown';
            
            if (value <= thresholds.good) return 'good';
            if (value <= thresholds.needs_improvement) return 'needs-improvement';
            return 'poor';
        },
        
        /**
         * Format time value for display
         */
        formatTime: function(ms) {
            if (ms >= 1000) {
                return (ms / 1000).toFixed(1) + 's';
            }
            return Math.round(ms) + 'ms';
        },
        
        /**
         * Load latest performance results
         */
        loadLatestResults: function() {
            const container = $('#latest-results');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_get_performance_data',
                    nonce: this.config.nonce,
                    url: this.state.currentUrl,
                    days: 1
                }
            })
            .done((response) => {
                if (response.success && response.data.latest) {
                    this.renderLatestResults(response.data.latest);
                } else {
                    container.html('<p>No recent performance data available.</p>');
                }
            })
            .fail(() => {
                container.html('<p>Error loading performance data.</p>');
            });
        },
        
        /**
         * Render latest results
         */
        renderLatestResults: function(data) {
            const container = $('#latest-results');
            
            const html = `
                <div class="latest-results-grid">
                    <div class="result-item">
                        <span class="result-label">Performance Score</span>
                        <span class="result-value score-${this.getScoreClass(data.performance_score)}">${data.performance_score}/100</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">LCP</span>
                        <span class="result-value">${this.formatTime(data.lcp)}</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">FID</span>
                        <span class="result-value">${this.formatTime(data.fid)}</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">CLS</span>
                        <span class="result-value">${data.cls.toFixed(3)}</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Last Updated</span>
                        <span class="result-value">${data.date}</span>
                    </div>
                </div>
            `;
            
            container.html(html);
        },
        
        /**
         * Get score class for styling
         */
        getScoreClass: function(score) {
            if (score >= 90) return 'good';
            if (score >= 50) return 'ok';
            return 'poor';
        },
        
        /**
         * Load page performance data
         */
        loadPagePerformance: function() {
            // This would load performance breakdown for the selected page
            const container = $('#page-performance');
            container.html('<p>Page performance analysis will be displayed here.</p>');
        },
        
        /**
         * Update charts with new data
         */
        updateCharts: function() {
            this.loadPerformanceHistory();
        },
        
        /**
         * Load performance history data
         */
        loadPerformanceHistory: function() {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_get_performance_data',
                    nonce: this.config.nonce,
                    url: this.state.currentUrl,
                    days: this.state.currentTimeframe,
                    strategy: this.state.currentStrategy
                }
            })
            .done((response) => {
                if (response.success) {
                    this.updatePerformanceTrendChart(response.data.history);
                } else {
                    this.showError('Error loading performance history');
                }
            })
            .fail(() => {
                this.showError('Network error loading performance history');
            });
        },
        
        /**
         * Update performance trend chart
         */
        updatePerformanceTrendChart: function(data) {
            if (!this.charts.performanceTrend || !data.length) return;
            
            const chart = this.charts.performanceTrend;
            
            // Prepare data
            const labels = data.map(item => new Date(item.timestamp * 1000).toLocaleDateString());
            const scores = data.map(item => item.performance_score);
            
            // Update chart
            chart.data.labels = labels;
            chart.data.datasets = [{
                label: 'Performance Score',
                data: scores,
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.1,
                fill: true
            }];
            
            chart.update();
        },
        
        /**
         * Load CWV data for detailed view
         */
        loadCWVData: function() {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_get_cwv_data',
                    nonce: this.config.nonce,
                    url: this.state.currentUrl
                }
            })
            .done((response) => {
                if (response.success) {
                    this.renderCWVCards(response.data);
                    this.updateCWVChart(response.data);
                } else {
                    this.showError('Error loading CWV data');
                }
            })
            .fail(() => {
                this.showError('Network error loading CWV data');
            });
        },
        
        /**
         * Render CWV cards
         */
        renderCWVCards: function(data) {
            const metrics = data.pagespeed || {};
            
            // Update LCP card
            if (metrics.lcp) {
                $('#lcp-value').text(this.formatTime(metrics.lcp));
                $('#lcp-status').text(this.getCWVStatus('lcp', metrics.lcp));
                $('.cwv-card[data-metric="lcp"]').removeClass('good needs-improvement poor')
                    .addClass(this.getCWVStatus('lcp', metrics.lcp));
            }
            
            // Update FID card
            if (metrics.fid) {
                $('#fid-value').text(this.formatTime(metrics.fid));
                $('#fid-status').text(this.getCWVStatus('fid', metrics.fid));
                $('.cwv-card[data-metric="fid"]').removeClass('good needs-improvement poor')
                    .addClass(this.getCWVStatus('fid', metrics.fid));
            }
            
            // Update CLS card
            if (metrics.cls) {
                $('#cls-value').text(metrics.cls.toFixed(3));
                $('#cls-status').text(this.getCWVStatus('cls', metrics.cls));
                $('.cwv-card[data-metric="cls"]').removeClass('good needs-improvement poor')
                    .addClass(this.getCWVStatus('cls', metrics.cls));
            }
            
            // Update RUM vs Lab data comparison
            this.renderDataComparison(data);
        },
        
        /**
         * Render RUM vs Lab data comparison
         */
        renderDataComparison: function(data) {
            // Render RUM data
            if (data.rum && Object.keys(data.rum).length > 0) {
                const rumHtml = `
                    <div class="comparison-metrics">
                        <div class="metric">
                            <span class="metric-name">LCP</span>
                            <span class="metric-value">${this.formatTime(data.rum.avg_lcp)}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-name">FID</span>
                            <span class="metric-value">${this.formatTime(data.rum.avg_fid)}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-name">CLS</span>
                            <span class="metric-value">${parseFloat(data.rum.avg_cls).toFixed(3)}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-name">Samples</span>
                            <span class="metric-value">${data.rum.sample_count}</span>
                        </div>
                    </div>
                `;
                $('#rum-data').html(rumHtml);
            } else {
                $('#rum-data').html('<p>No real user data available yet.</p>');
            }
            
            // Render Lab data
            if (data.pagespeed) {
                const labHtml = `
                    <div class="comparison-metrics">
                        <div class="metric">
                            <span class="metric-name">LCP</span>
                            <span class="metric-value">${this.formatTime(data.pagespeed.lcp)}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-name">FID</span>
                            <span class="metric-value">${this.formatTime(data.pagespeed.fid)}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-name">CLS</span>
                            <span class="metric-value">${data.pagespeed.cls.toFixed(3)}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-name">Score</span>
                            <span class="metric-value">${data.pagespeed.performance_score}/100</span>
                        </div>
                    </div>
                `;
                $('#lab-data').html(labHtml);
            } else {
                $('#lab-data').html('<p>No lab data available.</p>');
            }
        },
        
        /**
         * Load history data for charts
         */
        loadHistoryData: function() {
            this.loadPerformanceHistory();
        },
        
        /**
         * Update history charts
         */
        updateHistoryCharts: function() {
            this.state.currentUrl = $('#history-url').val();
            this.state.currentTimeframe = $('#history-days').val();
            this.state.currentStrategy = $('#history-strategy').val();
            
            this.loadPerformanceHistory();
        },
        
        /**
         * Load recommendations
         */
        loadRecommendations: function() {
            const container = $('#recommendations-list');
            
            // For now, show placeholder
            container.html(`
                <div class="recommendations-placeholder">
                    <p>Performance recommendations will be displayed here after running a performance test.</p>
                    <button type="button" id="run-test-for-recommendations" class="button button-primary">
                        Run Performance Test
                    </button>
                </div>
            `);
            
            $('#run-test-for-recommendations').on('click', () => {
                this.runPerformanceTest();
            });
        },
        
        /**
         * Run performance test
         */
        runPerformanceTest: function() {
            if (this.state.isTestRunning) {
                return;
            }
            
            this.state.isTestRunning = true;
            this.showTestModal();
            this.updateTestProgress(0, 'Initializing performance test...');
            
            // Simulate test steps
            this.simulateTestProgress();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_run_performance_test',
                    nonce: this.config.nonce,
                    url: this.state.currentUrl || this.config.siteUrl,
                    strategy: this.state.currentStrategy
                },
                timeout: 60000 // 1 minute timeout
            })
            .done((response) => {
                this.updateTestProgress(100, 'Performance test complete!');
                
                if (response.success) {
                    setTimeout(() => {
                        this.closeModal();
                        this.handleTestSuccess(response.data);
                    }, 1000);
                } else {
                    this.handleTestError(response.data || 'Unknown error');
                }
            })
            .fail((xhr, status, error) => {
                this.handleTestError(`Network error: ${error}`);
            })
            .always(() => {
                this.state.isTestRunning = false;
            });
        },
        
        /**
         * Show test modal
         */
        showTestModal: function() {
            $('#performance-test-modal').show();
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('.khm-modal').hide();
        },
        
        /**
         * Simulate test progress
         */
        simulateTestProgress: function() {
            const steps = [
                { progress: 20, message: 'Preparing test environment...', step: 1 },
                { progress: 40, message: 'Running PageSpeed analysis...', step: 2 },
                { progress: 60, message: 'Analyzing Core Web Vitals...', step: 3 },
                { progress: 80, message: 'Generating recommendations...', step: 4 }
            ];
            
            let currentStep = 0;
            
            const stepInterval = setInterval(() => {
                if (currentStep < steps.length) {
                    const step = steps[currentStep];
                    this.updateTestProgress(step.progress, step.message, step.step);
                    currentStep++;
                } else {
                    clearInterval(stepInterval);
                }
            }, 2000);
        },
        
        /**
         * Update test progress
         */
        updateTestProgress: function(progress, message, step = null) {
            $('.progress-fill').css('width', progress + '%');
            $('#test-status').text(message);
            
            if (step) {
                $('.test-steps .step').removeClass('active completed');
                $(`.test-steps .step[data-step="${step}"]`).addClass('active');
                $(`.test-steps .step[data-step]`).each(function() {
                    if ($(this).data('step') < step) {
                        $(this).addClass('completed');
                    }
                });
            }
        },
        
        /**
         * Handle test success
         */
        handleTestSuccess: function(data) {
            // Refresh dashboard data
            this.loadInitialData();
            this.updateCharts();
            
            // Show success message
            this.showNotice('Performance test completed successfully!', 'success');
            
            // Switch to recommendations tab if test was run from there
            if (this.state.activeTab === 'recommendations') {
                this.loadRecommendations();
            }
        },
        
        /**
         * Handle test error
         */
        handleTestError: function(error) {
            this.updateTestProgress(100, 'Test failed: ' + error);
            
            setTimeout(() => {
                this.closeModal();
                this.showNotice('Performance test failed: ' + error, 'error');
            }, 2000);
        },
        
        /**
         * Save settings
         */
        saveSettings: function() {
            const formData = $('#performance-settings-form').serialize();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData + '&action=khm_save_performance_settings&nonce=' + this.config.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('Settings saved successfully!', 'success');
                } else {
                    this.showNotice('Error saving settings: ' + response.data, 'error');
                }
            })
            .fail(() => {
                this.showNotice('Network error saving settings', 'error');
            });
        },
        
        /**
         * Enable quick optimization
         */
        enableOptimization: function(feature) {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_enable_optimization',
                    nonce: this.config.nonce,
                    feature: feature
                }
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice(`${feature} optimization enabled!`, 'success');
                } else {
                    this.showNotice('Error enabling optimization: ' + response.data, 'error');
                }
            })
            .fail(() => {
                this.showNotice('Network error enabling optimization', 'error');
            });
        },
        
        /**
         * Show notification
         */
        showNotice: function(message, type = 'info') {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible khm-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.khm-performance-dashboard').prepend(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut();
            }, 5000);
            
            // Manual dismiss
            notice.find('.notice-dismiss').on('click', () => {
                notice.fadeOut();
            });
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.khm-performance-dashboard').length) {
            KHMPerformanceDashboard.init();
        }
    });
    
})(jQuery);