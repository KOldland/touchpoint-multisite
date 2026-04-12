/**
 * Social Media Preview JavaScript
 * Handles interactive preview generation and meta tag editing
 * 
 * @package KHM_SEO
 * @subpackage Preview
 */

(function($) {
    'use strict';
    
    // Main Preview Manager Object
    window.KHMSocialPreview = {
        
        // Configuration
        config: {
            ajaxUrl: ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: khmSeoPreview?.nonce || '',
            postId: khmSeoPreview?.postId || 0,
            platforms: khmSeoPreview?.platforms || {},
            refreshInterval: 30000, // 30 seconds auto-refresh
            debounceDelay: 1000 // 1 second debounce for input changes
        },
        
        // State Management
        state: {
            activeTab: 'all',
            activePlatform: null,
            previews: {},
            loading: new Set(),
            lastUpdate: {},
            autoRefresh: false
        },
        
        // Initialize the preview system
        init: function() {
            this.bindEvents();
            this.initializeTabs();
            this.loadInitialPreviews();
            this.setupAutoRefresh();
            this.setupCharacterCounters();
            this.setupImageSelectors();
            this.setupModalHandlers();
            
            // Setup live preview updates
            this.setupLivePreview();
            
            console.log('KHM Social Preview initialized');
        },
        
        // Bind all event handlers
        bindEvents: function() {
            const self = this;
            
            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                const platform = $(this).data('platform');
                self.switchTab(platform);
            });
            
            // Refresh buttons
            $('.refresh-preview, #refresh-all-previews').on('click', function(e) {
                e.preventDefault();
                const platform = $(this).data('platform');
                
                if (platform) {
                    self.refreshPreview(platform);
                } else {
                    self.refreshAllPreviews();
                }
            });
            
            // Auto-optimize buttons
            $('.optimize-meta, #optimize-all-meta').on('click', function(e) {
                e.preventDefault();
                const platform = $(this).data('platform');
                
                if (platform) {
                    self.optimizeMeta(platform);
                } else {
                    self.optimizeAllMeta();
                }
            });
            
            // Edit meta buttons
            $('.edit-meta').on('click', function(e) {
                e.preventDefault();
                const platform = $(this).data('platform');
                self.switchTab(platform);
            });
            
            // Save meta buttons
            $('.save-meta').on('click', function(e) {
                e.preventDefault();
                const platform = $(this).data('platform');
                self.saveMeta(platform);
            });
            
            // Reset meta buttons
            $('.reset-meta').on('click', function(e) {
                e.preventDefault();
                const platform = $(this).data('platform');
                self.resetMeta(platform);
            });
            
            // Meta input changes (debounced)
            $('.meta-input').on('input', this.debounce(function() {
                const $input = $(this);
                const platform = $input.data('platform');
                const field = $input.data('field');
                
                self.updateCharacterCount($input);
                self.validateField($input);
                
                if (self.state.activeTab === platform) {
                    self.updateLivePreview(platform);
                }
            }, this.config.debounceDelay));
            
            // Image selection
            $('.select-image').on('click', function(e) {
                e.preventDefault();
                const platform = $(this).data('platform');
                self.openImageSelector(platform);
            });
            
            // Warning fix buttons
            $(document).on('click', '.fix-warning', function(e) {
                e.preventDefault();
                const field = $(this).data('field');
                const action = $(this).data('action');
                const platform = $(this).closest('.platform-preview-wrapper').data('platform');
                self.fixWarning(platform, field, action);
            });
            
            // Suggestion apply buttons
            $(document).on('click', '.apply-suggestion', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                const platform = $(this).closest('.meta-editor').attr('id').replace('editor-', '');
                self.applySuggestion(platform, action);
            });
        },
        
        // Initialize tab system
        initializeTabs: function() {
            $('.tab-content').hide();
            $('.tab-content.active').show();
        },
        
        // Switch between tabs
        switchTab: function(platform) {
            // Update navigation
            $('.nav-tab').removeClass('nav-tab-active');
            $(`.nav-tab[data-platform="${platform}"]`).addClass('nav-tab-active');
            
            // Update content
            $('.tab-content').removeClass('active').hide();
            $(`#${platform === 'all' ? 'all-platforms' : 'platform-' + platform}`).addClass('active').show();
            
            this.state.activeTab = platform;
            this.state.activePlatform = platform === 'all' ? null : platform;
            
            // Load preview if not already loaded
            if (platform !== 'all' && !this.state.previews[platform]) {
                this.refreshPreview(platform);
            }
        },
        
        // Load initial previews for all platforms
        loadInitialPreviews: function() {
            const platforms = Object.keys(this.config.platforms);
            
            platforms.forEach(platform => {
                this.refreshPreview(platform, false); // false = don't show loading indicator
            });
        },
        
        // Refresh preview for specific platform
        refreshPreview: function(platform, showLoading = true) {
            if (this.state.loading.has(platform)) {
                return; // Already loading
            }
            
            this.state.loading.add(platform);
            
            if (showLoading) {
                this.showLoadingState(platform);
            }
            
            const requestData = {
                action: 'khm_seo_generate_preview',
                nonce: this.config.nonce,
                post_id: this.config.postId,
                platform: platform,
                meta_data: this.getCurrentMetaData(platform)
            };
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: requestData,
                timeout: 15000
            })
            .done((response) => {
                if (response.success) {
                    this.renderPreview(platform, response.data);
                    this.state.lastUpdate[platform] = Date.now();
                } else {
                    this.showError(platform, response.data?.message || 'Preview generation failed');
                }
            })
            .fail((xhr, status, error) => {
                this.showError(platform, `Network error: ${error}`);
            })
            .always(() => {
                this.state.loading.delete(platform);
                this.hideLoadingState(platform);
            });
        },
        
        // Refresh all platform previews
        refreshAllPreviews: function() {
            const platforms = Object.keys(this.config.platforms);
            
            platforms.forEach(platform => {
                this.refreshPreview(platform);
            });
        },
        
        // Render preview HTML for platform
        renderPreview: function(platform, data) {
            const previewHtml = data.preview_html;
            const warnings = data.warnings || [];
            const analysis = data.analysis || {};
            
            // Update preview container
            $(`#preview-${platform}, #preview-large-${platform}`)
                .html(previewHtml)
                .removeClass('loading error');
            
            // Update warnings
            this.renderWarnings(platform, warnings);
            
            // Update analysis (for detailed view)
            this.renderAnalysis(platform, analysis);
            
            // Update platform status
            this.updatePlatformStatus(platform, 'success');
            
            // Store in state
            this.state.previews[platform] = data;
            
            // Trigger custom event
            $(document).trigger('khm-preview-updated', [platform, data]);
        },
        
        // Show loading state
        showLoadingState: function(platform) {
            $(`#preview-${platform}, #preview-large-${platform}`)
                .addClass('loading')
                .html(`
                    <div class="preview-loading">
                        <div class="loading-spinner"></div>
                        <p>Generating preview...</p>
                    </div>
                `);
            
            this.updatePlatformStatus(platform, 'loading');
        },
        
        // Hide loading state
        hideLoadingState: function(platform) {
            $(`#preview-${platform}, #preview-large-${platform}`).removeClass('loading');
            this.updatePlatformStatus(platform, 'ready');
        },
        
        // Show error state
        showError: function(platform, message) {
            const errorHtml = `
                <div class="preview-error">
                    <span class="dashicons dashicons-warning"></span>
                    <p><strong>Error generating preview:</strong></p>
                    <p>${this.escapeHtml(message)}</p>
                    <button type="button" class="button retry-preview" data-platform="${platform}">
                        <span class="dashicons dashicons-update"></span>
                        Retry
                    </button>
                </div>
            `;
            
            $(`#preview-${platform}, #preview-large-${platform}`)
                .addClass('error')
                .html(errorHtml);
            
            this.updatePlatformStatus(platform, 'error');
            
            // Bind retry button
            $('.retry-preview').off('click').on('click', (e) => {
                e.preventDefault();
                const retryPlatform = $(e.currentTarget).data('platform');
                this.refreshPreview(retryPlatform);
            });
        },
        
        // Update platform status indicator
        updatePlatformStatus: function(platform, status) {
            const $status = $(`.platform-preview-wrapper[data-platform="${platform}"] .platform-status`);
            
            $status.removeClass('loading success error ready')
                   .addClass(status);
            
            let icon = 'dashicons-update-alt';
            switch (status) {
                case 'loading':
                    icon = 'dashicons-update-alt';
                    break;
                case 'success':
                    icon = 'dashicons-yes-alt';
                    break;
                case 'error':
                    icon = 'dashicons-warning';
                    break;
                default:
                    icon = 'dashicons-clock';
            }
            
            $status.find('.dashicons').attr('class', `dashicons ${icon}`);
        },
        
        // Render warnings for platform
        renderWarnings: function(platform, warnings) {
            const $container = $(`#warnings-${platform}`);
            
            if (!warnings.length) {
                $container.empty();
                return;
            }
            
            const warningsHtml = warnings.map(warning => {
                return this.renderTemplate('#warning-item-template', {
                    severity: warning.severity || 'warning',
                    message: warning.message,
                    field: warning.field || '',
                    action: warning.action || 'edit'
                });
            }).join('');
            
            $container.html(`<div class="warnings-list">${warningsHtml}</div>`);
        },
        
        // Render analysis for platform
        renderAnalysis: function(platform, analysis) {
            const $container = $(`#analysis-${platform}`);
            
            if (!analysis || Object.keys(analysis).length === 0) {
                $container.empty();
                return;
            }
            
            let analysisHtml = '<div class="analysis-list">';
            
            // Score
            if (analysis.score !== undefined) {
                analysisHtml += `
                    <div class="analysis-score">
                        <span class="score-label">SEO Score:</span>
                        <span class="score-value score-${this.getScoreClass(analysis.score)}">${analysis.score}/100</span>
                    </div>
                `;
            }
            
            // Metrics
            if (analysis.metrics) {
                analysisHtml += '<div class="analysis-metrics">';
                Object.entries(analysis.metrics).forEach(([key, value]) => {
                    analysisHtml += `
                        <div class="metric-item">
                            <span class="metric-label">${this.formatMetricLabel(key)}:</span>
                            <span class="metric-value">${value}</span>
                        </div>
                    `;
                });
                analysisHtml += '</div>';
            }
            
            analysisHtml += '</div>';
            $container.html(analysisHtml);
        },
        
        // Get current meta data for platform
        getCurrentMetaData: function(platform) {
            const data = {};
            
            // Get values from form fields
            const fields = ['title', 'description', 'image'];
            fields.forEach(field => {
                const $input = $(`#${platform}_${field}`);
                if ($input.length) {
                    data[field] = $input.val();
                }
            });
            
            // Platform-specific fields
            if (platform === 'twitter') {
                const $cardType = $('#twitter_card_type');
                if ($cardType.length) {
                    data.card_type = $cardType.val();
                }
            }
            
            return data;
        },
        
        // Setup character counters
        setupCharacterCounters: function() {
            $('.meta-input[data-limit]').each((index, element) => {
                this.updateCharacterCount($(element));
            });
        },
        
        // Update character count for input
        updateCharacterCount: function($input) {
            const limit = parseInt($input.data('limit'));
            const current = $input.val().length;
            const $counter = $input.siblings('label').find('.character-counter .current-count');
            
            $counter.text(current);
            
            // Update styling based on usage
            const $counterContainer = $counter.closest('.character-counter');
            $counterContainer.removeClass('warning danger');
            
            if (current > limit) {
                $counterContainer.addClass('danger');
            } else if (current > limit * 0.9) {
                $counterContainer.addClass('warning');
            }
        },
        
        // Setup image selectors
        setupImageSelectors: function() {
            // Initialize WordPress media uploader for each platform
            Object.keys(this.config.platforms).forEach(platform => {
                this.initializeImageSelector(platform);
            });
        },
        
        // Initialize image selector for platform
        initializeImageSelector: function(platform) {
            let mediaUploader;
            
            $(`.select-image[data-platform="${platform}"]`).on('click', (e) => {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: 'Select Social Media Image',
                    button: {
                        text: 'Use This Image'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                mediaUploader.on('select', () => {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    this.setImageForPlatform(platform, attachment);
                });
                
                mediaUploader.open();
            });
        },
        
        // Set image for platform
        setImageForPlatform: function(platform, attachment) {
            const $input = $(`#${platform}_image`);
            const $preview = $(`#image-preview-${platform}`);
            
            $input.val(attachment.url);
            
            const previewHtml = `
                <div class="image-preview-item">
                    <img src="${attachment.sizes?.thumbnail?.url || attachment.url}" 
                         alt="${this.escapeHtml(attachment.alt)}"
                         class="preview-thumbnail">
                    <div class="image-details">
                        <p><strong>${this.escapeHtml(attachment.title)}</strong></p>
                        <p>${attachment.width} x ${attachment.height}px</p>
                        <button type="button" class="button button-small remove-image" data-platform="${platform}">
                            Remove
                        </button>
                    </div>
                </div>
            `;
            
            $preview.html(previewHtml);
            
            // Bind remove button
            $preview.find('.remove-image').on('click', (e) => {
                e.preventDefault();
                $input.val('');
                $preview.empty();
                this.updateLivePreview(platform);
            });
            
            // Update live preview
            this.updateLivePreview(platform);
        },
        
        // Setup live preview updates
        setupLivePreview: function() {
            // Auto-update preview when meta fields change
            $('.meta-input').on('input', this.debounce(() => {
                if (this.state.activePlatform) {
                    this.updateLivePreview(this.state.activePlatform);
                }
            }, this.config.debounceDelay));
        },
        
        // Update live preview for platform
        updateLivePreview: function(platform) {
            // Only update if we're viewing the specific platform tab
            if (this.state.activeTab !== platform) {
                return;
            }
            
            // Generate preview with current form data
            this.refreshPreview(platform);
        },
        
        // Save meta data for platform
        saveMeta: function(platform) {
            const metaData = this.getCurrentMetaData(platform);
            
            const requestData = {
                action: 'khm_seo_save_meta',
                nonce: this.config.nonce,
                post_id: this.config.postId,
                platform: platform,
                meta_data: metaData
            };
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: requestData
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('Meta tags saved successfully!', 'success');
                    this.refreshPreview(platform);
                } else {
                    this.showNotice(response.data?.message || 'Failed to save meta tags', 'error');
                }
            })
            .fail(() => {
                this.showNotice('Network error occurred', 'error');
            });
        },
        
        // Reset meta data for platform
        resetMeta: function(platform) {
            if (!confirm('Are you sure you want to reset all meta tags to their default values?')) {
                return;
            }
            
            // Clear all form fields
            const fields = ['title', 'description', 'image'];
            fields.forEach(field => {
                $(`#${platform}_${field}`).val('');
            });
            
            // Clear image preview
            $(`#image-preview-${platform}`).empty();
            
            // Refresh character counters
            $(`.meta-input[data-limit]`).each((index, element) => {
                this.updateCharacterCount($(element));
            });
            
            // Update preview
            this.refreshPreview(platform);
        },
        
        // Auto-optimize meta tags
        optimizeMeta: function(platform) {
            const requestData = {
                action: 'khm_seo_optimize_meta',
                nonce: this.config.nonce,
                post_id: this.config.postId,
                platform: platform
            };
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: requestData
            })
            .done((response) => {
                if (response.success) {
                    const optimizedData = response.data.meta_data;
                    
                    // Update form fields with optimized values
                    Object.entries(optimizedData).forEach(([field, value]) => {
                        const $input = $(`#${platform}_${field}`);
                        if ($input.length && value) {
                            $input.val(value);
                            this.updateCharacterCount($input);
                        }
                    });
                    
                    this.showNotice('Meta tags optimized successfully!', 'success');
                    this.refreshPreview(platform);
                } else {
                    this.showNotice(response.data?.message || 'Optimization failed', 'error');
                }
            })
            .fail(() => {
                this.showNotice('Network error occurred', 'error');
            });
        },
        
        // Optimize all platforms
        optimizeAllMeta: function() {
            const platforms = Object.keys(this.config.platforms);
            
            platforms.forEach(platform => {
                this.optimizeMeta(platform);
            });
        },
        
        // Setup auto-refresh
        setupAutoRefresh: function() {
            if (this.config.refreshInterval > 0) {
                setInterval(() => {
                    if (this.state.autoRefresh) {
                        this.refreshAllPreviews();
                    }
                }, this.config.refreshInterval);
            }
        },
        
        // Setup modal handlers
        setupModalHandlers: function() {
            // Modal close handlers
            $('.modal-close').on('click', () => {
                $('#preview-modal').hide();
            });
            
            // Click outside modal to close
            $('#preview-modal').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    $('#preview-modal').hide();
                }
            });
        },
        
        // Utility: Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        // Utility: Escape HTML
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        // Utility: Render template
        renderTemplate: function(templateSelector, data) {
            const template = $(templateSelector).html();
            if (!template) {
                return '';
            }
            
            return template.replace(/<%-([\s\S]+?)%>/g, (match, code) => {
                const value = this.evaluateTemplate(code.trim(), data);
                return this.escapeHtml(String(value));
            }).replace(/<%\s*([\s\S]+?)\s*%>/g, (match, code) => {
                return this.evaluateTemplate(code.trim(), data) || '';
            });
        },
        
        // Utility: Evaluate template code
        evaluateTemplate: function(code, data) {
            try {
                return new Function(...Object.keys(data), `return ${code}`)(...Object.values(data));
            } catch (e) {
                console.warn('Template evaluation error:', e);
                return '';
            }
        },
        
        // Utility: Get score class
        getScoreClass: function(score) {
            if (score >= 80) return 'good';
            if (score >= 60) return 'okay';
            return 'poor';
        },
        
        // Utility: Format metric label
        formatMetricLabel: function(key) {
            return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },
        
        // Utility: Show notice
        showNotice: function(message, type = 'info') {
            // Create notice element
            const notice = $(`
                <div class="notice notice-${type} is-dismissible khm-preview-notice">
                    <p>${this.escapeHtml(message)}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            // Add to page
            $('.khm-social-preview-container').prepend(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.slideUp(300, () => notice.remove());
            }, 5000);
            
            // Manual dismiss
            notice.find('.notice-dismiss').on('click', () => {
                notice.slideUp(300, () => notice.remove());
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Check if we're on a post/page edit screen with the preview meta box
        if ($('.khm-social-preview-container').length) {
            KHMSocialPreview.init();
        }
    });
    
})(jQuery);