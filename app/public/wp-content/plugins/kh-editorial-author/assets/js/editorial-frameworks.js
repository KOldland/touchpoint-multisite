/**
 * Writing Studio - Editorial Author Workspace
 * Refactored to support async drafting, polling, and policy configuration.
 */
const { useState, useEffect } = wp.element;
const { __ } = wp.i18n;
const { 
    Spinner, 
    Notice, 
    Button, 
    TextareaControl, 
    SelectControl, 
    ToggleControl,
    PanelBody,
    Placeholder,
    ProgressBar
} = wp.components;

const apiFetch = (options) =>
    wp.apiFetch({
        ...options,
        headers: {
            'X-WP-Nonce': authorData.nonce,
            ...(options.headers || {}),
        },
    });

const WritingStudioApp = () => {
    // UI State
    const [view, setView] = useState('list'); // 'list' | 'articles' | 'workspace'
    const [sessions, setSessions] = useState([]);
    const [selectedSession, setSelectedSession] = useState(null);
    const [selectedArticle, setSelectedArticle] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    // Drafting State
    const [status, setStatus] = useState('idle'); // 'idle' | 'queued' | 'processing' | 'completed' | 'failed'
    const [activeJobId, setActiveJobId] = useState(null);
    const [draftResult, setDraftResult] = useState(null);
    
    // Config State
    const [instructions, setInstructions] = useState('');
    const [policy, setPolicy] = useState(authorData.defaults || {});
    const [enrichment, setEnrichment] = useState({
        generate_images: false,
        image_provider: 'openai',
        verify_citations: true
    });
    const [exporting, setExporting] = useState(null);

    useEffect(() => {
        loadSessions();
    }, []);

    // Polling logic
    useEffect(() => {
        let pollInterval;
        let consecutiveErrors = 0;
        if (activeJobId && (status === 'queued' || status === 'processing')) {
            pollInterval = setInterval(async () => {
                try {
                    const job = await apiFetch({
                        path: `editorial/v1/author/job/${activeJobId}`,
                        method: 'GET',
                    });

                    consecutiveErrors = 0; // Reset on success

                    if (job.status === 'completed' && job.response) {
                        setDraftResult(job.response);
                        setStatus('completed');
                        setActiveJobId(null);
                        clearInterval(pollInterval);
                    } else if (job.status === 'failed') {
                        setError(job.error_message || __('Drafting failed.', 'kh-editorial-author'));
                        setStatus('failed');
                        setActiveJobId(null);
                        clearInterval(pollInterval);
                    } else if (job.status === 'processing') {
                        setStatus('processing');
                    }
                } catch (err) {
                    console.error('Polling error', err);
                    consecutiveErrors++;
                    if (consecutiveErrors > 5) {
                        setError(__('Lost connection to server. Polling stopped.', 'kh-editorial-author'));
                        setStatus('failed');
                        setActiveJobId(null);
                        clearInterval(pollInterval);
                    }
                }
            }, 3000);
        }
        return () => clearInterval(pollInterval);
    }, [activeJobId, status]);

    const loadSessions = async () => {
        try {
            setLoading(true);
            const response = await apiFetch({
                path: 'editorial/v1/sessions',
                method: 'GET',
            });
            const list = Array.isArray(response) ? response : response.data || [];
            setSessions(list);
        } catch (err) {
            setError('Failed to load sessions.');
        } finally {
            setLoading(false);
        }
    };

    const handleStartDrafting = (session) => {
        setSelectedSession(session);
        setView('articles');
        setError('');
    };

    const handleSelectArticle = (article) => {
        // Reset states for new workspace session
        setDraftResult(null);
        setStatus('idle');
        setInstructions('');
        setError('');
        setActiveJobId(null);

        setSelectedArticle(article);
        setView('workspace');
        // Pre-fill policy if session/article has it
        if (selectedSession.author_policy) {
            setPolicy({ ...authorData.defaults, ...selectedSession.author_policy });
        }
    };

    const runDrafting = async () => {
        try {
            setError('');
            setStatus('queued');
            setDraftResult(null);

            const response = await apiFetch({
                path: 'editorial/v1/author/run',
                method: 'POST',
                data: {
                    mode: 'draft',
                    planner_session_id: selectedSession.session_id,
                    article_id: selectedArticle.id,
                    instructions: instructions,
                    author_policy: policy,
                    core_settings: {
                        industry_focus: policy.industry_focus,
                        audience_tier: policy.audience_tier,
                        risk_tolerance: policy.risk_tolerance,
                        brand_profile: policy.brand_profile
                    },
                    enrichment: enrichment
                }
            });

            if (response.job_id) {
                setActiveJobId(response.job_id);
            } else if (response.mode === 'draft') {
                // Fallback for synchronous response if worker is disabled
                setDraftResult(response);
                setStatus('completed');
            }
        } catch (err) {
            setError(err.message || __('Failed to trigger drafting.', 'kh-editorial-author'));
            setStatus('failed');
        }
    };

    const handleExport = async (format) => {
        if (!selectedSession) return;
        try {
            setExporting(format);
            const response = await apiFetch({
                path: `fg/v1/export/${selectedSession.session_id}?format=${format}`,
                method: 'GET',
            });
            if (response && response.file_url) {
                window.location.href = response.file_url;
            }
        } catch (err) {
            setError(__('Export failed.', 'kh-editorial-author'));
        } finally {
            setExporting(null);
        }
    };

    if (loading) return (
        <div style={{ padding: '40px', textAlign: 'center' }}>
            <Spinner />
            <p>{__('Loading Writing Studio...', 'kh-editorial-author')}</p>
        </div>
    );

    if (view === 'list') {
        return (
            <div className="writing-studio-list" style={{ padding: '20px' }}>
                <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <h1>{__('Writing Studio', 'kh-editorial-author')}</h1>
                    <Button isPrimary onClick={loadSessions}>{__('Refresh Sessions', 'kh-editorial-author')}</Button>
                </header>
                {error && <Notice status="error" onDismiss={() => setError('')}>{error}</Notice>}
                
                <div className="sessions-grid" style={{ display: 'grid', gap: '15px' }}>
                    {sessions.map(session => (
                        <div key={session.session_id} style={{ border: '1px solid #ccd0d4', padding: '15px', borderRadius: '4px', background: '#fff' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <div>
                                    <h3 style={{ margin: '0 0 5px 0' }}>{session.title || `Session #${session.session_id}`}</h3>
                                    <small>{__('ID:', 'kh-editorial-author')} {session.session_id} | {__('Created:', 'kh-editorial-author')} {new Date(session.created_at).toLocaleDateString()}</small>
                                </div>
                                <Button isSecondary onClick={() => handleStartDrafting(session)}>
                                    {__('Open Workspace', 'kh-editorial-author')}
                                </Button>
                            </div>
                        </div>
                    ))}
                    {sessions.length === 0 && <Placeholder label={__('No Planning Sessions Found', 'kh-editorial-author')} />}
                </div>
            </div>
        );
    }

    if (view === 'articles') {
        const articles = selectedSession.planner_data?.articles || [];
        return (
            <div className="writing-studio-articles" style={{ padding: '20px' }}>
                <header style={{ marginBottom: '20px' }}>
                    <Button variant="link" onClick={() => setView('list')} style={{ padding: 0 }}>
                        &larr; {__('Back to Sessions', 'kh-editorial-author')}
                    </Button>
                    <h1>{selectedSession.title} - {__('Select Article Idea', 'kh-editorial-author')}</h1>
                </header>
                
                <div className="articles-grid" style={{ display: 'grid', gap: '15px' }}>
                    {articles.map(article => (
                        <div key={article.id} style={{ border: '1px solid #ccd0d4', padding: '20px', borderRadius: '4px', background: '#fff' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                <div style={{ flex: 1 }}>
                                    <h3 style={{ margin: '0 0 10px 0' }}>{article.headline || article.title}</h3>
                                    <p style={{ color: '#666', fontSize: '14px' }}>{article.summary || article.brief}</p>
                                </div>
                                <Button isPrimary onClick={() => handleSelectArticle(article)}>
                                    {__('Draft This Idea', 'kh-editorial-author')}
                                </Button>
                            </div>
                        </div>
                    ))}
                    {articles.length === 0 && (
                        <Notice status="warning" isDismissible={false}>
                            {__('No article ideas found in this session. Ensure the planner has completed Phase 3.', 'kh-editorial-author')}
                        </Notice>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="writing-studio-workspace" style={{ display: 'flex', height: 'calc(100vh - 100px)', background: '#f0f0f1' }}>
            {/* Sidebar - Configuration */}
            <aside style={{ width: '300px', background: '#fff', borderRight: '1px solid #ccd0d4', overflowY: 'auto', padding: '15px' }}>
                <Button variant="link" onClick={() => setView('articles')} style={{ marginBottom: '10px', padding: 0 }}>
                    &larr; {__('Back to Articles', 'kh-editorial-author')}
                </Button>
                <h2>{__('Configuration', 'kh-editorial-author')}</h2>
                
                <PanelBody title={__('Core Settings', 'kh-editorial-author')} initialOpen={true}>
                    <SelectControl
                        label={__('Industry Focus', 'kh-editorial-author')}
                        value={policy.industry_focus}
                        options={authorData.options.industry_focus.map(o => ({ label: o, value: o }))}
                        onChange={(val) => setPolicy({ ...policy, industry_focus: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                    <SelectControl
                        label={__('Audience Tier', 'kh-editorial-author')}
                        value={policy.audience_tier}
                        options={authorData.options.audience_tier.map(o => ({ label: o, value: o }))}
                        onChange={(val) => setPolicy({ ...policy, audience_tier: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                    <SelectControl
                        label={__('Risk Tolerance', 'kh-editorial-author')}
                        value={policy.risk_tolerance}
                        options={authorData.options.risk_tolerance.map(o => ({ label: o, value: o }))}
                        onChange={(val) => setPolicy({ ...policy, risk_tolerance: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                    <SelectControl
                        label={__('Brand Profile', 'kh-editorial-author')}
                        value={policy.brand_profile}
                        options={authorData.options.brand_profiles.map(o => ({ label: o, value: o }))}
                        onChange={(val) => setPolicy({ ...policy, brand_profile: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                </PanelBody>

                <PanelBody title={__('Author Policy', 'kh-editorial-author')} initialOpen={false}>
                    <ToggleControl
                        label={__('Reporter Voice', 'kh-editorial-author')}
                        checked={policy.reporter_voice_required}
                        onChange={(val) => setPolicy({ ...policy, reporter_voice_required: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                    <ToggleControl
                        label={__('Disallow First Person', 'kh-editorial-author')}
                        checked={policy.disallow_first_person}
                        onChange={(val) => setPolicy({ ...policy, disallow_first_person: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                    <ToggleControl
                        label={__('Disallow Em-Dash', 'kh-editorial-author')}
                        checked={policy.disallow_em_dash}
                        onChange={(val) => setPolicy({ ...policy, disallow_em_dash: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                    <ToggleControl
                        label={__('Disallow Listicle Framing', 'kh-editorial-author')}
                        checked={policy.disallow_listicle_framing}
                        onChange={(val) => setPolicy({ ...policy, disallow_listicle_framing: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                    <div style={{ display: 'flex', gap: '10px' }}>
                        <div style={{ flex: 1 }}>
                            <small>{__('Min Words', 'kh-editorial-author')}</small>
                            <input 
                                type="number" 
                                value={policy.min_words} 
                                onChange={(e) => setPolicy({ ...policy, min_words: e.target.value })} 
                                style={{ width: '100%' }} 
                                disabled={status === 'queued' || status === 'processing'}
                            />
                        </div>
                        <div style={{ flex: 1 }}>
                            <small>{__('Max Words', 'kh-editorial-author')}</small>
                            <input 
                                type="number" 
                                value={policy.max_words} 
                                onChange={(e) => setPolicy({ ...policy, max_words: e.target.value })} 
                                style={{ width: '100%' }} 
                                disabled={status === 'queued' || status === 'processing'}
                            />
                        </div>
                    </div>
                </PanelBody>

                <PanelBody title={__('Enrichment', 'kh-editorial-author')} initialOpen={false}>
                    <ToggleControl
                        label={__('Generate Editorial Images', 'kh-editorial-author')}
                        checked={enrichment.generate_images}
                        onChange={(val) => setEnrichment({ ...enrichment, generate_images: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                    {enrichment.generate_images && (
                        <SelectControl
                            label={__('Image Provider', 'kh-editorial-author')}
                            value={enrichment.image_provider}
                            options={[
                                { label: 'OpenAI (DALL-E 3)', value: 'openai' },
                                { label: 'Google (Imagen)', value: 'google' }
                            ]}
                            onChange={(val) => setEnrichment({ ...enrichment, image_provider: val })}
                            disabled={status === 'queued' || status === 'processing'}
                        />
                    )}
                    <ToggleControl
                        label={__('Verify References', 'kh-editorial-author')}
                        checked={enrichment.verify_citations}
                        onChange={(val) => setEnrichment({ ...enrichment, verify_citations: val })}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                </PanelBody>

                <PanelBody title={__('Export', 'kh-editorial-author')} initialOpen={false}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
                        <Button isSecondary onClick={() => handleExport('html')} disabled={exporting === 'html'}>
                            {exporting === 'html' ? __('Exporting...', 'kh-editorial-author') : __('Export HTML', 'kh-editorial-author')}
                        </Button>
                        <Button isSecondary onClick={() => handleExport('docx')} disabled={exporting === 'docx'}>
                            {exporting === 'docx' ? __('Exporting...', 'kh-editorial-author') : __('Export DOCX', 'kh-editorial-author')}
                        </Button>
                        <Button isSecondary onClick={() => handleExport('pdf')} disabled={exporting === 'pdf'}>
                            {exporting === 'pdf' ? __('Exporting...', 'kh-editorial-author') : __('Export PDF', 'kh-editorial-author')}
                        </Button>
                    </div>
                </PanelBody>
            </aside>

            {/* Main Stage */}
            <main style={{ flex: 1, padding: '30px', overflowY: 'auto' }}>
                <header style={{ marginBottom: '30px' }}>
                    <h1>{selectedArticle.headline || selectedArticle.title}</h1>
                    <p style={{ color: '#666', marginTop: '-15px', marginBottom: '20px' }}>
                        {__('Derived from Session:', 'kh-editorial-author')} <strong>{selectedSession.title}</strong>
                    </p>
                    <TextareaControl
                        label={__('Instructions for the Author Agent', 'kh-editorial-author')}
                        help={__('Topic focus, specific data points to emphasize, or tonal adjustments.', 'kh-editorial-author')}
                        value={instructions}
                        onChange={(val) => setInstructions(val)}
                        rows={4}
                        disabled={status === 'queued' || status === 'processing'}
                    />
                    <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                        <Button isPrimary onClick={runDrafting} disabled={status === 'queued' || status === 'processing'}>
                            {status === 'idle' ? __('Generate Draft', 'kh-editorial-author') : __('Regenerate Draft', 'kh-editorial-author')}
                        </Button>
                        {(status === 'queued' || status === 'processing') && (
                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flex: 1 }}>
                                <Spinner />
                                <div style={{ flex: 1 }}>
                                    <ProgressBar value={status === 'queued' ? 10 : 50} />
                                    <small>{__('Agent is', 'kh-editorial-author')} {status}...</small>
                                </div>
                            </div>
                        )}
                    </div>
                </header>

                {error && <Notice status="error" onDismiss={() => setError('')}>{error}</Notice>}

                {draftResult && (
                    <div className="draft-output" style={{ background: '#fff', padding: '40px', borderRadius: '4px', border: '1px solid #ccd0d4', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', borderBottom: '1px solid #eee', paddingBottom: '10px', marginBottom: '20px' }}>
                            <span style={{ color: '#666' }}>{__('Word Count:', 'kh-editorial-author')} <strong>{draftResult.word_count}</strong></span>
                            <span style={{ color: '#666' }}>{__('Mode:', 'kh-editorial-author')} <strong>{draftResult.mode}</strong></span>
                        </div>

                        {/* Policy Warnings */}
                        {draftResult.warnings && draftResult.warnings.length > 0 && (
                            <div style={{ background: '#fff8e5', borderLeft: '4px solid #ffb900', padding: '15px', marginBottom: '30px' }}>
                                <h4 style={{ margin: '0 0 10px 0' }}>{__('Policy Feedback', 'kh-editorial-author')}</h4>
                                <ul style={{ margin: 0, paddingLeft: '20px' }}>
                                    {draftResult.warnings.map((w, i) => <li key={i}>{w}</li>)}
                                </ul>
                            </div>
                        )}

                        {/* Article Preview */}
                        <article className="entry-content">
                            {draftResult.blocks && draftResult.blocks.map((block, idx) => {
                                if (block.type === 'heading') {
                                    const Tag = `h${block.level || 2}`;
                                    return <Tag key={idx}>{block.content}</Tag>;
                                }
                                if (block.type === 'list') {
                                    const ListTag = block.ordered ? 'ol' : 'ul';
                                    return (
                                        <ListTag key={idx}>
                                            {block.items && block.items.map((item, i) => <li key={i}>{item}</li>)}
                                        </ListTag>
                                    );
                                }
                                if (block.type === 'pullquote') {
                                    return (
                                        <blockquote key={idx} style={{ borderLeft: '4px solid #007cba', paddingLeft: '20px', fontStyle: 'italic', fontSize: '1.2em' }}>
                                            <p>{block.content}</p>
                                            {block.cite && <cite>&mdash; {block.cite}</cite>}
                                        </blockquote>
                                    );
                                }
                                return <p key={idx} style={{ lineHeight: '1.6', fontSize: '16px' }}>{block.content}</p>;
                            })}
                        </article>
                    </div>
                )}
                
                {status === 'idle' && !draftResult && (
                    <Placeholder 
                        icon="edit" 
                        label={__('Ready to Draft', 'kh-editorial-author')}
                        instructions={__('Configure your policy settings in the sidebar and provide any specific instructions above to begin.', 'kh-editorial-author')}
                    />
                )}
            </main>
        </div>
    );
};

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('editorial-frameworks-app');
    if (container) {
        wp.element.render(<WritingStudioApp />, container);
    }
});