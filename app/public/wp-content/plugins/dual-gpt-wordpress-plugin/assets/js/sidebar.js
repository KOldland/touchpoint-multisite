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
    const [authorPrompt, setAuthorPrompt] = useState('');
    const [researchLoading, setResearchLoading] = useState(false);
    const [authorLoading, setAuthorLoading] = useState(false);
    const [researchResults, setResearchResults] = useState('');
    const [authorResults, setAuthorResults] = useState('');
    const [researchError, setResearchError] = useState('');
    const [authorError, setAuthorError] = useState('');
    const [researchJobId, setResearchJobId] = useState(null);
    const [authorJobId, setAuthorJobId] = useState(null);

    const { insertBlocks } = useDispatch('core/block-editor');
    const { createNotice } = useDispatch('core/notices');

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
        if (!authorPrompt.trim()) {
            setAuthorError('Please enter an authoring prompt');
            return;
        }

        setAuthorLoading(true);
        setAuthorError('');
        setAuthorResults('');

        try {
            // First create a session
            const sessionResponse = await apiFetch({
                path: 'dual-gpt/v1/sessions',
                method: 'POST',
                data: {
                    role: 'author',
                    title: 'Author Session - ' + new Date().toLocaleString(),
                },
            });

            // Then create the job
            const jobResponse = await apiFetch({
                path: 'dual-gpt/v1/jobs',
                method: 'POST',
                data: {
                    session_id: sessionResponse.session_id,
                    prompt: authorPrompt,
                    model: 'gpt-4',
                },
            });

            setAuthorJobId(jobResponse.job_id);
            setAuthorResults('Job submitted successfully. Processing...');

            // Start polling for results
            pollJobStatus(jobResponse.job_id, 'author');

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
        // Placeholder for inserting blocks from author output
        const blocks = [
            wp.blocks.createBlock('core/paragraph', { content: 'Sample paragraph from AI' }),
        ];
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

            <PanelBody title="Author Pane" initialOpen={true}>
                <TextareaControl
                    label="Author Prompt"
                    value={authorPrompt}
                    onChange={(value) => {
                        setAuthorPrompt(value);
                        if (authorError) setAuthorError(''); // Clear error on input
                    }}
                    placeholder="Enter your authoring prompt..."
                />
                <Button
                    isPrimary
                    onClick={handleAuthorSubmit}
                    disabled={authorLoading || !authorPrompt.trim()}
                >
                    {authorLoading ? <Spinner /> : 'Generate Content'}
                </Button>
                <Button
                    isSecondary
                    onClick={insertBlocksFromAuthor}
                    disabled={!authorResults || authorError}
                    style={{ marginLeft: '10px' }}
                >
                    Insert Blocks
                </Button>
                {authorError && (
                    <div style={{ marginTop: '10px', padding: '10px', backgroundColor: '#ffe6e6', border: '1px solid #ff9999', borderRadius: '4px' }}>
                        <strong>Error:</strong> {authorError}
                    </div>
                )}
                {authorResults && !authorError && (
                    <div style={{ marginTop: '10px', padding: '10px', backgroundColor: '#e6ffe6', border: '1px solid #99ff99', borderRadius: '4px' }}>
                        <strong>Author Results:</strong>
                        <p>{authorResults}</p>
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