/**
 * Live Analyzer - Real-time SEO content analysis
 * 
 * Provides real-time analysis of content as users type in WordPress editors.
 * Integrates with the Phase 1 Analysis Engine through AJAX calls.
 * 
 * @package KHMSeo\Editor
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Live Analyzer Class
     */
    class KHMSeoLiveAnalyzer {
        constructor(options = {}) {
            this.options = {
                ajaxUrl: khmSeoEditor.ajaxUrl,
                nonce: khmSeoEditor.nonce,
                debounceDelay: khmSeoEditor.config.analysis.debounce_delay || 500,
                minContentLength: khmSeoEditor.config.analysis.min_content_length || 50,
                targetScore: khmSeoEditor.config.analysis.target_score || 75,
                ...options
            };

            this.currentAnalysis = null;
            this.analysisCache = new Map();
            this.debounceTimer = null;
            this.isAnalyzing = false;
            this.callbacks = {
                onAnalysisStart: [],
                onAnalysisComplete: [],
                onAnalysisError: []
            };

            this.init();
        }

        /**
         * Initialize the live analyzer
         */
        init() {
            this.setupEventListeners();
            this.detectEditor();
            
            // Trigger initial analysis if content exists
            setTimeout(() => {
                const content = this.getContentData();
                if (content.content && content.content.length >= this.options.minContentLength) {
                    this.analyzeContent();
                }
            }, 1000);
        }

        /**
         * Setup event listeners for different editors
         */
        setupEventListeners() {
            const editorType = khmSeoEditor.currentEditor;

            switch (editorType) {
                case 'gutenberg':
                    this.setupGutenbergListeners();
                    break;
                case 'classic':
                    this.setupClassicEditorListeners();
                    break;
                case 'elementor':
                    this.setupElementorListeners();
                    break;
                default:
                    this.setupGenericListeners();
            }

            // Always listen for meta field changes
            this.setupMetaFieldListeners();
        }

        /**
         * Setup Gutenberg editor listeners
         */
        setupGutenbergListeners() {
            // Listen for block editor changes
            if (wp && wp.data) {
                let previousContent = '';
                
                wp.data.subscribe(() => {
                    const newContent = this.getGutenbergContent();
                    if (newContent !== previousContent) {
                        previousContent = newContent;
                        this.debouncedAnalyze();
                    }
                });
            }

            // Fallback: Listen for content area changes
            this.observeContentChanges('.editor-post-title__input, .block-editor-rich-text__editable');
        }

        /**
         * Setup Classic Editor listeners
         */
        setupClassicEditorListeners() {
            // TinyMCE editor
            $(document).on('tinymce-editor-setup', (event, editor) => {
                editor.on('input keyup change', () => {
                    this.debouncedAnalyze();
                });
            });

            // Text mode editor
            $('#content').on('input keyup change', () => {
                this.debouncedAnalyze();
            });

            // Title field
            $('#title').on('input keyup change', () => {
                this.debouncedAnalyze();
            });

            // Excerpt field
            $('#excerpt').on('input keyup change', () => {
                this.debouncedAnalyze();
            });
        }

        /**
         * Setup Elementor listeners
         */
        setupElementorListeners() {
            // Listen for Elementor changes
            if (window.elementor) {
                elementor.channels.editor.on('change', () => {
                    this.debouncedAnalyze();
                });
            }
        }

        /**
         * Setup generic listeners for unknown editors
         */
        setupGenericListeners() {
            this.observeContentChanges('textarea, input[type="text"], [contenteditable]');
        }

        /**
         * Setup meta field listeners
         */
        setupMetaFieldListeners() {
            // Focus keyword field
            $('#khm-seo-focus-keyword').on('input keyup change', () => {
                this.debouncedAnalyze();
            });

            // Custom meta description field
            $('#khm-seo-meta-description').on('input keyup change', () => {
                this.debouncedAnalyze();
            });

            // Custom title field
            $('#khm-seo-title').on('input keyup change', () => {
                this.debouncedAnalyze();
            });
        }

        /**
         * Observe content changes using MutationObserver
         */
        observeContentChanges(selector) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        this.debouncedAnalyze();
                    }
                });
            });

            $(selector).each((index, element) => {
                if (element) {
                    observer.observe(element, {
                        childList: true,
                        subtree: true,
                        characterData: true
                    });
                }
            });
        }

        /**
         * Detect current editor type
         */
        detectEditor() {
            if ($('.block-editor').length > 0) {
                this.editorType = 'gutenberg';
            } else if ($('#wp-content-wrap').length > 0) {
                this.editorType = 'classic';
            } else if (window.elementor) {
                this.editorType = 'elementor';
            } else {
                this.editorType = 'unknown';
            }
        }

        /**
         * Debounced analyze function
         */
        debouncedAnalyze() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.analyzeContent();
            }, this.options.debounceDelay);
        }

        /**
         * Main content analysis function
         */
        analyzeContent() {
            if (this.isAnalyzing) {
                return;
            }

            const contentData = this.getContentData();
            
            // Check minimum content length
            if (!contentData.content || contentData.content.length < this.options.minContentLength) {
                this.triggerCallback('onAnalysisComplete', {
                    type: 'insufficient_content',
                    message: khmSeoEditor.strings.insufficient_content || 'Add more content to analyze'
                });
                return;
            }

            // Check cache first
            const cacheKey = this.generateCacheKey(contentData);
            if (this.analysisCache.has(cacheKey)) {
                const cachedResult = this.analysisCache.get(cacheKey);
                this.triggerCallback('onAnalysisComplete', cachedResult);
                return;
            }

            this.performAnalysis(contentData, cacheKey);
        }

        /**
         * Perform the actual analysis via AJAX
         */
        performAnalysis(contentData, cacheKey) {
            this.isAnalyzing = true;
            this.triggerCallback('onAnalysisStart', contentData);

            const analysisData = {
                action: 'khm_seo_live_analysis',
                nonce: this.options.nonce,
                content: contentData.content,
                title: contentData.title,
                excerpt: contentData.excerpt,
                focus_keyword: contentData.focus_keyword
            };

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: analysisData,
                timeout: 10000, // 10 second timeout
                success: (response) => {
                    this.isAnalyzing = false;
                    
                    if (response.success) {
                        this.currentAnalysis = response.data;
                        
                        // Cache the result
                        this.addToCache(cacheKey, response.data);
                        
                        this.triggerCallback('onAnalysisComplete', response.data);
                    } else {
                        this.handleAnalysisError('Analysis failed: ' + (response.data || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    this.isAnalyzing = false;
                    this.handleAnalysisError(`Analysis request failed: ${status} - ${error}`);
                }
            });
        }

        /**
         * Get content data from current editor
         */
        getContentData() {
            const editorType = khmSeoEditor.currentEditor;
            let content = '';
            let title = '';
            let excerpt = '';

            switch (editorType) {
                case 'gutenberg':
                    content = this.getGutenbergContent();
                    title = this.getGutenbergTitle();
                    excerpt = this.getGutenbergExcerpt();
                    break;
                case 'classic':
                    content = this.getClassicContent();
                    title = $('#title').val() || '';
                    excerpt = $('#excerpt').val() || '';
                    break;
                case 'elementor':
                    content = this.getElementorContent();
                    title = $('#title').val() || '';
                    break;
                default:
                    content = this.getGenericContent();
                    title = $('#title').val() || $('input[name="post_title"]').val() || '';
                    excerpt = $('#excerpt').val() || $('textarea[name="excerpt"]').val() || '';
            }

            return {
                content: this.stripHtml(content),
                title: title,
                excerpt: excerpt,
                focus_keyword: $('#khm-seo-focus-keyword').val() || ''
            };
        }

        /**
         * Get Gutenberg editor content
         */
        getGutenbergContent() {
            if (wp && wp.data && wp.data.select) {
                const postContent = wp.data.select('core/editor').getEditedPostAttribute('content');
                return postContent || '';
            }
            
            // Fallback: get from DOM
            return $('.block-editor-rich-text__editable').map(function() {
                return $(this).text();
            }).get().join(' ');
        }

        /**
         * Get Gutenberg title
         */
        getGutenbergTitle() {
            if (wp && wp.data && wp.data.select) {
                return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
            }
            
            return $('.editor-post-title__input').val() || '';
        }

        /**
         * Get Gutenberg excerpt
         */
        getGutenbergExcerpt() {
            if (wp && wp.data && wp.data.select) {
                return wp.data.select('core/editor').getEditedPostAttribute('excerpt') || '';
            }
            
            return $('#excerpt').val() || '';
        }

        /**
         * Get Classic Editor content
         */
        getClassicContent() {
            // Try TinyMCE first
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                return tinyMCE.get('content').getContent();
            }
            
            // Fallback to textarea
            return $('#content').val() || '';
        }

        /**
         * Get Elementor content
         */
        getElementorContent() {
            if (window.elementor && elementor.getDocument) {
                // Get content from Elementor
                const elementorData = elementor.getDocument().container.model.get('elements');
                return this.extractElementorText(elementorData);
            }
            
            return '';
        }

        /**
         * Extract text from Elementor data
         */
        extractElementorText(elements) {
            let text = '';
            
            if (Array.isArray(elements)) {
                elements.forEach(element => {
                    if (element.elements) {
                        text += this.extractElementorText(element.elements);
                    } else if (element.settings && element.settings.editor) {
                        text += element.settings.editor + ' ';
                    }
                });
            }
            
            return text;
        }

        /**
         * Get content from generic editor
         */
        getGenericContent() {
            let content = '';
            
            // Try common content areas
            const contentSelectors = [
                '#content',
                '.wp-editor-area',
                '[name="content"]',
                '.editor-content',
                '[contenteditable="true"]'
            ];
            
            for (const selector of contentSelectors) {
                const element = $(selector).first();
                if (element.length) {
                    content = element.val() || element.text() || element.html();
                    if (content) break;
                }
            }
            
            return content;
        }

        /**
         * Strip HTML tags from content
         */
        stripHtml(html) {
            const tmp = document.createElement('DIV');
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || '';
        }

        /**
         * Generate cache key for content
         */
        generateCacheKey(contentData) {
            const dataString = JSON.stringify(contentData);
            return this.hashCode(dataString).toString();
        }

        /**
         * Simple hash function for cache keys
         */
        hashCode(str) {
            let hash = 0;
            if (str.length === 0) return hash;
            
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32-bit integer
            }
            
            return hash;
        }

        /**
         * Add result to cache
         */
        addToCache(key, data) {
            // Implement simple LRU cache
            if (this.analysisCache.size >= 20) { // Max 20 cached items
                const firstKey = this.analysisCache.keys().next().value;
                this.analysisCache.delete(firstKey);
            }
            
            this.analysisCache.set(key, data);
        }

        /**
         * Handle analysis errors
         */
        handleAnalysisError(errorMessage) {
            console.error('KHM SEO Live Analysis Error:', errorMessage);
            this.triggerCallback('onAnalysisError', {
                message: errorMessage,
                timestamp: new Date().toISOString()
            });
        }

        /**
         * Add event callback
         */
        on(event, callback) {
            if (this.callbacks[event]) {
                this.callbacks[event].push(callback);
            }
        }

        /**
         * Remove event callback
         */
        off(event, callback) {
            if (this.callbacks[event]) {
                const index = this.callbacks[event].indexOf(callback);
                if (index > -1) {
                    this.callbacks[event].splice(index, 1);
                }
            }
        }

        /**
         * Trigger callback
         */
        triggerCallback(event, data) {
            if (this.callbacks[event]) {
                this.callbacks[event].forEach(callback => {
                    try {
                        callback(data);
                    } catch (error) {
                        console.error(`Error in ${event} callback:`, error);
                    }
                });
            }
        }

        /**
         * Get current analysis results
         */
        getCurrentAnalysis() {
            return this.currentAnalysis;
        }

        /**
         * Clear analysis cache
         */
        clearCache() {
            this.analysisCache.clear();
        }

        /**
         * Force re-analysis
         */
        forceAnalyze() {
            this.clearCache();
            this.analyzeContent();
        }

        /**
         * Get cache statistics
         */
        getCacheStats() {
            return {
                size: this.analysisCache.size,
                keys: Array.from(this.analysisCache.keys())
            };
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize on post edit pages
        if ($('body').hasClass('post-php') || $('body').hasClass('post-new-php')) {
            window.khmSeoLiveAnalyzer = new KHMSeoLiveAnalyzer();
            
            // Trigger custom event for other components
            $(document).trigger('khm-seo-live-analyzer-ready');
        }
    });

    // Export for other modules
    window.KHMSeoLiveAnalyzer = KHMSeoLiveAnalyzer;

})(jQuery);