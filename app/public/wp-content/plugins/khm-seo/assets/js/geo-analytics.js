/**
 * GEO Analytics Dashboard JavaScript
 *
 * Provides interactive analytics and performance tracking for AnswerCards
 */

(function($) {
    'use strict';

    /**
     * GEO Analytics Manager
     */
    window.KHMGeoAnalytics = {

        /**
         * Initialize analytics dashboard
         */
        init: function() {
            this.bindEvents();
            this.loadAnalyticsData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Refresh analytics button
            $(document).on('click', '.khm-geo-refresh-analytics', this.refreshAnalytics.bind(this));

            // Date range selector
            $(document).on('change', '.khm-geo-date-range', this.changeDateRange.bind(this));

            // Metric type filter
            $(document).on('change', '.khm-geo-metric-filter', this.filterMetrics.bind(this));

            // Export analytics
            $(document).on('click', '.khm-geo-export-analytics', this.exportAnalytics.bind(this));

            // Performance chart interactions
            $(document).on('click', '.khm-performance-chart .data-point', this.showMetricDetails.bind(this));
        },

        /**
         * Load analytics data for current post
         */
        loadAnalyticsData: function() {
            var self = this;
            var postId = this.getCurrentPostId();

            if (!postId) {
                return;
            }

            this.showLoading();

            $.ajax({
                url: KHMGeoAnalytics.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_geo_get_analytics',
                    nonce: KHMGeoAnalytics.nonce,
                    post_id: postId
                },
                success: function(response) {
                    self.hideLoading();
                    if (response.success) {
                        self.renderAnalytics(response.data);
                    } else {
                        self.showError('Failed to load analytics data');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showError('Network error loading analytics');
                }
            });
        },

        /**
         * Refresh analytics data
         */
        refreshAnalytics: function(e) {
            e.preventDefault();
            this.loadAnalyticsData();
        },

        /**
         * Change date range
         */
        changeDateRange: function(e) {
            var range = $(e.target).val();
            this.setDateRange(range);
            this.loadAnalyticsData();
        },

        /**
         * Filter metrics by type
         */
        filterMetrics: function(e) {
            var metricType = $(e.target).val();
            this.filterCharts(metricType);
        },

        /**
         * Export analytics data
         */
        exportAnalytics: function(e) {
            e.preventDefault();

            var postId = this.getCurrentPostId();
            var data = {
                action: 'khm_geo_export_analytics',
                nonce: KHMGeoAnalytics.nonce,
                post_id: postId,
                format: 'csv'
            };

            // Create download link
            var url = KHMGeoAnalytics.ajax_url + '?' + $.param(data);
            window.open(url, '_blank');
        },

        /**
         * Show metric details on chart click
         */
        showMetricDetails: function(e) {
            var $point = $(e.currentTarget);
            var metricData = $point.data('metric');

            this.showMetricModal(metricData);
        },

        /**
         * Render analytics dashboard
         */
        renderAnalytics: function(data) {
            var $container = $('.khm-geo-analytics-container');

            if (!$container.length) {
                return;
            }

            // Performance overview
            this.renderPerformanceOverview(data);

            // Engagement metrics
            this.renderEngagementChart(data);

            // Traffic trends
            this.renderTrafficChart(data);

            // Top performing AnswerCards
            this.renderTopAnswerCards(data);

            // Recommendations
            this.renderRecommendations(data);
        },

        /**
         * Render performance overview cards
         */
        renderPerformanceOverview: function(data) {
            var html = '';

            html += '<div class="khm-performance-cards">';
            html += '<div class="khm-card">';
            html += '<h4>Performance Score</h4>';
            html += '<div class="khm-score">' + data.performance_score + '/100</div>';
            html += '<div class="khm-score-bar"><div class="khm-score-fill" style="width:' + data.performance_score + '%"></div></div>';
            html += '</div>';

            html += '<div class="khm-card">';
            html += '<h4>Engagement Rate</h4>';
            html += '<div class="khm-metric">' + (data.engagement_rate * 100).toFixed(1) + '%</div>';
            html += '<div class="khm-trend ' + (data.engagement_rate > 0.15 ? 'positive' : 'neutral') + '">Target: >15%</div>';
            html += '</div>';

            html += '<div class="khm-card">';
            html += '<h4>Citation Rate</h4>';
            html += '<div class="khm-metric">' + (data.citation_rate * 100).toFixed(1) + '%</div>';
            html += '<div class="khm-trend ' + (data.citation_rate > 0.05 ? 'positive' : 'neutral') + '">Target: >5%</div>';
            html += '</div>';

            html += '<div class="khm-card">';
            html += '<h4>Total Views</h4>';
            html += '<div class="khm-metric">' + data.total_views.toLocaleString() + '</div>';
            html += '<div class="khm-period">Last 30 days</div>';
            html += '</div>';
            html += '</div>';

            $('.khm-performance-overview').html(html);
        },

        /**
         * Render engagement chart
         */
        renderEngagementChart: function(data) {
            // Simple chart implementation - in production, use Chart.js or similar
            var html = '<div class="khm-chart-container">';
            html += '<h4>Engagement Over Time</h4>';
            html += '<div class="khm-chart-placeholder">';
            html += '<p>Chart visualization would go here</p>';
            html += '<small>Expansions: ' + data.total_expansions + ' | Citation Clicks: ' + data.total_citation_clicks + '</small>';
            html += '</div>';
            html += '</div>';

            $('.khm-engagement-chart').html(html);
        },

        /**
         * Render traffic chart
         */
        renderTrafficChart: function(data) {
            var html = '<div class="khm-chart-container">';
            html += '<h4>Traffic Trends</h4>';
            html += '<div class="khm-chart-placeholder">';
            html += '<p>Traffic visualization would go here</p>';
            html += '<small>Page Views: ' + data.total_views + '</small>';
            html += '</div>';
            html += '</div>';

            $('.khm-traffic-chart').html(html);
        },

        /**
         * Render top performing AnswerCards
         */
        renderTopAnswerCards: function(data) {
            var html = '<div class="khm-top-cards">';
            html += '<h4>Top Performing AnswerCards</h4>';

            // Mock data - in production, get from API
            var topCards = [
                { question: 'What is SEO?', score: 95, views: 1250 },
                { question: 'How to optimize WordPress?', score: 87, views: 980 },
                { question: 'SEO best practices?', score: 82, views: 756 }
            ];

            topCards.forEach(function(card) {
                html += '<div class="khm-card-item">';
                html += '<div class="khm-card-question">' + card.question + '</div>';
                html += '<div class="khm-card-stats">';
                html += '<span class="khm-card-score">Score: ' + card.score + '</span>';
                html += '<span class="khm-card-views">Views: ' + card.views + '</span>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';

            $('.khm-top-answercards').html(html);
        },

        /**
         * Render recommendations
         */
        renderRecommendations: function(data) {
            var recommendations = this.generateRecommendations(data);

            var html = '<div class="khm-recommendations">';
            html += '<h4>Optimization Recommendations</h4>';

            recommendations.forEach(function(rec) {
                html += '<div class="khm-recommendation ' + rec.priority + '">';
                html += '<div class="khm-rec-icon">' + rec.icon + '</div>';
                html += '<div class="khm-rec-content">';
                html += '<h5>' + rec.title + '</h5>';
                html += '<p>' + rec.description + '</p>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';

            $('.khm-recommendations-container').html(html);
        },

        /**
         * Generate optimization recommendations
         */
        generateRecommendations: function(data) {
            var recommendations = [];

            if (data.engagement_rate < 0.15) {
                recommendations.push({
                    title: 'Improve AnswerCard Engagement',
                    description: 'Your engagement rate is below the 15% target. Consider improving question clarity and answer quality.',
                    priority: 'high',
                    icon: '📈'
                });
            }

            if (data.citation_rate < 0.05) {
                recommendations.push({
                    title: 'Add More Citations',
                    description: 'Citation click rate is low. Add more authoritative sources to build trust.',
                    priority: 'medium',
                    icon: '🔗'
                });
            }

            if (data.performance_score < 70) {
                recommendations.push({
                    title: 'Overall Performance Optimization',
                    description: 'Your content performance score needs improvement. Focus on quality and engagement.',
                    priority: 'high',
                    icon: '⚡'
                });
            }

            if (data.total_views < 100) {
                recommendations.push({
                    title: 'Increase Content Visibility',
                    description: 'Low view count suggests visibility issues. Consider SEO optimization and promotion.',
                    priority: 'medium',
                    icon: '👁️'
                });
            }

            // Default recommendation if everything looks good
            if (recommendations.length === 0) {
                recommendations.push({
                    title: 'Content Performing Well',
                    description: 'Your AnswerCards are performing above average. Keep up the good work!',
                    priority: 'success',
                    icon: '✅'
                });
            }

            return recommendations;
        },

        /**
         * Show metric details modal
         */
        showMetricModal: function(metricData) {
            var html = '<div class="khm-modal-overlay">';
            html += '<div class="khm-modal">';
            html += '<div class="khm-modal-header">';
            html += '<h3>Metric Details</h3>';
            html += '<button class="khm-modal-close">&times;</button>';
            html += '</div>';
            html += '<div class="khm-modal-body">';
            html += '<pre>' + JSON.stringify(metricData, null, 2) + '</pre>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            $('body').append(html);

            // Bind close events
            $('.khm-modal-close, .khm-modal-overlay').on('click', function() {
                $('.khm-modal-overlay').remove();
            });
        },

        /**
         * Set date range for analytics
         */
        setDateRange: function(range) {
            // Store in localStorage for persistence
            localStorage.setItem('khm_geo_date_range', range);
        },

        /**
         * Filter charts by metric type
         */
        filterCharts: function(metricType) {
            // Hide/show chart elements based on metric type
            $('.khm-chart-container').each(function() {
                var $chart = $(this);
                if (metricType === 'all' || $chart.hasClass('khm-' + metricType)) {
                    $chart.show();
                } else {
                    $chart.hide();
                }
            });
        },

        /**
         * Get current post ID
         */
        getCurrentPostId: function() {
            var postId = $('#post_ID').val() || KHMGeoAnalytics.post_id;
            return parseInt(postId) || 0;
        },

        /**
         * Show loading indicator
         */
        showLoading: function() {
            $('.khm-geo-analytics-container').addClass('loading');
        },

        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            $('.khm-geo-analytics-container').removeClass('loading');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            var html = '<div class="khm-error-notice">';
            html += '<p>' + message + '</p>';
            html += '</div>';

            $('.khm-geo-analytics-container').prepend(html);

            setTimeout(function() {
                $('.khm-error-notice').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof KHMGeoAnalytics !== 'undefined') {
            KHMGeoAnalytics.init();
        }
    });

})(jQuery);