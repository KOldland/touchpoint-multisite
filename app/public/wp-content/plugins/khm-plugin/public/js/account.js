/**
 * KHM Account Page JavaScript
 */

(function($) {
    'use strict';

    var KHMAccount = {
        stripe: null,
        elements: {},
        cardElements: {},
        /**
         * Initialize
         */
        init: function() {
            if (window.khmAccount && khmAccount.stripeKey) {
                this.stripe = Stripe(khmAccount.stripeKey);
                this.elements = {};
                this.cardElements = {};
            }
            this.bindEvents();
        },

        /**
         * Bind DOM events
         */
        bindEvents: function() {
            $(document).on('click', '.khm-button-cancel-period-end', this.handleCancelAtPeriodEnd.bind(this));
            $(document).on('click', '.khm-button-cancel-now', this.handleCancelNow.bind(this));
            $(document).on('click', '.khm-button-reactivate', this.handleReactivate.bind(this));
            $(document).on('click', '.khm-button-pause', this.handlePause.bind(this));
            $(document).on('click', '.khm-button-resume', this.handleResume.bind(this));
            $(document).on('click', '.khm-button-update-card-toggle', this.toggleUpdateCard.bind(this));
            $(document).on('click', '.khm-button-save-card', this.handleSaveCard.bind(this));
        },

        /**
         * Cancel at period end
         */
        handleCancelAtPeriodEnd: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var levelId = parseInt($button.data('level-id'), 10);
            
            // Confirm cancellation
            if (!confirm(khmAccount.confirmCancel)) {
                return;
            }
            
            this.setLoading($button, true);
            this.clearMessages();
            
            $.ajax({
                url: khmAccount.restUrl + '/subscription/cancel',
                type: 'POST',
                data: JSON.stringify({ level_id: levelId, at_period_end: true }),
                contentType: 'application/json',
                headers: { 'X-WP-Nonce': khmAccount.restNonce },
                success: function(response) {
                    if (response && response.success) {
                        this.showMessage('success', response.message || 'Your subscription will be cancelled at period end.');
                        
                        // Update UI - remove the card or update status
                        var $card = $button.closest('.khm-membership-card');
                        var $badge = $card.find('.khm-badge');
                        $badge.text('Active (Cancels at period end)');
                        
                        // Disable buttons
                        $card.find('.khm-membership-actions button').prop('disabled', true);
                        
                        // Reload page after 2 seconds
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        var msg = (response && (response.message || (response.data && response.data.message))) || 'Failed to cancel.';
                        this.showMessage('error', msg);
                        this.setLoading($button, false);
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showMessage('error', 'An error occurred. Please try again.');
                    this.setLoading($button, false);
                }.bind(this)
            });
        },

        /**
         * Cancel immediately
         */
        handleCancelNow: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var levelId = parseInt($button.data('level-id'), 10);

            if (!confirm(khmAccount.confirmCancel)) {
                return;
            }

            this.setLoading($button, true);
            this.clearMessages();

            $.ajax({
                url: khmAccount.restUrl + '/subscription/cancel',
                type: 'POST',
                data: JSON.stringify({ level_id: levelId, at_period_end: false }),
                contentType: 'application/json',
                headers: { 'X-WP-Nonce': khmAccount.restNonce },
                success: function(response) {
                    if (response && response.success) {
                        this.showMessage('success', response.message || 'Your subscription has been cancelled.');

                        var $card = $button.closest('.khm-membership-card');
                        var $badge = $card.find('.khm-badge');
                        $badge.removeClass('khm-badge-active').addClass('khm-badge-cancelled').text('Cancelled');

                        $button.closest('.khm-membership-actions').remove();

                        setTimeout(function() { window.location.reload(); }, 2000);
                    } else {
                        var msg = (response && (response.message || (response.data && response.data.message))) || 'Failed to cancel.';
                        this.showMessage('error', msg);
                        this.setLoading($button, false);
                    }
                }.bind(this),
                error: function() {
                    this.showMessage('error', 'An error occurred. Please try again.');
                    this.setLoading($button, false);
                }.bind(this)
            });
        },

        handlePause: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var levelId = parseInt($button.data('level-id'), 10);

            if (!confirm(khmAccount.confirmPause || 'Pause this membership?')) {
                return;
            }

            this.setLoading($button, true);
            this.clearMessages();

            $.ajax({
                url: khmAccount.restUrl + '/subscription/pause',
                type: 'POST',
                data: JSON.stringify({ level_id: levelId }),
                contentType: 'application/json',
                headers: { 'X-WP-Nonce': khmAccount.restNonce },
                success: function(response) {
                    if (response && response.success) {
                        this.showMessage('success', response.message || 'Membership paused.');
                        setTimeout(function() { window.location.reload(); }, 1500);
                    } else {
                        var msg = (response && (response.message || (response.data && response.data.message))) || 'Failed to pause.';
                        this.showMessage('error', msg);
                        this.setLoading($button, false);
                    }
                }.bind(this),
                error: function() {
                    this.showMessage('error', 'An error occurred. Please try again.');
                    this.setLoading($button, false);
                }.bind(this)
            });
        },

        handleResume: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var levelId = parseInt($button.data('level-id'), 10);

            this.setLoading($button, true);
            this.clearMessages();

            $.ajax({
                url: khmAccount.restUrl + '/subscription/resume',
                type: 'POST',
                data: JSON.stringify({ level_id: levelId }),
                contentType: 'application/json',
                headers: { 'X-WP-Nonce': khmAccount.restNonce },
                success: function(response) {
                    if (response && response.success) {
                        this.showMessage('success', response.message || 'Membership resumed.');
                        setTimeout(function() { window.location.reload(); }, 1500);
                    } else {
                        var msg = (response && (response.message || (response.data && response.data.message))) || 'Failed to resume.';
                        this.showMessage('error', msg);
                        this.setLoading($button, false);
                    }
                }.bind(this),
                error: function() {
                    this.showMessage('error', 'An error occurred. Please try again.');
                    this.setLoading($button, false);
                }.bind(this)
            });
        },

        /**
         * Set button loading state
         */
        setLoading: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true).addClass('khm-loading');
            } else {
                $button.prop('disabled', false).removeClass('khm-loading');
            }
        },

        /**
         * Show message
         */
        showMessage: function(type, message) {
            var $messagesContainer = $('#khm-account-messages');
            
            var $message = $('<div>')
                .addClass('khm-message')
                .addClass(type)
                .text(message);
            
            $messagesContainer.html($message);
            
            // Scroll to messages
            $('html, body').animate({
                scrollTop: $messagesContainer.offset().top - 100
            }, 500);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $message.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },

        /**
         * Clear messages
         */
        clearMessages: function() {
            $('#khm-account-messages').empty();
        },

        /**
         * Reactivate subscription (unset cancel_at_period_end)
         */
        handleReactivate: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var levelId = parseInt($button.data('level-id'), 10);
            
            if (!confirm('Are you sure you want to reactivate this subscription?')) {
                return;
            }
            
            this.setLoading($button, true);
            this.clearMessages();
            
            $.ajax({
                url: khmAccount.restUrl + '/subscription/reactivate',
                type: 'POST',
                data: JSON.stringify({ level_id: levelId }),
                contentType: 'application/json',
                headers: { 'X-WP-Nonce': khmAccount.restNonce },
                success: function(response) {
                    if (response && response.success) {
                        this.showMessage('success', response.message || 'Your subscription has been reactivated.');
                        
                        // Reload page after 2 seconds to refresh status
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        var msg = (response && (response.message || (response.data && response.data.message))) || 'Failed to reactivate.';
                        this.showMessage('error', msg);
                        this.setLoading($button, false);
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showMessage('error', 'An error occurred. Please try again.');
                    this.setLoading($button, false);
                }.bind(this)
            });
        },

        /**
         * Toggle Update Card UI and lazily mount Stripe Elements
         */
        toggleUpdateCard: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var levelId = parseInt($btn.data('level-id'), 10);
            var $section = $('#khm-update-card-' + levelId);
            $section.toggle();

            if (!$section.is(':visible')) return;

            if (!this.stripe) {
                this.showMessage('error', 'Stripe is not configured.');
                return;
            }

            // Mount if not already
            if (!this.cardElements[levelId]) {
                var elements = this.stripe.elements();
                var card = elements.create('card');
                card.mount('#khm-card-element-' + levelId);
                this.elements[levelId] = elements;
                this.cardElements[levelId] = card;
            }
        },

        /**
         * Save updated card: create SetupIntent, confirm with Stripe.js, then POST PM to server
         */
        handleSaveCard: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var levelId = parseInt($btn.data('level-id'), 10);
            var card = this.cardElements[levelId];
            if (!card) {
                this.showMessage('error', 'Card element not ready.');
                return;
            }

            this.setLoading($btn, true);
            this.clearMessages();

            // Request SetupIntent from server
            $.ajax({
                url: khmAccount.restUrl + '/payment-method/setup-intent',
                type: 'POST',
                data: JSON.stringify({ level_id: levelId }),
                contentType: 'application/json',
                headers: { 'X-WP-Nonce': khmAccount.restNonce },
                success: async function(resp) {
                    if (!resp || !resp.success || !resp.client_secret) {
                        var msg = (resp && resp.message) || 'Unable to prepare card update.';
                        this.showMessage('error', msg);
                        this.setLoading($btn, false);
                        return;
                    }

                    try {
                        var result = await this.stripe.confirmCardSetup(resp.client_secret, {
                            payment_method: {
                                card: card
                            }
                        });
                        if (result.error) {
                            this.showMessage('error', result.error.message || 'Card confirmation failed.');
                            this.setLoading($btn, false);
                            return;
                        }

                        var pmId = result.setupIntent && result.setupIntent.payment_method;
                        if (!pmId) {
                            this.showMessage('error', 'No payment method returned.');
                            this.setLoading($btn, false);
                            return;
                        }

                        // Send PM to server to attach and set default
                        $.ajax({
                            url: khmAccount.restUrl + '/payment-method/update',
                            type: 'POST',
                            data: JSON.stringify({ level_id: levelId, payment_method_id: pmId }),
                            contentType: 'application/json',
                            headers: { 'X-WP-Nonce': khmAccount.restNonce },
                            success: function(u) {
                                if (u && u.success) {
                                    this.showMessage('success', u.message || 'Payment method updated.');
                                    // Hide section and reset element
                                    $('#khm-update-card-' + levelId).hide();
                                } else {
                                    var m = (u && u.message) || 'Failed to update payment method.';
                                    this.showMessage('error', m);
                                }
                                this.setLoading($btn, false);
                            }.bind(this),
                            error: function() {
                                this.showMessage('error', 'An error occurred updating the payment method.');
                                this.setLoading($btn, false);
                            }.bind(this)
                        });
                    } catch (err) {
                        this.showMessage('error', err.message || 'Card confirmation failed.');
                        this.setLoading($btn, false);
                    }
                }.bind(this),
                error: function() {
                    this.showMessage('error', 'Failed to initialize card update.');
                    this.setLoading($btn, false);
                }.bind(this)
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        KHMAccount.init();
    });

})(jQuery);
