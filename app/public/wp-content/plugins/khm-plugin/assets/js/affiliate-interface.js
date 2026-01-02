/**
 * Professional Affiliate Interface JavaScript
 * Handles all frontend interactions for the affiliate dashboard
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        KHMAffiliateInterface.init();
    });

    window.KHMAffiliateInterface = {
        
        // Cache DOM elements
        cache: {
            $dashboard: null,
            $tabs: null,
            $tabPanels: null,
            $linkForm: null,
            $generatedLinkSection: null,
            $creativesGrid: null,
            $analyticsCharts: null
        },

        // Configuration
        config: {
            charts: {},
            currentPeriod: 30
        },

        /**
         * Initialize the interface
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initializeCharts();
            this.loadRecentLinks();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.cache.$dashboard = $('.khm-affiliate-dashboard');
            this.cache.$tabs = $('.khm-tab-button');
            this.cache.$tabPanels = $('.khm-tab-panel');
            this.cache.$linkForm = $('#link-generator-form');
            this.cache.$generatedLinkSection = $('#generated-link-section');
            this.cache.$creativesGrid = $('#creatives-grid');
            this.cache.$analyticsCharts = $('.khm-analytics-charts');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Tab switching
            this.cache.$tabs.on('click', this.handleTabSwitch.bind(this));
            
            // Link generation
            this.cache.$linkForm.on('submit', this.handleLinkGeneration.bind(this));
            
            // Quick links
            $('.khm-quick-link-item').on('click', this.handleQuickLink.bind(this));
            
            // Copy link button
            $(document).on('click', '#copy-link-btn', this.handleCopyLink.bind(this));
            
            // Test link button
            $(document).on('click', '#test-link-btn', this.handleTestLink.bind(this));
            
            // Creative category filtering
            $('.khm-category-btn').on('click', this.handleCreativeFilter.bind(this));
            
            // Creative actions
            $(document).on('click', '.khm-get-code-btn', this.handleGetCreativeCode.bind(this));
            $(document).on('click', '.khm-preview-btn', this.handlePreviewCreative.bind(this));
            
            // Analytics period change
            $('#analytics-period').on('change', this.handleAnalyticsPeriodChange.bind(this));
            
            // Export analytics
            $('#export-analytics-btn').on('click', this.handleExportAnalytics.bind(this));
            
            // Account forms
            $('#account-form').on('submit', this.handleAccountUpdate.bind(this));
            $('#payment-form').on('submit', this.handlePaymentUpdate.bind(this));
        },

        /**
         * Handle tab switching
         */
        handleTabSwitch: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const targetTab = $button.data('tab');
            
            // Update active states
            this.cache.$tabs.removeClass('active');
            $button.addClass('active');
            
            this.cache.$tabPanels.removeClass('active');
            $('#' + targetTab).addClass('active');
            
            // Load tab-specific content
            this.loadTabContent(targetTab);
        },

        /**
         * Load tab-specific content
         */
        loadTabContent: function(tabId) {
            switch(tabId) {
                case 'analytics-tab':
                    this.refreshAnalytics();
                    break;
                case 'creatives-tab':
                    this.loadCreatives();
                    break;
                case 'overview-tab':
                    this.refreshOverview();
                    break;
            }
        },

        /**
         * Handle link generation
         */
        handleLinkGeneration: function(e) {
            e.preventDefault();
            
            const formData = {
                action: 'khm_generate_affiliate_link',
                nonce: khmAffiliate.nonce,
                target_url: $('#target-url').val(),
                campaign: $('#link-campaign').val(),
                medium: $('#link-medium').val(),
                source: $('#link-source').val()
            };
            
            // Show loading state
            const $submitBtn = this.cache.$linkForm.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            $submitBtn.text('Generating...').prop('disabled', true);
            
            $.ajax({
                url: khmAffiliate.ajaxUrl,
                type: 'POST',
                data: formData,
                success: this.handleLinkGenerationSuccess.bind(this),
                error: this.handleLinkGenerationError.bind(this),
                complete: function() {
                    $submitBtn.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle successful link generation
         */
        handleLinkGenerationSuccess: function(response) {
            if (response.success) {
                $('#generated-link').val(response.data.affiliate_url);
                this.cache.$generatedLinkSection.slideDown();
                this.showMessage('Link generated successfully!', 'success');
                this.addToRecentLinks(response.data.affiliate_url);
            } else {
                this.showMessage(response.data.message || 'Failed to generate link', 'error');
            }
        },

        /**
         * Handle link generation error
         */
        handleLinkGenerationError: function() {
            this.showMessage('Network error. Please try again.', 'error');
        },

        /**
         * Handle quick link selection
         */
        handleQuickLink: function(e) {
            const $item = $(e.currentTarget);
            const url = $item.data('url');
            
            $('#target-url').val(url);
            $item.addClass('selected').siblings().removeClass('selected');
            
            // Auto-generate if URL is valid
            if (url) {
                this.cache.$linkForm.trigger('submit');
            }
        },

        /**
         * Handle copy link
         */
        handleCopyLink: function(e) {
            e.preventDefault();
            
            const linkInput = document.getElementById('generated-link');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                this.showMessage('Link copied to clipboard!', 'success');
                
                // Visual feedback
                const $btn = $(e.currentTarget);
                const originalText = $btn.text();
                $btn.text('Copied!');
                setTimeout(() => {
                    $btn.text(originalText);
                }, 2000);
            } catch (err) {
                this.showMessage('Failed to copy link', 'error');
            }
        },

        /**
         * Handle test link
         */
        handleTestLink: function(e) {
            e.preventDefault();
            
            const link = $('#generated-link').val();
            if (link) {
                window.open(link, '_blank');
            }
        },

        /**
         * Handle creative filtering
         */
        handleCreativeFilter: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const category = $btn.data('category');
            
            // Update active state
            $('.khm-category-btn').removeClass('active');
            $btn.addClass('active');
            
            // Filter creatives
            this.filterCreatives(category);
        },

        /**
         * Filter creatives by category
         */
        filterCreatives: function(category) {
            const $items = $('.khm-creative-item');
            
            if (category === 'all') {
                $items.show();
            } else {
                $items.each(function() {
                    const $item = $(this);
                    const itemType = $item.data('type');
                    
                    if (itemType === category) {
                        $item.show();
                    } else {
                        $item.hide();
                    }
                });
            }
        },

        /**
         * Handle get creative code
         */
        handleGetCreativeCode: function(e) {
            e.preventDefault();
            
            const creativeId = $(e.currentTarget).data('creative-id');
            this.showCreativeCode(creativeId);
        },

        /**
         * Show creative code modal
         */
        showCreativeCode: function(creativeId) {
            // Create modal HTML
            const modalHtml = `
                <div class="khm-modal-overlay">
                    <div class="khm-modal">
                        <div class="khm-modal-header">
                            <h3>Creative Code</h3>
                            <button class="khm-modal-close">&times;</button>
                        </div>
                        <div class="khm-modal-body">
                            <p>Copy this code to use the creative on your website:</p>
                            <textarea class="khm-code-textarea" readonly>
&lt;a href="[affiliate_link]" target="_blank"&gt;
    &lt;img src="[creative_url]" alt="[creative_alt]" /&gt;
&lt;/a&gt;
                            </textarea>
                            <div class="khm-modal-actions">
                                <button class="khm-copy-code-btn">Copy Code</button>
                                <button class="khm-modal-close">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            $('body').append(modalHtml);
            
            // Bind modal events
            $('.khm-modal-close').on('click', this.closeModal);
            $('.khm-copy-code-btn').on('click', this.copyCreativeCode);
            $('.khm-modal-overlay').on('click', function(e) {
                if (e.target === e.currentTarget) {
                    $(this).remove();
                }
            });
        },

        /**
         * Handle preview creative
         */
        handlePreviewCreative: function(e) {
            e.preventDefault();
            
            const creativeId = $(e.currentTarget).data('creative-id');
            // Implementation for preview would go here
            this.showMessage('Preview functionality coming soon!', 'info');
        },

        /**
         * Handle analytics period change
         */
        handleAnalyticsPeriodChange: function(e) {
            const period = $(e.target).val();
            this.config.currentPeriod = period;
            this.refreshAnalytics();
        },

        /**
         * Refresh analytics data
         */
        refreshAnalytics: function() {
            const data = {
                action: 'khm_get_affiliate_stats',
                nonce: khmAffiliate.nonce,
                period: this.config.currentPeriod
            };
            
            $.ajax({
                url: khmAffiliate.ajaxUrl,
                type: 'POST',
                data: data,
                success: this.updateAnalyticsCharts.bind(this),
                error: function() {
                    console.error('Failed to load analytics data');
                }
            });
        },

        /**
         * Initialize charts
         */
        initializeCharts: function() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded');
                return;
            }
            
            this.initTrafficChart();
            this.initConversionsChart();
        },

        /**
         * Initialize traffic chart
         */
        initTrafficChart: function() {
            const ctx = document.getElementById('traffic-chart');
            if (!ctx) return;
            
            this.config.charts.traffic = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.generateDateLabels(30),
                    datasets: [{
                        label: 'Clicks',
                        data: this.generateSampleData(30, 10, 50),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
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
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * Initialize conversions chart
         */
        initConversionsChart: function() {
            const ctx = document.getElementById('conversions-chart');
            if (!ctx) return;
            
            this.config.charts.conversions = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: this.generateDateLabels(30),
                    datasets: [{
                        label: 'Conversions',
                        data: this.generateSampleData(30, 0, 5),
                        backgroundColor: '#10b981',
                        borderRadius: 4
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
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * Update analytics charts
         */
        updateAnalyticsCharts: function(response) {
            if (response.success && response.data) {
                // Update charts with real data
                const data = response.data;
                
                if (this.config.charts.traffic) {
                    this.config.charts.traffic.data.datasets[0].data = data.traffic_data || [];
                    this.config.charts.traffic.update();
                }
                
                if (this.config.charts.conversions) {
                    this.config.charts.conversions.data.datasets[0].data = data.conversion_data || [];
                    this.config.charts.conversions.update();
                }
                
                // Update performance table
                this.updatePerformanceTable(data.link_performance || []);
            }
        },

        /**
         * Update performance table
         */
        updatePerformanceTable: function(performanceData) {
            const $tbody = $('#performance-table-body');
            $tbody.empty();
            
            if (performanceData.length === 0) {
                $tbody.append('<tr><td colspan="5">No performance data available.</td></tr>');
                return;
            }
            
            performanceData.forEach(function(link) {
                const row = `
                    <tr>
                        <td>${link.name}</td>
                        <td>${link.clicks.toLocaleString()}</td>
                        <td>${link.conversions.toLocaleString()}</td>
                        <td>${link.ctr}%</td>
                        <td>$${link.earnings.toFixed(2)}</td>
                    </tr>
                `;
                $tbody.append(row);
            });
        },

        /**
         * Handle export analytics
         */
        handleExportAnalytics: function(e) {
            e.preventDefault();
            
            const data = {
                action: 'khm_export_affiliate_analytics',
                nonce: khmAffiliate.nonce,
                period: this.config.currentPeriod,
                format: 'csv'
            };
            
            // Create temporary form to download file
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = khmAffiliate.ajaxUrl;
            
            Object.keys(data).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = data[key];
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        },

        /**
         * Handle account update
         */
        handleAccountUpdate: function(e) {
            e.preventDefault();
            
            const formData = $(e.target).serialize();
            
            $.ajax({
                url: khmAffiliate.ajaxUrl,
                type: 'POST',
                data: formData + '&action=khm_update_affiliate_account&nonce=' + khmAffiliate.nonce,
                success: function(response) {
                    if (response.success) {
                        KHMAffiliateInterface.showMessage('Account updated successfully!', 'success');
                    } else {
                        KHMAffiliateInterface.showMessage(response.data.message || 'Update failed', 'error');
                    }
                },
                error: function() {
                    KHMAffiliateInterface.showMessage('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Handle payment update
         */
        handlePaymentUpdate: function(e) {
            e.preventDefault();
            
            const formData = $(e.target).serialize();
            
            $.ajax({
                url: khmAffiliate.ajaxUrl,
                type: 'POST',
                data: formData + '&action=khm_update_payment_info&nonce=' + khmAffiliate.nonce,
                success: function(response) {
                    if (response.success) {
                        KHMAffiliateInterface.showMessage('Payment information updated!', 'success');
                    } else {
                        KHMAffiliateInterface.showMessage(response.data.message || 'Update failed', 'error');
                    }
                },
                error: function() {
                    KHMAffiliateInterface.showMessage('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Load recent links
         */
        loadRecentLinks: function() {
            const recentLinks = JSON.parse(localStorage.getItem('khm_recent_links') || '[]');
            this.displayRecentLinks(recentLinks);
        },

        /**
         * Add to recent links
         */
        addToRecentLinks: function(url) {
            let recentLinks = JSON.parse(localStorage.getItem('khm_recent_links') || '[]');
            
            // Add new link to beginning
            recentLinks.unshift({
                url: url,
                created: new Date().toISOString()
            });
            
            // Keep only last 10
            recentLinks = recentLinks.slice(0, 10);
            
            localStorage.setItem('khm_recent_links', JSON.stringify(recentLinks));
            this.displayRecentLinks(recentLinks);
        },

        /**
         * Display recent links
         */
        displayRecentLinks: function(links) {
            const $container = $('#recent-links-list');
            
            if (links.length === 0) {
                $container.html('<p>Generate your first link to see it here.</p>');
                return;
            }
            
            let html = '<div class="khm-recent-links-list">';
            links.forEach(function(link) {
                const date = new Date(link.created).toLocaleDateString();
                html += `
                    <div class="khm-recent-link-item">
                        <div class="khm-link-url">${link.url}</div>
                        <div class="khm-link-date">${date}</div>
                        <button class="khm-copy-btn" onclick="KHMAffiliateInterface.copyToClipboard('${link.url}')">Copy</button>
                    </div>
                `;
            });
            html += '</div>';
            
            $container.html(html);
        },

        /**
         * Copy to clipboard utility
         */
        copyToClipboard: function(text) {
            navigator.clipboard.writeText(text).then(function() {
                KHMAffiliateInterface.showMessage('Link copied!', 'success');
            }).catch(function() {
                KHMAffiliateInterface.showMessage('Failed to copy', 'error');
            });
        },

        /**
         * Load creatives
         */
        loadCreatives: function() {
            // Implementation for loading creatives dynamically
            // Would make AJAX call to get latest creatives
        },

        /**
         * Refresh overview
         */
        refreshOverview: function() {
            // Implementation for refreshing overview data
            // Would make AJAX call to get latest stats
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.khm-modal-overlay').remove();
        },

        /**
         * Copy creative code
         */
        copyCreativeCode: function() {
            const $textarea = $('.khm-code-textarea');
            $textarea.select();
            document.execCommand('copy');
            KHMAffiliateInterface.showMessage('Code copied!', 'success');
        },

        /**
         * Show message
         */
        showMessage: function(message, type) {
            const $message = $(`
                <div class="khm-message ${type}">
                    ${message}
                </div>
            `);
            
            // Insert at top of dashboard
            this.cache.$dashboard.prepend($message);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Generate date labels for charts
         */
        generateDateLabels: function(days) {
            const labels = [];
            const today = new Date();
            
            for (let i = days - 1; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            }
            
            return labels;
        },

        /**
         * Generate sample data for charts
         */
        generateSampleData: function(points, min, max) {
            const data = [];
            for (let i = 0; i < points; i++) {
                data.push(Math.floor(Math.random() * (max - min + 1)) + min);
            }
            return data;
        }
    };

})(jQuery);

// Additional styles for modals and recent links
const additionalCSS = `
    .khm-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }
    
    .khm-modal {
        background: white;
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 80%;
        overflow-y: auto;
    }
    
    .khm-modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .khm-modal-header h3 {
        margin: 0;
        color: #1e293b;
    }
    
    .khm-modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #64748b;
    }
    
    .khm-modal-body {
        padding: 24px;
    }
    
    .khm-code-textarea {
        width: 100%;
        height: 120px;
        padding: 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-family: monospace;
        font-size: 0.9rem;
        resize: vertical;
        margin: 16px 0;
    }
    
    .khm-modal-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 20px;
    }
    
    .khm-copy-code-btn {
        background: #667eea;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
    }
    
    .khm-recent-links-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .khm-recent-link-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
    }
    
    .khm-link-url {
        flex: 1;
        font-family: monospace;
        font-size: 0.85rem;
        color: #475569;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .khm-link-date {
        font-size: 0.8rem;
        color: #64748b;
        min-width: 80px;
    }
    
    .khm-recent-link-item .khm-copy-btn {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
`;

// Inject additional CSS
const style = document.createElement('style');
style.textContent = additionalCSS;
document.head.appendChild(style);