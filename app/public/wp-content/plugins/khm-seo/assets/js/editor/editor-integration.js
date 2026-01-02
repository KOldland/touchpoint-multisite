/**
 * Editor Integration - Coordinate all SEO components in WordPress editors
 * 
 * Main orchestrator for real-time SEO analysis across Classic Editor,
 * Gutenberg, and Elementor with seamless component communication.
 * 
 * @package KHMSeo\Editor
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Editor Integration Class
     */
    class KHMSeoEditorIntegration {
        constructor(options = {}) {
            this.options = {
                autoStart: true,
                debounceDelay: 500,
                enableKeyboardShortcuts: true,
                enableAutoSave: true,
                enableRealTimeMode: true,
                ...options
            };

            this.components = {};
            this.editorType = null;
            this.isInitialized = false;
            this.lastAnalysisTime = 0;
            this.keyboardShortcuts = new Map();
            
            this.state = {
                currentScore: 0,
                lastAnalysisData: null,
                suggestionsCount: 0,
                previewEffectiveness: 0,
                activeTab: 'analysis'
            };

            this.init();
        }

        /**
         * Initialize the editor integration
         */
        init() {
            if (this.isInitialized) return;

            this.detectEditorType();
            this.createMainContainer();
            this.initializeComponents();
            this.bindGlobalEvents();
            this.setupKeyboardShortcuts();
            
            if (this.options.autoStart) {
                this.start();
            }

            this.isInitialized = true;
        }

        /**
         * Detect the current WordPress editor type
         */
        detectEditorType() {
            // Check for Gutenberg
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                this.editorType = 'gutenberg';
                return;
            }

            // Check for Elementor
            if (window.elementor && window.elementor.isEditMode && window.elementor.isEditMode()) {
                this.editorType = 'elementor';
                return;
            }

            // Check for classic editor
            if ($('#wp-content-editor-container').length || $('textarea#content').length) {
                this.editorType = 'classic';
                return;
            }

            // Fallback - assume classic for any editing context
            this.editorType = 'classic';
        }

        /**
         * Create the main SEO container
         */
        createMainContainer() {
            // Remove existing container if present
            $('#khm-seo-main-container').remove();

            const containerHtml = `
                <div id="khm-seo-main-container" class="khm-seo-integration">
                    <div class="seo-container-header">
                        <div class="seo-branding">
                            <span class="seo-icon">🎯</span>
                            <h3>KHM SEO Suite</h3>
                        </div>
                        <div class="seo-status-bar">
                            <div class="overall-score-mini">
                                <span class="score-label">Score:</span>
                                <span class="score-value" id="mini-score">--</span>
                            </div>
                            <div class="analysis-status">
                                <span class="status-indicator" id="analysis-status">Ready</span>
                            </div>
                        </div>
                        <div class="seo-controls">
                            <button class="btn-real-time-toggle ${this.options.enableRealTimeMode ? 'active' : ''}" 
                                    title="Toggle Real-time Analysis">
                                <span class="toggle-icon">⚡</span>
                            </button>
                            <button class="btn-collapse-all" title="Collapse All Panels">
                                <span class="collapse-icon">📁</span>
                            </button>
                        </div>
                    </div>

                    <div class="seo-container-content">
                        <div class="seo-tabs">
                            <button class="seo-tab active" data-tab="analysis">
                                <span class="tab-icon">📊</span>
                                Analysis
                                <span class="tab-notification" style="display: none;"></span>
                            </button>
                            <button class="seo-tab" data-tab="suggestions">
                                <span class="tab-icon">💡</span>
                                Suggestions
                                <span class="tab-notification" style="display: none;"></span>
                            </button>
                            <button class="seo-tab" data-tab="preview">
                                <span class="tab-icon">👁️</span>
                                Preview
                                <span class="tab-notification" style="display: none;"></span>
                            </button>
                        </div>

                        <div class="seo-tab-contents">
                            <div class="seo-tab-content active" id="analysis-tab">
                                <div id="khm-seo-score-display"></div>
                            </div>
                            
                            <div class="seo-tab-content" id="suggestions-tab">
                                <div id="khm-seo-suggestions-panel"></div>
                            </div>
                            
                            <div class="seo-tab-content" id="preview-tab">
                                <div id="khm-seo-meta-preview"></div>
                            </div>
                        </div>
                    </div>

                    <div class="seo-container-footer">
                        <div class="seo-quick-actions">
                            <button class="quick-action-btn" id="analyze-now" title="Analyze Now (Ctrl+Shift+A)">
                                <span class="action-icon">🔍</span>
                                Analyze
                            </button>
                            <button class="quick-action-btn" id="apply-suggestions" title="Apply Top Suggestions" disabled>
                                <span class="action-icon">✨</span>
                                Apply
                            </button>
                            <button class="quick-action-btn" id="export-report" title="Export SEO Report" disabled>
                                <span class="action-icon">📄</span>
                                Export
                            </button>
                        </div>
                        
                        <div class="seo-performance-mini">
                            <span class="performance-label">Analysis time:</span>
                            <span class="performance-value" id="analysis-time">--ms</span>
                        </div>
                    </div>

                    <div class="seo-floating-indicator" style="display: none;">
                        <div class="floating-score">
                            <span class="floating-score-value">85</span>
                            <span class="floating-score-trend">↗️</span>
                        </div>
                    </div>
                </div>
            `;

            // Insert container based on editor type
            this.insertContainer(containerHtml);
        }

        /**
         * Insert container in the appropriate location for each editor
         */
        insertContainer(containerHtml) {
            switch (this.editorType) {
                case 'gutenberg':
                    // Insert in Gutenberg sidebar or after editor
                    if ($('.interface-complementary-area').length) {
                        $('.interface-complementary-area').prepend(containerHtml);
                    } else {
                        $('#editor').after(containerHtml);
                    }
                    break;

                case 'elementor':
                    // Insert in Elementor panel
                    if ($('#elementor-panel').length) {
                        $('#elementor-panel').prepend(containerHtml);
                    } else {
                        $('body').append(containerHtml);
                    }
                    break;

                case 'classic':
                default:
                    // Insert after classic editor or in meta boxes area
                    if ($('#postdivrich').length) {
                        $('#postdivrich').after(containerHtml);
                    } else if ($('#normal-sortables').length) {
                        $('#normal-sortables').prepend(`<div class="postbox">${containerHtml}</div>`);
                    } else {
                        $('#wpbody-content').append(containerHtml);
                    }
                    break;
            }

            // Cache main container reference
            this.$container = $('#khm-seo-main-container');
        }

        /**
         * Initialize all SEO components
         */
        initializeComponents() {
            // Initialize live analyzer
            if (typeof KHMSeoLiveAnalyzer !== 'undefined') {
                this.components.liveAnalyzer = new KHMSeoLiveAnalyzer({
                    editorType: this.editorType,
                    realTimeMode: this.options.enableRealTimeMode,
                    debounceDelay: this.options.debounceDelay
                });
            }

            // Initialize score display
            if (typeof KHMSeoScoreDisplay !== 'undefined') {
                const $scoreContainer = $('#khm-seo-score-display');
                if ($scoreContainer.length) {
                    this.components.scoreDisplay = new KHMSeoScoreDisplay($scoreContainer);
                }
            }

            // Initialize suggestion panel
            if (typeof KHMSeoSuggestionPanel !== 'undefined') {
                const $suggestionsContainer = $('#khm-seo-suggestions-panel');
                if ($suggestionsContainer.length) {
                    this.components.suggestionPanel = new KHMSeoSuggestionPanel($suggestionsContainer);
                }
            }

            // Initialize meta preview
            if (typeof KHMSeoMetaPreview !== 'undefined') {
                const $previewContainer = $('#khm-seo-meta-preview');
                if ($previewContainer.length) {
                    this.components.metaPreview = new KHMSeoMetaPreview($previewContainer);
                }
            }

            // Wait for all components to be ready
            this.waitForComponents().then(() => {
                this.setupComponentCommunication();
                $(document).trigger('khm-seo-integration-ready', { integration: this });
            });
        }

        /**
         * Wait for all components to initialize
         */
        waitForComponents() {
            return new Promise((resolve) => {
                const checkComponents = () => {
                    const expectedComponents = ['liveAnalyzer', 'scoreDisplay', 'suggestionPanel', 'metaPreview'];
                    const readyComponents = expectedComponents.filter(comp => this.components[comp]);
                    
                    if (readyComponents.length === expectedComponents.length) {
                        resolve();
                    } else {
                        setTimeout(checkComponents, 100);
                    }
                };
                
                checkComponents();
            });
        }

        /**
         * Setup communication between components
         */
        setupComponentCommunication() {
            const { liveAnalyzer, scoreDisplay, suggestionPanel, metaPreview } = this.components;

            if (liveAnalyzer) {
                // Analysis events
                liveAnalyzer.on('onAnalysisStart', (data) => {
                    this.onAnalysisStart(data);
                });

                liveAnalyzer.on('onAnalysisComplete', (data) => {
                    this.onAnalysisComplete(data);
                });

                liveAnalyzer.on('onAnalysisError', (error) => {
                    this.onAnalysisError(error);
                });

                liveAnalyzer.on('onContentChange', (data) => {
                    this.onContentChange(data);
                });
            }

            // Setup cross-component events
            $(document).on('khm-seo-show-analyzer-details', (e, data) => {
                this.switchToTab('suggestions');
                if (suggestionPanel && suggestionPanel.showAnalyzerSuggestions) {
                    suggestionPanel.showAnalyzerSuggestions(data.analyzer, data.data);
                }
            });

            $(document).on('khm-seo-preview-updated', (e, data) => {
                this.updateTabNotification('preview', data.effectivenessScore);
            });
        }

        /**
         * Bind global event listeners
         */
        bindGlobalEvents() {
            // Tab switching
            this.$container.on('click', '.seo-tab', (e) => {
                const tab = $(e.currentTarget).data('tab');
                this.switchToTab(tab);
            });

            // Real-time toggle
            this.$container.on('click', '.btn-real-time-toggle', () => {
                this.toggleRealTimeMode();
            });

            // Collapse all panels
            this.$container.on('click', '.btn-collapse-all', () => {
                this.collapseAllPanels();
            });

            // Quick actions
            this.$container.on('click', '#analyze-now', () => {
                this.forceAnalyze();
            });

            this.$container.on('click', '#apply-suggestions', () => {
                this.applyTopSuggestions();
            });

            this.$container.on('click', '#export-report', () => {
                this.exportReport();
            });

            // Auto-save integration
            if (this.options.enableAutoSave) {
                $(document).on('heartbeat-send', (event, data) => {
                    if (this.state.lastAnalysisData) {
                        data.khm_seo_data = this.state.lastAnalysisData;
                    }
                });
            }

            // Window events
            $(window).on('beforeunload', () => {
                this.saveState();
            });

            $(window).on('resize', () => {
                this.adjustLayout();
            });
        }

        /**
         * Setup keyboard shortcuts
         */
        setupKeyboardShortcuts() {
            if (!this.options.enableKeyboardShortcuts) return;

            // Define shortcuts
            this.keyboardShortcuts.set('ctrl+shift+a', () => this.forceAnalyze());
            this.keyboardShortcuts.set('ctrl+shift+s', () => this.switchToTab('suggestions'));
            this.keyboardShortcuts.set('ctrl+shift+p', () => this.switchToTab('preview'));
            this.keyboardShortcuts.set('ctrl+shift+r', () => this.toggleRealTimeMode());
            this.keyboardShortcuts.set('ctrl+shift+c', () => this.collapseAllPanels());

            // Bind keyboard events
            $(document).on('keydown', (e) => {
                const key = this.getKeyString(e);
                const action = this.keyboardShortcuts.get(key);
                
                if (action) {
                    e.preventDefault();
                    action();
                }
            });
        }

        /**
         * Get keyboard shortcut string
         */
        getKeyString(event) {
            const parts = [];
            
            if (event.ctrlKey) parts.push('ctrl');
            if (event.shiftKey) parts.push('shift');
            if (event.altKey) parts.push('alt');
            if (event.metaKey) parts.push('meta');
            
            parts.push(event.key.toLowerCase());
            
            return parts.join('+');
        }

        /**
         * Start the integration
         */
        start() {
            if (!this.isInitialized) {
                this.init();
                return;
            }

            // Start live analyzer if available
            if (this.components.liveAnalyzer) {
                this.components.liveAnalyzer.start();
            }

            // Trigger initial analysis
            setTimeout(() => {
                this.forceAnalyze();
            }, 1000);

            this.setStatus('Active');
            $(document).trigger('khm-seo-integration-started');
        }

        /**
         * Stop the integration
         */
        stop() {
            if (this.components.liveAnalyzer) {
                this.components.liveAnalyzer.stop();
            }

            this.setStatus('Inactive');
            $(document).trigger('khm-seo-integration-stopped');
        }

        /**
         * Analysis start handler
         */
        onAnalysisStart(data) {
            this.setStatus('Analyzing...');
            this.showLoadingStates();
            
            const startTime = performance.now();
            this.lastAnalysisTime = startTime;
        }

        /**
         * Analysis complete handler
         */
        onAnalysisComplete(data) {
            this.state.lastAnalysisData = data;
            
            // Update overall score
            if (data.overall_score !== undefined) {
                this.state.currentScore = data.overall_score;
                this.updateMiniScore(data.overall_score);
            }

            // Update suggestions count
            if (data.real_time_feedback) {
                const quickWins = data.real_time_feedback.quick_wins || [];
                const priorityIssues = data.real_time_feedback.priority_issues || [];
                this.state.suggestionsCount = quickWins.length + priorityIssues.length;
                this.updateTabNotification('suggestions', this.state.suggestionsCount);
            }

            // Calculate analysis time
            const analysisTime = performance.now() - this.lastAnalysisTime;
            this.updateAnalysisTime(analysisTime);

            // Update status
            this.setStatus('Complete');
            this.hideLoadingStates();

            // Enable action buttons
            this.updateActionButtons();

            // Show floating indicator if score improved
            this.showScoreImprovement(data.overall_score);

            $(document).trigger('khm-seo-analysis-complete', { data, integration: this });
        }

        /**
         * Analysis error handler
         */
        onAnalysisError(error) {
            console.error('SEO Analysis Error:', error);
            this.setStatus('Error');
            this.hideLoadingStates();
            
            // Show error notification
            this.showNotification('Analysis error occurred. Please try again.', 'error');
        }

        /**
         * Content change handler
         */
        onContentChange(data) {
            // Update mini score if real-time mode is enabled
            if (this.options.enableRealTimeMode && data.score !== undefined) {
                this.updateMiniScore(data.score);
            }
        }

        /**
         * Switch to a specific tab
         */
        switchToTab(tabName) {
            if (this.state.activeTab === tabName) return;

            this.state.activeTab = tabName;

            // Update tab buttons
            this.$container.find('.seo-tab').removeClass('active');
            this.$container.find(`.seo-tab[data-tab="${tabName}"]`).addClass('active');

            // Update tab content
            this.$container.find('.seo-tab-content').removeClass('active');
            this.$container.find(`#${tabName}-tab`).addClass('active');

            // Clear notification for active tab
            this.clearTabNotification(tabName);

            $(document).trigger('khm-seo-tab-switched', { tab: tabName, integration: this });
        }

        /**
         * Toggle real-time analysis mode
         */
        toggleRealTimeMode() {
            this.options.enableRealTimeMode = !this.options.enableRealTimeMode;
            
            const $toggleBtn = this.$container.find('.btn-real-time-toggle');
            
            if (this.options.enableRealTimeMode) {
                $toggleBtn.addClass('active');
                this.showNotification('Real-time analysis enabled', 'success');
                
                if (this.components.liveAnalyzer) {
                    this.components.liveAnalyzer.enableRealTimeMode();
                }
            } else {
                $toggleBtn.removeClass('active');
                this.showNotification('Real-time analysis disabled', 'info');
                
                if (this.components.liveAnalyzer) {
                    this.components.liveAnalyzer.disableRealTimeMode();
                }
            }
        }

        /**
         * Collapse all panels
         */
        collapseAllPanels() {
            Object.values(this.components).forEach(component => {
                if (component && typeof component.collapse === 'function') {
                    component.collapse();
                }
            });
        }

        /**
         * Force analysis
         */
        forceAnalyze() {
            if (this.components.liveAnalyzer) {
                this.components.liveAnalyzer.forceAnalyze();
            }
        }

        /**
         * Apply top suggestions
         */
        applyTopSuggestions() {
            if (this.components.suggestionPanel) {
                const suggestions = this.components.suggestionPanel.getCurrentSuggestions();
                if (suggestions) {
                    // Apply top 3 quick wins
                    const quickWins = suggestions.quick_wins || [];
                    const topSuggestions = quickWins.slice(0, 3);
                    
                    // This would trigger actual application logic
                    topSuggestions.forEach(suggestion => {
                        console.log('Applying suggestion:', suggestion.title);
                        // Implement suggestion application logic here
                    });

                    this.showNotification(`Applied ${topSuggestions.length} suggestions`, 'success');
                }
            }
        }

        /**
         * Export SEO report
         */
        exportReport() {
            if (!this.state.lastAnalysisData) {
                this.showNotification('No analysis data available to export', 'warning');
                return;
            }

            // Create report data
            const reportData = {
                timestamp: new Date().toISOString(),
                url: window.location.href,
                overall_score: this.state.currentScore,
                analysis_data: this.state.lastAnalysisData,
                suggestions_count: this.state.suggestionsCount,
                preview_effectiveness: this.state.previewEffectiveness
            };

            // Generate and download report
            const blob = new Blob([JSON.stringify(reportData, null, 2)], { 
                type: 'application/json' 
            });
            const url = URL.createObjectURL(blob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = `seo-report-${Date.now()}.json`;
            link.click();
            
            URL.revokeObjectURL(url);
            this.showNotification('SEO report exported successfully', 'success');
        }

        /**
         * Update mini score display
         */
        updateMiniScore(score) {
            const $miniScore = this.$container.find('#mini-score');
            $miniScore.text(Math.round(score));
            
            // Add score-based styling
            $miniScore
                .removeClass('score-low score-medium score-high')
                .addClass(score < 50 ? 'score-low' : score < 80 ? 'score-medium' : 'score-high');
        }

        /**
         * Update analysis time display
         */
        updateAnalysisTime(time) {
            const $analysisTime = this.$container.find('#analysis-time');
            $analysisTime.text(`${Math.round(time)}ms`);
        }

        /**
         * Set status indicator
         */
        setStatus(status) {
            const $statusIndicator = this.$container.find('#analysis-status');
            $statusIndicator.text(status);
            
            // Add status-based styling
            $statusIndicator
                .removeClass('status-ready status-active status-error')
                .addClass(`status-${status.toLowerCase().replace(/[^a-z]/g, '')}`);
        }

        /**
         * Show loading states
         */
        showLoadingStates() {
            this.$container.addClass('analyzing');
            this.$container.find('.quick-action-btn').prop('disabled', true);
        }

        /**
         * Hide loading states
         */
        hideLoadingStates() {
            this.$container.removeClass('analyzing');
        }

        /**
         * Update action buttons state
         */
        updateActionButtons() {
            const hasData = !!this.state.lastAnalysisData;
            const hasSuggestions = this.state.suggestionsCount > 0;
            
            this.$container.find('#apply-suggestions').prop('disabled', !hasSuggestions);
            this.$container.find('#export-report').prop('disabled', !hasData);
            this.$container.find('#analyze-now').prop('disabled', false);
        }

        /**
         * Update tab notification
         */
        updateTabNotification(tabName, count) {
            const $tab = this.$container.find(`.seo-tab[data-tab="${tabName}"]`);
            const $notification = $tab.find('.tab-notification');
            
            if (count > 0) {
                $notification.text(count).show();
            } else {
                $notification.hide();
            }
        }

        /**
         * Clear tab notification
         */
        clearTabNotification(tabName) {
            const $tab = this.$container.find(`.seo-tab[data-tab="${tabName}"]`);
            $tab.find('.tab-notification').hide();
        }

        /**
         * Show score improvement indicator
         */
        showScoreImprovement(newScore) {
            if (!this.state.currentScore) return;
            
            const improvement = newScore - this.state.currentScore;
            if (improvement > 0) {
                const $indicator = this.$container.find('.seo-floating-indicator');
                const $scoreValue = $indicator.find('.floating-score-value');
                const $trend = $indicator.find('.floating-score-trend');
                
                $scoreValue.text(Math.round(newScore));
                $trend.text('↗️');
                
                $indicator.fadeIn(300).delay(3000).fadeOut(300);
            }
        }

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            // Create notification element if it doesn't exist
            let $notifications = $('#khm-seo-notifications');
            if (!$notifications.length) {
                $notifications = $('<div id="khm-seo-notifications" class="seo-notifications"></div>');
                $('body').append($notifications);
            }

            const notification = $(`
                <div class="seo-notification seo-notification-${type}">
                    <span class="notification-message">${message}</span>
                    <button class="notification-close">×</button>
                </div>
            `);

            $notifications.append(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                notification.fadeOut(300, function() { $(this).remove(); });
            }, 5000);
            
            // Manual close
            notification.find('.notification-close').on('click', function() {
                notification.fadeOut(300, function() { $(this).remove(); });
            });
        }

        /**
         * Adjust layout for different screen sizes
         */
        adjustLayout() {
            const containerWidth = this.$container.width();
            
            if (containerWidth < 600) {
                this.$container.addClass('compact-mode');
            } else {
                this.$container.removeClass('compact-mode');
            }
        }

        /**
         * Save current state
         */
        saveState() {
            const state = {
                activeTab: this.state.activeTab,
                realTimeMode: this.options.enableRealTimeMode,
                lastScore: this.state.currentScore
            };
            
            try {
                localStorage.setItem('khm_seo_integration_state', JSON.stringify(state));
            } catch (e) {
                console.warn('Could not save SEO integration state:', e);
            }
        }

        /**
         * Load saved state
         */
        loadState() {
            try {
                const savedState = localStorage.getItem('khm_seo_integration_state');
                if (savedState) {
                    const state = JSON.parse(savedState);
                    
                    if (state.activeTab) {
                        this.switchToTab(state.activeTab);
                    }
                    
                    if (state.realTimeMode !== undefined) {
                        this.options.enableRealTimeMode = state.realTimeMode;
                    }
                    
                    if (state.lastScore) {
                        this.updateMiniScore(state.lastScore);
                    }
                }
            } catch (e) {
                console.warn('Could not load SEO integration state:', e);
            }
        }

        /**
         * Public API methods
         */
        getState() {
            return { ...this.state };
        }

        getComponents() {
            return { ...this.components };
        }

        getCurrentScore() {
            return this.state.currentScore;
        }

        getLastAnalysisData() {
            return this.state.lastAnalysisData;
        }

        refresh() {
            this.forceAnalyze();
        }

        destroy() {
            // Clean up components
            Object.values(this.components).forEach(component => {
                if (component && typeof component.destroy === 'function') {
                    component.destroy();
                }
            });

            // Remove event listeners
            $(document).off('.khm-seo-integration');
            $(window).off('.khm-seo-integration');

            // Remove container
            this.$container.remove();

            // Clear state
            this.isInitialized = false;
            this.components = {};
        }
    }

    // Auto-initialize when document is ready
    $(document).ready(function() {
        // Only initialize in WordPress admin editing contexts
        if (window.pagenow && (
            window.pagenow === 'post' || 
            window.pagenow === 'page' || 
            window.pagenow.includes('edit')
        )) {
            // Wait for other components to load
            setTimeout(() => {
                window.khmSeoEditorIntegration = new KHMSeoEditorIntegration();
                
                // Trigger global ready event
                $(document).trigger('khm-seo-editor-integration-ready');
            }, 500);
        }
    });

    // Export for external use
    window.KHMSeoEditorIntegration = KHMSeoEditorIntegration;

})(jQuery);