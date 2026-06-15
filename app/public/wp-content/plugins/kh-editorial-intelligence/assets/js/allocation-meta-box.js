/**
 * Content Allocation Meta Box – JS
 *
 * Handles site selection, clone/rewrite actions, and status updates.
 */
(function ($, apiFetch) {
    'use strict';

    const $container = $('#kh-allocation-meta-box');
    if (!$container.length) return;

    const $checkboxes = $container.find('.kh-allocation-checkbox');
    const $btnRewrite = $('#kh-allocate-clone-rewrite');
    const $btnCloneOnly = $('#kh-allocate-clone-only');
    const $statusMsg = $('#kh-allocation-status-message');
    const { postId, nonce, labels } = window.khAllocationData || {};
    const API_BASE = 'kh-editorial/v1/allocation';

    // ─── Button enable / disable ───────────────────────────────────────────────

    function updateButtonState() {
        const selected = $checkboxes.filter(':checked:not(:disabled)').length;
        $btnRewrite.prop('disabled', selected === 0);
        $btnCloneOnly.prop('disabled', selected === 0);
    }

    $checkboxes.on('change', updateButtonState);

    // ─── Show status message ───────────────────────────────────────────────────

    function showStatus(message, type) {
        $statusMsg
            .text(message)
            .removeClass('notice-success notice-error')
            .addClass(type === 'success' ? 'notice-success' : 'notice-error')
            .css({
                display: 'block',
                background: type === 'success' ? '#ecf7ed' : '#fbeaea',
                borderLeft: type === 'success' ? '4px solid #00a32a' : '4px solid #d63638',
                color: type === 'success' ? '#1e561e' : '#8b3a3a',
            });

        // Auto-hide success messages after 8 seconds
        if (type === 'success') {
            setTimeout(function () {
                $statusMsg.fadeOut(300);
            }, 8000);
        }
    }

    // ─── Show button loading state ────────────────────────────────────────────

    function setLoading(isRewrite, loading) {
        const $btn = isRewrite ? $btnRewrite : $btnCloneOnly;
        const label = isRewrite
            ? (loading ? labels.rewriting : 'Clone & Rewrite')
            : (loading ? labels.cloning : 'Clone Only');

        $btn.prop('disabled', loading).text(label);

        // Disable the other button too during operation
        const $otherBtn = isRewrite ? $btnCloneOnly : $btnRewrite;
        $otherBtn.prop('disabled', loading);
        $checkboxes.prop('disabled', loading);
    }

    // ─── Execute clone ─────────────────────────────────────────────────────────

    async function doClone(rewrite) {
        const selectedSlugs = $checkboxes
            .filter(':checked:not(:disabled)')
            .map(function () {
                return $(this).val();
            })
            .get();

        if (selectedSlugs.length === 0) {
            showStatus(labels.selectSites, 'error');
            return;
        }

        setLoading(rewrite, true);

        try {
            const result = await apiFetch({
                path: API_BASE + '/clone',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': nonce,
                    'Content-Type': 'application/json',
                },
                data: {
                    post_id: parseInt(postId, 10),
                    site_slugs: selectedSlugs,
                    rewrite: rewrite,
                },
            });

            if (result.success && result.results) {
                // Update UI for each successful clone
                result.results.forEach(function (res) {
                    markAsCloned(res.slug, res.rewrite_applied, res.edit_url);
                });

                // Show any errors
                if (result.errors && result.errors.length > 0) {
                    const errorMessages = result.errors.map(function (e) {
                        return e.slug + ': ' + e.message;
                    }).join(' | ');
                    showStatus(result.message + ' Errors: ' + errorMessages, 'error');
                } else {
                    showStatus(result.message, 'success');
                }

                // Refresh button states
                updateButtonState();
            } else {
                showStatus((result && result.message) || labels.error, 'error');
            }
        } catch (err) {
            console.error('[Allocation] Clone error:', err);
            showStatus(
                (err && err.message) || labels.error,
                'error'
            );
        } finally {
            setLoading(rewrite, false);
            updateButtonState();
        }
    }

    // ─── Mark a site as cloned in the UI ───────────────────────────────────────

    function markAsCloned(slug, rewriteApplied, editUrl) {
        const $row = $container.find('.kh-allocation-site-row[data-slug="' + slug + '"]');
        if (!$row.length) return;

        const $checkbox = $row.find('.kh-allocation-checkbox');
        $checkbox.prop('disabled', true).prop('checked', false);

        const $status = $row.find('.kh-allocation-status');
        let statusHtml = '<span style="color: #00a32a;">✅ Cloned</span>';
        if (rewriteApplied) {
            statusHtml += ' <span style="color: #2271b1; font-size: 11px;">(rewritten)</span>';
        }
        $status.html(statusHtml);

        // Add edit link if we have one
        if (editUrl) {
            const existingLink = $row.find('.kh-allocation-edit-link');
            if (!existingLink.length) {
                const $editLink = $('<a>')
                    .attr('href', editUrl)
                    .attr('target', '_blank')
                    .addClass('kh-allocation-edit-link')
                    .css({ marginLeft: '6px', fontSize: '12px' })
                    .attr('title', 'Edit variant')
                    .text('📝');
                $status.after($editLink);
            }
        }
    }

    // ─── Event handlers ────────────────────────────────────────────────────────

    $btnRewrite.on('click', function () {
        doClone(true);
    });

    $btnCloneOnly.on('click', function () {
        doClone(false);
    });

    // ─── Load existing allocation status on page load ──────────────────────────

    async function loadStatus() {
        if (!postId || parseInt(postId, 10) <= 0) return;

        try {
            const result = await apiFetch({
                path: API_BASE + '/status/' + parseInt(postId, 10),
                method: 'GET',
                headers: {
                    'X-WP-Nonce': nonce,
                },
            });

            if (result.success && result.sites) {
                result.sites.forEach(function (site) {
                    if (site.allocated) {
                        markAsCloned(site.slug, site.rewrite_applied, site.edit_url);
                    }
                });
            }

            updateButtonState();
        } catch (err) {
            // Status load is non-critical — log and continue
            console.warn('[Allocation] Could not load allocation status:', err);
        }
    }

    // Run on load
    $(loadStatus);

})(jQuery, wp.apiFetch);