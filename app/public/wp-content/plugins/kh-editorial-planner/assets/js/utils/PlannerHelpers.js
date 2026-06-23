export const AUTHOR_PROFILE_OPTIONS = [
    { label: 'Balanced', value: 'balanced' },
    { label: 'Journalistic', value: 'journalistic' },
    { label: 'Analytical', value: 'analytical' },
    { label: 'Executive', value: 'executive' },
];

export const WORD_LENGTH_OPTIONS = [
    { label: 'Standard', value: 'standard' },
    { label: 'Short', value: 'short' },
    { label: 'Long', value: 'long' },
];

export const getFocusLabel = (value) => {
    if (value >= 70) return 'Focused';
    if (value <= 30) return 'Broad';
    return 'Balanced';
};

export const getCitationCount = (article) => {
    const explicitCount = Number(article?.citation_count || 0);
    const citationsLength = Array.isArray(article?.citations) ? article.citations.length : 0;
    return Math.max(explicitCount, citationsLength);
};

export const getRecommendedAuthorProfile = (article) => {
    const blob = JSON.stringify({
        title: article?.title || article?.headline || '',
        summary: article?.summary || article?.brief || '',
        keywords: article?.keywords || [],
        framework: article?.framework?.output || {},
    }).toLowerCase();
    
    if (/\b(board|ceo|cfo|leadership|executive|strategy|roadmap|portfolio|investment)\b/.test(blob)) return 'executive';
    if (/\b(data|model|forecast|sensitivity|variance|analysis|benchmark|quant|correlation|method)\b/.test(blob)) return 'analytical';
    if (/\b(report|interview|case study|investigation|survey|field|news|press|announced)\b/.test(blob)) return 'journalistic';
    return 'balanced';
};

export const getSelectedAuthorProfile = (article, selectionState) =>
    selectionState?.[article?.id] || article?.author?.profile || getRecommendedAuthorProfile(article);

export const getAuthorProfileLabel = (value) =>
    AUTHOR_PROFILE_OPTIONS.find((item) => item.value === value)?.label || 'Balanced';

export const getSelectedWordLength = (article, selectionState) =>
    selectionState?.[article?.id] || 'standard';

export const estimateSynopses = (meta, value) => {
    if (!meta) return { min: 0, max: 0, estimate: 0, topics: 0 };
    const phase1Trends = meta?.phases?.phase1?.payload?.trends?.length || 0;
    const phase1Keywords = meta?.phases?.phase1?.payload?.candidate_keywords?.length || 0;
    const phase2Metrics = meta?.phases?.phase2?.payload?.keyword_metrics?.length || 0;
    const phase3Topics = meta?.phases?.phase3?.payload?.prioritized_topics?.length || 0;
    const phase4Topics = meta?.phases?.phase4?.payload?.validated_topics?.length || 0;
    
    const validated = meta?.phases?.phase4?.payload?.validated_topics || [];
    const phase4Citations = validated.reduce(
        (total, item) => total + (Array.isArray(item?.supporting_citations || item?.citations) ? (item.supporting_citations || item.citations).length : 0),
        0
    );
    
    const topics = Math.max(phase4Topics, phase3Topics, 1);
    const breadthScore = phase1Trends + Math.round(phase2Metrics / 3) + Math.round(phase1Keywords / 4);
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

export const summarizeAuthorValidation = (meta) => {
    const summary = { drafts_with_output: 0, drafts_failed: 0, error_count: 0, warning_count: 0, issues: [] };
    const articles = Array.isArray(meta?.articles) ? meta.articles : [];
    
    articles.forEach((article) => {
        const author = article?.author || {};
        if (author?.status === 'failed') summary.drafts_failed += 1;

        const output = author?.output;
        if (!output || typeof output !== 'object') return;

        summary.drafts_with_output += 1;
        const validationErrors = Array.isArray(output?.validation_errors) ? output.validation_errors : [];
        const warnings = Array.isArray(output?.warnings) ? output.warnings : [];

        summary.error_count += validationErrors.length;
        summary.warning_count += warnings.length;

        validationErrors.forEach((m) => m && summary.issues.push({ severity: 'error', message: String(m) }));
        warnings.forEach((m) => m && summary.issues.push({ severity: 'warning', message: String(m) }));
    });
    return summary;
};

export const getScoringBadgeColor = (qualityLevel) => {
    if (!qualityLevel) return '#f0f0f1';
    const level = qualityLevel.toLowerCase();
    if (level === 'excellent') return '#edfaef';
    if (level === 'good') return '#e7f5ff';
    if (level === 'standard' || level === 'poor') return '#fffbe6';
    return '#f6f7f7';
};