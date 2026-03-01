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
        appliedPromo: null,

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
            const config = window.khmMembershipModal || {};
            const showGuestCreateAccount = !config.isLoggedIn;
            const createAccountHTML = showGuestCreateAccount ? `
                            <div class="khm-create-account-section">
                                <label class="khm-create-account-toggle-wrap">
                                    <input type="checkbox" id="khm-create-account-toggle" />
                                    <strong>Create an account for free?</strong>
                                </label>
                                <div id="khm-create-account-details" class="khm-create-account-details" style="display:none;">
                                    <p class="khm-create-account-description">Create an account now so you can manage your membership and billing. We'll email you a secure link to set your password.</p>
                                    <div class="khm-field-group">
                                        <label for="khm-first-name">First name</label>
                                        <input id="khm-first-name" name="khm_first_name" type="text" class="regular-text" />
                                    </div>
                                    <div class="khm-field-group">
                                        <label for="khm-last-name">Last name</label>
                                        <input id="khm-last-name" name="khm_last_name" type="text" class="regular-text" />
                                    </div>
                                    <div class="khm-field-group">
                                        <label for="khm-mobile">Mobile (24A)</label>
                                        <input id="khm-mobile" name="khm_mobile" type="tel" class="regular-text" placeholder="+44 7123 456789" />
                                    </div>
                                    <div class="khm-field-group">
                                        <label for="khm-job-title">Job title</label>
                                        <input id="khm-job-title" name="khm_job_title" type="text" class="regular-text" />
                                    </div>
                                    <div class="khm-field-group">
                                        <label for="khm-company">Company</label>
                                        <input id="khm-company" name="khm_company" type="text" class="regular-text" />
                                    </div>
                                    <div class="khm-field-group">
                                        <label>
                                            <input id="khm-marketing-optin" name="khm_marketing_optin" type="checkbox" />
                                            I'd like to receive occasional updates and offers
                                        </label>
                                    </div>
                                </div>
                            </div>
            ` : '';
            const promoHTML = `
                            <div class="khm-membership-promo-section">
                                <label for="khm-membership-promo-code">Promo code</label>
                                <div class="khm-membership-promo-row">
                                    <input id="khm-membership-promo-code" type="text" class="regular-text" placeholder="Enter promo code" />
                                    <button type="button" id="khm-membership-apply-promo" class="khm-btn-secondary">Apply</button>
                                    <button type="button" id="khm-membership-remove-promo" class="khm-btn-link" style="display:none;">Remove</button>
                                </div>
                                <div id="khm-membership-promo-message" class="khm-messages"></div>
                            </div>
            `;

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
                            ${promoHTML}
                            ${createAccountHTML}

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

            // Toggle create-account details.
            $(document).on('change', '#khm-create-account-toggle', (e) => {
                const isChecked = $(e.currentTarget).is(':checked');
                $('#khm-create-account-details').toggle(isChecked);
            });

            $(document).on('click', '#khm-membership-apply-promo', (e) => {
                e.preventDefault();
                this.applyPromo();
            });

            $(document).on('click', '#khm-membership-remove-promo', (e) => {
                e.preventDefault();
                this.clearPromo();
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
            this.resetGuestCreateAccountFields();
            this.clearPromo();
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
            const payload = {
                action: 'khm_create_membership_checkout',
                membership_level_id: this.currentTierData.levelId,
                nonce: config.nonce
            };

            if (!config.isLoggedIn) {
                const createAccount = $('#khm-create-account-toggle').is(':checked');
                payload.create_account = createAccount ? 1 : 0;

                if (createAccount) {
                    const firstName = ($('#khm-first-name').val() || '').toString().trim();
                    const lastName = ($('#khm-last-name').val() || '').toString().trim();
                    const mobile = ($('#khm-mobile').val() || '').toString().trim();
                    const jobTitle = ($('#khm-job-title').val() || '').toString().trim();
                    const company = ($('#khm-company').val() || '').toString().trim();
                    const marketingOptIn = $('#khm-marketing-optin').is(':checked') ? 1 : 0;

                    if (!firstName || !lastName) {
                        this.showError('Please enter your first and last name to create an account.');
                        $button.prop('disabled', false).text(originalText);
                        return;
                    }

                    if (mobile && mobile.length < 7) {
                        this.showError('Please enter a valid mobile number.');
                        $button.prop('disabled', false).text(originalText);
                        return;
                    }

                    payload.profile = {
                        first_name: firstName,
                        last_name: lastName,
                        mobile: mobile,
                        job_title: jobTitle,
                        company: company,
                        marketing_opt_in: marketingOptIn
                    };
                }
            }

            if (this.appliedPromo && this.appliedPromo.promo_code) {
                payload.promo_code = this.appliedPromo.promo_code;
                payload.applied_promo_code = this.appliedPromo.promo_code;
                payload.applied_promo = this.appliedPromo.promo_id || '';
                if (this.appliedPromo.stripe_promotion_code) {
                    payload.stripe_promotion_code = this.appliedPromo.stripe_promotion_code;
                }
            }

            // AJAX call to create Stripe Checkout Session
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: payload,
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
            this.resetGuestCreateAccountFields();
            this.clearPromo();
            this.currentTierData = null;
        },

        /**
         * Reset the create-account collapsible state and values.
         */
        resetGuestCreateAccountFields: function() {
            $('#khm-create-account-toggle').prop('checked', false);
            $('#khm-create-account-details').hide();
            $('#khm-first-name, #khm-last-name, #khm-mobile, #khm-job-title, #khm-company').val('');
            $('#khm-marketing-optin').prop('checked', false);
        },

        applyPromo: function() {
            const promoCode = ($('#khm-membership-promo-code').val() || '').toString().trim();
            if (!promoCode) {
                this.showPromoMessage('Please enter a promo code.', 'error');
                return;
            }

            if (!this.currentTierData || !this.currentTierData.levelId) {
                this.showPromoMessage('Membership tier is missing.', 'error');
                return;
            }

            const config = window.khmMembershipModal || {};
            $('#khm-membership-apply-promo').prop('disabled', true).text('Applying...');
            this.showPromoMessage('', 'clear');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_validate_membership_promo',
                    membership_level_id: this.currentTierData.levelId,
                    promo_code: promoCode,
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.appliedPromo = {
                            promo_code: response.data.promo_code || promoCode,
                            promo_id: response.data.promo_id || '',
                            promo_type: response.data.promo_type || '',
                            promo_amount: response.data.promo_amount || 0,
                            stripe_promotion_code: response.data.stripe_promotion_code || ''
                        };
                        $('#khm-membership-promo-code').val(this.appliedPromo.promo_code).prop('readonly', true);
                        $('#khm-membership-apply-promo').text('Applied');
                        $('#khm-membership-remove-promo').show();
                        this.showPromoMessage(response.data.message || 'Promo code applied.', 'success');
                    } else {
                        this.clearPromo(false);
                        const message = response.data && response.data.message ? response.data.message : 'Invalid promo code.';
                        this.showPromoMessage(message, 'error');
                    }
                    $('#khm-membership-apply-promo').prop('disabled', false);
                },
                error: (xhr) => {
                    this.clearPromo(false);
                    let message = 'Unable to validate promo code.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                    this.showPromoMessage(message, 'error');
                    $('#khm-membership-apply-promo').prop('disabled', false).text('Apply');
                }
            });
        },

        clearPromo: function(clearMessage = true) {
            this.appliedPromo = null;
            $('#khm-membership-promo-code').val('').prop('readonly', false);
            $('#khm-membership-apply-promo').prop('disabled', false).text('Apply');
            $('#khm-membership-remove-promo').hide();
            if (clearMessage) {
                this.showPromoMessage('', 'clear');
            }
        },

        showPromoMessage: function(message, type) {
            const $node = $('#khm-membership-promo-message');
            if (type === 'clear' || !message) {
                $node.empty();
                return;
            }
            const css = type === 'success' ? 'khm-message khm-success' : 'khm-message khm-error';
            $node.html('<div class="' + css + '">' + this.escapeHtml(message) + '</div>');
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
