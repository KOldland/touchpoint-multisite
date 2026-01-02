/**
 * SEO Score Display - Interactive visual score components
 * 
 * Handles the display and animation of SEO scores, progress indicators,
 * and visual feedback for real-time content analysis.
 * 
 * @package KHMSeo\Editor
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * SEO Score Display Class
     */
    class KHMSeoScoreDisplay {
        constructor(container, options = {}) {
            this.container = $(container);
            this.options = {
                animationDuration: 1000,
                scoreColors: {
                    excellent: '#46b450',
                    good: '#00a32a', 
                    needs_improvement: '#ffb900',
                    poor: '#dc3232'
                },
                targetScore: 75,
                ...options
            };

            this.currentScore = 0;
            this.currentAnalysis = null;
            this.animationInProgress = false;

            this.init();
        }

        /**
         * Initialize the score display
         */
        init() {
            this.createScoreContainer();
            this.bindEvents();
        }

        /**
         * Create the main score display container
         */
        createScoreContainer() {
            const html = `
                <div class="khm-seo-score-display">
                    <div class="score-loading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <p>${khmSeoEditor.strings.analyzing || 'Analyzing content...'}</p>
                    </div>
                    
                    <div class="score-main-display" style="display: none;">
                        <div class="score-overview">
                            <div class="score-circle-container">
                                <div class="score-circle">
                                    <div class="score-value">0</div>
                                    <div class="score-label">SEO</div>
                                </div>
                                <div class="score-ring">
                                    <svg class="score-ring-svg" width="80" height="80">
                                        <circle class="score-ring-bg" cx="40" cy="40" r="35" fill="none" stroke="#e1e1e1" stroke-width="6"/>
                                        <circle class="score-ring-progress" cx="40" cy="40" r="35" fill="none" stroke="#46b450" stroke-width="6" 
                                                stroke-dasharray="220" stroke-dashoffset="220" transform="rotate(-90 40 40)"/>
                                    </svg>
                                </div>
                            </div>
                            
                            <div class="score-description">
                                <h4 class="score-title">Analyzing...</h4>
                                <p class="score-message">Please wait while we analyze your content.</p>
                            </div>
                        </div>

                        <div class="progress-indicators">
                            <div class="progress-section">
                                <div class="progress-label">Optimization Progress: <span class="progress-percentage">0%</span></div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: 0%;"></div>
                                </div>
                            </div>
                            
                            <div class="check-counters">
                                <div class="check-item passed">
                                    <span class="check-icon">✓</span>
                                    <span class="check-count">0</span>
                                    <span class="check-label">Passed</span>
                                </div>
                                
                                <div class="check-item warning">
                                    <span class="check-icon">⚠</span>
                                    <span class="check-count">0</span>
                                    <span class="check-label">Warnings</span>
                                </div>
                                
                                <div class="check-item failed">
                                    <span class="check-icon">✗</span>
                                    <span class="check-count">0</span>
                                    <span class="check-label">Issues</span>
                                </div>
                            </div>
                        </div>

                        <div class="analyzer-scores">
                            <h4>SEO Analysis Details</h4>
                            <div class="analyzer-grid"></div>
                        </div>

                        <div class="real-time-status">
                            <div class="status-message"></div>
                            <div class="performance-indicator" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="score-error" style="display: none;">
                        <div class="error-icon">⚠</div>
                        <div class="error-message">Analysis failed. Please try again.</div>
                        <button class="retry-analysis btn btn-small">Retry Analysis</button>
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
            this.$scoreDisplay = this.container.find('.khm-seo-score-display');
            this.$loading = this.$scoreDisplay.find('.score-loading');
            this.$mainDisplay = this.$scoreDisplay.find('.score-main-display');
            this.$error = this.$scoreDisplay.find('.score-error');
            
            this.$scoreCircle = this.$scoreDisplay.find('.score-circle');
            this.$scoreValue = this.$scoreDisplay.find('.score-value');
            this.$scoreRing = this.$scoreDisplay.find('.score-ring-progress');
            this.$scoreTitle = this.$scoreDisplay.find('.score-title');
            this.$scoreMessage = this.$scoreDisplay.find('.score-message');
            
            this.$progressBar = this.$scoreDisplay.find('.progress-bar');
            this.$progressPercentage = this.$scoreDisplay.find('.progress-percentage');
            this.$checkCounters = this.$scoreDisplay.find('.check-counters');
            
            this.$analyzerGrid = this.$scoreDisplay.find('.analyzer-grid');
            this.$statusMessage = this.$scoreDisplay.find('.status-message');
            this.$performanceIndicator = this.$scoreDisplay.find('.performance-indicator');
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Listen for live analyzer events
            $(document).on('khm-seo-live-analyzer-ready', () => {
                if (window.khmSeoLiveAnalyzer) {
                    window.khmSeoLiveAnalyzer.on('onAnalysisStart', (data) => {
                        this.showLoading();
                    });

                    window.khmSeoLiveAnalyzer.on('onAnalysisComplete', (data) => {
                        this.displayAnalysisResults(data);
                    });

                    window.khmSeoLiveAnalyzer.on('onAnalysisError', (error) => {
                        this.showError(error.message);
                    });
                }
            });

            // Retry button
            this.$error.find('.retry-analysis').on('click', () => {
                if (window.khmSeoLiveAnalyzer) {
                    window.khmSeoLiveAnalyzer.forceAnalyze();
                }
            });

            // Analyzer item clicks for detailed view
            this.$analyzerGrid.on('click', '.analyzer-item', (e) => {
                this.showAnalyzerDetails($(e.currentTarget));
            });
        }

        /**
         * Show loading state
         */
        showLoading() {
            this.$loading.show();
            this.$mainDisplay.hide();
            this.$error.hide();
        }

        /**
         * Show error state
         */
        showError(message) {
            this.$error.find('.error-message').text(message);
            this.$error.show();
            this.$loading.hide();
            this.$mainDisplay.hide();
        }

        /**
         * Display analysis results
         */
        displayAnalysisResults(data) {
            this.currentAnalysis = data;
            
            if (data.type === 'insufficient_content') {
                this.showInsufficientContent(data.message);
                return;
            }

            this.$loading.hide();
            this.$error.hide();
            this.$mainDisplay.show();

            // Update overall score
            this.updateOverallScore(data.overall_score);
            
            // Update progress indicators
            if (data.real_time_feedback && data.real_time_feedback.progress_indicators) {
                this.updateProgressIndicators(data.real_time_feedback.progress_indicators);
            }
            
            // Update analyzer scores
            if (data.detailed_analysis) {
                this.updateAnalyzerScores(data.detailed_analysis);
            }
            
            // Update status
            if (data.real_time_feedback) {
                this.updateRealTimeStatus(data.real_time_feedback);
            }
            
            // Update performance indicator
            if (data.performance) {
                this.updatePerformanceIndicator(data.performance);
            }
        }

        /**
         * Show insufficient content message
         */
        showInsufficientContent(message) {
            this.$loading.hide();
            this.$error.hide();
            this.$mainDisplay.show();
            
            this.$scoreValue.text('--');
            this.$scoreTitle.text('Insufficient Content');
            this.$scoreMessage.text(message);
            this.$scoreCircle.removeClass('score-excellent score-good score-needs-improvement score-poor')
                           .addClass('score-insufficient');
        }

        /**
         * Update overall score with animation
         */
        updateOverallScore(score) {
            if (this.animationInProgress) return;
            
            const targetScore = Math.round(score);
            const startScore = this.currentScore;
            const status = this.getScoreStatus(targetScore);
            
            this.animationInProgress = true;
            
            // Update score circle class
            this.$scoreCircle.removeClass('score-excellent score-good score-needs-improvement score-poor score-insufficient')
                           .addClass(`score-${status}`);
            
            // Animate score number
            const duration = this.options.animationDuration;
            const steps = duration / 16; // 60fps
            const increment = (targetScore - startScore) / steps;
            let currentStep = 0;
            
            const scoreAnimation = () => {
                currentStep++;
                const currentScore = Math.round(startScore + (increment * currentStep));
                
                this.$scoreValue.text(currentScore);
                
                if (currentStep < steps) {
                    requestAnimationFrame(scoreAnimation);
                } else {
                    this.$scoreValue.text(targetScore);
                    this.currentScore = targetScore;
                    this.animationInProgress = false;
                }
            };
            
            requestAnimationFrame(scoreAnimation);
            
            // Animate progress ring
            this.animateScoreRing(targetScore);
            
            // Update title and description
            this.$scoreTitle.text(this.getScoreTitle(status));
            this.$scoreMessage.text(this.getScoreDescription(status, targetScore));
        }

        /**
         * Animate the score ring
         */
        animateScoreRing(score) {
            const circumference = 2 * Math.PI * 35; // radius = 35
            const percentage = Math.max(0, Math.min(100, score));
            const offset = circumference - (percentage / 100) * circumference;
            
            // Update ring color based on score
            const color = this.options.scoreColors[this.getScoreStatus(score)];
            this.$scoreRing.css('stroke', color);
            
            // Animate the stroke-dashoffset
            this.$scoreRing.css({
                'stroke-dasharray': circumference,
                'stroke-dashoffset': circumference
            });
            
            setTimeout(() => {
                this.$scoreRing.css({
                    'transition': `stroke-dashoffset ${this.options.animationDuration}ms ease-out`,
                    'stroke-dashoffset': offset
                });
            }, 100);
        }

        /**
         * Update progress indicators
         */
        updateProgressIndicators(indicators) {
            const completion = indicators.completion_percentage || 0;
            const passedChecks = indicators.passed_checks || 0;
            const warningChecks = indicators.warning_checks || 0;
            const failedChecks = indicators.failed_checks || 0;
            
            // Update progress bar
            this.$progressBar.css('width', `${completion}%`);
            this.$progressPercentage.text(`${completion}%`);
            
            // Update check counters with animation
            this.animateCounter(this.$checkCounters.find('.passed .check-count'), passedChecks);
            this.animateCounter(this.$checkCounters.find('.warning .check-count'), warningChecks);
            this.animateCounter(this.$checkCounters.find('.failed .check-count'), failedChecks);
        }

        /**
         * Animate counter numbers
         */
        animateCounter($element, targetValue) {
            const startValue = parseInt($element.text()) || 0;
            const duration = 500;
            const steps = duration / 16;
            const increment = (targetValue - startValue) / steps;
            let currentStep = 0;
            
            const counterAnimation = () => {
                currentStep++;
                const currentValue = Math.round(startValue + (increment * currentStep));
                
                $element.text(currentValue);
                
                if (currentStep < steps) {
                    requestAnimationFrame(counterAnimation);
                } else {
                    $element.text(targetValue);
                }
            };
            
            if (startValue !== targetValue) {
                requestAnimationFrame(counterAnimation);
            }
        }

        /**
         * Update analyzer scores grid
         */
        updateAnalyzerScores(detailedAnalysis) {
            this.$analyzerGrid.empty();
            
            Object.entries(detailedAnalysis).forEach(([analyzer, data]) => {
                const analyzerHtml = this.createAnalyzerItem(analyzer, data);
                this.$analyzerGrid.append(analyzerHtml);
            });
            
            // Add stagger animation to analyzer items
            this.$analyzerGrid.find('.analyzer-item').each((index, element) => {
                $(element).css({
                    'animation-delay': `${index * 100}ms`,
                    'animation': 'fadeInUp 0.5s ease-out forwards'
                });
            });
        }

        /**
         * Create analyzer item HTML
         */
        createAnalyzerItem(analyzer, data) {
            const score = data.score || 0;
            const status = data.status || this.getScoreStatus(score);
            const message = data.message || '';
            const priority = data.priority || 'low';
            
            const displayName = this.getAnalyzerDisplayName(analyzer);
            const statusIcon = this.getStatusIcon(status);
            const statusText = this.getStatusText(status);
            const scoreColor = this.options.scoreColors[status] || '#666';
            
            return $(`
                <div class="analyzer-item analyzer-${status} priority-${priority}" data-analyzer="${analyzer}">
                    <div class="analyzer-header">
                        <div class="analyzer-name">${displayName}</div>
                        <div class="analyzer-score-badge" style="background-color: ${scoreColor};">
                            ${score}
                        </div>
                    </div>
                    
                    <div class="analyzer-status">
                        <span class="status-icon">${statusIcon}</span>
                        <span class="status-text">${statusText}</span>
                    </div>
                    
                    ${message ? `<div class="analyzer-message">${message}</div>` : ''}
                    
                    ${priority === 'critical' || priority === 'high' ? 
                        `<div class="priority-indicator priority-${priority}">
                            ${priority === 'critical' ? 'Critical Issue' : 'High Priority'}
                        </div>` : ''
                    }
                    
                    <div class="analyzer-actions" style="display: none;">
                        <button class="btn-small show-details">View Details</button>
                        <button class="btn-small show-suggestions">Get Suggestions</button>
                    </div>
                </div>
            `);
        }

        /**
         * Update real-time status
         */
        updateRealTimeStatus(realTimeFeedback) {
            const status = realTimeFeedback.status || 'unknown';
            const message = this.getRealTimeStatusMessage(status);
            
            this.$statusMessage.text(message);
            
            // Update status styling
            this.$statusMessage.parent().removeClass('status-optimized status-good status-needs_work status-poor status-insufficient_content')
                                        .addClass(`status-${status}`);
        }

        /**
         * Update performance indicator
         */
        updatePerformanceIndicator(performance) {
            if (performance.analysis_time) {
                const analysisTime = performance.analysis_time;
                this.$performanceIndicator.text(`Analyzed in ${analysisTime}ms`).show();
            }
        }

        /**
         * Show analyzer details modal/popup
         */
        showAnalyzerDetails($item) {
            const analyzer = $item.data('analyzer');
            const data = this.currentAnalysis?.detailed_analysis?.[analyzer];
            
            if (!data) return;
            
            // Show details in existing suggestions panel or create popup
            $(document).trigger('khm-seo-show-analyzer-details', {
                analyzer: analyzer,
                data: data
            });
        }

        /**
         * Helper methods for status and display
         */
        getScoreStatus(score) {
            if (score >= 80) return 'excellent';
            if (score >= 60) return 'good';
            if (score >= 40) return 'needs_improvement';
            return 'poor';
        }

        getScoreTitle(status) {
            const titles = {
                excellent: 'Excellent SEO',
                good: 'Good SEO',
                needs_improvement: 'Needs Improvement',
                poor: 'Poor SEO',
                insufficient: 'Insufficient Content'
            };
            return titles[status] || 'Unknown';
        }

        getScoreDescription(status, score) {
            const descriptions = {
                excellent: 'Your content is well optimized for search engines!',
                good: 'Your content is good but has room for improvement.',
                needs_improvement: 'Your content needs optimization to perform better.',
                poor: 'Your content requires significant SEO improvements.',
                insufficient: 'Add more content to get SEO analysis.'
            };
            return descriptions[status] || 'Unable to determine content quality.';
        }

        getAnalyzerDisplayName(analyzer) {
            const names = {
                keyword_density: 'Keyword Usage',
                meta_description: 'Meta Description',
                title_analysis: 'Title Optimization',
                heading_structure: 'Heading Structure',
                image_alt_tags: 'Image Alt Tags',
                internal_links: 'Internal Linking',
                readability: 'Content Readability',
                content_length: 'Content Length'
            };
            return names[analyzer] || analyzer.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        getStatusIcon(status) {
            const icons = {
                excellent: '✓',
                good: '✓',
                needs_improvement: '⚠',
                poor: '✗',
                insufficient: '📝'
            };
            return icons[status] || '?';
        }

        getStatusText(status) {
            const texts = {
                excellent: 'Excellent',
                good: 'Good',
                needs_improvement: 'Needs Work',
                poor: 'Poor',
                insufficient: 'No Data'
            };
            return texts[status] || 'Unknown';
        }

        getRealTimeStatusMessage(status) {
            const messages = {
                optimized: '✓ Content is well optimized',
                good: '👍 Content is in good shape',
                needs_work: '⚠ Content needs optimization',
                poor: '⚠ Content requires significant improvement',
                insufficient_content: '📝 Add more content to analyze',
                analyzing: '🔄 Analyzing content...',
                unknown: '❓ Analysis status unknown'
            };
            return messages[status] || 'Status unknown';
        }

        /**
         * Public methods for external control
         */
        refresh() {
            if (window.khmSeoLiveAnalyzer) {
                window.khmSeoLiveAnalyzer.forceAnalyze();
            }
        }

        getCurrentScore() {
            return this.currentScore;
        }

        getCurrentAnalysis() {
            return this.currentAnalysis;
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize score display in SEO meta box
        const $scoreContainer = $('#khm-seo-score-display');
        if ($scoreContainer.length) {
            window.khmSeoScoreDisplay = new KHMSeoScoreDisplay($scoreContainer);
        }
        
        // Trigger custom event for other components
        $(document).trigger('khm-seo-score-display-ready');
    });

    // Export for other modules
    window.KHMSeoScoreDisplay = KHMSeoScoreDisplay;

})(jQuery);