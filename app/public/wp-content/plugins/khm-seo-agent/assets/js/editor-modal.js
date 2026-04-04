(function () {
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { PanelBody, Button, Modal, CheckboxControl, Spinner } = wp.components;
    const { apiFetch } = wp;
    const { useState } = wp.element;

    const getFallbackReason = (details) => {
        if (!details || !details.response || details.response.status !== 'fallback') {
            return '';
        }
        const code = details.response?.error?.code || '';
        const message = details.response?.error?.message || '';
        if (code && message) {
            return `${code}: ${message}`;
        }
        return message || code || 'Fallback mode was used.';
    };

    const SEOAgentSidebar = () => {
        const [loading, setLoading] = useState(false);
        const [summary, setSummary] = useState(null);
        const [details, setDetails] = useState(null);
        const [showModal, setShowModal] = useState(false);
        const [selectedActions, setSelectedActions] = useState([]);
        const [previewHtml, setPreviewHtml] = useState('');
        const [applyLoading, setApplyLoading] = useState(false);
        const postId = wp.data.select('core/editor').getCurrentPostId();

        const pollAuditStatus = async (jobId) => {
            try {
                const response = await apiFetch({
                    path: `khm-seo-agent/v1/audit/status?job_id=${jobId}`,
                    method: 'GET',
                });

                const output = response.llm_output || {};
                const issues = output.issues || [];
                const suggestions = output.suggestions || [];
                const summaryData = output.summary || {};
                setSummary({
                    issues_total: summaryData.issues_total ?? issues.length,
                    suggestions_total: summaryData.suggestions_total ?? suggestions.length,
                    score: summary?.score ?? 0,
                });
                setDetails((prev) => ({
                    ...(prev || {}),
                    output,
                    jobId,
                }));
            } catch (e) {
                const message = e?.message || 'Audit status failed. See console.';
                setSummary({ error: message });
                // eslint-disable-next-line no-console
                console.error(e);
            }
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
                if (response.status === 'queued') {
                    setSummary({
                        issues_total: 0,
                        suggestions_total: 0,
                        score: response.analysis?.overall_score || 0,
                    });
                    setDetails({
                        response,
                        output: null,
                        jobId: response.job_id,
                    });
                    setShowModal(true);
                    setSelectedActions([]);
                    setTimeout(() => pollAuditStatus(response.job_id), 2000);
                } else if (response.status === 'fallback') {
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
                setPreviewHtml(response.preview_html || '');
            } catch (e) {
                setPreviewHtml('<p>Preview failed. See console.</p>');
                // eslint-disable-next-line no-console
                console.error(e);
            }
            setApplyLoading(false);
        };

        const runApply = async () => {
            if (!details?.jobId) return;
            setApplyLoading(true);
            try {
                const idempotencyKey = (window.crypto && window.crypto.randomUUID)
                    ? window.crypto.randomUUID()
                    : 'seo-agent-' + Date.now();

                await apiFetch({
                    path: 'khm-seo-agent/v1/apply',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        actions: selectedActions,
                        job_id: details.jobId,
                        idempotency_key: idempotencyKey,
                    },
                });
                setShowModal(false);
            } catch (e) {
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
                            {summary.issues_total} issues · {summary.suggestions_total} suggestions · Score {summary.score}
                        </p>
                    )}
                    {summary && summary.error && (
                        <p style={{ marginTop: '10px', color: '#b32d2e' }}>{summary.error}</p>
                    )}
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
                            {details.response?.status === 'fallback' && (
                                <p style={{ color: '#b32d2e', marginTop: '8px' }}>
                                    Fallback mode: {getFallbackReason(details)}
                                </p>
                            )}
                            <h4>Suggestions</h4>
                            {(details.output?.apply_actions || []).length === 0 && (
                                <p>
                                    No apply actions available
                                    {details.response?.status === 'fallback' ? ' in fallback mode.' : '.'}
                                </p>
                            )}
                            {(details.output?.apply_actions || []).map((action, index) => (
                                <CheckboxControl
                                    key={index}
                                    label={`${action.action_type}`}
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
