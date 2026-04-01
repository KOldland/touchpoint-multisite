(function () {
    const state = {
        postId: null,
        link: null,
    };

    function el(id) {
        return document.getElementById(id);
    }

    function message(text, type = 'info') {
        const box = el('khm-preview-message');
        box.textContent = text;
        box.className = 'khm-preview-status khm-preview-status-' + type;
    }

    function api(path, options = {}) {
        const headers = options.headers || {};
        headers['X-WP-Nonce'] = khmPreviewData.nonce;
        if (options.body && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }
        return fetch(khmPreviewData.restUrl + path, {
            credentials: 'same-origin',
            ...options,
            headers,
        }).then(async (response) => {
            if (response.status === 204) {
                return null;
            }
            const data = await response.json().catch(() => null);
            if (!response.ok) {
                throw new Error((data && data.message) || 'Request failed');
            }
            return data;
        });
    }

    function renderLink(link) {
        state.link = link;
        const details = el('khm-preview-details');
        if (!link) {
            details.innerHTML = '<p>No preview link history for this post yet.</p>';
            el('khm-preview-actions').classList.remove('hidden');
            el('khm-preview-hits').innerHTML = '';
            return;
        }

        el('khm-preview-actions').classList.remove('hidden');
        const previewUrl = `${window.location.origin}/?p=${link.post_id}&khm_preview_post=${link.post_id}&khm_preview_token=${link.token}`;
        const status = String(link.status_display || link.status || '').toLowerCase();
        const isActive = status === 'active';
        const statusClass = isActive ? 'khm-preview-badge-active' : (status === 'expired' ? 'khm-preview-badge-expired' : 'khm-preview-badge-revoked');
        const statusLabel = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';
        const linkHint = isActive ? '' : '<p class="description">This link is not currently valid. Create/Refresh to issue a new one.</p>';
        details.innerHTML = `
            <p><strong>Status:</strong> <span class="khm-preview-badge ${statusClass}">${statusLabel}</span></p>
            <p><strong>Expires:</strong> ${link.expires_at}</p>
            <p><strong>Preview URL:</strong><br/><code id="khm-preview-url">${previewUrl}</code></p>
            <p class="khm-preview-url-actions">
                <button type="button" class="button" id="khm-preview-copy-url" data-preview-url="${previewUrl.replace(/"/g, '&quot;')}" ${isActive ? '' : 'disabled'}>Copy Link</button>
                ${isActive
                    ? `<a class="button" id="khm-preview-open-url" href="${previewUrl.replace(/"/g, '&quot;')}" target="_blank" rel="noopener">Open Link</a>`
                    : '<button type="button" class="button" id="khm-preview-open-url" disabled>Open Link</button>'
                }
            </p>
            ${linkHint}
        `;
        const hits = (link.hits || []).map((hit) => `<li>${hit.viewed_at} — ${hit.ip || 'n/a'}</li>`).join('');
        el('khm-preview-hits').innerHTML = hits ? `<h3>Recent Views</h3><ul>${hits}</ul>` : '<p>No previews recorded.</p>';

        const revokeButton = el('khm-preview-revoke');
        const extendButton = el('khm-preview-extend');
        if (revokeButton) revokeButton.disabled = !isActive;
        if (extendButton) extendButton.disabled = !isActive;

    }

    function copyText(value) {
        if (!value) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value)
                .then(() => message('Preview URL copied.', 'success'))
                .catch(() => fallbackCopy(value));
            return;
        }
        fallbackCopy(value);
    }

    function fallbackCopy(value) {
        const input = document.createElement('input');
        input.value = value;
        document.body.appendChild(input);
        input.select();
        try {
            document.execCommand('copy');
            message('Preview URL copied.', 'success');
        } catch (err) {
            message('Could not copy URL automatically.', 'error');
        } finally {
            document.body.removeChild(input);
        }
    }

    function setPostId(value) {
        el('khm-preview-post-id').value = value || '';
    }

    function loadLink() {
        const postId = parseInt(el('khm-preview-post-id').value, 10);
        if (!postId) {
            message('Enter a valid post ID', 'error');
            return;
        }
        state.postId = postId;
        api(`/posts/${postId}/link`, { method: 'GET' })
            .then((data) => {
                renderLink(data);
                message(data ? 'Preview link loaded.' : 'No preview link yet for this post.', 'success');
            })
            .catch((error) => message(error.message, 'error'));
    }

    function createLink() {
        if (!state.postId) {
            message('Load a post first.', 'error');
            return;
        }
        const hours = parseInt(el('khm-preview-create-hours').value, 10) || 48;
        api('/links', {
            method: 'POST',
            body: JSON.stringify({ post_id: state.postId, hours }),
        })
            .then((data) => {
                renderLink({ ...data, post_id: state.postId, status: 'active' });
                message('Preview link created.', 'success');
                loadLink();
            })
            .catch((error) => message(error.message, 'error'));
    }

    function revokeLink() {
        if (!state.link) {
            return;
        }
        api(`/links/${state.link.id}`, { method: 'DELETE' })
            .then(() => {
                message('Preview link revoked.', 'success');
                loadLink();
            })
            .catch((error) => message(error.message, 'error'));
    }

    function extendLink() {
        if (!state.link) {
            return;
        }
        const hours = parseInt(el('khm-preview-extend-hours').value, 10) || 24;
        api(`/links/${state.link.id}/extend`, {
            method: 'POST',
            body: JSON.stringify({ hours }),
        })
            .then(() => {
                message('Preview link extended.', 'success');
                loadLink();
            })
            .catch((error) => message(error.message, 'error'));
    }

    document.addEventListener('DOMContentLoaded', () => {
        const container = el('khm-preview-manager');
        if (!container) {
            return;
        }
        const recentPosts = khmPreviewData.recentPosts || [];
        const formatOptionLabel = (post) => `${post.title} (${post.status}, ${post.author || 'Unknown'}, ${post.modified || ''})`;

        container.innerHTML = `
            <div class="khm-preview-controls">
                <label for="khm-preview-post-id">Post ID</label>
                <input type="number" id="khm-preview-post-id" placeholder="e.g. 42" />
                <button class="button" id="khm-preview-load">Load Preview</button>
                ${recentPosts.length ? `
                    <label for="khm-preview-search">Search drafts</label>
                    <input type="text" id="khm-preview-search" placeholder="Type to filter..." />
                    <select id="khm-preview-recent">
                        <option value="">Select draft...</option>
                        ${recentPosts.map((post) => `<option value="${post.id}">${formatOptionLabel(post)}</option>`).join('')}
                    </select>
                ` : ''}
            </div>
            <div class="khm-preview-status" id="khm-preview-message">Load a post to manage its preview link.</div>
            <div class="khm-preview-details" id="khm-preview-details"></div>
            <div id="khm-preview-actions" class="hidden">
                <h3>Actions</h3>
                <div>
                    <label for="khm-preview-create-hours">Create/Regenerate (hours)</label>
                    <input type="number" id="khm-preview-create-hours" value="48" min="1" />
                    <button class="button button-primary" id="khm-preview-create">Create/Refresh Link</button>
                </div>
                <div style="margin-top:10px;">
                    <label for="khm-preview-extend-hours">Extend Existing Link (hours)</label>
                    <input type="number" id="khm-preview-extend-hours" value="24" min="1" />
                    <button class="button" id="khm-preview-extend">Extend Link</button>
                </div>
                <button class="button button-secondary" id="khm-preview-revoke" style="margin-top:10px;">Revoke Link</button>
            </div>
            <div class="khm-preview-hits" id="khm-preview-hits"></div>
        `;

        el('khm-preview-load').addEventListener('click', loadLink);
        el('khm-preview-create').addEventListener('click', createLink);
        el('khm-preview-revoke').addEventListener('click', revokeLink);
        el('khm-preview-extend').addEventListener('click', extendLink);
        container.addEventListener('click', (event) => {
            const target = event.target;
            if (!target) {
                return;
            }
            if (target.id === 'khm-preview-copy-url') {
                const value = String(target.getAttribute('data-preview-url') || '');
                if (!value) {
                    message('No preview URL available to copy.', 'error');
                    return;
                }
                copyText(value);
            }
        });

        const recentSelect = el('khm-preview-recent');
        const searchInput  = el('khm-preview-search');
        if (recentSelect) {
            recentSelect.addEventListener('change', (event) => {
                setPostId(event.target.value);
                if (event.target.value) {
                    loadLink();
                }
            });
        }
        if (searchInput && recentSelect) {
            const renderFilteredOptions = () => {
                const filter = searchInput.value.toLowerCase();
                recentSelect.innerHTML = '<option value="">Select draft...</option>' +
                    recentPosts
                        .filter((post) => post.title.toLowerCase().includes(filter) || (post.author || '').toLowerCase().includes(filter))
                        .map((post) => `<option value="${post.id}">${formatOptionLabel(post)}</option>`)
                        .join('');
            };
            searchInput.addEventListener('input', renderFilteredOptions);
        }

        if (khmPreviewData.initialPostId) {
            setPostId(khmPreviewData.initialPostId);
            loadLink();
        }
    });
})();
