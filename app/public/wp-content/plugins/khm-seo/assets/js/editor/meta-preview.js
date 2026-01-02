/**
 * Meta Preview - Real-time SERP and social media preview
 * 
 * Displays how content will appear in Google search results,
 * Facebook, Twitter, and mobile with real-time updates.
 * 
 * @package KHMSeo\Editor
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Meta Preview Class
     */
    class KHMSeoMetaPreview {
        constructor(container, options = {}) {
            this.container = $(container);
            this.options = {
                showMobile: true,
                showSocial: true,
                updateInterval: 500,
                truncateLength: {
                    google: {
                        title: 60,
                        description: 160
                    },
                    facebook: {
                        title: 95,
                        description: 297
                    },
                    twitter: {
                        title: 70,
                        description: 200
                    }
                },
                ...options
            };

            this.currentMeta = {};
            this.activePreview = 'google';
            this.lastUpdateTime = 0;

            this.init();
        }

        /**
         * Initialize the meta preview
         */
        init() {
            this.createPreviewContainer();
            this.bindEvents();
            this.extractCurrentMeta();
        }

        /**
         * Create the preview container HTML structure
         */
        createPreviewContainer() {
            const html = `
                <div class="khm-seo-meta-preview">
                    <div class="preview-header">
                        <h4>SERP & Social Preview</h4>
                        <div class="preview-controls">
                            <button class="btn-toggle-preview" title="Toggle preview">
                                <span class="toggle-icon">▼</span>
                            </button>
                        </div>
                    </div>

                    <div class="preview-content">
                        <div class="preview-loading" style="display: none;">
                            <div class="loading-spinner"></div>
                            <p>Updating preview...</p>
                        </div>

                        <div class="preview-tabs">
                            <button class="preview-tab active" data-preview="google">
                                <span class="tab-icon">🔍</span>
                                Google
                            </button>
                            <button class="preview-tab" data-preview="facebook">
                                <span class="tab-icon">📘</span>
                                Facebook
                            </button>
                            <button class="preview-tab" data-preview="twitter">
                                <span class="tab-icon">🐦</span>
                                Twitter
                            </button>
                            ${this.options.showMobile ? `
                            <button class="preview-tab" data-preview="mobile">
                                <span class="tab-icon">📱</span>
                                Mobile
                            </button>
                            ` : ''}
                        </div>

                        <div class="preview-body">
                            <!-- Google Preview -->
                            <div class="preview-pane active" id="preview-google">
                                <div class="google-serp-preview">
                                    <div class="serp-header">
                                        <div class="site-info">
                                            <div class="site-icon"></div>
                                            <div class="site-url">
                                                <span class="domain">${window.location.hostname}</span>
                                                <span class="breadcrumb" id="google-breadcrumb"></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="serp-content">
                                        <h3 class="serp-title" id="google-title">
                                            Your page title will appear here
                                        </h3>
                                        <div class="serp-description" id="google-description">
                                            Your meta description will appear here to give searchers a preview of your content.
                                        </div>
                                    </div>

                                    <div class="serp-footer">
                                        <div class="serp-features">
                                            <span class="feature-item last-updated" style="display: none;">
                                                Updated: <span class="update-date"></span>
                                            </span>
                                            <span class="feature-item reading-time" style="display: none;">
                                                <span class="read-duration"></span> min read
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="preview-analysis">
                                    <div class="analysis-item">
                                        <span class="analysis-label">Title Length:</span>
                                        <span class="analysis-value" id="google-title-length">0 characters</span>
                                        <span class="analysis-status" id="google-title-status">✓</span>
                                    </div>
                                    <div class="analysis-item">
                                        <span class="analysis-label">Description Length:</span>
                                        <span class="analysis-value" id="google-description-length">0 characters</span>
                                        <span class="analysis-status" id="google-description-status">✓</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Facebook Preview -->
                            <div class="preview-pane" id="preview-facebook">
                                <div class="facebook-preview">
                                    <div class="fb-header">
                                        <div class="fb-user">
                                            <div class="fb-avatar"></div>
                                            <div class="fb-user-info">
                                                <span class="fb-username">Your Page</span>
                                                <span class="fb-timestamp">Just now</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="fb-content">
                                        <div class="fb-text">
                                            Check out this great content!
                                        </div>

                                        <div class="fb-card">
                                            <div class="fb-image" id="facebook-image">
                                                <div class="no-image-placeholder">
                                                    <span class="placeholder-icon">🖼️</span>
                                                    <span class="placeholder-text">No featured image</span>
                                                </div>
                                            </div>
                                            <div class="fb-card-content">
                                                <div class="fb-card-title" id="facebook-title">
                                                    Your page title will appear here
                                                </div>
                                                <div class="fb-card-description" id="facebook-description">
                                                    Your meta description will appear here.
                                                </div>
                                                <div class="fb-card-url">
                                                    <span class="fb-domain">${window.location.hostname.toUpperCase()}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="preview-analysis">
                                    <div class="analysis-item">
                                        <span class="analysis-label">Title Length:</span>
                                        <span class="analysis-value" id="facebook-title-length">0 characters</span>
                                        <span class="analysis-status" id="facebook-title-status">✓</span>
                                    </div>
                                    <div class="analysis-item">
                                        <span class="analysis-label">Description Length:</span>
                                        <span class="analysis-value" id="facebook-description-length">0 characters</span>
                                        <span class="analysis-status" id="facebook-description-status">✓</span>
                                    </div>
                                    <div class="analysis-item">
                                        <span class="analysis-label">Image:</span>
                                        <span class="analysis-value" id="facebook-image-status">Not set</span>
                                        <span class="analysis-status" id="facebook-image-indicator">⚠️</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Twitter Preview -->
                            <div class="preview-pane" id="preview-twitter">
                                <div class="twitter-preview">
                                    <div class="twitter-header">
                                        <div class="twitter-user">
                                            <div class="twitter-avatar"></div>
                                            <div class="twitter-user-info">
                                                <span class="twitter-name">Your Account</span>
                                                <span class="twitter-handle">@youraccount</span>
                                                <span class="twitter-time">• now</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="twitter-content">
                                        <div class="twitter-text">
                                            Just published this amazing content! 
                                        </div>

                                        <div class="twitter-card" id="twitter-card">
                                            <div class="twitter-card-image" id="twitter-image">
                                                <div class="no-image-placeholder">
                                                    <span class="placeholder-icon">🖼️</span>
                                                    <span class="placeholder-text">No image</span>
                                                </div>
                                            </div>
                                            <div class="twitter-card-content">
                                                <div class="twitter-card-title" id="twitter-title">
                                                    Your page title will appear here
                                                </div>
                                                <div class="twitter-card-description" id="twitter-description">
                                                    Your meta description will appear here.
                                                </div>
                                                <div class="twitter-card-url">
                                                    <span class="twitter-domain">${window.location.hostname}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="twitter-actions">
                                        <span class="action-item">💬 Reply</span>
                                        <span class="action-item">🔄 Retweet</span>
                                        <span class="action-item">❤️ Like</span>
                                        <span class="action-item">↗️ Share</span>
                                    </div>
                                </div>

                                <div class="preview-analysis">
                                    <div class="analysis-item">
                                        <span class="analysis-label">Title Length:</span>
                                        <span class="analysis-value" id="twitter-title-length">0 characters</span>
                                        <span class="analysis-status" id="twitter-title-status">✓</span>
                                    </div>
                                    <div class="analysis-item">
                                        <span class="analysis-label">Description Length:</span>
                                        <span class="analysis-value" id="twitter-description-length">0 characters</span>
                                        <span class="analysis-status" id="twitter-description-status">✓</span>
                                    </div>
                                    <div class="analysis-item">
                                        <span class="analysis-label">Card Type:</span>
                                        <span class="analysis-value" id="twitter-card-type">Summary</span>
                                        <span class="analysis-status" id="twitter-card-indicator">✓</span>
                                    </div>
                                </div>
                            </div>

                            ${this.options.showMobile ? this.createMobilePreview() : ''}
                        </div>

                        <div class="preview-footer">
                            <div class="preview-effectiveness">
                                <span class="effectiveness-label">Preview Effectiveness:</span>
                                <div class="effectiveness-score">
                                    <span class="score-value" id="preview-effectiveness-score">--</span>
                                    <span class="score-label">/100</span>
                                </div>
                            </div>
                            <div class="preview-actions">
                                <button class="btn-refresh-preview btn btn-small">
                                    <span class="refresh-icon">🔄</span>
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="preview-empty" style="display: none;">
                        <div class="empty-state">
                            <div class="empty-icon">📱</div>
                            <h5>Preview not available</h5>
                            <p>Add a title and meta description to see the preview</p>
                        </div>
                    </div>
                </div>
            `;

            this.container.html(html);
            this.cacheElements();
        }

        /**
         * Create mobile preview section
         */
        createMobilePreview() {
            return `
                <!-- Mobile Preview -->
                <div class="preview-pane" id="preview-mobile">
                    <div class="mobile-device">
                        <div class="mobile-header">
                            <div class="mobile-status-bar">
                                <span class="mobile-time">9:41</span>
                                <div class="mobile-indicators">
                                    <span class="signal">📶</span>
                                    <span class="wifi">📶</span>
                                    <span class="battery">🔋</span>
                                </div>
                            </div>
                            <div class="mobile-browser-bar">
                                <div class="mobile-url-bar">
                                    <span class="mobile-secure">🔒</span>
                                    <span class="mobile-url">${window.location.hostname}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-content">
                            <div class="mobile-serp-result">
                                <div class="mobile-result-url">
                                    <span class="mobile-domain">${window.location.hostname}</span>
                                    <span class="mobile-path" id="mobile-breadcrumb"></span>
                                </div>
                                <h3 class="mobile-result-title" id="mobile-title">
                                    Your page title will appear here
                                </h3>
                                <div class="mobile-result-description" id="mobile-description">
                                    Your meta description will appear here to give mobile searchers a preview.
                                </div>
                                <div class="mobile-result-meta">
                                    <span class="mobile-published">Published • </span>
                                    <span class="mobile-read-time">5 min read</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="preview-analysis">
                        <div class="analysis-item">
                            <span class="analysis-label">Mobile Title:</span>
                            <span class="analysis-value" id="mobile-title-length">0 characters</span>
                            <span class="analysis-status" id="mobile-title-status">✓</span>
                        </div>
                        <div class="analysis-item">
                            <span class="analysis-label">Mobile Description:</span>
                            <span class="analysis-value" id="mobile-description-length">0 characters</span>
                            <span class="analysis-status" id="mobile-description-status">✓</span>
                        </div>
                        <div class="analysis-item">
                            <span class="analysis-label">Mobile Optimization:</span>
                            <span class="analysis-value" id="mobile-optimization">Good</span>
                            <span class="analysis-status" id="mobile-optimization-status">✓</span>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * Cache DOM elements
         */
        cacheElements() {
            this.$panel = this.container.find('.khm-seo-meta-preview');
            this.$header = this.$panel.find('.preview-header');
            this.$content = this.$panel.find('.preview-content');
            this.$loading = this.$panel.find('.preview-loading');
            this.$empty = this.$panel.find('.preview-empty');
            
            this.$toggleBtn = this.$panel.find('.btn-toggle-preview');
            this.$toggleIcon = this.$panel.find('.toggle-icon');
            this.$refreshBtn = this.$panel.find('.btn-refresh-preview');
            
            this.$tabs = this.$panel.find('.preview-tab');
            this.$panes = this.$panel.find('.preview-pane');
            
            this.$effectivenessScore = this.$panel.find('#preview-effectiveness-score');

            // Google elements
            this.$googleTitle = this.$panel.find('#google-title');
            this.$googleDescription = this.$panel.find('#google-description');
            this.$googleBreadcrumb = this.$panel.find('#google-breadcrumb');
            
            // Facebook elements
            this.$facebookTitle = this.$panel.find('#facebook-title');
            this.$facebookDescription = this.$panel.find('#facebook-description');
            this.$facebookImage = this.$panel.find('#facebook-image');
            
            // Twitter elements
            this.$twitterTitle = this.$panel.find('#twitter-title');
            this.$twitterDescription = this.$panel.find('#twitter-description');
            this.$twitterImage = this.$panel.find('#twitter-image');
            
            if (this.options.showMobile) {
                this.$mobileTitle = this.$panel.find('#mobile-title');
                this.$mobileDescription = this.$panel.find('#mobile-description');
                this.$mobileBreadcrumb = this.$panel.find('#mobile-breadcrumb');
            }
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Listen for content changes
            $(document).on('khm-seo-live-analyzer-ready', () => {
                if (window.khmSeoLiveAnalyzer) {
                    window.khmSeoLiveAnalyzer.on('onAnalysisStart', () => {
                        this.showLoading();
                    });

                    window.khmSeoLiveAnalyzer.on('onAnalysisComplete', (data) => {
                        this.updatePreview(data);
                    });

                    window.khmSeoLiveAnalyzer.on('onContentChange', () => {
                        this.scheduleUpdate();
                    });
                }
            });

            // Panel toggle
            this.$toggleBtn.on('click', () => {
                this.togglePanel();
            });

            // Tab switching
            this.$tabs.on('click', (e) => {
                const preview = $(e.currentTarget).data('preview');
                this.switchPreview(preview);
            });

            // Refresh preview
            this.$refreshBtn.on('click', () => {
                this.refreshPreview();
            });

            // Listen for meta field changes
            $(document).on('input', 'input[name="khm_seo_title"], textarea[name="khm_seo_meta_description"]', () => {
                this.scheduleUpdate();
            });

            // WordPress editor events
            if (typeof wp !== 'undefined' && wp.data) {
                // Gutenberg title changes
                wp.data.subscribe(() => {
                    const title = wp.data.select('core/editor').getEditedPostAttribute('title');
                    if (title !== this.currentMeta.title) {
                        this.currentMeta.title = title;
                        this.scheduleUpdate();
                    }
                });
            }

            // Classic editor title changes
            $(document).on('input', '#title', () => {
                this.scheduleUpdate();
            });
        }

        /**
         * Extract current meta information
         */
        extractCurrentMeta() {
            const meta = {
                title: '',
                description: '',
                keywords: '',
                image: '',
                url: window.location.href,
                siteName: document.title.split(' - ').pop() || window.location.hostname
            };

            // Get title from various sources
            const $titleField = $('input[name="khm_seo_title"]');
            const $postTitle = $('#title');
            
            if ($titleField.length && $titleField.val()) {
                meta.title = $titleField.val();
            } else if ($postTitle.length && $postTitle.val()) {
                meta.title = $postTitle.val();
            } else if (typeof wp !== 'undefined' && wp.data) {
                meta.title = wp.data.select('core/editor')?.getEditedPostAttribute('title') || '';
            }

            // Get meta description
            const $descField = $('textarea[name="khm_seo_meta_description"]');
            if ($descField.length && $descField.val()) {
                meta.description = $descField.val();
            }

            // Get featured image
            const $featuredImage = $('#set-post-thumbnail img');
            if ($featuredImage.length) {
                meta.image = $featuredImage.attr('src');
            }

            this.currentMeta = meta;
            this.updateAllPreviews();
        }

        /**
         * Schedule update with debouncing
         */
        scheduleUpdate() {
            const now = Date.now();
            if (now - this.lastUpdateTime < this.options.updateInterval) {
                clearTimeout(this.updateTimeout);
            }

            this.updateTimeout = setTimeout(() => {
                this.extractCurrentMeta();
                this.lastUpdateTime = Date.now();
            }, this.options.updateInterval);
        }

        /**
         * Show loading state
         */
        showLoading() {
            this.$loading.show();
            this.$content.find('.preview-body').hide();
        }

        /**
         * Update preview based on analysis data
         */
        updatePreview(analysisData) {
            this.$loading.hide();
            this.$content.find('.preview-body').show();
            
            if (analysisData.type === 'insufficient_content') {
                this.showEmptyState();
                return;
            }

            // Update meta from analysis if available
            if (analysisData.meta_analysis) {
                this.currentMeta = {
                    ...this.currentMeta,
                    ...analysisData.meta_analysis
                };
            }

            this.updateAllPreviews();
            this.updateEffectivenessScore(analysisData);
            this.$empty.hide();
        }

        /**
         * Update all preview panes
         */
        updateAllPreviews() {
            this.updateGooglePreview();
            this.updateFacebookPreview();
            this.updateTwitterPreview();
            
            if (this.options.showMobile) {
                this.updateMobilePreview();
            }
        }

        /**
         * Update Google SERP preview
         */
        updateGooglePreview() {
            const meta = this.currentMeta;
            const limits = this.options.truncateLength.google;
            
            // Update title
            const title = meta.title || 'Untitled Page';
            const truncatedTitle = this.truncateText(title, limits.title);
            this.$googleTitle.text(truncatedTitle);
            
            // Update description
            const description = meta.description || 'No meta description available.';
            const truncatedDescription = this.truncateText(description, limits.description);
            this.$googleDescription.text(truncatedDescription);
            
            // Update breadcrumb
            const path = window.location.pathname.split('/').filter(p => p);
            const breadcrumb = path.length > 0 ? ` › ${path.slice(-1)[0].replace(/-/g, ' ')}` : '';
            this.$googleBreadcrumb.text(breadcrumb);
            
            // Update analysis
            this.updateLengthAnalysis('google', title.length, description.length, limits);
        }

        /**
         * Update Facebook preview
         */
        updateFacebookPreview() {
            const meta = this.currentMeta;
            const limits = this.options.truncateLength.facebook;
            
            // Update title
            const title = meta.title || 'Untitled Page';
            const truncatedTitle = this.truncateText(title, limits.title);
            this.$facebookTitle.text(truncatedTitle);
            
            // Update description
            const description = meta.description || 'No description available.';
            const truncatedDescription = this.truncateText(description, limits.description);
            this.$facebookDescription.text(truncatedDescription);
            
            // Update image
            if (meta.image) {
                this.$facebookImage.html(`<img src="${meta.image}" alt="Featured image" />`);
                $('#facebook-image-status').text('Set');
                $('#facebook-image-indicator').text('✓');
            } else {
                this.$facebookImage.html(`
                    <div class="no-image-placeholder">
                        <span class="placeholder-icon">🖼️</span>
                        <span class="placeholder-text">No featured image</span>
                    </div>
                `);
                $('#facebook-image-status').text('Not set');
                $('#facebook-image-indicator').text('⚠️');
            }
            
            // Update analysis
            this.updateLengthAnalysis('facebook', title.length, description.length, limits);
        }

        /**
         * Update Twitter preview
         */
        updateTwitterPreview() {
            const meta = this.currentMeta;
            const limits = this.options.truncateLength.twitter;
            
            // Update title
            const title = meta.title || 'Untitled Page';
            const truncatedTitle = this.truncateText(title, limits.title);
            this.$twitterTitle.text(truncatedTitle);
            
            // Update description
            const description = meta.description || 'No description available.';
            const truncatedDescription = this.truncateText(description, limits.description);
            this.$twitterDescription.text(truncatedDescription);
            
            // Update image
            if (meta.image) {
                this.$twitterImage.html(`<img src="${meta.image}" alt="Featured image" />`);
            } else {
                this.$twitterImage.html(`
                    <div class="no-image-placeholder">
                        <span class="placeholder-icon">🖼️</span>
                        <span class="placeholder-text">No image</span>
                    </div>
                `);
            }
            
            // Update analysis
            this.updateLengthAnalysis('twitter', title.length, description.length, limits);
        }

        /**
         * Update mobile preview
         */
        updateMobilePreview() {
            if (!this.options.showMobile) return;
            
            const meta = this.currentMeta;
            const limits = this.options.truncateLength.google; // Mobile uses Google limits
            
            // Update title
            const title = meta.title || 'Untitled Page';
            const truncatedTitle = this.truncateText(title, limits.title - 10); // Slightly shorter for mobile
            this.$mobileTitle.text(truncatedTitle);
            
            // Update description
            const description = meta.description || 'No meta description available.';
            const truncatedDescription = this.truncateText(description, limits.description - 20); // Shorter for mobile
            this.$mobileDescription.text(truncatedDescription);
            
            // Update breadcrumb
            const path = window.location.pathname.split('/').filter(p => p);
            const breadcrumb = path.length > 0 ? `/${path.slice(-1)[0]}` : '';
            this.$mobileBreadcrumb.text(breadcrumb);
            
            // Update mobile analysis
            this.updateMobileLengthAnalysis(title.length, description.length);
        }

        /**
         * Update length analysis for a platform
         */
        updateLengthAnalysis(platform, titleLength, descLength, limits) {
            // Title analysis
            const $titleLength = $(`#${platform}-title-length`);
            const $titleStatus = $(`#${platform}-title-status`);
            
            $titleLength.text(`${titleLength} characters`);
            
            if (titleLength === 0) {
                $titleStatus.text('⚠️').removeClass('good warning').addClass('error');
            } else if (titleLength > limits.title) {
                $titleStatus.text('⚠️').removeClass('good error').addClass('warning');
            } else {
                $titleStatus.text('✓').removeClass('warning error').addClass('good');
            }
            
            // Description analysis
            const $descLength = $(`#${platform}-description-length`);
            const $descStatus = $(`#${platform}-description-status`);
            
            $descLength.text(`${descLength} characters`);
            
            if (descLength === 0) {
                $descStatus.text('⚠️').removeClass('good warning').addClass('error');
            } else if (descLength > limits.description) {
                $descStatus.text('⚠️').removeClass('good error').addClass('warning');
            } else {
                $descStatus.text('✓').removeClass('warning error').addClass('good');
            }
        }

        /**
         * Update mobile-specific analysis
         */
        updateMobileLengthAnalysis(titleLength, descLength) {
            const $titleLength = $('#mobile-title-length');
            const $titleStatus = $('#mobile-title-status');
            const $descLength = $('#mobile-description-length');
            const $descStatus = $('#mobile-description-status');
            
            $titleLength.text(`${titleLength} characters`);
            $descLength.text(`${descLength} characters`);
            
            // Mobile title (shorter limit)
            if (titleLength === 0 || titleLength > 50) {
                $titleStatus.text('⚠️').removeClass('good').addClass('error');
            } else {
                $titleStatus.text('✓').removeClass('error').addClass('good');
            }
            
            // Mobile description (shorter limit)
            if (descLength === 0 || descLength > 140) {
                $descStatus.text('⚠️').removeClass('good').addClass('error');
            } else {
                $descStatus.text('✓').removeClass('error').addClass('good');
            }
        }

        /**
         * Update effectiveness score
         */
        updateEffectivenessScore(analysisData) {
            let score = 0;
            let factors = 0;

            // Title factor (30%)
            if (this.currentMeta.title && this.currentMeta.title.length > 0) {
                if (this.currentMeta.title.length <= 60) {
                    score += 30;
                } else {
                    score += 15; // Partial credit for long titles
                }
            }
            factors++;

            // Description factor (30%)
            if (this.currentMeta.description && this.currentMeta.description.length > 0) {
                if (this.currentMeta.description.length <= 160) {
                    score += 30;
                } else {
                    score += 15; // Partial credit for long descriptions
                }
            }
            factors++;

            // Image factor (20%)
            if (this.currentMeta.image) {
                score += 20;
            }
            factors++;

            // SEO factor (20%) - from analysis data
            if (analysisData && analysisData.overall_score) {
                score += Math.round(analysisData.overall_score * 0.2);
            }
            factors++;

            const finalScore = Math.round(score);
            this.$effectivenessScore.text(finalScore);
            
            // Add score-based styling
            this.$effectivenessScore
                .removeClass('score-low score-medium score-high')
                .addClass(finalScore < 50 ? 'score-low' : finalScore < 80 ? 'score-medium' : 'score-high');
        }

        /**
         * Truncate text with ellipsis
         */
        truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength - 3) + '...';
        }

        /**
         * Switch active preview tab
         */
        switchPreview(previewName) {
            if (this.activePreview === previewName) return;
            
            this.activePreview = previewName;
            
            // Update tabs
            this.$tabs.removeClass('active');
            this.$tabs.filter(`[data-preview="${previewName}"]`).addClass('active');
            
            // Update panes
            this.$panes.removeClass('active');
            $(`#preview-${previewName}`).addClass('active');
        }

        /**
         * Toggle panel visibility
         */
        togglePanel() {
            const isCollapsed = this.$content.is(':hidden');
            
            if (isCollapsed) {
                this.$content.slideDown(300);
                this.$toggleIcon.text('▼');
                this.$panel.removeClass('collapsed');
            } else {
                this.$content.slideUp(300);
                this.$toggleIcon.text('▶');
                this.$panel.addClass('collapsed');
            }
        }

        /**
         * Show empty state
         */
        showEmptyState() {
            this.$content.hide();
            this.$empty.show();
        }

        /**
         * Refresh preview
         */
        refreshPreview() {
            this.extractCurrentMeta();
            
            if (window.khmSeoLiveAnalyzer) {
                window.khmSeoLiveAnalyzer.forceAnalyze();
            } else {
                this.updateAllPreviews();
            }
        }

        /**
         * Get current preview data
         */
        getPreviewData() {
            return {
                meta: this.currentMeta,
                activePreview: this.activePreview,
                effectivenessScore: parseInt(this.$effectivenessScore.text()) || 0
            };
        }

        /**
         * Public methods
         */
        getCurrentMeta() {
            return this.currentMeta;
        }

        refresh() {
            this.refreshPreview();
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize meta preview in SEO meta box
        const $previewContainer = $('#khm-seo-meta-preview');
        if ($previewContainer.length) {
            window.khmSeoMetaPreview = new KHMSeoMetaPreview($previewContainer);
        }
        
        // Trigger custom event for other components
        $(document).trigger('khm-seo-meta-preview-ready');
    });

    // Export for other modules
    window.KHMSeoMetaPreview = KHMSeoMetaPreview;

})(jQuery);