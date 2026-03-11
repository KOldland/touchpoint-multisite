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
    { label: 'Pricing', value: 'Pricing' },
];

const DEFAULT_RESEARCH_POLICY = {
    recency_months: 36,
    source_mix_minimums: {
        academic: 1,
        analyst: 1,
        industry: 1,
        case_study: 1,
    },
    blocked_domains: ['wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'],
};

const DEFAULT_AUTHOR_POLICY = {
    reporter_voice_required: true,
    disallow_first_person: true,
    disallow_em_dash: true,
    disallow_rhetorical_binaries: true,
    disallow_listicle_framing: true,
    disallow_tidy_conclusion: true,
    min_words: 1200,
    max_words: 2600,
    banned_phrases: [],
};

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

const AUTHOR_PROFILE_OPTIONS = [
    { label: 'Balanced', value: 'balanced' },
    { label: 'Journalistic', value: 'journalistic' },
    { label: 'Analytical', value: 'analytical' },
    { label: 'Executive', value: 'executive' },
];

const MIN_CITATIONS_REQUIRED = 2;
const PHASE_ORDER = ['phase1', 'phase2', 'phase3', 'phase4'];

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
    const [topicOptions, setTopicOptions] = useState(TOPIC_OPTIONS);
    const [selectedTopic, setSelectedTopic] = useState(TOPIC_OPTIONS[0].value);
    const [includes, setIncludes] = useState([]);
    const [excludes, setExcludes] = useState([]);
    const [starting, setStarting] = useState(false);

    const [detailModalOpen, setDetailModalOpen] = useState(false);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState('');
    const [sessionDetail, setSessionDetail] = useState(null);
    const [researchPolicyDetail, setResearchPolicyDetail] = useState(null);
    const [researchValidationDetail, setResearchValidationDetail] = useState(null);
    const [researchValidationLoading, setResearchValidationLoading] = useState(false);
    const [policyDraft, setPolicyDraft] = useState({ ...DEFAULT_RESEARCH_POLICY });
    const [policyDirty, setPolicyDirty] = useState(false);
    const [policySaving, setPolicySaving] = useState(false);
    const [authorPolicyDetail, setAuthorPolicyDetail] = useState(null);
    const [authorPolicyLoading, setAuthorPolicyLoading] = useState(false);
    const [authorPolicyDraft, setAuthorPolicyDraft] = useState({ ...DEFAULT_AUTHOR_POLICY });
    const [authorPolicyDirty, setAuthorPolicyDirty] = useState(false);
    const [authorPolicySaving, setAuthorPolicySaving] = useState(false);

    const [previewArticle, setPreviewArticle] = useState(null);
    const [frameworkPreview, setFrameworkPreview] = useState(null);
    const [frameworkLoading, setFrameworkLoading] = useState({});
    const [frameworkProgress, setFrameworkProgress] = useState({});
    const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);
    const [authorPreview, setAuthorPreview] = useState(null);
    const [authorProfileSelection, setAuthorProfileSelection] = useState({});
    const [authorProgress, setAuthorProgress] = useState({});
    const [authorLoading, setAuthorLoading] = useState({});
    const authorStatusRef = useRef({});
    const [phase4RerunLoading, setPhase4RerunLoading] = useState(false);
    const [phase3RerunLoading, setPhase3RerunLoading] = useState(false);
    const [phase2RerunLoading, setPhase2RerunLoading] = useState(false);
    const [phase1RerunLoading, setPhase1RerunLoading] = useState(false);
    const [articleActionLoading, setArticleActionLoading] = useState({});
    const [expandedPhases, setExpandedPhases] = useState({});
    const [focusLevel, setFocusLevel] = useState(50);
    const [synopsisModalOpen, setSynopsisModalOpen] = useState(false);
    const [synopsisPlan, setSynopsisPlan] = useState({});
    const [synopsisPlanLoading, setSynopsisPlanLoading] = useState(false);
    const [synopsisPlanError, setSynopsisPlanError] = useState('');
    const [synopsisGenerateLoading, setSynopsisGenerateLoading] = useState(false);
    const [synopsisTotal, setSynopsisTotal] = useState(20);
    const [focusDirty, setFocusDirty] = useState(false);
    const [thinkingPhraseIndex, setThinkingPhraseIndex] = useState(0);
    const [diveDeeperModalOpen, setDiveDeeperModalOpen] = useState(false);
    const [diveDeeperArticle, setDiveDeeperArticle] = useState(null);
    const [diveDeeperDepthSlider, setDiveDeeperDepthSlider] = useState(2);
    const isDeepDiveLoading = diveDeeperArticle ? !!articleActionLoading[`dive_deeper:${diveDeeperArticle.id}`] : false;
    const showFocusControls = true;
    // URL-based routing: check if we're viewing a specific session detail
    const params = new URLSearchParams(window.location.search);
    const viewingSessionId = params.get('session') || params.get('session_id');

    const navigateToSession = (sessionId) => {
        if (!sessionId) {
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('session_id', String(sessionId));
        url.searchParams.delete('session');
        window.history.pushState(null, '', `${url.pathname}${url.search}`);
        openSessionDetail(sessionId);
    };

    const navigateBack = () => {
        const url = new URL(window.location.href);
        url.searchParams.delete('session_id');
        url.searchParams.delete('session');
        window.history.pushState(null, '', `${url.pathname}${url.search}`);
        setDetailModalOpen(false);
        setSessionDetail(null);
        setResearchPolicyDetail(null);
        setResearchValidationDetail(null);
        setPolicyDraft({ ...DEFAULT_RESEARCH_POLICY });
        setPolicyDirty(false);
        setAuthorPolicyDetail(null);
        setAuthorPolicyDraft({ ...DEFAULT_AUTHOR_POLICY });
        setAuthorPolicyDirty(false);
        loadSessions();
    };

    useEffect(() => {
        loadSessions();
        loadTopLineCategories();
    }, []);

    const loadTopLineCategories = async () => {
        try {
            const response = await apiFetch({
                path: 'dual-gpt/v1/planner/top-line-categories',
                method: 'GET',
            });
            const rows = Array.isArray(response?.top_line_categories) ? response.top_line_categories : [];
            if (!rows.length) {
                return;
            }

            const options = rows.map((row) => ({ label: row.name, value: row.name }));
            setTopicOptions(options);
            if (!selectedTopic || !options.some((option) => option.value === selectedTopic)) {
                setSelectedTopic(options[0].value);
            }
        } catch (error) {
            console.error('Failed to load top-line categories:', error);
        }
    };

    useEffect(() => {
        if (!viewingSessionId) {
            return;
        }
        openSessionDetail(viewingSessionId);
    }, [viewingSessionId]);

    useEffect(() => {
        if (!showFocusControls) {
            return;
        }
        if (sessionDetail?.meta?.focus_level != null && !focusDirty) {
            setFocusLevel(Number(sessionDetail.meta.focus_level));
        }
    }, [sessionDetail?.meta?.focus_level, focusDirty, showFocusControls]);

    useEffect(() => {
        if (!researchPolicyDetail || policyDirty) {
            return;
        }
        setPolicyDraft({
            recency_months: Number(researchPolicyDetail?.recency_months ?? DEFAULT_RESEARCH_POLICY.recency_months),
            source_mix_minimums: {
                academic: Number(researchPolicyDetail?.source_mix_minimums?.academic ?? DEFAULT_RESEARCH_POLICY.source_mix_minimums.academic),
                analyst: Number(researchPolicyDetail?.source_mix_minimums?.analyst ?? DEFAULT_RESEARCH_POLICY.source_mix_minimums.analyst),
                industry: Number(researchPolicyDetail?.source_mix_minimums?.industry ?? DEFAULT_RESEARCH_POLICY.source_mix_minimums.industry),
                case_study: Number(researchPolicyDetail?.source_mix_minimums?.case_study ?? DEFAULT_RESEARCH_POLICY.source_mix_minimums.case_study),
            },
            blocked_domains: Array.isArray(researchPolicyDetail?.blocked_domains)
                ? [...researchPolicyDetail.blocked_domains]
                : [...DEFAULT_RESEARCH_POLICY.blocked_domains],
        });
    }, [researchPolicyDetail, policyDirty]);

    useEffect(() => {
        if (!authorPolicyDetail || authorPolicyDirty) {
            return;
        }
        setAuthorPolicyDraft({
            reporter_voice_required: Boolean(authorPolicyDetail?.reporter_voice_required ?? DEFAULT_AUTHOR_POLICY.reporter_voice_required),
            disallow_first_person: Boolean(authorPolicyDetail?.disallow_first_person ?? DEFAULT_AUTHOR_POLICY.disallow_first_person),
            disallow_em_dash: Boolean(authorPolicyDetail?.disallow_em_dash ?? DEFAULT_AUTHOR_POLICY.disallow_em_dash),
            disallow_rhetorical_binaries: Boolean(authorPolicyDetail?.disallow_rhetorical_binaries ?? DEFAULT_AUTHOR_POLICY.disallow_rhetorical_binaries),
            disallow_listicle_framing: Boolean(authorPolicyDetail?.disallow_listicle_framing ?? DEFAULT_AUTHOR_POLICY.disallow_listicle_framing),
            disallow_tidy_conclusion: Boolean(authorPolicyDetail?.disallow_tidy_conclusion ?? DEFAULT_AUTHOR_POLICY.disallow_tidy_conclusion),
            min_words: Number(authorPolicyDetail?.min_words ?? DEFAULT_AUTHOR_POLICY.min_words),
            max_words: Number(authorPolicyDetail?.max_words ?? DEFAULT_AUTHOR_POLICY.max_words),
            banned_phrases: Array.isArray(authorPolicyDetail?.banned_phrases)
                ? [...authorPolicyDetail.banned_phrases]
                : [],
        });
    }, [authorPolicyDetail, authorPolicyDirty]);

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
                    research_policy: DEFAULT_RESEARCH_POLICY,
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
            setSelectedTopic(topicOptions[0]?.value || TOPIC_OPTIONS[0].value);
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
            if (!silent && sessionId) {
                const url = new URL(window.location.href);
                if (url.searchParams.get('session_id') !== String(sessionId)) {
                    url.searchParams.set('session_id', String(sessionId));
                    url.searchParams.delete('session');
                    window.history.pushState(null, '', `${url.pathname}${url.search}`);
                }
            }
            if (!silent) {
                setDetailModalOpen(true);
                setDetailLoading(true);
                setDetailError('');
                setFocusDirty(false);
            }
            setResearchValidationLoading(true);
            setAuthorPolicyLoading(true);
            const data = await apiFetch({
                path: `dual-gpt/v1/sessions/${sessionId}`,
                method: 'GET',
            });
            handleAuthorStatusTransitions(data);
            setSessionDetail(data);

            try {
                const validationData = await apiFetch({
                    path: `dual-gpt/v1/planner/research-validation?session_id=${sessionId}`,
                    method: 'GET',
                });
                setResearchPolicyDetail(validationData?.research_policy || data?.meta?.research_policy || null);
                setResearchValidationDetail(validationData?.research_validation || null);
            } catch (validationError) {
                console.error('Failed to load research validation detail:', validationError);
                setResearchPolicyDetail(data?.meta?.research_policy || null);
                setResearchValidationDetail(null);
            }

            try {
                const authorPolicyResponse = await apiFetch({
                    path: `dual-gpt/v1/planner/author-policy?session_id=${sessionId}`,
                    method: 'GET',
                });
                setAuthorPolicyDetail(authorPolicyResponse?.author_policy || data?.meta?.author_policy || null);
            } catch (authorPolicyError) {
                console.error('Failed to load author policy detail:', authorPolicyError);
                setAuthorPolicyDetail(data?.meta?.author_policy || null);
            }

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
            setResearchPolicyDetail(null);
            setResearchValidationDetail(null);
            setAuthorPolicyDetail(null);
        } finally {
            setResearchValidationLoading(false);
            setAuthorPolicyLoading(false);
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

    const getCitationCount = (article) => {
        const explicitCount = Number(article?.citation_count || 0);
        if (explicitCount > 0) {
            return explicitCount;
        }
        return Array.isArray(article?.citations) ? article.citations.length : 0;
    };

    const getRecommendedAuthorProfile = (article) => {
        const blob = JSON.stringify({
            title: article?.title || article?.headline || '',
            summary: article?.summary || article?.brief || '',
            keywords: article?.keywords || [],
            framework: article?.framework?.output || {},
        }).toLowerCase();

        if (/\b(board|ceo|cfo|leadership|executive|strategy|roadmap|portfolio|investment)\b/.test(blob)) {
            return 'executive';
        }
        if (/\b(data|model|forecast|sensitivity|variance|analysis|benchmark|quant|correlation|method)\b/.test(blob)) {
            return 'analytical';
        }
        if (/\b(report|interview|case study|investigation|survey|field|news|press|announced)\b/.test(blob)) {
            return 'journalistic';
        }

        return 'balanced';
    };

    const getSelectedAuthorProfile = (article) =>
        authorProfileSelection?.[article?.id] || article?.author?.profile || getRecommendedAuthorProfile(article);

    const getAuthorProfileLabel = (value) =>
        AUTHOR_PROFILE_OPTIONS.find((item) => item.value === value)?.label || 'Balanced';

    const isFrameworkGenerating = Object.values(frameworkLoading || {}).some(Boolean);
    const isAuthorGenerating = Object.values(authorLoading || {}).some(Boolean);
    const hasRunningFrameworks = (sessionDetail?.meta?.articles || []).some((article) =>
        ['running', 'queued'].includes(article?.framework?.status)
    );
    const hasRunningAuthors = (sessionDetail?.meta?.articles || []).some((article) =>
        ['running', 'queued'].includes(article?.author?.status)
    );

        const showThinkingIndicator =
                synopsisGenerateLoading === true ||
                isFrameworkGenerating ||
                isAuthorGenerating ||
                phase4RerunLoading ||
                phase3RerunLoading ||
                phase2RerunLoading ||
                phase1RerunLoading ||
                ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase1?.status) ||
                ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase2?.status) ||
                ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase3?.status) ||
                ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase4?.status) ||
                hasRunningFrameworks ||
                hasRunningAuthors;

        const activePhaseLabel = synopsisGenerateLoading
                ? 'Generating Article Synopses'
                : isAuthorGenerating
                    ? 'Generating Article Draft'
                    : isFrameworkGenerating
                        ? 'Generating Framework'
                        : phase4RerunLoading
                            ? 'Research Phase 4'
                            : phase3RerunLoading
                                ? 'Research Phase 3'
                                : phase2RerunLoading
                                    ? 'Research Phase 2'
                                    : phase1RerunLoading
                                        ? 'Research Phase 1'
                                        : hasRunningAuthors
                                            ? 'Generating Article Draft'
                                            : hasRunningFrameworks
                                                ? 'Generating Framework'
                                        : ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase4?.status)
                                            ? 'Research Phase 4'
                                            : ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase3?.status)
                                                ? 'Research Phase 3'
                                                : ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase2?.status)
                                                    ? 'Research Phase 2'
                                                    : ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase1?.status)
                                                        ? 'Research Phase 1'
                                                        : '';

    // Sync preview snapshots from live session data so modals always reflect latest state
    useEffect(() => {
        const articles = sessionDetail?.meta?.articles;
        if (!Array.isArray(articles)) return;
        if (authorPreview?.id) {
            const updated = articles.find((a) => a.id === authorPreview.id);
            if (updated && updated !== authorPreview) {
                setAuthorPreview(updated);
            }
        }
        if (frameworkPreview?.id) {
            const updated = articles.find((a) => a.id === frameworkPreview.id);
            if (updated && updated !== frameworkPreview) {
                setFrameworkPreview(updated);
            }
        }
    }, [sessionDetail]);

    useEffect(() => {
        const articles = sessionDetail?.meta?.articles;
        if (!Array.isArray(articles) || !articles.length) {
            return;
        }
        setAuthorProfileSelection((prev) => {
            const next = { ...prev };
            let changed = false;
            articles.forEach((article) => {
                if (!article?.id) {
                    return;
                }
                const existing = next[article.id];
                if (existing) {
                    return;
                }
                const persisted = article?.author?.profile;
                next[article.id] = persisted || getRecommendedAuthorProfile(article);
                changed = true;
            });
            return changed ? next : prev;
        });
    }, [sessionDetail?.meta?.articles]);

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

    const summarizeAuthorValidation = (meta) => {
        const summary = {
            drafts_with_output: 0,
            drafts_failed: 0,
            error_count: 0,
            warning_count: 0,
            issues: [],
        };

        const articles = Array.isArray(meta?.articles) ? meta.articles : [];
        articles.forEach((article) => {
            const author = article?.author || {};
            if (author?.status === 'failed') {
                summary.drafts_failed += 1;
            }

            const output = author?.output;
            if (!output || typeof output !== 'object') {
                return;
            }

            summary.drafts_with_output += 1;

            const validationErrors = Array.isArray(output?.validation_errors)
                ? output.validation_errors
                : [];
            const warnings = Array.isArray(output?.warnings) ? output.warnings : [];

            summary.error_count += validationErrors.length;
            summary.warning_count += warnings.length;

            validationErrors.forEach((message) => {
                if (!message) {
                    return;
                }
                summary.issues.push({ severity: 'error', message: String(message) });
            });

            warnings.forEach((message) => {
                if (!message) {
                    return;
                }
                summary.issues.push({ severity: 'warning', message: String(message) });
            });
        });

        return summary;
    };

    const formatPolicyDomainList = (domains) => {
        if (!Array.isArray(domains) || domains.length === 0) {
            return '—';
        }
        return domains.join(', ');
    };

    const normalizeDomainTokens = (tokens) => {
        if (!Array.isArray(tokens)) {
            return [];
        }
        const seen = new Set();
        return tokens
            .map((domain) => String(domain || '').trim().toLowerCase())
            .map((domain) => domain.replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/.*$/, ''))
            .filter((domain) => {
                if (!domain || seen.has(domain)) {
                    return false;
                }
                seen.add(domain);
                return true;
            });
    };

    const normalizePhraseTokens = (tokens) => {
        if (!Array.isArray(tokens)) {
            return [];
        }
        const seen = new Set();
        return tokens
            .map((phrase) => String(phrase || '').trim().toLowerCase())
            .filter((phrase) => {
                if (!phrase || seen.has(phrase)) {
                    return false;
                }
                seen.add(phrase);
                return true;
            });
    };

    const handleSavePolicy = async () => {
        if (!sessionDetail?.id) {
            return;
        }

        try {
            setPolicySaving(true);
            const payload = {
                recency_months: Number(policyDraft?.recency_months ?? DEFAULT_RESEARCH_POLICY.recency_months),
                source_mix_minimums: {
                    academic: Number(policyDraft?.source_mix_minimums?.academic ?? 0),
                    analyst: Number(policyDraft?.source_mix_minimums?.analyst ?? 0),
                    industry: Number(policyDraft?.source_mix_minimums?.industry ?? 0),
                    case_study: Number(policyDraft?.source_mix_minimums?.case_study ?? 0),
                },
                blocked_domains: normalizeDomainTokens(policyDraft?.blocked_domains),
            };

            const saveResponse = await apiFetch({
                path: 'dual-gpt/v1/planner/policy',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    research_policy: payload,
                },
            });

            setPolicyDirty(false);
            dispatch('core/notices').createNotice(
                'success',
                saveResponse?.changed ? 'Research policy saved.' : 'No policy changes detected.',
                { type: 'snackbar' }
            );

            await openSessionDetail(sessionDetail.id, { silent: true });
        } catch (error) {
            console.error('Failed to save research policy:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Failed to save research policy.',
                { type: 'snackbar' }
            );
        } finally {
            setPolicySaving(false);
        }
    };

    const handleResetPolicyDraft = () => {
        const source = researchPolicyDetail || DEFAULT_RESEARCH_POLICY;
        setPolicyDraft({
            recency_months: Number(source?.recency_months ?? DEFAULT_RESEARCH_POLICY.recency_months),
            source_mix_minimums: {
                academic: Number(source?.source_mix_minimums?.academic ?? DEFAULT_RESEARCH_POLICY.source_mix_minimums.academic),
                analyst: Number(source?.source_mix_minimums?.analyst ?? DEFAULT_RESEARCH_POLICY.source_mix_minimums.analyst),
                industry: Number(source?.source_mix_minimums?.industry ?? DEFAULT_RESEARCH_POLICY.source_mix_minimums.industry),
                case_study: Number(source?.source_mix_minimums?.case_study ?? DEFAULT_RESEARCH_POLICY.source_mix_minimums.case_study),
            },
            blocked_domains: Array.isArray(source?.blocked_domains)
                ? [...source.blocked_domains]
                : [...DEFAULT_RESEARCH_POLICY.blocked_domains],
        });
        setPolicyDirty(false);
    };

    const handleSaveAuthorPolicy = async () => {
        if (!sessionDetail?.id) {
            return;
        }

        try {
            setAuthorPolicySaving(true);
            const payload = {
                reporter_voice_required: Boolean(authorPolicyDraft?.reporter_voice_required),
                disallow_first_person: Boolean(authorPolicyDraft?.disallow_first_person),
                disallow_em_dash: Boolean(authorPolicyDraft?.disallow_em_dash),
                disallow_rhetorical_binaries: Boolean(authorPolicyDraft?.disallow_rhetorical_binaries),
                disallow_listicle_framing: Boolean(authorPolicyDraft?.disallow_listicle_framing),
                disallow_tidy_conclusion: Boolean(authorPolicyDraft?.disallow_tidy_conclusion),
                min_words: Number(authorPolicyDraft?.min_words ?? DEFAULT_AUTHOR_POLICY.min_words),
                max_words: Number(authorPolicyDraft?.max_words ?? DEFAULT_AUTHOR_POLICY.max_words),
                banned_phrases: normalizePhraseTokens(authorPolicyDraft?.banned_phrases || []),
            };

            const saveResponse = await apiFetch({
                path: 'dual-gpt/v1/planner/author-policy',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    author_policy: payload,
                },
            });

            setAuthorPolicyDirty(false);
            dispatch('core/notices').createNotice(
                'success',
                saveResponse?.changed ? 'Author policy saved.' : 'No author policy changes detected.',
                { type: 'snackbar' }
            );

            await openSessionDetail(sessionDetail.id, { silent: true });
        } catch (error) {
            console.error('Failed to save author policy:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Failed to save author policy.',
                { type: 'snackbar' }
            );
        } finally {
            setAuthorPolicySaving(false);
        }
    };

    const handleResetAuthorPolicyDraft = () => {
        const source = authorPolicyDetail || DEFAULT_AUTHOR_POLICY;
        setAuthorPolicyDraft({
            reporter_voice_required: Boolean(source?.reporter_voice_required ?? DEFAULT_AUTHOR_POLICY.reporter_voice_required),
            disallow_first_person: Boolean(source?.disallow_first_person ?? DEFAULT_AUTHOR_POLICY.disallow_first_person),
            disallow_em_dash: Boolean(source?.disallow_em_dash ?? DEFAULT_AUTHOR_POLICY.disallow_em_dash),
            disallow_rhetorical_binaries: Boolean(source?.disallow_rhetorical_binaries ?? DEFAULT_AUTHOR_POLICY.disallow_rhetorical_binaries),
            disallow_listicle_framing: Boolean(source?.disallow_listicle_framing ?? DEFAULT_AUTHOR_POLICY.disallow_listicle_framing),
            disallow_tidy_conclusion: Boolean(source?.disallow_tidy_conclusion ?? DEFAULT_AUTHOR_POLICY.disallow_tidy_conclusion),
            min_words: Number(source?.min_words ?? DEFAULT_AUTHOR_POLICY.min_words),
            max_words: Number(source?.max_words ?? DEFAULT_AUTHOR_POLICY.max_words),
            banned_phrases: Array.isArray(source?.banned_phrases) ? [...source.banned_phrases] : [],
        });
        setAuthorPolicyDirty(false);
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

        const citationCount = getCitationCount(article);
        if (citationCount < MIN_CITATIONS_REQUIRED) {
            dispatch('core/notices').createNotice(
                'error',
                `Framework generation requires at least ${MIN_CITATIONS_REQUIRED} citations. This article has ${citationCount}.`,
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

        const citationCount = getCitationCount(article);
        if (citationCount < MIN_CITATIONS_REQUIRED) {
            dispatch('core/notices').createNotice(
                'error',
                `Author generation requires at least ${MIN_CITATIONS_REQUIRED} citations. This article has ${citationCount}.`,
                { type: 'snackbar' }
            );
            return;
        }

        const selectedProfile = getSelectedAuthorProfile(article);

        try {
            setAuthorLoading((prev) => ({ ...prev, [article.id]: true }));
            console.log('[Planner] Run Author click', {
                sessionId: sessionDetail.id,
                articleId: article.id,
                authorProfile: selectedProfile,
            });
            const data = await apiFetch({
                path: 'dual-gpt/v1/planner/run-author',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    article_id: article.id,
                    author_profile: selectedProfile,
                },
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
                            profile: selectedProfile,
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

    const runArticleAction = async (article, action, successMessage, params = null) => {
        if (!sessionDetail?.id || !article?.id) {
            return;
        }

        const loadingKey = `${action}:${article.id}`;
        try {
            setArticleActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
            const payload = {
                session_id: sessionDetail.id,
                article_id: article.id,
                action,
                ...(showFocusControls ? { focus_level: focusLevel } : {}),
                ...(params ? { params } : {}),
            };
            await apiFetch({
                path: 'dual-gpt/v1/planner/article-action',
                method: 'POST',
                data: payload,
            });

            dispatch('core/notices').createNotice('success', successMessage, { type: 'snackbar' });
            await refreshSessionDetail();
        } catch (error) {
            console.error(`Article action failed (${action}):`, error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Article action failed.',
                { type: 'snackbar' }
            );
        } finally {
            setArticleActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
        }
    };

    const handleDismissArticle = async (article) => {
        if (!article?.id) {
            return;
        }
        const title = article?.headline || article?.title || 'this article';
        const confirmed = window.confirm(`Dismiss ${title}? This removes it from this planner session.`);
        if (!confirmed) {
            return;
        }
        await runArticleAction(article, 'dismiss', 'Article dismissed from this planner session.');
    };

    const handleDeepDiveArticle = (article) => {
        setDiveDeeperArticle(article);
        setDiveDeeperDepthSlider(2);
        setDiveDeeperModalOpen(true);
    };

    const mapSliderToDepthParams = (sliderValue) => {
        const stops = [
            {
                target_min_citations: 2,
                recency_months: 6,
                source_mix_minimums: { industry: 1 },
            },
            {
                target_min_citations: 3,
                recency_months: 12,
                source_mix_minimums: { industry: 1, news: 1 },
            },
            {
                target_min_citations: 4,
                recency_months: 18,
                source_mix_minimums: { industry: 1, news: 1, research: 1 },
            },
            {
                target_min_citations: 6,
                recency_months: 24,
                source_mix_minimums: { industry: 2, news: 1, research: 1 },
            },
            {
                target_min_citations: 8,
                recency_months: 36,
                source_mix_minimums: { industry: 2, news: 2, research: 2 },
            },
        ];
        return stops[Math.min(Math.max(sliderValue, 0), 4)];
    };

    const handleDiveDeeperSubmit = async () => {
        if (!diveDeeperArticle) {
            return;
        }
        const params = mapSliderToDepthParams(diveDeeperDepthSlider);
        await runArticleAction(
            diveDeeperArticle,
            'dive_deeper',
            'Dive deeper research initiated. Specialist is gathering additional citations...',
            params
        );
        setDiveDeeperModalOpen(false);
    };

    const handleOpinionPieceArticle = async (article) => {
        const params = { allow_low_citations: true };
        await runArticleAction(
            article,
            'opinion_piece',
            'Opinion piece initiated. Author is generating perspective on this topic...',
            params
        );
    };

    const renderPhaseSummaries = () => {
        const phases = sessionDetail?.meta?.phases || {};
        const phaseOrder = PHASE_ORDER;

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

        const rows = articles.map((article, index) => {
            const metric = findMetric(article.keywords || []);
            const volumeValue = Number(metric?.search_volume ?? 0);
            const rankingSignal = Number(metric?.priority_score ?? metric?.rank ?? 0);
            const citationsCount = getCitationCount(article);
            return {
                article,
                index,
                metric,
                volumeValue: Number.isFinite(volumeValue) ? volumeValue : 0,
                rankingSignal: Number.isFinite(rankingSignal) ? rankingSignal : 0,
                citationsCount,
            };
        });

        const maxVolume = Math.max(1, ...rows.map((item) => item.volumeValue || 0));
        const maxRankingSignal = Math.max(1, ...rows.map((item) => item.rankingSignal || 0));
        const maxCitations = Math.max(1, ...rows.map((item) => item.citationsCount || 0));
        const scoredRows = rows
            .map((item) => {
                const priorityScore =
                    ((item.volumeValue / maxVolume) * 0.45) +
                    ((item.citationsCount / maxCitations) * 0.35) +
                    ((item.rankingSignal / maxRankingSignal) * 0.2);
                const marketSignal = Math.round((item.volumeValue / maxVolume) * 100);
                return { ...item, priorityScore, marketSignal };
            })
            .sort((a, b) => b.priorityScore - a.priorityScore);

        return wp.element.createElement(
            'div',
            null,
            wp.element.createElement(
                'p',
                { style: { margin: '8px 0', fontSize: '12px', color: '#50575e' } },
                'Priority score weights: 45% Market Signal, 35% Citation Strength, 20% Keyword Ranking. Author profile recommendation is inferred from article intent and framework language.'
            ),
            wp.element.createElement(
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
                    wp.element.createElement('th', null, 'Market Signal'),
                    wp.element.createElement('th', null, 'Citations'),
                    wp.element.createElement('th', null, 'Framework Status'),
                    wp.element.createElement('th', null, 'Author Status'),
                    wp.element.createElement('th', null, 'Actions')
                )
            ),
            wp.element.createElement(
                'tbody',
                null,
                scoredRows.map((row, priorityIndex) => {
                    const { article, index, metric, citationsCount, priorityScore, marketSignal } = row;
                    const volume = metric?.search_volume ?? '—';
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
                    const meetsCitationThreshold = citationsCount >= MIN_CITATIONS_REQUIRED;
                    const selectedProfile = getSelectedAuthorProfile(article);
                    const recommendedProfile = getRecommendedAuthorProfile(article);
                    const frameworkActionLabel =
                        frameworkStatus === 'complete' ? 'Regenerate Framework' : 'Generate Framework';
                    const isDismissLoading = !!articleActionLoading[`dismiss:${article.id}`];
                    const isDeepDiveLoading = !!articleActionLoading[`deep_dive:${article.id}`];
                    const isOpinionLoading = !!articleActionLoading[`opinion_piece:${article.id}`];
                    return wp.element.createElement(
                        'tr',
                        { key: `${article.headline || article.title || article.id}-${index}` },
                        wp.element.createElement(
                            'td',
                            null,
                            wp.element.createElement('strong', null, `#${priorityIndex + 1}`),
                            ' ',
                            article.headline || article.title || 'Untitled',
                            wp.element.createElement(
                                'div',
                                { style: { fontSize: '11px', color: '#50575e', marginTop: '2px' } },
                                `Priority score: ${(priorityScore * 100).toFixed(0)}`
                            )
                        ),
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
                        wp.element.createElement(
                            'td',
                            { title: `Search volume: ${volume}` },
                            wp.element.createElement('strong', null, `${marketSignal}%`),
                            wp.element.createElement(
                                'div',
                                { style: { fontSize: '11px', color: '#50575e', marginTop: '2px' } },
                                `Vol: ${volume}`
                            )
                        ),
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
                                SelectControl,
                                {
                                    label: 'Author profile',
                                    value: selectedProfile,
                                    options: AUTHOR_PROFILE_OPTIONS,
                                    onChange: (value) => {
                                        setAuthorProfileSelection((prev) => ({
                                            ...prev,
                                            [article.id]: value,
                                        }));
                                    },
                                    help: `Recommended: ${getAuthorProfileLabel(recommendedProfile)}`,
                                }
                            ),
                            !meetsCitationThreshold &&
                                wp.element.createElement(
                                    Notice,
                                    { status: 'warning', isDismissible: false },
                                    `Requires at least ${MIN_CITATIONS_REQUIRED} citations before framework/author generation.`
                                ),
                            !meetsCitationThreshold &&
                                wp.element.createElement(
                                    Button,
                                    {
                                        isSecondary: true,
                                        onClick: () => handleDeepDiveArticle(article),
                                        style: { marginRight: '8px', marginBottom: '8px' },
                                        disabled: isDeepDiveLoading,
                                    },
                                    isDeepDiveLoading ? wp.element.createElement(Spinner, null) : 'Dive Deeper'
                                ),
                            !meetsCitationThreshold &&
                                wp.element.createElement(
                                    Button,
                                    {
                                        isPrimary: true,
                                        onClick: () => handleOpinionPieceArticle(article),
                                        style: { marginRight: '8px', marginBottom: '8px' },
                                        disabled: isOpinionLoading,
                                    },
                                    isOpinionLoading ? wp.element.createElement(Spinner, null) : 'Opinion Piece'
                                ),
                            !meetsCitationThreshold &&
                                wp.element.createElement(
                                    Button,
                                    {
                                        isSecondary: true,
                                        onClick: () => handleDismissArticle(article),
                                        style: { marginRight: '8px', marginBottom: '8px' },
                                        disabled: isDismissLoading,
                                    },
                                    isDismissLoading ? wp.element.createElement(Spinner, null) : 'Dismiss'
                                ),
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
                                    disabled: !meetsCitationThreshold || !frameworkReady || isAuthorLoading || authorStatus === 'running' || authorStatus === 'queued',
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
                                    disabled: !meetsCitationThreshold || !!frameworkLoading[index],
                                },
                                frameworkLoading[index] ? wp.element.createElement(Spinner, null) : frameworkActionLabel
                            )
                        )
                    );
                })
            )
            )
        );
    };

    const renderFrameworks = () => {
        const frameworks =
            (sessionDetail?.meta?.frameworks || []).length > 0
                ? sessionDetail.meta.frameworks
                : (sessionDetail?.meta?.articles || [])
                      .filter((article) => article?.framework?.output)
                      .map((article, index) => ({
                          job_id: article?.framework?.job_id || article?.id || `framework-${index}`,
                          article_id: article?.id || null,
                          article_title: article?.headline || article?.title || 'Framework',
                          output: article?.framework?.output,
                      }));
        if (!frameworks.length) {
            return null;
        }

        const formatFrameworkOutput = (output) => {
            if (!output) {
                return 'No output captured.';
            }
            if (typeof output === 'string') {
                return output;
            }
            try {
                return JSON.stringify(output, null, 2);
            } catch (error) {
                return 'Framework output available but could not be formatted.';
            }
        };

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
                            formatFrameworkOutput(framework.output)
                        )
                    )
                )
            )
        );
    };

    const phase1Complete = sessionDetail?.meta?.phases?.phase1?.status === 'completed';
    const phase2Complete = sessionDetail?.meta?.phases?.phase2?.status === 'completed';
    const allPhasesExpanded = PHASE_ORDER.every((key) => !!expandedPhases[key]);
    const phase3Complete = sessionDetail?.meta?.phases?.phase3?.status === 'completed';
    const phase4Complete = sessionDetail?.meta?.phases?.phase4?.status === 'completed';
    const synopsisPlanTotal = Object.values(synopsisPlan).reduce(
        (sum, value) => sum + (parseInt(value || 0, 10) || 0),
        0
    );
    const focusLabel = getFocusLabel(focusLevel);
    const synopsisEstimate = estimateSynopses(sessionDetail?.meta, focusLevel);
    const authorValidationSummary = summarizeAuthorValidation(sessionDetail?.meta);

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
                              showThinkingIndicator &&
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
                                      wp.element.createElement('span', null, `${activePhaseLabel || 'Working'} · ${THINKING_PHRASES[thinkingPhraseIndex]}...`)
                                  )
                          ),
                          wp.element.createElement(
                              'div',
                              null,
                              wp.element.createElement(
                                  Button,
                                  {
                                      isSecondary: true,
                                      onClick: () => {
                                          setExpandedPhases(
                                              allPhasesExpanded
                                                  ? {}
                                                  : PHASE_ORDER.reduce((acc, key) => {
                                                        acc[key] = true;
                                                        return acc;
                                                    }, {})
                                          );
                                      },
                                      style: { marginRight: '8px' },
                                  },
                                  allPhasesExpanded ? 'Collapse All Phases' : 'Expand All Phases'
                              ),
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
                                                                renderArticlesTable(),
                                                                renderFrameworks()
                            )
                          : null
                  ),
            diveDeeperModalOpen &&
                wp.element.createElement(
                    Modal,
                    {
                        title: 'Dive Deeper - Research Depth',
                        onRequestClose: () => !isDeepDiveLoading && setDiveDeeperModalOpen(false),
                    },
                    isDeepDiveLoading
                        ? wp.element.createElement(
                              'div',
                              { style: { textAlign: 'center', padding: '32px 24px' } },
                              wp.element.createElement(Spinner, null),
                              wp.element.createElement(
                                  'p',
                                  { style: { marginTop: '16px', fontSize: '14px', color: '#666' } },
                                  'Specialist is researching additional citations...'
                              ),
                              wp.element.createElement(
                                  'p',
                                  { style: { marginTop: '8px', fontSize: '12px', color: '#999' } },
                                  'This may take a few moments.'
                              )
                          )
                        : wp.element.createElement(
                              'div',
                              { style: { marginBottom: '24px' } },
                              wp.element.createElement(
                                  'p',
                                  { style: { marginBottom: '16px', color: '#50575e' } },
                                  'Select how much research depth you want. The slider controls how many citations and how recent the sources should be.'
                              ),
                              wp.element.createElement(RangeControl, {
                                  label: 'Depth Level',
                                  value: diveDeeperDepthSlider,
                                  onChange: setDiveDeeperDepthSlider,
                                  min: 0,
                                  max: 4,
                                  step: 1,
                                  marks: [
                                      { value: 0, label: 'A bit more' },
                                      { value: 1, label: '' },
                                      { value: 2, label: 'Default' },
                                      { value: 3, label: '' },
                                      { value: 4, label: 'Deep dive' },
                                  ],
                              }),
                              wp.element.createElement(
                                  'div',
                                  { style: { marginTop: '16px', padding: '12px', background: '#f0f0f0', borderRadius: '4px' } },
                                  wp.element.createElement(
                                      'strong',
                                      null,
                                      'Parameters:'
                                  ),
                                  (() => {
                                      const params = mapSliderToDepthParams(diveDeeperDepthSlider);
                                      return wp.element.createElement(
                                          'ul',
                                          { style: { margin: '8px 0 0', paddingLeft: '20px' } },
                                          wp.element.createElement(
                                              'li',
                                              null,
                                              `Target: ${params.target_min_citations} citations`
                                          ),
                                          wp.element.createElement(
                                              'li',
                                              null,
                                              `Recency: Last ${params.recency_months} months`
                                          ),
                                          wp.element.createElement(
                                              'li',
                                              null,
                                              `Sources: ${Object.keys(params.source_mix_minimums).join(', ')}`
                                          )
                                      );
                                  })()
                              ),
                              wp.element.createElement(
                                  'div',
                                  { style: { marginTop: '24px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: () => setDiveDeeperModalOpen(false),
                                      },
                                      'Cancel'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isPrimary: true,
                                          onClick: handleDiveDeeperSubmit,
                                          disabled: isDeepDiveLoading,
                                      },
                                      isDeepDiveLoading ? wp.element.createElement(Spinner, null) : 'Start'
                                  )
                              )
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
                        { style: { marginBottom: '12px', display: 'flex', gap: '8px', flexWrap: 'wrap' } },
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
                    wp.element.createElement(
                        'div',
                        {
                            style: {
                                marginBottom: '12px',
                                padding: '10px 12px',
                                border: '1px solid #dcdcde',
                                borderRadius: '6px',
                                background: '#fff',
                            },
                        },
                        wp.element.createElement('strong', null, authorPreview.title || 'Draft'),
                        wp.element.createElement(
                            'p',
                            { style: { margin: '6px 0 0', color: '#50575e' } },
                            `Profile: ${getAuthorProfileLabel(getSelectedAuthorProfile(authorPreview))} · Recommended: ${getAuthorProfileLabel(getRecommendedAuthorProfile(authorPreview))}`
                        )
                    ),
                    wp.element.createElement(
                        'div',
                        {
                            style: {
                                whiteSpace: 'pre-wrap',
                                maxHeight: '60vh',
                                overflowY: 'auto',
                                padding: '12px',
                                border: '1px solid #dcdcde',
                                borderRadius: '6px',
                                background: '#fff',
                            },
                        },
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
                    options: topicOptions,
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
                    onRequestClose: navigateBack,
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
                              {
                                  style: {
                                      marginBottom: '12px',
                                      padding: '12px',
                                      border: '1px solid #dcdcde',
                                      borderRadius: '6px',
                                      background: '#fff',
                                  },
                              },
                              wp.element.createElement('strong', null, 'Effective Research Policy'),
                              researchValidationLoading
                                  ? wp.element.createElement('p', { style: { margin: '8px 0 0' } }, 'Loading policy…')
                                  : wp.element.createElement(
                                        wp.element.Fragment,
                                        null,
                                        wp.element.createElement(
                                            'p',
                                            { style: { margin: '8px 0 4px', color: '#50575e' } },
                                            `Recency: ${researchPolicyDetail?.recency_months ?? '—'} months · Source mix minimums: academic ${researchPolicyDetail?.source_mix_minimums?.academic ?? 0}, analyst ${researchPolicyDetail?.source_mix_minimums?.analyst ?? 0}, industry ${researchPolicyDetail?.source_mix_minimums?.industry ?? 0}, case study ${researchPolicyDetail?.source_mix_minimums?.case_study ?? 0}`
                                        ),
                                        wp.element.createElement(
                                            'p',
                                            { style: { margin: '4px 0', color: '#50575e' } },
                                            `Blocked domains: ${formatPolicyDomainList(researchPolicyDetail?.blocked_domains)}`
                                        )
                                    ),
                              wp.element.createElement('h4', { style: { margin: '12px 0 8px' } }, 'Edit Policy'),
                              wp.element.createElement(RangeControl, {
                                  label: 'Recency Window (months)',
                                  value: Number(policyDraft?.recency_months ?? DEFAULT_RESEARCH_POLICY.recency_months),
                                  onChange: (value) => {
                                      setPolicyDirty(true);
                                      setPolicyDraft((prev) => ({
                                          ...prev,
                                          recency_months: Number(value || DEFAULT_RESEARCH_POLICY.recency_months),
                                      }));
                                  },
                                  min: 1,
                                  max: 60,
                                  step: 1,
                              }),
                              wp.element.createElement('p', { style: { margin: '6px 0', fontWeight: '500' } }, 'Source Mix Minimums'),
                              wp.element.createElement(RangeControl, {
                                  label: 'Academic',
                                  value: Number(policyDraft?.source_mix_minimums?.academic ?? 0),
                                  onChange: (value) => {
                                      setPolicyDirty(true);
                                      setPolicyDraft((prev) => ({
                                          ...prev,
                                          source_mix_minimums: {
                                              ...prev.source_mix_minimums,
                                              academic: Number(value || 0),
                                          },
                                      }));
                                  },
                                  min: 0,
                                  max: 3,
                                  step: 1,
                              }),
                              wp.element.createElement(RangeControl, {
                                  label: 'Analyst',
                                  value: Number(policyDraft?.source_mix_minimums?.analyst ?? 0),
                                  onChange: (value) => {
                                      setPolicyDirty(true);
                                      setPolicyDraft((prev) => ({
                                          ...prev,
                                          source_mix_minimums: {
                                              ...prev.source_mix_minimums,
                                              analyst: Number(value || 0),
                                          },
                                      }));
                                  },
                                  min: 0,
                                  max: 3,
                                  step: 1,
                              }),
                              wp.element.createElement(RangeControl, {
                                  label: 'Industry',
                                  value: Number(policyDraft?.source_mix_minimums?.industry ?? 0),
                                  onChange: (value) => {
                                      setPolicyDirty(true);
                                      setPolicyDraft((prev) => ({
                                          ...prev,
                                          source_mix_minimums: {
                                              ...prev.source_mix_minimums,
                                              industry: Number(value || 0),
                                          },
                                      }));
                                  },
                                  min: 0,
                                  max: 3,
                                  step: 1,
                              }),
                              wp.element.createElement(RangeControl, {
                                  label: 'Case Study',
                                  value: Number(policyDraft?.source_mix_minimums?.case_study ?? 0),
                                  onChange: (value) => {
                                      setPolicyDirty(true);
                                      setPolicyDraft((prev) => ({
                                          ...prev,
                                          source_mix_minimums: {
                                              ...prev.source_mix_minimums,
                                              case_study: Number(value || 0),
                                          },
                                      }));
                                  },
                                  min: 0,
                                  max: 3,
                                  step: 1,
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Blocked Domains',
                                  value: policyDraft?.blocked_domains || [],
                                  onChange: (value) => {
                                      setPolicyDirty(true);
                                      setPolicyDraft((prev) => ({ ...prev, blocked_domains: value }));
                                  },
                                  placeholder: 'Add domains like quora.com',
                              }),
                              wp.element.createElement(
                                  'div',
                                  { style: { marginTop: '8px', display: 'flex', gap: '8px' } },
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isPrimary: true,
                                          onClick: handleSavePolicy,
                                          disabled: policySaving || !policyDirty,
                                      },
                                      policySaving ? wp.element.createElement(Spinner, null) : 'Save Policy'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: handleResetPolicyDraft,
                                          disabled: policySaving,
                                      },
                                      'Reset'
                                  )
                              ),
                              wp.element.createElement(
                                  'p',
                                  { style: { margin: '6px 0 0', fontSize: '12px', color: '#50575e' } },
                                  'Saving policy updates enforcement without re-running planner.'
                              ),
                              wp.element.createElement(
                                  'p',
                                  { style: { margin: '8px 0 4px', fontWeight: '500' } },
                                  `Validation Issues: ${researchValidationDetail?.summary?.error_count ?? 0} errors, ${researchValidationDetail?.summary?.warning_count ?? 0} warnings`
                              ),
                              (researchValidationDetail?.issues || []).length > 0 &&
                                  wp.element.createElement(
                                      'ul',
                                      { style: { margin: '0', paddingLeft: '18px' } },
                                      (researchValidationDetail.issues || []).slice(0, 5).map((issue, index) =>
                                          wp.element.createElement(
                                              'li',
                                              { key: `${issue.code || 'issue'}-${index}`, style: { marginBottom: '4px' } },
                                              `${(issue.severity || 'warning').toUpperCase()}: ${issue.message || issue.code || 'Validation issue'}`
                                          )
                                      )
                                  )
                          ),
                          wp.element.createElement(
                              'div',
                              {
                                  style: {
                                      marginBottom: '12px',
                                      padding: '12px',
                                      border: '1px solid #dcdcde',
                                      borderRadius: '6px',
                                      background: '#fff',
                                  },
                              },
                              wp.element.createElement('strong', null, 'Effective Author Policy'),
                              authorPolicyLoading
                                  ? wp.element.createElement('p', { style: { margin: '8px 0 0' } }, 'Loading author policy…')
                                  : wp.element.createElement(
                                        wp.element.Fragment,
                                        null,
                                        wp.element.createElement(
                                            'p',
                                            { style: { margin: '8px 0 4px', color: '#50575e' } },
                                            `Word range: ${authorPolicyDetail?.min_words ?? DEFAULT_AUTHOR_POLICY.min_words}-${authorPolicyDetail?.max_words ?? DEFAULT_AUTHOR_POLICY.max_words} · Reporter voice: ${(authorPolicyDetail?.reporter_voice_required ?? true) ? 'required' : 'optional'} · First-person: ${(authorPolicyDetail?.disallow_first_person ?? true) ? 'disallowed' : 'allowed'}`
                                        ),
                                        wp.element.createElement(
                                            'p',
                                            { style: { margin: '4px 0', color: '#50575e' } },
                                            `Em dash: ${(authorPolicyDetail?.disallow_em_dash ?? true) ? 'disallowed' : 'allowed'} · Rhetorical binaries: ${(authorPolicyDetail?.disallow_rhetorical_binaries ?? true) ? 'disallowed' : 'allowed'} · Listicle framing: ${(authorPolicyDetail?.disallow_listicle_framing ?? true) ? 'disallowed' : 'allowed'} · Tidy conclusions: ${(authorPolicyDetail?.disallow_tidy_conclusion ?? true) ? 'disallowed' : 'allowed'}`
                                        )
                                    ),
                              wp.element.createElement('h4', { style: { margin: '12px 0 8px' } }, 'Edit Author Policy'),
                              wp.element.createElement(ToggleControl, {
                                  label: 'Require reporter voice',
                                  checked: Boolean(authorPolicyDraft?.reporter_voice_required),
                                  onChange: (value) => {
                                      setAuthorPolicyDirty(true);
                                      setAuthorPolicyDraft((prev) => ({ ...prev, reporter_voice_required: Boolean(value) }));
                                  },
                              }),
                              wp.element.createElement(ToggleControl, {
                                  label: 'Disallow first-person pronouns',
                                  checked: Boolean(authorPolicyDraft?.disallow_first_person),
                                  onChange: (value) => {
                                      setAuthorPolicyDirty(true);
                                      setAuthorPolicyDraft((prev) => ({ ...prev, disallow_first_person: Boolean(value) }));
                                  },
                              }),
                              wp.element.createElement(ToggleControl, {
                                  label: 'Disallow em dashes',
                                  checked: Boolean(authorPolicyDraft?.disallow_em_dash),
                                  onChange: (value) => {
                                      setAuthorPolicyDirty(true);
                                      setAuthorPolicyDraft((prev) => ({ ...prev, disallow_em_dash: Boolean(value) }));
                                  },
                              }),
                              wp.element.createElement(ToggleControl, {
                                  label: 'Disallow rhetorical binaries',
                                  checked: Boolean(authorPolicyDraft?.disallow_rhetorical_binaries),
                                  onChange: (value) => {
                                      setAuthorPolicyDirty(true);
                                      setAuthorPolicyDraft((prev) => ({ ...prev, disallow_rhetorical_binaries: Boolean(value) }));
                                  },
                              }),
                              wp.element.createElement(ToggleControl, {
                                  label: 'Disallow listicle framing',
                                  checked: Boolean(authorPolicyDraft?.disallow_listicle_framing),
                                  onChange: (value) => {
                                      setAuthorPolicyDirty(true);
                                      setAuthorPolicyDraft((prev) => ({ ...prev, disallow_listicle_framing: Boolean(value) }));
                                  },
                              }),
                              wp.element.createElement(ToggleControl, {
                                  label: 'Disallow tidy conclusions',
                                  checked: Boolean(authorPolicyDraft?.disallow_tidy_conclusion),
                                  onChange: (value) => {
                                      setAuthorPolicyDirty(true);
                                      setAuthorPolicyDraft((prev) => ({ ...prev, disallow_tidy_conclusion: Boolean(value) }));
                                  },
                              }),
                              wp.element.createElement(TextControl, {
                                  label: 'Minimum words',
                                  type: 'number',
                                  min: 300,
                                  value: Number(authorPolicyDraft?.min_words ?? DEFAULT_AUTHOR_POLICY.min_words),
                                  onChange: (value) => {
                                      setAuthorPolicyDirty(true);
                                      setAuthorPolicyDraft((prev) => ({ ...prev, min_words: Number(value || DEFAULT_AUTHOR_POLICY.min_words) }));
                                  },
                              }),
                              wp.element.createElement(TextControl, {
                                  label: 'Maximum words',
                                  type: 'number',
                                  min: 300,
                                  value: Number(authorPolicyDraft?.max_words ?? DEFAULT_AUTHOR_POLICY.max_words),
                                  onChange: (value) => {
                                      setAuthorPolicyDirty(true);
                                      setAuthorPolicyDraft((prev) => ({ ...prev, max_words: Number(value || DEFAULT_AUTHOR_POLICY.max_words) }));
                                  },
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Banned phrases',
                                  value: authorPolicyDraft?.banned_phrases || [],
                                  onChange: (value) => {
                                      setAuthorPolicyDirty(true);
                                      setAuthorPolicyDraft((prev) => ({ ...prev, banned_phrases: value }));
                                  },
                                  placeholder: 'Add banned words/phrases',
                              }),
                              wp.element.createElement(
                                  'div',
                                  { style: { marginTop: '8px', display: 'flex', gap: '8px' } },
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isPrimary: true,
                                          onClick: handleSaveAuthorPolicy,
                                          disabled: authorPolicySaving || !authorPolicyDirty,
                                      },
                                      authorPolicySaving ? wp.element.createElement(Spinner, null) : 'Save Author Policy'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: handleResetAuthorPolicyDraft,
                                          disabled: authorPolicySaving,
                                      },
                                      'Reset'
                                  )
                              ),
                              wp.element.createElement(
                                  'p',
                                  { style: { margin: '6px 0 0', fontSize: '12px', color: '#50575e' } },
                                  'Saving author policy updates enforcement without re-running planner.'
                              ),
                              wp.element.createElement(
                                  'p',
                                  { style: { margin: '8px 0 4px', fontWeight: '500' } },
                                  `Author Validation: ${authorValidationSummary.error_count} errors, ${authorValidationSummary.warning_count} warnings across ${authorValidationSummary.drafts_with_output} drafts${authorValidationSummary.drafts_failed > 0 ? ` · ${authorValidationSummary.drafts_failed} failed runs` : ''}`
                              ),
                              authorValidationSummary.issues.length > 0 &&
                                  wp.element.createElement(
                                      'ul',
                                      { style: { margin: '0', paddingLeft: '18px' } },
                                      authorValidationSummary.issues.slice(0, 5).map((issue, index) =>
                                          wp.element.createElement(
                                              'li',
                                              { key: `author-issue-${index}`, style: { marginBottom: '4px' } },
                                              `${issue.severity.toUpperCase()}: ${issue.message}`
                                          )
                                      )
                                  ),
                              authorValidationSummary.issues.length === 0 &&
                                  wp.element.createElement(
                                      'p',
                                      { style: { margin: '4px 0', color: '#50575e' } },
                                      'No author validation issues captured yet.'
                                  )
                              )
                          ),
                          wp.element.createElement(
                              'div',
                              { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                              wp.element.createElement(
                                  'div',
                                  null,
                                  wp.element.createElement('h2', null, 'Phase Summaries')
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
                                                                        renderArticlesTable(),
                                                                        renderFrameworks()
                                )
                              : null
            ),
        detailModalOpen &&
            showThinkingIndicator &&
            wp.element.createElement(
                'div',
                {
                    style: {
                        position: 'fixed',
                        inset: 0,
                        zIndex: 100000,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: 'rgba(17, 24, 39, 0.28)',
                        backdropFilter: 'blur(6px)',
                        WebkitBackdropFilter: 'blur(6px)',
                    },
                },
                wp.element.createElement(
                    'div',
                    {
                        style: {
                            minWidth: '320px',
                            maxWidth: '560px',
                            padding: '24px 28px',
                            borderRadius: '14px',
                            background: '#ffffff',
                            boxShadow: '0 24px 60px rgba(15, 23, 42, 0.25)',
                            textAlign: 'center',
                        },
                    },
                    wp.element.createElement('div', { style: { fontSize: '30px', marginBottom: '8px' } }, '✨'),
                    wp.element.createElement('h3', { style: { margin: '0 0 8px' } }, activePhaseLabel || 'Working'),
                    wp.element.createElement(
                        'p',
                        { style: { margin: '0 0 16px', color: '#50575e', fontSize: '16px', fontStyle: 'italic' } },
                        THINKING_PHRASES[thinkingPhraseIndex] + '...'
                    ),
                    wp.element.createElement(
                        'div',
                        { style: { display: 'flex', justifyContent: 'center', marginBottom: '12px' } },
                        wp.element.createElement(Spinner, null)
                    ),
                    wp.element.createElement(
                        'p',
                        { style: { margin: 0, fontSize: '13px', color: '#6b7280' } },
                        'Please wait while the planner updates this phase.'
                    )
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
                            wp.element.createElement('div', { style: { fontSize: '28px', marginBottom: '10px' } }, '✨'),
                            wp.element.createElement(Spinner, null),
                            wp.element.createElement(
                                'p',
                                { style: { marginTop: '16px', fontSize: '18px', color: '#444', fontStyle: 'italic' } },
                                `${THINKING_PHRASES[thinkingPhraseIndex]}...`
                            ),
                            wp.element.createElement(
                                'p',
                                { style: { marginTop: '10px', fontSize: '14px', color: '#666' } },
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
                    { style: { marginBottom: '12px', display: 'flex', gap: '8px', flexWrap: 'wrap' } },
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
                wp.element.createElement(
                    'div',
                    {
                        style: {
                            marginBottom: '12px',
                            padding: '10px 12px',
                            border: '1px solid #dcdcde',
                            borderRadius: '6px',
                            background: '#fff',
                        },
                    },
                    wp.element.createElement('strong', null, authorPreview.title || 'Draft'),
                    wp.element.createElement(
                        'p',
                        { style: { margin: '6px 0 0', color: '#50575e' } },
                        `Profile: ${getAuthorProfileLabel(getSelectedAuthorProfile(authorPreview))} · Recommended: ${getAuthorProfileLabel(getRecommendedAuthorProfile(authorPreview))}`
                    )
                ),
                wp.element.createElement(
                    'div',
                    {
                        style: {
                            whiteSpace: 'pre-wrap',
                            maxHeight: '60vh',
                            overflowY: 'auto',
                            padding: '12px',
                            border: '1px solid #dcdcde',
                            borderRadius: '6px',
                            background: '#fff',
                        },
                    },
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
