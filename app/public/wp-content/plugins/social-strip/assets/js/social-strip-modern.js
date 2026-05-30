/**
 * Social Strip Modern Hydrator
 * 
 * Replaces legacy AJAX Sprawl with a unified REST-based hydration model.
 * Consumes the editorial/v1 namespace for member data, pricing, and sharing.
 */

(function($) {
    'use strict';

    const { __ } = wp.i18n;
    const { apiFetch } = wp;

    class SocialStripHydrator {
        constructor() {
            // Localized data provided by the plugin (decoupled from khm_ajax)
            this.queue = new Set();
            this.cache = {}; // Singleton state cache
            this.isProcessing = false;
            
            // Check if standard WordPress libraries are available
            if (!apiFetch || !__) return;

            this.init();
        }

        init() {
            $(document).ready(() => {
                this.scanAndQueue();
                this.bindActions();
                this.bindGlobalEvents();
            });

            // Re-run on AJAX completions for SPA themes/dynamic content
            $(document).on('ajaxComplete', () => {
                this.scanAndQueue();
            });
        }

        /**
         * Listen for state updates from other strips on the page.
         */
        bindGlobalEvents() {
            $(document).on('kss:state-updated', (e, { postId, data }) => {
                this.cache[postId] = { ...this.cache[postId], ...data };
                this.syncPostStrips(postId);
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
                    if (this.cache[postId]) {
                        this.applyData($strip, this.cache[postId]);
                    } else {
                        this.queue.add(postId);
                    }
                }
            });

            if (this.queue.size > 0 && !this.isProcessing) {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => this.processQueue(), 50);
            }
        }

        /**
         * Process the queued IDs in a single bulk request using wp.apiFetch.
         */
        async processQueue() {
            if (this.queue.size === 0) return;
            
            this.isProcessing = true;
            const ids = Array.from(this.queue).join(',');
            this.queue.clear();

            try {
                // FIXED: Full namespace path required for apiFetch relative to wp-json/ root
                const response = await apiFetch({
                    path: `/editorial/v1/member/posts-data?ids=${ids}`,
                    method: 'GET'
                });

                Object.keys(response).forEach(postId => {
                    this.cache[postId] = response[postId];
                    this.syncPostStrips(postId);
                });

            } catch (err) {
                console.error('[SocialStrip] Bulk Hydration Failed', err);
            } finally {
                this.isProcessing = false;
            }
        }

        /**
         * Sync all strips on the page for a specific post ID.
         */
        syncPostStrips(postId) {
            const data = this.cache[postId];
            if (!data) return;

            const $strips = this.findStripsByPostId(postId);
            $strips.each((i, el) => this.applyData($(el), data));
        }

        /**
         * Find all strips for a specific post ID on the page.
         */
        findStripsByPostId(postId) {
            return $(`.kss-social-strip [data-post-id="${postId}"]`).closest('.kss-social-strip');
        }

        /**
         * Apply the REST response to the DOM elements.
         */
        applyData($strip, data) {
            const { access, sharing, labels } = data;

            // 1. Update Buy Button
            $strip.find('.kss-buy-button').each((i, btn) => {
                const $btn = $(btn);
                const $label = $btn.closest('.kss-action').find('.kss-label');
                if (labels && labels.buy) {
                    $label.text(labels.buy);
                    $btn.attr('title', labels.buy);
                }
                if (access.has_purchased) {
                    $btn.addClass('purchased');
                } else {
                    $btn.removeClass('purchased');
                }
            });

            // 2. Update Gift Button
            $strip.find('.kss-gift-button').each((i, btn) => {
                const $btn = $(btn);
                const $label = $btn.closest('.kss-action').find('.kss-label');
                if (labels && labels.gift) {
                    $label.text(labels.gift);
                    $btn.attr('title', labels.gift);
                }
            });

            // 3. Update Save Button
            $strip.find('.kss-save-button').each((i, btn) => {
                const $btn = $(btn);
                const $label = $btn.closest('.kss-action').find('.kss-label');
                if (access.is_saved) {
                    $btn.addClass('saved');
                } else {
                    $btn.removeClass('saved');
                }
                if (labels && labels.save) {
                    $btn.attr('title', labels.save);
                    $label.text(labels.save);
                }
            });

            // 4. Update Download Button
            $strip.find('.kss-download-credit').each((i, btn) => {
                const $btn = $(btn);
                const $label = $btn.closest('.kss-action').find('.kss-label');
                if (labels && labels.download) {
                    $label.text(labels.download);
                    $btn.attr('title', labels.download);
                }
                if (access.has_downloaded) {
                    $btn.addClass('downloaded');
                } else {
                    $btn.removeClass('downloaded');
                }
            });

            // 5. Update Sharing
            $strip.find('.ssm-share-trigger').each((i, btn) => {
                const $btn = $(btn);
                if (sharing.affiliate_url) {
                    $btn.attr('data-url', sharing.affiliate_url);
                }
            });

            $strip.attr('data-kss-hydrated', 'true');
            $strip.data('kss-hydrated', true);
            $strip.trigger('kss:hydrated', [data]);
        }

        bindActions() {
            // Save to Library - Modern REST path
            $(document).off('click.kssModernSave', '.kss-save-button');
            $(document).on('click.kssModernSave', '.kss-save-button', (e) => {
                e.preventDefault();
                e.stopImmediatePropagation();
                this.handleLibraryToggle($(e.currentTarget));
            });
        }

        /**
         * Handle Save/Remove toggle with synchronized loading states across all elements.
         */
        async handleLibraryToggle($clickedBtn) {
            const postId = $clickedBtn.data('post-id');
            const isRemoving = $clickedBtn.hasClass('saved');
            
            // UX SYNC: Apply loading state to ALL strips for this post ID
            const $allRelatedBtns = this.findStripsByPostId(postId).find('.kss-save-button');
            $allRelatedBtns.addClass('loading');

            try {
                // FIXED: Full namespace path required for apiFetch
                const response = await apiFetch({
                    path: '/editorial/v1/member/library',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        action: isRemoving ? 'remove' : 'save'
                    }
                });

                if (response.success) {
                    // Update global cache and re-sync DOM
                    $(document).trigger('kss:state-updated', [{
                        postId: postId,
                        data: {
                            access: {
                                ...this.cache[postId]?.access,
                                is_saved: response.is_saved
                            }
                        }
                    }]);

                    const msg = response.is_saved 
                        ? __('Saved to library', 'social-strip') 
                        : __('Removed from library', 'social-strip');
                    this.showFlash(msg, 'success');
                }
            } catch (err) {
                console.error('[SocialStrip] Library Toggle Failed', err);
                this.showFlash(__('Something went wrong. Please try again.', 'social-strip'), 'error');
            } finally {
                // UX SYNC: Remove loading state from ALL related strips
                $allRelatedBtns.removeClass('loading');
            }
        }

        showFlash(message, type) {
            if (window.showSaveFlashNotification) {
                window.showSaveFlashNotification(message, type);
            } else {
                console.log(`[SocialStrip] ${type}: ${message}`);
            }
        }
    }

    // Initialize singleton
    window.kssModernHydrator = new SocialStripHydrator();

})(jQuery);
