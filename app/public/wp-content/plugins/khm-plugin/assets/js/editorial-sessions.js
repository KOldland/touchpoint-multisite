// Editorial Sessions React App
const { useState, useEffect, useRef } = wp.element;
const {
    Spinner,
    Notice,
    Button,
    Modal,
    ProgressBar,
    ToggleControl,
    RangeControl,
} = wp.components;
const { dispatch } = wp.data;

const apiFetch = (options) =>
    wp.apiFetch({
        ...options,
        headers: {
            'X-WP-Nonce': dualGptData.nonce,
            ...(options.headers || {}),
        },
    });

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
};

const EditorialSessionsApp = () => {
    const [allSessions, setAllSessions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedSession, setSelectedSession] = useState(null);
    const [sessionDetails, setSessionDetails] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState('created_at_desc');
    const [error, setError] = useState('');
    const [deletingSessionId, setDeletingSessionId] = useState('');

    // Modal editor state
    const [detailModalOpen, setDetailModalOpen] = useState(false);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState('');
    const [frameworkProgress, setFrameworkProgress] = useState({});
    const [authorProgress, setAuthorProgress] = useState({});
    const [frameworkLoading, setFrameworkLoading] = useState({});
    const [authorLoading, setAuthorLoading] = useState({});
    const [previewArticle, setPreviewArticle] = useState(null);
    const [frameworkPreview, setFrameworkPreview] = useState(null);
    const [authorPreview, setAuthorPreview] = useState(null);
    const [expandedPhases, setExpandedPhases] = useState({});
    const [synopsisModalOpen, setSynopsisModalOpen] = useState(false);
    const [synopsisPlan, setSynopsisPlan] = useState({});
    const [synopsisPlanLoading, setSynopsisPlanLoading] = useState(false);
    const [synopsisPlanError, setSynopsisPlanError] = useState('');
    const [synopsisGenerateLoading, setSynopsisGenerateLoading] = useState(false);
    const [synopsisTotal, setSynopsisTotal] = useState(20);
    const [focusLevel, setFocusLevel] = useState(50);
    const [focusDirty, setFocusDirty] = useState(false);
    const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);
    const authorStatusRef = useRef({});

    useEffect(() => {
        loadSessions();
    }, []);

    const loadSessions = async () => {
        try {
            setLoading(true);
            setError('');
            const response = await apiFetch({
                path: 'dual-gpt/v1/sessions',
                method: 'GET',
            });
            
            if (Array.isArray(response)) {
                setAllSessions(response);
            } else {
                console.log('Session response structure:', response);
                // Handle paginated or nested response structure if needed
                setAllSessions(response.data || response.sessions || []);
            }
        } catch (err) {
            console.error('Failed to load sessions', err);
            setError('Failed to load sessions. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const loadSessionDetails = async (sessionId) => {
        try {
            setDetailModalOpen(true);
            setDetailLoading(true);
            setDetailError('');
            setFocusDirty(false);
            const response = await apiFetch({
                path: `dual-gpt/v1/sessions/${sessionId}`,
                method: 'GET',
            });
            setSessionDetails(response);
            setSelectedSession(sessionId);
            if (response?.meta?.focus_level != null) {
                setFocusLevel(Number(response.meta.focus_level));
            }
        } catch (err) {
            console.error('Failed to load session details', err);
            setDetailError('Failed to load session details.');
        } finally {
            setDetailLoading(false);
        }
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
            setError('');
            await apiFetch({
                path: `dual-gpt/v1/sessions/${sessionId}`,
                method: 'DELETE',
            });

            if (selectedSession === sessionId) {
                setSelectedSession(null);
                setSessionDetails(null);
                setDetailModalOpen(false);
            }

            await loadSessions();
        } catch (err) {
            console.error('Failed to delete session', err);
            setError(err?.message || 'Failed to delete session. Please try again.');
        } finally {
            setDeletingSessionId('');
        }
    };

    const refreshSessionDetail = async () => {
        if (!sessionDetails || !sessionDetails.id) {
            return;
        }
        try {
            setDetailLoading(true);
            const response = await apiFetch({
                path: `dual-gpt/v1/sessions/${sessionDetails.id}`,
                method: 'GET',
            });
            setSessionDetails(response);
        } catch (err) {
            console.error('Failed to refresh session', err);
        } finally {
            setDetailLoading(false);
        }
    };

    const handleRegenerateFramework = async (article, index) => {
        if (!sessionDetails || !sessionDetails.id) {
            dispatch('core/notices').createNotice(
                'error',
                'Session not loaded. Please refresh and try again.',
                { type: 'snackbar' }
            );
            return;
        }

        try {
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
                    session_id: sessionDetails.id,
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
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Framework regeneration failed.',
                { type: 'snackbar' }
            );
        } finally {
            setFrameworkLoading((prev) => ({ ...prev, [index]: false }));
        }
    };

    const handleRunAuthorAgent = async (article) => {
        if (!sessionDetails || !sessionDetails.id || !article?.id) {
            dispatch('core/notices').createNotice(
                'error',
                'Author agent could not start: missing session or article data.',
                { type: 'snackbar' }
            );
            return;
        }

        try {
            setAuthorLoading((prev) => ({ ...prev, [article.id]: true }));
            await apiFetch({
                path: 'dual-gpt/v1/planner/run-author',
                method: 'POST',
                data: { session_id: sessionDetails.id, article_id: article.id },
            });
            dispatch('core/notices').createNotice(
                'success',
                'Author agent started.',
                { type: 'snackbar' }
            );

            await refreshSessionDetail();
        } catch (error) {
            console.error('Author agent failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Author agent failed.',
                { type: 'snackbar' }
            );
        } finally {
            setAuthorLoading((prev) => ({ ...prev, [article.id]: false }));
        }
    };

    const openPrintWindow = (html) => {
        const printWindow = window.open('', '_blank');
        if (!printWindow) return;
        printWindow.document.open();
        printWindow.document.write(html || '');
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    };

    const handleExportFramework = async (article) => {
        if (!sessionDetails || !sessionDetails.id || !article?.id) return;

        try {
            const data = await apiFetch({
                path: 'dual-gpt/v1/planner/export-framework',
                method: 'POST',
                data: { session_id: sessionDetails.id, article_id: article.id },
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

    const handleExportAuthorDraft = async (article) => {
        if (!sessionDetails || !sessionDetails.id || !article?.id) return;

        try {
            const data = await apiFetch({
                path: 'dual-gpt/v1/planner/export-author',
                method: 'POST',
                data: { session_id: sessionDetails.id, article_id: article.id },
            });
            openPrintWindow(data.html || '');
        } catch (error) {
            console.error('Author draft export failed:', error);
            dispatch('core/notices').createNotice(
                'error',
                error.message || 'Author draft export failed.',
                { type: 'snackbar' }
            );
        }
    };

    // Filter sessions by search term
    const filteredSessions = allSessions.filter(session => {
        const searchLower = searchTerm.toLowerCase();
        const title = (session.title || session.session_id || '').toLowerCase();
        const sessionId = (session.session_id || '').toLowerCase();
        return title.includes(searchLower) || sessionId.includes(searchLower);
    });

    // Sort sessions
    const sortedSessions = [...filteredSessions].sort((a, b) => {
        switch (sortBy) {
            case 'created_at_desc':
                return new Date(b.created_at || 0) - new Date(a.created_at || 0);
            case 'created_at_asc':
                return new Date(a.created_at || 0) - new Date(b.created_at || 0);
            case 'title_asc':
                return (a.title || '').localeCompare(b.title || '');
            case 'title_desc':
                return (b.title || '').localeCompare(a.title || '');
            default:
                return 0;
        }
    });

    const renderArticlesTable = () => {
        const articles = sessionDetails?.meta?.articles || [];
        if (!articles.length) {
            return wp.element.createElement('p', null, 'No articles yet.');
        }

        return wp.element.createElement(
            'table',
            { className: 'widefat striped' },
            wp.element.createElement(
                'thead',
                null,
                wp.element.createElement(
                    'tr',
                    null,
                    wp.element.createElement('th', null, 'Title'),
                    wp.element.createElement('th', null, 'Summary'),
                    wp.element.createElement('th', null, 'Keywords'),
                    wp.element.createElement('th', null, 'Framework'),
                    wp.element.createElement('th', null, 'Author'),
                    wp.element.createElement('th', null, 'Actions')
                )
            ),
            wp.element.createElement(
                'tbody',
                null,
                articles.map((article, index) => {
                    const frameworkStatus = article?.framework?.status || 'pending';
                    const authorStatusRaw = article?.author?.status || 'pending';
                    const authorStatus = authorStatusRaw === 'completed' ? 'complete' : authorStatusRaw;
                    const frameworkEntry = frameworkProgress[article.id];
                    const frameworkPercent = frameworkEntry?.percent || 5;
                    const authorEntry = authorProgress[article.id];
                    const authorPercent = authorEntry?.percent || 5;
                    const isAuthorLoading = !!authorLoading[article.id];
                    const authorEditUrl = article.author?.edit_url;
                    const frameworkReady = frameworkStatus === 'complete' && !!article.framework?.output;
                    const frameworkActionLabel = frameworkStatus === 'complete' ? 'Regenerate' : 'Generate';

                    return wp.element.createElement(
                        'tr',
                        { key: `${article.headline || article.title || article.id}-${index}` },
                        wp.element.createElement('td', null, article.headline || article.title || 'Untitled'),
                        wp.element.createElement(
                            'td',
                            null,
                            wp.element.createElement('div', { style: { maxWidth: '420px' } },
                                article.summary || article.brief || article.summary_two_sentences || 'No summary.')
                        ),
                        wp.element.createElement('td', null, (article.keywords || article.tags || []).join(', ') || '—'),
                        wp.element.createElement(
                            'td',
                            null,
                            frameworkStatus === 'running'
                                ? wp.element.createElement(
                                      'div',
                                      { style: { minWidth: '160px' } },
                                      wp.element.createElement('div', null, `Running (${frameworkPercent}%)`),
                                      wp.element.createElement(ProgressBar, { value: frameworkPercent })
                                  )
                                : frameworkStatus === 'queued'
                                  ? wp.element.createElement(
                                        'div',
                                        { style: { minWidth: '160px' } },
                                        wp.element.createElement('div', null, `Queued (${frameworkPercent}%)`),
                                        wp.element.createElement(ProgressBar, { value: frameworkPercent })
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

    const renderSessionModal = () => {
        return wp.element.createElement(
            Modal,
            {
                title: sessionDetails?.title || 'Session Editor',
                onRequestClose: () => setDetailModalOpen(false),
                style: { minWidth: '85vw' },
            },
            detailLoading
                ? wp.element.createElement(Spinner, null)
                : wp.element.createElement(
                      'div',
                      null,
                      detailError && wp.element.createElement(Notice, { status: 'error', isDismissible: false }, detailError),
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
                          wp.element.createElement('strong', null, 'Session Info'),
                          wp.element.createElement(
                              'p',
                              { style: { margin: '6px 0', fontSize: '12px', color: '#50575e' } },
                              'ID: ' + (sessionDetails?.session_id || '-')
                          ),
                          wp.element.createElement(
                              'p',
                              { style: { margin: '6px 0', fontSize: '12px', color: '#50575e' } },
                              'Status: ' + (sessionDetails?.status || 'Unknown')
                          ),
                          wp.element.createElement(
                              'p',
                              { style: { margin: '6px 0', fontSize: '12px', color: '#50575e' } },
                              'Topic: ' + (sessionDetails?.meta?.topic || '—')
                          )
                      ),
                      wp.element.createElement(
                          'div',
                          { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '16px' } },
                          wp.element.createElement('h2', null, 'Articles & Synopses'),
                          wp.element.createElement(
                              'div',
                              null,
                              wp.element.createElement(
                                  Button,
                                  {
                                      isSecondary: true,
                                      onClick: refreshSessionDetail,
                                  },
                                  'Refresh'
                              )
                          )
                      ),
                      renderArticlesTable(),
                      // Preview modals
                      previewArticle &&
                          wp.element.createElement(
                              Modal,
                              {
                                  title: previewArticle.headline || previewArticle.title || 'Article Preview',
                                  onRequestClose: () => setPreviewArticle(null),
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
                                  )
                          ),
                      frameworkPreview &&
                          wp.element.createElement(
                              Modal,
                              {
                                  title: 'Framework Preview',
                                  onRequestClose: () => setFrameworkPreview(null),
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
                                  )
                              ),
                              wp.element.createElement('p', null, frameworkPreview.title || 'Framework'),
                              wp.element.createElement(
                                  'div',
                                  { style: { whiteSpace: 'pre-wrap' } },
                                  frameworkPreview.framework?.output || 'No framework output.'
                              )
                          ),
                      authorPreview &&
                          wp.element.createElement(
                              Modal,
                              {
                                  title: 'Author Draft',
                                  onRequestClose: () => setAuthorPreview(null),
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
                  )
        );
    };

    if (loading) {
        return wp.element.createElement('div', { style: { padding: '20px', textAlign: 'center' } },
            wp.element.createElement(Spinner, null),
            wp.element.createElement('p', null, 'Loading sessions...')
        );
    }

    return wp.element.createElement('div', { className: 'editorial-sessions', style: { padding: '20px' } },
        wp.element.createElement('h1', null, 'Sessions'),
        error && wp.element.createElement(Notice, { status: 'error', isDismissible: true }, error),
        wp.element.createElement('div', { style: { marginBottom: '20px' } },
            wp.element.createElement('input', {
                type: 'text',
                placeholder: 'Search by title or session ID...',
                value: searchTerm,
                onChange: (e) => setSearchTerm(e.target.value),
                style: {
                    padding: '8px 12px',
                    marginRight: '10px',
                    width: '300px',
                    border: '1px solid #ccc',
                    borderRadius: '4px'
                }
            }),
            wp.element.createElement('select', {
                value: sortBy,
                onChange: (e) => setSortBy(e.target.value),
                style: {
                    padding: '8px 12px',
                    border: '1px solid #ccc',
                    borderRadius: '4px'
                }
            },
                wp.element.createElement('option', { value: 'created_at_desc' }, 'Newest First'),
                wp.element.createElement('option', { value: 'created_at_asc' }, 'Oldest First'),
                wp.element.createElement('option', { value: 'title_asc' }, 'Title A-Z'),
                wp.element.createElement('option', { value: 'title_desc' }, 'Title Z-A')
            )
        ),
        wp.element.createElement('div', { style: { display: 'flex', gap: '20px' } },
            // Sessions list
            wp.element.createElement('div', { style: { flex: 1 } },
                wp.element.createElement('h3', null, `Sessions (${sortedSessions.length})`),
                sortedSessions.length === 0 ? 
                    wp.element.createElement('p', null, 'No sessions found.') :
                    wp.element.createElement('table', { className: 'widefat striped' },
                        wp.element.createElement('thead', null,
                            wp.element.createElement('tr', null,
                                wp.element.createElement('th', null, 'Title'),
                                wp.element.createElement('th', null, 'Created'),
                                wp.element.createElement('th', null, 'Status'),
                                wp.element.createElement('th', null, 'Actions')
                            )
                        ),
                        wp.element.createElement('tbody', null,
                            sortedSessions.map(session => {
                                const sessionId = session.session_id || session.id;
                                return (
                                wp.element.createElement('tr', {
                                    key: sessionId,
                                    style: { backgroundColor: selectedSession === sessionId ? '#f5f5f5' : 'transparent' }
                                },
                                    wp.element.createElement('td', null, session.title || sessionId),
                                    wp.element.createElement('td', null, session.created_at ? formatDate(session.created_at) : '-'),
                                    wp.element.createElement('td', null, session.status || '-'),
                                    wp.element.createElement('td', null,
                                        wp.element.createElement('button', {
                                            className: 'button button-primary button-small',
                                            onClick: () => loadSessionDetails(sessionId),
                                            style: { marginRight: '5px' }
                                        }, 'VIEW'),
                                        wp.element.createElement('button', {
                                            className: 'button button-small',
                                            onClick: () => deleteSession(sessionId),
                                            disabled: deletingSessionId === sessionId,
                                            style: { marginLeft: '5px', color: '#b32d2e', borderColor: '#b32d2e' }
                                        }, deletingSessionId === sessionId ? 'Deleting…' : 'Delete')
                                    )
                                )
                                );
                            })
                        )
                    )
            ),
            // Session modal editor
            detailModalOpen && sessionDetails ? renderSessionModal() : null
        )
    );
};

// Mount the app
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('editorial-sessions-app');
    if (container) {
        wp.element.render(wp.element.createElement(EditorialSessionsApp), container);
    }
});