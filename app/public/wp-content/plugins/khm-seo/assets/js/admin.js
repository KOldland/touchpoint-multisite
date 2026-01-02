/**
 * KHM SEO Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        initSEOAnalysis();
        initFormValidation();
        initTabs();
        initTooltips();
    });

    /**
     * Initialize SEO content analysis
     */
    function initSEOAnalysis() {
        // Real-time analysis on content change
        var analysisTimeout;
        
        $('#content, #title, #khm_seo_focus_keyword').on('input keyup', function() {
            clearTimeout(analysisTimeout);
            analysisTimeout = setTimeout(performAnalysis, 1000);
        });

        // Initial analysis
        if ($('#content').length) {
            performAnalysis();
        }
    }

    /**
     * Perform SEO analysis
     */
    function performAnalysis() {
        var content = getContent();
        var title = $('#title').val() || '';
        var focusKeyword = $('#khm_seo_focus_keyword').val() || '';

        if (!content && !title) {
            return;
        }

        showAnalysisLoading();

        $.ajax({
            url: khmSeo.ajax_url,
            type: 'POST',
            data: {
                action: 'khm_seo_analyze_content',
                nonce: khmSeo.nonce,
                content: content,
                title: title,
                focus_keyword: focusKeyword
            },
            success: function(response) {
                if (response.success) {
                    displayAnalysisResults(response.data);
                }
            },
            error: function() {
                hideAnalysisLoading();
            }
        });
    }

    /**
     * Get content from editor
     */
    function getContent() {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
            return tinyMCE.activeEditor.getContent();
        }
        return $('#content').val() || '';
    }

    /**
     * Show analysis loading indicator
     */
    function showAnalysisLoading() {
        var $container = getAnalysisContainer();
        $container.html('<div class="khm-seo-loading"></div> ' + khmSeo.strings.analyzing);
    }

    /**
     * Hide analysis loading indicator
     */
    function hideAnalysisLoading() {
        var $container = getAnalysisContainer();
        $container.empty();
    }

    /**
     * Get or create analysis container
     */
    function getAnalysisContainer() {
        var $container = $('#khm-seo-analysis');
        if (!$container.length) {
            $container = $('<div id="khm-seo-analysis" class="khm-seo-analysis"></div>');
            $('#khm-seo-meta-box').append($container);
        }
        return $container;
    }

    /**
     * Display analysis results
     */
    function displayAnalysisResults(data) {
        var $container = getAnalysisContainer();
        var html = '';

        // Score
        html += '<div class="khm-seo-score-container">';
        html += '<h4>SEO Score: <span class="khm-seo-score ' + data.status + '">' + data.score + '/100</span></h4>';
        html += '</div>';

        // Checks
        if (data.checks) {
            html += '<div class="khm-seo-checks">';
            for (var check in data.checks) {
                var checkData = data.checks[check];
                html += '<div class="khm-seo-check">';
                html += '<div class="khm-seo-check-icon ' + checkData.status + '">';
                html += getStatusIcon(checkData.status);
                html += '</div>';
                html += '<div class="khm-seo-check-message">' + checkData.message + '</div>';
                html += '</div>';
            }
            html += '</div>';
        }

        // Recommendations
        if (data.recommendations && data.recommendations.length > 0) {
            html += '<div class="khm-seo-recommendations">';
            html += '<h4>Recommendations</h4>';
            html += '<ul>';
            for (var i = 0; i < data.recommendations.length; i++) {
                html += '<li>' + data.recommendations[i] + '</li>';
            }
            html += '</ul>';
            html += '</div>';
        }

        $container.html(html);
    }

    /**
     * Get status icon
     */
    function getStatusIcon(status) {
        switch (status) {
            case 'good':
                return '✓';
            case 'needs_improvement':
                return '!';
            case 'poor':
                return '✗';
            default:
                return '?';
        }
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        // Character count for title and description
        $('#khm_seo_title').on('input', function() {
            updateCharacterCount($(this), 60, 'title');
        });

        $('#khm_seo_description').on('input', function() {
            updateCharacterCount($(this), 160, 'description');
        });

        // Initial character count
        $('#khm_seo_title').trigger('input');
        $('#khm_seo_description').trigger('input');
    }

    /**
     * Update character count display
     */
    function updateCharacterCount($field, recommended, type) {
        var length = $field.val().length;
        var $counter = $field.siblings('.char-counter');
        
        if (!$counter.length) {
            $counter = $('<div class="char-counter"></div>');
            $field.after($counter);
        }

        var status = '';
        if (length > recommended + 10) {
            status = 'over';
        } else if (length < recommended - 10) {
            status = 'under';
        } else {
            status = 'good';
        }

        $counter.html(length + ' / ' + recommended + ' characters')
                .removeClass('under good over')
                .addClass(status);
    }

    /**
     * Initialize tabs functionality
     */
    function initTabs() {
        $('.khm-seo-tabs a').on('click', function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.attr('href');

            // Update active tab
            $tab.closest('ul').find('a').removeClass('active');
            $tab.addClass('active');

            // Show target content
            $('.khm-seo-tab-content').hide();
            $(target).show();
        });

        // Show first tab by default
        $('.khm-seo-tabs a:first').trigger('click');
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('.khm-seo-help').hover(function() {
            var $help = $(this);
            var text = $help.data('help');
            
            if (text) {
                var $tooltip = $('<div class="khm-seo-tooltip">' + text + '</div>');
                $help.append($tooltip);
                
                // Position tooltip
                var helpPos = $help.position();
                $tooltip.css({
                    top: helpPos.top + $help.outerHeight() + 5,
                    left: helpPos.left
                });
            }
        }, function() {
            $(this).find('.khm-seo-tooltip').remove();
        });
    }

    /**
     * Social media preview functions
     */
    window.khmSeoPreview = {
        updateFacebookPreview: function() {
            var title = $('#khm_seo_og_title').val() || $('#khm_seo_title').val() || $('#title').val();
            var description = $('#khm_seo_og_description').val() || $('#khm_seo_description').val();
            var image = $('#khm_seo_og_image').val();

            $('.facebook-preview .preview-title').text(title);
            $('.facebook-preview .preview-description').text(description);
            
            if (image) {
                $('.facebook-preview .preview-image').attr('src', image).show();
            } else {
                $('.facebook-preview .preview-image').hide();
            }
        },

        updateTwitterPreview: function() {
            var title = $('#khm_seo_twitter_title').val() || $('#khm_seo_title').val() || $('#title').val();
            var description = $('#khm_seo_twitter_description').val() || $('#khm_seo_description').val();
            var image = $('#khm_seo_twitter_image').val();

            $('.twitter-preview .preview-title').text(title);
            $('.twitter-preview .preview-description').text(description);
            
            if (image) {
                $('.twitter-preview .preview-image').attr('src', image).show();
            } else {
                $('.twitter-preview .preview-image').hide();
            }
        }
    };

    // Auto-update social previews
    $('#khm_seo_title, #khm_seo_description, #khm_seo_og_title, #khm_seo_og_description, #khm_seo_og_image').on('input', function() {
        if (typeof window.khmSeoPreview !== 'undefined') {
            window.khmSeoPreview.updateFacebookPreview();
        }
    });

    $('#khm_seo_twitter_title, #khm_seo_twitter_description, #khm_seo_twitter_image').on('input', function() {
        if (typeof window.khmSeoPreview !== 'undefined') {
            window.khmSeoPreview.updateTwitterPreview();
        }
    });

})(jQuery);