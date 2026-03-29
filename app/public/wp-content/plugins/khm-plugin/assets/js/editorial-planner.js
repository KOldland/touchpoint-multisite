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

const MIN_CITATIONS_REQUIRED = 4;
const IDEAL_CITATIONS_TARGET = 6;
const LOW_CITATION_QUEUE_BATCH_SIZE = 6;
const SYNOPSIS_BATCH_SIZE = 2;
const PHASE_ORDER = ['phase1', 'phase2', 'phase3', 'phase4'];
const DIVE_DEEPER_STALL_SECONDS = 90;
const DIVE_DEEPER_STAGE_META = {
    queued: { label: 'Queued', progress: 20, detail: 'Waiting for the queue worker to pick up the job.' },
    running: { label: 'Running', progress: 55, detail: 'Analyzing sources and collecting evidence.' },
    processing: { label: 'Processing', progress: 85, detail: 'Applying citations to this article.' },
    completed: { label: 'Complete', progress: 100, detail: 'Source-check finished successfully.' },
    failed: { label: 'Failed', progress: 100, detail: 'Source-check failed. You can retry.' },
    starting: { label: 'Starting', progress: 10, detail: 'Preparing the source-check request.' },
};

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
    const [searchProviderStatus, setSearchProviderStatus] = useState(null);
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
    const queueStatusRef = useRef({});
    const [phase4RerunLoading, setPhase4RerunLoading] = useState(false);
    const [phase3RerunLoading, setPhase3RerunLoading] = useState(false);
    const [phase2RerunLoading, setPhase2RerunLoading] = useState(false);
    const [phase1RerunLoading, setPhase1RerunLoading] = useState(false);
    const [articleActionLoading, setArticleActionLoading] = useState({});
    const [imageActionLoading, setImageActionLoading] = useState({});
    const [generatedImageByArticle, setGeneratedImageByArticle] = useState({});
    const [expandedPhases, setExpandedPhases] = useState({});
    const [focusLevel, setFocusLevel] = useState(50);
    const [synopsisModalOpen, setSynopsisModalOpen] = useState(false);
    const [synopsisPlan, setSynopsisPlan] = useState({});
    const [synopsisPlanLoading, setSynopsisPlanLoading] = useState(false);
    const [synopsisPlanError, setSynopsisPlanError] = useState('');
    const [synopsisGenerateLoading, setSynopsisGenerateLoading] = useState(false);
    const [synopsisTotal, setSynopsisTotal] = useState(20);
    const [synopsisCompactSuggestion, setSynopsisCompactSuggestion] = useState(null);
    const [citationSegmentFilter, setCitationSegmentFilter] = useState('all');
    const [lowCitationBatchLoading, setLowCitationBatchLoading] = useState(false);
    const [focusDirty, setFocusDirty] = useState(false);
    const [thinkingPhraseIndex, setThinkingPhraseIndex] = useState(0);
    const [diveDeeperModalOpen, setDiveDeeperModalOpen] = useState(false);
    const [diveDeeperArticle, setDiveDeeperArticle] = useState(null);
    const [diveDeeperDepthSlider, setDiveDeeperDepthSlider] = useState(3);
    const [diveDeeperSuccess, setDiveDeeperSuccess] = useState(false);
    const [diveDeeperJobId, setDiveDeeperJobId] = useState('');
    const [diveDeeperJobStatus, setDiveDeeperJobStatus] = useState('');
    const [diveDeeperJobError, setDiveDeeperJobError] = useState('');
    const [diveDeeperElapsedSeconds, setDiveDeeperElapsedSeconds] = useState(0);
    const [queueModalOpen, setQueueModalOpen] = useState(false);
    const [queueLoading, setQueueLoading] = useState(false);
    const [queueClearing, setQueueClearing] = useState(false);
    const [queueRemoving, setQueueRemoving] = useState(false);
    const [queueError, setQueueError] = useState('');
    const [queueCounts, setQueueCounts] = useState({ queued: 0, running: 0, completed: 0, failed: 0 });
    const [queueItems, setQueueItems] = useState([]);
    const [queueActionLoading, setQueueActionLoading] = useState({});
    const [diveDeeperQueueLoading, setDiveDeeperQueueLoading] = useState(false);
    const [selectedQueueItems, setSelectedQueueItems] = useState([]);
    const [draggedQueueItemId, setDraggedQueueItemId] = useState('');
    const [queueReorderLoading, setQueueReorderLoading] = useState(false);
    const [queueStatusFilter, setQueueStatusFilter] = useState('all');
    const [queueTaskTypeFilter, setQueueTaskTypeFilter] = useState('all');
    const isDeepDiveLoading = diveDeeperArticle ? !!articleActionLoading[`dive_deeper:${diveDeeperArticle.id}`] : false;
    const isDiveDeeperJobRunning = ['queued', 'running', 'processing'].includes(diveDeeperJobStatus);
    const isDiveDeeperWorking = isDeepDiveLoading || isDiveDeeperJobRunning;
    const diveDeeperStageKey = diveDeeperJobStatus || (isDeepDiveLoading ? 'starting' : 'queued');
    const diveDeeperStageMeta = DIVE_DEEPER_STAGE_META[diveDeeperStageKey] || DIVE_DEEPER_STAGE_META.queued;
    const isDiveDeeperStalled =
        diveDeeperStageKey === 'queued' &&
        isDiveDeeperJobRunning &&
        diveDeeperElapsedSeconds >= DIVE_DEEPER_STALL_SECONDS;
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

    const navigateToNewSession = () => {
        const url = new URL(window.location.href);
        url.searchParams.delete('session_id');
        url.searchParams.delete('session');
        url.searchParams.set('page', 'editorial_new_session');
        window.location.href = `${url.pathname}${url.search}`;
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
            const cacheBuster = new Date().getTime();
            const data = await apiFetch({
                path: `dual-gpt/v1/sessions/${sessionId}?_t=${cacheBuster}`,
                method: 'GET',
            });
            handleAuthorStatusTransitions(data);
            setSessionDetail(data);

            try {
                const validationData = await apiFetch({
                    path: `dual-gpt/v1/planner/research-validation?session_id=${sessionId}&_t=${cacheBuster}`,
                    method: 'GET',
                });
                setResearchPolicyDetail(validationData?.research_policy || data?.meta?.research_policy || null);
                setResearchValidationDetail(validationData?.research_validation || null);
                setSearchProviderStatus(validationData?.search_provider_status || null);
            } catch (validationError) {
                console.error('Failed to load research validation detail:', validationError);
                setResearchPolicyDetail(data?.meta?.research_policy || null);
                setResearchValidationDetail(null);
                setSearchProviderStatus(null);
            }

            try {
                const authorPolicyResponse = await apiFetch({
                    path: `dual-gpt/v1/planner/author-policy?session_id=${sessionId}&_t=${cacheBuster}`,
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

            return data;
        } catch (error) {
            console.error('Failed to load session detail:', error);
            if (!silent) {
                setDetailError(error.message || 'Failed to load session detail.');
            }
            setResearchPolicyDetail(null);
            setResearchValidationDetail(null);
            setSearchProviderStatus(null);
            setAuthorPolicyDetail(null);
            return null;
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
            return null;
        }
        return openSessionDetail(sessionDetail.id, { silent: true });
    };

    const loadPlannerQueue = async ({ silent = false } = {}) => {
        try {
            if (!silent) {
                setQueueLoading(true);
            }
            setQueueError('');
            // Add cache-busting timestamp to force fresh data
            const cacheBuster = new Date().getTime();
            const response = await apiFetch({
                path: `dual-gpt/v1/planner/queue?_t=${cacheBuster}`,
                method: 'GET',
            });
            console.log('[QUEUE] Loaded queue:', response);
            setQueueCounts(response?.counts || { queued: 0, running: 0, completed: 0, failed: 0 });
            const nextItems = Array.isArray(response?.active_items) ? response.active_items : [];
            const previousStatuses = queueStatusRef.current || {};
            const nextStatuses = {};
            nextItems.forEach((item) => {
                if (!item?.id) {
                    return;
                }
                const currentStatus = item.status || 'queued';
                nextStatuses[item.id] = currentStatus;
                const previousStatus = previousStatuses[item.id];
                if (!previousStatus || previousStatus === currentStatus) {
                    return;
                }
                console.log(`[QUEUE] Status changed: ${item.id} ${previousStatus} → ${currentStatus}`);
                if (currentStatus === 'running') {
                    dispatch('core/notices').createNotice('info', `${taskTypeLabel(item.task_type)} is processing.`, { type: 'snackbar' });
                } else if (currentStatus === 'dispatched') {
                    dispatch('core/notices').createNotice('info', `${taskTypeLabel(item.task_type)} dispatched.`, { type: 'snackbar' });
                } else if (currentStatus === 'completed') {
                    dispatch('core/notices').createNotice('success', `${taskTypeLabel(item.task_type)} ready.`, { type: 'snackbar' });
                } else if (currentStatus === 'failed') {
                    dispatch('core/notices').createNotice('error', item.error_message || `${taskTypeLabel(item.task_type)} failed.`, { type: 'snackbar' });
                }
            });
            queueStatusRef.current = nextStatuses;
            setQueueItems(nextItems);
        } catch (error) {
            console.error('[QUEUE] Failed to load planner queue:', error);
            setQueueError(error?.message || 'Failed to load queue status.');
        } finally {
            if (!silent) {
                setQueueLoading(false);
            }
        }
    };

    const queueStatusLabel = (status) => {
        if (status === 'running') {
            return 'Processing';
        }
        if (status === 'completed') {
            return 'Ready';
        }
        if (status === 'dispatched') {
            return 'Dispatched';
        }
        if (status === 'failed') {
            return 'Failed';
        }
        return 'Queued';
    };

    const taskTypeLabel = (taskType) => {
        if (taskType === 'dive_deeper') {
            return 'Deeper Dives';
        }
        if (taskType === 'framework_generation') {
            return 'Framework Generation';
        }
        if (taskType === 'article_creation') {
            return 'Article Creation';
        }
        return taskType || 'Task';
    };

    const scheduleQueueFollowUpSync = () => {
        setTimeout(() => {
            loadPlannerQueue();
            refreshSessionDetail();
        }, 4000);
        setTimeout(() => {
            loadPlannerQueue();
            refreshSessionDetail();
        }, 10000);
    };

    const enqueuePlannerTask = async ({
        taskType,
        articleId = '',
        payload = null,
        successMessage = 'Added to queue.',
        silentSuccess = false,
    }) => {
        if (!sessionDetail?.id) {
            return null;
        }
        const loadingKey = `enqueue:${taskType}:${articleId || 'session'}`;
        try {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
            const response = await apiFetch({
                path: 'dual-gpt/v1/planner/queue/add',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    article_id: articleId || undefined,
                    task_type: taskType,
                    ...(payload ? { payload } : {}),
                },
            });
            if (!silentSuccess && successMessage) {
                dispatch('core/notices').createNotice('success', successMessage, { type: 'snackbar' });
            }
            await loadPlannerQueue();
            return response;
        } catch (error) {
            console.error('Failed to enqueue planner task:', error);
            dispatch('core/notices').createNotice('error', error?.message || 'Failed to add task to queue.', {
                type: 'snackbar',
            });
            return null;
        } finally {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
        }
    };

    const runPlannerQueueItem = async (queueId, options = {}) => {
        if (!queueId) {
            return;
        }
        const { retryFailed = false, retryPayload = {} } = options;
        const loadingKey = `run:${queueId}`;
        try {
            console.log('[QUEUE] Running item:', queueId);
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
            const response = await apiFetch({
                path: 'dual-gpt/v1/planner/queue/run',
                method: 'POST',
                data: {
                    queue_id: queueId,
                    ...(retryFailed ? { retry_failed: true } : {}),
                    ...(retryFailed && retryPayload && Object.keys(retryPayload).length
                        ? { retry_payload: retryPayload }
                        : {}),
                },
            });
            console.log('[QUEUE] Run response:', response);
            dispatch('core/notices').createNotice('success', response?.job_id ? `Queued job ${response.job_id} started.` : 'Queued task started.', {
                type: 'snackbar',
            });
            await loadPlannerQueue();
            await refreshSessionDetail();
            scheduleQueueFollowUpSync();
        } catch (error) {
            console.error('[QUEUE] Failed to run queued task:', error);
            dispatch('core/notices').createNotice('error', error?.message || 'Failed to run queued task.', {
                type: 'snackbar',
            });
            await loadPlannerQueue();
        } finally {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
        }
    };

    const runPlannerQueueBulk = async ({ queueIds = null, runAllQueued = false } = {}) => {
        if (!sessionDetail?.id) {
            return;
        }
        const loadingKey = runAllQueued ? 'run:all' : 'run:selected';
        try {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
            const response = await apiFetch({
                path: 'dual-gpt/v1/planner/queue/run-bulk',
                method: 'POST',
                data: runAllQueued
                    ? { run_all_queued: true, session_id: sessionDetail.id }
                    : { queue_ids: queueIds || [] },
            });
            const startedCount = Number(response?.started || 0);
            const failedCount = Number(response?.failed || 0);
            if (startedCount === 0) {
                dispatch('core/notices').createNotice(
                    'warning',
                    'No queued items were started. Items may already be dispatched/completed or filtered out.',
                    { type: 'snackbar' }
                );
            } else {
                dispatch('core/notices').createNotice(
                    'success',
                    `Started ${startedCount} queued item(s).${failedCount ? ` ${failedCount} failed.` : ''}`,
                    { type: 'snackbar' }
                );
            }
            setSelectedQueueItems([]);
            await loadPlannerQueue();
            await refreshSessionDetail();
            scheduleQueueFollowUpSync();
        } catch (error) {
            console.error('Failed to bulk run queued tasks:', error);
            dispatch('core/notices').createNotice('error', error?.message || 'Failed to start queued tasks.', {
                type: 'snackbar',
            });
            await loadPlannerQueue();
        } finally {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
        }
    };

    const toggleQueueItemSelection = (queueId, checked) => {
        setSelectedQueueItems((prev) => {
            if (checked) {
                return prev.includes(queueId) ? prev : [...prev, queueId];
            }
            return prev.filter((item) => item !== queueId);
        });
    };

    const reorderPlannerQueue = async (orderedIds) => {
        if (!sessionDetail?.id || !Array.isArray(orderedIds) || !orderedIds.length) {
            return;
        }
        try {
            setQueueReorderLoading(true);
            await apiFetch({
                path: 'dual-gpt/v1/planner/queue/reorder',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    ordered_ids: orderedIds,
                },
            });
            await loadPlannerQueue();
        } catch (error) {
            console.error('Failed to reorder planner queue:', error);
            dispatch('core/notices').createNotice('error', error?.message || 'Failed to reorder queue.', {
                type: 'snackbar',
            });
            await loadPlannerQueue();
        } finally {
            setQueueReorderLoading(false);
        }
    };

    const removePlannerQueueItem = async (queueId) => {
        if (!queueId) {
            return;
        }
        const loadingKey = `remove:${queueId}`;
        try {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
            await apiFetch({
                path: 'dual-gpt/v1/planner/queue/remove',
                method: 'POST',
                data: { queue_id: queueId },
            });
            setSelectedQueueItems((prev) => prev.filter((id) => id !== queueId));
            await loadPlannerQueue();
        } catch (error) {
            console.error('Failed to remove queue item:', error);
            dispatch('core/notices').createNotice('error', error?.message || 'Failed to remove queue item.', {
                type: 'snackbar',
            });
        } finally {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
        }
    };

    const stopPlannerQueueItem = async (queueId) => {
        if (!queueId) {
            return;
        }
        const loadingKey = `stop:${queueId}`;
        try {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
            await apiFetch({
                path: 'dual-gpt/v1/planner/queue/stop',
                method: 'POST',
                data: {
                    queue_id: queueId,
                    reason: 'Stopped by operator (manual cancel).',
                },
            });
            dispatch('core/notices').createNotice('warning', 'Queue item stopped.', { type: 'snackbar' });
            await loadPlannerQueue();
            await refreshSessionDetail();
        } catch (error) {
            console.error('Failed to stop queue item:', error);
            dispatch('core/notices').createNotice('error', error?.message || 'Failed to stop queue item.', {
                type: 'snackbar',
            });
        } finally {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
        }
    };

    const handleQueuePreview = (item) => {
        const articles = sessionDetail?.meta?.articles || [];
        const article = articles.find((entry) => entry?.id === item?.article_id);
        if (!article) {
            dispatch('core/notices').createNotice('warning', 'No article data available to preview for this queue item.', {
                type: 'snackbar',
            });
            return;
        }

        if (item.task_type === 'framework_generation') {
            setFrameworkPreview(article);
            return;
        }
        if (item.task_type === 'article_creation') {
            if (article?.author?.output) {
                setAuthorPreview(article);
                return;
            }
            setPreviewArticle(article);
            return;
        }
        setPreviewArticle(article);
    };

    const rerunPlannerQueueItem = async (item) => {
        if (!sessionDetail?.id || !item?.task_type) {
            return;
        }
        const loadingKey = `rerun:${item.id}`;
        if (item.status === 'failed') {
            const retryPayload =
                item.task_type === 'article_creation' && /prompt exceeds maximum length|prompt too long/i.test(item.error_message || '')
                    ? { retry_compact_prompt: true }
                    : {};
            try {
                setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
                await runPlannerQueueItem(item.id, { retryFailed: true, retryPayload });
            } finally {
                setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
            }
            return;
        }
        try {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
            const addResponse = await apiFetch({
                path: 'dual-gpt/v1/planner/queue/add',
                method: 'POST',
                data: {
                    session_id: sessionDetail.id,
                    article_id: item.article_id || undefined,
                    task_type: item.task_type,
                    ...(item.payload ? { payload: item.payload } : {}),
                },
            });

            const newQueueId = addResponse?.queue_id;
            if (!newQueueId) {
                throw new Error('Could not create rerun queue item.');
            }

            await apiFetch({
                path: 'dual-gpt/v1/planner/queue/run',
                method: 'POST',
                data: { queue_id: newQueueId },
            });

            dispatch('core/notices').createNotice('success', `${taskTypeLabel(item.task_type)} re-run started.`, {
                type: 'snackbar',
            });
            await loadPlannerQueue();
        } catch (error) {
            console.error('Failed to re-run queue item:', error);
            dispatch('core/notices').createNotice('error', error?.message || 'Failed to re-run queue item.', {
                type: 'snackbar',
            });
        } finally {
            setQueueActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
        }
    };

    const handleQueueRowDrop = async (targetQueueId) => {
        if (!draggedQueueItemId || !targetQueueId || draggedQueueItemId === targetQueueId) {
            setDraggedQueueItemId('');
            return;
        }

        const currentIds = queueItems.map((item) => item.id);
        const fromIndex = currentIds.indexOf(draggedQueueItemId);
        const toIndex = currentIds.indexOf(targetQueueId);
        if (fromIndex < 0 || toIndex < 0) {
            setDraggedQueueItemId('');
            return;
        }

        const reordered = [...queueItems];
        const [moved] = reordered.splice(fromIndex, 1);
        reordered.splice(toIndex, 0, moved);
        const withPositions = reordered.map((item, index) => ({ ...item, position: index + 1 }));
        setQueueItems(withPositions);
        setDraggedQueueItemId('');
        await reorderPlannerQueue(withPositions.map((item) => item.id));
    };

    const parseQueueDate = (value) => {
        if (!value) {
            return 0;
        }
        const unix = Date.parse(String(value).replace(' ', 'T'));
        return Number.isFinite(unix) ? unix : 0;
    };

    const formatQueueElapsed = (value) => {
        const startedAt = parseQueueDate(value);
        if (!startedAt) {
            return '';
        }
        const elapsedSeconds = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
        if (elapsedSeconds < 60) {
            return `${elapsedSeconds}s elapsed`;
        }
        const minutes = Math.floor(elapsedSeconds / 60);
        const seconds = elapsedSeconds % 60;
        return `${minutes}m ${String(seconds).padStart(2, '0')}s elapsed`;
    };

    const getQueueProgressDetail = (item) => {
        const status = item?.status || 'queued';
        const elapsed = formatQueueElapsed(item?.updated_at || item?.created_at);
        if (status === 'dispatched') {
            return elapsed ? `Job sent to backend · ${elapsed}` : 'Job sent to backend';
        }
        if (status === 'running') {
            return elapsed ? `Backend processing · ${elapsed}` : 'Backend processing';
        }
        if (status === 'queued') {
            return elapsed ? `Waiting in queue · ${elapsed}` : 'Waiting in queue';
        }
        if (status === 'completed') {
            return item?.updated_at ? `Completed at ${item.updated_at}` : 'Completed';
        }
        if (status === 'failed') {
            return item?.updated_at ? `Failed at ${item.updated_at}` : 'Failed';
        }
        return elapsed;
    };

    const openQueueModal = async () => {
        setQueueModalOpen(true);
        await loadPlannerQueue();
    };

    const removeAllQueueItems = async () => {
        const confirmed = window.confirm('Remove all queue items? This permanently deletes all completed, failed, and queued entries. Running jobs will not be affected.');
        if (!confirmed) {
            return;
        }
        try {
            setQueueRemoving(true);
            const response = await apiFetch({
                path: 'dual-gpt/v1/planner/queue/remove-all',
                method: 'POST',
            });
            dispatch('core/notices').createNotice('success', response?.message || 'Queue items removed.', { type: 'snackbar' });
            await loadPlannerQueue();
        } catch (error) {
            console.error('Failed to remove all queue items:', error);
            dispatch('core/notices').createNotice('error', error?.message || 'Failed to remove queue items.', { type: 'snackbar' });
        } finally {
            setQueueRemoving(false);
        }
    };

    const clearQueuedJobs = async () => {
        const confirmed = window.confirm('Clear all queued jobs? Running jobs will not be affected.');
        if (!confirmed) {
            return;
        }
        try {
            setQueueClearing(true);
            const response = await apiFetch({
                path: 'dual-gpt/v1/planner/queue/clear',
                method: 'POST',
                data: { older_than_seconds: 0 },
            });
            dispatch('core/notices').createNotice('success', response?.message || 'Queued jobs cleared.', { type: 'snackbar' });
            await loadPlannerQueue();
        } catch (error) {
            console.error('Failed to clear queue:', error);
            dispatch('core/notices').createNotice('error', error?.message || 'Failed to clear queue.', { type: 'snackbar' });
        } finally {
            setQueueClearing(false);
        }
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
        const isDiveDeeperGenerating = diveDeeperModalOpen && isDiveDeeperWorking;
        if (!hasRunningPhase && !isSynopsisGenerating && !isDiveDeeperGenerating) {
            setThinkingPhraseIndex(0);
            return undefined;
        }
        const interval = setInterval(() => {
            setThinkingPhraseIndex((prev) => (prev + 1) % THINKING_PHRASES.length);
        }, 1500);
        return () => clearInterval(interval);
    }, [detailModalOpen, sessionDetail?.meta?.phases, synopsisGenerateLoading, diveDeeperModalOpen, isDiveDeeperWorking]);

    useEffect(() => {
        if (!diveDeeperModalOpen || !diveDeeperJobId || !diveDeeperArticle?.id) {
            return;
        }
        const articles = sessionDetail?.meta?.articles || [];
        const article = articles.find((item) => item?.id === diveDeeperArticle.id);
        if (!article || !Array.isArray(article.dive_deeper_jobs)) {
            return;
        }
        const job = [...article.dive_deeper_jobs].reverse().find((item) => item?.job_id === diveDeeperJobId);
        if (!job) {
            return;
        }
        const status = job.status || 'queued';
        setDiveDeeperJobStatus(status);
        setDiveDeeperJobError(job.error_message || '');
        if (status === 'completed') {
            setDiveDeeperSuccess(true);
        }
    }, [diveDeeperModalOpen, diveDeeperJobId, diveDeeperArticle?.id, sessionDetail?.meta?.articles]);

    useEffect(() => {
        if (!diveDeeperModalOpen || !diveDeeperJobId || !isDiveDeeperJobRunning) {
            return undefined;
        }
        let cancelled = false;
        const poll = async () => {
            if (cancelled) {
                return;
            }
            await refreshSessionDetail();
        };
        const interval = setInterval(poll, 3000);
        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [diveDeeperModalOpen, diveDeeperJobId, isDiveDeeperJobRunning]);

    useEffect(() => {
        if (!diveDeeperModalOpen || !isDiveDeeperWorking) {
            setDiveDeeperElapsedSeconds(0);
            return undefined;
        }
        setDiveDeeperElapsedSeconds(0);
        const startedAt = Date.now();
        const interval = setInterval(() => {
            setDiveDeeperElapsedSeconds(Math.floor((Date.now() - startedAt) / 1000));
        }, 1000);
        return () => clearInterval(interval);
    }, [diveDeeperModalOpen, isDiveDeeperWorking, diveDeeperJobId]);

    useEffect(() => {
        if (!detailModalOpen || !sessionDetail?.id) {
            return undefined;
        }
        loadPlannerQueue();
        return undefined;
    }, [detailModalOpen, sessionDetail?.id]);

    useEffect(() => {
        if (!detailModalOpen || !sessionDetail?.id) {
            return undefined;
        }

        const hasActiveQueueItems = queueItems.some((item) =>
            ['queued', 'running', 'dispatched'].includes(item?.status || '')
        );

        if (!hasActiveQueueItems) {
            return undefined;
        }

        let cancelled = false;
        const interval = setInterval(async () => {
            if (cancelled || queueReorderLoading) {
                return;
            }
            await loadPlannerQueue({ silent: true });
            await refreshSessionDetail();
        }, 4000);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [detailModalOpen, sessionDetail?.id, queueItems, queueReorderLoading]);

    useEffect(() => {
        if (!detailModalOpen || !sessionDetail?.id) {
            return undefined;
        }

        const articles = sessionDetail?.meta?.articles || [];
        const hasActiveDeepDiveJobs = articles.some((article) =>
            Array.isArray(article?.dive_deeper_jobs) &&
            article.dive_deeper_jobs.some((job) =>
                ['queued', 'running', 'processing', 'dispatched'].includes(job?.status || '')
            )
        );

        if (!hasActiveDeepDiveJobs) {
            return undefined;
        }

        let cancelled = false;
        const interval = setInterval(async () => {
            if (cancelled) {
                return;
            }
            await refreshSessionDetail();
        }, 5000);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [detailModalOpen, sessionDetail?.id, sessionDetail?.meta?.articles]);

    useEffect(() => {
        const validQueuedIds = new Set(queueItems.filter((item) => item.status === 'queued').map((item) => item.id));
        setSelectedQueueItems((prev) => prev.filter((id) => validQueuedIds.has(id)));
    }, [queueItems]);

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
        const citationsLength = Array.isArray(article?.citations) ? article.citations.length : 0;
        return Math.max(explicitCount, citationsLength);
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

    const handleQueueFrameworkGeneration = async (article) => {
        if (!article?.id || !sessionDetail?.id) {
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

        await enqueuePlannerTask({
            taskType: 'framework_generation',
            articleId: article.id,
            payload: { force: article?.framework?.status !== 'pending' },
            successMessage: 'Framework generation added to queue.',
        });
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
        setSynopsisCompactSuggestion(null);
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

    const suggestCompactSynopsisTotal = () => {
        const topicCount = Math.max(1, Object.keys(synopsisPlan || {}).length);
        const reducedByRatio = Math.max(topicCount, Math.floor((Number(synopsisTotal) || 1) * 0.6));
        if (reducedByRatio < synopsisTotal) {
            return reducedByRatio;
        }
        return Math.max(topicCount, Math.max(1, synopsisTotal - 1));
    };

    const applyCompactSynopsisPlan = async () => {
        if (!sessionDetail?.id) {
            return;
        }
        const compactTotal = synopsisCompactSuggestion?.suggestedTotal || suggestCompactSynopsisTotal();
        try {
            setSynopsisPlanLoading(true);
            setSynopsisPlanError('');
            const data = await apiFetch({
                path: 'dual-gpt/v1/planner/synopsis-plan',
                method: 'POST',
                data: { session_id: sessionDetail.id, total: compactTotal },
            });
            setSynopsisTotal(compactTotal);
            setSynopsisPlan(data.plan || {});
            setSynopsisCompactSuggestion(null);
            dispatch('core/notices').createNotice(
                'success',
                `Compact plan applied (target ${compactTotal}).`,
                { type: 'snackbar' }
            );
        } catch (error) {
            console.error('Failed to apply compact synopsis plan:', error);
            setSynopsisPlanError(error.message || 'Failed to apply compact plan.');
        } finally {
            setSynopsisPlanLoading(false);
        }
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
            setSynopsisPlanError('');
            const baselineArticlesCount = Array.isArray(sessionDetail?.meta?.articles)
                ? sessionDetail.meta.articles.length
                : 0;
            let effectivePlan = synopsisPlan;

            if (synopsisPlanTotal !== synopsisTotal) {
                const recalculated = await apiFetch({
                    path: 'dual-gpt/v1/planner/synopsis-plan',
                    method: 'POST',
                    data: { session_id: sessionDetail.id, total: synopsisTotal },
                });
                effectivePlan = recalculated?.plan || synopsisPlan;
                setSynopsisPlan(effectivePlan);
            }

            console.log('[Planner] Generate synopses click', {
                sessionId: sessionDetail.id,
                plan: effectivePlan,
                baselineArticlesCount,
            });
            setSynopsisGenerateLoading(true);
            dispatch('core/notices').createNotice(
                'info',
                'Generating synopses...',
                { type: 'snackbar' }
            );
            const synopsisResponse = await apiFetch({
                path: 'dual-gpt/v1/planner/synopses',
                method: 'POST',
                data: { session_id: sessionDetail.id, plan: effectivePlan, batch_size: SYNOPSIS_BATCH_SIZE },
            });
            const synopsisJobIds = Array.isArray(synopsisResponse?.job_ids) ? synopsisResponse.job_ids : [];
            setSynopsisCompactSuggestion(null);

            dispatch('core/notices').createNotice(
                'success',
                `Article synopses queued (${synopsisResponse?.batch_count || 0} batch${(synopsisResponse?.batch_count || 0) === 1 ? '' : 'es'}). Refreshing to display results...`,
                { type: 'snackbar' }
            );
            if (!synopsisJobIds.length) {
                dispatch('core/notices').createNotice(
                    'warning',
                    'Synopses request returned without job IDs. Falling back to session polling.',
                    { type: 'snackbar' }
                );
            }

            // Keep the synopsis modal open with loading state while generating.
            // Use an interval poller (instead of recursive timeouts) to avoid silent callback drops.
            let pollCount = 0;
            const maxPolls = 120; // 120 * 2.5s = 5 minutes max
            const intervalId = window.setInterval(async () => {
                pollCount++;
                let jobsStatus = null;

                if (synopsisJobIds.length) {
                    try {
                        jobsStatus = await apiFetch({
                            path: 'dual-gpt/v1/planner/jobs-status',
                            method: 'POST',
                            data: { session_id: sessionDetail.id, job_ids: synopsisJobIds },
                        });
                    } catch (statusError) {
                        console.warn('[Planner] Failed to check synopsis job status:', statusError);
                    }
                }

                const refreshed = await refreshSessionDetail();
                const articlesCount = refreshed?.meta?.articles?.length || 0;
                const createdNewSynopses = articlesCount > baselineArticlesCount;
                const allJobsComplete = synopsisJobIds.length
                    ? Boolean(jobsStatus?.all_complete)
                    : false;
                const failedCount = Number(jobsStatus?.failed_count || 0);
                const timedOut = pollCount >= maxPolls;
                const shouldFinish = createdNewSynopses || allJobsComplete || timedOut;

                if (!shouldFinish) {
                    return;
                }

                window.clearInterval(intervalId);
                setSynopsisGenerateLoading(false);
                setSynopsisModalOpen(false);

                if (createdNewSynopses) {
                    const createdCount = Math.max(0, articlesCount - baselineArticlesCount);
                    dispatch('core/notices').createNotice(
                        'success',
                        `${createdCount} article synopses generated successfully.`,
                        { type: 'snackbar' }
                    );
                    return;
                }

                if (allJobsComplete && failedCount > 0) {
                    const firstFailure = Array.isArray(jobsStatus?.jobs)
                        ? jobsStatus.jobs.find((job) => job.status === 'failed')
                        : null;
                    dispatch('core/notices').createNotice(
                        'error',
                        firstFailure?.error_message || 'Synopsis generation failed. Please review job errors and retry.',
                        { type: 'snackbar' }
                    );
                    return;
                }

                if (allJobsComplete) {
                    dispatch('core/notices').createNotice(
                        'success',
                        'Synopsis jobs completed. Refreshing article list now.',
                        { type: 'snackbar' }
                    );
                    return;
                }

                dispatch('core/notices').createNotice(
                    'warning',
                    'Synopses were queued, but no new synopsis records were detected yet. Check queue status and retry in a moment.',
                    { type: 'snackbar' }
                );
            }, 2500);
        } catch (error) {
            console.error('Synopsis generation failed:', error);
            const errorCode = String(error?.code || '');
            const errorMessage = String(error?.message || '');
            const isPromptTooLong = errorCode === 'prompt_too_long' || /prompt too long/i.test(errorMessage);
            if (isPromptTooLong) {
                const suggestedTotal = suggestCompactSynopsisTotal();
                setSynopsisCompactSuggestion({ suggestedTotal });
                setSynopsisPlanError(
                    `Prompt too long for current synopsis volume. Try compact mode (suggested target: ${suggestedTotal}).`
                );
                dispatch('core/notices').createNotice(
                    'warning',
                    `Prompt too long. Use Compact Plan (target ${suggestedTotal}) and retry.`,
                    { type: 'snackbar' }
                );
                setSynopsisGenerateLoading(false);
                return;
            }
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

    const handleQueueAuthorAgent = async (article) => {
        if (!article?.id || !sessionDetail?.id) {
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
        await enqueuePlannerTask({
            taskType: 'article_creation',
            articleId: article.id,
            payload: { author_profile: selectedProfile },
            successMessage: 'Article creation added to queue.',
        });
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

    const buildArticleImagePayload = (article, overrides = {}) => {
        const title = article?.title || article?.headline || 'Article Image';
        const summary = article?.summary || article?.brief || '';
        const keywords = Array.isArray(article?.keywords)
            ? article.keywords
            : Array.isArray(article?.tags)
              ? article.tags
              : [];
        const postId = parseInt(article?.author?.post_id || 0, 10) || 0;

        return {
            post_id: postId,
            title,
            summary,
            keywords,
            alt_text: `${title} illustration`,
            caption: `${title}`,
            editorial_accuracy: true,
            store_in_media_library: true,
            set_featured_image: postId > 0,
            ...overrides,
        };
    };

    const handleRecommendImageArticle = async (article) => {
        if (!article?.id) {
            return;
        }
        const loadingKey = `recommend:${article.id}`;
        try {
            setImageActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
            const response = await apiFetch({
                path: 'dual-gpt/v1/images/recommend',
                method: 'POST',
                data: buildArticleImagePayload(article),
            });

            const promptPreview = (response?.prompt || '').trim();
            const message = promptPreview
                ? `Image recommendation ready: ${promptPreview.slice(0, 120)}${promptPreview.length > 120 ? '...' : ''}`
                : 'Image recommendation generated.';
            dispatch('core/notices').createNotice('success', message, { type: 'snackbar' });
        } catch (error) {
            dispatch('core/notices').createNotice(
                'error',
                error?.message || 'Failed to recommend image.',
                { type: 'snackbar' }
            );
        } finally {
            setImageActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
        }
    };

    const handleGenerateImageArticle = async (article) => {
        if (!article?.id) {
            return;
        }
        const loadingKey = `generate:${article.id}`;
        try {
            setImageActionLoading((prev) => ({ ...prev, [loadingKey]: true }));
            const response = await apiFetch({
                path: 'dual-gpt/v1/images/generate',
                method: 'POST',
                data: buildArticleImagePayload(article),
            });

            const firstAttachment = Array.isArray(response?.attachments) ? response.attachments[0] : null;
            if (firstAttachment?.url) {
                setGeneratedImageByArticle((prev) => ({
                    ...prev,
                    [article.id]: {
                        url: firstAttachment.url,
                        attachmentId: firstAttachment.attachment_id || 0,
                    },
                }));
            }

            const attachmentCount = Array.isArray(response?.attachments) ? response.attachments.length : 0;
            const message = attachmentCount > 0
                ? `Image generated and stored (${attachmentCount} attachment${attachmentCount === 1 ? '' : 's'}).`
                : 'Image generation completed.';
            dispatch('core/notices').createNotice('success', message, { type: 'snackbar' });
        } catch (error) {
            dispatch('core/notices').createNotice(
                'error',
                error?.message || 'Failed to generate image.',
                { type: 'snackbar' }
            );
        } finally {
            setImageActionLoading((prev) => ({ ...prev, [loadingKey]: false }));
        }
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
            
            console.log(`[Planner] Starting article action: ${action} for article ${article.id}`, payload);
            
            // Create a timeout promise that rejects after 12 seconds
            const timeoutPromise = new Promise((_, reject) =>
                setTimeout(() => reject(new Error('Request timeout after 12 seconds')), 12000)
            );
            
            const fetchPromise = apiFetch({
                path: 'dual-gpt/v1/planner/article-action',
                method: 'POST',
                data: payload,
            });
            
            let response;
            try {
                // Race between fetch and timeout
                response = await Promise.race([fetchPromise, timeoutPromise]);
                console.log(`[Planner] Article action succeeded:`, response);
            } catch (raceError) {
                // If timeout, refresh to get the job that was likely queued on backend
                if (raceError.message.includes('timeout')) {
                    console.warn(`[Planner] Article action timed out (${action}), refreshing to pick up job...`);
                    dispatch('core/notices').createNotice('success', successMessage, { type: 'snackbar' });
                    // Refresh immediately to fetch the newly queued job
                    await refreshSessionDetail();
                    return { queued_async: true };
                }
                throw raceError;
            }

            const responseJobId = response && typeof response === 'object' ? response.job_id : '';
            const liteFrameworkGenerated = !!(response && typeof response === 'object' && response.lite_framework_generated);
            const noticeMessage =
                action === 'opinion_piece' && responseJobId
                    ? liteFrameworkGenerated
                        ? `Lite framework prepared. Opinion piece queued (Job ${responseJobId}).`
                        : `Opinion piece queued (Job ${responseJobId}).`
                    : successMessage;
            
            dispatch('core/notices').createNotice('success', noticeMessage, { type: 'snackbar' });
            await refreshSessionDetail();
            return response;
        } catch (error) {
            console.error(`[Planner] Article action failed (${action}):`, error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Article action failed.',
                { type: 'snackbar' }
            );
            return null;
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
        setDiveDeeperDepthSlider(3);
        setDiveDeeperSuccess(false);
        setDiveDeeperJobId('');
        setDiveDeeperJobStatus('');
        setDiveDeeperJobError('');
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
        const result = await runArticleAction(
            diveDeeperArticle,
            'dive_deeper',
            'Source-check queued. Progress will appear in this window until it completes.',
            params
        );
        setDiveDeeperSuccess(false);
        setDiveDeeperJobError('');
        
        // Try to get job_id from response first
        let queuedJobId = result?.job_id || '';
        let queuedJobStatus = result?.status || '';
        
        const refreshedDetail = await refreshSessionDetail();

        const refreshedArticles = refreshedDetail?.meta?.articles || [];
        const refreshedArticle = refreshedArticles.find((a) => a?.id === diveDeeperArticle.id);
        if (refreshedArticle?.dive_deeper_jobs && refreshedArticle.dive_deeper_jobs.length > 0) {
            const jobsNewestFirst = [...refreshedArticle.dive_deeper_jobs].reverse();
            const matchedJob = queuedJobId
                ? jobsNewestFirst.find((item) => item?.job_id === queuedJobId)
                : jobsNewestFirst.find((item) => !!item?.job_id);

            if (matchedJob?.job_id) {
                queuedJobId = matchedJob.job_id;
                queuedJobStatus = matchedJob.status || queuedJobStatus;
                console.log(`[Planner] Bound dive_deeper job from refreshed session: ${queuedJobId} (${queuedJobStatus || 'unknown'})`);
            }
        }

        setDiveDeeperJobId(queuedJobId);
        setDiveDeeperJobStatus(queuedJobId ? (queuedJobStatus || 'queued') : '');
    };

    const handleDiveDeeperQueueSubmit = async () => {
        if (!diveDeeperArticle?.id || !sessionDetail?.id) {
            return;
        }
        try {
            setDiveDeeperQueueLoading(true);
            const payload = mapSliderToDepthParams(diveDeeperDepthSlider);
            await enqueuePlannerTask({
                taskType: 'dive_deeper',
                articleId: diveDeeperArticle.id,
                payload,
                successMessage: 'Dive deeper task added to queue.',
            });
            setDiveDeeperModalOpen(false);
            setDiveDeeperSuccess(false);
        } finally {
            setDiveDeeperQueueLoading(false);
        }
    };

    const handleDiveDeeperRetry = async () => {
        if (!diveDeeperArticle || isDeepDiveLoading) {
            return;
        }
        await handleDiveDeeperSubmit();
    };

    const closeDiveDeeperModal = () => {
        setDiveDeeperModalOpen(false);
        setDiveDeeperSuccess(false);
        setDiveDeeperJobId('');
        setDiveDeeperJobStatus('');
        setDiveDeeperJobError('');
        setDiveDeeperElapsedSeconds(0);
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

        const providerErrors = Array.isArray(searchProviderStatus?.provider_errors)
            ? searchProviderStatus.provider_errors
            : [];
        const hasProviderErrors = Boolean(searchProviderStatus?.has_errors) || providerErrors.length > 0;
        const serpapiIssue = providerErrors.find((item) => String(item).toLowerCase().includes('serpapi:')) || '';
        const providerAdminInstruction = searchProviderStatus?.admin_instruction
            || (serpapiIssue
                ? 'SerpAPI is failing (quota or credential issue). Please contact your System Administrator to restore SerpAPI access and verify fallback provider support before rerunning Research Phase 4.'
                : 'Search provider is failing. Please contact your System Administrator to restore provider access before rerunning Research Phase 4.');

        const phaseCards = phaseOrder.map((key) => {
            const phase = phases[key];
            if (!phase) {
                return null;
            }
            const citationsCount = Array.isArray(phase.citations) ? phase.citations.length : 0;
            const phaseSummary = phase.summary || phase.payload?.summary || phase.payload?.article_summary || '';
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
            const placeholderSourcePattern = /^Relevant Result\s+\d+\s+for:/i;

            const canonicalizeUrl = (value) => {
                if (!value) {
                    return '';
                }
                try {
                    const parsed = new URL(value);
                    parsed.hash = '';
                    parsed.search = '';
                    const normalized = parsed.toString().replace(/\/+$/, '');
                    return normalized.toLowerCase();
                } catch (error) {
                    return String(value).trim().toLowerCase();
                }
            };

            const isPlaceholderSource = (title, url) => {
                const titleText = String(title || '');
                const urlText = String(url || '');
                return placeholderSourcePattern.test(titleText) || urlText.includes('example.com/result');
            };

            const pushLink = (title, url) => {
                if (!url || isPlaceholderSource(title, url)) {
                    return;
                }
                const dedupeKey = canonicalizeUrl(url);
                if (!dedupeKey || seenUrls.has(dedupeKey)) {
                    return;
                }
                seenUrls.add(dedupeKey);
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
            const phase2Payload =
                key === 'phase2'
                    ? (sessionDetail?.meta?.phase2 && typeof sessionDetail.meta.phase2 === 'object'
                        ? sessionDetail.meta.phase2
                        : (sessionDetail?.meta?.phases?.phase2?.payload || {}))
                    : null;

            if (key === 'phase2' && Array.isArray(phase2Payload?.keyword_metrics) && phase2Payload.keyword_metrics.length) {
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
                                phase2Payload.keyword_metrics.slice(0, 10).map((item, idx) =>
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
            if (key === 'phase2' && Array.isArray(phase2Payload?.ranked_keywords) && phase2Payload.ranked_keywords.length) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'ranked-keywords-phase2', style: { marginTop: '10px' } },
                        wp.element.createElement('strong', null, 'Priority Ranking'),
                        renderList(
                            phase2Payload.ranked_keywords.slice(0, 10).map((item) => {
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
                        phase2Payload.ranked_keywords.slice(0, 6).map((item, idx) =>
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
            const rawSources = phase.payload?.sources || phase.citations || [];
            const sourceTitles = rawSources
                .filter((source) => !isPlaceholderSource(source?.title, source?.url))
                .map((source) => source.title || source.url || 'Source');

            if (sourceTitles.length) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        { key: 'sources', style: { marginTop: '8px' } },
                        wp.element.createElement('strong', null, 'Sources'),
                        renderList(sourceTitles)
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
            if (key === 'phase4' && hasProviderErrors && phase.status === 'completed') {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        {
                            key: 'search-incomplete-notice',
                            style: {
                                marginTop: '10px',
                                padding: '10px',
                                border: '1px solid #946200',
                                borderRadius: '4px',
                                background: '#fffbe6',
                            },
                        },
                        wp.element.createElement('strong', null, 'Note: Search was incomplete'),
                        wp.element.createElement(
                            'p',
                            { style: { margin: '6px 0 0' } },
                            'Live web search was unavailable during this validation run. Results are based on model-inferred validation using prior research context and may not reflect the most current sources. Re-run Phase 4 once search access is restored for live-sourced citations.'
                        )
                    )
                );
            }
            if (key === 'phase4' && hasProviderErrors) {
                detailBlocks.push(
                    wp.element.createElement(
                        'div',
                        {
                            key: 'provider-admin-instruction',
                            style: {
                                marginTop: '10px',
                                padding: '10px',
                                border: '1px solid #d63638',
                                borderRadius: '4px',
                                background: '#fff5f5',
                            },
                        },
                        wp.element.createElement('strong', null, 'Source Provider Issue'),
                        wp.element.createElement('p', { style: { margin: '6px 0 0' } }, providerAdminInstruction),
                        serpapiIssue
                            ? wp.element.createElement(
                                  'p',
                                  { style: { margin: '6px 0 0', fontSize: '12px', color: '#7a1f1f' } },
                                  `Detected provider error: ${serpapiIssue}`
                              )
                            : null
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
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: () =>
                                setExpandedPhases((prev) => ({ ...prev, [key]: !prev[key] })),
                            style: { marginTop: '2px' },
                        },
                        isExpanded ? 'Hide details' : 'View details'
                    ),
                    isExpanded &&
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '12px' } },
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
                            ...detailBlocks
                        ),
                    isExpanded && phase.payload?.next_step_question &&
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
                    isExpanded && key === 'phase2' &&
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

        const phaseKeysPresent = phaseOrder.filter((key) => !!phases[key]);
        return wp.element.createElement(
            'div',
            null,
            phaseKeysPresent.length > 0 &&
                wp.element.createElement(
                    'div',
                    { style: { marginBottom: '8px', display: 'flex', gap: '8px' } },
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: () => {
                                const next = {};
                                phaseKeysPresent.forEach((key) => {
                                    next[key] = true;
                                });
                                setExpandedPhases(next);
                            },
                        },
                        'Expand all phases'
                    ),
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: () => setExpandedPhases({}),
                        },
                        'Collapse all phases'
                    )
                ),
            ...phaseCards
        );
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
            const prioritySignal = Number(metric?.priority_score ?? 0);
            const rankValue = Number(metric?.rank ?? 0);
            const rankingSignal = Number.isFinite(prioritySignal) && prioritySignal > 0
                ? prioritySignal
                : Number.isFinite(rankValue) && rankValue > 0
                ? 1 / rankValue
                : 0;
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

        const classifyCitationBand = (count) => {
            if (count < 2) {
                return 'critical';
            }
            if (count < MIN_CITATIONS_REQUIRED) {
                return 'below_minimum';
            }
            if (count < IDEAL_CITATIONS_TARGET) {
                return 'ready';
            }
            return 'ideal';
        };

        const criticalCitationCount = rows.filter((item) => item.citationsCount < 2).length;
        const belowMinimumCitationCount = rows.filter(
            (item) => item.citationsCount < MIN_CITATIONS_REQUIRED
        ).length;
        const idealCitationCount = rows.filter(
            (item) => item.citationsCount >= IDEAL_CITATIONS_TARGET
        ).length;

        const maxVolume = Math.max(1, ...rows.map((item) => item.volumeValue || 0));
        const maxRankingSignal = Math.max(1, ...rows.map((item) => item.rankingSignal || 0));
        const maxCitations = Math.max(1, ...rows.map((item) => item.citationsCount || 0));
        const scoredRows = rows
            .map((item) => {
                const hasMarketData = !!item.metric;
                const volumeSignal = maxVolume > 1
                    ? Math.log1p(item.volumeValue) / Math.log1p(maxVolume)
                    : item.volumeValue > 0
                    ? 1
                    : 0;
                const rankingSignalNorm = maxRankingSignal > 0 ? item.rankingSignal / maxRankingSignal : 0;
                const marketSignalRaw = (volumeSignal * 0.7) + (rankingSignalNorm * 0.3);
                const marketSignal = hasMarketData
                    ? Math.round(Math.max(5, Math.min(95, marketSignalRaw * 100)))
                    : null;
                const priorityScore =
                    ((item.volumeValue / maxVolume) * 0.45) +
                    ((item.citationsCount / maxCitations) * 0.35) +
                    ((item.rankingSignal / maxRankingSignal) * 0.2);
                const citationBand = classifyCitationBand(item.citationsCount);
                const deprioritizedScore = citationBand === 'critical'
                    ? priorityScore * 0.5
                    : citationBand === 'below_minimum'
                    ? priorityScore * 0.75
                    : priorityScore;
                return { ...item, priorityScore: deprioritizedScore, marketSignal, citationBand };
            })
            .sort((a, b) => b.priorityScore - a.priorityScore);

        const filteredRows = citationSegmentFilter === 'all'
            ? scoredRows
            : scoredRows.filter((item) => item.citationBand === citationSegmentFilter);

        const lowCitationRows = scoredRows.filter(
            (item) => item.citationsCount < MIN_CITATIONS_REQUIRED && item?.article?.id
        );
        const lowCitationEligibleRows = lowCitationRows.filter((item) => {
            const jobs = Array.isArray(item?.article?.dive_deeper_jobs)
                ? item.article.dive_deeper_jobs
                : [];
            const hasActiveDiveDeeper = jobs.some((job) =>
                ['queued', 'running', 'processing'].includes(String(job?.status || '').toLowerCase())
            );
            const hasCompletedDiveDeeper = jobs.some(
                (job) => String(job?.status || '').toLowerCase() === 'completed'
            );
            const hasCitationLift = Number(item?.article?.deep_dive?.citations_added || 0) > 0;
            return !hasActiveDiveDeeper && !hasCompletedDiveDeeper && !hasCitationLift;
        });
        const lowCitationBatchRows = [...lowCitationEligibleRows]
            .sort((a, b) => {
                if (a.citationsCount !== b.citationsCount) {
                    return a.citationsCount - b.citationsCount;
                }
                return b.priorityScore - a.priorityScore;
            })
            .slice(0, LOW_CITATION_QUEUE_BATCH_SIZE);

        const handleQueueLowCitationDeepDive = async () => {
            if (!lowCitationRows.length) {
                dispatch('core/notices').createNotice('info', 'No low-citation articles need processing.', {
                    type: 'snackbar',
                });
                return;
            }

            if (!lowCitationEligibleRows.length) {
                dispatch('core/notices').createNotice(
                    'info',
                    'Low-citation articles are already being processed or were already deep-dived.',
                    { type: 'snackbar' }
                );
                return;
            }

            const deepDivePayload = mapSliderToDepthParams(3);
            let queued = 0;
            setLowCitationBatchLoading(true);
            try {
                for (const row of lowCitationBatchRows) {
                    const response = await enqueuePlannerTask({
                        taskType: 'dive_deeper',
                        articleId: row.article.id,
                        payload: deepDivePayload,
                        silentSuccess: true,
                    });
                    if (response) {
                        queued += 1;
                    }
                }

                dispatch('core/notices').createNotice(
                    queued > 0 ? 'success' : 'warning',
                    queued > 0
                        ? `Queued deep-dive for ${queued} low-citation article${queued === 1 ? '' : 's'} (target ${deepDivePayload.target_min_citations} citations). ${Math.max(0, lowCitationRows.length - lowCitationBatchRows.length)} remaining for future batches.`
                        : 'No low-citation articles were queued. Check queue status and try again.',
                    { type: 'snackbar' }
                );
                await refreshSessionDetail();
            } finally {
                setLowCitationBatchLoading(false);
            }
        };

        return wp.element.createElement(
            'div',
            null,
            wp.element.createElement(
                'p',
                { style: { margin: '8px 0', fontSize: '12px', color: '#50575e' } },
                'Priority score weights: 45% Market Signal, 35% Citation Strength, 20% Keyword Ranking. Author profile recommendation is inferred from article intent and framework language.'
            ),
            wp.element.createElement(
                'p',
                {
                    style: {
                        margin: '0 0 8px',
                        fontSize: '12px',
                        color: belowMinimumCitationCount > 0 ? '#8a5a00' : '#1e5f3a',
                    },
                },
                `Citation coverage: ${criticalCitationCount} critical (<2), ${belowMinimumCitationCount} below minimum (<${MIN_CITATIONS_REQUIRED}), ${idealCitationCount} ideal (${IDEAL_CITATIONS_TARGET}+)`
            ),
            wp.element.createElement(
                'div',
                {
                    style: {
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        flexWrap: 'wrap',
                        marginBottom: '8px',
                    },
                },
                wp.element.createElement(SelectControl, {
                    label: 'Citation Segment',
                    value: citationSegmentFilter,
                    options: [
                        { label: `All (${scoredRows.length})`, value: 'all' },
                        { label: `Critical <2 (${criticalCitationCount})`, value: 'critical' },
                        {
                            label: `Below minimum 2-${MIN_CITATIONS_REQUIRED - 1} (${Math.max(0, belowMinimumCitationCount - criticalCitationCount)})`,
                            value: 'below_minimum',
                        },
                        {
                            label: `Ready ${MIN_CITATIONS_REQUIRED}-${IDEAL_CITATIONS_TARGET - 1} (${Math.max(0, scoredRows.length - belowMinimumCitationCount - idealCitationCount)})`,
                            value: 'ready',
                        },
                        { label: `Ideal ${IDEAL_CITATIONS_TARGET}+ (${idealCitationCount})`, value: 'ideal' },
                    ],
                    onChange: setCitationSegmentFilter,
                }),
                wp.element.createElement(
                    Button,
                    {
                        isSecondary: true,
                        onClick: handleQueueLowCitationDeepDive,
                        disabled: lowCitationBatchLoading || lowCitationEligibleRows.length === 0,
                        style: { marginTop: '22px' },
                    },
                    lowCitationBatchLoading
                        ? wp.element.createElement(Spinner, null)
                        : `Queue Next Low-Citation Batch (${Math.min(lowCitationEligibleRows.length, LOW_CITATION_QUEUE_BATCH_SIZE)}/${lowCitationEligibleRows.length})`
                )
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
                filteredRows.map((row, priorityIndex) => {
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
                    const citationQuality = citationsCount < 2
                        ? {
                            label: 'Critical: under 2 citations',
                            bg: '#fde8e8',
                            border: '#d63638',
                            text: '#8a2424',
                        }
                        : citationsCount < MIN_CITATIONS_REQUIRED
                          ? {
                                label: `Below minimum: ${MIN_CITATIONS_REQUIRED} required`,
                                bg: '#fff4e5',
                                border: '#dba617',
                                text: '#8a5a00',
                            }
                          : citationsCount < IDEAL_CITATIONS_TARGET
                            ? {
                                  label: `Meets minimum. Target ${IDEAL_CITATIONS_TARGET}+`,
                                  bg: '#e7f5ff',
                                  border: '#4da3ff',
                                  text: '#0b4f8a',
                              }
                            : {
                                  label: `Ideal coverage (${IDEAL_CITATIONS_TARGET}+)`,
                                  bg: '#edfaef',
                                  border: '#4ab866',
                                  text: '#1f6f3c',
                              };
                    const opinionPieceWritten =
                        authorStatus === 'complete' && article.framework?.lite_mode === 'opinion';
                    const selectedProfile = getSelectedAuthorProfile(article);
                    const recommendedProfile = getRecommendedAuthorProfile(article);
                    const frameworkActionLabel =
                        frameworkStatus === 'complete' ? 'Regenerate Framework' : 'Generate Framework';
                    const isDismissLoading = !!articleActionLoading[`dismiss:${article.id}`];
                    const isDeepDiveLoading = !!articleActionLoading[`dive_deeper:${article.id}`];
                    const isOpinionLoading = !!articleActionLoading[`opinion_piece:${article.id}`];
                    const isRecommendImageLoading = !!imageActionLoading[`recommend:${article.id}`];
                    const isGenerateImageLoading = !!imageActionLoading[`generate:${article.id}`];
                    const generatedImage = generatedImageByArticle[article.id];
                    const isQueueFrameworkLoading = !!queueActionLoading[`enqueue:framework_generation:${article.id}`];
                    const isQueueArticleLoading = !!queueActionLoading[`enqueue:article_creation:${article.id}`];
                    const canImageActions = authorStatus === 'complete' && !!article.author?.output;
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
                            wp.element.createElement('strong', null, marketSignal == null ? '—' : `${marketSignal}%`),
                            wp.element.createElement(
                                'div',
                                { style: { fontSize: '11px', color: '#50575e', marginTop: '2px' } },
                                `Vol: ${volume}${metric?.priority_score != null ? ` · Priority: ${metric.priority_score}` : ''}`
                            )
                        ),
                        wp.element.createElement(
                            'td',
                            null,
                            wp.element.createElement('strong', null, citationsCount),
                            wp.element.createElement(
                                'div',
                                {
                                    style: {
                                        marginTop: '4px',
                                        display: 'inline-block',
                                        padding: '2px 6px',
                                        borderRadius: '999px',
                                        border: `1px solid ${citationQuality.border}`,
                                        background: citationQuality.bg,
                                        color: citationQuality.text,
                                        fontSize: '11px',
                                        fontWeight: 600,
                                        lineHeight: 1.3,
                                    },
                                },
                                citationQuality.label
                            )
                        ),
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
                            opinionPieceWritten &&
                                wp.element.createElement(
                                    Notice,
                                    { status: 'success', isDismissible: false },
                                    'Opinion piece written.'
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
                                    onClick: () => handleQueueAuthorAgent(article),
                                    style: { marginRight: '8px' },
                                    disabled: !meetsCitationThreshold || !frameworkReady || isQueueArticleLoading || authorStatus === 'running' || authorStatus === 'queued',
                                },
                                isQueueArticleLoading ? wp.element.createElement(Spinner, null) : 'Queue Article'
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
                                    onClick: () => handleRecommendImageArticle(article),
                                    style: { marginRight: '8px' },
                                    disabled: !canImageActions || isRecommendImageLoading,
                                },
                                isRecommendImageLoading ? wp.element.createElement(Spinner, null) : 'Recommend Image'
                            ),
                            wp.element.createElement(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: () => handleGenerateImageArticle(article),
                                    style: { marginRight: '8px' },
                                    disabled: !canImageActions || isGenerateImageLoading,
                                },
                                isGenerateImageLoading ? wp.element.createElement(Spinner, null) : 'Generate Image'
                            ),
                            wp.element.createElement(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: () => window.open(generatedImage?.url, '_blank'),
                                    style: { marginRight: '8px' },
                                    disabled: !generatedImage?.url,
                                },
                                'Open Image'
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
                                    isSecondary: true,
                                    onClick: () => handleQueueFrameworkGeneration(article),
                                    style: { marginRight: '8px' },
                                    disabled: !meetsCitationThreshold || isQueueFrameworkLoading,
                                },
                                isQueueFrameworkLoading ? wp.element.createElement(Spinner, null) : 'Queue Framework'
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

    const renderEditorQueueTable = () => {
        const articles = sessionDetail?.meta?.articles || [];
        const filteredQueueItems = queueItems.filter((item) => {
            const matchesStatus = queueStatusFilter === 'all' ? true : item.status === queueStatusFilter;
            const matchesTaskType = queueTaskTypeFilter === 'all' ? true : item.task_type === queueTaskTypeFilter;
            return matchesStatus && matchesTaskType;
        });
        const sortedQueueItems = [...filteredQueueItems].sort((a, b) => {
            const aQueued = (a?.status || '') === 'queued';
            const bQueued = (b?.status || '') === 'queued';

            if (queueStatusFilter === 'queued') {
                return Number(a?.position || 0) - Number(b?.position || 0);
            }
            if (queueStatusFilter !== 'all') {
                return parseQueueDate(b?.updated_at || b?.created_at) - parseQueueDate(a?.updated_at || a?.created_at);
            }

            if (aQueued && bQueued) {
                return Number(a?.position || 0) - Number(b?.position || 0);
            }
            if (aQueued) {
                return -1;
            }
            if (bQueued) {
                return 1;
            }

            return parseQueueDate(b?.updated_at || b?.created_at) - parseQueueDate(a?.updated_at || a?.created_at);
        });
        const queuedItems = queueItems.filter((item) => item.status === 'queued');
        const activeQueueItems = queueItems.filter((item) => ['queued', 'running', 'dispatched'].includes(item.status || ''));
        const hiddenActiveItems = activeQueueItems.filter((item) => !filteredQueueItems.some((filtered) => filtered.id === item.id));
        const tableEntries = [
            ...hiddenActiveItems.map((item) => ({ type: 'item', item, pinned: true })),
            ...(hiddenActiveItems.length
                ? [{ type: 'separator', id: 'filtered-results-separator' }]
                : []),
            ...sortedQueueItems.map((item) => ({ type: 'item', item, pinned: false })),
        ];
        const selectedQueuedItems = queuedItems.filter((item) => selectedQueueItems.includes(item.id));
        const articleTitleById = articles.reduce((acc, article) => {
            acc[article.id] = article.headline || article.title || article.id;
            return acc;
        }, {});

        const canRemoveQueueItem = (status) => !['running', 'dispatched'].includes(status || '');

        return wp.element.createElement(
            'div',
            { style: { marginTop: '16px' } },
            wp.element.createElement('h2', null, 'Editor Queue'),
            queueError && wp.element.createElement(Notice, { status: 'error', isDismissible: false }, queueError),
            wp.element.createElement(
                'p',
                { style: { margin: '8px 0', fontSize: '12px', color: '#50575e' } },
                `Queued: ${queueCounts?.queued || 0} · Running: ${queueCounts?.running || 0} · Completed: ${queueCounts?.completed || 0} · Failed: ${queueCounts?.failed || 0}`
            ),
            wp.element.createElement(
                'p',
                { style: { margin: '0 0 8px', fontSize: '12px', color: '#666' } },
                'Drag queued rows to reorder priority, then run selected items or run all queued items.'
            ),
            wp.element.createElement(
                'div',
                { style: { display: 'flex', gap: '8px', marginBottom: '10px' } },
                wp.element.createElement(SelectControl, {
                    label: 'Status filter',
                    value: queueStatusFilter,
                    options: [
                        { label: 'All statuses', value: 'all' },
                        { label: 'Queued', value: 'queued' },
                        { label: 'Running', value: 'running' },
                        { label: 'Dispatched', value: 'dispatched' },
                        { label: 'Completed', value: 'completed' },
                        { label: 'Failed', value: 'failed' },
                    ],
                    onChange: setQueueStatusFilter,
                }),
                wp.element.createElement(SelectControl, {
                    label: 'Category filter',
                    value: queueTaskTypeFilter,
                    options: [
                        { label: 'All categories', value: 'all' },
                        { label: 'Deeper Dives', value: 'dive_deeper' },
                        { label: 'Framework Generation', value: 'framework_generation' },
                        { label: 'Article Creation', value: 'article_creation' },
                    ],
                    onChange: setQueueTaskTypeFilter,
                }),
            ),
            wp.element.createElement(
                'div',
                { style: { display: 'flex', gap: '8px', marginBottom: '10px' } },
                wp.element.createElement(
                    Button,
                    { isSecondary: true, onClick: loadPlannerQueue, disabled: queueLoading || queueClearing },
                    queueLoading ? wp.element.createElement(Spinner, null) : 'Refresh Queue'
                ),
                wp.element.createElement(
                    Button,
                    {
                        isSecondary: true,
                        onClick: () => runPlannerQueueBulk({ queueIds: selectedQueuedItems.map((item) => item.id) }),
                        disabled: !selectedQueuedItems.length || !!queueActionLoading['run:selected'] || queueLoading || queueReorderLoading,
                    },
                    queueActionLoading['run:selected'] ? wp.element.createElement(Spinner, null) : `Run Selected (${selectedQueuedItems.length})`
                ),
                wp.element.createElement(
                    Button,
                    {
                        isPrimary: true,
                        onClick: () => runPlannerQueueBulk({ runAllQueued: true }),
                        disabled: !queuedItems.length || !!queueActionLoading['run:all'] || queueLoading || queueReorderLoading,
                    },
                    queueActionLoading['run:all'] ? wp.element.createElement(Spinner, null) : `Run All Queued (${queuedItems.length})`
                ),
                wp.element.createElement(
                    Button,
                    { isDestructive: true, onClick: clearQueuedJobs, disabled: queueLoading || queueClearing || queueRemoving },
                    queueClearing ? wp.element.createElement(Spinner, null) : 'Clear Queued'
                ),
                wp.element.createElement(
                    Button,
                    { isDestructive: true, onClick: removeAllQueueItems, disabled: queueLoading || queueClearing || queueRemoving },
                    queueRemoving ? wp.element.createElement(Spinner, null) : 'Remove All'
                )
            ),
            queueLoading &&
                wp.element.createElement(
                    'p',
                    { style: { margin: '6px 0', fontSize: '12px', color: '#666' } },
                    'Refreshing queue…'
                ),
            hiddenActiveItems.length > 0 &&
                wp.element.createElement(
                    Notice,
                    { status: 'info', isDismissible: false },
                    `${hiddenActiveItems.length} active queue item(s) are pinned above the filtered results so controls stay available in every view.`
                ),
            wp.element.createElement(
                      'table',
                      { className: 'widefat striped' },
                      wp.element.createElement(
                          'thead',
                          null,
                          wp.element.createElement(
                              'tr',
                              null,
                              wp.element.createElement('th', null, ''),
                              wp.element.createElement('th', null, ''),
                              wp.element.createElement('th', null, 'Order'),
                              wp.element.createElement('th', null, 'Task'),
                              wp.element.createElement('th', null, 'Article'),
                              wp.element.createElement('th', null, 'Status'),
                              wp.element.createElement('th', null, 'Created'),
                              wp.element.createElement('th', null, 'Action'),
                              wp.element.createElement('th', null, 'Remove')
                          )
                      ),
                      wp.element.createElement(
                          'tbody',
                          null,
                          tableEntries.length
                              ? tableEntries.map((entry) => {
                                    if (entry.type === 'separator') {
                                        return wp.element.createElement(
                                            'tr',
                                            { key: entry.id },
                                            wp.element.createElement(
                                                'td',
                                                {
                                                    colSpan: 9,
                                                    style: {
                                                        background: '#f6f7f7',
                                                        color: '#50575e',
                                                        fontSize: '12px',
                                                        fontWeight: 600,
                                                    },
                                                },
                                                'Filtered results'
                                            )
                                        );
                                    }

                                    const item = entry.item;
                                    const runKey = `run:${item.id}`;
                                    const removeKey = `remove:${item.id}`;
                                                                        const stopKey = `stop:${item.id}`;
                                    const isRunningAction = !!queueActionLoading[runKey];
                                    const isRemoveAction = !!queueActionLoading[removeKey];
                                                                        const isStopAction = !!queueActionLoading[stopKey];
                                    const rerunKey = `rerun:${item.id}`;
                                    const isRerunAction = !!queueActionLoading[rerunKey];
                                    const isQueued = item.status === 'queued';
                                    const isCompleted = item.status === 'completed';
                                    const isFailed = item.status === 'failed';
                                                                        const isStoppable = ['queued', 'running', 'dispatched'].includes(item.status || '');
                                    const isSelected = selectedQueueItems.includes(item.id);
                                    const articleTitle = articleTitleById[item.article_id] || item.article_id || '—';
                                    const failureReason = isFailed ? String(item.error_message || '').trim() : '';
                                    const progressDetail = getQueueProgressDetail(item);
                                    return wp.element.createElement(
                                        'tr',
                                        {
                                            key: item.id,
                                            draggable: isQueued && !queueReorderLoading,
                                            onDragStart: () => setDraggedQueueItemId(item.id),
                                            onDragOver: (event) => {
                                                if (!isQueued) {
                                                    return;
                                                }
                                                event.preventDefault();
                                            },
                                            onDrop: (event) => {
                                                event.preventDefault();
                                                handleQueueRowDrop(item.id);
                                            },
                                            style: {
                                                cursor: isQueued ? 'move' : 'default',
                                                opacity: draggedQueueItemId === item.id ? 0.6 : 1,
                                                background: entry.pinned ? '#fffbe6' : undefined,
                                            },
                                        },
                                        wp.element.createElement(
                                            'td',
                                            { style: { color: isQueued ? '#666' : '#bbb', width: '28px', textAlign: 'center' } },
                                            isQueued ? '⋮⋮' : '•'
                                        ),
                                        wp.element.createElement(
                                            'td',
                                            null,
                                            wp.element.createElement('input', {
                                                type: 'checkbox',
                                                checked: isSelected,
                                                disabled: !isQueued,
                                                onChange: (event) => toggleQueueItemSelection(item.id, !!event?.target?.checked),
                                            })
                                        ),
                                        wp.element.createElement('td', null, item.position || '—'),
                                        wp.element.createElement('td', null, taskTypeLabel(item.task_type)),
                                        wp.element.createElement('td', null, articleTitle),
                                        wp.element.createElement(
                                            'td',
                                            null,
                                            queueStatusLabel(item.status || 'queued'),
                                            progressDetail &&
                                                wp.element.createElement(
                                                    'div',
                                                    {
                                                        style: {
                                                            marginTop: '4px',
                                                            fontSize: '11px',
                                                            color: isFailed ? '#666' : '#666',
                                                            lineHeight: 1.35,
                                                        },
                                                    },
                                                    progressDetail
                                                ),
                                            failureReason &&
                                                wp.element.createElement(
                                                    'div',
                                                    {
                                                        style: {
                                                            marginTop: '4px',
                                                            fontSize: '11px',
                                                            color: '#a00',
                                                            maxWidth: '260px',
                                                            lineHeight: 1.35,
                                                        },
                                                        title: failureReason,
                                                    },
                                                    failureReason.length > 120 ? `${failureReason.slice(0, 120)}…` : failureReason
                                                )
                                        ),
                                        wp.element.createElement('td', null, item.created_at || '—'),
                                        wp.element.createElement(
                                            'td',
                                            null,
                                            wp.element.createElement(
                                                'div',
                                                { style: { display: 'flex', gap: '6px', flexWrap: 'wrap' } },
                                                wp.element.createElement(
                                                    Button,
                                                    {
                                                        isSecondary: true,
                                                        onClick: () => runPlannerQueueItem(item.id),
                                                        disabled: !isQueued || isRunningAction || queueReorderLoading,
                                                    },
                                                    isRunningAction ? wp.element.createElement(Spinner, null) : 'Run Now'
                                                ),
                                                isCompleted &&
                                                    wp.element.createElement(
                                                        Button,
                                                        {
                                                            isSecondary: true,
                                                            onClick: () => handleQueuePreview(item),
                                                        },
                                                        'Preview'
                                                    ),
                                                isCompleted &&
                                                    wp.element.createElement(
                                                        Button,
                                                        {
                                                            isSecondary: true,
                                                            onClick: () => rerunPlannerQueueItem(item),
                                                            disabled: isRerunAction || queueReorderLoading,
                                                        },
                                                        isRerunAction ? wp.element.createElement(Spinner, null) : 'Re-run'
                                                    ),
                                                isFailed &&
                                                    wp.element.createElement(
                                                        Button,
                                                        {
                                                            isSecondary: true,
                                                            onClick: () => rerunPlannerQueueItem(item),
                                                            disabled: isRerunAction || isRunningAction || queueReorderLoading,
                                                        },
                                                        isRerunAction ? wp.element.createElement(Spinner, null) : 'Retry'
                                                    ),
                                                isStoppable &&
                                                    wp.element.createElement(
                                                        Button,
                                                        {
                                                            isDestructive: true,
                                                            onClick: () => stopPlannerQueueItem(item.id),
                                                            disabled: isStopAction,
                                                        },
                                                        isStopAction ? wp.element.createElement(Spinner, null) : 'Stop'
                                                    )
                                            )
                                        ),
                                        wp.element.createElement(
                                            'td',
                                            null,
                                            wp.element.createElement(
                                                Button,
                                                {
                                                    isSecondary: true,
                                                    onClick: () => removePlannerQueueItem(item.id),
                                                    disabled: !canRemoveQueueItem(item.status) || isRemoveAction,
                                                },
                                                isRemoveAction ? wp.element.createElement(Spinner, null) : 'Remove'
                                            )
                                        )
                                    );
                                })
                              : wp.element.createElement(
                                    'tr',
                                    null,
                                    wp.element.createElement(
                                        'td',
                                        { colSpan: 9, style: { color: '#666' } },
                                        'No queue items match the current filters.'
                                    )
                                )
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
    const providerErrors = Array.isArray(searchProviderStatus?.provider_errors)
        ? searchProviderStatus.provider_errors
        : [];
    const hasProviderErrors = Boolean(searchProviderStatus?.has_errors) || providerErrors.length > 0;
    const serpapiIssue = providerErrors.find((item) => String(item).toLowerCase().includes('serpapi:')) || '';
    const providerAdminInstruction = searchProviderStatus?.admin_instruction
        || (serpapiIssue
            ? 'SerpAPI is failing (quota or credential issue). Please contact your System Administrator to restore SerpAPI access and verify fallback provider support before rerunning Research Phase 4.'
            : 'Search provider is failing. Please contact your System Administrator to restore provider access before rerunning Research Phase 4.');

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
                                  {
                                      isSecondary: true,
                                      onClick: openQueueModal,
                                      style: { marginRight: '8px' },
                                  },
                                  `Queue (${queueCounts?.queued || 0})`
                              ),
                              wp.element.createElement(
                                  Button,
                                  { isSecondary: true, onClick: refreshSessionDetail },
                                  'Refresh'
                              )
                          )
                      ),
                      renderPhaseSummaries(),
                      renderEditorQueueTable(),
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
                        onRequestClose: closeDiveDeeperModal,
                    },
                    diveDeeperSuccess
                        ? wp.element.createElement(
                              'div',
                              { style: { textAlign: 'center', padding: '32px 24px' } },
                              wp.element.createElement(
                                  'div',
                                  { style: { fontSize: '48px', lineHeight: 1, marginBottom: '12px' } },
                                  '✓'
                              ),
                              wp.element.createElement(
                                  'p',
                                  { style: { fontSize: '16px', fontWeight: '600', color: '#1e7e34', margin: '0 0 8px' } },
                                  'Source-check complete'
                              ),
                              wp.element.createElement(
                                  'p',
                                  { style: { fontSize: '13px', color: '#666', margin: 0 } },
                                  'Supporting evidence has been processed for this article. You can now review updated citations.'
                              )
                          )
                        : isDiveDeeperWorking
                        ? wp.element.createElement(
                              'div',
                              { style: { textAlign: 'center', padding: '32px 24px' } },
                              wp.element.createElement(Spinner, null),
                              wp.element.createElement(
                                  'p',
                                  { style: { marginTop: '16px', fontSize: '14px', color: '#666' } },
                                  `${diveDeeperStageMeta.label} · ${THINKING_PHRASES[thinkingPhraseIndex]}...`
                              ),
                              wp.element.createElement(
                                  'div',
                                  { style: { marginTop: '14px', marginBottom: '10px' } },
                                  wp.element.createElement(ProgressBar, { value: diveDeeperStageMeta.progress })
                              ),
                              wp.element.createElement(
                                  'p',
                                  { style: { marginTop: '8px', fontSize: '12px', color: '#666' } },
                                  diveDeeperStageMeta.detail
                              ),
                              wp.element.createElement(
                                  'p',
                                  { style: { marginTop: '6px', fontSize: '12px', color: '#999' } },
                                  `Status: ${diveDeeperStageKey}${diveDeeperElapsedSeconds > 0 ? ` · ${diveDeeperElapsedSeconds}s elapsed` : ''}`
                              ),
                              isDiveDeeperStalled &&
                                  wp.element.createElement(
                                      Notice,
                                      { status: 'warning', isDismissible: false },
                                      wp.element.createElement(
                                          'div',
                                          {
                                              style: {
                                                  display: 'flex',
                                                  alignItems: 'center',
                                                  justifyContent: 'space-between',
                                                  gap: '10px',
                                              },
                                          },
                                          wp.element.createElement(
                                              'span',
                                              null,
                                              'This job has been queued longer than expected. You can retry now.'
                                          ),
                                          wp.element.createElement(
                                              Button,
                                              {
                                                  isSecondary: true,
                                                  onClick: handleDiveDeeperRetry,
                                                  disabled: isDeepDiveLoading,
                                              },
                                              isDeepDiveLoading ? wp.element.createElement(Spinner, null) : 'Retry'
                                          )
                                      )
                                  )
                          )
                        : wp.element.createElement(
                              'div',
                              { style: { marginBottom: '24px' } },
                              diveDeeperJobError &&
                                  wp.element.createElement(
                                      Notice,
                                      { status: 'error', isDismissible: false },
                                      diveDeeperJobError
                                  ),
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
                                          onClick: closeDiveDeeperModal,
                                      },
                                      'Cancel'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isSecondary: true,
                                          onClick: handleDiveDeeperQueueSubmit,
                                          disabled: isDiveDeeperWorking || diveDeeperQueueLoading,
                                      },
                                      diveDeeperQueueLoading ? wp.element.createElement(Spinner, null) : 'Add to Queue'
                                  ),
                                  wp.element.createElement(
                                      Button,
                                      {
                                          isPrimary: true,
                                          onClick: handleDiveDeeperSubmit,
                                          disabled: isDiveDeeperWorking || diveDeeperQueueLoading,
                                      },
                                      isDiveDeeperWorking ? wp.element.createElement(Spinner, null) : 'Run Now'
                                  )
                              )
                          )
                ),
            queueModalOpen &&
                wp.element.createElement(
                    Modal,
                    {
                        title: 'Planner Queue',
                        onRequestClose: () => !queueClearing && setQueueModalOpen(false),
                    },
                    queueError && wp.element.createElement(Notice, { status: 'error', isDismissible: false }, queueError),
                    wp.element.createElement(
                        'p',
                        { style: { marginTop: 0, color: '#50575e' } },
                        `Queued: ${queueCounts?.queued || 0} · Running: ${queueCounts?.running || 0} · Failed: ${queueCounts?.failed || 0}`
                    ),
                    queueLoading
                        ? wp.element.createElement(Spinner, null)
                        : wp.element.createElement(
                              'div',
                              {
                                  style: {
                                      maxHeight: '320px',
                                      overflowY: 'auto',
                                      border: '1px solid #ddd',
                                      borderRadius: '4px',
                                      padding: '8px',
                                  },
                              },
                              queueItems.length
                                  ? queueItems.map((item) =>
                                        wp.element.createElement(
                                            'div',
                                            {
                                                key: item.id,
                                                style: {
                                                    padding: '8px 6px',
                                                    borderBottom: '1px solid #eee',
                                                    fontSize: '12px',
                                                },
                                            },
                                            wp.element.createElement(
                                                'div',
                                                { style: { fontWeight: 600 } },
                                                `${(item.task_type || 'task').replace(/_/g, ' ')} · ${item.status.toUpperCase()} · ${item.created_at}`
                                            ),
                                            wp.element.createElement(
                                                'div',
                                                { style: { color: '#666', marginTop: '4px' } },
                                                item.article_id || item.id
                                            ),
                                            wp.element.createElement(
                                                'div',
                                                { style: { color: '#666', marginTop: '4px', fontSize: '11px' } },
                                                getQueueProgressDetail(item) || 'No progress detail yet.'
                                            ),
                                            wp.element.createElement(
                                                'div',
                                                { style: { marginTop: '6px' } },
                                                wp.element.createElement(
                                                    Button,
                                                    {
                                                        isSecondary: true,
                                                        onClick: () => runPlannerQueueItem(item.id),
                                                        disabled: item.status !== 'queued' || !!queueActionLoading[`run:${item.id}`],
                                                    },
                                                    queueActionLoading[`run:${item.id}`] ? wp.element.createElement(Spinner, null) : 'Run Now'
                                                ),
                                                item.status === 'failed' &&
                                                    wp.element.createElement(
                                                        Button,
                                                        {
                                                            isSecondary: true,
                                                            onClick: () => rerunPlannerQueueItem(item),
                                                            disabled: !!queueActionLoading[`rerun:${item.id}`],
                                                            style: { marginLeft: '6px' },
                                                        },
                                                        queueActionLoading[`rerun:${item.id}`] ? wp.element.createElement(Spinner, null) : 'Retry'
                                                    ),
                                                ['queued', 'running', 'dispatched'].includes(item.status || '') &&
                                                    wp.element.createElement(
                                                        Button,
                                                        {
                                                            isDestructive: true,
                                                            onClick: () => stopPlannerQueueItem(item.id),
                                                            disabled: !!queueActionLoading[`stop:${item.id}`],
                                                            style: { marginLeft: '6px' },
                                                        },
                                                        queueActionLoading[`stop:${item.id}`] ? wp.element.createElement(Spinner, null) : 'Stop'
                                                    )
                                            )
                                        )
                                    )
                                  : wp.element.createElement(
                                        'p',
                                        { style: { margin: 0, color: '#666' } },
                                        'No queued/running jobs.'
                                    )
                          ),
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                        wp.element.createElement(
                            Button,
                            { isSecondary: true, onClick: loadPlannerQueue, disabled: queueLoading || queueClearing },
                            queueLoading ? wp.element.createElement(Spinner, null) : 'Refresh Queue'
                        ),
                        wp.element.createElement(
                            Button,
                            { isDestructive: true, onClick: clearQueuedJobs, disabled: queueLoading || queueClearing || queueRemoving },
                            queueClearing ? wp.element.createElement(Spinner, null) : 'Clear Queued'
                        ),
                        wp.element.createElement(
                            Button,
                            { isDestructive: true, onClick: removeAllQueueItems, disabled: queueLoading || queueClearing || queueRemoving },
                            queueRemoving ? wp.element.createElement(Spinner, null) : 'Remove All'
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
                              synopsisCompactSuggestion &&
                                  wp.element.createElement(
                                      Notice,
                                      { status: 'warning', isDismissible: false },
                                      `Compact mode suggested to avoid prompt-length failures (target ${synopsisCompactSuggestion.suggestedTotal}).`,
                                      wp.element.createElement(
                                          'div',
                                          { style: { marginTop: '8px' } },
                                          wp.element.createElement(
                                              Button,
                                              {
                                                  isSecondary: true,
                                                  onClick: applyCompactSynopsisPlan,
                                                  disabled: synopsisPlanLoading || synopsisGenerateLoading,
                                              },
                                              'Use Compact Plan'
                                          )
                                      )
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
                    onClick: navigateToNewSession,
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
                              hasProviderErrors &&
                                  wp.element.createElement(
                                      'div',
                                      {
                                          style: {
                                              margin: '8px 0 8px',
                                              padding: '10px',
                                              border: '1px solid #d63638',
                                              borderRadius: '4px',
                                              background: '#fff5f5',
                                          },
                                      },
                                      wp.element.createElement('strong', null, 'Search Provider Action Required'),
                                      wp.element.createElement(
                                          'p',
                                          { style: { margin: '6px 0 0' } },
                                          providerAdminInstruction
                                      ),
                                      serpapiIssue
                                          ? wp.element.createElement(
                                                'p',
                                                { style: { margin: '6px 0 0', fontSize: '12px', color: '#7a1f1f' } },
                                                `Detected provider error: ${serpapiIssue}`
                                            )
                                          : null
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
                          synopsisCompactSuggestion &&
                              wp.element.createElement(
                                  Notice,
                                  { status: 'warning', isDismissible: false },
                                  `Compact mode suggested to avoid prompt-length failures (target ${synopsisCompactSuggestion.suggestedTotal}).`,
                                  wp.element.createElement(
                                      'div',
                                      { style: { marginTop: '8px' } },
                                      wp.element.createElement(
                                          Button,
                                          {
                                              isSecondary: true,
                                              onClick: applyCompactSynopsisPlan,
                                              disabled: synopsisPlanLoading || synopsisGenerateLoading,
                                          },
                                          'Use Compact Plan'
                                      )
                                  )
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
