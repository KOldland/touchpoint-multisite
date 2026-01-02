/**
 * Suggestion Panel - SEO optimization recommendations interface
 * 
 * Displays actionable SEO suggestions based on content analysis,
 * organized by priority and type for optimal user experience.
 * 
 * @package KHMSeo\Editor
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Suggestion Panel Class
     */
    class KHMSeoSuggestionPanel {
        constructor(container, options = {}) {
            this.container = $(container);
            this.options = {
                maxQuickWins: 5,
                maxPriorityFixes: 3,
                autoUpdate: true,
                expandByDefault: false,
                ...options
            };

            this.currentSuggestions = null;
            this.activeTab = 'quick_wins';
            this.expandedSections = new Set();

            this.init();
        }

        /**
         * Initialize the suggestion panel
         */
        init() {
            this.createSuggestionContainer();
            this.bindEvents();
        }

        /**
         * Create the suggestion panel HTML structure
         */
        createSuggestionContainer() {
            const html = `
                <div class="khm-seo-suggestions-panel">
                    <div class="suggestions-header">
                        <h4>SEO Suggestions</h4>
                        <div class="suggestions-controls">
                            <button class="btn-toggle-panel" title="Toggle suggestions">
                                <span class="toggle-icon">▼</span>
                            </button>
                        </div>
                    </div>

                    <div class="suggestions-content">
                        <div class="suggestions-loading" style="display: none;">
                            <div class="loading-spinner"></div>
                            <p>Generating suggestions...</p>
                        </div>

                        <div class="suggestions-tabs">
                            <button class="tab-button active" data-tab="quick_wins">
                                <span class="tab-icon">⚡</span>
                                Quick Wins
                                <span class="tab-badge">0</span>
                            </button>
                            <button class="tab-button" data-tab="priority_fixes">
                                <span class="tab-icon">🚨</span>
                                Priority Issues
                                <span class="tab-badge">0</span>
                            </button>
                            <button class="tab-button" data-tab="strategy">
                                <span class="tab-icon">📈</span>
                                Strategy
                                <span class="tab-badge">0</span>
                            </button>
                        </div>

                        <div class="suggestions-body">
                            <div class="tab-content active" id="tab-quick-wins">
                                <div class="suggestions-section">
                                    <div class="section-header">
                                        <h5>Quick Optimization Wins</h5>
                                        <p class="section-description">Easy improvements with high SEO impact</p>
                                    </div>
                                    <div class="suggestions-list" id="quick-wins-list">
                                        <div class="no-suggestions">
                                            <div class="no-suggestions-icon">💡</div>
                                            <p>Great! No quick wins needed right now.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content" id="tab-priority-fixes">
                                <div class="suggestions-section">
                                    <div class="section-header">
                                        <h5>Priority Issues</h5>
                                        <p class="section-description">Critical problems that need immediate attention</p>
                                    </div>
                                    <div class="suggestions-list" id="priority-fixes-list">
                                        <div class="no-suggestions">
                                            <div class="no-suggestions-icon">✅</div>
                                            <p>Excellent! No critical issues found.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content" id="tab-strategy">
                                <div class="suggestions-section">
                                    <div class="section-header">
                                        <h5>Content Strategy</h5>
                                        <p class="section-description">Long-term optimization recommendations</p>
                                    </div>
                                    <div class="suggestions-list" id="strategy-list">
                                        <div class="strategy-subsection" id="content-strategy">
                                            <h6>Content Optimization</h6>
                                            <div class="subsection-items"></div>
                                        </div>
                                        <div class="strategy-subsection" id="technical-seo">
                                            <h6>Technical SEO</h6>
                                            <div class="subsection-items"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="suggestions-footer">
                            <div class="suggestions-summary">
                                <span class="total-suggestions">0 suggestions available</span>
                            </div>
                            <div class="suggestions-actions">
                                <button class="btn-refresh-suggestions btn btn-small">
                                    <span class="refresh-icon">🔄</span>
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="suggestions-empty" style="display: none;">
                        <div class="empty-state">
                            <div class="empty-icon">🎯</div>
                            <h5>Ready to analyze</h5>
                            <p>Start typing to get personalized SEO suggestions</p>
                        </div>
                    </div>
                </div>
            `;

            this.container.html(html);
            this.cacheElements();
        }

        /**
         * Cache DOM elements for performance
         */
        cacheElements() {
            this.$panel = this.container.find('.khm-seo-suggestions-panel');
            this.$header = this.$panel.find('.suggestions-header');
            this.$content = this.$panel.find('.suggestions-content');
            this.$loading = this.$panel.find('.suggestions-loading');
            this.$empty = this.$panel.find('.suggestions-empty');
            
            this.$toggleBtn = this.$panel.find('.btn-toggle-panel');
            this.$toggleIcon = this.$panel.find('.toggle-icon');
            this.$refreshBtn = this.$panel.find('.btn-refresh-suggestions');
            
            this.$tabs = this.$panel.find('.tab-button');
            this.$tabContents = this.$panel.find('.tab-content');
            this.$tabBadges = this.$panel.find('.tab-badge');
            
            this.$quickWinsList = this.$panel.find('#quick-wins-list');
            this.$priorityFixesList = this.$panel.find('#priority-fixes-list');
            this.$strategyList = this.$panel.find('#strategy-list');
            
            this.$totalSuggestions = this.$panel.find('.total-suggestions');
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Listen for analysis events
            $(document).on('khm-seo-live-analyzer-ready', () => {
                if (window.khmSeoLiveAnalyzer) {
                    window.khmSeoLiveAnalyzer.on('onAnalysisStart', () => {
                        this.showLoading();
                    });

                    window.khmSeoLiveAnalyzer.on('onAnalysisComplete', (data) => {
                        this.updateSuggestions(data);
                    });
                }
            });

            // Panel toggle
            this.$toggleBtn.on('click', () => {
                this.togglePanel();
            });

            // Tab switching
            this.$tabs.on('click', (e) => {
                const tab = $(e.currentTarget).data('tab');
                this.switchTab(tab);
            });

            // Refresh suggestions
            this.$refreshBtn.on('click', () => {
                this.refreshSuggestions();
            });

            // Suggestion item interactions
            this.$panel.on('click', '.suggestion-item', (e) => {
                this.toggleSuggestionDetails($(e.currentTarget));
            });

            this.$panel.on('click', '.apply-suggestion', (e) => {
                e.stopPropagation();
                this.applySuggestion($(e.currentTarget));
            });

            this.$panel.on('click', '.dismiss-suggestion', (e) => {
                e.stopPropagation();
                this.dismissSuggestion($(e.currentTarget));
            });

            // Analyzer details event
            $(document).on('khm-seo-show-analyzer-details', (e, data) => {
                this.showAnalyzerSuggestions(data.analyzer, data.data);
            });
        }

        /**
         * Show loading state
         */
        showLoading() {
            this.$loading.show();
            this.$content.find('.suggestions-body').hide();
            this.$empty.hide();
        }

        /**
         * Update suggestions based on analysis results
         */
        updateSuggestions(analysisData) {
            this.$loading.hide();
            
            if (analysisData.type === 'insufficient_content') {
                this.showEmptyState();
                return;
            }

            // Generate suggestions using the suggestion engine data
            this.generateSuggestionsFromAnalysis(analysisData)
                .then(suggestions => {
                    this.currentSuggestions = suggestions;
                    this.displaySuggestions(suggestions);
                    this.$content.find('.suggestions-body').show();
                    this.$empty.hide();
                });
        }

        /**
         * Generate suggestions from analysis data
         */
        async generateSuggestionsFromAnalysis(analysisData) {
            // This would normally call the backend suggestion engine
            // For now, we'll generate suggestions from the real-time feedback
            const suggestions = {
                quick_wins: [],
                priority_fixes: [],
                content_strategy: [],
                technical_seo: []
            };

            const realTimeFeedback = analysisData.real_time_feedback;
            const detailedAnalysis = analysisData.detailed_analysis;

            // Generate quick wins from real-time feedback
            if (realTimeFeedback && realTimeFeedback.quick_wins) {
                suggestions.quick_wins = realTimeFeedback.quick_wins.map(qw => ({
                    id: `qw_${qw.analyzer}`,
                    type: 'quick_win',
                    analyzer: qw.analyzer,
                    title: this.getQuickWinTitle(qw.analyzer),
                    description: this.getQuickWinDescription(qw.analyzer, qw.current_score),
                    action_steps: this.getQuickWinActions(qw.analyzer),
                    estimated_time: this.getEstimatedTime(qw.analyzer),
                    impact_score: qw.potential_improvement,
                    difficulty: 'easy',
                    priority: 'medium'
                }));
            }

            // Generate priority fixes from real-time feedback
            if (realTimeFeedback && realTimeFeedback.priority_issues) {
                suggestions.priority_fixes = realTimeFeedback.priority_issues.map(issue => ({
                    id: `pf_${issue.analyzer}`,
                    type: 'priority_fix',
                    analyzer: issue.analyzer,
                    title: this.getPriorityFixTitle(issue.analyzer, issue.severity),
                    description: this.getPriorityFixDescription(issue.analyzer, issue.score),
                    action_steps: this.getQuickWinActions(issue.analyzer), // Reuse action steps
                    estimated_time: this.getEstimatedTime(issue.analyzer, true),
                    impact_score: this.calculateImpactScore(issue.score),
                    difficulty: issue.severity === 'critical' ? 'medium' : 'easy',
                    priority: issue.severity === 'critical' ? 'critical' : 'high',
                    severity: issue.severity
                }));
            }

            // Generate content strategy suggestions
            if (detailedAnalysis) {
                Object.entries(detailedAnalysis).forEach(([analyzer, data]) => {
                    if (data.score < 70) {
                        const strategySuggestion = this.generateStrategySuggestion(analyzer, data);
                        if (strategySuggestion.type === 'content') {
                            suggestions.content_strategy.push(strategySuggestion);
                        } else {
                            suggestions.technical_seo.push(strategySuggestion);
                        }
                    }
                });
            }

            return suggestions;
        }

        /**
         * Display suggestions in the UI
         */
        displaySuggestions(suggestions) {
            // Update tab badges
            this.updateTabBadges(suggestions);
            
            // Display quick wins
            this.displayQuickWins(suggestions.quick_wins || []);
            
            // Display priority fixes
            this.displayPriorityFixes(suggestions.priority_fixes || []);
            
            // Display strategy suggestions
            this.displayStrategy(suggestions.content_strategy || [], suggestions.technical_seo || []);
            
            // Update summary
            this.updateSuggestionsSummary(suggestions);
        }

        /**
         * Update tab badges with suggestion counts
         */
        updateTabBadges(suggestions) {
            const counts = {
                quick_wins: (suggestions.quick_wins || []).length,
                priority_fixes: (suggestions.priority_fixes || []).length,
                strategy: (suggestions.content_strategy || []).length + (suggestions.technical_seo || []).length
            };

            this.$tabs.each((index, tab) => {
                const $tab = $(tab);
                const tabName = $tab.data('tab');
                const count = counts[tabName] || 0;
                $tab.find('.tab-badge').text(count);
                
                // Add urgency class for priority fixes
                if (tabName === 'priority_fixes' && count > 0) {
                    $tab.addClass('has-urgent');
                } else {
                    $tab.removeClass('has-urgent');
                }
            });
        }

        /**
         * Display quick wins
         */
        displayQuickWins(quickWins) {
            const $container = this.$quickWinsList;
            $container.empty();

            if (quickWins.length === 0) {
                $container.html(`
                    <div class="no-suggestions">
                        <div class="no-suggestions-icon">💡</div>
                        <p>Great! No quick wins needed right now.</p>
                    </div>
                `);
                return;
            }

            quickWins.slice(0, this.options.maxQuickWins).forEach(suggestion => {
                const suggestionHtml = this.createSuggestionItem(suggestion);
                $container.append(suggestionHtml);
            });
        }

        /**
         * Display priority fixes
         */
        displayPriorityFixes(priorityFixes) {
            const $container = this.$priorityFixesList;
            $container.empty();

            if (priorityFixes.length === 0) {
                $container.html(`
                    <div class="no-suggestions">
                        <div class="no-suggestions-icon">✅</div>
                        <p>Excellent! No critical issues found.</p>
                    </div>
                `);
                return;
            }

            priorityFixes.slice(0, this.options.maxPriorityFixes).forEach(suggestion => {
                const suggestionHtml = this.createSuggestionItem(suggestion);
                $container.append(suggestionHtml);
            });
        }

        /**
         * Display strategy suggestions
         */
        displayStrategy(contentStrategy, technicalSeo) {
            // Content strategy
            const $contentContainer = this.$strategyList.find('#content-strategy .subsection-items');
            $contentContainer.empty();
            
            if (contentStrategy.length > 0) {
                contentStrategy.forEach(suggestion => {
                    const suggestionHtml = this.createSuggestionItem(suggestion);
                    $contentContainer.append(suggestionHtml);
                });
            } else {
                $contentContainer.html('<p class="no-items">Content strategy looks good!</p>');
            }

            // Technical SEO
            const $technicalContainer = this.$strategyList.find('#technical-seo .subsection-items');
            $technicalContainer.empty();
            
            if (technicalSeo.length > 0) {
                technicalSeo.forEach(suggestion => {
                    const suggestionHtml = this.createSuggestionItem(suggestion);
                    $technicalContainer.append(suggestionHtml);
                });
            } else {
                $technicalContainer.html('<p class="no-items">Technical SEO is optimized!</p>');
            }
        }

        /**
         * Create suggestion item HTML
         */
        createSuggestionItem(suggestion) {
            const priorityClass = suggestion.priority || 'medium';
            const difficultyClass = suggestion.difficulty || 'medium';
            const impactScore = suggestion.impact_score || 0;
            
            return $(`
                <div class="suggestion-item priority-${priorityClass} difficulty-${difficultyClass}" data-suggestion-id="${suggestion.id}">
                    <div class="suggestion-header">
                        <div class="suggestion-title">
                            <span class="suggestion-icon">${this.getSuggestionIcon(suggestion.type)}</span>
                            ${suggestion.title}
                        </div>
                        <div class="suggestion-meta">
                            <span class="impact-score" title="Impact Score">+${impactScore}</span>
                            <span class="estimated-time">${suggestion.estimated_time}</span>
                            ${suggestion.severity ? `<span class="severity-badge ${suggestion.severity}">${suggestion.severity}</span>` : ''}
                        </div>
                    </div>
                    
                    <div class="suggestion-description">
                        ${suggestion.description}
                    </div>
                    
                    <div class="suggestion-details" style="display: none;">
                        <div class="action-steps">
                            <h6>Action Steps:</h6>
                            <ol>
                                ${suggestion.action_steps.map(step => `<li>${step}</li>`).join('')}
                            </ol>
                        </div>
                        
                        <div class="suggestion-actions">
                            <button class="apply-suggestion btn btn-small btn-primary">
                                Apply Suggestion
                            </button>
                            <button class="dismiss-suggestion btn btn-small">
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
            `);
        }

        /**
         * Toggle suggestion details
         */
        toggleSuggestionDetails($suggestionItem) {
            const $details = $suggestionItem.find('.suggestion-details');
            const isExpanded = $details.is(':visible');
            
            if (isExpanded) {
                $details.slideUp(300);
                $suggestionItem.removeClass('expanded');
            } else {
                // Hide other expanded suggestions
                this.$panel.find('.suggestion-item.expanded .suggestion-details').slideUp(300);
                this.$panel.find('.suggestion-item').removeClass('expanded');
                
                // Show this suggestion's details
                $details.slideDown(300);
                $suggestionItem.addClass('expanded');
            }
        }

        /**
         * Apply suggestion (placeholder for future implementation)
         */
        applySuggestion($button) {
            const $suggestionItem = $button.closest('.suggestion-item');
            const suggestionId = $suggestionItem.data('suggestion-id');
            
            // Show loading state
            $button.prop('disabled', true).text('Applying...');
            
            // Placeholder: In a real implementation, this would apply the suggestion
            setTimeout(() => {
                $suggestionItem.addClass('applied');
                $button.text('Applied!').addClass('btn-success');
                
                // Trigger re-analysis after a delay
                setTimeout(() => {
                    if (window.khmSeoLiveAnalyzer) {
                        window.khmSeoLiveAnalyzer.forceAnalyze();
                    }
                }, 1000);
            }, 1500);
        }

        /**
         * Dismiss suggestion
         */
        dismissSuggestion($button) {
            const $suggestionItem = $button.closest('.suggestion-item');
            
            $suggestionItem.fadeOut(300, function() {
                $(this).remove();
                // Update counts and summary
                // This would also save dismissed suggestions to prevent showing again
            });
        }

        /**
         * Switch active tab
         */
        switchTab(tabName) {
            if (this.activeTab === tabName) return;
            
            this.activeTab = tabName;
            
            // Update tab buttons
            this.$tabs.removeClass('active');
            this.$tabs.filter(`[data-tab="${tabName}"]`).addClass('active');
            
            // Update tab content
            this.$tabContents.removeClass('active');
            $(`#tab-${tabName.replace('_', '-')}`).addClass('active');
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
         * Refresh suggestions
         */
        refreshSuggestions() {
            if (window.khmSeoLiveAnalyzer) {
                window.khmSeoLiveAnalyzer.forceAnalyze();
            }
        }

        /**
         * Show specific analyzer suggestions
         */
        showAnalyzerSuggestions(analyzer, data) {
            // Switch to appropriate tab and highlight relevant suggestions
            if (data.priority === 'critical' || data.priority === 'high') {
                this.switchTab('priority_fixes');
            } else {
                this.switchTab('quick_wins');
            }
            
            // Highlight suggestions for this analyzer
            setTimeout(() => {
                const $analyzerSuggestions = this.$panel.find(`[data-suggestion-id*="${analyzer}"]`);
                $analyzerSuggestions.addClass('highlight');
                
                if ($analyzerSuggestions.length > 0) {
                    $analyzerSuggestions[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                setTimeout(() => {
                    $analyzerSuggestions.removeClass('highlight');
                }, 3000);
            }, 100);
        }

        /**
         * Update suggestions summary
         */
        updateSuggestionsSummary(suggestions) {
            const totalCount = Object.values(suggestions).reduce((total, group) => {
                return total + (Array.isArray(group) ? group.length : 0);
            }, 0);
            
            this.$totalSuggestions.text(`${totalCount} suggestion${totalCount !== 1 ? 's' : ''} available`);
        }

        /**
         * Helper methods for generating suggestions
         */
        getQuickWinTitle(analyzer) {
            const titles = {
                keyword_density: 'Optimize Keyword Usage',
                meta_description: 'Add Meta Description',
                title_analysis: 'Improve Title Tag',
                heading_structure: 'Add Heading Tags',
                image_alt_tags: 'Add Image Alt Text',
                internal_links: 'Add Internal Links',
                readability: 'Improve Readability',
                content_length: 'Expand Content'
            };
            return titles[analyzer] || 'Optimization Opportunity';
        }

        getQuickWinDescription(analyzer, score) {
            // Return contextual description based on analyzer and score
            return `Improve your ${analyzer.replace('_', ' ')} to boost your SEO score.`;
        }

        getQuickWinActions(analyzer) {
            const actions = {
                keyword_density: ['Use focus keyword naturally in content', 'Include keyword variations', 'Aim for 1-3% keyword density'],
                meta_description: ['Write compelling 150-160 character description', 'Include focus keyword', 'Make it click-worthy'],
                title_analysis: ['Include focus keyword in title', 'Keep under 60 characters', 'Make it compelling'],
                heading_structure: ['Add H1 tag with keyword', 'Use H2/H3 for structure', 'Include keywords in headings'],
                image_alt_tags: ['Add descriptive alt text', 'Include relevant keywords', 'Keep concise and descriptive'],
                internal_links: ['Add 2-3 relevant internal links', 'Use descriptive anchor text', 'Link to related content'],
                readability: ['Use shorter sentences', 'Break up paragraphs', 'Use simple language'],
                content_length: ['Add more valuable information', 'Expand key points', 'Aim for at least 300 words']
            };
            return actions[analyzer] || ['Optimize this element'];
        }

        getEstimatedTime(analyzer, isPriority = false) {
            const baseTime = {
                keyword_density: 10,
                meta_description: 5,
                title_analysis: 3,
                heading_structure: 15,
                image_alt_tags: 8,
                internal_links: 12,
                readability: 20,
                content_length: 30
            };
            
            const time = baseTime[analyzer] || 10;
            const multiplier = isPriority ? 1.5 : 1;
            
            return `${Math.round(time * multiplier)} min`;
        }

        getPriorityFixTitle(analyzer, severity) {
            return `${severity.charAt(0).toUpperCase() + severity.slice(1)}: ${this.getQuickWinTitle(analyzer)}`;
        }

        getPriorityFixDescription(analyzer, score) {
            return `Critical SEO issue detected. Current score: ${score}/100. Immediate action required.`;
        }

        calculateImpactScore(score) {
            return Math.max(5, Math.round((100 - score) / 2));
        }

        generateStrategySuggestion(analyzer, data) {
            const contentAnalyzers = ['content_length', 'readability', 'heading_structure'];
            const technicalAnalyzers = ['meta_description', 'title_analysis', 'image_alt_tags'];
            
            return {
                id: `strategy_${analyzer}`,
                type: contentAnalyzers.includes(analyzer) ? 'content' : 'technical',
                analyzer: analyzer,
                title: `Strategic ${analyzer.replace('_', ' ')} Improvement`,
                description: `Long-term optimization plan for ${analyzer.replace('_', ' ')}.`,
                action_steps: this.getQuickWinActions(analyzer),
                estimated_time: this.getEstimatedTime(analyzer),
                impact_score: this.calculateImpactScore(data.score),
                difficulty: 'medium',
                priority: 'medium'
            };
        }

        getSuggestionIcon(type) {
            const icons = {
                quick_win: '⚡',
                priority_fix: '🚨',
                content: '📝',
                technical: '⚙️'
            };
            return icons[type] || '💡';
        }

        /**
         * Public methods
         */
        getCurrentSuggestions() {
            return this.currentSuggestions;
        }

        refresh() {
            this.refreshSuggestions();
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize suggestion panel in SEO meta box
        const $suggestionContainer = $('#khm-seo-suggestions-panel');
        if ($suggestionContainer.length) {
            window.khmSeoSuggestionPanel = new KHMSeoSuggestionPanel($suggestionContainer);
        }
        
        // Trigger custom event for other components
        $(document).trigger('khm-seo-suggestion-panel-ready');
    });

    // Export for other modules
    window.KHMSeoSuggestionPanel = KHMSeoSuggestionPanel;

})(jQuery);