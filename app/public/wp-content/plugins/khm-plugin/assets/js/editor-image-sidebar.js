/**
 * KHM Image Generator — Gutenberg Plugin Sidebar
 *
 * Provides "Recommend Image Prompt" and "Generate Image" actions
 * directly inside the WordPress block editor for any post.
 *
 * Depends on: wp-plugins, wp-edit-post, wp-editor, wp-element,
 *             wp-components, wp-data, wp-api-fetch
 */
(function () {
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = (wp.editPost && wp.editPost.PluginSidebar) || (wp.editor && wp.editor.PluginSidebar);
    var PluginSidebarMoreMenuItem = (wp.editPost && wp.editPost.PluginSidebarMoreMenuItem) || (wp.editor && wp.editor.PluginSidebarMoreMenuItem);
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var createElement = wp.element.createElement;
    var Button = wp.components.Button;
    var TextareaControl = wp.components.TextareaControl;
    var SelectControl = wp.components.SelectControl;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var PanelBody = wp.components.PanelBody;
    var useSelect = wp.data.useSelect;
    var dispatch = wp.data.dispatch;

    if (!registerPlugin || !PluginSidebar) {
        return;
    }

    var SIDEBAR_NAME = 'khm-image-generator';
    var SIDEBAR_TITLE = 'AI Image Generator';

    var SIZE_OPTIONS = [
        { label: '1024 × 1024 (Square)', value: '1024x1024' },
        { label: '1792 × 1024 (Landscape)', value: '1792x1024' },
        { label: '1024 × 1792 (Portrait)', value: '1024x1792' },
    ];

    var QUALITY_OPTIONS = [
        { label: 'Standard', value: 'standard' },
        { label: 'HD', value: 'hd' },
    ];

    function KhmImageSidebar() {
        var postId = useSelect(function (select) {
            return select('core/editor').getCurrentPostId();
        });
        var postTitle = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('title') || '';
        });
        var postExcerpt = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('excerpt') || '';
        });

        var _useState1 = useState(''), recommendedPrompt = _useState1[0], setRecommendedPrompt = _useState1[1];
        var _useState2 = useState(''), editablePrompt = _useState2[0], setEditablePrompt = _useState2[1];
        var _useState3 = useState('1024x1024'), size = _useState3[0], setSize = _useState3[1];
        var _useState4 = useState('standard'), quality = _useState4[0], setQuality = _useState4[1];
        var _useState5 = useState(false), recommending = _useState5[0], setRecommending = _useState5[1];
        var _useState6 = useState(false), generating = _useState6[0], setGenerating = _useState6[1];
        var _useState7 = useState(null), notice = _useState7[0], setNotice = _useState7[1];
        var _useState8 = useState(null), generatedImage = _useState8[0], setGeneratedImage = _useState8[1];

        function buildPayload(extras) {
            return Object.assign({
                post_id: postId,
                title: postTitle,
                summary: postExcerpt,
                size: size,
                quality: quality,
                store_in_media_library: true,
                set_featured_image: postId > 0,
            }, extras || {});
        }

        function handleRecommend() {
            setRecommending(true);
            setNotice(null);
            wp.apiFetch({
                path: 'dual-gpt/v1/images/recommend',
                method: 'POST',
                data: buildPayload(),
            }).then(function (res) {
                var prompt = res.prompt || res.recommended_prompt || '';
                setRecommendedPrompt(prompt);
                setEditablePrompt(prompt);
                setNotice({ type: 'success', text: 'Prompt recommended — review and generate below.' });
            }).catch(function (err) {
                setNotice({ type: 'error', text: err.message || 'Failed to recommend image prompt.' });
            }).finally(function () {
                setRecommending(false);
            });
        }

        function handleGenerate() {
            setGenerating(true);
            setNotice(null);
            setGeneratedImage(null);
            wp.apiFetch({
                path: 'dual-gpt/v1/images/generate',
                method: 'POST',
                data: buildPayload({ prompt: editablePrompt }),
            }).then(function (res) {
                var url = res.url || (res.attachments && res.attachments[0] && res.attachments[0].url) || null;
                setGeneratedImage(url);
                var count = res.attachment_count || (res.attachments && res.attachments.length) || 1;
                setNotice({ type: 'success', text: 'Image generated (' + count + ' attachment' + (count === 1 ? '' : 's') + ')' + (postId > 0 ? ' and set as featured image.' : '.') });
                // Refresh the editor's featured image display
                if (postId > 0) {
                    dispatch('core').invalidateResolution('getEntityRecord', ['postType', 'post', postId]);
                }
            }).catch(function (err) {
                setNotice({ type: 'error', text: err.message || 'Failed to generate image.' });
            }).finally(function () {
                setGenerating(false);
            });
        }

        var isBusy = recommending || generating;

        return createElement(
            wp.element.Fragment,
            null,
            PluginSidebarMoreMenuItem
                ? createElement(PluginSidebarMoreMenuItem, { target: SIDEBAR_NAME }, SIDEBAR_TITLE)
                : null,
            createElement(
                PluginSidebar,
                { name: SIDEBAR_NAME, title: SIDEBAR_TITLE, icon: 'format-image' },
                createElement(
                    PanelBody,
                    { title: 'Image Prompt', initialOpen: true },

                    notice
                        ? createElement(
                            Notice,
                            {
                                status: notice.type,
                                isDismissible: true,
                                onRemove: function () { setNotice(null); },
                                style: { marginBottom: '12px' },
                            },
                            notice.text
                          )
                        : null,

                    createElement(
                        'p',
                        { style: { margin: '0 0 8px', fontSize: '12px', color: '#666' } },
                        'Uses the current post title and excerpt to generate a tailored image prompt.'
                    ),

                    createElement(
                        Button,
                        {
                            isPrimary: true,
                            onClick: handleRecommend,
                            disabled: isBusy,
                            style: { marginBottom: '12px' },
                        },
                        recommending ? createElement(Spinner, null) : 'Recommend Image Prompt'
                    ),

                    editablePrompt
                        ? createElement(TextareaControl, {
                            label: 'Image Prompt',
                            value: editablePrompt,
                            onChange: setEditablePrompt,
                            rows: 5,
                            help: 'Review and edit the prompt before generating.',
                          })
                        : null
                ),

                editablePrompt
                    ? createElement(
                        PanelBody,
                        { title: 'Generate', initialOpen: true },

                        createElement(SelectControl, {
                            label: 'Size',
                            value: size,
                            options: SIZE_OPTIONS,
                            onChange: setSize,
                        }),

                        createElement(SelectControl, {
                            label: 'Quality',
                            value: quality,
                            options: QUALITY_OPTIONS,
                            onChange: setQuality,
                        }),

                        createElement(
                            Button,
                            {
                                isPrimary: true,
                                onClick: handleGenerate,
                                disabled: isBusy || !editablePrompt.trim(),
                                style: { marginTop: '8px' },
                            },
                            generating ? createElement(Spinner, null) : 'Generate Image'
                        ),

                        generatedImage
                            ? createElement(
                                'img',
                                {
                                    src: generatedImage,
                                    alt: 'Generated image preview',
                                    style: { marginTop: '12px', maxWidth: '100%', borderRadius: '4px' },
                                }
                              )
                            : null
                      )
                    : null
            )
        );
    }

    registerPlugin(SIDEBAR_NAME, {
        render: KhmImageSidebar,
        icon: 'format-image',
    });
})();
