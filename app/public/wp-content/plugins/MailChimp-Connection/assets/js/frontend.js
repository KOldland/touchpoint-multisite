/**
 * TouchPoint MailChimp Frontend JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        initSubscriptionForms();
    });
    
    function initSubscriptionForms() {
        $('.tmc-subscription-form').on('submit', handleFormSubmission);
        $('.tmc-modal-trigger').on('click', openSubscriptionModal);
        $('.tmc-modal-close').on('click', closeSubscriptionModal);
        
        // Close modal on background click
        $(document).on('click', '.tmc-modal-overlay', function(e) {
            if (e.target === this) {
                closeSubscriptionModal();
            }
        });
        
        // Close modal on ESC key
        $(document).on('keyup', function(e) {
            if (e.keyCode === 27) {
                closeSubscriptionModal();
            }
        });
    }
    
    function handleFormSubmission(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('.tmc-submit-button');
        var $messageDiv = $form.find('.tmc-form-message');
        
        // Disable submit button and show loading
        $submitButton.prop('disabled', true).text(tmc_ajax.loading_text);
        $messageDiv.removeClass('tmc-error tmc-success').empty();
        
        // Collect form data
        var formData = {
            action: 'tmc_subscribe',
            nonce: tmc_ajax.nonce,
            email: $form.find('input[name="email"]').val(),
            list_id: $form.find('input[name="list_id"]').val() || '',
            interests: []
        };
        
        // Collect interest selections
        $form.find('input[name="interests[]"]:checked').each(function() {
            formData.interests.push($(this).val());
        });
        
        // Additional fields
        $form.find('input[name^="merge_fields"]').each(function() {
            var fieldName = $(this).attr('name').replace('merge_fields[', '').replace(']', '');
            if (!formData.merge_fields) {
                formData.merge_fields = {};
            }
            formData.merge_fields[fieldName] = $(this).val();
        });
        
        // Submit via AJAX
        $.post(tmc_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    showFormMessage($messageDiv, response.data.message, 'success');
                    $form[0].reset();
                    
                    // Close modal if it's a modal form
                    if ($form.closest('.tmc-modal-overlay').length) {
                        setTimeout(closeSubscriptionModal, 2000);
                    }
                } else {
                    showFormMessage($messageDiv, response.data.message, 'error');
                }
            })
            .fail(function() {
                showFormMessage($messageDiv, tmc_ajax.error_text, 'error');
            })
            .always(function() {
                // Re-enable submit button
                $submitButton.prop('disabled', false).text($submitButton.data('original-text') || 'Subscribe');
            });
    }
    
    function showFormMessage($messageDiv, message, type) {
        $messageDiv
            .removeClass('tmc-error tmc-success')
            .addClass('tmc-' + type)
            .html(message)
            .slideDown();
    }
    
    function openSubscriptionModal() {
        var $trigger = $(this);
        var listId = $trigger.data('list');
        var modalId = $trigger.data('modal') || 'tmc-default-modal';
        
        var $modal = $('#' + modalId);
        if ($modal.length === 0) {
            // Create default modal if it doesn't exist
            $modal = createDefaultModal(modalId, listId);
            $('body').append($modal);
        }
        
        // Set list ID if specified
        if (listId) {
            $modal.find('input[name="list_id"]').val(listId);
        }
        
        $modal.fadeIn(300);
        $('body').addClass('tmc-modal-open');
        
        // Focus on email field
        setTimeout(function() {
            $modal.find('input[name="email"]').focus();
        }, 350);
    }
    
    function closeSubscriptionModal() {
        $('.tmc-modal-overlay').fadeOut(300);
        $('body').removeClass('tmc-modal-open');
    }
    
    function createDefaultModal(modalId, listId) {
        var modal = '<div id="' + modalId + '" class="tmc-modal-overlay">' +
            '<div class="tmc-modal-container">' +
                '<div class="tmc-modal-header">' +
                    '<h3>Subscribe to Newsletter</h3>' +
                    '<button type="button" class="tmc-modal-close">&times;</button>' +
                '</div>' +
                '<div class="tmc-modal-content">' +
                    '<form class="tmc-subscription-form tmc-modal-form">' +
                        '<input type="hidden" name="list_id" value="' + (listId || '') + '">' +
                        '<div class="tmc-form-group">' +
                            '<label for="tmc-modal-email">Email Address</label>' +
                            '<input type="email" id="tmc-modal-email" name="email" required>' +
                        '</div>' +
                        '<div class="tmc-form-message"></div>' +
                        '<button type="submit" class="tmc-submit-button">Subscribe</button>' +
                    '</form>' +
                '</div>' +
            '</div>' +
        '</div>';
        
        return $(modal);
    }
    
    // Email validation
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Real-time email validation
    $(document).on('blur', '.tmc-subscription-form input[name="email"]', function() {
        var $input = $(this);
        var email = $input.val();
        
        if (email && !isValidEmail(email)) {
            $input.addClass('tmc-invalid');
            $input.siblings('.tmc-field-error').remove();
            $input.after('<span class="tmc-field-error">Please enter a valid email address</span>');
        } else {
            $input.removeClass('tmc-invalid');
            $input.siblings('.tmc-field-error').remove();
        }
    });
    
})(jQuery);