/**
 * Dual-GPT Gutenberg Sidebar
 */

const { registerPlugin } = wp.plugins;
const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
const { PanelBody, TextareaControl, Button, Spinner } = wp.components;
const { useState, useEffect } = wp.element;
const { useSelect, useDispatch } = wp.data;
const { apiFetch } = wp;

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

    const { insertBlocks } = useDispatch('core/block-editor');
    const { createNotice } = useDispatch('core/notices');

    const draftContent = useSelect((select) => select('core/editor').getEditedPostContent(), []);

    const handleResearchSubmit = async () => {
        if (!researchPrompt.trim()) {
            setResearchError('Please enter a research prompt');
            return;
        }

        setResearchLoading(true);
        setResearchError('');
        setResearchResults('');

        try {
            // First create a session
            const sessionResponse = await apiFetch({
                path: 'dual-gpt/v1/sessions',
                method: 'POST',
                data: {
                    role: 'research',
                    title: 'Research Session - ' + new Date().toLocaleString(),
                },
            });

            // Then create the job
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

            // Start polling for results
            pollJobStatus(jobResponse.job_id, 'research');

        } catch (error) {
            console.error('Research error:', error);
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
            console.error('Author error:', error);
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
                    setResearchResults('Research completed successfully!');
                } else {
                    setAuthorResults('Content generation completed successfully!');
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
                // Still processing, poll again in 2 seconds
                setTimeout(() => pollJobStatus(jobId, type), 2000);
            }
        } catch (error) {
            console.error('Polling error:', error);
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
                    const listItems = (block.items || []).map((item) => `<li>${item}</li>`).join('');
                    const listTag = block.ordered ? 'ol' : 'ul';
                    return wp.blocks.createBlock('core/list', {
                        ordered: !!block.ordered,
                        values: `<${listTag}>${listItems}</${listTag}>`,
                    });
                case 'pullquote':
                    return wp.blocks.createBlock('core/pullquote', {
                        value: `<p>${block.content || ''}</p>`,
                        citation: block.cite || '',
                    });
                case 'quote':
                    return wp.blocks.createBlock('core/quote', {
                        value: `<p>${block.content || ''}</p>`,
                        citation: block.cite || '',
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

    return (
        <PluginSidebar
            name="dual-gpt-sidebar"
            title="Dual-GPT Authoring"
            icon="admin-tools"
        >
            <PanelBody title="Research Pane" initialOpen={true}>
                <TextareaControl
                    label="Research Prompt"
                    value={researchPrompt}
                    onChange={(value) => {
                        setResearchPrompt(value);
                        if (researchError) setResearchError(''); // Clear error on input
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
                {researchError && (
                    <div style={{ marginTop: '10px', padding: '10px', backgroundColor: '#ffe6e6', border: '1px solid #ff9999', borderRadius: '4px' }}>
                        <strong>Error:</strong> {researchError}
                    </div>
                )}
                {researchResults && !researchError && (
                    <div style={{ marginTop: '10px', padding: '10px', backgroundColor: '#e6ffe6', border: '1px solid #99ff99', borderRadius: '4px' }}>
                        <strong>Research Results:</strong>
                        <p>{researchResults}</p>
                    </div>
                )}
            </PanelBody>

            <PanelBody title="Author Agent" initialOpen={true}>
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
                    disabled={!authorBlocks.length || authorError}
                    style={{ marginLeft: '10px' }}
                >
                    Insert Blocks
                </Button>
                {authorError && (
                    <div style={{ marginTop: '10px', padding: '10px', backgroundColor: '#ffe6e6', border: '1px solid #ff9999', borderRadius: '4px' }}>
                        <strong>Error:</strong> {authorError}
                    </div>
                )}
                {authorWarnings.length > 0 && (
                    <div style={{ marginTop: '10px', padding: '10px', backgroundColor: '#fff4e5', border: '1px solid #ffb74d', borderRadius: '4px' }}>
                        <strong>Warnings:</strong>
                        <ul>
                            {authorWarnings.map((warning, index) => (
                                <li key={index}>{warning}</li>
                            ))}
                        </ul>
                    </div>
                )}
                {authorValidationErrors.length > 0 && (
                    <div style={{ marginTop: '10px', padding: '10px', backgroundColor: '#ffe6e6', border: '1px solid #ff9999', borderRadius: '4px' }}>
                        <strong>Validation Errors:</strong>
                        <ul>
                            {authorValidationErrors.map((error, index) => (
                                <li key={index}>{error}</li>
                            ))}
                        </ul>
                    </div>
                )}
                {authorResults && !authorError && (
                    <div style={{ marginTop: '10px', padding: '10px', backgroundColor: '#e6ffe6', border: '1px solid #99ff99', borderRadius: '4px' }}>
                        <strong>Author Results:</strong>
                        <p>{authorResults}</p>
                    </div>
                )}
                {authorAbstract && (
                    <div style={{ marginTop: '10px', padding: '10px', backgroundColor: '#f0f4ff', border: '1px solid #99b5ff', borderRadius: '4px' }}>
                        <strong>Abstract Output:</strong>
                        <pre style={{ whiteSpace: 'pre-wrap' }}>{JSON.stringify(authorAbstract, null, 2)}</pre>
                    </div>
                )}
            </PanelBody>
        </PluginSidebar>
    );
};

registerPlugin('dual-gpt-sidebar', {
    render: DualGPTSidebar,
    icon: 'admin-tools',
});