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
        initMembershipActions();
        initAccountForms();
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
                            <button class="btn-cancel tp-btn tp-btn-cancel">Cancel</button>
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
        $modal.on('click', '.khm-modal-close, .btn-cancel', function() {
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
        
        $btn.prop('disabled', true).text('Processing...');
        
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
                    $btn.prop('disabled', false).html('<span class="khm-btn-icon">📥</span> Download Again');
                    $btn.removeData('credits');
                    
                    // Update credits display if visible
                    if (response.remaining_credits !== undefined) {
                        $('.khm-balance-value').text(response.remaining_credits);
                    }
                } else {
                    alert(response.error || 'Download failed');
                    $btn.prop('disabled', false).html('<span class="khm-btn-icon dashicons dashicons-download"></span> Download (' + credits + ' Credits)');
                }
            },
            error: function(xhr) {
                var error = 'Download failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    error = xhr.responseJSON.message;
                }
                alert(error);
                $btn.prop('disabled', false).html('<span class="khm-btn-icon dashicons dashicons-download"></span> Download (' + credits + ' Credits)');
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
            
            $btn.prop('disabled', true).text('Downloading...');
            
            $.ajax({
                url: khmPortalWidgets.restUrl + 'downloads/redownload',
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
                        
                        $btn.prop('disabled', false).html('<span class="khm-btn-icon dashicons dashicons-download"></span> Download Again');
                    } else {
                        alert(response.error || 'Download failed');
                        $btn.prop('disabled', false).html('<span class="khm-btn-icon dashicons dashicons-download"></span> Download Again');
                    }
                },
                error: function() {
                    alert('Download failed. Please try again.');
                    $btn.prop('disabled', false).html('<span class="khm-btn-icon dashicons dashicons-download"></span> Download Again');
                }
            });
        });
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
                            <button class="btn-cancel tp-btn tp-btn-cancel">Cancel</button>
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
        $modal.on('click', '.khm-modal-close, .btn-cancel', function() {
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
