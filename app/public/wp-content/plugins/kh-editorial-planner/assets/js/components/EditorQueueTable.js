const { createElement } = wp.element;
const { Button, Spinner, SelectControl, Notice } = wp.components;

// Simple internal helper for safe timestamp comparison
const parseQueueDate = (dateStr) => {
    if (!dateStr) return 0;
    const timestamp = Date.parse(dateStr);
    return isNaN(timestamp) ? 0 : timestamp;
};

export const EditorQueueTable = ({
    // Data & State
    sessionDetail,
    queueItems,
    queueCounts,
    queueStatusFilter,
    queueTaskTypeFilter,
    selectedQueueItems,
    draggedQueueItemId,
    queueLoading,
    queueClearing,
    queueRemoving,
    queueReorderLoading,
    queueActionLoading,
    queueError,
    
    // State Setters
    setQueueStatusFilter,
    setQueueTaskTypeFilter,
    setDraggedQueueItemId,
    
    // Handlers
    loadPlannerQueue,
    runPlannerQueueBulk,
    runPlannerQueueItem,
    rerunPlannerQueueItem,
    clearQueuedJobs,
    removeAllQueueItems,
    stopPlannerQueueItem,
    handleQueuePreview,
    toggleQueueItemSelection,
    handleQueueRowDrop,
    
    // Label Helpers from Main App
    taskTypeLabel,
    queueStatusLabel,
    getQueueProgressDetail
}) => {
    const articles = sessionDetail?.meta?.articles || [];
    
    // 1. Filter Queue Items
    const filteredQueueItems = queueItems.filter((item) => {
        const matchesStatus = queueStatusFilter === 'all' ? true : item.status === queueStatusFilter;
        const matchesTaskType = queueTaskTypeFilter === 'all' ? true : item.task_type === queueTaskTypeFilter;
        return matchesStatus && matchesTaskType;
    });

    // 2. Sort Queue Items
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
        if (aQueued) return -1;
        if (bQueued) return 1;

        return parseQueueDate(b?.updated_at || b?.created_at) - parseQueueDate(a?.updated_at || a?.created_at);
    });

    const queuedItems = queueItems.filter((item) => item.status === 'queued');
    const activeQueueItems = queueItems.filter((item) => ['queued', 'running', 'dispatched'].includes(item.status || ''));
    const hiddenActiveItems = activeQueueItems.filter((item) => !filteredQueueItems.some((filtered) => filtered.id === item.id));
    
    // 3. Assemble Grid Entries
    const tableEntries = [
        ...hiddenActiveItems.map((item) => ({ type: 'item', item, pinned: true })),
        ...(hiddenActiveItems.length ? [{ type: 'separator', id: 'filtered-results-separator' }] : []),
        ...sortedQueueItems.map((item) => ({ type: 'item', item, pinned: false })),
    ];
    
    const selectedQueuedItemsData = queuedItems.filter((item) => selectedQueueItems.includes(item.id));
    
    const articleTitleById = articles.reduce((acc, article) => {
        acc[article.id] = article.headline || article.title || article.id;
        return acc;
    }, {});

    const canRemoveQueueItem = (status) => !['running', 'dispatched'].includes(status || '');

    return createElement(
        'div',
        { style: { marginTop: '16px' } },
        createElement('h2', null, 'Editor Queue'),
        queueError && createElement(Notice, { status: 'error', isDismissible: false }, queueError),
        createElement(
            'p',
            { style: { margin: '8px 0', fontSize: '12px', color: '#50575e' } },
            `Queued: ${queueCounts?.queued || 0} · Running: ${queueCounts?.running || 0} · Completed: ${queueCounts?.completed || 0} · Failed: ${queueCounts?.failed || 0}`
        ),
        createElement(
            'p',
            { style: { margin: '0 0 8px', fontSize: '12px', color: '#666' } },
            'Drag queued rows to reorder priority, then run selected items or run all queued items.'
        ),
        createElement(
            'div',
            { style: { display: 'flex', gap: '8px', marginBottom: '10px' } },
            createElement(SelectControl, {
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
            createElement(SelectControl, {
                label: 'Category filter',
                value: queueTaskTypeFilter,
                options: [
                    { label: 'All categories', value: 'all' },
                    { label: 'Deeper Dives', value: 'dive_deeper' },
                    { label: 'Framework Generation', value: 'framework_generation' },
                    { label: 'Article Creation', value: 'article_creation' },
                ],
                onChange: setQueueTaskTypeFilter,
            })
        ),
        createElement(
            'div',
            { style: { display: 'flex', gap: '8px', marginBottom: '10px' } },
            createElement(
                Button,
                { isSecondary: true, onClick: loadPlannerQueue, disabled: queueLoading || queueClearing },
                queueLoading ? createElement(Spinner, null) : 'Refresh Queue'
            ),
            createElement(
                Button,
                {
                    isSecondary: true,
                    onClick: () => runPlannerQueueBulk({ queueIds: selectedQueuedItemsData.map((item) => item.id) }),
                    disabled: !selectedQueuedItemsData.length || !!queueActionLoading['run:selected'] || queueLoading || queueReorderLoading,
                },
                queueActionLoading['run:selected'] ? createElement(Spinner, null) : `Run Selected (${selectedQueuedItemsData.length})`
            ),
            createElement(
                Button,
                {
                    isPrimary: true,
                    onClick: () => runPlannerQueueBulk({ runAllQueued: true }),
                    disabled: !queuedItems.length || !!queueActionLoading['run:all'] || queueLoading || queueReorderLoading,
                },
                queueActionLoading['run:all'] ? createElement(Spinner, null) : `Run All Queued (${queuedItems.length})`
            ),
            createElement(
                Button,
                { isDestructive: true, onClick: clearQueuedJobs, disabled: queueLoading || queueClearing || queueRemoving },
                queueClearing ? createElement(Spinner, null) : 'Clear Queued'
            ),
            createElement(
                Button,
                { isDestructive: true, onClick: removeAllQueueItems, disabled: queueLoading || queueClearing || queueRemoving },
                queueRemoving ? createElement(Spinner, null) : 'Remove All'
            )
        ),
        queueLoading && createElement('p', { style: { margin: '6px 0', fontSize: '12px', color: '#666' } }, 'Refreshing queue…'),
        hiddenActiveItems.length > 0 && createElement(
            Notice,
            { status: 'info', isDismissible: false },
            `${hiddenActiveItems.length} active queue item(s) are pinned above the filtered results so controls stay available in every view.`
        ),
        createElement(
            'table',
            { className: 'widefat striped' },
            createElement('thead', null,
                createElement('tr', null,
                    ['', '', 'Order', 'Task', 'Article', 'Status', 'Created', 'Action', 'Remove'].map((h, i) => createElement('th', { key: i }, h))
                )
            ),
            createElement('tbody', null,
                tableEntries.length ? tableEntries.map((entry) => {
                    if (entry.type === 'separator') {
                        return createElement(
                            'tr',
                            { key: entry.id },
                            createElement('td', { colSpan: 9, style: { background: '#f6f7f7', color: '#50575e', fontSize: '12px', fontWeight: 600 } }, 'Filtered results')
                        );
                    }

                    const item = entry.item;
                    const runKey = `run:${item.id}`;
                    const removeKey = `remove:${item.id}`;
                    const stopKey = `stop:${item.id}`;
                    const rerunKey = `rerun:${item.id}`;
                    
                    const isRunningAction = !!queueActionLoading[runKey];
                    const isRemoveAction = !!queueActionLoading[removeKey];
                    const isStopAction = !!queueActionLoading[stopKey];
                    const isRerunAction = !!queueActionLoading[rerunKey];
                    
                    const isQueued = item.status === 'queued';
                    const isCompleted = item.status === 'completed';
                    const isFailed = item.status === 'failed';
                    const isStoppable = ['queued', 'running', 'dispatched'].includes(item.status || '');
                    const isSelected = selectedQueueItems.includes(item.id);
                    
                    const articleTitle = articleTitleById[item.article_id] || item.article_id || '—';
                    const failureReason = isFailed ? String(item.error_message || '').trim() : '';
                    const progressDetail = getQueueProgressDetail(item);

                    return createElement(
                        'tr',
                        {
                            key: item.id,
                            draggable: isQueued && !queueReorderLoading,
                            onDragStart: () => setDraggedQueueItemId(item.id),
                            onDragOver: (e) => { if (isQueued) e.preventDefault(); },
                            onDrop: (e) => { e.preventDefault(); handleQueueRowDrop(item.id); },
                            style: {
                                cursor: isQueued ? 'move' : 'default',
                                opacity: draggedQueueItemId === item.id ? 0.6 : 1,
                                background: entry.pinned ? '#fffbe6' : undefined,
                            },
                        },
                        createElement('td', { style: { color: isQueued ? '#666' : '#bbb', width: '28px', textAlign: 'center' } }, isQueued ? '⋮⋮' : '•'),
                        createElement('td', null, createElement('input', {
                            type: 'checkbox',
                            checked: isSelected,
                            disabled: !isQueued,
                            onChange: (e) => toggleQueueItemSelection(item.id, !!e?.target?.checked),
                        })),
                        createElement('td', null, item.position || '—'),
                        createElement('td', null, taskTypeLabel(item.task_type)),
                        createElement('td', null, articleTitle),
                        createElement('td', null, 
                            queueStatusLabel(item.status || 'queued'),
                            progressDetail && createElement('div', { style: { marginTop: '4px', fontSize: '11px', color: '#666', lineHeight: 1.35 } }, progressDetail),
                            failureReason && createElement('div', { style: { marginTop: '4px', fontSize: '11px', color: '#a00', maxWidth: '260px', lineHeight: 1.35 }, title: failureReason }, failureReason.length > 120 ? `${failureReason.slice(0, 120)}…` : failureReason)
                        ),
                        createElement('td', null, item.created_at || '—'),
                        createElement('td', null,
                            createElement('div', { style: { display: 'flex', gap: '6px', flexWrap: 'wrap' } },
                                createElement(Button, { isSecondary: true, onClick: () => runPlannerQueueItem(item.id), disabled: !isQueued || isRunningAction || queueReorderLoading }, isRunningAction ? createElement(Spinner) : 'Run Now'),
                                isCompleted && createElement(Button, { isSecondary: true, onClick: () => handleQueuePreview(item) }, 'Preview'),
                                isCompleted && createElement(Button, { isSecondary: true, onClick: () => rerunPlannerQueueItem(item), disabled: isRerunAction || queueReorderLoading }, isRerunAction ? createElement(Spinner) : 'Re-run'),
                                isFailed && createElement(Button, { isSecondary: true, onClick: () => rerunPlannerQueueItem(item), disabled: isRerunAction || isRunningAction || queueReorderLoading }, isRerunAction ? createElement(Spinner) : 'Retry'),
                                isStoppable && createElement(Button, { isDestructive: true, onClick: () => stopPlannerQueueItem(item.id), disabled: isStopAction }, isStopAction ? createElement(Spinner) : 'Stop')
                            )
                        ),
                        createElement('td', null,
                            createElement(Button, { isSecondary: true, onClick: () => removePlannerQueueItem(item.id), disabled: !canRemoveQueueItem(item.status) || isRemoveAction }, isRemoveAction ? createElement(Spinner) : 'Remove')
                        )
                    );
                }) : createElement('tr', null, createElement('td', { colSpan: 9, style: { color: '#666' } }, 'No queue items match the current filters.'))
            )
        )
    );
};