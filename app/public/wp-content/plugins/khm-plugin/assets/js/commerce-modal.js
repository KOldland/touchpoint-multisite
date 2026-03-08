/**
 * Unified Commerce Modal JavaScript
 * 
 * Handles cart review, quick purchase, and payment processing
 * Reuses existing Stripe integration from CheckoutShortcode
 */

(function($) {
    'use strict';

    const CommerceModal = {
        stripe: null,
        elements: null,
        paymentElement: null,
        modal: null,
        currentPostId: null,
        currentUserName: '',
        currentUserEmail: '',
        fallbackArticle: null,
        clientSecret: null,
        isSubmitting: false,

        init: function() {
            const config = window.khmCommerce || {};
            if (typeof Stripe === 'undefined' || !config.stripe_key) {
                console.error('Commerce Modal: Stripe.js not loaded or key missing');
                return;
            }

            this.stripe = Stripe(config.stripe_key);
            this.createModal();
            this.bindEvents();
        },

        createModal: function() {
            const modalHTML = `
                <div id="khm-commerce-modal" class="khm-modal-overlay" style="display: none;">
                    <div class="khm-modal-container">
                        <div class="khm-modal-header">
                            <h3 id="khm-modal-title">Purchase Article</h3>
                            <button class="khm-modal-close" aria-label="Close">&times;</button>
                        </div>
                        
                        <div class="khm-modal-content">
                            <div id="khm-purchase-success" class="khm-success-state" style="display: none;">
                                <h4>Purchase Confirmed</h4>
                                <p>Your purchase was successful. Would you like to download the PDF now?</p>
                                <div class="khm-modal-actions">
                                    <button id="khm-download-now" class="khm-btn-primary">Download Now</button>
                                    <button id="khm-download-later" class="khm-btn-secondary">Download Later</button>
                                </div>
                                <div id="khm-purchase-followup" class="khm-messages"></div>
                            </div>
                            <div class="khm-article-summary">
                                <div class="khm-article-header">
                                    <div class="khm-article-thumb" id="khm-article-thumb"></div>
                                    <div class="khm-article-text">
                                        <h4 id="khm-article-title"></h4>
                                        <div class="khm-price-display">
                                            <span class="khm-member-price"></span>
                                            <span class="khm-regular-price"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="khm-article-note">
                                Purchase this article for permanent access online and unlimited PDF downloads.
                            </p>
                            
                            <!-- Payment Section (shared) -->
                            <div class="khm-payment-section">
                                <h4>Payment Information</h4>
                                
                                <div class="khm-billing-fields">
                                    <input type="text" id="khm-billing-name" placeholder="Full Name" required>
                                </div>

                                <div class="khm-promo-fields">
                                    <div class="khm-promo-row">
                                        <input type="text" id="khm-commerce-promo-code" placeholder="Promo code">
                                        <button type="button" id="khm-commerce-apply-promo" class="khm-btn-secondary">Apply</button>
                                        <button type="button" id="khm-commerce-remove-promo" class="khm-btn-link" style="display:none">Remove</button>
                                    </div>
                                    <div id="khm-commerce-promo-message" class="khm-messages"></div>
                                </div>
                                
                                <div class="khm-card-field">
                                    <div id="khm-payment-element"></div>
                                    <div id="khm-card-errors" class="khm-error"></div>
                                </div>
                                
                                <div class="khm-modal-actions">
                                    <button id="khm-complete-purchase" class="khm-btn-primary">
                                        Complete Purchase
                                    </button>
                                </div>
                                
                                <div id="khm-purchase-messages" class="khm-messages"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHTML);
            this.modal = $('#khm-commerce-modal');
        },

        setupStripeElements: async function() {
            if (!this.clientSecret) {
                return;
            }

            if (this.paymentElement) {
                this.paymentElement.unmount();
                this.paymentElement = null;
            }

            this.elements = this.stripe.elements({ clientSecret: this.clientSecret });
            this.paymentElement = this.elements.create('payment', {
                layout: 'tabs'
            });
            this.paymentElement.mount('#khm-payment-element');
        },

        bindEvents: function() {
            // Modal triggers from social strip
            $(document).on('click', '.kss-buy-button', (e) => {
                e.preventDefault();
                const $button = $(e.target).closest('.kss-buy-button');
                const isPurchased = $button.hasClass('purchased') ||
                    $button.data('purchased') === 1 ||
                    $button.data('purchased') === '1';
                if (isPurchased) {
                    return;
                }
                const postId = $button.data('post-id');
                this.openQuickBuy(postId);
            });

            // Generic trigger for widget/shortcode/block buttons.
            $(document).on('click', '.khm-commerce-checkout-trigger', (e) => {
                e.preventDefault();
                const $button = $(e.currentTarget);
                const postId = parseInt($button.data('post-id'), 10);
                if (!postId) {
                    return;
                }

                const fallbackMeta = {
                    title: ($button.data('title') || '').toString(),
                    image_url: ($button.data('image-url') || '').toString()
                };
                this.openQuickBuy(postId, fallbackMeta);
            });

            // Modal close
            $(document).on('click', '.khm-modal-close, .khm-modal-overlay', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal();
                }
            });

            // Purchase completion
            $(document).on('click', '#khm-complete-purchase', (e) => {
                e.preventDefault();
                this.processPurchase();
            });

            $(document).on('click', '#khm-commerce-apply-promo', (e) => {
                e.preventDefault();
                this.applyPromoCode();
            });

            $(document).on('click', '#khm-commerce-remove-promo', (e) => {
                e.preventDefault();
                this.removePromoCode();
            });

            // Success modal actions
            $(document).on('click', '#khm-download-now', (e) => {
                e.preventDefault();
                const urls = this.pendingDownloads || [];
                if (urls.length) {
                    urls.forEach(url => {
                        window.open(url, '_blank');
                    });
                    this.closeModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    $('#khm-purchase-followup').html('<div class="error">Download link not ready. Please check your member dashboard.</div>');
                }
            });

            $(document).on('click', '#khm-download-later', (e) => {
                e.preventDefault();
                $('#khm-purchase-followup')
                    .show()
                    .html('<div class="khm-download-later"><p>Reminder! Your purchased articles are available in the member dashboard.</p><button id="khm-continue-browsing" class="khm-btn-secondary">Continue Browsing</button></div>');
            });

            $(document).on('click', '#khm-continue-browsing', (e) => {
                e.preventDefault();
                this.closeModal();
                window.location.reload();
            });
        },

        openQuickBuy: function(postId, meta) {
            this.currentPostId = postId;
            this.fallbackArticle = meta || null;
            if (this.fallbackArticle) {
                this.populateArticleData(this.fallbackArticle);
            }
            this.refreshPromoState();
            this.loadArticleData(postId);
            this.createPaymentIntent(postId);
            this.showModal();
        },

        loadArticleData: function(postId) {
            const config = window.khmCommerce || {};
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_get_article_data',
                    post_id: postId,
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.populateArticleData(response.data);
                    }
                },
                error: () => {
                    if (this.fallbackArticle) {
                        this.populateArticleData(this.fallbackArticle);
                    }
                }
            });
        },
        createPaymentIntent: function(postId) {
            const config = window.khmCommerce || {};
            if (!config.ajax_url || !config.nonce) {
                return;
            }

            $('#khm-card-errors').text('').hide();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_create_commerce_intent',
                    post_id: postId,
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success && response.data && response.data.client_secret) {
                        this.clientSecret = response.data.client_secret;
                        this.setupStripeElements();
                    } else {
                        const errorMessage = typeof response.data === 'string'
                            ? response.data
                            : (response.data && response.data.error ? response.data.error : 'Unable to prepare payment.');
                        this.showError(errorMessage);
                    }
                },
                error: () => {
                    this.showError('Unable to prepare payment. Please refresh and try again.');
                }
            });
        },

        populateArticleData: function(data) {
            this.currentUserName = data.user_name || '';
            this.currentUserEmail = data.user_email || '';
            const resolvedTitle = data.title || this.resolveArticleTitle();
            $('#khm-article-title').text(resolvedTitle);

            const $thumb = $('#khm-article-thumb');
            if (data.image_url) {
                $thumb
                    .css('background-image', `url("${data.image_url}")`)
                    .addClass('has-image')
                    .removeClass('is-empty');
            } else {
                $thumb
                    .css('background-image', '')
                    .removeClass('has-image')
                    .addClass('is-empty');
            }
            if (data.member_price_formatted) {
                $('.khm-member-price').text(data.member_price_formatted).show();
            } else {
                $('.khm-member-price').hide();
            }
            
            if (data.regular_price_formatted && data.regular_price !== data.member_price) {
                $('.khm-regular-price').text(data.regular_price_formatted).show();
            } else {
                $('.khm-regular-price').hide();
            }
            
            // Pre-fill user data
            $('#khm-billing-name').val(this.currentUserName);
        },
        resolveArticleTitle: function() {
            const ogTitle = document.querySelector('meta[property="og:title"]');
            if (ogTitle && ogTitle.content) {
                return ogTitle.content;
            }

            if (document.title) {
                return document.title.replace(/\s*\|\s*.*$/, '');
            }

            return 'Article';
        },

        processPurchase: async function() {
            const purchaseBtn = $('#khm-complete-purchase');
            if (this.isSubmitting) {
                return;
            }
            this.isSubmitting = true;
            console.log('[KHM Commerce] Starting purchase', {
                postId: this.currentPostId,
                hasElements: !!this.elements,
                hasClientSecret: !!this.clientSecret
            });
            purchaseBtn.prop('disabled', true).text('Processing...');

            try {
                if (!this.currentPostId) {
                    this.showError('Missing article details. Please refresh and try again.');
                    return;
                }

                const config = window.khmCommerce || {};
                if (!this.elements || !this.clientSecret) {
                    throw new Error('Payment form is not ready. Please refresh and try again.');
                }

                const confirmation = await this.stripe.confirmPayment({
                    elements: this.elements,
                    redirect: 'if_required',
                    confirmParams: {
                        payment_method_data: {
                            billing_details: {
                                name: $('#khm-billing-name').val(),
                                email: this.currentUserEmail || undefined
                            }
                        }
                    }
                });
                console.log('[KHM Commerce] Stripe confirmation response', confirmation);

                if (confirmation.error) {
                    const maybeIntent = confirmation.error.payment_intent;
                    if (confirmation.error.code === 'payment_intent_unexpected_state' &&
                        maybeIntent &&
                        maybeIntent.status === 'succeeded') {
                        console.log('[KHM Commerce] Intent already succeeded, finalizing', maybeIntent.id);
                        await this.finalizePurchase(maybeIntent.id);
                        return;
                    }
                    throw new Error(confirmation.error.message);
                }

                if (!confirmation.paymentIntent || confirmation.paymentIntent.status !== 'succeeded') {
                    throw new Error('Payment could not be completed.');
                }

                console.log('[KHM Commerce] Intent succeeded, finalizing', confirmation.paymentIntent.id);
                await this.finalizePurchase(confirmation.paymentIntent.id);

            } catch (err) {
                console.error('[KHM Commerce] Purchase failed', err);
                const fallbackMessage = err && err.responseJSON && err.responseJSON.data
                    ? err.responseJSON.data
                    : (err && err.responseText ? err.responseText : null);
                const message = (err && err.message) ? err.message : (fallbackMessage || 'Payment failed. Please try again.');
                this.showError(message);
            } finally {
                this.isSubmitting = false;
                purchaseBtn.prop('disabled', false).text('Complete Purchase');
            }
        },
        applyPromoCode: function() {
            const code = ($('#khm-commerce-promo-code').val() || '').trim();
            if (!code) {
                this.showPromoMessage('Please enter a promo code.', 'error');
                return;
            }

            const config = window.khmCommerce || {};
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_apply_promo_code',
                    promo_code: code,
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const applied = response.data && response.data.promo_code ? response.data.promo_code : code;
                        $('#khm-commerce-promo-code').val(applied);
                        $('#khm-commerce-remove-promo').show();
                        this.showPromoMessage('Promo code applied.', 'success');
                        if (this.currentPostId) {
                            this.createPaymentIntent(this.currentPostId);
                        }
                    } else {
                        const message = typeof response.data === 'string' ? response.data : 'Invalid promo code.';
                        this.showPromoMessage(message, 'error');
                    }
                },
                error: () => {
                    this.showPromoMessage('Could not apply promo code. Please try again.', 'error');
                }
            });
        },
        removePromoCode: function() {
            const config = window.khmCommerce || {};
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_remove_promo_code',
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#khm-commerce-promo-code').val('');
                        $('#khm-commerce-remove-promo').hide();
                        this.showPromoMessage('Promo code removed.', 'success');
                        if (this.currentPostId) {
                            this.createPaymentIntent(this.currentPostId);
                        }
                    } else {
                        this.showPromoMessage('Could not remove promo code.', 'error');
                    }
                },
                error: () => {
                    this.showPromoMessage('Could not remove promo code.', 'error');
                }
            });
        },
        refreshPromoState: function() {
            const config = window.khmCommerce || {};
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_get_cart_data',
                    nonce: config.nonce
                },
                success: (response) => {
                    if (!response.success || !response.data) {
                        return;
                    }
                    const code = response.data.promo_code || '';
                    $('#khm-commerce-promo-code').val(code);
                    if (code) {
                        $('#khm-commerce-remove-promo').show();
                    } else {
                        $('#khm-commerce-remove-promo').hide();
                    }
                }
            });
        },
        showPromoMessage: function(message, type) {
            const css = type === 'success' ? 'success' : 'error';
            $('#khm-commerce-promo-message').html(`<div class="${css}">${message}</div>`);
        },
        finalizePurchase: async function(paymentIntentId) {
            const config = window.khmCommerce || {};
            console.log('[KHM Commerce] Finalize purchase request', {
                paymentIntentId,
                postId: this.currentPostId
            });
            const finalizeResponse = await $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_finalize_commerce_purchase',
                    payment_intent_id: paymentIntentId,
                    post_id: this.currentPostId,
                    billing_name: $('#khm-billing-name').val(),
                    billing_email: this.currentUserEmail,
                    nonce: config.nonce
                }
            });
            console.log('[KHM Commerce] Finalize response', finalizeResponse);

            if (!finalizeResponse.success) {
                const finalizeMessage = typeof finalizeResponse.data === 'string'
                    ? finalizeResponse.data
                    : (finalizeResponse.data && finalizeResponse.data.error ? finalizeResponse.data.error : 'Purchase failed');
                throw new Error(finalizeMessage);
            }

            this.handlePurchaseSuccess(finalizeResponse.data);
        },

        handlePurchaseSuccess: function(data) {
            this.pendingDownloads = data.download_urls || [];
            this.clearMessages();
            $('.khm-article-summary, .khm-article-note, .khm-payment-section').hide();
            $('#khm-purchase-success').show();
        },

        showModal: function() {
            this.modal.fadeIn(300);
            $('body').addClass('khm-modal-open');
        },

        closeModal: function() {
            this.modal.fadeOut(300);
            $('body').removeClass('khm-modal-open');
            this.clearMessages();
            $('#khm-purchase-success').hide();
            $('.khm-article-summary, .khm-article-note, .khm-payment-section').show();
            $('#khm-purchase-followup').empty();
        },

        showError: function(message) {
            $('#khm-purchase-messages').html(`<div class="error">${message}</div>`);
        },

        showSuccess: function(message) {
            $('#khm-purchase-messages').html(`<div class="success">${message}</div>`);
        },

        clearMessages: function() {
            $('#khm-purchase-messages').empty();
            $('#khm-card-errors').text('').hide();
            $('#khm-commerce-promo-message').empty();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CommerceModal.init();
    });

    // Make cart functionality available globally
    window.KHMCommerce = {
        openCart: () => {},
        openQuickBuy: (postId, meta) => CommerceModal.openQuickBuy(postId, meta),
        updateCartCount: () => {}
    };

})(jQuery);
