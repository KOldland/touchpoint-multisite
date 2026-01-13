/**
 * Member Portal JavaScript
 * 
 * Handles AJAX interactions for the member portal:
 * - Library loading and management
 * - Downloads list and re-download
 * - Credit top-up
 * - Membership pause/resume/cancel
 * - Profile and password updates
 */

(function($) {
    'use strict';

    // Portal state
    const state = {
        libraryPage: 1,
        libraryLoading: false,
        libraryHasMore: true,
        downloadsPage: 1,
        downloadsLoading: false,
        downloadsHasMore: true,
    };

    /**
     * Initialize portal
     */
    function init() {
        if (!$('.khm-portal').length) return;

        // Load dynamic content based on current tab
        loadCurrentTabContent();

        // Bind events
        bindLibraryEvents();
        bindDownloadsEvents();
        bindCreditsEvents();
        bindMembershipEvents();
        bindAccountEvents();
        bindVoucherEvents();
    }

    /**
     * Load content for current tab
     */
    function loadCurrentTabContent() {
        const $portal = $('.khm-portal');
        
        // Check which tab is active
        if ($('.khm-portal-library').length) {
            loadLibraryItems();
        }
        
        if ($('.khm-portal-downloads').length) {
            loadDownloads();
        }
    }

    /**
     * ================================
     * LIBRARY FUNCTIONALITY
     * ================================
     */
    
    function bindLibraryEvents() {
        // Search
        let searchTimeout;
        $('#library-search').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                state.libraryPage = 1;
                state.libraryHasMore = true;
                loadLibraryItems(true);
            }, 300);
        });

        // Load more
        $('#library-load-more button').on('click', function() {
            loadLibraryItems();
        });

        // Remove from library
        $(document).on('click', '.khm-library-remove', function() {
            if (confirm(khmPortal.strings.confirm_remove)) {
                const $item = $(this).closest('.khm-library-item');
                const postId = $(this).data('post-id');
                removeFromLibrary(postId, $item);
            }
        });
    }

    function loadLibraryItems(reset = false) {
        if (state.libraryLoading || (!state.libraryHasMore && !reset)) return;
        
        state.libraryLoading = true;
        const $container = $('#library-items');
        const search = $('#library-search').val() || '';

        if (reset) {
            $container.html('<div class="khm-loading">' + khmPortal.strings.loading + '</div>');
        }

        $.ajax({
            url: khmPortal.restUrl + 'library',
            method: 'GET',
            headers: { 'X-WP-Nonce': khmPortal.restNonce },
            data: {
                page: state.libraryPage,
                per_page: 12,
                search: search
            },
            success: function(response) {
                if (reset) {
                    $container.empty();
                }

                if (response.items && response.items.length > 0) {
                    response.items.forEach(function(item) {
                        $container.append(renderLibraryItem(item));
                    });

                    state.libraryPage++;
                    state.libraryHasMore = response.items.length >= 12;
                    $('#library-load-more').toggle(state.libraryHasMore);
                } else if (reset) {
                    $container.html('<p class="khm-empty-state">No items in your library yet. Save articles to build your collection!</p>');
                    $('#library-load-more').hide();
                }
            },
            error: function() {
                showToast(khmPortal.strings.error, 'error');
            },
            complete: function() {
                state.libraryLoading = false;
            }
        });
    }

    function renderLibraryItem(item) {
        const thumbnail = item.thumbnail 
            ? '<img src="' + item.thumbnail + '" alt="">' 
            : '<span class="dashicons dashicons-media-document"></span>';
        
        const savedDate = item.saved_at 
            ? new Date(item.saved_at).toLocaleDateString() 
            : '';
        const purchasedBadge = item.is_purchased
            ? '<span class="khm-library-purchased-badge" title="Purchased">$</span>'
            : '';
        const removeButton = item.is_purchased
            ? ''
            : `
                <button class="khm-btn khm-btn-sm khm-btn-secondary khm-library-remove" data-post-id="${item.post_id}" title="Remove">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            `;

        return `
            <div class="khm-library-item" data-post-id="${item.post_id}">
                <div class="khm-library-thumb">${thumbnail}</div>
                <div class="khm-library-content">
                    <h3 class="khm-library-title">
                        <a href="${item.url}">${escapeHtml(item.title)}</a>
                    </h3>
                    <div class="khm-library-meta">
                        <span class="khm-library-date">${savedDate}</span>
                        <div class="khm-library-actions">
                            ${item.has_downloaded ? `
                                <button class="khm-btn khm-btn-sm khm-btn-secondary khm-redownload-btn" data-post-id="${item.post_id}" title="Re-download PDF">
                                    <span class="dashicons dashicons-download"></span>
                                </button>
                            ` : ''}
                            ${purchasedBadge}
                            ${removeButton}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function removeFromLibrary(postId, $item) {
        $.ajax({
            url: khmPortal.restUrl + 'library/' + postId,
            method: 'DELETE',
            headers: { 'X-WP-Nonce': khmPortal.restNonce },
            success: function() {
                $item.fadeOut(300, function() {
                    $(this).remove();
                });
                showToast('Removed from library', 'success');
            },
            error: function() {
                showToast(khmPortal.strings.error, 'error');
            }
        });
    }

    /**
     * ================================
     * DOWNLOADS FUNCTIONALITY
     * ================================
     */

    function bindDownloadsEvents() {
        // Re-download button
        $(document).on('click', '.khm-redownload-btn', function() {
            const postId = $(this).data('post-id');
            handlePortalRedownload(postId, $(this));
        });
    }

    /**
     * ================================
     * VOUCHER REDEMPTION
     * ================================
     */

    function bindVoucherEvents() {
        $(document).on('submit', '.khm-voucher-form', function(e) {
            e.preventDefault();
            const code = $(this).find('.khm-voucher-code').val().trim();
            if (!code) {
                showToast(khmPortal.strings.error, 'error');
                return;
            }
            redeemVoucher(code, $(this));
        });
    }

    function redeemVoucher(code, $form) {
        const $button = $form.find('button[type="submit"]');
        $button.prop('disabled', true).text(khmPortal.strings.loading);

        $.ajax({
            url: khmPortal.restUrl + 'gift/redeem',
            method: 'POST',
            headers: { 'X-WP-Nonce': khmPortal.restNonce },
            data: { token: code },
            success: function(response) {
                if (response.success) {
                    showToast(response.message || 'Voucher redeemed! Article added to your library.', 'success');
                    $form.find('.khm-voucher-code').val('');
                    loadDownloads();
                    if (window.kssRefreshStripStatus) {
                        window.kssRefreshStripStatus();
                    }
                } else {
                    showToast(response.error || khmPortal.strings.error, 'error');
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : khmPortal.strings.error;
                showToast(msg, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Redeem Voucher');
            }
        });
    }

    function loadDownloads() {
        if (state.downloadsLoading) return;
        
        state.downloadsLoading = true;
        const $container = $('#downloads-list');

        $.ajax({
            url: khmPortal.restUrl + 'downloads',
            method: 'GET',
            headers: { 'X-WP-Nonce': khmPortal.restNonce },
            success: function(response) {
                $container.empty();

                if (response.items && response.items.length > 0) {
                    response.items.forEach(function(item) {
                        $container.append(renderDownloadItem(item));
                    });
                } else {
                    $container.html('<p class="khm-empty-state">No downloads yet. Download a PDF to get started!</p>');
                }
            },
            error: function() {
                showToast(khmPortal.strings.error, 'error');
            },
            complete: function() {
                state.downloadsLoading = false;
            }
        });
    }

    function renderDownloadItem(item) {
        const downloadDate = item.last_download_at 
            ? new Date(item.last_download_at).toLocaleDateString() 
            : '';

        return `
            <div class="khm-download-item" data-post-id="${item.post_id}">
                <div class="khm-recent-thumb">
                    ${item.thumbnail 
                        ? '<img src="' + item.thumbnail + '" alt="">' 
                        : '<span class="dashicons dashicons-media-document"></span>'
                    }
                </div>
                <div class="khm-download-info">
                    <div class="khm-download-title">${escapeHtml(item.title)}</div>
                    <div class="khm-download-meta">
                        Downloaded ${downloadDate} • ${item.download_count || 1} ${item.download_count === 1 ? 'time' : 'times'}
                    </div>
                </div>
                <a href="${item.url}" class="khm-btn khm-btn-sm khm-btn-secondary" target="_blank" title="View article" aria-label="View article">
                    <span class="dashicons dashicons-external"></span>
                </a>
                <button class="khm-btn khm-btn-sm khm-btn-primary khm-redownload-btn" data-post-id="${item.post_id}" title="Re-download PDF" aria-label="Re-download PDF">
                    <span class="dashicons dashicons-download"></span>
                </button>
            </div>
        `;
    }

    function handlePortalRedownload(postId, $btn) {
        if (!khmPortal || !khmPortal.downloadRestUrl) {
            return;
        }

        const originalTitle = $btn.attr('title') || 'Re-download PDF';
        $btn.prop('disabled', true).attr('title', 'Checking...');

        $.ajax({
            url: khmPortal.downloadRestUrl + 'check/' + postId,
            type: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khmPortal.restNonce);
            },
            success: function(response) {
                $btn.prop('disabled', false).attr('title', originalTitle);

                if (!response.success || !response.can_download) {
                    showToast(response.message || khmPortal.strings.error, 'error');
                    return;
                }

                showPortalRedownloadModal(response, $btn, postId);
            },
            error: function(xhr) {
                $btn.prop('disabled', false).attr('title', originalTitle);
                const message = xhr.responseJSON?.message || khmPortal.strings.error;
                showToast(message, 'error');
            }
        });
    }

    function showPortalRedownloadModal(data, $button, postId) {
        $('#khm-portal-download-modal').remove();

        const modalHtml = `
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
        const $modal = $('#khm-portal-download-modal');
        requestAnimationFrame(() => $modal.addClass('show'));

        $modal.on('click', '.khm-modal-close', function() {
            $modal.removeClass('show');
            setTimeout(() => $modal.remove(), 300);
        });

        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.removeClass('show');
                setTimeout(() => $modal.remove(), 300);
            }
        });

        $modal.on('click', '.btn-confirm-download', function() {
            $modal.removeClass('show');
            setTimeout(() => $modal.remove(), 300);
            processPortalRedownload(postId, $button);
        });

        $(document).on('keydown.portalRedownloadModal', function(e) {
            if (e.key === 'Escape') {
                $modal.removeClass('show');
                setTimeout(() => $modal.remove(), 300);
                $(document).off('keydown.portalRedownloadModal');
            }
        });
    }

    function processPortalRedownload(postId, $button) {
        const originalTitle = $button.attr('title') || 'Re-download PDF';
        $button.prop('disabled', true).attr('title', 'Downloading...');

        $.ajax({
            url: khmPortal.downloadRestUrl + postId,
            type: 'POST',
            data: JSON.stringify({ confirm: true }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khmPortal.restNonce);
            },
            success: function(response) {
                if (response.download_url) {
                    window.location.href = response.download_url;
                    showToast('PDF ready for download!', 'success');
                } else {
                    showToast(response.message || 'PDF generated', 'success');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || khmPortal.strings.error;
                showToast(message, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).attr('title', originalTitle);
            }
        });
    }

    /**
     * ================================
     * CREDITS FUNCTIONALITY
     * ================================
     */

    function bindCreditsEvents() {
        $('#topup-credits-btn').on('click', function() {
            openTopUpModal();
        });
    }

    function openTopUpModal() {
        // For MVP, redirect to credits purchase page
        // TODO: Implement modal with Stripe checkout
        window.location.href = '/purchase-credits/';
    }

    function updateCreditBalance() {
        $.ajax({
            url: khmPortal.restUrl + 'credits',
            method: 'GET',
            headers: { 'X-WP-Nonce': khmPortal.restNonce },
            success: function(response) {
                $('#credit-balance').text(response.balance);
            }
        });
    }

    /**
     * ================================
     * MEMBERSHIP FUNCTIONALITY
     * ================================
     */

    function bindMembershipEvents() {
        $('#pause-membership-btn').on('click', function() {
            if (confirm(khmPortal.strings.confirm_pause)) {
                pauseMembership($(this));
            }
        });

        $('#resume-membership-btn').on('click', function() {
            resumeMembership($(this));
        });

        $('#cancel-membership-btn').on('click', function() {
            if (confirm(khmPortal.strings.confirm_cancel)) {
                cancelMembership($(this));
            }
        });
    }

    function pauseMembership($btn) {
        membershipAction('pause', $btn);
    }

    function resumeMembership($btn) {
        membershipAction('resume', $btn);
    }

    function cancelMembership($btn) {
        membershipAction('cancel', $btn);
    }

    function membershipAction(action, $btn) {
        $btn.prop('disabled', true).addClass('loading');

        $.ajax({
            url: khmPortal.restUrl + 'membership/' + action,
            method: 'POST',
            headers: { 'X-WP-Nonce': khmPortal.restNonce },
            success: function(response) {
                showToast(response.message || 'Membership updated', 'success');
                // Reload page to show updated state
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || khmPortal.strings.error;
                showToast(message, 'error');
                $btn.prop('disabled', false).removeClass('loading');
            }
        });
    }

    /**
     * ================================
     * ACCOUNT FUNCTIONALITY
     * ================================
     */

    function bindAccountEvents() {
        $('#profile-form').on('submit', function(e) {
            e.preventDefault();
            updateProfile($(this));
        });

        $('#password-form').on('submit', function(e) {
            e.preventDefault();
            updatePassword($(this));
        });
    }

    function updateProfile($form) {
        const $btn = $form.find('button[type="submit"]');
        const data = {
            display_name: $form.find('#display_name').val(),
            email: $form.find('#user_email').val()
        };

        $btn.prop('disabled', true);

        // Use WordPress REST API for profile updates
        $.ajax({
            url: '/wp-json/wp/v2/users/me',
            method: 'POST',
            headers: { 'X-WP-Nonce': khmPortal.restNonce },
            data: {
                name: data.display_name,
                email: data.email
            },
            success: function() {
                showToast(khmPortal.strings.saved, 'success');
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || khmPortal.strings.error;
                showToast(message, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

    function updatePassword($form) {
        const $btn = $form.find('button[type="submit"]');
        const newPassword = $form.find('#new_password').val();
        const confirmPassword = $form.find('#confirm_password').val();

        if (newPassword !== confirmPassword) {
            showToast('Passwords do not match', 'error');
            return;
        }

        if (newPassword.length < 8) {
            showToast('Password must be at least 8 characters', 'error');
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: '/wp-json/wp/v2/users/me',
            method: 'POST',
            headers: { 'X-WP-Nonce': khmPortal.restNonce },
            data: {
                password: newPassword
            },
            success: function() {
                showToast('Password updated successfully', 'success');
                $form[0].reset();
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || khmPortal.strings.error;
                showToast(message, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * ================================
     * UTILITY FUNCTIONS
     * ================================
     */

    function showToast(message, type = 'info') {
        const $toast = $('#khm-portal-toast');
        $toast
            .text(message)
            .removeClass('success error')
            .addClass(type)
            .addClass('show');

        setTimeout(function() {
            $toast.removeClass('show');
        }, 3000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);

// Add spin animation
const style = document.createElement('style');
style.textContent = `
    @keyframes khm-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .khm-spin {
        animation: khm-spin 1s linear infinite;
    }
`;
document.head.appendChild(style);
