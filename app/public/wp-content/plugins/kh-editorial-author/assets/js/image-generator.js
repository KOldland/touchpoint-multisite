(function() {
    var __ = wp.i18n.__;
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var select = wp.data.select;
    var dispatch = wp.data.dispatch;
    var apiFetch = wp.apiFetch;
    var useState = wp.element.useState;

    var ART_STYLES = [
        { label: 'Layered Editorial Cutout', value: 'layered_editorial_cutout' },
    ];

    function ImageGenPanel() {
        var promptState = useState('');
        var prompt = promptState[0], setPrompt = promptState[1];
        var providerState = useState('openai');
        var provider = providerState[0], setProvider = providerState[1];
        var artStyleState = useState('layered_editorial_cutout');
        var artStyle = artStyleState[0], setArtStyle = artStyleState[1];
        var autoPromptState = useState(true);
        var autoPrompt = autoPromptState[0], setAutoPrompt = autoPromptState[1];
        var loadingState = useState(false);
        var loading = loadingState[0], setLoading = loadingState[1];
        var resultState = useState(null);
        var result = resultState[0], setResult = resultState[1];
        var errorState = useState(null);
        var error = errorState[0], setError = errorState[1];
        var insertingState = useState(false);
        var inserting = insertingState[0], setInserting = insertingState[1];

        var postId = select('core/editor').getCurrentPostId();
        var postTitle = select('core/editor').getEditedPostAttribute('title');
        var postExcerpt = select('core/editor').getEditedPostAttribute('excerpt');
        var postContent = select('core/editor').getEditedPostAttribute('content');
        var postCategories = select('core/editor').getEditedPostAttribute('categories');

        function buildAutoPrompt() {
            var parts = [];
            if (postTitle) parts.push('Create a publication-quality editorial image for the article "' + postTitle + '".');
            parts.push('Art direction: Paper-cut editorial illustration with geometric forms and tactile texture. Brand palette: Warm kraft beige, muted teal, deep blue, orange, yellow.');
            if (postExcerpt) parts.push('Article summary: ' + postExcerpt);
            parts.push('Do NOT use photorealism, glossy 3D render, or cluttered backgrounds.');
            return parts.join('\n\n');
        }

        function handleGenerate() {
            var finalPrompt = autoPrompt ? buildAutoPrompt() : prompt.trim();
            if (!finalPrompt && !postTitle) {
                setError(__('Add a post title or toggle off auto-prompt and write your own.', 'kh-editorial-author'));
                return;
            }

            setLoading(true);
            setError(null);
            setResult(null);

            apiFetch({
                path: '/editorial/v1/images/generate',
                method: 'POST',
                data: {
                    prompt: finalPrompt || 'Editorial illustration for: ' + postTitle,
                    provider: provider,
                    title: postTitle,
                    summary: postExcerpt,
                    preset_key: artStyle,
                    store_in_media_library: true,
                    post_id: postId
                }
            }).then(function(res) {
                if (res.success) {
                    setResult(res);
                } else {
                    setError(res.message || __('Generation failed.', 'kh-editorial-author'));
                }
                setLoading(false);
            }).catch(function(err) {
                setError(err.message || __('API request failed.', 'kh-editorial-author'));
                setLoading(false);
            });
        }

        function handleInsertImage() {
            if (!result || !result.attachments || !result.attachments.length) return;
            setInserting(true);
            var attachment = result.attachments[0];
            dispatch('core/editor').editPost({ featured_media: attachment.id });
            if (result.image_url) {
                var content = select('core/editor').getEditedPostAttribute('content');
                var imgBlock = '<!-- wp:image {"id":' + attachment.id + ',"sizeSlug":"full","linkDestination":"media"} -->\n<figure class="wp-block-image size-full"><img src="' + result.image_url + '" alt="' + (result.alt_text || '') + '" class="wp-image-' + attachment.id + '"/></figure>\n<!-- /wp:image -->\n\n';
                dispatch('core/editor').editPost({ content: content + imgBlock });
            }
            setInserting(false);
        }

        return wp.element.createElement(PluginDocumentSettingPanel, {
            name: 'image-generator',
            title: __('Generate Image', 'kh-editorial-author'),
            className: 'image-generator-panel',
            initialOpen: false
        },
            wp.element.createElement(SelectControl, {
                label: __('Art Style', 'kh-editorial-author'),
                value: artStyle,
                onChange: setArtStyle,
                options: ART_STYLES
            }),
            wp.element.createElement(SelectControl, {
                label: __('Provider', 'kh-editorial-author'),
                value: provider,
                onChange: setProvider,
                options: [
                    { label: 'OpenAI (DALL-E)', value: 'openai' },
                    { label: 'Google (Imagen)', value: 'google' }
                ]
            }),
            wp.element.createElement(ToggleControl, {
                label: __('Auto-generate prompt from article', 'kh-editorial-author'),
                checked: autoPrompt,
                onChange: function(val) { setAutoPrompt(val); if (val) setPrompt(''); }
            }),
            autoPrompt && postTitle ? wp.element.createElement('div', {
                style: {
                    background: '#f0f6fc',
                    border: '1px solid #c5d9ed',
                    borderRadius: '4px',
                    padding: '10px',
                    fontSize: '12px',
                    color: '#1d2327',
                    marginBottom: '12px',
                    lineHeight: '1.5',
                    maxHeight: '120px',
                    overflowY: 'auto',
                    whiteSpace: 'pre-wrap'
                }
            }, buildAutoPrompt()) : null,
            !autoPrompt ? wp.element.createElement(TextareaControl, {
                label: __('Custom Prompt', 'kh-editorial-author'),
                value: prompt,
                onChange: setPrompt,
                placeholder: 'Describe the image you want to generate...',
                rows: 4
            }) : null,
            wp.element.createElement('div', { style: { display: 'flex', gap: '8px', marginBottom: '12px' } },
                wp.element.createElement(Button, {
                    isPrimary: true,
                    disabled: loading,
                    onClick: handleGenerate,
                    style: { flex: 1, justifyContent: 'center' }
                },
                    loading ? wp.element.createElement(Spinner, null) : __('Generate', 'kh-editorial-author')
                )
            ),
            error ? wp.element.createElement(Notice, { status: 'error', isDismissible: true, onRemove: function() { setError(null); } }, error) : null,
            result && result.image_url ? wp.element.createElement('div', { style: { marginTop: '8px' } },
                wp.element.createElement('div', { style: { borderRadius: '4px', overflow: 'hidden', marginBottom: '8px', border: '1px solid #e0e0e0' } },
                    wp.element.createElement('img', {
                        src: result.image_url,
                        alt: result.alt_text || '',
                        style: { width: '100%', height: 'auto', display: 'block' }
                    })
                ),
                result.alt_text ? wp.element.createElement('p', { style: { fontSize: '11px', color: '#666', fontStyle: 'italic', margin: '4px 0' } },
                    'Alt: ' + result.alt_text
                ) : null,
                wp.element.createElement(Button, {
                    isPrimary: true,
                    disabled: inserting || !result.attachments || !result.attachments.length,
                    onClick: handleInsertImage,
                    style: { width: '100%', justifyContent: 'center' }
                },
                    inserting ? wp.element.createElement(Spinner, null) : __('Set as Featured & Insert in Post', 'kh-editorial-author')
                )
            ) : null
        );
    }

    registerPlugin('image-generator', {
        icon: 'format-image',
        render: ImageGenPanel
    });
})();
                onChange: setPrompt,
                placeholder: postTitle ? 'Leave blank to auto-generate from title' : 'Describe the image you want...'
            }),
            wp.element.createElement(Button, {
                isPrimary: true,
                disabled: loading,
                onClick: handleGenerate,
                style: { width: '100%', justifyContent: 'center', marginBottom: '12px' }
            },
                loading ? wp.element.createElement(Spinner, null) : __('Generate Image', 'kh-editorial-author')
            ),
            error ? wp.element.createElement(Notice, { status: 'error', isDismissible: true, onRemove: function() { setError(null); } }, error) : null,
            result && result.image_url ? wp.element.createElement('div', { style: { marginTop: '8px' } },
                wp.element.createElement('div', { style: { borderRadius: '4px', overflow: 'hidden', marginBottom: '8px', border: '1px solid #e0e0e0' } },
                    wp.element.createElement('img', {
                        src: result.image_url,
                        alt: result.alt_text || '',
                        style: { width: '100%', height: 'auto', display: 'block' }
                    })
                ),
                wp.element.createElement(Button, {
                    isPrimary: true,
                    disabled: inserting || !result.attachments,
                    onClick: handleInsertImage,
                    style: { width: '100%', justifyContent: 'center' }
                },
                    inserting ? wp.element.createElement(Spinner, null) : __('Set as Featured & Insert in Post', 'kh-editorial-author')
                )
            ) : null
        );
    }

    registerPlugin('image-generator', {
        icon: 'format-image',
        render: ImageGenPanel
    });
})();