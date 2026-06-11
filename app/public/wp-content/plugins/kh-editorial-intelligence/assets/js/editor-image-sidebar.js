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

    var SHOW_PROMPT_EDITOR = window.khEditorialSettings && window.khEditorialSettings.show_prompt_editor;
    if (SHOW_PROMPT_EDITOR === undefined) SHOW_PROMPT_EDITOR = true; // fallback safe

    var SIZE_OPTIONS = [
        { label: '1024 × 1024 (Square)', value: '1024x1024' },
        { label: '1792 × 1024 (Landscape)', value: '1792x1024' },
        { label: '1024 × 1792 (Portrait)', value: '1024x1792' },
    ];

    var QUALITY_OPTIONS = [
        { label: 'Standard', value: 'standard' },
        { label: 'HD', value: 'hd' },
    ];

    var PROVIDER_OPTIONS = [
        { label: 'OpenAI (DALL-E 3)', value: 'openai' },
        { label: 'Google (Imagen)', value: 'google' },
        { label: 'Open Router', value: 'openrouter' },
    ];

    var MODEL_OPTIONS = {
        openrouter: [
            { label: 'FLUX.2 Pro (best quality)', value: 'black-forest-labs/flux.2-pro' },
            { label: 'FLUX.2 Flex (balanced)', value: 'black-forest-labs/flux.2-flex' },
            { label: 'FLUX.2 Max (top tier)', value: 'black-forest-labs/flux.2-max' },
            { label: 'Seedream 4.5 (ByteDance)', value: 'bytedance-seed/seedream-4.5' },
            { label: 'Recraft V4', value: 'recraft/recraft-v4' },
            { label: 'Recraft V4.1', value: 'recraft/recraft-v4.1' },
        ],
    };

    var ART_STYLES = [
        { label: 'Risograph Print', value: 'risograph_print' },
        { label: 'Bauhaus Woodblock', value: 'bauhaus_woodblock' },
        { label: 'Chalk Matte Vector', value: 'chalk_matte_vector' },
        { label: 'Linocut Monochromatic', value: 'linocut_mono' },
        { label: 'Knitted Yarn Craft', value: 'knitted_yarn_craft' },
        { label: 'Origami', value: 'origami' },
        { label: 'Vintage Playroom', value: 'vintage_playroom' },
        { label: 'Pop Art', value: 'pop_art' },
        { label: '1950s Googie', value: 'googie' },
        { label: 'Photorealistic Steampunk', value: 'steampunk' },
    ];

    var COLOUR_PALETTES = [
        { label: 'Terracotta & Indigo', value: 'terracotta_indigo' },
        { label: 'Birch & Cobalt', value: 'birch_cobalt' },
        { label: 'Obsidian & Cyan', value: 'obsidian_cyan' },
        { label: 'Midnight & Gold', value: 'midnight_gold' },
        { label: 'Oatmeal & Sage', value: 'oatmeal_sage' },
        { label: 'Alabaster & Terracotta', value: 'alabaster_terracotta' },
        { label: 'Primary & Pine', value: 'primary_pine' },
        { label: 'Crimson & Cyan', value: 'crimson_cyan' },
        { label: 'Avocado & Turquoise', value: 'avocado_turquoise' },
        { label: 'Verdigris & Brass', value: 'verdigris_brass' },
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
        var _useState9 = useState(null), generatedImageId = _useState9[0], setGeneratedImageId = _useState9[1];
        var _useState10 = useState('openrouter'), provider = _useState10[0], setProvider = _useState10[1];
        var _useState11 = useState('black-forest-labs/flux.2-pro'), model = _useState11[0], setModel = _useState11[1];
        var _useState12 = useState('risograph_print'), artStyle = _useState12[0], setArtStyle = _useState12[1];
        var _useState13 = useState('terracotta_indigo'), colourPalette = _useState13[0], setColourPalette = _useState13[1];

        function buildPayload(extras) {
            return Object.assign({
                post_id: postId,
                title: postTitle,
                summary: postExcerpt,
                size: size,
                quality: quality,
                provider: provider,
                model: model,
                preset_key: artStyle,
                colour_palette: colourPalette,
                store_in_media_library: true,
                set_featured_image: false,
            }, extras || {});
        }

        function handleRecommend() {
            setRecommending(true);
            setNotice(null);
            wp.apiFetch({
                path: 'editorial/v1/images/recommend',
                method: 'POST',
                data: buildPayload(),
            }).then(function (res) {
                var prompt = res.prompt || res.recommended_prompt || '';
                setRecommendedPrompt(prompt);
                setEditablePrompt(prompt);
                if (!SHOW_PROMPT_EDITOR) {
                    // Auto-generate immediately if prompt editor is hidden
                    handleGenerateWithPrompt(prompt);
                } else {
                    setNotice({ type: 'success', text: 'Prompt recommended — review and generate below.' });
                    setRecommending(false);
                }
            }).catch(function (err) {
                setNotice({ type: 'error', text: err.message || 'Failed to recommend image prompt.' });
                setRecommending(false);
            });
        }

        function handleGenerateWithPrompt(prompt) {
            setGenerating(true);
            setNotice(null);
            setGeneratedImage(null);
            setGeneratedImageId(null);
            wp.apiFetch({
                path: 'editorial/v1/images/generate',
                method: 'POST',
                data: buildPayload({ prompt: prompt }),
            }).then(function (res) {
                if (!res.success) {
                    setNotice({ type: 'error', text: res.message || 'Failed to generate image.' });
                    setGenerating(false);
                    return;
                }
                var url = res.url || (res.attachments && res.attachments[0] && res.attachments[0].url) || null;
                var attId = res.attachments && res.attachments[0] && res.attachments[0].id || null;
                setGeneratedImage(url);
                setGeneratedImageId(attId);
                setNotice({ type: 'success', text: 'Image generated.' });
                setRecommending(false);
                setGenerating(false);
            }).catch(function (err) {
                setNotice({ type: 'error', text: err.message || 'Failed to generate image.' });
                setRecommending(false);
                setGenerating(false);
            });
        }

        function handleGenerate() {
            if (!SHOW_PROMPT_EDITOR) return;
            setGenerating(true);
            setNotice(null);
            setGeneratedImage(null);
            setGeneratedImageId(null);
            wp.apiFetch({
                path: 'editorial/v1/images/generate',
                method: 'POST',
                data: buildPayload({ prompt: editablePrompt }),
            }).then(function (res) {
                if (!res.success) {
                    setNotice({ type: 'error', text: res.message || 'Failed to generate image.' });
                    setGenerating(false);
                    return;
                }
                var url = res.url || (res.attachments && res.attachments[0] && res.attachments[0].url) || null;
                var attId = res.attachments && res.attachments[0] && res.attachments[0].id || null;
                setGeneratedImage(url);
                setGeneratedImageId(attId);
                setNotice({ type: 'success', text: 'Image generated.' });
            }).catch(function (err) {
                setNotice({ type: 'error', text: err.message || 'Failed to generate image.' });
            }).finally(function () {
                setGenerating(false);
            });
        }

        function handleSetFeatured() {
            if (!generatedImageId || !postId) return;
            wp.data.dispatch('core').editEntityRecord('postType', 'post', postId, {
                featured_media: generatedImageId,
            });
            wp.data.dispatch('core').saveEntityRecord('postType', 'post', postId);
            setNotice({ type: 'success', text: 'Set as featured image.' });
        }

        var isBusy = recommending || generating;

        return createElement(
            wp.element.Fragment,
            null,
            createElement(
                PluginSidebar,
                { name: SIDEBAR_NAME, title: SIDEBAR_TITLE, icon: 'format-image' },
                createElement(
                    PanelBody,
                    { title: 'Generate', initialOpen: true },

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

                    createElement(SelectControl, {
                        label: 'Art Style',
                        value: artStyle,
                        options: ART_STYLES,
                        onChange: setArtStyle,
                    }),

                    createElement(SelectControl, {
                        label: 'Colour Palette',
                        value: colourPalette,
                        options: COLOUR_PALETTES,
                        onChange: setColourPalette,
                    }),

                    createElement(SelectControl, {
                        label: 'Provider',
                        value: provider,
                        options: PROVIDER_OPTIONS,
                        onChange: function (val) {
                            setProvider(val);
                            if (val === 'openrouter') {
                                setModel('black-forest-labs/flux.2-pro');
                            } else {
                                setModel('');
                            }
                        },
                    }),

                    provider === 'openrouter'
                        ? createElement(SelectControl, {
                            label: 'Model',
                            value: model,
                            options: MODEL_OPTIONS.openrouter,
                            onChange: setModel,
                          })
                        : null,

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
                            onClick: handleRecommend,
                            disabled: isBusy,
                            style: { marginTop: '8px', marginBottom: '8px' },
                        },
                        recommending ? createElement(Spinner, null) : (SHOW_PROMPT_EDITOR ? 'Recommend Image Prompt' : 'Generate Image')
                    ),

                    SHOW_PROMPT_EDITOR && editablePrompt
                        ? createElement(TextareaControl, {
                            label: 'Image Prompt',
                            value: editablePrompt,
                            onChange: setEditablePrompt,
                            rows: 4,
                          })
                        : null,

                    SHOW_PROMPT_EDITOR
                        ? createElement(
                            Button,
                            {
                                isPrimary: true,
                                onClick: handleGenerate,
                                disabled: isBusy || !editablePrompt.trim(),
                                style: { marginTop: '8px' },
                            },
                            generating ? createElement(Spinner, null) : 'Generate Image'
                        )
                        : null,

                    generatedImage
                        ? createElement(
                            'div',
                            { style: { marginTop: '12px' } },
                            createElement(
                                'img',
                                {
                                    src: generatedImage,
                                    alt: 'Generated image preview',
                                    style: { maxWidth: '100%', borderRadius: '4px', display: 'block' },
                                }
                            ),
                            postId && generatedImageId
                                ? createElement(
                                    Button,
                                    {
                                        isPrimary: true,
                                        onClick: handleSetFeatured,
                                        style: { marginTop: '8px' },
                                    },
                                    'Set as Featured Image'
                                  )
                                : null
                          )
                        : null

                )
            )
        );
    }

    registerPlugin(SIDEBAR_NAME, {
        render: KhmImageSidebar,
        icon: 'format-image',
    });
})();
