/**
 * KHM Library Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Library state
    let currentOffset = 0;
    let currentCategory = 'all';
    let currentSearch = '';
    let currentSort = 'created_at_desc';
    let currentStatus = 'all';
    let isLoading = false;
    let hasMore = true;

    // Initialize
    init();

    function init() {
        bindEvents();
        loadLibraryItems(true); // Initial load
    }

    function bindEvents() {
        // Category selection
        $(document).on('click', '.category-item', handleCategoryClick);
        
        // Search
        $('#library-search').on('input', debounce(handleSearch, 300));
        
        // Sort and filter changes
        $('#sort-by').on('change', handleSortChange);
        $('#filter-status').on('change', handleStatusChange);
        
        // View toggle
        $('.view-toggle').on('click', handleViewToggle);
        
        // Load more
        $('.btn-load-more').on('click', handleLoadMore);
        
        // Item actions
        $(document).on('click', '.btn-edit', handleEditItem);
        $(document).on('click', '.btn-download', handleDownloadItem);
        $(document).on('click', '.btn-share', handleEmailShare);
        $(document).on('click', '.btn-share-link', handleLinkShare);
        
        // Modal actions
        $('#add-new-category').on('click', showAddCategoryModal);
        $('.btn-cancel').on('click', hideModals);
        $('#category-form').on('submit', handleCreateCategory);
        $('#item-form').on('submit', handleUpdateItem);
        $('#share-form').on('submit', handleSendEmailShare);
        $('.btn-delete').on('click', handleDeleteItem);
        
        // Close modal on overlay click
        $('.khm-modal').on('click', function(e) {
            if (e.target === this) {
                hideModals();
            }
        });
    }

    function handleCategoryClick(e) {
        e.preventDefault();
        
        const $item = $(this);
        const categoryId = $item.data('category-id');
        
        // Update active state
        $('.category-item').removeClass('active');
        $item.addClass('active');
        
        // Reset and load
        currentCategory = categoryId;
        currentOffset = 0;
        hasMore = true;
        loadLibraryItems(true);
    }

    function handleSearch(e) {
        currentSearch = $(e.target).val().trim();
        currentOffset = 0;
        hasMore = true;
        loadLibraryItems(true);
    }

    function handleSortChange(e) {
        currentSort = $(e.target).val();
        currentOffset = 0;
        hasMore = true;
        loadLibraryItems(true);
    }

    function handleStatusChange(e) {
        currentStatus = $(e.target).val();
        currentOffset = 0;
        hasMore = true;
        loadLibraryItems(true);
    }

    function handleViewToggle(e) {
        e.preventDefault();
        
        const $button = $(this);
        const view = $button.data('view');
        
        // Update active state
        $('.view-toggle').removeClass('active');
        $button.addClass('active');
        
        // Update container class
        const $container = $('.khm-library-items');
        $container.removeClass('grid-view list-view').addClass(view + '-view');
    }

    function handleLoadMore(e) {
        e.preventDefault();
        
        if (!isLoading && hasMore) {
            loadLibraryItems(false);
        }
    }

    function loadLibraryItems(reset = false) {
        if (isLoading) return;
        
        isLoading = true;
        
        if (reset) {
            currentOffset = 0;
            $('.khm-library-items').html('<div class="loading-spinner"><span class="dashicons dashicons-update-alt"></span> Loading your library...</div>');
            $('.load-more-container').hide();
        } else {
            $('.btn-load-more').text('Loading...');
        }

        const data = {
            action: 'khm_load_library_items',
            nonce: khmLibrary.nonce,
            category_id: currentCategory,
            search: currentSearch,
            sort: currentSort,
            status: currentStatus === 'all' ? '' : currentStatus,
            per_page: $('.khm-library-items').data('per-page') || 12,
            offset: currentOffset
        };

        $.post(khmLibrary.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    if (reset) {
                        $('.khm-library-items').html(response.data.html);
                    } else {
                        $('.khm-library-items').append(response.data.html);
                    }
                    
                    currentOffset += data.per_page;
                    hasMore = response.data.has_more;
                    
                    if (hasMore) {
                        $('.load-more-container').show();
                        $('.btn-load-more').text('Load More Articles');
                    } else {
                        $('.load-more-container').hide();
                    }
                } else {
                    showMessage(response.data || khmLibrary.strings.error, 'error');
                }
            })
            .fail(function() {
                showMessage(khmLibrary.strings.error, 'error');
            })
            .always(function() {
                isLoading = false;
                if (!reset) {
                    $('.btn-load-more').text('Load More Articles');
                }
            });
    }

    function handleEditItem(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const postId = $(this).data('post-id');
        showEditItemModal(postId);
    }

    function handleDownloadItem(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const postId = $(this).data('post-id');
        
        // Check if download functionality is available
        if (typeof window.handleDownload === 'function') {
            window.handleDownload(postId);
        } else {
            // Fallback: trigger download via Social Strip or redirect
            window.open('/wp-admin/admin-ajax.php?action=kss_download_pdf&post_id=' + postId, '_blank');
        }
    }

    function handleEmailShare(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const postId = $(this).data('post-id');
        const url = $(this).data('url');
        
        // Populate share modal
        $('#share-post-id').val(postId);
        $('#recipient-email').val('');
        $('#personal-message').val('');
        $('#include-notes').prop('checked', true);
        $('#include-membership-info').prop('checked', true);
        
        // Show share modal
        $('#share-modal').show();
    }

    function handleLinkShare(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const url = $(this).data('url');
        
        if (navigator.share) {
            navigator.share({
                title: 'Check out this article',
                url: url
            });
        } else {
            // Fallback: copy to clipboard
            const tempInput = $('<input>');
            $('body').append(tempInput);
            tempInput.val(url).select();
            document.execCommand('copy');
            tempInput.remove();
            
            showMessage('Link copied to clipboard!', 'success');
        }
    }

    function handleSendEmailShare(e) {
        e.preventDefault();
        
        const postId = $('#share-post-id').val();
        const recipientEmail = $('#recipient-email').val();
        const personalMessage = $('#personal-message').val();
        const includeNotes = $('#include-notes').is(':checked');
        const includeMembershipInfo = $('#include-membership-info').is(':checked');
        
        if (!recipientEmail) {
            showMessage('Please enter a recipient email address', 'error');
            return;
        }

        // Show loading state
        const $submitBtn = $('#share-form .btn-send');
        const originalText = $submitBtn.text();
        $submitBtn.text('Sending...').prop('disabled', true);

        $.ajax({
            url: khmLibrary.ajax_url,
            type: 'POST',
            data: {
                action: 'khm_share_library_article',
                post_id: postId,
                recipient_email: recipientEmail,
                personal_message: personalMessage,
                include_notes: includeNotes,
                include_membership_info: includeMembershipInfo,
                nonce: khmLibrary.nonce
            },
            success: function(response) {
                $submitBtn.text(originalText).prop('disabled', false);
                
                if (response.success) {
                    showMessage('Article shared successfully!', 'success');
                    hideModals();
                } else {
                    showMessage(response.data || 'Failed to share article', 'error');
                }
            },
            error: function() {
                $submitBtn.text(originalText).prop('disabled', false);
                showMessage('Network error. Please try again.', 'error');
            }
        });
    }

    function showAddCategoryModal() {
        $('#category-name').val('');
        $('input[name="privacy"][value="private"]').prop('checked', true);
        $('#category-modal').show();
    }

    function showEditItemModal(postId) {
        // Get current item data
        const $item = $(`.library-item[data-post-id="${postId}"]`);
        
        $('#item-post-id').val(postId);
        
        // Pre-populate form (you'd need to get this data from the item or via AJAX)
        // For now, we'll show the modal and let users edit
        $('#item-modal').show();
    }

    function hideModals() {
        $('.khm-modal').hide();
    }

    function handleCreateCategory(e) {
        e.preventDefault();
        
        const categoryName = $('#category-name').val().trim();
        const privacy = $('input[name="privacy"]:checked').val();
        
        if (!categoryName) {
            showMessage('Please enter a category name', 'error');
            return;
        }

        const data = {
            action: 'khm_create_library_category',
            nonce: khmLibrary.nonce,
            category_name: categoryName,
            privacy: privacy
        };

        $.post(khmLibrary.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    // Add new category to list
                    const categoryHtml = `
                        <div class="category-item" data-category-id="${response.data.category_id}">
                            <span class="category-name">${response.data.category_name}</span>
                            <span class="category-count">0</span>
                        </div>
                    `;
                    
                    $('.category-items').append(categoryHtml);
                    
                    // Update item modal select
                    const optionHtml = `<option value="${response.data.category_id}">${response.data.category_name}</option>`;
                    $('#item-category').append(optionHtml);
                    
                    hideModals();
                    showMessage('Category created successfully!', 'success');
                } else {
                    showMessage(response.data || khmLibrary.strings.error, 'error');
                }
            })
            .fail(function() {
                showMessage(khmLibrary.strings.error, 'error');
            });
    }

    function handleUpdateItem(e) {
        e.preventDefault();
        
        const postId = $('#item-post-id').val();
        const categoryId = $('#item-category').val();
        const readStatus = $('#item-status').val();
        const isFavorite = $('#item-favorite').prop('checked');
        const notes = $('#item-notes').val();

        const data = {
            action: 'khm_update_library_item',
            nonce: khmLibrary.nonce,
            post_id: postId,
            category_id: categoryId,
            read_status: readStatus,
            is_favorite: isFavorite,
            notes: notes
        };

        $.post(khmLibrary.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    hideModals();
                    showMessage(khmLibrary.strings.saved, 'success');
                    
                    // Refresh the current view
                    currentOffset = 0;
                    hasMore = true;
                    loadLibraryItems(true);
                } else {
                    showMessage(response.data || khmLibrary.strings.error, 'error');
                }
            })
            .fail(function() {
                showMessage(khmLibrary.strings.error, 'error');
            });
    }

    function handleDeleteItem(e) {
        e.preventDefault();
        
        if (!confirm(khmLibrary.strings.confirm_delete)) {
            return;
        }
        
        const postId = $('#item-post-id').val();

        const data = {
            action: 'khm_delete_library_item',
            nonce: khmLibrary.nonce,
            post_id: postId
        };

        $.post(khmLibrary.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    hideModals();
                    showMessage(khmLibrary.strings.deleted, 'success');
                    
                    // Update stats
                    if (response.data.stats) {
                        updateStats(response.data.stats);
                    }
                    
                    // Refresh the current view
                    currentOffset = 0;
                    hasMore = true;
                    loadLibraryItems(true);
                } else {
                    showMessage(response.data || khmLibrary.strings.error, 'error');
                }
            })
            .fail(function() {
                showMessage(khmLibrary.strings.error, 'error');
            });
    }

    function updateStats(stats) {
        $('.khm-library-stats .stat-item:nth-child(1) strong').text(stats.total_saved);
        $('.khm-library-stats .stat-item:nth-child(2) strong').text(stats.favorites);
        $('.khm-library-stats .stat-item:nth-child(3) strong').text(stats.unread);
        
        // Update "All Articles" count
        $('.category-item[data-category-id="all"] .category-count').text(stats.total_saved);
    }

    function showMessage(message, type = 'info') {
        // Remove existing messages
        $('.khm-message').remove();
        
        const messageClass = type === 'error' ? 'error' : 'success';
        const messageHtml = `
            <div class="khm-message khm-message-${messageClass}" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'error' ? '#e74c3c' : '#27ae60'};
                color: white;
                padding: 15px 20px;
                border-radius: 6px;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                font-weight: 500;
            ">
                ${message}
            </div>
        `;
        
        $('body').append(messageHtml);
        
        // Auto-hide after 3 seconds
        setTimeout(function() {
            $('.khm-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Utility function for debouncing
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

    // Integration with Social Strip save buttons
    $(document).on('library-item-saved', function(e, postId) {
        // Refresh library if an item was saved from elsewhere
        if (currentCategory === 'all' || currentCategory === '1') { // Reading List
            currentOffset = 0;
            hasMore = true;
            loadLibraryItems(true);
        }
    });

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Escape key closes modals
        if (e.keyCode === 27) {
            hideModals();
        }
        
        // Ctrl/Cmd + F focuses search
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 70) {
            e.preventDefault();
            $('#library-search').focus();
        }
    });

    // Infinite scroll (optional enhancement)
    if ($('.khm-library-container').data('infinite-scroll') === true) {
        $(window).on('scroll', debounce(function() {
            if ($(window).scrollTop() + $(window).height() > $(document).height() - 1000) {
                if (hasMore && !isLoading) {
                    handleLoadMore();
                }
            }
        }, 200));
    }
});