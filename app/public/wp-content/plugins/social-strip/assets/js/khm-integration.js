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
        refreshStripStatus();
        window.kssRefreshStripStatus = refreshStripStatus;

        // Download with Credits functionality
        $(document).off('click.kssDownload', '.kss-download-credit');
        $(document).on('click.kssDownload', '.kss-download-credit', function(e) {
            e.preventDefault();
            handleCreditDownload($(this));
        });
        
        // Save to Library functionality
        $(document).off('click.kssSave', '.kss-save-button');
        $(document).on('click.kssSave', '.kss-save-button', function(e) {
            e.preventDefault();
            handleSaveToLibrary($(this));
        });
        
        // Buy PDF functionality
        $(document).off('click.kssBuy', '.kss-buy-button');
        $(document).on('click.kssBuy', '.kss-buy-button', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            e.stopPropagation();
            handleBuyArticle($(this));
        });
        
        // Gift functionality
        $(document).off('click.kssGift', '.kss-gift-button');
        $(document).on('click.kssGift', '.kss-gift-button', function(e) {
            e.preventDefault();
            handleGiftArticle($(this));
        });
        
        // Direct PDF download (for purchased articles)
        $(document).off('click.kssDirect', '.kss-direct-download');
        $(document).on('click.kssDirect', '.kss-direct-download', function(e) {
            e.preventDefault();
            handleDirectDownload($(this));
        });
    }

    function refreshStripStatus() {
        if (typeof khm_ajax === 'undefined') {
            return;
        }

        const postIds = new Set();
        $('.kss-social-strip button[data-post-id]').each(function() {
            const postId = $(this).data('post-id');
            if (postId) {
                postIds.add(postId);
            }
        });

        if (!postIds.size) {
            return;
        }

        postIds.forEach((postId) => {
            $.ajax({
                url: khm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kss_get_strip_status',
                    post_id: postId,
                    nonce: khm_ajax.nonce
                },
                success: function(response) {
                    if (!response || !response.success) {
                        return;
                    }

                    const data = response.data || {};

                    const $downloadButtons = $('.kss-download-credit[data-post-id="' + postId + '"]');
                    if (data.has_downloaded) {
                        $downloadButtons.addClass('downloaded').attr('title', 'Redownload (already downloaded)');
                        $downloadButtons.closest('.kss-action').find('.kss-label').text('Redownload PDF');
                    } else {
                        $downloadButtons.removeClass('downloaded');
                    }

                    const $buyButtons = $('.kss-buy-button[data-post-id="' + postId + '"]');
                    if (data.is_purchased) {
                        $buyButtons.addClass('purchased')
                            .attr('data-purchased', '1')
                            .attr('title', 'Purchased');
                        $buyButtons.closest('.kss-action').find('.kss-label').text('Purchased');
                    } else {
                        $buyButtons.removeClass('purchased')
                            .attr('data-purchased', '0');
                    }

                    if (typeof data.is_saved !== 'undefined') {
                        const $saveButtons = $('.kss-save-button[data-post-id="' + postId + '"]');
                        if (data.is_saved) {
                            $saveButtons.addClass('saved').attr('title', 'Saved to Library');
                        } else {
                            $saveButtons.removeClass('saved').attr('title', 'Save to Library');
                        }
                    }
                }
            });
        });
    }
    
    /**
     * Handle credit-based download
     * Uses REST API to check eligibility, shows confirmation modal, then processes download
     */
    function handleCreditDownload($button) {
        const postId = $button.data('post-id');
        
        if (!postId) {
            showMessage('Error: Invalid article ID', 'error');
            return;
        }
        
        // Show loading state
        setButtonLoading($button, true);
        
        // First, check eligibility via REST API
        $.ajax({
            url: khm_ajax.rest_url + 'khm/v1/download/check/' + postId,
            type: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khm_ajax.rest_nonce);
            },
            success: function(response) {
                setButtonLoading($button, false);
                
                if (!response.success) {
                    showMessage(response.error || 'Failed to check eligibility', 'error');
                    return;
                }
                
                // If user can't download, show error
                if (!response.can_download) {
                    showMessage(response.message, 'error');
                    return;
                }
                
                // If it's free (re-download), show re-download confirmation modal
                if (response.is_free) {
                    showRedownloadConfirmationModal(response, $button);
                    return;
                }
                
                // Show confirmation modal for credit deduction
                showDownloadConfirmationModal(response, $button);
            },
            error: function(xhr) {
                setButtonLoading($button, false);
                const errorMsg = xhr.responseJSON?.message || 'Network error. Please try again.';
                showMessage(errorMsg, 'error');
            }
        });
    }
    
    /**
     * Show download confirmation modal
     * Uses Touchpoint Design System
     */
    function showDownloadConfirmationModal(data, $button) {
        // Remove existing modal
        $('#kss-download-modal').remove();
        
        const modalHtml = `
            <div id="kss-download-modal" class="khm-modal-backdrop">
                <div class="khm-modal" style="min-width: 400px; max-width: 480px;">
                    <div class="khm-modal-header">
                        <h3 class="khm-modal-title">Confirm Download</h3>
                        <button class="khm-modal-close">&times;</button>
                    </div>
                    <div class="khm-modal-content">
                        <div class="tp-modal-title-strip">
                            <h4>${data.post_title}</h4>
                        </div>
                        
                        <div class="tp-credit-info">
                            <p><strong>Credit Cost:</strong> ${data.credits_required} credit${data.credits_required > 1 ? 's' : ''}</p>
                            <p><strong>Your Balance:</strong> ${data.user_credits} credit${data.user_credits !== 1 ? 's' : ''}</p>
                            <p class="tp-credit-remaining"><strong>After download:</strong> ${data.user_credits - data.credits_required} credits remaining</p>
                        </div>
                        
                        <p class="tp-modal-notice">${data.message}</p>
                        
                        <div class="tp-modal-actions">
                            <button class="btn-confirm-download tp-btn tp-btn-primary">Download PDF</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        $('body').append(modalHtml);
        
        const $modal = $('#kss-download-modal');
        
        // Bind close events
        $modal.on('click', '.khm-modal-close', function() {
            $modal.removeClass('show');
            setTimeout(() => $modal.remove(), 300);
        });
        
        // Click outside to close
        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.removeClass('show');
                setTimeout(() => $modal.remove(), 300);
            }
        });
        
        // Confirm download
        $modal.on('click', '.btn-confirm-download', function() {
            $modal.removeClass('show');
            setTimeout(() => $modal.remove(), 300);
            processConfirmedDownload(data.post_id, $button);
        });
        
        // Handle ESC key
        $(document).on('keydown.downloadModal', function(e) {
            if (e.key === 'Escape') {
                $modal.removeClass('show');
                setTimeout(() => $modal.remove(), 300);
                $(document).off('keydown.downloadModal');
            }
        });
        
        // Show modal with animation
        requestAnimationFrame(() => {
            $modal.addClass('show');
        });
    }
    
    /**
     * Show re-download confirmation modal (for free re-downloads)
     * Uses Touchpoint Design System
     */
    function showRedownloadConfirmationModal(data, $button) {
        // Remove existing modal
        $('#kss-download-modal').remove();
        
        const modalHtml = `
            <div id="kss-download-modal" class="khm-modal-backdrop">
                <div class="khm-modal" style="min-width: 400px; max-width: 480px;">
                    <div class="khm-modal-header">
                        <h3 class="khm-modal-title">Re-Download PDF</h3>
                        <button class="khm-modal-close">&times;</button>
                    </div>
                    <div class="khm-modal-content">
                        <div class="tp-modal-title-strip">
                            <h4>${data.post_title}</h4>
                        </div>
                        
                        <div class="tp-credit-success">
                            <p class="tp-success-title">✓ Free Re-Download</p>
                            <p>You have already downloaded this PDF, so you may re-download again without using any credits.</p>
                        </div>
                        
                        <div class="tp-balance-info">
                            <p><strong>Your Credit Balance:</strong> ${data.user_credits} credit${data.user_credits !== 1 ? 's' : ''}</p>
                        </div>
                        
                        <div class="tp-modal-actions">
                            <button class="btn-confirm-download tp-btn tp-btn-success">Download PDF</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        $('body').append(modalHtml);
        
        const $modal = $('#kss-download-modal');
        
        // Bind close events
        $modal.on('click', '.khm-modal-close', function() {
            $modal.removeClass('show');
            setTimeout(() => $modal.remove(), 300);
        });
        
        // Click outside to close
        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.removeClass('show');
                setTimeout(() => $modal.remove(), 300);
            }
        });
        
        // Confirm download
        $modal.on('click', '.btn-confirm-download', function() {
            $modal.removeClass('show');
            setTimeout(() => $modal.remove(), 300);
            processConfirmedDownload(data.post_id, $button);
        });
        
        // Handle ESC key
        $(document).on('keydown.redownloadModal', function(e) {
            if (e.key === 'Escape') {
                $modal.removeClass('show');
                setTimeout(() => $modal.remove(), 300);
                $(document).off('keydown.redownloadModal');
            }
        });
        
        // Show modal with animation
        requestAnimationFrame(() => {
            $modal.addClass('show');
        });
    }
    
    /**
     * Process confirmed download - deduct credits and trigger PDF download
     */
    function processConfirmedDownload(postId, $button) {
        setButtonLoading($button, true);
        
        $.ajax({
            url: khm_ajax.rest_url + 'khm/v1/download/' + postId,
            type: 'POST',
            data: JSON.stringify({ confirm: true }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khm_ajax.rest_nonce);
            },
            success: function(response) {
                setButtonLoading($button, false);
                
                if (response.success && response.download_url) {
                    showMessage(response.message || 'Download started!', 'success');
                    
                    // Update credits display
                    updateCreditsDisplay(response.credits_remaining);
                    
                    // Open PDF in new tab
                    window.open(response.download_url, '_blank');
                } else {
                    showMessage(response.error || 'Download failed', 'error');
                }
            },
            error: function(xhr) {
                setButtonLoading($button, false);
                const errorMsg = xhr.responseJSON?.error || 'Network error. Please try again.';
                showMessage(errorMsg, 'error');
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
        
        // If already saved, show confirmation before removing
        if (isSaved) {
            showRemoveFromLibraryConfirmation($button, postId);
            return;
        }
        
        // Show loading state
        setButtonLoading($button, true);
        
        $.ajax({
            url: khm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kss_save_to_library',
                post_id: postId,
                nonce: khm_ajax.nonce
            },
            success: function(response) {
                setButtonLoading($button, false);
                
                if (response.success) {
                    // Add saved class
                    $button.addClass('saved');
                    
                    // Show subtle flash notification
                    showSaveFlashNotification('Saved to library', 'success');
                    
                    // Update button appearance
                    updateSaveButton($button, true);
                    
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
     * Show confirmation modal before removing from library
     */
    function showRemoveFromLibraryConfirmation($button, postId) {
        // Remove existing modal
        $('#kss-remove-library-modal').remove();
        
        const postTitle = document.title || 'this article';
        
        const modalHtml = `
            <div id="kss-remove-library-modal" class="khm-modal-backdrop">
                <div class="khm-modal" style="min-width: 400px; max-width: 480px;">
                    <div class="khm-modal-header">
                        <h3 class="khm-modal-title">Remove from Library</h3>
                        <button class="khm-modal-close">&times;</button>
                    </div>
                    <div class="khm-modal-content">
                        <div class="tp-modal-warning">
                            <p>Are you sure you want to remove this article from your library?</p>
                            <p class="tp-warning-note">You can always save it again later.</p>
                        </div>
                        
                        <div class="tp-modal-actions">
                            <button class="btn-confirm-remove tp-btn tp-btn-danger">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        $('body').append(modalHtml);
        
        const $modal = $('#kss-remove-library-modal');
        
        // Bind close events
        $modal.on('click', '.khm-modal-close', function() {
            $modal.removeClass('show');
            setTimeout(() => $modal.remove(), 300);
        });
        
        // Click outside to close
        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.removeClass('show');
                setTimeout(() => $modal.remove(), 300);
            }
        });
        
        // Confirm remove
        $modal.on('click', '.btn-confirm-remove', function() {
            $modal.removeClass('show');
            setTimeout(() => $modal.remove(), 300);
            processRemoveFromLibrary($button, postId);
        });
        
        // Handle ESC key
        $(document).on('keydown.removeLibraryModal', function(e) {
            if (e.key === 'Escape') {
                $modal.removeClass('show');
                setTimeout(() => $modal.remove(), 300);
                $(document).off('keydown.removeLibraryModal');
            }
        });
        
        // Show modal with animation
        requestAnimationFrame(() => {
            $modal.addClass('show');
        });
    }
    
    /**
     * Process actual removal from library
     */
    function processRemoveFromLibrary($button, postId) {
        setButtonLoading($button, true);
        
        $.ajax({
            url: khm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kss_remove_from_library',
                post_id: postId,
                nonce: khm_ajax.nonce
            },
            success: function(response) {
                setButtonLoading($button, false);
                
                if (response.success) {
                    // Remove saved class
                    $button.removeClass('saved');
                    
                    // Show subtle flash notification
                    showSaveFlashNotification('Removed from library', 'success');
                    
                    // Update button appearance
                    updateSaveButton($button, false);
                    
                } else {
                    showMessage(response.data.error || 'Remove failed', 'error');
                }
            },
            error: function() {
                setButtonLoading($button, false);
                showMessage('Network error. Please try again.', 'error');
            }
        });
    }
    
    /**
     * Show subtle flash notification for save/remove actions
     */
    function showSaveFlashNotification(message, type) {
        // Remove existing notification
        $('.kss-save-notification').remove();
        
        let iconClass = 'i';
        if (type === 'success') {
            iconClass = '✓';
        } else if (type === 'error') {
            iconClass = '✕';
        }
        
        const notificationHtml = `
            <div class="kss-save-notification kss-notification-${type}">
                <span class="kss-notification-icon">${iconClass}</span>
                <span class="kss-notification-text">${message}</span>
            </div>
        `;
        
        $('body').append(notificationHtml);
        
        const $notification = $('.kss-save-notification');
        
        // Animate in
        requestAnimationFrame(() => {
            $notification.addClass('show');
        });
        
        // Auto-hide after 2 seconds
        setTimeout(() => {
            $notification.removeClass('show');
            setTimeout(() => {
                $notification.remove();
            }, 300);
        }, 2000);
    }
    
    /**
     * Handle buy article - now opens unified modal
     */
    function handleBuyArticle($button) {
        if ($button.hasClass('purchased') || $button.data('purchased') === 1 || $button.data('purchased') === '1') {
            showSaveFlashNotification('Already purchased. Download from your member dashboard.', 'success');
            return;
        }
        const postId = $button.data('post-id');
        
        if (!postId) {
            showMessage('Error: Invalid article ID', 'error');
            return;
        }

        const fallbackMeta = {
            title: $button.data('title') || '',
            image_url: $button.data('image') || ''
        };

        if (typeof window.KHMCommerce !== 'undefined' && window.KHMCommerce.openQuickBuy) {
            window.KHMCommerce.openQuickBuy(postId, fallbackMeta);
            return;
        }

        loadCommerceModalAssets()
            .then(() => {
                if (window.KHMCommerce && window.KHMCommerce.openQuickBuy) {
                    window.KHMCommerce.openQuickBuy(postId, fallbackMeta);
                } else {
                    showMessage('Purchase modal failed to load. Please refresh and try again.', 'error');
                }
            })
            .catch(() => {
                showMessage('Purchase modal failed to load. Please refresh and try again.', 'error');
            });
    }

    function loadCommerceModalAssets() {
        if (loadCommerceModalAssets.promise) {
            return loadCommerceModalAssets.promise;
        }

        loadCommerceModalAssets.promise = new Promise((resolve, reject) => {
            if (!khm_ajax || !khm_ajax.commerce_js || !khm_ajax.commerce_css) {
                reject();
                return;
            }

            if (typeof window.khmCommerce === 'undefined') {
                window.khmCommerce = {
                    ajax_url: khm_ajax.ajax_url,
                    nonce: khm_ajax.commerce_nonce,
                    stripe_key: khm_ajax.stripe_key
                };
            }

            if (!document.querySelector('link[data-khm-commerce]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = khm_ajax.commerce_css;
                link.setAttribute('data-khm-commerce', 'true');
                document.head.appendChild(link);
            }

            const existingScript = document.querySelector('script[data-khm-commerce]');
            if (existingScript) {
                waitForCommerce(resolve, reject);
                return;
            }

            const script = document.createElement('script');
            script.src = khm_ajax.commerce_js;
            script.defer = true;
            script.setAttribute('data-khm-commerce', 'true');
            script.onload = () => waitForCommerce(resolve, reject);
            script.onerror = () => reject();
            document.head.appendChild(script);
        });

        return loadCommerceModalAssets.promise;
    }

    function waitForCommerce(resolve, reject) {
        const start = Date.now();
        const timer = setInterval(() => {
            if (window.KHMCommerce && window.KHMCommerce.openQuickBuy) {
                clearInterval(timer);
                resolve();
                return;
            }
            if (Date.now() - start > 3000) {
                clearInterval(timer);
                reject();
            }
        }, 100);
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
    
    const GiftModal = {
        stripe: null,
        elements: null,
        paymentElement: null,
        clientSecret: null,
        data: null,
        recipients: [],
        intentCount: 0,

        init: function(postId) {
            if (!postId) {
                showMessage('Error: Invalid article ID', 'error');
                return;
            }

            if (typeof Stripe === 'undefined' || !khm_ajax.stripe_key) {
                showMessage('Payment system unavailable. Please refresh and try again.', 'error');
                return;
            }

            this.stripe = this.stripe || Stripe(khm_ajax.stripe_key);
            this.loadGiftData(postId);
        },

        loadGiftData: function(postId) {
            showMessage('Loading gift options...', 'info');
            $.ajax({
                url: khm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kss_get_gift_data',
                    post_id: postId,
                    nonce: khm_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.data = response.data;
                        this.recipients = [];
                        this.clientSecret = null;
                        this.intentCount = 0;
                        this.renderModal();
                    } else {
                        showMessage(response.data.error || 'Failed to load gift options', 'error');
                    }
                },
                error: () => {
                    showMessage('Network error. Please try again.', 'error');
                }
            });
        },

        renderModal: function() {
            $('#kss-gift-modal').remove();

            const modalHtml = `
                <div id="kss-gift-modal" class="kss-modal">
                    <div class="kss-modal-content kss-gift-modal">
                        <div class="kss-modal-header">
                            <h2>Send Article as Gift</h2>
                            <span class="kss-modal-close">&times;</span>
                        </div>
                        <div class="kss-modal-body">
                            <div class="kss-gift-summary">
                                <div class="kss-gift-article">
                                    <div class="kss-gift-thumb ${this.data.post.image_url ? 'has-image' : 'is-empty'}" style="${this.data.post.image_url ? `background-image: url('${this.data.post.image_url}')` : ''}"></div>
                                    <div class="kss-gift-title-wrap">
                                        <h3>${this.data.post.title}</h3>
                                        <div class="kss-gift-price">Price per recipient: <strong>${this.data.pricing.currency}${this.data.pricing.member_price.toFixed(2)}</strong></div>
                                    </div>
                                </div>
                            </div>

                            <div class="kss-gift-note">
                                Purchase this article for permanent access online and unlimited PDF downloads.
                            </div>

                            <form id="kss-gift-form" class="kss-gift-form">
                                <div class="kss-gift-field">
                                    <label for="kss-gift-message">Message</label>
                                    <textarea id="kss-gift-message" rows="3">${this.data.default_message}</textarea>
                                </div>

                                <div class="kss-gift-field">
                                    <label for="kss-gift-sender">Sender Name</label>
                                    <input type="text" id="kss-gift-sender" value="${this.data.sender.name}" required>
                                </div>

                                <div class="kss-gift-field kss-gift-email-row">
                                    <label for="kss-gift-email">Recipient Email</label>
                                    <div class="kss-gift-email-input">
                                        <input type="email" id="kss-gift-email" placeholder="Enter an email address">
                                        <button type="button" class="kss-gift-add-email" aria-label="Add email">+</button>
                                    </div>
                                    <div class="kss-gift-helper">Each email added costs ${this.data.pricing.currency}${this.data.pricing.member_price.toFixed(2)}.</div>
                                </div>

                                <div class="kss-gift-recipient-list"></div>

                                <div class="kss-gift-total">
                                    <span>Total</span>
                                    <strong>${this.data.pricing.currency}0.00</strong>
                                </div>

                                <div class="kss-gift-payment">
                                    <h4>Payment Information</h4>
                                    <div id="kss-gift-payment-element"></div>
                                    <div id="kss-gift-errors" class="kss-gift-errors"></div>
                                    <div class="kss-modal-actions">
                                        <button type="submit" class="btn-send-gift" disabled>Send Gift</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            this.bindModalEvents();
            this.renderRecipients();
            this.updateTotals();
            $('#kss-gift-modal').fadeIn();
        },

        bindModalEvents: function() {
            const $modal = $('#kss-gift-modal');

            $modal.on('click', '.kss-modal-close', () => this.closeModal());
            $modal.on('click', (e) => {
                if (e.target === $modal[0]) {
                    this.closeModal();
                }
            });

            $modal.on('click', '.kss-gift-add-email', () => this.addRecipient());
            $modal.on('keypress', '#kss-gift-email', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.addRecipient();
                }
            });
            $modal.on('click', '.kss-gift-remove', (e) => {
                const email = $(e.currentTarget).data('email');
                this.recipients = this.recipients.filter(item => item !== email);
                this.renderRecipients();
                this.updateTotals();
            });

            $modal.on('submit', '#kss-gift-form', async (e) => {
                e.preventDefault();
                await this.submitGift();
            });
        },

        addRecipient: function() {
            const $input = $('#kss-gift-email');
            const email = ($input.val() || '').trim().toLowerCase();
            if (!email) {
                this.showGiftError('Please enter an email address.');
                return;
            }
            if (!this.isValidEmail(email)) {
                this.showGiftError('Please enter a valid email address.');
                return;
            }
            if (this.recipients.includes(email)) {
                this.showGiftError('That email is already in the list.');
                return;
            }
            this.recipients.push(email);
            $input.val('');
            this.renderRecipients();
            this.updateTotals();
        },

        renderRecipients: function() {
            const $list = $('.kss-gift-recipient-list');
            if (!this.recipients.length) {
                $list.html('<div class="kss-gift-empty">No recipients added yet.</div>');
                return;
            }

            const items = this.recipients.map(email => `
                <div class="kss-gift-recipient">
                    <span>${email}</span>
                    <button type="button" class="kss-gift-remove" data-email="${email}" aria-label="Remove ${email}">&times;</button>
                </div>
            `).join('');
            $list.html(items);
        },

        updateTotals: function() {
            const total = this.recipients.length * this.data.pricing.member_price;
            $('.kss-gift-total strong').text(`${this.data.pricing.currency}${total.toFixed(2)}`);

            if (!this.recipients.length) {
                this.disableSubmit();
                this.showGiftError('');
                return;
            }

            this.createPaymentIntent();
        },

        async createPaymentIntent() {
            if (!this.data) {
                return;
            }

            const count = this.recipients.length;
            if (!count) {
                return;
            }

            this.intentCount += 1;
            const intentRequestId = this.intentCount;
            this.showGiftError('');

            $.ajax({
                url: khm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kss_create_gift_intent',
                    post_id: this.data.post.id,
                    recipient_count: count,
                    nonce: khm_ajax.nonce
                },
                success: (response) => {
                    if (intentRequestId !== this.intentCount) {
                        return;
                    }
                    if (response.success && response.data && response.data.client_secret) {
                        this.clientSecret = response.data.client_secret;
                        this.setupStripeElements();
                    } else {
                        this.showGiftError(response.data?.error || 'Unable to prepare payment.');
                    }
                },
                error: () => {
                    if (intentRequestId !== this.intentCount) {
                        return;
                    }
                    this.showGiftError('Unable to prepare payment. Please refresh and try again.');
                }
            });
        },

        setupStripeElements: function() {
            if (!this.clientSecret) {
                return;
            }

            if (this.paymentElement) {
                this.paymentElement.unmount();
                this.paymentElement = null;
            }

            this.elements = this.stripe.elements({ clientSecret: this.clientSecret });
            this.paymentElement = this.elements.create('payment', {
                layout: 'tabs',
                paymentMethodOrder: ['card']
            });
            this.paymentElement.mount('#kss-gift-payment-element');

            this.enableSubmit();
        },

        disableSubmit: function(reason) {
            const $btn = $('#kss-gift-form .btn-send-gift');
            $btn.prop('disabled', true);
            if (reason) {
                this.showGiftError(reason);
            }
        },

        enableSubmit: function() {
            $('#kss-gift-form .btn-send-gift').prop('disabled', false);
        },

        showGiftError: function(message) {
            const $error = $('#kss-gift-errors');
            if (!$error.length) {
                return;
            }
            if (!message) {
                $error.text('').hide();
                return;
            }
            $error.text(message).show();
        },

        async submitGift() {
            if (!this.recipients.length) {
                this.showGiftError('Please add at least one recipient.');
                return;
            }

            const senderName = ($('#kss-gift-sender').val() || '').trim();
            if (!senderName) {
                this.showGiftError('Please enter the sender name.');
                return;
            }

            if (!this.clientSecret || !this.paymentElement) {
                this.showGiftError('Payment details are not ready yet.');
                return;
            }

            const $btn = $('#kss-gift-form .btn-send-gift');
            $btn.prop('disabled', true).text('Processing...');

            const { error, paymentIntent } = await this.stripe.confirmPayment({
                elements: this.elements,
                confirmParams: {
                    payment_method_data: {
                        billing_details: {
                            name: senderName
                        }
                    }
                },
                redirect: 'if_required'
            });

            if (error) {
                this.showGiftError(error.message || 'Payment failed. Please try again.');
                $btn.prop('disabled', false).text('Send Gift');
                return;
            }

            if (!paymentIntent || paymentIntent.status !== 'succeeded') {
                this.showGiftError('Payment could not be confirmed.');
                $btn.prop('disabled', false).text('Send Gift');
                return;
            }

            this.finalizeGift(paymentIntent.id, senderName, $btn);
        },

        finalizeGift: function(intentId, senderName, $btn) {
            $.ajax({
                url: khm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kss_finalize_gift',
                    post_id: this.data.post.id,
                    payment_intent_id: intentId,
                    recipients: this.recipients,
                    gift_message: $('#kss-gift-message').val(),
                    sender_name: senderName,
                    nonce: khm_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.closeModal();
                        this.showGiftSuccess(response.data || {});
                    } else {
                        this.showGiftError(response.data?.error || 'Failed to send gift.');
                        $btn.prop('disabled', false).text('Send Gift');
                    }
                },
                error: () => {
                    this.showGiftError('Network error. Please try again.');
                    $btn.prop('disabled', false).text('Send Gift');
                }
            });
        },

        closeModal: function() {
            $('#kss-gift-modal').fadeOut(function() {
                $(this).remove();
            });
        },

        showGiftSuccess: function(data) {
            $('#kss-gift-success').remove();

            const gifts = Array.isArray(data.gifts) ? data.gifts : [];
            const message = data.message || 'Gift sent successfully! The recipient will receive an email shortly.';

            const rows = gifts.map((gift) => `
                <tr>
                    <td>${gift.email || ''}</td>
                    <td><code>${gift.token || ''}</code></td>
                </tr>
            `).join('');

            const modalHtml = `
                <div id="kss-gift-success" class="kss-modal">
                    <div class="kss-modal-content kss-gift-confirm">
                        <div class="kss-modal-header">
                            <h2>Gift Sent</h2>
                            <span class="kss-modal-close">&times;</span>
                        </div>
                        <div class="kss-modal-body">
                            <p class="kss-gift-confirm-message">${message}</p>
                            <div class="kss-gift-confirm-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Recipient</th>
                                            <th>Voucher Code</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${rows || '<tr><td colspan="2">No recipients found.</td></tr>'}
                                    </tbody>
                                </table>
                            </div>
                            <div class="kss-gift-confirm-note" id="kss-gift-confirm-note"></div>
                            <div class="kss-modal-actions">
                                <button type="button" class="kss-copy-gift btn-send-gift">Copy Details</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            const $modal = $('#kss-gift-success');
            $modal.fadeIn();

            $modal.on('click', '.kss-modal-close', () => {
                $modal.fadeOut(function() {
                    $(this).remove();
                });
            });

            $modal.on('click', (e) => {
                if (e.target === $modal[0]) {
                    $modal.fadeOut(function() {
                        $(this).remove();
                    });
                }
            });

            $modal.on('click', '.kss-copy-gift', () => {
                const lines = gifts.map((gift) => `${gift.email || ''} - ${gift.token || ''}`);
                const text = `${message}\n\n${lines.join('\n')}`;
                this.copyToClipboard(text);
                $('#kss-gift-confirm-note').text('Gift details copied to clipboard.').addClass('is-visible');
            });
        },

        copyToClipboard: function(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
                return;
            }
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.top = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                // ignore
            }
            document.body.removeChild(textarea);
        },

        isValidEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
    };

    function handleGiftArticle($button) {
        GiftModal.init($button.data('post-id'));
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
        showSaveFlashNotification(message, type || 'info');
    }
    
    /**
     * Initialize on AJAX page loads (for SPA themes)
     */
    $(document).on('ajaxComplete', function() {
        initKHMIntegration();
    });
    
})(jQuery);
