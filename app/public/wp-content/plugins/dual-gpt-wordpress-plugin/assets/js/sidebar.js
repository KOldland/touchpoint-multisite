/**
 * Dual-GPT Gutenberg Sidebar
 */

const { registerPlugin } = wp.plugins;
const { PluginSidebar } = wp.editPost;
const {
    PanelBody,
    TextareaControl,
    TextControl,
    Button,
    Spinner,
    ToggleControl,
    SelectControl,
    Notice,
} = wp.components;
const { useState, useEffect } = wp.element;
const { useSelect, useDispatch } = wp.data;
const { apiFetch } = wp;

const StatusMessage = ({ tone = 'info', title, children }) => (
    <div className={`dual-gpt-message dual-gpt-message-${tone}`}>
        {title ? <strong>{title}</strong> : null}
        {children ? <div>{children}</div> : null}
    </div>
);

const DualGPTSidebar = () => {
    const [researchPrompt, setResearchPrompt] = useState('');
    const [authorMode, setAuthorMode] = useState('draft');
    const [frameworkBriefId, setFrameworkBriefId] = useState('');
    const [plannerSessionId, setPlannerSessionId] = useState('');
    const [authorInstructions, setAuthorInstructions] = useState('');
    const [authorCoreSettings, setAuthorCoreSettings] = useState({
        industry_focus: dualGptData?.coreSettings?.industry_focus || 'General',
        audience_tier: dualGptData?.coreSettings?.audience_tier || 'General',
        risk_tolerance: dualGptData?.coreSettings?.risk_tolerance || 'Moderate',
        brand_profile: dualGptData?.coreSettings?.brand_profile || 'Brand A (FSI)',
    });
    const [researchLoading, setResearchLoading] = useState(false);
    const [authorLoading, setAuthorLoading] = useState(false);
    const [researchResults, setResearchResults] = useState('');
    const [authorResults, setAuthorResults] = useState('');
    const [researchError, setResearchError] = useState('');
    const [authorError, setAuthorError] = useState('');
    const [researchJobId, setResearchJobId] = useState(null);
    const [authorJobId, setAuthorJobId] = useState(null);
    const [authorBlocks, setAuthorBlocks] = useState([]);
    const [authorAbstract, setAuthorAbstract] = useState(null);
    const [authorWarnings, setAuthorWarnings] = useState([]);
    const [authorValidationErrors, setAuthorValidationErrors] = useState([]);

    const [imageConfig, setImageConfig] = useState(null);
    const [imageConfigLoading, setImageConfigLoading] = useState(true);
    const [imageConfigError, setImageConfigError] = useState('');
    const [imageRecommendationLoading, setImageRecommendationLoading] = useState(false);
    const [imageGenerateLoading, setImageGenerateLoading] = useState(false);
    const [imageError, setImageError] = useState('');
    const [imageNotice, setImageNotice] = useState('');
    const [imageRecommendation, setImageRecommendation] = useState(null);
    const [imageResult, setImageResult] = useState(null);
    const [imagePrompt, setImagePrompt] = useState('');
    const [imageNegativePrompt, setImageNegativePrompt] = useState('');
    const [imageAltText, setImageAltText] = useState('');
    const [imageCaption, setImageCaption] = useState('');
    const [imageTextInImage, setImageTextInImage] = useState('');
    const [imageEditorialAccuracy, setImageEditorialAccuracy] = useState(false);
    const [imageSetFeatured, setImageSetFeatured] = useState(true);
    const [imageStoreMedia, setImageStoreMedia] = useState(true);
    const [imageAspectRatio, setImageAspectRatio] = useState('16:9');
    const [imageSize, setImageSize] = useState('4K');
    const [imageProvider, setImageProvider] = useState('google');
    const [imagePresetKey, setImagePresetKey] = useState('layered_editorial_cutout');
    const [imageAdditionalKeywords, setImageAdditionalKeywords] = useState('');

    const { insertBlocks } = useDispatch('core/block-editor');
    const { editPost } = useDispatch('core/editor');
    const { createNotice } = useDispatch('core/notices');

    const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);
    const draftContent = useSelect((select) => select('core/editor').getEditedPostContent(), []);
    const postTitle = useSelect((select) => select('core/editor').getEditedPostAttribute('title') || '', []);
    const postExcerpt = useSelect((select) => select('core/editor').getEditedPostAttribute('excerpt') || '', []);

    useEffect(() => {
        const loadImageConfig = async () => {
            setImageConfigLoading(true);
            setImageConfigError('');

            try {
                const response = await apiFetch({
                    path: 'dual-gpt/v1/images/config',
                    method: 'GET',
                });

                setImageConfig(response);
                setImageProvider(response.image_provider || 'google');
                setImagePresetKey(response.default_preset_key || 'layered_editorial_cutout');
                setImageAspectRatio(response.house_style?.aspect_ratio || '16:9');
                setImageStoreMedia(!!response.workflow?.auto_store_media);
                setImageSetFeatured(!!response.workflow?.allow_featured_image_replace);
            } catch (error) {
                setImageConfigError(error?.message || 'Failed to load image settings.');
            } finally {
                setImageConfigLoading(false);
            }
        };

        loadImageConfig();
    }, []);

    const handleResearchSubmit = async () => {
        if (!researchPrompt.trim()) {
            setResearchError('Please enter a research prompt');
            return;
        }

        setResearchLoading(true);
        setResearchError('');
        setResearchResults('');

        try {
            const sessionResponse = await apiFetch({
                path: 'dual-gpt/v1/sessions',
                method: 'POST',
                data: {
                    role: 'research',
                    title: 'Research Session - ' + new Date().toLocaleString(),
                },
            });

            const jobResponse = await apiFetch({
                path: 'dual-gpt/v1/jobs',
                method: 'POST',
                data: {
                    session_id: sessionResponse.session_id,
                    prompt: researchPrompt,
                    model: 'gpt-4',
                },
            });

            setResearchJobId(jobResponse.job_id);
            setResearchResults('Job submitted successfully. Processing...');
            pollJobStatus(jobResponse.job_id, 'research');
        } catch (error) {
            let errorMessage = 'An error occurred while processing your research request.';

            if (error.code === 'budget_exceeded') {
                errorMessage = 'Token budget exceeded. Please contact an administrator.';
            } else if (error.code === 'invalid_api_key') {
                errorMessage = 'API configuration error. Please contact an administrator.';
            } else if (error.message) {
                errorMessage = error.message;
            }

            setResearchError(errorMessage);
            createNotice('error', errorMessage, { type: 'snackbar' });
        } finally {
            setResearchLoading(false);
        }
    };

    const handleAuthorSubmit = async () => {
        setAuthorLoading(true);
        setAuthorError('');
        setAuthorResults('');
        setAuthorBlocks([]);
        setAuthorAbstract(null);
        setAuthorWarnings([]);
        setAuthorValidationErrors([]);

        try {
            const payload = {
                mode: authorMode,
                framework_brief_id: frameworkBriefId || undefined,
                planner_session_id: plannerSessionId || undefined,
                draft_content: authorMode !== 'draft' ? draftContent : undefined,
                instructions: authorInstructions || undefined,
                core_settings: authorCoreSettings,
            };

            const response = await apiFetch({
                path: 'dual-gpt/v1/author/run',
                method: 'POST',
                data: payload,
            });

            setAuthorWarnings(response.warnings || []);
            setAuthorValidationErrors(response.validation_errors || []);

            if (response.mode === 'draft') {
                setAuthorBlocks(response.output?.blocks || []);
                setAuthorResults('Draft completed successfully.');
            } else if (response.mode === 'abstract') {
                setAuthorAbstract(response.output?.abstract || null);
                setAuthorResults('Abstract completed successfully.');
            } else if (response.mode === 'enrichment') {
                setAuthorBlocks(response.output?.blocks || []);
                setAuthorResults('Enrichment completed successfully.');
            }
        } catch (error) {
            let errorMessage = 'An error occurred while processing your authoring request.';

            if (error.code === 'budget_exceeded') {
                errorMessage = 'Token budget exceeded. Please contact an administrator.';
            } else if (error.code === 'invalid_api_key') {
                errorMessage = 'API configuration error. Please contact an administrator.';
            } else if (error.message) {
                errorMessage = error.message;
            }

            setAuthorError(errorMessage);
            createNotice('error', errorMessage, { type: 'snackbar' });
        } finally {
            setAuthorLoading(false);
        }
    };

    const pollJobStatus = async (jobId, type) => {
        try {
            const response = await apiFetch({
                path: `dual-gpt/v1/jobs/${jobId}`,
                method: 'GET',
            });

            if (response.status === 'completed') {
                if (type === 'research') {
                    setResearchResults('Research completed successfully.');
                } else {
                    setAuthorResults('Content generation completed successfully.');
                }
            } else if (response.status === 'failed') {
                const errorMsg = response.error_message || 'Job failed';
                if (type === 'research') {
                    setResearchError(errorMsg);
                } else {
                    setAuthorError(errorMsg);
                }
                createNotice('error', `Job failed: ${errorMsg}`, { type: 'snackbar' });
            } else {
                setTimeout(() => pollJobStatus(jobId, type), 2000);
            }
        } catch (error) {
            const errorMsg = 'Error checking job status';
            if (type === 'research') {
                setResearchError(errorMsg);
            } else {
                setAuthorError(errorMsg);
            }
        }
    };

    const insertBlocksFromAuthor = () => {
        if (!authorBlocks || authorBlocks.length === 0) {
            return;
        }

        const escapeHtml = (value) => {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const buildPullquoteMetaSpan = (meta) => {
            if (!meta || typeof meta !== 'object') {
                return '';
            }
            const attributes = [
                `data-source-author="${escapeHtml(meta.source_author || '')}"`,
                `data-publication="${escapeHtml(meta.publication || '')}"`,
                `data-organisation="${escapeHtml(meta.organisation || '')}"`,
                `data-date="${escapeHtml(meta.date || '')}"`,
                `data-citation-ref-id="${escapeHtml(meta.citation_ref_id || '')}"`,
            ];

            return `<span class="dual-gpt-pullquote-meta" style="display:none" ${attributes.join(' ')}></span>`;
        };

        const blocks = authorBlocks.map((block) => {
            switch (block.type) {
                case 'heading':
                    return wp.blocks.createBlock('core/heading', {
                        level: block.level || 2,
                        content: block.content || '',
                    });
                case 'paragraph':
                    return wp.blocks.createBlock('core/paragraph', {
                        content: block.content || '',
                    });
                case 'list':
                    const listItems = (block.items || []).map((item) => `<li>${escapeHtml(item)}</li>`).join('');
                    const listTag = block.ordered ? 'ol' : 'ul';
                    return wp.blocks.createBlock('core/list', {
                        ordered: !!block.ordered,
                        values: `<${listTag}>${listItems}</${listTag}>`,
                    });
                case 'pullquote':
                    const pullquoteMeta = buildPullquoteMetaSpan(block.meta || block.metadata);
                    return wp.blocks.createBlock('core/pullquote', {
                        value: `<p>${escapeHtml(block.content || '')}</p>${pullquoteMeta}`,
                        citation: escapeHtml(block.cite || ''),
                    });
                case 'quote':
                    return wp.blocks.createBlock('core/quote', {
                        value: `<p>${escapeHtml(block.content || '')}</p>`,
                        citation: escapeHtml(block.cite || ''),
                    });
                case 'separator':
                    return wp.blocks.createBlock('core/separator', {});
                default:
                    return wp.blocks.createBlock('core/paragraph', {
                        content: block.content || '',
                    });
            }
        });

        insertBlocks(blocks);
    };

    const buildImagePayload = () => ({
        post_id: postId,
        title: postTitle,
        summary: postExcerpt || '',
        prompt: imagePrompt,
        negative_prompt: imageNegativePrompt,
        alt_text: imageAltText,
        caption: imageCaption,
        provider: imageProvider,
        preset_key: imagePresetKey,
        keywords: imageAdditionalKeywords,
        text_in_image: imageTextInImage,
        editorial_accuracy: imageEditorialAccuracy,
        store_in_media_library: imageStoreMedia,
        set_featured_image: imageSetFeatured,
        aspect_ratio: imageAspectRatio,
        image_size: imageSize,
    });

    const handleRecommendImage = async () => {
        setImageRecommendationLoading(true);
        setImageError('');
        setImageNotice('');

        try {
            const response = await apiFetch({
                path: 'dual-gpt/v1/images/recommend',
                method: 'POST',
                data: buildImagePayload(),
            });

            setImageRecommendation(response);
            setImagePrompt(response.prompt || '');
            setImageNegativePrompt(response.negative_prompt || '');
            setImageAltText(response.alt_text || '');
            setImageCaption(response.caption || '');
            setImageNotice('Image recommendation updated from the current article context.');
        } catch (error) {
            setImageError(error?.message || 'Failed to generate an image recommendation.');
        } finally {
            setImageRecommendationLoading(false);
        }
    };

    const handleGenerateImage = async () => {
        setImageGenerateLoading(true);
        setImageError('');
        setImageNotice('');

        try {
            const response = await apiFetch({
                path: 'dual-gpt/v1/images/generate',
                method: 'POST',
                data: buildImagePayload(),
            });

            setImageResult(response);
            setImageNotice(response.stored_in_media_library
                ? 'Image generated and saved to the media library.'
                : 'Image generated successfully.');

            const firstAttachment = response.attachments?.[0];
            if (firstAttachment?.attachment_id && imageSetFeatured) {
                editPost({ featured_media: firstAttachment.attachment_id });
            }
        } catch (error) {
            setImageError(error?.message || 'Failed to generate image.');
        } finally {
            setImageGenerateLoading(false);
        }
    };

    const insertGeneratedImage = () => {
        const firstAttachment = imageResult?.attachments?.[0];
        if (!firstAttachment?.attachment_id || !firstAttachment?.url) {
            return;
        }

        const imageBlock = wp.blocks.createBlock('core/image', {
            id: firstAttachment.attachment_id,
            url: firstAttachment.url,
            alt: imageAltText,
            caption: imageCaption,
        });

        insertBlocks([imageBlock]);
        createNotice('success', 'Generated image inserted into the post.', { type: 'snackbar' });
    };

    return (
        <PluginSidebar
            name="dual-gpt-sidebar"
            title="Dual-GPT Authoring"
            icon="admin-tools"
        >
            <PanelBody title="AI Images" initialOpen={true}>
                {imageConfigLoading ? (
                    <Spinner />
                ) : null}

                {imageConfigError ? (
                    <StatusMessage tone="error" title="Image settings unavailable">
                        {imageConfigError}
                    </StatusMessage>
                ) : null}

                {!imageConfigLoading && !imageConfigError ? (
                    <>
                        <SelectControl
                            label="Style Preset"
                            value={imagePresetKey}
                            options={Object.entries(imageConfig?.presets || {}).map(([value, preset]) => ({
                                label: preset.label || value,
                                value,
                            }))}
                            onChange={(value) => {
                                setImagePresetKey(value);
                                const preset = imageConfig?.presets?.[value];
                                if (preset?.aspect_ratio) {
                                    setImageAspectRatio(preset.aspect_ratio);
                                }
                            }}
                        />

                        <SelectControl
                            label="Image Provider"
                            value={imageProvider}
                            options={Object.entries(imageConfig?.provider_status || {})
                                .filter(([, provider]) => (provider.supports || []).includes('image'))
                                .map(([value, provider]) => ({
                                    label: `${provider.label}${provider.configured ? '' : ' (not configured)'}`,
                                    value,
                                    disabled: !provider.enabled,
                                }))}
                            onChange={setImageProvider}
                        />

                        <SelectControl
                            label="Aspect Ratio"
                            value={imageAspectRatio}
                            options={[
                                { label: '16:9', value: '16:9' },
                                { label: '4:3', value: '4:3' },
                                { label: '3:4', value: '3:4' },
                                { label: '1:1', value: '1:1' },
                                { label: '9:16', value: '9:16' },
                            ]}
                            onChange={setImageAspectRatio}
                        />

                        <SelectControl
                            label="Image Size"
                            value={imageSize}
                            options={[
                                { label: '2K', value: '2K' },
                                { label: '4K', value: '4K' },
                            ]}
                            onChange={setImageSize}
                        />

                        <TextControl
                            label="Additional Keywords"
                            value={imageAdditionalKeywords}
                            onChange={setImageAdditionalKeywords}
                            placeholder="Optional themes, objects, sectors, or motifs"
                            help="Comma-separated keywords to steer the image without rewriting the full prompt."
                        />

                        <TextControl
                            label="Text In Image"
                            value={imageTextInImage}
                            onChange={setImageTextInImage}
                            placeholder="Optional exact text to render"
                        />

                        <TextareaControl
                            label="Prompt"
                            value={imagePrompt}
                            onChange={setImagePrompt}
                            placeholder="Generate a recommendation first, or write your own prompt."
                        />

                        <TextareaControl
                            label="Negative Prompt"
                            value={imageNegativePrompt}
                            onChange={setImageNegativePrompt}
                            placeholder="Optional exclusions"
                        />

                        <TextControl
                            label="Alt Text"
                            value={imageAltText}
                            onChange={setImageAltText}
                        />

                        <TextControl
                            label="Caption"
                            value={imageCaption}
                            onChange={setImageCaption}
                        />

                        <ToggleControl
                            label="Editorial Accuracy / Google Search Grounding"
                            checked={imageEditorialAccuracy}
                            onChange={setImageEditorialAccuracy}
                        />

                        <ToggleControl
                            label="Save To Media Library"
                            checked={imageStoreMedia}
                            onChange={setImageStoreMedia}
                        />

                        <ToggleControl
                            label="Set As Featured Image"
                            checked={imageSetFeatured}
                            onChange={setImageSetFeatured}
                        />

                        <div className="dual-gpt-button-row">
                            <Button
                                isSecondary
                                onClick={handleRecommendImage}
                                disabled={imageRecommendationLoading || imageGenerateLoading}
                            >
                                {imageRecommendationLoading ? <Spinner /> : 'Recommend'}
                            </Button>
                            <Button
                                isPrimary
                                onClick={handleGenerateImage}
                                disabled={imageGenerateLoading || imageRecommendationLoading || !imagePrompt.trim()}
                            >
                                {imageGenerateLoading ? <Spinner /> : 'Generate'}
                            </Button>
                        </div>

                        {imageError ? (
                            <StatusMessage tone="error" title="Image generation failed">
                                {imageError}
                            </StatusMessage>
                        ) : null}

                        {imageNotice ? (
                            <StatusMessage tone="success" title="Image workflow">
                                {imageNotice}
                            </StatusMessage>
                        ) : null}

                        {imageRecommendation?.rationale ? (
                            <Notice status="info" isDismissible={false}>
                                {imageRecommendation.rationale}
                            </Notice>
                        ) : null}

                        {imageResult?.attachments?.[0]?.url ? (
                            <div className="dual-gpt-image-preview">
                                <img src={imageResult.attachments[0].url} alt={imageAltText || 'Generated image preview'} />
                                <div className="dual-gpt-button-row">
                                    <Button isSecondary onClick={insertGeneratedImage}>
                                        Insert Inline
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </>
                ) : null}
            </PanelBody>

            <PanelBody title="Research Pane" initialOpen={false}>
                <TextareaControl
                    label="Research Prompt"
                    value={researchPrompt}
                    onChange={(value) => {
                        setResearchPrompt(value);
                        if (researchError) setResearchError('');
                    }}
                    placeholder="Enter your research query..."
                />
                <Button
                    isPrimary
                    onClick={handleResearchSubmit}
                    disabled={researchLoading || !researchPrompt.trim()}
                >
                    {researchLoading ? <Spinner /> : 'Research'}
                </Button>
                {researchError ? <StatusMessage tone="error" title="Research error">{researchError}</StatusMessage> : null}
                {researchResults && !researchError ? <StatusMessage tone="success" title="Research">{researchResults}</StatusMessage> : null}
            </PanelBody>

            <PanelBody title="Author Agent" initialOpen={false}>
                <label style={{ display: 'block', marginBottom: '6px', fontWeight: 600 }}>Mode</label>
                <select
                    value={authorMode}
                    onChange={(event) => setAuthorMode(event.target.value)}
                    style={{ width: '100%', marginBottom: '12px' }}
                >
                    <option value="draft">Draft</option>
                    <option value="abstract">Abstract</option>
                    <option value="enrichment">Enrichment</option>
                </select>

                <TextareaControl
                    label="Framework Brief ID"
                    value={frameworkBriefId}
                    onChange={(value) => setFrameworkBriefId(value)}
                    placeholder="FG brief ID (required for draft)"
                />
                <TextareaControl
                    label="Planner Session ID"
                    value={plannerSessionId}
                    onChange={(value) => setPlannerSessionId(value)}
                    placeholder="Editorial Planner session ID (required for draft)"
                />
                <TextareaControl
                    label="Author Instructions (optional)"
                    value={authorInstructions}
                    onChange={(value) => setAuthorInstructions(value)}
                    placeholder="Optional constraints or notes"
                />

                <PanelBody title="Core Settings" initialOpen={false}>
                    <TextareaControl
                        label="Industry Focus"
                        value={authorCoreSettings.industry_focus}
                        onChange={(value) => setAuthorCoreSettings({ ...authorCoreSettings, industry_focus: value })}
                    />
                    <TextareaControl
                        label="Audience Tier"
                        value={authorCoreSettings.audience_tier}
                        onChange={(value) => setAuthorCoreSettings({ ...authorCoreSettings, audience_tier: value })}
                    />
                    <TextareaControl
                        label="Risk Tolerance"
                        value={authorCoreSettings.risk_tolerance}
                        onChange={(value) => setAuthorCoreSettings({ ...authorCoreSettings, risk_tolerance: value })}
                    />
                    <TextareaControl
                        label="Brand Profile"
                        value={authorCoreSettings.brand_profile}
                        onChange={(value) => setAuthorCoreSettings({ ...authorCoreSettings, brand_profile: value })}
                    />
                </PanelBody>

                <div className="dual-gpt-button-row">
                    <Button
                        isPrimary
                        onClick={handleAuthorSubmit}
                        disabled={authorLoading}
                    >
                        {authorLoading ? <Spinner /> : 'Run Author Agent'}
                    </Button>
                    <Button
                        isSecondary
                        onClick={insertBlocksFromAuthor}
                        disabled={!authorBlocks.length || !!authorError}
                    >
                        Insert Blocks
                    </Button>
                </div>

                {authorError ? <StatusMessage tone="error" title="Author error">{authorError}</StatusMessage> : null}
                {authorWarnings.length > 0 ? (
                    <StatusMessage tone="warning" title="Warnings">
                        <ul>{authorWarnings.map((warning, index) => <li key={index}>{warning}</li>)}</ul>
                    </StatusMessage>
                ) : null}
                {authorValidationErrors.length > 0 ? (
                    <StatusMessage tone="error" title="Validation Errors">
                        <ul>{authorValidationErrors.map((error, index) => <li key={index}>{error}</li>)}</ul>
                    </StatusMessage>
                ) : null}
                {authorResults && !authorError ? <StatusMessage tone="success" title="Author">{authorResults}</StatusMessage> : null}
                {authorAbstract ? (
                    <div className="dual-gpt-results">
                        <strong>Abstract Output:</strong>
                        <pre style={{ whiteSpace: 'pre-wrap' }}>{JSON.stringify(authorAbstract, null, 2)}</pre>
                    </div>
                ) : null}
            </PanelBody>
        </PluginSidebar>
    );
};

registerPlugin('dual-gpt-sidebar', {
    render: DualGPTSidebar,
    icon: 'admin-tools',
});
