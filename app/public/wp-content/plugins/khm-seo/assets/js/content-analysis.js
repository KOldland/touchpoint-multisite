/**
 * KHM SEO Content Analysis JavaScript
 * Provides real-time SEO analysis for content editing
 */

(function($) {
    'use strict';

    // Content Analysis Engine
    const ContentAnalysis = {
        // Initialize analysis
        init: function() {
            this.bindEvents();
            this.performInitialAnalysis();
        },

        // Bind events for real-time analysis
        bindEvents: function() {
            let analysisTimer;
            
            // Monitor content changes
            $('#content, #title, #khm_seo_focus_keyword, #khm_seo_meta_description').on('input keyup paste', function() {
                clearTimeout(analysisTimer);
                analysisTimer = setTimeout(() => {
                    ContentAnalysis.performAnalysis();
                }, 1000);
            });

            // Monitor focus keyword changes
            $('#khm_seo_focus_keyword').on('blur', function() {
                ContentAnalysis.updateKeywordRelatedAnalysis();
            });
        },

        // Perform initial analysis on page load
        performInitialAnalysis: function() {
            if ($('#content').length || $('#title').length) {
                this.performAnalysis();
            }
        },

        // Main analysis function
        performAnalysis: function() {
            const content = this.getContent();
            const title = this.getTitle();
            const focusKeyword = this.getFocusKeyword();
            const metaDescription = this.getMetaDescription();

            // Perform individual analyses
            const titleAnalysis = this.analyzeTitleSEO(title, focusKeyword);
            const contentAnalysis = this.analyzeContentSEO(content, focusKeyword);
            const metaAnalysis = this.analyzeMetaDescription(metaDescription, focusKeyword);
            const readabilityAnalysis = this.analyzeReadability(content);
            const keywordAnalysis = this.analyzeKeywordDensity(content, focusKeyword);

            // Calculate overall score
            const overallScore = this.calculateOverallScore([
                titleAnalysis.score,
                contentAnalysis.score,
                metaAnalysis.score,
                readabilityAnalysis.score,
                keywordAnalysis.score
            ]);

            // Update UI
            this.updateAnalysisDisplay({
                overall: overallScore,
                title: titleAnalysis,
                content: contentAnalysis,
                meta: metaAnalysis,
                readability: readabilityAnalysis,
                keyword: keywordAnalysis
            });
        },

        // Get content from editor
        getContent: function() {
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                return tinymce.get('content').getContent();
            }
            return $('#content').val() || '';
        },

        // Get title
        getTitle: function() {
            return $('#title').val() || '';
        },

        // Get focus keyword
        getFocusKeyword: function() {
            return $('#khm_seo_focus_keyword').val() || '';
        },

        // Get meta description
        getMetaDescription: function() {
            return $('#khm_seo_meta_description').val() || '';
        },

        // Analyze title SEO
        analyzeTitleSEO: function(title, focusKeyword) {
            const results = {
                score: 0,
                items: []
            };

            // Title length check
            if (title.length === 0) {
                results.items.push({
                    status: 'bad',
                    text: 'No title set. Add a title to improve SEO.'
                });
            } else if (title.length < 30) {
                results.items.push({
                    status: 'bad',
                    text: 'Title is too short. Aim for 50-60 characters.'
                });
                results.score += 30;
            } else if (title.length > 60) {
                results.items.push({
                    status: 'okay',
                    text: 'Title might be too long. Consider shortening for better display.'
                });
                results.score += 70;
            } else {
                results.items.push({
                    status: 'good',
                    text: 'Title length is optimal!'
                });
                results.score += 100;
            }

            // Focus keyword in title
            if (focusKeyword && title.toLowerCase().includes(focusKeyword.toLowerCase())) {
                results.items.push({
                    status: 'good',
                    text: 'Focus keyword found in title!'
                });
                results.score += 100;
            } else if (focusKeyword) {
                results.items.push({
                    status: 'bad',
                    text: 'Focus keyword not found in title.'
                });
            }

            return results;
        },

        // Analyze content SEO
        analyzeContentSEO: function(content, focusKeyword) {
            const results = {
                score: 0,
                items: []
            };

            const textContent = this.stripHTML(content);
            const wordCount = this.countWords(textContent);

            // Content length
            if (wordCount === 0) {
                results.items.push({
                    status: 'bad',
                    text: 'No content found. Add content to improve SEO.'
                });
            } else if (wordCount < 300) {
                results.items.push({
                    status: 'bad',
                    text: `Content is too short (${wordCount} words). Aim for at least 300 words.`
                });
                results.score += 30;
            } else if (wordCount < 600) {
                results.items.push({
                    status: 'okay',
                    text: `Content length is okay (${wordCount} words). Consider adding more for better SEO.`
                });
                results.score += 70;
            } else {
                results.items.push({
                    status: 'good',
                    text: `Content length is great (${wordCount} words)!`
                });
                results.score += 100;
            }

            // Focus keyword in content
            if (focusKeyword && wordCount > 0) {
                const keywordCount = this.countKeywordOccurrences(textContent, focusKeyword);
                const density = (keywordCount / wordCount) * 100;

                if (keywordCount === 0) {
                    results.items.push({
                        status: 'bad',
                        text: 'Focus keyword not found in content.'
                    });
                } else if (density < 0.5) {
                    results.items.push({
                        status: 'okay',
                        text: `Focus keyword density is low (${density.toFixed(1)}%). Consider using it more.`
                    });
                    results.score += 50;
                } else if (density > 3) {
                    results.items.push({
                        status: 'bad',
                        text: `Focus keyword density is too high (${density.toFixed(1)}%). Reduce usage to avoid keyword stuffing.`
                    });
                    results.score += 30;
                } else {
                    results.items.push({
                        status: 'good',
                        text: `Focus keyword density is optimal (${density.toFixed(1)}%)!`
                    });
                    results.score += 100;
                }
            }

            // Check for headings
            const headings = content.match(/<h[1-6][^>]*>.*?<\/h[1-6]>/gi);
            if (!headings || headings.length === 0) {
                results.items.push({
                    status: 'bad',
                    text: 'No headings found. Add H2-H6 headings to structure your content.'
                });
            } else {
                results.items.push({
                    status: 'good',
                    text: `Found ${headings.length} heading(s). Good content structure!`
                });
                results.score += 80;
            }

            return results;
        },

        // Analyze meta description
        analyzeMetaDescription: function(metaDescription, focusKeyword) {
            const results = {
                score: 0,
                items: []
            };

            if (metaDescription.length === 0) {
                results.items.push({
                    status: 'bad',
                    text: 'No meta description set. Add one to improve click-through rates.'
                });
            } else if (metaDescription.length < 120) {
                results.items.push({
                    status: 'okay',
                    text: 'Meta description is short. Consider expanding for better results.'
                });
                results.score += 70;
            } else if (metaDescription.length > 160) {
                results.items.push({
                    status: 'bad',
                    text: 'Meta description is too long. It may be truncated in search results.'
                });
                results.score += 50;
            } else {
                results.items.push({
                    status: 'good',
                    text: 'Meta description length is optimal!'
                });
                results.score += 100;
            }

            // Focus keyword in meta description
            if (focusKeyword && metaDescription.toLowerCase().includes(focusKeyword.toLowerCase())) {
                results.items.push({
                    status: 'good',
                    text: 'Focus keyword found in meta description!'
                });
                results.score += 100;
            } else if (focusKeyword) {
                results.items.push({
                    status: 'bad',
                    text: 'Focus keyword not found in meta description.'
                });
            }

            return results;
        },

        // Analyze readability
        analyzeReadability: function(content) {
            const results = {
                score: 0,
                items: []
            };

            const textContent = this.stripHTML(content);
            const sentences = this.countSentences(textContent);
            const words = this.countWords(textContent);

            if (words === 0) {
                return results;
            }

            // Average sentence length
            const avgSentenceLength = words / sentences;
            if (avgSentenceLength > 25) {
                results.items.push({
                    status: 'bad',
                    text: 'Average sentence length is too long. Consider shorter sentences.'
                });
                results.score += 30;
            } else if (avgSentenceLength > 20) {
                results.items.push({
                    status: 'okay',
                    text: 'Average sentence length is acceptable but could be improved.'
                });
                results.score += 70;
            } else {
                results.items.push({
                    status: 'good',
                    text: 'Sentences are an appropriate length!'
                });
                results.score += 100;
            }

            // Check for transition words
            const transitionWords = ['however', 'therefore', 'furthermore', 'moreover', 'additionally', 'consequently', 'meanwhile', 'nevertheless'];
            const hasTransitions = transitionWords.some(word => 
                textContent.toLowerCase().includes(word.toLowerCase())
            );

            if (hasTransitions) {
                results.items.push({
                    status: 'good',
                    text: 'Great use of transition words!'
                });
                results.score += 80;
            } else {
                results.items.push({
                    status: 'okay',
                    text: 'Consider using transition words to improve flow.'
                });
                results.score += 50;
            }

            return results;
        },

        // Analyze keyword density
        analyzeKeywordDensity: function(content, focusKeyword) {
            const results = {
                score: 0,
                items: []
            };

            if (!focusKeyword) {
                results.items.push({
                    status: 'bad',
                    text: 'No focus keyword set. Add one to optimize your content.'
                });
                return results;
            }

            const textContent = this.stripHTML(content);
            const words = this.countWords(textContent);
            const keywordCount = this.countKeywordOccurrences(textContent, focusKeyword);

            if (words === 0) {
                return results;
            }

            const density = (keywordCount / words) * 100;

            results.items.push({
                status: 'info',
                text: `Focus keyword appears ${keywordCount} times (${density.toFixed(1)}% density).`
            });

            if (density >= 0.5 && density <= 2.5) {
                results.score = 100;
            } else if (density >= 0.3 && density <= 3) {
                results.score = 70;
            } else {
                results.score = 30;
            }

            return results;
        },

        // Calculate overall score
        calculateOverallScore: function(scores) {
            const validScores = scores.filter(score => score > 0);
            if (validScores.length === 0) return 0;
            
            return Math.round(validScores.reduce((a, b) => a + b, 0) / validScores.length);
        },

        // Update analysis display
        updateAnalysisDisplay: function(analysis) {
            const container = $('#khm-seo-analysis-results');
            if (!container.length) return;

            let html = '<div class="khm-seo-analysis-overall">';
            html += `<div class="khm-seo-score ${this.getScoreClass(analysis.overall)}">${analysis.overall}/100</div>`;
            html += '<h4>SEO Analysis Results</h4>';
            html += '</div>';

            // Add individual analysis sections
            const sections = {
                'Title SEO': analysis.title,
                'Content SEO': analysis.content,
                'Meta Description': analysis.meta,
                'Readability': analysis.readability,
                'Keyword Usage': analysis.keyword
            };

            Object.keys(sections).forEach(sectionName => {
                const section = sections[sectionName];
                if (section.items && section.items.length > 0) {
                    html += `<div class="khm-seo-analysis-section">`;
                    html += `<h5>${sectionName}</h5>`;
                    html += '<ul>';
                    
                    section.items.forEach(item => {
                        html += `<li class="khm-seo-analysis-item ${item.status}">${item.text}</li>`;
                    });
                    
                    html += '</ul></div>';
                }
            });

            container.html(html);
        },

        // Get score class for styling
        getScoreClass: function(score) {
            if (score >= 80) return 'good';
            if (score >= 60) return 'okay';
            return 'bad';
        },

        // Helper functions
        stripHTML: function(html) {
            const div = document.createElement('div');
            div.innerHTML = html;
            return div.textContent || div.innerText || '';
        },

        countWords: function(text) {
            return text.trim().split(/\s+/).filter(word => word.length > 0).length;
        },

        countSentences: function(text) {
            return text.split(/[.!?]+/).filter(sentence => sentence.trim().length > 0).length;
        },

        countKeywordOccurrences: function(text, keyword) {
            if (!keyword) return 0;
            const regex = new RegExp(keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
            const matches = text.match(regex);
            return matches ? matches.length : 0;
        },

        // Update keyword-related analysis when focus keyword changes
        updateKeywordRelatedAnalysis: function() {
            this.performAnalysis();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ContentAnalysis.init();
    });

    // Export for external use
    window.KHMSEOContentAnalysis = ContentAnalysis;

})(jQuery);