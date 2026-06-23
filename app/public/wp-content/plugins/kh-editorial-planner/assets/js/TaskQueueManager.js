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

export const getQueueProgressDetail = (item) => {
    const status = item?.status || 'queued';
    if (status === 'queued') {
        return 'Waiting in queue';
    }
    if (status === 'dispatched') {
        const elapsed = formatQueueElapsed(item?.started_at || item?.updated_at || item?.created_at);
        return elapsed ? `Job sent to backend · ${elapsed}` : 'Job sent to backend';
    }
    if (status === 'running') {
        const elapsed = formatQueueElapsed(item?.started_at || item?.updated_at || item?.created_at);
        return elapsed ? `Backend processing · ${elapsed}` : 'Backend processing';
    }
    if (status === 'completed') {
        return item?.updated_at ? `Completed at ${item.updated_at}` : 'Completed';
    }
    if (status === 'failed') {
        return item?.updated_at ? `Failed at ${item.updated_at}` : 'Failed';
    }
    return '';
};