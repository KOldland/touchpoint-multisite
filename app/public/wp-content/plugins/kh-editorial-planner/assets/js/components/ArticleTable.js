const { createElement } = wp.element;
const { Button, Spinner, ProgressBar, SelectControl, Notice } = wp.components;

const classifyCitationBand = (count, MIN_CITATIONS, IDEAL_CITATIONS) => {
    if (count < 2) return 'critical';
    if (count < MIN_CITATIONS) return 'below_minimum';
    if (count < IDEAL_CITATIONS) return 'ready';
    return 'ideal';
};

export const ArticleTable = ({
    rows,
    frameworkProgress,
    authorProgress,
    authorLoading,
    articleActionLoading,
    queueActionLoading,
    frameworkLoading,
    onDismiss,
    onDeepDive,
    onOpinion,
    onPreview,
    onFrameworkPreview,
    onExportFramework,
    onRegenerateFramework,
    onQueueFramework,
    onRunAuthor,
    onQueueAuthor,
    onViewDraft,
    onExportDraft,
    onOpenEditor,
    getSelectedAuthorProfile,
    getRecommendedAuthorProfile,
    getSelectedWordLength,
    setAuthorProfileSelection,
    setWordLengthSelection,
    AUTHOR_PROFILE_OPTIONS,
    WORD_LENGTH_OPTIONS,
    getAuthorProfileLabel,
    MIN_CITATIONS_REQUIRED,
    IDEAL_CITATIONS_TARGET
}) => {
    return createElement(
        'table',
        { className: 'widefat striped', style: { marginTop: '12px' } },
        createElement('thead', null,
            createElement('tr', null,
                ['Headline', 'Brief', 'Keywords', 'Market Signal', 'Citations', 'Framework', 'Author', 'Actions'].map(
                    label => createElement('th', { key: label }, label)
                )
            )
        ),
        createElement('tbody', null,
            rows.map((row, priorityIndex) => {
                const { article, metric, citationsCount, priorityScore, marketSignal } = row;
                
                const frameworkStatus = (article.framework?.status === 'completed') ? 'complete' : (article.framework?.status || 'pending');
                const progressPercent = frameworkProgress[article.id]?.percent || 5;
                const authorStatus = (article.author?.status === 'completed') ? 'complete' : (article.author?.status || 'pending');
                const authorPercent = authorProgress[article.id]?.percent || 5;
                
                const isAuthorLoading = !!authorLoading[article.id];
                const frameworkReady = frameworkStatus === 'complete' && !!article.framework?.output;
                const meetsCitationThreshold = citationsCount >= MIN_CITATIONS_REQUIRED;
                
                const band = classifyCitationBand(citationsCount, MIN_CITATIONS_REQUIRED, IDEAL_CITATIONS_TARGET);
                const citationQuality = band === 'critical' 
                    ? { label: 'Critical: under 2 citations', bg: '#fde8e8', border: '#d63638', text: '#8a2424' }
                    : band === 'below_minimum'
                    ? { label: `Below minimum: ${MIN_CITATIONS_REQUIRED} required`, bg: '#fff4e5', border: '#dba617', text: '#8a5a00' }
                    : band === 'ready'
                    ? { label: `Meets minimum. Target ${IDEAL_CITATIONS_TARGET}+`, bg: '#e7f5ff', border: '#4da3ff', text: '#0b4f8a' }
                    : { label: `Ideal coverage (${IDEAL_CITATIONS_TARGET}+)`, bg: '#edfaef', border: '#4ab866', text: '#1f6f3c' };

                const isDismissLoading = !!articleActionLoading[`dismiss:${article.id}`];
                const isDeepDiveLoading = !!articleActionLoading[`dive_deeper:${article.id}`];
                const isOpinionLoading = !!articleActionLoading[`opinion_piece:${article.id}`];
                const isQueueArticleLoading = !!queueActionLoading[`enqueue:article_creation:${article.id}`];
                const isFrameworkLoading = !!frameworkLoading[article.id];

                return createElement('tr', { key: article.id },
                    createElement('td', null, 
                        createElement('strong', null, `#${priorityIndex + 1}`), ' ', article.headline || article.title || 'Untitled',
                        createElement('div', { style: { fontSize: '11px', color: '#50575e', marginTop: '2px' } }, `Priority: ${(priorityScore * 100).toFixed(0)}`)
                    ),
                    createElement('td', null, createElement('div', { style: { maxWidth: '420px' } }, article.summary || 'No summary.')),
                    createElement('td', null, (article.keywords || article.tags || []).join(', ') || '—'),
                    createElement('td', null, createElement('strong', null, marketSignal == null ? '—' : `${marketSignal}%`)),
                    createElement('td', null, createElement('strong', null, citationsCount), 
                        createElement('div', { style: { marginTop: '4px', padding: '2px 6px', borderRadius: '999px', border: `1px solid ${citationQuality.border}`, background: citationQuality.bg, color: citationQuality.text, fontSize: '11px', fontWeight: 600 } }, citationQuality.label)
                    ),
                    createElement('td', null, (frameworkStatus === 'running' || frameworkStatus === 'queued') ? 
                        createElement('div', { style: { minWidth: '160px' } }, 
                            createElement('div', null, `${frameworkStatus} (${progressPercent}%)`),
                            createElement(ProgressBar, { value: progressPercent })
                        ) : frameworkStatus
                    ),
                    createElement('td', null, authorStatus === 'running' ? 
                        createElement('div', { style: { minWidth: '160px' } }, 
                            createElement('div', null, `Running (${authorPercent}%)`),
                            createElement(ProgressBar, { value: authorPercent })
                        ) : authorStatus
                    ),
                    createElement('td', null,
                        createElement('div', { style: { display: 'flex', gap: '8px', flexWrap: 'wrap', marginBottom: '8px' } },
                            createElement(SelectControl, { label: 'Author profile', value: getSelectedAuthorProfile(article), options: AUTHOR_PROFILE_OPTIONS, onChange: (v) => setAuthorProfileSelection(article.id, v), help: `Rec: ${getAuthorProfileLabel(getRecommendedAuthorProfile(article))}` }),
                            createElement(SelectControl, { label: 'Word length', value: getSelectedWordLength(article), options: WORD_LENGTH_OPTIONS, onChange: (v) => setWordLengthSelection(article.id, v) })
                        ),
                        (authorStatus === 'complete' && article.framework?.lite_mode === 'opinion') && createElement(Notice, { status: 'success', isDismissible: false }, 'Opinion piece written.'),
                        !meetsCitationThreshold && createElement(Button, { isSecondary: true, onClick: () => onDeepDive(article), disabled: isDeepDiveLoading }, isDeepDiveLoading ? createElement(Spinner) : 'Dive Deeper'),
                        createElement(Button, { isPrimary: true, onClick: () => onOpinion(article), disabled: isOpinionLoading }, isOpinionLoading ? createElement(Spinner) : 'Opinion Piece'),
                        createElement(Button, { isSecondary: true, onClick: () => onDismiss(article), disabled: isDismissLoading }, 'Dismiss'),
                        createElement(Button, { isSecondary: true, onClick: () => onPreview(article) }, 'Preview'),
                        !frameworkReady && createElement(Button, { isPrimary: true, onClick: () => onRegenerateFramework(article), disabled: isFrameworkLoading }, isFrameworkLoading ? createElement(Spinner) : 'Run Framework'),
                        !frameworkReady && createElement(Button, { isSecondary: true, onClick: () => onQueueFramework(article) }, 'Queue Framework'),
                        frameworkReady && createElement(Button, { isSecondary: true, onClick: () => onFrameworkPreview(article) }, 'View Framework'),
                        createElement(Button, { isSecondary: true, onClick: () => onExportFramework(article), disabled: !frameworkReady }, 'Export Framework'),
                        createElement(Button, { isSecondary: true, onClick: () => onRunAuthor(article), disabled: !meetsCitationThreshold || !frameworkReady || isAuthorLoading }, isAuthorLoading ? createElement(Spinner) : 'Run Author'),
                        createElement(Button, { isSecondary: true, onClick: () => onQueueAuthor(article), disabled: !meetsCitationThreshold || !frameworkReady || isQueueArticleLoading }, isQueueArticleLoading ? createElement(Spinner) : 'Queue Article'),
                        createElement(Button, { isSecondary: true, onClick: () => onViewDraft(article), disabled: !(authorStatus === 'complete' && article.author?.output) }, 'View Draft'),
                        createElement(Button, { isSecondary: true, onClick: () => onExportDraft(article), disabled: !(authorStatus === 'complete' && article.author?.output) }, 'Export Draft'),
                        createElement(Button, { isSecondary: true, onClick: () => onOpenEditor(article), disabled: !(authorStatus === 'complete' && article.author?.edit_url) }, 'Open in Editor')
                    )
                );
            })
        )
    );
};