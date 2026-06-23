const { useEffect } = wp.element;

export const usePlannerSync = ({
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
}) => {
    // 1. Framework Polling Interval
    useEffect(() => {
        if (!detailModalOpen || !autoRefreshEnabled) return undefined;
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
    }, [detailModalOpen, autoRefreshEnabled, setFrameworkProgress]);

    // 2. Author Progress Interval
    useEffect(() => {
        if (!detailModalOpen) return undefined;
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
    }, [detailModalOpen, setAuthorProgress]);

    // 3. Session Status Core Sync Polling
    useEffect(() => {
        if (!detailModalOpen) return undefined;
        const hasRunningArticles =
            sessionDetail?.meta?.articles?.some((article) =>
                ['running', 'queued'].includes(article?.framework?.status)
            ) ||
            sessionDetail?.meta?.articles?.some((article) =>
                ['running', 'queued'].includes(article?.author?.status)
            ) || false;
            
        const hasRunningPhase =
            ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase1?.status) ||
            ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase2?.status) ||
            ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase3?.status) ||
            ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase4?.status) || false;
            
        if (!hasRunningArticles && !hasRunningPhase) return undefined;

        let cancelled = false;
        const poll = async () => {
            if (cancelled) return;
            await refreshSessionDetail();
        };
        const interval = setInterval(poll, hasRunningPhase ? 5000 : 30000);
        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [detailModalOpen, autoRefreshEnabled, sessionDetail?.meta?.articles, sessionDetail?.meta?.phases, refreshSessionDetail]);

    // 4. Interface Thinking Text Phrase Ticker
    useEffect(() => {
        if (!detailModalOpen) return undefined;
        const hasRunningPhase =
            ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase1?.status) ||
            ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase2?.status) ||
            ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase3?.status) ||
            ['running', 'queued', 'processing'].includes(sessionDetail?.meta?.phases?.phase4?.status) || false;
            
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
    }, [detailModalOpen, sessionDetail?.meta?.phases, synopsisGenerateLoading, diveDeeperModalOpen, isDiveDeeperWorking, setThinkingPhraseIndex, THINKING_PHRASES]);

    // 5. Active Queue Processing Watcher Loop
    useEffect(() => {
        if (!detailModalOpen || !sessionDetail?.id) return undefined;
        const hasActiveQueueItems = queueItems.some((item) =>
            ['queued', 'running', 'dispatched'].includes(item?.status || '')
        );
        if (!hasActiveQueueItems) return undefined;

        let cancelled = false;
        const interval = setInterval(async () => {
            if (cancelled || queueReorderLoading) return;
            await loadPlannerQueue({ silent: true });
            await refreshSessionDetail();
        }, 4000);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [detailModalOpen, sessionDetail?.id, queueItems, queueReorderLoading, loadPlannerQueue, refreshSessionDetail]);

    // 6. Deep Dive Jobs Status Synchronization Tracking
    useEffect(() => {
        if (!detailModalOpen || !sessionDetail?.id) return undefined;
        const articles = sessionDetail?.meta?.articles || [];
        const hasActiveDeepDiveJobs = articles.some((article) =>
            Array.isArray(article?.dive_deeper_jobs) &&
            article.dive_deeper_jobs.some((job) =>
                ['queued', 'running', 'processing', 'dispatched'].includes(job?.status || '')
            )
        );
        if (!hasActiveDeepDiveJobs) return undefined;
        
        let cancelled = false;
        const poll = async () => {
            if (cancelled) return;
            await refreshSessionDetail();
        };
        const interval = setInterval(poll, 5000);
        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [detailModalOpen, sessionDetail?.id, sessionDetail?.meta?.articles, refreshSessionDetail]); // Added refreshSessionDetail here
};