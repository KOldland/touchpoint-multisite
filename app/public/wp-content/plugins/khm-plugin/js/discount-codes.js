/**
 * Discount Code Validation
 * 
 * Handles AJAX validation of discount codes during checkout.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const $discountCodeInput = $('#khm_discount_code');
        const $applyButton = $('#khm_apply_discount');
        const $messageContainer = $('#khm_discount_message');
        const $orderTotal = $('#khm_order_total');
        const $originalTotal = $('#khm_original_total');

        if ($applyButton.length === 0) {
            return; // No discount code field on this page
        }

        // Apply discount code
        $applyButton.on('click', function(e) {
            e.preventDefault();

            const code = $discountCodeInput.val().trim();
            const levelId = $applyButton.data('level-id') || $('input[name="level_id"]').val() || $('input[name="membership_level"]').val() || $('select[name="membership_level"]').val();

            if (!code) {
                showMessage('Please enter a discount code.', 'error');
                return;
            }

            if (!levelId) {
                showMessage('Please select a membership level.', 'error');
                return;
            }

            // Show loading state
            $applyButton.prop('disabled', true).text('Validating...');
            $messageContainer.html('');

            // AJAX request
            $.ajax({
                url: (typeof khmDiscountCode !== 'undefined' ? khmDiscountCode.ajaxUrl : khmDiscountCodes.ajax_url),
                type: 'POST',
                data: {
                    action: 'khm_validate_discount_code',
                    nonce: (typeof khmDiscountCode !== 'undefined' ? khmDiscountCode.nonce : khmDiscountCodes.nonce),
                    code: code,
                    level_id: levelId
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        updateOrderSummary(response.data);
                        recalculateTotal();
                        $discountCodeInput.prop('readonly', true);
                        $applyButton.text('Applied').addClass('applied');
                    } else {
                        showMessage(response.data.message, 'error');
                        $applyButton.prop('disabled', false).text('Apply');
                    }
                },
                error: function() {
                    showMessage('An error occurred. Please try again.', 'error');
                    $applyButton.prop('disabled', false).text('Apply');
                }
            });
        });

        // Remove discount code
        $(document).on('click', '#khm_remove_discount', function(e) {
            e.preventDefault();
            $discountCodeInput.val('').prop('readonly', false);
            $applyButton.prop('disabled', false).text('Apply').removeClass('applied');
            $messageContainer.html('');
            recalculateTotal();
        });

        // Show message helper
        function showMessage(message, type) {
            const cssClass = type === 'success' ? 'khm-success' : 'khm-error';
            $messageContainer.html('<div class="' + cssClass + '">' + message + '</div>');
        }

        // Recalculate order total
        function recalculateTotal() {
            // Trigger server-side recalculation
            // This would normally refresh the checkout page or update via AJAX
            // For now, just trigger a custom event that the checkout page can listen to
            $(document).trigger('khm_discount_applied');
        }

        function formatCurrency(amount) {
            var num = parseFloat(amount || 0);
            if (isNaN(num)) num = 0;
            return '$' + num.toFixed(2);
        }

        function updateOrderSummary(data) {
            try {
                // Update due today if provided
                if (typeof data.due_today !== 'undefined') {
                    var $due = $('[data-test="khm-due-today"], #khm_due_today').first();
                    if ($due.length) {
                        $due.text(formatCurrency(data.due_today));
                    }
                }

                // Trial label
                var $trial = $('[data-test="khm-trial-label"], .khm-trial-label').first();
                if ($trial.length) {
                    if (data.trial && data.trial.days > 0) {
                        var amount = parseFloat(data.trial.amount || 0);
                        if (amount > 0) {
                            $trial.text('Paid trial: ' + data.trial.days + ' days (' + formatCurrency(amount) + ' due today)').show();
                        } else {
                            $trial.text('Free trial: ' + data.trial.days + ' days').show();
                        }
                    } else {
                        $trial.hide();
                    }
                }

                // First payment only label
                var $firstOnly = $('[data-test="khm-first-only-label"], .khm-first-only-label').first();
                if ($firstOnly.length) {
                    if (data.first_payment_only) {
                        $firstOnly.text('First payment only discount applied').show();
                    } else {
                        $firstOnly.hide();
                    }
                }
            } catch (e) {
                // noop
            }
        }
    });

})(jQuery);
