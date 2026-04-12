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
            details.innerHTML = '<p>No active preview link for this post.</p>';
            el('khm-preview-actions').classList.add('hidden');
            el('khm-preview-hits').innerHTML = '';
            return;
        }
        el('khm-preview-actions').classList.remove('hidden');
        const previewUrl = `${window.location.origin}/?khm_preview_post=${link.post_id}&khm_preview_token=${link.token}`;
        details.innerHTML = `
            <p><strong>Status:</strong> ${link.status}</p>
            <p><strong>Expires:</strong> ${link.expires_at}</p>
            <p><strong>Preview URL:</strong><br/><code>${previewUrl}</code></p>
        `;
        const hits = (link.hits || []).map((hit) => `<li>${hit.viewed_at} — ${hit.ip || 'n/a'}</li>`).join('');
        el('khm-preview-hits').innerHTML = hits ? `<h3>Recent Views</h3><ul>${hits}</ul>` : '<p>No previews recorded.</p>';
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
                message('Preview link loaded.', 'success');
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
