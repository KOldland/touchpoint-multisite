/**
 * KHM eCommerce Frontend JavaScript
 * Shopping cart, checkout, and purchase functionality
 * Based on TouchPoint Marketing Suite specifications
 */

jQuery(document).ready(function($) {
    'use strict';

    // eCommerce state
    let cart = {
        items: [],
        total: 0,
        subtotal: 0,
        tax: 0,
        discount: 0,
        shipping: 0
    };

    let checkoutStep = 1;
    let paymentMethod = 'stripe';
    let isProcessing = false;

    // Initialize
    init();

    function init() {
        bindEvents();
        loadCart();
        updateCartDisplay();
    }

    function bindEvents() {
        // Add to cart buttons (from social strip)
        $(document).on('click', '.kss-add-to-cart', handleAddToCart);
        
        // Cart management
        $(document).on('click', '.khm-quantity-btn', handleQuantityChange);
        $(document).on('change', '.khm-quantity-input', handleQuantityInput);
        $(document).on('click', '.khm-remove-item', handleRemoveItem);
        
        // Checkout process
        $(document).on('click', '.khm-checkout-btn', handleCheckoutStart);
        $(document).on('click', '.khm-place-order', handlePlaceOrder);
        
        // Payment methods
        $(document).on('click', '.khm-payment-method', handlePaymentMethodSelect);
        
        // Form validation
        $(document).on('blur', '.khm-form-input, .khm-form-select', validateField);
        $(document).on('submit', '#khm-checkout-form', handleCheckoutSubmit);
        
        // Promo codes
        $(document).on('click', '#apply-promo-code', handlePromoCode);
        
        // Continue shopping
        $(document).on('click', '.khm-continue-shopping', handleContinueShopping);
        
        // Auto-save checkout form
        $(document).on('input', '.khm-form-input', debounce(saveCheckoutData, 500));
    }

    /**
     * Add to Cart Handler
     */
    function handleAddToCart(e) {
        e.preventDefault();
        
        if (isProcessing) return;
        
        const $button = $(this);
        const postId = $button.data('post-id');
        const userId = $button.data('user-id') || 0;
        
        if (!postId) {
            showNotification('Error: Invalid article', 'error');
            return;
        }

        // Show loading state
        const originalText = $button.text();
        $button.html('<span class="khm-loading"></span> Adding...');
        $button.prop('disabled', true);
        
        // AJAX request to add to cart
        $.ajax({
            url: khmEcommerce.ajaxUrl,
            type: 'POST',
            data: {
                action: 'khm_add_to_cart',
                post_id: postId,
                user_id: userId,
                quantity: 1,
                nonce: khmEcommerce.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update local cart
                    addItemToLocalCart(response.data.item);
                    updateCartDisplay();
                    updateCartCount();
                    
                    // Show success message
                    showNotification('Article added to cart!', 'success');
                    
                    // Update button state
                    $button.text('Added to Cart ✓');
                    $button.addClass('added');
                    
                    // Reset button after delay
                    setTimeout(() => {
                        $button.text('Add to Cart');
                        $button.removeClass('added');
                        $button.prop('disabled', false);
                    }, 2000);
                    
                } else {
                    showNotification(response.data || 'Failed to add to cart', 'error');
                    $button.text(originalText);
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                showNotification('Connection error. Please try again.', 'error');
                $button.text(originalText);
                $button.prop('disabled', false);
            }
        });
    }

    /**
     * Load Cart from Server
     */
    function loadCart() {
        $.ajax({
            url: khmEcommerce.ajaxUrl,
            type: 'POST',
            data: {
                action: 'khm_get_cart',
                nonce: khmEcommerce.nonce
            },
            success: function(response) {
                if (response.success) {
                    cart = response.data;
                    updateCartDisplay();
                    updateCartCount();
                }
            }
        });
    }

    /**
     * Handle Quantity Changes
     */
    function handleQuantityChange(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $input = $button.siblings('.khm-quantity-input');
        const $item = $button.closest('.khm-cart-item');
        const itemId = $item.data('item-id');
        const currentQty = parseInt($input.val()) || 1;
        let newQty = currentQty;
        
        if ($button.hasClass('increase')) {
            newQty = currentQty + 1;
        } else if ($button.hasClass('decrease') && currentQty > 1) {
            newQty = currentQty - 1;
        }
        
        if (newQty !== currentQty) {
            updateCartItemQuantity(itemId, newQty);
            $input.val(newQty);
        }
    }

    /**
     * Handle Quantity Input Changes
     */
    function handleQuantityInput(e) {
        const $input = $(this);
        const $item = $input.closest('.khm-cart-item');
        const itemId = $item.data('item-id');
        const newQty = parseInt($input.val()) || 1;
        
        if (newQty >= 1) {
            updateCartItemQuantity(itemId, newQty);
        } else {
            $input.val(1);
        }
    }

    /**
     * Update Cart Item Quantity
     */
    function updateCartItemQuantity(itemId, quantity) {
        $.ajax({
            url: khmEcommerce.ajaxUrl,
            type: 'POST',
            data: {
                action: 'khm_update_cart_quantity',
                item_id: itemId,
                quantity: quantity,
                nonce: khmEcommerce.nonce
            },
            success: function(response) {
                if (response.success) {
                    cart = response.data;
                    updateCartDisplay();
                    updateCartCount();
                } else {
                    showNotification(response.data || 'Failed to update quantity', 'error');
                }
            }
        });
    }

    /**
     * Handle Remove Item
     */
    function handleRemoveItem(e) {
        e.preventDefault();
        
        if (!confirm('Remove this item from your cart?')) {
            return;
        }
        
        const $item = $(this).closest('.khm-cart-item');
        const itemId = $item.data('item-id');
        
        // Show loading state
        $item.addClass('loading');
        
        $.ajax({
            url: khmEcommerce.ajaxUrl,
            type: 'POST',
            data: {
                action: 'khm_remove_from_cart',
                item_id: itemId,
                nonce: khmEcommerce.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove item with animation
                    $item.fadeOut(300, function() {
                        $(this).remove();
                    });
                    
                    cart = response.data;
                    updateCartDisplay();
                    updateCartCount();
                    showNotification('Item removed from cart', 'success');
                } else {
                    showNotification(response.data || 'Failed to remove item', 'error');
                    $item.removeClass('loading');
                }
            }
        });
    }

    /**
     * Handle Checkout Start
     */
    function handleCheckoutStart(e) {
        e.preventDefault();
        
        if (cart.items.length === 0) {
            showNotification('Your cart is empty', 'warning');
            return;
        }
        
        // Redirect to checkout page or show checkout modal
        window.location.href = khmEcommerce.checkoutUrl;
    }

    /**
     * Handle Payment Method Selection
     */
    function handlePaymentMethodSelect(e) {
        e.preventDefault();
        
        const $method = $(this);
        const method = $method.data('method');
        
        // Update selection
        $('.khm-payment-method').removeClass('selected');
        $method.addClass('selected');
        
        // Show/hide card form
        $('.khm-card-form').removeClass('active');
        if (method === 'stripe') {
            $('.khm-card-form').addClass('active');
        }
        
        paymentMethod = method;
    }

    /**
     * Handle Place Order
     */
    function handlePlaceOrder(e) {
        e.preventDefault();
        
        if (isProcessing) return;
        
        // Validate form
        if (!validateCheckoutForm()) {
            return;
        }
        
        isProcessing = true;
        showLoadingOverlay('Processing your order...');
        
        const formData = collectCheckoutData();
        
        $.ajax({
            url: khmEcommerce.ajaxUrl,
            type: 'POST',
            data: {
                action: 'khm_process_checkout',
                checkout_data: formData,
                payment_method: paymentMethod,
                nonce: khmEcommerce.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Clear cart
                    cart = { items: [], total: 0, subtotal: 0, tax: 0, discount: 0, shipping: 0 };
                    updateCartDisplay();
                    updateCartCount();
                    
                    // Redirect to success page
                    window.location.href = response.data.redirect_url || khmEcommerce.successUrl;
                } else {
                    hideLoadingOverlay();
                    showNotification(response.data || 'Order processing failed', 'error');
                    isProcessing = false;
                }
            },
            error: function() {
                hideLoadingOverlay();
                showNotification('Connection error. Please try again.', 'error');
                isProcessing = false;
            }
        });
    }

    /**
     * Handle Promo Code Application
     */
    function handlePromoCode(e) {
        e.preventDefault();
        
        const promoCode = $('#promo-code').val().trim();
        
        if (!promoCode) {
            showNotification('Please enter a promo code', 'warning');
            return;
        }
        
        $.ajax({
            url: khmEcommerce.ajaxUrl,
            type: 'POST',
            data: {
                action: 'khm_apply_promo_code',
                promo_code: promoCode,
                nonce: khmEcommerce.nonce
            },
            success: function(response) {
                if (response.success) {
                    cart = response.data;
                    updateCartDisplay();
                    showNotification('Promo code applied!', 'success');
                } else {
                    showNotification(response.data || 'Invalid promo code', 'error');
                }
            }
        });
    }

    /**
     * Update Cart Display
     */
    function updateCartDisplay() {
        // Update cart summary
        $('.khm-subtotal-amount').text(formatPrice(cart.subtotal));
        $('.khm-tax-amount').text(formatPrice(cart.tax));
        $('.khm-discount-amount').text(formatPrice(cart.discount));
        $('.khm-total-amount').text(formatPrice(cart.total));
        
        // Update item count
        $('.khm-cart-count').text(cart.items.length);
        
        // Enable/disable checkout button
        const $checkoutBtn = $('.khm-checkout-btn');
        if (cart.items.length > 0 && cart.total > 0) {
            $checkoutBtn.prop('disabled', false);
        } else {
            $checkoutBtn.prop('disabled', true);
        }
    }

    /**
     * Update Cart Count in Header/Menu
     */
    function updateCartCount() {
        const count = cart.items.length;
        $('.cart-count, .khm-cart-count').text(count);
        
        if (count > 0) {
            $('.cart-count').addClass('has-items');
        } else {
            $('.cart-count').removeClass('has-items');
        }
    }

    /**
     * Add Item to Local Cart
     */
    function addItemToLocalCart(item) {
        // Check if item already exists
        const existingIndex = cart.items.findIndex(cartItem => cartItem.id === item.id);
        
        if (existingIndex >= 0) {
            cart.items[existingIndex].quantity += item.quantity;
        } else {
            cart.items.push(item);
        }
        
        // Recalculate totals
        recalculateCart();
    }

    /**
     * Recalculate Cart Totals
     */
    function recalculateCart() {
        cart.subtotal = cart.items.reduce((sum, item) => {
            return sum + (item.price * item.quantity);
        }, 0);
        
        cart.tax = cart.subtotal * 0.1; // 10% tax (configurable)
        cart.total = cart.subtotal + cart.tax + cart.shipping - cart.discount;
    }

    /**
     * Validate Checkout Form
     */
    function validateCheckoutForm() {
        let isValid = true;
        const requiredFields = [
            'billing_first_name',
            'billing_last_name', 
            'billing_email',
            'billing_address_1',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_country'
        ];
        
        requiredFields.forEach(fieldName => {
            const $field = $(`[name="${fieldName}"]`);
            if (!$field.val().trim()) {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });
        
        // Validate email
        const email = $('[name="billing_email"]').val();
        if (email && !isValidEmail(email)) {
            $('[name="billing_email"]').addClass('error');
            isValid = false;
        }
        
        if (!isValid) {
            showNotification('Please fill in all required fields', 'error');
        }
        
        return isValid;
    }

    /**
     * Collect Checkout Data
     */
    function collectCheckoutData() {
        const formData = {};
        
        // Collect all form fields
        $('.khm-checkout-form .khm-form-input, .khm-checkout-form .khm-form-select').each(function() {
            const $field = $(this);
            formData[$field.attr('name')] = $field.val();
        });
        
        // Collect checkboxes
        $('.khm-checkout-form .khm-checkbox:checked').each(function() {
            const $field = $(this);
            formData[$field.attr('name')] = true;
        });
        
        return formData;
    }

    /**
     * Save Checkout Data to Local Storage
     */
    function saveCheckoutData() {
        const data = collectCheckoutData();
        localStorage.setItem('khm_checkout_data', JSON.stringify(data));
    }

    /**
     * Load Checkout Data from Local Storage
     */
    function loadCheckoutData() {
        const data = localStorage.getItem('khm_checkout_data');
        if (data) {
            try {
                const formData = JSON.parse(data);
                
                Object.keys(formData).forEach(fieldName => {
                    const $field = $(`[name="${fieldName}"]`);
                    if ($field.length) {
                        if ($field.attr('type') === 'checkbox') {
                            $field.prop('checked', formData[fieldName]);
                        } else {
                            $field.val(formData[fieldName]);
                        }
                    }
                });
            } catch (e) {
                console.error('Error loading checkout data:', e);
            }
        }
    }

    /**
     * Utility Functions
     */
    function formatPrice(amount) {
        return '£' + parseFloat(amount).toFixed(2);
    }

    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function validateField(e) {
        const $field = $(this);
        const value = $field.val().trim();
        
        if ($field.attr('required') && !value) {
            $field.addClass('error');
            showFieldError($field, 'This field is required');
        } else {
            $field.removeClass('error');
            hideFieldError($field);
        }
        
        // Email validation
        if ($field.attr('type') === 'email' && value && !isValidEmail(value)) {
            $field.addClass('error');
            showFieldError($field, 'Please enter a valid email address');
        }
    }

    function showFieldError($field, message) {
        const $error = $field.siblings('.khm-form-error');
        if ($error.length) {
            $error.text(message);
        } else {
            $field.after(`<span class="khm-form-error">${message}</span>`);
        }
    }

    function hideFieldError($field) {
        $field.siblings('.khm-form-error').remove();
    }

    function showLoadingOverlay(message) {
        const $overlay = $('.khm-loading-overlay');
        if ($overlay.length) {
            $('.khm-loading-text').text(message);
            $overlay.addClass('active');
        }
    }

    function hideLoadingOverlay() {
        $('.khm-loading-overlay').removeClass('active');
    }

    function showNotification(message, type = 'info') {
        // Create notification element
        const $notification = $(`
            <div class="khm-notification khm-notification-${type}">
                <div class="khm-notification-content">
                    <span class="khm-notification-message">${message}</span>
                    <button class="khm-notification-close">&times;</button>
                </div>
            </div>
        `);
        
        // Add to page
        $('body').append($notification);
        
        // Show with animation
        setTimeout(() => {
            $notification.addClass('show');
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            removeNotification($notification);
        }, 5000);
        
        // Handle close button
        $notification.find('.khm-notification-close').on('click', function() {
            removeNotification($notification);
        });
    }

    function removeNotification($notification) {
        $notification.removeClass('show');
        setTimeout(() => {
            $notification.remove();
        }, 300);
    }

    function handleContinueShopping(e) {
        e.preventDefault();
        window.history.back();
    }

    function handleCheckoutSubmit(e) {
        e.preventDefault();
        handlePlaceOrder(e);
    }

    // Initialize checkout form if on checkout page
    if ($('.khm-checkout-form').length) {
        loadCheckoutData();
    }

    // Expose public methods for external use
    window.KHMEcommerce = {
        addToCart: handleAddToCart,
        updateCart: loadCart,
        getCart: () => cart,
        showNotification: showNotification
    };
});