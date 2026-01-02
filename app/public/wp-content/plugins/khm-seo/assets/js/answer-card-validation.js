/**
 * AnswerCard Validation JavaScript
 *
 * Handles client-side validation for AnswerCard widgets in Elementor editor.
 *
 * @package KHM_SEO
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // Validation state
    var validationState = {
        isValidating: false,
        lastValidation: null,
        validationResults: {}
    };

    /**
     * Initialize validation on Elementor editor load
     */
    $(document).on('elementor/editor/init', function() {
        initializeValidation();
    });

    /**
     * Initialize validation functionality
     */
    function initializeValidation() {
        // Bind to AnswerCard widget changes
        elementor.channels.editor.on('change', function(view) {
            if (isAnswerCardWidget(view)) {
                scheduleValidation(view);
            }
        });

        // Bind to widget settings changes
        elementor.channels.editor.on('change:settings', function(view) {
            if (isAnswerCardWidget(view)) {
                scheduleValidation(view);
            }
        });

        // Add validation button to widget panel
        addValidationButton();

        // Initial validation for existing widgets
        setTimeout(function() {
            validateAllAnswerCards();
        }, 2000);
    }

    /**
     * Check if view is an AnswerCard widget
     */
    function isAnswerCardWidget(view) {
        if (!view || !view.model) return false;

        var widgetType = view.model.get('widgetType');
        return widgetType === 'khm-answer-card';
    }

    /**
     * Schedule validation with debounce
     */
    function scheduleValidation(view) {
        clearTimeout(validationState.validationTimer);
        validationState.validationTimer = setTimeout(function() {
            validateAnswerCard(view);
        }, 1000);
    }

    /**
     * Validate a specific AnswerCard widget
     */
    function validateAnswerCard(view) {
        if (validationState.isValidating) return;

        var model = view.model;
        var settings = model.get('settings').toJSON();
        var widgetId = model.get('id');

        // Skip if no significant content
        if (!settings.question && !settings.answer) {
            updateValidationUI(view, null);
            return;
        }

        validationState.isValidating = true;
        showValidationLoading(view);

        // Prepare validation data
        var validationData = {
            action: 'khm_validate_answer_card',
            nonce: khmValidation.nonce,
            settings: settings,
            post_id: elementor.config.document.id || 0
        };

        // Send validation request
        $.ajax({
            url: khmValidation.ajaxUrl,
            type: 'POST',
            data: validationData,
            success: function(response) {
                if (response.success) {
                    updateValidationUI(view, {
                        valid: true,
                        score: response.data.score,
                        quality_level: response.data.quality_level,
                        message: response.data.message
                    });
                } else {
                    updateValidationUI(view, {
                        valid: false,
                        errors: response.data.errors || [],
                        warnings: response.data.warnings || [],
                        recommendations: response.data.recommendations || [],
                        score: response.data.score,
                        quality_level: response.data.quality_level,
                        message: response.data.message
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AnswerCard validation error:', error);
                updateValidationUI(view, {
                    valid: false,
                    errors: ['Validation service unavailable'],
                    message: 'Unable to validate AnswerCard'
                });
            },
            complete: function() {
                validationState.isValidating = false;
                hideValidationLoading(view);
            }
        });
    }

    /**
     * Validate all AnswerCard widgets on the page
     */
    function validateAllAnswerCards() {
        var answerCards = elementor.getPreviewView().$el.find('.elementor-widget-khm-answer-card');

        answerCards.each(function() {
            var widgetId = $(this).data('id');
            var view = elementor.getPreviewView().getChildViewById(widgetId);
            if (view) {
                validateAnswerCard(view);
            }
        });
    }

    /**
     * Show validation loading state
     */
    function showValidationLoading(view) {
        var $widget = view.$el;
        var $validationIndicator = $widget.find('.khm-validation-indicator');

        if ($validationIndicator.length === 0) {
            $validationIndicator = $('<div class="khm-validation-indicator"></div>');
            $widget.find('.elementor-widget-container').prepend($validationIndicator);
        }

        $validationIndicator.html('<span class="khm-validation-loading">⏳ ' + khmValidation.messages.validating + '</span>');
        $validationIndicator.show();
    }

    /**
     * Hide validation loading state
     */
    function hideValidationLoading(view) {
        var $widget = view.$el;
        $widget.find('.khm-validation-indicator').hide();
    }

    /**
     * Update validation UI for a widget
     */
    function updateValidationUI(view, result) {
        var $widget = view.$el;
        var $indicator = $widget.find('.khm-validation-indicator');

        if ($indicator.length === 0) {
            $indicator = $('<div class="khm-validation-indicator"></div>');
            $widget.find('.elementor-widget-container').prepend($indicator);
        }

        if (!result) {
            // No validation needed
            $indicator.hide();
            return;
        }

        var html = '';

        if (result.valid) {
            html += '<span class="khm-validation-success">✓ ' + khmValidation.messages.validation_passed + '</span>';
            if (result.score) {
                var percentage = Math.round(result.score * 100);
                html += '<span class="khm-validation-score">(' + percentage + '% - ' + result.quality_level + ')</span>';
            }
            $widget.removeClass('khm-validation-error').addClass('khm-validation-success');
        } else {
            html += '<span class="khm-validation-error">✗ ' + khmValidation.messages.validation_failed + '</span>';
            if (result.score) {
                var percentage = Math.round(result.score * 100);
                html += '<span class="khm-validation-score">(' + percentage + '% - ' + result.quality_level + ')</span>';
            }

            // Show errors and warnings
            if (result.errors && result.errors.length > 0) {
                html += '<div class="khm-validation-details khm-validation-errors">';
                html += '<strong>Errors:</strong><ul>';
                result.errors.forEach(function(error) {
                    html += '<li>' + error + '</li>';
                });
                html += '</ul></div>';
            }

            if (result.warnings && result.warnings.length > 0) {
                html += '<div class="khm-validation-details khm-validation-warnings">';
                html += '<strong>Warnings:</strong><ul>';
                result.warnings.forEach(function(warning) {
                    html += '<li>' + warning + '</li>';
                });
                html += '</ul></div>';
            }

            if (result.recommendations && result.recommendations.length > 0) {
                html += '<div class="khm-validation-details khm-validation-recommendations">';
                html += '<strong>Recommendations:</strong><ul>';
                result.recommendations.forEach(function(rec) {
                    html += '<li>' + rec + '</li>';
                });
                html += '</ul></div>';
            }

            // Add publish anyway button
            html += '<button class="khm-publish-anyway-btn elementor-button elementor-button-default">' + khmValidation.messages.publish_anyway + '</button>';

            $widget.removeClass('khm-validation-success').addClass('khm-validation-error');
        }

        $indicator.html(html).show();

        // Bind publish anyway button
        $indicator.find('.khm-publish-anyway-btn').on('click', function(e) {
            e.preventDefault();
            overrideValidation(view);
        });
    }

    /**
     * Override validation for publishing
     */
    function overrideValidation(view) {
        if (confirm('Are you sure you want to publish this AnswerCard despite validation issues?')) {
            var $widget = view.$el;
            $widget.removeClass('khm-validation-error').addClass('khm-validation-overridden');
            $widget.find('.khm-validation-indicator').html('<span class="khm-validation-overridden">⚠ Published with override</span>');
        }
    }

    /**
     * Add validation button to widget panel
     */
    function addValidationButton() {
        elementor.hooks.addAction('panel/widgets/khm-answer-card/controls/content_section/before_section_end', function(panelView, widgetId) {
            panelView.addControl('validate_answer_card', {
                type: 'button',
                label: 'Validate AnswerCard',
                button_type: 'default',
                text: 'Run Validation',
                event: 'khm:validate_answer_card'
            });
        });

        // Bind validation button event
        elementor.channels.editor.on('khm:validate_answer_card', function(panelView) {
            var widgetId = panelView.getOption('editedElementView').model.get('id');
            var view = elementor.getPreviewView().getChildViewById(widgetId);
            if (view) {
                validateAnswerCard(view);
            }
        });
    }

    /**
     * Get quality color for score
     */
    function getQualityColor(qualityLevel) {
        var colors = {
            'excellent': '#2e7d32',
            'good': '#1976d2',
            'fair': '#f57c00',
            'poor': '#d32f2f',
            'critical': '#7b1fa2'
        };
        return colors[qualityLevel] || '#666';
    }

})(jQuery);