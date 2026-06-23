// imports
import { blocksToHTML, handleExportAuthorDraft } from './ModalViewerHub.js';
import { EditorQueueTable } from './components/EditorQueueTable.js';
import { ArticleModalsHub } from './components/ArticleModalsHub.js';
import { SessionsDashboardList } from './components/SessionsDashboardList.js';
import { ArticleTable } from './components/ArticleTable.js';
import { PhaseCard } from './components/PhaseCard.js';
import ResearchPolicyPanel from './components/ResearchPolicyPanel.js';
import { usePlannerSync } from './hooks/usePlannerSync.js'; // 1. Hook Import Added
import { 
    getFocusLabel, 
    estimateSynopses, 
    summarizeAuthorValidation,
    getSelectedAuthorProfile,
    getAuthorProfileLabel,
    getRecommendedAuthorProfile,
    getSelectedWordLength,
    getScoringBadgeColor,
    AUTHOR_PROFILE_OPTIONS,
    WORD_LENGTH_OPTIONS
} from './utils/PlannerHelpers.js';
import { getQueueProgressDetail } from './TaskQueueManager.js'; 

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

    const TOPIC_OPTIONS = [];

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

    const MIN_CITATIONS_REQUIRED = 4;
    const IDEAL_CITATIONS_TARGET = 6;
    const LOW_CITATION_QUEUE_BATCH_SIZE = 6;
    const SYNOPSIS_BATCH_SIZE = 2;
    const PHASE_ORDER = ['phase1', 'phase2', 'phase3', 'phase4'];
    const DIVE_DEEPER_STALL_SECONDS = 120;
    
    const DIVE_DEEPER_STAGE_META = {
        queued: { label: 'Starting', progress: 15, detail: 'Job dispatched. Waiting for worker to begin.' },
        running: { label: 'Researching', progress: 50, detail: 'Searching for citations and analyzing sources.' },
        processing: { label: 'Finalizing', progress: 85, detail: 'Applying citations to this article.' },
        completed: { label: 'Complete', progress: 100, detail: 'Source-check finished successfully.' },
        failed: { label: 'Failed', progress: 100, detail: 'Source-check failed. You can retry.' },
        starting: { label: 'Preparing', progress: 5, detail: 'Preparing the source-check request.' },
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
        const [selectedTopic, setSelectedTopic] = useState('');
        const [selectedPillar, setSelectedPillar] = useState(null);
        const [pillarOptions, setPillarOptions] = useState([]);
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
        const [deletingSessionId, setDeletingSessionId] = useState('');
        const [authorPreview, setAuthorPreview] = useState(null);
        const [authorProfileSelection, setAuthorProfileSelection] = useState({});
        const [wordLengthSelection, setWordLengthSelection] = useState({});
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
        const [expandedPillars, setExpandedPillars] = useState({});
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

        const params = new URLSearchParams(window.location.search);
        const viewingSessionId = params.get('session') || params.get('id');

        const navigateToSession = (sessionId) => {
            if (!sessionId) {
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.set('id', String(sessionId));
            url.searchParams.delete('session');
            window.location.href = `${url.pathname}${url.search}`;
        };

        const navigateBack = () => {
            const url = new URL(window.location.href);
            url.searchParams.delete('id');
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

        const deleteSession = async (sessionId) => {
            if (!sessionId) {
                return;
            }

            const confirmed = window.confirm('Delete this session permanently? This cannot be undone.');
            if (!confirmed) {
                return;
            }

            try {
                setDeletingSessionId(sessionId);
                setSessionsError('');
                await apiFetch({
                    path: `editorial/v1/sessions/${sessionId}`,
                    method: 'DELETE',
                });
                dispatch('core/notices').createNotice(
                    'success',
                    'Session deleted successfully.',
                    { type: 'snackbar' }
                );
                if (sessionDetail?.id === sessionId) {
                    navigateBack();
                }
                await loadSessions();
            } catch (error) {
                console.error('Failed to delete session:', error);
                setSessionsError(error?.message || 'Failed to delete session.');
            } finally {
                setDeletingSessionId('');
            }
        };

        const navigateToNewSession = () => {
            const url = new URL(window.location.href);
            url.searchParams.delete('id');
            url.searchParams.delete('session');
            url.searchParams.set('page', 'kh-planner-new');
            window.location.href = `${url.pathname}${url.search}`;
        };

        useEffect(() => {
            loadSessions();
            loadTopLineCategories();
        }, []);

        const loadTopLineCategories = async () => {
            try {
                const response = await apiFetch({
                    path: 'editorial/v1/planner/top-line-categories',
                    method: 'GET',
                });
                const rows = Array.isArray(response?.top_line_categories) ? response.top_line_categories : [];
                if (!rows.length) {
                    return;
                }

                const options = rows.map((row) => ({ 
                    label: row.name, 
                    value: row.name,
                    slug: row.slug,
                    site_slug: row.site_slug || row.slug,
                    pillars: row.pillars || [],
                }));
                setTopicOptions(options);
                if (!selectedTopic || !options.some((option) => option.value === selectedTopic)) {
                    setSelectedTopic(options[0].value || '');
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

                const selectedCategory = topicOptions.find(o => o.value === selectedTopic);
                const audienceSlug = selectedCategory?.site_slug || selectedTopic.toLowerCase().replace(/\s+/g, '-');
                const pillarValue = selectedPillar || '';

                const sessionPayload = {
                    role: 'research',
                    preset_id: 'research-default',
                    title: selectedTopic,
                    meta: {
                        topic: selectedTopic,
                        pillar: pillarValue,
                        pillar_slug: pillarValue.toLowerCase().replace(/\s+/g, '-'),
                        audience_slug: audienceSlug,
                        includes,
                        excludes,
                        ...(showFocusControls ? { focus_level: focusLevel } : {}),
                        research_policy: DEFAULT_RESEARCH_POLICY,
                    },
                    idempotency_key: `planner-${Date.now()}`,
                };

                const sessionResponse = await apiFetch({
                    path: 'editorial/v1/sessions',
                    method: 'POST',
                    data: sessionPayload,
                });

                if (!sessionResponse || !sessionResponse.id) {
                    throw new Error('Session creation did not return a session id.');
                }

                await apiFetch({
                    path: `editorial/v1/sessions/${sessionResponse.id}/run`,
                    method: 'POST',
                    data: {
                        id: sessionResponse.id,
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
                setSelectedTopic(topicOptions[0]?.value || '');
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
                    if (url.searchParams.get('id') !== String(sessionId)) {
                        url.searchParams.set('id', String(sessionId));
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
                        path: `editorial/v1/planner/research-validation?id=${sessionId}&_t=${cacheBuster}`,
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
                        path: `editorial/v1/planner/author-policy?id=${sessionId}&_t=${cacheBuster}`,
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
                const cacheBuster = new Date().getTime();
                const response = await apiFetch({
                    path: `editorial/v1/planner/queue?id=${sessionDetail.id}&_t=${cacheBuster}`,
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
                    path: 'editorial/v1/planner/queue/add',
                    method: 'POST',
                    data: {
                        id: sessionDetail.id,
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
                    path: 'editorial/v1/planner/queue/run',
                    method: 'POST',
                    data: {
                        id: sessionDetail.id,
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
                    path: 'editorial/v1/planner/queue/run-bulk',
                    method: 'POST',
                    data: runAllQueued
                        ? { run_all_queued: true, id: sessionDetail.id }
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
                    path: 'editorial/v1/planner/queue/reorder',
                    method: 'POST',
                    data: {
                        id: sessionDetail.id,
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
                    path: 'editorial/v1/planner/queue/remove',
                    method: 'POST',
                    data: { id: sessionDetail.id, queue_id: queueId },
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
                    path: 'editorial/v1/planner/queue/stop',
                    method: 'POST',
                    data: {
                        id: sessionDetail.id,
                        queue_id: queueId,
                        reason: 'Stopped by operator (manual cancel).',
                    },
                });
                dispatch('warning', 'Queue item stopped.', { type: 'snackbar' });
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
                    path: 'editorial/v1/planner/queue/add',
                    method: 'POST',
                    data: {
                        id: sessionDetail.id,
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
                    path: 'editorial/v1/planner/queue/run',
                    method: 'POST',
                    data: { id: sessionDetail.id, queue_id: newQueueId },
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
                    path: 'editorial/v1/planner/queue/remove-all',
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
                    path: 'editorial/v1/planner/queue/clear',
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


        // --- Handler Stubs (decoupled from monolith — wire up real implementations) ---
        const openSynopsisModal = () => { /* TODO */ };
        const handleRerunPhase1 = async () => { /* TODO */ };
        const handleRerunPhase2 = async () => { /* TODO */ };
        const handleRerunPhase3 = async () => { /* TODO */ };
        const handleRerunPhase4 = async () => { /* TODO */ };
        const handleReanalyseGaps = async () => { /* TODO */ };
        const handleDismissArticle = async () => { /* TODO */ };
        const handleDeepDiveArticle = async (article) => { /* TODO */ };
        const handleOpinionPieceArticle = async (article) => { /* TODO */ };
        const handleRegenerateFramework = async (article) => { /* TODO */ };
        const handleQueueFrameworkGeneration = async (article) => { /* TODO */ };
        const handleRunAuthorAgent = async (article) => { /* TODO */ };
        const handleQueueAuthorAgent = async (article) => { /* TODO */ };
        const handleRecommendImageArticle = async (article) => { /* TODO */ };
        const handleGenerateImageArticle = async (article) => { /* TODO */ };
        const handleExportFramework = (article) => { /* TODO */ };
        const updateSynopsisCount = (count) => { /* TODO */ };
        const handleGenerateSynopses = async () => { /* TODO */ };
        const closeDiveDeeperModal = () => { setDiveDeeperModalOpen(false); setDiveDeeperArticle(null); setDiveDeeperSuccess(false); };
        const handleDiveDeeperSubmit = async () => { /* TODO */ };
        const handleDiveDeeperQueueSubmit = async () => { /* TODO */ };
        const handleDiveDeeperRetry = async () => { /* TODO */ };

        // 2. Synchronized Driver Hook Initialization (after all function definitions)
        usePlannerSync({
            detailModalOpen,
            autoRefreshEnabled,
            sessionDetail,
            queueItems,
            queueReorderLoading,
            synopsisGenerateLoading,
            diveDeeperModalOpen,
            isDiveDeeperWorking,
            setFrameworkProgress,
            setAuthorProgress,
            refreshSessionDetail,
            loadPlannerQueue,
            setThinkingPhraseIndex,
            THINKING_PHRASES
        });
        // Execution variables scoped cleanly for structural layout components
        const phases = sessionDetail?.meta?.phases || {};
        const filteredRows = (sessionDetail?.meta?.articles || []).map(article => ({
            article,
            metric: 0,
            citationsCount: Array.isArray(article?.citations) ? article.citations.length : 0,
            priorityScore: 0,
            marketSignal: null,
        }));
        
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

        // Loading fallbacks
        if (viewingSessionId && !sessionDetail) {
            return wp.element.createElement(
                'div',
                { className: 'editorial-planner-dashboard' },
                wp.element.createElement(
                    'div',
                    { style: { textAlign: 'center', padding: '48px 24px' } },
                    wp.element.createElement(Spinner, null),
                    wp.element.createElement(
                        'p',
                        { style: { marginTop: '16px', fontSize: '14px', color: '#50575e' } },
                        'Loading session details...'
                    )
                )
            );
        }

        // View Router Switch logic
        if (detailModalOpen && sessionDetail) {
            return wp.element.createElement(
                'div',
                { className: 'editorial-planner-session-detail' },
                
                // 1. Research Phases Grid Rendering Layer
                wp.element.createElement(
                    'div',
                    { className: 'planner-phases-grid', style: { display: 'grid', gap: '16px', marginBottom: '24px' } },
                    PHASE_ORDER.filter(key => !!phases[key]).map(key => 
                        wp.element.createElement(PhaseCard, {
                            key: key,
                            phaseKey: key,
                            phase: phases[key],
                            isExpanded: !!expandedPhases[key],
                            onToggle: () => setExpandedPhases(prev => ({ ...prev, [key]: !prev[key] })),
                            handleRerunPhase1: handleRerunPhase1,
                            handleRerunPhase2: handleRerunPhase2,
                            handleRerunPhase3: handleRerunPhase3,
                            handleRerunPhase4: handleRerunPhase4,
                            openSynopsisModal: openSynopsisModal,
                            isLoadingPhase1: phase1RerunLoading,
                            isLoadingPhase2: phase2RerunLoading,
                            isLoadingPhase3: phase3RerunLoading,
                            isLoadingPhase4: phase4RerunLoading,
                            phase3Complete: phases?.phase3?.status === 'completed',
                            phase4Complete: phases?.phase4?.status === 'completed',
                            hasProviderErrors: hasProviderErrors,
                            providerAdminInstruction: providerAdminInstruction,
                            serpapiIssue: serpapiIssue
                        })
                    )
                ),

                // Connected Content Gaps Pipeline Layer
                wp.element.createElement(ResearchPolicyPanel, {
                    sessionDetail: sessionDetail,
                    onReanalyseGaps: handleReanalyseGaps
                }),

                // 2. Main Synopses Management Layout Screen Component
                wp.element.createElement(ArticleTable, {
                    rows: filteredRows,
                    frameworkProgress: frameworkProgress,
                    authorProgress: authorProgress,
                    authorLoading: authorLoading,
                    articleActionLoading: articleActionLoading,
                    queueActionLoading: queueActionLoading,
                    
                    // Modals / Action link handlers required by ArticleTable
                    onDismiss: handleDismissArticle,
                    onDeepDive: handleDeepDiveArticle,
                    onOpinion: handleOpinionPieceArticle,
                    onRegenerateFramework: handleRegenerateFramework,
                    onQueueFramework: handleQueueFrameworkGeneration,
                    onRunAuthor: handleRunAuthorAgent,
                    onQueueAuthor: handleQueueAuthorAgent,
                    onRecommendImage: handleRecommendImageArticle,
                    onGenerateImage: handleGenerateImageArticle,
                    onExportFramework: handleExportFramework,
                    
                    // Connected overlay window controllers
                    onPreview: (article) => setPreviewArticle(article),
                    onFrameworkPreview: (article) => setFrameworkPreview(article),
                    onViewDraft: (article) => setAuthorPreview(article),
                    onExportDraft: (article) => handleExportAuthorDraft(article),
                    onOpenEditor: (article) => article?.author?.edit_url && window.open(article.author.edit_url, '_blank'),
                    
                    // Profile value mappers and configuration options
                    getSelectedAuthorProfile: (article) => authorProfileSelection[article.id] || article?.author?.profile || '',
                    getRecommendedAuthorProfile: (article) => getRecommendedAuthorProfile(article),
                    getSelectedWordLength: (article) => wordLengthSelection[article.id] || '',
                    setAuthorProfileSelection: (id, value) => setAuthorProfileSelection(prev => ({ ...prev, [id]: value })),
                    setWordLengthSelection: (id, value) => setWordLengthSelection(prev => ({ ...prev, [id]: value })),
                    
                    getAuthorProfileLabel: getAuthorProfileLabel,
                    AUTHOR_PROFILE_OPTIONS: AUTHOR_PROFILE_OPTIONS,
                    WORD_LENGTH_OPTIONS: WORD_LENGTH_OPTIONS,
                    MIN_CITATIONS_REQUIRED: MIN_CITATIONS_REQUIRED,
                    IDEAL_CITATIONS_TARGET: IDEAL_CITATIONS_TARGET
                }),

                // 3. System Worker Hub Async Task Queue Panel Overlay
                wp.element.createElement(EditorQueueTable, {
                    sessionDetail: sessionDetail,
                    queueItems: queueItems,
                    queueCounts: queueCounts,
                    queueStatusFilter: queueStatusFilter,
                    queueTaskTypeFilter: queueTaskTypeFilter,
                    selectedQueueItems: selectedQueueItems,
                    draggedQueueItemId: draggedQueueItemId,
                    queueLoading: queueLoading,
                    queueClearing: queueClearing,
                    queueRemoving: queueRemoving,
                    queueReorderLoading: queueReorderLoading,
                    queueActionLoading: queueActionLoading,
                    queueError: queueError,
                    setQueueStatusFilter: setQueueStatusFilter,
                    setQueueTaskTypeFilter: setQueueTaskTypeFilter,
                    setDraggedQueueItemId: setDraggedQueueItemId,
                    loadPlannerQueue: loadPlannerQueue,
                    runPlannerQueueBulk: runPlannerQueueBulk,
                    runPlannerQueueItem: runPlannerQueueItem,
                    rerunPlannerQueueItem: rerunPlannerQueueItem,
                    clearQueuedJobs: clearQueuedJobs,
                    removeAllQueueItems: removeAllQueueItems,
                    stopPlannerQueueItem: stopPlannerQueueItem,
                    handleQueuePreview: handleQueuePreview,
                    toggleQueueItemSelection: toggleQueueItemSelection,
                    handleQueueRowDrop: handleQueueRowDrop,
                    taskTypeLabel: taskTypeLabel,
                    queueStatusLabel: queueStatusLabel,
                    getQueueProgressDetail: getQueueProgressDetail
                }),

                // Core Component Dialogue Hub Modals Pipeline Bridge
                wp.element.createElement(ArticleModalsHub, {
                    sessionDetail: sessionDetail,
                    previewArticle: previewArticle,
                    setPreviewArticle: setPreviewArticle,
                    frameworkPreview: frameworkPreview,
                    setFrameworkPreview: setFrameworkPreview,
                    authorPreview: authorPreview,
                    setAuthorPreview: setAuthorPreview,
                    synopsisModalOpen: synopsisModalOpen,
                    setSynopsisModalOpen: setSynopsisModalOpen,
                    synopsisPlan: synopsisPlan,
                    synopsisPlanLoading: synopsisPlanLoading,
                    synopsisPlanError: synopsisPlanError,
                    synopsisGenerateLoading: synopsisGenerateLoading,
                    synopsisTotal: synopsisTotal,
                    updateSynopsisCount: updateSynopsisCount,
                    handleGenerateSynopses: handleGenerateSynopses,
                    diveDeeperModalOpen: diveDeeperModalOpen,
                    closeDiveDeeperModal: closeDiveDeeperModal,
                    diveDeeperArticle: diveDeeperArticle,
                    diveDeeperDepthSlider: diveDeeperDepthSlider,
                    setDiveDeeperDepthSlider: setDiveDeeperDepthSlider,
                    diveDeeperSuccess: diveDeeperSuccess,
                    diveDeeperJobId: diveDeeperJobId,
                    diveDeeperJobStatus: diveDeeperJobStatus,
                    diveDeeperJobError: diveDeeperJobError,
                    diveDeeperElapsedSeconds: diveDeeperElapsedSeconds,
                    isDiveDeeperWorking: isDiveDeeperWorking,
                    diveDeeperStageMeta: diveDeeperStageMeta,
                    isDiveDeeperStalled: isDiveDeeperStalled,
                    handleDiveDeeperSubmit: handleDiveDeeperSubmit,
                    handleDiveDeeperQueueSubmit: handleDiveDeeperQueueSubmit,
                    handleDiveDeeperRetry: handleDiveDeeperRetry,
                    showThinkingIndicator: showThinkingIndicator,
                    activePhaseLabel: activePhaseLabel,
                    thinkingPhraseIndex: thinkingPhraseIndex,
                    THINKING_PHRASES: THINKING_PHRASES,
                    onNavigateBack: navigateBack
                })
            );
        }

        // Fallback View: Default Dashboard Panel Listing Screen
        return wp.element.createElement(SessionsDashboardList, {
            sessions: sessions,
            loadingSessions: loadingSessions,
            sessionsError: sessionsError,
            deletingSessionId: deletingSessionId,
            startModalOpen: startModalOpen,
            selectedTopic: selectedTopic,
            topicOptions: topicOptions,
            selectedPillar: selectedPillar,
            pillarOptions: pillarOptions,
            includes: includes,
            excludes: excludes,
            starting: starting,
            focusLevel: focusLevel,
            setFocusLevel: setFocusLevel,
            setStartModalOpen: setStartModalOpen,
            setSelectedTopic: setSelectedTopic,
            setSelectedPillar: setSelectedPillar,
            setPillarOptions: setPillarOptions,
            setIncludes: setIncludes,
            setExcludes: setExcludes,
            navigateToNewSession: navigateToNewSession,
            navigateToSession: navigateToSession,
            deleteSession: deleteSession,
            startNewSession: startNewSession
        });
    };

    wp.element.render(
        wp.element.createElement(EditorialPlannerApp),
        document.getElementById('editorial-planner-app')
    );
})();