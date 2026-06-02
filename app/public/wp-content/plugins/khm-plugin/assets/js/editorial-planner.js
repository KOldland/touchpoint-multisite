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

            'X-WP-Nonce': editorialData.nonce,
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

                path: 'editorial/v1/sessions?limit=20',
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


                title: selectedTopic,
                meta: {
                    topic: selectedTopic,
                    includes,
                    excludes,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),



                }
            };

            const sessionResponse = await apiFetch({

                path: 'editorial/v1/sessions',
                method: 'POST',
                data: sessionPayload,
            });



            if (!sessionResponse || !sessionResponse.id) {
                throw new Error('Session creation did not return an id.');
            }

            await apiFetch({

                path: `editorial/v1/sessions/${sessionResponse.id}/run`,
                method: 'POST',




            });

            dispatch('core/notices').createNotice(
                'success',

                'Planning session created and discovery started.',
                { type: 'snackbar' }
            );

            setStartModalOpen(false);
            setIncludes([]);
            setExcludes([]);
            setSelectedTopic(topicOptions[0]?.value || TOPIC_OPTIONS[0].value);
            await loadSessions();

            await openSessionDetail(sessionResponse.id);
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

                path: `editorial/v1/sessions/${sessionId}?_t=${cacheBuster}`,
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
                data: { session_id: sessionDetail.id, plan: synopsisPlan, batch_size: SYNOPSIS_BATCH_SIZE },
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










































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































        setDiveDeepe
