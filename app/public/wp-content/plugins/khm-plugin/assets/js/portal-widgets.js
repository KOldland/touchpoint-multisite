/**
 * KHM Portal Widgets - JavaScript
 * Handles AJAX interactions for modular portal widgets
 */
(function($) {
    'use strict';

    // Wait for DOM
    $(document).ready(function() {
        initPortalWidgets();
    });

    function initPortalWidgets() {
        initDownloadButtons();
        initRedownloadButtons();
        initRemoveButtons();
        initAnswerCardRemoveButtons();
        initAnswerCardShareButtons();
        initMembershipActions();
        initAccountForms();
        initVoucherForms();
    }

    /**
     * First-time download buttons (costs credits)
     */
    function initDownloadButtons() {
        $(document).on('click', '.khm-download-btn', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var postId = $btn.data('post-id');
            var credits = $btn.data('credits');
            
            if (!postId) return;
            
            // Get current user credits for modal display
            var currentCredits = parseInt($('.khm-balance-value').text()) || 0;
            var postTitle = $btn.closest('.khm-download-item').find('.khm-download-title a').text();
            
            // Show confirmation modal
            showPortalDownloadModal({
                post_id: postId,
                post_title: postTitle,
                credits_required: credits,
                user_credits: currentCredits
            }, $btn);
        });
    }

    /**
     * Show download confirmation modal (echoes social strip UX)
     */
    function showPortalDownloadModal(data, $button) {
        // Remove existing modal
        $('#khm-portal-download-modal').remove();
        
        var remainingCredits = data.user_credits - data.credits_required;
        
        var modalHtml = `
            <div id="khm-portal-download-modal" class="khm-modal-backdrop">
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
                            <p class="tp-credit-remaining"><strong>After download:</strong> ${remainingCredits} credits remaining</p>
                        </div>
                        
                        <p class="tp-modal-notice">This PDF will be downloaded and saved to your library for future access.</p>
                        
                        <div class="tp-modal-actions">
                            <button class="btn-confirm-download tp-btn tp-btn-primary">Download PDF</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        $('body').append(modalHtml);
        
        var $modal = $('#khm-portal-download-modal');
        
        // Bind close events
        $modal.on('click', '.khm-modal-close', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
        });
        
        // Click outside to close
        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.removeClass('show');
                setTimeout(function() { $modal.remove(); }, 300);
            }
        });
        
        // Confirm download
        $modal.on('click', '.btn-confirm-download', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
            processPortalDownload(data.post_id, $button);
        });
        
        // Handle ESC key
        $(document).on('keydown.portalDownloadModal', function(e) {
            if (e.key === 'Escape') {
                $modal.removeClass('show');
                setTimeout(function() { $modal.remove(); }, 300);
                $(document).off('keydown.portalDownloadModal');
            }
        });
        
        // Show modal with animation
        requestAnimationFrame(function() {
            $modal.addClass('show');
        });
    }

    /**
     * Process confirmed download
     */
    function processPortalDownload(postId, $btn) {
        var credits = $btn.data('credits');
        
        var originalTitle = $btn.attr('title') || '';
        $btn.prop('disabled', true).attr('title', 'Processing...');
        
        $.ajax({
            url: khmPortalWidgets.restUrl + 'downloads/purchase',
            method: 'POST',
            headers: {
                'X-WP-Nonce': khmPortalWidgets.restNonce
            },
            data: {
                post_id: postId
            },
            success: function(response) {
                if (response.success && response.download_url) {
                    // Trigger download
                    var link = document.createElement('a');
                    link.href = response.download_url;
                    link.download = '';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Change button to "Download Again"
                    $btn.removeClass('khm-download-btn').addClass('khm-redownload-btn');
                    $btn.prop('disabled', false)
                        .html('<span class="khm-btn-icon dashicons dashicons-download"></span>')
                        .attr('title', 'Re-download PDF');
                    $btn.removeData('credits');
                    
                    // Update credits display if visible
                    if (response.remaining_credits !== undefined) {
                        $('.khm-balance-value').text(response.remaining_credits);
                    }
                } else {
                    alert(response.error || 'Download failed');
                    $btn.prop('disabled', false)
                        .html('<span class="khm-btn-icon dashicons dashicons-download"></span>')
                        .attr('title', 'Download (' + credits + ' credits)');
                }
            },
            error: function(xhr) {
                var error = 'Download failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    error = xhr.responseJSON.message;
                }
                alert(error);
                $btn.prop('disabled', false)
                    .html('<span class="khm-btn-icon dashicons dashicons-download"></span>')
                    .attr('title', originalTitle || ('Download (' + credits + ' credits)'));
            }
        });
    }

    /**
     * Re-download buttons
     */
    function initRedownloadButtons() {
        $(document).on('click', '.khm-redownload-btn', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var postId = $btn.data('post-id');
            
            if (!postId) return;
            
            handlePortalRedownload(postId, $btn);
        });
    }

    function handlePortalRedownload(postId, $btn) {
        if (!khmPortalWidgets || !khmPortalWidgets.downloadRestUrl) {
            return;
        }

        var originalTitle = $btn.attr('title') || 'Re-download PDF';
        $btn.prop('disabled', true).attr('title', 'Checking...');

        $.ajax({
            url: khmPortalWidgets.downloadRestUrl + 'check/' + postId,
            type: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khmPortalWidgets.restNonce);
            },
            success: function(response) {
                $btn.prop('disabled', false).attr('title', originalTitle);

                if (!response.success || !response.can_download) {
                    alert(response.message || 'Download unavailable');
                    return;
                }

                showPortalRedownloadModal(response, $btn, postId);
            },
            error: function() {
                $btn.prop('disabled', false).attr('title', originalTitle);
                alert('Download failed. Please try again.');
            }
        });
    }

    function showPortalRedownloadModal(data, $button, postId) {
        $('#khm-portal-download-modal').remove();

        var modalHtml = `
            <div id="khm-portal-download-modal" class="khm-modal-backdrop">
                <div class="khm-modal" style="min-width: 400px; max-width: 480px;">
                    <div class="khm-modal-header">
                        <h3 class="khm-modal-title">Ready to Re-download?</h3>
                        <button class="khm-modal-close">&times;</button>
                    </div>
                    <div class="khm-modal-content">
                        <div class="tp-modal-title-strip">
                            <h4>${data.post_title || 'Download PDF'}</h4>
                        </div>
                        <p>You have already downloaded this PDF, so you may re-download again without using any credits.</p>
                        <div class="tp-modal-actions">
                            <button class="btn-confirm-download tp-btn tp-btn-success">Download PDF</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        var $modal = $('#khm-portal-download-modal');
        requestAnimationFrame(function() {
            $modal.addClass('show');
        });

        $modal.on('click', '.khm-modal-close', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
        });

        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.removeClass('show');
                setTimeout(function() { $modal.remove(); }, 300);
            }
        });

        $modal.on('click', '.btn-confirm-download', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
            processPortalRedownload(postId, $button);
        });

        $(document).on('keydown.portalRedownloadModal', function(e) {
            if (e.key === 'Escape') {
                $modal.removeClass('show');
                setTimeout(function() { $modal.remove(); }, 300);
                $(document).off('keydown.portalRedownloadModal');
            }
        });
    }

    function processPortalRedownload(postId, $button) {
        if (!khmPortalWidgets || !khmPortalWidgets.downloadRestUrl) {
            return;
        }

        var originalTitle = $button.attr('title') || 'Re-download PDF';
        $button.prop('disabled', true).attr('title', 'Downloading...');

        $.ajax({
            url: khmPortalWidgets.downloadRestUrl + postId,
            type: 'POST',
            data: JSON.stringify({ confirm: true }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khmPortalWidgets.restNonce);
            },
            success: function(response) {
                if (response.download_url) {
                    window.location.href = response.download_url;
                    showToast('PDF ready for download!', 'success');
                } else {
                    alert(response.message || 'Download failed');
                }
            },
            error: function() {
                alert('Download failed. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).attr('title', originalTitle);
            }
        });
    }

    /**
     * Voucher redemption form
     */
    function initVoucherForms() {
        $(document).on('submit', '.khm-voucher-form', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $input = $form.find('.khm-voucher-code');
            var code = $input.val().trim();
            if (!code) {
                var emptyMessage = (window.khmPortalWidgets && khmPortalWidgets.strings && khmPortalWidgets.strings.error)
                    ? khmPortalWidgets.strings.error
                    : 'Please enter a voucher code.';
                setVoucherMessage($form, emptyMessage, 'error');
                return;
            }

            redeemVoucher($form, code);
        });
    }

    function redeemVoucher($form, code) {
        var $button = $form.find('button[type="submit"]');
        var originalLabel = $button.text();
        var loadingLabel = (window.khmPortalWidgets && khmPortalWidgets.strings && khmPortalWidgets.strings.loading)
            ? khmPortalWidgets.strings.loading
            : 'Loading...';
        $button.prop('disabled', true).text(loadingLabel);

        $.ajax({
            url: khmPortalWidgets.restUrl + 'gift/redeem',
            method: 'POST',
            headers: {
                'X-WP-Nonce': khmPortalWidgets.restNonce
            },
            data: {
                token: code
            },
            success: function(response) {
                if (response.success) {
                    setVoucherMessage($form, response.message || 'Voucher redeemed! Article added to your library.', 'success');
                    $form.find('.khm-voucher-code').val('');
                } else {
                    var failMessage = (window.khmPortalWidgets && khmPortalWidgets.strings && khmPortalWidgets.strings.error)
                        ? khmPortalWidgets.strings.error
                        : 'Unable to redeem voucher.';
                    setVoucherMessage($form, response.error || failMessage, 'error');
                }
            },
            error: function(xhr) {
                var errorMessage = (window.khmPortalWidgets && khmPortalWidgets.strings && khmPortalWidgets.strings.error)
                    ? khmPortalWidgets.strings.error
                    : 'Unable to redeem voucher.';
                var message = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : errorMessage;
                setVoucherMessage($form, message, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalLabel);
            }
        });
    }

    function setVoucherMessage($form, message, status) {
        var $message = $form.find('.khm-form-message');
        if (!$message.length) {
            return;
        }
        $message
            .removeClass('success error')
            .addClass(status)
            .text(message);
    }

    /**
     * Membership action buttons (pause/resume/cancel)
     */
    function initMembershipActions() {
        $(document).on('click', '.khm-pause-btn, .khm-resume-btn, .khm-cancel-btn', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var action = $btn.data('action');
            var strings = khmPortalWidgets.strings || {};
            
            // Confirm action
            var confirmMsg = '';
            if (action === 'pause') {
                confirmMsg = strings.confirm_pause || 'Are you sure you want to pause your membership?';
            } else if (action === 'cancel') {
                confirmMsg = strings.confirm_cancel || 'Are you sure you want to cancel?';
            }
            
            if (confirmMsg && !confirm(confirmMsg)) {
                return;
            }
            
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: khmPortalWidgets.restUrl + 'membership/' + action,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': khmPortalWidgets.restNonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload to reflect changes
                        location.reload();
                    } else {
                        alert(response.error || 'Action failed');
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert('Action failed. Please try again.');
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * Account forms (profile, password, email prefs)
     */
    function initAccountForms() {
        // Profile form
        $(document).on('submit', '.khm-profile-form', function(e) {
            e.preventDefault();
            submitAccountForm($(this), 'profile');
        });

        // Password form
        $(document).on('submit', '.khm-password-form', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var newPass = $form.find('[name="new_password"]').val();
            var confirmPass = $form.find('[name="confirm_password"]').val();
            var strings = khmPortalWidgets.strings || {};
            
            if (newPass !== confirmPass) {
                showFormMessage($form, strings.passwords_mismatch || 'Passwords do not match.', 'error');
                return;
            }
            
            submitAccountForm($form, 'password');
        });

        // Email preferences form
        $(document).on('submit', '.khm-email-prefs-form', function(e) {
            e.preventDefault();
            submitAccountForm($(this), 'email-preferences');
        });
    }

    function submitAccountForm($form, endpoint) {
        var $btn = $form.find('.khm-save-btn');
        var $msg = $form.find('.khm-form-message');
        var strings = khmPortalWidgets.strings || {};
        
        $btn.prop('disabled', true).text(strings.saving || 'Saving...');
        $msg.removeClass('success error').text('');
        
        var formData = {};
        $form.serializeArray().forEach(function(item) {
            formData[item.name] = item.value;
        });
        
        $.ajax({
            url: khmPortalWidgets.restUrl + 'account/' + endpoint,
            method: 'POST',
            headers: {
                'X-WP-Nonce': khmPortalWidgets.restNonce
            },
            data: formData,
            success: function(response) {
                if (response.success) {
                    showFormMessage($form, strings.saved || 'Saved!', 'success');
                    
                    // Clear password fields
                    if (endpoint === 'password') {
                        $form.find('input[type="password"]').val('');
                    }
                } else {
                    showFormMessage($form, response.error || strings.error || 'An error occurred.', 'error');
                }
                
                $btn.prop('disabled', false).text(getButtonLabel(endpoint));
            },
            error: function(xhr) {
                var error = strings.error || 'An error occurred.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    error = xhr.responseJSON.message;
                }
                showFormMessage($form, error, 'error');
                $btn.prop('disabled', false).text(getButtonLabel(endpoint));
            }
        });
    }

    function showFormMessage($form, message, type) {
        var $msg = $form.find('.khm-form-message');
        $msg.removeClass('success error').addClass(type).text(message);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $msg.fadeOut(function() {
                    $(this).text('').show();
                });
            }, 3000);
        }
    }

    function getButtonLabel(endpoint) {
        switch (endpoint) {
            case 'profile':
                return 'Save Changes';
            case 'password':
                return 'Update Password';
            case 'email-preferences':
                return 'Save Preferences';
            default:
                return 'Save';
        }
    }

    /**
     * Remove from library buttons
     */
    function initRemoveButtons() {
        $(document).on('click', '.khm-remove-btn', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var postId = $btn.data('post-id');
            var postTitle = $btn.data('title');
            
            if (!postId) return;
            
            // Show confirmation modal
            showRemoveConfirmationModal({
                post_id: postId,
                post_title: postTitle
            }, $btn);
        });
    }

    /**
     * Remove saved AnswerCard buttons
     */
    function initAnswerCardRemoveButtons() {
        $(document).on('click', '.khm-answercard-remove-btn', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var postId = $btn.data('post-id');
            var answerCardId = $btn.data('answer-card-id');
            var title = $btn.data('title');

            if (!postId || !answerCardId) return;

            showAnswerCardRemoveModal({
                post_id: postId,
                answer_card_id: answerCardId,
                title: title
            }, $btn);
        });
    }

    /**
     * Show remove from library confirmation modal
     */
    function showRemoveConfirmationModal(data, $button) {
        // Remove existing modal
        $('#khm-portal-remove-modal').remove();
        
        var modalHtml = `
            <div id="khm-portal-remove-modal" class="khm-modal-backdrop">
                <div class="khm-modal" style="min-width: 400px; max-width: 480px;">
                    <div class="khm-modal-header">
                        <h3 class="khm-modal-title">Remove from Library</h3>
                        <button class="khm-modal-close">&times;</button>
                    </div>
                    <div class="khm-modal-content">
                        <div class="tp-modal-title-strip">
                            <h4>${data.post_title}</h4>
                        </div>
                        
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
        
        var $modal = $('#khm-portal-remove-modal');
        
        // Bind close events
        $modal.on('click', '.khm-modal-close', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
        });
        
        // Click outside to close
        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.removeClass('show');
                setTimeout(function() { $modal.remove(); }, 300);
            }
        });
        
        // Confirm remove
        $modal.on('click', '.btn-confirm-remove', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
            processRemoveFromLibrary(data.post_id, $button);
        });
        
        // Handle ESC key
        $(document).on('keydown.portalRemoveModal', function(e) {
            if (e.key === 'Escape') {
                $modal.removeClass('show');
                setTimeout(function() { $modal.remove(); }, 300);
                $(document).off('keydown.portalRemoveModal');
            }
        });
        
        // Show modal with animation
        requestAnimationFrame(function() {
            $modal.addClass('show');
        });
    }

    /**
     * Process remove from library
     */
    function processRemoveFromLibrary(postId, $btn) {
        var $item = $btn.closest('.khm-download-item');
        
        $btn.prop('disabled', true).text('Removing...');
        
        $.ajax({
            url: khmPortalWidgets.restUrl + 'library/remove',
            method: 'POST',
            headers: {
                'X-WP-Nonce': khmPortalWidgets.restNonce
            },
            data: {
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    // Show success notification
                    showPortalNotification('Article removed from library', 'success');
                    
                    // Animate item removal
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if library is now empty
                        if ($('.khm-downloads-list .khm-download-item').length === 0) {
                            $('.khm-downloads-list').replaceWith(`
                                <div class="khm-empty-state">
                                    <span class="khm-empty-icon dashicons dashicons-download"></span>
                                    <p>No saved articles yet. Browse and save articles to your library.</p>
                                    <a href="/" class="khm-browse-btn">Browse Articles</a>
                                </div>
                            `);
                        }
                    });
                } else {
                    showPortalNotification(response.error || 'Failed to remove', 'error');
                    $btn.prop('disabled', false).html('<span class="khm-btn-icon dashicons dashicons-trash"></span> Remove');
                }
            },
            error: function() {
                showPortalNotification('Failed to remove. Please try again.', 'error');
                $btn.prop('disabled', false).html('<span class="khm-btn-icon dashicons dashicons-trash"></span> Remove');
            }
        });
    }

    /**
     * Section summary remove modal
     */
    function showAnswerCardRemoveModal(data, $button) {
        $('#khm-portal-answercard-remove-modal').remove();

        var modalHtml = `
            <div id="khm-portal-answercard-remove-modal" class="khm-modal-backdrop">
                <div class="khm-modal" style="min-width: 400px; max-width: 480px;">
                    <div class="khm-modal-header">
                        <h3 class="khm-modal-title">Remove section summary</h3>
                        <button class="khm-modal-close">&times;</button>
                    </div>
                    <div class="khm-modal-content">
                        <div class="tp-modal-title-strip">
                            <h4>${data.title || 'Section Summary'}</h4>
                        </div>
                        <div class="tp-modal-warning">
                            <p>Are you sure you want to remove this section summary from your saved list?</p>
                            <p class="tp-warning-note">You can always save it again later.</p>
                        </div>
                        <div class="tp-modal-actions">
                            <button class="btn-confirm-remove tp-btn tp-btn-danger">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);
        var $modal = $('#khm-portal-answercard-remove-modal');

        $modal.on('click', '.khm-modal-close', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
        });

        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.removeClass('show');
                setTimeout(function() { $modal.remove(); }, 300);
            }
        });

        $modal.on('click', '.btn-confirm-remove', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
            processRemoveAnswerCard(data.answer_card_id, $button);
        });

        $(document).on('keydown.portalAnswerCardRemoveModal', function(e) {
            if (e.key === 'Escape') {
                $modal.removeClass('show');
                setTimeout(function() { $modal.remove(); }, 300);
                $(document).off('keydown.portalAnswerCardRemoveModal');
            }
        });

        requestAnimationFrame(function() {
            $modal.addClass('show');
        });
    }

    function processRemoveAnswerCard(answerCardId, $btn) {
        var $item = $btn.closest('.khm-download-item');

        $btn.prop('disabled', true);

        $.ajax({
            url: khmPortalWidgets.restUrl + 'answercards/remove',
            method: 'POST',
            headers: {
                'X-WP-Nonce': khmPortalWidgets.restNonce
            },
            data: {
                answer_card_id: answerCardId
            },
            success: function(response) {
                if (response.success) {
                    showPortalNotification('Section summary removed', 'success');
                    $item.fadeOut(300, function() { $(this).remove(); });
                } else {
                    showPortalNotification(response.error || 'Failed to remove', 'error');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                showPortalNotification('Failed to remove. Please try again.', 'error');
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Share section summary buttons
     */
    function initAnswerCardShareButtons() {
        $(document).on('click', '.khm-answercard-share-btn', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var postId = $btn.data('post-id');
            var title = $btn.data('title') || 'Section Summary';

            if (!postId) return;

            showAnswerCardShareModal({
                post_id: postId,
                title: title
            });
        });
    }

    function showAnswerCardShareModal(data) {
        $('#khm-portal-answercard-share-modal').remove();

        var modalHtml = `
            <div id="khm-portal-answercard-share-modal" class="khm-modal-backdrop">
                <div class="khm-modal khm-portal-share-modal">
                    <div class="khm-modal-header">
                        <h3 class="khm-modal-title">Share section summary</h3>
                        <button class="khm-modal-close">&times;</button>
                    </div>
                    <div class="modal-content">
                        <div class="tp-modal-title-strip">
                            <h4>${data.title}</h4>
                        </div>
                        <form class="khm-answercard-share-form">
                            <label>
                                Recipient Email:
                                <span class="khm-input-row">
                                    <input type="email" name="recipient_email" required placeholder="friend@example.com" />
                                    <button type="button" class="khm-contact-btn" aria-label="Open address book" title="Open address book">
                                        <span class="dashicons dashicons-admin-users"></span>
                                    </button>
                                </span>
                            </label>
                            <label>
                                Personal Message (Optional):
                                <textarea name="personal_message" placeholder="I thought you'd find this section summary useful..."></textarea>
                            </label>
                            <div class="modal-actions">
                                <button type="button" class="btn-cancel">Cancel</button>
                                <button type="submit" class="btn-send">Send Email</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);
        var $modal = $('#khm-portal-answercard-share-modal');

        $modal.on('click', '.khm-modal-close', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
        });

        $modal.on('click', '.btn-cancel', function() {
            $modal.removeClass('show');
            setTimeout(function() { $modal.remove(); }, 300);
        });

        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.removeClass('show');
                setTimeout(function() { $modal.remove(); }, 300);
            }
        });

        $modal.on('submit', '.khm-answercard-share-form', function(e) {
            e.preventDefault();
            var $form = $(this);
            var recipient = $form.find('input[name="recipient_email"]').val();
            var message = $form.find('textarea[name="personal_message"]').val();
            var $submit = $form.find('button[type="submit"]');

            $submit.prop('disabled', true).text('Sending...');

            $.ajax({
                url: khmPortalWidgets.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'khm_share_library_article',
                    nonce: khmPortalWidgets.shareNonce,
                    post_id: data.post_id,
                    recipient_email: recipient,
                    personal_message: message,
                    include_notes: 'false',
                    include_membership_info: 'false'
                },
                success: function(response) {
                    if (response.success) {
                        showPortalNotification('Section summary shared', 'success');
                        $modal.removeClass('show');
                        setTimeout(function() { $modal.remove(); }, 300);
                    } else {
                        showPortalNotification(response.data || 'Share failed', 'error');
                        $submit.prop('disabled', false).text('Send Email');
                    }
                },
                error: function() {
                    showPortalNotification('Share failed. Please try again.', 'error');
                    $submit.prop('disabled', false).text('Send Email');
                }
            });
        });

        requestAnimationFrame(function() {
            $modal.addClass('show');
        });
    }

    /**
     * Show subtle notification toast
     */
    function showPortalNotification(message, type) {
        // Remove existing notification
        $('.khm-portal-notification').remove();
        
        var iconClass = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
        
        var notificationHtml = `
            <div class="khm-portal-notification khm-notification-${type}">
                <span class="dashicons ${iconClass}"></span>
                <span class="khm-notification-text">${message}</span>
            </div>
        `;
        
        $('body').append(notificationHtml);
        
        var $notification = $('.khm-portal-notification');
        
        // Animate in
        requestAnimationFrame(function() {
            $notification.addClass('show');
        });
        
        // Auto-hide after 3 seconds
        setTimeout(function() {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 3000);
    }

})(jQuery);
