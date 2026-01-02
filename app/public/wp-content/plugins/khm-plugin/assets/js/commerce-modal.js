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
        cardElement: null,
        modal: null,
        currentTab: 'quick-buy',
        cartData: null,

        init: function() {
            if (typeof Stripe === 'undefined' || !khm_ajax.stripe_key) {
                console.error('Commerce Modal: Stripe.js not loaded or key missing');
                return;
            }

            this.stripe = Stripe(khm_ajax.stripe_key);
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
                        
                        <div class="khm-modal-tabs">
                            <button class="khm-tab-btn active" data-tab="quick-buy">Quick Buy</button>
                            <button class="khm-tab-btn" data-tab="cart" style="display: none;">
                                Cart (<span id="khm-cart-count">0</span>)
                            </button>
                        </div>
                        
                        <div class="khm-modal-content">
                            <!-- Quick Buy Tab -->
                            <div id="khm-tab-quick-buy" class="khm-tab-content active">
                                <div class="khm-article-summary">
                                    <h4 id="khm-article-title"></h4>
                                    <div class="khm-price-display">
                                        <span class="khm-member-price"></span>
                                        <span class="khm-regular-price"></span>
                                    </div>
                                    <div class="khm-purchase-options">
                                        <label>
                                            <input type="checkbox" id="khm-auto-download" checked>
                                            Auto-download PDF after purchase
                                        </label>
                                        <label>
                                            <input type="checkbox" id="khm-auto-save" checked>
                                            Save to my library
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cart Tab -->
                            <div id="khm-tab-cart" class="khm-tab-content">
                                <div id="khm-cart-items"></div>
                                <div class="khm-cart-total">
                                    <strong>Total: <span id="khm-cart-total">£0.00</span></strong>
                                </div>
                            </div>
                            
                            <!-- Payment Section (shared) -->
                            <div class="khm-payment-section">
                                <h4>Payment Information</h4>
                                
                                <div class="khm-billing-fields">
                                    <input type="text" id="khm-billing-name" placeholder="Full Name" required>
                                    <input type="email" id="khm-billing-email" placeholder="Email" required>
                                </div>
                                
                                <div class="khm-card-field">
                                    <div id="khm-card-element">
                                        <!-- Stripe Elements will mount here -->
                                    </div>
                                    <div id="khm-card-errors" class="khm-error"></div>
                                </div>
                                
                                <div class="khm-modal-actions">
                                    <button id="khm-complete-purchase" class="khm-btn-primary">
                                        Complete Purchase
                                    </button>
                                    <button class="khm-modal-close khm-btn-secondary">Cancel</button>
                                </div>
                                
                                <div id="khm-purchase-messages" class="khm-messages"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHTML);
            this.modal = $('#khm-commerce-modal');
            this.setupStripeElements();
        },

        setupStripeElements: function() {
            const elements = this.stripe.elements();
            
            this.cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#32325d',
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        '::placeholder': { color: '#aab7c4' }
                    },
                    invalid: { color: '#fa755a', iconColor: '#fa755a' }
                }
            });

            this.cardElement.mount('#khm-card-element');
            
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
            // Modal triggers from social strip
            $(document).on('click', '.kss-buy-button', (e) => {
                e.preventDefault();
                const postId = $(e.target).closest('[data-post-id]').data('post-id');
                this.openQuickBuy(postId);
            });

            // Modal close
            $(document).on('click', '.khm-modal-close, .khm-modal-overlay', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal();
                }
            });

            // Tab switching
            $(document).on('click', '.khm-tab-btn', (e) => {
                const tab = $(e.target).data('tab');
                this.switchTab(tab);
            });

            // Purchase completion
            $(document).on('click', '#khm-complete-purchase', (e) => {
                e.preventDefault();
                this.processPurchase();
            });

            // Cart updates
            $(document).on('click', '.khm-remove-item', (e) => {
                const postId = $(e.target).data('post-id');
                this.removeFromCart(postId);
            });
        },

        openQuickBuy: function(postId) {
            this.currentTab = 'quick-buy';
            this.loadArticleData(postId);
            this.showModal();
        },

        openCart: function() {
            this.currentTab = 'cart';
            this.loadCartData();
            this.switchTab('cart');
            this.showModal();
        },

        loadArticleData: function(postId) {
            $.ajax({
                url: khm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_get_article_data',
                    post_id: postId,
                    nonce: khm_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.populateArticleData(response.data);
                    }
                }
            });
        },

        populateArticleData: function(data) {
            $('#khm-article-title').text(data.title);
            $('.khm-member-price').text(data.member_price_formatted);
            
            if (data.regular_price !== data.member_price) {
                $('.khm-regular-price').text(data.regular_price_formatted).show();
            }
            
            // Pre-fill user data
            $('#khm-billing-name').val(data.user_name);
            $('#khm-billing-email').val(data.user_email);
        },

        loadCartData: function() {
            $.ajax({
                url: khm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'khm_get_cart_data',
                    nonce: khm_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.populateCartData(response.data);
                        this.updateCartCount(response.data.count);
                    }
                }
            });
        },

        populateCartData: function(cartData) {
            this.cartData = cartData;
            const cartHTML = cartData.items.map(item => `
                <div class="khm-cart-item" data-post-id="${item.post_id}">
                    <h5>${item.title}</h5>
                    <span class="khm-price">${item.price_formatted}</span>
                    <button class="khm-remove-item" data-post-id="${item.post_id}">Remove</button>
                </div>
            `).join('');
            
            $('#khm-cart-items').html(cartHTML);
            $('#khm-cart-total').text(cartData.total_formatted);
        },

        processPurchase: async function() {
            const purchaseBtn = $('#khm-complete-purchase');
            purchaseBtn.prop('disabled', true).text('Processing...');

            try {
                // Create payment method
                const {paymentMethod, error} = await this.stripe.createPaymentMethod({
                    type: 'card',
                    card: this.cardElement,
                    billing_details: {
                        name: $('#khm-billing-name').val(),
                        email: $('#khm-billing-email').val()
                    }
                });

                if (error) {
                    this.showError(error.message);
                    return;
                }

                // Process purchase
                const purchaseData = {
                    action: 'khm_process_commerce_purchase',
                    payment_method_id: paymentMethod.id,
                    purchase_type: this.currentTab,
                    auto_download: $('#khm-auto-download').is(':checked'),
                    auto_save: $('#khm-auto-save').is(':checked'),
                    nonce: khm_ajax.nonce
                };

                if (this.currentTab === 'quick-buy') {
                    purchaseData.post_id = this.getCurrentPostId();
                }

                $.ajax({
                    url: khm_ajax.ajax_url,
                    type: 'POST',
                    data: purchaseData,
                    success: (response) => {
                        if (response.success) {
                            this.handlePurchaseSuccess(response.data);
                        } else {
                            this.showError(response.data.error || 'Purchase failed');
                        }
                    },
                    error: () => {
                        this.showError('Network error. Please try again.');
                    },
                    complete: () => {
                        purchaseBtn.prop('disabled', false).text('Complete Purchase');
                    }
                });

            } catch (err) {
                this.showError(err.message);
                purchaseBtn.prop('disabled', false).text('Complete Purchase');
            }
        },

        handlePurchaseSuccess: function(data) {
            // Show success message
            this.showSuccess('Purchase completed successfully!');
            
            // Handle downloads
            if (data.download_urls && data.download_urls.length > 0) {
                data.download_urls.forEach(url => {
                    window.open(url, '_blank');
                });
            }
            
            // Update UI
            this.updateCartCount(0);
            
            // Close modal after delay
            setTimeout(() => {
                this.closeModal();
            }, 2000);
        },

        switchTab: function(tab) {
            $('.khm-tab-btn').removeClass('active');
            $('.khm-tab-content').removeClass('active');
            
            $(`.khm-tab-btn[data-tab="${tab}"]`).addClass('active');
            $(`#khm-tab-${tab}`).addClass('active');
            
            this.currentTab = tab;
            
            if (tab === 'cart') {
                this.loadCartData();
            }
        },

        showModal: function() {
            this.modal.fadeIn(300);
            $('body').addClass('khm-modal-open');
        },

        closeModal: function() {
            this.modal.fadeOut(300);
            $('body').removeClass('khm-modal-open');
            this.clearMessages();
        },

        updateCartCount: function(count) {
            $('#khm-cart-count').text(count);
            $('.khm-tab-btn[data-tab="cart"]').toggle(count > 0);
        },

        getCurrentPostId: function() {
            return $('.kss-social-strip').data('post-id');
        },

        removeFromCart: function(postId) {
            $.ajax({
                url: khm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kss_remove_from_cart',
                    post_id: postId,
                    nonce: khm_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.loadCartData();
                    }
                }
            });
        },

        showError: function(message) {
            $('#khm-purchase-messages').html(`<div class="error">${message}</div>`);
        },

        showSuccess: function(message) {
            $('#khm-purchase-messages').html(`<div class="success">${message}</div>`);
        },

        clearMessages: function() {
            $('#khm-purchase-messages').empty();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CommerceModal.init();
    });

    // Make cart functionality available globally
    window.KHMCommerce = {
        openCart: () => CommerceModal.openCart(),
        openQuickBuy: (postId) => CommerceModal.openQuickBuy(postId),
        updateCartCount: (count) => CommerceModal.updateCartCount(count)
    };

})(jQuery);