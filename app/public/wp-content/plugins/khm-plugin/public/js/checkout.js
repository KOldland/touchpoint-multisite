/**
 * KHM Checkout JavaScript
 * 
 * Handles Stripe Elements integration and checkout form submission.
 */

(function($) {
    'use strict';

    const KHMCheckout = {
        stripe: null,
        cardElement: null,
        form: null,
        submitButton: null,
        messages: null,

        init: function() {
            if (typeof Stripe === 'undefined' || !khmCheckout.stripeKey) {
                console.error('KHM Checkout: Stripe.js not loaded or publishable key missing');
                return;
            }

            this.stripe = Stripe(khmCheckout.stripeKey);
            this.form = $('#khm-checkout-form');
            this.submitButton = $('#khm-checkout-submit');
            this.messages = $('#khm-checkout-messages');

            if (!this.form.length) {
                return;
            }

            this.setupStripeElements();
            this.bindEvents();
        },

        setupStripeElements: function() {
            const elements = this.stripe.elements();
            
            // Create card element
            this.cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#32325d',
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        '::placeholder': {
                            color: '#aab7c4'
                        }
                    },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }
                }
            });

            // Mount to DOM
            this.cardElement.mount('#khm-card-element');

            // Handle real-time validation errors
            this.cardElement.on('change', (event) => {
                const displayError = $('#khm-card-errors');
                if (event.error) {
                    displayError.text(event.error.message).show();
                } else {
                    displayError.text('').hide();
                }
            });
        },

        bindEvents: function() {
            this.form.on('submit', (e) => this.handleSubmit(e));
        },

        handleSubmit: async function(e) {
            e.preventDefault();

            if (this.submitButton.prop('disabled')) {
                return false;
            }

            this.setLoading(true);
            this.clearMessages();

            try {
                // Create payment method
                const {paymentMethod, error} = await this.stripe.createPaymentMethod({
                    type: 'card',
                    card: this.cardElement,
                    billing_details: {
                        name: $('#khm-billing-name').val(),
                        email: $('#khm-billing-email').val(),
                        address: {
                            line1: $('#khm-billing-street').val(),
                            city: $('#khm-billing-city').val(),
                            state: $('#khm-billing-state').val(),
                            postal_code: $('#khm-billing-zip').val(),
                            country: $('#khm-billing-country').val()
                        }
                    }
                });

                if (error) {
                    throw new Error(error.message);
                }

                // Set payment method ID
                $('#khm-payment-method-id').val(paymentMethod.id);

                // Submit to server
                const formData = this.form.serialize();
                
                const response = await $.ajax({
                    url: khmCheckout.ajaxUrl,
                    method: 'POST',
                    data: formData,
                    dataType: 'json'
                });

                if (response.success) {
                    this.showSuccess(response.data.message);
                    
                    // Redirect after short delay
                    if (response.data.redirect) {
                        setTimeout(() => {
                            window.location.href = response.data.redirect;
                        }, 1500);
                    }
                } else {
                    throw new Error(response.data.message || 'Payment failed');
                }

            } catch (error) {
                this.showError(error.message);
                this.setLoading(false);
            }

            return false;
        },

        setLoading: function(loading) {
            this.submitButton.prop('disabled', loading);
            
            if (loading) {
                this.submitButton.addClass('khm-loading');
                this.submitButton.text(this.submitButton.data('loading-text') || 'Processing...');
            } else {
                this.submitButton.removeClass('khm-loading');
                this.submitButton.text(this.submitButton.data('original-text') || 'Complete Purchase');
            }
        },

        showSuccess: function(message) {
            this.messages
                .removeClass('khm-error')
                .addClass('khm-success')
                .html('<p>' + this.escapeHtml(message) + '</p>')
                .show();
        },

        showError: function(message) {
            this.messages
                .removeClass('khm-success')
                .addClass('khm-error')
                .html('<p>' + this.escapeHtml(message) + '</p>')
                .show();
            
            // Scroll to message
            $('html, body').animate({
                scrollTop: this.messages.offset().top - 100
            }, 500);
        },

        clearMessages: function() {
            this.messages.removeClass('khm-success khm-error').html('').hide();
            $('#khm-card-errors').text('').hide();
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, (m) => map[m]);
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        KHMCheckout.init();
    });

})(jQuery);
