(function () {
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { PanelBody, Button, Modal, CheckboxControl, Spinner, SelectControl } = wp.components;
    const { apiFetch } = wp;
    const { useState, useEffect } = wp.element;

    // Wire X-WP-Nonce header for all REST requests
    if (window.khmSeoAgentData && window.khmSeoAgentData.nonce) {
        apiFetch.use(apiFetch.createNonceMiddleware(window.khmSeoAgentData.nonce));
    }

    const ACTION_LABELS = {
        'set_meta_title': 'Update Meta Title',
        'set_meta_description': 'Update Meta Description',
        'set_focus_keyword': 'Set Focus Keyword',
        'set_keywords': 'Update Keywords',
        'set_robots_meta': 'Update Robots Meta',
        'set_schema_config': 'Update Schema Configuration',
    };

    const SCHEMA_OPTIONS = [
        { value: 'article', label: 'Article (Default)' },
        { value: 'techarticle', label: 'Atomic Article (TechArticle)' },
        { value: 'qapage', label: 'QAPage (Answer Cards)' },
        { value: 'videoobject', label: 'VideoObject (YouTube)' },
        { value: 'audioobject', label: 'AudioObject (Podcast)' },
        { value: 'organization', label: 'Organization' },
        { value: 'person', label: 'Person' },
        { value: 'product', label: 'Product' },
        { value: 'breadcrumb', label: 'BreadcrumbList' },
    ];

    const SEOAgentSidebar = () => {
        const [loading, setLoading] = useState(false);
        const [summary, setSummary] = useState(null);
        const [details, setDetails] = useState(null);
        const [showModal, setShowModal] = useState(false);
        const [selectedActions, setSelectedActions] = useState([]);
        const [previewHtml, setPreviewHtml] = useState('');
        const [applyLoading, setApplyLoading] = useState(false);
        const [schemaType, setSchemaType] = useState('article');
        const [schemaLoading, setSchemaLoading] = useState(false);
        const [statusMessage, setStatusMessage] = useState('');
        const [statusType, setStatusType] = useState(''); // 'success' or 'error'
        const [showResult, setShowResult] = useState(false);
        const postId = wp.data.select('core/editor').getCurrentPostId();

        // Load current schema config on mount
        useEffect(() => {
            if (!postId) return;
            setSchemaLoading(true);
            apiFetch({
                path: 'khm-seo-agent/v1/schema-config?post_id=' + postId,
            })
                .then((response) => {
                    const config = response.schema_config || {};
                    setSchemaType(config.type || 'article');
                })
                .catch(() => {
                    setSchemaType('article');
                })
                .finally(() => setSchemaLoading(false));
        }, [postId]);

        const handleSchemaTypeChange = (newType) => {
            setSchemaType(newType);
            setSchemaLoading(true);
            apiFetch({
                path: 'khm-seo-agent/v1/schema-config',
                method: 'POST',
                data: { post_id: postId, schema_type: newType },
            })
                .finally(() => setSchemaLoading(false));
        };

        const runAudit = async () => {
            setLoading(true);
            setSummary(null);
            setDetails(null);
            setPreviewHtml('');
            try {
                const response = await apiFetch({
                    path: 'khm-seo-agent/v1/audit',
                    method: 'POST',
                    data: { post_id: postId },
                });
                if (response.status === 'fallback') {
                    const issues = response.analysis?.technical_issues || [];
                    const suggestions = response.analysis?.suggestions || [];
                    setSummary({
                        issues_total: issues.length,
                        suggestions_total: suggestions.length,
                        score: response.analysis?.overall_score || 0,
                    });
                    setDetails({
                        response,
                        output: response.llm_output || null,
                        jobId: response.job_id,
                    });
                    setShowModal(true);
                    setSelectedActions([]);
                } else {
                    const output = response.llm_output || {};
                    const issues = output.issues || [];
                    const suggestions = output.suggestions || [];
                    const summaryData = output.summary || {};
                    setSummary({
                        issues_total: summaryData.issues_total ?? issues.length,
                        suggestions_total: summaryData.suggestions_total ?? suggestions.length,
                        score: response.analysis?.overall_score || 0,
                    });
                    setDetails({
                        response,
                        output,
                        jobId: response.job_id,
                    });
                    setShowModal(true);
                    setSelectedActions([]);
                }
            } catch (e) {
                const message = e?.message || 'Audit failed. See console.';
                setSummary({ error: message });
                // eslint-disable-next-line no-console
                console.error(e);
            }
            setLoading(false);
        };

        const toggleAction = (action, checked) => {
            if (checked) {
                setSelectedActions((prev) => [...prev, action]);
            } else {
                setSelectedActions((prev) => prev.filter((a) => a !== action));
            }
        };

        const runPreview = async () => {
            setPreviewHtml('');
            setApplyLoading(true);
            try {
                const response = await apiFetch({
                    path: 'khm-seo-agent/v1/preview',
                    method: 'POST',
                    data: { post_id: postId, actions: selectedActions },
                });
                const changes = response.preview || [];
                if (changes.length === 0) {
                    setPreviewHtml('<p>No changes to preview.</p>');
                } else {
                    const rows = changes.map(function (c) {
                        const oldVal = c.old_value || '(empty)';
                        const newVal = c.new_value || '(empty)';
                        var label = ACTION_LABELS[c.action_type] || c.action_type;
                        return '<tr><td><strong>' + label + '</strong></td><td style="text-decoration:line-through;color:#b32d2e;">' + oldVal + '</td><td style="color:#008a20;">' + newVal + '</td></tr>';
                    });
                    setPreviewHtml('<table style="width:100%;border-collapse:collapse;"><thead><tr><th>Action</th><th style="color:#b32d2e;">Before</th><th style="color:#008a20;">After</th></tr></thead><tbody>' + rows.join('') + '</tbody></table>');
                }
            } catch (e) {
                setPreviewHtml('<p>Preview failed. See console.</p>');
                // eslint-disable-next-line no-console
                console.error(e);
            }
            setApplyLoading(false);
        };

        const runApply = async () => {
            if (!details?.jobId) {
                setStatusMessage('No job ID available. Re-run the audit first.');
                setStatusType('error');
                return;
            }
            setApplyLoading(true);
            setStatusMessage('');
            setStatusType('');
            setShowResult(false);
            try {
                const idempotencyKey = (window.crypto && window.crypto.randomUUID)
                    ? window.crypto.randomUUID()
                    : 'seo-agent-' + Date.now();

                const hasSchemaAction = selectedActions.some(function (a) {
                    return a && a.action_type === 'set_schema_config';
                });

                const response = await apiFetch({
                    path: 'khm-seo-agent/v1/apply',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        actions: selectedActions,
                        job_id: details.jobId,
                        idempotency_key: idempotencyKey,
                        confirm_schema_changes: hasSchemaAction ? true : undefined,
                    },
                });
                const applied = response.changes || [];
                const successCount = applied.filter(function (c) { return !c.error; }).length;
                const errorCount = applied.filter(function (c) { return c.error; }).length;
                if (errorCount > 0) {
                    setStatusMessage(errorCount + ' action(s) failed, ' + successCount + ' applied. See console for details.');
                    setStatusType('error');
                } else {
                    setStatusMessage(successCount + ' action(s) applied successfully. Refresh the editor to see updates.');
                    setStatusType('success');
                }
                setShowResult(true);
            } catch (e) {
                const msg = e?.message || 'Apply failed. See console.';
                setStatusMessage(msg);
                setStatusType('error');
                setShowResult(true);
                // eslint-disable-next-line no-console
                console.error(e);
            }
            setApplyLoading(false);
        };

        return (
            <PluginSidebar name="khm-seo-agent" title="SEO Agent">
                <PanelBody title="Audit" initialOpen={true}>
                    <Button isPrimary isBusy={loading} onClick={runAudit}>
                        Run Audit
                    </Button>
                    {summary && !summary.error && (
                        <p style={{ marginTop: '10px' }}>
                            {summary.issues_total} issues · {(details?.output?.apply_actions || []).length} actions · Score {summary.score}
                        </p>
                    )}
                    {summary && summary.error && (
                        <p style={{ marginTop: '10px', color: '#b32d2e' }}>{summary.error}</p>
                    )}
                </PanelBody>
                <PanelBody title="Schema Type" initialOpen={false}>
                    <SelectControl
                        label="Override Schema Type"
                        value={schemaType}
                        options={SCHEMA_OPTIONS}
                        onChange={handleSchemaTypeChange}
                        help="AI-recommended on audit. Manually select to override."
                    />
                    {schemaLoading && <Spinner />}
                </PanelBody>
                {showModal && details && (
                    <Modal
                        title="SEO Agent Audit"
                        onRequestClose={() => setShowModal(false)}
                    >
                        <div>
                            <p>
                                {summary?.issues_total ?? 0} issues · {summary?.suggestions_total ?? 0} suggestions
                            </p>

                            {(details.output?.suggestions || []).length > 0 && (
                                <div style={{ marginBottom: '16px' }}>
                                    <h4>Suggestions</h4>
                                    <ul style={{ margin: '8px 0 0 16px', padding: 0 }}>
                                        {(details.output?.suggestions || []).map(function (s, i) {
                                            return <li key={i} style={{ marginBottom: '4px' }}><strong>{s.title || 'Suggestion'}:</strong> {s.message || ''}</li>;
                                        })}
                                    </ul>
                                </div>
                            )}

                            <h4>Apply Actions</h4>
                            {(details.output?.apply_actions || []).length === 0 && (
                                <p>No apply actions available.</p>
                            )}
                            {(details.output?.apply_actions || []).map((action, index) => (
                                <CheckboxControl
                                    key={index}
                                    label={ACTION_LABELS[action.action_type] || action.action_type}
                                    checked={selectedActions.includes(action)}
                                    onChange={(checked) => toggleAction(action, checked)}
                                />
                            ))}

                            <div style={{ marginTop: '16px' }}>
                                <Button isSecondary disabled={!selectedActions.length || applyLoading} onClick={runPreview}>
                                    Preview
                                </Button>
                                <Button
                                    style={{ marginLeft: '8px' }}
                                    isPrimary
                                    disabled={!selectedActions.length || applyLoading}
                                    onClick={runApply}
                                >
                                    Apply
                                </Button>
                                {applyLoading && <Spinner style={{ marginLeft: '8px' }} />}
                            </div>

                            {previewHtml && (
                                <div style={{ marginTop: '16px' }} dangerouslySetInnerHTML={{ __html: previewHtml }} />
                            )}

                            {statusMessage && (
                                <div style={{
                                    marginTop: '16px',
                                    padding: '10px 12px',
                                    borderRadius: '4px',
                                    backgroundColor: statusType === 'success' ? '#edfaef' : '#fbeaea',
                                    color: statusType === 'success' ? '#008a20' : '#b32d2e',
                                    border: '1px solid ' + (statusType === 'success' ? '#008a20' : '#b32d2e'),
                                }}>
                                    {statusMessage}
                                </div>
                            )}
                        </div>
                    </Modal>
                )}
            </PluginSidebar>
        );
    };

    registerPlugin('khm-seo-agent-sidebar', {
        render: SEOAgentSidebar,
        icon: 'search',
    });

    registerPlugin('khm-seo-agent-sidebar-menu', {
        render: () => (
            <PluginSidebarMoreMenuItem target="khm-seo-agent">
                SEO Agent
            </PluginSidebarMoreMenuItem>
        ),
    });
})();
