(function(root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
        return;
    }

    root.KHSmmaImageUpload = factory();
})(typeof self !== 'undefined' ? self : this, function() {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function inferOrientation(width, height) {
        var w = Number(width || 0);
        var h = Number(height || 0);
        if (w === h) {
            return 'square';
        }
        return w > h ? 'landscape' : 'portrait';
    }

    function normalizeAttachment(attachment) {
        var raw = attachment && attachment.attributes ? attachment.attributes : attachment || {};
        var width = Number(raw.width || 0);
        var height = Number(raw.height || 0);
        var ratio = width > 0 && height > 0 ? (width / height).toFixed(4) : '1.0000';

        return {
            id: String(raw.id || raw.image_id || ''),
            title: String(raw.title || raw.filename || raw.image_id || 'Selected image'),
            thumbnailUrl: String(raw.thumbnail_url || raw.url || ''),
            width: width,
            height: height,
            orientation: String(raw.orientation || inferOrientation(width, height)),
            aspectRatio: String(raw.aspect_ratio || ratio)
        };
    }

    function buildSelectedImagesMarkup(images) {
        if (!images.length) {
            return '<p class="kh-smma-images-empty">No images selected yet.</p>';
        }

        return images.map(function(image, index) {
            return [
                '<article class="kh-smma-image-card" draggable="true" data-image-id="' + escapeHtml(image.id) + '" data-image-index="' + index + '">',
                '<div class="kh-smma-image-card__thumb">' +
                    (image.thumbnailUrl ? '<img src="' + escapeHtml(image.thumbnailUrl) + '" alt="' + escapeHtml(image.title) + '">' : '<span>No preview</span>') +
                '</div>',
                '<div class="kh-smma-image-card__meta">',
                '<h3>' + escapeHtml(image.title) + '</h3>',
                '<dl>',
                '<div><dt>Orientation</dt><dd>' + escapeHtml(image.orientation) + '</dd></div>',
                '<div><dt>Size</dt><dd>' + escapeHtml(image.width + '×' + image.height) + '</dd></div>',
                '<div><dt>Aspect</dt><dd>' + escapeHtml(image.aspectRatio) + '</dd></div>',
                '</dl>',
                '</div>',
                '</article>'
            ].join('');
        }).join('');
    }

    function buildLayoutMarkup(layouts, selectedImages, activeLayoutId) {
        if (!Array.isArray(layouts) || !layouts.length) {
            return '<p class="kh-smma-images-empty">No layout recommendations available.</p>';
        }

        return layouts.map(function(layout) {
            var slotCount = Array.isArray(layout.slots) ? layout.slots.length : 0;
            var active = String(activeLayoutId || '') === String(layout.layout_id || '') ? ' kh-smma-layout-card--active' : '';
            var disabled = selectedImages.length ? '' : ' disabled';

            return [
                '<article class="kh-smma-layout-card' + active + '" data-layout-id="' + escapeHtml(layout.layout_id) + '" tabindex="0" aria-label="Layout ' + escapeHtml(layout.layout_id) + '">',
                '<div class="kh-smma-layout-card__thumb">' +
                    (layout.thumbnail ? '<img src="' + escapeHtml(layout.thumbnail) + '" alt="' + escapeHtml(layout.layout_id) + ' thumbnail">' : '<span>No thumbnail</span>') +
                '</div>',
                '<div class="kh-smma-layout-card__body">',
                '<h3>' + escapeHtml(layout.layout_id) + '</h3>',
                '<p>Intent ' + escapeHtml(layout.intent || 'demo') + ' · Score ' + escapeHtml(layout.score || '0') + '</p>',
                '<p>' + slotCount + ' slots · ' + escapeHtml((layout.canvas && layout.canvas.width) || 0) + '×' + escapeHtml((layout.canvas && layout.canvas.height) || 0) + '</p>',
                '<button type="button" class="button kh-smma-layout-preview-btn" data-layout-id="' + escapeHtml(layout.layout_id) + '"' + disabled + '>Preview</button>',
                '</div>',
                '</article>'
            ].join('');
        }).join('');
    }

    function buildComposePayload(state) {
        var layout = state.activeLayout || {};
        var images = (state.selectedImages || []).map(function(image, index) {
            return {
                image_id: image.id,
                slot_index: index
            };
        });

        return {
            reference_id: String(state.referenceId || ''),
            post_id: Number(state.postId || 0),
            layout_id: String(layout.layout_id || ''),
            preview_url: String(state.previewUrl || ''),
            composed_image_id: String(state.composedImageId || ''),
            mapping: images
        };
    }

    function buildPreviewState(layout, selectedImages, composeFixture) {
        var fixture = composeFixture || {};
        var slots = Array.isArray(layout && layout.slots) ? layout.slots : [];
        var mapping = slots.map(function(slot, index) {
            return {
                slot_index: slot.slot_index,
                image_id: selectedImages[index] ? selectedImages[index].id : '',
                x: slot.x,
                y: slot.y,
                w: slot.w,
                h: slot.h
            };
        });

        return {
            layoutId: String(layout && layout.layout_id ? layout.layout_id : ''),
            previewUrl: String(fixture.preview_url || ''),
            composedImageId: String(fixture.composed_image_id || ''),
            mapping: mapping
        };
    }

    function renderPreviewMarkup(previewState, selectedImages) {
        if (!previewState || !previewState.layoutId) {
            return {
                frame: '<p class="kh-smma-images-empty">Choose a layout to preview the compose.</p>',
                meta: ''
            };
        }

        var mapped = previewState.mapping.map(function(slot) {
            var image = selectedImages.find(function(item) {
                return item.id === slot.image_id;
            });
            return '<li>Slot ' + escapeHtml(slot.slot_index) + ': ' + escapeHtml(image ? image.title : 'Unassigned') + '</li>';
        }).join('');

        return {
            frame: [
                '<div class="kh-smma-preview-card">',
                previewState.previewUrl ? '<img src="' + escapeHtml(previewState.previewUrl) + '" alt="Composed preview">' : '<div class="kh-smma-preview-card__placeholder">No preview image</div>',
                '</div>'
            ].join(''),
            meta: [
                '<p><strong>Layout:</strong> ' + escapeHtml(previewState.layoutId) + '</p>',
                '<p><strong>Composed image:</strong> ' + escapeHtml(previewState.composedImageId || 'pending') + '</p>',
                '<ul>' + mapped + '</ul>'
            ].join('')
        };
    }

    function reorderImages(images, fromIndex, toIndex) {
        var next = images.slice();
        var moved = next.splice(fromIndex, 1)[0];
        next.splice(toIndex, 0, moved);
        return next;
    }

    function initBrowser(config) {
        if (typeof window === 'undefined' || typeof document === 'undefined') {
            return;
        }

        var settings = config || window.khSmmaImageUpload;
        if (!settings) {
            return;
        }

        var selectedEl = document.getElementById('kh-smma-images-selected');
        var layoutsEl = document.getElementById('kh-smma-images-layouts');
        var previewEl = document.getElementById('kh-smma-images-preview-frame');
        var previewMetaEl = document.getElementById('kh-smma-images-preview-meta');
        var statusEl = document.getElementById('kh-smma-images-status');
        var referenceEl = document.getElementById('kh-smma-images-reference');
        var openButton = document.getElementById('kh-smma-images-open-media');
        var refreshButton = document.getElementById('kh-smma-images-load-layouts');
        var saveButton = document.getElementById('kh-smma-images-save-compose');

        if (!selectedEl || !layoutsEl || !previewEl || !previewMetaEl || !statusEl || !referenceEl) {
            return;
        }

        var state = {
            selectedImages: [],
            layouts: [],
            activeLayout: null,
            previewUrl: settings.savedCompose && settings.savedCompose.preview_url ? settings.savedCompose.preview_url : '',
            composedImageId: settings.savedCompose && settings.savedCompose.composed_image_id ? settings.savedCompose.composed_image_id : '',
            referenceId: referenceEl.value || settings.referenceId || '',
            postId: 0
        };

        function setStatus(message, isError) {
            statusEl.textContent = message || '';
            statusEl.className = 'kh-smma-images-status' + (isError ? ' kh-smma-images-status--error' : '');
        }

        function renderSelected() {
            selectedEl.innerHTML = buildSelectedImagesMarkup(state.selectedImages);
        }

        function renderLayouts() {
            layoutsEl.innerHTML = buildLayoutMarkup(state.layouts, state.selectedImages, state.activeLayout && state.activeLayout.layout_id);
        }

        function renderPreview() {
            var previewState = buildPreviewState(state.activeLayout || {}, state.selectedImages, {
                preview_url: state.previewUrl,
                composed_image_id: state.composedImageId
            });
            var markup = renderPreviewMarkup(previewState, state.selectedImages);
            previewEl.innerHTML = markup.frame;
            previewMetaEl.innerHTML = markup.meta;
        }

        function fetchFixture(file) {
            return window.fetch(settings.fixtureEndpoint + '&file=' + encodeURIComponent(file), {
                credentials: 'same-origin'
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error(settings.messages.loadError);
                }
                return response.json();
            });
        }

        function loadSavedCompose() {
            var url = settings.composeEndpoint + '?reference_id=' + encodeURIComponent(state.referenceId);
            return window.fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': settings.nonce
                }
            }).then(function(response) {
                if (!response.ok) {
                    return null;
                }
                return response.json();
            }).then(function(result) {
                if (result && Array.isArray(result.mapping) && result.mapping.length) {
                    state.previewUrl = result.preview_url || state.previewUrl;
                }
            }).catch(function() {
                return null;
            });
        }

        function loadLayouts() {
            return fetchFixture('layouts_response.json').then(function(layouts) {
                state.layouts = Array.isArray(layouts) ? layouts : [];
                if (!state.activeLayout && state.layouts.length) {
                    state.activeLayout = state.layouts[0];
                }
                renderLayouts();
                renderPreview();
            }).catch(function(error) {
                setStatus(error.message || settings.messages.loadError, true);
            });
        }

        function saveCompose() {
            if (!state.activeLayout) {
                setStatus('Choose a layout before saving.', true);
                return Promise.resolve();
            }

            var payload = buildComposePayload(state);
            return window.fetch(settings.composeEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': settings.nonce
                },
                body: JSON.stringify(payload)
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error(settings.messages.saveError);
                }
                return response.json();
            }).then(function(result) {
                state.previewUrl = result.preview_url || state.previewUrl;
                state.composedImageId = result.composed_image_id || state.composedImageId;
                renderPreview();
                setStatus(settings.messages.saveSuccess, false);
            }).catch(function(error) {
                setStatus(error.message || settings.messages.saveError, true);
            });
        }

        if (openButton) {
            openButton.addEventListener('click', function() {
                if (!window.wp || !window.wp.media) {
                    setStatus('WordPress media library is unavailable.', true);
                    return;
                }

                var frame = window.wp.media({
                    title: settings.messages.openUploader,
                    library: { type: 'image' },
                    multiple: true
                });

                frame.on('select', function() {
                    state.selectedImages = frame.state().get('selection').map(function(model) {
                        return normalizeAttachment(model.toJSON ? model.toJSON() : model);
                    });
                    renderSelected();
                    renderLayouts();
                    renderPreview();
                });

                frame.open();
            });
        }

        if (refreshButton) {
            refreshButton.addEventListener('click', function() {
                state.referenceId = referenceEl.value || settings.referenceId || '';
                loadLayouts();
            });
        }

        if (saveButton) {
            saveButton.addEventListener('click', function() {
                state.referenceId = referenceEl.value || settings.referenceId || '';
                saveCompose();
            });
        }

        layoutsEl.addEventListener('click', function(event) {
            var button = event.target.closest('.kh-smma-layout-preview-btn');
            if (!button) {
                return;
            }
            var layoutId = button.getAttribute('data-layout-id');
            state.activeLayout = state.layouts.find(function(item) {
                return String(item.layout_id) === String(layoutId);
            }) || null;

            fetchFixture('compose_response.json').then(function(composeFixture) {
                state.previewUrl = composeFixture.preview_url || '';
                state.composedImageId = composeFixture.composed_image_id || '';
                renderLayouts();
                renderPreview();
                setStatus('Preview ready for ' + layoutId + '.', false);
            }).catch(function(error) {
                setStatus(error.message || settings.messages.loadError, true);
            });
        });

        var dragIndex = null;
        selectedEl.addEventListener('dragstart', function(event) {
            var card = event.target.closest('.kh-smma-image-card');
            if (!card) {
                return;
            }
            dragIndex = Number(card.getAttribute('data-image-index'));
        });
        selectedEl.addEventListener('dragover', function(event) {
            event.preventDefault();
        });
        selectedEl.addEventListener('drop', function(event) {
            event.preventDefault();
            var card = event.target.closest('.kh-smma-image-card');
            if (!card || dragIndex === null) {
                return;
            }
            var dropIndex = Number(card.getAttribute('data-image-index'));
            state.selectedImages = reorderImages(state.selectedImages, dragIndex, dropIndex);
            dragIndex = null;
            renderSelected();
            renderPreview();
        });

        renderSelected();
        renderPreview();
        loadSavedCompose().finally(loadLayouts);
    }

    return {
        inferOrientation: inferOrientation,
        normalizeAttachment: normalizeAttachment,
        buildSelectedImagesMarkup: buildSelectedImagesMarkup,
        buildLayoutMarkup: buildLayoutMarkup,
        buildComposePayload: buildComposePayload,
        buildPreviewState: buildPreviewState,
        renderPreviewMarkup: renderPreviewMarkup,
        reorderImages: reorderImages,
        initBrowser: initBrowser
    };
});

if (typeof window !== 'undefined' && window.KHSmmaImageUpload) {
    window.KHSmmaImageUpload.initBrowser();
}
