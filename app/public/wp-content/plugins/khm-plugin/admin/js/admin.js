/**
 * KHM Admin JavaScript
 */

(function($) {
    'use strict';

    var KHMAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind DOM events
         */
        bindEvents: function() {
            // Confirm delete actions
            $(document).on('click', 'a[href*="action=delete"]', this.confirmDelete);
            
            // Bulk action confirmation
            $('#doaction, #doaction2').on('click', this.confirmBulkAction);
            
            // Add level button (placeholder)
            $('#khm-add-level').on('click', function(e) {
                e.preventDefault();
                alert('Level editor coming soon! For now, manage levels via phpMyAdmin or SQL.');
            });
            
            // Add discount code button (placeholder)
            $('#khm-add-code').on('click', function(e) {
                e.preventDefault();
                alert('Discount code editor coming soon! For now, manage codes via phpMyAdmin or SQL.');
            });
        },

        /**
         * Confirm delete action
         */
        confirmDelete: function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        },

        /**
         * Confirm bulk actions
         */
        confirmBulkAction: function(e) {
            var $button = $(this);
            var action = $button.closest('form').find('select[name="action"]').val();
            
            if (action === 'delete') {
                if (!confirm('Are you sure you want to delete the selected items? This action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            if (action === 'cancel') {
                if (!confirm('Are you sure you want to cancel the selected memberships?')) {
                    e.preventDefault();
                    return false;
                }
            }
        },

        /**
         * Show loading state on button
         */
        setLoading: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true).addClass('khm-loading');
            } else {
                $button.prop('disabled', false).removeClass('khm-loading');
            }
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'success';
            
            var $notice = $('<div>')
                .addClass('notice notice-' + type + ' is-dismissible')
                .append($('<p>').text(message));
            
            $('.wrap h1').after($notice);
            
            // Initialize dismiss button
            if (typeof wp !== 'undefined' && wp.notices) {
                wp.notices.init();
            }
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 100
            }, 300);
        },

        /**
         * Copy to clipboard helper
         */
        copyToClipboard: function(text) {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            
            this.showNotice('Copied to clipboard!', 'info');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        KHMAdmin.init();
    });

    // Expose to global scope for extensions
    window.KHMAdmin = KHMAdmin;

})(jQuery);
