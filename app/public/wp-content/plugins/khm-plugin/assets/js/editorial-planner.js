(function () {
// Editorial Planner Dashboard React App
const { useState, useEffect, useRef } = wp.element;
const {
    Button,
    Modal,
    SelectControl,
    FormTokenField,
    Spinner,
    Notice,
    ProgressBar,
    Card,
    CardBody,
    CardHeader,
    TextControl,
    RangeControl,
    ToggleControl,
} = wp.components;
const { dispatch } = wp.data;

const TOPIC_OPTIONS = [
    { label: 'Manufacturing', value: 'Manufacturing' },
    { label: 'Field Service', value: 'Field Service' },
    { label: 'Logistics', value: 'Logistics' },
    { label: 'Energy', value: 'Energy' },
    { label: 'Retail', value: 'Retail' },
];

const THINKING_PHRASES = [
    'Thinking',
    'Musing',
    'Investigating',
    'Digging',
    'Digging Deeper',
    'Permutating',
    'Transmographying',
    'Dotting Tees',
    'Ruminating',
    'Asphyxiating',
    'Crossing Eyes',
    'Geniusing',
    'Almost there',
];

const apiFetch = (options) =>
    wp.apiFetch({
        ...options,
        headers: {
            'X-WP-Nonce': dualGptData.nonce,
            ...(options.headers || {}),
        },
    });

const EditorialPlannerApp = () => {
    const [sessions, setSessions] = useState([]);
    const [loadingSessions, setLoadingSessions] = useState(true);
    const [sessionsError, setSessionsError] = useState('');

    const [startModalOpen, setStartModalOpen] = useState(false);
    const [selectedTopic, setSelectedTopic] = useState(TOPIC_OPTIONS[0].value);
    const [includes, setIncludes] = useState([]);
    const [excludes, setExcludes] = useState([]);
    const [starting, setStarting] = useState(false);

    const [detailModalOpen, setDetailModalOpen] = useState(false);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState('');
    const [sessionDetail, setSessionDetail] = useState(null);

    const [previewArticle, setPreviewArticle] = useState(null);
    const [frameworkPreview, setFrameworkPreview] = useState(null);
    const [frameworkLoading, setFrameworkLoading] = useState({});
    const [frameworkProgress, setFrameworkProgress] = useState({});
    const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);
    const [authorPreview, setAuthorPreview] = useState(null);
    const [authorProgress, setAuthorProgress] = useState({});
    const [authorLoading, setAuthorLoading] = useState({});
    const authorStatusRef = useRef({});
    const [phase4RerunLoading, setPhase4RerunLoading] = useState(false);
    const [phase3RerunLoading, setPhase3RerunLoading] = useState(false);
    const [phase2RerunLoading, setPhase2RerunLoading] = useState(false);
    const [phase1RerunLoading, setPhase1RerunLoading] = useState(false);
    const [expandedPhases, setExpandedPhases] = useState({});
    const [synopsisModalOpen, setSynopsisModalOpen] = useState(false);
    const [synopsisPlan, setSynopsisPlan] = useState({});
    const [synopsisPlanLoading, setSynopsisPlanLoading] = useState(false);
    const [synopsisPlanError, setSynopsisPlanError] = useState('');
    const [synopsisGenerateLoading, setSynopsisGenerateLoading] = useState(false);
    const [synopsisTotal, setSynopsisTotal] = useState(20);
    const [focusLevel, setFocusLevel] = useState(50);
    const [focusDirty, setFocusDirty] = useState(false);
    const [thinkingPhraseIndex, setThinkingPhraseIndex] = useState(0);
    const showFocusControls = true;

    // URL-based routing: check if we're viewing a specific session detail
    const params = new URLSearchParams(window.location.search);
    const viewingSessionId = params.get('session') || params.get('session_id');

    const navigateToSession = (sessionId) => {
        if (!sessionId) {
            return;
        }
        window.history.pushState(null, '', `?session_id=${sessionId}`);
        openSessionDetail(sessionId);
    };

    const navigateBack = () => {
        window.history.pushState(null, '', window.location.pathname);
        setDetailModalOpen(false);
        setSessionDetail(null);
        loadSessions();
    };

    useEffect(() => {
        loadSessions();
    }, []);

    useEffect(() => {
        if (!viewingSessionId) {
            return;
        }
        openSessionDetail(viewingSessionId, { silent: true });
    }, [viewingSessionId]);

    useEffect(() => {
        if (!showFocusControls) {
            return;
        }
        if (sessionDetail?.meta?.focus_level != null && !focusDirty) {
            setFocusLevel(Number(sessionDetail.meta.focus_level));
        }
    }, [sessionDetail?.meta?.focus_level, focusDirty, showFocusControls]);

    const loadSessions = async () => {
        try {
            setLoadingSessions(true);
            setSessionsError('');
            const data = await apiFetch({
                path: 'dual-gpt/v1/sessions?limit=20',
                method: 'GET',
            });
            setSessions(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Failed to load sessions:', error);
            setSessionsError(error.message || 'Failed to load sessions.');
        } finally {
            setLoadingSessions(false);
        }
    };

    const startNewSession = async () => {
        if (!selectedTopic) {
            setSessionsError('Please select a top-line topic.');
            return;
        }

        try {
            setStarting(true);
            setSessionsError('');

            const sessionPayload = {
                role: 'research',
                preset_id: 'research-default',
                title: selectedTopic,
                meta: {
                    topic: selectedTopic,
                    includes,
                    excludes,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
                },
                idempotency_key: `planner-${Date.now()}`,
            };

            const sessionResponse = await apiFetch({
                path: 'dual-gpt/v1/sessions',
                method: 'POST',
                data: sessionPayload,
            });

            if (!sessionResponse || !sessionResponse.session_id) {
                throw new Error('Session creation did not return a session id.');
            }

            await apiFetch({
                path: 'dual-gpt/v1/planner/run',
                method: 'POST',
                data: {
                    session_id: sessionResponse.session_id,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
                },
            });

            dispatch('core/notices').createNotice(
                'success',
                'Planning session created and queued successfully.',
                { type: 'snackbar' }
            );

            setStartModalOpen(false);
            setIncludes([]);
            setExcludes([]);
            setSelectedTopic(TOPIC_OPTIONS[0].value);
            await loadSessions();
            await openSessionDetail(sessionResponse.session_id);
        } catch (error) {
            console.error('Failed to start session:', error);
            setSessionsError(error.message || 'Failed to start session.');
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Failed to start session.',
                { type: 'snackbar' }
            );
        } finally {
            setStarting(false);
        }
    };

    const handleAuthorStatusTransitions = (nextDetail) => {
        const nextArticles = nextDetail?.meta?.articles || [];
        const previous = authorStatusRef.current || {};
        const nextMap = {};

        nextArticles.forEach((article) => {
            const statusRaw = article?.author?.status || 'pending';
            const status = statusRaw === 'completed' ? 'complete' : statusRaw;
            nextMap[article.id] = status;

            const prevStatus = previous[article.id];
            if (prevStatus === 'running' && status === 'complete') {
                dispatch('core/notices').createNotice(
                    'success',
                    `Author draft ready for "${article.title || article.headline || 'Article'}".`,
                    { type: 'snackbar' }
                );
                setAuthorProgress((prev) => {
                    if (!prev[article.id]) {
                        return prev;
                    }
                    return { ...prev, [article.id]: { ...prev[article.id], percent: 100 } };
                });
                if (article.author?.edit_url) {
                    const shouldOpen = window.confirm('Author draft is ready. Open in editor now?');
                    if (shouldOpen) {
                        window.open(article.author.edit_url, '_blank');
                    }
                }
            }

            if (prevStatus === 'running' && status === 'failed') {
                dispatch('core/notices').createNotice(
                    'error',
                    article.author?.error_message ||
                        `Author draft failed for "${article.title || article.headline || 'Article'}".`,
                    { type: 'snackbar' }
                );
            }
        });

        authorStatusRef.current = nextMap;
    };

    const openSessionDetail = async (sessionId, options = {}) => {
        const { silent = false } = options;
        try {
            if (!silent) {
                setDetailModalOpen(true);
                setDetailLoading(true);
                setDetailError('');
                setFocusDirty(false);
            }
            const data = await apiFetch({
                path: `dual-gpt/v1/sessions/${sessionId}`,
                method: 'GET',
            });
            handleAuthorStatusTransitions(data);
            setSessionDetail(data);
            if (data?.meta?.articles && Array.isArray(data.meta.articles)) {
                setFrameworkProgress((prev) => {
                    const next = { ...prev };
                    data.meta.articles.forEach((article) => {
                        const status = article?.framework?.status;
                        if (status === 'running' || status === 'queued') {
                            if (!next[article.id]) {
                                next[article.id] = { startedAt: Date.now(), percent: 5 };
                            }
                        } else if (next[article.id]) {
                            delete next[article.id];
                        }
                    });
                    return next;
                });
                setAuthorProgress((prev) => {
                    const next = { ...prev };
                    data.meta.articles.forEach((article) => {
                        const status = article?.author?.status;
                        if (status === 'running' || status === 'queued') {
                            if (!next[article.id]) {
                                next[article.id] = { startedAt: Date.now(), percent: 5 };
                            }
                        } else if (next[article.id]) {
                            delete next[article.id];
                        }
                    });
                    return next;
                });
            }
        } catch (error) {
            console.error('Failed to load session detail:', error);
            if (!silent) {
                setDetailError(error.message || 'Failed to load session detail.');
            }
        } finally {
            if (!silent) {
                setDetailLoading(false);
            }
        }
    };

    const refreshSessionDetail = async () => {
        if (!sessionDetail || !sessionDetail.id) {
            return;
        }
        await openSessionDetail(sessionDetail.id, { silent: true });
    };

    useEffect(() => {
        if (!detailModalOpen || !autoRefreshEnabled) {
            return undefined;
        }
        const interval = setInterval(() => {
            setFrameworkProgress((prev) => {
                const next = { ...prev };
                Object.entries(next).forEach(([articleId, entry]) => {
                    const elapsed = Date.now() - entry.startedAt;
                    const percent = Math.min(95, Math.max(5, Math.round((elapsed / 60000) * 90) + 5));
                    next[articleId] = { ...entry, percent };
                });
                return next;
            });
        }, 1000);
        return () => clearInterval(interval);
    }, [detailModalOpen]);

    useEffect(() => {
        if (!detailModalOpen) {
            return undefined;
        }
        const interval = setInterval(() => {
            setAuthorProgress((prev) => {
                const next = { ...prev };
                Object.entries(next).forEach(([articleId, entry]) => {
                    const elapsed = Date.now() - entry.startedAt;
                    const percent = Math.min(95, Math.max(5, Math.round((elapsed / 60000) * 90) + 5));
                    next[articleId] = { ...entry, percent };
                });
                return next;
            });
        }, 1000);
        return () => clearInterval(interval);
    }, [detailModalOpen]);

    useEffect(() => {
        if (!detailModalOpen) {
            return undefined;
        }
        const hasRunningArticles =
            sessionDetail?.meta?.articles?.some((article) =>
                ['running', 'queued'].includes(article?.framework?.status)
            ) ||
            sessionDetail?.meta?.articles?.some((article) =>
                ['running', 'queued'].includes(article?.author?.status)
            ) ||
            false;
        const hasRunningPhase =
            ['running', 'queued', 'processing'].includes(
                sessionDetail?.meta?.phases?.phase1?.status
            ) ||
            ['running', 'queued', 'processing'].includes(
                sessionDetail?.meta?.phases?.phase2?.status
            ) ||
            ['running', 'queued', 'processing'].includes(
                sessionDetail?.meta?.phases?.phase3?.status
            ) ||
            ['running', 'queued', 'processing'].includes(
                sessionDetail?.meta?.phases?.phase4?.status
            ) ||
            false;
        const hasRunning = hasRunningArticles || hasRunningPhase;
        if (!hasRunning) {
            return undefined;
        }
        let cancelled = false;
        const poll = async () => {
            if (cancelled) {
                return;
            }
            await refreshSessionDetail();
        };
        // Poll faster (5s) for phases, slower (30s) for articles
        const pollInterval = hasRunningPhase ? 5000 : 30000;
        const interval = setInterval(poll, pollInterval);
        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [detailModalOpen, autoRefreshEnabled, sessionDetail?.meta?.articles, sessionDetail?.meta?.phases]);

    useEffect(() => {
        if (!detailModalOpen) {
            return undefined;
        }
        const hasRunningPhase =
            ['running', 'queued', 'processing'].includes(
                sessionDetail?.meta?.phases?.phase1?.status
            ) ||
            ['running', 'queued', 'processing'].includes(
                sessionDetail?.meta?.phases?.phase2?.status
            ) ||
            ['running', 'queued', 'processing'].includes(
                sessionDetail?.meta?.phases?.phase3?.status
            ) ||
            ['running', 'queued', 'processing'].includes(
                sessionDetail?.meta?.phases?.phase4?.status
            ) ||
            false;
        const isSynopsisGenerating = synopsisGenerateLoading === true;
        if (!hasRunningPhase && !isSynopsisGenerating) {
            setThinkingPhraseIndex(0);
            return undefined;
        }
        const interval = setInterval(() => {
            setThinkingPhraseIndex((prev) => (prev + 1) % THINKING_PHRASES.length);
        }, 1500);
        return () => clearInterval(interval);
    }, [detailModalOpen, sessionDetail?.meta?.phases, synopsisGenerateLoading]);

    const getFocusLabel = (value) => {
        if (value >= 70) {
            return 'Focused';
        }
        if (value <= 30) {
            return 'Broad';
        }
        return 'Balanced';
    };

    const estimateSynopses = (meta, value) => {
        if (!meta) {
            return { min: 0, max: 0, estimate: 0, topics: 0 };
        }
        const phase1Trends = meta?.phases?.phase1?.payload?.trends?.length || 0;
        const phase1Keywords =
            meta?.phase1?.candidate_keywords?.length ||
            meta?.phases?.phase1?.payload?.candidate_keywords?.length ||
            0;
        const phase2Metrics =
            meta?.phase2?.keyword_metrics?.length ||
            meta?.phases?.phase2?.payload?.keyword_metrics?.length ||
            0;
        const phase3Topics = meta?.phases?.phase3?.payload?.prioritized_topics?.length || 0;
        const phase4Topics = meta?.phases?.phase4?.payload?.validated_topics?.length || 0;
        const phase4Citations = (meta?.phases?.phase4?.payload?.validated_topics || []).reduce(
            (total, item) => total + (Array.isArray(item?.citations) ? item.citations.length : 0),
            0
        );

        const topics = Math.max(phase4Topics, phase3Topics, 1);
        const breadthScore =
            phase1Trends + Math.round(phase2Metrics / 3) + Math.round(phase1Keywords / 4);
        const depthScore = Math.round(phase4Citations / Math.max(1, topics));
        const focusFactor = 1 - (Math.min(100, Math.max(0, value)) / 100) * 0.35;
        const raw = Math.round((topics * 2 + breadthScore + depthScore) * focusFactor);
        const estimate = Math.min(40, Math.max(topics, raw));
        const variance = Math.max(2, Math.round(estimate * 0.2));
        return {
            estimate,
            topics,
            min: Math.max(topics, estimate - variance),
            max: estimate + variance,
        };
    };

    const handleRegenerateFramework = async (article, index) => {
        if (!sessionDetail || !sessionDetail.id) {
            dispatch('core/notices').createNotice(
                'error',
                'Session detail not loaded yet. Please refresh and try again.',
                { type: 'snackbar' }
            );
            return;
        }

        try {
            console.log('[Planner] Generate framework click', {
                sessionId: sessionDetail.id,
                articleId: article?.id,
            });
            setFrameworkLoading((prev) => ({ ...prev, [index]: true }));
            if (article?.id) {
                setFrameworkProgress((prev) => ({
                    ...prev,
                    [article.id]: { startedAt: Date.now(), percent: 5 },
                }));
            }
            await apiFetch({
                path: 'dual-gpt/v1/planner/run-framework',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    article_id: article.id,
                    force: article?.framework?.status !== 'pending',
                },
            });

            dispatch('core/notices').createNotice(
                'success',
                'Framework regeneration started.',
                { type: 'snackbar' }
            );

            await refreshSessionDetail();
        } catch (error) {
            console.error('Framework regeneration failed:', error);
            const errorMessage =
                error?.code === 'budget_exceeded'
                    ? 'No credits remaining. Please top up or reset your token budget.'
                    : error.message || 'Framework regeneration failed.';
            dispatch('core/notices').createNotice('error', errorMessage, { type: 'snackbar' });
        } finally {
            setFrameworkLoading((prev) => ({ ...prev, [index]: false }));
        }
    };

    const handleRerunPhase4 = async () => {
        if (!sessionDetail || !sessionDetail.id) {
            return;
        }

        try {
            setPhase4RerunLoading(true);
            await apiFetch({
                path: 'dual-gpt/v1/planner/phase4',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
                },
            });

            dispatch('core/notices').createNotice(
                'success',
                'Research Phase 4 queued. Refresh in a moment for validation output.',
                { type: 'snackbar' }
            );

            await refreshSessionDetail();
        } catch (error) {
            console.error('Phase 4 rerun failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Phase 4 rerun failed.',
                { type: 'snackbar' }
            );
        } finally {
            setPhase4RerunLoading(false);
        }
    };

    const handleRerunPhase2 = async () => {
        if (!sessionDetail || !sessionDetail.id) {
            return;
        }

        if (sessionDetail?.meta?.phases?.phase1?.status !== 'completed') {
            dispatch('core/notices').createNotice(
                'warning',
                'Research Phase 1 must complete before Qualification runs.',
                { type: 'snackbar' }
            );
            return;
        }

        try {
            setPhase2RerunLoading(true);
            await apiFetch({
                path: 'dual-gpt/v1/planner/phase2-qualification',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
                },
            });

            dispatch('core/notices').createNotice(
                'success',
                'Research Phase 2 refreshed. Review the updated qualification data.',
                { type: 'snackbar' }
            );

            await refreshSessionDetail();
        } catch (error) {
            console.error('Phase 2 rerun failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Phase 2 rerun failed.',
                { type: 'snackbar' }
            );
        } finally {
            setPhase2RerunLoading(false);
        }
    };

    const handleRerunPhase3 = async () => {
        if (!sessionDetail || !sessionDetail.id) {
            return;
        }

        try {
            setPhase3RerunLoading(true);
            await apiFetch({
                path: 'dual-gpt/v1/planner/phase2',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
                },
            });

            dispatch('core/notices').createNotice(
                'success',
                'Research Phase 3 queued. Refresh in a moment for the deep dive.',
                { type: 'snackbar' }
            );

            await refreshSessionDetail();
        } catch (error) {
            console.error('Phase 3 rerun failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Phase 3 rerun failed.',
                { type: 'snackbar' }
            );
        } finally {
            setPhase3RerunLoading(false);
        }
    };

    const handleRerunPhase1 = async () => {
        if (!sessionDetail || !sessionDetail.id) {
            return;
        }

        try {
            setPhase1RerunLoading(true);
            await apiFetch({
                path: 'dual-gpt/v1/planner/phase1',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
                },
            });

            dispatch('core/notices').createNotice(
                'success',
                'Phase 1 queued. Refresh in a moment for updated discovery.',
                { type: 'snackbar' }
            );

            await refreshSessionDetail();
        } catch (error) {
            console.error('Phase 1 rerun failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Phase 1 rerun failed.',
                { type: 'snackbar' }
            );
        } finally {
            setPhase1RerunLoading(false);
        }
    };

    const openSynopsisModal = async () => {
        if (!sessionDetail || !sessionDetail.id) {
            return;
        }
        const targetTotal = synopsisEstimate?.estimate || synopsisTotal;
        if (targetTotal !== synopsisTotal) {
            setSynopsisTotal(targetTotal);
        }
        setSynopsisModalOpen(true);
        setSynopsisPlanLoading(true);
        setSynopsisPlanError('');
        try {
            const data = await apiFetch({
                path: 'dual-gpt/v1/planner/synopsis-plan',
                method: 'POST',
                data: { session_id: sessionDetail.id, total: targetTotal },
            });
            setSynopsisPlan(data.plan || {});
        } catch (error) {
            console.error('Failed to load synopsis plan:', error);
            setSynopsisPlanError(error.message || 'Failed to load synopsis plan.');
        } finally {
            setSynopsisPlanLoading(false);
        }
    };

    const updateSynopsisCount = (topic, value) => {
        const count = Math.max(0, parseInt(value || 0, 10));
        setSynopsisPlan((prev) => ({ ...prev, [topic]: count }));
    };

    const handleGenerateSynopses = async () => {
        if (!sessionDetail || !sessionDetail.id) {
            dispatch('core/notices').createNotice(
                'error',
                'Session detail not loaded yet. Please refresh and try again.',
                { type: 'snackbar' }
            );
            return;
        }

        try {
            console.log('[Planner] Generate synopses click', {
                sessionId: sessionDetail.id,
                plan: synopsisPlan,
            });
            setSynopsisGenerateLoading(true);
            dispatch('core/notices').createNotice(
                'info',
                'Generating synopses...',
                { type: 'snackbar' }
            );
            await apiFetch({
                path: 'dual-gpt/v1/planner/synopses',
                method: 'POST',
                data: { session_id: sessionDetail.id, plan: synopsisPlan },
            });

            dispatch('core/notices').createNotice(
                'success',
                'Article synopses queued. Refreshing to display results...',
                { type: 'snackbar' }
            );

            // Keep the synopsis modal open with loading state while generating
            // Poll for updates until synopses appear
            let pollCount = 0;
            const maxPolls = 120; // 120 * 2.5s = 5 minutes max
            const pollSynopses = async () => {
                pollCount++;
                await refreshSessionDetail();
                const articlesCount = sessionDetail?.meta?.articles?.length || 0;
                if (articlesCount > 0 || pollCount >= maxPolls) {
                    setSynopsisGenerateLoading(false);
                    setSynopsisModalOpen(false);
                    if (articlesCount > 0) {
                        dispatch('core/notices').createNotice(
                            'success',
                            `${articlesCount} article synopses generated successfully.`,
                            { type: 'snackbar' }
                        );
                    }
                    return;
                }
                setTimeout(pollSynopses, 2500);
            };
            setTimeout(pollSynopses, 2500);
        } catch (error) {
            console.error('Synopsis generation failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Synopsis generation failed.',
                { type: 'snackbar' }
            );
            setSynopsisGenerateLoading(false);
        }
    };

    const handleExportValidation = async () => {
        if (!sessionDetail || !sessionDetail.id) {
            return;
        }

        try {
            const data = await apiFetch({
                path: 'dual-gpt/v1/planner/export',
                method: 'POST',
                data: { session_id: sessionDetail.id },
            });

            const filename = data.filename || 'validation-export.html';
            const blob = new Blob([data.html || ''], { type: 'text/html' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Validation export failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Validation export failed.',
                { type: 'snackbar' }
            );
        }
    };

    const openPrintWindow = (html) => {
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            return;
        }
        printWindow.document.open();
        printWindow.document.write(html || '');
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    };

    const handleExportSynopses = async () => {
        if (!sessionDetail || !sessionDetail.id) {
            return;
        }

        try {
            const data = await apiFetch({
                path: 'dual-gpt/v1/planner/export-synopses',
                method: 'POST',
                data: { session_id: sessionDetail.id },
            });
            openPrintWindow(data.html || '');
        } catch (error) {
            console.error('Synopses export failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Synopses export failed.',
                { type: 'snackbar' }
            );
        }
    };

    const handleExportFramework = async (article) => {
        if (!sessionDetail || !sessionDetail.id || !article?.id) {
            return;
        }

        try {
            const data = await apiFetch({
                path: 'dual-gpt/v1/planner/export-framework',
                method: 'POST',
                data: { session_id: sessionDetail.id, article_id: article.id },
            });
            openPrintWindow(data.html || '');
        } catch (error) {
            console.error('Framework export failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Framework export failed.',
                { type: 'snackbar' }
            );
        }
    };

    const handleRunAuthorAgent = async (article) => {
        if (!sessionDetail || !sessionDetail.id || !article?.id) {
            console.warn('[Planner] Run Author blocked', {
                hasSession: !!sessionDetail,
                sessionId: sessionDetail?.id,
                articleId: article?.id,
            });
            dispatch('core/notices').createNotice(
                'error',
                'Author agent could not start: missing session or article data.',
                { type: 'snackbar' }
            );
            return;
        }

        try {
            setAuthorLoading((prev) => ({ ...prev, [article.id]: true }));
            console.log('[Planner] Run Author click', {
                sessionId: sessionDetail.id,
                articleId: article.id,
            });
            const data = await apiFetch({
                path: 'dual-gpt/v1/planner/run-author',
                method: 'POST',
                data: { session_id: sessionDetail.id, article_id: article.id },
            });
            console.log('[Planner] Author job queued', data);
            dispatch('core/notices').createNotice(
                'success',
                data?.job_id ? `Author agent started (job ${data.job_id}).` : 'Author agent started.',
                { type: 'snackbar' }
            );
            setSessionDetail((prev) => {
                if (!prev?.meta?.articles) {
                    return prev;
                }
                const nextArticles = prev.meta.articles.map((item) => {
                    if (item.id !== article.id) {
                        return item;
                    }
                    return {
                        ...item,
                        author: {
                            ...(item.author || {}),
                            status: 'running',
                        },
                    };
                });
                return {
                    ...prev,
                    meta: {
                        ...prev.meta,
                        articles: nextArticles,
                    },
                };
            });
            if (article?.id) {
                setAuthorProgress((prev) => ({
                    ...prev,
                    [article.id]: { startedAt: Date.now(), percent: 5 },
                }));
            }
            await refreshSessionDetail();
        } catch (error) {
            console.error('Author agent failed:', error);
            const errorMessage =
                error?.code === 'budget_exceeded'
                    ? 'No credits remaining. Please top up or reset your token budget.'
                    : error.message || 'Author agent failed.';
            dispatch('core/notices').createNotice('error', errorMessage, { type: 'snackbar' });
        } finally {
            setAuthorLoading((prev) => ({ ...prev, [article.id]: false }));
        }
    };

    const handleExportAuthorDraft = (article) => {
        if (!article?.author?.output) {
            return;
        }
        const title = article.title || 'Draft';
        const draft = article.author.output?.draft || article.author.output?.content || '';
        const html = `<!doctype html><html><head><meta charset="utf-8"><title>${title}</title><style>body{font-family:Arial,sans-serif;margin:24px;line-height:1.6;}h1{font-size:24px;}</style></head><body><h1>${title}</h1><div>${(draft || '').replace(/\\n/g, '<br>')}</div></body></html>`;
        openPrintWindow(html);
    };

    const renderPhaseSummaries = () => {
        const phases = sessionDetail?.meta?.phases || {};
        const phaseOrder = ['phase1', 'phase2', 'phase3', 'phase4'];

        const renderList = (items) => {
            if (!items || !items.length) {
                return null;
            }
            return wp.element.createElement(
                'ul',
                { style: { marginTop: '6px', marginBottom: 0, paddingLeft: '20px', listStyleType: 'disc' } },
                items.map((item, idx) => wp.element.createElement('li', { key: idx }, item))
            );
        };

        const renderLinkList = (links) => {
            if (!links || !links.length) {
                return null;
            }
            return wp.element.createElement(
                'ul',
                { style: { marginTop: '6px', marginBottom: 0 } },
                links.map((link, idx) =>
                    wp.element.createElement(
                        'li',
                        { key: idx },
                        wp.element.createElement(
                            'a',
                            { href: link.url, target: '_blank', rel: 'noreferrer' },
                            link.title || link.url
                        )
                    )
                )
            );
        };

        const renderTrendBlocks = (trends) => {
            if (!Array.isArray(trends) || !trends.length) {
                return null;
            }
            const normalizeText = (value) => {
                if (Array.isArray(value)) {
                    return value.filter(Boolean).join(' ');
                }
                if (value && typeof value === 'object') {
                    return Object.values(value).filter(Boolean).join(' ');
                }
                if (value == null) {
                    return '';
                }
                return String(value).replace(/\s0$/, '').trim();
            };
            return trends.map((trend, idx) => {
                const insightPoints = Array.isArray(trend.insight_points) && trend.insight_points.length
                    ? trend.insight_points
                    : trend.insight
                    ? [trend.insight]
                    : [];
                const implications = Array.isArray(trend.implications_for_articles)
                    ? trend.implications_for_articles
                    : [];
                const evidencePoints = Array.isArray(trend.evidence) ? trend.evidence : [];
                const citationTitles = Array.isArray(trend.citations)
                    ? trend.citations.map((citation) => citation.title || citation.url || 'Citation')
                    : [];
                const whyMatters = normalizeText(trend.why_it_matters);
                return wp.element.createElement(
                    'div',
                    { key: idx, style: { marginTop: '16px' } },
                    wp.element.createElement('h2', { style: { margin: '0 0 6px' } }, trend.title || `Trend ${idx + 1}`),
                    renderList(insightPoints),
                    whyMatters &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '10px' } },
                            wp.element.createElement('h3', { style: { margin: '0 0 4px' } }, 'Why this matters'),
                            wp.element.createElement('p', { style: { margin: 0 } }, whyMatters)
                        ),
                    trend.strategic_implication &&
                        wp.element.createElement(
                            'p',
                            { style: { marginTop: '6px' } },
                            `Strategic implication: ${trend.strategic_implication}`
                        ),
                    implications.length &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '6px' } },
                            wp.element.createElement('h3', { style: { margin: '0 0 4px' } }, 'Implications for articles'),
                            renderList(implications)
                        ),
                    citationTitles.length
                        ? wp.element.createElement(
                              'div',
                              { style: { marginTop: '6px' } },
                              wp.element.createElement('h3', { style: { margin: '0 0 4px' } }, 'Citations'),
                              renderList(citationTitles)
                          )
                        : null,
                    evidencePoints.length
                        ? wp.element.createElement(
                              'div',
                              { style: { marginTop: '6px' } },
                              wp.element.createElement('h3', { style: { margin: '0 0 4px' } }, 'Supporting evidence'),
                              renderList(
                                  evidencePoints.map((item) => {
                                      const label = item.stat_or_finding || item.evidence || '';
                                      const source = item.source || item.url || '';
                                      const year = item.year ? ` (${item.year})` : '';
                                      return `${label}${source ? ` — ${source}${year}` : ''}`;
                                  })
                              )
                          )
                        : null
                    ,
                    wp.element.createElement('div', {
                        style: {
                            marginTop: '14px',
                            borderBottom: '0.5pt solid #e2e4e7',
                        },
                    })
                );
            });
        };

        const renderPrioritizedTopics = (topics) => {
            if (!Array.isArray(topics) || !topics.length) {
                return null;
            }
            return topics.map((topic, idx) => {
                const findings = Array.isArray(topic.key_findings) ? topic.key_findings : [];
                const citations = Array.isArray(topic.citations) ? topic.citations : [];
                const keywords = Array.isArray(topic.keywords) ? topic.keywords : [];
                return wp.element.createElement(
                    'div',
                    { key: `prioritized-${idx}`, style: { marginTop: '16px' } },
                    wp.element.createElement('h2', { style: { margin: '0 0 6px' } }, topic.topic || `Topic ${idx + 1}`),
                    topic.why_now &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '6px' } },
                            wp.element.createElement('h3', { style: { margin: '0 0 4px' } }, 'Why now'),
                            wp.element.createElement('p', { style: { margin: 0 } }, topic.why_now)
                        ),
                    findings.length
                        ? wp.element.createElement(
                              'div',
                              { style: { marginTop: '6px' } },
                              wp.element.createElement('h3', { style: { margin: '0 0 4px' } }, 'Key findings'),
                              renderList(findings)
                          )
                        : null,
                    keywords.length
                        ? wp.element.createElement(
                              'div',
                              { style: { marginTop: '6px' } },
                              wp.element.createElement('h3', { style: { margin: '0 0 4px' } }, 'Keywords'),
                              renderList(keywords)
                          )
                        : null,
                    topic.content_opportunity &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '6px' } },
                            wp.element.createElement('h3', { style: { margin: '0 0 4px' } }, 'Content opportunity'),
                            wp.element.createElement('p', { style: { margin: 0 } }, topic.content_opportunity)
                        ),
                    citations.length
                        ? wp.element.createElement(
                              'div',
                              { style: { marginTop: '6px' } },
                              wp.element.createElement('h3', { style: { margin: '0 0 4px' } }, 'Citations'),
                              renderList(citations.map((cite) => cite.title || cite.url || 'Citation'))
                          )
                        : null,
                    wp.element.createElement('div', {
                        style: {
                            marginTop: '14px',
                            borderBottom: '0.5pt solid #e2e4e7',
                        },
                    })
                );
            });
        };

        return phaseOrder.map((key) => {
            const phase = phases[key];
            if (!phase) {
                return null;
            }
            const citationsCount = Array.isArray(phase.citations) ? phase.citations.length : 0;
            const phaseSummary = phase.summary || phase.payload?.article_summary || '';
            const hasSummary = !!(phaseSummary && String(phaseSummary).trim());
            const hasError = !!(phase.error_message && String(phase.error_message).trim());
            const isExpanded = !!expandedPhases[key];
            const statusText = hasError
                ? phase.error_message
                : phase.status && phase.status !== 'completed'
                ? 'In progress...'
                : 'No summary yet.';
            const detailBlocks = [];
            const referencedLinks = [];
            const seenUrls = new Set();

            const pushLink = (title, url) => {
                if (!url || seenUrls.has(url)) {
                    return;
                }
                seenUrls.add(url);
                referencedLinks.push({ title, url });
            };

            if (phase.payload?.trends) {
                phase.payload.trends.forEach((trend) => {
                    (trend.citations || []).forEach((citation) => {
                        pushLink(citation.title, citation.url);
                    });
                });
            }
            if (phase.payload?.validated_topics) {
                phase.payload.validated_topics.forEach((topic) => {
                    (topic.citations || []).forEach((citation) => {
                        pushLink(citation.title, citation.url);
                    });
                });
            }
            if (phase.payload?.sources) {
                phase.payload.sources.forEach((source) => {
                    pushLink(source.title, source.url);
                });
            }
            if (key === 'phase1' && sessionDetail?.meta?.phase1?.serp_snapshot) {
                Object.values(sessionDetail.meta.phase1.serp_snapshot).forEach((snapshot) => {
                    (snapshot.results || []).forEach((result) => {
                        pushLink(result.title, result.url);
                    });
                });
            }
            if (key === 'phase1' && sessionDetail?.meta?.phase1?.candidate_keywords?.length) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'candidate-keywords', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Candidate Keywords'),
                        renderList(sessionDetail.meta.phase1.candidate_keywords)
                    )
                );
            }
            if (key === 'phase1' && sessionDetail?.meta?.phase1?.trend_summary?.length) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'trend-summary-phase1', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Trend Summary'),
                        renderList(
                            sessionDetail.meta.phase1.trend_summary.map(
                                (item) =>
                                    `${item.trend || 'Trend'}: ${
                                        item.repeated_in_research || item.insight || ''
                                    }`
                            )
                        )
                    )
                );
                detailBlocks.push(
                    wp.element.createElement(
                        'p',
                        { key: 'trend-summary-legend', style: { marginTop: '6px', fontSize: '12px', color: '#50575e' } },
                        'Repeated in research: yes = cited by multiple sources; mixed = conflicting or uneven coverage.'
                    )
                );
            }
            if (key === 'phase2' && sessionDetail?.meta?.phase2?.keyword_metrics?.length) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'keyword-metrics-phase2', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Keyword Metrics'),
                        wp.element.createElement(
                            'table',
                            { className: 'widefat striped', style: { marginTop: '8px' } },
                            wp.element.createElement(
                                'thead',
                                null,
                                wp.element.createElement(
                                    'tr',
                                    null,
                                    wp.element.createElement('th', null, 'Keyword'),
                                    wp.element.createElement('th', null, 'Volume'),
                                    wp.element.createElement('th', null, 'CPC'),
                                    wp.element.createElement('th', null, 'Competition'),
                                    wp.element.createElement('th', null, 'Trend'),
                                    wp.element.createElement('th', null, 'Difficulty')
                                )
                            ),
                            wp.element.createElement(
                                'tbody',
                                null,
                                sessionDetail.meta.phase2.keyword_metrics.slice(0, 10).map((item, idx) =>
                                    wp.element.createElement(
                                        'tr',
                                        { key: `kw-${idx}` },
                                        wp.element.createElement('td', null, item.keyword || 'Keyword'),
                                        wp.element.createElement('td', null, item.search_volume ?? '—'),
                                        wp.element.createElement('td', null, item.cpc ?? '—'),
                                        wp.element.createElement('td', null, item.competition ?? '—'),
                                        wp.element.createElement('td', null, item.trend ?? '—'),
                                        wp.element.createElement('td', null, item.difficulty ?? '—')
                                    )
                                )
                            )
                        )
                    )
                );
            }
            if (key === 'phase2' && sessionDetail?.meta?.phase2?.ranked_keywords?.length) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'ranked-keywords-phase2', style: { marginTop: '10px' } },
                        wp.element.createElement('strong', null, 'Priority Ranking'),
                        renderList(
                            sessionDetail.meta.phase2.ranked_keywords.slice(0, 10).map((item) => {
                                const score = item.priority_score != null ? item.priority_score : '—';
                                const volume = item.search_volume ?? '—';
                                const cpc = item.cpc ?? '—';
                                const competition = item.competition || '—';
                                return `${item.keyword} — Priority Score: ${score}, Volume: ${volume}, CPC: ${cpc}, Competition: ${competition}`;
                            })
                        )
                    )
                );
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'serp-signals-phase2', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'SERP Signals'),
                        sessionDetail.meta.phase2.ranked_keywords.slice(0, 6).map((item, idx) =>
                            wp.element.createElement(
                                'div',
                                { key: `serp-${idx}`, style: { marginTop: '6px' } },
                                wp.element.createElement('strong', null, item.keyword),
                                renderList(
                                    (item.serp_sources || []).map((source) => source.title || source.domain || source.url)
                                )
                            )
                        )
                    )
                );
            }
            if (phase.payload?.trends) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'trends', style: { marginTop: '8px' } },
                        wp.element.createElement('h2', { style: { margin: '0 0 6px' } }, 'Trends and Highlights'),
                        renderTrendBlocks(phase.payload.trends)
                    )
                );
            }
            if (phase.payload?.prioritized_topics) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'prioritized-topics', style: { marginTop: '8px' } },
                        wp.element.createElement('h2', { style: { margin: '0 0 6px' } }, 'Prioritized Topics'),
                        renderPrioritizedTopics(phase.payload.prioritized_topics)
                    )
                );
            }
            if (key === 'phase3' && Array.isArray(phase.payload?.prioritized_topics)) {
                detailBlocks.push(
                    wp.element.createElement(
                        'p',
                        { key: 'phase3-topic-count', style: { marginTop: '6px', fontSize: '12px', color: '#50575e' } },
                        `Prioritized topics: ${phase.payload.prioritized_topics.length}`
                    )
                );
            }
            if (phase.payload?.trend_summary) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'trend-summary', style: { marginTop: '8px' } },
                        wp.element.createElement('h2', { style: { margin: '0 0 6px' } }, 'Trend Summary'),
                        renderList(
                            phase.payload.trend_summary.map(
                                (item) => `${item.trend || 'Trend'}: ${item.repeated_in_research || ''}`
                            )
                        ),
                        wp.element.createElement(
                            'p',
                            { style: { marginTop: '6px', fontSize: '12px', color: '#50575e' } },
                            'Key: yes = repeated across multiple sources; mixed = conflicting or uneven coverage; no = limited support.'
                        )
                    )
                );
            }
            if (phase.payload?.data_evidence) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'data-evidence', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Data & Evidence'),
                        renderList(phase.payload.data_evidence.map((item) => item.insight || item.claim || item.evidence))
                    )
                );
            }
            if (phase.payload?.risks_and_gaps) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'risks-gaps', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Risks & Gaps'),
                        renderList(phase.payload.risks_and_gaps)
                    )
                );
            }
            if (phase.payload?.content_applications) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'content-applications', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Content Applications'),
                        renderList(phase.payload.content_applications.map((item) => item.recommendation || item.format))
                    )
                );
            }
            if (phase.payload?.content_roadmap) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'content-roadmap', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Content Roadmap'),
                        renderList(phase.payload.content_roadmap.map((item) => item.deliverable))
                    )
                );
            }
            if (phase.payload?.validated_topics) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'validated-topics', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Validated Topics'),
                        renderList(
                            phase.payload.validated_topics.map((item) => {
                                const topic = item.topic || 'Topic';
                                const citations = Array.isArray(item.citations) ? item.citations.length : 0;
                                return `${topic} (citations: ${citations})`;
                            })
                        )
                    )
                );
            }
            if (phase.payload?.sources || phase.citations) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'sources', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Sources'),
                        renderList(
                            (phase.payload?.sources || phase.citations || []).map(
                                (source) => source.title || source.url || 'Source'
                            )
                        )
                    )
                );
            }
            if (phase.payload?.needs_validation) {
                detailBlocks.push(
                    wp.element.createElement(
                        'p',
                        { key: 'needs-validation', style: { marginTop: '8px', color: '#946200' } },
                        'Needs validation: evidence unavailable from live sources.'
                    )
                );
            }
            if (referencedLinks.length) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'referenced-links', style: { marginTop: '8px' } },
                        wp.element.createElement('h2', { style: { margin: '0 0 6px' } }, 'Referenced Links'),
                        renderLinkList(referencedLinks.slice(0, 20))
                    )
                );
            }
            const phaseTitleOverrides = {
                phase1: 'Research Phase 1',
                phase2: 'Research Phase 2',
                phase3: 'Research Phase 3',
                phase4: 'Research Phase 4',
            };
            return wp.element.createElement(
                Card,
                { key, style: { marginBottom: '12px' } },
                wp.element.createElement(
                    CardHeader,
                    null,
                    phaseTitleOverrides[key] || phase.title || key
                ),
                wp.element.createElement(
                    CardBody,
                    null,
                    wp.element.createElement('p', null, hasSummary ? phaseSummary : statusText),
                    hasError &&
                        wp.element.createElement(
                            'p',
                            { style: { marginTop: '8px', color: '#b32d2e' } },
                            'Phase failed.'
                        ),
                    phase.error &&
                        wp.element.createElement(
                            'p',
                            { style: { marginTop: '6px', color: '#b32d2e' } },
                            phase.error
                        ),
                    (referencedLinks.length || citationsCount)
                        ? wp.element.createElement(
                              'p',
                              { style: { marginTop: '8px', fontSize: '12px', color: '#50575e' } },
                              `Links checked: ${referencedLinks.length || citationsCount}`
                          )
                        : null,
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: () =>
                                setExpandedPhases((prev) => ({ ...prev, [key]: !prev[key] })),
                            style: { marginTop: '8px' },
                        },
                        isExpanded ? 'Hide details' : 'View details'
                    ),
                    isExpanded &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '12px' } },
                            ...detailBlocks
                        ),
                    phase.payload?.next_step_question &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '8px' } },
                            wp.element.createElement(
                                'p',
                                { style: { fontStyle: 'italic', marginBottom: '6px' } },
                                phase.payload.next_step_question
                            ),
                            key === 'phase1' &&
                                wp.element.createElement(
                                    Button,
                                    {
                                        isSecondary: true,
                                        onClick: handleRerunPhase2,
                                        disabled: phase2RerunLoading,
                                    },
                                    phase2RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Research Phase 2'
                                ),
                            key === 'phase3' &&
                                wp.element.createElement(
                                    Button,
                                    {
                                        isSecondary: true,
                                        onClick: handleRerunPhase4,
                                        disabled: phase4RerunLoading || !phase3Complete,
                                    },
                                    phase4RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Research Phase 4'
                                ),
                            key === 'phase4' &&
                                wp.element.createElement(
                                    Button,
                                    {
                                        isPrimary: true,
                                        onClick: openSynopsisModal,
                                        disabled: !phase4Complete,
                                    },
                                    'Generate Article Synopses'
                                )
                        ),
                    key === 'phase2' &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '8px' } },
                            wp.element.createElement(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: handleRerunPhase3,
                                    disabled: phase3RerunLoading,
                                },
                                phase3RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Research Phase 3'
                            )
                        )
                )
            );
        });
    };

    const renderArticlesTable = () => {
        const articles = sessionDetail?.meta?.articles || [];
        if (!articles.length) {
            return wp.element.createElement('p', null, 'No article synopses yet.');
        }

        const keywordMetrics = sessionDetail?.meta?.phase2?.keyword_metrics || [];
        const findMetric = (keywords) => {
            if (!keywords || !keywords.length) {
                return null;
            }
            return keywordMetrics.find((metric) => keywords.includes(metric.keyword));
        };

        return wp.element.createElement(
            'table',
            { className: 'widefat striped', style: { marginTop: '12px' } },
            wp.element.createElement(
                'thead',
                null,
                wp.element.createElement(
                    'tr',
                    null,
                    wp.element.createElement('th', null, 'Headline'),
                    wp.element.createElement('th', null, 'Brief'),
                    wp.element.createElement('th', null, 'Keywords'),
                    wp.element.createElement('th', null, 'Volume'),
                    wp.element.createElement('th', null, 'Citations'),
                    wp.element.createElement('th', null, 'Framework Status'),
                    wp.element.createElement('th', null, 'Author Status'),
                    wp.element.createElement('th', null, 'Actions')
                )
            ),
            wp.element.createElement(
                'tbody',
                null,
                articles.map((article, index) => {
                    const metric = findMetric(article.keywords || []);
                    const volume = metric?.search_volume ?? '—';
                    const citationsCount = article.citation_count ?? (article.citations?.length || 0);
                    const frameworkStatusRaw = article.framework?.status || 'pending';
                    const frameworkStatus =
                        frameworkStatusRaw === 'completed' ? 'complete' : frameworkStatusRaw;
                    const progressEntry = frameworkProgress[article.id];
                    const progressPercent = progressEntry?.percent || 5;
                    const authorStatusRaw = article.author?.status || 'pending';
                    const authorStatus =
                        authorStatusRaw === 'completed' ? 'complete' : authorStatusRaw;
                    const authorEntry = authorProgress[article.id];
                    const authorPercent = authorEntry?.percent || 5;
                    const isAuthorLoading = !!authorLoading[article.id];
                    const authorEditUrl = article.author?.edit_url;
                    const frameworkReady =
                        frameworkStatus === 'complete' && !!article.framework?.output;
                    const frameworkActionLabel = frameworkStatus === 'complete' ? 'Regenerate' : 'Generate';
                    return wp.element.createElement(
                        'tr',
                        { key: `${article.headline || article.title || article.id}-${index}` },
                        wp.element.createElement('td', null, article.headline || article.title || 'Untitled'),
                        wp.element.createElement(
                            'td',
                            null,
                            wp.element.createElement(
                                'div',
                                { style: { maxWidth: '420px' } },
                                article.summary || article.brief || article.summary_two_sentences || 'No summary.'
                            )
                        ),
                        wp.element.createElement(
                            'td',
                            null,
                            (article.keywords || article.tags || []).join(', ') || '—'
                        ),
                        wp.element.createElement('td', null, volume),
                        wp.element.createElement('td', null, citationsCount),
                        wp.element.createElement(
                            'td',
                            null,
                            frameworkStatus === 'running'
                                ? wp.element.createElement(
                                      'div',
                                      { style: { minWidth: '160px' } },
                                      wp.element.createElement('div', null, `Running (${progressPercent}%)`),
                                      wp.element.createElement(ProgressBar, { value: progressPercent })
                                  )
                                : frameworkStatus === 'queued'
                                  ? wp.element.createElement(
                                        'div',
                                        { style: { minWidth: '160px' } },
                                        wp.element.createElement('div', null, `Queued (${progressPercent}%)`),
                                        wp.element.createElement(ProgressBar, { value: progressPercent })
                                    )
                                : frameworkStatus === 'failed'
                                  ? wp.element.createElement(
                                        'div',
                                        null,
                                        wp.element.createElement('div', null, 'failed'),
                                        article.framework?.error_message &&
                                            wp.element.createElement(
                                                'div',
                                                { style: { fontSize: '12px', color: '#a00' } },
                                                article.framework.error_message
                                            )
                                    )
                                  : frameworkStatus
                        ),
                        wp.element.createElement(
                            'td',
                            null,
                            authorStatus === 'running'
                                ? wp.element.createElement(
                                      'div',
                                      { style: { minWidth: '160px' } },
                                      wp.element.createElement('div', null, `Running (${authorPercent}%)`),
                                      wp.element.createElement(ProgressBar, { value: authorPercent })
                                  )
                                : authorStatus === 'queued'
                                  ? wp.element.createElement(
                                        'div',
                                        { style: { minWidth: '160px' } },
                                        wp.element.createElement('div', null, 'Queued'),
                                        wp.element.createElement(Spinner, null)
                                    )
                                : authorStatus === 'failed'
                                  ? wp.element.createElement(
                                        'div',
                                        null,
                                        wp.element.createElement('div', null, 'failed'),
                                        article.author?.error_message &&
                                            wp.element.createElement(
                                                'div',
                                                { style: { fontSize: '12px', color: '#a00' } },
                                                article.author.error_message
                                            )
                                    )
                                  : authorStatus
                        ),
                        wp.element.createElement(
                            'td',
                            null,
                            wp.element.createElement(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: () => setPreviewArticle(article),
                                    style: { marginRight: '8px' },
                                },
                                'Preview'
                            ),
                            frameworkReady &&
                                article.framework?.output &&
                                wp.element.createElement(
                                    Button,
                                    {
                                        isSecondary: true,
                                        onClick: () => setFrameworkPreview(article),
                                        style: { marginRight: '8px' },
                                    },
                                    'View Framework'
                                ),
                            wp.element.createElement(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: () => handleExportFramework(article),
                                    style: { marginRight: '8px' },
                                    disabled: !frameworkReady,
                                },
                                'Export Framework'
                            ),
                            wp.element.createElement(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: () => handleRunAuthorAgent(article),
                                    style: { marginRight: '8px' },
                                    disabled: !frameworkReady || isAuthorLoading || authorStatus === 'running' || authorStatus === 'queued',
                                },
                                isAuthorLoading ? wp.element.createElement(Spinner, null) : 'Run Author'
                            ),
                            wp.element.createElement(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: () => setAuthorPreview(article),
                                    style: { marginRight: '8px' },
                                    disabled: !(authorStatus === 'complete' && article.author?.output),
                                },
                                'View Draft'
                            ),
                            wp.element.createElement(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: () => handleExportAuthorDraft(article),
                                    style: { marginRight: '8px' },
                                    disabled: !(authorStatus === 'complete' && article.author?.output),
                                },
                                'Export Draft'
                            ),
                            wp.element.createElement(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: () => window.open(authorEditUrl, '_blank'),
                                    style: { marginRight: '8px' },
                                    disabled: !(authorStatus === 'complete' && authorEditUrl),
                                },
                                'Open in Editor'
                            ),
                            wp.element.createElement(
                                Button,
                                {
                                    isPrimary: true,
                                    onClick: () => handleRegenerateFramework(article, index),
                                    disabled: !!frameworkLoading[index],
                                },
                                frameworkLoading[index] ? wp.element.createElement(Spinner, null) : frameworkActionLabel
                            )
                        )
                    );
                })
            )
        );
    };

    const renderFrameworks = () => {
        const frameworks = sessionDetail?.meta?.frameworks || [];
        if (!frameworks.length) {
            return null;
        }

        return wp.element.createElement(
            'div',
            { style: { marginTop: '16px' } },
            wp.element.createElement('h3', null, 'Generated Frameworks'),
            frameworks.map((framework, index) =>
                wp.element.createElement(
                    Card,
                    { key: `${framework.job_id}-${index}`, style: { marginTop: '8px' } },
                    wp.element.createElement(CardHeader, null, framework.article_title || 'Framework'),
                    wp.element.createElement(
                        CardBody,
                        null,
                        wp.element.createElement(
                            'pre',
                            { style: { whiteSpace: 'pre-wrap' } },
                            framework.output || 'No output captured.'
                        )
                    )
                )
            )
        );
    };

    const phase1Complete = sessionDetail?.meta?.phases?.phase1?.status === 'completed';
    const phase2Complete = sessionDetail?.meta?.phases?.phase2?.status === 'completed';
    const phase3Complete = sessionDetail?.meta?.phases?.phase3?.status === 'completed';
    const phase4Complete = sessionDetail?.meta?.phases?.phase4?.status === 'completed';
    const synopsisPlanTotal = Object.values(synopsisPlan).reduce(
        (sum, value) => sum + (parseInt(value || 0, 10) || 0),
        0
    );
    const focusLabel = getFocusLabel(focusLevel);
    const synopsisEstimate = estimateSynopses(sessionDetail?.meta, focusLevel);

    // Render the detail page when viewing a session
    if (viewingSessionId && sessionDetail) {
        return wp.element.createElement(
            'div',
            { className: 'editorial-planner-dashboard' },
            wp.element.createElement(
                'div',
                { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' } },
                wp.element.createElement(
                    'div',
                    null,
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: navigateBack,
                            style: { marginRight: '12px' },
                        },
                        '← Back to Sessions'
                    ),
                    wp.element.createElement('h1', { style: { display: 'inline-block', margin: '0' } }, sessionDetail?.title || 'Session Detail')
                )
            ),
            detailLoading
                ? wp.element.createElement(Spinner, null)
                : wp.element.createElement(
                      'div',
                      null,
                      detailError && wp.element.createElement(Notice, { status: 'error', isDismissible: false }, detailError),
                      showFocusControls &&
                          wp.element.createElement(
                              'div',
                              {
                                  style: {
                                      marginBottom: '12px',
                                      padding: '12px',
                                      border: '1px solid #dcdcde',
                                      borderRadius: '6px',
                                      background: '#f6f7f7',
                                  },
                              },
                              wp.element.createElement('strong', null, 'Focus vs Breadth'),
                              wp.element.createElement(
                                  'p',
                                  { style: { margin: '6px 0 8px', color: '#50575e' } },
                                  `Current setting: ${focusLabel} (${focusLevel})`
                              ),
                              wp.element.createElement(RangeControl, {
                                  value: focusLevel,
                                  min: 0,
                                  max: 100,
                                  step: 5,
                                  onChange: (value) => {
                                      setFocusDirty(true);
                                      setFocusLevel(value);
                                  },
                                  help: 'Lower = broader coverage; higher = tighter focus.',
                              }),
                              wp.element.createElement(
                                  'p',
                                  { style: { margin: '6px 0 0', fontSize: '12px', color: '#50575e' } },
                                  synopsisEstimate.estimate > 0
                                      ? `Estimated synopses: ${synopsisEstimate.min}–${synopsisEstimate.max} (target 20).`
                                      : 'Estimated synopses available after Phase 2.'
                              )
                          ),
                      wp.element.createElement(
                          'div',
                          { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                          wp.element.createElement(
                              'div',
                              null,
                              wp.element.createElement('h2', null, 'Phase Summaries'),
                              thinkingPhraseIndex > 0 &&
                                  wp.element.createElement(
                                      'div',
                                      {
                                          style: {
                                              display: 'flex',
                                              alignItems: 'center',
                                              gap: '8px',
                                              marginTop: '6px',
                                              fontSize: '14px',
                                              color: '#0073aa',
                                              fontStyle: 'italic',
                                          },
                                      },
                                      wp.element.createElement('span', null, '✨'),
                                      wp.element.createElement('span', null, THINKING_PHRASES[thinkingPhraseIndex] + '...'),
                                      wp.element.createElement(Spinner, null)
                                  )
                          ),
                          wp.element.createElement(
                              'div',
                              null,
                              wp.element.createElement(
                                  Button,
                                  {
                                      isSecondary: true,
                                      onClick: handleRerunPhase1,
                                      style: { marginRight: '8px' },
                                      disabled: phase1RerunLoading,
                                  },
                                  phase1RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Discovery'
                              ),
                              wp.element.createElement(
                                  Button,
                                  {
                                      isSecondary: true,
                                      onClick: handleRerunPhase2,
                                      style: { marginRight: '8px' },
                                      disabled: phase2RerunLoading,
                                  },
                                  phase2RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Research Phase 2'
                              ),
                              wp.element.createElement(
                                  Button,
                                  {
                                      isSecondary: true,
                                      onClick: handleRerunPhase3,
                                      style: { marginRight: '8px' },
                                      disabled: phase3RerunLoading || !phase2Complete,
                                  },
                                  phase3RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Research Phase 3'
                              ),
                              wp.element.createElement(
                                  Button,
                                  {
                                      isSecondary: true,
                                      onClick: handleRerunPhase4,
                                      style: { marginRight: '8px' },
                                      disabled: phase4RerunLoading || !phase3Complete,
                                  },
                                  phase4RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Research Phase 4'
                              ),
                              wp.element.createElement(
                                  Button,
                                  {
                                      isSecondary: true,
                                      onClick: handleExportValidation,
                                      style: { marginRight: '8px' },
                                      disabled: !phase4Complete,
                                  },
                                  'Export Validation'
                              ),
                              wp.element.createElement(
                                  Button,
                                  {
                                      isPrimary: true,
                                      onClick: openSynopsisModal,
                                      style: { marginRight: '8px' },
                                      disabled: !phase4Complete,
                                  },
                                  'Generate Article Synopses'
                              ),
                              wp.element.createElement(
                                  Button,
                                  {
                                      isSecondary: true,
                                      onClick: handleExportSynopses,
                                      style: { marginRight: '8px' },
                                      disabled: !(sessionDetail?.meta?.articles || []).length,
                                  },
                                  'Export Synopses'
                              ),
                              wp.element.createElement(
                                  ToggleControl,
                                  {
                                      label: 'Auto refresh',
                                      checked: autoRefreshEnabled,
                                      onChange: () => setAutoRefreshEnabled((prev) => !prev),
                                      style: { marginRight: '12px' },
                                  }
                              ),
                              wp.element.createElement(
                                  Button,
                                  { isSecondary: true, onClick: refreshSessionDetail },
                                  'Refresh'
                              )
                          )
                      ),
                      renderPhaseSummaries(),
                      (sessionDetail?.meta?.articles || []).length > 0
                          ? wp.element.createElement(
                                wp.element.Fragment,
                                null,
                                wp.element.createElement('h2', { style: { marginTop: '16px' } }, 'Article Synopses'),
                                renderArticlesTable()
                            )
                          : null
                  ),
            synopsisModalOpen &&
                wp.element.createElement(
                    Modal,
                    {
                        title: 'Generate Article Synopses',
                        onRequestClose: () => (!synopsisGenerateLoading ? setSynopsisModalOpen(false) : null),
                        style: { minWidth: '60vw' },
                    },
                    synopsisPlanLoading
                        ? wp.element.createElement(Spinner, null)
                        : synopsisGenerateLoading
                          ? wp.element.createElement(
                                'div',
                                { style: { textAlign: 'center', padding: '24px' } },
                                wp.element.createElement(Spinner, null),
                                wp.element.createElement(
                                    'p',
                                    { style: { marginTop: '16px', fontSize: '14px', color: '#666' } },
                                    'Generating article synopses from research data...'
                                ),
                                wp.element.createElement(
                                    'p',
                                    { style: { fontSize: '12px', color: '#aaa', marginTop: '8px' } },
                                    'This typically takes 1-5 minutes depending on topic complexity.'
                                )
                            )
                          : wp.element.createElement(
                              'div',
                              null,
                              synopsisPlanError &&
                                  wp.element.createElement(Notice, { status: 'error', isDismissible: false }, synopsisPlanError),
                              wp.element.createElement(
                                  'p',
                                  { style: { marginTop: '8px' } },
                                  `Target synopses: ${synopsisTotal}. Current total: ${synopsisPlanTotal}.`
                              ),
                              synopsisPlanTotal !== synopsisTotal &&
                                  wp.element.createElement(
                                      Notice,
                                      { status: 'warning', isDismissible: false },
                                      `Total synopses do not match target (${synopsisTotal}). You can still generate, or adjust counts.`
                                  ),
                              wp.element.createElement(
                                  'div',
                                  { style: { marginTop: '12px', maxWidth: '240px' } },
                                  wp.element.createElement(TextControl, {
                                      label: 'Target total',
                                      type: 'number',
                                      min: 1,
                                      value: synopsisTotal,
                                      onChange: (value) => setSynopsisTotal(Math.max(1, parseInt(value || 0, 10))),
                                  })
                              ),
                              Object.keys(synopsisPlan).length
                                  ? wp.element.createElement(
                                        'div',
                                        { style: { marginTop: '12px' } },
                                        Object.keys(synopsisPlan).map((topic) =>
                                            wp.element.createElement(TextControl, {
                                                key: topic,
                                                type: 'number',
                                                label: topic,
                                                value: synopsisPlan[topic],
                                                onChange: (value) => updateSynopsisCount(topic, value),
                                                min: 0,
                                            })
                                        )
                                    )
                                  : wp.element.createElement('p', null, 'No validated topics available.'),
                              wp.element.createElement(
                                  'div',
                                  { style: { marginTop: '16px', display: 'flex', justifyContent: 'flex-end' } },
                                  wp.element.createElement(
                                      Button,
                                      { isSecondary: true, onClick: () => setSynopsisModalOpen(false) },
                                      'Cancel'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isPrimary: true,
                                          onClick: handleGenerateSynopses,
                                          disabled: synopsisGenerateLoading || synopsisPlanTotal === 0 || !sessionDetail?.id,
                                          type: 'button',
                                          style: { marginLeft: '8px' },
                                      },
                                      synopsisGenerateLoading ? wp.element.createElement(Spinner, null) : 'Generate Synopses'
                                  )
                              )
                          )
                ),
            previewArticle &&
                wp.element.createElement(
                    Modal,
                    {
                        title: previewArticle.headline || previewArticle.title || 'Article Preview',
                        onRequestClose: () => setPreviewArticle(null),
                        isDismissible: true,
                        shouldCloseOnClickOutside: false,
                    },
                    wp.element.createElement(
                        'p',
                        null,
                        previewArticle.summary || previewArticle.brief || previewArticle.summary_two_sentences || 'No summary.'
                    ),
                    previewArticle.key_points &&
                        previewArticle.key_points.length &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '12px' } },
                            wp.element.createElement('strong', null, 'Key Points'),
                            wp.element.createElement(
                                'ul',
                                null,
                                previewArticle.key_points.map((point, idx) =>
                                    wp.element.createElement('li', { key: idx }, point)
                                )
                            )
                        ),
                    (previewArticle.keywords || previewArticle.tags) &&
                        (previewArticle.keywords || previewArticle.tags).length &&
                        wp.element.createElement(
                            'p',
                            { style: { marginTop: '8px', fontSize: '12px', color: '#50575e' } },
                            `Keywords: ${(previewArticle.keywords || previewArticle.tags).join(', ')}`
                        ),
                    previewArticle.recommended_word_count &&
                        wp.element.createElement(
                            'p',
                            { style: { marginTop: '8px', fontSize: '12px', color: '#50575e' } },
                            `Recommended word count: ${previewArticle.recommended_word_count}`
                        ),
                    previewArticle.topic_coverage_level &&
                        wp.element.createElement(
                            'p',
                            { style: { marginTop: '4px', fontSize: '12px', color: '#50575e' } },
                            `Topic coverage level: ${previewArticle.topic_coverage_level}`
                        ),
                    previewArticle.citations &&
                        previewArticle.citations.length > 0 &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '12px' } },
                            wp.element.createElement('strong', null, 'Supporting Citations'),
                            wp.element.createElement(
                                'ul',
                                null,
                                previewArticle.citations.map((citation, idx) =>
                                    wp.element.createElement(
                                        'li',
                                        { key: idx },
                                        citation.title || citation.url || 'Citation'
                                    )
                                )
                            )
                        )
                ),
            frameworkPreview &&
                wp.element.createElement(
                    Modal,
                    {
                        title: 'Framework Preview',
                        onRequestClose: () => setFrameworkPreview(null),
                        isDismissible: true,
                        shouldCloseOnClickOutside: false,
                    },
                    wp.element.createElement(
                        'div',
                        { style: { marginBottom: '12px', display: 'flex', gap: '8px' } },
                        wp.element.createElement(
                            Button,
                            {
                                isSecondary: true,
                                onClick: () => handleExportFramework(frameworkPreview),
                                disabled: !(frameworkPreview?.framework?.output),
                            },
                            'Export Framework'
                        ),
                        wp.element.createElement(
                            Button,
                            {
                                isSecondary: true,
                                onClick: () => handleRunAuthorAgent(frameworkPreview),
                                disabled: !(frameworkPreview?.framework?.output),
                            },
                            'Run Author Agent'
                        )
                    ),
                    wp.element.createElement('p', null, frameworkPreview.title || 'Framework'),
                    frameworkPreview.framework?.output?.title &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '12px' } },
                            wp.element.createElement('h3', null, frameworkPreview.framework.output.title),
                            frameworkPreview.framework.output.overview &&
                                wp.element.createElement(
                                    'div',
                                    { style: { marginTop: '8px' } },
                                    wp.element.createElement('strong', null, 'Overview'),
                                    wp.element.createElement('p', null, frameworkPreview.framework.output.overview)
                                ),
                            frameworkPreview.framework.output.context &&
                                wp.element.createElement(
                                    'div',
                                    { style: { marginTop: '8px' } },
                                    wp.element.createElement('strong', null, 'Context'),
                                    wp.element.createElement('p', null, frameworkPreview.framework.output.context)
                                ),
                            frameworkPreview.framework.output.application &&
                                wp.element.createElement(
                                    'div',
                                    { style: { marginTop: '8px' } },
                                    wp.element.createElement('strong', null, 'Application'),
                                    wp.element.createElement(
                                        'p',
                                        null,
                                        frameworkPreview.framework.output.application.intended_reader
                                            ? `Intended Reader: ${frameworkPreview.framework.output.application.intended_reader}`
                                            : null
                                    ),
                                    wp.element.createElement(
                                        'p',
                                        null,
                                        frameworkPreview.framework.output.application.use_case
                                            ? `Use Case: ${frameworkPreview.framework.output.application.use_case}`
                                            : null
                                    )
                                ),
                            frameworkPreview.framework.output.observations &&
                                frameworkPreview.framework.output.observations.length > 0 &&
                                wp.element.createElement(
                                    'div',
                                    { style: { marginTop: '8px' } },
                                    wp.element.createElement('strong', null, 'Observations'),
                                    wp.element.createElement(
                                        'ul',
                                        null,
                                        frameworkPreview.framework.output.observations.map((item, idx) =>
                                            wp.element.createElement(
                                                'li',
                                                { key: idx },
                                                wp.element.createElement('strong', null, item.headline || 'Observation'),
                                                wp.element.createElement('p', null, item.detail || '')
                                            )
                                        )
                                    )
                                ),
                            frameworkPreview.framework.output.key_themes &&
                                frameworkPreview.framework.output.key_themes.length > 0 &&
                                wp.element.createElement(
                                    'div',
                                    { style: { marginTop: '8px' } },
                                    wp.element.createElement('strong', null, 'Key Themes'),
                                    wp.element.createElement(
                                        'ul',
                                        null,
                                        frameworkPreview.framework.output.key_themes.map((theme, idx) =>
                                            wp.element.createElement('li', { key: idx }, theme)
                                        )
                                    )
                                )
                        ),
                    !frameworkPreview.framework?.output?.title &&
                        frameworkPreview.framework?.output?.h2_sections &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '12px' } },
                            wp.element.createElement('strong', null, 'Framework'),
                            wp.element.createElement(
                                'ul',
                                null,
                                frameworkPreview.framework.output.h2_sections.map((section, idx) =>
                                    wp.element.createElement(
                                        'li',
                                        { key: idx },
                                        section.title || 'Section',
                                        section.h3_sections && section.h3_sections.length
                                            ? wp.element.createElement(
                                                  'ul',
                                                  null,
                                                  section.h3_sections.map((h3, h3Idx) =>
                                                      wp.element.createElement('li', { key: h3Idx }, h3)
                                                  )
                                              )
                                            : null
                                    )
                                )
                            )
                        ),
                    frameworkPreview.citations &&
                        frameworkPreview.citations.length > 0 &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '12px' } },
                            wp.element.createElement('strong', null, 'Citations'),
                            wp.element.createElement(
                                'ul',
                                null,
                                frameworkPreview.citations.map((citation, idx) =>
                                    wp.element.createElement(
                                        'li',
                                        { key: idx },
                                        citation.apa || citation.title || citation.url || 'Citation',
                                        citation.relevance
                                            ? wp.element.createElement('p', null, citation.relevance)
                                            : null
                                    )
                                )
                            )
                        )
                ),
            authorPreview &&
                wp.element.createElement(
                    Modal,
                    {
                        title: 'Author Draft',
                        onRequestClose: () => setAuthorPreview(null),
                        isDismissible: true,
                        shouldCloseOnClickOutside: false,
                    },
                    wp.element.createElement(
                        'div',
                        { style: { marginBottom: '12px', display: 'flex', gap: '8px' } },
                        wp.element.createElement(
                            Button,
                            {
                                isSecondary: true,
                                onClick: () => handleExportAuthorDraft(authorPreview),
                                disabled: !(authorPreview?.author?.output),
                            },
                            'Export Draft'
                        ),
                        wp.element.createElement(
                            Button,
                            {
                                isSecondary: true,
                                onClick: () => authorPreview?.author?.edit_url && window.open(authorPreview.author.edit_url, '_blank'),
                                disabled: !(authorPreview?.author?.edit_url),
                            },
                            'Open in Editor'
                        )
                    ),
                    wp.element.createElement('p', null, authorPreview.title || 'Draft'),
                    wp.element.createElement(
                        'div',
                        { style: { whiteSpace: 'pre-wrap' } },
                        authorPreview.author?.output?.draft ||
                            authorPreview.author?.output?.content ||
                            'No draft available.'
                    )
                )
        );
    }

    // Render the sessions list page (default view)
    return wp.element.createElement(
        'div',
        { className: 'editorial-planner-dashboard' },
        wp.element.createElement(
            'div',
            { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
            wp.element.createElement('h1', null, 'Article Planner'),
            wp.element.createElement(
                Button,
                {
                    isPrimary: true,
                    onClick: () => setStartModalOpen(true),
                },
                'Start New Session'
            )
        ),
        sessionsError && wp.element.createElement(Notice, { status: 'error', isDismissible: false }, sessionsError),
        loadingSessions
            ? wp.element.createElement(Spinner, null)
            : wp.element.createElement(
                  Card,
                  { style: { marginTop: '16px' } },
                  wp.element.createElement(CardHeader, null, 'Recent Sessions'),
                  wp.element.createElement(
                      CardBody,
                      null,
                      sessions.length
                          ? wp.element.createElement(
                                'table',
                                { className: 'widefat striped' },
                                wp.element.createElement(
                                    'thead',
                                    null,
                                    wp.element.createElement(
                                        'tr',
                                        null,
                                        wp.element.createElement('th', null, 'Title'),
                                        wp.element.createElement('th', null, 'Topic'),
                                        wp.element.createElement('th', null, 'Created'),
                                        wp.element.createElement('th', null, 'Actions')
                                    )
                                ),
                                wp.element.createElement(
                                    'tbody',
                                    null,
                                    sessions.map((session) =>
                                        wp.element.createElement(
                                            'tr',
                                            { key: session.id },
                                            wp.element.createElement('td', null, session.title || session.meta?.topic || session.id),
                                            wp.element.createElement('td', null, session.meta?.topic || '—'),
                                            wp.element.createElement('td', null, session.created_at || '—'),
                                            wp.element.createElement(
                                                'td',
                                                null,
                                                wp.element.createElement(
                                                    Button,
                                                    {
                                                        isSecondary: true,
                                                        onClick: () => navigateToSession(session.id),
                                                    },
                                                    'View'
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                          : wp.element.createElement('p', null, 'No planning sessions yet — start your first above now!')
                  )
              ),
        startModalOpen &&
            wp.element.createElement(
                Modal,
                {
                    title: 'Start a New Planning Session',
                    onRequestClose: () => setStartModalOpen(false),
                },
                wp.element.createElement(SelectControl, {
                    label: 'Top-line Topic',
                    value: selectedTopic,
                    options: TOPIC_OPTIONS,
                    onChange: setSelectedTopic,
                }),
                wp.element.createElement(FormTokenField, {
                    label: 'Includes',
                    value: includes,
                    onChange: setIncludes,
                    placeholder: 'Add include terms',
                }),
                wp.element.createElement(FormTokenField, {
                    label: 'Excludes',
                    value: excludes,
                    onChange: setExcludes,
                    placeholder: 'Add exclude terms',
                }),
                wp.element.createElement(
                    'div',
                    { style: { marginTop: '16px', display: 'flex', justifyContent: 'flex-end' } },
                    wp.element.createElement(
                        Button,
                        { isSecondary: true, onClick: () => setStartModalOpen(false) },
                        'Cancel'
                    ),
                    wp.element.createElement(
                        Button,
                        {
                            isPrimary: true,
                            onClick: startNewSession,
                            disabled: starting,
                            style: { marginLeft: '8px' },
                        },
                        starting ? wp.element.createElement(Spinner, null) : 'Start New Session'
                    )
                )
            ),
        detailModalOpen &&
            wp.element.createElement(
                Modal,
                {
                    title: sessionDetail?.title || 'Session Detail',
                    onRequestClose: () => setDetailModalOpen(false),
                    style: { minWidth: '70vw' },
                },
                detailLoading
                    ? wp.element.createElement(Spinner, null)
                    : wp.element.createElement(
                          'div',
                          null,
                          detailError && wp.element.createElement(Notice, { status: 'error', isDismissible: false }, detailError),
                          showFocusControls &&
                              wp.element.createElement(
                                  'div',
                                  {
                                      style: {
                                          marginBottom: '12px',
                                          padding: '12px',
                                          border: '1px solid #dcdcde',
                                          borderRadius: '6px',
                                          background: '#f6f7f7',
                                      },
                                  },
                                  wp.element.createElement('strong', null, 'Focus vs Breadth'),
                                  wp.element.createElement(
                                      'p',
                                      { style: { margin: '6px 0 8px', color: '#50575e' } },
                                      `Current setting: ${focusLabel} (${focusLevel})`
                                  ),
                                  wp.element.createElement(RangeControl, {
                                      value: focusLevel,
                                      min: 0,
                                      max: 100,
                                      step: 5,
                                      onChange: (value) => {
                                          setFocusDirty(true);
                                          setFocusLevel(value);
                                      },
                                      help: 'Lower = broader coverage; higher = tighter focus.',
                                  }),
                                  wp.element.createElement(
                                      'p',
                                      { style: { margin: '6px 0 0', fontSize: '12px', color: '#50575e' } },
                                      synopsisEstimate.estimate > 0
                                          ? `Estimated synopses: ${synopsisEstimate.min}–${synopsisEstimate.max} (target 20).`
                                          : 'Estimated synopses available after Phase 2.'
                                  )
                              ),
                          wp.element.createElement(
                              'div',
                              { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                              wp.element.createElement(
                                  'div',
                                  null,
                                  wp.element.createElement('h2', null, 'Phase Summaries'),
                                  thinkingPhraseIndex > 0 &&
                                      wp.element.createElement(
                                          'div',
                                          {
                                              style: {
                                                  display: 'flex',
                                                  alignItems: 'center',
                                                  gap: '8px',
                                                  marginTop: '6px',
                                                  fontSize: '14px',
                                                  color: '#0073aa',
                                                  fontStyle: 'italic',
                                              },
                                          },
                                          wp.element.createElement('span', null, '✨'),
                                          wp.element.createElement('span', null, THINKING_PHRASES[thinkingPhraseIndex] + '...'),
                                          wp.element.createElement(Spinner, null)
                                      )
                              ),
                              wp.element.createElement(
                                  'div',
                                  null,
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: handleRerunPhase1,
                                          style: { marginRight: '8px' },
                                          disabled: phase1RerunLoading,
                                      },
                                      phase1RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Discovery'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: handleRerunPhase2,
                                          style: { marginRight: '8px' },
                                          disabled: phase2RerunLoading,
                                      },
                                      phase2RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Research Phase 2'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: handleRerunPhase3,
                                          style: { marginRight: '8px' },
                                          disabled: phase3RerunLoading || !phase2Complete,
                                      },
                                      phase3RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Research Phase 3'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: handleRerunPhase4,
                                          style: { marginRight: '8px' },
                                          disabled: phase4RerunLoading || !phase3Complete,
                                      },
                                      phase4RerunLoading ? wp.element.createElement(Spinner, null) : 'Run Research Phase 4'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: handleExportValidation,
                                          style: { marginRight: '8px' },
                                          disabled: !phase4Complete,
                                      },
                                      'Export Validation'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isPrimary: true,
                                          onClick: openSynopsisModal,
                                          style: { marginRight: '8px' },
                                          disabled: !phase4Complete,
                                      },
                                      'Generate Article Synopses'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: handleExportSynopses,
                                          style: { marginRight: '8px' },
                                          disabled: !(sessionDetail?.meta?.articles || []).length,
                                      },
                                      'Export Synopses'
                                  ),
                                  wp.element.createElement(
                                      ToggleControl,
                                      {
                                          label: 'Auto refresh',
                                          checked: autoRefreshEnabled,
                                          onChange: () => setAutoRefreshEnabled((prev) => !prev),
                                          style: { marginRight: '12px' },
                                      }
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      { isSecondary: true, onClick: refreshSessionDetail },
                                      'Refresh'
                                  )
                              )
                          ),
                          renderPhaseSummaries(),
                          (sessionDetail?.meta?.articles || []).length > 0
                              ? wp.element.createElement(
                                    wp.element.Fragment,
                                    null,
                                    wp.element.createElement('h2', { style: { marginTop: '16px' } }, 'Article Synopses'),
                                    renderArticlesTable()
                                )
                              : null
                      )
            ),
        synopsisModalOpen &&
            wp.element.createElement(
                Modal,
                {
                    title: 'Generate Article Synopses',
                    onRequestClose: () => (!synopsisGenerateLoading ? setSynopsisModalOpen(false) : null),
                    style: { minWidth: '60vw' },
                },
                synopsisPlanLoading
                    ? wp.element.createElement(Spinner, null)
                    : synopsisGenerateLoading
                      ? wp.element.createElement(
                            'div',
                            { style: { textAlign: 'center', padding: '24px' } },
                            wp.element.createElement(Spinner, null),
                            wp.element.createElement(
                                'p',
                                { style: { marginTop: '16px', fontSize: '14px', color: '#666' } },
                                'Generating article synopses from research data...'
                            ),
                            wp.element.createElement(
                                'p',
                                { style: { fontSize: '12px', color: '#aaa', marginTop: '8px' } },
                                'This typically takes 1-5 minutes depending on topic complexity.'
                            )
                        )
                      : wp.element.createElement(
                          'div',
                          null,
                          synopsisPlanError &&
                              wp.element.createElement(Notice, { status: 'error', isDismissible: false }, synopsisPlanError),
                          wp.element.createElement(
                              'p',
                              { style: { marginTop: '8px' } },
                              `Target synopses: ${synopsisTotal}. Current total: ${synopsisPlanTotal}.`
                          ),
                          synopsisPlanTotal !== synopsisTotal &&
                              wp.element.createElement(
                                  Notice,
                                  { status: 'warning', isDismissible: false },
                                  `Total synopses do not match target (${synopsisTotal}). You can still generate, or adjust counts.`
                              ),
                          wp.element.createElement(
                              'div',
                              { style: { marginTop: '12px', maxWidth: '240px' } },
                              wp.element.createElement(TextControl, {
                                  label: 'Target total',
                                  type: 'number',
                                  min: 1,
                                  value: synopsisTotal,
                                  onChange: (value) => setSynopsisTotal(Math.max(1, parseInt(value || 0, 10))),
                              })
                          ),
                          Object.keys(synopsisPlan).length
                              ? wp.element.createElement(
                                    'div',
                                    { style: { marginTop: '12px' } },
                                    Object.keys(synopsisPlan).map((topic) =>
                                        wp.element.createElement(TextControl, {
                                            key: topic,
                                            type: 'number',
                                            label: topic,
                                            value: synopsisPlan[topic],
                                            onChange: (value) => updateSynopsisCount(topic, value),
                                            min: 0,
                                        })
                                    )
                                )
                              : wp.element.createElement('p', null, 'No validated topics available.'),
                          wp.element.createElement(
                              'div',
                              { style: { marginTop: '16px', display: 'flex', justifyContent: 'flex-end' } },
                              wp.element.createElement(
                                  Button,
                                  { isSecondary: true, onClick: () => setSynopsisModalOpen(false) },
                                  'Cancel'
                              ),
                              wp.element.createElement(
                                  Button,
                                  {
                                      isPrimary: true,
                                      onClick: handleGenerateSynopses,
                                      disabled: synopsisGenerateLoading || synopsisPlanTotal === 0 || !sessionDetail?.id,
                                      type: 'button',
                                      style: { marginLeft: '8px' },
                                  },
                                  synopsisGenerateLoading ? wp.element.createElement(Spinner, null) : 'Generate Synopses'
                              )
                          )
                      )
            ),
        previewArticle &&
            wp.element.createElement(
                Modal,
                {
                    title: previewArticle.headline || previewArticle.title || 'Article Preview',
                    onRequestClose: () => setPreviewArticle(null),
                    isDismissible: true,
                    shouldCloseOnClickOutside: false,
                },
                wp.element.createElement(
                    'p',
                    null,
                    previewArticle.summary || previewArticle.brief || previewArticle.summary_two_sentences || 'No summary.'
                ),
                previewArticle.key_points &&
                    previewArticle.key_points.length &&
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '12px' } },
                        wp.element.createElement('strong', null, 'Key Points'),
                        wp.element.createElement(
                            'ul',
                            null,
                            previewArticle.key_points.map((point, idx) =>
                                wp.element.createElement('li', { key: idx }, point)
                            )
                        )
                    ),
                (previewArticle.keywords || previewArticle.tags) &&
                    (previewArticle.keywords || previewArticle.tags).length &&
                    wp.element.createElement(
                        'p',
                        { style: { marginTop: '8px', fontSize: '12px', color: '#50575e' } },
                        `Keywords: ${(previewArticle.keywords || previewArticle.tags).join(', ')}`
                    ),
                previewArticle.recommended_word_count &&
                    wp.element.createElement(
                        'p',
                        { style: { marginTop: '8px', fontSize: '12px', color: '#50575e' } },
                        `Recommended word count: ${previewArticle.recommended_word_count}`
                    ),
                previewArticle.topic_coverage_level &&
                    wp.element.createElement(
                        'p',
                        { style: { marginTop: '4px', fontSize: '12px', color: '#50575e' } },
                        `Topic coverage level: ${previewArticle.topic_coverage_level}`
                    ),
                previewArticle.citations &&
                    previewArticle.citations.length > 0 &&
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '12px' } },
                        wp.element.createElement('strong', null, 'Supporting Citations'),
                        wp.element.createElement(
                            'ul',
                            null,
                            previewArticle.citations.map((citation, idx) =>
                                wp.element.createElement(
                                    'li',
                                    { key: idx },
                                    citation.title || citation.url || 'Citation'
                                )
                            )
                        )
                    )
            ),
        frameworkPreview &&
            wp.element.createElement(
                Modal,
                {
                    title: 'Framework Preview',
                    onRequestClose: () => setFrameworkPreview(null),
                    isDismissible: true,
                    shouldCloseOnClickOutside: false,
                },
                wp.element.createElement(
                    'div',
                    { style: { marginBottom: '12px', display: 'flex', gap: '8px' } },
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: () => handleExportFramework(frameworkPreview),
                            disabled: !(frameworkPreview?.framework?.output),
                        },
                        'Export Framework'
                    ),
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: () => handleRunAuthorAgent(frameworkPreview),
                            disabled: !(frameworkPreview?.framework?.output),
                        },
                        'Run Author Agent'
                    )
                ),
                wp.element.createElement('p', null, frameworkPreview.title || 'Framework'),
                frameworkPreview.framework?.output?.title &&
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '12px' } },
                        wp.element.createElement('h3', null, frameworkPreview.framework.output.title),
                        frameworkPreview.framework.output.overview &&
                            wp.element.createElement(
                                'div',
                                { style: { marginTop: '8px' } },
                                wp.element.createElement('strong', null, 'Overview'),
                                wp.element.createElement('p', null, frameworkPreview.framework.output.overview)
                            ),
                        frameworkPreview.framework.output.context &&
                            wp.element.createElement(
                                'div',
                                { style: { marginTop: '8px' } },
                                wp.element.createElement('strong', null, 'Context'),
                                wp.element.createElement('p', null, frameworkPreview.framework.output.context)
                            ),
                        frameworkPreview.framework.output.application &&
                            wp.element.createElement(
                                'div',
                                { style: { marginTop: '8px' } },
                                wp.element.createElement('strong', null, 'Application'),
                                wp.element.createElement(
                                    'p',
                                    null,
                                    frameworkPreview.framework.output.application.intended_reader
                                        ? `Intended Reader: ${frameworkPreview.framework.output.application.intended_reader}`
                                        : null
                                ),
                                wp.element.createElement(
                                    'p',
                                    null,
                                    frameworkPreview.framework.output.application.use_case
                                        ? `Use Case: ${frameworkPreview.framework.output.application.use_case}`
                                        : null
                                )
                            ),
                        frameworkPreview.framework.output.observations &&
                            frameworkPreview.framework.output.observations.length > 0 &&
                            wp.element.createElement(
                                'div',
                                { style: { marginTop: '8px' } },
                                wp.element.createElement('strong', null, 'Observations'),
                                wp.element.createElement(
                                    'ul',
                                    null,
                                    frameworkPreview.framework.output.observations.map((item, idx) =>
                                        wp.element.createElement(
                                            'li',
                                            { key: idx },
                                            wp.element.createElement('strong', null, item.headline || 'Observation'),
                                            wp.element.createElement('p', null, item.detail || '')
                                        )
                                    )
                                )
                            ),
                        frameworkPreview.framework.output.key_themes &&
                            frameworkPreview.framework.output.key_themes.length > 0 &&
                            wp.element.createElement(
                                'div',
                                { style: { marginTop: '8px' } },
                                wp.element.createElement('strong', null, 'Key Themes'),
                                wp.element.createElement(
                                    'ul',
                                    null,
                                    frameworkPreview.framework.output.key_themes.map((theme, idx) =>
                                        wp.element.createElement('li', { key: idx }, theme)
                                    )
                                )
                            )
                    ),
                !frameworkPreview.framework?.output?.title &&
                    frameworkPreview.framework?.output?.h2_sections &&
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '12px' } },
                        wp.element.createElement('strong', null, 'Framework'),
                        wp.element.createElement(
                            'ul',
                            null,
                            frameworkPreview.framework.output.h2_sections.map((section, idx) =>
                                wp.element.createElement(
                                    'li',
                                    { key: idx },
                                    section.title || 'Section',
                                    section.h3_sections && section.h3_sections.length
                                        ? wp.element.createElement(
                                              'ul',
                                              null,
                                              section.h3_sections.map((h3, h3Idx) =>
                                                  wp.element.createElement('li', { key: h3Idx }, h3)
                                              )
                                          )
                                        : null
                                )
                            )
                        )
                    ),
                frameworkPreview.citations &&
                    frameworkPreview.citations.length > 0 &&
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '12px' } },
                        wp.element.createElement('strong', null, 'Citations'),
                        wp.element.createElement(
                            'ul',
                            null,
                            frameworkPreview.citations.map((citation, idx) =>
                                wp.element.createElement(
                                    'li',
                                    { key: idx },
                                    citation.apa || citation.title || citation.url || 'Citation',
                                    citation.relevance
                                        ? wp.element.createElement('p', null, citation.relevance)
                                        : null
                                )
                            )
                        )
                    )
            ),
        authorPreview &&
            wp.element.createElement(
                Modal,
                {
                    title: 'Author Draft',
                    onRequestClose: () => setAuthorPreview(null),
                    isDismissible: true,
                    shouldCloseOnClickOutside: false,
                },
                wp.element.createElement(
                    'div',
                    { style: { marginBottom: '12px', display: 'flex', gap: '8px' } },
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: () => handleExportAuthorDraft(authorPreview),
                            disabled: !(authorPreview?.author?.output),
                        },
                        'Export Draft'
                    ),
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: () => authorPreview?.author?.edit_url && window.open(authorPreview.author.edit_url, '_blank'),
                            disabled: !(authorPreview?.author?.edit_url),
                        },
                        'Open in Editor'
                    )
                ),
                wp.element.createElement('p', null, authorPreview.title || 'Draft'),
                wp.element.createElement(
                    'div',
                    { style: { whiteSpace: 'pre-wrap' } },
                    authorPreview.author?.output?.draft ||
                        authorPreview.author?.output?.content ||
                        'No draft available.'
                )
            )
    );
};

wp.element.render(
    wp.element.createElement(EditorialPlannerApp),
    document.getElementById('editorial-planner-app')
);

})();
