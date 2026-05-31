/**
 * Writing Studio - Editorial Author Workspace
 * Refactored to support async drafting, polling, and SmartSEO integration.
 */
const { useState, useEffect } = wp.element;
const { __ } = wp.i18n;
const { 
    Spinner, 
    Notice, 
    Button, 
    TextareaControl, 
    TextControl,
    SelectControl, 
    ToggleControl,
    PanelBody,
    Placeholder,
    ProgressBar,
    Icon,
    CheckboxControl,
    RangeControl
} = wp.components;

const apiFetch = (options) =>
    wp.apiFetch({
        ...options,
        headers: {
            'X-WP-Nonce': authorData.nonce,
            ...(options.headers || {}),
        },
    });

/**
 * SmartRecommendationsPanel Component
 * Surfaces semantically related content for cross-linking and research.
 */
const SmartRecommendationsPanel = ({ postId }) => {
    const [recommendations, setRecommendations] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [limit, setLimit] = useState(3);
    const [copiedId, setCopiedId] = useState(null);
    const [refreshTrigger, setRefreshTrigger] = useState(0);
    const [forceTrigger, setForceTrigger] = useState(false);
    const [showForceNotice, setShowForceNotice] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        const signal = controller.signal;
        
        const fetchRecommendations = async () => {
            if (!postId) return;

            setRecommendations([]);
            setError('');
            setLoading(true);

            try {
                const isForced = forceTrigger;
                const response = await apiFetch({
                    path: `editorial/v1/recommendations/${postId}?limit=${limit}${isForced ? '&force=1' : ''}`,
                    method: 'GET',
                    signal: signal
                });

                if (response.success) {
                    setRecommendations(response.data || []);
                    if (isForced) {
                        setShowForceNotice(true);
                        setTimeout(() => setShowForceNotice(false), 4000);
                    }
                } else {
                    setError(__('Failed to load recommendations.', 'kh-editorial-author'));
                }
            } catch (err) {
                // Ignore abort errors
                if (err.name === 'AbortError') return;

                if (err.code === 'post_not_found') {
                    setError(__('AI Agent is still profiling this content. Check back shortly.', 'kh-editorial-author'));
                } else if (err.code === 'budget_exceeded' || err.status === 402) {
                    setError(__('AI Budget reached. Please contact your administrator.', 'kh-editorial-author'));
                } else {
                    setError(err.message || __('Error fetching recommendations.', 'kh-editorial-author'));
                }
            } finally {
                if (!signal.aborted) {
                    setLoading(false);
                    setForceTrigger(false);
                }
            }
        };

        // Simple debounce for RangeControl (limit) changes
        const timeoutId = setTimeout(fetchRecommendations, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [postId, limit, refreshTrigger, forceTrigger]);

    const copyToClipboard = (url, id) => {
        navigator.clipboard.writeText(url).then(() => {
            setCopiedId(id);
            setTimeout(() => setCopiedId(null), 2000);
        });
    };

    const handleRefresh = () => setRefreshTrigger(prev => prev + 1);
    const handleForceRescan = () => {
        setForceTrigger(true);
    };

    return (
        <PanelBody title={__('Smart Recommendations', 'kh-editorial-author')} initialOpen={false} icon="location">
            <div style={{ display: 'flex', flexDirection: 'column', gap: '15px', marginBottom: '20px' }}>
                <RangeControl
                    label={__('Discovery Depth', 'kh-editorial-author')}
                    value={limit}
                    onChange={setLimit}
                    min={1}
                    max={10}
                    disabled={loading}
                    help={__('Adjust the number of semantic matches to discover.', 'kh-editorial-author')}
                />
                <div style={{ display: 'flex', gap: '8px' }}>
                    <Button 
                        isSecondary
                        isSmall
                        icon="update" 
                        onClick={handleRefresh} 
                        disabled={loading}
                    >
                        {__('Refresh', 'kh-editorial-author')}
                    </Button>
                    <Button 
                        isSecondary
                        isSmall
                        icon="brainstorm" 
                        onClick={handleForceRescan} 
                        disabled={loading}
                        showTooltip
                        label={__('Bypass cache and re-analyze content', 'kh-editorial-author')}
                    >
                        {__('Force Re-scan', 'kh-editorial-author')}
                    </Button>
                </div>
            </div>

            {showForceNotice && (
                <Notice status="success" onDismiss={() => setShowForceNotice(false)}>
                    {__('Thematic profile updated and cache invalidated.', 'kh-editorial-author')}
                </Notice>
            )}

            {loading && (
                <Placeholder 
                    icon="admin-site" 
                    label={__('Analyzing Ecosystem...', 'kh-editorial-author')}
                    instructions={__('Consulting the Intelligence Tier for semantic matches.', 'kh-editorial-author')}
                >
                    <Spinner />
                </Placeholder>
            )}

            {error && (
                <Notice status="warning" isDismissible={false}>
                    {error}
                    {!loading && (
                        <div style={{ marginTop: '10px' }}>
                            <Button isSecondary isSmall onClick={handleRefresh} icon="redo">
                                {__('Retry Discovery', 'kh-editorial-author')}
                            </Button>
                        </div>
                    )}
                </Notice>
            )}

            {!loading && !error && recommendations.length === 0 && (
                <Notice status="info" isDismissible={false}>
                    {__('No semantic matches found yet. The AI performs better as you add more content to your draft.', 'kh-editorial-author')}
                </Notice>
            )}

            {!loading && recommendations.length > 0 && (
                <div className="recommendations-list">
                    {recommendations.map((rec) => (
                        <div key={rec.id} style={{ 
                            background: '#fff', 
                            border: '1px solid #ccd0d4', 
                            padding: '12px', 
                            marginBottom: '10px',
                            borderRadius: '4px',
                            boxShadow: '0 1px 2px rgba(0,0,0,0.05)'
                        }}>
                            <div style={{ display: 'flex', gap: '12px', marginBottom: '10px' }}>
                                <div style={{ 
                                    width: '40px', 
                                    height: '40px', 
                                    background: '#f0f0f1', 
                                    borderRadius: '2px',
                                    overflow: 'hidden',
                                    display: 'flex',
                                    flexShrink: 0,
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    border: '1px solid #e2e4e7'
                                }}>
                                    {rec.image_url ? (
                                        <img 
                                            src={rec.image_url} 
                                            alt="" 
                                            style={{ width: '100%', height: '100%', objectFit: 'cover' }} 
                                        />
                                    ) : (
                                        <Icon icon="format-image" style={{ color: '#ccd0d4' }} />
                                    )}
                                </div>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <h5 style={{ 
                                        margin: '0 0 4px 0', 
                                        fontSize: '13px', 
                                        lineHeight: '1.4',
                                        fontWeight: '600',
                                        color: '#1e1e1e',
                                        whiteSpace: 'nowrap',
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis'
                                    }}>
                                        {rec.title}
                                    </h5>
                                    <p style={{ 
                                        margin: 0, 
                                        fontSize: '11px', 
                                        lineHeight: '1.5',
                                        color: '#666', 
                                        display: '-webkit-box',
                                        WebkitLineClamp: 2,
                                        WebkitBoxOrient: 'vertical',
                                        overflow: 'hidden'
                                    }}>
                                        {rec.excerpt}
                                    </p>
                                </div>
                            </div>
                            <div style={{ display: 'flex', gap: '8px' }}>
                                <Button 
                                    isSecondary 
                                    isSmall 
                                    href={rec.url} 
                                    target="_blank"
                                    rel="noreferrer"
                                    icon="external"
                                    label={__('View original post in new tab', 'kh-editorial-author')}
                                    showTooltip
                                >
                                    {__('View', 'kh-editorial-author')}
                                </Button>
                                <Button 
                                    isSecondary 
                                    isSmall 
                                    onClick={() => copyToClipboard(rec.url, rec.id)}
                                    icon={copiedId === rec.id ? 'yes' : 'admin-links'}
                                    label={__('Copy link to clipboard for insertion', 'kh-editorial-author')}
                                    showTooltip
                                >
                                    {copiedId === rec.id ? __('Copied!', 'kh-editorial-author') : __('Copy Link', 'kh-editorial-author')}
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </PanelBody>
    );
};

/**
 * SmartSEOPanel Component
 * Surfaces AI-driven SEO insights and automated fixes.
 */
const SmartSEOPanel = ({ postId }) => {
    const [status, setStatus] = useState('idle'); // 'idle' | 'auditing' | 'completed' | 'applying'
    const [jobStatus, setJobStatus] = useState('queued'); // 'queued' | 'processing'
    const [activeJobId, setActiveJobId] = useState(null);
    const [auditData, setAuditData] = useState(null);
    const [keyword, setKeyword] = useState('');
    const [error, setError] = useState('');
    const [allowSchema, setAllowSchema] = useState(false);

    // Initial Hydration: Load existing focus keyword
    useEffect(() => {
        const fetchKeywords = async () => {
            try {
                const response = await apiFetch({
                    path: `editorial/v1/seo/keywords?post_id=${postId}`,
                    method: 'GET'
                });
                if (response.focus_keyword) {
                    setKeyword(response.focus_keyword);
                }
            } catch (err) {
                console.error('Failed to load keywords', err);
            }
        };

        // RESET LOGIC: Clear state and re-hydrate when the article changes
        setStatus('idle');
        setJobStatus('queued');
        setActiveJobId(null);
        setAuditData(null);
        setKeyword('');
        setError('');
        setAllowSchema(false);

        if (postId) {
            fetchKeywords();
        }
    }, [postId]);

    // Polling logic for SEO Audit
    useEffect(() => {
        let pollInterval;
        if (activeJobId && status === 'auditing') {
            pollInterval = setInterval(async () => {
                try {
                    const response = await apiFetch({
                        path: `editorial/v1/seo/audit/status/${activeJobId}`,
                        method: 'GET',
                    });

                    if (response.status === 'completed' && response.data) {
                        setAuditData(response.data);
                        setStatus('completed');
                        setActiveJobId(null);
                        clearInterval(pollInterval);
                    } else if (response.status === 'failed') {
                        setError(response.error || __('Audit failed.', 'kh-editorial-author'));
                        setStatus('idle');
                        setActiveJobId(null);
                        clearInterval(pollInterval);
                    } else if (response.status === 'processing') {
                        setJobStatus('processing');
                    }
                } catch (err) {
                    console.error('SEO Polling error', err);
                }
            }, 3000);
        }
        return () => clearInterval(pollInterval);
    }, [activeJobId, status]);

    const runAudit = async () => {
        try {
            const trimmedKeyword = keyword.trim();
            setError('');
            setStatus('auditing');
            setJobStatus('queued');
            setAuditData(null);

            const response = await apiFetch({
                path: 'editorial/v1/seo/audit',
                method: 'POST',
                data: {
                    post_id: postId,
                    keyword: trimmedKeyword,
                    idempotency_key: `seo-${postId}-${Date.now()}`
                }
            });

            if (response.job_id) {
                setActiveJobId(response.job_id);
            }
        } catch (err) {
            setError(err.message || __('Failed to trigger SEO audit.', 'kh-editorial-author'));
            setStatus('idle');
        }
    };

    const applyFix = async (action) => {
        try {
            setStatus('applying');
            const response = await apiFetch({
                path: 'editorial/v1/seo/apply',
                method: 'POST',
                data: {
                    post_id: postId,
                    actions: [action],
                    idempotency_key: `apply-${postId}-${Date.now()}`,
                    allow_schema: allowSchema
                }
            });

            if (response.success) {
                // DATA INTEGRITY: Synchronize both analysis and summary score
                if (response.analysis) {
                    setAuditData(prev => ({ 
                        ...prev, 
                        analysis: response.analysis,
                        summary: {
                            ...prev.summary,
                            score: response.analysis.overall_score
                        }
                    }));
                }
                // Filter out the applied action from suggestions
                setAuditData(prev => ({
                    ...prev,
                    apply_actions: prev.apply_actions.filter(a => a.action_type !== action.action_type)
                }));
                
                // SAFE GATE: Always reset schema authorization after any attempt
                setAllowSchema(false);
                setStatus('completed');
            } else {
                setError(__('Failed to apply specific SEO fix.', 'kh-editorial-author'));
                setAllowSchema(false);
                setStatus('idle');
            }
        } catch (err) {
            setError(err.message || __('Error applying fix.', 'kh-editorial-author'));
            setAllowSchema(false);
            setStatus('idle');
        }
    };

    const getScoreColor = (score) => {
        if (score >= 75) return '#46b450';
        if (score >= 40) return '#ffb900';
        return '#dc3232';
    };

    const getPriorityIcon = (priority) => {
        switch(priority) {
            case 'high': return <Icon icon="warning" style={{ color: '#dc3232', marginRight: '5px' }} />;
            case 'medium': return <Icon icon="info" style={{ color: '#ffb900', marginRight: '5px' }} />;
            default: return <Icon icon="yes" style={{ color: '#46b450', marginRight: '5px' }} />;
        }
    };

    const getStatusLabel = () => {
        if (status === 'applying') return __('Applying Fix...', 'kh-editorial-author');
        if (status === 'auditing') {
            return jobStatus === 'processing' 
                ? __('AI is Analyzing...', 'kh-editorial-author') 
                : __('Waiting for Agent...', 'kh-editorial-author');
        }
        return __('Run AI SEO Audit', 'kh-editorial-author');
    };

    const score = auditData?.summary?.score || 0;

    return (
        <PanelBody title={__('Smart SEO', 'kh-editorial-author')} initialOpen={false} icon="performance">
            <div style={{ marginBottom: '15px' }}>
                <TextControl
                    label={__('Focus Keyword', 'kh-editorial-author')}
                    value={keyword}
                    onChange={setKeyword}
                    placeholder={__('Enter keyword...', 'kh-editorial-author')}
                    disabled={status === 'auditing' || status === 'applying'}
                />
                <Button 
                    isPrimary 
                    isBusy={status === 'auditing' || status === 'applying'} 
                    onClick={runAudit} 
                    style={{ width: '100%', justifyContent: 'center' }}
                    disabled={status === 'applying' || status === 'auditing'}
                >
                    {getStatusLabel()}
                </Button>
            </div>

            {status === 'auditing' && <ProgressBar value={jobStatus === 'processing' ? 60 : 20} />}
            {error && <Notice status="error" onDismiss={() => setError('')}>{error}</Notice>}

            {auditData && (
                <div className="seo-results">
                    <div style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        justifyContent: 'center', 
                        background: '#f6f7f7', 
                        padding: '15px', 
                        borderRadius: '4px',
                        marginBottom: '15px',
                        borderLeft: `5px solid ${getScoreColor(score)}`
                    }}>
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: '24px', fontWeight: 'bold', color: getScoreColor(score) }}>{score}/100</div>
                            <small>{__('Overall SEO Score', 'kh-editorial-author')}</small>
                        </div>
                    </div>

                    <div className="seo-insights" style={{ marginBottom: '20px' }}>
                        <h4 style={{ borderBottom: '1px solid #eee', paddingBottom: '5px' }}>{__('Strategic Insights', 'kh-editorial-author')}</h4>
                        {[...(auditData.issues || []), ...(auditData.suggestions || [])].map((item, idx) => (
                            <div key={idx} style={{ fontSize: '13px', marginBottom: '8px', display: 'flex', alignItems: 'flex-start' }}>
                                {getPriorityIcon(item.priority)}
                                <div>
                                    <strong>{item.title}:</strong> {item.message}
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="seo-fixes">
                        <h4 style={{ borderBottom: '1px solid #eee', paddingBottom: '5px' }}>{__('Automated Fixes', 'kh-editorial-author')}</h4>
                        {auditData.apply_actions && auditData.apply_actions.length > 0 ? (
                            auditData.apply_actions.map((action, idx) => (
                                <div key={idx} style={{ 
                                    background: '#fff', 
                                    border: '1px solid #ccd0d4', 
                                    padding: '10px', 
                                    marginBottom: '10px',
                                    borderRadius: '4px'
                                }}>
                                    <div style={{ fontSize: '11px', fontWeight: 'bold', textTransform: 'uppercase', color: '#666' }}>
                                        {action.action_type.replace('set_', '').replace('_', ' ')}
                                    </div>
                                    <div style={{ margin: '5px 0', fontSize: '12px', fontStyle: 'italic', wordBreak: 'break-word' }}>
                                        {typeof action.payload.value === 'string' ? action.payload.value : __('Complex Schema Configuration', 'kh-editorial-author')}
                                    </div>
                                    {action.action_type === 'set_schema_config' && (
                                        <CheckboxControl
                                            label={__('Authorize Schema Change', 'kh-editorial-author')}
                                            checked={allowSchema}
                                            onChange={setAllowSchema}
                                        />
                                    )}
                                    <Button 
                                        isSecondary 
                                        isSmall 
                                        onClick={() => applyFix(action)}
                                        disabled={status === 'applying' || (action.action_type === 'set_schema_config' && !allowSchema)}
                                        style={{ marginTop: '5px' }}
                                    >
                                        {status === 'applying' ? __('Applying...', 'kh-editorial-author') : __('Apply Fix', 'kh-editorial-author')}
                                    </Button>
                                </div>
                            ))
                        ) : (
                            <p style={{ fontSize: '12px', color: '#666' }}>{__('No immediate fixes suggested.', 'kh-editorial-author')}</p>
                        )}
                    </div>
                </div>
            )}
        </PanelBody>
    );
};

const WritingStudioApp = () => {
    // UI State
    const [view, setView] = useState('list');
    const [sessions, setSessions] = useState([]);
    const [selectedSession, setSelectedSession] = useState(null);
    const [selectedArticle, setSelectedArticle] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    // Drafting State
    const [status, setStatus] = useState('idle');
    const [activeJobId, setActiveJobId] = useState(null);
    const [draftResult, setDraftResult] = useState(null);
    
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

    // Polling logic for Drafting
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

                    consecutiveErrors = 0;

                    if (job.status === 'completed' && job.response) {
                        // The backend already parses the response for the /author/job endpoint
                        // We set it directly if it's already an object.
                        const finalResponse = (typeof job.response === 'object' && job.response !== null) 
                            ? job.response 
                            : JSON.parse(job.response);

                        setDraftResult(finalResponse);
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
                        setError(__('Lost connection to server.', 'kh-editorial-author'));
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
            const response = await apiFetch({ path: 'editorial/v1/sessions', method: 'GET' });
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
        setDraftResult(null);
        setStatus('idle');
        setInstructions('');
        setError('');
        setActiveJobId(null);
        setSelectedArticle(article);
        setView('workspace');
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
                            {__('No article ideas found in this session.', 'kh-editorial-author')}
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
                
                {selectedArticle?.wp_post_id && (
                    <>
                        <SmartSEOPanel postId={selectedArticle.wp_post_id} />
                        <SmartRecommendationsPanel postId={selectedArticle.wp_post_id} />
                    </>
                )}

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
