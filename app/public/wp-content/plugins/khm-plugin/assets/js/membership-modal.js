/**
 * Membership Checkout Modal
 *
 * Handles the membership checkout flow using Stripe Checkout (hosted).
 *
 * Flow:
 * 1. User clicks .khm-checkout-trigger button
 * 2. Read membership tier data from button data attributes
 * 3. Open modal showing tier details
 * 4. User clicks "Proceed to Checkout"
 * 5. AJAX call creates Stripe Checkout Session
 * 6. Redirect user to Stripe-hosted checkout page
 * 7. User completes payment on Stripe
 * 8. Stripe redirects back to success/cancel URL
 * 9. Webhook handler activates membership
 */

(function($) {
    'use strict';

    const MembershipModal = {
        modal: null,
        currentTierData: null,

        /**
         * Initialize the modal system.
         */
        init: function() {
            this.createModal();
            this.bindEvents();
        },

        /**
         * Create and inject modal HTML into the page.
         */
        createModal: function() {
            const modalHTML = `
                <div id="khm-membership-modal" class="khm-modal-overlay" style="display: none;">
                    <div class="khm-modal-container khm-membership-container">
                        <div class="khm-modal-header">
                            <h3 id="khm-membership-modal-title">Join Our Membership</h3>
                            <button class="khm-modal-close" aria-label="Close">&times;</button>
                        </div>

                        <div class="khm-modal-content">
                            <!-- Tier Summary -->
                            <div class="khm-membership-summary">
                                <div class="khm-tier-header">
                                    <h4 id="khm-tier-name" class="khm-tier-name"></h4>
                                    <div class="khm-tier-price">
                                        <span id="khm-tier-price-amount" class="khm-price-amount"></span>
                                        <span id="khm-tier-price-interval" class="khm-price-interval"></span>
                                    </div>
                                </div>

                                <div id="khm-tier-description" class="khm-tier-description"></div>

                                <div id="khm-tier-features" class="khm-tier-features"></div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="khm-modal-actions">
                                <button id="khm-proceed-checkout" class="khm-btn-primary">
                                    Proceed to Checkout
                                </button>
                                <button class="khm-btn-secondary khm-modal-close">
                                    Cancel
                                </button>
                            </div>

                            <!-- Messages -->
                            <div id="khm-membership-messages" class="khm-messages"></div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
            this.modal = $('#khm-membership-modal');
        },

        /**
         * Bind all event handlers.
         */
        bindEvents: function() {
            // Trigger modal from button clicks
            $(document).on('click', '.khm-checkout-trigger', (e) => {
                e.preventDefault();
                const $button = $(e.currentTarget);
                this.openModal($button);
            });

            // Close modal
            $(document).on('click', '.khm-modal-close', (e) => {
                e.preventDefault();
                if ($(e.target).closest('#khm-membership-modal').length) {
                    this.closeModal();
                }
            });

            // Close on overlay click
            $(document).on('click', '#khm-membership-modal.khm-modal-overlay', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal();
                }
            });

            // Proceed to Stripe Checkout
            $(document).on('click', '#khm-proceed-checkout', (e) => {
                e.preventDefault();
                this.proceedToCheckout();
            });
        },

        /**
         * Open the modal and populate with membership tier data.
         *
         * @param {jQuery} $button The button that was clicked
         */
        openModal: function($button) {
            // Extract tier data from button data attributes
            this.currentTierData = {
                levelId: $button.data('membership-level-id'),
                levelName: $button.data('membership-level-name'),
                price: $button.data('membership-price'),
                priceDisplay: $button.data('membership-price-display'),
                interval: $button.data('membership-interval'),
                description: $button.data('membership-description') || '',
                monthlyCredits: $button.data('membership-monthly-credits') || 0,
            };

            // Validate required data
            if (!this.currentTierData.levelId || !this.currentTierData.levelName) {
                console.error('Missing required membership tier data');
                return;
            }

            // Populate modal with tier info
            this.populateModal();

            // Show modal
            this.modal.fadeIn(300);
            $('body').addClass('khm-modal-open');
        },

        /**
         * Populate modal content with current tier data.
         */
        populateModal: function() {
            const data = this.currentTierData;

            // Set tier name
            $('#khm-tier-name').text(data.levelName);

            // Set price display
            if (data.priceDisplay) {
                $('#khm-tier-price-amount').text(data.priceDisplay);
                $('#khm-tier-price-interval').text('/' + data.interval);
            } else {
                $('#khm-tier-price-amount').text('Free');
                $('#khm-tier-price-interval').text('');
            }

            // Set description
            if (data.description) {
                $('#khm-tier-description').html('<p>' + this.escapeHtml(data.description) + '</p>').show();
            } else {
                $('#khm-tier-description').hide();
            }

            // Set features
            const features = [];
            if (data.monthlyCredits > 0) {
                features.push(`<li><span class="khm-feature-icon">💳</span> ${data.monthlyCredits} monthly credits</li>`);
            }
            features.push('<li><span class="khm-feature-icon">📚</span> Access to member-only content</li>');
            features.push('<li><span class="khm-feature-icon">📥</span> Unlimited downloads</li>');

            if (features.length > 0) {
                $('#khm-tier-features').html('<ul>' + features.join('') + '</ul>').show();
            } else {
                $('#khm-tier-features').hide();
            }

            // Clear any previous messages
            this.clearMessages();
        },

        /**
         * Proceed to Stripe Checkout.
         * Creates a Checkout Session via AJAX and redirects to Stripe.
         */
        proceedToCheckout: function() {
            const $button = $('#khm-proceed-checkout');
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text('Creating checkout session...');
            this.clearMessages();

            const config = window.khmMembershipModal || {};

            // AJAX call to create Stripe Checkout Session
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_create_membership_checkout',
                    membership_level_id: this.currentTierData.levelId,
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success && response.data && response.data.checkout_url) {
                        // Redirect to Stripe Checkout
                        window.location.href = response.data.checkout_url;
                    } else {
                        // Show error message
                        const errorMessage = response.data && response.data.message
                            ? response.data.message
                            : 'Unable to create checkout session. Please try again.';
                        this.showError(errorMessage);
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: (xhr) => {
                    console.error('Checkout session creation failed:', xhr);
                    let errorMessage = 'An error occurred. Please try again.';

                    // Try to extract error message from response
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    } else if (xhr.responseText) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.data && data.data.message) {
                                errorMessage = data.data.message;
                            }
                        } catch (e) {
                            // Ignore parse error
                        }
                    }

                    this.showError(errorMessage);
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Close the modal.
         */
        closeModal: function() {
            this.modal.fadeOut(300);
            $('body').removeClass('khm-modal-open');
            this.clearMessages();
            this.currentTierData = null;
        },

        /**
         * Show error message in modal.
         *
         * @param {string} message Error message to display
         */
        showError: function(message) {
            $('#khm-membership-messages').html(
                '<div class="khm-message khm-error">' + this.escapeHtml(message) + '</div>'
            );
        },

        /**
         * Show success message in modal.
         *
         * @param {string} message Success message to display
         */
        showSuccess: function(message) {
            $('#khm-membership-messages').html(
                '<div class="khm-message khm-success">' + this.escapeHtml(message) + '</div>'
            );
        },

        /**
         * Clear all messages.
         */
        clearMessages: function() {
            $('#khm-membership-messages').empty();
        },

        /**
         * Escape HTML to prevent XSS.
         *
         * @param {string} text Text to escape
         * @return {string} Escaped text
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, (m) => map[m]);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        MembershipModal.init();
    });

    // Expose to global scope if needed
    window.KHMMembershipModal = MembershipModal;

})(jQuery);
