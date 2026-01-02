/**
 * KHM Integration JavaScript for Social Strip
 * Handles AJAX calls for Download, Save, Buy, and Gift functionality
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initKHMIntegration();
    });
    
    function initKHMIntegration() {
        // Download with Credits functionality
        $('.kss-download-credit').on('click', function(e) {
            e.preventDefault();
            handleCreditDownload($(this));
        });
        
        // Save to Library functionality
        $('.kss-save-button').on('click', function(e) {
            e.preventDefault();
            handleSaveToLibrary($(this));
        });
        
        // Buy PDF functionality
        $('.kss-buy-button.kss-add-to-cart').on('click', function(e) {
            e.preventDefault();
            handleBuyArticle($(this));
        });
        
        // Gift functionality
        $('.kss-gift-button').on('click', function(e) {
            e.preventDefault();
            handleGiftArticle($(this));
        });
        
        // Direct PDF download (for purchased articles)
        $('.kss-direct-download').on('click', function(e) {
            e.preventDefault();
            handleDirectDownload($(this));
        });
    }
    
    /**
     * Handle credit-based download
     */
    function handleCreditDownload($button) {
        const postId = $button.data('post-id');
        
        if (!postId) {
            showMessage('Error: Invalid article ID', 'error');
            return;
        }
        
        // Show loading state
        setButtonLoading($button, true);
        
        $.ajax({
            url: khm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kss_download_with_credit',
                post_id: postId,
                nonce: khm_ajax.nonce
            },
            success: function(response) {
                setButtonLoading($button, false);
                
                if (response.success) {
                    showMessage('Download started! Credits remaining: ' + response.data.credits_remaining, 'success');
                    
                    // Create download link
                    if (response.data.download_url) {
                        window.location.href = response.data.download_url;
                    }
                    
                    // Update credits display
                    updateCreditsDisplay(response.data.credits_remaining);
                    
                } else {
                    showMessage(response.data.error || 'Download failed', 'error');
                }
            },
            error: function() {
                setButtonLoading($button, false);
                showMessage('Network error. Please try again.', 'error');
            }
        });
    }
    
    /**
     * Handle save to library
     */
    function handleSaveToLibrary($button) {
        const postId = $button.data('post-id');
        const isSaved = $button.hasClass('saved');
        
        if (!postId) {
            showMessage('Error: Invalid article ID', 'error');
            return;
        }
        
        // Show loading state
        setButtonLoading($button, true);
        
        $.ajax({
            url: khm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: isSaved ? 'kss_remove_from_library' : 'kss_save_to_library',
                post_id: postId,
                nonce: khm_ajax.nonce
            },
            success: function(response) {
                setButtonLoading($button, false);
                
                if (response.success) {
                    // Toggle saved state
                    $button.toggleClass('saved');
                    
                    const message = isSaved ? 'Removed from library' : 'Saved to library';
                    showMessage(message, 'success');
                    
                    // Update button appearance
                    updateSaveButton($button, !isSaved);
                    
                } else {
                    showMessage(response.data.error || 'Save failed', 'error');
                }
            },
            error: function() {
                setButtonLoading($button, false);
                showMessage('Network error. Please try again.', 'error');
            }
        });
    }
    
    /**
     * Handle buy article - now opens unified modal
     */
    function handleBuyArticle($button) {
        const postId = $button.data('post-id');
        
        if (!postId) {
            showMessage('Error: Invalid article ID', 'error');
            return;
        }

        // Check if the commerce modal is available
        if (typeof window.KHMCommerce !== 'undefined' && window.KHMCommerce.openQuickBuy) {
            // Open the unified commerce modal for quick buy
            window.KHMCommerce.openQuickBuy(postId);
        } else {
            // Fallback to old cart behavior
            handleBuyArticleFallback($button);
        }
    }

    /**
     * Fallback buy article handler (legacy behavior)
     */
    function handleBuyArticleFallback($button) {
        const postId = $button.data('post-id');
        
        // Show loading state
        setButtonLoading($button, true);
        
        $.ajax({
            url: khm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kss_add_to_cart',
                post_id: postId,
                nonce: khm_ajax.nonce
            },
            success: function(response) {
                setButtonLoading($button, false);
                
                if (response.success) {
                    showMessage('Added to cart!', 'success');
                    
                    // Update cart count
                    updateCartCount(response.data.cart_count);
                    
                    // Offer to open cart modal if available
                    if (typeof window.KHMCommerce !== 'undefined' && window.KHMCommerce.openCart) {
                        setTimeout(() => {
                            if (confirm('Article added to cart. Would you like to review your cart?')) {
                                window.KHMCommerce.openCart();
                            }
                        }, 1000);
                    } else if (response.data.redirect_url) {
                        // Fallback to redirect
                        window.location.href = response.data.redirect_url;
                    }
                    
                } else {
                    showMessage(response.data.error || 'Failed to add to cart', 'error');
                }
            },
            error: function() {
                setButtonLoading($button, false);
                showMessage('Network error. Please try again.', 'error');
            }
        });
    }
    
    /**
     * Handle gift article
     */
    function handleGiftArticle($button) {
        const postId = $button.data('post-id');
        
        if (!postId) {
            showMessage('Error: Invalid article ID', 'error');
            return;
        }
        
        // Open gift modal
        openGiftModal(postId);
    }

    /**
     * Open gift modal with article data
     */
    function openGiftModal(postId) {
        // Show loading state
        showMessage('Loading gift options...', 'info');
        
        // Get gift data from server
        $.ajax({
            url: khm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kss_get_gift_data',
                post_id: postId,
                nonce: khm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create and show gift modal
                    createGiftModal(response.data);
                } else {
                    showMessage(response.data.error || 'Failed to load gift options', 'error');
                }
            },
            error: function() {
                showMessage('Network error. Please try again.', 'error');
            }
        });
    }

    /**
     * Create and display gift modal
     */
    function createGiftModal(data) {
        // Remove existing modal
        $('#kss-gift-modal').remove();
        
        // Create modal HTML
        const modalHtml = `
            <div id="kss-gift-modal" class="kss-modal">
                <div class="kss-modal-content">
                    <div class="kss-modal-header">
                        <h2>Send Article as Gift</h2>
                        <span class="kss-modal-close">&times;</span>
                    </div>
                    <div class="kss-modal-body">
                        <div class="gift-article-info">
                            <h3>${data.post.title}</h3>
                            <p class="article-excerpt">${data.post.excerpt}</p>
                            <div class="gift-price">
                                Gift Price: <strong>${data.pricing.currency}${data.pricing.member_price.toFixed(2)}</strong>
                                ${data.pricing.discount_percent > 0 ? `<small>(${data.pricing.discount_percent}% member discount applied)</small>` : ''}
                            </div>
                        </div>
                        
                        <form id="gift-form">
                            <div class="form-group">
                                <label for="recipient-name">Recipient Name *</label>
                                <input type="text" id="recipient-name" name="recipient_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="recipient-email">Recipient Email *</label>
                                <input type="email" id="recipient-email" name="recipient_email" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="sender-name">Your Name *</label>
                                <input type="text" id="sender-name" name="sender_name" value="${data.sender.name}" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="gift-message">Personal Message</label>
                                <textarea id="gift-message" name="gift_message" rows="4" placeholder="Add a personal message (optional)"></textarea>
                                
                                <div class="message-templates">
                                    <label>Quick Templates:</label>
                                    <div class="template-buttons">
                                        <button type="button" class="template-btn" data-template="birthday">Birthday</button>
                                        <button type="button" class="template-btn" data-template="holiday">Holiday</button>
                                        <button type="button" class="template-btn" data-template="thank_you">Thank You</button>
                                        <button type="button" class="template-btn" data-template="thinking_of_you">Thinking of You</button>
                                        <button type="button" class="template-btn" data-template="professional">Professional</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment-method">Payment Method</label>
                                <select id="payment-method" name="payment_method">
                                    <option value="stripe">Credit Card</option>
                                    <option value="paypal">PayPal</option>
                                </select>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn-cancel">Cancel</button>
                                <button type="submit" class="btn-send-gift">Send Gift</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        $('body').append(modalHtml);
        
        // Store template messages for quick access
        window.giftTemplates = data.templates;
        
        // Bind modal events
        bindGiftModalEvents(data);
        
        // Show modal
        $('#kss-gift-modal').fadeIn();
    }

    /**
     * Bind events for gift modal
     */
    function bindGiftModalEvents(data) {
        const $modal = $('#kss-gift-modal');
        
        // Close modal
        $modal.on('click', '.kss-modal-close, .btn-cancel', function() {
            closeGiftModal();
        });
        
        // Close on outside click
        $modal.on('click', function(e) {
            if (e.target === this) {
                closeGiftModal();
            }
        });
        
        // Template buttons
        $modal.on('click', '.template-btn', function() {
            const template = $(this).data('template');
            if (window.giftTemplates && window.giftTemplates[template]) {
                $('#gift-message').val(window.giftTemplates[template]);
            }
        });
        
        // Form submission
        $modal.on('submit', '#gift-form', function(e) {
            e.preventDefault();
            sendGift(data);
        });
    }

    /**
     * Send gift via AJAX
     */
    function sendGift(data) {
        const $form = $('#gift-form');
        const $submitBtn = $form.find('.btn-send-gift');
        
        // Validate form
        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }
        
        // Set loading state
        $submitBtn.prop('disabled', true).text('Sending Gift...');
        
        // Prepare form data
        const formData = {
            action: 'kss_send_gift',
            post_id: data.post.id,
            recipient_name: $('#recipient-name').val(),
            recipient_email: $('#recipient-email').val(),
            sender_name: $('#sender-name').val(),
            gift_message: $('#gift-message').val(),
            payment_method: $('#payment-method').val(),
            gift_price: data.pricing.original_price,
            nonce: khm_ajax.nonce
        };
        
        // Send AJAX request
        $.ajax({
            url: khm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                $submitBtn.prop('disabled', false).text('Send Gift');
                
                if (response.success) {
                    showMessage('Gift sent successfully! The recipient will receive an email shortly.', 'success');
                    closeGiftModal();
                } else {
                    showMessage(response.data.error || 'Failed to send gift', 'error');
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false).text('Send Gift');
                showMessage('Network error. Please try again.', 'error');
            }
        });
    }

    /**
     * Close gift modal
     */
    function closeGiftModal() {
        $('#kss-gift-modal').fadeOut(function() {
            $(this).remove();
        });
    }

    /**
     * Handle direct download (for purchased articles)
     */
    function handleDirectDownload($button) {
        const downloadUrl = $button.data('download-url') || $button.attr('href');
        
        if (!downloadUrl) {
            showMessage('Error: No download URL available', 'error');
            return;
        }
        
        // Track download
        $.ajax({
            url: khm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kss_track_download',
                download_url: downloadUrl,
                nonce: khm_ajax.nonce
            }
        });
        
        // Start download
        window.location.href = downloadUrl;
        showMessage('Download started!', 'success');
    }
    
    /**
     * Set button loading state
     */
    function setButtonLoading($button, loading) {
        if (loading) {
            $button.addClass('loading').prop('disabled', true);
            
            // Add spinner if not exists
            if (!$button.find('.spinner').length) {
                $button.append('<span class="spinner"></span>');
            }
        } else {
            $button.removeClass('loading').prop('disabled', false);
            $button.find('.spinner').remove();
        }
    }
    
    /**
     * Update save button appearance
     */
    function updateSaveButton($button, isSaved) {
        const $img = $button.find('img');
        
        if (isSaved) {
            $button.attr('title', 'Saved to Library');
            $img.attr('alt', 'Saved');
            // Could change icon to filled bookmark
        } else {
            $button.attr('title', 'Save to Library');
            $img.attr('alt', 'Save');
            // Could change icon to empty bookmark
        }
    }
    
    /**
     * Update credits display
     */
    function updateCreditsDisplay(credits) {
        $('.credits-count').text(credits);
        $('.kss-download-credit').each(function() {
            const $this = $(this);
            if (credits < 1) {
                $this.addClass('disabled').prop('disabled', true);
                $this.attr('title', 'Insufficient credits');
            } else {
                $this.removeClass('disabled').prop('disabled', false);
                $this.attr('title', 'Download (1 credit)');
            }
        });
    }
    
    /**
     * Update cart count display
     */
    function updateCartCount(count) {
        $('.cart-count').text(count);
        
        // Show/hide cart indicator
        if (count > 0) {
            $('.cart-indicator').show();
        } else {
            $('.cart-indicator').hide();
        }
    }
    
    /**
     * Show user message
     */
    function showMessage(message, type) {
        type = type || 'info';
        
        // Remove existing messages
        $('.kss-message').remove();
        
        // Create message element
        const $message = $('<div class="kss-message kss-message-' + type + '">' + message + '</div>');
        
        // Add to page
        $('body').prepend($message);
        
        // Auto-hide after 3 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    /**
     * Initialize on AJAX page loads (for SPA themes)
     */
    $(document).on('ajaxComplete', function() {
        initKHMIntegration();
    });
    
})(jQuery);