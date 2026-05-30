/**
 * Social Strip Modern Hydrator
 * 
 * Replaces legacy AJAX Sprawl with a unified REST-based hydration model.
 * Consumes the editorial/v1 namespace for member data, pricing, and sharing.
 */

(function($) {
    'use strict';

    class SocialStripHydrator {
        constructor() {
            this.apiUrl = typeof khm_ajax !== 'undefined' ? khm_ajax.rest_url + 'editorial/v1/' : '';
            this.nonce = typeof khm_ajax !== 'undefined' ? khm_ajax.rest_nonce : '';
            this.queue = new Set();
            this.isProcessing = false;
            
            if (!this.apiUrl) return;

            this.init();
        }

        init() {
            $(document).ready(() => {
                this.scanAndQueue();
                this.bindActions();
            });

            // Re-run on AJAX completions for SPA themes
            $(document).on('ajaxComplete', () => {
                this.scanAndQueue();
            });
        }

        /**
         * Scan page for social strips and add them to the bulk queue.
         */
        scanAndQueue() {
            $('.kss-social-strip').each((index, el) => {
                const $strip = $(el);
                if ($strip.data('kss-hydrated') === true) return;

                const postId = $strip.find('[data-post-id]').first().data('post-id');
                if (postId) {
                    this.queue.add(postId);
                }
            });

            if (this.queue.size > 0 && !this.isProcessing) {
                // Debounce slightly to catch all strips on the page
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => this.processQueue(), 50);
            }
        }

        /**
         * Process the queued IDs in a single bulk request.
         */
        processQueue() {
            if (this.queue.size === 0) return;
            
            this.isProcessing = true;
            const ids = Array.from(this.queue).join(',');
            this.queue.clear();

            $.ajax({
                url: `${this.apiUrl}member/posts-data`,
                method: 'GET',
                data: { ids: ids },
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                },
                success: (response) => {
                    this.isProcessing = false;
                    Object.keys(response).forEach(postId => {
                        const $strips = $(`.kss-social-strip [data-post-id="${postId}"]`).closest('.kss-social-strip');
                        $strips.each((i, el) => this.applyData($(el), response[postId]));
                    });
                },
                error: () => {
                    this.isProcessing = false;
                    // Error state handled by lack of hydration marker
                }
            });
        }

        /**
         * Apply the REST response to the DOM elements.
         */
        applyData($strip, data) {
            const { pricing, access, sharing, labels } = data;

            // 1. Update Buy Button & Label
            $strip.find('.kss-buy-button').each((i, btn) => {
                const $btn = $(btn);
                const $label = $btn.closest('.kss-action').find('.kss-label');
                
                $label.text(labels.buy);
                $btn.attr('title', labels.buy);
                if (access.has_purchased) $btn.addClass('purchased');
            });

            // 2. Update Gift Button
            $strip.find('.kss-gift-button').each((i, btn) => {
                const $btn = $(btn);
                const $label = $btn.closest('.kss-action').find('.kss-label');
                
                $label.text(labels.gift);
                $btn.attr('title', labels.gift);
            });

            // 3. Update Save/Library Status
            $strip.find('.kss-save-button').each((i, btn) => {
                const $btn = $(btn);
                const $label = $btn.closest('.kss-action').find('.kss-label');

                if (access.is_saved) {
                    $btn.addClass('saved').attr('title', labels.save);
                } else {
                    $btn.removeClass('saved').attr('title', labels.save);
                }
                $label.text(labels.save);
            });

            // 4. Update Download Status
            $strip.find('.kss-download-credit').each((i, btn) => {
                const $btn = $(btn);
                const $label = $btn.closest('.kss-action').find('.kss-label');
                
                $label.text(labels.download);
                $btn.attr('title', labels.download);
                if (access.has_downloaded) $btn.addClass('downloaded');
            });

            // 5. Update Sharing (Affiliate Links)
            $strip.find('.ssm-share-trigger').each((i, btn) => {
                const $btn = $(btn);
                $btn.attr('data-url', sharing.affiliate_url);
            });

            // Mark as hydrated
            $strip.attr('data-kss-hydrated', 'true');
            $strip.trigger('kss:hydrated', [data]);
        }

        /**
         * Bind global actions for buttons.
         */
        bindActions() {
            // Save to Library - use .off() to prevent double binding with legacy
            $(document).off('click.kssModernSave', '.kss-save-button');
            $(document).on('click.kssModernSave', '.kss-save-button', (e) => {
                e.preventDefault();
                e.stopImmediatePropagation(); // Stop legacy script from firing
                const $btn = $(e.currentTarget);
                this.handleLibraryToggle($btn);
            });
        }

        /**
         * Handle Save/Remove from Library via REST.
         */
        handleLibraryToggle($btn) {
            const postId = $btn.data('post-id');
            const isRemoving = $btn.hasClass('saved');
            
            $btn.addClass('loading');

            $.ajax({
                url: `${this.apiUrl}member/library`,
                method: 'POST',
                data: {
                    post_id: postId,
                    action: isRemoving ? 'remove' : 'save'
                },
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                },
                success: (response) => {
                    $btn.removeClass('loading');
                    if (response.success) {
                        if (response.is_saved) {
                            $btn.addClass('saved');
                            this.showFlash('Saved to library', 'success');
                        } else {
                            $btn.removeClass('saved');
                            this.showFlash('Removed from library', 'success');
                        }
                    }
                }
            });
        }

        showFlash(message, type) {
            if (window.showSaveFlashNotification) {
                window.showSaveFlashNotification(message, type);
            } else {
                console.log(`[SocialStrip] ${type}: ${message}`);
            }
        }
    }

    // Initialize
    new SocialStripHydrator();

})(jQuery);
