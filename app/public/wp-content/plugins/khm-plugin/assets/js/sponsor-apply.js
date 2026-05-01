/**
 * Sponsor Application Form JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        $('#khm-sponsor-apply-form').on('submit', function(e) {
            e.preventDefault();
            submitApplication();
        });
    });

    function submitApplication() {
        const $form = $('#khm-sponsor-apply-form');
        const $submit = $('#khm-sponsor-apply-submit');
        const $message = $('#khm-sponsor-apply-message');
        const $successMsg = $('#khm-sponsor-apply-success');

        // Validate form
        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }

        // Disable submit button
        $submit.prop('disabled', true);
        $form.addClass('loading');
        $message.hide();

        // Collect form data
        const data = {
            action: 'khm_sponsor_apply',
            khm_sponsor_apply_nonce: $('input[name="khm_sponsor_apply_nonce"]').val(),
            company_name: $('#company_name').val(),
            contact_name: $('#contact_name').val(),
            contact_email: $('#contact_email').val(),
            contact_phone: $('#contact_phone').val(),
            sector: $('#sector').val(),
            company_url: $('#company_url').val(),
            use_case: $('#use_case').val(),
            message: $('#message').val(),
            accept_terms: $('input[name="accept_terms"]:checked').length ? 1 : 0
        };

        // Send AJAX request
        $.ajax({
            type: 'POST',
            url: khm_sponsor_apply.ajax_url,
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Hide form and show success message
                    $form.hide();
                    $successMsg.show();
                } else {
                    showError(response.data.message || 'An error occurred. Please try again.');
                    $submit.prop('disabled', false);
                    $form.removeClass('loading');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showError('A network error occurred. Please try again.');
                $submit.prop('disabled', false);
                $form.removeClass('loading');
            }
        });
    }

    function showError(message) {
        const $message = $('#khm-sponsor-apply-message');
        $message
            .removeClass('success')
            .addClass('error')
            .text(message)
            .show();
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 300);
    }
})(jQuery);
