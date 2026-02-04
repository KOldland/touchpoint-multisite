// KH Events Booking JavaScript
// This file handles the booking form functionality

jQuery(document).ready(function($) {
    'use strict';

    // Initialize Stripe if available
    var stripe, cardElement;

    if (typeof kh_events_booking !== 'undefined' && kh_events_booking.stripe_publishable_key) {
        stripe = Stripe(kh_events_booking.stripe_publishable_key);
        var elements = stripe.elements();
        cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
            },
        });

        // Mount card element when Stripe payment is selected
        $(document).on('change', 'input[name="payment_gateway"]', function() {
            if ($(this).val() === 'stripe') {
                cardElement.mount('#card-element');
            }
        });
    }

    // Update total when ticket quantities change
    $(document).on('change', '.ticket-quantity', function() {
        updateTotal();
    });

    function updateTotal() {
        var total = 0;
        $('.ticket-quantity').each(function() {
            var quantity = parseInt($(this).val()) || 0;
            var price = parseFloat($(this).data('price'));
            total += quantity * price;
        });

        if (total > 0) {
            $('#kh-total-amount').html('<p><strong>Total: $' + total.toFixed(2) + '</strong></p>');
            $('#kh-payment-section').show();
        } else {
            $('#kh-total-amount').empty();
            $('#kh-payment-section').hide();
        }
    }

    // Show/hide payment fields based on gateway selection
    $(document).on('change', 'input[name="payment_gateway"]', function() {
        $('.payment-fields').hide();
        if ($(this).val() === 'stripe') {
            $('#kh-stripe-payment').show();
        } else if ($(this).val() === 'paypal') {
            $('#kh-paypal-payment').show();
        }
    });

    // Handle form submission
    $(document).on('submit', '#kh-booking-form', function(e) {
        e.preventDefault();

        var total = 0;
        $('.ticket-quantity').each(function() {
            var quantity = parseInt($(this).val()) || 0;
            var price = parseFloat($(this).data('price'));
            total += quantity * price;
        });

        if (total === 0) {
            $('#kh-booking-message').html('<p class="error">Please select at least one ticket.</p>');
            return;
        }

        var paymentGateway = $('input[name="payment_gateway"]:checked').val();
        if (!paymentGateway) {
            $('#kh-booking-message').html('<p class="error">Please select a payment method.</p>');
            return;
        }

        $('#kh-submit-booking').prop('disabled', true).text('Processing...');

        if (paymentGateway === 'stripe' && stripe && cardElement) {
            // Process Stripe payment
            stripe.createToken(cardElement).then(function(result) {
                if (result.error) {
                    $('#card-errors').text(result.error.message);
                    $('#kh-submit-booking').prop('disabled', false).text('Submit Booking');
                } else {
                    submitBooking(result.token.id, paymentGateway, total);
                }
            });
        } else {
            // For other gateways, submit directly
            submitBooking('', paymentGateway, total);
        }
    });

    function submitBooking(paymentToken, paymentGateway, total) {
        var formData = $('#kh-booking-form').serialize();
        formData += '&action=kh_submit_booking&payment_token=' + encodeURIComponent(paymentToken) + '&payment_gateway=' + encodeURIComponent(paymentGateway) + '&total_amount=' + total;

        $.ajax({
            type: 'POST',
            url: kh_events_booking.ajax_url,
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#kh-booking-message').html('<p class="success">' + response.data.message + '</p>');
                    $('#kh-booking-form')[0].reset();
                    $('#kh-payment-section').hide();
                    $('#kh-total-amount').empty();
                    $('.payment-fields').hide();
                    if (cardElement) {
                        cardElement.unmount();
                    }
                } else {
                    $('#kh-booking-message').html('<p class="error">' + response.data.message + '</p>');
                }
                $('#kh-submit-booking').prop('disabled', false).text('Submit Booking');
            },
            error: function() {
                $('#kh-booking-message').html('<p class="error">An error occurred. Please try again.</p>');
                $('#kh-submit-booking').prop('disabled', false).text('Submit Booking');
            }
        });
    }
});