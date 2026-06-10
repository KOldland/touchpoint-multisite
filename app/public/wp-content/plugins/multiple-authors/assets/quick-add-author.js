(function() {
    // Guard: only inject once globally
    if (window.__qcAuthorInjected) return;
    window.__qcAuthorInjected = true;

    var apiFetch = wp.apiFetch;

    function init() {
        var field = document.querySelector('[data-key="field_multi_author_relationship"]');
        if (!field) return;

        var btnWrap = document.createElement('div');
        btnWrap.className = 'qc-add-author-btn';
        btnWrap.style.cssText = 'margin-top:8px;';

        var toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'components-button is-secondary is-small';
        toggleBtn.style.cssText = 'width:100%;justify-content:center;cursor:pointer;padding:6px;';
        toggleBtn.textContent = '+ Create New Author';
        btnWrap.appendChild(toggleBtn);

        var formWrap = document.createElement('div');
        formWrap.style.cssText = 'display:none;margin-top:8px;padding:8px;background:#f6f7f7;border-radius:4px;';
        formWrap.innerHTML = '' +
            '<div style="margin-bottom:8px;">' +
            '  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#1e1e1e;">Name *</label>' +
            '  <input type="text" id="qc-name" style="width:100%;padding:6px 8px;border:1px solid #ccc;border-radius:2px;font-size:13px;box-sizing:border-box;" placeholder="Full name">' +
            '</div>' +
            '<div style="margin-bottom:8px;">' +
            '  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#1e1e1e;">Title</label>' +
            '  <input type="text" id="qc-title" style="width:100%;padding:6px 8px;border:1px solid #ccc;border-radius:2px;font-size:13px;box-sizing:border-box;" placeholder="e.g. Senior Editor">' +
            '</div>' +
            '<div style="margin-bottom:8px;">' +
            '  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#1e1e1e;">Company</label>' +
            '  <input type="text" id="qc-company" style="width:100%;padding:6px 8px;border:1px solid #ccc;border-radius:2px;font-size:13px;box-sizing:border-box;" placeholder="e.g. Company Name">' +
            '</div>' +
            '<div style="margin-bottom:8px;">' +
            '  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#1e1e1e;">Bio</label>' +
            '  <textarea id="qc-bio" rows="3" style="width:100%;padding:6px 8px;border:1px solid #ccc;border-radius:2px;font-size:13px;box-sizing:border-box;resize:vertical;" placeholder="Short biography..."></textarea>' +
            '</div>' +
            '<div id="qc-msg" style="display:none;margin-bottom:8px;padding:6px;border-radius:2px;font-size:12px;"></div>' +
            '<button type="button" id="qc-submit" class="components-button is-primary" style="width:100%;justify-content:center;cursor:pointer;padding:6px;">Create & Link Author</button>';

        btnWrap.appendChild(formWrap);
        field.parentNode.insertBefore(btnWrap, field.nextSibling);

        toggleBtn.addEventListener('click', function() {
            if (formWrap.style.display === 'none') {
                formWrap.style.display = 'block';
                toggleBtn.textContent = '− Cancel';
            } else {
                formWrap.style.display = 'none';
                toggleBtn.textContent = '+ Create New Author';
            }
        });

        document.getElementById('qc-submit').addEventListener('click', function() {
            var name = document.getElementById('qc-name').value.trim();
            var title = document.getElementById('qc-title').value.trim();
            var company = document.getElementById('qc-company').value.trim();
            var bio = document.getElementById('qc-bio').value.trim();
            var msgEl = document.getElementById('qc-msg');
            var submitBtn = document.getElementById('qc-submit');
            var postId = wp.data.select('core/editor').getCurrentPostId();

            if (!name) {
                msgEl.style.cssText = 'display:block;margin-bottom:8px;padding:6px;border-radius:2px;font-size:12px;background:#fcf0f1;color:#cc1818;';
                msgEl.textContent = 'Name is required.';
                return;
            }

            msgEl.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            apiFetch({
                path: '/khm/v1/quick-add-author',
                method: 'POST',
                data: { name: name, title: title, company: company, bio: bio, post_id: postId }
            }).then(function(result) {
                if (result.success) {
                    msgEl.style.cssText = 'display:block;margin-bottom:8px;padding:6px;border-radius:2px;font-size:12px;background:#edfaef;color:#1e8a1e;';
                    msgEl.textContent = result.data.message;
                    document.getElementById('qc-name').value = '';
                    document.getElementById('qc-title').value = '';
                    document.getElementById('qc-company').value = '';
                    document.getElementById('qc-bio').value = '';
                    setTimeout(function() {
                        wp.data.dispatch('core/editor').refreshPost();
                        formWrap.style.display = 'none';
                        toggleBtn.textContent = '+ Create New Author';
                    }, 1000);
                } else {
                    msgEl.style.cssText = 'display:block;margin-bottom:8px;padding:6px;border-radius:2px;font-size:12px;background:#fcf0f1;color:#cc1818;';
                    msgEl.textContent = result.data.message;
                }
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create & Link Author';
            }).catch(function(err) {
                msgEl.style.cssText = 'display:block;margin-bottom:8px;padding:6px;border-radius:2px;font-size:12px;background:#fcf0f1;color:#cc1818;';
                msgEl.textContent = err.message || 'Failed to save author.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create & Link Author';
            });
        });
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(init, 2000);
    } else {
        document.addEventListener('DOMContentLoaded', function() { setTimeout(init, 2000); });
    }

    // Single-shot retry for when Gutenberg finally renders the panel
    var retries = 0;
    var retryInterval = setInterval(function() {
        retries++;
        var field = document.querySelector('[data-key="field_multi_author_relationship"]');
        // Only inject if the field exists AND our button is not already in the DOM
        if (field && !document.querySelector('.qc-add-author-btn')) {
            init();
        }
        if (retries >= 5) clearInterval(retryInterval);
    }, 3000);
})();